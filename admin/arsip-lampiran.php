<?php
// admin/arsip-lampiran.php
// Tambahkan error reporting untuk debugging di InfinityFree
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_css = 'arsip-surat';
require_once __DIR__ . '/header.php';

requireSekretaris();
$periode_id = getUserPeriode();

$success = '';
$error = '';

// --- ACTION HANDLER: DELETE ---
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    csrfVerify();
    $del_id = (int)$_GET['delete'];
    $res = dbQuery("DELETE FROM lampiran_pinjam WHERE id = ? AND periode_id = ?", [$del_id, $periode_id]);
    if ($res) {
        $success = "Data lampiran berhasil dihapus.";
    } else {
        $error = "Gagal menghapus data.";
    }
}

// --- ACTION HANDLER: UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    csrfVerify();
    $id      = (int)$_POST['id'];
    $acara   = trim($_POST['nama_acara'] ?? '');
    $tanggal = trim($_POST['tanggal_kegiatan'] ?? '');
    $tahun   = trim($_POST['tahun'] ?? '');
    
    if (empty($acara) || empty($tanggal)) {
        $error = "Nama acara dan tanggal wajib diisi.";
    } else {
        $res = dbQuery("UPDATE lampiran_pinjam SET nama_acara = ?, tanggal_kegiatan = ?, tahun = ? WHERE id = ? AND periode_id = ?", [
            $acara, $tanggal, $tahun, $id, $periode_id
        ]);
        if ($res) {
            $success = "Data lampiran berhasil diperbarui.";
        } else {
            $error = "Gagal memperbarui data.";
        }
    }
}

// Ambil data arsip lampiran
$list_lampiran = dbFetchAll("SELECT * FROM lampiran_pinjam WHERE periode_id = ? ORDER BY created_at DESC", [$periode_id], "i");
?>

<div class="arsip-surat-container">
    <div class="page-header" style="margin-bottom: 30px;">
        <div class="header-content">
            <h1><i class="fas fa-box-archive"></i> Arsip Pustaka Lampiran</h1>
            <p>Kelola data peminjaman barang yang telah disimpan untuk lampiran surat.</p>
        </div>
        <a href="cetak-lampiran.php" class="btn-primary" style="background: var(--primary-gradient); color: #fff; text-decoration: none; padding: 12px 24px; border-radius: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
            <i class="fas fa-plus"></i> Tambah Lampiran Baru
        </a>
    </div>

    <?php if ($success): ?>
        <div style="background: rgba(46, 204, 113, 0.1); color: #2ecc71; padding: 15px; border-radius: 12px; border: 1px solid rgba(46, 204, 113, 0.2); margin-bottom: 20px;">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div style="background: rgba(231, 76, 60, 0.1); color: #e74c3c; padding: 15px; border-radius: 12px; border: 1px solid rgba(231, 76, 60, 0.2); margin-bottom: 20px;">
            <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="card" style="background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 20px; overflow: hidden; backdrop-filter: blur(10px);">
        <div class="table-responsive">
            <table class="arsip-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: rgba(255,255,255,0.03);">
                        <th style="padding: 15px; text-align: left; color: #888; font-weight: 600; border-bottom: 1px solid var(--border-color);" width="50">No</th>
                        <th style="padding: 15px; text-align: left; color: #888; font-weight: 600; border-bottom: 1px solid var(--border-color);">Nama Acara / Kegiatan</th>
                        <th style="padding: 15px; text-align: left; color: #888; font-weight: 600; border-bottom: 1px solid var(--border-color);">Waktu Pelaksanaan</th>
                        <th style="padding: 15px; text-align: left; color: #888; font-weight: 600; border-bottom: 1px solid var(--border-color);" width="120">Barang</th>
                        <th style="padding: 15px; text-align: center; color: #888; font-weight: 600; border-bottom: 1px solid var(--border-color);" width="150">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($list_lampiran)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding: 50px; color: #555;">
                                <i class="fas fa-folder-open" style="font-size: 3rem; margin-bottom: 15px; display:block; color: #333;"></i>
                                Belum ada data lampiran yang disimpan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($list_lampiran as $idx => $l): 
                            $barang_data = json_decode($l['barang_json'], true) ?: [];
                            $jml_barang = count($barang_data);
                        ?>
                        <tr style="border-bottom: 1px solid var(--border-color); transition: 0.3s;" onmouseover="this.style.background='rgba(255,255,255,0.02)'" onmouseout="this.style.background='transparent'">
                            <td style="padding: 15px;"><?php echo $idx + 1; ?></td>
                            <td style="padding: 15px;">
                                <div style="font-weight:600; color:var(--accent-color);"><?php echo htmlspecialchars($l['nama_acara']); ?></div>
                                <div style="font-size:0.75rem; color:#666;">ID: #<?php echo $l['id']; ?></div>
                            </td>
                            <td style="padding: 15px;">
                                <div style="color: #eee;"><?php echo htmlspecialchars($l['tanggal_kegiatan']); ?></div>
                                <div style="font-size:0.75rem; font-weight:bold; color:#555;"><?php echo htmlspecialchars($l['tahun']); ?></div>
                            </td>
                            <td style="padding: 15px;">
                                <span style="background: rgba(79, 172, 254, 0.1); color: #4facfe; border: 1px solid rgba(79, 172, 254, 0.2); padding: 4px 10px; border-radius: 8px; font-size: 0.8rem; font-weight: 600;">
                                    <?php echo $jml_barang; ?> Item
                                </span>
                            </td>
                            <td style="padding: 15px; text-align:center;">
                                <div style="display:flex; gap:8px; justify-content:center;">
                                    <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($l)); ?>)" style="width: 38px; height: 38px; border-radius: 10px; background: rgba(79, 172, 254, 0.1); color: #4facfe; border: none; cursor: pointer; transition: 0.3s;" title="Edit Info">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?delete=<?php echo $l['id']; ?>&csrf_token=<?php echo csrfToken(); ?>" 
                                       style="width: 38px; height: 38px; border-radius: 10px; background: rgba(231, 76, 60, 0.1); color: #e74c3c; border: none; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; text-decoration: none;"
                                       onclick="return confirm('Hapus data lampiran ini dari arsip?')" 
                                       title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL EDIT INFO -->
<div id="editModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:9999; backdrop-filter:blur(10px); align-items:center; justify-content:center;">
    <div style="background: var(--card-bg); border: 1px solid var(--border-color); width:100%; max-width:500px; padding:30px; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.5);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2 style="margin:0; color: #fff;"><i class="fas fa-edit"></i> Edit Info Lampiran</h2>
            <button onclick="closeModal()" style="background:none; border:none; color:#888; font-size:1.5rem; cursor:pointer;"><i class="fas fa-times"></i></button>
        </div>
        <form action="" method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit-id">
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 8px; color: #aaa; font-size: 0.9rem;">Nama Acara / Kegiatan</label>
                <input type="text" name="nama_acara" id="edit-acara" required style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--border-color); padding: 12px; border-radius: 10px; color: #fff;">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 8px; color: #aaa; font-size: 0.9rem;">Tanggal Pelaksanaan</label>
                <input type="text" name="tanggal_kegiatan" id="edit-tanggal" placeholder="Contoh: Senin, 12 Desember 2025" required style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--border-color); padding: 12px; border-radius: 10px; color: #fff;">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 8px; color: #aaa; font-size: 0.9rem;">Tahun</label>
                <input type="text" name="tahun" id="edit-tahun" maxlength="4" required style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--border-color); padding: 12px; border-radius: 10px; color: #fff;">
            </div>
            
            <div style="margin-top:25px; display:flex; gap:10px;">
                <button type="submit" class="btn-primary" style="flex-grow:1; background: var(--primary-gradient); border: none; color: #fff; padding: 12px; border-radius: 12px; font-weight: 600; cursor: pointer;">Simpan Perubahan</button>
                <button type="button" onclick="closeModal()" style="background: #333; color: #fff; border: none; padding: 12px 20px; border-radius: 12px; cursor: pointer;">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(data) {
    document.getElementById('edit-id').value = data.id;
    document.getElementById('edit-acara').value = data.nama_acara;
    document.getElementById('edit-tanggal').value = data.tanggal_kegiatan;
    document.getElementById('edit-tahun').value = data.tahun;
    document.getElementById('editModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Close on outside click
window.onclick = function(event) {
    if (event.target == document.getElementById('editModal')) {
        closeModal();
    }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
