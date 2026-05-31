<?php
// ============================================================================
// FILE: public/login.php
// DESKRIPSI: Halaman login untuk admin dan kasir
// ============================================================================

require_once '../config/koneksi.php';

// Jika sudah login, redirect ke index
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Validasi input
    if (empty($username)) {
        $errors[] = 'Username wajib diisi!';
    }
    if (empty($password)) {
        $errors[] = 'Password wajib diisi!';
    }

    if (empty($errors)) {
        try {
            // Cek apakah tabel user ada
            $cek_tabel = $pdo->query("SHOW TABLES LIKE 'user'")->fetch();
            if (!$cek_tabel) {
                $errors[] = 'Tabel user belum dibuat! Import tokoprima.sql terlebih dahulu, lalu jalankan setup.php.';
            } else {
                $stmt = $pdo->prepare("SELECT * FROM user WHERE username = :username LIMIT 1");
                $stmt->execute([':username' => $username]);
                $user = $stmt->fetch();

                if ($user) {
                    // Verifikasi password dengan bcrypt
                    $verified = password_verify($password, $user['password']);

                    // Fallback: jika hash masih plain text (untuk development)
                    if (!$verified && $user['password'] === $password) {
                        $verified = true;
                        // Auto-upgrade ke bcrypt
                        $new_hash = password_hash($password, PASSWORD_DEFAULT);
                        $pdo->prepare("UPDATE user SET password = :hash WHERE id_user = :id")
                            ->execute([':hash' => $new_hash, ':id' => $user['id_user']]);
                    }

                    if ($verified) {
                        // Set session
                        $_SESSION['user_id']       = $user['id_user'];
                        $_SESSION['user_name']     = $user['nama_user'];
                        $_SESSION['user_role']     = $user['role'];
                        $_SESSION['user_username'] = $user['username'];

                        // Regenerate session ID untuk keamanan
                        session_regenerate_id(true);

                        header("Location: index.php");
                        exit;
                    } else {
                        $errors[] = 'Password tidak cocok!';
                    }
                } else {
                    $errors[] = 'Username tidak ditemukan!';
                }
            }
        } catch (PDOException $e) {
            error_log("Login Error: " . $e->getMessage());
            $errors[] = 'Terjadi kesalahan database. Pastikan database sudah diimport.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Toko Prima Furnitur</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            padding: 20px;
        }
        .login-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 420px;
            padding: 40px 36px;
            animation: loginSlide 0.5s ease;
        }
        @keyframes loginSlide {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-brand {
            text-align: center;
            margin-bottom: 32px;
        }
        .login-brand .brand-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 16px;
            box-shadow: 0 4px 14px rgba(249, 115, 22, 0.35);
        }
        .login-brand h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: #1a1a1a;
        }
        .login-brand p {
            color: #888;
            font-size: 0.9rem;
            margin-top: 4px;
        }
        .login-form .form-group {
            margin-bottom: 20px;
        }
        .login-form .form-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #555;
            margin-bottom: 6px;
            display: block;
        }
        .login-form .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e8e8e3;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: inherit;
            box-sizing: border-box;
        }
        .login-form .form-input:focus {
            outline: none;
            border-color: #f97316;
            box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.1);
        }
        .login-form .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 14px rgba(249, 115, 22, 0.35);
            font-family: inherit;
        }
        .login-form .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(249, 115, 22, 0.45);
        }
        .login-info {
            margin-top: 24px;
            padding: 16px;
            background: #fff7ed;
            border-radius: 12px;
            border: 1px solid #fdba74;
        }
        .login-info h4 {
            font-size: 0.85rem;
            color: #c2410c;
            margin-bottom: 8px;
            font-weight: 700;
        }
        .login-info p {
            font-size: 0.8rem;
            color: #9a3412;
            margin-bottom: 4px;
        }
        .login-info code {
            background: #fff;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.75rem;
            border: 1px solid #fed7aa;
        }
    </style>
</head>
<body>
    <div class="login-page">
        <div class="login-card">
            <div class="login-brand">
                <div class="brand-icon">🪑</div>
                <h1>Toko Prima Furnitur</h1>
                <p>Sistem Informasi Penjualan Terintegrasi</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error" style="margin-bottom: 20px;">
                    <span>❌ <?php echo implode(', ', $errors); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="login-form" autocomplete="off">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-input" 
                           placeholder="Masukkan username" required autofocus
                           autocomplete="off" 
                           value="<?php echo isset($_POST['username']) ? sanitize($_POST['username']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" 
                           placeholder="Masukkan password" required
                           autocomplete="off">
                </div>
                <button type="submit" class="btn-login">🔐 Masuk</button>
            </form>

            <div class="login-info">
                <h4>📋 Akun Demo</h4>
                <p><strong>Admin:</strong> <code>admin</code> / <code>password</code></p>
                <p><strong>Kasir 1:</strong> <code>kasir1</code> / <code>password</code></p>
                <p><strong>Kasir 2:</strong> <code>kasir2</code> / <code>password</code></p>
            </div>
        </div>
    </div>
</body>
</html>
