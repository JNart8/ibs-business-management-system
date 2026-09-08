<?php

/**
 * Export Controller
 * Handles CSV exports for Categories, Products, Suppliers, Customers, Sales, Transactions, and Reports
 */

if (!defined('APP_START')) {
    die('Direct access not permitted');
}

$db = Database::getInstance();

// Parse URL
$segments = array_values(array_filter(explode('/', trim($path, '/'))));
$type = $segments[1] ?? null; // categories, products, suppliers, customers, sales, transactions, stock-valuation, receivables, payables, sales-report

// Validate type
if (!in_array($type, [
    'categories',
    'products',
    'suppliers',
    'customers',
    'sales',
    'transactions',
    'account-ledger',
    'audit-log',
    'stocks',
    'stock-valuation',
    'receivables',
    'payables',
    'customer-credit-settlement',
    'sales-report',
    'profit-loss',
    'top-selling',
    'low-stock',
    'dead-stock',
    'profit-margin'
])) {
    redirect(BASE_URL . '/', 'error', 'Invalid export type');
    exit;
}

// ── Permission gate ─────────────────────────────────────────────
// Each export type requires the same permission its corresponding
// module page requires (see public/index.php's $permissionRestrictions),
// closing a gap where exports were plan-gated but not permission-gated —
// a cashier without financial_accounts.access, for example, couldn't
// reach the ledger through the UI but could previously still hit
// /export/account-ledger directly. Report exports (sales-report,
// stock-valuation, receivables, payables, profit-loss, top-selling,
// low-stock, dead-stock, profit-margin) and 'sales' intentionally have
// no entry here — their source pages (/reports/*, /sales) have never
// required a permission either, so this preserves that behavior rather
// than introducing a new restriction nobody asked for.
$exportPermissionMap = [
    'categories'      => 'categories.manage',
    'products'        => 'products.manage',
    'suppliers'       => 'suppliers.manage',
    'customers'       => 'customers.manage',
    'transactions'    => 'transactions.manage',
    'account-ledger'  => 'financial_accounts.access',
    'stocks'          => 'stock.manage',
    'audit-log'       => 'audit.view',
];

if (isset($exportPermissionMap[$type]) && !can($exportPermissionMap[$type])) {
    redirect(BASE_URL . '/', 'error', 'You do not have permission to export this data.');
    exit;
}

// Export data
exportData($db, $type);

/**
 * Export data to CSV
 */
function exportData($db, $type)
{
    // Set headers for download
    $filename = $type . '_export_' . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    switch ($type) {
        case 'categories':
            exportCategories($db, $output);
            break;
        case 'products':
            exportProducts($db, $output);
            break;
        case 'suppliers':
            exportSuppliers($db, $output);
            break;
        case 'customers':
            exportCustomers($db, $output);
            break;
        case 'sales':
            exportSales($db, $output);
            break;
        case 'transactions':
            exportTransactions($db, $output);
            break;
        case 'account-ledger':
            exportAccountLedger($db, $output);
            break;
        case 'audit-log':
            exportAuditLog($db, $output);
            break;
        case 'stocks':
            exportStocks($db, $output);
            break;
        case 'stock-valuation':
            exportStockValuation($db, $output);
            break;
        case 'receivables':
            exportReceivables($db, $output);
            break;
        case 'payables':
            exportPayables($db, $output);
            break;
        case 'customer-credit-settlement':
            exportCustomerCreditSettlement($db, $output);
            break;
        case 'sales-report':
            exportSalesReport($db, $output);
            break;
        case 'profit-loss':
            exportProfitLoss($db, $output);
            break;
        case 'top-selling':
            exportTopSelling($db, $output);
            break;
        case 'low-stock':
            exportLowStock($db, $output);
            break;
        case 'dead-stock':
            exportDeadStock($db, $output);
            break;
        case 'profit-margin':
            exportProfitMargin($db, $output);
            break;
    }

    fclose($output);
    exit;
}


/**
 * Export categories
 */
function exportCategories($db, $output)
{
    // Write headers (same as import template)
    fputcsv($output, ['name']);

    // Fetch all active categories
    $categories = $db->fetchAll("
        SELECT name 
        FROM categories 
        WHERE is_active = 1 
        ORDER BY name
    ");

    // Write data rows
    foreach ($categories as $cat) {
        fputcsv($output, [
            $cat['name']
        ]);
    }
}

/**
 * Export products
 */
function exportProducts($db, $output)
{
    // Write headers (same as import template)
    fputcsv($output, [
        'sku',
        'name',
        'category',
        'supplier',
        'selling_price',
        'cost_price',
        'current_stock',
        'reorder_level',
        'unit',
        'barcode'
    ]);

    // Fetch all active products with category and supplier names
    $products = $db->fetchAll("
        SELECT 
            p.sku,
            p.name,
            c.name as category_name,
            s.company_name as supplier_name,
            p.selling_price,
            p.cost_price,
            p.current_stock,
            p.reorder_level,
            p.unit,
            p.barcode
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        WHERE p.is_active = 1
        ORDER BY p.sku
    ");

    // Write data rows
    foreach ($products as $prod) {
        fputcsv($output, [
            $prod['sku'],
            $prod['name'],
            $prod['category_name'] ?? '',
            $prod['supplier_name'] ?? '',
            number_format($prod['selling_price'], 2, '.', ''),
            number_format($prod['cost_price'], 2, '.', ''),
            $prod['current_stock'],
            $prod['reorder_level'],
            $prod['unit'],
            $prod['barcode'] ?? ''
        ]);
    }
}

/**
 * Export suppliers
 */
function exportSuppliers($db, $output)
{
    // Write headers (same as import template)
    fputcsv($output, [
        'company_name',
        'contact_name',
        'phone',
        'email',
        'address'
    ]);

    // Fetch all active suppliers
    $suppliers = $db->fetchAll("
        SELECT 
            company_name,
            contact_name,
            phone,
            email,
            address
        FROM suppliers 
        WHERE is_active = 1 
        ORDER BY company_name
    ");

    // Write data rows
    foreach ($suppliers as $sup) {
        fputcsv($output, [
            $sup['company_name'],
            $sup['contact_name'] ?? '',
            $sup['phone'] ?? '',
            $sup['email'] ?? '',
            $sup['address'] ?? ''
        ]);
    }
}

/**
 * Export customers
 */
function exportCustomers($db, $output)
{
    // Write headers
    fputcsv($output, [
        'id',
        'customer_code',
        'full_name',
        'phone',
        'email',
        'address',
        'credit_limit',
        'current_balance',
        'total_purchases',
        'is_default',
        'is_active',
        'created_at',
        'updated_at'
    ]);

    // Fetch all active customers (exclude default walk-in)
    $customers = $db->fetchAll("
        SELECT 
            id,
            customer_code,
            full_name,
            phone,
            email,
            address,
            credit_limit,
            current_balance,
            total_purchases,
            is_default,
            is_active,
            created_at,
            updated_at
        FROM customers 
        WHERE is_active = 1 
        AND is_default = 0
        ORDER BY full_name
    ");

    // Write data rows
    foreach ($customers as $cust) {
        fputcsv($output, [
            $cust['id'],
            $cust['customer_code'],
            $cust['full_name'],
            $cust['phone'] ?? '',
            $cust['email'] ?? '',
            $cust['address'] ?? '',
            number_format($cust['credit_limit'], 2, '.', ''),
            number_format($cust['current_balance'], 2, '.', ''),
            number_format($cust['total_purchases'], 2, '.', ''),
            $cust['is_default'],
            $cust['is_active'],
            $cust['created_at'],
            $cust['updated_at']
        ]);
    }
}

/**
 * Export sales with line items - WITH FILTER SUPPORT
 */
function exportSales($db, $output)
{
    // Get filters from URL (same as sales list page)
    $search   = trim($_GET['search'] ?? '');
    $status   = $_GET['status']      ?? '';
    $dateFrom = $_GET['date_from']   ?? '';
    $dateTo   = $_GET['date_to']     ?? '';

    // Build WHERE clause
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

    // Date range filter
    if (!empty($dateFrom) && !empty($dateTo)) {
        $where .= " AND DATE(s.sale_date) BETWEEN ? AND ?";
        $params[] = $dateFrom;
        $params[] = $dateTo;
    } elseif (!empty($dateFrom)) {
        $where .= " AND DATE(s.sale_date) >= ?";
        $params[] = $dateFrom;
    } elseif (!empty($dateTo)) {
        $where .= " AND DATE(s.sale_date) <= ?";
        $params[] = $dateTo;
    }

    // Write headers
    fputcsv($output, [
        'sale_number',
        'sale_date',
        'customer_name',
        'product_sku',
        'product_name',
        'quantity',
        'unit_price',
        'item_discount_type',
        'discount_percent',
        'item_discount_amount',
        'line_total',
        'sale_subtotal',
        'sale_discount_type',
        'sale_discount_percent',
        'sale_discount_amount',
        'sale_total',
        'amount_paid',
        'amount_due',
        'payment_method',
        'payment_status',
        'notes',
        'sold_by'
    ]);

    // Fetch sales with items (FILTERED)
    $salesItems = $db->fetchAll("
        SELECT 
            s.sale_number,
            s.sale_date,
            c.full_name as customer_name,
            p.sku as product_sku,
            si.product_name,
            si.quantity,
            si.unit_price,
            si.discount_type AS item_discount_type,
            si.discount_percent,
            si.discount_amount AS item_discount_amount,
            si.line_total,
            s.subtotal as sale_subtotal,
            s.discount_type AS sale_discount_type,
            s.discount_percent as sale_discount_percent,
            s.discount_amount AS sale_discount_amount,
            s.total_amount as sale_total,
            s.amount_paid,
            s.amount_due,
            s.payment_method,
            s.payment_status,
            s.notes,
            u.username as sold_by
        FROM sales s
        INNER JOIN sale_items si ON s.id = si.sale_id
        INNER JOIN customers c ON s.customer_id = c.id
        LEFT JOIN products p ON si.product_id = p.id
        LEFT JOIN users u ON s.user_id = u.id
        $where
        ORDER BY s.sale_date DESC, s.id, si.id
    ", $params);

    // Write data rows
    foreach ($salesItems as $item) {
        fputcsv($output, [
            $item['sale_number'],
            $item['sale_date'],
            $item['customer_name'],
            $item['product_sku'],
            $item['product_name'],
            $item['quantity'],
            number_format($item['unit_price'], 2, '.', ''),
            $item['item_discount_type'],
            $item['discount_percent'],
            number_format($item['item_discount_amount'], 2, '.', ''),
            number_format($item['line_total'], 2, '.', ''),
            number_format($item['sale_subtotal'], 2, '.', ''),
            $item['sale_discount_type'],
            $item['sale_discount_percent'],
            number_format($item['sale_discount_amount'], 2, '.', ''),
            number_format($item['sale_total'], 2, '.', ''),
            number_format($item['amount_paid'], 2, '.', ''),
            number_format($item['amount_due'], 2, '.', ''),
            $item['payment_method'],
            $item['payment_status'],
            $item['notes'] ?? '',
            $item['sold_by']
        ]);
    }

    // Add summary at the end
    fputcsv($output, []);
    fputcsv($output, ['=== EXPORT SUMMARY ===']);
    fputcsv($output, ['Export Date:', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Total Records:', count($salesItems)]);

    // Add filter info if filters were applied
    if (!empty($search) || !empty($status) || !empty($dateFrom) || !empty($dateTo)) {
        fputcsv($output, []);
        fputcsv($output, ['Filters Applied:']);
        if (!empty($search)) {
            fputcsv($output, ['Search:', $search]);
        }
        if (!empty($status)) {
            fputcsv($output, ['Status:', ucfirst($status)]);
        }
        if (!empty($dateFrom)) {
            fputcsv($output, ['Date From:', $dateFrom]);
        }
        if (!empty($dateTo)) {
            fputcsv($output, ['Date To:', $dateTo]);
        }
    }
}

/**
 * Export customer transactions (deposits/payments/credits)
 */
function exportTransactions($db, $output)
{
    // Get all filter parameters from URL (same as index page)
    $search     = trim($_GET['search'] ?? '');
    $customer   = $_GET['customer'] ?? '';  // Note: 'customer' not 'customer_id'
    $type       = $_GET['type'] ?? '';
    $dateFrom   = $_GET['date_from'] ?? '';
    $dateTo     = $_GET['date_to'] ?? '';

    // Build WHERE clause
    $whereClause = "WHERE c.is_default = 0";
    $params = [];

    // Search filter (customer name, phone, notes)
    if (!empty($search)) {
        $whereClause .= " AND (c.full_name LIKE ? OR c.phone LIKE ? OR ct.notes LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    // Customer filter
    if (!empty($customer)) {
        $whereClause .= " AND ct.customer_id = ?";
        $params[] = $customer;
    }

    // Transaction type filter
    if (!empty($type)) {
        $whereClause .= " AND ct.transaction_type = ?";
        $params[] = $type;
    }

    // Date range filter
    if (!empty($dateFrom) && !empty($dateTo)) {
        $whereClause .= " AND DATE(ct.created_at) BETWEEN ? AND ?";
        $params[] = $dateFrom;
        $params[] = $dateTo;
    } elseif (!empty($dateFrom)) {
        $whereClause .= " AND DATE(ct.created_at) >= ?";
        $params[] = $dateFrom;
    } elseif (!empty($dateTo)) {
        $whereClause .= " AND DATE(ct.created_at) <= ?";
        $params[] = $dateTo;
    }

    // Write headers
    fputcsv($output, [
        'transaction_date',
        'customer_name',
        'customer_code',
        'transaction_type',
        'amount',
        'balance_before',
        'balance_after',
        'reference_type',
        'reference_id',
        'payment_method',
        'notes',
        'recorded_by'
    ]);

    // Fetch filtered customer transactions
    $transactions = $db->fetchAll("
        SELECT 
            ct.created_at as transaction_date,
            c.full_name as customer_name,
            c.customer_code,
            ct.transaction_type,
            ct.amount,
            ct.balance_before,
            ct.balance_after,
            ct.reference_type,
            ct.reference_id,
            ct.payment_method,
            ct.notes,
            u.username as recorded_by
        FROM customer_transactions ct
        INNER JOIN customers c ON ct.customer_id = c.id
        LEFT JOIN users u ON ct.user_id = u.id
        $whereClause
        ORDER BY ct.created_at DESC, ct.id DESC
    ", $params);

    // Write data rows
    foreach ($transactions as $txn) {
        fputcsv($output, [
            $txn['transaction_date'],
            $txn['customer_name'],
            $txn['customer_code'],
            $txn['transaction_type'],
            number_format($txn['amount'], 2, '.', ''),
            number_format($txn['balance_before'], 2, '.', ''),
            number_format($txn['balance_after'], 2, '.', ''),
            $txn['reference_type'] ?? '',
            $txn['reference_id'] ?? '',
            $txn['payment_method'] ?? '',
            $txn['notes'] ?? '',
            $txn['recorded_by']
        ]);
    }

    // Add export summary
    fputcsv($output, []);
    fputcsv($output, ['=== EXPORT SUMMARY ===']);
    fputcsv($output, ['Export Date:', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Total Records:', count($transactions)]);

    // Add filter info if filters were applied
    if (!empty($search) || !empty($customer) || !empty($type) || !empty($dateFrom) || !empty($dateTo)) {
        fputcsv($output, []);
        fputcsv($output, ['Filters Applied:']);
        if (!empty($search)) {
            fputcsv($output, ['Search:', $search]);
        }
        if (!empty($customer)) {
            $customerName = $db->fetchOne("SELECT full_name FROM customers WHERE id = ?", [$customer]);
            fputcsv($output, ['Customer:', $customerName['full_name'] ?? 'Unknown']);
        }
        if (!empty($type)) {
            fputcsv($output, ['Type:', ucfirst($type)]);
        }
        if (!empty($dateFrom)) {
            fputcsv($output, ['Date From:', $dateFrom]);
        }
        if (!empty($dateTo)) {
            fputcsv($output, ['Date To:', $dateTo]);
        }
    }
}

/**
 * Export a single financial account's ledger (deposits, withdrawals,
 * transfers, charges) — filtered the same way as the on-screen ledger,
 * using the same shared buildLedgerWhere() helper, so the export can
 * never drift from what the user is actually looking at.
 */
function exportAccountLedger($db, $output)
{
    $accountId = intval($_GET['account_id'] ?? 0);
    $account = $accountId ? $db->fetchOne("SELECT * FROM accounts WHERE id = ?", [$accountId]) : null;

    if (!$account) {
        fputcsv($output, ['Error: account not found or account_id missing.']);
        return;
    }

    $filters = ledgerFilters();
    [$where, $params] = buildLedgerWhere($accountId, $filters);

    // Write headers
    fputcsv($output, [
        'date_time',
        'transaction_type',
        'reference_type',
        'reference_id',
        'amount',
        'balance_before',
        'balance_after',
        'notes',
        'recorded_by'
    ]);

    // Fetch filtered ledger entries — same WHERE the ledger page used
    $transactions = $db->fetchAll("
        SELECT t.*, u.full_name as user_name
        FROM account_transactions t
        LEFT JOIN users u ON t.user_id = u.id
        $where
        ORDER BY t.created_at DESC
    ", $params);

    // Write data rows
    foreach ($transactions as $txn) {
        fputcsv($output, [
            $txn['created_at'],
            $txn['transaction_type'],
            $txn['reference_type'] ?? '',
            $txn['reference_id'] ?? '',
            number_format($txn['amount'], 2, '.', ''),
            number_format($txn['balance_before'], 2, '.', ''),
            number_format($txn['balance_after'], 2, '.', ''),
            $txn['notes'] ?? '',
            $txn['user_name'] ?? 'System'
        ]);
    }

    // Summary
    fputcsv($output, []);
    fputcsv($output, ['=== EXPORT SUMMARY ===']);
    fputcsv($output, ['Account:', $account['name']]);
    fputcsv($output, ['Current Balance:', number_format($account['balance'], 2, '.', '')]);
    fputcsv($output, ['Export Date:', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Total Records:', count($transactions)]);

    // Filter info, if any were applied
    if (!empty($filters['type']) || !empty($filters['date_from']) || !empty($filters['date_to'])) {
        fputcsv($output, []);
        fputcsv($output, ['Filters Applied:']);
        if (!empty($filters['type'])) {
            fputcsv($output, ['Type:', ucfirst(str_replace('_', ' ', $filters['type']))]);
        }
        if (!empty($filters['date_from'])) {
            fputcsv($output, ['Date From:', $filters['date_from']]);
        }
        if (!empty($filters['date_to'])) {
            fputcsv($output, ['Date To:', $filters['date_to']]);
        }
    }
}

/**
 * Export the audit log — filtered the same way the on-screen viewer
 * is (same query shape as AuditController::listAuditLog()), including
 * the same optional UNION with audit_log_archive.
 */
function exportAuditLog($db, $output)
{
    $userId          = $_GET['user_id'] ?? '';
    $action          = $_GET['action'] ?? '';
    $entityType      = $_GET['entity_type'] ?? '';
    $dateFrom        = $_GET['date_from'] ?? '';
    $dateTo          = $_GET['date_to'] ?? '';
    $includeArchived = isset($_GET['include_archived']);

    $source = $includeArchived
        ? "FROM (
              SELECT id, user_id, username, action, entity_type, entity_id, branch_id, details, ip_address, created_at FROM audit_log
              UNION ALL
              SELECT id, user_id, username, action, entity_type, entity_id, branch_id, details, ip_address, created_at FROM audit_log_archive
           ) combined"
        : "FROM audit_log";

    $where  = "WHERE 1=1";
    $params = [];

    [$scopeSql, $scopeParams] = branchScopeSql('');
    $where .= $scopeSql;
    $params = array_merge($params, $scopeParams);

    if (!empty($userId)) {
        $where .= " AND user_id = ?";
        $params[] = $userId;
    }
    if (!empty($action)) {
        $where .= " AND action = ?";
        $params[] = $action;
    }
    if (!empty($entityType)) {
        $where .= " AND entity_type = ?";
        $params[] = $entityType;
    }
    if (!empty($dateFrom)) {
        $where .= " AND DATE(created_at) >= ?";
        $params[] = $dateFrom;
    }
    if (!empty($dateTo)) {
        $where .= " AND DATE(created_at) <= ?";
        $params[] = $dateTo;
    }

    $entries = $db->fetchAll("
        SELECT *
        $source
        $where
        ORDER BY created_at DESC
    ", $params);

    fputcsv($output, ['date_time', 'user', 'action', 'entity_type', 'entity_id', 'branch', 'details', 'ip_address']);

    foreach ($entries as $entry) {
        $details = $entry['details'] ? json_decode($entry['details'], true) : null;
        fputcsv($output, [
            $entry['created_at'],
            $entry['username'] ?? 'System',
            $entry['action'],
            $entry['entity_type'] ?? '',
            $entry['entity_id'] ?? '',
            $entry['branch_id'] ? branchName($entry['branch_id']) : 'Company-wide',
            $details ? json_encode($details) : '',
            $entry['ip_address'] ?? '',
        ]);
    }

    fputcsv($output, []);
    fputcsv($output, ['=== EXPORT SUMMARY ===']);
    fputcsv($output, ['Total Records:', count($entries)]);
    fputcsv($output, ['Includes Archived:', $includeArchived ? 'Yes' : 'No']);
    fputcsv($output, ['Export Date:', date('Y-m-d H:i:s')]);
    if (hasMultiBranch() && !isCompanyWide()) {
        fputcsv($output, ['Note:', 'Partial view — showing ' . activeBranchName() . ' only.']);
    }
}

/**
 * Export Stock Valuation Report
 */
function exportStockValuation($db, $output)
{
    // Get filters from URL
    $category = $_GET['category'] ?? '';
    $supplier = $_GET['supplier'] ?? '';
    $lowStock = $_GET['low_stock'] ?? '';
    $sortBy = $_GET['sort_by'] ?? 'value_desc';

    // Build query
    $params = [];
    $where = "WHERE p.is_active = 1";

    if (!empty($category)) {
        $where .= " AND p.category_id = ?";
        $params[] = $category;
    }
    if (!empty($supplier)) {
        $where .= " AND p.supplier_id = ?";
        $params[] = $supplier;
    }

    // Same as the on-screen report: this must be HAVING (post-aggregation),
    // not WHERE, since stock is now summed across potentially several
    // visible branches per product.
    $havingLowStock = ($lowStock === '1') ? "HAVING COALESCE(SUM(bs.quantity), 0) <= p.reorder_level" : "";

    $orderBy = match ($sortBy) {
        'value_asc' => 'stock_value ASC',
        'name' => 'p.name ASC',
        default => 'stock_value DESC'
    };

    // Branch visibility — matches the on-screen report exactly (all
    // branches for company-wide, just their own for branch-scoped).
    [$scopeSql, $scopeParams] = branchScopeSql('bs');

    // Fetch data
    $products = $db->fetchAll("
        SELECT
            p.name,
            p.sku,
            p.unit,
            c.name AS category_name,
            s.company_name AS supplier_name,
            COALESCE(SUM(bs.quantity), 0) AS current_stock,
            p.average_cost,
            (COALESCE(SUM(bs.quantity), 0) * p.average_cost) AS stock_value,
            p.selling_price,
            ((p.selling_price - p.average_cost) * COALESCE(SUM(bs.quantity), 0)) AS potential_profit,
            CASE WHEN p.average_cost > 0 
                THEN ROUND(((p.selling_price - p.average_cost) / p.average_cost) * 100, 2)
                ELSE 0 
            END AS profit_margin_pct,
            p.reorder_level,
            p.last_purchase_date
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        LEFT JOIN branch_stock bs ON bs.product_id = p.id $scopeSql
        $where
        GROUP BY p.id, p.name, p.sku, p.unit, c.name, s.company_name,
                 p.average_cost, p.selling_price, p.reorder_level, p.last_purchase_date
        $havingLowStock
        ORDER BY $orderBy
    ", array_merge($scopeParams, $params));

    // Calculate totals
    $totalValue = array_sum(array_column($products, 'stock_value'));
    $totalProfit = array_sum(array_column($products, 'potential_profit'));

    // Header row
    fputcsv($output, [
        'Product Name',
        'SKU',
        'Category',
        'Supplier',
        'Stock Qty',
        'Unit',
        'Avg Cost (' . CURRENCY_HOLDER . ')',
        'Stock Value (' . CURRENCY_HOLDER . ')',
        'Selling Price (' . CURRENCY_HOLDER . ')',
        'Profit Margin %',
        'Potential Profit (' . CURRENCY_HOLDER . ')',
        'Reorder Level',
        'Last Purchase',
        'Status'
    ]);

    // Data rows
    foreach ($products as $product) {
        $status = 'OK';
        if ($product['current_stock'] == 0) {
            $status = 'Out of Stock';
        } elseif ($product['current_stock'] <= $product['reorder_level']) {
            $status = 'Low Stock';
        }

        fputcsv($output, [
            $product['name'],
            $product['sku'],
            $product['category_name'] ?? '',
            $product['supplier_name'] ?? '',
            $product['current_stock'],
            $product['unit'],
            number_format($product['average_cost'], 2, '.', ''),
            number_format($product['stock_value'], 2, '.', ''),
            number_format($product['selling_price'], 2, '.', ''),
            number_format($product['profit_margin_pct'], 2, '.', ''),
            number_format($product['potential_profit'], 2, '.', ''),
            $product['reorder_level'],
            $product['last_purchase_date'] ?? 'Never',
            $status
        ]);
    }

    // Totals row
    fputcsv($output, [
        'TOTAL',
        '',
        '',
        '',
        '',
        '',
        '',
        number_format($totalValue, 2, '.', ''),
        '',
        '',
        number_format($totalProfit, 2, '.', ''),
        '',
        '',
        ''
    ]);

    if (hasMultiBranch()) {
        fputcsv($output, []);
        fputcsv($output, ['Scope:', isCompanyWide() ? 'All Branches' : activeBranchName()]);
    }

    // Summary info
    fputcsv($output, []);
    fputcsv($output, ['Report Generated:', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Currency:', CURRENCY_HOLDER]);
    fputcsv($output, ['Total Products:', count($products)]);
}

/**
 * Export Outstanding Receivables Report
 */
function exportReceivables($db, $output)
{
    // Get filters
    $aging = $_GET['aging'] ?? '';
    $sortBy = $_GET['sort_by'] ?? 'amount_desc';

    // amount_owed is summed from branch-scoped unpaid sales, not read from
    // customers.current_balance — same reasoning as receivablesReport()
    // in ReportsController.php: customers are shared company-wide, so a
    // branch-scoped user's export must show the same partial figure the
    // screen shows them, not the full company-wide balance.
    [$scopeSql, $scopeParams] = branchScopeSql('s');

    $where = "WHERE c.is_active = 1 AND c.is_default = 0";
    $params = [];

    if (!empty($aging)) {
        switch ($aging) {
            case 'current':
                $where .= " AND DATEDIFF(CURDATE(), agg.oldest_debt_date) <= 30";
                break;
            case 'overdue_30':
                $where .= " AND DATEDIFF(CURDATE(), agg.oldest_debt_date) BETWEEN 31 AND 60";
                break;
            case 'overdue_60':
                $where .= " AND DATEDIFF(CURDATE(), agg.oldest_debt_date) BETWEEN 61 AND 90";
                break;
            case 'overdue_90':
                $where .= " AND DATEDIFF(CURDATE(), agg.oldest_debt_date) > 90";
                break;
        }
    }

    $orderBy = match ($sortBy) {
        'amount_asc' => 'amount_owed ASC',
        'name' => 'c.full_name ASC',
        'days_desc' => 'days_outstanding DESC',
        default => 'amount_owed DESC'
    };

    // Fetch data
    $customers = $db->fetchAll("
        SELECT
            c.full_name,
            c.phone,
            c.email,
            COALESCE(agg.amount_owed, 0) AS amount_owed,
            c.credit_limit,
            COALESCE(agg.unpaid_invoices, 0) AS unpaid_invoices,
            agg.oldest_debt_date,
            DATEDIFF(CURDATE(), agg.oldest_debt_date) AS days_outstanding
        FROM customers c
        INNER JOIN (
            SELECT
                s.customer_id,
                SUM(s.total_amount - COALESCE(s.amount_paid, 0)) AS amount_owed,
                COUNT(*) AS unpaid_invoices,
                MIN(s.sale_date) AS oldest_debt_date
            FROM sales s
            WHERE s.payment_status IN ('unpaid', 'partial')
              AND (s.total_amount - COALESCE(s.amount_paid, 0)) > 0.01
              $scopeSql
            GROUP BY s.customer_id
        ) agg ON c.id = agg.customer_id
        $where
        ORDER BY $orderBy
    ", array_merge($scopeParams, $params));

    // Calculate total
    $totalOwed = array_sum(array_column($customers, 'amount_owed'));

    // Headers
    fputcsv($output, [
        'Customer Name',
        'Phone',
        'Email',
        'Amount Owed (' . CURRENCY_HOLDER . ')',
        'Credit Limit (' . CURRENCY_HOLDER . ')',
        'Unpaid Invoices',
        'Oldest Debt Date',
        'Days Outstanding',
        'Aging Category',
        'Status',
        'Collection Priority'
    ]);

    // Data
    foreach ($customers as $customer) {
        $days = $customer['days_outstanding'] ?? 0;

        // Determine aging category
        if ($days <= 30) {
            $agingCat = '0-30 days';
            $status = 'Current';
            $priority = 'Low';
        } elseif ($days <= 60) {
            $agingCat = '31-60 days';
            $status = 'Warning';
            $priority = 'Medium';
        } elseif ($days <= 90) {
            $agingCat = '61-90 days';
            $status = 'Overdue';
            $priority = 'High';
        } else {
            $agingCat = '90+ days';
            $status = 'Critical';
            $priority = 'Urgent';
        }

        fputcsv($output, [
            $customer['full_name'],
            $customer['phone'],
            $customer['email'] ?? '',
            number_format($customer['amount_owed'], 2, '.', ''),
            number_format($customer['credit_limit'], 2, '.', ''),
            $customer['unpaid_invoices'],
            $customer['oldest_debt_date'] ?? 'N/A',
            $days,
            $agingCat,
            $status,
            $priority
        ]);
    }

    // Total
    fputcsv($output, [
        'TOTAL',
        '',
        '',
        number_format($totalOwed, 2, '.', ''),
        '',
        '',
        '',
        '',
        '',
        '',
        ''
    ]);

    if (hasMultiBranch() && !isCompanyWide()) {
        fputcsv($output, []);
        fputcsv($output, ['Note: partial view — showing ' . activeBranchName() . ' only.']);
        fputcsv($output, ['Customer balances are shared company-wide; these totals reflect only what was invoiced at this branch.']);
    }

    // Summary
    fputcsv($output, []);
    fputcsv($output, ['Report Generated:', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Total Customers:', count($customers)]);
    fputcsv($output, ['Currency:', CURRENCY_HOLDER]);
}

/**
 * Export Outstanding Payables Report
 */
function exportPayables($db, $output)
{
    // Get filters
    $sortBy = $_GET['sort_by'] ?? 'amount_desc';

    $orderBy = match ($sortBy) {
        'amount_asc' => 'amount_owed ASC',
        'name' => 's.company_name ASC',
        'days_desc' => 'days_outstanding DESC',
        default => 'amount_owed DESC'
    };

    // amount_owed is summed from branch-scoped unpaid purchases, not read
    // from suppliers.current_balance — matches payablesReport() and the
    // same reasoning as exportReceivables() above.
    [$scopeSql, $scopeParams] = branchScopeSql('');

    // Fetch suppliers
    $suppliers = $db->fetchAll("
        SELECT
            s.company_name,
            s.contact_name,
            s.phone,
            s.email,
            (
                SELECT COALESCE(SUM(amount_due), 0) FROM purchases
                WHERE supplier_id = s.id AND payment_status IN ('unpaid', 'partial') $scopeSql
            ) AS amount_owed,
            (
                SELECT COUNT(*) FROM purchases
                WHERE supplier_id = s.id AND payment_status IN ('unpaid', 'partial') $scopeSql
            ) AS unpaid_invoices,
            (
                SELECT MIN(DATE(purchase_date)) FROM purchases
                WHERE supplier_id = s.id AND payment_status IN ('unpaid', 'partial') $scopeSql
            ) AS oldest_debt_date,
            DATEDIFF(CURDATE(), (
                SELECT MIN(DATE(purchase_date)) FROM purchases
                WHERE supplier_id = s.id AND payment_status IN ('unpaid', 'partial') $scopeSql
            )) AS days_outstanding
        FROM suppliers s
        WHERE s.is_active = 1
        HAVING amount_owed > 0.01
        ORDER BY $orderBy
    ", array_merge($scopeParams, $scopeParams, $scopeParams, $scopeParams));

    // Total
    $totalOwed = array_sum(array_column($suppliers, 'amount_owed'));

    // Headers
    fputcsv($output, [
        'Supplier Name',
        'Contact Person',
        'Phone',
        'Email',
        'Amount Owed (' . CURRENCY_HOLDER . ')',
        'Unpaid Purchases',
        'Oldest Debt Date',
        'Days Outstanding',
        'Status',
        'Payment Priority',
        'Action Required'
    ]);

    // Data
    foreach ($suppliers as $supplier) {
        $days = $supplier['days_outstanding'] ?? 0;

        if ($days > 60) {
            $status = 'Urgent';
            $priority = 'Immediate';
            $action = 'Pay This Week';
        } elseif ($days > 30) {
            $status = 'Due Soon';
            $priority = 'High';
            $action = 'Schedule Payment';
        } else {
            $status = 'Current';
            $priority = 'Normal';
            $action = 'Monitor';
        }

        fputcsv($output, [
            $supplier['company_name'],
            $supplier['contact_name'] ?? '',
            $supplier['phone'],
            $supplier['email'] ?? '',
            number_format($supplier['amount_owed'], 2, '.', ''),
            $supplier['unpaid_invoices'],
            $supplier['oldest_debt_date'] ?? 'N/A',
            $days,
            $status,
            $priority,
            $action
        ]);
    }

    // Total
    fputcsv($output, [
        'TOTAL',
        '',
        '',
        '',
        number_format($totalOwed, 2, '.', ''),
        '',
        '',
        '',
        '',
        '',
        ''
    ]);

    if (hasMultiBranch() && !isCompanyWide()) {
        fputcsv($output, []);
        fputcsv($output, ['Note: partial view — showing ' . activeBranchName() . ' only.']);
        fputcsv($output, ['Supplier balances are shared company-wide; these totals reflect only what was purchased at this branch.']);
    }

    // Summary
    fputcsv($output, []);
    fputcsv($output, ['Report Generated:', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Total Suppliers:', count($suppliers)]);
    fputcsv($output, ['Currency:', CURRENCY_HOLDER]);
}

/**
 * Export Customer Credit Settlement Report (Phase 3d) — recomputes
 * the exact same two queries as customerCreditSettlementReport() in
 * ReportsController.php, so the export can never drift from the screen.
 */
function exportCustomerCreditSettlement($db, $output)
{
    $period   = $_GET['period']    ?? 'this_month';
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo   = $_GET['date_to']   ?? '';

    $dates    = calculateDateRangeForExport($period, $dateFrom, $dateTo);
    $dateFrom = $dates['from'];
    $dateTo   = $dates['to'];

    [$issuedScopeSql, $issuedScopeParams] = branchScopeSql('ct');
    $issuedRows = $db->fetchAll("
        SELECT ct.branch_id, SUM(ct.amount) AS issued
        FROM customer_transactions ct
        WHERE ct.transaction_type = 'deposit'
            AND ct.branch_id IS NOT NULL
            AND DATE(ct.created_at) BETWEEN ? AND ?
            $issuedScopeSql
        GROUP BY ct.branch_id
    ", array_merge([$dateFrom, $dateTo], $issuedScopeParams));

    [$redeemedScopeSql, $redeemedScopeParams] = branchScopeSql('s');
    $redeemedRows = $db->fetchAll("
        SELECT s.branch_id, SUM(s.amount_paid) AS redeemed
        FROM sales s
        WHERE s.payment_method = 'deposit'
            AND DATE(s.sale_date) BETWEEN ? AND ?
            $redeemedScopeSql
        GROUP BY s.branch_id
    ", array_merge([$dateFrom, $dateTo], $redeemedScopeParams));

    $branches = [];
    foreach ($issuedRows as $row) {
        $branches[$row['branch_id']]['issued'] = (float) $row['issued'];
    }
    foreach ($redeemedRows as $row) {
        $branches[$row['branch_id']]['redeemed'] = (float) $row['redeemed'];
    }

    $rows = [];
    foreach ($branches as $branchId => $amounts) {
        $issued   = $amounts['issued']   ?? 0.0;
        $redeemed = $amounts['redeemed'] ?? 0.0;
        $rows[] = [
            'branch_name' => branchName($branchId),
            'issued'      => $issued,
            'redeemed'    => $redeemed,
            'net'         => $issued - $redeemed,
        ];
    }
    usort($rows, fn($a, $b) => strcmp($a['branch_name'], $b['branch_name']));

    fputcsv($output, [
        'Branch',
        'Deposits Issued (' . CURRENCY_HOLDER . ')',
        'Credit Redeemed (' . CURRENCY_HOLDER . ')',
        'Net Position (' . CURRENCY_HOLDER . ')',
    ]);

    foreach ($rows as $row) {
        fputcsv($output, [
            $row['branch_name'],
            number_format($row['issued'], 2, '.', ''),
            number_format($row['redeemed'], 2, '.', ''),
            number_format($row['net'], 2, '.', ''),
        ]);
    }

    fputcsv($output, [
        'TOTAL',
        number_format(array_sum(array_column($rows, 'issued')), 2, '.', ''),
        number_format(array_sum(array_column($rows, 'redeemed')), 2, '.', ''),
        number_format(array_sum(array_column($rows, 'net')), 2, '.', ''),
    ]);

    if (hasMultiBranch() && !isCompanyWide()) {
        fputcsv($output, []);
        fputcsv($output, ['Note: partial view — showing ' . activeBranchName() . ' only.']);
        fputcsv($output, ['This report is inherently about cross-branch imbalance; a branch-scoped export can only show this branch\'s side of it.']);
    }

    fputcsv($output, []);
    fputcsv($output, ['Report Period:', $dateFrom . ' to ' . $dateTo]);
    fputcsv($output, ['Report Generated:', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Currency:', CURRENCY_HOLDER]);
}

/**
 * Export Sales Report
 */
function exportSalesReport($db, $output)
{
    // Get filters
    $period = $_GET['period'] ?? 'this_month';
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo = $_GET['date_to'] ?? '';

    // Calculate date range
    $dates = calculateDateRangeForExport($period, $dateFrom, $dateTo);
    $dateFrom = $dates['from'];
    $dateTo = $dates['to'];

    // Fetch one row per sold item. Sale fields intentionally repeat for Excel analysis.
    [$scopeSql, $scopeParams] = branchScopeSql('s');
    $salesItems = $db->fetchAll("
        SELECT
            s.id AS sale_id,
            s.sale_number,
            s.sale_date,
            c.full_name AS customer_name,
            u.full_name AS cashier_name,
            p.sku AS product_sku,
            si.product_name,
            si.quantity,
            si.unit_price,
            si.discount_type AS item_discount_type,
            si.discount_percent AS item_discount_percent,
            si.discount_amount AS item_discount_amount,
            si.line_total,
            s.subtotal,
            s.discount_type AS sale_discount_type,
            s.discount_percent AS sale_discount_percent,
            s.total_amount,
            s.payment_method,
            s.payment_status,
            s.amount_paid,
            s.amount_due,
            s.discount_amount,
            s.notes
        FROM sales s
        INNER JOIN sale_items si ON s.id = si.sale_id
        LEFT JOIN products p ON si.product_id = p.id
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN users u ON s.user_id = u.id
        WHERE DATE(s.sale_date) BETWEEN ? AND ?
        $scopeSql
        ORDER BY s.sale_date DESC, s.id, si.id
    ", array_merge([$dateFrom, $dateTo], $scopeParams));

    // Headers
    fputcsv($output, [
        'Receipt #',
        'Date',
        'Time',
        'Customer',
        'Cashier',
        'Product SKU',
        'Item',
        'Quantity',
        'Unit Price (' . CURRENCY_HOLDER . ')',
        'Item Discount Type',
        'Item Discount %',
        'Item Discount (' . CURRENCY_HOLDER . ')',
        'Line Total (' . CURRENCY_HOLDER . ')',
        'Sale Subtotal (' . CURRENCY_HOLDER . ')',
        'Sale Discount Type',
        'Sale Discount %',
        'Sale Discount (' . CURRENCY_HOLDER . ')',
        'Sale Total (' . CURRENCY_HOLDER . ')',
        'Payment Method',
        'Status',
        'Paid (' . CURRENCY_HOLDER . ')',
        'Due (' . CURRENCY_HOLDER . ')',
        'Notes'
    ]);

    // Data
    $total = 0;
    $totalPaid = 0;
    $totalDue = 0;

    $uniqueSales = [];
    foreach ($salesItems as $sale) {
        $datetime = new DateTime($sale['sale_date']);
        fputcsv($output, [
            $sale['sale_number'],
            $datetime->format('Y-m-d'),
            $datetime->format('H:i:s'),
            $sale['customer_name'] ?? 'Walk-in',
            $sale['cashier_name'] ?? 'System',
            $sale['product_sku'] ?? '',
            $sale['product_name'],
            $sale['quantity'],
            number_format($sale['unit_price'], 2, '.', ''),
            $sale['item_discount_type'],
            $sale['item_discount_percent'],
            number_format($sale['item_discount_amount'], 2, '.', ''),
            number_format($sale['line_total'], 2, '.', ''),
            number_format($sale['subtotal'], 2, '.', ''),
            $sale['sale_discount_type'],
            $sale['sale_discount_percent'],
            number_format($sale['discount_amount'], 2, '.', ''),
            number_format($sale['total_amount'], 2, '.', ''),
            ucfirst($sale['payment_method']),
            ucfirst($sale['payment_status']),
            number_format($sale['amount_paid'], 2, '.', ''),
            number_format($sale['amount_due'], 2, '.', ''),
            $sale['notes'] ?? ''
        ]);

        // Repeated item rows must not multiply sale-level totals.
        if (!isset($uniqueSales[$sale['sale_id']])) {
            $uniqueSales[$sale['sale_id']] = true;
            $total += $sale['total_amount'];
            $totalPaid += $sale['amount_paid'];
            $totalDue += $sale['amount_due'];
        }
    }

    // Totals
    $totalsRow = array_fill(0, 23, '');
    $totalsRow[0] = 'TOTAL';
    $totalsRow[17] = number_format($total, 2, '.', '');
    $totalsRow[20] = number_format($totalPaid, 2, '.', '');
    $totalsRow[21] = number_format($totalDue, 2, '.', '');
    fputcsv($output, $totalsRow);

    // Summary
    fputcsv($output, []);
    fputcsv($output, ['Period:', $dateFrom . ' to ' . $dateTo]);
    fputcsv($output, ['Total Sales:', count($uniqueSales)]);
    fputcsv($output, ['Total Item Rows:', count($salesItems)]);
    fputcsv($output, ['Total Revenue:', CURRENCY_HOLDER . ' ' . number_format($total, 2)]);
    fputcsv($output, ['Total Collected:', CURRENCY_HOLDER . ' ' . number_format($totalPaid, 2)]);
    fputcsv($output, ['Total Outstanding:', CURRENCY_HOLDER . ' ' . number_format($totalDue, 2)]);
    fputcsv($output, ['Report Generated:', date('Y-m-d H:i:s')]);
}

/**
 * Export Profit & Loss Statement
 */
function exportProfitLoss($db, $output)
{
    // Get filters
    $period = $_GET['period'] ?? 'this_month';
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo = $_GET['date_to'] ?? '';

    // Calculate date range
    $dates = calculateDateRangeForExport($period, $dateFrom, $dateTo);
    $dateFrom = $dates['from'];
    $dateTo = $dates['to'];

    // ── Revenue ─────────────────────────────────────────────
    [$scopeSql, $scopeParams] = branchScopeSql('');
    $revenue = $db->fetchOne("
        SELECT
            COALESCE(SUM(total_amount), 0) AS total_sales,
            COALESCE(SUM(discount_amount), 0) AS total_discounts,
            COALESCE(SUM(total_amount - discount_amount), 0) AS net_sales
        FROM sales
        WHERE DATE(sale_date) BETWEEN ? AND ?
        $scopeSql
    ", array_merge([$dateFrom, $dateTo], $scopeParams));

    // ── COGS ────────────────────────────────────────────────
    [$scopeSqlS, $scopeParamsS] = branchScopeSql('s');
    $cogs = $db->fetchOne("
        SELECT
            COALESCE(SUM(si.quantity * p.average_cost), 0) AS total_cogs
        FROM sale_items si
        INNER JOIN sales s ON si.sale_id = s.id
        INNER JOIN products p ON si.product_id = p.id
        WHERE DATE(s.sale_date) BETWEEN ? AND ?
        $scopeSqlS
    ", array_merge([$dateFrom, $dateTo], $scopeParamsS));

    // ── Calculations ────────────────────────────────────────
    $grossProfit = $revenue['net_sales'] - $cogs['total_cogs'];
    $grossMargin = $revenue['net_sales'] > 0 ? ($grossProfit / $revenue['net_sales']) * 100 : 0;

    // ── Expenses (if table exists) ──────────────────────────
    $expenses = $db->fetchOne("
        SELECT COALESCE(SUM(amount), 0) AS total_expenses
        FROM expenses
        WHERE DATE(expense_date) BETWEEN ? AND ?
        $scopeSql
    ", array_merge([$dateFrom, $dateTo], $scopeParams)) ?? ['total_expenses' => 0];

    $netProfit = $grossProfit - $expenses['total_expenses'];
    $netMargin = $revenue['net_sales'] > 0 ? ($netProfit / $revenue['net_sales']) * 100 : 0;

    // ── Sales by Category ───────────────────────────────────
    $salesByCategory = $db->fetchAll("
        SELECT
            COALESCE(c.name, 'Uncategorized') AS category_name,
            COALESCE(SUM(si.line_total), 0) AS revenue,
            COALESCE(SUM(si.quantity * p.average_cost), 0) AS cogs,
            COALESCE(SUM(si.line_total) - SUM(si.quantity * p.average_cost), 0) AS profit
        FROM sale_items si
        INNER JOIN sales s ON si.sale_id = s.id
        INNER JOIN products p ON si.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE DATE(s.sale_date) BETWEEN ? AND ?
        $scopeSqlS
        GROUP BY p.category_id, c.name
        ORDER BY revenue DESC
    ", array_merge([$dateFrom, $dateTo], $scopeParamsS));

    // ── Write CSV ───────────────────────────────────────────
    fputcsv($output, ['PROFIT & LOSS STATEMENT']);
    fputcsv($output, ['Period:', $dateFrom . ' to ' . $dateTo]);
    fputcsv($output, ['Currency:', CURRENCY_HOLDER]);
    if (hasMultiBranch() && !isCompanyWide()) {
        fputcsv($output, ['Branch:', activeBranchName()]);
    }
    fputcsv($output, []);

    // Revenue Section
    fputcsv($output, ['REVENUE']);
    fputcsv($output, ['Total Sales', number_format($revenue['total_sales'], 2, '.', '')]);
    fputcsv($output, ['Less: Discounts', number_format($revenue['total_discounts'], 2, '.', '')]);
    fputcsv($output, ['Net Sales', number_format($revenue['net_sales'], 2, '.', '')]);
    fputcsv($output, []);

    // COGS Section
    fputcsv($output, ['COST OF GOODS SOLD']);
    fputcsv($output, ['COGS', number_format($cogs['total_cogs'], 2, '.', '')]);
    fputcsv($output, []);

    // Gross Profit
    fputcsv($output, ['GROSS PROFIT', number_format($grossProfit, 2, '.', '')]);
    fputcsv($output, ['Gross Margin %', number_format($grossMargin, 2, '.', '') . '%']);
    fputcsv($output, []);

    // Operating Expenses
    fputcsv($output, ['OPERATING EXPENSES']);
    fputcsv($output, ['Total Expenses', number_format($expenses['total_expenses'], 2, '.', '')]);
    fputcsv($output, []);

    // Net Profit
    fputcsv($output, ['NET PROFIT', number_format($netProfit, 2, '.', '')]);
    fputcsv($output, ['Net Margin %', number_format($netMargin, 2, '.', '') . '%']);
    fputcsv($output, []);

    // Sales by Category
    fputcsv($output, ['SALES BY CATEGORY']);
    fputcsv($output, ['Category', 'Revenue (' . CURRENCY_HOLDER . ')', 'COGS (' . CURRENCY_HOLDER . ')', 'Profit (' . CURRENCY_HOLDER . ')']);
    foreach ($salesByCategory as $cat) {
        fputcsv($output, [
            $cat['category_name'],
            number_format($cat['revenue'], 2, '.', ''),
            number_format($cat['cogs'], 2, '.', ''),
            number_format($cat['profit'], 2, '.', '')
        ]);
    }

    fputcsv($output, []);
    fputcsv($output, ['Report Generated:', date('Y-m-d H:i:s')]);
}

/**
 * Export Top Selling Products
 */
function exportTopSelling($db, $output)
{
    // Get filters
    $period = $_GET['period'] ?? 'this_month';
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo = $_GET['date_to'] ?? '';
    $category = $_GET['category'] ?? '';
    $limit = $_GET['limit'] ?? 50;

    // Calculate date range
    $dates = calculateDateRangeForExport($period, $dateFrom, $dateTo);
    $dateFrom = $dates['from'];
    $dateTo = $dates['to'];

    // Build WHERE clause
    $params = [$dateFrom, $dateTo];
    $whereCategory = '';
    if (!empty($category)) {
        $whereCategory = " AND p.category_id = ?";
        $params[] = $category;
    }

    // Branch visibility — same rule as the on-screen report.
    [$scopeSql, $scopeParams] = branchScopeSql('s');
    $whereCategory .= $scopeSql;
    $params = array_merge($params, $scopeParams);

    // Fetch top products by quantity
    $topProducts = $db->fetchAll("
        SELECT
            p.name,
            p.sku,
            c.name AS category_name,
            SUM(si.quantity) AS total_quantity,
            COUNT(DISTINCT si.sale_id) AS order_count,
            COALESCE(SUM(si.line_total), 0) AS total_revenue,
            COALESCE(SUM(si.quantity * p.average_cost), 0) AS total_cogs,
            COALESCE(SUM(si.line_total) - SUM(si.quantity * p.average_cost), 0) AS total_profit,
            CASE WHEN SUM(si.quantity * p.average_cost) > 0 
                THEN ((SUM(si.line_total) - SUM(si.quantity * p.average_cost)) / SUM(si.quantity * p.average_cost)) * 100
                ELSE 0 
            END AS profit_margin_pct
        FROM sale_items si
        INNER JOIN sales s ON si.sale_id = s.id
        INNER JOIN products p ON si.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE DATE(s.sale_date) BETWEEN ? AND ?
        $whereCategory
        GROUP BY si.product_id, p.name, p.sku, c.name
        ORDER BY total_quantity DESC
        LIMIT ?
    ", array_merge($params, [(int)$limit]));

    // Headers
    fputcsv($output, ['TOP SELLING PRODUCTS']);
    fputcsv($output, ['Period:', $dateFrom . ' to ' . $dateTo]);
    fputcsv($output, ['Top:', $limit . ' products']);
    fputcsv($output, []);

    fputcsv($output, [
        'Rank',
        'Product Name',
        'SKU',
        'Category',
        'Qty Sold',
        'Orders',
        'Revenue (' . CURRENCY_HOLDER . ')',
        'COGS (' . CURRENCY_HOLDER . ')',
        'Profit (' . CURRENCY_HOLDER . ')',
        'Margin %'
    ]);

    // Data
    $rank = 1;
    foreach ($topProducts as $product) {
        fputcsv($output, [
            $rank++,
            $product['name'],
            $product['sku'],
            $product['category_name'] ?? 'N/A',
            number_format($product['total_quantity'], 3, '.', ''),
            $product['order_count'],
            number_format($product['total_revenue'], 2, '.', ''),
            number_format($product['total_cogs'], 2, '.', ''),
            number_format($product['total_profit'], 2, '.', ''),
            number_format($product['profit_margin_pct'], 2, '.', '') . '%'
        ]);
    }

    // Totals
    fputcsv($output, []);
    fputcsv($output, [
        'TOTAL',
        '',
        '',
        '',
        number_format(array_sum(array_column($topProducts, 'total_quantity')), 3, '.', ''),
        '',
        number_format(array_sum(array_column($topProducts, 'total_revenue')), 2, '.', ''),
        number_format(array_sum(array_column($topProducts, 'total_cogs')), 2, '.', ''),
        number_format(array_sum(array_column($topProducts, 'total_profit')), 2, '.', ''),
        ''
    ]);

    fputcsv($output, []);
    fputcsv($output, ['Report Generated:', date('Y-m-d H:i:s')]);
}

/**
 * Export Low Stock Alert
 */
function exportLowStock($db, $output)
{
    // Get filters
    $category = $_GET['category'] ?? '';
    $severity = $_GET['severity'] ?? '';

    // Build WHERE
    $params = [];
    $where = "WHERE p.is_active = 1";

    if (!empty($category)) {
        $where .= " AND p.category_id = ?";
        $params[] = $category;
    }

    // Same as the on-screen report: severity is evaluated post-aggregation
    // (HAVING), since stock is summed across potentially several visible
    // branches per product.
    if ($severity === 'critical') {
        $having = "HAVING COALESCE(SUM(bs.quantity), 0) = 0";
    } elseif ($severity === 'warning') {
        $having = "HAVING COALESCE(SUM(bs.quantity), 0) > 0 AND COALESCE(SUM(bs.quantity), 0) <= p.reorder_level";
    } else {
        $having = "HAVING COALESCE(SUM(bs.quantity), 0) <= p.reorder_level";
    }

    // Branch visibility — matches the on-screen report exactly.
    [$scopeSql, $scopeParams] = branchScopeSql('bs');

    // Fetch products
    $products = $db->fetchAll("
        SELECT
            p.name,
            p.sku,
            c.name AS category_name,
            COALESCE(SUM(bs.quantity), 0) AS current_stock,
            p.reorder_level,
            p.average_cost,
            p.selling_price,
            s.company_name AS supplier_name,
            s.phone AS supplier_phone,
            GREATEST((p.reorder_level * 2) - COALESCE(SUM(bs.quantity), 0), p.reorder_level) AS suggested_order_qty,
            (GREATEST((p.reorder_level * 2) - COALESCE(SUM(bs.quantity), 0), p.reorder_level) * p.average_cost) AS order_cost
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        LEFT JOIN branch_stock bs ON bs.product_id = p.id $scopeSql
        $where
        GROUP BY p.id, p.name, p.sku, c.name, p.reorder_level, p.average_cost,
                 p.selling_price, s.company_name, s.phone
        $having
        ORDER BY 
            CASE WHEN COALESCE(SUM(bs.quantity), 0) = 0 THEN 0 ELSE 1 END,
            current_stock ASC
    ", array_merge($scopeParams, $params));

    // Headers
    fputcsv($output, ['LOW STOCK ALERT']);
    fputcsv($output, ['Report Date:', date('Y-m-d H:i:s')]);
    if (hasMultiBranch()) {
        fputcsv($output, ['Scope:', isCompanyWide() ? 'All Branches' : activeBranchName()]);
    }
    fputcsv($output, ['Total Items:', count($products)]);
    fputcsv($output, []);

    fputcsv($output, [
        'Product Name',
        'SKU',
        'Category',
        'Current Stock',
        'Reorder Level',
        'Status',
        'Suggested Order Qty',
        'Order Cost (' . CURRENCY_HOLDER . ')',
        'Supplier',
        'Supplier Phone'
    ]);

    // Data
    $totalOrderCost = 0;
    foreach ($products as $product) {
        $status = $product['current_stock'] == 0 ? 'OUT OF STOCK' : 'LOW STOCK';
        $totalOrderCost += $product['order_cost'];

        fputcsv($output, [
            $product['name'],
            $product['sku'],
            $product['category_name'] ?? 'N/A',
            $product['current_stock'],
            $product['reorder_level'],
            $status,
            $product['suggested_order_qty'],
            number_format($product['order_cost'], 2, '.', ''),
            $product['supplier_name'] ?? 'No supplier',
            $product['supplier_phone'] ?? ''
        ]);
    }

    // Summary
    fputcsv($output, []);
    fputcsv($output, ['Total Estimated Reorder Cost:', number_format($totalOrderCost, 2, '.', '')]);
    fputcsv($output, ['Currency:', CURRENCY_HOLDER]);
}

/**
 * Export Dead Stock Report
 */
function exportDeadStock($db, $output)
{
    // Get filters
    $period = $_GET['period'] ?? 90;
    $category = $_GET['category'] ?? '';
    $minValue = $_GET['min_value'] ?? 0;

    // Branch visibility — matches deadStockReport() exactly: both the
    // stock figure and the sales-history subqueries must agree on the
    // same branch scope.
    [$stockScopeSql, $stockScopeParams] = branchScopeSql('bs');
    [$salesScopeSql, $salesScopeParams] = branchScopeSql('s');

    $whereCategory = '';
    $categoryParams = [];
    if (!empty($category)) {
        $whereCategory = " AND p.category_id = ?";
        $categoryParams[] = $category;
    }

    // Fetch dead stock
    $products = $db->fetchAll("
        SELECT
            p.name,
            p.sku,
            c.name AS category_name,
            COALESCE(SUM(bs.quantity), 0) AS current_stock,
            p.average_cost,
            p.selling_price,
            (COALESCE(SUM(bs.quantity), 0) * p.average_cost) AS stock_value,
            (COALESCE(SUM(bs.quantity), 0) * p.selling_price) AS potential_revenue,
            ((p.selling_price - p.average_cost) * COALESCE(SUM(bs.quantity), 0)) AS potential_profit,
            (
                SELECT MAX(DATE(s.sale_date))
                FROM sale_items si
                INNER JOIN sales s ON si.sale_id = s.id
                WHERE si.product_id = p.id
                $salesScopeSql
            ) AS last_sale_date,
            DATEDIFF(
                CURDATE(),
                (
                    SELECT MAX(DATE(s.sale_date))
                    FROM sale_items si
                    INNER JOIN sales s ON si.sale_id = s.id
                    WHERE si.product_id = p.id
                    $salesScopeSql
                )
            ) AS days_since_last_sale
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN branch_stock bs ON bs.product_id = p.id $stockScopeSql
        WHERE p.is_active = 1
        $whereCategory
        GROUP BY p.id, p.name, p.sku, c.name, p.average_cost, p.selling_price
        HAVING current_stock > 0
           AND (last_sale_date IS NULL OR days_since_last_sale >= ?)
           AND stock_value >= ?
        ORDER BY stock_value DESC
    ", array_merge(
        $salesScopeParams, $salesScopeParams,
        $stockScopeParams, $categoryParams,
        [(int)$period, (float)$minValue]
    ));

    // Headers
    fputcsv($output, ['DEAD STOCK REPORT']);
    fputcsv($output, ['No Sales In:', $period . ' days']);
    fputcsv($output, ['Report Date:', date('Y-m-d H:i:s')]);
    if (hasMultiBranch()) {
        fputcsv($output, ['Scope:', isCompanyWide() ? 'All Branches' : activeBranchName()]);
    }
    fputcsv($output, []);

    fputcsv($output, [
        'Product Name',
        'SKU',
        'Category',
        'Stock Qty',
        'Avg Cost (' . CURRENCY_HOLDER . ')',
        'Stock Value (' . CURRENCY_HOLDER . ')',
        'Selling Price (' . CURRENCY_HOLDER . ')',
        'Potential Revenue (' . CURRENCY_HOLDER . ')',
        'Potential Profit (' . CURRENCY_HOLDER . ')',
        'Last Sale Date',
        'Days Idle',
        'Recommendation'
    ]);

    // Data
    $totalValue = 0;
    $totalPotentialRevenue = 0;
    foreach ($products as $product) {
        $days = $product['days_since_last_sale'] ?? 999;
        $recommendation = $days > 180 ? 'Clearance Sale' : ($days > 90 ? 'Discount 30%' : 'Bundle or Promote');

        $totalValue += $product['stock_value'];
        $totalPotentialRevenue += $product['potential_revenue'];

        fputcsv($output, [
            $product['name'],
            $product['sku'],
            $product['category_name'] ?? 'N/A',
            $product['current_stock'],
            number_format($product['average_cost'], 2, '.', ''),
            number_format($product['stock_value'], 2, '.', ''),
            number_format($product['selling_price'], 2, '.', ''),
            number_format($product['potential_revenue'], 2, '.', ''),
            number_format($product['potential_profit'], 2, '.', ''),
            $product['last_sale_date'] ?? 'Never',
            $days,
            $recommendation
        ]);
    }

    // Summary
    fputcsv($output, []);
    fputcsv($output, ['TOTALS']);
    fputcsv($output, ['Total Items:', count($products)]);
    fputcsv($output, ['Total Value Tied Up:', number_format($totalValue, 2, '.', '')]);
    fputcsv($output, ['Potential Revenue:', number_format($totalPotentialRevenue, 2, '.', '')]);
    fputcsv($output, ['Currency:', CURRENCY_HOLDER]);
}

/**
 * Export Profit Margin Analysis
 */
function exportProfitMargin($db, $output)
{
    // Get filters
    $category = $_GET['category'] ?? '';
    $sortBy = $_GET['sort_by'] ?? 'margin_desc';

    // Build WHERE (stock-derived "has stock" condition moves to HAVING)
    $params = [];
    $where = "WHERE p.is_active = 1";

    if (!empty($category)) {
        $where .= " AND p.category_id = ?";
        $params[] = $category;
    }

    // Determine sort
    $orderBy = match ($sortBy) {
        'margin_asc' => 'profit_margin_pct ASC',
        'profit_desc' => 'profit_per_unit DESC',
        'revenue_desc' => 'potential_revenue DESC',
        'stock_desc' => 'current_stock DESC',
        default => 'profit_margin_pct DESC'
    };

    // Margin stays company-wide (correct — cost accounting doesn't differ
    // per branch); only stock quantity is branch-scoped, matching the
    // on-screen report exactly.
    [$scopeSql, $scopeParams] = branchScopeSql('bs');

    // Fetch products
    $products = $db->fetchAll("
        SELECT
            p.name,
            p.sku,
            c.name AS category_name,
            COALESCE(SUM(bs.quantity), 0) AS current_stock,
            p.average_cost,
            p.selling_price,
            (p.selling_price - p.average_cost) AS profit_per_unit,
            CASE WHEN p.average_cost > 0 
                THEN ((p.selling_price - p.average_cost) / p.average_cost) * 100
                ELSE 0 
            END AS profit_margin_pct,
            (COALESCE(SUM(bs.quantity), 0) * p.average_cost) AS stock_value,
            (COALESCE(SUM(bs.quantity), 0) * p.selling_price) AS potential_revenue,
            ((p.selling_price - p.average_cost) * COALESCE(SUM(bs.quantity), 0)) AS potential_profit
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN branch_stock bs ON bs.product_id = p.id $scopeSql
        $where
        GROUP BY p.id, p.name, p.sku, c.name, p.average_cost, p.selling_price
        HAVING current_stock > 0
        ORDER BY $orderBy
    ", array_merge($scopeParams, $params));

    // Headers
    fputcsv($output, ['PROFIT MARGIN ANALYSIS']);
    fputcsv($output, ['Report Date:', date('Y-m-d H:i:s')]);
    if (hasMultiBranch()) {
        fputcsv($output, ['Scope:', isCompanyWide() ? 'All Branches' : activeBranchName()]);
    }
    fputcsv($output, []);

    fputcsv($output, [
        'Product Name',
        'SKU',
        'Category',
        'Stock Qty',
        'Avg Cost (' . CURRENCY_HOLDER . ')',
        'Selling Price (' . CURRENCY_HOLDER . ')',
        'Profit/Unit (' . CURRENCY_HOLDER . ')',
        'Margin %',
        'Stock Value (' . CURRENCY_HOLDER . ')',
        'Potential Revenue (' . CURRENCY_HOLDER . ')',
        'Potential Profit (' . CURRENCY_HOLDER . ')',
        'Margin Category'
    ]);

    // Data
    $totalStockValue = 0;
    $totalPotentialProfit = 0;
    foreach ($products as $product) {
        $margin = $product['profit_margin_pct'];
        $marginCategory = $margin < 10 ? 'Low (<10%)' : ($margin < 20 ? 'Medium (10-20%)' : ($margin < 30 ? 'Good (20-30%)' : 'Excellent (30%+)'));

        $totalStockValue += $product['stock_value'];
        $totalPotentialProfit += $product['potential_profit'];

        fputcsv($output, [
            $product['name'],
            $product['sku'],
            $product['category_name'] ?? 'N/A',
            $product['current_stock'],
            number_format($product['average_cost'], 2, '.', ''),
            number_format($product['selling_price'], 2, '.', ''),
            number_format($product['profit_per_unit'], 2, '.', ''),
            number_format($margin, 2, '.', '') . '%',
            number_format($product['stock_value'], 2, '.', ''),
            number_format($product['potential_revenue'], 2, '.', ''),
            number_format($product['potential_profit'], 2, '.', ''),
            $marginCategory
        ]);
    }

    // Summary
    $avgMargin = count($products) > 0 ? array_sum(array_column($products, 'profit_margin_pct')) / count($products) : 0;
    fputcsv($output, []);
    fputcsv($output, ['SUMMARY']);
    fputcsv($output, ['Total Products:', count($products)]);
    fputcsv($output, ['Average Margin:', number_format($avgMargin, 2, '.', '') . '%']);
    fputcsv($output, ['Total Stock Value:', number_format($totalStockValue, 2, '.', '')]);
    fputcsv($output, ['Total Potential Profit:', number_format($totalPotentialProfit, 2, '.', '')]);
    fputcsv($output, ['Currency:', CURRENCY_HOLDER]);
}

function exportStocks($db, $output)
{
    // Get filter parameters (matching your stock index page)
    $search     = trim($_GET['search'] ?? '');
    $category   = $_GET['category'] ?? '';
    $filter     = $_GET['filter'] ?? 'all'; // all, ok, low, out

    // Build WHERE clause
    $whereClause = "WHERE p.is_active = 1";
    $params = [];

    // Search filter (product name, SKU, barcode)
    if (!empty($search)) {
        $whereClause .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    // Category filter
    if (!empty($category)) {
        $whereClause .= " AND p.category_id = ?";
        $params[] = $category;
    }

    // Stock status filter — branch-scoped (COALESCE(bs.quantity, 0)), matching the on-screen dashboard exactly
    if ($filter === 'out') {
        $whereClause .= " AND COALESCE(bs.quantity, 0) <= 0";
    } elseif ($filter === 'low') {
        $whereClause .= " AND COALESCE(bs.quantity, 0) > 0 AND COALESCE(bs.quantity, 0) <= p.reorder_level";
    } elseif ($filter === 'ok') {
        $whereClause .= " AND COALESCE(bs.quantity, 0) > p.reorder_level";
    }
    // 'all' = no additional filter

    // Write CSV headers (ALL fields you requested)
    fputcsv($output, [
        'sku',
        'product_name',
        'category',
        'unit',
        'current_stock',
        'reorder_level',
        'cost_price',
        'selling_price',
        'profit_margin_percent',
        'stock_value_cost',
        'stock_value_selling',
        'potential_profit',
        'stock_status',
        'last_movement_date',
        'is_active'
    ]);

    // Fetch stock data — current_stock/stock_value/status all reflect THIS
    // branch (bs.quantity), not the company-wide total, matching the
    // on-screen stock dashboard exactly.
    $branchId = activeBranchId();
    $stocks = $db->fetchAll("
        SELECT 
            p.sku,
            p.name as product_name,
            c.name as category,
            p.unit,
            COALESCE(bs.quantity, 0) as current_stock,
            p.reorder_level,
            p.cost_price,
            p.selling_price,
            p.is_active,
            
            -- Calculated fields
            CASE 
                WHEN p.cost_price > 0 
                THEN ROUND(((p.selling_price - p.cost_price) / p.cost_price) * 100, 2)
                ELSE 0 
            END as profit_margin_pct,
            
            (COALESCE(bs.quantity, 0) * p.cost_price) as stock_value_cost,
            (COALESCE(bs.quantity, 0) * p.selling_price) as stock_value_selling,
            (COALESCE(bs.quantity, 0) * (p.selling_price - p.cost_price)) as potential_profit,
            
            -- Stock status (matching your page logic)
            CASE 
                WHEN COALESCE(bs.quantity, 0) <= 0 THEN 'out'
                WHEN COALESCE(bs.quantity, 0) <= p.reorder_level THEN 'low'
                ELSE 'ok'
            END as stock_status,
            
            -- Last movement date (this branch only)
            (SELECT MAX(created_at) 
             FROM stock_movements sm 
             WHERE sm.product_id = p.id AND sm.branch_id = ?) as last_movement
             
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN branch_stock bs ON bs.product_id = p.id AND bs.branch_id = ?
        $whereClause
        ORDER BY p.name ASC
    ", array_merge([$branchId, $branchId], $params));

    // Calculate totals
    $totalProducts = count($stocks);
    $totalStockValueCost = 0;
    $totalStockValueSelling = 0;
    $totalPotentialProfit = 0;
    $outOfStockCount = 0;
    $lowStockCount = 0;
    $healthyStockCount = 0;

    // Write data rows
    foreach ($stocks as $stock) {
        // Accumulate totals
        $totalStockValueCost += floatval($stock['stock_value_cost']);
        $totalStockValueSelling += floatval($stock['stock_value_selling']);
        $totalPotentialProfit += floatval($stock['potential_profit']);

        // Count by status
        if ($stock['stock_status'] === 'out') $outOfStockCount++;
        elseif ($stock['stock_status'] === 'low') $lowStockCount++;
        else $healthyStockCount++;

        fputcsv($output, [
            $stock['sku'],
            $stock['product_name'],
            $stock['category'] ?? 'Uncategorized',
            $stock['unit'],
            $stock['current_stock'],
            $stock['reorder_level'],
            number_format($stock['cost_price'], 2, '.', ''),
            number_format($stock['selling_price'], 2, '.', ''),
            number_format($stock['profit_margin_pct'], 2, '.', '') . '%',
            number_format($stock['stock_value_cost'], 2, '.', ''),
            number_format($stock['stock_value_selling'], 2, '.', ''),
            number_format($stock['potential_profit'], 2, '.', ''),
            ucfirst($stock['stock_status']), // Out, Low, Ok
            $stock['last_movement'] ? date('Y-m-d H:i', strtotime($stock['last_movement'])) : 'Never',
            $stock['is_active'] ? 'Active' : 'Inactive'
        ]);
    }

    // Add summary section
    fputcsv($output, []);
    fputcsv($output, ['=== STOCK SUMMARY ===']);
    fputcsv($output, ['Export Date:', date('Y-m-d H:i:s')]);
    if (hasMultiBranch()) {
        fputcsv($output, ['Branch:', activeBranchName()]);
    }
    fputcsv($output, ['Total Products:', $totalProducts]);
    fputcsv($output, []);
    fputcsv($output, ['By Status:']);
    fputcsv($output, ['- Healthy Stock:', $healthyStockCount]);
    fputcsv($output, ['- Low Stock:', $lowStockCount]);
    fputcsv($output, ['- Out of Stock:', $outOfStockCount]);
    fputcsv($output, []);
    fputcsv($output, ['Stock Valuation:']);
    fputcsv($output, ['Total Stock Value (Cost):', number_format($totalStockValueCost, 2)]);
    fputcsv($output, ['Total Stock Value (Selling):', number_format($totalStockValueSelling, 2)]);
    fputcsv($output, ['Potential Profit:', number_format($totalPotentialProfit, 2)]);

    // Add filter info if filters were applied
    if (!empty($search) || !empty($category) || $filter !== 'all') {
        fputcsv($output, []);
        fputcsv($output, ['Filters Applied:']);

        if (!empty($search)) {
            fputcsv($output, ['Search:', $search]);
        }

        if (!empty($category)) {
            $categoryName = $db->fetchOne("SELECT name FROM categories WHERE id = ?", [$category]);
            fputcsv($output, ['Category:', $categoryName['name'] ?? 'Unknown']);
        }

        if ($filter !== 'all') {
            $filterLabels = [
                'ok' => 'Healthy Stock Only',
                'low' => 'Low Stock Only',
                'out' => 'Out of Stock Only'
            ];
            fputcsv($output, ['Status Filter:', $filterLabels[$filter] ?? $filter]);
        }
    }
}

// ============================================================
// HELPER FUNCTION
// ============================================================

/**
 * Calculate date range based on period
 */
function calculateDateRangeForExport($period, $customFrom = '', $customTo = '')
{
    $today = date('Y-m-d');
    $from = $today;
    $to = $today;

    switch ($period) {
        case 'today':
            $from = $to = $today;
            break;
        case 'yesterday':
            $from = $to = date('Y-m-d', strtotime('-1 day'));
            break;
        case 'this_week':
            $from = date('Y-m-d', strtotime('monday this week'));
            $to = $today;
            break;
        case 'last_week':
            $from = date('Y-m-d', strtotime('monday last week'));
            $to = date('Y-m-d', strtotime('sunday last week'));
            break;
        case 'this_month':
            $from = date('Y-m-01');
            $to = $today;
            break;
        case 'last_month':
            $from = date('Y-m-01', strtotime('first day of last month'));
            $to = date('Y-m-t', strtotime('last day of last month'));
            break;
        case 'this_year':
            $from = date('Y-01-01');
            $to = $today;
            break;
        case 'custom':
            if (!empty($customFrom)) $from = $customFrom;
            if (!empty($customTo)) $to = $customTo;
            break;
        default:
            $from = date('Y-m-01');
            $to = $today;
    }

    return ['from' => $from, 'to' => $to];
}
