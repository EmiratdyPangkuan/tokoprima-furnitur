<?php
// ============================================================================
// FILE: proses/update.php
// DESKRIPSI: Handler untuk semua operasi UPDATE
//            Menangani: barang, pembeli, karyawan, stok, status transaksi
// ============================================================================

require_once '../config/koneksi.php';  // Include koneksi database

// --- AUTH CHECK (ADMIN ONLY) ---
requireAdmin();  // Hanya Admin yang boleh mengupdate data

// --- FIX REDIRECT PATH ---
function fixRedirect($url) {
    if (strpos($url, '../public/') !== 0 && strpos($url, 'http') !== 0) {
        return '../public/' . $url;
    }
    return $url;
}

// --- VALIDASI REQUEST METHOD ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../public/index.php', 'error', 'Akses tidak valid!');
}

// --- AMBIL JENIS UPDATE ---
$type = isset($_POST['type']) ? trim($_POST['type']) : '';

switch ($type) {

    // ============================================================================
    // UPDATE BARANG
    // ============================================================================
    case 'barang':
        $id_barang = trim($_POST['id_barang'] ?? '');
        $nama = trim($_POST['nama'] ?? '');
        $harga = trim($_POST['harga'] ?? '');
        $bahan = trim($_POST['bahan'] ?? '');
        $warna = trim($_POST['warna'] ?? '');
        $ukuran = trim($_POST['ukuran'] ?? '');
        $jumlah_stok = trim($_POST['jumlah_stok'] ?? '0');
        $lokasi_rak = trim($_POST['lokasi_rak'] ?? 'Rak A1');

        if (empty($id_barang)) {
            redirect('../public/index.php', 'error', 'ID Barang tidak ditemukan!');
        }

        $errors = [];
        if (empty($nama) || strlen($nama) < 3) $errors[] = 'Nama barang minimal 3 karakter';
        if (empty($harga) || !is_numeric($harga) || (float)$harga <= 0) $errors[] = 'Harga tidak valid';
        if (empty($bahan)) $errors[] = 'Bahan wajib diisi';
        if (empty($warna)) $errors[] = 'Warna wajib diisi';
        if (empty($ukuran)) $errors[] = 'Ukuran wajib diisi';
        if (!is_numeric($jumlah_stok) || (int)$jumlah_stok < 0) $errors[] = 'Stok tidak boleh negatif';

        if (!empty($errors)) {
            redirect("../public/edit.php?id={$id_barang}", 'error', implode(', ', $errors));
        }

        try {
            $pdo->beginTransaction();

            $sql = "UPDATE barang SET 
                    nama = :nama, 
                    harga = :harga, 
                    bahan = :bahan, 
                    warna = :warna, 
                    ukuran = :ukuran 
                    WHERE id_barang = :id_barang";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nama' => $nama,
                ':harga' => (float)$harga,
                ':bahan' => $bahan,
                ':warna' => $warna,
                ':ukuran' => $ukuran,
                ':id_barang' => $id_barang
            ]);

            $cek_stok = $pdo->prepare("SELECT id_stok FROM stok WHERE id_barang = :id");
            $cek_stok->execute([':id' => $id_barang]);
            $stok = $cek_stok->fetch();

            if ($stok) {
                $sql_stok = "UPDATE stok SET 
                            jumlah_barang = :jumlah, 
                            lokasi_rak = :lokasi 
                            WHERE id_barang = :id_barang";
                $stmt_stok = $pdo->prepare($sql_stok);
                $stmt_stok->execute([
                    ':jumlah' => (int)$jumlah_stok,
                    ':lokasi' => $lokasi_rak,
                    ':id_barang' => $id_barang
                ]);
            } else {
                $id_stok = generateId('ST', $pdo, 'stok', 'id_stok');
                $sql_stok = "INSERT INTO stok (id_stok, id_barang, jumlah_barang, lokasi_rak) 
                            VALUES (:id_stok, :id_barang, :jumlah, :lokasi)";
                $stmt_stok = $pdo->prepare($sql_stok);
                $stmt_stok->execute([
                    ':id_stok' => $id_stok,
                    ':id_barang' => $id_barang,
                    ':jumlah' => (int)$jumlah_stok,
                    ':lokasi' => $lokasi_rak
                ]);
            }

            $pdo->commit();
            redirect('../public/index.php', 'success', "Barang '{$nama}' berhasil diperbarui!");

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Update Barang Error: " . $e->getMessage());
            redirect("../public/edit.php?id={$id_barang}", 'error', 'Gagal memperbarui barang.');
        }
        break;

    // ============================================================================
    // UPDATE PEMBELI
    // ============================================================================
    case 'pembeli':
        $id_pembeli = trim($_POST['id_pembeli'] ?? '');
        $nama = trim($_POST['nama_pembeli'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        $telepon = trim($_POST['nomor_telepon'] ?? '');

        if (empty($id_pembeli) || empty($nama) || empty($alamat) || empty($telepon)) {
            redirect('../public/pembeli.php', 'error', 'Data tidak lengkap!');
        }

        if (!preg_match('/^[0-9]{10,15}$/', $telepon)) {
            redirect('../public/pembeli.php', 'error', 'Nomor telepon tidak valid!');
        }

        try {
            $sql = "UPDATE pembeli SET 
                    nama_pembeli = :nama, 
                    alamat = :alamat, 
                    nomor_telepon = :telepon 
                    WHERE id_pembeli = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nama' => $nama,
                ':alamat' => $alamat,
                ':telepon' => $telepon,
                ':id' => $id_pembeli
            ]);

            redirect('../public/pembeli.php', 'success', "Pembeli '{$nama}' berhasil diperbarui!");

        } catch (PDOException $e) {
            error_log("Update Pembeli Error: " . $e->getMessage());
            redirect('../public/pembeli.php', 'error', 'Gagal memperbarui pembeli.');
        }
        break;

    // ============================================================================
    // UPDATE KARYAWAN
    // ============================================================================
    case 'karyawan':
        $id_karyawan = trim($_POST['id_karyawan'] ?? '');
        $nama = trim($_POST['nama_karyawan'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        $telepon = trim($_POST['nomor_telepon'] ?? '');
        $jabatan = trim($_POST['jabatan'] ?? 'Karyawan');

        if (empty($id_karyawan) || empty($nama) || empty($alamat) || empty($telepon)) {
            redirect('../public/karyawan.php', 'error', 'Data tidak lengkap!');
        }

        try {
            $sql = "UPDATE karyawan SET 
                    nama_karyawan = :nama, 
                    alamat = :alamat, 
                    nomor_telepon = :telepon, 
                    jabatan = :jabatan 
                    WHERE id_karyawan = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nama' => $nama,
                ':alamat' => $alamat,
                ':telepon' => $telepon,
                ':jabatan' => $jabatan,
                ':id' => $id_karyawan
            ]);

            redirect('../public/karyawan.php', 'success', "Karyawan '{$nama}' berhasil diperbarui!");

        } catch (PDOException $e) {
            error_log("Update Karyawan Error: " . $e->getMessage());
            redirect('../public/karyawan.php', 'error', 'Gagal memperbarui karyawan.');
        }
        break;

    // ============================================================================
    // UPDATE STOK
    // ============================================================================
    case 'stok':
        $id_barang = trim($_POST['id_barang'] ?? '');
        $jumlah_baru = trim($_POST['jumlah_barang'] ?? '');
        $lokasi_rak = trim($_POST['lokasi_rak'] ?? '');

        if (empty($id_barang) || !is_numeric($jumlah_baru) || (int)$jumlah_baru < 0) {
            redirect('../public/stok.php', 'error', 'Data stok tidak valid!');
        }

        try {
            $pdo->beginTransaction();

            $sql = "UPDATE stok SET 
                    jumlah_barang = :jumlah, 
                    lokasi_rak = :lokasi 
                    WHERE id_barang = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':jumlah' => (int)$jumlah_baru,
                ':lokasi' => $lokasi_rak,
                ':id' => $id_barang
            ]);

            $pdo->commit();
            redirect('../public/stok.php', 'success', "Stok barang berhasil diperbarui!");

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Update Stok Error: " . $e->getMessage());
            redirect('../public/stok.php', 'error', 'Gagal memperbarui stok.');
        }
        break;

    // ============================================================================
    // UPDATE STATUS TRANSAKSI
    // ============================================================================
    case 'transaksi_status':
        $id_transaksi = trim($_POST['id_transaksi'] ?? '');
        $status = trim($_POST['status'] ?? '');
        $allowed_status = ['Pending', 'Proses', 'Selesai', 'Batal'];

        if (empty($id_transaksi) || !in_array($status, $allowed_status)) {
            redirect('../public/transaksi.php', 'error', 'Data status tidak valid!');
        }

        try {
            $sql = "UPDATE transaksi SET status = :status WHERE id_transaksi = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':status' => $status, ':id' => $id_transaksi]);

            redirect('../public/transaksi.php', 'success', "Status transaksi {$id_transaksi} diubah menjadi {$status}!");

        } catch (PDOException $e) {
            error_log("Update Status Error: " . $e->getMessage());
            redirect('../public/transaksi.php', 'error', 'Gagal mengubah status.');
        }
        break;

    default:
        redirect('../public/index.php', 'error', 'Tipe update tidak dikenali!');
}
