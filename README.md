# NeoArcade — 15-page PHP/MySQL student reference project

## Setup (XAMPP)
1. Install XAMPP and start Apache + MySQL.
2. Copy this folder to `C:/xampp/htdocs/neoarcade` (folder name should be `neoarcade`).
3. Visit `http://localhost/phpmyadmin`, open **Import**, select `database/neoarcade.sql`, then import.
4. Check MySQL credentials in `includes/config.php` (defaults: root, blank password).
5. Open `http://localhost/neoarcade/index.php` — **do not double-click PHP files**.
6. Register a user, log in, play games, and view profile/history/leaderboard.
7. To access admin, change your account role in phpMyAdmin:
   `UPDATE users SET role='admin' WHERE email='YOUR_EMAIL_HERE';`
   Log out and back in.

## 15 pages
1 index.php, 2 games.php, 3 tictactoe.php, 4 puzzle.php, 5 quiz.php,
6 login.php, 7 register.php, 8 forgot-password.php, 9 reset-password.php,
10 profile.php, 11 edit-profile.php, 12 leaderboard.php, 13 history.php,
14 admin.php, 15 about.php.

## Architecture
- Shared PHP layout: `includes/layout.php`
- Database connection and helpers: `includes/config.php`
- Global styling: `assets/css/style.css`
- Browser game logic: `assets/js/games.js`
- SQL schema and sample questions: `database/neoarcade.sql`
- Score API: `save-score.php`

## Important limitations
This is an educational prototype, **not production-ready**.
- Forgot password shows a reset link on the page in local demo mode. For deployment, send reset links through email and never display them in the response.
- Browser game scores are client-supplied and can be falsified. For competitive scoring, validate gameplay on the server.
- Contact form is a visual demo only (does not email/store).
- Tic-Tac-Toe is local two-player, not online multiplayer.
- Quiz uses questions loaded from MySQL.
- Add email verification, login rate limiting, secure HTTPS/cookie settings, and deployment hardening before hosting publicly.
