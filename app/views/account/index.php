<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="max-w-lg mx-auto">

    <h1 class="text-2xl font-bold text-gray-800 mb-6">My Account</h1>

    <?= flashMessage() ?>

    <!-- Profile Card -->
    <div class="bg-white rounded-lg shadow p-6 mb-4">
        <div class="flex items-center gap-4 mb-6">
            <div class="w-14 h-14 rounded-full bg-blue-600 flex items-center justify-center
                        text-white text-2xl font-bold flex-shrink-0">
                <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
            </div>
            <div>
                <div class="text-xl font-bold text-gray-800"><?= e($user['full_name']) ?></div>
                <div class="text-sm text-gray-500 font-mono">@<?= e($user['username']) ?></div>
                <?php
                $roleBadge = [
                    'admin'   => 'bg-purple-100 text-purple-700',
                    'staff'   => 'bg-blue-100 text-blue-700',
                    'cashier' => 'bg-green-100 text-green-700',
                ][$user['role']] ?? 'bg-gray-100 text-gray-600';
                ?>
                <span class="inline-block mt-1 px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $roleBadge ?>">
                    <?= ucfirst($user['role']) ?>
                </span>
            </div>
        </div>

        <div class="divide-y divide-gray-100 text-sm">
            <div class="flex justify-between py-3">
                <span class="text-gray-500">Member since</span>
                <span class="text-gray-800"><?= formatDate($user['created_at'], 'd M Y') ?></span>
            </div>
            <div class="flex justify-between py-3">
                <span class="text-gray-500">Last login</span>
                <span class="text-gray-800">
                    <?= !empty($user['last_login'])
                        ? formatDate($user['last_login'], 'd M Y H:i')
                        : '<span class="text-gray-400">—</span>' ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="bg-white rounded-lg shadow divide-y divide-gray-100">
        <a href="<?= BASE_URL ?>/account/password"
            class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition group">
            <div class="flex items-center gap-3">
                <span class="text-xl">🔒</span>
                <div>
                    <div class="font-medium text-gray-800 text-sm">Change Password</div>
                    <div class="text-xs text-gray-400">Update your login password</div>
                </div>
            </div>
            <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </a>

        <a href="<?= BASE_URL ?>/logout"
            class="flex items-center justify-between px-6 py-4 hover:bg-red-50 transition group">
            <div class="flex items-center gap-3">
                <span class="text-xl">🚪</span>
                <div>
                    <div class="font-medium text-red-600 text-sm">Sign Out</div>
                    <div class="text-xs text-gray-400">End your current session</div>
                </div>
            </div>
            <svg class="w-4 h-4 text-gray-400 group-hover:text-red-500 transition"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </a>
    </div>

</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>