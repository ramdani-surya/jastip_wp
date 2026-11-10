# Panduan CI/CD & Deployment — Titip Cirebon

Panduan ini menjelaskan cara kerja otomasi CI/CD menggunakan GitHub Actions dan pengelolaan baseline database untuk website **Titip Cirebon**.

---

## 1. Arsitektur Deployment & Database

### A. Fresh Installation (Day-0)
Ketika project di-clone di server baru atau komputer lokal:
1. Jalankan `cp .env.example .env` (atau `cp .env.prod.example .env.prod` untuk production).
2. Jalankan `make up` (atau `make prod-up`).
3. Jalankan `make wp-install`.
   - Script akan otomatis mendeteksi bahwa database masih kosong.
   - Mengimpor `database/seed.sql.gz` (seluruh layout Elementor, shortcode, dan pengaturan global langsung terpasang).
   - Mengestrak `database/uploads-seed.tar.gz` (aset media awal langsung tersedia).
   - Menjalankan `sync-domain.php` untuk menyelaraskan domain secara rekursif (termasuk JSON Elementor & PHP serialized data).
   - **Jaminan Nomor Pesanan**: Tabel pesanan dijamin bersih sehingga order pertama setelah instalasi **pasti dimulai dari `TC-YYYY-0001`**.

### B. Continuous Deployment (Day-2+)
Saat Anda melakukan push update kode:
* Pipeline CI/CD **hanya memperbarui kode** (`titip-cirebon-core`, Nginx, Docker configs).
* **Database live TIDAK PERNAH ditimpa**, sehingga seluruh pesanan riil WhatsApp, data pelanggan, dan log transaksi tetap aman.
* Sebelum update dimuat, sistem secara otomatis mengeksekusi `./scripts/backup.sh` sebagai jaring pengaman instan.

---

## 2. Alur Branching & Trigger Otomatis

| Branch | Target Environment | Pemicu (Trigger) |
|---|---|---|
| `staging` | **Staging Server** | Otomatis setiap `git push origin staging` |
| `main` | **Production Server** | Otomatis setiap `git push origin main` |
| *Bebas* | **Staging / Production** | Manual via GitHub Actions (`workflow_dispatch`) |

---

## 3. Konfigurasi GitHub Secrets & Environments

Untuk mengaktifkan deployment via SSH, buka repository di GitHub:
**Settings > Environments** (buat dua environment: `staging` dan `production`).

Tambahkan Secrets berikut pada masing-masing Environment:

| Secret Name | Wajib? | Deskripsi | Contoh |
|---|---|---|---|
| `SSH_HOST` | Ya | Alamat IP atau Domain Server | `103.123.45.67` atau `vps.titipcirebon.com` |
| `SSH_USER` | Ya | Akun SSH yang memiliki hak akses docker | `ubuntu` atau `deployer` |
| `SSH_PRIVATE_KEY` | Ya | Private SSH Key (format OpenSSH / PEM) | `-----BEGIN OPENSSH PRIVATE KEY-----...` |
| `SSH_PORT` | Opsional | Port SSH server (default: `22`) | `22` |
| `DEPLOY_PATH` | Opsional | Path folder project di server | `/opt/titip-cirebon` |
| `ENV_FILE` | **Disarankan** | Seluruh konfigurasi `.env` (diinjeksi ke RAM saat deploy, **0 byte file di disk**) | Salin seluruh teks dari `.env.prod` |

> [!TIP]
> **Keamanan Maksimal (In-Memory Injection / Zero Disk Footprint)**:
> Dengan mengisi Secret `ENV_FILE`, Anda **tidak perlu lagi membuat file `.env` atau `.env.prod` di disk VPS**.
> Pipeline deploy akan otomatis memuat variabel-variabel tersebut langsung ke memori RAM sesi Docker Compose melalui koneksi terenkripsi. Tidak ada file kredensial plaintext yang tersimpan di folder project server Anda!

Tambahkan juga Configuration Variable:

| Variable Name | Deskripsi | Contoh |
|---|---|---|
| `WORDPRESS_DOMAIN` | Domain atau IP untuk healthcheck (otomatis cek HTTPS & fallback ke HTTP) | `titipcirebon.com`, `staging.titipcirebon.com`, atau `103.123.45.67` |

> [!TIP]
> **Toleransi Protokol (HTTPS & HTTP Fallback)**:
> Automated healthcheck akan mencoba akses via `https://` terlebih dahulu (mengabaikan self-signed cert). Jika port 443 belum aktif atau SSL belum dipasang di VPS, script secara cerdas akan otomatis fallback mengecek via `http://`. Jika VPS belum memiliki domain atau sertifikat SSL, Anda juga dapat menyetel `WORDPRESS_PROTOCOL=http` di file `.env.prod` server Anda.

---

## 4. Cara Menjalankan Deployment Manual

Jika Anda ingin mendeploy branch tertentu tanpa perlu melakukan push:
1. Buka tab **Actions** di repository GitHub Anda.
2. Di sidebar kiri, klik workflow **CD Continuous Deployment**.
3. Klik tombol **Run workflow** di sebelah kanan.
4. Pilih:
   - **Use workflow from**: Branch sumber (misal `main` atau `staging`).
   - **Target Environment**: Pilih `staging` atau `production`.
   - **Skip Pre-Deploy Backup**: Biarkan `false` (sangat disarankan tetap mencadangkan DB).
5. Klik tombol hijau **Run workflow**.

---

## 5. Prosedur Rollback Cepat

Jika suatu saat deployment mengalami kendala di server:
1. Masuk ke server via SSH:
   ```bash
   ssh user@your-server-ip
   cd /opt/titip-cirebon
   ```
2. Jalankan restore dari backup otomatis terakhir yang dibuat sebelum deploy:
   ```bash
   # Lihat file backup terakhir
   ls -lt backups/db-*.sql | head -n 1

   # Jalankan restore
   ./scripts/restore.sh --db backups/db-YYYY-MM-DD-HHMMSS.sql
   ```
