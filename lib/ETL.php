<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * Pipeline ETL sederhana namun lengkap untuk data warehouse Superstore.
 *
 * Extract  : membaca seluruh baris dari view v_sales_detail (grain transaksi).
 * Transform: validasi, normalisasi dimensi, agregasi per Region/Category/Sub-Category,
 *            perhitungan average discount, profit margin, dan KPI global.
 * Load     : rebuild tabel warehouse dw_sales_summary + dw_kpi dalam satu transaksi.
 *
 * ensureFresh() membuat dashboard tetap sinkron walaupun data berubah dari luar
 * aplikasi (import SQL, CRUD manual, dsb) dengan membandingkan fingerprint data.
 */
final class ETL
{
    public static function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $pdo = db();

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS etl_state (
                id TINYINT UNSIGNED NOT NULL,
                fingerprint CHAR(32) NOT NULL,
                last_sale_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                source_rows INT UNSIGNED NOT NULL DEFAULT 0,
                last_run_at DATETIME NOT NULL,
                last_run_type VARCHAR(20) NOT NULL DEFAULT \'full\',
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS etl_runs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                trigger_source VARCHAR(30) NOT NULL DEFAULT \'manual\',
                run_type VARCHAR(20) NOT NULL DEFAULT \'full\',
                source_rows INT UNSIGNED NOT NULL DEFAULT 0,
                loaded_rows INT UNSIGNED NOT NULL DEFAULT 0,
                rejected_rows INT UNSIGNED NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT \'success\',
                message TEXT NULL,
                duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
                fingerprint CHAR(32) NOT NULL DEFAULT \'\',
                started_at DATETIME NOT NULL,
                finished_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_etl_runs_started (started_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS dw_sales_summary (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                region_name VARCHAR(50) NOT NULL,
                category_name VARCHAR(100) NOT NULL,
                subcategory_name VARCHAR(100) NOT NULL,
                sales DECIMAL(16,4) NOT NULL DEFAULT 0,
                profit DECIMAL(16,4) NOT NULL DEFAULT 0,
                quantity BIGINT NOT NULL DEFAULT 0,
                tx_count INT UNSIGNED NOT NULL DEFAULT 0,
                discount_weight DECIMAL(20,6) NOT NULL DEFAULT 0,
                avg_discount DECIMAL(8,6) NOT NULL DEFAULT 0,
                profit_margin DECIMAL(10,4) NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_dw_summary (region_name, category_name, subcategory_name),
                KEY idx_dw_region (region_name),
                KEY idx_dw_category (category_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS dw_kpi (
                id TINYINT UNSIGNED NOT NULL,
                total_sales DECIMAL(18,4) NOT NULL DEFAULT 0,
                total_profit DECIMAL(18,4) NOT NULL DEFAULT 0,
                avg_discount DECIMAL(10,6) NOT NULL DEFAULT 0,
                total_orders INT UNSIGNED NOT NULL DEFAULT 0,
                loss_orders INT UNSIGNED NOT NULL DEFAULT 0,
                total_quantity BIGINT NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS etl_rejects (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                sale_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                reason VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /**
     * Fingerprint murah untuk mendeteksi perubahan data (insert/update/delete).
     *
     * @return array{0:string,1:array<string,mixed>}
     */
    public static function fingerprint(): array
    {
        $pdo = db();
        $row = $pdo->query(
            'SELECT
                COUNT(*) AS row_count,
                COALESCE(MAX(sale_id), 0) AS max_id,
                COALESCE(SUM(sales), 0) AS sum_sales,
                COALESCE(SUM(profit), 0) AS sum_profit,
                COALESCE(SUM(quantity), 0) AS sum_qty,
                COALESCE(SUM(discount), 0) AS sum_discount
             FROM fact_sales'
        )->fetch();

        $parts = [
            (string) ($row['row_count'] ?? 0),
            (string) ($row['max_id'] ?? 0),
            (string) ($row['sum_sales'] ?? 0),
            (string) ($row['sum_profit'] ?? 0),
            (string) ($row['sum_qty'] ?? 0),
            (string) ($row['sum_discount'] ?? 0),
        ];

        return [md5(implode('|', $parts)), $row ?: []];
    }

    public static function state(): ?array
    {
        self::ensureSchema();
        $row = db()->query('SELECT * FROM etl_state WHERE id = 1')->fetch();
        return $row ?: null;
    }

    public static function status(): array
    {
        self::ensureSchema();
        $pdo = db();

        $state = $pdo->query('SELECT * FROM etl_state WHERE id = 1')->fetch() ?: null;
        $lastRun = $pdo->query('SELECT * FROM etl_runs ORDER BY id DESC LIMIT 1')->fetch() ?: null;
        $summaryRows = (int) $pdo->query('SELECT COUNT(*) FROM dw_sales_summary')->fetchColumn();
        $rejectRows = (int) $pdo->query('SELECT COUNT(*) FROM etl_rejects')->fetchColumn();
        $kpi = $pdo->query('SELECT * FROM dw_kpi WHERE id = 1')->fetch() ?: null;

        [$fingerprint] = self::fingerprint();
        $inSync = $state !== null && hash_equals((string) $state['fingerprint'], $fingerprint);

        return [
            'state'       => $state,
            'lastRun'     => $lastRun,
            'summaryRows' => $summaryRows,
            'rejectRows'  => $rejectRows,
            'kpi'         => $kpi,
            'fingerprint' => $fingerprint,
            'inSync'      => $inSync,
        ];
    }

    /**
     * Jalankan ETL bila fingerprint sumber berbeda dengan warehouse.
     *
     * @return array{ran:bool,fingerprint:string,result?:array}
     */
    public static function ensureFresh(bool $force = false): array
    {
        self::ensureSchema();

        [$fingerprint] = self::fingerprint();
        $state = self::state();

        if (!$force && $state !== null && hash_equals((string) $state['fingerprint'], $fingerprint)) {
            return ['ran' => false, 'fingerprint' => $fingerprint];
        }

        $result = self::run('auto');

        return ['ran' => true, 'fingerprint' => $result['fingerprint'], 'result' => $result];
    }

    /**
     * Extract -> Transform -> Load (full rebuild).
     *
     * Full rebuild dipilih agar perubahan data sedikit maupun banyak
     * (insert, update, delete) selalu konsisten tanpa state incremental yang rapuh.
     *
     * @return array<string,mixed>
     */
    public static function run(string $trigger = 'manual'): array
    {
        self::ensureSchema();

        $pdo = db();
        $startedMicro = microtime(true);
        $startedAt = date('Y-m-d H:i:s');
        [$fingerprint] = self::fingerprint();

        $rows = $pdo->query('SELECT * FROM v_sales_detail ORDER BY sale_id')->fetchAll();

        /* ----------------------------- TRANSFORM ----------------------------- */
        $groups = [];
        $rejects = [];
        $totalSales = 0.0;
        $totalProfit = 0.0;
        $totalQuantity = 0;
        $sumDiscount = 0.0;
        $totalRows = 0;
        $lossRows = 0;
        $maxSaleId = 0;

        foreach ($rows as $row) {
            $saleId = (int) ($row['sale_id'] ?? 0);
            $sales = (float) ($row['sales'] ?? 0);
            $quantity = (int) ($row['quantity'] ?? 0);
            $discount = (float) ($row['discount'] ?? 0);
            $profit = (float) ($row['profit'] ?? 0);

            $region = trim((string) ($row['region_name'] ?? ''));
            $category = trim((string) ($row['category_name'] ?? ''));
            $subcategory = trim((string) ($row['subcategory_name'] ?? ''));

            $reason = null;
            if ($region === '' || $category === '' || $subcategory === '') {
                $reason = 'Dimensi region/category/subcategory tidak lengkap';
            } elseif ($quantity <= 0) {
                $reason = 'Quantity harus lebih besar dari 0';
            } elseif ($discount < 0 || $discount > 1) {
                $reason = 'Discount harus berada pada rentang 0..1';
            } elseif (!is_finite($sales) || !is_finite($profit)) {
                $reason = 'Nilai sales/profit tidak valid';
            }

            if ($reason !== null) {
                $rejects[] = ['sale_id' => $saleId, 'reason' => $reason];
                continue;
            }

            $key = $region . "\x1F" . $category . "\x1F" . $subcategory;
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'region'        => $region,
                    'category'      => $category,
                    'subcategory'   => $subcategory,
                    'sales'         => 0.0,
                    'profit'        => 0.0,
                    'quantity'      => 0,
                    'tx_count'      => 0,
                    'discount_sum'  => 0.0,
                ];
            }

            $groups[$key]['sales'] += $sales;
            $groups[$key]['profit'] += $profit;
            $groups[$key]['quantity'] += $quantity;
            $groups[$key]['tx_count']++;
            $groups[$key]['discount_sum'] += $discount;

            $totalSales += $sales;
            $totalProfit += $profit;
            $totalQuantity += $quantity;
            $sumDiscount += $discount;
            $totalRows++;
            if ($profit < 0) {
                $lossRows++;
            }
            if ($saleId > $maxSaleId) {
                $maxSaleId = $saleId;
            }
        }

        $now = date('Y-m-d H:i:s');

        /* -------------------------------- LOAD -------------------------------- */
        $pdo->beginTransaction();

        try {
            $pdo->exec('DELETE FROM dw_sales_summary');

            $insertSummary = $pdo->prepare(
                'INSERT INTO dw_sales_summary
                    (region_name, category_name, subcategory_name, sales, profit, quantity,
                     tx_count, discount_weight, avg_discount, profit_margin, updated_at)
                 VALUES
                    (:region, :category, :subcategory, :sales, :profit, :quantity,
                     :tx_count, :discount_weight, :avg_discount, :profit_margin, :updated_at)'
            );

            foreach ($groups as $group) {
                $avgDiscount = $group['tx_count'] > 0
                    ? $group['discount_sum'] / $group['tx_count']
                    : 0.0;
                $margin = $group['sales'] != 0.0
                    ? ($group['profit'] / $group['sales']) * 100
                    : 0.0;

                $insertSummary->execute([
                    ':region'          => $group['region'],
                    ':category'        => $group['category'],
                    ':subcategory'     => $group['subcategory'],
                    ':sales'           => round($group['sales'], 4),
                    ':profit'          => round($group['profit'], 4),
                    ':quantity'        => $group['quantity'],
                    ':tx_count'        => $group['tx_count'],
                    ':discount_weight' => round($group['discount_sum'], 6),
                    ':avg_discount'    => round($avgDiscount, 6),
                    ':profit_margin'   => round($margin, 4),
                    ':updated_at'      => $now,
                ]);
            }

            $pdo->exec('DELETE FROM dw_kpi');
            $insertKpi = $pdo->prepare(
                'INSERT INTO dw_kpi
                    (id, total_sales, total_profit, avg_discount, total_orders,
                     loss_orders, total_quantity, updated_at)
                 VALUES
                    (1, :total_sales, :total_profit, :avg_discount, :total_orders,
                     :loss_orders, :total_quantity, :updated_at)'
            );
            $insertKpi->execute([
                ':total_sales'    => round($totalSales, 4),
                ':total_profit'   => round($totalProfit, 4),
                ':avg_discount'   => $totalRows > 0 ? round($sumDiscount / $totalRows, 6) : 0,
                ':total_orders'   => $totalRows,
                ':loss_orders'    => $lossRows,
                ':total_quantity' => $totalQuantity,
                ':updated_at'     => $now,
            ]);

            $pdo->exec('DELETE FROM etl_rejects');
            if ($rejects !== []) {
                $insertReject = $pdo->prepare(
                    'INSERT INTO etl_rejects (sale_id, reason, created_at)
                     VALUES (:sale_id, :reason, :created_at)'
                );
                foreach ($rejects as $reject) {
                    $insertReject->execute([
                        ':sale_id'    => $reject['sale_id'],
                        ':reason'     => $reject['reason'],
                        ':created_at' => $now,
                    ]);
                }
            }

            $pdo->exec('DELETE FROM etl_state WHERE id = 1');
            $insertState = $pdo->prepare(
                'INSERT INTO etl_state
                    (id, fingerprint, last_sale_id, source_rows, last_run_at, last_run_type)
                 VALUES
                    (1, :fingerprint, :last_sale_id, :source_rows, :last_run_at, :last_run_type)'
            );
            $insertState->execute([
                ':fingerprint'   => $fingerprint,
                ':last_sale_id'  => $maxSaleId,
                ':source_rows'   => $totalRows,
                ':last_run_at'   => $now,
                ':last_run_type' => 'full',
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $durationMs = (int) round((microtime(true) - $startedMicro) * 1000);
            self::logRun(
                $pdo,
                $trigger,
                'full',
                count($rows),
                0,
                count($rejects),
                'failed',
                $e->getMessage(),
                $durationMs,
                $fingerprint,
                $startedAt
            );

            throw $e;
        }

        $durationMs = (int) round((microtime(true) - $startedMicro) * 1000);
        self::logRun(
            $pdo,
            $trigger,
            'full',
            count($rows),
            count($groups),
            count($rejects),
            'success',
            null,
            $durationMs,
            $fingerprint,
            $startedAt
        );

        return [
            'fingerprint'   => $fingerprint,
            'trigger'       => $trigger,
            'sourceRows'    => count($rows),
            'loadedRows'    => count($groups),
            'rejectedRows'  => count($rejects),
            'durationMs'    => $durationMs,
            'lastRunAt'     => $now,
            'totalSales'    => round($totalSales, 4),
            'totalProfit'   => round($totalProfit, 4),
            'totalOrders'   => $totalRows,
            'lossOrders'    => $lossRows,
            'avgDiscount'   => $totalRows > 0 ? round($sumDiscount / $totalRows, 6) : 0,
        ];
    }

    private static function logRun(
        PDO $pdo,
        string $trigger,
        string $runType,
        int $sourceRows,
        int $loadedRows,
        int $rejectedRows,
        string $status,
        ?string $message,
        int $durationMs,
        string $fingerprint,
        string $startedAt
    ): void {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO etl_runs
                    (trigger_source, run_type, source_rows, loaded_rows, rejected_rows,
                     status, message, duration_ms, fingerprint, started_at, finished_at)
                 VALUES
                    (:trigger_source, :run_type, :source_rows, :loaded_rows, :rejected_rows,
                     :status, :message, :duration_ms, :fingerprint, :started_at, :finished_at)'
            );
            $stmt->execute([
                ':trigger_source' => $trigger,
                ':run_type'       => $runType,
                ':source_rows'    => $sourceRows,
                ':loaded_rows'    => $loadedRows,
                ':rejected_rows'  => $rejectedRows,
                ':status'         => $status,
                ':message'        => $message,
                ':duration_ms'    => $durationMs,
                ':fingerprint'    => $fingerprint,
                ':started_at'     => $startedAt,
                ':finished_at'    => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable) {
            // Logging tidak boleh menggagalkan proses utama.
        }
    }
}
