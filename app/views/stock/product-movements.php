<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="mb-5 text-sm flex gap-3">
    <a href="<?= BASE_URL ?>/stock" class="text-blue-600 hover:underline">← Stock</a>
    <span class="text-gray-400">/</span>
    <span class="text-gray-600"><?= e($product['name']) ?></span>
</div>

<!-- Product Header -->
<div class="bg-white rounded-lg shadow p-5 mb-6">
    <div class="flex flex-wrap justify-between items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800"><?= e($product['name']) ?></h1>
            <div class="text-sm text-gray-500 mt-1">
                SKU: <?= e($product['sku']) ?>
                <?php if (!empty($product['barcode'])): ?>
                    · Barcode: <?= e($product['barcode']) ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="<?= BASE_URL ?>/stock/in?product=<?= $product['id'] ?>"
                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm transition">
                ↑ Stock In
            </a>
            <a href="<?= BASE_URL ?>/stock/adjust/<?= $product['id'] ?>"
                class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm transition">
                ⚙ Adjust
            </a>
        </div>
    </div>
</div>

<!-- Summary Stats -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white p-5 rounded-lg shadow text-center">
        <div class="text-gray-400 text-xs mb-1">Current Stock</div>
        <div class="text-3xl font-bold
            <?= $product['current_stock'] <= 0 ? 'text-red-600'
                : ($product['current_stock'] <= $product['reorder_level'] ? 'text-yellow-500' : 'text-green-600') ?>">
            <?= $product['current_stock'] ?>
        </div>
        <div class="text-xs text-gray-400"><?= e($product['unit']) ?></div>
    </div>
    <div class="bg-white p-5 rounded-lg shadow text-center">
        <div class="text-gray-400 text-xs mb-1">Total Received</div>
        <div class="text-3xl font-bold text-green-600"><?= safeInt($summary['total_in']) ?></div>
        <div class="text-xs text-gray-400"><?= e($product['unit']) ?></div>
    </div>
    <div class="bg-white p-5 rounded-lg shadow text-center">
        <div class="text-gray-400 text-xs mb-1">Total Issued</div>
        <div class="text-3xl font-bold text-red-500"><?= safeInt($summary['total_out']) ?></div>
        <div class="text-xs text-gray-400"><?= e($product['unit']) ?></div>
    </div>
    <div class="bg-white p-5 rounded-lg shadow text-center">
        <div class="text-gray-400 text-xs mb-1">Stock Value</div>
        <div class="text-2xl font-bold text-blue-600">
            <?= formatMoney($product['current_stock'] * $product['cost_price']) ?>
        </div>
        <div class="text-xs text-gray-400">At cost price</div>
    </div>
</div>

<!-- Movement History -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-5 py-4 border-b">
        <h2 class="font-bold text-gray-700">Movement History (Last 50)</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Qty</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Before</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">After</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">By</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($movements)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-400">No movements recorded yet</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($movements as $m): ?>
                        <?php
                        $typeConfig = [
                            'in'         => ['class' => 'bg-green-100 text-green-700',  'label' => '↑ In',     'qty' => 'text-green-600'],
                            'out'        => ['class' => 'bg-red-100 text-red-600',      'label' => '↓ Out',    'qty' => 'text-red-600'],
                            'adjustment' => ['class' => 'bg-purple-100 text-purple-700', 'label' => '⚙ Adjust', 'qty' => 'text-purple-600'],
                        ];
                        $tc = $typeConfig[$m['movement_type']] ?? $typeConfig['adjustment'];
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                                <?= formatDate($m['created_at'], 'd M Y H:i') ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-1 rounded-full text-xs font-semibold <?= $tc['class'] ?>">
                                    <?= $tc['label'] ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center font-bold <?= $tc['qty'] ?>">
                                <?= $m['movement_type'] === 'out' ? '-' : '+' ?><?= $m['quantity'] ?>
                            </td>
                            <td class="px-4 py-3 text-center text-sm text-gray-500"><?= $m['previous_stock'] ?></td>
                            <td class="px-4 py-3 text-center text-sm font-semibold"><?= $m['new_stock'] ?></td>
                            <td class="px-4 py-3 text-sm text-gray-500 max-w-xs truncate"><?= e($m['notes'] ?? '—') ?></td>
                            <td class="px-4 py-3 text-xs text-gray-500"><?= e($m['user_name'] ?? 'System') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>