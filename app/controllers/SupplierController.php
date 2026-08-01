<?php

/**
 * Supplier Controller
 * Handles all supplier operations
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
    $id = null;
} elseif (count($segments) === 2) {
    $action = $segments[1];
    $id = null;
} else {
    $action = $segments[1];
    $id = $segments[2];
}

// Route
switch ($action) {
    case 'index':
        listSuppliers($db);
        break;
    case 'create':
        $_SERVER['REQUEST_METHOD'] === 'POST'
            ? createSupplier($db)
            : showCreateForm($db);
        break;
    case 'view':
        if (!$id) redirect(BASE_URL . '/suppliers', 'error', 'Supplier ID required');
        viewSupplier($db, $id);
        break;
    case 'edit':
        if (!$id) redirect(BASE_URL . '/suppliers', 'error', 'Supplier ID required');
        $_SERVER['REQUEST_METHOD'] === 'POST'
            ? updateSupplier($db, $id)
            : showEditForm($db, $id);
        break;
    case 'delete':
        if (!$id) redirect(BASE_URL . '/suppliers', 'error', 'Supplier ID required');
        deleteSupplier($db, $id);
        break;
    case 'deposit':
        if (!$id) redirect(BASE_URL . '/suppliers', 'error', 'Supplier ID required');
        $_SERVER['REQUEST_METHOD'] === 'POST'
            ? processSupplierDeposit($db, $id)
            : showSupplierDepositForm($db, $id);
        break;
    case 'edit-deposit':
        if (!$id) redirect(BASE_URL . '/suppliers', 'error', 'Transaction ID required');
        $_SERVER['REQUEST_METHOD'] === 'POST'
            ? updateSupplierDeposit($db, $id)
            : showEditSupplierDepositForm($db, $id);
        break;
    case 'delete-deposit':
        if (!$id) redirect(BASE_URL . '/suppliers', 'error', 'Transaction ID required');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') deleteSupplierDeposit($db, $id);
        else redirect(BASE_URL . '/suppliers', 'error', 'Invalid request');
        break;
    case 'toggle':
        if (!$id) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false]);
            exit;
        }
        toggleSupplier($db, $id);
        break;
    case 'search':
        searchSuppliers($db);
        break;
    default:
        http_response_code(404);
        echo "<h1>Action not found</h1><a href='" . BASE_URL . "/suppliers'>← Back</a>";
        break;
}

// ============================================================
// FUNCTIONS
// ============================================================

function listSuppliers($db)
{
    $search = trim($_GET['search'] ?? '');
    $filter = $_GET['filter'] ?? 'all';
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = ITEMS_PER_PAGE;
    $offset = ($page - 1) * $limit;

    $params = [];
    $where  = "WHERE 1=1";

    if (!empty($search)) {
        $where   .= " AND (s.company_name LIKE ? OR s.contact_name LIKE ?
                          OR s.phone LIKE ? OR s.supplier_code LIKE ?)";
        $t        = "%$search%";
        $params   = [$t, $t, $t, $t];
    }

    if ($filter === 'active')   $where .= " AND s.is_active = 1";
    if ($filter === 'inactive') $where .= " AND s.is_active = 0";

    $total      = $db->fetchOne("SELECT COUNT(*) AS cnt FROM suppliers s $where", $params);
    $totalCount = intval($total['cnt'] ?? 0);
    $totalPages = max(1, ceil($totalCount / $limit));

    $suppliers = $db->fetchAll("
        SELECT
            s.*,
            COUNT(DISTINCT p.id)                        AS total_products,
            COUNT(DISTINCT sm.id)                       AS total_deliveries,
            COALESCE(SUM(sm.quantity * p.cost_price),0) AS total_supplied_value,
            MAX(sm.created_at)                          AS last_delivery
        FROM suppliers s
        LEFT JOIN products p      ON p.supplier_id   = s.id
        LEFT JOIN stock_movements sm ON sm.supplier_id = s.id
                                    AND sm.movement_type = 'in'
        $where
        GROUP BY s.id
        ORDER BY s.company_name ASC
        LIMIT ? OFFSET ?
    ", array_merge($params, [$limit, $offset]));

    // Summary stats
    $stats = $db->fetchOne("
        SELECT
            COUNT(*)                                                AS total,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END)        AS active,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END)        AS inactive
        FROM suppliers
    ");

    $pageTitle = 'Suppliers';
    include APP_PATH . '/views/suppliers/index.php';
}

function showCreateForm($db)
{
    $pageTitle = 'Add New Supplier';
    include APP_PATH . '/views/suppliers/create.php';
}

function createSupplier($db)
{
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect(BASE_URL . '/suppliers/create', 'error', 'Invalid form submission');
        return;
    }

    $companyName = trim($_POST['company_name'] ?? '');
    $contactName = trim($_POST['contact_name'] ?? '');
    $phone       = trim($_POST['phone']        ?? '');
    $phoneAlt    = trim($_POST['phone_alt']    ?? '');
    $email       = trim($_POST['email']        ?? '');
    $address     = trim($_POST['address']      ?? '');
    $city        = trim($_POST['city']         ?? '');
    $notes       = trim($_POST['notes']        ?? '');
    $code        = trim($_POST['supplier_code'] ?? '');
    $creditLimit = floatval($_POST['credit_limit'] ?? 0);

    if (empty($code)) $code = generateSupplierCode($db);

    $errors = [];
    if (empty($companyName)) $errors[] = 'Company name is required';
    if (empty($phone))       $errors[] = 'Phone number is required';
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address';
    }

    $existingCode  = $db->fetchOne("SELECT id FROM suppliers WHERE supplier_code = ?", [$code]);
    if ($existingCode) $errors[] = 'Supplier code already exists';

    $existingPhone = $db->fetchOne("SELECT id FROM suppliers WHERE phone = ?", [$phone]);
    if ($existingPhone) $errors[] = 'Phone number already registered';

    if (!empty($errors)) {
        $_SESSION['errors']    = $errors;
        $_SESSION['old_input'] = $_POST;
        redirect(BASE_URL . '/suppliers/create', 'error', implode(' | ', $errors));
        return;
    }

    try {
        $db->query("
            INSERT INTO suppliers
                (supplier_code, company_name, contact_name, phone, phone_alt,
                 email, address, city, notes, credit_limit)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $code,
            $companyName,
            $contactName ?: null,
            $phone,
            $phoneAlt ?: null,
            $email ?: null,
            $address  ?: null,
            $city  ?: null,
            $notes ?: null,
            $creditLimit
        ]);

        redirect(BASE_URL . '/suppliers', 'success', "Supplier \"$companyName\" added successfully");
    } catch (Exception $e) {
        error_log('Create supplier error: ' . $e->getMessage());
        redirect(BASE_URL . '/suppliers/create', 'error', 'Failed to create supplier');
    }
}

function viewSupplier($db, $id)
{
    $supplier = $db->fetchOne("SELECT * FROM suppliers WHERE id = ?", [$id]);
    if (!$supplier) {
        redirect(BASE_URL . '/suppliers', 'error', 'Supplier not found');
        return;
    }

    // Products supplied by this supplier
    $products = $db->fetchAll("
        SELECT p.id, p.sku, p.name, p.current_stock,
               p.cost_price, p.average_cost, p.selling_price, p.unit,
               c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.supplier_id = ? AND p.is_active = 1
        ORDER BY p.name ASC
    ", [$id]);

    // Purchase history (from purchases table)
    $purchases = $db->fetchAll("
        SELECT 
            p.id,
            p.purchase_number,
            p.purchase_date,
            p.total_amount,
            p.amount_paid,
            p.amount_due,
            p.payment_status,
            p.payment_method,
            p.invoice_number,
            p.notes,
            u.full_name AS created_by
        FROM purchases p
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.supplier_id = ?
        ORDER BY p.purchase_date DESC
        LIMIT 30
    ", [$id]);

    // Summary stats
    $summary = $db->fetchOne("
        SELECT
            COUNT(id) AS total_purchases_count,
            MAX(purchase_date) AS last_purchase_date
        FROM purchases
        WHERE supplier_id = ?
    ", [$id]);

    // Supplier transaction history (payments, deposits, adjustments)
    $transactions = $db->fetchAll("
        SELECT
            st.*,
            u.full_name AS user_name
        FROM supplier_transactions st
        LEFT JOIN users u ON st.user_id = u.id
        WHERE st.supplier_id = ?
        ORDER BY st.created_at DESC
        LIMIT 30
    ", [$id]);

    $pageTitle = 'Supplier: ' . $supplier['company_name'];
    include APP_PATH . '/views/suppliers/view.php';
}

/**
 * Show supplier deposit (payment) form
 */
function showSupplierDepositForm($db, $id)
{
    $supplier = $db->fetchOne("SELECT * FROM suppliers WHERE id = ?", [$id]);
    if (!$supplier) {
        redirect(BASE_URL . '/suppliers', 'error', 'Supplier not found');
        return;
    }

    // Load active financial accounts to pay from
    $accounts = $db->fetchAll("
        SELECT id, name, type, balance
        FROM accounts
        WHERE is_active = 1
        ORDER BY type ASC, name ASC
    ");

    $pageTitle = 'Supplier Payment — ' . $supplier['company_name'];
    include APP_PATH . '/views/suppliers/deposit.php';
}

/**
 * Process a supplier deposit (payment to supplier) from a financial account
 */
function processSupplierDeposit($db, $id)
{
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect(BASE_URL . '/suppliers/deposit/' . $id, 'error', 'Invalid form submission');
        return;
    }

    $supplier = $db->fetchOne("SELECT * FROM suppliers WHERE id = ?", [$id]);
    if (!$supplier) {
        redirect(BASE_URL . '/suppliers', 'error', 'Supplier not found');
        return;
    }

    $amount    = floatval($_POST['amount']     ?? 0);
    $accountId = intval($_POST['account_id']   ?? 0);
    $notes     = trim($_POST['notes']          ?? '');
    $dateStr   = trim($_POST['payment_date']   ?? '');

    // Validate optional payment date
    $dateSql = null;
    if (!empty($dateStr)) {
        $dateObj = DateTime::createFromFormat('Y-m-d', $dateStr);
        if (!$dateObj || $dateObj > new DateTime('today')) {
            redirect(BASE_URL . '/suppliers/deposit/' . $id, 'error', 'Invalid date — must be today or a past date');
            return;
        }
        $dateSql = $dateObj->format('Y-m-d') . ' 12:00:00';
    }

    if ($amount <= 0) {
        redirect(BASE_URL . '/suppliers/deposit/' . $id, 'error', 'Amount must be greater than zero');
        return;
    }

    if (!$accountId) {
        redirect(BASE_URL . '/suppliers/deposit/' . $id, 'error', 'Please select an account to pay from');
        return;
    }

    $account = $db->fetchOne("SELECT * FROM accounts WHERE id = ? AND is_active = 1", [$accountId]);
    if (!$account) {
        redirect(BASE_URL . '/suppliers/deposit/' . $id, 'error', 'Selected account not found or inactive');
        return;
    }

    if ($account['balance'] < $amount) {
        redirect(BASE_URL . '/suppliers/deposit/' . $id, 'error',
            'Insufficient balance in ' . $account['name'] . '. Available: ' . formatMoney($account['balance']));
        return;
    }

    $userId         = $_SESSION['user_id'] ?? null;
    $balanceBefore  = floatval($supplier['current_balance']);
    $balanceAfter   = $balanceBefore + $amount; // Paying reduces debt (e.g. -500 + 300 = -200)

    $accBalBefore   = floatval($account['balance']);
    $accBalAfter    = $accBalBefore - $amount;

    $noteText = $notes ?: "Payment of " . formatMoney($amount) . " to " . $supplier['company_name'];

    $methodMap = [
        'cash' => 'cash',
        'mobile_money' => 'mobile',
        'bank' => 'bank'
    ];
    $payMethod = $methodMap[$account['type']] ?? 'cash';

    try {
        $db->beginTransaction();

        // 1. Log supplier transaction (payment)
        if ($dateSql) {
            $db->query("
                INSERT INTO supplier_transactions
                    (supplier_id, transaction_type, amount, balance_before, balance_after,
                     reference_type, payment_method, notes, user_id, created_at)
                VALUES (?, 'payment', ?, ?, ?, 'payment', ?, ?, ?, ?)
            ", [
                $id, $amount, $balanceBefore, $balanceAfter,
                $payMethod, $noteText, $userId, $dateSql
            ]);
        } else {
            $db->query("
                INSERT INTO supplier_transactions
                    (supplier_id, transaction_type, amount, balance_before, balance_after,
                     reference_type, payment_method, notes, user_id)
                VALUES (?, 'payment', ?, ?, ?, 'payment', ?, ?, ?)
            ", [
                $id, $amount, $balanceBefore, $balanceAfter,
                $payMethod, $noteText, $userId
            ]);
        }

        $supplierTxId = $db->lastInsertId();

        // 2. Update supplier balance
        $db->query(
            "UPDATE suppliers SET current_balance = current_balance + ? WHERE id = ?",
            [$amount, $id]
        );

        // 3. Record withdrawal from financial account (tagged with supplier_deposit for clean lookups)
        $success = recordAccountTransaction(
            $db,
            $payMethod,
            $amount,
            'withdrawal',
            'supplier_deposit',
            $supplierTxId,
            'Payment to supplier: ' . $supplier['company_name'] . ($notes ? " — $notes" : ''),
            $accountId
        );

        if (!$success) {
            throw new Exception("Failed to record account transaction");
        }

        $db->commit();

        redirect(
            BASE_URL . '/suppliers/view/' . $id,
            'success',
            "Payment of " . formatMoney($amount) . " recorded from " . $account['name']
        );
    } catch (Exception $e) {
        $db->rollback();
        error_log('Supplier deposit error: ' . $e->getMessage());
        redirect(BASE_URL . '/suppliers/deposit/' . $id, 'error', 'Failed to process payment');
    }
}

/**
 * Delete a standalone supplier deposit (payment to supplier)
 */
function deleteSupplierDeposit($db, $txId)
{
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect(BASE_URL . '/suppliers', 'error', 'Invalid form submission');
        return;
    }

    $tx = $db->fetchOne("
        SELECT * FROM supplier_transactions
        WHERE id = ? AND transaction_type = 'payment' AND reference_type = 'payment'
    ", [$txId]);
    if (!$tx) {
        redirect(BASE_URL . '/suppliers', 'error', 'Deposit not found or cannot be deleted');
        return;
    }

    $supplierId = $tx['supplier_id'];
    $amount     = floatval($tx['amount']);

    // Find linked account_transactions row
    $acctTx = $db->fetchOne("
        SELECT * FROM account_transactions
        WHERE reference_type = 'supplier_deposit' AND reference_id = ?
        LIMIT 1
    ", [$txId]);
    if (!$acctTx) {
        // Legacy fallback
        $acctTx = $db->fetchOne("
            SELECT * FROM account_transactions
            WHERE reference_type = 'purchase'
              AND reference_id = ?
              AND transaction_type = 'withdrawal'
              AND amount = ?
              AND DATE(created_at) = DATE(?)
            LIMIT 1
        ", [$supplierId, $amount, $tx['created_at']]);
    }

    try {
        $db->beginTransaction();

        // 1. Reverse supplier balance (remove the payment that was previously applied)
        $db->query(
            "UPDATE suppliers SET current_balance = current_balance - ? WHERE id = ?",
            [$amount, $supplierId]
        );

        // 2. Restore the financial account balance and delete account_transactions row
        if ($acctTx) {
            $db->query(
                "UPDATE accounts SET balance = balance + ? WHERE id = ?",
                [$acctTx['amount'], $acctTx['account_id']]
            );
            $db->query("DELETE FROM account_transactions WHERE id = ?", [$acctTx['id']]);
        }

        // 3. Delete the supplier transaction record
        $db->query("DELETE FROM supplier_transactions WHERE id = ?", [$txId]);

        $db->commit();
        redirect(
            BASE_URL . '/suppliers/view/' . $supplierId,
            'success',
            'Payment of ' . formatMoney($amount) . ' deleted successfully'
        );
    } catch (Exception $e) {
        $db->rollback();
        error_log('Supplier deposit delete error: ' . $e->getMessage());
        redirect(BASE_URL . '/suppliers/view/' . $supplierId, 'error', 'Failed to delete payment');
    }
}

function showEditForm($db, $id)
{
    $supplier = $db->fetchOne("SELECT * FROM suppliers WHERE id = ?", [$id]);
    if (!$supplier) {
        redirect(BASE_URL . '/suppliers', 'error', 'Supplier not found');
        return;
    }
    $pageTitle = 'Edit Supplier';
    include APP_PATH . '/views/suppliers/edit.php';
}

/**
 * Show edit form for a supplier deposit (payment to supplier)
 */
function showEditSupplierDepositForm($db, $txId)
{
    $tx = $db->fetchOne("
        SELECT * FROM supplier_transactions
        WHERE id = ? AND transaction_type = 'payment' AND reference_type = 'payment'
    ", [$txId]);
    if (!$tx) {
        redirect(BASE_URL . '/suppliers', 'error', 'Deposit transaction not found or not editable');
        return;
    }

    $supplier = $db->fetchOne("SELECT * FROM suppliers WHERE id = ?", [$tx['supplier_id']]);
    if (!$supplier) {
        redirect(BASE_URL . '/suppliers', 'error', 'Supplier not found');
        return;
    }

    $accounts = $db->fetchAll("
        SELECT id, name, type, balance
        FROM accounts
        WHERE is_active = 1
        ORDER BY type ASC, name ASC
    ");

    // Find the linked account_transactions row
    // processSupplierDeposit() stored: reference_type='purchase', reference_id=supplier_id
    $acctTx = $db->fetchOne("
        SELECT * FROM account_transactions
        WHERE reference_type = 'supplier_deposit' AND reference_id = ?
        LIMIT 1
    ", [$txId]);
    if (!$acctTx) {
        // Legacy fallback: old rows used reference_type='purchase', reference_id=supplier_id
        $acctTx = $db->fetchOne("
            SELECT * FROM account_transactions
            WHERE reference_type = 'purchase'
              AND reference_id = ?
              AND transaction_type = 'withdrawal'
              AND amount = ?
              AND DATE(created_at) = DATE(?)
            LIMIT 1
        ", [$tx['supplier_id'], $tx['amount'], $tx['created_at']]);
    }

    $currentAccountId = $acctTx ? intval($acctTx['account_id']) : null;

    $pageTitle = 'Edit Supplier Payment';
    include APP_PATH . '/views/suppliers/edit_deposit.php';
}

/**
 * Process updating/editing a supplier deposit (payment to supplier)
 */
function updateSupplierDeposit($db, $txId)
{
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect(BASE_URL . '/suppliers', 'error', 'Invalid form submission');
        return;
    }

    $tx = $db->fetchOne("
        SELECT * FROM supplier_transactions
        WHERE id = ? AND transaction_type = 'payment' AND reference_type = 'payment'
    ", [$txId]);
    if (!$tx) {
        redirect(BASE_URL . '/suppliers', 'error', 'Deposit transaction not found');
        return;
    }

    $supplierId = $tx['supplier_id'];
    $supplier   = $db->fetchOne("SELECT * FROM suppliers WHERE id = ?", [$supplierId]);
    if (!$supplier) {
        redirect(BASE_URL . '/suppliers', 'error', 'Supplier not found');
        return;
    }

    $newAmount    = floatval($_POST['amount']     ?? 0);
    $newAccountId = intval($_POST['account_id']   ?? 0);
    $newNotes     = trim($_POST['notes']          ?? '');
    $newDateStr   = trim($_POST['payment_date']   ?? '');

    // Validate optional payment date
    $newDateSql = null;
    if (!empty($newDateStr)) {
        $dateObj = DateTime::createFromFormat('Y-m-d', $newDateStr);
        if (!$dateObj || $dateObj > new DateTime('today')) {
            redirect(BASE_URL . '/suppliers/edit-deposit/' . $txId, 'error', 'Invalid date — must be today or a past date');
            return;
        }
        $newDateSql = $dateObj->format('Y-m-d') . ' 12:00:00';
    }

    if ($newAmount <= 0) {
        redirect(BASE_URL . '/suppliers/edit-deposit/' . $txId, 'error', 'Amount must be greater than zero');
        return;
    }

    $newAccount = $db->fetchOne("SELECT * FROM accounts WHERE id = ? AND is_active = 1", [$newAccountId]);
    if (!$newAccount) {
        redirect(BASE_URL . '/suppliers/edit-deposit/' . $txId, 'error', 'Selected account not found or inactive');
        return;
    }

    // Find linked account_transactions row
    $acctTx = $db->fetchOne("
        SELECT * FROM account_transactions
        WHERE reference_type = 'supplier_deposit' AND reference_id = ?
        LIMIT 1
    ", [$txId]);
    if (!$acctTx) {
        $acctTx = $db->fetchOne("
            SELECT * FROM account_transactions
            WHERE reference_type = 'purchase'
              AND reference_id = ?
              AND transaction_type = 'withdrawal'
              AND amount = ?
              AND DATE(created_at) = DATE(?)
            LIMIT 1
        ", [$supplierId, $tx['amount'], $tx['created_at']]);
    }

    $oldAmount       = floatval($tx['amount']);
    $methodMap       = ['cash' => 'cash', 'mobile_money' => 'mobile', 'bank' => 'bank'];
    $newPayMethod    = $methodMap[$newAccount['type']] ?? 'cash';
    $userId          = $_SESSION['user_id'] ?? null;
    $noteText        = $newNotes ?: "Payment of " . formatMoney($newAmount) . " to " . $supplier['company_name'];

    try {
        $db->beginTransaction();

        // 1. Revert old supplier balance (undo the old payment)
        $db->query("UPDATE suppliers SET current_balance = current_balance - ? WHERE id = ?", [$oldAmount, $supplierId]);

        // 2. Revert old financial account balance & remove old account_transactions row
        if ($acctTx) {
            $db->query("UPDATE accounts SET balance = balance + ? WHERE id = ?", [$acctTx['amount'], $acctTx['account_id']]);
            $db->query("DELETE FROM account_transactions WHERE id = ?", [$acctTx['id']]);
        }

        // 3. Apply new supplier balance
        $db->query("UPDATE suppliers SET current_balance = current_balance + ? WHERE id = ?", [$newAmount, $supplierId]);

        // 4. Record new account withdrawal (with new reference_type for easy lookup later)
        recordAccountTransaction(
            $db,
            $newPayMethod,
            $newAmount,
            'withdrawal',
            'supplier_deposit',
            $txId,
            'Payment to supplier (Edited): ' . $supplier['company_name'] . ($newNotes ? " — $newNotes" : ''),
            $newAccountId
        );

        // 5. Update supplier_transactions record
        $balanceBefore = floatval($tx['balance_before']);
        $balanceAfter  = $balanceBefore + $newAmount;

        if ($newDateSql) {
            $db->query("
                UPDATE supplier_transactions
                SET amount = ?,
                    balance_after = ?,
                    payment_method = ?,
                    notes = ?,
                    user_id = ?,
                    created_at = ?
                WHERE id = ?
            ", [$newAmount, $balanceAfter, $newPayMethod, $noteText, $userId, $newDateSql, $txId]);
        } else {
            $db->query("
                UPDATE supplier_transactions
                SET amount = ?,
                    balance_after = ?,
                    payment_method = ?,
                    notes = ?,
                    user_id = ?
                WHERE id = ?
            ", [$newAmount, $balanceAfter, $newPayMethod, $noteText, $userId, $txId]);
        }

        $db->commit();
        redirect(
            BASE_URL . '/suppliers/view/' . $supplierId,
            'success',
            'Payment updated successfully'
        );
    } catch (Exception $e) {
        $db->rollback();
        error_log('Supplier deposit update error: ' . $e->getMessage());
        redirect(BASE_URL . '/suppliers/edit-deposit/' . $txId, 'error', 'Failed to update payment');
    }
}

function updateSupplier($db, $id)
{
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect(BASE_URL . '/suppliers/edit/' . $id, 'error', 'Invalid form submission');
        return;
    }

    $supplier = $db->fetchOne("SELECT * FROM suppliers WHERE id = ?", [$id]);
    if (!$supplier) {
        redirect(BASE_URL . '/suppliers', 'error', 'Supplier not found');
        return;
    }

    $companyName = trim($_POST['company_name'] ?? '');
    $contactName = trim($_POST['contact_name'] ?? '');
    $phone       = trim($_POST['phone']        ?? '');
    $phoneAlt    = trim($_POST['phone_alt']    ?? '');
    $email       = trim($_POST['email']        ?? '');
    $address     = trim($_POST['address']      ?? '');
    $city        = trim($_POST['city']         ?? '');
    $notes       = trim($_POST['notes']        ?? '');
    $is_active   = isset($_POST['is_active']) ? 1 : 0;
    $creditLimit = floatval($_POST['credit_limit'] ?? 0);

    $errors = [];
    if (empty($companyName)) $errors[] = 'Company name is required';
    if (empty($phone))       $errors[] = 'Phone number is required';
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address';
    }

    $existingPhone = $db->fetchOne(
        "SELECT id FROM suppliers WHERE phone = ? AND id != ?",
        [$phone, $id]
    );
    if ($existingPhone) $errors[] = 'Phone number already registered to another supplier';

    if (!empty($errors)) {
        $_SESSION['errors']    = $errors;
        $_SESSION['old_input'] = $_POST;
        redirect(BASE_URL . '/suppliers/edit/' . $id, 'error', implode(' | ', $errors));
        return;
    }

    try {
        $db->query("
            UPDATE suppliers SET
                company_name = ?, contact_name = ?, phone = ?, phone_alt = ?,
                email = ?, address = ?, city = ?, notes = ?, is_active = ?, credit_limit = ?
            WHERE id = ?
        ", [
            $companyName,
            $contactName ?: null,
            $phone,
            $phoneAlt ?: null,
            $email ?: null,
            $address ?: null,
            $city ?: null,
            $notes ?: null,
            $is_active,
            $creditLimit,
            $id
        ]);

        redirect(BASE_URL . '/suppliers/view/' . $id, 'success', 'Supplier updated successfully');
    } catch (Exception $e) {
        error_log('Update supplier error: ' . $e->getMessage());
        redirect(BASE_URL . '/suppliers/edit/' . $id, 'error', 'Failed to update supplier');
    }
}

function deleteSupplier($db, $id)
{
    $supplier = $db->fetchOne("SELECT * FROM suppliers WHERE id = ?", [$id]);
    if (!$supplier) {
        redirect(BASE_URL . '/suppliers', 'error', 'Supplier not found');
        return;
    }

    // Check if supplier has linked products or deliveries
    $linked = $db->fetchOne("
        SELECT
            (SELECT COUNT(*) FROM products      WHERE supplier_id = ?) AS products,
            (SELECT COUNT(*) FROM stock_movements WHERE supplier_id = ?) AS deliveries
    ", [$id, $id]);

    if (intval($linked['products']) > 0 || intval($linked['deliveries']) > 0) {
        // Soft delete — keep data integrity
        $db->query("UPDATE suppliers SET is_active = 0 WHERE id = ?", [$id]);
        redirect(
            BASE_URL . '/suppliers',
            'success',
            "Supplier deactivated (has {$linked['products']} product(s) and {$linked['deliveries']} delivery record(s))"
        );
    } else {
        $db->query("DELETE FROM suppliers WHERE id = ?", [$id]);
        redirect(BASE_URL . '/suppliers', 'success', 'Supplier deleted successfully');
    }
}

function toggleSupplier($db, $id)
{
    header('Content-Type: application/json');
    $supplier = $db->fetchOne("SELECT id, is_active FROM suppliers WHERE id = ?", [$id]);
    if (!$supplier) {
        echo json_encode(['success' => false, 'message' => 'Not found']);
        exit;
    }
    $new = $supplier['is_active'] ? 0 : 1;
    $db->query("UPDATE suppliers SET is_active = ? WHERE id = ?", [$new, $id]);
    echo json_encode([
        'success'   => true,
        'is_active' => $new,
        'message'   => $new ? 'Supplier activated' : 'Supplier deactivated'
    ]);
    exit;
}

function searchSuppliers($db)
{
    header('Content-Type: application/json');
    // Handle "all suppliers" request
    if (isset($_GET['all']) && $_GET['all'] == '1') {
        $suppliers = $db->fetchAll("
        SELECT id, company_name, supplier_code, phone 
        FROM suppliers 
        WHERE is_active = 1 
        ORDER BY company_name ASC
    ");
        echo json_encode($suppliers);
        return;
    }
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 1) {
        echo json_encode([]);
        return;
    }

    $suppliers = $db->fetchAll("
        SELECT id, supplier_code, company_name, contact_name, phone
        FROM suppliers
        WHERE is_active = 1
          AND (company_name LIKE ? OR phone LIKE ? OR supplier_code LIKE ?)
        ORDER BY company_name ASC
        LIMIT 10
    ", ["%$q%", "%$q%", "%$q%"]);

    echo json_encode($suppliers);
}

// ============================================================
// HELPER
// ============================================================
function generateSupplierCode($db)
{
    $prefix = 'SUP-';
    $year   = date('Y');
    $last   = $db->fetchOne("
        SELECT supplier_code FROM suppliers
        WHERE supplier_code LIKE ?
        ORDER BY id DESC LIMIT 1
    ", ["$prefix$year-%"]);

    $num = $last
        ? intval(end(explode('-', $last['supplier_code']))) + 1
        : 1;

    return $prefix . $year . '-' . str_pad($num, 4, '0', STR_PAD_LEFT);
}
