<?php
require __DIR__ . '/layout.php';

// Cookie set by the game pages: show a "continue playing" shortcut.
$continue = null;
if (!empty($_COOKIE['last_game'])) {
    $s = db()->prepare('SELECT title, page_url FROM games WHERE slug=? AND is_active=1');
    $s->execute([$_COOKIE['last_game']]);
    $continue = $s->fetch() ?: null;
}
$banner = trim((string) setting('welcome_banner', ''));
top('Home', false);
?>
<?php if ($banner !== ''): ?><p class="notice" role="status"><?= e($banner) ?></p><?php endif; ?>
<section class="hero">
  <div class="hero-copy">
    <h1>Three games.<br><em>One arcade.</em></h1>
    <p>Play a quick match, solve a sliding puzzle, or put your computing knowledge to the test.</p>
    <div class="actions">
      <a class="primary" href="games.php">Explore games <span aria-hidden="true">↗</span></a>
      <a class="secondary" href="leaderboard.php">View leaderboard</a>
      <?php if ($continue): ?>
        <a class="secondary" href="<?= e($continue['page_url']) ?>">Continue: <?= e($continue['title']) ?></a>
      <?php endif; ?>
    </div>
  </div>
  <div class="hero-art" aria-hidden="true">
    <div class="orbit"></div>
    <div class="art-board">
      <span></span><span class="mark-o">○</span><span></span>
      <span class="mark-x">×</span><span class="mark-x main-mark">×</span><span class="mark-x">×</span>
      <span></span><span class="mark-o">○</span><span></span>
    </div>
  </div>
</section>
<section class="lineup" aria-labelledby="lineup">
  <div class="section-head"><h2 id="lineup">Game lineup</h2><a href="games.php">Browse all games <span aria-hidden="true">→</span></a></div>
  <?php game_cards(); ?>
</section>
<?php bottom(); ?>
