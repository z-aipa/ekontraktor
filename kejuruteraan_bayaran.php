<?php
session_start();

// Sekat daripada menyimpan cache halaman ini
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Pastikan hanya admin kejuruteraan yang boleh akses
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

$target_dir = "uploads/";
define('MAX_FILE_SIZE', 10485760); // Had maksimum saiz fail: 10MB (10 * 1024 * 1024 bytes)

// ==========================================
// LOGIK PROSES MUAT NAIK BIL (ADMIN)
// ==========================================
if (isset($_POST['action_upload_bil'])) {
    $profil_id = mysqli_real_escape_string($conn, $_POST['profil_id']);
    if (!empty($_FILES['fail_bil']['name'])) {
        
        // Validasi Saiz Fail (Tidak boleh lebih 10MB)
        if ($_FILES['fail_bil']['size'] > MAX_FILE_SIZE) {
            echo "<script>alert('Gagal! Saiz fail bil melebihi had maksimum 10MB.'); window.location.href='kejuruteraan_bayaran.php';</script>";
            exit();
        }

        $filename = time() . "_bil_" . basename($_FILES['fail_bil']['name']);
        $target_file = $target_dir . $filename;
        if (move_uploaded_file($_FILES['fail_bil']['tmp_name'], $target_file)) {
            $conn->query("UPDATE kontraktor_profil SET fail_bil = '$filename' WHERE id = '$profil_id'");
            echo "<script>alert('Bil pendaftaran berjaya dihantar ke dashboard kontraktor!'); window.location.href='kejuruteraan_bayaran.php';</script>";
            exit();
        } else {
            echo "<script>alert('Gagal memuat naik bil.');</script>";
        }
    }
}

// ==========================================
// LOGIK SAHKAN BAYARAN, JANA SIJIL AUTOMATIK & HANTAR E-MEL (ADMIN)
// ==========================================
if (isset($_POST['action_sahkan_bayaran'])) {
    $profil_id = mysqli_real_escape_string($conn, $_POST['profil_id']);

    // 1. Ambil maklumat kontraktor dari database
    $query_kontraktor = $conn->query("SELECT * FROM kontraktor_profil WHERE id = '$profil_id'");
    
    if ($query_kontraktor && $query_kontraktor->num_rows > 0) {
        $kontraktor = $query_kontraktor->fetch_assoc();

        // 1b. Sekatan Keselamatan: Sahkan bayaran hanya boleh diproses jika resit kontraktor sudah dimuat naik
        if (empty($kontraktor['fail_resit'])) {
            echo "<script>alert('Gagal! Kontraktor ini belum muat naik resit bayaran.'); window.location.href='kejuruteraan_bayaran.php';</script>";
            exit();
        }

        // 2. Semak jika fail templat sijil wujud
        if (!file_exists('template_sijil.html')) {
            echo "<script>alert('Gagal! Fail template_sijil.html tidak dijumpai.'); window.location.href='kejuruteraan_bayaran.php';</script>";
            exit();
        }

        // Gunakan __DIR__ supaya PHP mencari terus di folder utama 'ekontraktor'
        $path_gambar = __DIR__ . '/sijil_template.jpg'; 

        $image_base64 = '';
        if (file_exists($path_gambar)) {
            $type_gambar = pathinfo($path_gambar, PATHINFO_EXTENSION);
            $data_gambar = file_get_contents($path_gambar);
            $image_base64 = 'data:image/' . $type_gambar . ';base64,' . base64_encode($data_gambar);
        } else {
            // Amaran ini akan keluar jika gambar masih tiada di lokasi tersebut
            echo "<script>alert('Ralat! Fail gambar tidak wujud di: " . addslashes($path_gambar) . "'); window.location.href='kejuruteraan_bayaran.php';</script>";
            exit();
        }

        // 3. Baca templat HTML
        $template = file_get_contents('template_sijil.html');

        // 4. Kira jujukan nombor siri (format 5 digit nombor sahaja: 00001, 00002, ...)
        // $query_sijil = $conn->query("SELECT COUNT(*) AS total_sijil FROM kontraktor_profil WHERE fail_sijil IS NOT NULL AND fail_sijil != ''");
        // $data_sijil  = $query_sijil->fetch_assoc();
        // $nombor_jujukan = $data_sijil['total_sijil'] + 1;
        // $no_siri = "MDBG/" . date('Y') . "/" . str_pad($nombor_jujukan, 5, '0', STR_PAD_LEFT);

        $tahun_semasa = date('Y');
        $query_sijil = $conn->query("SELECT COUNT(*) AS total_sijil FROM kontraktor_profil WHERE fail_sijil IS NOT NULL AND fail_sijil != '' AND YEAR(tarikh_mula_aktif) = '$tahun_semasa'");
        $data_sijil  = $query_sijil->fetch_assoc();
        
        $nombor_jujukan = $data_sijil['total_sijil'] + 1;
        $no_siri = "MDBG/" . $tahun_semasa . "/" . str_pad($nombor_jujukan, 5, '0', STR_PAD_LEFT);

        $tarikh_mula   = date('d/m/Y');
        $tarikh_tamat  = date('d/m/Y', strtotime('+1 year'));

        //Untuk ekstrak kolum Gred sahaja
        $raw_cidb = trim($kontraktor['gred_cidb_kewangan'] ?? '');
        $senarai_gred = [];

        if (!empty($raw_cidb)) {
            $lines = explode("\n", str_replace("\r", "", $raw_cidb));
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || stripos($line, 'GRED') !== false) continue;
                
                $cols = preg_split('/\s+/', $line);
                if (!empty($cols[0])) {
                    $senarai_gred[] = $cols[0]; // Ambil nilai kolum Gred sahaja
                }
            }
        }
        
        $gred_display = !empty($senarai_gred) ? implode(', ', $senarai_gred) : '-';
        $replacements = [
            '{{IMAGE_BASE64}}' => $image_base64,
            '{{SIRI}}'     => $no_siri,
            '{{ID}}'       => htmlspecialchars($kontraktor['no_pendaftaran'] ?? ''),
            '{{MULA}}'     => $tarikh_mula,
            '{{TAMAT}}'    => $tarikh_tamat,
            '{{SYARIKAT}}' => strtoupper(htmlspecialchars($kontraktor['nama_syarikat'] ?? '')),
            '{{ALAMAT}}'   => nl2br(htmlspecialchars($kontraktor['alamat'] ?? 'TIADA MAKLUMAT ALAMAT')),
            '{{PEGAWAI}}'  => strtoupper(htmlspecialchars($kontraktor['nama_pemilik'] ?? $kontraktor['nama_syarikat'] ?? '')),
            '{{GRED}}'     => htmlspecialchars($gred_display)
        ];

        // 5. Gantikan tag {{...}} dalam templat dengan data sebenar
        $sijil_html = str_replace(array_keys($replacements), array_values($replacements), $template);

        // 6. Simpan fail sijil HTML ke dalam folder uploads
        $filename    = time() . "_sijil_" . $profil_id . ".html";
        $target_file = $target_dir . $filename;
        file_put_contents($target_file, $sijil_html);

        // 7. Kemaskini pangkalan data: Tukar status bayaran & simpan nama fail sijil
        $conn->query("UPDATE kontraktor_profil SET status_bayaran_daftar = 'Sudah Bayar', fail_sijil = '$filename' WHERE id = '$profil_id'");

        // --- 8. PROSES HANTAR E-MEL NOTIFIKASI KEPADA KONTRAKTOR ---
        $penerima = $kontraktor['email_aktif'] ?? '';
        $nama_syarikat = $kontraktor['nama_syarikat'] ?? 'Kontraktor';

        if (!empty($penerima)) {
            $mail = new PHPMailer(true);

            try {
                // Tetapan Pelayan (SMTP Google Workspace)
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com'; 
                $mail->SMTPAuth   = true;
                $mail->Username   = 'prk.mdbg@mdbg.gov.my';       
                $mail->Password   = 'bgcd cdwt bfup uxeq'; 
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
                $mail->Port       = 465;

                // Bypass SSL untuk Localhost (XAMPP)
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );

                // Penerima & Penghantar
                $mail->setFrom('prk.mdbg@mdbg.gov.my', 'Majlis Daerah Batu Gajah');
                $mail->addAddress($penerima, $nama_syarikat);

                // Lampirkan Fail Sijil yang baru digenerate
                if (file_exists($target_file)) {
                    $mail->addAttachment($target_file, "Sijil_Pendaftaran_$no_siri.html");
                }

                // Kandungan E-mel HTML
                $mail->isHTML(true);
                $mail->Subject = "MDBG - Pengesahan Bayaran & Sijil Pendaftaran Dikeluarkan";
                $mail->Body    = "Salam Sejahtera <b>" . htmlspecialchars($nama_syarikat) . "</b>,<br><br>"
                               . "Tahniah! Bayaran pendaftaran anda telah <b>DISAHKAN</b> oleh pihak Majlis.<br><br>"
                               . "<b>Maklumat Sijil Pendaftaran:</b><br>"
                               . "• Nombor Siri Sijil: <b>" . $no_siri . "</b><br>"
                               . "• Tarikh Sah: <b>" . $tarikh_mula . " hingga " . $tarikh_tamat . "</b><br><br>"
                               . "Sijil Kelayakan Pendaftaran anda telah diterbitkan. Anda boleh menyemak dan memuat turun sijil ini melalui dashboard akaun anda atau melalui fail yang dilampirkan bersama e-mel ini.<br><br>"
                               . "Terima kasih,<br><b>Majlis Daerah Batu Gajah</b>";

                $mail->AltBody = "Salam Sejahtera " . $nama_syarikat . ",\n\nBayaran pendaftaran anda telah DISAHKAN. Sijil pendaftaran anda (No. Siri: " . $no_siri . ") telah dikeluarkan.\n\nTerima kasih,\nMajlis Daerah Batu Gajah";

                $mail->send();

            } catch (Exception $e) {
                // Rekod ralat jika hantar e-mel gagal tanpa menghentikan sistem
                error_log("E-mel sijil gagal dihantar: " . $mail->ErrorInfo);
            }
        }

        echo "<script>alert('Bayaran disahkan, Sijil No. Siri $no_siri digenerate & e-mel notifikasi telah dihantar!'); window.location.href='kejuruteraan_bayaran.php';</script>";
        exit();
    }
}

// ==========================================
// LOGIK SAHKAN SEMUA BAYARAN (BULK CONFIRMATION)
// ==========================================
if (isset($_POST['action_sahkan_semua_bayaran'])) {
    $query_semua = $conn->query("SELECT * FROM kontraktor_profil WHERE status_borang = 'Lengkap' AND (status_bayaran_daftar != 'Sudah Bayar' OR status_bayaran_daftar IS NULL) AND fail_resit IS NOT NULL AND fail_resit != ''");
    
    $bil_berjaya = 0;
    if ($query_semua && $query_semua->num_rows > 0) {
        while ($kontraktor = $query_semua->fetch_assoc()) {
            $profil_id = $kontraktor['id'];

            if (!file_exists('template_sijil.html')) {
                continue;
            }

            $path_gambar = __DIR__ . '/sijil_template.jpg'; 
            $image_base64 = '';
            if (file_exists($path_gambar)) {
                $type_gambar = pathinfo($path_gambar, PATHINFO_EXTENSION);
                $data_gambar = file_get_contents($path_gambar);
                $image_base64 = 'data:image/' . $type_gambar . ';base64,' . base64_encode($data_gambar);
            }

            $template = file_get_contents('template_sijil.html');

            $query_sijil = $conn->query("SELECT COUNT(*) AS total_sijil FROM kontraktor_profil WHERE fail_sijil IS NOT NULL AND fail_sijil != ''");
            $data_sijil  = $query_sijil->fetch_assoc();
            $nombor_jujukan = $data_sijil['total_sijil'] + 1;
            $no_siri = "MDBG/" . date('Y') . "/" . str_pad($nombor_jujukan, 5, '0', STR_PAD_LEFT);

            $tarikh_mula   = date('d/m/Y');
            $tarikh_tamat  = date('d/m/Y', strtotime('+1 year'));

            $raw_cidb = trim($kontraktor['gred_cidb_kewangan'] ?? '');
            $senarai_gred = [];

            if (!empty($raw_cidb)) {
                $lines = explode("\n", str_replace("\r", "", $raw_cidb));
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line) || stripos($line, 'GRED') !== false) continue;
                    
                    $cols = preg_split('/\s+/', $line);
                    if (!empty($cols[0])) {
                        $senarai_gred[] = $cols[0];
                    }
                }
            }
            
            $gred_display = !empty($senarai_gred) ? implode(', ', $senarai_gred) : '-';
            $replacements = [
                '{{IMAGE_BASE64}}' => $image_base64,
                '{{SIRI}}'     => $no_siri,
                '{{ID}}'       => htmlspecialchars($kontraktor['no_pendaftaran'] ?? ''),
                '{{MULA}}'     => $tarikh_mula,
                '{{TAMAT}}'    => $tarikh_tamat,
                '{{SYARIKAT}}' => strtoupper(htmlspecialchars($kontraktor['nama_syarikat'] ?? '')),
                '{{ALAMAT}}'   => nl2br(htmlspecialchars($kontraktor['alamat'] ?? 'TIADA MAKLUMAT ALAMAT')),
                '{{PEGAWAI}}'  => strtoupper(htmlspecialchars($kontraktor['nama_pemilik'] ?? $kontraktor['nama_syarikat'] ?? '')),
                '{{GRED}}'     => htmlspecialchars($gred_display)
            ];

            $sijil_html = str_replace(array_keys($replacements), array_values($replacements), $template);

            $filename    = time() . "_sijil_" . $profil_id . ".html";
            $target_file = $target_dir . $filename;
            file_put_contents($target_file, $sijil_html);

            $conn->query("UPDATE kontraktor_profil SET status_bayaran_daftar = 'Sudah Bayar', fail_sijil = '$filename' WHERE id = '$profil_id'");

            $penerima = $kontraktor['email_aktif'] ?? '';
            $nama_syarikat = $kontraktor['nama_syarikat'] ?? 'Kontraktor';

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
                    $mail->addAddress($penerima, $nama_syarikat);

                    if (file_exists($target_file)) {
                        $mail->addAttachment($target_file, "Sijil_Pendaftaran_$no_siri.html");
                    }

                    $mail->isHTML(true);
                    $mail->Subject = "MDBG - Pengesahan Bayaran & Sijil Pendaftaran Dikeluarkan";
                    $mail->Body    = "Salam Sejahtera <b>" . htmlspecialchars($nama_syarikat) . "</b>,<br><br>"
                                   . "Tahniah! Bayaran pendaftaran anda telah <b>DISAHKAN</b> oleh pihak Majlis.<br><br>"
                                   . "<b>Maklumat Sijil Pendaftaran:</b><br>"
                                   . "• Nombor Siri Sijil: <b>" . $no_siri . "</b><br>"
                                   . "• Tarikh Sah: <b>" . $tarikh_mula . " hingga " . $tarikh_tamat . "</b><br><br>"
                                   . "Sijil Kelayakan Pendaftaran anda telah diterbitkan. Anda boleh menyemak dan memuat turun sijil ini melalui dashboard akaun anda atau melalui fail yang dilampirkan bersama e-mel ini.<br><br>"
                                   . "Terima kasih,<br><b>Majlis Daerah Batu Gajah</b>";

                    $mail->AltBody = "Salam Sejahtera " . $nama_syarikat . ",\n\nBayaran pendaftaran anda telah DISAHKAN. Sijil pendaftaran anda (No. Siri: " . $no_siri . ") telah dikeluarkan.\n\nTerima kasih,\nMajlis Daerah Batu Gajah";

                    $mail->send();

                } catch (Exception $e) {
                    error_log("E-mel sijil gagal dihantar: " . $mail->ErrorInfo);
                }
            }
            $bil_berjaya++;
        }
        echo "<script>alert('Berjaya mengesahkan $bil_berjaya bayaran kontraktor!'); window.location.href='kejuruteraan_bayaran.php';</script>";
        exit();
    } else {
        echo "<script>alert('Tiada bayaran baharu untuk disahkan.'); window.location.href='kejuruteraan_bayaran.php';</script>";
        exit();
    }
}

// ==========================================
// LOGIK PROSES MUAT NAIK SIJIL (ADMIN)
// ==========================================
if (isset($_POST['action_upload_sijil'])) {
    $profil_id = mysqli_real_escape_string($conn, $_POST['profil_id']);
    
    // Validasi Status Kontraktor (Sila tukar status kontraktor sudah bayar baru boleh upload)
    $semak_kontraktor = $conn->query("SELECT status_bayaran_daftar FROM kontraktor_profil WHERE id = '$profil_id'")->fetch_assoc();
    if (!$semak_kontraktor || $semak_kontraktor['status_bayaran_daftar'] != 'Sudah Bayar') {
        echo "<script>alert('Gagal! Sila tukar status kontraktor kepada Sudah Bayar terlebih dahulu sebelum memuat naik sijil.'); window.location.href='kejuruteraan_bayaran.php';</script>";
        exit();
    }

    if (!empty($_FILES['fail_sijil']['name'])) {
        
        // Validasi Saiz Fail (Tidak boleh lebih 10MB)
        if ($_FILES['fail_sijil']['size'] > MAX_FILE_SIZE) {
            echo "<script>alert('Gagal! Saiz fail sijil melebihi had maksimum 10MB.'); window.location.href='kejuruteraan_bayaran.php';</script>";
            exit();
        }

        $filename = time() . "_sijil_" . basename($_FILES['fail_sijil']['name']);
        $target_file = $target_dir . $filename;
        if (move_uploaded_file($_FILES['fail_sijil']['tmp_name'], $target_file)) {
            // Sebaik sahaja sijil dimuat naik, automatik tukar status_bayaran_daftar kepada 'Sudah Bayar'
            $conn->query("UPDATE kontraktor_profil SET fail_sijil = '$filename', status_bayaran_daftar = 'Sudah Bayar' WHERE id = '$profil_id'");
            echo "<script>alert('Sijil pendaftaran berjaya dihantar ke dashboard kontraktor!'); window.location.href='kejuruteraan_bayaran.php';</script>";
            exit();
        } else {
            echo "<script>alert('Gagal memuat naik sijil.');</script>";
        }
    }
}

// Ambil senarai semua kontraktor yang status borangnya 'Lengkap' untuk urusan pembayaran
$senarai_kontraktor = $conn->query("SELECT * FROM kontraktor_profil WHERE status_borang = 'Lengkap' ORDER BY id DESC");

// Kira bilangan kontraktor yang LAYAK untuk disahkan secara pukal (mesti sudah ada resit & belum disahkan)
$query_layak_semua = $conn->query("SELECT COUNT(*) AS jumlah FROM kontraktor_profil WHERE status_borang = 'Lengkap' AND (status_bayaran_daftar != 'Sudah Bayar' OR status_bayaran_daftar IS NULL) AND fail_resit IS NOT NULL AND fail_resit != ''");
$bil_layak_semua = $query_layak_semua ? $query_layak_semua->fetch_assoc()['jumlah'] : 0;
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resit & Sijil | Jabatan Kejuruteraan Admin</title>
    <!-- Google Fonts Inter & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-mdbg: #8D5B4C;
            --primary-dark: #6e4438;
            --sidebar-bg: #0f172a;
            --sidebar-active: #8D5B4C;
            --bg-body: #f1f2f3;
            --sidebar-width: 280px;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
        }

        body {
            background-color: var(--bg-body);
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            color: var(--text-main);
            overflow-x: auto;
        }
        
        /* NAVBAR MODEN & EMAS/PRO */
        .mdbg-navbar {
            background: linear-gradient(135deg, #3d221a 0%, #6e4438 60%, #543228 100%);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border-bottom: 3px solid #d97706;
            color: white;
            padding: 0 28px;
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 75px;
            z-index: 1050;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .mdbg-brand {
            font-weight: 800;
            font-size: 1.15rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .mdbg-logo-navbar {
            width: 48px;
            height: 48px;
            object-fit: contain;
            background-color: #ffffff;
            padding: 4px;
            border-radius: 50%;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            transition: transform 0.2s ease;
        }
        .mdbg-logo-navbar:hover {
            transform: scale(1.05);
        }
        
        .btn-toggle-sidebar {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        .btn-toggle-sidebar:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        .btn-logout {
            background-color: #ef4444;
            color: white;
            border: none;
            font-weight: 600;
            padding: 8px 18px;
            font-size: 0.85rem;
            border-radius: 8px;
            transition: all 0.2s ease;
            text-decoration: none;
            box-shadow: 0 2px 6px rgba(239, 68, 68, 0.2);
        }
        .btn-logout:hover {
            background-color: #dc2626;
            color: white;
            transform: translateY(-1px);
        }

        /* LAYOUT & SIDEBAR */
        .wrapper {
            display: flex;
            margin-top: 75px;
            min-height: calc(100vh - 75px);
            width: 100%;
        }

        .sidebar-container {
            width: var(--sidebar-width);
            background-color: var(--sidebar-bg);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            flex-shrink: 0;
            z-index: 1010;
            display: flex;
            flex-direction: column;
            border-right: 1px solid rgba(255, 255, 255, 0.05);
            position: sticky;
            top: 75px;
            height: calc(100vh - 75px);
        }
        
        .sidebar-container.collapsed {
            margin-left: calc(-1 * var(--sidebar-width));
        }
        
        .sidebar-menu {
            padding: 24px 16px;
        }
        
        .sidebar-category-title {
            padding: 16px 12px 8px 12px;
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            font-weight: 700;
            color: #64748b;
        }
        
        .sidebar-menu .nav-link-item {
            padding: 11px 16px;
            font-weight: 600;
            color: #94a3b8;
            font-size: 0.88rem;
            border-radius: 10px;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .sidebar-menu .nav-link-item:hover {
            background-color: rgba(255, 255, 255, 0.06);
            color: #f8fafc;
            transform: translateX(3px);
        }

        .sidebar-menu .nav-link-item .chevron-icon {
            transition: transform 0.2s ease;
        }

        .sidebar-menu .nav-link-item[aria-expanded="true"] .chevron-icon {
            transform: rotate(180deg);
        }

        /* STYLES SUBMENU SIDEBAR */
        .sidebar-submenu {
            padding-left: 12px;
            margin-top: 4px;
            margin-bottom: 6px;
        }

        .sidebar-submenu .sub-link-item {
            padding: 8px 14px;
            font-weight: 500;
            color: #94a3b8;
            font-size: 0.82rem;
            border-radius: 8px;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.2s ease;
            border-left: 2px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-submenu .sub-link-item:hover {
            background-color: rgba(255, 255, 255, 0.05);
            color: #ffffff;
            border-left-color: var(--primary-mdbg);
            padding-left: 18px;
        }

        .sidebar-submenu .sub-link-item.active {
            background-color: var(--primary-mdbg) !important;
            color: #ffffff !important;
            border-left-color: #ffc107 !important;
            font-weight: 700;
        }

        .sidebar-submenu .sub-link-item.active i {
            color: #ffc107 !important;
        }

        .sidebar-submenu .sub-link-item i {
            font-size: 0.85rem;
            width: 22px;
            color: #64748b;
        }

        .main-content-container {
            flex-grow: 1;
            padding: 30px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background-color: var(--bg-body);
            box-sizing: border-box;
            width: calc(100% - var(--sidebar-width));
            overflow-x: visible; 
        }

        .sidebar-container.collapsed + .main-content-container {
            width: 100%;
        }

        .status-card-container {
            background: #ffffff;
            border-radius: 18px;
            border: 3px solid var(--border-color);
            padding: 28px;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.03);
        }

        .status-header {
            font-size: 1.15rem;
            font-weight: 800;
            color: #0f172a;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 12px;
        }

        .table-responsive-custom {
            display: block;
            width: 100% !important;
            overflow-x: auto !important;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            background: #ffffff;
        }

        .table-mdbg {
            margin-bottom: 0 !important;
            width: 100%;
            border-collapse: collapse;
            border: 2px solid var(--border-color);
        }

        .table-mdbg th {
            padding: 16px 20px !important;
            font-size: 0.78rem !important;
            font-weight: 700 !important;
            border: 2px solid var(--border-color) !important;
            text-transform: uppercase !important;
            color: #475569 !important;
            white-space: nowrap !important; 
            background-color: #f8fafc;
            border-bottom: 2px solid var(--border-color);
        }

        .table-mdbg tbody tr {
            border-bottom: 1px solid var(--border-color) !important;
            transition: background-color 0.15s ease;
        }

        .table-mdbg tbody tr:hover {
            background-color: #f8fafc;
        }

        .table-mdbg td {
            padding: 16px 20px !important;
            font-size: 0.90rem !important;
            color: #334155;
            border: 2px solid var(--border-color) !important;
            vertical-align: middle;
        }

        /* GAYA JADUAL DALAMAN GRED CIDB / KEWANGAN */
        .table-mdbg td table {
            width: auto !important;
            margin: 0 auto !important;
            font-size: 0.65rem !important;
        }
        .table-mdbg td table th, 
        .table-mdbg td table td {
            padding: 2px 6px !important;
            font-size: 0.65rem !important;
            white-space: nowrap !important;
        }

        .w-fit {
            width: fit-content;
        }

        @media (max-width: 768px) {
            .main-content-container { padding: 18px; }
        }
    </style>
</head>
<body>

    <!-- NAVBAR ADMIN -->
    <div class="mdbg-navbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn-toggle-sidebar" id="sidebarToggle" type="button" title="Buka/Tutup Sidebar">
                <i class="fa-solid fa-bars fs-5"></i>
            </button>
            <img src="logo_mdbg.png" alt="Logo MDBG" class="mdbg-logo-navbar">
            <div>
                <div class="mdbg-brand">JABATAN KEJURUTERAAN ADMIN</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <a href="logout.php" class="btn-logout">
                <i class="fa-solid fa-right-from-bracket me-1"></i> Log Keluar
            </a>
        </div>
    </div>

    <!-- WRAPPER CONTENT -->
    <div class="wrapper">
        
        <!-- SIDEBAR ADMIN -->
        <div class="sidebar-container" id="sidebarWrapper">
            <div class="sidebar-menu">
                <div class="sidebar-category-title">Menu Utama</div>
                 <a href="kejuruteraan.php" class="nav-link-item">
                    <i class="fa-solid fa-chart-pie me-2"></i> Dashboard
                </a>
                <a href="data_analysis.php" class="nav-link-item">
                    <i class="fa-solid fa-chart-line me-2"></i> Data Analysis
                </a>
                <!-- MENU LAPORAN -->
                <a href="laporan.php" class="nav-link-item">
                    <i class="fa-solid fa-file-contract me-2"></i> Laporan
                </a>
                <div class="sidebar-category-title">Urusan Semakan</div>
                
                <!-- FASA 1: DAFTAR SYARIKAT -->
                <a class="nav-link-item d-flex align-items-center justify-content-between" 
                   data-bs-toggle="collapse" 
                   href="#menuFasa1" 
                   role="button" 
                   aria-expanded="true" 
                   aria-controls="menuFasa1">
                    <span>
                        <i class="fa-solid fa-folder-open me-2 text-warning"></i> Fasa 1
                    </span>
                    <i class="fa-solid fa-chevron-down small chevron-icon"></i>
                </a>

                <!-- SUB-MENU FASA 1 -->
                <div class="collapse show sidebar-submenu" id="menuFasa1">
                    <a href="kejuruteraan_senarai_kontraktor.php" class="sub-link-item">
                        <i class="fa-solid fa-file-signature me-2"></i> Senarai Kontraktor
                    </a>
                    <a href="kejuruteraan_senarai_borang.php" class="sub-link-item">
                        <i class="fa-solid fa-check-to-slot me-2"></i> Tarikh Sah Borang
                    </a>
                    <a href="kejuruteraan_bayaran.php" class="sub-link-item active">
                        <i class="fa-solid fa-coins me-2"></i> Resit dan Sijil
                    </a>
                </div>

                <!-- FASA 2: UNDIAN / PENILAIAN -->
                <a class="nav-link-item d-flex align-items-center justify-content-between mt-1" 
                   data-bs-toggle="collapse" 
                   href="#menuFasa2" 
                   role="button" 
                   aria-expanded="false" 
                   aria-controls="menuFasa2">
                    <span>
                        <i class="fa-solid fa-folder-open me-2 text-warning"></i> Fasa 2
                    </span>
                    <i class="fa-solid fa-chevron-down small chevron-icon"></i>
                </a>

                <!-- SUB-MENU FASA 2 -->
                <div class="collapse sidebar-submenu" id="menuFasa2">
                    <a href="senarai_kontraktor_undi.php" class="sub-link-item">
                        <i class="fa-solid fa-box-archive me-2"></i> Senarai Undian
                    </a>
                    <a href="kategori_undi.php" class="sub-link-item">
                        <i class="fa-solid fa-tags me-2"></i> Kategori Undi
                    </a>
                    <a href="keputusan_pemilihan_kontraktor.php" class="sub-link-item">
                        <i class="fa-solid fa-tags me-2"></i> Keputusan Kontraktor
                    </a>
                    <a href="senarai_keputusan_kontraktor.php" class="sub-link-item">
                        <i class="fa-solid fa-tags me-2"></i> Senarai Keputusan
                    </a>
                </div>

            </div>
        </div>

        <!-- MAIN SITE CONTENT -->
        <div class="main-content-container" id="contentWrapper">
            <div class="container-fluid p-0">
                
                <div class="status-card-container mb-4">
                    <div class="d-flex justify-content-between align-items-center status-header mb-3">
                        <div class="m-0"><i class="fa-solid fa-receipt me-2 text-warning"></i>Pengurusan Resit & Sijil Pendaftaran</div>
                        <form action="" method="POST" class="m-0" onsubmit="return confirm('Adakah anda pasti ingin mengesahkan SEMUA bayaran kontraktor yang belum disahkan?');">
                            <button type="submit" name="action_sahkan_semua_bayaran" class="btn btn-success fw-bold btn-sm px-3 shadow-sm" style="border-radius: 8px;" <?= $bil_layak_semua == 0 ? 'disabled title="Tiada kontraktor dengan resit bayaran yang belum disahkan"' : ''; ?>>
                                <i class="fa-solid fa-check-double me-1"></i> Sahkan Semua Bayaran
                            </button>
                        </form>
                    </div>
                    <p class="text-muted small mb-4">Uruskan penghantaran bil pendaftaran kontraktor, semak muat turun resit bayaran yang dihantar, serta keluarkan Sijil Kelayakan Pembekal Rasmi.</p>
                    
                    <div class="table-responsive-custom">
                        <table class="table table-mdbg align-middle">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 5%;">BIL.</th>
                                    <th style="width: 25%;">MAKLUMAT SYARIKAT</th>
                                    <th class="text-center" style="width: 8%;">GRED</th>
                                    <th style="width: 17%;">1. TINDAKAN BIL</th>
                                    <th style="width: 15%;">2. RESIT KONTRAKTOR</th>
                                    <th style="width: 15%;">3. TINDAKAN SIJIL</th>
                                    <th style="width: 15%;">4. PENGESAHAN</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1;
                                if ($senarai_kontraktor->num_rows > 0):
                                    while($row = $senarai_kontraktor->fetch_assoc()):
                                ?>
                                <tr>
                                    <td class="text-center fw-semibold text-muted"><?= $no++; ?>.</td>
                                    <td>
                                        <strong class="d-block text-dark text-uppercase"><?= htmlspecialchars($row['nama_syarikat']); ?></strong>
                                        <span class="text-muted small d-block mt-1"><i class="fa-solid fa-id-card me-1"></i> <?= htmlspecialchars($row['no_pendaftaran']); ?></span>
                                        <span class="text-muted small d-block"><i class="fa-solid fa-envelope me-1"></i> <?= htmlspecialchars($row['email_aktif']); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php 
                                            $gred_raw = trim($row['gred_cidb_kewangan'] ?? '');
                                            // Semak jika data berstruktur berbilang baris (Jadual Database)
                                            if (strpos($gred_raw, "\n") !== false || (strpos($gred_raw, 'GRED') !== false && strpos($gred_raw, 'KATEGORI') !== false)) {
                                                $lines = array_filter(array_map('trim', explode("\n", str_replace("\r", "", $gred_raw))));
                                                echo '<table class="table table-sm table-bordered m-0 text-center align-middle d-inline-table" style="font-size:0.65rem; width: auto; max-width: 100%; background:#ffffff; border-color:#cbd5e1; margin: 0 auto !important;">';
                                                
                                                $is_first = true;
                                                foreach ($lines as $line) {
                                                    $cols = preg_split('/\s+/', $line);
                                                    if (count($cols) >= 3) {
                                                        if ($is_first && (strcasecmp($cols[0], 'GRED') == 0 || strcasecmp($cols[1], 'KATEGORI') == 0)) {
                                                            echo '<thead style="background-color:#64748b; color:#ffffff;"><tr>';
                                                            echo '<th style="padding:2px 6px;">' . htmlspecialchars($cols[0]) . '</th>';
                                                            echo '<th style="padding:2px 6px;">' . htmlspecialchars($cols[1]) . '</th>';
                                                            echo '<th style="padding:2px 6px;">' . htmlspecialchars(implode(' ', array_slice($cols, 2))) . '</th>';
                                                            echo '</tr></thead><tbody>';
                                                            $is_first = false;
                                                        } else {
                                                            if ($is_first) {
                                                                echo '<thead style="background-color:#64748b; color:#ffffff;"><tr><th style="padding:2px 6px;">GRED</th><th style="padding:2px 6px;">KATEGORI</th><th style="padding:2px 6px;">PENGKHUSUSAN</th></tr></thead><tbody>';
                                                                $is_first = false;
                                                            }
                                                            echo '<tr>';
                                                            echo '<td class="fw-bold font-monospace" style="padding:2px 6px;">' . htmlspecialchars($cols[0]) . '</td>';
                                                            echo '<td class="font-monospace" style="padding:2px 6px;">' . htmlspecialchars($cols[1]) . '</td>';
                                                            echo '<td class="font-monospace" style="padding:2px 6px;">' . htmlspecialchars(implode(' ', array_slice($cols, 2))) . '</td>';
                                                            echo '</tr>';
                                                        }
                                                    } elseif (count($cols) == 2) {
                                                        if ($is_first) {
                                                            echo '<thead style="background-color:#64748b; color:#ffffff;"><tr><th style="padding:2px 6px;">GRED</th><th style="padding:2px 6px;">KATEGORI</th></tr></thead><tbody>';
                                                            $is_first = false;
                                                        }
                                                        echo '<tr><td class="font-monospace" style="padding:2px 6px;">' . htmlspecialchars($cols[0]) . '</td><td class="font-monospace" style="padding:2px 6px;">' . htmlspecialchars($cols[1]) . '</td></tr>';
                                                    } elseif (!empty($cols[0])) {
                                                        if ($is_first) {
                                                            echo '<tbody>';
                                                            $is_first = false;
                                                        }
                                                        echo '<tr><td colspan="3" class="font-monospace" style="padding:2px 6px;">' . htmlspecialchars($cols[0]) . '</td></tr>';
                                                    }
                                                }
                                                if (!$is_first) {
                                                    echo '</tbody>';
                                                }
                                                echo '</table>';
                                            } else {
                                                echo '<span class="badge border font-monospace" style="font-size: 0.72rem; font-weight: 700; letter-spacing: 0.5px; padding: 4px 8px; background-color: #f8fafc; color: #475569; border-color: #cbd5e1 !important; border-radius: 6px;">' . htmlspecialchars($gred_raw) . '</span>';
                                            }
                                        ?>
                                    </td>
                                    
                                    <!-- 1. UPLOAD BIL -->
                                    <td>
                                        <?php if(empty($row['fail_bil'])): ?>
                                            <form action="" method="POST" enctype="multipart/form-data" class="d-flex align-items-center gap-1 m-0">
                                                <input type="hidden" name="profil_id" value="<?= $row['id']; ?>">
                                                <input type="hidden" name="action_upload_bil" value="1">
                                                <input type="file" name="fail_bil" class="form-control form-control-sm" accept="application/pdf" required style="max-width: 140px;">
                                                <button type="submit" class="btn btn-sm btn-warning text-dark text-nowrap rounded-2" title="Hantar Bil"><i class="fa-solid fa-paper-plane"></i></button>
                                            </form>
                                        <?php else: ?>
                                            <div class="d-flex flex-column gap-1">
                                                <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2 small w-fit rounded-2"><i class="fa-solid fa-circle-check"></i> Bil Dihantar</span>
                                                <a href="./uploads/<?= $row['fail_bil']; ?>" target="_blank" class="text-primary small fw-semibold text-decoration-none mt-1"><i class="fa-solid fa-file-pdf me-1"></i> Lihat Fail Bil</a>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- 2. DOWNLOAD RESIT -->
                                    <td>
                                        <?php if(!empty($row['fail_resit'])): ?>
                                            <div class="d-flex flex-column gap-1">
                                                <span class="badge bg-info-subtle text-dark border border-info-subtle py-1 px-2 small w-fit rounded-2"><i class="fa-solid fa-money-bill-wave"></i> Resit Masuk</span>
                                                <a href="./uploads/<?= $row['fail_resit']; ?>" target="_blank" class="btn btn-sm btn-outline-success fw-bold py-1 px-2 mt-1 align-self-start rounded-2" style="font-size: 0.75rem;"><i class="fa-solid fa-download me-1"></i> Muat Turun</a>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small fst-italic"><i class="fa-solid fa-spinner fa-spin me-1"></i> Menunggu Bayaran</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- 3. UPLOAD SIJIL -->
                                    <td>
                                        <?php if(empty($row['fail_sijil'])): ?>
                                            <form action="" method="POST" enctype="multipart/form-data" class="d-flex align-items-center gap-1 m-0">
                                                <input type="hidden" name="profil_id" value="<?= $row['id']; ?>">
                                                <input type="hidden" name="action_upload_sijil" value="1">
                                                <input type="file" name="fail_sijil" class="form-control form-control-sm" accept="application/pdf" required style="max-width: 140px;" <?= $row['status_bayaran_daftar'] != 'Sudah Bayar' ? 'disabled title="Sila tukar status kontraktor kepada Sudah Bayar terlebih dahulu"' : ''; ?>>
                                                <button type="submit" class="btn btn-sm btn-dark text-nowrap rounded-2" title="Hantar Sijil" <?= $row['status_bayaran_daftar'] != 'Sudah Bayar' ? 'disabled' : ''; ?>><i class="fa-solid fa-upload"></i></button>
                                            </form>
                                            <?php if($row['status_bayaran_daftar'] != 'Sudah Bayar'): ?>
                                                <span class="text-danger d-block mt-1" style="font-size: 0.7rem;">*Sila Tukar Status Kepada Sudah Bayar</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="d-flex flex-column gap-1">
                                                <span class="badge bg-dark text-white border border-dark py-1 px-2 small w-fit rounded-2"><i class="fa-solid fa-award text-warning"></i> Sijil Aktif</span>
                                                <a href="./uploads/<?= $row['fail_sijil']; ?>" target="_blank" class="text-dark small fw-semibold text-decoration-none mt-1"><i class="fa-solid fa-file-invoice me-1"></i> Lihat Sijil Kelayakan</a>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <!-- 4. SAHKAN BAYARAN -->
                                    <td>
                                        <?php if($row['status_bayaran_daftar'] == 'Sudah Bayar'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2 small w-fit rounded-2">
                                                <i class="fa-solid fa-circle-check me-1"></i> Sudah Disahkan
                                            </span>
                                        <?php else: ?>
                                            <form action="" method="POST" class="m-0" onsubmit="return confirm('Adakah anda pasti ingin mengesahkan bayaran bagi kontraktor ini?');">
                                                <input type="hidden" name="profil_id" value="<?= $row['id']; ?>">
                                                <button type="submit" name="action_sahkan_bayaran" class="btn btn-sm btn-success fw-semibold text-nowrap rounded-2" style="font-size: 0.78rem;" <?= empty($row['fail_resit']) ? 'disabled title="Kontraktor belum muat naik resit bayaran"' : ''; ?>>
                                                    <i class="fa-solid fa-check me-1"></i> Sahkan Bayaran
                                                </button>
                                            </form>
                                            <?php if(empty($row['fail_resit'])): ?>
                                                <span class="text-danger d-block mt-1" style="font-size: 0.7rem;">*Menunggu resit kontraktor</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>                                   
                                </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Tiada kontraktor berstatus 'Lengkap' yang ditemui buat masa ini.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebarWrapper');
            if (sidebar) sidebar.classList.toggle('collapsed');
        });
    </script>
</body>
</html>