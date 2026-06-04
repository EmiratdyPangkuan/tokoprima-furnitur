# 🪑 TOKO PRIMA FURNITUR
## Sistem Informasi Penjualan Terintegrasi untuk Optimasi Pengelolaan Stok

---

### 📁 STRUKTUR FOLDER

```
tokoprima/
├── config/
│   └── koneksi.php          # Koneksi PDO + helper functions
├── public/
│   ├── index.php            # Dashboard & Data Barang (CRUD utama)
│   ├── tambah.php           # Form Tambah Barang
│   ├── edit.php             # Form Edit Barang
│   ├── hapus.php            # Halaman Konfirmasi Hapus
│   ├── stok.php             # Manajemen Stok (Sorting + Filter)
│   ├── stok_edit.php        # Edit Stok Barang
│   ├── transaksi.php        # Daftar Transaksi (Sorting + Filter)
│   ├── transaksi_tambah.php # Form Tambah Transaksi (Multi-item)
│   ├── transaksi_detail.php # Detail Laporan Transaksi
│   └── laporan.php          # Dashboard Laporan Komprehensif
├── proses/
│   ├── insert.php           # Proses Insert (Barang, Pembeli, Karyawan, Transaksi)
│   ├── update.php           # Proses Update (Barang, Pembeli, Karyawan, Stok, Status)
│   └── delete.php           # Proses Delete (Semua tabel dengan validasi)
├── assets/
│   └── css/
│       └── style.css        # CSS Modern Orange-White Theme
└── tokoprima.sql            # Database SQL lengkap dengan data sample
```

---

### 🚀 CARA MENJALANKAN

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
