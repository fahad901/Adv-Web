<?php
require __DIR__ . '/layout.php';
remember_game('puzzle');
top('Sliding Puzzle');
?>
<div class="panel game-panel">
    <p>Tap a tile next to the empty space to move it. Arrange 1–8 in order.</p>
    <h2 id="puzzle-status">Moves: 0</h2>
    <div class="puzzle" id="puzzle-board"></div>
    <button class="btn" id="puzzle-reset">Shuffle</button>
</div>
<script>window.CSRF=<?= json_encode(csrf()) ?>;</script>
<script src="games.js"></script>
<?php bottom(); ?>
