<?php
/**
 * Halaman Instruksi Transfer Pulsa
 * Ditampilkan setelah user klik "Convert Sekarang"
 */
session_start();

define('DATA_DIR', __DIR__ . '/data');
define('TX_FILE', DATA_DIR . '/transactions.json');
define('SETTINGS_FILE', DATA_DIR . '/settings.json');

if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);

function getTransactions(): array {
    if (!file_exists(TX_FILE)) return [];
    $d = json_decode(file_get_contents(TX_FILE), true);
    return is_array($d) ? $d : [];
}
function saveTransactions(array $txs): void {
    file_put_contents(TX_FILE, json_encode($txs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}
function getSettings(): array {
    if (!file_exists(SETTINGS_FILE)) return [];
    $d = json_decode(file_get_contents(SETTINGS_FILE), true);
    return is_array($d) ? $d : [];
}
function rupiah(int $v): string { return 'Rp' . number_format($v, 0, ',', '.'); }
function sanitize(string $s): string { return htmlspecialchars(trim($s), ENT_QUOTES, 'UTF-8'); }

// Cek ada ID transaksi di URL
 $txId = sanitize($_GET['tx'] ?? '');
if (empty($txId)) {
    header('Location: index.php');
    exit;
}

// Cari transaksi
 $allTx = getTransactions();
 $tx = null;
foreach ($allTx as $t) {
    if ($t['id'] === $txId) { $tx = $t; break; }
}
if (!$tx) {
    header('Location: index.php');
    exit;
}

// Jika sudah bukan pending, redirect ke result
if ($tx['status'] !== 'pending') {
    header('Location: index.php?tab=result');
    exit;
}

// =============================================
// DATA INSTRUKSI TRANSFER PER PROVIDER
// =============================================
 $transferGuides = [
    'telkomsel' => [
        'provider' => 'Telkomsel',
        'color' => '#e4002b',
        'icon' => 'fa-signal',
        'methods' => [
            [
                'title' => 'Via Kode USSD',
                'subtitle' => 'Paling cepat, tanpa aplikasi',
                'icon' => 'fa-hashtag',
                'steps' => [
                    ['text' => 'Buka aplikasi Telepon di HP Anda'],
                    ['text' => 'Ketik kode berikut di dial pad:'],
                    ['code' => '*858*' . $tx['phone'] . '*' . $tx['amount'] . '#'],
                    ['text' => 'Tekan tombol Panggil (hijau)'],
                    ['text' => 'Konfirmasi saat muncul notifikasi'],
                ],
            ],
            [
                'title' => 'Via Aplikasi MyTelkomsel',
                'subtitle' => 'Jika sudah install aplikasi',
                'icon' => 'fa-mobile-screen-button',
                'steps' => [
                    ['text' => 'Buka aplikasi MyTelkomsel'],
                    ['text' => 'Pilih menu "Transfer Pulsa"'],
                    ['text' => 'Masukkan nomor tujuan:'],
                    ['code' => $tx['phone']],
                    ['text' => 'Masukkan nominal:'],
                    ['code' => rupiah($tx['amount'])],
                    ['text' => 'Konfirmasi dan masukkan PIN'],
                ],
            ],
        ],
        'notes' => [
            'Transfer pulsa Telkomsel minimal Rp5.000 per transaksi',
            'Biaya transfer Rp1.000 per transaksi (ditanggung pengirim)',
            'Pastikan sisa pulsa Anda mencukupi setelah biaya admin',
            'Maksimal transfer 5x per hari ke nomor yang sama',
        ],
    ],
    'xl' => [
        'provider' => 'XL / Axis',
        'color' => '#0064d2',
        'icon' => 'fa-tower-cell',
        'methods' => [
            [
                'title' => 'Via Kode USSD',
                'subtitle' => 'Paling cepat, tanpa aplikasi',
                'icon' => 'fa-hashtag',
                'steps' => [
                    ['text' => 'Buka aplikasi Telepon di HP Anda'],
                    ['text' => 'Ketik kode berikut di dial pad:'],
                    ['code' => '*123*' . $tx['phone'] . '*' . $tx['amount'] . '#'],
                    ['text' => 'Tekan tombol Panggil (hijau)'],
                    ['text' => 'Konfirmasi saat muncul notifikasi'],
                ],
            ],
            [
                'title' => 'Via Aplikasi myXL',
                'subtitle' => 'Jika sudah install aplikasi',
                'icon' => 'fa-mobile-screen-button',
                'steps' => [
                    ['text' => 'Buka aplikasi myXL'],
                    ['text' => 'Pilih menu "Bagi Pulsa"'],
                    ['text' => 'Masukkan nomor tujuan:'],
                    ['code' => $tx['phone']],
                    ['text' => 'Masukkan nominal:'],
                    ['code' => rupiah($tx['amount'])],
                    ['text' => 'Konfirmasi transfer'],
                ],
            ],
        ],
        'notes' => [
            'Transfer pulsa XL minimal Rp1.000 per transaksi',
            'Biaya transfer Rp500 per transaksi',
            'Maksimal transfer 10x per hari',
            'Pulsa yang ditransfer tidak bisa digunakan untuk berlangganan paket',
        ],
    ],
    'indosat' => [
        'provider' => 'Indosat Ooredoo',
        'color' => '#ffd500',
        'icon' => 'fa-satellite-dish',
        'methods' => [
            [
                'title' => 'Via Kode USSD',
                'subtitle' => 'Paling cepat, tanpa aplikasi',
                'icon' => 'fa-hashtag',
                'steps' => [
                    ['text' => 'Buka aplikasi Telepon di HP Anda'],
                    ['text' => 'Ketik kode berikut di dial pad:'],
                    ['code' => '*123*' . $tx['phone'] . '*' . $tx['amount'] . '#'],
                    ['text' => 'Tekan tombol Panggil (hijau)'],
                    ['text' => 'Konfirmasi saat muncul notifikasi'],
                ],
            ],
            [
                'title' => 'Via Aplikasi MyIM3',
                'subtitle' => 'Jika sudah install aplikasi',
                'icon' => 'fa-mobile-screen-button',
                'steps' => [
                    ['text' => 'Buka aplikasi MyIM3'],
                    ['text' => 'Pilih menu "Kirim Pulsa"'],
                    ['text' => 'Masukkan nomor tujuan:'],
                    ['code' => $tx['phone']],
                    ['text' => 'Masukkan nominal:'],
                    ['code' => rupiah($tx['amount'])],
                    ['text' => 'Konfirmasi dan masukkan PIN'],
                ],
            ],
        ],
        'notes' => [
            'Transfer pulsa Indosat minimal Rp5.000 per transaksi',
            'Biaya transfer Rp1.000 - Rp1.500 per transaksi',
            'Masa berlaku pulsa yang ditransfer mengikuti pengirim',
            'Maksimal transfer 5x per hari ke nomor yang sama',
        ],
    ],
    'tri' => [
        'provider' => 'Tri (3)',
        'color' => '#e60012',
        'icon' => 'fa-mobile-screen',
        'methods' => [
            [
                'title' => 'Via Kode USSD',
                'subtitle' => 'Paling cepat, tanpa aplikasi',
                'icon' => 'fa-hashtag',
                'steps' => [
                    ['text' => 'Buka aplikasi Telepon di HP Anda'],
                    ['text' => 'Ketik kode berikut di dial pad:'],
                    ['code' => '*111*1*' . $tx['phone'] . '*' . $tx['amount'] . '#'],
                    ['text' => 'Tekan tombol Panggil (hijau)'],
                    ['text' => 'Konfirmasi saat muncul notifikasi'],
                ],
            ],
            [
                'title' => 'Via Aplikasi Bima+',
                'subtitle' => 'Jika sudah install aplikasi',
                'icon' => 'fa-mobile-screen-button',
                'steps' => [
                    ['text' => 'Buka aplikasi Bima+'],
                    ['text' => 'Pilih menu "Kirim Pulsa"'],
                    ['text' => 'Masukkan nomor tujuan:'],
                    ['code' => $tx['phone']],
                    ['text' => 'Masukkan nominal:'],
                    ['code' => rupiah($tx['amount'])],
                    ['text' => 'Konfirmasi transfer'],
                ],
            ],
        ],
        'notes' => [
            'Transfer pulsa Tri minimal Rp1.000 per transaksi',
            'Biaya transfer Rp500 per transaksi',
            'Pulsa yang ditransfer berlaku 7 hari',
            'Maksimal transfer 10x per hari',
        ],
    ],
    'smartfren' => [
        'provider' => 'Smartfren',
        'color' => '#ff0000',
        'icon' => 'fa-bolt',
        'methods' => [
            [
                'title' => 'Via Kode USSD',
                'subtitle' => 'Paling cepat, tanpa aplikasi',
                'icon' => 'fa-hashtag',
                'steps' => [
                    ['text' => 'Buka aplikasi Telepon di HP Anda'],
                    ['text' => 'Ketik kode berikut di dial pad:'],
                    ['code' => '*899*' . $tx['phone'] . '*' . $tx['amount'] . '#'],
                    ['text' => 'Tekan tombol Panggil (hijau)'],
                    ['text' => 'Konfirmasi saat muncul notifikasi'],
                ],
            ],
            [
                'title' => 'Via Aplikasi MySmartfren',
                'subtitle' => 'Jika sudah install aplikasi',
                'icon' => 'fa-mobile-screen-button',
                'steps' => [
                    ['text' => 'Buka aplikasi MySmartfren'],
                    ['text' => 'Pilih menu "Kirim Pulsa"'],
                    ['text' => 'Masukkan nomor tujuan:'],
                    ['code' => $tx['phone']],
                    ['text' => 'Masukkan nominal:'],
                    ['code' => rupiah($tx['amount'])],
                    ['text' => 'Konfirmasi transfer'],
                ],
            ],
        ],
        'notes' => [
            'Transfer pulsa Smartfren minimal Rp5.000 per transaksi',
            'Biaya transfer Rp1.000 per transaksi',
            'Masa berlaku pulsa mengikuti pengirim',
            'Maksimal transfer 5x per hari',
        ],
    ],
];

// Ambil guide berdasarkan provider transaksi
 $guide = $transferGuides[$tx['provider']] ?? null;
if (!$guide) {
    // Fallback generic
    $guide = [
        'provider' => $tx['provider_name'],
        'color' => '#666',
        'icon' => 'fa-signal',
        'methods' => [
            [
                'title' => 'Via Kode USSD',
                'subtitle' => 'Hubungi customer service provider Anda',
                'icon' => 'fa-hashtag',
                'steps' => [
                    ['text' => 'Buka menu transfer pulsa di aplikasi provider Anda'],
                    ['text' => 'Masukkan nomor tujuan:'],
                    ['code' => $tx['phone']],
                    ['text' => 'Masukkan nominal:'],
                    ['code' => rupiah($tx['amount'])],
                    ['text' => 'Konfirmasi transfer'],
                ],
            ],
        ],
        'notes' => [
            'Pastikan nominal transfer persis sesuai',
            'Transfer dari nomor yang terdaftar di form',
            'Tunggu 1-5 menit setelah transfer untuk verifikasi',
        ],
    ];
}

// Proses tombol "Sudah Transfer"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_sent'])) {
    // Update status menjadi "sent" (menunggu verifikasi)
    foreach ($allTx as &$t) {
        if ($t['id'] === $txId) {
            $t['status'] = 'sent';
            $t['sent_at'] = date('Y-m-d H:i:s');
            break;
        }
    }
    unset($t);
    saveTransactions($allTx);
    header('Location: index.php?tab=result');
    exit;
}

// Hitung countdown (15 menit)
 $countdownSeconds = 900;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instruksi Transfer - <?= sanitize($guide['provider']) ?> - PulsaKu</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { display: ['Space Grotesk','sans-serif'], body: ['Plus Jakarta Sans','sans-serif'] } } }
        }
    </script>
    <style>
        :root {
            --bg:#060d09; --bg2:#0c1a12; --fg:#e4f2ea; --muted:#6b9a80;
            --accent:#10b981; --accent2:#f59e0b; --card:rgba(12,26,18,0.85);
            --border:rgba(16,185,129,0.12); --glass:rgba(255,255,255,0.02);
        }
        *{box-sizing:border-box}
        body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--fg);margin:0;min-height:100vh}
        html{scroll-behavior:smooth}
        .bg-scene{position:fixed;inset:0;z-index:0;pointer-events:none;
            background:radial-gradient(ellipse 60% 40% at 30% 20%,rgba(16,185,129,0.06) 0%,transparent 60%),
                        radial-gradient(ellipse 50% 50% at 70% 80%,rgba(245,158,11,0.04) 0%,transparent 60%)}
        .card{background:var(--card);border:1px solid var(--border);backdrop-filter:blur(16px);border-radius:16px}

        /* Step item */
        .step-item{display:flex;gap:14px;align-items:flex-start}
        .step-num{
            width:30px;height:30px;border-radius:50%;flex-shrink:0;
            display:flex;align-items:center;justify-content:center;
            font-size:12px;font-weight:700;
            background:rgba(16,185,129,0.1);border:1.5px solid rgba(16,185,129,0.2);color:var(--accent);
        }
        .step-item.active .step-num{
            background:var(--accent);border-color:var(--accent);color:#fff;
            box-shadow:0 0 16px rgba(16,185,129,0.3);
        }

        /* Code block */
        .code-block{
            background:rgba(6,13,9,0.95);border:1.5px solid rgba(16,185,129,0.2);
            border-radius:12px;padding:14px 18px;font-family:'Courier New',monospace;
            font-size:18px;font-weight:700;color:var(--accent);text-align:center;
            letter-spacing:0.5px;position:relative;cursor:pointer;
            transition:all 0.25s ease;
        }
        .code-block:hover{border-color:rgba(16,185,129,0.4);box-shadow:0 0 20px rgba(16,185,129,0.1)}
        .code-block .copy-hint{
            position:absolute;top:-9px;right:12px;
            background:var(--accent);color:#fff;font-size:9px;font-weight:700;
            padding:2px 8px;border-radius:6px;font-family:'Plus Jakarta Sans',sans-serif;
            letter-spacing:0;opacity:0;transition:opacity 0.2s ease;
        }
        .code-block:hover .copy-hint{opacity:1}

        /* Method tabs */
        .method-tab{
            padding:10px 20px;border-radius:10px;font-size:13px;font-weight:600;
            cursor:pointer;transition:all 0.25s ease;border:1px solid transparent;
            background:transparent;color:var(--muted);font-family:inherit;
            display:inline-flex;align-items:center;gap:8px;
        }
        .method-tab:hover{color:var(--fg);background:var(--glass)}
        .method-tab.active{background:rgba(16,185,129,0.1);border-color:rgba(16,185,129,0.25);color:var(--accent)}

        /* Note item */
        .note-item{display:flex;gap:10px;align-items:flex-start;font-size:13px;color:var(--muted);line-height:1.6}
        .note-item i{color:var(--accent2);margin-top:4px;flex-shrink:0;font-size:10px}

        /* Warning box */
        .warn-box{
            background:rgba(239,68,68,0.05);border:1px solid rgba(239,68,68,0.15);
            border-radius:14px;padding:16px 20px;
        }

        /* Success box */
        .success-box{
            background:rgba(16,185,129,0.05);border:1px solid rgba(16,185,129,0.15);
            border-radius:14px;padding:16px 20px;
        }

        /* Button */
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;
            padding:14px 28px;border-radius:12px;font-size:15px;font-weight:700;
            cursor:pointer;border:none;transition:all 0.3s ease;font-family:inherit;text-decoration:none}
        .btn-primary{background:linear-gradient(135deg,#10b981,#059669);color:#fff}
        .btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 30px rgba(16,185,129,0.3)}
        .btn-ghost{background:var(--glass);border:1px solid var(--border);color:var(--fg)}
        .btn-ghost:hover{background:rgba(16,185,129,0.06);border-color:rgba(16,185,129,0.25)}

        /* Countdown ring */
        .countdown-ring{position:relative;width:64px;height:64px}
        .countdown-ring svg{transform:rotate(-90deg)}
        .countdown-ring .ring-bg{stroke:rgba(255,255,255,0.05);fill:none;stroke-width:4}
        .countdown-ring .ring-fg{fill:none;stroke-width:4;stroke-linecap:round;transition:stroke-dashoffset 1s linear}
        .countdown-text{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;font-variant-numeric:tabular-nums}

        /* Progress bar */
        .progress-track{height:4px;border-radius:2px;background:rgba(255,255,255,0.05);overflow:hidden}
        .progress-fill{height:100%;border-radius:2px;background:linear-gradient(90deg,var(--accent),#34d399);transition:width 1s linear}

        /* Summary row */
        .summary-row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.04)}
        .summary-row:last-child{border-bottom:none}

        /* Pulse dot */
        .pulse-dot{width:8px;height:8px;border-radius:50%;background:var(--accent2);animation:pulseDot 1.5s ease-in-out infinite}
        @keyframes pulseDot{0%,100%{box-shadow:0 0 0 0 rgba(245,158,11,0.4)}50%{box-shadow:0 0 0 8px rgba(245,158,11,0)}}

        /* Toast */
        .toast-box{position:fixed;top:16px;left:50%;transform:translateX(-50%);z-index:100;width:380px;max-width:calc(100vw - 2rem)}
        .toast{padding:12px 18px;border-radius:12px;backdrop-filter:blur(16px);animation:toastIn 0.3s ease;display:flex;align-items:center;gap:10px}
        .toast-copy{background:rgba(6,30,18,0.95);border:1px solid rgba(16,185,129,0.3);color:#34d399;font-size:13px;font-weight:600}
        @keyframes toastIn{from{opacity:0;transform:translateY(-12px)}to{opacity:1;transform:translateY(0)}}

        /* Scrollbar */
        ::-webkit-scrollbar{width:5px}
        ::-webkit-scrollbar-track{background:transparent}
        ::-webkit-scrollbar-thumb{background:rgba(16,185,129,0.15);border-radius:3px}

        @media(max-width:640px){
            .code-block{font-size:14px;padding:12px 14px;letter-spacing:0}
        }
        @media(prefers-reduced-motion:reduce){
            *,*::before,*::after{animation-duration:0.01ms!important;transition-duration:0.01ms!important}
        }
    </style>
</head>
<body>

<div class="bg-scene"></div>

<!-- Toast container -->
<div class="toast-box" id="toastBox"></div>

<div class="relative z-10">

    <!-- Top bar -->
    <div class="sticky top-0 z-30 border-b border-white/[0.04]" style="background:rgba(6,13,9,0.9);backdrop-filter:blur(16px)">
        <div class="max-w-2xl mx-auto px-4 h-14 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-2 text-[var(--muted)] hover:text-[var(--fg)] transition-colors">
                <i class="fas fa-arrow-left text-sm"></i>
                <span class="text-sm font-medium">Kembali</span>
            </a>
            <div class="flex items-center gap-2">
                <div class="pulse-dot"></div>
                <span class="text-xs font-semibold text-amber-400">Menunggu Transfer</span>
            </div>
        </div>
        <!-- Progress bar countdown -->
        <div class="progress-track">
            <div class="progress-fill" id="progressBar" style="width:100%"></div>
        </div>
    </div>

    <div class="max-w-2xl mx-auto px-4 py-6">

        <!-- Header Card -->
        <div class="card p-5 sm:p-6 mb-5">
            <div class="flex items-start gap-4 mb-5">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0" style="background:<?= $guide['color'] ?>20;border:1px solid <?= $guide['color'] ?>30">
                    <i class="fas <?= $guide['icon'] ?> text-lg" style="color:<?= $guide['color'] ?>"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h1 class="font-display text-lg sm:text-xl font-bold mb-0.5">Transfer Pulsa <?= sanitize($guide['provider']) ?></h1>
                    <p class="text-xs text-[var(--muted)]">Ikuti instruksi di bawah untuk menyelesaikan konversi</p>
                </div>
                <!-- Countdown -->
                <div class="countdown-ring flex-shrink-0">
                    <svg width="64" height="64" viewBox="0 0 64 64">
                        <circle class="ring-bg" cx="32" cy="32" r="28"/>
                        <circle class="ring-fg" id="countdownCircle" cx="32" cy="32" r="28"
                            stroke="<?= $guide['color'] ?>"
                            stroke-dasharray="175.93"
                            stroke-dashoffset="0"/>
                    </svg>
                    <div class="countdown-text" id="countdownText" style="color:<?= $guide['color'] ?>">15:00</div>
                </div>
            </div>

            <!-- Summary -->
            <div class="rounded-xl p-4" style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.04)">
                <div class="summary-row">
                    <span class="text-sm text-[var(--muted)]">ID Transaksi</span>
                    <span class="text-sm font-mono font-semibold text-emerald-400 cursor-pointer hover:text-emerald-300" onclick="copyText('<?= $tx['id'] ?>')"><?= $tx['id'] ?></span>
                </div>
                <div class="summary-row">
                    <span class="text-sm text-[var(--muted)]">Nomor Tujuan</span>
                    <span class="text-sm font-semibold font-mono cursor-pointer hover:text-emerald-300" onclick="copyText('<?= $tx['phone'] ?>')"><?= $tx['phone'] ?></span>
                </div>
                <div class="summary-row">
                    <span class="text-sm text-[var(--muted)]">Nominal Transfer</span>
                    <span class="text-sm font-bold cursor-pointer hover:text-emerald-300" onclick="copyText('<?= $tx['amount'] ?>')"><?= rupiah($tx['amount']) ?></span>
                </div>
                <div class="summary-row" style="border-bottom:none;background:rgba(16,185,129,0.04);margin:0 -16px -16px;padding:12px 16px;border-radius:0 0 12px 12px">
                    <span class="text-sm font-semibold text-emerald-400">Yang Anda Terima</span>
                    <span class="font-display text-lg font-bold text-emerald-400"><?= rupiah($tx['received']) ?> <span class="text-xs font-normal text-emerald-400/60">via <?= $tx['payment_name'] ?></span></span>
                </div>
            </div>
        </div>

        <!-- WARNING: Nominal harus persis -->
        <div class="warn-box mb-5 flex items-start gap-3">
            <i class="fas fa-triangle-exclamation text-red-400 mt-0.5"></i>
            <div class="text-sm leading-relaxed">
                <p class="font-semibold text-red-300 mb-1">Nominal Harus Persis!</p>
                <p class="text-red-200/70 text-xs">Transfer <strong class="text-red-300"><?= rupiah($tx['amount']) ?></strong> — bukan lebih dan bukan kurang. Jika nominal tidak sesuai, sistem tidak bisa memverifikasi secara otomatis.</p>
            </div>
        </div>

        <!-- Method Tabs -->
        <div class="flex gap-2 mb-5 overflow-x-auto pb-1 -mx-4 px-4" id="methodTabs">
            <?php foreach ($guide['methods'] as $i => $m): ?>
            <button onclick="switchMethod(<?= $i ?>)" class="method-tab <?= $i === 0 ? 'active' : '' ?>" id="methodTab<?= $i ?>">
                <i class="fas <?= $m['icon'] ?>"></i>
                <span><?= $m['title'] ?></span>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- Method Panels -->
        <?php foreach ($guide['methods'] as $mi => $method): ?>
        <div class="card p-5 sm:p-6 mb-5 <?= $mi > 0 ? 'hidden' : '' ?>" id="methodPanel<?= $mi ?>">
            <h2 class="font-display font-bold text-base mb-1"><?= $method['title'] ?></h2>
            <p class="text-xs text-[var(--muted)] mb-6"><?= $method['subtitle'] ?></p>

            <div class="space-y-5">
                <?php $stepNum = 0; ?>
                <?php foreach ($method['steps'] as $si => $step): ?>
                <?php if (isset($step['code'])): ?>
                    <!-- Code Block -->
                    <div class="ml-[44px]">
                        <div class="code-block" onclick="copyText('<?= addslashes($step['code']) ?>')">
                            <span class="copy-hint">SALIN</span>
                            <?= sanitize($step['code']) ?>
                        </div>
                    </div>
                <?php else: ?>
                    <?php $stepNum++; ?>
                    <div class="step-item <?= $si <= 1 ? 'active' : '' ?>">
                        <div class="step-num"><?= $stepNum ?></div>
                        <div class="text-sm leading-relaxed pt-1"><?= $step['text'] ?></div>
                    </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Catatan Penting -->
        <div class="card p-5 sm:p-6 mb-5">
            <h3 class="font-semibold text-sm mb-4 flex items-center gap-2">
                <i class="fas fa-circle-info text-[var(--accent2)] text-xs"></i>
                Catatan Penting
            </h3>
            <div class="space-y-3">
                <?php foreach ($guide['notes'] as $note): ?>
                <div class="note-item">
                    <i class="fas fa-circle"></i>
                    <span><?= $note ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Checklist -->
        <div class="card p-5 sm:p-6 mb-5">
            <h3 class="font-semibold text-sm mb-4 flex items-center gap-2">
                <i class="fas fa-clipboard-check text-emerald-400 text-xs"></i>
                Checklist Sebelum Transfer
            </h3>
            <div class="space-y-3" id="checklist">
                <?php
                $checks = [
                    ['id' => 'c1', 'text' => 'Saya memastikan nominal transfer persis <strong>' . rupiah($tx['amount']) . '</strong>'],
                    ['id' => 'c2', 'text' => 'Saya mentransfer dari nomor <strong>' . $tx['phone'] . '</strong>'],
                    ['id' => 'c3', 'text' => 'Saya sudah siap menerima dana via <strong>' . $tx['payment_name'] . '</strong> ke nomor <strong>' . $tx['payment_account'] . '</strong>'],
                ];
                foreach ($checks as $c):
                ?>
                <label class="flex items-start gap-3 cursor-pointer group" for="<?= $c['id'] ?>">
                    <div class="mt-0.5 w-5 h-5 rounded-md border-2 border-white/10 flex items-center justify-center flex-shrink-0 transition-all group-hover:border-emerald-500/40" id="<?= $c['id'] ?>_box">
                        <i class="fas fa-check text-[10px] text-emerald-400 opacity-0 transition-opacity" id="<?= $c['id'] ?>_check"></i>
                    </div>
                    <input type="checkbox" id="<?= $c['id'] ?>" class="hidden" onchange="toggleCheck('<?= $c['id'] ?>')">
                    <span class="text-sm text-[var(--muted)] group-hover:text-[var(--fg)] transition-colors leading-relaxed"><?= $c['text'] ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="space-y-3 mb-8">
            <form method="POST" action="" id="sentForm" onsubmit="return confirm('Sudah yakin sudah transfer pulsa?')">
                <button type="submit" name="mark_sent" value="1" class="btn btn-primary w-full" id="btnSent" disabled>
                    <i class="fas fa-check-circle"></i>
                    <span>Saya Sudah Transfer Pulsa</span>
                </button>
            </form>

            <div class="success-box flex items-start gap-3">
                <i class="fas fa-clock text-emerald-400 mt-0.5"></i>
                <div class="text-xs leading-relaxed text-emerald-200/70">
                    Setelah klik "Saya Sudah Transfer", sistem akan memverifikasi dalam <strong class="text-emerald-300">1-5 menit</strong>. Dana akan dikirim ke <?= $tx['payment_name'] ?> Anda setelah verifikasi berhasil.
                </div>
            </div>

            <div class="flex gap-3">
                <a href="index.php" class="btn btn-ghost flex-1 !text-sm !py-3">
                    <i class="fas fa-arrow-left"></i> Batal
                </a>
                <button onclick="copyText('<?= $tx['id'] ?>')" class="btn btn-ghost flex-1 !text-sm !py-3">
                    <i class="fas fa-copy"></i> Salin ID Transaksi
                </button>
            </div>
        </div>

        <!-- Butuh Bantuan -->
        <div class="text-center pb-10">
            <p class="text-xs text-[var(--muted)] mb-2">Butuh bantuan?</p>
            <a href="https://wa.me/6281234567890?text=Halo,%20saya%20butuh%20bantuan%20transaksi%20<?= urlencode($tx['id']) ?>" target="_blank"
               class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-400 hover:text-emerald-300 transition-colors">
                <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
            </a>
        </div>

    </div>
</div>

<script>
(function() {
    'use strict';

    // === Countdown Timer (15 menit) ===
    const TOTAL = 900; // 15 * 60
    const CIRCUMFERENCE = 2 * Math.PI * 28; // ~175.93
    let remaining = TOTAL;

    const circle = document.getElementById('countdownCircle');
    const text = document.getElementById('countdownText');
    const bar = document.getElementById('progressBar');

    circle.style.strokeDasharray = CIRCUMFERENCE;

    function updateCountdown() {
        if (remaining <= 0) {
            clearInterval(timer);
            text.textContent = '00:00';
            circle.style.strokeDashoffset = CIRCUMFERENCE;
            bar.style.width = '0%';
            return;
        }
        remaining--;
        const m = Math.floor(remaining / 60);
        const s = remaining % 60;
        text.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        circle.style.strokeDashoffset = CIRCUMFERENCE * (1 - remaining / TOTAL);
        bar.style.width = (remaining / TOTAL * 100) + '%';

        // Ubah warna jika < 2 menit
        if (remaining < 120) {
            circle.style.stroke = '#ef4444';
            text.style.color = '#ef4444';
            bar.style.background = 'linear-gradient(90deg,#ef4444,#f87171)';
        } else if (remaining < 300) {
            circle.style.stroke = '#f59e0b';
            text.style.color = '#f59e0b';
            bar.style.background = 'linear-gradient(90deg,#f59e0b,#fbbf24)';
        }
    }

    const timer = setInterval(updateCountdown, 1000);

    // === Method Tabs ===
    window.switchMethod = function(idx) {
        const totalMethods = <?= count($guide['methods']) ?>;
        for (let i = 0; i < totalMethods; i++) {
            document.getElementById('methodTab' + i).classList.toggle('active', i === idx);
            const panel = document.getElementById('methodPanel' + i);
            panel.classList.toggle('hidden', i !== idx);
        }
    };

    // === Checklist ===
    let checkedCount = 0;
    const totalChecks = 3;

    window.toggleCheck = function(id) {
        const input = document.getElementById(id);
        const box = document.getElementById(id + '_box');
        const check = document.getElementById(id + '_check');
        const isChecked = input.checked;

        box.style.borderColor = isChecked ? 'rgba(16,185,129,0.5)' : 'rgba(255,255,255,0.1)';
        box.style.background = isChecked ? 'rgba(16,185,129,0.1)' : 'transparent';
        check.style.opacity = isChecked ? '1' : '0';

        checkedCount = document.querySelectorAll('#checklist input:checked').length;
        const btn = document.getElementById('btnSent');
        btn.disabled = checkedCount < totalChecks;
        btn.style.opacity = checkedCount < totalChecks ? '0.4' : '1';
        btn.style.cursor = checkedCount < totalChecks ? 'not-allowed' : 'pointer';
    };

    // === Copy to Clipboard ===
    window.copyText = function(text) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('Disalin: ' + text);
        }).catch(() => {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            showToast('Disalin: ' + text);
        });
    };

    // === Toast ===
    function showToast(msg) {
        const box = document.getElementById('toastBox');
        const div = document.createElement('div');
        div.className = 'toast toast-copy';
        div.innerHTML = '<i class="fas fa-circle-check"></i> ' + msg;
        box.appendChild(div);
        setTimeout(() => { if (div.parentElement) div.remove(); }, 2500);
    }

    // === Highlight steps saat scroll ===
    const stepItems = document.querySelectorAll('.step-item');
    const stepObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('active');
            }
        });
    }, { threshold: 0.5, rootMargin: '0px 0px -30px 0px' });
    stepItems.forEach(el => stepObserver.observe(el));

    // === Peringatan sebelum tinggalkan halaman ===
    window.addEventListener('beforeunload', function(e) {
        if (remaining > 0 && remaining < TOTAL) {
            e.preventDefault();
            e.returnValue = 'Proses transfer belum selesai. Yakin ingin meninggalkan halaman?';
        }
    });

})();
</script>

</body>
</html>
