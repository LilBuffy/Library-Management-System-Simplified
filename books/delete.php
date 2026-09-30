<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();
requirePost('books/list.php');

$pdo = getDB();
$id  = (int) postStr('id', '0');

$stmt = $pdo->prepare('SELECT id, book_number, title, status FROM books WHERE id = ?');
$stmt->execute([$id]);
$book = $stmt->fetch();

if (!$book) {
    flash('error', 'That book could not be found. It may already have been deleted.');
    redirect('books/list.php');
}

if ($book['status'] === 'Borrowed') {
    flash('error', '"' . $book['title'] . '" is on loan. Record its return before deleting it.');
    redirect('books/list.php');
}

try {
    $pdo->prepare('DELETE FROM books WHERE id = ?')->execute([$id]);
    logActivity('Book deleted', $book['title'] . ' (' . $book['book_number'] . ')');
    flash('success', 'Deleted "' . $book['title'] . '" (' . $book['book_number'] . ').');
} catch (PDOException $ex) {
    error_log('Book delete failed: ' . $ex->getMessage());
    flash('error', 'The book could not be deleted. Please try again.');
}
redirect('books/list.php');
