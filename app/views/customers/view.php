<?php include APP_PATH . '/views/layout/header.php'; ?>

<?php
$balance        = floatval($customer['current_balance']);
$creditLimit    = floatval($customer['credit_limit']);
$availableCredit = $creditLimit - ($balance < 0 ? abs($balance) : 0);
$balanceColor   = $balance < 0 ? 'red' : ($balance > 0 ? 'green' : 'gray');
?>

<div class="mb-5 text-sm">
    <a href="<?= BASE_URL ?>/customers" class="text-blue-600 hover:underline">← Back to Customers</a>
</div>

<?= flashMessage() ?>

<!-- Customer Header Card -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <div class="flex flex-wrap justify-between items-start gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-800"><?= e($customer['full_name']) ?></h1>
            <div class="flex flex-wrap gap-3 mt-2 text-sm text-gray-500">
                <span>📋 <?= e($customer['customer_code']) ?></span>
                <span>📞 <?= e($customer['phone']) ?></span>
                <?php if (!empty($customer['email'])): ?>
                    <span>✉️ <?= e($customer['email']) ?></span>
                <?php endif; ?>
                <?php if (!empty($customer['address'])): ?>
                    <span>📍 <?= e($customer['address']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="<?= BASE_URL ?>/customers/statement/<?= $customer['id'] ?>"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition">
                📄 Statement
            </a>
            <a href="<?= BASE_URL ?>/customers/deposit/<?= $customer['id'] ?>"
                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm transition">
                + Deposit
            </a>
            <a href="<?= BASE_URL ?>/customers/adjust-credit/<?= $customer['id'] ?>"
                class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm transition">
                Credit Limit
            </a>
            <a href="<?= BASE_URL ?>/customers/edit/<?= $customer['id'] ?>"
                class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg text-sm transition">
                Edit
            </a>
        </div>
    </div>
</div>

<!-- Stats Row -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white p-5 rounded-lg shadow">
        <div class="text-gray-400 text-xs mb-1">Current Balance</div>
        <div class="text-xl font-bold text-<?= $balanceColor ?>-600">
            <?php if ($balance < 0): ?>
                -<?= formatMoney(abs($balance)) ?>
            <?php else: ?>
                <?= formatMoney($balance) ?>
            <?php endif; ?>
        </div>
        <div class="text-xs mt-1 text-<?= $balanceColor ?>-400">
            <?= $balance < 0 ? 'Owes money' : ($balance > 0 ? 'Cash Balance' : 'Zero balance') ?>
        </div>
    </div>
    <div class="bg-white p-5 rounded-lg shadow">
        <div class="text-gray-400 text-xs mb-1">Credit Limit</div>
        <div class="text-xl font-bold text-gray-800"><?= formatMoney($creditLimit) ?></div>
        <div class="text-xs text-gray-400 mt-1">
            Available: <?= formatMoney(max(0, $availableCredit)) ?>
        </div>
    </div>
    <div class="bg-white p-5 rounded-lg shadow">
        <div class="text-gray-400 text-xs mb-1">Total Orders</div>
        <div class="text-xl font-bold text-gray-800"><?= safeInt($salesSummary['total_orders']) ?></div>
        <?php if (!empty($salesSummary['last_purchase'])): ?>
            <div class="text-xs text-gray-400 mt-1">
                Last: <?= formatDate($salesSummary['last_purchase'], 'd M Y') ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="bg-white p-5 rounded-lg shadow">
        <div class="text-gray-400 text-xs mb-1">Total Purchased</div>
        <div class="text-xl font-bold text-blue-600"><?= formatMoney($salesSummary['total_spent']) ?></div>
        <div class="text-xs text-gray-400 mt-1">
            Avg: <?= formatMoney($salesSummary['avg_order_value']) ?>
        </div>
    </div>
</div>

<!-- Credit Bar -->
<?php if ($creditLimit > 0): ?>
    <?php $usedPct = $creditLimit > 0 ? min(100, (abs(min(0, $balance)) / $creditLimit) * 100) : 0; ?>
    <div class="bg-white p-5 rounded-lg shadow mb-6">
        <div class="flex justify-between text-sm font-medium text-gray-600 mb-2">
            <span>Credit Used</span>
            <span><?= number_format($usedPct, 1) ?>% of <?= formatMoney($creditLimit) ?></span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-3">
            <div class="h-3 rounded-full transition-all <?= $usedPct >= 90 ? 'bg-red-500' : ($usedPct >= 60 ? 'bg-yellow-500' : 'bg-green-500') ?>"
                style="width: <?= $usedPct ?>%"></div>
        </div>
    </div>
<?php endif; ?>

<!-- Two-column: Transactions + Recent Sales -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- Transaction History -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-5 py-4 border-b flex justify-between items-center">
            <div>
                <h2 class="font-bold text-gray-700">Transaction History</h2>
                <span class="text-xs text-gray-400"><?= count($transactions) ?> records</span>
            </div>

            <a href="<?= BASE_URL ?>/export/transactions?customer_id=<?= $customer['id'] ?>"
                class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm transition">
                📤 Export
            </a>
        </div>
        <div class="divide-y max-h-96 overflow-y-auto">
            <?php if (empty($transactions)): ?>
                <div class="p-8 text-center text-gray-400">
                    <p>No transactions yet</p>
                </div>
            <?php else: ?>
                <?php foreach ($transactions as $t): ?>
                    <?php
                    $tColor  = in_array($t['transaction_type'], ['deposit', 'payment', 'refund'])
                        ? 'green' : 'red';
                    $tSign   = in_array($t['transaction_type'], ['deposit', 'payment', 'refund'])
                        ? '+' : '-';
                    $tLabels = [
                        'deposit'    => '💰 Deposit',
                        'payment'    => '💳 Payment',
                        'sale'       => '🛒 Sale',
                        'refund'     => '↩️ Refund',
                        'adjustment' => '⚙️ Adjustment',
                    ];
                    ?>
                    <div class="flex items-center justify-between px-5 py-3 hover:bg-gray-50">
                        <div>
                            <div class="text-sm font-medium text-gray-700">
                                <?= $tLabels[$t['transaction_type']] ?? $t['transaction_type'] ?>
                            </div>
                            <div class="text-xs text-gray-400">
                                <?= formatDate($t['created_at'], 'd M Y H:i') ?>
                                <?= !empty($t['user_name']) ? ' · ' . e($t['user_name']) : '' ?>
                            </div>
                            <?php if (!empty($t['notes'])): ?>
                                <div class="text-xs text-gray-400 italic"><?= e($t['notes']) ?></div>
                            <?php endif; ?>
                            <?php if ($t['transaction_type'] === 'deposit'): ?>
                                <div class="mt-1 text-xs space-x-2">
                                    <a href="<?= BASE_URL ?>/customers/edit-deposit/<?= $t['id'] ?>" class="text-blue-600 hover:underline">✏️ Edit</a>
                                    <a href="<?= BASE_URL ?>/customers/delete-deposit/<?= $t['id'] ?>" 
                                       onclick="return confirm('Are you sure you want to delete this deposit? This will revert the customer balance and financial account balance.')" 
                                       class="text-red-600 hover:underline">🗑️ Delete</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="text-right">
                            <?php if ($t['transaction_type'] !== 'adjustment'): ?>
                                <div class="font-semibold text-<?= $tColor ?>-600">
                                    <?= $tSign ?><?= formatMoney(abs($t['amount'])) ?>
                                </div>
                            <?php endif; ?>
                            <div class="text-xs text-gray-400">
                                Bal: <?= formatMoney($t['balance_after']) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Sales -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-5 py-4 border-b flex justify-between items-center">
            <h2 class="font-bold text-gray-700">Recent Sales</h2>
            <a href="<?= BASE_URL ?>/sales?customer=<?= $customer['id'] ?>"
                class="text-xs text-blue-600 hover:underline">View all →</a>
        </div>
        <div class="divide-y max-h-96 overflow-y-auto">
            <?php if (empty($recentSales)): ?>
                <div class="p-8 text-center text-gray-400">
                    <p>No sales yet</p>
                </div>
            <?php else: ?>
                <?php foreach ($recentSales as $s): ?>
                    <?php
                    $isVoided = strpos($s['notes'] ?? '', '[VOIDED]') !== false;
                    $statusText = $isVoided ? 'Voided' : ucfirst($s['payment_status']);
                    $statusColors = [
                        'paid'    => 'bg-green-100 text-green-700',
                        'partial' => 'bg-yellow-100 text-yellow-700',
                        'unpaid'  => 'bg-red-100 text-red-600',
                    ];
                    $sColor = $isVoided ? 'bg-gray-100 text-gray-600 border border-gray-200' : ($statusColors[$s['payment_status']] ?? 'bg-gray-100 text-gray-600');
                    ?>
                    <div class="flex items-center justify-between px-5 py-3 hover:bg-gray-50">
                        <div>
                            <div class="text-sm font-medium text-gray-700">
                                <?= e($s['sale_number']) ?>
                            </div>
                            <div class="text-xs text-gray-400">
                                <?= formatDate($s['sale_date'], 'd M Y H:i') ?>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-semibold text-gray-800">
                                <?= formatMoney($s['total_amount']) ?>
                            </div>
                            <span class="text-xs px-2 py-0.5 rounded-full <?= $sColor ?>">
                                <?= $statusText ?>
                            </span>
                            <?php if ($s['amount_due'] > 0 && !$isVoided): ?>
                                <div class="text-xs text-red-500 mt-0.5">
                                    Due: <?= formatMoney($s['amount_due']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>