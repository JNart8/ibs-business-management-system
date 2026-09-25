<?php

if (!defined('APP_START')) die('Direct access not permitted');

if (!can('settings.manage')) {
    http_response_code(403);
    echo 'Access denied';
    return;
}

$db = Database::getInstance();
$settingsBefore = $db->fetchOne("SELECT * FROM settings ORDER BY id ASC LIMIT 1") ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
        redirect(BASE_URL . '/settings', 'error', 'Invalid security token. Please try again.');
    }

    $discountType = $_POST['sale_discount_type'] ?? 'percentage';
    if (!in_array($discountType, ['percentage', 'flat'], true)) {
        redirect(BASE_URL . '/settings', 'error', 'Invalid discount type.');
    }
    $posDefaultWalkin = isset($_POST['pos_default_walkin']) ? 1 : 0;
    $trackExpiry      = isset($_POST['track_expiry']) ? 1 : 0;
    $warningDays      = (int) ($_POST['expiry_warning_days'] ?? 90);
    if ($warningDays < 1 || $warningDays > 730) {
        redirect(BASE_URL . '/settings', 'error', 'The expiring-soon warning must be between 1 and 730 days.');
    }
    $expiredSales = $_POST['expired_sales'] ?? 'block';
    if (!in_array($expiredSales, ['block', 'warn'], true)) {
        redirect(BASE_URL . '/settings', 'error', 'Invalid choice for selling expired stock.');
    }

    // Must be a real IANA identifier (the Settings form only ever offers
    // ones from this same list) — never trust it blind, since it's about
    // to be handed to date_default_timezone_set()/SET time_zone.
    $timezone = trim($_POST['timezone'] ?? '');
    if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
        redirect(BASE_URL . '/settings', 'error', 'Invalid timezone selected.');
    }

    // If this is turned on, make sure a walk-in customer actually exists —
    // see ensureWalkInCustomerExists() for why this can't be assumed.
    if ($posDefaultWalkin) {
        ensureWalkInCustomerExists($db);
    }

    $timezoneChanged = $timezone !== ($settingsBefore['timezone'] ?? '');

    $db->query(
        "UPDATE settings
         SET sale_discount_type = ?, pos_default_walkin = ?, timezone = ?,
             track_expiry = ?, expiry_warning_days = ?, expired_sales = ?
         ORDER BY id ASC LIMIT 1",
        [$discountType, $posDefaultWalkin, $timezone, $trackExpiry, $warningDays, $expiredSales]
    );

    logAudit('settings.update', 'settings', null, [
        'sale_discount_type'  => $discountType,
        'pos_default_walkin'  => (bool) $posDefaultWalkin,
        'timezone'            => $timezone,
        'track_expiry'        => (bool) $trackExpiry,
        'expiry_warning_days' => $warningDays,
        'expired_sales'       => $expiredSales,
    ], true);

    // Takes effect immediately for everyone from their NEXT request — this
    // process already resolved the OLD timezone when it connected at
    // bootstrap (app.php), and PHP's default timezone can't be changed
    // mid-request for what's already been computed, so redirecting (a
    // fresh request) is what actually shows the new time, not anything
    // done here.
    redirect(BASE_URL . '/settings', 'success', $timezoneChanged
        ? 'Settings updated. Timestamps now use ' . $timezone . '.'
        : 'Settings updated.');
}

$settings = $db->fetchOne("SELECT * FROM settings ORDER BY id ASC LIMIT 1") ?: [];
$pageTitle = 'Settings';
include APP_PATH . '/views/settings/index.php';
