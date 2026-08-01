<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="max-w-2xl mx-auto">

    <!-- Breadcrumb -->
    <div class="mb-6 text-sm">
        <a href="<?= BASE_URL ?>/categories" class="text-blue-600 hover:underline">← Back to Categories</a>
    </div>

    <!-- Page Header -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h1 class="text-2xl font-bold text-gray-800"><?= e($pageTitle) ?></h1>
        <p class="text-gray-500 mt-1">Group your products into logical categories</p>
    </div>

    <?= flashMessage() ?>

    <!-- Form -->
    <form method="POST" action="<?= BASE_URL ?>/categories/create"
        class="bg-white rounded-lg shadow p-6"
        x-data="{ name: '<?= e(old('name')) ?>', charCount: <?= strlen(old('name')) ?> }">

        <?= csrfField() ?>

        <!-- Category Name -->
        <div class="mb-5">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Category Name <span class="text-red-500">*</span>
            </label>
            <input
                type="text"
                name="name"
                x-model="name"
                @input="charCount = name.length"
                value="<?= e(old('name')) ?>"
                maxlength="100"
                required
                autofocus
                placeholder="e.g. Beverages, Electronics, Snacks..."
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                       focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <div class="flex justify-between mt-1">
                <span class="text-xs text-gray-400">Use a clear, descriptive name</span>
                <span class="text-xs" :class="charCount > 80 ? 'text-red-500' : 'text-gray-400'"
                    x-text="charCount + '/100'"></span>
            </div>
        </div>

        <!-- Description -->
        <div class="mb-5">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Description <span class="text-gray-400 font-normal">(Optional)</span>
            </label>
            <textarea
                name="description"
                rows="3"
                placeholder="What types of products belong in this category?"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                       focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"><?= e(old('description')) ?></textarea>
        </div>

        <!-- Active toggle -->
        <div class="mb-6">
            <label class="flex items-center gap-3 cursor-pointer">
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    checked
                    class="w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                <div>
                    <span class="text-sm font-medium text-gray-700">Active</span>
                    <p class="text-xs text-gray-400">Inactive categories won't appear in product forms</p>
                </div>
            </label>
        </div>

        <!-- Preview card -->
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6" x-show="name.length > 0" x-cloak>
            <p class="text-xs text-gray-400 uppercase font-semibold mb-2">Preview</p>
            <div class="flex items-center gap-3">
                <div class="bg-blue-100 p-2 rounded-lg text-blue-600 text-xl">🗂️</div>
                <div>
                    <div class="font-semibold text-gray-800" x-text="name"></div>
                    <div class="text-xs text-gray-400">0 products</div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex justify-between items-center pt-4 border-t">
            <a href="<?= BASE_URL ?>/categories"
                class="px-5 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition">
                Cancel
            </a>
            <button type="submit"
                class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg shadow transition">
                Save Category
            </button>
        </div>

    </form>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>