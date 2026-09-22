<?php

/**
 * Financial Accounts Controller
 * Allows admins/staff to track Cash, MoMo, and Bank accounts, and move funds between them.
 */

if (!defined('APP_START')) {
    die('Direct access not permitted');
}

// $path/$method are always set by public/index.php before this file is
// included (same variable scope as the includer) — the ?? here is just to
// satisfy static analysis, which can't see across the include boundary.
/** @var string $path */
$path = $path ?? '';
/** @var string $method */
$method = $method ?? '';

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

function listAccounts(Database $db)
{
    [$scopeSql, $scopeParams] = accountBranchScopeSql();
    $accounts = $db->fetchAll("
        SELECT * FROM accounts
        WHERE 1=1 $scopeSql
        ORDER BY type ASC, is_active DESC, name ASC
    ", $scopeParams);

    // For the Add Account modal's branch picker — restricted to the
    // creating user's own branches unless they're company-wide, matching
    // resolveAccountBranchChoice()'s own rule.
    $branches = [];
    if (hasMultiBranch()) {
        $branches = isCompanyWide()
            ? $db->fetchAll("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name ASC")
            : userBranches(currentUser()['id']);
    }

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

function storeAccount(Database $db)
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

    // Company-wide (shared, branch_id NULL) only when a company-wide admin
    // deliberately leaves the branch blank; a branch-scoped admin's account
    // must land on one of their own branches — see resolveAccountBranchChoice().
    $branchId = null;
    if (hasMultiBranch()) {
        [$branchId, $branchError] = resolveAccountBranchChoice($db, $_POST['branch_id'] ?? '');
        if ($branchError) {
            redirect(BASE_URL . '/financial-accounts', 'error', $branchError);
        }
    }

    try {
        $db->beginTransaction();

        $db->query("
            INSERT INTO accounts (branch_id, name, type, provider, account_number, balance, is_default, is_active)
            VALUES (?, ?, ?, ?, ?, ?, 0, 1)
        ", [$branchId, $name, $type, $provider, $accountNumber, $initialBalance]);

        $accountId = $db->lastInsertId();

        // Record opening balance transaction
        if ($initialBalance > 0) {
            $db->query("
                INSERT INTO account_transactions (account_id, transaction_type, amount, balance_before, balance_after, reference_type, notes, user_id)
                VALUES (?, 'deposit', ?, 0.00, ?, 'manual', 'Opening balance', ?)
            ", [$accountId, $initialBalance, $initialBalance, $_SESSION['user_id']]);
        }

        $db->commit();
        logAudit('account.create', 'account', $accountId, ['name' => $name, 'type' => $type, 'initial_balance' => $initialBalance, 'branch' => branchName($branchId)]);
        redirect(BASE_URL . '/financial-accounts', 'success', 'Account created successfully.');
    } catch (Exception $e) {
        $db->rollback();
        redirect(BASE_URL . '/financial-accounts', 'error', 'Failed to create account: ' . $e->getMessage());
    }
}

function showTransferForm(Database $db)
{
    [$scopeSql, $scopeParams] = accountBranchScopeSql();
    $accounts = $db->fetchAll("SELECT * FROM accounts WHERE is_active = 1 $scopeSql ORDER BY name ASC", $scopeParams);
    $pageTitle = 'Transfer Funds';
    include APP_PATH . '/views/financial_accounts/transfer.php';
}

function executeTransfer(Database $db)
{
    $fromAccountId = safeInt($_POST['from_account_id'] ?? 0);
    $toAccountId   = safeInt($_POST['to_account_id'] ?? 0);
    $amount        = safeFloat($_POST['amount'] ?? 0.00);
    $charges       = safeFloat($_POST['charges'] ?? 0.00);
    $notes         = trim($_POST['notes'] ?? '');

    // Transfers are always charged to the business, never the customer — a
    // deliberate decision, not a placeholder. `charged_to` stays in the schema
    // (and the INSERT below) since account_transfers already has the column,
    // but it's no longer POST-configurable — no UI ever exposed a choice here,
    // so this was always resolving to 'business' in practice anyway.
    $chargedTo = 'business';
    $settlementType = $_POST['settlement_type'] ?? 'routine';
    if (!in_array($settlementType, ['routine', 'customer_credit_balancing'], true)) {
        $settlementType = 'routine';
    }

    // Which reporting period this settlement pays off — only meaningful
    // (and only ever set) for a customer-credit settlement, threaded
    // through as hidden fields from the settlement report's "Record
    // Settlement" link. Lets that report net a settled amount back out
    // instead of showing the same imbalance as owed forever. Malformed/
    // missing dates just leave it untracked rather than failing the
    // transfer — the money still moves either way.
    $settlesPeriodFrom = null;
    $settlesPeriodTo   = null;
    if ($settlementType === 'customer_credit_balancing') {
        $rawFrom = $_POST['settles_period_from'] ?? '';
        $rawTo   = $_POST['settles_period_to']   ?? '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawTo)) {
            $settlesPeriodFrom = $rawFrom;
            $settlesPeriodTo   = $rawTo;
        }
    }

    if ($fromAccountId === $toAccountId) {
        redirect(BASE_URL . '/financial-accounts/transfer', 'error', 'Source and destination accounts must be different.');
    }

    if ($amount <= 0) {
        redirect(BASE_URL . '/financial-accounts/transfer', 'error', 'Transfer amount must be greater than zero.');
    }

    if ($charges < 0) {
        redirect(BASE_URL . '/financial-accounts/transfer', 'error', 'Charges cannot be negative.');
    }

    [$scopeSql, $scopeParams] = accountBranchScopeSql();
    $fromAccount = $db->fetchOne("SELECT * FROM accounts WHERE id = ? AND is_active = 1 $scopeSql", array_merge([$fromAccountId], $scopeParams));
    $toAccount   = $db->fetchOne("SELECT * FROM accounts WHERE id = ? AND is_active = 1 $scopeSql", array_merge([$toAccountId], $scopeParams));

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
            INSERT INTO account_transfers
                (from_account_id, to_account_id, amount, charges, charged_to, settlement_type,
                 settles_period_from, settles_period_to, notes, user_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $fromAccountId, $toAccountId, $amount, $charges, $chargedTo, $settlementType,
            $settlesPeriodFrom, $settlesPeriodTo, $notes, $_SESSION['user_id']
        ]);
        
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

function showTransactions(Database $db, mixed $id)
{
    [$scopeSql, $scopeParams] = accountBranchScopeSql();
    $account = $db->fetchOne("SELECT * FROM accounts WHERE id = ? $scopeSql", array_merge([$id], $scopeParams));
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

function showEditForm(Database $db, mixed $id)
{
    [$scopeSql, $scopeParams] = accountBranchScopeSql();
    $account = $db->fetchOne("SELECT * FROM accounts WHERE id = ? $scopeSql", array_merge([$id], $scopeParams));
    if (!$account) {
        redirect(BASE_URL . '/financial-accounts', 'error', 'Account not found.');
    }

    $branches = [];
    if (hasMultiBranch() && $account['type'] !== 'cash') {
        $branches = isCompanyWide()
            ? $db->fetchAll("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name ASC")
            : userBranches(currentUser()['id']);
    }

    $pageTitle = 'Edit Account: ' . $account['name'];
    include APP_PATH . '/views/financial_accounts/edit.php';
}

function updateAccount(Database $db, mixed $id)
{
    [$scopeSql, $scopeParams] = accountBranchScopeSql();
    $account = $db->fetchOne("SELECT * FROM accounts WHERE id = ? $scopeSql", array_merge([$id], $scopeParams));
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
        // Per-branch, not global: a branch's cash account is only "covered"
        // by an active account that's either its own (branch_id matches) or
        // company-wide (branch_id IS NULL) — the same visibility rule
        // accountBranchScopeSql() already applies everywhere else. The old
        // check just counted active accounts anywhere, so deactivating a
        // branch's only cash account was allowed as long as some OTHER
        // branch still had one active — that branch would then have zero
        // accounts to post sales/purchases to. For a company-wide account
        // being deactivated, every branch is potentially affected, not
        // just one — the query below checks all of them either way: when
        // $account['branch_id'] is a specific branch it only checks that
        // one (via the `? IS NULL OR` short-circuit), when it's NULL
        // (company-wide) it checks every branch. Works unchanged for
        // single-branch installs too, since `branches` always has exactly
        // one row there. Excludes the suspense account from "coverage" —
        // it's an internal reconciliation account, not a valid posting
        // target, the same reason every payment/deposit picker in the app
        // already excludes it (§6u).
        $strandedBranches = $db->fetchAll("
            SELECT b.id, b.name
            FROM branches b
            WHERE b.is_active = 1
              AND (? IS NULL OR b.id = ?)
              AND NOT EXISTS (
                  SELECT 1 FROM accounts a
                  WHERE a.is_active = 1 AND a.is_suspense = 0 AND a.id != ?
                    AND (a.branch_id = b.id OR a.branch_id IS NULL)
              )
        ", [$account['branch_id'], $account['branch_id'], $id]);

        if (!empty($strandedBranches)) {
            $names = implode(', ', array_column($strandedBranches, 'name'));
            redirect(BASE_URL . '/financial-accounts/edit/' . $id, 'error',
                'You cannot deactivate this account — ' . e($names) . ' would be left with no active account to post sales/purchases to.');
        }
    }

    // Branch scope: editable for mobile_money/bank (a client's own choice —
    // see resolveAccountBranchChoice()); cash accounts are system-managed
    // and always stay pinned to the branch they were created for.
    $branchId = $account['branch_id'];
    if (hasMultiBranch() && $account['type'] !== 'cash') {
        [$branchId, $branchError] = resolveAccountBranchChoice($db, $_POST['branch_id'] ?? '', $account['branch_id']);
        if ($branchError) {
            redirect(BASE_URL . '/financial-accounts/edit/' . $id, 'error', $branchError);
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
        SET name = ?, provider = ?, account_number = ?, is_active = ?, branch_id = ?
        WHERE id = ?
    ", [$name, $provider ?: null, $accountNumber ?: null, $isActive, $branchId, $id]);

    logAudit('account.update', 'account', $id, [
        'name'          => $name,
        'active_before' => (bool) $account['is_active'],
        'active_after'  => (bool) $isActive,
        'branch_before' => branchName($account['branch_id']),
        'branch_after'  => branchName($branchId),
    ]);

    redirect(BASE_URL . '/financial-accounts', 'success', 'Account updated successfully.');
}

