<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

require_login_api();

/*
|--------------------------------------------------------------------------
| Pastikan warehouse selalu mengikuti data sumber terbaru.
| Jika ada perubahan (CRUD, import SQL, dsb) ETL otomatis dijalankan.
|--------------------------------------------------------------------------
*/
$fresh = ETL::ensureFresh();
$fingerprint = $fresh['fingerprint'];
$since = query_str('since');
$force = query_str('force') === '1';

if (!$force && $since !== '' && hash_equals($fingerprint, $since)) {
    json_response([
        'ok'          => true,
        'unchanged'   => true,
        'dataVersion' => $fingerprint,
        'serverTime'  => date('c'),
    ]);
}

$pdo = db();

$kpi = $pdo->query('SELECT * FROM dw_kpi WHERE id = 1')->fetch();
if ($kpi === false) {
    ETL::run('auto-dashboard');
    $kpi = $pdo->query('SELECT * FROM dw_kpi WHERE id = 1')->fetch();
}

$summaryRows = $pdo->query(
    'SELECT region_name, category_name, subcategory_name,
            sales, profit, quantity, tx_count, avg_discount
     FROM dw_sales_summary
     ORDER BY region_name, category_name, subcategory_name'
)->fetchAll();

$superstoreData = [];

foreach ($summaryRows as $row) {
    $region = (string) $row['region_name'];
    $category = (string) $row['category_name'];
    $subcategory = (string) $row['subcategory_name'];

    if (!isset($superstoreData[$region])) {
        $superstoreData[$region] = ['sales' => 0.0, 'profit' => 0.0, 'categories' => []];
    }
    if (!isset($superstoreData[$region]['categories'][$category])) {
        $superstoreData[$region]['categories'][$category] = [
            'sales'         => 0.0,
            'profit'        => 0.0,
            'subcategories' => [],
        ];
    }

    $sales = (float) $row['sales'];
    $profit = (float) $row['profit'];

    $superstoreData[$region]['sales'] += $sales;
    $superstoreData[$region]['profit'] += $profit;
    $superstoreData[$region]['categories'][$category]['sales'] += $sales;
    $superstoreData[$region]['categories'][$category]['profit'] += $profit;
    $superstoreData[$region]['categories'][$category]['subcategories'][$subcategory] = [
        'sales'       => $sales,
        'profit'      => $profit,
        'avgDiscount' => (float) $row['avg_discount'],
        'txCount'     => (int) $row['tx_count'],
    ];
}

$totals = [
    'totalSales'  => (float) $kpi['total_sales'],
    'totalProfit' => (float) $kpi['total_profit'],
    'avgDiscount' => (float) $kpi['avg_discount'],
];

$meta = [
    'totalOrders'   => (int) $kpi['total_orders'],
    'lossOrders'    => (int) $kpi['loss_orders'],
    'totalQuantity' => (int) $kpi['total_quantity'],
];

$leaks = $pdo->query(
    'SELECT sale_id, sales, discount, profit,
            region_name, state, category_name, subcategory_name
     FROM v_sales_detail
     WHERE profit < 0
     ORDER BY profit ASC
     LIMIT 25'
)->fetchAll();

$sampleLeakTransactions = array_map(static function (array $row): array {
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
}, $leaks);

$state = ETL::state();
$lastRun = $pdo->query('SELECT * FROM etl_runs ORDER BY id DESC LIMIT 1')->fetch() ?: null;

json_response([
    'ok'                    => true,
    'unchanged'             => false,
    'dataVersion'           => $fingerprint,
    'serverTime'            => date('c'),
    'generatedAt'           => date('Y-m-d H:i:s'),
    'totals'                => $totals,
    'meta'                  => $meta,
    'superstoreData'        => $superstoreData,
    'sampleLeakTransactions' => $sampleLeakTransactions,
    'etl'                   => [
        'lastRunAt'   => $state['last_run_at'] ?? null,
        'sourceRows'  => (int) ($state['source_rows'] ?? 0),
        'summaryRows' => count($summaryRows),
        'durationMs'  => (int) ($lastRun['duration_ms'] ?? 0),
        'status'      => $lastRun['status'] ?? 'idle',
        'trigger'     => $lastRun['trigger_source'] ?? null,
        'autoTriggered' => (bool) $fresh['ran'],
        'inSync'      => true,
    ],
]);
