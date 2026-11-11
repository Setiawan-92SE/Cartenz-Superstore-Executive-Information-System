<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
| Ubah nilai di bawah jika kredensial server MySQL berbeda.
*/
const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'superstore_db';
const DB_USER = 'root';
const DB_PASS = '';

const APP_NAME = 'Cartenz Superstore EIS';

/* Akun admin awal (dibuat otomatis saat tabel admin_users masih kosong). */
const DEFAULT_ADMIN_USERNAME = 'admin';
const DEFAULT_ADMIN_PASSWORD = 'admin123';
const DEFAULT_ADMIN_NAME     = 'Administrator';

/* Interval polling data real-time di sisi browser (milidetik). */
const LIVE_SYNC_MS = 5000;