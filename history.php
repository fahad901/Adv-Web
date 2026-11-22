<?php
require __DIR__ . '/layout.php';
require_login();
$s = db()->prepare(
    'SELECT g.title, s.score, s.created_at
     FROM scores s JOIN games g ON g.id = s.game_id
     WHERE s.user_id = ?
     ORDER BY s.created_at DESC, s.id DESC
     LIMIT 50'
);
$s->execute([user()['id']]);
$rows = $s->fetchAll();
top('Game History');
?>
<div class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Game</th>
                    <th>Score</th>
                    <th>Played</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e($r['title']) ?></td>
                        <td><?= e($r['score']) ?></td>
                        <td><?= e($r['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (!$rows): ?>
        <p>Nothing played yet!</p>
    <?php endif; ?>
</div>
<?php bottom(); ?>
