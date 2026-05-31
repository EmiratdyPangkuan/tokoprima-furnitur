<?php
// ============================================================================
// FILE: public/tambah.php
// DESKRIPSI: Form untuk menambah data barang baru
//            Fitur: Validasi lengkap, auto-generate ID, insert ke 2 tabel (barang + stok)
// ============================================================================

require_once '../config/koneksi.php';  // Include koneksi database

// --- AUTH CHECK (ADMIN ONLY) ---
requireAdmin();  // Hanya Admin yang boleh menambah barang

// ============================================================================
// INISIALISASI VARIABEL
// ============================================================================
// Array untuk menyimpan pesan error validasi
$errors = [];

// Array untuk menyimpan data form (untuk preserve input saat error)
$data = [
    'nama' => '',
    'harga' => '',
    'bahan' => '',
    'warna' => '',
    'ukuran' => '',
    'jumlah_stok' => '0',
    'lokasi_rak' => 'Rak A1'
];

// ============================================================================
// PROSES FORM SUBMIT (METHOD POST)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- AMBIL DATA DARI FORM ---
    // trim() menghapus spasi di awal/akhir
    // ?? '' adalah null coalescing operator (jika tidak ada, gunakan default)
    $data['nama'] = trim($_POST['nama'] ?? '');
    $data['harga'] = trim($_POST['harga'] ?? '');
    $data['bahan'] = trim($_POST['bahan'] ?? '');
    $data['warna'] = trim($_POST['warna'] ?? '');
    $data['ukuran'] = trim($_POST['ukuran'] ?? '');
    $data['jumlah_stok'] = trim($_POST['jumlah_stok'] ?? '0');
    $data['lokasi_rak'] = trim($_POST['lokasi_rak'] ?? 'Rak A1');

    // ============================================================================
    // VALIDASI INPUT
    // ============================================================================

    // --- VALIDASI NAMA BARANG ---
    if (empty($data['nama'])) {
        $errors['nama'] = 'Nama barang wajib diisi!';
    } elseif (strlen($data['nama']) < 3) {
        $errors['nama'] = 'Nama barang minimal 3 karakter!';
    } elseif (strlen($data['nama']) > 100) {
        $errors['nama'] = 'Nama barang maksimal 100 karakter!';
    }

    // --- VALIDASI HARGA ---
    if (empty($data['harga'])) {
        $errors['harga'] = 'Harga wajib diisi!';
    } elseif (!is_numeric($data['harga'])) {
        $errors['harga'] = 'Harga harus berupa angka!';
    } elseif ((float)$data['harga'] <= 0) {
        $errors['harga'] = 'Harga harus lebih besar dari 0!';
    } elseif ((float)$data['harga'] > 999999999999) {
        $errors['harga'] = 'Harga terlalu besar!';
    }

    // --- VALIDASI BAHAN ---
    if (empty($data['bahan'])) {
        $errors['bahan'] = 'Bahan wajib diisi!';
    } elseif (strlen($data['bahan']) > 50) {
        $errors['bahan'] = 'Bahan maksimal 50 karakter!';
    }

    // --- VALIDASI WARNA ---
    if (empty($data['warna'])) {
        $errors['warna'] = 'Warna wajib diisi!';
    } elseif (strlen($data['warna']) > 30) {
        $errors['warna'] = 'Warna maksimal 30 karakter!';
    }

    // --- VALIDASI UKURAN ---
    if (empty($data['ukuran'])) {
        $errors['ukuran'] = 'Ukuran wajib diisi!';
    } elseif (strlen($data['ukuran']) > 30) {
        $errors['ukuran'] = 'Ukuran maksimal 30 karakter!';
    }

    // --- VALIDASI STOK ---
    if (!is_numeric($data['jumlah_stok']) || (int)$data['jumlah_stok'] < 0) {
        $errors['jumlah_stok'] = 'Stok tidak boleh negatif!';
    }

    // ============================================================================
    // PROSES INSERT KE DATABASE (JIKA TIDAK ADA ERROR)
    // ============================================================================
    if (empty($errors)) {
        try {
            // Mulai transaksi database (atomik operation)
            $pdo->beginTransaction();

            // --- GENERATE ID BARANG ---
            // Menggunakan fungsi generateId() dari koneksi.php
            // Format: PDK-001, PDK-002, dst.
            $id_barang = generateId('PDK', $pdo, 'barang', 'id_barang');

            // --- INSERT KE TABEL BARANG ---
            $sql_barang = "INSERT INTO barang (id_barang, nama, harga, bahan, warna, ukuran) 
                          VALUES (:id_barang, :nama, :harga, :bahan, :warna, :ukuran)";
            $stmt_barang = $pdo->prepare($sql_barang);
            $stmt_barang->execute([
                ':id_barang' => $id_barang,
                ':nama' => $data['nama'],
                ':harga' => (float)$data['harga'],
                ':bahan' => $data['bahan'],
                ':warna' => $data['warna'],
                ':ukuran' => $data['ukuran']
            ]);

            // --- GENERATE ID STOK ---
            $id_stok = generateId('ST', $pdo, 'stok', 'id_stok');

            // --- INSERT KE TABEL STOK ---
            // Stok terpisah dari barang untuk normalisasi database (3NF)
            $sql_stok = "INSERT INTO stok (id_stok, id_barang, jumlah_barang, lokasi_rak) 
                        VALUES (:id_stok, :id_barang, :jumlah_barang, :lokasi_rak)";
            $stmt_stok = $pdo->prepare($sql_stok);
            $stmt_stok->execute([
                ':id_stok' => $id_stok,
                ':id_barang' => $id_barang,
                ':jumlah_barang' => (int)$data['jumlah_stok'],
                ':lokasi_rak' => $data['lokasi_rak']
            ]);

            // Commit transaksi (simpan permanen ke database)
            $pdo->commit();

            // Redirect ke index dengan pesan sukses
            redirect('index.php', 'success', "Barang '{$data['nama']}' berhasil ditambahkan dengan ID {$id_barang}!");

        } catch (PDOException $e) {
            // Rollback jika terjadi error (batalkan semua perubahan)
            $pdo->rollBack();
            error_log("Insert Error: " . $e->getMessage());
            $errors['general'] = 'Gagal menyimpan data. Silakan coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Barang - Toko Prima Furnitur</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<!-- ============================================================================
     NAVBAR
     ============================================================================ -->
    <nav class="navbar">
        <div class="navbar-container">
            <a href="index.php" class="navbar-brand">
                <div class="brand-icon">🪑</div>
                <span>Toko Prima Furnitur</span>
            </a>
            <ul class="navbar-menu">
                <li><a href="index.php">📦 Barang</a></li>
                <li><a href="stok.php">📊 Stok</a></li>
                <li><a href="transaksi.php">🛒 Transaksi</a></li>
                <li><a href="laporan.php">📈 Laporan</a></li>
                <li class="nav-user">
                    <span class="user-badge admin">
                        👑 <?php echo sanitize(getUserName()); ?> (admin)
                    </span>
                    <a href="logout.php" class="btn-logout">🚪 Logout</a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="main-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="index.php">Beranda</a>
            <span>/</span>
            <a href="index.php">Data Barang</a>
            <span>/</span>
            <span>Tambah Barang</span>
        </div>

        <!-- Page Header -->
        <div class="page-header">
            <h1>➕ Tambah Barang Baru</h1>
            <p>Isi form berikut untuk menambahkan data barang furnitur ke sistem</p>
        </div>

        <!-- Error General -->
        <?php if (isset($errors['general'])): ?>
            <div class="alert alert-error">
                <span>❌ <?php echo $errors['general']; ?></span>
                <button class="alert-close" onclick="this.parentElement.remove()">×</button>
            </div>
        <?php endif; ?>

        <!-- ============================================================================
             FORM INSERT BARANG
             ============================================================================ -->
        <div class="card">
            <div class="card-header">
                <h2>📝 Informasi Barang</h2>
            </div>
            <div class="card-body">
                <!-- novalidate = nonaktifkan validasi HTML5 bawaan browser (kita pakai PHP validation) -->
                <form method="POST" action="" novalidate>
                    <div class="form-grid">
                        <!-- Nama Barang -->
                        <div class="form-group">
                            <label class="form-label" for="nama">
                                Nama Barang <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="nama" 
                                   name="nama" 
                                   class="form-input <?php echo isset($errors['nama']) ? 'is-invalid' : ''; ?>"
                                   value="<?php echo sanitize($data['nama']); ?>"
                                   placeholder="Contoh: Sofa Minimalis 3 Duduk"
                                   maxlength="100"
                                   required>
                            <?php if (isset($errors['nama'])): ?>
                                <div class="form-error">⚠️ <?php echo $errors['nama']; ?></div>
                            <?php else: ?>
                                <div class="form-hint">Minimal 3 karakter, maksimal 100 karakter</div>
                            <?php endif; ?>
                        </div>

                        <!-- Harga -->
                        <div class="form-group">
                            <label class="form-label" for="harga">
                                Harga (Rp) <span class="required">*</span>
                            </label>
                            <input type="number" 
                                   id="harga" 
                                   name="harga" 
                                   class="form-input <?php echo isset($errors['harga']) ? 'is-invalid' : ''; ?>"
                                   value="<?php echo sanitize($data['harga']); ?>"
                                   placeholder="Contoh: 3500000"
                                   min="1"
                                   step="0.01"
                                   required>
                            <?php if (isset($errors['harga'])): ?>
                                <div class="form-error">⚠️ <?php echo $errors['harga']; ?></div>
                            <?php else: ?>
                                <div class="form-hint">Masukkan harga dalam Rupiah, tanpa titik/koma</div>
                            <?php endif; ?>
                        </div>

                        <!-- Bahan -->
                        <div class="form-group">
                            <label class="form-label" for="bahan">
                                Bahan <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="bahan" 
                                   name="bahan" 
                                   class="form-input <?php echo isset($errors['bahan']) ? 'is-invalid' : ''; ?>"
                                   value="<?php echo sanitize($data['bahan']); ?>"
                                   placeholder="Contoh: Kayu Jati Solid"
                                   maxlength="50"
                                   required>
                            <?php if (isset($errors['bahan'])): ?>
                                <div class="form-error">⚠️ <?php echo $errors['bahan']; ?></div>
                            <?php else: ?>
                                <div class="form-hint">Maksimal 50 karakter</div>
                            <?php endif; ?>
                        </div>

                        <!-- Warna -->
                        <div class="form-group">
                            <label class="form-label" for="warna">
                                Warna <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="warna" 
                                   name="warna" 
                                   class="form-input <?php echo isset($errors['warna']) ? 'is-invalid' : ''; ?>"
                                   value="<?php echo sanitize($data['warna']); ?>"
                                   placeholder="Contoh: Coklat Tua"
                                   maxlength="30"
                                   required>
                            <?php if (isset($errors['warna'])): ?>
                                <div class="form-error">⚠️ <?php echo $errors['warna']; ?></div>
                            <?php else: ?>
                                <div class="form-hint">Maksimal 30 karakter</div>
                            <?php endif; ?>
                        </div>

                        <!-- Ukuran -->
                        <div class="form-group">
                            <label class="form-label" for="ukuran">
                                Ukuran <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="ukuran" 
                                   name="ukuran" 
                                   class="form-input <?php echo isset($errors['ukuran']) ? 'is-invalid' : ''; ?>"
                                   value="<?php echo sanitize($data['ukuran']); ?>"
                                   placeholder="Contoh: 200x80x90 cm"
                                   maxlength="30"
                                   required>
                            <?php if (isset($errors['ukuran'])): ?>
                                <div class="form-error">⚠️ <?php echo $errors['ukuran']; ?></div>
                            <?php else: ?>
                                <div class="form-hint">Format: panjang x lebar x tinggi</div>
                            <?php endif; ?>
                        </div>

                        <!-- Jumlah Stok -->
                        <div class="form-group">
                            <label class="form-label" for="jumlah_stok">
                                Jumlah Stok Awal
                            </label>
                            <input type="number" 
                                   id="jumlah_stok" 
                                   name="jumlah_stok" 
                                   class="form-input <?php echo isset($errors['jumlah_stok']) ? 'is-invalid' : ''; ?>"
                                   value="<?php echo sanitize($data['jumlah_stok']); ?>"
                                   placeholder="Contoh: 10"
                                   min="0"
                                   required>
                            <?php if (isset($errors['jumlah_stok'])): ?>
                                <div class="form-error">⚠️ <?php echo $errors['jumlah_stok']; ?></div>
                            <?php else: ?>
                                <div class="form-hint">Minimal 0 unit</div>
                            <?php endif; ?>
                        </div>

                        <!-- Lokasi Rak -->
                        <div class="form-group">
                            <label class="form-label" for="lokasi_rak">
                                Lokasi Rak
                            </label>
                            <select id="lokasi_rak" name="lokasi_rak" class="form-select">
                                <option value="Rak A1" <?php echo $data['lokasi_rak'] === 'Rak A1' ? 'selected' : ''; ?>>Rak A1</option>
                                <option value="Rak A2" <?php echo $data['lokasi_rak'] === 'Rak A2' ? 'selected' : ''; ?>>Rak A2</option>
                                <option value="Rak A3" <?php echo $data['lokasi_rak'] === 'Rak A3' ? 'selected' : ''; ?>>Rak A3</option>
                                <option value="Rak A4" <?php echo $data['lokasi_rak'] === 'Rak A4' ? 'selected' : ''; ?>>Rak A4</option>
                                <option value="Rak B1" <?php echo $data['lokasi_rak'] === 'Rak B1' ? 'selected' : ''; ?>>Rak B1</option>
                                <option value="Rak B2" <?php echo $data['lokasi_rak'] === 'Rak B2' ? 'selected' : ''; ?>>Rak B2</option>
                                <option value="Rak C1" <?php echo $data['lokasi_rak'] === 'Rak C1' ? 'selected' : ''; ?>>Rak C1</option>
                                <option value="Rak C2" <?php echo $data['lokasi_rak'] === 'Rak C2' ? 'selected' : ''; ?>>Rak C2</option>
                            </select>
                            <div class="form-hint">Pilih lokasi penyimpanan di gudang</div>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div style="display: flex; gap: 12px; margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--border);">
                        <button type="submit" class="btn btn-primary btn-lg">
                            💾 Simpan Barang
                        </button>
                        <a href="index.php" class="btn btn-secondary btn-lg">
                            ❌ Batal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ============================================================================
         FOOTER
         ============================================================================ -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-section">
                <div class="footer-brand">
                    <span>🪑</span>
                    <span>Toko Prima Furnitur</span>
                </div>
                <p>Sistem Informasi Penjualan Terintegrasi untuk optimasi pengelolaan stok dan transaksi.</p>
            </div>
            <div class="footer-section">
                <h4>Menu Cepat</h4>
                <a href="index.php">📦 Data Barang</a><br>
                <a href="stok.php">📊 Manajemen Stok</a><br>
                <a href="transaksi.php">🛒 Transaksi</a><br>
                <a href="laporan.php">📈 Laporan</a>
            </div>
            <div class="footer-section">
                <h4>Kontak</h4>
                <p>📍 Jl. Furnitur No. 123, Jakarta</p>
                <p>📞 (021) 1234-5678</p>
                <p>✉️ info@primafurnitur.com</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© 2026 Toko Prima Furnitur. Sistem Informasi Penjualan Terintegrasi.</p>
        </div>
    </footer>

</body>
</html>
