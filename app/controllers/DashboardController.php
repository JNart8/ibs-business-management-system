<?php

/**
 * Dashboard Controller - IMPROVED VERSION
 * Modern business overview with financial insights
 */

if (!defined('APP_START')) {
    die('Direct access not permitted');
}

$db = Database::getInstance();

// ══════════════════════════════════════════════════════════════════════════════
// TODAY'S PERFORMANCE METRICS
// ══════════════════════════════════════════════════════════════════════════════

$todayMetrics = [
    'revenue' => 0,
    'profit' => 0,
    'transactions' => 0,
    'avg_transaction' => 0,
    'profit_margin' => 0
];

try {
    // Today's sales with COGS calculation
    $todaySales = $db->fetchOne("
        SELECT 
            COALESCE(SUM(s.total_amount), 0) as revenue,
            COUNT(*) as transactions,
            COALESCE(SUM(s.total_amount), 0) / NULLIF(COUNT(*), 0) as avg_transaction
        FROM sales s
        WHERE DATE(s.sale_date) = CURDATE() AND (s.notes IS NULL OR s.notes NOT LIKE '%[VOIDED]%')
    ");

    $todayMetrics['revenue'] = floatval($todaySales['revenue'] ?? 0);
    $todayMetrics['transactions'] = intval($todaySales['transactions'] ?? 0);
    $todayMetrics['avg_transaction'] = floatval($todaySales['avg_transaction'] ?? 0);

    // Calculate today's COGS
    $todayCOGS = $db->fetchOne("
        SELECT COALESCE(SUM(si.quantity * p.average_cost), 0) as total_cogs
        FROM sale_items si
        INNER JOIN sales s ON si.sale_id = s.id
        INNER JOIN products p ON si.product_id = p.id
        WHERE DATE(s.sale_date) = CURDATE() AND (s.notes IS NULL OR s.notes NOT LIKE '%[VOIDED]%')
    ");

    $cogs = floatval($todayCOGS['total_cogs'] ?? 0);
    $todayMetrics['profit'] = $todayMetrics['revenue'] - $cogs;
    $todayMetrics['profit_margin'] = $todayMetrics['revenue'] > 0
        ? ($todayMetrics['profit'] / $todayMetrics['revenue']) * 100
        : 0;
} catch (Exception $e) {
    error_log('Dashboard today metrics error: ' . $e->getMessage());
}

// ══════════════════════════════════════════════════════════════════════════════
// FINANCIAL HEALTH INDICATORS
// ══════════════════════════════════════════════════════════════════════════════

$financialHealth = [
    'receivables' => 0,
    'payables' => 0,
    'stock_value' => 0,
    'customer_deposits' => 0
];

try {
    // Outstanding Receivables (customers owe us)
    $receivables = $db->fetchOne("
        SELECT COALESCE(SUM(ABS(current_balance)), 0) as total
        FROM customers
        WHERE current_balance < 0 AND is_active = 1 AND is_default = 0
    ");
    $financialHealth['receivables'] = floatval($receivables['total'] ?? 0);

    // Outstanding Payables (we owe suppliers)
    $payables = $db->fetchOne("
        SELECT COALESCE(SUM(ABS(current_balance)), 0) as total
        FROM suppliers
        WHERE current_balance < 0 AND is_active = 1
    ");
    $financialHealth['payables'] = floatval($payables['total'] ?? 0);

    // Total Stock Value (at average cost)
    $stockValue = $db->fetchOne("
        SELECT COALESCE(SUM(current_stock * average_cost), 0) as total
        FROM products
        WHERE is_active = 1
    ");
    $financialHealth['stock_value'] = floatval($stockValue['total'] ?? 0);

    // Customer Deposits Held
    $deposits = $db->fetchOne("
        SELECT COALESCE(SUM(current_balance), 0) as total
        FROM customers
        WHERE current_balance > 0 AND is_active = 1 AND is_default = 0
    ");
    $financialHealth['customer_deposits'] = floatval($deposits['total'] ?? 0);
} catch (Exception $e) {
    error_log('Dashboard financial health error: ' . $e->getMessage());
}

// ══════════════════════════════════════════════════════════════════════════════
// CRITICAL ALERTS
// ══════════════════════════════════════════════════════════════════════════════

$alerts = [
    'out_of_stock' => 0,
    'low_stock' => 0,
    'overdue_receivables' => 0,
    'overdue_payables' => 0
];

try {
    // Out of stock items
    $result = $db->fetchOne("
        SELECT COUNT(*) as count FROM products 
        WHERE current_stock = 0 AND is_active = 1
    ");
    $alerts['out_of_stock'] = intval($result['count'] ?? 0);

    // Low stock items
    $result = $db->fetchOne("
        SELECT COUNT(*) as count FROM products 
        WHERE current_stock > 0 AND current_stock <= reorder_level AND is_active = 1
    ");
    $alerts['low_stock'] = intval($result['count'] ?? 0);

    // Overdue receivables (90+ days)
    $result = $db->fetchOne("
        SELECT COUNT(*) as count
        FROM customers c
        WHERE c.current_balance < 0 
        AND c.is_active = 1 
        AND c.is_default = 0
        AND DATEDIFF(CURDATE(), (
            SELECT MIN(DATE(sale_date)) 
            FROM sales 
            WHERE customer_id = c.id 
            AND payment_status IN ('unpaid', 'partial')
            AND (notes IS NULL OR notes NOT LIKE '%[VOIDED]%')
        )) > 90
    ");
    $alerts['overdue_receivables'] = intval($result['count'] ?? 0);

    // Overdue payables (30+ days)
    $result = $db->fetchOne("
        SELECT COUNT(*) as count
        FROM suppliers s
        WHERE s.current_balance < 0 
        AND s.is_active = 1
        AND DATEDIFF(CURDATE(), (
            SELECT MIN(DATE(purchase_date)) 
            FROM purchases 
            WHERE supplier_id = s.id 
            AND payment_status IN ('unpaid', 'partial')
        )) > 30
    ");
    $alerts['overdue_payables'] = intval($result['count'] ?? 0);
} catch (Exception $e) {
    error_log('Dashboard alerts error: ' . $e->getMessage());
}

// ══════════════════════════════════════════════════════════════════════════════
// SALES TREND (LAST 30 DAYS) - WITH PROFIT
// ══════════════════════════════════════════════════════════════════════════════

$salesTrend = $db->fetchAll("
    SELECT 
        DATE(s.sale_date) as date,
        COALESCE(SUM(s.total_amount), 0) as revenue,
        COALESCE(SUM(si.quantity * p.average_cost), 0) as cogs,
        COALESCE(SUM(s.total_amount) - SUM(si.quantity * p.average_cost), 0) as profit
    FROM sales s
    INNER JOIN sale_items si ON s.id = si.sale_id
    INNER JOIN products p ON si.product_id = p.id
    WHERE s.sale_date >= CURDATE() - INTERVAL 29 DAY AND (s.notes IS NULL OR s.notes NOT LIKE '%[VOIDED]%')
    GROUP BY DATE(s.sale_date)
    ORDER BY date ASC
");

// Fill in missing days
$trend = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $found = false;
    foreach ($salesTrend as $row) {
        if ($row['date'] === $date) {
            $trend[] = [
                'date' => date('M j', strtotime($date)),
                'revenue' => floatval($row['revenue']),
                'profit' => floatval($row['profit'])
            ];
            $found = true;
            break;
        }
    }
    if (!$found) {
        $trend[] = [
            'date' => date('M j', strtotime($date)),
            'revenue' => 0,
            'profit' => 0
        ];
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// TOP PERFORMERS (LAST 30 DAYS)
// ══════════════════════════════════════════════════════════════════════════════

// Top 5 Products by Profit
$topProducts = $db->fetchAll("
    SELECT 
        p.name,
        SUM(si.quantity) as total_qty,
        COALESCE(SUM(si.line_total) - SUM(si.quantity * p.average_cost), 0) as profit
    FROM sale_items si
    INNER JOIN sales s ON si.sale_id = s.id
    INNER JOIN products p ON si.product_id = p.id
    WHERE s.sale_date >= CURDATE() - INTERVAL 29 DAY AND (s.notes IS NULL OR s.notes NOT LIKE '%[VOIDED]%')
    GROUP BY si.product_id, p.name
    ORDER BY profit DESC
    LIMIT 5
");

// Sales by Category (Last 30 Days)
$categoryPerformance = $db->fetchAll("
    SELECT 
        COALESCE(c.name, 'Uncategorized') as category,
        COALESCE(SUM(si.line_total), 0) as revenue,
        COALESCE(SUM(si.line_total) - SUM(si.quantity * p.average_cost), 0) as profit
    FROM sale_items si
    INNER JOIN sales s ON si.sale_id = s.id
    INNER JOIN products p ON si.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE s.sale_date >= CURDATE() - INTERVAL 29 DAY AND (s.notes IS NULL OR s.notes NOT LIKE '%[VOIDED]%')
    GROUP BY p.category_id, c.name
    ORDER BY profit DESC
    LIMIT 5
");

// ══════════════════════════════════════════════════════════════════════════════
// RECENT ACTIVITY (LAST 10)
// ══════════════════════════════════════════════════════════════════════════════

$recentSales = $db->fetchAll("
    SELECT 
        s.id,
        s.sale_number,
        s.total_amount,
        s.payment_status,
        s.notes,
        s.created_at,
        c.full_name as customer_name
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    ORDER BY s.created_at DESC
    LIMIT 10
");

$recentPurchases = $db->fetchAll("
    SELECT 
        p.id,
        p.purchase_number,
        p.total_amount,
        p.payment_status,
        p.created_at,
        s.company_name as supplier_name
    FROM purchases p
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    ORDER BY p.created_at DESC
    LIMIT 10
");

// ══════════════════════════════════════════════════════════════════════════════
// LOW STOCK PRODUCTS (TOP 10 CRITICAL)
// ══════════════════════════════════════════════════════════════════════════════

$lowStockProducts = $db->fetchAll("
    SELECT 
        id, 
        name, 
        sku, 
        current_stock, 
        reorder_level,
        unit
    FROM products 
    WHERE current_stock <= reorder_level 
      AND is_active = 1
    ORDER BY 
        CASE WHEN current_stock = 0 THEN 0 ELSE 1 END,
        current_stock ASC
    LIMIT 10
");

// ══════════════════════════════════════════════════════════════════════════════
// THIS MONTH SUMMARY
// ══════════════════════════════════════════════════════════════════════════════

$monthStart = date('Y-m-01');
$today = date('Y-m-d');

$monthSummary = [
    'revenue' => 0,
    'profit' => 0,
    'transactions' => 0,
    'profit_margin' => 0
];

try {
    $monthData = $db->fetchOne("
        SELECT 
            COALESCE(SUM(s.total_amount), 0) as revenue,
            COUNT(*) as transactions
        FROM sales s
        WHERE DATE(s.sale_date) BETWEEN ? AND ? AND (s.notes IS NULL OR s.notes NOT LIKE '%[VOIDED]%')
    ", [$monthStart, $today]);

    $monthSummary['revenue'] = floatval($monthData['revenue'] ?? 0);
    $monthSummary['transactions'] = intval($monthData['transactions'] ?? 0);

    $monthCOGS = $db->fetchOne("
        SELECT COALESCE(SUM(si.quantity * p.average_cost), 0) as total_cogs
        FROM sale_items si
        INNER JOIN sales s ON si.sale_id = s.id
        INNER JOIN products p ON si.product_id = p.id
        WHERE DATE(s.sale_date) BETWEEN ? AND ? AND (s.notes IS NULL OR s.notes NOT LIKE '%[VOIDED]%')
    ", [$monthStart, $today]);

    $cogs = floatval($monthCOGS['total_cogs'] ?? 0);
    $monthSummary['profit'] = $monthSummary['revenue'] - $cogs;
    $monthSummary['profit_margin'] = $monthSummary['revenue'] > 0
        ? ($monthSummary['profit'] / $monthSummary['revenue']) * 100
        : 0;
} catch (Exception $e) {
    error_log('Dashboard month summary error: ' . $e->getMessage());
}

// ══════════════════════════════════════════════════════════════════════════════
// RENDER VIEW
// ══════════════════════════════════════════════════════════════════════════════

$pageTitle = 'Dashboard';
include APP_PATH . '/views/dashboard/index.php';
