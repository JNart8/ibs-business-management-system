<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="container mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">🚚 Distributor Direct Deliveries</h1>
            <p class="text-gray-500 text-sm mt-1">Combined purchase-sale transactions with direct supplier-to-customer shipment</p>
        </div>
        <div class="flex gap-2">
            <a href="<?= BASE_URL ?>/distributor/create"
                class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 rounded-lg font-semibold shadow hover:shadow-md transition flex items-center gap-2">
                <span>➕ New Direct Delivery</span>
            </a>
        </div>
    </div>

    <!-- Sessions Flash Messages -->
    <?php if (isset($_SESSION['flash'])): ?>
        <?php $flash = $_SESSION['flash']; unset($_SESSION['flash']); ?>
        <div class="mb-6 p-4 rounded-lg <?= $flash['type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold text-gray-600 uppercase">Date</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-600 uppercase">Sale #</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-600 uppercase">Purchase #</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-600 uppercase">Customer</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-600 uppercase">Supplier</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-600 uppercase text-right">Sale Total</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-600 uppercase text-right">Purchase Cost</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-600 uppercase text-right">Markup Profit</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-600 uppercase text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($deliveries)): ?>
                        <tr>
                            <td colspan="9" class="px-6 py-8 text-center text-gray-500">
                                No direct deliveries recorded yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($deliveries as $del): 
                            $profit = floatval($del['sale_total']) - floatval($del['purchase_total']);
                        ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <?= date('d M Y, H:i', strtotime($del['sale_date'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-mono text-sm text-blue-600 font-semibold">
                                    <?= e($del['sale_number']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-mono text-sm text-purple-600 font-semibold">
                                    <?= e($del['purchase_number']) ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-800 font-medium">
                                    <?= e($del['customer_name']) ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-800 font-medium">
                                    <?= e($del['supplier_name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-green-600">
                                    <?= formatMoney($del['sale_total']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-red-500">
                                    <?= formatMoney($del['purchase_total']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-blue-700">
                                    <?= formatMoney($profit) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                    <a href="<?= BASE_URL ?>/distributor/view/<?= $del['sale_id'] ?>"
                                        class="bg-blue-50 hover:bg-blue-100 text-blue-600 px-3 py-1.5 rounded font-medium transition">
                                        👁️ View Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="px-6 py-4 bg-gray-50 border-t flex items-center justify-between">
                <span class="text-sm text-gray-600">
                    Showing Page <?= $page ?> of <?= $totalPages ?> (Total: <?= $total ?> deliveries)
                </span>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" class="px-3 py-1 border rounded bg-white hover:bg-gray-100 text-sm font-medium">Previous</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>" class="px-3 py-1 border rounded bg-white hover:bg-gray-100 text-sm font-medium">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
