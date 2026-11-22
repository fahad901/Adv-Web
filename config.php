<?php
// Gridline Arcade - shared configuration, session control, cookies and helpers.

const DB_HOST = 'localhost';
const DB_NAME = 'gridline_arcade';
const DB_USER = 'root';
const DB_PASS = '';

// ---------------------------------------------------------------- sessions
function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

function cookie_options(int $ttlSeconds = 0): array
{
    return [
        'expires'  => $ttlSeconds > 0 ? time() + $ttlSeconds : 0,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('GRIDLINE_SID');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

set_exception_handler(function (Throwable $error): void {
    error_log((string) $error);
    $databaseError = $error instanceof PDOException;
    http_response_code($databaseError ? 503 : 500);
    $message = $databaseError
        ? 'Start MySQL in XAMPP and import gridline_arcade.sql in phpMyAdmin. '
            . 'If the database is already installed, check the connection settings in config.php.'
        : 'This page could not load. Check the PHP error log for details.';
    if (in_array(basename($_SERVER['SCRIPT_NAME'] ?? ''), ['save-score.php', 'favorite.php'], true)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $message]);
        return;
    }
    echo '<!doctype html><html lang="en"><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Gridline Arcade setup</title><link rel="stylesheet" href="style.css">'
        . '<main><section class="panel"><h1>' . ($databaseError ? 'Database unavailable' : 'Page unavailable') . '</h1>'
        . '<p>' . e($message) . '</p><a href="index.php">Back to Gridline Arcade</a></section></main></html>';
});

// ---------------------------------------------------------------- database
function db(): PDO
{
    static $pdo = null;
    if (!$pdo) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }
    return $pdo;
}

// ---------------------------------------------------------------- helpers
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/** Value stored in every table's created_by column. */
function actor(): string
{
    return user()['username'] ?? 'guest';
}

/** Read a value from the admin_settings table (loaded once per request). */
function setting(string $key, $default = null)
{
    static $all = null;
    if ($all === null) {
        $all = db()->query('SELECT setting_key, setting_value FROM admin_settings')
            ->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    return $all[$key] ?? $default;
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/** Write a row to login_logs (register, login_success, login_failed, logout, session_timeout). */
function log_event(string $event, ?int $userId, string $email, string $by): void
{
    db()->prepare(
        'INSERT INTO login_logs(user_id,email_attempted,event,ip_address,user_agent,created_by) VALUES(?,?,?,?,?,?)'
    )->execute([
        $userId,
        substr($email, 0, 190),
        $event,
        client_ip(),
        substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        substr($by, 0, 50),
    ]);
}

function csrf(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(24));
}

function verify_csrf(): void
{
    if (!hash_equals(csrf(), $_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Invalid request token');
    }
}

function require_login(): void
{
    if (!user()) {
        header('Location: login.php');
        exit;
    }
}

function require_admin(): void
{
    require_login();
    if (user()['role'] !== 'admin') {
        http_response_code(403);
        exit('Admin only');
    }
}

function flash($message): void
{
    $_SESSION['flash'] = $message;
}

function redirect($page): void
{
    header('Location: ' . $page);
    exit;
}

/** Cookie: remember the last game page the visitor opened (used on the home page). */
function remember_game(string $slug): void
{
    setcookie('last_game', $slug, cookie_options(60 * 60 * 24 * 30));
}

// ------------------------------------------------- session control (every request)
// 1) idle timeout from admin_settings, 2) role/status re-checked against the users table.
if (user()) {
    $idleLimit = (int) setting('session_timeout_minutes', 30) * 60;
    $now = time();
    $account = db()->prepare('SELECT role,status FROM users WHERE id=?');
    $account->execute([user()['id']]);
    $fresh = $account->fetch();

    if (!$fresh || $fresh['status'] !== 'active') {
        $_SESSION = [];
        session_regenerate_id(true);
        flash('Your account is not available. Please contact an administrator.');
    } elseif ($idleLimit > 0 && isset($_SESSION['last_activity']) && $now - $_SESSION['last_activity'] > $idleLimit) {
        log_event('session_timeout', (int) user()['id'], '', user()['username']);
        $_SESSION = [];
        session_regenerate_id(true);
        flash('Your session expired after a period of inactivity. Please log in again.');
    } else {
        $_SESSION['last_activity'] = $now;
        $_SESSION['user']['role'] = $fresh['role'];
    }
}
