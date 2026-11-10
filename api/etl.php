<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

require_login_api();

$action = query_str('action', 'status');

if ($action === 'status') {
    $status = ETL::status();

    json_response([
        'ok'     => true,
        'status' => $status,
        'serverTime' => date('c'),
    ]);
}

if ($action === 'run') {
    require_method('POST');
    verify_csrf();

    $result = ETL::run('manual');
    $status = ETL::status();

    json_response([
        'ok'      => true,
        'message' => 'ETL selesai. Warehouse dan seluruh chart telah disinkronkan.',
        'result'  => $result,
        'status'  => $status,
        'serverTime' => date('c'),
    ]);
}

json_error('Aksi ETL tidak dikenal.', 400);
