<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="container mx-auto px-4 py-6">

    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
        <a href="<?= BASE_URL ?>/purchases" class="hover:text-blue-600">Purchases</a>
        <span>›</span>
        <span class="text-gray-800"><?= e($purchase['purchase_number']) ?></span>
    </div>

    <?= flashMessage() ?>

    <!-- Header with Actions -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Purchase #<?= e($purchase['purchase_number']) ?></h1>
            <p class="text-gray-500 text-sm mt-1">
                Created <?= date('d M Y, H:i', strtotime($purchase['purchase_date'])) ?>
                by <?= e($purchase['created_by'] ?? 'Staff') ?>
            </p>
        </div>
        <div class="flex gap-2">
            <a href="<?= BASE_URL ?>/purchases/edit/<?= $purchase['id'] ?>"
                class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg font-medium transition">
                ✏️ Edit Purchase
            </a>
            <?php if ($purchase['payment_status'] !== 'paid'): ?>
                <a href="<?= BASE_URL ?>/purchases/pay/<?= $purchase['id'] ?>"
                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition">
                    💳 Record Payment
                </a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/purchases/receipt/<?= $purchase['id'] ?>"
                target="_blank"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition">
                🖨️ Print GRN
            </a>
            <a href="<?= BASE_URL ?>/purchases/void/<?= $purchase['id'] ?>"
                onclick="return confirm('Are you sure you want to void this purchase? This will reduce stock levels, revert product average costs, refund any recorded payments, and delete the purchase record. This action cannot be undone.')"
                class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition">
                ⚠️ Void Purchase
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left Column - Purchase Details -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Supplier & Invoice Info -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Supplier Information</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="text-xs text-gray-500 mb-1">Supplier Name</div>
                        <div class="font-medium text-gray-800"><?= e($purchase['company_name']) ?></div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 mb-1">Supplier Code</div>
                        <div class="font-mono text-sm text-gray-600"><?= e($purchase['supplier_code']) ?></div>
                    </div>
                    <?php if (!empty($purchase['contact_name'])): ?>
                        <div>
                            <div class="text-xs text-gray-500 mb-1">Contact Person</div>
                            <div class="text-gray-800"><?= e($purchase['contact_name']) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($purchase['supplier_phone'])): ?>
                        <div>
                            <div class="text-xs text-gray-500 mb-1">Phone</div>
                            <div class="text-gray-800"><?= e($purchase['supplier_phone']) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($purchase['invoice_number'])): ?>
                        <div class="col-span-2">
                            <div class="text-xs text-gray-500 mb-1">Supplier Invoice #</div>
                            <div class="font-mono font-semibold text-blue-600"><?= e($purchase['invoice_number']) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Purchase Items -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b">
                    <h2 class="text-lg font-bold text-gray-800">Purchase Items</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Product</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Qty</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Unit Cost</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Discount</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Line Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php foreach ($items as $item): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-800"><?= e($item['product_name']) ?></div>
                                        <div class="text-xs text-gray-400">
                                            <span>SKU: <?= e($item['sku']) ?></span>
                                            <?php if (!empty($item['current_stock'])): ?>
                                                <span class="mx-1">•</span>
                                                <span>Current Stock: <?= number_format($item['current_stock']) ?> <?= e($item['unit']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="font-semibold"><?= formatQty($item['quantity']) ?></span>
                                        <span class="text-xs text-gray-400 ml-1"><?= e($item['unit'] ?? 'pcs') ?></span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-sm">
                                        <?= formatMoney($item['unit_cost']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <?php if ($item['discount_percent'] > 0): ?>
                                            <span class="text-green-600 font-semibold"><?= $item['discount_percent'] ?>%</span>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-800">
                                        <?= formatMoney($item['line_total']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-gray-50 border-t-2">
                            <tr>
                                <td colspan="4" class="px-4 py-3 text-right font-semibold text-gray-700">
                                    Subtotal:
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-gray-800">
                                    <?= formatMoney($purchase['subtotal']) ?>
                                </td>
                            </tr>
                            <?php if ($purchase['discount_amount'] > 0): ?>
                                <tr>
                                    <td colspan="4" class="px-4 py-2 text-right text-sm text-gray-600">
                                        Discount (<?= $purchase['discount_percent'] ?>%):
                                    </td>
                                    <td class="px-4 py-2 text-right text-sm text-green-600">
                                        - <?= formatMoney($purchase['discount_amount']) ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <?php if ($purchase['vat_amount'] > 0): ?>
                                <tr>
                                    <td colspan="4" class="px-4 py-2 text-right text-sm text-gray-600">
                                        VAT (<?= $purchase['vat_percent'] ?>%):
                                    </td>
                                    <td class="px-4 py-2 text-right text-sm text-orange-600">
                                        + <?= formatMoney($purchase['vat_amount']) ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <tr>
                                <td colspan="4" class="px-4 py-4 text-right font-bold text-gray-700 text-lg">
                                    TOTAL:
                                </td>
                                <td class="px-4 py-4 text-right font-bold text-blue-600 text-xl">
                                    <?= formatMoney($purchase['total_amount']) ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Payment History -->
            <?php if (!empty($payments)): ?>
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b">
                        <h2 class="text-lg font-bold text-gray-800">Payment History</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Method</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Amount</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Recorded By</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <?php foreach ($payments as $pmt): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            <?= date('d M Y, H:i', strtotime($pmt['created_at'])) ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs font-semibold">
                                                <?= ucfirst($pmt['payment_method'] ?? 'N/A') ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold text-green-600">
                                            <?= formatMoney($pmt['amount']) ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            <?= e($pmt['user_name'] ?? 'System') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Notes -->
            <?php if (!empty($purchase['notes'])): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-700 mb-2">📝 Notes</h3>
                    <p class="text-gray-600 text-sm"><?= nl2br(e($purchase['notes'])) ?></p>
                </div>
            <?php endif; ?>

        </div>

        <!-- Right Column - Summary Cards -->
        <div class="space-y-6">

            <!-- Payment Summary -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Payment Summary</h2>

                <div class="space-y-3">
                    <div class="flex justify-between items-center pb-3 border-b">
                        <span class="text-gray-600">Total Amount:</span>
                        <span class="font-bold text-lg text-gray-800">
                            <?= formatMoney($purchase['total_amount']) ?>
                        </span>
                    </div>

                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Amount Paid:</span>
                        <span class="font-semibold text-green-600">
                            <?= formatMoney($purchase['amount_paid']) ?>
                        </span>
                    </div>

                    <div class="flex justify-between items-center pb-3 border-b">
                        <span class="text-gray-600">Balance Due:</span>
                        <span class="font-semibold text-red-600">
                            <?= formatMoney($purchase['amount_due']) ?>
                        </span>
                    </div>

                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Payment Method:</span>
                        <span class="px-2 py-1 bg-gray-100 rounded text-sm font-medium">
                            <?= ucfirst($purchase['payment_method']) ?>
                        </span>
                    </div>

                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Status:</span>
                        <?php
                        $statusColors = [
                            'paid' => 'bg-green-100 text-green-700',
                            'partial' => 'bg-yellow-100 text-yellow-700',
                            'unpaid' => 'bg-red-100 text-red-700'
                        ];
                        $color = $statusColors[$purchase['payment_status']] ?? 'bg-gray-100 text-gray-700';
                        ?>
                        <span class="px-3 py-1 rounded-full text-sm font-semibold <?= $color ?>">
                            <?= ucfirst($purchase['payment_status']) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Quick Actions</h2>
                <div class="space-y-2">
                    <a href="<?= BASE_URL ?>/purchases/edit/<?= $purchase['id'] ?>"
                        class="block w-full bg-yellow-500 hover:bg-yellow-600 text-white text-center px-4 py-3 rounded-lg font-medium transition">
                        ✏️ Edit Purchase
                    </a>
                    <?php if ($purchase['payment_status'] !== 'paid'): ?>
                        <a href="<?= BASE_URL ?>/purchases/pay/<?= $purchase['id'] ?>"
                            class="block w-full bg-green-600 hover:bg-green-700 text-white text-center px-4 py-3 rounded-lg font-medium transition">
                            💳 Record Payment
                        </a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/purchases/receipt/<?= $purchase['id'] ?>"
                        target="_blank"
                        class="block w-full bg-blue-600 hover:bg-blue-700 text-white text-center px-4 py-3 rounded-lg font-medium transition">
                        🖨️ Print GRN
                    </a>
                    <a href="<?= BASE_URL ?>/suppliers/view/<?= $purchase['supplier_id'] ?>"
                        class="block w-full bg-gray-600 hover:bg-gray-700 text-white text-center px-4 py-3 rounded-lg font-medium transition">
                        👤 View Supplier
                    </a>
                    <a href="<?= BASE_URL ?>/purchases"
                        class="block w-full bg-gray-200 hover:bg-gray-300 text-gray-700 text-center px-4 py-3 rounded-lg font-medium transition">
                        ← Back to Purchases
                    </a>
                </div>
            </div>

            <!-- Purchase Info -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h3 class="font-semibold text-blue-800 mb-3">📋 Purchase Details</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-blue-700">Purchase #:</span>
                        <span class="font-mono font-semibold text-blue-900">
                            <?= e($purchase['purchase_number']) ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-blue-700">Date:</span>
                        <span class="font-medium text-blue-900">
                            <?= date('d M Y', strtotime($purchase['purchase_date'])) ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-blue-700">Time:</span>
                        <span class="font-medium text-blue-900">
                            <?= date('H:i', strtotime($purchase['purchase_date'])) ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-blue-700">Created By:</span>
                        <span class="font-medium text-blue-900">
                            <?= e($purchase['created_by'] ?? 'Staff') ?>
                        </span>
                    </div>
                </div>
            </div>

        </div>

    </div>

</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>