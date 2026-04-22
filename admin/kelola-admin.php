<?php
// admin/kelola-admin.php
// VERSI: 4.1 - REMOVE reset_2fa, ADD toggle_aktif
//   CHANGED: Hapus fitur Reset 2FA — superadmin tidak bisa ubah 2FA orang lain
//   CHANGED: Tambah toggle Aktif/Nonaktif — cara yang benar untuk putus akses
//   CHANGED: Nonaktifkan akun langsung putuskan semua sesi aktifnya

$page_css = 'kelola-admin';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../includes/totp.php';

if ($_SESSION['admin_role'] !== 'superadmin' && !$user_can_access_all) {
    redirect('admin/dashboard.php', 'Akses ditolak!', 'error');
    exit();
}

$periode_list = dbFetchAll("SELECT * FROM periode_kepengurusan ORDER BY tahun_mulai DESC");
$admin_list   = dbFetchAll("
    SELECT u.id, u.username, u.nama, u.email, u.role, u.can_access_all,
           u.periode_id, u.totp_enabled, u.is_active, u.created_at,
           p.nama as periode_nama, p.tahun_mulai, p.tahun_selesai
    FROM users u
    LEFT JOIN periode_kepengurusan p ON u.periode_id = p.id
    ORDER BY u.role DESC, u.created_at DESC
");

$error   = '';
$success = '';

// Data untuk tampilkan QR setelah akun baru dibuat
$newAdminSecret   = null;
$newAdminUsername = null;
$newAdminQrUrl    = null;

// ============================================
// PROSES TAMBAH ADMIN
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'tambah') {
    if (!csrfVerify()) {
        $error = 'Request tidak valid.';
    } else {
        $username   = sanitizeText($_POST['username'] ?? '', 50);
        $password   = $_POST['password'] ?? '';
        $nama       = sanitizeText($_POST['nama']  ?? '', 100);
        $emailRaw   = sanitizeText($_POST['email'] ?? '', 100);
        $email      = filter_var($emailRaw, FILTER_VALIDATE_EMAIL) ? $emailRaw : '';
        $role       = in_array($_POST['role'] ?? '', ['admin','superadmin','sekretaris']) ? $_POST['role'] : 'admin';
        $periode_id = !empty($_POST['periode_id']) ? (int)$_POST['periode_id'] : null;
        $can_access_all = ($role === 'superadmin') ? 1 : 0;

        if (empty($username) || empty($password) || empty($nama)) {
            $error = 'Username, password, dan nama wajib diisi!';
        } elseif ($role === 'admin' && !$periode_id) {
            $error = 'Pilih periode untuk admin biasa!';
        } elseif (strlen($password) < 8) {
            $error = 'Password minimal 8 karakter!';
        } else {
            $cek = dbFetchOne("SELECT id FROM users WHERE username = ?", [$username], "s");
            if ($cek) {
                $error = "Username '{$username}' sudah digunakan!";
            } else {
                $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]);

                $enable_2fa = isset($_POST['enable_2fa']) && $_POST['enable_2fa'] === '1';
                $secret = $enable_2fa ? totpGenerateSecret() : null;
                $totp_enabled = $enable_2fa ? 1 : 0;

                dbBeginTransaction();
                try {
                    if ($role === 'superadmin') {
                        dbQuery(
                            "INSERT INTO users (username, password, nama, email, role, can_access_all, totp_secret, totp_enabled, is_active)
                             VALUES (?, ?, ?, ?, 'superadmin', 1, ?, ?, 1)",
                            [$username, $hashed, $nama, $email, $secret, $totp_enabled], "sssssi"
                        );
                    } else {
                        dbQuery(
                            "INSERT INTO users (username, password, nama, email, role, periode_id, can_access_all, totp_secret, totp_enabled, is_active)
                             VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, 1)",
                            [$username, $hashed, $nama, $email, $role, $periode_id, $secret, $totp_enabled], "sssssisi"
                        );
                    }
                    dbCommit();
                    $newId = dbLastId();
                    auditLog('CREATE', 'users', $newId, 'Tambah admin: ' . $username . ' (' . $role . ')');

                    if ($enable_2fa) {
                        $newAdminSecret   = $secret;
                        $newAdminUsername = $username;
                        $newAdminQrUrl    = totpGetUri($secret, $username, 'BEM Admin');
                        $success = "Admin '{$username}' berhasil dibuat! Bagikan QR/secret di bawah ke admin tersebut.";
                    } else {
                        $success = "Admin '{$username}' berhasil dibuat TANPA 2FA aktif.";
                    }

                    // Refresh daftar
                    $admin_list = dbFetchAll("
                        SELECT u.id, u.username, u.nama, u.email, u.role, u.can_access_all,
                               u.periode_id, u.totp_enabled, u.is_active, u.created_at,
                               p.nama as periode_nama, p.tahun_mulai, p.tahun_selesai
                        FROM users u LEFT JOIN periode_kepengurusan p ON u.periode_id = p.id
                        ORDER BY u.role DESC, u.created_at DESC
                    ");

                } catch (Exception $e) {
                    dbRollback();
                    error_log("[KELOLA-ADMIN] Tambah: " . $e->getMessage());
                    $error = 'Gagal menambahkan admin. Silakan coba lagi.';
                }
            }
        }
    }
}

// ============================================
// PROSES HAPUS ADMIN
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'hapus') {
    if (!csrfVerify()) {
        $error = 'Request tidak valid.';
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int)$_SESSION['admin_id']) {
            $error = 'Tidak dapat menghapus akun sendiri!';
        } elseif ($id > 0) {
            dbBeginTransaction();
            try {
                dbQuery("UPDATE struktur_bph SET created_by = NULL WHERE created_by = ?", [$id], "i");
                dbQuery("UPDATE anggota_bph SET created_by = NULL WHERE created_by = ?", [$id], "i");
                dbQuery("UPDATE kementerian SET created_by = NULL WHERE created_by = ?", [$id], "i");
                dbQuery("UPDATE anggota_kementerian SET created_by = NULL WHERE created_by = ?", [$id], "i");
                dbQuery("UPDATE struktur_organisasi SET created_by = NULL WHERE created_by = ?", [$id], "i");
                // Hapus sesi aktif admin ini
                dbQuery("DELETE FROM user_sessions WHERE user_id = ?", [$id], "i");
                dbQuery("DELETE FROM users WHERE id = ?", [$id], "i");
                dbCommit();
                auditLog('DELETE', 'users', $id, 'Hapus admin ID: ' . $id);
                redirect('admin/kelola-admin.php', 'Admin berhasil dihapus!', 'success');
                exit();
            } catch (Exception $e) {
                dbRollback();
                error_log("[KELOLA-ADMIN] Hapus: " . $e->getMessage());
                $error = 'Gagal menghapus admin.';
            }
        }
    }
}

// ============================================
// PROSES RESET PASSWORD
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_password') {
    if (!csrfVerify()) {
        $error = 'Request tidak valid.';
    } else {
        $id          = (int) ($_POST['id'] ?? 0);
        $new_password = $_POST['new_password'] ?? '';

        if ($id > 0 && strlen($new_password) >= 8) {
            $hashed = password_hash($new_password, PASSWORD_BCRYPT, ['cost'=>12]);
            dbQuery("UPDATE users SET password = ? WHERE id = ?", [$hashed, $id], "si");
            auditLog('UPDATE', 'users', $id, 'Reset password admin ID: ' . $id);
            redirect('admin/kelola-admin.php', 'Password berhasil direset!', 'success');
            exit();
        } else {
            $error = 'Password minimal 8 karakter.';
        }
    }
}

// ============================================
// PROSES TOGGLE AKTIF / NONAKTIF AKUN
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_aktif') {
    if (!csrfVerify()) {
        $error = 'Request tidak valid.';
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int)$_SESSION['admin_id']) {
            $error = 'Tidak dapat menonaktifkan akun sendiri!';
        } elseif ($id > 0) {
            $target = dbFetchOne(
                "SELECT username, is_active FROM users WHERE id = ?", [$id], "i"
            );
            if ($target) {
                $newStatus   = $target['is_active'] ? 0 : 1;
                dbQuery("UPDATE users SET is_active = ? WHERE id = ?", [$newStatus, $id], "ii");
                $statusLog = $newStatus ? 'diaktifkan' : 'dinonaktifkan';
                auditLog('UPDATE', 'users', $id, 'Akun ' . $target['username'] . ' ' . $statusLog);
                // Nonaktifkan → putuskan semua sesi aktifnya seketika
                if (!$newStatus) {
                    dbQuery("DELETE FROM user_sessions WHERE user_id = ?", [$id], "i");
                }
                $statusText = $newStatus ? 'diaktifkan kembali' : 'dinonaktifkan';
                redirect('admin/kelola-admin.php',
                    "Akun '" . htmlspecialchars($target['username']) . "' berhasil {$statusText}.",
                    'success');
                exit();
            }
        }
    }
}

// Flash message dari redirect
if (isset($_SESSION['flash'])) {
    $success = $_SESSION['flash']['message'];
    unset($_SESSION['flash']);
}
?>

<!-- Page Header -->
<div class="page-header">
    <h1><i class="fas fa-user-shield"></i> Kelola Admin Periode</h1>
    <p style="margin-bottom: 30px;">Atur admin untuk setiap periode kepengurusan (Superadmin Only)</p>
</div>

<?php flashMessage(); ?>

<?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<?php /* ===== TAMPILKAN QR CODE SETELAH BUAT AKUN BARU / RESET 2FA ===== */ ?>
<?php if ($newAdminSecret && $newAdminQrUrl): ?>
<div style="background:#1a1a2e;border:2px solid #4A90E2;border-radius:12px;padding:1.5rem;margin-bottom:1.5rem;">
    <h3 style="color:#4A90E2;margin-bottom:.5rem;font-size:1rem;">
        <i class="fas fa-qrcode"></i> Setup 2FA untuk: <strong><?php echo htmlspecialchars($newAdminUsername, ENT_QUOTES, 'UTF-8'); ?></strong>
    </h3>
    <p style="color:#f44336;font-size:.85rem;margin-bottom:1rem;">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>Simpan sekarang!</strong> Secret ini hanya ditampilkan sekali dan tidak bisa dilihat lagi.
    </p>
    <div style="display:flex;gap:1.5rem;flex-wrap:wrap;align-items:flex-start;">
        <!-- QR Code -->
        <div style="text-align:center;flex-shrink:0;">
            <div id="qrcode" style="display:inline-block;padding:12px;background:white;border-radius:8px;"></div>
            <br>
            <button type="button" id="downloadQrBtn" style="margin-top:12px;padding:8px 16px;background:#2E7D32;color:white;border:none;border-radius:6px;font-size:.9rem;cursor:pointer;"><i class="fas fa-download"></i> Download QR</button>
            <p style="color:#888;font-size:.75rem;margin-top:.5rem;">Scan dengan Aegis / Google Authenticator</p>
        </div>
        <!-- Secret text -->
        <div style="flex:1;min-width:220px;">
            <p style="color:#aaa;font-size:.85rem;margin-bottom:.5rem;">Atau masukkan secret key manual:</p>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                <div style="background:#111;border:1px solid #333;border-radius:8px;padding:1rem;font-family:monospace;font-size:1.1rem;letter-spacing:4px;color:#4A90E2;word-break:break-all;flex:1;text-align:center;">
                    <?php echo htmlspecialchars(strtoupper($newAdminSecret), ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <button type="button" id="copySecretBtn" style="padding:16px;background:#333;color:white;border:none;border-radius:8px;cursor:pointer;" title="Salin Key">
                    <i class="fas fa-copy"></i>
                </button>
            </div>
            <p style="color:#555;font-size:.75rem;margin-top:.5rem;">Type: TOTP &bull; Algorithm: SHA1 &bull; Digits: 6 &bull; Period: 30s</p>
            <p style="color:#aaa;font-size:.8rem;margin-top:1rem;">
                Admin tinggal scan/masukkan secret ini di Aegis, lalu bisa langsung login dengan 2FA.
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Grid 2 Kolom -->
<div class="admin-grid">

    <!-- Kolom Kiri: Form Tambah Admin -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-user-plus"></i> Tambah Admin Baru
        </div>
        <div class="card-body">
            <form method="POST" id="formTambah">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="tambah">

                <div class="form-group">
                    <label><i class="fas fa-user"></i> Username:</label>
                    <input type="text" name="username" class="form-control" required autocomplete="off">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password:</label>
                    <input type="password" name="password" class="form-control" required minlength="8" autocomplete="new-password">
                    <small>Minimal 8 karakter. Admin bisa ganti sendiri via pengaturan.</small>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-id-card"></i> Nama Lengkap:</label>
                    <input type="text" name="nama" class="form-control" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email:</label>
                    <input type="email" name="email" class="form-control">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-user-tag"></i> Role:</label>
                    <select name="role" class="form-control" id="roleSelect" onchange="togglePeriodeField()">
                        <option value="admin" selected>Admin Biasa</option>
                        <option value="sekretaris">Sekretaris</option>
                        <option value="superadmin">Superadmin</option>
                    </select>
                </div>

                <div class="form-group" id="periodeField">
                    <label><i class="fas fa-calendar-alt"></i> Periode:</label>
                    <select name="periode_id" class="form-control">
                        <option value="">-- Pilih Periode --</option>
                        <?php foreach ($periode_list as $p): ?>
                            <option value="<?php echo (int)$p['id']; ?>">
                                <?php echo htmlspecialchars($p['nama']); ?>
                                (<?php echo (int)$p['tahun_mulai']; ?>/<?php echo (int)$p['tahun_selesai']; ?>)
                                <?php echo $p['is_active'] ? '• AKTIF' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Admin biasa hanya bisa mengelola SATU periode</small>
                </div>

                <div class="form-group" style="background:rgba(74,144,226,.08);border:1px solid rgba(74,144,226,.3);border-radius:8px;padding:1rem;">
                    <label style="margin-bottom: 5px; color:#4A90E2;"><i class="fas fa-shield-alt"></i> Pengaturan Keamanan</label>
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" name="enable_2fa" value="1" checked> <strong>Aktifkan 2FA Langsung</strong>
                    </label>
                    <small style="display:block; margin-top:5px; color:#8BB9F0;">Jika dicentang, sistem akan men-generate Secret Key & QR Code saat akun dibuat. Lepas centang jika Admin ingin menyetel 2FA-nya sendiri nanti secara mandiri di profilnya.</small>
                </div>

                <button type="submit" class="btn-primary" style="width:100%;padding:12px;">
                    <i class="fas fa-save"></i> Tambah Admin
                </button>
            </form>
        </div>
    </div>

    <!-- Kolom Kanan: Daftar Admin -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-list"></i> Daftar Admin (<?php echo count($admin_list); ?>)
        </div>
        <div class="card-body">
            <div style="overflow-x: auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Nama</th>
                            <th>Role</th>
                            <th>Periode</th>
                            <th>2FA</th>
                            <th>Status</th>
                            <th style="text-align:center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($admin_list)): ?>
                            <tr>
                                <td colspan="6" style="padding:30px;text-align:center;color:#666;">
                                    <i class="fas fa-users" style="font-size:2rem;margin-bottom:10px;display:block;"></i>
                                    Belum ada admin
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($admin_list as $admin):
                                $adminId = (int)$admin['id'];
                                $isSelf  = ($adminId === (int)$_SESSION['admin_id']);
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($admin['username']); ?></strong>
                                    <?php if ($isSelf): ?>
                                        <span class="badge" style="background:#4A90E2;color:white;margin-left:5px;">Anda</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($admin['nama']); ?></td>
                                <td>
                                    <?php if ($admin['role'] === 'superadmin' || $admin['can_access_all']): ?>
                                        <span class="badge" style="background:gold;color:black;">
                                            <i class="fas fa-crown"></i> Superadmin
                                        </span>
                                    <?php elseif ($admin['role'] === 'sekretaris'): ?>
                                        <span class="badge" style="background:#2E7D32;color:white;">
                                            <i class="fas fa-file-signature"></i> Sekretaris
                                        </span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#4A90E2;color:white;">
                                            <i class="fas fa-user"></i> Admin
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($admin['periode_id']): ?>
                                        <span class="badge" style="background:#1a2f4a;color:#8BB9F0;">
                                            <?php echo htmlspecialchars($admin['periode_nama'] ?? 'Periode '.$admin['periode_id']); ?>
                                        </span>
                                    <?php elseif ($admin['role'] === 'superadmin' || $admin['can_access_all']): ?>
                                        <span style="color:#888;"><i class="fas fa-globe"></i> Semua</span>
                                    <?php else: ?>
                                        <span style="color:#888;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($admin['totp_enabled']): ?>
                                        <span style="color:#4caf50;font-size:.8rem;"><i class="fas fa-shield-alt"></i> Aktif</span>
                                    <?php else: ?>
                                        <span style="color:#f44336;font-size:.8rem;"><i class="fas fa-times"></i> Belum</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ((int)($admin['is_active'] ?? 0)): ?>
                                        <span style="color:#4caf50;font-size:.8rem;"><i class="fas fa-check-circle"></i> Aktif</span>
                                    <?php else: ?>
                                        <span style="color:#f44336;font-size:.8rem;"><i class="fas fa-ban"></i> Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php if (!$isSelf): ?>
                                    <div style="display:flex;gap:4px;justify-content:center;flex-wrap:wrap;">
                                        <!-- Reset Password -->
                                        <form method="POST" style="display:inline;" onsubmit="return konfirmasiResetPw(this)">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="hidden" name="id" value="<?php echo $adminId; ?>">
                                            <input type="hidden" name="new_password" id="pw_<?php echo $adminId; ?>" value="">
                                            <button type="submit" class="btn-edit" title="Reset Password">
                                                <i class="fas fa-key"></i>
                                            </button>
                                        </form>

                                        <!-- Toggle Aktif/Nonaktif -->
                                        <form method="POST" style="display:inline;"
                                              onsubmit="return confirm('<?php echo (int)($admin['is_active'] ?? 0) ? 'Nonaktifkan' : 'Aktifkan kembali'; ?> akun <?php echo htmlspecialchars(addslashes($admin['username'])); ?>?')">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="action" value="toggle_aktif">
                                            <input type="hidden" name="id" value="<?php echo $adminId; ?>">
                                            <button type="submit"
                                                    class="btn-edit"
                                                    title="<?php echo (int)($admin['is_active'] ?? 0) ? 'Nonaktifkan' : 'Aktifkan'; ?>"
                                                    style="background:<?php echo (int)($admin['is_active'] ?? 0) ? '#f44336' : '#4caf50'; ?>;">
                                                <i class="fas <?php echo (int)($admin['is_active'] ?? 0) ? 'fa-ban' : 'fa-check'; ?>"></i>
                                            </button>
                                        </form>

                                        <!-- Hapus -->
                                        <form method="POST" style="display:inline;"
                                              onsubmit="return confirm('Yakin hapus admin <?php echo htmlspecialchars(addslashes($admin['username'])); ?>?')">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="action" value="hapus">
                                            <input type="hidden" name="id" value="<?php echo $adminId; ?>">
                                            <button type="submit" class="btn-delete" title="Hapus Admin">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                    <?php else: ?>
                                        <span style="color:#666;font-size:.8rem;">(Akun sendiri)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Informasi Hak Akses -->
<div class="card info-card-access">
    <div class="card-header">
        <i class="fas fa-info-circle"></i> Informasi Hak Akses
    </div>
    <div class="card-body">
        <div class="access-grid">
            <div class="access-card superadmin">
                <div class="access-icon"><i class="fas fa-crown"></i></div>
                <h3 class="access-title">Superadmin</h3>
                <ul class="access-list">
                    <li><i class="fas fa-check-circle"></i> Bisa mengakses SEMUA periode</li>
                    <li><i class="fas fa-check-circle"></i> Bisa menambah/menghapus admin</li>
                    <li><i class="fas fa-check-circle"></i> Bisa membuat periode baru</li>
                    <li><i class="fas fa-check-circle"></i> Bisa mengaktifkan/menonaktifkan periode</li>
                    <li><i class="fas fa-check-circle"></i> Bisa mengganti periode yang dikelola</li>
                </ul>
            </div>
            <div class="access-card admin">
                <div class="access-icon"><i class="fas fa-user"></i></div>
                <h3 class="access-title">Admin Biasa</h3>
                <ul class="access-list">
                    <li><i class="fas fa-check-circle"></i> Hanya bisa mengelola SATU periode</li>
                    <li><i class="fas fa-check-circle"></i> Tidak bisa melihat periode lain</li>
                    <li><i class="fas fa-check-circle"></i> Tidak bisa menambah admin</li>
                    <li><i class="fas fa-check-circle"></i> Tidak bisa menghapus periode</li>
                    <li><i class="fas fa-check-circle"></i> Data terisolasi per periode</li>
                </ul>
            </div>
            <div class="access-card admin">
                <div class="access-icon"><i class="fas fa-file-signature"></i></div>
                <h3 class="access-title">Sekretaris</h3>
                <ul class="access-list">
                    <li><i class="fas fa-check-circle"></i> Sama seperti Admin Biasa</li>
                    <li><i class="fas fa-check-circle"></i> Tambahan Akses ke Manajemen Arsip & Buat Surat otomatis</li>
                    <li><i class="fas fa-check-circle"></i> Data terisolasi per periode kepengurusan</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
function togglePeriodeField() {
    const role = document.getElementById('roleSelect').value;
    document.getElementById('periodeField').style.display = role === 'superadmin' ? 'none' : 'block';
}

function konfirmasiResetPw(form) {
    const pw = prompt('Masukkan password baru (minimal 8 karakter):');
    if (!pw) return false;
    if (pw.length < 8) { alert('Password minimal 8 karakter!'); return false; }
    form.querySelector('input[name="new_password"]').value = pw;
    return confirm('Reset password sekarang?');
}

document.addEventListener('DOMContentLoaded', function() {
    togglePeriodeField();
});

document.getElementById('formTambah')?.addEventListener('submit', function(e) {
    const role = document.getElementById('roleSelect').value;
    const periodeId = document.querySelector('select[name="periode_id"]').value;
    // admin dan sekretaris WAJIB pilih periode
    if ((role === 'admin' || role === 'sekretaris') && !periodeId) {
        e.preventDefault();
        alert('Pilih periode untuk admin/sekretaris!');
    }
});
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var qrContainer = document.getElementById("qrcode");
    if(qrContainer) {
        var qrContent = <?php echo json_encode($newAdminQrUrl ?? ''); ?>;
        if(qrContent) {
            new QRCode(qrContainer, {
                text: qrContent,
                width: 180,
                height: 180,
                colorDark : "#000000",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.M
            });

            document.getElementById('downloadQrBtn').addEventListener('click', function() {
                var canvas = qrContainer.querySelector("canvas");
                if(canvas) {
                    var url = canvas.toDataURL("image/png");
                    var a = document.createElement("a");
                    a.href = url;
                    a.download = "BEM_Admin_2FA_<?php echo htmlspecialchars($newAdminUsername ?? '', ENT_QUOTES, 'UTF-8'); ?>.png";
                    a.click();
                } else {
                    alert("QR Code belum siap didownload.");
                }
            });
            
            document.getElementById('copySecretBtn').addEventListener('click', function() {
                var text = "<?php echo htmlspecialchars(strtoupper($newAdminSecret ?? ''), ENT_QUOTES, 'UTF-8'); ?>";
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text).then(function() {
                        var btn = document.getElementById('copySecretBtn');
                        btn.innerHTML = '<i class="fas fa-check"></i>';
                        setTimeout(function(){ btn.innerHTML = '<i class="fas fa-copy"></i>'; }, 2000);
                    });
                } else {
                    alert("Fitur copy otomatis tidak didukung di browser ini.");
                }
            });
        }
    }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>