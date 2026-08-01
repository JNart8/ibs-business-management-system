<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="mb-5 text-sm">
    <a href="<?= BASE_URL ?>/suppliers" class="text-blue-600 hover:underline">← Back to Suppliers</a>
</div>

<?= flashMessage() ?>

<!-- Supplier Header -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <div class="flex flex-wrap justify-between items-start gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-800"><?= e($supplier['company_name']) ?></h1>
            <div class="flex flex-wrap gap-3 mt-2 text-sm text-gray-500">
                <span>📋 <?= e($supplier['supplier_code']) ?></span>
                <?php if (!empty($supplier['contact_name'])): ?>
                    <span>👤 <?= e($supplier['contact_name']) ?></span>
                <?php endif; ?>
                <span>📞 <?= e($supplier['phone']) ?></span>
                <?php if (!empty($supplier['phone_alt'])): ?>
                    <span>📞 <?= e($supplier['phone_alt']) ?></span>
                <?php endif; ?>
                <?php if (!empty($supplier['email'])): ?>
                    <span>✉️ <?= e($supplier['email']) ?></span>
                <?php endif; ?>
                <?php if (!empty($supplier['city'])): ?>
                    <span>📍 <?= e($supplier['city']) ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($supplier['notes'])): ?>
                <div class="mt-3 text-sm text-gray-500 bg-gray-50 rounded p-3 italic">
                    📝 <?= e($supplier['notes']) ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="<?= BASE_URL ?>/purchases/create?supplier_id=<?= $supplier['id'] ?>"
                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm transition">
                + New Purchase
            </a>
            <a href="<?= BASE_URL ?>/suppliers/deposit/<?= $supplier['id'] ?>"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition">
                💳 + Payment
            </a>
            <a href="<?= BASE_URL ?>/suppliers/edit/<?= $supplier['id'] ?>"
                class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg text-sm transition">
                Edit
            </a>
        </div>
    </div>
</div>

<!-- Summary Stats -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white p-5 rounded-lg shadow text-center">
        <div class="text-gray-400 text-xs mb-1">Products Supplied</div>
        <div class="text-3xl font-bold text-gray-800"><?= count($products) ?></div>
    </div>
    <div class="bg-white p-5 rounded-lg shadow text-center">
        <div class="text-gray-400 text-xs mb-1">Credit Limit</div>
        <div class="text-3xl font-bold text-purple-600"><?= formatMoney($supplier['credit_limit'] ?? 0) ?></div>
        <div class="text-xs text-purple-500 mt-1">Max Credit Limit</div>
    </div>
    <div class="bg-white p-5 rounded-lg shadow text-center">
        <div class="text-gray-400 text-xs mb-1">Supplier Balance</div>
        <?php 
        $balance = floatval($supplier['current_balance']);
        if ($balance < 0): ?>
            <div class="text-3xl font-bold text-red-600"><?= formatMoney(abs($balance)) ?></div>
            <div class="text-xs text-red-500 mt-1">Outstanding (We Owe)</div>
        <?php elseif ($balance > 0): ?>
            <div class="text-3xl font-bold text-green-600"><?= formatMoney($balance) ?></div>
            <div class="text-xs text-green-500 mt-1">Credit / Prepayment</div>
        <?php else: ?>
            <div class="text-3xl font-bold text-gray-800"><?= formatMoney(0) ?></div>
            <div class="text-xs text-gray-500 mt-1">Clear Balance</div>
        <?php endif; ?>
    </div>
    <div class="bg-white p-5 rounded-lg shadow text-center">
        <div class="text-gray-400 text-xs mb-1">Total Purchases</div>
        <div class="text-3xl font-bold text-blue-600"><?= safeInt($summary['total_purchases_count']) ?></div>
        <?php if (!empty($summary['last_purchase_date'])): ?>
            <div class="text-xs text-gray-400 mt-1">Last: <?= formatDate($summary['last_purchase_date'], 'd M Y') ?></div>
        <?php endif; ?>
    </div>
</div>

<!-- Two column layout -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- Products List -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-5 py-4 border-b flex justify-between items-center">
            <h2 class="font-bold text-gray-700">Products from this Supplier (stock levels)</h2>
            <span class="bg-blue-100 text-blue-700 text-xs font-semibold px-2 py-1 rounded-full">
                <?= count($products) ?>
            </span>
        </div>
        <?php if (empty($products)): ?>
            <div class="p-10 text-center text-gray-400">
                <div class="text-3xl mb-2">📦</div>
                <p class="text-sm">No products assigned yet</p>
                <a href="<?= BASE_URL ?>/products" class="text-blue-600 text-xs hover:underline mt-1 inline-block">
                    Assign via Products page
                </a>
            </div>
        <?php else: ?>
            <div class="divide-y max-h-80 overflow-y-auto">
                <?php foreach ($products as $p): ?>
                    <div class="flex items-center justify-between px-5 py-3 hover:bg-gray-50">
                        <div>
                            <a href="<?= BASE_URL ?>/products/view/<?= $p['id'] ?>"
                                class="font-medium text-blue-600 hover:underline text-sm">
                                <?= e($p['name']) ?>
                            </a>
                            <div class="text-xs text-gray-400"><?= e($p['sku']) ?></div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-semibold
                                <?= $p['current_stock'] <= 0 ? 'text-red-600' : 'text-gray-700' ?>">
                                <?= $p['current_stock'] ?> <?= e($p['unit']) ?>
                            </div>
                            <div class="text-xs text-gray-400">Avg: <?= formatMoney($p['average_cost']) ?> | Cost: <?= formatMoney($p['cost_price']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Purchase History -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-5 py-4 border-b flex justify-between items-center">
            <h2 class="font-bold text-gray-700">Purchase History</h2>
            <span class="text-xs text-gray-400">Last 30 purchases</span>
        </div>
        <?php if (empty($purchases)): ?>
            <div class="p-10 text-center text-gray-400">
                <div class="text-3xl mb-2">🛒</div>
                <p class="text-sm">No purchases recorded yet</p>
                <a href="<?= BASE_URL ?>/purchases/create?supplier_id=<?= $supplier['id'] ?>" class="text-blue-600 text-xs hover:underline mt-1 inline-block">
                    Record a purchase
                </a>
            </div>
        <?php else: ?>
            <div class="divide-y max-h-80 overflow-y-auto">
                <?php foreach ($purchases as $pur): ?>
                    <div class="flex items-center justify-between px-5 py-3 hover:bg-gray-50">
                        <div>
                            <div class="flex items-center gap-2">
                                <a href="<?= BASE_URL ?>/purchases/view/<?= $pur['id'] ?>" class="text-sm font-semibold text-blue-600 hover:underline">
                                    <?= e($pur['purchase_number']) ?>
                                </a>
                                <?php if (!empty($pur['invoice_number'])): ?>
                                    <span class="text-xs text-gray-500 font-mono">#<?= e($pur['invoice_number']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-gray-400">
                                <?= formatDate($pur['purchase_date'], 'd M Y H:i') ?>
                                <?= !empty($pur['created_by']) ? ' · ' . e($pur['created_by']) : '' ?>
                            </div>
                            <?php if (!empty($pur['notes'])): ?>
                                <div class="text-xs text-gray-400 italic"><?= e($pur['notes']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="text-right flex items-center gap-3">
                            <div class="text-right">
                                <div class="font-bold text-gray-800"><?= formatMoney($pur['total_amount']) ?></div>
                                <div class="text-xs text-gray-400">Paid: <?= formatMoney($pur['amount_paid']) ?></div>
                            </div>
                            <div>
                                <?php
                                $statusColors = [
                                    'paid' => 'bg-green-100 text-green-800',
                                    'partial' => 'bg-yellow-100 text-yellow-800',
                                    'unpaid' => 'bg-red-100 text-red-800',
                                ];
                                $color = $statusColors[$pur['payment_status']] ?? 'bg-gray-100 text-gray-800';
                                ?>
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full <?= $color ?>">
                                    <?= e(ucfirst($pur['payment_status'])) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Transaction History (full width) -->
<div class="bg-white rounded-lg shadow overflow-hidden mt-6">
    <div class="px-5 py-4 border-b flex justify-between items-center">
        <div>
            <h2 class="font-bold text-gray-700">Transaction History</h2>
            <span class="text-xs text-gray-400"><?= count($transactions) ?> records</span>
        </div>
        <a href="<?= BASE_URL ?>/suppliers/deposit/<?= $supplier['id'] ?>"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition">
            💳 Record Payment
        </a>
    </div>
    <div class="divide-y max-h-96 overflow-y-auto">
        <?php if (empty($transactions)): ?>
            <div class="p-10 text-center text-gray-400">
                <div class="text-3xl mb-2">📋</div>
                <p class="text-sm">No transactions recorded yet</p>
            </div>
        <?php else: ?>
            <?php
            $txLabels = [
                'purchase'   => '🛒 Purchase',
                'payment'    => '💳 Payment',
                'refund'     => '↩️ Refund',
                'adjustment' => '⚙️ Adjustment',
                'deposit'    => '💰 Deposit',
            ];
            ?>
            <?php foreach ($transactions as $tx): ?>
                <?php
                $isCredit = in_array($tx['transaction_type'], ['payment', 'deposit', 'refund']);
                $amtColor = $isCredit ? 'text-green-600' : 'text-red-600';
                $sign     = $isCredit ? '+' : '-';
                $balAfter = floatval($tx['balance_after']);
                ?>
                <div class="flex items-center justify-between px-5 py-3 hover:bg-gray-50">
                    <div>
                        <div class="text-sm font-medium text-gray-700">
                            <?= $txLabels[$tx['transaction_type']] ?? ucfirst($tx['transaction_type']) ?>
                        </div>
                        <div class="text-xs text-gray-400">
                            <?= formatDate($tx['created_at'], 'd M Y H:i') ?>
                            <?= !empty($tx['user_name']) ? ' · ' . e($tx['user_name']) : '' ?>
                            <?= !empty($tx['payment_method']) ? ' · ' . ucfirst($tx['payment_method']) : '' ?>
                        </div>
                        <?php if (!empty($tx['notes'])): ?>
                            <div class="text-xs text-gray-400 italic"><?= e($tx['notes']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="text-right">
                            <div class="font-semibold <?= $amtColor ?>">
                                <?= $sign ?><?= formatMoney(abs($tx['amount'])) ?>
                            </div>
                            <div class="text-xs <?= $balAfter < 0 ? 'text-red-400' : 'text-green-500' ?>">
                                Bal: <?= $balAfter < 0 ? '-' : '' ?><?= formatMoney(abs($balAfter)) ?>
                            </div>
                        </div>
                        <?php if ($tx['transaction_type'] === 'payment' && $tx['reference_type'] === 'payment'): ?>
                            <?php $txAmt = formatMoney($tx['amount']); ?>
                            <a href="<?= BASE_URL ?>/suppliers/edit-deposit/<?= $tx['id'] ?>"
                                class="text-xs px-2 py-1 bg-yellow-100 hover:bg-yellow-200 text-yellow-700 rounded transition whitespace-nowrap">
                                ✏️ Edit
                            </a>
                            <form method="POST" action="<?= BASE_URL ?>/suppliers/delete-deposit/<?= $tx['id'] ?>"
                                class="inline"
                                onsubmit="return confirm('Delete this payment of <?= $txAmt ?>?\n\nThis will reverse the supplier balance and restore the account balance.')">
                                <?= csrfField() ?>
                                <button type="submit"
                                    class="text-xs px-2 py-1 bg-red-100 hover:bg-red-200 text-red-700 rounded transition whitespace-nowrap">
                                    🗑️ Delete
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>