<?php
// ============================================================================
// FILE: public/index.php
// DESKRIPSI: Halaman utama (Dashboard) - Menampilkan daftar barang dan statistik
//            Fitur: Sorting, statistik dashboard, modal delete, notifikasi
// ============================================================================

require_once '../config/koneksi.php';  // Include koneksi database

// --- AUTH CHECK ---
requireLogin();  // Pastikan user sudah login

// ============================================================================
// PARAMETER SORTING
// ============================================================================
// Ambil parameter sorting dari URL (jika ada)
// Default: sort by id_barang, order ASC (naik)
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'id_barang';
$order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'ASC';

// Whitelist kolom yang boleh di-sort (keamanan)
$allowed_sort = ['id_barang', 'nama', 'harga', 'bahan', 'warna', 'ukuran'];
$allowed_order = ['ASC', 'DESC'];

// Validasi parameter sorting
if (!in_array($sort, $allowed_sort)) $sort = 'id_barang';
if (!in_array($order, $allowed_order)) $order = 'ASC';

// Tentukan order berikutnya untuk toggle sorting
// Jika sekarang ASC, klik lagi jadi DESC, dan sebaliknya
$next_order = ($order === 'ASC') ? 'DESC' : 'ASC';

try {
    // ============================================================================
    // QUERY DATA BARANG + STOK (LEFT JOIN)
    // ============================================================================
    // LEFT JOIN digunakan agar barang tanpa stok tetap muncul (stok = NULL/0)
    // COALESCE(s.jumlah_barang, 0) = jika NULL, ganti dengan 0
    $sql = "SELECT b.*, COALESCE(s.jumlah_barang, 0) as stok, s.lokasi_rak 
            FROM barang b 
            LEFT JOIN stok s ON b.id_barang = s.id_barang 
            ORDER BY b.{$sort} {$order}";
    $stmt = $pdo->query($sql);
    $barang_list = $stmt->fetchAll();

    // ============================================================================
    // QUERY STATISTIK DASHBOARD
    // ============================================================================
    // Menggunakan query terpisah untuk efisiensi (tidak perlu JOIN kompleks)
    $stats = [
        'total_barang' => $pdo->query("SELECT COUNT(*) FROM barang")->fetchColumn(),
        'total_stok' => $pdo->query("SELECT COALESCE(SUM(jumlah_barang), 0) FROM stok")->fetchColumn(),
        'total_transaksi' => $pdo->query("SELECT COUNT(*) FROM transaksi")->fetchColumn(),
        'total_pembeli' => $pdo->query("SELECT COUNT(*) FROM pembeli")->fetchColumn(),
        'total_pendapatan' => $pdo->query("SELECT COALESCE(SUM(total_harga), 0) FROM transaksi WHERE status = 'Selesai'")->fetchColumn()
    ];
} catch (PDOException $e) {
    // Jika error, set data kosong dan statistik nol
    $barang_list = [];
    $stats = ['total_barang' => 0, 'total_stok' => 0, 'total_transaksi' => 0, 'total_pembeli' => 0, 'total_pendapatan' => 0];
}

// ============================================================================
// FUNGSI HELPER UNTUK ICON SORTING
// ============================================================================
function sortIcon($col, $currentSort, $currentOrder) {
    // Jika kolom ini sedang di-sort, tampilkan panah naik/turun
    // Jika tidak, tampilkan icon sort netral
    return ($col === $currentSort) ? ($currentOrder === 'ASC' ? '▲' : '▼') : '⇅';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Toko Prima Furnitur - Dashboard</title>
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
                <li><a href="index.php" class="active">📦 Barang</a></li>
                <li><a href="stok.php">📊 Stok</a></li>
                <li><a href="transaksi.php">🛒 Transaksi</a></li>
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
        <!-- ============================================================================
             NOTIFIKASI (DARI REDIRECT)
             ============================================================================ -->
        <?php if (isset($_GET['status']) && isset($_GET['message'])): ?>
            <div class="alert alert-<?php echo $_GET['status'] === 'success' ? 'success' : 'error'; ?>" id="notif">
                <span><?php echo sanitize($_GET['message']); ?></span>
                <button style="margin-left: auto; background: none; border: none; font-size: 1.2rem; cursor: pointer;" onclick="this.parentElement.remove()">×</button>
            </div>
        <?php endif; ?>

        <!-- ============================================================================
             PAGE HEADER
             ============================================================================ -->
        <div class="page-header">
            <div class="breadcrumb">
                <a href="index.php">Beranda</a>
                <span>/</span>
                <span>Data Barang</span>
            </div>
            <h1>📦 Manajemen Barang</h1>
            <p>Kelola data barang furnitur dengan mudah dan terintegrasi dengan sistem stok</p>
        </div>

        <!-- ============================================================================
             STATISTIK DASHBOARD
             ============================================================================ -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-value"><?php echo $stats['total_barang']; ?></div>
                <div class="stat-label">Total Barang</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-value"><?php echo $stats['total_stok']; ?></div>
                <div class="stat-label">Total Stok Unit</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🛒</div>
                <div class="stat-value"><?php echo $stats['total_transaksi']; ?></div>
                <div class="stat-label">Total Transaksi</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-value"><?php echo $stats['total_pembeli']; ?></div>
                <div class="stat-label">Total Pembeli</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-value"><?php echo formatRupiah($stats['total_pendapatan']); ?></div>
                <div class="stat-label">Pendapatan Selesai</div>
            </div>
        </div>

        <!-- ============================================================================
             TABEL DATA BARANG
             ============================================================================ -->
        <div class="card">
            <div class="card-header">
                <h2>📋 Daftar Barang</h2>
                <?php if (isAdmin()): ?>
                <a href="tambah.php" class="btn btn-primary">➕ Tambah Barang</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($barang_list)): ?>
                    <!-- Tampilan jika tidak ada data -->
                    <div class="empty-state">
                        <div class="empty-icon">📦</div>
                        <h3>Belum Ada Data Barang</h3>
                        <p>Silakan tambah data barang pertama Anda dengan mengklik tombol di atas.</p>
                        <?php if (isAdmin()): ?>
                        <a href="tambah.php" class="btn btn-primary">Tambah Barang Pertama</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <!-- Header dengan sorting clickable -->
                                    <th class="sortable" onclick="window.location.href='?sort=id_barang&order=<?php echo $next_order; ?>'">
                                        ID <?php echo sortIcon('id_barang', $sort, $order); ?>
                                    </th>
                                    <th class="sortable" onclick="window.location.href='?sort=nama&order=<?php echo $next_order; ?>'">
                                        Nama Barang <?php echo sortIcon('nama', $sort, $order); ?>
                                    </th>
                                    <th class="sortable" onclick="window.location.href='?sort=harga&order=<?php echo $next_order; ?>'">
                                        Harga <?php echo sortIcon('harga', $sort, $order); ?>
                                    </th>
                                    <th class="sortable" onclick="window.location.href='?sort=bahan&order=<?php echo $next_order; ?>'">
                                        Bahan <?php echo sortIcon('bahan', $sort, $order); ?>
                                    </th>
                                    <th class="sortable" onclick="window.location.href='?sort=warna&order=<?php echo $next_order; ?>'">
                                        Warna <?php echo sortIcon('warna', $sort, $order); ?>
                                    </th>
                                    <th class="sortable" onclick="window.location.href='?sort=ukuran&order=<?php echo $next_order; ?>'">
                                        Ukuran <?php echo sortIcon('ukuran', $sort, $order); ?>
                                    </th>
                                    <th>Stok</th>
                                    <th style="text-align: center;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($barang_list as $barang): ?>
                                    <tr>
                                        <td><strong><?php echo sanitize($barang['id_barang']); ?></strong></td>
                                        <td><?php echo sanitize($barang['nama']); ?></td>
                                        <td class="price-tag"><?php echo formatRupiah($barang['harga']); ?></td>
                                        <td><?php echo sanitize($barang['bahan']); ?></td>
                                        <td><span class="badge badge-info"><?php echo sanitize($barang['warna']); ?></span></td>
                                        <td><?php echo sanitize($barang['ukuran']); ?></td>
                                        <td>
                                            <?php if ($barang['stok'] <= 5): ?>
                                                <!-- Stok kritis: warna merah -->
                                                <span class="stock-low"><?php echo $barang['stok']; ?> (Kritis)</span>
                                            <?php else: ?>
                                                <!-- Stok aman: warna hijau -->
                                                <span class="stock-ok"><?php echo $barang['stok']; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-btns">
                                                <?php if (isAdmin()): ?>
                                                <!-- Tombol Edit -->
                                                <a href="edit.php?id=<?php echo urlencode($barang['id_barang']); ?>" class="btn-edit">✏️ Edit</a>
                                                <!-- Tombol Hapus (buka modal) -->
                                                <button type="button" class="btn-delete" onclick="openDeleteModal('<?php echo sanitize($barang['id_barang']); ?>', '<?php echo sanitize($barang['nama']); ?>')">🗑️ Hapus</button>
                                                <?php else: ?>
                                                <span style="color:#888;font-size:0.8rem;">🔒 Hanya Admin</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ============================================================================
         MODAL KONFIRMASI DELETE
         ============================================================================ -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal">
            <div style="text-align: center; margin-bottom: 20px;">
                <div style="width: 64px; height: 64px; background: #fef3c7; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 16px;">⚠️</div>
                <h3 style="font-size: 1.25rem; font-weight: 700; color: #1a1a1a;">Konfirmasi Hapus Barang</h3>
            </div>
            <div style="color: #666; line-height: 1.6; text-align: center; margin-bottom: 20px;">
                <p>Apakah Anda yakin ingin menghapus barang berikut?</p>
                <p id="deleteItemName" style="font-weight: 700; color: #ea580c; background: #fff7ed; padding: 8px 16px; border-radius: 8px; display: inline-block; margin: 8px 0;"></p>
                <p style="color: #ef4444; font-size: 0.85rem; margin-top: 12px;">⚡ Tindakan ini akan menghapus data stok terkait dan tidak dapat dibatalkan!</p>
            </div>
            <div style="display: flex; gap: 12px; justify-content: center;">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">❌ Batal</button>
                <!-- Form mengirim ke proses/delete.php -->
                <form action="../proses/delete.php" method="POST" style="display: inline;">
                    <input type="hidden" name="id" id="deleteId">
                    <input type="hidden" name="table" value="barang">
                    <input type="hidden" name="redirect" value="index.php">
                    <button type="submit" class="btn btn-danger">🗑️ Ya, Hapus</button>
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
                <p>Sistem Informasi Penjualan Terintegrasi.</p>
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
            </div>
        </div>
        <div class="footer-bottom">
            <p>© 2026 Toko Prima Furnitur. Sistem Informasi Penjualan Terintegrasi.</p>
        </div>
    </footer>

    <!-- ============================================================================
         JAVASCRIPT
         ============================================================================ -->
    <script>
        // Fungsi membuka modal delete
        function openDeleteModal(id, nama) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteItemName').textContent = nama;
            document.getElementById('deleteModal').classList.add('active');
            document.body.style.overflow = 'hidden';  // Disable scroll
        }

        // Fungsi menutup modal delete
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
            document.body.style.overflow = '';  // Enable scroll
        }

        // Tutup modal jika klik di luar modal
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) closeDeleteModal();
        });

        // Tutup modal dengan tombol Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeDeleteModal();
        });

        // Auto-hide notifikasi setelah 5 detik
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
