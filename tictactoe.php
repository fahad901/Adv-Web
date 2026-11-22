<?php
require __DIR__ . '/layout.php';
remember_game('tic');
top('Tic-Tac-Toe');
?>
<div class="panel game-panel">
    <p>Two players share one screen. The winner earns 10 points (when logged in).</p>
    <h2 id="tic-status">X to play</h2>
    <div class="board" id="tic-board"></div>
    <button class="btn" id="tic-reset">New round</button>
</div>
<script>window.CSRF=<?= json_encode(csrf()) ?>;</script>
<script src="games.js"></script>
<?php bottom(); ?>
