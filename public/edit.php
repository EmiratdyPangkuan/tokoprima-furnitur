<?php
// ============================================================================
// FILE: public/edit.php
// DESKRIPSI: Form untuk mengedit data barang yang sudah ada
//            Fitur: Fetch data barang + stok, validasi, update 2 tabel sekaligus
// ============================================================================

require_once '../config/koneksi.php';  // Include koneksi database

// --- AUTH CHECK (ADMIN ONLY) ---
requireAdmin();  // Hanya Admin yang boleh mengedit barang

// ============================================================================
// AMBIL ID BARANG DARI URL
// ============================================================================
// $_GET['id'] mengambil parameter id dari URL
// Contoh: edit.php?id=PDK-001
$id_barang = isset($_GET['id']) ? trim($_GET['id']) : '';

// Validasi: ID tidak boleh kosong
if (empty($id_barang)) {
    redirect('index.php', 'error', 'ID Barang tidak ditemukan!');
}

// ============================================================================
// FETCH DATA BARANG + STOK DARI DATABASE
// ============================================================================
try {
    // Query LEFT JOIN untuk mengambil data barang dan stok sekaligus
    // COALESCE(s.jumlah_barang, 0) = jika stok NULL, tampilkan 0
    $sql = "SELECT b.*, COALESCE(s.jumlah_barang, 0) as jumlah_stok, s.lokasi_rak, s.id_stok 
            FROM barang b 
            LEFT JOIN stok s ON b.id_barang = s.id_barang 
            WHERE b.id_barang = :id_barang";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_barang' => $id_barang]);
    $barang = $stmt->fetch();

    // Jika data tidak ditemukan, redirect dengan pesan error
    if (!$barang) {
        redirect('index.php', 'error', 'Data barang tidak ditemukan!');
    }

} catch (PDOException $e) {
    error_log("Edit Fetch Error: " . $e->getMessage());
    redirect('index.php', 'error', 'Gagal mengambil data barang!');
}

// ============================================================================
// INISIALISASI VARIABEL
// ============================================================================
$errors = [];

// Isi data awal dari database (untuk ditampilkan di form)
$data = [
    'nama' => $barang['nama'],
    'harga' => $barang['harga'],
    'bahan' => $barang['bahan'],
    'warna' => $barang['warna'],
    'ukuran' => $barang['ukuran'],
    'jumlah_stok' => $barang['jumlah_stok'] ?? 0,
    'lokasi_rak' => $barang['lokasi_rak'] ?? 'Rak A1'
];

// ============================================================================
// PROSES FORM SUBMIT (METHOD POST)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari form
    $data['nama'] = trim($_POST['nama'] ?? '');
    $data['harga'] = trim($_POST['harga'] ?? '');
    $data['bahan'] = trim($_POST['bahan'] ?? '');
    $data['warna'] = trim($_POST['warna'] ?? '');
    $data['ukuran'] = trim($_POST['ukuran'] ?? '');
    $data['jumlah_stok'] = trim($_POST['jumlah_stok'] ?? '0');
    $data['lokasi_rak'] = trim($_POST['lokasi_rak'] ?? 'Rak A1');

    // ============================================================================
    // VALIDASI INPUT (sama seperti tambah.php)
    // ============================================================================
    if (empty($data['nama'])) {
        $errors['nama'] = 'Nama barang wajib diisi!';
    } elseif (strlen($data['nama']) < 3) {
        $errors['nama'] = 'Nama barang minimal 3 karakter!';
    } elseif (strlen($data['nama']) > 100) {
        $errors['nama'] = 'Nama barang maksimal 100 karakter!';
    }

    if (empty($data['harga'])) {
        $errors['harga'] = 'Harga wajib diisi!';
    } elseif (!is_numeric($data['harga'])) {
        $errors['harga'] = 'Harga harus berupa angka!';
    } elseif ((float)$data['harga'] <= 0) {
        $errors['harga'] = 'Harga harus lebih besar dari 0!';
    }

    if (empty($data['bahan'])) {
        $errors['bahan'] = 'Bahan wajib diisi!';
    } elseif (strlen($data['bahan']) > 50) {
        $errors['bahan'] = 'Bahan maksimal 50 karakter!';
    }

    if (empty($data['warna'])) {
        $errors['warna'] = 'Warna wajib diisi!';
    } elseif (strlen($data['warna']) > 30) {
        $errors['warna'] = 'Warna maksimal 30 karakter!';
    }

    if (empty($data['ukuran'])) {
        $errors['ukuran'] = 'Ukuran wajib diisi!';
    } elseif (strlen($data['ukuran']) > 30) {
        $errors['ukuran'] = 'Ukuran maksimal 30 karakter!';
    }

    if (!is_numeric($data['jumlah_stok']) || (int)$data['jumlah_stok'] < 0) {
        $errors['jumlah_stok'] = 'Stok tidak boleh negatif!';
    }

    // ============================================================================
    // PROSES UPDATE (JIKA TIDAK ADA ERROR)
    // ============================================================================
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // --- UPDATE TABEL BARANG ---
            $sql_update = "UPDATE barang SET 
                          nama = :nama, 
                          harga = :harga, 
                          bahan = :bahan, 
                          warna = :warna, 
                          ukuran = :ukuran 
                          WHERE id_barang = :id_barang";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([
                ':nama' => $data['nama'],
                ':harga' => (float)$data['harga'],
                ':bahan' => $data['bahan'],
                ':warna' => $data['warna'],
                ':ukuran' => $data['ukuran'],
                ':id_barang' => $id_barang
            ]);

            // --- UPDATE ATAU INSERT STOK ---
            if ($barang['id_stok']) {
                // Jika stok sudah ada, UPDATE
                $sql_stok = "UPDATE stok SET 
                            jumlah_barang = :jumlah_barang, 
                            lokasi_rak = :lokasi_rak 
                            WHERE id_stok = :id_stok";
                $stmt_stok = $pdo->prepare($sql_stok);
                $stmt_stok->execute([
                    ':jumlah_barang' => (int)$data['jumlah_stok'],
                    ':lokasi_rak' => $data['lokasi_rak'],
                    ':id_stok' => $barang['id_stok']
                ]);
            } else {
                // Jika stok belum ada, INSERT baru
                $id_stok = generateId('ST', $pdo, 'stok', 'id_stok');
                $sql_stok = "INSERT INTO stok (id_stok, id_barang, jumlah_barang, lokasi_rak) 
                            VALUES (:id_stok, :id_barang, :jumlah_barang, :lokasi_rak)";
                $stmt_stok = $pdo->prepare($sql_stok);
                $stmt_stok->execute([
                    ':id_stok' => $id_stok,
                    ':id_barang' => $id_barang,
                    ':jumlah_barang' => (int)$data['jumlah_stok'],
                    ':lokasi_rak' => $data['lokasi_rak']
                ]);
            }

            $pdo->commit();
            redirect('index.php', 'success', "Barang '{$data['nama']}' berhasil diperbarui!");

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Update Error: " . $e->getMessage());
            $errors['general'] = 'Gagal memperbarui data. Silakan coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Barang - Toko Prima Furnitur</title>
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
            <span>Edit Barang</span>
        </div>

        <!-- Page Header -->
        <div class="page-header">
            <h1>✏️ Edit Barang</h1>
            <p>Edit data barang <strong><?php echo sanitize($barang['nama']); ?></strong> (ID: <?php echo sanitize($id_barang); ?>)</p>
        </div>

        <!-- Error General -->
        <?php if (isset($errors['general'])): ?>
            <div class="alert alert-error">
                <span>❌ <?php echo $errors['general']; ?></span>
                <button class="alert-close" onclick="this.parentElement.remove()">×</button>
            </div>
        <?php endif; ?>

        <!-- Form Card -->
        <div class="card">
            <div class="card-header">
                <h2>📝 Informasi Barang</h2>
                <span class="badge badge-info">ID: <?php echo sanitize($id_barang); ?></span>
            </div>
            <div class="card-body">
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
                            <?php endif; ?>
                        </div>

                        <!-- Jumlah Stok -->
                        <div class="form-group">
                            <label class="form-label" for="jumlah_stok">
                                Jumlah Stok
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
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div style="display: flex; gap: 12px; margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--border);">
                        <button type="submit" class="btn btn-primary btn-lg">
                            💾 Simpan Perubahan
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
