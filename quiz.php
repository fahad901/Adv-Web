<?php
require __DIR__ . '/layout.php';
remember_game('quiz');

$categories = db()->query('SELECT id, name FROM question_categories ORDER BY name')->fetchAll();
$selected = null;
foreach ($categories as $c) {
    if ((string) $c['id'] === ($_GET['category'] ?? '')) {
        $selected = $c;
    }
}
$count = max(1, min(20, (int) setting('quiz_question_count', 5)));

$sql = 'SELECT question, option_a, option_b, option_c, option_d, correct_index FROM questions'
    . ($selected ? ' WHERE category_id = ?' : '')
    . " ORDER BY RAND() LIMIT $count";
$stmt = db()->prepare($sql);
$stmt->execute($selected ? [$selected['id']] : []);
$qs = $stmt->fetchAll();

$out = [];
foreach ($qs as $q) {
    $out[] = [
        'question' => $q['question'],
        'options' => [$q['option_a'], $q['option_b'], $q['option_c'], $q['option_d']],
        'correct_index' => (int) $q['correct_index'],
    ];
}
top('Quiz Challenge');
?>
<p class="filters">
    <a class="secondary" href="quiz.php"<?= !$selected ? ' aria-current="true"' : '' ?>>All categories</a>
    <?php foreach ($categories as $c): ?>
        <a class="secondary" href="quiz.php?category=<?= e($c['id']) ?>"<?= $selected && $selected['id'] === $c['id'] ? ' aria-current="true"' : '' ?>><?= e($c['name']) ?></a>
    <?php endforeach; ?>
</p>
<?php if (!$out): ?>
    <div class="panel"><p>No questions in this category yet.</p></div>
<?php else: ?>
    <div class="panel game-panel" id="quiz-root" data-questions="<?= e(json_encode($out)) ?>"></div>
    <script>window.CSRF=<?= json_encode(csrf()) ?>;</script>
    <script src="games.js"></script>
<?php endif; ?>
<?php bottom(); ?>
