<?php include APP_PATH . '/views/layout/header.php'; ?>

<?php
// Quantities can be fractional (kg, metres): show up to 3 decimals, trimmed.
$fmtQty = fn($q) => rtrim(rtrim(number_format((float) $q, 3), '0'), '.') ?: '0';
$shownLabel = $selectedBranch !== null
    ? e($branches[0]['name'] ?? '')
    : (count($branches) > 1 ? 'all ' . count($branches) . ' branches' : e($branches[0]['name'] ?? 'your branch'));
$exportQuery = http_build_query(array_filter([
    'branch'   => $selectedBranch,
    'category' => $category,
    'search'   => $search,
    'status'   => $status,
    'sort_by'  => $sortBy,
], fn($v) => $v !== null && $v !== ''));
?>

<!-- Page Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">🏬 Stock by Branch</h1>
        <p class="text-gray-500 text-sm mt-1">
            Product stock at each branch — showing <strong><?= $shownLabel ?></strong>
        </p>
    </div>
    <div class="flex gap-2 no-print">
        <button onclick="window.print()"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition">
            🖨 Print Report
        </button>
        <a href="<?= BASE_URL ?>/export/inventory<?= $exportQuery ? '?' . $exportQuery : '' ?>"
            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm transition">
            📥 Export to Excel
        </a>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-4 mb-6 no-print">
    <form method="GET" action="<?= BASE_URL ?>/reports/inventory">
        <div class="grid grid-cols-1 md:grid-cols-6 gap-3">

            <!-- Branch -->
            <div>
                <label class="block text-xs text-gray-600 mb-1 font-medium">Branch</label>
                <?php if (count($viewableBranches) > 1): ?>
                    <select name="branch" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Branches</option>
                        <?php foreach ($viewableBranches as $b): ?>
                            <option value="<?= (int) $b['id'] ?>" <?= $selectedBranch === (int) $b['id'] ? 'selected' : '' ?>>
                                <?= e($b['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <div class="px-3 py-2 border rounded-lg text-sm bg-gray-50 text-gray-700">
                        <?= e($viewableBranches[0]['name'] ?? '—') ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Category -->
            <div>
                <label class="block text-xs text-gray-600 mb-1 font-medium">Category</label>
                <select name="category" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= (string) $category === (string) $cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Search -->
            <div>
                <label class="block text-xs text-gray-600 mb-1 font-medium">Product / SKU</label>
                <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search…"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <!-- Stock status -->
            <div>
                <label class="block text-xs text-gray-600 mb-1 font-medium">Stock Status</label>
                <select name="status" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All</option>
                    <option value="in_stock" <?= $status === 'in_stock' ? 'selected' : '' ?>>In Stock</option>
                    <option value="low" <?= $status === 'low' ? 'selected' : '' ?>>Low Stock</option>
                    <option value="out" <?= $status === 'out' ? 'selected' : '' ?>>Out of Stock</option>
                </select>
            </div>

            <!-- Sort -->
            <div>
                <label class="block text-xs text-gray-600 mb-1 font-medium">Sort By</label>
                <select name="sort_by" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>Name (A-Z)</option>
                    <option value="stock_desc" <?= $sortBy === 'stock_desc' ? 'selected' : '' ?>>Stock (High-Low)</option>
                    <option value="stock_asc" <?= $sortBy === 'stock_asc' ? 'selected' : '' ?>>Stock (Low-High)</option>
                    <option value="value_desc" <?= $sortBy === 'value_desc' ? 'selected' : '' ?>>Value (High-Low)</option>
                    <option value="value_asc" <?= $sortBy === 'value_asc' ? 'selected' : '' ?>>Value (Low-High)</option>
                </select>
            </div>

            <!-- Buttons -->
            <div class="flex gap-2 items-end">
                <button type="submit"
                    class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    Apply
                </button>
                <a href="<?= BASE_URL ?>/reports/inventory"
                    class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    Reset
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-gray-400 text-xs uppercase tracking-wide mb-1">Products</div>
        <div class="text-2xl font-bold text-gray-800"><?= number_format($summary['products']) ?></div>
        <div class="text-xs text-gray-500 mt-0.5">listed</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-gray-400 text-xs uppercase tracking-wide mb-1">Total Units</div>
        <div class="text-2xl font-bold text-blue-600"><?= $fmtQty($summary['units']) ?></div>
        <div class="text-xs text-gray-500 mt-0.5">across <?= $shownLabel ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-gray-400 text-xs uppercase tracking-wide mb-1">Stock Value</div>
        <div class="text-2xl font-bold text-green-600"><?= formatMoney($summary['value']) ?></div>
        <div class="text-xs text-gray-500 mt-0.5">At average cost</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-gray-400 text-xs uppercase tracking-wide mb-1">Stock Alerts</div>
        <div class="text-2xl font-bold text-red-600"><?= number_format($summary['low']) ?> low</div>
        <div class="text-xs text-gray-500 mt-0.5"><?= number_format($summary['out']) ?> out of stock</div>
    </div>
</div>

<!-- Per-branch totals -->
<?php if (count($branches) > 1): ?>
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <h2 class="text-sm font-bold text-gray-800 mb-3">Stock held at each branch</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <?php foreach ($branches as $b): $bt = $branchTotals[(int) $b['id']]; ?>
                <a href="<?= BASE_URL ?>/reports/inventory?<?= e(http_build_query(array_merge($_GET, ['branch' => $b['id']]))) ?>"
                    class="border rounded-lg p-3 hover:bg-gray-50 transition block">
                    <div class="text-sm font-medium text-gray-800"><?= e($b['name']) ?></div>
                    <div class="text-xs text-gray-500 mt-1"><?= $fmtQty($bt['units']) ?> units</div>
                    <div class="text-sm font-bold text-green-600"><?= formatMoney($bt['value']) ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Stock table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-4 py-3 border-b">
        <h3 class="font-bold text-gray-800">Stock Levels</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Product</th>
                    <?php foreach ($branches as $b): ?>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase"><?= e($b['name']) ?></th>
                    <?php endforeach; ?>
                    <?php if (count($branches) > 1): ?>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Total</th>
                    <?php endif; ?>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Avg Cost</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Stock Value</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($rows as $p): ?>
                    <tr class="hover:bg-gray-50 <?= $p['state'] === 'out' ? 'bg-red-50' : ($p['state'] === 'low' ? 'bg-yellow-50' : '') ?>">
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-800"><?= e($p['name']) ?></div>
                            <div class="text-xs text-gray-400">
                                SKU: <?= e($p['sku']) ?>
                                <?php if (!empty($p['category_name'])): ?> · <?= e($p['category_name']) ?><?php endif; ?>
                            </div>
                        </td>
                        <?php foreach ($branches as $b): $q = $p['branch_qty'][(int) $b['id']] ?? 0; ?>
                            <td class="px-4 py-3 text-right text-sm <?= $q <= 0 ? 'text-gray-300' : 'text-gray-800' ?>">
                                <?= $fmtQty($q) ?>
                            </td>
                        <?php endforeach; ?>
                        <?php if (count($branches) > 1): ?>
                            <td class="px-4 py-3 text-right text-sm font-semibold text-gray-800">
                                <?= $fmtQty($p['total']) ?> <span class="text-xs text-gray-400"><?= e($p['unit']) ?></span>
                            </td>
                        <?php endif; ?>
                        <td class="px-4 py-3 text-right text-sm text-gray-600"><?= formatMoney($p['average_cost']) ?></td>
                        <td class="px-4 py-3 text-right text-sm font-semibold text-green-600"><?= formatMoney($p['value']) ?></td>
                        <td class="px-4 py-3 text-center">
                            <?php if ($p['state'] === 'out'): ?>
                                <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-700">Out</span>
                            <?php elseif ($p['state'] === 'low'): ?>
                                <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-700">Low</span>
                            <?php else: ?>
                                <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-700">OK</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="<?= count($branches) + (count($branches) > 1 ? 1 : 0) + 4 ?>" class="px-4 py-8 text-center text-gray-400 text-sm">
                            No products match these filters.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($rows)): ?>
                <tfoot class="bg-gray-50 border-t-2">
                    <tr class="font-bold text-gray-800 text-sm">
                        <td class="px-4 py-3">Total</td>
                        <?php foreach ($branches as $b): ?>
                            <td class="px-4 py-3 text-right"><?= $fmtQty($branchTotals[(int) $b['id']]['units']) ?></td>
                        <?php endforeach; ?>
                        <?php if (count($branches) > 1): ?>
                            <td class="px-4 py-3 text-right"><?= $fmtQty($summary['units']) ?></td>
                        <?php endif; ?>
                        <td class="px-4 py-3"></td>
                        <td class="px-4 py-3 text-right text-green-600"><?= formatMoney($summary['value']) ?></td>
                        <td class="px-4 py-3"></td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
