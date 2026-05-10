<?php
/**
 * GET /api/admin/realtime.php
 * Endpoint real-time untuk dashboard admin (auto-refresh)
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

// Hasil per paslon untuk chart
$hasilChart = [];
foreach ($stats['hasil_per_paslon'] as $h) {
    $hasilChart[] = [
        'id' => (int)$h['id'],
        'nomor_urut' => (int)$h['nomor_urut'],
        'nama_ketua' => $h['nama_ketua'],
        'nama_wakil' => $h['nama_wakil'],
        'suara' => (int)$h['total_suara'],
        'persentase' => round($stats['sudah_memilih'] > 0 ? (($h['total_suara'] / $stats['sudah_memilih']) * 100) : 0, 1)
    ];
}

// Voting per jam (hari ini) - data lengkap 24 jam
$votingPerJam = array_fill(7, 18, 0); // Jam 7-17 (jam sekolah)
$stmtPerJam = $db->query("
    SELECT
        HOUR(waktu_memilih) AS jam,
        COUNT(*) AS jumlah
    FROM users
    WHERE DATE(waktu_memilih) = CURDATE()
      AND sudah_memilih = 1
    GROUP BY HOUR(waktu_memilih)
");
foreach ($stmtPerJam->fetchAll() as $row) {
    $votingPerJam[(int)$row['jam']] = (int)$row['jumlah'];
}

// Convert ke format chart
$votingPerJamChart = [];
for ($i = 7; $i <= 17; $i++) {
    $votingPerJamChart[] = [
        'jam' => $i,
        'jumlah' => $votingPerJam[$i]
    ];
}

// Distribusi per kelas
$perKelas = [];
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
foreach ($stmtKelas->fetchAll() as $row) {
    $perKelas[] = [
        'kelas' => $row['kelas'],
        'total' => (int)$row['total'],
        'sudah' => (int)$row['sudah'],
        'belum' => (int)$row['belum']
    ];
}

// Status voting
$pengaturanRaw = $db->query("SELECT kunci, nilai FROM pengaturan")->fetchAll();
$pengaturan = [];
foreach ($pengaturanRaw as $row) {
    $pengaturan[$row['kunci']] = $row['nilai'];
}

// Log voting terakhir (5 terakhir)
$logTerakhir = $db->query("
    SELECT vl.*, u.nama, p.nama_ketua
    FROM voting_log vl
    JOIN users u ON vl.user_id = u.id
    JOIN paslon p ON vl.paslon_id = p.id
    ORDER BY vl.waktu_vote DESC
    LIMIT 5
")->fetchAll();

jsonResponse(true, 'Data realtime berhasil diambil', [
    'stats' => [
        'total_pemilih' => $stats['total_pemilih'],
        'sudah_memilih' => $stats['sudah_memilih'],
        'belum_memilih' => $stats['belum_memilih'],
        'partisipasi' => $stats['partisipasi']
    ],
    'hasil_per_paslon' => $hasilChart,
    'voting_per_jam' => $votingPerJamChart,
    'per_kelas' => $perKelas,
    'voting_aktif' => $pengaturan['voting_aktif'] ?? '0',
    'log_terakhir' => $logTerakhir,
    'timestamp' => date('Y-m-d H:i:s')
]);
