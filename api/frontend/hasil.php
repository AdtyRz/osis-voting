<?php
/**
 * GET /api/frontend/hasil.php
 * Hasil voting (hanya tampil jika admin mengizinkan)
 */

require_once __DIR__ . '/../../includes/helpers.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Method tidak diizinkan', [], 405);
}

$db = getDB();

// Cek apakah hasil boleh dilihat publik (bisa tambahkan setting ini di pengaturan)
$cek = $db->prepare("SELECT nilai FROM pengaturan WHERE kunci = 'tampil_hasil'");
$cek->execute();
$tampil = $cek->fetchColumn();

// Default: hanya tampilkan jumlah suara tanpa persentase jika voting masih aktif
$stats = getStatistikVoting();

jsonResponse(true, 'Hasil sementara', [
    'total_suara'      => $stats['sudah_memilih'],
    'hasil_per_paslon' => array_map(function($p) {
        return [
            'nomor_urut'   => $p['nomor_urut'],
            'nama_paslon'  => $p['nama_paslon'],
            'jumlah_suara' => (int)$p['jumlah_suara'],
        ];
    }, $stats['hasil_per_paslon']),
]);
