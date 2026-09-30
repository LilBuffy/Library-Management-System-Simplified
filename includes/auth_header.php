<?php
$pageTitle  = $pageTitle ?? '';
$cssVersion = @filemtime(APP_ROOT . '/assets/css/app.css') ?: 1;
$jsVersion  = @filemtime(APP_ROOT . '/assets/js/app.js') ?: 1;
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
<body class="auth-page">
<?= iconSprite() ?>
<div class="auth">
    <header class="auth-top">
        <a class="auth-brand" href="<?= e(url('index.php')) ?>"><?= e(APP_NAME) ?></a>
        <?php if (!empty($topLink)): ?><a class="small" href="<?= e(url($topLink[0])) ?>"><?= e($topLink[1]) ?></a><?php endif; ?>
    </header>
