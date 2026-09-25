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

        <fieldset class="mt-6 pt-6 border-t">
            <legend class="font-semibold text-gray-800">Timezone</legend>
            <p class="text-sm text-gray-500 mt-1 mb-3">
                Controls what "now" means everywhere in this install — sale/purchase timestamps,
                reports, exports, session timeouts. Set this to wherever this business actually
                operates; it's per-install, not tied to any one country, so a client anywhere can
                use it correctly.
            </p>
            <?php
                $currentTimezone = $settings['timezone'] ?? 'Africa/Accra';
                // Grouped by continent/region (the part before the "/" in each IANA
                // identifier) so the list is actually browsable instead of one flat
                // alphabetical wall of ~400 entries.
                $timezoneGroups = [];
                foreach (DateTimeZone::listIdentifiers() as $tz) {
                    $region = strstr($tz, '/', true) ?: 'Other';
                    $timezoneGroups[$region][] = $tz;
                }
                ksort($timezoneGroups);
            ?>
            <select name="timezone" required
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <?php foreach ($timezoneGroups as $region => $zones): ?>
                    <optgroup label="<?= e($region) ?>">
                        <?php foreach ($zones as $tz): ?>
                            <option value="<?= e($tz) ?>" <?= $tz === $currentTimezone ? 'selected' : '' ?>><?= e($tz) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-400 mt-1">
                Currently: <strong><?= e($currentTimezone) ?></strong> —
                right now that's <strong><?= (new DateTime('now', new DateTimeZone($currentTimezone)))->format('d M Y, H:i') ?></strong>.
                Changing this takes effect immediately for everyone, from their next page load.
            </p>
        </fieldset>

        <fieldset class="mt-6 pt-6 border-t">
            <legend class="font-semibold text-gray-800">POS default customer</legend>
            <p class="text-sm text-gray-500 mt-1 mb-3">
                Most shops sell mostly to walk-in customers, so POS pre-selects "Walk-in Customer"
                on every new sale by default. Turn this off if most of your sales go to registered
                (often credit) customers and you'd rather the cashier always pick one explicitly —
                walk-in customers can't buy on credit or use deposit, so this is purely a workflow
                preference, not a safety setting.
            </p>
            <label class="flex items-center gap-3 rounded-lg border p-4 cursor-pointer">
                <input type="checkbox" name="pos_default_walkin" value="1"
                    <?= ($settings['pos_default_walkin'] ?? 1) ? 'checked' : '' ?>
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span>
                    <span class="block font-medium">Pre-select Walk-in Customer at the start of each sale</span>
                    <span class="text-sm text-gray-500">Uncheck to start each sale with no customer selected.</span>
                </span>
            </label>
        </fieldset>

        <fieldset class="mt-6 pt-6 border-t">
            <legend class="font-semibold text-gray-800">Account balances</legend>
            <p class="text-sm text-gray-500 mt-1 mb-3">
                Wherever staff choose an account to pay into or out of — the POS, purchases, expenses,
                customer and supplier deposits — each account is listed with how much it holds. Turn
                this off if you'd rather cashiers and other staff didn't see that. They can still
                choose the account; only people whose role can open Financial Accounts keep seeing
                the balances.
            </p>
            <label class="flex items-center gap-3 rounded-lg border p-4 cursor-pointer">
                <input type="checkbox" name="show_account_balances_to_all" value="1"
                    <?= ($settings['show_account_balances_to_all'] ?? 1) ? 'checked' : '' ?>
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span>
                    <span class="block font-medium">Show account balances to all staff</span>
                    <span class="text-sm text-gray-500">Uncheck to show them only to roles with Financial Accounts access.</span>
                </span>
            </label>
        </fieldset>

        <fieldset class="mt-6 pt-6 border-t">
            <legend class="font-semibold text-gray-800">Batch &amp; expiry tracking</legend>
            <p class="text-sm text-gray-500 mt-1 mb-3">
                For businesses selling goods that expire — medicines, food, agro-chemicals. Once on,
                you choose which products to track (on each product's edit page), and their stock is
                kept by batch with its expiry date. Products you don't track work exactly as before.
                Leave this off if nothing you sell expires.
            </p>
            <label class="flex items-center gap-3 rounded-lg border p-4 cursor-pointer">
                <input type="checkbox" name="track_expiry" value="1"
                    <?= !empty($settings['track_expiry']) ? 'checked' : '' ?>
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span>
                    <span class="block font-medium">Track batches and expiry dates</span>
                    <span class="text-sm text-gray-500">Turning this off hides batch details but keeps them; turning it back on picks up where you left off.</span>
                </span>
            </label>
            <label class="block text-sm font-medium text-gray-700 mt-4 mb-1" for="expiry_warning_days">
                Warn about batches expiring within
            </label>
            <div class="flex items-center gap-2">
                <input type="number" id="expiry_warning_days" name="expiry_warning_days" min="1" max="730" required
                    value="<?= (int) ($settings['expiry_warning_days'] ?? 90) ?>"
                    class="w-28 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <span class="text-sm text-gray-600">days</span>
            </div>
            <p class="text-xs text-gray-400 mt-1">
                90 days suits most pharmacies — suppliers often only take returns up to 3 months before expiry.
            </p>

            <?php $expiredSales = $settings['expired_sales'] ?? 'block'; ?>
            <div class="text-sm font-medium text-gray-700 mt-4 mb-2">Selling expired stock</div>
            <div class="space-y-3">
                <label class="flex gap-3 rounded-lg border p-4 cursor-pointer">
                    <input type="radio" name="expired_sales" value="block" <?= $expiredSales !== 'warn' ? 'checked' : '' ?>>
                    <span><span class="block font-medium">Don't allow it</span><span class="text-sm text-gray-500">Expired batches can't be sold at the POS. Recommended — and what pharmacies need.</span></span>
                </label>
                <label class="flex gap-3 rounded-lg border p-4 cursor-pointer">
                    <input type="radio" name="expired_sales" value="warn" <?= $expiredSales === 'warn' ? 'checked' : '' ?>>
                    <span><span class="block font-medium">Allow, with a warning</span><span class="text-sm text-gray-500">In-date stock is still sold first; expired stock only once it runs out, flagged in the cart.</span></span>
                </label>
            </div>
        </fieldset>
        <div class="mt-6 flex justify-end">
            <button class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2 rounded-lg">Save setting</button>
        </div>
    </form>
</div>
<?php include APP_PATH . '/views/layout/footer.php'; ?>
