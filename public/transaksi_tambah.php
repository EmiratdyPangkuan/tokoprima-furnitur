<?php
// ============================================================================
// FILE: public/transaksi_tambah.php
// DESKRIPSI: Form untuk menambah transaksi baru
//            Fitur: Multi-item transaksi, auto-insert pembeli baru,
//                   auto hitung subtotal, validasi stok cukup
// ============================================================================

require_once '../config/koneksi.php';  // Include koneksi database

// --- AUTH CHECK ---
requireLogin();  // Admin dan Kasir boleh membuat transaksi

// ============================================================================
// AMBIL DATA REFERENSI UNTUK DROPDOWN
// ============================================================================
try {
    // Ambil data karyawan untuk dropdown karyawan
    $karyawan_list = $pdo->query("SELECT id_karyawan, nama_karyawan FROM karyawan ORDER BY nama_karyawan")->fetchAll();

    // Ambil data barang + stok untuk dropdown barang
    $barang_list = $pdo->query("SELECT b.id_barang, b.nama, b.harga, COALESCE(s.jumlah_barang, 0) as stok 
                                 FROM barang b 
                                 LEFT JOIN stok s ON b.id_barang = s.id_barang 
                                 ORDER BY b.nama")->fetchAll();
} catch (PDOException $e) {
    error_log("Transaksi Tambah Error: " . $e->getMessage());
    $karyawan_list = [];
    $barang_list = [];
}

// ============================================================================
// INISIALISASI VARIABEL
// ============================================================================
$errors = [];
$data = [
    'tanggal_pesan' => date('Y-m-d'),
    'jenis_pemesanan' => 'Offline',
    'nama_pembeli' => '',
    'alamat_pembeli' => '',
    'telepon_pembeli' => '',
    'id_karyawan' => ''
];

// ============================================================================
// PROSES FORM SUBMIT
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data['tanggal_pesan'] = trim($_POST['tanggal_pesan'] ?? date('Y-m-d'));
    $data['jenis_pemesanan'] = trim($_POST['jenis_pemesanan'] ?? 'Offline');
    $data['nama_pembeli'] = trim($_POST['nama_pembeli'] ?? '');
    $data['alamat_pembeli'] = trim($_POST['alamat_pembeli'] ?? '');
    $data['telepon_pembeli'] = trim($_POST['telepon_pembeli'] ?? '');
    $data['id_karyawan'] = trim($_POST['id_karyawan'] ?? '');
    $barang_items = $_POST['barang'] ?? [];
    $kuantitas_items = $_POST['kuantitas'] ?? [];

    // ============================================================================
    // VALIDASI INPUT
    // ============================================================================
    if (empty($data['tanggal_pesan'])) $errors[] = 'Tanggal wajib diisi';
    if (empty($data['nama_pembeli'])) $errors[] = 'Nama pembeli wajib diisi';
    if (empty($data['alamat_pembeli'])) $errors[] = 'Alamat pembeli wajib diisi';
    if (empty($data['telepon_pembeli'])) {
        $errors[] = 'Nomor telepon pembeli wajib diisi';
    } elseif (!preg_match('/^[0-9]{10,15}$/', $data['telepon_pembeli'])) {
        $errors[] = 'Nomor telepon hanya boleh angka (10-15 digit)';
    }
    if (empty($data['id_karyawan'])) $errors[] = 'Karyawan wajib dipilih';
    if (empty($barang_items)) $errors[] = 'Minimal pilih 1 barang';

    // --- VALIDASI STOK CUKUP ---
    foreach ($barang_items as $index => $id_barang) {
        $qty = (int)($kuantitas_items[$index] ?? 1);
        foreach ($barang_list as $b) {
            if ($b['id_barang'] === $id_barang && $qty > $b['stok']) {
                $errors[] = "Stok '{$b['nama']}' tidak mencukupi (tersedia: {$b['stok']})";
            }
        }
    }

    // ============================================================================
    // PROSES INSERT TRANSAKSI (JIKA TIDAK ADA ERROR)
    // ============================================================================
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // ============================================================================
            // AUTO-INSERT PEMBELI BARU (ATAU GUNAKAN YANG SUDAH ADA)
            // ============================================================================
            // Cek apakah pembeli sudah ada berdasarkan nama + telepon
            $cek_pembeli = $pdo->prepare("SELECT id_pembeli FROM pembeli WHERE nama_pembeli = :nama AND nomor_telepon = :telp LIMIT 1");
            $cek_pembeli->execute([
                ':nama' => $data['nama_pembeli'],
                ':telp' => $data['telepon_pembeli']
            ]);
            $pembeli_existing = $cek_pembeli->fetch();

            if ($pembeli_existing) {
                // Gunakan pembeli yang sudah ada
                $id_pembeli = $pembeli_existing['id_pembeli'];
                // Update alamat jika berbeda
                $pdo->prepare("UPDATE pembeli SET alamat = :alamat WHERE id_pembeli = :id")
                    ->execute([':alamat' => $data['alamat_pembeli'], ':id' => $id_pembeli]);
            } else {
                // Buat pembeli baru
                $id_pembeli = generateId('PM', $pdo, 'pembeli', 'id_pembeli');
                $sql_pembeli = "INSERT INTO pembeli (id_pembeli, nama_pembeli, alamat, nomor_telepon) 
                                VALUES (:id, :nama, :alamat, :telp)";
                $stmt_pembeli = $pdo->prepare($sql_pembeli);
                $stmt_pembeli->execute([
                    ':id' => $id_pembeli,
                    ':nama' => $data['nama_pembeli'],
                    ':alamat' => $data['alamat_pembeli'],
                    ':telp' => $data['telepon_pembeli']
                ]);
            }

            // ============================================================================
            // GENERATE ID TRANSAKSI DAN INSERT
            // ============================================================================
            $id_transaksi = generateId('TR', $pdo, 'transaksi', 'id_transaksi');

            $sql = "INSERT INTO transaksi (id_transaksi, tanggal_pesan, jenis_pemesanan, id_pembeli, id_karyawan) 
                    VALUES (:id, :tanggal, :jenis, :pembeli, :karyawan)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id' => $id_transaksi,
                ':tanggal' => $data['tanggal_pesan'],
                ':jenis' => $data['jenis_pemesanan'],
                ':pembeli' => $id_pembeli,
                ':karyawan' => $data['id_karyawan']
            ]);

            // ============================================================================
            // INSERT DETAIL TRANSAKSI + UPDATE STOK
            // ============================================================================
            $total = 0;
            foreach ($barang_items as $index => $id_barang) {
                $qty = (int)($kuantitas_items[$index] ?? 1);
                if ($qty <= 0) continue;

                // Ambil harga barang dari database
                $harga_stmt = $pdo->prepare("SELECT harga FROM barang WHERE id_barang = :id");
                $harga_stmt->execute([':id' => $id_barang]);
                $harga = $harga_stmt->fetchColumn();

                $subtotal = $harga * $qty;
                $total += $subtotal;

                // Insert detail transaksi
                $id_detail = generateId('DT', $pdo, 'detail_transaksi', 'id_detail');
                $sql_detail = "INSERT INTO detail_transaksi (id_detail, id_transaksi, id_barang, kuantitas, harga_satuan, subtotal) 
                             VALUES (:id_detail, :id_transaksi, :id_barang, :qty, :harga, :subtotal)";
                $stmt_detail = $pdo->prepare($sql_detail);
                $stmt_detail->execute([
                    ':id_detail' => $id_detail,
                    ':id_transaksi' => $id_transaksi,
                    ':id_barang' => $id_barang,
                    ':qty' => $qty,
                    ':harga' => $harga,
                    ':subtotal' => $subtotal
                ]);

                // Update stok (kurangi jumlah barang)
                $pdo->prepare("UPDATE stok SET jumlah_barang = jumlah_barang - :qty WHERE id_barang = :id")
                    ->execute([':qty' => $qty, ':id' => $id_barang]);
            }

            // Update total transaksi
            $pdo->prepare("UPDATE transaksi SET total_harga = :total WHERE id_transaksi = :id")
                ->execute([':total' => $total, ':id' => $id_transaksi]);

            $pdo->commit();
            redirect('transaksi.php', 'success', "Transaksi {$id_transaksi} berhasil dibuat! Total: " . formatRupiah($total));

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Insert Transaksi Error: " . $e->getMessage());
            $errors[] = 'Gagal membuat transaksi: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Transaksi - Toko Prima Furnitur</title>
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
        <div class="breadcrumb">
            <a href="index.php">Beranda</a>
            <span>/</span>
            <a href="transaksi.php">Transaksi</a>
            <span>/</span>
            <span>Tambah Transaksi</span>
        </div>

        <div class="page-header">
            <h1>➕ Tambah Transaksi Baru</h1>
            <p>Buat transaksi pembelian furnitur baru</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <span>❌ <?php echo implode(', ', $errors); ?></span>
                <button class="alert-close" onclick="this.parentElement.remove()">×</button>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="transaksiForm">
            <!-- ============================================================================
                 CARD INFORMASI TRANSAKSI
                 ============================================================================ -->
            <div class="card">
                <div class="card-header">
                    <h2>📝 Informasi Transaksi</h2>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Tanggal Pesan <span class="required">*</span></label>
                            <input type="date" name="tanggal_pesan" class="form-input" 
                                   value="<?php echo $data['tanggal_pesan']; ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Jenis Pemesanan <span class="required">*</span></label>
                            <select name="jenis_pemesanan" class="form-select" required>
                                <option value="Offline" <?php echo $data['jenis_pemesanan'] === 'Offline' ? 'selected' : ''; ?>>🏪 Offline (Toko)</option>
                                <option value="Online" <?php echo $data['jenis_pemesanan'] === 'Online' ? 'selected' : ''; ?>>🌐 Online (Website)</option>
                                <option value="Telepon" <?php echo $data['jenis_pemesanan'] === 'Telepon' ? 'selected' : ''; ?>>📞 Telepon</option>
                            </select>
                        </div>
                    </div>

                    <!-- PEMBELI: Input manual baru dengan datalist -->
                    <div style="margin-top: 8px;">
                        <h3 style="font-size: 1rem; color: #ea580c; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                            👤 Data Pembeli
                        </h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Nama Pembeli <span class="required">*</span></label>
                                <!-- Datalist memungkinkan input manual + pilih dari daftar -->
                                <input list="pembeli-list" name="nama_pembeli" class="form-input" 
                                       value="<?php echo sanitize($data['nama_pembeli']); ?>" 
                                       placeholder="Ketik atau pilih nama pembeli..." required>
                                <datalist id="pembeli-list">
                                    <?php 
                                    try {
                                        $pembeli_datalist = $pdo->query("SELECT DISTINCT nama_pembeli FROM pembeli ORDER BY nama_pembeli LIMIT 50")->fetchAll();
                                        foreach ($pembeli_datalist as $p): 
                                    ?>
                                        <option value="<?php echo sanitize($p['nama_pembeli']); ?>">
                                    <?php 
                                        endforeach;
                                    } catch (PDOException $e) {}
                                    ?>
                                </datalist>
                                <div class="pembeli-hint">💡 Ketik manual atau pilih dari daftar. Jika nama sudah ada, data lama akan digunakan.</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Nomor Telepon <span class="required">*</span></label>
                                <input type="text" name="telepon_pembeli" class="form-input" 
                                       value="<?php echo sanitize($data['telepon_pembeli']); ?>" 
                                       placeholder="Contoh: 081234567890" 
                                       inputmode="numeric" pattern="[0-9]+" 
                                       oninput="this.value = this.value.replace(/[^0-9]/g, '')" 
                                       required>
                            </div>
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label class="form-label">Alamat Lengkap <span class="required">*</span></label>
                                <textarea name="alamat_pembeli" class="form-textarea" rows="2" 
                                          placeholder="Masukkan alamat lengkap pembeli..." required><?php echo sanitize($data['alamat_pembeli']); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="form-grid" style="margin-top: 16px;">
                        <div class="form-group">
                            <label class="form-label">Karyawan <span class="required">*</span></label>
                            <select name="id_karyawan" class="form-select" required>
                                <option value="">-- Pilih Karyawan --</option>
                                <?php foreach ($karyawan_list as $k): ?>
                                    <option value="<?php echo $k['id_karyawan']; ?>" 
                                        <?php echo $data['id_karyawan'] === $k['id_karyawan'] ? 'selected' : ''; ?>>
                                        <?php echo sanitize($k['nama_karyawan']); ?> (<?php echo $k['id_karyawan']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================================
                 CARD ITEM BARANG
                 ============================================================================ -->
            <div class="card" style="margin-top: 24px;">
                <div class="card-header">
                    <h2>📦 Item Barang</h2>
                </div>
                <div class="card-body">
                    <div id="barangContainer">
                        <!-- Barang items akan ditambahkan via JavaScript -->
                    </div>
                    <button type="button" class="btn btn-primary" onclick="addBarangItem()" style="width: 100%; border: 2px dashed #fdba74; background: #fff7ed; color: #ea580c;">
                        ➕ Tambah Item Barang
                    </button>

                    <div class="total-section">
                        <h3>Total Transaksi</h3>
                        <div class="total-amount" id="totalDisplay">Rp 0</div>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 12px; margin-top: 32px;">
                <button type="submit" class="btn btn-primary btn-lg">💾 Simpan Transaksi</button>
                <a href="transaksi.php" class="btn btn-secondary btn-lg">❌ Batal</a>
            </div>
        </form>
    </div>

    <footer class="footer">
        <div class="footer-bottom">
            <p>© 2026 Toko Prima Furnitur. Sistem Informasi Penjualan Terintegrasi.</p>
        </div>
    </footer>

<!-- ============================================================================
     JAVASCRIPT DINAMIS UNTUK MULTI-ITEM TRANSAKSI
     ============================================================================ -->
<script>
    // Data barang dari PHP (dikonversi ke JavaScript object)
    const barangData = <?php echo json_encode($barang_list); ?>;
    let itemCount = 0;

    /**
     * addBarangItem()
     * Menambahkan baris item barang baru ke form
     */
    function addBarangItem() {
        itemCount++;
        const container = document.getElementById('barangContainer');
        const div = document.createElement('div');
        div.className = 'barang-item';
        div.id = `item-${itemCount}`;

        // Generate options untuk dropdown barang
        let options = '<option value="">-- Pilih Barang --</option>';
        barangData.forEach(b => {
            options += `<option value="${b.id_barang}" data-harga="${b.harga}" data-stok="${b.stok}">${b.nama} - ${formatRupiah(b.harga)} (Stok: ${b.stok})</option>`;
        });

        div.innerHTML = `
            <div>
                <label class="form-label">Barang</label>
                <select name="barang[]" class="form-select" onchange="updateSubtotal(${itemCount})" required>
                    ${options}
                </select>
            </div>
            <div>
                <label class="form-label">Kuantitas</label>
                <input type="number" name="kuantitas[]" class="form-input" value="1" min="1" 
                       onchange="updateSubtotal(${itemCount})" required>
                <div class="stok-info" id="stok-${itemCount}"></div>
            </div>
            <div>
                <label class="form-label">Subtotal</label>
                <input type="text" class="form-input" id="subtotal-${itemCount}" readonly 
                       style="font-weight: 700; color: #ea580c;" value="Rp 0">
            </div>
            <div>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeBarangItem(${itemCount})">🗑️</button>
            </div>
        `;

        container.appendChild(div);
    }

    /**
     * removeBarangItem(id)
     * Menghapus baris item barang
     */
    function removeBarangItem(id) {
        const item = document.getElementById(`item-${id}`);
        if (item) {
            item.remove();
            updateTotal();
        }
    }

    /**
     * updateSubtotal(id)
     * Menghitung subtotal per item dan update tampilan
     */
    function updateSubtotal(id) {
        const item = document.getElementById(`item-${id}`);
        const select = item.querySelector('select');
        const qtyInput = item.querySelector('input[name="kuantitas[]"]');
        const subtotalInput = document.getElementById(`subtotal-${id}`);
        const stokInfo = document.getElementById(`stok-${id}`);

        const selected = select.options[select.selectedIndex];
        const harga = parseFloat(selected.dataset.harga) || 0;
        const stok = parseInt(selected.dataset.stok) || 0;
        const qty = parseInt(qtyInput.value) || 1;

        // Tampilkan info stok
        if (stok > 0) {
            stokInfo.textContent = `Tersedia: ${stok} unit`;
            stokInfo.style.color = qty > stok ? '#ef4444' : '#888';
        }

        // Hitung dan tampilkan subtotal
        const subtotal = harga * qty;
        subtotalInput.value = formatRupiah(subtotal);
        updateTotal();
    }

    /**
     * updateTotal()
     * Menghitung total keseluruhan transaksi
     */
    function updateTotal() {
        let total = 0;
        document.querySelectorAll('.barang-item').forEach(item => {
            const select = item.querySelector('select');
            const qtyInput = item.querySelector('input[name="kuantitas[]"]');
            const selected = select.options[select.selectedIndex];
            const harga = parseFloat(selected.dataset.harga) || 0;
            const qty = parseInt(qtyInput.value) || 0;
            total += harga * qty;
        });
        document.getElementById('totalDisplay').textContent = formatRupiah(total);
    }

    /**
     * formatRupiah(angka)
     * Format angka ke format Rupiah Indonesia
     */
    function formatRupiah(angka) {
        return 'Rp ' + angka.toLocaleString('id-ID');
    }

    // Tambahkan item pertama saat halaman dimuat
    addBarangItem();
</script>
</body>
</html>
