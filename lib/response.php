<?php
declare(strict_types=1);

/**
 * Kirim respons JSON lalu hentikan eksekusi.
 */
function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-Content-Type-Options: nosniff');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Kirim respons error JSON.
 */
function json_error(string $message, int $status = 400, array $extra = []): never
{
    json_response(array_merge([
        'ok'      => false,
        'message' => $message,
    ], $extra), $status);
}

/**
 * Baca body request JSON (fallback ke form-urlencoded).
 */
function read_json_body(): array
{
    $raw = file_get_contents('php://input');

    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return $_POST;
}

/**
 * Pastikan HTTP method sesuai.
 */
function require_method(string $method): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== strtoupper($method)) {
        json_error('Metode HTTP tidak diizinkan.', 405);
    }
}

/**
 * Ambil query string dengan aman.
 */
function query_str(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function query_int(string $key, int $default = 0): int
{
    $value = $_GET[$key] ?? null;
    return is_numeric($value) ? (int) $value : $default;
}
