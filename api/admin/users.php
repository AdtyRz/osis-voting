<?php
/**
 * Users API (Data Pemilih)
 * GET    /api/admin/users.php           - List semua user (dengan filter/search)
 * GET    /api/admin/users.php?id=1      - Detail user
 * POST   /api/admin/users.php           - Tambah user
 * PUT    /api/admin/users.php?id=1      - Update user
 * DELETE /api/admin/users.php?id=1      - Hapus user
 * POST   /api/admin/users.php?action=reset_vote&id=1 - Reset suara user
 * POST   /api/admin/users.php?action=import           - Import CSV massal
 */

require_once __DIR__ . '/../../includes/helpers.php';
setCorsHeaders();
requireAdmin();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id'])     ? (int)$_GET['id']     : null;
$action = isset($_GET['action']) ? $_GET['action']       : null;

// ── GET ────────────────────────────────────────────────
if ($method === 'GET') {
    if ($id) {
        $stmt = $db->prepare("
            SELECT u.*, p.nomor_urut AS paslon_nomor, 
                   CONCAT(p.nama_ketua, ' & ', p.nama_wakil) AS paslon_nama
            FROM users u
            LEFT JOIN paslon p ON p.id = u.paslon_dipilih
            WHERE u.id = ?
        ");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) {
            jsonResponse(false, 'User tidak ditemukan', [], 404);
        }
        unset($user['password']);
        jsonResponse(true, 'Data user', ['user' => $user]);
    }

    // List dengan filter opsional
    $search = sanitize($_GET['search'] ?? '');
    $kelas  = sanitize($_GET['kelas']  ?? '');
    $status = $_GET['status'] ?? ''; // 'sudah' | 'belum'
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = min(100, max(10, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $where  = [];
    $params = [];

    if ($search) {
        $where[]  = "(u.nama LIKE ? OR u.nisn LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($kelas) {
        $where[]  = "u.kelas = ?";
        $params[] = $kelas;
    }
    if ($status === 'sudah') {
        $where[] = "u.sudah_memilih = 1";
    } elseif ($status === 'belum') {
        $where[] = "u.sudah_memilih = 0";
    }

    $whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

    // Total
    $countStmt = $db->prepare("SELECT COUNT(*) FROM users u $whereSQL");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // Data
    $stmt = $db->prepare("
        SELECT u.id, u.nama, u.nisn, u.kelas, u.sudah_memilih, u.waktu_memilih, u.created_at,
               p.nomor_urut AS paslon_nomor,
               CONCAT(p.nama_ketua, ' & ', p.nama_wakil) AS paslon_nama
        FROM users u
        LEFT JOIN paslon p ON p.id = u.paslon_dipilih
        $whereSQL
        ORDER BY u.kelas, u.nama
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    // Daftar kelas untuk filter dropdown
    $kelasStmt = $db->query("SELECT DISTINCT kelas FROM users ORDER BY kelas");
    $daftarKelas = $kelasStmt->fetchAll(PDO::FETCH_COLUMN);

    jsonResponse(true, 'Daftar user', [
        'users'        => $users,
        'total'        => $total,
        'page'         => $page,
        'limit'        => $limit,
        'total_page'   => ceil($total / $limit),
        'daftar_kelas' => $daftarKelas,
    ]);
}

// ── POST ───────────────────────────────────────────────
if ($method === 'POST') {
    // Reset suara user tertentu
    if ($action === 'reset_vote' && $id) {
        $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            jsonResponse(false, 'User tidak ditemukan', [], 404);
        }
        $upd = $db->prepare("
            UPDATE users SET sudah_memilih=0, paslon_dipilih=NULL, waktu_memilih=NULL WHERE id=?
        ");
        $upd->execute([$id]);
        // Hapus log
        $delLog = $db->prepare("DELETE FROM voting_log WHERE user_id=?");
        $delLog->execute([$id]);
        jsonResponse(true, 'Suara user berhasil direset');
    }

    // Import CSV massal
    if ($action === 'import') {
        if (empty($_FILES['file']['name'])) {
            jsonResponse(false, 'File CSV diperlukan', [], 400);
        }
        $file = $_FILES['file']['tmp_name'];
        $handle = fopen($file, 'r');
        if (!$handle) {
            jsonResponse(false, 'File tidak dapat dibaca', [], 400);
        }

        $berhasil = 0;
        $gagal    = 0;
        $errors   = [];
        $baris    = 0;

        // Skip header
        fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            $baris++;
            if (count($row) < 4) {
                $errors[] = "Baris $baris: format tidak valid";
                $gagal++;
                continue;
            }
            [$nama, $nisn, $kelas, $password] = $row;
            $nama    = sanitize(trim($nama));
            $nisn    = sanitize(trim($nisn));
            $kelas   = sanitize(trim($kelas));
            $password = trim($password);

            if (!$nama || !$nisn || !$kelas || !$password) {
                $errors[] = "Baris $baris: ada field kosong";
                $gagal++;
                continue;
            }

            try {
                $ins = $db->prepare("
                    INSERT INTO users (nama, nisn, kelas, password)
                    VALUES (?, ?, ?, ?)
                ");
                $ins->execute([$nama, $nisn, $kelas, password_hash($password, PASSWORD_BCRYPT)]);
                $berhasil++;
            } catch (PDOException $e) {
                $errors[] = "Baris $baris (NISN $nisn): " . ($e->getCode() === '23000' ? 'NISN sudah terdaftar' : $e->getMessage());
                $gagal++;
            }
        }
        fclose($handle);

        jsonResponse(true, "Import selesai: $berhasil berhasil, $gagal gagal", [
            'berhasil' => $berhasil,
            'gagal'    => $gagal,
            'errors'   => $errors,
        ]);
    }

    // Tambah user baru
    $body     = getInput();
    $nama     = sanitize($body['nama']     ?? '');
    $nisn     = sanitize($body['nisn']     ?? '');
    $kelas    = sanitize($body['kelas']    ?? '');
    $password = $body['password'] ?? '';

    if (!$nama || !$nisn || !$kelas || !$password) {
        jsonResponse(false, 'Semua field wajib diisi (nama, nisn, kelas, password)', [], 400);
    }
    if (strlen($password) < 6) {
        jsonResponse(false, 'Password minimal 6 karakter', [], 400);
    }
    if (!preg_match('/^\d{8,10}$/', $nisn)) {
        jsonResponse(false, 'NISN harus berupa 8-10 digit angka', [], 400);
    }

    // Cek NISN duplikat
    $cek = $db->prepare("SELECT id FROM users WHERE nisn = ?");
    $cek->execute([$nisn]);
    if ($cek->fetch()) {
        jsonResponse(false, 'NISN sudah terdaftar', [], 409);
    }

    $stmt = $db->prepare("
        INSERT INTO users (nama, nisn, kelas, password) VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$nama, $nisn, $kelas, password_hash($password, PASSWORD_BCRYPT)]);
    $newId = $db->lastInsertId();

    jsonResponse(true, 'User berhasil ditambahkan', ['id' => (int)$newId], 201);
}

// ── PUT ────────────────────────────────────────────────
if ($method === 'PUT') {
    if (!$id) {
        jsonResponse(false, 'ID user diperlukan', [], 400);
    }

    $existing = $db->prepare("SELECT * FROM users WHERE id = ?");
    $existing->execute([$id]);
    $user = $existing->fetch();
    if (!$user) {
        jsonResponse(false, 'User tidak ditemukan', [], 404);
    }

    $body     = getInput();
    $nama     = isset($body['nama'])     ? sanitize($body['nama'])     : $user['nama'];
    $nisn     = isset($body['nisn'])     ? sanitize($body['nisn'])     : $user['nisn'];
    $kelas    = isset($body['kelas'])    ? sanitize($body['kelas'])    : $user['kelas'];
    $password = $body['password'] ?? '';

    if ($nisn !== $user['nisn']) {
        if (!preg_match('/^\d{8,10}$/', $nisn)) {
            jsonResponse(false, 'NISN harus berupa 8-10 digit angka', [], 400);
        }
        $cek = $db->prepare("SELECT id FROM users WHERE nisn = ? AND id != ?");
        $cek->execute([$nisn, $id]);
        if ($cek->fetch()) {
            jsonResponse(false, 'NISN sudah digunakan user lain', [], 409);
        }
    }

    $hashedPass = $user['password'];
    if ($password) {
        if (strlen($password) < 6) {
            jsonResponse(false, 'Password minimal 6 karakter', [], 400);
        }
        $hashedPass = password_hash($password, PASSWORD_BCRYPT);
    }

    $stmt = $db->prepare("UPDATE users SET nama=?, nisn=?, kelas=?, password=? WHERE id=?");
    $stmt->execute([$nama, $nisn, $kelas, $hashedPass, $id]);

    jsonResponse(true, 'User berhasil diupdate', ['id' => $id]);
}

// ── DELETE ─────────────────────────────────────────────
if ($method === 'DELETE') {
    if (!$id) {
        jsonResponse(false, 'ID user diperlukan', [], 400);
    }

    $existing = $db->prepare("SELECT id FROM users WHERE id = ?");
    $existing->execute([$id]);
    if (!$existing->fetch()) {
        jsonResponse(false, 'User tidak ditemukan', [], 404);
    }

    // Hapus log voting dulu
    $db->prepare("DELETE FROM voting_log WHERE user_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);

    jsonResponse(true, 'User berhasil dihapus');
}

jsonResponse(false, 'Method tidak diizinkan', [], 405);
