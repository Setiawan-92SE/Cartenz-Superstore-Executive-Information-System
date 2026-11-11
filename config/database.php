<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Koneksi PDO tunggal (singleton) ke database superstore_db.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
    } catch (PDOException $e) {
        $isApi = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/');

        if ($isApi) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok'      => false,
                'message' => 'Koneksi database gagal: ' . $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><html lang="id"><head><meta charset="utf-8">'
                . '<title>Koneksi Database Gagal</title>'
                . '<script src="https://cdn.tailwindcss.com"></script></head>'
                . '<body class="bg-slate-900 text-slate-100 flex items-center justify-center min-h-screen">'
                . '<div class="max-w-lg p-8 bg-slate-800 rounded-2xl border border-rose-500/40">'
                . '<h1 class="text-xl font-bold text-rose-400 mb-2">Koneksi database gagal</h1>'
                . '<p class="text-sm text-slate-300">Pastikan MySQL berjalan di port <b>'
                . htmlspecialchars(DB_PORT) . '</b> dan database <b>'
                . htmlspecialchars(DB_NAME) . '</b> sudah di-import.</p>'
                . '<pre class="mt-4 p-3 bg-slate-900 rounded-lg text-xs text-rose-300 overflow-auto">'
                . htmlspecialchars($e->getMessage()) . '</pre></div></body></html>';
        }
        exit;
    }

    return $pdo;
}
