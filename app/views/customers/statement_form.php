<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="mb-5 text-sm">
    <a href="<?= BASE_URL ?>/customers/view/<?= $customer['id'] ?>" class="text-blue-600 hover:underline">
        ← Back to <?= e($customer['full_name']) ?>
    </a>
</div>

<?= flashMessage() ?>

<!-- Statement Form -->
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-lg shadow p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-2">Customer Statement</h1>
        <p class="text-gray-500 mb-6">Generate a statement for <?= e($customer['full_name']) ?></p>

        <form method="POST" action="<?= BASE_URL ?>/customers/statement/<?= $customer['id'] ?>" target="_blank">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <!-- Customer Info Card -->
            <div class="bg-gray-50 rounded-lg p-4 mb-6">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500">Customer Code:</span>
                        <span class="font-medium text-gray-800"><?= e($customer['customer_code']) ?></span>
                    </div>
                    <div>
                        <span class="text-gray-500">Phone:</span>
                        <span class="font-medium text-gray-800"><?= e($customer['phone']) ?></span>
                    </div>
                    <div>
                        <span class="text-gray-500">Current Balance:</span>
                        <span class="font-semibold <?= $customer['current_balance'] < 0 ? 'text-red-600' : 'text-green-600' ?>">
                            <?= formatMoney(abs($customer['current_balance'])) ?>
                            <?= $customer['current_balance'] < 0 ? ' (Owes)' : '' ?>
                        </span>
                    </div>
                    <div>
                        <span class="text-gray-500">Credit Limit:</span>
                        <span class="font-medium text-gray-800"><?= formatMoney($customer['credit_limit']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Date Range -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Statement Period *</label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Start Date</label>
                        <input type="date" name="date_from" required
                            value="<?= date('Y-m-01') ?>"
                            max="<?= date('Y-m-d') ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">End Date</label>
                        <input type="date" name="date_to" required
                            value="<?= date('Y-m-d') ?>"
                            max="<?= date('Y-m-d') ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-2">
                    💡 Default: Current month (<?= date('M Y') ?>)
                </p>
            </div>

            <!-- Quick Period Buttons -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Quick Select</label>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                    <button type="button" onclick="setThisMonth()"
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50 transition">
                        This Month
                    </button>
                    <button type="button" onclick="setLastMonth()"
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50 transition">
                        Last Month
                    </button>
                    <button type="button" onclick="setLast3Months()"
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50 transition">
                        Last 3 Months
                    </button>
                    <button type="button" onclick="setThisYear()"
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50 transition">
                        This Year
                    </button>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="flex gap-3">
                <button type="submit"
                    class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition">
                    🖨️ Generate & Print Statement
                </button>
                <a href="<?= BASE_URL ?>/customers/view/<?= $customer['id'] ?>"
                    class="px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
    // Quick date setters
    const dateFrom = document.querySelector('input[name="date_from"]');
    const dateTo = document.querySelector('input[name="date_to"]');
    const today = new Date();

    function setThisMonth() {
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        dateFrom.value = firstDay.toISOString().split('T')[0];
        dateTo.value = today.toISOString().split('T')[0];
    }

    function setLastMonth() {
        const firstDay = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        const lastDay = new Date(today.getFullYear(), today.getMonth(), 0);
        dateFrom.value = firstDay.toISOString().split('T')[0];
        dateTo.value = lastDay.toISOString().split('T')[0];
    }

    function setLast3Months() {
        const firstDay = new Date(today.getFullYear(), today.getMonth() - 3, 1);
        dateFrom.value = firstDay.toISOString().split('T')[0];
        dateTo.value = today.toISOString().split('T')[0];
    }

    function setThisYear() {
        const firstDay = new Date(today.getFullYear(), 0, 1);
        dateFrom.value = firstDay.toISOString().split('T')[0];
        dateTo.value = today.toISOString().split('T')[0];
    }
</script>

<?php include APP_PATH . '/views/layout/footer.php'; ?>