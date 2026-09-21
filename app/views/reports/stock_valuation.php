<?php include APP_PATH . '/views/layout/header.php'; ?>

<!-- Page Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">📦 Stock Valuation Report</h1>
        <p class="text-gray-500 text-sm mt-1">
            Current inventory value and analysis
            <?php if ($viewingScopeLabel ?? null): ?>
                — showing <strong><?= e($viewingScopeLabel) ?></strong>
            <?php endif; ?>
        </p>
    </div>
    <div class="flex gap-2">
        <button onclick="window.print()"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition">
            🖨 Print Report
        </button>
        <a href="<?= BASE_URL ?>/export/stock-valuation?category=<?= $category ?>&supplier=<?= $supplier ?>&low_stock=<?= $lowStock ?>&sort_by=<?= $sortBy ?>"
            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm transition">
            📥 Export to Excel
        </a>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-4 mb-6 no-print">
    <form method="GET" action="<?= BASE_URL ?>/reports/stock-valuation">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-3">

            <!-- Category Filter -->
            <div>
                <label class="block text-xs text-gray-600 mb-1 font-medium">Category</label>
                <select name="category" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Supplier Filter -->
            <div>
                <label class="block text-xs text-gray-600 mb-1 font-medium">Supplier</label>
                <select name="supplier" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All Suppliers</option>
                    <?php foreach ($suppliers as $sup): ?>
                        <option value="<?= $sup['id'] ?>" <?= $supplier == $sup['id'] ? 'selected' : '' ?>>
                            <?= e($sup['company_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Low Stock Filter -->
            <div>
                <label class="block text-xs text-gray-600 mb-1 font-medium">Stock Status</label>
                <select name="low_stock" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All Stock</option>
                    <option value="1" <?= $lowStock === '1' ? 'selected' : '' ?>>Low Stock Only</option>
                </select>
            </div>

            <!-- Sort By -->
            <div>
                <label class="block text-xs text-gray-600 mb-1 font-medium">Sort By</label>
                <select name="sort_by" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="value_desc" <?= $sortBy === 'value_desc' ? 'selected' : '' ?>>Value (High-Low)</option>
                    <option value="value_asc" <?= $sortBy === 'value_asc' ? 'selected' : '' ?>>Value (Low-High)</option>
                    <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>Name (A-Z)</option>
                    <option value="stock_desc" <?= $sortBy === 'stock_desc' ? 'selected' : '' ?>>Stock (High-Low)</option>
                    <option value="stock_asc" <?= $sortBy === 'stock_asc' ? 'selected' : '' ?>>Stock (Low-High)</option>
                </select>
            </div>

            <!-- Buttons -->
            <div class="flex gap-2 items-end">
                <button type="submit"
                    class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    Apply
                </button>
                <a href="<?= BASE_URL ?>/reports/stock-valuation"
                    class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    Reset
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <div class="text-gray-400 text-xs uppercase tracking-wide mb-1">Total Products</div>
                <div class="text-2xl font-bold text-gray-800"><?= number_format($summary['total_products']) ?></div>
                <div class="text-xs text-gray-500 mt-0.5"><?= number_format($summary['total_units']) ?> units</div>
            </div>
            <div class="bg-blue-100 rounded-full p-3">
                <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <div class="text-gray-400 text-xs uppercase tracking-wide mb-1">Stock Value</div>
                <div class="text-2xl font-bold text-green-600"><?= formatMoney($summary['total_value']) ?></div>
                <div class="text-xs text-gray-500 mt-0.5">At average cost</div>
            </div>
            <div class="bg-green-100 rounded-full p-3">
                <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <div class="text-gray-400 text-xs uppercase tracking-wide mb-1">Potential Profit</div>
                <div class="text-2xl font-bold text-purple-600"><?= formatMoney($summary['total_potential_profit']) ?></div>
                <div class="text-xs text-gray-500 mt-0.5">If all sold</div>
            </div>
            <div class="bg-purple-100 rounded-full p-3">
                <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <div class="text-gray-400 text-xs uppercase tracking-wide mb-1">Stock Alerts</div>
                <div class="text-2xl font-bold text-red-600"><?= number_format($summary['low_stock_count']) ?></div>
                <div class="text-xs text-gray-500 mt-0.5">
                    <?= number_format($summary['out_of_stock_count']) ?> out of stock
                </div>
            </div>
            <div class="bg-red-100 rounded-full p-3">
                <svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
        </div>
    </div>
</div>

<!-- Category Breakdown Chart -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-5">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Stock Value by Category</h2>
        <div style="height: 300px;">
            <canvas id="categoryChart"></canvas>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-5">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Top 10 Most Valuable Items</h2>
        <div class="space-y-3">
            <?php foreach ($topValuable as $idx => $item): ?>
                <div class="flex items-center gap-3">
                    <div class="text-sm font-bold text-gray-400 w-6"><?= $idx + 1 ?></div>
                    <div class="flex-1">
                        <div class="text-sm font-medium text-gray-800"><?= e($item['name']) ?></div>
                        <div class="text-xs text-gray-400"><?= number_format($item['current_stock']) ?> units @ <?= formatMoney($item['average_cost']) ?></div>
                    </div>
                    <div class="text-sm font-bold text-green-600">
                        <?= formatMoney($item['stock_value']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Products Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-4 py-3 border-b">
        <h3 class="font-bold text-gray-800">Detailed Stock Valuation</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Product</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Stock</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Avg Cost</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Stock Value</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Selling Price</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Profit/Unit</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Margin %</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Potential Profit</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($products as $product): ?>
                    <tr class="hover:bg-gray-50 <?= $product['current_stock'] <= $product['reorder_level'] ? 'bg-red-50' : '' ?>">
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-800"><?= e($product['name']) ?></div>
                            <div class="text-xs text-gray-400">
                                SKU: <?= e($product['sku']) ?>
                                <?php if (!empty($product['category_name'])): ?>
                                    · <?= e($product['category_name']) ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="font-semibold <?= $product['current_stock'] <= 0 ? 'text-red-600' : ($product['current_stock'] <= $product['reorder_level'] ? 'text-orange-600' : 'text-gray-800') ?>">
                                <?= number_format($product['current_stock']) ?>
                            </div>
                            <div class="text-xs text-gray-400"><?= e($product['unit']) ?></div>
                        </td>
                        <td class="px-4 py-3 text-right text-sm text-gray-700">
                            <?= formatMoney($product['average_cost']) ?>
                        </td>
                        <td class="px-4 py-3 text-right text-sm font-bold text-green-600">
                            <?= formatMoney($product['stock_value']) ?>
                        </td>
                        <td class="px-4 py-3 text-right text-sm text-gray-700">
                            <?= formatMoney($product['selling_price']) ?>
                        </td>
                        <td class="px-4 py-3 text-right text-sm text-gray-700">
                            <?= formatMoney($product['selling_price'] - $product['average_cost']) ?>
                        </td>
                        <td class="px-4 py-3 text-right text-sm">
                            <span class="<?= $product['profit_margin_pct'] < 10 ? 'text-red-600' : ($product['profit_margin_pct'] < 20 ? 'text-orange-600' : 'text-green-600') ?> font-semibold">
                                <?= number_format($product['profit_margin_pct'], 1) ?>%
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-sm font-bold text-purple-600">
                            <?= formatMoney($product['potential_profit']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-gray-400 text-sm">
                            No products found
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot class="bg-gray-50 border-t-2 font-bold">
                <tr>
                    <td colspan="3" class="px-4 py-3 text-right text-gray-700">TOTALS:</td>
                    <td class="px-4 py-3 text-right text-green-600"><?= formatMoney($summary['total_value']) ?></td>
                    <td colspan="3" class="px-4 py-3"></td>
                    <td class="px-4 py-3 text-right text-purple-600"><?= formatMoney($summary['total_potential_profit']) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Chart.js Script -->
<script src="<?= BASE_URL ?>/assets/js/chart.min.js"></script>
<script>
    // Category breakdown chart
    const categoryCtx = document.getElementById('categoryChart').getContext('2d');
    const categoryData = <?= json_encode($categoryBreakdown) ?>;
    new Chart(categoryCtx, {
        type: 'bar',
        data: {
            labels: categoryData.map(c => c.category_name),
            datasets: [{
                label: 'Stock Value',
                data: categoryData.map(c => parseFloat(c.stock_value)),
                backgroundColor: 'rgba(59, 130, 246, 0.7)',
                borderColor: 'rgb(59, 130, 246)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: (context) => '<?= CURRENCY_HOLDER ?> ' + context.parsed.y.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: (value) => '<?= CURRENCY_HOLDER ?> ' + value.toFixed(0)
                    }
                }
            }
        }
    });
</script>

<?php include APP_PATH . '/views/layout/footer.php'; ?>