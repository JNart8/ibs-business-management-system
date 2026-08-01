<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="max-w-lg mx-auto">
    <div class="mb-5 text-sm">
        <a href="<?= BASE_URL ?>/suspense" class="text-blue-600 hover:underline">
            ← Back to Suspense Account
        </a>
    </div>

    <?= flashMessage() ?>

    <form method="POST" action="<?= BASE_URL ?>/suspense/edit/<?= $tx['id'] ?>" class="bg-white rounded-lg shadow p-6">
        <?= csrfField() ?>

        <h1 class="text-xl font-bold text-gray-800 mb-5">Edit Suspense Transaction</h1>

        <!-- Amount -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Amount <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <span class="absolute left-3 top-2.5 text-gray-500 font-medium"><?= CURRENCY_HOLDER ?></span>
                <input type="number" name="amount" min="0.01" step="0.01" required
                    value="<?= floatval($tx['amount']) ?>"
                    placeholder="0.00"
                    class="w-full pl-14 pr-4 py-2 border border-gray-300 rounded-lg text-lg font-bold focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
        </div>

        <!-- Received in Account -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Received in Account <span class="text-red-500">*</span>
            </label>
            <select name="account_id" required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">-- Select Financial Account --</option>
                <?php foreach ($accounts as $a): ?>
                    <option value="<?= $a['id'] ?>" <?= $a['id'] == $tx['resolved_account_id'] ? 'selected' : '' ?>>
                        <?= e($a['name']) ?> (<?= ucfirst(str_replace('_', ' ', $a['type'])) ?><?= $a['provider'] ? ' · ' . e($a['provider']) : '' ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-400 mt-1">The Bank or MoMo account where this payment was received.</p>
        </div>

        <!-- Reference ID -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Reference No / Tx ID (Optional)
            </label>
            <input type="text" name="reference_no" value="<?= e($tx['reference_no'] ?? '') ?>" placeholder="e.g. MTN-TX-849202, GCB-REF-820"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>

        <!-- Notes -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Description / Notes
            </label>
            <textarea name="notes" rows="3" placeholder="Describe where it came from..."
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"><?= e($tx['source_notes'] ?? '') ?></textarea>
        </div>

        <div class="flex justify-between border-t pt-4">
            <a href="<?= BASE_URL ?>/suspense"
                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg text-sm transition">
                Cancel
            </a>
            <button type="submit"
                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg text-sm shadow-sm transition">
                ✓ Save Changes
            </button>
        </div>
    </form>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
