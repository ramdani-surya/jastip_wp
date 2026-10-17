# Launch Readiness Checklist — Titip Cirebon (Sprint 10)

Dokumen ini adalah panduan Quality Assurance (QA) pra-peluncuran untuk memverifikasi bahwa seluruh komponen teknis, fungsional, keamanan, dan infrastruktur sistem **Titip Cirebon** siap digunakan secara publik.

Gunakan format checklist `- [ ]` ini untuk menandai setiap item yang telah lolos pengujian.

---

## 1. Smoke Testing (Fungsionalitas Dasar)

Pengujian fungsi utama halaman publik dan alur navigasi pengguna.

- [ ] **Homepage (`/`)**:
  - [ ] Hero section tampil rapi, judul dan tagline jelas terbaca.
  - [ ] Tombol CTA utama (misal: "Lihat Menu Minggu Ini" / "Pesan Sekarang") mengarahkan ke halaman atau section yang benar.
  - [ ] Bagian informasi alur pemesanan (Cara Order) jelas dan informatif.
  - [ ] Footer menampilkan informasi operasional jastip dan kontak admin dengan benar.
- [ ] **Katalog & Penawaran Mingguan (`/katalog-mingguan/` atau section penawaran)**:
  - [ ] Daftar produk/menu open PO mingguan muncul dengan foto yang tajam dan tidak pecah.
  - [ ] Harga produk, deskripsi singkat, dan kategori produk (Jajanan, Makanan, Oleh-oleh, dll.) ditampilkan dengan benar.
  - [ ] Status ketersediaan (Stok Tersedia / Hampir Habis / Tutup PO) sesuai dengan data di admin panel.
- [ ] **WhatsApp CTA Buttons**:
  - [ ] Setiap tombol pesan WhatsApp di produk memicu link `https://wa.me/<nomor_admin>?text=...` dengan nomor admin aktif yang benar.
  - [ ] Teks pesan pembuka WhatsApp sudah terisi otomatis (*prefilled*) dengan nama produk atau format order yang rapi (tidak berantakan saat dibuka di WhatsApp Web maupun Mobile).
- [ ] **Navigasi Navbar & Menu Header**:
  - [ ] Semua link menu (Produk, Cara Order, Lacak Pesanan, Tentang Kami) mengarah ke URL aktif (port dan domain sesuai, tidak mengarah ke `localhost:8080` lama).

---

## 2. Responsive & Accessibility (a11y) Testing

Memastikan kenyamanan akses pengguna perangkat mobile (mayoritas pelanggan jastip mengakses via smartphone).

- [ ] **Mobile Viewport Standar (390 × 844 px — iPhone 12/13/14/15)**:
  - [ ] Tidak ada *horizontal scrolling* (overflow horizontal) di seluruh halaman.
  - [ ] Ukuran teks judul, paragraf, dan harga tetap proporsional dan mudah dibaca tanpa perlu *pinch-to-zoom*.
  - [ ] Sticky header atau menu hamburger dapat dibuka dan ditutup dengan mulus tanpa menutupi konten penting.
- [ ] **Viewport Layar Kecil (360 × 800 px — Android Umum)**:
  - [ ] Layout grid produk otomatis menyesuaikan (1 atau 2 kolom) tanpa tumpang-tindih (*overlapping*).
- [ ] **Touch Target & Ergonomi**:
  - [ ] Semua tombol CTA dan link navigasi memiliki area sentuh minimal **44 × 44 piksel** (atau 48 × 48 px untuk tombol aksi utama).
  - [ ] Jarak antar tombol cukup renggang sehingga mencegah salah klik (*mis-tap*) pada layar sentuh.
- [ ] **Aksesibilitas & Kontras Visual**:
  - [ ] Kontras warna teks terhadap latar belakang memenuhi standar WCAG AA (rasio minimal 4.5:1 untuk teks normal).
  - [ ] Seluruh elemen gambar memiliki atribut `alt` yang deskriptif untuk pembaca layar (*screen reader*).
  - [ ] Form input (seperti input Kode Pesanan di Lacak Pesanan) memiliki label atau placeholder yang jelas serta indikator fokus saat disentuh.

---

## 3. End-to-End Workflow Testing (Order & Tracking)

Simulasi siklus pesanan penuh dari interaksi pelanggan hingga tracking publik.

- [ ] **Simulasi Masuknya Pesanan**:
  - [ ] Pelanggan mengklik tombol CTA WhatsApp pada salah satu produk.
  - [ ] Pesan WhatsApp diterima admin dengan format pemesanan standar.
- [ ] **Pencatatan Pesanan Internal (Backend WP-Admin)**:
  - [ ] Admin login ke WP Admin dan masuk ke menu **Pesanan > Tambah Pesanan Baru**.
  - [ ] Form meta box menampilkan field: Nama Pemesan, No. WhatsApp, Alamat Pengiriman, Wilayah Pengantaran (*Cimalaka*, *Sumedang Kota*, atau *Lainnya*), Item Pesanan, dan Total Harga.
  - [ ] Input data pesanan simulasi dan klik **Terbitkan / Perbarui**.
  - [ ] **Kode Pesanan**: Kode otomatis ter-generate dengan format `TC-YYYY-XXXX` (contoh: `TC-2026-0001`).
  - [ ] Status awal otomatis terset sebagai `Pesanan diterima`.
- [ ] **Pengujian Halaman Publik Lacak Pesanan (`/lacak-pesanan/`)**:
  - [ ] Buka halaman `/lacak-pesanan/` di jendela *Incognito* / *Private Browser* (tanpa login WordPress).
  - [ ] Form input kode pesanan tampil dengan tombol pencarian.
  - [ ] **Skenario Kode Valid**:
    - [ ] Masukkan kode pesanan simulasi (misal: `TC-2026-0001`) lalu klik **Cari**.
    - [ ] Timeline status pesanan muncul (Pesanan diterima -> Sedang diproses -> Produk dibeli -> Siap dikirim -> Dalam pengantaran -> Selesai).
    - [ ] Status yang aktif saat ini disorot (*highlighted*) dengan benar.
    - [ ] Daftar item pesanan tampil sesuai pesanan.
  - [ ] **Skenario Proteksi Data Pribadi (Anti-IDOR / PII Protection)**:
    - [ ] Pastikan **TIDAK ADA** nama lengkap, nomor WhatsApp, alamat detail pengantaran, atau catatan modal internal yang tampil di halaman pelacakan publik.
  - [ ] **Skenario Kode Tidak Ditemukan**:
    - [ ] Masukkan kode acak (misal: `TC-9999-9999`).
    - [ ] Muncul pesan ramah: *"Pesanan dengan kode tersebut tidak ditemukan. Pastikan kode sudah benar."* tanpa memicu fatal error atau broken layout.

---

## 4. Infrastructure & Data Integrity Testing

Memastikan kesiapan server Docker, volume persistent, dan rutinitas backup.

- [ ] **Docker Containers Health**:
  - [ ] Jalankan `docker compose ps` (atau `make ps`).
  - [ ] Semua kontainer (`nginx`, `wordpress`, `db`) berada dalam status `Up` dan database menunjukkan status `(healthy)`.
- [ ] **Backup Otomatis (`scripts/backup.sh`)**:
  - [ ] Jalankan perintah: `./scripts/backup.sh` (atau `make backup`).
  - [ ] Verifikasi file dump database terbuat di folder `backups/db-YYYY-MM-DD-HHMMSS.sql` dengan ukuran valid (>0 KB).
  - [ ] Verifikasi file arsip media uploads terbuat di folder `backups/uploads-YYYY-MM-DD-HHMMSS.tar.gz`.
  - [ ] Mekanisme pembersihan otomatis menghapus file backup yang berumur lebih dari 30 hari berjalan tanpa error.
- [ ] **Restore Testing (`scripts/restore.sh`)**:
  - [ ] Verifikasi bahwa script `./scripts/restore.sh` meminta konfirmasi eksplisit nama database sebelum menimpa data (mencegah salah pencet saat panik).

---

## 5. Security & Performance Testing

Memastikan sistem aman dari eksploitasi dan performa halaman responsif.

- [ ] **Protokol SSL/TLS & HTTPS**:
  - [ ] Akses melalui HTTP (`http://domain.com`) otomatis di-redirect ke HTTPS (`https://domain.com`) dengan status 301.
  - [ ] Gembok SSL hijau/aman di browser tanpa peringatan *Mixed Content* (seluruh CSS, JS, font, dan gambar ter-load via HTTPS).
- [ ] **Nginx Security Headers**:
  - [ ] Pengecekan via `curl -sI https://domain.com` atau [securityheaders.com](https://securityheaders.com) memastikan header aktif:
    - [ ] `Strict-Transport-Security: max-age=31536000; includeSubDomains`
    - [ ] `X-Frame-Options: SAMEORIGIN` (mencegah clickjacking)
    - [ ] `X-Content-Type-Options: nosniff` (mencegah MIME confusion)
    - [ ] `Referrer-Policy: strict-origin-when-cross-origin`
    - [ ] `Permissions-Policy: geolocation=(), microphone=(), camera=()`
- [ ] **Akses Terlarang & Hardening**:
  - [ ] Akses langsung ke `https://domain.com/xmlrpc.php` mengembalikan HTTP `403 Forbidden`.
  - [ ] Akses langsung ke `https://domain.com/wp-config.php` mengembalikan HTTP `403 Forbidden`.
  - [ ] Akses langsung ke file tersembunyi seperti `https://domain.com/.env` atau `https://domain.com/.git` mengembalikan HTTP `403 Forbidden`.
  - [ ] Eksekusi skrip PHP di dalam folder uploads (`/wp-content/uploads/test.php`) diblokir (`403 Forbidden`).
- [ ] **Konfigurasi PHP & WordPress Production**:
  - [ ] `WP_DEBUG_DISPLAY` = `false` dan `display_errors` = `Off` (tidak ada warning/notice PHP yang bocor ke pengunjung).
  - [ ] `DISALLOW_FILE_EDIT` = `true` (editor kode bawaan di admin dinonaktifkan).
  - [ ] OPcache aktif dengan `opcache.validate_timestamps = 0` pada environment production untuk kecepatan eksekusi script.
- [ ] **Browser Console & Network**:
  - [ ] Inspect browser console (F12) menunjukkan **0 error JavaScript**.
  - [ ] Waktu loading awal (*First Contentful Paint*) berada di bawah 2 detik pada koneksi 4G standar.

---

## Tanda Tangan Kesiapan Rilis

| Penguji (QA / Admin) | Tanggal Pengujian | Status (Lolos / Belum) | Catatan |
|---|---|---|---|
| Admin Titip Cirebon | _________________ | [ ] LOLOS / [ ] REVISI | ____________________________________ |
