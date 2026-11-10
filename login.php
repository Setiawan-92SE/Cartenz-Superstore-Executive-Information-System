<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

start_session();
bootstrap_admin();

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $token = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $error = 'Sesi form kedaluwarsa. Silakan muat ulang halaman.';
    } elseif ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = db()->prepare('SELECT * FROM admin_users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if ($user !== false && password_verify($password, (string) $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $user['id'];
            $_SESSION['admin_username'] = (string) $user['username'];
            $_SESSION['admin_name'] = (string) $user['full_name'];
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header('Location: index.php');
            exit;
        }

        $error = 'Username atau password salah.';
    }
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Cartenz Superstore EIS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        brand: {
                            50: '#f0f7ff', 100: '#e0effe', 500: '#0284c7',
                            600: '#0369a1', 700: '#075985', 900: '#0c4a6e'
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="bg-slate-950 text-slate-100 font-sans antialiased min-h-screen selection:bg-sky-500 selection:text-white">
    <div class="min-h-screen grid lg:grid-cols-2">
        <!-- Brand / Information panel -->
        <div class="relative hidden lg:flex flex-col justify-between overflow-hidden bg-gradient-to-br from-slate-900 via-sky-950 to-slate-950 p-12">
            <div class="absolute inset-0 opacity-30">
                <div class="absolute -top-24 -left-24 w-96 h-96 rounded-full bg-sky-500/20 blur-3xl"></div>
                <div class="absolute bottom-0 right-0 w-96 h-96 rounded-full bg-emerald-500/10 blur-3xl"></div>
            </div>

            <div class="relative flex items-center gap-3">
                <img src="public/logo.png" alt="Cartenz Logo" class="w-12 h-12 object-contain rounded-xl bg-slate-800/60 p-1 border border-slate-700/60">
                <div>
                    <p class="font-bold text-lg tracking-tight">Cartenz Superstore</p>
                    <p class="text-xs text-sky-300/80">Executive Information System</p>
                </div>
            </div>

            <div class="relative max-w-md">
                <span class="inline-block px-3 py-1 rounded-full bg-sky-500/10 border border-sky-500/20 text-sky-300 text-[11px] font-semibold uppercase tracking-wider mb-5">
                    Admin Area
                </span>
                <h1 class="text-4xl font-extrabold leading-tight text-white">
                    Kendalikan data.<br>
                    <span class="text-sky-400">Lihat insight</span> secara real-time.
                </h1>
                <p class="mt-4 text-sm text-slate-300 leading-relaxed">
                    Dashboard eksekutif dengan pipeline ETL otomatis. Setiap perubahan transaksi
                    langsung memengaruhi KPI, chart, dan analisis drill-down.
                </p>

                <div class="mt-8 grid grid-cols-3 gap-3 text-center">
                    <div class="bg-slate-900/60 border border-slate-700/60 rounded-xl p-3">
                        <p class="text-lg font-bold text-white">ETL</p>
                        <p class="text-[10px] text-slate-400">Extract · Transform · Load</p>
                    </div>
                    <div class="bg-slate-900/60 border border-slate-700/60 rounded-xl p-3">
                        <p class="text-lg font-bold text-white">CRUD</p>
                        <p class="text-[10px] text-slate-400">Kelola transaksi</p>
                    </div>
                    <div class="bg-slate-900/60 border border-slate-700/60 rounded-xl p-3">
                        <p class="text-lg font-bold text-white">Live</p>
                        <p class="text-[10px] text-slate-400">Sinkron otomatis</p>
                    </div>
                </div>
            </div>

            <p class="relative text-[11px] text-slate-500">
                Sulthan V.O.R · Devano A.H · Ganesha S — Sistem Informasi Eksekutif
            </p>
        </div>

        <!-- Login form panel -->
        <div class="flex items-center justify-center p-6 sm:p-10">
            <div class="w-full max-w-md">
                <div class="lg:hidden flex items-center gap-3 mb-8">
                    <img src="public/logo.png" alt="Cartenz Logo" class="w-10 h-10 object-contain rounded-xl bg-slate-800 p-1">
                    <div>
                        <p class="font-bold text-white">Cartenz Superstore</p>
                        <p class="text-[11px] text-sky-400">Executive Information System</p>
                    </div>
                </div>

                <div class="bg-slate-900/70 border border-slate-800 rounded-2xl p-7 shadow-2xl backdrop-blur">
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-white">Login Admin</h2>
                        <p class="text-xs text-slate-400 mt-1">Masuk untuk mengakses dashboard eksekutif dan ETL workspace.</p>
                    </div>

                    <?php if ($error !== ''): ?>
                        <div class="mb-4 flex items-start gap-2 bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs rounded-xl px-3.5 py-3">
                            <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86l-8.2 14.2A2 2 0 003.82 21h16.36a2 2 0 001.72-2.94l-8.2-14.2a2 2 0 00-3.4 0z"/>
                            </svg>
                            <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="login.php" class="space-y-4" autocomplete="on">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                        <div>
                            <label for="username" class="block text-xs font-medium text-slate-300 mb-1.5">Username</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                </span>
                                <input id="username" name="username" type="text" required
                                       value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
                                       placeholder="admin"
                                       class="w-full bg-slate-950/70 border border-slate-700 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 text-sm text-slate-100 placeholder-slate-600 rounded-xl pl-10 pr-4 py-3 outline-none transition">
                            </div>
                        </div>

                        <div>
                            <label for="password" class="block text-xs font-medium text-slate-300 mb-1.5">Password</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                    </svg>
                                </span>
                                <input id="password" name="password" type="password" required
                                       placeholder="••••••••"
                                       class="w-full bg-slate-950/70 border border-slate-700 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 text-sm text-slate-100 placeholder-slate-600 rounded-xl pl-10 pr-4 py-3 outline-none transition">
                            </div>
                        </div>

                        <button type="submit"
                                class="w-full bg-sky-600 hover:bg-sky-500 active:bg-sky-700 text-white text-sm font-semibold rounded-xl py-3 transition shadow-lg shadow-sky-900/30 flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                            </svg>
                            Masuk ke Dashboard
                        </button>
                    </form>

                    <div class="mt-5 bg-slate-950/60 border border-slate-800 rounded-xl p-3 text-[11px] text-slate-400">
                        <p class="font-semibold text-slate-300 mb-1">Akun default (dibuat otomatis)</p>
                        <p>Username: <code class="text-sky-300">admin</code> &nbsp;·&nbsp; Password: <code class="text-sky-300">admin123</code></p>
                        <p class="mt-1 text-slate-500">Database: db_superstore · MySQL port 3307</p>
                    </div>
                </div>

                <p class="text-center text-[11px] text-slate-600 mt-6">
                    © <?= date('Y') ?> Cartenz Superstore Executive Information System
                </p>
            </div>
        </div>
    </div>
</body>
</html>
