<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="max-w-xl mx-auto">
    <!-- Page Title -->
    <div class="mb-6">
        <a href="<?= BASE_URL ?>/financial-accounts" class="text-blue-600 hover:underline text-sm font-semibold flex items-center gap-1 mb-2">
            ← Back to Accounts
        </a>
        <h1 class="text-3xl font-bold text-gray-800">🔄 Transfer Funds</h1>
        <p class="text-gray-500 text-sm mt-1">Move money between your cash, bank, or mobile money accounts</p>
    </div>

    <!-- Flash Message -->
    <?= flashMessage() ?>

    <!-- Transfer Form -->
    <div class="bg-white rounded-lg shadow-sm p-6">
        <form action="<?= BASE_URL ?>/financial-accounts/transfer" method="POST" class="space-y-4">
            <?= csrfField() ?>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Source Account (From) *</label>
                <select name="from_account_id" id="from_account_id" required onchange="updateSourceBalance()"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">-- Select Source Account --</option>
                    <?php foreach ($accounts as $account): ?>
                        <option value="<?= $account['id'] ?>" data-balance="<?= $account['balance'] ?>">
                            <?= e($account['name']) ?> (Balance: <?= formatMoney($account['balance']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="text-xs text-gray-500 mt-1" id="source_balance_text"></div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Destination Account (To) *</label>
                <select name="to_account_id" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">-- Select Destination Account --</option>
                    <?php foreach ($accounts as $account): ?>
                        <option value="<?= $account['id'] ?>">
                            <?= e($account['name']) ?> (Balance: <?= formatMoney($account['balance']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Amount to Transfer (GHS) *</label>
                <input type="number" step="0.01" min="0.01" name="amount" required placeholder="0.00"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent font-semibold text-lg">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Transfer Charges / Fees (GHS)</label>
                <input type="number" step="0.01" min="0.00" name="charges" value="0.00"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent font-medium">
                <span class="text-xs text-gray-400">e.g. E-Levy, bank transfer fee, mobile money cashout charges.</span>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Notes / Reference</label>
                <textarea name="notes" placeholder="Reason for transfer, bank reference number, transaction ID, etc." rows="3"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <a href="<?= BASE_URL ?>/financial-accounts"
                    class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-2 px-6 rounded-lg text-sm transition">
                    Cancel
                </a>
                <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-6 rounded-lg text-sm transition shadow-sm">
                    Confirm & Move Funds
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function updateSourceBalance() {
        const select = document.getElementById('from_account_id');
        const selectedOption = select.options[select.selectedIndex];
        const balanceText = document.getElementById('source_balance_text');
        
        if (selectedOption.value) {
            const balance = parseFloat(selectedOption.getAttribute('data-balance'));
            balanceText.innerText = "Available Balance: GHS " + balance.toFixed(2);
            balanceText.classList.remove('text-red-500');
        } else {
            balanceText.innerText = "";
        }
    }
</script>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
