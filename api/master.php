<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

require_login_api();

$pdo = db();

$regions = $pdo->query(
    'SELECT region_id, region_name FROM regions ORDER BY region_name'
)->fetchAll();

$shipModes = $pdo->query(
    'SELECT ship_mode_id, ship_mode_name FROM ship_modes ORDER BY ship_mode_name'
)->fetchAll();

$segments = $pdo->query(
    'SELECT segment_id, segment_name FROM segments ORDER BY segment_name'
)->fetchAll();

$categoryRows = $pdo->query(
    'SELECT c.category_id, c.category_name,
            sc.subcategory_id, sc.subcategory_name
     FROM categories c
     JOIN subcategories sc ON sc.category_id = c.category_id
     ORDER BY c.category_name, sc.subcategory_name'
)->fetchAll();

$categories = [];
foreach ($categoryRows as $row) {
    $categoryId = (int) $row['category_id'];

    if (!isset($categories[$categoryId])) {
        $categories[$categoryId] = [
            'category_id'   => $categoryId,
            'category_name' => (string) $row['category_name'],
            'subcategories' => [],
        ];
    }

    $categories[$categoryId]['subcategories'][] = [
        'subcategory_id'   => (int) $row['subcategory_id'],
        'subcategory_name' => (string) $row['subcategory_name'],
    ];
}

$locations = $pdo->query(
    'SELECT l.location_id, l.country, l.state, l.city, l.postal_code,
            l.region_id, r.region_name
     FROM locations l
     JOIN regions r ON r.region_id = l.region_id
     ORDER BY r.region_name, l.state, l.city'
)->fetchAll();

json_response([
    'ok'         => true,
    'regions'    => array_map(static fn (array $r): array => [
        'region_id'   => (int) $r['region_id'],
        'region_name' => (string) $r['region_name'],
    ], $regions),
    'shipModes'  => array_map(static fn (array $r): array => [
        'ship_mode_id'   => (int) $r['ship_mode_id'],
        'ship_mode_name' => (string) $r['ship_mode_name'],
    ], $shipModes),
    'segments'   => array_map(static fn (array $r): array => [
        'segment_id'   => (int) $r['segment_id'],
        'segment_name' => (string) $r['segment_name'],
    ], $segments),
    'categories' => array_values($categories),
    'locations'  => array_map(static fn (array $r): array => [
        'location_id' => (int) $r['location_id'],
        'country'     => (string) $r['country'],
        'state'       => (string) $r['state'],
        'city'        => (string) $r['city'],
        'postal_code' => (string) $r['postal_code'],
        'region_id'   => (int) $r['region_id'],
        'region_name' => (string) $r['region_name'],
    ], $locations),
]);
