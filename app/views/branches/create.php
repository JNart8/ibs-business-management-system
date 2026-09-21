<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="max-w-lg mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">+ Add Branch</h1>
            <p class="text-gray-500 text-sm mt-1">Create a new location</p>
        </div>
    </div>

    <div class="mb-4">
        <a href="<?= BASE_URL ?>/branches"
            class="bg-gray-100 hover:bg-gray-200 text-gray-800 px-4 py-2.5 rounded-lg text-sm font-medium transition inline-block">
            ← Back to Branches
        </a>
    </div>

    <?= flashMessage() ?>

    <div class="bg-white rounded-xl shadow-sm border">
        <form action="<?= BASE_URL ?>/branches/create" method="POST" class="p-6 space-y-4">
            <?= csrfField() ?>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Branch Name *</label>
                <input type="text" name="name" required placeholder="e.g., Osu Branch, Warehouse"
                    value="<?= e(old('name', '')) ?>"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Address</label>
                <textarea name="address" rows="2"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"><?= e(old('address', '')) ?></textarea>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Phone</label>
                <input type="text" name="phone" value="<?= e(old('phone', '')) ?>"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t">
                <a href="<?= BASE_URL ?>/branches"
                    class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-2 px-4 rounded-lg text-sm transition">
                    Cancel
                </a>
                <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg text-sm transition">
                    Create Branch
                </button>
            </div>
        </form>
    </div>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
