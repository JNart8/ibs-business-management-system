<?php include APP_PATH . '/views/layout/header.php'; ?>

<!-- Page Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">⚠️ Low Stock Alert</h1>
        <p class="text-sm text-gray-500">
            Items needing reorder
            <?php if ($viewingScopeLabel ?? null): ?>
                — showing <strong><?= e($viewingScopeLabel) ?></strong>
            <?php endif; ?>
        </p>
    </div>
    <div class="flex gap-2">
        <button onclick="window.print()"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition">
            🖨 Print
        </button>
        <a href="<?= BASE_URL ?>/export/low-stock?severity=<?= $severity ?>&category=<?= $category ?>"
            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm transition">
            📥 Export CSV
        </a>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-4 mb-6 no-print">
    <form method="GET" action="<?= BASE_URL ?>/reports/low-stock">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <!-- Severity Filter -->
            <div>
                <label class="block text-xs text-gray-600 mb-1 font-medium">Severity</label>
                <select name="severity" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="">All Low Stock</option>
                    <option value="critical" <?= $severity === 'critical' ? 'selected' : '' ?>>🔴 Critical (Out of Stock)</option>
                    <option value="warning" <?= $severity === 'warning' ? 'selected' : '' ?>>🟡 Warning (Below Reorder)</option>
                </select>
            </div>

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
        <div class="text-xs text-gray-400 uppercase tracking-wide mb-1">Total Items</div>
        <div class="text-xl font-bold text-gray-800"><?= number_format($summary['total_items']) ?></div>
        <div class="text-xs text-gray-500 mt-0.5">Need attention</div>
    </div>

    <div class="bg-white rounded-lg shadow p-3">
        <div class="text-xs text-gray-400 uppercase tracking-wide mb-1">Out of Stock</div>
        <div class="text-xl font-bold text-red-600"><?= number_format($summary['out_of_stock']) ?></div>
        <div class="text-xs text-gray-500 mt-0.5">Order immediately</div>
    </div>

    <div class="bg-white rounded-lg shadow p-3">
        <div class="text-xs text-gray-400 uppercase tracking-wide mb-1">Low Stock</div>
        <div class="text-xl font-bold text-yellow-600"><?= number_format($summary['low_stock']) ?></div>
        <div class="text-xs text-gray-500 mt-0.5">Below reorder level</div>
    </div>

    <div class="bg-white rounded-lg shadow p-3">
        <div class="text-xs text-gray-400 uppercase tracking-wide mb-1">Est. Reorder Cost</div>
        <div class="text-xl font-bold text-blue-600"><?= formatMoney($summary['estimated_reorder_cost']) ?></div>
        <div class="text-xs text-gray-500 mt-0.5">Budget needed</div>
    </div>
</div>

<!-- Low Stock Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-4 py-3 border-b bg-gray-50">
        <h3 class="font-bold text-gray-800">Products Needing Reorder</h3>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Product</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Current Stock</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Reorder At</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Status</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Days Left</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Suggested Order</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Supplier</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($products as $product): ?>
                    <?php
                    $isCritical = $product['current_stock'] == 0;
                    $rowBg = $isCritical ? 'bg-red-50' : 'hover:bg-gray-50';
                    ?>
                    <tr class="<?= $rowBg ?>">
                        <!-- Product Name -->
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-800"><?= e($product['name']) ?></div>
                            <div class="text-xs text-gray-400"><?= e($product['sku']) ?> • <?= e($product['category_name'] ?? 'N/A') ?></div>
                        </td>

                        <!-- Current Stock -->
                        <td class="px-4 py-3 text-center">
                            <span class="font-bold <?= $isCritical ? 'text-red-600' : 'text-yellow-600' ?>">
                                <?= number_format($product['current_stock']) ?>
                            </span>
                        </td>

                        <!-- Reorder Level -->
                        <td class="px-4 py-3 text-center text-sm text-gray-600">
                            <?= number_format($product['reorder_level']) ?>
                        </td>

                        <!-- Status Badge -->
                        <td class="px-4 py-3 text-center">
                            <?php if ($isCritical): ?>
                                <span class="px-2 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                                    🔴 OUT OF STOCK
                                </span>
                            <?php else: ?>
                                <span class="px-2 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">
                                    🟡 LOW STOCK
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Days Until Stockout -->
                        <td class="px-4 py-3 text-center">
                            <?php if ($product['days_until_stockout']): ?>
                                <span class="text-xs px-2 py-1 rounded <?= $product['days_until_stockout'] <= 3 ? 'bg-red-100 text-red-700 font-semibold' : 'bg-yellow-100 text-yellow-700' ?>">
                                    <?= $product['days_until_stockout'] ?> days
                                </span>
                            <?php else: ?>
                                <span class="text-xs text-gray-400">-</span>
                            <?php endif; ?>
                        </td>

                        <!-- Suggested Order Quantity -->
                        <td class="px-4 py-3 text-right">
                            <span class="font-bold text-blue-600"><?= number_format($product['suggested_order_qty']) ?></span>
                            <span class="text-xs text-gray-500 ml-1"><?= e($product['unit']) ?></span>
                        </td>

                        <!-- Supplier -->
                        <td class="px-4 py-3">
                            <?php if ($product['supplier_name']): ?>
                                <div class="text-sm text-gray-700"><?= e($product['supplier_name']) ?></div>
                                <?php if ($product['supplier_phone']): ?>
                                    <div class="text-xs text-gray-400">📞 <?= e($product['supplier_phone']) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-xs text-gray-400">No supplier</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-400 text-sm">
                            🎉 All stock levels are good! No items need reordering.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Action Recommendations -->
<?php if (!empty($products)): ?>
    <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
        <h4 class="font-bold text-blue-800 mb-2">📋 Action Items:</h4>
        <ul class="space-y-1 text-sm text-blue-700">
            <?php if ($summary['out_of_stock'] > 0): ?>
                <li>• <strong>URGENT:</strong> <?= $summary['out_of_stock'] ?> item(s) out of stock - order immediately!</li>
            <?php endif; ?>
            <?php if ($summary['low_stock'] > 0): ?>
                <li>• <?= $summary['low_stock'] ?> item(s) below reorder level - plan purchase order</li>
            <?php endif; ?>
            <li>• Estimated budget needed: <strong><?= formatMoney($summary['estimated_reorder_cost']) ?></strong></li>
            <li>• Review with suppliers and confirm delivery times</li>
        </ul>
    </div>
<?php endif; ?>

<?php include APP_PATH . '/views/layout/footer.php'; ?>