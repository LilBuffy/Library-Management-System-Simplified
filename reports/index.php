<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$pdo    = getDB();
$titles = ['all' => 'All books', 'available' => 'Available books', 'borrowed' => 'Books on loan'];
$filter = pickEnum(getStr('filter'), array_keys($titles), 'all');

$where = $filter === 'available' ? "WHERE status = 'Available'" : ($filter === 'borrowed' ? "WHERE status = 'Borrowed'" : '');
$books = $pdo->query("SELECT book_number, title, copyright_year, edition, author, course_code, status FROM books $where ORDER BY book_number ASC")->fetchAll();

$counts    = $pdo->query('SELECT status, COUNT(*) FROM books GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$available = (int) ($counts['Available'] ?? 0);
$borrowed  = (int) ($counts['Borrowed'] ?? 0);
$all       = $available + $borrowed;

$area        = 'admin';
$navActive   = 'reports';
$pageTitle   = 'Reports';
$pageLead    = 'Choose which books to include, then print the list.';
$pageActions = '<button type="button" class="btn btn-primary" data-print>' . icon('printer') . 'Print report</button>';
require APP_ROOT . '/includes/header.php';
?>

<section class="panel">
    <?= tabs([
        'all'       => ['label' => 'All books', 'count' => $all],
        'available' => ['label' => 'Available', 'count' => $available],
        'borrowed'  => ['label' => 'On loan', 'count' => $borrowed],
    ], $filter, 'filter') ?>

    <div class="report-sheet">
        <header class="report-head">
            <div class="report-kicker"><?= e(APP_NAME) ?></div>
            <h2 class="report-title"><?= e($titles[$filter]) ?></h2>
            <div class="report-meta">
                Generated <?= e(date('F j, Y, g:i A')) ?>.
                <?= number_format(count($books)) ?> <?= plural(count($books), 'book') ?> listed.
                Catalog totals: <?= number_format($available) ?> available, <?= number_format($borrowed) ?> on loan.
            </div>
        </header>

        <div class="table-wrap">
            <table class="table report-table">
                <thead>
                    <tr><th>No.</th><th>Book number</th><th>Title</th><th>Author</th><th>Year</th><th>Edition</th><th>Course</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php if (!$books): ?>
                    <tr><td colspan="8" class="cell-empty">No books in this list.</td></tr>
                    <?php else: foreach ($books as $i => $b): ?>
                    <tr>
                        <td class="num muted"><?= $i + 1 ?></td>
                        <td><span class="mono"><?= e($b['book_number']) ?></span></td>
                        <td><?= e($b['title']) ?></td>
                        <td><?= e($b['author']) ?></td>
                        <td><?= e($b['copyright_year'] ?: '') ?></td>
                        <td><?= e($b['edition'] ?: '') ?></td>
                        <td class="mono"><?= e($b['course_code'] ?: '') ?></td>
                        <td><?= e($b['status'] === 'Available' ? 'Available' : 'On loan') ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php require APP_ROOT . '/includes/footer.php'; ?>
