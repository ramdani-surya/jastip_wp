# Rencana Pemulihan Bencana & Rollback (Disaster Recovery) — Titip Cirebon

Dokumen ini adalah panduan penanganan insiden darurat (*Incident Response*) jika terjadi kendala teknis, downtime, atau data corrupt pada server **Titip Cirebon** selama tahap peluncuran maupun operasional rutin.

---

## 1. Matriks Tingkat Keparahan (Severity Matrix)

| Level | Kondisi | Contoh Kasus | Target Respon |
|---|---|---|---|
| **P1 — Kritis** | Website tidak bisa diakses sama sekali oleh publik | Server mati, Docker crash, database corrupt, SSL error | < 15 menit |
| **P2 — Mayor** | Website jalan, tapi fitur kunci bermasalah | Tracking tidak menampilkan data, admin tidak bisa simpan order | < 1 jam |
| **P3 — Minor** | Kendala tampilan kosmetik / teks | Typo deskripsi, gambar lambat load, alignment tombol geser | < 1 hari |

---

## 2. Triase Cepat: Pengecekan Awal Kondisi Server

Saat mendapat laporan website tidak bisa diakses, segera login ke server via SSH lalu jalankan urutan diagnosa berikut:

### Langkah 1: Cek Status Kontainer Docker
```bash
cd /path/to/jastip_wp
make ps
# atau: docker compose ps
```
**Analisis Hasil**:
- Jika ada kontainer yang berstatus `Exited` atau `Restarting`, periksa log kontainer tersebut:
  ```bash
  make logs
  # atau cek spesifik kontainer: docker compose logs -f --tail=100 wordpress
  ```

### Langkah 2: Restart Service Standar
Jika kontainer hang atau memory penuh, lakukan restart aman:
```bash
# Untuk development:
make restart

# Untuk production:
docker compose -f compose.yaml -f docker-compose.prod.yml --env-file .env.prod restart
```

---

## 3. Prosedur Pemulihan Data Menggunakan Script `restore.sh`

Jika terjadi kesalahan fatal data (contoh: data terhapus tidak sengaja, database rusak, atau kegagalan update plugin), gunakan script pemulihan yang telah disiapkan di Sprint 9.

### Skenario A: Pemulihan Database Saja (Paling Sering Digunakan)
1. Periksa file backup database yang tersedia di folder `backups/`:
   ```bash
   ls -lh backups/db-*.sql
   ```
2. Pilih file backup terakhir sebelum insiden terjadi (contoh: `backups/db-2026-10-17-095502.sql`).
3. Jalankan script restore:
   ```bash
   ./scripts/restore.sh --db backups/db-2026-10-17-095502.sql
   # atau via Makefile:
   make restore ARGS="--db backups/db-2026-10-17-095502.sql"
   ```
4. **Konfirmasi Keamanan**:
   Sistem akan meminta konfirmasi dengan mengetikkan nama database target (misal: `titip_cirebon`).
   Ketik nama database tersebut lalu tekan `Enter`.
5. Tunggu proses selesai sampai muncul pesan:
   `✓ Database berhasil di-restore.`

### Skenario B: Pemulihan File Uploads Media Saja
Jika foto produk atau media di `wp-content/uploads/` hilang atau rusak:
```bash
./scripts/restore.sh --uploads backups/uploads-2026-10-17-095502.tar.gz
# atau:
make restore ARGS="--uploads backups/uploads-2026-10-17-095502.tar.gz"
```
Ketik `y` saat diminta konfirmasi untuk mengekstrak ulang arsip ke dalam folder uploads kontainer WordPress.

### Skenario C: Pemulihan Penuh (Database + Uploads Sekaligus)
```bash
./scripts/restore.sh \
  --db backups/db-2026-10-17-095502.sql \
  --uploads backups/uploads-2026-10-17-095502.tar.gz
```

---

## 4. Penanganan Error Spesifik

### Kasus 1: Error "502 Bad Gateway" di Browser
- **Penyebab**: Nginx berjalan normal, tetapi service PHP-FPM di kontainer WordPress mati atau tidak merespons request.
- **Solusi**:
  1. Periksa log kontainer WordPress:
     ```bash
     docker compose logs --tail=50 wordpress
     ```
  2. Jika PHP-FPM kehabisan memory (*Out Of Memory*), restart service wordpress:
     ```bash
     docker compose restart wordpress
     ```
  3. Pastikan memory limit di `docker/php/conf.d/wordpress.ini` sudah cukup (`memory_limit = 512M`).

### Kasus 2: Error "Error Establishing a Database Connection"
- **Penyebab**: Kontainer `db` mati, MySQL belum selesai startup, atau kredensial database salah.
- **Solusi**:
  1. Cek kesehatan kontainer MySQL:
     ```bash
     docker compose ps db
     ```
  2. Jika berstatus `unhealthy`, periksa log MySQL:
     ```bash
     docker compose logs --tail=50 db
     ```
  3. Cek kembali kecocokan password di file `.env` (atau `.env.prod`).

### Kasus 3: Sertifikat SSL Bermasalah / Expired
- **Penyebab**: File sertifikat SSL dari hosting panel kadaluarsa atau path salah.
- **Solusi**:
  1. Unduh sertifikat SSL baru dari cPanel/panel hosting Anda.
  2. Timpa file lama di folder:
     - `docker/nginx/certs/fullchain.crt`
     - `docker/nginx/certs/privkey.key`
  3. Reload Nginx tanpa downtime:
     ```bash
     docker compose exec nginx nginx -s reload
     ```

### Kasus 4: Rollback Kode Plugin atau Konfigurasi Git
Jika terjadi error setelah update kode:
```bash
# Cek riwayat commit terakhir:
git log -n 5 --oneline

# Kembalikan ke commit sebelumnya yang stabil:
git checkout <commit_hash>
# atau buang perubahan lokal yang bermasalah:
git restore .

# Restart kontainer untuk membaca file stabil:
make restart
```

---

## 5. Protokol Komunikasi Krisis (Customer Updates)

Jika insiden P1 (downtime total) terjadi di hari Jumat atau Sabtu saat pelanggan aktif memantau pesanan, jangan biarkan pelanggan panik. Gunakan template komunikasi darurat berikut melalui WhatsApp:

### Template Pesan WhatsApp:
> *"Halo Kak [Nama Pelanggan],*
> 
> *Mohon maaf, saat ini sistem pelacakan website Titip Cirebon sedang dalam pemeliharaan berkala selama beberapa menit ke depan.*
> 
> *Namun jangan khawatir, **seluruh pesanan Kakak tetap aman dan sedang kami proses/antarkan sesuai jadwal**. Jika Kakak membutuhkan update posisi pesanan secara langsung, silakan balas chat ini ya Kak.*
> 
> *Terima kasih banyak atas pengertiannya! 🙏"*

Dengan template ini, pelanggan merasa dihargai, operasional jastip tetap berjalan lancar secara manual, dan tim teknis memiliki waktu tenang untuk melakukan pemulihan tanpa tekanan komplain pembeli.
