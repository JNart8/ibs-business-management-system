<?php

/**
 * User Controller - WITH ACTIVATE FUNCTION
 * Admin-only: manage system users
 */

if (!defined('APP_START')) {
    die('Direct access not permitted');
}

$db = Database::getInstance();

// ── Requires users.manage permission ───────────────────────────
if (!can('users.manage')) {
    redirect(BASE_URL . '/', 'error', 'Access denied. You do not have permission to manage users.');
}

// ── Parse segments ────────────────────────────────────────────
$segments = array_values(array_filter(explode('/', trim($path, '/'))));

// /users              → index
// /users/create       → create
// /users/edit/5       → edit
// /users/delete/5     → deactivate
// /users/activate/5   → activate (NEW!)
// /users/password/5   → change password
// /users/unlock/5     → unlock locked account
$action = $segments[1] ?? 'index';
$id     = $segments[2] ?? null;

switch ($action) {
    case 'index':
        listUsers($db);
        break;
    case 'create':
        $method === 'POST' ? storeUser($db) : showCreateUser($db);
        break;
    case 'edit':
        if (!$id) redirect(BASE_URL . '/users', 'error', 'User ID required');
        $method === 'POST' ? updateUser($db, $id) : showEditUser($db, $id);
        break;
    case 'password':
        if (!$id) redirect(BASE_URL . '/users', 'error', 'User ID required');
        $method === 'POST' ? updatePassword($db, $id) : showPasswordForm($db, $id);
        break;
    case 'delete':
        if (!$id) redirect(BASE_URL . '/users', 'error', 'User ID required');
        deleteUser($db, $id);
        break;
    case 'activate':  // ← NEW ROUTE
        if (!$id) redirect(BASE_URL . '/users', 'error', 'User ID required');
        activateUser($db, $id);
        break;
    case 'unlock':
        if (!$id) redirect(BASE_URL . '/users', 'error', 'User ID required');
        unlockUser($db, $id);
        break;
    default:
        http_response_code(404);
        echo "<h1>Not found</h1><a href='" . BASE_URL . "/users'>← Back</a>";
}

// ============================================================
// FUNCTIONS
// ============================================================

function listUsers($db)
{
    $users = $db->fetchAll("
        SELECT id, username, full_name, role, role_id, branch_scope, branch_id, is_active,
               login_attempts, locked_until, last_login, created_at
        FROM users
        ORDER BY is_active DESC, role ASC, full_name ASC
    ");
    $pageTitle = 'Users';
    include APP_PATH . '/views/users/index.php';
}

function showCreateUser($db)
{
    $roles = assignableRoles();
    $branches = hasMultiBranch()
        ? $db->fetchAll("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name ASC")
        : [];
    $pageTitle = 'Add User';
    include APP_PATH . '/views/users/create.php';
}

function storeUser($db)
{
    $errors = validateUserInput($_POST);

    $exists = $db->fetchOne("SELECT id FROM users WHERE username = ?", [trim($_POST['username'])]);
    if ($exists) $errors[] = 'Username already taken.';

    $activeUserCount = $db->fetchOne("SELECT COUNT(*) as c FROM users WHERE is_active = 1");
    if (!withinUserLimit(intval($activeUserCount['c'] ?? 0))) {
        $errors[] = 'You have reached the ' . currentPlanDefinition()['max_users']
            . '-user limit for your ' . currentPlanDefinition()['label'] . ' plan. Upgrade to add more users.';
    }

    if (!empty($errors)) {
        $_SESSION['old_input'] = $_POST;
        redirect(BASE_URL . '/users/create', 'error', implode(' ', $errors));
    }

    $roleId = intval($_POST['role_id'] ?? 0);
    // Legacy `role` enum only accepts admin/staff/cashier — for a custom Enterprise
    // role that doesn't map to one of those, fall back to 'staff' for the handful of
    // remaining cosmetic reads of this column. `role_id` is the real source of truth.
    $slug = roleSlug($roleId);
    $legacyRole = in_array($slug, ['admin', 'staff', 'cashier'], true) ? $slug : 'staff';

    $branchScope = (hasMultiBranch() && ($_POST['branch_scope'] ?? '') === 'all') ? 'all' : 'assigned';

    $db->query("
        INSERT INTO users (username, password_hash, full_name, role, role_id, branch_scope, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ", [
        trim($_POST['username']),
        password_hash($_POST['password'], PASSWORD_DEFAULT),
        trim($_POST['full_name']),
        $legacyRole,
        $roleId,
        $branchScope,
        isset($_POST['is_active']) ? 1 : 0,
    ]);

    $newUserId = $db->lastInsertId();

    if (hasMultiBranch()) {
        saveUserBranches($db, $newUserId, $_POST['branch_ids'] ?? [], $_POST['primary_branch_id'] ?? null);
    } else {
        // Multi-branch isn't active for this install — everyone implicitly
        // works at Main Branch. Keep the new user consistent with that.
        $mainBranch = $db->fetchOne("SELECT id FROM branches ORDER BY id ASC LIMIT 1");
        if ($mainBranch) {
            saveUserBranches($db, $newUserId, [$mainBranch['id']], $mainBranch['id']);
        }
    }

    logAudit('user.create', 'user', $newUserId, [
        'username' => trim($_POST['username']),
        'role'     => roleName($roleId),
    ]);

    redirect(BASE_URL . '/users', 'success', 'User created successfully.');
}

function showEditUser($db, $id)
{
    $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
    if (!$user) redirect(BASE_URL . '/users', 'error', 'User not found');
    $roles = assignableRoles();
    $branches = hasMultiBranch()
        ? $db->fetchAll("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name ASC")
        : [];
    $assignedBranches = hasMultiBranch() ? userBranches($id) : [];
    $pageTitle = 'Edit User';
    include APP_PATH . '/views/users/edit.php';
}

function updateUser($db, $id)
{
    $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
    if (!$user) redirect(BASE_URL . '/users', 'error', 'User not found');

    $fullName    = trim($_POST['full_name'] ?? '');
    $roleId      = intval($_POST['role_id'] ?? 0);
    $isActive    = isset($_POST['is_active']) ? 1 : 0;
    $newUsername = trim($_POST['username']  ?? '');

    if (empty($fullName)) {
        redirect(BASE_URL . '/users/edit/' . $id, 'error', 'Full name is required.');
    }

    $duplicate = $db->fetchOne(
        "SELECT id FROM users WHERE username = ? AND id != ?",
        [$newUsername, $id]
    );
    if ($duplicate) {
        redirect(BASE_URL . '/users/edit/' . $id, 'error', 'Username already taken.');
    }

    $slug = roleSlug($roleId);
    $legacyRole = in_array($slug, ['admin', 'staff', 'cashier'], true) ? $slug : 'staff';

    if ($user['role'] === 'admin' && $legacyRole !== 'admin') {
        $adminCount = $db->fetchOne("SELECT COUNT(*) as c FROM users WHERE role = 'admin' AND is_active = 1");
        if (intval($adminCount['c']) <= 1) {
            redirect(BASE_URL . '/users/edit/' . $id, 'error', 'Cannot demote the only admin.');
        }
    }

    $branchScope = (hasMultiBranch() && ($_POST['branch_scope'] ?? '') === 'all') ? 'all' : 'assigned';

    $db->query("
        UPDATE users SET username = ?, full_name = ?, role = ?, role_id = ?, branch_scope = ?, is_active = ?
        WHERE id = ?
    ", [$newUsername, $fullName, $legacyRole, $roleId, $branchScope, $isActive, $id]);

    if (hasMultiBranch()) {
        saveUserBranches($db, $id, $_POST['branch_ids'] ?? [], $_POST['primary_branch_id'] ?? null);
    }

    logAudit('user.update', 'user', $id, [
        'username'      => $newUsername,
        'role_before'   => roleName($user['role_id']),
        'role_after'    => roleName($roleId),
        'active_before' => (bool) $user['is_active'],
        'active_after'  => (bool) $isActive,
    ]);

    if ($id == $_SESSION['user_id']) {
        $_SESSION['full_name'] = $fullName;
        $_SESSION['role']      = $legacyRole;
        unset($_SESSION['user_data']);
    }

    redirect(BASE_URL . '/users', 'success', 'User updated successfully.');
}

/**
 * Replace a user's branch assignments with the given list, and set
 * their `branch_id` (the "currently active" branch POS/sales read
 * from, no picker shown) to the chosen primary — falling back to the
 * first assigned branch if no primary was submitted, and to Main
 * Branch if no branches were assigned at all (shouldn't normally
 * happen, since the UI always requires picking at least one).
 */
function saveUserBranches($db, $userId, $branchIds, $primaryBranchId)
{
    $validBranchIds = array_column($db->fetchAll("SELECT id FROM branches WHERE is_active = 1"), 'id');
    $branchIds = array_values(array_intersect(array_map('intval', $branchIds), $validBranchIds));

    if (empty($branchIds)) {
        $fallback = $db->fetchOne("SELECT id FROM branches ORDER BY id ASC LIMIT 1");
        $branchIds = $fallback ? [$fallback['id']] : [];
    }

    $primaryBranchId = intval($primaryBranchId ?? 0);
    if (!in_array($primaryBranchId, $branchIds, true)) {
        $primaryBranchId = $branchIds[0] ?? null;
    }

    $db->query("DELETE FROM user_branches WHERE user_id = ?", [$userId]);
    foreach ($branchIds as $branchId) {
        $db->query(
            "INSERT INTO user_branches (user_id, branch_id, is_primary) VALUES (?, ?, ?)",
            [$userId, $branchId, $branchId === $primaryBranchId ? 1 : 0]
        );
    }

    if ($primaryBranchId) {
        $db->query("UPDATE users SET branch_id = ? WHERE id = ?", [$primaryBranchId, $userId]);
    }
}

function showPasswordForm($db, $id)
{
    $user = $db->fetchOne("SELECT id, username, full_name FROM users WHERE id = ?", [$id]);
    if (!$user) redirect(BASE_URL . '/users', 'error', 'User not found');
    $pageTitle = 'Change Password';
    include APP_PATH . '/views/users/password.php';
}

function updatePassword($db, $id)
{
    $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
    if (!$user) redirect(BASE_URL . '/users', 'error', 'User not found');

    $newPassword     = $_POST['password']         ?? '';
    $confirmPassword = $_POST['password_confirm'] ?? '';

    if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
        redirect(
            BASE_URL . '/users/password/' . $id,
            'error',
            'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.'
        );
    }
    if ($newPassword !== $confirmPassword) {
        redirect(BASE_URL . '/users/password/' . $id, 'error', 'Passwords do not match.');
    }

    $db->query(
        "UPDATE users SET password_hash = ? WHERE id = ?",
        [password_hash($newPassword, PASSWORD_DEFAULT), $id]
    );

    if ($id == $_SESSION['user_id']) {
        redirect(BASE_URL . '/logout', 'success', 'Password changed. Please log in again.');
    }

    redirect(BASE_URL . '/users', 'success', 'Password updated successfully.');
}

function deleteUser($db, $id)
{
    if ($id == $_SESSION['user_id']) {
        redirect(BASE_URL . '/users', 'error', 'You cannot deactivate your own account.');
    }

    $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
    if (!$user) redirect(BASE_URL . '/users', 'error', 'User not found');

    if ($user['role'] === 'admin') {
        $adminCount = $db->fetchOne("SELECT COUNT(*) as c FROM users WHERE role = 'admin' AND is_active = 1");
        if (intval($adminCount['c']) <= 1) {
            redirect(BASE_URL . '/users', 'error', 'Cannot deactivate the only admin account.');
        }
    }

    $db->query("UPDATE users SET is_active = 0 WHERE id = ?", [$id]);
    logAudit('user.deactivate', 'user', $id, ['username' => $user['username']]);
    redirect(BASE_URL . '/users', 'success', 'User deactivated successfully.');
}

/**
 * Activate a deactivated user account (NEW!)
 */
function activateUser($db, $id)
{
    $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
    if (!$user) {
        redirect(BASE_URL . '/users', 'error', 'User not found');
    }

    // Check if already active
    if ($user['is_active'] == 1) {
        redirect(BASE_URL . '/users', 'error', 'User is already active.');
    }

    // Activate the user
    $db->query("UPDATE users SET is_active = 1 WHERE id = ?", [$id]);
    logAudit('user.activate', 'user', $id, ['username' => $user['username']]);

    redirect(
        BASE_URL . '/users',
        'success',
        e($user['full_name']) . '\'s account has been activated.'
    );
}

/**
 * Unlock a locked user account
 */
function unlockUser($db, $id)
{
    $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
    if (!$user) redirect(BASE_URL . '/users', 'error', 'User not found');

    // Guard against unlocking a user that isn't actually locked
    $isLocked = $user['locked_until'] && strtotime($user['locked_until']) > time();
    if (!$isLocked) {
        redirect(BASE_URL . '/users', 'error', 'This account is not currently locked.');
    }

    $db->query(
        "UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = ?",
        [$id]
    );

    redirect(
        BASE_URL . '/users',
        'success',
        e($user['full_name']) . '\'s account has been unlocked.'
    );
}

// ── Validation helper ─────────────────────────────────────────
function validateUserInput($data)
{
    $errors = [];

    if (empty(trim($data['username'] ?? '')))  $errors[] = 'Username is required.';
    if (empty(trim($data['full_name'] ?? ''))) $errors[] = 'Full name is required.';

    if (strlen($data['password'] ?? '') < PASSWORD_MIN_LENGTH) {
        $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    }
    if (($data['password'] ?? '') !== ($data['password_confirm'] ?? '')) {
        $errors[] = 'Passwords do not match.';
    }

    $validRoleIds = array_column(assignableRoles(), 'id');
    if (!in_array(intval($data['role_id'] ?? 0), $validRoleIds, true)) {
        $errors[] = 'Invalid role selected.';
    }

    return $errors;
}
