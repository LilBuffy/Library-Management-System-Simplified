<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireLogin();

$pdo    = getDB();
$errors = [];

$stmt = $pdo->prepare('SELECT id, username, password, full_name, email, created_at FROM admins WHERE id = ?');
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch();

if (!$admin) {
    clearAdminSession();
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $current = postRaw('current_password');
    $new     = postRaw('new_password');
    $confirm = postRaw('confirm_password');

    if (!password_verify($current, $admin['password'])) {
        $errors[] = 'Your current password is not correct.';
    }
    if (strlen($new) < MIN_PASSWORD_LENGTH) {
        $errors[] = 'The new password must be at least ' . MIN_PASSWORD_LENGTH . ' characters.';
    } elseif ($new !== $confirm) {
        $errors[] = 'The two new passwords do not match.';
    } elseif ($new === $current) {
        $errors[] = 'Choose a password different from the current one.';
    }

    if (!$errors) {
        try {
            $pdo->prepare('UPDATE admins SET password = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $admin['id']]);
            session_regenerate_id(true);
            logActivity('Password changed', 'Administrator changed their password');
            flash('success', 'Your password has been changed.');
            redirect('account.php');
        } catch (PDOException $ex) {
            error_log('Admin password change failed: ' . $ex->getMessage());
            $errors[] = 'The password could not be changed. Please try again.';
        }
    }
}

$area      = 'admin';
$navActive = 'account';
$pageTitle = 'Account';
$pageLead  = 'Signed in as ' . $admin['username'] . '.';
require APP_ROOT . '/includes/header.php';
?>

<?= errorsHtml($errors) ?>
<section class="panel">
    <div class="panel-head"><h2 class="panel-title">Change password</h2></div>
    <form method="post" class="panel-body">
        <?= csrfField() ?>
        <div class="form-grid cols-2">
            <div class="field span-all">
                <label class="label" for="current_password">Current password</label>
                <input class="input" type="password" id="current_password" name="current_password" autocomplete="current-password" required>
            </div>
            <div class="field">
                <label class="label" for="new_password">New password</label>
                <input class="input" type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="<?= (int) MIN_PASSWORD_LENGTH ?>" required>
                <span class="hint">At least <?= (int) MIN_PASSWORD_LENGTH ?> characters.</span>
            </div>
            <div class="field">
                <label class="label" for="confirm_password">Confirm new password</label>
                <input class="input" type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
            </div>
        </div>
        <div class="actions">
            <button type="submit" class="btn btn-primary">Change password</button>
        </div>
    </form>
</section>

<?php require APP_ROOT . '/includes/footer.php'; ?>
