<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="max-w-lg mx-auto">
    <div class="mb-5 text-sm">
        <a href="<?= BASE_URL ?>/customers/view/<?= $customer['id'] ?>" class="text-blue-600 hover:underline">
            ← Back to <?= e($customer['full_name']) ?>
        </a>
    </div>

    <!-- Customer Summary -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-5 mb-5">
        <div class="flex justify-between items-center">
            <div>
                <div class="font-bold text-gray-800 text-lg"><?= e($customer['full_name']) ?></div>
                <div class="text-sm text-gray-500"><?= e($customer['customer_code']) ?> · <?= e($customer['phone']) ?></div>
            </div>
            <div class="text-right">
                <div class="text-xs text-gray-500">Current Balance</div>
                <?php $bal = floatval($customer['current_balance']); ?>
                <div class="text-xl font-bold <?= $bal < 0 ? 'text-red-600' : 'text-green-600' ?>">
                    <?= $bal < 0 ? '-' : '' ?><?= formatMoney(abs($bal)) ?>
                </div>
            </div>
        </div>
    </div>

    <?= flashMessage() ?>

    <!-- Edit Deposit Form -->
    <form method="POST" action="<?= BASE_URL ?>/customers/edit-deposit/<?= $tx['id'] ?>"
        class="bg-white rounded-lg shadow p-6"
        x-data="depositForm()">
        <?= csrfField() ?>

        <h1 class="text-xl font-bold text-gray-800 mb-5">Edit Customer Deposit</h1>

        <!-- Amount -->
        <div class="mb-5">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Amount <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <span class="absolute left-3 top-2.5 text-gray-500 font-medium"><?= CURRENCY_HOLDER ?></span>
                <input type="number" name="amount"
                    min="0.01" step="0.01" required
                    x-model="amount"
                    @input="calcNewBalance()"
                    placeholder="0.00"
                    class="w-full pl-14 pr-4 py-3 border border-gray-300 rounded-lg text-xl font-bold
                              focus:ring-2 focus:ring-green-500 focus:border-transparent">
            </div>
        </div>

        <!-- Deposit to Account -->
        <div class="mb-5">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Deposit to Account <span class="text-red-500">*</span>
            </label>
            <?php if (empty($accounts)): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-sm text-yellow-700">
                    ⚠️ No active financial accounts found.
                    <a href="<?= BASE_URL ?>/financial-accounts" class="underline ml-1">Add one first →</a>
                </div>
            <?php else: ?>
                <div class="space-y-2">
                    <?php
                    $typeIcons = ['cash' => '💵', 'mobile_money' => '📱', 'bank' => '🏦'];
                    $typeLabels = ['cash' => 'Cash', 'mobile_money' => 'Mobile Money', 'bank' => 'Bank'];
                    ?>
                    <?php foreach ($accounts as $acc): ?>
                        <label class="flex items-center justify-between p-3 border rounded-lg cursor-pointer
                                      hover:bg-gray-50 has-[:checked]:border-green-500 has-[:checked]:bg-green-50 transition">
                            <div class="flex items-center gap-3">
                                <input type="radio" name="account_id" value="<?= $acc['id'] ?>"
                                    x-model="selectedAccountId"
                                    <?= $acc['id'] == $currentAccountId ? 'checked' : '' ?>
                                    class="text-green-600">
                                <div>
                                    <div class="text-sm font-semibold text-gray-800">
                                        <?= $typeIcons[$acc['type']] ?? '💳' ?> <?= e($acc['name']) ?>
                                    </div>
                                    <div class="text-xs text-gray-400"><?= $typeLabels[$acc['type']] ?? ucfirst($acc['type']) ?></div>
                                </div>
                             </div>
                             <div class="text-right">
                                 <div class="text-sm font-bold text-gray-800">
                                     <?= formatMoney($acc['balance']) ?>
                                 </div>
                                 <div class="text-xs text-gray-400">available</div>
                             </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Notes -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes (Optional)</label>
            <input type="text" name="notes"
                value="<?= e($tx['notes'] ?? '') ?>"
                placeholder="e.g. Advance payment for next order"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                          focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>

        <!-- New Balance Preview -->
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6" x-show="amount > 0" x-cloak>
            <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600">New Balance After Edit:</span>
                <span class="font-bold text-green-600 text-lg" x-text="newBalance"></span>
            </div>
        </div>

        <!-- Deposit Date -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                📅 Deposit Date <span class="text-xs text-gray-400 font-normal">(back-date if needed)</span>
            </label>
            <input type="date"
                name="deposit_date"
                id="deposit_date"
                value="<?= date('Y-m-d', strtotime($tx['created_at'])) ?>"
                max="<?= date('Y-m-d') ?>"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                          focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <p class="text-xs text-gray-500 mt-1">
                Original: <?= date('d M Y', strtotime($tx['created_at'])) ?> — Change to back-date this deposit.
            </p>
        </div>

        <div class="flex justify-between">
            <a href="<?= BASE_URL ?>/customers/view/<?= $customer['id'] ?>"
                class="px-5 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition">
                Cancel
            </a>
            <button type="submit" <?= empty($accounts) ? 'disabled' : '' ?>
                class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg shadow transition
                       disabled:opacity-50 disabled:cursor-not-allowed">
                ✓ Save Changes
            </button>
        </div>
    </form>
</div>

<script>
    function depositForm() {
        return {
            amount: <?= floatval($tx['amount']) ?>,
            selectedAccountId: '<?= $currentAccountId ?? (isset($accounts[0]) ? $accounts[0]['id'] : "") ?>',
            currentBalance: <?= floatval($customer['current_balance']) - floatval($tx['amount']) ?>,
            newBalance: '<?= formatMoney($customer['current_balance']) ?>',
            calcNewBalance() {
                const amt = parseFloat(this.amount) || 0;
                const nb = this.currentBalance + amt;
                this.newBalance = '<?= CURRENCY_HOLDER ?> ' + nb.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            },
            init() {
                this.calcNewBalance();
            }
        }
    }
</script>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
