<?php
/**
 * Paslon API
 * GET    /api/admin/paslon.php          - List semua paslon
 * GET    /api/admin/paslon.php?id=1     - Detail paslon
 * POST   /api/admin/paslon.php          - Tambah paslon (multipart/form-data)
 * PUT    /api/admin/paslon.php?id=1     - Update paslon (multipart/form-data)
 * DELETE /api/admin/paslon.php?id=1     - Hapus paslon
 */

require_once __DIR__ . '/../../includes/helpers.php';
setCorsHeaders();
requireAdmin();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// ── GET ────────────────────────────────────────────────
if ($method === 'GET') {
    if ($id) {
        $stmt = $db->prepare("
            SELECT p.*, 
                   COUNT(u.id) AS jumlah_suara,
                   ROUND(COUNT(u.id) * 100.0 / NULLIF((SELECT COUNT(*) FROM users WHERE sudah_memilih=1),0), 2) AS persentase
            FROM paslon p
            LEFT JOIN users u ON u.paslon_dipilih = p.id AND u.sudah_memilih = 1
            WHERE p.id = ?
            GROUP BY p.id
        ");
        $stmt->execute([$id]);
        $paslon = $stmt->fetch();

        if (!$paslon) {
            jsonResponse(false, 'Paslon tidak ditemukan', [], 404);
        }
        $paslon['foto_url'] = UPLOAD_URL . $paslon['foto'];
        jsonResponse(true, 'Data paslon', ['paslon' => $paslon]);
    }

    // List semua
    $stmt   = $db->query("SELECT * FROM v_hasil_voting");
    $paslon = $stmt->fetchAll();
    foreach ($paslon as &$p) {
        $p['foto_url'] = UPLOAD_URL . $p['foto'];
    }
    jsonResponse(true, 'Daftar paslon', ['paslon' => $paslon, 'total' => count($paslon)]);
}

// ── POST ───────────────────────────────────────────────
if ($method === 'POST') {
    $nomorUrut  = (int)($_POST['nomor_urut']  ?? 0);
    $namaKetua  = sanitize($_POST['nama_ketua']  ?? '');
    $namaWakil  = sanitize($_POST['nama_wakil']  ?? '');
    $visi       = sanitize($_POST['visi']        ?? '');
    $misi       = sanitize($_POST['misi']        ?? '');

    if (!$nomorUrut || !$namaKetua || !$namaWakil || !$visi || !$misi) {
        jsonResponse(false, 'Semua field wajib diisi', [], 400);
    }

    // Cek nomor urut duplikat
    $cek = $db->prepare("SELECT id FROM paslon WHERE nomor_urut = ?");
    $cek->execute([$nomorUrut]);
    if ($cek->fetch()) {
        jsonResponse(false, "Nomor urut $nomorUrut sudah digunakan", [], 409);
    }

    // Upload foto
    $fotoFilename = 'default.jpg';
    if (!empty($_FILES['foto']['name'])) {
        $uploaded = uploadFoto($_FILES['foto'], 'paslon');
        if (!$uploaded) {
            jsonResponse(false, 'Upload foto gagal. Format: JPG/PNG/WEBP, maks 15MB', [], 400);
        }
        $fotoFilename = $uploaded;
    }

    $stmt = $db->prepare("
        INSERT INTO paslon (nomor_urut, nama_ketua, nama_wakil, visi, misi, foto)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$nomorUrut, $namaKetua, $namaWakil, $visi, $misi, $fotoFilename]);
    $newId = $db->lastInsertId();

    jsonResponse(true, 'Paslon berhasil ditambahkan', [
        'id'       => (int)$newId,
        'foto_url' => UPLOAD_URL . $fotoFilename,
    ], 201);
}

// ── PUT ────────────────────────────────────────────────
if ($method === 'PUT') {
    if (!$id) {
        jsonResponse(false, 'ID paslon diperlukan', [], 400);
    }

    // Cek paslon ada
    $existing = $db->prepare("SELECT * FROM paslon WHERE id = ?");
    $existing->execute([$id]);
    $paslon = $existing->fetch();
    if (!$paslon) {
        jsonResponse(false, 'Paslon tidak ditemukan', [], 404);
    }

    // PUT dengan multipart/form-data: ambil dari $_POST
    $nomorUrut = isset($_POST['nomor_urut'])  ? (int)$_POST['nomor_urut']          : $paslon['nomor_urut'];
    $namaKetua = isset($_POST['nama_ketua'])  ? sanitize($_POST['nama_ketua'])       : $paslon['nama_ketua'];
    $namaWakil = isset($_POST['nama_wakil'])  ? sanitize($_POST['nama_wakil'])       : $paslon['nama_wakil'];
    $visi      = isset($_POST['visi'])        ? sanitize($_POST['visi'])             : $paslon['visi'];
    $misi      = isset($_POST['misi'])        ? sanitize($_POST['misi'])             : $paslon['misi'];

    // Cek nomor urut duplikat (kecuali diri sendiri)
    $cek = $db->prepare("SELECT id FROM paslon WHERE nomor_urut = ? AND id != ?");
    $cek->execute([$nomorUrut, $id]);
    if ($cek->fetch()) {
        jsonResponse(false, "Nomor urut $nomorUrut sudah digunakan paslon lain", [], 409);
    }

    // Update foto jika ada upload baru
    $fotoFilename = $paslon['foto'];
    if (!empty($_FILES['foto']['name'])) {
        $uploaded = uploadFoto($_FILES['foto'], 'paslon');
        if (!$uploaded) {
            jsonResponse(false, 'Upload foto gagal. Format: JPG/PNG/WEBP, maks 15MB', [], 400);
        }
        deleteFoto($paslon['foto']); // hapus foto lama
        $fotoFilename = $uploaded;
    }

    $stmt = $db->prepare("
        UPDATE paslon 
        SET nomor_urut=?, nama_ketua=?, nama_wakil=?, visi=?, misi=?, foto=?
        WHERE id=?
    ");
    $stmt->execute([$nomorUrut, $namaKetua, $namaWakil, $visi, $misi, $fotoFilename, $id]);

    jsonResponse(true, 'Paslon berhasil diupdate', [
        'id'       => $id,
        'foto_url' => UPLOAD_URL . $fotoFilename,
    ]);
}

// ── DELETE ─────────────────────────────────────────────
if ($method === 'DELETE') {
    if (!$id) {
        jsonResponse(false, 'ID paslon diperlukan', [], 400);
    }

    $existing = $db->prepare("SELECT * FROM paslon WHERE id = ?");
    $existing->execute([$id]);
    $paslon = $existing->fetch();
    if (!$paslon) {
        jsonResponse(false, 'Paslon tidak ditemukan', [], 404);
    }

    // Cek apakah sudah ada yang memilih
    $cekVote = $db->prepare("SELECT COUNT(*) FROM users WHERE paslon_dipilih = ?");
    $cekVote->execute([$id]);
    if ((int)$cekVote->fetchColumn() > 0) {
        jsonResponse(false, 'Paslon tidak dapat dihapus karena sudah ada yang memilih', [], 409);
    }

    // Hapus foto & data
    deleteFoto($paslon['foto']);
    $stmt = $db->prepare("DELETE FROM paslon WHERE id = ?");
    $stmt->execute([$id]);

    jsonResponse(true, 'Paslon berhasil dihapus');
}

jsonResponse(false, 'Method tidak diizinkan', [], 405);
