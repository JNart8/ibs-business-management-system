<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="container mx-auto px-4 py-6">

    <!-- Breadcrumb & Back button -->
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-2 text-sm text-gray-500">
            <a href="<?= BASE_URL ?>/distributor" class="hover:text-blue-600 font-medium">Distributor Deliveries</a>
            <span>›</span>
            <span class="text-gray-800 font-mono"><?= e($sale['sale_number']) ?></span>
        </div>
        <a href="<?= BASE_URL ?>/distributor"
            class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm transition">
            ← Back to List
        </a>
    </div>

    <!-- Title Card -->
    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <span class="bg-blue-100 text-blue-800 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider">Distributor Direct Delivery</span>
                <h1 class="text-3xl font-extrabold text-gray-800 mt-2">Transaction Details</h1>
                <p class="text-gray-500 text-sm mt-1">
                    Recorded on <?= date('d M Y, H:i', strtotime($sale['sale_date'])) ?> by <?= e($sale['cashier_name'] ?? 'Staff') ?>
                </p>
            </div>
            <!-- Profit Badge -->
            <div class="mt-4 md:mt-0 bg-green-50 border border-green-200 p-4 rounded-xl text-center md:text-right">
                <div class="text-xs text-green-700 font-bold uppercase tracking-wider">Markup Profit Margin</div>
                <div class="text-2xl font-black text-green-600 mt-1">
                    <?= formatMoney($sale['total_amount'] - $purchase['total_amount']) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Combined Details Columns -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

        <!-- Customer Invoice Card -->
        <div class="bg-white rounded-xl shadow overflow-hidden border-t-4 border-green-500">
            <div class="p-5 bg-gray-50 border-b flex justify-between items-center">
                <h2 class="font-bold text-gray-800 text-lg">👤 Customer Sale (Inflow)</h2>
                <span class="font-mono text-sm font-bold text-green-600 bg-green-50 px-2.5 py-1 rounded">
                    <?= e($sale['sale_number']) ?>
                </span>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="text-xs text-gray-400 block font-semibold uppercase">Customer Name</span>
                        <span class="text-sm font-bold text-gray-800"><?= e($sale['customer_name']) ?></span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400 block font-semibold uppercase">Phone</span>
                        <span class="text-sm text-gray-800"><?= e($sale['customer_phone'] ?: 'N/A') ?></span>
                    </div>
                    <div class="col-span-2">
                        <span class="text-xs text-gray-400 block font-semibold uppercase">Delivery Address</span>
                        <span class="text-sm text-gray-800"><?= e($sale['customer_address'] ?: 'N/A') ?></span>
                    </div>
                </div>

                <div class="border-t pt-4">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Financial Status</h3>
                    <div class="grid grid-cols-3 gap-2">
                        <div class="bg-gray-50 p-2.5 rounded text-center">
                            <span class="text-[10px] text-gray-400 uppercase block">Total Sale</span>
                            <span class="text-sm font-bold text-gray-800"><?= formatMoney($sale['total_amount']) ?></span>
                        </div>
                        <div class="bg-gray-50 p-2.5 rounded text-center">
                            <span class="text-[10px] text-gray-400 uppercase block">Amount Paid</span>
                            <span class="text-sm font-bold text-green-600"><?= formatMoney($sale['amount_paid']) ?></span>
                        </div>
                        <div class="bg-gray-50 p-2.5 rounded text-center">
                            <span class="text-[10px] text-gray-400 uppercase block">Due / Credit</span>
                            <span class="text-sm font-bold text-red-500"><?= formatMoney($sale['amount_due']) ?></span>
                        </div>
                    </div>
                    <div class="mt-3 flex justify-between text-sm">
                        <span class="text-gray-500">Payment Status:</span>
                        <span class="font-bold uppercase text-xs px-2 py-0.5 rounded <?= $sale['payment_status'] === 'paid' ? 'bg-green-100 text-green-800' : ($sale['payment_status'] === 'partial' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                            <?= e($sale['payment_status']) ?>
                        </span>
                    </div>
                    <div class="mt-1 flex justify-between text-sm">
                        <span class="text-gray-500">Payment Method:</span>
                        <span class="font-semibold text-gray-700 uppercase"><?= e($sale['payment_method']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Supplier Purchase Card -->
        <div class="bg-white rounded-xl shadow overflow-hidden border-t-4 border-red-500">
            <div class="p-5 bg-gray-50 border-b flex justify-between items-center">
                <h2 class="font-bold text-gray-800 text-lg">🏢 Supplier Purchase (Outflow)</h2>
                <span class="font-mono text-sm font-bold text-red-600 bg-red-50 px-2.5 py-1 rounded">
                    <?= e($purchase['purchase_number']) ?>
                </span>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="text-xs text-gray-400 block font-semibold uppercase">Supplier Company</span>
                        <span class="text-sm font-bold text-gray-800"><?= e($purchase['supplier_name']) ?></span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400 block font-semibold uppercase">Phone</span>
                        <span class="text-sm text-gray-800"><?= e($purchase['supplier_phone'] ?: 'N/A') ?></span>
                    </div>
                    <div class="col-span-2">
                        <span class="text-xs text-gray-400 block font-semibold uppercase">Supplier Address</span>
                        <span class="text-sm text-gray-800"><?= e($purchase['supplier_address'] ?: 'N/A') ?></span>
                    </div>
                </div>

                <div class="border-t pt-4">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Financial Status</h3>
                    <div class="grid grid-cols-3 gap-2">
                        <div class="bg-gray-50 p-2.5 rounded text-center">
                            <span class="text-[10px] text-gray-400 uppercase block">Total Purchase</span>
                            <span class="text-sm font-bold text-gray-800"><?= formatMoney($purchase['total_amount']) ?></span>
                        </div>
                        <div class="bg-gray-50 p-2.5 rounded text-center">
                            <span class="text-[10px] text-gray-400 uppercase block">Amount Paid</span>
                            <span class="text-sm font-bold text-green-600"><?= formatMoney($purchase['amount_paid']) ?></span>
                        </div>
                        <div class="bg-gray-50 p-2.5 rounded text-center">
                            <span class="text-[10px] text-gray-400 uppercase block">Due / Credit</span>
                            <span class="text-sm font-bold text-red-500"><?= formatMoney($purchase['amount_due']) ?></span>
                        </div>
                    </div>
                    <div class="mt-3 flex justify-between text-sm">
                        <span class="text-gray-500">Payment Status:</span>
                        <span class="font-bold uppercase text-xs px-2 py-0.5 rounded <?= $purchase['payment_status'] === 'paid' ? 'bg-green-100 text-green-800' : ($purchase['payment_status'] === 'partial' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                            <?= e($purchase['payment_status']) ?>
                        </span>
                    </div>
                    <div class="mt-1 flex justify-between text-sm">
                        <span class="text-gray-500">Payment Method:</span>
                        <span class="font-semibold text-gray-700 uppercase"><?= e($purchase['payment_method']) ?></span>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Items Grid Comparison Card -->
    <div class="bg-white rounded-xl shadow overflow-hidden mb-6">
        <div class="p-5 border-b bg-gray-50">
            <h2 class="font-bold text-gray-800 text-lg">📦 Items & Margins Comparison</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-100 border-b">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase">Product</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase text-center w-24">Quantity</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase text-right w-36">Unit Cost</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase text-right w-36">Unit Selling</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase text-right w-36">Purchase Cost</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase text-right w-36">Sale Price</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase text-right w-36">Profit Margin</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($comparisonItems as $item): 
                        $itemProfit = floatval($item['sale_total']) - floatval($item['purchase_total']);
                    ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="font-semibold text-gray-800 text-sm"><?= e($item['product_name']) ?></div>
                                <div class="text-xs text-gray-400 font-mono">SKU: <?= e($item['sku']) ?></div>
                            </td>
                            <td class="px-6 py-4 text-center text-sm font-semibold text-gray-700">
                                <?= e($item['quantity']) ?>
                            </td>
                            <td class="px-6 py-4 text-right text-sm text-red-500 font-medium">
                                <?= formatMoney($item['purchase_cost']) ?>
                            </td>
                            <td class="px-6 py-4 text-right text-sm text-green-600 font-medium">
                                <?= formatMoney($item['selling_price']) ?>
                            </td>
                            <td class="px-6 py-4 text-right text-sm text-red-600 font-semibold">
                                <?= formatMoney($item['purchase_total']) ?>
                            </td>
                            <td class="px-6 py-4 text-right text-sm text-green-700 font-semibold">
                                <?= formatMoney($item['sale_total']) ?>
                            </td>
                            <td class="px-6 py-4 text-right text-sm text-blue-700 font-extrabold bg-blue-50/50">
                                <?= formatMoney($itemProfit) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Notes Card -->
    <?php if (!empty($sale['notes'])): ?>
        <div class="bg-white rounded-xl shadow p-5">
            <h3 class="text-sm font-bold text-gray-700 mb-2">📝 Notes & Delivery Details</h3>
            <p class="text-sm text-gray-600 italic bg-gray-50 p-4 rounded-lg border border-gray-100 leading-relaxed">
                <?= nl2br(e($sale['notes'])) ?>
            </p>
        </div>
    <?php endif; ?>

</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
