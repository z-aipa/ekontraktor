<?php
session_start();

// 1. Sekat daripada menyimpan cache halaman ini
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 1. Sekatan Hak Akses - Hanya pengguna dengan peranan 'kejuruteraan' sahaja dibenarkan
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kejuruteraan') { 
    header("Location: index.php"); 
    exit(); 
}

include 'db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$user_id = $_SESSION['user_id'];
$profil = null;
$selected_id = '';
$target_dir = "uploads/";

// 2. Dapatkan senarai semua kontraktor yang berstatus 'Pending' untuk diletakkan ke dalam drop-down
$senarai_kontraktor = $conn->query("SELECT id, nama_syarikat FROM kontraktor_profil WHERE status_borang = 'Pending' ORDER BY nama_syarikat ASC");

// Ambil ID dari GET request jika admin memilih syarikat tertentu dari dropdown
if (isset($_GET['kontraktor_id']) && !empty($_GET['kontraktor_id'])) {
    $selected_id = $_GET['kontraktor_id'];
}

// 3. Tentukan data kontraktor terpilih secara selamat
if (!empty($selected_id)) {
    // Cari syarikat spesifik yang dipilih oleh admin menggunakan Prepared Statement
    $stmt = $conn->prepare("SELECT * FROM kontraktor_profil WHERE id = ? AND status_borang = 'Pending'");
    $stmt->bind_param("i", $selected_id);
    $stmt->execute();
    $profil = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    // Jika baru buka halaman, ambil rekod pertama yang dijumpai secara automatik (jika ada)
    $result_first = $conn->query("SELECT * FROM kontraktor_profil WHERE status_borang = 'Pending' LIMIT 1");
    if ($result_first && $result_first->num_rows > 0) {
        $profil = $result_first->fetch_assoc();
        $selected_id = $profil['id'];
    }
}

// 4. Proses kemaskini keputusan semakan borang & MUAT NAIK BIL SERTA-MERTA
if (isset($_POST['kemaskini_pendaftaran'])) {
    $profil_id = $_POST['profil_id'];
    $status = $_POST['status_borang'];
    $alasan = $_POST['alasan_tolak'];

    // --- PROSES MUAT NAIK BIL JIKA ADA FAIL DIPILIH ---
    $fail_bil_nama = null;
    if (!empty($_FILES['fail_bil']['name'])) {
        $filename = time() . "_bil_" . basename($_FILES['fail_bil']['name']);
        $target_file = $target_dir . $filename;
        if (move_uploaded_file($_FILES['fail_bil']['tmp_name'], $target_file)) {
            $fail_bil_nama = $filename;
        }
    }

    if ($status == 'Lengkap') {
        // Tarikh hari ini sebagai mula aktif
        $tarikh_mula = date('Y-m-d');
        $tarikh_tamat = date('Y-m-d', strtotime('+1 year')); 

        if ($fail_bil_nama) {
            $stmt_update = $conn->prepare("UPDATE kontraktor_profil SET status_borang = ?, alasan_tolak = ?, tarikh_mula_aktif = ?, tarikh_tamat_aktif = ?, fail_bil = ? WHERE id = ?");
            $stmt_update->bind_param("sssssi", $status, $alasan, $tarikh_mula, $tarikh_tamat, $fail_bil_nama, $profil_id);
        } else {
            $stmt_update = $conn->prepare("UPDATE kontraktor_profil SET status_borang = ?, alasan_tolak = ?, tarikh_mula_aktif = ?, tarikh_tamat_aktif = ? WHERE id = ?");
            $stmt_update->bind_param("ssssi", $status, $alasan, $tarikh_mula, $tarikh_tamat, $profil_id);
        }
    } else {
        // Jika status ditukar kepada selain 'Lengkap' (Tidak Lengkap/Gagal)
        $tarikh_null = null;
        if ($fail_bil_nama) {
            $stmt_update = $conn->prepare("UPDATE kontraktor_profil SET status_borang = ?, alasan_tolak = ?, tarikh_mula_aktif = ?, tarikh_tamat_aktif = ?, fail_bil = ? WHERE id = ?");
            $stmt_update->bind_param("sssssi", $status, $alasan, $tarikh_null, $tarikh_null, $fail_bil_nama, $profil_id);
        } else {
            $stmt_update = $conn->prepare("UPDATE kontraktor_profil SET status_borang = ?, alasan_tolak = ?, tarikh_mula_aktif = ?, tarikh_tamat_aktif = ? WHERE id = ?");
            $stmt_update->bind_param("ssssi", $status, $alasan, $tarikh_null, $tarikh_null, $profil_id);
        }
    }
    
    if ($stmt_update->execute()) {
        
        // --- 1. AMBIL ALAMAT E-MEL & NAMA SYARIKAT PENGGUNA ---
        $stmt_email = $conn->prepare("SELECT email_aktif, nama_syarikat FROM kontraktor_profil WHERE id = ?");
        $stmt_email->bind_param("i", $profil_id);
        $stmt_email->execute();
        $data_user = $stmt_email->get_result()->fetch_assoc();
        $stmt_email->close();

        $penerima = $data_user['email_aktif'] ?? '';
        $nama_syarikat = $data_user['nama_syarikat'] ?? 'Kontraktor';

        // --- 2 & 3. TETAPAN & HANTAR E-MEL MENGGUNAKAN PHPMAILER ---
        if (!empty($penerima)) {
            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com'; 
                $mail->SMTPAuth   = true;
                $mail->Username   = 'prk.mdbg@mdbg.gov.my';       
                $mail->Password   = 'bgcd cdwt bfup uxeq'; // App Password anda
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
                $mail->Port       = 465;

                // TAMBAHAN PENTING: Lepaskan sekatan sijil SSL untuk ujian di XAMPP / Localhost
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );

                // Penerima dan Penghantar
                $mail->setFrom('prk.mdbg@mdbg.gov.my', 'Majlis Daerah Batu Gajah');
                $mail->addAddress($penerima, $nama_syarikat);

                // Kandungan E-mel (Format HTML)
                $mail->isHTML(true); 
                
                if ($status == 'Lengkap') {
                    $mail->Subject = "MDBG - Keputusan Semakan Pendaftaran: LENGKAP";
                    $mail->Body    = "Salam Sejahtera <b>" . htmlspecialchars($nama_syarikat) . "</b>,<br><br>"
                                   . "Sukacita dimaklumkan bahawa semakan fail dan dokumen pendaftaran anda telah disahkan <b>LENGKAP</b>.<br>"
                                   . "Sila log masuk ke dalam sistem untuk menyemak bil dan melengkapkan proses seterusnya.<br><br>"
                                   . "Terima kasih,<br><b>Majlis Daerah Batu Gajah</b>";
                } else {
                    $mail->Subject = "MDBG - Keputusan Semakan Pendaftaran: TIDAK LENGKAP";
                    $mail->Body    = "Salam Sejahtera <b>" . htmlspecialchars($nama_syarikat) . "</b>,<br><br>"
                                   . "Dukacita dimaklumkan bahawa permohonan / semakan fail anda berstatus <b>TIDAK LENGKAP</b>.<br><br>"
                                   . "<b>Catatan / Alasan Penolakan:</b><br>"
                                   . nl2br(htmlspecialchars($alasan)) . "<br><br>"
                                   . "Sila log masuk semula ke dalam sistem untuk membuat pembetulan dokumen.<br><br>"
                                   . "Terima kasih,<br><b>Majlis Daerah Batu Gajah</b>";
                }

                // Hantar E-mel
                $mail->send();
                
            } catch (Exception $e) {
                // PAPARKAN RALAT SEBENAR PADA POP-UP JIKA E-MEL GAGAL
                echo "<script>alert('Perhatian: Data BERJAYA dikemaskini, TETAPI e-mel GAGAL dihantar.\\n\\nRalat PHPMailer: " . addslashes($mail->ErrorInfo) . "');</script>";
            }
        } else {
            echo "<script>alert('Perhatian: E-mel penerima tidak dijumpai di dalam pangkalan data.');</script>";
        }

        // --- 4. PAPARKAN MESEJ & REDIRECT ---
        $msg = ($status == 'Lengkap') 
             ? 'Borang disahkan LENGKAP, bil diproses & notifikasi e-mel telah dihantar!' 
             : 'Borang diisytiharkan TIDAK LENGKAP & notifikasi e-mel telah dihantar.';
             
        echo "<script>alert('$msg'); window.location='kejuruteraan.php';</script>";
        exit();
        
    } else {
        echo "<script>alert('Gagal mengemaskini maklumat ke dalam pangkalan data.');</script>";
    }

    $stmt_update->close();
}

?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semakan Dokumen Kontraktor Baharu dan Pembaharuan | MDBG</title>
    <!-- Google Fonts & FontAwesome & Bootstrap 5 -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --mdbg-primary: #8D5B4C;
            --mdbg-primary-hover: #72473a;
            --mdbg-bg: #f1f2f3;
            --mdbg-card-border: #e5e7eb;
            --mdbg-text: #1f2937;
            --mdbg-subtext: #6b7280;
        }

        body {
            background-color: var(--mdbg-bg);
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--mdbg-text);
            padding-bottom: 3rem;
        }

        .main-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid var(--mdbg-card-border);
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
            padding: 32px;
        }

        /* Header Style */
        .page-header-icon {
            width: 44px;
            height: 44px;
            background-color: #fcf4f0;
            color: var(--mdbg-primary);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        /* Dropdown Styling */
        .select-company-box {
            background: #fafafa;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px 20px;
        }

        /* Information Cards */
        .info-card {
            background: #ffffff;
            border: 1px solid var(--mdbg-card-border);
            border-radius: 12px;
            padding: 20px;
        }

        .info-label {
            font-size: 0.725rem;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--mdbg-subtext);
            letter-spacing: 0.04em;
            margin-bottom: 4px;
        }

        .info-value-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--mdbg-primary);
            margin: 0;
        }

        /* Document Table/List Clean Style */
        .doc-section {
            border: 1px solid var(--mdbg-card-border);
            border-radius: 12px;
            overflow: hidden;
        }

        .doc-header {
            background: #f9fafb;
            padding: 12px 20px;
            border-bottom: 1px solid var(--mdbg-card-border);
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--mdbg-subtext);
        }

        .doc-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid #f3f4f6;
            background: #ffffff;
            transition: background 0.15s ease;
        }

        .doc-row:last-child {
            border-bottom: none;
        }

        .doc-row:hover {
            background-color: #fafafa;
        }

        .doc-title {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--mdbg-text);
            width: 30%;
        }

        .doc-content {
            width: 70%;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 12px;
            flex-wrap: nowrap;
        }

        /* Buttons & Badges */
        .btn-pdf-view {
            background-color: #fef2f2;
            color: #dc2626;
            border: 1px solid #fee2e2;
            font-weight: 600;
            font-size: 0.8rem;
            padding: 6px 14px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .btn-pdf-view:hover {
            background-color: #dc2626;
            color: #ffffff;
            border-color: #dc2626;
        }

        .btn-sub-doc {
            background-color: #f3f4f6;
            color: #374151;
            border: 1px solid #e5e7eb;
            font-weight: 600;
            font-size: 0.775rem;
            padding: 5px 12px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
            white-space: nowrap;
        }

        .btn-sub-doc:hover {
            background-color: #e5e7eb;
            color: #111827;
        }

        /* Styling Moden & Profesional Untuk Tarikh */
        .pill-date {
            font-size: 0.78rem;
            font-weight: 500;
            padding: 4px 10px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #475569;
            white-space: nowrap;
        }

        .pill-date .date-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .pill-date-start .date-label {
            color: #0f766e;
        }

        .pill-date-end .date-label {
            color: #b91c1c;
        }

        .pill-date .date-value {
            font-weight: 600;
            color: #0f172a;
        }

        /* Action Panel */
        .action-panel {
            background-color: #fdfbfb;
            border: 1px solid #ebdcd7;
            border-radius: 12px;
            padding: 24px;
        }

        .btn-mdbg-submit {
            background-color: var(--mdbg-primary);
            color: #ffffff;
            font-weight: 600;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            transition: background 0.2s ease;
            width: 100%;
        }

        .btn-mdbg-submit:hover {
            background-color: var(--mdbg-primary-hover);
            color: #ffffff;
        }

        .btn-mdbg-back {
            background-color: #ffffff;
            color: #4b5563;
            border: 1px solid #d1d5db;
            font-weight: 600;
            padding: 12px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            transition: all 0.2s ease;
        }

        .btn-mdbg-back:hover {
            background-color: #f9fafb;
            color: #111827;
            border-color: #9ca3af;
        }

        @media (max-width: 768px) {
            .doc-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            .doc-title, .doc-content {
                width: 100%;
            }
            .doc-content {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>

    <div class="container my-4" style="max-width: 920px;">
        
        <div class="main-card">
            <!-- Header Section -->
            <div class="d-flex align-items-center gap-3 pb-4 mb-4 border-bottom">
                <div class="page-header-icon">
                    <i class="fa-solid fa-file-signature"></i>
                </div>
                <div>
                    <h5 class="fw-bold m-0 text-dark">Semakan Dokumen Kontraktor Baharu dan Pembaharuan</h5>
                    <p class="text-muted small m-0">Sila teliti dan sahkan kesahihan dokumen fizikal pembekal sebelum meluluskan fasa ini.</p>
                </div>
            </div>

            <!-- Dropdown Pilihan Syarikat -->
            <?php if ($senarai_kontraktor && $senarai_kontraktor->num_rows > 0): ?>
                <div class="select-company-box mb-4">
                    <label for="pilih_kontraktor" class="info-label d-block mb-2">Pilih Syarikat Untuk Disemak:</label>
                    <select id="pilih_kontraktor" class="form-select border-secondary-subtle" onchange="location = this.value;">
                        <?php while($row = $senarai_kontraktor->fetch_assoc()): ?>
                            <option value="?kontraktor_id=<?= htmlspecialchars($row['id']); ?>" <?= ($selected_id == $row['id']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($row['nama_syarikat']); ?>
                            </option>
                        <?php endwhile; ?>
                        <?php $senarai_kontraktor->data_seek(0); ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if ($profil): ?>
                <!-- Profil Ringkas Syarikat -->
                <div class="info-card mb-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <span class="info-label">Nama Syarikat / Perniagaan</span>
                            <h6 class="info-value-title text-uppercase mt-1"><?= htmlspecialchars($profil['nama_syarikat']); ?></h6>
                        </div>
                        <div class="col-md-4">
                            <span class="info-label">No. Pendaftaran (SSM)</span>
                            <div>
                                <span class="badge bg-light text-dark border fs-6 font-monospace px-3 py-1 mt-1">
                                    <?= htmlspecialchars($profil['no_pendaftaran']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-12 border-top pt-3">
                            <span class="info-label">Jenis & Kategori Pendaftaran</span>
                            <div class="fw-semibold text-dark small mt-1">
                                <div><?= htmlspecialchars($profil['jenis_pendaftaran']); ?></div>
                                <div class="text-muted fw-normal">&bull; <?= htmlspecialchars($profil['kategori_pendaftaran']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kad Gred Kelayakan CIDB / Kewangan (Gambar 2 & 3) -->
                <div class="info-card mb-4">
                    <h6 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2" style="font-size: 0.95rem;">
                        <i class="fa-solid fa-gear text-secondary"></i> GRED KELAYAKAN CIDB / KEWANGAN
                    </h6>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle text-center m-0" style="border-color: #dee2e6;">
                            <thead>
                                <tr style="background-color: #212529; color: #ffffff; font-size: 0.85rem; letter-spacing: 0.05em;">
                                    <th class="py-2 fw-bold" style="width: 30%;">GRED</th>
                                    <th class="py-2 fw-bold" style="width: 35%;">KATEGORI</th>
                                    <th class="py-2 fw-bold" style="width: 35%;">PENGKHUSUSAN</th>
                                </tr>
                            </thead>
                            <tbody style="font-size: 0.875rem;">
                                <?php
                                $raw_cidb = trim($profil['gred_cidb_kewangan'] ?? '');
                                $has_rows = false;
                                if (!empty($raw_cidb)) {
                                    $lines = explode("\n", str_replace("\r", "", $raw_cidb));
                                    foreach ($lines as $line) {
                                        $line = trim($line);
                                        if (empty($line)) continue;
                                        // Abaikan header jika sedia ada dalam rentetan string
                                        if (stripos($line, 'GRED') !== false && stripos($line, 'KATEGORI') !== false) {
                                            continue;
                                        }
                                        $cols = preg_split('/\s+/', $line);
                                        if (!empty($cols)) {
                                            $has_rows = true;
                                            $gred = $cols[0] ?? '-';
                                            $kategori = $cols[1] ?? '-';
                                            $pengkhususan = isset($cols[2]) ? implode(' ', array_slice($cols, 2)) : '-';
                                            ?>
                                            <tr>
                                                <td class="fw-bold text-dark py-2"><?= htmlspecialchars($gred); ?></td>
                                                <td class="text-secondary py-2"><?= htmlspecialchars($kategori); ?></td>
                                                <td class="fw-bold text-primary py-2"><?= htmlspecialchars($pengkhususan); ?></td>
                                            </tr>
                                            <?php
                                        }
                                    }
                                }
                                if (!$has_rows):
                                ?>
                                    <tr>
                                        <td colspan="3" class="text-muted py-3">Tiada Maklumat Gred CIDB / Kewangan.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Senarai Dokumen Lampiran -->
                <div class="doc-section mb-4">
                    <div class="doc-header">Dokumen Lampiran & Tempoh Sah</div>

                    <!-- Salinan SSM -->
                    <div class="doc-row">
                        <div class="doc-title">Salinan Sijil SSM</div>
                        <div class="doc-content">
                            <a href="./uploads/<?= htmlspecialchars($profil['fail_ssm']); ?>" target="_blank" class="btn-pdf-view">
                                <i class="fa-solid fa-file-pdf"></i> Buka Dokumen PDF
                            </a>
                            <span class="pill-date pill-date-start">
                                <i class="fa-regular fa-calendar-check text-teal"></i>
                                <span class="date-label">Mula:</span>
                                <span class="date-value"><?= ($profil['tarikh_mula_ssm'] && $profil['tarikh_mula_ssm'] != '0000-00-00') ? htmlspecialchars($profil['tarikh_mula_ssm']) : '-'; ?></span>
                            </span>
                            <span class="pill-date pill-date-end">
                                <i class="fa-regular fa-calendar-xmark text-danger"></i>
                                <span class="date-label">Tamat:</span>
                                <span class="date-value"><?= ($profil['tarikh_tamat_ssm'] && $profil['tarikh_tamat_ssm'] != '0000-00-00') ? htmlspecialchars($profil['tarikh_tamat_ssm']) : '-'; ?></span>
                            </span>
                        </div>
                    </div>

                    <!-- Sijil TCC -->
                    <div class="doc-row">
                        <div class="doc-title">Sijil Kelulusan TCC (Cukai)</div>
                        <div class="doc-content">
                            <a href="./uploads/<?= htmlspecialchars($profil['fail_tcc']); ?>" target="_blank" class="btn-pdf-view">
                                <i class="fa-solid fa-file-pdf"></i> Buka Dokumen PDF
                            </a>
                            <span class="pill-date pill-date-start">
                                <i class="fa-regular fa-calendar-check text-teal"></i>
                                <span class="date-label">Mula:</span>
                                <span class="date-value"><?= ($profil['tarikh_mula_tcc'] && $profil['tarikh_mula_tcc'] != '0000-00-00') ? htmlspecialchars($profil['tarikh_mula_tcc']) : '-'; ?></span>
                            </span>
                            <span class="pill-date pill-date-end">
                                <i class="fa-regular fa-calendar-xmark text-danger"></i>
                                <span class="date-label">Tamat:</span>
                                <span class="date-value"><?= ($profil['tarikh_tamat_tcc'] && $profil['tarikh_tamat_tcc'] != '0000-00-00') ? htmlspecialchars($profil['tarikh_tamat_tcc']) : '-'; ?></span>
                            </span>
                        </div>
                    </div>

                    <!-- Sijil Sokongan Tambahan -->
                    <div class="doc-row">
                        <div class="doc-title">Sijil Sokongan Tambahan</div>
                        <div class="doc-content flex-column align-items-start gap-2">
                            <?php if(!empty($profil['fail_pkk'])): ?>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <a href="./uploads/<?= htmlspecialchars($profil['fail_pkk']); ?>" target="_blank" class="btn-sub-doc"><i class="fa-solid fa-folder-open text-warning"></i> Sijil PKK</a>
                                    <span class="pill-date pill-date-start">
                                        <span class="date-label">Mula:</span>
                                        <span class="date-value"><?= ($profil['tarikh_mula_pkk'] && $profil['tarikh_mula_pkk'] != '0000-00-00') ? htmlspecialchars($profil['tarikh_mula_pkk']) : '-'; ?></span>
                                    </span>
                                    <span class="pill-date pill-date-end">
                                        <span class="date-label">Tamat:</span>
                                        <span class="date-value"><?= ($profil['tarikh_tamat_pkk'] && $profil['tarikh_tamat_pkk'] != '0000-00-00') ? htmlspecialchars($profil['tarikh_tamat_pkk']) : '-'; ?></span>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <?php if(!empty($profil['fail_cidb_perakuan'])): ?>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <a href="./uploads/<?= htmlspecialchars($profil['fail_cidb_perakuan']); ?>" target="_blank" class="btn-sub-doc"><i class="fa-solid fa-folder-open text-warning"></i> CIDB Perakuan</a>
                                    <span class="pill-date pill-date-start">
                                        <span class="date-label">Mula:</span>
                                        <span class="date-value"><?= ($profil['tarikh_mula_cidb_perakuan'] && $profil['tarikh_mula_cidb_perakuan'] != '0000-00-00') ? htmlspecialchars($profil['tarikh_mula_cidb_perakuan']) : '-'; ?></span>
                                    </span>
                                    <span class="pill-date pill-date-end">
                                        <span class="date-label">Tamat:</span>
                                        <span class="date-value"><?= ($profil['tarikh_tamat_cidb_perakuan'] && $profil['tarikh_tamat_cidb_perakuan'] != '0000-00-00') ? htmlspecialchars($profil['tarikh_tamat_cidb_perakuan']) : '-'; ?></span>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <?php if(!empty($profil['fail_cidb_perolehan'])): ?>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <a href="./uploads/<?= htmlspecialchars($profil['fail_cidb_perolehan']); ?>" target="_blank" class="btn-sub-doc"><i class="fa-solid fa-folder-open text-warning"></i> CIDB Perolehan</a>
                                    <span class="pill-date pill-date-start">
                                        <span class="date-label">Mula:</span>
                                        <span class="date-value"><?= ($profil['tarikh_mula_cidb_perolehan'] && $profil['tarikh_mula_cidb_perolehan'] != '0000-00-00') ? htmlspecialchars($profil['tarikh_mula_cidb_perolehan']) : '-'; ?></span>
                                    </span>
                                    <span class="pill-date pill-date-end">
                                        <span class="date-label">Tamat:</span>
                                        <span class="date-value"><?= ($profil['tarikh_tamat_cidb_perolehan'] && $profil['tarikh_tamat_cidb_perolehan'] != '0000-00-00') ? htmlspecialchars($profil['tarikh_tamat_cidb_perolehan']) : '-'; ?></span>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <?php if(!empty($profil['fail_mof'])): ?>
                                <div>
                                    <a href="./uploads/<?= htmlspecialchars($profil['fail_mof']); ?>" target="_blank" class="btn-sub-doc"><i class="fa-solid fa-folder-open text-warning"></i> Sijil MOF</a>
                                </div>
                            <?php endif; ?>
                            
                            <?php if(empty($profil['fail_pkk']) && empty($profil['fail_cidb_perakuan']) && empty($profil['fail_cidb_perolehan']) && empty($profil['fail_mof'])): ?>
                                <span class="text-muted small fst-italic">Tiada dokumen lampiran tambahan dikemukakan.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Form Keputusan & Tindakan -->
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="profil_id" value="<?= htmlspecialchars($profil['id']); ?>">
                    
                    <div class="action-panel mb-4">
                        <h6 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-gavel text-primary"></i> Tindakan & Keputusan Semakan
                        </h6>
                        
                        <!-- Keputusan Dropdown -->
                        <div class="mb-3">
                            <label class="info-label mb-1">Keputusan Semakan Fail</label>
                            <select name="status_borang" id="status_borang" class="form-select form-select-lg fs-6 fw-semibold border-secondary-subtle" onchange="toggleAlasan()" required>
                                <option value="Lengkap">Lengkap (Teruskan untuk Bil Semakan Terimaan 21326)</option>
                                <option value="Tidak Lengkap">Tidak Lengkap (Hantar Notifikasi Pembetulan/Gagal)</option>
                            </select>
                        </div>
                        
                        <!-- Alasan Tolak (Disembunyikan secara lalai apabila 'Lengkap') -->
                        <div class="mb-3" id="wrapper_alasan_tolak" style="display: none;">
                            <label class="info-label text-danger mb-1"><i class="fa-solid fa-circle-exclamation me-1"></i>Alasan Penolakan / Catatan Tambahan (Jika Tidak Lengkap)</label>
                            <textarea name="alasan_tolak" class="form-control border-danger-subtle bg-white" rows="3" placeholder="Sila nyatakan dokumen mana yang tidak sah atau kabur jika memilih status 'Tidak Lengkap'..." style="border-radius: 8px; resize: none;"></textarea>
                        </div>

                        <!-- Muat Naik Bil -->
                        <div class="p-3 bg-white border rounded-3">
                            <label class="info-label mb-2">Lampiran Bil (PDF)</label>
                            <?php if(empty($profil['fail_bil'])): ?>
                                <div>
                                    <input type="file" name="fail_bil" class="form-control form-control-sm" accept="application/pdf">
                                </div>
                                <span class="text-muted small mt-2 d-block"><i class="fa-solid fa-circle-info text-primary me-1"></i> Lampirkan fail bil (PDF) jika kelulusan memerlukan pengeluaran bil.</span>
                            <?php else: ?>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-2 px-3"><i class="fa-solid fa-circle-check"></i> Bil Telah Ada</span>
                                    <a href="./uploads/<?= $profil['fail_bil']; ?>" target="_blank" class="text-primary small fw-semibold text-decoration-none ms-2"><i class="fa-solid fa-file-pdf"></i> Lihat Fail Bil Sedia Ada</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Submit Buttons -->
                    <div class="row g-3">
                        <div class="col-md-4 order-2 order-md-1">
                            <a href="kejuruteraan.php" class="btn-mdbg-back">
                                <i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard
                            </a>
                        </div>
                        <div class="col-md-8 order-1 order-md-2">
                            <button type="submit" name="kemaskini_pendaftaran" class="btn btn-mdbg-submit d-flex align-items-center justify-content-center gap-2">
                                <i class="fa-solid fa-paper-plane"></i> Simpan Keputusan & Hantar Notifikasi Rasmi
                            </button>
                        </div>
                    </div>
                </form>

            <?php else: ?>
                <!-- Empty State -->
                <div class="text-center py-5">
                    <div class="text-success mb-3">
                        <i class="fa-solid fa-circle-check fa-4x opacity-75"></i>
                    </div>
                    <h5 class="fw-bold text-dark">Tiada Semakan Tertunggak</h5>
                    <p class="text-muted small px-md-5 mb-4">Tiada sebarang tugasan semakan fail atau pendaftaran kontraktor baharu bertaraf <strong>'Pending'</strong> buat masa sekarang.</p>
                    <div style="max-width: 250px; margin: 0 auto;">
                        <a href="kejuruteraan.php" class="btn-mdbg-back py-2 fs-6 justify-content-center">
                            <i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
    </div>

    <!-- Skrip JavaScript Untuk Memaparkan/Sembunyikan Petak Alasan Penolakan -->
    <script>
        function toggleAlasan() {
            var statusSelect = document.getElementById('status_borang');
            var wrapperAlasan = document.getElementById('wrapper_alasan_tolak');
            
            if (statusSelect.value === 'Tidak Lengkap') {
                wrapperAlasan.style.display = 'block';
            } else {
                wrapperAlasan.style.display = 'none';
            }
        }

        // Jalankan sekali semasa paparan sedia ada dimuatkan
        document.addEventListener('DOMContentLoaded', toggleAlasan);
    </script>
</body>
</html>