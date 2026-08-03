<?php

/**
 * High-Performance Entry Point
 * Optimized for speed on XAMPP/cPanel
 */

ob_start();

define('APP_START', true);

require_once __DIR__ . '/../app/config/app.php';

// ── Build clean path ──────────────────────────────────────────
$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = dirname($_SERVER['SCRIPT_NAME']);

$basePath = rtrim($scriptName, '/');
$path     = str_replace($basePath, '', parse_url($requestUri, PHP_URL_PATH));
$path     = '/' . trim($path, '/');
$path     = str_replace('/public', '', $path);

$method = $_SERVER['REQUEST_METHOD'];

// ── Public routes (no login required) ────────────────────────
$publicRoutes = ['/login', '/logout'];
$isPublic     = in_array($path, $publicRoutes);


// ── Auth guard ────────────────────────────────────────────────
if (!$isPublic && !isLoggedIn()) {
    // Only remember the destination if it's a real page, not an asset
    $isAsset = preg_match('/\.(ico|png|jpg|gif|css|js|svg|woff|woff2|ttf)$/i', $path);
    if (!$isAsset) {
        $_SESSION['redirect_after_login'] = BASE_URL . $path;
    }
    header('Location: ' . BASE_URL . '/login');
    exit;
}

// ── Single-session enforcement ────────────────────────────────
// A newer login for this account (elsewhere) invalidates this session.
// See enforceSingleSession() in functions.php for the full rationale.
if (!$isPublic && isLoggedIn()) {
    enforceSingleSession();
}

// ── Permission-based access control ───────────────────────────
// Define which permission each route prefix requires. Anything
// not listed here is available to any logged-in user (matches
// today's behavior for /pos, /sales, /dashboard, /reports, /account).
// See app/config/plans.php for plan-tier gating (a separate,
// orthogonal system — a route can require both a permission AND
// a plan feature).
//
// NOTE: '/purchases' is included here for the first time — it was
// previously reachable by any logged-in user via direct URL (the
// dashboard only *hid* the button for cashiers, it didn't actually
// block the route). See ARCHITECTURE.md §5.10.
$permissionRestrictions = [
    '/users'                => 'users.manage',
    '/settings'             => 'settings.manage',
    '/roles'                => 'roles.manage',
    '/products'             => 'products.manage',
    '/categories'           => 'categories.manage',
    '/stock'                => 'stock.manage',
    '/suppliers'            => 'suppliers.manage',
    '/purchases'            => 'purchases.manage',
    '/customers'            => 'customers.manage',
    '/transactions'         => 'transactions.manage',
    '/financial-accounts'   => 'financial_accounts.access',
    '/expenses'             => 'expenses.manage',
    '/distributor'          => 'distributor.manage',
    '/suspense'             => 'suspense.manage',
    '/branches'             => 'branches.manage',
];

if (!$isPublic && isLoggedIn()) {
    $role = currentUser()['role'] ?? '';

    // Cashiers need these read-only lookup endpoints to operate the POS.
    // Keep the exception exact so product/customer management routes remain protected.
    $cashierPosLookupRoutes = [
        '/products/search',
        '/customers/search',
    ];
    $isCashierPosLookup = $role === 'cashier'
        && $method === 'GET'
        && in_array($path, $cashierPosLookupRoutes, true);

    foreach ($permissionRestrictions as $prefix => $permission) {
        if ($isCashierPosLookup) {
            continue;
        }

        if (strpos($path, $prefix) === 0 && !can($permission)) {
            http_response_code(403);
            echo '<!DOCTYPE html>
            <html>
            <head>
                <title>Access Denied</title>
                <style>
                    body { font-family: Arial; text-align: center; padding: 80px; background: #f8fafc; }
                    .box { display:inline-block; background:#fff; border-radius:12px;
                           padding:50px 60px; box-shadow:0 4px 20px rgba(0,0,0,.08); }
                    h1 { color: #ef4444; font-size: 2rem; margin-bottom: 8px; }
                    p  { color: #6b7280; margin-bottom: 24px; }
                    a  { background:#3b82f6; color:#fff; padding:10px 24px;
                         border-radius:8px; text-decoration:none; font-weight:600; }
                    a:hover { background:#2563eb; }
                </style>
            </head>
            <body>
                <div class="box">
                    <div style="font-size:3rem">🚫</div>
                    <h1>Access Denied</h1>
                    <p>Your account does not have permission to view this page.</p>
                    <a href="' . BASE_URL . '/">← Go to Dashboard</a>
                </div>
            </body>
            </html>';
            ob_end_flush();
            exit;
        }
    }
}

// ── Plan / feature-tier access control ──────────────────────────
// Which plan feature a route prefix requires. Anything not listed
// here is available on every plan (Core included). Order matters:
// more specific prefixes are listed so they're matched correctly
// even though this is a simple strpos() prefix check.
// See app/config/plans.php for what each plan includes.
$planFeatureMap = [
    '/reports/receivables'  => 'advanced_reports',
    '/reports/payables'     => 'advanced_reports',
    '/reports/profit-loss'  => 'advanced_reports',
    '/reports/dead-stock'   => 'advanced_reports',
    '/reports/profit-margin' => 'advanced_reports',
    '/import'               => 'imports_exports',
    '/export'               => 'imports_exports',
    '/suspense'             => 'suspense',
    '/financial-accounts/store' => 'manage_financial_accounts',
    '/expenses'             => 'expenses',
    '/distributor'          => 'distributor',
    '/roles'                => 'advanced_permissions',
];

if (!$isPublic && isLoggedIn()) {
    foreach ($planFeatureMap as $prefix => $feature) {
        if (strpos($path, $prefix) === 0 && !planAllows($feature)) {
            http_response_code(403);
            echo '<!DOCTYPE html>
            <html>
            <head>
                <title>Upgrade Required</title>
                <style>
                    body { font-family: Arial; text-align: center; padding: 80px; background: #f8fafc; }
                    .box { display:inline-block; background:#fff; border-radius:12px;
                           padding:50px 60px; box-shadow:0 4px 20px rgba(0,0,0,.08); }
                    h1 { color: #d97706; font-size: 2rem; margin-bottom: 8px; }
                    p  { color: #6b7280; margin-bottom: 24px; }
                    a  { background:#3b82f6; color:#fff; padding:10px 24px;
                         border-radius:8px; text-decoration:none; font-weight:600; }
                    a:hover { background:#2563eb; }
                </style>
            </head>
            <body>
                <div class="box">
                    <div style="font-size:3rem">🔒</div>
                    <h1>Upgrade Required</h1>
                    <p>This feature isn\'t included in your ' . e(currentPlanDefinition()['label']) . ' plan. Contact us to upgrade.</p>
                    <a href="' . BASE_URL . '/">← Go to Dashboard</a>
                </div>
            </body>
            </html>';
            ob_end_flush();
            exit;
        }
    }

    // ── Multi-branch add-on gate ─────────────────────────────
    // Not a plain plan-tier feature (see hasMultiBranch()), so it's
    // checked separately from the $planFeatureMap loop above.
    if (strpos($path, '/branches') === 0 && !hasMultiBranch()) {
        http_response_code(403);
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Upgrade Required</title>
            <style>
                body { font-family: Arial; text-align: center; padding: 80px; background: #f8fafc; }
                .box { display:inline-block; background:#fff; border-radius:12px;
                       padding:50px 60px; box-shadow:0 4px 20px rgba(0,0,0,.08); }
                h1 { color: #d97706; font-size: 2rem; margin-bottom: 8px; }
                p  { color: #6b7280; margin-bottom: 24px; }
                a  { background:#3b82f6; color:#fff; padding:10px 24px;
                     border-radius:8px; text-decoration:none; font-weight:600; }
                a:hover { background:#2563eb; }
            </style>
        </head>
        <body>
            <div class="box">
                <div style="font-size:3rem">🔒</div>
                <h1>Upgrade Required</h1>
                <p>Multi-branch isn\'t enabled for this account. Contact us to add it.</p>
                <a href="' . BASE_URL . '/">← Go to Dashboard</a>
            </div>
        </body>
        </html>';
        ob_end_flush();
        exit;
    }
}

// ── Route to controller ───────────────────────────────────────
if ($path === '/' || $path === '' || $path === '/dashboard') {
    require APP_PATH . '/controllers/DashboardController.php';
} elseif (strpos($path, '/products') === 0) {
    require APP_PATH . '/controllers/ProductController.php';
} elseif (strpos($path, '/categories') === 0) {
    require APP_PATH . '/controllers/CategoryController.php';
} elseif (strpos($path, '/customers') === 0) {
    require APP_PATH . '/controllers/CustomerController.php';
} elseif (strpos($path, '/transactions') === 0) {
    require APP_PATH . '/controllers/TransactionsController.php';
} elseif (strpos($path, '/sales') === 0 || strpos($path, '/pos') === 0) {
    require APP_PATH . '/controllers/SaleController.php';
} elseif (strpos($path, '/reports') === 0) {
    require_once APP_PATH . '/controllers/ReportsController.php';
} elseif (strpos($path, '/stock') === 0) {
    require APP_PATH . '/controllers/StockController.php';
} elseif (strpos($path, '/suppliers') === 0) {
    require APP_PATH . '/controllers/SupplierController.php';
} elseif (strpos($path, '/purchases') === 0 || strpos($path, '/create-purchase') === 0) {
    require_once APP_PATH . '/controllers/PurchaseController.php';
} elseif (strpos($path, '/distributor') === 0) {
    require_once APP_PATH . '/controllers/DistributorController.php';
} elseif (strpos($path, '/users') === 0) {
    require APP_PATH . '/controllers/UserController.php';
} elseif (strpos($path, '/roles') === 0) {
    require APP_PATH . '/controllers/RoleController.php';
} elseif (strpos($path, '/branches') === 0) {
    require APP_PATH . '/controllers/BranchController.php';
} elseif (strpos($path, '/settings') === 0) {
    require APP_PATH . '/controllers/SettingsController.php';
} elseif (strpos($path, '/import') === 0) {
    require APP_PATH . '/controllers/ImportController.php';
} elseif (strpos($path, '/export') === 0) {
    require APP_PATH . '/controllers/ExportController.php';
} elseif (strpos($path, '/financial-accounts') === 0) {
    require APP_PATH . '/controllers/FinancialAccountsController.php';
} elseif (strpos($path, '/expenses') === 0) {
    require APP_PATH . '/controllers/ExpensesController.php';
} elseif (strpos($path, '/suspense') === 0) {
    require APP_PATH . '/controllers/SuspenseController.php';
} elseif (strpos($path, '/account') === 0) {
    require APP_PATH . '/controllers/AccountController.php';
} elseif ($path === '/login' || $path === '/logout') {
    require APP_PATH . '/controllers/AuthController.php';
} else {
    http_response_code(404);
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>404 - Not Found</title>
        <style>
            body { font-family: Arial; text-align: center; padding: 50px; }
            h1 { color: #333; }
            a { color: #3b82f6; text-decoration: none; }
        </style>
    </head>
    <body>
        <h1>404 - Page Not Found</h1>
        <p>The page you are looking for does not exist.</p>
        <a href="' . BASE_URL . '/">← Go to Dashboard</a>
    </body>
    </html>';
}

ob_end_flush();
