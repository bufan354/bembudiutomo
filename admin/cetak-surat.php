<?php
// admin/cetak-surat.php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/config.php';

requireLogin();
requireSekretaris();

$id = (int)($_GET['id'] ?? 0);
$periode_id = getUserPeriode();

$surat = dbFetchOne("SELECT * FROM arsip_surat WHERE id = ? AND periode_id = ?", [$id, $periode_id], "ii");

if (!$surat) {
    die("Surat tidak ditemukan atau Anda tidak memiliki akses ke periode ini.");
}

$konten = json_decode($surat['konten_surat'], true) ?? [];

// Helper untuk format teks HTML supaya tebal otomatis menangani yang digarisbawahi oleh user (jika perlu)
$tujuan_html = nl2br(htmlspecialchars($surat['tujuan']));

// Mengambil Ketua BEM yg aktif untuk fallback TTD bawah
$ketua_bem = getKetua($periode_id);
$nama_ketua_bem = $ketua_bem['nama_lengkap'] ?? 'DEDE ANGGI MUHYIDIN';

// Ambil Pengaturan Tabel Tanda Tangan Tetap
$db_pengaturan = dbFetchAll("SELECT kunci, nilai FROM pengaturan");
$pengaturan = [];
foreach($db_pengaturan as $p) {
    if(trim($p['nilai']) !== '') $pengaturan[$p['kunci']] = $p['nilai'];
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Surat - <?php echo htmlspecialchars($surat['nomor_surat']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Reset & Setup Kertas A4 */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #525659; font-family: 'Times New Roman', Times, serif; font-size: 16px; color: #000; line-height: 1.5; }
        
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 15mm 20mm;
            margin: 10mm auto;
            border: 1px solid #D3D3D3;
            border-radius: 5px;
            background: white;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        /* Non-Printable Elements (Tombol Cetak) */
        .no-print {
            text-align: center;
            padding: 15px;
            background: #222;
            color: #fff;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .btn {
            background: #4A90E2; color: #fff; border: none; padding: 10px 20px; font-size: 16px;
            border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin: 0 5px;
        }
        .btn-warning { background: #f39c12; }
        
        /* Kop Surat Custom */
        .kop-surat {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 8px solid #1c3687; /* Garis tebal biru tua */
            padding-bottom: 5px;
            margin-bottom: 25px;
            position: relative;
        }
        .kop-surat::after {
            content: '';
            position: absolute;
            bottom: -15px; /* Sesuaikan jarak pita biru muda dr garis tebal */
            left: 0;
            width: 100%;
            height: 10px; /* Lebar pita biru muda */
            background-color: #688cc2;
            display: none; /* Matikan jika tak perlu */
        }
        .kop-logo {
            width: 120px;
            height: auto;
            margin-right: 15px;
        }
        .kop-teks {
            text-align: center;
            flex-grow: 1;
            padding: 0 10px;
            color: #000;
        }
        .kop-teks h1 { font-size: 26px; font-weight: 900; margin: 0; font-family: Arial, sans-serif; letter-spacing: 1px; }
        .kop-teks h2 { font-size: 34px; font-weight: 900; margin: 5px 0; font-family: 'Times New Roman', Times, serif; color: #1c3687; letter-spacing: 4px;}
        .kop-teks h4 { font-size: 16px; font-weight: bold; margin: 5px 0 0 0; color: #000; }
        .kop-teks .kop-alamat {
            background-color: #1c3687;
            color: white;
            padding: 4px 10px;
            font-size: 11px;
            margin-top: 5px;
            border-top-right-radius: 15px;
            border-bottom-right-radius: 15px;
            display: inline-block;
            font-family: Arial, sans-serif;
            font-weight: bold;
        }
        
        .kop-extra { 
            width: 130px; 
            text-align: right; 
            font-family: Arial, sans-serif; 
            font-size: 10px;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 5px;
        }
        .kop-extra img { width: 80px; height: 80px; margin-bottom: 2px;}
        .kop-extra .contact-item { display: flex; align-items: center; justify-content: flex-end; gap: 5px; font-weight: bold;}
        .kop-extra .contact-item i { font-size: 12px; }
        .kop-extra .contact-item.wa i { color: #25D366; }
        .kop-extra .contact-item.email i { color: #EA4335; }

        /* Meta Surat (Nomor, Hal, Tujuan) */
        .meta-surat { width: 100%; margin-bottom: 10px; line-height: 1.3; }
        .meta-surat td { vertical-align: top; }
        .col-label { width: 70px; }
        .col-titik { width: 15px; text-align: center; }

        /* Isi Surat */
        .isi-surat {
            text-align: justify;
            margin-bottom: 15px;
        }
        .indent { text-indent: 40px; margin-top: 5px; }
        
        /* Waktu Pelaksanaan Table */
        .waktu-pelaksanaan { width: calc(100% - 40px); margin-left: 40px; margin-top: 10px; margin-bottom: 10px; border-collapse: collapse; }
        .waktu-pelaksanaan td { vertical-align: top; padding: 4px 10px; border: none; }
        
        /* TTD Area */
        .ttd-area { width: 100%; margin-top: 25px; text-align: center; }
        .ttd-area .ttd-title { font-weight: bold; margin-bottom: 15px; }
        .ttd-table { width: 100%; margin-bottom: 10px; }
        .ttd-table td { width: 50%; vertical-align: top; padding-bottom: 10px; }
        .ttd-name { font-weight: bold; text-decoration: underline; margin-top: 55px; }
        .ttd-jabatan { font-size: 15px; }

        @page {
            size: A4 portrait;
            margin: 0;
        }

        @media print {
            body { background: white; margin: 0; padding: 0; }
            /* Reset visual screen-only traits tapi pertahankan dimensi padding A4 (20mm) */
            .page { 
                margin: 0; 
                padding: 15mm 20mm; 
                border: none; 
                border-radius: 0; 
                width: 210mm; 
                min-height: 297mm; 
                box-shadow: none; 
                background: white; 
                page-break-after: always; 
            }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button onclick="window.print()" class="btn"><i class="fas fa-print"></i> Cetak Dokumen</button>
        <button onclick="exportWord()" class="btn" style="background:#27ae60;"><i class="fas fa-file-word"></i> Download Word</button>
        <a href="arsip-surat.php" class="btn btn-warning"><i class="fas fa-arrow-left"></i> Kembali ke Arsip</a>
    </div>

    <div class="page">
        <!-- 1. KOP SURAT -->
        <?php 
        $kop_path = rtrim(UPLOAD_PATH, '/\\') . '/kop_surat.png';
        if (file_exists($kop_path)): 
            $kop_url = baseUrl('uploads/kop_surat.png') . '?v=' . filemtime($kop_path);
        ?>
            <div style="margin: -15mm -20mm -5px -20mm; text-align: center;">
                <img src="<?php echo htmlspecialchars($kop_url); ?>" style="width:100%; height:auto; display:block;" alt="Kop Surat">
            </div>
        <?php else: ?>
            <div class="kop-surat">
                <img src="<?php echo assetUrl('images/favicon/android-chrome-192x192.png'); ?>" class="kop-logo" alt="Logo">
                <div class="kop-teks">
                    <h1>BADAN EKSEKUTIF MAHASISWA</h1>
                    <h2>INSTBUNAS</h2>
                    <h4>SK No. 610/VIII/SK-BEM/INSTBUNAS/2024</h4>
                    <div class="kop-alamat">Jl. Siliwangi No. 121 (Jl. Raya Kadipaten - Majalengka) Heuleut - Kadipaten - Majalengka</div>
                </div>
                <div class="kop-extra">
                    <!-- QR Placeholder -->
                    <div style="width: 80px; height: 80px; background: white; border: 1px solid #000; display:flex; align-items:center; justify-content:center; padding:2px;">
                        <i class="fas fa-qrcode" style="font-size: 60px;"></i>
                    </div>
                    <div class="contact-item wa">
                        <i class="fab fa-whatsapp"></i> <span>083869304199</span>
                    </div>
                    <div class="contact-item email">
                        <i class="fas fa-envelope"></i> <span>beminstbunas@gmail.com</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- 2. META SURAT -->
        <table class="meta-surat">
            <tr>
                <td class="col-label">Nomor</td>
                <td class="col-titik">:</td>
                <td><?php echo htmlspecialchars($surat['nomor_surat']); ?></td>
                <td style="white-space: nowrap; width: 1%;"><?php echo htmlspecialchars($surat['tempat_tanggal']); ?></td>
            </tr>
            <tr>
                <td class="col-label">Lampiran</td>
                <td class="col-titik">:</td>
                <td>
                    <?php 
                    $jml_lampiran = !empty($konten['lampiran_files']) ? count($konten['lampiran_files']) : 0;
                    echo $jml_lampiran > 0 ? $jml_lampiran : '-';
                    ?>
                </td>
                <td></td>
            </tr>
            <tr>
                <td class="col-label">Perihal</td>
                <td class="col-titik">:</td>
                <td><b><u><?php echo htmlspecialchars($surat['perihal']); ?></u></b></td>
                <td style="padding-top: 10px;">
                    Yth,<br>
                    <b><?php echo nl2br(htmlspecialchars($surat['tujuan'])); ?></b><br>
                    Di Tempat
                </td>
            </tr>
        </table>

        <!-- 3. ISI SURAT -->
        <div class="isi-surat">
            <p><b><i>Assalamu'alaikum Wr. Wb.</i></b></p>
            
            <p class="indent">
                Puji syukur kita panjatkan kehadirat Allah SWT karena atas rahmat hidayah-Nya kita masih diberikan kesehatan dan selalu mendapatkan perlindungannya.
                <?php
                // Deteksi mode paragraf pembuka
                $nama_keg  = trim($konten['nama_kegiatan'] ?? '');
                $tema_keg  = trim($konten['tema'] ?? '');
                $custom    = trim($konten['tema_kegiatan'] ?? '');

                // Ambil tahun dari nomor surat (bagian terakhir: 001/L/BEMCUP/BEM/IV/2026)
                $parts_nomor = explode('/', $surat['nomor_surat']);
                $tahun_surat = end($parts_nomor) ?: date('Y');

                if (!empty($nama_keg)) {
                    // Mode template: generate dari nama_kegiatan + tema (Bolding the nama_keg and tema_keg)
                    $pembuka = 'Sehubungan akan diadakannya kegiatan <b>'
                        . htmlspecialchars($nama_keg) . '</b> Tahun ' . htmlspecialchars($tahun_surat)
                        . (!empty($tema_keg) ? ' dengan tema "<b>' . htmlspecialchars($tema_keg) . '</b>"' : '')
                        . ' yang akan dilaksanakan pada :';
                } elseif (!empty($custom)) {
                    // Mode custom: izinkan tag format dasar dari rich text editor
                    $pembuka = strip_tags($custom, '<b><strong><i><em><u>');
                } else {
                    $pembuka = '';
                }
                echo $pembuka;
                ?>
            </p>

            <table class="waktu-pelaksanaan">
                <tr>
                    <td style="width: 120px;">Hari, tanggal</td>
                    <td style="width: 15px;">:</td>
                    <td><?php echo htmlspecialchars($konten['pelaksanaan_hari_tanggal'] ?? ''); ?></td>
                </tr>
                <tr>
                    <td>Waktu</td>
                    <td>:</td>
                    <td><?php echo htmlspecialchars($konten['pelaksanaan_waktu'] ?? ''); ?></td>
                </tr>
                <tr>
                    <td>Tempat</td>
                    <td>:</td>
                    <td><?php echo htmlspecialchars($konten['pelaksanaan_tempat'] ?? ''); ?></td>
                </tr>
            </table>

            <?php
            // Paragraf Permohonan: generate dinamis dengan akhiran cerdas
            $tujuan_baris_pertama = trim(explode("\n", $surat['tujuan'])[0]);
            $konteks_text         = trim($konten['konteks'] ?? '');
            
            // Tentukan "buntut" kalimat (suffix) secara pintar
            if (!empty($konteks_text)) {
                // Konteks menggantikan kalimat akhiran sepenuhnya
                $suffix = ' ' . $konteks_text . '.';
            } else {
                // Suffix otomatis berdasarkan Perihal
                $perihal_lower = strtolower($surat['perihal']);
                if (strpos($perihal_lower, 'undangan') !== false) {
                    $suffix = ' agar dapat menghadiri kegiatan tersebut.';
                } else if (strpos($perihal_lower, 'peminjaman') !== false || strpos($perihal_lower, 'permohonan tempat') !== false) {
                    $suffix = ' untuk dapat menggunakan fasilitas tersebut.';
                } else if (strpos($perihal_lower, 'delegasi') !== false || strpos($perihal_lower, 'utusan') !== false) {
                    $suffix = ' untuk mendelegasikan perwakilannya pada kegiatan tersebut.';
                } else {
                    $suffix = ' demi mendukung terselenggaranya acara tersebut.';
                }
            }

            $sapaan = !empty($konten['sapaan_tujuan']) ? $konten['sapaan_tujuan'] . ' ' : '';
            $paragraf_permohonan  = 'Dengan ini kami menyampaikan '
                . strtolower($surat['perihal'])
                . ' kepada '
                . $sapaan
                . $tujuan_baris_pertama
                . $suffix;
            ?>
            <p class="indent"><?php echo htmlspecialchars($paragraf_permohonan); ?></p>
            
            <?php
            // Paragraf Penutup: dinamis (mengikuti perihal)
            $paragraf_penutup = 'Demikian surat ' . $surat['perihal'] . ' ini kami sampaikan, atas perhatian dan kerjasamanya kami ucapkan terimakasih.';
            ?>
            <p class="indent"><?php echo htmlspecialchars($paragraf_penutup); ?></p>
            
            <p style="margin-top: 15px;"><b><i>Wassalamu'alaikum Wr. Wb.</i></b></p>
        </div>

        <!-- 4. TANDA TANGAN -->
        <div class="ttd-area">
            <?php 
            $kode_keg = "";
            $parts = explode('/', $surat['nomor_surat']);
            if (isset($parts[2])) $kode_keg = $parts[2];
            $nama_panitia = !empty($konten['nama_kegiatan']) ? $konten['nama_kegiatan'] : $kode_keg;
            ?>
            <div class="ttd-title">PANITIA PELAKSANA <?php echo strtoupper(htmlspecialchars($nama_panitia)); ?></div>
            
            <table class="ttd-table" style="margin-bottom: 5px;">
                <tr>
                    <td style="position:relative;">
                        Ketua Pelaksana
                        <?php if(!empty($pengaturan['cap_panitia_image']) && ($konten['use_cap_panitia'] ?? '1') === '1'): ?>
                            <img src="<?php echo uploadUrl($pengaturan['cap_panitia_image']); ?>" style="position:absolute; top:20px; left:100%; transform:translateX(-50%); max-width:190px; max-height:95px; mix-blend-mode:multiply; pointer-events:none; opacity:0.85; z-index:2;">
                        <?php endif; ?>
                        <?php if(!empty($konten['panitia_ketua_ttd'])): ?>
                            <img src="<?php echo htmlspecialchars($konten['panitia_ketua_ttd']); ?>" style="position:absolute; top:15px; left:50%; transform:translateX(-50%); max-height:80px; mix-blend-mode:multiply; pointer-events:none;">
                        <?php endif; ?>
                        <div class="ttd-name"><?php echo htmlspecialchars($konten['panitia_ketua'] ?? ''); ?></div>
                    </td>
                    <td style="position:relative;">
                        Sekretaris
                        <?php if(!empty($konten['panitia_sekretaris_ttd'])): ?>
                            <img src="<?php echo htmlspecialchars($konten['panitia_sekretaris_ttd']); ?>" style="position:absolute; top:15px; left:50%; transform:translateX(-50%); max-height:80px; mix-blend-mode:multiply; pointer-events:none;">
                        <?php endif; ?>
                        <div class="ttd-name"><?php echo htmlspecialchars($konten['panitia_sekretaris'] ?? ''); ?></div>
                    </td>
                </tr>
            </table>

            <div style="margin-top: -10px; margin-bottom: 10px;">Mengetahui,</div>

            <table class="ttd-table">
                <tr>
                    <td style="position:relative;">
                        a.n Rektor INSTBUNAS Majalengka<br>
                        <span class="ttd-jabatan"><?php echo htmlspecialchars($pengaturan['ttd_warek_jabatan'] ?? 'WAREK III Bid. Kemahasiswaan'); ?></span>
                        <?php if(!empty($pengaturan['cap_warek_image']) && ($konten['use_cap_warek'] ?? '1') === '1'): ?>
                            <img src="<?php echo uploadUrl($pengaturan['cap_warek_image']); ?>" style="position:absolute; bottom:20px; left:0; max-width:180px; max-height:130px; mix-blend-mode:multiply; pointer-events:none; opacity:0.85; z-index:2;">
                        <?php endif; ?>
                        <?php if(!empty($pengaturan['ttd_warek_image']) && ($konten['use_ttd_warek'] ?? '1') === '1'): ?>
                            <img src="<?php echo uploadUrl($pengaturan['ttd_warek_image']); ?>" style="position:absolute; top:40px; left:50%; transform:translateX(-50%); max-height:80px; mix-blend-mode:multiply; pointer-events:none;">
                        <?php endif; ?>
                        <div class="ttd-name"><?php echo htmlspecialchars($pengaturan['ttd_warek_name'] ?? 'II MUHAMAD MISBAH, S.Pd.I., SE., MM.'); ?></div>
                    </td>
                    <td style="position:relative;">
                        Ketua BEM<br>
                        <span class="ttd-jabatan"><?php echo htmlspecialchars(trim(str_ireplace('Ketua BEM', '', $pengaturan['ttd_presma_jabatan'] ?? 'INSTBUNAS Majalengka'))); ?></span>
                        <?php if(!empty($pengaturan['cap_presma_image']) && ($konten['use_cap_presma'] ?? '1') === '1'): ?>
                            <img src="<?php echo uploadUrl($pengaturan['cap_presma_image']); ?>" style="position:absolute; bottom:20px; left:10%; max-width:180px; max-height:130px; mix-blend-mode:multiply; pointer-events:none; opacity:0.85; z-index:2;">
                        <?php endif; ?>
                        <?php if(!empty($pengaturan['ttd_presma_image']) && ($konten['use_ttd_presma'] ?? '1') === '1'): ?>
                            <img src="<?php echo uploadUrl($pengaturan['ttd_presma_image']); ?>" style="position:absolute; top:40px; left:50%; transform:translateX(-50%); max-height:80px; mix-blend-mode:multiply; pointer-events:none;">
                        <?php endif; ?>
                        <div class="ttd-name"><?php echo htmlspecialchars($pengaturan['ttd_presma_name'] ?? $nama_ketua_bem); ?></div>
                    </td>
                </tr>
            </table>
        </div>

    </div>

    <!-- Script Print Viewport -->
    <script>
        // Opsional: otomatis buka pop-up print saat baru di-load jika parameter ?print=true
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('print')) {
            window.print();
        }

        // Export ke Word (.doc)
        function exportWord() {
            var clone = document.querySelector('.page').cloneNode(true);
            
            // Tambahkan CSS inline dasar agar mirip
            var css = `
                <style>
                    body { font-family: 'Times New Roman', Times, serif; font-size: 12pt; color: #000; }
                    table { border-collapse: collapse; }
                    .indent { text-indent: 40px; margin-top: 8px; }
                    .ttd-table td { width: 50%; vertical-align: top; }
                    .meta-surat { width: 100%; margin-bottom: 20px; line-height: 1.4; }
                    .meta-surat td { vertical-align: top; }
                    .col-label { width: 70px; }
                    .col-titik { width: 15px; text-align: center; }
                    .isi-surat { text-align: justify; }
                </style>
            `;

            var preHtml = "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'><head><meta charset='utf-8'><title>Surat Export</title>" + css + "</head><body>";
            var postHtml = "</body></html>";
            
            var html = preHtml + clone.outerHTML + postHtml;

            var blob = new Blob(['\ufeff', html], {
                type: 'application/msword'
            });
            
            // Nama file dari nomor surat
            var filename = '<?php echo addslashes(htmlspecialchars($surat['nomor_surat'])); ?>'.replace(/[^a-zA-Z0-9]/g, '_').toLowerCase();
            filename = filename ? filename + '.doc' : 'document.doc';
            
            var url = 'data:application/vnd.ms-word;charset=utf-8,' + encodeURIComponent('\ufeff' + html);
            
            var downloadLink = document.createElement("a");
            document.body.appendChild(downloadLink);
            
            if (navigator.msSaveOrOpenBlob) {
                navigator.msSaveOrOpenBlob(blob, filename);
            } else {
                downloadLink.href = url;
                downloadLink.download = filename;
                downloadLink.click();
            }
            
            document.body.removeChild(downloadLink);
        }
    </script>
</body>
</html>
