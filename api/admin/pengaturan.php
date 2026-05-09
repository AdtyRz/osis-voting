<?php
/**
 * Pengaturan API
 * GET  /api/admin/pengaturan.php - Ambil semua pengaturan
 * POST /api/admin/pengaturan.php - Update pengaturan
 */

require_once __DIR__ . '/../../includes/helpers.php';
setCorsHeaders();
requireAdmin();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $db->query("SELECT kunci, nilai FROM pengaturan");
    $rows = $stmt->fetchAll();
    $pengaturan = [];
    foreach ($rows as $row) {
        $pengaturan[$row['kunci']] = $row['nilai'];
    }
    jsonResponse(true, 'Pengaturan berhasil diambil', ['pengaturan' => $pengaturan]);
}

if ($method === 'POST') {
    $body = getInput();

    $allowed = ['voting_aktif', 'nama_sekolah', 'tahun_ajaran', 'tanggal_mulai', 'tanggal_selesai'];

    $db->beginTransaction();
    try {
        foreach ($allowed as $kunci) {
            if (isset($body[$kunci])) {
                $nilai = sanitize((string)$body[$kunci]);
                $stmt  = $db->prepare("
                    INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)
                ");
                $stmt->execute([$kunci, $nilai]);
            }
        }
        $db->commit();
        jsonResponse(true, 'Pengaturan berhasil disimpan');
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(false, 'Gagal menyimpan pengaturan: ' . $e->getMessage(), [], 500);
    }
}

jsonResponse(false, 'Method tidak diizinkan', [], 405);
