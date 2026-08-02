<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="flex flex-wrap justify-between items-center mb-6 gap-3">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Roles & Permissions</h1>
        <p class="text-gray-500 text-sm mt-1">
            Enterprise feature — create custom roles or fine-tune what Admin/Staff/Cashier can access.
        </p>
    </div>
    <a href="<?= BASE_URL ?>/roles/create"
        class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg shadow transition">
        + Add Role
    </a>
</div>

<?= flashMessage() ?>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="px-5 py-3 text-left   text-xs font-medium text-gray-500 uppercase">Role</th>
                <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase">Type</th>
                <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase">Permissions</th>
                <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase">Users</th>
                <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php if (empty($roles)): ?>
                <tr>
                    <td colspan="5" class="px-6 py-16 text-center text-gray-400">
                        <div class="text-5xl mb-3">🔑</div>
                        <p>No roles found</p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($roles as $r): ?>
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-5 py-4 font-medium text-gray-800 text-sm">
                            <?= e($r['name']) ?>
                        </td>
                        <td class="px-5 py-4 text-center">
                            <?php if ($r['is_system']): ?>
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">Built-in</span>
                            <?php else: ?>
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-700">Custom</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-4 text-center text-sm text-gray-600">
                            <?= intval($r['permission_count']) ?>
                        </td>
                        <td class="px-5 py-4 text-center text-sm text-gray-600">
                            <?= intval($r['user_count']) ?>
                        </td>
                        <td class="px-5 py-4 text-center">
                            <div class="flex justify-center gap-2">
                                <a href="<?= BASE_URL ?>/roles/edit/<?= $r['id'] ?>"
                                    class="p-1.5 rounded bg-blue-50 text-blue-600 hover:bg-blue-100" title="Edit permissions">
                                    ✏️
                                </a>
                                <?php if (!$r['is_system']): ?>
                                    <form action="<?= BASE_URL ?>/roles/delete/<?= $r['id'] ?>" method="POST"
                                        onsubmit="return confirm('Delete the &quot;<?= e($r['name']) ?>&quot; role? This cannot be undone.');">
                                        <?= csrfField() ?>
                                        <button type="submit"
                                            class="p-1.5 rounded bg-red-50 text-red-600 hover:bg-red-100" title="Delete role">
                                            🗑️
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
