<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

require_login_page();

$user = current_user();

require __DIR__ . '/views/dashboard.php';
