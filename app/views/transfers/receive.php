<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">✅ Receive Transfer <?= e($transfer['transfer_number']) ?></h1>
            <p class="text-gray-500 text-sm mt-1">
                From <?= e($transfer['from_branch_name'] ?? '—') ?> to <?= e($transfer['to_branch_name'] ?? '—') ?>
            </p>
        </div>
    </div>

    <div class="mb-4">
        <a href="<?= BASE_URL ?>/transfers/view/<?= $transfer['id'] ?>"
            class="bg-gray-100 hover:bg-gray-200 text-gray-800 px-4 py-2.5 rounded-lg text-sm font-medium transition inline-block">
            ← Back to Transfer
        </a>
    </div>

    <?= flashMessage() ?>

    <div class="bg-blue-50 border border-blue-200 text-blue-800 text-sm rounded-lg p-4 mb-4">
        ℹ️ Confirm how much of each item actually arrived. If everything showed up as sent, just
        leave the quantities as they are and submit — they're pre-filled with what was
        dispatched.
    </div>

    <form action="<?= BASE_URL ?>/transfers/receive/<?= $transfer['id'] ?>" method="POST">
        <?= csrfField() ?>

        <div class="bg-white rounded-xl shadow-sm border overflow-hidden mb-4">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-5 py-3 text-left  text-xs font-medium text-gray-500 uppercase">Product</th>
                        <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Dispatched</th>
                        <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Received Qty</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="px-5 py-3 text-sm">
                                <div class="font-medium text-gray-800"><?= e($item['product_name'] ?? 'Unknown product') ?></div>
                                <div class="text-xs text-gray-400"><?= e($item['sku'] ?? '') ?></div>
                            </td>
                            <td class="px-5 py-3 text-right text-sm text-gray-700">
                                <?= formatQty($item['quantity']) ?> <?= e($item['unit'] ?? '') ?>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <input type="number" step="0.01" min="0" max="<?= e($item['quantity']) ?>"
                                    name="received[<?= $item['id'] ?>]" value="<?= e($item['quantity']) ?>"
                                    class="w-28 text-right px-3 py-1.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="flex justify-end gap-2">
            <a href="<?= BASE_URL ?>/transfers/view/<?= $transfer['id'] ?>"
                class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-2 px-4 rounded-lg text-sm transition">
                Cancel
            </a>
            <button type="submit"
                class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded-lg text-sm transition">
                Confirm Receipt
            </button>
        </div>
    </form>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
