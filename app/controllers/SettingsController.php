<?php

if (!defined('APP_START')) die('Direct access not permitted');

if (!can('settings.manage')) {
    http_response_code(403);
    echo 'Access denied';
    return;
}

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
        redirect(BASE_URL . '/settings', 'error', 'Invalid security token. Please try again.');
    }

    $discountType = $_POST['sale_discount_type'] ?? 'percentage';
    if (!in_array($discountType, ['percentage', 'flat'], true)) {
        redirect(BASE_URL . '/settings', 'error', 'Invalid discount type.');
    }
    $posDefaultWalkin = isset($_POST['pos_default_walkin']) ? 1 : 0;

    // If this is turned on, make sure a walk-in customer actually exists —
    // see ensureWalkInCustomerExists() for why this can't be assumed.
    if ($posDefaultWalkin) {
        ensureWalkInCustomerExists($db);
    }

    $db->query(
        "UPDATE settings SET sale_discount_type = ?, pos_default_walkin = ? ORDER BY id ASC LIMIT 1",
        [$discountType, $posDefaultWalkin]
    );
    redirect(BASE_URL . '/settings', 'success', 'Settings updated.');
}

$settings = $db->fetchOne("SELECT * FROM settings ORDER BY id ASC LIMIT 1") ?: [];
$pageTitle = 'Settings';
include APP_PATH . '/views/settings/index.php';
