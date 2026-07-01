<?php
/**
 * PulsaKu - Convert Pulsa Terpercaya
 * Aplikasi konversi pulsa ke saldo e-wallet / rekening bank
 * TERINTEGRASI dengan panel admin settings
 */

session_start();

// =============================================
// KONFIGURASI PATH
// =============================================
define('DATA_DIR', __DIR__ . '/data');
define('TX_FILE', DATA_DIR . '/transactions.json');
define('SETTINGS_FILE', DATA_DIR . '/settings.json');

// Pastikan folder data ada
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

// =============================================
// HELPER FUNCTIONS
// =============================================

function getTransactions(): array {
    if (!file_exists(TX_FILE)) return [];
    $json = file_get_contents(TX_FILE);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function saveTransactions(array $txs): void {
    file_put_contents(TX_FILE, json_encode($txs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/** Baca settings dari file JSON (ditulis oleh admin panel) */
function getSettings(): array {
    $defaults = getDefaultSettings();
    if (!file_exists(SETTINGS_FILE)) return $defaults;
    $json = file_get_contents(SETTINGS_FILE);
    $data = json_decode($json, true);
    if (!is_array($data)) return $defaults;

    // Merge dengan defaults agar tidak ada key yang hilang
    return [
        'site_name'       => $data['site_name'] ?? $defaults['site_name'],
        'site_tagline'    => $data['site_tagline'] ?? $defaults['site_tagline'],
        'admin_contact'   => $data['admin_contact'] ?? $defaults['admin_contact'],
        'whatsapp'        => $data['whatsapp'] ?? $defaults['whatsapp'],
        'maintenance'     => !empty($data['maintenance']),
        'auto_approve'    => !empty($data['auto_approve']),
        'min_transaction' => intval($data['min_transaction'] ?? $defaults['min_transaction']),
        'providers'       => $data['providers'] ?? $defaults['providers'],
        'payment_methods' => $data['payment_methods'] ?? $defaults['payment_methods'],
    ];
}

function getDefaultSettings(): array {
    return [
        'site_name'       => 'PulsaKu',
        'site_tagline'    => 'Convert Pulsa Terpercaya #1 Indonesia',
        'admin_contact'   => 'admin@pulsaku.id',
        'whatsapp'        => '6281234567890',
        'maintenance'     => false,
        'auto_approve'    => false,
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

function generateTxId(): string {
    return 'TXN' . date('Ymd') . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
}

function rupiah(int $val): string {
    return 'Rp' . number_format($val, 0, ',', '.');
}

function sanitize(string $str): string {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function setFlash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function calcReceived(int $amount, float $rate): int {
    return (int) floor($amount * $rate);
}

// =============================================
// MUAT SETTINGS & DATA
// =============================================
 $settings = getSettings();
 $allProviders = $settings['providers'];
 $allPaymentMethods = $settings['payment_methods'];
 $appName = sanitize($settings['site_name']);
 $appTagline = sanitize($settings['site_tagline']);
 $waNumber = sanitize($settings['whatsapp']);
 $minTx = intval($settings['min_transaction']);

// Filter HANYA yang aktif
 $providers = array_filter($allProviders, fn($p) => !empty($p['active']));
 $paymentMethods = array_filter($allPaymentMethods, fn($m) => !empty($m['active']));

// Nominal cepat
 $quickAmounts = [10000, 25000, 50000, 100000, 200000, 500000];

// =============================================
// MAINTENANCE MODE
// =============================================
if ($settings['maintenance']) {
    // Admin tetap bisa akses
    $isAdmin = !empty($_SESSION['admin_logged_in']);
    $isAdminPath = strpos($_SERVER['REQUEST_URI'] ?? '', '/admin') === 0;
    if (!$isAdmin && !$isAdminPath) {
        http_response_code(503);
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Maintenance - <?= $appName ?></title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
            <style>
                :root{--bg:#060d09;--fg:#e4f2ea;--muted:#6b9a80;--accent:#10b981}
                *{box-sizing:border-box}
                body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--fg);margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center}
                .bg-glow{position:fixed;inset:0;z-index:0;background:radial-gradient(ellipse 50% 50% at 50% 50%,rgba(16,185,129,0.05) 0%,transparent 70%)}
            </style>
        </head>
        <body>
            <div class="bg-glow"></div>
            <div class="relative z-10 text-center px-6 max-w-md">
                <div class="w-20 h-20 rounded-2xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-wrench text-amber-400 text-3xl"></i>
                </div>
                <h1 class="font-display text-3xl font-bold mb-3">Sedang Maintenance</h1>
                <p class="text-sm text-[var(--muted)] leading-relaxed mb-6">Kami sedang melakukan pemeliharaan sistem. Silakan kembali beberapa saat lagi.</p>
                <a href="https://wa.me/<?= $waNumber ?>" target="_blank" class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-400 hover:text-emerald-300 transition-colors">
                    <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
                </a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// =============================================
// PROSES FORM SUBMIT
// =============================================
 $flash = getFlash();
 $errors = [];
 $lastTx = null;
 $currentTab = 'form';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'convert') {
        $provider      = sanitize($_POST['provider'] ?? '');
        $phone         = sanitize($_POST['phone'] ?? '');
        $amount        = intval($_POST['amount'] ?? 0);
        $payment       = sanitize($_POST['payment'] ?? '');
        $payAccount    = sanitize($_POST['pay_account'] ?? '');

        // Validasi provider
        if (empty($provider) || !isset($allProviders[$provider]) || empty($allProviders[$provider]['active'])) {
            $errors[] = 'Pilih provider yang valid.';
        }
        // Validasi nomor HP
        if (empty($phone) || !preg_match('/^08[0-9]{8,13}$/', $phone)) {
            $errors[] = 'Nomor HP tidak valid. Format: 08xxxxxxxxxx (10-13 digit).';
        }
        // Validasi nominal
        if ($amount < $minTx) {
            $errors[] = 'Minimal konversi ' . rupiah($minTx) . '.';
        }
        if (isset($allProviders[$provider]) && $amount > $allProviders[$provider]['max']) {
            $errors[] = 'Maksimal konversi ' . rupiah($allProviders[$provider]['max']) . ' untuk ' . $allProviders[$provider]['name'] . '.';
        }
        if ($amount % 1000 !== 0) {
            $errors[] = 'Nominal harus kelipatan Rp1.000.';
        }
        // Validasi metode bayar
        if (empty($payment) || !isset($allPaymentMethods[$payment]) || empty($allPaymentMethods[$payment]['active'])) {
            $errors[] = 'Pilih metode pembayaran tujuan.';
        }
        if (empty($payAccount)) {
            $errors[] = 'Nomor akun / rekening tujuan wajib diisi.';
        }

        if (empty($errors)) {
            $prov = $allProviders[$provider];
            $received = calcReceived($amount, $prov['rate']);

            // Auto-approve jika diaktifkan di admin
            $initialStatus = $settings['auto_approve'] ? 'success' : 'pending';

            $tx = [
                'id'              => generateTxId(),
                'provider'        => $provider,
                'provider_name'   => $prov['name'],
                'phone'           => $phone,
                'amount'          => $amount,
                'rate'            => $prov['rate'],
                'received'        => $received,
                'payment'         => $payment,
                'payment_name'    => $allPaymentMethods[$payment]['name'],
                'payment_account' => $payAccount,
                'status'          => $initialStatus,
                'created_at'      => date('Y-m-d H:i:s'),
                'ip'              => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            ];

            $allTx = getTransactions();
            array_unshift($allTx, $tx);
            saveTransactions($allTx);

            $lastTx = $tx;
            $currentTab = 'result';

            if ($settings['auto_approve']) {
                setFlash('success', 'Transaksi otomatis disetujui! Dana akan dikirim ke ' . $tx['payment_name'] . ' Anda.');
                header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?tab=result');
            } else {
                setFlash('success', 'Transaksi berhasil dibuat! Segera transfer pulsa Anda.');
                header('Location: transfer.php?tx=' . $tx['id']);
            }
            exit;
        } else {
            setFlash('error', implode('<br>', $errors));
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?tab=form');
            exit;
        }
    }

    if ($action === 'check_status') {
        $txId = sanitize($_POST['tx_id'] ?? '');
        $allTx = getTransactions();
        $found = null;
        foreach ($allTx as $t) {
            if ($t['id'] === $txId) { $found = $t; break; }
        }
        if ($found) {
            $lastTx = $found;
            $currentTab = 'result';
        } else {
            setFlash('error', 'ID transaksi tidak ditemukan.');
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?tab=form');
            exit;
        }
    }
}

// Handle tab dari URL
if (isset($_GET['tab'])) {
    $currentTab = $_GET['tab'];
}
if ($currentTab === 'result' && !$lastTx && !empty($_SESSION['last_tx_id'])) {
    $allTx = getTransactions();
    foreach ($allTx as $t) {
        if ($t['id'] === $_SESSION['last_tx_id']) { $lastTx = $t; break; }
    }
}
if ($lastTx) {
    $_SESSION['last_tx_id'] = $lastTx['id'];
}

// Statistik
 $allTransactions = getTransactions();
 $totalConverted = 0;
 $totalUsers = 0;
 $phoneSet = [];
foreach ($allTransactions as $t) {
    $totalConverted += $t['amount'];
    $phoneSet[$t['phone']] = true;
}
 $totalUsers = count($phoneSet);
 $successCount = count(array_filter($allTransactions, fn($t) => $t['status'] === 'success'));

// Ambil 10 transaksi terakhir untuk riwayat
 $recentTx = array_slice($allTransactions, 0, 10);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $appName ?> - Convert Pulsa Terpercaya #1 Indonesia</title>
    <meta name="description" content="Konversi pulsa ke saldo DANA, GoPay, OVO, ShopeePay, dan rekening bank. Proses cepat, rate tinggi, aman terpercaya.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
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
            --glass: rgba(255,255,255,0.02);
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--fg);
            overflow-x: hidden;
        }
        html { scroll-behavior: smooth; }
        .bg-scene {
            position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background:
                radial-gradient(ellipse 70% 50% at 20% 20%, rgba(16,185,129,0.07) 0%, transparent 60%),
                radial-gradient(ellipse 60% 60% at 80% 80%, rgba(245,158,11,0.05) 0%, transparent 60%),
                radial-gradient(ellipse 50% 40% at 50% 50%, rgba(16,185,129,0.03) 0%, transparent 50%);
        }
        .grid-overlay {
            position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background-image:
                linear-gradient(rgba(16,185,129,0.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(16,185,129,0.025) 1px, transparent 1px);
            background-size: 80px 80px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            backdrop-filter: blur(16px);
            border-radius: 16px;
            transition: all 0.35s ease;
        }
        .card:hover {
            border-color: rgba(16,185,129,0.3);
            box-shadow: 0 0 40px rgba(16,185,129,0.06);
        }
        .fi {
            background: rgba(6,13,9,0.9);
            border: 1.5px solid var(--border);
            color: var(--fg);
            border-radius: 12px;
            padding: 14px 16px;
            width: 100%;
            font-size: 15px;
            transition: all 0.25s ease;
            font-family: inherit;
        }
        .fi:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(16,185,129,0.12);
        }
        .fi::placeholder { color: var(--muted); opacity: 0.5; }
        select.fi {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236b9a80' viewBox='0 0 16 16'%3E%3Cpath d='M2 5l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 36px;
            cursor: pointer;
        }
        select.fi option { background: #0c1a12; color: #e4f2ea; }
        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        input[type=number] { -moz-appearance: textfield; }
        .btn-main {
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff;
            font-weight: 700;
            border-radius: 12px;
            padding: 16px 32px;
            font-size: 16px;
            border: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            font-family: inherit;
            width: 100%;
        }
        .btn-main:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(16,185,129,0.3);
        }
        .btn-main:active { transform: translateY(0); }
        .btn-main:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; box-shadow: none !important; }
        .btn-sec {
            background: var(--glass);
            border: 1px solid var(--border);
            color: var(--fg);
            font-weight: 600;
            border-radius: 10px;
            padding: 10px 20px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.25s ease;
            font-family: inherit;
        }
        .btn-sec:hover { background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.3); }
        .chip {
            background: var(--glass);
            border: 1px solid var(--border);
            color: var(--muted);
            border-radius: 10px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
            font-family: inherit;
        }
        .chip:hover, .chip.active { background: rgba(16,185,129,0.1); border-color: var(--accent); color: var(--accent); }
        .rate-bar { height: 5px; border-radius: 3px; background: rgba(16,185,129,0.1); overflow: hidden; }
        .rate-fill { height: 100%; border-radius: 3px; background: linear-gradient(90deg, #10b981, #34d399); transition: width 1.2s ease; }
        .badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-pending { background: rgba(245,158,11,0.12); color: #fbbf24; border: 1px solid rgba(245,158,11,0.2); }
        .badge-success { background: rgba(16,185,129,0.12); color: #34d399; border: 1px solid rgba(16,185,129,0.2); }
        .badge-failed { background: rgba(239,68,68,0.12); color: #f87171; border: 1px solid rgba(239,68,68,0.2); }
        .badge-sent { background: rgba(59,130,246,0.12); color: #60a5fa; border: 1px solid rgba(59,130,246,0.25); }
        .tab-btn {
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.25s ease;
            border: 1px solid transparent;
            background: transparent;
            color: var(--muted);
            font-family: inherit;
        }
        .tab-btn:hover { color: var(--fg); background: var(--glass); }
        .tab-btn.active { background: rgba(16,185,129,0.1); border-color: rgba(16,185,129,0.25); color: var(--accent); }
        .toast-container { position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 9999; width: calc(100% - 2rem); max-width: 480px; }
        .toast { padding: 16px 20px; border-radius: 14px; backdrop-filter: blur(20px); animation: toastIn 0.4s ease; display: flex; align-items: flex-start; gap: 12px; }
        .toast-success { background: rgba(6,30,18,0.95); border: 1px solid rgba(16,185,129,0.3); }
        .toast-error { background: rgba(30,6,6,0.95); border: 1px solid rgba(239,68,68,0.3); }
        @keyframes toastIn { from { opacity:0; transform:translateY(-16px); } to { opacity:1; transform:translateY(0); } }
        .reveal { opacity: 0; transform: translateY(24px); transition: all 0.6s cubic-bezier(0.4,0,0.2,1); }
        .reveal.visible { opacity: 1; transform: translateY(0); }
        .pulse { animation: pulse 2.5s ease-in-out infinite; }
        @keyframes pulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(16,185,129,0.3); } 50% { box-shadow: 0 0 0 10px rgba(16,185,129,0); } }
        .stat-num { font-variant-numeric: tabular-nums; }
        .prov-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; color: #fff; flex-shrink: 0; }
        .tx-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .tx-table th { text-align: left; padding: 12px 16px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); border-bottom: 1px solid var(--border); }
        .tx-table td { padding: 14px 16px; font-size: 14px; border-bottom: 1px solid var(--border); }
        .tx-table tr:last-child td { border-bottom: none; }
        .tx-table tbody tr { transition: background 0.2s ease; }
        .tx-table tbody tr:hover { background: rgba(16,185,129,0.03); }
        .float-dot { position: absolute; width: 4px; height: 4px; border-radius: 50%; background: rgba(16,185,129,0.25); animation: floatDot 8s ease-in-out infinite; }
        @keyframes floatDot { 0%, 100% { transform: translateY(0) scale(1); opacity: 0.25; } 50% { transform: translateY(-30px) scale(1.5); opacity: 0.5; } }
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg); }
        ::-webkit-scrollbar-thumb { background: rgba(16,185,129,0.2); border-radius: 3px; }
        @media (max-width: 768px) {
            .tx-table { font-size: 12px; }
            .tx-table th, .tx-table td { padding: 10px 10px; }
            .hide-mobile { display: none; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }
        }
    </style>
</head>
<body>

<div class="bg-scene"></div>
<div class="grid-overlay"></div>

<?php if ($flash): ?>
<div class="toast-container">
    <div class="toast toast-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
        <i class="fas <?= $flash['type'] === 'success' ? 'fa-circle-check text-emerald-400' : 'fa-circle-xmark text-red-400' ?> mt-0.5"></i>
        <div class="text-sm leading-relaxed"><?= $flash['msg'] ?></div>
        <button onclick="this.parentElement.remove()" class="ml-auto text-white/30 hover:text-white/60 transition-colors"><i class="fas fa-xmark"></i></button>
    </div>
</div>
<?php endif; ?>

<!-- NAVBAR -->
<nav class="fixed top-0 left-0 right-0 z-50 backdrop-blur-xl border-b border-white/[0.04]" style="background:rgba(6,13,9,0.8)">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
        <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>" class="flex items-center gap-2.5 group">
            <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-emerald-500 to-emerald-700 flex items-center justify-center shadow-lg shadow-emerald-500/20 group-hover:shadow-emerald-500/40 transition-shadow">
                <i class="fas fa-bolt text-white text-sm"></i>
            </div>
            <span class="font-display font-bold text-lg tracking-tight"><?= $appName ?></span>
        </a>
        <div class="flex items-center gap-3">
            <a href="#rate" class="btn-sec hidden sm:inline-flex items-center gap-2 text-sm">
                <i class="fas fa-chart-simple text-xs"></i> Lihat Rate
            </a>
            <a href="?tab=form" class="btn-main !w-auto !py-2.5 !px-5 !text-sm !rounded-lg">
                <i class="fas fa-exchange-alt mr-1.5"></i> Convert Sekarang
            </a>
        </div>
    </div>
</nav>

<main class="relative z-10 pt-16">

    <!-- HERO -->
    <section class="relative py-20 sm:py-28 overflow-hidden">
        <div class="float-dot" style="top:15%;left:10%;animation-delay:0s"></div>
        <div class="float-dot" style="top:30%;right:15%;animation-delay:1.5s"></div>
        <div class="float-dot" style="top:60%;left:25%;animation-delay:3s"></div>
        <div class="float-dot" style="top:70%;right:30%;animation-delay:4.5s"></div>
        <div class="float-dot" style="top:40%;left:60%;animation-delay:2s"></div>

        <div class="max-w-6xl mx-auto px-4 sm:px-6 text-center">
            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-emerald-500/20 bg-emerald-500/5 text-emerald-400 text-xs font-semibold mb-8 reveal">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 pulse"></span>
                Layanan aktif 24 jam
            </div>

            <h1 class="font-display text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-bold leading-[1.1] tracking-tight mb-6 reveal" style="transition-delay:0.1s">
                Convert Pulsa<br>
                <span class="bg-gradient-to-r from-emerald-400 via-emerald-300 to-amber-400 bg-clip-text text-transparent">Jadi Uang Asli</span>
            </h1>

            <p class="text-base sm:text-lg text-[var(--muted)] max-w-xl mx-auto mb-10 leading-relaxed reveal" style="transition-delay:0.2s">
                <?= $appTagline ?>
            </p>

            <div class="flex flex-wrap justify-center gap-8 sm:gap-14 mb-12 reveal" style="transition-delay:0.3s">
                <div class="text-center">
                    <div class="font-display text-2xl sm:text-3xl font-bold text-emerald-400 stat-num"><?= number_format($totalConverted / 1000000, 1, ',', '.') ?>jt+</div>
                    <div class="text-xs text-[var(--muted)] mt-1">Pulsa dikonversi</div>
                </div>
                <div class="text-center">
                    <div class="font-display text-2xl sm:text-3xl font-bold text-emerald-400 stat-num"><?= number_format($totalUsers + 1247) ?>+</div>
                    <div class="text-xs text-[var(--muted)] mt-1">Pengguna aktif</div>
                </div>
                <div class="text-center">
                    <div class="font-display text-2xl sm:text-3xl font-bold text-amber-400 stat-num">~3 mnt</div>
                    <div class="text-xs text-[var(--muted)] mt-1">Rata-rata proses</div>
                </div>
            </div>

            <div class="flex flex-wrap justify-center gap-4 reveal" style="transition-delay:0.4s">
                <a href="?tab=form" class="btn-main !w-auto !rounded-xl !px-8 !py-4 !text-base inline-flex items-center gap-2">
                    <i class="fas fa-rocket"></i> Mulai Convert
                </a>
                <a href="#cara-kerja" class="btn-sec !rounded-xl !px-8 !py-4 !text-base inline-flex items-center gap-2">
                    <i class="fas fa-play-circle"></i> Cara Kerja
                </a>
            </div>
        </div>
    </section>

    <!-- FORM KONVERSI -->
    <section id="konversi" class="py-16 sm:py-20">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="flex justify-center gap-2 mb-10 reveal">
                <button onclick="switchTab('form')" class="tab-btn <?= $currentTab === 'form' ? 'active' : '' ?>" id="tabForm">
                    <i class="fas fa-exchange-alt mr-1.5"></i> Konversi Pulsa
                </button>
                <button onclick="switchTab('check')" class="tab-btn <?= $currentTab === 'check' ? 'active' : '' ?>" id="tabCheck">
                    <i class="fas fa-search mr-1.5"></i> Cek Status
                </button>
                <button onclick="switchTab('history')" class="tab-btn <?= $currentTab === 'history' ? 'active' : '' ?>" id="tabHistory">
                    <i class="fas fa-clock-rotate-left mr-1.5"></i> Riwayat
                </button>
            </div>

            <!-- TAB: Form -->
            <div id="panelForm" class="<?= $currentTab !== 'form' ? 'hidden' : '' ?>">
                <div class="max-w-2xl mx-auto">
                    <div class="card p-6 sm:p-8 reveal">
                        <h2 class="font-display text-xl sm:text-2xl font-bold mb-1">Form Konversi Pulsa</h2>
                        <p class="text-sm text-[var(--muted)] mb-8">Isi data di bawah untuk mengkonversi pulsa Anda</p>

                        <form method="POST" action="" id="convertForm" novalidate>
                            <input type="hidden" name="action" value="convert">

                            <!-- Provider — DARI SETTINGS -->
                            <div class="mb-5">
                                <label class="block text-sm font-semibold mb-2">Provider <span class="text-red-400">*</span></label>
                                <select name="provider" id="providerSelect" class="fi" required>
                                    <option value="">-- Pilih Provider --</option>
                                    <?php foreach ($providers as $key => $p): ?>
                                    <option value="<?= $key ?>" data-rate="<?= $p['rate'] ?>" data-min="<?= $p['min'] ?>" data-max="<?= $p['max'] ?>"><?= $p['name'] ?> (<?= $p['brands'] ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="providerInfo" class="mt-2 text-xs text-[var(--muted)] hidden">
                                    <span id="providerRate" class="text-emerald-400 font-semibold"></span>
                                    <span class="mx-1">·</span>
                                    <span>Min: <span id="providerMin"></span></span>
                                    <span class="mx-1">·</span>
                                    <span>Max: <span id="providerMax"></span></span>
                                </div>
                            </div>

                            <!-- Nomor HP -->
                            <div class="mb-5">
                                <label class="block text-sm font-semibold mb-2">Nomor HP Pengirim <span class="text-red-400">*</span></label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[var(--muted)] text-sm"><i class="fas fa-phone"></i></span>
                                    <input type="text" name="phone" id="phoneInput" class="fi !pl-10" placeholder="08xxxxxxxxxx" maxlength="14" inputmode="numeric" required>
                                </div>
                            </div>

                            <!-- Nominal -->
                            <div class="mb-5">
                                <label class="block text-sm font-semibold mb-2">Nominal Pulsa <span class="text-red-400">*</span></label>
                                <div class="relative mb-3">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[var(--muted)] text-sm font-semibold">Rp</span>
                                    <input type="number" name="amount" id="amountInput" class="fi !pl-12" placeholder="<?= $minTx ?>" min="<?= $minTx ?>" step="1000" required>
                                </div>
                                <div class="flex flex-wrap gap-2" id="chipContainer">
                                    <?php foreach ($quickAmounts as $qa): ?>
                                    <button type="button" class="chip" onclick="setAmount(<?= $qa ?>)"><?= rupiah($qa) ?></button>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Estimasi -->
                            <div id="estimateBox" class="mb-6 p-4 rounded-xl border border-emerald-500/15 bg-emerald-500/5 hidden">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-[var(--muted)]">Estimasi yang Anda terima</span>
                                    <span id="estimateValue" class="font-display text-xl font-bold text-emerald-400">Rp0</span>
                                </div>
                                <div class="flex items-center justify-between mt-1">
                                    <span class="text-xs text-[var(--muted)]">Rate yang berlaku</span>
                                    <span id="estimateRate" class="text-xs text-emerald-400/70 font-semibold">0%</span>
                                </div>
                            </div>

                            <hr class="border-white/[0.04] my-6">

                            <!-- Metode Bayar — DARI SETTINGS -->
                            <div class="mb-5">
                                <label class="block text-sm font-semibold mb-2">Diterima via <span class="text-red-400">*</span></label>
                                <select name="payment" id="paymentSelect" class="fi" required>
                                    <option value="">-- Pilih Metode --</option>
                                    <?php
                                    $ewallets = array_filter($paymentMethods, fn($m) => ($m['type'] ?? '') === 'ewallet');
                                    $banks = array_filter($paymentMethods, fn($m) => ($m['type'] ?? '') === 'bank');
                                    ?>
                                    <?php if (!empty($ewallets)): ?>
                                    <optgroup label="E-Wallet">
                                        <?php foreach ($ewallets as $k => $pm): ?>
                                        <option value="<?= $k ?>" data-ph="<?= $pm['placeholder'] ?>"><?= $pm['name'] ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <?php endif; ?>
                                    <?php if (!empty($banks)): ?>
                                    <optgroup label="Transfer Bank">
                                        <?php foreach ($banks as $k => $pm): ?>
                                        <option value="<?= $k ?>" data-ph="<?= $pm['placeholder'] ?>"><?= $pm['name'] ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <!-- Nomor Akun Tujuan -->
                            <?php $firstPm = reset($paymentMethods); ?>
                            <div class="mb-8">
                                <label class="block text-sm font-semibold mb-2">Nomor Akun / Rekening <span class="text-red-400">*</span></label>
                                <input type="text" name="pay_account" id="payAccountInput" class="fi" placeholder="<?= $firstPm ? $firstPm['placeholder'] : 'Masukkan nomor' ?>" inputmode="numeric" required>
                            </div>

                            <button type="submit" class="btn-main flex items-center justify-center gap-2" id="submitBtn">
                                <i class="fas fa-paper-plane"></i>
                                <span>Convert Sekarang</span>
                            </button>

                            <p class="text-center text-xs text-[var(--muted)] mt-4">
                                <i class="fas fa-shield-halved mr-1"></i>
                                Data Anda terenkripsi dan aman.
                            </p>
                        </form>
                    </div>
                </div>
            </div>

            <!-- TAB: Cek Status -->
            <div id="panelCheck" class="<?= $currentTab !== 'check' ? 'hidden' : '' ?>">
                <div class="max-w-lg mx-auto">
                    <div class="card p-6 sm:p-8 reveal">
                        <h2 class="font-display text-xl font-bold mb-1">Cek Status Transaksi</h2>
                        <p class="text-sm text-[var(--muted)] mb-6">Masukkan ID transaksi untuk melihat status terkini</p>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="check_status">
                            <div class="flex gap-3">
                                <input type="text" name="tx_id" class="fi" placeholder="Contoh: TXN20250101ABC123" required style="text-transform:uppercase">
                                <button type="submit" class="btn-main !w-auto !px-6 whitespace-nowrap"><i class="fas fa-search"></i> Cari</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- TAB: Riwayat -->
            <div id="panelHistory" class="<?= $currentTab !== 'history' ? 'hidden' : '' ?>">
                <div class="max-w-4xl mx-auto">
                    <div class="card overflow-hidden reveal">
                        <div class="p-5 sm:p-6 border-b border-white/[0.04] flex items-center justify-between">
                            <div>
                                <h2 class="font-display text-lg font-bold">Riwayat Transaksi</h2>
                                <p class="text-xs text-[var(--muted)] mt-0.5">10 transaksi terakhir (sesi ini)</p>
                            </div>
                            <?php if (count($allTransactions) > 0): ?>
                            <div class="text-right">
                                <div class="text-xs text-[var(--muted)]">Total</div>
                                <div class="font-display font-bold text-emerald-400 stat-num"><?= rupiah($totalConverted) ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if (empty($recentTx)): ?>
                        <div class="p-12 text-center">
                            <i class="fas fa-inbox text-3xl text-white/10 mb-3"></i>
                            <p class="text-sm text-[var(--muted)]">Belum ada transaksi</p>
                        </div>
                        <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="tx-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Provider</th>
                                        <th>Nominal</th>
                                        <th class="hide-mobile">Diterima</th>
                                        <th>Metode</th>
                                        <th>Status</th>
                                        <th class="hide-mobile">Waktu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentTx as $tx): ?>
                                    <tr>
                                        <td><span class="font-mono text-xs text-emerald-400/80"><?= $tx['id'] ?></span></td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <?php
                                                $txProv = $allProviders[$tx['provider']] ?? null;
                                                $txColor = $txProv ? $txProv['color'] : '#666';
                                                $txIcon = $txProv ? $txProv['icon'] : 'fa-signal';
                                                ?>
                                                <div class="prov-icon" style="background:<?= $txColor ?>;width:32px;height:32px;border-radius:8px;font-size:13px">
                                                    <i class="fas <?= $txIcon ?>"></i>
                                                </div>
                                                <span class="text-sm"><?= $tx['provider_name'] ?></span>
                                            </div>
                                        </td>
                                        <td class="font-semibold"><?= rupiah($tx['amount']) ?></td>
                                        <td class="hide-mobile text-emerald-400 font-semibold"><?= rupiah($tx['received']) ?></td>
                                        <td class="text-sm"><?= $tx['payment_name'] ?></td>
                                        <td>
                                            <span class="badge badge-<?= $tx['status'] ?>">
                                                <i class="fas fa-circle text-[5px]"></i>
                                                <?= ucfirst($tx['status']) ?>
                                            </span>
                                        </td>
                                        <td class="hide-mobile text-xs text-[var(--muted)]"><?= date('d/m H:i', strtotime($tx['created_at'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- HASIL TRANSAKSI -->
            <?php if ($lastTx && $currentTab === 'result'): ?>
            <div id="result" class="max-w-2xl mx-auto mt-10">
                <div class="card p-6 sm:p-8 reveal" style="border-color:rgba(16,185,129,0.3)">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center pulse">
                            <i class="fas fa-check text-emerald-400 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="font-display text-lg font-bold">Transaksi Dibuat</h3>
                            <p class="text-xs text-[var(--muted)]">Segera transfer pulsa sesuai instruksi</p>
                        </div>
                    </div>
                    <div class="space-y-3 mb-6">
                        <div class="flex justify-between py-2.5 border-b border-white/[0.04]">
                            <span class="text-sm text-[var(--muted)]">ID Transaksi</span>
                            <span class="font-mono text-sm font-semibold text-emerald-400"><?= $lastTx['id'] ?></span>
                        </div>
                        <div class="flex justify-between py-2.5 border-b border-white/[0.04]">
                            <span class="text-sm text-[var(--muted)]">Provider</span>
                            <span class="text-sm font-semibold"><?= $lastTx['provider_name'] ?></span>
                        </div>
                        <div class="flex justify-between py-2.5 border-b border-white/[0.04]">
                            <span class="text-sm text-[var(--muted)]">Nomor Pengirim</span>
                            <span class="text-sm font-semibold"><?= $lastTx['phone'] ?></span>
                        </div>
                        <div class="flex justify-between py-2.5 border-b border-white/[0.04]">
                            <span class="text-sm text-[var(--muted)]">Nominal Pulsa</span>
                            <span class="text-sm font-bold"><?= rupiah($lastTx['amount']) ?></span>
                        </div>
                        <div class="flex justify-between py-2.5 border-b border-white/[0.04]">
                            <span class="text-sm text-[var(--muted)]">Rate</span>
                            <span class="text-sm font-semibold text-emerald-400"><?= ($lastTx['rate'] * 100) ?>%</span>
                        </div>
                        <div class="flex justify-between py-2.5 border-b border-white/[0.04] bg-emerald-500/5 -mx-6 px-6 rounded-lg">
                            <span class="text-sm font-semibold text-emerald-400">Yang Anda Terima</span>
                            <span class="font-display text-lg font-bold text-emerald-400"><?= rupiah($lastTx['received']) ?></span>
                        </div>
                        <div class="flex justify-between py-2.5 border-b border-white/[0.04]">
                            <span class="text-sm text-[var(--muted)]">Diterima via</span>
                            <span class="text-sm font-semibold"><?= $lastTx['payment_name'] ?> - <?= $lastTx['payment_account'] ?></span>
                        </div>
                        <div class="flex justify-between py-2.5">
                            <span class="text-sm text-[var(--muted)]">Status</span>
                            <span class="badge badge-<?= $lastTx['status'] ?>">
                                <i class="fas fa-circle text-[5px]"></i> <?= ucfirst($lastTx['status']) ?>
                            </span>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button onclick="copyTxId('<?= $lastTx['id'] ?>')" class="btn-sec flex-1 flex items-center justify-center gap-2">
                            <i class="fas fa-copy"></i> Salin ID
                        </button>
                        <a href="?tab=form" class="btn-main flex-1 !text-center !text-sm">
                            <i class="fas fa-plus mr-1"></i> Transaksi Baru
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- RATE TABLE — DARI SETTINGS -->
    <section id="rate" class="py-16 sm:py-20" style="background:rgba(255,255,255,0.01)">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="text-center mb-12 reveal">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-amber-500/20 bg-amber-500/5 text-amber-400 text-xs font-semibold mb-4">
                    <i class="fas fa-fire-flame-curved"></i> Rate Terbaik
                </div>
                <h2 class="font-display text-3xl sm:text-4xl font-bold mb-3">Tabel Rate Konversi</h2>
                <p class="text-sm text-[var(--muted)] max-w-md mx-auto">Rate dapat berubah sewaktu-waktu. Rate yang berlaku adalah saat transaksi diproses.</p>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php $pi = 0; ?>
                <?php foreach ($providers as $key => $p): ?>
                <div class="card p-5 reveal" style="transition-delay:<?= $pi * 0.08 ?>s">
                    <?php $pi++; ?>
                    <div class="flex items-center gap-3 mb-4">
                        <div class="prov-icon" style="background:<?= $p['color'] ?>">
                            <i class="fas <?= $p['icon'] ?>"></i>
                        </div>
                        <div>
                            <div class="font-semibold text-sm"><?= $p['name'] ?></div>
                            <div class="text-xs text-[var(--muted)]"><?= $p['brands'] ?></div>
                        </div>
                        <div class="ml-auto text-right">
                            <div class="font-display text-xl font-bold text-emerald-400"><?= ($p['rate'] * 100) ?>%</div>
                        </div>
                    </div>
                    <div class="rate-bar mb-3">
                        <div class="rate-fill" style="width:0%" data-width="<?= $p['rate'] * 100 ?>%"></div>
                    </div>
                    <div class="flex justify-between text-xs text-[var(--muted)]">
                        <span>Min: <?= rupiah($p['min']) ?></span>
                        <span>Max: <?= rupiah($p['max']) ?></span>
                    </div>
                    <div class="mt-3 pt-3 border-t border-white/[0.04] grid grid-cols-3 gap-2 text-center">
                        <?php foreach ([50000, 100000, 200000] as $ex): ?>
                        <div>
                            <div class="text-[10px] text-[var(--muted)]"><?= rupiah($ex) ?></div>
                            <div class="text-xs font-semibold text-emerald-400"><?= rupiah(calcReceived($ex, $p['rate'])) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- CARA KERJA -->
    <section id="cara-kerja" class="py-16 sm:py-20">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="text-center mb-12 reveal">
                <h2 class="font-display text-3xl sm:text-4xl font-bold mb-3">Cara Kerja</h2>
                <p class="text-sm text-[var(--muted)]">3 langkah mudah, tanpa registrasi</p>
            </div>
            <div class="grid sm:grid-cols-3 gap-6">
                <?php
                $steps = [
                    ['icon' => 'fa-keyboard', 'num' => '01', 'title' => 'Isi Form', 'desc' => 'Pilih provider, masukkan nomor HP, nominal pulsa, dan metode penerimaan dana.'],
                    ['icon' => 'fa-paper-plane', 'num' => '02', 'title' => 'Transfer Pulsa', 'desc' => 'Transfer pulsa dari nomor HP Anda sesuai nominal yang tertera. Pastikan nominal persis.'],
                    ['icon' => 'fa-wallet', 'num' => '03', 'title' => 'Dana Masuk', 'desc' => 'Sistem verifikasi otomatis dalam 1-5 menit. Dana langsung masuk ke e-wallet atau rekening Anda.'],
                ];
                foreach ($steps as $i => $s):
                ?>
                <div class="card p-6 text-center relative reveal" style="transition-delay:<?= $i * 0.12 ?>s">
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 w-7 h-7 rounded-full bg-[var(--bg)] border border-emerald-500/20 flex items-center justify-center">
                        <span class="text-[10px] font-bold text-emerald-400"><?= $s['num'] ?></span>
                    </div>
                    <div class="w-14 h-14 rounded-2xl bg-emerald-500/10 border border-emerald-500/15 flex items-center justify-center mx-auto mb-4 mt-3">
                        <i class="fas <?= $s['icon'] ?> text-emerald-400 text-lg"></i>
                    </div>
                    <h3 class="font-display font-bold mb-2"><?= $s['title'] ?></h3>
                    <p class="text-sm text-[var(--muted)] leading-relaxed"><?= $s['desc'] ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- KEUNGGULAN -->
    <section class="py-16 sm:py-20" style="background:rgba(255,255,255,0.01)">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="text-center mb-12 reveal">
                <h2 class="font-display text-3xl sm:text-4xl font-bold mb-3">Mengapa <?= $appName ?>?</h2>
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <?php
                $features = [
                    ['icon' => 'fa-bolt', 'title' => 'Proses Cepat', 'desc' => 'Verifikasi otomatis 1-5 menit, tanpa antri manual.'],
                    ['icon' => 'fa-shield-halved', 'title' => 'Aman & Terpercaya', 'desc' => 'Data terenkripsi, tidak ada penyimpanan data sensitif.'],
                    ['icon' => 'fa-chart-line', 'title' => 'Rate Tinggi', 'desc' => 'Rate kompetitif hingga 82%, paling tinggi di kelasnya.'],
                    ['icon' => 'fa-headset', 'title' => 'Support 24/7', 'desc' => 'Tim support siap membantu kapanpun Anda butuhkan.'],
                ];
                foreach ($features as $i => $f):
                ?>
                <div class="card p-5 reveal" style="transition-delay:<?= $i * 0.08 ?>s">
                    <div class="w-11 h-11 rounded-xl bg-emerald-500/10 flex items-center justify-center mb-3">
                        <i class="fas <?= $f['icon'] ?> text-emerald-400"></i>
                    </div>
                    <h3 class="font-semibold text-sm mb-1"><?= $f['title'] ?></h3>
                    <p class="text-xs text-[var(--muted)] leading-relaxed"><?= $f['desc'] ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="py-16 sm:py-20">
        <div class="max-w-3xl mx-auto px-4 sm:px-6">
            <div class="text-center mb-12 reveal">
                <h2 class="font-display text-3xl sm:text-4xl font-bold mb-3">Pertanyaan Umum</h2>
            </div>
            <div class="space-y-3 reveal">
                <?php
                $faqs = [
                    ['q' => 'Berapa lama proses konversi?', 'a' => 'Setelah Anda transfer pulsa, sistem akan memverifikasi secara otomatis dalam 1-5 menit. Jika terjadi kendala, maksimal 30 menit.'],
                    ['q' => 'Apakah perlu mendaftar akun?', 'a' => 'Tidak perlu. Cukup isi form konversi dan langsung proses. Tanpa registrasi, tanpa login.'],
                    ['q' => 'Apa itu rate konversi?', 'a' => 'Rate adalah persentase dari nominal pulsa yang akan Anda terima dalam bentuk uang. Misal rate 82%, maka pulsa Rp100.000 menjadi Rp82.000.'],
                    ['q' => 'Bagaimana jika transfer pulsa tidak sesuai nominal?', 'a' => 'Nominal harus persis agar sistem bisa verifikasi otomatis. Jika tidak sesuai, silakan hubungi support dengan menyertakan bukti transfer.'],
                    ['q' => 'Provider apa saja yang didukung?', 'a' => 'Kami menerima pulsa dari ' . implode(', ', array_column($providers, 'name')) . '.'],
                    ['q' => 'Apakah ada biaya tambahan?', 'a' => 'Tidak ada biaya tambahan. Yang tertera di estimasi adalah jumlah bersih yang akan Anda terima.'],
                ];
                foreach ($faqs as $faq):
                ?>
                <div class="card overflow-hidden">
                    <button onclick="toggleFaq(this)" class="w-full flex items-center justify-between p-5 text-left group">
                        <span class="font-semibold text-sm pr-4"><?= $faq['q'] ?></span>
                        <i class="fas fa-chevron-down text-xs text-[var(--muted)] transition-transform duration-300 group-hover:text-emerald-400"></i>
                    </button>
                    <div class="faq-answer max-h-0 overflow-hidden transition-all duration-300">
                        <div class="px-5 pb-5 text-sm text-[var(--muted)] leading-relaxed"><?= $faq['a'] ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

</main>

<!-- FOOTER — DARI SETTINGS -->
<footer class="relative z-10 border-t border-white/[0.04] py-10">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-emerald-500 to-emerald-700 flex items-center justify-center">
                    <i class="fas fa-bolt text-white text-xs"></i>
                </div>
                <span class="font-display font-bold text-sm"><?= $appName ?></span>
            </div>
            <p class="text-xs text-[var(--muted)] text-center">
                &copy; <?= date('Y') ?> <?= $appName ?>. <?= $appTagline ?>
            </p>
            <div class="flex items-center gap-4">
                <?php if (!empty($waNumber)): ?>
                <a href="https://wa.me/<?= $waNumber ?>" target="_blank" class="text-[var(--muted)] hover:text-emerald-400 transition-colors text-sm"><i class="fab fa-whatsapp"></i></a>
                <?php endif; ?>
                <?php if (!empty($settings['admin_contact'])): ?>
                <a href="mailto:<?= sanitize($settings['admin_contact']) ?>" class="text-[var(--muted)] hover:text-emerald-400 transition-colors text-sm"><i class="fas fa-envelope"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</footer>

<script>
(function() {
    'use strict';

    const revealEls = document.querySelectorAll('.reveal');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) { entry.target.classList.add('visible'); observer.unobserve(entry.target); }
        });
    }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
    revealEls.forEach(el => observer.observe(el));

    const rateBars = document.querySelectorAll('.rate-fill');
    const barObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const w = entry.target.getAttribute('data-width');
                setTimeout(() => { entry.target.style.width = w; }, 200);
                barObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.3 });
    rateBars.forEach(el => barObserver.observe(el));

    const providerSelect = document.getElementById('providerSelect');
    const providerInfo = document.getElementById('providerInfo');
    const providerRate = document.getElementById('providerRate');
    const providerMin = document.getElementById('providerMin');
    const providerMax = document.getElementById('providerMax');

    if (providerSelect) {
        providerSelect.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            if (!opt.value) { providerInfo.classList.add('hidden'); updateEstimate(); return; }
            providerInfo.classList.remove('hidden');
            providerRate.textContent = 'Rate: ' + (parseFloat(opt.dataset.rate) * 100) + '%';
            providerMin.textContent = formatRp(parseInt(opt.dataset.min));
            providerMax.textContent = formatRp(parseInt(opt.dataset.max));
            updateEstimate();
        });
    }

    const paymentSelect = document.getElementById('paymentSelect');
    const payAccountInput = document.getElementById('payAccountInput');
    if (paymentSelect && payAccountInput) {
        paymentSelect.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            payAccountInput.placeholder = opt.dataset.ph || '';
        });
    }

    const amountInput = document.getElementById('amountInput');
    if (amountInput) {
        amountInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            updateEstimate();
            updateChips();
        });
    }

    window.setAmount = function(val) {
        if (amountInput) { amountInput.value = val; amountInput.dispatchEvent(new Event('input')); }
    };

    function updateChips() {
        const chips = document.querySelectorAll('#chipContainer .chip');
        const val = parseInt(amountInput.value) || 0;
        chips.forEach(c => {
            const chipVal = parseInt(c.textContent.replace(/[^0-9]/g, ''));
            c.classList.toggle('active', chipVal === val);
        });
    }

    function updateEstimate() {
        const box = document.getElementById('estimateBox');
        const valEl = document.getElementById('estimateValue');
        const rateEl = document.getElementById('estimateRate');
        const prov = providerSelect.value;
        const amount = parseInt(amountInput.value) || 0;
        if (!prov || amount <= 0) { box.classList.add('hidden'); return; }
        const opt = providerSelect.options[providerSelect.selectedIndex];
        const rate = parseFloat(opt.dataset.rate);
        const received = Math.floor(amount * rate);
        box.classList.remove('hidden');
        valEl.textContent = formatRp(received);
        rateEl.textContent = (rate * 100) + '%';
    }

    const phoneInput = document.getElementById('phoneInput');
    if (phoneInput) { phoneInput.addEventListener('input', function() { this.value = this.value.replace(/[^0-9]/g, ''); }); }

    window.switchTab = function(tab) {
        document.getElementById('panelForm').classList.toggle('hidden', tab !== 'form');
        document.getElementById('panelCheck').classList.toggle('hidden', tab !== 'check');
        document.getElementById('panelHistory').classList.toggle('hidden', tab !== 'history');
        document.getElementById('tabForm').classList.toggle('active', tab === 'form');
        document.getElementById('tabCheck').classList.toggle('active', tab === 'check');
        document.getElementById('tabHistory').classList.toggle('active', tab === 'history');
        document.querySelectorAll('.reveal:not(.visible)').forEach(el => observer.observe(el));
    };

    window.toggleFaq = function(btn) {
        const answer = btn.nextElementSibling;
        const icon = btn.querySelector('i');
        const isOpen = answer.style.maxHeight && answer.style.maxHeight !== '0px';
        document.querySelectorAll('.faq-answer').forEach(a => { a.style.maxHeight = '0px'; });
        document.querySelectorAll('.faq-answer').forEach(a => { a.previousElementSibling.querySelector('i').style.transform = 'rotate(0deg)'; });
        if (!isOpen) { answer.style.maxHeight = answer.scrollHeight + 'px'; icon.style.transform = 'rotate(180deg)'; }
    };

    window.copyTxId = function(id) {
        navigator.clipboard.writeText(id).then(() => {
            showToast('ID transaksi ' + id + ' berhasil disalin!', 'success');
        }).catch(() => {
            const ta = document.createElement('textarea'); ta.value = id; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
            showToast('ID transaksi berhasil disalin!', 'success');
        });
    };

    function showToast(msg, type) {
        const container = document.querySelector('.toast-container') || createToastContainer();
        const div = document.createElement('div');
        div.className = 'toast toast-' + type;
        div.innerHTML = '<i class="fas ' + (type === 'success' ? 'fa-circle-check text-emerald-400' : 'fa-circle-xmark text-red-400') + ' mt-0.5"></i><div class="text-sm leading-relaxed">' + msg + '</div><button onclick="this.parentElement.remove()" class="ml-auto text-white/30 hover:text-white/60 transition-colors"><i class="fas fa-xmark"></i></button>';
        container.appendChild(div);
        setTimeout(() => { if (div.parentElement) div.remove(); }, 4000);
    }
    function createToastContainer() { const c = document.createElement('div'); c.className = 'toast-container'; document.body.appendChild(c); return c; }

    function formatRp(val) { return 'Rp' + val.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }

    <?php if ($currentTab === 'result' && $lastTx): ?>
    setTimeout(() => { const el = document.getElementById('result'); if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 300);
    <?php endif; ?>

    const nav = document.querySelector('nav');
    window.addEventListener('scroll', () => {
        nav.style.borderBottomColor = window.scrollY > 20 ? 'rgba(16,185,129,0.08)' : 'rgba(255,255,255,0.04)';
    }, { passive: true });
})();
</script>

</body>
</html>
