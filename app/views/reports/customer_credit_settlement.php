<?php include APP_PATH . '/views/layout/header.php'; ?>

<!-- Page Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">🔁 Customer Credit Settlement</h1>
        <p class="text-gray-500 text-sm mt-1">
            <?= formatDate($dateFrom, 'd M Y') ?> to <?= formatDate($dateTo, 'd M Y') ?> —
            deposits issued vs. credit redeemed, by branch
        </p>
    </div>
    <div class="flex gap-2">
        <button onclick="window.print()"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition">
            🖨 Print Report
        </button>
        <a href="<?= BASE_URL ?>/export/customer-credit-settlement?period=<?= $period ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>"
            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm transition">
            📥 Export to Excel
        </a>
    </div>
</div>

<?php if ($isPartialView ?? false): ?>
<div class="bg-amber-50 border border-amber-300 text-amber-800 text-sm rounded-lg p-4 mb-6">
    ⚠️ <strong>Partial view — showing <?= e(activeBranchName()) ?> only.</strong>
    This report is inherently about the imbalance between branches, so a branch-scoped view can
    only show your own branch's row(s) — it can't tell you which other branch is on the other
    side of your net position. Switch to a company-wide user to see the full picture.
</div>
<?php endif; ?>

<div class="bg-blue-50 border border-blue-200 text-blue-800 text-sm rounded-lg p-4 mb-6">
    ℹ️ This report shows a <strong>net position</strong> per branch, not a trace of which
    specific deposit funded which specific sale — customer balances are pooled, shared
    company-wide money, not earmarked cash. A positive net means the branch has issued more
    store credit than has been redeemed there (other branches owe it); negative means the
    reverse.
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-4 mb-6 no-print">
    <form method="GET" action="<?= BASE_URL ?>/reports/customer-credit">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-3">
            <div class="md:col-span-2">
                <label class="block text-xs text-gray-600 mb-1 font-medium">Period</label>
                <select name="period"
                    onchange="toggleCustomDates(this.value)"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="today" <?= $period === 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="yesterday" <?= $period === 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
                    <option value="this_week" <?= $period === 'this_week' ? 'selected' : '' ?>>This Week</option>
                    <option value="last_week" <?= $period === 'last_week' ? 'selected' : '' ?>>Last Week</option>
                    <option value="this_month" <?= $period === 'this_month' ? 'selected' : '' ?>>This Month</option>
                    <option value="last_month" <?= $period === 'last_month' ? 'selected' : '' ?>>Last Month</option>
                    <option value="this_quarter" <?= $period === 'this_quarter' ? 'selected' : '' ?>>This Quarter</option>
                    <option value="this_year" <?= $period === 'this_year' ? 'selected' : '' ?>>This Year</option>
                    <option value="last_year" <?= $period === 'last_year' ? 'selected' : '' ?>>Last Year</option>
                    <option value="custom" <?= $period === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                </select>
            </div>

            <div class="flex gap-2 items-end md:col-span-2">
                <button type="submit"
                    class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    Apply
                </button>
                <a href="<?= BASE_URL ?>/reports/customer-credit"
                    class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    Reset
                </a>
            </div>
        </div>

        <div id="custom-dates" style="display: <?= $period === 'custom' ? 'block' : 'none' ?>;" class="grid grid-cols-1 md:grid-cols-4 gap-3 pt-3 border-t">
            <div class="md:col-span-2">
                <label class="block text-xs text-gray-600 mb-1 font-medium">From Date</label>
                <input type="date" name="date_from" value="<?= e($_GET['date_from'] ?? $dateFrom) ?>"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs text-gray-600 mb-1 font-medium">To Date</label>
                <input type="date" name="date_to" value="<?= e($_GET['date_to'] ?? $dateTo) ?>"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
        </div>
    </form>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-gray-400 text-xs uppercase tracking-wide mb-1">Total Deposits Issued</div>
        <div class="text-2xl font-bold text-green-600"><?= formatMoney($summary['total_issued']) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-gray-400 text-xs uppercase tracking-wide mb-1">Total Credit Redeemed</div>
        <div class="text-2xl font-bold text-blue-600"><?= formatMoney($summary['total_redeemed']) ?></div>
    </div>
</div>

<!-- Branch Net Position Table -->
<div class="bg-white rounded-lg shadow overflow-hidden mb-6">
    <div class="px-4 py-3 border-b">
        <h3 class="font-bold text-gray-800">Net Position by Branch</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Branch</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Deposits Issued</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Credit Redeemed</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Net Position</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($rows as $row): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium text-gray-800"><?= e($row['branch_name']) ?></td>
                        <td class="px-4 py-3 text-right text-sm text-gray-700"><?= formatMoney($row['issued']) ?></td>
                        <td class="px-4 py-3 text-right text-sm text-gray-700"><?= formatMoney($row['redeemed']) ?></td>
                        <td class="px-4 py-3 text-right text-sm font-bold <?= $row['net'] > 0.01 ? 'text-green-600' : ($row['net'] < -0.01 ? 'text-red-600' : 'text-gray-500') ?>">
                            <?= ($row['net'] > 0.01 ? '+' : '') . formatMoney($row['net']) ?>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <?php if (abs($row['net']) > 0.01): ?>
                                <a href="<?= BASE_URL ?>/financial-accounts/transfer?settlement_type=customer_credit_balancing&note=<?= urlencode('Customer-credit settlement for ' . $row['branch_name']) ?>"
                                    class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                    Record Settlement →
                                </a>
                            <?php else: ?>
                                <span class="text-gray-300 text-sm">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-400 text-sm">
                            No customer deposits or credit-funded sales in this period.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function toggleCustomDates(period) {
        document.getElementById('custom-dates').style.display = period === 'custom' ? 'grid' : 'none';
    }
</script>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
