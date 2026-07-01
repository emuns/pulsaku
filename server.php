<?php
/**
 * Router lengkap untuk PHP built-in server di Railway
 */

 $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($uri !== '/' && substr($uri, -1) === '/') {
    $uri = rtrim($uri, '/');
}

// =============================================
// KEAMANAN: Blok akses file sensitif
// =============================================
 $blockedPatterns = ['/.', '/data/', '/admin/config.php'];
foreach ($blockedPatterns as $pattern) {
    if (stripos($uri, $pattern) === 0) {
        http_response_code(403);
        header('Content-Type: text/plain');
        echo '403 Forbidden';
        return true;
    }
}

// =============================================
// ROUTING: API
// =============================================
if (preg_match('#^/api(/.*)?$#', $uri)) {
    require __DIR__ . '/api/index.php';
    return true;
}

// =============================================
// ROUTING: Admin panel
// =============================================
 $adminFiles = [
    '/admin'              => '/admin/index.php',
    '/admin/index'        => '/admin/index.php',
    '/admin/dashboard'    => '/admin/dashboard.php',
    '/admin/transactions' => '/admin/transactions.php',
    '/admin/settings'     => '/admin/settings.php',
    '/admin/api-keys'     => '/admin/api_keys.php',
    '/admin/logout'       => '/admin/logout.php',
];

if (isset($adminFiles[$uri])) {
    $file = __DIR__ . $adminFiles[$uri];
    if (file_exists($file)) { require $file; return true; }
}

if (preg_match('#^/admin/[a-z_]+\.php$#i', $uri)) {
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
    if (file_exists($file)) { require $file; return true; }
}

// =============================================
// STATIC FILES
// =============================================
if ($uri !== '/') {
    $file = __DIR__ . $uri;
    if (file_exists($file) && is_file($file)) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $allowed = ['css','js','png','jpg','jpeg','gif','svg','ico','webp','woff','woff2','ttf','eot','map'];
        if (in_array($ext, $allowed)) {
            $mimes = [
                'css'=>'text/css','js'=>'application/javascript','png'=>'image/png',
                'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif',
                'svg'=>'image/svg+xml','ico'=>'image/x-icon','webp'=>'image/webp',
                'woff'=>'font/woff','woff2'=>'font/woff2','ttf'=>'font/ttf',
                'eot'=>'application/vnd.ms-fontobject','map'=>'application/json',
            ];
            if (isset($mimes[$ext])) header('Content-Type: ' . $mimes[$ext]);
            return false;
        }
        if ($ext === 'php' && strpos($uri, '/admin/') === false) {
            require $file;
            return true;
        }
    }
}

// =============================================
// FALLBACK: index.php
// =============================================
 $_SERVER['PATH_INFO'] = $uri;
require __DIR__ . '/index.php';
return true;
