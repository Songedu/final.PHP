<?php
if (!function_exists('session_start_secure')) {
    function session_start_secure() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name('nirvana_session');
            session_set_cookie_params([
                'lifetime' => 3600,
                'path'     => '/',
                'domain'   => '',
                'secure'   => false,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
        }
    }
}

session_start_secure();

if (!function_exists('ensure_logged_in')) {
    function ensure_logged_in(): void {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        if (!isset($_SESSION['user_id'])) {
            header('Location: login.php?msg=login_required');
            exit;
        }
    }
}

if (!function_exists('ensure_admin')) {
    function ensure_admin(): void {
        ensure_logged_in();
        if (($_SESSION['role'] ?? '') !== 'admin') {
            http_response_code(403);
            header('Location: login.php?msg=forbidden');
            exit;
        }
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(string $token): bool {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

function db() {
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $db   = getenv('DB_NAME') ?: 'final_projecto';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }
}
$pdo = db();