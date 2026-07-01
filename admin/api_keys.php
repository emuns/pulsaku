<?php
/**
 * Kelola API Keys di Panel Admin
 */
require_once __DIR__ . '/config.php';
requireLogin();

 $settings = getSettings();
 $apiKeys = $settings['api_keys'] ?? [];

// Proses create key
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_key') {
        $name = sanitize($_POST['key_name'] ?? '');
        if (empty($name)) {
            setFlash('error', 'Nama key wajib diisi.');
            header('Location: api_keys.php');
            exit;
        }

        $newKey = [
            'id'            => 'key_' . strtolower(substr(md5(uniqid()), 0, 8)),
            'name'          => $name,
            'key'           => 'pk_live_' . bin2hex(random_bytes(24)),
            'active'        => true,
            'created_at'    => date('Y-m-d H:i:s'),
            'last_used'     => null,
            'total_requests'=> 0,
        ];

        $settings['api_keys'][] = $newKey;
        saveSettings($settings);
        writeLog('API_KEY_CREATE', 'API key dibuat: ' . $name);
        setFlash('success', 'API key berhasil dibuat. Salin key sekarang karena tidak bisa dilihat lagi.');
        header('Location: api_keys.php');
        exit;
    }

    if ($action === 'toggle_key') {
        $keyId = sanitize($_POST['key_id'] ?? '');
        foreach ($settings['api_keys'] as &$k) {
            if ($k['id'] === $keyId) {
                $k['active'] = !$k['active'];
                break;
            }
        }
        unset($k);
        saveSettings($settings);
        writeLog('API_KEY_TOGGLE', 'API key ' . $keyId . ' di-toggle');
        setFlash('success', 'Status API key diperbarui.');
        header('Location: api_keys.php');
        exit;
    }

    if ($action === 'delete_key') {
        $keyId = sanitize($_POST['key_id'] ?? '');
        $settings['api_keys'] = array_values(array_filter($settings['api_keys'], fn($k) => $k['id'] !== $keyId));
        saveSettings($settings);
        writeLog('API_KEY_DELETE', 'API key ' . $keyId . ' dihapus');
        setFlash('success', 'API key dihapus.');
        header('Location: api_keys.php');
        exit;
    }

    // Update webhook secret
    if ($action === 'update_webhook') {
        $settings['webhook_secret'] = sanitize($_POST['webhook_secret'] ?? '');
        saveSettings($settings);
        writeLog('WEBHOOK_SECRET', 'Webhook secret diperbarui');
        setFlash('success', 'Webhook secret berhasil disimpan.');
        header('Location: api_keys.php#webhook');
        exit;
    }
}

ob_start();
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-8">
    <div>
        <h1 class="font-display text-2xl font-bold">API Keys</h1>
        <p class="text-sm text-[var(--muted)] mt-0.5">Kelola akses API untuk integrasi eksternal</p>
    </div>
</div>

<!-- Dokumentasi API Cepat -->
<div class="card p-5 mb-6">
    <h3 class="font-semibold text-sm mb-4 flex items-center gap-2">
        <i class="fas fa-book text-emerald-400 text-xs"></i> Dokumentasi Endpoint
    </h3>
    <div class="overflow-x-auto">
        <table class="tx-table">
            <thead>
                <tr><th>Method</th><th>Endpoint</th><th>Deskripsi</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><span class="badge badge-success">GET</span></td>
                    <td class="font-mono text-xs text-emerald-400">/api/rates</td>
                    <td class="text-sm">Daftar provider & rate aktif</td>
                </tr>
                <tr>
                    <td><span class="badge badge-success">GET</span></td>
                    <td class="font-mono text-xs text-emerald-400">/api/transactions</td>
                    <td class="text-sm">List transaksi (filter: status, provider, phone, limit, offset)</td>
                </tr>
                <tr>
                    <td><span class="badge badge-pending">POST</span></td>
                    <td class="font-mono text-xs text-emerald-400">/api/transactions</td>
                    <td class="text-sm">Buat transaksi baru</td>
                </tr>
                <tr>
                    <td><span class="badge badge-success">GET</span></td>
                    <td class="font-mono text-xs text-emerald-400">/api/check/{tx_id}</td>
                    <td class="text-sm">Cek status transaksi</td>
                </tr>
                <tr>
                    <td><span class="badge badge-pending">POST</span></td>
                    <td class="font-mono text-xs text-emerald-400">/api/webhook</td>
                    <td class="text-sm">Callback update status (pakai webhook secret, bukan API key)</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Buat Key Baru -->
<div class="card p-5 mb-6">
    <h3 class="font-semibold text-sm mb-4 flex items-center gap-2">
        <i class="fas fa-plus text-emerald-400 text-xs"></i> Buat API Key Baru
    </h3>
    <form method="POST" action="">
        <input type="hidden" name="action" value="create_key">
        <div class="flex gap-3">
            <input type="text" name="key_name" class="fi !py-2 !text-sm" placeholder="Nama key (contoh: Bot Telegram)" required style="max-width:300px">
            <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-key"></i> Generate Key</button>
        </div>
    </form>
</div>

<!-- Daftar Keys -->
<div class="card overflow-hidden mb-6">
    <div class="p-5 border-b border-white/[0.04]">
        <h3 class="font-semibold text-sm">API Keys Terdaftar (<?= count($apiKeys) ?>)</h3>
    </div>
    <?php if (empty($apiKeys)): ?>
    <div class="p-12 text-center text-sm text-[var(--muted)]">Belum ada API key</div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="tx-table">
            <thead>
                <tr><th>Nama</th><th>Key</th><th>Status</th><th>Terakhir Dipakai</th><th>Total Request</th><th>Dibuat</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                <?php foreach ($apiKeys as $k): ?>
                <tr>
                    <td class="font-semibold text-sm"><?= sanitize($k['name']) ?></td>
                    <td>
                        <code class="text-xs bg-white/5 px-2 py-1 rounded font-mono"><?= substr($k['key'], 0, 12) ?>...<?= substr($k['key'], -4) ?></code>
                    </td>
                    <td>
                        <span class="badge <?= $k['active'] ? 'badge-success' : 'badge-failed' ?>">
                            <i class="fas fa-circle text-[4px]"></i> <?= $k['active'] ? 'Aktif' : 'Nonaktif' ?>
                        </span>
                    </td>
                    <td class="text-xs text-[var(--muted)]"><?= $k['last_used'] ?? '-' ?></td>
                    <td class="text-xs font-semibold"><?= number_format($k['total_requests'] ?? 0) ?></td>
                    <td class="text-xs text-[var(--muted)]"><?= date('d/m/Y', strtotime($k['created_at'])) ?></td>
                    <td>
                        <div class="flex gap-1">
                            <form method="POST" action="" style="display:inline">
                                <input type="hidden" name="action" value="toggle_key">
                                <input type="hidden" name="key_id" value="<?= $k['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-ghost !px-2 !py-1" title="<?= $k['active'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                    <i class="fas fa-<?= $k['active'] ? 'pause' : 'play' ?> text-[10px]"></i>
                                </button>
                            </form>
                            <form method="POST" action="" onsubmit="return confirm('Hapus API key ini?')" style="display:inline">
                                <input type="hidden" name="action" value="delete_key">
                                <input type="hidden" name="key_id" value="<?= $k['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger !px-2 !py-1" title="Hapus">
                                    <i class="fas fa-trash text-[10px]"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Webhook Secret -->
<div class="card p-5" id="webhook">
    <h3 class="font-semibold text-sm mb-4 flex items-center gap-2">
        <i class="fas fa-link text-amber-400 text-xs"></i> Webhook Secret
    </h3>
    <p class="text-xs text-[var(--muted)] mb-4">Secret ini digunakan untuk autentikasi webhook callback (bukan API key). Kosongkan jika tidak digunakan.</p>
    <form method="POST" action="">
        <input type="hidden" name="action" value="update_webhook">
        <div class="flex gap-3">
            <input type="text" name="webhook_secret" class="fi !py-2 !text-sm font-mono" value="<?= sanitize($settings['webhook_secret'] ?? '') ?>" placeholder="whsec_xxxxxxxxxxxxx" style="max-width:400px">
            <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-save"></i> Simpan</button>
        </div>
    </form>
</div>

<!-- Contoh Usage -->
<div class="card p-5 mt-6">
    <h3 class="font-semibold text-sm mb-4 flex items-center gap-2">
        <i class="fas fa-code text-blue-400 text-xs"></i> Contoh Penggunaan (cURL)
    </h3>
    <div class="space-y-3">
        <div>
            <div class="text-[10px] font-semibold text-[var(--muted)] uppercase mb-1">Ambil Rate</div>
            <pre class="text-xs bg-black/30 p-3 rounded-lg overflow-x-auto text-emerald-300"><code>curl -H "Authorization: Bearer YOUR_API_KEY" \
  <?= getBaseUrl() ?>/api/rates</code></pre>
        </div>
        <div>
            <div class="text-[10px] font-semibold text-[var(--muted)] uppercase mb-1">Buat Transaksi</div>
            <pre class="text-xs bg-black/30 p-3 rounded-lg overflow-x-auto text-emerald-300"><code>curl -X POST \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "provider": "telkomsel",
    "phone": "081234567890",
    "amount": 50000,
    "payment": "dana",
    "pay_account": "081234567890"
  }' \
  <?= getBaseUrl() ?>/api/transactions</code></pre>
        </div>
        <div>
            <div class="text-[10px] font-semibold text-[var(--muted)] uppercase mb-1">Cek Status</div>
            <pre class="text-xs bg-black/30 p-3 rounded-lg overflow-x-auto text-emerald-300"><code>curl -H "Authorization: Bearer YOUR_API_KEY" \
  <?= getBaseUrl() ?>/api/check/TXN20260101ABC123</code></pre>
        </div>
        <div>
            <div class="text-[10px] font-semibold text-[var(--muted)] uppercase mb-1">Webhook Callback</div>
            <pre class="text-xs bg-black/30 p-3 rounded-lg overflow-x-auto text-emerald-300"><code>curl -X POST \
  -H "Content-Type: application/json" \
  -d '{
    "tx_id": "TXN20260101ABC123",
    "status": "success",
    "note": "Pulsa diterima",
    "secret": "YOUR_WEBHOOK_SECRET"
  }' \
  <?= getBaseUrl() ?>/api/webhook</code></pre>
        </div>
    </div>
</div>

<?php
 $content = ob_get_clean();
renderAdminPage('API Keys', $content, 'api-keys');
