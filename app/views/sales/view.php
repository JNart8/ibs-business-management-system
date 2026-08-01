<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="mb-5 text-sm flex justify-between items-center">
    <a href="<?= BASE_URL ?>/sales" class="text-blue-600 hover:underline">← Back to Sales</a>
    <div class="flex gap-2">
        <a href="<?= BASE_URL ?>/sales/receipt/<?= $sale['id'] ?>" target="_blank"
            class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm transition">
            🖨 Print Receipt
        </a>
        <?php if ($sale['payment_status'] !== 'paid'): ?>
            <a href="<?= BASE_URL ?>/sales/pay/<?= $sale['id'] ?>"
                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm transition">
                💳 Record Payment
            </a>
        <?php endif; ?>
    </div>
</div>

<?= flashMessage() ?>

<!-- Sale Header -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <div class="flex flex-wrap justify-between items-start gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800"><?= e($sale['sale_number']) ?></h1>
            <div class="flex flex-wrap gap-3 mt-2 text-sm text-gray-500">
                <span>👤 <?= e($sale['customer_name']) ?></span>
                <?php if (!empty($sale['customer_phone'])): ?>
                    <span>📞 <?= e($sale['customer_phone']) ?></span>
                <?php endif; ?>
                <span>🕐 <?= formatDate($sale['sale_date'], 'd M Y H:i') ?></span>
                <?php if (!empty($sale['cashier_name'])): ?>
                    <span>👩‍💼 <?= e($sale['cashier_name']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php
        $isVoided = strpos($sale['notes'] ?? '', '[VOIDED]') !== false;
        $statusText = $isVoided ? 'VOIDED' : strtoupper($sale['payment_status']);
        $sc = $isVoided ? 'bg-gray-100 text-gray-600 border border-gray-200' : ([
            'paid'    => 'bg-green-100 text-green-700',
            'partial' => 'bg-yellow-100 text-yellow-700',
            'unpaid'  => 'bg-red-100 text-red-600',
        ][$sale['payment_status']] ?? 'bg-gray-100');
        ?>
        <span class="px-4 py-2 rounded-full font-bold text-sm <?= $sc ?>">
            <?= $statusText ?>
        </span>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Items Table (2/3) -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-5 py-4 border-b font-bold text-gray-700">Items</div>
            <table class="w-full">
                <thead class="bg-gray-50 border-b text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">Product</th>
                        <th class="px-4 py-3 text-center">Qty</th>
                        <th class="px-4 py-3 text-right">Unit Price</th>
                        <th class="px-4 py-3 text-center">Disc %</th>
                        <th class="px-4 py-3 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-800 text-sm"><?= e($item['product_name']) ?></div>
                                <?php if (!empty($item['sku'])): ?>
                                    <div class="text-xs text-gray-400"><?= e($item['sku']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-center text-sm">
                                <?= $item['quantity'] ?> <?= e($item['unit'] ?? '') ?>
                            </td>
                            <td class="px-4 py-3 text-right text-sm"><?= formatMoney($item['unit_price']) ?></td>
                            <td class="px-4 py-3 text-center text-sm text-gray-500">
                                <?php if (($item['discount_amount'] ?? 0) > 0 || ($item['discount_percent'] ?? 0) > 0): ?>
                                    <?= ($item['discount_type'] ?? 'percentage') === 'flat'
                                        ? formatMoney($item['discount_amount'])
                                        : e($item['discount_percent']) . '%' ?>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold"><?= formatMoney($item['line_total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Payment History -->
        <?php if (!empty($payments)): ?>
            <div class="bg-white rounded-lg shadow overflow-hidden mt-4">
                <div class="px-5 py-4 border-b font-bold text-gray-700">Payment History</div>
                <div class="divide-y">
                    <?php foreach ($payments as $pmt): ?>
                        <div class="flex justify-between items-center px-5 py-3">
                            <div>
                                <div class="text-sm font-medium text-gray-700 capitalize"><?= $pmt['payment_method'] ?></div>
                                <div class="text-xs text-gray-400"><?= formatDate($pmt['created_at'], 'd M Y H:i') ?></div>
                            </div>
                            <div class="font-bold text-green-600"><?= formatMoney($pmt['amount']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Summary (1/3) -->
    <div class="space-y-4">
        <div class="bg-white rounded-lg shadow p-5 space-y-3">
            <h2 class="font-bold text-gray-700 border-b pb-2">Summary</h2>
            <div class="flex justify-between text-sm text-gray-600">
                <span>Subtotal</span>
                <span><?= formatMoney($sale['subtotal']) ?></span>
            </div>
            <?php if ($sale['discount_amount'] > 0): ?>
                <div class="flex justify-between text-sm text-red-500">
                    <span>Discount<?= ($sale['discount_type'] ?? 'percentage') === 'percentage' ? ' (' . e($sale['discount_percent']) . '%)' : '' ?></span>
                    <span>- <?= formatMoney($sale['discount_amount']) ?></span>
                </div>
            <?php endif; ?>
            <div class="flex justify-between font-bold text-lg border-t pt-2">
                <span>Total</span>
                <span><?= formatMoney($sale['total_amount']) ?></span>
            </div>
            <div class="flex justify-between text-sm text-green-600">
                <span>Paid</span>
                <span><?= formatMoney($sale['amount_paid']) ?></span>
            </div>
            <?php if ($sale['amount_due'] > 0): ?>
                <div class="flex justify-between text-sm font-bold text-red-500 bg-red-50 -mx-5 px-5 py-2">
                    <span>Balance Due</span>
                    <span><?= formatMoney($sale['amount_due']) ?></span>
                </div>
            <?php endif; ?>
            <div class="text-sm text-gray-500 pt-2">
                <span class="capitalize">💳 <?= $sale['payment_method'] ?></span>
            </div>
        </div>

        <?php if (!empty($sale['notes'])): ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="text-xs font-semibold text-yellow-700 mb-1">Notes</div>
                <div class="text-sm text-gray-600"><?= e($sale['notes']) ?></div>
            </div>
        <?php endif; ?>

        <a href="<?= BASE_URL ?>/sales/void/<?= $sale['id'] ?>"
            onclick="return confirmDelete('Void sale <?= e($sale['sale_number']) ?>? This will reverse stock changes.')"
            class="block text-center bg-red-50 hover:bg-red-100 text-red-600 border border-red-200
              px-4 py-3 rounded-lg text-sm font-semibold transition">
            ⚠️ Void Sale
        </a>
    </div>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
