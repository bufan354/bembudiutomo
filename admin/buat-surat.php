<?php
// admin/buat-surat.php
$page_css = 'arsip-surat';
require_once __DIR__ . '/header.php';

requireSekretaris();
$periode_id = getUserPeriode();
$error = '';
$success = '';

// Helper Bulan Romawi
function getRomawiBulan($bulan) {
    $romawi = ['1'=>'I', '2'=>'II', '3'=>'III', '4'=>'IV', '5'=>'V', '6'=>'VI', '7'=>'VII', '8'=>'VIII', '9'=>'IX', '10'=>'X', '11'=>'XI', '12'=>'XII'];
    return $romawi[(int)$bulan] ?? 'I';
}

// Mode Edit Setup
$edit_id = (isset($_GET['edit']) && is_numeric($_GET['edit'])) ? (int)$_GET['edit'] : 0;
$clone_id = (isset($_GET['clone']) && is_numeric($_GET['clone'])) ? (int)$_GET['clone'] : 0;
$is_edit = false;
$is_clone = false;
$edit_data = [];

// Defaults untuk Create Baru
$kode_kegiatan = 'BEMCUP';
$jenis_surat_val = 'L';

$target_id = $edit_id > 0 ? $edit_id : $clone_id;

if ($target_id > 0) {
    $existing = dbFetchOne("SELECT * FROM arsip_surat WHERE id = ? AND periode_id = ? AND jenis_surat IN ('L', 'D')", [$target_id, $periode_id], "ii");
    if ($existing) {
        if ($edit_id > 0) $is_edit = true;
        if ($clone_id > 0) $is_clone = true;
        // Parse data
        $konten = json_decode($existing['konten_surat'], true) ?: [];
        $edit_data = array_merge($existing, $konten);
        
        // Extract nomor_urut and kode_kegiatan from nomor_surat
        // Format: 012/L/BEMCUP/BEM/IV/2026
        $parts = explode('/', $existing['nomor_surat']);
        $edit_data['nomor_urut']    = $parts[0] ?? '';
        $jenis_surat_val            = $parts[1] ?? 'L';
        $kode_kegiatan              = $parts[2] ?? 'BEMCUP';

        // Cek apakah tergabung dalam grup (multi-recipient)
        $group_count = dbFetchOne("SELECT COUNT(*) as total FROM arsip_surat WHERE nomor_surat = ? AND periode_id = ?", [$existing['nomor_surat'], $periode_id], "si")['total'];
        $is_group = $group_count > 1;
    } else {
        $error = "Data arsip surat tidak ditemukan atau hak akses ditolak.";
    }
}

// Rekomendasi Nomor Urut per Jenis (Otomatis meneruskan nomor terbesar)
function getLastSequence($jenis, $periode_id) {
    global $conn;
    $last = dbFetchOne("SELECT nomor_surat FROM arsip_surat WHERE periode_id = ? AND jenis_surat = ? ORDER BY id DESC LIMIT 1", [$periode_id, $jenis], "is");
    if ($last && !empty($last['nomor_surat'])) {
        $parts = explode('/', $last['nomor_surat']);
        return (int)$parts[0];
    }
    return 0;
}

$count_L = getLastSequence('L', $periode_id);
$count_D = getLastSequence('D', $periode_id);

$next_L = str_pad($count_L + 1, 3, '0', STR_PAD_LEFT);
$next_D = str_pad($count_D + 1, 3, '0', STR_PAD_LEFT);

$next_urut_default = ($jenis_surat_val === 'D') ? $next_D : $next_L;

if ($is_edit || $is_clone) {
    $next_urut_default = $edit_data['nomor_urut'];
}

$bulan_romawi = getRomawiBulan(date('n'));
$tahun = date('Y');

// Ambil Template Tersimpan
$templates = dbFetchAll("SELECT * FROM surat_templates WHERE periode_id = ?", [$periode_id], "i");
$list_perihal = array_filter($templates, fn($t) => $t['jenis'] === 'perihal');
$list_tujuan  = array_filter($templates, fn($t) => $t['jenis'] === 'tujuan');

// Proses Simpan / Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify()) {
        $error = 'Token CSRF tidak valid. Silakan muat ulang halaman.';
    } else {
        $action_type   = $_POST['action_type'] ?? 'insert';
        $jenis_surat   = $_POST['jenis_surat'] === 'D' ? 'D' : 'L';
        
        // Dinamis formasi string nomor surat yang mengizinkan override baik saat edit maupun create
        $nomor_urut    = sanitizeText($_POST['nomor_urut'], 10);
        $kode_keg      = strtoupper(sanitizeText($_POST['kode_kegiatan'], 50));
        $nomor_surat   = "{$nomor_urut}/{$jenis_surat}/{$kode_keg}/BEM/{$bulan_romawi}/{$tahun}";
        
        $tanggal_dikirim_raw = sanitizeText($_POST['tanggal_dikirim'], 50);
        $tanggal_dikirim = (empty($tanggal_dikirim_raw) || $tanggal_dikirim_raw === 'Belum Di kirim') ? null : $tanggal_dikirim_raw;
        
        $perihal        = sanitizeText($_POST['perihal'], 255);
        $tempat_tanggal = sanitizeText($_POST['tempat_tanggal'], 255);
        $tujuan         = strip_tags(trim($_POST['tujuan']));
        
        // Data Spesifik Konten HTML (Json)
        $konten_data = [
            'sapaan_tujuan'           => sanitizeText($_POST['sapaan_tujuan'] ?? '', 50),
            'nama_kegiatan'           => sanitizeText($_POST['nama_kegiatan'] ?? '', 100),
            'tema'                    => sanitizeText($_POST['tema'] ?? '', 255),
            'tema_kegiatan'           => strip_tags(trim($_POST['tema_kegiatan'] ?? '')), // custom override
            'pelaksanaan_hari_tanggal'=> sanitizeText($_POST['pelaksanaan_hari_tanggal'], 100),
            'pelaksanaan_waktu'       => sanitizeText($_POST['pelaksanaan_waktu'], 100),
            'pelaksanaan_tempat'      => sanitizeText($_POST['pelaksanaan_tempat'], 100),
            'konteks'                 => sanitizeText($_POST['konteks'] ?? '', 255),
            'panitia_ketua'           => strtoupper(sanitizeText($_POST['panitia_ketua'], 100)),
            'panitia_ketua_ttd'       => $_POST['panitia_ketua_ttd'] ?? '',
            'panitia_sekretaris'      => strtoupper(sanitizeText($_POST['panitia_sekretaris'], 100)),
            'panitia_sekretaris_ttd'  => $_POST['panitia_sekretaris_ttd'] ?? '',
            'use_ttd_warek'           => $_POST['use_ttd_warek'] ?? '1',
            'use_ttd_presma'          => $_POST['use_ttd_presma'] ?? '1',
            'use_cap_panitia'         => $_POST['use_cap_panitia'] ?? '1',
            'use_cap_warek'           => $_POST['use_cap_warek'] ?? '1',
            'use_cap_presma'          => $_POST['use_cap_presma'] ?? '1',
            'tembusan'                => strip_tags(trim($_POST['tembusan'] ?? ''))
        ];

        // Maintance lampiran yang sudah ada saat edit
        $lampiran_files = [];
        if ($is_edit && !empty($konten['lampiran_files'])) {
            $lampiran_files = $konten['lampiran_files'];
        }

        // Handle File Lampiran Uploads if any
        if (isset($_FILES['lampiran_surat'])) {
            $upload_lampiran_dir = rtrim(UPLOAD_PATH, '/\\') . '/umum/lampiran/';
            if (!is_dir($upload_lampiran_dir)) mkdir($upload_lampiran_dir, 0755, true);
            foreach ($_FILES['lampiran_surat']['name'] as $key => $filename) {
                if ($_FILES['lampiran_surat']['error'][$key] === UPLOAD_ERR_OK && !empty($filename)) {
                    $tmp_name = $_FILES['lampiran_surat']['tmp_name'][$key];
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    if ($ext === 'pdf') {
                        $new_name = uniqid('lamp_', true) . '.pdf';
                        if (move_uploaded_file($tmp_name, $upload_lampiran_dir . $new_name)) {
                            // save relative path compatible with uploadUrl() helper
                            $lampiran_files[] = 'umum/lampiran/' . $new_name;
                        }
                    }
                }
            }
        }
        $konten_data['lampiran_files'] = $lampiran_files;
        
        $konten_json = json_encode($konten_data);
        $created_by  = $_SESSION['admin_id'];

        try {
            if ($action_type === 'update' && $is_edit) {
                // Find all connected letters (including this one) based on OLD nomor_surat
                $old_nomor_surat = $existing['nomor_surat'];
                $connected = dbFetchAll("SELECT id, konten_surat, tujuan FROM arsip_surat WHERE nomor_surat = ? AND periode_id = ?", [$old_nomor_surat, $periode_id], "si");
                
                foreach ($connected as $conn_surat) {
                    $conn_id = $conn_surat['id'];
                    $conn_konten_old = json_decode($conn_surat['konten_surat'], true) ?: [];
                    
                    // Create a copy of the NEW shared konten_data
                    $new_konten = $konten_data;
                    
                    if ($conn_id == $edit_id) {
                        // For the currently edited letter, use the form's tujuan & sapaan
                        $final_tujuan = $tujuan;
                    } else {
                        // For other connected letters, preserve their existing tujuan & sapaan
                        $final_tujuan = $conn_surat['tujuan'];
                        $new_konten['sapaan_tujuan'] = $conn_konten_old['sapaan_tujuan'] ?? '';
                    }
                    
                    $final_konten_json = json_encode($new_konten);
                    
                    dbQuery(
                        "UPDATE arsip_surat SET jenis_surat=?, tanggal_dikirim=?, nomor_surat=?, perihal=?, tujuan=?, tempat_tanggal=?, konten_surat=? WHERE id=? AND periode_id=?",
                        [$jenis_surat, $tanggal_dikirim, $nomor_surat, $perihal, $final_tujuan, $tempat_tanggal, $final_konten_json, $conn_id, $periode_id],
                        "sssssssii"
                    );
                }
                
                $msg = count($connected) > 1 ? 'Arsip Surat dan ' . (count($connected)-1) . ' salinannya berhasil diperbarui!' : 'Arsip Surat berhasil diperbarui!';
                auditLog('UPDATE', 'arsip_surat', $edit_id, 'Mengubah arsip surat' . (count($connected)>1 ? ' (beserta salinannya)' : '') . ': ' . $nomor_surat);
                redirect('admin/cetak-surat.php?id=' . $edit_id, $msg . ' Membuka halaman cetak...', 'success');
            } else {
                dbQuery(
                    "INSERT INTO arsip_surat (periode_id, jenis_surat, tanggal_dikirim, nomor_surat, perihal, tujuan, tempat_tanggal, konten_surat, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                     [$periode_id, $jenis_surat, $tanggal_dikirim, $nomor_surat, $perihal, $tujuan, $tempat_tanggal, $konten_json, $created_by],
                     "isssssssi"
                );
                $new_id = dbLastId();
                auditLog('CREATE', 'arsip_surat', $new_id, 'Membuat surat baru: ' . $nomor_surat);
                redirect('admin/cetak-surat.php?id=' . $new_id, 'Surat berhasil dibuat! Membuka halaman cetak...', 'success');
            }
            exit();
        } catch (Exception $e) {
            error_log("Gagal membuat/mengubah surat: " . $e->getMessage());
            $error = 'Terjadi kesalahan sistem saat menyimpan data surat.';
        }
    }
}

// Data Helper Defaults (Apabila Bukan Edit)
$def = [
    'sapaan_tujuan'            => '',
    'tanggal_dikirim'          => 'Belum Di kirim',
    'perihal'                  => 'Permohonan Support Kegiatan',
    'tempat_tanggal'           => 'Majalengka, ' . date('j F Y'),
    'tujuan'                   => "Bapak Muhammad Iqbal, S.,S.T.\nKetua AFKAB Majalengka",
    'nama_kegiatan'            => 'BEM CUP',
    'tema'                     => 'Membangun Solidaritas dan Sportivitas Dalam Semangat Kebersamaan',
    'tema_kegiatan'            => '', // kosong = mode template; diisi = mode custom
    'pelaksanaan_hari_tanggal' => "Jum'at-Minggu, 17-19 Januari 2025",
    'pelaksanaan_waktu'        => '08.00 s.d Selesai',
    'pelaksanaan_tempat'       => 'Kampus INSTBUNAS Majalengka, GOR Futsal...',
    'konteks'                  => '',
    'panitia_ketua'            => 'ADE LIA AGUSTINA',
    'panitia_sekretaris'       => 'ANGGI DIYARTI',
    'tembusan'                 => ''
];
if ($is_edit || $is_clone) {
    // Fill gaps
    foreach($def as $k=>$v) {
        if(!isset($edit_data[$k])) $edit_data[$k] = $v;
    }
    
    // Clear specific fields if it's a clone
    if ($is_clone) {
        $edit_data['tujuan'] = '';
        $edit_data['sapaan_tujuan'] = '';
        // If it's a clone, we also probably want to keep the lampiran intact or clear it? 
        // We'll leave the arrays as is, the user can re-upload or keep the old ones.
    }
} else {
    $edit_data = $def;
}

?>

<div class="page-header">
    <h1><i class="fas fa-file-signature"></i> <?php echo $is_edit ? 'Edit Surat Arsip' : ($is_clone ? 'Duplikat Surat' : 'Buat Surat Otomatis'); ?></h1>
    <p><?php echo $is_edit ? 'Memperbaiki isi detail surat yang sudah dibuat (Nomor Surat otomatis dikunci).' : ($is_clone ? 'Membuat salinan surat dengan nomor yang sama untuk tujuan penerima yang berbeda.' : 'Isi template formulir di bawah ini untuk menghasilkan surat PDF yang siap cetak.'); ?></p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<form method="POST" class="card" enctype="multipart/form-data">
    <?php echo csrfField(); ?>
    <input type="hidden" name="action_type" value="<?php echo $is_edit ? 'update' : 'insert'; ?>">

    <div class="card-header">
        <i class="fas <?php echo $is_edit ? 'fa-edit' : ($is_clone ? 'fa-copy' : 'fa-plus'); ?>"></i> <?php echo $is_edit ? 'Form Edit Surat #'.$edit_data['id'] : ($is_clone ? 'Form Duplikat Surat' : 'Form Template Surat Kegiatan'); ?>
    </div>
    <div class="card-body">
        
        <h3 style="border-bottom: 1px solid #333; padding-bottom: 10px; margin-bottom: 20px; color:#4A90E2;">1. Meta Surat Resmi</h3>
        
        <?php if($is_group && $is_edit): ?>
            <div style="background: rgba(74, 144, 226, 0.1); border: 1px solid #4A90E2; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                <strong style="color: #4A90E2;"><i class="fas fa-layer-group"></i> Info Grup Surat (Multi-Recipient)</strong><br>
                Surat ini memiliki <strong><?php echo $group_count - 1; ?> salinan</strong> dengan nomor yang sama. 
                <span style="color:#aaa;">Perubahan pada Nomor, Perihal, Tanggal, dan Isi Kegiatan akan otomatis memperbarui seluruh anggota grup ini.</span>
            </div>
        <?php endif; ?>

        <?php if($is_edit): ?>
            <div style="background: rgba(42, 53, 69, 0.5); border: 1px solid #3a4a5a; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                <strong style="color: #4A90E2;"><i class="fas fa-info-circle"></i> Info Mode Edit</strong><br>
                Anda sedang mengubah detail atau penomoran untuk Surat #<?php echo htmlspecialchars($edit_data['id']); ?> secara retrospektif.
            </div>
        <?php elseif($is_clone): ?>
            <div style="background: rgba(39, 174, 96, 0.15); border: 1px solid #27ae60; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                <strong style="color: #27ae60;"><i class="fas fa-copy"></i> Info Mode Duplikat</strong><br>
                Anda sedang menambah penerima baru untuk nomor surat ini. Identitas grup (Nomor, Perihal, dan Tanggal) dikunci agar tetap sinkron. 
                Silakan isi <strong>Sapaan</strong> dan <strong>Tujuan</strong> yang berbeda.
            </div>
        <?php endif; ?>
        
        <div style="display: flex; gap: 20px; margin-bottom: 15px; flex-wrap: wrap;">
            <div class="form-group" style="flex:1; min-width:200px;">
                <label>Nomor Urut (Misal: 012)</label>
                <input type="text" id="nomor_urut_input" name="nomor_urut" class="form-control" value="<?php echo htmlspecialchars($next_urut_default); ?>" required onkeyup="updatePreview()" <?php echo $is_clone ? 'readonly style="background:rgba(255,255,255,0.05); color:#888;"' : ''; ?>>
            </div>
            <div class="form-group" style="flex:1; min-width:200px;">
                <label>Jenis Surat</label>
                <script>
                    const nextL_val = "<?php echo $next_L; ?>";
                    const nextD_val = "<?php echo $next_D; ?>";
                    function gantiNomorUrut(sel) {
                        <?php if(!$is_edit && !$is_clone): ?>
                        document.getElementById('nomor_urut_input').value = (sel.value === 'L') ? nextL_val : nextD_val;
                        <?php endif; ?>
                        updatePreview();
                    }
                </script>
                <select name="jenis_surat" id="jenis_surat_select" class="form-control" onchange="gantiNomorUrut(this)" required <?php echo $is_clone ? 'style="pointer-events:none; background:rgba(255,255,255,0.05); color:#888;"' : ''; ?>>
                    <option value="L" <?php echo $jenis_surat_val === 'L' ? 'selected' : ''; ?>>Surat Keluar (L)</option>
                    <option value="D" <?php echo $jenis_surat_val === 'D' ? 'selected' : ''; ?>>Surat Dalam (D)</option>
                </select>
                <?php if($is_clone): ?><input type="hidden" name="jenis_surat" value="<?php echo $jenis_surat_val; ?>"><?php endif; ?>
            </div>
            <div class="form-group" style="flex:1; min-width:200px;">
                <label>Kode Kegiatan (Misal: BEMCUP)</label>
                <input type="text" id="kode_kegiatan_input" name="kode_kegiatan" class="form-control" placeholder="BEMCUP" value="<?php echo htmlspecialchars($kode_kegiatan); ?>" required onkeyup="updatePreview()" <?php echo $is_clone ? 'readonly style="background:rgba(255,255,255,0.05); color:#888;"' : ''; ?>>
            </div>
        </div>

        <div style="background: rgba(0,0,0,0.2); border: 1px solid #333; padding: 15px; border-radius: 6px; margin-bottom: 20px; font-family: monospace;">
            <strong style="color: #aaa;">Preview Nomor Surat:</strong><br>
            <span id="preview_nomor" style="color:#4A90E2; font-size: 1.1rem; font-weight: bold; margin-top: 8px; display: inline-block;">
                <?php echo htmlspecialchars($next_urut_default); ?>/<?php echo htmlspecialchars($jenis_surat_val); ?>/<?php echo htmlspecialchars($kode_kegiatan); ?>/BEM/<?php echo $bulan_romawi; ?>/<?php echo $tahun; ?>
            </span>
        </div>
        
        <script>
            function updatePreview() {
                let urut = document.getElementById('nomor_urut_input').value || '[URUT]';
                let jenis = document.getElementById('jenis_surat_select').value;
                let kode = document.getElementById('kode_kegiatan_input').value || '[KODE]';
                document.getElementById('preview_nomor').innerText = urut + '/' + jenis + '/' + kode + '/BEM/<?php echo $bulan_romawi; ?>/<?php echo $tahun; ?>';
            }
            document.addEventListener("DOMContentLoaded", updatePreview);
            document.getElementById('nomor_urut_input').addEventListener('keyup', updatePreview);
        </script>

        <div style="display: flex; gap: 20px; margin-bottom: 15px; flex-wrap: wrap;">
            <div class="form-group" style="flex:1; min-width:300px;">
                <label>Perihal Surat</label>
                <?php if(!empty($list_perihal)): ?>
                <div class="tpl-picker" id="picker-perihal">
                    <div class="tpl-search-box">
                        <i class="fas fa-search tpl-search-icon"></i>
                        <input type="text" class="tpl-search-input" placeholder="Cari & Pilih Template Perihal..." onfocus="showTplResults('perihal')" onkeyup="filterTpl('perihal')">
                    </div>
                    <div class="tpl-results" id="results-perihal">
                        <?php foreach($list_perihal as $p): ?>
                        <div class="tpl-item" onclick="selectTpl('input_perihal', <?php echo htmlspecialchars(json_encode($p['isi_teks'])); ?>, 'perihal')">
                            <span class="tpl-item-label"><?php echo htmlspecialchars($p['label']); ?></span>
                            <span class="tpl-item-text"><?php echo htmlspecialchars($p['isi_teks']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                <input type="text" id="input_perihal" name="perihal" class="form-control" placeholder="Permohonan Support Kegiatan" value="<?php echo htmlspecialchars($edit_data['perihal']); ?>" required <?php echo $is_clone ? 'readonly style="background:rgba(255,255,255,0.05); color:#888;"' : ''; ?>>
            </div>
            <div class="form-group" style="flex:1; min-width:300px;">
                <label>Titimangsa & Tempat Tanggal Surat</label>
                <input type="text" name="tempat_tanggal" class="form-control" value="<?php echo htmlspecialchars($edit_data['tempat_tanggal']); ?>" <?php echo empty($list_perihal) ? 'style="margin-top:0px;"' : 'style="margin-top:42px;"'; ?> required <?php echo $is_clone ? 'readonly style="background:rgba(255,255,255,0.05); color:#888;"' : ''; ?>>
            </div>
        </div>
        
        <div class="form-group">
            <label>Status Tabel Arsip (Tanggal Kirim)</label>
            <input type="text" name="tanggal_dikirim" class="form-control" value="<?php echo htmlspecialchars((string)($edit_data['tanggal_dikirim'] ?? 'Belum Di kirim')); ?>" placeholder="Bisa dikosongkan atau diisi 'Belum Di kirim'" <?php echo $is_clone ? 'readonly style="background:rgba(255,255,255,0.05); color:#888;"' : ''; ?>>
        </div>

        <h3 style="border-bottom: 1px solid #333; padding-bottom: 10px; margin: 30px 0 20px; color:#4A90E2;">2. Tujuan & Isi Surat</h3>
        
        <div class="form-group" style="max-width:300px;">
            <label>Sapaan Tujuan (Opsional)</label>
            <select name="sapaan_tujuan" class="form-control">
                <option value="">-- Tanpa Sapaan --</option>
                <option value="Bapak" <?php echo ($edit_data['sapaan_tujuan'] ?? '') === 'Bapak' ? 'selected' : ''; ?>>Bapak</option>
                <option value="Ibu" <?php echo ($edit_data['sapaan_tujuan'] ?? '') === 'Ibu' ? 'selected' : ''; ?>>Ibu</option>
                <option value="Saudara" <?php echo ($edit_data['sapaan_tujuan'] ?? '') === 'Saudara' ? 'selected' : ''; ?>>Saudara</option>
                <option value="Saudari" <?php echo ($edit_data['sapaan_tujuan'] ?? '') === 'Saudari' ? 'selected' : ''; ?>>Saudari</option>
            </select>
        </div>

        <div class="form-group">
            <label>Kepada Yth (Tujuan)</label>
            <?php if(!empty($list_tujuan)): ?>
            <div class="tpl-picker" id="picker-tujuan">
                <div class="tpl-search-box">
                    <i class="fas fa-search tpl-search-icon"></i>
                    <input type="text" class="tpl-search-input" placeholder="Cari & Pilih Template Tujuan..." onfocus="showTplResults('tujuan')" onkeyup="filterTpl('tujuan')">
                </div>
                <div class="tpl-results" id="results-tujuan">
                    <?php foreach($list_tujuan as $t): ?>
                    <div class="tpl-item" onclick="selectTpl('textarea_tujuan', <?php echo htmlspecialchars(json_encode($t['isi_teks'])); ?>, 'tujuan')">
                        <span class="tpl-item-label"><?php echo htmlspecialchars($t['label']); ?></span>
                        <span class="tpl-item-text"><?php echo htmlspecialchars($t['isi_teks']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <textarea id="textarea_tujuan" name="tujuan" class="form-control" rows="4" oninput="syncParagraf()" required><?php echo htmlspecialchars($edit_data['tujuan']); ?></textarea>
            <small style="color:#aaa;">Isi nama &amp; jabatan penerima saja. Teks "Di Tempat" akan otomatis ditambahkan di cetakan surat.</small>
        </div>
        
        <script>
        // ======================================================
        // Tidak ada paragraf field — keduanya dibuat dinamis
        // ======================================================
        </script>
        
        <?php
        // Deteksi mode edit: jika tema_kegiatan ada tapi nama_kegiatan kosong → mode custom
        $mode_custom_default = !empty($edit_data['tema_kegiatan']) && empty($edit_data['nama_kegiatan']);
        $tpl_hidden  = $mode_custom_default ? 'display:none' : '';
        $cust_hidden = $mode_custom_default ? '' : 'display:none';
        ?>

        <div style="margin-bottom: 15px; padding: 18px; background: rgba(0,0,0,0.25); border-radius: 10px; border: 1px solid #2a2a2a;">

            <!-- Header bar -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                <div>
                    <strong style="color:#4A90E2; font-size:0.95rem;"><i class="fas fa-paragraph"></i> Paragraf Pembuka</strong>
                    <span id="mode-badge-template" style="<?php echo $mode_custom_default ? 'display:none' : ''; ?> margin-left:8px; font-size:0.72rem; background:rgba(74,144,226,0.15); color:#4A90E2; border:1px solid rgba(74,144,226,0.3); padding:2px 8px; border-radius:20px;">Mode Template</span>
                    <span id="mode-badge-custom"   style="<?php echo $mode_custom_default ? '' : 'display:none'; ?> margin-left:8px; font-size:0.72rem; background:rgba(255,165,0,0.15); color:#ffa500; border:1px solid rgba(255,165,0,0.3); padding:2px 8px; border-radius:20px;">Mode Custom</span>
                </div>
                <button type="button" id="toggle-mode-btn" onclick="toggleModeParagraf()"
                        style="display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border-radius:6px; border:1px solid #444; background:rgba(255,255,255,0.06); color:#ccc; font-size:0.8rem; cursor:pointer; transition: all 0.2s;"
                        onmouseover="this.style.background='rgba(255,255,255,0.12)'; this.style.color='#fff';"
                        onmouseout="this.style.background='rgba(255,255,255,0.06)'; this.style.color='#ccc';">
                    <i class="fas fa-exchange-alt"></i>
                    <span id="toggle-mode-label"><?php echo $mode_custom_default ? 'Ganti ke Mode Template' : 'Ganti ke Mode Custom'; ?></span>
                </button>
            </div>

            <!-- MODE TEMPLATE (default) -->
            <div id="blok-template" style="<?php echo $tpl_hidden; ?>">
                <div style="display:flex; gap:15px; flex-wrap:wrap;">
                    <div class="form-group" style="flex:1; min-width:180px; margin-bottom:0;">
                        <label>Nama Kegiatan</label>
                        <input type="text" id="input_nama_kegiatan" name="nama_kegiatan" class="form-control"
                               placeholder="BEM CUP" value="<?php echo htmlspecialchars($edit_data['nama_kegiatan'] ?? ''); ?>">
                    </div>
                    <div class="form-group" style="flex:2; min-width:260px; margin-bottom:0;">
                        <label>Tema Kegiatan</label>
                        <input type="text" id="input_tema" name="tema" class="form-control"
                               placeholder="Membangun Solidaritas dan Sportivitas..."
                               value="<?php echo htmlspecialchars($edit_data['tema'] ?? ''); ?>">
                    </div>
                </div>
                <!-- Removed hidden_tema_kegiatan overlapping element -->
                <div style="margin-top:10px; padding:10px; background:rgba(74,144,226,0.06); border-radius:6px; border-left:3px solid rgba(74,144,226,0.4);">
                    <small style="color:#666;">Preview hasil cetakan:</small><br>
                    <small style="color:#8BB9F0; font-style:italic;">
                        "Sehubungan akan diadakannya kegiatan <strong>[Nama Kegiatan]</strong> Tahun <?php echo date('Y'); ?>
                        dengan tema "<strong>[Tema]</strong>" yang akan dilaksanakan pada :"
                    </small>
                </div>
            </div>

            <!-- MODE CUSTOM (opsional) — rich text editor -->
            <div id="blok-custom" style="<?php echo $cust_hidden; ?>">
                <!-- Removed hidden_nama_kegiatan and hidden_tema overlapping elements -->
                <input type="hidden" id="input_tema_kegiatan_val" name="tema_kegiatan" value="<?php echo htmlspecialchars($edit_data['tema_kegiatan'] ?? ''); ?>">

                <!-- Toolbar -->
                <div style="display:flex; gap:4px; padding:6px 8px; background:#1a1a1a; border:1px solid #333; border-bottom:none; border-radius:6px 6px 0 0;">
                    <button type="button" onclick="execRTE('bold')"
                            title="Bold" style="width:30px; height:28px; border-radius:4px; border:1px solid #333; background:#222; color:#ccc; cursor:pointer; font-weight:bold; font-size:0.85rem; transition:background 0.15s;"
                            onmouseover="this.style.background='#333'" onmouseout="this.style.background='#222'">B</button>
                    <button type="button" onclick="execRTE('italic')"
                            title="Italic" style="width:30px; height:28px; border-radius:4px; border:1px solid #333; background:#222; color:#ccc; cursor:pointer; font-style:italic; font-size:0.85rem; transition:background 0.15s;"
                            onmouseover="this.style.background='#333'" onmouseout="this.style.background='#222'">I</button>
                    <button type="button" onclick="execRTE('underline')"
                            title="Underline" style="width:30px; height:28px; border-radius:4px; border:1px solid #333; background:#222; color:#ccc; cursor:pointer; text-decoration:underline; font-size:0.85rem; transition:background 0.15s;"
                            onmouseover="this.style.background='#333'" onmouseout="this.style.background='#222'">U</button>
                    <div style="width:1px; background:#333; margin:4px 4px;"></div>
                    <span style="font-size:0.72rem; color:#555; align-self:center; margin-left:4px;">Pilih teks lalu klik format</span>
                </div>

                <!-- Editable area -->
                <div id="rte-editor"
                     contenteditable="true"
                     style="min-height:90px; padding:12px 14px; background:#111; border:1px solid #333; border-radius:0 0 6px 6px; color:#ddd; font-size:0.88rem; line-height:1.7; outline:none; white-space:pre-wrap;"
                     onfocus="this.style.borderColor='#4A90E2'"
                     onblur="this.style.borderColor='#333'; syncRTE()"><?php echo $edit_data['tema_kegiatan'] ?? ''; ?></div>
                <small style="color:#555; margin-top:5px; display:block;"><i class="fas fa-info-circle"></i> Teks ini akan tampil di surat sebagai paragraf pembuka.</small>
            </div>
        </div>

        <script>
        // ============================================================
        // Toggle Mode Template / Custom
        // ============================================================
        function toggleModeParagraf() {
            const blokTpl   = document.getElementById('blok-template');
            const blokCust  = document.getElementById('blok-custom');
            const label     = document.getElementById('toggle-mode-label');
            const badgeTpl  = document.getElementById('mode-badge-template');
            const badgeCust = document.getElementById('mode-badge-custom');
            const isCustom  = blokCust.style.display !== 'none';

            if (isCustom) {
                blokTpl.style.display  = '';
                blokCust.style.display = 'none';
                label.textContent      = 'Ganti ke Mode Custom';
                badgeTpl.style.display = '';
                badgeCust.style.display= 'none';
                // Kosongkan custom
                document.getElementById('rte-editor').innerHTML = '';
                document.getElementById('input_tema_kegiatan_val').value = '';
                // (Hidden overrides dihapus)
            } else {
                blokTpl.style.display  = 'none';
                blokCust.style.display = '';
                label.textContent      = 'Ganti ke Mode Template';
                badgeTpl.style.display = 'none';
                badgeCust.style.display= '';
                // Kosongkan template
                document.getElementById('input_nama_kegiatan').value = '';
                document.getElementById('input_tema').value = '';
                // (Hidden override dihapus)
                document.getElementById('rte-editor').focus();
            }
        }

        // ============================================================
        // Rich Text Editor helpers
        // ============================================================
        function execRTE(cmd) {
            document.getElementById('rte-editor').focus();
            document.execCommand(cmd, false, null);
            syncRTE();
        }

        function syncRTE() {
            // Ambil HTML dari editor, strip tag berbahaya, simpan ke hidden input
            const html = document.getElementById('rte-editor').innerHTML;
            document.getElementById('input_tema_kegiatan_val').value = html;
        }

        // Sync sebelum form disubmit
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            if (form) form.addEventListener('submit', syncRTE);
        });
        </script>


        <style>
        /* ===== WAKTU PELAKSANAAN ===== */
        .wakpel-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }
        @media (max-width: 700px) { .wakpel-grid { grid-template-columns: 1fr; } }

        .wakpel-card {
            background: rgba(0,0,0,0.25);
            border: 1px solid #252525;
            border-radius: 10px;
            padding: 16px;
        }
        .wakpel-card-label {
            font-size: 0.78rem;
            color: #5a8fc4;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 600;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Date inputs */
        .date-range-wrap {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 6px;
            align-items: center;
        }
        .date-field label { font-size: 0.7rem; color: #555; display: block; margin-bottom: 4px; }
        .date-field input[type="date"] {
            width: 100%;
            background: #111;
            border: 1px solid #2a2a2a;
            border-radius: 8px;
            color: #ccc;
            padding: 9px 10px;
            font-size: 0.85rem;
            outline: none;
            transition: border-color 0.2s;
            cursor: pointer;
            color-scheme: dark;
        }
        .date-field input[type="date"]:focus,
        .date-field input[type="date"]:hover { border-color: #4A90E2; }
        .date-sep { color: #444; font-size: 0.8rem; text-align: center; padding-top: 18px; white-space: nowrap; }

        /* Preview bar */
        .preview-bar {
            margin-top: 12px;
            padding: 8px 12px;
            background: rgba(74,144,226,0.06);
            border-radius: 6px;
            border-left: 3px solid rgba(74,144,226,0.3);
            font-size: 0.78rem;
            color: #6a9fd4;
            font-style: italic;
            min-height: 34px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* ===== DRUM WHEEL ===== */
        .drum-group {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .drum-group-sep { color: #555; font-size: 0.75rem; padding: 0 4px; }
        .drum-groups-wrap {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }
        .drum-time-label {
            font-size: 0.7rem;
            color: #555;
            text-align: center;
            margin-bottom: 5px;
        }
        .drum-col {
            width: 56px;
            height: 168px;
            overflow: hidden;
            position: relative;
            border-radius: 10px;
            background: #0d0d0d;
            border: 1px solid #222;
            cursor: ns-resize;
            transition: border-color 0.2s;
        }
        .drum-col:hover { border-color: #3a6a9a; }
        .drum-col.active { border-color: #4A90E2; }
        .drum-col::before, .drum-col::after {
            content: '';
            position: absolute;
            left: 0; right: 0;
            height: 60px;
            z-index: 2;
            pointer-events: none;
        }
        .drum-col::before { top: 0;    background: linear-gradient(to bottom, #0d0d0d 30%, transparent); }
        .drum-col::after  { bottom: 0; background: linear-gradient(to top,   #0d0d0d 30%, transparent); }

        .drum-highlight {
            position: absolute;
            top: 50%; transform: translateY(-50%);
            left: 4px; right: 4px;
            height: 40px;
            border-radius: 6px;
            border-top: 1px solid rgba(74,144,226,0.6);
            border-bottom: 1px solid rgba(74,144,226,0.6);
            background: rgba(74,144,226,0.1);
            pointer-events: none;
            z-index: 1;
        }
        .drum-inner {
            position: absolute;
            width: 100%;
            transition: transform 0.18s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        .drum-item {
            height: 40px;
            line-height: 40px;
            text-align: center;
            font-size: 0.95rem;
            color: #444;
            font-family: 'Courier New', monospace;
            font-weight: 500;
            user-select: none;
            transition: color 0.12s, font-size 0.12s, font-weight 0.12s, text-shadow 0.12s;
        }
        .drum-item.sel   { color: #ffffff; font-size: 1.4rem; font-weight: 700; text-shadow: 0 0 8px rgba(255,255,255,0.4); }
        .drum-item.near1 { color: #8ba6c1; font-size: 1.05rem; font-weight: 600; }
        .drum-item.near2 { color: #506478; font-size: 0.9rem; }


        .drum-colon {
            color: #4A90E2;
            font-size: 1.3rem;
            font-weight: 700;
            padding-bottom: 2px;
            flex-shrink: 0;
        }
        .drum-arrow {
            background: none;
            border: none;
            color: #3a4a5a;
            cursor: pointer;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 4px;
            display: block;
            width: 100%;
            text-align: center;
            transition: color 0.15s, background 0.15s;
        }
        .drum-arrow:hover { color: #fff; background: rgba(74,144,226,0.2); }

        /* Toggle switch */
        .toggle-switch-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            background: rgba(0,0,0,0.2);
            border-radius: 8px;
            border: 1px solid #1e1e1e;
            cursor: pointer;
            transition: border-color 0.2s;
        }
        .toggle-switch-wrap:hover { border-color: #333; }
        .toggle-switch {
            position: relative; width: 36px; height: 20px;
            background: #222; border-radius: 10px;
            transition: background 0.25s; flex-shrink: 0;
            border: 1px solid #333;
        }
        .toggle-switch.on { background: #2563a8; border-color: #4A90E2; }
        .toggle-knob {
            position: absolute; top: 2px; left: 2px;
            width: 14px; height: 14px;
            background: #555; border-radius: 50%;
            transition: transform 0.25s, background 0.25s;
        }
        .toggle-switch.on .toggle-knob { transform: translateX(16px); background: #fff; }
        .toggle-label { font-size: 0.8rem; color: #666; transition: color 0.2s; }
        .toggle-switch-wrap.toggle-on .toggle-label { color: #7ab3e0; }

        /* Template Picker Searchable */
        .tpl-picker { position: relative; margin-bottom: 12px; }
        .tpl-search-box { position: relative; }
        .tpl-search-input { 
            width: 100%; padding: 10px 15px 10px 35px; background: rgba(0,0,0,0.3); 
            border: 1px solid #444; border-radius: 8px; color: white; font-size: 0.85rem; outline: none;
            transition: all 0.2s;
        }
        .tpl-search-input:focus { border-color: #4A90E2; background: rgba(0,0,0,0.5); }
        .tpl-search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #666; font-size: 0.8rem; }
        
        .tpl-results { 
            position: absolute; top: 100%; left: 0; right: 0; z-index: 1000; 
            background: #121822; border: 1px solid #333; border-top: none; 
            max-height: 250px; overflow-y: auto; display: none; border-radius: 0 0 10px 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.6);
        }
        .tpl-item { padding: 10px 15px; cursor: pointer; border-bottom: 1px solid #1e2633; transition: all 0.2s; }
        .tpl-item:hover { background: rgba(74, 144, 226, 0.15); }
        .tpl-item-label { display: block; font-weight: 700; color: #8BB9F0; font-size: 0.82rem; margin-bottom: 3px; }
        .tpl-item-text { display: block; font-size: 0.72rem; color: #888; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tpl-empty { padding: 15px; text-align: center; color: #555; font-size: 0.8rem; }
        </style>

        <div style="background: rgba(74,144,226,.04); padding: 18px; border-radius: 12px; border: 1px solid #1a2a3a; margin-bottom: 15px;">
            <div style="color:#4A90E2; font-size:0.85rem; font-weight:600; margin-bottom:16px; display:flex; align-items:center; gap:8px;">
                <i class="fas fa-calendar-alt"></i> Waktu Pelaksanaan
            </div>

            <div class="wakpel-grid">

                <!-- ===== TANGGAL RANGE ===== -->
                <div class="wakpel-card">
                    <div class="wakpel-card-label"><i class="fas fa-calendar-day"></i> Hari &amp; Tanggal</div>
                    <div class="date-range-wrap">
                        <div class="date-field">
                            <label>Mulai</label>
                            <input type="date" id="tgl-mulai" onchange="formatTanggalRange()">
                        </div>
                        <div class="date-sep">—</div>
                        <div class="date-field">
                            <label>Selesai <span style="color:#3a3a3a;">(opsional)</span></label>
                            <input type="date" id="tgl-selesai" onchange="formatTanggalRange()">
                        </div>
                    </div>
                    <input type="hidden" id="out-tanggal" name="pelaksanaan_hari_tanggal"
                           value="<?php echo htmlspecialchars($edit_data['pelaksanaan_hari_tanggal']); ?>">
                    <div class="preview-bar">
                        <i class="fas fa-eye" style="color:#3a6a9a; flex-shrink:0;"></i>
                        <span id="preview-tanggal"><?php echo htmlspecialchars($edit_data['pelaksanaan_hari_tanggal']); ?></span>
                    </div>
                </div>

                <!-- ===== WAKTU DRUM ===== -->
                <div class="wakpel-card">
                    <div class="wakpel-card-label"><i class="fas fa-clock"></i> Waktu</div>
                    <input type="hidden" id="out-waktu" name="pelaksanaan_waktu"
                           value="<?php echo htmlspecialchars($edit_data['pelaksanaan_waktu']); ?>">

                    <div class="drum-groups-wrap">
                        <!-- Drum Mulai -->
                        <div>
                            <div class="drum-time-label">Mulai</div>
                            <div class="drum-group">
                                <div>
                                    <button type="button" class="drum-arrow" onclick="drumHS.scrollBy(-1)">▲</button>
                                    <div class="drum-col" id="drum-h-start"></div>
                                    <button type="button" class="drum-arrow" onclick="drumHS.scrollBy(1)">▼</button>
                                </div>
                                <span class="drum-colon">:</span>
                                <div>
                                    <button type="button" class="drum-arrow" onclick="drumMS.scrollBy(-1)">▲</button>
                                    <div class="drum-col" id="drum-m-start"></div>
                                    <button type="button" class="drum-arrow" onclick="drumMS.scrollBy(1)">▼</button>
                                </div>
                            </div>
                        </div>

                        <div style="color:#2a3a4a; font-size:0.8rem; padding-top:24px; flex-shrink:0;">s.d</div>

                        <!-- Drum Selesai -->
                        <div id="drum-end-wrap">
                            <div class="drum-time-label">Selesai</div>
                            <div class="drum-group">
                                <div>
                                    <button type="button" class="drum-arrow" onclick="drumHE.scrollBy(-1)">▲</button>
                                    <div class="drum-col" id="drum-h-end"></div>
                                    <button type="button" class="drum-arrow" onclick="drumHE.scrollBy(1)">▼</button>
                                </div>
                                <span class="drum-colon">:</span>
                                <div>
                                    <button type="button" class="drum-arrow" onclick="drumME.scrollBy(-1)">▲</button>
                                    <div class="drum-col" id="drum-m-end"></div>
                                    <button type="button" class="drum-arrow" onclick="drumME.scrollBy(1)">▼</button>
                                </div>
                            </div>
                        </div>

                        <!-- Toggle Selesai -->
                        <div style="padding-top:28px;">
                            <div class="toggle-switch-wrap" id="toggle-selesai-wrap" onclick="doToggleSelesai()">
                                <div class="toggle-switch" id="ts-switch"><div class="toggle-knob"></div></div>
                                <span class="toggle-label" id="ts-label">Tanpa waktu akhir</span>
                            </div>
                        </div>
                    </div>

                    <div class="preview-bar" style="margin-top:14px;">
                        <i class="fas fa-eye" style="color:#3a6a9a; flex-shrink:0;"></i>
                        <span id="preview-waktu"><?php echo htmlspecialchars($edit_data['pelaksanaan_waktu']); ?></span>
                    </div>
                </div>
            </div>

            <!-- TEMPAT — full width bawah -->
            <div class="form-group" style="margin-top:14px; margin-bottom:0;">
                <label style="font-size:0.82rem; color:#aaa; margin-bottom:6px; display:flex; align-items:center; gap:6px;">
                    <i class="fas fa-map-marker-alt" style="color:#5a8fc4;"></i> Tempat Pelaksanaan
                </label>
                <input type="text" name="pelaksanaan_tempat" class="form-control"
                       placeholder="Kampus INSTBUNAS Majalengka, GOR Futsal..."
                       value="<?php echo htmlspecialchars($edit_data['pelaksanaan_tempat']); ?>" required>
            </div>
        </div>

        <script>
        // ================================================================
        // DRUM PICKER CLASS
        // ================================================================
        class DrumPicker {
            constructor(elId, values, initVal, onChange) {
                this.el       = document.getElementById(elId);
                this.values   = values;
                this.idx      = Math.max(0, values.indexOf(initVal));
                this.onChange = onChange;
                this.ITEM     = 40;
                this._build();
                this._bind();
                this._render(false);
            }
            _build() {
                const hl = document.createElement('div');
                hl.className = 'drum-highlight';
                this.el.appendChild(hl);

                this.inner = document.createElement('div');
                this.inner.className = 'drum-inner';
                this.inner.style.transition = 'none'; // no anim on build

                const pad = () => { const d=document.createElement('div'); d.className='drum-item'; return d; };
                [0,1,2].forEach(() => this.inner.appendChild(pad())); // 3 pads top (center=3rd item visible)

                this.values.forEach((v, i) => {
                    const d = document.createElement('div');
                    d.className = 'drum-item'; d.dataset.i = i; d.textContent = v;
                    this.inner.appendChild(d);
                });

                [0,1,2].forEach(() => this.inner.appendChild(pad())); // 3 pads bottom
                this.el.appendChild(this.inner);
            }
            _render(animate = true) {
                // Height of drum = 168, center = 84
                // Item height = 40, half-height = 20
                // Pads = 3 items = 120px top offset
                // Target item top relative to inner = 120 + idx * 40
                // Translation offset = Center(84) - Half(20) - TargetTop(120 + idx*40)
                const offset = -56 - this.idx * this.ITEM;

                this.inner.style.transition = animate ? 'transform 0.18s cubic-bezier(0.25,0.46,0.45,0.94)' : 'none';
                this.inner.style.transform  = `translateY(${offset}px)`;

                this.inner.querySelectorAll('[data-i]').forEach(el => {
                    const diff = Math.abs(parseInt(el.dataset.i) - this.idx);
                    // To handle wrapping style mapping smoothly, calculate shortest distance in modulo space:
                    const len = this.values.length;
                    const wrapDiff = Math.min(diff, len - diff);
                    
                    el.className = 'drum-item' + (wrapDiff===0?' sel':wrapDiff===1?' near1':wrapDiff===2?' near2':'');
                });
                
                // Fix: delay onChange execution to avoid accessing undefined obj during construct
                if (this.onChange) setTimeout(() => this.onChange(this.values[this.idx]), 0);
            }
            scrollBy(delta) {
                const oldIdx = this.idx;
                const len = this.values.length;
                
                // Loop the index
                this.idx = (this.idx + delta) % len;
                if (this.idx < 0) this.idx += len;

                // Disable animation if wrapped around so it doesn't slide across the whole list
                const wrapped = Math.abs(this.idx - oldIdx) > 1;

                // Flash active border
                this.el.classList.add('active');
                clearTimeout(this._at);
                this._at = setTimeout(() => this.el.classList.remove('active'), 300);
                this._render(!wrapped);
            }
            _bind() {
                this.el.addEventListener('wheel', e => {
                    e.preventDefault(); this.scrollBy(e.deltaY > 0 ? 1 : -1);
                }, { passive: false });
                let ty = 0, moved = false;
                this.el.addEventListener('touchstart', e => { ty = e.touches[0].clientY; moved = false; });
                this.el.addEventListener('touchmove', e => {
                    e.preventDefault();
                    const d = ty - e.touches[0].clientY;
                    if (Math.abs(d) > 14) { this.scrollBy(d > 0 ? 1 : -1); ty = e.touches[0].clientY; moved = true; }
                }, { passive: false });
                this.inner.addEventListener('click', e => {
                    if (moved) return;
                    const item = e.target.closest('[data-i]');
                    if (item) { this.idx = parseInt(item.dataset.i); this._render(true); }
                });
            }
            val() { return this.values[this.idx]; }
        }

        // ================================================================
        // INIT
        // ================================================================
        const hours = Array.from({length:24}, (_,i) => String(i).padStart(2,'0'));
        const mins  = Array.from({length:60}, (_,i) => String(i).padStart(2,'0'));

        const existingWaktu = document.getElementById('out-waktu').value || '';
        const wParts  = existingWaktu.split(' s.d ');
        const startT  = (wParts[0] || '08.00').replace('.', ':').split(':');
        const isSelesai = !wParts[1] || wParts[1] === 'Selesai';
        const endT    = !isSelesai ? wParts[1].replace('.', ':').split(':') : null;

        let drumHS, drumMS, drumHE, drumME;
        let _selesaiMode = isSelesai;

        document.addEventListener('DOMContentLoaded', () => {
            drumHS = new DrumPicker('drum-h-start', hours, startT[0]||'08', updateWaktu);
            drumMS = new DrumPicker('drum-m-start', mins,  startT[1]||'00', updateWaktu);
            drumHE = new DrumPicker('drum-h-end',   hours, endT?endT[0]:'17', updateWaktu);
            drumME = new DrumPicker('drum-m-end',   mins,  endT?endT[1]:'00', updateWaktu);

            if (isSelesai) applyToggleSelesai(true);

            const existingTgl = document.getElementById('out-tanggal').value;
            if (existingTgl) document.getElementById('preview-tanggal').textContent = existingTgl;
        });

        function updateWaktu() {
            // Cancel if any drum isn't fully created yet to avoid undefined errors
            if (!drumHS || !drumMS || !drumHE || !drumME) return;
            const start  = drumHS.val() + '.' + drumMS.val();
            const end    = _selesaiMode ? 'Selesai' : drumHE.val() + '.' + drumME.val();
            const result = start + ' s.d ' + end;
            document.getElementById('out-waktu').value   = result;
            document.getElementById('preview-waktu').textContent = result;
        }

        function doToggleSelesai() {
            _selesaiMode = !_selesaiMode;
            applyToggleSelesai(_selesaiMode);
        }

        function applyToggleSelesai(on) {
            _selesaiMode = on;
            const sw   = document.getElementById('ts-switch');
            const wrap = document.getElementById('toggle-selesai-wrap');
            const lbl  = document.getElementById('ts-label');
            const end  = document.getElementById('drum-end-wrap');
            sw.classList.toggle('on', on);
            wrap.classList.toggle('toggle-on', on);
            lbl.textContent  = on ? 'Tanpa waktu akhir' : 'Dengan waktu akhir';
            end.style.opacity       = on ? '0.2' : '1';
            end.style.pointerEvents = on ? 'none' : '';
            updateWaktu();
        }

        // ================================================================
        // DATE RANGE → Indonesian format
        // ================================================================
        const HARI_ID  = ['Minggu','Senin','Selasa','Rabu','Kamis',"Jum'at",'Sabtu'];
        const BULAN_ID = ['Januari','Februari','Maret','April','Mei','Juni',
                          'Juli','Agustus','September','Oktober','November','Desember'];

        function formatTanggalRange() {
            const mulai   = document.getElementById('tgl-mulai').value;
            const selesai = document.getElementById('tgl-selesai').value;
            if (!mulai) { document.getElementById('preview-tanggal').textContent = '—belum dipilih—'; return; }

            const d1 = new Date(mulai + 'T00:00:00');
            let result = '';

            if (!selesai || selesai === mulai) {
                result = HARI_ID[d1.getDay()] + ', ' + d1.getDate() + ' ' + BULAN_ID[d1.getMonth()] + ' ' + d1.getFullYear();
            } else {
                const d2 = new Date(selesai + 'T00:00:00');
                const hari = HARI_ID[d1.getDay()] === HARI_ID[d2.getDay()]
                    ? HARI_ID[d1.getDay()]
                    : HARI_ID[d1.getDay()] + '-' + HARI_ID[d2.getDay()];
                const bln1 = BULAN_ID[d1.getMonth()], bln2 = BULAN_ID[d2.getMonth()];
                const tgl  = bln1 === bln2 && d1.getFullYear() === d2.getFullYear()
                    ? d1.getDate() + '-' + d2.getDate() + ' ' + bln1 + ' ' + d1.getFullYear()
                    : d1.getDate() + ' ' + bln1 + ' ' + d1.getFullYear() + ' – ' + d2.getDate() + ' ' + bln2 + ' ' + d2.getFullYear();
                result = hari + ', ' + tgl;
            }

            document.getElementById('out-tanggal').value = result;
            document.getElementById('preview-tanggal').textContent = result;
        }
        </script>


        <div class="form-group">
            <label>Konteks Tambahan (Akhiran Kalimat) <small style="color:#666;">(Opsional — akan sepenuhnya menggantikan akhiran kalimat permohonan default)</small></label>
            <input type="text" name="konteks" class="form-control" 
                   placeholder="cth: untuk mengirimkan 2 orang delegasi pada acara tersebut"
                   value="<?php echo htmlspecialchars($edit_data['konteks'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label>Tembusan (Opsional)</label>
            <textarea name="tembusan" class="form-control" rows="2" placeholder="1. Bagian Umum..."><?php echo htmlspecialchars($edit_data['tembusan'] ?? ''); ?></textarea>
        </div>
        <div class="form-group">
            <label>Lampiran Berkas (Opsional)</label>
            <input type="file" name="lampiran_surat[]" class="form-control" accept=".pdf" multiple>
            <small style="color:#aaa; display:block; margin-top:5px;">Pilih satu atau lebih file PDF sekaligus. Indikator "Lampiran" di surat akan berubah seiring jumlah berkas.</small>
            
            <?php if($is_edit && !empty($konten['lampiran_files'])): ?>
                <div style="background: rgba(42, 53, 69, 0.5); padding: 10px; border-radius: 6px; margin-top: 10px; border: 1px solid #3a4a5a;">
                    <strong style="color: #4A90E2; font-size: 0.85rem;"><i class="fas fa-file-pdf"></i> Lampiran Tersimpan Saat Ini (<?php echo count($konten['lampiran_files']); ?> File)</strong>
                    <ul style="margin: 5px 0 0 20px; font-size: 0.85rem; color: #ccc;">
                    <?php foreach($konten['lampiran_files'] as $l): ?>
                        <li><a href="<?php echo baseUrl($l); ?>" target="_blank" style="color: #8BB9F0;"><?php echo htmlspecialchars(basename($l)); ?></a></li>
                    <?php endforeach; ?>
                    </ul>
                    <small style="color:#f39c12; margin-top: 5px; display:inline-block;">*Mengunggah berkas baru akan secara otomatis menambahkan ke lampiran yang sudah ada.</small>
                </div>
            <?php endif; ?>
        </div>

        <h3 style="border-bottom: 1px solid #333; padding-bottom: 10px; margin: 30px 0 20px; color:#4A90E2;">3. Tanda Tangan Kepanitiaan</h3>
        
        <div style="display: flex; gap: 20px; margin-bottom: 15px; flex-wrap: wrap;">
            <div class="form-group" style="flex:1; min-width:300px;">
                <label>Nama Ketua Pelaksana</label>
                <input type="text" name="panitia_ketua" class="form-control" placeholder="ADE LIA AGUSTINA" value="<?php echo htmlspecialchars($edit_data['panitia_ketua']); ?>" required>
                
                <h4 style="margin-bottom: 5px; margin-top: 15px; color:#8BB9F0; font-size: 0.85rem;"><i class="fas fa-pen-nib"></i> Mode Tanda Tangan</h4>
                <select id="ttd_mode_ketua" class="form-control" style="margin-bottom:8px;" onchange="changeTtdMode('ketua')">
                    <option value="draw">✍️ Gambar Manual (Layar)</option>
                    <option value="upload">📁 Upload Gambar (.PNG Transparan)</option>
                    <option value="none" selected>🚫 Kosong (Tanda Tangan Asli Nanti)</option>
                </select>

                <div id="wrap_canvas_ketua" style="display:none;">
                    <div style="border: 1px solid #2a3545; border-radius: 8px; overflow: hidden; background: #fff; width: 300px; max-width: 100%; margin: 0 auto;">
                        <canvas id="pad_ketua" width="300" height="150" style="cursor:crosshair; touch-action:none; display:block; max-width: 100%; height: auto;"></canvas>
                    </div>
                    <div style="text-align: right; margin-top: 10px; width: 300px; max-width: 100%; margin-left: auto; margin-right: auto;">
                        <button type="button" onclick="clearPad('ketua')" style="background:#2a1111; border: 1px solid #E74C3C; color:#ff6b6b; padding: 6px 12px; border-radius: 5px; font-size:0.85rem; cursor:pointer; transition: all 0.2s;"><i class="fas fa-trash-alt"></i> Bersihkan Coretan</button>
                    </div>
                </div>

                <div id="wrap_upload_ketua" style="display:none;">
                    <input type="file" id="upload_ketua" class="form-control" accept="image/png, image/jpeg, image/webp" onchange="handleTtdUpload('ketua', this)">
                    <small style="color:#aaa;">File ini akan disisipkan ke cetak surat dalam bentuk digital (tanpa disimpan di penyimpanan server).</small>
                </div>

                <div id="preview_ttd_ketua" style="margin-top:10px; <?php echo !empty($edit_data['panitia_ketua_ttd']) ? '' : 'display:none;'; ?>">
                    <small style="color:#27ae60; display:block; margin-bottom:5px;"><i class="fas fa-check-circle"></i> Tanda tangan tersimpan:</small>
                    <img id="img_preview_ketua" src="<?php echo htmlspecialchars($edit_data['panitia_ketua_ttd'] ?? ''); ?>" style="max-height:60px; border:1px solid #333; background:#fff; padding:2px; border-radius:4px;">
                </div>

                <input type="hidden" name="panitia_ketua_ttd" id="ttd_ketua_val" value="<?php echo htmlspecialchars($edit_data['panitia_ketua_ttd'] ?? ''); ?>">
            </div>
            
            <div class="form-group" style="flex:1; min-width:300px;">
                <label>Nama Sekretaris Pelaksana</label>
                <input type="text" name="panitia_sekretaris" class="form-control" placeholder="ANGGI DIYARTI" value="<?php echo htmlspecialchars($edit_data['panitia_sekretaris']); ?>" required>
                
                <h4 style="margin-bottom: 5px; margin-top: 15px; color:#8BB9F0; font-size: 0.85rem;"><i class="fas fa-pen-nib"></i> Mode Tanda Tangan</h4>
                <select id="ttd_mode_sekretaris" class="form-control" style="margin-bottom:8px;" onchange="changeTtdMode('sekretaris')">
                    <option value="draw">✍️ Gambar Manual (Layar)</option>
                    <option value="upload">📁 Upload Gambar (.PNG Transparan)</option>
                    <option value="none" selected>🚫 Kosong (Tanda Tangan Asli Nanti)</option>
                </select>

                <div id="wrap_canvas_sekretaris" style="display:none;">
                    <div style="border: 1px solid #2a3545; border-radius: 8px; overflow: hidden; background: #fff; width: 300px; max-width: 100%; margin: 0 auto;">
                        <canvas id="pad_sekretaris" width="300" height="150" style="cursor:crosshair; touch-action:none; display:block; max-width: 100%; height: auto;"></canvas>
                    </div>
                    <div style="text-align: right; margin-top: 10px; width: 300px; max-width: 100%; margin-left: auto; margin-right: auto;">
                        <button type="button" onclick="clearPad('sekretaris')" style="background:#2a1111; border: 1px solid #E74C3C; color:#ff6b6b; padding: 6px 12px; border-radius: 5px; font-size:0.85rem; cursor:pointer; transition: all 0.2s;"><i class="fas fa-trash-alt"></i> Bersihkan Coretan</button>
                    </div>
                </div>

                <div id="wrap_upload_sekretaris" style="display:none;">
                    <input type="file" id="upload_sekretaris" class="form-control" accept="image/png, image/jpeg, image/webp" onchange="handleTtdUpload('sekretaris', this)">
                    <small style="color:#aaa;">File ini akan disisipkan ke cetak surat dalam bentuk digital (tanpa disimpan di penyimpanan server).</small>
                </div>

                <div id="preview_ttd_sekretaris" style="margin-top:10px; <?php echo !empty($edit_data['panitia_sekretaris_ttd']) ? '' : 'display:none;'; ?>">
                    <small style="color:#27ae60; display:block; margin-bottom:5px;"><i class="fas fa-check-circle"></i> Tanda tangan tersimpan:</small>
                    <img id="img_preview_sekretaris" src="<?php echo htmlspecialchars($edit_data['panitia_sekretaris_ttd'] ?? ''); ?>" style="max-height:60px; border:1px solid #333; background:#fff; padding:2px; border-radius:4px;">
                </div>

                <input type="hidden" name="panitia_sekretaris_ttd" id="ttd_sekretaris_val" value="<?php echo htmlspecialchars($edit_data['panitia_sekretaris_ttd'] ?? ''); ?>">
            </div>
        </div>

        <script>
        const pads = {};

        function resizeCanvas(role) {
            if (!pads[role]) return;
            const canvas = pads[role].canvas;
            const ctx = pads[role].ctx;
            
            // Render ulang resolusi dinonaktifkan agar kanvas tetap fix 300x150 (Aspect Ratio ideal TTD)
            // Restore drawing context states
            ctx.lineWidth = 2.5;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#02183b';
        }
        
        function changeTtdMode(role) {
            const mode = document.getElementById('ttd_mode_' + role).value;
            const wrapCanvas = document.getElementById('wrap_canvas_' + role);
            
            wrapCanvas.style.display = mode === 'draw' ? 'block' : 'none';
            document.getElementById('wrap_upload_' + role).style.display = mode === 'upload' ? 'block' : 'none';

            const hidVal = document.getElementById('ttd_' + role + '_val');
            
            if (mode === 'none') {
                hidVal.value = ''; 
            } else if (mode === 'draw') {
                resizeCanvas(role); // Fix scale
                // REMOVED: hidVal.value = pads[role].canvas.toDataURL('image/png');
                // Alasan: Menyebabkan data TTD yang sudah ada tertimpa kanvas kosong saat inisialisasi.
                // Update nilai kini hanya dilakukan saat user selesai menggores (event end).
            } else if (mode === 'upload') {
                const fileIn = document.getElementById('upload_' + role);
                if (fileIn.files.length > 0) handleTtdUpload(role, fileIn);
            }
        }

        function handleTtdUpload(role, input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const res = e.target.result;
                    document.getElementById('ttd_' + role + '_val').value = res;
                    // Update preview
                    document.getElementById('preview_ttd_' + role).style.display = 'block';
                    document.getElementById('img_preview_' + role).src = res;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function initSignaturePad(role) {
            const canvas = document.getElementById('pad_' + role);
            if(!canvas) return;
            const ctx = canvas.getContext('2d');
            let isDrawing = false;
            
            // Fix dimension overrides are disabled. We rely on the raw HTML width/height.
            // canvas.width = 300;
            
            pads[role] = { canvas, ctx };

            // Load existing OR set to none
            const hidValEl = document.getElementById('ttd_' + role + '_val');
            const existingVal = hidValEl ? hidValEl.value : '';
            const sel = document.getElementById('ttd_mode_' + role);
            
            if(existingVal && existingVal.length > 100) { // Cek panjang base64
                sel.value = 'draw';
                const wrap_canvas = document.getElementById('wrap_canvas_' + role);
                wrap_canvas.style.display = 'block';
                resizeCanvas(role);
                const img = new Image();
                img.onload = () => ctx.drawImage(img, 0, 0);
                img.src = existingVal;
            } else {
                sel.value = 'none';
                changeTtdMode(role);
            }

            ctx.lineWidth = 2.5;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#02183b'; // Tinta biru dongker/hitam

            function getPos(e) {
                const r = canvas.getBoundingClientRect();
                const scaleX = canvas.width / r.width;
                const scaleY = canvas.height / r.height;
                
                let cx = e.touches && e.touches.length > 0 ? e.touches[0].clientX : e.clientX;
                let cy = e.touches && e.touches.length > 0 ? e.touches[0].clientY : e.clientY;
                
                return { 
                    x: (cx - r.left) * scaleX, 
                    y: (cy - r.top) * scaleY 
                };
            }

            function start(e) {
                e.preventDefault();
                isDrawing = true;
                const pos = getPos(e);
                ctx.beginPath();
                ctx.moveTo(pos.x, pos.y);
            }

            function draw(e) {
                if (!isDrawing) return;
                e.preventDefault();
                const pos = getPos(e);
                ctx.lineTo(pos.x, pos.y);
                ctx.stroke();
            }

            function end() {
                if (isDrawing) {
                    isDrawing = false;
                    const dataUrl = canvas.toDataURL('image/png');
                    document.getElementById('ttd_' + role + '_val').value = dataUrl;
                    // Update preview
                    document.getElementById('preview_ttd_' + role).style.display = 'block';
                    document.getElementById('img_preview_' + role).src = dataUrl;
                }
            }

            canvas.addEventListener('mousedown', start, {passive:false});
            canvas.addEventListener('mousemove', draw, {passive:false});
            window.addEventListener('mouseup', end);

            canvas.addEventListener('touchstart', start, {passive:false});
            canvas.addEventListener('touchmove', draw, {passive:false});
            window.addEventListener('touchend', end);
        }

        function clearPad(role) {
            if(pads[role]) {
                pads[role].ctx.clearRect(0, 0, pads[role].canvas.width, pads[role].canvas.height);
                document.getElementById('ttd_' + role + '_val').value = '';
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            initSignaturePad('ketua');
            initSignaturePad('sekretaris');
        });
        </script>
        <h3 style="border-bottom: 1px solid #333; padding-bottom: 10px; margin: 40px 0 20px; color:#4A90E2;">4. Pengaturan Tanda Tangan & Stempel Instansi</h3>
        <div style="display: flex; gap: 20px; margin-bottom: 15px; flex-wrap: wrap;">
            <div class="form-group" style="flex:1; min-width:300px;">
                <label>Sertakan Tanda Tangan WAREK III?</label>
                <select name="use_ttd_warek" class="form-control">
                    <option value="1" <?php echo ($edit_data['use_ttd_warek'] ?? '1') == '1' ? 'selected' : ''; ?>>✅ Ya, gunakan TTD Digital</option>
                    <option value="0" <?php echo ($edit_data['use_ttd_warek'] ?? '1') == '0' ? 'selected' : ''; ?>>🚫 Tidak (Biarkan kosong untuk TTD fisik)</option>
                </select>
                <small style="color:#aaa;">Gambar akan diambil dari Pengaturan Surat.</small>
            </div>
            <div class="form-group" style="flex:1; min-width:300px;">
                <label>Sertakan Tanda Tangan PRESMA BEM?</label>
                <select name="use_ttd_presma" class="form-control">
                    <option value="1" <?php echo ($edit_data['use_ttd_presma'] ?? '1') == '1' ? 'selected' : ''; ?>>✅ Ya, gunakan TTD Digital</option>
                    <option value="0" <?php echo ($edit_data['use_ttd_presma'] ?? '1') == '0' ? 'selected' : ''; ?>>🚫 Tidak (Biarkan kosong untuk TTD fisik)</option>
                </select>
                <small style="color:#aaa;">Gambar akan diambil dari Pengaturan Surat.</small>
            </div>
        </div>

        <div style="display: flex; gap: 20px; margin-bottom: 15px; flex-wrap: wrap;">
            <div class="form-group" style="flex:1; min-width:200px;">
                <label>Sertakan Cap PANITIA?</label>
                <select name="use_cap_panitia" class="form-control">
                    <option value="1" <?php echo ($edit_data['use_cap_panitia'] ?? '1') == '1' ? 'selected' : ''; ?>>✅ Ya</option>
                    <option value="0" <?php echo ($edit_data['use_cap_panitia'] ?? '1') == '0' ? 'selected' : ''; ?>>🚫 Tidak</option>
                </select>
            </div>
            <div class="form-group" style="flex:1; min-width:200px;">
                <label>Sertakan Cap WAREK / LEMBAGA?</label>
                <select name="use_cap_warek" class="form-control">
                    <option value="1" <?php echo ($edit_data['use_cap_warek'] ?? '1') == '1' ? 'selected' : ''; ?>>✅ Ya</option>
                    <option value="0" <?php echo ($edit_data['use_cap_warek'] ?? '1') == '0' ? 'selected' : ''; ?>>🚫 Tidak</option>
                </select>
            </div>
            <div class="form-group" style="flex:1; min-width:200px;">
                <label>Sertakan Cap BEM?</label>
                <select name="use_cap_presma" class="form-control">
                    <option value="1" <?php echo ($edit_data['use_cap_presma'] ?? '1') == '1' ? 'selected' : ''; ?>>✅ Ya</option>
                    <option value="0" <?php echo ($edit_data['use_cap_presma'] ?? '1') == '0' ? 'selected' : ''; ?>>🚫 Tidak</option>
                </select>
            </div>
        </div>

        <div class="form-group" style="margin-top: 30px;">
            <button type="submit" class="btn-primary" style="padding: 12px 25px; font-size: 1.1rem; width: 100%;">
                <i class="fas fa-file-pdf"></i> <?php echo $is_edit ? 'Simpan Perubahan & Cek Arsip' : 'Generate dan Arsipkan Surat'; ?>
            </button>
            <?php if($is_edit): ?>
            <a href="arsip-surat.php?jenis=<?php echo $jenis_surat_val; ?>" style="display:block; text-align:center; margin-top:15px; color:#555;">Kembali ke Daftar Arsip</a>
            <?php else: ?>
            <p style="text-align: center; font-size: 0.85rem; color: #666; margin-top: 10px;">
                Saat di-klik, data otomatis tersimpan di Arsip dan layar akan dialihkan ke halaman Mode Print PDF.
            </p>
            <?php endif; ?>
        </div>

    </div>
</form>

<script>
// ============================================================
// Searchable Template Picker Logic
// ============================================================
function showTplResults(type) {
    document.getElementById('results-' + type).style.display = 'block';
}

function filterTpl(type) {
    const input = document.querySelector('#picker-' + type + ' .tpl-search-input');
    const filter = input.value.toLowerCase();
    const results = document.getElementById('results-' + type);
    const items = results.getElementsByClassName('tpl-item');
    let hasMatch = false;

    for (let i = 0; i < items.length; i++) {
        const label = items[i].querySelector('.tpl-item-label').innerText.toLowerCase();
        const text = items[i].querySelector('.tpl-item-text').innerText.toLowerCase();
        if (label.includes(filter) || text.includes(filter)) {
            items[i].style.display = "";
            hasMatch = true;
        } else {
            items[i].style.display = "none";
        }
    }

    // Handle empty results
    let emptyMsg = results.querySelector('.tpl-empty');
    if (!hasMatch) {
        if (!emptyMsg) {
            emptyMsg = document.createElement('div');
            emptyMsg.className = 'tpl-empty';
            emptyMsg.innerText = 'Template tidak ditemukan...';
            results.appendChild(emptyMsg);
        }
    } else if (emptyMsg) {
        emptyMsg.remove();
    }
}

function selectTpl(targetId, value, type) {
    document.getElementById(targetId).value = value;
    document.getElementById('results-' + type).style.display = 'none';
    document.querySelector('#picker-' + type + ' .tpl-search-input').value = '';
    
    // Trigger any sync if needed
    if (typeof syncParagraf === 'function') syncParagraf();
}

// Close results when clicking outside
document.addEventListener('click', function(e) {
    ['perihal', 'tujuan'].forEach(type => {
        const picker = document.getElementById('picker-' + type);
        const results = document.getElementById('results-' + type);
        if (picker && !picker.contains(e.target)) {
            results.style.display = 'none';
        }
    });
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
