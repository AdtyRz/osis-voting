<?php
/**
 * API untuk Frontend Siswa (Voting)
 *
 * POST /api/frontend/login.php        - Login siswa
 * POST /api/frontend/vote.php         - Kirim suara
 * GET  /api/frontend/paslon.php       - Daftar paslon (publik)
 * GET  /api/frontend/hasil.php        - Hasil sementara (jika diizinkan)
 */

require_once __DIR__ . '/../../includes/helpers.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method tidak diizinkan', [], 405);
}

$db   = getDB();
$body = getInput();

$nisn     = sanitize($body['nisn']     ?? '');
$password = $body['password'] ?? '';

if (!$nisn || !$password) {
    jsonResponse(false, 'NISN dan password wajib diisi', [], 400);
}

// Cek apakah voting aktif
$cekAktif = $db->prepare("SELECT nilai FROM pengaturan WHERE kunci = 'voting_aktif'");
$cekAktif->execute();
$votingAktif = $cekAktif->fetchColumn();
if ($votingAktif !== '1') {
    jsonResponse(false, 'Voting sedang tidak aktif', [], 403);
}

// Cari user
$stmt = $db->prepare("SELECT * FROM users WHERE nisn = ? LIMIT 1");
$stmt->execute([$nisn]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    jsonResponse(false, 'NISN atau password salah', [], 401);
}

startSession();
$_SESSION['user_id']   = $user['id'];
$_SESSION['user_nama'] = $user['nama'];
$_SESSION['user_nisn'] = $user['nisn'];
$_SESSION['user_kelas'] = $user['kelas'];
$_SESSION['sudah_memilih'] = (bool)$user['sudah_memilih'];

jsonResponse(true, 'Login berhasil', [
    'user' => [
        'id'            => $user['id'],
        'nama'          => $user['nama'],
        'kelas'         => $user['kelas'],
        'sudah_memilih' => (bool)$user['sudah_memilih'],
    ]
]);
