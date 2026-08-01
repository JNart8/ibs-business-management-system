<?php

/**
 * Account Controller
 * Any logged-in user can manage their own profile and password
 */

if (!defined('APP_START')) {
    die('Direct access not permitted');
}

$db = Database::getInstance();

// Parse action
$segments = array_values(array_filter(explode('/', trim($path, '/'))));
$action   = $segments[1] ?? 'index';

switch ($action) {
    case 'index':
        showAccount($db);
        break;
    case 'password':
        $method === 'POST' ? updatePassword($db) : showPasswordForm($db);
        break;
    default:
        redirect(BASE_URL . '/account');
}

// ============================================================
// FUNCTIONS
// ============================================================

function showAccount($db)
{
    $user      = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    $pageTitle = 'My Account';
    include APP_PATH . '/views/account/index.php';
}

function showPasswordForm($db)
{
    $user      = $db->fetchOne("SELECT id, username, full_name FROM users WHERE id = ?", [$_SESSION['user_id']]);
    $pageTitle = 'Change Password';
    include APP_PATH . '/views/account/password.php';
}

function updatePassword($db)
{
    $currentPassword = $_POST['current_password']  ?? '';
    $newPassword     = $_POST['password']           ?? '';
    $confirmPassword = $_POST['password_confirm']   ?? '';

    $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);

    // Verify current password first
    if (!password_verify($currentPassword, $user['password_hash'])) {
        redirect(BASE_URL . '/account/password', 'error', 'Current password is incorrect.');
    }

    if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
        redirect(
            BASE_URL . '/account/password',
            'error',
            'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.'
        );
    }

    if ($newPassword !== $confirmPassword) {
        redirect(BASE_URL . '/account/password', 'error', 'New passwords do not match.');
    }

    if ($newPassword === $currentPassword) {
        redirect(
            BASE_URL . '/account/password',
            'error',
            'New password must be different from your current password.'
        );
    }

    $db->query(
        "UPDATE users SET password_hash = ? WHERE id = ?",
        [password_hash($newPassword, PASSWORD_DEFAULT), $_SESSION['user_id']]
    );

    // Force re-login with new password
    redirect(BASE_URL . '/logout', 'success', 'Password changed successfully. Please log in again.');
}
