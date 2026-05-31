<?php
// ============================================================================
// FILE: public/transaksi.php
// DESKRIPSI: Halaman manajemen transaksi pembelian
//            Fitur: Sorting, filter status/jenis, search, statistik
// ============================================================================

require_once '../config/koneksi.php';  // Include koneksi database

// --- AUTH CHECK ---
requireLogin();  // Pastikan user sudah login

// ============================================================================
// PARAMETER SORTING DAN FILTER
// ============================================================================
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'tanggal_pesan';
$order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'DESC';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$filter_jenis = isset($_GET['jenis']) ? trim($_GET['jenis']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$allowed_sort = ['id_transaksi', 'tanggal_pesan', 'jenis_pemesanan', 'total_harga', 'status'];
$allowed_order = ['ASC', 'DESC'];

if (!in_array($sort, $allowed_sort)) $sort = 'tanggal_pesan';
if (!in_array($order, $allowed_order)) $order = 'DESC';
$next_order = ($order === 'ASC') ? 'DESC' : 'ASC';

// ============================================================================
// BUILD QUERY DENGAN JOIN
// ============================================================================
try {
    $where_clauses = [];
    $params = [];

    if (!empty($filter_status)) {
        $where_clauses[] = "t.status = :status";
        $params[':status'] = $filter_status;
    }

    if (!empty($filter_jenis)) {
        $where_clauses[] = "t.jenis_pemesanan = :jenis";
        $params[':jenis'] = $filter_jenis;
    }

    if (!empty($search)) {
        $where_clauses[] = "(t.id_transaksi LIKE :search OR p.nama_pembeli LIKE :search OR k.nama_karyawan LIKE :search)";
        $params[':search'] = "%{$search}%";
    }

    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

    // Query transaksi dengan JOIN ke tabel pembeli dan karyawan
    $sql = "SELECT t.*, p.nama_pembeli, k.nama_karyawan,
                   (SELECT COUNT(*) FROM detail_transaksi dt WHERE dt.id_transaksi = t.id_transaksi) as jumlah_item
            FROM transaksi t 
            LEFT JOIN pembeli p ON t.id_pembeli = p.id_pembeli 
            LEFT JOIN karyawan k ON t.id_karyawan = k.id_karyawan 
            {$where_sql}
            ORDER BY t.{$sort} {$order}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transaksi_list = $stmt->fetchAll();

    // Statistik transaksi
    $stats = $pdo->query("SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN status = 'Selesai' THEN 1 END) as selesai,
        COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending,
        COUNT(CASE WHEN status = 'Proses' THEN 1 END) as proses,
        COALESCE(SUM(CASE WHEN status = 'Selesai' THEN total_harga END), 0) as pendapatan
        FROM transaksi")->fetch();

} catch (PDOException $e) {
    error_log("Transaksi Error: " . $e->getMessage());
    $transaksi_list = [];
    $stats = ['total' => 0, 'selesai' => 0, 'pending' => 0, 'proses' => 0, 'pendapatan' => 0];
}

// ============================================================================
// FUNGSI HELPER
// ============================================================================
function sortIcon($col, $currentSort, $currentOrder) {
    if ($col === $currentSort) {
        return $currentOrder === 'ASC' ? '↑' : '↓';
    }
    return '⇅';
}

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
    <title>Transaksi - Toko Prima Furnitur</title>
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
                <li><a href="laporan.php"{' class="active"' if active_menu == 'laporan' else ''}>📈 Laporan</a></li>
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
        <!-- Notifikasi -->
        <?php if (isset($_GET['status']) && isset($_GET['message'])): ?>
            <div class="alert alert-<?php echo $_GET['status'] === 'success' ? 'success' : 'error'; ?>" id="notif">
                <span><?php echo sanitize($_GET['message']); ?></span>
                <button class="alert-close" onclick="this.parentElement.remove()">×</button>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div class="breadcrumb">
                <a href="index.php">Beranda</a>
                <span>/</span>
                <span>Transaksi Pembelian</span>
            </div>
            <h1>🛒 Manajemen Transaksi</h1>
            <p>Kelola transaksi pembelian furnitur dari pembeli</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">🛒</div>
                <div class="stat-value"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Transaksi</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?php echo $stats['selesai']; ?></div>
                <div class="stat-label">Transaksi Selesai</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <div class="stat-value"><?php echo $stats['pending'] + $stats['proses']; ?></div>
                <div class="stat-label">Dalam Proses</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-value"><?php echo formatRupiah($stats['pendapatan']); ?></div>
                <div class="stat-label">Total Pendapatan</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-body">
                <form method="GET" action="" class="filter-bar">
                    <div class="search-box" style="flex: 1; min-width: 200px;">
                        <span class="search-icon">🔍</span>
                        <input type="text" name="search" value="<?php echo sanitize($search); ?>" 
                               placeholder="Cari ID, pembeli, atau karyawan..." style="width: 100%;">
                    </div>
                    <select name="status" class="filter-select">
                        <option value="">📊 Semua Status</option>
                        <option value="Pending" <?php echo $filter_status === 'Pending' ? 'selected' : ''; ?>>🕐 Pending</option>
                        <option value="Proses" <?php echo $filter_status === 'Proses' ? 'selected' : ''; ?>>⏳ Proses</option>
                        <option value="Selesai" <?php echo $filter_status === 'Selesai' ? 'selected' : ''; ?>>✅ Selesai</option>
                        <option value="Batal" <?php echo $filter_status === 'Batal' ? 'selected' : ''; ?>>❌ Batal</option>
                    </select>
                    <select name="jenis" class="filter-select">
                        <option value="">🛍️ Semua Jenis</option>
                        <option value="Offline" <?php echo $filter_jenis === 'Offline' ? 'selected' : ''; ?>>🏪 Offline</option>
                        <option value="Online" <?php echo $filter_jenis === 'Online' ? 'selected' : ''; ?>>🌐 Online</option>
                        <option value="Telepon" <?php echo $filter_jenis === 'Telepon' ? 'selected' : ''; ?>>📞 Telepon</option>
                    </select>
                    <button type="submit" class="btn btn-primary">🔍 Filter</button>
                    <a href="transaksi.php" class="btn btn-secondary">🔄 Reset</a>
                    <a href="transaksi_tambah.php" class="btn btn-success">➕ Transaksi Baru</a>
                </form>
            </div>
        </div>

        <!-- Data Transaksi -->
        <div class="card">
            <div class="card-header">
                <h2>📋 Daftar Transaksi</h2>
                <span class="badge badge-info"><?php echo count($transaksi_list); ?> transaksi</span>
            </div>
            <div class="card-body">
                <?php if (empty($transaksi_list)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">🛒</div>
                        <h3>Belum Ada Transaksi</h3>
                        <p>Belum ada data transaksi. Buat transaksi pertama sekarang!</p>
                        <a href="transaksi_tambah.php" class="btn btn-primary">Buat Transaksi Baru</a>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th class="sortable" onclick="window.location.href='?sort=id_transaksi&order=<?php echo $next_order; ?>&status=<?php echo urlencode($filter_status); ?>&jenis=<?php echo urlencode($filter_jenis); ?>&search=<?php echo urlencode($search); ?>'">
                                        ID <?php echo sortIcon('id_transaksi', $sort, $order); ?>
                                    </th>
                                    <th class="sortable" onclick="window.location.href='?sort=tanggal_pesan&order=<?php echo $next_order; ?>&status=<?php echo urlencode($filter_status); ?>&jenis=<?php echo urlencode($filter_jenis); ?>&search=<?php echo urlencode($search); ?>'">
                                        Tanggal <?php echo sortIcon('tanggal_pesan', $sort, $order); ?>
                                    </th>
                                    <th>Pembeli</th>
                                    <th>Karyawan</th>
                                    <th class="sortable" onclick="window.location.href='?sort=jenis_pemesanan&order=<?php echo $next_order; ?>&status=<?php echo urlencode($filter_status); ?>&jenis=<?php echo urlencode($filter_jenis); ?>&search=<?php echo urlencode($search); ?>'">
                                        Jenis <?php echo sortIcon('jenis_pemesanan', $sort, $order); ?>
                                    </th>
                                    <th>Item</th>
                                    <th class="sortable" onclick="window.location.href='?sort=total_harga&order=<?php echo $next_order; ?>&status=<?php echo urlencode($filter_status); ?>&jenis=<?php echo urlencode($filter_jenis); ?>&search=<?php echo urlencode($search); ?>'">
                                        Total <?php echo sortIcon('total_harga', $sort, $order); ?>
                                    </th>
                                    <th class="sortable" onclick="window.location.href='?sort=status&order=<?php echo $next_order; ?>&status=<?php echo urlencode($filter_status); ?>&jenis=<?php echo urlencode($filter_jenis); ?>&search=<?php echo urlencode($search); ?>'">
                                        Status <?php echo sortIcon('status', $sort, $order); ?>
                                    </th>
                                    <th style="text-align: center;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transaksi_list as $trx): ?>
                                    <tr>
                                        <td><span class="transaksi-id"><?php echo sanitize($trx['id_transaksi']); ?></span></td>
                                        <td><?php echo formatTanggal($trx['tanggal_pesan']); ?></td>
                                        <td><?php echo sanitize($trx['nama_pembeli'] ?? '-'); ?></td>
                                        <td><?php echo sanitize($trx['nama_karyawan'] ?? '-'); ?></td>
                                        <td>
                                            <?php if ($trx['jenis_pemesanan'] === 'Online'): ?>
                                                <span class="badge badge-info">🌐 Online</span>
                                            <?php elseif ($trx['jenis_pemesanan'] === 'Telepon'): ?>
                                                <span class="badge badge-warning">📞 Telepon</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">🏪 Offline</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: center;"><?php echo $trx['jumlah_item']; ?> item</td>
                                        <td class="total-highlight"><?php echo formatRupiah($trx['total_harga']); ?></td>
                                        <td><?php echo getStatusBadge($trx['status']); ?></td>
                                        <td style="text-align: center;">
                                            <a href="transaksi_detail.php?id=<?php echo urlencode($trx['id_transaksi']); ?>" class="btn btn-sm" style="background: #dbeafe; color: #1e40af;">
                                                📄 Detail
                                            </a>
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
