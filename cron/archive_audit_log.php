<?php

/**
 * Archive audit_log entries older than the retention period into
 * audit_log_archive, then remove them from the live table.
 *
 * Run this on a schedule (daily is plenty — it's cheap and idempotent
 * either way) via cPanel → Cron Jobs, as a command, NOT a URL:
 *
 *   php /home/YOURUSER/path/to/ibs-sales-app-v2/cron/archive_audit_log.php
 *
 * This does NOT require SSH/Terminal access — cPanel's Cron Jobs
 * feature runs the command directly; SSH is a separate, unrelated
 * permission. If your specific host's cron only supports "visit a
 * URL" rather than running a command, this script would need a small
 * adaptation (a shared-secret query parameter check) — ask if that
 * turns out to be the case.
 *
 * Safe to run repeatedly: already-archived rows are simply skipped
 * (INSERT IGNORE), and nothing here can double-move a row.
 */

// This script runs standalone via CLI — it does NOT go through
// public/index.php, so there's no session, no routing, no logged-in
// user. It only needs the database connection.
define('APP_START', true);
define('APP_PATH', __DIR__ . '/../app');

require APP_PATH . '/config/database.php';

const RETENTION_DAYS = 365;

$db = Database::getInstance();

echo "[" . date('Y-m-d H:i:s') . "] Starting audit log archive (retention: " . RETENTION_DAYS . " days)\n";

try {
    $cutoff = date('Y-m-d H:i:s', strtotime('-' . RETENTION_DAYS . ' days'));

    // How many are we about to move? (for the log output — not load-bearing)
    $countRow = $db->fetchOne(
        "SELECT COUNT(*) AS cnt FROM audit_log WHERE created_at < ?",
        [$cutoff]
    );
    $toArchive = intval($countRow['cnt'] ?? 0);

    if ($toArchive === 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Nothing older than $cutoff. Nothing to do.\n";
        exit(0);
    }

    echo "[" . date('Y-m-d H:i:s') . "] Archiving $toArchive entries older than $cutoff...\n";

    // Copy first (INSERT IGNORE so a re-run can't double-archive),
    // only delete the ones actually copied.
    $db->query("
        INSERT IGNORE INTO audit_log_archive
            (id, user_id, username, action, entity_type, entity_id, branch_id, details, ip_address, created_at)
        SELECT id, user_id, username, action, entity_type, entity_id, branch_id, details, ip_address, created_at
        FROM audit_log
        WHERE created_at < ?
    ", [$cutoff]);

    $db->query("DELETE FROM audit_log WHERE created_at < ?", [$cutoff]);

    echo "[" . date('Y-m-d H:i:s') . "] Done. $toArchive entries archived and removed from the live table.\n";
} catch (Exception $e) {
    // Non-zero exit so cPanel's cron failure notifications (if configured) fire.
    fwrite(STDERR, "[" . date('Y-m-d H:i:s') . "] Archive failed: " . $e->getMessage() . "\n");
    exit(1);
}
