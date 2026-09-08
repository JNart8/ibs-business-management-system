<?php

/**
 * Product Controller
 * Handles all product-related operations
 */

if (!defined('APP_START')) {
    die('Direct access not permitted');
}

$db = Database::getInstance();

// ── Use $path already parsed by the router ────────────────────
$segments = array_values(array_filter(explode('/', trim($path, '/'))));

$action = $segments[1] ?? 'index';
$id     = $segments[2] ?? null;

switch ($action) {
    case 'index':
    case 'list':
        listProducts($db);
        break;

    case 'create':
    case 'add':
        $method === 'POST' ? createProduct($db) : showCreateForm($db);
        break;

    case 'edit':
    case 'update':
        if (!$id) redirect(BASE_URL . '/products', 'error', 'Product ID required');
        $method === 'POST' ? updateProduct($db, $id) : showEditForm($db, $id);
        break;

    case 'delete':
        if (!$id) redirect(BASE_URL . '/products', 'error', 'Product ID required');
        deleteProduct($db, $id);
        break;

    case 'search':
        searchProducts($db);
        break;

    case 'view':
        if (!$id) redirect(BASE_URL . '/products', 'error', 'Product ID required');
        viewProduct($db, $id);
        break;

    default:
        http_response_code(404);
        echo "Action not found";
        break;
}

// ============================================================
// FUNCTIONS
// ============================================================

/**
 * List all products with search and pagination
 */
function listProducts($db)
{
    $search   = $_GET['search'] ?? '';
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $perPage  = ITEMS_PER_PAGE;
    $offset   = ($page - 1) * $perPage;

    $params = [];
    $where  = "WHERE p.is_active = 1";

    if (!empty($search)) {
        $where   .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)";
        $t        = "%{$search}%";
        $params[] = $t;
        $params[] = $t;
        $params[] = $t;
    }

    $categoryFilter = (int)($_GET['category'] ?? 0);
    if ($categoryFilter > 0) {
        $where   .= " AND p.category_id = ?";
        $params[] = $categoryFilter;
    }

    $filterCategoryName = '';
    if ($categoryFilter > 0) {
        $cat = $db->fetchOne("SELECT name FROM categories WHERE id = ?", [$categoryFilter]);
        $filterCategoryName = $cat['name'] ?? '';
    }

    $totalResult   = $db->fetchOne("SELECT COUNT(*) as total FROM products p $where", $params);
    $totalProducts = $totalResult['total'];
    $totalPages    = max(1, ceil($totalProducts / $perPage));

    $products = $db->fetchAll("
        SELECT
            p.id, p.sku, p.barcode, p.name,
            p.selling_price, p.cost_price, p.average_cost,
            p.current_stock, p.reorder_level, p.unit,
            c.name AS category_name,
            CASE
                WHEN p.current_stock = 0              THEN 'out-of-stock'
                WHEN p.current_stock <= p.reorder_level THEN 'low-stock'
                ELSE 'in-stock'
            END AS stock_status
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        $where
        ORDER BY p.name ASC
        LIMIT ? OFFSET ?
    ", array_merge($params, [$perPage, $offset]));

    $stats = $db->fetchOne("
        SELECT
            COUNT(*)                                                          AS total_products,
            COALESCE(SUM(current_stock * cost_price), 0)                      AS total_value,
            COALESCE(SUM(CASE WHEN current_stock <= reorder_level THEN 1 ELSE 0 END), 0) AS low_stock_count,
            COALESCE(SUM(CASE WHEN current_stock = 0 THEN 1 ELSE 0 END), 0)  AS out_of_stock_count
        FROM products
        WHERE is_active = 1
    ");

    $pageTitle = 'Products';
    include APP_PATH . '/views/products/index.php';
}

/**
 * Show create product form
 */
function showCreateForm($db)
{
    $categories = $db->fetchAll(
        "SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC"
    );
    $suppliers = $db->fetchAll(
        "SELECT id, company_name FROM suppliers WHERE is_active = 1 ORDER BY company_name ASC"
    );

    $pageTitle = 'Add New Product';
    include APP_PATH . '/views/products/create.php';
}

/**
 * Create a new product
 */
function createProduct($db)
{
    // CSRF check
    if (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
        redirect(BASE_URL . '/products/create', 'error', 'Invalid form submission');
    }

    $sku           = trim($_POST['sku']           ?? '');
    $barcode       = trim($_POST['barcode']       ?? '');
    $name          = trim($_POST['name']          ?? '');
    $description   = trim($_POST['description']   ?? '');
    $category_id   = !empty($_POST['category_id'])  ? (int)$_POST['category_id']  : null;
    $supplier_id   = !empty($_POST['supplier_id'])  ? (int)$_POST['supplier_id']  : null;
    $cost_price    = floatval($_POST['cost_price']   ?? 0);
    $selling_price = floatval($_POST['selling_price'] ?? 0);
    $current_stock = floatval($_POST['current_stock']  ?? 0);
    $reorder_level = floatval($_POST['reorder_level']  ?? 10);
    $unit          = normalizeUnit($_POST['unit'] ?? 'pcs');

    $errors = [];
    if (empty($sku))           $errors[] = 'SKU is required';
    if (empty($name))          $errors[] = 'Product name is required';
    if ($selling_price <= 0)   $errors[] = 'Selling price must be greater than 0';

    if ($db->fetchOne("SELECT id FROM products WHERE sku = ?", [$sku])) {
        $errors[] = 'SKU already exists';
    }
    if (!empty($barcode) && $db->fetchOne("SELECT id FROM products WHERE barcode = ?", [$barcode])) {
        $errors[] = 'Barcode already exists';
    }

    if (!empty($errors)) {
        $_SESSION['old_input'] = $_POST;
        redirect(BASE_URL . '/products/create', 'error', implode(', ', $errors));
    }

    try {
        $db->query("
            INSERT INTO products
                (sku, barcode, name, description, category_id, supplier_id,
                 cost_price, average_cost, selling_price, current_stock, reorder_level, unit)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)
        ", [
            $sku,
            $barcode ?: null,
            $name,
            $description,
            $category_id,
            $supplier_id,
            $cost_price,
            $cost_price,
            $selling_price,
            $reorder_level,
            $unit
        ]);

        $productId = $db->lastInsertId();

        if ($current_stock > 0) {
            $branchId = activeBranchId();
            // Seeds this branch's stock and keeps products.current_stock
            // (the maintained total) in sync automatically.
            setBranchStock($db, $productId, $branchId, $current_stock);

            $db->query("
                INSERT INTO stock_movements
                    (product_id, movement_type, quantity, reference_type,
                     previous_stock, new_stock, notes, user_id, branch_id)
                VALUES (?, 'in', ?, 'opening', 0, ?, 'Opening stock', ?, ?)
            ", [$productId, $current_stock, $current_stock, $_SESSION['user_id'] ?? null, $branchId]);
        }

        redirect(BASE_URL . '/products', 'success', 'Product created successfully');
    } catch (Exception $e) {
        error_log('Error creating product: ' . $e->getMessage());
        redirect(BASE_URL . '/products/create', 'error', 'Failed to create product');
    }
}

/**
 * Show edit product form
 */
function showEditForm($db, $id)
{
    $product = $db->fetchOne("SELECT * FROM products WHERE id = ?", [$id]);
    if (!$product) redirect(BASE_URL . '/products', 'error', 'Product not found');

    $categories = $db->fetchAll(
        "SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC"
    );
    $suppliers = $db->fetchAll(
        "SELECT id, company_name FROM suppliers WHERE is_active = 1 ORDER BY company_name ASC"
    );

    $pageTitle = 'Edit Product';
    include APP_PATH . '/views/products/edit.php';
}

/**
 * Update a product
 */
function updateProduct($db, $id)
{
    if (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
        redirect(BASE_URL . '/products/edit/' . $id, 'error', 'Invalid form submission');
    }

    $product = $db->fetchOne("SELECT * FROM products WHERE id = ?", [$id]);
    if (!$product) redirect(BASE_URL . '/products', 'error', 'Product not found');

    $sku           = trim($_POST['sku']           ?? '');
    $barcode       = trim($_POST['barcode']       ?? '');
    $name          = trim($_POST['name']          ?? '');
    $description   = trim($_POST['description']   ?? '');
    $category_id   = !empty($_POST['category_id'])  ? (int)$_POST['category_id']  : null;
    $supplier_id   = !empty($_POST['supplier_id'])  ? (int)$_POST['supplier_id']  : null;
    $cost_price    = floatval($_POST['cost_price']   ?? 0);
    $selling_price = floatval($_POST['selling_price'] ?? 0);
    $reorder_level = floatval($_POST['reorder_level']  ?? 10);
    $unit          = normalizeUnit($_POST['unit'] ?? 'pcs');
    $is_active     = isset($_POST['is_active']) ? 1 : 0;

    $errors = [];
    if (empty($sku))         $errors[] = 'SKU is required';
    if (empty($name))        $errors[] = 'Product name is required';
    if ($selling_price <= 0) $errors[] = 'Selling price must be greater than 0';

    if ($db->fetchOne("SELECT id FROM products WHERE sku = ? AND id != ?", [$sku, $id])) {
        $errors[] = 'SKU already exists';
    }
    if (!empty($barcode) && $db->fetchOne("SELECT id FROM products WHERE barcode = ? AND id != ?", [$barcode, $id])) {
        $errors[] = 'Barcode already exists';
    }

    if (!empty($errors)) {
        $_SESSION['old_input'] = $_POST;
        redirect(BASE_URL . '/products/edit/' . $id, 'error', implode(', ', $errors));
    }

    try {
        $db->query("
            UPDATE products
            SET sku = ?, barcode = ?, name = ?, description = ?,
                category_id = ?, supplier_id = ?,
                cost_price = ?, selling_price = ?,
                reorder_level = ?, unit = ?, is_active = ?
            WHERE id = ?
        ", [
            $sku,
            $barcode ?: null,
            $name,
            $description,
            $category_id,
            $supplier_id,
            $cost_price,
            $selling_price,
            $reorder_level,
            $unit,
            $is_active,
            $id
        ]);

        redirect(BASE_URL . '/products', 'success', 'Product updated successfully');
    } catch (Exception $e) {
        error_log('Error updating product: ' . $e->getMessage());
        redirect(BASE_URL . '/products/edit/' . $id, 'error', 'Failed to update product');
    }
}

/**
 * Delete or deactivate a product
 */
function deleteProduct($db, $id)
{
    $product = $db->fetchOne("SELECT * FROM products WHERE id = ?", [$id]);
    if (!$product) redirect(BASE_URL . '/products', 'error', 'Product not found');

    $hasSales = $db->fetchOne("SELECT COUNT(*) as count FROM sale_items WHERE product_id = ?", [$id]);

    if ($hasSales['count'] > 0) {
        $db->query("UPDATE products SET is_active = 0 WHERE id = ?", [$id]);
        redirect(BASE_URL . '/products', 'success', 'Product deactivated (has sales history)');
    } else {
        try {
            $db->query("DELETE FROM products WHERE id = ?", [$id]);
            redirect(BASE_URL . '/products', 'success', 'Product deleted successfully');
        } catch (Exception $e) {
            error_log('Error deleting product: ' . $e->getMessage());
            redirect(BASE_URL . '/products', 'error', 'Failed to delete product');
        }
    }
}

/**
 * Show product details
 */
function viewProduct($db, $id)
{
    // Get product details with category and supplier
    $product = $db->fetchOne("
        SELECT 
            p.*,
            c.name as category_name,
            s.company_name as supplier_name,
            s.id as supplier_id
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        WHERE p.id = ?
    ", [$id]);

    if (!$product) {
        redirect(BASE_URL . '/products', 'error', 'Product not found');
        return;
    }

    // Calculate stock value and margin based on Weighted Moving Average Cost
    $stockValue = $product['current_stock'] * $product['average_cost'];
    $margin = $product['selling_price'] > 0
        ? (($product['selling_price'] - $product['average_cost']) / $product['selling_price']) * 100
        : 0;

    // Get sales summary
    $salesSummary = $db->fetchOne("
        SELECT 
            COUNT(DISTINCT si.sale_id) as total_sales,
            COALESCE(SUM(si.quantity), 0) as units_sold,
            COALESCE(SUM(si.line_total), 0) as revenue_generated,
            MAX(s.sale_date) as last_sale
        FROM sale_items si
        INNER JOIN sales s ON si.sale_id = s.id
        WHERE si.product_id = ?
    ", [$id]) ?? [
        'total_sales' => 0,
        'units_sold' => 0,
        'revenue_generated' => 0,
        'last_sale' => null
    ];

    // Get recent sales (last 20)
    $recentSales = $db->fetchAll("
        SELECT 
            s.sale_number,
            s.sale_date,
            c.full_name as customer_name,
            si.quantity,
            si.unit_price,
            si.discount_percent,
            si.line_total,
            s.payment_status,
            u.username as sold_by
        FROM sale_items si
        INNER JOIN sales s ON si.sale_id = s.id
        INNER JOIN customers c ON s.customer_id = c.id
        LEFT JOIN users u ON s.user_id = u.id
        WHERE si.product_id = ?
        ORDER BY s.sale_date DESC
        LIMIT 20
    ", [$id]);

    // Get stock movement history (last 30)
    $stockMovements = $db->fetchAll("
        SELECT 
            sm.movement_type,
            sm.quantity,
            sm.reference_type,
            sm.reference_id,
            sm.previous_stock,
            sm.new_stock,
            sm.notes,
            sm.created_at,
            u.username as recorded_by,
            s.company_name as supplier_name
        FROM stock_movements sm
        LEFT JOIN users u ON sm.user_id = u.id
        LEFT JOIN suppliers s ON sm.supplier_id = s.id
        WHERE sm.product_id = ?
        ORDER BY sm.created_at DESC
        LIMIT 30
    ", [$id]);

    // Prepare view data
    $pageTitle = $product['name'];
    $summary = [
        'stock_value' => $stockValue,
        'margin_percent' => $margin,
        'total_sales' => $salesSummary['total_sales'],
        'units_sold' => $salesSummary['units_sold'],
        'revenue_generated' => $salesSummary['revenue_generated'],
        'last_sale' => $salesSummary['last_sale']
    ];

    include APP_PATH . '/views/products/view.php';
}

/**
 * Search products — JSON endpoint used by POS
 */
function searchProducts($db)
{
    header('Content-Type: application/json');

    $query    = trim($_GET['q']        ?? '');
    $catId    = (int)($_GET['category'] ?? 0);

    // ?all=1 skips stock filter — used by stock-in/out forms to include zero-stock products
    // Default (POS) only returns products that actually have stock to sell
    $includeEmpty = isset($_GET['all']) && $_GET['all'] == '1';

    // current_stock here means "at the target branch", not the company-wide
    // total — a cashier at Branch 2 must only see/sell what's actually on
    // Branch 2's shelf. The alias keeps the JSON key name unchanged, so
    // every existing consumer of this endpoint (POS, stock-in/out,
    // purchases, distributor) needs no changes on the JS side.
    //
    // Normally this is just the requester's active branch, but transfers
    // need to see stock at an arbitrary *source* branch that isn't
    // necessarily their active one — ?branch_id= allows that override,
    // restricted to branches the requester can actually access (company-wide,
    // or one they're assigned to), so this can't be used to snoop another
    // branch's stock levels.
    $requestedBranchId = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : null;
    if ($requestedBranchId && (isCompanyWide() || in_array($requestedBranchId, array_column(userBranches($_SESSION['user_id']), 'id'), true))) {
        $branchId = $requestedBranchId;
    } else {
        $branchId = activeBranchId();
    }
    $params   = [$branchId];
    $where    = $includeEmpty
        ? "WHERE p.is_active = 1"
        : "WHERE p.is_active = 1 AND COALESCE(bs.quantity, 0) > 0";

    if (!empty($query)) {
        $where   .= " AND (p.sku LIKE ? OR p.barcode = ? OR p.name LIKE ?)";
        $t        = "%{$query}%";
        $params[] = $t;
        $params[] = $query;
        $params[] = $t;
    }

    if ($catId > 0) {
        $where   .= " AND p.category_id = ?";
        $params[] = $catId;
    }

    $products = $db->fetchAll("
        SELECT p.id, p.sku, p.barcode, p.name, p.selling_price, p.unit, p.cost_price,
               COALESCE(bs.quantity, 0) AS current_stock
        FROM products p
        LEFT JOIN branch_stock bs ON bs.product_id = p.id AND bs.branch_id = ?
        $where
        ORDER BY p.name ASC
        LIMIT 20
    ", $params);

    echo json_encode($products);
}
