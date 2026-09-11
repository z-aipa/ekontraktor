<?php
session_start();

// 1. Sekat daripada menyimpan cache halaman ini
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'perbendaharaan') { header("Location: index.php"); exit(); }
include 'db.php';

$result = $conn->query("SELECT * FROM kontraktor_profil WHERE status_borang = 'Lengkap' AND status_bayaran_daftar = 'Belum Bayar' LIMIT 1");
$row = $result->fetch_assoc();

if (isset($_POST['sah_pendaftaran'])) {
    $profil_id = $_POST['profil_id'];

    // FORMULA STRUKTUR MDBG: Sah sehingga 31 Disember dua (2) tahun berikutnya
    $tahun_sekarang = date('Y');
    $tahun_tamat = $tahun_sekarang + 1;
    $tarikh_tamat_aktif = $tahun_tamat . "-12-31"; 

    $sql = "UPDATE kontraktor_profil SET status_bayaran_daftar = 'Sudah Bayar', tarikh_mula_aktif = CURDATE(), tarikh_tamat_aktif = '$tarikh_tamat_aktif' WHERE id = '$profil_id'";
    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('Terimaan Kod Transaksi 21326 Direkodkan secara Offline. Sijil aktif sehingga $tarikh_tamat_aktif.'); window.location='perbendaharaan.php';</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Penyemak Perbendaharaan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5" style="max-width: 600px;">
        <div class="card shadow border-0 p-4">
            <h4 class="fw-bold text-success border-bottom pb-2 mb-4">Kaunter / Sistem Kemaskini Offline Perbendaharaan</h4>
            <?php if ($row): ?>
                <div class="alert alert-warning py-3">
                    <h6>Syarikat Menerima Bil Terimaan Pelbagai: <strong><?= $row['nama_syarikat']; ?></strong></h6>
                    <p class="small mb-0 text-dark">Sila sahkan sekiranya kontraktor telah menjelaskan yuran <strong>RM52.00 (Kod: 21326)</strong> di kaunter fizikal atau melalui platform JomPAY MDBG.</p>
                </div>
                <form method="POST">
                    <input type="hidden" name="profil_id" value="<?= $row['id']; ?>">
                    <button type="submit" name="sah_pendaftaran" class="btn btn-success w-100 fw-bold shadow-sm">Sahkan Bayaran & Terbitkan Resit Rasmi</button>
                </form>
            <?php else: ?>
                <div class="alert alert-info">Tiada bil yuran pendaftaran 21326 yang menunggu giliran penjelasan bayaran.</div>
                <a href="perbendaharaan.php" class="btn btn-secondary btn-sm">Kembali</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>