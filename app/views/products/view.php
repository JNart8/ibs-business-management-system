<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="mb-5 text-sm">
    <a href="<?= BASE_URL ?>/products" class="text-blue-600 hover:underline">← Back to Products</a>
</div>

<?= flashMessage() ?>

<!-- Product Header -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <div class="flex flex-wrap justify-between items-start gap-4">
        <div>
            <div class="flex items-center gap-3 mb-2">
                <h1 class="text-3xl font-bold text-gray-800"><?= e($product['name']) ?></h1>
                <?php if ($product['is_active']): ?>
                    <span class="bg-green-100 text-green-700 text-xs font-semibold px-2 py-1 rounded-full">Active</span>
                <?php else: ?>
                    <span class="bg-red-100 text-red-700 text-xs font-semibold px-2 py-1 rounded-full">Inactive</span>
                <?php endif; ?>
            </div>

            <div class="flex flex-wrap gap-3 mt-2 text-sm text-gray-500">
                <span>📋 <?= e($product['sku']) ?></span>
                <?php if (!empty($product['barcode'])): ?>
                    <span>🔖 <?= e($product['barcode']) ?></span>
                <?php endif; ?>
                <?php if (!empty($product['category_name'])): ?>
                    <span>📁 <?= e($product['category_name']) ?></span>
                <?php endif; ?>
                <?php if (!empty($product['supplier_name'])): ?>
                    <span>🏭
                        <a href="<?= BASE_URL ?>/suppliers/view/<?= $product['supplier_id'] ?>"
                            class="text-blue-600 hover:underline">
                            <?= e($product['supplier_name']) ?>
                        </a>
                    </span>
                <?php endif; ?>
                <span>📦 <?= e($product['unit']) ?></span>
                <span>💰 Cost (Last): <?= formatMoney($product['cost_price']) ?></span>
                <span>📈 Cost (WMA): <?= formatMoney($product['average_cost']) ?></span>
                <span>💵 Sell: <?= formatMoney($product['selling_price']) ?></span>
                <span>📊 Margin: <?= number_format($summary['margin_percent'], 1) ?>%</span>
            </div>

            <?php if (!empty($product['description'])): ?>
                <div class="mt-3 text-sm text-gray-500 bg-gray-50 rounded p-3 italic">
                    📝 <?= nl2br(e($product['description'])) ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="flex gap-2 flex-wrap">
            <a href="<?= BASE_URL ?>/stock/in?product_id=<?= $product['id'] ?>"
                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm transition">
                + Restock
            </a>
            <a href="<?= BASE_URL ?>/products/edit/<?= $product['id'] ?>"
                class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg text-sm transition">
                Edit
            </a>
        </div>
    </div>
</div>

<!-- Summary Stats -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white p-5 rounded-lg shadow text-center">
        <div class="text-gray-400 text-xs mb-1">Current Stock</div>
        <div class="text-3xl font-bold 
            <?= $product['current_stock'] <= 0 ? 'text-red-600' : ($product['current_stock'] <= $product['reorder_level'] ? 'text-yellow-600' : 'text-gray-800') ?>">
            <?= $product['current_stock'] ?> <?= e($product['unit']) ?>
        </div>
        <div class="text-xs text-gray-400 mt-1">
            Reorder at <?= $product['reorder_level'] ?>
        </div>
    </div>

    <div class="bg-white p-5 rounded-lg shadow text-center">
        <div class="text-gray-400 text-xs mb-1">Total Sales</div>
        <div class="text-3xl font-bold text-blue-600"><?= safeInt($summary['total_sales']) ?></div>
        <div class="text-xs text-gray-400 mt-1"><?= safeInt($summary['units_sold']) ?> units sold</div>
    </div>

    <div class="bg-white p-5 rounded-lg shadow text-center">
        <div class="text-gray-400 text-xs mb-1">Revenue Generated</div>
        <div class="text-2xl font-bold text-green-600"><?= formatMoney($summary['revenue_generated']) ?></div>
        <?php if (!empty($summary['last_sale'])): ?>
            <div class="text-xs text-gray-400 mt-1">Last: <?= formatDate($summary['last_sale'], 'd M Y') ?></div>
        <?php endif; ?>
    </div>

    <div class="bg-white p-5 rounded-lg shadow text-center">
        <div class="text-gray-400 text-xs mb-1">Stock Value</div>
        <div class="text-2xl font-bold text-purple-600"><?= formatMoney($summary['stock_value']) ?></div>
        <div class="text-xs text-gray-400 mt-1">@ average cost (WMA)</div>
    </div>
</div>

<!-- Two column layout -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- Recent Sales -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-5 py-4 border-b flex justify-between items-center">
            <h2 class="font-bold text-gray-700">Recent Sales</h2>
            <span class="text-xs text-gray-400">Last 20 transactions</span>
        </div>
        <?php if (empty($recentSales)): ?>
            <div class="p-10 text-center text-gray-400">
                <div class="text-3xl mb-2">🛒</div>
                <p class="text-sm">No sales yet</p>
                <a href="<?= BASE_URL ?>/pos" class="text-blue-600 text-xs hover:underline mt-1 inline-block">
                    Make a sale
                </a>
            </div>
        <?php else: ?>
            <div class="divide-y max-h-80 overflow-y-auto">
                <?php foreach ($recentSales as $sale): ?>
                    <div class="px-5 py-3 hover:bg-gray-50">
                        <div class="flex items-center justify-between mb-1">
                            <div>
                                <a href="<?= BASE_URL ?>/sales/view/<?= extractId($sale['sale_number']) ?>"
                                    class="text-sm font-medium text-blue-600 hover:underline">
                                    <?= e($sale['sale_number']) ?>
                                </a>
                                <span class="text-xs text-gray-400 ml-2">
                                    <?= e($sale['customer_name']) ?>
                                </span>
                            </div>
                            <span class="text-xs px-2 py-1 rounded-full
                                <?= $sale['payment_status'] === 'paid' ? 'bg-green-100 text-green-700' : ($sale['payment_status'] === 'partial' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') ?>">
                                <?= ucfirst($sale['payment_status']) ?>
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="text-xs text-gray-400">
                                <?= formatDate($sale['sale_date'], 'd M Y H:i') ?>
                                <?= !empty($sale['sold_by']) ? ' · ' . e($sale['sold_by']) : '' ?>
                            </div>
                            <div class="text-right">
                                <div class="font-bold text-gray-700">
                                    <?= $sale['quantity'] ?> × <?= formatMoney($sale['unit_price']) ?>
                                    <?php if ($sale['discount_percent'] > 0): ?>
                                        <span class="text-xs text-red-500">(-<?= $sale['discount_percent'] ?>%)</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-sm font-semibold text-green-600">
                                    <?= formatMoney($sale['line_total']) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Stock Movement History -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-5 py-4 border-b flex justify-between items-center">
            <h2 class="font-bold text-gray-700">Stock Movement History</h2>
            <span class="text-xs text-gray-400">Last 30 movements</span>
        </div>
        <?php if (empty($stockMovements)): ?>
            <div class="p-10 text-center text-gray-400">
                <div class="text-3xl mb-2">📊</div>
                <p class="text-sm">No stock movements yet</p>
                <a href="<?= BASE_URL ?>/stock/in" class="text-blue-600 text-xs hover:underline mt-1 inline-block">
                    Record stock in
                </a>
            </div>
        <?php else: ?>
            <div class="divide-y max-h-80 overflow-y-auto">
                <?php foreach ($stockMovements as $movement): ?>
                    <div class="px-5 py-3 hover:bg-gray-50">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm font-medium text-gray-700">
                                    <?php if ($movement['movement_type'] === 'in'): ?>
                                        <span class="text-green-600 font-bold">+<?= $movement['quantity'] ?></span>
                                    <?php elseif ($movement['movement_type'] === 'out'): ?>
                                        <span class="text-red-600 font-bold">-<?= abs($movement['quantity']) ?></span>
                                    <?php else: ?>
                                        <span class="text-blue-600 font-bold"><?= $movement['quantity'] ?></span>
                                    <?php endif; ?>
                                    <span class="text-gray-500 font-normal ml-1"><?= ucfirst($movement['reference_type']) ?></span>
                                </div>
                                <div class="text-xs text-gray-400">
                                    <?= formatDate($movement['created_at'], 'd M Y H:i') ?>
                                    <?= !empty($movement['recorded_by']) ? ' · ' . e($movement['recorded_by']) : '' ?>
                                </div>
                                <?php if (!empty($movement['notes'])): ?>
                                    <div class="text-xs text-gray-400 italic"><?= e($movement['notes']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="text-right">
                                <div class="font-bold text-gray-700">
                                    <?= $movement['previous_stock'] ?> → <?= $movement['new_stock'] ?>
                                </div>
                                <?php if (!empty($movement['supplier_name'])): ?>
                                    <div class="text-xs text-gray-400"><?= e($movement['supplier_name']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>