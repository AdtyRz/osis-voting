<?php
/**
 * POST /api/admin/login.php
 * Body: { "username": "...", "password": "..." }
 */

require_once __DIR__ . '/../../includes/helpers.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method tidak diizinkan', [], 405);
}

$body = getInput();
$username = sanitize($body['username'] ?? '');
$password = $body['password'] ?? '';

if (empty($username) || empty($password)) {
    jsonResponse(false, 'Username dan password wajib diisi', [], 400);
}

$db   = getDB();
$stmt = $db->prepare("SELECT * FROM admin WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($password, $admin['password'])) {
    jsonResponse(false, 'Username atau password salah', [], 401);
}

startSession();
$_SESSION['admin_id']   = $admin['id'];
$_SESSION['admin_nama'] = $admin['nama'];
$_SESSION['admin_user'] = $admin['username'];
$_SESSION['login_time'] = time();

jsonResponse(true, 'Login berhasil', [
    'admin' => [
        'id'       => $admin['id'],
        'nama'     => $admin['nama'],
        'username' => $admin['username'],
    ]
]);
