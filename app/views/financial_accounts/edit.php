<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="max-w-lg mx-auto">
    <!-- Page Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">✏️ Edit Account</h1>
            <p class="text-gray-500 text-sm mt-1">Update this account's details</p>
        </div>
    </div>

    <div class="mb-4">
        <a href="<?= BASE_URL ?>/financial-accounts"
            class="bg-gray-100 hover:bg-gray-200 text-gray-800 px-4 py-2.5 rounded-lg text-sm font-medium transition inline-block">
            ← Back to Accounts
        </a>
    </div>

    <!-- Flash Message -->
    <?= flashMessage() ?>

    <div class="bg-white rounded-xl shadow-sm border">
        <form action="<?= BASE_URL ?>/financial-accounts/update/<?= $account['id'] ?>" method="POST" class="p-6 space-y-4">
            <?= csrfField() ?>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Account Name *</label>
                <input type="text" name="name" required value="<?= e($account['name']) ?>"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Account Type</label>
                <input type="text" disabled value="<?= e(ucfirst(str_replace('_', ' ', $account['type']))) ?>"
                    class="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-500">
                <p class="text-xs text-gray-400 mt-1">
                    Account type can't be changed here — it affects how past transactions are
                    categorized. Contact support if an account was set up with the wrong type.
                </p>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Provider (Bank / MoMo Brand)</label>
                <input type="text" name="provider" value="<?= e($account['provider']) ?>"
                    placeholder="e.g., Fidelity Bank, MTN, Telecel"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Account / Phone Number</label>
                <input type="text" name="account_number" value="<?= e($account['account_number']) ?>"
                    placeholder="e.g., 104033284711, 0244111222"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Current Balance</label>
                <input type="text" disabled value="<?= formatMoney($account['balance']) ?>"
                    class="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-500">
                <p class="text-xs text-gray-400 mt-1">
                    Balance can only change through a sale, deposit, or transfer — not a direct edit —
                    so it always matches the account's transaction ledger.
                </p>
            </div>

            <?php if (!$account['is_default']): ?>
            <div class="flex items-center gap-2 pt-2">
                <input type="checkbox" name="is_active" id="is_active" value="1" <?= $account['is_active'] ? 'checked' : '' ?>
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <label for="is_active" class="text-sm text-gray-700">Account is active</label>
            </div>
            <p class="text-xs text-gray-400">
                Uncheck to retire this account without deleting it — it will stop appearing as a
                payment option on new sales/purchases, but its history stays intact.
            </p>
            <?php else: ?>
            <input type="hidden" name="is_active" value="1">
            <p class="text-xs text-gray-400">This is a default account and can't be deactivated.</p>
            <?php endif; ?>

            <div class="flex justify-end gap-2 pt-4 border-t">
                <a href="<?= BASE_URL ?>/financial-accounts"
                    class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-2 px-4 rounded-lg text-sm transition">
                    Cancel
                </a>
                <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg text-sm transition">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
