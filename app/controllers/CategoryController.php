<?php

/**
 * Category Controller
 * Handles all product category operations
 */

if (!defined('APP_START')) {
    die('Direct access not permitted');
}

$db = Database::getInstance();

// Parse URL
$requestUri  = $_SERVER['REQUEST_URI'];
$scriptName  = dirname($_SERVER['SCRIPT_NAME']);
$basePath    = rtrim($scriptName, '/');
$fullPath    = parse_url($requestUri, PHP_URL_PATH);
$relativePath = str_replace($basePath, '', $fullPath);
$relativePath = str_replace('/public', '', $relativePath);
$relativePath = '/' . trim($relativePath, '/');

$segments = array_values(array_filter(explode('/', trim($relativePath, '/'))));
// $segments[0] = 'categories'
// $segments[1] = action
// $segments[2] = id

if (count($segments) === 1) {
    $action = 'index';
    $id     = null;
} elseif (count($segments) === 2) {
    $action = $segments[1];
    $id     = null;
} else {
    $action = $segments[1];
    $id     = $segments[2];
}

// Route request
switch ($action) {
    case 'index':
        listCategories($db);
        break;

    case 'create':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            createCategory($db);
        } else {
            showCreateForm($db);
        }
        break;

    case 'edit':
        if (!$id) redirect(BASE_URL . '/categories', 'error', 'Category ID required');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            updateCategory($db, $id);
        } else {
            showEditForm($db, $id);
        }
        break;

    case 'delete':
        if (!$id) redirect(BASE_URL . '/categories', 'error', 'Category ID required');
        deleteCategory($db, $id);
        break;

    case 'toggle':
        // Quick enable/disable via AJAX
        if (!$id) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'ID required']);
            exit;
        }
        toggleCategory($db, $id);
        break;

    default:
        http_response_code(404);
        echo "<h1>Action not found</h1>";
        echo "<a href='" . BASE_URL . "/categories'>← Back to Categories</a>";
        break;
}

// ============================================================
// FUNCTIONS
// ============================================================

/**
 * List all categories with product counts
 */
function listCategories($db)
{
    $search = trim($_GET['search'] ?? '');
    $params = [];
    $where  = '';

    if (!empty($search)) {
        $where  = "WHERE c.name LIKE ? OR c.description LIKE ?";
        $params = ["%$search%", "%$search%"];
    }

    // Get categories with product count
    $categories = $db->fetchAll("
        SELECT 
            c.id,
            c.name,
            c.description,
            c.is_active,
            c.created_at,
            COUNT(p.id)                                          AS total_products,
            COALESCE(SUM(p.current_stock * p.cost_price), 0)    AS stock_value,
            SUM(CASE WHEN p.current_stock <= p.reorder_level 
                      AND p.is_active = 1 
                      THEN 1 ELSE 0 END)                         AS low_stock_count
        FROM categories c
        LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
        $where
        GROUP BY c.id
        ORDER BY c.name ASC
    ", $params);

    // Summary stats
    $stats = $db->fetchOne("
        SELECT
            COUNT(*)                                             AS total_categories,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END)     AS active_categories,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END)     AS inactive_categories
        FROM categories
    ");

    $pageTitle = 'Product Categories';
    include APP_PATH . '/views/categories/index.php';
}

/**
 * Show create form
 */
function showCreateForm($db)
{
    $pageTitle = 'Add New Category';
    include APP_PATH . '/views/categories/create.php';
}

/**
 * Create a new category
 */
function createCategory($db)
{
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect(BASE_URL . '/categories/create', 'error', 'Invalid form submission');
        return;
    }

    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $is_active   = isset($_POST['is_active']) ? 1 : 0;

    // Validate
    $errors = [];
    if (empty($name)) {
        $errors[] = 'Category name is required';
    }
    if (strlen($name) > 100) {
        $errors[] = 'Category name must be 100 characters or less';
    }

    // Check duplicate name
    $existing = $db->fetchOne("SELECT id FROM categories WHERE name = ?", [$name]);
    if ($existing) {
        $errors[] = 'A category with this name already exists';
    }

    if (!empty($errors)) {
        $_SESSION['errors']    = $errors;
        $_SESSION['old_input'] = $_POST;
        redirect(BASE_URL . '/categories/create', 'error', implode(' | ', $errors));
        return;
    }

    try {
        $db->query(
            "INSERT INTO categories (name, description, is_active) VALUES (?, ?, ?)",
            [$name, $description ?: null, $is_active]
        );
        redirect(BASE_URL . '/categories', 'success', "Category \"$name\" created successfully");
    } catch (Exception $e) {
        error_log('Error creating category: ' . $e->getMessage());
        redirect(BASE_URL . '/categories/create', 'error', 'Failed to create category. Please try again.');
    }
}

/**
 * Show edit form
 */
function showEditForm($db, $id)
{
    $category = $db->fetchOne("SELECT * FROM categories WHERE id = ?", [$id]);

    if (!$category) {
        redirect(BASE_URL . '/categories', 'error', 'Category not found');
        return;
    }

    // Get products in this category
    $products = $db->fetchAll("
        SELECT id, name, sku, current_stock, selling_price, is_active
        FROM products
        WHERE category_id = ?
        ORDER BY name ASC
    ", [$id]);

    $pageTitle = 'Edit Category';
    include APP_PATH . '/views/categories/edit.php';
}

/**
 * Update a category
 */
function updateCategory($db, $id)
{
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect(BASE_URL . '/categories/edit/' . $id, 'error', 'Invalid form submission');
        return;
    }

    $category = $db->fetchOne("SELECT * FROM categories WHERE id = ?", [$id]);
    if (!$category) {
        redirect(BASE_URL . '/categories', 'error', 'Category not found');
        return;
    }

    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $is_active   = isset($_POST['is_active']) ? 1 : 0;

    // Validate
    $errors = [];
    if (empty($name)) {
        $errors[] = 'Category name is required';
    }
    if (strlen($name) > 100) {
        $errors[] = 'Category name must be 100 characters or less';
    }

    // Check duplicate name (excluding current)
    $existing = $db->fetchOne(
        "SELECT id FROM categories WHERE name = ? AND id != ?",
        [$name, $id]
    );
    if ($existing) {
        $errors[] = 'A category with this name already exists';
    }

    if (!empty($errors)) {
        $_SESSION['errors']    = $errors;
        $_SESSION['old_input'] = $_POST;
        redirect(BASE_URL . '/categories/edit/' . $id, 'error', implode(' | ', $errors));
        return;
    }

    try {
        $db->query(
            "UPDATE categories SET name = ?, description = ?, is_active = ? WHERE id = ?",
            [$name, $description ?: null, $is_active, $id]
        );
        redirect(BASE_URL . '/categories', 'success', "Category \"$name\" updated successfully");
    } catch (Exception $e) {
        error_log('Error updating category: ' . $e->getMessage());
        redirect(BASE_URL . '/categories/edit/' . $id, 'error', 'Failed to update category. Please try again.');
    }
}

/**
 * Delete a category
 * Soft-delete if it has products, hard-delete if empty
 */
function deleteCategory($db, $id)
{
    $category = $db->fetchOne("SELECT * FROM categories WHERE id = ?", [$id]);
    if (!$category) {
        redirect(BASE_URL . '/categories', 'error', 'Category not found');
        return;
    }

    // Check if category has products
    $productCount = $db->fetchOne(
        "SELECT COUNT(*) AS count FROM products WHERE category_id = ?",
        [$id]
    );

    if ($productCount['count'] > 0) {
        // Has products - just deactivate, set products category to NULL
        $db->query("UPDATE products SET category_id = NULL WHERE category_id = ?", [$id]);
        $db->query("DELETE FROM categories WHERE id = ?", [$id]);
        redirect(BASE_URL . '/categories', 'success', "Category deleted. {$productCount['count']} product(s) moved to uncategorized.");
    } else {
        // Safe to delete completely
        $db->query("DELETE FROM categories WHERE id = ?", [$id]);
        redirect(BASE_URL . '/categories', 'success', 'Category deleted successfully');
    }
}

/**
 * Toggle active/inactive (AJAX)
 */
function toggleCategory($db, $id)
{
    header('Content-Type: application/json');

    $category = $db->fetchOne("SELECT id, is_active FROM categories WHERE id = ?", [$id]);
    if (!$category) {
        echo json_encode(['success' => false, 'message' => 'Category not found']);
        exit;
    }

    $newStatus = $category['is_active'] ? 0 : 1;
    $db->query("UPDATE categories SET is_active = ? WHERE id = ?", [$newStatus, $id]);

    echo json_encode([
        'success'   => true,
        'is_active' => $newStatus,
        'message'   => $newStatus ? 'Category activated' : 'Category deactivated'
    ]);
    exit;
}
