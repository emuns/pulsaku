<?php
/**
 * Config & Helper untuk Panel Admin
 */

session_start();

define('DATA_DIR', __DIR__ . '/../data');
define('TX_FILE', DATA_DIR . '/transactions.json');
define('SETTINGS_FILE', DATA_DIR . '/settings.json');
define('ADMINS_FILE', DATA_DIR . '/admins.json');
define('LOG_FILE', DATA_DIR . '/activity.log');

// Pastikan folder data ada
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

// =============================================
// HELPER FUNCTIONS
// =============================================

function getTransactions(): array {
    if (!file_exists(TX_FILE)) return [];
    $data = json_decode(file_get_contents(TX_FILE), true);
    return is_array($data) ? $data : [];
}

function saveTransactions(array $txs): void {
    file_put_contents(TX_FILE, json_encode($txs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function getSettings(): array {
    if (!file_exists(SETTINGS_FILE)) {
        $defaults = getDefaultSettings();
        saveSettings($defaults);
        return $defaults;
    }
    $data = json_decode(file_get_contents(SETTINGS_FILE), true);
    return is_array($data) ? array_merge(getDefaultSettings(), $data) : getDefaultSettings();
}

function saveSettings(array $settings): void {
    file_put_contents(SETTINGS_FILE, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function getDefaultSettings(): array {
    return [
        'site_name' => 'PulsaKu',
        'site_tagline' => 'Convert Pulsa Terpercaya #1 Indonesia',
        'admin_contact' => 'admin@pulsaku.id',
        'whatsapp' => '6281234567890',
        'maintenance' => false,
        'auto_approve' => false,
        'min_transaction' => 10000,
        'providers' => [
            'telkomsel' => ['name' => 'Telkomsel', 'brands' => 'Simpati, As, Loop', 'rate' => 0.82, 'min' => 10000, 'max' => 1000000, 'color' => '#e4002b', 'icon' => 'fa-signal', 'active' => true],
            'xl'        => ['name' => 'XL / Axis', 'brands' => 'XL, Axis', 'rate' => 0.80, 'min' => 10000, 'max' => 1000000, 'color' => '#0064d2', 'icon' => 'fa-tower-cell', 'active' => true],
            'indosat'   => ['name' => 'Indosat Ooredoo', 'brands' => 'IM3, Mentari', 'rate' => 0.78, 'min' => 10000, 'max' => 1000000, 'color' => '#ffd500', 'icon' => 'fa-satellite-dish', 'active' => true],
            'tri'       => ['name' => 'Tri (3)', 'brands' => '3 (Tri)', 'rate' => 0.75, 'min' => 10000, 'max' => 500000, 'color' => '#e60012', 'icon' => 'fa-mobile-screen', 'active' => true],
            'smartfren' => ['name' => 'Smartfren', 'brands' => 'Smartfren', 'rate' => 0.76, 'min' => 10000, 'max' => 500000, 'color' => '#ff0000', 'icon' => 'fa-bolt', 'active' => true],
        ],
        'payment_methods' => [
            'dana'       => ['name' => 'DANA',        'icon' => 'fa-wallet',           'placeholder' => '08xx atau nomor DANA',     'active' => true, 'type' => 'ewallet'],
            'gopay'      => ['name' => 'GoPay',       'icon' => 'fa-money-bill',       'placeholder' => '08xx atau nomor GoPay',    'active' => true, 'type' => 'ewallet'],
            'ovo'        => ['name' => 'OVO',         'icon' => 'fa-credit-card',      'placeholder' => '08xx atau nomor OVO',      'active' => true, 'type' => 'ewallet'],
            'shopeepay'  => ['name' => 'ShopeePay',   'icon' => 'fa-bag-shopping',     'placeholder' => '08xx atau nomor ShopeePay','active' => true, 'type' => 'ewallet'],
            'bca'        => ['name' => 'Bank BCA',    'icon' => 'fa-building-columns', 'placeholder' => 'Nomor rekening BCA',       'active' => true, 'type' => 'bank'],
            'bri'        => ['name' => 'Bank BRI',    'icon' => 'fa-building-columns', 'placeholder' => 'Nomor rekening BRI',       'active' => true, 'type' => 'bank'],
            'mandiri'    => ['name' => 'Bank Mandiri','icon' => 'fa-building-columns', 'placeholder' => 'Nomor rekening Mandiri',   'active' => true, 'type' => 'bank'],
            'bsi'        => ['name' => 'Bank BSI',    'icon' => 'fa-building-columns', 'placeholder' => 'Nomor rekening BSI',       'active' => true, 'type' => 'bank'],
        ],
    ];
}

function getAdmins(): array {
    if (!file_exists(ADMINS_FILE)) return [];
    $data = json_decode(file_get_contents(ADMINS_FILE), true);
    return is_array($data) ? $data : [];
}

function saveAdmins(array $admins): void {
    file_put_contents(ADMINS_FILE, json_encode($admins, JSON_PRETTY_PRINT), LOCK_EX);
}

function isAdminLoggedIn(): bool {
    return !empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function requireLogin(): void {
    if (!isAdminLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

function rupiah(int $val): string {
    return 'Rp' . number_format($val, 0, ',', '.');
}

function sanitize(string $str): string {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function setFlash(string $type, string $msg): void {
    $_SESSION['admin_flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    $flash = $_SESSION['admin_flash'] ?? null;
    unset($_SESSION['admin_flash']);
    return $flash;
}

function writeLog(string $action, string $detail = ''): void {
    $admin = $_SESSION['admin_username'] ?? 'unknown';
    $line = date('Y-m-d H:i:s') . ' | ' . $admin . ' | ' . $action . ($detail ? ' | ' . $detail : '') . "\n";
    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

function getLogs(int $limit = 100): array {
    if (!file_exists(LOG_FILE)) return [];
    $lines = file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lines = array_reverse($lines);
    return array_slice($lines, 0, $limit);
}

function getClientIp(): string {
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// =============================================
// ADMIN LAYOUT TEMPLATE
// =============================================

function renderAdminPage(string $title, string $content, string $activePage = ''): void {
    $settings = getSettings();
    $flash = getFlash();
    $admins = getAdmins();
    $username = $_SESSION['admin_username'] ?? 'Admin';
    $txCount = count(getTransactions());
    $pendingCount = count(array_filter(getTransactions(), fn($t) => $t['status'] === 'pending'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($title) ?> - Admin <?= sanitize($settings['site_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        display: ['Space Grotesk', 'sans-serif'],
                        body: ['Plus Jakarta Sans', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        :root {
            --bg: #060d09;
            --bg2: #0c1a12;
            --fg: #e4f2ea;
            --muted: #6b9a80;
            --accent: #10b981;
            --accent2: #f59e0b;
            --card: rgba(12, 26, 18, 0.85);
            --border: rgba(16, 185, 129, 0.12);
        }
        * { box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--fg); margin: 0; }

        /* Sidebar */
        .sidebar {
            position: fixed; left: 0; top: 0; bottom: 0; width: 260px;
            background: var(--bg2); border-right: 1px solid var(--border);
            z-index: 40; transition: transform 0.3s ease;
            display: flex; flex-direction: column;
        }
        .sidebar-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6);
            z-index: 35;
        }
        @media (max-width: 1023px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .sidebar-overlay.open { display: block; }
        }
        .main-content {
            margin-left: 260px; min-height: 100vh;
        }
        @media (max-width: 1023px) {
            .main-content { margin-left: 0; }
        }

        /* Nav link */
        .nav-link {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 20px; font-size: 14px; font-weight: 500;
            color: var(--muted); text-decoration: none;
            border-radius: 10px; margin: 2px 12px;
            transition: all 0.2s ease;
        }
        .nav-link:hover { background: rgba(16,185,129,0.06); color: var(--fg); }
        .nav-link.active {
            background: rgba(16,185,129,0.1); color: var(--accent);
            border: 1px solid rgba(16,185,129,0.15);
        }
        .nav-link i { width: 20px; text-align: center; font-size: 15px; }

        /* Card */
        .card {
            background: var(--card); border: 1px solid var(--border);
            border-radius: 14px; backdrop-filter: blur(12px);
        }

        /* Form input */
        .fi {
            background: rgba(6,13,9,0.9); border: 1.5px solid var(--border);
            color: var(--fg); border-radius: 10px; padding: 11px 14px;
            width: 100%; font-size: 14px; transition: all 0.25s ease;
            font-family: inherit;
        }
        .fi:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(16,185,129,0.12); }
        .fi::placeholder { color: var(--muted); opacity: 0.5; }
        select.fi {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236b9a80' viewBox='0 0 16 16'%3E%3Cpath d='M2 5l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center; padding-right: 34px; cursor: pointer;
        }
        select.fi option { background: #0c1a12; color: #e4f2ea; }

        /* Button */
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 10px 20px; border-radius: 10px; font-size: 14px;
            font-weight: 600; cursor: pointer; border: none;
            transition: all 0.25s ease; font-family: inherit; text-decoration: none;
        }
        .btn-primary { background: linear-gradient(135deg, #10b981, #059669); color: #fff; }
        .btn-primary:hover { box-shadow: 0 4px 20px rgba(16,185,129,0.3); transform: translateY(-1px); }
        .btn-ghost { background: transparent; border: 1px solid var(--border); color: var(--fg); }
        .btn-ghost:hover { background: rgba(16,185,129,0.06); border-color: rgba(16,185,129,0.25); }
        .btn-danger { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #f87171; }
        .btn-danger:hover { background: rgba(239,68,68,0.2); }
        .btn-sm { padding: 6px 14px; font-size: 12px; border-radius: 8px; }
        .btn-warning { background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.2); color: #fbbf24; }
        .btn-warning:hover { background: rgba(245,158,11,0.2); }
        .btn-success { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #34d399; }
        .btn-success:hover { background: rgba(16,185,129,0.2); }

        /* Badge */
        .badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-pending { background: rgba(245,158,11,0.12); color: #fbbf24; }
        .badge-success { background: rgba(16,185,129,0.12); color: #34d399; }
        .badge-failed { background: rgba(239,68,68,0.12); color: #f87171; }

        /* Table */
        .tx-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .tx-table th { text-align: left; padding: 10px 14px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); border-bottom: 1px solid var(--border); font-weight: 600; }
        .tx-table td { padding: 12px 14px; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.03); }
        .tx-table tbody tr { transition: background 0.15s ease; }
        .tx-table tbody tr:hover { background: rgba(16,185,129,0.03); }

        /* Stat card */
        .stat-card {
            position: relative; overflow: hidden;
        }
        .stat-card::after {
            content: ''; position: absolute; top: -20px; right: -20px;
            width: 80px; height: 80px; border-radius: 50%;
            opacity: 0.06;
        }
        .stat-card.emerald::after { background: #10b981; }
        .stat-card.amber::after { background: #f59e0b; }
        .stat-card.blue::after { background: #3b82f6; }
        .stat-card.red::after { background: #ef4444; }

        /* Toast */
        .toast-box { position: fixed; top: 16px; right: 16px; z-index: 100; width: 360px; max-width: calc(100vw - 2rem); }
        .toast { padding: 14px 18px; border-radius: 12px; backdrop-filter: blur(16px); animation: toastSlide 0.3s ease; display: flex; align-items: flex-start; gap: 10px; margin-bottom: 8px; }
        .toast-success { background: rgba(6,30,18,0.95); border: 1px solid rgba(16,185,129,0.3); }
        .toast-error { background: rgba(30,6,6,0.95); border: 1px solid rgba(239,68,68,0.3); }
        @keyframes toastSlide { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(16,185,129,0.15); border-radius: 3px; }

        /* Log line */
        .log-line { font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.8; }
        .log-time { color: var(--muted); }
        .log-user { color: #60a5fa; }
        .log-action { color: var(--accent); }
        .log-detail { color: var(--fg); opacity: 0.7; }

        /* Toggle switch */
        .toggle { position: relative; width: 44px; height: 24px; cursor: pointer; }
        .toggle input { opacity: 0; width: 0; height: 0; }
        .toggle-slider {
            position: absolute; inset: 0; background: rgba(255,255,255,0.1);
            border-radius: 12px; transition: all 0.3s ease;
        }
        .toggle-slider::before {
            content: ''; position: absolute; width: 18px; height: 18px;
            left: 3px; top: 3px; background: #fff; border-radius: 50%;
            transition: all 0.3s ease;
        }
        .toggle input:checked + .toggle-slider { background: var(--accent); }
        .toggle input:checked + .toggle-slider::before { transform: translateX(20px); }

        /* Chart bar */
        .chart-bar {
            transition: height 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @media (max-width: 768px) {
            .hide-mobile { display: none; }
            .stat-grid { grid-template-columns: 1fr 1fr !important; }
        }
        @media (max-width: 480px) {
            .stat-grid { grid-template-columns: 1fr !important; }
        }
    </style>
</head>
<body>

<!-- Toast -->
<?php if ($flash): ?>
<div class="toast-box">
    <div class="toast toast-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
        <i class="fas <?= $flash['type'] === 'success' ? 'fa-circle-check text-emerald-400' : 'fa-circle-xmark text-red-400' ?> mt-0.5"></i>
        <div class="text-sm leading-relaxed"><?= $flash['msg'] ?></div>
        <button onclick="this.parentElement.remove()" class="ml-auto text-white/30 hover:text-white/60"><i class="fas fa-xmark"></i></button>
    </div>
</div>
<?php endif; ?>

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="p-5 border-b border-white/[0.04]">
        <a href="dashboard.php" class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-emerald-700 flex items-center justify-center">
                <i class="fas fa-bolt text-white text-xs"></i>
            </div>
            <div>
                <div class="font-display font-bold text-sm"><?= sanitize($settings['site_name']) ?></div>
                <div class="text-[10px] text-[var(--muted)]">Admin Panel</div>
            </div>
        </a>
    </div>

    <nav class="flex-1 py-4 overflow-y-auto">
        <div class="px-5 mb-2 text-[10px] font-semibold text-[var(--muted)] uppercase tracking-wider">Menu Utama</div>
        <a href="dashboard.php" class="nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
            <i class="fas fa-chart-pie"></i> Dashboard
        </a>
        <a href="transactions.php" class="nav-link <?= $activePage === 'transactions' ? 'active' : '' ?>">
            <i class="fas fa-receipt"></i> Transaksi
            <?php if ($pendingCount > 0): ?>
            <span class="ml-auto bg-amber-500/15 text-amber-400 text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $pendingCount ?></span>
            <?php endif; ?>
        </a>

        <div class="px-5 mb-2 mt-6 text-[10px] font-semibold text-[var(--muted)] uppercase tracking-wider">Pengaturan</div>
        <a href="settings.php" class="nav-link <?= $activePage === 'settings' ? 'active' : '' ?>">
            <i class="fas fa-sliders"></i> Rate & Metode
</a>
<a href="api_keys.php" class="nav-link <?= $activePage === 'api-keys' ? 'active' : '' ?>">
    <i class="fas fa-key"></i> API Keys
</a>
        <div class="px-5 mb-2 mt-6 text-[10px] font-semibold text-[var(--muted)] uppercase tracking-wider">Sistem</div>
        <a href="transactions.php?tab=logs" class="nav-link <?= $activePage === 'logs' ? 'active' : '' ?>">
            <i class="fas fa-scroll"></i> Activity Log
        </a>
        <a href="../index.php" target="_blank" class="nav-link">
            <i class="fas fa-external-link-alt"></i> Lihat Website
        </a>
    </nav>

    <div class="p-4 border-t border-white/[0.04]">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-emerald-500/15 border border-emerald-500/20 flex items-center justify-center">
                <i class="fas fa-user text-emerald-400 text-xs"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-xs font-semibold truncate"><?= sanitize($username) ?></div>
                <div class="text-[10px] text-[var(--muted)]">Administrator</div>
            </div>
            <a href="logout.php" class="text-[var(--muted)] hover:text-red-400 transition-colors" title="Logout">
                <i class="fas fa-right-from-bracket text-sm"></i>
            </a>
        </div>
    </div>
</aside>

<!-- Main Content -->
<div class="main-content">
    <!-- Top bar (mobile) -->
    <div class="lg:hidden sticky top-0 z-30 px-4 py-3 flex items-center gap-3 border-b border-white/[0.04]" style="background:rgba(6,13,9,0.9);backdrop-filter:blur(12px)">
        <button onclick="openSidebar()" class="text-[var(--muted)] hover:text-[var(--fg)]">
            <i class="fas fa-bars text-lg"></i>
        </button>
        <span class="font-display font-bold text-sm"><?= sanitize($title) ?></span>
    </div>

    <!-- Page Content -->
    <div class="p-4 sm:p-6 lg:p-8">
        <?= $content ?>
    </div>
</div>

<script>
function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('sidebarOverlay').classList.add('open');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('open');
}
// Auto-close toast setelah 4 detik
setTimeout(() => {
    document.querySelectorAll('.toast-box .toast').forEach(t => t.remove());
}, 4000);
</script>
</body>
</html>
<?php
}
?>
