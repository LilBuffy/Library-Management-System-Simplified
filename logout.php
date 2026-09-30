<?php
require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(isLoggedIn() ? 'dashboard.php' : 'login.php');
}

if (isLoggedIn()) {
    logActivity('Sign out', 'Administrator signed out', $_SESSION['admin_username'] ?? '');
}

$_SESSION = [];
session_regenerate_id(true);
flash('info', 'You have been signed out.');
redirect('login.php');
