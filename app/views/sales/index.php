<?php
/**
 * Sales-history data provided by SaleController::listSales().
 *
 * @var array<int, array<string, mixed>> $sales
 * @var array{total_sales: numeric-string|int|float, cash_sales: numeric-string|int|float, momo_sales: numeric-string|int|float, outstanding: numeric-string|int|float} $todayStats
 * @var int $page
 * @var int $totalPages
 * @var string $search
 * @var string $status
 * @var string $dateFrom
 * @var string $dateTo
 * @var string $pageTitle
 */
include APP_PATH . '/views/layout/header.php';
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Sales History</h1>
        <p class="text-gray-500 mt-1">View and manage all sales transactions</p>
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
            <a href="<?= BASE_URL ?>/pos"
                class="block px-4 py-2 hover:bg-gray-100 text-gray-700">
                + New Sale
            </a>
            <a href="<?= BASE_URL ?>/export/sales?search=<?= urlencode($_GET['search'] ?? '') ?>&status=<?= urlencode($_GET['status'] ?? '') ?>&date_from=<?= urlencode($_GET['date_from'] ?? '') ?>&date_to=<?= urlencode($_GET['date_to'] ?? '') ?>"
                class="block px-4 py-2 hover:bg-gray-100 text-gray-700">
                📤 Export Sales
            </a>
        </div>
    </div>

    <!-- Desktop: Full Buttons -->
    <div class="hidden sm:flex gap-2">
        <a href="<?= BASE_URL ?>/pos"
            class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg shadow transition flex items-center gap-2">
            <span class="text-lg font-bold">+</span> New Sale
        </a>
        <a href="<?= BASE_URL ?>/export/sales?search=<?= urlencode($_GET['search'] ?? '') ?>&status=<?= urlencode($_GET['status'] ?? '') ?>&date_from=<?= urlencode($_GET['date_from'] ?? '') ?>&date_to=<?= urlencode($_GET['date_to'] ?? '') ?>"
            class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg transition">
            📤 Export Filtered
        </a>
    </div>
</div>

<?= flashMessage() ?>

<!-- Today's Stats -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white p-5 rounded-lg shadow">
        <div class="text-gray-400 text-xs mb-1">Today's Total Sales</div>
        <div class="text-2xl font-bold text-gray-800"><?= formatMoney($todayStats['total_sales']) ?></div>
    </div>
    <div class="bg-white p-5 rounded-lg shadow">
        <div class="text-gray-400 text-xs mb-1">Today's Cash Sales</div>
        <div class="text-2xl font-bold text-green-600"><?= formatMoney($todayStats['cash_sales']) ?></div>
    </div>
    <div class="bg-white p-5 rounded-lg shadow">
        <div class="text-gray-400 text-xs mb-1">Today's MoMo Sales</div>
        <div class="text-2xl font-bold text-blue-600"><?= formatMoney($todayStats['momo_sales']) ?></div>
    </div>
    <div class="bg-white p-5 rounded-lg shadow">
        <div class="text-gray-400 text-xs mb-1">Today's Outstanding</div>
        <div class="text-2xl font-bold text-red-500"><?= formatMoney($todayStats['outstanding']) ?></div>
    </div>
</div>

<!-- Filters - WITH DATE RANGE -->
<div class="bg-white p-4 rounded-lg shadow mb-6">
    <form method="GET" action="<?= BASE_URL ?>/sales" class="flex flex-wrap gap-3">
        <!-- Search -->
        <input type="text" name="search"
            placeholder="Search by sale # or customer..."
            value="<?= e($_GET['search'] ?? '') ?>"
            class="flex-1 min-w-48 px-4 py-2 border border-gray-300 rounded-lg
                      focus:ring-2 focus:ring-blue-500 focus:border-transparent">

        <!-- Status -->
        <select name="status"
            class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            <option value="">All Status</option>
            <option value="paid" <?= ($_GET['status'] ?? '') === 'paid'    ? 'selected' : '' ?>>Paid</option>
            <option value="partial" <?= ($_GET['status'] ?? '') === 'partial' ? 'selected' : '' ?>>Partial</option>
            <option value="unpaid" <?= ($_GET['status'] ?? '') === 'unpaid'  ? 'selected' : '' ?>>Unpaid</option>
        </select>

        <!-- Date Range - NEW! -->
        <div class="flex items-center gap-2">
            <input type="date" name="date_from"
                value="<?= e($_GET['date_from'] ?? '') ?>"
                placeholder="From"
                class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            <span class="text-gray-400">to</span>
            <input type="date" name="date_to"
                value="<?= e($_GET['date_to'] ?? '') ?>"
                placeholder="To"
                class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
        </div>

        <!-- Buttons -->
        <button type="submit"
            class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg transition">
            Search
        </button>

        <?php if (!empty($_GET['search']) || !empty($_GET['status']) || !empty($_GET['date_from']) || !empty($_GET['date_to'])): ?>
            <a href="<?= BASE_URL ?>/sales"
                class="bg-gray-500 hover:bg-gray-600 text-white px-5 py-2 rounded-lg transition">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Active Filter Info -->
<?php if (!empty($_GET['date_from']) || !empty($_GET['date_to']) || !empty($_GET['status']) || !empty($_GET['search'])): ?>
    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
        <div class="flex items-start">
            <div class="flex-1">
                <p class="text-sm font-medium text-blue-800">Active Filters:</p>
                <div class="mt-1 text-sm text-blue-700">
                    <?php if (!empty($_GET['search'])): ?>
                        <span class="inline-block bg-white px-2 py-1 rounded mr-2 mb-1">
                            Search: "<?= e($_GET['search']) ?>"
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($_GET['status'])): ?>
                        <span class="inline-block bg-white px-2 py-1 rounded mr-2 mb-1">
                            Status: <?= ucfirst($_GET['status']) ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($_GET['date_from'])): ?>
                        <span class="inline-block bg-white px-2 py-1 rounded mr-2 mb-1">
                            From: <?= e($_GET['date_from']) ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($_GET['date_to'])): ?>
                        <span class="inline-block bg-white px-2 py-1 rounded mr-2 mb-1">
                            To: <?= e($_GET['date_to']) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <a href="<?= BASE_URL ?>/export/sales?search=<?= urlencode($_GET['search'] ?? '') ?>&status=<?= urlencode($_GET['status'] ?? '') ?>&date_from=<?= urlencode($_GET['date_from'] ?? '') ?>&date_to=<?= urlencode($_GET['date_to'] ?? '') ?>"
                class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded text-sm transition">
                📥 Export Filtered
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- Sales Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left   text-xs font-medium text-gray-500 uppercase">Sale #</th>
                    <th class="px-4 py-3 text-left   text-xs font-medium text-gray-500 uppercase">Customer</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Items</th>
                    <th class="px-4 py-3 text-right  text-xs font-medium text-gray-500 uppercase">Total</th>
                    <th class="px-4 py-3 text-right  text-xs font-medium text-gray-500 uppercase">Paid</th>
                    <th class="px-4 py-3 text-right  text-xs font-medium text-gray-500 uppercase">Due</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Method</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($sales)): ?>
                    <tr>
                        <td colspan="10" class="px-6 py-16 text-center text-gray-400">
                            <div class="text-5xl mb-4">🧾</div>
                            <p class="text-lg font-medium">No sales found</p>
                            <?php if (empty($_GET['search']) && empty($_GET['status']) && empty($_GET['date_from'])): ?>
                                <a href="<?= BASE_URL ?>/pos"
                                    class="inline-block mt-4 bg-green-600 text-white px-5 py-2 rounded-lg hover:bg-green-700">
                                    Make a Sale
                                </a>
                            <?php else: ?>
                                <p class="text-sm text-gray-500 mt-2">Try adjusting your filters</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($sales as $s): ?>
                        <?php
                        $isVoided = strpos($s['notes'] ?? '', '[VOIDED]') !== false;
                        $statusText = $isVoided ? 'Voided' : ucfirst($s['payment_status']);
                        $sc = $isVoided ? 'bg-gray-100 text-gray-600 border border-gray-200' : ([
                            'paid'    => 'bg-green-100 text-green-700',
                            'partial' => 'bg-yellow-100 text-yellow-700',
                            'unpaid'  => 'bg-red-100 text-red-600',
                        ][$s['payment_status']] ?? 'bg-gray-100 text-gray-600');
                        ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 text-sm font-semibold text-blue-600">
                                <a href="<?= BASE_URL ?>/sales/view/<?= $s['id'] ?>" class="hover:underline">
                                    <?= e($s['sale_number']) ?>
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm text-gray-800">
                                    <?= e($s['customer_name']) ?>
                                    <?php if ($s['is_walkin']): ?>
                                        <span class="text-xs text-blue-400">(walk-in)</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center text-sm text-gray-600">
                                <?= $s['item_count'] ?>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-gray-800">
                                <?= formatMoney($s['total_amount']) ?>
                            </td>
                            <td class="px-4 py-3 text-right text-sm text-green-600">
                                <?= formatMoney($s['amount_paid']) ?>
                            </td>
                            <td class="px-4 py-3 text-right text-sm">
                                <?php if ($s['amount_due'] > 0): ?>
                                    <span class="text-red-500 font-semibold"><?= formatMoney($s['amount_due']) ?></span>
                                  <?php else: ?>
                                    <span class="text-gray-300">—</span>
                                  <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-center text-xs text-gray-500 capitalize">
                                <?= $s['payment_method'] ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= $sc ?>">
                                    <?= $statusText ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center text-xs text-gray-500">
                                <?= formatDate($s['sale_date'], 'd M Y') ?>
                                <div class="text-gray-400"><?= formatDate($s['sale_date'], 'H:i') ?></div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-center gap-1">
                                    <!-- View -->
                                    <a href="<?= BASE_URL ?>/sales/view/<?= $s['id'] ?>"
                                        class="p-1.5 rounded bg-blue-50 text-blue-600 hover:bg-blue-100"
                                        title="View Details">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>

                                    <!-- Receipt -->
                                    <a href="<?= BASE_URL ?>/sales/receipt/<?= $s['id'] ?>"
                                        target="_blank"
                                        class="p-1.5 rounded bg-gray-50 text-gray-600 hover:bg-gray-100"
                                        title="Print Receipt">
                                        🖨
                                    </a>

                                    <!-- Edit (Admin/Manager Only) -->
                                    <?php if (can('sales.edit')): ?>
                                        <a href="<?= BASE_URL ?>/sales/edit/<?= $s['id'] ?>"
                                            class="p-1.5 rounded bg-yellow-50 text-yellow-600 hover:bg-yellow-100"
                                            title="Edit Sale">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </a>
                                    <?php endif; ?>

                                    <!-- Pay (if not fully paid) -->
                                    <?php if ($s['payment_status'] !== 'paid'): ?>
                                        <a href="<?= BASE_URL ?>/sales/pay/<?= $s['id'] ?>"
                                            class="p-1.5 rounded bg-green-50 text-green-600 hover:bg-green-100"
                                            title="Record Payment">
                                            💳
                                        </a>
                                    <?php endif; ?>
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
        <div class="bg-gray-50 px-5 py-3 border-t flex items-center justify-between">
            <span class="text-sm text-gray-500">Page <?= $page ?> of <?= $totalPages ?> — <?= $totalCount ?> sales</span>
            <div class="flex gap-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($_GET['search'] ?? '') ?>&status=<?= urlencode($_GET['status'] ?? '') ?>&date_from=<?= urlencode($_GET['date_from'] ?? '') ?>&date_to=<?= urlencode($_GET['date_to'] ?? '') ?>"
                        class="px-3 py-1.5 bg-white border rounded text-sm hover:bg-gray-50">← Prev</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($_GET['search'] ?? '') ?>&status=<?= urlencode($_GET['status'] ?? '') ?>&date_from=<?= urlencode($_GET['date_from'] ?? '') ?>&date_to=<?= urlencode($_GET['date_to'] ?? '') ?>"
                        class="px-3 py-1.5 bg-white border rounded text-sm hover:bg-gray-50">Next →</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
