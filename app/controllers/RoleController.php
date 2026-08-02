<?php

/**
 * Role Controller
 * Enterprise-only: create custom roles, edit permission sets for
 * any role (including the built-in Admin/Staff/Cashier system roles).
 *
 * Gated twice, deliberately (matches UserController/SettingsController's
 * pattern of an internal check on top of the router's own gate):
 *   - can('roles.manage')            — only admin-level users by default
 *   - planAllows('advanced_permissions') — Enterprise plan only
 */

if (!defined('APP_START')) {
    die('Direct access not permitted');
}

if (!can('roles.manage') || !planAllows('advanced_permissions')) {
    redirect(BASE_URL . '/', 'error', 'Access denied. Role management requires an Enterprise plan and admin permissions.');
}

$db = Database::getInstance();

$segments = array_values(array_filter(explode('/', trim($path, '/'))));

// /roles              → index
// /roles/create       → create
// /roles/edit/5       → edit
// /roles/delete/5     → delete (custom roles only, and only if unassigned)
$action = $segments[1] ?? 'index';
$id     = $segments[2] ?? null;

switch ($action) {
    case 'index':
        listRoles($db);
        break;
    case 'create':
        $method === 'POST' ? storeRole($db) : showCreateRole($db);
        break;
    case 'edit':
        if (!$id) redirect(BASE_URL . '/roles', 'error', 'Role ID required');
        $method === 'POST' ? updateRole($db, $id) : showEditRole($db, $id);
        break;
    case 'delete':
        if (!$id) redirect(BASE_URL . '/roles', 'error', 'Role ID required');
        deleteRole($db, $id);
        break;
    default:
        http_response_code(404);
        echo "<h1>Not found</h1><a href='" . BASE_URL . "/roles'>← Back</a>";
}

// ============================================================
// FUNCTIONS
// ============================================================

function listRoles($db)
{
    $roles = $db->fetchAll("
        SELECT r.*,
               (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) as permission_count,
               (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id AND u.is_active = 1) as user_count
        FROM roles r
        ORDER BY r.is_system DESC, r.name ASC
    ");
    $pageTitle = 'Roles & Permissions';
    include APP_PATH . '/views/roles/index.php';
}

function permissionCatalog($db)
{
    $rows = $db->fetchAll("SELECT `key`, label, category FROM permissions ORDER BY category, label");
    $grouped = [];
    foreach ($rows as $row) {
        $grouped[$row['category']][] = $row;
    }
    return $grouped;
}

function showCreateRole($db)
{
    $catalog = permissionCatalog($db);
    $checked = []; // nothing pre-checked for a brand new role
    $pageTitle = 'Create Role';
    include APP_PATH . '/views/roles/create.php';
}

function storeRole($db)
{
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect(BASE_URL . '/roles/create', 'error', 'Invalid form submission');
    }

    $name = trim($_POST['name'] ?? '');
    if (empty($name)) {
        redirect(BASE_URL . '/roles/create', 'error', 'Role name is required.');
    }

    $slug = roleSlugFromName($db, $name);

    $db->query("INSERT INTO roles (name, slug, is_system) VALUES (?, ?, 0)", [$name, $slug]);
    $roleId = $db->lastInsertId();

    $permissionKeys = $_POST['permissions'] ?? [];
    saveRolePermissions($db, $roleId, $permissionKeys);

    redirect(BASE_URL . '/roles', 'success', 'Role "' . e($name) . '" created.');
}

function showEditRole($db, $id)
{
    $role = $db->fetchOne("SELECT * FROM roles WHERE id = ?", [$id]);
    if (!$role) redirect(BASE_URL . '/roles', 'error', 'Role not found.');

    $catalog = permissionCatalog($db);
    $currentPermissions = $db->fetchAll("SELECT permission_key FROM role_permissions WHERE role_id = ?", [$id]);
    $checked = array_column($currentPermissions, 'permission_key');

    $pageTitle = 'Edit Role: ' . $role['name'];
    include APP_PATH . '/views/roles/edit.php';
}

function updateRole($db, $id)
{
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect(BASE_URL . '/roles/edit/' . $id, 'error', 'Invalid form submission');
    }

    $role = $db->fetchOne("SELECT * FROM roles WHERE id = ?", [$id]);
    if (!$role) redirect(BASE_URL . '/roles', 'error', 'Role not found.');

    // Name/slug are locked for system roles (admin/staff/cashier) — renaming
    // these could confuse the handful of legacy `role` enum reads that still
    // exist for cosmetic purposes. Custom roles can be renamed freely.
    if (!$role['is_system']) {
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            redirect(BASE_URL . '/roles/edit/' . $id, 'error', 'Role name is required.');
        }
        $db->query("UPDATE roles SET name = ? WHERE id = ?", [$name, $id]);
    }

    $permissionKeys = $_POST['permissions'] ?? [];

    // Guardrail: the built-in Admin role can never lose the two permissions
    // needed to manage users/roles, or an admin could lock every admin out
    // of the Users and Roles screens with no way back in except a direct
    // database edit. Force them back on for this specific role, regardless
    // of what was submitted.
    if ($role['slug'] === 'admin') {
        $permissionKeys = array_unique(array_merge($permissionKeys, ['users.manage', 'roles.manage']));
    }

    saveRolePermissions($db, $id, $permissionKeys);

    redirect(BASE_URL . '/roles', 'success', 'Role "' . e($role['name']) . '" updated.');
}

function deleteRole($db, $id)
{
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect(BASE_URL . '/roles', 'error', 'Invalid form submission');
    }

    $role = $db->fetchOne("SELECT * FROM roles WHERE id = ?", [$id]);
    if (!$role) redirect(BASE_URL . '/roles', 'error', 'Role not found.');

    if ($role['is_system']) {
        redirect(BASE_URL . '/roles', 'error', 'Built-in roles (Admin/Staff/Cashier) cannot be deleted.');
    }

    $inUse = $db->fetchOne("SELECT COUNT(*) as c FROM users WHERE role_id = ?", [$id]);
    if (intval($inUse['c'] ?? 0) > 0) {
        redirect(BASE_URL . '/roles', 'error', 'Cannot delete "' . e($role['name']) . '" — it is still assigned to ' . intval($inUse['c']) . ' user(s). Reassign them first.');
    }

    $db->query("DELETE FROM roles WHERE id = ?", [$id]);
    redirect(BASE_URL . '/roles', 'success', 'Role "' . e($role['name']) . '" deleted.');
}

/**
 * Replace a role's entire permission set with the given list of keys.
 * Only accepts keys that actually exist in the permission catalog,
 * so a tampered form submission can't grant an unknown permission.
 */
function saveRolePermissions($db, $roleId, $permissionKeys)
{
    $validKeys = array_column($db->fetchAll("SELECT `key` FROM permissions"), 'key');
    $permissionKeys = array_values(array_intersect($permissionKeys, $validKeys));

    $db->query("DELETE FROM role_permissions WHERE role_id = ?", [$roleId]);
    foreach ($permissionKeys as $key) {
        $db->query("INSERT INTO role_permissions (role_id, permission_key) VALUES (?, ?)", [$roleId, $key]);
    }
}

/**
 * Generate a unique slug for a new custom role from its display name.
 */
function roleSlugFromName($db, $name)
{
    $base = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
    if ($base === '') $base = 'role';

    $slug = $base;
    $i = 2;
    while ($db->fetchOne("SELECT id FROM roles WHERE slug = ?", [$slug])) {
        $slug = $base . '-' . $i;
        $i++;
    }
    return $slug;
}
