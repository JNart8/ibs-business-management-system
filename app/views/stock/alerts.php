<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">⚠️ <?= e($pageTitle) ?></h1>
        <p class="text-gray-500 text-sm mt-1">
            <?= count($alerts) ?> product(s) need attention
        </p>
    </div>
    <a href="<?= BASE_URL ?>/stock" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition">
        ← Back to Stock
    </a>
</div>

<!-- Out of Stock Section -->
<?php if (!empty($outOfStock)): ?>
    <div class="mb-6">
        <h2 class="text-lg font-bold text-red-600 mb-3 flex items-center gap-2">
            <span class="bg-red-100 text-red-600 px-3 py-1 rounded-full text-sm"><?= count($outOfStock) ?></span>
            Out of Stock
        </h2>
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <?php foreach ($outOfStock as $p): ?>
                <div class="flex items-center justify-between px-5 py-4 border-b last:border-0 hover:bg-red-50 transition">
                    <div class="flex items-center gap-4">
                        <div class="w-2 h-2 rounded-full bg-red-500"></div>
                        <div>
                            <div class="font-semibold text-gray-800"><?= e($p['name']) ?></div>
                            <div class="text-xs text-gray-400"><?= e($p['sku']) ?> · <?= e($p['category_name'] ?? 'Uncategorized') ?></div>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="text-right">
                            <div class="text-red-600 font-bold">0 <?= e($p['unit']) ?></div>
                            <div class="text-xs text-gray-400">Reorder at <?= $p['reorder_level'] ?></div>
                        </div>
                        <a href="<?= BASE_URL ?>/stock/in?product=<?= $p['id'] ?>"
                            class="bg-green-600 hover:bg-green-700 text-white text-sm px-4 py-2 rounded-lg transition whitespace-nowrap">
                            + Stock In
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Low Stock Section -->
<?php if (!empty($lowStock)): ?>
    <div class="mb-6">
        <h2 class="text-lg font-bold text-yellow-600 mb-3 flex items-center gap-2">
            <span class="bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-sm"><?= count($lowStock) ?></span>
            Low Stock
        </h2>
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <?php foreach ($lowStock as $p): ?>
                <?php $pct = $p['reorder_level'] > 0 ? min(100, ($p['current_stock'] / $p['reorder_level']) * 100) : 100; ?>
                <div class="px-5 py-4 border-b last:border-0 hover:bg-yellow-50 transition">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-4">
                            <div class="w-2 h-2 rounded-full bg-yellow-500"></div>
                            <div>
                                <div class="font-semibold text-gray-800"><?= e($p['name']) ?></div>
                                <div class="text-xs text-gray-400"><?= e($p['sku']) ?> · <?= e($p['category_name'] ?? 'Uncategorized') ?></div>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="text-right">
                                <div class="text-yellow-600 font-bold">
                                    <?= $p['current_stock'] ?> / <?= $p['reorder_level'] ?> <?= e($p['unit']) ?>
                                </div>
                                <div class="text-xs text-red-400">Short by <?= $p['shortage'] ?> <?= e($p['unit']) ?></div>
                            </div>
                            <a href="<?= BASE_URL ?>/stock/in?product=<?= $p['id'] ?>"
                                class="bg-green-600 hover:bg-green-700 text-white text-sm px-4 py-2 rounded-lg transition whitespace-nowrap">
                                + Stock In
                            </a>
                        </div>
                    </div>
                    <!-- Progress bar -->
                    <div class="w-full bg-gray-200 rounded-full h-1.5 ml-6">
                        <div class="h-1.5 rounded-full bg-yellow-500" style="width: <?= $pct ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php if (empty($alerts)): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-12 text-center">
        <div class="text-5xl mb-4">✅</div>
        <h2 class="text-xl font-bold text-green-700">All products are well stocked!</h2>
        <p class="text-green-500 mt-2">No low stock or out of stock alerts at this time.</p>
    </div>
<?php endif; ?>

<?php include APP_PATH . '/views/layout/footer.php'; ?>