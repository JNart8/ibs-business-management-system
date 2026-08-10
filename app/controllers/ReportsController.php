<?php

/**
 * Reports Controller
 * Handles sales reports, analytics, and data exports
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

// Determine action
if (count($segments) <= 1) {
    $action = 'sales'; // Default to sales report
} else {
    $action = $segments[1]; // reports/sales, reports/stock-valuation, etc.
}

// Route
switch ($action) {
    case 'sales':
        salesReport($db);
        break;
    case 'stock-valuation':
        stockValuationReport($db);
        break;
    case 'receivables':
        receivablesReport($db);
        break;
    case 'payables':
        payablesReport($db);
        break;
    case 'products':
        productsReport($db);
        break;
    case 'customers':
        customersReport($db);
        break;
    case 'inventory':
        inventoryReport($db);
        break;
    case 'profit-loss':
        profitLossReport($db);
        break;
    case 'top-selling':
        topSellingReport($db);
        break;
    case 'low-stock':
        lowStockReport($db);
        break;
    case 'dead-stock':
        deadStockReport($db);
        break;
    case 'profit-margin':
        profitMarginReport($db);
        break;
    default:
        // Default to sales report
        salesReport($db);
        break;
}

// ============================================================
// SALES REPORT
// ============================================================

function salesReport($db)
{
    // Get filter parameters
    $period     = $_GET['period']     ?? 'this_month';
    $dateFrom   = $_GET['date_from']  ?? '';
    $dateTo     = $_GET['date_to']    ?? '';
    $payMethod  = $_GET['payment']    ?? '';
    $payStatus  = $_GET['status']     ?? '';
    $cashier    = $_GET['cashier']    ?? '';

    // Calculate date range based on period
    $dates = calculateDateRange($period, $dateFrom, $dateTo);
    $dateFrom = $dates['from'];
    $dateTo   = $dates['to'];

    // Build WHERE clause
    $params = [];
    $where  = "WHERE DATE(s.sale_date) BETWEEN ? AND ?";
    $where .= " AND (s.notes IS NULL OR s.notes NOT LIKE '%[VOIDED]%')";
    $params[] = $dateFrom;
    $params[] = $dateTo;

    // Branch visibility — applies to every sub-query below, since they
    // all reuse this same $where/$params.
    [$scopeSql, $scopeParams] = branchScopeSql('s');
    $where .= $scopeSql;
    $params = array_merge($params, $scopeParams);

    if (!empty($payMethod)) {
        $where .= " AND s.payment_method = ?";
        $params[] = $payMethod;
    }
    if (!empty($payStatus)) {
        $where .= " AND s.payment_status = ?";
        $params[] = $payStatus;
    }
    if (!empty($cashier)) {
        $where .= " AND s.user_id = ?";
        $params[] = $cashier;
    }

    // ── Summary Statistics ──────────────────────────────────
    $summary = $db->fetchOne("
        SELECT
            COUNT(*)                                AS total_sales,
            COALESCE(SUM(s.total_amount), 0)        AS total_revenue,
            COALESCE(SUM(s.subtotal), 0)            AS total_subtotal,
            COALESCE(SUM(s.discount_amount), 0)     AS total_discounts,
            COALESCE(SUM(s.amount_paid), 0)         AS total_paid,
            COALESCE(SUM(s.amount_due), 0)          AS total_due,
            COALESCE(AVG(s.total_amount), 0)        AS avg_order_value,
            COALESCE(SUM(si.quantity), 0)           AS total_items_sold
        FROM sales s
        LEFT JOIN sale_items si ON s.id = si.sale_id
        $where
    ", $params);

    // ── Sales by Payment Method ─────────────────────────────
    $paymentBreakdown = $db->fetchAll("
        SELECT
            s.payment_method,
            COUNT(*)                          AS sale_count,
            COALESCE(SUM(s.total_amount), 0)  AS revenue
        FROM sales s
        $where
        GROUP BY s.payment_method
        ORDER BY revenue DESC
    ", $params);

    // ── Sales by Payment Status ─────────────────────────────
    $statusBreakdown = $db->fetchAll("
        SELECT
            s.payment_status,
            COUNT(*)                          AS sale_count,
            COALESCE(SUM(s.total_amount), 0)  AS revenue
        FROM sales s
        $where
        GROUP BY s.payment_status
        ORDER BY revenue DESC
    ", $params);

    // ── Sales by Cashier ────────────────────────────────────
    $cashierBreakdown = $db->fetchAll("
        SELECT
            COALESCE(u.full_name, 'Unknown') AS cashier_name,
            COUNT(*)                         AS sale_count,
            COALESCE(SUM(s.total_amount), 0) AS revenue
        FROM sales s
        LEFT JOIN users u ON s.user_id = u.id
        $where
        GROUP BY s.user_id, u.full_name
        ORDER BY revenue DESC
    ", $params);

    // ── Top Products by Quantity ────────────────────────────
    $topProducts = $db->fetchAll("
        SELECT
            si.product_name,
            SUM(si.quantity)                        AS total_quantity,
            COALESCE(SUM(si.line_total), 0)         AS total_revenue,
            COUNT(DISTINCT si.sale_id)              AS sale_count
        FROM sale_items si
        INNER JOIN sales s ON si.sale_id = s.id
        $where
        GROUP BY si.product_id, si.product_name
        ORDER BY total_quantity DESC
        LIMIT 10
    ", $params);

    // ── Top Products by Revenue ─────────────────────────────
    $topRevenue = $db->fetchAll("
        SELECT
            si.product_name,
            SUM(si.quantity)                        AS total_quantity,
            COALESCE(SUM(si.line_total), 0)         AS total_revenue,
            COUNT(DISTINCT si.sale_id)              AS sale_count
        FROM sale_items si
        INNER JOIN sales s ON si.sale_id = s.id
        $where
        GROUP BY si.product_id, si.product_name
        ORDER BY total_revenue DESC
        LIMIT 10
    ", $params);

    // ── Top Customers ───────────────────────────────────────
    $topCustomers = $db->fetchAll("
        SELECT
            c.full_name,
            c.phone,
            COUNT(*)                                AS purchase_count,
            COALESCE(SUM(s.total_amount), 0)        AS total_spent,
            COALESCE(AVG(s.total_amount), 0)        AS avg_order_value
        FROM sales s
        INNER JOIN customers c ON s.customer_id = c.id
        $where AND c.is_default = 0
        GROUP BY s.customer_id, c.full_name, c.phone
        ORDER BY total_spent DESC
        LIMIT 10
    ", $params);

    // ── Daily Sales Trend ───────────────────────────────────
    $dailyTrend = $db->fetchAll("
        SELECT
            DATE(s.sale_date)                       AS sale_date,
            COUNT(*)                                AS sale_count,
            COALESCE(SUM(s.total_amount), 0)        AS revenue
        FROM sales s
        $where
        GROUP BY DATE(s.sale_date)
        ORDER BY sale_date ASC
    ", $params);

    // ── Recent Sales (for detailed table) ──────────────────
    $recentSales = $db->fetchAll("
        SELECT
            s.*,
            c.full_name         AS customer_name,
            c.is_default        AS is_walkin,
            u.full_name         AS cashier_name
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN users u     ON s.user_id     = u.id
        $where
        ORDER BY s.sale_date DESC
        LIMIT 100
    ", $params);

    // ── Get all cashiers for filter ────────────────────────
    $cashiers = $db->fetchAll("
        SELECT DISTINCT u.id, u.full_name
        FROM sales s
        INNER JOIN users u ON s.user_id = u.id
        WHERE u.id IS NOT NULL
        ORDER BY u.full_name ASC
    ");

    $pageTitle = 'Sales Report';
    include APP_PATH . '/views/reports/sales.php';
}

// ============================================================
// STOCK VALUATION REPORT
// ============================================================

function stockValuationReport($db)
{
    // Get filter parameters
    $category   = $_GET['category']   ?? '';
    $supplier   = $_GET['supplier']   ?? '';
    $lowStock   = $_GET['low_stock']  ?? '';
    $sortBy     = $_GET['sort_by']    ?? 'value_desc';

    // Build WHERE clause
    $params = [];
    $where  = "WHERE p.is_active = 1";

    if (!empty($category)) {
        $where .= " AND p.category_id = ?";
        $params[] = $category;
    }
    if (!empty($supplier)) {
        $where .= " AND p.supplier_id = ?";
        $params[] = $supplier;
    }

    // Low-stock filtering must happen AFTER branches are summed (HAVING),
    // not before (WHERE) — a branch-scoped user with 2+ visible branches
    // would otherwise get this evaluated per-branch-row instead of against
    // the actual combined total, filtering incorrectly.
    $havingLowStock = ($lowStock === '1') ? "HAVING COALESCE(SUM(bs.quantity), 0) <= p.reorder_level" : "";

    // Determine sort order
    $orderBy = match ($sortBy) {
        'value_asc'  => 'stock_value ASC',
        'name'       => 'p.name ASC',
        'stock_asc'  => 'current_stock ASC',
        'stock_desc' => 'current_stock DESC',
        default      => 'stock_value DESC'
    };

    // Branch visibility — all branches for a company-wide viewer, just
    // their own for a branch-scoped one. Unlike receivables/payables,
    // there's no separate "true total" this could be a confusing partial
    // slice of — the sum across visible branches genuinely is the answer
    // to "how much stock do I have", so no partial-view warning is needed
    // here, just a plain "showing: X" label for clarity.
    [$scopeSql, $scopeParams] = branchScopeSql('bs');

    // ── Get products with stock value ──────────────────────
    $products = $db->fetchAll("
        SELECT
            p.id,
            p.sku,
            p.name,
            p.unit,
            COALESCE(SUM(bs.quantity), 0) AS current_stock,
            p.reorder_level,
            p.cost_price,
            p.average_cost,
            p.selling_price,
            p.last_purchase_date,
            c.name AS category_name,
            s.company_name AS supplier_name,
            (COALESCE(SUM(bs.quantity), 0) * p.average_cost) AS stock_value,
            ((p.selling_price - p.average_cost) * COALESCE(SUM(bs.quantity), 0)) AS potential_profit,
            CASE 
                WHEN p.average_cost > 0 
                THEN ((p.selling_price - p.average_cost) / p.average_cost) * 100 
                ELSE 0 
            END AS profit_margin_pct
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        LEFT JOIN branch_stock bs ON bs.product_id = p.id $scopeSql
        $where
        GROUP BY p.id, p.sku, p.name, p.unit, p.reorder_level, p.cost_price,
                 p.average_cost, p.selling_price, p.last_purchase_date, c.name, s.company_name
        $havingLowStock
        ORDER BY $orderBy
    ", array_merge($scopeParams, $params));

    // ── Summary Statistics ──────────────────────────────────
    $summary = $db->fetchOne("
        SELECT
            COUNT(DISTINCT p.id) AS total_products,
            COALESCE(SUM(bs.quantity), 0) AS total_units,
            COALESCE(SUM(bs.quantity * p.average_cost), 0) AS total_value,
            COALESCE(SUM((p.selling_price - p.average_cost) * bs.quantity), 0) AS total_potential_profit,
            COUNT(DISTINCT CASE WHEN bs.quantity <= p.reorder_level THEN p.id END) AS low_stock_count,
            COUNT(DISTINCT CASE WHEN bs.quantity = 0 THEN p.id END) AS out_of_stock_count
        FROM products p
        LEFT JOIN branch_stock bs ON bs.product_id = p.id $scopeSql
        $where
    ", array_merge($scopeParams, $params));

    // ── Category Breakdown ──────────────────────────────────
    $categoryBreakdown = $db->fetchAll("
        SELECT
            COALESCE(c.name, 'Uncategorized') AS category_name,
            COUNT(DISTINCT p.id) AS product_count,
            COALESCE(SUM(bs.quantity), 0) AS total_stock,
            COALESCE(SUM(bs.quantity * p.average_cost), 0) AS stock_value
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN branch_stock bs ON bs.product_id = p.id $scopeSql
        $where
        GROUP BY p.category_id, c.name
        ORDER BY stock_value DESC
    ", array_merge($scopeParams, $params));

    // ── Top 10 Most Valuable Items ─────────────────────────
    // (this also fixes a pre-existing bug: this query previously used
    // $where, which can contain category/supplier ? placeholders, but
    // never actually passed $params — filtering by category or supplier
    // would have thrown a param-count mismatch)
    $topValuable = $db->fetchAll("
        SELECT
            p.name,
            COALESCE(SUM(bs.quantity), 0) AS current_stock,
            p.average_cost,
            (COALESCE(SUM(bs.quantity), 0) * p.average_cost) AS stock_value
        FROM products p
        LEFT JOIN branch_stock bs ON bs.product_id = p.id $scopeSql
        $where
        GROUP BY p.id, p.name, p.average_cost
        $havingLowStock
        ORDER BY stock_value DESC
        LIMIT 10
    ", array_merge($scopeParams, $params));

    // ── Get categories and suppliers for filters ───────────
    $categories = $db->fetchAll("
        SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC
    ");

    $suppliers = $db->fetchAll("
        SELECT id, company_name FROM suppliers WHERE is_active = 1 ORDER BY company_name ASC
    ");

    $pageTitle = 'Stock Valuation Report';
    $viewingScopeLabel = hasMultiBranch() ? (isCompanyWide() ? 'All Branches' : activeBranchName()) : null;
    include APP_PATH . '/views/reports/stock_valuation.php';
}

// ============================================================
// OUTSTANDING RECEIVABLES REPORT
// ============================================================

function receivablesReport($db)
{
    // Get filter parameters
    $aging      = $_GET['aging']      ?? '';
    $sortBy     = $_GET['sort_by']    ?? 'amount_desc';

    // Define risk calculation as a closure
    $calculateRiskLevel = function ($customer) {
        $riskScore = 0;

        // Days outstanding
        if ($customer['days_outstanding'] > 90) $riskScore += 50;
        elseif ($customer['days_outstanding'] > 60) $riskScore += 30;
        elseif ($customer['days_outstanding'] > 30) $riskScore += 15;

        // Amount owed relative to credit limit
        if ($customer['credit_limit'] > 0) {
            $utilization = ($customer['total_owed'] / $customer['credit_limit']) * 100;
            if ($utilization > 90) $riskScore += 30;
            elseif ($utilization > 75) $riskScore += 15;
        }

        // Multiple invoices
        if ($customer['unpaid_invoices'] > 5) $riskScore += 20;
        elseif ($customer['unpaid_invoices'] > 3) $riskScore += 10;

        // Determine risk level
        if ($riskScore >= 70) return ['level' => 'High', 'color' => 'bg-red-100 text-red-700', 'icon' => '🔴'];
        if ($riskScore >= 40) return ['level' => 'Medium', 'color' => 'bg-orange-100 text-orange-700', 'icon' => '🟠'];
        return ['level' => 'Low', 'color' => 'bg-green-100 text-green-700', 'icon' => '🟢'];
    };

    // Get all unpaid invoices with aging calculation
    // Using correct column names from the schema: sale_number, not invoice_no
    //
    // Branch-scoped by s.branch_id (which invoice originated where) — this
    // deliberately makes total_owed/aging a *partial* picture for a
    // branch-scoped user, while c.current_balance stays the customer's
    // real company-wide balance (customers are shared across branches by
    // design, see ARCHITECTURE.md §5.3). The view flags this explicitly
    // rather than letting a partial number pass as the whole picture.
    [$scopeSql, $scopeParams] = branchScopeSql('s');
    $unpaidInvoices = $db->fetchAll("
        SELECT 
            c.id AS customer_id,
            c.full_name,
            c.phone,
            c.email,
            c.current_balance,
            c.credit_limit,
            s.id AS invoice_id,
            s.sale_number AS invoice_no,  -- Using sale_number as invoice number
            s.sale_date,
            s.total_amount,
            s.amount_paid,
            (s.total_amount - COALESCE(s.amount_paid, 0)) AS amount_due,
            DATEDIFF(CURDATE(), s.sale_date) AS days_outstanding,
            s.payment_status
        FROM customers c
        INNER JOIN sales s ON c.id = s.customer_id
        WHERE c.is_active = 1 
            AND c.is_default = 0
            AND s.payment_status IN ('unpaid', 'partial')
            AND (s.total_amount - COALESCE(s.amount_paid, 0)) > 0.01
            $scopeSql
        ORDER BY c.id, s.sale_date ASC
    ", $scopeParams);

    // Group by customer and calculate accurate aging
    $customers = [];
    $agingTotals = [
        'current_0_30' => 0,
        'overdue_31_60' => 0,
        'overdue_61_90' => 0,
        'overdue_90_plus' => 0
    ];

    foreach ($unpaidInvoices as $invoice) {
        $customerId = $invoice['customer_id'];
        $days = $invoice['days_outstanding'];
        $amountDue = $invoice['amount_due'];

        // Initialize customer if not exists
        if (!isset($customers[$customerId])) {
            $customers[$customerId] = [
                'id' => $invoice['customer_id'],
                'full_name' => $invoice['full_name'],
                'phone' => $invoice['phone'],
                'email' => $invoice['email'],
                'credit_limit' => $invoice['credit_limit'],
                'current_balance' => $invoice['current_balance'],
                'total_owed' => 0,
                'unpaid_invoices' => 0,
                'oldest_debt_date' => null,
                'newest_debt_date' => null,
                'invoices' => [],
                // Per-customer aging breakdown
                'aging_current' => 0,
                'aging_31_60' => 0,
                'aging_61_90' => 0,
                'aging_90_plus' => 0
            ];
        }

        // Add to customer totals
        $customers[$customerId]['total_owed'] += $amountDue;
        $customers[$customerId]['unpaid_invoices']++;

        // Track oldest and newest debt dates
        if (
            !$customers[$customerId]['oldest_debt_date'] ||
            $invoice['sale_date'] < $customers[$customerId]['oldest_debt_date']
        ) {
            $customers[$customerId]['oldest_debt_date'] = $invoice['sale_date'];
        }

        if (
            !$customers[$customerId]['newest_debt_date'] ||
            $invoice['sale_date'] > $customers[$customerId]['newest_debt_date']
        ) {
            $customers[$customerId]['newest_debt_date'] = $invoice['sale_date'];
        }

        // Add to customer's aging breakdown by invoice amount
        if ($days <= 30) {
            $customers[$customerId]['aging_current'] += $amountDue;
        } elseif ($days <= 60) {
            $customers[$customerId]['aging_31_60'] += $amountDue;
        } elseif ($days <= 90) {
            $customers[$customerId]['aging_61_90'] += $amountDue;
        } else {
            $customers[$customerId]['aging_90_plus'] += $amountDue;
        }

        // Add to global aging totals
        if ($days <= 30) {
            $agingTotals['current_0_30'] += $amountDue;
        } elseif ($days <= 60) {
            $agingTotals['overdue_31_60'] += $amountDue;
        } elseif ($days <= 90) {
            $agingTotals['overdue_61_90'] += $amountDue;
        } else {
            $agingTotals['overdue_90_plus'] += $amountDue;
        }

        // Store individual invoice details
        $customers[$customerId]['invoices'][] = [
            'id' => $invoice['invoice_id'],
            'invoice_no' => $invoice['invoice_no'], // This is sale_number
            'date' => $invoice['sale_date'],
            'days' => $days,
            'amount' => $amountDue,
            'total' => $invoice['total_amount'],
            'paid' => $invoice['amount_paid'] ?? 0,
            'status' => $invoice['payment_status']
        ];
    }

    // Calculate days outstanding and risk level for each customer
    foreach ($customers as &$customer) {
        if ($customer['oldest_debt_date']) {
            $oldestDate = new DateTime($customer['oldest_debt_date']);
            $today = new DateTime();
            $customer['days_outstanding'] = $oldestDate->diff($today)->days;
        } else {
            $customer['days_outstanding'] = 0;
        }

        // Determine status based on oldest debt
        $days = $customer['days_outstanding'];
        if ($days > 90) {
            $customer['status'] = 'Critical';
            $customer['status_color'] = 'bg-red-100 text-red-700';
        } elseif ($days > 60) {
            $customer['status'] = 'Overdue';
            $customer['status_color'] = 'bg-orange-100 text-orange-700';
        } elseif ($days > 30) {
            $customer['status'] = 'Warning';
            $customer['status_color'] = 'bg-yellow-100 text-yellow-700';
        } else {
            $customer['status'] = 'Current';
            $customer['status_color'] = 'bg-green-100 text-green-700';
        }

        // Calculate risk level using the closure
        $customer['risk_level'] = $calculateRiskLevel($customer);

        // Add credit utilization
        if ($customer['credit_limit'] > 0) {
            $utilization = ($customer['total_owed'] / $customer['credit_limit']) * 100;
            $customer['credit_utilization'] = round($utilization, 1);
            $customer['credit_warning'] = $utilization > 90 ? 'danger' : ($utilization > 75 ? 'warning' : 'normal');
        } else {
            $customer['credit_utilization'] = 0;
            $customer['credit_warning'] = 'normal';
        }
    }

    // Apply aging filter
    if (!empty($aging)) {
        $customers = array_filter($customers, function ($customer) use ($aging) {
            $days = $customer['days_outstanding'];
            switch ($aging) {
                case 'current':
                    return $days <= 30;
                case 'overdue_30':
                    return $days >= 31 && $days <= 60;
                case 'overdue_60':
                    return $days >= 61 && $days <= 90;
                case 'overdue_90':
                    return $days > 90;
                default:
                    return true;
            }
        });
    }

    // Sort customers
    $customers = array_values($customers); // Re-index after filter

    usort($customers, function ($a, $b) use ($sortBy) {
        switch ($sortBy) {
            case 'amount_asc':
                return $a['total_owed'] <=> $b['total_owed'];
            case 'amount_desc':
                return $b['total_owed'] <=> $a['total_owed'];
            case 'name':
                return strcmp($a['full_name'], $b['full_name']);
            case 'days_desc':
                return $b['days_outstanding'] <=> $a['days_outstanding'];
            default:
                return $b['total_owed'] <=> $a['total_owed'];
        }
    });

    // Calculate summary statistics
    $totalOwed = array_sum(array_column($customers, 'total_owed'));
    $totalCustomers = count($customers);

    $summary = [
        'total_customers' => $totalCustomers,
        'total_owed' => $totalOwed,
        'avg_owed' => $totalCustomers > 0 ? $totalOwed / $totalCustomers : 0,
        'severely_overdue_count' => count(array_filter($customers, function ($c) {
            return $c['days_outstanding'] > 90;
        })),
        'total_unpaid_invoices' => array_sum(array_column($customers, 'unpaid_invoices'))
    ];

    // Calculate percentage of total for aging analysis
    $agingAnalysis = [
        'current_0_30' => $agingTotals['current_0_30'],
        'overdue_31_60' => $agingTotals['overdue_31_60'],
        'overdue_61_90' => $agingTotals['overdue_61_90'],
        'overdue_90_plus' => $agingTotals['overdue_90_plus']
    ];

    // Add percentages
    foreach ($agingAnalysis as $key => $value) {
        $agingAnalysis[$key . '_percent'] = $totalOwed > 0
            ? round(($value / $totalOwed) * 100, 1)
            : 0;
    }

    $pageTitle = 'Outstanding Receivables';
    $isPartialView = hasMultiBranch() && !isCompanyWide();
    include APP_PATH . '/views/reports/receivables.php';
}

// ============================================================
// OUTSTANDING PAYABLES REPORT
// ============================================================

function payablesReport($db)
{
    // Get filter parameters
    $sortBy = $_GET['sort_by'] ?? 'amount_desc';

    // ── Get suppliers we owe money ──────────────────────────
    // amount_owed is deliberately computed from SUM(purchases.amount_due)
    // rather than read from suppliers.current_balance — the same approach
    // as receivablesReport, and for the same reason: current_balance is a
    // company-wide figure (suppliers, like customers, are shared across
    // branches by design — see ARCHITECTURE.md §5.3), so it can't be used
    // as-is for a branch-scoped user without silently showing them a
    // number bigger than "what my branch owes." Summing scoped invoices
    // instead makes this a genuine partial view rather than a mislabeled
    // company-wide one.
    [$scopeSql, $scopeParams] = branchScopeSql('');
    $suppliers = $db->fetchAll("
        SELECT
            s.id,
            s.company_name,
            s.contact_name,
            s.phone,
            s.email,
            s.current_balance,
            (
                SELECT COALESCE(SUM(amount_due), 0)
                FROM purchases
                WHERE supplier_id = s.id
                AND payment_status IN ('unpaid', 'partial')
                $scopeSql
            ) AS amount_owed,
            (
                SELECT MIN(DATE(purchase_date))
                FROM purchases
                WHERE supplier_id = s.id 
                AND payment_status IN ('unpaid', 'partial')
                $scopeSql
            ) AS oldest_debt_date,
            (
                SELECT COUNT(*)
                FROM purchases
                WHERE supplier_id = s.id 
                AND payment_status IN ('unpaid', 'partial')
                $scopeSql
            ) AS unpaid_invoices,
            DATEDIFF(
                CURDATE(),
                (
                    SELECT MIN(DATE(purchase_date))
                    FROM purchases
                    WHERE supplier_id = s.id 
                    AND payment_status IN ('unpaid', 'partial')
                    $scopeSql
                )
            ) AS days_outstanding
        FROM suppliers s
        WHERE s.is_active = 1
        HAVING amount_owed > 0.01
        ORDER BY " . ($sortBy === 'amount_asc' ? 'amount_owed ASC'
                : ($sortBy === 'name' ? 's.company_name ASC'
                : ($sortBy === 'days_desc' ? 'days_outstanding DESC'
                : 'amount_owed DESC'))) . "
    ", array_merge($scopeParams, $scopeParams, $scopeParams, $scopeParams));

    // ── Summary statistics — computed in PHP from the already-scoped
    // $suppliers list above, so it can't drift from what's actually shown
    // (mirrors how receivablesReport builds its summary from $customers).
    $totalOwed = array_sum(array_column($suppliers, 'amount_owed'));
    $totalSuppliers = count($suppliers);
    $overdueCount = count(array_filter($suppliers, fn($s) => ($s['days_outstanding'] ?? 0) > 30));

    $summary = [
        'total_suppliers' => $totalSuppliers,
        'total_owed'      => $totalOwed,
        'avg_owed'        => $totalSuppliers > 0 ? $totalOwed / $totalSuppliers : 0,
        'overdue_count'   => $overdueCount,
    ];

    // ── Get unpaid purchases for detail ────────────────────
    [$detailScopeSql, $detailScopeParams] = branchScopeSql('p');
    $unpaidPurchases = $db->fetchAll("
        SELECT
            p.id,
            p.purchase_number,
            p.purchase_date,
            p.total_amount,
            p.amount_paid,
            p.amount_due,
            p.payment_status,
            s.company_name AS supplier_name,
            DATEDIFF(CURDATE(), DATE(p.purchase_date)) AS days_old
        FROM purchases p
        INNER JOIN suppliers s ON p.supplier_id = s.id
        WHERE p.payment_status IN ('unpaid', 'partial')
        $detailScopeSql
        ORDER BY p.purchase_date ASC
        LIMIT 50
    ", $detailScopeParams);

    $pageTitle = 'Outstanding Payables';
    $isPartialView = hasMultiBranch() && !isCompanyWide();
    include APP_PATH . '/views/reports/payables.php';
}


/**
 * PHASE 2 REPORT FUNCTIONS
 */

// ============================================================
// PROFIT & LOSS STATEMENT
// ============================================================

function profitLossReport($db)
{
    // Get filter parameters
    $period = $_GET['period'] ?? 'this_month';
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo = $_GET['date_to'] ?? '';

    // Calculate date range
    $dates = calculateDateRange($period, $dateFrom, $dateTo);
    $dateFrom = $dates['from'];
    $dateTo = $dates['to'];

    // ── Revenue (Sales) ─────────────────────────────────────
    [$scopeSql, $scopeParams] = branchScopeSql('');
    $revenue = $db->fetchOne("
        SELECT
            COALESCE(SUM(total_amount), 0) AS total_sales,
            COALESCE(SUM(discount_amount), 0) AS total_discounts,
            COALESCE(SUM(total_amount - discount_amount), 0) AS net_sales
        FROM sales
        WHERE DATE(sale_date) BETWEEN ? AND ?
          AND (notes IS NULL OR notes NOT LIKE '%[VOIDED]%')
        $scopeSql
    ", array_merge([$dateFrom, $dateTo], $scopeParams));

    // ── Cost of Goods Sold (COGS) ──────────────────────────
    [$scopeSqlS, $scopeParamsS] = branchScopeSql('s');
    $cogs = $db->fetchOne("
        SELECT
            COALESCE(SUM(si.quantity * p.average_cost), 0) AS total_cogs
        FROM sale_items si
        INNER JOIN sales s ON si.sale_id = s.id
        INNER JOIN products p ON si.product_id = p.id
        WHERE DATE(s.sale_date) BETWEEN ? AND ?
          AND (s.notes IS NULL OR s.notes NOT LIKE '%[VOIDED]%')
        $scopeSqlS
    ", array_merge([$dateFrom, $dateTo], $scopeParamsS));

    // ── Gross Profit ────────────────────────────────────────
    $grossProfit = $revenue['net_sales'] - $cogs['total_cogs'];
    $grossMargin = $revenue['net_sales'] > 0 ? ($grossProfit / $revenue['net_sales']) * 100 : 0;

    // ── Operating Expenses (if tracked) ─────────────────────
    // Note: You'll need an expenses table for this
    // For now, we'll set to 0 or get from a simple expenses table if it exists
    $expenses = $db->fetchOne("
        SELECT COALESCE(SUM(amount), 0) AS total_expenses
        FROM expenses
        WHERE DATE(expense_date) BETWEEN ? AND ?
        $scopeSql
    ", array_merge([$dateFrom, $dateTo], $scopeParams)) ?? ['total_expenses' => 0];

    // ── Net Profit ──────────────────────────────────────────
    $netProfit = $grossProfit - $expenses['total_expenses'];
    $netMargin = $revenue['net_sales'] > 0 ? ($netProfit / $revenue['net_sales']) * 100 : 0;

    // ── Sales by Category ───────────────────────────────────
    $salesByCategory = $db->fetchAll("
        SELECT
            COALESCE(c.name, 'Uncategorized') AS category_name,
            COUNT(DISTINCT si.sale_id) AS sale_count,
            COALESCE(SUM(si.quantity), 0) AS total_quantity,
            COALESCE(SUM(si.line_total), 0) AS revenue,
            COALESCE(SUM(si.quantity * p.average_cost), 0) AS cogs,
            COALESCE(SUM(si.line_total) - SUM(si.quantity * p.average_cost), 0) AS profit
        FROM sale_items si
        INNER JOIN sales s ON si.sale_id = s.id
        INNER JOIN products p ON si.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE DATE(s.sale_date) BETWEEN ? AND ?
          AND (s.notes IS NULL OR s.notes NOT LIKE '%[VOIDED]%')
        $scopeSqlS
        GROUP BY p.category_id, c.name
        ORDER BY revenue DESC
    ", array_merge([$dateFrom, $dateTo], $scopeParamsS));

    // ── Daily Profit Trend ──────────────────────────────────
    $dailyProfit = $db->fetchAll("
        SELECT
            DATE(s.sale_date) AS sale_date,
            COALESCE(SUM(s.total_amount), 0) AS revenue,
            COALESCE(SUM(si.quantity * p.average_cost), 0) AS cogs,
            COALESCE(SUM(s.total_amount) - SUM(si.quantity * p.average_cost), 0) AS profit
        FROM sales s
        INNER JOIN sale_items si ON s.id = si.sale_id
        INNER JOIN products p ON si.product_id = p.id
        WHERE DATE(s.sale_date) BETWEEN ? AND ?
          AND (s.notes IS NULL OR s.notes NOT LIKE '%[VOIDED]%')
        $scopeSqlS
        GROUP BY DATE(s.sale_date)
        ORDER BY sale_date ASC
    ", array_merge([$dateFrom, $dateTo], $scopeParamsS));

    // ── Period Comparison (vs previous period) ──────────────
    $periodLength = (strtotime($dateTo) - strtotime($dateFrom)) / 86400;
    $prevDateTo = date('Y-m-d', strtotime($dateFrom . ' -1 day'));
    $prevDateFrom = date('Y-m-d', strtotime($prevDateTo . ' -' . $periodLength . ' days'));

    $previousRevenue = $db->fetchOne("
        SELECT COALESCE(SUM(total_amount), 0) AS total_sales
        FROM sales
        WHERE DATE(sale_date) BETWEEN ? AND ?
          AND (notes IS NULL OR notes NOT LIKE '%[VOIDED]%')
        $scopeSql
    ", array_merge([$prevDateFrom, $prevDateTo], $scopeParams));

    $previousCogs = $db->fetchOne("
        SELECT COALESCE(SUM(si.quantity * p.average_cost), 0) AS total_cogs
        FROM sale_items si
        INNER JOIN sales s ON si.sale_id = s.id
        INNER JOIN products p ON si.product_id = p.id
        WHERE DATE(s.sale_date) BETWEEN ? AND ?
          AND (s.notes IS NULL OR s.notes NOT LIKE '%[VOIDED]%')
        $scopeSqlS
    ", array_merge([$prevDateFrom, $prevDateTo], $scopeParamsS));

    $previousProfit = $previousRevenue['total_sales'] - $previousCogs['total_cogs'];
    $profitChange = $previousProfit > 0 ? (($grossProfit - $previousProfit) / $previousProfit) * 100 : 0;

    $pageTitle = 'Profit & Loss Statement';
    include APP_PATH . '/views/reports/profit_loss.php';
}

// ============================================================
// TOP SELLING PRODUCTS REPORT
// ============================================================

function topSellingReport($db)
{
    // Get filter parameters
    $period = $_GET['period'] ?? 'this_month';
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo = $_GET['date_to'] ?? '';
    $category = $_GET['category'] ?? '';
    $limit = $_GET['limit'] ?? 50;

    // Calculate date range
    $dates = calculateDateRange($period, $dateFrom, $dateTo);
    $dateFrom = $dates['from'];
    $dateTo = $dates['to'];

    // Build WHERE clause
    $params = [$dateFrom, $dateTo];
    $whereCategory = '';
    if (!empty($category)) {
        $whereCategory = " AND p.category_id = ?";
        $params[] = $category;
    }

    // Branch visibility — appended to $whereCategory since all 3 queries
    // below reuse it, and $params is the shared base each one extends.
    [$scopeSql, $scopeParams] = branchScopeSql('s');
    $whereCategory .= $scopeSql;
    $params = array_merge($params, $scopeParams);

    // ── Top Products by Quantity ────────────────────────────
    $topByQuantity = $db->fetchAll("
        SELECT
            p.id,
            p.name,
            p.sku,
            p.unit,
            p.current_stock,
            p.selling_price,
            p.average_cost,
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
          AND (s.notes IS NULL OR s.notes NOT LIKE '%[VOIDED]%')
        $whereCategory
        GROUP BY si.product_id, p.id, p.name, p.sku, p.unit, p.current_stock, p.selling_price, p.average_cost, c.name
        ORDER BY total_quantity DESC
        LIMIT ?
    ", array_merge($params, [(int)$limit]));

    // ── Top Products by Revenue ─────────────────────────────
    $topByRevenue = $db->fetchAll("
        SELECT
            p.id,
            p.name,
            p.sku,
            c.name AS category_name,
            SUM(si.quantity) AS total_quantity,
            COALESCE(SUM(si.line_total), 0) AS total_revenue,
            COALESCE(SUM(si.line_total) - SUM(si.quantity * p.average_cost), 0) AS total_profit
        FROM sale_items si
        INNER JOIN sales s ON si.sale_id = s.id
        INNER JOIN products p ON si.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE DATE(s.sale_date) BETWEEN ? AND ?
          AND (s.notes IS NULL OR s.notes NOT LIKE '%[VOIDED]%')
        $whereCategory
        GROUP BY si.product_id, p.id, p.name, p.sku, c.name
        ORDER BY total_revenue DESC
        LIMIT ?
    ", array_merge($params, [(int)$limit]));

    // ── Top Products by Profit ──────────────────────────────
    $topByProfit = $db->fetchAll("
        SELECT
            p.id,
            p.name,
            p.sku,
            c.name AS category_name,
            SUM(si.quantity) AS total_quantity,
            COALESCE(SUM(si.line_total), 0) AS total_revenue,
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
          AND (s.notes IS NULL OR s.notes NOT LIKE '%[VOIDED]%')
        $whereCategory
        GROUP BY si.product_id, p.id, p.name, p.sku, c.name
        ORDER BY total_profit DESC
        LIMIT ?
    ", array_merge($params, [(int)$limit]));

    // ── Summary Stats ───────────────────────────────────────
    $summary = $db->fetchOne("
        SELECT
            COUNT(DISTINCT si.product_id) AS products_sold,
            COALESCE(SUM(si.quantity), 0) AS total_units,
            COALESCE(SUM(si.line_total), 0) AS total_revenue,
            COALESCE(SUM(si.line_total) - SUM(si.quantity * p.average_cost), 0) AS total_profit
        FROM sale_items si
        INNER JOIN sales s ON si.sale_id = s.id
        INNER JOIN products p ON si.product_id = p.id
        WHERE DATE(s.sale_date) BETWEEN ? AND ?
          AND (s.notes IS NULL OR s.notes NOT LIKE '%[VOIDED]%')
        $whereCategory
    ", $params);

    // ── Categories for filter ───────────────────────────────
    $categories = $db->fetchAll("
        SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC
    ");

    $pageTitle = 'Top Selling Products';
    include APP_PATH . '/views/reports/top_selling.php';
}

// ============================================================
// LOW STOCK ALERT REPORT
// ============================================================

function lowStockReport($db)
{
    // Get filter parameters
    $category = $_GET['category'] ?? '';
    $severity = $_GET['severity'] ?? ''; // critical, warning, all

    // Build WHERE clause
    $params = [];
    $where = "WHERE p.is_active = 1";

    if (!empty($category)) {
        $where .= " AND p.category_id = ?";
        $params[] = $category;
    }

    // Severity is a stock-level condition, evaluated post-aggregation
    // (HAVING) since stock is now summed across potentially several
    // visible branches per product — same reasoning as the stock
    // valuation report's low-stock filter.
    if ($severity === 'critical') {
        $having = "HAVING COALESCE(SUM(bs.quantity), 0) = 0";
    } elseif ($severity === 'warning') {
        $having = "HAVING COALESCE(SUM(bs.quantity), 0) > 0 AND COALESCE(SUM(bs.quantity), 0) <= p.reorder_level";
    } else {
        // All low stock items (out of stock OR at/below reorder level)
        $having = "HAVING COALESCE(SUM(bs.quantity), 0) <= p.reorder_level";
    }

    // Branch visibility — matches every other report.
    [$scopeSql, $scopeParams] = branchScopeSql('bs');

    // ── Low Stock Products ──────────────────────────────────
    $products = $db->fetchAll("
        SELECT
            p.id,
            p.sku,
            p.name,
            p.unit,
            COALESCE(SUM(bs.quantity), 0) AS current_stock,
            p.reorder_level,
            p.average_cost,
            p.selling_price,
            p.last_purchase_date,
            c.name AS category_name,
            s.company_name AS supplier_name,
            s.phone AS supplier_phone,
            -- Suggested reorder quantity (2x reorder level minus current stock)
            GREATEST((p.reorder_level * 2) - COALESCE(SUM(bs.quantity), 0), p.reorder_level) AS suggested_order_qty
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        LEFT JOIN branch_stock bs ON bs.product_id = p.id $scopeSql
        $where
        GROUP BY p.id, p.sku, p.name, p.unit, p.reorder_level, p.average_cost,
                 p.selling_price, p.last_purchase_date, c.name, s.company_name, s.phone
        $having
        ORDER BY 
            CASE WHEN COALESCE(SUM(bs.quantity), 0) = 0 THEN 0 ELSE 1 END,
            current_stock ASC
    ", array_merge($scopeParams, $params));

    // Calculate days until stockout for each product (after fetching).
    // Sales velocity is scoped to the same visible branches as the stock
    // figure above — comparing branch-specific stock against company-wide
    // sales velocity would give a misleading projection.
    [$velocityScopeSql, $velocityScopeParams] = branchScopeSql('sa');
    foreach ($products as &$product) {
        $avgDaily = $db->fetchOne("
            SELECT AVG(daily_qty) as avg_daily
            FROM (
                SELECT SUM(si.quantity) AS daily_qty
                FROM sale_items si
                INNER JOIN sales sa ON si.sale_id = sa.id
                WHERE si.product_id = ?
                AND DATE(sa.sale_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                $velocityScopeSql
                GROUP BY DATE(sa.sale_date)
            ) AS daily_sales
        ", array_merge([$product['id']], $velocityScopeParams));

        // Calculate days until stockout
        if ($avgDaily && $avgDaily['avg_daily'] > 0 && $product['current_stock'] > 0) {
            $product['days_until_stockout'] = floor($product['current_stock'] / $avgDaily['avg_daily']);
        } else {
            $product['days_until_stockout'] = null;
        }
    }
    unset($product); // Break reference

    // ── Summary Stats ───────────────────────────────────────
    $summary = $db->fetchOne("
        SELECT
            COUNT(DISTINCT p.id) AS total_items,
            COUNT(DISTINCT CASE WHEN bs.quantity = 0 THEN p.id END) AS out_of_stock,
            COUNT(DISTINCT CASE WHEN bs.quantity > 0 AND bs.quantity <= p.reorder_level THEN p.id END) AS low_stock,
            COALESCE(SUM(DISTINCT p.reorder_level * p.average_cost), 0) AS estimated_reorder_cost
        FROM products p
        LEFT JOIN branch_stock bs ON bs.product_id = p.id $scopeSql
        WHERE p.is_active = 1 AND COALESCE(bs.quantity, 0) <= p.reorder_level
    ", $scopeParams);

    // ── Categories for filter ───────────────────────────────
    $categories = $db->fetchAll("
        SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC
    ");

    $pageTitle = 'Low Stock Alert';
    $viewingScopeLabel = hasMultiBranch() ? (isCompanyWide() ? 'All Branches' : activeBranchName()) : null;
    include APP_PATH . '/views/reports/low_stock.php';
}

// ============================================================
// DEAD STOCK REPORT
// ============================================================

function deadStockReport($db)
{
    // Get filter parameters
    $period = $_GET['period'] ?? 90; // Days without sales
    $category = $_GET['category'] ?? '';
    $minValue = $_GET['min_value'] ?? 0; // Minimum stock value to show

    // Branch visibility applies to BOTH halves of this report: the stock
    // figure itself (branch_stock) and the sales-history subqueries (a
    // product might be genuinely dead at Branch 2 while selling fine at
    // Branch 1 — that's exactly the case this report exists to catch, so
    // both need to agree on the same branch scope, not mix a
    // branch-specific stock figure with company-wide sales history).
    [$stockScopeSql, $stockScopeParams] = branchScopeSql('bs');
    [$salesScopeSql, $salesScopeParams] = branchScopeSql('s');

    // Build WHERE clause (category only — stock-derived conditions move to HAVING)
    $whereCategory = '';
    $categoryParams = [];
    if (!empty($category)) {
        $whereCategory = " AND p.category_id = ?";
        $categoryParams[] = $category;
    }

    // ── Dead Stock Products ─────────────────────────────────
    // last_sale_date/days_since_last_sale/total_sold correlated subqueries
    // and the stock_value/current_stock HAVING conditions below all
    // reference SELECT-list aliases — a MySQL-specific HAVING extension,
    // used here to avoid repeating the branch-scoped stock expression yet
    // again.
    $products = $db->fetchAll("
        SELECT
            p.id,
            p.sku,
            p.name,
            p.unit,
            COALESCE(SUM(bs.quantity), 0) AS current_stock,
            p.average_cost,
            p.selling_price,
            (COALESCE(SUM(bs.quantity), 0) * p.average_cost) AS stock_value,
            (COALESCE(SUM(bs.quantity), 0) * p.selling_price) AS potential_revenue,
            c.name AS category_name,
            p.last_purchase_date,
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
            ) AS days_since_last_sale,
            (
                SELECT COALESCE(SUM(si.quantity), 0)
                FROM sale_items si
                INNER JOIN sales s ON si.sale_id = s.id
                WHERE si.product_id = p.id
                $salesScopeSql
            ) AS total_sold
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN branch_stock bs ON bs.product_id = p.id $stockScopeSql
        WHERE p.is_active = 1
        $whereCategory
        GROUP BY p.id, p.sku, p.name, p.unit, p.average_cost, p.selling_price,
                 c.name, p.last_purchase_date
        HAVING current_stock > 0
           AND (last_sale_date IS NULL OR days_since_last_sale >= ?)
           AND stock_value >= ?
        ORDER BY stock_value DESC
    ", array_merge(
        $salesScopeParams, $salesScopeParams, $salesScopeParams,
        $stockScopeParams, $categoryParams,
        [(int)$period, (float)$minValue]
    ));

    // ── Summary Stats ───────────────────────────────────────
    // Computed in PHP from the already-scoped/filtered $products list
    // above, so it can never drift from what's actually shown — same
    // approach used by receivablesReport()/payablesReport().
    $summary = [
        'total_items'        => count($products),
        'total_units'        => array_sum(array_column($products, 'current_stock')),
        'total_value'        => array_sum(array_column($products, 'stock_value')),
        'potential_revenue'  => array_sum(array_column($products, 'potential_revenue')),
    ];

    // ── Categories for filter ───────────────────────────────
    $categories = $db->fetchAll("
        SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC
    ");

    $pageTitle = 'Dead Stock Report';
    $viewingScopeLabel = hasMultiBranch() ? (isCompanyWide() ? 'All Branches' : activeBranchName()) : null;
    include APP_PATH . '/views/reports/dead_stock.php';
}

// ============================================================
// PROFIT MARGIN ANALYSIS REPORT
// ============================================================

function profitMarginReport($db)
{
    // Get filter parameters
    $category = $_GET['category'] ?? '';
    $sortBy = $_GET['sort_by'] ?? 'margin_desc'; // margin_desc, margin_asc, profit_desc, revenue_desc

    // Build WHERE clause (stock-derived "has stock" condition moves to HAVING)
    $params = [];
    $where = "WHERE p.is_active = 1";

    if (!empty($category)) {
        $where .= " AND p.category_id = ?";
        $params[] = $category;
    }

    // Determine sort order
    $orderBy = match ($sortBy) {
        'margin_asc' => 'profit_margin_pct ASC',
        'profit_desc' => 'profit_per_unit DESC',
        'revenue_desc' => 'potential_revenue DESC',
        'stock_desc' => 'current_stock DESC',
        default => 'profit_margin_pct DESC'
    };

    // Margin itself (average_cost, selling_price) is correctly company-wide
    // — cost accounting doesn't differ per branch, same reasoning as the
    // weighted-average-cost calculation in Purchase/Sale controllers. Only
    // the stock quantity and 30-day sales velocity below are branch-scoped.
    [$stockScopeSql, $stockScopeParams] = branchScopeSql('bs');
    [$salesScopeSql, $salesScopeParams] = branchScopeSql('s');

    // ── Products with Margin Analysis ───────────────────────
    $products = $db->fetchAll("
        SELECT
            p.id,
            p.sku,
            p.name,
            p.unit,
            COALESCE(SUM(bs.quantity), 0) AS current_stock,
            p.average_cost,
            p.selling_price,
            (p.selling_price - p.average_cost) AS profit_per_unit,
            CASE WHEN p.average_cost > 0 
                THEN ((p.selling_price - p.average_cost) / p.average_cost) * 100
                ELSE 0 
            END AS profit_margin_pct,
            CASE WHEN p.selling_price > 0
                THEN ((p.selling_price - p.average_cost) / p.selling_price) * 100
                ELSE 0
            END AS markup_pct,
            (COALESCE(SUM(bs.quantity), 0) * p.average_cost) AS stock_value,
            (COALESCE(SUM(bs.quantity), 0) * p.selling_price) AS potential_revenue,
            ((p.selling_price - p.average_cost) * COALESCE(SUM(bs.quantity), 0)) AS potential_profit,
            c.name AS category_name,
            -- Last 30 days sales (excluding voided), branch-scoped to match current_stock
            (
                SELECT COALESCE(SUM(si.quantity), 0)
                FROM sale_items si
                INNER JOIN sales s ON si.sale_id = s.id
                WHERE si.product_id = p.id
                AND DATE(s.sale_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                AND (s.notes IS NULL OR s.notes NOT LIKE '%[VOIDED]%')
                $salesScopeSql
            ) AS qty_sold_30days,
            (
                SELECT COALESCE(SUM(si.line_total), 0)
                FROM sale_items si
                INNER JOIN sales s ON si.sale_id = s.id
                WHERE si.product_id = p.id
                AND DATE(s.sale_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                AND (s.notes IS NULL OR s.notes NOT LIKE '%[VOIDED]%')
                $salesScopeSql
            ) AS revenue_30days
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN branch_stock bs ON bs.product_id = p.id $stockScopeSql
        $where
        GROUP BY p.id, p.sku, p.name, p.unit, p.average_cost, p.selling_price, c.name
        HAVING current_stock > 0
        ORDER BY $orderBy
    ", array_merge($salesScopeParams, $salesScopeParams, $stockScopeParams, $params));

    // ── Summary Stats ───────────────────────────────────────
    // Computed in PHP from the already-scoped $products list above, so
    // it can never drift from what's shown — same approach as
    // deadStockReport()/receivablesReport().
    $margins = array_column($products, 'profit_margin_pct');
    $summary = [
        'total_products'         => count($products),
        'avg_margin'             => count($margins) > 0 ? array_sum($margins) / count($margins) : 0,
        'total_stock_value'      => array_sum(array_column($products, 'stock_value')),
        'total_potential_profit' => array_sum(array_column($products, 'potential_profit')),
        'low_margin_count'       => count(array_filter($products, fn($p) => $p['profit_margin_pct'] < 10)),
        'high_margin_count'      => count(array_filter($products, fn($p) => $p['profit_margin_pct'] >= 30)),
    ];

    // ── Margin Distribution ─────────────────────────────────
    // Also computed from $products in PHP, for the same reason.
    $bucketLabels = ['Negative', '0-10%', '10-20%', '20-30%', '30-50%', '50%+'];
    $buckets = array_fill_keys($bucketLabels, ['product_count' => 0, 'stock_value' => 0]);
    foreach ($products as $p) {
        $pct = $p['profit_margin_pct'];
        $label = $pct < 0 ? 'Negative' : ($pct < 10 ? '0-10%' : ($pct < 20 ? '10-20%' : ($pct < 30 ? '20-30%' : ($pct < 50 ? '30-50%' : '50%+'))));
        $buckets[$label]['product_count']++;
        $buckets[$label]['stock_value'] += $p['stock_value'];
    }
    $marginDistribution = [];
    foreach ($bucketLabels as $label) {
        if ($buckets[$label]['product_count'] > 0) {
            $marginDistribution[] = array_merge(['margin_range' => $label], $buckets[$label]);
        }
    }

    // ── Categories for filter ───────────────────────────────
    $categories = $db->fetchAll("
        SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC
    ");

    $pageTitle = 'Profit Margin Analysis';
    $viewingScopeLabel = hasMultiBranch() ? (isCompanyWide() ? 'All Branches' : activeBranchName()) : null;
    include APP_PATH . '/views/reports/profit_margin.php';
}

// ============================================================
// PRODUCTS REPORT (Placeholder)
// ============================================================

function productsReport($db)
{
    echo "<h1>Products Report - Coming Soon</h1>";
    echo "<a href='" . BASE_URL . "/reports'>← Back to Reports</a>";
}

// ============================================================
// CUSTOMERS REPORT (Placeholder)
// ============================================================

function customersReport($db)
{
    echo "<h1>Customers Report - Coming Soon</h1>";
    echo "<a href='" . BASE_URL . "/reports'>← Back to Reports</a>";
}

// ============================================================
// INVENTORY REPORT (Placeholder)
// ============================================================

function inventoryReport($db)
{
    echo "<h1>Inventory Report - Coming Soon</h1>";
    echo "<a href='" . BASE_URL . "/reports'>← Back to Reports</a>";
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================

/**
 * Calculate date range based on period
 */
function calculateDateRange($period, $customFrom = '', $customTo = '')
{
    $today = date('Y-m-d');
    $from  = $today;
    $to    = $today;

    switch ($period) {
        case 'today':
            $from = $today;
            $to   = $today;
            break;

        case 'yesterday':
            $from = date('Y-m-d', strtotime('-1 day'));
            $to   = date('Y-m-d', strtotime('-1 day'));
            break;

        case 'this_week':
            $from = date('Y-m-d', strtotime('monday this week'));
            $to   = $today;
            break;

        case 'last_week':
            $from = date('Y-m-d', strtotime('monday last week'));
            $to   = date('Y-m-d', strtotime('sunday last week'));
            break;

        case 'this_month':
            $from = date('Y-m-01');
            $to   = $today;
            break;

        case 'last_month':
            $from = date('Y-m-01', strtotime('first day of last month'));
            $to   = date('Y-m-t', strtotime('last day of last month'));
            break;

        case 'this_quarter':
            $month = date('n');
            $year  = date('Y');
            $quarter = ceil($month / 3);
            $from = date('Y-m-01', strtotime("$year-" . (($quarter - 1) * 3 + 1) . "-01"));
            $to   = $today;
            break;

        case 'this_year':
            $from = date('Y-01-01');
            $to   = $today;
            break;

        case 'last_year':
            $from = date('Y-01-01', strtotime('last year'));
            $to   = date('Y-12-31', strtotime('last year'));
            break;

        case 'custom':
            if (!empty($customFrom)) $from = $customFrom;
            if (!empty($customTo))   $to   = $customTo;
            break;

        default:
            $from = date('Y-m-01'); // Default to this month
            $to   = $today;
    }

    return ['from' => $from, 'to' => $to];
}
