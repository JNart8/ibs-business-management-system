<?php include APP_PATH . '/views/layout/header.php'; ?>

<!-- Page Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">📊 Sales Report</h1>
        <p class="text-gray-500 text-sm mt-1">
            <?= formatDate($dateFrom, 'd M Y') ?> to <?= formatDate($dateTo, 'd M Y') ?>
        </p>
    </div>
    <div class="flex gap-2">
        <button onclick="window.print()"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition">
            🖨 Print Report
        </button>
        <a href="<?= BASE_URL ?>/export/sales-report?period=<?= $period ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>"
            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm transition">
            📥 Export to Excel
        </a>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-4 mb-6 no-print">
    <form method="GET" action="<?= BASE_URL ?>/reports/sales">
        <!-- First Row: Main Filters -->
        <div class="grid grid-cols-1 md:grid-cols-6 gap-3 mb-3">

            <!-- Period Selector -->
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

            <!-- Payment Method -->
            <div>
                <label class="block text-xs text-gray-600 mb-1 font-medium">Payment</label>
                <select name="payment" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All Methods</option>
                    <option value="cash" <?= $payMethod === 'cash' ? 'selected' : '' ?>>Cash</option>
                    <option value="mobile" <?= $payMethod === 'mobile' ? 'selected' : '' ?>>Mobile Money</option>
                    <option value="bank" <?= $payMethod === 'bank' ? 'selected' : '' ?>>Bank Transfer</option>
                    <option value="credit" <?= $payMethod === 'credit' ? 'selected' : '' ?>>Credit</option>
                </select>
            </div>

            <!-- Payment Status -->
            <div>
                <label class="block text-xs text-gray-600 mb-1 font-medium">Status</label>
                <select name="status" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All Status</option>
                    <option value="paid" <?= $payStatus === 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="partial" <?= $payStatus === 'partial' ? 'selected' : '' ?>>Partial</option>
                    <option value="unpaid" <?= $payStatus === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                </select>
            </div>

            <!-- Cashier -->
            <div>
                <label class="block text-xs text-gray-600 mb-1 font-medium">Cashier</label>
                <select name="cashier" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All Cashiers</option>
                    <?php foreach ($cashiers as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $cashier == $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Buttons -->
            <div class="flex gap-2 items-end">
                <button type="submit"
                    class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    Apply
                </button>
                <a href="<?= BASE_URL ?>/reports/sales"
                    class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    Reset
                </a>
            </div>
        </div>

        <!-- Second Row: Custom Date Range (conditional) -->
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
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <div class="text-gray-400 text-xs uppercase tracking-wide mb-1">Total Sales</div>
                <div class="text-2xl font-bold text-gray-800"><?= number_format($summary['total_sales']) ?></div>
                <div class="text-xs text-gray-500 mt-0.5">Transactions</div>
            </div>
            <div class="bg-blue-100 rounded-full p-3">
                <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <div class="text-gray-400 text-xs uppercase tracking-wide mb-1">Total Revenue</div>
                <div class="text-2xl font-bold text-green-600"><?= formatMoney($summary['total_revenue']) ?></div>
                <div class="text-xs text-gray-500 mt-0.5">
                    Discounts: <?= formatMoney($summary['total_discounts']) ?>
                </div>
            </div>
            <div class="bg-green-100 rounded-full p-3">
                <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <div class="text-gray-400 text-xs uppercase tracking-wide mb-1">Items Sold</div>
                <div class="text-2xl font-bold text-gray-800"><?= number_format($summary['total_items_sold']) ?></div>
                <div class="text-xs text-gray-500 mt-0.5">Units</div>
            </div>
            <div class="bg-purple-100 rounded-full p-3">
                <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <div class="text-gray-400 text-xs uppercase tracking-wide mb-1">Avg Order Value</div>
                <div class="text-2xl font-bold text-gray-800"><?= formatMoney($summary['avg_order_value']) ?></div>
                <div class="text-xs text-gray-500 mt-0.5">Per Sale</div>
            </div>
            <div class="bg-orange-100 rounded-full p-3">
                <svg class="w-7 h-7 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

    <!-- Sales Trend Chart -->
    <div class="bg-white rounded-lg shadow p-5">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Sales Trend</h2>
        <div style="height: 300px;">
            <canvas id="salesTrendChart"></canvas>
        </div>
    </div>

    <!-- Payment Method Breakdown -->
    <div class="bg-white rounded-lg shadow p-5">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Payment Methods</h2>
        <div style="height: 300px;">
            <canvas id="paymentMethodChart"></canvas>
        </div>
    </div>

</div>

<!-- Breakdown Tables Row -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

    <!-- Sales by Status -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-4 border-b">
            <h3 class="font-bold text-gray-800">Payment Status</h3>
        </div>
        <div class="divide-y">
            <?php foreach ($statusBreakdown as $status): ?>
                <div class="p-3 flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <?php
                        $badge = [
                            'paid' => 'bg-green-100 text-green-700',
                            'partial' => 'bg-yellow-100 text-yellow-700',
                            'unpaid' => 'bg-red-100 text-red-700'
                        ][$status['payment_status']] ?? 'bg-gray-100 text-gray-700';
                        ?>
                        <span class="px-2 py-1 rounded text-xs font-semibold <?= $badge ?>">
                            <?= ucfirst($status['payment_status']) ?>
                        </span>
                        <span class="text-sm text-gray-600">(<?= $status['sale_count'] ?>)</span>
                    </div>
                    <div class="text-sm font-bold text-gray-800">
                        <?= formatMoney($status['revenue']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Sales by Cashier -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-4 border-b">
            <h3 class="font-bold text-gray-800">Top Cashiers</h3>
        </div>
        <div class="divide-y">
            <?php foreach (array_slice($cashierBreakdown, 0, 5) as $cashier): ?>
                <div class="p-3 flex justify-between items-center">
                    <div>
                        <div class="text-sm font-medium text-gray-800"><?= e($cashier['cashier_name']) ?></div>
                        <div class="text-xs text-gray-400"><?= $cashier['sale_count'] ?> sales</div>
                    </div>
                    <div class="text-sm font-bold text-gray-800">
                        <?= formatMoney($cashier['revenue']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Collection Summary -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-4 border-b">
            <h3 class="font-bold text-gray-800">Collection Status</h3>
        </div>
        <div class="p-4 space-y-3">
            <div class="flex justify-between">
                <span class="text-sm text-gray-600">Total Sales</span>
                <span class="text-sm font-bold"><?= formatMoney($summary['total_revenue']) ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-gray-600">Collected</span>
                <span class="text-sm font-bold text-green-600"><?= formatMoney($summary['total_paid']) ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-gray-600">Outstanding</span>
                <span class="text-sm font-bold text-red-600"><?= formatMoney($summary['total_due']) ?></span>
            </div>
            <div class="pt-3 border-t">
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium">Collection Rate</span>
                    <span class="text-lg font-bold text-blue-600">
                        <?php
                        $collectionRate = $summary['total_revenue'] > 0
                            ? ($summary['total_paid'] / $summary['total_revenue']) * 100
                            : 0;
                        echo number_format($collectionRate, 1) . '%';
                        ?>
                    </span>
                </div>
                <div class="mt-2 bg-gray-200 rounded-full h-2">
                    <div class="bg-blue-600 rounded-full h-2" style="width: <?= $collectionRate ?>%"></div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Top Products Row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

    <!-- Top Products by Quantity -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-4 border-b">
            <h3 class="font-bold text-gray-800">Top Products by Quantity</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase">#</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Product</th>
                        <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 uppercase">Qty Sold</th>
                        <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 uppercase">Revenue</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($topProducts as $idx => $product): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-sm text-gray-500"><?= $idx + 1 ?></td>
                            <td class="px-4 py-2 text-sm font-medium text-gray-800"><?= e($product['product_name']) ?></td>
                            <td class="px-4 py-2 text-sm text-right font-bold text-blue-600"><?= number_format($product['total_quantity']) ?></td>
                            <td class="px-4 py-2 text-sm text-right text-gray-700"><?= formatMoney($product['total_revenue']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($topProducts)): ?>
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-400 text-sm">
                                No products sold in this period
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Products by Revenue -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-4 border-b">
            <h3 class="font-bold text-gray-800">Top Products by Revenue</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase">#</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Product</th>
                        <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 uppercase">Revenue</th>
                        <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 uppercase">Qty</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($topRevenue as $idx => $product): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-sm text-gray-500"><?= $idx + 1 ?></td>
                            <td class="px-4 py-2 text-sm font-medium text-gray-800"><?= e($product['product_name']) ?></td>
                            <td class="px-4 py-2 text-sm text-right font-bold text-green-600"><?= formatMoney($product['total_revenue']) ?></td>
                            <td class="px-4 py-2 text-sm text-right text-gray-700"><?= number_format($product['total_quantity']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($topRevenue)): ?>
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-400 text-sm">
                                No products sold in this period
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Top Customers -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="p-4 border-b">
        <h3 class="font-bold text-gray-800">Top Customers</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase">#</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Customer</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Phone</th>
                    <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 uppercase">Purchases</th>
                    <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 uppercase">Total Spent</th>
                    <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 uppercase">Avg Order</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($topCustomers as $idx => $customer): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 text-sm text-gray-500"><?= $idx + 1 ?></td>
                        <td class="px-4 py-2 text-sm font-medium text-gray-800"><?= e($customer['full_name']) ?></td>
                        <td class="px-4 py-2 text-sm text-gray-600"><?= e($customer['phone']) ?></td>
                        <td class="px-4 py-2 text-sm text-right text-gray-700"><?= $customer['purchase_count'] ?></td>
                        <td class="px-4 py-2 text-sm text-right font-bold text-green-600"><?= formatMoney($customer['total_spent']) ?></td>
                        <td class="px-4 py-2 text-sm text-right text-gray-700"><?= formatMoney($customer['avg_order_value']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($topCustomers)): ?>
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">
                            No customer data available for this period
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Sales Detail -->
<div class="bg-white rounded-lg shadow">
    <div class="p-4 border-b">
        <h3 class="font-bold text-gray-800">Recent Sales (Last 100)</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Date</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Receipt #</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Customer</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Cashier</th>
                    <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 uppercase">Amount</th>
                    <th class="px-4 py-2 text-center text-xs font-semibold text-gray-600 uppercase">Payment</th>
                    <th class="px-4 py-2 text-center text-xs font-semibold text-gray-600 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($recentSales as $sale): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 text-sm text-gray-600 whitespace-nowrap">
                            <?= formatDate($sale['sale_date'], 'd M Y H:i') ?>
                        </td>
                        <td class="px-4 py-2 text-sm">
                            <a href="<?= BASE_URL ?>/sales/view/<?= $sale['id'] ?>"
                                class="text-blue-600 hover:underline font-mono">
                                <?= e($sale['sale_number']) ?>
                            </a>
                        </td>
                        <td class="px-4 py-2 text-sm font-medium text-gray-800">
                            <?= e($sale['customer_name'] ?? 'Walk-in') ?>
                            <?php if ($sale['is_walkin']): ?>
                                <span class="text-xs text-gray-400">(Walk-in)</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-2 text-sm text-gray-600"><?= e($sale['cashier_name'] ?? 'System') ?></td>
                        <td class="px-4 py-2 text-sm text-right font-bold text-gray-800">
                            <?= formatMoney($sale['total_amount']) ?>
                        </td>
                        <td class="px-4 py-2 text-sm text-center">
                            <span class="text-xs px-2 py-1 rounded bg-gray-100 text-gray-700">
                                <?= ucfirst($sale['payment_method']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-2 text-sm text-center">
                            <?php
                            $statusBadge = [
                                'paid' => 'bg-green-100 text-green-700',
                                'partial' => 'bg-yellow-100 text-yellow-700',
                                'unpaid' => 'bg-red-100 text-red-700'
                            ][$sale['payment_status']] ?? 'bg-gray-100 text-gray-700';
                            ?>
                            <span class="text-xs px-2 py-1 rounded font-semibold <?= $statusBadge ?>">
                                <?= ucfirst($sale['payment_status']) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($recentSales)): ?>
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-400 text-sm">
                            No sales found for this period
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
    // Toggle custom date fields
    function toggleCustomDates(period) {
        const customDates = document.getElementById('custom-dates');
        customDates.style.display = period === 'custom' ? 'block' : 'none';
    }

    // Sales Trend Chart
    const trendCtx = document.getElementById('salesTrendChart').getContext('2d');
    const trendData = <?= json_encode($dailyTrend) ?>;
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

    // Payment Method Chart
    const paymentCtx = document.getElementById('paymentMethodChart').getContext('2d');
    const paymentData = <?= json_encode($paymentBreakdown) ?>;
    new Chart(paymentCtx, {
        type: 'doughnut',
        data: {
            labels: paymentData.map(d => d.payment_method.charAt(0).toUpperCase() + d.payment_method.slice(1)),
            datasets: [{
                data: paymentData.map(d => parseFloat(d.revenue)),
                backgroundColor: [
                    'rgb(34, 197, 94)', // green
                    'rgb(59, 130, 246)', // blue
                    'rgb(168, 85, 247)', // purple
                    'rgb(251, 146, 60)' // orange
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: (context) => context.label + ': <?= CURRENCY_HOLDER ?> ' + context.parsed.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')
                    }
                }
            }
        }
    });
</script>

<?php include APP_PATH . '/views/layout/footer.php'; ?>