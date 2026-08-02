<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="container mx-auto px-4 py-6" x-data="distributorForm()" x-init="init()">
    <!-- Top Bar -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">🚚 Distributor Direct Delivery Entry</h1>
            <p class="text-sm text-gray-500 mt-1">Combine a supplier purchase and a customer sale into a single delivery flow</p>
        </div>
        <div class="flex gap-2">
            <a href="<?= BASE_URL ?>/distributor"
                class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm transition">
                ← Back to List
            </a>
            <button @click="clearCart()"
                class="bg-red-100 hover:bg-red-200 text-red-600 px-4 py-2 rounded-lg text-sm transition">
                Clear Form
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

        <!-- LEFT: Form Details & Items Grid (3/5) -->
        <div class="lg:col-span-3 space-y-6">

            <!-- Supplier & Customer Selection Card -->
            <div class="bg-white rounded-xl shadow p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Supplier Selector -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">🏢 Supplier (Outflow)</label>
                    <select x-model="supplierId" @change="selectSupplier()"
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Select Supplier...</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>"
                                data-name="<?= e($s['company_name']) ?>"
                                data-balance="<?= $s['current_balance'] ?>">
                                <?= e($s['company_name']) ?>
                                (Bal: <?= formatMoney($s['current_balance']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Customer Selector -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">👤 Customer (Inflow)</label>
                    <select x-model="customerId" @change="selectCustomer()"
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Select Customer...</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>"
                                data-name="<?= e($c['full_name']) ?>"
                                data-balance="<?= $c['current_balance'] ?>">
                                <?= e($c['full_name']) ?>
                                (Bal: <?= formatMoney($c['current_balance']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Product Search Bar -->
            <div class="bg-white rounded-xl shadow p-4">
                <div class="relative">
                    <input type="text"
                        id="product-search"
                        placeholder="🔍 Search products by name or SKU to add..."
                        x-model="productSearch"
                        @input.debounce.250ms="searchProducts()"
                        @keydown.enter.prevent="addFirstResult()"
                        autocomplete="off"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">

                    <!-- Product Dropdown -->
                    <div x-show="productResults.length > 0"
                        @click.outside="productResults = []"
                        class="absolute z-20 w-full bg-white border border-gray-200 rounded-lg shadow-lg mt-1 max-h-64 overflow-y-auto">
                        <template x-for="p in productResults" :key="p.id">
                            <div @click="addToCart(p)"
                                class="flex items-center justify-between px-4 py-3 hover:bg-blue-50 cursor-pointer border-b last:border-0">
                                <div>
                                    <div class="font-medium text-gray-800 text-sm" x-text="p.name"></div>
                                    <div class="text-xs text-gray-400">
                                        <span x-text="'SKU: ' + p.sku"></span>
                                        <span class="mx-1">•</span>
                                        <span x-text="'Current Stock: ' + p.current_stock + ' ' + p.unit"></span>
                                    </div>
                                </div>
                                <div class="text-right ml-4">
                                    <div class="text-xs text-gray-500">
                                        Cost: <span class="font-bold text-red-500" x-text="formatMoney(p.cost_price)"></span>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Sell: <span class="font-bold text-green-600" x-text="formatMoney(p.selling_price)"></span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Cart Table -->
            <div class="bg-white rounded-xl shadow overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase">Product</th>
                            <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase text-center w-24">Qty</th>
                            <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase text-right w-32">Unit Cost</th>
                            <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase text-right w-32">Unit Selling</th>
                            <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase text-right w-32">Net Margin</th>
                            <th class="px-4 py-3 text-center w-12"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="(item, idx) in cart" :key="item.product_id">
                            <tr class="hover:bg-gray-50 align-middle">
                                <td class="px-4 py-3.5">
                                    <div class="font-medium text-gray-800 text-sm" x-text="item.name"></div>
                                    <div class="text-xs text-gray-400 font-mono" x-text="item.sku"></div>
                                </td>
                                <td class="px-4 py-3.5 text-center">
                                    <div class="flex items-center justify-center border border-gray-300 rounded-lg overflow-hidden w-24 mx-auto">
                                        <button type="button" @click="changeQty(idx, -1)" class="px-2 py-1 bg-gray-100 hover:bg-gray-200 text-gray-600 font-bold transition">-</button>
                                        <input type="number" x-model.number="item.quantity" @change="validateQty(idx)" class="w-10 text-center border-0 py-1 text-sm focus:ring-0 focus:outline-none" min="0.01" step="0.01">
                                        <button type="button" @click="changeQty(idx, 1)" class="px-2 py-1 bg-gray-100 hover:bg-gray-200 text-gray-600 font-bold transition">+</button>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    <input type="number" step="0.01" x-model.number="item.purchase_cost" @input="updateAmounts()"
                                        class="w-full text-right px-2 py-1 border border-gray-200 rounded text-sm focus:ring-1 focus:ring-blue-500">
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    <input type="number" step="0.01" x-model.number="item.selling_price" @input="updateAmounts()"
                                        class="w-full text-right px-2 py-1 border border-gray-200 rounded text-sm focus:ring-1 focus:ring-blue-500">
                                </td>
                                <td class="px-4 py-3.5 text-right font-semibold text-blue-700 text-sm">
                                    <span x-text="formatMoney((item.selling_price - item.purchase_cost) * item.quantity)"></span>
                                </td>
                                <td class="px-4 py-3.5 text-center">
                                    <button type="button" @click="removeFromCart(idx)" class="text-red-500 hover:text-red-700 font-medium">❌</button>
                                </td>
                            </tr>
                        </template>
                        <template x-if="cart.length === 0">
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">
                                    No items in direct delivery. Search above to add items.
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Notes -->
            <div class="bg-white rounded-xl shadow p-5">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Transaction Notes</label>
                <textarea x-model="notes" rows="2" placeholder="e.g. Delivered directly via Cargo Express. Tracking ID #9822..."
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
            </div>
        </div>

        <!-- RIGHT: Financial Settlement & Totals (2/5) -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Summary Totals Card -->
            <div class="bg-white rounded-xl shadow p-5 space-y-4">
                <h3 class="text-lg font-bold text-gray-800 border-b pb-2">Delivery Summary</h3>
                
                <div class="flex justify-between items-center text-sm">
                    <span class="text-gray-500">Sale Subtotal (Inflow):</span>
                    <span class="font-semibold text-gray-800" x-text="formatMoney(saleSubtotal())"></span>
                </div>
                <div class="flex justify-between items-center text-sm">
                    <span class="text-gray-500">Purchase Subtotal (Outflow):</span>
                    <span class="font-semibold text-gray-800" x-text="formatMoney(purchaseSubtotal())"></span>
                </div>

                <div class="grid grid-cols-2 gap-2 border-t pt-3">
                    <div>
                        <label class="block text-xs text-gray-500">Discount %</label>
                        <input type="number" x-model.number="discountPct" @input="updateAmounts()" min="0" max="100"
                            class="w-full px-2 py-1 border rounded text-sm focus:ring-1 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">VAT / Tax %</label>
                        <input type="number" x-model.number="vatPercent" @input="updateAmounts()" min="0" max="100"
                            class="w-full px-2 py-1 border rounded text-sm focus:ring-1 focus:ring-blue-500">
                    </div>
                </div>

                <div class="border-t pt-3 space-y-2">
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-green-600">Final Sale (Customer):</span>
                        <span class="text-lg font-bold text-green-600" x-text="formatMoney(customerGrandTotal())"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-red-500">Final Purchase (Supplier):</span>
                        <span class="text-lg font-bold text-red-500" x-text="formatMoney(supplierGrandTotal())"></span>
                    </div>
                    <div class="flex justify-between items-center bg-blue-50 p-2.5 rounded-lg border border-blue-100">
                        <span class="text-sm font-bold text-blue-800">Net Estimated Profit:</span>
                        <span class="text-lg font-black text-blue-800" x-text="formatMoney(netProfit())"></span>
                    </div>
                </div>
            </div>

            <!-- Customer Financial Settlement -->
            <div class="bg-white rounded-xl shadow p-5 space-y-3">
                <h3 class="text-md font-bold text-gray-800 border-b pb-2">👤 Customer Payment</h3>
                
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Payment Method</label>
                    <div class="grid grid-cols-3 gap-1">
                        <button type="button" @click="selectCustomerPaymentMethod('credit')"
                            :class="customerPaymentMethod === 'credit' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600'"
                            class="py-1.5 px-2 rounded text-xs font-semibold text-center transition">Credit</button>
                        <button type="button" @click="selectCustomerPaymentMethod('cash')"
                            :class="customerPaymentMethod === 'cash' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600'"
                            class="py-1.5 px-2 rounded text-xs font-semibold text-center transition">Cash</button>
                        <button type="button" @click="selectCustomerPaymentMethod('mobile')"
                            :class="customerPaymentMethod === 'mobile' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600'"
                            class="py-1.5 px-2 rounded text-xs font-semibold text-center transition">Mobile</button>
                    </div>
                </div>

                <div x-show="customerPaymentMethod !== 'credit'" class="space-y-3 pt-2">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Amount Paid by Customer</label>
                        <input type="number" step="0.01" x-model.number="customerAmountPaid"
                            class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-1 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Inflow Account</label>
                        <select x-model="customerAccountId"
                            class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-1 focus:ring-blue-500">
                            <option value="0">Default Account</option>
                            <template x-for="acc in accountsForMethod(customerPaymentMethod)" :key="acc.id">
                                <option :value="acc.id" x-text="acc.name + ' (' + formatMoney(acc.balance) + ')'"></option>
                            </template>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Supplier Financial Settlement -->
            <div class="bg-white rounded-xl shadow p-5 space-y-3">
                <h3 class="text-md font-bold text-gray-800 border-b pb-2">🏢 Supplier Payment</h3>
                
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Payment Method</label>
                    <div class="grid grid-cols-3 gap-1">
                        <button type="button" @click="selectSupplierPaymentMethod('credit')"
                            :class="supplierPaymentMethod === 'credit' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600'"
                            class="py-1.5 px-2 rounded text-xs font-semibold text-center transition">Credit</button>
                        <button type="button" @click="selectSupplierPaymentMethod('cash')"
                            :class="supplierPaymentMethod === 'cash' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600'"
                            class="py-1.5 px-2 rounded text-xs font-semibold text-center transition">Cash</button>
                        <button type="button" @click="selectSupplierPaymentMethod('mobile')"
                            :class="supplierPaymentMethod === 'mobile' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600'"
                            class="py-1.5 px-2 rounded text-xs font-semibold text-center transition">Mobile</button>
                    </div>
                </div>

                <div x-show="supplierPaymentMethod !== 'credit'" class="space-y-3 pt-2">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Amount We Paid Supplier</label>
                        <input type="number" step="0.01" x-model.number="supplierAmountPaid"
                            class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-1 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Outflow Account</label>
                        <select x-model="supplierAccountId"
                            class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-1 focus:ring-blue-500">
                            <option value="0">Default Account</option>
                            <template x-for="acc in accountsForMethod(supplierPaymentMethod)" :key="acc.id">
                                <option :value="acc.id" x-text="acc.name + ' (' + formatMoney(acc.balance) + ')'"></option>
                            </template>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="button" @click="completeDelivery()" :disabled="isProcessing"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 px-4 rounded-xl shadow-lg transition flex justify-center items-center gap-2">
                <span x-show="isProcessing">⏳ Processing...</span>
                <span x-show="!isProcessing">🚚 Record Distributor Delivery</span>
            </button>
        </div>

    </div>
</div>

<script>
    function distributorForm() {
        return {
            cart: [],
            productResults: [],
            productSearch: '',
            supplierId: '',
            customerId: '',
            selectedSupplier: null,
            selectedCustomer: null,
            discountPct: 0,
            vatPercent: 0,
            notes: '',
            
            supplierPaymentMethod: 'credit',
            supplierAmountPaid: 0,
            supplierAccountId: 0,

            customerPaymentMethod: 'credit',
            customerAmountPaid: 0,
            customerAccountId: 0,

            isProcessing: false,
            allAccounts: <?= json_encode($financialAccounts) ?>,

            init() {
                this.$nextTick(() => {
                    const el = document.getElementById('product-search');
                    if (el) el.focus();
                });
            },

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
                        purchase_cost: parseFloat(product.cost_price) || 0,
                        selling_price: parseFloat(product.selling_price) || 0,
                        quantity: 1
                    });
                }
                this.productSearch = '';
                this.productResults = [];
                this.updateAmounts();
            },

            removeFromCart(idx) {
                this.cart.splice(idx, 1);
                this.updateAmounts();
            },

            changeQty(idx, delta) {
                const item = this.cart[idx];
                const newQty = Math.round((item.quantity + delta) * 100) / 100;
                if (newQty < 0.01) {
                    this.removeFromCart(idx);
                    return;
                }
                item.quantity = newQty;
                this.updateAmounts();
            },

            validateQty(idx) {
                const item = this.cart[idx];
                if (item.quantity < 0.01) item.quantity = 0.01;
                this.updateAmounts();
            },

            clearCart() {
                if (confirm('Are you sure you want to clear the form?')) {
                    this.cart = [];
                    this.supplierId = '';
                    this.customerId = '';
                    this.selectedSupplier = null;
                    this.selectedCustomer = null;
                    this.discountPct = 0;
                    this.vatPercent = 0;
                    this.notes = '';
                    this.supplierPaymentMethod = 'credit';
                    this.customerPaymentMethod = 'credit';
                    this.updateAmounts();
                }
            },

            purchaseSubtotal() {
                return this.cart.reduce((s, i) => s + (i.purchase_cost * i.quantity), 0);
            },

            saleSubtotal() {
                return this.cart.reduce((s, i) => s + (i.selling_price * i.quantity), 0);
            },

            supplierGrandTotal() {
                const sub = this.purchaseSubtotal();
                const disc = sub * (this.discountPct / 100);
                const vat = (sub - disc) * (this.vatPercent / 100);
                return Math.max(0, sub - disc + vat);
            },

            customerGrandTotal() {
                const sub = this.saleSubtotal();
                const disc = sub * (this.discountPct / 100);
                const vat = (sub - disc) * (this.vatPercent / 100);
                return Math.max(0, sub - disc + vat);
            },

            netProfit() {
                return Math.max(0, this.customerGrandTotal() - this.supplierGrandTotal());
            },

            updateAmounts() {
                if (this.supplierPaymentMethod !== 'credit') {
                    this.supplierAmountPaid = this.supplierGrandTotal();
                }
                if (this.customerPaymentMethod !== 'credit') {
                    this.customerAmountPaid = this.customerGrandTotal();
                }
            },

            selectSupplier() {
                const select = document.querySelector('select[x-model="supplierId"]');
                const option = select.options[select.selectedIndex];
                this.selectedSupplier = option && option.value ? { id: option.value } : null;
            },

            selectCustomer() {
                const select = document.querySelector('select[x-model="customerId"]');
                const option = select.options[select.selectedIndex];
                this.selectedCustomer = option && option.value ? { id: option.value } : null;
            },

            selectSupplierPaymentMethod(method) {
                this.supplierPaymentMethod = method;
                const list = this.accountsForMethod(method);
                this.supplierAccountId = list.length > 0 ? list[0].id : 0;
                this.updateAmounts();
            },

            selectCustomerPaymentMethod(method) {
                this.customerPaymentMethod = method;
                const list = this.accountsForMethod(method);
                this.customerAccountId = list.length > 0 ? list[0].id : 0;
                this.updateAmounts();
            },

            accountsForMethod(method) {
                const typeMap = { cash: 'cash', mobile: 'mobile_money', bank: 'bank' };
                const type = typeMap[method];
                if (!type) return [];
                return this.allAccounts.filter(a => a.type === type);
            },

            completeDelivery() {
                if (this.cart.length === 0) {
                    alert('Cart is empty');
                    return;
                }
                if (!this.supplierId || !this.customerId) {
                    alert('Please select both a supplier and a customer');
                    return;
                }

                this.isProcessing = true;

                const payload = {
                    supplier_id: this.supplierId,
                    customer_id: this.customerId,
                    items: this.cart.map(i => ({
                        product_id: i.product_id,
                        quantity: i.quantity,
                        purchase_cost: i.purchase_cost,
                        selling_price: i.selling_price
                    })),
                    supplier_payment_method: this.supplierPaymentMethod,
                    supplier_amount_paid: this.supplierAmountPaid,
                    supplier_account_id: this.supplierAccountId,

                    customer_payment_method: this.customerPaymentMethod,
                    customer_amount_paid: this.customerAmountPaid,
                    customer_account_id: this.customerAccountId,

                    discount_pct: this.discountPct,
                    vat_pct: this.vatPercent,
                    notes: this.notes
                };

                fetch('<?= BASE_URL ?>/distributor/complete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
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
                    alert('Network error, please try again.');
                });
            },

            formatMoney(amount) {
                amount = parseFloat(amount) || 0;
                return '<?= CURRENCY_HOLDER ?> ' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            }
        };
    }
</script>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
