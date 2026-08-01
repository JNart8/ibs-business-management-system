<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="max-w-lg mx-auto">
    <div class="mb-5 text-sm">
        <a href="<?= BASE_URL ?>/customers/view/<?= $customer['id'] ?>" class="text-blue-600 hover:underline">
            ← Back to <?= e($customer['full_name']) ?>
        </a>
    </div>

    <!-- Customer Summary -->
    <div class="bg-purple-50 border border-purple-200 rounded-lg p-5 mb-5">
        <div class="flex justify-between items-center">
            <div>
                <div class="font-bold text-gray-800 text-lg"><?= e($customer['full_name']) ?></div>
                <div class="text-sm text-gray-500"><?= e($customer['customer_code']) ?></div>
            </div>
            <div class="text-right">
                <div class="text-xs text-gray-500">Current Credit Limit</div>
                <div class="text-xl font-bold text-purple-700">
                    <?= formatMoney($customer['credit_limit']) ?>
                </div>
            </div>
        </div>
    </div>

    <?= flashMessage() ?>

    <form method="POST" action="<?= BASE_URL ?>/customers/adjust-credit/<?= $customer['id'] ?>"
        class="bg-white rounded-lg shadow p-6"
        x-data="{ limit: <?= floatval($customer['credit_limit']) ?> }">
        <?= csrfField() ?>

        <h1 class="text-xl font-bold text-gray-800 mb-5">Adjust Credit Limit</h1>

        <!-- Credit Limit Input -->
        <div class="mb-5">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                New Credit Limit <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <span class="absolute left-3 top-2.5 text-gray-500 font-medium"><?= CURRENCY_HOLDER ?></span>
                <input type="number" name="credit_limit"
                    x-model="limit"
                    min="0" step="0.01" required
                    class="w-full pl-14 pr-4 py-3 border border-gray-300 rounded-lg text-xl font-bold
                              focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            </div>
            <p class="text-xs text-gray-400 mt-1">Set to 0 to remove credit (cash-only)</p>
        </div>

        <!-- Quick Presets -->
        <div class="mb-5">
            <p class="text-xs text-gray-500 mb-2">Quick Presets:</p>
            <div class="flex flex-wrap gap-2">
                <?php foreach ([0, 500, 1000, 2000, 5000, 10000] as $preset): ?>
                    <button type="button"
                        @click="limit = <?= $preset ?>"
                        class="px-3 py-1 text-sm border rounded-full hover:bg-purple-50 hover:border-purple-400 transition">
                        <?= formatMoney($preset) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Notes -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Reason (Optional)</label>
            <input type="text" name="notes"
                placeholder="e.g. Increased due to good payment history"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                          focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>

        <!-- Warning if setting to 0 -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-6" x-show="limit == 0" x-cloak>
            <p class="text-sm text-yellow-700">
                ⚠️ Setting to GHS 0.00 means this customer cannot buy on credit.
            </p>
        </div>

        <div class="flex justify-between">
            <a href="<?= BASE_URL ?>/customers/view/<?= $customer['id'] ?>"
                class="px-5 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition">
                Cancel
            </a>
            <button type="submit"
                class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-lg shadow transition">
                Update Credit Limit
            </button>
        </div>
    </form>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>