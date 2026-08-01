<?php include APP_PATH . '/views/layout/header.php'; ?>

<!-- Page Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">🕵️ Suspense Account Manager</h1>
        <p class="text-gray-500 text-sm mt-1">Track funds of unknown origin and assign them to customer and financial accounts</p>
    </div>
    <div class="flex gap-2">
        <button onclick="openRecordModal()"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium transition flex items-center gap-1 shadow-sm">
            ➕ Record Unknown Deposit
        </button>
    </div>
</div>

<!-- Flash Message -->
<?= flashMessage() ?>

<!-- Overview Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <!-- Suspense Account Balance Card -->
    <?php
        $bal = floatval($suspenseAccount['balance']);
        $cardBg = $bal > 0 ? 'from-purple-500 to-indigo-600' : 'from-gray-500 to-gray-600';
    ?>
    <div class="relative overflow-hidden rounded-xl shadow-sm text-white p-6 bg-gradient-to-br <?= $cardBg ?> border border-indigo-200">
        <div class="absolute -right-4 -bottom-4 text-7xl opacity-15 pointer-events-none select-none z-0">
            🕵️
        </div>
        <div class="relative z-10 flex flex-col h-full justify-between">
            <div>
                <span class="text-xs uppercase font-bold tracking-wider bg-white/20 px-2 py-1 rounded">
                    Suspense Ledger
                </span>
                <h3 class="text-xl font-bold mt-2 mb-1 truncate"><?= e($suspenseAccount['name']) ?></h3>
                <p class="text-xs opacity-85">
                    <?= e($suspenseAccount['provider']) ?> · <?= e($suspenseAccount['account_number']) ?>
                </p>
            </div>
            <div class="mt-4">
                <div class="text-xs opacity-75">Unresolved Balance</div>
                <div class="text-3xl font-black tracking-tight font-sans">
                    <?= formatMoney($bal) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Transactions Count -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex items-center justify-between">
        <div>
            <span class="text-xs font-bold text-yellow-600 uppercase bg-yellow-50 px-2.5 py-1 rounded-full">Pending Resolution</span>
            <div class="text-3xl font-black text-gray-800 mt-2 font-sans"><?= count($unresolvedTransactions) ?></div>
            <p class="text-xs text-gray-400 mt-1">Unknown payments waiting for identification</p>
        </div>
        <div class="text-4xl text-yellow-500 opacity-60">🕒</div>
    </div>

    <!-- Total Resolved in History -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex items-center justify-between">
        <div>
            <span class="text-xs font-bold text-green-600 uppercase bg-green-50 px-2.5 py-1 rounded-full">Resolved Transactions</span>
            <div class="text-3xl font-black text-gray-800 mt-2 font-sans"><?= count($resolvedTransactions) ?></div>
            <p class="text-xs text-gray-400 mt-1">Successfully assigned deposits</p>
        </div>
        <div class="text-4xl text-green-500 opacity-60">✓</div>
    </div>
</div>

<!-- Tabs Section -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden" x-data="{ activeTab: 'pending' }">
    <div class="border-b border-gray-200 bg-gray-50 flex">
        <button 
            @click="activeTab = 'pending'"
            :class="activeTab === 'pending' ? 'border-blue-600 text-blue-600 font-bold bg-white' : 'border-transparent text-gray-500 hover:text-gray-700'"
            class="px-6 py-4 border-b-2 font-medium text-sm transition focus:outline-none flex items-center gap-2">
            🕒 Pending Identification (<?= count($unresolvedTransactions) ?>)
        </button>
        <button 
            @click="activeTab = 'resolved'"
            :class="activeTab === 'resolved' ? 'border-blue-600 text-blue-600 font-bold bg-white' : 'border-transparent text-gray-500 hover:text-gray-700'"
            class="px-6 py-4 border-b-2 font-medium text-sm transition focus:outline-none flex items-center gap-2">
            ✅ Resolution Log (<?= count($resolvedTransactions) ?>)
        </button>
    </div>

    <!-- Pending Transactions List -->
    <div x-show="activeTab === 'pending'" class="p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Unidentified Deposits</h2>
        <?php if (empty($unresolvedTransactions)): ?>
            <div class="text-center py-12 text-gray-400">
                <span class="text-4xl block mb-2">🎉</span>
                <p class="text-sm font-medium">All suspense transactions are fully resolved.</p>
                <p class="text-xs mt-1 text-gray-500">Every deposit in the system has been mapped to a customer.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Recorded</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference No</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Received In</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes / Description</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 text-sm">
                        <?php foreach ($unresolvedTransactions as $tx): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 whitespace-nowrap text-gray-500">
                                    <?= formatDate($tx['created_at']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-800">
                                    <span class="font-mono bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs border">
                                        <?= e($tx['reference_no'] ?: 'NONE') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-bold text-blue-600">
                                    <?= formatMoney($tx['amount']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                                    <?= e($tx['account_name'] ?? 'N/A') ?>
                                </td>
                                <td class="px-6 py-4 text-gray-500 max-w-sm">
                                    <?= e($tx['source_notes'] ?: 'No details provided.') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button 
                                        onclick="openResolveModal(<?= $tx['id'] ?>, '<?= $tx['amount'] ?>', '<?= e($tx['reference_no'] ?: 'NONE') ?>')"
                                        class="bg-green-600 hover:bg-green-700 text-white px-3.5 py-1.5 rounded-lg text-xs transition shadow-sm font-semibold">
                                        🕵️ Assign to Customer
                                    </button>
                                    <div class="flex items-center justify-end gap-2 mt-1.5">
                                        <a href="<?= BASE_URL ?>/suspense/edit/<?= $tx['id'] ?>" class="text-blue-600 hover:underline text-xs">✏️ Edit</a>
                                        <a href="<?= BASE_URL ?>/suspense/delete/<?= $tx['id'] ?>" 
                                           onclick="return confirm('Are you sure you want to delete this unknown deposit? This will revert the bank/momo balance and suspense account balance.')" 
                                           class="text-red-600 hover:underline text-xs">🗑️ Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Resolved Transactions Log -->
    <div x-show="activeTab === 'resolved'" class="p-6" x-cloak>
        <h2 class="text-lg font-bold text-gray-800 mb-4">Historical Resolutions</h2>
        <?php if (empty($resolvedTransactions)): ?>
            <div class="text-center py-12 text-gray-400">
                <span class="text-4xl block mb-2">📋</span>
                <p class="text-sm">No resolved transactions found in history.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Resolved</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Respective Account</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ref No</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">By User</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 text-sm">
                        <?php foreach ($resolvedTransactions as $tx): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 whitespace-nowrap text-gray-500">
                                    <?= formatDate($tx['resolved_at']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">
                                    <?= e($tx['customer_name']) ?> 
                                    <span class="text-xs text-gray-400 font-mono">(<?= e($tx['customer_code']) ?>)</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                                    <?= e($tx['account_name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-bold text-green-600">
                                    <?= formatMoney($tx['amount']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-mono text-xs text-gray-500">
                                    <?= e($tx['reference_no'] ?: 'N/A') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-500">
                                    <?= e($tx['user_name'] ?? 'System') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="<?= BASE_URL ?>/suspense/edit/<?= $tx['id'] ?>" class="text-blue-600 hover:underline text-xs">✏️ Edit</a>
                                        <a href="<?= BASE_URL ?>/suspense/delete/<?= $tx['id'] ?>" 
                                           onclick="return confirm('Are you sure you want to delete this resolved transaction? This will revert customer balance, financial account balance, and delete all resolution logs.')" 
                                           class="text-red-600 hover:underline text-xs">🗑️ Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal 1: Record Unknown Deposit -->
<div id="recordModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 transition-opacity">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 overflow-hidden border border-gray-100">
        <div class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white px-6 py-4 flex justify-between items-center">
            <h3 class="font-bold text-lg flex items-center gap-2">
                <span>🕵️ Record Unknown Deposit</span>
            </h3>
            <button onclick="closeRecordModal()" class="text-white hover:text-gray-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <form method="POST" action="<?= BASE_URL ?>/suspense/store" class="p-6">
            <?= csrfField() ?>

            <!-- Amount -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Amount <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <span class="absolute left-3 top-2.5 text-gray-500 font-medium"><?= CURRENCY_HOLDER ?></span>
                    <input type="number" name="amount" min="0.01" step="0.01" required
                        placeholder="0.00"
                        class="w-full pl-14 pr-4 py-2 border border-gray-300 rounded-lg text-lg font-bold focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
            </div>

            <!-- Received in Account -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Received in Account <span class="text-red-500">*</span>
                </label>
                <select name="account_id" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">-- Select Financial Account --</option>
                    <?php foreach ($accounts as $a): ?>
                        <option value="<?= $a['id'] ?>">
                            <?= e($a['name']) ?> (<?= ucfirst(str_replace('_', ' ', $a['type'])) ?><?= $a['provider'] ? ' · ' . e($a['provider']) : '' ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-400 mt-1">Select the Bank or MoMo account where this payment was received.</p>
            </div>

            <!-- Reference ID -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Reference No / Tx ID (Optional)
                </label>
                <input type="text" name="reference_no" placeholder="e.g. MTN-TX-849202, GCB-REF-820"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <!-- Notes -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Description / Notes
                </label>
                <textarea name="notes" rows="3" placeholder="Describe where it came from (e.g. Bank SMS text, transfer name if visible, etc.)"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
            </div>

            <div class="flex justify-end gap-3 border-t pt-4">
                <button type="button" onclick="closeRecordModal()"
                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg text-sm transition">
                    Cancel
                </button>
                <button type="submit"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg text-sm shadow-sm transition">
                    ✓ Save Deposit
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Resolve & Assign to Customer -->
<div id="resolveModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 transition-opacity">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 overflow-hidden border border-gray-100">
        <div class="bg-gradient-to-r from-green-600 to-emerald-700 text-white px-6 py-4 flex justify-between items-center">
            <h3 class="font-bold text-lg flex items-center gap-2">
                <span>🕵️ Resolve Suspense Deposit</span>
            </h3>
            <button onclick="closeResolveModal()" class="text-white hover:text-gray-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <form method="POST" action="<?= BASE_URL ?>/suspense/resolve" class="p-6">
            <?= csrfField() ?>
            <input type="hidden" name="transaction_id" id="resolve_transaction_id">

            <!-- Detail Banner -->
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-5 flex justify-between items-center text-green-800">
                <div>
                    <div class="text-xs uppercase font-bold tracking-wider opacity-75">Amount to resolve</div>
                    <div class="text-2xl font-black font-sans" id="resolve_amount_label">GHS 0.00</div>
                </div>
                <div class="text-right">
                    <div class="text-xs uppercase font-bold tracking-wider opacity-75">Reference No</div>
                    <div class="font-mono text-sm bg-green-100 px-2 py-0.5 rounded font-bold" id="resolve_ref_label">N/A</div>
                </div>
            </div>

            <!-- Customer Assignment -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Assign to Customer <span class="text-red-500">*</span>
                </label>
                <select name="customer_id" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                    <option value="">-- Select Customer --</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= e($c['full_name']) ?> (<?= e($c['customer_code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-400 mt-1">This will increase the customer's balance and auto-pay outstanding credit sales (oldest first).</p>
            </div>


            <!-- Resolution Notes -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Resolution Notes (Optional)
                </label>
                <textarea name="resolution_notes" rows="2" placeholder="e.g. Confirmed via bank statement matching client receipt"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"></textarea>
            </div>

            <div class="flex justify-end gap-3 border-t pt-4">
                <button type="button" onclick="closeResolveModal()"
                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg text-sm transition">
                    Cancel
                </button>
                <button type="submit"
                    class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg text-sm shadow-sm transition">
                    ✓ Complete Resolution
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openRecordModal() {
    document.getElementById('recordModal').classList.remove('hidden');
}

function closeRecordModal() {
    document.getElementById('recordModal').classList.add('hidden');
}

function openResolveModal(id, amount, referenceNo) {
    document.getElementById('resolve_transaction_id').value = id;
    document.getElementById('resolve_amount_label').innerText = '<?= CURRENCY_HOLDER ?> ' + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    document.getElementById('resolve_ref_label').innerText = referenceNo;
    document.getElementById('resolveModal').classList.remove('hidden');
}

function closeResolveModal() {
    document.getElementById('resolveModal').classList.add('hidden');
}
</script>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
