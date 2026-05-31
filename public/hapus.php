<?php
// ============================================================================
// FILE: public/hapus.php
// DESKRIPSI: Halaman konfirmasi penghapusan data dengan tampilan modern
//            User melihat preview data sebelum memutuskan untuk menghapus
// ============================================================================

require_once '../config/koneksi.php';  // Include koneksi database

// --- AUTH CHECK (ADMIN ONLY) ---
requireAdmin();  // Hanya Admin yang boleh menghapus data

// ============================================================================
// AMBIL PARAMETER DARI URL
// ============================================================================
// $_GET digunakan karena parameter dikirim via URL (method GET)
// Contoh: hapus.php?id=PDK-001&table=barang
$id = isset($_GET['id']) ? trim($_GET['id']) : '';       // ID data yang akan dihapus
$table = isset($_GET['table']) ? trim($_GET['table']) : 'barang';  // Default tabel = barang

// --- VALIDASI ID ---
// Jika ID kosong, redirect ke halaman utama dengan pesan error
if (empty($id)) {
    redirect('index.php', 'error', 'ID tidak ditemukan!');
}

// ============================================================================
// WHITELIST TABEL (KEAMANAN)
// ============================================================================
// Mencegah SQL Injection pada nama tabel
$allowed_tables = ['barang', 'pembeli', 'karyawan', 'stok', 'transaksi'];
if (!in_array($table, $allowed_tables)) {
    redirect('index.php', 'error', 'Tabel tidak valid!');
}

// ============================================================================
// KONFIGURASI TAMPILAN PER TABEL
// ============================================================================
// Setiap tabel memiliki konfigurasi tampilan yang berbeda:
// - id_col: nama kolom primary key
// - name_col: kolom yang berisi nama (untuk ditampilkan di judul)
// - title: judul yang ditampilkan di halaman
// - icon: emoji/icon untuk visualisasi
// - redirect: halaman tujuan setelah batal
// - fields: kolom-kolom yang akan ditampilkan di preview
$table_config = [
    'barang' => [
        'id_col' => 'id_barang',
        'name_col' => 'nama',
        'title' => 'Barang',
        'icon' => '📦',
        'redirect' => 'index.php',
        'fields' => ['nama' => 'Nama', 'harga' => 'Harga', 'bahan' => 'Bahan', 'warna' => 'Warna']
    ],
    'pembeli' => [
        'id_col' => 'id_pembeli',
        'name_col' => 'nama_pembeli',
        'title' => 'Pembeli',
        'icon' => '👤',
        'redirect' => 'pembeli.php',
        'fields' => ['nama_pembeli' => 'Nama', 'alamat' => 'Alamat', 'nomor_telepon' => 'Telepon']
    ],
    'karyawan' => [
        'id_col' => 'id_karyawan',
        'name_col' => 'nama_karyawan',
        'title' => 'Karyawan',
        'icon' => '👨‍💼',
        'redirect' => 'karyawan.php',
        'fields' => ['nama_karyawan' => 'Nama', 'jabatan' => 'Jabatan', 'nomor_telepon' => 'Telepon']
    ],
    'transaksi' => [
        'id_col' => 'id_transaksi',
        'name_col' => 'id_transaksi',
        'title' => 'Transaksi',
        'icon' => '🛒',
        'redirect' => 'transaksi.php',
        'fields' => ['tanggal_pesan' => 'Tanggal', 'jenis_pemesanan' => 'Jenis', 'total_harga' => 'Total']
    ]
];

// Ambil konfigurasi untuk tabel yang dipilih
$config = $table_config[$table];
$id_col = $config['id_col'];
$name_col = $config['name_col'];

// ============================================================================
// AMBIL DATA DARI DATABASE
// ============================================================================
try {
    // Query untuk mengambil data yang akan dihapus
    // LIMIT 1 untuk efisiensi (hanya butuh 1 baris)
    $sql = "SELECT * FROM {$table} WHERE {$id_col} = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $data = $stmt->fetch();

    // Jika data tidak ditemukan, redirect dengan pesan error
    if (!$data) {
        redirect($config['redirect'], 'error', 'Data tidak ditemukan!');
    }

    // Ambil nama item untuk ditampilkan
    $item_name = $data[$name_col] ?? $id;

} catch (PDOException $e) {
    // Log error dan redirect dengan pesan error
    error_log("Hapus Fetch Error: " . $e->getMessage());
    redirect($config['redirect'], 'error', 'Gagal mengambil data!');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Hapus - Toko Prima Furnitur</title>
    <!-- Link ke file CSS utama -->
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
        <!-- ============================================================================
             CARD KONFIRMASI DELETE
             ============================================================================ -->
        <div class="card delete-confirm-card">
            <div class="card-body">
                <!-- Icon peringatan -->
                <div class="delete-icon">⚠️</div>

                <!-- Judul konfirmasi -->
                <h2 style="color: var(--text-dark); margin-bottom: 8px;">Konfirmasi Penghapusan</h2>
                <p style="color: var(--text-medium);">Anda akan menghapus data <?php echo $config['title']; ?> berikut:</p>

                <!-- ============================================================================
                     PREVIEW DATA YANG AKAN DIHAPUS
                     ============================================================================ -->
                <div class="item-preview">
                    <!-- Nama item dengan icon -->
                    <h4><?php echo $config['icon']; ?> <?php echo sanitize($item_name); ?></h4>

                    <!-- Loop untuk menampilkan field-field yang dikonfigurasi -->
                    <?php foreach ($config['fields'] as $field => $label): ?>
                        <?php if (isset($data[$field])): ?>
                            <div class="preview-row">
                                <span class="preview-label"><?php echo $label; ?></span>
                                <span class="preview-value">
                                    <?php 
                                    // Jika field adalah harga, format ke Rupiah
                                    if ($field === 'harga' || $field === 'total_harga') {
                                        echo formatRupiah($data[$field]);
                                    } else {
                                        // Untuk field lain, sanitize sebelum tampil
                                        echo sanitize($data[$field]);
                                    }
                                    ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <!-- Selalu tampilkan ID -->
                    <div class="preview-row">
                        <span class="preview-label">ID</span>
                        <span class="preview-value"><?php echo sanitize($id); ?></span>
                    </div>
                </div>

                <!-- ============================================================================
                     PERINGATAN
                     ============================================================================ -->
                <div class="warning-text">
                    <span>🗑️</span>
                    <span>Tindakan ini tidak dapat dibatalkan! Data yang dihapus tidak bisa dikembalikan.</span>
                </div>

                <!-- Peringatan khusus untuk barang (memiliki stok terkait) -->
                <?php if ($table === 'barang'): ?>
                    <div class="warning-text">
                        <span>📊</span>
                        <span>Data stok dan detail transaksi terkait juga akan ikut terhapus.</span>
                    </div>
                <?php endif; ?>

                <!-- ============================================================================
                     FORM DELETE
                     ============================================================================ -->
                <!-- Form mengirim ke proses/delete.php via POST -->
                <form action="../proses/delete.php" method="POST" class="btn-group">
                    <!-- Hidden input untuk mengirim parameter ke delete.php -->
                    <input type="hidden" name="id" value="<?php echo sanitize($id); ?>">
                    <input type="hidden" name="table" value="<?php echo $table; ?>">
                    <input type="hidden" name="redirect" value="<?php echo $config['redirect']; ?>">

                    <!-- Tombol batal (kembali ke halaman sebelumnya) -->
                    <a href="<?php echo $config['redirect']; ?>" class="btn btn-secondary btn-lg">
                        ❌ Batal
                    </a>

                    <!-- Tombol konfirmasi hapus -->
                    <button type="submit" class="btn btn-danger btn-lg">
                        🗑️ Ya, Hapus Data
                    </button>
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
            </div>
        </div>
        <div class="footer-bottom">
            <p>© 2026 Toko Prima Furnitur. Sistem Informasi Penjualan Terintegrasi.</p>
        </div>
    </footer>

</body>
</html>
