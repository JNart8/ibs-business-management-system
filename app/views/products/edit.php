<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="max-w-4xl mx-auto">

    <!-- Breadcrumb -->
    <div class="mb-6">
        <a href="<?= BASE_URL ?>/products" class="text-blue-600 hover:underline">← Back to Products</a>
    </div>

    <!-- Page Header -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
        <h1 class="text-3xl font-bold text-gray-800"><?= e($pageTitle) ?></h1>
        <p class="text-gray-600 mt-2">Editing: <strong><?= e($product['name']) ?></strong></p>
    </div>

    <!-- Flash Messages -->
    <?= flashMessage() ?>

    <!-- Form -->
    <form method="POST" action="<?= BASE_URL ?>/products/edit/<?= $product['id'] ?>" class="bg-white rounded-lg shadow-lg p-6">

        <?= csrfField() ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <!-- SKU -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    SKU <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    name="sku"
                    value="<?= e($product['sku']) ?>"
                    required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <!-- Barcode -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Barcode
                </label>
                <input
                    type="text"
                    name="barcode"
                    value="<?= e($product['barcode'] ?? '') ?>"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <!-- Product Name -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Product Name <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    name="name"
                    value="<?= e($product['name']) ?>"
                    required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <!-- Description -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Description
                </label>
                <textarea
                    name="description"
                    rows="3"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"><?= e($product['description'] ?? '') ?></textarea>
            </div>

            <!-- Category -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Category
                </label>
                <select
                    name="category_id"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">-- No Category --</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>" <?= $product['category_id'] == $category['id'] ? 'selected' : '' ?>>
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Supplier -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Main Supplier</label>
                <select name="supplier_id"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                   focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">-- No Supplier --</option>
                    <?php foreach ($suppliers as $sup): ?>
                        <option value="<?= $sup['id'] ?>"
                            <?= (old('supplier_id') == $sup['id']
                                || (isset($product) && $product['supplier_id'] == $sup['id']))
                                ? 'selected' : '' ?>>
                            <?= e($sup['company_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Unit -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Unit
                </label>
                <select
                    name="unit"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="pcs" <?= $product['unit'] == 'pcs' ? 'selected' : '' ?>>Pieces</option>
                    <option value="bag" <?= old('unit') == 'bag' ? 'selected' : '' ?>>Bag</option>
                    <option value="box" <?= $product['unit'] == 'box' ? 'selected' : '' ?>>Box</option>
                    <option value="bottle" <?= $product['unit'] == 'bottle' ? 'selected' : '' ?>>Bottle</option>
                    <option value="kg" <?= $product['unit'] == 'kg' ? 'selected' : '' ?>>Kilograms</option>
                    <option value="liter" <?= $product['unit'] == 'liter' ? 'selected' : '' ?>>Liters</option>
                    <option value="pack" <?= $product['unit'] == 'pack' ? 'selected' : '' ?>>Pack</option>
                </select>
            </div>

            <!-- Cost Price -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Cost Price
                </label>
                <div class="relative">
                    <span class="absolute left-3 top-2.5 text-gray-500"><?= CURRENCY_HOLDER ?></span>
                    <input
                        type="number"
                        name="cost_price"
                        value="<?= $product['cost_price'] ?>"
                        step="0.01"
                        min="0"
                        class="w-full pl-12 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
            </div>

            <!-- Selling Price -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Selling Price <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <span class="absolute left-3 top-2.5 text-gray-500"><?= CURRENCY_HOLDER ?></span>
                    <input
                        type="number"
                        name="selling_price"
                        value="<?= $product['selling_price'] ?>"
                        step="0.01"
                        min="0.01"
                        required
                        class="w-full pl-12 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
            </div>

            <!-- Current Stock (Read Only) -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Current Stock
                </label>
                <input
                    type="text"
                    value="<?= $product['current_stock'] ?> <?= e($product['unit']) ?>"
                    readonly
                    class="w-full px-4 py-2 bg-gray-100 border border-gray-300 rounded-lg">
                <p class="text-xs text-gray-500 mt-1">Use Stock In/Out to change quantity</p>
            </div>

            <!-- Reorder Level -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Reorder Level
                </label>
                <input
                    type="number"
                    name="reorder_level"
                    value="<?= $product['reorder_level'] ?>"
                    min="0"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <!-- Active Status -->
            <div class="md:col-span-2">
                <label class="flex items-center">
                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        <?= $product['is_active'] ? 'checked' : '' ?>
                        class="w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <span class="ml-3 text-sm font-medium text-gray-700">
                        Product is active (visible in sales)
                    </span>
                </label>
            </div>

        </div>

        <!-- Form Actions -->
        <div class="flex justify-between items-center mt-8 pt-6 border-t">
            <a href="<?= BASE_URL ?>/products" class="px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition">
                Cancel
            </a>
            <div class="flex gap-4">
                <a href="<?= BASE_URL ?>/stock/in?product=<?= $product['id'] ?>" class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg transition">
                    Add Stock
                </a>
                <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">
                    Update Product
                </button>
            </div>
        </div>

    </form>

</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>