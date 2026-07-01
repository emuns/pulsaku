<?php
/**
 * Shared functions untuk API dan Admin
 */

define('DATA_DIR', __DIR__ . '/../data');
define('TX_FILE', DATA_DIR . '/transactions.json');
define('SETTINGS_FILE', DATA_DIR . '/settings.json');
define('LOG_FILE', DATA_DIR . '/activity.log');

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
    $defaults = getDefaultSettings();
    if (!file_exists(SETTINGS_FILE)) { saveSettings($defaults); return $defaults; }
    $d = json_decode(file_get_contents(SETTINGS_FILE), true);
    return is_array($d) ? array_merge($defaults, $d) : $defaults;
}
function saveSettings(array $s): void {
    file_put_contents(SETTINGS_FILE, json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}
function getDefaultSettings(): array {
    return [
        'site_name' => 'PulsaKu', 'site_tagline' => 'Convert Pulsa Terpercaya #1 Indonesia',
        'admin_contact' => 'admin@pulsaku.id', 'whatsapp' => '6281234567890',
        'maintenance' => false, 'auto_approve' => false, 'min_transaction' => 10000,
        'webhook_secret' => '',
        'api_keys' => [],
        'providers' => [
            'telkomsel' => ['name'=>'Telkomsel','brands'=>'Simpati, As, Loop','rate'=>0.82,'min'=>10000,'max'=>1000000,'color'=>'#e4002b','icon'=>'fa-signal','active'=>true],
            'xl' => ['name'=>'XL / Axis','brands'=>'XL, Axis','rate'=>0.80,'min'=>10000,'max'=>1000000,'color'=>'#0064d2','icon'=>'fa-tower-cell','active'=>true],
            'indosat' => ['name'=>'Indosat Ooredoo','brands'=>'IM3, Mentari','rate'=>0.78,'min'=>10000,'max'=>1000000,'color'=>'#ffd500','icon'=>'fa-satellite-dish','active'=>true],
            'tri' => ['name'=>'Tri (3)','brands'=>'3 (Tri)','rate'=>0.75,'min'=>10000,'max'=>500000,'color'=>'#e60012','icon'=>'fa-mobile-screen','active'=>true],
            'smartfren' => ['name'=>'Smartfren','brands'=>'Smartfren','rate'=>0.76,'min'=>10000,'max'=>500000,'color'=>'#ff0000','icon'=>'fa-bolt','active'=>true],
        ],
        'payment_methods' => [
            'dana'=>['name'=>'DANA','icon'=>'fa-wallet','placeholder'=>'08xx atau nomor DANA','active'=>true,'type'=>'ewallet'],
            'gopay'=>['name'=>'GoPay','icon'=>'fa-money-bill','placeholder'=>'08xx atau nomor GoPay','active'=>true,'type'=>'ewallet'],
            'ovo'=>['name'=>'OVO','icon'=>'fa-credit-card','placeholder'=>'08xx atau nomor OVO','active'=>true,'type'=>'ewallet'],
            'shopeepay'=>['name'=>'ShopeePay','icon'=>'fa-bag-shopping','placeholder'=>'08xx atau nomor ShopeePay','active'=>true,'type'=>'ewallet'],
            'bca'=>['name'=>'Bank BCA','icon'=>'fa-building-columns','placeholder'=>'Nomor rekening BCA','active'=>true,'type'=>'bank'],
            'bri'=>['name'=>'Bank BRI','icon'=>'fa-building-columns','placeholder'=>'Nomor rekening BRI','active'=>true,'type'=>'bank'],
            'mandiri'=>['name'=>'Bank Mandiri','icon'=>'fa-building-columns','placeholder'=>'Nomor rekening Mandiri','active'=>true,'type'=>'bank'],
            'bsi'=>['name'=>'Bank BSI','icon'=>'fa-building-columns','placeholder'=>'Nomor rekening BSI','active'=>true,'type'=>'bank'],
        ],
    ];
}
function generateTxId(): string {
    return 'TXN' . date('Ymd') . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
}
function rupiah(int $v): string { return 'Rp' . number_format($v, 0, ',', '.'); }
function sanitize(string $s): string { return htmlspecialchars(trim($s), ENT_QUOTES, 'UTF-8'); }
function writeLog(string $action, string $detail = ''): void {
    $user = $_SESSION['admin_username'] ?? 'api';
    $line = date('Y-m-d H:i:s') . ' | ' . $user . ' | ' . $action . ($detail ? ' | ' . $detail : '') . "\n";
    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}
function maskPhone(string $phone): string {
    if (strlen($phone) < 8) return $phone;
    return substr($phone, 0, 4) . '****' . substr($phone, -3);
}
function getBaseUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

/** JSON response helper */
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/** Parse JSON body */
function getJsonInput(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
