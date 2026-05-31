<?php
// ============================================================================
// FILE: public/laporan.php
// DESKRIPSI: Dashboard laporan komprehensif
//            Menampilkan: statistik penjualan, top barang, top pembeli, stok kritis
//            Fitur: Filter periode, cetak laporan
// ============================================================================

require_once '../config/koneksi.php';  // Include koneksi database

// --- AUTH CHECK (ADMIN ONLY) ---
requireAdmin();  // Halaman ini hanya untuk Admin

// ============================================================================
// PARAMETER FILTER PERIODE
// ============================================================================
$periode = isset($_GET['periode']) ? trim($_GET['periode']) : 'bulan_ini';
$tanggal_awal = isset($_GET['awal']) ? trim($_GET['awal']) : date('Y-m-01');
$tanggal_akhir = isset($_GET['akhir']) ? trim($_GET['akhir']) : date('Y-m-t');

// Set periode otomatis berdasarkan pilihan
if ($periode === 'hari_ini') {
    $tanggal_awal = date('Y-m-d');
    $tanggal_akhir = date('Y-m-d');
} elseif ($periode === 'minggu_ini') {
    $tanggal_awal = date('Y-m-d', strtotime('monday this week'));
    $tanggal_akhir = date('Y-m-d', strtotime('sunday this week'));
} elseif ($periode === 'bulan_ini') {
    $tanggal_awal = date('Y-m-01');
    $tanggal_akhir = date('Y-m-t');
} elseif ($periode === 'tahun_ini') {
    $tanggal_awal = date('Y-01-01');
    $tanggal_akhir = date('Y-12-31');
}

try {
    // ============================================================================
    // STATISTIK PENJUALAN
    // ============================================================================
    $sql_penjualan = "SELECT 
        COUNT(*) as jumlah_transaksi,
        COALESCE(SUM(total_harga), 0) as total_pendapatan,
        COALESCE(AVG(total_harga), 0) as rata_rata,
        COUNT(CASE WHEN status = 'Selesai' THEN 1 END) as selesai,
        COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending,
        COUNT(CASE WHEN status = 'Proses' THEN 1 END) as proses,
        COUNT(CASE WHEN status = 'Batal' THEN 1 END) as batal
        FROM transaksi 
        WHERE tanggal_pesan BETWEEN :awal AND :akhir";
    $stmt_penjualan = $pdo->prepare($sql_penjualan);
    $stmt_penjualan->execute([':awal' => $tanggal_awal, ':akhir' => $tanggal_akhir]);
    $penjualan = $stmt_penjualan->fetch();

    // ============================================================================
    // TOP 5 BARANG TERLARIS
    // ============================================================================
    $sql_top_barang = "SELECT b.nama, b.id_barang, SUM(dt.kuantitas) as total_terjual, SUM(dt.subtotal) as total_pendapatan
                       FROM detail_transaksi dt
                       JOIN barang b ON dt.id_barang = b.id_barang
                       JOIN transaksi t ON dt.id_transaksi = t.id_transaksi
                       WHERE t.tanggal_pesan BETWEEN :awal AND :akhir AND t.status != 'Batal'
                       GROUP BY dt.id_barang
                       ORDER BY total_terjual DESC
                       LIMIT 5";
    $stmt_top = $pdo->prepare($sql_top_barang);
    $stmt_top->execute([':awal' => $tanggal_awal, ':akhir' => $tanggal_akhir]);
    $top_barang = $stmt_top->fetchAll();

    // ============================================================================
    // TOP 5 PEMBELI
    // ============================================================================
    $sql_top_pembeli = "SELECT p.nama_pembeli, COUNT(t.id_transaksi) as jumlah_transaksi, SUM(t.total_harga) as total_belanja
                        FROM transaksi t
                        JOIN pembeli p ON t.id_pembeli = p.id_pembeli
                        WHERE t.tanggal_pesan BETWEEN :awal AND :akhir AND t.status != 'Batal'
                        GROUP BY t.id_pembeli
                        ORDER BY total_belanja DESC
                        LIMIT 5";
    $stmt_top_pembeli = $pdo->prepare($sql_top_pembeli);
    $stmt_top_pembeli->execute([':awal' => $tanggal_awal, ':akhir' => $tanggal_akhir]);
    $top_pembeli = $stmt_top_pembeli->fetchAll();

    // ============================================================================
    // STOK KRITIS (<= 5)
    // ============================================================================
    $sql_stok_kritis = "SELECT b.id_barang, b.nama, b.harga, s.jumlah_barang, s.lokasi_rak
                        FROM barang b
                        JOIN stok s ON b.id_barang = s.id_barang
                        WHERE s.jumlah_barang <= 5
                        ORDER BY s.jumlah_barang ASC";
    $stok_kritis = $pdo->query($sql_stok_kritis)->fetchAll();

    // ============================================================================
    // RINGKASAN PER JENIS PEMESANAN
    // ============================================================================
    $sql_jenis = "SELECT jenis_pemesanan, COUNT(*) as jumlah, SUM(total_harga) as total
                  FROM transaksi 
                  WHERE tanggal_pesan BETWEEN :awal AND :akhir AND status != 'Batal'
                  GROUP BY jenis_pemesanan";
    $stmt_jenis = $pdo->prepare($sql_jenis);
    $stmt_jenis->execute([':awal' => $tanggal_awal, ':akhir' => $tanggal_akhir]);
    $jenis_stats = $stmt_jenis->fetchAll();

} catch (PDOException $e) {
    error_log("Laporan Error: " . $e->getMessage());
    $penjualan = ['jumlah_transaksi' => 0, 'total_pendapatan' => 0, 'rata_rata' => 0, 'selesai' => 0, 'pending' => 0, 'proses' => 0, 'batal' => 0];
    $top_barang = [];
    $top_pembeli = [];
    $stok_kritis = [];
    $jenis_stats = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Toko Prima Furnitur</title>
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
                <li><a href="laporan.php" class="active">📈 Laporan</a></li>
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
        <!-- Report Header -->
        <div class="report-header">
            <h1>📈 Laporan Penjualan & Operasional</h1>
            <p>Ringkasan performa bisnis Toko Prima Furnitur</p>
            <div class="periode-badge">
                📅 <?php echo formatTanggal($tanggal_awal); ?> - <?php echo formatTanggal($tanggal_akhir); ?>
            </div>
        </div>

        <!-- ============================================================================
             FILTER PERIODE
             ============================================================================ -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-body">
                <form method="GET" action="" class="filter-bar" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: end;">
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-size: 0.85rem; font-weight: 600; color: #666;">Periode Cepat</label>
                        <select name="periode" class="filter-select" onchange="this.form.submit()">
                            <option value="hari_ini" <?php echo $periode === 'hari_ini' ? 'selected' : ''; ?>>📅 Hari Ini</option>
                            <option value="minggu_ini" <?php echo $periode === 'minggu_ini' ? 'selected' : ''; ?>>📆 Minggu Ini</option>
                            <option value="bulan_ini" <?php echo $periode === 'bulan_ini' ? 'selected' : ''; ?>>📅 Bulan Ini</option>
                            <option value="tahun_ini" <?php echo $periode === 'tahun_ini' ? 'selected' : ''; ?>>📆 Tahun Ini</option>
                            <option value="custom" <?php echo $periode === 'custom' ? 'selected' : ''; ?>>🔧 Custom</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-size: 0.85rem; font-weight: 600; color: #666;">Dari Tanggal</label>
                        <input type="date" name="awal" class="form-input" value="<?php echo $tanggal_awal; ?>">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-size: 0.85rem; font-weight: 600; color: #666;">Sampai Tanggal</label>
                        <input type="date" name="akhir" class="form-input" value="<?php echo $tanggal_akhir; ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">🔍 Tampilkan</button>
                    <button type="button" onclick="window.print()" class="btn btn-secondary">🖨️ Cetak</button>
                </form>
            </div>
        </div>

        <!-- ============================================================================
             STATS OVERVIEW
             ============================================================================ -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">🛒</div>
                <div class="stat-value"><?php echo $penjualan['jumlah_transaksi']; ?></div>
                <div class="stat-label">Jumlah Transaksi</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-value"><?php echo formatRupiah($penjualan['total_pendapatan']); ?></div>
                <div class="stat-label">Total Pendapatan</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-value"><?php echo formatRupiah($penjualan['rata_rata']); ?></div>
                <div class="stat-label">Rata-rata Transaksi</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?php echo $penjualan['selesai']; ?></div>
                <div class="stat-label">Transaksi Selesai</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <div class="stat-value"><?php echo $penjualan['pending'] + $penjualan['proses']; ?></div>
                <div class="stat-label">Dalam Proses</div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px; margin-top: 24px;">
            <!-- ============================================================================
                 TOP 5 BARANG TERLARIS
                 ============================================================================ -->
            <div class="card">
                <div class="card-header">
                    <h2>🏆 Top 5 Barang Terlaris</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($top_barang)): ?>
                        <div class="empty-state" style="padding: 32px;">
                            <div class="empty-icon" style="width: 80px; height: 80px; font-size: 2rem;">📦</div>
                            <p>Belum ada data penjualan pada periode ini.</p>
                        </div>
                    <?php else: ?>
                        <?php $max_qty = max(array_column($top_barang, 'total_terjual')); ?>
                        <?php foreach ($top_barang as $index => $barang): 
                            $rank = $index + 1;
                            $rankClass = $rank <= 3 ? "rank-{$rank}" : 'rank-other';
                            $percentage = ($barang['total_terjual'] / $max_qty) * 100;
                        ?>
                            <div class="chart-bar">
                                <div class="rank-number <?php echo $rankClass; ?>"><?php echo $rank; ?></div>
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; margin-bottom: 4px;"><?php echo sanitize($barang['nama']); ?></div>
                                    <div class="chart-track">
                                        <div class="chart-fill" style="width: <?php echo $percentage; ?>%">
                                            <?php echo $barang['total_terjual']; ?> unit
                                        </div>
                                    </div>
                                </div>
                                <div class="chart-value"><?php echo formatRupiah($barang['total_pendapatan']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ============================================================================
                 TOP 5 PEMBELI
                 ============================================================================ -->
            <div class="card">
                <div class="card-header">
                    <h2>👑 Top 5 Pembeli</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($top_pembeli)): ?>
                        <div class="empty-state" style="padding: 32px;">
                            <div class="empty-icon" style="width: 80px; height: 80px; font-size: 2rem;">👤</div>
                            <p>Belum ada data pembeli pada periode ini.</p>
                        </div>
                    <?php else: ?>
                        <?php $max_belanja = max(array_column($top_pembeli, 'total_belanja')); ?>
                        <?php foreach ($top_pembeli as $index => $pembeli): 
                            $rank = $index + 1;
                            $rankClass = $rank <= 3 ? "rank-{$rank}" : 'rank-other';
                            $percentage = ($pembeli['total_belanja'] / $max_belanja) * 100;
                        ?>
                            <div class="chart-bar">
                                <div class="rank-number <?php echo $rankClass; ?>"><?php echo $rank; ?></div>
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; margin-bottom: 4px;"><?php echo sanitize($pembeli['nama_pembeli']); ?></div>
                                    <div class="chart-track">
                                        <div class="chart-fill" style="width: <?php echo $percentage; ?>%">
                                            <?php echo $pembeli['jumlah_transaksi']; ?>x transaksi
                                        </div>
                                    </div>
                                </div>
                                <div class="chart-value"><?php echo formatRupiah($pembeli['total_belanja']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ============================================================================
             STOK KRITIS
             ============================================================================ -->
        <div class="card" style="margin-top: 24px;">
            <div class="card-header">
                <h2>⚠️ Stok Kritis (≤ 5 unit)</h2>
                <span class="badge badge-danger"><?php echo count($stok_kritis); ?> barang</span>
            </div>
            <div class="card-body">
                <?php if (empty($stok_kritis)): ?>
                    <div class="empty-state" style="padding: 32px;">
                        <div class="empty-icon" style="width: 80px; height: 80px; font-size: 2rem;">✅</div>
                        <h3>Semua Stok Aman</h3>
                        <p>Tidak ada barang dengan stok kritis saat ini.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nama Barang</th>
                                    <th>Harga</th>
                                    <th>Sisa Stok</th>
                                    <th>Lokasi</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stok_kritis as $item): ?>
                                    <tr>
                                        <td><strong><?php echo sanitize($item['id_barang']); ?></strong></td>
                                        <td><?php echo sanitize($item['nama']); ?></td>
                                        <td><?php echo formatRupiah($item['harga']); ?></td>
                                        <td style="font-weight: 700; color: #ef4444;"><?php echo $item['jumlah_barang']; ?> unit</td>
                                        <td><?php echo sanitize($item['lokasi_rak']); ?></td>
                                        <td>
                                            <?php if ($item['jumlah_barang'] == 0): ?>
                                                <span class="badge badge-danger">🚫 Habis</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">⚠️ Kritis</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ============================================================================
             STATISTIK PER JENIS PEMESANAN
             ============================================================================ -->
        <div class="card" style="margin-top: 24px;">
            <div class="card-header">
                <h2>📊 Statistik per Jenis Pemesanan</h2>
            </div>
            <div class="card-body">
                <?php if (empty($jenis_stats)): ?>
                    <div class="empty-state" style="padding: 32px;">
                        <p>Belum ada data pada periode ini.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Jenis Pemesanan</th>
                                    <th style="text-align: center;">Jumlah Transaksi</th>
                                    <th>Total Pendapatan</th>
                                    <th>Persentase</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_all = array_sum(array_column($jenis_stats, 'jumlah'));
                                foreach ($jenis_stats as $jenis): 
                                    $pct = $total_all > 0 ? round(($jenis['jumlah'] / $total_all) * 100, 1) : 0;
                                ?>
                                    <tr>
                                        <td>
                                            <?php if ($jenis['jenis_pemesanan'] === 'Online'): ?>
                                                <span class="badge badge-info">🌐 Online</span>
                                            <?php elseif ($jenis['jenis_pemesanan'] === 'Telepon'): ?>
                                                <span class="badge badge-warning">📞 Telepon</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">🏪 Offline</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: center; font-weight: 700;"><?php echo $jenis['jumlah']; ?></td>
                                        <td style="font-weight: 700; color: #ea580c;"><?php echo formatRupiah($jenis['total']); ?></td>
                                        <td>
                                            <div class="stock-bar">
                                                <div class="stock-fill high" style="width: <?php echo $pct; ?>%"></div>
                                            </div>
                                            <small style="color: #888;"><?php echo $pct; ?>%</small>
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

</body>
</html>
