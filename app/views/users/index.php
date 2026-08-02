<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="flex flex-wrap justify-between items-center mb-6 gap-3">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Users</h1>
        <p class="text-gray-500 text-sm mt-1">Manage system access and roles</p>
    </div>
    <a href="<?= BASE_URL ?>/users/create"
        class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg shadow transition">
        + Add User
    </a>
</div>

<?= flashMessage() ?>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="px-5 py-3 text-left   text-xs font-medium text-gray-500 uppercase">User</th>
                <th class="px-5 py-3 text-left   text-xs font-medium text-gray-500 uppercase">Username</th>
                <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase">Role</th>
                <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase">Last Login</th>
                <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php if (empty($users)): ?>
                <tr>
                    <td colspan="6" class="px-6 py-16 text-center text-gray-400">
                        <div class="text-5xl mb-3">👤</div>
                        <p>No users found</p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($users as $u):
                    $isSelf   = $u['id'] == $_SESSION['user_id'];
                    $isLocked = !empty($u['locked_until']) && strtotime($u['locked_until']) > time();
                    $isActive = $u['is_active'] == 1;

                    $roleBadge = [
                        'admin'   => 'bg-purple-100 text-purple-700',
                        'staff'   => 'bg-blue-100 text-blue-700',
                        'cashier' => 'bg-green-100 text-green-700',
                    ][$u['role']] ?? 'bg-indigo-100 text-indigo-700';
                    $roleDisplayName = $u['role_id'] ? roleName($u['role_id']) : ucfirst($u['role']);
                ?>
                    <tr class="hover:bg-gray-50 transition <?= !$isActive ? 'opacity-50' : '' ?>">

                        <!-- Name + avatar -->
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full flex items-center justify-center
                                        text-white text-sm font-bold flex-shrink-0
                                        <?= $isLocked ? 'bg-red-500' : ($isActive ? 'bg-blue-600' : 'bg-gray-400') ?>">
                                    <?= $isLocked ? '🔒' : strtoupper(substr($u['full_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="font-medium text-gray-800 text-sm">
                                        <?= e($u['full_name']) ?>
                                        <?php if ($isSelf): ?>
                                            <span class="text-xs text-blue-500 font-normal">(you)</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-xs text-gray-400">
                                        Added <?= formatDate($u['created_at'], 'd M Y') ?>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- Username -->
                        <td class="px-5 py-4 text-sm text-gray-600 font-mono">
                            <?= e($u['username']) ?>
                        </td>

                        <!-- Role -->
                        <td class="px-5 py-4 text-center">
                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= $roleBadge ?>">
                                <?= e($roleDisplayName) ?>
                            </span>
                        </td>

                        <!-- Status — shows locked if applicable, else active/inactive -->
                        <td class="px-5 py-4 text-center">
                            <?php if ($isLocked): ?>
                                <div class="flex flex-col items-center gap-0.5">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-600">
                                        🔒 Locked
                                    </span>
                                    <span class="text-xs text-gray-400">
                                        until <?= formatDate($u['locked_until'], 'H:i') ?>
                                    </span>
                                </div>
                            <?php elseif ($isActive): ?>
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                                    ✓ Active
                                </span>
                            <?php else: ?>
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">
                                    ✗ Inactive
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Last login -->
                        <td class="px-5 py-4 text-center text-xs text-gray-500">
                            <?php if ($isLocked && $u['login_attempts'] > 0): ?>
                                <span class="text-red-400">
                                    <?= $u['login_attempts'] ?> failed attempt<?= $u['login_attempts'] > 1 ? 's' : '' ?>
                                </span>
                            <?php elseif (!empty($u['last_login'])): ?>
                                <?= formatDate($u['last_login'], 'd M Y') ?>
                                <div class="text-gray-400"><?= formatDate($u['last_login'], 'H:i') ?></div>
                            <?php else: ?>
                                <span class="text-gray-300">Never</span>
                            <?php endif; ?>
                        </td>

                        <!-- Actions -->
                        <td class="px-5 py-4">
                            <div class="flex justify-center gap-1">

                                <!-- Unlock (only shown when locked) -->
                                <?php if ($isLocked): ?>
                                    <a href="<?= BASE_URL ?>/users/unlock/<?= $u['id'] ?>"
                                        onclick="return confirm('Unlock <?= e($u['full_name']) ?>\'s account?')"
                                        class="p-1.5 rounded bg-green-50 text-green-600 hover:bg-green-100 transition"
                                        title="Unlock Account">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6
                                                 a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                                        </svg>
                                    </a>
                                <?php endif; ?>

                                <!-- Activate (only shown when inactive) - NEW! -->
                                <?php if (!$isActive && !$isSelf): ?>
                                    <a href="<?= BASE_URL ?>/users/activate/<?= $u['id'] ?>"
                                        onclick="return confirm('Activate <?= e($u['full_name']) ?>\'s account?')"
                                        class="p-1.5 rounded bg-green-50 text-green-600 hover:bg-green-100 transition"
                                        title="Activate Account">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </a>
                                <?php endif; ?>

                                <!-- Edit (always shown for active users) -->
                                <?php if ($isActive): ?>
                                    <a href="<?= BASE_URL ?>/users/edit/<?= $u['id'] ?>"
                                        class="p-1.5 rounded bg-blue-50 text-blue-600 hover:bg-blue-100 transition"
                                        title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5
                                                 m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>

                                    <!-- Change password (only for active users) -->
                                    <a href="<?= BASE_URL ?>/users/password/<?= $u['id'] ?>"
                                        class="p-1.5 rounded bg-yellow-50 text-yellow-600 hover:bg-yellow-100 transition"
                                        title="Change Password">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11
                                                 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                        </svg>
                                    </a>
                                <?php endif; ?>

                                <!-- Deactivate (only for active users, not self) -->
                                <?php if ($isActive && !$isSelf): ?>
                                    <a href="<?= BASE_URL ?>/users/delete/<?= $u['id'] ?>"
                                        onclick="return confirmDelete('Deactivate <?= e($u['full_name']) ?>?')"
                                        class="p-1.5 rounded bg-red-50 text-red-500 hover:bg-red-100 transition"
                                        title="Deactivate">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M18.364 18.364A9 9 0 005.636 5.636m12.728
                                                 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                        </svg>
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

<!-- Helper Text -->
<div class="mt-4 p-4 bg-blue-50 rounded-lg">
    <p class="text-sm text-blue-800">
        <strong>💡 Tip:</strong> Inactive users can be reactivated anytime using the <strong>✓</strong> button.
        Locked accounts (🔒) can be unlocked to allow immediate login.
    </p>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>