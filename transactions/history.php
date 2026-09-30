<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$pdo   = getDB();
$today = today();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    if (postStr('action') === 'pay_fine') {
        $id = (int) postStr('id', '0');
        $stmt = $pdo->prepare("UPDATE transactions SET fine_paid = 1 WHERE id = ? AND status = 'returned' AND fine_amount > 0 AND fine_paid = 0");
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) {
            logActivity('Fine collected', 'Loan #' . $id);
            flash('success', 'Fine marked as collected.');
        } else {
            flash('error', 'That fine was not found or is already marked as collected.');
        }
    }
    redirectSelf();
}

$legacy  = ['borrowed' => 'active'];
$stateIn = getStr('state', getStr('status'));
$state   = pickEnum($legacy[$stateIn] ?? $stateIn, ['active', 'overdue', 'returned']);
$search  = getStr('search');
$perPage = 20;

$searchSql    = '';
$searchParams = [];
if ($search !== '') {
    $searchSql    = 'WHERE (u.full_name LIKE ? OR u.member_id LIKE ? OR b.title LIKE ? OR b.author LIKE ? OR t.book_number LIKE ?)';
    $searchParams = array_fill(0, 5, likeTerm($search));
}

$countStmt = $pdo->prepare(
    "SELECT COUNT(*) AS total,
            SUM(t.status = 'borrowed') AS active,
            SUM(t.status = 'borrowed' AND t.due_date < ?) AS overdue,
            SUM(t.status = 'returned') AS returned
     FROM transactions t
     JOIN books b ON b.id = t.book_id
     LEFT JOIN users u ON u.id = t.user_id
     $searchSql"
);
$countStmt->execute(array_merge([$today], $searchParams));
$c        = $countStmt->fetch();
$cAll     = (int) $c['total'];
$cActive  = (int) ($c['active'] ?? 0);
$cOverdue = (int) ($c['overdue'] ?? 0);
$cReturned = (int) ($c['returned'] ?? 0);
$total    = ['' => $cAll, 'active' => $cActive, 'overdue' => $cOverdue, 'returned' => $cReturned][$state];

$where  = $searchSql;
$params = $searchParams;
$extra  = '';
if ($state === 'active') {
    $extra = "t.status = 'borrowed'";
} elseif ($state === 'overdue') {
    $extra    = "t.status = 'borrowed' AND t.due_date < ?";
    $params[] = $today;
} elseif ($state === 'returned') {
    $extra = "t.status = 'returned'";
}
if ($extra !== '') {
    $where .= ($where === '' ? 'WHERE ' : ' AND ') . $extra;
}

$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min(pageParam(), $totalPages);
$offset     = ($page - 1) * $perPage;

$nameSql = borrowerNameSql();
$idSql   = borrowerIdSql();
$stmt = $pdo->prepare(
    "SELECT t.id, t.book_number, t.borrow_date, t.due_date, t.return_date, t.status, t.fine_amount, t.fine_paid,
            b.title, b.author, $nameSql AS borrower, $idSql AS member_id
     FROM transactions t
     JOIN books b ON b.id = t.book_id
     LEFT JOIN users u ON u.id = t.user_id
     $where
     ORDER BY t.created_at DESC, t.id DESC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$area        = 'admin';
$navActive   = 'history';
$pageTitle   = 'History';
$pageLead    = 'Every loan, newest first.';
$pageActions = '<a class="btn btn-primary" href="' . e(url('transactions/borrow.php')) . '">' . icon('arrow-out') . 'Borrow a book</a>'
             . '<a class="btn" href="' . e(url('transactions/return.php')) . '">' . icon('arrow-in') . 'Return a book</a>';
require APP_ROOT . '/includes/header.php';
?>

<section class="panel" aria-label="Loan history">
    <form method="get" class="toolbar" role="search">
        <?php if ($state !== ''): ?><input type="hidden" name="state" value="<?= e($state) ?>"><?php endif; ?>
        <div class="search">
            <?= icon('search') ?>
            <input class="input" type="search" name="search" value="<?= e($search) ?>" placeholder="Search member, book title, author or number" aria-label="Search loans">
        </div>
        <button type="submit" class="btn">Search</button>
        <?php if ($search !== ''): ?><a class="btn btn-quiet" href="<?= e(queryString(['search' => '', 'page' => 1]) ?: url('transactions/history.php')) ?>">Clear</a><?php endif; ?>
    </form>

    <?= tabs([
        ''         => ['label' => 'All', 'count' => $cAll],
        'active'   => ['label' => 'On loan', 'count' => $cActive],
        'overdue'  => ['label' => 'Overdue', 'count' => $cOverdue],
        'returned' => ['label' => 'Returned', 'count' => $cReturned],
    ], $state, 'state') ?>

    <?php if ($search !== ''): ?>
    <p class="result-line"><strong><?= number_format($total) ?></strong> <?= plural($total, 'loan') ?> for &ldquo;<?= e($search) ?>&rdquo;</p>
    <?php endif; ?>

    <div class="table-wrap">
        <table class="table table-stack">
            <thead>
                <tr><th>Book</th><th>Borrower</th><th>Borrowed</th><th>Due</th><th>Returned</th><th>Status</th><th class="col-end">Fine</th></tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                <tr><td colspan="7" class="cell-empty"><?= $search !== '' || $state !== '' ? 'No loans match those filters.' : 'No loans have been recorded yet.' ?></td></tr>
                <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td class="cell-main">
                        <span class="cell-title"><?= highlight(truncate($r['title'], 60), $search) ?></span>
                        <span class="cell-sub"><span class="mono"><?= highlight($r['book_number'], $search) ?></span>, <?= highlight($r['author'], $search) ?></span>
                    </td>
                    <td data-label="Borrower">
                        <?= highlight($r['borrower'], $search) ?>
                        <?php if ($r['member_id'] !== ''): ?><span class="cell-sub mono"><?= highlight($r['member_id'], $search) ?></span><?php endif; ?>
                    </td>
                    <td data-label="Borrowed" class="nowrap"><?= e(fmtDate($r['borrow_date'])) ?></td>
                    <td data-label="Due" class="nowrap"><?= e(fmtDate($r['due_date'])) ?></td>
                    <td data-label="Returned" class="nowrap"><?= $r['return_date'] ? e(fmtDate($r['return_date'])) : '<span class="muted">Not yet</span>' ?></td>
                    <td data-label="Status"><?= loanBadge($r) ?></td>
                    <td data-label="Fine" class="col-end">
                        <?php if ((float) $r['fine_amount'] > 0): ?>
                            <?php if ($r['fine_paid']): ?>
                            <span class="num"><?= e(money($r['fine_amount'])) ?></span> <span class="muted small">paid</span>
                            <?php else: ?>
                            <span class="num fine-unpaid"><?= e(money($r['fine_amount'])) ?></span>
                            <form method="post" class="row-actions" data-no-lock>
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="pay_fine">
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <button type="submit" class="link-action small">Mark paid</button>
                            </form>
                            <?php endif; ?>
                        <?php else: ?>
                        <span class="muted">None</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= pagination($page, $totalPages) ?>
</section>

<?php require APP_ROOT . '/includes/footer.php'; ?>
