<?php
// =============================================
// KONFIGURASI DATABASE
// Sesuaikan dengan setting server kamu
// =============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // Ganti dengan username MySQL kamu
define('DB_PASS', '');             // Ganti dengan password MySQL kamu
define('DB_NAME', 'db_osis_voting');
define('DB_CHARSET', 'utf8mb4');

// Base URL aplikasi
define('BASE_URL', 'http://localhost/osis-voting');
define('UPLOAD_DIR', __DIR__ . '/../uploads/foto/');
define('UPLOAD_URL', BASE_URL . '/uploads/foto/');

// Session lifetime (detik)
define('SESSION_LIFETIME', 3600); // 1 jam

// =============================================
// KONEKSI DATABASE (PDO)
// =============================================

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode([
                'success' => false,
                'message' => 'Koneksi database gagal: ' . $e->getMessage()
            ]));
        }
    }
    return $pdo;
}
