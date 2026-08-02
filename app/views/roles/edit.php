<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">✏️ Edit Role: <?= e($role['name']) ?></h1>
            <p class="text-gray-500 text-sm mt-1">
                <?php if ($role['is_system']): ?>
                    Built-in role — name can't be changed, but you can adjust what it can access.
                <?php else: ?>
                    Custom role
                <?php endif; ?>
            </p>
        </div>
    </div>

    <div class="mb-4">
        <a href="<?= BASE_URL ?>/roles"
            class="bg-gray-100 hover:bg-gray-200 text-gray-800 px-4 py-2.5 rounded-lg text-sm font-medium transition inline-block">
            ← Back to Roles
        </a>
    </div>

    <?= flashMessage() ?>

    <div class="bg-white rounded-xl shadow-sm border">
        <form action="<?= BASE_URL ?>/roles/edit/<?= $role['id'] ?>" method="POST" class="p-6 space-y-6">
            <?= csrfField() ?>

            <?php if ($role['is_system']): ?>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Role Name</label>
                    <input type="text" disabled value="<?= e($role['name']) ?>"
                        class="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-500">
                </div>
            <?php else: ?>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Role Name *</label>
                    <input type="text" name="name" required value="<?= e(old('name', $role['name'])) ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
            <?php endif; ?>

            <?php if ($role['slug'] === 'admin'): ?>
                <div class="bg-yellow-50 border border-yellow-300 text-yellow-800 text-xs rounded-lg p-3">
                    "Manage Users" and "Manage Roles & Permissions" are always kept on for the Admin
                    role, even if unchecked below — otherwise it would be possible to lock every
                    admin out of these screens with no way back in except a direct database edit.
                </div>
            <?php endif; ?>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Permissions</label>
                <div class="space-y-4">
                    <?php foreach ($catalog as $category => $permissions): ?>
                        <div class="border rounded-lg p-4">
                            <div class="font-semibold text-sm text-gray-700 mb-2"><?= e($category) ?></div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <?php foreach ($permissions as $perm):
                                    $isLockedOn = $role['slug'] === 'admin' && in_array($perm['key'], ['users.manage', 'roles.manage'], true);
                                ?>
                                    <label class="flex items-center gap-2 text-sm text-gray-700 <?= $isLockedOn ? 'opacity-60' : '' ?>">
                                        <input type="checkbox" name="permissions[]" value="<?= e($perm['key']) ?>"
                                            <?= (in_array($perm['key'], $checked, true) || $isLockedOn) ? 'checked' : '' ?>
                                            <?= $isLockedOn ? 'disabled' : '' ?>
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <?= e($perm['label']) ?>
                                        <?php if ($isLockedOn): ?><span class="text-gray-400">(always on)</span><?php endif; ?>
                                    </label>
                                    <?php if ($isLockedOn): ?>
                                        <!-- disabled checkboxes aren't submitted by the browser, so force it through -->
                                        <input type="hidden" name="permissions[]" value="<?= e($perm['key']) ?>">
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t">
                <a href="<?= BASE_URL ?>/roles"
                    class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-2 px-4 rounded-lg text-sm transition">
                    Cancel
                </a>
                <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg text-sm transition">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
