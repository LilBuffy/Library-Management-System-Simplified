<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();
requirePost('users/list.php');

$pdo = getDB();
$id  = (int) postStr('id', '0');

$stmt = $pdo->prepare('SELECT id, full_name, username, member_id FROM users WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    flash('error', 'That member could not be found. They may already have been deleted.');
    redirect('users/list.php');
}

$chk = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ? AND status = 'borrowed'");
$chk->execute([$id]);
if ((int) $chk->fetchColumn() > 0) {
    flash('error', $user['full_name'] . ' still has books on loan. Record their returns before deleting the account.');
    redirect('users/list.php');
}

try {
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    logActivity('Member deleted', $user['full_name'] . ' (' . $user['member_id'] . ')');
    flash('success', 'Deleted ' . $user['full_name'] . '.');
} catch (PDOException $ex) {
    error_log('Member delete failed: ' . $ex->getMessage());
    flash('error', 'The member could not be deleted. Please try again.');
}
redirect('users/list.php');
