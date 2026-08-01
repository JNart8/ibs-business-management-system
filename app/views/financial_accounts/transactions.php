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

<!-- Transactions Table -->
<div class="bg-white rounded-lg shadow-sm p-6">
    <h2 class="text-lg font-bold text-gray-800 mb-4">📜 Transaction History</h2>
    <?php if (empty($transactions)): ?>
        <div class="text-center py-12 text-gray-400">
            <p class="text-sm">No transactions recorded for this account.</p>
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
