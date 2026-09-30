<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$pdo   = getDB();
$today = today();

if (isset($_GET['lookup'])) {
    jsonOut(['results' => searchBooks($pdo, getStr('lookup'))]);
}

if (isset($_GET['member'])) {
    $q = getStr('member');
    $results = [];
    if ($q !== '') {
        $like = likeTerm($q);
        $stmt = $pdo->prepare(
            "SELECT u.id, u.member_id, u.full_name, u.membership_type,
                    (SELECT COUNT(*) FROM transactions t WHERE t.user_id = u.id AND t.status = 'borrowed') AS on_loan
             FROM users u
             WHERE u.status = 'active' AND (u.full_name LIKE ? OR u.member_id LIKE ? OR u.username LIKE ?)
             ORDER BY u.full_name ASC
             LIMIT 10"
        );
        $stmt->execute([$like, $like, $like]);
        foreach ($stmt->fetchAll() as $u) {
            $results[] = [
                'id'       => (int) $u['id'],
                'code'     => $u['member_id'],
                'title'    => $u['full_name'],
                'meta'     => ucfirst($u['membership_type']),
                'note'     => (int) $u['on_loan'] > 0 ? $u['on_loan'] . ' on loan' : '',
                'disabled' => false,
            ];
        }
    }
    jsonOut(['results' => $results]);
}

$errors     = [];
$bookId     = 0;
$borrowerId = 0;
$borrowDate = $today;
$dueDate    = addDays($today, BORROW_DAYS);
$notes      = '';
$info       = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $bookId     = (int) postStr('book_id', '0');
    $borrowerId = (int) postStr('borrower_id', '0');
    $borrowDate = postStr('borrow_date', $today);
    $dueDate    = postStr('due_date');
    $notes      = postStr('notes');

    if ($bookId <= 0) {
        $errors[] = 'Choose the book being borrowed.';
    }
    if ($borrowerId <= 0) {
        $errors[] = 'Choose the member borrowing it.';
    }
    if (!validDate($borrowDate)) {
        $errors[] = 'Enter a valid borrow date.';
    } elseif ($borrowDate > $today) {
        $errors[] = 'The borrow date cannot be in the future.';
    }
    if (!validDate($dueDate)) {
        $errors[] = 'Enter a valid due date.';
    } elseif (validDate($borrowDate) && $dueDate < $borrowDate) {
        $errors[] = 'The due date cannot be before the borrow date.';
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

            $us = $pdo->prepare("SELECT id, full_name FROM users WHERE id = ? AND status = 'active'");
            $us->execute([$borrowerId]);
            $member = $us->fetch();

            if (!$book) {
                $errors[] = 'That book is no longer in the catalog.';
            } elseif ($book['status'] !== 'Available') {
                $errors[] = $book['book_number'] . ' is already on loan. Choose another copy.';
            }
            if (!$member) {
                $errors[] = 'That member was not found or is not active.';
            }

            if ($errors) {
                $pdo->rollBack();
            } else {
                $pdo->prepare("INSERT INTO transactions (user_id, book_id, book_number, borrow_date, due_date, status, notes) VALUES (?, ?, ?, ?, ?, 'borrowed', ?)")
                    ->execute([$member['id'], $book['id'], $book['book_number'], $borrowDate, $dueDate, $notes ?: null]);
                $pdo->prepare("UPDATE books SET status = 'Borrowed' WHERE id = ?")->execute([$book['id']]);
                $pdo->commit();

                logActivity('Book borrowed', $member['full_name'] . ' borrowed "' . $book['title'] . '" (' . $book['book_number'] . ')');
                flash('success', '"' . $book['title'] . '" issued to ' . $member['full_name'] . '. Due ' . fmtDate($dueDate) . '.');
                redirect('transactions/borrow.php');
            }
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Borrow failed: ' . $ex->getMessage());
            $errors[] = 'The loan could not be recorded. Please try again.';
        }
    }
} elseif (isset($_GET['book'])) {
    $bookId = (int) getStr('book', '0');
    if ($bookId > 0 && !bookPickerItem($pdo, $bookId)) {
        $bookId = 0;
        $info   = 'That copy is not available to borrow right now.';
    }
}

$selectedBook = bookPickerItem($pdo, $bookId);
$selectedMember = null;
if ($borrowerId > 0) {
    $ms = $pdo->prepare("SELECT id, member_id, full_name, membership_type FROM users WHERE id = ? AND status = 'active'");
    $ms->execute([$borrowerId]);
    if ($m = $ms->fetch()) {
        $selectedMember = ['id' => (int) $m['id'], 'code' => $m['member_id'], 'title' => $m['full_name'], 'meta' => ucfirst($m['membership_type'])];
    }
}

$area       = 'admin';
$navActive  = 'borrow';
$pageTitle  = 'Borrow a book';
$pageLead   = 'Find the book and the member, confirm the dates, and issue it.';
require APP_ROOT . '/includes/header.php';
?>

<?= $info ? noticeHtml('info', $info) : '' ?>
<?= errorsHtml($errors) ?>

<section class="panel">
    <form method="post" class="panel-body">
        <?= csrfField() ?>
        <div class="form-grid cols-2">
            <?= renderPicker([
                'id' => 'book', 'name' => 'book_id', 'label' => 'Book', 'required' => true,
                'endpoint' => url('transactions/borrow.php?lookup='),
                'placeholder' => 'Book number, title or author',
                'hint' => 'Available copies are listed first.',
                'error' => 'Choose a book to continue.',
                'selected' => $selectedBook,
            ]) ?>
            <?= renderPicker([
                'id' => 'member', 'name' => 'borrower_id', 'label' => 'Member', 'required' => true,
                'endpoint' => url('transactions/borrow.php?member='),
                'placeholder' => 'Name, member ID or username',
                'hint' => 'Only active members can borrow.',
                'error' => 'Choose a member to continue.',
                'selected' => $selectedMember,
            ]) ?>
            <div class="field">
                <label class="label" for="borrow_date">Borrow date</label>
                <input class="input" type="date" id="borrow_date" name="borrow_date" value="<?= e($borrowDate) ?>" max="<?= e($today) ?>" data-days="<?= (int) BORROW_DAYS ?>" required>
            </div>
            <div class="field">
                <label class="label" for="due_date">Due date</label>
                <input class="input" type="date" id="due_date" name="due_date" value="<?= e($dueDate) ?>" min="<?= e($borrowDate) ?>" required>
                <span class="hint">Fines are <?= e(money(FINE_PER_DAY)) ?> for each day past the due date.</span>
            </div>
            <div class="field span-all">
                <label class="label" for="notes">Notes <span class="opt">(optional)</span></label>
                <textarea class="textarea" id="notes" name="notes" rows="2" maxlength="500"><?= e($notes) ?></textarea>
            </div>
        </div>
        <div class="actions">
            <button type="submit" class="btn btn-primary">Issue book</button>
            <a class="btn btn-quiet" href="<?= e(url('transactions/return.php')) ?>">Go to returns</a>
        </div>
    </form>
</section>

<?php require APP_ROOT . '/includes/footer.php'; ?>
