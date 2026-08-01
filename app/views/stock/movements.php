<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-800"><?= e($pageTitle) ?></h1>
        <p class="text-gray-500 text-sm mt-1">Complete audit trail of all stock changes</p>
    </div>
    <a href="<?= BASE_URL ?>/stock"
        class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition">
        ← Back to Stock
    </a>
</div>

<?= flashMessage() ?>

<!-- Filters -->
<div class="bg-white p-4 rounded-lg shadow mb-6">
    <form method="GET" action="<?= BASE_URL ?>/stock/movements" class="flex flex-wrap gap-3">
        <input type="text" name="search"
            placeholder="Search product name..."
            value="<?= e($_GET['search'] ?? '') ?>"
            class="flex-1 min-w-48 px-4 py-2 border border-gray-300 rounded-lg
                      focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        <select name="type"
            class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            <option value="">All Types</option>
            <option value="in" <?= ($_GET['type'] ?? '') === 'in'         ? 'selected' : '' ?>>Stock In</option>
            <option value="out" <?= ($_GET['type'] ?? '') === 'out'        ? 'selected' : '' ?>>Stock Out</option>
            <option value="adjustment" <?= ($_GET['type'] ?? '') === 'adjustment' ? 'selected' : '' ?>>Adjustment</option>
        </select>
        <button type="submit"
            class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg transition">
            Filter
        </button>
        <?php if (!empty($_GET['search']) || !empty($_GET['type'])): ?>
            <a href="<?= BASE_URL ?>/stock/movements"
                class="bg-gray-500 hover:bg-gray-600 text-white px-5 py-2 rounded-lg transition">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Movements Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date / Time</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
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
                        <td colspan="8" class="px-6 py-16 text-center text-gray-400">
                            <div class="text-5xl mb-3">📋</div>
                            <p>No movements found</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($movements as $m): ?>
                        <?php
                        $typeConfig = [
                            'in'         => ['class' => 'bg-green-100 text-green-700', 'label' => '↑ In',        'qty' => 'text-green-600'],
                            'out'        => ['class' => 'bg-red-100 text-red-600',     'label' => '↓ Out',       'qty' => 'text-red-600'],
                            'adjustment' => ['class' => 'bg-purple-100 text-purple-700', 'label' => '⚙ Adjust',   'qty' => 'text-purple-600'],
                        ];
                        $tc = $typeConfig[$m['movement_type']] ?? $typeConfig['adjustment'];
                        ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                                <?= formatDate($m['created_at'], 'd M Y') ?>
                                <div class="text-xs text-gray-400"><?= formatDate($m['created_at'], 'H:i') ?></div>
                            </td>
                            <td class="px-4 py-3">
                                <a href="<?= BASE_URL ?>/stock/product/<?= $m['product_id'] ?>"
                                    class="font-medium text-blue-600 hover:underline">
                                    <?= e($m['product_name']) ?>
                                </a>
                                <div class="text-xs text-gray-400"><?= e($m['product_sku']) ?></div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= $tc['class'] ?>">
                                    <?= $tc['label'] ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center font-bold <?= $tc['qty'] ?>">
                                <?= $m['movement_type'] === 'out' ? '-' : '+' ?><?= $m['quantity'] ?>
                                <span class="text-xs font-normal text-gray-400"><?= e($m['unit']) ?></span>
                            </td>
                            <td class="px-4 py-3 text-center text-sm text-gray-600"><?= $m['previous_stock'] ?></td>
                            <td class="px-4 py-3 text-center text-sm font-semibold text-gray-800"><?= $m['new_stock'] ?></td>
                            <td class="px-4 py-3 text-sm text-gray-500 max-w-xs truncate">
                                <?= e($m['notes'] ?? '—') ?>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500">
                                <?= e($m['user_name'] ?? 'System') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="bg-gray-50 px-5 py-3 border-t flex items-center justify-between">
            <span class="text-sm text-gray-500">Page <?= $page ?> of <?= $totalPages ?></span>
            <div class="flex gap-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&type=<?= urlencode($_GET['type'] ?? '') ?>&search=<?= urlencode($_GET['search'] ?? '') ?>"
                        class="px-3 py-1.5 bg-white border rounded text-sm hover:bg-gray-50">← Prev</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&type=<?= urlencode($_GET['type'] ?? '') ?>&search=<?= urlencode($_GET['search'] ?? '') ?>"
                        class="px-3 py-1.5 bg-white border rounded text-sm hover:bg-gray-50">Next →</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>