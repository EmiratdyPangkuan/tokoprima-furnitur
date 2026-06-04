# 🪑 TOKO PRIMA FURNITUR
## Sistem Informasi Penjualan Terintegrasi untuk Optimasi Pengelolaan Stok
**ANGGOTA KELOMPOK**
1. Emiratdy Pangkuan
2. Husaini Ibnu
3. Nashya Deswita

---

### 🚀 CARA MENJALANKAN DI XAMPP

1. **Import Database:**
   - Buka phpMyAdmin (http://localhost/phpmyadmin)
   - Buat database baru: `tokoprima`
   - Import file `tokoprima.sql`

2. **Konfigurasi Koneksi:**
   - Edit `config/koneksi.php` jika username/password MySQL berbeda
   - Default: username `root`, password kosong

3. **Akses Aplikasi:**
   - Letakkan folder `tokoprima` di `htdocs/` (XAMPP)
   - Buka browser: `http://localhost/tokoprima/public/index.php`

---

### ✨ FITUR UTAMA

| Fitur | Deskripsi |
|-------|-----------|
| **CRUD Barang** | Tambah, Edit, Hapus dengan validasi lengkap |
| **Manajemen Stok** | Sorting multi-kolom, filter lokasi & status, visual bar |
| **Transaksi** | Multi-item, auto hitung subtotal, update stok otomatis |
| **Laporan** | Periode filter, top barang terlaris, top pembeli, stok kritis |
| **Konfirmasi Hapus** | Modal popup menampilkan nama data yang akan dihapus |
| **Notifikasi** | Alert sukses/error dengan auto-dismiss |
| **Responsive** | Mobile-friendly layout |
| **Print Ready** | Halaman laporan & detail bisa dicetak |

---

### 🎨 DESAIN

- **Tema:** Orange-Putih Modern
- **Animasi:** Hover effects, transitions, floating icons
- **Komponen:** Cards, badges, progress bars, data tables
- **Icons:** Emoji native (tanpa library eksternal)

---

### 🔒 KEAMANAN

- ✅ Prepared Statements (PDO)
- ✅ Input Sanitization (htmlspecialchars)
- ✅ Validasi form server-side
- ✅ Error handling dengan try-catch
- ✅ Transaction rollback jika gagal
- ✅ Whitelist tabel untuk delete

---

### 📊 FORMAT ID

| Tabel | Format | Contoh |
|-------|--------|--------|
| barang | PDK-XXX | PDK-001 |
| pembeli | PM-XXX | PM-001 |
| karyawan | KR-XXX | KR-001 |
| stok | ST-XXX | ST-001 |
| transaksi | TR-XXX | TR-001 |
| detail_transaksi | DT-XXX | DT-001 |

---

© 2026 Toko Prima Furnitur - Sistem Informasi Penjualan Terintegrasi
