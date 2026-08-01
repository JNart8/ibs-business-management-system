<?php

/**
 * Customer Controller
 * Handles all customer operations including credit & deposits
 */

if (!defined('APP_START')) {
    die('Direct access not permitted');
}

$db = Database::getInstance();

// Parse URL
$requestUri   = $_SERVER['REQUEST_URI'];
$scriptName   = dirname($_SERVER['SCRIPT_NAME']);
$basePath     = rtrim($scriptName, '/');
$fullPath     = parse_url($requestUri, PHP_URL_PATH);
$relativePath = str_replace($basePath, '', $fullPath);
$relativePath = str_replace('/public', '', $relativePath);
$relativePath = '/' . trim($relativePath, '/');

$segments = array_values(array_filter(explode('/', trim($relativePath, '/'))));

if (count($segments) === 1) {
    $action = 'index';
    $id     = null;
} elseif (count($segments) === 2) {
    $action = $segments[1];
    $id     = null;
} else {
    $action = $segments[1];
    $id     = $segments[2];
}

// Route request
switch ($action) {
    case 'index':
        listCustomers($db);
        break;

    case 'create':
        $_SERVER['REQUEST_METHOD'] === 'POST'
            ? createCustomer($db)
            : showCreateForm($db);
        break;

    case 'view':
        if (!$id) redirect(BASE_URL . '/customers', 'error', 'Customer ID required');
        viewCustomer($db, $id);
        break;

    case 'edit':
        if (!$id) redirect(BASE_URL . '/customers', 'error', 'Customer ID required');
        $_SERVER['REQUEST_METHOD'] === 'POST'
            ? updateCustomer($db, $id)
            : showEditForm($db, $id);
        break;

    case 'delete':
        if (!$id) redirect(BASE_URL . '/customers', 'error', 'Customer ID required');
        deleteCustomer($db, $id);
        break;

    case 'deposit':
        if (!$id) redirect(BASE_URL . '/customers', 'error', 'Customer ID required');
        $_SERVER['REQUEST_METHOD'] === 'POST'
            ? processDeposit($db, $id)
            : showDepositForm($db, $id);
        break;

    case 'edit-deposit':
        if (!$id) redirect(BASE_URL . '/customers', 'error', 'Transaction ID required');
        $_SERVER['REQUEST_METHOD'] === 'POST'
            ? updateCustomerDeposit($db, $id)
            : showEditCustomerDepositForm($db, $id);
        break;

    case 'delete-deposit':
        if (!$id) redirect(BASE_URL . '/customers', 'error', 'Transaction ID required');
        deleteCustomerDeposit($db, $id);
        break;

    case 'adjust-credit':
        if (!$id) redirect(BASE_URL . '/customers', 'error', 'Customer ID required');
        $_SERVER['REQUEST_METHOD'] === 'POST'
            ? adjustCreditLimit($db, $id)
            : showCreditForm($db, $id);
        break;
    case 'statement':
        if (!$id) redirect(BASE_URL . '/customers', 'error', 'Customer ID required');
        $_SERVER['REQUEST_METHOD'] === 'POST'
            ? generateStatement($db, $id)
            : showStatementForm($db, $id);
        break;

    case 'search':
        searchCustomers($db);
        break;

    default:
        http_response_code(404);
        echo "<h1>Action not found</h1>";
        echo "<a href='" . BASE_URL . "/customers'>← Back to Customers</a>";
        break;
}

// ============================================================
// FUNCTIONS
// ============================================================

/**
 * List all customers with balance info
 */
function listCustomers($db)
{
    $search = trim($_GET['search'] ?? '');
    $filter = $_GET['filter'] ?? 'all'; // all | owing | credit | default
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = ITEMS_PER_PAGE;
    $offset = ($page - 1) * $limit;

    $params = [];
    $where  = "WHERE 1=1";

    // Search filter
    if (!empty($search)) {
        $where   .= " AND (c.full_name LIKE ? OR c.phone LIKE ? OR c.customer_code LIKE ? OR c.email LIKE ?)";
        $term     = "%$search%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    // Balance filter
    switch ($filter) {
        case 'owing':
            $where .= " AND c.current_balance < 0";
            break;
        case 'credit':
            $where .= " AND c.current_balance > 0";
            break;
        case 'active':
            $where .= " AND c.is_active = 1 AND c.is_default = 0";
            break;
        case 'default':
            $where .= " AND c.is_default = 1";
            break;
        default:
            $where .= " AND c.is_default = 0"; // exclude walk-in from main list
            break;
    }

    // Total count
    $total      = $db->fetchOne("SELECT COUNT(*) as cnt FROM customers c $where", $params);
    $totalCount = intval($total['cnt'] ?? 0);
    $totalPages = max(1, ceil($totalCount / $limit));

    // Fetch customers
    $customers = $db->fetchAll("
        SELECT
            c.id,
            c.customer_code,
            c.full_name,
            c.phone,
            c.email,
            c.credit_limit,
            c.current_balance,
            c.total_purchases,
            c.is_active,
            c.is_default,
            c.created_at,
            COUNT(s.id)                     AS total_orders,
            MAX(s.sale_date)                AS last_purchase
        FROM customers c
        LEFT JOIN sales s ON s.customer_id = c.id
        $where
        GROUP BY c.id
        ORDER BY c.full_name ASC
        LIMIT ? OFFSET ?
    ", array_merge($params, [$limit, $offset]));

    // Summary stats
    $stats = $db->fetchOne("
        SELECT
            COUNT(*)                                                    AS total_customers,
            SUM(CASE WHEN current_balance < 0 THEN 1 ELSE 0 END)       AS owing_count,
            SUM(CASE WHEN current_balance > 0 THEN 1 ELSE 0 END)       AS credit_count,
            COALESCE(SUM(CASE WHEN current_balance < 0
                              THEN ABS(current_balance) ELSE 0 END), 0) AS total_owed,
            COALESCE(SUM(CASE WHEN current_balance > 0
                              THEN current_balance ELSE 0 END), 0)      AS total_credit
        FROM customers
        WHERE is_default = 0
    ");

    $pageTitle = 'Customers';
    include APP_PATH . '/views/customers/index.php';
}

/**
 * Show create form
 */
function showCreateForm($db)
{
    $pageTitle = 'Add New Customer';
    include APP_PATH . '/views/customers/create.php';
}

/**
 * Create customer
 */
function createCustomer($db)
{
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect(BASE_URL . '/customers/create', 'error', 'Invalid form submission');
        return;
    }

    $fullName    = trim($_POST['full_name']    ?? '');
    $phone       = trim($_POST['phone']        ?? '');
    $email       = trim($_POST['email']        ?? '');
    $address     = trim($_POST['address']      ?? '');
    $creditLimit = floatval($_POST['credit_limit'] ?? 0);
    $code        = trim($_POST['customer_code'] ?? '');

    // Auto-generate code if empty
    if (empty($code)) {
        $code = generateCustomerCode($db);
    }

    // Validate
    $errors = [];
    if (empty($fullName))     $errors[] = 'Full name is required';
    if (empty($phone))        $errors[] = 'Phone number is required';
    if ($creditLimit < 0)     $errors[] = 'Credit limit cannot be negative';

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address';
    }

    // Check duplicate phone
    $existingPhone = $db->fetchOne("SELECT id FROM customers WHERE phone = ?", [$phone]);
    if ($existingPhone) $errors[] = 'Phone number already registered';

    // Check duplicate code
    $existingCode = $db->fetchOne("SELECT id FROM customers WHERE customer_code = ?", [$code]);
    if ($existingCode) $errors[] = 'Customer code already exists';

    if (!empty($errors)) {
        $_SESSION['errors']    = $errors;
        $_SESSION['old_input'] = $_POST;
        redirect(BASE_URL . '/customers/create', 'error', implode(' | ', $errors));
        return;
    }

    try {
        $db->query("
            INSERT INTO customers
                (customer_code, full_name, phone, email, address, credit_limit)
            VALUES (?, ?, ?, ?, ?, ?)
        ", [$code, $fullName, $phone, $email ?: null, $address ?: null, $creditLimit]);

        redirect(BASE_URL . '/customers', 'success', "Customer \"$fullName\" added successfully");
    } catch (Exception $e) {
        error_log('Create customer error: ' . $e->getMessage());
        redirect(BASE_URL . '/customers/create', 'error', 'Failed to create customer');
    }
}

/**
 * View single customer - full details & transaction history
 */
function viewCustomer($db, $id)
{
    $customer = $db->fetchOne("SELECT * FROM customers WHERE id = ?", [$id]);
    if (!$customer) {
        redirect(BASE_URL . '/customers', 'error', 'Customer not found');
        return;
    }

    // Recent transactions
    $transactions = $db->fetchAll("
        SELECT
            ct.*,
            u.full_name AS user_name
        FROM customer_transactions ct
        LEFT JOIN users u ON ct.user_id = u.id
        WHERE ct.customer_id = ?
        ORDER BY ct.created_at DESC
        LIMIT 30
    ", [$id]);

    // Recent sales
    $recentSales = $db->fetchAll("
        SELECT
            s.id,
            s.sale_number,
            s.total_amount,
            s.amount_paid,
            s.amount_due,
            s.payment_status,
            s.notes,
            s.sale_date
        FROM sales s
        WHERE s.customer_id = ?
        ORDER BY s.sale_date DESC
        LIMIT 10
    ", [$id]);

    // Sales summary
    $salesSummary = $db->fetchOne("
        SELECT
            COUNT(*)                            AS total_orders,
            COALESCE(SUM(total_amount),  0)     AS total_spent,
            COALESCE(SUM(amount_paid),   0)     AS total_paid,
            COALESCE(SUM(amount_due),    0)     AS total_outstanding,
            COALESCE(AVG(total_amount),  0)     AS avg_order_value,
            MAX(sale_date)                      AS last_purchase
        FROM sales
        WHERE customer_id = ?
    ", [$id]);

    $pageTitle = 'Customer: ' . $customer['full_name'];
    include APP_PATH . '/views/customers/view.php';
}

/**
 * Show edit form
 */
function showEditForm($db, $id)
{
    $customer = $db->fetchOne("SELECT * FROM customers WHERE id = ?", [$id]);
    if (!$customer) {
        redirect(BASE_URL . '/customers', 'error', 'Customer not found');
        return;
    }

    $pageTitle = 'Edit Customer';
    include APP_PATH . '/views/customers/edit.php';
}

/**
 * Update customer
 */
function updateCustomer($db, $id)
{
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect(BASE_URL . '/customers/edit/' . $id, 'error', 'Invalid form submission');
        return;
    }

    $customer = $db->fetchOne("SELECT * FROM customers WHERE id = ?", [$id]);
    if (!$customer) {
        redirect(BASE_URL . '/customers', 'error', 'Customer not found');
        return;
    }

    $fullName    = trim($_POST['full_name']    ?? '');
    $phone       = trim($_POST['phone']        ?? '');
    $email       = trim($_POST['email']        ?? '');
    $address     = trim($_POST['address']      ?? '');
    $is_active   = isset($_POST['is_active']) ? 1 : 0;

    $errors = [];
    if (empty($fullName)) $errors[] = 'Full name is required';
    if (empty($phone))    $errors[] = 'Phone number is required';
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address';
    }

    // Check duplicate phone (excluding current)
    $existingPhone = $db->fetchOne(
        "SELECT id FROM customers WHERE phone = ? AND id != ?",
        [$phone, $id]
    );
    if ($existingPhone) $errors[] = 'Phone number already registered to another customer';

    if (!empty($errors)) {
        $_SESSION['errors']    = $errors;
        $_SESSION['old_input'] = $_POST;
        redirect(BASE_URL . '/customers/edit/' . $id, 'error', implode(' | ', $errors));
        return;
    }

    try {
        $db->query("
            UPDATE customers
            SET full_name  = ?,
                phone      = ?,
                email      = ?,
                address    = ?,
                is_active  = ?
            WHERE id = ?
        ", [$fullName, $phone, $email ?: null, $address ?: null, $is_active, $id]);

        redirect(BASE_URL . '/customers/view/' . $id, 'success', 'Customer updated successfully');
    } catch (Exception $e) {
        error_log('Update customer error: ' . $e->getMessage());
        redirect(BASE_URL . '/customers/edit/' . $id, 'error', 'Failed to update customer');
    }
}

/**
 * Delete customer (soft delete if has transactions)
 */
function deleteCustomer($db, $id)
{
    $customer = $db->fetchOne("SELECT * FROM customers WHERE id = ?", [$id]);
    if (!$customer) {
        redirect(BASE_URL . '/customers', 'error', 'Customer not found');
        return;
    }

    // Protect walk-in customer
    if ($customer['is_default']) {
        redirect(BASE_URL . '/customers', 'error', 'Cannot delete the default walk-in customer');
        return;
    }

    $hasSales = $db->fetchOne("SELECT COUNT(*) as cnt FROM sales WHERE customer_id = ?", [$id]);

    if (intval($hasSales['cnt']) > 0) {
        // Soft delete
        $db->query("UPDATE customers SET is_active = 0 WHERE id = ?", [$id]);
        redirect(BASE_URL . '/customers', 'success', 'Customer deactivated (has sales history)');
    } else {
        $db->query("DELETE FROM customer_transactions WHERE customer_id = ?", [$id]);
        $db->query("DELETE FROM customers WHERE id = ?", [$id]);
        redirect(BASE_URL . '/customers', 'success', 'Customer deleted successfully');
    }
}

/**
 * Show deposit form
 */
function showDepositForm($db, $id)
{
    $customer = $db->fetchOne("SELECT * FROM customers WHERE id = ?", [$id]);
    if (!$customer) {
        redirect(BASE_URL . '/customers', 'error', 'Customer not found');
        return;
    }

    // Block walk-in customers
    if ($customer['is_default'] == 1) {
        redirect(BASE_URL . '/customers', 'error', 'Walk-in customers cannot make deposits');
        return;
    }

    $accounts = $db->fetchAll("SELECT id, name, type, provider, balance FROM accounts WHERE is_active = 1 AND is_suspense = 0 ORDER BY name ASC");
    $pageTitle = 'Customer Deposit';
    include APP_PATH . '/views/customers/deposit.php';
}

/**
 * Process a deposit with auto-apply to outstanding sales
 */
function processDeposit($db, $id)
{
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect(BASE_URL . '/customers/deposit/' . $id, 'error', 'Invalid form submission');
        return;
    }

    $customer = $db->fetchOne("SELECT * FROM customers WHERE id = ?", [$id]);
    if (!$customer) {
        redirect(BASE_URL . '/customers', 'error', 'Customer not found');
        return;
    }

    // Block walk-in customers from depositing
    if ($customer['is_default'] == 1) {
        redirect(BASE_URL . '/customers', 'error', 'Walk-in customers cannot make deposits');
        return;
    }

    $amount    = floatval($_POST['amount'] ?? 0);
    $accountId = intval($_POST['account_id'] ?? 0);
    $notes     = trim($_POST['notes'] ?? '');
    $depositDate = trim($_POST['deposit_date'] ?? '');

    // Validate optional deposit date
    $depositDateSql = null;
    if (!empty($depositDate)) {
        $dateObj = DateTime::createFromFormat('Y-m-d', $depositDate);
        if (!$dateObj || $dateObj > new DateTime('today')) {
            redirect(BASE_URL . '/customers/deposit/' . $id, 'error', 'Invalid deposit date — must be today or a past date');
            return;
        }
        $depositDateSql = $dateObj->format('Y-m-d') . ' 12:00:00';
    }

    if ($amount <= 0) {
        redirect(BASE_URL . '/customers/deposit/' . $id, 'error', 'Amount must be greater than zero');
        return;
    }

    if ($accountId <= 0) {
        redirect(BASE_URL . '/customers/deposit/' . $id, 'error', 'Please select a destination account');
        return;
    }

    // Validate account
    $account = $db->fetchOne("SELECT * FROM accounts WHERE id = ? AND is_active = 1 AND is_suspense = 0", [$accountId]);
    if (!$account) {
        redirect(BASE_URL . '/customers/deposit/' . $id, 'error', 'Selected account not found or inactive');
        return;
    }

    // Map account type to payment method
    $paymentMethod = 'cash';
    if ($account['type'] === 'mobile_money') {
        $paymentMethod = 'mobile';
    } elseif ($account['type'] === 'bank') {
        $paymentMethod = 'bank';
    }

    $userId         = $_SESSION['user_id'] ?? null;
    $balanceBefore  = floatval($customer['current_balance']);

    try {
        $db->beginTransaction();

        // 1. Log customer deposit transaction
        $balanceAfterDeposit = $balanceBefore + $amount;
        if ($depositDateSql) {
            $db->query("
                INSERT INTO customer_transactions
                    (customer_id, transaction_type, amount, balance_before,
                     balance_after, payment_method, notes, user_id, created_at)
                VALUES (?, 'deposit', ?, ?, ?, ?, ?, ?, ?)
            ", [
                $id,
                $amount,
                $balanceBefore,
                $balanceAfterDeposit,
                $paymentMethod,
                $notes ?: "Deposit of " . formatMoney($amount),
                $userId,
                $depositDateSql
            ]);
        } else {
            $db->query("
                INSERT INTO customer_transactions
                    (customer_id, transaction_type, amount, balance_before,
                     balance_after, payment_method, notes, user_id)
                VALUES (?, 'deposit', ?, ?, ?, ?, ?, ?)
            ", [
                $id,
                $amount,
                $balanceBefore,
                $balanceAfterDeposit,
                $paymentMethod,
                $notes ?: "Deposit of " . formatMoney($amount),
                $userId
            ]);
        }

        $customerTxId = $db->lastInsertId();

        // 2. Credit the financial account using helper
        recordAccountTransaction(
            $db,
            $paymentMethod,
            $amount,
            'deposit',
            'customer_deposit',
            $customerTxId,
            "Deposit from customer: " . $customer['full_name'] . ($notes ? " | " . $notes : ""),
            $accountId
        );

        // Update customer balance with deposit
        $db->query(
            "UPDATE customers SET current_balance = current_balance + ? WHERE id = ?",
            [$amount, $id]
        );

        // Auto-apply deposit to outstanding sales (oldest first - FIFO)
        $remainingDeposit = $amount;
        $paymentsApplied = [];

        // Get all unpaid and partial sales for this customer (oldest first)
        $outstandingSales = $db->fetchAll("
            SELECT id, sale_number, amount_due, amount_paid, total_amount
            FROM sales
            WHERE customer_id = ?
              AND payment_status IN ('unpaid', 'partial')
              AND amount_due > 0
            ORDER BY sale_date ASC
        ", [$id]);

        $currentBalance = $balanceAfterDeposit;

        foreach ($outstandingSales as $sale) {
            if ($remainingDeposit <= 0.01) break; // Float precision check

            $amountDue = floatval($sale['amount_due']);
            $paymentAmount = min($remainingDeposit, $amountDue);

            // Update sale payment status
            $newAmountPaid = floatval($sale['amount_paid']) + $paymentAmount;
            $newAmountDue = $amountDue - $paymentAmount;
            $newStatus = $newAmountDue <= 0.01 ? 'paid' : 'partial'; // 0.01 for float precision

            $db->query("
                UPDATE sales
                SET amount_paid = ?, amount_due = ?, payment_status = ?
                WHERE id = ?
            ", [$newAmountPaid, max(0, $newAmountDue), $newStatus, $sale['id']]);

            // Log payment transaction for this sale
            $balanceBeforePayment = $currentBalance;
            $balanceAfterPayment = $currentBalance; // - $paymentAmount; already updated with deposit, so we just log the payment without changing balance again

            $db->query("
                INSERT INTO customer_transactions
                    (customer_id, transaction_type, amount, balance_before,
                     balance_after, reference_type, reference_id, payment_method, notes, user_id)
                VALUES (?, 'payment', ?, ?, ?, 'sale', ?, 'deposit', ?, ?)
            ", [
                $id,
                -$paymentAmount, // Negative because it's reducing their deposit balance
                $balanceBeforePayment,
                $balanceAfterPayment,
                $sale['id'],
                "Auto-payment from deposit for sale " . $sale['sale_number'],
                $userId
            ]);

            // Update customer balance (reduce by payment amount)
            //commented out to avoid double updating balance since it is already updated with deposit
            // $db->query(
            //     "UPDATE customers SET current_balance = current_balance - ? WHERE id = ?",
            //     [$paymentAmount, $id]
            // );

            $currentBalance = $balanceAfterPayment;
            $remainingDeposit -= $paymentAmount;
            $paymentsApplied[] = [
                'sale_number' => $sale['sale_number'],
                'amount' => $paymentAmount,
                'status' => $newStatus
            ];
        }

        $db->commit();

        // Build success message
        $message = "Deposit of " . formatMoney($amount) . " recorded successfully";
        if (!empty($paymentsApplied)) {
            $message .= ". Auto-applied to " . count($paymentsApplied) . " sale(s)";
        }

        redirect(BASE_URL . '/customers/view/' . $id, 'success', $message);
    } catch (Exception $e) {
        $db->rollback();
        error_log('Deposit error: ' . $e->getMessage());
        redirect(BASE_URL . '/customers/deposit/' . $id, 'error', 'Failed to process deposit');
    }
}

/**
 * Show credit limit adjustment form
 */
function showCreditForm($db, $id)
{
    $customer = $db->fetchOne("SELECT * FROM customers WHERE id = ?", [$id]);
    if (!$customer) {
        redirect(BASE_URL . '/customers', 'error', 'Customer not found');
        return;
    }

    $pageTitle = 'Adjust Credit Limit';
    include APP_PATH . '/views/customers/credit.php';
}

/**
 * Adjust credit limit
 */
function adjustCreditLimit($db, $id)
{
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect(BASE_URL . '/customers/adjust-credit/' . $id, 'error', 'Invalid form submission');
        return;
    }

    $customer = $db->fetchOne("SELECT * FROM customers WHERE id = ?", [$id]);
    if (!$customer) {
        redirect(BASE_URL . '/customers', 'error', 'Customer not found');
        return;
    }

    $newLimit = floatval($_POST['credit_limit'] ?? 0);
    $notes    = trim($_POST['notes'] ?? '');

    if ($newLimit < 0) {
        redirect(BASE_URL . '/customers/adjust-credit/' . $id, 'error', 'Credit limit cannot be negative');
        return;
    }

    try {
        $db->query("UPDATE customers SET credit_limit = ? WHERE id = ?", [$newLimit, $id]);

        // Log the adjustment
        $userId = $_SESSION['user_id'] ?? null;
        $db->query("
            INSERT INTO customer_transactions
                (customer_id, transaction_type, amount, balance_before,
                 balance_after, notes, user_id)
            VALUES (?, 'adjustment', 0, ?, ?, ?, ?)
        ", [
            $id,
            $customer['current_balance'],
            $customer['current_balance'],
            "Credit limit changed from " . formatMoney($customer['credit_limit'])
                . " to " . formatMoney($newLimit)
                . ($notes ? ". $notes" : ''),
            $userId
        ]);

        redirect(
            BASE_URL . '/customers/view/' . $id,
            'success',
            "Credit limit updated to " . formatMoney($newLimit)
        );
    } catch (Exception $e) {
        error_log('Credit limit error: ' . $e->getMessage());
        redirect(BASE_URL . '/customers/adjust-credit/' . $id, 'error', 'Failed to update credit limit');
    }
}

/**
 * AJAX customer search for POS
 */
function searchCustomers($db)
{
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');

    if (strlen($q) < 1) {
        echo json_encode([]);
        return;
    }

    $customers = $db->fetchAll("
        SELECT id, customer_code, full_name, phone, current_balance, credit_limit, is_default
        FROM customers
        WHERE is_active = 1
          AND (full_name LIKE ? OR phone LIKE ? OR customer_code LIKE ?)
        ORDER BY full_name ASC
        LIMIT 10
    ", ["%$q%", "%$q%", "%$q%"]);

    echo json_encode($customers);
}

/**
 * Show statement form - date range selection
 */
function showStatementForm($db, $id)
{
    $customer = $db->fetchOne("SELECT * FROM customers WHERE id = ?", [$id]);
    if (!$customer) {
        redirect(BASE_URL . '/customers', 'error', 'Customer not found');
        return;
    }

    // Block walk-in customers
    if ($customer['is_default'] == 1) {
        redirect(BASE_URL . '/customers', 'error', 'Cannot generate statement for walk-in customer');
        return;
    }

    $pageTitle = 'Customer Statement';
    include APP_PATH . '/views/customers/statement_form.php';
}

/**
 * Generate and display statement
 */
function generateStatement($db, $id)
{
    $customer = $db->fetchOne("SELECT * FROM customers WHERE id = ?", [$id]);
    if (!$customer) {
        redirect(BASE_URL . '/customers', 'error', 'Customer not found');
        return;
    }

    // Block walk-in customers
    if ($customer['is_default'] == 1) {
        redirect(BASE_URL . '/customers', 'error', 'Cannot generate statement for walk-in customer');
        return;
    }

    // Get date range from form
    $dateFrom = trim($_POST['date_from'] ?? '');
    $dateTo   = trim($_POST['date_to'] ?? '');

    // Validate dates
    if (empty($dateFrom) || empty($dateTo)) {
        redirect(BASE_URL . '/customers/statement/' . $id, 'error', 'Please select both start and end dates');
        return;
    }

    // Ensure from is before to
    if ($dateFrom > $dateTo) {
        redirect(BASE_URL . '/customers/statement/' . $id, 'error', 'Start date must be before end date');
        return;
    }

    // Get opening balance (balance at start of period)
    $openingBalanceData = $db->fetchOne("
        SELECT balance_after 
        FROM customer_transactions 
        WHERE customer_id = ? 
          AND DATE(created_at) < ?
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ", [$id, $dateFrom]);

    $openingBalance = $openingBalanceData ? floatval($openingBalanceData['balance_after']) : 0;

    // Get all transactions in the period
    $transactions = $db->fetchAll("
        SELECT
            ct.*,
            u.full_name AS user_name,
            s.sale_number
        FROM customer_transactions ct
        LEFT JOIN users u ON ct.user_id = u.id
        LEFT JOIN sales s ON ct.reference_type = 'sale' AND ct.reference_id = s.id
        WHERE ct.customer_id = ?
          AND DATE(ct.created_at) BETWEEN ? AND ?
        ORDER BY ct.created_at ASC, ct.id ASC
    ", [$id, $dateFrom, $dateTo]);

    // Calculate totals
    $totalDebits = 0;
    $totalCredits = 0;

    foreach ($transactions as $t) {
        if (in_array($t['transaction_type'], ['sale'])) {
            $totalDebits += abs($t['amount']);
        } elseif (in_array($t['transaction_type'], ['deposit', 'payment', 'refund'])) {
            $totalCredits += abs($t['amount']);
        }
    }

    // Closing balance
    $closingBalance = $openingBalance + $totalCredits - $totalDebits;

    // Get company settings for header
    $settings = $db->fetchOne("SELECT * FROM settings LIMIT 1");

    $pageTitle = 'Statement - ' . $customer['full_name'];
    include APP_PATH . '/views/customers/statement.php';
}
/**
 * Revert and delete a customer deposit
 */
function deleteCustomerDeposit($db, $txId)
{
    $tx = $db->fetchOne("SELECT * FROM customer_transactions WHERE id = ? AND transaction_type = 'deposit'", [$txId]);
    if (!$tx) {
        redirect(BASE_URL . '/customers', 'error', 'Deposit transaction not found');
        return;
    }

    $customerId = $tx['customer_id'];
    $customer = $db->fetchOne("SELECT * FROM customers WHERE id = ?", [$customerId]);
    if (!$customer) {
        redirect(BASE_URL . '/customers', 'error', 'Customer not found');
        return;
    }

    // Try to find the corresponding financial account transaction
    // First: new-style link (reference_type = 'customer_deposit', reference_id = customer_transactions.id)
    $acctTx = $db->fetchOne("SELECT * FROM account_transactions WHERE reference_type = 'customer_deposit' AND reference_id = ?", [$txId]);
    if (!$acctTx) {
        // Fallback: legacy rows where ENUM stored empty string due to missing 'customer_deposit' value,
        // OR old-style rows recorded with reference_type='manual', reference_id=customer_id
        $acctTx = $db->fetchOne("
            SELECT * FROM account_transactions
            WHERE (reference_type = '' OR reference_type = 'manual')
              AND reference_id = ?
              AND amount = ?
              AND transaction_type = 'deposit'
              AND DATE(created_at) = DATE(?)
            LIMIT 1
        ", [$txId, $tx['amount'], $tx['created_at']]);
    }
    if (!$acctTx) {
        // Final fallback: old-style with customer ID as reference_id
        $acctTx = $db->fetchOne("
            SELECT * FROM account_transactions
            WHERE reference_type = 'manual'
              AND reference_id = ?
              AND amount = ?
              AND transaction_type = 'deposit'
              AND DATE(created_at) = DATE(?)
            LIMIT 1
        ", [$customerId, $tx['amount'], $tx['created_at']]);
    }

    try {
        $db->beginTransaction();

        // 1. Revert customer balance
        $db->query("UPDATE customers SET current_balance = current_balance - ? WHERE id = ?", [$tx['amount'], $customerId]);

        // 2. Revert financial account balance and delete the log
        if ($acctTx) {
            $db->query("UPDATE accounts SET balance = balance - ? WHERE id = ?", [$acctTx['amount'], $acctTx['account_id']]);
            $db->query("DELETE FROM account_transactions WHERE id = ?", [$acctTx['id']]);
        }

        // 3. Delete the customer transaction log
        $db->query("DELETE FROM customer_transactions WHERE id = ?", [$txId]);

        $db->commit();
        redirect(BASE_URL . '/customers/view/' . $customerId, 'success', 'Customer deposit deleted successfully');
    } catch (Exception $e) {
        $db->rollback();
        error_log('Delete deposit error: ' . $e->getMessage());
        redirect(BASE_URL . '/customers/view/' . $customerId, 'error', 'Failed to delete customer deposit');
    }
}

/**
 * Show edit deposit form
 */
function showEditCustomerDepositForm($db, $txId)
{
    $tx = $db->fetchOne("SELECT * FROM customer_transactions WHERE id = ? AND transaction_type = 'deposit'", [$txId]);
    if (!$tx) {
        redirect(BASE_URL . '/customers', 'error', 'Deposit transaction not found');
        return;
    }

    $customer = $db->fetchOne("SELECT * FROM customers WHERE id = ?", [$tx['customer_id']]);
    if (!$customer) {
        redirect(BASE_URL . '/customers', 'error', 'Customer not found');
        return;
    }

    $accounts = $db->fetchAll("SELECT id, name, type, provider, balance FROM accounts WHERE is_active = 1 AND is_suspense = 0 ORDER BY name ASC");

    // Retrieve financial account linked to this deposit
    $acctTx = $db->fetchOne("SELECT * FROM account_transactions WHERE reference_type = 'customer_deposit' AND reference_id = ?", [$txId]);
    if (!$acctTx) {
        // Fallback: legacy rows with empty reference_type (before ENUM fix)
        $acctTx = $db->fetchOne("
            SELECT * FROM account_transactions
            WHERE (reference_type = '' OR reference_type = 'manual')
              AND reference_id = ?
              AND amount = ?
              AND transaction_type = 'deposit'
              AND DATE(created_at) = DATE(?)
            LIMIT 1
        ", [$txId, $tx['amount'], $tx['created_at']]);
    }
    if (!$acctTx) {
        // Final fallback: old-style with customer ID as reference_id
        $acctTx = $db->fetchOne("
            SELECT * FROM account_transactions
            WHERE reference_type = 'manual'
              AND reference_id = ?
              AND amount = ?
              AND transaction_type = 'deposit'
              AND DATE(created_at) = DATE(?)
            LIMIT 1
        ", [$tx['customer_id'], $tx['amount'], $tx['created_at']]);
    }
    
    $currentAccountId = $acctTx ? $acctTx['account_id'] : null;

    $pageTitle = 'Edit Customer Deposit';
    include APP_PATH . '/views/customers/edit_deposit.php';
}

/**
 * Process updating/editing a customer deposit
 */
function updateCustomerDeposit($db, $txId)
{
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect(BASE_URL . '/customers', 'error', 'Invalid form submission');
        return;
    }

    $tx = $db->fetchOne("SELECT * FROM customer_transactions WHERE id = ? AND transaction_type = 'deposit'", [$txId]);
    if (!$tx) {
        redirect(BASE_URL . '/customers', 'error', 'Deposit transaction not found');
        return;
    }

    $customerId = $tx['customer_id'];
    $customer = $db->fetchOne("SELECT * FROM customers WHERE id = ?", [$customerId]);
    if (!$customer) {
        redirect(BASE_URL . '/customers', 'error', 'Customer not found');
        return;
    }

    $newAmount    = floatval($_POST['amount'] ?? 0);
    $newAccountId = intval($_POST['account_id'] ?? 0);
    $newNotes     = trim($_POST['notes'] ?? '');
    $newDepositDate = trim($_POST['deposit_date'] ?? '');

    // Validate optional deposit date
    $newDepositDateSql = null;
    if (!empty($newDepositDate)) {
        $dateObj = DateTime::createFromFormat('Y-m-d', $newDepositDate);
        if (!$dateObj || $dateObj > new DateTime('today')) {
            redirect(BASE_URL . '/customers/edit-deposit/' . $txId, 'error', 'Invalid deposit date — must be today or a past date');
            return;
        }
        $newDepositDateSql = $dateObj->format('Y-m-d') . ' 12:00:00';
    }

    if ($newAmount <= 0) {
        redirect(BASE_URL . '/customers/edit-deposit/' . $txId, 'error', 'Amount must be greater than zero');
        return;
    }

    // Validate account
    $newAccount = $db->fetchOne("SELECT * FROM accounts WHERE id = ? AND is_active = 1 AND is_suspense = 0", [$newAccountId]);
    if (!$newAccount) {
        redirect(BASE_URL . '/customers/edit-deposit/' . $txId, 'error', 'Selected financial account not found or inactive');
        return;
    }

    // Try to find the corresponding old financial account transaction
    // First: new-style link (reference_type = 'customer_deposit', reference_id = customer_transactions.id)
    $acctTx = $db->fetchOne("SELECT * FROM account_transactions WHERE reference_type = 'customer_deposit' AND reference_id = ?", [$txId]);
    if (!$acctTx) {
        // Fallback: legacy rows where ENUM stored empty string
        $acctTx = $db->fetchOne("
            SELECT * FROM account_transactions
            WHERE (reference_type = '' OR reference_type = 'manual')
              AND reference_id = ?
              AND amount = ?
              AND transaction_type = 'deposit'
              AND DATE(created_at) = DATE(?)
            LIMIT 1
        ", [$txId, $tx['amount'], $tx['created_at']]);
    }
    if (!$acctTx) {
        // Final fallback: old-style with customer ID as reference_id
        $acctTx = $db->fetchOne("
            SELECT * FROM account_transactions
            WHERE reference_type = 'manual'
              AND reference_id = ?
              AND amount = ?
              AND transaction_type = 'deposit'
              AND DATE(created_at) = DATE(?)
            LIMIT 1
        ", [$customerId, $tx['amount'], $tx['created_at']]);
    }

    $oldAmount = floatval($tx['amount']);
    $oldAccountId = $acctTx ? intval($acctTx['account_id']) : null;
    $newPaymentMethod = $newAccount['type'] === 'mobile_money' ? 'mobile' : ($newAccount['type'] === 'bank' ? 'bank' : 'cash');
    $userId = $_SESSION['user_id'] ?? null;

    try {
        $db->beginTransaction();

        // 1. Revert old customer deposit balance
        $db->query("UPDATE customers SET current_balance = current_balance - ? WHERE id = ?", [$oldAmount, $customerId]);

        // 2. Revert old financial account balance and delete old transaction
        if ($acctTx) {
            $db->query("UPDATE accounts SET balance = balance - ? WHERE id = ?", [$acctTx['amount'], $acctTx['account_id']]);
            $db->query("DELETE FROM account_transactions WHERE id = ?", [$acctTx['id']]);
        }

        // 3. Apply new customer balance
        $db->query("UPDATE customers SET current_balance = current_balance + ? WHERE id = ?", [$newAmount, $customerId]);

        // 4. Create new financial account transaction (which automatically credits the new account)
        recordAccountTransaction(
            $db,
            $newPaymentMethod,
            $newAmount,
            'deposit',
            'customer_deposit',
            $txId,
            "Deposit from customer (Edited): " . $customer['full_name'] . ($newNotes ? " | " . $newNotes : ""),
            $newAccountId
        );

        // 5. Update customer_transactions record
        if ($newDepositDateSql) {
            $db->query("
                UPDATE customer_transactions
                SET amount = ?,
                    notes = ?,
                    payment_method = ?,
                    balance_after = balance_before + ?,
                    user_id = ?,
                    created_at = ?
                WHERE id = ?
            ", [$newAmount, $newNotes ?: null, $newPaymentMethod, $newAmount, $userId, $newDepositDateSql, $txId]);
        } else {
            $db->query("
                UPDATE customer_transactions
                SET amount = ?,
                    notes = ?,
                    payment_method = ?,
                    balance_after = balance_before + ?,
                    user_id = ?
                WHERE id = ?
            ", [$newAmount, $newNotes ?: null, $newPaymentMethod, $newAmount, $userId, $txId]);
        }

        $db->commit();
        redirect(BASE_URL . '/customers/view/' . $customerId, 'success', 'Customer deposit updated successfully');
    } catch (Exception $e) {
        $db->rollback();
        error_log('Update customer deposit error: ' . $e->getMessage());
        redirect(BASE_URL . '/customers/view/' . $customerId, 'error', 'Failed to update customer deposit');
    }
}

// ============================================================
// HELPERS
// ============================================================

/**
 * Auto-generate unique customer code
 */
function generateCustomerCode($db)
{
    $prefix = 'CUST-';
    $year   = date('Y');
    $last   = $db->fetchOne("
        SELECT customer_code FROM customers
        WHERE customer_code LIKE ?
        ORDER BY id DESC LIMIT 1
    ", ["$prefix$year-%"]);

    if ($last) {
        $parts  = explode('-', $last['customer_code']);
        $num    = intval(end($parts)) + 1;
    } else {
        $num = 1;
    }

    return $prefix . $year . '-' . str_pad($num, 4, '0', STR_PAD_LEFT);
}
