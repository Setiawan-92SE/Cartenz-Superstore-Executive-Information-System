<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

$action = query_str('action', 'check');

if ($action === 'check') {
    $user = current_user();

    json_response([
        'ok'            => true,
        'authenticated' => $user !== null,
        'user'          => $user,
        'csrfToken'     => csrf_token(),
        'liveSyncMs'    => LIVE_SYNC_MS,
    ]);
}

if ($action === 'logout') {
    require_method('POST');
    require_login_api();
    verify_csrf();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    session_destroy();

    json_response(['ok' => true, 'message' => 'Logout berhasil.']);
}

json_error('Aksi autentikasi tidak dikenal.', 400);
