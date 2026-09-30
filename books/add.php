<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$pdo    = getDB();
$errors = [];
$form   = ['book_number' => '', 'title' => '', 'author' => '', 'copyright_year' => '', 'edition' => '', 'course_code' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    foreach (array_keys($form) as $key) {
        $form[$key] = postStr($key);
    }
    $form['book_number'] = strtoupper($form['book_number']);

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
        $chk = $pdo->prepare('SELECT 1 FROM books WHERE book_number = ?');
        $chk->execute([$form['book_number']]);
        if ($chk->fetchColumn()) {
            $errors[] = 'Book number ' . $form['book_number'] . ' is already in the catalog.';
        }
    }

    if (!$errors) {
        try {
            $pdo->prepare("INSERT INTO books (book_number, title, copyright_year, edition, author, course_code, status) VALUES (?, ?, ?, ?, ?, ?, 'Available')")
                ->execute([
                    $form['book_number'], $form['title'], $form['copyright_year'] ?: null,
                    $form['edition'] ?: null, $form['author'], $form['course_code'] ?: null,
                ]);
            logActivity('Book added', $form['title'] . ' (' . $form['book_number'] . ')');
            flash('success', 'Added "' . $form['title'] . '" as ' . $form['book_number'] . '.');
            redirect('books/list.php');
        } catch (PDOException $ex) {
            error_log('Book insert failed: ' . $ex->getMessage());
            $errors[] = $ex->getCode() === '23000' ? 'That book number was just taken. Try again.' : 'The book could not be saved. Please try again.';
        }
    }
} else {
    $form['book_number'] = nextBookNumber($pdo);
}

$area       = 'admin';
$navActive  = 'book-add';
$pageTitle  = 'Add a book';
$pageLead   = 'The next free book number is filled in for you. Change it if the book already carries one.';
$backLink   = ['books/list.php', 'Back to books'];
$submitLabel = 'Save book';
require APP_ROOT . '/includes/header.php';
?>

<?= errorsHtml($errors) ?>
<section class="panel">
    <?php require __DIR__ . '/_form.php'; ?>
</section>

<?php require APP_ROOT . '/includes/footer.php'; ?>
