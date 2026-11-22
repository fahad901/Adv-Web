<?php
require __DIR__ . '/layout.php';
require_login();
$id = user()['id'];

$s = db()->prepare('SELECT username,email,bio,created_at,last_login_at FROM users WHERE id=?');
$s->execute([$id]);
$u = $s->fetch();

$s = db()->prepare('SELECT COUNT(*) AS played,COALESCE(MAX(score),0) AS best,COALESCE(SUM(score),0) AS total FROM scores WHERE user_id=?');
$s->execute([$id]);
$stats = $s->fetch();

$s = db()->prepare('SELECT g.title, g.page_url FROM favorites f JOIN games g ON g.id = f.game_id WHERE f.user_id=? AND g.is_active=1 ORDER BY g.sort_order');
$s->execute([$id]);
$favorites = $s->fetchAll();

$s = db()->prepare("SELECT event, ip_address, created_at FROM login_logs WHERE user_id=? AND event IN ('login_success','logout','session_timeout') ORDER BY id DESC LIMIT 5");
$s->execute([$id]);
$activity = $s->fetchAll();

$timeout = (int) setting('session_timeout_minutes', 30);
top('My Profile');
?>
<div class="panel">
    <h2>👾 <?= e($u['username']) ?></h2>
    <p><?= e($u['email']) ?></p>
    <p><?= e($u['bio'] ?: 'No bio yet.') ?></p>
    <p>Joined <?= e(date('M Y', strtotime($u['created_at']))) ?></p>
    <a class="btn" href="edit-profile.php">Edit profile</a></div>
<div class="grid2">
    <div class="stat"><strong><?= e($stats['played']) ?></strong>Games played</div>
    <div class="stat"><strong><?= e($stats['best']) ?></strong>Best score</div>
    <div class="stat"><strong><?= e($stats['total']) ?></strong>Total points</div>
</div>
<div class="panel">
    <h2>Favourite games</h2>
    <?php if ($favorites): ?>
        <p class="filters">
            <?php foreach ($favorites as $f): ?>
                <a class="secondary" href="<?= e($f['page_url']) ?>">★ <?= e($f['title']) ?></a>
            <?php endforeach; ?>
        </p>
    <?php else: ?>
        <p>No favourites yet. Star a game in the <a href="games.php">Game library</a>.</p>
    <?php endif; ?>
</div>
<div class="panel">
    <h2>Session &amp; recent activity</h2>
    <p>Your session ends after <?= e($timeout) ?> minutes without activity.</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Event</th><th>IP address</th><th>When</th></tr></thead>
            <tbody>
                <?php foreach ($activity as $a): ?>
                    <tr><td><?= e(str_replace('_', ' ', $a['event'])) ?></td><td><?= e($a['ip_address']) ?></td><td><?= e($a['created_at']) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php bottom(); ?>
