<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">📋 Audit Log</h1>
        <p class="text-gray-500 text-sm mt-1">
            Who changed what — voids, permission changes, user/branch/account edits, settings.
            <?php if (hasMultiBranch() && !isCompanyWide()): ?>
                Showing <?= e(activeBranchName()) ?> only.
            <?php endif; ?>
        </p>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow-sm p-4 mb-4">
    <form method="GET" action="<?= BASE_URL ?>/audit" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">User</label>
            <select name="user_id" class="text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">All Users</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= ($_GET['user_id'] ?? '') == $u['id'] ? 'selected' : '' ?>>
                        <?= e($u['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Action</label>
            <select name="action" class="text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">All Actions</option>
                <?php foreach ($distinctActions as $a): ?>
                    <option value="<?= e($a['action']) ?>" <?= ($_GET['action'] ?? '') === $a['action'] ? 'selected' : '' ?>>
                        <?= e($a['action']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Entity</label>
            <select name="entity_type" class="text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">All Types</option>
                <?php foreach ($distinctEntities as $e): ?>
                    <option value="<?= e($e['entity_type']) ?>" <?= ($_GET['entity_type'] ?? '') === $e['entity_type'] ? 'selected' : '' ?>>
                        <?= e(ucfirst($e['entity_type'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">From</label>
            <input type="date" name="date_from" value="<?= e($_GET['date_from'] ?? '') ?>"
                class="text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">To</label>
            <input type="date" name="date_to" value="<?= e($_GET['date_to'] ?? '') ?>"
                class="text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-600 pb-2">
            <input type="checkbox" name="include_archived" value="1" <?= isset($_GET['include_archived']) ? 'checked' : '' ?>
                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            Include archived (1+ year old)
        </label>

        <button type="submit"
            class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
            Filter
        </button>
        <?php if (!empty($_GET['user_id']) || !empty($_GET['action']) || !empty($_GET['entity_type']) || !empty($_GET['date_from']) || !empty($_GET['date_to']) || !empty($_GET['include_archived'])): ?>
            <a href="<?= BASE_URL ?>/audit" class="text-sm text-gray-500 hover:text-gray-700 px-2">Clear</a>
        <?php endif; ?>

        <a href="<?= e($exportUrl) ?>"
            class="ml-auto bg-green-600 hover:bg-green-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
            ⬇ Export CSV
        </a>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="px-4 py-3 text-left   text-xs font-medium text-gray-500 uppercase">When</th>
                <th class="px-4 py-3 text-left   text-xs font-medium text-gray-500 uppercase">User</th>
                <th class="px-4 py-3 text-left   text-xs font-medium text-gray-500 uppercase">Action</th>
                <th class="px-4 py-3 text-left   text-xs font-medium text-gray-500 uppercase">Entity</th>
                <?php if (hasMultiBranch()): ?>
                <th class="px-4 py-3 text-left   text-xs font-medium text-gray-500 uppercase">Branch</th>
                <?php endif; ?>
                <th class="px-4 py-3 text-left   text-xs font-medium text-gray-500 uppercase">Details</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php if (empty($entries)): ?>
                <tr>
                    <td colspan="6" class="px-6 py-16 text-center text-gray-400">
                        <div class="text-5xl mb-3">📋</div>
                        <p>No audit entries found</p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($entries as $entry): ?>
                    <tr class="hover:bg-gray-50 transition align-top">
                        <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                            <?= formatDate($entry['created_at'] ?? '') ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-800">
                            <?= e($entry['username'] ?? 'System') ?>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700">
                                <?= e($entry['action']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <?php if ($entry['entity_type']): ?>
                                <?= e(ucfirst($entry['entity_type'])) ?><?= $entry['entity_id'] ? ' #' . $entry['entity_id'] : '' ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <?php if (hasMultiBranch()): ?>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <?= $entry['branch_id'] ? e(branchName($entry['branch_id'])) : '<span class="text-gray-400">Company-wide</span>' ?>
                        </td>
                        <?php endif; ?>
                        <td class="px-4 py-3 text-xs text-gray-500 max-w-md">
                            <?php
                                $details = $entry['details'] ? json_decode($entry['details'], true) : null;
                            ?>
                            <?php if ($details): ?>
                                <?php foreach ($details as $key => $value): ?>
                                    <div><span class="font-medium"><?= e($key) ?>:</span> <?= e(is_scalar($value) ? $value : json_encode($value)) ?></div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="bg-gray-50 px-5 py-3 border-t flex items-center justify-between">
            <span class="text-sm text-gray-500">Page <?= $page ?> of <?= $totalPages ?> — <?= $totalCount ?> entries</span>
            <div class="flex gap-2">
                <?php
                    $qs = array_filter([
                        'user_id'     => $_GET['user_id']     ?? '',
                        'action'      => $_GET['action']      ?? '',
                        'entity_type' => $_GET['entity_type'] ?? '',
                        'date_from'   => $_GET['date_from']   ?? '',
                        'date_to'     => $_GET['date_to']     ?? '',
                    ]);
                ?>
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&<?= http_build_query($qs) ?>"
                        class="px-3 py-1.5 bg-white border rounded text-sm hover:bg-gray-50">← Prev</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&<?= http_build_query($qs) ?>"
                        class="px-3 py-1.5 bg-white border rounded text-sm hover:bg-gray-50">Next →</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
