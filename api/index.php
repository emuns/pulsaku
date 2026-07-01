<?php
/**
 * API Router - Entry Point
 * Semua request ke /api/* masuk ke sini
 */

require_once __DIR__ . '/../data/functions.php';
require_once __DIR__ . '/auth.php';

// CORS & Preflight
setCorsHeaders();
handlePreflight();

// Parse path
 $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
 $uri = str_replace('/api/', '', $uri);
 $uri = trim($uri, '/');
 $parts = explode('/', $uri);
 $endpoint = $parts[0] ?? '';

// Route ke endpoint yang sesuai
switch ($endpoint) {
    case 'rates':
        authenticateApi();
        require __DIR__ . '/endpoints/rates.php';
        break;

    case 'transactions':
        authenticateApi();
        require __DIR__ . '/endpoints/transactions.php';
        break;

    case 'check':
        authenticateApi();
        require __DIR__ . '/endpoints/check.php';
        break;

    case 'webhook':
        // Webhook pakai secret sendiri, bukan API key
        require __DIR__ . '/endpoints/webhook.php';
        break;

    default:
        jsonResponse([
            'success' => false,
            'error'  => 'Endpoint tidak ditemukan.',
            'docs'   => [
                'GET  /api/rates'                  => 'Daftar provider & rate aktif',
                'GET  /api/transactions'            => 'List transaksi (filter: status, provider, phone, limit, offset)',
                'POST /api/transactions'            => 'Buat transaksi baru (body: provider, phone, amount, payment, pay_account)',
                'GET  /api/check/{tx_id}'           => 'Cek status transaksi',
                'POST /api/webhook'                 => 'Callback update status (body: tx_id, status, note, secret)',
            ],
        ], 404);
        break;
}
