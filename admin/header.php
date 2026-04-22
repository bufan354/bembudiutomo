<?php
ob_start();
// admin/header.php - Header untuk halaman admin
// VERSI: 3.2 - Minor: komentar untuk peningkatan CSP di masa depan
//   CHANGED: Versi, tambah komentar saran CSP
//   UNCHANGED: Semua logika keamanan tetap sama

// ============================================
// ANTI-CACHE HEADERS (untuk halaman admin)
// ============================================
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Wed, 11 Jan 1984 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");

// ============================================
// SECURITY HEADERS
// ============================================
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
// CSP: Saat ini mengizinkan unsafe-inline untuk script dan style karena
// adanya toggle password, strength meter, dll. Jika memungkinkan, gunakan nonce
// untuk meningkatkan keamanan tanpa mengorbankan fungsionalitas.
header("Content-Security-Policy: "
    . "default-src 'self'; "
    . "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; "
    . "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; "
    . "font-src 'self' https://cdnjs.cloudflare.com; "
    . "img-src 'self' data:; "
    . "connect-src 'self'; "
    . "frame-ancestors 'none';"
);

// ============================================
// LOAD DEPENDENSI & CEK LOGIN
// ============================================
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/config.php';

$current_page = basename($_SERVER['PHP_SELF']);

if ($current_page !== 'login.php') {
    requireLogin();
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_role = $_SESSION['admin_role'] ?? 'admin';

$isSuperadmin = $admin_role === 'superadmin'
                || !empty($_SESSION['admin_can_access_all']);

// ============================================
// CACHE BUSTER UNTUK CSS
// ============================================
$adminCssPath = __DIR__ . '/css/admin.css';
$adminCssVer  = file_exists($adminCssPath) ? filemtime($adminCssPath) : '1';

$pageCssTag = '';
if (isset($page_css)) {
    $safeCss = preg_replace('/[^a-zA-Z0-9\-_]/', '', $page_css);
    if (!empty($safeCss)) {
        $pageCssPath = __DIR__ . '/css/' . $safeCss . '.css';
        $pageCssVer  = file_exists($pageCssPath) ? filemtime($pageCssPath) : '1';
        $pageCssUrl  = htmlspecialchars(
            baseUrl('admin/css/' . $safeCss . '.css') . '?v=' . $pageCssVer,
            ENT_QUOTES, 'UTF-8'
        );
        $pageCssTag = "<link rel=\"stylesheet\" href=\"{$pageCssUrl}\">";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - BEM Kabinet Astawidya</title>

    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">

    <link rel="icon" type="image/svg+xml"  href="<?php echo baseUrl('assets/images/favicon/favicon.svg'); ?>">
    <link rel="icon" type="image/x-icon"   href="<?php echo baseUrl('assets/images/favicon/favicon.ico'); ?>">
    <link rel="icon" type="image/png" sizes="96x96" href="<?php echo baseUrl('assets/images/favicon/favicon-96x96.png'); ?>">
    <link rel="apple-touch-icon" sizes="180x180"    href="<?php echo baseUrl('assets/images/favicon/apple-touch-icon.png'); ?>">
    <link rel="manifest" href="<?php echo baseUrl('assets/images/favicon/site.webmanifest'); ?>">
    <meta name="theme-color" content="#4A90E2">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(baseUrl('admin/css/admin.css') . '?v=' . $adminCssVer, ENT_QUOTES, 'UTF-8'); ?>">

    <?php echo $pageCssTag; ?>
</head>
<body class="<?php echo htmlspecialchars(basename($current_page, '.php'), ENT_QUOTES, 'UTF-8'); ?>">
<div class="admin-wrapper">

<?php if ($current_page !== 'login.php'): ?>

    <!-- Mobile Topbar -->
    <div class="mobile-topbar">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle menu">
            <i class="fas fa-bars"></i>
        </button>
        <div class="mobile-brand">
            <span>BEM Admin</span>
            <?php if (isset($periode_data)): ?>
            <small><?php echo htmlspecialchars($periode_data['nama'] ?? 'Astawidya', ENT_QUOTES, 'UTF-8'); ?></small>
            <?php endif; ?>
        </div>
        <div class="mobile-user">
            <i class="fas fa-user-circle"></i>
        </div>
    </div>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <button class="sidebar-close" id="sidebarClose" aria-label="Tutup menu">
            <i class="fas fa-times"></i>
        </button>

        <div class="sidebar-header">
            <h2>BEM Admin</h2>
            <p>Kabinet Astawidya</p>
        </div>

        <?php if (isset($periode_data)): ?>
        <div class="periode-info">
            <div class="periode-info-label">Periode Aktif</div>
            <div class="periode-info-nama">
                <?php echo htmlspecialchars($periode_data['nama'] ?? 'Astawidya', ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <div class="periode-info-tahun">
                <?php
                $tahunMulai   = htmlspecialchars($periode_data['tahun_mulai']   ?? '2025', ENT_QUOTES, 'UTF-8');
                $tahunSelesai = htmlspecialchars($periode_data['tahun_selesai'] ?? '2026', ENT_QUOTES, 'UTF-8');
                echo $tahunMulai . '/' . $tahunSelesai;
                ?>
            </div>
        </div>
        <?php endif; ?>

        <nav class="sidebar-menu">
            <a href="dashboard.php"
               class="<?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
            </a>
            <a href="berita.php"
               class="<?php echo $current_page === 'berita.php' ? 'active' : ''; ?>">
                <i class="fas fa-newspaper"></i><span>Berita</span>
            </a>
            <a href="kepengurusan.php"
               class="<?php echo $current_page === 'kepengurusan.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i><span>Kepengurusan</span>
            </a>
            <a href="kabinet.php"
               class="<?php echo $current_page === 'kabinet.php' ? 'active' : ''; ?>">
                <i class="fas fa-crown"></i><span>Kabinet</span>
            </a>
            <a href="visi-misi.php"
               class="<?php echo $current_page === 'visi-misi.php' ? 'active' : ''; ?>">
                <i class="fas fa-bullseye"></i><span>Visi Misi</span>
            </a>
            <a href="kontak.php"
               class="<?php echo $current_page === 'kontak.php' ? 'active' : ''; ?>">
                <i class="fas fa-address-book"></i><span>Kontak</span>
            </a>
            <a href="pengaturan.php"
               class="<?php echo $current_page === 'pengaturan.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i><span>Pengaturan</span>
            </a>
            <a href="upload-struktur.php"
               class="<?php echo $current_page === 'upload-struktur.php' ? 'active' : ''; ?>">
                <i class="fas fa-image"></i><span>Upload Struktur</span>
            </a>

            <?php if ($admin_role === 'sekretaris' || $isSuperadmin): ?>
            <div class="menu-divider"></div>
            <div class="menu-label">Surat & Arsip</div>
            <a href="arsip-surat.php"
               class="<?php echo $current_page === 'arsip-surat.php' ? 'active' : ''; ?>">
                <i class="fas fa-folder-open"></i><span>Arsip Surat</span>
            </a>
            <a href="buat-surat.php"
               class="<?php echo $current_page === 'buat-surat.php' ? 'active' : ''; ?>">
                <i class="fas fa-file-signature"></i><span>Buat Surat Otomatis</span>
            </a>
            <a href="pengaturan-surat.php"
               class="<?php echo $current_page === 'pengaturan-surat.php' ? 'active' : ''; ?>">
                <i class="fas fa-cogs"></i><span>Pengaturan Surat</span>
            </a>
            <?php endif; ?>

            <?php if ($isSuperadmin): ?>
            <div class="menu-divider"></div>
            <div class="menu-label">Superadmin</div>

            <a href="periode-kepengurusan.php"
               class="<?php echo $current_page === 'periode-kepengurusan.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i><span>Periode</span>
            </a>
            <a href="kelola-admin.php"
               class="<?php echo $current_page === 'kelola-admin.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-shield"></i><span>Kelola Admin</span>
            </a>
            <a href="ganti-periode.php"
               class="<?php echo $current_page === 'ganti-periode.php' ? 'active' : ''; ?>">
                <i class="fas fa-sync-alt"></i><span>Ganti Periode</span>
            </a>
            <a href="audit-log.php"
               class="<?php echo $current_page === 'audit-log.php' ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-list"></i><span>Audit Log</span>
            </a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <div>
                    <div class="user-name">
                        <?php echo htmlspecialchars($admin_name, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <div class="user-role">
                        <?php echo htmlspecialchars($admin_role, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </div>
            </div>
            <a href="logout.php?csrf_token=<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>"
               class="logout-btn"
               onclick="return confirm('Yakin ingin logout?')">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </aside>

<?php endif; ?>

    <!-- Main Content -->
    <main class="main-content">
        <?php flashMessage(); ?>

        <?php if ($current_page !== 'login.php'): ?>
        <div class="breadcrumb">
            <i class="fas fa-home"></i>
            <a href="dashboard.php">Dashboard</a>
            <?php if ($current_page !== 'dashboard.php'):
                $page_label = ucwords(str_replace(['.php', '-'], ['', ' '], $current_page));
            ?>
                <span class="separator">/</span>
                <span><?php echo htmlspecialchars($page_label, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>

            <?php if (isset($periode_data)): ?>
            <span class="separator">|</span>
            <span class="breadcrumb-periode">
                <i class="fas fa-calendar-alt"></i>
                <?php echo htmlspecialchars($periode_data['nama'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                (<?php echo $tahunMulai . '/' . $tahunSelesai; ?>)
            </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>