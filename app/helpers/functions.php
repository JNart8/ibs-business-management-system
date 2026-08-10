<?php

/**
 * General Helper Functions
 */

/**
 * Redirect to another page with optional flash message
 */
function redirect($url, $type = null, $message = null)
{
    if ($type && $message) {
        $_SESSION['flash_type'] = $type; // 'success', 'error', 'warning', 'info'
        $_SESSION['flash_message'] = $message;
    }

    header('Location: ' . $url);
    exit;
}

/**
 * Display flash message and clear it
 */
function flashMessage()
{
    if (isset($_SESSION['flash_message'])) {
        $type = $_SESSION['flash_type'] ?? 'info';
        $message = $_SESSION['flash_message'];

        // Clear the flash message
        unset($_SESSION['flash_type'], $_SESSION['flash_message']);

        // Color coding
        $colors = [
            'success' => 'green',
            'error' => 'red',
            'warning' => 'yellow',
            'info' => 'blue'
        ];

        $color = $colors[$type] ?? 'blue';

        return "
            <div class='bg-{$color}-100 border border-{$color}-400 text-{$color}-700 px-4 py-3 rounded mb-4' role='alert'>
                <span class='block sm:inline'>{$message}</span>
            </div>
        ";
    }

    return '';
}

/**
 * Format currency (NULL-safe for PHP 8+)
 */
function formatMoney($amount)
{
    // Handle NULL and empty values
    $amount = $amount ?? 0;
    $amount = floatval($amount);

    if (CURRENCY_FORMAT === 'before') {
        return CURRENCY_HOLDER . ' ' . number_format($amount, 2);
    } else {
        return number_format($amount, 2) . ' ' . CURRENCY_HOLDER;
    }
}

/**
 * Format number safely (NULL-safe for PHP 8+)
 */
function formatNumber($number, $decimals = 0)
{
    $number = $number ?? 0;
    return number_format(floatval($number), $decimals);
}

/**
 * Safe integer conversion
 */
function safeInt($value)
{
    return intval($value ?? 0);
}

/**
 * Safe float conversion
 */
function safeFloat($value)
{
    return floatval($value ?? 0);
}

/**
 * Escape HTML output (prevent XSS attacks)
 */
function e($string)
{
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Get old input value (for form validation errors)
 */
function old($field, $default = '')
{
    if (isset($_SESSION['old_input'][$field])) {
        $value = $_SESSION['old_input'][$field];
        return $value;
    }
    return $default;
}

/**
 * Clear old input after displaying form
 */
function clearOldInput()
{
    unset($_SESSION['old_input']);
}

/**
 * Generate CSRF token field for forms
 */
function csrfField()
{
    return "<input type='hidden' name='csrf_token' value='" . ($_SESSION['csrf_token'] ?? '') . "'>";
}

/**
 * Check if user is logged in
 */
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

/**
 * Get current user data
 */
function currentUser()
{
    if (!isLoggedIn()) {
        return null;
    }

    // Cache user data in session to avoid repeated DB queries
    if (!isset($_SESSION['user_data'])) {
        $db = Database::getInstance();
        $_SESSION['user_data'] = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    }

    return $_SESSION['user_data'];
}

/**
 * All permission keys granted to the current user's role, via
 * users.role_id → role_permissions. Cached per-request.
 * Returns an empty array for a user with no role_id assigned
 * (shouldn't happen post-migration, but fails closed, not open).
 */
function currentUserPermissions()
{
    static $permissions = null;
    if ($permissions === null) {
        $user = currentUser();
        $roleId = $user['role_id'] ?? null;
        if (!$roleId) {
            $permissions = [];
        } else {
            $db   = Database::getInstance();
            $rows = $db->fetchAll("SELECT permission_key FROM role_permissions WHERE role_id = ?", [$roleId]);
            $permissions = array_column($rows, 'permission_key');
        }
    }
    return $permissions;
}

/**
 * Does the current user have at least one "back office" management
 * permission? Used to show/hide whole nav sections (Dashboard,
 * Inventory, People, Finance, Reports dropdowns) as a group, the
 * same way the old admin/staff-only check did — but based on actual
 * permissions, so a custom Enterprise role with even one of these
 * granted will see the nav area (individual sub-items inside are
 * still protected item-by-item at the router regardless of what
 * the nav shows).
 */
function canManageAnything()
{
    $modulePermissions = [
        'products.manage', 'categories.manage', 'stock.manage', 'suppliers.manage',
        'purchases.manage', 'customers.manage', 'transactions.manage',
        'financial_accounts.access', 'expenses.manage', 'distributor.manage', 'suspense.manage',
    ];
    foreach ($modulePermissions as $permission) {
        if (can($permission)) return true;
    }
    return false;
}

/**
 * Does the current user have this permission?
 * Usage: if (can('products.manage')) { ... }
 */
function can($permission)
{
    return in_array($permission, currentUserPermissions(), true);
}

/**
 * Slug of a role by id (admin/staff/cashier/custom-slug), cached
 * per-request per role_id since it's looked up repeatedly (e.g.
 * once per user row on the Users list).
 */
function roleSlug($roleId)
{
    static $cache = [];
    if (!$roleId) return null;
    if (!array_key_exists($roleId, $cache)) {
        $db  = Database::getInstance();
        $row = $db->fetchOne("SELECT slug FROM roles WHERE id = ?", [$roleId]);
        $cache[$roleId] = $row['slug'] ?? null;
    }
    return $cache[$roleId];
}

/**
 * Display name of a role by id (e.g. for the Users list / badges).
 */
function roleName($roleId)
{
    static $cache = [];
    if (!$roleId) return 'Unknown';
    if (!array_key_exists($roleId, $cache)) {
        $db  = Database::getInstance();
        $row = $db->fetchOne("SELECT name FROM roles WHERE id = ?", [$roleId]);
        $cache[$roleId] = $row['name'] ?? 'Unknown';
    }
    return $cache[$roleId];
}

/**
 * All roles a user can be assigned to. Core/Growth only ever see
 * the 3 system roles (matches today's fixed dropdown); Enterprise
 * (planAllows('advanced_permissions')) also sees any custom roles
 * that have been created.
 */
function assignableRoles()
{
    $db = Database::getInstance();
    if (planAllows('advanced_permissions')) {
        return $db->fetchAll("SELECT id, name, slug, is_system FROM roles ORDER BY is_system DESC, name ASC");
    }
    return $db->fetchAll("SELECT id, name, slug, is_system FROM roles WHERE is_system = 1 ORDER BY id ASC");
}

/**
 * Load the plan tier definitions (app/config/plans.php).
 * Cached in a static var so the file is only read once per request.
 */
function planDefinitions()
{
    static $plans = null;
    if ($plans === null) {
        $plans = require APP_PATH . '/config/plans.php';
    }
    return $plans;
}

/**
 * Get the plan slug for this installation (core/growth/enterprise).
 * Cached per-request only (not session) so that flipping the plan
 * in the database (e.g. via phpMyAdmin) takes effect on the very
 * next page load, with no need to log out/in.
 */
function currentPlan()
{
    static $plan = null;
    if ($plan === null) {
        $db  = Database::getInstance();
        $row = $db->fetchOne("SELECT plan FROM settings ORDER BY id ASC LIMIT 1");
        $plan = $row['plan'] ?? 'core';
    }
    return $plan;
}

/**
 * Get the full definition array (label, limits, features) for the
 * installation's current plan.
 */
function currentPlanDefinition()
{
    $plans = planDefinitions();
    return $plans[currentPlan()] ?? $plans['core'];
}

/**
 * Does the current plan include this feature?
 * Usage: if (planAllows('advanced_reports')) { ... }
 */
function planAllows($feature)
{
    $definition = currentPlanDefinition();
    return in_array($feature, $definition['features'] ?? [], true);
}

/**
 * Is there room for one more active user under the current plan?
 * Pass the count of currently active users (see UserController).
 */
function withinUserLimit($activeUserCount)
{
    return (int)$activeUserCount < currentPlanDefinition()['max_users'];
}

/**
 * Render a small "Upgrade your plan" notice, styled like the app's
 * existing flash messages, for use on blocked pages/sections.
 */
function planUpgradeNotice($featureLabel = 'This feature')
{
    $planLabel = currentPlanDefinition()['label'];
    return "
        <div class='bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded mb-4' role='alert'>
            <span class='block sm:inline'>{$featureLabel} isn't included in your {$planLabel} plan. Contact us to upgrade.</span>
        </div>
    ";
}

/**
 * Generate unique SKU (if you want auto-generation)
 */
function generateSKU($prefix = 'PROD')
{
    return $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'Y-m-d H:i')
{
    if (empty($date)) {
        return '';
    }
    return date($format, strtotime($date));
}

/**
 * Extract numeric ID from a string (e.g., "SALE-0001" → 1)
 */
function extractId($string)
{
    preg_match('/\d+$/', $string, $matches);
    return isset($matches[0]) ? (int)$matches[0] : 0;
}

/**
 * Normalize product units so common aliases map to the app's default value.
 */
function normalizeUnit($value)
{
    $unit = trim((string)($value ?? ''));
    if ($unit === '') {
        return 'pcs';
    }

    $normalized = strtolower($unit);
    $aliases = ['pcs', 'pc', 'piece', 'pieces', 'unit', 'units'];

    return in_array($normalized, $aliases, true) ? 'pcs' : $normalized;
}

/**
 * Record a transaction on a financial account.
 *
 * When $accountId is provided the transaction is posted directly to that
 * account. When omitted the helper falls back to the seeded default cash
 * account so that money is never silently dropped.
 *
 * @param Database $db
 * @param string   $paymentMethod  'cash', 'mobile', 'bank', etc.
 * @param float    $amount
 * @param string   $type           'deposit' or 'withdrawal'
 * @param string   $referenceType  'sale', 'purchase', 'expense', etc.
 * @param int      $referenceId
 * @param string   $notes
 * @param int|null $accountId      Specific account ID (optional)
 * @return bool
 */
function recordAccountTransaction($db, $paymentMethod, $amount, $type, $referenceType, $referenceId, $notes, $accountId = null)
{
    $amount = floatval($amount);
    if ($amount <= 0) return false;

    // ── Resolve account ────────────────────────────────────────────────────
    if ($accountId && $accountId > 0) {
        // Use the explicitly selected account
        $account = $db->fetchOne("SELECT * FROM accounts WHERE id = ? AND is_active = 1 LIMIT 1", [$accountId]);
    } else {
        // Auto-resolve: try default account for the payment-method type
        $accountType = null;
        if ($paymentMethod === 'cash') {
            $accountType = 'cash';
        } elseif ($paymentMethod === 'mobile') {
            $accountType = 'mobile_money';
        } elseif (in_array($paymentMethod, ['bank', 'cheque'])) {
            $accountType = 'bank';
        }

        if ($accountType) {
            $account = $db->fetchOne(
                "SELECT * FROM accounts WHERE type = ? AND is_default = 1 AND is_active = 1 LIMIT 1",
                [$accountType]
            );
        }

        // Final fallback: default cash account (always seeded)
        if (empty($account)) {
            $account = $db->fetchOne(
                "SELECT * FROM accounts WHERE type = 'cash' AND is_default = 1 AND is_active = 1 LIMIT 1"
            );
        }
    }

    if (!$account) return false;

    $resolvedAccountId = $account['id'];
    $balanceBefore     = floatval($account['balance']);
    $balanceAfter      = ($type === 'deposit')
        ? $balanceBefore + $amount
        : $balanceBefore - $amount;

    // Update account balance
    $db->query("UPDATE accounts SET balance = ? WHERE id = ?", [$balanceAfter, $resolvedAccountId]);

    // Record transaction log
    $db->query("
        INSERT INTO account_transactions
            (account_id, transaction_type, amount, balance_before, balance_after,
             reference_type, reference_id, notes, user_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ", [
        $resolvedAccountId,
        $type,
        $amount,
        $balanceBefore,
        $balanceAfter,
        $referenceType,
        $referenceId,
        $notes,
        $_SESSION['user_id'] ?? null
    ]);

    return true;
}

/**
 * Read + normalize the account-ledger filter inputs. Shared by the
 * ledger page (FinancialAccountsController) and its CSV export
 * (ExportController) — both load independently per request, so this
 * lives in the global helpers file rather than being duplicated,
 * guaranteeing the export always matches exactly what's on screen.
 */
function ledgerFilters()
{
    return [
        'type'      => $_GET['type'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to'   => $_GET['date_to'] ?? '',
    ];
}

/**
 * Build the WHERE clause + params for an account's ledger, given the
 * filters from ledgerFilters().
 */
function buildLedgerWhere($accountId, $filters)
{
    $where  = "WHERE t.account_id = ?";
    $params = [$accountId];

    $validTypes = ['deposit', 'withdrawal', 'transfer_out', 'transfer_in', 'charge'];
    if (!empty($filters['type']) && in_array($filters['type'], $validTypes, true)) {
        $where .= " AND t.transaction_type = ?";
        $params[] = $filters['type'];
    }

    if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
        $where .= " AND DATE(t.created_at) BETWEEN ? AND ?";
        $params[] = $filters['date_from'];
        $params[] = $filters['date_to'];
    } elseif (!empty($filters['date_from'])) {
        $where .= " AND DATE(t.created_at) >= ?";
        $params[] = $filters['date_from'];
    } elseif (!empty($filters['date_to'])) {
        $where .= " AND DATE(t.created_at) <= ?";
        $params[] = $filters['date_to'];
    }

    return [$where, $params];
}

/**
 * Format a quantity for display. Shows whole numbers plainly (5, not
 * 5.00) but keeps up to 3 decimal places when a product is sold in
 * fractional amounts (0.5, 1.25, 0.125) — added alongside decimal
 * stock/quantity support so displays don't silently round a real
 * fractional quantity down to a whole number.
 */
function formatQty($value)
{
    $value = floatval($value ?? 0);
    $rounded = round($value, 3);

    if ($rounded == floor($rounded)) {
        return number_format($rounded, 0);
    }

    // Trim trailing zeros (1.500 -> 1.5) while keeping real precision.
    return rtrim(rtrim(number_format($rounded, 3), '0'), '.');
}

/**
 * Single-session enforcement. Compares this session's stored login
 * token against the one currently on file for the user in the
 * database (set fresh on every successful login — see
 * AuthController::processLogin()). If they don't match, a newer
 * login has happened elsewhere for this account, so this older
 * session is signed out immediately.
 *
 * Cost: one indexed primary-key lookup per authenticated request —
 * the same cost class as currentPlan()/currentUserPermissions(),
 * which already run once per request. Chosen over an interactive
 * "someone else is logged in, continue anyway?" prompt because that
 * would add an extra round-trip at every login (rare) for no
 * reduction in this per-request cost (unavoidable either way to
 * detect a takeover promptly) — i.e. it doesn't trade away any
 * steady-state speed, so the simpler behavior wins.
 *
 * Sessions created before this feature existed have no token in
 * either place yet, so both sides are NULL and are treated as a
 * match — nobody is forced to log out purely because this shipped;
 * enforcement begins the next time that user (or anyone impersonating
 * them) actually logs in.
 */
function enforceSingleSession()
{
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        return;
    }

    $db = Database::getInstance();
    $row = $db->fetchOne("SELECT session_token FROM users WHERE id = ?", [$userId]);
    $dbToken = $row['session_token'] ?? null;
    $sessionToken = $_SESSION['session_token'] ?? null;

    if ($dbToken === $sessionToken) {
        return; // still the current session for this user
    }

    // Someone (possibly this same person, from another device) has
    // logged in since this session started. Sign this one out.
    logAudit('user.session_kicked', 'user', $userId, [
        'reason' => 'Signed in from another device/session',
    ]);

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();

    // Start a new session so the flash message survives to the login page.
    session_start();
    $_SESSION['flash_type'] = 'info';
    $_SESSION['flash_message'] = 'You have been signed out because this account was signed in from another device.';

    header('Location: ' . BASE_URL . '/login');
    exit;
}

/**
 * Make sure a walk-in customer (customers.is_default = 1) actually
 * exists. Client databases that predate the schema.sql seed row (or
 * where it was deleted) would otherwise have "Default POS customer to
 * Walk-in" silently do nothing — there'd be no is_default=1 row to
 * load. Called from showPOS() (so the very first page load that needs
 * it self-heals) and from the Settings save handler (so turning the
 * setting on proactively fixes it too). Idempotent — safe to call on
 * every POS load; it only ever inserts once.
 */
function ensureWalkInCustomerExists($db)
{
    $existing = $db->fetchOne("SELECT id FROM customers WHERE is_default = 1 LIMIT 1");
    if ($existing) {
        return;
    }

    $code = $db->fetchOne("SELECT id FROM customers WHERE customer_code = 'WALK-IN-001'")
        ? 'WALK-IN-' . time()
        : 'WALK-IN-001';

    $db->query(
        "INSERT INTO customers (customer_code, full_name, is_default, credit_limit, is_active) VALUES (?, ?, 1, 0.00, 1)",
        [$code, 'Walk-in Customer']
    );
}

/**
 * Is multi-branch active for this install? Independent of plan tier —
 * every Enterprise client gets it automatically, but it can also be
 * sold as a paid add-on to a Growth client via settings.addon_multi_branch.
 * Cached per-request like currentPlan().
 */
function hasMultiBranch()
{
    static $result = null;
    if ($result === null) {
        $db  = Database::getInstance();
        $row = $db->fetchOne("SELECT plan, addon_multi_branch FROM settings ORDER BY id ASC LIMIT 1");
        $result = (($row['plan'] ?? 'core') === 'enterprise') || !empty($row['addon_multi_branch']);
    }
    return $result;
}

/**
 * All branches a user is assigned to (id, name, is_primary), ordered
 * primary-first. Cached per user id per request.
 */
function userBranches($userId)
{
    static $cache = [];
    if (!isset($cache[$userId])) {
        $db = Database::getInstance();
        $cache[$userId] = $db->fetchAll("
            SELECT b.id, b.name, ub.is_primary
            FROM user_branches ub
            JOIN branches b ON b.id = ub.branch_id
            WHERE ub.user_id = ? AND b.is_active = 1
            ORDER BY ub.is_primary DESC, b.name ASC
        ", [$userId]);
    }
    return $cache[$userId];
}

/**
 * The branch a sale/purchase/stock movement should be recorded
 * against right now — the current user's "active" branch. No picker
 * is shown in POS; this is resolved once (defaults to the user's
 * primary branch, changeable via the branch switcher when a user has
 * more than one). Falls back to Main Branch (1) defensively if
 * somehow unset.
 */
function activeBranchId()
{
    return currentUser()['branch_id'] ?? 1;
}

/**
 * Display name for the current user's active branch.
 */
function activeBranchName()
{
    return branchName(activeBranchId());
}

/**
 * Is the current user "company-wide" (sees/manages every branch) or
 * restricted to just the branches they're assigned to? Independent of
 * role — an Admin can be scoped to one branch ("branch admin"), and a
 * non-admin could in principle be made company-wide, though in
 * practice this is mainly used to distinguish branch-level admins
 * from company-wide ones. Defaults to 'assigned' (the more
 * conservative option) for anyone not explicitly set otherwise.
 */
function isCompanyWide()
{
    return (currentUser()['branch_scope'] ?? 'assigned') === 'all';
}

/**
 * Which branch IDs the current user should see data for.
 * Returns null to mean "no restriction" (multi-branch isn't active
 * for this install, or this user is company-wide) — callers should
 * treat null as "don't filter at all", not as "empty list".
 */
function visibleBranchIds()
{
    if (!hasMultiBranch() || isCompanyWide()) {
        return null;
    }
    return array_column(userBranches(currentUser()['id']), 'id');
}

/**
 * A ready-to-splice SQL fragment + params enforcing branch visibility
 * on a query, built from visibleBranchIds(). Returns ['', []] when
 * there's no restriction to apply. If a user is somehow assigned to
 * zero branches, this deliberately shows nothing rather than
 * everything (fails closed, not open).
 *
 * Usage:
 *   [$scopeSql, $scopeParams] = branchScopeSql('s');
 *   $where   .= $scopeSql;
 *   $params   = array_merge($params, $scopeParams);
 */
function branchScopeSql($alias = '', $column = 'branch_id')
{
    $branchIds = visibleBranchIds();
    if ($branchIds === null) {
        return ['', []];
    }
    if (empty($branchIds)) {
        return [' AND 1=0', []];
    }
    $prefix = $alias ? "$alias." : '';
    $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
    return [" AND {$prefix}{$column} IN ($placeholders)", $branchIds];
}

/**
 * Display name of any branch by id (e.g. for the Users list),
 * cached per-request per branch id.
 */
function branchName($branchId)
{
    static $cache = [];
    if (!$branchId) return '—';
    if (!array_key_exists($branchId, $cache)) {
        $db  = Database::getInstance();
        $row = $db->fetchOne("SELECT name FROM branches WHERE id = ?", [$branchId]);
        $cache[$branchId] = $row['name'] ?? '—';
    }
    return $cache[$branchId];
}

/**
 * Record an audit trail entry. Scope is deliberately narrow — this is
 * for money and access-changing actions (voids, permission/role
 * changes, user/branch/account edits, settings), not routine data
 * entry (a normal sale or purchase is not logged here; who can see
 * it and reconstruct it from the sales/purchases tables themselves).
 *
 * Never throws — a logging failure should never block the actual
 * action it's describing. If the audit_log insert fails for any
 * reason (e.g. this migration hasn't been run yet on an older
 * install), the calling code continues normally.
 *
 * @param string $action      e.g. 'sale.void', 'user.update', 'role.permissions_changed'
 * @param string|null $entityType  e.g. 'sale', 'user', 'role', 'account', 'branch', 'settings'
 * @param int|null $entityId
 * @param array $details      Arbitrary JSON-able context — what changed, old/new values, etc.
 */
function logAudit($action, $entityType = null, $entityId = null, $details = [])
{
    try {
        $db = Database::getInstance();
        $db->query("
            INSERT INTO audit_log (user_id, username, action, entity_type, entity_id, branch_id, details, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $_SESSION['user_id'] ?? null,
            $_SESSION['username'] ?? null,
            $action,
            $entityType,
            $entityId,
            currentUser() ? activeBranchId() : null,
            !empty($details) ? json_encode($details) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Exception $e) {
        // Deliberately swallow — see doc comment above.
    }
}

/**
 * How much of a product is at a given branch (defaults to the
 * current user's active branch). This is the real, per-branch
 * source of truth — products.current_stock is a maintained total
 * across all branches, not a substitute for this.
 */
function getBranchStock($productId, $branchId = null)
{
    $branchId = $branchId ?? activeBranchId();
    $db = Database::getInstance();
    $row = $db->fetchOne(
        "SELECT quantity FROM branch_stock WHERE product_id = ? AND branch_id = ?",
        [$productId, $branchId]
    );
    return $row ? floatval($row['quantity']) : 0.0;
}

/**
 * Add (positive delta) or remove (negative delta) stock for a product
 * at a specific branch, then keep products.current_stock in sync as
 * the total across all branches. This is THE function every
 * stock-changing action should call — never write to branch_stock or
 * products.current_stock directly, or the two will drift apart.
 *
 * Upserts the branch_stock row (a product may not have had any
 * recorded stock at this branch yet).
 */
function adjustBranchStock($db, $productId, $branchId, $delta)
{
    $db->query("
        INSERT INTO branch_stock (product_id, branch_id, quantity)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
    ", [$productId, $branchId, $delta]);

    recalcProductTotalStock($db, $productId);

    return getBranchStock($productId, $branchId);
}

/**
 * Set a product's stock at a specific branch to an absolute value
 * (used by manual stock adjustment, not by sales/purchases, which
 * should use adjustBranchStock() with a relative delta instead).
 * Also keeps products.current_stock in sync.
 */
function setBranchStock($db, $productId, $branchId, $newQuantity)
{
    $db->query("
        INSERT INTO branch_stock (product_id, branch_id, quantity)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)
    ", [$productId, $branchId, $newQuantity]);

    recalcProductTotalStock($db, $productId);
}

/**
 * Recompute products.current_stock as the sum of branch_stock across
 * every branch for one product. Called automatically by
 * adjustBranchStock()/setBranchStock() — exposed standalone too, for
 * a one-off repair if the cache is ever suspected to have drifted.
 */
function recalcProductTotalStock($db, $productId)
{
    $total = $db->fetchOne(
        "SELECT COALESCE(SUM(quantity), 0) AS total FROM branch_stock WHERE product_id = ?",
        [$productId]
    );
    $totalValue = floatval($total['total'] ?? 0);
    $db->query("UPDATE products SET current_stock = ? WHERE id = ?", [$totalValue, $productId]);
    return $totalValue;
}
