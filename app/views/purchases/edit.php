<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="h-screen-safe" x-data="purchaseForm()" x-init="init()">

    <!-- Top Bar -->
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold text-gray-800">✏️ Edit Purchase #<?= e($purchase['purchase_number']) ?></h1>
        <div class="flex gap-2">
            <a href="<?= BASE_URL ?>/purchases/view/<?= $purchase['id'] ?>"
                class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm transition">
                ← Back to Purchase Details
            </a>
            <button @click="clearCart()"
                class="bg-red-100 hover:bg-red-200 text-red-600 px-4 py-2 rounded-lg text-sm transition">
                Clear Cart
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">

        <!-- LEFT: Product Search + Grid (3/5) -->
        <div class="lg:col-span-3 space-y-4">

            <!-- Supplier Selector -->
            <div class="bg-white rounded-lg shadow p-3">
                <div class="flex items-center gap-3">
                    <div class="text-gray-500 text-sm font-medium whitespace-nowrap">🏢 Supplier:</div>
                    <div class="flex-1 relative">
                        <select x-model="supplierId"
                            @change="selectSupplier()"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm
                                      focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Select Supplier...</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= $s['id'] ?>"
                                    data-name="<?= e($s['company_name']) ?>"
                                    data-balance="<?= $s['current_balance'] ?>">
                                    <?= e($s['company_name']) ?>
                                    <?php if ($s['current_balance'] != 0): ?>
                                        - <?= $s['current_balance'] < 0 ? 'We owe: ' . formatMoney(abs($s['current_balance'])) : 'Credit: ' . formatMoney($s['current_balance']) ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Selected Supplier Badge -->
                    <div x-show="selectedSupplier" class="flex items-center gap-2 bg-blue-50 px-3 py-1.5 rounded-lg">
                        <span class="text-sm font-semibold text-blue-700" x-text="selectedSupplier?.name"></span>
                    </div>
                </div>

                <!-- Balance warning -->
                <div x-show="selectedSupplier && selectedSupplier.balance < 0"
                    class="mt-2 text-xs text-red-500 bg-red-50 px-3 py-1.5 rounded">
                    ⚠️ We owe this supplier
                    <strong x-text="formatMoney(Math.abs(selectedSupplier?.balance || 0))"></strong>
                </div>
            </div>

            <!-- Product Search Bar -->
            <div class="bg-white rounded-lg shadow p-3">
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <input type="text"
                            id="product-search"
                            placeholder="🔍 Search by name, SKU or scan barcode..."
                            x-model="productSearch"
                            @input.debounce.250ms="searchProducts()"
                            @keydown.enter.prevent="addFirstResult()"
                            autocomplete="off"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                                      focus:ring-2 focus:ring-blue-500 focus:border-transparent">

                        <!-- Product Dropdown -->
                        <div x-show="productResults.length > 0"
                            @click.outside="productResults = []"
                            class="absolute z-20 w-full bg-white border border-gray-200 rounded-lg
                                    shadow-lg mt-1 max-h-64 overflow-y-auto">
                            <template x-for="p in productResults" :key="p.id">
                                <div @click="addToCart(p)"
                                    class="flex items-center justify-between px-4 py-3
                                             hover:bg-green-50 cursor-pointer border-b last:border-0">
                                    <div>
                                        <div class="font-medium text-gray-800 text-sm" x-text="p.name"></div>
                                        <div class="text-xs text-gray-400">
                                            <span x-text="'SKU: ' + p.sku"></span>
                                            <span class="mx-1">•</span>
                                            <span x-text="'Stock: ' + p.current_stock + ' ' + p.unit"></span>
                                            <span class="mx-1">•</span>
                                            <span x-text="'Avg Cost: ' + formatMoney(p.average_cost)"></span>
                                        </div>
                                    </div>
                                    <div class="text-right ml-4">
                                        <div class="font-bold text-blue-600 text-sm"
                                            x-text="formatMoney(p.cost_price)"></div>
                                        <div class="text-xs text-gray-400">Last Cost</div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Category Filter Tabs -->
                <div class="flex gap-2 mt-2 overflow-x-auto pb-1">
                    <button @click="filterCategory(null)"
                        :class="activeCategory === null ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                        class="px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap transition">
                        All
                    </button>
                    <?php foreach ($categories as $cat): ?>
                        <button @click="filterCategory(<?= $cat['id'] ?>)"
                            :class="activeCategory === <?= $cat['id'] ?> ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap transition">
                            <?= e($cat['name']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Quick Product Grid -->
            <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                <template x-for="p in quickProducts" :key="p.id">
                    <button @click="addToCart(p)"
                        class="bg-white rounded-lg shadow p-2 text-left hover:shadow-md
                                   hover:border-blue-400 border-2 border-transparent transition">
                        <div class="font-semibold text-gray-800 text-sm truncate" x-text="p.name"></div>
                        <div class="text-xs text-gray-400 mt-0.5" x-text="p.sku"></div>
                        <div class="flex justify-between items-end mt-1.5">
                            <div>
                                <div class="font-bold text-blue-600 text-sm" x-text="formatMoney(p.cost_price)"></div>
                                <div class="text-xs text-gray-400" x-text="'Avg: ' + formatMoney(p.average_cost)"></div>
                            </div>
                            <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-500"
                                x-text="p.current_stock + ' ' + p.unit">
                            </span>
                        </div>
                    </button>
                </template>
            </div>

        </div>

        <!-- RIGHT: Cart + Checkout (2/5) -->
        <div class="lg:col-span-2 lg:sticky lg:top-4" style="align-self: flex-start;">
            <div class="bg-white rounded-lg shadow flex flex-col" style="min-height: 580px; max-height: 88vh;">

                <!-- Cart Header -->
                <div class="px-4 py-3 border-b flex justify-between items-center">
                    <h2 class="font-bold text-gray-700">
                        🛒 Purchase Cart
                        <span class="ml-1 bg-blue-100 text-blue-700 text-xs px-2 py-0.5 rounded-full"
                            x-text="cart.length + ' item' + (cart.length !== 1 ? 's' : '')"></span>
                    </h2>
                    <span class="text-sm text-gray-500" x-text="formatDate()"></span>
                </div>

                <!-- Cart Items -->
                <div class="flex-1 overflow-y-auto divide-y" style="min-height: 220px;">
                    <div x-show="cart.length === 0" class="p-10 text-center text-gray-400">
                        <div class="text-4xl mb-2">📦</div>
                        <p class="text-sm">Cart is empty</p>
                        <p class="text-xs mt-1">Search or click a product to add</p>
                    </div>

                    <template x-for="(item, idx) in cart" :key="item.product_id">
                        <div class="px-2 py-1.5">
                            <div class="flex items-center gap-1.5">
                                <!-- Product Name -->
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium text-gray-800 text-xs truncate" x-text="item.name" :title="item.name"></div>
                                    <div class="text-[10px] text-gray-400">
                                        <span x-text="'Avg: ' + formatMoney(item.avgCost)"></span>
                                        <span class="mx-1">•</span>
                                        <span x-text="item.unit"></span>
                                    </div>
                                </div>

                                <!-- Quantity controls -->
                                <div class="w-[88px] flex items-center border rounded overflow-hidden">
                                    <button @click="changeQty(idx, -1)"
                                        class="px-1.5 py-0.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-xs font-bold transition">−</button>
                                    <input type="number"
                                        x-model.number="item.quantity"
                                        @change="validateQty(idx)"
                                        min="1"
                                        class="w-8 text-center text-xs py-0.5 border-x focus:outline-none">
                                    <button @click="changeQty(idx, 1)"
                                        class="px-1.5 py-0.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-xs font-bold transition">+</button>
                                </div>

                                <!-- Unit Cost -->
                                <div class="w-[76px] flex items-center border rounded overflow-hidden">
                                    <input type="number"
                                        x-model.number="item.cost"
                                        min="0" step="0.01"
                                        class="w-full text-right text-xs px-1 py-0.5 focus:outline-none"
                                        placeholder="Cost"
                                        title="Unit Cost">
                                </div>

                                <!-- Item Discount -->
                                <div class="w-[52px] flex items-center border rounded overflow-hidden">
                                    <input type="number"
                                        x-model.number="item.discount"
                                        min="0" max="100" placeholder="0"
                                        class="w-8 text-center text-[10px] py-0.5 focus:outline-none"
                                        title="Discount %">
                                    <span class="px-1 bg-gray-50 text-gray-500 text-[10px]">%</span>
                                </div>

                                <!-- Line Total -->
                                <div class="w-16 font-bold text-xs text-gray-800 text-right pr-1"
                                    x-text="formatMoney(lineTotal(item))">
                                </div>

                                <!-- Remove button -->
                                <button @click="removeFromCart(idx)"
                                    class="w-4 text-gray-300 hover:text-red-500 text-base leading-none text-center">
                                    &times;
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Totals -->
                <div class="border-t px-4 py-3 space-y-2">

                    <!-- Subtotal -->
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>Subtotal</span>
                        <span x-text="formatMoney(subtotal())"></span>
                    </div>

                    <!-- Discount Row -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-600">Discount</span>
                            <div class="flex items-center border rounded overflow-hidden">
                                <input type="number"
                                    x-model.number="purchaseDiscount"
                                    min="0" max="100" placeholder="0"
                                    class="w-12 text-center text-sm py-0.5 focus:outline-none">
                                <span class="px-1.5 bg-gray-50 text-gray-500 text-xs">%</span>
                            </div>
                        </div>
                        <span class="text-sm text-red-500"
                            x-text="purchaseDiscount > 0 ? '- ' + formatMoney(discountAmount()) : '—'">
                        </span>
                    </div>

                    <!-- VAT Row -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-600">VAT</span>
                            <div class="flex items-center border rounded overflow-hidden">
                                <input type="number"
                                    x-model.number="vatPercent"
                                    min="0" max="100" placeholder="0"
                                    class="w-12 text-center text-sm py-0.5 focus:outline-none">
                                <span class="px-1.5 bg-gray-50 text-gray-500 text-xs">%</span>
                            </div>
                        </div>
                        <span class="text-sm text-orange-500"
                            x-text="vatPercent > 0 ? '+ ' + formatMoney(vatAmount()) : '—'">
                        </span>
                    </div>

                    <!-- Total -->
                    <div class="flex justify-between font-bold text-xl border-t pt-2">
                        <span>TOTAL</span>
                        <span class="text-blue-600" x-text="formatMoney(grandTotal())"></span>
                    </div>
                </div>

                <!-- Payment Section -->
                <div class="border-t px-4 py-3 space-y-3">

                    <!-- Invoice Number -->
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Supplier Invoice # (Optional)</label>
                        <input type="text"
                            x-model="invoiceNumber"
                            placeholder="INV-12345"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm
                                      focus:ring-2 focus:ring-blue-500">
                    </div>

                    <!-- Payment Method -->
                    <div class="grid grid-cols-5 gap-1.5">
                        <?php
                        $methods = [
                            'cash' => '💵 Cash',
                            'mobile' => '📱 Mobile',
                            'bank' => '🏦 Bank',
                            'cheque' => '📝 Cheque',
                            'credit' => '📋 Credit'
                        ];
                        foreach ($methods as $val => $label): ?>
                            <button @click="selectPaymentMethod('<?= $val ?>')"
                                :class="paymentMethod === '<?= $val ?>'
                        ? 'bg-blue-600 text-white border-blue-600'
                        : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
                                class="border rounded-lg py-2 text-xs font-semibold transition text-center">
                                <?= $label ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <!-- Account Selection Dropdown -->
                    <div x-show="['cash', 'mobile', 'bank', 'cheque'].includes(paymentMethod)" class="transition-all duration-200">
                        <label class="block text-xs text-gray-500 mb-1">Payment Source Account</label>
                        <select 
                            x-model.number="selectedAccountId"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm bg-white"
                            required>
                            <option value="0" disabled>-- Select Financial Account --</option>
                            <template x-for="acc in accountsForMethod(paymentMethod)" :key="acc.id">
                                <option :value="acc.id" x-text="acc.name + ' (' + formatMoney(acc.balance) + ')'"></option>
                            </template>
                        </select>
                    </div>

                    <!-- Amount Paid (hide for full credit) -->
                    <div x-show="paymentMethod !== 'credit'">
                        <label class="block text-xs text-gray-500 mb-1">Amount Paid</label>
                        <div class="relative">
                            <span class="absolute left-3 top-2.5 text-gray-500 text-sm font-semibold">
                                <?= CURRENCY_HOLDER ?>
                            </span>
                            <input type="number"
                                x-model.number="amountPaid"
                                @focus="$el.select()"
                                min="0" step="0.01"
                                class="w-full pl-14 pr-4 py-2.5 border border-gray-300 rounded-lg
                                          text-lg font-bold focus:ring-2 focus:ring-blue-500">
                        </div>

                        <!-- Quick amount buttons -->
                        <div class="flex gap-1.5 mt-2">
                            <button @click="amountPaid = grandTotal()"
                                class="flex-1 py-1.5 bg-gray-100 hover:bg-gray-200 rounded text-xs font-semibold transition">
                                Exact
                            </button>
                        </div>
                    </div>

                    <!-- Balance Display -->
                    <div class="flex justify-between text-sm font-semibold">
                        <template x-if="paymentMethod !== 'credit'">
                            <template x-if="amountPaid >= grandTotal()">
                                <div class="flex justify-between w-full">
                                    <span class="text-gray-600">Change</span>
                                    <span class="text-blue-600" x-text="formatMoney(amountPaid - grandTotal())"></span>
                                </div>
                            </template>
                        </template>
                        <template x-if="paymentMethod !== 'credit' && amountPaid < grandTotal()">
                            <div class="flex justify-between w-full">
                                <span class="text-gray-600">Balance Due</span>
                                <span class="text-red-500" x-text="formatMoney(grandTotal() - amountPaid)"></span>
                            </div>
                        </template>
                        <template x-if="paymentMethod === 'credit'">
                            <div class="flex justify-between w-full">
                                <span class="text-gray-600">On Credit</span>
                                <span class="text-orange-500" x-text="formatMoney(grandTotal())"></span>
                            </div>
                        </template>
                    </div>

                    <!-- Notes -->
                    <input type="text"
                        x-model="notes"
                        placeholder="Notes (optional)..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm
                                  focus:ring-2 focus:ring-blue-500">

                    <!-- Complete Purchase Button -->
                    <button @click="completePurchase()"
                        :disabled="cart.length === 0 || !supplierId || isProcessing"
                        class="w-full py-4 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300
                                   disabled:cursor-not-allowed text-white font-bold text-lg
                                   rounded-lg shadow transition flex items-center justify-center gap-2">
                        <span x-show="!isProcessing">✓ Update Purchase</span>
                        <span x-show="isProcessing" class="flex items-center gap-2">
                            <div class="spinner w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                            Processing...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
    function purchaseForm() {
        return {
            // State
            cart: [
                <?php foreach ($purchaseItems as $item): ?>
                {
                    product_id: <?= intval($item['product_id']) ?>,
                    name: <?= json_encode($item['product_name']) ?>,
                    sku: <?= json_encode($item['sku']) ?>,
                    unit: <?= json_encode($item['unit'] ?? 'pcs') ?>,
                    cost: <?= floatval($item['unit_cost']) ?>,
                    avgCost: <?= floatval($item['average_cost'] ?? $item['unit_cost']) ?>,
                    quantity: <?= intval($item['quantity']) ?>,
                    discount: <?= floatval($item['discount_percent']) ?>,
                },
                <?php endforeach; ?>
            ],
            quickProducts: <?= json_encode($quickProducts) ?>,
            productResults: [],
            productSearch: '',
            supplierId: <?= json_encode($purchase['supplier_id']) ?>,
            selectedSupplier: null,
            purchaseDiscount: <?= floatval($purchase['discount_percent']) ?>,
            vatPercent: <?= floatval($purchase['vat_percent']) ?>,
            paymentMethod: <?= json_encode($purchase['payment_method']) ?>,
            amountPaid: <?= floatval($purchase['amount_paid']) ?>,
            invoiceNumber: <?= json_encode($purchase['invoice_number'] ?? '') ?>,
            notes: <?= json_encode($purchase['notes'] ?? '') ?>,
            activeCategory: null,
            isProcessing: false,
            allAccounts: <?= json_encode($financialAccounts) ?>,
            selectedAccountId: <?= intval($selectedAccountId) ?>,

            init() {
                this.$nextTick(() => {
                    const el = document.getElementById('product-search');
                    if (el) el.focus();
                    this.selectSupplier();
                });
            },

            // ── Product Search ───────────────────────────────
            searchProducts() {
                if (!this.productSearch) {
                    this.productResults = [];
                    return;
                }
                fetch('<?= BASE_URL ?>/products/search?q=' + encodeURIComponent(this.productSearch))
                    .then(r => r.json())
                    .then(d => this.productResults = d);
            },

            addFirstResult() {
                if (this.productResults.length > 0) {
                    this.addToCart(this.productResults[0]);
                    this.productSearch = '';
                    this.productResults = [];
                }
            },

            filterCategory(catId) {
                this.activeCategory = catId;
                const url = catId ?
                    '<?= BASE_URL ?>/products/search?q=&category=' + catId :
                    '<?= BASE_URL ?>/products/search?q=';
                fetch(url).then(r => r.json()).then(d => {
                    this.quickProducts = d.slice(0, 20);
                    this.productResults = [];
                });
            },

            // ── Supplier ────────────────────────────────────
            selectSupplier() {
                const select = document.querySelector('select[x-model="supplierId"]');
                if (!select) return;
                const option = select.options[select.selectedIndex];
                if (option && option.value) {
                    this.selectedSupplier = {
                        id: option.value,
                        name: option.dataset.name,
                        balance: parseFloat(option.dataset.balance) || 0
                    };
                } else {
                    this.selectedSupplier = null;
                }
            },

            // ── Cart ────────────────────────────────────────
            addToCart(product) {
                const existing = this.cart.find(i => i.product_id === product.id);
                if (existing) {
                    existing.quantity++;
                } else {
                    this.cart.push({
                        product_id: product.id,
                        name: product.name,
                        sku: product.sku,
                        unit: product.unit,
                        cost: parseFloat(product.cost_price) || 0,
                        avgCost: parseFloat(product.average_cost) || 0,
                        quantity: 1,
                        discount: 0,
                    });
                }
                this.productSearch = '';
                this.productResults = [];
                this.updateAmountPaid();
            },

            removeFromCart(idx) {
                this.cart.splice(idx, 1);
                this.updateAmountPaid();
            },

            changeQty(idx, delta) {
                const item = this.cart[idx];
                const newQty = item.quantity + delta;
                if (newQty < 1) {
                    this.removeFromCart(idx);
                    return;
                }
                item.quantity = newQty;
                this.updateAmountPaid();
            },

            validateQty(idx) {
                const item = this.cart[idx];
                if (item.quantity < 1) item.quantity = 1;
                this.updateAmountPaid();
            },

            clearCart() {
                if (this.cart.length === 0 || confirm('Clear the cart?')) {
                    this.cart = [];
                    this.purchaseDiscount = 0;
                    this.vatPercent = 0;
                    this.notes = '';
                    this.invoiceNumber = '';
                    this.paymentMethod = 'credit';
                    this.selectedAccountId = 0;
                    this.updateAmountPaid();
                }
            },

            // ── Calculations ────────────────────────────────
            lineTotal(item) {
                return item.cost * item.quantity * (1 - (item.discount || 0) / 100);
            },

            subtotal() {
                return this.cart.reduce((s, i) => s + this.lineTotal(i), 0);
            },

            discountAmount() {
                return this.subtotal() * (this.purchaseDiscount / 100);
            },

            afterDiscount() {
                return this.subtotal() - this.discountAmount();
            },

            vatAmount() {
                return this.afterDiscount() * (this.vatPercent / 100);
            },

            grandTotal() {
                return Math.max(0, this.afterDiscount() + this.vatAmount());
            },

            updateAmountPaid() {
                if (this.paymentMethod !== 'credit') {
                    this.amountPaid = this.grandTotal();
                }
            },

            // ── Complete Purchase ────────────────────────────────
            completePurchase() {
                if (this.cart.length === 0) {
                    alert('Cart is empty');
                    return;
                }
                if (!this.supplierId) {
                    alert('Please select a supplier');
                    return;
                }

                const total = this.grandTotal();
                const paid = this.paymentMethod === 'credit' ? 0 : parseFloat(this.amountPaid) || 0;

                if (this.paymentMethod !== 'credit' && paid <= 0) {
                    alert('Please enter the amount paid');
                    return;
                }

                this.isProcessing = true;

                const payload = {
                    supplier_id: this.supplierId,
                    items: this.cart.map(i => ({
                        product_id: i.product_id,
                        quantity: i.quantity,
                        cost: i.cost,
                        discount: i.discount || 0,
                    })),
                    discount_pct: this.purchaseDiscount,
                    vat_pct: this.vatPercent,
                    payment_method: this.paymentMethod,
                    amount_paid: paid,
                    invoice_number: this.invoiceNumber,
                    notes: this.notes,
                    account_id: (['cash','mobile','bank','cheque'].includes(this.paymentMethod)) ? this.selectedAccountId : 0,
                };

                fetch('<?= BASE_URL ?>/purchases/update/<?= $purchase['id'] ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload),
                    })
                    .then(r => r.json())
                    .then(data => {
                        this.isProcessing = false;
                        if (data.success) {
                            alert(data.message);
                            window.location.href = data.redirect;
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(() => {
                        this.isProcessing = false;
                        alert('Network error. Please try again.');
                    });
            },

            // ── Helpers ─────────────────────────────────────
            formatMoney(amount) {
                amount = parseFloat(amount) || 0;
                return '<?= CURRENCY_HOLDER ?> ' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            },

            // ── Account Selector Helpers ─────────────────────
            selectPaymentMethod(method) {
                this.paymentMethod = method;
                this.autoSelectAccount(method);
                this.updateAmountPaid();
            },

            accountsForMethod(method) {
                const typeMap = { cash: 'cash', mobile: 'mobile_money', bank: 'bank', cheque: 'bank' };
                const type = typeMap[method];
                if (!type) return [];
                return this.allAccounts.filter(a => a.type === type);
            },

            autoSelectAccount(method) {
                const list = this.accountsForMethod(method);
                this.selectedAccountId = list.length > 0 ? list[0].id : 0;
            },

            formatDate() {
                return new Date().toLocaleDateString('en-GH', {
                    weekday: 'short',
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric'
                });
            },
        };
    }
</script>

<?php include APP_PATH . '/views/layout/header.php'; ?>
