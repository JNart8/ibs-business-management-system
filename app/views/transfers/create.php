<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="max-w-3xl mx-auto" x-data="transferForm()">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">🔄 New Stock Transfer</h1>
            <p class="text-gray-500 text-sm mt-1">
                Stock leaves the source branch immediately. It won't count toward the
                destination branch's stock until someone there confirms receipt.
            </p>
        </div>
    </div>

    <div class="mb-4">
        <a href="<?= BASE_URL ?>/transfers"
            class="bg-gray-100 hover:bg-gray-200 text-gray-800 px-4 py-2.5 rounded-lg text-sm font-medium transition inline-block">
            ← Back to Transfers
        </a>
    </div>

    <?= flashMessage() ?>

    <form id="transferForm" action="<?= BASE_URL ?>/transfers/create" method="POST" @submit="return validateSubmit($event)">
        <?= csrfField() ?>

        <div class="bg-white rounded-xl shadow-sm border p-6 mb-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">From Branch *</label>
                    <select name="from_branch_id" x-model="fromBranchId" @change="onFromBranchChange()" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Select source branch</option>
                        <?php foreach ($fromBranches as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (count($fromBranches) === 1): ?>
                        <p class="text-xs text-gray-400 mt-1">You're only assigned to one branch, so this is fixed.</p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">To Branch *</label>
                    <select name="to_branch_id" x-model="toBranchId" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Select destination branch</option>
                        <?php foreach ($allBranches as $b): ?>
                            <option value="<?= $b['id'] ?>" x-bind:disabled="fromBranchId == <?= $b['id'] ?>"><?= e($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Product picker -->
        <div class="bg-white rounded-xl shadow-sm border p-6 mb-4">
            <label class="block text-sm font-semibold text-gray-700 mb-1">Add Products</label>
            <div class="relative">
                <input type="text" x-model="searchQuery" @input.debounce.300ms="searchProducts()"
                    :disabled="!fromBranchId"
                    placeholder="Search products at the source branch by name or SKU..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-gray-50 disabled:text-gray-400">
                <div x-show="showDropdown && productResults.length > 0" x-cloak
                    class="absolute z-10 w-full bg-white border rounded-lg shadow-lg mt-1 max-h-64 overflow-y-auto">
                    <template x-for="product in productResults" :key="product.id">
                        <button type="button" @click="addToCart(product)"
                            class="w-full text-left px-4 py-2 hover:bg-blue-50 border-b flex justify-between items-center">
                            <span>
                                <span class="font-medium" x-text="product.name"></span>
                                <span class="text-xs text-gray-400 block" x-text="product.sku"></span>
                            </span>
                            <span class="text-sm text-gray-500" x-text="product.current_stock + ' ' + product.unit"></span>
                        </button>
                    </template>
                </div>
                <p x-show="showDropdown && productResults.length === 0" x-cloak class="text-sm text-gray-400 mt-2">
                    No products with stock found at that branch.
                </p>
            </div>
            <p x-show="!fromBranchId" class="text-xs text-gray-400 mt-1">Select a source branch first.</p>
        </div>

        <!-- Cart -->
        <div class="bg-white rounded-xl shadow-sm border p-6 mb-4" x-show="cart.length > 0" x-cloak>
            <h3 class="font-semibold text-gray-800 mb-3">Items to Transfer</h3>
            <div class="space-y-2">
                <template x-for="(item, idx) in cart" :key="item.product_id">
                    <div class="flex items-center justify-between border rounded-lg px-3 py-2">
                        <div>
                            <div class="font-medium text-sm" x-text="item.name"></div>
                            <div class="text-xs text-gray-400" x-text="item.sku + ' · available: ' + item.stock + ' ' + item.unit"></div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="changeQty(idx, -1)" class="w-7 h-7 bg-gray-100 hover:bg-gray-200 rounded">−</button>
                            <input type="number" x-model.number="item.quantity" @change="validateQty(idx)"
                                min="0.01" step="0.01" class="w-20 text-center text-sm py-1 border rounded">
                            <button type="button" @click="changeQty(idx, 1)" class="w-7 h-7 bg-gray-100 hover:bg-gray-200 rounded">+</button>
                            <button type="button" @click="removeFromCart(idx)" class="ml-2 text-red-500 hover:text-red-700">🗑️</button>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Hidden inputs for form submission -->
            <template x-for="(item, idx) in cart" :key="'hidden-' + item.product_id">
                <div>
                    <input type="hidden" :name="'items[' + idx + '][product_id]'" :value="item.product_id">
                    <input type="hidden" :name="'items[' + idx + '][quantity]'" :value="item.quantity">
                </div>
            </template>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-6 mb-4">
            <label class="block text-sm font-semibold text-gray-700 mb-1">Notes (optional)</label>
            <textarea name="notes" rows="2"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="e.g. reason for transfer, delivery reference..."></textarea>
        </div>

        <div class="flex justify-end gap-2">
            <a href="<?= BASE_URL ?>/transfers"
                class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-2 px-4 rounded-lg text-sm transition">
                Cancel
            </a>
            <button type="submit" :disabled="cart.length === 0 || !fromBranchId || !toBranchId"
                class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-semibold py-2 px-6 rounded-lg text-sm transition">
                Dispatch Transfer
            </button>
        </div>
    </form>
</div>

<script>
function transferForm() {
    return {
        fromBranchId: '<?= count($fromBranches) === 1 ? $fromBranches[0]['id'] : '' ?>',
        toBranchId: '',
        cart: [],
        searchQuery: '',
        productResults: [],
        showDropdown: false,

        searchProducts() {
            if (this.searchQuery.length < 1 || !this.fromBranchId) {
                this.productResults = [];
                return;
            }
            fetch('<?= BASE_URL ?>/products/search?q=' + encodeURIComponent(this.searchQuery) + '&branch_id=' + this.fromBranchId + '&all=1')
                .then(r => r.json())
                .then(data => {
                    this.productResults = data.filter(p => parseFloat(p.current_stock) > 0);
                    this.showDropdown = true;
                });
        },

        addToCart(product) {
            const existing = this.cart.find(i => i.product_id === product.id);
            if (existing) {
                if (existing.quantity < parseFloat(product.current_stock)) existing.quantity++;
            } else {
                this.cart.push({
                    product_id: product.id,
                    name: product.name,
                    sku: product.sku,
                    unit: product.unit,
                    stock: parseFloat(product.current_stock),
                    quantity: 1
                });
            }
            this.searchQuery = '';
            this.productResults = [];
            this.showDropdown = false;
        },

        removeFromCart(idx) {
            this.cart.splice(idx, 1);
        },

        changeQty(idx, delta) {
            const item = this.cart[idx];
            const newQty = Math.round((item.quantity + delta) * 100) / 100;
            if (newQty < 0.01) {
                this.removeFromCart(idx);
                return;
            }
            if (newQty > item.stock) {
                alert('Not enough stock at the source branch!');
                return;
            }
            item.quantity = newQty;
        },

        validateQty(idx) {
            const item = this.cart[idx];
            if (item.quantity < 0.01) item.quantity = 0.01;
            if (item.quantity > item.stock) item.quantity = item.stock;
        },

        onFromBranchChange() {
            // Stock levels differ by branch — clear the cart so nothing
            // stale (validated against the old branch) gets submitted.
            if (this.cart.length > 0) {
                this.cart = [];
            }
            this.searchQuery = '';
            this.productResults = [];
        },

        validateSubmit(event) {
            if (!this.fromBranchId || !this.toBranchId) {
                alert('Select both a source and destination branch.');
                event.preventDefault();
                return false;
            }
            if (this.fromBranchId == this.toBranchId) {
                alert('Source and destination branches must be different.');
                event.preventDefault();
                return false;
            }
            if (this.cart.length === 0) {
                alert('Add at least one product to transfer.');
                event.preventDefault();
                return false;
            }
            return true;
        }
    };
}
</script>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
