<?php
require __DIR__ . '/config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('games.php');
}
require_login();
verify_csrf();

$gameId = filter_var($_POST['game_id'] ?? null, FILTER_VALIDATE_INT);
$s = db()->prepare('SELECT title FROM games WHERE id=? AND is_active=1');
$s->execute([$gameId]);
$game = $s->fetch();

if ($game) {
    $has = db()->prepare('SELECT id FROM favorites WHERE user_id=? AND game_id=?');
    $has->execute([user()['id'], $gameId]);
    $existing = $has->fetchColumn();
    if ($existing) {
        db()->prepare('DELETE FROM favorites WHERE id=?')->execute([$existing]);
        flash($game['title'] . ' removed from your favourites.');
    } else {
        db()->prepare('INSERT INTO favorites(user_id,game_id,created_by) VALUES(?,?,?)')
            ->execute([user()['id'], $gameId, actor()]);
        flash($game['title'] . ' added to your favourites.');
    }
}
redirect('games.php');
