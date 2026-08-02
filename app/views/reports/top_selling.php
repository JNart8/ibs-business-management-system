<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">⭐ Top Selling Products</h1>
        <p class="text-sm text-gray-500"><?= formatDate($dateFrom, 'd M Y') ?> to <?= formatDate($dateTo, 'd M Y') ?></p>
    </div>
    <div class="flex gap-2">
        <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">🖨 Print</button>
        <a href="<?= BASE_URL ?>/export/top-selling?period=<?= $period ?>&category=<?= $category ?>&limit=<?= $limit ?>"
            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm">📥 Export CSV</a>
    </div>
</div>

<!-- Filters -->
<div class="bg-white shadow p-4 mb-6 no-print">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-3">
        <div class="md:col-span-2">
            <label class="block text-xs text-gray-600 mb-1 font-medium">Period</label>
            <select name="period" onchange="this.form.submit()" class="w-full px-3 py-2 border rounded-lg text-sm">
                <option value="this_week" <?= $period === 'this_week' ? 'selected' : '' ?>>This Week</option>
                <option value="this_month" <?= $period === 'this_month' ? 'selected' : '' ?>>This Month</option>
                <option value="this_year" <?= $period === 'this_year' ? 'selected' : '' ?>>This Year</option>
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-600 mb-1 font-medium">Category</label>
            <select name="category" class="w-full px-3 py-2 border rounded-lg text-sm">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-600 mb-1 font-medium">Show Top</label>
            <select name="limit" class="w-full px-3 py-2 border rounded-lg text-sm">
                <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>Top 10</option>
                <option value="20" <?= $limit == 20 ? 'selected' : '' ?>>Top 20</option>
                <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>Top 50</option>
            </select>
        </div>
        <div class="flex items-end"><button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg">Apply</button></div>
    </form>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="bg-white shadow p-4">
        <div class="text-xs text-gray-400 uppercase mb-1">Products Sold</div>
        <div class="text-2xl font-bold text-blue-600"><?= number_format($summary['products_sold']) ?></div>
    </div>
    <div class="bg-white shadow p-4">
        <div class="text-xs text-gray-400 uppercase mb-1">Total Units</div>
        <div class="text-2xl font-bold text-gray-800"><?= number_format($summary['total_units']) ?></div>
    </div>
    <div class="bg-white shadow p-4">
        <div class="text-xs text-gray-400 uppercase mb-1">Total Revenue</div>
        <div class="text-2xl font-bold text-green-600"><?= formatMoney($summary['total_revenue']) ?></div>
    </div>
    <div class="bg-white shadow p-4">
        <div class="text-xs text-gray-400 uppercase mb-1">Total Profit</div>
        <div class="text-2xl font-bold text-purple-600"><?= formatMoney($summary['total_profit']) ?></div>
    </div>
</div>

<!-- Top Products by Quantity -->
<div class="bg-white shadow overflow-hidden mb-6">
    <div class="px-4 py-3 border-b">
        <h3 class="font-bold">Top Products by Quantity Sold</h3>
    </div>
    <table class="w-full">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">#</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Product</th>
                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600">Qty Sold</th>
                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600">Revenue</th>
                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600">Profit</th>
                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600">Margin %</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            <?php foreach ($topByQuantity as $idx => $product): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 text-sm"><?= $idx + 1 ?></td>
                    <td class="px-4 py-2">
                        <div class="font-medium"><?= e($product['name']) ?></div>
                        <div class="text-xs text-gray-400"><?= e($product['sku']) ?></div>
                    </td>
                    <td class="px-4 py-2 text-right font-bold text-blue-600"><?= formatQty($product['total_quantity']) ?></td>
                    <td class="px-4 py-2 text-right"><?= formatMoney($product['total_revenue']) ?></td>
                    <td class="px-4 py-2 text-right text-green-600"><?= formatMoney($product['total_profit']) ?></td>
                    <td class="px-4 py-2 text-right font-semibold"><?= number_format($product['profit_margin_pct'], 1) ?>%</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>