<?php include APP_PATH . '/views/layout/header.php'; ?>

<?= flashMessage() ?>

<!-- Page Header -->
<div class="flex flex-wrap justify-between items-center mb-6 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Customer Transactions</h1>
        <p class="text-gray-500 mt-1">All deposits, payments, and credit transactions</p>
    </div>

    <!-- Export Button -->
    <div>
        <a href="<?= BASE_URL ?>/export/transactions<?= !empty($_GET) ? '?' . http_build_query($_GET) : '' ?>"
            class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg transition">
            📤 Export Transactions
        </a>
    </div>
</div>

<!-- Summary Stats -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <div class="bg-white p-4 rounded-lg shadow text-center">
        <div class="text-gray-400 text-xs uppercase tracking-wide mb-1">Total Transactions</div>
        <div class="text-2xl font-bold text-gray-800"><?= number_format($summary['total_transactions']) ?></div>
    </div>
    <div class="bg-white p-4 rounded-lg shadow text-center">
        <div class="text-gray-400 text-xs uppercase tracking-wide mb-1">Deposits</div>
        <div class="text-2xl font-bold text-green-600"><?= formatMoney($summary['total_deposits']) ?></div>
    </div>
    <div class="bg-white p-4 rounded-lg shadow text-center">
        <div class="text-gray-400 text-xs uppercase tracking-wide mb-1">Payments</div>
        <div class="text-2xl font-bold text-blue-600"><?= formatMoney($summary['total_payments']) ?></div>
    </div>
    <div class="bg-white p-4 rounded-lg shadow text-center">
        <div class="text-gray-400 text-xs uppercase tracking-wide mb-1">Credit Sales</div>
        <div class="text-2xl font-bold text-red-600"><?= formatMoney($summary['total_credit_sales']) ?></div>
    </div>
    <div class="bg-white p-4 rounded-lg shadow text-center">
        <div class="text-gray-400 text-xs uppercase tracking-wide mb-1">Refunds</div>
        <div class="text-2xl font-bold text-purple-600"><?= formatMoney($summary['total_refunds']) ?></div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="GET" action="<?= BASE_URL ?>/transactions">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-7 gap-3">

            <!-- Search -->
            <div class="lg:col-span-2">
                <label class="block text-xs text-gray-600 mb-1">Search</label>
                <input type="text"
                    name="search"
                    value="<?= e($_GET['search'] ?? '') ?>"
                    placeholder="Customer, phone, notes..."
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <!-- Customer Filter -->
            <div>
                <label class="block text-xs text-gray-600 mb-1">Customer</label>
                <select name="customer" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All Customers</option>
                    <?php foreach ($customers as $cust): ?>
                        <option value="<?= $cust['id'] ?>" <?= (($_GET['customer'] ?? '') == $cust['id']) ? 'selected' : '' ?>>
                            <?= e($cust['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Type Filter -->
            <div>
                <label class="block text-xs text-gray-600 mb-1">Type</label>
                <select name="type" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All Types</option>
                    <option value="deposit" <?= (($_GET['type'] ?? '') === 'deposit') ? 'selected' : '' ?>>Deposit</option>
                    <option value="payment" <?= (($_GET['type'] ?? '') === 'payment') ? 'selected' : '' ?>>Payment</option>
                    <option value="sale" <?= (($_GET['type'] ?? '') === 'sale') ? 'selected' : '' ?>>Sale (Credit)</option>
                    <option value="refund" <?= (($_GET['type'] ?? '') === 'refund') ? 'selected' : '' ?>>Refund</option>
                    <option value="adjustment" <?= (($_GET['type'] ?? '') === 'adjustment') ? 'selected' : '' ?>>Adjustment</option>
                </select>
            </div>

            <!-- Date From -->
            <div>
                <label class="block text-xs text-gray-600 mb-1">From Date</label>
                <input type="date"
                    name="date_from"
                    value="<?= e($_GET['date_from'] ?? '') ?>"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <!-- Date To -->
            <div>
                <label class="block text-xs text-gray-600 mb-1">To Date</label>
                <input type="date"
                    name="date_to"
                    value="<?= e($_GET['date_to'] ?? '') ?>"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <!-- Buttons -->
            <div class="flex gap-2 items-end">
                <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    Filter
                </button>
                <a href="<?= BASE_URL ?>/transactions" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    Reset
                </a>
            </div>

        </div>
    </form>
</div>

<!-- Transactions Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">

    <?php if (empty($transactions)): ?>
        <div class="p-20 text-center text-gray-400">
            <div class="text-5xl mb-4">💰</div>
            <p class="text-lg">No transactions found</p>
            <p class="text-sm mt-2">Transactions will appear here as customers make deposits, payments, or credit purchases</p>
        </div>
    <?php else: ?>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Date/Time</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Customer</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Type</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Amount</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Balance</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Reference</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">By</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($transactions as $txn): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <!-- Date/Time -->
                            <td class="px-4 py-3 text-sm whitespace-nowrap">
                                <div class="text-gray-800 font-medium"><?= formatDate($txn['created_at'], 'd M Y') ?></div>
                                <div class="text-xs text-gray-400"><?= formatDate($txn['created_at'], 'H:i') ?></div>
                            </td>

                            <!-- Customer -->
                            <td class="px-4 py-3 text-sm">
                                <a href="<?= BASE_URL ?>/customers/view/<?= $txn['customer_id'] ?>"
                                    class="text-blue-600 hover:underline font-medium">
                                    <?= e($txn['customer_name']) ?>
                                </a>
                                <div class="text-xs text-gray-400"><?= e($txn['customer_code']) ?></div>
                            </td>

                            <!-- Type -->
                            <td class="px-4 py-3 text-sm">
                                <?php
                                $typeColors = [
                                    'deposit' => 'bg-green-100 text-green-700',
                                    'payment' => 'bg-blue-100 text-blue-700',
                                    'sale' => 'bg-red-100 text-red-700',
                                    'refund' => 'bg-purple-100 text-purple-700',
                                    'adjustment' => 'bg-yellow-100 text-yellow-700'
                                ];
                                $color = $typeColors[$txn['transaction_type']] ?? 'bg-gray-100 text-gray-700';
                                ?>
                                <span class="inline-block px-2 py-1 rounded-full text-xs font-semibold <?= $color ?>">
                                    <?= ucfirst($txn['transaction_type']) ?>
                                </span>
                                <?php if (!empty($txn['payment_method'])): ?>
                                    <div class="text-xs text-gray-400 mt-1"><?= ucfirst($txn['payment_method']) ?></div>
                                <?php endif; ?>
                            </td>

                            <!-- Amount -->
                            <td class="px-4 py-3 text-sm text-right">
                                <span class="font-bold <?= $txn['amount'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                    <?= $txn['amount'] >= 0 ? '+' : '' ?><?= formatMoney($txn['amount']) ?>
                                </span>
                            </td>

                            <!-- Balance -->
                            <td class="px-4 py-3 text-sm text-right">
                                <div class="text-xs text-gray-400 mb-0.5">
                                    <?= formatMoney($txn['balance_before']) ?>
                                </div>
                                <div class="font-semibold <?= $txn['balance_after'] < 0 ? 'text-red-600' : 'text-gray-700' ?>">
                                    <?= formatMoney($txn['balance_after']) ?>
                                </div>
                            </td>

                            <!-- Reference -->
                            <td class="px-4 py-3 text-sm">
                                <?php if (!empty($txn['reference_type']) && !empty($txn['reference_id'])): ?>
                                    <?php if ($txn['reference_type'] === 'sale'): ?>
                                        <a href="<?= BASE_URL ?>/sales/view/<?= $txn['reference_id'] ?>"
                                            class="text-blue-600 hover:underline text-xs">
                                            Sale #<?= $txn['reference_id'] ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-500">
                                            <?= ucfirst($txn['reference_type']) ?> #<?= $txn['reference_id'] ?>
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if (!empty($txn['notes'])): ?>
                                    <div class="text-xs text-gray-400 italic mt-1 max-w-xs truncate" title="<?= e($txn['notes']) ?>">
                                        <?= e($txn['notes']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Recorded By -->
                            <td class="px-4 py-3 text-sm text-gray-500 whitespace-nowrap">
                                <?= e($txn['recorded_by'] ?? 'System') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="px-6 py-4 border-t bg-gray-50 flex flex-col sm:flex-row justify-between items-center gap-3">
                <div class="text-sm text-gray-600">
                    Showing <span class="font-medium"><?= ($page - 1) * $perPage + 1 ?></span> to
                    <span class="font-medium"><?= min($page * $perPage, $total) ?></span> of
                    <span class="font-medium"><?= number_format($total) ?></span> transactions
                </div>
                <div class="flex items-center gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?><?= !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : '' ?>"
                            class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 text-sm font-medium text-gray-700 transition">
                            ← Previous
                        </a>
                    <?php else: ?>
                        <span class="px-4 py-2 bg-gray-100 border border-gray-200 rounded-lg text-sm font-medium text-gray-400 cursor-not-allowed">
                            ← Previous
                        </span>
                    <?php endif; ?>

                    <span class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium">
                        <?= $page ?> / <?= $totalPages ?>
                    </span>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?><?= !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : '' ?>"
                            class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 text-sm font-medium text-gray-700 transition">
                            Next →
                        </a>
                    <?php else: ?>
                        <span class="px-4 py-2 bg-gray-100 border border-gray-200 rounded-lg text-sm font-medium text-gray-400 cursor-not-allowed">
                            Next →
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>