<?php
// ============================================================================
// FILE: public/transaksi_detail.php
// DESKRIPSI: Halaman detail transaksi lengkap
//            Menampilkan: info transaksi, info pembeli, info karyawan, item barang
//            Fitur: Update status, cetak nota
// ============================================================================

require_once '../config/koneksi.php';  // Include koneksi database

// --- AUTH CHECK ---
requireLogin();  // Admin dan Kasir boleh melihat detail transaksi

// ============================================================================
// AMBIL ID TRANSAKSI DARI URL
// ============================================================================
$id_transaksi = isset($_GET['id']) ? trim($_GET['id']) : '';

if (empty($id_transaksi)) {
    redirect('transaksi.php', 'error', 'ID Transaksi tidak ditemukan!');
}

try {
    // ============================================================================
    // FETCH DATA TRANSAKSI + PEMBELI + KARYAWAN (JOIN)
    // ============================================================================
    $sql_transaksi = "SELECT t.*, p.nama_pembeli, p.alamat as alamat_pembeli, p.nomor_telepon as telp_pembeli,
                             k.nama_karyawan, k.jabatan
                      FROM transaksi t 
                      LEFT JOIN pembeli p ON t.id_pembeli = p.id_pembeli 
                      LEFT JOIN karyawan k ON t.id_karyawan = k.id_karyawan 
                      WHERE t.id_transaksi = :id";
    $stmt_transaksi = $pdo->prepare($sql_transaksi);
    $stmt_transaksi->execute([':id' => $id_transaksi]);
    $transaksi = $stmt_transaksi->fetch();

    if (!$transaksi) {
        redirect('transaksi.php', 'error', 'Transaksi tidak ditemukan!');
    }

    // ============================================================================
    // FETCH DETAIL BARANG (JOIN DENGAN TABEL BARANG)
    // ============================================================================
    $sql_detail = "SELECT dt.*, b.nama as nama_barang, b.bahan, b.warna, b.ukuran
                   FROM detail_transaksi dt 
                   LEFT JOIN barang b ON dt.id_barang = b.id_barang 
                   WHERE dt.id_transaksi = :id";
    $stmt_detail = $pdo->prepare($sql_detail);
    $stmt_detail->execute([':id' => $id_transaksi]);
    $detail_list = $stmt_detail->fetchAll();

} catch (PDOException $e) {
    error_log("Transaksi Detail Error: " . $e->getMessage());
    redirect('transaksi.php', 'error', 'Gagal mengambil detail transaksi!');
}

// ============================================================================
// FUNGSI HELPER STATUS BADGE
// ============================================================================
function getStatusBadge($status) {
    switch ($status) {
        case 'Selesai': return '<span class="badge badge-success">✅ Selesai</span>';
        case 'Proses': return '<span class="badge badge-warning">⏳ Proses</span>';
        case 'Pending': return '<span class="badge badge-info">🕐 Pending</span>';
        case 'Batal': return '<span class="badge badge-danger">❌ Batal</span>';
        default: return '<span class="badge">' . $status . '</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Transaksi - Toko Prima Furnitur</title>
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
                <li><a href="transaksi.php" class="active">🛒 Transaksi</a></li>
                <?php if (isAdmin()): ?>
                <li><a href="laporan.php">📈 Laporan</a></li>
                <?php endif; ?>
                <li class="nav-user">
                    <span class="user-badge <?php echo getUserRole(); ?>">
                        <?php echo getUserRole() === 'admin' ? '👑' : '💰'; ?> 
                        <?php echo sanitize(getUserName()); ?> (<?php echo getUserRole(); ?>)
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
            <a href="transaksi.php">Transaksi</a>
            <span>/</span>
            <span>Detail <?php echo sanitize($id_transaksi); ?></span>
        </div>

        <!-- Notifikasi -->
        <?php if (isset($_GET['status']) && isset($_GET['message'])): ?>
            <div class="alert alert-<?php echo $_GET['status'] === 'success' ? 'success' : 'error'; ?>" id="notif">
                <span><?php echo sanitize($_GET['message']); ?></span>
                <button class="alert-close" onclick="this.parentElement.remove()">×</button>
            </div>
        <?php endif; ?>

        <!-- ============================================================================
             HEADER TRANSAKSI
             ============================================================================ -->
        <div class="transaksi-header">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
                <div>
                    <div class="transaksi-id-big">🧾 <?php echo sanitize($id_transaksi); ?></div>
                    <h1 style="font-size: 1.5rem; margin-bottom: 8px;">Detail Transaksi</h1>
                    <p><?php echo getStatusBadge($transaksi['status']); ?></p>
                </div>
                <div style="text-align: right;">
                    <p style="opacity: 0.9;"><?php echo formatTanggal($transaksi['tanggal_pesan']); ?></p>
                    <p style="opacity: 0.8; font-size: 0.9rem;"><?php echo $transaksi['jenis_pemesanan']; ?></p>
                </div>
            </div>
        </div>

        <!-- ============================================================================
             INFO CARDS (PEMBELI, KARYAWAN, TANGGAL, JENIS)
             ============================================================================ -->
        <div class="info-grid">
            <div class="info-card">
                <h4>👤 Pembeli</h4>
                <p><?php echo sanitize($transaksi['nama_pembeli'] ?? '-'); ?></p>
                <p style="font-size: 0.85rem; color: #888; margin-top: 4px;">
                    <?php echo sanitize($transaksi['alamat_pembeli'] ?? '-'); ?>
                </p>
                <p style="font-size: 0.85rem; color: #888;">
                    📞 <?php echo sanitize($transaksi['telp_pembeli'] ?? '-'); ?>
                </p>
            </div>
            <div class="info-card">
                <h4>👨‍💼 Karyawan</h4>
                <p><?php echo sanitize($transaksi['nama_karyawan'] ?? '-'); ?></p>
                <p style="font-size: 0.85rem; color: #888; margin-top: 4px;">
                    <?php echo sanitize($transaksi['jabatan'] ?? '-'); ?>
                </p>
            </div>
            <div class="info-card">
                <h4>📅 Tanggal Transaksi</h4>
                <p><?php echo formatTanggal($transaksi['tanggal_pesan']); ?></p>
            </div>
            <div class="info-card">
                <h4>🛒 Jenis Pemesanan</h4>
                <p><?php echo $transaksi['jenis_pemesanan']; ?></p>
            </div>
        </div>

        <!-- ============================================================================
             DETAIL BARANG
             ============================================================================ -->
        <div class="card">
            <div class="card-header">
                <h2>📦 Item Barang</h2>
                <span class="badge badge-info"><?php echo count($detail_list); ?> item</span>
            </div>
            <div class="card-body">
                <?php if (empty($detail_list)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📦</div>
                        <h3>Tidak Ada Item</h3>
                        <p>Transaksi ini tidak memiliki item barang.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="data-table detail-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>ID Barang</th>
                                    <th>Nama Barang</th>
                                    <th>Spesifikasi</th>
                                    <th style="text-align: center;">Qty</th>
                                    <th>Harga Satuan</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach ($detail_list as $detail): ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td><strong><?php echo sanitize($detail['id_barang']); ?></strong></td>
                                        <td><?php echo sanitize($detail['nama_barang']); ?></td>
                                        <td>
                                            <small style="color: #888;">
                                                <?php echo sanitize($detail['bahan']); ?> • 
                                                <?php echo sanitize($detail['warna']); ?> • 
                                                <?php echo sanitize($detail['ukuran']); ?>
                                            </small>
                                        </td>
                                        <td style="text-align: center; font-weight: 700;"><?php echo $detail['kuantitas']; ?></td>
                                        <td><?php echo formatRupiah($detail['harga_satuan']); ?></td>
                                        <td style="font-weight: 700; color: #ea580c;">
                                            <?php echo formatRupiah($detail['subtotal']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Total Transaksi -->
                    <div class="total-box">
                        <h3>Total Transaksi</h3>
                        <div class="amount"><?php echo formatRupiah($transaksi['total_harga']); ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ============================================================================
             STATUS UPDATE & ACTIONS
             ============================================================================ -->
        <div class="card" style="margin-top: 24px;">
            <div class="card-header">
                <h2>⚡ Update Status Transaksi</h2>
            </div>
            <div class="card-body">
                <div style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                    <?php if (isAdmin() || isKasir()): ?>
                    <!-- Form update status (Admin & Kasir boleh update) -->
                    <form action="update_status.php" method="POST" style="display: inline-flex; gap: 8px; align-items: center;">
                        <input type="hidden" name="type" value="transaksi_status">
                        <input type="hidden" name="id_transaksi" value="<?php echo sanitize($id_transaksi); ?>">
                        <select name="status" class="filter-select" onchange="this.form.submit()">
                            <option value="Pending" <?php echo $transaksi['status'] === 'Pending' ? 'selected' : ''; ?>>🕐 Pending</option>
                            <option value="Proses" <?php echo $transaksi['status'] === 'Proses' ? 'selected' : ''; ?>>⏳ Proses</option>
                            <option value="Selesai" <?php echo $transaksi['status'] === 'Selesai' ? 'selected' : ''; ?>>✅ Selesai</option>
                            <option value="Batal" <?php echo $transaksi['status'] === 'Batal' ? 'selected' : ''; ?>>❌ Batal</option>
                        </select>
                    </form>
                    <?php endif; ?>
                    <button onclick="window.print()" class="btn btn-secondary">🖨️ Cetak Nota</button>
                    <a href="transaksi.php" class="btn btn-secondary">← Kembali</a>
                </div>
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
            </div>
            <div class="footer-section">
                <h4>Menu Cepat</h4>
                <a href="index.php">📦 Data Barang</a><br>
                <a href="stok.php">📊 Manajemen Stok</a><br>
                <a href="transaksi.php">🛒 Transaksi</a><br>
                <a href="laporan.php">📈 Laporan</a>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© 2026 Toko Prima Furnitur. Sistem Informasi Penjualan Terintegrasi.</p>
        </div>
    </footer>

    <script>
        const notif = document.getElementById('notif');
        if (notif) {
            setTimeout(() => {
                notif.style.opacity = '0';
                notif.style.transform = 'translateY(-20px)';
                setTimeout(() => notif.remove(), 400);
            }, 5000);
        }
    </script>
</body>
</html>
