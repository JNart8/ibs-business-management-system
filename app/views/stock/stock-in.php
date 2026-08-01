<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="max-w-2xl mx-auto">
    <div class="mb-5 text-sm">
        <a href="<?= BASE_URL ?>/stock" class="text-blue-600 hover:underline">← Back to Stock</a>
    </div>

    <div class="bg-green-50 border border-green-200 rounded-lg p-5 mb-5 flex items-center gap-4">
        <div class="text-4xl">📥</div>
        <div>
            <h1 class="text-2xl font-bold text-gray-800"><?= e($pageTitle) ?></h1>
            <p class="text-gray-500 text-sm">Add stock to a product (purchases, returns, etc.)</p>
        </div>
    </div>

    <?= flashMessage() ?>

    <form method="POST" action="<?= BASE_URL ?>/stock/in"
        class="bg-white rounded-lg shadow p-6"
        x-data="stockInForm()">
        <?= csrfField() ?>

        <!-- Product Search -->
        <div class="mb-5">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Product <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <input type="text"
                    id="product-search"
                    placeholder="Type product name, SKU or scan barcode..."
                    x-model="searchQuery"
                    @input.debounce.300ms="searchProducts()"
                    @focus="showDropdown = true"
                    autocomplete="off"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-green-500 focus:border-transparent">

                <!-- Hidden product_id -->
                <input type="hidden" name="product_id" x-model="selectedId">

                <!-- Dropdown Results -->
                <div x-show="showDropdown && productResults.length > 0" x-cloak
                    @click.outside="showDropdown = false"
                    class="absolute z-10 w-full bg-white border border-gray-200 rounded-lg
                            shadow-lg mt-1 max-h-56 overflow-y-auto">
                    <template x-for="p in productResults" :key="p.id">
                        <div @click="selectProduct(p)"
                            class="px-4 py-3 hover:bg-green-50 cursor-pointer border-b last:border-0">
                            <div class="font-medium text-gray-800" x-text="p.name"></div>
                            <div class="text-xs text-gray-400 flex gap-2">
                                <span x-text="'SKU: ' + p.sku"></span>
                                <span>·</span>
                                <span :class="p.current_stock <= 0 ? 'text-red-500 font-semibold' : ''"
                                    x-text="'Stock: ' + p.current_stock + ' ' + p.unit"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Selected Product Info -->
        <div x-show="selectedProduct" x-cloak
            class="bg-green-50 border border-green-200 rounded-lg p-4 mb-5">
            <div class="flex justify-between items-center">
                <div>
                    <div class="font-semibold text-gray-800" x-text="selectedProduct?.name"></div>
                    <div class="text-sm text-gray-500">
                        Current Stock:
                        <span class="font-bold text-blue-600"
                            x-text="selectedProduct?.current_stock + ' ' + selectedProduct?.unit"></span>
                    </div>
                </div>
                <button type="button" @click="clearProduct()"
                    class="text-gray-400 hover:text-red-500 text-xl">&times;</button>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-5 mb-5">
            <!-- Quantity -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Quantity <span class="text-red-500">*</span>
                </label>
                <input type="number" name="quantity"
                    min="1" required
                    value="<?= e(old('quantity', '1')) ?>"
                    x-model="quantity"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-lg font-bold
                              focus:ring-2 focus:ring-green-500 focus:border-transparent">
            </div>

            <!-- Cost Price -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Cost Price <span class="text-gray-400 font-normal">(Optional)</span>
                </label>
                <div class="relative">
                    <span class="absolute left-3 top-2.5 text-gray-500"><?= CURRENCY_HOLDER ?></span>
                    <input type="number" name="cost_price"
                        min="0" step="0.01"
                        value="<?= e(old('cost_price', $preProduct ? $preProduct['cost_price'] : '')) ?>"
                        placeholder="0.00"
                        class="w-full pl-14 pr-4 py-2.5 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-green-500 focus:border-transparent">
                </div>
                <p class="text-xs text-gray-400 mt-1">Leave blank to keep existing cost</p>
            </div>
        </div>

        <!-- New stock preview -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-5"
            x-show="selectedProduct && quantity > 0" x-cloak>
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Stock After Update:</span>
                <span class="font-bold text-blue-700"
                    x-text="((parseInt(selectedProduct?.current_stock) || 0) + (parseInt(quantity) || 0))
                               + ' ' + (selectedProduct?.unit || '')">
                </span>
            </div>
        </div>

        <!-- Reference / Invoice -->
        <div class="mb-5">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Reference <span class="text-gray-400 font-normal">(Optional)</span>
            </label>
            <input type="text" name="reference"
                value="<?= e(old('reference')) ?>"
                placeholder="e.g. Invoice #12345, Delivery note..."
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                          focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>

        <!-- Supplier — REQUIRED -->
        <div class="mb-5">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Supplier <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <input type="text"
                    placeholder="Click to select or type to search..."
                    x-model="supplierQuery"
                    @focus="searchSuppliers()"
                    @input="searchSuppliers()"
                    @click="searchSuppliers()"
                    autocomplete="off"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-green-500 focus:border-transparent">
                <input type="hidden" name="supplier_id" x-model="supplierId">

                <!-- Selected supplier badge -->
                <div x-show="selectedSupplier" x-cloak
                    class="mt-2 flex items-center justify-between bg-green-50
                            border border-green-200 rounded-lg px-3 py-2">
                    <span class="text-sm font-medium text-green-800"
                        x-text="selectedSupplier?.company_name"></span>
                    <button type="button" @click="clearSupplier()"
                        class="text-gray-400 hover:text-red-500 text-lg leading-none">&times;</button>
                </div>

                <div x-show="showDrop && supplierResults.length > 0" x-cloak
                    @click.outside="showDrop = false"
                    class="absolute z-10 w-full bg-white border border-gray-200 rounded-lg
                            shadow-lg mt-1 max-h-48 overflow-y-auto">
                    <template x-for="s in supplierResults" :key="s.id">
                        <div @click="selectSupplier(s)"
                            class="px-4 py-3 hover:bg-green-50 cursor-pointer border-b last:border-0">
                            <div class="font-medium text-gray-800" x-text="s.company_name"></div>
                            <div class="text-xs text-gray-400" x-text="s.phone"></div>
                        </div>
                    </template>
                </div>

                <div x-show="!supplierId && supplierQuery.length === 0" class="text-xs text-gray-400 mt-1">
                    Start typing to search suppliers
                </div>
            </div>
        </div>

        <!-- Notes -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
            <textarea name="notes" rows="2"
                placeholder="Any additional notes..."
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                             focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"><?= e(old('notes')) ?></textarea>
        </div>

        <div class="flex justify-between">
            <a href="<?= BASE_URL ?>/stock"
                class="px-5 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition">
                Cancel
            </a>
            <!-- Disabled until both product AND supplier are selected -->
            <button type="submit"
                :disabled="!selectedId || !supplierId"
                class="px-6 py-2.5 bg-green-600 hover:bg-green-700 disabled:bg-gray-300
                           disabled:cursor-not-allowed text-white rounded-lg shadow transition">
                <span x-text="buttonLabel()"></span>
            </button>
        </div>
    </form>
</div>

<script>
    function stockInForm() {
        return {
            // ── Product ──────────────────────────────────────────
            searchQuery: '<?= $preProduct ? e($preProduct['name']) : '' ?>',
            selectedId: <?= $preProduct ? $preProduct['id'] : 'null' ?>,
            selectedProduct: <?= $preProduct ? json_encode($preProduct) : 'null' ?>,
            productResults: [],
            showDropdown: false,
            quantity: <?= old('quantity', 1) ?>,

            searchProducts() {
                if (this.searchQuery.length < 1) {
                    this.productResults = [];
                    return;
                }
                fetch('<?= BASE_URL ?>/products/search?q=' + encodeURIComponent(this.searchQuery) + '&all=1')
                    .then(r => r.json())
                    .then(data => {
                        this.productResults = data;
                        this.showDropdown = true;
                    });
            },

            selectProduct(p) {
                this.selectedProduct = p;
                this.selectedId = p.id;
                this.searchQuery = p.name;
                this.showDropdown = false;
                this.productResults = [];
            },

            clearProduct() {
                this.selectedProduct = null;
                this.selectedId = null;
                this.searchQuery = '';
            },

            // ── Supplier ─────────────────────────────────────────
            supplierQuery: '',
            supplierId: null,
            selectedSupplier: null,
            supplierResults: [],
            showDrop: false,
            allSuppliers: [],
            _suppliersLoaded: false, // Flag to track if suppliers are loaded


            searchSuppliers() {
                // Load all suppliers on first call
                if (!this._suppliersLoaded) {
                    this.loadAllSuppliers();
                    this._suppliersLoaded = true;
                    return;
                }

                // If empty, show all suppliers
                if (this.supplierQuery.length === 0) {
                    this.supplierResults = this.allSuppliers;
                    this.showDrop = true;
                    return;
                }

                // Otherwise filter locally
                const searchLower = this.supplierQuery.toLowerCase();
                this.supplierResults = this.allSuppliers.filter(s =>
                    s.company_name.toLowerCase().includes(searchLower) ||
                    (s.supplier_code && s.supplier_code.toLowerCase().includes(searchLower)) ||
                    (s.phone && s.phone.includes(searchLower))
                );
                this.showDrop = true;
            },

            loadAllSuppliers() {
                fetch('<?= BASE_URL ?>/suppliers/search?all=1')
                    .then(r => r.json())
                    .then(d => {
                        this.allSuppliers = d;
                        this.supplierResults = d;
                        this.showDrop = true; // Open dropdown after loading
                    })
                    .catch(err => {
                        console.error('Failed to load suppliers:', err);
                    });
            },

            selectSupplier(s) {
                this.supplierId = s.id;
                this.selectedSupplier = s;
                this.supplierQuery = '';
                this.showDrop = false;
                this.supplierResults = [];
            },

            clearSupplier() {
                this.supplierId = null;
                this.selectedSupplier = null;
                this.supplierQuery = '';
            },

            buttonLabel() {
                if (!this.selectedId) return 'Select a product first';
                if (!this.supplierId) return 'Select a supplier';
                return '✓ Confirm Stock In';
            }
        }
    }
</script>

<?php include APP_PATH . '/views/layout/footer.php'; ?>