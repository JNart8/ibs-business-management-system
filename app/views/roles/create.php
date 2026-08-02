<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">+ Create Role</h1>
            <p class="text-gray-500 text-sm mt-1">Define a custom role and pick exactly what it can access</p>
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
        <form action="<?= BASE_URL ?>/roles/create" method="POST" class="p-6 space-y-6">
            <?= csrfField() ?>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Role Name *</label>
                <input type="text" name="name" required placeholder="e.g., Warehouse Supervisor, Branch Accountant"
                    value="<?= e(old('name', '')) ?>"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Permissions</label>
                <div class="space-y-4">
                    <?php foreach ($catalog as $category => $permissions): ?>
                        <div class="border rounded-lg p-4">
                            <div class="font-semibold text-sm text-gray-700 mb-2"><?= e($category) ?></div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <?php foreach ($permissions as $perm): ?>
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="permissions[]" value="<?= e($perm['key']) ?>"
                                            <?= in_array($perm['key'], $checked, true) ? 'checked' : '' ?>
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <?= e($perm['label']) ?>
                                    </label>
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
                    Create Role
                </button>
            </div>
        </form>
    </div>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>
