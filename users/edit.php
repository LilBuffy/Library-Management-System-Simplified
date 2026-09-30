<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$pdo    = getDB();
$errors = [];
$types  = ['student' => 'Student', 'faculty' => 'Faculty', 'staff' => 'Staff', 'public' => 'Public'];
$states = ['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'];
$id     = (int) getStr('id', '0');

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    flash('error', 'That member could not be found.');
    redirect('users/list.php');
}

$form = [
    'full_name'       => $user['full_name'],
    'email'           => (string) $user['email'],
    'phone'           => (string) $user['phone'],
    'address'         => (string) $user['address'],
    'membership_type' => $user['membership_type'],
    'status'          => $user['status'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $form = [
        'full_name'       => postStr('full_name'),
        'email'           => postStr('email'),
        'phone'           => postStr('phone'),
        'address'         => postStr('address'),
        'membership_type' => pickEnum(postStr('membership_type'), array_keys($types), $user['membership_type']),
        'status'          => pickEnum(postStr('status'), array_keys($states), $user['status']),
    ];
    $newPassword = postRaw('new_password');

    if ($form['full_name'] === '') {
        $errors[] = 'Enter the member\'s full name.';
    }
    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address or leave it blank.';
    }
    if ($form['phone'] !== '' && !preg_match('/^[0-9+\-\s()]{5,30}$/', $form['phone'])) {
        $errors[] = 'Phone number can only contain digits, spaces, plus, hyphens and parentheses.';
    }
    if ($form['email'] !== '') {
        $chk = $pdo->prepare('SELECT 1 FROM users WHERE email = ? AND id <> ?');
        $chk->execute([$form['email'], $id]);
        if ($chk->fetchColumn()) {
            $errors[] = 'Another member already uses that email address.';
        }
    }
    if ($newPassword !== '' && strlen($newPassword) < MIN_PASSWORD_LENGTH) {
        $errors[] = 'A new password must be at least ' . MIN_PASSWORD_LENGTH . ' characters.';
    }

    if (!$errors) {
        $sql    = 'UPDATE users SET full_name = ?, email = ?, phone = ?, address = ?, membership_type = ?, status = ?';
        $params = [$form['full_name'], $form['email'] ?: null, $form['phone'] ?: null, $form['address'] ?: null, $form['membership_type'], $form['status']];
        if ($newPassword !== '') {
            $sql     .= ', password = ?';
            $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
        }
        $params[] = $id;
        try {
            $pdo->prepare($sql . ' WHERE id = ?')->execute($params);
            logActivity('Member updated', $form['full_name'] . ' (' . $user['member_id'] . ')' . ($newPassword !== '' ? ', password reset' : ''));
            flash('success', 'Saved changes to ' . $form['full_name'] . '.');
            redirect('users/list.php');
        } catch (PDOException $ex) {
            error_log('Member update failed: ' . $ex->getMessage());
            $errors[] = 'The changes could not be saved. Please try again.';
        }
    }
}

$area      = 'admin';
$navActive = 'members';
$pageTitle = 'Edit member';
$pageLead  = $user['member_id'] . ', username ' . $user['username'];
$backLink  = ['users/list.php', 'Back to members'];
require APP_ROOT . '/includes/header.php';
?>

<?= errorsHtml($errors) ?>
<section class="panel">
    <form method="post" class="panel-body">
        <?= csrfField() ?>
        <div class="form-grid cols-2">
            <div class="field">
                <label class="label" for="full_name">Full name</label>
                <input class="input" type="text" id="full_name" name="full_name" value="<?= e($form['full_name']) ?>" maxlength="150" required>
            </div>
            <div class="field">
                <label class="label" for="email">Email <span class="opt">(optional)</span></label>
                <input class="input" type="email" id="email" name="email" value="<?= e($form['email']) ?>" maxlength="150">
            </div>
            <div class="field">
                <label class="label" for="phone">Phone <span class="opt">(optional)</span></label>
                <input class="input" type="tel" id="phone" name="phone" value="<?= e($form['phone']) ?>" maxlength="30">
            </div>
            <div class="field">
                <label class="label" for="new_password">New password <span class="opt">(optional)</span></label>
                <input class="input" type="password" id="new_password" name="new_password" autocomplete="new-password">
                <span class="hint">Leave blank to keep the current password.</span>
            </div>
            <div class="field">
                <label class="label" for="membership_type">Membership type</label>
                <select class="select" id="membership_type" name="membership_type">
                    <?php foreach ($types as $v => $l): ?><option value="<?= e($v) ?>"<?= $form['membership_type'] === $v ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="label" for="status">Account status</label>
                <select class="select" id="status" name="status">
                    <?php foreach ($states as $v => $l): ?><option value="<?= e($v) ?>"<?= $form['status'] === $v ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                </select>
                <span class="hint">Only active members can sign in and borrow.</span>
            </div>
            <div class="field span-all">
                <label class="label" for="address">Address <span class="opt">(optional)</span></label>
                <textarea class="textarea" id="address" name="address" rows="2"><?= e($form['address']) ?></textarea>
            </div>
        </div>
        <div class="actions">
            <button type="submit" class="btn btn-primary">Save changes</button>
            <a class="btn btn-quiet" href="<?= e(url('users/list.php')) ?>">Cancel</a>
        </div>
    </form>
</section>

<?php require APP_ROOT . '/includes/footer.php'; ?>
