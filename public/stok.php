<?php
// ============================================================================
// FILE: public/stok.php
// DESKRIPSI: Halaman manajemen stok barang
//            Fitur: Sorting multi-kolom, filter lokasi, filter status, search
// ============================================================================

require_once '../config/koneksi.php';  // Include koneksi database

// --- AUTH CHECK ---
requireLogin();  // Pastikan user sudah login

// ============================================================================
// PARAMETER SORTING DAN FILTER
// ============================================================================
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'id_barang';
$order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'ASC';
$filter_lokasi = isset($_GET['lokasi']) ? trim($_GET['lokasi']) : '';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Whitelist kolom dan order yang diizinkan
$allowed_sort = ['id_barang', 'nama', 'harga', 'jumlah_barang', 'lokasi_rak', 'last_update'];
$allowed_order = ['ASC', 'DESC'];

// Validasi parameter
if (!in_array($sort, $allowed_sort)) $sort = 'id_barang';
if (!in_array($order, $allowed_order)) $order = 'ASC';
$next_order = ($order === 'ASC') ? 'DESC' : 'ASC';

// ============================================================================
// BUILD QUERY DENGAN FILTER DINAMIS
// ============================================================================
try {
    $where_clauses = [];  // Array untuk menyimpan kondisi WHERE
    $params = [];         // Array untuk parameter prepared statement

    // Filter berdasarkan lokasi rak
    if (!empty($filter_lokasi)) {
        $where_clauses[] = "s.lokasi_rak = :lokasi";
        $params[':lokasi'] = $filter_lokasi;
    }

    // Filter berdasarkan status stok
    if (!empty($filter_status)) {
        if ($filter_status === 'kosong') {
            $where_clauses[] = "s.jumlah_barang = 0";
        } elseif ($filter_status === 'sedikit') {
            $where_clauses[] = "s.jumlah_barang > 0 AND s.jumlah_barang <= 5";
        } elseif ($filter_status === 'aman') {
            $where_clauses[] = "s.jumlah_barang > 5";
        }
    }

    // Filter berdasarkan pencarian (nama atau ID barang)
    if (!empty($search)) {
        $where_clauses[] = "(b.nama LIKE :search OR b.id_barang LIKE :search)";
        $params[':search'] = "%{$search}%";  // % = wildcard SQL
    }

    // Gabungkan kondisi WHERE
    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

    // ============================================================================
    // QUERY UTAMA
    // ============================================================================
    $sql = "SELECT b.id_barang, b.nama, b.harga, b.bahan, b.warna, 
                   s.id_stok, s.jumlah_barang, s.lokasi_rak, s.last_update
            FROM barang b 
            LEFT JOIN stok s ON b.id_barang = s.id_barang 
            {$where_sql}
            ORDER BY {$sort} {$order}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $stok_list = $stmt->fetchAll();

    // ============================================================================
    // QUERY STATISTIK STOK
    // ============================================================================
    $stats_sql = "SELECT 
        COUNT(*) as total_item,
        COALESCE(SUM(jumlah_barang), 0) as total_unit,
        COUNT(CASE WHEN jumlah_barang = 0 THEN 1 END) as stok_kosong,
        COUNT(CASE WHEN jumlah_barang > 0 AND jumlah_barang <= 5 THEN 1 END) as stok_rendah,
        COUNT(CASE WHEN jumlah_barang > 5 THEN 1 END) as stok_aman
        FROM stok";
    $stats = $pdo->query($stats_sql)->fetch();

    // Ambil daftar lokasi unik untuk dropdown filter
    $lokasi_list = $pdo->query("SELECT DISTINCT lokasi_rak FROM stok ORDER BY lokasi_rak")->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    error_log("Stok Error: " . $e->getMessage());
    $stok_list = [];
    $stats = ['total_item' => 0, 'total_unit' => 0, 'stok_kosong' => 0, 'stok_rendah' => 0, 'stok_aman' => 0];
    $lokasi_list = [];
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

function getStokBadge($jumlah) {
    if ($jumlah == 0) return '<span class="badge badge-danger">🚫 Kosong</span>';
    if ($jumlah <= 5) return '<span class="badge badge-warning">⚠️ Kritis (' . $jumlah . ')</span>';
    return '<span class="badge badge-success">✓ Aman (' . $jumlah . ')</span>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Stok - Toko Prima Furnitur</title>
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
                <span>Manajemen Stok</span>
            </div>
            <h1>📊 Manajemen Stok Barang</h1>
            <p>Pantau dan kelola stok barang furnitur secara real-time</p>
        </div>

        <!-- ============================================================================
             STATS CARDS
             ============================================================================ -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-value"><?php echo $stats['total_item']; ?></div>
                <div class="stat-label">Jenis Barang</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-value"><?php echo $stats['total_unit']; ?></div>
                <div class="stat-label">Total Unit</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?php echo $stats['stok_aman']; ?></div>
                <div class="stat-label">Stok Aman</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⚠️</div>
                <div class="stat-value"><?php echo $stats['stok_rendah']; ?></div>
                <div class="stat-label">Stok Kritis</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🚫</div>
                <div class="stat-value"><?php echo $stats['stok_kosong']; ?></div>
                <div class="stat-label">Stok Habis</div>
            </div>
        </div>

        <!-- ============================================================================
             FILTER & SEARCH BAR
             ============================================================================ -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-body">
                <form method="GET" action="" class="filter-bar">
                    <div class="search-box" style="flex: 1; min-width: 200px;">
                        <span class="search-icon">🔍</span>
                        <input type="text" name="search" value="<?php echo sanitize($search); ?>" 
                               placeholder="Cari barang..." style="width: 100%;">
                    </div>
                    <select name="lokasi" class="filter-select">
                        <option value="">📍 Semua Lokasi</option>
                        <?php foreach ($lokasi_list as $lok): ?>
                            <option value="<?php echo $lok; ?>" <?php echo $filter_lokasi === $lok ? 'selected' : ''; ?>>
                                <?php echo $lok; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status" class="filter-select">
                        <option value="">📊 Semua Status</option>
                        <option value="aman" <?php echo $filter_status === 'aman' ? 'selected' : ''; ?>>✅ Stok Aman</option>
                        <option value="sedikit" <?php echo $filter_status === 'sedikit' ? 'selected' : ''; ?>>⚠️ Stok Kritis</option>
                        <option value="kosong" <?php echo $filter_status === 'kosong' ? 'selected' : ''; ?>>🚫 Stok Habis</option>
                    </select>
                    <button type="submit" class="btn btn-primary">🔍 Filter</button>
                    <a href="stok.php" class="btn btn-secondary">🔄 Reset</a>
                </form>
            </div>
        </div>

        <!-- ============================================================================
             DATA STOK
             ============================================================================ -->
        <div class="card">
            <div class="card-header">
                <h2>📋 Daftar Stok Barang</h2>
                <span class="badge badge-info"><?php echo count($stok_list); ?> item</span>
            </div>
            <div class="card-body">
                <?php if (empty($stok_list)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📊</div>
                        <h3>Tidak Ada Data Stok</h3>
                        <p>Belum ada data stok yang sesuai dengan filter yang dipilih.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th class="sortable" onclick="window.location.href='?sort=id_barang&order=<?php echo $next_order; ?>&lokasi=<?php echo urlencode($filter_lokasi); ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search); ?>'">
                                        ID <?php echo sortIcon('id_barang', $sort, $order); ?>
                                    </th>
                                    <th class="sortable" onclick="window.location.href='?sort=nama&order=<?php echo $next_order; ?>&lokasi=<?php echo urlencode($filter_lokasi); ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search); ?>'">
                                        Nama Barang <?php echo sortIcon('nama', $sort, $order); ?>
                                    </th>
                                    <th class="sortable" onclick="window.location.href='?sort=jumlah_barang&order=<?php echo $next_order; ?>&lokasi=<?php echo urlencode($filter_lokasi); ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search); ?>'">
                                        Stok <?php echo sortIcon('jumlah_barang', $sort, $order); ?>
                                    </th>
                                    <th class="sortable" onclick="window.location.href='?sort=lokasi_rak&order=<?php echo $next_order; ?>&lokasi=<?php echo urlencode($filter_lokasi); ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search); ?>'">
                                        Lokasi <?php echo sortIcon('lokasi_rak', $sort, $order); ?>
                                    </th>
                                    <th class="sortable" onclick="window.location.href='?sort=last_update&order=<?php echo $next_order; ?>&lokasi=<?php echo urlencode($filter_lokasi); ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search); ?>'">
                                        Update <?php echo sortIcon('last_update', $sort, $order); ?>
                                    </th>
                                    <th style="text-align: center;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stok_list as $item): 
                                    $max_stok = max($item['jumlah_barang'] * 2, 10);
                                    $percentage = min(($item['jumlah_barang'] / $max_stok) * 100, 100);
                                    $bar_class = $item['jumlah_barang'] > 5 ? 'high' : ($item['jumlah_barang'] > 0 ? 'medium' : 'low');
                                ?>
                                    <tr>
                                        <td><strong><?php echo sanitize($item['id_barang']); ?></strong></td>
                                        <td>
                                            <div style="font-weight: 600;"><?php echo sanitize($item['nama']); ?></div>
                                            <div style="font-size: 0.8rem; color: var(--text-light);">
                                                <?php echo formatRupiah($item['harga']); ?> • <?php echo sanitize($item['bahan']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo getStokBadge($item['jumlah_barang']); ?>
                                            <div class="stock-bar">
                                                <div class="stock-fill <?php echo $bar_class; ?>" style="width: <?php echo $percentage; ?>%"></div>
                                            </div>
                                        </td>
                                        <td><span class="lokasi-badge"><?php echo sanitize($item['lokasi_rak'] ?? '-'); ?></span></td>
                                        <td><?php echo $item['last_update'] ? date('d/m/Y H:i', strtotime($item['last_update'])) : '-'; ?></td>
                                        <td style="text-align: center;">
                                            <a href="stok_edit.php?id=<?php echo urlencode($item['id_barang']); ?>" class="btn btn-sm" style="background: #dbeafe; color: #1e40af;">
                                                ✏️ Edit
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

    <!-- ============================================================================
         JAVASCRIPT
         ============================================================================ -->
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
