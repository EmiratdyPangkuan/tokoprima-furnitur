<?php
// ============================================================================
// FILE: proses/insert.php
// DESKRIPSI: Handler untuk semua operasi INSERT
//            Menangani: barang, pembeli, karyawan, transaksi
// ============================================================================

require_once '../config/koneksi.php';  // Include koneksi database

// --- AUTH CHECK (ADMIN ONLY) ---
requireAdmin();  // Hanya Admin yang boleh menambah data

// --- VALIDASI REQUEST METHOD ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../public/index.php', 'error', 'Akses tidak valid!');
}

// --- AMBIL JENIS INSERT ---
$type = isset($_POST['type']) ? trim($_POST['type']) : '';

switch ($type) {

    // ============================================================================
    // INSERT BARANG
    // ============================================================================
    case 'barang':
        $nama = trim($_POST['nama'] ?? '');
        $harga = trim($_POST['harga'] ?? '');
        $bahan = trim($_POST['bahan'] ?? '');
        $warna = trim($_POST['warna'] ?? '');
        $ukuran = trim($_POST['ukuran'] ?? '');
        $jumlah_stok = trim($_POST['jumlah_stok'] ?? '0');
        $lokasi_rak = trim($_POST['lokasi_rak'] ?? 'Rak A1');

        // Validasi
        $errors = [];
        if (empty($nama) || strlen($nama) < 3) $errors[] = 'Nama barang minimal 3 karakter';
        if (empty($harga) || !is_numeric($harga) || (float)$harga <= 0) $errors[] = 'Harga tidak valid';
        if (empty($bahan)) $errors[] = 'Bahan wajib diisi';
        if (empty($warna)) $errors[] = 'Warna wajib diisi';
        if (empty($ukuran)) $errors[] = 'Ukuran wajib diisi';
        if (!is_numeric($jumlah_stok) || (int)$jumlah_stok < 0) $errors[] = 'Stok tidak boleh negatif';

        if (!empty($errors)) {
            redirect('../public/tambah.php', 'error', implode(', ', $errors));
        }

        try {
            $pdo->beginTransaction();

            $id_barang = generateId('PDK', $pdo, 'barang', 'id_barang');

            $sql = "INSERT INTO barang (id_barang, nama, harga, bahan, warna, ukuran) 
                    VALUES (:id_barang, :nama, :harga, :bahan, :warna, :ukuran)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id_barang' => $id_barang,
                ':nama' => $nama,
                ':harga' => (float)$harga,
                ':bahan' => $bahan,
                ':warna' => $warna,
                ':ukuran' => $ukuran
            ]);

            $id_stok = generateId('ST', $pdo, 'stok', 'id_stok');
            $sql_stok = "INSERT INTO stok (id_stok, id_barang, jumlah_barang, lokasi_rak) 
                        VALUES (:id_stok, :id_barang, :jumlah_barang, :lokasi_rak)";
            $stmt_stok = $pdo->prepare($sql_stok);
            $stmt_stok->execute([
                ':id_stok' => $id_stok,
                ':id_barang' => $id_barang,
                ':jumlah_barang' => (int)$jumlah_stok,
                ':lokasi_rak' => $lokasi_rak
            ]);

            $pdo->commit();
            redirect('../public/index.php', 'success', "Barang '{$nama}' berhasil ditambahkan!");

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Insert Barang Error: " . $e->getMessage());
            redirect('../public/tambah.php', 'error', 'Gagal menambahkan barang. Silakan coba lagi.');
        }
        break;

    // ============================================================================
    // INSERT PEMBELI
    // ============================================================================
    case 'pembeli':
        $nama = trim($_POST['nama_pembeli'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        $telepon = trim($_POST['nomor_telepon'] ?? '');

        if (empty($nama) || empty($alamat) || empty($telepon)) {
            redirect('../public/pembeli.php', 'error', 'Semua field wajib diisi!');
        }

        if (!preg_match('/^[0-9]{10,15}$/', $telepon)) {
            redirect('../public/pembeli.php', 'error', 'Nomor telepon tidak valid (10-15 digit angka)!');
        }

        try {
            $id_pembeli = generateId('PM', $pdo, 'pembeli', 'id_pembeli');

            $sql = "INSERT INTO pembeli (id_pembeli, nama_pembeli, alamat, nomor_telepon) 
                    VALUES (:id_pembeli, :nama, :alamat, :telepon)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id_pembeli' => $id_pembeli,
                ':nama' => $nama,
                ':alamat' => $alamat,
                ':telepon' => $telepon
            ]);

            redirect('../public/pembeli.php', 'success', "Pembeli '{$nama}' berhasil ditambahkan!");

        } catch (PDOException $e) {
            error_log("Insert Pembeli Error: " . $e->getMessage());
            redirect('../public/pembeli.php', 'error', 'Gagal menambahkan pembeli.');
        }
        break;

    // ============================================================================
    // INSERT KARYAWAN
    // ============================================================================
    case 'karyawan':
        $nama = trim($_POST['nama_karyawan'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        $telepon = trim($_POST['nomor_telepon'] ?? '');
        $jabatan = trim($_POST['jabatan'] ?? 'Karyawan');

        if (empty($nama) || empty($alamat) || empty($telepon)) {
            redirect('../public/karyawan.php', 'error', 'Semua field wajib diisi!');
        }

        if (!preg_match('/^[0-9]{10,15}$/', $telepon)) {
            redirect('../public/karyawan.php', 'error', 'Nomor telepon tidak valid!');
        }

        try {
            $id_karyawan = generateId('KR', $pdo, 'karyawan', 'id_karyawan');

            $sql = "INSERT INTO karyawan (id_karyawan, nama_karyawan, alamat, nomor_telepon, jabatan) 
                    VALUES (:id_karyawan, :nama, :alamat, :telepon, :jabatan)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id_karyawan' => $id_karyawan,
                ':nama' => $nama,
                ':alamat' => $alamat,
                ':telepon' => $telepon,
                ':jabatan' => $jabatan
            ]);

            redirect('../public/karyawan.php', 'success', "Karyawan '{$nama}' berhasil ditambahkan!");

        } catch (PDOException $e) {
            error_log("Insert Karyawan Error: " . $e->getMessage());
            redirect('../public/karyawan.php', 'error', 'Gagal menambahkan karyawan.');
        }
        break;

    // ============================================================================
    // INSERT TRANSAKSI
    // ============================================================================
    case 'transaksi':
        $tanggal = trim($_POST['tanggal_pesan'] ?? '');
        $jenis = trim($_POST['jenis_pemesanan'] ?? 'Offline');
        $id_pembeli = trim($_POST['id_pembeli'] ?? '');
        $id_karyawan = trim($_POST['id_karyawan'] ?? '');
        $barang_items = $_POST['barang'] ?? [];
        $kuantitas_items = $_POST['kuantitas'] ?? [];

        if (empty($tanggal) || empty($id_pembeli) || empty($id_karyawan) || empty($barang_items)) {
            redirect('../public/transaksi_tambah.php', 'error', 'Data transaksi tidak lengkap!');
        }

        try {
            $pdo->beginTransaction();

            $id_transaksi = generateId('TR', $pdo, 'transaksi', 'id_transaksi');

            $sql = "INSERT INTO transaksi (id_transaksi, tanggal_pesan, jenis_pemesanan, id_pembeli, id_karyawan) 
                    VALUES (:id_transaksi, :tanggal, :jenis, :id_pembeli, :id_karyawan)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id_transaksi' => $id_transaksi,
                ':tanggal' => $tanggal,
                ':jenis' => $jenis,
                ':id_pembeli' => $id_pembeli,
                ':id_karyawan' => $id_karyawan
            ]);

            $total = 0;
            foreach ($barang_items as $index => $id_barang) {
                $qty = (int)($kuantitas_items[$index] ?? 1);
                if ($qty <= 0) continue;

                $harga_stmt = $pdo->prepare("SELECT harga FROM barang WHERE id_barang = :id");
                $harga_stmt->execute([':id' => $id_barang]);
                $harga = $harga_stmt->fetchColumn();

                if (!$harga) continue;

                $subtotal = $harga * $qty;
                $total += $subtotal;

                $id_detail = generateId('DT', $pdo, 'detail_transaksi', 'id_detail');
                $sql_detail = "INSERT INTO detail_transaksi (id_detail, id_transaksi, id_barang, kuantitas, harga_satuan, subtotal) 
                             VALUES (:id_detail, :id_transaksi, :id_barang, :kuantitas, :harga, :subtotal)";
                $stmt_detail = $pdo->prepare($sql_detail);
                $stmt_detail->execute([
                    ':id_detail' => $id_detail,
                    ':id_transaksi' => $id_transaksi,
                    ':id_barang' => $id_barang,
                    ':kuantitas' => $qty,
                    ':harga' => $harga,
                    ':subtotal' => $subtotal
                ]);

                $pdo->prepare("UPDATE stok SET jumlah_barang = jumlah_barang - :qty WHERE id_barang = :id")
                    ->execute([':qty' => $qty, ':id' => $id_barang]);
            }

            $pdo->prepare("UPDATE transaksi SET total_harga = :total WHERE id_transaksi = :id")
                ->execute([':total' => $total, ':id' => $id_transaksi]);

            $pdo->commit();
            redirect('../public/transaksi.php', 'success', "Transaksi {$id_transaksi} berhasil dibuat! Total: " . formatRupiah($total));

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Insert Transaksi Error: " . $e->getMessage());
            redirect('../public/transaksi_tambah.php', 'error', 'Gagal membuat transaksi.');
        }
        break;

    default:
        redirect('../public/index.php', 'error', 'Tipe insert tidak dikenali!');
}
