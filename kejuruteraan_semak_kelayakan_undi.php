<?php
session_start();

// 1. Sekat daripada menyimpan cache halaman ini
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

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

// SEMAK & TAMBAH KOLUM DATABASE SECARA AUTOMATIK JIKA BELUM ADA
$check_col1 = mysqli_query($conn, "SHOW COLUMNS FROM kontraktor_undi LIKE 'no_bil_transaksi'");
if (mysqli_num_rows($check_col1) == 0) {
    mysqli_query($conn, "ALTER TABLE kontraktor_undi ADD COLUMN no_bil_transaksi VARCHAR(100) DEFAULT NULL");
}
$check_col2 = mysqli_query($conn, "SHOW COLUMNS FROM kontraktor_undi LIKE 'fail_bil_undi'");
if (mysqli_num_rows($check_col2) == 0) {
    mysqli_query($conn, "ALTER TABLE kontraktor_undi ADD COLUMN fail_bil_undi VARCHAR(255) DEFAULT NULL");
}
$check_col3 = mysqli_query($conn, "SHOW COLUMNS FROM kontraktor_undi LIKE 'fail_resit_undi'");
if (mysqli_num_rows($check_col3) == 0) {
    mysqli_query($conn, "ALTER TABLE kontraktor_undi ADD COLUMN fail_resit_undi VARCHAR(255) DEFAULT NULL");
}
$check_col4 = mysqli_query($conn, "SHOW COLUMNS FROM kontraktor_undi LIKE 'alasan_tolak'");
if (mysqli_num_rows($check_col4) == 0) {
    mysqli_query($conn, "ALTER TABLE kontraktor_undi ADD COLUMN alasan_tolak TEXT DEFAULT NULL");
}
$check_col5 = mysqli_query($conn, "SHOW COLUMNS FROM kontraktor_undi LIKE 'kategori_id'");
if (mysqli_num_rows($check_col5) == 0) {
    mysqli_query($conn, "ALTER TABLE kontraktor_undi ADD COLUMN kategori_id INT DEFAULT NULL");
}

// 1. AMBIL SEMUA KONTRAKTOR DARI JADUAL 'kontraktor_undi'
$senarai_kontraktor = [];
$ambil_semua = mysqli_query($conn, "
    SELECT id, nama_syarikat, status_undi 
    FROM kontraktor_undi 
    ORDER BY nama_syarikat ASC
");
while ($r = mysqli_fetch_assoc($ambil_semua)) {
    $senarai_kontraktor[] = $r;
}

// 2. MENGAMBIL ID DARI LIST BOX YANG DIPILIH
$id_undi = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Nilai lalai (Default values)
$nama_syarikat = "TIADA MAKLUMAT"; 
$no_pendaftaran = "Tiada Maklumat";
$alamat = "Tiada Maklumat";
$no_tel = "Tiada Maklumat";
$email = "Tiada Maklumat";
$gred_cidb = "Tiada Maklumat";
$kod_bidang = "Sila Semak Dokumen Lampiran";
$status_semasa = "Pending";
$no_bil_transaksi = "";
$fail_bil_undi = "";
$alasan_tolak = "";
$nama_kategori_undi = "Tiada Kategori Dipilih";

// Variable fail & tarikh
$fail_dokumen = "";
$fail_pkk = "";
$tarikh_mula_pkk = "";
$tarikh_tamat_pkk = "";
$fail_cidb_perakuan = "";
$tarikh_mula_cidb_perakuan = "";
$tarikh_tamat_cidb_perakuan = "";
$fail_cidb_perolehan = "";
$tarikh_mula_cidb_perolehan = "";
$tarikh_tamat_cidb_perolehan = "";
$fail_mof = "";
$fail_ssm = "";
$tarikh_mula_ssm = "";
$tarikh_tamat_ssm = "";
$fail_tcc = "";
$tarikh_mula_tcc = "";
$tarikh_tamat_tcc = "";

if ($id_undi > 0) {
    // Tarik maklumat dari kontraktor_undi dan hubungkan dengan kontraktor_profil & kategori_undi
    $query = mysqli_query($conn, "
        SELECT u.*, 
               k.pilihan AS nama_kategori_undi,
               p.gred_cidb_kewangan AS p_gred_cidb_kewangan,
               p.alamat AS p_alamat,
               p.no_pendaftaran AS p_no_pendaftaran,
               p.no_telefon_syarikat AS p_no_tel,
               p.email_aktif AS p_email,
               p.fail_ssm AS p_fail_ssm,
               p.tarikh_mula_ssm AS p_tarikh_mula_ssm,
               p.tarikh_tamat_ssm AS p_tarikh_tamat_ssm,
               p.fail_pkk AS p_fail_pkk,
               p.tarikh_mula_pkk AS p_tarikh_mula_pkk,
               p.tarikh_tamat_pkk AS p_tarikh_tamat_pkk,
               p.fail_cidb_perakuan AS p_fail_cidb_perakuan,
               p.tarikh_mula_cidb_perakuan AS p_tarikh_mula_cidb_perakuan,
               p.tarikh_tamat_cidb_perakuan AS p_tarikh_tamat_cidb_perakuan,
               p.fail_cidb_perolehan AS p_fail_cidb_perolehan,
               p.tarikh_mula_cidb_perolehan AS p_tarikh_mula_cidb_perolehan,
               p.tarikh_tamat_cidb_perolehan AS p_tarikh_tamat_cidb_perolehan,
               p.fail_mof AS p_fail_mof,
               p.fail_tcc AS p_fail_tcc,
               p.tarikh_mula_tcc AS p_tarikh_mula_tcc,
               p.tarikh_tamat_tcc AS p_tarikh_tamat_tcc
        FROM kontraktor_undi u
        LEFT JOIN kontraktor_profil p ON u.user_id = p.user_id
        LEFT JOIN kategori_undi k ON u.kategori_id = k.id
        WHERE u.id = $id_undi
    ");
    
    if ($row = mysqli_fetch_assoc($query)) {
        $nama_syarikat = $row['nama_syarikat'];
        $no_pendaftaran = !empty($row['no_pendaftaran']) ? $row['no_pendaftaran'] : (!empty($row['p_no_pendaftaran']) ? $row['p_no_pendaftaran'] : "Tiada Maklumat");
        $nama_kategori_undi = !empty($row['nama_kategori_undi']) ? $row['nama_kategori_undi'] : "Tiada Kategori Dipilih";
        $alamat = !empty($row['alamat']) ? $row['alamat'] : (!empty($row['p_alamat']) ? $row['p_alamat'] : "Tiada Maklumat");
        $no_tel = !empty($row['no_tel']) ? $row['no_tel'] : (!empty($row['p_no_tel']) ? $row['p_no_tel'] : "Tiada Maklumat");
        $email = !empty($row['email']) ? $row['email'] : (!empty($row['p_email']) ? $row['p_email'] : "Tiada Maklumat");
        $gred_cidb = !empty($row['gred_cidb_kewangan']) ? $row['gred_cidb_kewangan'] : (!empty($row['p_gred_cidb_kewangan']) ? $row['p_gred_cidb_kewangan'] : "Tiada Maklumat");
        $status_semasa = $row['status_undi'];

        // Fail Undi Utama
        $fail_dokumen = $row['fail_dokumen'] ?? "";

        // Sijil PKK & Tarikh
        $fail_pkk = !empty($row['fail_pkk']) ? $row['fail_pkk'] : ($row['p_fail_pkk'] ?? "");
        $tarikh_mula_pkk = !empty($row['tarikh_mula_pkk']) ? $row['tarikh_mula_pkk'] : ($row['p_tarikh_mula_pkk'] ?? "");
        $tarikh_tamat_pkk = !empty($row['tarikh_tamat_pkk']) ? $row['tarikh_tamat_pkk'] : ($row['p_tarikh_tamat_pkk'] ?? "");

        // Perakuan CIDB & Tarikh
        $fail_cidb_perakuan = !empty($row['fail_cidb_perakuan']) ? $row['fail_cidb_perakuan'] : ($row['p_fail_cidb_perakuan'] ?? "");
        $tarikh_mula_cidb_perakuan = !empty($row['tarikh_mula_cidb_perakuan']) ? $row['tarikh_mula_cidb_perakuan'] : ($row['p_tarikh_mula_cidb_perakuan'] ?? "");
        $tarikh_tamat_cidb_perakuan = !empty($row['tarikh_tamat_cidb_perakuan']) ? $row['tarikh_tamat_cidb_perakuan'] : ($row['p_tarikh_tamat_cidb_perakuan'] ?? "");

        // Perolehan CIDB & Tarikh
        $fail_cidb_perolehan = !empty($row['fail_cidb_perolehan']) ? $row['fail_cidb_perolehan'] : ($row['p_fail_cidb_perolehan'] ?? "");
        $tarikh_mula_cidb_perolehan = !empty($row['tarikh_mula_cidb_perolehan']) ? $row['tarikh_mula_cidb_perolehan'] : ($row['p_tarikh_mula_cidb_perolehan'] ?? "");
        $tarikh_tamat_cidb_perolehan = !empty($row['tarikh_tamat_cidb_perolehan']) ? $row['tarikh_tamat_cidb_perolehan'] : ($row['p_tarikh_tamat_cidb_perolehan'] ?? "");

        // Sijil MOF
        $fail_mof = !empty($row['fail_mof']) ? $row['fail_mof'] : ($row['p_fail_mof'] ?? "");

        // Sijil SSM & TCC (Gantian/Profil)
        $fail_ssm = $row['p_fail_ssm'] ?? "";
        $tarikh_mula_ssm = $row['p_tarikh_mula_ssm'] ?? "";
        $tarikh_tamat_ssm = $row['p_tarikh_tamat_ssm'] ?? "";

        $fail_tcc = $row['p_fail_tcc'] ?? "";
        $tarikh_mula_tcc = $row['p_tarikh_mula_tcc'] ?? "";
        $tarikh_tamat_tcc = $row['p_tarikh_tamat_tcc'] ?? "";

        $no_bil_transaksi = isset($row['no_bil_transaksi']) ? $row['no_bil_transaksi'] : "";
        $fail_bil_undi = isset($row['fail_bil_undi']) ? $row['fail_bil_undi'] : "";
        $alasan_tolak = isset($row['alasan_tolak']) ? $row['alasan_tolak'] : "";
    }
}

// PROSES PARSING GRED CIDB UNTUK DISPLAY JADUAL (JSON & TEXT SUPPORTED)
$gred_rows = [];
if ($gred_cidb != 'Tiada Maklumat' && !empty(trim($gred_cidb))) {
    $json_data = json_decode($gred_cidb, true);
    
    // 1. Jika data berformat JSON
    if (json_last_error() === JSON_ERROR_NONE && is_array($json_data)) {
        foreach ($json_data as $item) {
            $gred_rows[] = [
                'gred' => !empty($item['gred']) ? $item['gred'] : '-',
                'kategori' => !empty($item['kategori']) ? $item['kategori'] : '-',
                'pengkhususan' => !empty($item['pengkhususan']) ? $item['pengkhususan'] : '-'
            ];
        }
    } else {
        // 2. Jika data berformat Teks / String biasa
        $lines = array_filter(array_map('trim', explode("\n", str_replace("\r", "", $gred_cidb))));
        foreach ($lines as $line) {
            if (stripos($line, 'GRED') !== false && stripos($line, 'KATEGORI') !== false) {
                continue; // Abaikan tajuk header jika wujud dalam teks
            }
            $cols = preg_split('/\s+/', $line);
            if (count($cols) >= 3) {
                $gred_rows[] = [
                    'gred' => $cols[0],
                    'kategori' => $cols[1],
                    'pengkhususan' => implode(' ', array_slice($cols, 2))
                ];
            } elseif (count($cols) == 2) {
                $gred_rows[] = [
                    'gred' => $cols[0],
                    'kategori' => $cols[1],
                    'pengkhususan' => '-'
                ];
            } elseif (count($cols) == 1 && !empty($cols[0])) {
                $gred_rows[] = [
                    'gred' => $cols[0],
                    'kategori' => '-',
                    'pengkhususan' => '-'
                ];
            }
        }
    }
}

// 3. PROSES SIMPAN KEPUTUSAN KELAYAKAN & BIL TRANSAKSI SERTA HANTAR NOTIFIKASI EMAIL
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_keputusan'])) {
    $keputusan = mysqli_real_escape_string($conn, $_POST['keputusan_kelayakan']);
    $bil_transaksi = mysqli_real_escape_string($conn, trim($_POST['no_bil_transaksi']));
    $input_alasan_tolak = mysqli_real_escape_string($conn, trim($_POST['alasan_tolak']));
    
    // Muat naik fail bil jika dimuat naik oleh Admin
    $fail_bil_query = "";
    if (!empty($_FILES['fail_bil_undi']['name'])) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $filename = time() . "_bil_undi_" . basename($_FILES['fail_bil_undi']['name']);
        if (move_uploaded_file($_FILES['fail_bil_undi']['tmp_name'], $target_dir . $filename)) {
            $fail_bil_query = ", fail_bil_undi = '$filename'";
        }
    }

    if ($id_undi > 0) {
        // Mengemas kini status_undi, no_bil_transaksi & alasan_tolak di jadual kontraktor_undi
        $update_status = mysqli_query($conn, "
            UPDATE kontraktor_undi 
            SET status_undi = '$keputusan',
                no_bil_transaksi = '$bil_transaksi',
                alasan_tolak = '$input_alasan_tolak'
                $fail_bil_query
            WHERE id = $id_undi
        ");
        
        if ($update_status) {
            // --- HANTAR NOTIFIKASI E-MEL MENGGUNAKAN PHPMAILER ---
            $get_email_query = mysqli_query($conn, "
                SELECT u.nama_syarikat, u.email, p.email_aktif 
                FROM kontraktor_undi u
                LEFT JOIN kontraktor_profil p ON u.user_id = p.user_id
                WHERE u.id = $id_undi
            ");
            $data_user = mysqli_fetch_assoc($get_email_query);

            $penerima = !empty($data_user['email']) ? $data_user['email'] : ($data_user['email_aktif'] ?? '');
            $nama_syarikat_email = !empty($data_user['nama_syarikat']) ? $data_user['nama_syarikat'] : 'Kontraktor';

            if (!empty($penerima)) {
                $mail = new PHPMailer(true);

                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com'; 
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'prk.mdbg@mdbg.gov.my';       
                    $mail->Password   = 'bgcd cdwt bfup uxeq'; 
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
                    $mail->Port       = 465;

                    $mail->SMTPOptions = array(
                        'ssl' => array(
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        )
                    );

                    $mail->setFrom('prk.mdbg@mdbg.gov.my', 'Majlis Daerah Batu Gajah');
                    $mail->addAddress($penerima, $nama_syarikat_email);

                    $mail->isHTML(true); 

                    if ($keputusan == 'Layak') {
                        $mail->Subject = "MDBG - Keputusan Semakan Kelayakan Undi: LAYAK";
                        $mail->Body    = "Salam Sejahtera <b>" . htmlspecialchars($nama_syarikat_email) . "</b>,<br><br>"
                                       . "Sukacita dimaklumkan bahawa semakan kelayakan penyertaan undi anda telah disahkan <b>LAYAK</b>.<br>"
                                       . "<b>No. Bil Transaksi:</b> " . htmlspecialchars($bil_transaksi) . "<br><br>"
                                       . "Sila log masuk ke dalam sistem untuk menyemak bil dan melengkapkan proses bayaran penyertaan undi.<br><br>"
                                       . "Terima kasih,<br><b>Majlis Daerah Batu Gajah</b>";
                    } elseif ($keputusan == 'Tidak Layak') {
                        $mail->Subject = "MDBG - Keputusan Semakan Kelayakan Undi: TIDAK LAYAK";
                        $mail->Body    = "Salam Sejahtera <b>" . htmlspecialchars($nama_syarikat_email) . "</b>,<br><br>"
                                       . "Dukacita dimaklumkan bahawa semakan kelayakan penyertaan undi anda berstatus <b>TIDAK LAYAK</b>.<br><br>"
                                       . "<b>Catatan / Alasan Penolakan:</b><br>"
                                       . nl2br(htmlspecialchars($input_alasan_tolak)) . "<br><br>"
                                       . "Sila log masuk semula ke dalam sistem untuk membuat pembetulan dokumen.<br><br>"
                                       . "Terima kasih,<br><b>Majlis Daerah Batu Gajah</b>";
                    } else {
                        $mail->Subject = "MDBG - Keputusan Semakan Kelayakan Undi: PENDING";
                        $mail->Body    = "Salam Sejahtera <b>" . htmlspecialchars($nama_syarikat_email) . "</b>,<br><br>"
                                       . "Semakan kelayakan penyertaan undi anda kini berstatus <b>PENDING</b>.<br><br>"
                                       . "Terima kasih,<br><b>Majlis Daerah Batu Gajah</b>";
                    }

                    $mail->send();
                } catch (Exception $e) {
                    echo "<script>alert('Perhatian: Keputusan BERJAYA disimpan, TETAPI e-mel GAGAL dihantar.\\n\\nRalat PHPMailer: " . addslashes($mail->ErrorInfo) . "');</script>";
                }
            } else {
                echo "<script>alert('Perhatian: Keputusan BERJAYA disimpan, TETAPI e-mel penerima tidak dijumpai.');</script>";
            }

            echo "<script>alert('Keputusan disimpan & notifikasi e-mel berjaya dihantar!'); window.location.href='?id=$id_undi';</script>";
        } else {
            echo "<script>alert('Ralat semasa mengemas kini keputusan: " . mysqli_error($conn) . "');</script>";
        }
    } else {
        echo "<script>alert('Sila pilih kontraktor terlebih dahulu sebelum menyimpan keputusan.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semakan Kod Bidang Undi | MDBG</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-mdbg: #8D5B4C;
            --primary-dark: #6e4438;
            --primary-gradient: linear-gradient(135deg, #8D5B4C 0%, #6E4438 40%, #56342A 100%);
            --bg-light: #f1f2f3;
            --text-dark: #2d3748;
        }

        body {
            background-color: var(--bg-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-dark);
        }

        .main-container {
            margin-top: 50px;
            margin-bottom: 50px;
            padding: 0 20px;
        }

        .semak-card {
            background-color: #ffffff;
            border: 1px solid rgba(0, 0, 0, 0.06);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            max-width: 750px;
            margin: 0 auto;
            overflow: hidden;
        }

        .card-header-custom {
            background: var(--primary-gradient);
            color: white;
            padding: 24px 30px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .card-body-custom {
            padding: 35px;
        }

        .mdbg-logo-header {
            width: 55px;
            height: 55px;
            object-fit: contain;
            background-color: #ffffff;
            padding: 4px;
            border-radius: 50%;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            flex-shrink: 0;
        }

        .listbox-container {
            background-color: #fff9f6;
            border: 1.5px dashed #ebd5ce;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .info-display-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
        }

        .info-label {
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #718096;
            font-weight: 700;
        }

        .info-value {
            font-size: 1.05rem;
            color: #1a202c;
            font-weight: 600;
        }

        .form-label-custom {
            font-weight: 700;
            color: #4a5568;
            font-size: 0.9rem;
            margin-bottom: 8px;
        }

        .form-select-custom, .form-control-custom {
            padding: 12px 16px;
            border-radius: 8px;
            border: 1.5px solid #cbd5e1;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .form-select-custom:focus, .form-control-custom:focus {
            border-color: var(--primary-mdbg);
            box-shadow: 0 0 0 3px rgba(141, 91, 76, 0.15);
        }

        .btn-mdbg-secondary {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #475569;
            font-weight: 600;
            padding: 12px 24px;
            border-radius: 8px;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-mdbg-secondary:hover {
            background-color: #e2e8f0;
            color: #334155;
        }

        .btn-mdbg-primary {
            background-color: var(--primary-mdbg);
            border: 1px solid var(--primary-mdbg);
            color: white;
            font-weight: 600;
            padding: 12px 24px;
            border-radius: 8px;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(141, 91, 76, 0.2);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-mdbg-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(141, 91, 76, 0.3);
        }

        .table-cidb th {
            font-size: 0.78rem;
            letter-spacing: 0.8px;
            color: #475569;
            background-color: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 10px;
        }

        .table-cidb td {
            font-size: 0.92rem;
            padding: 10px;
            border-bottom: 1px solid #f1f5f9;
        }
    </style>
</head>
<body>

    <div class="container main-container">
        <div class="semak-card">
            
            <div class="card-header-custom d-flex align-items-center gap-3">
                <img src="logo_mdbg.png" alt="Logo MDBG" class="mdbg-logo-header">
                <div>
                    <h5 class="m-0 fw-bold">Semakan Kod Bidang Undi</h5>
                    <span class="small opacity-75">Keputusan kelayakan kontraktor untuk cabutan undian</span>
                </div>
            </div>

            <div class="card-body-custom">

                <!-- LIST BOX: Carian & Pemilihan Kontraktor dari kontraktor_undi -->
                <div class="listbox-container">
                    <label for="pilih_kontraktor" class="form-label form-label-custom text-primary-mdbg">
                        <i class="fa-solid fa-users-gear me-1"></i> Pilih Kontraktor Untuk Semakan (Jadual Undi)
                    </label>
                    <select class="form-select form-select-custom" id="pilih_kontraktor" onchange="tukarKontraktor(this.value)">
                        <option value="0">-- Pilih Kontraktor di Sini --</option>
                        <?php foreach ($senarai_kontraktor as $kontraktor): ?>
                            <option value="<?= $kontraktor['id']; ?>" <?= ($id_undi == $kontraktor['id']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($kontraktor['nama_syarikat']); ?> (Status: <?= htmlspecialchars($kontraktor['status_undi']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <form action="" method="POST" enctype="multipart/form-data">
                    
                    <!-- Paparan Maklumat Kontraktor Terpilih -->
                    <div class="info-display-box mb-4">
                        <div class="row g-3">
                            <!-- Nama Syarikat -->
                            <div class="col-12 border-bottom pb-3" style="border-color: #e2e8f0 !important;">
                                <div class="info-label">Nama Syarikat (Kontraktor)</div>
                                <div class="info-value text-uppercase mt-1 text-primary-mdbg">
                                    <i class="fa-solid fa-hotel me-2 text-muted small"></i><?= htmlspecialchars($nama_syarikat); ?>
                                </div>
                            </div>

                            <!-- No. Pendaftaran Syarikat -->
                            <div class="col-12 border-bottom pb-3" style="border-color: #e2e8f0 !important;">
                                <div class="info-label">No. Pendaftaran Syarikat</div>
                                <div class="info-value mt-1 text-dark" style="font-size: 0.95rem;">
                                    <i class="fa-solid fa-id-card me-2 text-muted small"></i><?= htmlspecialchars($no_pendaftaran); ?>
                                </div>
                            </div>

                            <!-- Kategori Undi Dipilih Kontraktor -->
                            <div class="col-12 border-bottom pb-3" style="border-color: #e2e8f0 !important;">
                                <div class="info-label">Kategori Undi Dipilih</div>
                                <div class="info-value text-uppercase mt-1 text-success fw-bold">
                                    <i class="fa-solid fa-layer-group me-2 text-muted small"></i><?= htmlspecialchars($nama_kategori_undi); ?>
                                </div>
                            </div>

                            <!-- Alamat Syarikat -->
                            <div class="col-12 border-bottom pb-3" style="border-color: #e2e8f0 !important;">
                                <div class="info-label">Alamat Syarikat</div>
                                <div class="info-value mt-1 text-dark" style="font-size: 0.95rem;">
                                    <i class="fa-solid fa-location-dot me-2 text-muted small"></i><?= !empty($alamat) && $alamat != 'Tiada Maklumat' ? nl2br(htmlspecialchars($alamat)) : '<span class="text-muted">Tiada Maklumat</span>'; ?>
                                </div>
                            </div>

                            <!-- No Tel & Email -->
                            <div class="col-md-6 border-bottom pb-3" style="border-color: #e2e8f0 !important;">
                                <div class="info-label">No. Telefon</div>
                                <div class="info-value mt-1 text-dark" style="font-size: 0.95rem;">
                                    <i class="fa-solid fa-phone me-2 text-muted small"></i><?= htmlspecialchars($no_tel); ?>
                                </div>
                            </div>
                            <div class="col-md-6 border-bottom pb-3" style="border-color: #e2e8f0 !important;">
                                <div class="info-label">E-mel</div>
                                <div class="info-value mt-1 text-dark" style="font-size: 0.95rem;">
                                    <i class="fa-solid fa-envelope me-2 text-muted small"></i><?= htmlspecialchars($email); ?>
                                </div>
                            </div>
                            
                            <!-- Dokumen Lampiran -->
                            <div class="col-12">
                                <div class="info-label mb-2">Dokumen Lampiran / Fail Muat Naik</div>
                                <?php if ($id_undi > 0): ?>
                                    <?php 
                                    $has_file = false;
                                    $senarai_fail = [
                                        [
                                            'label' => 'Sijil SSM',
                                            'file'  => $fail_ssm,
                                            'mula'  => $tarikh_mula_ssm,
                                            'tamat' => $tarikh_tamat_ssm
                                        ],
                                        [
                                            'label' => 'Sijil PKK',
                                            'file'  => $fail_pkk,
                                            'mula'  => $tarikh_mula_pkk,
                                            'tamat' => $tarikh_tamat_pkk
                                        ],
                                        [
                                            'label' => 'Perakuan CIDB',
                                            'file'  => $fail_cidb_perakuan,
                                            'mula'  => $tarikh_mula_cidb_perakuan,
                                            'tamat' => $tarikh_tamat_cidb_perakuan
                                        ],
                                        [
                                            'label' => 'Perolehan CIDB',
                                            'file'  => $fail_cidb_perolehan,
                                            'mula'  => $tarikh_mula_cidb_perolehan,
                                            'tamat' => $tarikh_tamat_cidb_perolehan
                                        ],
                                        [
                                            'label' => 'Sijil MOF',
                                            'file'  => $fail_mof,
                                            'mula'  => null,
                                            'tamat' => null
                                        ],
                                        [
                                            'label' => 'Sijil TCC',
                                            'file'  => $fail_tcc,
                                            'mula'  => $tarikh_mula_tcc,
                                            'tamat' => $tarikh_tamat_tcc
                                        ]
                                    ];

                                    foreach ($senarai_fail as $item) {
                                        if (!empty($item['file'])) { $has_file = true; break; }
                                    }
                                    ?>

                                    <?php if ($has_file): ?>
                                        <div class="row g-2">
                                            <?php foreach ($senarai_fail as $item): 
                                                if (!empty($item['file'])): 
                                                    $t_mula = (!empty($item['mula']) && $item['mula'] != '0000-00-00') ? date('d/m/Y', strtotime($item['mula'])) : '';
                                                    $t_tamat = (!empty($item['tamat']) && $item['tamat'] != '0000-00-00') ? date('d/m/Y', strtotime($item['tamat'])) : '';
                                            ?>
                                                    <div class="col-md-6 col-12">
                                                        <div class="d-flex align-items-center justify-content-between p-2 bg-white rounded border h-100">
                                                            <a href="uploads/<?= htmlspecialchars($item['file']); ?>" target="_blank" class="btn btn-sm btn-outline-danger py-1 px-2 text-nowrap">
                                                                <?= $item['label']; ?>
                                                            </a>
                                                            <?php if ($t_mula || $t_tamat): ?>
                                                                <span class="badge bg-light text-dark border fw-normal ms-1 text-end" style="font-size: 0.75rem;">
                                                                    <i class="fa-regular fa-calendar-days me-1 text-primary"></i>
                                                                    <?= $t_mula ?: '-'; ?> - <?= $t_tamat ?: '-'; ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                            <?php 
                                                endif;
                                            endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">Tiada fail dimuat naik</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small">Tiada fail dimuat naik</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- GRED KELAYAKAN CIDB / KEWANGAN -->
                    <div class="info-display-box mb-4">
                        <div class="info-label mb-3 text-dark d-flex align-items-center gap-2" style="font-size: 0.85rem;">
                            <i class="fa-solid fa-gear text-secondary"></i> GRED KELAYAKAN CIDB / KEWANGAN
                        </div>
                        
                        <div class="table-responsive rounded border">
                            <table class="table table-bordered table-cidb text-center align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 30%;">GRED</th>
                                        <th style="width: 35%;">KATEGORI</th>
                                        <th style="width: 35%;">PENGKHUSUSAN</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($gred_rows)): ?>
                                        <?php foreach ($gred_rows as $row_gred): ?>
                                            <tr>
                                                <td class="fw-bold text-dark"><?= htmlspecialchars($row_gred['gred']); ?></td>
                                                <td class="text-secondary"><?= htmlspecialchars($row_gred['kategori']); ?></td>
                                                <td class="text-primary fw-semibold"><?= htmlspecialchars($row_gred['pengkhususan']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td class="fw-bold text-dark"><?= ($gred_cidb != 'Tiada Maklumat') ? htmlspecialchars($gred_cidb) : '-'; ?></td>
                                            <td class="text-secondary">-</td>
                                            <td class="text-secondary">-</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Pilihan Status Kelayakan -->
                    <div class="mb-3">
                        <label for="keputusan_kelayakan" class="form-label form-label-custom">
                            <i class="fa-solid fa-gavel me-1 text-muted"></i> Keputusan Kelayakan Sijil Kerja (Jabatan Kejuruteraan)
                        </label>
                        <select class="form-select form-select-custom w-100" id="keputusan_kelayakan" name="keputusan_kelayakan" onchange="kemaskiniAlasanTolakDisplay()" required <?= ($id_undi == 0) ? 'disabled' : ''; ?>>
                            <option value="" disabled selected>-- Sila Pilih Status Kelayakan --</option>
                            <option value="Layak" <?= ($status_semasa == 'Layak') ? 'selected' : ''; ?>>Layak (Sedia untuk bayaran penyertaan undi)</option>
                            <option value="Tidak Layak" <?= ($status_semasa == 'Tidak Layak') ? 'selected' : ''; ?>>Tidak Layak / Gagal Semakan</option>
                        </select>
                    </div>

                    <!-- Ruangan Input Alasan Tolak -->
                    <div class="mb-4" id="ruangan_alasan_tolak" style="<?= ($status_semasa == 'Tidak Layak') ? 'display: block;' : 'display: none;'; ?>">
                        <label for="alasan_tolak" class="form-label form-label-custom text-danger">
                            <i class="fa-solid fa-circle-xmark me-1"></i> Alasan Tolak (Nyatakan sebab jika Tidak Layak)
                        </label>
                        <textarea class="form-control form-control-custom" id="alasan_tolak" name="alasan_tolak" rows="3" placeholder="Sila tuliskan alasan penolakan di sini..." <?= ($id_undi == 0) ? 'disabled' : ''; ?>><?= htmlspecialchars($alasan_tolak); ?></textarea>
                    </div>

                    <!-- Bil Transaksi & Muat Naik Dokumen Bil -->
                    <div class="mb-4 p-3 border rounded style-bg-bil" style="background-color: #fcf8f6; border-color: #ebd5ce !important;">
                        <label for="no_bil_transaksi" class="form-label form-label-custom text-primary-mdbg">
                            <i class="fa-solid fa-file-invoice-dollar me-1"></i> Bil Transaksi 21355 (Jabatan Kejuruteraan)
                        </label>
                        <input type="text" class="form-control form-control-custom mb-3" id="no_bil_transaksi" name="no_bil_transaksi" value="<?= htmlspecialchars($no_bil_transaksi ? $no_bil_transaksi : '21355'); ?>" placeholder="Masukkan No. Bil Transaksi (cth: 21355)" <?= ($id_undi == 0) ? 'disabled' : ''; ?>>
                        
                        <label for="fail_bil_undi" class="form-label form-label-custom text-muted small">
                            <i class="fa-solid fa-upload me-1"></i> Lampirkan Fail Bil Transaksi PDF (Jika Ada)
                        </label>
                        <input type="file" class="form-control form-control-custom" id="fail_bil_undi" name="fail_bil_undi" accept="application/pdf" <?= ($id_undi == 0) ? 'disabled' : ''; ?>>
                        <?php if (!empty($fail_bil_undi)): ?>
                            <div class="mt-2">
                                <a href="uploads/<?= htmlspecialchars($fail_bil_undi); ?>" target="_blank" class="text-primary small fw-semibold">
                                    Lihat Fail Bil Dimuat Naik
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <hr class="my-4" style="opacity: 0.1;">

                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-stretch align-items-sm-center gap-3">
                        <a href="kejuruteraan.php" class="btn-mdbg-secondary">
                            <i class="fa-solid fa-arrow-left"></i> Kembali ke Senarai
                        </a>
                        <button type="submit" name="simpan_keputusan" class="btn-mdbg-primary" <?= ($id_undi == 0) ? 'disabled' : ''; ?>>
                            <i class="fa-solid fa-floppy-disk"></i> Simpan Keputusan
                        </button>
                    </div>

                </form>
            </div>

        </div>
    </div>

    <script>
    function tukarKontraktor(idDaftar) {
        if(idDaftar !== "0") {
            window.location.href = "?id=" + idDaftar;
        } else {
            window.location.href = window.location.pathname;
        }
    }

    function kemaskiniAlasanTolakDisplay() {
        var status = document.getElementById('keputusan_kelayakan').value;
        var box = document.getElementById('ruangan_alasan_tolak');
        if (status === 'Tidak Layak') {
            box.style.display = 'block';
        } else {
            box.style.display = 'none';
        }
    }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bundle.min.js"></script>
</body>
</html>