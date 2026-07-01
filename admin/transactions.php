<?php
/**
 * Kelola Transaksi + Activity Log
 */
require_once __DIR__ . '/config.php';
requireLogin();

 $settings = getSettings();
 $allTx = getTransactions();
 $tab = $_GET['tab'] ?? 'list';

// === Proses aksi ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Update status transaksi
    if ($action === 'update_status') {
        $txId = sanitize($_POST['tx_id'] ?? '');
        $newStatus = sanitize($_POST['new_status'] ?? '');
        $note = sanitize($_POST['note'] ?? '');

        if (!in_array($newStatus, ['pending', 'success', 'failed'])) {
            setFlash('error', 'Status tidak valid.');
            header('Location: transactions.php');
            exit;
        }

        $found = false;
        foreach ($allTx as &$tx) {
            if ($tx['id'] === $txId) {
                $oldStatus = $tx['status'];
                $tx['status'] = $newStatus;
                $tx['note'] = $note;
                $tx['processed_at'] = date('Y-m-d H:i:s');
                $tx['processed_by'] = $_SESSION['admin_username'];
                $found = true;
                break;
            }
        }
        unset($tx);

        if ($found) {
            saveTransactions($allTx);
            writeLog('UPDATE_TX', "Status {$txId}: {$oldStatus} → {$newStatus}" . ($note ? " ({$note})" : ''));
            setFlash('success', "Status transaksi {$txId} diubah menjadi " . ucfirst($newStatus) . ".");
        } else {
            setFlash('error', 'Transaksi tidak ditemukan.');
        }
        header('Location: transactions.php');
        exit;
    }

    // Hapus transaksi
    if ($action === 'delete_tx') {
        $txId = sanitize($_POST['tx_id'] ?? '');
        $allTx = array_values(array_filter($allTx, fn($t) => $t['id'] !== $txId));
        saveTransactions($allTx);
        writeLog('DELETE_TX', "Transaksi {$txId} dihapus");
        setFlash('success', "Transaksi {$txId} telah dihapus.");
        header('Location: transactions.php');
        exit;
    }

    // Clear logs
    if ($action === 'clear_logs') {
        if (file_exists(LOG_FILE)) unlink(LOG_FILE);
        writeLog('CLEAR_LOG', 'Activity log dibersihkan');
        setFlash('success', 'Activity log telah dibersihkan.');
        header('Location: transactions.php?tab=logs');
        exit;
    }
}

// Filter & pencarian
 $filterStatus = $_GET['status'] ?? 'all';
 $filterProvider = $_GET['provider'] ?? 'all';
 $searchQuery = trim($_GET['q'] ?? '');
 $page = max(1, intval($_GET['page'] ?? 1));
 $perPage = 20;

 $filtered = $allTx;

if ($filterStatus !== 'all') {
    $filtered = array_filter($filtered, fn($t) => $t['status'] === $filterStatus);
}
if ($filterProvider !== 'all') {
    $filtered = array_filter($filtered, fn($t) => $t['provider'] === $filterProvider);
}
if (!empty($searchQuery)) {
    $filtered = array_filter($filtered, fn($t) =>
        stripos($t['id'], $searchQuery) !== false ||
        stripos($t['phone'], $searchQuery) !== false ||
        stripos($t['payment_account'], $searchQuery) !== false
    );
}
 $filtered = array_values($filtered);
 $totalFiltered = count($filtered);
 $totalPages = max(1, ceil($totalFiltered / $perPage));
 $page = min($page, $totalPages);
 $paged = array_slice($filtered, ($page - 1) * $perPage, $perPage);

// Logs
 $logs = getLogs(200);

ob_start();
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
    <div>
        <h1 class="font-display text-2xl font-bold">Transaksi</h1>
        <p class="text-sm text-[var(--muted)] mt-0.5"><?= number_format($totalFiltered) ?> transaksi ditemukan</p>
    </div>
</div>

<!-- Tab toggle -->
<div class="flex gap-2 mb-6">
    <button onclick="showTab('list')" id="btnTabList" class="btn btn-sm <?= $tab === 'list' ? 'btn-primary' : 'btn-ghost' ?>">
        <i class="fas fa-list"></i> Daftar Transaksi
    </button>
    <button onclick="showTab('logs')" id="btnTabLogs" class="btn btn-sm <?= $tab === 'logs' ? 'btn-primary' : 'btn-ghost' ?>">
        <i class="fas fa-scroll"></i> Activity Log
    </button>
</div>

<!-- === TAB: Daftar Transaksi === -->
<div id="tabList" class="<?= $tab !== 'list' ? 'hidden' : '' ?>">

    <!-- Filter Bar -->
    <div class="card p-4 mb-4">
        <form method="GET" action="" class="flex flex-wrap gap-3 items-end">
            <input type="hidden" name="tab" value="list">
            <div class="flex-1 min-w-[180px]">
                <label class="block text-[10px] font-semibold text-[var(--muted)] mb-1 uppercase">Cari</label>
                <input type="text" name="q" class="fi !py-2 !text-sm" placeholder="ID, No HP, No rekening..." value="<?= sanitize($searchQuery) ?>">
            </div>
            <div class="w-36">
                <label class="block text-[10px] font-semibold text-[var(--muted)] mb-1 uppercase">Status</label>
                <select name="status" class="fi !py-2 !text-sm">
                    <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>Semua</option>
                    <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="success" <?= $filterStatus === 'success' ? 'selected' : '' ?>>Success</option>
                    <option value="failed" <?= $filterStatus === 'failed' ? 'selected' : '' ?>>Failed</option>
                </select>
            </div>
            <div class="w-40">
                <label class="block text-[10px] font-semibold text-[var(--muted)] mb-1 uppercase">Provider</label>
                <select name="provider" class="fi !py-2 !text-sm">
                    <option value="all" <?= $filterProvider === 'all' ? 'selected' : '' ?>>Semua</option>
                    <?php foreach ($settings['providers'] as $k => $p): ?>
                    <option value="<?= $k ?>" <?= $filterProvider === $k ? 'selected' : '' ?>><?= $p['name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-sm btn-primary !py-2">
                <i class="fas fa-search"></i> Filter
            </button>
            <a href="transactions.php?tab=list" class="btn btn-sm btn-ghost !py-2">
                <i class="fas fa-rotate-left"></i> Reset
            </a>
        </form>
    </div>

    <!-- Tabel -->
    <div class="card overflow-hidden">
        <?php if (empty($paged)): ?>
        <div class="p-12 text-center">
            <i class="fas fa-inbox text-3xl text-white/10 mb-3"></i>
            <p class="text-sm text-[var(--muted)]">Tidak ada transaksi</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="tx-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Provider</th>
                        <th>No. HP</th>
                        <th>Nominal</th>
                        <th class="hide-mobile">Diterima</th>
                        <th>Metode</th>
                        <th class="hide-mobile">Tujuan</th>
                        <th>Status</th>
                        <th>Waktu</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paged as $tx): ?>
                    <tr id="row-<?= $tx['id'] ?>">
                        <td><span class="font-mono text-xs text-emerald-400/80 cursor-pointer hover:text-emerald-300" onclick="copyText('<?= $tx['id'] ?>')" title="Klik untuk salin"><?= $tx['id'] ?></span></td>
                        <td class="text-sm"><?= $tx['provider_name'] ?></td>
                        <td class="text-sm font-mono"><?= $tx['phone'] ?></td>
                        <td class="font-semibold text-sm"><?= rupiah($tx['amount']) ?></td>
                        <td class="hide-mobile text-sm text-emerald-400 font-semibold"><?= rupiah($tx['received']) ?></td>
                        <td class="text-xs"><?= $tx['payment_name'] ?></td>
                        <td class="hide-mobile text-xs font-mono text-[var(--muted)]"><?= $tx['payment_account'] ?></td>
                        <td><span class="badge badge-<?= $tx['status'] ?>"><i class="fas fa-circle text-[4px]"></i><?= ucfirst($tx['status']) ?></span></td>
                        <td class="text-xs text-[var(--muted)] whitespace-nowrap"><?= date('d/m H:i', strtotime($tx['created_at'])) ?></td>
                        <td>
                            <div class="flex gap-1">
                                <button onclick="openModal('<?= $tx['id'] ?>', '<?= $tx['status'] ?>')" class="btn btn-sm btn-ghost !px-2 !py-1" title="Ubah status">
                                    <i class="fas fa-pen text-[10px]"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="p-4 border-t border-white/[0.04] flex items-center justify-between">
            <span class="text-xs text-[var(--muted)]">Halaman <?= $page ?> dari <?= $totalPages ?></span>
            <div class="flex gap-1">
                <?php if ($page > 1): ?>
                <a href="?tab=list&page=<?= $page - 1 ?>&status=<?= $filterStatus ?>&provider=<?= $filterProvider ?>&q=<?= urlencode($searchQuery) ?>" class="btn btn-sm btn-ghost !px-3 !py-1"><i class="fas fa-chevron-left text-[10px]"></i></a>
                <?php endif; ?>
                <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                <a href="?tab=list&page=<?= $p ?>&status=<?= $filterStatus ?>&provider=<?= $filterProvider ?>&q=<?= urlencode($searchQuery) ?>"
                   class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-ghost' ?> !px-3 !py-1"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                <a href="?tab=list&page=<?= $page + 1 ?>&status=<?= $filterStatus ?>&provider=<?= $filterProvider ?>&q=<?= urlencode($searchQuery) ?>" class="btn btn-sm btn-ghost !px-3 !py-1"><i class="fas fa-chevron-right text-[10px]"></i></a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- === TAB: Activity Log === -->
<div id="tabLogs" class="<?= $tab !== 'logs' ? 'hidden' : '' ?>">
    <div class="card overflow-hidden">
        <div class="p-4 border-b border-white/[0.04] flex items-center justify-between">
            <span class="text-sm font-semibold">Activity Log (<?= count($logs) ?> entri)</span>
            <form method="POST" action="" onsubmit="return confirm('Hapus semua log?')">
                <input type="hidden" name="action" value="clear_logs">
                <button type="submit" class="btn btn-sm btn-danger !py-1">
                    <i class="fas fa-trash text-[10px]"></i> Hapus Semua
                </button>
            </form>
        </div>
        <?php if (empty($logs)): ?>
        <div class="p-12 text-center text-sm text-[var(--muted)]">Belum ada log</div>
        <?php else: ?>
        <div class="p-4 max-h-[600px] overflow-y-auto">
            <?php foreach ($logs as $line): ?>
            <?php
            $parts = explode(' | ', $line, 4);
            $time = $parts[0] ?? '';
            $user = $parts[1] ?? '';
            $action = $parts[2] ?? '';
            $detail = $parts[3] ?? '';
            ?>
            <div class="log-line py-1 border-b border-white/[0.02] last:border-0">
                <span class="log-time"><?= $time ?></span>
                <span class="log-user">[<?= $user ?>]</span>
                <span class="log-action"><?= $action ?></span>
                <?php if ($detail): ?><span class="log-detail"> — <?= $detail ?></span><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- === MODAL: Update Status === -->
<div id="statusModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(0,0,0,0.7);backdrop-filter:blur(4px)">
    <div class="card p-6 w-full max-w-md" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-display font-bold text-lg">Update Status</h3>
            <button onclick="closeModal()" class="text-[var(--muted)] hover:text-[var(--fg)]"><i class="fas fa-xmark"></i></button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="tx_id" id="modalTxId">

            <div class="mb-4">
                <label class="block text-xs font-semibold mb-1.5">Transaksi</label>
                <div id="modalTxInfo" class="text-sm font-mono text-emerald-400"></div>
            </div>

            <div class="mb-4">
                <label class="block text-xs font-semibold mb-1.5">Status Baru</label>
                <select name="new_status" id="modalStatus" class="fi" required>
                    <option value="pending">Pending</option>
                    <option value="success">Success</option>
                    <option value="failed">Failed</option>
                </select>
            </div>

            <div class="mb-6">
                <label class="block text-xs font-semibold mb-1.5">Catatan (opsional)</label>
                <textarea name="note" class="fi" rows="2" placeholder="Alasan perubahan status..."></textarea>
            </div>

            <!-- Konfirmasi hapus -->
            <div id="deleteSection" class="mb-6 hidden">
                <button type="button" onclick="confirmDelete()" class="btn btn-sm btn-danger w-full">
                    <i class="fas fa-trash"></i> Hapus Transaksi Ini
                </button>
                <form id="deleteForm" method="POST" action="" class="hidden">
                    <input type="hidden" name="action" value="delete_tx">
                    <input type="hidden" name="tx_id" id="deleteTxId">
                </form>
            </div>

            <div class="flex gap-3">
                <button type="button" onclick="closeModal()" class="btn btn-ghost flex-1">Batal</button>
                <button type="submit" class="btn btn-primary flex-1"><i class="fas fa-check"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
function showTab(tab) {
    document.getElementById('tabList').classList.toggle('hidden', tab !== 'list');
    document.getElementById('tabLogs').classList.toggle('hidden', tab !== 'logs');
    document.getElementById('btnTabList').className = 'btn btn-sm ' + (tab === 'list' ? 'btn-primary' : 'btn-ghost');
    document.getElementById('btnTabLogs').className = 'btn btn-sm ' + (tab === 'logs' ? 'btn-primary' : 'btn-ghost');
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    history.replaceState(null, '', url);
}

function openModal(txId, currentStatus) {
    document.getElementById('modalTxId').value = txId;
    document.getElementById('modalTxInfo').textContent = txId;
    document.getElementById('modalStatus').value = currentStatus;
    document.getElementById('deleteTxId').value = txId;
    document.getElementById('deleteSection').classList.remove('hidden');
    const modal = document.getElementById('statusModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}
function closeModal() {
    const modal = document.getElementById('statusModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}
document.getElementById('statusModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

function confirmDelete() {
    if (confirm('Yakin ingin menghapus transaksi ini? Tindakan tidak bisa dibatalkan.')) {
        document.getElementById('deleteForm').submit();
    }
}

function copyText(text) {
    navigator.clipboard.writeText(text).then(() => {
        const t = document.createElement('div');
        t.className = 'fixed top-4 right-4 z-[100] px-4 py-2 rounded-lg bg-emerald-900/90 border border-emerald-500/30 text-emerald-300 text-xs font-medium';
        t.textContent = 'Disalin: ' + text;
        document.body.appendChild(t);
        setTimeout(() => t.remove(), 2000);
    });
}
</script>

<?php
 $content = ob_get_clean();
renderAdminPage('Transaksi', $content, 'transactions');