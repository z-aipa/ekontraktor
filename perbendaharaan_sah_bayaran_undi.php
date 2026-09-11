<?php
session_start();

// 1. Sekat daripada menyimpan cache halaman ini
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'perbendaharaan') { header("Location: index.php"); exit(); }
include 'db.php';

$result = $conn->query("SELECT d.*, k.nama_syarikat FROM daftar_undi d JOIN kontraktor_profil k ON d.kontraktor_id = k.id WHERE d.status_layak = 'Layak' AND d.status_resit_undi = 'Belum Kemaskini' LIMIT 1");
$row = $result->fetch_assoc();

if (isset($_POST['sah_undi'])) {
    $undi_id = $_POST['undi_id'];

    $sql = "UPDATE daftar_undi SET status_resit_undi = 'Selesai' WHERE id = '$undi_id'";
    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('Terimaan bayaran disahkan offline! Resit rasmi cabutan undi sebutharga dikeluarkan kepada kontraktor.'); window.location='dashboard.php';</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8"><title>Pengesahan Bayaran Undi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5" style="max-width: 600px;">
        <div class="card shadow border-0 p-4">
            <h4 class="fw-bold text-success mb-3">Kemaskini Offline / Keluar Resit Undi (Perbendaharaan)</h4>
            <?php if ($row): ?>
                <div class="p-3 bg-light rounded border border-success mb-4">
                    <h5>Syarikat: <strong><?= $row['nama_syarikat']; ?></strong></h5>
                    <p class="small mb-0 text-muted">Kontraktor dikesan sudah bersedia membuat pembayaran bil kos penyertaan undi sebutharga bagi <strong>Kod Transaksi: 21355</strong>.</p>
                </div>
                <form method="POST">
                    <input type="hidden" name="undi_id" value="<?= $row['id']; ?>">
                    <button type="submit" name="sah_undi" class="btn btn-success w-100 fw-bold py-2 shadow-sm">Sahkan Terima Bayaran & Cetak Resit Rasmi</button>
                </form>
            <?php else: ?>
                <div class="alert alert-info">Tiada transaksi kutipan penyertaan undian sebutharga 21355 yang belum dijelaskan.</div>
                <a href="perbendaharaan.php" class="btn btn-secondary btn-sm">Kembali</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>