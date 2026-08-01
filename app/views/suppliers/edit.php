<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="max-w-2xl mx-auto">
    <div class="mb-5 text-sm">
        <a href="<?= BASE_URL ?>/suppliers/view/<?= $supplier['id'] ?>" class="text-blue-600 hover:underline">
            ← Back to <?= e($supplier['company_name']) ?>
        </a>
    </div>

    <div class="bg-white rounded-lg shadow p-6 mb-5">
        <h1 class="text-2xl font-bold text-gray-800"><?= e($pageTitle) ?></h1>
        <p class="text-gray-500 text-sm mt-1">Editing: <strong><?= e($supplier['company_name']) ?></strong></p>
    </div>

    <?= flashMessage() ?>

    <form method="POST" action="<?= BASE_URL ?>/suppliers/edit/<?= $supplier['id'] ?>"
        class="bg-white rounded-lg shadow p-6">
        <?= csrfField() ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Company Name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="company_name"
                    value="<?= e($supplier['company_name']) ?>" required
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Supplier Code</label>
                <input type="text" value="<?= e($supplier['supplier_code']) ?>" readonly
                    class="w-full px-4 py-2.5 bg-gray-100 border border-gray-300 rounded-lg text-gray-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Contact Person</label>
                <input type="text" name="contact_name"
                    value="<?= e($supplier['contact_name'] ?? '') ?>"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Phone <span class="text-red-500">*</span>
                </label>
                <input type="tel" name="phone"
                    value="<?= e($supplier['phone']) ?>" required
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Alt Phone</label>
                <input type="tel" name="phone_alt"
                    value="<?= e($supplier['phone_alt'] ?? '') ?>"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email"
                    value="<?= e($supplier['email'] ?? '') ?>"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                <input type="text" name="city"
                    value="<?= e($supplier['city'] ?? '') ?>"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Credit Limit</label>
                <input type="number" name="credit_limit" step="0.01" min="0"
                    value="<?= e($supplier['credit_limit'] ?? '0.00') ?>"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                <textarea name="address" rows="2"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                                 focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"><?= e($supplier['address'] ?? '') ?></textarea>
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" rows="2"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                                 focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"><?= e($supplier['notes'] ?? '') ?></textarea>
            </div>

            <div class="md:col-span-2">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1"
                        <?= $supplier['is_active'] ? 'checked' : '' ?>
                        class="w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <div>
                        <span class="text-sm font-medium text-gray-700">Active Supplier</span>
                        <p class="text-xs text-gray-400">Inactive suppliers won't appear in stock forms</p>
                    </div>
                </label>
            </div>

        </div>

        <!-- Danger Zone -->
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mt-6">
            <h3 class="font-semibold text-red-700 text-sm mb-2">Danger Zone</h3>
            <a href="<?= BASE_URL ?>/suppliers/delete/<?= $supplier['id'] ?>"
                onclick="return confirmDelete('Delete supplier <?= e($supplier['company_name']) ?>?')"
                class="inline-block bg-red-500 hover:bg-red-600 text-white text-sm px-4 py-2 rounded-lg transition">
                Delete Supplier
            </a>
        </div>

        <div class="flex justify-between mt-6 pt-5 border-t">
            <a href="<?= BASE_URL ?>/suppliers/view/<?= $supplier['id'] ?>"
                class="px-5 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition">
                Cancel
            </a>
            <button type="submit"
                class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg shadow transition">
                Update Supplier
            </button>
        </div>
    </form>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>