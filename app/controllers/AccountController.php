<?php

/**
 * Account Controller
 * Any logged-in user can manage their own profile and password
 */

if (!defined('APP_START')) {
    die('Direct access not permitted');
}

// $path/$method are always set by public/index.php before this file is
// included (same variable scope as the includer) — the ?? here is just to
// satisfy static analysis, which can't see across the include boundary.
/** @var string $path */
$path = $path ?? '';
/** @var string $method */
$method = $method ?? '';

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
    case 'switch-branch':
        switchBranch($db);
        break;
    default:
        redirect(BASE_URL . '/account');
}

// ============================================================
// FUNCTIONS
// ============================================================

function showAccount(Database $db)
{
    $user      = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    $pageTitle = 'My Account';
    include APP_PATH . '/views/account/index.php';
}

function showPasswordForm(Database $db)
{
    $user      = $db->fetchOne("SELECT id, username, full_name FROM users WHERE id = ?", [$_SESSION['user_id']]);
    $pageTitle = 'Change Password';
    include APP_PATH . '/views/account/password.php';
}

function updatePassword(Database $db)
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

/**
 * Change the current user's active branch (the one POS/sales record
 * against, no picker shown elsewhere) — only to a branch they're
 * actually assigned to (any active branch for company-wide users; see
 * switchableBranches()), so this can't be used to spoof another
 * branch's activity.
 */
function switchBranch(Database $db)
{
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect(BASE_URL . '/account', 'error', 'Invalid form submission');
    }

    if (!hasMultiBranch()) {
        redirect(BASE_URL . '/', 'error', 'Multi-branch is not enabled for this account.');
    }

    $branchId = intval($_POST['branch_id'] ?? 0);
    $allowed  = array_map('intval', array_column(switchableBranches(), 'id'));

    if (!in_array($branchId, $allowed, true)) {
        redirect(BASE_URL . '/', 'error', 'You are not assigned to that branch.');
    }

    $previousBranchName = activeBranchName();

    $db->query("UPDATE users SET branch_id = ? WHERE id = ?", [$branchId, $_SESSION['user_id']]);
    unset($_SESSION['user_data']);

    $branchName = $db->fetchOne("SELECT name FROM branches WHERE id = ?", [$branchId]);

    logAudit('user.switch_branch', 'user', $_SESSION['user_id'], [
        'from' => $previousBranchName,
        'to'   => $branchName['name'] ?? 'branch',
    ]);

    redirect(BASE_URL . '/', 'success', 'Switched to ' . e($branchName['name'] ?? 'branch') . '.');
}
