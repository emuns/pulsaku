<?php
/**
 * Pengaturan Rate & Metode Pembayaran
 */
require_once __DIR__ . '/config.php';
requireLogin();

 $settings = getSettings();

// === Proses update ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['section'] ?? '';

    // Update site settings
    if ($section === 'site') {
        $settings['site_name'] = sanitize($_POST['site_name'] ?? 'PulsaKu');
        $settings['site_tagline'] = sanitize($_POST['site_tagline'] ?? '');
        $settings['admin_contact'] = sanitize($_POST['admin_contact'] ?? '');
        $settings['whatsapp'] = sanitize($_POST['whatsapp'] ?? '');
        $settings['maintenance'] = isset($_POST['maintenance']);
        $settings['auto_approve'] = isset($_POST['auto_approve']);
        $settings['min_transaction'] = intval($_POST['min_transaction'] ?? 10000);
        saveSettings($settings);
        writeLog('SETTINGS', 'Pengaturan situs diperbarui');
        setFlash('success', 'Pengaturan situs berhasil disimpan.');
        header('Location: settings.php');
        exit;
    }

    // Update provider rate
    if ($section === 'providers') {
        $providers = $_POST['providers'] ?? [];
        foreach ($providers as $key => $data) {
            if (isset($settings['providers'][$key])) {
                $settings['providers'][$key]['rate'] = floatval($data['rate'] ?? 0);
                $settings['providers'][$key]['min'] = intval($data['min'] ?? 10000);
                $settings['providers'][$key]['max'] = intval($data['max'] ?? 1000000);
                $settings['providers'][$key]['active'] = isset($data['active']);
                $settings['providers'][$key]['name'] = sanitize($data['name'] ?? $settings['providers'][$key]['name']);
                $settings['providers'][$key]['brands'] = sanitize($data['brands'] ?? $settings['providers'][$key]['brands']);
            }
        }
        saveSettings($settings);
        writeLog('SETTINGS', 'Rate provider diperbarui');
        setFlash('success', 'Rate provider berhasil diperbarui.');
        header('Location: settings.php#providers');
        exit;
    }

    // Update payment methods
    if ($section === 'payments') {
        $payments = $_POST['payments'] ?? [];
        foreach ($payments as $key => $data) {
            if (isset($settings['payment_methods'][$key])) {
                $settings['payment_methods'][$key]['active'] = isset($data['active']);
                $settings['payment_methods'][$key]['name'] = sanitize($data['name'] ?? $settings['payment_methods'][$key]['name']);
                $settings['payment_methods'][$key]['placeholder'] = sanitize($data['placeholder'] ?? $settings['payment_methods'][$key]['placeholder']);
            }
        }
        saveSettings($settings);
        writeLog('SETTINGS', 'Metode pembayaran diperbarui');
        setFlash('success', 'Metode pembayaran berhasil diperbarui.');
        header('Location: settings.php#payments');
        exit;
    }
}

ob_start();
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-8">
    <div>
        <h1 class="font-display text-2xl font-bold">Pengaturan</h1>
        <p class="text-sm text-[var(--muted)] mt-0.5">Kelola rate, metode bayar, dan konfigurasi situs</p>
    </div>
</div>

<!-- === Pengaturan Situs === -->
<div class="card p-6 mb-6">
    <h2 class="font-display font-bold text-lg mb-1">Pengaturan Situs</h2>
    <p class="text-xs text-[var(--muted)] mb-6">Informasi dasar dan konfigurasi umum</p>

    <form method="POST" action="">
        <input type="hidden" name="section" value="site">

        <div class="grid sm:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-xs font-semibold mb-1.5">Nama Situs</label>
                <input type="text" name="site_name" class="fi" value="<?= sanitize($settings['site_name']) ?>">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1.5">Email Admin</label>
                <input type="email" name="admin_contact" class="fi" value="<?= sanitize($settings['admin_contact']) ?>">
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-xs font-semibold mb-1.5">Tagline</label>
                <input type="text" name="site_tagline" class="fi" value="<?= sanitize($settings['site_tagline']) ?>">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1.5">Nomor WhatsApp</label>
                <input type="text" name="whatsapp" class="fi" value="<?= sanitize($settings['whatsapp']) ?>" placeholder="628xxxxxxxxxx">
            </div>
        </div>

        <div class="mb-6">
            <label class="block text-xs font-semibold mb-1.5">Minimal Transaksi (Rp)</label>
            <input type="number" name="min_transaction" class="fi" value="<?= $settings['min_transaction'] ?>" min="1000" step="1000" style="max-width:200px">
        </div>

        <div class="flex flex-wrap gap-6 mb-6">
            <label class="flex items-center gap-3 cursor-pointer">
                <label class="toggle">
                    <input type="checkbox" name="maintenance" <?= $settings['maintenance'] ? 'checked' : '' ?>>
                    <span class="toggle-slider"></span>
                </label>
                <div>
                    <div class="text-sm font-medium">Mode Maintenance</div>
                    <div class="text-[10px] text-[var(--muted)]">Website menampilkan halaman maintenance</div>
                </div>
            </label>

            <label class="flex items-center gap-3 cursor-pointer">
                <label class="toggle">
                    <input type="checkbox" name="auto_approve" <?= $settings['auto_approve'] ? 'checked' : '' ?>>
                    <span class="toggle-slider"></span>
                </label>
                <div>
                    <div class="text-sm font-medium">Auto Approve</div>
                    <div class="text-[10px] text-[var(--muted)]">Otomatis setujui transaksi baru</div>
                </div>
            </label>
        </div>

        <button type="submit" class="btn btn-primary !w-auto"><i class="fas fa-save"></i> Simpan Pengaturan</button>
    </form>
</div>

<!-- === Rate Provider === -->
<div class="card p-6 mb-6" id="providers">
    <h2 class="font-display font-bold text-lg mb-1">Rate Provider</h2>
    <p class="text-xs text-[var(--muted)] mb-6">Atur rate konversi, nominal min/max, dan aktif/nonaktif provider</p>

    <form method="POST" action="">
        <input type="hidden" name="section" value="providers">

        <div class="space-y-4">
            <?php foreach ($settings['providers'] as $key => $p): ?>
            <div class="p-4 rounded-xl border border-white/[0.04] bg-white/[0.01]">
                <div class="flex flex-wrap items-center gap-3 mb-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-sm" style="background:<?= $p['color'] ?>">
                        <i class="fas <?= $p['icon'] ?>"></i>
                    </div>
                    <div class="flex-1 min-w-[120px]">
                        <input type="text" name="providers[<?= $key ?>][name]" class="fi !py-1.5 !text-sm font-semibold" value="<?= sanitize($p['name']) ?>">
                    </div>
                    <div class="flex-1 min-w-[120px]">
                        <input type="text" name="providers[<?= $key ?>][brands]" class="fi !py-1.5 !text-sm" value="<?= sanitize($p['brands']) ?>" placeholder="Merek">
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <label class="toggle">
                            <input type="checkbox" name="providers[<?= $key ?>][active]" <?= $p['active'] ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <span class="text-xs <?= $p['active'] ? 'text-emerald-400' : 'text-[var(--muted)]' ?>"><?= $p['active'] ? 'Aktif' : 'Nonaktif' ?></span>
                    </label>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[10px] font-semibold text-[var(--muted)] mb-1">RATE (%)</label>
                        <input type="number" name="providers[<?= $key ?>][rate]" class="fi !py-1.5 !text-sm text-emerald-400 font-bold" value="<?= $p['rate'] ?>" min="0.5" max="0.95" step="0.01">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-[var(--muted)] mb-1">MIN (Rp)</label>
                        <input type="number" name="providers[<?= $key ?>][min]" class="fi !py-1.5 !text-sm" value="<?= $p['min'] ?>" min="1000" step="1000">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-[var(--muted)] mb-1">MAX (Rp)</label>
                        <input type="number" name="providers[<?= $key ?>][max]" class="fi !py-1.5 !text-sm" value="<?= $p['max'] ?>" min="10000" step="10000">
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-6">
            <button type="submit" class="btn btn-primary !w-auto"><i class="fas fa-save"></i> Simpan Rate</button>
        </div>
    </form>
</div>

<!-- === Metode Pembayaran === -->
<div class="card p-6" id="payments">
    <h2 class="font-display font-bold text-lg mb-1">Metode Pembayaran</h2>
    <p class="text-xs text-[var(--muted)] mb-6">Atur metode pembayaran yang tersedia untuk menerima dana</p>

    <form method="POST" action="">
        <input type="hidden" name="section" value="payments">

        <div class="grid sm:grid-cols-2 gap-3">
            <?php
            $ewallets = array_filter($settings['payment_methods'], fn($m) => ($m['type'] ?? '') === 'ewallet');
            $banks = array_filter($settings['payment_methods'], fn($m) => ($m['type'] ?? '') === 'bank');
            ?>

            <!-- E-Wallet -->
            <?php if (!empty($ewallets)): ?>
            <div class="sm:col-span-2 text-xs font-semibold text-[var(--muted)] uppercase tracking-wider mb-1">E-Wallet</div>
            <?php foreach ($ewallets as $key => $pm): ?>
            <div class="p-4 rounded-xl border border-white/[0.04] bg-white/[0.01] flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-emerald-500/10 flex items-center justify-center flex-shrink-0">
                    <i class="fas <?= $pm['icon'] ?> text-emerald-400 text-sm"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <input type="text" name="payments[<?= $key ?>][name]" class="fi !py-1 !text-sm font-semibold !mb-1" value="<?= sanitize($pm['name']) ?>">
                    <input type="text" name="payments[<?= $key ?>][placeholder]" class="fi !py-1 !text-xs" value="<?= sanitize($pm['placeholder']) ?>">
                </div>
                <label class="toggle flex-shrink-0">
                    <input type="checkbox" name="payments[<?= $key ?>][active]" <?= $pm['active'] ? 'checked' : '' ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

            <!-- Bank -->
            <?php if (!empty($banks)): ?>
            <div class="sm:col-span-2 text-xs font-semibold text-[var(--muted)] uppercase tracking-wider mb-1 mt-4">Transfer Bank</div>
            <?php foreach ($banks as $key => $pm): ?>
            <div class="p-4 rounded-xl border border-white/[0.04] bg-white/[0.01] flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-blue-500/10 flex items-center justify-center flex-shrink-0">
                    <i class="fas <?= $pm['icon'] ?> text-blue-400 text-sm"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <input type="text" name="payments[<?= $key ?>][name]" class="fi !py-1 !text-sm font-semibold !mb-1" value="<?= sanitize($pm['name']) ?>">
                    <input type="text" name="payments[<?= $key ?>][placeholder]" class="fi !py-1 !text-xs" value="<?= sanitize($pm['placeholder']) ?>>
                </div>
                <label class="toggle flex-shrink-0">
                    <input type="checkbox" name="payments[<?= $key ?>][active]" <?= $pm['active'] ? 'checked' : '' ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="mt-6">
            <button type="submit" class="btn btn-primary !w-auto"><i class="fas fa-save"></i> Simpan Metode</button>
        </div>
    </form>
</div>

<?php
 $content = ob_get_clean();
renderAdminPage('Pengaturan', $content, 'settings');