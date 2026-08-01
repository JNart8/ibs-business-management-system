<?php

/**
 * Transactions Controller
 * Shows all customer transactions across all customers
 * 
 * Add to public/index.php routing:
 * elseif (strpos($path, '/transactions') === 0) {
 *     require APP_PATH . '/controllers/TransactionsController.php';
 * }
 */

if (!defined('APP_START')) {
    die('Direct access not permitted');
}

$db = Database::getInstance();

// Parse URL
$segments = array_values(array_filter(explode('/', trim($path, '/'))));
$action = $segments[1] ?? 'index';

// Route to appropriate action
switch ($action) {
    case 'index':
    default:
        listTransactions($db);
        break;
}

/**
 * List all customer transactions
 */
function listTransactions($db)
{
    // Get filter parameters
    $customerId = $_GET['customer'] ?? null;
    $type = $_GET['type'] ?? null;
    $dateFrom = $_GET['date_from'] ?? null;
    $dateTo = $_GET['date_to'] ?? null;
    $search = $_GET['search'] ?? null;

    // Build WHERE clause
    $where = ["c.is_default = 0"]; // Exclude walk-in customer
    $params = [];

    if ($customerId) {
        $where[] = "ct.customer_id = ?";
        $params[] = $customerId;
    }

    if ($type) {
        $where[] = "ct.transaction_type = ?";
        $params[] = $type;
    }

    if ($dateFrom) {
        $where[] = "DATE(ct.created_at) >= ?";
        $params[] = $dateFrom;
    }

    if ($dateTo) {
        $where[] = "DATE(ct.created_at) <= ?";
        $params[] = $dateTo;
    }

    if ($search) {
        $where[] = "(c.full_name LIKE ? OR c.phone LIKE ? OR ct.notes LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $whereClause = implode(' AND ', $where);

    // Get transactions with pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $perPage = 50;
    $offset = ($page - 1) * $perPage;

    // Count total
    $total = $db->fetchOne("
        SELECT COUNT(*) as count
        FROM customer_transactions ct
        INNER JOIN customers c ON ct.customer_id = c.id
        WHERE $whereClause
    ", $params)['count'];

    // Get transactions
    $transactions = $db->fetchAll("
        SELECT 
            ct.*,
            c.full_name as customer_name,
            c.phone as customer_phone,
            c.customer_code,
            u.username as recorded_by
        FROM customer_transactions ct
        INNER JOIN customers c ON ct.customer_id = c.id
        LEFT JOIN users u ON ct.user_id = u.id
        WHERE $whereClause
        ORDER BY ct.created_at DESC, ct.id DESC
        LIMIT $perPage OFFSET $offset
    ", $params);

    // Get summary stats
    $summary = $db->fetchOne("
        SELECT 
            COUNT(*) as total_transactions,
            SUM(CASE WHEN transaction_type = 'deposit' THEN amount ELSE 0 END) as total_deposits,
            SUM(CASE WHEN transaction_type = 'payment' THEN amount ELSE 0 END) as total_payments,
            SUM(CASE WHEN transaction_type = 'sale' THEN ABS(amount) ELSE 0 END) as total_credit_sales,
            SUM(CASE WHEN transaction_type = 'refund' THEN amount ELSE 0 END) as total_refunds
        FROM customer_transactions ct
        INNER JOIN customers c ON ct.customer_id = c.id
        WHERE $whereClause
    ", $params);

    // Get customers for filter dropdown
    $customers = $db->fetchAll("
        SELECT id, full_name, customer_code
        FROM customers
        WHERE is_active = 1 AND is_default = 0
        ORDER BY full_name
    ");

    // Calculate pagination
    $totalPages = ceil($total / $perPage);

    $pageTitle = 'All Customer Transactions';
    include APP_PATH . '/views/transactions/index.php';
}
