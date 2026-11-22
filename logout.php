<?php
require __DIR__ . '/config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('profile.php');
}
verify_csrf();
if (user()) {
    log_event('logout', (int) user()['id'], '', user()['username']);
}
$_SESSION = [];
session_regenerate_id(true);
flash('You have been logged out.');
redirect('index.php');
