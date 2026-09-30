<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(isUserLoggedIn() ? 'user/dashboard.php' : 'auth/login.php');
}

if (isUserLoggedIn()) {
    logActivity('Sign out', 'Member signed out', $_SESSION['user_username'] ?? '');
}

$_SESSION = [];
session_regenerate_id(true);
flash('info', 'You have been signed out.');
redirect('auth/login.php');
