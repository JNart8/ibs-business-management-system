<?php

/**
 * Expenses Controller
 * Manage operating expenses, linked to financial accounts.
 */

if (!defined('APP_START')) {
    die('Direct access not permitted');
}

$db = Database::getInstance();

// Parse segments
$segments = array_values(array_filter(explode('/', trim($path, '/'))));
$action = $segments[1] ?? 'index';
$id     = $segments[2] ?? null;

switch ($action) {
    case 'index':
        listExpenses($db);
        break;
    case 'create':
        $method === 'POST' ? storeExpense($db) : showExpenseForm($db);
        break;
    case 'delete':
        if (!$id) redirect(BASE_URL . '/expenses', 'error', 'Expense ID required');
        deleteExpense($db, $id);
        break;
    default:
        http_response_code(404);
        echo "<h1>Page Not Found</h1><a href='" . BASE_URL . "/expenses'>← Back to Expenses</a>";
}

// ============================================================
// FUNCTIONS
// ============================================================

function listExpenses($db)
{
    // Filter parameters
    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo   = $_GET['date_to']   ?? date('Y-m-t');
    $category = $_GET['category']  ?? '';
    $accountId = $_GET['account_id'] ?? '';

    $where = ["expense_date BETWEEN ? AND ?"];
    $params = [$dateFrom, $dateTo];

    if (!empty($category)) {
        $where[] = "category = ?";
        $params[] = $category;
    }

    if (!empty($accountId)) {
        $where[] = "account_id = ?";
        $params[] = safeInt($accountId);
    }

    $whereClause = implode(" AND ", $where);

    $expenses = $db->fetchAll("
        SELECT e.*, a.name as account_name
        FROM expenses e
        LEFT JOIN accounts a ON e.account_id = a.id
        WHERE {$whereClause}
        ORDER BY e.expense_date DESC, e.created_at DESC
    ", $params);

    $categories = $db->fetchAll("SELECT DISTINCT category FROM expenses ORDER BY category ASC");
    $accounts   = $db->fetchAll("SELECT id, name FROM accounts WHERE is_active = 1 ORDER BY name ASC");

    // Calculate totals
    $totals = $db->fetchOne("
        SELECT SUM(amount) as total_amount, SUM(charges) as total_charges
        FROM expenses
        WHERE {$whereClause}
    ", $params);

    $totalAmount  = safeFloat($totals['total_amount'] ?? 0.00);
    $totalCharges = safeFloat($totals['total_charges'] ?? 0.00);

    $pageTitle = 'Operating Expenses';
    include APP_PATH . '/views/expenses/index.php';
}

function showExpenseForm($db)
{
    $accounts = $db->fetchAll("SELECT * FROM accounts WHERE is_active = 1 ORDER BY name ASC");
    $categories = $db->fetchAll("SELECT DISTINCT category FROM expenses ORDER BY category ASC");
    $pageTitle = 'Record Expense';
    include APP_PATH . '/views/expenses/create.php';
}

function storeExpense($db)
{
    $expenseDate = $_POST['expense_date'] ?? date('Y-m-d');
    $category    = trim($_POST['category'] ?? '');
    $newCategory = trim($_POST['new_category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $amount      = safeFloat($_POST['amount'] ?? 0.00);
    $accountId   = safeInt($_POST['account_id'] ?? 0);
    $charges     = safeFloat($_POST['charges'] ?? 0.00);
    $notes       = trim($_POST['notes'] ?? '');

    // Resolve category
    $finalCategory = !empty($newCategory) ? $newCategory : $category;

    if (empty($finalCategory)) {
        redirect(BASE_URL . '/expenses/create', 'error', 'Expense category is required.');
    }

    if ($amount <= 0) {
        redirect(BASE_URL . '/expenses/create', 'error', 'Amount must be greater than zero.');
    }

    if ($accountId <= 0) {
        redirect(BASE_URL . '/expenses/create', 'error', 'Please select a payment account.');
    }

    $account = $db->fetchOne("SELECT * FROM accounts WHERE id = ? AND is_active = 1", [$accountId]);
    if (!$account) {
        redirect(BASE_URL . '/expenses/create', 'error', 'Selected account is invalid or inactive.');
    }

    $totalDeduction = $amount + $charges;
    if ($account['balance'] < $totalDeduction) {
        redirect(BASE_URL . '/expenses/create', 'error', 'Insufficient balance in the selected payment account.');
    }

    // Map account type to payment method string
    $paymentMethodMap = [
        'cash' => 'cash',
        'mobile_money' => 'mobile',
        'bank' => 'bank'
    ];
    $paymentMethod = $paymentMethodMap[$account['type']] ?? 'cash';

    try {
        $db->beginTransaction();

        // 1. Insert into expenses table
        $db->query("
            INSERT INTO expenses (expense_date, category, description, amount, account_id, charges, payment_method, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ", [$expenseDate, $finalCategory, $description, $amount, $accountId, $charges, $paymentMethod, $notes]);

        $expenseId = $db->lastInsertId();

        // 2. Deduct from account balance
        $balanceBefore = $account['balance'];
        $balanceAfter  = $balanceBefore - $totalDeduction;
        $db->query("UPDATE accounts SET balance = ? WHERE id = ?", [$balanceAfter, $accountId]);

        // 3. Log main withdrawal transaction
        $db->query("
            INSERT INTO account_transactions (account_id, transaction_type, amount, balance_before, balance_after, reference_type, reference_id, notes, user_id)
            VALUES (?, 'withdrawal', ?, ?, ?, 'expense', ?, ?, ?)
        ", [$accountId, $amount, $balanceBefore, $balanceBefore - $amount, $expenseId, "Expense: " . $finalCategory . " - " . $description, $_SESSION['user_id']]);

        // 4. Log charges transaction if any
        if ($charges > 0) {
            $db->query("
                INSERT INTO account_transactions (account_id, transaction_type, amount, balance_before, balance_after, reference_type, reference_id, notes, user_id)
                VALUES (?, 'charge', ?, ?, ?, 'expense', ?, ?, ?)
            ", [$accountId, $charges, $balanceBefore - $amount, $balanceAfter, $expenseId, "Transaction charges for expense #" . $expenseId, $_SESSION['user_id']]);
        }

        $db->commit();
        redirect(BASE_URL . '/expenses', 'success', 'Expense recorded successfully.');
    } catch (Exception $e) {
        $db->rollback();
        redirect(BASE_URL . '/expenses/create', 'error', 'Failed to record expense: ' . $e->getMessage());
    }
}

function deleteExpense($db, $id)
{
    $expense = $db->fetchOne("SELECT * FROM expenses WHERE id = ?", [$id]);
    if (!$expense) {
        redirect(BASE_URL . '/expenses', 'error', 'Expense not found.');
    }

    try {
        $db->beginTransaction();

        $accountId = $expense['account_id'];
        $refundAmount = safeFloat($expense['amount']) + safeFloat($expense['charges']);

        if ($accountId) {
            $account = $db->fetchOne("SELECT * FROM accounts WHERE id = ?", [$accountId]);
            if ($account) {
                // Refund account balance
                $balanceBefore = $account['balance'];
                $balanceAfter  = $balanceBefore + $refundAmount;
                $db->query("UPDATE accounts SET balance = ? WHERE id = ?", [$balanceAfter, $accountId]);

                // Record refund/deposit transaction log
                $db->query("
                    INSERT INTO account_transactions (account_id, transaction_type, amount, balance_before, balance_after, reference_type, reference_id, notes, user_id)
                    VALUES (?, 'deposit', ?, ?, ?, 'expense', ?, ?, ?)
                ", [$accountId, $refundAmount, $balanceBefore, $balanceAfter, $id, "Reversal/Refund of Deleted Expense #" . $id, $_SESSION['user_id']]);
            }
        }

        // Delete from database
        $db->query("DELETE FROM expenses WHERE id = ?", [$id]);

        $db->commit();
        redirect(BASE_URL . '/expenses', 'success', 'Expense deleted successfully and account balance restored.');
    } catch (Exception $e) {
        $db->rollback();
        redirect(BASE_URL . '/expenses', 'error', 'Failed to delete expense: ' . $e->getMessage());
    }
}
