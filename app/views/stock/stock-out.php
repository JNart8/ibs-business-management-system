<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="max-w-2xl mx-auto">
    <div class="mb-5 text-sm">
        <a href="<?= BASE_URL ?>/stock" class="text-blue-600 hover:underline">← Back to Stock</a>
    </div>

    <div class="bg-red-50 border border-red-200 rounded-lg p-5 mb-5 flex items-center gap-4">
        <div class="text-4xl">📤</div>
        <div>
            <h1 class="text-2xl font-bold text-gray-800"><?= e($pageTitle) ?></h1>
            <p class="text-gray-500 text-sm">Remove stock manually (damaged, expired, lost, etc.)</p>
        </div>
    </div>

    <?= flashMessage() ?>

    <form method="POST" action="<?= BASE_URL ?>/stock/out"
        class="bg-white rounded-lg shadow p-6"
        x-data="stockOutForm()">
        <?= csrfField() ?>

        <!-- Product Search -->
        <div class="mb-5">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Product <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <input type="text" placeholder="Type product name or SKU..."
                    x-model="searchQuery"
                    @input.debounce.300ms="searchProducts()"
                    @focus="showDropdown = true"
                    autocomplete="off"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-red-500 focus:border-transparent">
                <input type="hidden" name="product_id" x-model="selectedId">

                <div x-show="showDropdown && results.length > 0" x-cloak
                    @click.outside="showDropdown = false"
                    class="absolute z-10 w-full bg-white border border-gray-200 rounded-lg shadow-lg mt-1 max-h-56 overflow-y-auto">
                    <template x-for="p in results" :key="p.id">
                        <div @click="selectProduct(p)"
                            class="px-4 py-3 hover:bg-red-50 cursor-pointer border-b last:border-0">
                            <div class="font-medium text-gray-800" x-text="p.name"></div>
                            <div class="text-xs text-gray-400">
                                <span x-text="'SKU: ' + p.sku"></span> &bull;
                                <span class="font-semibold" x-text="'Available: ' + p.current_stock + ' ' + p.unit"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Selected Product Info -->
        <div x-show="selectedProduct" x-cloak
            class="bg-red-50 border border-red-200 rounded-lg p-4 mb-5">
            <div class="flex justify-between items-center">
                <div>
                    <div class="font-semibold text-gray-800" x-text="selectedProduct?.name"></div>
                    <div class="text-sm text-gray-500">
                        Available Stock:
                        <span class="font-bold text-red-600" x-text="selectedProduct?.current_stock + ' ' + selectedProduct?.unit"></span>
                    </div>
                </div>
                <button type="button" @click="clearProduct()"
                    class="text-gray-400 hover:text-red-500 text-xl">&times;</button>
            </div>
        </div>

        <!-- Quantity -->
        <div class="mb-5">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Quantity to Remove <span class="text-red-500">*</span>
            </label>
            <input type="number" name="quantity"
                min="1" required
                value="<?= e(old('quantity', '1')) ?>"
                x-model="quantity"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-lg font-bold
                          focus:ring-2 focus:ring-red-500 focus:border-transparent">

            <!-- Warning if quantity > stock -->
            <p class="text-xs text-red-500 mt-1"
                x-show="selectedProduct && parseInt(quantity) > parseInt(selectedProduct?.current_stock)" x-cloak>
                ⚠️ Quantity exceeds available stock!
            </p>
        </div>

        <!-- Stock after preview -->
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 mb-5"
            x-show="selectedProduct && quantity > 0" x-cloak>
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Stock After Removal:</span>
                <span class="font-bold"
                    :class="((selectedProduct?.current_stock || 0) - (parseInt(quantity) || 0)) < 0 ? 'text-red-600' : 'text-gray-800'"
                    x-text="Math.max(0, (parseInt(selectedProduct?.current_stock) || 0) - (parseInt(quantity) || 0)) + ' ' + (selectedProduct?.unit || '')">
                </span>
            </div>
        </div>

        <!-- Reason -->
        <div class="mb-5">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Reason <span class="text-red-500">*</span>
            </label>
            <div class="grid grid-cols-2 gap-2 mb-3">
                <?php
                $reasons = ['Damaged', 'Expired', 'Lost / Stolen', 'Sample / Giveaway', 'Return to Supplier', 'Other'];
                ?>
                <?php foreach ($reasons as $r): ?>
                    <label class="flex items-center gap-2 p-2.5 border rounded-lg cursor-pointer
                                  hover:bg-gray-50 has-[:checked]:border-red-400 has-[:checked]:bg-red-50 text-sm">
                        <input type="radio" name="reason" value="<?= $r ?>"
                            <?= old('reason') === $r ? 'checked' : '' ?>
                            class="text-red-500">
                        <?= $r ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Notes -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Additional Notes</label>
            <textarea name="notes" rows="2"
                placeholder="Any additional details..."
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                             focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"><?= e(old('notes')) ?></textarea>
        </div>

        <div class="flex justify-between">
            <a href="<?= BASE_URL ?>/stock"
                class="px-5 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition">
                Cancel
            </a>
            <button type="submit" :disabled="!selectedId"
                class="px-6 py-2.5 bg-red-500 hover:bg-red-600 disabled:bg-gray-300
                           disabled:cursor-not-allowed text-white rounded-lg shadow transition">
                ✓ Confirm Stock Out
            </button>
        </div>
    </form>
</div>

<script>
    function stockOutForm() {
        return {
            searchQuery: '<?= $preProduct ? e($preProduct['name']) : '' ?>',
            selectedId: <?= $preProduct ? $preProduct['id'] : 'null' ?>,
            selectedProduct: <?= $preProduct ? json_encode($preProduct) : 'null' ?>,
            results: [],
            showDropdown: false,
            quantity: 1,
            searchProducts() {
                if (this.searchQuery.length < 1) {
                    this.results = [];
                    return;
                }
                fetch('<?= BASE_URL ?>/products/search?q=' + encodeURIComponent(this.searchQuery))
                    .then(r => r.json())
                    .then(data => {
                        this.results = data;
                        this.showDropdown = true;
                    });
            },
            selectProduct(p) {
                this.selectedProduct = p;
                this.selectedId = p.id;
                this.searchQuery = p.name;
                this.showDropdown = false;
            },
            clearProduct() {
                this.selectedProduct = null;
                this.selectedId = null;
                this.searchQuery = '';
            }
        }
    }
</script>

<?php include APP_PATH . '/views/layout/footer.php'; ?>