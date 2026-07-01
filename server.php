<?php
/**
 * Router untuk PHP built-in server
 * Menangani static files dan fallback ke index.php
 */

 $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Blok akses langsung ke file data/sensitif
 $blocked = ['/data/', '/.git/', '/.env', '/admin/config.php'];
foreach ($blocked as $b) {
    if (stripos($uri, $b) === 0) {
        http_response_code(403);
        echo 'Forbidden';
        return true;
    }
}

// Jika file statis ada, serve langsung
 $publicPath = __DIR__ . $uri;
if ($uri !== '/' && file_exists($publicPath) && !is_dir($publicPath)) {
    // Hanya serve file yang aman
    $ext = strtolower(pathinfo($publicPath, PATHINFO_EXTENSION));
    $allowedExts = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'webp', 'woff', 'woff2', 'ttf', 'eot'];
    if (in_array($ext, $allowedExts)) {
        return false; // Biar PHP built-in server yang handle
    }
}

// Fallback ke index.php
 $_SERVER['PATH_INFO'] = $uri;
require __DIR__ . '/index.php';
