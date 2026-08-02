<?php

/**
 * Auth Controller
 * Handles login, logout, and session management
 */

if (!defined('APP_START')) {
    die('Direct access not permitted');
}

/** @var string $path Request path provided by public/index.php */
/** @var string $method HTTP method provided by public/index.php */

$db = Database::getInstance();

// ── Use $path set by the router ───────────────────────────────
$action = trim($path, '/');  // 'login' or 'logout'

switch ($action) {
    case 'login':
        $method === 'POST'
            ? processLogin($db)
            : showLogin();
        break;

    case 'logout':
        processLogout();
        break;

    default:
        redirect(BASE_URL . '/login');
}

// ============================================================
// FUNCTIONS
// ============================================================

/**
 * Show login page
 */
function showLogin()
{
    if (isLoggedIn()) {
        redirect((currentUser()['role'] ?? '') === 'cashier' ? BASE_URL . '/pos' : BASE_URL . '/');
    }
    $pageTitle = 'Login';
    include APP_PATH . '/views/auth/login.php';
}

/**
 * Process login form submission
 */
function processLogin($db)
{
    if (isLoggedIn()) {
        redirect((currentUser()['role'] ?? '') === 'cashier' ? BASE_URL . '/pos' : BASE_URL . '/');
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password']        ?? '';

    if (empty($username) || empty($password)) {
        redirect(BASE_URL . '/login', 'error', 'Please enter your username and password.');
    }

    // ── Look up user by username only (active or not) ─────────
    // We fetch first so we can apply per-user lockout
    $user = $db->fetchOne(
        "SELECT * FROM users WHERE username = ? LIMIT 1",
        [$username]
    );

    // ── Wrong username entirely — generic message, no lockout ─
    if (!$user) {
        redirect(BASE_URL . '/login', 'error', 'Invalid username or password.');
    }

    // ── Account inactive ──────────────────────────────────────
    if (!$user['is_active']) {
        redirect(BASE_URL . '/login', 'error', 'This account has been deactivated. Contact your administrator.');
    }

    // ── Per-user lockout check ────────────────────────────────
    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
        $minutesLeft = ceil((strtotime($user['locked_until']) - time()) / 60);
        redirect(
            BASE_URL . '/login',
            'error',
            "Account locked due to too many failed attempts. Try again in {$minutesLeft} minute(s)."
        );
    }

    // ── Wrong password — increment THIS user's counter ────────
    if (!password_verify($password, $user['password_hash'])) {
        $newAttempts = intval($user['login_attempts']) + 1;

        if ($newAttempts >= MAX_LOGIN_ATTEMPTS) {
            // Lock the account
            $lockedUntil = date('Y-m-d H:i:s', time() + LOGIN_TIMEOUT_MINUTES * 60);
            $db->query(
                "UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?",
                [$newAttempts, $lockedUntil, $user['id']]
            );
            redirect(
                BASE_URL . '/login',
                'error',
                "Too many failed attempts. Account locked for " . LOGIN_TIMEOUT_MINUTES . " minute(s)."
            );
        }

        $remaining = MAX_LOGIN_ATTEMPTS - $newAttempts;
        $db->query(
            "UPDATE users SET login_attempts = ? WHERE id = ?",
            [$newAttempts, $user['id']]
        );
        redirect(
            BASE_URL . '/login',
            'error',
            "Invalid username or password. {$remaining} attempt(s) remaining."
        );
    }

    // ── Success — reset counter and clear lockout ─────────────
    $db->query(
        "UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = ?",
        [$user['id']]
    );

    session_regenerate_id(true);

    // A fresh random token per login. Storing it in both the DB and this
    // session, and checking the two match on every request (see
    // enforceSingleSession() in functions.php), means whichever session
    // logged in most recently wins — any earlier session for this user
    // gets signed out automatically on its next request.
    $sessionToken = bin2hex(random_bytes(32));
    $db->query("UPDATE users SET session_token = ? WHERE id = ?", [$sessionToken, $user['id']]);

    $_SESSION['user_id']       = $user['id'];
    $_SESSION['username']      = $user['username'];
    $_SESSION['full_name']     = $user['full_name'];
    $_SESSION['role']          = $user['role'];
    $_SESSION['logged_in']     = true;
    $_SESSION['session_token'] = $sessionToken;

    // Update last login (only if column exists — safe to remove if not)
    try {
        $db->query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
    } catch (Exception $e) {
        // Column may not exist yet — silently skip
    }

    $redirect = $_SESSION['redirect_after_login'] ?? '';
    unset($_SESSION['redirect_after_login']);

    // Safety net — only redirect to internal app pages, never assets or external URLs
    $isValid = !empty($redirect)
        && strpos($redirect, BASE_URL) === 0
        && !preg_match('/\.(ico|png|jpg|gif|css|js|svg|woff|woff2|ttf)$/i', $redirect);

    $defaultLanding = $user['role'] === 'cashier' ? BASE_URL . '/pos' : BASE_URL . '/';
    $isCashierDashboard = $user['role'] === 'cashier'
        && in_array(rtrim($redirect, '/'), [rtrim(BASE_URL, '/'), BASE_URL . '/dashboard'], true);

    redirect(
        $isValid && !$isCashierDashboard ? $redirect : $defaultLanding,
        'success',
        'Welcome back, ' . $user['full_name'] . '!'
    );
}

/**
 * Log the user out
 */
function processLogout()
{
    if (!empty($_SESSION['user_id'])) {
        try {
            Database::getInstance()->query(
                "UPDATE users SET session_token = NULL WHERE id = ?",
                [$_SESSION['user_id']]
            );
        } catch (Exception $e) {
            // Non-fatal — the session is being destroyed either way.
        }
    }

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

    header('Location: ' . BASE_URL . '/login');
    exit;
}
