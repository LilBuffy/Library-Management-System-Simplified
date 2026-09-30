<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (isUserLoggedIn()) {
    redirect('user/dashboard.php');
}

$error    = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $username = postStr('username');
    $password = postRaw('password');

    if ($username === '' || $password === '') {
        $error = 'Enter your username and password.';
    } elseif (loginBlocked('member', $username)) {
        $error = 'Too many failed attempts. Wait ' . LOGIN_WINDOW_MINUTES . ' minutes and try again.';
    } else {
        $stmt = getDB()->prepare("SELECT id, username, password, full_name, member_id, membership_type FROM users WHERE username = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (password_verify($password, $user['password'] ?? DUMMY_HASH) && $user) {
            clearLoginFailures('member', $username);
            signInUser($user);
            logActivity('Sign in', 'Member signed in', $user['username']);
            redirect('user/dashboard.php');
        }
        recordLoginFailure('member', $username);
        $error = 'Incorrect username or password, or the account is not active.';
    }
}

$flash     = getFlash();
$pageTitle = 'Sign in';
$topLink   = ['login.php', 'Staff sign in'];
require APP_ROOT . '/includes/auth_header.php';
?>
    <main class="auth-card">
        <h1 class="auth-title">Sign in</h1>
        <p class="auth-lead">Search the catalog, borrow a book and see what is due.</p>

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
            <span>New here? <a href="<?= e(url('auth/register.php')) ?>">Create an account</a></span>
        </div>
    </main>
<?php require APP_ROOT . '/includes/auth_footer.php'; ?>
