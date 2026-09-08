<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="flex flex-wrap justify-between items-center mb-6 gap-3">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">🔄 Stock Transfers</h1>
        <p class="text-gray-500 text-sm mt-1">
            Move stock between branches
            <?php if (!isCompanyWide()): ?>
                — showing transfers involving your branch(es)
            <?php endif; ?>
        </p>
    </div>
    <a href="<?= BASE_URL ?>/transfers/create"
        class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg shadow transition">
        + New Transfer
    </a>
</div>

<?= flashMessage() ?>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="px-5 py-3 text-left   text-xs font-medium text-gray-500 uppercase">Transfer #</th>
                <th class="px-5 py-3 text-left   text-xs font-medium text-gray-500 uppercase">Route</th>
                <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase">Items</th>
                <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase">Qty</th>
                <th class="px-5 py-3 text-left   text-xs font-medium text-gray-500 uppercase">By</th>
                <th class="px-5 py-3 text-left   text-xs font-medium text-gray-500 uppercase">Date</th>
                <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php if (empty($transfers)): ?>
                <tr>
                    <td colspan="7" class="px-6 py-16 text-center text-gray-400">
                        <div class="text-5xl mb-3">🔄</div>
                        <p>No transfers yet</p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($transfers as $t): ?>
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-5 py-4 font-mono text-sm text-gray-800">
                            <?= e($t['transfer_number']) ?>
                            <?php
                            $statusBadge = [
                                'in_transit' => 'bg-amber-100 text-amber-700',
                                'completed'  => 'bg-green-100 text-green-700',
                                'cancelled'  => 'bg-gray-100 text-gray-500',
                            ][$t['status']] ?? 'bg-gray-100 text-gray-500';
                            $statusLabel = [
                                'in_transit' => '⏳ In Transit',
                                'completed'  => '✅ Completed',
                                'cancelled'  => '🚫 Cancelled',
                            ][$t['status']] ?? ucfirst($t['status']);
                            ?>
                            <span class="block mt-1 inline-block px-2 py-0.5 rounded-full text-xs font-semibold <?= $statusBadge ?>">
                                <?= $statusLabel ?>
                            </span>
                        </td>
                        <td class="px-5 py-4 text-sm text-gray-700">
                            <?= e($t['from_branch_name'] ?? '—') ?>
                            <span class="text-gray-400">→</span>
                            <?= e($t['to_branch_name'] ?? '—') ?>
                        </td>
                        <td class="px-5 py-4 text-center text-sm text-gray-600">
                            <?= intval($t['item_count']) ?>
                        </td>
                        <td class="px-5 py-4 text-center text-sm text-gray-600">
                            <?= formatQty($t['total_quantity']) ?>
                        </td>
                        <td class="px-5 py-4 text-sm text-gray-600">
                            <?= e($t['user_name'] ?? 'System') ?>
                        </td>
                        <td class="px-5 py-4 text-sm text-gray-500">
                            <?= formatDate($t['created_at']) ?>
                        </td>
                        <td class="px-5 py-4 text-center">
                            <a href="<?= BASE_URL ?>/transfers/view/<?= $t['id'] ?>"
                                class="p-1.5 rounded bg-blue-50 text-blue-600 hover:bg-blue-100" title="View details">
                                👁️
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
        <div class="bg-gray-50 px-5 py-3 border-t flex items-center justify-between">
            <span class="text-sm text-gray-500">Page <?= $page ?> of <?= $totalPages ?> — <?= $totalCount ?> transfers</span>
            <div class="flex gap-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="px-3 py-1.5 bg-white border rounded text-sm hover:bg-gray-50">← Prev</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="px-3 py-1.5 bg-white border rounded text-sm hover:bg-gray-50">Next →</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
