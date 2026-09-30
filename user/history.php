<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireUserLogin();

$pdo     = getDB();
$uid     = (int) $_SESSION['user_id'];
$today   = today();
$state   = pickEnum(getStr('state'), ['active', 'overdue', 'returned']);
$perPage = 20;

$cs = $pdo->prepare(
    "SELECT COUNT(*) AS total,
            SUM(status = 'borrowed') AS active,
            SUM(status = 'borrowed' AND due_date < ?) AS overdue,
            SUM(status = 'returned') AS returned
     FROM transactions WHERE user_id = ?"
);
$cs->execute([$today, $uid]);
$c         = $cs->fetch();
$cAll      = (int) $c['total'];
$cActive   = (int) ($c['active'] ?? 0);
$cOverdue  = (int) ($c['overdue'] ?? 0);
$cReturned = (int) ($c['returned'] ?? 0);
$total     = ['' => $cAll, 'active' => $cActive, 'overdue' => $cOverdue, 'returned' => $cReturned][$state];

$where  = 't.user_id = ?';
$params = [$uid];
if ($state === 'active') {
    $where .= " AND t.status = 'borrowed'";
} elseif ($state === 'overdue') {
    $where   .= " AND t.status = 'borrowed' AND t.due_date < ?";
    $params[] = $today;
} elseif ($state === 'returned') {
    $where .= " AND t.status = 'returned'";
}

$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min(pageParam(), $totalPages);
$offset     = ($page - 1) * $perPage;

$stmt = $pdo->prepare(
    "SELECT t.id, t.book_number, t.borrow_date, t.due_date, t.return_date, t.status, t.fine_amount, t.fine_paid, b.title, b.author
     FROM transactions t
     JOIN books b ON b.id = t.book_id
     WHERE $where
     ORDER BY t.created_at DESC, t.id DESC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$area        = 'user';
$navActive   = 'loans';
$pageTitle   = 'My loans';
$pageLead    = 'Everything you have borrowed, newest first.';
$pageActions = '<a class="btn btn-primary" href="' . e(url('user/borrow.php')) . '">' . icon('arrow-out') . 'Borrow a book</a>';
require APP_ROOT . '/includes/header.php';
?>

<section class="panel" aria-label="My loans">
    <?= tabs([
        ''         => ['label' => 'All', 'count' => $cAll],
        'active'   => ['label' => 'On loan', 'count' => $cActive],
        'overdue'  => ['label' => 'Overdue', 'count' => $cOverdue],
        'returned' => ['label' => 'Returned', 'count' => $cReturned],
    ], $state, 'state') ?>

    <div class="table-wrap">
        <table class="table table-stack">
            <thead>
                <tr><th>Book</th><th>Borrowed</th><th>Due</th><th>Returned</th><th>Status</th><th class="col-end">Fine</th></tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                <tr><td colspan="6" class="cell-empty"><?= $state !== '' ? 'Nothing here.' : 'You have not borrowed any books yet.' ?></td></tr>
                <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td class="cell-main">
                        <span class="cell-title"><?= e(truncate($r['title'], 60)) ?></span>
                        <span class="cell-sub"><?= e($r['author']) ?>, <span class="mono"><?= e($r['book_number']) ?></span></span>
                    </td>
                    <td data-label="Borrowed" class="nowrap"><?= e(fmtDate($r['borrow_date'])) ?></td>
                    <td data-label="Due" class="nowrap"><?= e(fmtDate($r['due_date'])) ?></td>
                    <td data-label="Returned" class="nowrap"><?= $r['return_date'] ? e(fmtDate($r['return_date'])) : '<span class="muted">Not yet</span>' ?></td>
                    <td data-label="Status"><?= loanBadge($r) ?></td>
                    <td data-label="Fine" class="col-end">
                        <?php if ((float) $r['fine_amount'] > 0): ?>
                        <span class="num<?= $r['fine_paid'] ? '' : ' fine-unpaid' ?>"><?= e(money($r['fine_amount'])) ?></span>
                        <span class="muted small"><?= $r['fine_paid'] ? 'paid' : 'unpaid' ?></span>
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
