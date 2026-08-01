<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="max-w-4xl mx-auto">

    <!-- Breadcrumb -->
    <div class="mb-6">
        <a href="<?= BASE_URL ?>/products" class="text-blue-600 hover:underline">← Back to Products</a>
    </div>

    <!-- Page Header -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
        <h1 class="text-3xl font-bold text-gray-800"><?= e($pageTitle) ?></h1>
        <p class="text-gray-600 mt-2">Add a new product to your inventory</p>
    </div>

    <!-- Flash Messages -->
    <?= flashMessage() ?>

    <!-- Form -->
    <form method="POST" action="<?= BASE_URL ?>/products/create" class="bg-white rounded-lg shadow-lg p-6" x-data="productForm()">

        <?= csrfField() ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <!-- SKU -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    SKU (Stock Keeping Unit) <span class="text-red-500">*</span>
                </label>
                <div class="flex gap-2">
                    <input
                        type="text"
                        name="sku"
                        value="<?= e(old('sku')) ?>"
                        required
                        class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="e.g., PROD-001">
                    <button
                        type="button"
                        @click="generateSKU()"
                        class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition">
                        Generate
                    </button>
                </div>
                <p class="text-xs text-gray-500 mt-1">Unique identifier for this product</p>
            </div>

            <!-- Barcode -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Barcode (Optional)
                </label>
                <input
                    type="text"
                    name="barcode"
                    value="<?= e(old('barcode')) ?>"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="e.g., 1234567890123">
                <p class="text-xs text-gray-500 mt-1">For barcode scanner integration</p>
            </div>

            <!-- Product Name -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Product Name <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    name="name"
                    value="<?= e(old('name')) ?>"
                    required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="e.g., Coca Cola 500ml">
            </div>

            <!-- Description -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Description (Optional)
                </label>
                <textarea
                    name="description"
                    rows="3"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="Product details, specifications, etc."><?= e(old('description')) ?></textarea>
            </div>

            <!-- Category -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Category<span class="text-red-500">*</span>
                </label>
                <select
                    name="category_id"
                    required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">-- Select Category --</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>" <?= old('category_id') == $category['id'] ? 'selected' : '' ?>>
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Supplier -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Main Supplier <span class="text-red-500">*</span></label>
                <select name="supplier_id"
                    required
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
                    Unit of Measure
                </label>
                <select
                    name="unit"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="pcs" <?= old('unit') == 'pcs' ? 'selected' : '' ?>>Pieces</option>
                    <option value="bag" <?= old('unit') == 'bag' ? 'selected' : '' ?>>Bag</option>
                    <option value="box" <?= old('unit') == 'box' ? 'selected' : '' ?>>Box</option>
                    <option value="bottle" <?= old('unit') == 'bottle' ? 'selected' : '' ?>>Bottle</option>
                    <option value="kg" <?= old('unit') == 'kg' ? 'selected' : '' ?>>Kilograms</option>
                    <option value="liter" <?= old('unit') == 'liter' ? 'selected' : '' ?>>Liters</option>
                    <option value="pack" <?= old('unit') == 'pack' ? 'selected' : '' ?>>Pack</option>
                </select>
            </div>

            <!-- Cost Price -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Cost Price (What you paid)
                </label>
                <div class="relative">
                    <span class="absolute left-3 top-2.5 text-gray-500"><?= CURRENCY_HOLDER ?></span>
                    <input
                        type="number"
                        name="cost_price"
                        value="<?= e(old('cost_price', '0')) ?>"
                        step="0.01"
                        min="0"
                        x-model="costPrice"
                        @input="calculateProfit()"
                        class="w-full pl-12 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="0.00">
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
                        value="<?= e(old('selling_price', '0')) ?>"
                        step="0.01"
                        min="0.01"
                        required
                        x-model="sellingPrice"
                        @input="calculateProfit()"
                        class="w-full pl-12 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="0.00">
                </div>

                <!-- Profit Margin Indicator -->
                <div class="mt-2 text-sm" x-show="profitMargin !== null">
                    <span class="text-gray-600">Profit Margin: </span>
                    <span :class="profitMargin >= 0 ? 'text-green-600 font-semibold' : 'text-red-600 font-semibold'" x-text="profitMargin + '%'"></span>
                </div>
            </div>

            <!-- Initial Stock -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Initial Stock Quantity
                </label>
                <input
                    type="number"
                    name="current_stock"
                    value="<?= e(old('current_stock', '0')) ?>"
                    min="0"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="0">
                <p class="text-xs text-gray-500 mt-1">Opening stock (can be updated later)</p>
            </div>

            <!-- Reorder Level -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Reorder Level (Low Stock Alert)
                </label>
                <input
                    type="number"
                    name="reorder_level"
                    value="<?= e(old('reorder_level', '10')) ?>"
                    min="0"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="10">
                <p class="text-xs text-gray-500 mt-1">Alert when stock falls below this level</p>
            </div>

        </div>

        <!-- Form Actions -->
        <div class="flex justify-end gap-4 mt-8 pt-6 border-t">
            <a href="<?= BASE_URL ?>/products" class="px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition">
                Cancel
            </a>
            <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">
                Save Product
            </button>
        </div>

    </form>

</div>

<script>
    function productForm() {
        return {
            costPrice: <?= old('cost_price', 0) ?>,
            sellingPrice: <?= old('selling_price', 0) ?>,
            profitMargin: null,

            generateSKU() {
                const timestamp = Date.now().toString().slice(-6);
                const random = Math.random().toString(36).substring(2, 5).toUpperCase();
                document.querySelector('input[name="sku"]').value = `PROD-${timestamp}-${random}`;
            },

            calculateProfit() {
                const cost = parseFloat(this.costPrice) || 0;
                const sell = parseFloat(this.sellingPrice) || 0;

                if (cost > 0 && sell > 0) {
                    this.profitMargin = (((sell - cost) / cost) * 100).toFixed(2);
                } else {
                    this.profitMargin = null;
                }
            }
        }
    }
</script>
<?php include APP_PATH . '/views/layout/footer.php'; ?>