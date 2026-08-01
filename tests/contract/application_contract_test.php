<?php

declare(strict_types=1);

function registerApplicationContractTests(TestRunner $runner): void
{
    $controllerDir = projectPath('app/controllers');
    $viewDir = projectPath('app/views');

    $runner->test('all PHP application and test files pass syntax validation', function (): void {
        $roots = [projectPath('app'), projectPath('public'), projectPath('tests')];
        $errors = [];
        foreach ($roots as $root) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
            foreach ($files as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') continue;
                exec('php -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $output, $code);
                if ($code !== 0) $errors[] = implode("\n", $output);
                $output = [];
            }
        }
        assertSame([], $errors, implode("\n", $errors));
    });

    $runner->test('front controller maps every functional controller', function () use ($controllerDir): void {
        $front = file_get_contents(projectPath('public/index.php'));
        $ignored = ['DashboardController.php']; // dashboard is mapped by the root route
        foreach (glob($controllerDir . '/*Controller.php') as $controller) {
            $name = basename($controller);
            if (in_array($name, $ignored, true)) continue;
            assertContains($name, $front, "Missing front-controller mapping for {$name}");
        }
        assertContains('DashboardController.php', $front);
    });

    $runner->test('all controller view includes resolve to existing templates', function () use ($controllerDir, $viewDir): void {
        $missing = [];
        foreach (glob($controllerDir . '/*.php') as $controller) {
            $source = file_get_contents($controller);
            preg_match_all("~APP_PATH\s*\.\s*['\"](/views/[^'\"]+\.php)['\"]~", $source, $matches);
            foreach ($matches[1] as $relative) {
                $target = dirname($viewDir) . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                if (!is_file($target)) $missing[] = basename($controller) . ': ' . $relative;
            }
        }
        assertSame([], $missing, 'Missing view files: ' . implode(', ', $missing));
    });

    $runner->test('every controller dispatcher action has a case or intentional default', function () use ($controllerDir): void {
        $withoutDispatch = ['DashboardController.php', 'ExportController.php', 'ImportController.php', 'SettingsController.php'];
        foreach (glob($controllerDir . '/*.php') as $controller) {
            if (in_array(basename($controller), $withoutDispatch, true)) continue;
            $source = file_get_contents($controller);
            assertTrue(str_contains($source, 'switch ($action)'), basename($controller) . ' has no action dispatcher');
            assertTrue((bool) preg_match('/case\s+[\'\"][^\'\"]+[\'\"]\s*:/', $source), basename($controller) . ' has no action cases');
        }
    });

    $runner->test('role restrictions protect all administrative route groups', function (): void {
        $front = file_get_contents(projectPath('public/index.php'));
        foreach (['users', 'settings', 'products', 'categories', 'stock', 'suppliers', 'customers', 'transactions', 'financial-accounts', 'expenses', 'distributor', 'suspense'] as $route) {
            assertContains("'/{$route}'", $front, "Missing role restriction for /{$route}");
        }
        assertContains("'/users'      => ['admin']", $front);
    });

    $runner->test('sale discount mode is configurable and retained on each sale', function (): void {
        $schema = file_get_contents(projectPath('database/schema.sql'));
        $controller = file_get_contents(projectPath('app/controllers/SaleController.php'));
        $pos = file_get_contents(projectPath('app/views/sales/pos.php'));
        assertContains('sale_discount_type', $schema);
        assertContains('discount_type', $schema);
        assertContains("\$discountType === 'flat'", $controller);
        assertContains('min($subtotal, $discountValue)', $controller);
        assertContains('min($lineSubtotal, max(0, $itemDisc))', $controller);
        assertContains('discountAmount', $controller);
        assertContains('discount_value: this.saleDiscount', $pos);
    });

    $runner->test('cashiers can use only the read-only POS lookup routes', function (): void {
        $front = file_get_contents(projectPath('public/index.php'));
        assertContains("'/products/search'", $front);
        assertContains("'/customers/search'", $front);
        assertContains("\$role === 'cashier'", $front);
        assertContains("\$method === 'GET'", $front);
        assertContains('in_array($path, $cashierPosLookupRoutes, true)', $front);
    });

    $runner->test('database schema defines every core functional domain', function (): void {
        $sql = strtolower(implode("\n", array_map('file_get_contents', glob(projectPath('database/*.sql')))));
        $tables = ['users', 'categories', 'products', 'customers', 'suppliers', 'sales', 'sale_items', 'purchases', 'purchase_items', 'stock_movements', 'customer_transactions', 'supplier_transactions', 'accounts', 'account_transactions', 'expenses'];
        foreach ($tables as $table) {
            assertTrue((bool) preg_match('/create\s+table(?:\s+if\s+not\s+exists)?\s+`?' . preg_quote($table, '/') . '`?/i', $sql), "No CREATE TABLE found for {$table}");
        }
    });

    $runner->test('all expected reports and exports remain registered', function (): void {
        $reports = file_get_contents(projectPath('app/controllers/ReportsController.php'));
        $exports = file_get_contents(projectPath('app/controllers/ExportController.php'));
        foreach (['sales', 'stock-valuation', 'receivables', 'payables', 'profit-loss', 'top-selling', 'low-stock', 'dead-stock', 'profit-margin'] as $type) {
            assertContains("case '{$type}':", $reports, "Missing report {$type}");
            assertContains("case '{$type}':", $exports, "Missing export {$type}");
        }
    });

    $runner->test('sales exports provide one reusable row per sold item', function (): void {
        $exports = file_get_contents(projectPath('app/controllers/ExportController.php'));
        assertContains('INNER JOIN sale_items si ON s.id = si.sale_id', $exports);
        assertContains("'Item Discount (" . "' . CURRENCY_HOLDER . '" . ")'", $exports);
        assertContains("'Line Total (" . "' . CURRENCY_HOLDER . '" . ")'", $exports);
        assertContains("'Total Item Rows:'", $exports);
        assertContains('$uniqueSales', $exports);
    });

    $runner->test('import contracts cover all supported master and purchasing data', function (): void {
        $source = file_get_contents(projectPath('app/controllers/ImportController.php'));
        foreach (['categories', 'products', 'suppliers', 'customers', 'purchases'] as $type) {
            assertContains("case '{$type}':", $source, "Missing import contract {$type}");
        }
    });

    $runner->test('opening inventory initializes weighted average cost', function (): void {
        $products = file_get_contents(projectPath('app/controllers/ProductController.php'));
        $imports = file_get_contents(projectPath('app/controllers/ImportController.php'));

        assertContains('cost_price, average_cost, selling_price', $products);
        assertContains('average_cost, current_stock, reorder_level', $imports);
        assertContains('WHEN COALESCE(average_cost, 0) = 0 THEN ?', $imports);
    });

    $runner->test('sales history provides today financial summary cards', function (): void {
        $controller = file_get_contents(projectPath('app/controllers/SaleController.php'));
        $view = file_get_contents(projectPath('app/views/sales/index.php'));

        foreach (['total_sales', 'cash_sales', 'momo_sales', 'outstanding'] as $metric) {
            assertContains("AS {$metric}", $controller, "Missing today's {$metric} calculation");
            assertContains("\$todayStats['{$metric}']", $view, "Missing today's {$metric} card");
        }
        assertContains("payment_method = 'cash'", $controller);
        assertContains("payment_method = 'mobile'", $controller);
    });
}
