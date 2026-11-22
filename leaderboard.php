<?php
require __DIR__ . '/layout.php';

$games = db()->query('SELECT id, slug, title FROM games WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
$selected = null;
foreach ($games as $g) {
    if ($g['slug'] === ($_GET['game'] ?? '')) {
        $selected = $g;
    }
}
$limit = max(1, (int) setting('leaderboard_size', 20));

$sql = 'SELECT u.username, SUM(s.score) AS points, COUNT(*) AS games
        FROM scores s
        JOIN users u ON u.id = s.user_id
        JOIN games g ON g.id = s.game_id'
    . ($selected ? ' WHERE g.id = ?' : '')
    . " GROUP BY u.id, u.username
        ORDER BY points DESC
        LIMIT $limit";
$stmt = db()->prepare($sql);
$stmt->execute($selected ? [$selected['id']] : []);
$rows = $stmt->fetchAll();

top('Leaderboard');
?>
<div class="panel">
    <p>Top players by total points<?= $selected ? ' in ' . e($selected['title']) : '' ?>.</p>
    <p class="filters">
        <a class="secondary" href="leaderboard.php"<?= !$selected ? ' aria-current="true"' : '' ?>>All games</a>
        <?php foreach ($games as $g): ?>
            <a class="secondary" href="leaderboard.php?game=<?= e($g['slug']) ?>"<?= $selected && $selected['id'] === $g['id'] ? ' aria-current="true"' : '' ?>><?= e($g['title']) ?></a>
        <?php endforeach; ?>
    </p>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Player</th>
                    <th>Points</th>
                    <th>Games</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $i => $r): ?>
                    <tr>
                        <td><?= ($i + 1) ?></td>
                        <td><?= e($r['username']) ?></td>
                        <td><?= e($r['points']) ?></td>
                        <td><?= e($r['games']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (!$rows): ?>
        <p>No scores yet. Be the first!</p>
    <?php endif; ?>
</div>
<?php bottom(); ?>
