<?php
require __DIR__ . '/layout.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if ($name === '' || strlen($name) > 80 || !filter_var($email, FILTER_VALIDATE_EMAIL) || $message === '' || strlen($message) > 2000) {
        $error = 'Please enter your name, a valid email and a message (up to 2000 characters).';
    } else {
        db()->prepare('INSERT INTO contact_messages(user_id,name,email,message,created_by) VALUES(?,?,?,?,?)')
            ->execute([user()['id'] ?? null, $name, $email, $message, actor()]);
        $sent = true;
    }
}
top('About & Contact');
?>
<div class="panel">
    <h2>Made for quick fun 🎮</h2>
    <p>Gridline Arcade is a student web-development reference project combining browser games with PHP and MySQL. Inspired by Japanese colour palettes and futuristic arcade visuals.</p>
</div>
<div class="panel">
    <h2>Contact us</h2>
    <?php if (isset($sent)): ?>
        <div class="notice">Thanks! Your message was saved and an administrator will review it.</div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <p class="error"><?= e($error) ?></p>
    <?php endif; ?>
    <form class="form" method="post">
        <?php csrf_field(); ?>
        <label>Name
            <input name="name" required maxlength="80" value="<?= e(user()['username'] ?? '') ?>">
        </label>
        <label>Email
            <input type="email" name="email" required>
        </label>
        <label>Message
            <textarea name="message" required maxlength="2000"></textarea>
        </label>
        <button class="btn">Send message</button>
    </form>
</div>
<?php bottom(); ?>
