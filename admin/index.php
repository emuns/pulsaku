<?php
/**
 * Login & Setup Pertama Kali
 */
require_once __DIR__ . '/config.php';

// Sudah login → redirect dashboard
if (isAdminLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

 $admins = getAdmins();
 $isSetup = empty($admins);
 $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($isSetup) {
        // === SETUP PERTAMA KALI ===
        $user = sanitize($_POST['username'] ?? '');
        $pass = $_POST['password'] ?? '';
        $pass2 = $_POST['password2'] ?? '';

        if (strlen($user) < 3) $error = 'Username minimal 3 karakter.';
        elseif (strlen($pass) < 6) $error = 'Password minimal 6 karakter.';
        elseif ($pass !== $pass2) $error = 'Password tidak cocok.';
        else {
            $newAdmin = [
                'username' => $user,
                'password' => password_hash($pass, PASSWORD_BCRYPT),
                'role' => 'superadmin',
                'created_at' => date('Y-m-d H:i:s'),
                'last_login' => null,
            ];
            saveAdmins([$newAdmin]);
            writeLog('SETUP', 'Akun admin pertama dibuat: ' . $user);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $user;
            $_SESSION['admin_role'] = 'superadmin';
            header('Location: dashboard.php');
            exit;
        }
    } else {
        // === LOGIN ===
        $user = sanitize($_POST['username'] ?? '');
        $pass = $_POST['password'] ?? '';

        $found = null;
        foreach ($admins as $a) {
            if ($a['username'] === $user && password_verify($pass, $a['password'])) {
                $found = $a;
                break;
            }
        }

        if ($found) {
            // Update last login
            foreach ($admins as &$a) {
                if ($a['username'] === $found['username']) {
                    $a['last_login'] = date('Y-m-d H:i:s');
                }
            }
            unset($a);
            saveAdmins($admins);

            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $found['username'];
            $_SESSION['admin_role'] = $found['role'] ?? 'admin';
            writeLog('LOGIN', 'Login berhasil dari IP ' . getClientIp());
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Username atau password salah.';
            writeLog('LOGIN_FAILED', 'Percobaan login: ' . $user . ' dari IP ' . getClientIp());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isSetup ? 'Setup Admin' : 'Login Admin' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --bg:#060d09; --bg2:#0c1a12; --fg:#e4f2ea; --muted:#6b9a80; --accent:#10b981; --border:rgba(16,185,129,0.12); --card:rgba(12,26,18,0.85); }
        * { box-sizing:border-box; }
        body { font-family:'Plus Jakarta Sans',sans-serif; background:var(--bg); color:var(--fg); margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; }
        .login-bg { position:fixed; inset:0; z-index:0; background:radial-gradient(ellipse 60% 50% at 50% 50%, rgba(16,185,129,0.06) 0%, transparent 70%); }
        .card { background:var(--card); border:1px solid var(--border); border-radius:16px; backdrop-filter:blur(16px); }
        .fi { background:rgba(6,13,9,0.9); border:1.5px solid var(--border); color:var(--fg); border-radius:10px; padding:12px 14px; width:100%; font-size:14px; transition:all 0.25s ease; font-family:inherit; }
        .fi:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px rgba(16,185,129,0.12); }
        .fi::placeholder { color:var(--muted); opacity:0.5; }
        .btn { display:flex; align-items:center; justify-content:center; gap:8px; padding:12px 24px; border-radius:10px; font-size:15px; font-weight:700; cursor:pointer; border:none; transition:all 0.25s ease; font-family:inherit; width:100%; background:linear-gradient(135deg,#10b981,#059669); color:#fff; }
        .btn:hover { box-shadow:0 6px 24px rgba(16,185,129,0.3); transform:translateY(-1px); }
    </style>
</head>
<body>
    <div class="login-bg"></div>
    <div class="relative z-10 w-full max-w-sm px-4">
        <div class="card p-7 sm:p-8">
            <!-- Logo -->
            <div class="flex items-center justify-center gap-2.5 mb-6">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-700 flex items-center justify-center shadow-lg shadow-emerald-500/20">
                    <i class="fas fa-bolt text-white"></i>
                </div>
                <span class="font-display font-bold text-xl">PulsaKu</span>
            </div>

            <h1 class="font-display text-xl font-bold text-center mb-1">
                <?= $isSetup ? 'Buat Akun Admin' : 'Login Admin' ?>
            </h1>
            <p class="text-sm text-[var(--muted)] text-center mb-6">
                <?= $isSetup ? 'Ini pertama kalinya. Buat username dan password untuk mengakses panel admin.' : 'Masukkan kredensial Anda untuk melanjutkan.' ?>
            </p>

            <?php if ($error): ?>
            <div class="mb-4 p-3 rounded-lg bg-red-500/10 border border-red-500/20 text-red-300 text-sm flex items-start gap-2">
                <i class="fas fa-circle-xmark mt-0.5"></i>
                <?= $error ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="" autocomplete="off">
                <div class="mb-4">
                    <label class="block text-xs font-semibold mb-1.5">Username</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--muted)] text-sm"><i class="fas fa-user"></i></span>
                        <input type="text" name="username" class="fi !pl-9" placeholder="Masukkan username" required autofocus value="<?= sanitize($_POST['username'] ?? '') ?>">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-semibold mb-1.5">Password</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--muted)] text-sm"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" class="fi !pl-9" placeholder="Masukkan password" required>
                    </div>
                </div>

                <?php if ($isSetup): ?>
                <div class="mb-6">
                    <label class="block text-xs font-semibold mb-1.5">Konfirmasi Password</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--muted)] text-sm"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password2" class="fi !pl-9" placeholder="Ulangi password" required>
                    </div>
                </div>
                <?php else: ?>
                <div class="mb-6"></div>
                <?php endif; ?>

                <button type="submit" class="btn">
                    <i class="fas <?= $isSetup ? 'fa-user-plus' : 'fa-right-to-bracket' ?>"></i>
                    <?= $isSetup ? 'Buat Akun' : 'Login' ?>
                </button>
            </form>

            <?php if (!$isSetup): ?>
            <p class="text-center text-xs text-[var(--muted)] mt-5">
                <a href="../index.php" class="hover:text-emerald-400 transition-colors"><i class="fas fa-arrow-left mr-1"></i>Kembali ke website</a>
            </p>
            <?php endif; ?>
        </div>

        <p class="text-center text-[10px] text-[var(--muted)] mt-4 opacity-50">
            <?= $isSetup ? 'Setelah dibuat, akun admin tidak bisa direset dari sini.' : 'Dilindungi dengan password hashing bcrypt.' ?>
        </p>
    </div>
</body>
</html>