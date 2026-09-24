<?php

/**
 * General Helper Functions
 */

/**
 * Redirect to another page with optional flash message
 */
function redirect(mixed $url, mixed $type = null, mixed $message = null)
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
function formatMoney(mixed $amount)
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
function formatNumber(mixed $number, mixed $decimals = 0)
{
    $number = $number ?? 0;
    return number_format(floatval($number), $decimals);
}

/**
 * Safe integer conversion
 */
function safeInt(mixed $value)
{
    return intval($value ?? 0);
}

/**
 * Safe float conversion
 */
function safeFloat(mixed $value)
{
    return floatval($value ?? 0);
}

/**
 * Escape HTML output (prevent XSS attacks)
 */
function e(mixed $string)
{
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Get old input value (for form validation errors)
 */
function old(mixed $field, mixed $default = '')
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
function can(mixed $permission)
{
    return in_array($permission, currentUserPermissions(), true);
}

/**
 * Slug of a role by id (admin/staff/cashier/custom-slug), cached
 * per-request per role_id since it's looked up repeatedly (e.g.
 * once per user row on the Users list).
 */
function roleSlug(mixed $roleId)
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
function roleName(mixed $roleId)
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
function planAllows(mixed $feature)
{
    $definition = currentPlanDefinition();
    return in_array($feature, $definition['features'] ?? [], true);
}

/**
 * Is there room for one more active user under the current plan?
 * Pass the count of currently active users (see UserController).
 */
function withinUserLimit(mixed $activeUserCount)
{
    return (int)$activeUserCount < currentPlanDefinition()['max_users'];
}

/**
 * Render a small "Upgrade your plan" notice, styled like the app's
 * existing flash messages, for use on blocked pages/sections.
 */
function planUpgradeNotice(mixed $featureLabel = 'This feature')
{
    $planLabel = currentPlanDefinition()['label'];
    return "
        <div class='bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded mb-4' role='alert'>
            <span class='block sm:inline'>{$featureLabel} isn't included in your {$planLabel} plan. Contact us to upgrade.</span>
        </div>
    ";
}

/**
 * Load app/config/licensing.php (warning/grace windows + public key).
 * Cached per-request, same pattern as planDefinitions().
 */
function licensingConfig()
{
    static $config = null;
    if ($config === null) {
        $config = require APP_PATH . '/config/licensing.php';
    }
    return $config;
}

/**
 * The single `license` row for this install (one row, like `settings`).
 * Auto-creates it (grandfathered 90 days) if a fresh install somehow
 * reaches this before the migration has run, so it degrades safely
 * rather than fatal-erroring every page.
 * Cached per-request only (not session) — same reasoning as
 * currentPlan(): entering a code should take effect on the very next
 * page load, no logout/login needed.
 */
function licenseRow()
{
    static $row = null;
    if ($row === null) {
        $db  = Database::getInstance();
        $row = $db->fetchOne("SELECT * FROM license ORDER BY id ASC LIMIT 1");
        if (!$row) {
            $db->query(
                "INSERT INTO license (install_id, expires_at) VALUES (?, DATE_ADD(NOW(), INTERVAL 90 DAY))",
                [bin2hex(random_bytes(8))]
            );
            $row = $db->fetchOne("SELECT * FROM license ORDER BY id ASC LIMIT 1");
        }
    }
    return $row;
}

/**
 * This install's unique id. Shown on /license so the client can quote
 * it when requesting a renewal code — codes are bound to one install_id
 * and won't verify against a different one.
 */
function licenseInstallId()
{
    return licenseRow()['install_id'];
}

function licenseExpiresAt()
{
    return licenseRow()['expires_at'];
}

/**
 * Signed day count to expiry: positive = days remaining, negative =
 * days since it expired. Display only — see licenseState() for the
 * actual lock/warning decision (which compares full timestamps, not
 * rounded days, to avoid off-by-one edge cases at day boundaries).
 */
function licenseDaysRemaining()
{
    $now       = new DateTimeImmutable('today');
    $expiresAt = new DateTimeImmutable(licenseExpiresAt());
    return (int) $now->diff($expiresAt)->format('%r%a');
}

/**
 * 'active'  — nothing to show.
 * 'warning' — inside warning_days of expiry, or past expiry but still
 *             inside grace_days. App stays fully usable either way.
 * 'locked'  — past expires_at + grace_days. Every route except
 *             /license (and login/logout) redirects there — see
 *             public/index.php.
 */
function licenseState()
{
    $config    = licensingConfig();
    $now       = new DateTimeImmutable();
    $expiresAt = new DateTimeImmutable(licenseExpiresAt());

    if ($now > $expiresAt->modify("+{$config['grace_days']} days")) {
        return 'locked';
    }
    if ($now >= $expiresAt->modify("-{$config['warning_days']} days")) {
        return 'warning';
    }
    return 'active';
}

/**
 * Verify and apply an unlock code submitted on /license.
 * Returns ['ok' => bool, 'message' => string] for the controller to
 * flash straight back to the user.
 */
function applyLicenseCode(mixed $code)
{
    $config  = licensingConfig();
    $decoded = LicenseCode::verify((string) $code, $config['public_key']);

    if ($decoded === null) {
        return ['ok' => false, 'message' => "That code isn't valid. Double check you copied the whole thing."];
    }

    if ($decoded['install_id'] !== licenseInstallId()) {
        return ['ok' => false, 'message' => 'That code was issued for a different installation.'];
    }

    if ($decoded['expires_at'] <= time()) {
        return ['ok' => false, 'message' => 'That code has already expired.'];
    }

    $previousExpiresAt = licenseExpiresAt();
    $newExpiresAt       = date('Y-m-d H:i:s', $decoded['expires_at']);

    $db = Database::getInstance();
    $db->query(
        "UPDATE license SET expires_at = ?, last_code_used = ?, last_unlocked_at = NOW() ORDER BY id ASC LIMIT 1",
        [$newExpiresAt, substr((string) $code, 0, 255)]
    );

    logAudit('license.renew', 'license', null, [
        'previous_expires_at' => $previousExpiresAt,
        'new_expires_at'      => $newExpiresAt,
    ], true);

    return [
        'ok'      => true,
        'message' => 'Subscription renewed — active until ' . formatDate($newExpiresAt, 'd M Y') . '.',
    ];
}

/**
 * Generate unique SKU (if you want auto-generation)
 */
function generateSKU(mixed $prefix = 'PROD')
{
    return $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

/**
 * Format date for display
 */
function formatDate(mixed $date, mixed $format = 'Y-m-d H:i')
{
    if (empty($date)) {
        return '';
    }
    return date($format, strtotime($date));
}

/**
 * Extract numeric ID from a string (e.g., "SALE-0001" → 1)
 */
function extractId(mixed $string)
{
    preg_match('/\d+$/', $string, $matches);
    return isset($matches[0]) ? (int)$matches[0] : 0;
}

/**
 * Normalize product units so common aliases map to the app's default value.
 */
function normalizeUnit(mixed $value)
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
 * account. When omitted the helper falls back to the default account for
 * $paymentMethod's type, preferring one scoped to $branchId (or the
 * current active branch) over a company-wide one — cash accounts are
 * branch-scoped, so this matters there; mobile/bank defaults are still
 * company-wide today and match regardless of branch.
 *
 * @param Database $db
 * @param string   $paymentMethod  'cash', 'mobile', 'bank', etc.
 * @param float    $amount
 * @param string   $type           'deposit' or 'withdrawal'
 * @param string   $referenceType  'sale', 'purchase', 'expense', etc.
 * @param int      $referenceId
 * @param string   $notes
 * @param int|null $accountId      Specific account ID (optional)
 * @param int|null $branchId       Branch to prefer when falling back by type (optional, defaults to activeBranchId())
 * @return bool
 */
function recordAccountTransaction(Database $db, mixed $paymentMethod, mixed $amount, mixed $type, mixed $referenceType, mixed $referenceId, mixed $notes, mixed $accountId = null, mixed $branchId = null)
{
    $amount = floatval($amount);
    if ($amount <= 0) return false;

    // ── Resolve account ────────────────────────────────────────────────────
    if ($accountId && $accountId > 0) {
        // Use the explicitly selected account. Deliberately NOT branch-scoped
        // here — this single function is also how void/refund flows reverse a
        // historical account_transactions row (e.g. SaleController::voidSale()
        // passes $acctTx['account_id']), and that account must be reachable
        // regardless of the *voiding* user's own branch scope, since undoing a
        // transaction isn't the same as choosing one. Branch validation of a
        // freshly-submitted account_id belongs in the caller, before it ever
        // reaches here — see accountBranchScopeSql() call sites in the
        // controllers (deposit/payment/POS pickers).
        $account = $db->fetchOne("SELECT * FROM accounts WHERE id = ? AND is_active = 1 LIMIT 1", [$accountId]);
    } else {
        // Auto-resolve: try default account for the payment-method type,
        // preferring one scoped to $branchId over a company-wide one.
        $accountType = null;
        if ($paymentMethod === 'cash') {
            $accountType = 'cash';
        } elseif ($paymentMethod === 'mobile') {
            $accountType = 'mobile_money';
        } elseif (in_array($paymentMethod, ['bank', 'cheque'])) {
            $accountType = 'bank';
        }

        $effectiveBranchId = $branchId ?? activeBranchId();

        if ($accountType) {
            $account = $db->fetchOne(
                "SELECT * FROM accounts
                 WHERE type = ? AND is_default = 1 AND is_active = 1
                   AND (branch_id = ? OR branch_id IS NULL)
                 ORDER BY (branch_id = ?) DESC
                 LIMIT 1",
                [$accountType, $effectiveBranchId, $effectiveBranchId]
            );
        }

        // Final fallback: default cash account (always seeded), same branch preference
        if (empty($account)) {
            $account = $db->fetchOne(
                "SELECT * FROM accounts
                 WHERE type = 'cash' AND is_default = 1 AND is_active = 1
                   AND (branch_id = ? OR branch_id IS NULL)
                 ORDER BY (branch_id = ?) DESC
                 LIMIT 1",
                [$effectiveBranchId, $effectiveBranchId]
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
function buildLedgerWhere(mixed $accountId, mixed $filters)
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
function formatQty(mixed $value)
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

    // Clear auth state but keep this same session (and its cookie) alive
    // so the flash message actually reaches /login. An earlier version of
    // this function expired the cookie and called session_destroy() first
    // (like processLogout() does), then tried to session_start() a fresh
    // session under the same ID to carry the flash message — but PHP
    // doesn't reliably re-send a Set-Cookie for a reused ID once one has
    // already been expired in the same response, so the browser was left
    // with no session cookie at all and the flash message never made it
    // to the login page (QA: "signed out, but no explanatory message").
    // Simply clearing $_SESSION (as redirect() already does for every
    // other flash message) avoids the whole cookie/destroy dance.
    $_SESSION = [];
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
function ensureWalkInCustomerExists(Database $db)
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
function userBranches(mixed $userId)
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
 * How much of a customer's deposit is still unspent.
 *
 * current_balance = deposits + cash/mobile/bank payments − sale totals
 * (every sale takes its FULL total off the balance, whatever the payment
 * method; applying a deposit to a sale then only marks the sale paid —
 * see processDeposit()'s auto-apply). So the balance alone understates
 * the deposit when sales still have amounts owing: unspent deposit =
 * balance + everything still owed on (non-voided) sales.
 */
function unappliedDeposit(Database $db, mixed $customerId, float $balance): float
{
    $row = $db->fetchOne(
        "SELECT COALESCE(SUM(amount_due), 0) AS due FROM sales
         WHERE customer_id = ? AND (notes IS NULL OR notes NOT LIKE '%[VOIDED]%')",
        [$customerId]
    );
    return $balance + floatval($row['due'] ?? 0);
}

/**
 * Net money a sale has put into each financial account so far (its
 * original payment, later /pay receipts, and any edit adjustments), as
 * [account_id => amount], largest first. Deposit-paid amounts never
 * appear here — no money moved when a deposit was applied.
 */
function saleAccountNet(Database $db, mixed $saleId): array
{
    $rows = $db->fetchAll("
        SELECT account_id,
               SUM(CASE WHEN transaction_type = 'deposit' THEN amount
                        WHEN transaction_type = 'withdrawal' THEN -amount
                        ELSE 0 END) AS net
        FROM account_transactions
        WHERE reference_type = 'sale' AND reference_id = ?
        GROUP BY account_id
        HAVING net > 0.005
        ORDER BY net DESC
    ", [$saleId]);
    return array_column($rows, 'net', 'account_id');
}

/**
 * Credit-limit check for a sale that leaves something owing. Returns an
 * error message, or null if the sale is within the customer's limit.
 *
 * The rule: after the sale, the customer may owe at most their credit
 * limit (balance_after >= -credit_limit). Callers pass the balance the
 * sale will actually leave — for a "pay from deposit" sale that is
 * balance - total (the deposit is consumed by this sale), otherwise
 * balance - amount_due. Checking "limit + current balance >= due", as
 * POS used to, counted a deposit twice when paying from it.
 * Walk-in customers are exempt, as before (they can't take credit or
 * use a deposit anyway — those are blocked separately).
 */
function creditLimitError(array $customer, float $balanceAfter, float $amountDue): ?string
{
    if ($amountDue <= 0 || !empty($customer['is_default'])) {
        return null;
    }
    $creditLimit = floatval($customer['credit_limit']);
    if ($balanceAfter >= -$creditLimit - 0.005) {
        return null;
    }
    // Credit still open to this sale once any deposit it uses is spent
    $available = max(0, $creditLimit + $balanceAfter + $amountDue);
    return "Insufficient credit. Available: " . formatMoney($available) . ", Required: " . formatMoney($amountDue)
        . " (credit limit " . formatMoney($creditLimit) . ")";
}

/**
 * Branches the current user may make their active branch via the
 * header switcher. Company-wide users can work "as" any active branch
 * (their user_branches row is just a home default); branch-scoped users
 * only their assigned ones. Switching changes users.branch_id (what
 * activeBranchId() reads) — never user_branches, so it grants no extra
 * access: assignment-based checks like receiving a transfer still go
 * by the real assignment.
 */
function switchableBranches()
{
    if (isCompanyWide()) {
        return Database::getInstance()->fetchAll("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name ASC");
    }
    return userBranches($_SESSION['user_id']);
}

/**
 * The branch a customer deposit should be recorded against — the branch
 * that owns the account the money physically landed in (e.g. a branch's
 * cash account), not whoever keyed it in: a company-wide admin whose home
 * branch is Main recording a deposit into Kasoa's cash drawer is a Kasoa
 * deposit. Falls back to activeBranchId() only for company-wide accounts
 * (branch_id NULL — shared bank/MoMo), where the account says nothing
 * about location. Drives the customer credit settlement report.
 */
function depositBranchId(?array $account)
{
    return $account['branch_id'] ?? activeBranchId();
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
 * Can the current user manage/see data scoped to this specific branch
 * id? True for company-wide users (and whenever multi-branch isn't
 * active for this install); otherwise only for branches they're
 * actually assigned to. Use this to guard direct-by-id access (edit,
 * delete, etc.) — visibleBranchIds()/branchScopeSql() alone only keep
 * a *list* correctly scoped, they don't stop a branch-scoped user
 * from reaching another branch's record by guessing/typing its URL.
 */
function canAccessBranch(mixed $branchId): bool
{
    if (!hasMultiBranch() || isCompanyWide()) {
        return true;
    }
    return in_array((int) $branchId, array_column(userBranches(currentUser()['id']), 'id'), true);
}

/**
 * SQL expression for a sale line's revenue net of the sale-level
 * discount. sale_items.line_total is already net of the line's own
 * discount; sales.discount_amount (the whole-cart discount) lives only
 * on the header, so it is spread across lines pro rata to line_total.
 * sales.subtotal is the sum of line_totals, so the shares add back up
 * to the discount given. Use in place of SUM(si.line_total) in any
 * revenue/profit report that joins sale_items to sales.
 *
 * Usage:
 *   $netLine = saleItemNetSql();          // aliases si / s
 *   "SELECT SUM({$netLine}) AS revenue FROM sale_items si JOIN sales s ..."
 */
function saleItemNetSql(string $itemAlias = 'si', string $saleAlias = 's'): string
{
    return "{$itemAlias}.line_total * (1 - COALESCE({$saleAlias}.discount_amount / NULLIF({$saleAlias}.subtotal, 0), 0))";
}

/**
 * SQL expression for a sale line's cost of goods sold. Uses the cost
 * snapshotted on the line at time of sale (sale_items.unit_cost), so a
 * past period's COGS doesn't move when later purchases change
 * products.average_cost. Falls back to the current average cost only
 * for a line with no snapshot. Use in any COGS/profit report that joins
 * sale_items to products — never si.quantity * p.average_cost.
 *
 * Usage:
 *   $costLine = saleItemCostSql();        // aliases si / p
 *   "SELECT SUM({$costLine}) AS cogs FROM sale_items si JOIN products p ..."
 */
function saleItemCostSql(string $itemAlias = 'si', string $productAlias = 'p'): string
{
    return "({$itemAlias}.quantity * COALESCE({$itemAlias}.unit_cost, {$productAlias}.average_cost))";
}

/**
 * Per-line share of each sale's whole-cart discount, for exports that write
 * one row per line item. Same rule as saleItemNetSql() (pro rata to
 * line_total), but rounded to cents per line with the leftover cent put on
 * the sale's LAST line, so a column sum equals the discount actually given.
 * Rows must carry sale_id and line_total, and be grouped by sale.
 *
 * @param array  $rows        export rows, one per line item
 * @param string $subtotalKey key holding the sale's subtotal on each row
 * @param string $discountKey key holding the sale's cart discount on each row
 * @return array [row index => allocated discount, 2dp float]
 */
function allocateCartDiscount(array $rows, string $subtotalKey, string $discountKey): array
{
    $bySale = [];
    foreach ($rows as $i => $r) {
        $bySale[$r['sale_id']][] = $i;
    }

    $alloc = [];
    foreach ($bySale as $indexes) {
        $subtotal = (float) $rows[$indexes[0]][$subtotalKey];
        $discount = round((float) $rows[$indexes[0]][$discountKey], 2);
        $given    = 0.0;
        $last     = count($indexes) - 1;

        foreach ($indexes as $n => $i) {
            if ($subtotal <= 0 || $discount <= 0) {
                $alloc[$i] = 0.0;
                continue;
            }
            $share = $n === $last
                ? round($discount - $given, 2)
                : round((float) $rows[$i]['line_total'] / $subtotal * $discount, 2);
            $alloc[$i] = $share;
            $given    += $share;
        }
    }
    return $alloc;
}

/**
 * Branches the current user may look at stock for: every active branch for a
 * company-wide user, only their own for a branch-scoped one. [] means none.
 *
 * @return array [['id' => .., 'name' => ..], ...] ordered by name
 */
function viewableBranches(Database $db): array
{
    $ids = visibleBranchIds();
    if ($ids === null) {
        return $db->fetchAll("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name ASC");
    }
    if (empty($ids)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    return $db->fetchAll(
        "SELECT id, name FROM branches WHERE is_active = 1 AND id IN ($placeholders) ORDER BY name ASC",
        $ids
    );
}

/**
 * Product stock laid out per branch — the data behind the Stock by Branch
 * report and its CSV export (shared so the two can never disagree).
 *
 * Branch filtering is validated against viewableBranches(): a requested
 * branch the user isn't allowed to see is ignored (falls back to all of
 * their viewable branches), so a branch-scoped user can't read another
 * branch's stock by editing the URL.
 *
 * $f keys (all optional): branch, category, search, status
 * (in_stock|low|out), sort_by (name|stock_desc|stock_asc|value_desc|value_asc).
 *
 * @return array ['viewable' => [...], 'branches' => [...shown], 'selected' => int|null,
 *                'rows' => [...], 'branchTotals' => [id => ['units','value']], 'summary' => [...]]
 */
function inventoryByBranch(Database $db, array $f): array
{
    $viewable    = viewableBranches($db);
    $viewableIds = array_map('intval', array_column($viewable, 'id'));

    $selected = null;
    if (($f['branch'] ?? '') !== '' && in_array((int) $f['branch'], $viewableIds, true)) {
        $selected = (int) $f['branch'];
    }
    $shown    = array_values(array_filter($viewable, fn($b) => $selected === null || (int) $b['id'] === $selected));
    $shownIds = array_map('intval', array_column($shown, 'id'));

    // Product filters
    $where  = "WHERE p.is_active = 1";
    $params = [];
    if (!empty($f['category'])) {
        $where   .= " AND p.category_id = ?";
        $params[] = $f['category'];
    }
    $search = trim($f['search'] ?? '');
    if ($search !== '') {
        $where   .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $products = $db->fetchAll("
        SELECT p.id, p.sku, p.name, p.unit, p.reorder_level, p.average_cost, p.selling_price,
               c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        $where
    ", $params);

    // One query for every shown branch's quantities, pivoted below.
    $qty = [];
    if ($shownIds) {
        $ph   = implode(',', array_fill(0, count($shownIds), '?'));
        $rows = $db->fetchAll("SELECT product_id, branch_id, quantity FROM branch_stock WHERE branch_id IN ($ph)", $shownIds);
        foreach ($rows as $r) {
            $qty[(int) $r['product_id']][(int) $r['branch_id']] = (float) $r['quantity'];
        }
    }

    $status = $f['status'] ?? '';
    $out    = [];
    foreach ($products as $p) {
        $perBranch = [];
        $total     = 0.0;
        foreach ($shownIds as $bid) {
            $q = $qty[(int) $p['id']][$bid] ?? 0.0;
            $perBranch[$bid] = $q;
            $total += $q;
        }
        $reorder = (float) $p['reorder_level'];
        $state   = $total <= 0 ? 'out' : ($total <= $reorder ? 'low' : 'ok');
        if ($status === 'in_stock' && $total <= 0) continue;
        if ($status === 'low' && $state !== 'low') continue;
        if ($status === 'out' && $state !== 'out') continue;

        $p['branch_qty'] = $perBranch;
        $p['total']      = $total;
        $p['value']      = $total * (float) $p['average_cost'];
        $p['state']      = $state;
        $out[]           = $p;
    }

    $sortBy = $f['sort_by'] ?? 'name';
    usort($out, match ($sortBy) {
        'stock_desc' => fn($a, $b) => $b['total'] <=> $a['total'],
        'stock_asc'  => fn($a, $b) => $a['total'] <=> $b['total'],
        'value_desc' => fn($a, $b) => $b['value'] <=> $a['value'],
        'value_asc'  => fn($a, $b) => $a['value'] <=> $b['value'],
        default      => fn($a, $b) => strcasecmp($a['name'], $b['name']),
    });

    $branchTotals = [];
    foreach ($shownIds as $bid) {
        $branchTotals[$bid] = ['units' => 0.0, 'value' => 0.0];
    }
    $summary = ['products' => count($out), 'units' => 0.0, 'value' => 0.0, 'low' => 0, 'out' => 0];
    foreach ($out as $p) {
        $summary['units'] += $p['total'];
        $summary['value'] += $p['value'];
        if ($p['state'] === 'low') $summary['low']++;
        if ($p['state'] === 'out') $summary['out']++;
        foreach ($p['branch_qty'] as $bid => $q) {
            $branchTotals[$bid]['units'] += $q;
            $branchTotals[$bid]['value'] += $q * (float) $p['average_cost'];
        }
    }

    return [
        'viewable'     => $viewable,
        'branches'     => $shown,
        'selected'     => $selected,
        'rows'         => $out,
        'branchTotals' => $branchTotals,
        'summary'      => $summary,
    ];
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
function branchScopeSql(mixed $alias = '', mixed $column = 'branch_id')
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
 * Is a company-wide user looking at every branch combined? Picked with
 * "All branches" in the header branch switcher; otherwise their lists
 * and reports follow the branch selected there (see viewBranchIds()).
 * Session-only, so each login starts on a single branch.
 */
function isViewingAllBranches(): bool
{
    return hasMultiBranch() && isCompanyWide() && !empty($_SESSION['view_all_branches']);
}

/**
 * Which branches LISTS, REPORTS and DASHBOARDS show — as opposed to
 * visibleBranchIds(), which is what a user may ACCESS. For a company-wide
 * user they differ: they can open any branch's record, but their lists
 * follow the branch selected in the header (or every branch with "All
 * branches"), so the figures on screen always describe one clear place.
 * Branch-scoped users see their own branches either way. null = no filter.
 */
function viewBranchIds()
{
    if (!hasMultiBranch()) {
        return null;
    }
    if (isCompanyWide()) {
        return isViewingAllBranches() ? null : [(int) activeBranchId()];
    }
    return visibleBranchIds();
}

/**
 * branchScopeSql()'s counterpart for lists/reports/dashboards: filters to
 * viewBranchIds(). Keep branchScopeSql() for guarding access to a single
 * record by id (view/edit/pay/void) — a company-wide admin looking at
 * Kasoa must still be able to open a Main sale linked from elsewhere.
 */
function branchViewSql(mixed $alias = '', mixed $column = 'branch_id')
{
    $branchIds = viewBranchIds();
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
 * What lists and reports are currently showing, for page labels: a branch
 * name, "All branches", or null when multi-branch isn't on.
 */
function viewingBranchLabel(): ?string
{
    if (!hasMultiBranch()) {
        return null;
    }
    $ids = viewBranchIds();
    if ($ids === null) {
        return 'All branches';
    }
    return count($ids) === 1 ? branchName($ids[0]) : 'Your branches';
}

/**
 * Same idea as branchScopeSql(), but for accounts specifically: a
 * branch-scoped user must still see every company-wide account
 * (branch_id IS NULL — a shared bank account, the suspense account)
 * alongside their own branch's. branchScopeSql()'s plain `IN (...)`
 * would wrongly hide every NULL-branch row, so accounts gets its own
 * OR-NULL variant instead of reusing it directly.
 *
 * Usage: identical to branchScopeSql() — [$scopeSql, $scopeParams] = accountBranchScopeSql('a');
 */
function accountBranchScopeSql(mixed $alias = '')
{
    if (!hasMultiBranch() || isCompanyWide()) {
        return ['', []];
    }
    $branchIds = array_column(userBranches(currentUser()['id']), 'id');
    if (empty($branchIds)) {
        return [' AND 1=0', []];
    }
    $prefix = $alias ? "$alias." : '';
    $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
    return [" AND ({$prefix}branch_id IS NULL OR {$prefix}branch_id IN ($placeholders))", $branchIds];
}

/**
 * Validates a submitted branch choice for a manually-created/edited
 * mobile_money or bank account (cash accounts are always system-managed
 * and branch-scoped — they never go through this). Empty/absent means
 * company-wide (NULL) — restricted to company-wide admins only: a
 * shared, every-branch-visible account is a bigger privilege than
 * managing your own branch's, so a branch-scoped admin can't create or
 * re-home an account into it. A specific branch must exist, be active,
 * and be one of the submitting user's own branches unless they're
 * company-wide (isCompanyWide()).
 *
 * @param mixed $currentBranchId On an edit, the account's branch_id
 *   *before* this submission (int, or null if already company-wide).
 *   Leave at the default when creating a new account. A branch-scoped
 *   admin can't newly grant company-wide scope (nor re-home an
 *   already-company-wide account into their own branch — same
 *   privilege, opposite direction), but editing some other field on
 *   an account that was already company-wide and stays that way isn't
 *   granting anything — that specific no-op case is let through so
 *   editing doesn't accidentally demote it.
 * @return array [int|null $branchId, string|null $error]
 */
function resolveAccountBranchChoice(Database $db, mixed $rawBranchId, mixed $currentBranchId = 'new-account')
{
    $rawBranchId = trim((string) $rawBranchId);
    $wasCompanyWide = ($currentBranchId === null);

    if ($rawBranchId === '') {
        if (hasMultiBranch() && !isCompanyWide() && !$wasCompanyWide) {
            return [null, 'Only a company-wide admin can create a company-wide account.'];
        }
        return [null, null];
    }

    $branchId = intval($rawBranchId);
    $branch = $db->fetchOne("SELECT id FROM branches WHERE id = ? AND is_active = 1", [$branchId]);
    if (!$branch) {
        return [null, 'Selected branch is invalid or inactive.'];
    }

    if (!isCompanyWide()) {
        // Re-homing an already-company-wide account into a specific
        // branch is the same privilege as creating one, from the other
        // direction — a disabled UI control prevents this in the normal
        // flow, but a crafted request could still POST it directly.
        if ($wasCompanyWide) {
            return [null, 'Only a company-wide admin can move a company-wide account to a single branch.'];
        }
        $ownBranchIds = array_column(userBranches(currentUser()['id']), 'id');
        if (!in_array($branchId, $ownBranchIds, true)) {
            return [null, 'You can only assign an account to your own branch.'];
        }
    }

    return [$branchId, null];
}

/**
 * Display name of any branch by id (e.g. for the Users list),
 * cached per-request per branch id.
 */
function branchName(mixed $branchId)
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
 * @param bool $companyWide   True for actions that aren't really about any one
 *                            branch (role/permission changes, settings) — stores
 *                            branch_id as NULL instead of the actor's current
 *                            branch, so branchScopeSql() excludes it for every
 *                            branch-scoped viewer, not just ones outside the
 *                            actor's branch at the time. Without this, a branch
 *                            admin could see company-wide administrative history
 *                            just because they happened to be active at their
 *                            own branch when someone made the change.
 */
function logAudit(mixed $action, mixed $entityType = null, mixed $entityId = null, mixed $details = [], mixed $companyWide = false)
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
            ($companyWide || !currentUser()) ? null : activeBranchId(),
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
function getBranchStock(mixed $productId, mixed $branchId = null)
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
function adjustBranchStock(Database $db, mixed $productId, mixed $branchId, mixed $delta)
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
function setBranchStock(Database $db, mixed $productId, mixed $branchId, mixed $newQuantity)
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
function recalcProductTotalStock(Database $db, mixed $productId)
{
    $total = $db->fetchOne(
        "SELECT COALESCE(SUM(quantity), 0) AS total FROM branch_stock WHERE product_id = ?",
        [$productId]
    );
    $totalValue = floatval($total['total'] ?? 0);
    $db->query("UPDATE products SET current_stock = ? WHERE id = ?", [$totalValue, $productId]);
    return $totalValue;
}

/**
 * Nets already-recorded customer-credit settlement transfers out of a
 * set of branch net positions (see customerCreditSettlementReport() in
 * ReportsController.php, §5.5 in ARCHITECTURE.md). Shared with
 * ExportController's CSV so the screen and the export can never drift.
 *
 * Only transfers with settlement_type = 'customer_credit_balancing'
 * whose settles_period_from/settles_period_to exactly match the
 * period being reported count — "Record Settlement" always writes
 * those to match the period it was launched from, so an exact match
 * is unambiguous (no risk of a settlement silently bleeding into an
 * adjacent period it wasn't actually for).
 *
 * Sign convention (see settlementMatrix()'s docblock for the full
 * cash trace): net > 0 means the branch took in more deposit cash
 * than it gave away in redemptions — it's cash-RICH and must PAY the
 * difference out. net < 0 means it gave away goods against other
 * branches' deposits without collecting matching cash — it's owed and
 * must RECEIVE. A settlement transfer moves real cash from the payer
 * (net > 0) to the receiver (net < 0): the payer's surplus shrinks
 * (net -= amount, toward zero), the receiver's deficit shrinks
 * (net += amount, toward zero).
 *
 * @param array $rows each with branch_id, branch_name, net, issued, redeemed, settled
 * @return array ['rows' => array (same shape, net/settled updated), 'total_settled' => float]
 */
function netOutSettledCustomerCredit(Database $db, array $rows, string $dateFrom, string $dateTo): array
{
    $settledTransfers = $db->fetchAll("
        SELECT
            fa.branch_id AS from_branch_id,
            ta.branch_id AS to_branch_id,
            t.amount
        FROM account_transfers t
        INNER JOIN accounts fa ON fa.id = t.from_account_id
        INNER JOIN accounts ta ON ta.id = t.to_account_id
        WHERE t.settlement_type = 'customer_credit_balancing'
          AND t.settles_period_from = ?
          AND t.settles_period_to   = ?
    ", [$dateFrom, $dateTo]);

    $rowsByBranch = [];
    foreach ($rows as $i => $row) {
        $rowsByBranch[$row['branch_id']] = $i;
    }

    $totalSettled = 0.0;
    foreach ($settledTransfers as $t) {
        // A transfer to/from a company-wide account (branch_id NULL,
        // e.g. a shared bank account) can't be attributed to one
        // branch's position — skip rather than guess.
        if ($t['from_branch_id'] === null || $t['to_branch_id'] === null) {
            continue;
        }
        $amount = (float) $t['amount'];
        $totalSettled += $amount;

        // from_account_id is where the settlement cash left FROM — the
        // payer, whose net was positive; its surplus shrinks.
        if (isset($rowsByBranch[$t['from_branch_id']])) {
            $rows[$rowsByBranch[$t['from_branch_id']]]['net']     -= $amount;
            $rows[$rowsByBranch[$t['from_branch_id']]]['settled'] += $amount;
        }
        // to_account_id is where it landed — the receiver, whose net
        // was negative; its deficit shrinks.
        if (isset($rowsByBranch[$t['to_branch_id']])) {
            $rows[$rowsByBranch[$t['to_branch_id']]]['net']     += $amount;
            $rows[$rowsByBranch[$t['to_branch_id']]]['settled'] += $amount;
        }
    }

    return ['rows' => $rows, 'total_settled' => $totalSettled];
}

/**
 * Turns a list of branch net positions into a suggested set of
 * branch-to-branch payments that would zero every branch out — a
 * classic debt-simplification allocation (largest payer settles with
 * largest receiver, repeat), NOT a claim about which specific deposit
 * funded which specific sale. Customer credit is pooled money (§5.5)
 * — this only allocates the already-computed *aggregate* net numbers,
 * it doesn't add per-transaction tracing.
 *
 * Sign convention — worked cash trace: customer deposits GHS 100 at
 * Branch A (A's till: +100 cash, company-wide liability: +100), then
 * spends it at Branch B (liability: -100, B hands over GHS 100 of
 * goods for zero cash). A is left holding GHS 100 of cash it never
 * earned via its own completed sale; B gave away GHS 100 of goods and
 * collected nothing. So A — issued (100) > redeemed (0), net = +100 —
 * is the one holding surplus cash and must PAY it to B — redeemed
 * (100) > issued (0), net = -100 — which must RECEIVE. In short:
 * net > 0 = pays, net < 0 = receives. (Do not flip this without
 * re-deriving the trace above — it's easy to get backwards, and it
 * was, in an earlier version of this report's UI copy.)
 *
 * @param array $rows each with branch_id, branch_name, net
 * @return array list of ['from_branch_id','from_branch_name','to_branch_id','to_branch_name','amount']
 */
function settlementMatrix(array $rows): array
{
    $payers = [];
    $receivers = [];
    foreach ($rows as $row) {
        if ($row['net'] > 0.01) {
            $payers[] = ['branch_id' => $row['branch_id'], 'branch_name' => $row['branch_name'], 'amount' => $row['net']];
        } elseif ($row['net'] < -0.01) {
            $receivers[] = ['branch_id' => $row['branch_id'], 'branch_name' => $row['branch_name'], 'amount' => -$row['net']];
        }
    }

    usort($payers, fn($a, $b) => $b['amount'] <=> $a['amount']);
    usort($receivers, fn($a, $b) => $b['amount'] <=> $a['amount']);

    $matrix = [];
    $p = 0;
    $r = 0;
    while ($p < count($payers) && $r < count($receivers)) {
        $pay = min($payers[$p]['amount'], $receivers[$r]['amount']);
        if ($pay > 0.01) {
            $matrix[] = [
                'from_branch_id'   => $payers[$p]['branch_id'],
                'from_branch_name' => $payers[$p]['branch_name'],
                'to_branch_id'     => $receivers[$r]['branch_id'],
                'to_branch_name'   => $receivers[$r]['branch_name'],
                'amount'           => round($pay, 2),
            ];
        }
        $payers[$p]['amount']    -= $pay;
        $receivers[$r]['amount'] -= $pay;
        if ($payers[$p]['amount'] <= 0.01) {
            $p++;
        }
        if ($receivers[$r]['amount'] <= 0.01) {
            $r++;
        }
    }

    return $matrix;
}
