<?php
/**
 * Dashboard Admin
 */
require_once __DIR__ . '/config.php';
requireLogin();

 $settings = getSettings();
 $allTx = getTransactions();

// Statistik
 $totalTx = count($allTx);
 $totalNominal = array_sum(array_column($allTx, 'amount'));
 $totalReceived = array_sum(array_column($allTx, 'received'));
 $pendingCount = count(array_filter($allTx, fn($t) => $t['status'] === 'pending'));
 $successCount = count(array_filter($allTx, fn($t) => $t['status'] === 'success'));
 $failedCount = count(array_filter($allTx, fn($t) => $t['status'] === 'failed'));
 $uniqueUsers = count(array_unique(array_column($allTx, 'phone')));

// Transaksi 7 hari terakhir per hari
 $last7 = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $count = count(array_filter($allTx, fn($t) => strpos($t['created_at'], $date) === 0));
    $nominal = array_sum(array_map(fn($t) => $t['amount'], array_filter($allTx, fn($t) => strpos($t['created_at'], $date) === 0)));
    $last7[] = ['date' => $date, 'label' => date('d/m', strtotime($date)), 'count' => $count, 'nominal' => $nominal];
}
 $maxCount = max(array_column($last7, 'count')) ?: 1;

// Transaksi per provider
 $perProvider = [];
foreach ($settings['providers'] as $key => $p) {
    $count = count(array_filter($allTx, fn($t) => $t['provider'] === $key));
    $nominal = array_sum(array_map(fn($t) => $t['amount'], array_filter($allTx, fn($t) => $t['provider'] === $key)));
    if ($count > 0) $perProvider[] = ['key' => $key, 'name' => $p['name'], 'color' => $p['color'], 'count' => $count, 'nominal' => $nominal];
}
usort($perProvider, fn($a, $b) => $b['nominal'] - $a['nominal']);

// Transaksi per metode bayar
 $perPayment = [];
foreach ($settings['payment_methods'] as $key => $pm) {
    $count = count(array_filter($allTx, fn($t) => $t['payment'] === $key));
    if ($count > 0) $perPayment[] = ['key' => $key, 'name' => $pm['name'], 'count' => $count];
}
usort($perPayment, fn($a, $b) => $b['count'] - $a['count']);

// 5 transaksi terakhir
 $recentTx = array_slice($allTx, 0, 5);

// Profit estimasi (selisih nominal - diterima)
 $profit = $totalNominal - $totalReceived;

ob_start();
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-8">
    <div>
        <h1 class="font-display text-2xl font-bold">Dashboard</h1>
        <p class="text-sm text-[var(--muted)] mt-0.5">Ringkasan aktivitas konversi pulsa</p>
    </div>
    <div class="text-xs text-[var(--muted)]">
        <i class="fas fa-clock mr-1"></i> Terakhir diperbarui: <?= date('d/m/Y H:i') ?>
    </div>
</div>

<!-- Stat Cards -->
<div class="stat-grid grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8" style="grid-template-columns: repeat(4, 1fr)">
    <div class="card stat-card emerald p-5">
        <div class="flex items-center gap-2 mb-3">
            <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center">
                <i class="fas fa-receipt text-emerald-400 text-sm"></i>
            </div>
            <span class="text-xs text-[var(--muted)] font-medium">Total Transaksi</span>
        </div>
        <div class="font-display text-2xl font-bold"><?= number_format($totalTx) ?></div>
        <div class="text-[10px] text-[var(--muted)] mt-1"><?= $successCount ?> berhasil · <?= $failedCount ?> gagal</div>
    </div>

    <div class="card stat-card amber p-5">
        <div class="flex items-center gap-2 mb-3">
            <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center">
                <i class="fas fa-coins text-amber-400 text-sm"></i>
            </div>
            <span class="text-xs text-[var(--muted)] font-medium">Total Nominal</span>
        </div>
        <div class="font-display text-2xl font-bold"><?= $totalNominal >= 1000000 ? 'Rp' . number_format($totalNominal / 1000000, 1) . 'jt' : rupiah($totalNominal) ?></div>
        <div class="text-[10px] text-[var(--muted)] mt-1">Pulsa yang dikonversi</div>
    </div>

    <div class="card stat-card blue p-5">
        <div class="flex items-center gap-2 mb-3">
            <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center">
                <i class="fas fa-hourglass-half text-blue-400 text-sm"></i>
            </div>
            <span class="text-xs text-[var(--muted)] font-medium">Menunggu</span>
        </div>
        <div class="font-display text-2xl font-bold text-amber-400"><?= $pendingCount ?></div>
        <div class="text-[10px] text-[var(--muted)] mt-1">Belum diproses</div>
    </div>

    <div class="card stat-card red p-5">
        <div class="flex items-center gap-2 mb-3">
            <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center">
                <i class="fas fa-chart-line text-emerald-400 text-sm"></i>
            </div>
            <span class="text-xs text-[var(--muted)] font-medium">Estimasi Profit</span>
        </div>
        <div class="font-display text-2xl font-bold"><?= $profit >= 1000000 ? 'Rp' . number_format($profit / 1000000, 1) . 'jt' : rupiah($profit) ?></div>
        <div class="text-[10px] text-[var(--muted)] mt-1">Selisih nominal - diterima</div>
    </div>
</div>

<!-- Charts Row -->
<div class="grid lg:grid-cols-2 gap-4 mb-8">
    <!-- Chart: 7 Hari Terakhir -->
    <div class="card p-5">
        <h3 class="font-semibold text-sm mb-5">Transaksi 7 Hari Terakhir</h3>
        <?php if (empty($last7) || array_sum(array_column($last7, 'count')) === 0): ?>
        <div class="text-center py-10 text-sm text-[var(--muted)]">Belum ada data</div>
        <?php else: ?>
        <div class="flex items-end justify-between gap-2" style="height:160px">
            <?php foreach ($last7 as $d): ?>
            <div class="flex-1 flex flex-col items-center gap-2">
                <div class="text-[10px] font-semibold text-emerald-400"><?= $d['count'] ?></div>
                <div class="w-full rounded-t-md bg-emerald-500/20 relative" style="height:<?= max(4, ($d['count'] / $maxCount) * 120) ?>px">
                    <div class="absolute inset-0 rounded-t-md bg-gradient-to-t from-emerald-600 to-emerald-400"></div>
                </div>
                <div class="text-[10px] text-[var(--muted)]"><?= $d['label'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Chart: Per Provider -->
    <div class="card p-5">
        <h3 class="font-semibold text-sm mb-5">Transaksi per Provider</h3>
        <?php if (empty($perProvider)): ?>
        <div class="text-center py-10 text-sm text-[var(--muted)]">Belum ada data</div>
        <?php else: ?>
        <div class="space-y-3">
            <?php
            $maxProvNominal = max(array_column($perProvider, 'nominal')) ?: 1;
            foreach ($perProvider as $pp):
            ?>
            <div>
                <div class="flex items-center justify-between mb-1">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-sm" style="background:<?= $pp['color'] ?>"></div>
                        <span class="text-xs font-medium"><?= $pp['name'] ?></span>
                    </div>
                    <div class="text-xs text-[var(--muted)]"><?= $pp['count'] ?> tx · <?= rupiah($pp['nominal']) ?></div>
                </div>
                <div class="h-2 rounded-full bg-white/5 overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-1000" style="width:<?= ($pp['nominal'] / $maxProvNominal) * 100 ?>%;background:<?= $pp['color'] ?>"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Bottom Row -->
<div class="grid lg:grid-cols-3 gap-4 mb-8">
    <!-- Transaksi Terakhir -->
    <div class="card lg:col-span-2 overflow-hidden">
        <div class="p-5 border-b border-white/[0.04] flex items-center justify-between">
            <h3 class="font-semibold text-sm">Transaksi Terakhir</h3>
            <a href="transactions.php" class="text-xs text-emerald-400 hover:text-emerald-300 transition-colors">Lihat semua <i class="fas fa-arrow-right ml-1"></i></a>
        </div>
        <?php if (empty($recentTx)): ?>
        <div class="p-10 text-center text-sm text-[var(--muted)]">Belum ada transaksi</div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="tx-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Provider</th>
                        <th>Nominal</th>
                        <th>Diterima</th>
                        <th>Status</th>
                        <th>Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentTx as $tx): ?>
                    <tr>
                        <td><span class="font-mono text-xs text-emerald-400/80"><?= $tx['id'] ?></span></td>
                        <td class="text-sm"><?= $tx['provider_name'] ?></td>
                        <td class="font-semibold text-sm"><?= rupiah($tx['amount']) ?></td>
                        <td class="text-sm text-emerald-400"><?= rupiah($tx['received']) ?></td>
                        <td><span class="badge badge-<?= $tx['status'] ?>"><i class="fas fa-circle text-[4px]"></i><?= ucfirst($tx['status']) ?></span></td>
                        <td class="text-xs text-[var(--muted)]"><?= date('d/m H:i', strtotime($tx['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Metode Bayar Populer -->
    <div class="card p-5">
        <h3 class="font-semibold text-sm mb-5">Metode Pembayaran</h3>
        <?php if (empty($perPayment)): ?>
        <div class="text-center py-10 text-sm text-[var(--muted)]">Belum ada data</div>
        <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($perPayment as $pm): ?>
            <div class="flex items-center justify-between py-2 border-b border-white/[0.03] last:border-0">
                <span class="text-sm"><?= $pm['name'] ?></span>
                <span class="text-xs font-semibold text-emerald-400"><?= $pm['count'] ?> tx</span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="mt-6 pt-5 border-t border-white/[0.04]">
            <h4 class="text-xs font-semibold text-[var(--muted)] mb-3 uppercase tracking-wider">Info Cepat</h4>
            <div class="space-y-2 text-xs">
                <div class="flex justify-between">
                    <span class="text-[var(--muted)]">Pengguna unik</span>
                    <span class="font-semibold"><?= $uniqueUsers ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[var(--muted)]">Rata-rata nominal</span>
                    <span class="font-semibold"><?= $totalTx > 0 ? rupiah((int)($totalNominal / $totalTx)) : '-' ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[var(--muted)]">Rate rata-rata</span>
                    <span class="font-semibold text-emerald-400"><?= $totalNominal > 0 ? number_format(($totalReceived / $totalNominal) * 100, 1) . '%' : '-' ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[var(--muted)]">Status mode</span>
                    <span class="font-semibold <?= $settings['maintenance'] ? 'text-red-400' : 'text-emerald-400' ?>">
                        <i class="fas fa-circle text-[5px] mr-1"></i><?= $settings['maintenance'] ? 'Maintenance' : 'Aktif' ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
 $content = ob_get_clean();
renderAdminPage('Dashboard', $content, 'dashboard');