<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$pdo    = getDB();
$errors = [];
$id     = (int) getStr('id', '0');

$stmt = $pdo->prepare('SELECT * FROM books WHERE id = ?');
$stmt->execute([$id]);
$book = $stmt->fetch();

if (!$book) {
    flash('error', 'That book could not be found.');
    redirect('books/list.php');
}

$form   = [];
$fields = ['book_number', 'title', 'author', 'copyright_year', 'edition', 'course_code'];
foreach ($fields as $key) {
    $form[$key] = (string) ($book[$key] ?? '');
}
$locked = $book['status'] === 'Borrowed';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    foreach ($fields as $key) {
        $form[$key] = postStr($key);
    }
    $form['book_number'] = strtoupper($form['book_number']);

    if ($locked) {
        $errors[] = 'This book is on loan. Record its return before editing it.';
    }
    if ($form['book_number'] === '') {
        $errors[] = 'Enter a book number.';
    } elseif (mb_strlen($form['book_number']) > 20) {
        $errors[] = 'Book number can be at most 20 characters.';
    }
    if ($form['title'] === '') {
        $errors[] = 'Enter the title.';
    }
    if ($form['author'] === '') {
        $errors[] = 'Enter the author.';
    }

    if (!$errors) {
        $chk = $pdo->prepare('SELECT 1 FROM books WHERE book_number = ? AND id <> ?');
        $chk->execute([$form['book_number'], $id]);
        if ($chk->fetchColumn()) {
            $errors[] = 'Book number ' . $form['book_number'] . ' belongs to another book.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE books SET book_number = ?, title = ?, copyright_year = ?, edition = ?, author = ?, course_code = ? WHERE id = ?')
                ->execute([
                    $form['book_number'], $form['title'], $form['copyright_year'] ?: null,
                    $form['edition'] ?: null, $form['author'], $form['course_code'] ?: null, $id,
                ]);
            $pdo->prepare('UPDATE transactions SET book_number = ? WHERE book_id = ?')->execute([$form['book_number'], $id]);
            $pdo->commit();
            logActivity('Book updated', $form['title'] . ' (' . $form['book_number'] . ')');
            flash('success', 'Saved changes to "' . $form['title'] . '".');
            redirect('books/list.php');
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Book update failed: ' . $ex->getMessage());
            $errors[] = 'The changes could not be saved. Please try again.';
        }
    }
}

$area        = 'admin';
$navActive   = 'books';
$pageTitle   = 'Edit book';
$pageLead    = $book['book_number'] . ', ' . ($locked ? 'currently on loan' : 'available');
$backLink    = ['books/list.php', 'Back to books'];
$submitLabel = 'Save changes';
require APP_ROOT . '/includes/header.php';
?>

<?php if ($locked && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
<?= noticeHtml('info', 'This book is on loan, so it cannot be edited until it is returned.') ?>
<?php endif; ?>
<?= errorsHtml($errors) ?>
<section class="panel">
    <?php require __DIR__ . '/_form.php'; ?>
</section>

<?php require APP_ROOT . '/includes/footer.php'; ?>
