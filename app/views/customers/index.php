<?php include APP_PATH . '/views/layout/header.php'; ?>

<!-- Page Header -->

<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Customers</h1>
        <p class="text-gray-500 mt-1">Manage customers, credit limits and deposits</p>
    </div>

    <!-- Mobile: Dropdown -->
    <div class="sm:hidden" x-data="{ open: false }">
        <button @click="open = !open"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
            Actions
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
        <div x-show="open"
            @click.outside="open = false"
            class="absolute right-4 mt-2 w-48 bg-white rounded-lg shadow-lg border py-1 z-20">
            <a href="<?= BASE_URL ?>/customers/create"
                class="block px-4 py-2 hover:bg-gray-100 text-gray-700">
                + Add Customer
            </a>
            <a href="<?= BASE_URL ?>/import/customers"
                class="block px-4 py-2 hover:bg-gray-100 text-gray-700">
                📥 Import CSV
            </a>
            <a href="<?= BASE_URL ?>/export/customers"
                class="block px-4 py-2 hover:bg-gray-100 text-gray-700">
                📤 Export CSV
            </a>
        </div>
    </div>

    <!-- Desktop: Full Buttons -->
    <div class="hidden sm:flex gap-2">
        <a href="<?= BASE_URL ?>/customers/create"
            class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg shadow transition flex items-center gap-2">
            <span class="text-lg font-bold">+</span> Add Customer
        </a>
        <a href="<?= BASE_URL ?>/import/customers"
            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition">
            📥 Import CSV
        </a>
        <a href="<?= BASE_URL ?>/export/customers"
            class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg transition">
            📤 Export CSV
        </a>
    </div>
</div>

<?= flashMessage() ?>

<!-- Stats Row -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white p-5 rounded-lg shadow">
        <div class="text-gray-500 text-xs mb-1">Total Customers</div>
        <div class="text-2xl font-bold text-gray-800"><?= formatNumber($stats['total_customers']) ?></div>
    </div>
    <div class="bg-white p-5 rounded-lg shadow">
        <div class="text-gray-500 text-xs mb-1">Owing (Debt)</div>
        <div class="text-2xl font-bold text-red-600"><?= formatNumber($stats['owing_count']) ?></div>
        <div class="text-xs text-red-400 mt-1"><?= formatMoney($stats['total_owed']) ?> total</div>
    </div>
    <div class="bg-white p-5 rounded-lg shadow">
        <div class="text-gray-500 text-xs mb-1">Cash Balance</div>
        <div class="text-2xl font-bold text-green-600"><?= formatNumber($stats['credit_count']) ?></div>
        <div class="text-xs text-green-400 mt-1"><?= formatMoney($stats['total_credit']) ?> total</div>
    </div>
    <div class="bg-white p-5 rounded-lg shadow">
        <a href="<?= BASE_URL ?>/customers?filter=default" class="block">
            <div class="text-gray-500 text-xs mb-1">Walk-In Default</div>
            <div class="text-2xl font-bold text-blue-600">1</div>
            <div class="text-xs text-blue-400 mt-1">Click to view</div>
        </a>
    </div>
</div>

<!-- Search & Filter -->
<div class="bg-white p-4 rounded-lg shadow mb-6">
    <form method="GET" action="<?= BASE_URL ?>/customers" class="flex flex-wrap gap-3">
        <input
            type="text"
            name="search"
            placeholder="Search by name, phone or code..."
            value="<?= e($_GET['search'] ?? '') ?>"
            class="flex-1 min-w-48 px-4 py-2 border border-gray-300 rounded-lg
                   focus:ring-2 focus:ring-blue-500 focus:border-transparent">

        <!-- Filter Tabs -->
        <select name="filter"
            class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
            onchange="this.form.submit()">
            <option value="all" <?= ($_GET['filter'] ?? 'all') === 'all'     ? 'selected' : '' ?>>All Customers</option>
            <option value="active" <?= ($_GET['filter'] ?? '') === 'active'     ? 'selected' : '' ?>>Active Only</option>
            <option value="owing" <?= ($_GET['filter'] ?? '') === 'owing'      ? 'selected' : '' ?>>Owing Money</option>
            <option value="credit" <?= ($_GET['filter'] ?? '') === 'credit'     ? 'selected' : '' ?>>Has Credit</option>
        </select>

        <button type="submit"
            class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg transition">
            Search
        </button>
        <?php if (!empty($_GET['search']) || !empty($_GET['filter'])): ?>
            <a href="<?= BASE_URL ?>/customers"
                class="bg-gray-500 hover:bg-gray-600 text-white px-5 py-2 rounded-lg transition">
                Clear
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- Customer Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                    <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Credit Limit</th>
                    <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Balance</th>
                    <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase">Orders</th>
                    <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-16 text-center text-gray-400">
                            <div class="text-5xl mb-4">👥</div>
                            <p class="text-lg font-medium">No customers found</p>
                            <a href="<?= BASE_URL ?>/customers/create"
                                class="inline-block mt-4 bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700">
                                + Add Customer
                            </a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($customers as $c): ?>
                        <?php
                        $balance      = floatval($c['current_balance']);
                        $balanceColor = $balance < 0 ? 'text-red-600' : ($balance > 0 ? 'text-green-600' : 'text-gray-500');
                        $balanceLabel = $balance < 0 ? 'Owes ' . formatMoney(abs($balance))
                            : ($balance > 0 ? 'Credit ' . formatMoney($balance) : 'GHS 0.00');
                        ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-5 py-4">
                                <div class="font-semibold text-gray-800">
                                    <?= e($c['full_name']) ?>
                                    <?php if ($c['is_default']): ?>
                                        <span class="ml-1 text-xs bg-blue-100 text-blue-600 px-2 py-0.5 rounded-full">Walk-in</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-gray-400"><?= e($c['customer_code']) ?></div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="text-sm text-gray-700"><?= e($c['phone']) ?></div>
                                <?php if (!empty($c['email'])): ?>
                                    <div class="text-xs text-gray-400"><?= e($c['email']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 text-right text-sm text-gray-600">
                                <?= formatMoney($c['credit_limit']) ?>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <span class="text-sm font-semibold <?= $balanceColor ?>">
                                    <?= $balanceLabel ?>
                                </span>
                            </td>
                            <td class="px-5 py-4 text-center text-sm text-gray-600">
                                <?= safeInt($c['total_orders']) ?>
                                <?php if (!empty($c['last_purchase'])): ?>
                                    <div class="text-xs text-gray-400">
                                        <?= formatDate($c['last_purchase'], 'd M Y') ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 text-center">
                                <?php if ($c['is_active']): ?>
                                    <span class="bg-green-100 text-green-700 text-xs px-2 py-1 rounded-full font-semibold">Active</span>
                                <?php else: ?>
                                    <span class="bg-red-100 text-red-600 text-xs px-2 py-1 rounded-full font-semibold">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex justify-center gap-2">
                                    <!-- View -->
                                    <a href="<?= BASE_URL ?>/customers/view/<?= $c['id'] ?>"
                                        class="p-1.5 rounded bg-blue-50 text-blue-600 hover:bg-blue-100 transition" title="View">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>
                                    <!-- Deposit -->
                                    <a href="<?= BASE_URL ?>/customers/deposit/<?= $c['id'] ?>"
                                        class="p-1.5 rounded bg-green-50 text-green-600 hover:bg-green-100 transition" title="Add Deposit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                    </a>
                                    <!-- Edit -->
                                    <a href="<?= BASE_URL ?>/customers/edit/<?= $c['id'] ?>"
                                        class="p-1.5 rounded bg-yellow-50 text-yellow-600 hover:bg-yellow-100 transition" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    <!-- Delete -->
                                    <?php if (!$c['is_default']): ?>
                                        <a href="<?= BASE_URL ?>/customers/delete/<?= $c['id'] ?>"
                                            onclick="return confirmDelete('Delete customer <?= e($c['full_name']) ?>?')"
                                            class="p-1.5 rounded bg-red-50 text-red-500 hover:bg-red-100 transition" title="Delete">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="bg-gray-50 px-5 py-3 border-t flex items-center justify-between">
            <span class="text-sm text-gray-500">
                Page <?= $page ?> of <?= $totalPages ?> &mdash; <?= $totalCount ?> customers
            </span>
            <div class="flex gap-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($_GET['search'] ?? '') ?>&filter=<?= urlencode($_GET['filter'] ?? '') ?>"
                        class="px-3 py-1.5 bg-white border rounded hover:bg-gray-50 text-sm">← Prev</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($_GET['search'] ?? '') ?>&filter=<?= urlencode($_GET['filter'] ?? '') ?>"
                        class="px-3 py-1.5 bg-white border rounded hover:bg-gray-50 text-sm">Next →</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>