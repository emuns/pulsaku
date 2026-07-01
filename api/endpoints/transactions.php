<?php
/**
 * GET  /api/transactions          → List transaksi (dengan filter)
 * POST /api/transactions          → Buat transaksi baru
 */

 $method = $_SERVER['REQUEST_METHOD'];

// =============================================
// GET: List Transaksi
// =============================================
if ($method === 'GET') {
    $allTx = getTransactions();

    // Filter
    $status  = $_GET['status'] ?? null;
    $provider = $_GET['provider'] ?? null;
    $phone   = $_GET['phone'] ?? null;
    $limit   = min(100, max(1, intval($_GET['limit'] ?? 20)));
    $offset  = max(0, intval($_GET['offset'] ?? 0));

    if ($status)  $allTx = array_filter($allTx, fn($t) => $t['status'] === $status);
    if ($provider) $allTx = array_filter($allTx, fn($t) => $t['provider'] === $provider);
    if ($phone)   $allTx = array_filter($allTx, fn($t) => $t['phone'] === $phone);

    $allTx = array_values($allTx);
    $total = count($allTx);
    $paged = array_slice($allTx, $offset, $limit);

    // Hapus field sensitif
    $safe = array_map(function($t) {
        return [
            'id'            => $t['id'],
            'provider'      => $t['provider'],
            'provider_name' => $t['provider_name'],
            'phone'         => maskPhone($t['phone']),
            'amount'        => $t['amount'],
            'rate'          => $t['rate'],
            'received'      => $t['received'],
            'payment'       => $t['payment'],
            'payment_name'  => $t['payment_name'],
            'status'        => $t['status'],
            'created_at'    => $t['created_at'],
        ];
    }, $paged);

    jsonResponse([
        'success' => true,
        'data'    => $safe,
        'meta'    => [
            'total'  => $total,
            'limit'  => $limit,
            'offset' => $offset,
        ],
    ]);
}

// =============================================
// POST: Buat Transaksi Baru
// =============================================
if ($method === 'POST') {
    $input = getJsonInput();

    $provider   = sanitize($input['provider'] ?? '');
    $phone      = sanitize($input['phone'] ?? '');
    $amount     = intval($input['amount'] ?? 0);
    $payment    = sanitize($input['payment'] ?? '');
    $payAccount = sanitize($input['pay_account'] ?? '');

    $settings = getSettings();
    $errors = [];

    if (empty($provider) || !isset($settings['providers'][$provider]) || empty($settings['providers'][$provider]['active'])) {
        $errors[] = 'Provider tidak valid atau tidak aktif.';
    }
    if (empty($phone) || !preg_match('/^08[0-9]{8,13}$/', $phone)) {
        $errors[] = 'Nomor HP tidak valid.';
    }
    if ($amount < $settings['min_transaction']) {
        $errors[] = 'Nominal di bawah minimum (' . $settings['min_transaction'] . ').';
    }
    if (isset($settings['providers'][$provider]) && $amount > $settings['providers'][$provider]['max']) {
        $errors[] = 'Nominal melebihi maksimal provider.';
    }
    if ($amount % 1000 !== 0) {
        $errors[] = 'Nominal harus kelipatan 1000.';
    }
    if (empty($payment) || !isset($settings['payment_methods'][$payment]) || empty($settings['payment_methods'][$payment]['active'])) {
        $errors[] = 'Metode pembayaran tidak valid.';
    }
    if (empty($payAccount)) {
        $errors[] = 'Nomor akun tujuan wajib diisi.';
    }

    if (!empty($errors)) {
        jsonResponse(['success' => false, 'error' => 'Validasi gagal', 'details' => $errors], 422);
    }

    $prov = $settings['providers'][$provider];
    $received = (int) floor($amount * $prov['rate']);
    $initialStatus = !empty($settings['auto_approve']) ? 'success' : 'pending';

    $tx = [
        'id'              => generateTxId(),
        'provider'        => $provider,
        'provider_name'   => $prov['name'],
        'phone'           => $phone,
        'amount'          => $amount,
        'rate'            => $prov['rate'],
        'received'        => $received,
        'payment'         => $payment,
        'payment_name'    => $settings['payment_methods'][$payment]['name'],
        'payment_account' => $payAccount,
        'status'          => $initialStatus,
        'source'          => 'api',
        'created_at'      => date('Y-m-d H:i:s'),
        'ip'              => $_SERVER['REMOTE_ADDR'] ?? 'api',
    ];

    $allTx = getTransactions();
    array_unshift($allTx, $tx);
    saveTransactions($allTx);

    writeLog('API_CREATE_TX', "Transaksi {$tx['id']} dibuat via API");

    jsonResponse([
        'success' => true,
        'message' => 'Transaksi berhasil dibuat.',
        'data'    => [
            'id'            => $tx['id'],
            'provider'      => $tx['provider_name'],
            'phone'         => $tx['phone'],
            'amount'        => $tx['amount'],
            'rate'          => $tx['rate'],
            'received'      => $tx['received'],
            'payment'       => $tx['payment_name'],
            'status'        => $tx['status'],
            'transfer_url'  => getBaseUrl() . '/transfer.php?tx=' . $tx['id'],
        ],
    ], 201);
}
