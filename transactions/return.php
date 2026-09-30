<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$pdo   = getDB();
$today = today();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePost('transactions/return.php');

    $txId       = (int) postStr('transaction_id', '0');
    $returnDate = postStr('return_date', $today);
    $finePaid   = isset($_POST['fine_paid']);

    if ($txId <= 0) {
        flash('error', 'Choose a loan to return.');
        redirect('transactions/return.php');
    }
    if (!validDate($returnDate) || $returnDate > $today) {
        flash('error', 'The return date must be a valid date that is not in the future.');
        redirect('transactions/return.php');
    }

    $nameSql = borrowerNameSql();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            "SELECT t.id, t.book_id, t.book_number, t.borrow_date, t.due_date, b.title, $nameSql AS borrower
             FROM transactions t
             JOIN books b ON b.id = t.book_id
             LEFT JOIN users u ON u.id = t.user_id
             WHERE t.id = ? AND t.status = 'borrowed'
             FOR UPDATE"
        );
        $stmt->execute([$txId]);
        $tx = $stmt->fetch();

        if (!$tx) {
            $pdo->rollBack();
            flash('error', 'That loan was not found or has already been returned.');
            redirect('transactions/return.php');
        }
        if ($returnDate < $tx['borrow_date']) {
            $pdo->rollBack();
            flash('error', 'The return date cannot be before the borrow date (' . fmtDate($tx['borrow_date']) . ').');
            redirect('transactions/return.php');
        }

        $fine = calcFine($tx['due_date'], $returnDate);
        $paid = $fine > 0 && $finePaid ? 1 : 0;

        $pdo->prepare("UPDATE transactions SET return_date = ?, status = 'returned', fine_amount = ?, fine_paid = ? WHERE id = ?")
            ->execute([$returnDate, $fine, $paid, $txId]);
        $pdo->prepare("UPDATE books SET status = 'Available' WHERE id = ?")->execute([$tx['book_id']]);
        $pdo->commit();

        logActivity('Book returned', $tx['borrower'] . ' returned "' . $tx['title'] . '" (' . $tx['book_number'] . ')' . ($fine > 0 ? ', fine ' . money($fine) . ($paid ? ' paid' : ' unpaid') : ''));
        $msg = '"' . $tx['title'] . '" returned and available again.';
        if ($fine > 0) {
            $msg .= ' Fine ' . money($fine) . ($paid ? ', collected.' : ', not yet collected.');
        }
        flash('success', $msg);
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Return failed: ' . $ex->getMessage());
        flash('error', 'The return could not be recorded. Please try again.');
    }
    redirect('transactions/return.php');
}

$nameSql = borrowerNameSql();
$idSql   = borrowerIdSql();
$loans = $pdo->query(
    "SELECT t.id, t.book_number, t.borrow_date, t.due_date, b.title, $nameSql AS borrower, $idSql AS member_id
     FROM transactions t
     JOIN books b ON b.id = t.book_id
     LEFT JOIN users u ON u.id = t.user_id
     WHERE t.status = 'borrowed'
     ORDER BY t.due_date ASC, t.id ASC"
)->fetchAll();

$find      = getStr('find');
$area      = 'admin';
$navActive = 'return';
$pageTitle = 'Return a book';
$pageLead  = 'Books on loan, oldest due date first. Choose Return to record it coming back.';
require APP_ROOT . '/includes/header.php';
?>

<section class="panel" aria-label="Books on loan">
    <?php if (!$loans): ?>
    <div class="empty">
        <p class="empty-title">No books are on loan.</p>
        <p>When a book is borrowed it will be listed here until it comes back.</p>
        <a class="btn btn-primary" href="<?= e(url('transactions/borrow.php')) ?>">Borrow a book</a>
    </div>
    <?php else: ?>
    <div class="toolbar">
        <div class="search">
            <?= icon('search') ?>
            <input class="input" type="search" value="<?= e($find) ?>" data-filter="loans-table" placeholder="Filter by book, number or borrower" aria-label="Filter loans">
        </div>
        <span class="panel-note" role="status" data-filter-count><?= count($loans) ?> <?= plural(count($loans), 'loan') ?></span>
    </div>
    <div class="table-wrap">
        <table class="table table-stack" id="loans-table">
            <thead>
                <tr><th>Book</th><th>Borrower</th><th>Due</th><th>Status</th><th class="col-end">Fine so far</th><th><span class="sr-only">Action</span></th></tr>
            </thead>
            <tbody>
                <?php foreach ($loans as $l):
                    $late = daysOverdue($l['due_date']); ?>
                <tr data-row>
                    <td class="cell-main">
                        <span class="cell-title"><?= e($l['title']) ?></span>
                        <span class="cell-sub"><span class="mono"><?= e($l['book_number']) ?></span></span>
                    </td>
                    <td data-label="Borrower">
                        <?= e($l['borrower']) ?>
                        <?php if ($l['member_id'] !== ''): ?><span class="cell-sub mono"><?= e($l['member_id']) ?></span><?php endif; ?>
                    </td>
                    <td data-label="Due" class="nowrap"><?= e(fmtDate($l['due_date'])) ?></td>
                    <td data-label="Status"><span class="badge badge-<?= $late > 0 ? 'overdue' : 'active' ?>"><?= e(dueNote($l)) ?></span></td>
                    <td data-label="Fine so far" class="col-end num"><?= $late > 0 ? e(money($late * FINE_PER_DAY)) : '<span class="muted">None</span>' ?></td>
                    <td class="cell-actions col-end">
                        <button type="button" class="btn btn-sm" data-return-open
                                data-tx="<?= (int) $l['id'] ?>"
                                data-book="<?= e($l['title'] . ' (' . $l['book_number'] . ')') ?>"
                                data-borrower="<?= e($l['borrower']) ?>"
                                data-due="<?= e($l['due_date']) ?>"
                                data-borrowed="<?= e($l['borrow_date']) ?>">Return</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr data-no-match hidden><td colspan="6" class="cell-empty">No loans match that filter.</td></tr>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<dialog class="dialog" id="return-dialog" aria-labelledby="return-title" data-rate="<?= e(FINE_PER_DAY) ?>" data-currency="<?= e(CURRENCY_SYMBOL) ?>">
    <form method="post" action="<?= e(url('transactions/return.php')) ?>">
        <?= csrfField() ?>
        <input type="hidden" name="transaction_id" value="" data-ret-tx>
        <h2 class="dialog-title" id="return-title">Record a return</h2>
        <dl class="defs">
            <dt>Book</dt><dd data-ret-book></dd>
            <dt>Borrower</dt><dd data-ret-borrower></dd>
            <dt>Due date</dt><dd data-ret-due></dd>
        </dl>
        <div class="field">
            <label class="label" for="return_date">Returned on</label>
            <input class="input" type="date" id="return_date" name="return_date" max="<?= e($today) ?>" value="<?= e($today) ?>" required data-ret-date>
        </div>
        <div class="fine-line" data-ret-fine hidden><span data-ret-fine-text></span></div>
        <label class="check" data-ret-paid hidden>
            <input type="checkbox" name="fine_paid" value="1">
            <span>The fine has been collected</span>
        </label>
        <div class="dialog-actions">
            <button type="button" class="btn btn-quiet" data-dialog-close>Cancel</button>
            <button type="submit" class="btn btn-primary">Confirm return</button>
        </div>
    </form>
</dialog>

<?php require APP_ROOT . '/includes/footer.php'; ?>
