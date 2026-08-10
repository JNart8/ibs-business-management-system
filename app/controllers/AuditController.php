<?php

/**
 * Audit Log Controller
 * Read-only. Branch-scoped the same way Sales/Purchases history is —
 * a branch-level admin sees only entries tied to their branch(es);
 * system-wide entries (branch_id NULL — role/permission changes,
 * anything not tied to one location) are only visible to company-wide
 * viewers, since NULL never matches an IN(...) list in SQL, so this
 * falls out of branchScopeSql() automatically with no extra logic.
 */

if (!defined('APP_START')) {
    die('Direct access not permitted');
}

if (!can('audit.view')) {
    redirect(BASE_URL . '/', 'error', 'Access denied. You do not have permission to view the audit log.');
}

$db = Database::getInstance();
listAuditLog($db);

function listAuditLog($db)
{
    $page   = max(1, intval($_GET['page'] ?? 1));
    $limit  = 50;
    $offset = ($page - 1) * $limit;

    $userId          = $_GET['user_id'] ?? '';
    $action          = $_GET['action'] ?? '';
    $entityType      = $_GET['entity_type'] ?? '';
    $dateFrom        = $_GET['date_from'] ?? '';
    $dateTo          = $_GET['date_to'] ?? '';
    $includeArchived = isset($_GET['include_archived']);

    // Archived entries (1+ years old, moved by cron/archive_audit_log.php)
    // live in a separate table. Most of the time we only want the live
    // table — it's smaller and faster — but a UNION lets someone look
    // back further when they actually need to.
    $source = $includeArchived
        ? "FROM (
              SELECT id, user_id, username, action, entity_type, entity_id, branch_id, details, ip_address, created_at FROM audit_log
              UNION ALL
              SELECT id, user_id, username, action, entity_type, entity_id, branch_id, details, ip_address, created_at FROM audit_log_archive
           ) combined"
        : "FROM audit_log";

    $where  = "WHERE 1=1";
    $params = [];

    [$scopeSql, $scopeParams] = branchScopeSql('');
    $where .= $scopeSql;
    $params = array_merge($params, $scopeParams);

    if (!empty($userId)) {
        $where .= " AND user_id = ?";
        $params[] = $userId;
    }
    if (!empty($action)) {
        $where .= " AND action = ?";
        $params[] = $action;
    }
    if (!empty($entityType)) {
        $where .= " AND entity_type = ?";
        $params[] = $entityType;
    }
    if (!empty($dateFrom)) {
        $where .= " AND DATE(created_at) >= ?";
        $params[] = $dateFrom;
    }
    if (!empty($dateTo)) {
        $where .= " AND DATE(created_at) <= ?";
        $params[] = $dateTo;
    }

    $total      = $db->fetchOne("SELECT COUNT(*) AS cnt $source $where", $params);
    $totalCount = intval($total['cnt'] ?? 0);
    $totalPages = max(1, ceil($totalCount / $limit));

    $entries = $db->fetchAll("
        SELECT *
        $source
        $where
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ", array_merge($params, [$limit, $offset]));

    // For filter dropdowns — deliberately based on the live table only,
    // to keep the common case's dropdown options relevant; an archived-only
    // action/entity value can still be filtered by editing the URL, it just
    // won't appear as a dropdown option.
    [$distinctScopeSql, $distinctScopeParams] = branchScopeSql('');
    $distinctActions = $db->fetchAll("SELECT DISTINCT action FROM audit_log WHERE 1=1 $distinctScopeSql ORDER BY action ASC", $distinctScopeParams);
    $distinctEntities = $db->fetchAll("SELECT DISTINCT entity_type FROM audit_log WHERE entity_type IS NOT NULL $distinctScopeSql ORDER BY entity_type ASC", $distinctScopeParams);
    $users = $db->fetchAll("SELECT id, full_name FROM users ORDER BY full_name ASC");

    $exportUrl = BASE_URL . '/export/audit-log?' . http_build_query(array_filter([
        'user_id'          => $userId,
        'action'           => $action,
        'entity_type'      => $entityType,
        'date_from'        => $dateFrom,
        'date_to'          => $dateTo,
        'include_archived' => $includeArchived ? '1' : '',
    ]));

    $pageTitle = 'Audit Log';
    include APP_PATH . '/views/audit/index.php';
}
