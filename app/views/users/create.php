<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="max-w-lg mx-auto">
    <div class="mb-5 text-sm">
        <a href="<?= BASE_URL ?>/users" class="text-blue-600 hover:underline">← Back to Users</a>
    </div>

    <h1 class="text-2xl font-bold text-gray-800 mb-6">Add New User</h1>

    <?= flashMessage() ?>

    <form method="POST" action="<?= BASE_URL ?>/users/create"
        class="bg-white rounded-lg shadow p-6 space-y-5"
        x-data="{ showPw: false, showPw2: false }">
        <?= csrfField() ?>

        <!-- Full Name -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Full Name <span class="text-red-500">*</span>
            </label>
            <input type="text" name="full_name"
                value="<?= e(old('full_name')) ?>"
                placeholder="e.g. Kofi Mensah"
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
                value="<?= e(old('username')) ?>"
                placeholder="e.g. kofi.mensah"
                required autocomplete="off"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm
                          focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>

        <!-- Role -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Role <span class="text-red-500">*</span>
            </label>
            <select name="role_id" required
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm
                           focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <?php
                $roleDescriptions = [
                    'cashier' => '🛒 Cashier — POS and sales only',
                    'staff'   => '👤 Staff — All features, no user management',
                    'admin'   => '⚙️ Admin — Full access including user management',
                ];
                foreach ($roles as $r):
                    $label = $roleDescriptions[$r['slug']] ?? ('🔧 ' . $r['name'] . ' (custom role)');
                ?>
                    <option value="<?= $r['id'] ?>" <?= old('role_id', $user['role_id'] ?? '') == $r['id'] ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-400 mt-1">
                Cashier: POS only · Staff: all features · Admin: full access including user management
                <?php if (planAllows('advanced_permissions')): ?>
                    · Need something in between? <a href="<?= BASE_URL ?>/roles" class="text-blue-600 hover:underline">Create a custom role</a>.
                <?php endif; ?>
            </p>
        </div>

        <?php if (hasMultiBranch()): ?>
        <!-- Branch assignment -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Branch <span class="text-red-500">*</span>
            </label>
            <div class="space-y-2 border border-gray-200 rounded-lg p-3">
                <?php $firstBranchId = $branches[0]['id'] ?? null; ?>
                <?php foreach ($branches as $b): ?>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="radio" name="branch_id" value="<?= $b['id'] ?>"
                            <?= (old('branch_id', '') !== '' ? old('branch_id', '') == $b['id'] : $b['id'] === $firstBranchId) ? 'checked' : '' ?>
                            class="border-gray-300 text-blue-600 focus:ring-blue-500">
                        <?= e($b['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="text-xs text-gray-400 mt-1">
                Where this user's POS/sales are recorded, and — for a Branch-level user below —
                the only branch they can see or manage. A Company-wide user still needs one
                branch here for defaulting, but isn't limited to it.
            </p>
        </div>

        <!-- Branch visibility scope — a branch-scoped admin can't grant
             company-wide access to anyone (enforced server-side too), so
             they only get the one option. -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Visibility</label>
            <div class="space-y-2">
                <label class="flex items-start gap-2 text-sm text-gray-700 border rounded-lg p-3 cursor-pointer">
                    <input type="radio" name="branch_scope" value="assigned"
                        <?= old('branch_scope', 'assigned') !== 'all' ? 'checked' : '' ?>
                        class="mt-0.5 border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span>
                        <span class="block font-medium">Branch-level (default)</span>
                        <span class="text-xs text-gray-500">Only sees/manages data for the branches checked above.</span>
                    </span>
                </label>
                <?php if (isCompanyWide()): ?>
                <label class="flex items-start gap-2 text-sm text-gray-700 border rounded-lg p-3 cursor-pointer">
                    <input type="radio" name="branch_scope" value="all"
                        <?= old('branch_scope', '') === 'all' ? 'checked' : '' ?>
                        class="mt-0.5 border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span>
                        <span class="block font-medium">Company-wide</span>
                        <span class="text-xs text-gray-500">Sees/manages every branch, regardless of the assignment above — e.g. an owner or head-office admin.</span>
                    </span>
                </label>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Password -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Password <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <input :type="showPw ? 'text' : 'password'" name="password"
                    placeholder="Min <?= PASSWORD_MIN_LENGTH ?> characters"
                    required autocomplete="new-password"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm pr-10
                              focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <button type="button" @click="showPw = !showPw"
                    class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600">
                    <span x-text="showPw ? '🙈' : '👁'"></span>
                </button>
            </div>
        </div>

        <!-- Confirm Password -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Confirm Password <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <input :type="showPw2 ? 'text' : 'password'" name="password_confirm"
                    placeholder="Repeat password"
                    required autocomplete="new-password"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm pr-10
                              focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <button type="button" @click="showPw2 = !showPw2"
                    class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600">
                    <span x-text="showPw2 ? '🙈' : '👁'"></span>
                </button>
            </div>
        </div>

        <!-- Active toggle -->
        <div class="flex items-center gap-3">
            <input type="checkbox" name="is_active" id="is_active" value="1" checked
                class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500">
            <label for="is_active" class="text-sm text-gray-700">Active (can log in)</label>
        </div>

        <!-- Actions -->
        <div class="flex justify-between pt-2">
            <a href="<?= BASE_URL ?>/users"
                class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition text-sm">
                Cancel
            </a>
            <button type="submit"
                class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg shadow transition text-sm font-semibold">
                Create User
            </button>
        </div>
    </form>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>