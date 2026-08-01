<?php include APP_PATH . '/views/layout/header.php'; ?>
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Settings</h1>
        <p class="text-sm text-gray-500 mt-1">Configure how this installation handles sales.</p>
    </div>
    <?= flashMessage() ?>

    <div class="bg-white rounded-xl shadow-sm border p-6 mb-6">
        <h2 class="font-semibold text-gray-800 mb-1">Current plan</h2>
        <p class="text-2xl font-bold text-blue-600 mb-2"><?= e(currentPlanDefinition()['label']) ?></p>
        <p class="text-sm text-gray-500">
            Up to <?= currentPlanDefinition()['max_users'] >= PHP_INT_MAX ? 'unlimited' : currentPlanDefinition()['max_users'] ?> users.
            To change plans, contact your provider — this isn't self-service yet.
        </p>
    </div>

    <form method="POST" class="bg-white rounded-xl shadow-sm border p-6">
        <?= csrfField() ?>
        <fieldset>
            <legend class="font-semibold text-gray-800">POS sale discount type</legend>
            <p class="text-sm text-gray-500 mt-1 mb-4">
                This controls both individual cart-item discounts and the order discount beside the POS total.
            </p>
            <?php $discountType = $settings['sale_discount_type'] ?? 'percentage'; ?>
            <div class="space-y-3">
                <label class="flex gap-3 rounded-lg border p-4 cursor-pointer">
                    <input type="radio" name="sale_discount_type" value="percentage" <?= $discountType === 'percentage' ? 'checked' : '' ?>>
                    <span><span class="block font-medium">Percentage</span><span class="text-sm text-gray-500">Example: 10% off the subtotal.</span></span>
                </label>
                <label class="flex gap-3 rounded-lg border p-4 cursor-pointer">
                    <input type="radio" name="sale_discount_type" value="flat" <?= $discountType === 'flat' ? 'checked' : '' ?>>
                    <span><span class="block font-medium">Flat amount</span><span class="text-sm text-gray-500">Example: <?= formatMoney(10) ?> off the subtotal.</span></span>
                </label>
            </div>
        </fieldset>
        <div class="mt-6 flex justify-end">
            <button class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2 rounded-lg">Save setting</button>
        </div>
    </form>
</div>
<?php include APP_PATH . '/views/layout/footer.php'; ?>
