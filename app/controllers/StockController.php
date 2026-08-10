<?php

/**
 * Stock Controller
 * Handles stock in, stock out, adjustments, movements history & alerts
 */

if (!defined('APP_START')) {
    die('Direct access not permitted');
}

$db = Database::getInstance();

// ── Use $path already parsed by the router ───────────────────
$segments = array_values(array_filter(explode('/', trim($path, '/'))));
$action   = $segments[1] ?? 'index';
$id       = $segments[2] ?? null;

// Route
switch ($action) {
    case 'index':
        stockDashboard($db);
        break;

    case 'in':
        $productParam = $_GET['product'] ?? $_GET['product_id'] ?? '';
        $redirectUrl = BASE_URL . '/purchases/create';
        if (!empty($productParam)) {
            $redirectUrl .= '?product=' . urlencode($productParam);
        }
        redirect($redirectUrl);
        break;

    case 'out':
        $method === 'POST'
            ? processStockOut($db)
            : showStockOutForm($db);
        break;

    case 'adjust':
        if (!$id) redirect(BASE_URL . '/stock', 'error', 'Product ID required');
        $method === 'POST'
            ? processAdjustment($db, $id)
            : showAdjustForm($db, $id);
        break;

    case 'movements':
        listMovements($db);
        break;

    case 'alerts':
        lowStockAlerts($db);
        break;

    case 'product':
        // View movements for a single product
        if (!$id) redirect(BASE_URL . '/stock', 'error', 'Product ID required');
        productMovements($db, $id);
        break;

    default:
        http_response_code(404);
        echo "<h1>Action not found</h1>";
        echo "<a href='" . BASE_URL . "/stock'>← Back to Stock</a>";
        break;
}

// ============================================================
// FUNCTIONS
// ============================================================

/**
 * Stock Dashboard - overview of all stock levels
 */
function stockDashboard($db)
{
    $search     = trim($_GET['search'] ?? '');
    $filter     = $_GET['filter'] ?? 'all';
    $category   = intval($_GET['category'] ?? 0);
    $page       = max(1, (int)($_GET['page'] ?? 1));
    $limit      = ITEMS_PER_PAGE;
    $offset     = ($page - 1) * $limit;

    $branchId = activeBranchId();
    $whereParams = [];
    $where  = "WHERE p.is_active = 1";

    if (!empty($search)) {
        $where   .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)";
        $term     = "%$search%";
        $whereParams[] = $term;
        $whereParams[] = $term;
        $whereParams[] = $term;
    }

    if ($category > 0) {
        $where   .= " AND p.category_id = ?";
        $whereParams[] = $category;
    }

    switch ($filter) {
        case 'low':
            $where .= " AND COALESCE(bs.quantity, 0) <= p.reorder_level AND COALESCE(bs.quantity, 0) > 0";
            break;
        case 'out':
            $where .= " AND COALESCE(bs.quantity, 0) = 0";
            break;
        case 'ok':
            $where .= " AND COALESCE(bs.quantity, 0) > p.reorder_level";
            break;
    }

    // Total count — one ? for the branch_stock JOIN, then $whereParams
    $total = $db->fetchOne(
        "SELECT COUNT(*) as cnt FROM products p LEFT JOIN branch_stock bs ON bs.product_id = p.id AND bs.branch_id = ? $where",
        array_merge([$branchId], $whereParams)
    );
    $totalCount = intval($total['cnt'] ?? 0);
    $totalPages = max(1, ceil($totalCount / $limit));

    // Products with stock info — current_stock here is THIS branch's
    // quantity (bs.quantity), not the company-wide total, since this page
    // drives stock-in/out/adjust actions that operate on the active branch.
    // Param order must match the ? order in the string exactly: the
    // correlated subquery's sm.branch_id comes first (it's in the SELECT
    // list, which appears before FROM in the query text), then the
    // branch_stock JOIN, then $whereParams, then LIMIT/OFFSET.
    $products = $db->fetchAll("
        SELECT
            p.id,
            p.sku,
            p.name,
            p.barcode,
            COALESCE(bs.quantity, 0)            AS current_stock,
            p.reorder_level,
            p.cost_price,
            p.selling_price,
            p.unit,
            c.name                              AS category_name,
            (COALESCE(bs.quantity, 0) * p.cost_price) AS stock_value,
            CASE
                WHEN COALESCE(bs.quantity, 0) = 0               THEN 'out'
                WHEN COALESCE(bs.quantity, 0) <= p.reorder_level THEN 'low'
                ELSE 'ok'
            END                                 AS stock_status,
            (SELECT sm.created_at
             FROM stock_movements sm
             WHERE sm.product_id = p.id AND sm.branch_id = ?
             ORDER BY sm.created_at DESC LIMIT 1) AS last_movement
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN branch_stock bs ON bs.product_id = p.id AND bs.branch_id = ?
        $where
        ORDER BY COALESCE(bs.quantity, 0) ASC, p.name ASC
        LIMIT ? OFFSET ?
    ", array_merge([$branchId, $branchId], $whereParams, [$limit, $offset]));

    // Summary stats — also this branch's numbers, not company-wide
    $stats = $db->fetchOne("
        SELECT
            COUNT(*)                                                                          AS total_products,
            COALESCE(SUM(COALESCE(bs.quantity, 0) * p.cost_price), 0)                          AS total_value,
            SUM(CASE WHEN COALESCE(bs.quantity, 0) = 0 THEN 1 ELSE 0 END)                      AS out_of_stock,
            SUM(CASE WHEN COALESCE(bs.quantity, 0) <= p.reorder_level
                      AND COALESCE(bs.quantity, 0) > 0 THEN 1 ELSE 0 END)                      AS low_stock,
            SUM(CASE WHEN COALESCE(bs.quantity, 0) > p.reorder_level THEN 1 ELSE 0 END)        AS healthy_stock,
            COALESCE(SUM(COALESCE(bs.quantity, 0)), 0)                                         AS total_units
        FROM products p
        LEFT JOIN branch_stock bs ON bs.product_id = p.id AND bs.branch_id = ?
        WHERE p.is_active = 1
    ", [$branchId]);

    // Categories for filter dropdown
    $categories = $db->fetchAll(
        "SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC"
    );

    $pageTitle = 'Stock Management';
    $viewingBranchName = hasMultiBranch() ? activeBranchName() : null;
    include APP_PATH . '/views/stock/index.php';
}

/**
 * Show Stock In form
 */
function showStockInForm($db)
{
    $preProduct = null;

    // Restore from old input after a validation error
    $oldProductId = intval($_SESSION['old_input']['product_id'] ?? 0);

    if ($oldProductId > 0) {
        $preProduct = $db->fetchOne(
            "SELECT id, sku, name, current_stock, unit, cost_price FROM products WHERE id = ? AND is_active = 1",
            [$oldProductId]
        );
    } elseif (!empty($_GET['product'])) {
        $preProduct = $db->fetchOne(
            "SELECT id, sku, name, current_stock, unit, cost_price FROM products WHERE id = ? AND is_active = 1",
            [(int)$_GET['product']]
        );
    }

    // The view's JS preview ("stock after this transaction") needs this
    // branch's quantity, not the company-wide total the query above returns.
    if ($preProduct) {
        $preProduct['current_stock'] = getBranchStock($preProduct['id']);
    }

    $categories = $db->fetchAll(
        "SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC"
    );

    $pageTitle = 'Stock In';
    include APP_PATH . '/views/stock/stock-in.php';
}

/**
 * Process Stock In
 */
function processStockIn($db)
{
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect(BASE_URL . '/stock/in', 'error', 'Invalid form submission');
        return;
    }

    $productId  = intval($_POST['product_id']  ?? 0);
    $quantity   = floatval($_POST['quantity']    ?? 0);
    $costPrice  = floatval($_POST['cost_price'] ?? 0);
    $notes      = trim($_POST['notes']         ?? '');
    $reference  = trim($_POST['reference']     ?? '');
    $userId     = $_SESSION['user_id']         ?? null;

    // Validate
    $supplierId = !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : null;

    $errors = [];
    if ($productId <= 0) $errors[] = 'Please select a product';
    if ($quantity  <= 0) $errors[] = 'Quantity must be greater than zero';
    if (!$supplierId)    $errors[] = 'Please select a supplier';

    $product = $db->fetchOne("SELECT * FROM products WHERE id = ? AND is_active = 1", [$productId]);
    if (!$product) $errors[] = 'Product not found';

    if (!empty($errors)) {
        $_SESSION['old_input'] = $_POST;
        redirect(BASE_URL . '/stock/in', 'error', implode(' | ', $errors));
        return;
    }

    $branchId  = activeBranchId();
    $prevStock = getBranchStock($productId, $branchId);
    $newStock  = $prevStock + $quantity;

    try {
        $db->beginTransaction();

        // Update this branch's stock (also keeps products.current_stock,
        // the company-wide total, in sync automatically)
        adjustBranchStock($db, $productId, $branchId, $quantity);

        // Cost price is a product-level (not branch-level) field
        if ($costPrice > 0) {
            $db->query("UPDATE products SET cost_price = ? WHERE id = ?", [$costPrice, $productId]);
        }

        // Log movement
        $db->query("
            INSERT INTO stock_movements
                (product_id, movement_type, quantity, reference_type,
                 previous_stock, new_stock, notes, user_id, branch_id)
            VALUES (?, 'in', ?, 'purchase', ?, ?, ?, ?, ?)
        ", [
            $productId,
            $quantity,
            $prevStock,
            $newStock,
            trim(($reference ? "Ref: $reference. " : '') . $notes),
            $userId,
            $branchId
        ]);

        $db->commit();
        redirect(
            BASE_URL . '/stock',
            'success',
            "{$product['name']}: stock updated from $prevStock to $newStock {$product['unit']} at " . branchName($branchId)
        );
    } catch (Exception $e) {
        $db->rollback();
        error_log('Stock in error: ' . $e->getMessage());
        redirect(BASE_URL . '/stock/in', 'error', 'Failed to process stock in');
    }
}

/**
 * Show Stock Out form (manual — sales handle this automatically)
 */
function showStockOutForm($db)
{
    $preProduct = null;
    if (!empty($_GET['product'])) {
        $preProduct = $db->fetchOne(
            "SELECT id, sku, name, current_stock, unit FROM products WHERE id = ? AND is_active = 1",
            [(int)$_GET['product']]
        );
        if ($preProduct) {
            $preProduct['current_stock'] = getBranchStock($preProduct['id']);
        }
    }

    $pageTitle = 'Stock Out (Manual)';
    include APP_PATH . '/views/stock/stock-out.php';
}

/**
 * Process Stock Out (manual removal — damaged, expired, etc.)
 */
function processStockOut($db)
{
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect(BASE_URL . '/stock/out', 'error', 'Invalid form submission');
        return;
    }

    $productId = intval($_POST['product_id'] ?? 0);
    $quantity  = floatval($_POST['quantity']   ?? 0);
    $reason    = trim($_POST['reason']       ?? '');
    $notes     = trim($_POST['notes']        ?? '');
    $userId    = $_SESSION['user_id']        ?? null;

    $errors = [];
    if ($productId <= 0) $errors[] = 'Please select a product';
    if ($quantity  <= 0) $errors[] = 'Quantity must be greater than zero';
    if (empty($reason))  $errors[] = 'Please provide a reason';

    $branchId = activeBranchId();
    $product = $db->fetchOne("SELECT * FROM products WHERE id = ? AND is_active = 1", [$productId]);
    $branchStockAvailable = $product ? getBranchStock($productId, $branchId) : 0;
    if (!$product) {
        $errors[] = 'Product not found';
    } elseif ($quantity > $branchStockAvailable) {
        $errors[] = "Not enough stock at " . branchName($branchId) . ". Available: {$branchStockAvailable} {$product['unit']}";
    }

    if (!empty($errors)) {
        $_SESSION['old_input'] = $_POST;
        redirect(BASE_URL . '/stock/out', 'error', implode(' | ', $errors));
        return;
    }

    $prevStock = $branchStockAvailable;
    $newStock  = $prevStock - $quantity;

    try {
        $db->beginTransaction();

        adjustBranchStock($db, $productId, $branchId, -$quantity);

        $db->query("
            INSERT INTO stock_movements
                (product_id, movement_type, quantity, reference_type,
                 previous_stock, new_stock, notes, user_id, branch_id)
            VALUES (?, 'out', ?, 'adjustment', ?, ?, ?, ?, ?)
        ", [
            $productId,
            $quantity,
            $prevStock,
            $newStock,
            trim("Reason: $reason" . ($notes ? ". $notes" : '')),
            $userId,
            $branchId
        ]);

        $db->commit();
        redirect(
            BASE_URL . '/stock',
            'success',
            "{$product['name']}: stock reduced from $prevStock to $newStock {$product['unit']} at " . branchName($branchId)
        );
    } catch (Exception $e) {
        $db->rollback();
        error_log('Stock out error: ' . $e->getMessage());
        redirect(BASE_URL . '/stock/out', 'error', 'Failed to process stock out');
    }
}

/**
 * Show Adjustment form
 */
function showAdjustForm($db, $id)
{
    $product = $db->fetchOne("SELECT * FROM products WHERE id = ? AND is_active = 1", [$id]);
    if (!$product) {
        redirect(BASE_URL . '/stock', 'error', 'Product not found');
        return;
    }
    $product['current_stock'] = getBranchStock($id);

    $pageTitle = 'Adjust Stock';
    include APP_PATH . '/views/stock/adjust.php';
}

/**
 * Process stock adjustment (set exact quantity)
 */
function processAdjustment($db, $id)
{
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect(BASE_URL . '/stock/adjust/' . $id, 'error', 'Invalid form submission');
        return;
    }

    $product = $db->fetchOne("SELECT * FROM products WHERE id = ? AND is_active = 1", [$id]);
    if (!$product) {
        redirect(BASE_URL . '/stock', 'error', 'Product not found');
        return;
    }

    $newStock = floatval($_POST['new_stock'] ?? 0);
    $notes    = trim($_POST['notes']       ?? '');
    $userId   = $_SESSION['user_id']       ?? null;
    $branchId = activeBranchId();

    if ($newStock < 0) {
        redirect(BASE_URL . '/stock/adjust/' . $id, 'error', 'Stock cannot be negative');
        return;
    }

    if (empty($notes)) {
        redirect(BASE_URL . '/stock/adjust/' . $id, 'error', 'Please provide a reason for the adjustment');
        return;
    }

    $prevStock = getBranchStock($id, $branchId);
    $diff      = round($newStock - $prevStock, 3);

    if (abs($diff) < 0.001) {
        redirect(BASE_URL . '/stock', 'info', 'No changes made — stock quantity is the same');
        return;
    }

    $movementType = $diff > 0 ? 'in' : 'out';

    try {
        $db->beginTransaction();

        setBranchStock($db, $id, $branchId, $newStock);

        $db->query("
            INSERT INTO stock_movements
                (product_id, movement_type, quantity, reference_type,
                 previous_stock, new_stock, notes, user_id, branch_id)
            VALUES (?, ?, ?, 'adjustment', ?, ?, ?, ?, ?)
        ", [
            $id,
            $movementType,
            abs($diff),
            $prevStock,
            $newStock,
            "Manual adjustment: $notes",
            $userId,
            $branchId
        ]);

        $db->commit();
        $direction = $diff > 0 ? "increased by $diff" : "decreased by " . abs($diff);
        redirect(
            BASE_URL . '/stock',
            'success',
            "{$product['name']}: stock $direction → now $newStock {$product['unit']} at " . branchName($branchId)
        );
    } catch (Exception $e) {
        $db->rollback();
        error_log('Adjustment error: ' . $e->getMessage());
        redirect(BASE_URL . '/stock/adjust/' . $id, 'error', 'Failed to process adjustment');
    }
}

/**
 * List all stock movements (audit log)
 */
function listMovements($db)
{
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 30;
    $offset = ($page - 1) * $limit;
    $type   = $_GET['type']   ?? '';
    $search = trim($_GET['search'] ?? '');

    $params = [];
    $where  = "WHERE 1=1";

    if (!empty($type) && in_array($type, ['in', 'out', 'adjustment'])) {
        $where   .= " AND sm.movement_type = ?";
        $params[] = $type;
    }

    if (!empty($search)) {
        $where   .= " AND p.name LIKE ?";
        $params[] = "%$search%";
    }

    $total      = $db->fetchOne("
        SELECT COUNT(*) as cnt
        FROM stock_movements sm
        JOIN products p ON sm.product_id = p.id
        $where
    ", $params);
    $totalCount = intval($total['cnt'] ?? 0);
    $totalPages = max(1, ceil($totalCount / $limit));

    $movements = $db->fetchAll("
        SELECT
            sm.id,
            sm.movement_type,
            sm.quantity,
            sm.reference_type,
            sm.previous_stock,
            sm.new_stock,
            sm.notes,
            sm.created_at,
            p.id   AS product_id,
            p.name AS product_name,
            p.sku  AS product_sku,
            p.unit,
            u.full_name AS user_name
        FROM stock_movements sm
        JOIN products p ON sm.product_id = p.id
        LEFT JOIN users u ON sm.user_id = u.id
        $where
        ORDER BY sm.created_at DESC
        LIMIT ? OFFSET ?
    ", array_merge($params, [$limit, $offset]));

    $pageTitle = 'Stock Movements';
    include APP_PATH . '/views/stock/movements.php';
}

/**
 * Low stock alerts
 */
function lowStockAlerts($db)
{
    $branchId = activeBranchId();
    $alerts = $db->fetchAll("
        SELECT
            p.id,
            p.sku,
            p.name,
            COALESCE(bs.quantity, 0)                        AS current_stock,
            p.reorder_level,
            p.unit,
            p.cost_price,
            p.selling_price,
            c.name                                           AS category_name,
            (p.reorder_level - COALESCE(bs.quantity, 0))     AS shortage,
            CASE WHEN COALESCE(bs.quantity, 0) = 0 THEN 'out' ELSE 'low' END AS alert_type
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN branch_stock bs ON bs.product_id = p.id AND bs.branch_id = ?
        WHERE p.is_active = 1
          AND COALESCE(bs.quantity, 0) <= p.reorder_level
        ORDER BY COALESCE(bs.quantity, 0) ASC, shortage DESC
    ", [$branchId]);

    $outOfStock = array_filter($alerts, fn($a) => $a['alert_type'] === 'out');
    $lowStock   = array_filter($alerts, fn($a) => $a['alert_type'] === 'low');

    $pageTitle = 'Low Stock Alerts';
    $viewingBranchName = hasMultiBranch() ? activeBranchName() : null;
    include APP_PATH . '/views/stock/alerts.php';
}

/**
 * Movements for a single product
 */
function productMovements($db, $id)
{
    $product = $db->fetchOne("SELECT * FROM products WHERE id = ?", [$id]);
    if (!$product) {
        redirect(BASE_URL . '/stock', 'error', 'Product not found');
        return;
    }

    $movements = $db->fetchAll("
        SELECT
            sm.*,
            u.full_name AS user_name
        FROM stock_movements sm
        LEFT JOIN users u ON sm.user_id = u.id
        WHERE sm.product_id = ?
        ORDER BY sm.created_at DESC
        LIMIT 50
    ", [$id]);

    // Stock summary for this product
    $summary = $db->fetchOne("
        SELECT
            COALESCE(SUM(CASE WHEN movement_type = 'in'  THEN quantity ELSE 0 END), 0) AS total_in,
            COALESCE(SUM(CASE WHEN movement_type = 'out' THEN quantity ELSE 0 END), 0) AS total_out
        FROM stock_movements
        WHERE product_id = ?
    ", [$id]);

    $pageTitle = 'Stock History: ' . $product['name'];
    include APP_PATH . '/views/stock/product-movements.php';
}
