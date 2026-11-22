<?php
require __DIR__ . '/layout.php';

if (user()) {
    redirect('profile.php');
}

$rememberedEmail = $_COOKIE['remember_email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $maxAttempts = max(1, (int) setting('max_login_attempts', 5));
    $window = max(1, (int) setting('lockout_minutes', 10));

    // Failed attempts for this email since its last successful login, inside the lockout window.
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM login_logs
         WHERE email_attempted = ? AND event = 'login_failed'
           AND created_at > DATE_SUB(NOW(), INTERVAL $window MINUTE)
           AND id > COALESCE((SELECT MAX(l2.id) FROM login_logs l2
                              WHERE l2.email_attempted = ? AND l2.event = 'login_success'), 0)"
    );
    $stmt->execute([$email, $email]);
    $recentFailures = (int) $stmt->fetchColumn();

    if ($recentFailures >= $maxAttempts) {
        $error = "Too many failed attempts. Try again in $window minutes.";
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE email=?');
        $stmt->execute([$email]);
        $found = $stmt->fetch();

        if ($found && password_verify($_POST['password'] ?? '', $found['password_hash'])) {
            if ($found['status'] !== 'active') {
                $error = 'This account is suspended. Please contact an administrator.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user'] = ['id' => $found['id'], 'username' => $found['username'], 'role' => $found['role']];
                $_SESSION['last_activity'] = time();
                db()->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([$found['id']]);
                log_event('login_success', (int) $found['id'], $email, $found['username']);

                // Cookie: optionally remember the email address for 30 days.
                if (!empty($_POST['remember'])) {
                    setcookie('remember_email', $email, cookie_options(60 * 60 * 24 * 30));
                } else {
                    setcookie('remember_email', '', ['expires' => time() - 3600] + cookie_options());
                }
                redirect('profile.php');
            }
        } else {
            log_event('login_failed', $found ? (int) $found['id'] : null, $email, 'guest');
            $error = 'Incorrect email or password.';
        }
    }
    $rememberedEmail = $email;
}
top('Log In');
?>
<div class="panel">
    <p>Welcome back! Log in to track your scores.</p>
    <?php if (isset($error)): ?>
        <p class="error"><?= e($error) ?></p>
    <?php endif; ?>
    <form class="form" method="post">
        <?php csrf_field(); ?>
        <label>Email
            <input type="email" name="email" required autocomplete="email" value="<?= e($rememberedEmail) ?>">
        </label>
        <label>Password
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <label class="check">
            <input type="checkbox" name="remember" value="1"<?= $rememberedEmail !== '' ? ' checked' : '' ?>>
            Remember my email on this device (cookie)
        </label>
        <button class="btn">Log in</button>
    </form>
    <p><a href="forgot-password.php">Forgot password?</a> · <a href="register.php">Create account</a></p>
</div>
<?php bottom(); ?>
