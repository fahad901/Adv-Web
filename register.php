<?php
require __DIR__ . '/layout.php';

$open = setting('registration_open', '1') === '1';

if ($open && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    if (strlen($name) < 3 || strlen($name) > 50 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8) {
        $error = 'Use a username of 3–50 characters, valid email, and password of 8+ characters.';
    } else {
        try {
            // created_by is the new user's own username (self-registration).
            $s = db()->prepare('INSERT INTO users(username,email,password_hash,created_by) VALUES(?,?,?,?)');
            $s->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), $name]);
            log_event('register', (int) db()->lastInsertId(), $email, $name);
            flash('Account created. Please log in.');
            redirect('login.php');
        } catch (PDOException $ex) {
            $error = 'Username or email is already taken.';
        }
    }
}
top('Create Account');
?>
<div class="panel">
    <?php if (!$open): ?>
        <p>Registration is currently closed by the administrator.</p>
    <?php else: ?>
        <?php if (isset($error)): ?>
            <p class="error"><?= e($error) ?></p>
        <?php endif; ?>
        <form class="form" method="post">
            <?php csrf_field(); ?>
            <label>Username
                <input name="username" required minlength="3" maxlength="50">
            </label>
            <label>Email
                <input type="email" name="email" required>
            </label>
            <label>Password
                <input type="password" name="password" required minlength="8">
            </label>
            <button class="btn">Register</button>
        </form>
        <p>Already a member? <a href="login.php">Log in</a></p>
    <?php endif; ?>
</div>
<?php bottom(); ?>
