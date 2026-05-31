<?php
// ============================================================================
// FILE: public/generate_dummy.php
// DESKRIPSI: Generate 100+ data dummy untuk benchmarking & load testing
// ============================================================================

require_once '../config/koneksi.php';

echo "<!DOCTYPE html>
<html lang='id'>
<head>
    <meta charset='UTF-8'>
    <title>Generate Data Dummy - Toko Prima Furnitur</title>
    <link rel='stylesheet' href='assets/css/style.css'>
</head>
<body>
<div class='main-container'>
    <div class='page-header'>
        <h1>🔧 Generate Data Dummy</h1>
        <p>Generate 100+ data untuk benchmarking dan load testing</p>
    </div>";

requireAdmin();

$start_time = microtime(true);

try {
    // --- AMBIL MAX ID SAAT INI UNTUK MENGHINDARI DUPLIKAT ---
    $max_barang = $pdo->query("SELECT MAX(CAST(SUBSTRING(id_barang, 5) AS UNSIGNED)) FROM barang")->fetchColumn() ?? 0;
    $max_stok = $pdo->query("SELECT MAX(CAST(SUBSTRING(id_stok, 4) AS UNSIGNED)) FROM stok")->fetchColumn() ?? 0;
    $max_pembeli = $pdo->query("SELECT MAX(CAST(SUBSTRING(id_pembeli, 4) AS UNSIGNED)) FROM pembeli")->fetchColumn() ?? 0;
    $max_karyawan = $pdo->query("SELECT MAX(CAST(SUBSTRING(id_karyawan, 4) AS UNSIGNED)) FROM karyawan")->fetchColumn() ?? 0;
    $max_transaksi = $pdo->query("SELECT MAX(CAST(SUBSTRING(id_transaksi, 4) AS UNSIGNED)) FROM transaksi")->fetchColumn() ?? 0;
    $max_detail = $pdo->query("SELECT MAX(CAST(SUBSTRING(id_detail, 4) AS UNSIGNED)) FROM detail_transaksi")->fetchColumn() ?? 0;
    
    echo "<div class='card'>
            <div class='card-header'><h2>📊 Data Saat Ini</h2></div>
            <div class='card-body'>
                <p>Max ID Barang: <strong>{$max_barang}</strong></p>
                <p>Max ID Pembeli: <strong>{$max_pembeli}</strong></p>
                <p>Max ID Karyawan: <strong>{$max_karyawan}</strong></p>
                <p>Max ID Transaksi: <strong>{$max_transaksi}</strong></p>
            </div>
          </div>";
    
    $pdo->beginTransaction();
    
    // --- GENERATE 100 BARANG ---
    echo "<div class='card'><div class='card-header'><h2>📦 Step 1: Generate 100 Barang</h2></div><div class='card-body'>";
    
    $bahan_list = ['Kayu Jati', 'Oscar Fabric', 'Multiplek HPL', 'Mesh + Plastik', 'MDF + Besi', 'Kayu Pinus', 'Kayu Mahoni', 'Per + Foam', 'Rotan', 'Stainless Steel'];
    $warna_list = ['Coklat', 'Abu-abu', 'Putih', 'Hitam', 'Natural', 'Coklat Muda', 'Putih-Hitam', 'Merah', 'Biru', 'Hijau'];
    
    $inserted_barang = 0;
    for ($i = 1; $i <= 100; $i++) {
        $new_id = $max_barang + $i;
        $id = sprintf("PDK-%03d", $new_id);
        $nama = "Furnitur Test " . $i . " " . $bahan_list[array_rand($bahan_list)];
        $harga = rand(500000, 10000000);
        $bahan = $bahan_list[array_rand($bahan_list)];
        $warna = $warna_list[array_rand($warna_list)];
        $ukuran = rand(50, 300) . "x" . rand(50, 200) . "x" . rand(50, 250) . " cm";
        
        $stmt = $pdo->prepare("INSERT INTO barang (id_barang, nama, harga, bahan, warna, ukuran) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id, $nama, $harga, $bahan, $warna, $ukuran]);
        $inserted_barang++;
        
        // Generate stok
        $new_stok_id = $max_stok + $i;
        $id_stok = sprintf("ST-%03d", $new_stok_id);
        $jumlah = rand(5, 100);
        $rak = "Rak " . chr(rand(65, 67)) . rand(1, 4);
        
        $pdo->prepare("INSERT INTO stok (id_stok, id_barang, jumlah_barang, lokasi_rak) 
                      VALUES (?, ?, ?, ?)")
            ->execute([$id_stok, $id, $jumlah, $rak]);
    }
    echo "<p>✅ {$inserted_barang} barang + stok berhasil dibuat</p></div></div>";
    
    // --- GENERATE 50 PEMBELI ---
    echo "<div class='card'><div class='card-header'><h2>👤 Step 2: Generate 50 Pembeli</h2></div><div class='card-body'>";
    
    $inserted_pembeli = 0;
    for ($i = 1; $i <= 50; $i++) {
        $new_id = $max_pembeli + $i;
        $id = sprintf("PM-%03d", $new_id);
        $nama = "Pembeli Test " . $i;
        $kota = ['Pontianak', 'Singkawang', 'Kubu Raya', 'Mempawah', 'Sambas', 'Ketapang', 'Sintang'][rand(0, 6)];
        $alamat = "Jl. " . chr(rand(65, 90)) . " No. " . rand(1, 999) . ", " . $kota;
        $telp = "08" . str_pad(rand(100000000, 999999999), 9, '0', STR_PAD_LEFT);
        
        $pdo->prepare("INSERT INTO pembeli (id_pembeli, nama_pembeli, alamat, nomor_telepon) 
                      VALUES (?, ?, ?, ?)")
            ->execute([$id, $nama, $alamat, $telp]);
        $inserted_pembeli++;
    }
    echo "<p>✅ {$inserted_pembeli} pembeli berhasil dibuat</p></div></div>";
    
    // --- GENERATE 20 KARYAWAN ---
    echo "<div class='card'><div class='card-header'><h2>👨‍💼 Step 3: Generate 20 Karyawan</h2></div><div class='card-body'>";
    
    $jabatan_list = ['Kasir', 'Gudang', 'Sales', 'Manajer', 'Supervisor', 'Admin'];
    $inserted_karyawan = 0;
    for ($i = 1; $i <= 20; $i++) {
        $new_id = $max_karyawan + $i;
        $id = sprintf("KR-%03d", $new_id);
        $nama = "Karyawan Test " . $i;
        $alamat = "Jl. Staff No. " . rand(1, 200);
        $telp = "08" . str_pad(rand(100000000, 999999999), 9, '0', STR_PAD_LEFT);
        $jabatan = $jabatan_list[array_rand($jabatan_list)];
        
        $pdo->prepare("INSERT INTO karyawan (id_karyawan, nama_karyawan, alamat, nomor_telepon, jabatan) 
                      VALUES (?, ?, ?, ?, ?)")
            ->execute([$id, $nama, $alamat, $telp, $jabatan]);
        $inserted_karyawan++;
    }
    echo "<p>✅ {$inserted_karyawan} karyawan berhasil dibuat</p></div></div>";
    
    // --- GENERATE 100 TRANSAKSI ---
    echo "<div class='card'><div class='card-header'><h2>🛒 Step 4: Generate 100 Transaksi</h2></div><div class='card-body'>";
    
    $jenis_list = ['Offline', 'Online', 'Telepon'];
    $status_list = ['Pending', 'Proses', 'Selesai', 'Batal'];
    $inserted_transaksi = 0;
    $inserted_detail = 0;
    
    // Hitung total pembeli dan karyawan yang tersedia sekarang
    $total_pembeli = $pdo->query("SELECT COUNT(*) FROM pembeli")->fetchColumn();
    $total_karyawan = $pdo->query("SELECT COUNT(*) FROM karyawan")->fetchColumn();
    $total_barang = $pdo->query("SELECT COUNT(*) FROM barang")->fetchColumn();
    
    for ($i = 1; $i <= 100; $i++) {
        $new_id = $max_transaksi + $i;
        $id = sprintf("TR-%03d", $new_id);
        $tanggal = date('Y-m-d', strtotime('-' . rand(0, 365) . ' days'));
        $jenis = $jenis_list[array_rand($jenis_list)];
        $id_pembeli = sprintf("PM-%03d", rand(1, $total_pembeli));
        $id_karyawan = sprintf("KR-%03d", rand(1, $total_karyawan));
        $status = $status_list[array_rand($status_list)];
        
        $pdo->prepare("INSERT INTO transaksi (id_transaksi, tanggal_pesan, jenis_pemesanan, id_pembeli, id_karyawan, status) 
                      VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$id, $tanggal, $jenis, $id_pembeli, $id_karyawan, $status]);
        $inserted_transaksi++;
        
        // Generate 1-5 detail transaksi per transaksi
        $jumlah_item = rand(1, 5);
        $total = 0;
        
        for ($j = 1; $j <= $jumlah_item; $j++) {
            $new_detail_id = $max_detail + ($i * 5) + $j;
            $id_detail = sprintf("DT-%03d", $new_detail_id);
            $id_barang = sprintf("PDK-%03d", rand(1, $total_barang));
            $qty = rand(1, 10);
            
            // Ambil harga barang
            $harga_stmt = $pdo->prepare("SELECT harga FROM barang WHERE id_barang = ?");
            $harga_stmt->execute([$id_barang]);
            $harga = $harga_stmt->fetchColumn();
            
            if (!$harga) continue;
            
            $subtotal = $harga * $qty;
            $total += $subtotal;
            
            $pdo->prepare("INSERT INTO detail_transaksi (id_detail, id_transaksi, id_barang, kuantitas, harga_satuan, subtotal) 
                          VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$id_detail, $id, $id_barang, $qty, $harga, $subtotal]);
            $inserted_detail++;
        }
        
        // Update total transaksi
        $pdo->prepare("UPDATE transaksi SET total_harga = ? WHERE id_transaksi = ?")
            ->execute([$total, $id]);
    }
    echo "<p>✅ {$inserted_transaksi} transaksi berhasil dibuat</p>";
    echo "<p>✅ {$inserted_detail} detail transaksi berhasil dibuat</p></div></div>";
    
    $pdo->commit();
    
    $end_time = microtime(true);
    $duration = round($end_time - $start_time, 2);
    
    // --- RINGKASAN ---
    echo "<div class='card' style='background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); color: white;'>
            <div class='card-header'><h2>📈 Ringkasan</h2></div>
            <div class='card-body'>
                <p><strong>Total waktu:</strong> {$duration} detik</p>
                <p><strong>Total data baru:</strong></p>
                <ul>
                    <li>{$inserted_barang} barang + stok</li>
                    <li>{$inserted_pembeli} pembeli</li>
                    <li>{$inserted_karyawan} karyawan</li>
                    <li>{$inserted_transaksi} transaksi</li>
                    <li>{$inserted_detail} detail transaksi</li>
                </ul>
                <p><strong>Grand Total: " . ($inserted_barang + $inserted_pembeli + $inserted_karyawan + $inserted_transaksi + $inserted_detail) . " records</strong></p>
                <br>
                <a href='index.php' class='btn btn-secondary'>🏠 Kembali ke Beranda</a>
                <a href='transaksi.php' class='btn btn-secondary'>🛒 Lihat Transaksi</a>
            </div>
          </div>";
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "<div class='alert alert-error'>❌ Error: " . $e->getMessage() . "</div>";
}

echo "</div></body></html>";
?>