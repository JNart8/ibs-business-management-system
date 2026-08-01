<?php include APP_PATH . '/views/layout/header.php'; ?>

<!-- Page Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">📈 Profit Margin Analysis</h1>
        <p class="text-sm text-gray-500">Product profitability breakdown</p>
    </div>
    <div class="flex gap-2">
        <button onclick="window.print()"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition">
            🖨 Print
        </button>
        <a href="<?= BASE_URL ?>/export/profit-margin?category=<?= $category ?>&sort_by=<?= $sortBy ?>"
            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm transition">
            📥 Export CSV
        </a>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-4 mb-6 no-print">
    <form method="GET" action="<?= BASE_URL ?>/reports/profit-margin">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <!-- Category Filter -->
            <div>
                <label class="block text-xs text-gray-600 mb-1 font-medium">Category</label>
                <select name="category" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Sort By -->
            <div>
                <label class="block text-xs text-gray-600 mb-1 font-medium">Sort By</label>
                <select name="sort_by" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="margin_desc" <?= $sortBy === 'margin_desc' ? 'selected' : '' ?>>Margin % (High-Low)</option>
                    <option value="margin_asc" <?= $sortBy === 'margin_asc' ? 'selected' : '' ?>>Margin % (Low-High)</option>
                    <option value="profit_desc" <?= $sortBy === 'profit_desc' ? 'selected' : '' ?>>Profit/Unit (High-Low)</option>
                    <option value="revenue_desc" <?= $sortBy === 'revenue_desc' ? 'selected' : '' ?>>Potential Revenue</option>
                    <option value="stock_desc" <?= $sortBy === 'stock_desc' ? 'selected' : '' ?>>Stock (High-Low)</option>
                </select>
            </div>

            <!-- Apply Button -->
            <div class="flex items-end">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    Apply Filters
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Summary Cards - COMPACT VERSION -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-3">
        <div class="text-xs text-gray-400 uppercase tracking-wide mb-1">Products</div>
        <div class="text-xl font-bold text-gray-800"><?= number_format($summary['total_products']) ?></div>
        <div class="text-xs text-gray-500 mt-0.5">In stock</div>
    </div>

    <div class="bg-white rounded-lg shadow p-3">
        <div class="text-xs text-gray-400 uppercase tracking-wide mb-1">Avg Margin</div>
        <div class="text-xl font-bold text-blue-600"><?= number_format($summary['avg_margin'], 1) ?>%</div>
        <div class="text-xs text-gray-500 mt-0.5">Overall profitability</div>
    </div>

    <div class="bg-white rounded-lg shadow p-3">
        <div class="text-xs text-gray-400 uppercase tracking-wide mb-1">Low Margin</div>
        <div class="text-xl font-bold text-red-600"><?= number_format($summary['low_margin_count']) ?></div>
        <div class="text-xs text-gray-500 mt-0.5">Below 10%</div>
    </div>

    <div class="bg-white rounded-lg shadow p-3">
        <div class="text-xs text-gray-400 uppercase tracking-wide mb-1">High Margin</div>
        <div class="text-xl font-bold text-green-600"><?= number_format($summary['high_margin_count']) ?></div>
        <div class="text-xs text-gray-500 mt-0.5">30% or more</div>
    </div>
</div>

<!-- Margin Distribution -->
<div class="bg-white rounded-lg shadow p-5 mb-6">
    <h3 class="text-lg font-bold text-gray-800 mb-4">Margin Distribution</h3>
    <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
        <?php foreach ($marginDistribution as $dist): ?>
            <?php
            $colorClass = match ($dist['margin_range']) {
                'Negative' => 'border-red-500 bg-red-50',
                '0-10%' => 'border-orange-500 bg-orange-50',
                '10-20%' => 'border-yellow-500 bg-yellow-50',
                '20-30%' => 'border-blue-500 bg-blue-50',
                '30-50%' => 'border-green-500 bg-green-50',
                '50%+' => 'border-purple-500 bg-purple-50',
                default => 'border-gray-300 bg-gray-50'
            };
            ?>
            <div class="border-l-4 <?= $colorClass ?> rounded p-3 text-center">
                <div class="text-xs font-semibold text-gray-700"><?= $dist['margin_range'] ?></div>
                <div class="text-2xl font-bold text-gray-800 mt-1"><?= $dist['product_count'] ?></div>
                <div class="text-xs text-gray-500 mt-1"><?= formatMoney($dist['stock_value']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Products Table - FIXED LAYOUT -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-4 py-3 border-b bg-gray-50">
        <h3 class="font-bold text-gray-800">Product Margin Analysis</h3>
    </div>

    <!-- Table Container with Horizontal Scroll -->
    <div class="overflow-x-auto">
        <table class="w-full min-w-max">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase whitespace-nowrap">Product</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase whitespace-nowrap">Avg Cost</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase whitespace-nowrap">Selling Price</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase whitespace-nowrap">Profit/Unit</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase whitespace-nowrap">Margin %</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase whitespace-nowrap">Stock Qty</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase whitespace-nowrap">Stock Value</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase whitespace-nowrap">Potential Profit</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($products as $product): ?>
                    <?php
                    $marginColor = $product['profit_margin_pct'] < 10 ? 'text-red-600' : ($product['profit_margin_pct'] < 20 ? 'text-yellow-600' : 'text-green-600');
                    $marginBg = $product['profit_margin_pct'] < 10 ? 'bg-red-50' : '';
                    ?>
                    <tr class="hover:bg-gray-50 <?= $marginBg ?>">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="font-medium text-gray-800"><?= e($product['name']) ?></div>
                            <div class="text-xs text-gray-400"><?= e($product['sku']) ?></div>
                        </td>
                        <td class="px-4 py-3 text-right text-sm text-gray-700 whitespace-nowrap">
                            <?= formatMoney($product['average_cost']) ?>
                        </td>
                        <td class="px-4 py-3 text-right text-sm text-gray-700 whitespace-nowrap">
                            <?= formatMoney($product['selling_price']) ?>
                        </td>
                        <td class="px-4 py-3 text-right text-sm font-semibold text-gray-800 whitespace-nowrap">
                            <?= formatMoney($product['profit_per_unit']) ?>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <span class="font-bold <?= $marginColor ?> text-sm">
                                <?= number_format($product['profit_margin_pct'], 1) ?>%
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-sm text-gray-700 whitespace-nowrap">
                            <?= number_format($product['current_stock']) ?>
                        </td>
                        <td class="px-4 py-3 text-right text-sm text-gray-700 whitespace-nowrap">
                            <?= formatMoney($product['stock_value']) ?>
                        </td>
                        <td class="px-4 py-3 text-right text-sm font-semibold text-purple-600 whitespace-nowrap">
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
            <?php if (!empty($products)): ?>
                <tfoot class="bg-gray-50 border-t-2 font-bold">
                    <tr>
                        <td colspan="5" class="px-4 py-3 text-right text-gray-700">TOTALS:</td>
                        <td class="px-4 py-3 text-right text-gray-800"><?= number_format(array_sum(array_column($products, 'current_stock'))) ?></td>
                        <td class="px-4 py-3 text-right text-blue-600"><?= formatMoney($summary['total_stock_value']) ?></td>
                        <td class="px-4 py-3 text-right text-purple-600"><?= formatMoney($summary['total_potential_profit']) ?></td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>