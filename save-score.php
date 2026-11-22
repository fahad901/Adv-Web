<?php
require __DIR__ . '/config.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('{}');
}
if (!user()) {
    http_response_code(401);
    exit('{"error":"Log in to save scores"}');
}
verify_csrf();
$slug = $_POST['game'] ?? '';
$score = filter_var($_POST['score'] ?? null, FILTER_VALIDATE_INT);

$s = db()->prepare('SELECT id FROM games WHERE slug=? AND is_active=1');
$s->execute([$slug]);
$gameId = $s->fetchColumn();

if (!$gameId || $score === false || $score < 0 || $score > 1000) {
    http_response_code(422);
    exit('{"error":"Invalid score"}');
}
db()->prepare('INSERT INTO scores(user_id,game_id,score,created_by) VALUES(?,?,?,?)')
    ->execute([user()['id'], $gameId, $score, actor()]);
echo '{"ok":true}';
