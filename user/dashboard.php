<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireUserLogin();

$pdo = getDB();
$uid = (int) $_SESSION['user_id'];

$loansStmt = $pdo->prepare(
    "SELECT t.id, t.book_number, t.borrow_date, t.due_date, t.status, b.title, b.author
     FROM transactions t
     JOIN books b ON b.id = t.book_id
     WHERE t.user_id = ? AND t.status = 'borrowed'
     ORDER BY t.due_date ASC, t.id ASC"
);
$loansStmt->execute([$uid]);
$loans = $loansStmt->fetchAll();

$overdue = 0;
foreach ($loans as $l) {
    if (daysOverdue($l['due_date']) > 0) {
        $overdue++;
    }
}

$fineStmt = $pdo->prepare("SELECT COALESCE(SUM(fine_amount), 0) FROM transactions WHERE user_id = ? AND status = 'returned' AND fine_amount > 0 AND fine_paid = 0");
$fineStmt->execute([$uid]);
$unpaid = (float) $fineStmt->fetchColumn();

$recentStmt = $pdo->prepare(
    "SELECT t.id, t.book_number, t.borrow_date, t.due_date, t.return_date, t.status, b.title
     FROM transactions t
     JOIN books b ON b.id = t.book_id
     WHERE t.user_id = ?
     ORDER BY t.created_at DESC, t.id DESC
     LIMIT 5"
);
$recentStmt->execute([$uid]);
$recent = $recentStmt->fetchAll();

$area      = 'user';
$navActive = 'home';
$pageTitle = 'Hello, ' . (explode(' ', trim($_SESSION['user_name'] ?? ''))[0] ?: 'there');
$pageLead  = 'Here is what you have borrowed and what is coming due.';
require APP_ROOT . '/includes/header.php';
?>

<form method="get" action="<?= e(url('user/books.php')) ?>" class="toolbar panel" role="search">
    <div class="search">
        <?= icon('search') ?>
        <input class="input" type="search" name="search" placeholder="Search the catalog by title, author or number" aria-label="Search the catalog">
    </div>
    <button type="submit" class="btn btn-primary">Search</button>
</form>

<div class="figures cols-3">
    <div class="figure">
        <span class="figure-value"><?= count($loans) ?></span>
        <span class="figure-label">On loan (limit <?= (int) MAX_ACTIVE_BORROWS ?>)</span>
    </div>
    <div class="figure<?= $overdue > 0 ? ' is-alert' : '' ?>">
        <span class="figure-value"><?= $overdue ?></span>
        <span class="figure-label">Overdue</span>
    </div>
    <div class="figure<?= $unpaid > 0 ? ' is-alert' : '' ?>">
        <span class="figure-value"><?= e(money($unpaid)) ?></span>
        <span class="figure-label">Unpaid fines</span>
    </div>
</div>

<section class="panel" aria-labelledby="loans-h">
    <div class="panel-head">
        <h2 class="panel-title" id="loans-h">On loan now</h2>
        <a class="small" href="<?= e(url('user/borrow.php')) ?>">Borrow a book</a>
    </div>
    <?php if (!$loans): ?>
    <div class="empty">
        <p class="empty-title">You have nothing on loan.</p>
        <p>Find a book in the catalog and borrow it in a few clicks.</p>
        <a class="btn btn-primary" href="<?= e(url('user/books.php?status=Available')) ?>">Browse available books</a>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table table-stack">
            <thead><tr><th>Book</th><th>Borrowed</th><th>Due</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($loans as $l): $late = daysOverdue($l['due_date']); ?>
                <tr>
                    <td class="cell-main">
                        <span class="cell-title"><?= e($l['title']) ?></span>
                        <span class="cell-sub"><?= e($l['author']) ?>, <span class="mono"><?= e($l['book_number']) ?></span></span>
                    </td>
                    <td data-label="Borrowed" class="nowrap"><?= e(fmtDate($l['borrow_date'])) ?></td>
                    <td data-label="Due" class="nowrap"><?= e(fmtDate($l['due_date'])) ?></td>
                    <td data-label="Status"><span class="badge badge-<?= $late > 0 ? 'overdue' : 'active' ?>"><?= e(dueNote($l)) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($overdue > 0): ?>
    <p class="result-line">Return overdue books to the library desk. Fines are <?= e(money(FINE_PER_DAY)) ?> for each day past the due date.</p>
    <?php endif; ?>
    <?php endif; ?>
</section>

<?php if ($recent): ?>
<section class="panel" aria-labelledby="recent-h">
    <div class="panel-head">
        <h2 class="panel-title" id="recent-h">Recent activity</h2>
        <a class="small" href="<?= e(url('user/history.php')) ?>">All loans</a>
    </div>
    <div class="table-wrap">
        <table class="table table-stack">
            <thead><tr><th>Book</th><th>Borrowed</th><th>Returned</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($recent as $r): ?>
                <tr>
                    <td class="cell-main">
                        <span class="cell-title"><?= e(truncate($r['title'], 56)) ?></span>
                        <span class="cell-sub mono"><?= e($r['book_number']) ?></span>
                    </td>
                    <td data-label="Borrowed" class="nowrap"><?= e(fmtDate($r['borrow_date'])) ?></td>
                    <td data-label="Returned" class="nowrap"><?= $r['return_date'] ? e(fmtDate($r['return_date'])) : '<span class="muted">Not yet</span>' ?></td>
                    <td data-label="Status"><?= loanBadge($r) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php require APP_ROOT . '/includes/footer.php'; ?>
