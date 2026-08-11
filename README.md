# Titip Cirebon

Fondasi development untuk website Titip Cirebon, bisnis jastip/open-PO produk dan makanan khas Cirebon untuk pelanggan di Sumedang.

Sprint 0 hanya menyiapkan environment Docker. Elementor, WooCommerce, theme tambahan, custom plugin, dan fitur bisnis belum dipasang atau dibuat.

## Stack

- WordPress 7.0.2, PHP 8.4 FPM
- MySQL 8.0
- Nginx 1.30.4 Alpine
- Docker Compose

Baseline awal meminta WordPress 7.0.3, tetapi rilis dan official Docker tag tersebut belum tersedia saat Sprint 0 dikerjakan. Project menggunakan exact official tag `wordpress:7.0.2-php8.4-fpm`, bukan mengganti ke floating tag atau WordPress 6.x.

Official image tersebut sudah menyediakan seluruh PHP extension yang dibutuhkan Sprint 0, termasuk `mbstring`, sehingga tidak ada layer extension tambahan yang perlu dipelihara.

## Architecture

```text
Browser -> Nginx -> WordPress PHP-FPM -> MySQL
```

Hanya Nginx yang dipublikasikan ke host. PHP-FPM dan MySQL hanya tersedia di internal Docker bridge network.

## Requirements

- Docker
- Docker Compose
- Git

PHP, MySQL, Nginx, Composer, dan Node.js tidak perlu dipasang di host.

## Installation

```bash
cp .env.example .env
```

Ganti seluruh nilai `change-me` di `.env`. Untuk development baru, gunakan password acak yang berbeda untuk MySQL root, user aplikasi, dan admin WordPress.

```bash
make up
make wp-install
```

Buka <http://localhost:8080>. Jika `WORDPRESS_PORT` diubah, gunakan port tersebut.

`make wp-install` aman dijalankan ulang. Instalasi kedua tidak membuat ulang database atau akun admin; command hanya memastikan URL, timezone Asia/Jakarta, dan pretty permalink tetap benar.

## Commands

```text
make up             Build dan jalankan semua service
make down           Hentikan service tanpa menghapus volume
make restart        Restart container
make ps             Tampilkan status container
make logs           Ikuti log semua service
make shell          Buka shell WordPress/PHP-FPM
make db-shell       Buka MySQL sebagai user aplikasi
make wp-install     Instal WordPress lokal secara idempoten
make db-backup      Buat backup database bertimestamp
make db-restore     Restore backup dengan konfirmasi nama database
```

## Backup

```bash
make db-backup
```

File dibuat sebagai `backups/db-YYYY-MM-DD-HHMMSS.sql`. File SQL diabaikan Git.

## Restore

```bash
make db-restore FILE=backups/db-YYYY-MM-DD-HHMMSS.sql
```

Restore meminta nama database aktif sebelum menimpa data. Untuk penggunaan non-interaktif yang disengaja:

```bash
make db-restore FILE=backups/example.sql CONFIRM=titip_cirebon
```

Gunakan restore hanya pada environment development yang sudah diperiksa. Nilai `CONFIRM` harus sama persis dengan `MYSQL_DATABASE` milik container aktif.

## Persistence and Reset

- `db_data` menyimpan seluruh data MySQL.
- `wordpress_data` menyimpan WordPress core, `wp-config.php`, plugins, themes, dan uploads.
- `docker compose down` atau `make down` mempertahankan kedua volume.
- `docker compose down -v` menghapus kedua volume beserta database, uploads, dan instalasi WordPress. Operasi ini bersifat destruktif.

Direktori `wordpress/wp-content/plugins` dan `wordpress/wp-content/themes` disiapkan untuk custom code pada sprint berikutnya. Sprint 0 belum memasang direktori tersebut ke runtime karena belum ada custom plugin/theme.

## PHP Development Configuration

PHP memiliki memory limit 512 MB, upload/post limit 64 MB, timeout 120 detik, dan OPcache aktif. `opcache.validate_timestamps=1` serta `opcache.revalidate_freq=0` membuat perubahan PHP terlihat tanpa restart container.

Default `.env.example` memakai `WP_ENVIRONMENT_TYPE=development` dan `WP_DEBUG=true`. Jangan gunakan konfigurasi debug ini di production. Production harus memakai secrets tersendiri, `WP_DEBUG=0`, HTTPS, dan konfigurasi deployment yang ditinjau ulang.

## Security Baseline

- Credentials hanya berasal dari `.env`, yang diabaikan Git.
- WordPress memakai dedicated MySQL user, bukan root.
- MySQL dan PHP-FPM tidak memiliki host port.
- Nginx menolak eksekusi PHP dari uploads.
- WordPress core auto-update dimatikan agar versi runtime mengikuti pinned Docker image; update dilakukan dengan perubahan image yang disengaja.

## Version Checks

```bash
docker compose exec wordpress php -r 'require "/var/www/html/wp-includes/version.php"; echo "$wp_version\n";'
docker compose exec wordpress php -v
docker compose exec db mysql --version
docker compose exec nginx nginx -v
docker compose version
```

MySQL 8.0 digunakan karena merupakan keputusan stack Sprint 0. Lifecycle dan target database production harus ditinjau kembali sebelum deployment production.
