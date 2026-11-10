<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

require_login_api();

function sales_select_sql(): string
{
    return 'SELECT f.sale_id, f.ship_mode_id, f.segment_id, f.location_id, f.subcategory_id,
                   f.sales, f.quantity, f.discount, f.profit,
                   sm.ship_mode_name, sg.segment_name,
                   c.category_name, sc.subcategory_name,
                   l.country, l.state, l.city, l.postal_code,
                   r.region_id, r.region_name';
}

function sales_from_sql(): string
{
    return ' FROM fact_sales f
             JOIN ship_modes sm ON sm.ship_mode_id = f.ship_mode_id
             JOIN segments sg ON sg.segment_id = f.segment_id
             JOIN subcategories sc ON sc.subcategory_id = f.subcategory_id
             JOIN categories c ON c.category_id = sc.category_id
             JOIN locations l ON l.location_id = f.location_id
             JOIN regions r ON r.region_id = l.region_id';
}

function fetch_sale(PDO $pdo, int $saleId): ?array
{
    $stmt = $pdo->prepare(sales_select_sql() . sales_from_sql() . ' WHERE f.sale_id = :sale_id LIMIT 1');
    $stmt->execute([':sale_id' => $saleId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function assert_exists(PDO $pdo, string $table, string $pk, int $id, string $label): void
{
    $allowed = [
        'ship_modes'    => 'ship_mode_id',
        'segments'      => 'segment_id',
        'locations'     => 'location_id',
        'subcategories' => 'subcategory_id',
    ];

    if (!isset($allowed[$table]) || $allowed[$table] !== $pk) {
        json_error('Validasi internal gagal.', 500);
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$pk} = :id");
    $stmt->execute([':id' => $id]);

    if ((int) $stmt->fetchColumn() === 0) {
        json_error($label . ' tidak ditemukan.', 422);
    }
}

/**
 * Ambil & validasi payload transaksi.
 *
 * @return array<string,mixed>
 */
function validate_sale_payload(array $body): array
{
    $shipModeId = isset($body['ship_mode_id']) && is_numeric($body['ship_mode_id']) ? (int) $body['ship_mode_id'] : 0;
    $segmentId = isset($body['segment_id']) && is_numeric($body['segment_id']) ? (int) $body['segment_id'] : 0;
    $locationId = isset($body['location_id']) && is_numeric($body['location_id']) ? (int) $body['location_id'] : 0;
    $subcategoryId = isset($body['subcategory_id']) && is_numeric($body['subcategory_id']) ? (int) $body['subcategory_id'] : 0;

    $sales = isset($body['sales']) && is_numeric($body['sales']) ? (float) $body['sales'] : null;
    $quantity = isset($body['quantity']) && is_numeric($body['quantity']) ? (int) $body['quantity'] : null;
    $discount = isset($body['discount']) && is_numeric($body['discount']) ? (float) $body['discount'] : null;
    $profit = isset($body['profit']) && is_numeric($body['profit']) ? (float) $body['profit'] : null;

    if ($shipModeId <= 0) {
        json_error('Ship mode wajib dipilih.', 422);
    }
    if ($segmentId <= 0) {
        json_error('Segment wajib dipilih.', 422);
    }
    if ($locationId <= 0) {
        json_error('Lokasi wajib dipilih.', 422);
    }
    if ($subcategoryId <= 0) {
        json_error('Sub-kategori wajib dipilih.', 422);
    }
    if ($sales === null || $sales < 0) {
        json_error('Sales tidak valid (harus >= 0).', 422);
    }
    if ($quantity === null || $quantity < 1) {
        json_error('Quantity tidak valid (harus >= 1).', 422);
    }
    if ($discount === null || $discount < 0 || $discount > 1) {
        json_error('Discount tidak valid (0 sampai 1 / 0% sampai 100%).', 422);
    }
    if ($profit === null) {
        json_error('Profit tidak valid.', 422);
    }

    return [
        'ship_mode_id'    => $shipModeId,
        'segment_id'      => $segmentId,
        'location_id'     => $locationId,
        'subcategory_id'  => $subcategoryId,
        'sales'           => round($sales, 4),
        'quantity'        => $quantity,
        'discount'        => round($discount, 4),
        'profit'          => round($profit, 4),
    ];
}

$action = query_str('action', 'list');

if ($action === 'list') {
    require_method('GET');

    $pdo = db();
    $where = [];
    $params = [];

    $search = query_str('search');
    if ($search !== '') {
        $where[] = '(CAST(f.sale_id AS CHAR) LIKE :search_id
                     OR sc.subcategory_name LIKE :search_sub
                     OR c.category_name LIKE :search_cat
                     OR l.city LIKE :search_city
                     OR l.state LIKE :search_state)';
        $like = '%' . $search . '%';
        $params[':search_id'] = $like;
        $params[':search_sub'] = $like;
        $params[':search_cat'] = $like;
        $params[':search_city'] = $like;
        $params[':search_state'] = $like;
    }

    $region = query_str('region');
    if ($region !== '') {
        $where[] = 'r.region_name = :region';
        $params[':region'] = $region;
    }

    $category = query_str('category');
    if ($category !== '') {
        $where[] = 'c.category_name = :category';
        $params[':category'] = $category;
    }

    $subcategory = query_str('subcategory');
    if ($subcategory !== '') {
        $where[] = 'sc.subcategory_name = :subcategory';
        $params[':subcategory'] = $subcategory;
    }

    $profitFilter = query_str('profit');
    if ($profitFilter === 'loss') {
        $where[] = 'f.profit < 0';
    } elseif ($profitFilter === 'profit') {
        $where[] = 'f.profit >= 0';
    }

    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

    $countStmt = $pdo->prepare('SELECT COUNT(*)' . sales_from_sql() . $whereSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $countStmt->execute();
    $total = (int) $countStmt->fetchColumn();

    $perPage = min(100, max(5, query_int('per_page', 20)));
    $page = max(1, query_int('page', 1));
    $totalPages = max(1, (int) ceil($total / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare(
        sales_select_sql() . sales_from_sql() . $whereSql .
        ' ORDER BY f.sale_id DESC LIMIT :limit OFFSET :offset'
    );
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $mapped = array_map(static function (array $row): array {
        return [
            'sale_id'          => (int) $row['sale_id'],
            'ship_mode_id'     => (int) $row['ship_mode_id'],
            'segment_id'       => (int) $row['segment_id'],
            'location_id'      => (int) $row['location_id'],
            'subcategory_id'   => (int) $row['subcategory_id'],
            'sales'            => (float) $row['sales'],
            'quantity'         => (int) $row['quantity'],
            'discount'         => (float) $row['discount'],
            'profit'           => (float) $row['profit'],
            'ship_mode_name'   => (string) $row['ship_mode_name'],
            'segment_name'     => (string) $row['segment_name'],
            'category_name'    => (string) $row['category_name'],
            'subcategory_name' => (string) $row['subcategory_name'],
            'country'          => (string) $row['country'],
            'state'            => (string) $row['state'],
            'city'             => (string) $row['city'],
            'postal_code'      => (string) $row['postal_code'],
            'region_id'        => (int) $row['region_id'],
            'region_name'      => (string) $row['region_name'],
        ];
    }, $rows);

    json_response([
        'ok'         => true,
        'rows'       => $mapped,
        'pagination' => [
            'page'       => $page,
            'perPage'    => $perPage,
            'total'      => $total,
            'totalPages' => $totalPages,
        ],
    ]);
}

if ($action === 'get') {
    require_method('GET');

    $saleId = query_int('id', 0);
    if ($saleId <= 0) {
        json_error('ID transaksi tidak valid.', 422);
    }

    $row = fetch_sale(db(), $saleId);
    if ($row === null) {
        json_error('Transaksi tidak ditemukan.', 404);
    }

    json_response(['ok' => true, 'sale' => $row]);
}

if ($action === 'create') {
    require_method('POST');
    verify_csrf();

    $payload = validate_sale_payload(read_json_body());
    $pdo = db();

    assert_exists($pdo, 'ship_modes', 'ship_mode_id', $payload['ship_mode_id'], 'Ship mode');
    assert_exists($pdo, 'segments', 'segment_id', $payload['segment_id'], 'Segment');
    assert_exists($pdo, 'locations', 'location_id', $payload['location_id'], 'Lokasi');
    assert_exists($pdo, 'subcategories', 'subcategory_id', $payload['subcategory_id'], 'Sub-kategori');

    $stmt = $pdo->prepare(
        'INSERT INTO fact_sales
            (ship_mode_id, segment_id, location_id, subcategory_id, sales, quantity, discount, profit)
         VALUES
            (:ship_mode_id, :segment_id, :location_id, :subcategory_id, :sales, :quantity, :discount, :profit)'
    );
    $stmt->execute([
        ':ship_mode_id'   => $payload['ship_mode_id'],
        ':segment_id'     => $payload['segment_id'],
        ':location_id'    => $payload['location_id'],
        ':subcategory_id' => $payload['subcategory_id'],
        ':sales'          => $payload['sales'],
        ':quantity'       => $payload['quantity'],
        ':discount'       => $payload['discount'],
        ':profit'         => $payload['profit'],
    ]);

    $saleId = (int) $pdo->lastInsertId();
    $etl = ETL::run('crud-create');

    json_response([
        'ok'          => true,
        'message'     => 'Transaksi #' . $saleId . ' berhasil ditambahkan dan ETL dijalankan.',
        'sale'        => fetch_sale($pdo, $saleId),
        'dataVersion' => $etl['fingerprint'],
        'etl'         => $etl,
    ]);
}

if ($action === 'update') {
    require_method('POST');
    verify_csrf();

    $body = read_json_body();
    $saleId = isset($body['sale_id']) && is_numeric($body['sale_id']) ? (int) $body['sale_id'] : 0;
    if ($saleId <= 0) {
        json_error('ID transaksi tidak valid.', 422);
    }

    $payload = validate_sale_payload($body);
    $pdo = db();

    if (fetch_sale($pdo, $saleId) === null) {
        json_error('Transaksi tidak ditemukan.', 404);
    }

    assert_exists($pdo, 'ship_modes', 'ship_mode_id', $payload['ship_mode_id'], 'Ship mode');
    assert_exists($pdo, 'segments', 'segment_id', $payload['segment_id'], 'Segment');
    assert_exists($pdo, 'locations', 'location_id', $payload['location_id'], 'Lokasi');
    assert_exists($pdo, 'subcategories', 'subcategory_id', $payload['subcategory_id'], 'Sub-kategori');

    $stmt = $pdo->prepare(
        'UPDATE fact_sales SET
            ship_mode_id = :ship_mode_id,
            segment_id = :segment_id,
            location_id = :location_id,
            subcategory_id = :subcategory_id,
            sales = :sales,
            quantity = :quantity,
            discount = :discount,
            profit = :profit
         WHERE sale_id = :sale_id'
    );
    $stmt->execute([
        ':ship_mode_id'   => $payload['ship_mode_id'],
        ':segment_id'     => $payload['segment_id'],
        ':location_id'    => $payload['location_id'],
        ':subcategory_id' => $payload['subcategory_id'],
        ':sales'          => $payload['sales'],
        ':quantity'       => $payload['quantity'],
        ':discount'       => $payload['discount'],
        ':profit'         => $payload['profit'],
        ':sale_id'        => $saleId,
    ]);

    $etl = ETL::run('crud-update');

    json_response([
        'ok'          => true,
        'message'     => 'Transaksi #' . $saleId . ' berhasil diperbarui dan ETL dijalankan.',
        'sale'        => fetch_sale($pdo, $saleId),
        'dataVersion' => $etl['fingerprint'],
        'etl'         => $etl,
    ]);
}

if ($action === 'delete') {
    require_method('POST');
    verify_csrf();

    $body = read_json_body();
    $saleId = isset($body['sale_id']) && is_numeric($body['sale_id']) ? (int) $body['sale_id'] : 0;
    if ($saleId <= 0) {
        json_error('ID transaksi tidak valid.', 422);
    }

    $pdo = db();
    if (fetch_sale($pdo, $saleId) === null) {
        json_error('Transaksi tidak ditemukan.', 404);
    }

    $stmt = $pdo->prepare('DELETE FROM fact_sales WHERE sale_id = :sale_id');
    $stmt->execute([':sale_id' => $saleId]);

    $etl = ETL::run('crud-delete');

    json_response([
        'ok'          => true,
        'message'     => 'Transaksi #' . $saleId . ' berhasil dihapus dan ETL dijalankan.',
        'dataVersion' => $etl['fingerprint'],
        'etl'         => $etl,
    ]);
}

json_error('Aksi transaksi tidak dikenal.', 400);
