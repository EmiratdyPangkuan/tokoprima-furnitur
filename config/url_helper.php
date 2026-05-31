<?php
// ============================================================================
// FILE: config/url_helper.php
// DESKRIPSI: Helper functions untuk generate URL dinamis
//            Berguna untuk menangani path yang berbeda-beda saat deployment
// ============================================================================

/**
 * base_url()
 * ---------------------------------------------------------------------------
 * Generate base URL project secara dinamis berdasarkan environment server
 * 
 * Cara kerja:
 * 1. Deteksi protocol (http/https)
 * 2. Ambil hostname dari $_SERVER['HTTP_HOST']
 * 3. Deteksi root folder project dari path script
 * 
 * @param string $path  Path tambahan setelah base URL
 * @return string       URL lengkap
 */
function base_url($path = '') {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];

    // Deteksi root project dari lokasi script
    $script_path = $_SERVER['SCRIPT_NAME'];
    $public_pos = strpos($script_path, '/public/');

    if ($public_pos !== false) {
        $project_root = substr($script_path, 0, $public_pos);
    } else {
        $project_root = dirname($script_path);
    }

    $project_root = rtrim($project_root, '/');

    return $protocol . '://' . $host . $project_root . '/' . ltrim($path, '/');
}

/**
 * pub_url()
 * Helper untuk URL di folder public
 */
function pub_url($path = '') {
    return base_url('public/' . ltrim($path, '/'));
}

/**
 * proc_url()
 * Helper untuk URL di folder proses
 */
function proc_url($path = '') {
    return base_url('proses/' . ltrim($path, '/'));
}

/**
 * asset_url()
 * Helper untuk URL asset (CSS, JS, images)
 */
function asset_url($path = '') {
    return base_url('public/assets/' . ltrim($path, '/'));
}

/**
 * redirect_to()
 * Redirect helper dengan URL absolut
 */
function redirect_to($path, $status = null, $message = null) {
    $url = pub_url($path);

    if ($status !== null) {
        $sep = strpos($url, '?') !== false ? '&' : '?';
        $url .= $sep . 'status=' . urlencode($status);
        if ($message !== null) {
            $url .= '&message=' . urlencode($message);
        }
    }

    header("Location: " . $url);
    exit;
}
