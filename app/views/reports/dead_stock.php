<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">💀 Dead Stock Report</h1>
        <p class="text-sm text-gray-500">
            Products with no recent sales
            <?php if ($viewingScopeLabel ?? null): ?>
                — showing <strong><?= e($viewingScopeLabel) ?></strong>
            <?php endif; ?>
        </p>
    </div>
    <div class="flex gap-2">
        <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">🖨 Print</button>
        <a href="<?= BASE_URL ?>/export/dead-stock?period=<?= $period ?>"
            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm">📥 Export CSV</a>
    </div>
</div>

<!-- Filters -->
<div class="bg-white shadow p-4 mb-6 no-print">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div>
            <label class="block text-xs text-gray-600 mb-1 font-medium">No Sales In</label>
            <select name="period" onchange="this.form.submit()" class="w-full px-3 py-2 border rounded-lg text-sm">
                <option value="30" <?= $period == 30 ? 'selected' : '' ?>>30 days</option>
                <option value="60" <?= $period == 60 ? 'selected' : '' ?>>60 days</option>
                <option value="90" <?= $period == 90 ? 'selected' : '' ?>>90 days</option>
                <option value="180" <?= $period == 180 ? 'selected' : '' ?>>180 days</option>
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
        <div class="flex items-end"><button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg">Apply</button></div>
    </form>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="bg-white shadow p-4">
        <div class="text-xs text-gray-400 uppercase mb-1">Dead Stock Items</div>
        <div class="text-2xl font-bold text-gray-800"><?= number_format($summary['total_items']) ?></div>
    </div>
    <div class="bg-white shadow p-4">
        <div class="text-xs text-gray-400 uppercase mb-1">Total Units</div>
        <div class="text-2xl font-bold text-gray-800"><?= number_format($summary['total_units']) ?></div>
    </div>
    <div class="bg-white shadow p-4">
        <div class="text-xs text-gray-400 uppercase mb-1">Value Tied Up</div>
        <div class="text-2xl font-bold text-red-600"><?= formatMoney($summary['total_value']) ?></div>
    </div>
    <div class="bg-white shadow p-4">
        <div class="text-xs text-gray-400 uppercase mb-1">Potential Revenue</div>
        <div class="text-2xl font-bold text-green-600"><?= formatMoney($summary['potential_revenue']) ?></div>
    </div>
</div>

<!-- Dead Stock Table -->
<div class="bg-white shadow overflow-hidden">
    <div class="px-4 py-3 border-b">
        <h3 class="font-bold">Slow Moving Inventory (<?= $period ?>+ days without sales)</h3>
    </div>
    <table class="w-full">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Product</th>
                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600">Stock</th>
                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600">Value</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Last Sale</th>
                <th class="px-4 py-2 text-center text-xs font-semibold text-gray-600">Days Idle</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Recommendation</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            <?php foreach ($products as $product): ?>
                <?php
                $days = $product['days_since_last_sale'] ?? 999;
                $recommendation = $days > 180 ? 'Clearance Sale' : ($days > 90 ? 'Discount 30%' : 'Bundle or Promote');
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2">
                        <div class="font-medium"><?= e($product['name']) ?></div>
                        <div class="text-xs text-gray-400"><?= e($product['sku']) ?></div>
                    </td>
                    <td class="px-4 py-2 text-right"><?= number_format($product['current_stock']) ?></td>
                    <td class="px-4 py-2 text-right font-semibold text-red-600"><?= formatMoney($product['stock_value']) ?></td>
                    <td class="px-4 py-2 text-sm">
                        <?= $product['last_sale_date'] ? date('d M Y', strtotime($product['last_sale_date'])) : 'Never' ?>
                    </td>
                    <td class="px-4 py-2 text-center">
                        <span class="px-2 py-1 rounded text-xs font-semibold <?= $days > 180 ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700' ?>">
                            <?= $days ?> days
                        </span>
                    </td>
                    <td class="px-4 py-2 text-sm text-gray-600"><?= $recommendation ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($products)): ?>
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-400">🎉 No dead stock found!</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>