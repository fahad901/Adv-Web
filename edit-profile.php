<?php
require __DIR__ . '/layout.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['username'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    if (strlen($name) < 3 || strlen($name) > 50 || strlen($bio) > 500) {
        $error = 'Username must be 3–50 characters; bio up to 500.';
    } else {
        try {
            db()->prepare('UPDATE users SET username=?,bio=? WHERE id=?')->execute([$name, $bio, user()['id']]);
            $_SESSION['user']['username'] = $name;
            flash('Profile updated.');
            redirect('profile.php');
        } catch (PDOException $ex) {
            $error = 'Username is already taken.';
        }
    }
}
$s = db()->prepare('SELECT username,bio FROM users WHERE id=?');
$s->execute([user()['id']]);
$u = $s->fetch();
top('Edit Profile');
?>
<div class="panel">
    <?php if (isset($error)): ?>
        <p class="error"><?= e($error) ?></p>
    <?php endif; ?>
    <form class="form" method="post">
        <?php csrf_field(); ?>
        <label>Username
            <input name="username" value="<?= e($u['username']) ?>" required>
        </label>
        <label>Bio
            <textarea name="bio" maxlength="500"><?= e($u['bio']) ?></textarea>
        </label>
        <button class="btn">Save changes</button>
    </form>
</div>
<?php bottom(); ?>
