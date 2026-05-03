# Sistem Arsip & Administrasi BEM

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://www.php.net/)

Sistem informasi berbasis web untuk pengelolaan administrasi, pengarsipan surat, manajemen rundown acara, dan sinkronisasi logistik organisasi Badan Eksekutif Mahasiswa.

## 🚀 Fitur Utama
- **Pembuatan Surat & Lampiran Otomatis**: Generate surat formal dan lampiran logistik dalam format PDF/Cetak dengan tata letak standar organisasi.
- **Manajemen Rundown Acara**: Modul pembuatan susunan acara dinamis dengan fitur kalkulasi waktu otomatis, dukungan kegiatan paralel, dan *auto-pagination* saat cetak PDF.
- **Dynamic Inventory Sync**: Integrasi real-time antara data master barang/tempat dengan modul cetak, memastikan satuan dan nama barang selalu akurat tanpa input manual berulang.
- **Mobile Responsive Layout**: Antarmuka dashboard dan manajemen arsip yang dioptimalkan sepenuhnya untuk perangkat seluler (Mobile-First Design).
- **Hybrid Database Support**: Berjalan lancar di MySQL (Hosting/InfinityFree) maupun PostgreSQL (Lokal/Supabase).
- **Manajemen Arsip Terpusat**: Pencatatan surat masuk, keluar, rundown, dan lampiran secara sistematis dengan fitur duplikasi data.
- **Keamanan & Log**: Dilengkapi dengan CSRF Protection, Audit Logging, dan manajemen periode kepengurusan.

## 🛠️ Persyaratan Sistem
- PHP 7.4 atau lebih baru (Direkomendasikan PHP 8.x)
- Database: MySQL atau PostgreSQL
- Web Server: Apache atau Nginx

## 📥 Cara Instalasi

### 1. Download/Clone
Buka terminal dan jalankan perintah:
```bash
git clone https://github.com/bufan354/bembudiutomo.git
cd bembudiutomo
```

### 2. Konfigurasi Lingkungan
Ubah nama file `.env.example` menjadi `.env`, lalu sesuaikan kredensial database Anda:

- **Untuk Lokal (PostgreSQL/Supabase)**: Salin isi dari `.env.example.pgsql` ke `.env`.
- **Untuk Hosting (MySQL/InfinityFree)**: Salin isi dari `.env.example.mysql` ke `.env`.

### 3. Setup Folder Upload
Sistem akan otomatis membuat folder `uploads/` dan sub-foldernya saat pertama kali dijalankan. Pastikan web server memiliki izin untuk menulis (Write Permission) di folder root.

## ⚙️ Konfigurasi File Penting
- `.env`: File utama untuk pengaturan database dan URL.
- `config/database.php`: Logika koneksi database hybrid.
- `includes/functions.php`: Fungsi pembantu sistem dan keamanan.
- `admin/cetak-surat.php`: Template tata letak surat (Kop, TTD, Margin).
- `admin/cetak-rundown.php`: Logika manajemen susunan acara responsif.

## 🚀 Deploy 
1. Unggah seluruh file (kecuali file yang ada di `.gitignore`) ke folder `htdocs`.
2. Buat database MySQL atau postgresSQL.
3. Import skema database Anda ke phpMyAdmin/mariaDB.
4. Sesuaikan file `.env` di server dengan data MySQL dari InfinityFree.
5. Selesai!

## 📄 Kontribusi
Dikembangkan oleh Bufan Fadhilah.

## ⚖️ Lisensi
Proyek ini dilisensikan di bawah **MIT License**. Silakan lihat file `LICENSE` untuk informasi lebih lanjut.

