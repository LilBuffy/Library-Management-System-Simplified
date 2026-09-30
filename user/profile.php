<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireUserLogin();

$pdo    = getDB();
$uid    = (int) $_SESSION['user_id'];
$errors = [];

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$uid]);
$user = $stmt->fetch();

$form = [
    'full_name' => $user['full_name'],
    'email'     => (string) $user['email'],
    'phone'     => (string) $user['phone'],
    'address'   => (string) $user['address'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = postStr('action');

    if ($action === 'update_info') {
        $form = [
            'full_name' => postStr('full_name'),
            'email'     => postStr('email'),
            'phone'     => postStr('phone'),
            'address'   => postStr('address'),
        ];
        if ($form['full_name'] === '') {
            $errors[] = 'Enter your full name.';
        }
        if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address or leave it blank.';
        }
        if ($form['phone'] !== '' && !preg_match('/^[0-9+\-\s()]{5,30}$/', $form['phone'])) {
            $errors[] = 'Phone number can only contain digits, spaces, plus, hyphens and parentheses.';
        }
        if (!$errors && $form['email'] !== '') {
            $chk = $pdo->prepare('SELECT 1 FROM users WHERE email = ? AND id <> ?');
            $chk->execute([$form['email'], $uid]);
            if ($chk->fetchColumn()) {
                $errors[] = 'Another account already uses that email address.';
            }
        }
        if (!$errors) {
            try {
                $pdo->prepare('UPDATE users SET full_name = ?, email = ?, phone = ?, address = ? WHERE id = ?')
                    ->execute([$form['full_name'], $form['email'] ?: null, $form['phone'] ?: null, $form['address'] ?: null, $uid]);
                $_SESSION['user_name'] = $form['full_name'];
                flash('success', 'Your details have been saved.');
                redirect('user/profile.php');
            } catch (PDOException $ex) {
                error_log('Profile update failed: ' . $ex->getMessage());
                $errors[] = 'Your details could not be saved. Please try again.';
            }
        }
    } elseif ($action === 'change_password') {
        $current = postRaw('current_password');
        $new     = postRaw('new_password');
        $confirm = postRaw('confirm_password');

        if (!password_verify($current, $user['password'])) {
            $errors[] = 'Your current password is not correct.';
        }
        if (strlen($new) < MIN_PASSWORD_LENGTH) {
            $errors[] = 'The new password must be at least ' . MIN_PASSWORD_LENGTH . ' characters.';
        } elseif ($new !== $confirm) {
            $errors[] = 'The two new passwords do not match.';
        }
        if (!$errors) {
            try {
                $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
                session_regenerate_id(true);
                logActivity('Password changed', 'Member changed their password', $user['username']);
                flash('success', 'Your password has been changed.');
                redirect('user/profile.php');
            } catch (PDOException $ex) {
                error_log('Password change failed: ' . $ex->getMessage());
                $errors[] = 'Your password could not be changed. Please try again.';
            }
        }
    }
}

$area      = 'user';
$navActive = 'profile';
$pageTitle = 'Profile';
$pageLead  = 'Keep your contact details current so the library can reach you.';
require APP_ROOT . '/includes/header.php';
?>

<?= errorsHtml($errors) ?>

<section class="panel" aria-labelledby="details-h">
    <div class="panel-head">
        <h2 class="panel-title" id="details-h">Your details</h2>
        <span class="mono muted"><?= e($user['member_id']) ?></span>
    </div>
    <form method="post" class="panel-body">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="update_info">
        <div class="form-grid cols-2">
            <div class="field">
                <label class="label" for="full_name">Full name</label>
                <input class="input" type="text" id="full_name" name="full_name" value="<?= e($form['full_name']) ?>" maxlength="150" autocomplete="name" required>
            </div>
            <div class="field">
                <label class="label" for="username">Username</label>
                <input class="input" type="text" id="username" value="<?= e($user['username']) ?>" readonly>
                <span class="hint">Usernames cannot be changed.</span>
            </div>
            <div class="field">
                <label class="label" for="email">Email <span class="opt">(optional)</span></label>
                <input class="input" type="email" id="email" name="email" value="<?= e($form['email']) ?>" maxlength="150" autocomplete="email">
            </div>
            <div class="field">
                <label class="label" for="phone">Phone <span class="opt">(optional)</span></label>
                <input class="input" type="tel" id="phone" name="phone" value="<?= e($form['phone']) ?>" maxlength="30" autocomplete="tel">
            </div>
            <div class="field span-all">
                <label class="label" for="address">Address <span class="opt">(optional)</span></label>
                <textarea class="textarea" id="address" name="address" rows="2" autocomplete="street-address"><?= e($form['address']) ?></textarea>
            </div>
        </div>
        <div class="actions"><button type="submit" class="btn btn-primary">Save details</button></div>
    </form>
</section>

<section class="panel" aria-labelledby="account-h">
    <div class="panel-head"><h2 class="panel-title" id="account-h">Membership</h2></div>
    <dl class="defs panel-body">
        <dt>Member ID</dt><dd class="mono"><?= e($user['member_id']) ?></dd>
        <dt>Type</dt><dd><?= e(ucfirst($user['membership_type'])) ?></dd>
        <dt>Status</dt><dd><?= e(ucfirst($user['status'])) ?></dd>
        <dt>Member since</dt><dd><?= e(fmtDate($user['joined_date'], 'F j, Y')) ?></dd>
    </dl>
</section>

<section class="panel" aria-labelledby="password-h">
    <div class="panel-head"><h2 class="panel-title" id="password-h">Change password</h2></div>
    <form method="post" class="panel-body">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="change_password">
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
        <div class="actions"><button type="submit" class="btn btn-primary">Change password</button></div>
    </form>
</section>

<?php require APP_ROOT . '/includes/footer.php'; ?>
