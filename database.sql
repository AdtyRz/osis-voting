-- =============================================
-- DATABASE: Sistem Pemilihan Ketua OSIS
-- =============================================

CREATE DATABASE IF NOT EXISTS db_osis_voting CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_osis_voting;

-- Tabel Pasangan Calon
CREATE TABLE IF NOT EXISTS paslon (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nomor_urut INT NOT NULL UNIQUE,
    nama_ketua VARCHAR(100) NOT NULL,
    nama_wakil VARCHAR(100) NOT NULL,
    visi TEXT NOT NULL,
    misi TEXT NOT NULL,
    foto VARCHAR(255) DEFAULT 'default.jpg',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabel User (Siswa Pemilih)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    nisn VARCHAR(20) NOT NULL UNIQUE,
    kelas VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    sudah_memilih TINYINT(1) DEFAULT 0,
    paslon_dipilih INT DEFAULT NULL,
    waktu_memilih TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (paslon_dipilih) REFERENCES paslon(id) ON DELETE SET NULL
);

-- Tabel Admin
CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Voting Log (untuk keamanan)
CREATE TABLE IF NOT EXISTS voting_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    paslon_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (paslon_id) REFERENCES paslon(id)
);

-- Tabel Pengaturan Voting
CREATE TABLE IF NOT EXISTS pengaturan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kunci VARCHAR(50) NOT NULL UNIQUE,
    nilai TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =============================================
-- DATA AWAL
-- =============================================

-- Admin default (password: admin123)
INSERT INTO admin (username, password, nama) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator');

-- Pengaturan default
INSERT INTO pengaturan (kunci, nilai) VALUES
('voting_aktif', '1'),
('nama_sekolah', 'SMA/SMK Contoh'),
('tahun_ajaran', '2024/2025'),
('tanggal_mulai', '2024-01-01'),
('tanggal_selesai', '2024-12-31');

-- Contoh Data Paslon
INSERT INTO paslon (nomor_urut, nama_ketua, nama_wakil, visi, misi, foto) VALUES
(1, 'Ahmad Fauzi', 'Siti Rahayu', 
 'Mewujudkan OSIS yang aktif, kreatif, dan berprestasi untuk kemajuan sekolah', 
 '1. Meningkatkan kegiatan ekstrakurikuler\n2. Membangun komunikasi yang baik antara siswa dan guru\n3. Mengadakan event-event kreatif dan inovatif\n4. Meningkatkan solidaritas antar siswa\n5. Mengembangkan potensi siswa secara optimal',
 'default.jpg'),
(2, 'Budi Santoso', 'Dewi Lestari',
 'OSIS Bersatu, Berkarya, dan Berprestasi demi Kebanggaan Sekolah',
 '1. Memperkuat persatuan dan kesatuan siswa\n2. Mengadakan program mentoring akademik\n3. Meningkatkan kegiatan sosial dan peduli lingkungan\n4. Mengembangkan bakat dan minat siswa\n5. Menjalin hubungan baik dengan alumni',
 'default.jpg');

-- Contoh Data User Siswa
INSERT INTO users (nama, nisn, kelas, password) VALUES
('Andi Pratama', '0012345678', 'XI IPA 1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Bela Safitri', '0012345679', 'XI IPS 2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Candra Wijaya', '0012345680', 'XII IPA 1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
-- Default password semua user: password

-- =============================================
-- VIEWS UNTUK LAPORAN
-- =============================================

CREATE OR REPLACE VIEW v_hasil_voting AS
SELECT 
    p.id,
    p.nomor_urut,
    CONCAT(p.nama_ketua, ' & ', p.nama_wakil) AS nama_paslon,
    p.foto,
    COUNT(u.id) AS jumlah_suara,
    ROUND(COUNT(u.id) * 100.0 / NULLIF((SELECT COUNT(*) FROM users WHERE sudah_memilih = 1), 0), 2) AS persentase
FROM paslon p
LEFT JOIN users u ON u.paslon_dipilih = p.id AND u.sudah_memilih = 1
GROUP BY p.id, p.nomor_urut, p.nama_ketua, p.nama_wakil, p.foto
ORDER BY p.nomor_urut;
