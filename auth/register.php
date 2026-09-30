<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (isUserLoggedIn()) {
    redirect('user/dashboard.php');
}

$pdo    = getDB();
$errors = [];
$types  = ['student' => 'Student', 'faculty' => 'Faculty', 'staff' => 'Staff', 'public' => 'Public'];
$data   = ['full_name' => '', 'username' => '', 'email' => '', 'phone' => '', 'membership_type' => 'student', 'address' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $data = [
        'full_name'       => postStr('full_name'),
        'username'        => strtolower(postStr('username')),
        'email'           => postStr('email'),
        'phone'           => postStr('phone'),
        'membership_type' => pickEnum(postStr('membership_type'), array_keys($types), 'student'),
        'address'         => postStr('address'),
    ];
    $password = postRaw('password');
    $confirm  = postRaw('confirm_password');

    if ($data['full_name'] === '') {
        $errors[] = 'Enter your full name.';
    } elseif (mb_strlen($data['full_name']) > 150) {
        $errors[] = 'Full name is too long.';
    }
    if (!preg_match('/^[a-z0-9_]{4,30}$/', $data['username'])) {
        $errors[] = 'Username must be 4 to 30 characters: letters, numbers and underscores only.';
    }
    if (strlen($password) < MIN_PASSWORD_LENGTH) {
        $errors[] = 'Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters.';
    } elseif ($password !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    }
    if ($data['email'] !== '' && (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($data['email']) > 150)) {
        $errors[] = 'Enter a valid email address or leave it blank.';
    }
    if ($data['phone'] !== '' && !preg_match('/^[0-9+\-\s()]{5,30}$/', $data['phone'])) {
        $errors[] = 'Phone number can only contain digits, spaces, plus, hyphens and parentheses.';
    }

    if (!$errors) {
        $chk = $pdo->prepare('SELECT 1 FROM users WHERE username = ?');
        $chk->execute([$data['username']]);
        if ($chk->fetchColumn()) {
            $errors[] = 'That username is already taken.';
        }
        if ($data['email'] !== '') {
            $chk = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
            $chk->execute([$data['email']]);
            if ($chk->fetchColumn()) {
                $errors[] = 'That email address is already registered.';
            }
        }
    }

    if (!$errors) {
        $created  = false;
        $memberId = '';
        for ($attempt = 0; $attempt < 3 && !$created; $attempt++) {
            $memberId = nextMemberId($pdo);
            try {
                $pdo->prepare(
                    'INSERT INTO users (member_id, username, password, full_name, email, phone, address, membership_type, status, joined_date)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'active\', ?)'
                )->execute([
                    $memberId,
                    $data['username'],
                    password_hash($password, PASSWORD_DEFAULT),
                    $data['full_name'],
                    $data['email'] ?: null,
                    $data['phone'] ?: null,
                    $data['address'] ?: null,
                    $data['membership_type'],
                    today(),
                ]);
                $created = true;
            } catch (PDOException $ex) {
                if ($ex->getCode() !== '23000') {
                    error_log('Registration failed: ' . $ex->getMessage());
                    break;
                }
                if (stripos($ex->getMessage(), 'username') !== false) {
                    $errors[] = 'That username is already taken.';
                    break;
                }
            }
        }
        if ($created) {
            logActivity('Member registered', $data['full_name'] . ' (' . $memberId . ')', $data['username']);
            flash('success', 'Account created. You can sign in now.');
            redirect('auth/login.php');
        }
        if (!$errors) {
            $errors[] = 'The account could not be created. Please try again.';
        }
    }
}

$pageTitle = 'Create an account';
$topLink   = ['auth/login.php', 'Sign in'];
require APP_ROOT . '/includes/auth_header.php';
?>
    <main class="auth-card is-wide">
        <h1 class="auth-title">Create an account</h1>
        <p class="auth-lead">Members can borrow up to <?= (int) MAX_ACTIVE_BORROWS ?> books at a time.</p>

        <?= errorsHtml($errors) ?>

        <form method="post">
            <?= csrfField() ?>
            <div class="form-grid cols-2">
                <div class="field">
                    <label class="label" for="full_name">Full name</label>
                    <input class="input" type="text" id="full_name" name="full_name" value="<?= e($data['full_name']) ?>" autocomplete="name" maxlength="150" required>
                </div>
                <div class="field">
                    <label class="label" for="username">Username</label>
                    <input class="input" type="text" id="username" name="username" value="<?= e($data['username']) ?>" autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="30" required>
                    <span class="hint">Letters, numbers and underscores. At least 4 characters.</span>
                </div>
                <div class="field">
                    <label class="label" for="password">Password</label>
                    <input class="input" type="password" id="password" name="password" autocomplete="new-password" minlength="<?= (int) MIN_PASSWORD_LENGTH ?>" required>
                    <span class="hint">At least <?= (int) MIN_PASSWORD_LENGTH ?> characters.</span>
                </div>
                <div class="field">
                    <label class="label" for="confirm_password">Confirm password</label>
                    <input class="input" type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
                </div>
                <div class="field">
                    <label class="label" for="email">Email <span class="opt">(optional)</span></label>
                    <input class="input" type="email" id="email" name="email" value="<?= e($data['email']) ?>" autocomplete="email" maxlength="150">
                </div>
                <div class="field">
                    <label class="label" for="phone">Phone <span class="opt">(optional)</span></label>
                    <input class="input" type="tel" id="phone" name="phone" value="<?= e($data['phone']) ?>" autocomplete="tel" maxlength="30">
                </div>
                <div class="field span-all">
                    <label class="label" for="membership_type">I am a</label>
                    <select class="select" id="membership_type" name="membership_type">
                        <?php foreach ($types as $value => $label): ?>
                        <option value="<?= e($value) ?>"<?= $data['membership_type'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field span-all">
                    <label class="label" for="address">Address <span class="opt">(optional)</span></label>
                    <textarea class="textarea" id="address" name="address" rows="2" autocomplete="street-address"><?= e($data['address']) ?></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Create account</button>
        </form>

        <div class="auth-alt">
            <span>Already registered? <a href="<?= e(url('auth/login.php')) ?>">Sign in</a></span>
        </div>
    </main>
<?php require APP_ROOT . '/includes/auth_footer.php'; ?>
