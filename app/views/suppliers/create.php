<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="max-w-2xl mx-auto">
    <div class="mb-5 text-sm">
        <a href="<?= BASE_URL ?>/suppliers" class="text-blue-600 hover:underline">← Back to Suppliers</a>
    </div>

    <div class="bg-white rounded-lg shadow p-6 mb-5">
        <h1 class="text-2xl font-bold text-gray-800"><?= e($pageTitle) ?></h1>
        <p class="text-gray-500 text-sm mt-1">Register a new supplier for your inventory</p>
    </div>

    <?= flashMessage() ?>

    <form method="POST" action="<?= BASE_URL ?>/suppliers/create"
        class="bg-white rounded-lg shadow p-6">
        <?= csrfField() ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

            <!-- Company Name -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Company / Business Name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="company_name"
                    value="<?= e(old('company_name')) ?>"
                    required autofocus
                    placeholder="e.g. Accra Foods Ltd"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <!-- Contact Person -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Contact Person</label>
                <input type="text" name="contact_name"
                    value="<?= e(old('contact_name')) ?>"
                    placeholder="e.g. Kofi Mensah"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <!-- Supplier Code -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Supplier Code <span class="text-gray-400 font-normal">(auto if empty)</span>
                </label>
                <input type="text" name="supplier_code"
                    value="<?= e(old('supplier_code')) ?>"
                    placeholder="e.g. SUP-2024-0001"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <!-- Phone -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Phone <span class="text-red-500">*</span>
                </label>
                <input type="tel" name="phone"
                    value="<?= e(old('phone')) ?>"
                    required placeholder="e.g. 0244000001"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <!-- Alt Phone -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Alt Phone</label>
                <input type="tel" name="phone_alt"
                    value="<?= e(old('phone_alt')) ?>"
                    placeholder="Secondary number"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <!-- Email -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email"
                    value="<?= e(old('email')) ?>"
                    placeholder="e.g. supplier@email.com"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <!-- City -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">City / Location</label>
                <input type="text" name="city"
                    value="<?= e(old('city')) ?>"
                    placeholder="e.g. Accra, Kumasi..."
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <!-- Credit Limit -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Credit Limit</label>
                <input type="number" name="credit_limit" step="0.01" min="0"
                    value="<?= e(old('credit_limit') ?: '0.00') ?>"
                    placeholder="e.g. 5000.00"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <!-- Address -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Full Address</label>
                <textarea name="address" rows="2"
                    placeholder="Street address..."
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                                 focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"><?= e(old('address')) ?></textarea>
            </div>

            <!-- Notes -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" rows="2"
                    placeholder="Payment terms, delivery days, special instructions..."
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                                 focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"><?= e(old('notes')) ?></textarea>
            </div>

        </div>

        <div class="flex justify-between mt-6 pt-5 border-t">
            <a href="<?= BASE_URL ?>/suppliers"
                class="px-5 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition">
                Cancel
            </a>
            <button type="submit"
                class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg shadow transition">
                Save Supplier
            </button>
        </div>
    </form>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>