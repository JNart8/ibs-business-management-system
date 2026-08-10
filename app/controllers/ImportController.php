<?php

/**
 * Import Controller
 * Handles CSV imports for Categories, Products, Suppliers, Customers
 */

if (!defined('APP_START')) {
    die('Direct access not permitted');
}

$db = Database::getInstance();

// Parse URL
$segments = array_values(array_filter(explode('/', trim($path, '/'))));
$type = $segments[1] ?? null; // categories, products, suppliers, customers

// Template download (GET request)
if (isset($segments[2]) && $segments[2] === 'template') {
    downloadTemplate($type);
    exit;
}

// Route based on type and action
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    showImportForm($db, $type);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'preview') {
        previewImport($db, $type);
    } else {
        processImport($db, $type);
    }
}

/**
 * Show import form
 */
function showImportForm($db, $type)
{
    if (!in_array($type, ['categories', 'products', 'suppliers', 'customers', 'purchases'])) {
        redirect(BASE_URL . '/', 'error', 'Invalid import type');
        return;
    }

    $pageTitle = 'Import ' . ucfirst($type);
    include APP_PATH . '/views/import/form.php';
}

/**
 * Preview CSV before importing
 */
function previewImport($db, $type)
{
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        redirect(BASE_URL . '/import/' . $type, 'error', 'Please upload a valid CSV file');
        return;
    }

    $file = $_FILES['csv_file']['tmp_name'];
    $mode = $_POST['import_mode'] ?? 'create'; // create or update

    // Parse CSV
    $result = parseCSV($file, $type, $mode, $db);

    if (!$result['success']) {
        redirect(BASE_URL . '/import/' . $type, 'error', $result['message']);
        return;
    }

    // Store in session for actual import
    $_SESSION['import_preview'] = [
        'type' => $type,
        'mode' => $mode,
        'data' => $result['data'],
        'errors' => $result['errors'],
        'file_hash' => md5_file($file)
    ];

    $pageTitle = 'Preview Import - ' . ucfirst($type);
    $preview = $result;
    include APP_PATH . '/views/import/preview.php';
}

/**
 * Process the actual import
 */
function processImport($db, $type)
{
    if (!isset($_SESSION['import_preview']) || $_SESSION['import_preview']['type'] !== $type) {
        redirect(BASE_URL . '/import/' . $type, 'error', 'No preview data found. Please upload again.');
        return;
    }

    $preview = $_SESSION['import_preview'];
    if ($type === 'purchases') {
        processPurchasesImport($db, $preview['data']);
        return;
    }
    $mode = $preview['mode'];
    $data = $preview['data'];

    $imported = 0;
    $updated = 0;
    $skipped = 0;
    $errors = [];

    try {
        $db->beginTransaction();

        foreach ($data as $row) {
            if (!empty($row['_error'])) {
                $skipped++;
                continue;
            }

            $result = importRow($db, $type, $row, $mode);

            if ($result['success']) {
                if ($result['action'] === 'created') $imported++;
                if ($result['action'] === 'updated') $updated++;
            } else {
                $skipped++;
                $errors[] = "Row {$row['_row_num']}: {$result['message']}";
            }
        }

        $db->commit();

        // Clear preview
        unset($_SESSION['import_preview']);

        $message = "Import complete: $imported created, $updated updated, $skipped skipped";
        if (!empty($errors)) {
            $message .= ". Errors: " . implode('; ', array_slice($errors, 0, 5));
        }

        redirect(BASE_URL . '/' . $type, 'success', $message);
    } catch (Exception $e) {
        $db->rollback();
        error_log('Import error: ' . $e->getMessage());
        redirect(BASE_URL . '/import/' . $type, 'error', 'Import failed: ' . $e->getMessage());
    }
}

/**
 * Parse CSV file and validate
 */
function parseCSV($file, $type, $mode, $db)
{
    $handle = fopen($file, 'r');
    if (!$handle) {
        return ['success' => false, 'message' => 'Could not read CSV file'];
    }

    // Get headers
    $headers = fgetcsv($handle);
    if (!$headers) {
        fclose($handle);
        return ['success' => false, 'message' => 'CSV file is empty'];
    }

    // Trim and lowercase headers
    $headers = array_map(fn($h) => strtolower(trim($h)), $headers);

    // Validate required headers
    $required = getRequiredHeaders($type);
    $missing = array_diff($required, $headers);

    if (!empty($missing)) {
        fclose($handle);
        return [
            'success' => false,
            'message' => 'Missing required columns: ' . implode(', ', $missing)
        ];
    }

    // Parse rows
    $data = [];
    $errors = [];
    $rowNum = 1; // Start at 1 (header is row 0)

    while (($row = fgetcsv($handle)) !== false) {
        $rowNum++;

        if (count($row) !== count($headers)) {
            $errors[] = "Row $rowNum: Column count mismatch";
            continue;
        }

        $rowData = array_combine($headers, $row);
        $rowData['_row_num'] = $rowNum;

        // Validate row
        $validation = validateRow($type, $rowData, $mode, $db);

        if (!$validation['valid']) {
            $rowData['_error'] = $validation['error'];
            $errors[] = "Row $rowNum: {$validation['error']}";
        }

        $data[] = $rowData;
    }

    fclose($handle);

    return [
        'success' => true,
        'data' => $data,
        'errors' => $errors,
        'total' => count($data),
        'valid' => count($data) - count($errors),
        'invalid' => count($errors)
    ];
}

/**
 * Get required CSV headers for each type
 */
function getRequiredHeaders($type)
{
    switch ($type) {
        case 'categories':
            return ['name'];
        case 'products':
            return ['sku', 'name', 'category', 'supplier', 'selling_price'];
        case 'suppliers':
            return ['company_name'];
        case 'customers':
            return ['full_name', 'phone'];
        case 'purchases':
            return ['supplier', 'sku', 'quantity', 'unit_cost'];
        default:
            return [];
    }
}

/**
 * Validate a single row
 */
function validateRow($type, $row, $mode, $db)
{
    switch ($type) {
        case 'categories':
            if (empty(trim($row['name']))) {
                return ['valid' => false, 'error' => 'Name is required'];
            }
            break;

        case 'products':
            if (empty(trim($row['sku']))) {
                return ['valid' => false, 'error' => 'SKU is required'];
            }
            // Check if product SKU already exists
            $existing = $db->fetchOne("SELECT id FROM products WHERE sku = ?", [trim($row['sku'])]);
            if ($existing) {
                return ['valid' => false, 'error' => "Product SKU '" . trim($row['sku']) . "' already exists"];
            }
            if (empty(trim($row['name']))) {
                return ['valid' => false, 'error' => 'Name is required'];
            }
            if (empty(trim($row['category'] ?? ''))) {
                return ['valid' => false, 'error' => 'Category is required'];
            }
            if (empty(trim($row['supplier'] ?? ''))) {
                return ['valid' => false, 'error' => 'Supplier is required'];
            }
            // Check if category exists
            $category = $db->fetchOne("SELECT id FROM categories WHERE name = ? AND is_active = 1", [trim($row['category'])]);
            if (!$category) {
                return ['valid' => false, 'error' => "Category '{$row['category']}' not found. Create it first."];
            }
            // Check if supplier exists
            $supplier = $db->fetchOne("SELECT id FROM suppliers WHERE company_name = ? AND is_active = 1", [trim($row['supplier'])]);
            if (!$supplier) {
                return ['valid' => false, 'error' => "Supplier '{$row['supplier']}' not found. Create it first."];
            }
            if (!isset($row['selling_price']) || !is_numeric($row['selling_price']) || floatval($row['selling_price']) <= 0) {
                return ['valid' => false, 'error' => 'Valid selling price is required'];
            }
            break;

        case 'suppliers':
            if (empty(trim($row['company_name']))) {
                return ['valid' => false, 'error' => 'Company name is required'];
            }
            break;

        case 'customers':
            if (empty(trim($row['full_name']))) {
                return ['valid' => false, 'error' => 'Full name is required'];
            }
            if (empty(trim($row['phone']))) {
                return ['valid' => false, 'error' => 'Phone is required'];
            }
            // Check if phone number already exists
            $existingPhone = $db->fetchOne("SELECT id, full_name FROM customers WHERE phone = ?", [trim($row['phone'])]);
            if ($existingPhone && $mode === 'skip') {
                return ['valid' => false, 'error' => "Phone number '" . trim($row['phone']) . "' is already registered to customer '" . $existingPhone['full_name'] . "'"];
            }
            break;

        case 'purchases':
            $supplierVal = trim($row['supplier'] ?? '');
            if (empty($supplierVal)) {
                return ['valid' => false, 'error' => 'Supplier is required'];
            }
            $supplier = $db->fetchOne("SELECT id FROM suppliers WHERE (company_name = ? OR supplier_code = ?) AND is_active = 1", [$supplierVal, $supplierVal]);
            if (!$supplier) {
                return ['valid' => false, 'error' => "Supplier '{$supplierVal}' not found or inactive"];
            }

            $skuVal = trim($row['sku'] ?? '');
            if (empty($skuVal)) {
                return ['valid' => false, 'error' => 'SKU is required'];
            }
            $product = $db->fetchOne("SELECT id FROM products WHERE sku = ? AND is_active = 1", [$skuVal]);
            if (!$product) {
                return ['valid' => false, 'error' => "Product SKU '{$skuVal}' not found or inactive"];
            }

            if (!isset($row['quantity']) || !is_numeric($row['quantity']) || floatval($row['quantity']) <= 0) {
                return ['valid' => false, 'error' => 'Quantity must be a positive number'];
            }

            if (!isset($row['unit_cost']) || !is_numeric($row['unit_cost']) || floatval($row['unit_cost']) <= 0) {
                return ['valid' => false, 'error' => 'Unit cost must be a positive number'];
            }

            $method = strtolower(trim($row['payment_method'] ?? 'credit'));
            if (!empty($method) && !in_array($method, ['cash', 'mobile', 'bank', 'credit', 'cheque'])) {
                return ['valid' => false, 'error' => "Invalid payment method '{$method}' (must be cash, mobile, bank, credit, cheque)"];
            }

            $accountVal = trim($row['payment_account'] ?? '');
            if (!empty($accountVal)) {
                $account = $db->fetchOne("SELECT id FROM accounts WHERE (account_number = ? OR name = ?) AND is_active = 1", [$accountVal, $accountVal]);
                if (!$account) {
                    return ['valid' => false, 'error' => "Payment account/number '{$accountVal}' not found or inactive"];
                }
            } elseif (!empty($method) && $method !== 'credit') {
                $expectedType = [
                    'cash' => 'cash',
                    'mobile' => 'mobile_money',
                    'bank' => 'bank',
                    'cheque' => 'bank'
                ][$method] ?? 'cash';
                $acct = $db->fetchOne("SELECT id FROM accounts WHERE type = ? AND is_active = 1 LIMIT 1", [$expectedType]);
                if (!$acct) {
                    $fallback = $db->fetchOne("SELECT id FROM accounts WHERE type = 'cash' AND is_active = 1 LIMIT 1");
                    if (!$fallback) {
                        return ['valid' => false, 'error' => "No active account of type '{$expectedType}' or fallback 'cash' found for payment method '{$method}'"];
                    }
                }
            }

            $dateVal = trim($row['purchase_date'] ?? '');
            if (!empty($dateVal)) {
                if (strtotime($dateVal) === false) {
                    return ['valid' => false, 'error' => "Invalid purchase date format '{$dateVal}'"];
                }
            }

            $discountPct = floatval($row['discount_percent'] ?? 0);
            if ($discountPct < 0 || $discountPct > 100) {
                return ['valid' => false, 'error' => 'Discount percent must be between 0 and 100'];
            }

            $vatPct = floatval($row['vat_percent'] ?? 0);
            if ($vatPct < 0 || $vatPct > 100) {
                return ['valid' => false, 'error' => 'VAT percent must be between 0 and 100'];
            }
            break;
    }

    return ['valid' => true];
}

/**
 * Import a single row
 */
function importRow($db, $type, $row, $mode)
{
    $userId = $_SESSION['user_id'] ?? null;

    try {
        switch ($type) {
            case 'categories':
                return importCategory($db, $row, $mode, $userId);
            case 'products':
                return importProduct($db, $row, $mode, $userId);
            case 'suppliers':
                return importSupplier($db, $row, $mode, $userId);
            case 'customers':
                return importCustomer($db, $row, $mode, $userId);
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }

    return ['success' => false, 'message' => 'Unknown type'];
}

function importCategory($db, $row, $mode, $userId)
{
    $existing = $db->fetchOne("SELECT id FROM categories WHERE name = ?", [trim($row['name'])]);

    if ($existing) {
        if ($mode === 'skip') {
            return ['success' => true, 'action' => 'skipped'];
        }
        // Update
        $db->query("UPDATE categories SET updated_at = NOW() WHERE id = ?", [$existing['id']]);
        return ['success' => true, 'action' => 'updated'];
    }

    // Create
    $db->query("INSERT INTO categories (name, is_active) VALUES (?, 1)", [trim($row['name'])]);
    return ['success' => true, 'action' => 'created'];
}

function importProduct($db, $row, $mode, $userId)
{
    // Look up category and supplier IDs by name
    $category = $db->fetchOne("SELECT id FROM categories WHERE name = ? AND is_active = 1", [trim($row['category'])]);
    $supplier = $db->fetchOne("SELECT id FROM suppliers WHERE company_name = ? AND is_active = 1", [trim($row['supplier'])]);

    if (!$category || !$supplier) {
        return ['success' => false, 'message' => 'Category or supplier not found'];
    }

    $categoryId = $category['id'];
    $supplierId = $supplier['id'];

    $existing = $db->fetchOne("SELECT id FROM products WHERE sku = ?", [trim($row['sku'])]);
    $importedStock = isset($row['current_stock']) ? floatval($row['current_stock']) : 0;

    if ($existing) {
        if ($mode === 'skip') {
            return ['success' => true, 'action' => 'skipped'];
        }
        // Update — current_stock is deliberately NOT set directly here; it's
        // maintained automatically by setBranchStock() below, which keeps
        // products.current_stock in sync as the sum across all branches.
        // A CSV import continues to mean "set stock to exactly this value",
        // now applied at the importer's active branch specifically.
        $db->query("
            UPDATE products 
            SET name = ?, category_id = ?, supplier_id = ?, selling_price = ?, cost_price = ?,
                average_cost = CASE
                    WHEN COALESCE(average_cost, 0) = 0 THEN ?
                    ELSE average_cost
                END,
                reorder_level = ?, unit = ?, barcode = ?, updated_at = NOW()
            WHERE id = ?
        ", [
            trim($row['name']),
            $categoryId,
            $supplierId,
            floatval($row['selling_price']),
            isset($row['cost_price']) ? floatval($row['cost_price']) : 0,
            isset($row['cost_price']) ? floatval($row['cost_price']) : 0,
            isset($row['reorder_level']) ? floatval($row['reorder_level']) : 10,
            normalizeUnit($row['unit'] ?? 'pcs'),
            $row['barcode'] ?? null,
            $existing['id']
        ]);
        if (isset($row['current_stock'])) {
            setBranchStock($db, $existing['id'], activeBranchId(), $importedStock);
        }
        return ['success' => true, 'action' => 'updated'];
    }

    // Create — same reasoning: current_stock is set via setBranchStock()
    // after the insert, not as a direct column value.
    $db->query("
        INSERT INTO products 
            (sku, name, category_id, supplier_id, selling_price, cost_price,
             average_cost, reorder_level, unit, barcode, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ", [
        trim($row['sku']),
        trim($row['name']),
        $categoryId,
        $supplierId,
        floatval($row['selling_price']),
        isset($row['cost_price']) ? floatval($row['cost_price']) : 0,
        isset($row['cost_price']) ? floatval($row['cost_price']) : 0,
        isset($row['reorder_level']) ? floatval($row['reorder_level']) : 10,
        normalizeUnit($row['unit'] ?? 'pcs'),
        $row['barcode'] ?? null
    ]);
    $newProductId = $db->lastInsertId();
    if ($importedStock > 0) {
        setBranchStock($db, $newProductId, activeBranchId(), $importedStock);
    }
    return ['success' => true, 'action' => 'created'];
}

function importSupplier($db, $row, $mode, $userId)
{
    $existing = $db->fetchOne("SELECT id FROM suppliers WHERE company_name = ?", [trim($row['company_name'])]);

    if ($existing) {
        if ($mode === 'skip') {
            return ['success' => true, 'action' => 'skipped'];
        }
        // Update
        $db->query("
            UPDATE suppliers 
            SET contact_name = ?, phone = ?, email = ?, address = ?, updated_at = NOW()
            WHERE id = ?
        ", [
            $row['contact_name'] ?? null,
            $row['phone'] ?? null,
            $row['email'] ?? null,
            $row['address'] ?? null,
            $existing['id']
        ]);
        return ['success' => true, 'action' => 'updated'];
    }

    // Create - generate supplier_code
    $code = 'SUP-' . strtoupper(substr(md5(trim($row['company_name'])), 0, 8));
    $db->query("
        INSERT INTO suppliers 
            (supplier_code, company_name, contact_name, phone, email, address, is_active)
        VALUES (?, ?, ?, ?, ?, ?, 1)
    ", [
        $code,
        trim($row['company_name']),
        $row['contact_name'] ?? null,
        $row['phone'] ?? null,
        $row['email'] ?? null,
        $row['address'] ?? null
    ]);
    return ['success' => true, 'action' => 'created'];
}

function importCustomer($db, $row, $mode, $userId)
{
    $existing = $db->fetchOne("SELECT id FROM customers WHERE phone = ?", [trim($row['phone'])]);

    if ($existing) {
        if ($mode === 'skip') {
            return ['success' => true, 'action' => 'skipped'];
        }
        // Update
        $db->query("
            UPDATE customers 
            SET full_name = ?, email = ?, address = ?, credit_limit = ?, updated_at = NOW()
            WHERE id = ?
        ", [
            trim($row['full_name']),
            $row['email'] ?? null,
            $row['address'] ?? null,
            isset($row['credit_limit']) ? floatval($row['credit_limit']) : 0,
            $existing['id']
        ]);
        return ['success' => true, 'action' => 'updated'];
    }

    // Create
    $code = 'CUST-' . strtoupper(substr(md5(trim($row['phone'])), 0, 8));
    $db->query("
        INSERT INTO customers 
            (customer_code, full_name, phone, email, address, credit_limit, is_active)
        VALUES (?, ?, ?, ?, ?, ?, 1)
    ", [
        $code,
        trim($row['full_name']),
        trim($row['phone']),
        $row['email'] ?? null,
        $row['address'] ?? null,
        isset($row['credit_limit']) ? floatval($row['credit_limit']) : 0
    ]);
    return ['success' => true, 'action' => 'created'];
}

/**
 * Download CSV template
 */
function downloadTemplate($type)
{
    $templates = [
        'categories' => [
            'headers' => ['name'],
            'sample' => [
                ['Electronics'],
                ['Clothing'],
                ['Food & Beverages'],
            ]
        ],
        'products' => [
            'headers' => ['sku', 'name', 'category', 'supplier', 'selling_price', 'cost_price', 'current_stock', 'reorder_level', 'unit', 'barcode'],
            'sample' => [
                ['PROD-001', 'Laptop Dell XPS 13', 'Electronics', 'Tech Supplies Ltd', '1200.00', '900.00', '10', '5', 'pcs', '123456789'],
                ['PROD-002', 'Wireless Mouse', 'Electronics', 'Tech Supplies Ltd', '25.50', '15.00', '50', '10', 'pcs', '987654321'],
                ['PROD-003', 'USB Cable 2m', 'Electronics', 'Global Electronics', '5.00', '3.00', '100', '20', 'pcs', ''],
            ]
        ],
        'suppliers' => [
            'headers' => ['company_name', 'contact_name', 'phone', 'email', 'address'],
            'sample' => [
                ['Tech Supplies Ltd', 'John Doe', '+265999123456', 'john@techsupplies.com', 'Blantyre, Malawi'],
                ['Global Electronics', 'Jane Smith', '+265888654321', 'jane@global.com', 'Lilongwe, Malawi'],
            ]
        ],
        'customers' => [
            'headers' => ['full_name', 'phone', 'email', 'address', 'credit_limit'],
            'sample' => [
                ['Alice Johnson', '+265991234567', 'alice@email.com', '123 Main St', '500.00'],
                ['Bob Wilson', '+265998765432', 'bob@email.com', '456 Oak Ave', '1000.00'],
            ]
        ],
        'purchases' => [
            'headers' => ['supplier', 'sku', 'quantity', 'unit_cost', 'invoice_number', 'payment_method', 'payment_account', 'purchase_date', 'notes', 'discount_percent', 'vat_percent'],
            'sample' => [
                ['Tech Supplies Ltd', 'PROD-001', '50', '850.00', 'INV-2026-001', 'credit', '', '2026-06-18 10:00:00', 'Bulk restock', '0', '0'],
                ['Tech Supplies Ltd', 'PROD-002', '20', '12.50', 'INV-2026-001', 'credit', '', '2026-06-18 10:00:00', 'Bulk restock', '0', '0'],
                ['Global Electronics', 'PROD-003', '10', '4.00', 'INV-2026-002', 'cash', 'Cash Account', '2026-06-18 11:30:00', 'Urgent purchase', '5', '0'],
            ]
        ]
    ];

    if (!isset($templates[$type])) {
        die('Invalid type');
    }

    $template = $templates[$type];

    // Set headers for download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $type . '_template.csv"');

    $output = fopen('php://output', 'w');

    // Write headers
    fputcsv($output, $template['headers']);

    // Write sample data
    foreach ($template['sample'] as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

/**
 * Process purchases import
 */
function processPurchasesImport($db, $data)
{
    $imported = 0;
    $skipped = 0;
    $errors = [];

    // Group rows by supplier and invoice_number (or generate key if empty)
    $groups = [];
    foreach ($data as $row) {
        if (!empty($row['_error'])) {
            $skipped++;
            continue;
        }

        $supplierVal = trim($row['supplier'] ?? '');
        $supplier = $db->fetchOne("SELECT id FROM suppliers WHERE (company_name = ? OR supplier_code = ?) AND is_active = 1", [$supplierVal, $supplierVal]);
        if (!$supplier) {
            $skipped++;
            $errors[] = "Row {$row['_row_num']}: Supplier not found";
            continue;
        }
        $row['_supplier_id'] = $supplier['id'];

        $skuVal = trim($row['sku'] ?? '');
        $product = $db->fetchOne("SELECT id, name, unit, cost_price, average_cost, current_stock FROM products WHERE sku = ? AND is_active = 1", [$skuVal]);
        if (!$product) {
            $skipped++;
            $errors[] = "Row {$row['_row_num']}: Product SKU not found";
            continue;
        }
        $row['_product_id'] = $product['id'];
        $row['_product_name'] = $product['name'];
        $row['_unit'] = $product['unit'];
        $row['_product'] = $product;

        $invoiceNum = trim($row['invoice_number'] ?? '');
        if (empty($invoiceNum)) {
            // Generate automatic invoice number prefix if missing
            $invoiceNum = 'AUTO-INV-' . strtoupper(substr(md5(uniqid()), 0, 8));
            // Keep the same autogenerated key for the item row so it gets imported
            $groupKey = 'row_' . $row['_row_num'] . '_' . uniqid();
        } else {
            $groupKey = $supplier['id'] . '_' . $invoiceNum;
        }

        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                'supplier_id' => $supplier['id'],
                'invoice_number' => $invoiceNum,
                'payment_method' => strtolower(trim($row['payment_method'] ?? 'credit')),
                'payment_account' => trim($row['payment_account'] ?? ''),
                'purchase_date' => !empty(trim($row['purchase_date'] ?? '')) ? date('Y-m-d H:i:s', strtotime(trim($row['purchase_date']))) : date('Y-m-d H:i:s'),
                'notes' => trim($row['notes'] ?? ''),
                'discount_percent' => floatval($row['discount_percent'] ?? 0),
                'vat_percent' => floatval($row['vat_percent'] ?? 0),
                'items' => []
            ];
        }

        $groups[$groupKey]['items'][] = $row;
    }

    try {
        $db->beginTransaction();

        foreach ($groups as $groupKey => $group) {
            $result = importPurchaseGroup($db, $group);
            if ($result['success']) {
                $imported++;
            } else {
                $skipped += count($group['items']);
                $errors[] = "Purchase group ({$groupKey}): {$result['message']}";
            }
        }

        $db->commit();

        unset($_SESSION['import_preview']);
        $message = "Import complete: $imported purchase invoices created, $skipped item rows skipped / errored";
        if (!empty($errors)) {
            $message .= ". Errors: " . implode('; ', array_slice($errors, 0, 5));
        }

        redirect(BASE_URL . '/purchases', 'success', $message);
    } catch (Exception $e) {
        $db->rollback();
        error_log('Import purchases error: ' . $e->getMessage());
        redirect(BASE_URL . '/import/purchases', 'error', 'Import failed: ' . $e->getMessage());
    }
}

/**
 * Import a single purchase invoice group
 */
function importPurchaseGroup($db, $group)
{
    $supplierId = $group['supplier_id'];
    $purchaseDate = $group['purchase_date'];
    $paymentMethod = $group['payment_method'];
    $paymentAccount = $group['payment_account'];
    $notes = $group['notes'];
    $discountPct = $group['discount_percent'];
    $vatPct = $group['vat_percent'];
    $invoiceNumber = $group['invoice_number'];
    $userId = $_SESSION['user_id'] ?? null;

    // Calculate totals
    $subtotal = 0;
    foreach ($group['items'] as $item) {
        $qty = floatval($item['quantity']);
        $unitCost = floatval($item['unit_cost']);
        $subtotal += $qty * $unitCost;
    }

    $discountAmount = $subtotal * ($discountPct / 100);
    $afterDiscount = $subtotal - $discountAmount;
    $vatAmount = $afterDiscount * ($vatPct / 100);
    $totalAmount = $afterDiscount + $vatAmount;

    $amountPaid = ($paymentMethod === 'credit') ? 0 : $totalAmount;
    $amountDue = max(0, $totalAmount - $amountPaid);
    $paymentStatus = ($amountPaid >= $totalAmount) ? 'paid' : (($amountPaid > 0) ? 'partial' : 'unpaid');

    // 1. Resolve account
    $accountId = null;
    if ($amountPaid > 0 && $paymentMethod !== 'credit') {
        $expectedType = [
            'cash' => 'cash',
            'mobile' => 'mobile_money',
            'bank' => 'bank',
            'cheque' => 'bank'
        ][$paymentMethod] ?? 'cash';

        if (!empty($paymentAccount)) {
            $acct = $db->fetchOne("SELECT id FROM accounts WHERE (account_number = ? OR name = ?) AND is_active = 1", [$paymentAccount, $paymentAccount]);
            if ($acct) {
                $accountId = $acct['id'];
            }
        }

        if (!$accountId) {
            $acct = $db->fetchOne("SELECT id FROM accounts WHERE type = ? AND is_active = 1 LIMIT 1", [$expectedType]);
            if ($acct) {
                $accountId = $acct['id'];
            } else {
                $acct = $db->fetchOne("SELECT id FROM accounts WHERE type = 'cash' AND is_active = 1 LIMIT 1");
                if ($acct) {
                    $accountId = $acct['id'];
                }
            }
        }
    }

    // Generate purchase number
    $purchaseNumber = generatePurchaseNumberForImport($db, $purchaseDate);

    // 2. Insert purchase header
    $db->query("
        INSERT INTO purchases
            (purchase_number, supplier_id, purchase_date, subtotal, discount_percent,
             discount_amount, vat_percent, vat_amount, total_amount,
             payment_status, payment_method, amount_paid, amount_due,
             invoice_number, notes, user_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ", [
        $purchaseNumber,
        $supplierId,
        $purchaseDate,
        $subtotal,
        $discountPct,
        $discountAmount,
        $vatPct,
        $vatAmount,
        $totalAmount,
        $paymentStatus,
        $paymentMethod,
        $amountPaid,
        $amountDue,
        $invoiceNumber ?: null,
        $notes ?: null,
        $userId
    ]);

    $purchaseId = $db->lastInsertId();

    // 3. Insert purchase items, update stock & WMA cost
    foreach ($group['items'] as $item) {
        $p = $item['_product'];
        $qty = floatval($item['quantity']);
        $unitCost = floatval($item['unit_cost']);
        $lineTotal = $qty * $unitCost;

        // Insert line item
        $db->query("
            INSERT INTO purchase_items
                (purchase_id, product_id, product_name, quantity,
                 unit_cost, discount_percent, line_total)
            VALUES (?, ?, ?, ?, ?, 0, ?)
        ", [
            $purchaseId,
            $p['id'],
            $p['name'],
            $qty,
            $unitCost,
            $lineTotal
        ]);

        // Calculate new weighted average cost — company-wide, using the
        // maintained total (p.current_stock) as its weight, matching how
        // PurchaseController's own purchase-creation flow works.
        $currentStock   = floatval($p['current_stock']);
        $currentAvgCost = floatval($p['average_cost']);

        if ($currentStock + $qty > 0) {
            $newAverageCost = (($currentStock * $currentAvgCost) + ($qty * $unitCost)) / ($currentStock + $qty);
        } else {
            $newAverageCost = $unitCost;
        }

        // Update product's cost fields (company-wide; not branch-specific)
        $db->query("
            UPDATE products
            SET cost_price = ?,
                average_cost = ?,
                last_purchase_cost = ?,
                last_purchase_date = ?
            WHERE id = ?
        ", [$unitCost, $newAverageCost, $unitCost, $purchaseDate, $p['id']]);

        // Update this branch's actual stock quantity (also keeps
        // products.current_stock, used above, in sync)
        $branchIdForImport = activeBranchId();
        $branchPrevStock = getBranchStock($p['id'], $branchIdForImport);
        $branchNewStock  = $branchPrevStock + $qty;
        adjustBranchStock($db, $p['id'], $branchIdForImport, $qty);

        // Log stock movement
        $db->query("
            INSERT INTO stock_movements
                (product_id, movement_type, quantity, reference_type,
                 reference_id, previous_stock, new_stock, user_id, branch_id, created_at)
            VALUES (?, 'in', ?, 'purchase', ?, ?, ?, ?, ?, ?)
        ", [$p['id'], $qty, $purchaseId, $branchPrevStock, $branchNewStock, $userId, $branchIdForImport, $purchaseDate]);

        // Log cost history
        $changePercent = $currentAvgCost > 0
            ? (($newAverageCost - $currentAvgCost) / $currentAvgCost) * 100
            : 0;

        $db->query("
            INSERT INTO product_cost_history
                (product_id, purchase_id, old_cost, new_cost, unit_cost,
                 quantity, change_percent, stock_before, stock_after, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $p['id'],
            $purchaseId,
            $currentAvgCost,
            $newAverageCost,
            $unitCost,
            $qty,
            $changePercent,
            $currentStock,
            $currentStock + $qty,
            $purchaseDate
        ]);
    }

    // 4. Update supplier balance & log transaction
    $supplier = $db->fetchOne("SELECT * FROM suppliers WHERE id = ?", [$supplierId]);
    $balanceBefore = floatval($supplier['current_balance']);

    $db->query("
        INSERT INTO supplier_transactions
            (supplier_id, transaction_type, amount, balance_before,
             balance_after, reference_type, reference_id, payment_method, user_id, created_at)
        VALUES (?, 'purchase', ?, ?, ?, 'purchase', ?, ?, ?, ?)
    ", [
        $supplierId,
        -$totalAmount,
        $balanceBefore,
        $balanceBefore - $totalAmount,
        $purchaseId,
        $paymentMethod,
        $userId,
        $purchaseDate
    ]);

    if ($amountPaid > 0) {
        $db->query("
            INSERT INTO supplier_transactions
                (supplier_id, transaction_type, amount, balance_before,
                 balance_after, reference_type, reference_id, payment_method, user_id, created_at)
            VALUES (?, 'payment', ?, ?, ?, 'purchase', ?, ?, ?, ?)
        ", [
            $supplierId,
            $amountPaid,
            $balanceBefore - $totalAmount,
            $balanceBefore - $totalAmount + $amountPaid,
            $purchaseId,
            $paymentMethod,
            $userId,
            $purchaseDate
        ]);
    }

    $netEffect = $amountPaid - $totalAmount;
    $db->query("
        UPDATE suppliers
        SET current_balance  = current_balance + ?,
            total_purchases  = total_purchases + ?
        WHERE id = ?
    ", [$netEffect, $totalAmount, $supplierId]);

    // 5. Record withdrawal from financial account
    if ($amountPaid > 0 && $paymentMethod !== 'credit' && $accountId > 0) {
        $db->query("
            INSERT INTO account_transactions
                (account_id, transaction_type, amount, reference_type, reference_id, description, user_id, created_at)
            VALUES (?, 'withdrawal', ?, 'purchase', ?, ?, ?, ?)
        ", [
            $accountId,
            $amountPaid,
            $purchaseId,
            "Purchase #{$purchaseNumber} payment to {$supplier['company_name']}",
            $userId,
            $purchaseDate
        ]);

        $db->query("
            UPDATE accounts
            SET balance = balance - ?
            WHERE id = ?
        ", [$amountPaid, $accountId]);
    }

    return ['success' => true];
}

/**
 * Generate sequential purchase number for imports based on target date
 */
function generatePurchaseNumberForImport($db, $dateStr)
{
    $prefix = 'PUR-';
    $date = date('Ymd', strtotime($dateStr));

    $last = $db->fetchOne("
        SELECT purchase_number FROM purchases
        WHERE purchase_number LIKE ?
        ORDER BY id DESC LIMIT 1
    ", ["$prefix$date-%"]);

    if ($last) {
        $parts = explode('-', $last['purchase_number']);
        $num = intval(end($parts)) + 1;
    } else {
        $num = 1;
    }

    return $prefix . $date . '-' . str_pad($num, 4, '0', STR_PAD_LEFT);
}
