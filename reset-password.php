<?php
require __DIR__ . '/layout.php';
$token = $_POST['token'] ?? $_GET['token'] ?? '';
$hash = hash('sha256', $token);
$s = db()->prepare('SELECT user_id FROM password_resets WHERE token_hash=? AND expires_at>NOW()');
$s->execute([$hash]);
$record = $s->fetch();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$record) {
        $error = 'Link invalid or expired.';
    } elseif (strlen($_POST['password'] ?? '') < 8) {
        $error = 'Password must contain at least 8 characters.';
    } else {
        db()->beginTransaction();
        db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($_POST['password'], PASSWORD_DEFAULT), $record['user_id']]);
        db()->prepare('DELETE FROM password_resets WHERE user_id=?')->execute([$record['user_id']]);
        db()->commit();
        flash('Password updated. Please log in.');
        redirect('login.php');
    }
}
top('Reset Password');
?>
<div class="panel">
    <?php if (isset($error)): ?>
        <p class="error"><?= e($error) ?></p>
    <?php endif; ?>
    <?php if ($record): ?>
        <form class="form" method="post">
            <?php csrf_field(); ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <label>New password
                <input type="password" name="password" minlength="8" required>
            </label>
            <button class="btn">Update password</button>
        </form>
    <?php else: ?>
        <p>This reset link is invalid or expired. <a href="forgot-password.php">Request a new one</a>.</p>
    <?php endif; ?>
</div>
<?php bottom(); ?>
