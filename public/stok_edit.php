<?php
// ============================================================================
// FILE: public/stok_edit.php
// DESKRIPSI: Form untuk mengedit stok barang
//            Fitur: Update stok existing atau insert stok baru jika belum ada
// ============================================================================

require_once '../config/koneksi.php';  // Include koneksi database

// --- AUTH CHECK (ADMIN ONLY) ---
requireAdmin();  // Hanya Admin yang boleh mengedit stok

// ============================================================================
// AMBIL ID BARANG DARI URL
// ============================================================================
$id_barang = isset($_GET['id']) ? trim($_GET['id']) : '';

if (empty($id_barang)) {
    redirect('stok.php', 'error', 'ID Barang tidak ditemukan!');
}

// ============================================================================
// FETCH DATA BARANG + STOK
// ============================================================================
try {
    $sql = "SELECT b.id_barang, b.nama, b.harga, COALESCE(s.jumlah_barang, 0) as jumlah_barang, 
                   s.lokasi_rak, s.id_stok
            FROM barang b 
            LEFT JOIN stok s ON b.id_barang = s.id_barang 
            WHERE b.id_barang = :id_barang";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_barang' => $id_barang]);
    $data = $stmt->fetch();

    if (!$data) {
        redirect('stok.php', 'error', 'Data barang tidak ditemukan!');
    }

} catch (PDOException $e) {
    error_log("Stok Edit Error: " . $e->getMessage());
    redirect('stok.php', 'error', 'Gagal mengambil data!');
}

// ============================================================================
// INISIALISASI ERROR
// ============================================================================
$errors = [];

// ============================================================================
// PROSES FORM SUBMIT
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jumlah_baru = trim($_POST['jumlah_barang'] ?? '');
    $lokasi_rak = trim($_POST['lokasi_rak'] ?? 'Rak A1');

    // Validasi jumlah stok
    if (!is_numeric($jumlah_baru) || (int)$jumlah_baru < 0) {
        $errors[] = 'Jumlah stok tidak valid!';
    }

    if (empty($errors)) {
        try {
            if ($data['id_stok']) {
                // --- UPDATE STOK EXISTING ---
                $sql = "UPDATE stok SET jumlah_barang = :jumlah, lokasi_rak = :lokasi 
                        WHERE id_stok = :id_stok";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':jumlah' => (int)$jumlah_baru,
                    ':lokasi' => $lokasi_rak,
                    ':id_stok' => $data['id_stok']
                ]);
            } else {
                // --- INSERT STOK BARU ---
                $id_stok = generateId('ST', $pdo, 'stok', 'id_stok');
                $sql = "INSERT INTO stok (id_stok, id_barang, jumlah_barang, lokasi_rak) 
                        VALUES (:id_stok, :id_barang, :jumlah, :lokasi)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':id_stok' => $id_stok,
                    ':id_barang' => $id_barang,
                    ':jumlah' => (int)$jumlah_baru,
                    ':lokasi' => $lokasi_rak
                ]);
            }

            redirect('stok.php', 'success', "Stok barang '{$data['nama']}' berhasil diperbarui!");

        } catch (PDOException $e) {
            error_log("Update Stok Error: " . $e->getMessage());
            $errors[] = 'Gagal memperbarui stok!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Stok - Toko Prima Furnitur</title>
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
                <li><a href="stok.php" class="active">📊 Stok</a></li>
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
        <div class="breadcrumb">
            <a href="index.php">Beranda</a>
            <span>/</span>
            <a href="stok.php">Stok</a>
            <span>/</span>
            <span>Edit Stok</span>
        </div>

        <div class="page-header">
            <h1>✏️ Edit Stok Barang</h1>
            <p><?php echo sanitize($data['nama']); ?> (<?php echo sanitize($id_barang); ?>)</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <span>❌ <?php echo implode(', ', $errors); ?></span>
                <button class="alert-close" onclick="this.parentElement.remove()">×</button>
            </div>
        <?php endif; ?>

        <div class="card" style="max-width: 600px;">
            <div class="card-header">
                <h2>📊 Informasi Stok</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">Nama Barang</label>
                        <input type="text" class="form-input" value="<?php echo sanitize($data['nama']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Harga</label>
                        <input type="text" class="form-input" value="<?php echo formatRupiah($data['harga']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Jumlah Stok <span class="required">*</span></label>
                        <input type="number" name="jumlah_barang" class="form-input" 
                               value="<?php echo $data['jumlah_barang']; ?>" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Lokasi Rak</label>
                        <select name="lokasi_rak" class="form-select">
                            <option value="Rak A1" <?php echo ($data['lokasi_rak'] ?? '') === 'Rak A1' ? 'selected' : ''; ?>>Rak A1</option>
                            <option value="Rak A2" <?php echo ($data['lokasi_rak'] ?? '') === 'Rak A2' ? 'selected' : ''; ?>>Rak A2</option>
                            <option value="Rak A3" <?php echo ($data['lokasi_rak'] ?? '') === 'Rak A3' ? 'selected' : ''; ?>>Rak A3</option>
                            <option value="Rak A4" <?php echo ($data['lokasi_rak'] ?? '') === 'Rak A4' ? 'selected' : ''; ?>>Rak A4</option>
                            <option value="Rak B1" <?php echo ($data['lokasi_rak'] ?? '') === 'Rak B1' ? 'selected' : ''; ?>>Rak B1</option>
                            <option value="Rak B2" <?php echo ($data['lokasi_rak'] ?? '') === 'Rak B2' ? 'selected' : ''; ?>>Rak B2</option>
                            <option value="Rak C1" <?php echo ($data['lokasi_rak'] ?? '') === 'Rak C1' ? 'selected' : ''; ?>>Rak C1</option>
                            <option value="Rak C2" <?php echo ($data['lokasi_rak'] ?? '') === 'Rak C2' ? 'selected' : ''; ?>>Rak C2</option>
                        </select>
                    </div>
                    <div style="display: flex; gap: 12px; margin-top: 24px;">
                        <button type="submit" class="btn btn-primary">💾 Simpan</button>
                        <a href="stok.php" class="btn btn-secondary">❌ Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="footer-bottom">
            <p>© 2026 Toko Prima Furnitur. Sistem Informasi Penjualan Terintegrasi.</p>
        </div>
    </footer>

</body>
</html>
