<?php include APP_PATH . '/views/layout/header.php'; ?>

<!-- Page Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">🏦 Outstanding Payables</h1>
        <p class="text-gray-500 text-sm mt-1">
            Suppliers we owe money to
        </p>
    </div>
    <div class="flex gap-2">
        <button onclick="window.print()"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition">
            🖨 Print Report
        </button>
        <a href="<?= BASE_URL ?>/export/payables?sort_by=<?= $sortBy ?>"
            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm transition">
            📥 Export to Excel
        </a>
    </div>
</div>

<?php if ($isPartialView ?? false): ?>
<div class="bg-amber-50 border border-amber-300 text-amber-800 text-sm rounded-lg p-4 mb-6">
    ⚠️ <strong>Partial view — showing <?= e(activeBranchName()) ?> only.</strong>
    A supplier's total balance can include purchases from other branches too, since supplier
    accounts are shared company-wide. The totals below reflect only what was purchased at
    your branch(es) — not each supplier's full outstanding balance.
</div>
<?php endif; ?>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-4 mb-6 no-print">
    <form method="GET" action="<?= BASE_URL ?>/reports/payables">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">

            <!-- Sort By -->
            <div class="md:col-span-2">
                <label class="block text-xs text-gray-600 mb-1 font-medium">Sort By</label>
                <select name="sort_by" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="amount_desc" <?= $sortBy === 'amount_desc' ? 'selected' : '' ?>>Amount (High-Low)</option>
                    <option value="amount_asc" <?= $sortBy === 'amount_asc' ? 'selected' : '' ?>>Amount (Low-High)</option>
                    <option value="days_desc" <?= $sortBy === 'days_desc' ? 'selected' : '' ?>>Days (Oldest First)</option>
                    <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>Supplier Name (A-Z)</option>
                </select>
            </div>

            <!-- Buttons -->
            <div class="flex gap-2 items-end">
                <button type="submit"
                    class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    Apply
                </button>
                <a href="<?= BASE_URL ?>/reports/payables"
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
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <div class="text-gray-400 text-xs uppercase tracking-wide mb-1">Total Suppliers</div>
                <div class="text-2xl font-bold text-gray-800"><?= number_format($summary['total_suppliers']) ?></div>
                <div class="text-xs text-gray-500 mt-0.5">We owe money to</div>
            </div>
            <div class="bg-blue-100 rounded-full p-3">
                <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <div class="text-gray-400 text-xs uppercase tracking-wide mb-1">Total Payables</div>
                <div class="text-2xl font-bold text-red-600"><?= formatMoney($summary['total_owed']) ?></div>
                <div class="text-xs text-gray-500 mt-0.5">Amount we owe</div>
            </div>
            <div class="bg-red-100 rounded-full p-3">
                <svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <div class="text-gray-400 text-xs uppercase tracking-wide mb-1">Average Payable</div>
                <div class="text-2xl font-bold text-gray-800"><?= formatMoney($summary['avg_owed']) ?></div>
                <div class="text-xs text-gray-500 mt-0.5">Per supplier</div>
            </div>
            <div class="bg-purple-100 rounded-full p-3">
                <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <div class="text-gray-400 text-xs uppercase tracking-wide mb-1">Overdue</div>
                <div class="text-2xl font-bold text-orange-600"><?= number_format($summary['overdue_count']) ?></div>
                <div class="text-xs text-gray-500 mt-0.5">30+ days</div>
            </div>
            <div class="bg-orange-100 rounded-full p-3">
                <svg class="w-7 h-7 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>
</div>

<!-- Suppliers Table -->
<div class="bg-white rounded-lg shadow overflow-hidden mb-6">
    <div class="px-4 py-3 border-b">
        <h3 class="font-bold text-gray-800">Supplier Payables</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Supplier</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Contact</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Amount Owed</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Invoices</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Oldest Debt</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Days</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Status</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($suppliers as $supplier): ?>
                    <?php
                    $days = $supplier['days_outstanding'] ?? 0;
                    $statusColor = 'bg-green-100 text-green-700';
                    $statusText = 'Current';
                    if ($days > 60) {
                        $statusColor = 'bg-red-100 text-red-700';
                        $statusText = 'Urgent';
                    } elseif ($days > 30) {
                        $statusColor = 'bg-orange-100 text-orange-700';
                        $statusText = 'Due Soon';
                    }
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-800"><?= e($supplier['company_name']) ?></div>
                            <?php if (!empty($supplier['contact_name'])): ?>
                                <div class="text-xs text-gray-400"><?= e($supplier['contact_name']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <div><?= e($supplier['phone']) ?></div>
                            <?php if (!empty($supplier['email'])): ?>
                                <div class="text-xs text-gray-400"><?= e($supplier['email']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right text-sm font-bold text-red-600">
                            <?= formatMoney($supplier['amount_owed']) ?>
                        </td>
                        <td class="px-4 py-3 text-center text-sm text-gray-700">
                            <?= number_format($supplier['unpaid_invoices']) ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <?= !empty($supplier['oldest_debt_date']) ? date('d M Y', strtotime($supplier['oldest_debt_date'])) : '-' ?>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="text-sm font-bold <?= $days > 60 ? 'text-red-600' : ($days > 30 ? 'text-orange-600' : 'text-green-600') ?>">
                                <?= number_format($days) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-1 rounded-full text-xs font-semibold <?= $statusColor ?>">
                                <?= $statusText ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <a href="<?= BASE_URL ?>/suppliers/view/<?= $supplier['id'] ?>"
                                class="text-blue-600 hover:text-blue-800 text-sm">
                                View
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($suppliers)): ?>
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-gray-400 text-sm">
                            🎉 No outstanding payables! All suppliers have been paid.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($suppliers)): ?>
                <tfoot class="bg-gray-50 border-t-2 font-bold">
                    <tr>
                        <td colspan="2" class="px-4 py-3 text-right text-gray-700">TOTAL:</td>
                        <td class="px-4 py-3 text-right text-red-600"><?= formatMoney($summary['total_owed']) ?></td>
                        <td colspan="5"></td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<!-- Unpaid Purchases Detail -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-4 py-3 border-b">
        <h3 class="font-bold text-gray-800">Recent Unpaid Purchases (Last 50)</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Purchase #</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Supplier</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Total</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Paid</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Due</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Days Old</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Status</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($unpaidPurchases as $purchase): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm">
                            <a href="<?= BASE_URL ?>/purchases/view/<?= $purchase['id'] ?>"
                                class="text-blue-600 hover:underline font-mono">
                                <?= e($purchase['purchase_number']) ?>
                            </a>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <?= date('d M Y', strtotime($purchase['purchase_date'])) ?>
                        </td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-800">
                            <?= e($purchase['supplier_name']) ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-right text-gray-700">
                            <?= formatMoney($purchase['total_amount']) ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-right text-green-600">
                            <?= formatMoney($purchase['amount_paid']) ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-right font-bold text-red-600">
                            <?= formatMoney($purchase['amount_due']) ?>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="text-sm font-bold <?= $purchase['days_old'] > 60 ? 'text-red-600' : ($purchase['days_old'] > 30 ? 'text-orange-600' : 'text-gray-700') ?>">
                                <?= $purchase['days_old'] ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <?php
                            $statusBadge = [
                                'partial' => 'bg-yellow-100 text-yellow-700',
                                'unpaid' => 'bg-red-100 text-red-700'
                            ][$purchase['payment_status']] ?? 'bg-gray-100 text-gray-700';
                            ?>
                            <span class="px-2 py-1 rounded text-xs font-semibold <?= $statusBadge ?>">
                                <?= ucfirst($purchase['payment_status']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <a href="<?= BASE_URL ?>/purchases/pay/<?= $purchase['id'] ?>"
                                class="text-green-600 hover:text-green-800 text-sm font-medium">
                                Pay
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($unpaidPurchases)): ?>
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-gray-400 text-sm">
                            No unpaid purchases
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>