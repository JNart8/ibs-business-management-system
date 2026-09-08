<?php

/**
 * Stock Transfer Controller
 * One-step (instant) inter-branch transfers — both branches' stock
 * update the moment a transfer is recorded. Gated by the dedicated
 * stock.transfer permission (admin-only by default, deliberately more
 * restrictive than stock.manage) plus hasMultiBranch().
 */

if (!defined('APP_START')) {
    die('Direct access not permitted');
}

if (!can('stock.transfer') || !hasMultiBranch()) {
    redirect(BASE_URL . '/', 'error', 'Access denied. Stock transfers require multi-branch and the appropriate permission.');
}

$db = Database::getInstance();

$segments = array_values(array_filter(explode('/', trim($path, '/'))));
$action = $segments[1] ?? 'index';
$id     = $segments[2] ?? null;

switch ($action) {
    case 'index':
        listTransfers($db);
        break;
    case 'create':
        $method === 'POST' ? storeTransfer($db) : showCreateTransfer($db);
        break;
    case 'view':
        if (!$id) redirect(BASE_URL . '/transfers', 'error', 'Transfer ID required');
        viewTransfer($db, $id);
        break;
    case 'receive':
        if (!$id) redirect(BASE_URL . '/transfers', 'error', 'Transfer ID required');
        $method === 'POST' ? receiveTransfer($db, $id) : showReceiveForm($db, $id);
        break;
    case 'cancel':
        if (!$id) redirect(BASE_URL . '/transfers', 'error', 'Transfer ID required');
        cancelTransfer($db, $id);
        break;
    default:
        http_response_code(404);
        echo "<h1>Not found</h1><a href='" . BASE_URL . "/transfers'>← Back</a>";
}

// ============================================================
// FUNCTIONS
// ============================================================

function listTransfers($db)
{
    $page   = max(1, intval($_GET['page'] ?? 1));
    $limit  = 50;
    $offset = ($page - 1) * $limit;

    // A transfer is visible to a branch-scoped user if it involves one of
    // their branches, either as source or destination — not just
    // branchScopeSql()'s usual single-column check, since a transfer has
    // two branch columns and either one being visible is enough.
    $where  = "WHERE 1=1";
    $params = [];
    if (!isCompanyWide()) {
        $visibleIds = array_column(userBranches($_SESSION['user_id']), 'id');
        if (empty($visibleIds)) {
            $where .= " AND 1=0";
        } else {
            $placeholders = implode(',', array_fill(0, count($visibleIds), '?'));
            $where .= " AND (t.from_branch_id IN ($placeholders) OR t.to_branch_id IN ($placeholders))";
            $params = array_merge($params, $visibleIds, $visibleIds);
        }
    }

    $total      = $db->fetchOne("SELECT COUNT(*) as cnt FROM stock_transfers t $where", $params);
    $totalCount = intval($total['cnt'] ?? 0);
    $totalPages = max(1, ceil($totalCount / $limit));

    $transfers = $db->fetchAll("
        SELECT
            t.*,
            fb.name AS from_branch_name,
            tb.name AS to_branch_name,
            u.full_name AS user_name,
            (SELECT COUNT(*) FROM stock_transfer_items ti WHERE ti.transfer_id = t.id) AS item_count,
            (SELECT COALESCE(SUM(ti.quantity), 0) FROM stock_transfer_items ti WHERE ti.transfer_id = t.id) AS total_quantity
        FROM stock_transfers t
        LEFT JOIN branches fb ON t.from_branch_id = fb.id
        LEFT JOIN branches tb ON t.to_branch_id = tb.id
        LEFT JOIN users u ON t.user_id = u.id
        $where
        ORDER BY t.created_at DESC
        LIMIT ? OFFSET ?
    ", array_merge($params, [$limit, $offset]));

    $pageTitle = 'Stock Transfers';
    include APP_PATH . '/views/transfers/index.php';
}

function showCreateTransfer($db)
{
    // Source branch is restricted to branches the user can actually
    // manage stock at — you shouldn't be able to dispatch stock away
    // from a branch you're not assigned to, even with stock.transfer.
    // Destination can be any active branch — no assignment required to
    // receive stock.
    $fromBranches = isCompanyWide()
        ? $db->fetchAll("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name ASC")
        : $db->fetchAll("
            SELECT b.id, b.name FROM branches b
            INNER JOIN user_branches ub ON ub.branch_id = b.id
            WHERE ub.user_id = ? AND b.is_active = 1
            ORDER BY b.name ASC
        ", [$_SESSION['user_id']]);

    $allBranches = $db->fetchAll("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name ASC");

    if (count($fromBranches) === 0) {
        redirect(BASE_URL . '/', 'error', 'You are not assigned to any branch you can transfer stock from.');
    }

    $pageTitle = 'New Stock Transfer';
    include APP_PATH . '/views/transfers/create.php';
}

function storeTransfer($db)
{
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect(BASE_URL . '/transfers/create', 'error', 'Invalid form submission');
    }

    $fromBranchId = intval($_POST['from_branch_id'] ?? 0);
    $toBranchId   = intval($_POST['to_branch_id'] ?? 0);
    $notes        = trim($_POST['notes'] ?? '');
    $items        = $_POST['items'] ?? []; // [['product_id' => x, 'quantity' => y], ...]
    $userId       = $_SESSION['user_id'] ?? null;

    if ($fromBranchId === $toBranchId || !$fromBranchId || !$toBranchId) {
        redirect(BASE_URL . '/transfers/create', 'error', 'Source and destination branches must be different.');
    }

    // Re-validate the source branch server-side — the form only shows
    // branches the user is assigned to, but don't trust that alone.
    if (!isCompanyWide()) {
        $assignedIds = array_column(userBranches($userId), 'id');
        if (!in_array($fromBranchId, $assignedIds, true)) {
            redirect(BASE_URL . '/transfers/create', 'error', 'You are not assigned to that source branch.');
        }
    }

    $destBranch = $db->fetchOne("SELECT id, name FROM branches WHERE id = ? AND is_active = 1", [$toBranchId]);
    $srcBranch  = $db->fetchOne("SELECT id, name FROM branches WHERE id = ? AND is_active = 1", [$fromBranchId]);
    if (!$destBranch || !$srcBranch) {
        redirect(BASE_URL . '/transfers/create', 'error', 'Invalid branch selected.');
    }

    // Clean and validate items
    $validItems = [];
    foreach ($items as $item) {
        $productId = intval($item['product_id'] ?? 0);
        $qty       = floatval($item['quantity'] ?? 0);
        if ($productId > 0 && $qty > 0) {
            $validItems[] = ['product_id' => $productId, 'quantity' => $qty];
        }
    }

    if (empty($validItems)) {
        redirect(BASE_URL . '/transfers/create', 'error', 'Add at least one product to transfer.');
    }

    // Validate stock availability at the source branch for every item
    // before touching anything.
    foreach ($validItems as $item) {
        $available = getBranchStock($item['product_id'], $fromBranchId);
        if ($item['quantity'] > $available) {
            $p = $db->fetchOne("SELECT name, unit FROM products WHERE id = ?", [$item['product_id']]);
            redirect(BASE_URL . '/transfers/create', 'error',
                "Not enough stock for '{$p['name']}' at {$srcBranch['name']}. Available: {$available} {$p['unit']}.");
        }
    }

    try {
        $db->beginTransaction();

        $transferNumber = generateTransferNumber($db);

        $db->query("
            INSERT INTO stock_transfers (transfer_number, from_branch_id, to_branch_id, user_id, notes, status)
            VALUES (?, ?, ?, ?, ?, 'in_transit')
        ", [$transferNumber, $fromBranchId, $toBranchId, $userId, $notes ?: null]);
        $transferId = $db->lastInsertId();

        foreach ($validItems as $item) {
            $pid = $item['product_id'];
            $qty = $item['quantity'];

            $db->query(
                "INSERT INTO stock_transfer_items (transfer_id, product_id, quantity) VALUES (?, ?, ?)",
                [$transferId, $pid, $qty]
            );

            // Only the source branch is affected at dispatch time — the
            // destination is credited when it confirms receipt (see
            // receiveTransfer()), not automatically here.
            $fromPrev = getBranchStock($pid, $fromBranchId);
            adjustBranchStock($db, $pid, $fromBranchId, -$qty);

            $db->query("
                INSERT INTO stock_movements
                    (product_id, movement_type, quantity, reference_type, reference_id, previous_stock, new_stock, notes, user_id, branch_id)
                VALUES (?, 'out', ?, 'transfer', ?, ?, ?, ?, ?, ?)
            ", [$pid, $qty, $transferId, $fromPrev, $fromPrev - $qty, "Transfer {$transferNumber} to {$destBranch['name']} (awaiting receipt)", $userId, $fromBranchId]);
        }

        $db->commit();

        logAudit('stock.transfer', 'stock_transfer', $transferId, [
            'transfer_number' => $transferNumber,
            'from_branch'     => $srcBranch['name'],
            'to_branch'       => $destBranch['name'],
            'item_count'      => count($validItems),
            'total_quantity'  => array_sum(array_column($validItems, 'quantity')),
        ]);

        redirect(BASE_URL . '/transfers/view/' . $transferId, 'success', "Transfer {$transferNumber} dispatched. It will reach {$destBranch['name']}'s stock once received there.");
    } catch (Exception $e) {
        $db->rollback();
        error_log('Stock transfer error: ' . $e->getMessage());
        redirect(BASE_URL . '/transfers/create', 'error', 'Failed to process transfer.');
    }
}

function viewTransfer($db, $id)
{
    // Same visibility rule as listTransfers(): involves one of the
    // viewer's branches, or company-wide sees everything.
    $transfer = fetchVisibleTransfer($db, $id);

    if (!$transfer) {
        redirect(BASE_URL . '/transfers', 'error', 'Transfer not found.');
    }

    $items = $db->fetchAll("
        SELECT ti.*, p.name AS product_name, p.sku, p.unit
        FROM stock_transfer_items ti
        LEFT JOIN products p ON ti.product_id = p.id
        WHERE ti.transfer_id = ?
        ORDER BY ti.id ASC
    ", [$id]);

    // Receive/cancel are each restricted to the branch that action is
    // physically about — receiving to the destination, cancelling a
    // dispatch to the source (or company-wide, either way).
    $canReceive = $transfer['status'] === 'in_transit' && userCanActOnBranch($transfer['to_branch_id']);
    $canCancel  = $transfer['status'] === 'in_transit' && userCanActOnBranch($transfer['from_branch_id']);

    $pageTitle = 'Transfer: ' . $transfer['transfer_number'];
    include APP_PATH . '/views/transfers/view.php';
}

/**
 * Is the current user allowed to act on a given branch — assigned to
 * it, or company-wide? Shared by receive/cancel's access checks.
 */
function userCanActOnBranch($branchId)
{
    if (isCompanyWide()) {
        return true;
    }
    $assignedIds = array_column(userBranches($_SESSION['user_id']), 'id');
    return in_array((int) $branchId, $assignedIds, true);
}

function showReceiveForm($db, $id)
{
    $transfer = fetchVisibleTransfer($db, $id);
    if (!$transfer) {
        redirect(BASE_URL . '/transfers', 'error', 'Transfer not found.');
    }
    if ($transfer['status'] !== 'in_transit') {
        redirect(BASE_URL . '/transfers/view/' . $id, 'error', 'This transfer is not awaiting receipt.');
    }
    if (!userCanActOnBranch($transfer['to_branch_id'])) {
        redirect(BASE_URL . '/transfers/view/' . $id, 'error', 'You are not assigned to the destination branch for this transfer.');
    }

    $items = $db->fetchAll("
        SELECT ti.*, p.name AS product_name, p.sku, p.unit
        FROM stock_transfer_items ti
        LEFT JOIN products p ON ti.product_id = p.id
        WHERE ti.transfer_id = ?
        ORDER BY ti.id ASC
    ", [$id]);

    $pageTitle = 'Receive Transfer: ' . $transfer['transfer_number'];
    include APP_PATH . '/views/transfers/receive.php';
}

function receiveTransfer($db, $id)
{
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect(BASE_URL . '/transfers/receive/' . $id, 'error', 'Invalid form submission');
    }

    $transfer = fetchVisibleTransfer($db, $id);
    if (!$transfer) {
        redirect(BASE_URL . '/transfers', 'error', 'Transfer not found.');
    }
    if ($transfer['status'] !== 'in_transit') {
        redirect(BASE_URL . '/transfers/view/' . $id, 'error', 'This transfer is not awaiting receipt.');
    }
    if (!userCanActOnBranch($transfer['to_branch_id'])) {
        redirect(BASE_URL . '/transfers/view/' . $id, 'error', 'You are not assigned to the destination branch for this transfer.');
    }

    $items = $db->fetchAll("SELECT * FROM stock_transfer_items WHERE transfer_id = ?", [$id]);
    $received = $_POST['received'] ?? []; // [item_id => quantity_received]
    $userId   = $_SESSION['user_id'] ?? null;

    // Validate every item's received quantity before touching anything.
    $quantities = [];
    foreach ($items as $item) {
        $qtyReceived = floatval($received[$item['id']] ?? $item['quantity']);
        if ($qtyReceived < 0 || $qtyReceived > (float) $item['quantity']) {
            redirect(BASE_URL . '/transfers/receive/' . $id, 'error', 'Received quantity must be between 0 and the quantity dispatched.');
        }
        $quantities[$item['id']] = $qtyReceived;
    }

    $toBranchId = $transfer['to_branch_id'];
    $anyShortfall = false;

    try {
        $db->beginTransaction();

        foreach ($items as $item) {
            $pid         = $item['product_id'];
            $qtyReceived = $quantities[$item['id']];

            if ($qtyReceived > 0) {
                $toPrev = getBranchStock($pid, $toBranchId);
                adjustBranchStock($db, $pid, $toBranchId, $qtyReceived);

                $db->query("
                    INSERT INTO stock_movements
                        (product_id, movement_type, quantity, reference_type, reference_id, previous_stock, new_stock, notes, user_id, branch_id)
                    VALUES (?, 'in', ?, 'transfer', ?, ?, ?, ?, ?, ?)
                ", [$pid, $qtyReceived, $id, $toPrev, $toPrev + $qtyReceived, "Transfer {$transfer['transfer_number']} received", $userId, $toBranchId]);
            }

            if ($qtyReceived < (float) $item['quantity']) {
                $anyShortfall = true;
            }

            $db->query("UPDATE stock_transfer_items SET quantity_received = ? WHERE id = ?", [$qtyReceived, $item['id']]);
        }

        $db->query("
            UPDATE stock_transfers SET status = 'completed', received_at = NOW(), received_by = ? WHERE id = ?
        ", [$userId, $id]);

        $db->commit();

        logAudit('stock_transfer.receive', 'stock_transfer', $id, [
            'transfer_number' => $transfer['transfer_number'],
            'to_branch'       => $transfer['to_branch_name'] ?? null,
            'item_count'      => count($items),
            'has_shortfall'   => $anyShortfall,
        ]);

        redirect(BASE_URL . '/transfers/view/' . $id, 'success', $anyShortfall
            ? "Transfer received with a shortfall on one or more items — check the details."
            : "Transfer received in full.");
    } catch (Exception $e) {
        $db->rollback();
        error_log('Stock transfer receive error: ' . $e->getMessage());
        redirect(BASE_URL . '/transfers/receive/' . $id, 'error', 'Failed to record receipt.');
    }
}

function cancelTransfer($db, $id)
{
    $transfer = fetchVisibleTransfer($db, $id);
    if (!$transfer) {
        redirect(BASE_URL . '/transfers', 'error', 'Transfer not found.');
    }
    if ($transfer['status'] !== 'in_transit') {
        redirect(BASE_URL . '/transfers/view/' . $id, 'error', 'Only a transfer still in transit can be cancelled.');
    }
    if (!userCanActOnBranch($transfer['from_branch_id'])) {
        redirect(BASE_URL . '/transfers/view/' . $id, 'error', 'You are not assigned to the source branch for this transfer.');
    }

    $items  = $db->fetchAll("SELECT * FROM stock_transfer_items WHERE transfer_id = ?", [$id]);
    $userId = $_SESSION['user_id'] ?? null;
    $fromBranchId = $transfer['from_branch_id'];

    try {
        $db->beginTransaction();

        // Dispatched stock has been sitting untouched "in transit" (never
        // credited to the destination), so returning it to the source
        // can never go negative — no availability pre-check needed here,
        // unlike a post-receipt reversal would require.
        foreach ($items as $item) {
            $pid = $item['product_id'];
            $qty = (float) $item['quantity'];

            $fromPrev = getBranchStock($pid, $fromBranchId);
            adjustBranchStock($db, $pid, $fromBranchId, $qty);

            $db->query("
                INSERT INTO stock_movements
                    (product_id, movement_type, quantity, reference_type, reference_id, previous_stock, new_stock, notes, user_id, branch_id)
                VALUES (?, 'in', ?, 'transfer', ?, ?, ?, ?, ?, ?)
            ", [$pid, $qty, $id, $fromPrev, $fromPrev + $qty, "Transfer {$transfer['transfer_number']} cancelled", $userId, $fromBranchId]);
        }

        $db->query("UPDATE stock_transfers SET status = 'cancelled' WHERE id = ?", [$id]);

        $db->commit();

        logAudit('stock_transfer.cancel', 'stock_transfer', $id, [
            'transfer_number' => $transfer['transfer_number'],
            'from_branch'     => $transfer['from_branch_name'] ?? null,
            'to_branch'       => $transfer['to_branch_name'] ?? null,
            'item_count'      => count($items),
        ]);

        redirect(BASE_URL . '/transfers/view/' . $id, 'success', "Transfer {$transfer['transfer_number']} cancelled. Stock returned to " . ($transfer['from_branch_name'] ?? 'the source branch') . ".");
    } catch (Exception $e) {
        $db->rollback();
        error_log('Stock transfer cancel error: ' . $e->getMessage());
        redirect(BASE_URL . '/transfers/view/' . $id, 'error', 'Failed to cancel transfer.');
    }
}

/**
 * A transfer by id, respecting the same two-sided branch visibility
 * rule as viewTransfer()/listTransfers() — shared by receive/cancel.
 */
function fetchVisibleTransfer($db, $id)
{
    $where  = "WHERE t.id = ?";
    $params = [$id];
    if (!isCompanyWide()) {
        $visibleIds = array_column(userBranches($_SESSION['user_id']), 'id');
        if (empty($visibleIds)) {
            $where .= " AND 1=0";
        } else {
            $placeholders = implode(',', array_fill(0, count($visibleIds), '?'));
            $where .= " AND (t.from_branch_id IN ($placeholders) OR t.to_branch_id IN ($placeholders))";
            $params = array_merge($params, $visibleIds, $visibleIds);
        }
    }

    return $db->fetchOne("
        SELECT t.*, fb.name AS from_branch_name, tb.name AS to_branch_name,
               u.full_name AS user_name, rb.full_name AS received_by_name
        FROM stock_transfers t
        LEFT JOIN branches fb ON t.from_branch_id = fb.id
        LEFT JOIN branches tb ON t.to_branch_id = tb.id
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN users rb ON t.received_by = rb.id
        $where
    ", $params);
}

function generateTransferNumber($db)
{
    $prefix = 'TRF-' . date('Ymd') . '-';
    $last = $db->fetchOne(
        "SELECT transfer_number FROM stock_transfers WHERE transfer_number LIKE ? ORDER BY id DESC LIMIT 1",
        [$prefix . '%']
    );
    $seq = 1;
    if ($last) {
        $lastSeq = intval(substr($last['transfer_number'], strlen($prefix)));
        $seq = $lastSeq + 1;
    }
    return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
}
