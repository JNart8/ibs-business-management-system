<?php include APP_PATH . '/views/layout/header.php'; ?>

<!-- Page Header -->
<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Product Categories</h1>
        <p class="text-gray-500 mt-1">Organise your products into categories</p>
    </div>

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
            <a href="<?= BASE_URL ?>/categories/create"
                class="block px-4 py-2 hover:bg-gray-100 text-gray-700">
                + Add Category
            </a>
            <a href="<?= BASE_URL ?>/import/categories"
                class="block px-4 py-2 hover:bg-gray-100 text-gray-700">
                📥 Import CSV
            </a>
            <a href="<?= BASE_URL ?>/export/categories"
                class="block px-4 py-2 hover:bg-gray-100 text-gray-700">
                📤 Export CSV
            </a>
        </div>
    </div>

    <!-- Desktop: Full Buttons -->
    <div class="hidden sm:flex gap-2">
        <a href="<?= BASE_URL ?>/categories/create"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition">
            + Add Category
        </a>
        <a href="<?= BASE_URL ?>/import/categories"
            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition">
            📥 Import CSV
        </a>
        <a href="<?= BASE_URL ?>/export/categories"
            class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg transition">
            📤 Export CSV
        </a>
    </div>
</div>

<!-- Flash Message -->
<?= flashMessage() ?>

<!-- Stats Row -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white p-5 rounded-lg shadow flex items-center gap-4">
        <div class="bg-blue-100 p-3 rounded-full text-blue-600 text-2xl">🗂️</div>
        <div>
            <div class="text-gray-500 text-sm">Total Categories</div>
            <div class="text-2xl font-bold text-gray-800"><?= formatNumber($stats['total_categories']) ?></div>
        </div>
    </div>
    <div class="bg-white p-5 rounded-lg shadow flex items-center gap-4">
        <div class="bg-green-100 p-3 rounded-full text-green-600 text-2xl">✅</div>
        <div>
            <div class="text-gray-500 text-sm">Active</div>
            <div class="text-2xl font-bold text-green-600"><?= formatNumber($stats['active_categories']) ?></div>
        </div>
    </div>
    <div class="bg-white p-5 rounded-lg shadow flex items-center gap-4">
        <div class="bg-red-100 p-3 rounded-full text-red-600 text-2xl">⛔</div>
        <div>
            <div class="text-gray-500 text-sm">Inactive</div>
            <div class="text-2xl font-bold text-red-500"><?= formatNumber($stats['inactive_categories']) ?></div>
        </div>
    </div>
</div>

<!-- Search -->
<div class="bg-white p-4 rounded-lg shadow mb-6">
    <form method="GET" action="<?= BASE_URL ?>/categories" class="flex gap-3">
        <input
            type="text"
            name="search"
            placeholder="Search categories..."
            value="<?= e($_GET['search'] ?? '') ?>"
            class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        <button type="submit"
            class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg transition">
            Search
        </button>
        <?php if (!empty($_GET['search'])): ?>
            <a href="<?= BASE_URL ?>/categories"
                class="bg-gray-500 hover:bg-gray-600 text-white px-5 py-2 rounded-lg transition">
                Clear
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- Categories Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Products</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Stock Value</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Low Stock</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="8" class="px-6 py-16 text-center text-gray-400">
                            <div class="text-5xl mb-4">🗂️</div>
                            <p class="text-lg font-medium">No categories found</p>
                            <p class="text-sm mt-1">Start by adding your first category</p>
                            <a href="<?= BASE_URL ?>/categories/create"
                                class="inline-block mt-4 bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 transition">
                                + Add Category
                            </a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($categories as $i => $cat): ?>
                        <tr class="hover:bg-gray-50 transition" id="row-<?= $cat['id'] ?>">
                            <td class="px-6 py-4 text-sm text-gray-500"><?= $i + 1 ?></td>
                            <td class="px-6 py-4">
                                <div class="font-semibold text-gray-800"><?= e($cat['name']) ?></div>
                                <div class="text-xs text-gray-400 mt-0.5">
                                    Added <?= formatDate($cat['created_at'], 'd M Y') ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                                <?= e($cat['description'] ?? '—') ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <a href="<?= BASE_URL ?>/products?category=<?= $cat['id'] ?>"
                                    class="inline-block bg-blue-100 text-blue-700 text-sm font-semibold px-3 py-1 rounded-full hover:bg-blue-200 transition">
                                    <?= $cat['total_products'] ?> products
                                </a>
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-semibold text-gray-700">
                                <?= formatMoney($cat['stock_value']) ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($cat['low_stock_count'] > 0): ?>
                                    <span class="bg-yellow-100 text-yellow-700 text-xs font-semibold px-2 py-1 rounded-full">
                                        ⚠️ <?= $cat['low_stock_count'] ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-400 text-xs">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <!-- Toggle switch (AJAX) -->
                                <button
                                    onclick="toggleStatus(<?= $cat['id'] ?>, this)"
                                    data-active="<?= $cat['is_active'] ?>"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none
                                           <?= $cat['is_active'] ? 'bg-green-500' : 'bg-gray-300' ?>"
                                    title="<?= $cat['is_active'] ? 'Click to deactivate' : 'Click to activate' ?>">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform
                                                 <?= $cat['is_active'] ? 'translate-x-6' : 'translate-x-1' ?>">
                                    </span>
                                </button>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex justify-center gap-3">
                                    <a href="<?= BASE_URL ?>/categories/edit/<?= $cat['id'] ?>"
                                        class="text-green-600 hover:text-green-800 transition" title="Edit">
                                        <!-- Pencil icon -->
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5
                                                     m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    <a href="<?= BASE_URL ?>/categories/delete/<?= $cat['id'] ?>"
                                        class="text-red-500 hover:text-red-700 transition" title="Delete"
                                        onclick="return confirmDelete('Delete category \'<?= e($cat['name']) ?>\'?\n<?= $cat['total_products'] > 0 ? $cat['total_products'] . ' product(s) will be moved to uncategorized.' : 'This category is empty.' ?>')">
                                        <!-- Trash icon -->
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858
                                                     L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
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
</div>

<script>
    /**
     * Toggle category active/inactive via AJAX (no page reload = fast!)
     */
    function toggleStatus(id, btn) {
        fetch('<?= BASE_URL ?>/categories/toggle/' + id, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const isActive = data.is_active;
                    // Update toggle button
                    btn.dataset.active = isActive;
                    btn.className = btn.className.replace(
                        isActive ? 'bg-gray-300' : 'bg-green-500',
                        isActive ? 'bg-green-500' : 'bg-gray-300'
                    );
                    // Move knob
                    const knob = btn.querySelector('span');
                    knob.className = knob.className.replace(
                        isActive ? 'translate-x-1' : 'translate-x-6',
                        isActive ? 'translate-x-6' : 'translate-x-1'
                    );
                    btn.title = isActive ? 'Click to deactivate' : 'Click to activate';
                    showToast(data.message, isActive ? 'green' : 'yellow');
                }
            })
            .catch(() => showToast('Something went wrong', 'red'));
    }

    /**
     * Show a small toast notification
     */
    function showToast(message, color = 'green') {
        const colors = {
            green: 'bg-green-500',
            yellow: 'bg-yellow-500',
            red: 'bg-red-500'
        };
        const toast = document.createElement('div');
        toast.className = `fixed bottom-6 right-6 ${colors[color] || colors.green} text-white px-5 py-3 rounded-lg shadow-lg z-50 transition-opacity`;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 400);
        }, 2500);
    }
</script>

<?php include APP_PATH . '/views/layout/footer.php'; ?>