# Titip Cirebon

Fondasi development untuk website Titip Cirebon, bisnis jastip/open-PO produk dan makanan khas Cirebon untuk pelanggan di Sumedang.

Sprint 0 menyiapkan environment Docker. Sprint 1B menambahkan fondasi visual Elementor tanpa membuat homepage, katalog, order system, atau fitur bisnis.

## Stack

- WordPress 7.0.3, PHP 8.4 FPM
- MySQL 8.0
- Nginx 1.30.4 Alpine
- Elementor 4.2.2 dan Elementor Pro 4.0.4
- Hello Elementor 3.4.9
- Docker Compose

Official Docker tag `wordpress:7.0.3-php8.4-fpm` belum tersedia saat Sprint 0.1 dikerjakan. Project karena itu membangun image `titip-cirebon-wordpress:7.0.3-php8.4-fpm` secara reproducible dari baseline `wordpress:7.0.2-php8.4-fpm` yang dipin sampai image digest dan tarball resmi WordPress 7.0.3. URL versi, SHA-1 resmi, dan SHA-256 tarball dipin di Docker build; versi embedded juga diverifikasi sebelum image selesai dibangun.

Base image tersebut tetap menyediakan PHP 8.4 dan seluruh PHP extension yang dibutuhkan Sprint 0, termasuk `mbstring`, sehingga stack PHP tidak berubah.

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
make wp-upgrade     Jalankan prosedur database upgrade resmi WordPress
make wp-verify-core Verifikasi versi dan checksum seluruh file core
make design-system  Terapkan Titip Cirebon design system secara idempoten
make design-system-verify Verifikasi design tokens, styles, dan preview page
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

## Modern Local Food Design System

Jalankan setup setelah Elementor Free, Elementor Pro, dan Hello Elementor aktif:

```bash
make db-backup
make design-system
make design-system-verify
```

Setup memakai Elementor Document API dan aman dijalankan ulang. Active Kit ID 11 diperbarui tanpa menghapus setting lain. Halaman terpublikasi `Design System Preview` tersedia di <http://localhost:8080/design-system-preview/> dengan template Elementor Canvas dan tidak dijadikan homepage.

### Design Tokens

Global colors:

```text
Primary       #B45309    Primary Dark  #78350F
Secondary     #0F766E    Accent        #F59E0B
Background    #FFFDF8    Surface       #FFFFFF
Text          #292524    Text Muted    #78716C
Border        #E7E5E4    Success       #15803D
Warning       #B45309    Error         #B91C1C
```

Heading memakai Poppins 600/700. Body, labels, dan buttons memakai Inter 400/500/600 dengan system-font fallback dan `font-display: swap`. Content width maksimum 1200px; horizontal padding desktop/tablet/mobile adalah 24/20/16px; section spacing adalah 80/64/48px.

### Component Classes

Utility styles disimpan pada blok bertanda di WordPress Additional CSS sehingga setup dapat mengganti blok milik project tanpa menimpa CSS lain:

```text
.tc-section                 Standard section spacing
.tc-section--compact        Compact section spacing
.tc-card                    Surface card
.tc-card--elevated          Elevated card shadow
.tc-product-card            Product card foundation
.tc-badge                   Badge foundation and color variants
.tc-button--secondary       Secondary Elementor button
.tc-form                    Form layout and focus behavior
.tc-local-accent            Subtle local color accent
```

Gunakan Global Colors dan Global Fonts untuk widget Elementor baru. Gunakan utility class hanya untuk pola komponen yang tidak tersedia sebagai Site Setting. Jangan menambahkan inline CSS per widget atau membuat salinan blok Additional CSS secara manual.

Untuk perubahan design system berikutnya, ubah declarative settings, preview elements, atau controlled CSS di `docker/php/setup-design-system.php`, jalankan kedua command di atas, lalu validasi desktop, tablet, dan mobile sebelum commit.

## Security Baseline

- Credentials hanya berasal dari `.env`, yang diabaikan Git.
- WordPress memakai dedicated MySQL user, bukan root.
- MySQL dan PHP-FPM tidak memiliki host port.
- Nginx menolak eksekusi PHP dari uploads.
- WordPress core auto-update dimatikan agar versi runtime mengikuti pinned Docker image; update dilakukan dengan perubahan image yang disengaja.

## WordPress Core Updates

Image menyimpan distribusi WordPress versi-pinned di `/usr/src/wordpress`. Pada startup pertama setelah perubahan versi, entrypoint project mengganti hanya file core di named volume `wordpress_data`; `wp-content`, `wp-config.php`, `.htaccess`, dan database tidak disentuh. Sinkronisasi tidak mengunduh file dan tidak menjalankan `wp core update` saat startup.

Prosedur security update berikutnya:

1. Pastikan tag official yang tepat tersedia, atau perbarui base image beserta digest, `WORDPRESS_VERSION`, URL versi, SHA-1 resmi, dan SHA-256 di `compose.yaml` serta `docker/php/Dockerfile`.
2. Perbarui `target_version` di `docker/php/wordpress-entrypoint.sh` dan expected version pada target `wp-verify-core`.
3. Jalankan `make db-backup`, lalu `make up`.
4. Jalankan `make wp-upgrade` dan `make wp-verify-core`.
5. Validasi admin, permalink, upload, dan persistence sebelum commit.

`make wp-verify-core` membandingkan file runtime terhadap manifest SHA-256 yang dibuat dari tarball terverifikasi saat build. Perbedaan file, file hilang, atau versi selain versi pinned membuat command gagal.

## Version Checks

```bash
docker compose exec wordpress php -r 'require "/var/www/html/wp-includes/version.php"; echo "$wp_version\n";'
docker compose exec wordpress php -v
docker compose exec db mysql --version
docker compose exec nginx nginx -v
docker compose version
make wp-verify-core
```

MySQL 8.0 digunakan karena merupakan keputusan stack Sprint 0. Lifecycle dan target database production harus ditinjau kembali sebelum deployment production.
