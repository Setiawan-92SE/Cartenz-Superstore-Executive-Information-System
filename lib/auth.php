<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/response.php';

/**
 * Mulai session dengan konfigurasi cookie yang aman.
 */
function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('CARTENZ_SIE');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Buat tabel admin_users dan akun admin default bila belum ada.
 */
function bootstrap_admin(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo = db();
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS admin_users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            username VARCHAR(50) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL DEFAULT \'Administrator\',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_admin_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $count = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare(
            'INSERT INTO admin_users (username, password_hash, full_name)
             VALUES (:username, :password_hash, :full_name)'
        );
        $stmt->execute([
            ':username'      => DEFAULT_ADMIN_USERNAME,
            ':password_hash' => password_hash(DEFAULT_ADMIN_PASSWORD, PASSWORD_DEFAULT),
            ':full_name'     => DEFAULT_ADMIN_NAME,
        ]);
    }
}

function is_logged_in(): bool
{
    start_session();
    return !empty($_SESSION['admin_id']);
}

function current_user(): ?array
{
    start_session();

    if (empty($_SESSION['admin_id'])) {
        return null;
    }

    return [
        'id'       => (int) $_SESSION['admin_id'],
        'username' => (string) ($_SESSION['admin_username'] ?? 'admin'),
        'fullName' => (string) ($_SESSION['admin_name'] ?? 'Administrator'),
    ];
}

/**
 * Guard untuk halaman HTML: redirect ke login jika belum masuk.
 */
function require_login_page(string $loginUrl = 'login.php'): void
{
    if (!is_logged_in()) {
        header('Location: ' . $loginUrl);
        exit;
    }
}

/**
 * Guard untuk endpoint API: balas 401 JSON jika belum masuk.
 */
function require_login_api(): void
{
    if (!is_logged_in()) {
        json_error('Sesi berakhir. Silakan login kembali.', 401);
    }
}

function csrf_token(): string
{
    start_session();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Verifikasi CSRF dari header X-CSRF-Token atau field csrf_token.
 */
function verify_csrf(): void
{
    start_session();

    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');

    if (!is_string($token) || $token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        json_error('CSRF token tidak valid. Muat ulang halaman.', 419);
    }
}
