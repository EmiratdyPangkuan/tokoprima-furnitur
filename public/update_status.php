<?php
// ============================================================================
// FILE: public/update_status.php
// DESKRIPSI: Handler untuk update status transaksi
//            Dipanggil dari transaksi_detail.php via form POST
// ============================================================================

require_once '../config/koneksi.php';  // Include koneksi database

// --- AUTH CHECK ---
requireLogin();  // Admin dan Kasir boleh update status transaksi

// --- VALIDASI REQUEST METHOD ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('transaksi.php', 'error', 'Akses tidak valid!');
}

// --- AMBIL PARAMETER ---
$type = $_POST['type'] ?? '';

// ============================================================================
// UPDATE STATUS TRANSAKSI
// ============================================================================
if ($type === 'transaksi_status') {
    $id_transaksi = trim($_POST['id_transaksi'] ?? '');
    $status = trim($_POST['status'] ?? '');

    // Whitelist status yang diizinkan
    $allowed_status = ['Pending', 'Proses', 'Selesai', 'Batal'];

    // Validasi input
    if (empty($id_transaksi)) {
        redirect('transaksi.php', 'error', 'ID Transaksi tidak ditemukan!');
    }

    if (!in_array($status, $allowed_status)) {
        redirect('transaksi_detail.php?id=' . urlencode($id_transaksi), 'error', 'Status tidak valid!');
    }

    try {
        // Update status transaksi
        $stmt = $pdo->prepare("UPDATE transaksi SET status = :status WHERE id_transaksi = :id");
        $stmt->execute([':status' => $status, ':id' => $id_transaksi]);

        redirect('transaksi_detail.php?id=' . urlencode($id_transaksi), 'success', 
                 "Status transaksi {$id_transaksi} diubah menjadi {$status}!");
    } catch (PDOException $e) {
        error_log("Update Status Error: " . $e->getMessage());
        redirect('transaksi_detail.php?id=' . urlencode($id_transaksi), 'error', 
                 'Gagal mengupdate status transaksi!');
    }
} else {
    redirect('transaksi.php', 'error', 'Tipe update tidak dikenali!');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Status - Toko Prima Furnitur</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
</body>
</html>
