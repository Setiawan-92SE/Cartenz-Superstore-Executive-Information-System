<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

require_login_api();

$pdo = db();

$where = ['profit < 0'];
$params = [];

$region = query_str('region');
if ($region !== '') {
    $where[] = 'region_name = :region';
    $params[':region'] = $region;
}

$category = query_str('category');
if ($category !== '') {
    $where[] = 'category_name = :category';
    $params[':category'] = $category;
}

$subcategory = query_str('subcategory');
if ($subcategory !== '') {
    $where[] = 'subcategory_name = :subcategory';
    $params[':subcategory'] = $subcategory;
}

$limit = min(200, max(1, query_int('limit', 100)));

$stmt = $pdo->prepare(
    'SELECT sale_id, sales, discount, profit,
            region_name, state, category_name, subcategory_name
     FROM v_sales_detail
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY profit ASC
     LIMIT :limit'
);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$mapped = array_map(static function (array $row): array {
    $saleId = (int) $row['sale_id'];

    return [
        'saleId'   => $saleId,
        'orderId'  => 'US-2023-' . str_pad((string) $saleId, 5, '0', STR_PAD_LEFT),
        'customer' => 'Customer #' . (1000 + ($saleId % 9000)),
        'region'   => (string) $row['region_name'],
        'state'    => (string) $row['state'],
        'category' => (string) $row['category_name'],
        'subCat'   => (string) $row['subcategory_name'],
        'sales'    => (float) $row['sales'],
        'discount' => (float) $row['discount'],
        'profit'   => (float) $row['profit'],
    ];
}, $rows);

json_response([
    'ok'    => true,
    'rows'  => $mapped,
    'count' => count($mapped),
]);
