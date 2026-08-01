<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="max-w-xl mx-auto">
    <!-- Page Title -->
    <div class="mb-6">
        <a href="<?= BASE_URL ?>/expenses" class="text-blue-600 hover:underline text-sm font-semibold flex items-center gap-1 mb-2">
            ← Back to Expenses
        </a>
        <h1 class="text-3xl font-bold text-gray-800">💸 Record Expense</h1>
        <p class="text-gray-500 text-sm mt-1">Deduct operating expenses directly from cash, MoMo, or bank accounts</p>
    </div>

    <!-- Flash Message -->
    <?= flashMessage() ?>

    <!-- Expense Recording Form -->
    <div class="bg-white rounded-lg shadow-sm p-6">
        <form action="<?= BASE_URL ?>/expenses/create" method="POST" class="space-y-4">
            <?= csrfField() ?>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Expense Date *</label>
                    <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required
                        class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Pay From Account *</label>
                    <select name="account_id" id="account_id" required onchange="updateAccountBalance()"
                        class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">-- Select Account --</option>
                        <?php foreach ($accounts as $account): ?>
                            <option value="<?= $account['id'] ?>" data-balance="<?= $account['balance'] ?>">
                                <?= e($account['name']) ?> (Balance: <?= formatMoney($account['balance']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="text-xs text-gray-500 mt-1" id="account_balance_text"></div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Category *</label>
                    <select name="category" id="category_select" onchange="toggleNewCategory()"
                        class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">-- Select or Create New --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= e($cat['category']) ?>"><?= e($cat['category']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">New Category</label>
                    <input type="text" name="new_category" id="new_category_input" placeholder="Enter new category"
                        class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Description / Particulars *</label>
                <input type="text" name="description" required placeholder="e.g. Office internet subscription, Electricity bill"
                    class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Net Amount (GHS) *</label>
                    <input type="number" step="0.01" min="0.01" name="amount" required placeholder="0.00"
                        class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 font-semibold text-lg">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Transaction Charges (GHS)</label>
                    <input type="number" step="0.01" min="0.00" name="charges" value="0.00"
                        class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 font-semibold">
                    <span class="text-[10px] text-gray-400">MoMo fee, bank fee, E-Levy, etc.</span>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Notes</label>
                <textarea name="notes" placeholder="Additional details (e.g. receipt reference, invoice number)" rows="3"
                    class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <a href="<?= BASE_URL ?>/expenses"
                    class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-2 px-6 rounded-lg text-sm transition">
                    Cancel
                </a>
                <button type="submit"
                    class="bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 px-6 rounded-lg text-sm transition shadow-sm">
                    Record & Pay Expense
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function updateAccountBalance() {
        const select = document.getElementById('account_id');
        const selectedOption = select.options[select.selectedIndex];
        const balanceText = document.getElementById('account_balance_text');
        
        if (selectedOption.value) {
            const balance = parseFloat(selectedOption.getAttribute('data-balance'));
            balanceText.innerText = "Available Balance: GHS " + balance.toFixed(2);
        } else {
            balanceText.innerText = "";
        }
    }

    function toggleNewCategory() {
        const select = document.getElementById('category_select');
        const input = document.getElementById('new_category_input');
        if (select.value !== "") {
            input.disabled = true;
            input.value = "";
            input.classList.add('bg-gray-50');
        } else {
            input.disabled = false;
            input.classList.remove('bg-gray-50');
        }
    }
</script>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
