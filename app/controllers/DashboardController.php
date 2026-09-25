<?php

/**
 * Dashboard Controller - IMPROVED VERSION
 * Modern business overview with financial insights
 */

if (!defined('APP_START')) {
    die('Direct access not permitted');
}

$db = Database::getInstance();

// Branch visibility for every sales/purchase-derived figure below (revenue,
// profit, trends, top products, recent activity). Empty for company-wide
// users, so their view is unchanged. Customer/supplier balances are left
// company-wide on purpose: those records are shared across branches.
[$salesScopeSql, $salesScopeParams] = branchViewSql('s');
[$purchScopeSql, $purchScopeParams] = branchViewSql('p');

// Line COGS at the cost snapshotted at time of sale (see saleItemCostSql()).
$costLine = saleItemCostSql();

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
        $salesScopeSql
    ", $salesScopeParams);

    $todayMetrics['revenue'] = floatval($todaySales['revenue'] ?? 0);
    $todayMetrics['transactions'] = intval($todaySales['transactions'] ?? 0);
    $todayMetrics['avg_transaction'] = floatval($todaySales['avg_transaction'] ?? 0);

    // Calculate today's COGS
    $todayCOGS = $db->fetchOne("
        SELECT COALESCE(SUM({$costLine}), 0) as total_cogs
        FROM sale_items si
        INNER JOIN sales s ON si.sale_id = s.id
        INNER JOIN products p ON si.product_id = p.id
        WHERE DATE(s.sale_date) = CURDATE() AND (s.notes IS NULL OR s.notes NOT LIKE '%[VOIDED]%')
        $salesScopeSql
    ", $salesScopeParams);

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
    // Outstanding Receivables (customers owe us) — unpaid invoices at the
    // branches this viewer can see, exactly as the Receivables report
    // computes it, so the card and the report it links to always agree.
    // (Customer balances are company-wide and can't be split by branch.)
    [$recScopeSql, $recScopeParams] = branchViewSql('s');
    $receivables = $db->fetchOne("
        SELECT COALESCE(SUM(s.total_amount - COALESCE(s.amount_paid, 0)), 0) as total
        FROM sales s
        JOIN customers c ON c.id = s.customer_id
        WHERE c.is_active = 1 AND c.is_default = 0
          AND s.payment_status IN ('unpaid', 'partial')
          AND (s.total_amount - COALESCE(s.amount_paid, 0)) > 0.01
          AND (s.notes IS NULL OR s.notes NOT LIKE '%[VOIDED]%')
          $recScopeSql
    ", $recScopeParams);
    $financialHealth['receivables'] = floatval($receivables['total'] ?? 0);

    // Outstanding Payables (we owe suppliers) — unpaid purchases at the
    // visible branches, as the Payables report computes it
    [$payScopeSql, $payScopeParams] = branchViewSql('p');
    $payables = $db->fetchOne("
        SELECT COALESCE(SUM(p.amount_due), 0) as total
        FROM purchases p
        JOIN suppliers sp ON sp.id = p.supplier_id
        WHERE sp.is_active = 1
          AND p.payment_status IN ('unpaid', 'partial')
          $payScopeSql
    ", $payScopeParams);
    $financialHealth['payables'] = floatval($payables['total'] ?? 0);

    // Total Stock Value (at average cost) — summed across the branches
    // this viewer can see (all of them for a company-wide user, just
    // their own for a branch-scoped one), via branch_stock rather than
    // products.current_stock directly, so this respects branch visibility
    // the same way every other report in the app does.
    [$dashScopeSql, $dashScopeParams] = branchViewSql('bs');
    $stockValue = $db->fetchOne("
        SELECT COALESCE(SUM(bs.quantity * p.average_cost), 0) as total
        FROM branch_stock bs
        JOIN products p ON p.id = bs.product_id
        WHERE p.is_active = 1
        $dashScopeSql
    ", $dashScopeParams);
    $financialHealth['stock_value'] = floatval($stockValue['total'] ?? 0);

    // Customer Deposits Held — deliberately company-wide: a deposit is the
    // customer's to spend at any branch, so there's no per-branch figure.
    // The card says so when the rest of the dashboard is branch-limited.
    $financialHealth['deposits_shared'] = viewBranchIds() !== null;
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
    'overdue_payables' => 0,
    'expired_batches' => 0,
    'expiring_batches' => 0,
];

try {
    [$alertScopeSql, $alertScopeParams] = branchViewSql('bs');

    // Out of stock items — a product counts as out of stock if the total
    // across this viewer's visible branches is zero.
    $result = $db->fetchOne("
        SELECT COUNT(*) as count FROM (
            SELECT p.id
            FROM products p
            JOIN branch_stock bs ON bs.product_id = p.id
            WHERE p.is_active = 1
            $alertScopeSql
            GROUP BY p.id
            HAVING SUM(bs.quantity) = 0
        ) t
    ", $alertScopeParams);
    $alerts['out_of_stock'] = intval($result['count'] ?? 0);

    // Low stock items
    $result = $db->fetchOne("
        SELECT COUNT(*) as count FROM (
            SELECT p.id
            FROM products p
            JOIN branch_stock bs ON bs.product_id = p.id
            WHERE p.is_active = 1
            $alertScopeSql
            GROUP BY p.id
            HAVING SUM(bs.quantity) > 0 AND SUM(bs.quantity) <= MAX(p.reorder_level)
        ) t
    ", $alertScopeParams);
    $alerts['low_stock'] = intval($result['count'] ?? 0);

    // Overdue receivables (90+ days) — customers with an invoice unpaid
    // for 90+ days at a branch this viewer can see (same basis as the
    // Receivables card above)
    [$odScopeSql, $odScopeParams] = branchViewSql('s');
    $result = $db->fetchOne("
        SELECT COUNT(DISTINCT s.customer_id) as count
        FROM sales s
        JOIN customers c ON c.id = s.customer_id
        WHERE c.is_active = 1 AND c.is_default = 0
          AND s.payment_status IN ('unpaid', 'partial')
          AND (s.total_amount - COALESCE(s.amount_paid, 0)) > 0.01
          AND (s.notes IS NULL OR s.notes NOT LIKE '%[VOIDED]%')
          AND DATEDIFF(CURDATE(), DATE(s.sale_date)) > 90
          $odScopeSql
    ", $odScopeParams);
    $alerts['overdue_receivables'] = intval($result['count'] ?? 0);

    // Overdue payables (30+ days) — suppliers with a purchase unpaid for
    // 30+ days at a visible branch
    [$opScopeSql, $opScopeParams] = branchViewSql('p');
    $result = $db->fetchOne("
        SELECT COUNT(DISTINCT p.supplier_id) as count
        FROM purchases p
        JOIN suppliers sp ON sp.id = p.supplier_id
        WHERE sp.is_active = 1
          AND p.payment_status IN ('unpaid', 'partial')
          AND p.amount_due > 0.01
          AND DATEDIFF(CURDATE(), DATE(p.purchase_date)) > 30
          $opScopeSql
    ", $opScopeParams);
    $alerts['overdue_payables'] = intval($result['count'] ?? 0);

    // Expired / soon-to-expire batches at visible branches (same basis
    // as the expiry report)
    if (expiryTrackingEnabled()) {
        $expirySummary = expiryReport($db, 'expired')['summary'];
        $alerts['expired_batches']  = intval($expirySummary['expired_batches'] ?? 0);
        $alerts['expiring_batches'] = intval($expirySummary['soon_batches'] ?? 0);
    }
} catch (Exception $e) {
    error_log('Dashboard alerts error: ' . $e->getMessage());
}

// ══════════════════════════════════════════════════════════════════════════════
// SECURITY ALERTS (high-risk audit_log changes, since this user's last visit
// to /audit — see AuditController::listAuditLog(), which stamps
// last_security_alert_seen_at on every visit). Deliberately narrow: role
// permission losses on users.manage/roles.manage, role deletion, sale voids
// and edits, and 30%+ selling-price drops (that threshold is enforced at
// write time in ProductController::updateProduct(), not here).
// ══════════════════════════════════════════════════════════════════════════════

$securityAlerts = [];
if (can('audit.view')) {
    try {
        $lastSeen = currentUser()['last_security_alert_seen_at'] ?? null;
        // >=, not > : both this column and audit_log.created_at are plain
        // TIMESTAMP (1-second resolution, no fractional seconds), so an
        // action taken right after visiting /audit can land in the very
        // same second as the seen-stamp — a strict > would silently drop
        // it from ever alerting. Confirmed live: voiding a sale immediately
        // after visiting /audit produced identical created_at and
        // last_security_alert_seen_at values down to the second.
        $sinceSql = $lastSeen ? "AND created_at >= ?" : "";
        $sinceParams = $lastSeen ? [$lastSeen] : [];

        [$scopeSql, $scopeParams] = branchViewSql('');
        $rows = $db->fetchAll("
            SELECT * FROM audit_log
            WHERE action IN ('role.permissions_changed', 'role.delete', 'sale.void', 'sale.edit', 'product.price_change')
            $sinceSql
            $scopeSql
            ORDER BY created_at DESC
            LIMIT 20
        ", array_merge($sinceParams, $scopeParams));

        foreach ($rows as $row) {
            $details = $row['details'] ? json_decode($row['details'], true) : [];

            if ($row['action'] === 'role.permissions_changed') {
                $removed = $details['removed'] ?? [];
                if (!in_array('users.manage', $removed, true) && !in_array('roles.manage', $removed, true)) {
                    continue; // a permission change that didn't touch the two sensitive ones
                }
                $lost = array_values(array_intersect($removed, ['users.manage', 'roles.manage']));
                $securityAlerts[] = [
                    'summary' => "Role \"" . ($details['name'] ?? '?') . "\" lost " . implode(' and ', $lost),
                    'row'     => $row,
                ];
            } elseif ($row['action'] === 'role.delete') {
                $securityAlerts[] = [
                    'summary' => "Role \"" . ($details['name'] ?? '?') . "\" was deleted",
                    'row'     => $row,
                ];
            } elseif ($row['action'] === 'sale.void') {
                $securityAlerts[] = [
                    'summary' => "Sale #" . ($details['sale_number'] ?? $row['entity_id']) . " voided (" . formatMoney($details['total_amount'] ?? 0) . ")",
                    'row'     => $row,
                ];
            } elseif ($row['action'] === 'sale.edit') {
                $securityAlerts[] = [
                    'summary' => "Sale #" . ($details['sale_number'] ?? $row['entity_id']) . " edited: " . ($details['changes'] ?? ''),
                    'row'     => $row,
                ];
            } elseif ($row['action'] === 'product.price_change') {
                $securityAlerts[] = [
                    'summary' => "\"" . ($details['name'] ?? '?') . "\" price dropped " . ($details['drop_pct'] ?? '?') . "% ("
                        . formatMoney($details['old_price'] ?? 0) . " → " . formatMoney($details['new_price'] ?? 0) . ")",
                    'row'     => $row,
                ];
            }
        }
    } catch (Exception $e) {
        error_log('Dashboard security alerts error: ' . $e->getMessage());
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// SALES TREND (LAST 30 DAYS) - WITH PROFIT
// ══════════════════════════════════════════════════════════════════════════════

// Line revenue net of the whole-cart discount (see saleItemNetSql()). Also
// avoids repeating each sale's total once per line item when joined below.
$netLine = saleItemNetSql();

$salesTrend = $db->fetchAll("
    SELECT 
        DATE(s.sale_date) as date,
        COALESCE(SUM({$netLine}), 0) as revenue,
        COALESCE(SUM({$costLine}), 0) as cogs,
        COALESCE(SUM({$netLine}) - SUM({$costLine}), 0) as profit
    FROM sales s
    INNER JOIN sale_items si ON s.id = si.sale_id
    INNER JOIN products p ON si.product_id = p.id
    WHERE s.sale_date >= CURDATE() - INTERVAL 29 DAY AND (s.notes IS NULL OR s.notes NOT LIKE '%[VOIDED]%')
    $salesScopeSql
    GROUP BY DATE(s.sale_date)
    ORDER BY date ASC
", $salesScopeParams);

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
        COALESCE(SUM({$netLine}) - SUM({$costLine}), 0) as profit
    FROM sale_items si
    INNER JOIN sales s ON si.sale_id = s.id
    INNER JOIN products p ON si.product_id = p.id
    WHERE s.sale_date >= CURDATE() - INTERVAL 29 DAY AND (s.notes IS NULL OR s.notes NOT LIKE '%[VOIDED]%')
    $salesScopeSql
    GROUP BY si.product_id, p.name
    ORDER BY profit DESC
    LIMIT 5
", $salesScopeParams);

// Sales by Category (Last 30 Days)
$categoryPerformance = $db->fetchAll("
    SELECT 
        COALESCE(c.name, 'Uncategorized') as category,
        COALESCE(SUM({$netLine}), 0) as revenue,
        COALESCE(SUM({$netLine}) - SUM({$costLine}), 0) as profit
    FROM sale_items si
    INNER JOIN sales s ON si.sale_id = s.id
    INNER JOIN products p ON si.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE s.sale_date >= CURDATE() - INTERVAL 29 DAY AND (s.notes IS NULL OR s.notes NOT LIKE '%[VOIDED]%')
    $salesScopeSql
    GROUP BY p.category_id, c.name
    ORDER BY profit DESC
    LIMIT 5
", $salesScopeParams);

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
    WHERE 1=1
    $salesScopeSql
    ORDER BY s.created_at DESC
    LIMIT 10
", $salesScopeParams);

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
    WHERE 1=1
    $purchScopeSql
    ORDER BY p.created_at DESC
    LIMIT 10
", $purchScopeParams);

// ══════════════════════════════════════════════════════════════════════════════
// LOW STOCK PRODUCTS (TOP 10 CRITICAL)
// ══════════════════════════════════════════════════════════════════════════════

[$topLowScopeSql, $topLowScopeParams] = branchViewSql('bs');
$lowStockProducts = $db->fetchAll("
    SELECT 
        p.id, 
        p.name, 
        p.sku, 
        COALESCE(SUM(bs.quantity), 0) as current_stock, 
        p.reorder_level,
        p.unit
    FROM products p
    JOIN branch_stock bs ON bs.product_id = p.id
    WHERE p.is_active = 1
    $topLowScopeSql
    GROUP BY p.id, p.name, p.sku, p.reorder_level, p.unit
    HAVING current_stock <= p.reorder_level
    ORDER BY 
        CASE WHEN current_stock = 0 THEN 0 ELSE 1 END,
        current_stock ASC
    LIMIT 10
", $topLowScopeParams);

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
        $salesScopeSql
    ", array_merge([$monthStart, $today], $salesScopeParams));

    $monthSummary['revenue'] = floatval($monthData['revenue'] ?? 0);
    $monthSummary['transactions'] = intval($monthData['transactions'] ?? 0);

    $monthCOGS = $db->fetchOne("
        SELECT COALESCE(SUM({$costLine}), 0) as total_cogs
        FROM sale_items si
        INNER JOIN sales s ON si.sale_id = s.id
        INNER JOIN products p ON si.product_id = p.id
        WHERE DATE(s.sale_date) BETWEEN ? AND ? AND (s.notes IS NULL OR s.notes NOT LIKE '%[VOIDED]%')
        $salesScopeSql
    ", array_merge([$monthStart, $today], $salesScopeParams));

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
