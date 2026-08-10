<?php

/**
 * Branch Controller
 * Gated twice, matching RoleController's pattern:
 *   - can('branches.manage')  — admin-level users by default
 *   - hasMultiBranch()        — Enterprise, or Growth + the add-on flag
 *
 * No delete action, deliberately — same reasoning as accounts and
 * roles: sales/purchases/stock_movements/expenses/accounts/users all
 * reference branch_id, so removing a branch outright would orphan
 * history. Branches are deactivated (is_active = 0), not deleted, and
 * the last active branch can't be deactivated.
 */

if (!defined('APP_START')) {
    die('Direct access not permitted');
}

if (!can('branches.manage') || !hasMultiBranch()) {
    redirect(BASE_URL . '/', 'error', 'Access denied. Branch management requires multi-branch to be enabled for this account.');
}

$db = Database::getInstance();

$segments = array_values(array_filter(explode('/', trim($path, '/'))));
$action = $segments[1] ?? 'index';
$id     = $segments[2] ?? null;

switch ($action) {
    case 'index':
        listBranches($db);
        break;
    case 'create':
        $method === 'POST' ? storeBranch($db) : showCreateBranch($db);
        break;
    case 'edit':
        if (!$id) redirect(BASE_URL . '/branches', 'error', 'Branch ID required');
        $method === 'POST' ? updateBranch($db, $id) : showEditBranch($db, $id);
        break;
    default:
        http_response_code(404);
        echo "<h1>Not found</h1><a href='" . BASE_URL . "/branches'>← Back</a>";
}

// ============================================================
// FUNCTIONS
// ============================================================

function listBranches($db)
{
    $branches = $db->fetchAll("
        SELECT b.*,
               (SELECT COUNT(*) FROM user_branches ub WHERE ub.branch_id = b.id) as user_count
        FROM branches b
        ORDER BY b.is_active DESC, b.name ASC
    ");
    $pageTitle = 'Branches';
    include APP_PATH . '/views/branches/index.php';
}

function showCreateBranch($db)
{
    $pageTitle = 'Add Branch';
    include APP_PATH . '/views/branches/create.php';
}

function storeBranch($db)
{
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect(BASE_URL . '/branches/create', 'error', 'Invalid form submission');
    }

    $name = trim($_POST['name'] ?? '');
    if (empty($name)) {
        redirect(BASE_URL . '/branches/create', 'error', 'Branch name is required.');
    }

    $db->query(
        "INSERT INTO branches (name, address, phone, is_active) VALUES (?, ?, ?, 1)",
        [$name, trim($_POST['address'] ?? '') ?: null, trim($_POST['phone'] ?? '') ?: null]
    );
    $newBranchId = $db->lastInsertId();

    logAudit('branch.create', 'branch', $newBranchId, ['name' => $name]);

    redirect(BASE_URL . '/branches', 'success', 'Branch "' . e($name) . '" created.');
}

function showEditBranch($db, $id)
{
    $branch = $db->fetchOne("SELECT * FROM branches WHERE id = ?", [$id]);
    if (!$branch) redirect(BASE_URL . '/branches', 'error', 'Branch not found.');

    $pageTitle = 'Edit Branch: ' . $branch['name'];
    include APP_PATH . '/views/branches/edit.php';
}

function updateBranch($db, $id)
{
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect(BASE_URL . '/branches/edit/' . $id, 'error', 'Invalid form submission');
    }

    $branch = $db->fetchOne("SELECT * FROM branches WHERE id = ?", [$id]);
    if (!$branch) redirect(BASE_URL . '/branches', 'error', 'Branch not found.');

    $name = trim($_POST['name'] ?? '');
    if (empty($name)) {
        redirect(BASE_URL . '/branches/edit/' . $id, 'error', 'Branch name is required.');
    }

    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($isActive === 0 && $branch['is_active'] == 1) {
        $otherActive = $db->fetchOne("SELECT COUNT(*) as c FROM branches WHERE is_active = 1 AND id != ?", [$id]);
        if (intval($otherActive['c'] ?? 0) === 0) {
            redirect(BASE_URL . '/branches/edit/' . $id, 'error',
                'You cannot deactivate the last active branch — sales and purchases need at least one to post to.');
        }
    }

    $db->query(
        "UPDATE branches SET name = ?, address = ?, phone = ?, is_active = ? WHERE id = ?",
        [$name, trim($_POST['address'] ?? '') ?: null, trim($_POST['phone'] ?? '') ?: null, $isActive, $id]
    );

    logAudit('branch.update', 'branch', $id, [
        'name'          => $name,
        'active_before' => (bool) $branch['is_active'],
        'active_after'  => (bool) $isActive,
    ]);

    redirect(BASE_URL . '/branches', 'success', 'Branch "' . e($name) . '" updated.');
}
