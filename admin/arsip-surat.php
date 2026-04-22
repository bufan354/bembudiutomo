<?php
// admin/arsip-surat.php
$page_css = 'arsip-surat';
require_once __DIR__ . '/header.php';

requireSekretaris();

$periode_id = getUserPeriode();
$jenis = $_GET['jenis'] ?? 'L';
if (!in_array($jenis, ['L', 'D', 'M'])) $jenis = 'L';

$error = '';
$success = '';

// Handle upload Kop Surat Global
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_kop') {
    if (!csrfVerify()) {
        $error = "CSRF tidak valid.";
    } else {
        $kop_path = rtrim(UPLOAD_PATH, '/\\') . '/kop_surat.png';
        if (isset($_FILES['kop_surat']) && $_FILES['kop_surat']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['kop_surat']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg'])) {
                if (move_uploaded_file($_FILES['kop_surat']['tmp_name'], $kop_path)) {
                    $success = "Template Kop Surat berhasil disimpan/diperbarui.";
                } else {
                    $error = "Gagal memindahkan file yang diupload. Cek permissions.";
                }
            } else {
                $error = "Hanya mendung format gambar PNG, JPG, JPEG.";
            }
        } else {
            $error = "File gagal diunggah atau tidak ada file yang dipilih.";
        }
    }
}

// Handle Hapus Arsip
if (isset($_GET['hapus']) && is_numeric($_GET['hapus'])) {
    if (hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf_token'] ?? '')) {
        $id_hapus = (int)$_GET['hapus'];
        $surat_target = dbFetchOne("SELECT * FROM arsip_surat WHERE id = ? AND periode_id = ?", [$id_hapus, $periode_id], "ii");
        
        if ($surat_target) {
            $can_delete = true;
            // Validasi: Jika surat keluar/dalam, hanya boleh hapus yang ID-nya paling terakhir (mencegah loncat nomor)
            if (in_array($surat_target['jenis_surat'], ['L', 'D'])) {
                $max_id_keluar = dbFetchOne("SELECT MAX(id) as last_id FROM arsip_surat WHERE periode_id = ? AND jenis_surat = ?", [$periode_id, $surat_target['jenis_surat']], "is")['last_id'];
                if ($id_hapus != $max_id_keluar) {
                    $can_delete = false;
                    $error = "Hanya arsip surat keluar urutan PALING AKHIR yang diizinkan untuk dihapus guna menjaga integritas nomor urut. Gunakan fitur 'Edit' untuk merevisi surat di tengah urutan.";
                }
            }
            
            if ($can_delete) {
                if (!empty($surat_target['file_surat']) && $surat_target['jenis_surat'] === 'M') {
                    $file_path = UPLOAD_PATH . '/' . $surat_target['file_surat'];
                    if (file_exists($file_path)) unlink($file_path);
                }
                dbQuery("DELETE FROM arsip_surat WHERE id = ?", [$id_hapus], "i");
                $success = "Surat terpilih berhasil dihapus secara permanen dari arsip.";
            }
        } else {
            $error = "Surat tidak ditemukan atau akses ditolak.";
        }
    } else {
        $error = "Token keamanan tidak valid saat menghapus arsip.";
    }
}

// Ambil data arsip
$surat_list_raw = dbFetchAll(
    "SELECT * FROM arsip_surat WHERE periode_id = ? AND jenis_surat = ? ORDER BY id ASC",
    [$periode_id, $jenis], "is"
);

// Grouping logic: Group by nomor_surat
$grouped_surat = [];
foreach ($surat_list_raw as $s) {
    $grouped_surat[$s['nomor_surat']][] = $s;
}

// Get latest ID for Keluar/Dalam UI rendering
// Get latest ID for current Tab UI rendering
$latest_keluar_id = 0;
if ($jenis !== 'M') {
    $latest_keluar_id = dbFetchOne("SELECT MAX(id) as last_id FROM arsip_surat WHERE periode_id = ? AND jenis_surat = ?", [$periode_id, $jenis], "is")['last_id'];
}

// HANDLE EXPORT EXCEL
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $filename = "Arsip_Surat_{$jenis}_" . date('Y-m-d') . ".xls";
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Pragma: public");
    
    echo "<table border='1'>";
    echo "<tr><th colspan='5'>Arsip Surat Jenis: {$jenis}</th></tr>";
    echo "<tr><th>No</th><th>Tanggal</th><th>Nomor Surat</th><th>Perihal</th><th>Tujuan/Asal</th></tr>";
    if (empty($surat_list)) {
        echo "<tr><td colspan='5'>Belum ada data arsip</td></tr>";
    } else {
        $no = 1;
        foreach ($surat_list_raw as $surat) {
            echo "<tr>";
            echo "<td>" . $no++ . "</td>";
            echo "<td>" . htmlspecialchars($surat['tanggal_dikirim']) . "</td>";
            echo "<td>" . htmlspecialchars($surat['nomor_surat']) . "</td>";
            echo "<td>" . htmlspecialchars($surat['perihal']) . "</td>";
            echo "<td>" . htmlspecialchars($surat['tujuan']) . "</td>";
            echo "</tr>";
        }
    }
    echo "</table>";
    exit();
}

$css = "
.surat-actions { margin-top: 20px; display: flex; justify-content: space-between; align-items: center; }
.btn-buat { background: #4A90E2; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: bold; display:inline-flex; align-items:center; gap:8px; }
.btn-buat:hover { background: #357ABD; }
.tab-container { display: flex; gap: 10px; margin-bottom: 20px; }
.tab-btn { padding: 10px 20px; background: #1a1a2e; color: #8BB9F0; text-decoration: none; border-radius: 5px; border: 1px solid #4A90E2; }
.tab-btn:hover { background: #4A90E2; color: white; }
.tab-btn.active { background: #4A90E2; color: white; font-weight: bold; }

/* Grouping Styles */
.group-parent { background: rgba(74, 144, 226, 0.05) !important; }
.group-toggle { cursor: pointer; color: #4A90E2; font-size: 1.1rem; transition: transform 0.2s; display: inline-block; vertical-align: middle; margin-right: 5px; }
.group-toggle.open { transform: rotate(90deg); }
.child-row { display: none; background: rgba(0, 0, 0, 0.2) !important; }
.child-row.show { display: table-row; }
.child-indicator { display: inline-block; width: 20px; height: 1px; background: #333; margin-right: 10px; vertical-align: middle; position: relative; top: -2px; }
.badge-count { background: #4A90E2; color: white; padding: 2px 6px; border-radius: 10px; font-size: 0.7rem; margin-left: 5px; vertical-align: middle; }
.btn-copy { background: #673AB7; color: white; padding: 8px 12px; border-radius: 4px; text-decoration: none; font-size: 0.85rem; font-weight: bold; display: inline-flex; align-items: center; gap: 6px; border: none; cursor: pointer; transition: background 0.2s; }
.btn-copy:hover { background: #5E35B1; }
";
?>
<style><?php echo $css; ?></style>

<div class="page-header">
    <h1><i class="fas fa-folder-open"></i> Arsip Surat</h1>
    <p>Manajemen arsip surat Keluar, surat Dalam, dan surat Masuk.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom: 20px;">
    <div class="card-header"><i class="fas fa-image"></i> Template Kop Surat Output</div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" style="display:flex; gap:15px; align-items:center; flex-wrap:wrap;">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="upload_kop">
            
            <?php $kop_exists = file_exists(rtrim(UPLOAD_PATH, '/\\') . '/kop_surat.png'); ?>
            
            <div style="flex:1; min-width:250px;">
                <input type="file" name="kop_surat" class="form-control" accept="image/png, image/jpeg" required>
                <small style="color:#666;">Upload gambar banner Kop Surat panjang untuk dicetak di atas hasil cetakan surat (Format JPG/PNG. Rekomendasi lebar 2480px).</small>
            </div>
            <button type="submit" class="btn-primary" style="padding: 10px 20px;"><i class="fas fa-upload"></i> Simpan Kop Surat</button>
            <?php if ($kop_exists): ?>
                <span class="badge" style="background:#4caf50; color:white;"><i class="fas fa-check-circle"></i> Tersedia (Aktif)</span>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        
        <div class="surat-actions" style="flex-wrap:wrap; gap:15px;">
            <div class="tab-container" style="flex-wrap:wrap;">
                <a href="arsip-surat.php?jenis=L" class="tab-btn <?php echo $jenis === 'L' ? 'active' : ''; ?>">Surat Keluar (Luar)</a>
                <a href="arsip-surat.php?jenis=D" class="tab-btn <?php echo $jenis === 'D' ? 'active' : ''; ?>">Surat Dalam</a>
                <a href="arsip-surat.php?jenis=M" class="tab-btn <?php echo $jenis === 'M' ? 'active' : ''; ?>">Surat Masuk (Luar)</a>
            </div>
            
            <?php if ($jenis === 'M'): ?>
                <div>
                    <a href="arsip-surat.php?jenis=<?php echo $jenis; ?>&export=excel" class="btn-buat" style="background:#2E7D32;"><i class="fas fa-file-excel"></i> Export Excel</a>
                    <a href="arsip-manual.php?type=M" class="btn-buat" style="background:#f39c12;"><i class="fas fa-file-import"></i> Catat Surat Masuk (Eksternal)</a>
                </div>
            <?php else: ?>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="arsip-surat.php?jenis=<?php echo $jenis; ?>&export=excel" class="btn-buat" style="background:#2E7D32;"><i class="fas fa-file-excel"></i> Export Excel</a>
                    <a href="buat-surat.php" class="btn-buat"><i class="fas fa-plus"></i> Buat Surat Otomatis</a>
                    <a href="arsip-manual.php?type=<?php echo $jenis; ?>" class="btn-buat" style="background:#f39c12;"><i class="fas fa-file-import"></i> Arsipkan Surat Manual (<?php echo $jenis; ?>)</a>
                </div>
            <?php endif; ?>
        </div>

        <div style="overflow-x:auto;">
            <table class="admin-table">
                <thead>
                    <tr class="header-row">
                        <th width="5%" style="text-align:center;">No</th>
                        <th width="15%" style="text-align:center;">Tanggal <?php echo $jenis==='M' ? 'Diterima' : 'Dikirim'; ?></th>
                        <th width="20%">Nomor Surat</th>
                        <th width="20%">Perihal</th>
                        <th width="25%"><?php echo $jenis==='M' ? 'Asal Instansi' : 'Dituju Kepada'; ?></th>
                        <th width="15%" style="text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($grouped_surat)): ?>
                        <tr>
                            <td colspan="6" style="padding: 30px; text-align:center;">Belum ada arsip surat.</td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($grouped_surat as $nomor_surat => $items): 
                            $parent = $items[0];
                            $has_children = count($items) > 1;
                            $group_id = "group_" . md5($nomor_surat);
                        ?>
                        <tr class="<?php echo $has_children ? 'group-parent' : ''; ?>">
                            <td style="text-align:center;"><?php echo $no++; ?></td>
                            <td style="text-align:center;"><?php echo htmlspecialchars((string)$parent['tanggal_dikirim']); ?></td>
                            <td>
                                <?php if ($has_children): ?>
                                    <span class="group-toggle" onclick="toggleGroup('<?php echo $group_id; ?>', this)">
                                        <i class="fas fa-caret-right"></i>
                                    </span>
                                <?php endif; ?>
                                <strong><?php echo htmlspecialchars((string)$parent['nomor_surat']); ?></strong>
                                <div style="margin-top: 5px; display: flex; gap: 5px; flex-wrap: wrap;">
                                    <?php if ($has_children): ?>
                                        <span class="badge-count"><?php echo count($items); ?> Recipient</span>
                                    <?php endif; ?>
                                    <?php if (!empty($parent['file_surat'])): ?>
                                        <span style="background: #666; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem;"><i class="fas fa-hand-paper"></i> Manual</span>
                                    <?php else: ?>
                                        <span style="background: #4A90E2; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem;"><i class="fas fa-robot"></i> Sistem</span>
                                    <?php endif; ?>
                                </div>
                                <br>
                                <?php if ($jenis === 'M' && !empty($parent['file_surat'])): ?>
                                    <a href="<?php echo uploadUrl($parent['file_surat']); ?>" target="_blank" style="font-size: 0.8rem; color: #8BB9F0; text-decoration: none; margin-top: 5px; display: inline-block;">
                                        <i class="fas fa-file-pdf"></i> Download Arsip
                                    </a>
                                <?php elseif ($jenis !== 'M'): ?>
                                    <a href="cetak-surat.php?id=<?php echo $parent['id']; ?>" target="_blank" style="font-size: 0.8rem; color: #8BB9F0; text-decoration: none; margin-top: 5px; display: inline-block;">
                                        <i class="fas fa-print"></i> Lihat/Cetak
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($parent['perihal']); ?></td>
                            <td>
                                <?php if ($has_children): ?>
                                    <em style="color: #aaa; font-size: 0.9rem;">(Multi-recipient)</em><br>
                                <?php endif; ?>
                                <?php echo nl2br(htmlspecialchars($parent['tujuan'])); ?>
                            </td>
                            <td style="text-align:center; vertical-align:middle; width: 140px;">
                                <?php 
                                // Jika ada file_surat, berarti ini arsip manual (M, L, atau D manual)
                                $is_manual = !empty($parent['file_surat']);
                                $edit_link = $is_manual ? "arsip-manual.php?edit={$parent['id']}" : "buat-surat.php?edit={$parent['id']}"; 
                                ?>
                                <a href="<?php echo $edit_link; ?>" class="btn-edit" style="margin-bottom:8px; width: 100%; justify-content: center;" title="Edit Surat"><i class="fas fa-edit"></i> Edit</a>
                                <?php if ($jenis !== 'M'): ?>
                                    <?php 
                                    $konten = json_decode($parent['konten_surat'], true) ?? [];
                                    $tujuan_first = trim(explode("\n", $parent['tujuan'])[0]);
                                    $copy_data = [
                                        'tujuan' => $parent['tujuan'],
                                        'tujuan_short' => $tujuan_first,
                                        'perihal' => $parent['perihal'],
                                        'kegiatan' => !empty($konten['nama_kegiatan']) ? $konten['nama_kegiatan'] : '',
                                        'hari' => $konten['pelaksanaan_hari_tanggal'] ?? '',
                                        'waktu' => $konten['pelaksanaan_waktu'] ?? '',
                                        'tempat' => $konten['pelaksanaan_tempat'] ?? '',
                                        'konteks' => $konten['konteks'] ?? ''
                                    ];
                                    ?>
                                    <button class="btn-copy" style="margin-bottom:8px; width: 100%; justify-content: center;" 
                                            onclick="copyRedaksi(<?php echo htmlspecialchars(json_encode($copy_data), ENT_QUOTES, 'UTF-8'); ?>, this)" title="Salin Redaksi untuk WA">
                                        <i class="fas fa-share-alt"></i> Salin Redaksi
                                    </button>
                                    <a href="buat-surat.php?clone=<?php echo $parent['id']; ?>" class="btn-buat" style="margin-bottom:8px; width: 100%; justify-content: center; background: #27ae60;" title="Buat salinan surat ini untuk tujuan lain"><i class="fas fa-copy"></i> Duplikat</a>
                                <?php endif; ?>
                                <br>
                                <?php if ($jenis === 'M' || $parent['id'] == $latest_keluar_id): ?>
                                    <a href="arsip-surat.php?jenis=<?php echo $jenis; ?>&hapus=<?php echo $parent['id']; ?>&csrf_token=<?php echo csrfToken(); ?>" 
                                       onclick="return confirm('Yakin ingin menghapus arsip surat ini beserta filenya secara permanen?')"
                                       class="btn-delete" style="width: 100%; justify-content: center;" title="Hapus"><i class="fas fa-trash-alt"></i> Hapus</a>
                                <?php else: ?>
                                    <span class="btn-locked" style="width: 100%; justify-content: center;" 
                                          title="Hanya surat paling akhir yang bisa dihapus. Hapus satu per satu dari yang terbaru."
                                          onclick="alert('❌ Tidak dapat dihapus!\n\nHanya surat dengan nomor urut PALING AKHIR yang boleh dihapus.\nHapus satu per satu mulai dari surat terbaru untuk menjaga urutan nomor surat.')">
                                        <i class="fas fa-lock"></i> Terkunci
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <?php if ($has_children): ?>
                            <?php foreach (array_slice($items, 1) as $child): ?>
                            <tr class="child-row <?php echo $group_id; ?>">
                                <td style="text-align:center; color: #555;">└</td>
                                <td style="text-align:center; color: #888; font-size: 0.85rem;"><?php echo htmlspecialchars((string)$child['tanggal_dikirim']); ?></td>
                                <td style="padding-left: 30px;">
                                    <div class="child-indicator"></div>
                                    <span style="color: #888; font-size: 0.85rem;"><?php echo htmlspecialchars((string)$child['nomor_surat']); ?></span><br>
                                    <a href="cetak-surat.php?id=<?php echo $child['id']; ?>" target="_blank" style="font-size: 0.75rem; color: #666; text-decoration: none; margin-top: 2px; display: inline-block;">
                                        <i class="fas fa-print"></i> Lihat/Cetak
                                    </a>
                                </td>
                                <td style="color: #888; font-size: 0.85rem;"><?php echo htmlspecialchars((string)$child['perihal']); ?></td>
                                <td style="border-left: 2px solid #4A90E2; padding-left: 15px;">
                                    <?php echo nl2br(htmlspecialchars((string)$child['tujuan'])); ?>
                                </td>
                                <td style="text-align:center; vertical-align:middle;">
                                    <?php 
                                    $child_is_manual = !empty($child['file_surat']);
                                    $child_edit = $child_is_manual ? "arsip-manual.php?edit={$child['id']}" : "buat-surat.php?edit={$child['id']}"; 
                                    ?>
                                    <a href="<?php echo $child_edit; ?>" class="btn-edit" style="font-size: 0.7rem; padding: 4px 8px; background: #333;" title="Edit Surat"><i class="fas fa-edit"></i> Edit</a>
                                    
                                    <?php if ($jenis !== 'M'): ?>
                                        <?php 
                                        $ckonten = json_decode($child['konten_surat'], true) ?? [];
                                        $ctujuan_first = trim(explode("\n", $child['tujuan'])[0]);
                                        $ccopy_data = [
                                            'tujuan' => $child['tujuan'],
                                            'tujuan_short' => $ctujuan_first,
                                            'perihal' => $child['perihal'],
                                            'kegiatan' => !empty($ckonten['nama_kegiatan']) ? $ckonten['nama_kegiatan'] : '',
                                            'hari' => $ckonten['pelaksanaan_hari_tanggal'] ?? '',
                                            'waktu' => $ckonten['pelaksanaan_waktu'] ?? '',
                                            'tempat' => $ckonten['pelaksanaan_tempat'] ?? '',
                                            'konteks' => $ckonten['konteks'] ?? ''
                                        ];
                                        ?>
                                        <button class="btn-copy" style="font-size: 0.7rem; padding: 4px 8px; background: #4527a0; margin-top:5px;" 
                                                onclick="copyRedaksi(<?php echo htmlspecialchars(json_encode($ccopy_data), ENT_QUOTES, 'UTF-8'); ?>, this)" title="Salin Redaksi">
                                            <i class="fas fa-share-alt"></i> Salin
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($jenis === 'M' || $child['id'] == $latest_keluar_id): ?>
                                        <a href="arsip-surat.php?jenis=<?php echo $jenis; ?>&hapus=<?php echo $child['id']; ?>&csrf_token=<?php echo csrfToken(); ?>" 
                                           onclick="return confirm('Yakin ingin menghapus salinan surat ini?')"
                                           class="btn-delete" style="font-size: 0.7rem; padding: 4px 8px; background: #442222; margin-top: 5px;" title="Hapus"><i class="fas fa-trash-alt"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<script>
function toggleGroup(groupId, btn) {
    const rows = document.querySelectorAll('.' + groupId);
    btn.classList.toggle('open');
    rows.forEach(row => {
        row.classList.toggle('show');
    });
}

function copyRedaksi(data, btn) {
    let perihal = data.perihal;
    let kegiatan = data.kegiatan || "Kegiatan BEM";
    let tujuan = data.tujuan;
    let tujuanShort = data.tujuan_short;
    
    // Tentukan kata kerja aksi (mengundang/permohonan/dll)
    let actionWord = "menyampaikan " + perihal.toLowerCase() + " kepada " + tujuanShort;
    if(perihal.toLowerCase().includes("undangan")) {
        actionWord = "mengundang " + tujuanShort;
    }

    let text = `Assalamu'alaikum Wr. Wb.
Yth. 
${tujuan}

Sehubungan dengan diadakanya ${kegiatan}. Dengan ini kami ${actionWord} pada kegiatan tersebut, yang akan dilaksanakan pada :

🗓️ | ${data.hari || '-'}
🕘 | ${data.waktu || '-'}
🏢 | ${data.tempat || '-'}

${data.konteks ? data.konteks + '\n\n' : ''}Demikian informasi ini kami sampaikan, atas perhatian dan kerjasamanya kami ucapkan terima kasih.

Wassalamu’alaikum Wr. Wb.`;

    navigator.clipboard.writeText(text).then(() => {
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Tersalin!';
        btn.style.background = '#2e7d32';
        setTimeout(() => {
            btn.innerHTML = originalHtml;
            btn.style.background = '';
        }, 2000);
    }).catch(err => {
        alert('Gagal menyalin teks: ' + err);
    });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
