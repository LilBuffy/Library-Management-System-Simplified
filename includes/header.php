<?php
$area       = $area ?? 'admin';
$isAdmin    = $area === 'admin';
$navActive  = $navActive ?? '';
$pageTitle  = $pageTitle ?? '';
$flash      = getFlash();
$cssVersion = @filemtime(APP_ROOT . '/assets/css/app.css') ?: 1;
$jsVersion  = @filemtime(APP_ROOT . '/assets/js/app.js') ?: 1;

if ($isAdmin) {
    $navGroups = [
        [null, [
            ['dashboard', 'dashboard.php', 'Overview', 'grid'],
        ]],
        ['Catalog', [
            ['books', 'books/list.php', 'Books', 'book'],
            ['book-add', 'books/add.php', 'Add a book', 'plus'],
        ]],
        ['Circulation', [
            ['borrow', 'transactions/borrow.php', 'Borrow', 'arrow-out'],
            ['return', 'transactions/return.php', 'Return', 'arrow-in'],
            ['history', 'transactions/history.php', 'History', 'clock'],
        ]],
        ['People', [
            ['members', 'users/list.php', 'Members', 'users'],
        ]],
        ['Reports', [
            ['reports', 'reports/index.php', 'Reports', 'file'],
        ]],
    ];
    $home       = 'dashboard.php';
    $roleLabel  = 'Staff';
    $personName = $_SESSION['admin_name'] ?? 'Administrator';
    $personSub  = 'Administrator';
    $logoutPath = 'logout.php';
} else {
    $navGroups = [
        [null, [
            ['home', 'user/dashboard.php', 'Home', 'home'],
            ['catalog', 'user/books.php', 'Catalog', 'book'],
            ['borrow', 'user/borrow.php', 'Borrow a book', 'arrow-out'],
        ]],
        ['My account', [
            ['loans', 'user/history.php', 'My loans', 'clock'],
            ['profile', 'user/profile.php', 'Profile', 'user'],
        ]],
    ];
    $home       = 'user/dashboard.php';
    $roleLabel  = 'Borrower portal';
    $personName = $_SESSION['user_name'] ?? 'Member';
    $personSub  = trim(($_SESSION['user_member_id'] ?? '') . ' ' . ucfirst($_SESSION['user_type'] ?? ''));
    $logoutPath = 'auth/logout.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<title><?= e($pageTitle !== '' ? $pageTitle . ' | ' . APP_NAME : APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>?v=<?= (int) $cssVersion ?>">
<script src="<?= e(url('assets/js/app.js')) ?>?v=<?= (int) $jsVersion ?>" defer></script>
</head>
<body class="area-<?= e($area) ?>">
<?= iconSprite() ?>
<a class="skip-link" href="#main">Skip to content</a>

<div class="shell">
    <header class="mobile-bar">
        <button type="button" class="menu-btn" id="menu-toggle" aria-controls="sidebar" aria-expanded="false" aria-label="Open menu">
            <?= icon('menu') ?>
        </button>
        <a class="mobile-brand" href="<?= e(url($home)) ?>"><?= e(APP_NAME) ?></a>
    </header>

    <div class="scrim" id="scrim" hidden></div>

    <aside class="sidebar" id="sidebar" aria-label="Main navigation">
        <a class="brand" href="<?= e(url($home)) ?>">
            <span class="brand-name"><?= e(APP_NAME) ?></span>
            <span class="brand-sub"><?= e($roleLabel) ?></span>
        </a>

        <nav class="nav">
            <?php foreach ($navGroups as $group): ?>
            <div class="nav-group">
                <?php if ($group[0]): ?><div class="nav-heading"><?= e($group[0]) ?></div><?php endif; ?>
                <?php foreach ($group[1] as $item): ?>
                <a class="nav-link" href="<?= e(url($item[1])) ?>"<?= $navActive === $item[0] ? ' aria-current="page"' : '' ?>>
                    <?= icon($item[3]) ?><span><?= e($item[2]) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-foot">
            <div class="person">
                <span class="person-name"><?= e($personName) ?></span>
                <span class="person-sub"><?= e($personSub) ?></span>
            </div>
            <?php if ($isAdmin): ?>
            <a class="nav-link" href="<?= e(url('account.php')) ?>"<?= $navActive === 'account' ? ' aria-current="page"' : '' ?>>
                <?= icon('shield') ?><span>Account</span>
            </a>
            <?php endif; ?>
            <form method="post" action="<?= e(url($logoutPath)) ?>">
                <button type="submit" class="nav-link nav-button"><?= icon('log-out') ?><span>Sign out</span></button>
            </form>
        </div>
    </aside>

    <main class="main" id="main" tabindex="-1">
        <div class="page">
            <?php if (!empty($backLink)): ?>
            <a class="back-link" href="<?= e(url($backLink[0])) ?>"><?= icon('arrow-left') ?><span><?= e($backLink[1]) ?></span></a>
            <?php endif; ?>

            <div class="page-head">
                <div class="page-head-text">
                    <h1 class="page-title"><?= e($pageTitle) ?></h1>
                    <?php if (!empty($pageLead)): ?><p class="page-lead"><?= e($pageLead) ?></p><?php endif; ?>
                </div>
                <?php if (!empty($pageActions)): ?><div class="page-actions"><?= $pageActions ?></div><?php endif; ?>
            </div>

            <?= $flash ? noticeHtml($flash['type'], $flash['message']) : '' ?>
