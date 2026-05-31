<?php
// ============================================================================
// FILE: config/koneksi.php
// DESKRIPSI: Koneksi database dengan privilege terbatas per role
// ============================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- KONFIGURASI DATABASE PER ROLE ---
$db_configs = [
    'admin' => [
        'DB_HOST' => 'localhost',
        'DB_NAME' => 'tokoprima',
        'DB_USER' => 'toko_admin',
        'DB_PASS' => 'AdminToko2026!',
        'DB_CHARSET' => 'utf8mb4'
    ],
    'kasir' => [
        'DB_HOST' => 'localhost',
        'DB_NAME' => 'tokoprima',
        'DB_USER' => 'toko_kasir',
        'DB_PASS' => 'KasirToko2026!',
        'DB_CHARSET' => 'utf8mb4'
    ]
];

// --- PILIH KONFIGURASI BERDASARKAN ROLE YANG LOGIN ---
function getDbConfig() {
    global $db_configs;
    
    // Default: admin config untuk setup.php
    if (!isset($_SESSION['user_role'])) {
        return $db_configs['admin'];
    }
    
    $role = $_SESSION['user_role'];
    
    // Validasi role yang diizinkan
    if (!in_array($role, ['admin', 'kasir'])) {
        die("Role tidak valid untuk koneksi database.");
    }
    
    return $db_configs[$role];
}

// --- KONEKSI DATABASE ---
$config = getDbConfig();

try {
    $dsn = "mysql:host=" . $config['DB_HOST'] . ";dbname=" . $config['DB_NAME'] . ";charset=" . $config['DB_CHARSET'];
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => true
    ];
    $pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASS'], $options);
    
} catch (PDOException $e) {
    error_log("Koneksi Database Gagal: " . $e->getMessage());
    die("<div style='padding:20px; background:#fee; color:#c33; border-radius:8px; font-family:Arial;'>
        <h3>⚠️ Koneksi Database Gagal</h3>
        <p>" . htmlspecialchars($e->getMessage()) . "</p>
        <p><small>User: " . htmlspecialchars($config['DB_USER']) . "</small></p>
    </div>");
}

// --- AUTHENTICATION HELPERS ---
function requireLogin() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['user_role'] !== 'admin') {
        header("Location: index.php?status=error&message=" . urlencode("Akses ditolak! Halaman ini hanya untuk Admin."));
        exit;
    }
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function isKasir() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'kasir';
}

function getUserName() {
    return $_SESSION['user_name'] ?? 'Guest';
}

function getUserRole() {
    return $_SESSION['user_role'] ?? '-';
}

// --- FUNGSI HELPER GLOBAL ---
function generateId($prefix, $pdo, $table, $column) {
    $sql = "SELECT MAX(CAST(SUBSTRING($column, LENGTH('$prefix') + 2) AS UNSIGNED)) as max_id FROM $table";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch();
    $nextId = ($result['max_id'] ?? 0) + 1;
    return sprintf("%s-%03d", $prefix, $nextId);
}

function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function redirect($url, $status = null, $message = null) {
    $params = [];
    if ($status) $params[] = "status=" . urlencode($status);
    if ($message) $params[] = "message=" . urlencode($message);
    if (!empty($params)) {
        $url .= (strpos($url, '?') !== false ? '&' : '?') . implode('&', $params);
    }
    header("Location: " . $url);
    exit;
}

function formatRupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}

function formatTanggal($tanggal) {
    $bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
              'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $date = new DateTime($tanggal);
    return $date->format('d') . ' ' . $bulan[(int)$date->format('m') - 1] . ' ' . $date->format('Y');
}

require_once __DIR__ . '/url_helper.php';