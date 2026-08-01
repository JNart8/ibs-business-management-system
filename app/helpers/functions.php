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
