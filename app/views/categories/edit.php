<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="max-w-4xl mx-auto">

    <!-- Breadcrumb -->
    <div class="mb-6 text-sm">
        <a href="<?= BASE_URL ?>/categories" class="text-blue-600 hover:underline">← Back to Categories</a>
    </div>

    <?= flashMessage() ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        <!-- LEFT: Edit Form (2/3 width) -->
        <div class="md:col-span-2">
            <div class="bg-white rounded-lg shadow p-6">
                <h1 class="text-2xl font-bold text-gray-800 mb-1"><?= e($pageTitle) ?></h1>
                <p class="text-gray-500 text-sm mb-6">Editing: <strong><?= e($category['name']) ?></strong></p>

                <form method="POST"
                    action="<?= BASE_URL ?>/categories/edit/<?= $category['id'] ?>"
                    x-data="{ name: '<?= e($category['name']) ?>', charCount: <?= strlen($category['name']) ?> }">

                    <?= csrfField() ?>

                    <!-- Name -->
                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Category Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="name"
                            x-model="name"
                            @input="charCount = name.length"
                            maxlength="100"
                            required
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                                   focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <div class="flex justify-end mt-1">
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
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                                   focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"><?= e($category['description'] ?? '') ?></textarea>
                    </div>

                    <!-- Active toggle -->
                    <div class="mb-6">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                name="is_active"
                                value="1"
                                <?= $category['is_active'] ? 'checked' : '' ?>
                                class="w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <div>
                                <span class="text-sm font-medium text-gray-700">Active</span>
                                <p class="text-xs text-gray-400">Inactive categories won't appear in product forms</p>
                            </div>
                        </label>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-between pt-4 border-t">
                        <a href="<?= BASE_URL ?>/categories"
                            class="px-5 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition">
                            Cancel
                        </a>
                        <button type="submit"
                            class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg shadow transition">
                            Update Category
                        </button>
                    </div>

                </form>
            </div>
        </div>

        <!-- RIGHT: Products in this category (1/3 width) -->
        <div class="md:col-span-1">
            <div class="bg-white rounded-lg shadow p-5">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="font-bold text-gray-700">Products in Category</h2>
                    <span class="bg-blue-100 text-blue-700 text-xs font-semibold px-2 py-1 rounded-full">
                        <?= count($products) ?>
                    </span>
                </div>

                <?php if (empty($products)): ?>
                    <div class="text-center py-8 text-gray-400">
                        <div class="text-3xl mb-2">📦</div>
                        <p class="text-sm">No products yet</p>
                        <a href="<?= BASE_URL ?>/products/create"
                            class="text-blue-600 text-xs hover:underline mt-1 inline-block">
                            Add a product
                        </a>
                    </div>
                <?php else: ?>
                    <div class="space-y-2 max-h-80 overflow-y-auto pr-1">
                        <?php foreach ($products as $p): ?>
                            <div class="flex items-center justify-between p-2.5 rounded-lg
                                        <?= $p['is_active'] ? 'bg-gray-50 hover:bg-gray-100' : 'bg-red-50 opacity-60' ?>
                                        transition">
                                <div class="min-w-0">
                                    <div class="text-sm font-medium text-gray-800 truncate">
                                        <?= e($p['name']) ?>
                                    </div>
                                    <div class="text-xs text-gray-400"><?= e($p['sku']) ?></div>
                                </div>
                                <div class="text-right ml-2 flex-shrink-0">
                                    <div class="text-sm font-semibold text-gray-700">
                                        <?= $p['current_stock'] ?> pcs
                                    </div>
                                    <div class="text-xs text-gray-400">
                                        <?= formatMoney($p['selling_price']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <a href="<?= BASE_URL ?>/products?category=<?= $category['id'] ?>"
                        class="block text-center text-blue-600 text-sm hover:underline mt-4">
                        View all in Products →
                    </a>
                <?php endif; ?>
            </div>

            <!-- Danger Zone -->
            <div class="bg-red-50 border border-red-200 rounded-lg p-5 mt-4">
                <h3 class="font-semibold text-red-700 mb-2">Danger Zone</h3>
                <p class="text-xs text-red-500 mb-3">
                    <?php if (count($products) > 0): ?>
                        Deleting will move <?= count($products) ?> product(s) to uncategorized.
                    <?php else: ?>
                        This category has no products. Safe to delete.
                    <?php endif; ?>
                </p>
                <a href="<?= BASE_URL ?>/categories/delete/<?= $category['id'] ?>"
                    onclick="return confirmDelete('Delete \'<?= e($category['name']) ?>\'?\n<?= count($products) > 0 ? count($products) . ' product(s) will be moved to uncategorized.' : 'This cannot be undone.' ?>')"
                    class="block text-center bg-red-500 hover:bg-red-600 text-white text-sm px-4 py-2 rounded-lg transition">
                    Delete Category
                </a>
            </div>
        </div>

    </div>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>