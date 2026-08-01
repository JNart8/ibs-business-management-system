<?php include APP_PATH . '/views/layout/header.php'; ?>

<!-- Page Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">💳 Financial Accounts</h1>
        <p class="text-gray-500 text-sm mt-1">Manage cash, mobile money, and bank account balances</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= BASE_URL ?>/financial-accounts/transfer"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium transition flex items-center gap-1">
            🔄 Move Funds
        </a>
        <?php if (planAllows('manage_financial_accounts')): ?>
        <button onclick="document.getElementById('addAccountModal').classList.remove('hidden')"
            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium transition flex items-center gap-1">
            + Add Account
        </button>
        <?php else: ?>
        <span class="text-gray-400 text-sm px-4 py-2.5" title="Upgrade your plan to add more accounts">
            🔒 Add Account (upgrade)
        </span>
        <?php endif; ?>
    </div>
</div>

<!-- Flash Message -->
<?= flashMessage() ?>

<!-- Accounts Grid -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <?php foreach ($accounts as $account): ?>
        <?php 
            $colorClass = 'bg-gradient-to-br from-gray-500 to-gray-600';
            if ($account['type'] === 'cash') {
                $colorClass = 'bg-gradient-to-br from-emerald-500 to-teal-600 border-emerald-200';
            } elseif ($account['type'] === 'mobile_money') {
                $colorClass = 'bg-gradient-to-br from-amber-500 to-orange-600 border-amber-200';
            } elseif ($account['type'] === 'bank') {
                $colorClass = 'bg-gradient-to-br from-blue-500 to-indigo-600 border-blue-200';
            }

            $icon = [
                'cash' => '💵',
                'mobile_money' => '📱',
                'bank' => '🏦'
            ][$account['type']] ?? '💰';
        ?>
        <div class="relative overflow-hidden rounded-xl shadow-sm text-white p-6 transition-transform duration-200 hover:-translate-y-1 <?= $colorClass ?>">
            <!-- Background Icon -->
            <div class="absolute -right-4 -bottom-4 text-7xl opacity-15 pointer-events-none select-none z-0">
                <?= $icon ?>
            </div>
            
            <div class="relative z-10 flex flex-col h-full justify-between">
                <div>
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <span class="text-xs uppercase font-bold tracking-wider bg-white/20 px-2 py-1 rounded">
                                <?= ucfirst(str_replace('_', ' ', $account['type'])) ?>
                            </span>
                            <?php if ($account['is_default']): ?>
                                <span class="text-[10px] font-bold bg-white text-gray-800 ml-1 px-1.5 py-0.5 rounded-full">DEFAULT</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-2xl"><?= $icon ?></div>
                    </div>

                    <h3 class="text-xl font-bold mb-1 truncate"><?= e($account['name']) ?></h3>
                    <p class="text-xs opacity-85 mb-4">
                        <?= e($account['provider']) ?><?= !empty($account['account_number']) ? ' • ' . e($account['account_number']) : '' ?>
                    </p>
                </div>

                <div class="flex justify-between items-end mt-4">
                    <div>
                        <div class="text-xs opacity-75">Current Balance</div>
                        <div class="text-2xl font-black tracking-tight font-sans">
                            <?= formatMoney($account['balance']) ?>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <a href="<?= BASE_URL ?>/financial-accounts/edit/<?= $account['id'] ?>"
                            class="bg-white/20 hover:bg-white/30 text-white text-xs font-semibold py-1 px-3 rounded transition z-20">
                            ✏️ Edit
                        </a>
                        <a href="<?= BASE_URL ?>/financial-accounts/transactions/<?= $account['id'] ?>"
                            class="bg-white/20 hover:bg-white/30 text-white text-xs font-semibold py-1 px-3 rounded transition z-20">
                            View Ledger →
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Bottom Section: Recent Transfers -->
<div class="bg-white rounded-lg shadow-sm p-6">
    <h2 class="text-lg font-bold text-gray-800 mb-4">🔄 Recent Funds Transfers</h2>
    <?php if (empty($recentTransfers)): ?>
        <div class="text-center py-8 text-gray-400">
            <p class="text-sm">No transfers recorded yet.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">From Account</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">To Account</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transfer Charges</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Deducted</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">By User</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 text-sm">
                    <?php foreach ($recentTransfers as $transfer): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-500">
                                <?= formatDate($transfer['created_at']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap font-medium text-red-600">
                                <?= e($transfer['from_account_name']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap font-medium text-green-600">
                                <?= e($transfer['to_account_name']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap font-bold text-gray-800">
                                <?= formatMoney($transfer['amount']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-red-600">
                                <?= $transfer['charges'] > 0 ? formatMoney($transfer['charges']) : 'GHS 0.00' ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap font-bold text-red-700">
                                <?= formatMoney($transfer['amount'] + $transfer['charges']) ?>
                            </td>
                            <td class="px-6 py-4 text-gray-500 max-w-xs truncate">
                                <?= e($transfer['notes']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-500">
                                <?= e($transfer['user_name'] ?? 'System') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Add Account Modal -->
<div id="addAccountModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 overflow-hidden">
        <div class="bg-blue-600 text-white px-6 py-4 flex justify-between items-center">
            <h3 class="font-bold text-lg">➕ Add New Financial Account</h3>
            <button onclick="document.getElementById('addAccountModal').classList.add('hidden')" class="text-white hover:text-gray-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <form action="<?= BASE_URL ?>/financial-accounts/store" method="POST" class="p-6 space-y-4">
            <?= csrfField() ?>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Account Name *</label>
                <input type="text" name="name" required placeholder="e.g., Fidelity Bank Business Account"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Account Type *</label>
                <select name="type" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="bank">🏦 Bank Account</option>
                    <option value="mobile_money">📱 Mobile Money Account</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Provider (Bank / MoMo Brand)</label>
                <input type="text" name="provider" placeholder="e.g., Fidelity Bank, MTN, Telecel"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Account / Phone Number</label>
                <input type="text" name="account_number" placeholder="e.g., 104033284711, 0244111222"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Initial Opening Balance (GHS) *</label>
                <input type="number" step="0.01" name="initial_balance" value="0.00" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <button type="button" onclick="document.getElementById('addAccountModal').classList.add('hidden')"
                    class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-2 px-4 rounded-lg text-sm transition">
                    Cancel
                </button>
                <button type="submit"
                    class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-lg text-sm transition">
                    Create Account
                </button>
            </div>
        </form>
    </div>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
