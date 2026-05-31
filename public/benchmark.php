<?php
// ============================================================================
// FILE: public/benchmark.php
// DESKRIPSI: Halaman benchmarking performa query
// ============================================================================

require_once '../config/koneksi.php';
requireAdmin();

echo "<!DOCTYPE html>
<html lang='id'>
<head>
    <meta charset='UTF-8'>
    <title>Benchmark - Toko Prima Furnitur</title>
    <link rel='stylesheet' href='assets/css/style.css'>
    <style>
        .benchmark-table { font-family: monospace; font-size: 0.85rem; }
        .time-good { color: #22c55e; font-weight: 700; }
        .time-medium { color: #f59e0b; font-weight: 700; }
        .time-bad { color: #ef4444; font-weight: 700; }
    </style>
</head>
<body>
<div class='main-container'>
    <div class='page-header'>
        <h1>📊 Benchmark Performa Query</h1>
        <p>Pengukuran waktu eksekusi query utama</p>
    </div>";

// --- BENCHMARK 1: Query Transaksi ---
echo "<div class='card'>
        <div class='card-header'><h2>🛒 Query 1: Daftar Transaksi (JOIN 3 tabel)</h2></div>
        <div class='card-body'>";

$sql1 = "SELECT t.*, p.nama_pembeli, k.nama_karyawan 
         FROM transaksi t 
         LEFT JOIN pembeli p ON t.id_pembeli = p.id_pembeli 
         LEFT JOIN karyawan k ON t.id_karyawan = k.id_karyawan 
         ORDER BY t.tanggal_pesan DESC";

$start = microtime(true);
$stmt1 = $pdo->query($sql1);
$result1 = $stmt1->fetchAll();
$time1 = round((microtime(true) - $start) * 1000, 2);

$color1 = $time1 < 50 ? 'time-good' : ($time1 < 200 ? 'time-medium' : 'time-bad');
echo "<p>Waktu eksekusi: <span class='{$color1}'>{$time1} ms</span></p>";
echo "<p>Jumlah row: " . count($result1) . "</p>";
echo "<pre class='benchmark-table'>" . htmlspecialchars($sql1) . "</pre>";
echo "</div></div>";

// --- BENCHMARK 2: Query Barang + Stok ---
echo "<div class='card'>
        <div class='card-header'><h2>📦 Query 2: Barang dengan Stok (LEFT JOIN)</h2></div>
        <div class='card-body'>";

$sql2 = "SELECT b.*, COALESCE(s.jumlah_barang, 0) as stok, s.lokasi_rak 
         FROM barang b 
         LEFT JOIN stok s ON b.id_barang = s.id_barang 
         ORDER BY b.id_barang ASC";

$start = microtime(true);
$stmt2 = $pdo->query($sql2);
$result2 = $stmt2->fetchAll();
$time2 = round((microtime(true) - $start) * 1000, 2);

$color2 = $time2 < 50 ? 'time-good' : ($time2 < 200 ? 'time-medium' : 'time-bad');
echo "<p>Waktu eksekusi: <span class='{$color2}'>{$time2} ms</span></p>";
echo "<p>Jumlah row: " . count($result2) . "</p>";
echo "<pre class='benchmark-table'>" . htmlspecialchars($sql2) . "</pre>";
echo "</div></div>";

// --- BENCHMARK 3: Query Detail Transaksi ---
echo "<div class='card'>
        <div class='card-header'><h2>📄 Query 3: Detail Transaksi (JOIN + WHERE)</h2></div>
        <div class='card-body'>";

$sql3 = "SELECT dt.*, b.nama as nama_barang, b.bahan, b.warna, b.ukuran
         FROM detail_transaksi dt 
         LEFT JOIN barang b ON dt.id_barang = b.id_barang 
         WHERE dt.id_transaksi = 'TR-001'";

$start = microtime(true);
$stmt3 = $pdo->query($sql3);
$result3 = $stmt3->fetchAll();
$time3 = round((microtime(true) - $start) * 1000, 2);

$color3 = $time3 < 50 ? 'time-good' : ($time3 < 200 ? 'time-medium' : 'time-bad');
echo "<p>Waktu eksekusi: <span class='{$color3}'>{$time3} ms</span></p>";
echo "<p>Jumlah row: " . count($result3) . "</p>";
echo "<pre class='benchmark-table'>" . htmlspecialchars($sql3) . "</pre>";
echo "</div></div>";

// --- BENCHMARK 4: Query Laporan (Agregasi) ---
echo "<div class='card'>
        <div class='card-header'><h2>📈 Query 4: Laporan Top Barang (Agregasi + JOIN + GROUP)</h2></div>
        <div class='card-body'>";

$sql4 = "SELECT b.nama, SUM(dt.kuantitas) as total_terjual, SUM(dt.subtotal) as total_pendapatan
         FROM detail_transaksi dt
         JOIN barang b ON dt.id_barang = b.id_barang
         JOIN transaksi t ON dt.id_transaksi = t.id_transaksi
         WHERE t.tanggal_pesan BETWEEN '2025-01-01' AND '2026-12-31' AND t.status != 'Batal'
         GROUP BY dt.id_barang
         ORDER BY total_terjual DESC
         LIMIT 5";

$start = microtime(true);
$stmt4 = $pdo->query($sql4);
$result4 = $stmt4->fetchAll();
$time4 = round((microtime(true) - $start) * 1000, 2);

$color4 = $time4 < 100 ? 'time-good' : ($time4 < 500 ? 'time-medium' : 'time-bad');
echo "<p>Waktu eksekusi: <span class='{$color4}'>{$time4} ms</span></p>";
echo "<p>Jumlah row: " . count($result4) . "</p>";
echo "<pre class='benchmark-table'>" . htmlspecialchars($sql4) . "</pre>";
echo "</div></div>";

// --- RINGKASAN ---
$total_time = $time1 + $time2 + $time3 + $time4;
echo "<div class='card' style='background: #1a1a1a; color: #fff;'>
        <div class='card-header'><h2>📊 Ringkasan Benchmark</h2></div>
        <div class='card-body'>
            <p>Total waktu 4 query: <strong>{$total_time} ms</strong></p>
            <p>Rata-rata per query: <strong>" . round($total_time / 4, 2) . " ms</strong></p>
            <br>
            <a href='index.php' class='btn btn-secondary'>🏠 Kembali</a>
        </div>
      </div>";

echo "</div></body></html>";
?>