<?php include APP_PATH . '/views/layout/header.php'; ?>

<!-- Page Header -->
<div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-6 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">💸 Operating Expenses</h1>
        <p class="text-gray-500 text-sm mt-1">Track and manage business operations expenses and payment accounts</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>/expenses/create"
            class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg text-sm font-semibold transition flex items-center gap-1 shadow-sm">
            + Record Expense
        </a>
    </div>
</div>

<!-- Flash Message -->
<?= flashMessage() ?>

<!-- Filter Panel -->
<div class="bg-white rounded-lg shadow-sm p-4 mb-6">
    <form method="GET" action="<?= BASE_URL ?>/expenses" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 items-end">
        <div>
            <label class="block text-xs font-bold text-gray-700 mb-1 uppercase tracking-wide">From Date</label>
            <input type="date" name="date_from" value="<?= e($dateFrom) ?>"
                class="w-full text-sm border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-700 mb-1 uppercase tracking-wide">To Date</label>
            <input type="date" name="date_to" value="<?= e($dateTo) ?>"
                class="w-full text-sm border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-700 mb-1 uppercase tracking-wide">Category</label>
            <select name="category"
                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">-- All Categories --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= e($cat['category']) ?>" <?= $category === $cat['category'] ? 'selected' : '' ?>>
                        <?= e($cat['category']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-700 mb-1 uppercase tracking-wide">Payment Account</label>
            <select name="account_id"
                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">-- All Accounts --</option>
                <?php foreach ($accounts as $acc): ?>
                    <option value="<?= $acc['id'] ?>" <?= $accountId == $acc['id'] ? 'selected' : '' ?>>
                        <?= e($acc['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg text-sm transition flex-1 text-center">
                Filter
            </button>
            <a href="<?= BASE_URL ?>/expenses"
                class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-lg text-sm transition text-center flex-1">
                Reset
            </a>
        </div>
    </form>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow-sm p-4 border-l-4 border-red-500">
        <div class="text-xs text-gray-500 uppercase font-bold">Total Net Expenses</div>
        <div class="text-2xl font-black text-gray-800 mt-1"><?= formatMoney($totalAmount) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-4 border-l-4 border-amber-500">
        <div class="text-xs text-gray-500 uppercase font-bold">Total Payment Charges</div>
        <div class="text-2xl font-black text-gray-800 mt-1"><?= formatMoney($totalCharges) ?></div>
    </div>
    <div class="bg-gradient-to-br from-rose-500 to-red-600 rounded-lg shadow-sm p-4 text-white">
        <div class="text-xs opacity-90 uppercase font-bold">Total Gross Outflow</div>
        <div class="text-2xl font-black mt-1"><?= formatMoney($totalAmount + $totalCharges) ?></div>
    </div>
</div>

<!-- Expenses Table -->
<div class="bg-white rounded-lg shadow-sm p-6">
    <h2 class="text-lg font-bold text-gray-800 mb-4">📋 Expense Log</h2>
    <?php if (empty($expenses)): ?>
        <div class="text-center py-12 text-gray-400">
            <p class="text-sm">No expenses recorded for this period.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paid From</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Net Amount</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Charges</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Outflow</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 text-sm">
                    <?php foreach ($expenses as $expense): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-500">
                                <?= date('Y-m-d', strtotime($expense['expense_date'])) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap font-semibold text-gray-700">
                                <span class="bg-gray-100 text-gray-800 px-2.5 py-1 rounded text-xs">
                                    <?= e($expense['category']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-800 font-medium">
                                <?= e($expense['description']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                                <?= e($expense['account_name'] ?? 'N/A') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-semibold text-gray-800">
                                <?= formatMoney($expense['amount']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-red-500">
                                <?= $expense['charges'] > 0 ? formatMoney($expense['charges']) : 'GHS 0.00' ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-black text-red-600">
                                <?= formatMoney($expense['amount'] + $expense['charges']) ?>
                            </td>
                            <td class="px-6 py-4 text-gray-400 max-w-xs truncate">
                                <?= e($expense['notes']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                <a href="<?= BASE_URL ?>/expenses/delete/<?= $expense['id'] ?>" 
                                   onclick="return confirm('Are you sure you want to delete this expense? This will refund GHS <?= number_format($expense['amount'] + $expense['charges'], 2) ?> back to the account: <?= e($expense['account_name']) ?>.')"
                                   class="text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 py-1 px-3.5 rounded transition">
                                    Delete
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
