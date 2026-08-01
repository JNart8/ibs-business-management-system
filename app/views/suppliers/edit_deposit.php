<?php include APP_PATH . '/views/layout/header.php'; ?>

<?php
$balance    = floatval($supplier['current_balance']);
$typeIcons  = ['cash' => '💵', 'mobile_money' => '📱', 'bank' => '🏦'];
$typeLabels = ['cash' => 'Cash', 'mobile_money' => 'Mobile Money', 'bank' => 'Bank'];
?>

<div class="max-w-lg mx-auto">
    <div class="mb-5 text-sm">
        <a href="<?= BASE_URL ?>/suppliers/view/<?= $supplier['id'] ?>" class="text-blue-600 hover:underline">
            ← Back to <?= e($supplier['company_name']) ?>
        </a>
    </div>

    <!-- Supplier Summary -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-5 mb-5">
        <div class="flex justify-between items-center">
            <div>
                <div class="font-bold text-gray-800 text-lg"><?= e($supplier['company_name']) ?></div>
                <div class="text-sm text-gray-500">
                    <?= e($supplier['supplier_code']) ?>
                    <?= !empty($supplier['contact_name']) ? ' · ' . e($supplier['contact_name']) : '' ?>
                </div>
            </div>
            <div class="text-right">
                <div class="text-xs text-gray-500 mb-1">Current Balance</div>
                <?php if ($balance < 0): ?>
                    <div class="text-xl font-bold text-red-600"><?= formatMoney(abs($balance)) ?></div>
                    <div class="text-xs text-red-400 mt-0.5">Outstanding (We Owe)</div>
                <?php elseif ($balance > 0): ?>
                    <div class="text-xl font-bold text-green-600"><?= formatMoney($balance) ?></div>
                    <div class="text-xs text-green-400 mt-0.5">Credit / Prepayment</div>
                <?php else: ?>
                    <div class="text-xl font-bold text-gray-600"><?= formatMoney(0) ?></div>
                    <div class="text-xs text-gray-400 mt-0.5">Clear Balance</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?= flashMessage() ?>

    <!-- Edit Payment Form -->
    <form method="POST" action="<?= BASE_URL ?>/suppliers/edit-deposit/<?= $tx['id'] ?>"
        class="bg-white rounded-lg shadow p-6"
        x-data="supplierEditForm()"
        x-init="init()">
        <?= csrfField() ?>

        <h1 class="text-xl font-bold text-gray-800 mb-1">Edit Supplier Payment</h1>
        <p class="text-sm text-gray-500 mb-5">
            Original: <?= formatMoney($tx['amount']) ?> on <?= date('d M Y', strtotime($tx['created_at'])) ?>
        </p>

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
                              focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
        </div>

        <!-- Pay From Account -->
        <div class="mb-5">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Pay From Account <span class="text-red-500">*</span>
            </label>
            <?php if (empty($accounts)): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-sm text-yellow-700">
                    ⚠️ No active financial accounts found.
                    <a href="<?= BASE_URL ?>/financial-accounts" class="underline ml-1">Add one first →</a>
                </div>
            <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($accounts as $acc): ?>
                        <label class="flex items-center justify-between p-3 border rounded-lg cursor-pointer
                                      hover:bg-gray-50 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 transition">
                            <div class="flex items-center gap-3">
                                <input type="radio" name="account_id" value="<?= $acc['id'] ?>"
                                    x-model="selectedAccountId"
                                    @change="calcNewBalance()"
                                    <?= $acc['id'] == $currentAccountId ? 'checked' : '' ?>
                                    class="text-blue-600">
                                <div>
                                    <div class="text-sm font-semibold text-gray-800">
                                        <?= $typeIcons[$acc['type']] ?? '💳' ?> <?= e($acc['name']) ?>
                                    </div>
                                    <div class="text-xs text-gray-400"><?= $typeLabels[$acc['type']] ?? ucfirst($acc['type']) ?></div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-bold <?= $acc['balance'] <= 0 ? 'text-red-500' : 'text-green-600' ?>">
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
        <div class="mb-5">
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes (Optional)</label>
            <input type="text" name="notes"
                value="<?= e($tx['notes'] ?? '') ?>"
                placeholder="e.g. Partial payment for invoice INV-001"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                          focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>

        <!-- Payment Date -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                📅 Payment Date <span class="text-xs text-gray-400 font-normal">(back-date if needed)</span>
            </label>
            <input type="date"
                name="payment_date"
                id="payment_date"
                value="<?= date('Y-m-d', strtotime($tx['created_at'])) ?>"
                max="<?= date('Y-m-d') ?>"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                          focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <p class="text-xs text-gray-500 mt-1">
                Original: <?= date('d M Y', strtotime($tx['created_at'])) ?> — Change to back-date this payment.
            </p>
        </div>

        <!-- Balance Preview -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6" x-show="amount > 0" x-cloak>
            <div class="flex justify-between items-center mb-2">
                <span class="text-sm text-gray-600">New Supplier Balance After Edit:</span>
                <span class="font-bold text-lg"
                    :class="newBalanceNum >= 0 ? 'text-green-600' : 'text-orange-500'"
                    x-text="newBalanceDisplay"></span>
            </div>
            <template x-if="newBalanceNum >= 0 && amount > 0">
                <div class="text-xs text-green-600 mt-1">✓ Balance cleared / in credit</div>
            </template>
            <template x-if="newBalanceNum < 0 && amount > 0">
                <div class="text-xs text-orange-500 mt-1">Remaining outstanding: <span x-text="remainingDisplay"></span></div>
            </template>
        </div>

        <div class="flex justify-between">
            <a href="<?= BASE_URL ?>/suppliers/view/<?= $supplier['id'] ?>"
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

    <!-- Danger Zone: Delete Payment -->
    <div class="mt-6 border border-red-200 rounded-lg p-5 bg-red-50">
        <h3 class="text-sm font-semibold text-red-700 mb-1">⚠️ Danger Zone</h3>
        <p class="text-xs text-red-500 mb-4">
            Deleting this payment will reverse the supplier balance by
            <strong><?= formatMoney($tx['amount']) ?></strong>
            and restore the account balance. This action cannot be undone.
        </p>
        <form method="POST" action="<?= BASE_URL ?>/suppliers/delete-deposit/<?= $tx['id'] ?>"
            onsubmit="return confirm('Delete payment of <?= formatMoney($tx['amount']) ?>?\n\nThis will reverse the supplier balance and restore the account balance. This cannot be undone.')">
            <?= csrfField() ?>
            <button type="submit"
                class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm rounded-lg shadow transition">
                🗑️ Delete This Payment
            </button>
        </form>
    </div>
</div>

<script>
    function supplierEditForm() {
        const currentSupplierBalance = <?= floatval($supplier['current_balance']) ?>;
        const oldPaymentAmount       = <?= floatval($tx['amount']) ?>;
        const currencyHolder         = '<?= CURRENCY_HOLDER ?>';

        function fmt(n) {
            return currencyHolder + ' ' + Math.abs(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }

        return {
            amount: <?= floatval($tx['amount']) ?>,
            selectedAccountId: '<?= $currentAccountId ?? (isset($accounts[0]) ? $accounts[0]['id'] : '') ?>',
            // Base = current balance minus the old payment (so we can preview with new amount)
            baseBalance: currentSupplierBalance - oldPaymentAmount,
            newBalanceNum: currentSupplierBalance,
            newBalanceDisplay: (currentSupplierBalance < 0 ? '-' : '') + fmt(currentSupplierBalance),
            remainingDisplay: '',
            calcNewBalance() {
                const amt = parseFloat(this.amount) || 0;
                const nb  = this.baseBalance + amt;
                this.newBalanceNum     = nb;
                this.newBalanceDisplay = (nb < 0 ? '-' : '') + fmt(nb);
                this.remainingDisplay  = nb < 0 ? fmt(nb) : fmt(0);
            },
            init() {
                this.calcNewBalance();
            }
        };
    }
</script>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
