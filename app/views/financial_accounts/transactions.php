<?php include APP_PATH . '/views/layout/header.php'; ?>

<!-- Page Header -->
<div class="mb-6">
    <a href="<?= BASE_URL ?>/financial-accounts" class="text-blue-600 hover:underline text-sm font-semibold flex items-center gap-1 mb-2">
        ← Back to Accounts
    </a>
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">📖 Account Ledger: <?= e($account['name']) ?></h1>
            <p class="text-gray-500 text-sm mt-1">
                <?= e($account['provider']) ?><?= !empty($account['account_number']) ? ' • ' . e($account['account_number']) : '' ?>
            </p>
        </div>
        <div class="bg-gray-800 text-white rounded-lg px-6 py-3 text-right">
            <span class="text-xs opacity-75 uppercase block tracking-wider font-semibold">Current Balance</span>
            <span class="text-2xl font-black tracking-tight"><?= formatMoney($account['balance']) ?></span>
        </div>
    </div>
</div>

<!-- Filters + Export -->
<?php
    $ledgerFilterParams = array_filter([
        'type'      => $_GET['type']      ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to'   => $_GET['date_to']   ?? '',
    ]);
    $exportUrl = BASE_URL . '/export/account-ledger?account_id=' . $account['id']
        . (!empty($ledgerFilterParams) ? '&' . http_build_query($ledgerFilterParams) : '');
?>
<div class="bg-white rounded-lg shadow-sm p-4 mb-4">
    <form method="GET" action="<?= BASE_URL ?>/financial-accounts/transactions/<?= $account['id'] ?>"
        class="flex flex-wrap items-end gap-3">

        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Type</label>
            <select name="type"
                class="text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">All Types</option>
                <?php foreach (['deposit', 'withdrawal', 'transfer_in', 'transfer_out', 'charge'] as $t): ?>
                    <option value="<?= $t ?>" <?= ($_GET['type'] ?? '') === $t ? 'selected' : '' ?>>
                        <?= ucfirst(str_replace('_', ' ', $t)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">From</label>
            <input type="date" name="date_from" value="<?= e($_GET['date_from'] ?? '') ?>"
                class="text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">To</label>
            <input type="date" name="date_to" value="<?= e($_GET['date_to'] ?? '') ?>"
                class="text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>

        <button type="submit"
            class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
            Filter
        </button>

        <?php if (!empty($_GET['type']) || !empty($_GET['date_from']) || !empty($_GET['date_to'])): ?>
            <a href="<?= BASE_URL ?>/financial-accounts/transactions/<?= $account['id'] ?>"
                class="text-sm text-gray-500 hover:text-gray-700 px-2">
                Clear
            </a>
        <?php endif; ?>

        <?php if (planAllows('imports_exports')): ?>
            <a href="<?= e($exportUrl) ?>"
                class="ml-auto bg-green-600 hover:bg-green-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
                ⬇ Export CSV
            </a>
        <?php else: ?>
            <span class="ml-auto text-sm text-gray-400 px-4 py-2" title="Upgrade your plan to export">
                🔒 Export CSV (upgrade)
            </span>
        <?php endif; ?>
    </form>
</div>

<!-- Transactions Table -->
<div class="bg-white rounded-lg shadow-sm p-6">
    <h2 class="text-lg font-bold text-gray-800 mb-4">
        📜 Transaction History
        <span class="text-sm font-normal text-gray-400">(<?= count($transactions) ?> <?= !empty($ledgerFilterParams) ? 'matching' : '' ?> <?= count($transactions) === 1 ? 'entry' : 'entries' ?>)</span>
    </h2>
    <?php if (empty($transactions)): ?>
        <div class="text-center py-12 text-gray-400">
            <?php if (!empty($ledgerFilterParams)): ?>
                <p class="text-sm">No transactions match this filter. <a href="<?= BASE_URL ?>/financial-accounts/transactions/<?= $account['id'] ?>" class="text-blue-600 hover:underline">Clear filters</a> to see everything.</p>
            <?php else: ?>
                <p class="text-sm">No transactions recorded for this account.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Balance Before</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Balance After</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded By</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 text-sm">
                    <?php foreach ($transactions as $txn): ?>
                        <?php 
                            $badgeColors = [
                                'deposit' => 'bg-green-100 text-green-700',
                                'withdrawal' => 'bg-red-100 text-red-700',
                                'transfer_in' => 'bg-emerald-100 text-emerald-700',
                                'transfer_out' => 'bg-orange-100 text-orange-700',
                                'charge' => 'bg-rose-100 text-rose-700',
                            ];
                            $badgeColor = $badgeColors[$txn['transaction_type']] ?? 'bg-gray-100 text-gray-700';
                            
                            $isOutflow = in_array($txn['transaction_type'], ['withdrawal', 'transfer_out', 'charge']);
                            $sign = $isOutflow ? '-' : '+';
                            $amountClass = $isOutflow ? 'text-red-600 font-bold' : 'text-green-600 font-bold';
                        ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-500">
                                <?= formatDate($txn['created_at']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-xs px-2.5 py-1 rounded-full font-semibold <?= $badgeColor ?>">
                                    <?= ucfirst(str_replace('_', ' ', $txn['transaction_type'])) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-500 font-medium">
                                <?= ucfirst($txn['reference_type']) ?><?= $txn['reference_id'] ? ' #' . $txn['reference_id'] : '' ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right <?= $amountClass ?>">
                                <?= $sign ?> <?= formatMoney($txn['amount']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-gray-500">
                                <?= formatMoney($txn['balance_before']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-bold text-gray-800">
                                <?= formatMoney($txn['balance_after']) ?>
                            </td>
                            <td class="px-6 py-4 text-gray-500 max-w-xs truncate">
                                <?= e($txn['notes']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-500">
                                <?= e($txn['user_name'] ?? 'System') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
