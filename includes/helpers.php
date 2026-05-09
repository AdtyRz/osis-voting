<?php
require_once __DIR__ . '/config.php';

// =============================================
// SESSION HELPER
// =============================================

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => false, // set true jika pakai HTTPS
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function isAdminLoggedIn(): bool {
    startSession();
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function requireAdmin(): void {
    if (!isAdminLoggedIn()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Akses ditolak. Silakan login terlebih dahulu.']);
        exit;
    }
}

// =============================================
// RESPONSE HELPER
// =============================================

function jsonResponse(bool $success, string $message, array $data = [], int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data,
    ]);
    exit;
}

function setCorsHeaders(): void {
    // Ambil origin dari request, fallback ke localhost
    $allowedOrigins = [
        'http://localhost',
        'http://localhost:80',
        'http://localhost:3000',
        'http://127.0.0.1',
        'http://127.0.0.1:80',
    ];

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    // Jika origin ada di whitelist, izinkan — kalau tidak, pakai origin pertama sebagai default
    if (in_array($origin, $allowedOrigins)) {
        header("Access-Control-Allow-Origin: $origin");
    } else {
        // Untuk localhost development: izinkan semua, tapi tetap echo origin-nya
        // agar browser mau kirim credentials
        header("Access-Control-Allow-Origin: " . ($origin ?: 'http://localhost'));
    }

    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// =============================================
// UPLOAD FOTO HELPER
// =============================================

function uploadFoto(array $file, string $prefix = 'paslon'): string|false {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    $maxSize = 10 * 1024 * 1024; // 5 MB

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    if (!in_array($file['type'], $allowedTypes)) {
        return false;
    }
    if ($file['size'] > $maxSize) {
        return false;
    }

    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $prefix . '_' . time() . '_' . uniqid() . '.' . strtolower($ext);
    $dest     = UPLOAD_DIR . $filename;

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    if (move_uploaded_file($file['tmp_name'], $dest)) {
        return $filename;
    }
    return false;
}

function deleteFoto(string $filename): void {
    if ($filename && $filename !== 'default.jpg') {
        $path = UPLOAD_DIR . $filename;
        if (file_exists($path)) {
            unlink($path);
        }
    }
}

// =============================================
// SANITASI INPUT
// =============================================

function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function getInput(): array {
    $json = file_get_contents('php://input');
    return json_decode($json, true) ?? [];
}

// =============================================
// STATISTIK VOTING
// =============================================

function getStatistikVoting(): array {
    $db = getDB();

    $totalPemilih   = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $sudahMemilih   = $db->query("SELECT COUNT(*) FROM users WHERE sudah_memilih = 1")->fetchColumn();
    $belumMemilih   = $totalPemilih - $sudahMemilih;
    $partisipasi    = $totalPemilih > 0 ? round(($sudahMemilih / $totalPemilih) * 100, 2) : 0;

    $hasilPerPaslon = $db->query("SELECT * FROM v_hasil_voting")->fetchAll();

    return [
        'total_pemilih'    => (int)$totalPemilih,
        'sudah_memilih'    => (int)$sudahMemilih,
        'belum_memilih'    => (int)$belumMemilih,
        'partisipasi'      => $partisipasi,
        'hasil_per_paslon' => $hasilPerPaslon,
    ];
}