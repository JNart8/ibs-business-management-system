<?php

/**
 * Sale Controller
 * Handles POS, sales history, payments and receipts
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

if (($segments[0] ?? '') === 'pos') {
    $action = 'pos';
    $id     = $segments[1] ?? null;
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
        listSales($db);
        break;
    case 'pos':
        showPOS($db);
        break;
    case 'complete':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') completeSale($db);
        else redirect(BASE_URL . '/pos');
        break;
    case 'view':
        if (!$id) redirect(BASE_URL . '/sales', 'error', 'Sale ID required');
        viewSale($db, $id);
        break;
    case 'edit':
        if (!$id) redirect(BASE_URL . '/sales', 'error', 'Sale ID required');
        $_SERVER['REQUEST_METHOD'] === 'POST'
            ? updateSale($db, $id)
            : showEditForm($db, $id);
        break;
    case 'receipt':
        if (!$id) redirect(BASE_URL . '/sales', 'error', 'Sale ID required');
        printReceipt($db, $id);
        break;
    case 'pay':
        if (!$id) redirect(BASE_URL . '/sales', 'error', 'Sale ID required');
        $_SERVER['REQUEST_METHOD'] === 'POST'
            ? processPayment($db, $id)
            : showPaymentForm($db, $id);
        break;
    case 'void':
        if (!$id) redirect(BASE_URL . '/sales', 'error', 'Sale ID required');
        voidSale($db, $id);
        break;
    default:
        http_response_code(404);
        echo "<h1>Action not found</h1><a href='" . BASE_URL . "/sales'>← Back</a>";
        break;
}

// ============================================================
// FUNCTIONS
// ============================================================

/**
 * Show POS Interface
 */
function showPOS($db)
{
    // Load settings for receipt + POS behavior
    $settings = $db->fetchOne("SELECT * FROM settings LIMIT 1");

    // Load walk-in customer as default, unless this install has opted out
    // via the "Default POS customer to Walk-in" setting.
    $walkIn = ($settings['pos_default_walkin'] ?? 1)
        ? $db->fetchOne("SELECT * FROM customers WHERE is_default = 1 LIMIT 1")
        : null;

    // Recent products for quick access (last 12 most sold)
    $quickProducts = $db->fetchAll("
    SELECT p.id, p.name, p.sku, p.selling_price, p.current_stock, p.unit
    FROM products p
    WHERE p.is_active = 1 AND p.current_stock > 0
    ORDER BY p.id DESC
    LIMIT 12
    ");


    // Most-sold products (top 12 by sales volume)
    // $quickProducts = $db->fetchAll("
    //     SELECT p.id, p.name, p.sku, p.selling_price, p.current_stock, p.unit,
    //            COALESCE(SUM(si.quantity), 0) AS total_sold
    //     FROM products p
    //     LEFT JOIN sale_items si ON si.product_id = p.id
    //     WHERE p.is_active = 1 AND p.current_stock > 0
    //     GROUP BY p.id
    //     ORDER BY total_sold DESC
    //     LIMIT 12
    // ");

    // Categories for filter tabs
    $categories = $db->fetchAll(
        "SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC"
    );

    // Financial accounts for the payment account selector
    $financialAccounts = $db->fetchAll(
        "SELECT id, name, type, provider, balance FROM accounts WHERE is_active = 1 ORDER BY type ASC, name ASC"
    );

    $pageTitle = 'Point of Sale';
    include APP_PATH . '/views/sales/pos.php';
}

/**
 * Complete a sale — the core transaction
 */
function completeSale($db)
{
    header('Content-Type: application/json');

    // Get JSON body
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        return;
    }

    $customerId    = intval($input['customer_id']     ?? 0);
    $items         = $input['items']                  ?? [];
    $discountValue = max(0, floatval($input['discount_value'] ?? $input['discount_pct'] ?? 0));
    $paymentMethod = $input['payment_method']         ?? 'cash';
    $amountPaid    = floatval($input['amount_paid']   ?? 0);
    $notes         = trim($input['notes']             ?? '');
    $userId        = $_SESSION['user_id']             ?? null;
    $customDate    = trim($input['sale_date']         ?? '');
    $accountId     = intval($input['account_id']      ?? 0);  // Specific financial account

    // Validate
    if ($customerId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please select a customer']);
        return;
    }
    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => 'Cart is empty']);
        return;
    }

    $customer = $db->fetchOne("SELECT * FROM customers WHERE id = ? AND is_active = 1", [$customerId]);
    if (!$customer) {
        echo json_encode(['success' => false, 'message' => 'Customer not found']);
        return;
    }
    // Validate custom date (admin only)
    $saleDate = null;
    if (!empty($customDate)) {
        // Check if user has backdate permission
        if (!can('sales.backdate')) {
            echo json_encode(['success' => false, 'message' => 'You do not have permission to back-date sales']);
            return;
        }

        // Validate date format
        $dateObj = DateTime::createFromFormat('Y-m-d', $customDate);
        if (!$dateObj) {
            echo json_encode(['success' => false, 'message' => 'Invalid date format']);
            return;
        }

        // Don't allow future dates
        if ($dateObj > new DateTime()) {
            echo json_encode(['success' => false, 'message' => 'Cannot create sales in the future']);
            return;
        }

        // Set sale date (use noon to avoid timezone issues)
        $saleDate = $dateObj->format('Y-m-d') . ' 12:00:00';
    }

    // Prevent walk-in customers from buying on credit
    if ($paymentMethod === 'credit' && $customer['is_default'] == 1) {
        echo json_encode(['success' => false, 'message' => 'Walk-in customers cannot buy on credit. Please select a registered customer or use cash/mobile payment.']);
        return;
    }

    // Prevent walk-in customers from using deposit
    if ($paymentMethod === 'deposit' && $customer['is_default'] == 1) {
        echo json_encode(['success' => false, 'message' => 'Walk-in customers cannot pay from deposit.']);
        return;
    }

    // Prevent customers with deposit from buying on credit
    if ($paymentMethod === 'credit' && floatval($customer['current_balance']) > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Customer has a deposit balance of ' . formatMoney($customer['current_balance']) . '. Please use "Pay from Deposit" or another payment method instead of credit.'
        ]);
        return;
    }

    // Handle deposit payment validation and adjustment
    if ($paymentMethod === 'deposit') {
        $customerBalance = floatval($customer['current_balance']);

        // Check if customer has deposit
        if ($customerBalance <= 0) {
            echo json_encode(['success' => false, 'message' => 'Customer has no deposit balance']);
            return;
        }

        // Deposit payment is capped at available balance (allow partial)
        $amountPaid = min($amountPaid, $customerBalance);
    }

    // The configured mode is authoritative for both line and sale discounts.
    $saleSettings = $db->fetchOne("SELECT sale_discount_type FROM settings ORDER BY id ASC LIMIT 1") ?: [];
    $discountType = ($saleSettings['sale_discount_type'] ?? 'percentage') === 'flat' ? 'flat' : 'percentage';

    // Validate all items and calculate totals
    $validatedItems = [];
    $subtotal       = 0;
    $itemsData      = []; // Store for response

    foreach ($items as $item) {
        $productId = intval($item['product_id'] ?? 0);
        $qty       = floatval($item['quantity']   ?? 0);
        $unitPrice = floatval($item['price']    ?? 0);
        $itemDisc  = floatval($item['discount'] ?? 0);

        if ($productId <= 0 || $qty <= 0) continue;

        $product = $db->fetchOne(
            "SELECT * FROM products WHERE id = ? AND is_active = 1",
            [$productId]
        );
        if (!$product) {
            echo json_encode(['success' => false, 'message' => "Product ID $productId not found"]);
            return;
        }
        if ($product['current_stock'] < $qty) {
            echo json_encode([
                'success' => false,
                'message' => "Not enough stock for {$product['name']}. Available: {$product['current_stock']} {$product['unit']}"
            ]);
            return;
        }

        $lineSubtotal = $unitPrice * $qty;
        if ($discountType === 'percentage' && $itemDisc > 100) {
            echo json_encode(['success' => false, 'message' => "Discount for {$product['name']} cannot exceed 100%"]);
            return;
        }
        $itemDiscountAmount = $discountType === 'flat'
            ? min($lineSubtotal, max(0, $itemDisc))
            : $lineSubtotal * (max(0, $itemDisc) / 100);
        $itemDiscountPct = $discountType === 'percentage' ? max(0, $itemDisc) : 0;
        $lineTotal = $lineSubtotal - $itemDiscountAmount;
        $subtotal        += $lineTotal;
        $validatedItems[] = [
            'product'   => $product,
            'qty'       => $qty,
            'unitPrice' => $unitPrice,
            'discount'  => $itemDiscountPct,
            'discountType' => $discountType,
            'discountAmount' => $itemDiscountAmount,
            'lineTotal' => $lineTotal,
        ];

        // Store for response
        $itemsData[] = [
            'product_id'       => $product['id'],
            'product_name'     => $product['name'],
            'sku'              => $product['sku'],
            'quantity'         => $qty,
            'unit_price'       => $unitPrice,
            'discount_type'    => $discountType,
            'discount_percent' => $itemDiscountPct,
            'discount_amount'  => $itemDiscountAmount,
            'line_total'       => $lineTotal,
        ];
    }

    if (empty($validatedItems)) {
        echo json_encode(['success' => false, 'message' => 'No valid items in cart']);
        return;
    }

    if ($discountType === 'percentage' && $discountValue > 100) {
        echo json_encode(['success' => false, 'message' => 'Percentage discount cannot exceed 100%']);
        return;
    }

    $discountAmount = $discountType === 'flat'
        ? min($subtotal, $discountValue)
        : $subtotal * ($discountValue / 100);
    $discountPct = $discountType === 'percentage' ? $discountValue : 0;
    $totalAmount    = $subtotal - $discountAmount;

    // Determine payment status
    $amountDue = max(0, $totalAmount - $amountPaid);
    $change = max(0, $amountPaid - $totalAmount);

    if ($amountPaid <= 0 && $paymentMethod === 'credit') {
        $amountDue   = $totalAmount;
        $amountPaid  = 0;
        $paymentStatus = 'unpaid';
    } elseif ($amountDue <= 0.01) { // Float precision
        $paymentStatus = 'paid';
        $amountPaid    = $totalAmount; // Don't overpay in DB
        $amountDue     = 0;
    } else {
        $paymentStatus = 'partial';
    }

    // Credit check for credit/partial sales
    if ($amountDue > 0) {
        $currentBalance  = floatval($customer['current_balance']);
        $creditLimit     = floatval($customer['credit_limit']);
        $availableCredit = $creditLimit + $currentBalance; // balance can be positive (has deposit)

        if ($availableCredit < $amountDue && !$customer['is_default']) {
            echo json_encode([
                'success' => false,
                'message' => "Insufficient credit. Available: " . formatMoney($availableCredit) . ", Required: " . formatMoney($amountDue)
            ]);
            return;
        }
    }

    // Generate sale number
    $saleNumber = generateSaleNumber($db);

    try {
        $db->beginTransaction();

        // 1. Insert sale header
        if (!empty($saleDate)) {
            // BACKDATED SALE
            $db->query("
        INSERT INTO sales
            (sale_number, customer_id, subtotal, discount_type, discount_percent,
             discount_amount, total_amount, payment_status,
             payment_method, amount_paid, amount_due, notes, user_id, 
             sale_date, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ", [
                $saleNumber,
                $customerId,
                $subtotal,
                $discountType,
                $discountPct,
                $discountAmount,
                $totalAmount,
                $paymentStatus,
                $paymentMethod,
                $amountPaid,
                $amountDue,
                $notes ?: null,
                $userId,
                $saleDate,
                $saleDate,
                $saleDate
            ]);
        } else {
            // NORMAL SALE
            $db->query("
        INSERT INTO sales
            (sale_number, customer_id, subtotal, discount_type, discount_percent,
             discount_amount, total_amount, payment_status,
             payment_method, amount_paid, amount_due, notes, user_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ", [
                $saleNumber,
                $customerId,
                $subtotal,
                $discountType,
                $discountPct,
                $discountAmount,
                $totalAmount,
                $paymentStatus,
                $paymentMethod,
                $amountPaid,
                $amountDue,
                $notes ?: null,
                $userId
            ]);
        }

        $saleId = $db->lastInsertId();

        // 2. Insert sale items + update stock
        foreach ($validatedItems as $vi) {
            $p = $vi['product'];

            // Insert line item
            $db->query("
                INSERT INTO sale_items
                    (sale_id, product_id, product_name, quantity,
                     unit_price, discount_type, discount_percent, discount_amount, line_total)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $saleId,
                $p['id'],
                $p['name'],
                $vi['qty'],
                $vi['unitPrice'],
                $vi['discountType'],
                $vi['discount'],
                $vi['discountAmount'],
                $vi['lineTotal']
            ]);

            $prevStock = floatval($p['current_stock']);
            $newStock  = $prevStock - $vi['qty'];

            // Update stock
            $db->query(
                "UPDATE products SET current_stock = ? WHERE id = ?",
                [$newStock, $p['id']]
            );

            // Log movement
            if (!empty($saleDate)) {
                $db->query("
        INSERT INTO stock_movements
            (product_id, movement_type, quantity, reference_type,
             reference_id, previous_stock, new_stock, user_id, created_at)
        VALUES (?, 'out', ?, 'sale', ?, ?, ?, ?, ?)", [$p['id'], $vi['qty'], $saleId, $prevStock, $newStock, $userId, $saleDate]);
            } else {
                $db->query("
        INSERT INTO stock_movements
            (product_id, movement_type, quantity, reference_type,
             reference_id, previous_stock, new_stock, user_id)
        VALUES (?, 'out', ?, 'sale', ?, ?, ?, ?)", [$p['id'], $vi['qty'], $saleId, $prevStock, $newStock, $userId]);
            }
        }

        // 3. Update customer balance & log transaction
        $balanceBefore = floatval($customer['current_balance']);

        // ============================================================
        // DEPOSIT PAYMENT: Just deduct from existing balance
        // ============================================================
        if ($paymentMethod === 'deposit') {
            // Record the sale transaction (deducts from deposit)
            if (!empty($saleDate)) {
                $db->query("
        INSERT INTO customer_transactions
            (customer_id, transaction_type, amount, balance_before,
             balance_after, reference_type, reference_id, payment_method, user_id, created_at)
        VALUES (?, 'sale', ?, ?, ?, 'sale', ?, 'deposit', ?, ?)", [
                    $customerId,
                    -$totalAmount,
                    $balanceBefore,
                    $balanceBefore - $totalAmount,
                    $saleId,
                    $userId,
                    $saleDate
                ]);
            } else {
                $db->query("
        INSERT INTO customer_transactions
            (customer_id, transaction_type, amount, balance_before,
             balance_after, reference_type, reference_id, payment_method, user_id)
        VALUES (?, 'sale', ?, ?, ?, 'sale', ?, 'deposit', ?)", [
                    $customerId,
                    -$totalAmount,
                    $balanceBefore,
                    $balanceBefore - $totalAmount,
                    $saleId,
                    $userId
                ]);
            }

            // Update customer balance (reduce by total)
            $db->query(
                "
                UPDATE customers 
                SET current_balance = current_balance - ?,
                    total_purchases = total_purchases + ?
                WHERE id = ?",
                [$totalAmount, $totalAmount, $customerId]
            );
        }
        // ============================================================
        // OTHER PAYMENT METHODS: Record sale + payment separately
        // ============================================================
        elseif ($amountPaid > 0) {
            // Record sale (debit)
            if (!empty($saleDate)) {
                $db->query("
                INSERT INTO customer_transactions
                    (customer_id, transaction_type, amount, balance_before,
                     balance_after, reference_type, reference_id, payment_method, user_id, created_at)
                VALUES (?, 'sale', ?, ?, ?, 'sale', ?, ?, ?, ?)", [
                    $customerId,
                    -$totalAmount,
                    $balanceBefore,
                    $balanceBefore - $totalAmount,
                    $saleId,
                    $paymentMethod,
                    $userId,
                    $saleDate
                ]);
            } else {
                $db->query("
                INSERT INTO customer_transactions
                    (customer_id, transaction_type, amount, balance_before,
                     balance_after, reference_type, reference_id, payment_method, user_id)
                VALUES (?, 'sale', ?, ?, ?, 'sale', ?, ?, ?)
            ", [
                    $customerId,
                    -$totalAmount,
                    $balanceBefore,
                    $balanceBefore - $totalAmount,
                    $saleId,
                    $paymentMethod,
                    $userId
                ]);
            }
            // Record payment (credit)
            if (!empty($saleDate)) {
                $db->query("
                INSERT INTO customer_transactions
                    (customer_id, transaction_type, amount, balance_before,
                     balance_after, reference_type, reference_id, payment_method, user_id, created_at)
                VALUES (?, 'payment', ?, ?, ?, 'sale', ?, ?, ?, ?)", [
                    $customerId,
                    $amountPaid,
                    $balanceBefore - $totalAmount,
                    $balanceBefore - $totalAmount + $amountPaid,
                    $saleId,
                    $paymentMethod,
                    $userId,
                    $saleDate
                ]);
            } else {
                $db->query("
                INSERT INTO customer_transactions
                    (customer_id, transaction_type, amount, balance_before,
                     balance_after, reference_type, reference_id, payment_method, user_id)
                VALUES (?, 'payment', ?, ?, ?, 'sale', ?, ?, ?)
            ", [
                    $customerId,
                    $amountPaid,
                    $balanceBefore - $totalAmount,
                    $balanceBefore - $totalAmount + $amountPaid,
                    $saleId,
                    $paymentMethod,
                    $userId
                ]);
            }
            // Update customer balance (net effect)
            $netEffect = $amountPaid - $totalAmount;
            $db->query("
                UPDATE customers
                SET current_balance = current_balance + ?,
                    total_purchases = total_purchases + ?
                WHERE id = ?
            ", [$netEffect, $totalAmount, $customerId]);
        }
        // ============================================================
        // CREDIT WITH NO PAYMENT: Just record the sale (customer owes)
        // ============================================================
        else {
            // Record sale only (customer now owes)
            if (!empty($saleDate)) {
                $db->query("
                INSERT INTO customer_transactions
                    (customer_id, transaction_type, amount, balance_before,
                     balance_after, reference_type, reference_id, payment_method, user_id, created_at)
                VALUES (?, 'sale', ?, ?, ?, 'sale', ?, 'credit', ?, ?)", [
                    $customerId,
                    -$totalAmount,
                    $balanceBefore,
                    $balanceBefore - $totalAmount,
                    $saleId,
                    $userId,
                    $saleDate
                ]);
            } else {
                $db->query("
                INSERT INTO customer_transactions
                    (customer_id, transaction_type, amount, balance_before,
                     balance_after, reference_type, reference_id, payment_method, user_id)
                VALUES (?, 'sale', ?, ?, ?, 'sale', ?, 'credit', ?)
            ", [
                    $customerId,
                    -$totalAmount,
                    $balanceBefore,
                    $balanceBefore - $totalAmount,
                    $saleId,
                    $userId
                ]);
            }

            // Update customer balance (negative = owes)
            $db->query("
                UPDATE customers
                SET current_balance = current_balance - ?,
                    total_purchases = total_purchases + ?
                WHERE id = ?
            ", [$totalAmount, $totalAmount, $customerId]);
        }

        // Record deposit to financial account
        // For credit/deposit payment methods, no account transaction is needed here
        if ($amountPaid > 0 && !in_array($paymentMethod, ['credit', 'deposit'])) {
            // Validate the selected account belongs to the right type for this payment method
            $resolvedAccountId = null;
            if ($accountId > 0) {
                $typeMap   = ['cash' => 'cash', 'mobile' => 'mobile_money', 'bank' => 'bank'];
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
                'deposit',
                'sale',
                $saleId,
                "Revenue from Sale #" . $saleNumber,
                $resolvedAccountId
            );
        } elseif ($amountPaid > 0 && $paymentMethod === 'deposit') {
            // Deposit payment: money was already in the system — no account inflow needed
            // (customer ledger deduction is handled above)
        }

        $db->commit();

        // Get cashier name
        $cashier = $db->fetchOne("SELECT full_name FROM users WHERE id = ?", [$userId]);

        // Get customer name
        $customerName = $customer['full_name'] ?? 'Walk-in Customer';

        // Format sale date
        $saleDate = date('Y-m-d H:i:s');

        // Fetch updated financial accounts to update POS state
        $financialAccounts = $db->fetchAll(
            "SELECT id, name, type, provider, balance FROM accounts WHERE is_active = 1 ORDER BY type ASC, name ASC"
        );

        // Fetch updated customer details to update POS state
        $updatedCustomer = $db->fetchOne(
            "SELECT id, customer_code, full_name, phone, current_balance, credit_limit, is_default FROM customers WHERE id = ?",
            [$customerId]
        );

        echo json_encode([
            'success'        => true,
            'sale_id'        => $saleId,
            'sale_number'    => $saleNumber,
            'subtotal'       => $subtotal,
            'total_amount'   => $totalAmount,
            'discount_amount' => $discountAmount,
            'discount_percent' => $discountPct,
            'discount_type'   => $discountType,
            'amount_paid'    => $amountPaid,
            'amount_due'     => $amountDue,
            'change'         => $change,
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'customer_name'  => $customerName,
            'cashier_name'   => $cashier ? $cashier['full_name'] : 'Staff',
            'sale_date'      => $saleDate,
            'items'          => $itemsData,
            'receipt_url'    => BASE_URL . '/sales/receipt/' . $saleId,
            'financial_accounts' => $financialAccounts,
            'customer'       => $updatedCustomer,
            'message'        => 'Sale completed successfully' . (!empty($customDate) ? ' (Back-dated to ' . date('d M Y', strtotime($customDate)) . ')' : '')
        ]);
    } catch (Exception $e) {
        $db->rollback();
        error_log('Sale error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to complete sale. Please try again.']);
    }
}

/**
 * List all sales - WITH DATE RANGE FILTERING
 */
function listSales($db)
{
    $search   = trim($_GET['search'] ?? '');
    $status   = $_GET['status']      ?? '';
    $dateFrom = $_GET['date_from']   ?? '';
    $dateTo   = $_GET['date_to']     ?? '';
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $limit    = ITEMS_PER_PAGE;
    $offset   = ($page - 1) * $limit;

    $params = [];
    $where  = "WHERE 1=1";

    // Search filter
    if (!empty($search)) {
        $where   .= " AND (s.sale_number LIKE ? OR c.full_name LIKE ? OR c.phone LIKE ?)";
        $t        = "%$search%";
        $params[] = $t;
        $params[] = $t;
        $params[] = $t;
    }

    // Status filter
    if (!empty($status) && in_array($status, ['paid', 'partial', 'unpaid'])) {
        $where .= " AND s.payment_status = ?";
        $params[] = $status;
    }

    // Date range filter - NEW LOGIC
    if (!empty($dateFrom) && !empty($dateTo)) {
        // Both dates provided - range
        $where .= " AND DATE(s.sale_date) BETWEEN ? AND ?";
        $params[] = $dateFrom;
        $params[] = $dateTo;
    } elseif (!empty($dateFrom)) {
        // Only from date - from that date forward
        $where .= " AND DATE(s.sale_date) >= ?";
        $params[] = $dateFrom;
    } elseif (!empty($dateTo)) {
        // Only to date - up to that date
        $where .= " AND DATE(s.sale_date) <= ?";
        $params[] = $dateTo;
    }

    // Count total
    $total      = $db->fetchOne("
        SELECT COUNT(*) AS cnt FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        $where
    ", $params);
    $totalCount = intval($total['cnt'] ?? 0);
    $totalPages = max(1, ceil($totalCount / $limit));

    // Fetch sales
    $sales = $db->fetchAll("
        SELECT
            s.*,
            c.full_name  AS customer_name,
            c.phone      AS customer_phone,
            c.is_default AS is_walkin,
            u.full_name  AS cashier_name
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN users u     ON s.user_id     = u.id
        $where
        ORDER BY s.sale_date DESC
        LIMIT ? OFFSET ?
    ", array_merge($params, [$limit, $offset]));

    // Daily summary (always today, not affected by filters)
    $todayStats = $db->fetchOne("
        SELECT
            COALESCE(SUM(total_amount), 0) AS total_sales,
            COALESCE(SUM(
                CASE WHEN payment_method = 'cash' THEN amount_paid ELSE 0 END
            ), 0) AS cash_sales,
            COALESCE(SUM(
                CASE WHEN payment_method = 'mobile' THEN amount_paid ELSE 0 END
            ), 0) AS momo_sales,
            COALESCE(SUM(amount_due), 0) AS outstanding
        FROM sales
        WHERE DATE(sale_date) = CURDATE()
          AND (notes IS NULL OR notes NOT LIKE '%[VOIDED]%')
    ");

    $pageTitle = 'Sales History';
    include APP_PATH . '/views/sales/index.php';
}

/**
 * View single sale
 */
function viewSale($db, $id)
{
    $sale = $db->fetchOne("
        SELECT s.*, c.full_name AS customer_name, c.phone AS customer_phone,
               c.customer_code, u.full_name AS cashier_name
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN users u     ON s.user_id     = u.id
        WHERE s.id = ?
    ", [$id]);

    if (!$sale) {
        redirect(BASE_URL . '/sales', 'error', 'Sale not found');
        return;
    }

    $items = $db->fetchAll("
        SELECT si.*, p.sku, p.unit
        FROM sale_items si
        LEFT JOIN products p ON si.product_id = p.id
        WHERE si.sale_id = ?
    ", [$id]);

    // Payment history for this sale
    $payments = $db->fetchAll("
        SELECT ct.*, u.full_name AS user_name
        FROM customer_transactions ct
        LEFT JOIN users u ON ct.user_id = u.id
        WHERE ct.reference_type = 'sale'
          AND ct.reference_id   = ?
          AND ct.transaction_type IN ('payment','deposit')
        ORDER BY ct.created_at ASC
    ", [$id]);

    $pageTitle = 'Sale #' . $sale['sale_number'];
    include APP_PATH . '/views/sales/view.php';
}


/**
 * Show edit sale form
 */
function showEditForm($db, $id)
{
    // Check permissions - requires sales.edit permission
    if (!can('sales.edit')) {
        redirect(BASE_URL . '/sales', 'error', 'You do not have permission to edit sales');
        return;
    }

    // Get sale details
    $sale = $db->fetchOne("
        SELECT 
            s.*,
            c.full_name as customer_name,
            c.customer_code,
            c.current_balance as customer_balance,
            c.is_default as is_walkin,
            u.full_name as cashier_name
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.id = ?
    ", [$id]);

    if (!$sale) {
        redirect(BASE_URL . '/sales', 'error', 'Sale not found');
        return;
    }

    // Get sale items
    $items = $db->fetchAll("
        SELECT * FROM sale_items 
        WHERE sale_id = ? 
        ORDER BY id ASC
    ", [$id]);

    // Get edit history
    $editHistory = $db->fetchAll("
        SELECT 
            ah.*,
            u.full_name as editor_name
        FROM audit_history ah
        LEFT JOIN users u ON ah.user_id = u.id
        WHERE ah.table_name = 'sales' 
          AND ah.record_id = ?
        ORDER BY ah.created_at DESC
        LIMIT 10
    ", [$id]);

    $pageTitle = 'Edit Sale: ' . $sale['sale_number'];
    include APP_PATH . '/views/sales/edit.php';
}

/**
 * Update sale
 */
function updateSale($db, $id)
{
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect(BASE_URL . '/sales/edit/' . $id, 'error', 'Invalid form submission');
        return;
    }

    // Check permissions - requires sales.edit permission
    if (!can('sales.edit')) {
        redirect(BASE_URL . '/sales', 'error', 'You do not have permission to edit sales');
        return;
    }

    // Get current sale
    $sale = $db->fetchOne("
        SELECT 
            s.*,
            c.current_balance as customer_balance
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        WHERE s.id = ?
    ", [$id]);

    if (!$sale) {
        redirect(BASE_URL . '/sales', 'error', 'Sale not found');
        return;
    }

    // Get form data
    $paymentMethod  = trim($_POST['payment_method'] ?? '');
    $amountPaid     = floatval($_POST['amount_paid'] ?? 0);
    $notes          = trim($_POST['notes'] ?? '');
    $editReason     = trim($_POST['edit_reason'] ?? '');
    $newSaleDate    = trim($_POST['sale_date'] ?? '');

    // Validate and parse the new sale date (optional)
    $saleDateSql = null;
    if (!empty($newSaleDate)) {
        $dateObj = DateTime::createFromFormat('Y-m-d', $newSaleDate);
        if (!$dateObj) {
            redirect(BASE_URL . '/sales/edit/' . $id, 'error', 'Invalid sale date format');
            return;
        }
        if ($dateObj > new DateTime('today')) {
            redirect(BASE_URL . '/sales/edit/' . $id, 'error', 'Sale date cannot be in the future');
            return;
        }
        $saleDateSql = $dateObj->format('Y-m-d') . ' 12:00:00';
    }

    // Validate
    $errors = [];

    if (!in_array($paymentMethod, ['cash', 'mobile', 'bank', 'credit', 'deposit'])) {
        $errors[] = 'Invalid payment method';
    }

    if ($amountPaid < 0) {
        $errors[] = 'Amount paid cannot be negative';
    }

    if ($amountPaid > $sale['total_amount']) {
        $errors[] = 'Amount paid cannot exceed total amount';
    }

    if (empty($editReason)) {
        $errors[] = 'Please provide a reason for editing this sale';
    }

    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        $_SESSION['old_input'] = $_POST;
        redirect(BASE_URL . '/sales/edit/' . $id, 'error', implode(' | ', $errors));
        return;
    }

    // Calculate new values
    $totalAmount    = floatval($sale['total_amount']);
    $oldAmountPaid  = floatval($sale['amount_paid']);
    $oldAmountDue   = floatval($sale['amount_due']);
    $oldPaymentMethod = $sale['payment_method'];
    $oldPaymentStatus = $sale['payment_status'];

    $newAmountDue   = $totalAmount - $amountPaid;

    // Determine new payment status
    if ($amountPaid <= 0) {
        $newPaymentStatus = 'unpaid';
    } elseif ($newAmountDue <= 0.01) {
        $newPaymentStatus = 'paid';
        $amountPaid = $totalAmount; // Ensure exact match
        $newAmountDue = 0;
    } else {
        $newPaymentStatus = 'partial';
    }

    // Normalize old sale date for comparison
    $oldSaleDateStr = date('Y-m-d', strtotime($sale['sale_date']));
    $newSaleDateStr = $newSaleDate ?: $oldSaleDateStr;

    // Check if anything actually changed
    if (
        $amountPaid == $oldAmountPaid &&
        $paymentMethod == $oldPaymentMethod &&
        $notes == $sale['notes'] &&
        $newSaleDateStr == $oldSaleDateStr
    ) {
        redirect(BASE_URL . '/sales/view/' . $id, 'warning', 'No changes made');
        return;
    }

    $userId = $_SESSION['user_id'] ?? null;
    $customerId = $sale['customer_id'];
    $paymentDifference = $amountPaid - $oldAmountPaid;

    try {
        $db->beginTransaction();

        // Log what changed (for audit)
        $changes = [];
        if ($amountPaid != $oldAmountPaid) {
            $changes[] = "Amount paid: " . formatMoney($oldAmountPaid) . " → " . formatMoney($amountPaid);
        }
        if ($paymentMethod != $oldPaymentMethod) {
            $changes[] = "Payment method: $oldPaymentMethod → $paymentMethod";
        }
        if ($notes != $sale['notes']) {
            $changes[] = "Notes updated";
        }
        if ($newSaleDateStr != $oldSaleDateStr) {
            $changes[] = "Sale date: " . date('d M Y', strtotime($oldSaleDateStr)) . " → " . date('d M Y', strtotime($newSaleDateStr));
        }
        $changeLog = implode('; ', $changes);

        // Update sale record
        if ($saleDateSql) {
            // Include sale_date in update
            $db->query("
                UPDATE sales 
                SET 
                    payment_method = ?,
                    amount_paid = ?,
                    amount_due = ?,
                    payment_status = ?,
                    notes = ?,
                    sale_date = ?,
                    updated_at = NOW()
                WHERE id = ?
            ", [
                $paymentMethod,
                $amountPaid,
                $newAmountDue,
                $newPaymentStatus,
                $notes ?: null,
                $saleDateSql,
                $id
            ]);
        } else {
            $db->query("
                UPDATE sales 
                SET 
                    payment_method = ?,
                    amount_paid = ?,
                    amount_due = ?,
                    payment_status = ?,
                    notes = ?,
                    updated_at = NOW()
                WHERE id = ?
            ", [
                $paymentMethod,
                $amountPaid,
                $newAmountDue,
                $newPaymentStatus,
                $notes ?: null,
                $id
            ]);
        }

        // Update customer balance if payment changed
        if ($paymentDifference != 0) {
            // Get customer's current balance
            $customerBalanceBefore = floatval($sale['customer_balance']);

            // Adjust customer balance
            // If payment increased: customer balance increases (they paid more)
            // If payment decreased: customer balance decreases (they owe more)
            $db->query(
                "UPDATE customers SET current_balance = current_balance + ? WHERE id = ?",
                [$paymentDifference, $customerId]
            );

            $customerBalanceAfter = $customerBalanceBefore + $paymentDifference;

            // Log customer transaction for the adjustment
            $txnNotes = "Sale payment adjusted: $changeLog. Reason: $editReason";

            $db->query("
                INSERT INTO customer_transactions
                    (customer_id, transaction_type, amount, balance_before,
                     balance_after, reference_type, reference_id, payment_method, notes, user_id)
                VALUES (?, 'adjustment', ?, ?, ?, 'sale', ?, ?, ?, ?)
            ", [
                $customerId,
                $paymentDifference,
                $customerBalanceBefore,
                $customerBalanceAfter,
                $id,
                $paymentMethod,
                $txnNotes,
                $userId
            ]);

            // Adjust financial account balance
            if ($paymentDifference > 0) {
                recordAccountTransaction(
                    $db,
                    $paymentMethod,
                    $paymentDifference,
                    'deposit',
                    'sale',
                    $id,
                    "Adjustment deposit for Sale #" . $sale['sale_number'] . " (Reason: " . $editReason . ")"
                );
            } else {
                recordAccountTransaction(
                    $db,
                    $paymentMethod,
                    abs($paymentDifference),
                    'withdrawal',
                    'sale',
                    $id,
                    "Adjustment withdrawal for Sale #" . $sale['sale_number'] . " (Reason: " . $editReason . ")"
                );
            }
        }

        // Log in audit history (optional, if you have this table)
        try {
            $db->query("
                INSERT INTO audit_history
                    (table_name, record_id, action, changes, reason, user_id)
                VALUES ('sales', ?, 'update', ?, ?, ?)
            ", [$id, $changeLog, $editReason, $userId]);
        } catch (Exception $e) {
            // Table might not exist, that's okay
        }

        $db->commit();

        redirect(
            BASE_URL . '/sales/view/' . $id,
            'success',
            'Sale updated successfully. ' . $changeLog
        );
    } catch (Exception $e) {
        $db->rollback();
        error_log('Update sale error: ' . $e->getMessage());
        redirect(BASE_URL . '/sales/edit/' . $id, 'error', 'Failed to update sale');
    }
}


/**
 * Print-friendly receipt
 */
function printReceipt($db, $id)
{
    $sale = $db->fetchOne("
        SELECT s.*, c.full_name AS customer_name, c.phone AS customer_phone,
               c.customer_code, u.full_name AS cashier_name
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN users u     ON s.user_id     = u.id
        WHERE s.id = ?
    ", [$id]);

    if (!$sale) {
        redirect(BASE_URL . '/sales', 'error', 'Sale not found');
        return;
    }

    $items    = $db->fetchAll("SELECT * FROM sale_items WHERE sale_id = ?", [$id]);
    $settings = $db->fetchOne("SELECT * FROM settings LIMIT 1");

    $pageTitle = 'Receipt - ' . $sale['sale_number'];
    include APP_PATH . '/views/sales/receipt.php';
}

/**
 * Show payment form for outstanding balance
 */
function showPaymentForm($db, $id)
{
    $sale = $db->fetchOne("
        SELECT s.*, c.full_name AS customer_name, c.current_balance, c.is_default
        FROM sales s LEFT JOIN customers c ON s.customer_id = c.id
        WHERE s.id = ? AND s.payment_status != 'paid'
    ", [$id]);

    if (!$sale) {
        redirect(BASE_URL . '/sales', 'error', 'Sale not found or already paid');
        return;
    }

    $pageTitle = 'Record Payment';
    include APP_PATH . '/views/sales/pay.php';
}
/**
 * Process payment on outstanding sale - WITH DEPOSIT SUPPORT
 */
function processPayment($db, $id)
{
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect(BASE_URL . '/sales/pay/' . $id, 'error', 'Invalid form submission');
        return;
    }

    $sale = $db->fetchOne("
        SELECT s.*, c.current_balance, c.is_default 
        FROM sales s 
        LEFT JOIN customers c ON s.customer_id = c.id
        WHERE s.id = ?
    ", [$id]);

    if (!$sale || $sale['payment_status'] === 'paid') {
        redirect(BASE_URL . '/sales', 'error', 'Sale not found or already paid');
        return;
    }

    $amount        = floatval($_POST['amount']         ?? 0);
    $paymentMethod = trim($_POST['payment_method']     ?? 'cash');
    $userId        = $_SESSION['user_id']              ?? null;

    if ($amount <= 0) {
        redirect(BASE_URL . '/sales/pay/' . $id, 'error', 'Amount must be greater than zero');
        return;
    }

    // If payment method is "deposit", check and deduct from customer balance
    if ($paymentMethod === 'deposit') {
        // Walk-in customers cannot use deposit
        if ($sale['is_default'] == 1) {
            redirect(BASE_URL . '/sales/pay/' . $id, 'error', 'Walk-in customers cannot pay from deposit');
            return;
        }

        $customerBalance = floatval($sale['current_balance']);

        // Allow partial payment if insufficient deposit
        if ($customerBalance <= 0) {
            redirect(BASE_URL . '/sales/pay/' . $id, 'error', 'Customer has no deposit balance');
            return;
        }

        // Adjust payment amount if insufficient (partial payment)
        $amountDue = floatval($sale['amount_due']);
        $availableDeposit = $customerBalance;
        $payment = min($amount, $amountDue, $availableDeposit);

        if ($payment < $amount) {
            $_SESSION['flash_warning'] = "Only " . formatMoney($payment) . " available in deposit. Partial payment applied.";
        }

        $amount = $payment; // Use the adjusted amount
    }

    $amountDue = floatval($sale['amount_due']);
    $payment   = min($amount, $amountDue); // Can't overpay

    try {
        $db->beginTransaction();

        $newAmountPaid = floatval($sale['amount_paid']) + $payment;
        $newAmountDue  = $amountDue - $payment;
        $newStatus     = $newAmountDue <= 0.01 ? 'paid' : 'partial'; // Float precision

        // Update sale
        $db->query("
            UPDATE sales
            SET amount_paid = ?, amount_due = ?, payment_status = ?
            WHERE id = ?
        ", [$newAmountPaid, max(0, $newAmountDue), $newStatus, $id]);

        // Get customer for balance update
        $customer      = $db->fetchOne("SELECT * FROM customers WHERE id = ?", [$sale['customer_id']]);
        $balanceBefore = floatval($customer['current_balance']);

        // If paying from deposit, deduct from customer balance
        if ($paymentMethod === 'deposit') {
            $balanceAfter = $balanceBefore - $payment; // Reduce deposit

            // Log transaction - negative amount to reduce deposit
            if (!empty($sale['sale_date'])) {
                $db->query("
                INSERT INTO customer_transactions
                    (customer_id, transaction_type, amount, balance_before,
                     balance_after, reference_type, reference_id, payment_method, user_id, created_at)
                VALUES (?, 'payment', ?, ?, ?, 'sale', ?, 'deposit', ?, ?)", [
                    $sale['customer_id'],
                    -$payment, // Negative to reduce balance
                    $balanceBefore,
                    $balanceAfter,
                    $id,
                    $userId,
                    $sale['sale_date']
                ]);
            } else {
                $db->query("
                INSERT INTO customer_transactions
                    (customer_id, transaction_type, amount, balance_before,
                     balance_after, reference_type, reference_id, payment_method, user_id)
                VALUES (?, 'payment', ?, ?, ?, 'sale', ?, 'deposit', ?)
            ", [
                    $sale['customer_id'],
                    -$payment, // Negative to reduce balance
                    $balanceBefore,
                    $balanceAfter,
                    $id,
                    $userId
                ]);
            }
            // Update customer balance - reduce deposit
            $db->query(
                "UPDATE customers SET current_balance = current_balance - ? WHERE id = ?",
                [$payment, $sale['customer_id']]
            );
        } else {
            // Regular payment (cash/mobile/bank) - increases balance
            $balanceAfter = $balanceBefore + $payment;

            // Log transaction
            if (!empty($sale['sale_date'])) {
                $db->query("
                INSERT INTO customer_transactions
                    (customer_id, transaction_type, amount, balance_before,
                     balance_after, reference_type, reference_id, payment_method, user_id, created_at)
                VALUES (?, 'payment', ?, ?, ?, 'sale', ?, ?, ?, ?)", [
                    $sale['customer_id'],
                    $payment,
                    $balanceBefore,
                    $balanceAfter,
                    $id,
                    $paymentMethod,
                    $userId,
                    $sale['sale_date']
                ]);
            } else {
                $db->query("
                INSERT INTO customer_transactions
                    (customer_id, transaction_type, amount, balance_before,
                     balance_after, reference_type, reference_id, payment_method, user_id)
                VALUES (?, 'payment', ?, ?, ?, 'sale', ?, ?, ?)
            ", [
                    $sale['customer_id'],
                    $payment,
                    $balanceBefore,
                    $balanceAfter,
                    $id,
                    $paymentMethod,
                    $userId
                ]);
            }
            // Update customer balance
            $db->query(
                "UPDATE customers SET current_balance = current_balance + ? WHERE id = ?",
                [$payment, $sale['customer_id']]
            );
        }

        $db->commit();
        redirect(
            BASE_URL . '/sales/view/' . $id,
            'success',
            'Payment of ' . formatMoney($payment) . ' recorded successfully'
        );
    } catch (Exception $e) {
        $db->rollback();
        error_log('Payment error: ' . $e->getMessage());
        redirect(BASE_URL . '/sales/pay/' . $id, 'error', 'Failed to process payment');
    }
}

/**
 * Void/cancel a sale (reverses stock)
 */
function voidSale($db, $id)
{
    $sale = $db->fetchOne("SELECT * FROM sales WHERE id = ?", [$id]);
    if (!$sale) {
        redirect(BASE_URL . '/sales', 'error', 'Sale not found');
        return;
    }

    $items  = $db->fetchAll("SELECT * FROM sale_items WHERE sale_id = ?", [$id]);
    $userId = $_SESSION['user_id'] ?? null;

    try {
        $db->beginTransaction();

        // Reverse stock for each item
        foreach ($items as $item) {
            $product   = $db->fetchOne("SELECT current_stock FROM products WHERE id = ?", [$item['product_id']]);
            $prevStock = floatval($product['current_stock']);
            $newStock  = $prevStock + $item['quantity'];

            $db->query("UPDATE products SET current_stock = ? WHERE id = ?", [$newStock, $item['product_id']]);
            $db->query("
                INSERT INTO stock_movements
                    (product_id, movement_type, quantity, reference_type,
                     reference_id, previous_stock, new_stock, notes, user_id)
                VALUES (?, 'in', ?, 'return', ?, ?, ?, 'Sale voided', ?)
            ", [$item['product_id'], $item['quantity'], $id, $prevStock, $newStock, $userId]);
        }

        // Reverse customer balance & transactions
        $customer = $db->fetchOne("SELECT * FROM customers WHERE id = ?", [$sale['customer_id']]);
        if ($customer && !$customer['is_default']) {
            $balanceBefore = floatval($customer['current_balance']);
            
            $paymentMethod = $sale['payment_method'];
            $amountPaid = floatval($sale['amount_paid']);
            $amountDue = floatval($sale['amount_due']);
            $totalAmount = floatval($sale['total_amount']);
            
            $depositRefund = ($paymentMethod === 'deposit') ? $amountPaid : 0;
            $creditReversal = $amountDue; // always reverse the outstanding credit they owe
            $customerBalanceAdj = $depositRefund + $creditReversal;
            
            if ($customerBalanceAdj > 0) {
                $balanceAfter = $balanceBefore + $customerBalanceAdj;
                
                // Update customer balance & total purchases
                $db->query("
                    UPDATE customers 
                    SET current_balance = current_balance + ?,
                        total_purchases = total_purchases - ?
                    WHERE id = ?
                ", [$customerBalanceAdj, $totalAmount, $customer['id']]);
                
                // Log customer transaction
                $db->query("
                    INSERT INTO customer_transactions
                        (customer_id, transaction_type, amount, balance_before,
                         balance_after, reference_type, reference_id, payment_method, notes, user_id)
                    VALUES (?, 'refund', ?, ?, ?, 'sale', ?, ?, ?, ?)
                ", [
                    $customer['id'],
                    $customerBalanceAdj,
                    $balanceBefore,
                    $balanceAfter,
                    $id,
                    $paymentMethod,
                    "Refund/Reversal for Voided Sale #" . $sale['sale_number'],
                    $userId
                ]);
            } else {
                // If no balance adjustment (fully paid cash/mobile/bank sale), just subtract total purchases
                $db->query("
                    UPDATE customers 
                    SET total_purchases = total_purchases - ?
                    WHERE id = ?
                ", [$totalAmount, $customer['id']]);
            }
        }

        // Reverse financial account deposits for cash/mobile/bank payments
        if (floatval($sale['amount_paid']) > 0 && !in_array($sale['payment_method'], ['credit', 'deposit'])) {
            $acctTx = $db->fetchOne("
                SELECT * FROM account_transactions 
                WHERE reference_type = 'sale' AND reference_id = ? AND transaction_type = 'deposit' 
                LIMIT 1
            ", [$id]);
            
            if ($acctTx) {
                // Log withdrawal refund
                recordAccountTransaction(
                    $db,
                    $acctTx['payment_method'],
                    $acctTx['amount'],
                    'withdrawal',
                    'sale',
                    $id,
                    "Refund for Voided Sale #" . $sale['sale_number'],
                    $acctTx['account_id']
                );
            }
        }

        // Mark sale as voided
        $db->query(
            "UPDATE sales SET payment_status = 'unpaid', notes = CONCAT(COALESCE(notes,''), ' [VOIDED]') WHERE id = ?",
            [$id]
        );

        $db->commit();
        redirect(BASE_URL . '/sales', 'success', 'Sale #' . $sale['sale_number'] . ' has been voided');
    } catch (Exception $e) {
        $db->rollback();
        error_log('Void sale error: ' . $e->getMessage());
        redirect(BASE_URL . '/sales/view/' . $id, 'error', 'Failed to void sale');
    }
}

// ============================================================
// HELPERS
// ============================================================
function generateSaleNumber($db)
{
    $prefix = 'SALE-' . date('Ymd') . '-';
    $last   = $db->fetchOne("
        SELECT sale_number FROM sales
        WHERE sale_number LIKE ?
        ORDER BY id DESC LIMIT 1
    ", ["$prefix%"]);

    $num = $last ? intval(substr($last['sale_number'], strrpos($last['sale_number'], '-') + 1)) + 1 : 1;
    return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
}
