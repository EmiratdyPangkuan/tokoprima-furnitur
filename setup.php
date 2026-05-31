<?php
// ============================================================================
// FILE: setup.php
// DESKRIPSI: Script setup untuk generate password hash yang kompatibel
//            Jalankan ini sekali untuk memastikan hash password sesuai
//            dengan PHP server Anda.
// ============================================================================

require_once 'config/koneksi.php';

echo "<div style='padding:20px; font-family:Arial;'>
<h2>🔧 Setup Toko Prima Furnitur</h2>";

// Generate hash untuk password "password"
$password = 'password';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h3>Password Hash Generator</h3>";
echo "<p>Password: <strong>password</strong></p>";
echo "<p>Generated Hash: <code style='background:#f5f5f0;padding:4px 8px;border-radius:4px;'>" . htmlspecialchars($hash) . "</code></p>";

// Verifikasi
$verify = password_verify($password, $hash);
echo "<p>Verification Test: <strong style='color:" . ($verify ? 'green' : 'red') . ";'>" . ($verify ? '✅ BERHASIL' : '❌ GAGAL') . "</strong></p>";

// Update database
if ($verify) {
    try {
        $stmt = $pdo->prepare("UPDATE user SET password = :hash WHERE username IN ('admin', 'kasir1', 'kasir2')");
        $stmt->execute([':hash' => $hash]);
        $updated = $stmt->rowCount();
        echo "<p>✅ Database updated! {$updated} user(s) password hash refreshed.</p>";
        echo "<p><strong>Silakan login dengan:</strong></p>";
        echo "<ul>";
        echo "<li>Admin: <code>admin</code> / <code>password</code></li>";
        echo "<li>Kasir: <code>kasir1</code> / <code>password</code></li>";
        echo "<li>Kasir: <code>kasir2</code> / <code>password</code></li>";
        echo "</ul>";
        echo "<p><a href='public/login.php' style='display:inline-block;margin-top:12px;padding:10px 20px;background:#f97316;color:#fff;text-decoration:none;border-radius:8px;'>🚀 Ke Halaman Login</a></p>";
    } catch (PDOException $e) {
        echo "<p style='color:red;'>❌ Database error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color:red;'>❌ password_verify() tidak berfungsi di server ini!</p>";
}

echo "</div>";
