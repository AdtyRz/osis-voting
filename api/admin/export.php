<?php
/**
 * GET /api/admin/export.php?type=users     - Export data user ke CSV
 * GET /api/admin/export.php?type=hasil     - Export hasil voting ke CSV
 */

require_once __DIR__ . '/../../includes/helpers.php';
setCorsHeaders();
requireAdmin();

$db   = getDB();
$type = $_GET['type'] ?? 'users';

if ($type === 'users') {
    $stmt = $db->query("
        SELECT u.nama, u.nisn, u.kelas,
               IF(u.sudah_memilih=1,'Sudah','Belum') AS status_memilih,
               IFNULL(CONCAT('Paslon ', p.nomor_urut, ' - ', p.nama_ketua, ' & ', p.nama_wakil), '-') AS pilihan,
               IFNULL(DATE_FORMAT(u.waktu_memilih,'%d/%m/%Y %H:%i'), '-') AS waktu_memilih
        FROM users u
        LEFT JOIN paslon p ON p.id = u.paslon_dipilih
        ORDER BY u.kelas, u.nama
    ");
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="data_pemilih_' . date('Ymd_His') . '.csv"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    // BOM untuk Excel agar bisa baca UTF-8
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Nama', 'NISN', 'Kelas', 'Status Memilih', 'Pilihan', 'Waktu Memilih']);
    foreach ($rows as $row) {
        fputcsv($out, array_values($row));
    }
    fclose($out);
    exit;
}

if ($type === 'hasil') {
    $stats = getStatistikVoting();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="hasil_voting_' . date('Ymd_His') . '.csv"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['No. Urut', 'Nama Paslon', 'Jumlah Suara', 'Persentase (%)']);
    foreach ($stats['hasil_per_paslon'] as $p) {
        fputcsv($out, [
            $p['nomor_urut'],
            $p['nama_paslon'],
            $p['jumlah_suara'],
            $p['persentase'] . '%',
        ]);
    }
    fputcsv($out, []);
    fputcsv($out, ['Total Pemilih', $stats['total_pemilih']]);
    fputcsv($out, ['Sudah Memilih', $stats['sudah_memilih']]);
    fputcsv($out, ['Belum Memilih', $stats['belum_memilih']]);
    fputcsv($out, ['Partisipasi', $stats['partisipasi'] . '%']);
    fclose($out);
    exit;
}

jsonResponse(false, 'Type export tidak valid. Gunakan: users atau hasil', [], 400);
