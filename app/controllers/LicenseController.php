<?php

/**
 * Subscription / license status + unlock-code activation.
 *
 * Deliberately NOT gated by a blanket permission check like
 * SettingsController — every logged-in user can view /license (they
 * need to see it when the install is locked, whatever their role),
 * but only settings.manage can actually submit a code.
 */

if (!defined('APP_START')) {
    die('Direct access not permitted');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!can('settings.manage')) {
        http_response_code(403);
        echo 'Access denied';
        return;
    }

    if (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
        redirect(BASE_URL . '/license', 'error', 'Invalid security token. Please try again.');
    }

    $code = trim($_POST['code'] ?? '');
    if ($code === '') {
        redirect(BASE_URL . '/license', 'error', 'Enter an unlock code.');
    }

    $result = applyLicenseCode($code);
    redirect(BASE_URL . '/license', $result['ok'] ? 'success' : 'error', $result['message']);
}

$pageTitle = 'Subscription';
include APP_PATH . '/views/license/index.php';
