<?php

/**
 * Financial Accounts Controller
 * Allows admins/staff to track Cash, MoMo, and Bank accounts, and move funds between them.
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
        listAccounts($db);
        break;
    case 'store':
        if ($method === 'POST') {
            storeAccount($db);
        } else {
            redirect(BASE_URL . '/financial-accounts');
        }
        break;
    case 'transfer':
        $method === 'POST' ? executeTransfer($db) : showTransferForm($db);
        break;
    case 'transactions':
        if (!$id) redirect(BASE_URL . '/financial-accounts', 'error', 'Account ID required');
        showTransactions($db, $id);
        break;
    case 'edit':
    case 'update':
        if (!$id) redirect(BASE_URL . '/financial-accounts', 'error', 'Account ID required');
        $method === 'POST' ? updateAccount($db, $id) : showEditForm($db, $id);
        break;
    default:
        http_response_code(404);
        echo "<h1>Page Not Found</h1><a href='" . BASE_URL . "/financial-accounts'>← Back to Accounts</a>";
}

// ============================================================
// FUNCTIONS
// ============================================================

function listAccounts($db)
{
    $accounts = $db->fetchAll("
        SELECT * FROM accounts 
        ORDER BY type ASC, is_active DESC, name ASC
    ");

    $recentTransfers = $db->fetchAll("
        SELECT t.*, f.name as from_account_name, to_acc.name as to_account_name, u.full_name as user_name
        FROM account_transfers t
        JOIN accounts f ON t.from_account_id = f.id
        JOIN accounts to_acc ON t.to_account_id = to_acc.id
        LEFT JOIN users u ON t.user_id = u.id
        ORDER BY t.created_at DESC
        LIMIT 10
    ");

    $pageTitle = 'Financial Accounts';
    include APP_PATH . '/views/financial_accounts/index.php';
}

function storeAccount($db)
{
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? '';
    $provider = trim($_POST['provider'] ?? '');
    $accountNumber = trim($_POST['account_number'] ?? '');
    $initialBalance = safeFloat($_POST['initial_balance'] ?? 0.00);

    if (empty($name) || empty($type)) {
        redirect(BASE_URL . '/financial-accounts', 'error', 'Account Name and Type are required.');
    }

    if (!in_array($type, ['mobile_money', 'bank'])) {
        redirect(BASE_URL . '/financial-accounts', 'error', 'Invalid account type selected. Cash accounts cannot be created manually.');
    }

    try {
        $db->beginTransaction();

        $db->query("
            INSERT INTO accounts (name, type, provider, account_number, balance, is_default, is_active)
            VALUES (?, ?, ?, ?, ?, 0, 1)
        ", [$name, $type, $provider, $accountNumber, $initialBalance]);

        $accountId = $db->lastInsertId();

        // Record opening balance transaction
        if ($initialBalance > 0) {
            $db->query("
                INSERT INTO account_transactions (account_id, transaction_type, amount, balance_before, balance_after, reference_type, notes, user_id)
                VALUES (?, 'deposit', ?, 0.00, ?, 'manual', 'Opening balance', ?)
            ", [$accountId, $initialBalance, $initialBalance, $_SESSION['user_id']]);
        }

        $db->commit();
        redirect(BASE_URL . '/financial-accounts', 'success', 'Account created successfully.');
    } catch (Exception $e) {
        $db->rollback();
        redirect(BASE_URL . '/financial-accounts', 'error', 'Failed to create account: ' . $e->getMessage());
    }
}

function showTransferForm($db)
{
    $accounts = $db->fetchAll("SELECT * FROM accounts WHERE is_active = 1 ORDER BY name ASC");
    $pageTitle = 'Transfer Funds';
    include APP_PATH . '/views/financial_accounts/transfer.php';
}

function executeTransfer($db)
{
    $fromAccountId = safeInt($_POST['from_account_id'] ?? 0);
    $toAccountId   = safeInt($_POST['to_account_id'] ?? 0);
    $amount        = safeFloat($_POST['amount'] ?? 0.00);
    $charges       = safeFloat($_POST['charges'] ?? 0.00);
    $notes         = trim($_POST['notes'] ?? '');

    if ($fromAccountId === $toAccountId) {
        redirect(BASE_URL . '/financial-accounts/transfer', 'error', 'Source and destination accounts must be different.');
    }

    if ($amount <= 0) {
        redirect(BASE_URL . '/financial-accounts/transfer', 'error', 'Transfer amount must be greater than zero.');
    }

    if ($charges < 0) {
        redirect(BASE_URL . '/financial-accounts/transfer', 'error', 'Charges cannot be negative.');
    }

    $fromAccount = $db->fetchOne("SELECT * FROM accounts WHERE id = ? AND is_active = 1", [$fromAccountId]);
    $toAccount   = $db->fetchOne("SELECT * FROM accounts WHERE id = ? AND is_active = 1", [$toAccountId]);

    if (!$fromAccount || !$toAccount) {
        redirect(BASE_URL . '/financial-accounts/transfer', 'error', 'One or both selected accounts are invalid or inactive.');
    }

    $totalDeduction = $amount + $charges;
    if ($fromAccount['balance'] < $totalDeduction) {
        redirect(BASE_URL . '/financial-accounts/transfer', 'error', 'Insufficient balance in the source account.');
    }

    try {
        $db->beginTransaction();

        $fromBalanceBefore = $fromAccount['balance'];
        $fromBalanceAfter  = $fromBalanceBefore - $totalDeduction;

        $toBalanceBefore = $toAccount['balance'];
        $toBalanceAfter  = $toBalanceBefore + $amount;

        // 1. Deduct from source account (amount + charges)
        $db->query("UPDATE accounts SET balance = ? WHERE id = ?", [$fromBalanceAfter, $fromAccountId]);

        // 2. Add to destination account
        $db->query("UPDATE accounts SET balance = ? WHERE id = ?", [$toBalanceAfter, $toAccountId]);

        // 3. Record transfer log
        $db->query("
            INSERT INTO account_transfers (from_account_id, to_account_id, amount, charges, notes, user_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ", [$fromAccountId, $toAccountId, $amount, $charges, $notes, $_SESSION['user_id']]);
        
        $transferId = $db->lastInsertId();

        // 4. Record transactions log
        // Outflow from source account
        $db->query("
            INSERT INTO account_transactions (account_id, transaction_type, amount, balance_before, balance_after, reference_type, reference_id, notes, user_id)
            VALUES (?, 'transfer_out', ?, ?, ?, 'transfer', ?, ?, ?)
        ", [$fromAccountId, $amount, $fromBalanceBefore, $fromBalanceBefore - $amount, $transferId, "Transferred to " . $toAccount['name'], $_SESSION['user_id']]);

        // Inflow to destination account
        $db->query("
            INSERT INTO account_transactions (account_id, transaction_type, amount, balance_before, balance_after, reference_type, reference_id, notes, user_id)
            VALUES (?, 'transfer_in', ?, ?, ?, 'transfer', ?, ?, ?)
        ", [$toAccountId, $amount, $toBalanceBefore, $toBalanceAfter, $transferId, "Transferred from " . $fromAccount['name'], $_SESSION['user_id']]);

        // Record charges as separate transaction if any
        if ($charges > 0) {
            $chargeNotes = "Transfer charges for moving " . formatMoney($amount) . " to " . $toAccount['name'];
            if (!empty($notes)) {
                $chargeNotes .= " | Notes: " . $notes;
            }
            $db->query("
                INSERT INTO account_transactions (account_id, transaction_type, amount, balance_before, balance_after, reference_type, reference_id, notes, user_id)
                VALUES (?, 'charge', ?, ?, ?, 'charge', ?, ?, ?)
            ", [$fromAccountId, $charges, $fromBalanceBefore - $amount, $fromBalanceAfter, $transferId, $chargeNotes, $_SESSION['user_id']]);
        }

        $db->commit();
        redirect(BASE_URL . '/financial-accounts', 'success', 'Funds transferred successfully.');
    } catch (Exception $e) {
        $db->rollback();
        redirect(BASE_URL . '/financial-accounts/transfer', 'error', 'Transfer failed: ' . $e->getMessage());
    }
}

function showTransactions($db, $id)
{
    $account = $db->fetchOne("SELECT * FROM accounts WHERE id = ?", [$id]);
    if (!$account) {
        redirect(BASE_URL . '/financial-accounts', 'error', 'Account not found.');
    }

    $filters = ledgerFilters();
    [$where, $params] = buildLedgerWhere($id, $filters);

    $transactions = $db->fetchAll("
        SELECT t.*, u.full_name as user_name
        FROM account_transactions t
        LEFT JOIN users u ON t.user_id = u.id
        $where
        ORDER BY t.created_at DESC
    ", $params);

    $pageTitle = 'Account Ledger: ' . $account['name'];
    include APP_PATH . '/views/financial_accounts/transactions.php';
}

function showEditForm($db, $id)
{
    $account = $db->fetchOne("SELECT * FROM accounts WHERE id = ?", [$id]);
    if (!$account) {
        redirect(BASE_URL . '/financial-accounts', 'error', 'Account not found.');
    }

    $pageTitle = 'Edit Account: ' . $account['name'];
    include APP_PATH . '/views/financial_accounts/edit.php';
}

function updateAccount($db, $id)
{
    $account = $db->fetchOne("SELECT * FROM accounts WHERE id = ?", [$id]);
    if (!$account) {
        redirect(BASE_URL . '/financial-accounts', 'error', 'Account not found.');
    }

    $name          = trim($_POST['name'] ?? '');
    $provider      = trim($_POST['provider'] ?? '');
    $accountNumber = trim($_POST['account_number'] ?? '');
    $isActive      = isset($_POST['is_active']) ? 1 : 0;

    if (empty($name)) {
        redirect(BASE_URL . '/financial-accounts/edit/' . $id, 'error', 'Account name is required.');
    }

    if ($isActive === 0 && $account['is_active'] == 1) {
        $otherActive = $db->fetchOne("SELECT COUNT(*) as c FROM accounts WHERE is_active = 1 AND id != ?", [$id]);
        if (intval($otherActive['c'] ?? 0) === 0) {
            redirect(BASE_URL . '/financial-accounts/edit/' . $id, 'error',
                'You cannot deactivate the last active account — sales and purchases need at least one to post to.');
        }
    }

    // Deliberately NOT editable here:
    // - `type` (cash/mobile_money/bank) — changing it after transactions exist would
    //   misclassify historical records and the color-coded UI relies on it being stable.
    // - `balance` — must only ever change through a deposit/transfer/sale transaction,
    //   never a direct edit, or the account_transactions ledger stops reconciling with
    //   the account's actual balance.
    // If a client genuinely picked the wrong type when an account was created, that's a
    // one-off data fix via phpMyAdmin, not something exposed in the UI.
    $db->query("
        UPDATE accounts
        SET name = ?, provider = ?, account_number = ?, is_active = ?
        WHERE id = ?
    ", [$name, $provider ?: null, $accountNumber ?: null, $isActive, $id]);

    redirect(BASE_URL . '/financial-accounts', 'success', 'Account updated successfully.');
}

