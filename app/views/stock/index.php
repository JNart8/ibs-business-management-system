<?php include APP_PATH . '/views/layout/header.php'; ?>

<!-- Header -->
<div class="flex flex-wrap justify-between items-center mb-6 gap-3">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Stock Management</h1>
        <p class="text-gray-500 mt-1 text-sm">
            Monitor and manage your inventory levels
            <?php if ($viewingBranchName ?? null): ?>
                — showing <strong><?= e($viewingBranchName) ?></strong>
            <?php endif; ?>
        </p>
    </div>
    <div class="flex gap-2 flex-wrap">
        <a href="<?= BASE_URL ?>/export/stocks<?= !empty($_GET) ? '?' . http_build_query($_GET) : '' ?>"
            class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg transition flex items-center gap-1">
            📤 Export
        </a>
        <a href="<?= BASE_URL ?>/stock/in"
            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition flex items-center gap-1">
            ↑ Stock In
        </a>
        <a href="<?= BASE_URL ?>/stock/out"
            class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition flex items-center gap-1">
            ↓ Stock Out
        </a>
        <a href="<?= BASE_URL ?>/stock/movements"
            class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition">
            📋 History
        </a>
        <a href="<?= BASE_URL ?>/stock/alerts"
            class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg transition">
            ⚠️ Alerts
            <?php if ($stats['out_of_stock'] + $stats['low_stock'] > 0): ?>
                <span class="ml-1 bg-white text-yellow-600 text-xs font-bold px-1.5 py-0.5 rounded-full">
                    <?= $stats['out_of_stock'] + $stats['low_stock'] ?>
                </span>
            <?php endif; ?>
        </a>
    </div>
</div>

<?= flashMessage() ?>

<!-- Stats -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white p-5 rounded-lg shadow">
        <div class="text-gray-400 text-xs mb-1">Total Products</div>
        <div class="text-2xl font-bold text-gray-800"><?= formatNumber($stats['total_products']) ?></div>
        <div class="text-xs text-gray-400 mt-1"><?= formatNumber($stats['total_units']) ?> total units</div>
    </div>
    <div class="bg-white p-5 rounded-lg shadow">
        <div class="text-gray-400 text-xs mb-1">Stock Value</div>
        <div class="text-2xl font-bold text-blue-600"><?= formatMoney($stats['total_value']) ?></div>
        <div class="text-xs text-gray-400 mt-1">At cost price</div>
    </div>
    <div class="bg-white p-5 rounded-lg shadow cursor-pointer hover:shadow-md transition"
        onclick="window.location='<?= BASE_URL ?>/stock?filter=low'">
        <div class="text-gray-400 text-xs mb-1">Low Stock</div>
        <div class="text-2xl font-bold text-yellow-500"><?= formatNumber($stats['low_stock']) ?></div>
        <div class="text-xs text-yellow-400 mt-1">Click to filter</div>
    </div>
    <div class="bg-white p-5 rounded-lg shadow cursor-pointer hover:shadow-md transition"
        onclick="window.location='<?= BASE_URL ?>/stock?filter=out'">
        <div class="text-gray-400 text-xs mb-1">Out of Stock</div>
        <div class="text-2xl font-bold text-red-500"><?= formatNumber($stats['out_of_stock']) ?></div>
        <div class="text-xs text-red-400 mt-1">Click to filter</div>
    </div>
</div>

<!-- Filters & Search -->
<div class="bg-white p-4 rounded-lg shadow mb-6">
    <form method="GET" action="<?= BASE_URL ?>/stock" class="flex flex-wrap gap-3">
        <input type="text" name="search"
            placeholder="Search by name, SKU or barcode..."
            value="<?= e($_GET['search'] ?? '') ?>"
            class="flex-1 min-w-48 px-4 py-2 border border-gray-300 rounded-lg
                      focus:ring-2 focus:ring-blue-500 focus:border-transparent">

        <select name="category"
            class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>"
                    <?= intval($_GET['category'] ?? 0) === intval($cat['id']) ? 'selected' : '' ?>>
                    <?= e($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="filter"
            class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            <option value="all" <?= ($_GET['filter'] ?? 'all') === 'all' ? 'selected' : '' ?>>All Stock</option>
            <option value="ok" <?= ($_GET['filter'] ?? '') === 'ok'  ? 'selected' : '' ?>>Healthy</option>
            <option value="low" <?= ($_GET['filter'] ?? '') === 'low' ? 'selected' : '' ?>>Low Stock</option>
            <option value="out" <?= ($_GET['filter'] ?? '') === 'out' ? 'selected' : '' ?>>Out of Stock</option>
        </select>

        <button type="submit"
            class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg transition">
            Search
        </button>
        <?php if (!empty($_GET['search']) || !empty($_GET['filter']) || !empty($_GET['category'])): ?>
            <a href="<?= BASE_URL ?>/stock"
                class="bg-gray-500 hover:bg-gray-600 text-white px-5 py-2 rounded-lg transition">
                Clear
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- Stock Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Current Stock</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Reorder At</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Stock Value</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Last Movement</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="8" class="px-6 py-16 text-center text-gray-400">
                            <div class="text-5xl mb-4">📦</div>
                            <p class="text-lg font-medium">No products found</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <?php
                        $statusConfig = [
                            'ok'  => ['class' => 'bg-green-100 text-green-700',  'label' => 'Healthy'],
                            'low' => ['class' => 'bg-yellow-100 text-yellow-700', 'label' => 'Low Stock'],
                            'out' => ['class' => 'bg-red-100 text-red-700',      'label' => 'Out of Stock'],
                        ];
                        $sc = $statusConfig[$p['stock_status']] ?? $statusConfig['ok'];
                        $rowClass = $p['stock_status'] === 'out'
                            ? 'bg-red-50' : ($p['stock_status'] === 'low' ? 'bg-yellow-50' : '');
                        ?>
                        <tr class="hover:bg-gray-50 transition <?= $rowClass ?>">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-800"><?= e($p['name']) ?></div>
                                <div class="text-xs text-gray-400"><?= e($p['sku']) ?></div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">
                                <?= e($p['category_name'] ?? '—') ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-lg font-bold
                                    <?= $p['stock_status'] === 'out' ? 'text-red-600'
                                        : ($p['stock_status'] === 'low' ? 'text-yellow-600' : 'text-gray-800') ?>">
                                    <?= $p['current_stock'] ?>
                                </span>
                                <span class="text-xs text-gray-400"> <?= e($p['unit']) ?></span>
                            </td>
                            <td class="px-4 py-3 text-center text-sm text-gray-500">
                                <?= $p['reorder_level'] ?> <?= e($p['unit']) ?>
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-medium text-gray-700">
                                <?= formatMoney($p['stock_value']) ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= $sc['class'] ?>">
                                    <?= $sc['label'] ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center text-xs text-gray-400">
                                <?= $p['last_movement'] ? formatDate($p['last_movement'], 'd M Y') : '—' ?>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-center gap-1">
                                    <a href="<?= BASE_URL ?>/stock/in?product=<?= $p['id'] ?>"
                                        class="p-1.5 rounded bg-green-50 text-green-600 hover:bg-green-100 transition"
                                        title="Stock In">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                    </a>
                                    <a href="<?= BASE_URL ?>/stock/out?product=<?= $p['id'] ?>"
                                        class="p-1.5 rounded bg-red-50 text-red-500 hover:bg-red-100 transition"
                                        title="Stock Out">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                                        </svg>
                                    </a>
                                    <a href="<?= BASE_URL ?>/stock/adjust/<?= $p['id'] ?>"
                                        class="p-1.5 rounded bg-purple-50 text-purple-600 hover:bg-purple-100 transition"
                                        title="Adjust">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                        </svg>
                                    </a>
                                    <a href="<?= BASE_URL ?>/stock/product/<?= $p['id'] ?>"
                                        class="p-1.5 rounded bg-blue-50 text-blue-600 hover:bg-blue-100 transition"
                                        title="View History">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                        </svg>
                                    </a>
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
            <span class="text-sm text-gray-500">Page <?= $page ?> of <?= $totalPages ?> — <?= $totalCount ?> products</span>
            <div class="flex gap-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($_GET['search'] ?? '') ?>&filter=<?= urlencode($_GET['filter'] ?? '') ?>"
                        class="px-3 py-1.5 bg-white border rounded text-sm hover:bg-gray-50">← Prev</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($_GET['search'] ?? '') ?>&filter=<?= urlencode($_GET['filter'] ?? '') ?>"
                        class="px-3 py-1.5 bg-white border rounded text-sm hover:bg-gray-50">Next →</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>