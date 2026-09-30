<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$pdo     = getDB();
$search  = getStr('search');
$status  = pickEnum(getStr('status'), ['Available', 'Borrowed']);
$perPage = 20;

$searchSql    = '';
$searchParams = [];
if ($search !== '') {
    $searchSql    = 'WHERE (book_number LIKE ? OR title LIKE ? OR author LIKE ? OR course_code LIKE ? OR copyright_year LIKE ? OR edition LIKE ?)';
    $searchParams = array_fill(0, 6, likeTerm($search));
}

$countStmt = $pdo->prepare("SELECT status, COUNT(*) FROM books $searchSql GROUP BY status");
$countStmt->execute($searchParams);
$counts    = $countStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$available = (int) ($counts['Available'] ?? 0);
$borrowed  = (int) ($counts['Borrowed'] ?? 0);
$all       = $available + $borrowed;
$total     = $status === 'Available' ? $available : ($status === 'Borrowed' ? $borrowed : $all);

$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min(pageParam(), $totalPages);
$offset     = ($page - 1) * $perPage;

$where  = $searchSql;
$params = $searchParams;
if ($status !== '') {
    $where .= ($where === '' ? 'WHERE ' : ' AND ') . 'status = ?';
    $params[] = $status;
}
$stmt = $pdo->prepare("SELECT * FROM books $where ORDER BY book_number ASC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$books = $stmt->fetchAll();

$area        = 'admin';
$navActive   = 'books';
$pageTitle   = 'Books';
$pageLead    = 'The full catalog. Search by number, title, author, year, edition or course code.';
$pageActions = '<a class="btn btn-primary" href="' . e(url('books/add.php')) . '">' . icon('plus') . 'Add a book</a>';
require APP_ROOT . '/includes/header.php';
?>

<section class="panel" aria-label="Book records">
    <form method="get" class="toolbar" role="search">
        <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
        <div class="search">
            <?= icon('search') ?>
            <input class="input" type="search" name="search" value="<?= e($search) ?>" placeholder="Search the catalog" aria-label="Search the catalog">
        </div>
        <button type="submit" class="btn">Search</button>
        <?php if ($search !== ''): ?><a class="btn btn-quiet" href="<?= e(queryString(['search' => '', 'page' => 1]) ?: url('books/list.php')) ?>">Clear</a><?php endif; ?>
    </form>

    <?= tabs([
        ''          => ['label' => 'All', 'count' => $all],
        'Available' => ['label' => 'Available', 'count' => $available],
        'Borrowed'  => ['label' => 'On loan', 'count' => $borrowed],
    ], $status, 'status') ?>

    <?php if ($search !== ''): ?>
    <p class="result-line"><strong><?= number_format($total) ?></strong> <?= plural($total, 'result') ?> for &ldquo;<?= e($search) ?>&rdquo;</p>
    <?php endif; ?>

    <div class="table-wrap">
        <table class="table table-stack">
            <thead>
                <tr><th>Number</th><th>Title and author</th><th>Year and edition</th><th>Course</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr>
            </thead>
            <tbody>
                <?php if (!$books): ?>
                <tr><td colspan="6" class="cell-empty">
                    <?= $search !== '' || $status !== '' ? 'No books match those filters.' : 'The catalog is empty. Add the first book to get started.' ?>
                </td></tr>
                <?php else: foreach ($books as $b):
                    $yearEdition = trim(($b['copyright_year'] ?: '') . ($b['copyright_year'] && $b['edition'] ? ', ' : '') . ($b['edition'] ?: '')); ?>
                <tr>
                    <td class="cell-main"><span class="mono"><?= highlight($b['book_number'], $search) ?></span></td>
                    <td data-label="Title">
                        <span class="cell-title"><?= highlight($b['title'], $search) ?></span>
                        <span class="cell-sub"><?= highlight($b['author'], $search) ?></span>
                    </td>
                    <td data-label="Year and edition"><?= $yearEdition !== '' ? highlight($yearEdition, $search) : '<span class="muted">Not set</span>' ?></td>
                    <td data-label="Course"><?= $b['course_code'] ? '<span class="mono">' . highlight($b['course_code'], $search) . '</span>' : '<span class="muted">None</span>' ?></td>
                    <td data-label="Status"><?= bookBadge($b['status']) ?></td>
                    <td class="cell-actions col-end">
                        <span class="row-actions">
                            <?php if ($b['status'] === 'Available'): ?>
                            <a class="btn btn-sm btn-quiet" href="<?= e(url('transactions/borrow.php?book=' . (int) $b['id'])) ?>">Issue</a>
                            <?php endif; ?>
                            <a class="btn btn-sm btn-quiet" href="<?= e(url('books/edit.php?id=' . (int) $b['id'])) ?>" aria-label="Edit <?= e($b['title']) ?>"><?= icon('edit') ?><span>Edit</span></a>
                            <form method="post" action="<?= e(url('books/delete.php')) ?>"
                                  data-confirm="<?= e('Delete "' . $b['title'] . '" (' . $b['book_number'] . ')? Its loan history is deleted with it. This cannot be undone.') ?>"
                                  data-confirm-title="Delete this book?" data-confirm-label="Delete book">
                                <?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-quiet" aria-label="Delete <?= e($b['title']) ?>"><?= icon('trash') ?><span>Delete</span></button>
                            </form>
                        </span>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= pagination($page, $totalPages) ?>
</section>

<?php require APP_ROOT . '/includes/footer.php'; ?>
