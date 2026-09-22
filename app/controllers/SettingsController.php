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
        "UPDATE settings SET sale_discount_type = ?, pos_default_walkin = ?, timezone = ? ORDER BY id ASC LIMIT 1",
        [$discountType, $posDefaultWalkin, $timezone]
    );

    logAudit('settings.update', 'settings', null, [
        'sale_discount_type' => $discountType,
        'pos_default_walkin' => (bool) $posDefaultWalkin,
        'timezone'           => $timezone,
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
