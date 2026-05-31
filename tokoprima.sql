-- ============================================
-- DATABASE: tokoprima
-- Sistem Informasi Penjualan Terintegrasi
-- Toko Prima Furnitur
-- 
-- CATATAN: Jika login gagal, jalankan setup.php 
--          untuk refresh password hash.
-- ============================================

-- DATABASE: tokoprima
-- Sistem Informasi Penjualan Terintegrasi
-- Toko Prima Furnitur
-- ============================================

CREATE DATABASE IF NOT EXISTS tokoprima 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

USE tokoprima;

-- ============================================
-- TABEL: user (BARU - Sistem Login)
-- ID Format: US-001, US-002, dst
-- Role: admin (hanya 1), kasir (bisa banyak)
-- Password: bcrypt hash untuk 'password'
-- ============================================
CREATE TABLE IF NOT EXISTS user (
    id_user VARCHAR(10) PRIMARY KEY,
    nama_user VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','kasir') NOT NULL DEFAULT 'kasir',
    nomor_telepon VARCHAR(15) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABEL: barang
-- ID Format: PDK-001, PDK-002, dst
-- ============================================
CREATE TABLE IF NOT EXISTS barang (
    id_barang VARCHAR(10) PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    harga DECIMAL(12,2) NOT NULL,
    bahan VARCHAR(50) NOT NULL,
    warna VARCHAR(30) NOT NULL,
    ukuran VARCHAR(30) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABEL: pembeli
-- ID Format: PM-001, PM-002, dst
-- ============================================
CREATE TABLE IF NOT EXISTS pembeli (
    id_pembeli VARCHAR(10) PRIMARY KEY,
    nama_pembeli VARCHAR(100) NOT NULL,
    alamat TEXT NOT NULL,
    nomor_telepon VARCHAR(15) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABEL: karyawan
-- ID Format: KR-001, KR-002, dst
-- ============================================
CREATE TABLE IF NOT EXISTS karyawan (
    id_karyawan VARCHAR(10) PRIMARY KEY,
    nama_karyawan VARCHAR(100) NOT NULL,
    alamat TEXT NOT NULL,
    nomor_telepon VARCHAR(15) NOT NULL,
    jabatan VARCHAR(30) NOT NULL DEFAULT 'Karyawan',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABEL: stok
-- ID Format: ST-001, ST-002, dst
-- ============================================
CREATE TABLE IF NOT EXISTS stok (
    id_stok VARCHAR(10) PRIMARY KEY,
    id_barang VARCHAR(10) NOT NULL,
    jumlah_barang INT NOT NULL DEFAULT 0,
    lokasi_rak VARCHAR(20) DEFAULT 'Rak A1',
    last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_barang) REFERENCES barang(id_barang) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABEL: transaksi
-- ID Format: TR-001, TR-002, dst
-- ============================================
CREATE TABLE IF NOT EXISTS transaksi (
    id_transaksi VARCHAR(10) PRIMARY KEY,
    tanggal_pesan DATE NOT NULL,
    jenis_pemesanan ENUM('Offline', 'Online', 'Telepon') NOT NULL DEFAULT 'Offline',
    id_pembeli VARCHAR(10) NOT NULL,
    id_karyawan VARCHAR(10) NOT NULL,
    total_harga DECIMAL(12,2) DEFAULT 0,
    status ENUM('Pending', 'Proses', 'Selesai', 'Batal') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pembeli) REFERENCES pembeli(id_pembeli) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (id_karyawan) REFERENCES karyawan(id_karyawan) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABEL: detail_transaksi
-- ID Format: DT-001, DT-002, dst
-- ============================================
CREATE TABLE IF NOT EXISTS detail_transaksi (
    id_detail VARCHAR(10) PRIMARY KEY,
    id_transaksi VARCHAR(10) NOT NULL,
    id_barang VARCHAR(10) NOT NULL,
    kuantitas INT NOT NULL,
    harga_satuan DECIMAL(12,2) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (id_transaksi) REFERENCES transaksi(id_transaksi) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_barang) REFERENCES barang(id_barang) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TRIGGER: Auto update total_harga transaksi
-- ============================================
DELIMITER //

CREATE TRIGGER IF NOT EXISTS trg_update_total AFTER INSERT ON detail_transaksi
FOR EACH ROW
BEGIN
    UPDATE transaksi 
    SET total_harga = (
        SELECT COALESCE(SUM(subtotal), 0) 
        FROM detail_transaksi 
        WHERE id_transaksi = NEW.id_transaksi
    )
    WHERE id_transaksi = NEW.id_transaksi;
END//

CREATE TRIGGER IF NOT EXISTS trg_update_total_del AFTER DELETE ON detail_transaksi
FOR EACH ROW
BEGIN
    UPDATE transaksi 
    SET total_harga = (
        SELECT COALESCE(SUM(subtotal), 0) 
        FROM detail_transaksi 
        WHERE id_transaksi = OLD.id_transaksi
    )
    WHERE id_transaksi = OLD.id_transaksi;
END//

DELIMITER ;

-- ============================================
-- DATA SAMPLE
-- ============================================

-- Data User (Admin 1, Kasir 2)
-- Password untuk semua akun: 'password' (bcrypt hash)
INSERT INTO user (id_user, nama_user, username, password, role, nomor_telepon) VALUES
('US-001', 'Administrator', 'admin', '$2b$10$TOHrgBsErhotGjr665a93.soBVaibXMbuYSriz4COhWWu6ovDR/ae', 'admin', '081111111111'),
('US-002', 'Kasir 1', 'kasir1', '$2b$10$TOHrgBsErhotGjr665a93.soBVaibXMbuYSriz4COhWWu6ovDR/ae', 'kasir', '082222222222'),
('US-003', 'Kasir 2', 'kasir2', '$2b$10$TOHrgBsErhotGjr665a93.soBVaibXMbuYSriz4COhWWu6ovDR/ae', 'kasir', '083333333333');

-- Data Barang
INSERT INTO barang (id_barang, nama, harga, bahan, warna, ukuran) VALUES
('PDK-001', 'Sofa Minimalis 3 Duduk', 3500000.00, 'Oscar Fabric', 'Abu-abu', '200x80x90 cm'),
('PDK-002', 'Meja Makan Kayu Jati', 2800000.00, 'Kayu Jati Solid', 'Coklat Tua', '160x90x75 cm'),
('PDK-003', 'Lemari Pakaian 4 Pintu', 4200000.00, 'Multiplek HPL', 'Putih', '180x60x200 cm'),
('PDK-004', 'Kursi Kantor Ergonomis', 1850000.00, 'Mesh + Plastik', 'Hitam', '65x65x110 cm'),
('PDK-005', 'Rak Buku Minimalis', 950000.00, 'Kayu Pinus', 'Natural', '80x30x180 cm'),
('PDK-006', 'Meja Kerja Modern', 2100000.00, 'MDF + Besi', 'Putih-Hitam', '120x60x75 cm'),
('PDK-007', 'Kasur Spring Bed', 5200000.00, 'Per + Foam', 'Putih', '160x200x25 cm'),
('PDK-008', 'Nakas Samping Tempat Tidur', 650000.00, 'Kayu Mahoni', 'Coklat Muda', '45x40x55 cm');

-- Data Pembeli
INSERT INTO pembeli (id_pembeli, nama_pembeli, alamat, nomor_telepon) VALUES
('PM-001', 'Budi Santoso', 'Jl. Merdeka No. 12, Jakarta', '081234567890'),
('PM-002', 'Siti Aminah', 'Jl. Sudirman No. 45, Bandung', '082345678901'),
('PM-003', 'Ahmad Rizky', 'Jl. Ahmad Yani No. 78, Surabaya', '083456789012'),
('PM-004', 'Dewi Lestari', 'Jl. Gatot Subroto No. 23, Yogyakarta', '084567890123');

-- Data Karyawan
INSERT INTO karyawan (id_karyawan, nama_karyawan, alamat, nomor_telepon, jabatan) VALUES
('KR-001', 'Joko Widodo', 'Jl. Pahlawan No. 1, Jakarta', '08111222333', 'Manajer'),
('KR-002', 'Rina Marlina', 'Jl. Mawar No. 5, Bandung', '08222333444', 'Kasir'),
('KR-003', 'Doni Pratama', 'Jl. Melati No. 10, Surabaya', '08333444555', 'Gudang'),
('KR-004', 'Maya Sari', 'Jl. Anggrek No. 15, Yogyakarta', '08444555666', 'Sales');

-- Data Stok
INSERT INTO stok (id_stok, id_barang, jumlah_barang, lokasi_rak) VALUES
('ST-001', 'PDK-001', 15, 'Rak A1'),
('ST-002', 'PDK-002', 8, 'Rak A2'),
('ST-003', 'PDK-003', 12, 'Rak B1'),
('ST-004', 'PDK-004', 25, 'Rak A3'),
('ST-005', 'PDK-005', 30, 'Rak C1'),
('ST-006', 'PDK-006', 18, 'Rak A4'),
('ST-007', 'PDK-007', 6, 'Rak B2'),
('ST-008', 'PDK-008', 22, 'Rak C2');

-- Data Transaksi
INSERT INTO transaksi (id_transaksi, tanggal_pesan, jenis_pemesanan, id_pembeli, id_karyawan, status) VALUES
('TR-001', '2026-05-20', 'Offline', 'PM-001', 'KR-002', 'Selesai'),
('TR-002', '2026-05-21', 'Online', 'PM-002', 'KR-004', 'Proses'),
('TR-003', '2026-05-22', 'Offline', 'PM-003', 'KR-002', 'Selesai'),
('TR-004', '2026-05-23', 'Telepon', 'PM-004', 'KR-004', 'Pending');

-- Data Detail Transaksi
INSERT INTO detail_transaksi (id_detail, id_transaksi, id_barang, kuantitas, harga_satuan, subtotal) VALUES
('DT-001', 'TR-001', 'PDK-001', 1, 3500000.00, 3500000.00),
('DT-002', 'TR-001', 'PDK-005', 2, 950000.00, 1900000.00),
('DT-003', 'TR-002', 'PDK-002', 1, 2800000.00, 2800000.00),
('DT-004', 'TR-003', 'PDK-003', 1, 4200000.00, 4200000.00),
('DT-005', 'TR-003', 'PDK-008', 2, 650000.00, 1300000.00),
('DT-006', 'TR-004', 'PDK-004', 3, 1850000.00, 5550000.00);

-- Update total transaksi setelah insert detail
UPDATE transaksi SET total_harga = 5400000.00 WHERE id_transaksi = 'TR-001';
UPDATE transaksi SET total_harga = 2800000.00 WHERE id_transaksi = 'TR-002';
UPDATE transaksi SET total_harga = 5500000.00 WHERE id_transaksi = 'TR-003';
UPDATE transaksi SET total_harga = 5550000.00 WHERE id_transaksi = 'TR-004';
