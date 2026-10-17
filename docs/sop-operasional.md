# Standar Operasional Prosedur (SOP) — Admin Titip Cirebon

Panduan praktis operasional mingguan untuk pengelola jastip **Titip Cirebon** (rute belanja Cirebon ke Sumedang). Dokumen ini dirancang sederhana agar mudah dijalankan secara konsisten dari minggu ke minggu.

---

## Ringkasan Siklus Mingguan Jastip

```text
[Senin - Kamis]            [Jumat]                     [Sabtu]
Kurasi Menu & Open PO  ──> Rekap Order & Belanja  ──>  Distribusi, Selesai & Retensi
(Penawaran Mingguan)       (Menu Pesanan & Dashboard)  (Update Tracking & Follow-up WA)
```

---

## 1. Workflow Senin – Kamis: Kurasi & Open Pre-Order (PO)

Fokus fase ini adalah menentukan menu kuliner/oleh-oleh yang akan dibuka untuk PO minggu ini dan menyebarkan informasinya kepada calon pembeli.

### Langkah-langkah:
1. **Login ke WordPress Admin**:
   - Buka browser ke: `https://<domain-anda>/wp-admin/` (atau `http://localhost:9090/wp-admin/` saat pengujian).
   - Masukkan Username & Password admin.
2. **Kelola Menu di "Penawaran Mingguan"**:
   - Di menu sebelah kiri, klik **Penawaran Mingguan** > **Tambah Baru** (atau klik judul penawaran lama untuk mengedit).
   - Masukkan informasi penawaran:
     - **Judul**: Nama batch penawaran (contoh: *PO Kuliner Cirebon Batch 12 — Spesial Empal Gentong & Tahu Gejrot*).
     - **Daftar Produk Terpilih**: Pilih produk-produk yang akan masuk dalam batch PO minggu ini.
     - **Batas Waktu PO (Closing Time)**: Tentukan batas akhir pemesanan (rekomendasi: **Jumat pukul 14:00 WIB**).
     - **Tanggal Pengantaran**: Tentukan hari pengiriman (contoh: **Sabtu siang/sore**).
   - Klik tombol **Terbitkan** (atau **Perbarui**).
3. **Promosi & Distribusi Link**:
   - Buka halaman penawaran mingguan di website.
   - Salin link URL halaman tersebut.
   - Sebarkan ke WhatsApp Status, WhatsApp Group kuliner Sumedang, Instagram Story, atau Facebook Group dengan format:
     > *"Halo wargi Sumedang! Open PO Jastip Cirebon minggu ini sudah dibuka sampai Jumat jam 14.00. Pengantaran hari Sabtu sore langsung ke rumah. Cek menu & pesan langsung via web kami: [LINK]"*

---

## 2. Workflow Jumat: Closing PO & Rekapitulasi Operasional

Fokus fase ini adalah menutup pesanan yang masuk via WhatsApp, mencatatnya ke sistem internal, membuat invoice/kode pesanan untuk pembeli, dan menyiapkan daftar belanjaan fisik untuk Sabtu pagi.

### A. Rekap Pesanan dari WhatsApp ke Sistem
1. Masuk ke menu **Pesanan** > **Tambah Pesanan Baru**.
2. Masukkan data dari chat WhatsApp pembeli:
   - **Nama Pemesan**: Nama lengkap / panggilan pelanggan di WhatsApp.
   - **No. WhatsApp**: Nomor aktif pelanggan (format disarankan: `08123456789` atau `628123456789`).
   - **Wilayah Pengantaran**: Pilih antara **Cimalaka**, **Sumedang Kota**, atau **Lainnya**.
   - **Alamat Lengkap Pengiriman**: Alamat rumah, patokan, atau catatan pengantaran.
   - **Item Pesanan**: Tuliskan rincian pesanan (contoh: *2x Nasi Jamblang Mang Dul, 1x Tahu Gejrot Kanoman*).
   - **Total Harga (Rp)**: Masukkan total nominal yang harus dibayar pembeli (sudah termasuk fee jastip & ongkir lokal).
   - **Status Pesanan**: Biarkan default pada `Pesanan diterima`.
3. Klik tombol **Terbitkan** / **Simpan**.
4. **Catat Kode Pesanan**:
   - Sistem akan membuat **Kode Pesanan** otomatis berformat `TC-2026-XXXX` (contoh: `TC-2026-0005`).
5. **Kirimkan Konfirmasi ke Pembeli**:
   - Kirim pesan balasan WhatsApp ke pembeli:
     > *"Terima kasih Kak [Nama]! Pesanan sudah kami rekap dengan Kode Pesanan: **TC-2026-0005**. Kakak bisa memantau proses belanja dan pengantaran paket secara live di website kami: https://<domain-anda>/lacak-pesanan/?kode_pesanan=TC-2026-0005"*

### B. Analisis Data di Dashboard Operasional
1. Di menu sebelah kiri, klik **Dashboard TC**.
2. Periksa data agregasi minggu ini:
   - **Total Order**: Berapa banyak paket yang harus diantar hari Sabtu.
   - **Total Penjualan**: Akumulasi nilai pesanan minggu ini.
   - **Breakdown Wilayah**: Cek berapa paket untuk rute Cimalaka vs Sumedang Kota agar efisien menentukan rute kurir.
   - **Ringkasan Item Belanja**: Buat catatan rekap belanja fisik (contoh: total 15 bungkus Nasi Jamblang, 8 Tahu Gejrot, 5 Sirup Tjampolay) untuk dibeli di Cirebon.

---

## 3. Workflow Sabtu: Belanja, Distribusi, & Retensi Pelanggan

Fokus hari Sabtu adalah eksekusi belanja di Cirebon, distribusi pengantaran di Sumedang, updating status pesanan, serta follow-up loyalitas pelanggan.

### A. Eksekusi Belanja (Sabtu Pagi — Cirebon)
- Sambil/setelah berbelanja di pasar/toko oleh-oleh Cirebon:
  1. Buka admin WordPress via browser handphone atau laptop.
  2. Masuk ke menu **Pesanan**.
  3. Edit pesanan yang barangnya sudah dibeli, ubah status dari `Pesanan diterima` menjadi **`Produk dibeli`**, lalu klik **Perbarui**.
  4. Pembeli yang mengecek di halaman `/lacak-pesanan/` akan langsung melihat bahwa pesanannya sudah aman dibelanjakan.

### B. Pengantaran / Distribusi (Sabtu Siang s/d Sore — Sumedang)
1. **Saat Barang Siap Diantar / Packing Selesai**:
   - Ubah status pesanan menjadi **`Siap dikirim`**.
2. **Saat Kurir Berangkat Mengantar**:
   - Ubah status pesanan menjadi **`Dalam pengantaran`**.
3. **Saat Paket Diterima oleh Pelanggan**:
   - Ubah status pesanan menjadi **`Selesai`**.
   - Kirim ucapan terima kasih via WhatsApp ke pelanggan.

### C. Menjalankan Retensi Pelanggan (Sabtu Malam atau Minggu)
1. Masuk ke menu **Dashboard TC** > **Retensi Pelanggan**.
2. Sistem secara otomatis mengelompokkan pesanan berstatus **Selesai** berdasarkan Nomor WhatsApp.
3. Anda akan melihat:
   - Nama Pelanggan & Nomor WhatsApp.
   - Jumlah Order (pelanggan dengan pesanan > 1 otomatis ditandai sebagai **Repeat Customer** dengan badge hijau).
   - Tanggal Order Terakhir & produk yang paling sering dipesan.
4. **Follow-Up 1-Klik**:
   - Klik tombol hijau **Follow-up via WA** di samping nama pelanggan.
   - Sistem akan otomatis membuka WhatsApp dengan pesan template personal:
     > *"Halo [Nama Pelanggan], ini dari Titip Cirebon. Minggu ini kita open PO lagi lho! Ada menu baru yang mungkin cocok. Mau titip sesuatu minggu ini?"*
   - Kirim pesan tersebut kepada pelanggan setia untuk mengamankan pesanan batch minggu berikutnya!

---

## 4. Penanganan Kasus Khusus (Pengecualian)

### Jika Produk di Cirebon Habis / Tutup Toko:
1. Hubungi pembeli segera melalui WhatsApp untuk menawarkan solusi:
   - **Ganti Menu Alternatif**: Jika setuju, update item di meta box Pesanan dan ubah status ke `Replacement`.
   - **Refund Uang**: Jika pembeli ingin batalkan, transfer kembali dana dan ubah status pesanan menjadi `Refund`.
2. Halaman tracking publik akan menginformasikan status tersebut dengan jelas dan transparan.

---

## 5. Ringkasan Status Pesanan

| Status Sistem | Label Tampilan | Deskripsi untuk Pembeli |
|---|---|---|
| `pesanan_diterima` | **Pesanan Diterima** | Order sudah dicatat oleh admin setelah pembayaran/konfirmasi. |
| `sedang_diproses` | **Sedang Diproses** | Admin sedang menyiapkan daftar belanja batch mingguan. |
| `produk_dibeli` | **Produk Dibeli** | Barang sudah berhasil dibeli oleh tim di Cirebon. |
| `siap_dikirim` | **Siap Dikirim** | Paket sudah dipacking rapi dan siap jalan ke Sumedang. |
| `dalam_pengantaran` | **Dalam Pengantaran** | Kurir sedang menuju lokasi alamat pengantaran. |
| `selesai` | **Selesai** | Paket sudah diterima dengan baik oleh pelanggan. |
| `refund` | **Refund** | Pembelian dibatalkan & uang dikembalikan (stok toko habis). |
| `replacement` | **Replacement** | Produk diganti dengan menu lain atas kesepakatan pembeli. |
