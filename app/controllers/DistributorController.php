<?php

/**
 * Distributor Controller
 * Handles combined purchase-sale distributor deliveries
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

if (($segments[0] ?? '') === 'distributor') {
    $action = $segments[1] ?? 'index';
    $id     = $segments[2] ?? null;
} else {
    $action = 'index';
    $id     = null;
}

// Route
switch ($action) {
    case 'index':
        listDistributorDeliveries($db);
        break;
    case 'create':
        showCreateDistributorDelivery($db);
        break;
    case 'complete':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') completeDistributorDelivery($db);
        else redirect(BASE_URL . '/distributor/create', 'error', 'Invalid request');
        break;
    case 'view':
        if (!$id) redirect(BASE_URL . '/distributor', 'error', 'Delivery ID required');
        viewDistributorDelivery($db, $id);
        break;
    default:
        http_response_code(404);
        echo "<h1>Action not found</h1><a href='" . BASE_URL . "/distributor'>← Back</a>";
        break;
}

// ============================================================
// FUNCTIONS
// ============================================================

/**
 * List direct distributor deliveries
 */
function listDistributorDeliveries($db)
{
    // Pagination
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = ITEMS_PER_PAGE;
    $offset = ($page - 1) * $limit;

    $total = $db->fetchOne("SELECT COUNT(*) as cnt FROM sales WHERE purchase_id IS NOT NULL")['cnt'];
    $totalPages = ceil($total / $limit);

    $deliveries = $db->fetchAll("
        SELECT 
            s.id as sale_id,
            s.sale_number,
            s.sale_date,
            s.total_amount as sale_total,
            s.payment_status as sale_payment_status,
            c.full_name as customer_name,
            p.id as purchase_id,
            p.purchase_number,
            p.total_amount as purchase_total,
            p.payment_status as purchase_payment_status,
            sup.company_name as supplier_name,
            u.full_name as user_name
        FROM sales s
        INNER JOIN purchases p ON s.purchase_id = p.id
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN suppliers sup ON p.supplier_id = sup.id
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.purchase_id IS NOT NULL
        ORDER BY s.id DESC
        LIMIT ? OFFSET ?
    ", [$limit, $offset]);

    $pageTitle = 'Distributor Direct Deliveries';
    include APP_PATH . '/views/distributor/index.php';
}

/**
 * Show Combined Entry Form
 */
function showCreateDistributorDelivery($db)
{
    // Active suppliers
    $suppliers = $db->fetchAll("
        SELECT id, supplier_code, company_name, current_balance
        FROM suppliers
        WHERE is_active = 1
        ORDER BY company_name ASC
    ");

    // Active customers
    $customers = $db->fetchAll("
        SELECT id, customer_code, full_name, current_balance
        FROM customers
        WHERE is_active = 1
        ORDER BY full_name ASC
    ");

    // Quick products
    $quickProducts = $db->fetchAll("
        SELECT id, name, sku, cost_price, average_cost, selling_price, current_stock, unit
        FROM products
        WHERE is_active = 1
        ORDER BY name ASC
    ");

    // Financial accounts
    $financialAccounts = $db->fetchAll("
        SELECT id, name, type, balance
        FROM accounts
        WHERE is_active = 1
        ORDER BY name ASC
    ");

    $pageTitle = 'Direct Delivery Entry';
    include APP_PATH . '/views/distributor/create.php';
}

/**
 * Handle direct delivery submission
 */
function completeDistributorDelivery($db)
{
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid request data']);
        return;
    }

    $supplierId             = intval($input['supplier_id'] ?? 0);
    $customerId             = intval($input['customer_id'] ?? 0);
    $items                  = $input['items'] ?? [];
    
    $supplierPaymentMethod  = $input['supplier_payment_method'] ?? 'credit';
    $supplierAmountPaid     = floatval($input['supplier_amount_paid'] ?? 0);
    $supplierAccountId      = intval($input['supplier_account_id'] ?? 0);

    $customerPaymentMethod  = $input['customer_payment_method'] ?? 'credit';
    $customerAmountPaid     = floatval($input['customer_amount_paid'] ?? 0);
    $customerAccountId      = intval($input['customer_account_id'] ?? 0);

    $discountPct            = floatval($input['discount_pct'] ?? 0);
    $vatPct                 = floatval($input['vat_pct'] ?? 0);
    $notes                  = trim($input['notes'] ?? '');
    $userId                 = $_SESSION['user_id'] ?? null;

    if ($supplierId <= 0 || $customerId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please select both Supplier and Customer']);
        return;
    }

    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => 'Please add at least one item']);
        return;
    }

    // Load supplier & customer
    $supplier = $db->fetchOne("SELECT * FROM suppliers WHERE id = ? AND is_active = 1", [$supplierId]);
    $customer = $db->fetchOne("SELECT * FROM customers WHERE id = ? AND is_active = 1", [$customerId]);

    if (!$supplier || !$customer) {
        echo json_encode(['success' => false, 'message' => 'Selected Supplier or Customer is invalid or inactive']);
        return;
    }

    try {
        $db->beginTransaction();

        $purchaseSubtotal = 0;
        $saleSubtotal     = 0;
        $validatedItems   = [];

        // Validate items and calculate subtotals
        foreach ($items as $item) {
            $productId    = intval($item['product_id'] ?? 0);
            $qty          = floatval($item['quantity'] ?? 0);
            $purchaseCost = floatval($item['purchase_cost'] ?? 0);
            $sellingPrice = floatval($item['selling_price'] ?? 0);

            if ($productId <= 0 || $qty <= 0 || $purchaseCost <= 0 || $sellingPrice <= 0) {
                throw new Exception("Invalid item configuration (zero/negative values detected)");
            }

            $product = $db->fetchOne("SELECT * FROM products WHERE id = ? AND is_active = 1", [$productId]);
            if (!$product) {
                throw new Exception("Product ID $productId not found or inactive");
            }

            $purchaseSubtotal += ($purchaseCost * $qty);
            $saleSubtotal     += ($sellingPrice * $qty);

            $validatedItems[] = [
                'product'       => $product,
                'qty'           => $qty,
                'purchase_cost' => $purchaseCost,
                'selling_price' => $sellingPrice,
                'purchase_total'=> ($purchaseCost * $qty),
                'sale_total'    => ($sellingPrice * $qty)
            ];
        }

        // Supplier side calculations
        $supplierDiscountAmount = $purchaseSubtotal * ($discountPct / 100);
        $supplierVatAmount      = ($purchaseSubtotal - $supplierDiscountAmount) * ($vatPct / 100);
        $supplierTotalAmount    = $purchaseSubtotal - $supplierDiscountAmount + $supplierVatAmount;

        $supplierAmountPaid     = ($supplierPaymentMethod === 'credit') ? 0.00 : $supplierAmountPaid;
        $supplierAmountDue      = max(0.00, $supplierTotalAmount - $supplierAmountPaid);
        $supplierPaymentStatus  = 'unpaid';
        if ($supplierAmountPaid >= $supplierTotalAmount) {
            $supplierPaymentStatus = 'paid';
        } elseif ($supplierAmountPaid > 0) {
            $supplierPaymentStatus = 'partial';
        }

        // Customer side calculations
        $customerDiscountAmount = $saleSubtotal * ($discountPct / 100);
        $customerVatAmount      = ($saleSubtotal - $customerDiscountAmount) * ($vatPct / 100);
        $customerTotalAmount    = $saleSubtotal - $customerDiscountAmount + $customerVatAmount;

        $customerAmountPaid     = ($customerPaymentMethod === 'credit') ? 0.00 : $customerAmountPaid;
        $customerAmountDue      = max(0.00, $customerTotalAmount - $customerAmountPaid);
        $customerPaymentStatus  = 'unpaid';
        if ($customerAmountPaid >= $customerTotalAmount) {
            $customerPaymentStatus = 'paid';
        } elseif ($customerAmountPaid > 0) {
            $customerPaymentStatus = 'partial';
        }

        // 1. Generate Numbers
        $purchaseNumber = distributorGeneratePurchaseNumber($db);
        $saleNumber     = distributorGenerateSaleNumber($db);

        // 2. Insert Purchase Header
        $db->query("
            INSERT INTO purchases 
                (purchase_number, supplier_id, purchase_date, subtotal, 
                 discount_percent, discount_amount, vat_percent, vat_amount, 
                 total_amount, payment_status, payment_method, amount_paid, 
                 amount_due, invoice_number, notes, user_id)
            VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $purchaseNumber,
            $supplierId,
            $purchaseSubtotal,
            $discountPct,
            $supplierDiscountAmount,
            $vatPct,
            $supplierVatAmount,
            $supplierTotalAmount,
            $supplierPaymentStatus,
            $supplierPaymentMethod,
            $supplierAmountPaid,
            $supplierAmountDue,
            'DD-AUTO-' . date('YmdHis'),
            "[Distributor Direct Delivery for $saleNumber] " . $notes,
            $userId
        ]);
        $purchaseId = $db->lastInsertId();

        // 3. Insert Sale Header
        $db->query("
            INSERT INTO sales 
                (sale_number, customer_id, subtotal, discount_percent, 
                 discount_amount, tax_amount, total_amount, payment_status, 
                 payment_method, amount_paid, amount_due, notes, user_id, 
                 item_count, purchase_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $saleNumber,
            $customerId,
            $saleSubtotal,
            $discountPct,
            $customerDiscountAmount,
            $customerVatAmount,
            $customerTotalAmount,
            $customerPaymentStatus,
            $customerPaymentMethod,
            $customerAmountPaid,
            $customerAmountDue,
            "[Distributor Direct Delivery linked to $purchaseNumber] " . $notes,
            $userId,
            count($validatedItems),
            $purchaseId
        ]);
        $saleId = $db->lastInsertId();

        // Cross-link sale_id to the purchase record
        $db->query("UPDATE purchases SET sale_id = ? WHERE id = ?", [$saleId, $purchaseId]);

        // 4. Process items & stock movements (Virtual IN & OUT)
        foreach ($validatedItems as $vi) {
            $p = $vi['product'];
            $qty = $vi['qty'];

            // Save Purchase Item
            $db->query("
                INSERT INTO purchase_items 
                    (purchase_id, product_id, product_name, quantity, unit_cost, line_total)
                VALUES (?, ?, ?, ?, ?, ?)
            ", [
                $purchaseId,
                $p['id'],
                $p['name'],
                $qty,
                $vi['purchase_cost'],
                $vi['purchase_total']
            ]);

            // Save Sale Item
            $db->query("
                INSERT INTO sale_items 
                    (sale_id, product_id, product_name, quantity, unit_price, line_total)
                VALUES (?, ?, ?, ?, ?, ?)
            ", [
                $saleId,
                $p['id'],
                $p['name'],
                $qty,
                $vi['selling_price'],
                $vi['sale_total']
            ]);

            // Stock movements
            $stockBefore = floatval($p['current_stock']);
            
            // Movement IN from Purchase
            $db->query("
                INSERT INTO stock_movements 
                    (product_id, movement_type, quantity, reference_type, reference_id, previous_stock, new_stock, notes, user_id, branch_id)
                VALUES (?, 'in', ?, 'purchase', ?, ?, ?, ?, ?, ?)
            ", [
                $p['id'],
                $qty,
                $purchaseId,
                $stockBefore,
                $stockBefore + $qty,
                "Direct Delivery - Supplier purchase",
                $userId,
                activeBranchId()
            ]);

            // Movement OUT to Sale
            $db->query("
                INSERT INTO stock_movements 
                    (product_id, movement_type, quantity, reference_type, reference_id, previous_stock, new_stock, notes, user_id, branch_id)
                VALUES (?, 'out', ?, 'sale', ?, ?, ?, ?, ?, ?)
            ", [
                $p['id'],
                $qty,
                $saleId,
                $stockBefore + $qty,
                $stockBefore, // Back to starting stock level
                "Direct Delivery - Customer sale",
                $userId,
                activeBranchId()
            ]);

            // Since it's direct delivery, we do not modify current_stock in products table (net zero).
            // But we do update cost_price and last_purchase_cost / last_purchase_date
            $db->query("
                UPDATE products 
                SET cost_price = ?, last_purchase_cost = ?, last_purchase_date = NOW()
                WHERE id = ?
            ", [$vi['purchase_cost'], $vi['purchase_cost'], $p['id']]);
        }

        // 5. SUPPLIER FINANCIALS
        $supBalanceBefore = floatval($supplier['current_balance']);
        // Net effect on supplier: total amount increases what we owe them (which reduces supplier balance in negative balance logic)
        // If we pay them, that increases balance back towards zero.
        $netSupplierBalanceEffect = $supplierAmountPaid - $supplierTotalAmount;

        $db->query("
            INSERT INTO supplier_transactions 
                (supplier_id, transaction_type, amount, balance_before, balance_after, reference_type, reference_id, payment_method, notes, user_id)
            VALUES (?, 'purchase', ?, ?, ?, 'purchase', ?, ?, 'Direct Delivery Purchase', ?)
        ", [
            $supplierId,
            -$supplierTotalAmount,
            $supBalanceBefore,
            $supBalanceBefore - $supplierTotalAmount,
            $purchaseId,
            $supplierPaymentMethod,
            $userId
        ]);

        if ($supplierAmountPaid > 0) {
            $db->query("
                INSERT INTO supplier_transactions 
                    (supplier_id, transaction_type, amount, balance_before, balance_after, reference_type, reference_id, payment_method, notes, user_id)
                VALUES (?, 'payment', ?, ?, ?, 'purchase', ?, ?, 'Direct Delivery Supplier Payment', ?)
            ", [
                $supplierId,
                $supplierAmountPaid,
                $supBalanceBefore - $supplierTotalAmount,
                $supBalanceBefore - $supplierTotalAmount + $supplierAmountPaid,
                $purchaseId,
                $supplierPaymentMethod,
                $userId
            ]);

            // Record money outflow from financial account
            recordAccountTransaction(
                $db,
                $supplierPaymentMethod,
                $supplierAmountPaid,
                'withdrawal',
                'purchase',
                $purchaseId,
                "Supplier Payment for Direct Delivery $purchaseNumber",
                $supplierAccountId
            );
        }

        $db->query("
            UPDATE suppliers 
            SET current_balance = current_balance + ?, total_purchases = total_purchases + ?
            WHERE id = ?
        ", [$netSupplierBalanceEffect, $supplierTotalAmount, $supplierId]);


        // 6. CUSTOMER FINANCIALS
        $custBalanceBefore = floatval($customer['current_balance']);
        // Customer total decreases customer balance (they owe us more)
        // Customer payments increase balance back towards zero/positive.
        $netCustomerBalanceEffect = $customerAmountPaid - $customerTotalAmount;

        $db->query("
            INSERT INTO customer_transactions 
                (customer_id, transaction_type, amount, balance_before, balance_after, reference_type, reference_id, payment_method, notes, user_id)
            VALUES (?, 'sale', ?, ?, ?, 'sale', ?, ?, 'Direct Delivery Sale', ?)
        ", [
            $customerId,
            -$customerTotalAmount,
            $custBalanceBefore,
            $custBalanceBefore - $customerTotalAmount,
            $saleId,
            $customerPaymentMethod,
            $userId
        ]);

        if ($customerAmountPaid > 0) {
            $db->query("
                INSERT INTO customer_transactions 
                    (customer_id, transaction_type, amount, balance_before, balance_after, reference_type, reference_id, payment_method, notes, user_id)
                VALUES (?, 'payment', ?, ?, ?, 'sale', ?, ?, 'Direct Delivery Customer Payment', ?)
            ", [
                $customerId,
                $customerAmountPaid,
                $custBalanceBefore - $customerTotalAmount,
                $custBalanceBefore - $customerTotalAmount + $customerAmountPaid,
                $saleId,
                $customerPaymentMethod,
                $userId
            ]);

            // Record money inflow to financial account
            recordAccountTransaction(
                $db,
                $customerPaymentMethod,
                $customerAmountPaid,
                'deposit',
                'sale',
                $saleId,
                "Customer Payment for Direct Delivery $saleNumber",
                $customerAccountId
            );
        }

        $db->query("
            UPDATE customers 
            SET current_balance = current_balance + ?, total_purchases = total_purchases + ?
            WHERE id = ?
        ", [$netCustomerBalanceEffect, $customerTotalAmount, $customerId]);

        $db->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Direct Delivery transaction saved successfully!',
            'redirect' => BASE_URL . '/distributor/view/' . $saleId
        ]);

    } catch (Exception $e) {
        $db->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * View distributor direct delivery details
 */
function viewDistributorDelivery($db, $saleId)
{
    // Fetch Sale
    $sale = $db->fetchOne("
        SELECT s.*, c.full_name as customer_name, c.phone as customer_phone, c.address as customer_address, u.full_name as cashier_name
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.id = ? AND s.purchase_id IS NOT NULL
    ", [$saleId]);

    if (!$sale) {
        redirect(BASE_URL . '/distributor', 'error', 'Delivery transaction not found');
    }

    // Fetch corresponding Purchase
    $purchase = $db->fetchOne("
        SELECT p.*, s.company_name as supplier_name, s.phone as supplier_phone, s.address as supplier_address
        FROM purchases p
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        WHERE p.id = ?
    ", [$sale['purchase_id']]);

    // Fetch Items for Sale
    $saleItems = $db->fetchAll("
        SELECT si.*, p.sku
        FROM sale_items si
        LEFT JOIN products p ON si.product_id = p.id
        WHERE si.sale_id = ?
    ", [$saleId]);

    // Fetch Items for Purchase
    $purchaseItems = $db->fetchAll("
        SELECT pi.*, p.sku
        FROM purchase_items pi
        LEFT JOIN products p ON pi.product_id = p.id
        WHERE pi.purchase_id = ?
    ", [$sale['purchase_id']]);

    // Format item lists into a joined comparison
    $comparisonItems = [];
    foreach ($saleItems as $sItem) {
        $productId = $sItem['product_id'];
        $comparisonItems[$productId] = [
            'sku' => $sItem['sku'],
            'product_name' => $sItem['product_name'],
            'quantity' => $sItem['quantity'],
            'selling_price' => $sItem['unit_price'],
            'sale_total' => $sItem['line_total'],
            'purchase_cost' => 0,
            'purchase_total' => 0
        ];
    }
    foreach ($purchaseItems as $pItem) {
        $productId = $pItem['product_id'];
        if (isset($comparisonItems[$productId])) {
            $comparisonItems[$productId]['purchase_cost'] = $pItem['unit_cost'];
            $comparisonItems[$productId]['purchase_total'] = $pItem['line_total'];
        } else {
            $comparisonItems[$productId] = [
                'sku' => $pItem['sku'],
                'product_name' => $pItem['product_name'],
                'quantity' => $pItem['quantity'],
                'selling_price' => 0,
                'sale_total' => 0,
                'purchase_cost' => $pItem['unit_cost'],
                'purchase_total' => $pItem['line_total']
            ];
        }
    }

    $pageTitle = 'Distributor Delivery Detail - ' . $sale['sale_number'];
    include APP_PATH . '/views/distributor/view.php';
}

function distributorGeneratePurchaseNumber($db)
{
    $prefix = 'PUR-DD-' . date('Ymd') . '-';
    $last   = $db->fetchOne("
        SELECT purchase_number FROM purchases
        WHERE purchase_number LIKE ?
        ORDER BY id DESC LIMIT 1
    ", ["$prefix%"]);

    $num = $last ? intval(substr($last['purchase_number'], strrpos($last['purchase_number'], '-') + 1)) + 1 : 1;
    return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
}

function distributorGenerateSaleNumber($db)
{
    $prefix = 'SALE-DD-' . date('Ymd') . '-';
    $last   = $db->fetchOne("
        SELECT sale_number FROM sales
        WHERE sale_number LIKE ?
        ORDER BY id DESC LIMIT 1
    ", ["$prefix%"]);

    $num = $last ? intval(substr($last['sale_number'], strrpos($last['sale_number'], '-') + 1)) + 1 : 1;
    return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
}

