<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="max-w-2xl mx-auto">
    <div class="mb-5 text-sm">
        <a href="<?= BASE_URL ?>/customers" class="text-blue-600 hover:underline">← Back to Customers</a>
    </div>

    <div class="bg-white rounded-lg shadow p-6 mb-5">
        <h1 class="text-2xl font-bold text-gray-800"><?= e($pageTitle) ?></h1>
        <p class="text-gray-500 mt-1 text-sm">Fill in the details below to register a new customer</p>
    </div>

    <?= flashMessage() ?>

    <form method="POST" action="<?= BASE_URL ?>/customers/create"
        class="bg-white rounded-lg shadow p-6">
        <?= csrfField() ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

            <!-- Full Name -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Full Name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="full_name"
                    value="<?= e(old('full_name')) ?>"
                    required autofocus
                    placeholder="e.g. Kwame Mensah"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <!-- Customer Code -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Customer Code
                    <span class="text-gray-400 font-normal">(auto-generated if empty)</span>
                </label>
                <input type="text" name="customer_code"
                    value="<?= e(old('customer_code')) ?>"
                    placeholder="e.g. CUST-2024-0001"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <!-- Phone -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Phone Number <span class="text-red-500">*</span>
                </label>
                <input type="tel" name="phone"
                    value="<?= e(old('phone')) ?>"
                    required placeholder="e.g. 0241234567"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <!-- Email -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Email <span class="text-gray-400 font-normal">(Optional)</span>
                </label>
                <input type="email" name="email"
                    value="<?= e(old('email')) ?>"
                    placeholder="e.g. kwame@email.com"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <!-- Credit Limit -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Credit Limit
                </label>
                <div class="relative">
                    <span class="absolute left-3 top-2.5 text-gray-500 font-medium"><?= CURRENCY_HOLDER ?></span>
                    <input type="number" name="credit_limit"
                        value="<?= e(old('credit_limit', '0')) ?>"
                        min="0" step="0.01"
                        class="w-full pl-14 pr-4 py-2.5 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <p class="text-xs text-gray-400 mt-1">
                    Max amount this customer can owe. Set 0 for cash-only customers.
                </p>
            </div>

            <!-- Address -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Address <span class="text-gray-400 font-normal">(Optional)</span>
                </label>
                <textarea name="address" rows="2"
                    placeholder="Street, City..."
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                                 focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"><?= e(old('address')) ?></textarea>
            </div>

        </div>

        <!-- Actions -->
        <div class="flex justify-between mt-6 pt-5 border-t">
            <a href="<?= BASE_URL ?>/customers"
                class="px-5 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition">
                Cancel
            </a>
            <button type="submit"
                class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg shadow transition">
                Save Customer
            </button>
        </div>
    </form>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>