<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="flex flex-wrap justify-between items-center mb-6 gap-3">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Branches</h1>
        <p class="text-gray-500 text-sm mt-1">
            Manage locations. Staff can be assigned to one or more branches from the Users page.
        </p>
    </div>
    <a href="<?= BASE_URL ?>/branches/create"
        class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg shadow transition">
        + Add Branch
    </a>
</div>

<?= flashMessage() ?>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="px-5 py-3 text-left   text-xs font-medium text-gray-500 uppercase">Branch</th>
                <th class="px-5 py-3 text-left   text-xs font-medium text-gray-500 uppercase">Contact</th>
                <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase">Staff Assigned</th>
                <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php if (empty($branches)): ?>
                <tr>
                    <td colspan="5" class="px-6 py-16 text-center text-gray-400">
                        <div class="text-5xl mb-3">🏢</div>
                        <p>No branches found</p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($branches as $b): ?>
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-5 py-4 font-medium text-gray-800 text-sm">
                            <?= e($b['name']) ?>
                        </td>
                        <td class="px-5 py-4 text-sm text-gray-600">
                            <?= e($b['phone'] ?? '—') ?>
                        </td>
                        <td class="px-5 py-4 text-center">
                            <?php if ($b['is_active']): ?>
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">Active</span>
                            <?php else: ?>
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-4 text-center text-sm text-gray-600">
                            <?= intval($b['user_count']) ?>
                        </td>
                        <td class="px-5 py-4 text-center">
                            <a href="<?= BASE_URL ?>/branches/edit/<?= $b['id'] ?>"
                                class="p-1.5 rounded bg-blue-50 text-blue-600 hover:bg-blue-100" title="Edit branch">
                                ✏️
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
