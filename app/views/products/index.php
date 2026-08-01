<?php include APP_PATH . '/views/layout/header.php'; ?>

<!-- Page Header -->
<div class="flex justify-between items-center mb-6">
    <h1 class="text-3xl font-bold text-gray-800"><?= e($pageTitle) ?></h1>

    <!-- Mobile: Dropdown -->
    <div class="sm:hidden" x-data="{ open: false }">
        <button @click="open = !open"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
            Actions
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
        <div x-show="open"
            @click.outside="open = false"
            class="absolute right-4 mt-2 w-48 bg-white rounded-lg shadow-lg border py-1 z-20">
            <a href="<?= BASE_URL ?>/products/create"
                class="block px-4 py-2 hover:bg-gray-100 text-gray-700">
                + Add Product
            </a>
            <a href="<?= BASE_URL ?>/import/products"
                class="block px-4 py-2 hover:bg-gray-100 text-gray-700">
                📥 Import CSV
            </a>
            <a href="<?= BASE_URL ?>/export/products"
                class="block px-4 py-2 hover:bg-gray-100 text-gray-700">
                📤 Export CSV
            </a>
        </div>
    </div>

    <!-- Desktop: Full Buttons -->
    <div class="hidden sm:flex gap-2">
        <a href="<?= BASE_URL ?>/products/create"
            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg shadow transition">
            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Add Product
        </a>
        <a href="<?= BASE_URL ?>/import/products"
            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition">
            📥 Import CSV
        </a>
        <a href="<?= BASE_URL ?>/export/products"
            class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg transition">
            📤 Export CSV
        </a>
    </div>
</div>

<!-- Flash Messages -->
<?= flashMessage() ?>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white p-6 rounded-lg shadow">
        <div class="text-gray-500 text-sm">Total Products</div>
        <div class="text-2xl font-bold text-gray-800"><?= number_format($stats['total_products']) ?></div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow">
        <div class="text-gray-500 text-sm">Total Stock Value</div>
        <div class="text-2xl font-bold text-green-600"><?= formatMoney($stats['total_value']) ?></div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow">
        <div class="text-gray-500 text-sm">Low Stock Items</div>
        <div class="text-2xl font-bold text-yellow-600"><?= $stats['low_stock_count'] ?></div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow">
        <div class="text-gray-500 text-sm">Out of Stock</div>
        <div class="text-2xl font-bold text-red-600"><?= $stats['out_of_stock_count'] ?></div>
    </div>
</div>

<!-- Search and Filter -->
<div class="bg-white p-4 rounded-lg shadow mb-6">
    <form method="GET" action="<?= BASE_URL ?>/products" class="flex gap-4">
        <div class="flex-1">
            <input
                type="text"
                name="search"
                placeholder="Search by name, SKU, or barcode..."
                value="<?= e($_GET['search'] ?? '') ?>"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition">
            Search
        </button>
        <?php if (!empty($_GET['search'])): ?>
            <a href="<?= BASE_URL ?>/products" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition">
                Clear
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- Products Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Avg Cost (WMA)</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Selling Price</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Stock</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                            </svg>
                            <p class="text-lg">No products found</p>
                            <a href="<?= BASE_URL ?>/products/create" class="text-blue-600 hover:underline mt-2 inline-block">Add your first product</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                <?= e($product['sku']) ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?= e($product['name']) ?></div>
                                <?php if (!empty($product['barcode'])): ?>
                                    <div class="text-xs text-gray-500">Barcode: <?= e($product['barcode']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?= e($product['category_name'] ?? 'N/A') ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right text-gray-900">
                                <?= formatMoney($product['average_cost']) ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-semibold text-gray-900">
                                <?= formatMoney($product['selling_price']) ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-sm font-semibold">
                                    <?= $product['current_stock'] ?> <?= e($product['unit']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php
                                $statusColors = [
                                    'in-stock' => 'bg-green-100 text-green-800',
                                    'low-stock' => 'bg-yellow-100 text-yellow-800',
                                    'out-of-stock' => 'bg-red-100 text-red-800'
                                ];
                                $statusLabels = [
                                    'in-stock' => 'In Stock',
                                    'low-stock' => 'Low Stock',
                                    'out-of-stock' => 'Out of Stock'
                                ];
                                $color = $statusColors[$product['stock_status']] ?? 'bg-gray-100 text-gray-800';
                                $label = $statusLabels[$product['stock_status']] ?? 'Unknown';
                                ?>
                                <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $color ?>">
                                    <?= $label ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex justify-center gap-2">
                                    <a href="<?= BASE_URL ?>/products/view/<?= $product['id'] ?>"
                                        class="text-blue-600 hover:text-blue-800"
                                        title="View">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                    </a>
                                    <a href="<?= BASE_URL ?>/products/edit/<?= $product['id'] ?>"
                                        class="text-green-600 hover:text-green-800"
                                        title="Edit">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </a>
                                    <a href="<?= BASE_URL ?>/products/delete/<?= $product['id'] ?>"
                                        class="text-red-600 hover:text-red-800"
                                        title="Delete"
                                        onclick="return confirmDelete('Delete this product?')">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="bg-gray-50 px-6 py-4 border-t">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-600">
                    Showing page <?= $page ?> of <?= $totalPages ?>
                </div>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                            class="px-4 py-2 bg-white border rounded hover:bg-gray-50">
                            Previous
                        </a>
                    <?php endif; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                            class="px-4 py-2 bg-white border rounded hover:bg-gray-50">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>