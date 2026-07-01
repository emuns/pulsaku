<?php
/**
 * Router lengkap untuk PHP built-in server di Railway
 * Menangani semua route: root, admin, transfer, dan static files
 */

 $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Normalisasi: hapus trailing slash kecuali root
if ($uri !== '/' && substr($uri, -1) === '/') {
    $uri = rtrim($uri, '/');
}

// =============================================
// KEAMANAN: Blok akses file sensitif
// =============================================
 $blockedPatterns = [
    '/.',           // hidden files (.git, .env, .htaccess)
    '/data/',       // folder data
    '/admin/config.php', // config admin
];
foreach ($blockedPatterns as $pattern) {
    if (stripos($uri, $pattern) === 0) {
        http_response_code(403);
        header('Content-Type: text/plain');
        echo '403 Forbidden';
        return true;
    }
}

// =============================================
// ROUTING: Admin panel
// =============================================
 $adminFiles = [
    '/admin'                  => '/admin/index.php',
    '/admin/index'            => '/admin/index.php',
    '/admin/dashboard'        => '/admin/dashboard.php',
    '/admin/transactions'     => '/admin/transactions.php',
    '/admin/settings'         => '/admin/settings.php',
    '/admin/logout'           => '/admin/logout.php',
];

if (isset($adminFiles[$uri])) {
    $file = __DIR__ . $adminFiles[$uri];
    if (file_exists($file)) {
        require $file;
        return true;
    }
}

// Admin files dengan .php extension langsung
if (preg_match('#^/admin/[a-z]+\.php$#i', $uri)) {
    $file = __DIR__ . $uri;
    if (file_exists($file) && $file !== __DIR__ . '/admin/config.php') {
        require $file;
        return true;
    }
}

// =============================================
// ROUTING: Halaman transfer
// =============================================
if ($uri === '/transfer' || $uri === '/transfer.php') {
    $file = __DIR__ . '/transfer.php';
    if (file_exists($file)) {
        require $file;
        return true;
    }
}

// =============================================
// STATIC FILES: Serve langsung jika ada
// =============================================
if ($uri !== '/') {
    $file = __DIR__ . $uri;

    // Jika file ada dan bukan directory, serve langsung
    if (file_exists($file) && is_file($file)) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        // Hanya boleh serve file types ini
        $allowed = [
            'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico',
            'webp', 'woff', 'woff2', 'ttf', 'eot', 'map'
        ];

        if (in_array($ext, $allowed)) {
            // Set MIME type
            $mimes = [
                'css'  => 'text/css',
                'js'   => 'application/javascript',
                'png'  => 'image/png',
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif'  => 'image/gif',
                'svg'  => 'image/svg+xml',
                'ico'  => 'image/x-icon',
                'webp' => 'image/webp',
                'woff' => 'font/woff',
                'woff2'=> 'font/woff2',
                'ttf'  => 'font/ttf',
                'eot'  => 'application/vnd.ms-fontobject',
                'map'  => 'application/json',
            ];
            if (isset($mimes[$ext])) {
                header('Content-Type: ' . $mimes[$ext]);
            }
            return false; // Biar PHP server yang serve file binary
        }

        // File .php yang ada di root, serve langsung
        if ($ext === 'php' && strpos($uri, '/admin/') === false) {
            require $file;
            return true;
        }
    }
}

// =============================================
// FALLBACK: Semua route lain → index.php
// =============================================
 $_SERVER['PATH_INFO'] = $uri;
require __DIR__ . '/index.php';
return true;
