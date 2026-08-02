<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="max-w-lg mx-auto">
    <div class="mb-5 text-sm">
        <a href="<?= BASE_URL ?>/stock" class="text-blue-600 hover:underline">← Back to Stock</a>
    </div>

    <div class="bg-purple-50 border border-purple-200 rounded-lg p-5 mb-5">
        <h1 class="text-2xl font-bold text-gray-800"><?= e($pageTitle) ?></h1>
        <p class="text-gray-500 text-sm mt-1">
            Manually set the exact stock quantity for <strong><?= e($product['name']) ?></strong>
        </p>
    </div>

    <?= flashMessage() ?>

    <form method="POST" action="<?= BASE_URL ?>/stock/adjust/<?= $product['id'] ?>"
        class="bg-white rounded-lg shadow p-6"
        x-data="{ newStock: <?= $product['current_stock'] ?>, current: <?= $product['current_stock'] ?> }">
        <?= csrfField() ?>

        <!-- Product Info -->
        <div class="bg-gray-50 rounded-lg p-4 mb-5">
            <div class="grid grid-cols-3 gap-4 text-center">
                <div>
                    <div class="text-xs text-gray-400">SKU</div>
                    <div class="font-semibold text-gray-700"><?= e($product['sku']) ?></div>
                </div>
                <div>
                    <div class="text-xs text-gray-400">Current Stock</div>
                    <div class="font-bold text-2xl text-blue-600"><?= $product['current_stock'] ?></div>
                </div>
                <div>
                    <div class="text-xs text-gray-400">Reorder At</div>
                    <div class="font-semibold text-gray-700"><?= $product['reorder_level'] ?></div>
                </div>
            </div>
        </div>

        <!-- New Stock -->
        <div class="mb-5">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                New Stock Quantity <span class="text-red-500">*</span>
            </label>
            <input type="number" name="new_stock"
                min="0" step="0.01" required
                x-model="newStock"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg text-2xl font-bold text-center
                          focus:ring-2 focus:ring-purple-500 focus:border-transparent">

            <!-- Difference indicator -->
            <div class="mt-2 text-center text-sm" x-show="newStock !== current">
                <span x-show="newStock > current" class="text-green-600 font-semibold">
                    ↑ Increase by <span x-text="newStock - current"></span> <?= e($product['unit']) ?>
                </span>
                <span x-show="newStock < current" class="text-red-600 font-semibold">
                    ↓ Decrease by <span x-text="current - newStock"></span> <?= e($product['unit']) ?>
                </span>
            </div>
            <div class="mt-2 text-center text-sm text-gray-400" x-show="parseFloat(newStock) === current">
                No change
            </div>
        </div>

        <!-- Quick adjust buttons -->
        <div class="mb-5">
            <p class="text-xs text-gray-500 mb-2">Quick Adjust:</p>
            <div class="flex flex-wrap gap-2 justify-center">
                <?php foreach ([-10, -5, -1, 1, 5, 10, 50, 100] as $adj): ?>
                    <button type="button"
                        @click="newStock = Math.max(0, Math.round((parseFloat(newStock || 0) + <?= $adj ?>) * 100) / 100)"
                        class="px-3 py-1.5 text-sm border rounded-lg
                                   <?= $adj > 0 ? 'hover:bg-green-50 hover:border-green-400 text-green-700'
                                        : 'hover:bg-red-50 hover:border-red-400 text-red-600' ?>
                                   transition">
                        <?= $adj > 0 ? "+$adj" : "$adj" ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Reason (required for audit) -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Reason for Adjustment <span class="text-red-500">*</span>
            </label>
            <input type="text" name="notes" required
                placeholder="e.g. Physical stock count, Damaged items removed..."
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                          focus:ring-2 focus:ring-purple-500 focus:border-transparent">
        </div>

        <div class="flex justify-between">
            <a href="<?= BASE_URL ?>/stock"
                class="px-5 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition">
                Cancel
            </a>
            <button type="submit"
                class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-lg shadow transition">
                ✓ Apply Adjustment
            </button>
        </div>
    </form>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>