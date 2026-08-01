<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="max-w-lg mx-auto">
    <div class="mb-5 text-sm">
        <a href="<?= BASE_URL ?>/users" class="text-blue-600 hover:underline">← Back to Users</a>
    </div>

    <h1 class="text-2xl font-bold text-gray-800 mb-6">Edit User</h1>

    <?= flashMessage() ?>

    <form method="POST" action="<?= BASE_URL ?>/users/edit/<?= $user['id'] ?>"
        class="bg-white rounded-lg shadow p-6 space-y-5">
        <?= csrfField() ?>

        <!-- Full Name -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Full Name <span class="text-red-500">*</span>
            </label>
            <input type="text" name="full_name"
                value="<?= e(old('full_name', $user['full_name'])) ?>"
                required
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm
                          focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>

        <!-- Username -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Username <span class="text-red-500">*</span>
            </label>
            <input type="text" name="username"
                value="<?= e(old('username', $user['username'])) ?>"
                required autocomplete="off"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm
                          focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>

        <!-- Role -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
            <select name="role"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm
                           focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <?php foreach (
                    [
                        'cashier' => '🛒 Cashier  — POS and sales only',
                        'staff'   => '👤 Staff    — All features, no user management',
                        'admin'   => '⚙️  Admin    — Full access including user management',
                    ] as $val => $label
                ): ?>
                    <option value="<?= $val ?>" <?= old('role', $user['role'] ?? '') === $val ? 'selected' : '' ?>>
                        <?= $label ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Active toggle -->
        <div class="flex items-center gap-3">
            <input type="checkbox" name="is_active" id="is_active" value="1"
                <?= $user['is_active'] ? 'checked' : '' ?>
                class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500">
            <label for="is_active" class="text-sm text-gray-700">Active (can log in)</label>
        </div>

        <!-- Password link -->
        <div class="bg-gray-50 rounded-lg px-4 py-3 text-sm text-gray-500 flex justify-between items-center">
            <span>To change password, use the dedicated form</span>
            <a href="<?= BASE_URL ?>/users/password/<?= $user['id'] ?>"
                class="text-blue-600 hover:underline font-medium">Change →</a>
        </div>

        <!-- Actions -->
        <div class="flex justify-between pt-2">
            <a href="<?= BASE_URL ?>/users"
                class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition text-sm">
                Cancel
            </a>
            <button type="submit"
                class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg shadow transition text-sm font-semibold">
                Save Changes
            </button>
        </div>
    </form>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>