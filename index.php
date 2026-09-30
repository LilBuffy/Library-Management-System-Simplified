<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}
if (isUserLoggedIn()) {
    redirect('user/dashboard.php');
}

$row       = getDB()->query("SELECT COUNT(*) AS total, SUM(status = 'Available') AS available FROM books")->fetch();
$total     = (int) ($row['total'] ?? 0);
$available = (int) ($row['available'] ?? 0);

$pageTitle = '';
$topLink   = ['login.php', 'Staff sign in'];
require APP_ROOT . '/includes/auth_header.php';
?>
    <main class="landing">
        <h1 class="landing-title">Find a book. Borrow it. Return it on time.</h1>
        <p class="landing-lead">
            Search the school library catalog, borrow available titles and keep track of your due dates. Staff manage the collection, members and returns from the same system.
        </p>
        <div class="landing-actions">
            <a class="btn btn-primary" href="<?= e(url('auth/login.php')) ?>">Sign in</a>
            <a class="btn" href="<?= e(url('auth/register.php')) ?>">Create an account</a>
        </div>

        <?php if ($total > 0): ?>
        <ul class="landing-facts">
            <li>
                <span class="landing-fact-value"><?= number_format($total) ?></span>
                <span class="landing-fact-label">books in the catalog</span>
            </li>
            <li>
                <span class="landing-fact-value"><?= number_format($available) ?></span>
                <span class="landing-fact-label">available to borrow today</span>
            </li>
        </ul>
        <?php endif; ?>
    </main>
<?php require APP_ROOT . '/includes/auth_footer.php'; ?>
