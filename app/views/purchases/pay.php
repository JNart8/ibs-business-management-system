<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="max-w-2xl mx-auto px-4 py-6">

    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
            <a href="<?= BASE_URL ?>/purchases" class="hover:text-blue-600">Purchases</a>
            <span>›</span>
            <a href="<?= BASE_URL ?>/purchases/view/<?= $purchase['id'] ?>" class="hover:text-blue-600">
                <?= e($purchase['purchase_number']) ?>
            </a>
            <span>›</span>
            <span class="text-gray-800">Record Payment</span>
        </div>
        <h1 class="text-2xl font-bold text-gray-800">💳 Record Payment to Supplier</h1>
    </div>

    <!-- Purchase Summary Card -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="grid grid-cols-2 gap-4 mb-4 pb-4 border-b">
            <div>
                <div class="text-xs text-gray-500 mb-1">Purchase Number</div>
                <div class="font-mono font-bold text-gray-800"><?= e($purchase['purchase_number']) ?></div>
            </div>
            <div>
                <div class="text-xs text-gray-500 mb-1">Supplier</div>
                <div class="font-medium text-gray-800"><?= e($purchase['supplier_name']) ?></div>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div>
                <div class="text-xs text-gray-500 mb-1">Total Amount</div>
                <div class="text-lg font-bold text-gray-800">
                    <?= formatMoney($purchase['total_amount']) ?>
                </div>
            </div>
            <div>
                <div class="text-xs text-gray-500 mb-1">Already Paid</div>
                <div class="text-lg font-bold text-green-600">
                    <?= formatMoney($purchase['amount_paid']) ?>
                </div>
            </div>
            <div>
                <div class="text-xs text-gray-500 mb-1">Balance Due</div>
                <div class="text-2xl font-bold text-red-600">
                    <?= formatMoney($purchase['amount_due']) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Form -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Payment Details</h2>

        <form method="POST" action="<?= BASE_URL ?>/purchases/pay/<?= $purchase['id'] ?>" x-data="paymentForm()" x-init="init()">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <!-- Amount -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Payment Amount <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <span class="absolute left-3 top-3 text-gray-500 font-semibold">
                        <?= CURRENCY_HOLDER ?>
                    </span>
                    <input type="number"
                        name="amount"
                        value="<?= formatNumber($purchase['amount_due']) ?>"
                        min="0.01"
                        max="<?= formatNumber($purchase['amount_due']) ?>"
                        step="0.01"
                        required
                        class="w-full pl-14 pr-4 py-3 border border-gray-300 rounded-lg text-lg font-bold
                                focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div class="text-xs text-gray-500 mt-1">
                    Maximum: <?= formatMoney($purchase['amount_due']) ?>
                </div>
            </div>

            <!-- Payment Method -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Payment Method <span class="text-red-500">*</span>
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="relative">
                        <input type="radio" name="payment_method" value="cash" x-model="paymentMethod" @change="selectPaymentMethod('cash')"
                            class="peer sr-only">
                        <div class="px-4 py-3 border-2 border-gray-300 rounded-lg cursor-pointer
                                    hover:border-blue-400 peer-checked:border-blue-600 peer-checked:bg-blue-50
                                    transition text-center">
                            <div class="text-2xl mb-1">💵</div>
                            <div class="text-sm font-semibold text-gray-700">Cash</div>
                        </div>
                    </label>

                    <label class="relative">
                        <input type="radio" name="payment_method" value="mobile" x-model="paymentMethod" @change="selectPaymentMethod('mobile')"
                            class="peer sr-only">
                        <div class="px-4 py-3 border-2 border-gray-300 rounded-lg cursor-pointer
                                    hover:border-blue-400 peer-checked:border-blue-600 peer-checked:bg-blue-50
                                    transition text-center">
                            <div class="text-2xl mb-1">📱</div>
                            <div class="text-sm font-semibold text-gray-700">Mobile Money</div>
                        </div>
                    </label>

                    <label class="relative">
                        <input type="radio" name="payment_method" value="bank" x-model="paymentMethod" @change="selectPaymentMethod('bank')"
                            class="peer sr-only">
                        <div class="px-4 py-3 border-2 border-gray-300 rounded-lg cursor-pointer
                                    hover:border-blue-400 peer-checked:border-blue-600 peer-checked:bg-blue-50
                                    transition text-center">
                            <div class="text-2xl mb-1">🏦</div>
                            <div class="text-sm font-semibold text-gray-700">Bank Transfer</div>
                        </div>
                    </label>

                    <label class="relative">
                        <input type="radio" name="payment_method" value="cheque" x-model="paymentMethod" @change="selectPaymentMethod('cheque')"
                            class="peer sr-only">
                        <div class="px-4 py-3 border-2 border-gray-300 rounded-lg cursor-pointer
                                    hover:border-blue-400 peer-checked:border-blue-600 peer-checked:bg-blue-50
                                    transition text-center">
                            <div class="text-2xl mb-1">📝</div>
                            <div class="text-sm font-semibold text-gray-700">Cheque</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Account Selection Dropdown -->
            <div class="mb-6" x-show="['cash', 'mobile', 'bank', 'cheque'].includes(paymentMethod)">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Payment Source Account <span class="text-red-500">*</span>
                </label>
                <select name="account_id" x-model.number="selectedAccountId"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm bg-white"
                    required>
                    <option value="0" disabled>-- Select Financial Account --</option>
                    <template x-for="acc in accountsForMethod(paymentMethod)" :key="acc.id">
                        <option :value="acc.id" x-text="acc.name + ' (' + formatMoney(acc.balance) + ')'"></option>
                    </template>
                </select>
            </div>

            <!-- Payment Date -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    📅 Payment Date <span class="text-xs text-gray-400 font-normal">(defaults to today)</span>
                </label>
                <input type="date"
                    name="payment_date"
                    id="payment_date"
                    value="<?= date('Y-m-d') ?>"
                    max="<?= date('Y-m-d') ?>"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <p class="text-xs text-gray-500 mt-1">Back-date this payment if it was received on a different day.</p>
            </div>

            <!-- Buttons -->
            <div class="flex gap-3">
                <button type="submit"
                    class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg
                            transition shadow-lg hover:shadow-xl">
                    ✓ Record Payment
                </button>
                <a href="<?= BASE_URL ?>/purchases/view/<?= $purchase['id'] ?>"
                    class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold
                            rounded-lg transition text-center">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- Payment Instructions -->
    <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
        <h3 class="font-semibold text-blue-800 mb-2">💡 Payment Information</h3>
        <ul class="text-sm text-blue-700 space-y-1">
            <li>• Payment cannot exceed the balance due</li>
            <li>• Select the payment method used for this transaction</li>
            <li>• This will update the supplier's balance</li>
            <li>• You can make multiple partial payments until fully paid</li>
        </ul>
    </div>

</div>

<script>
    function paymentForm() {
        return {
            paymentMethod: 'cash',
            selectedAccountId: 0,
            allAccounts: <?= json_encode($accounts) ?>,

            init() {
                this.autoSelectAccount(this.paymentMethod);
            },

            accountsForMethod(method) {
                const typeMap = { cash: 'cash', mobile: 'mobile_money', bank: 'bank', cheque: 'bank' };
                const type = typeMap[method];
                if (!type) return [];
                return this.allAccounts.filter(a => a.type === type);
            },

            autoSelectAccount(method) {
                const list = this.accountsForMethod(method);
                this.selectedAccountId = list.length > 0 ? list[0].id : 0;
            },

            selectPaymentMethod(method) {
                this.paymentMethod = method;
                this.autoSelectAccount(method);
            },

            formatMoney(amount) {
                amount = parseFloat(amount) || 0;
                return '<?= CURRENCY_HOLDER ?> ' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            }
        };
    }
</script>

<?php include APP_PATH . '/views/layout/footer.php'; ?>