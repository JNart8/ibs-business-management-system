<?php include APP_PATH . '/views/layout/header.php'; ?>
<?php
/** @var array $batches */
/** @var array $summary */
/** @var array $categories */
/** @var string $status */
/** @var int $category */
/** @var int $warningDays */
$statusBadges = [
    'expired' => ['bg-red-100 text-red-700', 'Expired'],
    'soon'    => ['bg-yellow-100 text-yellow-800', 'Expiring soon'],
    'ok'      => ['bg-green-100 text-green-700', 'OK'],
    'unknown' => ['bg-gray-100 text-gray-600', 'Unknown expiry'],
];
?>

<!-- Page Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">⏳ Expiry Report</h1>
        <p class="text-sm text-gray-500">
            Batches expired or expiring within <?= $warningDays ?> days
            <?php if ($viewingScopeLabel ?? null): ?>
                — showing <strong><?= e($viewingScopeLabel) ?></strong>
            <?php endif; ?>
        </p>
    </div>
    <div class="flex gap-2">
        <button onclick="window.print()"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition">
            🖨 Print
        </button>
        <?php if (planAllows('imports_exports')): ?>
            <a href="<?= BASE_URL ?>/export/expiry?status=<?= e($status) ?>&category=<?= $category ?>"
                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm transition">
                📥 Export CSV
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-4 mb-6 no-print">
    <form method="GET" action="<?= BASE_URL ?>/reports/expiry">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
                <label class="block text-xs text-gray-600 mb-1 font-medium">Show</label>
                <select name="status" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    <?php foreach ([
                        'attention' => 'Needs attention (expired + expiring soon)',
                        'expired'   => '🔴 Expired',
                        'soon'      => '🟡 Expiring within ' . $warningDays . ' days',
                        'unknown'   => 'Unknown expiry',
                        'all'       => 'All batches',
                    ] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1 font-medium">Category</label>
                <select name="category" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    Apply Filters
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <a href="<?= BASE_URL ?>/reports/expiry?status=expired&category=<?= $category ?>" class="bg-white rounded-lg shadow p-3 hover:shadow-md transition">
        <div class="text-xs text-gray-400 uppercase tracking-wide mb-1">Expired</div>
        <div class="text-xl font-bold text-red-600"><?= formatMoney($summary['expired_value']) ?></div>
        <div class="text-xs text-gray-500 mt-0.5"><?= number_format($summary['expired_batches']) ?> batch(es) — write off or return</div>
    </a>
    <a href="<?= BASE_URL ?>/reports/expiry?status=soon&category=<?= $category ?>" class="bg-white rounded-lg shadow p-3 hover:shadow-md transition">
        <div class="text-xs text-gray-400 uppercase tracking-wide mb-1">Expiring within <?= $warningDays ?> days</div>
        <div class="text-xl font-bold text-yellow-600"><?= formatMoney($summary['soon_value']) ?></div>
        <div class="text-xs text-gray-500 mt-0.5"><?= number_format($summary['soon_batches']) ?> batch(es) — sell first or return</div>
    </a>
    <a href="<?= BASE_URL ?>/reports/expiry?status=unknown&category=<?= $category ?>" class="bg-white rounded-lg shadow p-3 hover:shadow-md transition">
        <div class="text-xs text-gray-400 uppercase tracking-wide mb-1">Unknown Expiry</div>
        <div class="text-xl font-bold text-gray-700"><?= formatMoney($summary['unknown_value']) ?></div>
        <div class="text-xs text-gray-500 mt-0.5"><?= number_format($summary['unknown_batches']) ?> batch(es) — check the shelf</div>
    </a>
    <a href="<?= BASE_URL ?>/reports/expiry?status=all&category=<?= $category ?>" class="bg-white rounded-lg shadow p-3 hover:shadow-md transition">
        <div class="text-xs text-gray-400 uppercase tracking-wide mb-1">All Tracked Stock</div>
        <div class="text-xl font-bold text-blue-600"><?= formatMoney($summary['all_value']) ?></div>
        <div class="text-xs text-gray-500 mt-0.5"><?= number_format($summary['all_batches']) ?> batch(es), at average cost</div>
    </a>
</div>

<!-- Batches -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-4 py-3 border-b bg-gray-50">
        <h3 class="font-bold text-gray-800">Batches</h3>
    </div>

    <?php if (empty($batches)): ?>
        <div class="p-10 text-center text-gray-400">
            <div class="text-3xl mb-2">✅</div>
            <p class="text-sm">No batches match this filter.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Product</th>
                        <?php if (hasMultiBranch()): ?>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Branch</th>
                        <?php endif; ?>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Batch No.</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Expiry Date</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Quantity</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Value at Cost</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($batches as $b): ?>
                        <?php [$badgeClass, $badgeLabel] = $statusBadges[$b['expiry_status']] ?? $statusBadges['unknown']; ?>
                        <tr class="<?= $b['expiry_status'] === 'expired' ? 'bg-red-50' : 'hover:bg-gray-50' ?>">
                            <td class="px-4 py-3">
                                <a href="<?= BASE_URL ?>/products/view/<?= $b['product_id'] ?>" class="font-medium text-gray-800 hover:text-blue-600"><?= e($b['name']) ?></a>
                                <div class="text-xs text-gray-400"><?= e($b['sku']) ?> • <?= e($b['category_name'] ?? 'N/A') ?></div>
                            </td>
                            <?php if (hasMultiBranch()): ?>
                                <td class="px-4 py-3 text-sm text-gray-700"><?= e($b['branch_name'] ?? '') ?></td>
                            <?php endif; ?>
                            <td class="px-4 py-3 text-sm text-gray-700"><?= $b['batch_number'] !== null ? e($b['batch_number']) : '<span class="text-gray-400">—</span>' ?></td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                <?= $b['expiry_date'] !== null ? formatDate($b['expiry_date'], 'd M Y') : '<span class="text-gray-400">Unknown</span>' ?>
                                <?php if ($b['days_to_expiry'] !== null): ?>
                                    <div class="text-xs <?= $b['days_to_expiry'] < 0 ? 'text-red-600' : 'text-gray-400' ?>">
                                        <?php $days = (int) $b['days_to_expiry']; ?>
                                        <?= $days < 0 ? abs($days) . ' day' . (abs($days) === 1 ? '' : 's') . ' ago' : ($days === 0 ? 'today' : 'in ' . $days . ' day' . ($days === 1 ? '' : 's')) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="<?= $badgeClass ?> text-xs font-semibold px-2 py-1 rounded-full"><?= $badgeLabel ?></span>
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-semibold text-gray-700"><?= formatQty($b['quantity']) ?> <?= e($b['unit']) ?></td>
                            <td class="px-4 py-3 text-right text-sm text-gray-700"><?= formatMoney($b['value']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
