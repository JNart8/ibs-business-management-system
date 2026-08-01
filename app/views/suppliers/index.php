<?php include APP_PATH . '/views/layout/header.php'; ?>


<div class="flex flex-wrap justify-between items-center mb-6 gap-3">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Suppliers</h1>
        <p class="text-gray-500 text-sm mt-1">Manage your product suppliers and delivery history</p>
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
            <a href="<?= BASE_URL ?>/suppliers/create"
                class="block px-4 py-2 hover:bg-gray-100 text-gray-700">
                + Add Supplier
            </a>
            <a href="<?= BASE_URL ?>/import/suppliers"
                class="block px-4 py-2 hover:bg-gray-100 text-gray-700">
                📥 Import CSV
            </a>
            <a href="<?= BASE_URL ?>/export/suppliers"
                class="block px-4 py-2 hover:bg-gray-100 text-gray-700">
                📤 Export CSV
            </a>
        </div>
    </div>

    <!-- Desktop: Full Buttons -->
    <div class="hidden sm:flex gap-2">
        <a href="<?= BASE_URL ?>/suppliers/create"
            class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg shadow transition flex items-center gap-2">
            <span class="font-bold text-lg">+</span> Add Supplier
        </a>
        <a href="<?= BASE_URL ?>/import/suppliers"
            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition">
            📥 Import CSV
        </a>
        <a href="<?= BASE_URL ?>/export/suppliers"
            class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg transition">
            📤 Export CSV
        </a>
    </div>
</div>

<?= flashMessage() ?>

<!-- Stats -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white p-5 rounded-lg shadow flex items-center gap-4">
        <div class="bg-blue-100 text-blue-600 text-2xl p-3 rounded-full">🏭</div>
        <div>
            <div class="text-gray-400 text-xs">Total Suppliers</div>
            <div class="text-2xl font-bold text-gray-800"><?= formatNumber($stats['total']) ?></div>
        </div>
    </div>
    <div class="bg-white p-5 rounded-lg shadow flex items-center gap-4">
        <div class="bg-green-100 text-green-600 text-2xl p-3 rounded-full">✅</div>
        <div>
            <div class="text-gray-400 text-xs">Active</div>
            <div class="text-2xl font-bold text-green-600"><?= formatNumber($stats['active']) ?></div>
        </div>
    </div>
    <div class="bg-white p-5 rounded-lg shadow flex items-center gap-4">
        <div class="bg-red-100 text-red-500 text-2xl p-3 rounded-full">⛔</div>
        <div>
            <div class="text-gray-400 text-xs">Inactive</div>
            <div class="text-2xl font-bold text-red-500"><?= formatNumber($stats['inactive']) ?></div>
        </div>
    </div>
</div>

<!-- Search & Filter -->
<div class="bg-white p-4 rounded-lg shadow mb-6">
    <form method="GET" action="<?= BASE_URL ?>/suppliers" class="flex flex-wrap gap-3">
        <input type="text" name="search"
            placeholder="Search by company, contact or phone..."
            value="<?= e($_GET['search'] ?? '') ?>"
            class="flex-1 min-w-48 px-4 py-2 border border-gray-300 rounded-lg
                      focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        <select name="filter"
            class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
            onchange="this.form.submit()">
            <option value="all" <?= ($_GET['filter'] ?? 'all') === 'all'      ? 'selected' : '' ?>>All</option>
            <option value="active" <?= ($_GET['filter'] ?? '') === 'active'      ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= ($_GET['filter'] ?? '') === 'inactive'    ? 'selected' : '' ?>>Inactive</option>
        </select>
        <button type="submit"
            class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg transition">
            Search
        </button>
        <?php if (!empty($_GET['search']) || !empty($_GET['filter'])): ?>
            <a href="<?= BASE_URL ?>/suppliers"
                class="bg-gray-500 hover:bg-gray-600 text-white px-5 py-2 rounded-lg transition">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Suppliers Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-5 py-3 text-left   text-xs font-medium text-gray-500 uppercase">Supplier</th>
                    <th class="px-5 py-3 text-left   text-xs font-medium text-gray-500 uppercase">Contact</th>
                    <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase">Products</th>
                    <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase">Deliveries</th>
                    <th class="px-5 py-3 text-right  text-xs font-medium text-gray-500 uppercase">Total Supplied</th>
                    <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase">Last Delivery</th>
                    <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($suppliers)): ?>
                    <tr>
                        <td colspan="8" class="px-6 py-16 text-center text-gray-400">
                            <div class="text-5xl mb-4">🏭</div>
                            <p class="text-lg font-medium">No suppliers found</p>
                            <a href="<?= BASE_URL ?>/suppliers/create"
                                class="inline-block mt-4 bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700">
                                + Add Supplier
                            </a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($suppliers as $s): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-5 py-4">
                                <div class="font-semibold text-gray-800"><?= e($s['company_name']) ?></div>
                                <div class="text-xs text-gray-400"><?= e($s['supplier_code']) ?></div>
                                <?php if (!empty($s['city'])): ?>
                                    <div class="text-xs text-gray-400">📍 <?= e($s['city']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4">
                                <?php if (!empty($s['contact_name'])): ?>
                                    <div class="text-sm text-gray-700"><?= e($s['contact_name']) ?></div>
                                <?php endif; ?>
                                <div class="text-sm text-gray-600">📞 <?= e($s['phone']) ?></div>
                                <?php if (!empty($s['email'])): ?>
                                    <div class="text-xs text-gray-400">✉️ <?= e($s['email']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 text-center">
                                <a href="<?= BASE_URL ?>/products?supplier=<?= $s['id'] ?>"
                                    class="inline-block bg-blue-100 text-blue-700 text-sm font-semibold
                                          px-3 py-1 rounded-full hover:bg-blue-200 transition">
                                    <?= safeInt($s['total_products']) ?>
                                </a>
                            </td>
                            <td class="px-5 py-4 text-center text-sm text-gray-600">
                                <?= safeInt($s['total_deliveries']) ?>
                            </td>
                            <td class="px-5 py-4 text-right text-sm font-semibold text-gray-700">
                                <?= formatMoney($s['total_supplied_value']) ?>
                            </td>
                            <td class="px-5 py-4 text-center text-xs text-gray-500">
                                <?= $s['last_delivery']
                                    ? formatDate($s['last_delivery'], 'd M Y')
                                    : '<span class="text-gray-300">No deliveries</span>' ?>
                            </td>
                            <td class="px-5 py-4 text-center">
                                <!-- AJAX Toggle -->
                                <button onclick="toggleStatus(<?= $s['id'] ?>, this)"
                                    data-active="<?= $s['is_active'] ?>"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors
                                               <?= $s['is_active'] ? 'bg-green-500' : 'bg-gray-300' ?>">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform
                                                 <?= $s['is_active'] ? 'translate-x-6' : 'translate-x-1' ?>"></span>
                                </button>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex justify-center gap-2">
                                    <a href="<?= BASE_URL ?>/suppliers/view/<?= $s['id'] ?>"
                                        class="p-1.5 rounded bg-blue-50 text-blue-600 hover:bg-blue-100 transition" title="View">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>
                                    <a href="<?= BASE_URL ?>/suppliers/deposit/<?= $s['id'] ?>"
                                        class="p-1.5 rounded bg-green-50 text-green-600 hover:bg-green-100 transition" title="Record Payment">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                    </a>
                                    <a href="<?= BASE_URL ?>/suppliers/edit/<?= $s['id'] ?>"
                                        class="p-1.5 rounded bg-yellow-50 text-yellow-600 hover:bg-yellow-100 transition" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    <a href="<?= BASE_URL ?>/suppliers/delete/<?= $s['id'] ?>"
                                        onclick="return confirmDelete('Delete supplier <?= e($s['company_name']) ?>?')"
                                        class="p-1.5 rounded bg-red-50 text-red-500 hover:bg-red-100 transition" title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
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

    <?php if ($totalPages > 1): ?>
        <div class="bg-gray-50 px-5 py-3 border-t flex items-center justify-between">
            <span class="text-sm text-gray-500">Page <?= $page ?> of <?= $totalPages ?> — <?= $totalCount ?> suppliers</span>
            <div class="flex gap-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($_GET['search'] ?? '') ?>"
                        class="px-3 py-1.5 bg-white border rounded text-sm hover:bg-gray-50">← Prev</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($_GET['search'] ?? '') ?>"
                        class="px-3 py-1.5 bg-white border rounded text-sm hover:bg-gray-50">Next →</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    function toggleStatus(id, btn) {
        fetch('<?= BASE_URL ?>/suppliers/toggle/' + id, {
                method: 'POST'
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    const on = d.is_active;
                    btn.className = btn.className
                        .replace(on ? 'bg-gray-300' : 'bg-green-500', on ? 'bg-green-500' : 'bg-gray-300');
                    btn.querySelector('span').className = btn.querySelector('span').className
                        .replace(on ? 'translate-x-1' : 'translate-x-6', on ? 'translate-x-6' : 'translate-x-1');
                    showToast(d.message, on ? 'green' : 'yellow');
                }
            });
    }

    function showToast(msg, color = 'green') {
        const c = {
            green: 'bg-green-500',
            yellow: 'bg-yellow-500',
            red: 'bg-red-500'
        };
        const t = document.createElement('div');
        t.className = `fixed bottom-6 right-6 ${c[color]} text-white px-5 py-3 rounded-lg shadow-lg z-50`;
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(() => {
            t.style.opacity = '0';
            t.style.transition = 'opacity 0.4s';
            setTimeout(() => t.remove(), 400);
        }, 2500);
    }
</script>

<?php include APP_PATH . '/views/layout/footer.php'; ?>