<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="max-w-2xl mx-auto">
    <?php
    $statusBadge = [
        'in_transit' => 'bg-amber-100 text-amber-700',
        'completed'  => 'bg-green-100 text-green-700',
        'cancelled'  => 'bg-gray-100 text-gray-500',
    ][$transfer['status']] ?? 'bg-gray-100 text-gray-500';
    $statusLabel = [
        'in_transit' => '⏳ In Transit',
        'completed'  => '✅ Completed',
        'cancelled'  => '🚫 Cancelled',
    ][$transfer['status']] ?? ucfirst($transfer['status']);
    ?>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">🔄 Transfer <?= e($transfer['transfer_number']) ?></h1>
            <p class="text-gray-500 text-sm mt-1"><?= formatDate($transfer['created_at']) ?></p>
        </div>
        <span class="px-3 py-1.5 rounded-full text-sm font-semibold <?= $statusBadge ?>"><?= $statusLabel ?></span>
    </div>

    <div class="mb-4">
        <a href="<?= BASE_URL ?>/transfers"
            class="bg-gray-100 hover:bg-gray-200 text-gray-800 px-4 py-2.5 rounded-lg text-sm font-medium transition inline-block">
            ← Back to Transfers
        </a>
    </div>

    <?= flashMessage() ?>

    <div class="bg-white rounded-xl shadow-sm border p-6 mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
            <div>
                <div class="text-xs text-gray-400 uppercase mb-1">From</div>
                <div class="font-semibold text-gray-800"><?= e($transfer['from_branch_name'] ?? '—') ?></div>
            </div>
            <div>
                <div class="text-xs text-gray-400 uppercase mb-1">To</div>
                <div class="font-semibold text-gray-800"><?= e($transfer['to_branch_name'] ?? '—') ?></div>
            </div>
            <div>
                <div class="text-xs text-gray-400 uppercase mb-1">By</div>
                <div class="font-semibold text-gray-800"><?= e($transfer['user_name'] ?? 'System') ?></div>
            </div>
        </div>
        <?php if (!empty($transfer['notes'])): ?>
            <div class="mt-4 pt-4 border-t">
                <div class="text-xs text-gray-400 uppercase mb-1">Notes</div>
                <div class="text-sm text-gray-700"><?= nl2br(e($transfer['notes'])) ?></div>
            </div>
        <?php endif; ?>
        <?php if ($transfer['status'] === 'completed' && !empty($transfer['received_at'])): ?>
            <div class="mt-4 pt-4 border-t text-sm text-gray-600">
                Received <?= formatDate($transfer['received_at']) ?> by <?= e($transfer['received_by_name'] ?? 'someone') ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden mb-4">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-5 py-3 text-left  text-xs font-medium text-gray-500 uppercase">Product</th>
                    <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Dispatched</th>
                    <?php if ($transfer['status'] !== 'in_transit'): ?>
                        <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Received</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($items as $item): ?>
                    <?php
                    $hasShortfall = $item['quantity_received'] !== null && (float) $item['quantity_received'] < (float) $item['quantity'];
                    ?>
                    <tr>
                        <td class="px-5 py-3 text-sm">
                            <div class="font-medium text-gray-800"><?= e($item['product_name'] ?? 'Unknown product') ?></div>
                            <div class="text-xs text-gray-400"><?= e($item['sku'] ?? '') ?></div>
                        </td>
                        <td class="px-5 py-3 text-right text-sm text-gray-700">
                            <?= formatQty($item['quantity']) ?> <?= e($item['unit'] ?? '') ?>
                        </td>
                        <?php if ($transfer['status'] !== 'in_transit'): ?>
                            <td class="px-5 py-3 text-right text-sm <?= $hasShortfall ? 'text-red-600 font-semibold' : 'text-gray-700' ?>">
                                <?php if ($item['quantity_received'] !== null): ?>
                                    <?= formatQty($item['quantity_received']) ?> <?= e($item['unit'] ?? '') ?>
                                    <?php if ($hasShortfall): ?>
                                        <div class="text-xs">short by <?= formatQty($item['quantity'] - $item['quantity_received']) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($canReceive || $canCancel): ?>
        <div class="flex gap-2">
            <?php if ($canReceive): ?>
                <a href="<?= BASE_URL ?>/transfers/receive/<?= $transfer['id'] ?>"
                    class="flex-1 text-center bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-lg text-sm font-semibold transition">
                    ✅ Receive Transfer
                </a>
            <?php endif; ?>
            <?php if ($canCancel): ?>
                <a href="<?= BASE_URL ?>/transfers/cancel/<?= $transfer['id'] ?>"
                    onclick="return confirmDelete('Cancel transfer <?= e($transfer['transfer_number']) ?>? Stock will be returned to <?= e($transfer['from_branch_name'] ?? 'the source branch') ?>.')"
                    class="flex-1 text-center bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 px-4 py-3 rounded-lg text-sm font-semibold transition">
                    🚫 Cancel Transfer
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
