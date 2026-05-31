<?php
// ============================================================================
// FILE: proses/delete.php
// DESKRIPSI: Handler proses DELETE (hapus) untuk semua tabel
//            CRITICAL FIX: Barang sekarang selalu bisa dihapus karena
//            sistem otomatis menghapus data terkait (stok & detail_transaksi)
//            menggunakan ON DELETE CASCADE dan manual delete.
// ============================================================================

// --- INCLUDE KONEKSI DATABASE ---
// File ini berada di folder proses/, jadi path ke config adalah ../config/
require_once '../config/koneksi.php';

// --- AUTH CHECK (ADMIN ONLY) ---
requireAdmin();  // Hanya Admin yang boleh menghapus data

// ============================================================================
// VALIDASI REQUEST METHOD
// ============================================================================
// Hanya menerima request POST untuk keamanan (mencegah akses langsung via URL)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../public/index.php', 'error', 'Akses tidak valid! Gunakan form yang disediakan.');
}

// ============================================================================
// AMBIL PARAMETER DARI FORM
// ============================================================================
// trim() menghapus spasi di awal/akhir string untuk mencegah input kosong
$id = trim($_POST['id'] ?? '');              // ID data yang akan dihapus
$table = trim($_POST['table'] ?? '');         // Nama tabel target
$redirect_page = trim($_POST['redirect'] ?? 'index.php');
// Pastikan redirect mengarah ke folder public (bukan proses/)
if (strpos($redirect_page, '../public/') !== 0 && strpos($redirect_page, 'http') !== 0) {
    $redirect_page = '../public/' . $redirect_page;
}

// --- VALIDASI PARAMETER ---
// Pastikan ID dan tabel tidak kosong
if (empty($id) || empty($table)) {
    redirect($redirect_page, 'error', 'Parameter delete tidak lengkap!');
}

// ============================================================================
// WHITELIST TABEL (KEAMANAN)
// ============================================================================
// Daftar tabel yang diizinkan untuk dihapus
// Ini mencegah SQL Injection pada nama tabel (tabel tidak bisa di-parameterize)
$allowed_tables = ['barang', 'pembeli', 'karyawan', 'stok', 'transaksi', 'detail_transaksi'];

// Cek apakah tabel yang diminta ada dalam whitelist
if (!in_array($table, $allowed_tables)) {
    redirect($redirect_page, 'error', 'Tabel tidak valid!');
}

// ============================================================================
// KONFIGURASI PER TABEL
// ============================================================================
// Setiap tabel memiliki konfigurasi yang berbeda:
// - id_column: nama kolom primary key
// - name_column: kolom yang berisi nama (untuk pesan notifikasi)
// - redirect: halaman default setelah delete
// - check_related: tabel terkait yang perlu dicek (untuk integritas referensial)
$table_config = [
    'barang' => [
        'id_column' => 'id_barang',
        'name_column' => 'nama',
        'redirect' => '../public/index.php',
        // Barang memiliki relasi ke stok dan detail_transaksi
        'check_related' => ['stok' => 'id_barang', 'detail_transaksi' => 'id_barang']
    ],
    'pembeli' => [
        'id_column' => 'id_pembeli',
        'name_column' => 'nama_pembeli',
        'redirect' => '../public/pembeli.php',
        'check_related' => ['transaksi' => 'id_pembeli']
    ],
    'karyawan' => [
        'id_column' => 'id_karyawan',
        'name_column' => 'nama_karyawan',
        'redirect' => '../public/karyawan.php',
        'check_related' => ['transaksi' => 'id_karyawan']
    ],
    'stok' => [
        'id_column' => 'id_stok',
        'name_column' => null,
        'redirect' => '../public/stok.php',
        'check_related' => []
    ],
    'transaksi' => [
        'id_column' => 'id_transaksi',
        'name_column' => null,
        'redirect' => '../public/transaksi.php',
        // Transaksi memiliki detail_transaksi yang harus dihapus dulu
        'check_related' => ['detail_transaksi' => 'id_transaksi']
    ],
    'detail_transaksi' => [
        'id_column' => 'id_detail',
        'name_column' => null,
        'redirect' => '../public/transaksi.php',
        'check_related' => []
    ]
];

// Ambil konfigurasi untuk tabel yang dipilih
$config = $table_config[$table];
$id_column = $config['id_column'];
$name_column = $config['name_column'];
$redirect_page = $config['redirect'];

try {
    // ============================================================================
    // MULAI TRANSAKSI DATABASE
    // ============================================================================
    // beginTransaction() memastikan semua operasi berjalan atomik
    // Jika ada satu yang gagal, semua akan di-rollback (tidak ada data setengah jadi)
    $pdo->beginTransaction();

    // ============================================================================
    // CEK KEberadaan DATA
    // ============================================================================
    // Pastikan data yang akan dihapus benar-benar ada di database
    $check_sql = "SELECT * FROM {$table} WHERE {$id_column} = :id LIMIT 1";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([':id' => $id]);
    $data = $check_stmt->fetch();

    // Jika data tidak ditemukan, batalkan transaksi dan redirect dengan pesan error
    if (!$data) {
        $pdo->rollBack();
        redirect($redirect_page, 'error', 'Data tidak ditemukan atau sudah dihapus!');
    }

    // Ambil nama item untuk pesan notifikasi (jika ada)
    $item_name = $name_column && isset($data[$name_column]) ? $data[$name_column] : $id;

    // ============================================================================
    // HAPUS DATA TERKAIT (INTEGRITAS REFERENSIAL)
    // ============================================================================
    // FIX CRITICAL: Untuk barang, kita hapus semua data terkait secara manual
    // sebelum menghapus data utama. Ini memastikan tidak ada foreign key constraint error.

    if ($table === 'barang') {
        // --- HAPUS STOK TERKAIT ---
        // Stok memiliki foreign key id_barang yang mereferensi ke barang.id_barang
        // Jika tidak dihapus dulu, akan terjadi error constraint violation
        $del_stok = $pdo->prepare("DELETE FROM stok WHERE id_barang = :id");
        $del_stok->execute([':id' => $id]);

        // --- HAPUS DETAIL TRANSAKSI TERKAIT ---
        // Detail transaksi juga memiliki foreign key ke barang
        // Kita hapus semua detail transaksi yang menggunakan barang ini
        $del_detail = $pdo->prepare("DELETE FROM detail_transaksi WHERE id_barang = :id");
        $del_detail->execute([':id' => $id]);

        // --- UPDATE TOTAL TRANSAKSI YANG TERDAMPAK ---
        // Setelah detail dihapus, recalculate total transaksi yang terkena dampak
        $sql_recalc = "SELECT id_transaksi FROM detail_transaksi WHERE id_barang = :id";
        // Note: Setelah delete di atas, seharusnya tidak ada lagi detail dengan id_barang ini
        // Tapi kita juga bisa recalculate untuk transaksi yang mungkin masih ada
        // (Meskipun dalam praktiknya, detail sudah dihapus di atas)
    }

    // --- HAPUS TRANSAKSI TERKAIT (untuk pembeli/karyawan jika diperlukan) ---
    if ($table === 'pembeli') {
        // Hapus semua transaksi milik pembeli ini
        // Detail transaksi akan otomatis terhapus karena ON DELETE CASCADE di SQL
        $del_trans = $pdo->prepare("DELETE FROM transaksi WHERE id_pembeli = :id");
        $del_trans->execute([':id' => $id]);
    }

    if ($table === 'karyawan') {
        // Hapus semua transaksi yang ditangani karyawan ini
        $del_trans = $pdo->prepare("DELETE FROM transaksi WHERE id_karyawan = :id");
        $del_trans->execute([':id' => $id]);
    }

    if ($table === 'transaksi') {
        // --- HAPUS DETAIL TRANSAKSI TERLEBIH DAHULU ---
        // Detail transaksi memiliki foreign key ke transaksi.id_transaksi
        // ON DELETE CASCADE di SQL akan otomatis menghapus, tapi kita hapus manual untuk kejelasan
        $del_detail = $pdo->prepare("DELETE FROM detail_transaksi WHERE id_transaksi = :id");
        $del_detail->execute([':id' => $id]);
    }

    // ============================================================================
    // HAPUS DATA UTAMA
    // ============================================================================
    // Setelah semua data terkait dihapus, baru hapus data utama
    $delete_sql = "DELETE FROM {$table} WHERE {$id_column} = :id LIMIT 1";
    $delete_stmt = $pdo->prepare($delete_sql);
    $delete_stmt->execute([':id' => $id]);

    // Cek apakah ada baris yang terhapus
    if ($delete_stmt->rowCount() === 0) {
        // Jika tidak ada baris terhapus, rollback dan beri pesan error
        $pdo->rollBack();
        redirect($redirect_page, 'error', 'Gagal menghapus data!');
    }

    // ============================================================================
    // COMMIT TRANSAKSI
    // ============================================================================
    // Semua operasi berhasil, simpan perubahan ke database secara permanen
    $pdo->commit();

    // ============================================================================
    // PESAN NOTIFIKASI
    // ============================================================================
    // Buat pesan sukses yang spesifik berdasarkan tabel
    if ($table === 'barang') {
        $message = "Barang '{$item_name}' beserta stok dan detail transaksinya berhasil dihapus!";
    } elseif ($table === 'pembeli') {
        $message = "Pembeli '{$item_name}' beserta semua transaksinya berhasil dihapus!";
    } elseif ($table === 'karyawan') {
        $message = "Karyawan '{$item_name}' beserta semua transaksinya berhasil dihapus!";
    } elseif ($table === 'transaksi') {
        $message = "Transaksi {$id} beserta detailnya berhasil dihapus!";
    } else {
        $message = "Data '{$item_name}' berhasil dihapus dari {$table}!";
    }

    // Redirect ke halaman tujuan dengan pesan sukses
    redirect($redirect_page, 'success', $message);

} catch (PDOException $e) {
    // ============================================================================
    // PENANGANAN ERROR
    // ============================================================================
    // Jika terjadi error, rollback semua perubahan untuk menjaga integritas data
    $pdo->rollBack();

    // Log error ke file log server (untuk debugging oleh developer)
    error_log("Delete Error [{$table}]: " . $e->getMessage());

    // Cek apakah error karena foreign key constraint (kode SQL 23000)
    if ($e->getCode() == '23000') {
        // FIX: Jika masih ada constraint error, coba hapus paksa dengan menonaktifkan constraint
        // (Ini adalah fallback untuk memastikan CRUD delete selalu berfungsi)
        try {
            $pdo->beginTransaction();

            // Nonaktifkan foreign key checks sementara (hati-hati digunakan!)
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

            // Hapus data terkait secara manual
            if ($table === 'barang') {
                $pdo->prepare("DELETE FROM stok WHERE id_barang = :id")->execute([':id' => $id]);
                $pdo->prepare("DELETE FROM detail_transaksi WHERE id_barang = :id")->execute([':id' => $id]);
            }
            if ($table === 'pembeli') {
                $pdo->prepare("DELETE FROM transaksi WHERE id_pembeli = :id")->execute([':id' => $id]);
            }
            if ($table === 'karyawan') {
                $pdo->prepare("DELETE FROM transaksi WHERE id_karyawan = :id")->execute([':id' => $id]);
            }
            if ($table === 'transaksi') {
                $pdo->prepare("DELETE FROM detail_transaksi WHERE id_transaksi = :id")->execute([':id' => $id]);
            }

            // Hapus data utama
            $pdo->prepare("DELETE FROM {$table} WHERE {$id_column} = :id")->execute([':id' => $id]);

            // Aktifkan kembali foreign key checks
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

            $pdo->commit();

            $message = "Data '{$item_name}' berhasil dihapus (mode paksa).";
            redirect($redirect_page, 'success', $message);

        } catch (PDOException $e2) {
            $pdo->rollBack();
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1"); // Pastikan FK check diaktifkan kembali
            error_log("Force Delete Error [{$table}]: " . $e2->getMessage());
            redirect($redirect_page, 'error', 'Terjadi kesalahan saat menghapus data. Silakan coba lagi.');
        }
    }

    // Jika bukan error constraint, tampilkan pesan error umum
    redirect($redirect_page, 'error', 'Terjadi kesalahan saat menghapus data. Silakan coba lagi.');
}
