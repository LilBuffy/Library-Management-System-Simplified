<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireLogin();

$pdo   = getDB();
$today = today();

$books = $pdo->query("SELECT COUNT(*) AS total, SUM(status = 'Available') AS available, SUM(status = 'Borrowed') AS borrowed FROM books")->fetch();
$totalBooks = (int) $books['total'];
$onLoan     = (int) ($books['borrowed'] ?? 0);
$members    = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();

$overdueCount = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE status = 'borrowed' AND due_date < ?");
$overdueCount->execute([$today]);
$overdueCount = (int) $overdueCount->fetchColumn();

$nameSql = borrowerNameSql();
$idSql   = borrowerIdSql();

$overdueStmt = $pdo->prepare(
    "SELECT t.id, t.book_number, t.due_date, b.title, $nameSql AS borrower, $idSql AS member_id
     FROM transactions t
     JOIN books b ON b.id = t.book_id
     LEFT JOIN users u ON u.id = t.user_id
     WHERE t.status = 'borrowed' AND t.due_date < ?
     ORDER BY t.due_date ASC
     LIMIT 8"
);
$overdueStmt->execute([$today]);
$overdue = $overdueStmt->fetchAll();

$recent = $pdo->query(
    "SELECT t.id, t.book_number, t.borrow_date, t.due_date, t.status, b.title, $nameSql AS borrower
     FROM transactions t
     JOIN books b ON b.id = t.book_id
     LEFT JOIN users u ON u.id = t.user_id
     ORDER BY t.created_at DESC, t.id DESC
     LIMIT 8"
)->fetchAll();

$topBooks = $pdo->query(
    'SELECT b.book_number, b.title, COUNT(t.id) AS loans
     FROM transactions t
     JOIN books b ON b.id = t.book_id
     GROUP BY b.id, b.book_number, b.title
     ORDER BY loans DESC, b.title ASC
     LIMIT 5'
)->fetchAll();

$activity = $pdo->query('SELECT action, details, performed_by, created_at FROM activity_log ORDER BY id DESC LIMIT 6')->fetchAll();

$since = addDays($today, -6);
$bs = $pdo->prepare('SELECT borrow_date, COUNT(*) FROM transactions WHERE borrow_date >= ? GROUP BY borrow_date');
$bs->execute([$since]);
$borrowedByDay = $bs->fetchAll(PDO::FETCH_KEY_PAIR);
$rs = $pdo->prepare('SELECT return_date, COUNT(*) FROM transactions WHERE return_date >= ? GROUP BY return_date');
$rs->execute([$since]);
$returnedByDay = $rs->fetchAll(PDO::FETCH_KEY_PAIR);

$labels = $borrows = $returns = [];
for ($i = 0; $i < 7; $i++) {
    $d         = addDays($since, $i);
    $labels[]  = fmtDate($d, 'M j');
    $borrows[] = (int) ($borrowedByDay[$d] ?? 0);
    $returns[] = (int) ($returnedByDay[$d] ?? 0);
}

$area       = 'admin';
$navActive  = 'dashboard';
$pageTitle  = 'Overview';
$pageLead   = fmtDate($today, 'l, F j');
require APP_ROOT . '/includes/header.php';
?>

<div class="figures">
    <div class="figure">
        <span class="figure-value"><?= number_format($totalBooks) ?></span>
        <span class="figure-label">Books in catalog</span>
    </div>
    <div class="figure">
        <span class="figure-value"><?= number_format($onLoan) ?></span>
        <span class="figure-label">On loan</span>
    </div>
    <div class="figure<?= $overdueCount > 0 ? ' is-alert' : '' ?>">
        <span class="figure-value"><?= number_format($overdueCount) ?></span>
        <span class="figure-label">Overdue</span>
    </div>
    <div class="figure">
        <span class="figure-value"><?= number_format($members) ?></span>
        <span class="figure-label">Active members</span>
    </div>
</div>

<div class="split">
    <div>
        <section class="panel" aria-labelledby="overdue-h">
            <div class="panel-head">
                <h2 class="panel-title" id="overdue-h">Overdue</h2>
                <?php if ($overdueCount > 8): ?><span class="panel-note">Showing 8 of <?= number_format($overdueCount) ?>, oldest first</span><?php endif; ?>
            </div>
            <?php if (!$overdue): ?>
            <div class="empty">
                <p class="empty-title">Nothing is overdue.</p>
                <p>Every book on loan is still within its due date.</p>
            </div>
            <?php else: ?>
            <div class="table-wrap">
                <table class="table table-stack">
                    <thead>
                        <tr><th>Book</th><th>Borrower</th><th>Late</th><th class="col-end">Fine so far</th><th><span class="sr-only">Action</span></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($overdue as $o): $late = daysOverdue($o['due_date']); ?>
                        <tr>
                            <td class="cell-main">
                                <span class="cell-title"><?= e(truncate($o['title'], 48)) ?></span>
                                <span class="cell-sub"><span class="mono"><?= e($o['book_number']) ?></span></span>
                            </td>
                            <td data-label="Borrower">
                                <?= e($o['borrower']) ?>
                                <?php if ($o['member_id'] !== ''): ?><span class="cell-sub mono"><?= e($o['member_id']) ?></span><?php endif; ?>
                            </td>
                            <td data-label="Late" class="nowrap"><span class="badge badge-overdue"><?= $late ?> <?= plural($late, 'day') ?></span></td>
                            <td data-label="Fine so far" class="col-end num"><?= e(money($late * FINE_PER_DAY)) ?></td>
                            <td class="cell-actions col-end">
                                <a class="btn btn-sm" href="<?= e(url('transactions/return.php?find=' . rawurlencode($o['book_number']))) ?>">Return</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>

        <section class="panel" aria-labelledby="chart-h">
            <div class="panel-head">
                <h2 class="panel-title" id="chart-h">Last 7 days</h2>
            </div>
            <div class="chart"><?= activityChart($labels, $borrows, $returns) ?></div>
            <div class="chart-legend">
                <span><i class="key key-solid"></i>Borrowed</span>
                <span><i class="key"></i>Returned</span>
            </div>
        </section>

        <section class="panel" aria-labelledby="recent-h">
            <div class="panel-head">
                <h2 class="panel-title" id="recent-h">Recent loans</h2>
                <a class="small" href="<?= e(url('transactions/history.php')) ?>">All history</a>
            </div>
            <?php if (!$recent): ?>
            <div class="empty">
                <p class="empty-title">No loans yet.</p>
                <p>Loans appear here as soon as the first book is borrowed.</p>
                <a class="btn btn-primary" href="<?= e(url('transactions/borrow.php')) ?>">Borrow a book</a>
            </div>
            <?php else: ?>
            <div class="table-wrap">
                <table class="table table-stack">
                    <thead>
                        <tr><th>Book</th><th>Borrower</th><th>Borrowed</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $r): ?>
                        <tr>
                            <td class="cell-main">
                                <span class="cell-title"><?= e(truncate($r['title'], 48)) ?></span>
                                <span class="cell-sub"><span class="mono"><?= e($r['book_number']) ?></span></span>
                            </td>
                            <td data-label="Borrower"><?= e($r['borrower']) ?></td>
                            <td data-label="Borrowed" class="nowrap"><?= e(fmtDate($r['borrow_date'])) ?></td>
                            <td data-label="Status"><?= loanBadge($r) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
    </div>

    <div>
        <section class="panel" aria-labelledby="top-h">
            <div class="panel-head"><h2 class="panel-title" id="top-h">Most borrowed</h2></div>
            <?php if (!$topBooks): ?>
            <div class="empty"><p>No borrowing data yet.</p></div>
            <?php else: ?>
            <ol class="ranked">
                <?php foreach ($topBooks as $i => $t): ?>
                <li>
                    <span class="rank"><?= $i + 1 ?></span>
                    <span class="ranked-body">
                        <span class="ranked-title"><?= e(truncate($t['title'], 44)) ?></span>
                        <span class="cell-sub mono"><?= e($t['book_number']) ?></span>
                    </span>
                    <span class="ranked-count"><?= (int) $t['loans'] ?> <?= plural((int) $t['loans'], 'loan') ?></span>
                </li>
                <?php endforeach; ?>
            </ol>
            <?php endif; ?>
        </section>

        <section class="panel" aria-labelledby="log-h">
            <div class="panel-head"><h2 class="panel-title" id="log-h">Activity</h2></div>
            <?php if (!$activity): ?>
            <div class="empty"><p>No activity recorded yet.</p></div>
            <?php else: ?>
            <ul class="log">
                <?php foreach ($activity as $a): ?>
                <li>
                    <span class="log-action"><?= e($a['action']) ?></span>
                    <?php if ($a['details']): ?><span class="log-detail"><?= e(truncate($a['details'], 90)) ?></span><?php endif; ?>
                    <span class="log-meta"><?= e($a['performed_by'] ?: 'system') ?>, <?= e(fmtDate($a['created_at'], 'M j, g:i A')) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php require APP_ROOT . '/includes/footer.php'; ?>
