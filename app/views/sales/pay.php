<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="max-w-2xl mx-auto">

    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
            <a href="<?= BASE_URL ?>/sales" class="hover:text-blue-600">Sales</a>
            <span>›</span>
            <a href="<?= BASE_URL ?>/sales/view/<?= $sale['id'] ?>" class="hover:text-blue-600">
                <?= e($sale['sale_number']) ?>
            </a>
            <span>›</span>
            <span class="text-gray-800">Record Payment</span>
        </div>
        <h1 class="text-2xl font-bold text-gray-800">💳 Record Payment</h1>
    </div>

    <!-- Sale Summary Card -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="grid grid-cols-2 gap-4 mb-4 pb-4 border-b">
            <div>
                <div class="text-xs text-gray-500 mb-1">Receipt Number</div>
                <div class="font-mono font-bold text-gray-800"><?= e($sale['sale_number']) ?></div>
            </div>
            <div>
                <div class="text-xs text-gray-500 mb-1">Customer</div>
                <div class="font-medium text-gray-800"><?= e($sale['customer_name']) ?></div>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div>
                <div class="text-xs text-gray-500 mb-1">Total Amount</div>
                <div class="text-lg font-bold text-gray-800">
                    <?= formatMoney($sale['total_amount']) ?>
                </div>
            </div>
            <div>
                <div class="text-xs text-gray-500 mb-1">Already Paid</div>
                <div class="text-lg font-bold text-green-600">
                    <?= formatMoney($sale['amount_paid']) ?>
                </div>
            </div>
            <div>
                <div class="text-xs text-gray-500 mb-1">Amount Due</div>
                <div class="text-2xl font-bold text-red-600">
                    <?= formatMoney($sale['amount_due']) ?>
                </div>
            </div>
        </div>

        <!-- Customer Deposit Balance (if not walk-in) -->
        <?php if ($sale['is_default'] != 1): ?>
            <div class="mt-4 pt-4 border-t">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-600">Customer Deposit Balance:</div>
                    <div class="text-lg font-bold <?= $sale['current_balance'] > 0 ? 'text-green-600' : 'text-gray-400' ?>">
                        <?= formatMoney($sale['current_balance']) ?>
                    </div>
                </div>
                <?php if ($sale['current_balance'] > 0): ?>
                    <div class="text-xs text-green-600 mt-1">
                        ✓ Customer can pay from deposit
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Payment Form -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Payment Details</h2>

        <form method="POST" action="<?= BASE_URL ?>/sales/pay/<?= $sale['id'] ?>">
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
                        value="<?= formatNumber($sale['amount_due']) ?>"
                        min="0.01"
                        max="<?= formatNumber($sale['amount_due']) ?>"
                        step="0.01"
                        required
                        class="w-full pl-14 pr-4 py-3 border border-gray-300 rounded-lg text-lg font-bold
                                focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div class="text-xs text-gray-500 mt-1">
                    Maximum: <?= formatMoney($sale['amount_due']) ?>
                </div>
            </div>

            <!-- Payment Method -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Payment Method <span class="text-red-500">*</span>
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="relative">
                        <input type="radio" name="payment_method" value="cash" checked
                            class="peer sr-only">
                        <div class="px-4 py-3 border-2 border-gray-300 rounded-lg cursor-pointer
                                    hover:border-blue-400 peer-checked:border-blue-600 peer-checked:bg-blue-50
                                    transition text-center">
                            <div class="text-2xl mb-1">💵</div>
                            <div class="text-sm font-semibold text-gray-700">Cash</div>
                        </div>
                    </label>

                    <label class="relative">
                        <input type="radio" name="payment_method" value="mobile"
                            class="peer sr-only">
                        <div class="px-4 py-3 border-2 border-gray-300 rounded-lg cursor-pointer
                                    hover:border-blue-400 peer-checked:border-blue-600 peer-checked:bg-blue-50
                                    transition text-center">
                            <div class="text-2xl mb-1">📱</div>
                            <div class="text-sm font-semibold text-gray-700">Mobile Money</div>
                        </div>
                    </label>

                    <label class="relative">
                        <input type="radio" name="payment_method" value="bank"
                            class="peer sr-only">
                        <div class="px-4 py-3 border-2 border-gray-300 rounded-lg cursor-pointer
                                    hover:border-blue-400 peer-checked:border-blue-600 peer-checked:bg-blue-50
                                    transition text-center">
                            <div class="text-2xl mb-1">🏦</div>
                            <div class="text-sm font-semibold text-gray-700">Bank Transfer</div>
                        </div>
                    </label>

                    <!-- Deposit Option (only if not walk-in and has balance) -->
                    <?php if ($sale['is_default'] != 1): ?>
                        <label class="relative <?= $sale['current_balance'] <= 0 ? 'opacity-50' : '' ?>">
                            <input type="radio" name="payment_method" value="deposit"
                                <?= $sale['current_balance'] <= 0 ? 'disabled' : '' ?>
                                class="peer sr-only">
                            <div class="px-4 py-3 border-2 border-gray-300 rounded-lg cursor-pointer
                                        hover:border-blue-400 peer-checked:border-blue-600 peer-checked:bg-blue-50
                                        peer-disabled:cursor-not-allowed peer-disabled:hover:border-gray-300
                                        transition text-center">
                                <div class="text-2xl mb-1">💰</div>
                                <div class="text-sm font-semibold text-gray-700">Deposit</div>
                                <?php if ($sale['current_balance'] > 0): ?>
                                    <div class="text-xs text-green-600 mt-1">
                                        <?= formatMoney($sale['current_balance']) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-xs text-red-500 mt-1">
                                        No balance
                                    </div>
                                <?php endif; ?>
                            </div>
                        </label>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Buttons -->
            <div class="flex gap-3">
                <button type="submit"
                    class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg
                            transition shadow-lg hover:shadow-xl">
                    ✓ Record Payment
                </button>
                <a href="<?= BASE_URL ?>/sales/view/<?= $sale['id'] ?>"
                    class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold
                            rounded-lg transition text-center">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- Payment Instructions -->
    <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
        <h3 class="font-semibold text-blue-800 mb-2">💡 Payment Options</h3>
        <ul class="text-sm text-blue-700 space-y-1">
            <li>• <strong>Cash/Mobile/Bank:</strong> Direct payment methods</li>
            <?php if ($sale['is_default'] != 1 && $sale['current_balance'] > 0): ?>
                <li>• <strong>Deposit:</strong> Pay from customer's deposit balance (available: <?= formatMoney($sale['current_balance']) ?>)</li>
                <li>• Partial payments allowed if deposit insufficient</li>
            <?php endif; ?>
            <li>• Payment cannot exceed amount due</li>
        </ul>
    </div>

</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>