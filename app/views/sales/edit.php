<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="mb-5">
    <a href="<?= BASE_URL ?>/sales/view/<?= $sale['id'] ?>" class="text-blue-600 hover:underline">
        ← Back to Sale Details
    </a>
</div>

<?= flashMessage() ?>

<!-- Warning Banner -->
<div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mb-6">
    <div class="flex items-start">
        <div class="text-2xl mr-3">⚠️</div>
        <div>
            <h3 class="text-yellow-800 font-semibold">Edit Sale with Caution</h3>
            <p class="text-yellow-700 text-sm mt-1">
                Editing a sale will adjust customer balances and transaction history.
                All changes are logged in the audit trail.
            </p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- LEFT: Edit Form (2/3) -->
    <div class="lg:col-span-2 space-y-6">

        <!-- Sale Information (Read-Only) -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Sale Information</h2>

            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="text-gray-500">Sale Number:</span>
                    <span class="font-semibold text-gray-800 ml-2"><?= e($sale['sale_number']) ?></span>
                </div>
                <div>
                    <span class="text-gray-500">Date:</span>
                    <span class="font-semibold text-gray-800 ml-2">
                        <?= formatDate($sale['sale_date'], 'd M Y H:i') ?>
                    </span>
                </div>
                <div>
                    <span class="text-gray-500">Customer:</span>
                    <span class="font-semibold text-gray-800 ml-2"><?= e($sale['customer_name']) ?></span>
                </div>
                <div>
                    <span class="text-gray-500">Cashier:</span>
                    <span class="font-semibold text-gray-800 ml-2"><?= e($sale['cashier_name'] ?? 'Unknown') ?></span>
                </div>
                <div>
                    <span class="text-gray-500">Total Amount:</span>
                    <span class="font-bold text-green-600 ml-2"><?= formatMoney($sale['total_amount']) ?></span>
                </div>
                <div>
                    <span class="text-gray-500">Current Status:</span>
                    <?php
                    $statusColors = [
                        'paid'    => 'bg-green-100 text-green-700',
                        'partial' => 'bg-yellow-100 text-yellow-700',
                        'unpaid'  => 'bg-red-100 text-red-600',
                    ];
                    $color = $statusColors[$sale['payment_status']] ?? 'bg-gray-100 text-gray-600';
                    ?>
                    <span class="inline-block px-2 py-1 rounded text-xs font-semibold <?= $color ?> ml-2">
                        <?= ucfirst($sale['payment_status']) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Items (Read-Only) -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Sale Items (Cannot Edit)</h2>
            <p class="text-xs text-gray-500 mb-4">
                Note: Items cannot be edited after sale is completed. Contact support if you need to modify items.
            </p>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs text-gray-500">Product</th>
                            <th class="px-3 py-2 text-right text-xs text-gray-500">Qty</th>
                            <th class="px-3 py-2 text-right text-xs text-gray-500">Price</th>
                            <th class="px-3 py-2 text-right text-xs text-gray-500">Disc%</th>
                            <th class="px-3 py-2 text-right text-xs text-gray-500">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="px-3 py-2"><?= e($item['product_name']) ?></td>
                                <td class="px-3 py-2 text-right"><?= $item['quantity'] ?></td>
                                <td class="px-3 py-2 text-right"><?= formatMoney($item['unit_price']) ?></td>
                                <td class="px-3 py-2 text-right">
                                    <?php if (($item['discount_amount'] ?? 0) > 0 || ($item['discount_percent'] ?? 0) > 0): ?>
                                        <?= ($item['discount_type'] ?? 'percentage') === 'flat'
                                            ? formatMoney($item['discount_amount'])
                                            : e($item['discount_percent']) . '%' ?>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td class="px-3 py-2 text-right font-semibold">
                                    <?= formatMoney($item['line_total']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="border-t-2">
                        <tr>
                            <td colspan="4" class="px-3 py-2 text-right font-semibold">Subtotal:</td>
                            <td class="px-3 py-2 text-right font-semibold">
                                <?= formatMoney($sale['subtotal']) ?>
                            </td>
                        </tr>
                        <?php if ($sale['discount_amount'] > 0): ?>
                            <tr>
                                <td colspan="4" class="px-3 py-2 text-right text-sm text-gray-500">
                                    Discount<?= ($sale['discount_type'] ?? 'percentage') === 'percentage' ? ' (' . e($sale['discount_percent']) . '%)' : '' ?>:
                                </td>
                                <td class="px-3 py-2 text-right text-sm text-red-500">
                                    -<?= formatMoney($sale['discount_amount']) ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <tr class="font-bold text-lg">
                            <td colspan="4" class="px-3 py-3 text-right">TOTAL:</td>
                            <td class="px-3 py-3 text-right text-green-600">
                                <?= formatMoney($sale['total_amount']) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Edit Form -->
        <form method="POST" action="<?= BASE_URL ?>/sales/edit/<?= $sale['id'] ?>" class="bg-white rounded-lg shadow p-6">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <h2 class="text-xl font-bold text-gray-800 mb-4">Edit Payment Information</h2>

            <!-- Payment Method -->
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Payment Method *
                </label>
                <select name="payment_method" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <?php
                    $methods = [
                        'cash' => '💵 Cash',
                        'mobile' => '📱 Mobile Money',
                        'bank' => '🏦 Bank Transfer',
                        'credit' => '📝 Credit',
                        'deposit' => '💰 From Deposit'
                    ];
                    foreach ($methods as $val => $label):
                    ?>
                        <option value="<?= $val ?>" <?= ($sale['payment_method'] === $val) ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Amount Paid -->
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Amount Paid *
                </label>
                <div class="relative">
                    <span class="absolute left-3 top-2.5 text-gray-500 font-semibold">
                        <?= CURRENCY_HOLDER ?>
                    </span>
                    <input type="number"
                        name="amount_paid"
                        step="0.01"
                        min="0"
                        max="<?= $sale['total_amount'] ?>"
                        value="<?= $sale['amount_paid'] ?>"
                        required
                        class="w-full pl-14 pr-4 py-2 border border-gray-300 rounded-lg text-lg font-bold
                               focus:ring-2 focus:ring-blue-500">
                </div>
                <p class="text-xs text-gray-500 mt-1">
                    Current: <?= formatMoney($sale['amount_paid']) ?> |
                    Maximum: <?= formatMoney($sale['total_amount']) ?>
                </p>
            </div>

            <!-- Notes -->
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Notes (Optional)
                </label>
                <textarea name="notes" rows="2"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                    placeholder="Any additional notes about this sale..."><?= e($sale['notes'] ?? '') ?></textarea>
            </div>

            <!-- Sale Date (Editable) -->
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    📅 Sale Date <span class="text-xs text-gray-400 font-normal">(Admin: back-date if needed)</span>
                </label>
                <input type="date"
                    name="sale_date"
                    value="<?= date('Y-m-d', strtotime($sale['sale_date'])) ?>"
                    max="<?= date('Y-m-d') ?>"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                <p class="text-xs text-gray-500 mt-1">
                    Current: <?= formatDate($sale['sale_date'], 'd M Y H:i') ?> — Set to a past date to back-date this sale.
                </p>
            </div>

            <!-- Edit Reason (Required for Audit) -->
            <div class="mb-6">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Reason for Edit * (Required for audit trail)
                </label>
                <textarea name="edit_reason" rows="3" required
                    class="w-full px-4 py-2 border-2 border-orange-300 rounded-lg focus:ring-2 focus:ring-orange-500"
                    placeholder="Example: Customer made additional payment, Payment method was recorded incorrectly, etc."></textarea>
                <p class="text-xs text-orange-600 mt-1">
                    ⚠️ This will be logged in the audit trail
                </p>
            </div>

            <!-- Submit Buttons -->
            <div class="flex gap-3">
                <button type="submit"
                    class="flex-1 bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-semibold transition">
                    💾 Save Changes
                </button>
                <a href="<?= BASE_URL ?>/sales/view/<?= $sale['id'] ?>"
                    class="px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </a>
            </div>
        </form>

    </div>

    <!-- RIGHT: Summary & History (1/3) -->
    <div class="space-y-6">

        <!-- Current vs New Comparison -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="font-bold text-gray-800 mb-4">Payment Summary</h3>

            <div class="space-y-3 text-sm">
                <div class="flex justify-between border-b pb-2">
                    <span class="text-gray-600">Total Amount:</span>
                    <span class="font-bold"><?= formatMoney($sale['total_amount']) ?></span>
                </div>
                <div class="flex justify-between border-b pb-2">
                    <span class="text-gray-600">Currently Paid:</span>
                    <span class="font-semibold text-green-600"><?= formatMoney($sale['amount_paid']) ?></span>
                </div>
                <div class="flex justify-between border-b pb-2">
                    <span class="text-gray-600">Currently Due:</span>
                    <span class="font-semibold <?= $sale['amount_due'] > 0 ? 'text-red-600' : 'text-gray-400' ?>">
                        <?= formatMoney($sale['amount_due']) ?>
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Payment Method:</span>
                    <span class="font-medium capitalize"><?= $sale['payment_method'] ?></span>
                </div>
            </div>
        </div>

        <!-- Customer Balance Info -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h4 class="font-semibold text-blue-800 mb-2 text-sm">Customer Balance</h4>
            <div class="text-xs text-blue-700 space-y-1">
                <div class="flex justify-between">
                    <span>Current:</span>
                    <span class="font-bold">
                        <?= formatMoney(abs($sale['customer_balance'])) ?>
                        <?= $sale['customer_balance'] < 0 ? '(Owes)' : ($sale['customer_balance'] > 0 ? '(Credit)' : '') ?>
                    </span>
                </div>
                <p class="text-[10px] text-blue-600 mt-2 border-t border-blue-200 pt-2">
                    💡 Changing payment amount will adjust customer balance
                </p>
            </div>
        </div>

        <!-- Edit History -->
        <?php if (!empty($editHistory)): ?>
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-bold text-gray-800 mb-4 text-sm">Edit History</h3>
                <div class="space-y-3">
                    <?php foreach ($editHistory as $edit): ?>
                        <div class="border-l-2 border-gray-300 pl-3 text-xs">
                            <div class="font-semibold text-gray-700">
                                <?= e($edit['editor_name'] ?? 'Unknown') ?>
                            </div>
                            <div class="text-gray-500">
                                <?= formatDate($edit['created_at'], 'd M Y H:i') ?>
                            </div>
                            <div class="text-gray-600 mt-1">
                                <?= e($edit['changes']) ?>
                            </div>
                            <?php if (!empty($edit['reason'])): ?>
                                <div class="text-gray-400 italic text-[10px] mt-1">
                                    "<?= e($edit['reason']) ?>"
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- What Will Change -->
        <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
            <h4 class="font-semibold text-orange-800 mb-2 text-sm">⚠️ What Will Change:</h4>
            <ul class="text-xs text-orange-700 space-y-1 list-disc list-inside">
                <li>Sale payment information updated</li>
                <li>Sale date updated (if changed)</li>
                <li>Customer balance adjusted (if payment changes)</li>
                <li>Customer transaction logged</li>
                <li>updated_at timestamp set to NOW()</li>
                <li>Change logged in audit trail</li>
            </ul>
        </div>

    </div>

</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
