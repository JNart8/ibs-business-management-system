<?php

/**
 * Suspense Account Controller
 * Manages unknown deposits, allows assigning them to customers, and updates bank/momo accounts.
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
        listSuspense($db);
        break;
    case 'store':
        if ($method === 'POST') {
            storeSuspense($db);
        } else {
            redirect(BASE_URL . '/suspense');
        }
        break;
    case 'resolve':
        if ($method === 'POST') {
            resolveSuspense($db);
        } else {
            redirect(BASE_URL . '/suspense');
        }
        break;
    case 'edit':
        if (!$id) redirect(BASE_URL . '/suspense', 'error', 'Transaction ID required');
        $method === 'POST'
            ? updateSuspense($db, $id)
            : showEditSuspenseForm($db, $id);
        break;
    case 'delete':
        if (!$id) redirect(BASE_URL . '/suspense', 'error', 'Transaction ID required');
        deleteSuspense($db, $id);
        break;
    default:
        http_response_code(404);
        echo "<h1>Page Not Found</h1><a href='" . BASE_URL . "/suspense'>← Back to Suspense Account</a>";
}

// ============================================================
// FUNCTIONS
// ============================================================

/**
 * List suspense transactions and show account overview
 */
function listSuspense($db)
{
    // Retrieve default suspense account details
    $suspenseAccount = $db->fetchOne("SELECT * FROM accounts WHERE is_suspense = 1 AND is_active = 1 LIMIT 1");
    if (!$suspenseAccount) {
        // Fallback: if not seeded, find first account named Suspense or create one dynamically
        $suspenseAccount = $db->fetchOne("SELECT * FROM accounts WHERE name LIKE '%Suspense%' LIMIT 1");
        if (!$suspenseAccount) {
            die('Error: Suspense Account not set up in the database. Please run migrations.');
        }
    }

    // Fetch unresolved transactions
    $unresolvedTransactions = $db->fetchAll("
        SELECT t.*, a.name as account_name
        FROM suspense_transactions t
        LEFT JOIN accounts a ON t.resolved_account_id = a.id
        WHERE t.is_resolved = 0 
        ORDER BY t.created_at DESC
    ");

    // Fetch resolved transactions
    $resolvedTransactions = $db->fetchAll("
        SELECT t.*, c.full_name as customer_name, c.customer_code, a.name as account_name, u.full_name as user_name
        FROM suspense_transactions t
        LEFT JOIN customers c ON t.resolved_customer_id = c.id
        LEFT JOIN accounts a ON t.resolved_account_id = a.id
        LEFT JOIN users u ON t.resolved_by = u.id
        WHERE t.is_resolved = 1 
        ORDER BY t.resolved_at DESC
        LIMIT 50
    ");

    // Fetch customers & active non-suspense accounts for the resolution modal
    $customers = $db->fetchAll("SELECT id, full_name, customer_code FROM customers WHERE is_active = 1 AND is_default = 0 ORDER BY full_name ASC");
    $accounts = $db->fetchAll("SELECT id, name, type, provider FROM accounts WHERE is_active = 1 AND is_suspense = 0 ORDER BY name ASC");

    $pageTitle = 'Suspense Account Manager';
    include APP_PATH . '/views/suspense/index.php';
}

/**
 * Record a new unknown deposit in suspense
 */
function storeSuspense($db)
{
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect(BASE_URL . '/suspense', 'error', 'Invalid form submission');
        return;
    }

    $amount      = floatval($_POST['amount'] ?? 0);
    $accountId   = intval($_POST['account_id'] ?? 0);
    $referenceNo = trim($_POST['reference_no'] ?? '');
    $notes       = trim($_POST['notes'] ?? '');

    if ($amount <= 0) {
        redirect(BASE_URL . '/suspense', 'error', 'Amount must be greater than zero.');
        return;
    }

    if ($accountId <= 0) {
        redirect(BASE_URL . '/suspense', 'error', 'Financial account is required.');
        return;
    }

    $financialAccount = $db->fetchOne("SELECT * FROM accounts WHERE id = ? AND is_active = 1 AND is_suspense = 0", [$accountId]);
    if (!$financialAccount) {
        redirect(BASE_URL . '/suspense', 'error', 'Selected financial account not found or inactive.');
        return;
    }

    $suspenseAccount = $db->fetchOne("SELECT * FROM accounts WHERE is_suspense = 1 AND is_active = 1 LIMIT 1");
    if (!$suspenseAccount) {
        redirect(BASE_URL . '/suspense', 'error', 'Default suspense account not found or inactive.');
        return;
    }

    try {
        $db->beginTransaction();

        // 1. Insert into suspense_transactions table (storing destination account immediately)
        $db->query("
            INSERT INTO suspense_transactions (amount, reference_no, source_notes, is_resolved, resolved_account_id)
            VALUES (?, ?, ?, 0, ?)
        ", [$amount, $referenceNo ?: null, $notes ?: null, $accountId]);

        $suspenseTxId = $db->lastInsertId();

        $paymentMethod = $financialAccount['type'] === 'mobile_money' ? 'mobile' : 'bank';

        // 2. Credit the Suspense Account
        recordAccountTransaction(
            $db,
            $paymentMethod,
            $amount,
            'deposit',
            'manual',
            $suspenseTxId,
            "Unknown deposit (Suspense). Ref: " . ($referenceNo ?: 'N/A') . ($notes ? " | " . $notes : ""),
            $suspenseAccount['id']
        );

        // 3. Credit the selected Financial Account
        recordAccountTransaction(
            $db,
            $paymentMethod,
            $amount,
            'deposit',
            'manual',
            $suspenseTxId,
            "Unknown deposit received. Ref: " . ($referenceNo ?: 'N/A') . ($notes ? " | " . $notes : ""),
            $accountId
        );

        $db->commit();
        redirect(BASE_URL . '/suspense', 'success', 'Unknown deposit recorded to Suspense and Financial Account successfully.');
    } catch (Exception $e) {
        $db->rollback();
        redirect(BASE_URL . '/suspense', 'error', 'Failed to record suspense deposit: ' . $e->getMessage());
    }
}

/**
 * Resolve a suspense transaction and allocate to customer and destination financial account
 */
function resolveSuspense($db)
{
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect(BASE_URL . '/suspense', 'error', 'Invalid form submission');
        return;
    }

    $txId       = intval($_POST['transaction_id'] ?? 0);
    $customerId = intval($_POST['customer_id'] ?? 0);
    $resolutionNotes = trim($_POST['resolution_notes'] ?? '');

    if ($txId <= 0 || $customerId <= 0) {
        redirect(BASE_URL . '/suspense', 'error', 'Transaction and Customer are required.');
        return;
    }

    // 1. Fetch matching transaction
    $suspenseTx = $db->fetchOne("SELECT * FROM suspense_transactions WHERE id = ? AND is_resolved = 0", [$txId]);
    if (!$suspenseTx) {
        redirect(BASE_URL . '/suspense', 'error', 'Transaction not found or already resolved.');
        return;
    }

    // 2. Fetch customer
    $customer = $db->fetchOne("SELECT * FROM customers WHERE id = ? AND is_active = 1", [$customerId]);
    if (!$customer) {
        redirect(BASE_URL . '/suspense', 'error', 'Selected customer is invalid or inactive.');
        return;
    }

    // 3. Fetch original financial account
    $financialAccountId = intval($suspenseTx['resolved_account_id']);
    $financialAccount = $db->fetchOne("SELECT * FROM accounts WHERE id = ?", [$financialAccountId]);

    // 4. Fetch default suspense account
    $suspenseAccount = $db->fetchOne("SELECT * FROM accounts WHERE is_suspense = 1 LIMIT 1");
    if (!$suspenseAccount) {
        redirect(BASE_URL . '/suspense', 'error', 'Default suspense account not configured.');
        return;
    }

    $amount = floatval($suspenseTx['amount']);
    $userId = $_SESSION['user_id'] ?? null;

    try {
        $db->beginTransaction();

        // A. Update suspense transaction details
        $db->query("
            UPDATE suspense_transactions
            SET is_resolved = 1,
                resolved_customer_id = ?,
                resolved_at = CURRENT_TIMESTAMP,
                resolved_by = ?
            WHERE id = ?
        ", [$customerId, $userId, $txId]);

        // B. Withdraw funds from Suspense Account using helper (reducing Suspense balance)
        recordAccountTransaction(
            $db,
            'bank', // dummy
            $amount,
            'withdrawal',
            'manual',
            $txId,
            "Resolved suspense deposit allocated to customer: " . $customer['full_name'] . ($resolutionNotes ? " | Notes: " . $resolutionNotes : ""),
            $suspenseAccount['id']
        );

        // C. Record deposit on customer account & update balance
        $custBalanceBefore = floatval($customer['current_balance']);
        $custBalanceAfter  = $custBalanceBefore + $amount;

        $financialMethod = ($financialAccount && $financialAccount['type'] === 'mobile_money') ? 'mobile' : 'bank';

        $db->query("
            INSERT INTO customer_transactions
                (customer_id, transaction_type, amount, balance_before,
                 balance_after, reference_type, reference_id, payment_method, notes, user_id)
            VALUES (?, 'deposit', ?, ?, ?, 'manual', ?, ?, ?, ?)
        ", [
            $customerId,
            $amount,
            $custBalanceBefore,
            $custBalanceAfter,
            $txId,
            $financialMethod,
            "Resolved suspense deposit (Ref: " . ($suspenseTx['reference_no'] ?: 'N/A') . ")",
            $userId
        ]);

        $db->query("UPDATE customers SET current_balance = current_balance + ? WHERE id = ?", [$amount, $customerId]);

        // D. Auto-apply deposit to outstanding sales (oldest first - FIFO)
        $remainingDeposit = $amount;
        $outstandingSales = $db->fetchAll("
            SELECT id, sale_number, amount_due, amount_paid, total_amount
            FROM sales
            WHERE customer_id = ?
              AND payment_status IN ('unpaid', 'partial')
              AND amount_due > 0
            ORDER BY sale_date ASC
        ", [$customerId]);

        $currentCustBalance = $custBalanceAfter;

        foreach ($outstandingSales as $sale) {
            if ($remainingDeposit <= 0.01) break;

            $amountDue = floatval($sale['amount_due']);
            $paymentAmount = min($remainingDeposit, $amountDue);

            $newAmountPaid = floatval($sale['amount_paid']) + $paymentAmount;
            $newAmountDue = $amountDue - $paymentAmount;
            $newStatus = $newAmountDue <= 0.01 ? 'paid' : 'partial';

            $db->query("
                UPDATE sales
                SET amount_paid = ?, amount_due = ?, payment_status = ?
                WHERE id = ?
            ", [$newAmountPaid, max(0, $newAmountDue), $newStatus, $sale['id']]);

            // Log auto-payment transaction under customer_transactions
            $db->query("
                INSERT INTO customer_transactions
                    (customer_id, transaction_type, amount, balance_before,
                     balance_after, reference_type, reference_id, payment_method, notes, user_id)
                VALUES (?, 'payment', ?, ?, ?, 'sale', ?, 'deposit', ?, ?)
            ", [
                $customerId,
                -$paymentAmount,
                $currentCustBalance,
                $currentCustBalance,
                $sale['id'],
                "Auto-applied payment from resolved suspense deposit for sale " . $sale['sale_number'],
                $userId
            ]);

            $remainingDeposit -= $paymentAmount;
        }

        $db->commit();
        redirect(BASE_URL . '/suspense', 'success', 'Suspense transaction resolved and assigned to ' . $customer['full_name'] . ' successfully.');
    } catch (Exception $e) {
        $db->rollback();
        redirect(BASE_URL . '/suspense', 'error', 'Failed to resolve suspense transaction: ' . $e->getMessage());
    }
}

/**
 * Delete a suspense transaction and reverse all financial/customer impact
 */
function deleteSuspense($db, $txId)
{
    $tx = $db->fetchOne("SELECT * FROM suspense_transactions WHERE id = ?", [$txId]);
    if (!$tx) {
        redirect(BASE_URL . '/suspense', 'error', 'Suspense transaction not found.');
        return;
    }

    $suspenseAccount = $db->fetchOne("SELECT * FROM accounts WHERE is_suspense = 1 LIMIT 1");
    if (!$suspenseAccount) {
        redirect(BASE_URL . '/suspense', 'error', 'Suspense account not configured.');
        return;
    }

    try {
        $db->beginTransaction();

        $amount = floatval($tx['amount']);
        $financialAccountId = intval($tx['resolved_account_id']);

        if ($tx['is_resolved'] == 0) {
            // Unresolved: Revert deposit from both Suspense and original Financial Account
            $db->query("UPDATE accounts SET balance = balance - ? WHERE id = ?", [$amount, $suspenseAccount['id']]);
            $db->query("UPDATE accounts SET balance = balance - ? WHERE id = ?", [$amount, $financialAccountId]);

            // Delete initial deposit account transaction logs
            $db->query("DELETE FROM account_transactions WHERE reference_type = 'manual' AND reference_id = ?", [$txId]);
        } else {
            // Resolved: Revert Customer balance and Financial Account balance.
            // (Suspense balance net change was already zero after unresolved deposit + resolved withdrawal, so no change)
            $customerId = intval($tx['resolved_customer_id']);
            $db->query("UPDATE customers SET current_balance = current_balance - ? WHERE id = ?", [$amount, $customerId]);
            $db->query("UPDATE accounts SET balance = balance - ? WHERE id = ?", [$amount, $financialAccountId]);

            // Delete customer transaction logs associated with resolution
            $db->query("DELETE FROM customer_transactions WHERE reference_type = 'manual' AND reference_id = ?", [$txId]);

            // Delete initial deposit account transaction logs
            $db->query("DELETE FROM account_transactions WHERE reference_type = 'manual' AND reference_id = ?", [$txId]);

            // Find and delete the transfer resolution transactions & transfers logs
            $transfer = $db->fetchOne("SELECT id FROM account_transfers WHERE notes LIKE ?", ["%Tx #{$txId}%"]);
            if ($transfer) {
                $db->query("DELETE FROM account_transactions WHERE reference_type = 'transfer' AND reference_id = ?", [$transfer['id']]);
                $db->query("DELETE FROM account_transfers WHERE id = ?", [$transfer['id']]);
            }
        }

        // Delete the suspense transaction itself
        $db->query("DELETE FROM suspense_transactions WHERE id = ?", [$txId]);

        $db->commit();
        redirect(BASE_URL . '/suspense', 'success', 'Suspense transaction deleted successfully.');
    } catch (Exception $e) {
        $db->rollback();
        error_log('Delete suspense error: ' . $e->getMessage());
        redirect(BASE_URL . '/suspense', 'error', 'Failed to delete suspense transaction.');
    }
}

/**
 * Show edit suspense form
 */
function showEditSuspenseForm($db, $txId)
{
    $tx = $db->fetchOne("SELECT * FROM suspense_transactions WHERE id = ?", [$txId]);
    if (!$tx) {
        redirect(BASE_URL . '/suspense', 'error', 'Suspense transaction not found.');
        return;
    }

    $accounts = $db->fetchAll("SELECT id, name, type, provider, balance FROM accounts WHERE is_active = 1 AND is_suspense = 0 ORDER BY name ASC");
    $pageTitle = 'Edit Suspense Transaction';
    include APP_PATH . '/views/suspense/edit.php';
}

/**
 * Process updating a suspense transaction
 */
function updateSuspense($db, $txId)
{
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect(BASE_URL . '/suspense', 'error', 'Invalid form submission');
        return;
    }

    $tx = $db->fetchOne("SELECT * FROM suspense_transactions WHERE id = ?", [$txId]);
    if (!$tx) {
        redirect(BASE_URL . '/suspense', 'error', 'Suspense transaction not found.');
        return;
    }

    $newAmount      = floatval($_POST['amount'] ?? 0);
    $newAccountId   = intval($_POST['account_id'] ?? 0);
    $newReferenceNo = trim($_POST['reference_no'] ?? '');
    $newNotes       = trim($_POST['notes'] ?? '');

    if ($newAmount <= 0) {
        redirect(BASE_URL . '/suspense/edit/' . $txId, 'error', 'Amount must be greater than zero.');
        return;
    }

    $newFinancialAccount = $db->fetchOne("SELECT * FROM accounts WHERE id = ? AND is_active = 1 AND is_suspense = 0", [$newAccountId]);
    if (!$newFinancialAccount) {
        redirect(BASE_URL . '/suspense/edit/' . $txId, 'error', 'Selected financial account not found.');
        return;
    }

    $suspenseAccount = $db->fetchOne("SELECT * FROM accounts WHERE is_suspense = 1 LIMIT 1");
    if (!$suspenseAccount) {
        redirect(BASE_URL . '/suspense/edit/' . $txId, 'error', 'Suspense account not configured.');
        return;
    }

    $oldAmount = floatval($tx['amount']);
    $oldFinancialAccountId = intval($tx['resolved_account_id']);
    $userId = $_SESSION['user_id'] ?? null;

    try {
        $db->beginTransaction();

        if ($tx['is_resolved'] == 0) {
            // 1. Revert old unresolved balances
            $db->query("UPDATE accounts SET balance = balance - ? WHERE id = ?", [$oldAmount, $suspenseAccount['id']]);
            $db->query("UPDATE accounts SET balance = balance - ? WHERE id = ?", [$oldAmount, $oldFinancialAccountId]);

            // Delete old deposit account transaction logs
            $db->query("DELETE FROM account_transactions WHERE reference_type = 'manual' AND reference_id = ?", [$txId]);

            // 2. Apply new unresolved balances
            $paymentMethod = $newFinancialAccount['type'] === 'mobile_money' ? 'mobile' : 'bank';
            recordAccountTransaction(
                $db,
                $paymentMethod,
                $newAmount,
                'deposit',
                'manual',
                $txId,
                "Unknown deposit (Suspense) (Edited). Ref: " . ($newReferenceNo ?: 'N/A') . ($newNotes ? " | " . $newNotes : ""),
                $suspenseAccount['id']
            );

            recordAccountTransaction(
                $db,
                $paymentMethod,
                $newAmount,
                'deposit',
                'manual',
                $txId,
                "Unknown deposit received (Edited). Ref: " . ($newReferenceNo ?: 'N/A') . ($newNotes ? " | " . $newNotes : ""),
                $newAccountId
            );

            // 3. Update suspense transaction row
            $db->query("
                UPDATE suspense_transactions
                SET amount = ?,
                    reference_no = ?,
                    source_notes = ?,
                    resolved_account_id = ?
                WHERE id = ?
            ", [$newAmount, $newReferenceNo ?: null, $newNotes ?: null, $newAccountId, $txId]);

        } else {
            // 1. Revert old resolved balances (revert customer, financial account)
            $customerId = intval($tx['resolved_customer_id']);
            $db->query("UPDATE customers SET current_balance = current_balance - ? WHERE id = ?", [$oldAmount, $customerId]);
            $db->query("UPDATE accounts SET balance = balance - ? WHERE id = ?", [$oldAmount, $oldFinancialAccountId]);

            // Revert old resolution withdrawal on Suspense account
            $db->query("UPDATE accounts SET balance = balance + ? WHERE id = ?", [$oldAmount, $suspenseAccount['id']]);

            // Delete old customer transactions resolution log
            $db->query("DELETE FROM customer_transactions WHERE reference_type = 'manual' AND reference_id = ?", [$txId]);

            // Delete old deposit account transactions
            $db->query("DELETE FROM account_transactions WHERE reference_type = 'manual' AND reference_id = ?", [$txId]);

            // Delete old transfer logs
            $transfer = $db->fetchOne("SELECT id FROM account_transfers WHERE notes LIKE ?", ["%Tx #{$txId}%"]);
            if ($transfer) {
                $db->query("DELETE FROM account_transactions WHERE reference_type = 'transfer' AND reference_id = ?", [$transfer['id']]);
                $db->query("DELETE FROM account_transfers WHERE id = ?", [$transfer['id']]);
            }

            // 2. Apply new resolved balances
            $paymentMethod = $newFinancialAccount['type'] === 'mobile_money' ? 'mobile' : 'bank';

            // Credit the customer
            $db->query("UPDATE customers SET current_balance = current_balance + ? WHERE id = ?", [$newAmount, $customerId]);

            // Credit the financial account
            $db->query("UPDATE accounts SET balance = balance + ? WHERE id = ?", [$newAmount, $newAccountId]);

            // Log new deposit on customer account
            $customer = $db->fetchOne("SELECT * FROM customers WHERE id = ?", [$customerId]);
            $custBalanceBefore = floatval($customer['current_balance']) - $newAmount;
            $custBalanceAfter  = floatval($customer['current_balance']);

            $db->query("
                INSERT INTO customer_transactions
                    (customer_id, transaction_type, amount, balance_before,
                     balance_after, reference_type, reference_id, payment_method, notes, user_id)
                VALUES (?, 'deposit', ?, ?, ?, 'manual', ?, ?, ?, ?)
            ", [
                $customerId,
                $newAmount,
                $custBalanceBefore,
                $custBalanceAfter,
                $txId,
                $paymentMethod,
                "Resolved suspense deposit (Ref: " . ($newReferenceNo ?: 'N/A') . ")",
                $userId
            ]);

            // Re-debit Suspense (withdrawing)
            recordAccountTransaction(
                $db,
                'bank',
                $newAmount,
                'withdrawal',
                'manual',
                $txId,
                "Resolved suspense deposit allocated to customer (Edited): " . ($customer['full_name'] ?? ""),
                $suspenseAccount['id']
            );

            // Log deposit on new financial account (manual log)
            recordAccountTransaction(
                $db,
                $paymentMethod,
                $newAmount,
                'deposit',
                'manual',
                $txId,
                "Unknown deposit received (Edited). Ref: " . ($newReferenceNo ?: 'N/A') . ($newNotes ? " | " . $newNotes : ""),
                $newAccountId
            );

            // 3. Update suspense transaction row
            $db->query("
                UPDATE suspense_transactions
                SET amount = ?,
                    reference_no = ?,
                    source_notes = ?,
                    resolved_account_id = ?
                WHERE id = ?
            ", [$newAmount, $newReferenceNo ?: null, $newNotes ?: null, $newAccountId, $txId]);
        }

        $db->commit();
        redirect(BASE_URL . '/suspense', 'success', 'Suspense transaction updated successfully.');
    } catch (Exception $e) {
        $db->rollback();
        error_log('Update suspense error: ' . $e->getMessage());
        redirect(BASE_URL . '/suspense', 'error', 'Failed to update suspense transaction: ' . $e->getMessage());
    }
}
