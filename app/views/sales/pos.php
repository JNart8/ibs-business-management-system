<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="h-screen-safe" x-data="pos()" x-init="init()">

    <!-- Top Bar -->
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold text-gray-800">🛒 Point of Sale</h1>
        <div class="flex gap-2">
            <a href="<?= BASE_URL ?>/sales"
                class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm transition">
                Sales History
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

            <!-- Customer Selector -->
            <div class="bg-white rounded-lg shadow p-3">
                <div class="flex items-center gap-3">
                    <div class="text-gray-500 text-sm font-medium whitespace-nowrap">👤 Customer:</div>
                    <div class="flex-1 relative">
                        <input type="text"
                            placeholder="<?= $walkIn ? 'Walk-in (default) — or search customer...' : 'Search or select a customer...' ?>"
                            x-model="customerSearch"
                            @input.debounce.300ms="searchCustomers()"
                            @focus="showCustomerDrop = true"
                            @blur.delay.200ms="showCustomerDrop = false"
                            autocomplete="off"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm
                                      focus:ring-2 focus:ring-blue-500 focus:border-transparent">

                        <!-- Customer Dropdown -->
                        <div x-show="showCustomerDrop && customerResults.length > 0"
                            class="absolute z-20 w-full bg-white border border-gray-200 rounded-lg
                                    shadow-lg mt-1 max-h-48 overflow-y-auto">
                            <template x-for="c in customerResults" :key="c.id">
                                <div @mousedown="selectCustomer(c)"
                                    class="px-4 py-3 hover:bg-blue-50 cursor-pointer border-b last:border-0">
                                    <div class="font-medium text-gray-800 text-sm" x-text="c.full_name"></div>
                                    <div class="text-xs text-gray-400 flex gap-2">
                                        <span x-text="c.phone"></span>
                                        <span x-show="c.current_balance != 0"
                                            :class="c.current_balance < 0 ? 'text-red-500' : 'text-green-500'"
                                            x-text="c.current_balance < 0 ? 'Owes ' + formatMoney(Math.abs(c.current_balance)) 
                                            : 'Deposit ' + formatMoney(c.current_balance)">
                                        </span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Selected Customer Badge -->
                    <div x-show="selectedCustomer" class="flex items-center gap-2 bg-blue-50 px-3 py-1.5 rounded-lg">
                        <span class="text-sm font-semibold text-blue-700" x-text="selectedCustomer?.full_name"></span>
                        <button @click="resetCustomer()" class="text-gray-400 hover:text-red-500 text-lg leading-none">&times;</button>
                    </div>
                </div>

                <!-- Balance info -->
                <div x-show="selectedCustomer && selectedCustomer.current_balance != 0"
                    class="mt-2 text-xs px-3 py-1.5 rounded"
                    :class="selectedCustomer.current_balance < 0 ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600'">
                    <span x-show="selectedCustomer.current_balance < 0">
                        ⚠️ This customer owes <strong x-text="formatMoney(Math.abs(selectedCustomer?.current_balance || 0))"></strong>
                    </span>
                    <span x-show="selectedCustomer.current_balance > 0">
                        ✓ Deposit available: <strong x-text="formatMoney(selectedCustomer?.current_balance || 0)"></strong>
                    </span>
                </div>
            </div>
            <?php if (can('sales.backdate')): ?>
                <!-- Admin: Sale Date Override -->
                <div class="bg-orange-50 border border-orange-300 rounded-lg p-2.5 mb-3">
                    <div class="flex items-center gap-3">
                        <div class="text-xl">📅</div>
                        <div class="flex-1">
                            <label class="block text-xs font-semibold text-orange-800 mb-1">
                                Sale Date (Admin Only - Optional)
                            </label>
                            <div class="flex gap-2">
                                <input type="date"
                                    x-model="customSaleDate"
                                    max="<?= date('Y-m-d') ?>"
                                    class="flex-1 px-3 py-1.5 border border-orange-300 rounded-lg text-sm
                               focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                <button @click="customSaleDate = ''"
                                    x-show="customSaleDate"
                                    class="px-3 py-1.5 bg-white border border-orange-300 rounded-lg text-sm hover:bg-orange-50 transition">
                                    Clear
                                </button>
                            </div>
                            <p class="text-xs text-orange-600 mt-1">
                                💡 Leave empty for today. Select a past date to back-date this sale.
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

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
                                        <div class="text-xs text-gray-400" x-text="'SKU: ' + p.sku + ' · Stock: ' + p.current_stock + ' ' + p.unit"></div>
                                    </div>
                                    <div class="text-right ml-4">
                                        <div class="font-bold text-green-600 text-sm"
                                            x-text="formatMoney(p.selling_price)"></div>
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
                        :disabled="p.current_stock <= 0"
                        class="bg-white rounded-lg shadow p-2 text-left hover:shadow-md
                                   hover:border-blue-400 border-2 border-transparent transition
                                   disabled:opacity-50 disabled:cursor-not-allowed">
                        <div class="font-semibold text-gray-800 text-sm truncate" x-text="p.name"></div>
                        <div class="text-xs text-gray-400 mt-0.5" x-text="p.sku"></div>
                        <div class="flex justify-between items-end mt-1.5">
                            <span class="font-bold text-green-600 text-sm" x-text="formatMoney(p.selling_price)"></span>
                            <span class="text-xs px-1.5 py-0.5 rounded"
                                :class="p.current_stock <= 0 ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-500'"
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
                        🛒 Cart
                        <span class="ml-1 bg-blue-100 text-blue-700 text-xs px-2 py-0.5 rounded-full"
                            x-text="cart.length + ' item' + (cart.length !== 1 ? 's' : '')"></span>
                    </h2>
                    <span class="text-sm text-gray-500" x-text="formatDate()"></span>
                </div>

                <!-- Column Headers - PERFECTLY ALIGNED -->
                <div x-show="cart.length > 0" class="px-2 py-2 bg-gray-50 border-b">
                    <div class="flex items-center gap-1.5 text-[10px] font-semibold text-gray-500 uppercase">
                        <!-- Product column - matches item name width -->
                        <div class="flex-1 min-w-0 pl-0">PRODUCT</div>

                        <!-- Qty column - exactly 88px to match controls -->
                        <div class="w-[88px] text-center">QTY</div>

                        <!-- Discount column - exactly 52px to match discount input + % label -->
                        <div class="w-[52px] text-center">DISC%</div>

                        <!-- Amount column - exactly 64px (w-16) to match line total -->
                        <div class="w-16 text-right pr-1">AMOUNT</div>

                        <!-- Remove button space - exactly 16px to match × button -->
                        <div class="w-4"></div>
                    </div>
                </div>

                <!-- Cart Items -->
                <div class="flex-1 overflow-y-auto divide-y" style="min-height: 200px;">
                    <div x-show="cart.length === 0" class="p-10 text-center text-gray-400">
                        <div class="text-4xl mb-2">🛒</div>
                        <p class="text-sm">Cart is empty</p>
                        <p class="text-xs mt-1">Search or click a product to add</p>
                    </div>

                    <template x-for="(item, idx) in cart" :key="item.product_id">
                        <div class="px-2 py-1.5">
                            <div class="flex items-center gap-1.5">
                                <!-- Product Name - flex-1 -->
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium text-gray-800 text-xs truncate" x-text="item.name"></div>
                                    <div class="text-[10px] text-gray-400" x-text="formatMoney(item.price)"></div>
                                </div>

                                <!-- Wider quantity control for high-volume products -->
                                <div class="w-[124px] flex items-center border rounded overflow-hidden">
                                    <button @click="changeQty(idx, -1)"
                                        class="px-1.5 py-0.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-xs font-bold">−</button>
                                    <input type="number"
                                        x-model.number="item.quantity"
                                        @change="validateQty(idx)"
                                        min="0.01" step="0.01" :max="item.stock"
                                        class="w-[76px] min-w-0 text-center text-xs py-0.5 border-x focus:outline-none">
                                    <button @click="changeQty(idx, 1)"
                                        class="px-1.5 py-0.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-xs font-bold">+</button>
                                </div>

                                <!-- Discount -->
                                <div class="w-[84px] flex items-center border rounded overflow-hidden">
                                    <input type="number"
                                        x-model.number="item.discount"
                                        min="0" :max="discountType === 'percentage' ? 100 : null" placeholder="0"
                                        class="w-14 min-w-0 text-center text-xs py-0.5 focus:outline-none"
                                        :title="discountType === 'percentage' ? 'Discount %' : 'Flat discount amount'">
                                    <span class="px-1 bg-gray-50 text-gray-500 text-[10px]"
                                        x-text="discountType === 'percentage' ? '%' : '<?= e(CURRENCY_HOLDER) ?>'"></span>
                                </div>

                                <!-- Line Total - w-16 (64px) EXACTLY -->
                                <div class="w-16 font-bold text-xs text-gray-800 text-right pr-1"
                                    x-text="formatMoney(lineTotal(item))">
                                </div>

                                <!-- Remove button - w-4 (16px) EXACTLY -->
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
                                    x-model.number="saleDiscount"
                                    min="0" :max="discountType === 'percentage' ? 100 : null" placeholder="0"
                                    class="w-20 text-center text-sm py-0.5 focus:outline-none">
                                <span class="px-1.5 bg-gray-50 text-gray-500 text-xs"
                                    x-text="discountType === 'percentage' ? '%' : '<?= e(CURRENCY_HOLDER) ?>'"></span>
                            </div>
                        </div>
                        <span class="text-sm text-red-500"
                            x-text="saleDiscount > 0 ? '- ' + formatMoney(discountAmount()) : '—'">
                        </span>
                    </div>

                    <!-- Total -->
                    <div class="flex justify-between font-bold text-xl border-t pt-2">
                        <span>TOTAL</span>
                        <span class="text-green-600" x-text="formatMoney(grandTotal())"></span>
                    </div>
                </div>

                <!-- Payment Section -->
                <div class="border-t px-4 py-3 space-y-3">

                    <!-- Payment Method -->
                    <div class="grid grid-cols-5 gap-1.5">
                        <?php
                        $methods = [
                            'cash' => '💵 Cash',
                            'mobile' => '📱 Mobile',
                            'bank' => '🏦 Bank',
                            'deposit' => '💰 Deposit',
                            'credit' => '📝 Credit'
                        ];
                        foreach ($methods as $val => $label): ?>
                            <button @click="selectPaymentMethod('<?= $val ?>')"
                                <?php if ($val === 'credit' || $val === 'deposit'): ?>
                                :disabled="selectedCustomer?.is_default == 1 <?= $val === 'deposit' ? '|| (selectedCustomer?.current_balance || 0) <= 0' : '' ?>"
                                <?php endif; ?>
                                :class="paymentMethod === '<?= $val ?>'
                        ? 'bg-blue-600 text-white border-blue-600'
                        : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
                                class="border rounded-lg py-2 text-xs font-semibold transition text-center
                       disabled:opacity-40 disabled:cursor-not-allowed">
                                <?= $label ?>
                                <?php if ($val === 'deposit'): ?>
                                    <div class="text-[10px] mt-0.5"
                                        x-show="selectedCustomer && !selectedCustomer.is_default"
                                        :class="(selectedCustomer?.current_balance || 0) > 0 ? 'text-green-300' : 'text-red-300'"
                                        x-text="formatMoney(selectedCustomer?.current_balance || 0)">
                                    </div>
                                <?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <!-- Account Selection Dropdown -->
                    <div x-show="['cash', 'mobile', 'bank'].includes(paymentMethod)" class="transition-all duration-200">
                        <label class="block text-xs text-gray-500 mb-1">Deposit Account</label>
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

                    <!-- Deposit Balance Info -->
                    <div x-show="paymentMethod === 'deposit' && selectedCustomer && !selectedCustomer.is_default"
                        class="p-2 rounded text-xs"
                        :class="(selectedCustomer?.current_balance || 0) >= grandTotal() ? 'bg-green-50 text-green-700' : 'bg-orange-50 text-orange-700'">
                        <div class="flex justify-between items-center">
                            <span>Available Deposit:</span>
                            <span class="font-bold" x-text="formatMoney(selectedCustomer?.current_balance || 0)"></span>
                        </div>
                        <div x-show="(selectedCustomer?.current_balance || 0) < grandTotal()" class="mt-1">
                            ⚠️ Insufficient balance. Will apply partial payment of <span class="font-bold" x-text="formatMoney(Math.min(selectedCustomer?.current_balance || 0, grandTotal()))"></span>
                        </div>
                    </div>

                    <!-- Amount Paid (hide for full credit) -->
                    <div x-show="paymentMethod !== 'credit' && paymentMethod !== 'deposit'">
                        <label class="block text-xs text-gray-500 mb-1">Amount Tendered</label>
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
                            <template x-for="q in quickAmounts()" :key="q">
                                <button @click="amountPaid = q"
                                    class="flex-1 py-1.5 bg-gray-100 hover:bg-gray-200 rounded text-xs font-semibold transition"
                                    x-text="formatMoney(q)">
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- Change / Due / Deposit Info -->
                    <div class="flex justify-between text-sm font-semibold">
                        <!-- Cash/Mobile/Bank -->
                        <template x-if="paymentMethod !== 'credit' && paymentMethod !== 'deposit'">
                            <template x-if="amountPaid >= grandTotal()">
                                <div class="flex justify-between w-full">
                                    <span class="text-gray-600">Change</span>
                                    <span class="text-blue-600" x-text="formatMoney(amountPaid - grandTotal())"></span>
                                </div>
                            </template>
                        </template>
                        <template x-if="paymentMethod !== 'credit' && paymentMethod !== 'deposit' && amountPaid < grandTotal()">
                            <div class="flex justify-between w-full">
                                <span class="text-gray-600">Balance Due</span>
                                <span class="text-red-500" x-text="formatMoney(grandTotal() - amountPaid)"></span>
                            </div>
                        </template>

                        <!-- Credit -->
                        <template x-if="paymentMethod === 'credit'">
                            <div class="flex justify-between w-full">
                                <span class="text-gray-600">On Credit</span>
                                <span class="text-orange-500" x-text="formatMoney(grandTotal())"></span>
                            </div>
                        </template>

                        <!-- Deposit -->
                        <template x-if="paymentMethod === 'deposit'">
                            <div class="flex justify-between w-full flex-col gap-1">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">From Deposit</span>
                                    <span class="text-green-600" x-text="formatMoney(Math.min(selectedCustomer?.current_balance || 0, grandTotal()))"></span>
                                </div>
                                <div x-show="(selectedCustomer?.current_balance || 0) < grandTotal()" class="flex justify-between text-xs">
                                    <span class="text-gray-500">Still Owed</span>
                                    <span class="text-red-500" x-text="formatMoney(grandTotal() - (selectedCustomer?.current_balance || 0))"></span>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Notes -->
                    <input type="text"
                        x-model="saleNotes"
                        placeholder="Notes (optional)..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm
                                  focus:ring-2 focus:ring-blue-500">

                    <!-- Complete Sale Button -->
                    <button @click="completeSale()"
                        :disabled="cart.length === 0 || isProcessing"
                        class="w-full py-4 bg-green-600 hover:bg-green-700 disabled:bg-gray-300
                                   disabled:cursor-not-allowed text-white font-bold text-lg
                                   rounded-lg shadow transition flex items-center justify-center gap-2">
                        <span x-show="!isProcessing">✓ Complete Sale</span>
                        <span x-show="isProcessing" class="flex items-center gap-2">
                            <div class="spinner w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                            Processing...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ====================================================
         RECEIPT MODAL — shows after sale completes
    ==================================================== -->
    <div x-show="showReceiptModal" x-cloak
        class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 p-4 overflow-y-auto"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0">

        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md my-8"
            @click.outside="closeReceiptModal()"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95">

            <!-- Modal Header -->
            <div class="flex items-center justify-between p-4 border-b">
                <div>
                    <h2 class="text-xl font-bold text-gray-800">✅ Sale Complete!</h2>
                    <p class="text-gray-500 text-xs mt-1" x-text="lastSale?.sale_number"></p>
                </div>
                <button @click="closeReceiptModal()"
                    class="text-gray-400 hover:text-gray-600 text-2xl leading-none">
                    &times;
                </button>
            </div>

            <!-- Receipt Content (inside modal) -->
            <div class="p-4 max-h-[70vh] overflow-y-auto" id="receipt-content">
                <div class="receipt bg-white" style="font-family: 'Courier New', monospace; max-width: 70mm; margin: 0 auto;">

                    <!-- Store Header -->
                    <div class="text-center mb-4">
                        <img src="<?= BASE_URL ?>/assets/images/ibs_logo_ntg.png"
                            alt="<?= APP_NAME ?>"
                            class="h-12 mx-auto mb-2">
                        <h3 class="text-lg font-bold"><?= e($settings['store_name'] ?? APP_NAME) ?></h3>
                        <?php if (!empty($settings['store_address'])): ?>
                            <p class="text-xs text-gray-600"><?= e($settings['store_address']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($settings['store_phone'])): ?>
                            <p class="text-xs text-gray-600">Tel: <?= e($settings['store_phone']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($settings['store_email'])): ?>
                            <p class="text-xs text-gray-600"><?= e($settings['store_email']) ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Sale Info -->
                    <div class="border-t border-b border-dashed py-3 mb-3">
                        <div class="grid grid-cols-2 gap-1 text-xs">
                            <div>
                                <span class="text-gray-600">Receipt:</span>
                                <span class="font-bold ml-1" x-text="lastSale?.sale_number"></span>
                            </div>
                            <div class="text-right">
                                <span class="text-gray-600">Date:</span>
                                <span class="ml-1" x-text="formatReceiptDate(lastSale?.sale_date)"></span>
                            </div>
                            <div>
                                <span class="text-gray-600">Cashier:</span>
                                <span class="ml-1" x-text="lastSale?.cashier_name || 'Staff'"></span>
                            </div>
                            <div class="text-right">
                                <span class="text-gray-600">Customer:</span>
                                <span class="ml-1" x-text="lastSale?.customer_name || 'Walk-in'"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Items - FIXED PREVIEW -->
                    <!-- Items Section -->
                    <div class="mb-3">
                        <!-- Header -->
                        <div class="flex text-[10px] font-bold text-gray-600 mb-1 border-b pb-1">
                            <div class="w-[45%]">Item</div>
                            <div class="w-[15%] text-right">Qty</div>
                            <div class="w-[20%] text-right">Price</div>
                            <div class="w-[20%] text-right">Total</div>
                        </div>

                        <!-- Items or Empty State -->
                        <template x-if="lastSale?.items && lastSale.items.length > 0">
                            <div>
                                <template x-for="item in lastSale.items" :key="item.product_id || item.id">
                                    <div class="flex text-xs mb-1">
                                        <div class="w-[45%] pr-1">
                                            <div x-text="item.product_name || item.name" class="truncate"></div>
                                            <div x-show="(item.discount_amount || 0) > 0"
                                                class="text-[10px] text-green-600"
                                                x-text="item.discount_type === 'flat' ? '-' + formatMoney(item.discount_amount) : '-' + (item.discount_percent || 0) + '%'">
                                            </div>
                                        </div>
                                        <div class="w-[15%] text-right" x-text="item.quantity || 0"></div>
                                        <div class="w-[20%] text-right" x-text="formatMoney(item.unit_price || item.price || 0)"></div>
                                        <div class="w-[20%] text-right font-medium" x-text="formatMoney(item.line_total || 0)"></div>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <template x-if="!lastSale?.items || lastSale.items.length === 0">
                            <div class="text-xs text-gray-400 text-center py-4">
                                No items to display
                            </div>
                        </template>
                    </div>

                    <!-- Totals -->
                    <div class="border-t border-dashed pt-3 mb-3">
                        <div class="space-y-1">
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-600">Subtotal:</span>
                                <span x-text="formatMoney(lastSale?.subtotal || 0)"></span>
                            </div>
                            <div x-show="(lastSale?.discount_amount || 0) > 0" class="flex justify-between text-xs">
                                <span class="text-gray-600">Discount:</span>
                                <span class="text-green-600" x-text="'-' + formatMoney(lastSale?.discount_amount)"></span>
                            </div>
                            <div class="flex justify-between font-bold text-base pt-1 border-t">
                                <span>TOTAL:</span>
                                <span class="text-green-600" x-text="formatMoney(lastSale?.total_amount || 0)"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Info -->
                    <div class="border-t border-dashed pt-3 mb-3">
                        <div class="space-y-1">
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-600">Payment:</span>
                                <span class="font-medium" x-text="lastSale?.payment_method ? lastSale.payment_method.charAt(0).toUpperCase() + lastSale.payment_method.slice(1) : ''"></span>
                            </div>
                            <div class="flex justify-between text-xs" x-show="lastSale?.payment_method === 'cash'">
                                <span class="text-gray-600">Amount Paid:</span>
                                <span x-text="formatMoney(lastSale?.amount_paid || 0)"></span>
                            </div>
                            <div class="flex justify-between text-xs" x-show="lastSale?.payment_method === 'cash' && (lastSale?.change || 0) > 0">
                                <span class="text-gray-600">Change:</span>
                                <span class="text-blue-600" x-text="formatMoney(lastSale?.change)"></span>
                            </div>
                            <div class="flex justify-between text-xs" x-show="(lastSale?.amount_due || 0) > 0">
                                <span class="text-gray-600 text-red-600">Due:</span>
                                <span class="text-red-600 font-bold" x-text="formatMoney(lastSale?.amount_due)"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="text-center border-t border-dashed pt-3">
                        <p class="font-bold text-xs mb-1">Thank You!</p>
                        <p class="text-xs text-gray-600 mb-1">Please Come Again</p>
                        <p class="text-[10px] text-gray-500">* No refunds without receipt</p>
                        <div class="mt-2 font-mono text-[10px] text-gray-400" x-text="lastSale?.sale_number"></div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer Actions -->
            <div class="flex gap-2 p-4 border-t bg-gray-50 rounded-b-2xl">
                <button @click="printReceipt()"
                    :disabled="printingReceipt"
                    class="flex-1 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-semibold rounded-lg transition">
                    <span x-show="!printingReceipt">🖨️ Print</span>
                    <span x-show="printingReceipt">⏳ Printing...</span>
                </button>
                <button @click="newSale()"
                    class="flex-1 py-2 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg transition flex items-center justify-center gap-1 text-sm">
                    ➕ New
                </button>
                <button @click="closeReceiptModal()"
                    class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold rounded-lg transition text-sm">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Hidden print iframe -->
    <iframe id="print-frame" style="display: none;"></iframe>

</div>

<script>
    function pos() {
        return {
            // State
            cart: [],
            quickProducts: <?= json_encode($quickProducts) ?>,
            productResults: [],
            productSearch: '',
            customerSearch: '',
            customerResults: [],
            showCustomerDrop: false,
            selectedCustomer: <?= json_encode($walkIn) ?>,
            saleDiscount: 0,
            discountType: <?= json_encode(($settings['sale_discount_type'] ?? 'percentage') === 'flat' ? 'flat' : 'percentage') ?>,
            paymentMethod: 'cash',
            amountPaid: 0,
            saleNotes: '',
            activeCategory: null,
            isProcessing: false,
            showReceiptModal: false,
            lastSale: null,
            customSaleDate: '',   // Admin-only: custom sale date
            // Financial account selection
            allAccounts: <?= json_encode($financialAccounts) ?>,
            selectedAccountId: 0, // 0 = use default fallback

            init() {
                // Focus search on load
                this.$nextTick(() => {
                    const el = document.getElementById('product-search');
                    if (el) el.focus();
                });

                // Initialize default account for default payment method ('cash')
                this.autoSelectAccount(this.paymentMethod);

                // Print keyboard shortcut
                document.addEventListener('keydown', (e) => {
                    if ((e.ctrlKey || e.metaKey) && e.key === 'p' && this.showReceiptModal) {
                        e.preventDefault();
                        this.printReceipt();
                    }
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
                    this.quickProducts = d.slice(0, 12);
                    this.productResults = [];
                });
            },

            // ── Cart ────────────────────────────────────────
            addToCart(product) {
                if (product.current_stock <= 0) {
                    alert(product.name + ' is out of stock!');
                    return;
                }
                const existing = this.cart.find(i => i.product_id === product.id);
                if (existing) {
                    if (existing.quantity < product.current_stock) existing.quantity++;
                    else alert('Maximum stock reached for ' + product.name);
                } else {
                    this.cart.push({
                        product_id: product.id,
                        name: product.name,
                        sku: product.sku,
                        unit: product.unit,
                        price: parseFloat(product.selling_price),
                        quantity: 1,
                        stock: parseFloat(product.current_stock),
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
                const newQty = Math.round((item.quantity + delta) * 100) / 100;
                if (newQty < 0.01) {
                    this.removeFromCart(idx);
                    return;
                }
                if (newQty > item.stock) {
                    alert('Not enough stock!');
                    return;
                }
                item.quantity = newQty;
                this.updateAmountPaid();
            },

            validateQty(idx) {
                const item = this.cart[idx];
                if (item.quantity < 0.01) item.quantity = 0.01;
                if (item.quantity > item.stock) item.quantity = item.stock;
                this.updateAmountPaid();
            },

            clearCart() {
                if (this.cart.length === 0 || confirm('Clear the cart?')) {
                    this.cart = [];
                    this.saleDiscount = 0;
                    this.saleNotes = '';
                    this.updateAmountPaid();
                }
            },

            // ── Calculations ────────────────────────────────
            lineTotal(item) {
                const lineSubtotal = item.price * item.quantity;
                const discount = Math.max(0, Number(item.discount) || 0);
                const amount = this.discountType === 'flat'
                    ? Math.min(lineSubtotal, discount)
                    : lineSubtotal * (discount / 100);
                return lineSubtotal - amount;
            },

            subtotal() {
                return this.cart.reduce((s, i) => s + this.lineTotal(i), 0);
            },

            discountAmount() {
                const value = Math.max(0, Number(this.saleDiscount) || 0);
                return this.discountType === 'flat'
                    ? Math.min(this.subtotal(), value)
                    : this.subtotal() * (value / 100);
            },

            grandTotal() {
                return Math.max(0, this.subtotal() - this.discountAmount());
            },

            quickAmounts() {
                const total = this.grandTotal();
                const round = (n) => Math.ceil(total / n) * n;
                return [round(10), round(20), round(50)].filter((v, i, a) => a.indexOf(v) === i && v > total).slice(0, 3);
            },

            updateAmountPaid() {
                if (this.paymentMethod !== 'credit') {
                    this.amountPaid = this.grandTotal();
                }
            },

            selectPaymentMethod(method) {
                this.paymentMethod = method;
                // Auto-select the first matching account for this payment method
                this.autoSelectAccount(method);

                // If deposit selected, set amount paid to available deposit or total (whichever is less)
                if (method === 'deposit' && this.selectedCustomer) {
                    const availableDeposit = parseFloat(this.selectedCustomer.current_balance) || 0;
                    this.amountPaid = Math.min(availableDeposit, this.grandTotal());
                } else if (method !== 'credit') {
                    this.updateAmountPaid();
                }
            },

            // Returns accounts filtered for the active payment method
            accountsForMethod(method) {
                const typeMap = { cash: 'cash', mobile: 'mobile_money', bank: 'bank' };
                const type = typeMap[method];
                if (!type) return [];
                return this.allAccounts.filter(a => a.type === type);
            },

            // Auto-select the first account of the matching type
            autoSelectAccount(method) {
                const list = this.accountsForMethod(method);
                this.selectedAccountId = list.length > 0 ? list[0].id : 0;
            },

            // ── Customer ────────────────────────────────────
            searchCustomers() {
                if (!this.customerSearch) {
                    this.customerResults = [];
                    return;
                }
                fetch('<?= BASE_URL ?>/customers/search?q=' + encodeURIComponent(this.customerSearch))
                    .then(r => r.json())
                    .then(d => this.customerResults = d);
            },

            selectCustomer(c) {
                this.selectedCustomer = c;
                this.customerSearch = '';
                this.customerResults = [];
                this.showCustomerDrop = false;
            },

            resetCustomer() {
                this.selectedCustomer = <?= json_encode($walkIn) ?>;
                this.customerSearch = '';
            },

            // ── Complete Sale ────────────────────────────────
            completeSale() {
                if (this.cart.length === 0) return;
                if (!this.selectedCustomer) {
                    alert('Please select a customer');
                    return;
                }

                // Validate deposit payment
                if (this.paymentMethod === 'deposit') {
                    const availableDeposit = parseFloat(this.selectedCustomer.current_balance) || 0;
                    if (availableDeposit <= 0) {
                        alert('Customer has no deposit balance available');
                        return;
                    }
                    // Set amount paid to minimum of deposit or total
                    this.amountPaid = Math.min(availableDeposit, this.grandTotal());
                }

                const total = this.grandTotal();
                const paid = (this.paymentMethod === 'credit') ? 0 :
                    (this.paymentMethod === 'deposit') ? Math.min(parseFloat(this.selectedCustomer.current_balance) || 0, total) :
                    parseFloat(this.amountPaid) || 0;

                if (this.paymentMethod !== 'credit' && paid <= 0) {
                    alert('Please enter the amount paid');
                    return;
                }

                this.isProcessing = true;

                const payload = {
                    customer_id: this.selectedCustomer.id,
                    items: this.cart.map(i => ({
                        product_id: i.product_id,
                        quantity: i.quantity,
                        price: i.price,
                        discount: i.discount || 0,
                    })),
                    discount_value: this.saleDiscount,
                    payment_method: this.paymentMethod,
                    amount_paid: paid,
                    notes: this.saleNotes,
                    sale_date: this.customSaleDate || null,
                    account_id: (['cash','mobile','bank'].includes(this.paymentMethod)) ? this.selectedAccountId : 0,
                };

                fetch('<?= BASE_URL ?>/sales/complete', {
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
                            this.lastSale = data;
                            this.showReceiptModal = true;
                            if (data.financial_accounts) {
                                this.allAccounts = data.financial_accounts;
                            }
                            if (data.customer && this.selectedCustomer && this.selectedCustomer.id === data.customer.id) {
                                this.selectedCustomer = data.customer;
                            }
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(() => {
                        this.isProcessing = false;
                        alert('Network error. Please try again.');
                    });
            },

            // ── Receipt Modal Functions ───────────────────────
            printingReceipt: false,

            printReceipt() {
                if (!this.lastSale || this.printingReceipt) return;

                this.printingReceipt = true;

                const receiptHTML = this.generateReceiptHTML();
                const printFrame = document.getElementById('print-frame');
                const frameDoc = printFrame.contentWindow || printFrame.contentDocument;

                frameDoc.document.open();
                frameDoc.document.write(receiptHTML);
                frameDoc.document.close();

                setTimeout(() => {
                    frameDoc.focus();
                    frameDoc.print();

                    // Refocus after print
                    setTimeout(() => {
                        this.printingReceipt = false;
                        document.getElementById('product-search')?.focus();
                    }, 500);
                }, 250);
            },

            generateReceiptHTML() {
                const sale = this.lastSale;
                if (!sale) return '';

                const formatMoney = (amount) => {
                    amount = parseFloat(amount) || 0;
                    return '<?= CURRENCY_HOLDER ?> ' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                };

                const formatDate = (dateStr) => {
                    if (!dateStr) return '';
                    const d = new Date(dateStr);
                    return d.toLocaleDateString('en-GH', {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                };

                let itemsHTML = '';
                if (sale.items && sale.items.length) {
                    sale.items.forEach(item => {
                        itemsHTML += `
                <tr>
                    <td style="padding: 3px 0;">${item.product_name}${item.discount_amount > 0 ? `<br>   (${item.discount_type === 'flat' ? '-' + formatMoney(item.discount_amount) : '-' + item.discount_percent + '%'})` : ''}</td>
                    <td style="padding: 3px 0; text-align: right;">${item.quantity}</td>
                    <td style="padding: 3px 0; text-align: right;">${formatMoney(item.unit_price)}</td>
                    <td style="padding: 3px 0; text-align: right;">${formatMoney(item.line_total)}</td>
                </tr>
            `;
                    });
                }

                return `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Receipt ${sale.sale_number}</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: 'Courier New', monospace;
                    font-size: 12px;
                    color: #000;
                    max-width: 80mm;
                    margin: 0 auto;
                    padding: 10px;
                }
                .center { text-align: center; }
                .divider { 
                    border-top: 1px dashed #000; 
                    margin: 10px 0; 
                }
                .row { 
                    display: flex; 
                    justify-content: space-between; 
                    margin: 5px 0; 
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 5px 0;
                }
                th {
                    text-align: left;
                    border-bottom: 1px dashed #000;
                    padding: 5px 0 3px 0;
                    font-size: 10px;
                    font-weight: bold;
                }
                td {
                    padding: 3px 0;
                    vertical-align: top;
                    font-size: 11px;
                }
                .total-row {
                    border-top: 2px solid #000;
                    font-weight: bold;
                    font-size: 14px;
                    padding-top: 5px;
                }
                @media print {
                    body { padding: 0; }
                    @page { margin: 0; }
                }
            </style>
        </head>
        <body>
            <div class="center">
                <img src="<?= BASE_URL ?>/assets/images/ibs_logo_ntg.png" style="width: 60px; margin-bottom: 5px;">
                <h3 style="font-size: 16px; margin: 5px 0;"><?= e($settings['store_name'] ?? APP_NAME) ?></h3>
                <?php if (!empty($settings['store_address'])): ?>
                <p style="font-size: 11px; margin: 2px 0;"><?= e($settings['store_address']) ?></p>
                <?php endif; ?>
                <?php if (!empty($settings['store_phone'])): ?>
                <p style="font-size: 11px; margin: 2px 0;">Tel: <?= e($settings['store_phone']) ?></p>
                <?php endif; ?>
            </div>
            
            <div class="divider"></div>
            
            <div class="row">
                <span>Receipt:</span>
                <span class="bold" style="font-weight: bold;">${sale.sale_number}</span>
            </div>
            <div class="row">
                <span>Date:</span>
                <span>${formatDate(sale.sale_date)}</span>
            </div>
            <div class="row">
                <span>Cashier:</span>
                <span>${sale.cashier_name || 'Staff'}</span>
            </div>
            <div class="row">
                <span>Customer:</span>
                <span>${sale.customer_name || 'Walk-in Customer'}</span>
            </div>
            
            <div class="divider"></div>
            
            <table>
                <thead>
                    <tr>
                        <th style="text-align: left; width: 45%;">Item</th>
                        <th style="text-align: right; width: 15%;">Qty</th>
                        <th style="text-align: right; width: 20%;">Price</th>
                        <th style="text-align: right; width: 20%;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    ${itemsHTML}
                </tbody>
            </table>
            
            <div class="divider"></div>
            
            <div class="row">
                <span>Subtotal:</span>
                <span>${formatMoney(sale.subtotal || 0)}</span>
            </div>
            ${sale.discount_amount > 0 ? `
            <div class="row">
                <span>Discount${sale.discount_type === 'percentage' ? ` (${sale.discount_percent}%)` : ''}:</span>
                <span>-${formatMoney(sale.discount_amount)}</span>
            </div>
            ` : ''}
            
            <div class="divider"></div>
            
            <div class="row" style="font-weight: bold; font-size: 16px;">
                <span>TOTAL:</span>
                <span>${formatMoney(sale.total_amount || 0)}</span>
            </div>
            
            <div class="row">
                <span>Paid (${sale.payment_method ? sale.payment_method.charAt(0).toUpperCase() + sale.payment_method.slice(1) : ''}):</span>
                <span>${formatMoney(sale.amount_paid || 0)}</span>
            </div>
            ${sale.change > 0 ? `
            <div class="row">
                <span>Change:</span>
                <span>${formatMoney(sale.change)}</span>
            </div>
            ` : ''}
            ${sale.amount_due > 0 ? `
            <div class="row" style="font-weight: bold;">
                <span>BALANCE DUE:</span>
                <span style="color: #dc2626;">${formatMoney(sale.amount_due)}</span>
            </div>
            ` : ''}
            
            <div class="divider"></div>
            
            <div class="center">
                <p style="font-weight: bold; margin: 5px 0; font-size: 13px;">Thank You For Your Business!</p>
                <p style="margin: 3px 0; font-size: 11px;">Please Come Again</p>
                <p style="font-size: 10px; margin: 2px 0;">* No refunds without receipt</p>
                <p style="font-size: 10px; margin: 2px 0;">* Goods sold are not returnable</p>
                <p style="margin-top: 10px; font-size: 9px; color: #666;">${sale.sale_number}</p>
                <p style="margin-top: 5px; font-size: 9px; color: #666;"><?= date('Y') ?> <?= APP_NAME ?></p>
            </div>
        </body>
        </html>
    `;
            },

            newSale() {
                this.cart = [];
                this.saleDiscount = 0;
                this.paymentMethod = 'cash';
                this.autoSelectAccount('cash');
                this.amountPaid = 0;
                this.saleNotes = '';
                this.customSaleDate = ''; // Reset custom date
                this.showReceiptModal = false;
                this.lastSale = null;
                this.resetCustomer();
                this.$nextTick(() => {
                    const el = document.getElementById('product-search');
                    if (el) el.focus();
                });
            },

            closeReceiptModal() {
                this.showReceiptModal = false;
                // Clear cart and reset for new sale
                this.cart = [];
                this.saleDiscount = 0;
                this.paymentMethod = 'cash';
                this.autoSelectAccount('cash');
                this.amountPaid = 0;
                this.saleNotes = '';
                this.customSaleDate = '';
                this.lastSale = null;
                this.resetCustomer();
            },

            // ── Helpers ─────────────────────────────────────
            formatMoney(amount) {
                amount = parseFloat(amount) || 0;
                return '<?= CURRENCY_HOLDER ?> ' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            },

            formatDate() {
                return new Date().toLocaleDateString('en-GH', {
                    weekday: 'short',
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric'
                });
            },

            formatReceiptDate(dateStr) {
                if (!dateStr) return '';
                const d = new Date(dateStr);
                return d.toLocaleDateString('en-GH', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            },
        };
    }
</script>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
