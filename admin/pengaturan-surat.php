<?php
// admin/pengaturan-surat.php
$page_css = 'arsip-surat';
require_once __DIR__ . '/header.php';

requireSekretaris();
$periode_id = getUserPeriode();
$error = '';
$success = '';

// Proses Hapus Template
if (isset($_GET['hapus']) && is_numeric($_GET['hapus'])) {
    if (hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf_token'] ?? '')) {
        $id_hapus = (int)$_GET['hapus'];
        dbQuery("DELETE FROM surat_templates WHERE id = ? AND periode_id = ?", [$id_hapus, $periode_id], "ii");
        $success = "Template berhasil dihapus.";
    } else {
        $error = "Token keamanan tidak valid saat menghapus.";
    }
}

// Proses Update Pengaturan Tanda Tangan Tetap
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_ttd') {
    if (!csrfVerify()) {
        $error = "Token CSRF tidak valid.";
    } else {
        $w_name = sanitizeText($_POST['ttd_warek_name'] ?? '');
        $w_jab = sanitizeText($_POST['ttd_warek_jabatan'] ?? '');
        $p_name = sanitizeText($_POST['ttd_presma_name'] ?? '');
        $p_jab = sanitizeText($_POST['ttd_presma_jabatan'] ?? '');

        dbQuery("REPLACE INTO pengaturan (kunci, nilai) VALUES ('ttd_warek_name', ?)", [$w_name], "s");
        dbQuery("REPLACE INTO pengaturan (kunci, nilai) VALUES ('ttd_warek_jabatan', ?)", [$w_jab], "s");
        dbQuery("REPLACE INTO pengaturan (kunci, nilai) VALUES ('ttd_presma_name', ?)", [$p_name], "s");
        dbQuery("REPLACE INTO pengaturan (kunci, nilai) VALUES ('ttd_presma_jabatan', ?)", [$p_jab], "s");

        // Handle File Uploads
        if (!empty($_FILES['ttd_warek_image']['name'])) {
            $uploaded = uploadFile($_FILES['ttd_warek_image'], 'umum');
            if ($uploaded) dbQuery("REPLACE INTO pengaturan (kunci, nilai) VALUES ('ttd_warek_image', ?)", [$uploaded], "s");
        }
        if (!empty($_FILES['ttd_presma_image']['name'])) {
            $uploaded = uploadFile($_FILES['ttd_presma_image'], 'umum');
            if ($uploaded) dbQuery("REPLACE INTO pengaturan (kunci, nilai) VALUES ('ttd_presma_image', ?)", [$uploaded], "s");
        }
        if (!empty($_FILES['cap_panitia_image']['name'])) {
            $uploaded = uploadFile($_FILES['cap_panitia_image'], 'umum');
            if ($uploaded) dbQuery("REPLACE INTO pengaturan (kunci, nilai) VALUES ('cap_panitia_image', ?)", [$uploaded], "s");
        }
        if (!empty($_FILES['cap_warek_image']['name'])) {
            $uploaded = uploadFile($_FILES['cap_warek_image'], 'umum');
            if ($uploaded) dbQuery("REPLACE INTO pengaturan (kunci, nilai) VALUES ('cap_warek_image', ?)", [$uploaded], "s");
        }
        if (!empty($_FILES['cap_presma_image']['name'])) {
            $uploaded = uploadFile($_FILES['cap_presma_image'], 'umum');
            if ($uploaded) dbQuery("REPLACE INTO pengaturan (kunci, nilai) VALUES ('cap_presma_image', ?)", [$uploaded], "s");
        }
        $success = "Pengaturan Tanda Tangan & Stempel berhasil diperbarui.";
    }
}


// Proses Tambah / Update Template
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['tambah', 'update'])) {
    if (!csrfVerify()) {
        $error = "Token CSRF tidak valid.";
    } else {
        $action = $_POST['action'];
        $jenis = $_POST['jenis'] === 'tujuan' ? 'tujuan' : 'perihal';
        $label = sanitizeText($_POST['label'], 100);
        $isi_teks = strip_tags(trim($_POST['isi_teks'])); 
        
        if (empty($label) || empty($isi_teks)) {
            $error = "Nama/Label dan Isi Teks tidak boleh kosong.";
        } else {
            if ($action === 'tambah') {
                dbQuery(
                    "INSERT INTO surat_templates (periode_id, jenis, label, isi_teks) VALUES (?, ?, ?, ?)",
                    [$periode_id, $jenis, $label, $isi_teks], "isss"
                );
                $success = "Template " . ucfirst($jenis) . " berhasil disimpan!";
            } else {
                $id_update = (int)$_POST['template_id'];
                dbQuery(
                    "UPDATE surat_templates SET jenis = ?, label = ?, isi_teks = ? WHERE id = ? AND periode_id = ?",
                    [$jenis, $label, $isi_teks, $id_update, $periode_id], "sssii"
                );
                $success = "Template " . ucfirst($jenis) . " berhasil diperbarui!";
            }
        }
    }
}

// Mode Edit Setup
$edit_id = (isset($_GET['edit']) && is_numeric($_GET['edit'])) ? (int)$_GET['edit'] : 0;
$edit_data = null;
if ($edit_id > 0) {
    $edit_data = dbFetchOne("SELECT * FROM surat_templates WHERE id = ? AND periode_id = ?", [$edit_id, $periode_id], "ii");
}

// Ambil semua template yang dimiliki periode ini
$templates = dbFetchAll("SELECT * FROM surat_templates WHERE periode_id = ? ORDER BY jenis DESC, label ASC", [$periode_id], "i");

// Pisahkan berdasarkan jenis untuk mempermudah render
$list_perihal = array_filter($templates, fn($t) => $t['jenis'] === 'perihal');
$list_tujuan  = array_filter($templates, fn($t) => $t['jenis'] === 'tujuan');

// Ambil Pengaturan Umum (Tanda Tangan)
$db_pengaturan = dbFetchAll("SELECT kunci, nilai FROM pengaturan");
$pengaturan = [];
foreach($db_pengaturan as $p) {
    $pengaturan[$p['kunci']] = $p['nilai'];
}

$def_warek_name = $pengaturan['ttd_warek_name'] ?? 'II MUHAMAD MISBAH, S.Pd.I., SE., MM.';
$def_warek_jab  = $pengaturan['ttd_warek_jabatan'] ?? 'WAREK III Bid. Kemahasiswaan';
$def_warek_img  = $pengaturan['ttd_warek_image'] ?? '';

// Fallback presma name
$ketua = getKetua($periode_id);
$fallback_presma = $ketua ? $ketua['nama_lengkap'] : '';

$def_presma_name = $pengaturan['ttd_presma_name'] ?? $fallback_presma;
$def_presma_jab  = $pengaturan['ttd_presma_jabatan'] ?? 'Ketua BEM INSTBUNAS Majalengka';
$def_presma_img  = $pengaturan['ttd_presma_image'] ?? '';

$def_cap_panitia = $pengaturan['cap_panitia_image'] ?? '';
$def_cap_warek   = $pengaturan['cap_warek_image'] ?? '';
$def_cap_presma  = $pengaturan['cap_presma_image'] ?? '';
?>

<style>
    .admin-table { border-collapse: separate; border-spacing: 0; }
    .admin-table tr td { border-bottom: 1px solid #2a3545; padding: 12px 10px; }
    .admin-table tr:last-child td { border-bottom: none; }
    .admin-table thead tr th { border-bottom: 2px solid #4A90E2; padding-bottom: 10px; }
</style>

<div class="page-header">
    <h1><i class="fas fa-cogs"></i> Pengaturan Template Surat</h1>
    <p>Kelola kumpulan teks Perihal dan Tujuan (Kepada Yth) agar pembuatan surat otomatis lebih cepat tanpa harus selalu mengetik ulang.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<!-- ============================================== -->
<!-- 1. PENGATURAN TANDA TANGAN TETAP              -->
<!-- ============================================== -->
<div class="card" style="margin-bottom:30px;">
    <div class="card-header" style="background:#1e2633;"><i class="fas fa-file-signature"></i> Pengaturan Tanda Tangan Tetap Pejabat (Untuk Cetak PDF)</div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="update_ttd">

            <div style="display:flex; gap:30px; flex-wrap:wrap;">
                
                <!-- WAREK III -->
                <div style="flex:1; min-width:300px; background:#12161b; padding:20px; border-radius:8px; border:1px solid #2a3545;">
                    <h3 style="margin-top:0; color:#8BB9F0; border-bottom:1px solid #333; padding-bottom:10px;"><i class="fas fa-user-tie"></i> Pihak Rektorat / WAREK</h3>
                    
                    <div class="form-group">
                        <label>Nama Rektor / Warek</label>
                        <input type="text" name="ttd_warek_name" class="form-control" value="<?php echo htmlspecialchars($def_warek_name); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Jabatan (Tampil di Surat)</label>
                        <input type="text" name="ttd_warek_jabatan" class="form-control" value="<?php echo htmlspecialchars($def_warek_jab); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Upload Tanda Tangan (Format transparan .PNG disarankan)</label>
                        <div id="preview_warek_ctn" style="<?php echo $def_warek_img ? '' : 'display:none;'; ?> margin-bottom:10px; padding:10px; background:#fff; border-radius:4px; max-width:200px; border:1px dashed #ccc;">
                            <?php if($def_warek_img): ?>
                                <?php echo imgTag($def_warek_img, 'TTD Warek', 'img-flexible', 'assets/images/no-image.jpg', 'preview_warek_img'); ?>
                            <?php else: ?>
                                <img id="preview_warek_img" class="img-flexible" src="#" alt="Preview" style="display:none;">
                            <?php endif; ?>
                        </div>
                        <input type="file" id="file_warek" name="ttd_warek_image" class="form-control" accept="image/png, image/jpeg, image/webp">
                        <small style="color:#aaa;">Biarkan kosong jika tidak ingin mengubah tanda tangan.</small>
                    </div>
                </div>

                <!-- PRESIDEN MAHASISWA -->
                <div style="flex:1; min-width:300px; background:#12161b; padding:20px; border-radius:8px; border:1px solid #2a3545;">
                    <h3 style="margin-top:0; color:#8BB9F0; border-bottom:1px solid #333; padding-bottom:10px;"><i class="fas fa-user-graduate"></i> Presiden Mahasiswa (BEM)</h3>
                    
                    <div class="form-group">
                        <label>Nama Presiden Mahasiswa</label>
                        <input type="text" name="ttd_presma_name" class="form-control" value="<?php echo htmlspecialchars($def_presma_name); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Jabatan (Tampil di Surat)</label>
                        <input type="text" name="ttd_presma_jabatan" class="form-control" value="<?php echo htmlspecialchars($def_presma_jab); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Upload Tanda Tangan (Format transparan .PNG disarankan)</label>
                        <div id="preview_presma_ctn" style="<?php echo $def_presma_img ? '' : 'display:none;'; ?> margin-bottom:10px; padding:10px; background:#fff; border-radius:4px; max-width:200px; border:1px dashed #ccc;">
                            <?php if($def_presma_img): ?>
                                <?php echo imgTag($def_presma_img, 'TTD Presma', 'img-flexible', 'assets/images/no-image.jpg', 'preview_presma_img'); ?>
                            <?php else: ?>
                                <img id="preview_presma_img" class="img-flexible" src="#" alt="Preview" style="display:none;">
                            <?php endif; ?>
                        </div>
                        <input type="file" id="file_presma" name="ttd_presma_image" class="form-control" accept="image/png, image/jpeg, image/webp">
                        <small style="color:#aaa;">Biarkan kosong jika tidak ingin mengubah tanda tangan.</small>
                    </div>
                </div>

            </div>

            <hr style="border:1px solid #2a3545; margin:40px 0;">

            <!-- STEMPEL / CAP -->
            <h3 style="margin-top:0; color:#8BB9F0; border-bottom:1px solid #333; padding-bottom:10px;"><i class="fas fa-stamp"></i> Pengaturan Stempel / Cap Instansi (Untuk Cetak PDF)</h3>
            <div style="display:flex; gap:30px; flex-wrap:wrap; margin-top: 20px;">
                
                <div style="flex:1; min-width:300px; background:#12161b; padding:20px; border-radius:8px; border:1px solid #2a3545;">
                    <div class="form-group">
                        <label>Cap PANITIA KEGIATAN</label>
                        <div id="preview_cap_panitia_ctn" style="<?php echo $def_cap_panitia ? '' : 'display:none;'; ?> margin-bottom:10px; padding:10px; background:#fff; border-radius:4px; max-width:200px; border:1px dashed #ccc;">
                            <?php if($def_cap_panitia): ?>
                                <?php echo imgTag($def_cap_panitia, 'Cap Panitia', 'img-flexible', 'assets/images/no-image.jpg', 'preview_cap_panitia_img'); ?>
                            <?php else: ?>
                                <img id="preview_cap_panitia_img" class="img-flexible" src="#" alt="Preview" style="display:none;">
                            <?php endif; ?>
                        </div>
                        <input type="file" id="file_cap_panitia" name="cap_panitia_image" class="form-control" accept="image/png, image/jpeg, image/webp">
                        <small style="color:#aaa;">Biarkan kosong jika tidak ingin mengubah cap.</small>
                    </div>
                </div>

                <div style="flex:1; min-width:300px; background:#12161b; padding:20px; border-radius:8px; border:1px solid #2a3545;">
                    <div class="form-group">
                        <label>Cap REKTORAT / LEMBAGA</label>
                        <div id="preview_cap_warek_ctn" style="<?php echo $def_cap_warek ? '' : 'display:none;'; ?> margin-bottom:10px; padding:10px; background:#fff; border-radius:4px; max-width:200px; border:1px dashed #ccc;">
                            <?php if($def_cap_warek): ?>
                                <?php echo imgTag($def_cap_warek, 'Cap Rektorat', 'img-flexible', 'assets/images/no-image.jpg', 'preview_cap_warek_img'); ?>
                            <?php else: ?>
                                <img id="preview_cap_warek_img" class="img-flexible" src="#" alt="Preview" style="display:none;">
                            <?php endif; ?>
                        </div>
                        <input type="file" id="file_cap_warek" name="cap_warek_image" class="form-control" accept="image/png, image/jpeg, image/webp">
                        <small style="color:#aaa;">Biarkan kosong jika tidak ingin mengubah cap.</small>
                    </div>
                </div>

                <div style="flex:1; min-width:300px; background:#12161b; padding:20px; border-radius:8px; border:1px solid #2a3545;">
                    <div class="form-group">
                        <label>Cap BEM / BEMCUP</label>
                        <div id="preview_cap_presma_ctn" style="<?php echo $def_cap_presma ? '' : 'display:none;'; ?> margin-bottom:10px; padding:10px; background:#fff; border-radius:4px; max-width:200px; border:1px dashed #ccc;">
                            <?php if($def_cap_presma): ?>
                                <?php echo imgTag($def_cap_presma, 'Cap BEM', 'img-flexible', 'assets/images/no-image.jpg', 'preview_cap_presma_img'); ?>
                            <?php else: ?>
                                <img id="preview_cap_presma_img" class="img-flexible" src="#" alt="Preview" style="display:none;">
                            <?php endif; ?>
                        </div>
                        <input type="file" id="file_cap_presma" name="cap_presma_image" class="form-control" accept="image/png, image/jpeg, image/webp">
                        <small style="color:#aaa;">Biarkan kosong jika tidak ingin mengubah cap.</small>
                    </div>
                </div>

            </div>

            <div style="margin-top:20px; text-align:right;">
                <button type="submit" class="btn-primary" style="margin-top:15px;"><i class="fas fa-save"></i> Simpan Pengaturan Tanda Tangan & Stempel</button>
            </div>
        </form>
    </div>
</div>

<script>
function setupLivePreview(inputId, imgId, ctnId) {
    const input = document.getElementById(inputId);
    const img = document.getElementById(imgId);
    if (!input || !img) return;

    input.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                img.src = e.target.result;
                img.style.display = 'block';
                document.getElementById(ctnId).style.display = 'block';
            }
            reader.readAsDataURL(file);
        }
    });
}
document.addEventListener('DOMContentLoaded', () => {
    setupLivePreview('file_warek', 'preview_warek_img', 'preview_warek_ctn');
    setupLivePreview('file_presma', 'preview_presma_img', 'preview_presma_ctn');
    setupLivePreview('file_cap_panitia', 'preview_cap_panitia_img', 'preview_cap_panitia_ctn');
    setupLivePreview('file_cap_warek', 'preview_cap_warek_img', 'preview_cap_warek_ctn');
    setupLivePreview('file_cap_presma', 'preview_cap_presma_img', 'preview_cap_presma_ctn');
});
</script>

<hr style="border:1px solid #2a3545; margin:40px 0;">

<!-- ============================================== -->
<!-- 2. PENGATURAN TEMPLATE REDAKSI                -->
<!-- ============================================== -->
<div style="display:flex; gap:20px; flex-wrap:wrap; align-items:flex-start;">
    
    <!-- Bagian Form Input Kiri -->
    <div class="card" style="flex:1; min-width:300px; max-width:400px;" id="form-template">
        <div class="card-header"><i class="fas <?php echo $edit_data ? 'fa-edit' : 'fa-plus-circle'; ?>"></i> <?php echo $edit_data ? 'Edit Template' : 'Tambah Template Baru'; ?></div>
        <div class="card-body">
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="<?php echo $edit_data ? 'update' : 'tambah'; ?>">
                <?php if ($edit_data): ?>
                    <input type="hidden" name="template_id" value="<?php echo $edit_data['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label style="color:#8BB9F0;"><i class="fas fa-tag"></i> Jenis Template</label>
                    <select name="jenis" class="form-control" required id="jenis_select">
                        <option value="perihal" <?php echo ($edit_data['jenis'] ?? '') === 'perihal' ? 'selected' : ''; ?>>Perihal (Subjek Surat)</option>
                        <option value="tujuan" <?php echo ($edit_data['jenis'] ?? '') === 'tujuan' ? 'selected' : ''; ?>>Tujuan (Kepada Yth)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label style="color:#8BB9F0;"><i class="fas fa-font"></i> Nama Label (Singkat)</label>
                    <input type="text" name="label" class="form-control" required placeholder="Cth: Undangan Rapat / Dekanat" value="<?php echo htmlspecialchars($edit_data['label'] ?? ''); ?>">
                    <small style="color:#aaa;">Ditampilkan sebagai opsi di menu dropdown form.</small>
                </div>
                
                <div class="form-group">
                    <label style="color:#8BB9F0;"><i class="fas fa-align-left"></i> Isi Teks</label>
                    <textarea name="isi_teks" id="isi_teks" rows="<?php echo ($edit_data['jenis'] ?? '') === 'tujuan' ? '4' : '3'; ?>" class="form-control" required placeholder="Cth: Permohonan Peminjaman Ruangan"><?php echo htmlspecialchars($edit_data['isi_teks'] ?? ''); ?></textarea>
                    <div style="background:rgba(0,0,0,0.3); border-radius:4px; padding:10px; margin-top:8px;">
                        <small style="color:#aaa;" id="hint_teks">
                            <?php if (($edit_data['jenis'] ?? '') === 'tujuan'): ?>
                                Ketik tujuan lengkap di sini (Boleh enter ke bawah).<br>Contoh:<br>Bapak Rektor Universitas X<br>Di Tempat
                            <?php else: ?>
                                Ketik perihal surat di sini. Tidak perlu huruf kapital semua (kecuali singkatan).
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
                
                <button type="submit" class="btn-primary" style="width:100%; padding:12px; font-size:1.05rem;"><i class="fas fa-save"></i> <?php echo $edit_data ? 'Update Template' : 'Simpan Template'; ?></button>
                <?php if ($edit_data): ?>
                    <a href="pengaturan-surat.php" class="btn-buat" style="width:100%; margin-top:10px; background:#444; justify-content:center;"><i class="fas fa-times"></i> Batal Edit</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <!-- Bagian Daftar Kanan -->
    <div style="flex:2; min-width:350px;">
        
        <div class="card" style="margin-bottom: 25px;">
            <div class="card-header"><i class="fas fa-list-ul"></i> Template "Tujuan" (Kepada Yth)</div>
            <div class="card-body" style="padding:15px; overflow-x:auto;">
                <table class="admin-table">
                    <thead>
                        <tr class="header-row">
                            <th width="35%"><i class="fas fa-bookmark"></i> Label</th>
                            <th width="50%"><i class="fas fa-align-justify"></i> Isi Teks Tujuan</th>
                            <th width="15%" style="text-align:center;"><i class="fas fa-cogs"></i> Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($list_tujuan)): ?>
                            <tr><td colspan="3" style="text-align:center; padding:20px; color:#aaa;">Belum ada template tujuan yang tersimpan.</td></tr>
                        <?php else: foreach ($list_tujuan as $tpl): ?>
                            <tr>
                                <td><strong style="color:#fff;"><?php echo htmlspecialchars($tpl['label']); ?></strong></td>
                                <td><div style="font-size:0.85rem; color:#bbb; white-space:pre-wrap; line-height:1.4;"><?php echo htmlspecialchars($tpl['isi_teks']); ?></div></td>
                                <td style="text-align:center; vertical-align:middle;">
                                    <div style="display:flex; flex-direction:column; gap:5px;">
                                        <a href="?edit=<?php echo $tpl['id']; ?>#form-template" class="btn-edit" style="justify-content: center; font-size: 0.75rem;"><i class="fas fa-edit"></i> Edit</a>
                                        <a href="?hapus=<?php echo $tpl['id']; ?>&csrf_token=<?php echo csrfToken(); ?>" 
                                           onclick="return confirm('Anda yakin ingin menghapus template ini?')"
                                           class="btn-delete" style="justify-content: center; font-size: 0.75rem;" title="Hapus Template" ><i class="fas fa-trash-alt"></i> Hapus</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header"><i class="fas fa-info-circle"></i> Template "Perihal"</div>
            <div class="card-body" style="padding:15px; overflow-x:auto;">
                <table class="admin-table">
                    <thead>
                        <tr class="header-row">
                            <th width="40%"><i class="fas fa-bookmark"></i> Label</th>
                            <th width="45%"><i class="fas fa-align-justify"></i> Isi Perihal</th>
                            <th width="15%" style="text-align:center;"><i class="fas fa-cogs"></i> Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($list_perihal)): ?>
                            <tr><td colspan="3" style="text-align:center; padding:20px; color:#aaa;">Belum ada template perihal yang tersimpan.</td></tr>
                        <?php else: foreach ($list_perihal as $tpl): ?>
                            <tr>
                                <td><strong style="color:#fff;"><?php echo htmlspecialchars($tpl['label']); ?></strong></td>
                                <td><div style="font-size:0.85rem; color:#bbb;"><?php echo htmlspecialchars($tpl['isi_teks']); ?></div></td>
                                <td style="text-align:center; vertical-align:middle;">
                                    <div style="display:flex; flex-direction:column; gap:5px;">
                                        <a href="?edit=<?php echo $tpl['id']; ?>#form-template" class="btn-edit" style="justify-content: center; font-size: 0.75rem;"><i class="fas fa-edit"></i> Edit</a>
                                        <a href="?hapus=<?php echo $tpl['id']; ?>&csrf_token=<?php echo csrfToken(); ?>" 
                                           onclick="return confirm('Anda yakin ingin menghapus template ini?')"
                                           class="btn-delete" style="justify-content: center; font-size: 0.75rem;" title="Hapus Template" ><i class="fas fa-trash-alt"></i> Hapus</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
    
</div>

<script>
document.getElementById('jenis_select').addEventListener('change', function() {
    let type = this.value;
    let hint = document.getElementById('hint_teks');
    let area = document.getElementById('isi_teks');
    
    if(type === 'tujuan') {
        hint.innerHTML = 'Ketik tujuan lengkap di sini (Boleh enter ke bawah).<br>Contoh:<br>Bapak Rektor Universitas X<br>Di Tempat';
        area.rows = 4;
        area.placeholder = 'Rektor Universitas X\nDi Tempat';
    } else {
        hint.innerHTML = 'Ketik perihal surat di sini. Tidak perlu enter ke bawah.<br>Contoh: Permohonan Bantuan Dana';
        area.rows = 2;
        area.placeholder = 'Permohonan Bantuan Dana';
    }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
