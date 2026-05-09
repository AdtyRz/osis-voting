<?php
/**
 * POST /api/frontend/vote.php
 * Body: { "paslon_id": 1 }
 * Requires: user session
 */

require_once __DIR__ . '/../../includes/helpers.php';
setCorsHeaders();

startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method tidak diizinkan', [], 405);
}

// Cek login siswa
if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'Silakan login terlebih dahulu', [], 401);
}

$db        = getDB();
$body      = getInput();
$paslonId  = (int)($body['paslon_id'] ?? 0);
$userId    = (int)$_SESSION['user_id'];

if (!$paslonId) {
    jsonResponse(false, 'Pilih paslon terlebih dahulu', [], 400);
}

// Cek apakah voting aktif
$cekAktif = $db->prepare("SELECT nilai FROM pengaturan WHERE kunci = 'voting_aktif'");
$cekAktif->execute();
if ($cekAktif->fetchColumn() !== '1') {
    jsonResponse(false, 'Voting sedang tidak aktif', [], 403);
}

// Cek user sudah memilih
$stmt = $db->prepare("SELECT sudah_memilih FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    jsonResponse(false, 'User tidak ditemukan', [], 404);
}
if ($user['sudah_memilih']) {
    jsonResponse(false, 'Kamu sudah pernah memilih sebelumnya', [], 409);
}

// Cek paslon valid
$cekPaslon = $db->prepare("SELECT id FROM paslon WHERE id = ?");
$cekPaslon->execute([$paslonId]);
if (!$cekPaslon->fetch()) {
    jsonResponse(false, 'Paslon tidak ditemukan', [], 404);
}

// Simpan suara dalam transaksi
$db->beginTransaction();
try {
    // Update user
    $upd = $db->prepare("
        UPDATE users 
        SET sudah_memilih=1, paslon_dipilih=?, waktu_memilih=NOW()
        WHERE id=? AND sudah_memilih=0
    ");
    $upd->execute([$paslonId, $userId]);

    if ($upd->rowCount() === 0) {
        $db->rollBack();
        jsonResponse(false, 'Gagal menyimpan suara (mungkin sudah memilih)', [], 409);
    }

    // Catat log
    $log = $db->prepare("
        INSERT INTO voting_log (user_id, paslon_id, ip_address, user_agent)
        VALUES (?, ?, ?, ?)
    ");
    $log->execute([
        $userId,
        $paslonId,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    ]);

    $db->commit();

    // Update session
    $_SESSION['sudah_memilih'] = true;

    jsonResponse(true, 'Suara berhasil dicatat! Terima kasih sudah berpartisipasi.');
} catch (Exception $e) {
    $db->rollBack();
    jsonResponse(false, 'Terjadi kesalahan: ' . $e->getMessage(), [], 500);
}
