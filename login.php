<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error    = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $username = postStr('username');
    $password = postRaw('password');

    if ($username === '' || $password === '') {
        $error = 'Enter your username and password.';
    } elseif (loginBlocked('admin', $username)) {
        $error = 'Too many failed attempts. Wait ' . LOGIN_WINDOW_MINUTES . ' minutes and try again.';
    } else {
        $stmt = getDB()->prepare('SELECT id, username, password, full_name FROM admins WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if (password_verify($password, $admin['password'] ?? DUMMY_HASH) && $admin) {
            clearLoginFailures('admin', $username);
            signInAdmin($admin);
            logActivity('Sign in', 'Administrator signed in', $admin['username']);
            redirect('dashboard.php');
        }
        recordLoginFailure('admin', $username);
        $error = 'Incorrect username or password.';
    }
}

$flash     = getFlash();
$pageTitle = 'Staff sign in';
$topLink   = ['auth/login.php', 'Borrower sign in'];
require APP_ROOT . '/includes/auth_header.php';
?>
    <main class="auth-card">
        <h1 class="auth-title">Staff sign in</h1>
        <p class="auth-lead">Manage the catalog, members and loans.</p>

        <?= $flash ? noticeHtml($flash['type'], $flash['message']) : '' ?>
        <?= $error ? errorsHtml([$error]) : '' ?>

        <form method="post">
            <?= csrfField() ?>
            <div class="field">
                <label class="label" for="username">Username</label>
                <input class="input" type="text" id="username" name="username" value="<?= e($username) ?>"
                       autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>
            </div>
            <div class="field">
                <label class="label" for="password">Password</label>
                <input class="input" type="password" id="password" name="password" autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Sign in</button>
        </form>

        <div class="auth-alt">
            <span>Borrowing a book? <a href="<?= e(url('auth/login.php')) ?>">Borrower sign in</a></span>
        </div>
    </main>
<?php require APP_ROOT . '/includes/auth_footer.php'; ?>
