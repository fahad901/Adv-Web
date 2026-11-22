<?php
require_once __DIR__ . '/config.php';

function top(string $title, bool $showHeading = true): void {
    $account = user();
    $current = basename($_SERVER['PHP_SELF']);
    $siteName = setting('site_name', 'Gridline Arcade');
    $navigation = [
        'Discover' => ['index.php' => 'Overview', 'games.php' => 'Game library', 'leaderboard.php' => 'Leaderboard', 'history.php' => 'Game history'],
        'Games' => ['tictactoe.php' => 'Tic-Tac-Toe', 'puzzle.php' => 'Slide Puzzle', 'quiz.php' => 'Quiz Challenge'],
        'Account' => ['profile.php' => 'My profile', 'about.php' => 'About & contact'],
    ];
    if ($account && $account['role'] === 'admin') {
        $navigation['Account'] = ['profile.php' => 'My profile', 'admin.php' => 'Admin', 'about.php' => 'About & contact'];
    }
    ?>
    <!doctype html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title><?=e($title)?> | <?=e($siteName)?></title>
      <link rel="stylesheet" href="style.css">
      <script src="navigation.js" defer></script>
    </head>
    <body>
    <a class="skip" href="#main">Skip to content</a>
    <header class="topbar">
      <button id="menu" class="menu secondary" type="button" aria-label="Navigation menu"
        aria-expanded="false" aria-controls="sidebar"><span class="menu-icon" aria-hidden="true"></span></button>
      <a class="brand" href="index.php"><span class="brand-icon" aria-hidden="true"><i></i><i></i><i></i><i></i></span><span>Gridline <b>Arcade</b></span></a>
      <div class="top-actions">
        <?php if ($account): ?>
          <?php preg_match('/^\X/u', $account['username'], $initial); ?>
          <a class="profile-link" href="profile.php" aria-label="My profile" title="<?=e($account['username'])?>">
            <span class="profile-icon" aria-hidden="true"><?=e(strtoupper($initial[0] ?? 'P'))?></span>
            <span class="profile-link-label">My profile</span>
          </a>
          <form method="post" action="logout.php"><?php csrf_field(); ?><button class="secondary" type="submit">Log out</button></form>
        <?php else: ?>
          <a href="login.php">Log in</a>
          <a class="secondary register-link" href="register.php" aria-label="Create account">
            <span class="desktop-label">Create account</span><span class="mobile-label" aria-hidden="true">Join</span>
          </a>
        <?php endif; ?>
      </div>
    </header>
    <div class="shell">
      <aside id="sidebar" class="sidebar"><nav aria-label="Main navigation">
        <?php foreach ($navigation as $heading => $links): ?>
          <div class="nav-group"><p><?=e($heading)?></p>
            <?php foreach ($links as $url => $label): ?>
              <a href="<?=e($url)?>"<?=$current === $url ? ' aria-current="page"' : ''?>><?=e($label)?></a>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </nav></aside>
      <main id="main" class="main" tabindex="-1">
        <?php if ($showHeading): ?><header class="page-heading"><h1><?=e($title)?></h1></header><?php endif; ?>
        <?php if (isset($_SESSION['flash'])): ?>
          <p class="notice" role="status"><?=e($_SESSION['flash'])?></p>
          <?php unset($_SESSION['flash']); ?>
        <?php endif;
}

function bottom(): void { ?>
      <footer><span><?=e(setting('site_name', 'Gridline Arcade'))?></span><a href="about.php">About &amp; contact</a></footer>
      </main>
    </div>
    </body>
    </html>
<?php }

/** Game cards come from the games table; logged-in players also get a favourite button. */
function game_cards(bool $withFavorites = false): void {
    $games = db()->query('SELECT * FROM games WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
    $favorites = [];
    if ($withFavorites && user()) {
        $s = db()->prepare('SELECT game_id FROM favorites WHERE user_id=?');
        $s->execute([user()['id']]);
        $favorites = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
    }
    ?>
    <div class="cards">
      <?php foreach ($games as $g): ?>
        <div class="game-wrap">
          <a class="game-card <?=e($g['theme'])?>" href="<?=e($g['page_url'])?>">
            <div class="game-art" aria-hidden="true"><span><?=e($g['symbol'])?></span></div>
            <div class="card-copy">
              <span class="category"><?=e($g['category'])?></span>
              <h3><?=e($g['title'])?><span aria-hidden="true">↗</span></h3>
              <p><?=e($g['description'])?></p>
            </div>
          </a>
          <?php if ($withFavorites && user()): $isFav = in_array((int) $g['id'], $favorites, true); ?>
            <form method="post" action="favorite.php" class="fav-form">
              <?php csrf_field(); ?>
              <input type="hidden" name="game_id" value="<?=e($g['id'])?>">
              <button class="secondary fav-btn" type="submit"><?= $isFav ? '★ Remove favourite' : '☆ Add to favourites' ?></button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if (!$games): ?><p class="intro">No games are available right now.</p><?php endif; ?>
<?php }

function csrf_field(): void {
    echo '<input type="hidden" name="csrf" value="'.e(csrf()).'">';
}
