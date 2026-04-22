<?php
// admin/dashboard.php - Dashboard admin
// VERSI: 3.0 - SECURITY HARDENING
//   CHANGED: Query statistik — tambah filter periode_id agar data terisolasi per periode
//   CHANGED: Query berita terbaru — SELECT kolom spesifik, tambah filter periode
//   CHANGED: Output $berita['id'] dan $berita['slug'] — tambah htmlspecialchars() + cast (int)
//   UNCHANGED: Struktur HTML, CSS class, logika tampilan

require_once __DIR__ . '/header.php';

// ============================================
// AMBIL PERIODE AKTIF USER
// ============================================
// getUserPeriode() dari auth-check.php (sudah di-include via header → config)
// Mengembalikan periode_id yang sudah divalidasi ke DB
$periode_id = (int) getUserPeriode();

// ============================================
// STATISTIK
// ============================================
// [FIX] Tambah filter WHERE periode_id = ?
// Tanpa filter ini, admin biasa bisa melihat total data dari SEMUA periode,
// bukan hanya periode yang menjadi tanggung jawabnya.
// Superadmin yang selected_periode-nya sudah divalidasi juga ikut filter ini.
$totalBerita = (int) (dbFetchOne(
    "SELECT COUNT(*) as total FROM berita WHERE periode_id = ?",
    [$periode_id], "i"
)['total'] ?? 0);

$totalKementerian = (int) (dbFetchOne(
    "SELECT COUNT(*) as total FROM kementerian WHERE periode_id = ?",
    [$periode_id], "i"
)['total'] ?? 0);

$totalAnggota = (int) (dbFetchOne(
    "SELECT COUNT(*) as total FROM anggota_kementerian WHERE periode_id = ?",
    [$periode_id], "i"
)['total'] ?? 0);

$totalBPH = (int) (dbFetchOne(
    "SELECT COUNT(*) as total FROM struktur_bph WHERE periode_id = ?",
    [$periode_id], "i"
)['total'] ?? 0);

// ============================================
// BERITA TERBARU
// ============================================
// [FIX 1] SELECT kolom spesifik — tidak SELECT *
//   Hanya ambil kolom yang benar-benar ditampilkan di dashboard.
// [FIX 2] Filter periode_id — konsisten dengan statistik di atas.
// [FIX 3] Cast LIMIT sebagai integer langsung di query
$beritaTerbaru = dbFetchAll(
    "SELECT id, judul, slug, tanggal, penulis
     FROM berita
     WHERE periode_id = ?
     ORDER BY tanggal DESC
     LIMIT 5",
    [$periode_id],
    "i"
) ?? [];
?>

<!-- Konten spesifik halaman dashboard -->
<div class="top-bar">
    <div class="page-title">
        <h1>Dashboard</h1>
        <p>Selamat datang di panel admin BEM Kabinet Astawidya</p>
    </div>
    <div class="date-display">
        <i class="far fa-calendar-alt"></i>
        <?php echo date('d F Y'); ?>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-newspaper"></i>
        </div>
        <div class="stat-value"><?php echo $totalBerita; ?></div>
        <div class="stat-label">Total Berita</div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-value"><?php echo $totalKementerian; ?></div>
        <div class="stat-label">Total Kementerian</div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-user-friends"></i>
        </div>
        <div class="stat-value"><?php echo $totalAnggota; ?></div>
        <div class="stat-label">Total Anggota</div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-user-tie"></i>
        </div>
        <div class="stat-value"><?php echo $totalBPH; ?></div>
        <div class="stat-label">Total BPH</div>
    </div>
</div>

<!-- Recent News -->
<div class="recent-news">
    <div class="section-header">
        <h2><i class="fas fa-clock"></i> Berita Terbaru</h2>
        <a href="berita.php">Lihat Semua <i class="fas fa-arrow-right"></i></a>
    </div>

    <div class="news-list">
        <?php if (empty($beritaTerbaru)): ?>
            <div class="news-item">
                <div class="news-info">
                    <p style="color: #666; text-align: center; width: 100%;">
                        Belum ada berita.
                        <a href="berita-edit.php" style="color: #4A90E2;">Tulis berita pertama</a>
                    </p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($beritaTerbaru as $berita): ?>
            <div class="news-item">
                <div class="news-info">
                    <?php /* judul sudah htmlspecialchars — tidak berubah */ ?>
                    <h3><?php echo htmlspecialchars($berita['judul'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p>
                        <i class="far fa-calendar-alt"></i>
                        <?php echo date('d/m/Y', strtotime($berita['tanggal'])); ?>
                        <i class="far fa-user" style="margin-left: 15px;"></i>
                        <?php echo htmlspecialchars($berita['penulis'], ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                </div>
                <div class="news-actions">
                    <?php
                        // [FIX] Cast id ke (int) — cegah injeksi via URL parameter
                        $beritaId   = (int) $berita['id'];
                        // [FIX] Escape slug — bisa berisi karakter berbahaya jika ada bug di createSlug()
                        $beritaSlug = htmlspecialchars($berita['slug'], ENT_QUOTES, 'UTF-8');
                    ?>
                    <a href="berita-edit.php?id=<?php echo $beritaId; ?>" class="btn-edit">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="../berita-detail.php?slug=<?php echo $beritaSlug; ?>"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="btn-view">
                        <?php /* [FIX] rel="noopener noreferrer" pada target="_blank"
                                 Mencegah tab baru mengakses window.opener (Reverse Tabnapping) */ ?>
                        <i class="fas fa-eye"></i> Lihat
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Actions -->
<h2 style="margin-bottom: 20px;">Aksi Cepat</h2>
<div class="quick-actions">
    <a href="berita-edit.php" class="action-card">
        <i class="fas fa-plus-circle"></i>
        <span>Tambah Berita</span>
    </a>
    <a href="kepengurusan.php?action=new" class="action-card">
        <i class="fas fa-user-plus"></i>
        <span>Tambah Anggota</span>
    </a>
    <a href="kabinet.php" class="action-card">
        <i class="fas fa-edit"></i>
        <span>Edit Kabinet</span>
    </a>
    <a href="kontak.php" class="action-card">
        <i class="fas fa-phone-alt"></i>
        <span>Edit Kontak</span>
    </a>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>