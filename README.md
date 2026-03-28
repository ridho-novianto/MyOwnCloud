# MyOwnCloud - Personal Workspace

MyOwnCloud adalah aplikasi *cloud* pribadi super ringan dan cepat yang dibangun menggunakan **PHP Native** tanpa *framework*. Aplikasi ini menggabungkan manajemen file ala Google Drive, manajemen tugas (To-Do List/Checklist) dengan dukungan *deadline*, penyimpanan tautan (*Link Manager*), dan notifikasi cerdas (*Push Notification*) ke *smartphone*. Berjalan sangat ringan bahkan di VPS dengan RAM 1GB.

## Fitur Utama

- **File Manager (G-Drive Style):** *Drag-and-Drop file* dan *folder*, navigasi bersarang (*nested folders*), *preview* visual langsung, filter tipe file, dan kuota penyimpanan *unlimited* khusus admin (membaca memori asli server).
- **Task Manager:** Pencatatan tugas berprioritas, status, kategori tag, dan penanda waktu tenggat (*deadline*).
- **Link Manager:** Menyimpan tautan penting (seperti *link tree*) dengan pelacakan jumlah klik, pengaturan kategori, dan *pinned links*.
- **Push Notifications (PWA):** Berjalan di latar belakang. Memberikan peringatan *deadline* secara otomatis (7 hari sebelum) langsung ke layar Android, Windows, Mac, maupun iOS (via Safari PWA) walaupun web sedang ditutup.
- **UI/UX Dark Neon:** Desain keren yang memanjakan mata, responsif (mendukung *mobile* dan tablet), dan minimalis.
- **Dashboard & Visualisasi:** Ringkasan seluruh aktivitas, daftar pekerjaan mendesak, dan grafik status (menggunakan Chart.js).

---

## Prasyarat Server (VPS)

- **OS:** Ubuntu/Debian (disarankan)
- **Web Server:** Nginx (disarankan) atau Apache
- **Database:** MariaDB (10.11+) atau MySQL (8.0+)
- **PHP:** Versi 8.1 atau lebih baru (8.3 direkomendasikan) beserta ekstensi: `pdo_mysql`, `gd`, `curl`, `mbstring`, `gmp` (opsional untuk *web push*).
- **Composer** (khusus jika menggunakan *Web Push Notifications*).

---

## Panduan Instalasi (Setup Lengkap)

### 1. Kloning & Pindahkan ke Web Root

Pastikan letak semua *source code* MyOwnCloud berada di direktori publik server web Anda (contohnya `/var/www/html/myowncloud`).

### 2. Konfigurasi Database

1. Buat database baru di MySQL/MariaDB.
    ```bash
    mysql -u root -p -e "CREATE DATABASE myowncloud CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    ```
2. Buat user dan berikan hak akses:
    ```bash
    mysql -u root -p -e "CREATE USER 'myowncloud'@'localhost' IDENTIFIED BY 'PasswordKuatAnda'; GRANT ALL PRIVILEGES ON myowncloud.* TO 'myowncloud'@'localhost'; FLUSH PRIVILEGES;"
    ```
3. Impor struktur tabel (`schema.sql`) ke dalam database `myowncloud`:
    ```bash
    mysql -u root -p myowncloud < schema.sql
    ```
   *(Secara otomatis, skema tersebut sudah menciptakan akun **Admin** dengan email `admin@myowncloud.local` dan password `admin123`)*

### 3. Konfigurasi Aplikasi (`config.php`)

Ganti atau edit konfigurasi di dalam file `config.php`:

```php
// Sesuaikan dengan kredensial database yang tadi dibuat
define('DB_HOST', 'localhost');
define('DB_NAME', 'myowncloud');
define('DB_USER', 'myowncloud');
define('DB_PASS', 'PasswordKuatAnda');

// Ganti APP_URL sesuai dengan domain aktif atau IP Anda!
define('APP_URL', 'https://domain.anda/myowncloud');
```

### 4. Setup Izin Folder (Permissions)

Direktori tempat menyimpan *file upload* wajib dimiliki dan dapat ditulis oleh *user* dari *web server* (biasanya `www-data` di Ubuntu/Debian).

```bash
mkdir -p uploads uploads/avatars assets/icons
sudo chown -R www-data:www-data uploads assets/icons
sudo chmod -R 755 uploads assets/icons
```

### 5. Atur Batasan Upload Nginx & PHP (File Besar)

Agar *File Manager* dapat mengunggah file-file berat (misal di atas 2 MB), Anda perlu mengatur limit *upload* pada konfigurasi `php.ini` PHP-FPM Anda dan server `nginx.conf`:

**Edit `php.ini`** (Biasanya di `/etc/php/8.3/fpm/php.ini`):
```ini
upload_max_filesize = 100M
post_max_size = 100M
max_file_uploads = 30
memory_limit = 256M
max_execution_time = 300
```

**Edit konfigurasi Nginx Web Block Anda:**
Arahkan alias atau `root` ke folder, lalu sisipkan skrip anti-bocor seperti contoh yang ada di file `nginx.conf` di repositori ini, pastikan juga terdapat perintah ini di blok Nginx Anda:
```nginx
client_max_body_size 100M;
```
*(Jangan lupa restart PHP-FPM dan Nginx setelahnya)*

### 6. Aktifkan Push Notification Cerdas (Notifikasi Otomatis)

MyOwnCloud dapat mengirim *Push Notification* (tanpa membakar API limit dari layanan eksternal) menggunakan web standar.

1. Install *dependency array* VAPID dengan Composer:
   ```bash
   composer require minishlink/web-push
   ```
2. Anda harus merancang sepasang "VAPID Keys" menggunakan generator dan menuliskannya di bawah file `config.php`. Eksekusi generator web-push ini (via `vendor/bin/vapid`):
   ```php
   define('VAPID_PUBLIC_KEY', 'Public_Key_Anda_Di_Sini');
   define('VAPID_PRIVATE_KEY', 'Private_Key_Anda_Di_Sini');
   define('VAPID_SUBJECT', 'mailto:admin@domainanda.com');
   ```
3. Supaya server bisa memeriksa *deadline tasks* dan mengirim notifikasi pada latar belakang, jadwalkan pekerjaan (*Cron Job*) melalui Linux:
   Buka *crontab*: `crontab -e`
   Tambahkan eksekusi otomatis yang menjadwalkan pengecekan tepat di jam 09:00 pagi setiap harinya:
   ```bash
   0 9 * * * /usr/bin/php /var/www/html/myowncloud/cron/notify_deadlines.php >> /var/log/myowncloud_cron.log 2>&1
   ```

---

## Memulai Penggunaan
1. Buka laman *web* di `https://domain.anda/myowncloud`.
2. Masukkan akun *default*:
   - Email: `admin@myowncloud.local`
   - Password: `admin123`
3. Silakan ubah alamat email dan *password* dari laman Profil Anda segera setelah berhasil *login*. 
4. Segera atur SSL (`https`) karena fitur PWA dan Service Worker (Notifikasi Push/Install Web Mobile) **HANYA BEKERJA** saat dijalankan di `https://` atau `localhost`.
