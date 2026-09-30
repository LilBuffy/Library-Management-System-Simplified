<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireUserLogin();

$pdo   = getDB();
$uid   = (int) $_SESSION['user_id'];
$today = today();

if (isset($_GET['lookup'])) {
    jsonOut(['results' => searchBooks($pdo, getStr('lookup'))]);
}

$minDue = addDays($today, 1);
$maxDue = addDays($today, MAX_LOAN_DAYS);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ? AND status = 'borrowed'");
$countStmt->execute([$uid]);
$activeCount = (int) $countStmt->fetchColumn();
$atLimit     = $activeCount >= MAX_ACTIVE_BORROWS;

$errors  = [];
$bookId  = 0;
$dueDate = addDays($today, BORROW_DAYS);
$notes   = '';
$info    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $bookId  = (int) postStr('book_id', '0');
    $dueDate = postStr('due_date');
    $notes   = postStr('notes');

    if ($bookId <= 0) {
        $errors[] = 'Choose the book you want to borrow.';
    }
    if (!validDate($dueDate)) {
        $errors[] = 'Enter a valid return date.';
    } elseif ($dueDate < $minDue || $dueDate > $maxDue) {
        $errors[] = 'Choose a return date between ' . fmtDate($minDue) . ' and ' . fmtDate($maxDue) . '.';
    }
    if ($atLimit) {
        $errors[] = 'You have reached the limit of ' . MAX_ACTIVE_BORROWS . ' books on loan. Return one to borrow another.';
    }
    if (mb_strlen($notes) > 500) {
        $errors[] = 'Notes can be at most 500 characters.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $bs = $pdo->prepare('SELECT id, book_number, title, status FROM books WHERE id = ? FOR UPDATE');
            $bs->execute([$bookId]);
            $book = $bs->fetch();

            $cs = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ? AND status = 'borrowed'");
            $cs->execute([$uid]);

            if (!$book) {
                $errors[] = 'That book is no longer in the catalog.';
            } elseif ($book['status'] !== 'Available') {
                $errors[] = 'Sorry, ' . $book['book_number'] . ' was just borrowed by someone else. Choose another copy.';
            } elseif ((int) $cs->fetchColumn() >= MAX_ACTIVE_BORROWS) {
                $errors[] = 'You have reached the limit of ' . MAX_ACTIVE_BORROWS . ' books on loan.';
            }

            if ($errors) {
                $pdo->rollBack();
            } else {
                $pdo->prepare("INSERT INTO transactions (user_id, book_id, book_number, borrow_date, due_date, status, notes) VALUES (?, ?, ?, ?, ?, 'borrowed', ?)")
                    ->execute([$uid, $book['id'], $book['book_number'], $today, $dueDate, $notes ?: null]);
                $pdo->prepare("UPDATE books SET status = 'Borrowed' WHERE id = ?")->execute([$book['id']]);
                $pdo->commit();

                logActivity('Book borrowed', $_SESSION['user_name'] . ' borrowed "' . $book['title'] . '" (' . $book['book_number'] . ')', $_SESSION['user_username']);
                flash('success', 'You borrowed "' . $book['title'] . '". Please return it by ' . fmtDate($dueDate, 'F j, Y') . '.');
                redirect('user/history.php');
            }
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Member borrow failed: ' . $ex->getMessage());
            $errors[] = 'The loan could not be recorded. Please try again.';
        }
    }
} elseif (isset($_GET['book'])) {
    $bookId = (int) getStr('book', '0');
    if ($bookId > 0 && !bookPickerItem($pdo, $bookId)) {
        $bookId = 0;
        $info   = 'That copy is not available to borrow right now. Search for another copy below.';
    }
}

$selectedBook = bookPickerItem($pdo, $bookId);

$mineStmt = $pdo->prepare(
    "SELECT t.book_number, t.borrow_date, t.due_date, b.title
     FROM transactions t JOIN books b ON b.id = t.book_id
     WHERE t.user_id = ? AND t.status = 'borrowed'
     ORDER BY t.due_date ASC"
);
$mineStmt->execute([$uid]);
$mine = $mineStmt->fetchAll();

$area      = 'user';
$navActive = 'borrow';
$pageTitle = 'Borrow a book';
$pageLead  = 'You have ' . $activeCount . ' of ' . MAX_ACTIVE_BORROWS . ' loans in use.';
require APP_ROOT . '/includes/header.php';
?>

<?= $info ? noticeHtml('info', $info) : '' ?>
<?= $atLimit && $_SERVER['REQUEST_METHOD'] !== 'POST' ? noticeHtml('info', 'You have reached the limit of ' . MAX_ACTIVE_BORROWS . ' books on loan. Return one to borrow another.') : '' ?>
<?= errorsHtml($errors) ?>

<section class="panel">
    <form method="post" class="panel-body">
        <?= csrfField() ?>
        <div class="form-grid cols-2">
            <div class="span-all">
                <?= renderPicker([
                    'id' => 'book', 'name' => 'book_id', 'label' => 'Book', 'required' => true,
                    'endpoint' => url('user/borrow.php?lookup='),
                    'placeholder' => 'Book number, title or author',
                    'hint' => 'Available copies are listed first.',
                    'error' => 'Choose a book to continue.',
                    'selected' => $selectedBook,
                ]) ?>
            </div>
            <div class="field">
                <label class="label" for="due_date">Return by</label>
                <input class="input" type="date" id="due_date" name="due_date" value="<?= e($dueDate) ?>" min="<?= e($minDue) ?>" max="<?= e($maxDue) ?>" required>
                <span class="hint">Up to <?= (int) MAX_LOAN_DAYS ?> days. Late returns cost <?= e(money(FINE_PER_DAY)) ?> a day.</span>
            </div>
            <div class="field">
                <label class="label" for="notes">Notes <span class="opt">(optional)</span></label>
                <textarea class="textarea" id="notes" name="notes" rows="2" maxlength="500"><?= e($notes) ?></textarea>
            </div>
        </div>
        <div class="actions">
            <button type="submit" class="btn btn-primary"<?= $atLimit ? ' disabled' : '' ?>>Confirm borrow</button>
            <a class="btn btn-quiet" href="<?= e(url('user/books.php')) ?>">Browse the catalog</a>
        </div>
    </form>
</section>

<?php if ($mine): ?>
<section class="panel" aria-labelledby="mine-h">
    <div class="panel-head"><h2 class="panel-title" id="mine-h">Your current loans</h2></div>
    <div class="table-wrap">
        <table class="table table-stack">
            <thead><tr><th>Book</th><th>Borrowed</th><th>Due</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($mine as $m): $late = daysOverdue($m['due_date']); ?>
                <tr>
                    <td class="cell-main">
                        <span class="cell-title"><?= e($m['title']) ?></span>
                        <span class="cell-sub mono"><?= e($m['book_number']) ?></span>
                    </td>
                    <td data-label="Borrowed" class="nowrap"><?= e(fmtDate($m['borrow_date'])) ?></td>
                    <td data-label="Due" class="nowrap"><?= e(fmtDate($m['due_date'])) ?></td>
                    <td data-label="Status"><span class="badge badge-<?= $late > 0 ? 'overdue' : 'active' ?>"><?= e(dueNote($m)) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php require APP_ROOT . '/includes/footer.php'; ?>
