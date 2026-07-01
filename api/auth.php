<?php
/**
 * API Authentication Middleware
 * Memvalidasi API key dari header Authorization: Bearer xxx
 */

require_once __DIR__ . '/../data/functions.php';

function authenticateApi(): array {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (empty($authHeader)) {
        jsonResponse(['success' => false, 'error' => 'Header Authorization tidak ditemukan'], 401);
    }

    // Format: Bearer <api_key>
    if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        jsonResponse(['success' => false, 'error' => 'Format Authorization salah. Gunakan: Bearer <api_key>'], 401);
    }

    $apiKey = trim($matches[1]);

    // Cek key di settings
    $settings = getSettings();
    $keys = $settings['api_keys'] ?? [];

    $found = null;
    foreach ($keys as $k) {
        if (hash_equals($k['key'], $apiKey) && !empty($k['active'])) {
            $found = $k;
            break;
        }
    }

    if (!$found) {
        jsonResponse(['success' => false, 'error' => 'API key tidak valid atau tidak aktif'], 401);
    }

    // Rate limiting sederhana (per key, 60 request/menit)
    $limitFile = DATA_DIR . '/rate_limit_' . substr($apiKey, 0, 8) . '.json';
    $now = time();
    $limitData = ['requests' => [], 'count' => 0];

    if (file_exists($limitFile)) {
        $limitData = json_decode(file_get_contents($limitFile), true) ?: $limitData;
        // Bersihkan request yang sudah lewat 60 detik
        $limitData['requests'] = array_filter($limitData['requests'], fn($t) => $t > $now - 60);
        $limitData['count'] = count($limitData['requests']);
    }

    if ($limitData['count'] >= 60) {
        jsonResponse([
            'success' => false,
            'error' => 'Rate limit tercapai. Maksimal 60 request per menit.',
            'retry_after' => 60 - ($now - min($limitData['requests']))
        ], 429);
    }

    $limitData['requests'][] = $now;
    $limitData['count'] = count($limitData['requests']);
    file_put_contents($limitFile, json_encode($limitData), LOCK_EX);

    // Update last used
    foreach ($settings['api_keys'] as &$k) {
        if ($k['key'] === $apiKey) {
            $k['last_used'] = date('Y-m-d H:i:s');
            $k['total_requests'] = ($k['total_requests'] ?? 0) + 1;
            break;
        }
    }
    unset($k);
    saveSettings($settings);

    return $found;
}

/** Set CORS headers */
function setCorsHeaders(): void {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Max-Age: 86400');
}

/** Handle preflight OPTIONS request */
function handlePreflight(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        setCorsHeaders();
        http_response_code(204);
        exit;
    }
}
