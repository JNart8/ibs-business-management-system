<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="container mx-auto px-4 py-6">

    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">📦 Purchases</h1>
            <p class="text-gray-500 text-sm mt-1">Track and manage all purchases from suppliers</p>
        </div>
        <div class="flex gap-2">
            <a href="<?= BASE_URL ?>/import/purchases"
                class="bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-lg font-semibold
                       transition shadow hover:shadow-md flex items-center gap-2">
                <span>📥 Import CSV</span>
            </a>
            <a href="<?= BASE_URL ?>/purchases/create"
                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold
                       transition shadow hover:shadow-md flex items-center gap-2">
                <span class="text-xl">+</span>
                <span>New Purchase</span>
            </a>
        </div>
    </div>

    <?= flashMessage() ?>

    <!-- Today's Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <div class="text-gray-400 text-xs uppercase tracking-wide mb-1">Today's Purchases</div>
                    <div class="text-2xl font-bold text-gray-800"><?= number_format($todayStats['total_purchases']) ?></div>
                    <div class="text-xs text-gray-500 mt-0.5">Transactions</div>
                </div>
                <div class="bg-blue-100 rounded-full p-3">
                    <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <div class="text-gray-400 text-xs uppercase tracking-wide mb-1">Total Spent</div>
                    <div class="text-2xl font-bold text-blue-600"><?= formatMoney($todayStats['total_spent']) ?></div>
                    <div class="text-xs text-gray-500 mt-0.5">Today</div>
                </div>
                <div class="bg-blue-100 rounded-full p-3">
                    <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <div class="text-gray-400 text-xs uppercase tracking-wide mb-1">Paid Today</div>
                    <div class="text-2xl font-bold text-green-600"><?= formatMoney($todayStats['paid_today']) ?></div>
                    <div class="text-xs text-gray-500 mt-0.5">To Suppliers</div>
                </div>
                <div class="bg-green-100 rounded-full p-3">
                    <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <div class="text-gray-400 text-xs uppercase tracking-wide mb-1">Outstanding</div>
                    <div class="text-2xl font-bold text-red-600"><?= formatMoney($todayStats['outstanding']) ?></div>
                    <div class="text-xs text-gray-500 mt-0.5">We Owe</div>
                </div>
                <div class="bg-red-100 rounded-full p-3">
                    <svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <form method="GET" action="<?= BASE_URL ?>/purchases" class="grid grid-cols-1 md:grid-cols-6 gap-3">

            <!-- Search -->
            <div class="md:col-span-2">
                <input type="text" name="search" value="<?= e($_GET['search'] ?? '') ?>"
                    placeholder="Search purchase, supplier, invoice..."
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Status Filter -->
            <div>
                <select name="status"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="">All Status</option>
                    <option value="paid" <?= ($status ?? '') === 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="partial" <?= ($status ?? '') === 'partial' ? 'selected' : '' ?>>Partial</option>
                    <option value="unpaid" <?= ($status ?? '') === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                </select>
            </div>

            <!-- Date Filter -->
            <div>
                <input type="date" name="date" value="<?= e($_GET['date'] ?? '') ?>"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Buttons -->
            <div class="flex gap-2">
                <button type="submit"
                    class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    Filter
                </button>
                <a href="<?= BASE_URL ?>/purchases"
                    class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Purchases Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <?php if (empty($purchases)): ?>
            <div class="p-12 text-center text-gray-400">
                <div class="text-6xl mb-4">📦</div>
                <p class="text-lg font-medium">No purchases found</p>
                <p class="text-sm mt-2">Start by creating your first purchase</p>
                <a href="<?= BASE_URL ?>/purchases/create"
                    class="inline-block mt-4 bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition">
                    Create Purchase
                </a>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Purchase #</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Supplier</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Amount</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Paid</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Due</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Status</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php foreach ($purchases as $purchase): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-3">
                                    <a href="<?= BASE_URL ?>/purchases/view/<?= $purchase['id'] ?>"
                                        class="font-mono text-sm font-semibold text-blue-600 hover:text-blue-800">
                                        <?= e($purchase['purchase_number']) ?>
                                    </a>
                                    <?php if (!empty($purchase['invoice_number'])): ?>
                                        <div class="text-xs text-gray-400 mt-0.5">
                                            INV: <?= e($purchase['invoice_number']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <?= date('d M Y', strtotime($purchase['purchase_date'])) ?>
                                    <div class="text-xs text-gray-400">
                                        <?= date('H:i', strtotime($purchase['purchase_date'])) ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-gray-800">
                                        <?= e($purchase['supplier_name']) ?>
                                    </div>
                                    <?php if (!empty($purchase['contact_name'])): ?>
                                        <div class="text-xs text-gray-400"><?= e($purchase['contact_name']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-800">
                                    <?= formatMoney($purchase['total_amount']) ?>
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-green-600">
                                    <?= formatMoney($purchase['amount_paid']) ?>
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-red-600">
                                    <?= formatMoney($purchase['amount_due']) ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <?php
                                    $statusColors = [
                                        'paid' => 'bg-green-100 text-green-700',
                                        'partial' => 'bg-yellow-100 text-yellow-700',
                                        'unpaid' => 'bg-red-100 text-red-700'
                                    ];
                                    $color = $statusColors[$purchase['payment_status']] ?? 'bg-gray-100 text-gray-700';
                                    ?>
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold <?= $color ?>">
                                        <?= ucfirst($purchase['payment_status']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <a href="<?= BASE_URL ?>/purchases/view/<?= $purchase['id'] ?>"
                                            class="text-blue-600 hover:text-blue-800 transition"
                                            title="View Details">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                        </a>
                                        <?php if ($purchase['payment_status'] !== 'paid'): ?>
                                            <a href="<?= BASE_URL ?>/purchases/pay/<?= $purchase['id'] ?>"
                                                class="text-green-600 hover:text-green-800 transition"
                                                title="Record Payment">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="bg-gray-50 px-4 py-3 border-t flex items-center justify-between">
                    <div class="text-sm text-gray-600">
                        Showing page <?= $page ?> of <?= $totalPages ?> (<?= number_format($totalCount) ?> total)
                    </div>
                    <div class="flex gap-1">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($status) ? '&status=' . $status : '' ?><?= !empty($date) ? '&date=' . $date : '' ?>"
                                class="px-3 py-1 bg-white border rounded hover:bg-gray-50 transition text-sm">
                                Previous
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($status) ? '&status=' . $status : '' ?><?= !empty($date) ? '&date=' . $date : '' ?>"
                                class="px-3 py-1 border rounded transition text-sm <?= $i === $page ? 'bg-blue-600 text-white border-blue-600' : 'bg-white hover:bg-gray-50' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($status) ? '&status=' . $status : '' ?><?= !empty($date) ? '&date=' . $date : '' ?>"
                                class="px-3 py-1 bg-white border rounded hover:bg-gray-50 transition text-sm">
                                Next
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>