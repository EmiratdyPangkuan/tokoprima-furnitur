<?php
// ============================================================================
// FILE: public/logout.php
// DESKRIPSI: Handler logout - hapus session dan redirect ke login
// ============================================================================

require_once '../config/koneksi.php';

// Hapus semua data session
$_SESSION = [];

// Hapus cookie session jika ada
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Hancurkan session
session_destroy();

// Redirect ke login dengan pesan
header("Location: login.php?status=success&message=" . urlencode("Berhasil logout. Sampai jumpa!"));
exit;
