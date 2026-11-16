<?php
require_once __DIR__.'/config.php';
function top(string $title): void {
 $u=user(); $page=basename($_SERVER['PHP_SELF']);
 $nav=['index.php'=>'Home','games.php'=>'All Games','tictactoe.php'=>'Tic-Tac-Toe','puzzle.php'=>'Puzzle','quiz.php'=>'Quiz','leaderboard.php'=>'Leaderboard','history.php'=>'Game History','profile.php'=>'Profile','about.php'=>'About / Contact'];
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?> | NeoArcade</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body><header class="topbar"><a class="brand" href="index.php">✦ NEO<span>ARCADE</span></a><div class="top-actions"><a href="games.php">Explore Games</a><?php if($u): ?><a href="profile.php">👤 <?=e($u['username'])?></a><a href="logout.php">Log out</a><?php else: ?><a href="login.php">Log in</a><a class="pill" href="register.php">Join now</a><?php endif; ?></div></header>
<div class="shell"><aside class="sidebar"><div class="side-label">DISCOVER</div><?php foreach($nav as $href=>$name): ?><a class="<?= $page===$href?'active':'' ?>" href="<?=e($href)?>"><?=e($name)?></a><?php endforeach; ?><div class="side-label">ACCOUNT</div><a href="edit-profile.php">Edit Profile</a><a href="forgot-password.php">Forgot Password</a><?php if($u&&$u['role']==='admin'): ?><a href="admin.php">Admin Dashboard</a><?php endif; ?></aside>
<main class="main"><div class="page-heading"><div class="eyebrow">PLAY • COMPETE • REPEAT</div><h1><?=e($title)?></h1></div>
<?php if(isset($_SESSION['flash'])): ?><div class="notice"><?=e($_SESSION['flash'])?></div><?php unset($_SESSION['flash']); endif; ?>
<?php }
function bottom(): void { ?><footer>© <?=date('Y')?> NeoArcade • Built with PHP, MySQL, HTML, CSS & JavaScript</footer></main></div></body></html><?php }
function game_card($href,$icon,$name,$desc,$class=''): void {echo '<a class="game-card '.e($class).'" href="'.e($href).'"><span class="game-icon">'.e($icon).'</span><h3>'.e($name).'</h3><p>'.e($desc).'</p><span class="play-link">PLAY NOW →</span></a>';}
function csrf_field(): void {echo '<input type="hidden" name="csrf" value="'.e(csrf()).'">';}
