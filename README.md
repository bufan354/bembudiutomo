# Sistem Arsip & Administrasi BEM

Sistem informasi berbasis web untuk pengelolaan administrasi, pengarsipan surat, dan pembuatan surat otomatis untuk organisasi Badan Eksekutif Mahasiswa.

## 🚀 Fitur Utama
- **Pembuatan Surat Otomatis**: Generate surat dalam format PDF/Cetak dengan tata letak standar organisasi.
- **Hybrid Database Support**: Berjalan lancar di MySQL (Hosting/InfinityFree) maupun PostgreSQL (Lokal/Supabase).
- **Manajemen Arsip**: Pencatatan surat masuk, surat keluar, dan lampiran secara sistematis.
- **Backup Sistem**: Fitur backup database murni PHP untuk memudahkan migrasi data.

## 🛠️ Persyaratan Sistem
- PHP 7.4 atau lebih baru (Direkomendasikan PHP 8.x)
- Database: MySQL atau PostgreSQL
- Web Server: Apache atau Nginx

## 📥 Cara Instalasi

### 1. Download/Clone
Buka terminal dan jalankan perintah:
```bash
git clone https://github.com/username/repo-name.git
cd repo-name
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

## 🚀 Deploy 
1. Unggah seluruh file (kecuali file yang ada di `.gitignore`) ke folder `htdocs`.
2. Buat database MySQL atau postgresSQL.
3. Import skema database Anda ke phpMyAdmin/mariaDB.
4. Sesuaikan file `.env` di server dengan data MySQL dari InfinityFree (gunakan template di `.env.example.mysql` atau `.env.example.pgsql`).
5. Selesai!

## 📄 Kontribusi
Dikembangkan oleh Bufan Fadhilah.
