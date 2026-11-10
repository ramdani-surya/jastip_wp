# Baseline Database & Media Seed

Direktori ini berisi data awal (*seed data*) untuk inisialisasi pertama kali (Day-0) pada environment lokal baru, staging, maupun production.

## File Terkelola
- `seed.sql.gz`: Dump database MySQL terkompresi yang sudah disanitasi dari order dummy, auto-draft, dan cache transients.
- `uploads-seed.tar.gz`: Arsip media `wp-content/uploads/` berisi aset gambar awal untuk Elementor dan homepage.

## Cara Kerja
Saat `make wp-install` dijalankan:
1. Jika database kosong (`!is_blog_installed()`):
   - Sistem akan mengimpor `seed.sql.gz`.
   - Mengestrak `uploads-seed.tar.gz` jika `wp-content/uploads/` belum ada.
   - Menjalankan penyesuaian domain & serialisasi (`sync-domain.php`) sesuai URL di `.env` (misal dari `http://localhost:9090` ke domain target).
   - Memastikan jaminan urutan nomor pesanan awal **selalu dimulai dari `0001`**.
2. Jika database sudah terinstal:
   - Data live tidak ditimpa, hanya memperbarui konfigurasi lokal/URL bila diperlukan.

## Cara Regenerasi Seed (Khusus Developer)
Jika ada pembaruan desain template global Elementor yang ingin dijadikan default seed baru:
```bash
# 1. Bersihkan order dummy & transients
docker compose exec -T wordpress php < scratch/sanitize-db.php

# 2. Re-dump database ke seed.sql.gz
docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysqldump --user="$MYSQL_USER" --single-transaction --quick --lock-tables=false --no-tablespaces --default-character-set=utf8mb4 "$MYSQL_DATABASE"' | gzip -9 > database/seed.sql.gz

# 3. Re-archive uploads ke uploads-seed.tar.gz
docker compose exec -T wordpress tar --create --gzip --file=- --directory=/var/www/html/wp-content uploads > database/uploads-seed.tar.gz
```
