<?php include APP_PATH . '/views/layout/header.php'; ?>

<!-- Page Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">💳 Outstanding Receivables</h1>
        <p class="text-gray-500 text-sm mt-1">
            Customers who owe you money
        </p>
    </div>
    <div class="flex gap-2">
        <button onclick="window.print()"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition">
            🖨 Print Report
        </button>
        <a href="<?= BASE_URL ?>/export/receivables?aging=<?= $aging ?>&sort_by=<?= $sortBy ?>"
            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm transition">
            📥 Export to Excel
        </a>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-4 mb-6 no-print">
    <form method="GET" action="<?= BASE_URL ?>/reports/receivables">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">

            <!-- Aging Filter -->
            <div class="md:col-span-2">
                <label class="block text-xs text-gray-600 mb-1 font-medium">Aging Period</label>
                <select name="aging" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All Ages</option>
                    <option value="current" <?= $aging === 'current' ? 'selected' : '' ?>>Current (0-30 days)</option>
                    <option value="overdue_30" <?= $aging === 'overdue_30' ? 'selected' : '' ?>>31-60 days</option>
                    <option value="overdue_60" <?= $aging === 'overdue_60' ? 'selected' : '' ?>>61-90 days</option>
                    <option value="overdue_90" <?= $aging === 'overdue_90' ? 'selected' : '' ?>>90+ days (Critical)</option>
                </select>
            </div>

            <!-- Sort By -->
            <div>
                <label class="block text-xs text-gray-600 mb-1 font-medium">Sort By</label>
                <select name="sort_by" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="amount_desc" <?= $sortBy === 'amount_desc' ? 'selected' : '' ?>>Amount (High-Low)</option>
                    <option value="amount_asc" <?= $sortBy === 'amount_asc' ? 'selected' : '' ?>>Amount (Low-High)</option>
                    <option value="days_desc" <?= $sortBy === 'days_desc' ? 'selected' : '' ?>>Days (Oldest First)</option>
                    <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>Name (A-Z)</option>
                </select>
            </div>

            <!-- Buttons -->
            <div class="flex gap-2 items-end">
                <button type="submit"
                    class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    Apply
                </button>
                <a href="<?= BASE_URL ?>/reports/receivables"
                    class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    Reset
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
    <!-- Total Customers Card -->
    <div class="bg-white rounded-lg shadow-sm p-3 border-l-4 border-blue-500">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-gray-500 text-xs uppercase tracking-wide">Total Customers</div>
                <div class="text-xl font-bold text-gray-800 mt-1"><?= number_format($summary['total_customers']) ?></div>
                <div class="text-xs text-gray-400 mt-0.5">with balances</div>
            </div>
            <div class="bg-blue-100 rounded-full p-2">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
        </div>
    </div>

    <!-- Total Owed Card -->
    <div class="bg-white rounded-lg shadow-sm p-3 border-l-4 border-red-500">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-gray-500 text-xs uppercase tracking-wide">Total Owed</div>
                <div class="text-xl font-bold text-red-600 mt-1"><?= formatMoney($summary['total_owed']) ?></div>
                <div class="text-xs text-gray-400 mt-0.5">outstanding</div>
            </div>
            <div class="bg-red-100 rounded-full p-2">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>

    <!-- Average Owed Card -->
    <div class="bg-white rounded-lg shadow-sm p-3 border-l-4 border-purple-500">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-gray-500 text-xs uppercase tracking-wide">Average Owed</div>
                <div class="text-xl font-bold text-gray-800 mt-1"><?= formatMoney($summary['avg_owed']) ?></div>
                <div class="text-xs text-gray-400 mt-0.5">per customer</div>
            </div>
            <div class="bg-purple-100 rounded-full p-2">
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
            </div>
        </div>
    </div>

    <!-- Severely Overdue Card -->
    <div class="bg-white rounded-lg shadow-sm p-3 border-l-4 border-orange-500">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-gray-500 text-xs uppercase tracking-wide">Severely Overdue</div>
                <div class="text-xl font-bold text-orange-600 mt-1"><?= number_format($summary['severely_overdue_count']) ?></div>
                <div class="text-xs text-gray-400 mt-0.5">90+ days</div>
            </div>
            <div class="bg-orange-100 rounded-full p-2">
                <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
        </div>
    </div>

    <!-- Unpaid Invoices Card -->
    <div class="bg-white rounded-lg shadow-sm p-3 border-l-4 border-indigo-500">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-gray-500 text-xs uppercase tracking-wide">Unpaid Invoices</div>
                <div class="text-xl font-bold text-indigo-600 mt-1"><?= number_format($summary['total_unpaid_invoices']) ?></div>
                <div class="text-xs text-gray-400 mt-0.5">open invoices</div>
            </div>
            <div class="bg-indigo-100 rounded-full p-2">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
        </div>
    </div>
</div>

<!-- Aging Analysis with Accurate Breakdown -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h2 class="text-lg font-bold text-gray-800 mb-4">📊 Aging Analysis (By Invoice)</h2>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="border-l-4 border-green-500 bg-green-50 p-4 rounded">
            <div class="text-xs text-gray-600 mb-1">Current (0-30 days)</div>
            <div class="text-2xl font-bold text-green-600"><?= formatMoney($agingAnalysis['current_0_30']) ?></div>
            <div class="text-xs text-gray-500 mt-1">
                <?= $agingAnalysis['current_0_30_percent'] ?>% of total
            </div>
        </div>

        <div class="border-l-4 border-yellow-500 bg-yellow-50 p-4 rounded">
            <div class="text-xs text-gray-600 mb-1">31-60 days</div>
            <div class="text-2xl font-bold text-yellow-600"><?= formatMoney($agingAnalysis['overdue_31_60']) ?></div>
            <div class="text-xs text-gray-500 mt-1">
                <?= $agingAnalysis['overdue_31_60_percent'] ?>% of total
            </div>
        </div>

        <div class="border-l-4 border-orange-500 bg-orange-50 p-4 rounded">
            <div class="text-xs text-gray-600 mb-1">61-90 days</div>
            <div class="text-2xl font-bold text-orange-600"><?= formatMoney($agingAnalysis['overdue_61_90']) ?></div>
            <div class="text-xs text-gray-500 mt-1">
                <?= $agingAnalysis['overdue_61_90_percent'] ?>% of total
            </div>
        </div>

        <div class="border-l-4 border-red-500 bg-red-50 p-4 rounded">
            <div class="text-xs text-gray-600 mb-1">90+ days (Critical)</div>
            <div class="text-2xl font-bold text-red-600"><?= formatMoney($agingAnalysis['overdue_90_plus']) ?></div>
            <div class="text-xs text-gray-500 mt-1">
                <?= $agingAnalysis['overdue_90_plus_percent'] ?>% of total
            </div>
        </div>
    </div>
</div>

<!-- Customers Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-4 py-3 border-b flex justify-between items-center">
        <h3 class="font-bold text-gray-800">Customer Details</h3>
        <div class="text-xs text-gray-500">
            <span class="inline-block w-3 h-3 bg-green-100 border border-green-300 rounded"></span> Current &nbsp;
            <span class="inline-block w-3 h-3 bg-yellow-100 border border-yellow-300 rounded"></span> Warning &nbsp;
            <span class="inline-block w-3 h-3 bg-orange-100 border border-orange-300 rounded"></span> Overdue &nbsp;
            <span class="inline-block w-3 h-3 bg-red-100 border border-red-300 rounded"></span> Critical
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full" id="receivables-table">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Customer</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Contact</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Amount Owed</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Invoices</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Oldest Debt</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Days</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Status</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Risk</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($customers as $customer): ?>
                    <tr class="hover:bg-gray-50 cursor-pointer" onclick="toggleInvoices(<?= $customer['id'] ?>)">
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-800"><?= e($customer['full_name']) ?></div>
                            <div class="text-xs text-gray-400">
                                Credit Limit: <?= formatMoney($customer['credit_limit']) ?>
                                <?php if ($customer['credit_warning'] !== 'normal'): ?>
                                    <span class="ml-1 <?= $customer['credit_warning'] === 'danger' ? 'text-red-500' : 'text-orange-500' ?>">
                                        (<?= $customer['credit_utilization'] ?>% used)
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <div><?= e($customer['phone']) ?></div>
                            <?php if (!empty($customer['email'])): ?>
                                <div class="text-xs text-gray-400"><?= e($customer['email']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right text-sm font-bold text-red-600">
                            <?= formatMoney($customer['total_owed']) ?>
                        </td>
                        <td class="px-4 py-3 text-center text-sm text-gray-700">
                            <span class="font-semibold"><?= number_format($customer['unpaid_invoices']) ?></span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <?= !empty($customer['oldest_debt_date']) ? date('d M Y', strtotime($customer['oldest_debt_date'])) : '-' ?>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="text-sm font-bold <?= $customer['days_outstanding'] > 90 ? 'text-red-600' : ($customer['days_outstanding'] > 60 ? 'text-orange-600' : ($customer['days_outstanding'] > 30 ? 'text-yellow-600' : 'text-green-600')) ?>">
                                <?= number_format($customer['days_outstanding']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-1 rounded-full text-xs font-semibold <?= $customer['status_color'] ?>">
                                <?= $customer['status'] ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-1 rounded-full text-xs font-semibold <?= $customer['risk_level']['color'] ?>">
                                <?= $customer['risk_level']['icon'] ?> <?= $customer['risk_level']['level'] ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <a href="<?= BASE_URL ?>/customers/view/<?= $customer['id'] ?>"
                                class="text-blue-600 hover:text-blue-800 text-sm">
                                View
                            </a>
                        </td>
                    </tr>

                    <!-- Invoice Details Row (Hidden by default) -->
                    <tr id="invoices-<?= $customer['id'] ?>" class="hidden bg-gray-50">
                        <td colspan="9" class="px-4 py-3">
                            <div class="ml-4">
                                <h4 class="text-sm font-semibold text-gray-700 mb-2">📄 Unpaid Invoices</h4>
                                <table class="w-full text-sm border-collapse">
                                    <thead class="bg-gray-100">
                                        <tr class="text-gray-600">
                                            <th class="px-3 py-2 text-left">Invoice #</th>
                                            <th class="px-3 py-2 text-left">Date</th>
                                            <th class="px-3 py-2 text-right">Total</th>
                                            <th class="px-3 py-2 text-right">Paid</th>
                                            <th class="px-3 py-2 text-right">Due</th>
                                            <th class="px-3 py-2 text-center">Days</th>
                                            <th class="px-3 py-2 text-center">Status</th>
                                            <th class="px-3 py-2 text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($customer['invoices'] as $invoice): ?>
                                            <tr class="border-b border-gray-200">
                                                <td class="px-3 py-2 font-mono text-xs"><?= e($invoice['invoice_no']) ?></td>
                                                <td class="px-3 py-2 text-xs"><?= date('d M Y', strtotime($invoice['date'])) ?></td>
                                                <td class="px-3 py-2 text-right text-xs"><?= formatMoney($invoice['total']) ?></td>
                                                <td class="px-3 py-2 text-right text-xs text-green-600"><?= formatMoney($invoice['paid']) ?></td>
                                                <td class="px-3 py-2 text-right text-xs font-bold text-red-600"><?= formatMoney($invoice['amount']) ?></td>
                                                <td class="px-3 py-2 text-center">
                                                    <span class="px-2 py-0.5 rounded-full text-xs 
                                                    <?= $invoice['days'] > 90 ? 'bg-red-100 text-red-700' : ($invoice['days'] > 60 ? 'bg-orange-100 text-orange-700' : ($invoice['days'] > 30 ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700')) ?>">
                                                        <?= $invoice['days'] ?> days
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2 text-center">
                                                    <span class="text-xs <?= $invoice['status'] === 'unpaid' ? 'text-red-600' : 'text-orange-600' ?>">
                                                        <?= ucfirst($invoice['status']) ?>
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2 text-center">
                                                    <a href="<?= BASE_URL ?>/sales/view/<?= $invoice['id'] ?>"
                                                        class="text-blue-500 hover:text-blue-700 text-xs">
                                                        View Invoice
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="bg-gray-100">
                                        <tr>
                                            <td colspan="4" class="px-3 py-2 text-right font-semibold">Total Due:</td>
                                            <td class="px-3 py-2 text-right font-bold text-red-600">
                                                <?= formatMoney($customer['total_owed']) ?>
                                            </td>
                                            <td colspan="3"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-gray-400 text-sm">
                            🎉 No outstanding receivables! All customers have paid.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($customers)): ?>
                <tfoot class="bg-gray-50 border-t-2 font-bold">
                    <tr>
                        <td colspan="2" class="px-4 py-3 text-right text-gray-700">TOTAL:</td>
                        <td class="px-4 py-3 text-right text-red-600"><?= formatMoney($summary['total_owed']) ?></td>
                        <td colspan="6"></td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<!-- Add JavaScript for toggling invoices -->
<script>
    function toggleInvoices(customerId) {
        const row = document.getElementById('invoices-' + customerId);
        if (row) {
            row.classList.toggle('hidden');
        }
    }

    // Add export to CSV functionality
    function exportToCSV() {
        const table = document.getElementById('receivables-table');
        const rows = table.querySelectorAll('tr');
        let csv = [];

        // Get headers from thead
        const headers = [];
        table.querySelectorAll('thead th').forEach(th => {
            headers.push(th.innerText.trim());
        });
        csv.push(headers.join(','));

        // Get data rows
        rows.forEach(row => {
            const rowData = [];
            if (row.id && row.id.startsWith('invoices-')) {
                return; // Skip invoice detail rows
            }
            row.querySelectorAll('td').forEach(cell => {
                rowData.push('"' + cell.innerText.trim().replace(/"/g, '""') + '"');
            });
            if (rowData.length) {
                csv.push(rowData.join(','));
            }
        });

        // Download CSV
        const blob = new Blob([csv.join('\n')], {
            type: 'text/csv'
        });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'receivables_report_' + new Date().toISOString().slice(0, 10) + '.csv';
        a.click();
        window.URL.revokeObjectURL(url);
    }
</script>
<?php include APP_PATH . '/views/layout/footer.php'; ?>