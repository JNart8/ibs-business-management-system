<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Inventory System') ?> - <?= APP_NAME ?></title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/ibs_logo_ntg.png">

    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tailwind.css">
    <script defer src="<?= BASE_URL ?>/assets/js/alpine.min.js"></script>

    <style>
        [x-cloak] {
            display: none !important;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3b82f6;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        @media print {
            .no-print {
                display: none !important;
            }
        }

        .nav-link {
            padding: 6px 12px;
            border-radius: 6px;
            transition: background 0.2s;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.25);
            font-weight: 600;
        }

        /* Dropdown styles */
        .dropdown-content {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            min-width: 200px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            z-index: 50;
            padding: 0.5rem 0;
        }

        .dropdown:hover .dropdown-content {
            display: block;
        }

        .dropdown-item {
            display: block;
            padding: 0.5rem 1rem;
            color: #374151;
            font-size: 0.875rem;
            transition: background 0.2s;
            white-space: nowrap;
        }

        .dropdown-item:hover {
            background-color: #f3f4f6;
        }

        /* Mobile dropdown adjustments */
        @media (max-width: 768px) {
            .dropdown-content {
                position: static;
                box-shadow: none;
                background-color: transparent;
                padding-left: 1rem;
                min-width: 100%;
            }

            .dropdown-item {
                color: white;
                padding: 0.5rem 1rem;
            }

            .dropdown-item:hover {
                background-color: rgba(255, 255, 255, 0.15);
            }
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen flex flex-col">

    <?php
    $currentPath = str_replace(BASE_URL, '', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    $currentPath = '/' . trim($currentPath, '/');

    function isActive($path, $currentPath)
    {
        if ($path === '/' && $currentPath === '/') return 'active';
        if ($path !== '/' && strpos($currentPath, $path) === 0) return 'active';
        return '';
    }

    // Check if any sub-pages are active for dropdown highlighting
    function isGroupActive($paths, $currentPath)
    {
        foreach ($paths as $path) {
            if (strpos($currentPath, $path) === 0) return 'active';
        }
        return '';
    }
    ?>

    <!-- Navigation Bar -->
    <nav class="bg-blue-600 text-white shadow-lg no-print" x-data="{ 
        mobileOpen: false, 
        inventoryOpen: false, 
        peopleOpen: false,
        financeOpen: false,
        reportsOpen: false,
        adminOpen: false 
    }">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between py-3">

                <!-- Logo/Brand -->
                <a href="<?= BASE_URL ?>/" class="flex items-center gap-2">
                    <img src="<?= BASE_URL ?>/assets/images/ibs_logo_ntg.png"
                        alt="<?= APP_NAME ?>"
                        class="h-9 w-auto">
                    <span class="text-xl font-bold tracking-tight hidden sm:block">
                        <?= APP_NAME ?>
                    </span>
                </a>

                <!-- Desktop Menu -->
                <div class="hidden md:flex items-center space-x-1">

                    <?php if (canManageAnything()): ?>
                        <!-- Dashboard -->
                        <a href="<?= BASE_URL ?>/"
                            class="nav-link <?= isActive('/', $currentPath) ?>">
                            Dashboard
                        </a>
                    <?php endif; ?>

                    <!-- POS (Most Used) -->
                    <a href="<?= BASE_URL ?>/pos"
                        class="nav-link <?= isActive('/pos', $currentPath) ?>">
                        🛒 POS
                    </a>

                    <?php if (canManageAnything()): ?>

                        <!-- Inventory Dropdown -->
                        <div class="relative dropdown">
                            <button
                                class="nav-link inline-flex items-center gap-1 <?= isGroupActive(['/products', '/categories', '/stock', '/transfers'], $currentPath) ?>">
                                Inventory
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div class="dropdown-content">
                                <a href="<?= BASE_URL ?>/products" class="dropdown-item">
                                    📦 Products
                                </a>
                                <a href="<?= BASE_URL ?>/categories" class="dropdown-item">
                                    🗂️ Categories
                                </a>
                                <a href="<?= BASE_URL ?>/stock" class="dropdown-item">
                                    📊 Stock Management
                                </a>
                                <?php if (can('stock.transfer') && hasMultiBranch()): ?>
                                <a href="<?= BASE_URL ?>/transfers" class="dropdown-item">
                                    🔄 Stock Transfers
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- People Dropdown -->
                        <div class="relative dropdown">
                            <button
                                class="nav-link inline-flex items-center gap-1 <?= isGroupActive(['/customers', '/suppliers'], $currentPath) ?>">
                                People
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div class="dropdown-content">
                                <a href="<?= BASE_URL ?>/customers" class="dropdown-item">
                                    👥 Customers
                                </a>
                                <a href="<?= BASE_URL ?>/suppliers" class="dropdown-item">
                                    🚚 Suppliers
                                </a>
                            </div>
                        </div>

                        <!-- Finance Dropdown -->
                        <div class="relative dropdown">
                            <button
                                class="nav-link inline-flex items-center gap-1 <?= isGroupActive(['/sales', '/purchases', '/transactions', '/financial-accounts', '/expenses', '/distributor', '/suspense'], $currentPath) ?>">
                                Finance
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div class="dropdown-content">
                                <a href="<?= BASE_URL ?>/sales" class="dropdown-item">
                                    💰 Sales
                                </a>
                                <a href="<?= BASE_URL ?>/purchases" class="dropdown-item">
                                    🛍️ Purchases
                                </a>
                                <?php if (planAllows('distributor')): ?>
                                <a href="<?= BASE_URL ?>/distributor" class="dropdown-item">
                                    🚚 Direct Deliveries
                                </a>
                                <?php endif; ?>
                                <a href="<?= BASE_URL ?>/transactions" class="dropdown-item">
                                    💳 Customer Transactions
                                </a>
                                <a href="<?= BASE_URL ?>/financial-accounts" class="dropdown-item">
                                    💳 Financial Accounts
                                </a>
                                <?php if (planAllows('expenses')): ?>
                                <a href="<?= BASE_URL ?>/expenses" class="dropdown-item">
                                    💸 Expenses
                                </a>
                                <?php endif; ?>
                                <?php if (planAllows('suspense')): ?>
                                <a href="<?= BASE_URL ?>/suspense" class="dropdown-item">
                                    🕵️ Suspense Account
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Reports Dropdown -->
                        <div class="relative dropdown">
                            <button
                                class="nav-link inline-flex items-center gap-1 <?= isActive('/reports', $currentPath) ?>">
                                Reports
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div class="dropdown-content">
                                <a href="<?= BASE_URL ?>/reports" class="dropdown-item font-semibold">
                                    📊 Sales Report
                                </a>
                                <div class="border-t my-1"></div>
                                <a href="<?= BASE_URL ?>/reports/stock-valuation" class="dropdown-item">
                                    📦 Stock Valuation
                                </a>
                                <a href="<?= BASE_URL ?>/reports/top-selling" class="dropdown-item">
                                    ⭐ Top Selling
                                </a>
                                <a href="<?= BASE_URL ?>/reports/low-stock" class="dropdown-item">
                                    ⚠️ Low Stock Alert
                                </a>
                                <?php if (planAllows('advanced_reports')): ?>
                                <div class="border-t my-1"></div>
                                <a href="<?= BASE_URL ?>/reports/receivables" class="dropdown-item">
                                    💳 Receivables
                                </a>
                                <a href="<?= BASE_URL ?>/reports/payables" class="dropdown-item">
                                    🏦 Payables
                                </a>
                                <a href="<?= BASE_URL ?>/reports/profit-loss" class="dropdown-item">
                                    💰 Profit & Loss
                                </a>
                                <a href="<?= BASE_URL ?>/reports/dead-stock" class="dropdown-item">
                                    💀 Dead Stock
                                </a>
                                <a href="<?= BASE_URL ?>/reports/profit-margin" class="dropdown-item">
                                    📈 Profit Margins
                                </a>
                                <?php if (hasMultiBranch()): ?>
                                <a href="<?= BASE_URL ?>/reports/customer-credit" class="dropdown-item">
                                    🔁 Customer Credit Settlement
                                </a>
                                <?php endif; ?>
                                <?php else: ?>
                                <div class="border-t my-1"></div>
                                <span class="dropdown-item text-gray-400 cursor-not-allowed" title="Upgrade to unlock">
                                    🔒 Advanced reports (upgrade)
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php endif; ?>

                    <?php if (can('users.manage') || (can('roles.manage') && planAllows('advanced_permissions')) || can('settings.manage') || (can('branches.manage') && hasMultiBranch()) || can('audit.view')): ?>
                        <!-- Administration Dropdown -->
                        <div class="relative dropdown">
                            <button
                                class="nav-link inline-flex items-center gap-1 <?= isGroupActive(['/users', '/roles', '/settings', '/branches', '/audit'], $currentPath) ?>">
                                Administration
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div class="dropdown-content">
                                <?php if (can('users.manage')): ?>
                                <a href="<?= BASE_URL ?>/users" class="dropdown-item">
                                    👤 Users
                                </a>
                                <?php endif; ?>
                                <?php if (can('branches.manage') && hasMultiBranch()): ?>
                                <a href="<?= BASE_URL ?>/branches" class="dropdown-item">
                                    🏢 Branches
                                </a>
                                <?php endif; ?>
                                <?php if (can('roles.manage') && planAllows('advanced_permissions')): ?>
                                <a href="<?= BASE_URL ?>/roles" class="dropdown-item">
                                    🔑 Roles
                                </a>
                                <?php endif; ?>
                                <?php if (can('audit.view')): ?>
                                <a href="<?= BASE_URL ?>/audit" class="dropdown-item">
                                    📋 Audit Log
                                </a>
                                <?php endif; ?>
                                <?php if (can('settings.manage')): ?>
                                <a href="<?= BASE_URL ?>/settings" class="dropdown-item">
                                    ⚙️ Settings
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Right Side: User + Mobile Button -->
                <div class="flex items-center space-x-3">
                    <?php if (isLoggedIn()): ?>
                        <?php if (hasMultiBranch()): $myBranches = userBranches($_SESSION['user_id']); ?>
                            <?php if (count($myBranches) > 1): ?>
                            <form action="<?= BASE_URL ?>/account/switch-branch" method="POST"
                                class="hidden md:block" onchange="this.submit()">
                                <?= csrfField() ?>
                                <select name="branch_id"
                                    class="text-sm bg-blue-700 hover:bg-blue-800 text-white border-0 rounded-full px-3 py-1 focus:ring-2 focus:ring-white">
                                    <?php foreach ($myBranches as $mb): ?>
                                        <option value="<?= $mb['id'] ?>" <?= activeBranchId() == $mb['id'] ? 'selected' : '' ?>>
                                            🏢 <?= e($mb['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                            <?php elseif (count($myBranches) === 1): ?>
                            <span class="hidden md:block text-sm text-blue-100 px-2" title="Your assigned branch">
                                🏢 <?= e($myBranches[0]['name']) ?>
                            </span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>/account"
                            class="hidden md:block text-sm bg-blue-700 hover:bg-blue-800 px-3 py-1 rounded-full transition">
                            <svg class="w-4 h-4 text-white inline-block align-text-bottom mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                            </svg>
                            <?= e(currentUser()['full_name'] ?? 'Account') ?>
                        </a>
                        <a href="<?= BASE_URL ?>/logout"
                            class="bg-red-500 hover:bg-red-600 px-3 py-1.5 rounded text-sm transition">
                            Logout
                        </a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/login"
                            class="bg-green-500 hover:bg-green-600 px-3 py-1.5 rounded text-sm transition">
                            Login
                        </a>
                    <?php endif; ?>

                    <!-- Mobile Menu Toggle -->
                    <button class="md:hidden p-2 rounded hover:bg-blue-700 transition"
                        @click="mobileOpen = !mobileOpen">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path x-show="!mobileOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            <path x-show="mobileOpen" x-cloak stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Mobile Menu -->
            <div x-show="mobileOpen" x-cloak
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                class="md:hidden pb-4 border-t border-blue-500 mt-2 pt-3">
                <div class="flex flex-col space-y-1">
                    <a href="<?= BASE_URL ?>/"
                        class="nav-link <?= isActive('/', $currentPath) ?>"
                        @click="mobileOpen = false">
                        🏠 Dashboard
                    </a>
                    <a href="<?= BASE_URL ?>/pos"
                        class="nav-link <?= isActive('/pos', $currentPath) ?>"
                        @click="mobileOpen = false">
                        🛒 POS
                    </a>

                    <?php if (canManageAnything()): ?>

                        <!-- Inventory Section (Mobile) -->
                        <div class="flex flex-col">
                            <button @click="inventoryOpen = !inventoryOpen"
                                class="nav-link flex items-center justify-between w-full text-left">
                                <span>📦 Inventory</span>
                                <svg class="w-4 h-4 transition-transform"
                                    :class="{ 'rotate-180': inventoryOpen }"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div x-show="inventoryOpen" x-cloak class="pl-4 mt-1 space-y-1">
                                <a href="<?= BASE_URL ?>/products"
                                    class="nav-link block"
                                    @click="mobileOpen = false">
                                    📦 Products
                                </a>
                                <a href="<?= BASE_URL ?>/categories"
                                    class="nav-link block"
                                    @click="mobileOpen = false">
                                    🗂️ Categories
                                </a>
                                <a href="<?= BASE_URL ?>/stock"
                                    class="nav-link block"
                                    @click="mobileOpen = false">
                                    📊 Stock Management
                                </a>
                                <?php if (can('stock.transfer') && hasMultiBranch()): ?>
                                <a href="<?= BASE_URL ?>/transfers"
                                    class="nav-link block"
                                    @click="mobileOpen = false">
                                    🔄 Stock Transfers
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- People Section (Mobile) -->
                        <div class="flex flex-col">
                            <button @click="peopleOpen = !peopleOpen"
                                class="nav-link flex items-center justify-between w-full text-left">
                                <span>👥 People</span>
                                <svg class="w-4 h-4 transition-transform"
                                    :class="{ 'rotate-180': peopleOpen }"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div x-show="peopleOpen" x-cloak class="pl-4 mt-1 space-y-1">
                                <a href="<?= BASE_URL ?>/customers"
                                    class="nav-link block"
                                    @click="mobileOpen = false">
                                    👥 Customers
                                </a>
                                <a href="<?= BASE_URL ?>/suppliers"
                                    class="nav-link block"
                                    @click="mobileOpen = false">
                                    🚚 Suppliers
                                </a>
                            </div>
                        </div>

                        <!-- Finance Section (Mobile) -->
                        <div class="flex flex-col">
                            <button @click="financeOpen = !financeOpen"
                                class="nav-link flex items-center justify-between w-full text-left">
                                <span>💰 Finance</span>
                                <svg class="w-4 h-4 transition-transform"
                                    :class="{ 'rotate-180': financeOpen }"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div x-show="financeOpen" x-cloak class="pl-4 mt-1 space-y-1">
                                <a href="<?= BASE_URL ?>/sales"
                                    class="nav-link block"
                                    @click="mobileOpen = false">
                                    💰 Sales
                                </a>
                                <a href="<?= BASE_URL ?>/purchases"
                                    class="nav-link block"
                                    @click="mobileOpen = false">
                                    🛍️ Purchases
                                </a>
                                <?php if (planAllows('distributor')): ?>
                                <a href="<?= BASE_URL ?>/distributor"
                                    class="nav-link block"
                                    @click="mobileOpen = false">
                                    🚚 Direct Deliveries
                                </a>
                                <?php endif; ?>
                                <a href="<?= BASE_URL ?>/transactions"
                                    class="nav-link block"
                                    @click="mobileOpen = false">
                                    💳 Customer Transactions
                                </a>
                                <a href="<?= BASE_URL ?>/financial-accounts"
                                    class="nav-link block"
                                    @click="mobileOpen = false">
                                    💳 Financial Accounts
                                </a>
                                <?php if (planAllows('expenses')): ?>
                                <a href="<?= BASE_URL ?>/expenses"
                                    class="nav-link block"
                                    @click="mobileOpen = false">
                                    💸 Expenses
                                </a>
                                <?php endif; ?>
                                <?php if (planAllows('suspense')): ?>
                                <a href="<?= BASE_URL ?>/suspense"
                                    class="nav-link block"
                                    @click="mobileOpen = false">
                                    🕵️ Suspense Account
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Reports Section (Mobile) -->
                        <div class="flex flex-col">
                            <button @click="reportsOpen = !reportsOpen"
                                class="nav-link flex items-center justify-between w-full text-left">
                                <span>📊 Reports</span>
                                <svg class="w-4 h-4 transition-transform"
                                    :class="{ 'rotate-180': reportsOpen }"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div x-show="reportsOpen" x-cloak class="pl-4 mt-1 space-y-1">
                                <a href="<?= BASE_URL ?>/reports"
                                    class="nav-link block font-semibold"
                                    @click="mobileOpen = false">
                                    📊 Sales Report
                                </a>
                                <a href="<?= BASE_URL ?>/reports/stock-valuation"
                                    class="nav-link block"
                                    @click="mobileOpen = false">
                                    📦 Stock Valuation
                                </a>
                                <a href="<?= BASE_URL ?>/reports/top-selling"
                                    class="nav-link block"
                                    @click="mobileOpen = false">
                                    ⭐ Top Selling
                                </a>
                                <a href="<?= BASE_URL ?>/reports/low-stock"
                                    class="nav-link block"
                                    @click="mobileOpen = false">
                                    ⚠️ Low Stock Alert
                                </a>
                                <?php if (planAllows('advanced_reports')): ?>
                                <a href="<?= BASE_URL ?>/reports/receivables"
                                    class="nav-link block"
                                    @click="mobileOpen = false">
                                    💳 Receivables
                                </a>
                                <a href="<?= BASE_URL ?>/reports/payables"
                                    class="nav-link block"
                                    @click="mobileOpen = false">
                                    🏦 Payables
                                </a>
                                <a href="<?= BASE_URL ?>/reports/profit-loss"
                                    class="nav-link block"
                                    @click="mobileOpen = false">
                                    💰 Profit & Loss
                                </a>
                                <a href="<?= BASE_URL ?>/reports/dead-stock"
                                    class="nav-link block"
                                    @click="mobileOpen = false">
                                    💀 Dead Stock
                                </a>
                                <a href="<?= BASE_URL ?>/reports/profit-margin"
                                    class="nav-link block"
                                    @click="mobileOpen = false">
                                    📈 Profit Margins
                                </a>
                                <?php if (hasMultiBranch()): ?>
                                <a href="<?= BASE_URL ?>/reports/customer-credit"
                                    class="nav-link block"
                                    @click="mobileOpen = false">
                                    🔁 Customer Credit Settlement
                                </a>
                                <?php endif; ?>
                                <?php else: ?>
                                <span class="nav-link block text-gray-400">🔒 Advanced reports (upgrade)</span>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php endif; ?>

                    <?php if (can('users.manage') || (can('roles.manage') && planAllows('advanced_permissions')) || can('settings.manage') || (can('branches.manage') && hasMultiBranch()) || can('audit.view')): ?>
                        <!-- Administration Section (Mobile) -->
                        <div class="flex flex-col">
                            <button @click="adminOpen = !adminOpen"
                                class="nav-link flex items-center justify-between w-full text-left">
                                <span>🛠️ Administration</span>
                                <svg class="w-4 h-4 transition-transform"
                                    :class="{ 'rotate-180': adminOpen }"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div x-show="adminOpen" x-cloak class="pl-4 mt-1 space-y-1">
                                <?php if (can('users.manage')): ?>
                                <a href="<?= BASE_URL ?>/users"
                                    class="nav-link block <?= isActive('/users', $currentPath) ?>"
                                    @click="mobileOpen = false">
                                    👤 Users
                                </a>
                                <?php endif; ?>
                                <?php if (can('branches.manage') && hasMultiBranch()): ?>
                                <a href="<?= BASE_URL ?>/branches"
                                    class="nav-link block <?= isActive('/branches', $currentPath) ?>"
                                    @click="mobileOpen = false">
                                    🏢 Branches
                                </a>
                                <?php endif; ?>
                                <?php if (can('roles.manage') && planAllows('advanced_permissions')): ?>
                                <a href="<?= BASE_URL ?>/roles"
                                    class="nav-link block <?= isActive('/roles', $currentPath) ?>"
                                    @click="mobileOpen = false">
                                    🔑 Roles
                                </a>
                                <?php endif; ?>
                                <?php if (can('audit.view')): ?>
                                <a href="<?= BASE_URL ?>/audit"
                                    class="nav-link block <?= isActive('/audit', $currentPath) ?>"
                                    @click="mobileOpen = false">
                                    📋 Audit Log
                                </a>
                                <?php endif; ?>
                                <?php if (can('settings.manage')): ?>
                                <a href="<?= BASE_URL ?>/settings"
                                    class="nav-link block <?= isActive('/settings', $currentPath) ?>"
                                    @click="mobileOpen = false">
                                    ⚙️ Settings
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (isLoggedIn()): ?>
                        <?php if (hasMultiBranch()): $myBranchesMobile = userBranches($_SESSION['user_id']); ?>
                            <?php if (count($myBranchesMobile) > 1): ?>
                            <div class="pt-2 border-t border-blue-500">
                                <p class="text-xs text-blue-200 px-1 mb-1">Active branch</p>
                                <form action="<?= BASE_URL ?>/account/switch-branch" method="POST" onchange="this.submit()">
                                    <?= csrfField() ?>
                                    <select name="branch_id"
                                        class="w-full text-sm bg-blue-700 text-white border border-blue-400 rounded-lg px-3 py-2">
                                        <?php foreach ($myBranchesMobile as $mb): ?>
                                            <option value="<?= $mb['id'] ?>" <?= activeBranchId() == $mb['id'] ? 'selected' : '' ?>>
                                                🏢 <?= e($mb['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </div>
                            <?php elseif (count($myBranchesMobile) === 1): ?>
                            <div class="pt-2 border-t border-blue-500">
                                <p class="text-xs text-blue-200 px-1">🏢 <?= e($myBranchesMobile[0]['name']) ?></p>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <div class="pt-2 border-t border-blue-500">
                            <a href="<?= BASE_URL ?>/account"
                                class="nav-link <?= isActive('/account', $currentPath) ?>"
                                @click="mobileOpen = false">
                                👤 My Account
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-1 container mx-auto px-4 py-6">
