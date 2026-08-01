<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="max-w-6xl mx-auto">

    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Review Import - <?= ucfirst($type) ?></h1>
        <p class="text-gray-500 text-sm mt-1">Check the data below before importing</p>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
            <div class="text-gray-500 text-sm">Total Rows</div>
            <div class="text-2xl font-bold text-gray-800"><?= $preview['total'] ?></div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
            <div class="text-gray-500 text-sm">Valid Rows</div>
            <div class="text-2xl font-bold text-green-600"><?= $preview['valid'] ?></div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-500">
            <div class="text-gray-500 text-sm">Invalid Rows</div>
            <div class="text-2xl font-bold text-red-600"><?= $preview['invalid'] ?></div>
        </div>
    </div>

    <!-- Errors (if any) -->
    <?php if (!empty($preview['errors'])): ?>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <h3 class="font-bold text-red-800 mb-2">⚠️ Errors Found</h3>
            <div class="text-sm text-red-700 space-y-1 max-h-48 overflow-y-auto">
                <?php foreach (array_slice($preview['errors'], 0, 10) as $error): ?>
                    <div>• <?= e($error) ?></div>
                <?php endforeach; ?>
                <?php if (count($preview['errors']) > 10): ?>
                    <div class="text-xs text-red-500 mt-2">
                        ... and <?= count($preview['errors']) - 10 ?> more errors
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Data Preview -->
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="px-4 py-3 border-b bg-gray-50">
            <h3 class="font-bold text-gray-800">Data Preview</h3>
        </div>
        <div class="overflow-x-auto" style="max-height: 500px;">
            <table class="w-full text-sm">
                <thead class="bg-gray-100 sticky top-0">
                    <tr>
                        <th class="px-4 py-2 text-left">#</th>
                        <th class="px-4 py-2 text-left">Status</th>
                        <?php
                        $firstRow = $preview['data'][0] ?? [];
                        $displayColumns = array_filter(array_keys($firstRow), fn($k) => !str_starts_with($k, '_'));
                        ?>
                        <?php foreach ($displayColumns as $col): ?>
                            <th class="px-4 py-2 text-left"><?= e(ucwords(str_replace('_', ' ', $col))) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach (array_slice($preview['data'], 0, 100) as $row): ?>
                        <tr class="<?= !empty($row['_error']) ? 'bg-red-50' : 'hover:bg-gray-50' ?>">
                            <td class="px-4 py-2 text-gray-500"><?= $row['_row_num'] ?></td>
                            <td class="px-4 py-2">
                                <?php if (!empty($row['_error'])): ?>
                                    <span class="text-xs bg-red-100 text-red-700 px-2 py-1 rounded" title="<?= e($row['_error']) ?>">
                                        ❌ Error
                                    </span>
                                <?php else: ?>
                                    <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded">
                                        ✓ Valid
                                    </span>
                                <?php endif; ?>
                            </td>
                            <?php foreach ($displayColumns as $col): ?>
                                <td class="px-4 py-2"><?= e($row[$col] ?? '') ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($preview['data']) > 100): ?>
                        <tr>
                            <td colspan="<?= count($displayColumns) + 2 ?>" class="px-4 py-3 text-center text-gray-500 text-sm">
                                ... and <?= count($preview['data']) - 100 ?> more rows (showing first 100)
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Actions -->
    <div class="flex justify-between items-center">
        <a href="<?= BASE_URL ?>/import/<?= $type ?>"
            class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-3 rounded-lg transition">
            ← Cancel
        </a>

        <?php if ($preview['valid'] > 0): ?>
            <form action="<?= BASE_URL ?>/import/<?= $type ?>" method="POST">
                <?= csrfField() ?>
                <button type="submit"
                    class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-semibold transition">
                    ✓ Import <?= $preview['valid'] ?> Valid Row<?= $preview['valid'] !== 1 ? 's' : '' ?>
                </button>
            </form>
        <?php else: ?>
            <div class="text-red-600 font-medium">
                Cannot import - all rows have errors
            </div>
        <?php endif; ?>
    </div>

</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>