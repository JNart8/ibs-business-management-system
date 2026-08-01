<?php

/**
 * Purchase Controller
 * Handles all purchase operations including stock updates and cost tracking
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

if (($segments[0] ?? '') === 'create-purchase') {
    $action = 'create';
    $id     = null;
} elseif (count($segments) <= 1) {
    $action = 'index';
    $id     = null;
} elseif (count($segments) === 2) {
    $action = $segments[1];
    $id     = null;
} else {
    $action = $segments[1];
    $id     = $segments[2];
}

// Route
switch ($action) {
    case 'index':
        listPurchases($db);
        break;
    case 'create':
        showCreatePurchase($db);
        break;
    case 'complete':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') completePurchase($db);
        else redirect(BASE_URL . '/purchases/create', 'error', 'Invalid request');
        break;
    case 'view':
        if (!$id) redirect(BASE_URL . '/purchases', 'error', 'Purchase ID required');
        viewPurchase($db, $id);
        break;
    case 'edit':
        if (!$id) redirect(BASE_URL . '/purchases', 'error', 'Purchase ID required');
        showEditPurchase($db, $id);
        break;
    case 'update':
        if (!$id) redirect(BASE_URL . '/purchases', 'error', 'Purchase ID required');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') updatePurchase($db, $id);
        else redirect(BASE_URL . '/purchases/edit/' . $id, 'error', 'Invalid request');
        break;
    case 'pay':
        if (!$id) redirect(BASE_URL . '/purchases', 'error', 'Purchase ID required');
        $_SERVER['REQUEST_METHOD'] === 'POST'
            ? processPurchasePayment($db, $id)
            : showPurchasePaymentForm($db, $id);
        break;
    case 'receipt':
        if (!$id) redirect(BASE_URL . '/purchases', 'error', 'Purchase ID required');
        printPurchaseReceipt($db, $id);
        break;
    case 'void':
        if (!$id) redirect(BASE_URL . '/purchases', 'error', 'Purchase ID required');
        voidPurchase($db, $id);
        break;
    default:
        http_response_code(404);
        echo "<h1>Action not found</h1><a href='" . BASE_URL . "/purchases'>← Back</a>";
        break;
}

// ============================================================
// FUNCTIONS
// ============================================================

/**
 * Show purchase creation interface (POS-style)
 */
function showCreatePurchase($db)
{
    // Get all active suppliers
    $suppliers = $db->fetchAll("
        SELECT id, supplier_code, company_name, contact_name, phone, current_balance
        FROM suppliers
        WHERE is_active = 1
        ORDER BY company_name ASC
    ");

    // Recent products for quick access
    $quickProducts = $db->fetchAll("
        SELECT p.id, p.name, p.sku, p.cost_price, p.average_cost, 
               p.current_stock, p.unit, p.supplier_id,
               s.company_name AS supplier_name
        FROM products p
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        WHERE p.is_active = 1
        ORDER BY p.id DESC
        LIMIT 20
    ");

    // Categories for filtering
    $categories = $db->fetchAll("
        SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC
    ");

    // Financial accounts for the payment account selector
    $financialAccounts = $db->fetchAll(
        "SELECT id, name, type, provider, balance FROM accounts WHERE is_active = 1 ORDER BY type ASC, name ASC"
    );

    // Check if a product is preselected
    $preselectedProduct = null;
    $preselectId = intval($_GET['product'] ?? $_GET['product_id'] ?? 0);
    if ($preselectId > 0) {
        $preselectedProduct = $db->fetchOne("
            SELECT p.id, p.name, p.sku, p.cost_price, p.average_cost, 
                   p.current_stock, p.unit, p.supplier_id,
                   s.company_name AS supplier_name
            FROM products p
            LEFT JOIN suppliers s ON p.supplier_id = s.id
            WHERE p.id = ? AND p.is_active = 1
        ", [$preselectId]);
    }

    $pageTitle = 'Create Purchase';
    include APP_PATH . '/views/purchases/create.php';
}

/**
 * Complete a purchase - create record, update costs, update stock
 */
function completePurchase($db)
{
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        return;
    }

    $supplierId    = intval($input['supplier_id']      ?? 0);
    $items         = $input['items']                   ?? [];
    $discountPct   = floatval($input['discount_pct']   ?? 0);
    $vatPct        = floatval($input['vat_pct']        ?? 0);
    $paymentMethod = $input['payment_method']          ?? 'credit';
    $amountPaid    = floatval($input['amount_paid']    ?? 0);
    $invoiceNumber = trim($input['invoice_number']     ?? '');
    $notes         = trim($input['notes']              ?? '');
    $userId        = $_SESSION['user_id']              ?? null;
    $accountId     = intval($input['account_id']       ?? 0);
    $customDate    = trim($input['purchase_date']      ?? '');

    // Validate custom date
    $purchaseDate = null;
    if (!empty($customDate)) {
        $dateObj = DateTime::createFromFormat('Y-m-d', $customDate);
        if (!$dateObj) {
            echo json_encode(['success' => false, 'message' => 'Invalid date format']);
            return;
        }

        if ($dateObj > new DateTime()) {
            echo json_encode(['success' => false, 'message' => 'Cannot create purchases in the future']);
            return;
        }

        $purchaseDate = $dateObj->format('Y-m-d') . ' 12:00:00';
    }

    // Validate
    if ($supplierId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please select a supplier']);
        return;
    }
    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => 'No items in purchase']);
        return;
    }

    $supplier = $db->fetchOne("SELECT * FROM suppliers WHERE id = ? AND is_active = 1", [$supplierId]);
    if (!$supplier) {
        echo json_encode(['success' => false, 'message' => 'Supplier not found']);
        return;
    }

    // Validate all items and calculate totals
    $validatedItems = [];
    $subtotal       = 0;
    $itemsData      = [];

    foreach ($items as $item) {
        $productId = intval($item['product_id'] ?? 0);
        $qty       = intval($item['quantity']   ?? 0);
        $unitCost  = floatval($item['cost']     ?? 0);
        $itemDisc  = floatval($item['discount'] ?? 0);

        if ($productId <= 0 || $qty <= 0 || $unitCost <= 0) continue;

        $product = $db->fetchOne("SELECT * FROM products WHERE id = ? AND is_active = 1", [$productId]);
        if (!$product) {
            echo json_encode(['success' => false, 'message' => "Product ID $productId not found"]);
            return;
        }

        $lineTotal        = ($unitCost * $qty) * (1 - $itemDisc / 100);
        $subtotal        += $lineTotal;
        $validatedItems[] = [
            'product'   => $product,
            'qty'       => $qty,
            'unitCost'  => $unitCost,
            'discount'  => $itemDisc,
            'lineTotal' => $lineTotal,
        ];

        $itemsData[] = [
            'product_id'       => $product['id'],
            'product_name'     => $product['name'],
            'sku'              => $product['sku'],
            'quantity'         => $qty,
            'unit_cost'        => $unitCost,
            'discount_percent' => $itemDisc,
            'line_total'       => $lineTotal,
        ];
    }

    if (empty($validatedItems)) {
        echo json_encode(['success' => false, 'message' => 'No valid items in purchase']);
        return;
    }

    // Calculate totals
    $discountAmount = $subtotal * ($discountPct / 100);
    $afterDiscount  = $subtotal - $discountAmount;
    $vatAmount      = $afterDiscount * ($vatPct / 100);
    $totalAmount    = $afterDiscount + $vatAmount;

    // Determine payment status
    $amountDue = max(0, $totalAmount - $amountPaid);

    if ($amountPaid <= 0 && $paymentMethod === 'credit') {
        $paymentStatus = 'unpaid';
        $amountDue     = $totalAmount;
        $amountPaid    = 0;
    } elseif ($amountDue <= 0.01) {
        $paymentStatus = 'paid';
        $amountPaid    = $totalAmount;
        $amountDue     = 0;
    } else {
        $paymentStatus = 'partial';
    }

    // Generate purchase number
    $purchaseNumber = generatePurchaseNumber($db);

    try {
        $db->beginTransaction();

        // 1. Insert purchase header
        if (!empty($purchaseDate)) {
            $db->query("
                INSERT INTO purchases
                    (purchase_number, supplier_id, subtotal, discount_percent,
                     discount_amount, vat_percent, vat_amount, total_amount,
                     payment_status, payment_method, amount_paid, amount_due,
                     invoice_number, notes, user_id, purchase_date, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $purchaseNumber,
                $supplierId,
                $subtotal,
                $discountPct,
                $discountAmount,
                $vatPct,
                $vatAmount,
                $totalAmount,
                $paymentStatus,
                $paymentMethod,
                $amountPaid,
                $amountDue,
                $invoiceNumber ?: null,
                $notes ?: null,
                $userId,
                $purchaseDate,
                $purchaseDate,
                $purchaseDate
            ]);
        } else {
            $db->query("
                INSERT INTO purchases
                    (purchase_number, supplier_id, subtotal, discount_percent,
                     discount_amount, vat_percent, vat_amount, total_amount,
                     payment_status, payment_method, amount_paid, amount_due,
                     invoice_number, notes, user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $purchaseNumber,
                $supplierId,
                $subtotal,
                $discountPct,
                $discountAmount,
                $vatPct,
                $vatAmount,
                $totalAmount,
                $paymentStatus,
                $paymentMethod,
                $amountPaid,
                $amountDue,
                $invoiceNumber ?: null,
                $notes ?: null,
                $userId
            ]);
        }

        $purchaseId = $db->lastInsertId();

        // 2. Insert purchase items, update stock & costs
        foreach ($validatedItems as $vi) {
            $p = $vi['product'];

            // Insert line item
            $db->query("
                INSERT INTO purchase_items
                    (purchase_id, product_id, product_name, quantity,
                     unit_cost, discount_percent, line_total)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ", [
                $purchaseId,
                $p['id'],
                $p['name'],
                $vi['qty'],
                $vi['unitCost'],
                $vi['discount'],
                $vi['lineTotal']
            ]);

            // Calculate new weighted average cost
            $currentStock   = intval($p['current_stock']);
            $currentAvgCost = floatval($p['average_cost']);
            $newQty         = $vi['qty'];
            $newCost        = $vi['unitCost'];

            // Weighted Average Formula: 
            // New Avg = (Current Stock × Current Avg + New Qty × New Cost) / (Current Stock + New Qty)
            if ($currentStock + $newQty > 0) {
                $newAverageCost = (($currentStock * $currentAvgCost) + ($newQty * $newCost)) / ($currentStock + $newQty);
            } else {
                $newAverageCost = $newCost;
            }

            $newStock = $currentStock + $newQty;

            // Update product
            $db->query("
                UPDATE products
                SET current_stock = ?,
                    cost_price = ?,
                    average_cost = ?,
                    last_purchase_cost = ?,
                    last_purchase_date = NOW()
                WHERE id = ?
            ", [$newStock, $newCost, $newAverageCost, $newCost, $p['id']]);

            // Log stock movement
            $db->query("
                INSERT INTO stock_movements
                    (product_id, movement_type, quantity, reference_type,
                     reference_id, previous_stock, new_stock, user_id)
                VALUES (?, 'in', ?, 'purchase', ?, ?, ?, ?)
            ", [$p['id'], $newQty, $purchaseId, $currentStock, $newStock, $userId]);

            // Log cost history
            $changePercent = $currentAvgCost > 0
                ? (($newAverageCost - $currentAvgCost) / $currentAvgCost) * 100
                : 0;

            $db->query("
                INSERT INTO product_cost_history
                    (product_id, purchase_id, old_cost, new_cost, unit_cost,
                     quantity, change_percent, stock_before, stock_after)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $p['id'],
                $purchaseId,
                $currentAvgCost,
                $newAverageCost,
                $newCost,
                $newQty,
                $changePercent,
                $currentStock,
                $newStock
            ]);
        }

        // 3. Update supplier balance & log transaction
        $balanceBefore = floatval($supplier['current_balance']);

        // Record purchase (increases what we owe - negative balance)
        $db->query("
            INSERT INTO supplier_transactions
                (supplier_id, transaction_type, amount, balance_before,
                 balance_after, reference_type, reference_id, payment_method, user_id)
            VALUES (?, 'purchase', ?, ?, ?, 'purchase', ?, ?, ?)
        ", [
            $supplierId,
            -$totalAmount, // Negative = we owe them
            $balanceBefore,
            $balanceBefore - $totalAmount,
            $purchaseId,
            $paymentMethod,
            $userId
        ]);

        // Record payment if any
        if ($amountPaid > 0) {
            $db->query("
                INSERT INTO supplier_transactions
                    (supplier_id, transaction_type, amount, balance_before,
                     balance_after, reference_type, reference_id, payment_method, user_id)
                VALUES (?, 'payment', ?, ?, ?, 'purchase', ?, ?, ?)
            ", [
                $supplierId,
                $amountPaid, // Positive = we paid them
                $balanceBefore - $totalAmount,
                $balanceBefore - $totalAmount + $amountPaid,
                $purchaseId,
                $paymentMethod,
                $userId
            ]);
        }

        // Update supplier balance and total purchases
        $netEffect = $amountPaid - $totalAmount; // Usually negative (we owe)
        $db->query("
            UPDATE suppliers
            SET current_balance  = current_balance + ?,
                total_purchases  = total_purchases + ?
            WHERE id = ?
        ", [$netEffect, $totalAmount, $supplierId]);

        // Record withdrawal from financial account
        if ($amountPaid > 0 && $paymentMethod !== 'credit') {
            $resolvedAccountId = null;
            if ($accountId > 0) {
                $typeMap   = ['cash' => 'cash', 'mobile' => 'mobile_money', 'bank' => 'bank', 'cheque' => 'bank'];
                $expected  = $typeMap[$paymentMethod] ?? null;
                $acctCheck = $db->fetchOne(
                    "SELECT id FROM accounts WHERE id = ? AND is_active = 1" . ($expected ? " AND type = ?" : ""),
                    $expected ? [$accountId, $expected] : [$accountId]
                );
                $resolvedAccountId = $acctCheck ? $accountId : null;
            }

            recordAccountTransaction(
                $db,
                $paymentMethod,
                $amountPaid,
                'withdrawal',
                'purchase',
                $purchaseId,
                "Payment for Purchase #" . $purchaseNumber,
                $resolvedAccountId
            );
        }

        $db->commit();

        // Get user name
        $user = $db->fetchOne("SELECT full_name FROM users WHERE id = ?", [$userId]);

        echo json_encode([
            'success'          => true,
            'purchase_id'      => $purchaseId,
            'purchase_number'  => $purchaseNumber,
            'subtotal'         => $subtotal,
            'total_amount'     => $totalAmount,
            'discount_amount'  => $discountAmount,
            'vat_amount'       => $vatAmount,
            'amount_paid'      => $amountPaid,
            'amount_due'       => $amountDue,
            'payment_method'   => $paymentMethod,
            'payment_status'   => $paymentStatus,
            'supplier_name'    => $supplier['company_name'],
            'created_by'       => $user ? $user['full_name'] : 'Staff',
            'items'            => $itemsData,
            'receipt_url'      => BASE_URL . '/purchases/receipt/' . $purchaseId,
            'message'          => 'Purchase recorded successfully'
        ]);
    } catch (Exception $e) {
        $db->rollback();
        error_log('Purchase error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to record purchase: ' . $e->getMessage()]);
    }
}

/**
 * List all purchases
 */
function listPurchases($db)
{
    $search = trim($_GET['search'] ?? '');
    $status = $_GET['status']     ?? '';
    $date   = $_GET['date']       ?? '';
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = ITEMS_PER_PAGE;
    $offset = ($page - 1) * $limit;

    $params = [];
    $where  = "WHERE 1=1";

    if (!empty($search)) {
        $where   .= " AND (p.purchase_number LIKE ? OR s.company_name LIKE ? OR p.invoice_number LIKE ?)";
        $t        = "%$search%";
        $params[] = $t;
        $params[] = $t;
        $params[] = $t;
    }
    if (!empty($status) && in_array($status, ['paid', 'partial', 'unpaid'])) {
        $where .= " AND p.payment_status = ?";
        $params[] = $status;
    }
    if (!empty($date)) {
        $where .= " AND DATE(p.purchase_date) = ?";
        $params[] = $date;
    }

    $total      = $db->fetchOne("
        SELECT COUNT(*) AS cnt FROM purchases p
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        $where
    ", $params);
    $totalCount = intval($total['cnt'] ?? 0);
    $totalPages = max(1, ceil($totalCount / $limit));

    $purchases = $db->fetchAll("
        SELECT
            p.*,
            s.company_name AS supplier_name,
            s.contact_name,
            s.phone AS supplier_phone,
            u.full_name AS created_by
        FROM purchases p
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        LEFT JOIN users u ON p.user_id = u.id
        $where
        ORDER BY p.purchase_date DESC
        LIMIT ? OFFSET ?
    ", array_merge($params, [$limit, $offset]));

    // Today's stats
    $todayStats = $db->fetchOne("
        SELECT
            COUNT(*)                        AS total_purchases,
            COALESCE(SUM(total_amount), 0)  AS total_spent,
            COALESCE(SUM(amount_paid), 0)   AS paid_today,
            COALESCE(SUM(amount_due), 0)    AS outstanding
        FROM purchases
        WHERE DATE(purchase_date) = CURDATE()
    ");

    $pageTitle = 'Purchases';
    include APP_PATH . '/views/purchases/index.php';
}

/**
 * View single purchase
 */
function viewPurchase($db, $id)
{
    $purchase = $db->fetchOne("
        SELECT p.*, s.company_name, s.contact_name, s.phone AS supplier_phone,
               s.supplier_code, u.full_name AS created_by
        FROM purchases p
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.id = ?
    ", [$id]);

    if (!$purchase) {
        redirect(BASE_URL . '/purchases', 'error', 'Purchase not found');
        return;
    }

    $items = $db->fetchAll("
        SELECT pi.*, p.sku, p.unit, p.current_stock
        FROM purchase_items pi
        LEFT JOIN products p ON pi.product_id = p.id
        WHERE pi.purchase_id = ?
    ", [$id]);

    // Payment history
    $payments = $db->fetchAll("
        SELECT st.*, u.full_name AS user_name
        FROM supplier_transactions st
        LEFT JOIN users u ON st.user_id = u.id
        WHERE st.reference_type = 'purchase'
          AND st.reference_id = ?
          AND st.transaction_type IN ('payment')
        ORDER BY st.created_at ASC
    ", [$id]);

    $pageTitle = 'Purchase #' . $purchase['purchase_number'];
    include APP_PATH . '/views/purchases/view.php';
}

/**
 * Show payment form for outstanding purchase
 */
function showPurchasePaymentForm($db, $id)
{
    $purchase = $db->fetchOne("
        SELECT p.*, s.company_name AS supplier_name
        FROM purchases p LEFT JOIN suppliers s ON p.supplier_id = s.id
        WHERE p.id = ? AND p.payment_status != 'paid'
    ", [$id]);

    if (!$purchase) {
        redirect(BASE_URL . '/purchases', 'error', 'Purchase not found or already paid');
        return;
    }

    // Financial accounts for the payment account selector
    $accounts = $db->fetchAll(
        "SELECT id, name, type, provider, balance FROM accounts WHERE is_active = 1 ORDER BY type ASC, name ASC"
    );

    $pageTitle = 'Record Payment';
    include APP_PATH . '/views/purchases/pay.php';
}

/**
 * Process payment on outstanding purchase
 */
function processPurchasePayment($db, $id)
{
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect(BASE_URL . '/purchases/pay/' . $id, 'error', 'Invalid form submission');
        return;
    }

    $purchase = $db->fetchOne("SELECT * FROM purchases WHERE id = ?", [$id]);
    if (!$purchase || $purchase['payment_status'] === 'paid') {
        redirect(BASE_URL . '/purchases', 'error', 'Purchase not found or already paid');
        return;
    }

    $amount        = floatval($_POST['amount']         ?? 0);
    $paymentMethod = trim($_POST['payment_method']     ?? 'cash');
    $userId        = $_SESSION['user_id']              ?? null;
    $accountId     = intval($_POST['account_id']       ?? 0);
    $paymentDate   = trim($_POST['payment_date']       ?? '');

    // Validate and parse optional payment date
    $paymentDateSql = null;
    if (!empty($paymentDate)) {
        $dateObj = DateTime::createFromFormat('Y-m-d', $paymentDate);
        if (!$dateObj || $dateObj > new DateTime('today')) {
            redirect(BASE_URL . '/purchases/pay/' . $id, 'error', 'Invalid payment date — must be today or a past date');
            return;
        }
        $paymentDateSql = $dateObj->format('Y-m-d') . ' 12:00:00';
    }

    if ($amount <= 0) {
        redirect(BASE_URL . '/purchases/pay/' . $id, 'error', 'Amount must be greater than zero');
        return;
    }

    $amountDue = floatval($purchase['amount_due']);
    $payment   = min($amount, $amountDue);

    try {
        $db->beginTransaction();

        $newAmountPaid = floatval($purchase['amount_paid']) + $payment;
        $newAmountDue  = $amountDue - $payment;
        $newStatus     = $newAmountDue <= 0.01 ? 'paid' : 'partial';

        // Update purchase
        $db->query("
            UPDATE purchases
            SET amount_paid = ?, amount_due = ?, payment_status = ?
            WHERE id = ?
        ", [$newAmountPaid, max(0, $newAmountDue), $newStatus, $id]);

        // Get supplier for balance update
        $supplier      = $db->fetchOne("SELECT * FROM suppliers WHERE id = ?", [$purchase['supplier_id']]);
        $balanceBefore = floatval($supplier['current_balance']);
        $balanceAfter  = $balanceBefore + $payment;

        // Log payment transaction
        if ($paymentDateSql) {
            $db->query("
                INSERT INTO supplier_transactions
                    (supplier_id, transaction_type, amount, balance_before,
                     balance_after, reference_type, reference_id, payment_method, user_id, created_at)
                VALUES (?, 'payment', ?, ?, ?, 'purchase', ?, ?, ?, ?)
            ", [
                $purchase['supplier_id'],
                $payment,
                $balanceBefore,
                $balanceAfter,
                $id,
                $paymentMethod,
                $userId,
                $paymentDateSql
            ]);
        } else {
            $db->query("
                INSERT INTO supplier_transactions
                    (supplier_id, transaction_type, amount, balance_before,
                     balance_after, reference_type, reference_id, payment_method, user_id)
                VALUES (?, 'payment', ?, ?, ?, 'purchase', ?, ?, ?)
            ", [
                $purchase['supplier_id'],
                $payment,
                $balanceBefore,
                $balanceAfter,
                $id,
                $paymentMethod,
                $userId
            ]);
        }

        // Update supplier balance
        $db->query(
            "UPDATE suppliers SET current_balance = current_balance + ? WHERE id = ?",
            [$payment, $purchase['supplier_id']]
        );

        // Record withdrawal from financial account
        if ($payment > 0) {
            $resolvedAccountId = null;
            if ($accountId > 0) {
                $typeMap   = ['cash' => 'cash', 'mobile' => 'mobile_money', 'bank' => 'bank', 'cheque' => 'bank'];
                $expected  = $typeMap[$paymentMethod] ?? null;
                $acctCheck = $db->fetchOne(
                    "SELECT id FROM accounts WHERE id = ? AND is_active = 1" . ($expected ? " AND type = ?" : ""),
                    $expected ? [$accountId, $expected] : [$accountId]
                );
                $resolvedAccountId = $acctCheck ? $accountId : null;
            }

            recordAccountTransaction(
                $db,
                $paymentMethod,
                $payment,
                'withdrawal',
                'purchase',
                $id,
                "Payment against Purchase #" . $purchase['purchase_number'],
                $resolvedAccountId
            );
        }

        $db->commit();
        redirect(
            BASE_URL . '/purchases/view/' . $id,
            'success',
            'Payment of ' . formatMoney($payment) . ' recorded successfully'
        );
    } catch (Exception $e) {
        $db->rollback();
        error_log('Purchase payment error: ' . $e->getMessage());
        redirect(BASE_URL . '/purchases/pay/' . $id, 'error', 'Failed to process payment');
    }
}

/**
 * Print purchase receipt
 */
function printPurchaseReceipt($db, $id)
{
    $purchase = $db->fetchOne("
        SELECT p.*, s.company_name, s.contact_name, s.phone AS supplier_phone,
               s.supplier_code, u.full_name AS created_by
        FROM purchases p
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.id = ?
    ", [$id]);

    if (!$purchase) {
        redirect(BASE_URL . '/purchases', 'error', 'Purchase not found');
        return;
    }

    $items    = $db->fetchAll("SELECT * FROM purchase_items WHERE purchase_id = ?", [$id]);
    $settings = $db->fetchOne("SELECT * FROM settings LIMIT 1");

    $pageTitle = 'Purchase Receipt - ' . $purchase['purchase_number'];
    include APP_PATH . '/views/purchases/receipt.php';
}

// ============================================================
// HELPERS
// ============================================================

function generatePurchaseNumber($db)
{
    $prefix = 'PUR-' . date('Ymd') . '-';
    $last   = $db->fetchOne("
        SELECT purchase_number FROM purchases
        WHERE purchase_number LIKE ?
        ORDER BY id DESC LIMIT 1
    ", ["$prefix%"]);

    $num = $last ? intval(substr($last['purchase_number'], strrpos($last['purchase_number'], '-') + 1)) + 1 : 1;
    return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
}

/**
 * Show purchase edit interface
 */
function showEditPurchase($db, $id)
{
    $purchase = $db->fetchOne("SELECT * FROM purchases WHERE id = ?", [$id]);
    if (!$purchase) {
        redirect(BASE_URL . '/purchases', 'error', 'Purchase not found');
        return;
    }

    $purchaseItems = $db->fetchAll("
        SELECT pi.*, p.sku, p.unit, p.current_stock
        FROM purchase_items pi
        LEFT JOIN products p ON pi.product_id = p.id
        WHERE pi.purchase_id = ?
    ", [$id]);

    // Get all active suppliers
    $suppliers = $db->fetchAll("
        SELECT id, supplier_code, company_name, contact_name, phone, current_balance
        FROM suppliers
        WHERE is_active = 1
        ORDER BY company_name ASC
    ");

    // Recent products for quick access
    $quickProducts = $db->fetchAll("
        SELECT p.id, p.name, p.sku, p.cost_price, p.average_cost, 
               p.current_stock, p.unit, p.supplier_id,
               s.company_name AS supplier_name
        FROM products p
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        WHERE p.is_active = 1
        ORDER BY p.id DESC
        LIMIT 20
    ");

    // Categories for filtering
    $categories = $db->fetchAll("
        SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC
    ");

    // Financial accounts for the payment account selector
    $financialAccounts = $db->fetchAll(
        "SELECT id, name, type, provider, balance FROM accounts WHERE is_active = 1 ORDER BY type ASC, name ASC"
    );

    // Find if there is an active account transaction to highlight which account was used
    $oldAcctTx = $db->fetchOne("
        SELECT account_id FROM account_transactions 
        WHERE reference_type = 'purchase' AND reference_id = ? AND transaction_type = 'withdrawal'
        LIMIT 1
    ", [$id]);
    $selectedAccountId = $oldAcctTx ? intval($oldAcctTx['account_id']) : 0;

    $pageTitle = 'Edit Purchase #' . $purchase['purchase_number'];
    include APP_PATH . '/views/purchases/edit.php';
}

/**
 * Process purchase update
 */
function updatePurchase($db, $id)
{
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        return;
    }

    $purchase = $db->fetchOne("SELECT * FROM purchases WHERE id = ?", [$id]);
    if (!$purchase) {
        echo json_encode(['success' => false, 'message' => 'Purchase not found']);
        return;
    }

    $supplierId    = intval($input['supplier_id']      ?? 0);
    $items         = $input['items']                   ?? [];
    $discountPct   = floatval($input['discount_pct']   ?? 0);
    $vatPct        = floatval($input['vat_pct']        ?? 0);
    $paymentMethod = $input['payment_method']          ?? 'credit';
    $amountPaid    = floatval($input['amount_paid']    ?? 0);
    $invoiceNumber = trim($input['invoice_number']     ?? '');
    $notes         = trim($input['notes']              ?? '');
    $userId        = $_SESSION['user_id']              ?? null;
    $accountId     = intval($input['account_id']       ?? 0);

    if ($supplierId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please select a supplier']);
        return;
    }
    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => 'No items in purchase']);
        return;
    }

    $supplier = $db->fetchOne("SELECT * FROM suppliers WHERE id = ? AND is_active = 1", [$supplierId]);
    if (!$supplier) {
        echo json_encode(['success' => false, 'message' => 'Supplier not found']);
        return;
    }

    // Load old items for comparison
    $oldItems = $db->fetchAll("SELECT * FROM purchase_items WHERE purchase_id = ?", [$id]);
    $oldItemsMap = [];
    foreach ($oldItems as $oi) {
        $oldItemsMap[intval($oi['product_id'])] = $oi;
    }

    // Map new items
    $newItemsMap = [];
    $validatedItems = [];
    $subtotal = 0;
    $itemsData = [];

    foreach ($items as $item) {
        $productId = intval($item['product_id'] ?? 0);
        $qty       = intval($item['quantity']   ?? 0);
        $unitCost  = floatval($item['cost']     ?? 0);
        $itemDisc  = floatval($item['discount'] ?? 0);

        if ($productId <= 0 || $qty <= 0 || $unitCost <= 0) continue;

        $product = $db->fetchOne("SELECT * FROM products WHERE id = ?", [$productId]);
        if (!$product) {
            echo json_encode(['success' => false, 'message' => "Product ID $productId not found"]);
            return;
        }

        $newItemsMap[$productId] = [
            'qty' => $qty,
            'cost' => $unitCost
        ];

        $lineTotal        = ($unitCost * $qty) * (1 - $itemDisc / 100);
        $subtotal        += $lineTotal;
        $validatedItems[] = [
            'product'   => $product,
            'qty'       => $qty,
            'unitCost'  => $unitCost,
            'discount'  => $itemDisc,
            'lineTotal' => $lineTotal,
        ];

        $itemsData[] = [
            'product_id'       => $product['id'],
            'product_name'     => $product['name'],
            'sku'              => $product['sku'],
            'quantity'         => $qty,
            'unit_cost'        => $unitCost,
            'discount_percent' => $itemDisc,
            'line_total'       => $lineTotal,
        ];
    }

    if (empty($validatedItems)) {
        echo json_encode(['success' => false, 'message' => 'No valid items in purchase']);
        return;
    }

    // --- Validate Stock and verify we don't result in negative stock ---
    $allProductIds = array_unique(array_merge(array_keys($oldItemsMap), array_keys($newItemsMap)));
    $stockChecks = [];

    foreach ($allProductIds as $pid) {
        $oldQty = isset($oldItemsMap[$pid]) ? intval($oldItemsMap[$pid]['quantity']) : 0;
        $newQty = isset($newItemsMap[$pid]) ? intval($newItemsMap[$pid]['qty']) : 0;
        $diff = $newQty - $oldQty;

        if ($diff < 0) {
            // Check product current stock
            $p = $db->fetchOne("SELECT name, current_stock FROM products WHERE id = ?", [$pid]);
            $currentStock = intval($p['current_stock'] ?? 0);
            if ($currentStock + $diff < 0) {
                echo json_encode([
                    'success' => false,
                    'message' => "Cannot reduce quantity for product '{$p['name']}' by " . abs($diff) . ". Doing so would reduce stock below zero (Current stock: {$currentStock})."
                ]);
                return;
            }
        }
    }

    // Totals
    $discountAmount = $subtotal * ($discountPct / 100);
    $afterDiscount  = $subtotal - $discountAmount;
    $vatAmount      = $afterDiscount * ($vatPct / 100);
    $totalAmount    = $afterDiscount + $vatAmount;

    $amountDue = max(0, $totalAmount - $amountPaid);
    if ($amountPaid <= 0 && $paymentMethod === 'credit') {
        $paymentStatus = 'unpaid';
        $amountDue     = $totalAmount;
        $amountPaid    = 0;
    } elseif ($amountDue <= 0.01) {
        $paymentStatus = 'paid';
        $amountPaid    = $totalAmount;
        $amountDue     = 0;
    } else {
        $paymentStatus = 'partial';
    }

    try {
        $db->beginTransaction();

        // 1. Revert stock and average cost logic
        foreach ($allProductIds as $pid) {
            $p = $db->fetchOne("SELECT * FROM products WHERE id = ?", [$pid]);
            $currentStock   = intval($p['current_stock']);
            $currentAvgCost = floatval($p['average_cost']);

            $oldQty  = isset($oldItemsMap[$pid]) ? intval($oldItemsMap[$pid]['quantity']) : 0;
            $oldCost = isset($oldItemsMap[$pid]) ? floatval($oldItemsMap[$pid]['unit_cost']) : 0;

            $newQty  = isset($newItemsMap[$pid]) ? intval($newItemsMap[$pid]['qty']) : 0;
            $newCost = isset($newItemsMap[$pid]) ? floatval($newItemsMap[$pid]['cost']) : 0;

            // Revert old purchase effect:
            $stockBefore = $currentStock - $oldQty;
            if ($stockBefore > 0) {
                $avgCostBefore = (($currentStock * $currentAvgCost) - ($oldQty * $oldCost)) / $stockBefore;
            } else {
                $avgCostBefore = 0;
            }

            // Apply new purchase effect:
            $newStock = $stockBefore + $newQty;
            if ($newStock > 0) {
                $newAverageCost = (($stockBefore * $avgCostBefore) + ($newQty * $newCost)) / $newStock;
            } else {
                $newAverageCost = $newCost;
            }

            // Update product
            $db->query("
                UPDATE products
                SET current_stock = ?,
                    cost_price = ?,
                    average_cost = ?,
                    last_purchase_cost = ?,
                    last_purchase_date = NOW()
                WHERE id = ?
            ", [$newStock, ($newQty > 0 ? $newCost : $p['cost_price']), $newAverageCost, ($newQty > 0 ? $newCost : $p['last_purchase_cost']), $pid]);
        }

        // 2. Delete old items & stock movements & cost history
        $db->query("DELETE FROM purchase_items WHERE purchase_id = ?", [$id]);
        $db->query("DELETE FROM stock_movements WHERE reference_type = 'purchase' AND reference_id = ?", [$id]);
        $db->query("DELETE FROM product_cost_history WHERE purchase_id = ?", [$id]);

        // 3. Insert new purchase items, log stock movement & cost history
        foreach ($validatedItems as $vi) {
            $p = $vi['product'];

            $db->query("
                INSERT INTO purchase_items
                    (purchase_id, product_id, product_name, quantity,
                     unit_cost, discount_percent, line_total)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ", [
                $id,
                $p['id'],
                $p['name'],
                $vi['qty'],
                $vi['unitCost'],
                $vi['discount'],
                $vi['lineTotal']
            ]);

            // Log stock movement
            // Retrieve updated stock after our adjustment above
            $updatedProduct = $db->fetchOne("SELECT current_stock, average_cost FROM products WHERE id = ?", [$p['id']]);
            $currentStockAfter = intval($updatedProduct['current_stock']);
            $currentStockBefore = $currentStockAfter - $vi['qty'];

            $db->query("
                INSERT INTO stock_movements
                    (product_id, movement_type, quantity, reference_type,
                     reference_id, previous_stock, new_stock, user_id)
                VALUES (?, 'in', ?, 'purchase', ?, ?, ?, ?)
            ", [$p['id'], $vi['qty'], $id, $currentStockBefore, $currentStockAfter, $userId]);

            // Log cost history
            $oldAvgCost = isset($oldItemsMap[$p['id']]) ? floatval($oldItemsMap[$p['id']]['unit_cost']) : floatval($p['average_cost']);
            $changePercent = $oldAvgCost > 0
                ? ((floatval($updatedProduct['average_cost']) - $oldAvgCost) / $oldAvgCost) * 100
                : 0;

            $db->query("
                INSERT INTO product_cost_history
                    (product_id, purchase_id, old_cost, new_cost, unit_cost,
                     quantity, change_percent, stock_before, stock_after)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $p['id'],
                $id,
                $oldAvgCost,
                floatval($updatedProduct['average_cost']),
                $vi['unitCost'],
                $vi['qty'],
                $changePercent,
                $currentStockBefore,
                $currentStockAfter
            ]);
        }

        // 4. Revert old supplier balance and transactions
        $oldSupplierId = intval($purchase['supplier_id']);
        $oldTotal      = floatval($purchase['total_amount']);
        $oldPaid       = floatval($purchase['amount_paid']);
        $oldNetEffect  = $oldPaid - $oldTotal;

        $db->query("
            UPDATE suppliers
            SET current_balance = current_balance - ?,
                total_purchases = total_purchases - ?
            WHERE id = ?
        ", [$oldNetEffect, $oldTotal, $oldSupplierId]);

        $db->query("DELETE FROM supplier_transactions WHERE reference_type = 'purchase' AND reference_id = ?", [$id]);

        // 5. Apply new supplier balance & log transactions
        $newSupplier = $db->fetchOne("SELECT * FROM suppliers WHERE id = ?", [$supplierId]);
        $balanceBefore = floatval($newSupplier['current_balance']);

        $db->query("
            INSERT INTO supplier_transactions
                (supplier_id, transaction_type, amount, balance_before,
                 balance_after, reference_type, reference_id, payment_method, user_id)
            VALUES (?, 'purchase', ?, ?, ?, 'purchase', ?, ?, ?)
        ", [
            $supplierId,
            -$totalAmount,
            $balanceBefore,
            $balanceBefore - $totalAmount,
            $id,
            $paymentMethod,
            $userId
        ]);

        if ($amountPaid > 0) {
            $db->query("
                INSERT INTO supplier_transactions
                    (supplier_id, transaction_type, amount, balance_before,
                     balance_after, reference_type, reference_id, payment_method, user_id)
                VALUES (?, 'payment', ?, ?, ?, 'purchase', ?, ?, ?)
            ", [
                $supplierId,
                $amountPaid,
                $balanceBefore - $totalAmount,
                $balanceBefore - $totalAmount + $amountPaid,
                $id,
                $paymentMethod,
                $userId
            ]);
        }

        $netEffect = $amountPaid - $totalAmount;
        $db->query("
            UPDATE suppliers
            SET current_balance = current_balance + ?,
                total_purchases = total_purchases + ?
            WHERE id = ?
        ", [$netEffect, $totalAmount, $supplierId]);

        // 6. Revert old financial account transactions
        $oldAcctTxList = $db->fetchAll("SELECT * FROM account_transactions WHERE reference_type = 'purchase' AND reference_id = ?", [$id]);
        foreach ($oldAcctTxList as $otx) {
            $oldAmt = floatval($otx['amount']);
            $oldAcctId = intval($otx['account_id']);
            $oldTxType = $otx['transaction_type'];

            if ($oldTxType === 'withdrawal') {
                $db->query("UPDATE accounts SET balance = balance + ? WHERE id = ?", [$oldAmt, $oldAcctId]);
            } else {
                $db->query("UPDATE accounts SET balance = balance - ? WHERE id = ?", [$oldAmt, $oldAcctId]);
            }
        }
        $db->query("DELETE FROM account_transactions WHERE reference_type = 'purchase' AND reference_id = ?", [$id]);

        // 7. Apply new financial account transaction
        if ($amountPaid > 0 && $paymentMethod !== 'credit') {
            $resolvedAccountId = null;
            if ($accountId > 0) {
                $typeMap   = ['cash' => 'cash', 'mobile' => 'mobile_money', 'bank' => 'bank', 'cheque' => 'bank'];
                $expected  = $typeMap[$paymentMethod] ?? null;
                $acctCheck = $db->fetchOne(
                    "SELECT id FROM accounts WHERE id = ? AND is_active = 1" . ($expected ? " AND type = ?" : ""),
                    $expected ? [$accountId, $expected] : [$accountId]
                );
                $resolvedAccountId = $acctCheck ? $accountId : null;
            }

            recordAccountTransaction(
                $db,
                $paymentMethod,
                $amountPaid,
                'withdrawal',
                'purchase',
                $id,
                "Payment for Purchase #" . $purchase['purchase_number'] . " (Updated)",
                $resolvedAccountId
            );
        }

        // 8. Update purchase header
        $db->query("
            UPDATE purchases
            SET supplier_id = ?,
                subtotal = ?,
                discount_percent = ?,
                discount_amount = ?,
                vat_percent = ?,
                vat_amount = ?,
                total_amount = ?,
                payment_status = ?,
                payment_method = ?,
                amount_paid = ?,
                amount_due = ?,
                invoice_number = ?,
                notes = ?,
                updated_at = NOW()
            WHERE id = ?
        ", [
            $supplierId,
            $subtotal,
            $discountPct,
            $discountAmount,
            $vatPct,
            $vatAmount,
            $totalAmount,
            $paymentStatus,
            $paymentMethod,
            $amountPaid,
            $amountDue,
            $invoiceNumber ?: null,
            $notes ?: null,
            $id
        ]);

        $db->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Purchase modified successfully',
            'redirect' => BASE_URL . '/purchases/view/' . $id
        ]);

    } catch (Exception $e) {
        $db->rollback();
        error_log('Modify purchase error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to modify purchase: ' . $e->getMessage()]);
    }
}

/**
 * Void/delete a purchase and reverse all associated stock and financial effects
 */
function voidPurchase($db, $id)
{
    $purchase = $db->fetchOne("SELECT * FROM purchases WHERE id = ?", [$id]);
    if (!$purchase) {
        redirect(BASE_URL . '/purchases', 'error', 'Purchase not found');
        return;
    }

    $items  = $db->fetchAll("SELECT * FROM purchase_items WHERE purchase_id = ?", [$id]);
    $userId = $_SESSION['user_id'] ?? null;

    // Validate that deducting the purchased stock will not result in negative stock
    foreach ($items as $item) {
        $p = $db->fetchOne("SELECT name, current_stock FROM products WHERE id = ?", [$item['product_id']]);
        if (!$p) continue;
        
        $currentStock = intval($p['current_stock']);
        $qty = intval($item['quantity']);
        if ($currentStock - $qty < 0) {
            redirect(BASE_URL . '/purchases/view/' . $id, 'error', "Cannot void purchase. Deducting {$qty} from '{$p['name']}' would reduce stock below zero (Current stock: {$currentStock}).");
            return;
        }
    }

    try {
        $db->beginTransaction();

        // 1. Revert stock and average cost logic
        foreach ($items as $item) {
            $pid = intval($item['product_id']);
            $p = $db->fetchOne("SELECT * FROM products WHERE id = ?", [$pid]);
            if (!$p) continue;

            $currentStock   = intval($p['current_stock']);
            $currentAvgCost = floatval($p['average_cost']);
            $oldQty         = intval($item['quantity']);
            $oldCost        = floatval($item['unit_cost']);

            $newStock = $currentStock - $oldQty;
            if ($newStock > 0) {
                $newAverageCost = (($currentStock * $currentAvgCost) - ($oldQty * $oldCost)) / $newStock;
            } else {
                $newAverageCost = $oldCost;
            }

            // Find the previous purchase cost/date prior to the one being voided
            $prevPurchaseItem = $db->fetchOne("
                SELECT pi.unit_cost, p.purchase_date
                FROM purchase_items pi
                JOIN purchases p ON pi.purchase_id = p.id
                WHERE pi.product_id = ? AND pi.purchase_id != ?
                ORDER BY p.purchase_date DESC, pi.id DESC LIMIT 1
            ", [$pid, $id]);

            $prevCost = $prevPurchaseItem ? floatval($prevPurchaseItem['unit_cost']) : $p['cost_price'];
            $prevDate = $prevPurchaseItem ? $prevPurchaseItem['purchase_date'] : null;

            $db->query("
                UPDATE products
                SET current_stock = ?,
                    average_cost = ?,
                    last_purchase_cost = ?,
                    last_purchase_date = ?
                WHERE id = ?
            ", [$newStock, $newAverageCost, $prevCost, $prevDate, $pid]);
        }

        // 2. Revert supplier balance and total purchases
        $supplierId = intval($purchase['supplier_id']);
        $total      = floatval($purchase['total_amount']);
        $paid       = floatval($purchase['amount_paid']);
        $netEffect  = $paid - $total;

        $db->query("
            UPDATE suppliers
            SET current_balance = current_balance - ?,
                total_purchases = total_purchases - ?
            WHERE id = ?
        ", [$netEffect, $total, $supplierId]);

        // 3. Revert financial account transaction balances
        $accountTxList = $db->fetchAll("SELECT * FROM account_transactions WHERE reference_type = 'purchase' AND reference_id = ?", [$id]);
        foreach ($accountTxList as $tx) {
            $amt = floatval($tx['amount']);
            $acctId = intval($tx['account_id']);
            $txType = $tx['transaction_type'];

            if ($txType === 'withdrawal') {
                $db->query("UPDATE accounts SET balance = balance + ? WHERE id = ?", [$amt, $acctId]);
            } else {
                $db->query("UPDATE accounts SET balance = balance - ? WHERE id = ?", [$amt, $acctId]);
            }
        }

        // 4. Delete all associated entries
        $db->query("DELETE FROM stock_movements WHERE reference_type = 'purchase' AND reference_id = ?", [$id]);
        $db->query("DELETE FROM product_cost_history WHERE purchase_id = ?", [$id]);
        $db->query("DELETE FROM supplier_transactions WHERE reference_type = 'purchase' AND reference_id = ?", [$id]);
        $db->query("DELETE FROM account_transactions WHERE reference_type = 'purchase' AND reference_id = ?", [$id]);
        
        // 5. Delete the purchase itself (cascade delete handles purchase_items)
        $db->query("DELETE FROM purchases WHERE id = ?", [$id]);

        $db->commit();
        redirect(BASE_URL . '/purchases', 'success', 'Purchase #' . $purchase['purchase_number'] . ' has been voided successfully.');

    } catch (Exception $e) {
        $db->rollback();
        error_log('Void purchase error: ' . $e->getMessage());
        redirect(BASE_URL . '/purchases/view/' . $id, 'error', 'Failed to void purchase: ' . $e->getMessage());
    }
}


