<?php include APP_PATH . '/views/layout/header.php'; ?>

<!-- Page Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">📊 Business Dashboard</h1>
        <p class="text-gray-500 text-sm mt-1">Real-time insights • <?= date('l, F j, Y') ?></p>
    </div>
    <div class="flex gap-2">
        <a href="<?= BASE_URL ?>/reports"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
            📊 View Reports
        </a>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     CRITICAL ALERTS (IF ANY)
     ══════════════════════════════════════════════════════════════════════════════ -->

<?php
$totalAlerts = $alerts['out_of_stock'] + $alerts['low_stock'] + $alerts['overdue_receivables'] + $alerts['overdue_payables'];
if ($totalAlerts > 0):
?>
    <div class="bg-red-50 border-l-4 border-red-500 rounded-lg p-4 mb-6">
        <div class="flex items-start gap-3">
            <span class="text-2xl">🚨</span>
            <div class="flex-1">
                <h3 class="font-bold text-red-800 mb-2"><?= $totalAlerts ?> Critical Alert(s) Need Attention</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-2">
                    <?php if ($alerts['out_of_stock'] > 0): ?>
                        <a href="<?= BASE_URL ?>/reports/low-stock?severity=critical" class="text-sm bg-white rounded px-3 py-2 hover:shadow transition">
                            <span class="font-semibold text-red-600"><?= $alerts['out_of_stock'] ?></span> Out of Stock
                        </a>
                    <?php endif; ?>
                    <?php if ($alerts['low_stock'] > 0): ?>
                        <a href="<?= BASE_URL ?>/reports/low-stock" class="text-sm bg-white rounded px-3 py-2 hover:shadow transition">
                            <span class="font-semibold text-yellow-600"><?= $alerts['low_stock'] ?></span> Low Stock
                        </a>
                    <?php endif; ?>
                    <?php if ($alerts['overdue_receivables'] > 0): ?>
                        <a href="<?= BASE_URL ?>/reports/receivables?aging=overdue_90" class="text-sm bg-white rounded px-3 py-2 hover:shadow transition">
                            <span class="font-semibold text-orange-600"><?= $alerts['overdue_receivables'] ?></span> Overdue Receivables
                        </a>
                    <?php endif; ?>
                    <?php if ($alerts['overdue_payables'] > 0): ?>
                        <a href="<?= BASE_URL ?>/reports/payables" class="text-sm bg-white rounded px-3 py-2 hover:shadow transition">
                            <span class="font-semibold text-purple-600"><?= $alerts['overdue_payables'] ?></span> Overdue Payables
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════════════════════════
     TODAY'S PERFORMANCE
     ══════════════════════════════════════════════════════════════════════════════ -->

<div class="mb-6">
    <h2 class="text-lg font-bold text-gray-700 mb-3">Today's Performance</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

        <!-- Today's Revenue -->
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow-sm p-4 text-white">
            <div class="text-xs font-medium opacity-90 mb-1">Revenue</div>
            <div class="text-2xl font-bold"><?= formatMoney($todayMetrics['revenue']) ?></div>
            <div class="text-xs opacity-75 mt-1"><?= $todayMetrics['transactions'] ?> transactions</div>
        </div>

        <!-- Today's Profit -->
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-sm p-4 text-white">
            <div class="text-xs font-medium opacity-90 mb-1">Profit</div>
            <div class="text-2xl font-bold"><?= formatMoney($todayMetrics['profit']) ?></div>
            <div class="text-xs opacity-75 mt-1"><?= number_format($todayMetrics['profit_margin'], 1) ?>% margin</div>
        </div>

        <!-- Avg Transaction -->
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg shadow-sm p-4 text-white">
            <div class="text-xs font-medium opacity-90 mb-1">Avg Transaction</div>
            <div class="text-2xl font-bold"><?= formatMoney($todayMetrics['avg_transaction']) ?></div>
            <div class="text-xs opacity-75 mt-1">Per sale</div>
        </div>

        <!-- This Month Summary -->
        <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-lg shadow-sm p-4 text-white">
            <div class="text-xs font-medium opacity-90 mb-1">This Month</div>
            <div class="text-2xl font-bold"><?= formatMoney($monthSummary['revenue']) ?></div>
            <div class="text-xs opacity-75 mt-1"><?= formatMoney($monthSummary['profit']) ?> profit</div>
        </div>

    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     FINANCIAL HEALTH
     ══════════════════════════════════════════════════════════════════════════════ -->

<div class="mb-6">
    <h2 class="text-lg font-bold text-gray-700 mb-3">Financial Health</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

        <!-- Stock Value -->
        <a href="<?= BASE_URL ?>/reports/stock-valuation"
            class="bg-white rounded-lg shadow-sm p-4 border-l-4 border-blue-500 hover:shadow-md transition block">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs text-gray-500 mb-1">Stock Value</div>
                    <div class="text-xl font-bold text-gray-800"><?= formatMoney($financialHealth['stock_value']) ?></div>
                    <div class="text-xs text-blue-600 mt-1">View Report →</div>
                </div>
                <div class="text-3xl">📦</div>
            </div>
        </a>

        <!-- Receivables -->
        <a href="<?= BASE_URL ?>/reports/receivables"
            class="bg-white rounded-lg shadow-sm p-4 border-l-4 border-orange-500 hover:shadow-md transition block">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs text-gray-500 mb-1">Receivables</div>
                    <div class="text-xl font-bold text-orange-600"><?= formatMoney($financialHealth['receivables']) ?></div>
                    <div class="text-xs text-orange-600 mt-1">Customers owe us →</div>
                </div>
                <div class="text-3xl">💳</div>
            </div>
        </a>

        <!-- Payables -->
        <a href="<?= BASE_URL ?>/reports/payables"
            class="bg-white rounded-lg shadow-sm p-4 border-l-4 border-red-500 hover:shadow-md transition block">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs text-gray-500 mb-1">Payables</div>
                    <div class="text-xl font-bold text-red-600"><?= formatMoney($financialHealth['payables']) ?></div>
                    <div class="text-xs text-red-600 mt-1">We owe suppliers →</div>
                </div>
                <div class="text-3xl">🏦</div>
            </div>
        </a>

        <!-- Customer Deposits -->
        <div class="bg-white rounded-lg shadow-sm p-4 border-l-4 border-green-500 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs text-gray-500 mb-1">Deposits Held</div>
                    <div class="text-xl font-bold text-green-600"><?= formatMoney($financialHealth['customer_deposits']) ?></div>
                    <div class="text-xs text-gray-500 mt-1">Customer prepayments</div>
                </div>
                <div class="text-3xl">💰</div>
            </div>
        </div>

    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     CHARTS ROW
     ══════════════════════════════════════════════════════════════════════════════ -->

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

    <!-- Revenue & Profit Trend (30 Days) -->
    <div class="bg-white rounded-lg shadow-sm p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-800">Revenue & Profit Trend</h3>
            <span class="text-xs text-gray-500">Last 30 days</span>
        </div>
        <canvas id="profitTrendChart" height="250"></canvas>
    </div>

    <!-- Category Performance -->
    <div class="bg-white rounded-lg shadow-sm p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-800">Category Performance</h3>
            <span class="text-xs text-gray-500">By profit</span>
        </div>
        <?php if (!empty($categoryPerformance)): ?>
            <canvas id="categoryChart" height="250"></canvas>
        <?php else: ?>
            <div class="text-center py-12 text-gray-400">
                <div class="text-4xl mb-2">📊</div>
                <p class="text-sm">No sales data yet</p>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     TOP PRODUCTS & QUICK REPORTS
     ══════════════════════════════════════════════════════════════════════════════ -->

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

    <!-- Top Products by Profit -->
    <div class="bg-white rounded-lg shadow-sm p-5">
        <h3 class="text-lg font-bold text-gray-800 mb-4">🏆 Top Products</h3>
        <?php if (!empty($topProducts)): ?>
            <div class="space-y-3">
                <?php foreach ($topProducts as $idx => $product): ?>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 flex-1">
                            <span class="text-lg font-bold text-gray-400">#<?= $idx + 1 ?></span>
                            <div class="flex-1">
                                <div class="text-sm font-medium text-gray-800"><?= e($product['name']) ?></div>
                                <div class="text-xs text-gray-400"><?= number_format($product['total_qty']) ?> sold</div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-bold text-green-600"><?= formatMoney($product['profit']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <a href="<?= BASE_URL ?>/reports/top-selling" class="block text-center text-xs text-blue-600 hover:underline mt-4">
                View Full Report →
            </a>
        <?php else: ?>
            <div class="text-center py-8 text-gray-400">
                <div class="text-3xl mb-2">📦</div>
                <p class="text-sm">No sales data</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Quick Reports Access -->
    <div class="bg-white rounded-lg shadow-sm p-5">
        <h3 class="text-lg font-bold text-gray-800 mb-4">📊 Quick Reports</h3>
        <div class="space-y-2">
            <a href="<?= BASE_URL ?>/reports/profit-loss"
                class="block px-3 py-2 bg-gray-50 hover:bg-gray-100 rounded text-sm transition">
                💰 Profit & Loss Statement
            </a>
            <a href="<?= BASE_URL ?>/reports/top-selling"
                class="block px-3 py-2 bg-gray-50 hover:bg-gray-100 rounded text-sm transition">
                ⭐ Top Selling Products
            </a>
            <a href="<?= BASE_URL ?>/reports/low-stock"
                class="block px-3 py-2 bg-gray-50 hover:bg-gray-100 rounded text-sm transition">
                ⚠️ Low Stock Alert
            </a>
            <a href="<?= BASE_URL ?>/reports/dead-stock"
                class="block px-3 py-2 bg-gray-50 hover:bg-gray-100 rounded text-sm transition">
                💀 Dead Stock
            </a>
            <a href="<?= BASE_URL ?>/reports/profit-margin"
                class="block px-3 py-2 bg-gray-50 hover:bg-gray-100 rounded text-sm transition">
                📈 Profit Margins
            </a>
            <a href="<?= BASE_URL ?>/reports"
                class="block px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm font-medium text-center transition mt-3">
                View All Reports →
            </a>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-lg shadow-sm p-5">
        <h3 class="text-lg font-bold text-gray-800 mb-4">⚡ Quick Actions</h3>
        <div class="space-y-2">
            <a href="<?= BASE_URL ?>/pos"
                class="block px-3 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded font-medium text-sm text-center transition">
                🛒 New Sale
            </a>
            <?php if (in_array(currentUser()['role'] ?? '', ['admin', 'staff'])): ?>
                <a href="<?= BASE_URL ?>/purchases/create"
                    class="block px-3 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded font-medium text-sm text-center transition">
                    🛍️ New Purchase
                </a>
                <a href="<?= BASE_URL ?>/stock/in"
                    class="block px-3 py-2.5 bg-orange-600 hover:bg-orange-700 text-white rounded font-medium text-sm text-center transition">
                    📥 Stock In
                </a>
                <a href="<?= BASE_URL ?>/products/create"
                    class="block px-3 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded font-medium text-sm text-center transition">
                    + Add Product
                </a>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     RECENT ACTIVITY
     ══════════════════════════════════════════════════════════════════════════════ -->

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

    <!-- Recent Sales -->
    <div class="bg-white rounded-lg shadow-sm p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-800">💰 Recent Sales</h3>
            <a href="<?= BASE_URL ?>/sales" class="text-xs text-blue-600 hover:underline">View All →</a>
        </div>
        <?php if (!empty($recentSales)): ?>
            <div class="space-y-2">
                <?php foreach ($recentSales as $sale): ?>
                    <div class="flex items-center justify-between py-2 border-b last:border-0">
                        <div class="flex-1">
                            <div class="text-sm font-medium text-gray-800">
                                <?= e($sale['customer_name'] ?? 'Walk-in') ?>
                            </div>
                            <div class="text-xs text-gray-400">
                                #<?= e($sale['sale_number']) ?> • <?= date('g:i A', strtotime($sale['created_at'])) ?>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-bold text-green-600"><?= formatMoney($sale['total_amount']) ?></div>
                            <?php
                            $isVoided = strpos($sale['notes'] ?? '', '[VOIDED]') !== false;
                            $statusText = $isVoided ? 'Voided' : ucfirst($sale['payment_status']);
                            $badgeClass = $isVoided ? 'bg-gray-100 text-gray-600 border border-gray-200' : (
                                $sale['payment_status'] === 'paid' ? 'bg-green-100 text-green-700' : ($sale['payment_status'] === 'partial' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700')
                            );
                            ?>
                            <span class="text-xs px-2 py-0.5 rounded-full <?= $badgeClass ?>">
                                <?= $statusText ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-8 text-gray-400">
                <div class="text-3xl mb-2">🛒</div>
                <p class="text-sm">No sales yet</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Purchases -->
    <div class="bg-white rounded-lg shadow-sm p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-800">🛍️ Recent Purchases</h3>
            <a href="<?= BASE_URL ?>/purchases" class="text-xs text-blue-600 hover:underline">View All →</a>
        </div>
        <?php if (!empty($recentPurchases)): ?>
            <div class="space-y-2">
                <?php foreach ($recentPurchases as $purchase): ?>
                    <div class="flex items-center justify-between py-2 border-b last:border-0">
                        <div class="flex-1">
                            <div class="text-sm font-medium text-gray-800">
                                <?= e($purchase['supplier_name'] ?? 'Unknown Supplier') ?>
                            </div>
                            <div class="text-xs text-gray-400">
                                #<?= e($purchase['purchase_number']) ?> • <?= date('g:i A', strtotime($purchase['created_at'])) ?>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-bold text-blue-600"><?= formatMoney($purchase['total_amount']) ?></div>
                            <span class="text-xs px-2 py-0.5 rounded-full 
                                <?= $purchase['payment_status'] === 'paid' ? 'bg-green-100 text-green-700' : ($purchase['payment_status'] === 'partial' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') ?>">
                                <?= ucfirst($purchase['payment_status']) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-8 text-gray-400">
                <div class="text-3xl mb-2">🛍️</div>
                <p class="text-sm">No purchases yet</p>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     LOW STOCK ALERT
     ══════════════════════════════════════════════════════════════════════════════ -->

<?php if (!empty($lowStockProducts)): ?>
    <div class="bg-yellow-50 border-l-4 border-yellow-400 rounded-lg shadow-sm p-5">
        <div class="flex items-start gap-3 mb-4">
            <div class="text-2xl">⚠️</div>
            <div class="flex-1">
                <h3 class="text-lg font-bold text-yellow-800">Low Stock Alert</h3>
                <p class="text-sm text-yellow-700"><?= count($lowStockProducts) ?> product(s) need restocking</p>
            </div>
            <a href="<?= BASE_URL ?>/reports/low-stock"
                class="text-sm text-yellow-800 hover:underline font-medium">
                View Full Report →
            </a>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
            <?php foreach ($lowStockProducts as $product): ?>
                <div class="flex items-center justify-between bg-white rounded px-3 py-2">
                    <div class="flex-1">
                        <span class="text-sm font-medium text-gray-800"><?= e($product['name']) ?></span>
                        <span class="text-xs text-gray-400 ml-2">(<?= e($product['sku']) ?>)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs">
                            <span class="<?= $product['current_stock'] == 0 ? 'text-red-600 font-bold' : 'text-yellow-600 font-semibold' ?>">
                                <?= $product['current_stock'] ?>
                            </span>
                            / <?= $product['reorder_level'] ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════════════════════════
     CHART.JS INITIALIZATION
     ══════════════════════════════════════════════════════════════════════════════ -->

<script src="<?= BASE_URL ?>/assets/js/chart.min.js"></script>
<script>
    Chart.defaults.font.family = "'DM Sans', sans-serif";
    Chart.defaults.color = '#6B7280';

    // ── Profit Trend Chart (30 Days) ──
    const profitTrendCtx = document.getElementById('profitTrendChart');
    new Chart(profitTrendCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($trend, 'date')) ?>,
            datasets: [{
                    label: 'Revenue',
                    data: <?= json_encode(array_column($trend, 'revenue')) ?>,
                    borderColor: '#10B981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                },
                {
                    label: 'Profit',
                    data: <?= json_encode(array_column($trend, 'profit')) ?>,
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true
                    }
                },
                tooltip: {
                    callbacks: {
                        label: (ctx) => ctx.dataset.label + ': <?= CURRENCY_HOLDER ?> ' + ctx.parsed.y.toFixed(2)
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: (val) => '<?= CURRENCY_HOLDER ?> ' + val
                    }
                }
            }
        }
    });

    // ── Category Performance Chart ──
    <?php if (!empty($categoryPerformance)): ?>
        const categoryCtx = document.getElementById('categoryChart');
        new Chart(categoryCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($categoryPerformance, 'category')) ?>,
                datasets: [{
                    label: 'Profit',
                    data: <?= json_encode(array_column($categoryPerformance, 'profit')) ?>,
                    backgroundColor: '#3B82F6',
                    borderRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => '<?= CURRENCY_HOLDER ?> ' + ctx.parsed.x.toFixed(2)
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true
                    }
                }
            }
        });
    <?php endif; ?>
</script>

<?php include APP_PATH . '/views/layout/footer.php'; ?>