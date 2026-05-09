<?php
/**
 * GET /api/frontend/paslon.php
 * Daftar paslon (publik, tidak perlu login)
 */

require_once __DIR__ . '/../../includes/helpers.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Method tidak diizinkan', [], 405);
}

$db   = getDB();
$stmt = $db->query("
    SELECT id, nomor_urut, nama_ketua, nama_wakil, visi, misi, foto
    FROM paslon
    ORDER BY nomor_urut
");
$paslon = $stmt->fetchAll();

foreach ($paslon as &$p) {
    $p['foto_url'] = UPLOAD_URL . $p['foto'];
}

jsonResponse(true, 'Daftar paslon', ['paslon' => $paslon]);
