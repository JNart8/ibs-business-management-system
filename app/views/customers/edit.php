<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="max-w-2xl mx-auto">
    <div class="mb-5 text-sm">
        <a href="<?= BASE_URL ?>/customers/view/<?= $customer['id'] ?>" class="text-blue-600 hover:underline">
            ← Back to <?= e($customer['full_name']) ?>
        </a>
    </div>

    <div class="bg-white rounded-lg shadow p-6 mb-5">
        <h1 class="text-2xl font-bold text-gray-800"><?= e($pageTitle) ?></h1>
        <p class="text-gray-500 text-sm mt-1">Editing: <strong><?= e($customer['full_name']) ?></strong></p>
    </div>

    <?= flashMessage() ?>

    <form method="POST" action="<?= BASE_URL ?>/customers/edit/<?= $customer['id'] ?>"
        class="bg-white rounded-lg shadow p-6">
        <?= csrfField() ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Full Name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="full_name"
                    value="<?= e($customer['full_name']) ?>" required
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Customer Code</label>
                <input type="text" value="<?= e($customer['customer_code']) ?>" readonly
                    class="w-full px-4 py-2.5 bg-gray-100 border border-gray-300 rounded-lg text-gray-500">
                <p class="text-xs text-gray-400 mt-1">Code cannot be changed</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Phone <span class="text-red-500">*</span>
                </label>
                <input type="tel" name="phone"
                    value="<?= e($customer['phone']) ?>" required
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email"
                    value="<?= e($customer['email'] ?? '') ?>"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                <textarea name="address" rows="2"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                                 focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"><?= e($customer['address'] ?? '') ?></textarea>
            </div>

            <div class="md:col-span-2">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1"
                        <?= $customer['is_active'] ? 'checked' : '' ?>
                        class="w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <div>
                        <span class="text-sm font-medium text-gray-700">Active Customer</span>
                        <p class="text-xs text-gray-400">Inactive customers won't appear in POS</p>
                    </div>
                </label>
            </div>

        </div>

        <div class="flex justify-between mt-6 pt-5 border-t">
            <a href="<?= BASE_URL ?>/customers/view/<?= $customer['id'] ?>"
                class="px-5 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition">
                Cancel
            </a>
            <button type="submit"
                class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg shadow transition">
                Update Customer
            </button>
        </div>
    </form>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>