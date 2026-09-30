<?php

define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_WINDOW_MINUTES', 15);
define('DUMMY_HASH', '$2b$12$lKNrEgI10vDIH8MXmLywduzDsTJ9W/0.4rLvxual4ZCQqxphMhWTu');

function startSession()
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('lms_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL === '' ? '/' : BASE_URL,
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

function isLoggedIn()
{
    return !empty($_SESSION['admin_id']);
}

function isUserLoggedIn()
{
    return !empty($_SESSION['user_id']);
}

function isAnyLoggedIn()
{
    return isLoggedIn() || isUserLoggedIn();
}

function clearAdminSession()
{
    foreach (['admin_id', 'admin_username', 'admin_name'] as $key) {
        unset($_SESSION[$key]);
    }
}

function clearUserSession()
{
    foreach (['user_id', 'user_username', 'user_name', 'user_member_id', 'user_type'] as $key) {
        unset($_SESSION[$key]);
    }
}

function signInAdmin(array $admin)
{
    session_regenerate_id(true);
    clearUserSession();
    $_SESSION['admin_id']       = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_name']     = $admin['full_name'];
}

function signInUser(array $user)
{
    session_regenerate_id(true);
    clearAdminSession();
    $_SESSION['user_id']        = $user['id'];
    $_SESSION['user_username']  = $user['username'];
    $_SESSION['user_name']      = $user['full_name'];
    $_SESSION['user_member_id'] = $user['member_id'];
    $_SESSION['user_type']      = $user['membership_type'];
}

function requireLogin()
{
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

function requireUserLogin()
{
    if (!isUserLoggedIn()) {
        redirect('auth/login.php');
    }
    $stmt = getDB()->prepare('SELECT full_name, membership_type, status FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $u = $stmt->fetch();
    if (!$u || $u['status'] !== 'active') {
        clearUserSession();
        flash('error', 'This account is no longer active. Please contact the library.');
        redirect('auth/login.php');
    }
    $_SESSION['user_name'] = $u['full_name'];
    $_SESSION['user_type'] = $u['membership_type'];
}

function csrfToken()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrfField()
{
    return '<input type="hidden" name="csrf" value="' . e(csrfToken()) . '">';
}

function csrfValid()
{
    $token = $_POST['csrf'] ?? '';
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

function requireCsrf($fallbackPath = null)
{
    if (csrfValid()) {
        return;
    }
    flash('error', 'That form expired. Please try again.');
    if ($fallbackPath) {
        redirect($fallbackPath);
    }
    redirectSelf();
}

function requirePost($fallbackPath)
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        redirect($fallbackPath);
    }
    requireCsrf($fallbackPath);
}

function clientIp()
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function loginBlocked($scope, $identifier)
{
    try {
        $stmt = getDB()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE scope = ? AND identifier = ? AND ip = ?
               AND attempted_at > (NOW() - INTERVAL ' . (int) LOGIN_WINDOW_MINUTES . ' MINUTE)'
        );
        $stmt->execute([$scope, mb_substr(strtolower($identifier), 0, 100), clientIp()]);
        return (int) $stmt->fetchColumn() >= LOGIN_MAX_ATTEMPTS;
    } catch (Throwable $e) {
        return false;
    }
}

function recordLoginFailure($scope, $identifier)
{
    try {
        $pdo = getDB();
        $pdo->prepare('INSERT INTO login_attempts (scope, identifier, ip) VALUES (?, ?, ?)')
            ->execute([$scope, mb_substr(strtolower($identifier), 0, 100), clientIp()]);
        $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
    } catch (Throwable $e) {
        error_log('login_attempts write failed: ' . $e->getMessage());
    }
}

function clearLoginFailures($scope, $identifier)
{
    try {
        getDB()->prepare('DELETE FROM login_attempts WHERE scope = ? AND identifier = ? AND ip = ?')
            ->execute([$scope, mb_substr(strtolower($identifier), 0, 100), clientIp()]);
    } catch (Throwable $e) {
        error_log('login_attempts clear failed: ' . $e->getMessage());
    }
}

function logActivity($action, $details = '', $by = '')
{
    try {
        $actor = $by ?: ($_SESSION['admin_username'] ?? $_SESSION['user_username'] ?? 'system');
        getDB()->prepare('INSERT INTO activity_log (action, details, performed_by) VALUES (?, ?, ?)')
            ->execute([$action, $details, $actor]);
    } catch (Throwable $e) {
        error_log('activity_log write failed: ' . $e->getMessage());
    }
}

function flash($type, $message)
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash()
{
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}
