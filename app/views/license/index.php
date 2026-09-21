<?php include APP_PATH . '/views/layout/header.php'; ?>
<div class="max-w-xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Subscription</h1>
        <p class="text-sm text-gray-500 mt-1">Status and renewal for this installation.</p>
    </div>

    <?= flashMessage() ?>

    <?php
    $state         = licenseState();
    $daysRemaining = licenseDaysRemaining();
    $expiresAt     = licenseExpiresAt();

    $stateMeta = [
        'active'  => ['border-gray-200', 'text-green-700', 'Active'],
        'warning' => ['border-yellow-300 bg-yellow-50', 'text-yellow-700', $daysRemaining >= 0 ? 'Renew soon' : 'Grace period'],
        'locked'  => ['border-red-300 bg-red-50', 'text-red-700', 'Locked'],
    ];
    [$boxClass, $textClass, $label] = $stateMeta[$state];
    ?>

    <div class="bg-white rounded-xl shadow-sm border p-6 mb-6 <?= $boxClass ?>">
        <div class="flex items-center justify-between mb-2">
            <h2 class="font-semibold text-gray-800">Status</h2>
            <span class="font-semibold <?= $textClass ?>"><?= e($label) ?></span>
        </div>
        <p class="text-sm text-gray-600">
            <?php if ($daysRemaining >= 0): ?>
                Active until <strong><?= formatDate($expiresAt, 'd M Y') ?></strong>
                (<?= $daysRemaining ?> day<?= $daysRemaining === 1 ? '' : 's' ?> remaining).
            <?php else: ?>
                Expired on <strong><?= formatDate($expiresAt, 'd M Y') ?></strong>
                (<?= abs($daysRemaining) ?> day<?= abs($daysRemaining) === 1 ? '' : 's' ?> ago).
            <?php endif; ?>
        </p>
        <?php if ($state === 'locked'): ?>
            <p class="text-sm text-red-700 mt-3 font-medium">
                This installation is locked. Enter a new unlock code below to restore access.
            </p>
        <?php elseif ($state === 'warning'): ?>
            <p class="text-sm text-yellow-700 mt-3">
                Renew before the grace period ends to avoid being locked out.
            </p>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-6 mb-6">
        <h2 class="font-semibold text-gray-800 mb-1">Installation ID</h2>
        <p class="text-sm text-gray-500 mb-3">Quote this when requesting a renewal code.</p>
        <code class="block bg-gray-50 border rounded-lg px-4 py-3 text-sm font-mono tracking-wide"><?= e(licenseInstallId()) ?></code>
    </div>

    <?php if (can('settings.manage')): ?>
        <form method="POST" class="bg-white rounded-xl shadow-sm border p-6">
            <?= csrfField() ?>
            <h2 class="font-semibold text-gray-800 mb-1">Enter unlock code</h2>
            <p class="text-sm text-gray-500 mt-1 mb-4">Paste the full code exactly as you received it.</p>
            <textarea name="code" rows="3" required
                class="w-full rounded-lg border-gray-300 font-mono text-sm focus:ring-blue-500 focus:border-blue-500"
                placeholder="IBS2-..."></textarea>
            <div class="mt-4 flex justify-end">
                <button class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2 rounded-lg">Activate</button>
            </div>
        </form>
    <?php else: ?>
        <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-xl p-6 text-sm">
            Ask an administrator to enter a renewal code to restore access.
        </div>
    <?php endif; ?>
</div>
<?php include APP_PATH . '/views/layout/footer.php'; ?>
