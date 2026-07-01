<?php
/**
 * GET /api/rates
 * Mengembalikan daftar provider aktif beserta rate dan metode pembayaran
 */

 $settings = getSettings();

// Filter hanya yang aktif
 $activeProviders = [];
foreach ($settings['providers'] as $key => $p) {
    if (!empty($p['active'])) {
        $activeProviders[$key] = [
            'id'     => $key,
            'name'   => $p['name'],
            'brands' => $p['brands'],
            'rate'   => $p['rate'],
            'min'    => $p['min'],
            'max'    => $p['max'],
        ];
    }
}

 $activePayments = [];
foreach ($settings['payment_methods'] as $key => $m) {
    if (!empty($m['active'])) {
        $activePayments[$key] = [
            'id'          => $key,
            'name'        => $m['name'],
            'type'        => $m['type'] ?? 'ewallet',
            'placeholder' => $m['placeholder'],
        ];
    }
}

jsonResponse([
    'success'  => true,
    'data'     => [
        'site_name'       => $settings['site_name'],
        'min_transaction' => $settings['min_transaction'],
        'auto_approve'    => !empty($settings['auto_approve']),
        'providers'       => array_values($activeProviders),
        'payment_methods' => array_values($activePayments),
    ],
]);
