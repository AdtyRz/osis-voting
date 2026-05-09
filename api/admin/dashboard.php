<?php
/**
 * GET /api/admin/dashboard.php
 * Mengembalikan data statistik voting untuk grafik beranda
 */

require_once __DIR__ . '/../../includes/helpers.php';
setCorsHeaders();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Method tidak diizinkan', [], 405);
}

$db = getDB();

// Statistik utama
$stats = getStatistikVoting();

// Distribusi pemilih per kelas
$stmtKelas = $db->query("
    SELECT 
        kelas,
        COUNT(*) AS total,
        SUM(sudah_memilih) AS sudah,
        COUNT(*) - SUM(sudah_memilih) AS belum
    FROM users
    GROUP BY kelas
    ORDER BY kelas
");
$stats['per_kelas'] = $stmtKelas->fetchAll();

// Voting per jam (hari ini)
$stmtPerJam = $db->query("
    SELECT 
        HOUR(waktu_memilih) AS jam,
        COUNT(*) AS jumlah
    FROM users
    WHERE DATE(waktu_memilih) = CURDATE()
      AND sudah_memilih = 1
    GROUP BY HOUR(waktu_memilih)
    ORDER BY jam
");
$stats['voting_per_jam'] = $stmtPerJam->fetchAll();

// Pengaturan sistem
$stmtPengaturan = $db->query("SELECT kunci, nilai FROM pengaturan");
$pengaturanRaw  = $stmtPengaturan->fetchAll();
$pengaturan     = [];
foreach ($pengaturanRaw as $row) {
    $pengaturan[$row['kunci']] = $row['nilai'];
}
$stats['pengaturan'] = $pengaturan;

// Info admin yang login
$stats['admin'] = [
    'nama'     => $_SESSION['admin_nama'],
    'username' => $_SESSION['admin_user'],
];

jsonResponse(true, 'Data dashboard berhasil diambil', $stats);
