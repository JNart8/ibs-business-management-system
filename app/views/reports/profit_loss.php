<?php include APP_PATH . '/views/layout/header.php'; ?>

<!-- Page Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">💰 Profit & Loss Statement</h1>
        <p class="text-gray-500 text-sm mt-1">
            <?= formatDate($dateFrom, 'd M Y') ?> to <?= formatDate($dateTo, 'd M Y') ?>
        </p>
    </div>
    <div class="flex gap-2">
        <button onclick="window.print()"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition">
            🖨 Print Report
        </button>
        <a href="<?= BASE_URL ?>/export/profit-loss?period=<?= $period ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>"
            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm transition">
            📥 Export CSV
        </a>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-4 mb-6 no-print">
    <form method="GET" action="<?= BASE_URL ?>/reports/profit-loss">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <!-- Period Selector -->
            <div class="md:col-span-2">
                <label class="block text-xs text-gray-600 mb-1 font-medium">Period</label>
                <select name="period" onchange="this.form.submit()"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="today" <?= $period === 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="this_week" <?= $period === 'this_week' ? 'selected' : '' ?>>This Week</option>
                    <option value="last_week" <?= $period === 'last_week' ? 'selected' : '' ?>>Last Week</option>
                    <option value="this_month" <?= $period === 'this_month' ? 'selected' : '' ?>>This Month</option>
                    <option value="last_month" <?= $period === 'last_month' ? 'selected' : '' ?>>Last Month</option>
                    <option value="this_quarter" <?= $period === 'this_quarter' ? 'selected' : '' ?>>This Quarter</option>
                    <option value="this_year" <?= $period === 'this_year' ? 'selected' : '' ?>>This Year</option>
                    <option value="custom" <?= $period === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                </select>
            </div>

            <!-- Custom Date Range -->
            <?php if ($period === 'custom'): ?>
                <div>
                    <label class="block text-xs text-gray-600 mb-1 font-medium">From</label>
                    <input type="date" name="date_from" value="<?= $dateFrom ?>"
                        class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1 font-medium">To</label>
                    <input type="date" name="date_to" value="<?= $dateTo ?>"
                        class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-gray-400 text-xs uppercase tracking-wide mb-1">Total Revenue</div>
        <div class="text-2xl font-bold text-blue-600"><?= formatMoney($revenue['total_sales']) ?></div>
        <div class="text-xs text-gray-500 mt-1">
            Net: <?= formatMoney($revenue['net_sales']) ?>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-gray-400 text-xs uppercase tracking-wide mb-1">Gross Profit</div>
        <div class="text-2xl font-bold text-green-600"><?= formatMoney($grossProfit) ?></div>
        <div class="text-xs text-gray-500 mt-1">
            Margin: <?= number_format($grossMargin, 1) ?>%
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-gray-400 text-xs uppercase tracking-wide mb-1">Net Profit</div>
        <div class="text-2xl font-bold <?= $netProfit >= 0 ? 'text-green-600' : 'text-red-600' ?>">
            <?= formatMoney($netProfit) ?>
        </div>
        <div class="text-xs text-gray-500 mt-1">
            Margin: <?= number_format($netMargin, 1) ?>%
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-gray-400 text-xs uppercase tracking-wide mb-1">vs Previous Period</div>
        <div class="text-2xl font-bold <?= $profitChange >= 0 ? 'text-green-600' : 'text-red-600' ?>">
            <?= $profitChange >= 0 ? '+' : '' ?><?= number_format($profitChange, 1) ?>%
        </div>
        <div class="text-xs text-gray-500 mt-1">
            Previous: <?= formatMoney($previousProfit) ?>
        </div>
    </div>
</div>

<!-- P&L Statement -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

    <!-- Main P&L -->
    <div class="lg:col-span-2 bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b bg-gray-50">
            <h3 class="font-bold text-gray-800">Income Statement</h3>
        </div>
        <div class="p-6">

            <!-- REVENUE -->
            <div class="mb-6">
                <div class="text-sm font-bold text-gray-700 mb-3 uppercase tracking-wide">Revenue</div>
                <div class="space-y-2 pl-4">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Total Sales</span>
                        <span class="font-semibold"><?= formatMoney($revenue['total_sales']) ?></span>
                    </div>
                    <div class="flex justify-between text-sm text-red-600">
                        <span>Less: Discounts</span>
                        <span class="font-semibold">(<?= formatMoney($revenue['total_discounts']) ?>)</span>
                    </div>
                    <div class="flex justify-between pt-2 border-t font-bold">
                        <span>Net Sales</span>
                        <span class="text-blue-600"><?= formatMoney($revenue['net_sales']) ?></span>
                    </div>
                </div>
            </div>

            <!-- COGS -->
            <div class="mb-6">
                <div class="text-sm font-bold text-gray-700 mb-3 uppercase tracking-wide">Cost of Goods Sold</div>
                <div class="space-y-2 pl-4">
                    <div class="flex justify-between text-sm text-red-600">
                        <span>COGS (at avg cost)</span>
                        <span class="font-semibold">(<?= formatMoney($cogs['total_cogs']) ?>)</span>
                    </div>
                </div>
            </div>

            <!-- GROSS PROFIT -->
            <div class="mb-6 bg-green-50 -mx-6 px-6 py-4">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="text-sm font-bold text-gray-700 uppercase">Gross Profit</div>
                        <div class="text-xs text-gray-500 mt-0.5">Margin: <?= number_format($grossMargin, 1) ?>%</div>
                    </div>
                    <div class="text-2xl font-bold text-green-600">
                        <?= formatMoney($grossProfit) ?>
                    </div>
                </div>
            </div>

            <!-- OPERATING EXPENSES -->
            <div class="mb-6">
                <div class="text-sm font-bold text-gray-700 mb-3 uppercase tracking-wide">Operating Expenses</div>
                <div class="space-y-2 pl-4">
                    <div class="flex justify-between text-sm text-red-600">
                        <span>Total Expenses</span>
                        <span class="font-semibold">(<?= formatMoney($expenses['total_expenses']) ?>)</span>
                    </div>
                    <?php if ($expenses['total_expenses'] == 0): ?>
                        <div class="text-xs text-gray-400 italic">No expenses recorded for this period</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- NET PROFIT -->
            <div class="<?= $netProfit >= 0 ? 'bg-green-50' : 'bg-red-50' ?> -mx-6 px-6 py-4">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="text-sm font-bold text-gray-700 uppercase">Net Profit</div>
                        <div class="text-xs text-gray-500 mt-0.5">Margin: <?= number_format($netMargin, 1) ?>%</div>
                    </div>
                    <div class="text-2xl font-bold <?= $netProfit >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                        <?= formatMoney($netProfit) ?>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Insights Panel -->
    <div class="space-y-6">

        <!-- Key Metrics -->
        <div class="bg-white rounded-lg shadow p-4">
            <h4 class="font-bold text-gray-800 mb-3 text-sm">Key Metrics</h4>
            <div class="space-y-3">
                <div>
                    <div class="text-xs text-gray-500">Gross Margin</div>
                    <div class="flex items-center gap-2 mt-1">
                        <div class="flex-1 bg-gray-200 rounded-full h-2">
                            <div class="bg-green-500 rounded-full h-2" style="width: <?= min($grossMargin, 100) ?>%"></div>
                        </div>
                        <span class="text-sm font-bold"><?= number_format($grossMargin, 1) ?>%</span>
                    </div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">Net Margin</div>
                    <div class="flex items-center gap-2 mt-1">
                        <div class="flex-1 bg-gray-200 rounded-full h-2">
                            <div class="<?= $netMargin >= 0 ? 'bg-blue-500' : 'bg-red-500' ?> rounded-full h-2"
                                style="width: <?= min(abs($netMargin), 100) ?>%"></div>
                        </div>
                        <span class="text-sm font-bold"><?= number_format($netMargin, 1) ?>%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance vs Previous -->
        <div class="bg-white rounded-lg shadow p-4">
            <h4 class="font-bold text-gray-800 mb-3 text-sm">Period Comparison</h4>
            <div class="space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Current Profit</span>
                    <span class="font-semibold"><?= formatMoney($grossProfit) ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Previous Profit</span>
                    <span class="font-semibold"><?= formatMoney($previousProfit) ?></span>
                </div>
                <div class="pt-2 border-t">
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-600">Change</span>
                        <span class="font-bold <?= $profitChange >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                            <?= $profitChange >= 0 ? '▲' : '▼' ?> <?= abs(number_format($profitChange, 1)) ?>%
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Health Indicators -->
        <div class="bg-white rounded-lg shadow p-4">
            <h4 class="font-bold text-gray-800 mb-3 text-sm">Health Indicators</h4>
            <div class="space-y-2">
                <div class="flex items-center gap-2">
                    <?php if ($grossMargin >= 30): ?>
                        <span class="text-green-500 text-xl">✓</span>
                        <span class="text-xs text-gray-600">Strong Gross Margin</span>
                    <?php elseif ($grossMargin >= 20): ?>
                        <span class="text-yellow-500 text-xl">⚠</span>
                        <span class="text-xs text-gray-600">Acceptable Gross Margin</span>
                    <?php else: ?>
                        <span class="text-red-500 text-xl">✗</span>
                        <span class="text-xs text-gray-600">Low Gross Margin</span>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-2">
                    <?php if ($netMargin >= 15): ?>
                        <span class="text-green-500 text-xl">✓</span>
                        <span class="text-xs text-gray-600">Healthy Net Profit</span>
                    <?php elseif ($netMargin >= 5): ?>
                        <span class="text-yellow-500 text-xl">⚠</span>
                        <span class="text-xs text-gray-600">Moderate Net Profit</span>
                    <?php else: ?>
                        <span class="text-red-500 text-xl">✗</span>
                        <span class="text-xs text-gray-600">Low/Negative Net Profit</span>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-2">
                    <?php if ($profitChange >= 0): ?>
                        <span class="text-green-500 text-xl">✓</span>
                        <span class="text-xs text-gray-600">Growing Profit</span>
                    <?php else: ?>
                        <span class="text-red-500 text-xl">✗</span>
                        <span class="text-xs text-gray-600">Declining Profit</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Charts Row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

    <!-- Daily Profit Trend -->
    <div class="bg-white rounded-lg shadow p-5">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Daily Profit Trend</h3>
        <div style="height: 300px;">
            <canvas id="profitTrendChart"></canvas>
        </div>
    </div>

    <!-- Sales by Category -->
    <div class="bg-white rounded-lg shadow p-5">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Profit by Category</h3>
        <div style="height: 300px;">
            <canvas id="categoryProfitChart"></canvas>
        </div>
    </div>

</div>

<!-- Category Breakdown Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-4 py-3 border-b">
        <h3 class="font-bold text-gray-800">Sales & Profit by Category</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Category</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Sales</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Qty</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Revenue</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">COGS</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Profit</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Margin %</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($salesByCategory as $cat): ?>
                    <?php
                    $catMargin = $cat['cogs'] > 0 ? (($cat['profit'] / $cat['cogs']) * 100) : 0;
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium text-gray-800"><?= e($cat['category_name']) ?></td>
                        <td class="px-4 py-3 text-sm text-right text-gray-700"><?= $cat['sale_count'] ?></td>
                        <td class="px-4 py-3 text-sm text-right text-gray-700"><?= number_format($cat['total_quantity']) ?></td>
                        <td class="px-4 py-3 text-sm text-right text-gray-700"><?= formatMoney($cat['revenue']) ?></td>
                        <td class="px-4 py-3 text-sm text-right text-red-600"><?= formatMoney($cat['cogs']) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-bold text-green-600"><?= formatMoney($cat['profit']) ?></td>
                        <td class="px-4 py-3 text-sm text-right">
                            <span class="<?= $catMargin >= 30 ? 'text-green-600' : ($catMargin >= 15 ? 'text-yellow-600' : 'text-red-600') ?> font-semibold">
                                <?= number_format($catMargin, 1) ?>%
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($salesByCategory)): ?>
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-400 text-sm">
                            No sales data for this period
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Chart.js Script -->
<script src="<?= BASE_URL ?>/assets/js/chart.min.js"></script>
<script>
    // Profit Trend Chart
    const trendCtx = document.getElementById('profitTrendChart').getContext('2d');
    const trendData = <?= json_encode($dailyProfit) ?>;
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: trendData.map(d => d.sale_date),
            datasets: [{
                    label: 'Revenue',
                    data: trendData.map(d => parseFloat(d.revenue)),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.3,
                    fill: true
                },
                {
                    label: 'Profit',
                    data: trendData.map(d => parseFloat(d.profit)),
                    borderColor: 'rgb(34, 197, 94)',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    tension: 0.3,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: (context) => context.dataset.label + ': <?= CURRENCY_HOLDER ?> ' +
                            context.parsed.y.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: (value) => '<?= CURRENCY_HOLDER ?> ' + value.toFixed(0)
                    }
                }
            }
        }
    });

    // Category Profit Chart
    const catCtx = document.getElementById('categoryProfitChart').getContext('2d');
    const catData = <?= json_encode($salesByCategory) ?>;
    new Chart(catCtx, {
        type: 'bar',
        data: {
            labels: catData.map(c => c.category_name),
            datasets: [{
                label: 'Profit',
                data: catData.map(c => parseFloat(c.profit)),
                backgroundColor: 'rgba(34, 197, 94, 0.7)',
                borderColor: 'rgb(34, 197, 94)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: (context) => '<?= CURRENCY_HOLDER ?> ' + context.parsed.y.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: (value) => '<?= CURRENCY_HOLDER ?> ' + value.toFixed(0)
                    }
                }
            }
        }
    });
</script>

<?php include APP_PATH . '/views/layout/footer.php'; ?>