<?php
    session_start();

    // 1. Sekat daripada menyimpan cache halaman ini
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");

    if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kontraktor') { 
        header("Location: index.php"); 
        exit(); 
    }
    include 'db.php';

    $user_id = $_SESSION['user_id'];

    // Mengambil data jenis akaun & nama penuh pengguna daripada jadual users
    $user_data = $conn->query("SELECT jenis_akaun, nama_penuh FROM users WHERE id='$user_id'")->fetch_assoc();

    // Mengambil data profil kontraktor terkini
    $profil = $conn->query("SELECT * FROM kontraktor_profil WHERE user_id='$user_id'")->fetch_assoc();

    // Logik menentukan Nama Paparan di Header (Individu vs Syarikat) - UPPERCASE ONLY
    $nama_paparan = 'KONTRAKTOR';
    if (!empty($user_data)) {
        if ($user_data['jenis_akaun'] == 'Individu') {
            $nama_paparan = !empty($user_data['nama_penuh']) ? $user_data['nama_penuh'] : 'MUHAMMAD AIFA AMMAR BIN ZULKIFLI';
        } else {
            // Utamakan nama_syarikat, jika tiada utamakan nama_penuh
            if (!empty($profil['nama_syarikat'])) {
                $nama_paparan = $profil['nama_syarikat'];
            } elseif (!empty($user_data['nama_penuh'])) {
                $nama_paparan = $user_data['nama_penuh'];
            } else {
                $nama_paparan = 'KONTRAKTOR / SYARIKAT BERDAFTAR';
            }
        }
    }

    // Tukar pembolehubah nama paparan kepada huruf besar sepenuhnya
    $nama_paparan = strtoupper($nama_paparan);

    // Tetapkan pemalar had saiz fail (10MB)
    define('MAX_FILE_SIZE', 10485760); 

    // ==========================================
    // LOGIK AUTO-FIX APABILA ADMIN LUKA MASUKKAN TARIKH (HANYA AKTIF APABILA LENGKAP & SUDAH BAYAR)
    // ==========================================
    if (
        !empty($profil) && 
        isset($profil['status_borang']) && $profil['status_borang'] == 'Lengkap' && 
        isset($profil['status_bayaran_daftar']) && $profil['status_bayaran_daftar'] == 'Sudah Bayar'
    ) {
        // Semak jika tarikh masih kosong atau '0000-00-00', baru set tarikh baharu untuk PERTAMA KALI SAHAJA
        if (empty($profil['tarikh_mula_aktif']) || $profil['tarikh_mula_aktif'] == '0000-00-00' || empty($profil['tarikh_tamat_aktif']) || $profil['tarikh_tamat_aktif'] == '0000-00-00') {
            // Tetapkan tarikh hari ini sebagai tarikh mula aktif pertama kali
            $tarikh_mula = date('Y-m-d');
            // Tetapkan tarikh tamat sah (1 tahun dari tarikh mula)
            $tarikh_tamat = date('Y-m-d', strtotime('+1 year'));
            
            // Kemaskini database secara automatik
            $conn->query("UPDATE kontraktor_profil SET tarikh_mula_aktif='$tarikh_mula', tarikh_tamat_aktif='$tarikh_tamat' WHERE user_id='$user_id'");
            
            // Refresh data profil supaya tarikh baru terus dipaparkan di skrin
            $profil = $conn->query("SELECT * FROM kontraktor_profil WHERE user_id='$user_id'")->fetch_assoc();
        }
    } 
    // ==========================================

    // TAMBAHAN: Semak sama ada kontraktor sudah mendaftar kerja undi dalam jadual kontraktor_undi
    $nama_syarikat_profil = isset($profil['nama_syarikat']) ? $profil['nama_syarikat'] : '';
    $semak_undi = !empty($nama_syarikat_profil) ? $conn->query("SELECT * FROM kontraktor_undi WHERE nama_syarikat = '" . mysqli_real_escape_string($conn, $nama_syarikat_profil) . "'") : null;
    $sudah_daftar_undi = ($semak_undi && $semak_undi->num_rows > 0);
    $data_undi = $sudah_daftar_undi ? $semak_undi->fetch_assoc() : null;

    // ==========================================
    // LOGIK SEMAKAN STATUS AKTIF & TAMAT SAH (1 TAHUN)
    // ==========================================
    $today = date('Y-m-d');
    $is_expired = false;
    $is_status_aktif = false;

    if (
        !empty($profil) && 
        !empty($profil['tarikh_mula_aktif']) && 
        !empty($profil['tarikh_tamat_aktif']) && 
        $profil['tarikh_mula_aktif'] != '0000-00-00' && 
        $profil['tarikh_tamat_aktif'] != '0000-00-00' &&
        isset($profil['status_bayaran_daftar']) && $profil['status_bayaran_daftar'] == 'Sudah Bayar'
    ) {
        if ($today >= $profil['tarikh_mula_aktif'] && $today <= $profil['tarikh_tamat_aktif']) {
            $is_status_aktif = true;
        } elseif ($today > $profil['tarikh_tamat_aktif']) {
            $is_expired = true;
            $is_status_aktif = false;
        }
    }

    // ==========================================
    // LOGIK PROSES MUAT NAIK RESIT BAYARAN (KONTRAKTOR)
    // ==========================================
    if (isset($_POST['action_upload_resit'])) {
        $target_dir = "uploads/";
        if (!empty($_FILES['fail_resit']['name'])) {
            
            // Validasi Saiz Fail Resit (Maksimum 10MB)
            if ($_FILES['fail_resit']['size'] > MAX_FILE_SIZE) {
                echo "<script>alert('Gagal! Saiz fail resit melebihi had maksimum 10MB.'); window.location.href='kontraktor.php';</script>";
                exit();
            }

            // TAMBAHAN VALIDATION: Format Fail Resit di Backend (Hanya PDF)
            $ext_resit = strtolower(pathinfo($_FILES['fail_resit']['name'], PATHINFO_EXTENSION));
            if ($ext_resit !== 'pdf') {
                echo "<script>alert('Gagal! Fail resit mestilah dalam format PDF sahaja.'); window.location.href='kontraktor.php';</script>";
                exit();
            }

            $filename = "RESIT_user_" . $user_id . ".pdf";
            $target_file = $target_dir . $filename;
            if (move_uploaded_file($_FILES['fail_resit']['tmp_name'], $target_file)) {
                $conn->query("UPDATE kontraktor_profil SET fail_resit = '$filename' WHERE user_id = '$user_id'");
                echo "<script>alert('Resit pembayaran berjaya dimuat naik!'); window.location.href='kontraktor.php';</script>";
                exit();
            } else {
                echo "<script>alert('Gagal memuat naik resit.');</script>";
            }
        }
    }

    // ==========================================
    // LOGIK PROSES MUAT NAIK RESIT BAYARAN KERJA UNDI (FASA 2)
    // ==========================================
    if (isset($_POST['action_upload_resit_undi'])) {
        $target_dir = "uploads/";
        if (!empty($_FILES['fail_resit_undi']['name'])) {
            
            if ($_FILES['fail_resit_undi']['size'] > MAX_FILE_SIZE) {
                echo "<script>alert('Gagal! Saiz fail resit melebihi had maksimum 10MB.'); window.location.href='kontraktor.php';</script>";
                exit();
            }

            $ext_resit = strtolower(pathinfo($_FILES['fail_resit_undi']['name'], PATHINFO_EXTENSION));
            if ($ext_resit !== 'pdf') {
                echo "<script>alert('Gagal! Fail resit mestilah dalam format PDF sahaja.'); window.location.href='kontraktor.php';</script>";
                exit();
            }

            $filename = "RESIT_UNDI_user_" . $user_id . ".pdf";
            $target_file = $target_dir . $filename;
            if (move_uploaded_file($_FILES['fail_resit_undi']['tmp_name'], $target_file)) {
                if ($data_undi && isset($data_undi['id'])) {
                    $undi_id = $data_undi['id'];
                    $conn->query("UPDATE kontraktor_undi SET fail_resit_undi = '$filename' WHERE id = '$undi_id'");
                }
                echo "<script>alert('Resit pembayaran kerja undi berjaya dimuat naik!'); window.location.href='kontraktor.php';</script>";
                exit();
            } else {
                echo "<script>alert('Gagal memuat naik resit kerja undi.');</script>";
            }
        }
    }

        // ==========================================
        // LOGIK PERMOHONAN KEMASKINI OLEH KONTRAKTOR (BERASINGAN)
        // ==========================================
        if (isset($_POST['action_minta_kemaskini'])) {
            $sebab_kemaskini = mysqli_real_escape_string($conn, trim($_POST['sebab_kemaskini'] ?? ''));
            
            $conn->query("UPDATE kontraktor_profil SET status_minta_kemaskini = 'Pending', sebab_kemaskini = '$sebab_kemaskini' WHERE user_id = '$user_id'");
            
            echo "<script>alert('Permohonan kemaskini telah dihantar kepada Admin untuk kelulusan.'); window.location.href='kontraktor.php';</script>";
            exit();
        }

    // ==========================================
    // LOGIK PROSES KEMASKINI PROFIL KONTRAKTOR
    // ==========================================
    if (isset($_POST['action_kemaskini_profil'])) {
        $nama_syarikat = mysqli_real_escape_string($conn, strtoupper(trim($_POST['nama_syarikat'])));
        $alamat = mysqli_real_escape_string($conn, strtoupper(trim($_POST['alamat'] ?? ''))); // TAMBAHAN: ALAMAT
        $no_pendaftaran = mysqli_real_escape_string($conn, strtoupper(trim($_POST['no_pendaftaran'])));
        $gred_cidb_kewangan = mysqli_real_escape_string($conn, trim($_POST['gred_cidb_kewangan']));
        $email_aktif = mysqli_real_escape_string($conn, trim($_POST['email_aktif']));
        $no_telefon_syarikat = mysqli_real_escape_string($conn, trim($_POST['no_telefon_syarikat']));
        $no_telefon_pengurus = mysqli_real_escape_string($conn, trim($_POST['no_telefon_pengurus']));

        // ------------------------------------------
        // TAMBAHAN 3: REGEX VALIDATION INPUT TEKS (BACKEND)
        // ------------------------------------------
        // 1. Validasi Format E-mel
        if (!filter_var($email_aktif, FILTER_VALIDATE_EMAIL)) {
            echo "<script>alert('Gagal! Format e-mel yang dimasukkan tidak sah.'); window.history.back();</script>";
            exit();
        }

        // 2. Validasi No. Telefon Syarikat (Hanya Digit Nombor)
        if (!preg_match('/^[0-9]+$/', $no_telefon_syarikat)) {
            echo "<script>alert('Gagal! No. Telefon Syarikat hendaklah mengandungi nombor sahaja.'); window.history.back();</script>";
            exit();
        }

        // 3. Validasi No. Telefon Pengurus (Hanya Digit Nombor)
        if (!preg_match('/^[0-9]+$/', $no_telefon_pengurus)) {
            echo "<script>alert('Gagal! No. Telefon Pengurus hendaklah mengandungi nombor sahaja.'); window.history.back();</script>";
            exit();
        }

        // 4. Validasi No. Pendaftaran SSM (10 digit/aksara, no space, uppercase, no symbol)
        if (!preg_match('/^[A-Z0-9]{10}$/', $no_pendaftaran)) {
            echo "<script>alert('Gagal! No. Pendaftaran (SSM) mestilah tepat 10 digit/aksara alfanumerik tanpa ruang atau simbol.'); window.history.back();</script>";
            exit();
        }

        // ------------------------------------------
        // TAMBAHAN 1: VALIDATION LOGIC TARIKH (BACKEND)
        // ------------------------------------------
        $tarikh_fields = [
            'SSM' => ['tarikh_mula_ssm', 'tarikh_tamat_ssm'],
            'TCC' => ['tarikh_mula_tcc', 'tarikh_tamat_tcc'],
            'PKK' => ['tarikh_mula_pkk', 'tarikh_tamat_pkk'],
            'CIDB Perakuan' => ['tarikh_mula_cidb_perakuan', 'tarikh_tamat_cidb_perakuan'],
            'CIDB Perolehan' => ['tarikh_mula_cidb_perolehan', 'tarikh_tamat_cidb_perolehan']
        ];

        foreach ($tarikh_fields as $sijil => $keys) {
            $mula = $_POST[$keys[0]] ?? '';
            $tamat = $_POST[$keys[1]] ?? '';
            
            if (!empty($mula) && !empty($tamat)) {
                if (strtotime($tamat) < strtotime($mula)) {
                    echo "<script>alert('Gagal! Tarikh Tamat bagi $sijil tidak boleh lebih awal daripada Tarikh Mula.'); window.history.back();</script>";
                    exit();
                }
            }
        }

        // Menangkap input tarikh baru yang dikemaskini
        $tarikh_mula_ssm = mysqli_real_escape_string($conn, $_POST['tarikh_mula_ssm']);
        $tarikh_tamat_ssm = mysqli_real_escape_string($conn, $_POST['tarikh_tamat_ssm']);
        $tarikh_mula_tcc = mysqli_real_escape_string($conn, $_POST['tarikh_mula_tcc']);
        $tarikh_tamat_tcc = mysqli_real_escape_string($conn, $_POST['tarikh_tamat_tcc']);
        $tarikh_mula_pkk = mysqli_real_escape_string($conn, $_POST['tarikh_mula_pkk']);
        $tarikh_tamat_pkk = mysqli_real_escape_string($conn, $_POST['tarikh_tamat_pkk']);
        $tarikh_mula_cidb_perakuan = mysqli_real_escape_string($conn, $_POST['tarikh_mula_cidb_perakuan']);
        $tarikh_tamat_cidb_perakuan = mysqli_real_escape_string($conn, $_POST['tarikh_tamat_cidb_perakuan']);
        $tarikh_mula_cidb_perolehan = mysqli_real_escape_string($conn, $_POST['tarikh_mula_cidb_perolehan']);
        $tarikh_tamat_cidb_perolehan = mysqli_real_escape_string($conn, $_POST['tarikh_tamat_cidb_perolehan']);

        // Senarai fail untuk dimuat naik jika ada perubahan
        $files_to_update = [];
        $target_dir = "uploads/";
        
        $file_fields = [
            'fail_ssm', 'fail_pkk', 
            'fail_cidb_perakuan', 'fail_cidb_perolehan', 'fail_mof', 'fail_tcc'
        ];

        foreach ($file_fields as $field) {
            if (!empty($_FILES[$field]['name'])) {
                
                // VALIDATION JENIS / EXTENSION FAIL (BACKEND)
                $file_ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
                if ($file_ext !== 'pdf') {
                    $nama_fail_asal = strtoupper(str_replace('fail_', '', $field));
                    echo "<script>alert('Gagal! Fail $nama_fail_asal mestilah dalam format PDF sahaja.'); window.history.back();</script>";
                    exit();
                }

                // Validasi Saiz Fail Dokumen (Maksimum 10MB)
                if ($_FILES[$field]['size'] > MAX_FILE_SIZE) {
                    $nama_fail_asal = strtoupper(str_replace('fail_', '', $field));
                    echo "<script>alert('Gagal! Saiz fail bagi dokumen $nama_fail_asal melebihi had maksimum 10MB.'); window.location.href='kontraktor.php';</script>";
                    exit();
                }

                // Penamaan tetap mengikut user_id untuk overwrite fail sedia ada
                $prefix = strtoupper(str_replace('fail_', '', $field));
                $filename = $prefix . "_user_" . $user_id . "_" . time() . ".pdf";
                $target_file = $target_dir . $filename;
                
                if (move_uploaded_file($_FILES[$field]['tmp_name'], $target_file)) {
                    $files_to_update[$field] = $filename;
                }
            }
        }

        // ==========================================
        // LOGIK PERMOHONAN KEMASKINI OLEH KONTRAKTOR
        // ==========================================
        if (isset($_POST['action_minta_kemaskini'])) {
            $sebab_kemaskini = mysqli_real_escape_string($conn, trim($_POST['sebab_kemaskini']));
            
            $conn->query("UPDATE kontraktor_profil SET status_minta_kemaskini = 'Pending', sebab_kemaskini = '$sebab_kemaskini' WHERE user_id = '$user_id'");
            
            echo "<script>alert('Permohonan kemaskini telah dihantar kepada Admin untuk kelulusan.'); window.location.href='kontraktor.php';</script>";
            exit();
        }

        // ... (kod validation sedia ada kekal sama) ...

       $query_update = "UPDATE kontraktor_profil SET 
            nama_syarikat = '$nama_syarikat',
            alamat = '$alamat',
            no_pendaftaran = '$no_pendaftaran',
            gred_cidb_kewangan = '$gred_cidb_kewangan',
            email_aktif = '$email_aktif',
            no_telefon_syarikat = '$no_telefon_syarikat',
            no_telefon_pengurus = '$no_telefon_pengurus',
            tarikh_mula_ssm = '$tarikh_mula_ssm',
            tarikh_tamat_ssm = '$tarikh_tamat_ssm',
            tarikh_mula_tcc = '$tarikh_mula_tcc',
            tarikh_tamat_tcc = '$tarikh_tamat_tcc',
            tarikh_mula_pkk = '$tarikh_mula_pkk',
            tarikh_tamat_pkk = '$tarikh_tamat_pkk',
            tarikh_mula_cidb_perakuan = '$tarikh_mula_cidb_perakuan',
            tarikh_tamat_cidb_perakuan = '$tarikh_tamat_cidb_perakuan',
            tarikh_mula_cidb_perolehan = '$tarikh_mula_cidb_perolehan',
            tarikh_tamat_cidb_perolehan = '$tarikh_tamat_cidb_perolehan',
            status_minta_kemaskini = NULL
            WHERE user_id = '$user_id'";
            
        if ($conn->query($query_update)) {
            foreach ($files_to_update as $col => $val) {
                $conn->query("UPDATE kontraktor_profil SET $col = '$val' WHERE user_id = '$user_id'");
            }
            
            echo "<script>alert('Profil berjaya dikemaskini!'); window.location.href='kontraktor.php';</script>";
            exit();
        } else {
            echo "<script>alert('Gagal mengemaskini data.');</script>";
        }
    }

    // Persediaan pembolehubah status untuk kegunaan sistem notifikasi di bahagian Header
    $status_dokumen_notifikasi = $profil['status_borang'] ?? '';
    $status_bayaran_notifikasi = $profil['status_bayaran_daftar'] ?? '';
    $mempunyai_notifikasi_aktif = ($status_dokumen_notifikasi == 'Lengkap' || $status_bayaran_notifikasi == 'Sudah Bayar' || $status_dokumen_notifikasi == 'Pending');

    // TAMBAHAN LOGIK BAHARU: Semak mod Paparan Clean / Semakan untuk Sesi Individu
    $jenis_akaun = $user_data['jenis_akaun'] ?? 'Syarikat';
    $show_details_individu = false;

    // Menyimpan atau menyemak status 'semak' dalam Session supaya kekal walaupun berpindah page
    if (isset($_GET['semak'])) {
        if ($_GET['semak'] == '1') {
            $_SESSION['semak_individu'] = true;
        } else {
            unset($_SESSION['semak_individu']);
        }
    }

    if ($jenis_akaun == 'Individu' && isset($_SESSION['semak_individu']) && $_SESSION['semak_individu'] === true) {
        $show_details_individu = true;
    }

    ?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Kontraktor | MDBG</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-mdbg: #8D5B4C;
            --primary-dark: #6e4438;
            --primary-light: #fdfaf9;
            --sidebar-width: 280px;

            /* Peranan warna am — dipakai di seluruh fail */
            --bg-page: #ebedf1;
            --bg-card: #ffffff;
            --bg-sidebar: #ffffff;
            --bg-subtle: #f8fafc;      /* ganti #f8fafc / #f1f5f9 */
            --text-dark: #1e293b;
            --text-primary: #1e293b;   /* alias, sesetengah bahagian guna nama ini */
            --text-muted: #64748b;
            --border-light: #cbd5e1;
            --border-color: #e2e8f0;
            --shadow-color: rgba(0,0,0,0.08);
        }

        [data-theme="dark"] {
            --bg-page: #0f172a;
            --bg-card: #1e293b;
            --bg-sidebar: #16213a;
            --bg-subtle: #263449;
            --text-dark: #e2e8f0;
            --text-primary: #e2e8f0;
            --text-muted: #94a3b8;
            --border-light: #334155;
            --border-color: #334155;
            --shadow-color: rgba(0,0,0,0.4);

            /* --primary-mdbg SENGAJA tak ditukar — kekal identiti jenama */
        }

        /* Teks & latar asas: pastikan kekal jelas dalam dark mode */
        [data-theme="dark"] body {
            color: var(--text-primary);
        }

        /* Bootstrap guna !important pada utility class ni, jadi kena override
           dengan specificity lebih tinggi supaya teks tak kekal gelap.
           :not(.badge) elak sentuh badge "subtle" (cth: Gred G1) yang
           latarnya sengaja kekal cerah walau dalam dark mode. */
        [data-theme="dark"] .text-dark:not(.badge),
        [data-theme="dark"] .text-black:not(.badge) {
            color: var(--text-primary) !important;
        }

        [data-theme="dark"] .text-muted:not(.badge),
        [data-theme="dark"] .text-secondary:not(.badge) {
            color: var(--text-muted) !important;
        }

        [data-theme="dark"] .bg-white,
        [data-theme="dark"] .bg-light {
            background-color: var(--bg-card) !important;
        }

        [data-theme="dark"] .border,
        [data-theme="dark"] .border-bottom,
        [data-theme="dark"] .border-top,
        [data-theme="dark"] .border-start,
        [data-theme="dark"] .border-end {
            border-color: var(--border-color) !important;
        }

        /* Butang outline (cth: "Kemaskini", "Sembunyikan Semakan") */
        [data-theme="dark"] .btn-outline-dark {
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
        }
        [data-theme="dark"] .btn-outline-dark:hover {
            background-color: var(--bg-subtle) !important;
            color: var(--text-primary) !important;
        }
        [data-theme="dark"] .btn-outline-secondary {
            color: var(--text-muted) !important;
            border-color: var(--border-color) !important;
        }
        [data-theme="dark"] .btn-outline-secondary:hover {
            background-color: var(--bg-subtle) !important;
            color: var(--text-primary) !important;
        }

        /* CSS UNTUK ALERT PEMBAHARUAN */
        
        #alertPembaharuan .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(243, 156, 18, 0.4) !important;
        }
        #alertPembaharuan .btn-close:hover {
            opacity: 1 !important;
        }

       body {
            background-color: var(--bg-page);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            height: 100vh; 
            overflow: hidden; 
        }
        
        /* --- MODERN WHITE NAVBAR --- */
        .mdbg-navbar {
            background-color: var(--bg-card);
            padding: 0 30px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 70px;
            z-index: 1030;
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
        }
        
        .header-logo-mdbg {
            height: 45px !important;
            width: auto !important;
            object-fit: contain !important;
            display: inline-block !important;
            transition: transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
        }
        
        .header-logo-mdbg:hover {
            transform: scale(1.15) rotate(-10deg) translateY(-3px) !important;
        }

        .mdbg-brand {
            font-weight: 700;
            font-size: 1.15rem;
            letter-spacing: -0.3px;
            color: var(--text-primary);
            line-height: 1.2;
        }

        .mdbg-subtext {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 400;
        }
        
        .btn-toggle-sidebar {
            background: var(--bg-subtle);
            border: none;
            color: var(--text-muted);
            padding: 8px 14px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .btn-toggle-sidebar:hover {
            background: var(--border-color);
            color: var(--text-primary);
        }

        .btn-logout {
            background-color: #fef2f2;
            color: #ef4444;
            border: 1px solid #fee2e2;
            font-weight: 600;
            padding: 8px 16px;
            font-size: 0.85rem;
            border-radius: 8px;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-logout:hover {
            background-color: #ef4444;
            color: white;
            border-color: #ef4444;
        }

        /* --- NOTIFICATION BUTTON --- */
        .btn-notification {
            background-color: var(--bg-subtle);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            padding: 8px 12px;
            border-radius: 8px;
            position: relative;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .btn-notification:hover {
            background-color: var(--border-color);
            color: var(--text-primary);
        }

        .btn-notification .notification-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            height: 10px;
            width: 10px;
            background-color: #ef4444;
            border-radius: 50%;
            border: 1.5px solid var(--bg-card);
        }

        /* --- LAYOUT WRAPPER --- */
        .wrapper {
            display: flex;
            position: fixed; 
            top: 70px;       
            left: 0;
            right: 0;
            bottom: 0;
            height: calc(100vh - 70px); 
            overflow: hidden; 
            align-items: stretch;
        }

        /* --- SIDEBAR --- */
        .sidebar-container {
            width: var(--sidebar-width);
            background-color: #1e293b; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            flex-shrink: 0;
            box-shadow: 4px 0 15px rgba(0,0,0,0.03);
            z-index: 1010;
            height: 100%; 
            overflow-y: auto; 
        }

        .sidebar-container.collapsed {
            margin-left: calc(-1 * var(--sidebar-width));
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .sidebar-category-title {
            padding: 12px 25px 6px 25px;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            font-weight: 700;
            color: #64748b; 
        }
        
        /* --- REKA BENTUK MODEN SIDEBAR MENU & SUB-MENU --- */

        /* Parent Menu Item */
        .sidebar-menu .nav-link-item {
            padding: 12px 18px;
            margin: 4px 12px;
            border-radius: 10px;
            font-weight: 500;
            color: #94a3b8;
            font-size: 0.90rem;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            text-decoration: none;
            border-left: none;
        }

        .sidebar-menu .nav-link-item:hover {
            background-color: #334155;
            color: #ffffff;
            transform: translateX(3px);
        }

        .sidebar-menu .nav-link-item.active {
            background-color: var(--primary-mdbg) !important;
            color: #ffffff !important;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(141, 91, 76, 0.35);
        }

        .sidebar-menu .nav-link-item[aria-expanded="true"] {
            background-color: rgba(51, 65, 85, 0.7);
            color: #ffffff;
        }

        /* Anak Panah (Chevron) Rotate Smooth */
        .chevron-icon {
            font-size: 0.75rem !important;
            width: auto !important;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            margin-left: 8px;
        }

        .nav-link-item[aria-expanded="true"] .chevron-icon {
            transform: rotate(180deg) !important;
        }

        .nav-link-item[aria-expanded="false"] .chevron-icon {
            transform: rotate(0deg) !important;
        }

        /* Container Sub-menu dengan Garisan Pengasing Hierarki (Tree Guide) */
        .submenu-tree {
            position: relative;
            padding-left: 12px;
            margin: 4px 16px 8px 32px;
            border-left: 2px solid #334155; /* Garisan bertingkat */
        }

        /* Sub-Menu Items */
        .nav-link-sub-item {
            padding: 9px 14px;
            margin: 3px 0;
            border-radius: 8px;
            font-weight: 400;
            color: #94a3b8;
            font-size: 0.84rem;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            text-decoration: none;
            background-color: transparent;
        }

        .nav-link-sub-item:hover {
            background-color: rgba(255, 255, 255, 0.08);
            color: #38bdf8; /* Highlight warna biru cerah/cyan semasa hover */
            transform: translateX(4px);
        }

        .nav-link-sub-item.active {
            color: #ffffff;
            font-weight: 600;
            background-color: rgba(56, 189, 248, 0.15);
            border: 1px solid rgba(56, 189, 248, 0.3);
        }

        .nav-link-sub-item i {
            font-size: 0.85rem !important;
            width: 22px !important;
            margin-right: 6px;
            transition: transform 0.2s ease;
        }

        .nav-link-sub-item:hover i {
            transform: scale(1.2);
            color: #38bdf8;
        }

        /* --- MAIN WORKSPACE --- */
        .main-content-container {
            flex-grow: 1;
            padding: 40px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            width: 100%;
            margin-left: 0; 
            height: 100%;
            overflow-y: auto; 
        }

        /* --- CARD --- */
        .custom-premium-card {
            background-color: var(--bg-card);
            border: 2px solid var(--border-light) !important;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08) !important;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .custom-premium-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.12) !important;
        }

        /* --- KAD STATUS RINGKASAN (Semakan Dokumen / Status Bayaran / Tarikh Tamat Sah) --- */
        .custom-premium-card.status-stat-card {
            border: 1px solid var(--border-color) !important;
            border-radius: 18px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 10px 24px rgba(15, 23, 42, 0.05) !important;
            position: relative;
            overflow: hidden;
        }
        .custom-premium-card.status-stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: var(--stat-accent-color, var(--primary-mdbg));
            opacity: 0.85;
        }
        .custom-premium-card.status-stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.06), 0 16px 30px rgba(15, 23, 42, 0.09) !important;
        }
        .status-stat-card .stat-icon-box {
            box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.05);
        }
        .status-stat-card .stat-label {
            letter-spacing: 0.6px !important;
            opacity: 0.8;
        }
        .status-stat-card .stat-value {
            letter-spacing: -0.2px;
        }

        /* --- TIMELINE --- */
        .timeline-steps {
            position: relative;
            padding-left: 30px;
        }
        .timeline-steps::before {
            content: '';
            position: absolute;
            left: 9px;
            top: 5px;
            bottom: 5px;
            width: 2px;
            background-color: var(--border-light);
        }
        .timeline-item {
            position: relative;
            padding-bottom: 25px;
        }
        .timeline-item:last-child {
            padding-bottom: 0;
        }
        .timeline-icon {
            position: absolute;
            left: -30px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background-color: var(--bg-card);
            border: 2px solid var(--primary-mdbg);
            z-index: 2;
        }
        .timeline-item.done .timeline-icon {
            background-color: #16a34a;
            border-color: #16a34a;
        }

        /* --- INPUT DATE FORMATTING --- */
        input[type="date"].form-control-sm {
            position: relative;
            background-color: var(--bg-card) !important;
            border: 1px solid var(--border-light) !important;
            border-radius: 6px !important;
            padding: 6px 10px !important;
            font-size: 0.85rem !important;
            font-weight: 600 !important;
            color: var(--text-primary) !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
            transition: all 0.2s ease;
            min-width: 135px !important;
        }

        input[type="date"].form-control-sm:focus {
            border-color: #8D5B4C !important;
            box-shadow: 0 0 0 3px rgba(141, 91, 76, 0.15) !important;
            outline: none;
        }

        input[type="date"]::-webkit-calendar-picker-indicator {
            background-color: var(--bg-subtle);
            padding: 4px;
            border-radius: 4px;
            cursor: pointer;
            color: var(--text-muted);
            transition: background-color 0.2s;
        }

        input[type="date"]::-webkit-calendar-picker-indicator:hover {
            background-color: var(--border-color);
        }

        /* ========================================================================= */
        /* --- MODERN & PROFESSIONAL BANTUAN & SOKONGAN CARD DESIGN --- */
        /* ========================================================================= */
        .support-card {
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border-color) !important;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .support-card:hover {
            box-shadow: 0 12px 28px -6px rgba(141, 91, 76, 0.12), 0 4px 12px -2px rgba(0, 0, 0, 0.04) !important;
        }
        .support-header-icon {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, rgba(141, 91, 76, 0.12) 0%, rgba(110, 68, 56, 0.08) 100%);
            color: var(--primary-mdbg);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            border: 1px solid rgba(141, 91, 76, 0.15);
        }
        .support-item {
            background: var(--bg-subtle);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 12px 14px;
            transition: all 0.2s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .support-item:hover {
            background: var(--bg-card);
            border-color: var(--primary-mdbg);
            box-shadow: 0 4px 12px rgba(141, 91, 76, 0.08);
            transform: translateY(-2px);
        }
        .support-item-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .support-icon-wa {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            color: #ffffff;
            box-shadow: 0 4px 10px rgba(37, 211, 102, 0.25);
        }
        .support-icon-clock {
            background: linear-gradient(135deg, #3B82F6 0%, #1D4ED8 100%);
            color: #ffffff;
            box-shadow: 0 4px 10px rgba(59, 130, 246, 0.25);
        }
        .support-icon-email {
            background: linear-gradient(135deg, #8D5B4C 0%, #6E4438 100%);
            color: #ffffff;
            box-shadow: 0 4px 10px rgba(141, 91, 76, 0.25);
        }
        .support-label {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--text-muted);
            margin-bottom: 2px;
            display: block;
        }
        .support-val {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.2px;
            margin-bottom: 0;
            line-height: 1.2;
        }

        /* ========================================================================= */
        /* --- MODERN & PROFESSIONAL AI CHATBOT DESIGN --- */
        /* ========================================================================= */
        .ai-chat-btn {
            position: fixed;
            bottom: 28px;
            right: 28px;
            width: 62px;
            height: 62px;
            background: linear-gradient(135deg, #8D5B4C 0%, #5d392e 100%);
            color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.55rem;
            box-shadow: 0 10px 25px -5px rgba(141, 91, 76, 0.4), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            z-index: 2000;
            border: 2px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .ai-chat-btn:hover {
            transform: scale(1.1) rotate(5deg);
            background: linear-gradient(135deg, #a16b5a 0%, #4a2d24 100%);
            color: #ffffff;
            box-shadow: 0 14px 30px -4px rgba(141, 91, 76, 0.5), 0 10px 12px -5px rgba(0, 0, 0, 0.15);
        }
        .ai-chat-btn:active {
            transform: scale(0.95);
        }

        .ai-chat-box {
            position: fixed;
            bottom: 102px;
            right: 28px;
            width: 380px;
            height: 520px;
            background-color: var(--bg-card);
            border-radius: 20px;
            box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.25), 0 0 0 1px rgba(15, 23, 42, 0.05);
            display: none;
            flex-direction: column;
            overflow: hidden;
            z-index: 2000;
            border: 1px solid rgba(226, 232, 240, 0.8);
            backdrop-filter: blur(10px);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            transform-origin: bottom right;
        }
        .ai-chat-box.active {
            display: flex;
            animation: chatSlideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes chatSlideIn {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .ai-chat-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .ai-avatar-icon {
            width: 38px;
            height: 38px;
            background: rgba(141, 91, 76, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #f59e0b;
            font-size: 1.15rem;
        }

        .ai-online-status {
            display: inline-block;
            width: 7px;
            height: 7px;
            background-color: #22c55e;
            border-radius: 50%;
            margin-right: 5px;
            box-shadow: 0 0 8px #22c55e;
            animation: pulseStatus 2s infinite;
        }

        @keyframes pulseStatus {
            0% { opacity: 1; }
            50% { opacity: 0.4; }
            100% { opacity: 1; }
        }

        .ai-chat-body {
            flex-grow: 1;
            padding: 18px;
            overflow-y: auto;
            background-color: var(--bg-subtle);
            display: flex;
            flex-direction: column;
            gap: 12px;
            scrollbar-width: thin;
            scrollbar-color: var(--border-light) transparent;
        }
        .ai-chat-body::-webkit-scrollbar {
            width: 5px;
        }
        .ai-chat-body::-webkit-scrollbar-thumb {
            background-color: var(--border-light);
            border-radius: 10px;
        }

        .ai-message {
            max-width: 82%;
            padding: 12px 16px;
            border-radius: 16px;
            font-size: 0.875rem;
            line-height: 1.5;
            word-wrap: break-word;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
            position: relative;
        }

        .ai-message.bot {
            background-color: var(--bg-card);
            color: var(--text-primary);
            align-self: flex-start;
            border: 1px solid var(--border-color);
            border-top-left-radius: 4px;
        }

        .ai-message.user {
            background: linear-gradient(135deg, #8D5B4C 0%, #75473a 100%);
            color: #ffffff;
            align-self: flex-end;
            border-top-right-radius: 4px;
            font-weight: 500;
        }

        .ai-chat-footer {
            padding: 12px 16px;
            background-color: var(--bg-card);
            border-top: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ai-chat-input-wrapper {
            position: relative;
            flex-grow: 1;
        }

        .ai-chat-input-wrapper input {
            width: 100%;
            border-radius: 22px !important;
            padding: 9px 16px !important;
            background-color: var(--bg-subtle) !important;
            border: 1px solid var(--border-color) !important;
            font-size: 0.85rem !important;
            transition: all 0.2s ease;
        }

        .ai-chat-input-wrapper input:focus {
            background-color: var(--bg-card) !important;
            border-color: #8D5B4C !important;
            box-shadow: 0 0 0 3px rgba(141, 91, 76, 0.12) !important;
            outline: none;
        }

        .ai-send-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, #8D5B4C 0%, #6e4438 100%);
            color: #ffffff;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            flex-shrink: 0;
            box-shadow: 0 3px 8px rgba(141, 91, 76, 0.3);
        }

        .ai-send-btn:hover {
            transform: scale(1.05);
            background: linear-gradient(135deg, #9d6858 0%, #5d392e 100%);
        }
        /* ========================================================================= */


        /* ========================================================================= */
        /* --- MODERN & PROFESSIONAL BANTUAN & SOKONGAN CARD DESIGN --- */
        /* ========================================================================= */
        .support-card {
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border-color) !important;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .support-card:hover {
            box-shadow: 0 12px 28px -6px rgba(141, 91, 76, 0.12), 0 4px 12px -2px rgba(0, 0, 0, 0.04) !important;
        }
        .support-header-icon {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, rgba(141, 91, 76, 0.12) 0%, rgba(110, 68, 56, 0.08) 100%);
            color: var(--primary-mdbg);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            border: 1px solid rgba(141, 91, 76, 0.15);
            flex-shrink: 0;
        }
        .support-item {
            background: var(--bg-subtle);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 12px 14px;
            transition: all 0.2s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            box-sizing: border-box;
        }
        .support-item:hover {
            background: var(--bg-card);
            border-color: var(--primary-mdbg);
            box-shadow: 0 4px 12px rgba(141, 91, 76, 0.08);
            transform: translateY(-2px);
        }
        .support-item-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .support-icon-wa {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            color: #ffffff;
            box-shadow: 0 4px 10px rgba(37, 211, 102, 0.25);
        }
        .support-icon-clock {
            background: linear-gradient(135deg, #3B82F6 0%, #1D4ED8 100%);
            color: #ffffff;
            box-shadow: 0 4px 10px rgba(59, 130, 246, 0.25);
        }
        .support-icon-email {
            background: linear-gradient(135deg, #8D5B4C 0%, #6E4438 100%);
            color: #ffffff;
            box-shadow: 0 4px 10px rgba(141, 91, 76, 0.25);
        }
        .support-label {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--text-muted);
            margin-bottom: 2px;
            display: block;
        }
        .support-val {
            font-size: clamp(0.75rem, 1vw, 0.88rem);
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.2px;
            margin-bottom: 0;
            line-height: 1.3;
            word-break: break-word;
            white-space: normal;
        }
        
        @media (max-width: 768px) {
            .mdbg-navbar {
                padding: 0 10px;
            }
            .btn-toggle-sidebar {
                padding: 6px 10px;
            }
            .header-logo-mdbg {
                height: 32px;
            }
            .mdbg-brand {
                font-size: 0.8rem;
                letter-spacing: -0.2px;
            }
            .btn-logout {
                padding: 6px 10px;
                font-size: 0.75rem;
            }
            .btn-logout i {
                margin-right: 4px !important;
            }
            .main-content-container {
                padding: 20px 15px;
                margin-left: 0 !important;
            }
            .sidebar-container {
                position: fixed;
                left: 0;
                top: 70px;
                height: calc(100vh - 70px);
                z-index: 1000;
            }
            .sidebar-container.collapsed {
                left: calc(-1 * var(--sidebar-width));
                margin-left: 0; 
            }
            .ai-chat-box {
                width: calc(100vw - 32px);
                right: 16px;
                bottom: 90px;
                height: 480px;
            }
        }

        /* --- REKA BENTUK BUTANG MEDIA SOSIAL FLOATING (DESKTOP & MOBILE) --- */
            .social-btn {
                position: fixed;
                bottom: 28px;
                width: 62px;      /* Tukar saiz di sini */
                height: 62px;     /* Tukar saiz di sini */
                color: #ffffff;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.55rem; /* Tukar saiz font di sini */
                z-index: 2000;
                border: 2px solid rgba(255, 255, 255, 0.3);
                transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
                text-decoration: none;
            }
            
            /* Butang Instagram (Di sebelah kiri AI Chatbot) */
            .social-btn-ig {
                right: 102px;
                background: linear-gradient(135deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);
                box-shadow: 0 10px 25px -5px rgba(220, 39, 67, 0.4), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            }

            .social-btn-ig:hover {
                transform: scale(1.1) rotate(5deg);
                color: #ffffff;
                box-shadow: 0 14px 30px -4px rgba(220, 39, 67, 0.5), 0 10px 12px -5px rgba(0, 0, 0, 0.15);
            }

            /* Butang Facebook (Di sebelah kiri Instagram) */
            .social-btn-fb {
                right: 176px;
                background: linear-gradient(135deg, #1877F2 0%, #0b5ed7 100%);
                box-shadow: 0 10px 25px -5px rgba(24, 119, 242, 0.4), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            }

            .social-btn-fb:hover {
                transform: scale(1.1) rotate(5deg);
                color: #ffffff;
                box-shadow: 0 14px 30px -4px rgba(24, 119, 242, 0.5), 0 10px 12px -5px rgba(0, 0, 0, 0.15);
            }

            /* Penyesuaian Saiz dan Jarak untuk Paparan Telefon (Mobile) */
            @media (max-width: 768px) {
                .social-btn {
                    width: 48px;
                    height: 48px;
                    font-size: 1.2rem;
                    bottom: 20px;
                }
                
                /* AI Chat - Paling Kanan (sejajar dengan Facebook & Instagram) */
                .ai-chat-btn {
                    right: 16px !important;
                    width: 48px !important;
                    height: 48px !important;
                    font-size: 1.2rem !important;
                    bottom: 20px !important;  /* Tambah ini untuk sejajar */
                }
                
                /* Instagram - Tengah */
                .social-btn-ig {
                    right: 72px;
                    bottom: 20px !important;  /* Tambah ini untuk sejajar */
                }
                
                /* Facebook - Kiri */
                .social-btn-fb {
                    right: 128px;
                    bottom: 20px !important;  /* Tambah ini untuk sejajar */
                }

                 .btn-logout-mobile {
                    width: 38px !important;
                    height: 38px !important;
                    padding: 0 !important;
                    justify-content: center !important;
                }
                .btn-logout-mobile i {
                    margin-right: 0 !important;
                }
            }

            /* --- STYLING UNTUK BAHAGIAN DOKUMEN PEMBAYARAN (HIGHLIGHT HIJAU) --- */
            .highlight-bil-section {
                background: linear-gradient(135deg, #bbf7d0 0%, #86efac 50%, #4ade80 100%) !important;
                border: 3px solid #22c55e !important;
                border-radius: 12px !important;
                padding: 20px !important;
                margin-bottom: 12px !important;
                box-shadow: 0 6px 20px rgba(34, 197, 94, 0.35) !important;
                transition: all 0.3s ease;
            }

            .highlight-bil-section:hover {
                box-shadow: 0 6px 25px rgba(34, 197, 94, 0.25) !important;
                transform: translateY(-2px);
            }

            .highlight-bil-section .btn {
                font-weight: 700 !important;
                letter-spacing: 0.3px;
            }

            .highlight-bil-section .badge {
                font-size: 0.75rem !important;
                padding: 6px 14px !important;
                border-radius: 20px !important;
            }

            /* Animasi berdenyut untuk menarik perhatian */
            @keyframes pulse-green {
                0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.3); }
                70% { box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); }
                100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
            }

            .highlight-bil-section {
                animation: pulse-green 2s ease-in-out 3;
            }

            /* Styling untuk label dalam bahagian hijau */
            .highlight-bil-section .text-dark {
                color: #14532d !important;
            }

            .highlight-bil-section .text-muted {
                color: #166534 !important;
            }

            /* PEMBETULAN DARK MODE: kotak ini latarnya sengaja kekal cerah
               (hijau/putih lutsinar), jadi teks mesti kekal hitam walau
               tema gelap diaktifkan — bukan ikut var(--text-primary) */
            [data-theme="dark"] .highlight-bil-section .text-dark,
            [data-theme="dark"] .highlight-bil-section .text-muted,
            [data-theme="dark"] .highlight-bil-section strong,
            [data-theme="dark"] .highlight-bil-section span {
                color: #000000 !important;
            }

            .highlight-bil-section .btn-primary {
                background: #16a34a !important;
                border-color: #16a34a !important;
            }

            .highlight-bil-section .btn-primary:hover {
                background: #15803d !important;
                border-color: #15803d !important;
            }

            .highlight-bil-section .btn-success {
                background: #16a34a !important;
                border-color: #16a34a !important;
            }

            .highlight-bil-section .btn-success:hover {
                background: #15803d !important;
                border-color: #15803d !important;
            }

            /* Badge "PERHATIAN" dalam bahagian hijau */
            .badge-attention {
                background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%) !important;
                color: #ffffff !important;
                font-size: 0.8rem !important;
                font-weight: 800 !important;
                padding: 6px 18px !important;
                border-radius: 30px !important;
                border: 2px solid #fef08a !important;
                animation: pulse-red 1.5s ease-in-out infinite;
                text-transform: uppercase !important;
                letter-spacing: 0.5px !important;
                box-shadow: 0 2px 10px rgba(220, 38, 38, 0.4) !important;
            }

            @keyframes pulse-red {
                0% { opacity: 1; }
                50% { opacity: 0.5; }
                100% { opacity: 1; }
            }
    </style>
    <script>

    // Muatkan tema tersimpan SEBELUM halaman render
    (function() {
        const savedTheme = localStorage.getItem('mdbg_theme');
        if (savedTheme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    })();
</script>

</head>
<body>

    <div class="mdbg-navbar d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2 gap-md-3">
            <button class="btn-toggle-sidebar" id="sidebarToggle" type="button">
                <i class="fa-solid fa-bars fs-5"></i>
            </button>
            <div class="d-flex align-items-center gap-1 gap-md-2">
                <img src="logo_mdbg.png" alt="Logo MDBG" class="header-logo-mdbg me-1 me-md-2">
                <div>
                    <div class="mdbg-brand">PORTAL KONTRAKTOR MDBG</div>
                    <div class="mdbg-subtext d-none d-md-block">Sistem Pendaftaran & Undi Pembekal Atas Talian</div>
                </div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 gap-md-3">
            <span class="text-dark medium d-none d-md-inline">Selamat Datang, <strong class="text-secondary text-uppercase"><?= htmlspecialchars(strtoupper($nama_paparan)); ?></strong></span>
            <button id="darkModeToggle" class="btn-notification" title="Tukar Tema">
                <i class="fa-solid fa-moon fs-5" id="themeIcon"></i>
            </button>        
            <button class="btn-notification" onclick="semakNotifikasiTerbaru()" title="Peti Notifikasi">
                <i class="fa-solid fa-bell fs-5"></i>
                <?php if ($mempunyai_notifikasi_aktif): ?>
                    <span class="notification-badge"></span>
                <?php endif; ?>
            </button>

            <a href="logout.php" class="btn-logout d-flex align-items-center btn-logout-mobile"><i class="fa-solid fa-right-from-bracket me-1 me-md-2"></i> <span class="d-none d-md-inline">Log Keluar</span></a>
        </div>
    </div>

    <div class="wrapper">
        
        <div class="sidebar-container" id="sidebarWrapper">
            <div class="sidebar-menu">
                <div class="sidebar-category-title">Menu Utama</div>
                <?php 
                    // Dynamic link supaya parameter semak=1 kekal terbuka jika aktif untuk akaun Individu
                    $dashboard_url = 'kontraktor.php';
                    if ($jenis_akaun == 'Individu' && $show_details_individu) {
                        $dashboard_url = 'kontraktor.php?semak=1';
                    }
                ?>
                <a href="<?= $dashboard_url; ?>" class="nav-link-item active">
                    <i class="fa-solid fa-chart-pie me-2"></i> Dashboard
                </a>
                <div class="sidebar-category-title">URUSAN PENDAFTARAN</div>

                <!-- Fasa 1 (Sembunyi jika Aktif) -->
                <?php if (!$is_status_aktif): ?>
                    <a href="kontraktor_borang_daftar.php" class="nav-link-item">
                        <i class="fa-solid fa-file-pen me-2"></i>
                        <span>
                            <?= $is_expired ? 'Fasa 1: Pembaharuan Syarikat' : 'Fasa 1: Daftar Syarikat'; ?>
                        </span>
                    </a>
                <?php endif; ?>

                <!-- Fasa 2 (Menu Utama Dropdown) -->
                <a href="#submenuFasa2" class="nav-link-item d-flex align-items-center justify-content-between" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="submenuFasa2">
                    <div class="d-flex align-items-center overflow-hidden">
                        <i class="fa-solid fa-box-archive me-2"></i>
                        <span>Fasa 2: Daftar Kerja Undi</span>
                    </div>
                    <i class="fa-solid fa-chevron-down chevron-icon"></i>
                </a>

                <!-- Sub-Menu Fasa 2 -->
                <div class="collapse" id="submenuFasa2">
                    <div class="submenu-tree">
                        <a href="kontraktor_daftar_undi.php" class="nav-link-sub-item">
                            <i class="fa-solid fa-pen-to-square"></i>
                            <span>Pendaftaran Undi</span>
                        </a>
                        <a href="kontraktor_carian_syarikat.php" class="nav-link-sub-item">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <span>Semakan Daftar Undi</span>
                        </a>
                    </div>
                </div>
                
                <div class="sidebar-category-title">Maklumat & Syarat</div>
                <a href="manual_pengguna.php" class="nav-link-item">
                    <i class="fa-solid fa-book-bookmark me-2"></i> Syarat Pengguna
                </a>

                <!-- MENU BAHARU: MAKLUMAT KONTRAKTOR -->
                <a href="maklumat_kontraktor.php" class="nav-link-item">
                    <i class="fa-solid fa-id-card me-2"></i> Maklumat Kontraktor
                </a>
            </div>
        </div>

        <div class="main-content-container" id="contentWrapper">
            <div class="container-fluid p-0">
                
                <?php 
                // TAMBAHAN LOGIK BAHARU: Mod Clean / Hide untuk Sesi INDIVIDU sahaja
                if ($jenis_akaun == 'Individu' && !$show_details_individu): 
                ?>
                    <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white my-4">
                        <div class="mb-3">
                            <i class="fa-solid fa-user-shield text-primary display-4"></i>
                        </div>
                        <h3 class="fw-bold text-dark">Selamat Datang, <?= htmlspecialchars($nama_paparan); ?></h3>
                        <p class="text-muted mx-auto" style="max-width: 580px;">
                            Sesi log masuk anda adalah sebagai <strong>Individu</strong>. Halaman ini dipaparkan ringkas. Sila tekan butang semakan di bawah untuk memaparkan Ringkasan Profil dan Status Pendaftaran Syarikat anda.
                        </p>
                        <div class="mt-3">
                            <a href="kontraktor.php?semak=1" class="btn btn-primary btn-lg px-4 rounded-3 fw-bold">
                                <i class="fa-solid fa-magnifying-glass me-2"></i> Semak Status & Profile Syarikat
                            </a>
                        </div>
                    </div>
                <?php else: ?>

                <div class="mb-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="fw-bold text-dark mb-1">Status Pendaftaran Syarikat Anda</h4>
                        <p class="text-muted small mb-0">Semakan maklumat terkini status permohonan pembekal di Majlis Daerah Batu Gajah.</p>
                    </div>
                    <?php if ($jenis_akaun == 'Individu'): ?>
                        <a href="kontraktor.php?semak=0" class="btn btn-outline-secondary btn-sm rounded-3">
                            <i class="fa-solid fa-eye-slash me-1"></i> Sembunyikan Semakan
                        </a>
                    <?php endif; ?>
                </div>
                
                <?php if (!$profil): ?>
                    <div class="alert alert-warning border border-warning shadow d-flex align-items-start p-4" role="alert" style="border-radius: 14px; background-color: #fff3cd; border-width: 2px !important;">
                        <i class="fa-solid fa-triangle-exclamation fs-4 text-warning me-3 mt-1"></i>
                        <div class="text-start">
                            <h6 class="fw-bold mb-1" style="color: #856404;">Permohonan Belum Dijumpai</h6>
                            <span class="small" style="color: #856404;">Anda belum mengemukakan sebarang permohonan pendaftaran syarikat. Sila pilih menu <strong>Fasa 1: Daftar Syarikat</strong>.</span>
                        </div>
                    </div>
                <?php else: ?>
                    
                <?php if ($is_expired): ?>
                    <div id="alertPembaharuan" class="alert d-flex align-items-center p-3 mb-4 position-relative" role="alert" style="border-radius: 10px; background: linear-gradient(135deg, #fdf6e3 0%, #fef9e7 100%); border: 1px solid #f5cba7; box-shadow: 0 2px 12px rgba(230, 126, 34, 0.08);">
                        <div class="d-flex align-items-center gap-3 flex-wrap flex-sm-nowrap w-100">
                            <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 38px; height: 38px; background: linear-gradient(135deg, #f39c12, #e67e22); color: #ffffff; box-shadow: 0 2px 8px rgba(243, 156, 18, 0.25);">
                                <i class="fa-regular fa-clock fs-6"></i>
                            </div>
                            <div class="flex-grow-1">
                                <span class="fw-bold" style="color: #7d6608; font-size: 0.90rem;">Pembaharuan Pendaftaran</span>
                                <div class="mt-1 d-flex align-items-center gap-2">
                                    <span class="badge px-2 py-1" style="font-size: 0.6rem; background: #e67e22; color: #ffffff; border-radius: 20px; letter-spacing: 0.3px;">TAMAT</span>
                                    <span style="font-size: 0.8rem; color: #7a5d2e;">Sila buat pembaharuan untuk mengemukakan semula borang</span>
                                </div>
                            </div>
                            <a href="kontraktor_borang_daftar.php" class="btn btn-sm fw-bold px-3 py-2 text-nowrap" style="font-size: 0.8rem; border-radius: 9px; background: linear-gradient(135deg, #f39c12, #e67e22); color: #ffffff; border: none; box-shadow: 0 2px 8px rgba(243, 156, 18, 0.2); transition: all 0.3s ease;">
                                <i class="fa-solid fa-rotate-right me-1"></i> Perbaharui Sekarang
                            </a>
                            <button type="button" class="btn-close btn-sm" onclick="this.closest('.alert').remove()" aria-label="Close" style="width: 0.6rem; height: 0.6rem; opacity: 0.7;"></button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="row g-3 g-md-4 mb-4">
                        <div class="col-12 col-md-4">
                            <?php 
                                $status = $profil['status_borang'];
                                
                                if ($status == 'Lengkap') {
                                    $bg_icon = '#dcfce7'; $color_icon = '#16a34a'; $fa_icon = 'fa-circle-check';
                                } elseif ($status == 'Pending') {
                                    $bg_icon = '#fef3c7'; $color_icon = '#d97706'; $fa_icon = 'fa-clock';
                                } else {
                                    $bg_icon = '#fee2e2'; $color_icon = '#dc2626'; $fa_icon = 'fa-circle-xmark';
                                }
                            ?>
                            <div class="p-4 custom-premium-card status-stat-card d-flex align-items-center h-100" style="--stat-accent-color: <?= $color_icon ?>;">
                                <div class="d-flex align-items-center justify-content-center rounded-3 me-3 flex-shrink-0 stat-icon-box" style="width: 56px; height: 56px; background-color: <?= $bg_icon ?>; color: <?= $color_icon ?>;">
                                    <i class="fa-solid <?= $fa_icon ?> fs-4"></i>
                                </div>
                                <div>
                                    <div class="text-uppercase text-muted fw-bold mb-1 stat-label" style="font-size: 0.75rem;">Semakan Dokumen</div>
                                    <div class="fw-bold text-dark fs-5 stat-value"><?= htmlspecialchars(!empty($status) ? $status : 'Gagal (Tolak)'); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <?php 
                                $bayaran = $profil['status_bayaran_daftar'];
                                $is_paid = ($bayaran == 'Sudah Bayar');
                                $bg_pay = $is_paid ? '#dcfce7' : '#fee2e2';
                                $color_pay = $is_paid ? '#16a34a' : '#dc2626';
                                $icon_pay = $is_paid ? 'fa-credit-card' : 'fa-money-bill-wave';
                            ?>
                            <div class="p-4 custom-premium-card status-stat-card d-flex align-items-center h-100" style="--stat-accent-color: <?= $color_pay ?>;">
                                <div class="d-flex align-items-center justify-content-center rounded-3 me-3 flex-shrink-0 stat-icon-box" style="width: 56px; height: 56px; background-color: <?= $bg_pay ?>; color: <?= $color_pay ?>;">
                                    <i class="fa-solid <?= $icon_pay ?> fs-4"></i>
                                </div>
                                <div>
                                    <div class="text-uppercase text-muted fw-bold mb-1 stat-label" style="font-size: 0.75rem;">Status Bayaran (RM52)</div>
                                    <div class="fw-bold text-dark fs-5 stat-value"><?= htmlspecialchars($bayaran); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <?php 
                                // Tarikh hanya dipaparkan jika status bayaran adalah 'Sudah Bayar'
                                $has_date = (!empty($profil['tarikh_tamat_aktif']) && $profil['tarikh_tamat_aktif'] != '0000-00-00' && isset($profil['status_bayaran_daftar']) && $profil['status_bayaran_daftar'] == 'Sudah Bayar');
                                
                                if ($is_expired) {
                                    // Design merah (Tamat Sah)
                                    $bg_date = '#fee2e2';
                                    $color_date = '#dc2626';
                                    $icon_date = 'fa-calendar-xmark';
                                    $text_color = 'text-danger';
                                } elseif ($has_date) {
                                    // Design biasa (Aktif)
                                    $bg_date = '#fdfaf9';
                                    $color_date = '#8D5B4C';
                                    $icon_date = 'fa-calendar-days';
                                    $text_color = 'text-dark';
                                } else {
                                    // Belum Aktif
                                    $bg_date = '#f8fafc';
                                    $color_date = '#64748b';
                                    $icon_date = 'fa-calendar-xmark';
                                    $text_color = 'text-dark';
                                }
                            ?>
                            <div class="p-4 custom-premium-card status-stat-card d-flex align-items-center h-100" style="--stat-accent-color: <?= $color_date ?>;">
                                <div class="d-flex align-items-center justify-content-center rounded-3 me-3 flex-shrink-0 stat-icon-box" style="width: 56px; height: 56px; background-color: <?= $bg_date ?>; color: <?= $color_date ?>; border: 1px solid #cbd5e1;">
                                    <i class="fa-solid <?= $icon_date ?> fs-4"></i>
                                </div>
                                <div>
                                    <div class="text-uppercase text-muted fw-bold mb-1 stat-label" style="font-size: 0.75rem;">Tarikh Tamat Sah</div>
                                    <div class="fw-bold <?= $text_color ?> fs-5 stat-value">
                                        <?= $has_date ? date('d-m-Y', strtotime($profil['tarikh_tamat_aktif'])) : 'Belum Aktif'; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================================================= -->
                    <!-- KOTAK ALASAN TOLAK -->
                    <!-- ========================================================================= -->
                    <?php 
                        $is_gagal = false;
                        if (isset($profil['status_borang'])) {
                            $sb = $profil['status_borang'];
                            if (strpos($sb, 'Gagal') !== false || strpos($sb, 'Tolak') !== false || strpos($sb, 'Tidak Lengkap') !== false || empty($sb)) {
                                $is_gagal = true;
                            }
                        }
                        if ($is_gagal && !empty($profil['alasan_tolak'])): 
                    ?>
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="alert alert-danger d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 p-3 shadow-sm" style="border-left: 4px solid #dc3545; background-color: #f8d7da; border-color: #f5c6cb; border-radius: 8px;">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="fa-solid fa-circle-exclamation fs-5 text-danger"></i>
                                        <div>
                                            <span class="fw-bold d-block" style="color: #721c24; font-size: 0.95rem; white-space: pre-line;">ALASAN TOLAK:
                                                <?= htmlspecialchars($profil['alasan_tolak']); ?></span>
                                            <span class="text-muted d-block mt-1" style="font-size: 0.8rem;">Sila perbetulkan borang pendaftaran anda dengan menekan butang di sebelah.</span>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-danger btn-sm fw-bold px-3 py-1.5 text-nowrap d-flex align-items-center justify-content-center gap-2" style="background-color: #dc3545; border: none; border-radius: 6px; font-size: 0.85rem;" data-bs-toggle="modal" data-bs-target="#modalKemaskiniProfil">
                                        <i class="fa-solid fa-pen-to-square"></i> Kemaskini Borang
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                   <div class="row g-4 mb-4">
                        <div class="col-12 col-lg-8">
                            <div class="p-4 custom-premium-card h-100">
                               <div class="d-flex align-items-center justify-content-between mb-4 pb-3 border-bottom">
                                    <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                                        <i class="fa-solid fa-building text-muted me-1"></i> Ringkasan Profil Syarikat
                                        <?php if ($is_status_aktif): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 fw-semibold rounded-pill ms-2" style="font-size: 0.90rem;">
                                                <i class="fa-solid fa-circle-check me-1"></i> Aktif
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3 py-2 fw-semibold rounded-pill ms-2" style="font-size: 0.90rem;">
                                                <i class="fa-solid fa-circle-xmark me-1"></i> Tidak Aktif
                                            </span>
                                        <?php endif; ?>
                                    </h5>
                                    <?php if (($profil['status_minta_kemaskini'] ?? '') == 'Approved'): ?>
                                        <!-- Jika Admin sudah Approve, butang ini akan membuka modal edit asal -->
                                        <button type="button" class="btn btn-outline-dark btn-sm fw-semibold px-3" style="border-radius: 6px;" data-bs-toggle="modal" data-bs-target="#modalKemaskiniProfil">
                                            <i class="fa-solid fa-pen-to-square me-1"></i> Kemaskini Profil
                                        </button>
                                    <?php elseif (($profil['status_minta_kemaskini'] ?? '') == 'Pending'): ?>
                                        <!-- Jika masih Pending kelulusan Admin -->
                                        <button type="button" class="btn btn-warning btn-sm fw-semibold px-3 text-dark" disabled style="border-radius: 6px;">
                                            <i class="fa-solid fa-hourglass-half me-1"></i> Menunggu Kelulusan Admin
                                        </button>
                                    <?php else: ?>
                                        <!-- Jika belum minta kebenaran, buka Modal Minta Sebab -->
                                        <button type="button" class="btn btn-outline-dark btn-sm fw-semibold px-3" style="border-radius: 6px;" data-bs-toggle="modal" data-bs-target="#modalMintaKemaskini">
                                            <i class="fa-solid fa-pen-to-square me-1"></i> Kemaskini
                                        </button>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="row g-3 g-md-4">
                                    <div class="col-12 col-sm-6">
                                        <label class="text-muted small fw-bold text-uppercase d-block mb-1"><i class="fa-solid fa-id-card me-1"></i> Nama Syarikat</label>
                                        <span class="text-dark fw-bold fs-6"><?= htmlspecialchars($profil['nama_syarikat'] ?? 'Belum Diisi'); ?></span>
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <label class="text-muted small fw-bold text-uppercase d-block mb-1"><i class="fa-solid fa-user-tie me-1"></i> No. Pendaftaran (SSM)</label>
                                        <span class="text-dark fw-semibold"><?= isset($profil['no_pendaftaran']) ? htmlspecialchars(strtoupper($profil['no_pendaftaran'])) : '-'; ?></span>
                                    </div>
                                    
                                    <!-- JADUAL GRED KELAYAKAN CIDB / KEWANGAN (DENGAN GARISAN MENEGAK) -->
                                    <div class="col-12">
                                        <label class="text-muted small fw-bold text-uppercase d-block mb-2">
                                            <i class="fa-solid fa-award me-1 text-warning"></i> Gred Kelayakan CIDB / Kewangan
                                        </label>
                                        <div class="table-responsive border rounded-3 shadow-sm bg-white overflow-hidden">
                                            <table class="table table-hover align-middle mb-0 w-100" style="font-size: 0.85rem;">
                                                <thead class="bg-light border-bottom">
                                                    <tr class="text-uppercase small fw-bold text-dark" style="letter-spacing: 0.5px;">
                                                        <th class="py-2.5 px-3 text-center" style="width: 30%;">Gred</th>
                                                        <th class="py-2.5 px-3 text-center border-start" style="width: 35%;">Kategori</th>
                                                        <th class="py-2.5 px-3 text-center border-start" style="width: 35%;">Pengkhususan</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    $gred_data = trim($profil['gred_cidb_kewangan'] ?? '');
                                                    if (!empty($gred_data)): 
                                                        $lines = array_filter(explode("\n", str_replace("\r", "", $gred_data)));
                                                        $has_rows = false;
                                                        
                                                        foreach ($lines as $line):
                                                            $clean_line = trim($line);
                                                            if (stripos($clean_line, 'GRED') !== false && stripos($clean_line, 'KATEGORI') !== false) {
                                                                continue;
                                                            }
                                                            
                                                            $cols = preg_split('/\s+/', $clean_line, 3);
                                                            if (!empty($cols[0])):
                                                                $has_rows = true;
                                                    ?>
                                                        <tr>
                                                            <td class="py-2.5 px-3 text-center">
                                                                <span class="badge bg-secondary-subtle text-dark border border-secondary-subtle font-monospace px-2.5 py-1 fw-bold rounded-2">
                                                                    <?= htmlspecialchars($cols[0] ?? '-'); ?>
                                                                </span>
                                                            </td>
                                                            <td class="py-2.5 px-3 text-center border-start fw-semibold text-secondary">
                                                                <?= htmlspecialchars($cols[1] ?? '-'); ?>
                                                            </td>
                                                            <td class="py-2.5 px-3 text-center border-start fw-bold text-primary">
                                                                <?= htmlspecialchars($cols[2] ?? '-'); ?>
                                                            </td>
                                                        </tr>
                                                    <?php 
                                                            endif;
                                                        endforeach;
                                                        
                                                        if (!$has_rows):
                                                    ?>
                                                        <tr>
                                                            <td colspan="3" class="py-3 px-3 text-center text-muted fst-italic bg-light">
                                                                <i class="fa-solid fa-folder-open me-1 opacity-50"></i> Tiada Rekod Kelayakan Ditemui
                                                            </td>
                                                        </tr>
                                                    <?php 
                                                        endif;
                                                    else: 
                                                    ?>
                                                        <tr>
                                                            <td colspan="3" class="py-3 px-3 text-center text-muted fst-italic bg-light">
                                                                <i class="fa-solid fa-folder-open me-1 opacity-50"></i> Tiada Rekod Kelayakan Ditemui
                                                            </td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>  
                                                                        
                                    <!-- ALAMAT EMEL RASMI & ALAMAT -->
                                        <div class="col-6">
                                            <label class="text-muted small fw-bold text-uppercase d-block mb-1"><i class="fa-solid fa-envelope me-1"></i> Alamat Emel Rasmi</label>
                                            <span class="text-dark fw-semibold"><?= htmlspecialchars($profil['email_aktif'] ?? '-'); ?></span>
                                        </div>
                                        <div class="col-6">
                                            <label class="text-muted small fw-bold text-uppercase d-block mb-1"><i class="fa-solid fa-location-dot me-1"></i> Alamat</label>
                                            <span class="text-dark fw-semibold"><?= htmlspecialchars($profil['alamat'] ?? '-'); ?></span>
                                        </div>

                                        <!-- NO. TELEFON (PENGURUS) & NO. TELEFON SYARIKAT -->
                                        <div class="col-6">
                                            <label class="text-muted small fw-bold text-uppercase d-block mb-1"><i class="fa-solid fa-phone me-1"></i> No. Telefon (Pengurus)</label>
                                            <span class="text-dark fw-semibold"><?= htmlspecialchars($profil['no_telefon_pengurus'] ?? '-'); ?></span>
                                        </div>
                                        <div class="col-6">
                                            <label class="text-muted small fw-bold text-uppercase d-block mb-1"><i class="fa-solid fa-building me-1"></i> No. Telefon Syarikat</label>
                                            <span class="text-dark fw-semibold"><?= htmlspecialchars($profil['no_telefon_syarikat'] ?? '-'); ?></span>
                                        </div>

                                        <!-- TARIKH TAMAT SAH & TARIKH MULA SAH -->
                                         <div class="col-6">
                                            <label class="text-muted small fw-bold text-uppercase d-block mb-1"><i class="fa-solid fa-calendar-plus me-1"></i> Tarikh Mula Sah</label>
                                            <span class="text-dark fw-semibold"><?= (!empty($profil['tarikh_mula_aktif']) && $profil['tarikh_mula_aktif'] != '0000-00-00' && isset($profil['status_bayaran_daftar']) && $profil['status_bayaran_daftar'] == 'Sudah Bayar') ? date('d-m-Y', strtotime($profil['tarikh_mula_aktif'])) : 'Belum Aktif'; ?></span>
                                        </div>
                                        <div class="col-6">
                                            <label class="text-muted small fw-bold text-uppercase d-block mb-1"><i class="fa-solid fa-calendar-minus me-1"></i> Tarikh Tamat Sah</label>
                                            <span class="<?= $is_expired ? 'text-danger fw-bold' : 'text-dark fw-semibold'; ?>">
                                                <?= (!empty($profil['tarikh_tamat_aktif']) && $profil['tarikh_tamat_aktif'] != '0000-00-00' && isset($profil['status_bayaran_daftar']) && $profil['status_bayaran_daftar'] == 'Sudah Bayar') ? date('d-m-Y', strtotime($profil['tarikh_tamat_aktif'])) : 'Belum Aktif'; ?>
                                            </span>
                                        </div>

                                    <!-- ========================================== -->
                                    <!-- BAHAGIAN KAWALAN DOKUMEN BIL, RESIT, SIJIL -->
                                    <!-- ========================================== -->
                                    <div class="col-12 mt-4 pt-3 border-top">
                                        <h6 class="fw-bold text-dark mb-3">
                                            <i class="fa-solid fa-folder-open me-2 text-muted"></i> Dokumen Pembayaran & Sijil Pendaftaran
                                            <!-- <span class="badge badge-attention ms-2">
                                                <i class="fa-solid fa-circle-exclamation me-1"></i> PERHATIAN
                                            </span> -->
                                        </h6>
                                        
                                        <?php if ($profil['status_borang'] == 'Lengkap' && $profil['status_bayaran_daftar'] == 'Belum Bayar'): ?>
                                            <div class="highlight-bil-section">
                                                <div class="row g-3">
                                                    <div class="col-12 col-md-6">
                                                        <div class="p-3 rounded-3 d-flex flex-column h-100 justify-content-between" style="background: rgba(255,255,255,0.7); border: 1px solid rgba(34, 197, 94, 0.3); border-radius: 10px;">
                                                            <div>
                                                                <strong class="d-block text-dark small mb-1">
                                                                    <i class="fa-solid fa-file-invoice-dollar text-primary me-1"></i> Bil Pendaftaran (Admin)
                                                                </strong>
                                                                <span class="text-muted small d-block mb-3 fw-semibold">Sila muat turun dan buat pembayaran mengikut jumlah bil yang tertera.</span>
                                                            </div>
                                                            <div>
                                                                <?php if (!empty($profil['fail_bil'])): ?>
                                                                    <a href="./uploads/<?= $profil['fail_bil']; ?>" target="_blank" class="btn btn-sm btn-primary w-100 fw-semibold"><i class="fa-solid fa-download me-1"></i> Buka & Muat Turun Bil</a>
                                                                <?php else: ?>
                                                                    <button class="btn btn-sm btn-secondary w-100" disabled><i class="fa-solid fa-hourglass-half me-1"></i> Menunggu Bil Daripada Admin</button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-12 col-md-6">
                                                        <div class="p-3 rounded-3 d-flex flex-column h-100 justify-content-between" style="background: rgba(255,255,255,0.7); border: 1px solid rgba(34, 197, 94, 0.3); border-radius: 10px;">
                                                            <form action="" method="POST" enctype="multipart/form-data" class="m-0">
                                                                <input type="hidden" name="action_upload_resit" value="1">
                                                                <strong class="d-block text-dark small mb-1"><i class="fa-solid fa-cloud-arrow-up text-success me-1"></i> Muat Naik Resit Pembayaran</strong>
                                                                <span class="text-muted small d-block mb-3 fw-semibold">Sila lampirkan resit transaksi bayaran (Format PDF sahaja).</span>
                                                                <input type="file" name="fail_resit" class="form-control form-control-sm mb-3" accept="application/pdf" required style="border: 1px solid #86efac;">
                                                                <button type="submit" class="btn btn-sm btn-success w-100 fw-semibold mb-2"><i class="fa-solid fa-upload me-1"></i> Hantar Resit</button>
                                                                
                                                                <!-- MENUNJUKKAN FAIL YANG SEDIA ADA DIUPLOAD OLEH USER -->
                                                                <?php if(!empty($profil['fail_resit'])): ?>
                                                                    <div class="mt-1 text-center">
                                                                        <a href="./uploads/<?= htmlspecialchars($profil['fail_resit']); ?>" target="_blank" class="badge bg-danger-subtle text-danger border border-danger-subtle decoration-none py-1.5 px-2 small rounded d-inline-block w-100" style="text-decoration: none;">
                                                                            <i class="fa-solid fa-file-pdf me-1"></i> Lihat Resit Telah Dihantar
                                                                        </a>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                        <?php elseif ($profil['status_borang'] == 'Lengkap' && $profil['status_bayaran_daftar'] == 'Sudah Bayar'): ?>
                                            <div class="p-4 bg-success-subtle border border-success-subtle rounded-3 text-center">
                                                <i class="fa-solid fa-ribbon text-success fs-1 mb-3"></i>
                                                <h6 class="fw-bold text-success-emphasis mb-1">Pendaftaran Syarikat Anda Telah Selesai & Aktif!</h6>
                                                <p class="text-success-emphasis small mb-4">Sila muat turun Sijil Pendaftaran Rasmi anda di bawah.</p>
                                                
                                                <?php if (!empty($profil['fail_sijil'])): ?>
                                                    <a href="./uploads/<?= $profil['fail_sijil']; ?>" target="_blank" class="btn btn-success fw-bold px-4 py-2" style="border-radius: 8px;"><i class="fa-solid fa-file-arrow-down me-2"></i> Muat Turun Sijil Pendaftaran</a>
                                                <?php else: ?>
                                                    <button class="btn btn-secondary px-4 py-2" disabled style="border-radius: 8px;"><i class="fa-solid fa-hourglass-half me-2"></i> Sijil Pendaftaran Sedang Dijana Oleh Admin</button>
                                                <?php endif; ?>
                                            </div>

                                        <?php else: ?>
                                            <div class="alert alert-secondary mb-0 small py-3" role="alert">
                                                <i class="fa-solid fa-circle-info me-2"></i> Bahagian bil, resit, dan sijil pendaftaran hanya akan dipaparkan sebaik sahaja semakan status dokumen anda disahkan <strong>Lengkap</strong> oleh pihak Admin.
                                            </div>
                                        <?php endif; ?>

                                    </div>
                                    <!-- ========================================== -->
                                </div>

                            </div>
                        </div>

                        <div class="col-12 col-lg-4 d-flex flex-column gap-4">
                            <!-- ALIRAN FASA -->
                            <div class="p-4 custom-premium-card">
                                <h5 class="fw-bold text-dark mb-4 pb-3 border-bottom">
                                    <i class="fa-solid fa-route text-muted me-2"></i> Aliran Fasa Tindakan / Bil Daftar Undi
                                </h5>
                                <div class="timeline-steps">
                                    <div class="timeline-item done">
                                        <div class="timeline-icon"></div>
                                        <h6 class="fw-bold text-dark mb-0">Fasa 1: Serah Borang</h6>
                                        <p class="text-muted small mb-0">Data profil syarikat berjaya dimasukkan ke pangkalan data.</p>
                                    </div>
                                    <div class="timeline-item <?= ($status == 'Lengkap' && $is_paid) ? 'done' : ''; ?>">
                                        <div class="timeline-icon"></div>
                                        <h6 class="fw-bold text-dark mb-0">Semakan & Bayaran Nyata</h6>
                                        <p class="text-muted small mb-0">Dokumen disahkan lengkap dan yuran RM52 telah disahkan.</p>
                                    </div>
                                   <div class="timeline-item <?= $sudah_daftar_undi ? 'done' : ''; ?>">
                                        <div class="timeline-icon"></div>
                                        <h6 class="fw-bold text-dark mb-0">Fasa 2: Daftar Kerja Undi</h6>
                                        <?php if ($sudah_daftar_undi): 
                                            $status_val = $data_undi['status_undi'] ?? ($data_undi['status_layak'] ?? 'Pending');
                                            $is_tidak_layak = ($status_val == 'Tidak Layak');
                                            
                                            // Ambil alasan tolak / tidak layak dari database
                                            $alasan_undi = $data_undi['alasan_tolak'] ?? ($data_undi['alasan_tidak_layak'] ?? '');
                                            
                                            // Tetapkan warna teks, ikon & badge secara dinamik
                                            $warna_teks = $is_tidak_layak ? 'text-danger' : 'text-success';
                                            $ikon_status = $is_tidak_layak ? 'fa-circle-xmark' : 'fa-circle-check';
                                            $warna_badge = $is_tidak_layak ? 'bg-danger' : ($status_val == 'Layak' ? 'bg-success' : 'bg-secondary');
                                        ?>
                                            <!-- 1. BAHAGIAN ATAS: STATUS & BADGE (TEXT STATUS DI ATAS, BADGE DI BAWAH) -->
                                            <p class="<?= $warna_teks; ?> small mb-0 fw-semibold">
                                                <i class="fa-solid <?= $ikon_status; ?> me-1"></i> 
                                                <?= $is_tidak_layak ? 'Status Pendaftaran Undi:' : 'Berjaya Daftar Undi! Status Kelayakan:'; ?>
                                                
                                                <span class="d-block mt-1">
                                                    <!-- Lencana Status Undi (Contoh: Layak) -->
                                                    <span class="badge <?= $warna_badge; ?> fw-bold py-1 px-2 d-inline-flex align-items-center" style="font-size: 0.80rem; line-height: 1.2;">
                                                        <?= htmlspecialchars($status_val); ?>
                                                    </span>

                                                    <!-- PAPARAN SUDAH BAYAR DI SEBELAH DENGAN DESIGN SAMA (GREEN BADGE) -->
                                                    <?php if (isset($data_undi['status_pembayaran']) && strtolower($data_undi['status_pembayaran']) == 'sudah bayar'): ?>
                                                        <span class="badge bg-success fw-bold py-1 px-2 d-inline-flex align-items-center ms-1" style="font-size: 0.80rem; line-height: 1.2;">
                                                            Sudah Bayar
                                                        </span>
                                                    <?php endif; ?>
                                                </span>
                                            </p>

                                            <!-- 2. BAHAGIAN TENGAH: ALASAN TOLAK / TIDAK LAYAK -->
                                            <?php if ($is_tidak_layak && !empty($alasan_undi)): ?>
                                                <div class="text-danger fw-normal mt-2 p-2 bg-danger-subtle rounded border border-danger-subtle" style="font-size: 0.8rem;">
                                                    <strong><i class="fa-solid fa-circle-exclamation me-1"></i> Alasan Tolak:</strong>
                                                    <div class="mt-1" style="white-space: pre-line;"><?= htmlspecialchars(trim($alasan_undi)); ?></div>
                                                </div>
                                            <?php endif; ?>

                                            <!-- 3. BAHAGIAN BAWAH: BUTTON KEMASKINI FASA 2 (CENTERED) -->
                                            <?php if ($is_tidak_layak): ?>
                                                <div class="mt-2 text-center">
                                                    <a href="kontraktor_daftar_undi.php" class="btn btn-danger btn-sm py-1 px-2 fw-bold d-inline-flex align-items-center" style="font-size: 0.80rem; line-height: 1.2; text-decoration: none;">
                                                        <i class="fa-solid fa-pen-to-square me-1"></i> Kemaskini Fasa 2
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- ================================================================= -->
                                            <!-- BAHAGIAN BIL & UPLOAD RESIT UNTUK FASA 2 KERJA UNDI JIKA LAYAK -->
                                            <!-- ================================================================= -->
                                            <?php 
                                                $status_layak_undi = $data_undi['status_undi'] ?? ($data_undi['status_layak'] ?? '');
                                                $fail_bil_undi = $data_undi['fail_bil_undi'] ?? ($data_undi['fail_bil'] ?? '');
                                                $fail_resit_undi = $data_undi['fail_resit_undi'] ?? ($data_undi['fail_resit'] ?? '');
                                            ?>
                                            <?php 
                                            $status_bayar_undi = $data_undi['status_pembayaran'] ?? 'belum bayar';
                                            ?>
                                            <?php if ($status_layak_undi == 'Layak' && !empty($fail_bil_undi) && strtolower($status_bayar_undi) != 'sudah bayar'): ?>
                                                <div class="mt-3 p-3 rounded-3 border" style="background: linear-gradient(135deg, #bbf7d0 0%, #86efac 50%, #4ade80 100%) !important; border: 3px solid #22c55e !important; box-shadow: 0 6px 20px rgba(34, 197, 94, 0.35) !important; animation: pulse-green 2s ease-in-out 3;">
                                                    <strong class="d-block small mb-2" style="color: #14532d !important;"><i class="fa-solid fa-file-invoice-dollar text-primary me-1"></i> Bil Kerja Undi (Fasa 2)</strong>
                                                    <a href="./uploads/<?= htmlspecialchars($fail_bil_undi); ?>" target="_blank" class="btn btn-sm w-100 fw-semibold mb-3" style="background: #16a34a !important; border-color: #16a34a !important; color: #ffffff !important;">
                                                        <i class="fa-solid fa-download me-1"></i> Muat Turun Bil Undi
                                                    </a>

                                                    <form action="" method="POST" enctype="multipart/form-data" class="m-0">
                                                        <input type="hidden" name="action_upload_resit_undi" value="1">
                                                        <strong class="d-block small mb-1" style="color: #14532d !important;"><i class="fa-solid fa-cloud-arrow-up text-success me-1"></i> Muat Naik Resit Undi</strong>
                                                        <span class="small d-block mb-2 fw-bold " style="font-size: 0.75rem; color: #ee0d0d !important;">Lampirkan resit bayaran (PDF sahaja).</span>
                                                        <input type="file" name="fail_resit_undi" class="form-control form-control-sm mb-2" accept="application/pdf" required style="border: 1px solid #86efac;">
                                                        <button type="submit" class="btn btn-sm w-100 fw-semibold mb-1" style="background: #16a34a !important; border-color: #16a34a !important; color: #ffffff !important;"><i class="fa-solid fa-upload me-1"></i> Hantar Resit Undi</button>
                                                    </form>

                                                    <?php if (!empty($fail_resit_undi)): ?>
                                                        <div class="mt-2 text-center">
                                                            <a href="./uploads/<?= htmlspecialchars($fail_resit_undi); ?>" target="_blank" class="badge bg-danger-subtle text-danger border border-danger-subtle decoration-none py-1.5 px-2 small rounded d-inline-block w-100" style="text-decoration: none;">
                                                                <i class="fa-solid fa-file-pdf me-1"></i> Lihat Resit Undi Dihantar
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <!-- ================================================================= -->

                                        <?php else: ?>
                                            <p class="text-muted small mb-0">Buka menu Fasa 2 di sebelah kiri untuk mula memohon kerja undi.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- CARD BANTUAN & SOKONGAN (REKA BENTUK PROFESSIONAL, KEMAS & ULTRA MODERN) -->
                            <div class="p-4 custom-premium-card support-card">
                                <div class="d-flex align-items-center justify-content-between mb-3 pb-3 border-bottom">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="support-header-icon">
                                            <i class="fa-solid fa-headset"></i>
                                        </div>
                                        <div>
                                            <h5 class="fw-bold text-dark mb-0 fs-6">Bantuan & Sokongan</h5>
                                            <span class="text-muted" style="font-size: 0.72rem;">Pusat Khidmat Pelanggan MDBG</span>
                                        </div>
                                    </div>
                                </div>

                                <p class="text-muted small mb-3" style="font-size: 0.8rem; line-height: 1.5;">
                                    Memerlukan bantuan teknikal mengenai sistem pendaftaran <strong>Fasa 1</strong> atau <strong>Fasa 2</strong>? Hubungi pegawai kami melalui talian di bawah:
                                </p>

                                <div class="d-flex flex-column gap-2.5">
                                    <!-- WHATSAPP / TALIAN KHIDMAT -->
                                    <a href="https://wa.me/60195551234" target="_blank" class="support-item">
                                        <div class="support-item-icon support-icon-wa">
                                            <i class="fa-brands fa-whatsapp"></i>
                                        </div>
                                        <div class="flex-grow-1 min-w-0">
                                            <span class="support-label">WhatsApp / Talian Khidmat</span>
                                            <p class="support-val">+60 19-555 1234</p>
                                        </div>
                                        <i class="fa-solid fa-chevron-right text-muted fs-7 ms-auto"></i>
                                    </a>

                                    <!-- WAKTU OPERASI KHIDMAT -->
                                    <div class="support-item" style="cursor: default;">
                                        <div class="support-item-icon support-icon-clock">
                                            <i class="fa-regular fa-clock"></i>
                                        </div>
                                        <div class="flex-grow-1 min-w-0">
                                            <span class="support-label">Waktu Operasi Khidmat</span>
                                            <p class="support-val text-truncate">Isnin – Jumaat (8.00 pagi – 5.00 ptg)</p>
                                        </div>
                                    </div>

                                   <!-- EMEL BANTUAN RASMI -->
                                    <a href="https://mail.google.com/mail/?view=cm&fs=1&to=pendaftaran@mdbg.gov.my" target="_blank" class="support-item">
                                        <div class="support-item-icon support-icon-email">
                                            <i class="fa-regular fa-envelope"></i>
                                        </div>
                                        <div class="flex-grow-1 min-w-0">
                                            <span class="support-label">Emel Bantuan Rasmi</span>
                                            <p class="support-val text-truncate">pendaftaran@mdbg.gov.my</p>
                                        </div>
                                        <i class="fa-solid fa-chevron-right text-muted fs-7 ms-auto"></i>
                                    </a>
                                </div>
                            </div>
        
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- STATUS PENDAFTARAN KERJA UNDI (DIPINDAHKAN KE BAWAH ROW 1) -->
                    <!-- ========================================== -->
                    <?php 
                        if ($sudah_daftar_undi) {
                            $status_dokumen_undi = !empty($data_undi['status_borang']) ? $data_undi['status_borang'] : 'Sedang Disemak';
                        } else {
                            $status_dokumen_undi = 'Belum Daftar';
                        }

                        if ($status_dokumen_undi == 'Lengkap'): 
                    ?>
                        <div class="mb-4 mt-2">
                            <h4 class="fw-bold text-dark mb-1">Status Pendaftaran Kerja Undi Anda</h4>
                            <p class="text-muted small mb-0">Semakan status permohonan fasa 2 untuk kelayakan cabutan undi kerja.</p>
                        </div>

                        <div class="row g-3 g-md-4 mb-4 mb-md-5 justify-content-center">
                            <div class="col-12 col-md-4">
                                <div class="p-4 custom-premium-card d-flex align-items-center h-100">
                                    <?php 
                                        if ($status_dokumen_undi == 'Lengkap') {
                                            $bg_icon_u1 = '#dcfce7'; $color_icon_u1 = '#16a34a'; $fa_icon_u1 = 'fa-circle-check';
                                        } elseif ($status_dokumen_undi == 'Pending' || $status_dokumen_undi == 'Sedang Disemak') {
                                            $bg_icon_u1 = '#fef3c7'; $color_icon_u1 = '#d97706'; $fa_icon_u1 = 'fa-clock';
                                        } else {
                                            $bg_icon_u1 = '#fee2e2'; $color_icon_u1 = '#dc2626'; $fa_icon_u1 = 'fa-circle-xmark';
                                        }
                                    ?>
                                    <div class="d-flex align-items-center justify-content-center rounded-3 me-3 flex-shrink-0" style="width: 56px; height: 56px; background-color: <?= $bg_icon_u1 ?>; color: <?= $color_icon_u1 ?>;">
                                        <i class="fa-solid <?= $fa_icon_u1 ?> fs-4"></i>
                                    </div>
                                    <div>
                                        <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">Semakan Dokumen</div>
                                        <div class="fw-bold text-dark fs-5"><?= htmlspecialchars($status_dokumen_undi); ?></div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-md-4">
                                <div class="p-4 custom-premium-card d-flex align-items-center h-100">
                                    <?php 
                                        $bayaran_undi = $data_undi['status_bayaran'] ?? 'Belum Bayar';
                                        $is_paid_undi = ($bayaran_undi == 'Sudah Bayar');
                                        $bg_pay_u2 = $is_paid_undi ? '#dcfce7' : '#fee2e2';
                                        $color_pay_u2 = $is_paid_undi ? '#16a34a' : '#dc2626';
                                        $icon_pay_u2 = $is_paid_undi ? 'fa-credit-card' : 'fa-money-bill-wave';
                                    ?>
                                    <div class="d-flex align-items-center justify-content-center rounded-3 me-3 flex-shrink-0" style="width: 56px; height: 56px; background-color: <?= $bg_pay_u2 ?>; color: <?= $color_pay_u2 ?>;">
                                        <i class="fa-solid <?= $icon_pay_u2 ?> fs-4"></i>
                                    </div>
                                    <div>
                                        <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">Status Bayaran (RM10)</div>
                                        <div class="fw-bold text-dark fs-5"><?= htmlspecialchars($bayaran_undi); ?></div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-md-4">
                                <div class="p-4 custom-premium-card d-flex align-items-center h-100">
                                    <?php 
                                        $status_undi_semak = $data_undi['status_undi'] ?? ($data_undi['status_layak'] ?? 'Belum Layak');
                                        if ($status_undi_semak == 'Layak') {
                                            $bg_icon_u3 = '#dcfce7'; $color_icon_u3 = '#16a34a'; $fa_icon_u3 = 'fa-check-to-slot';
                                        } elseif ($status_undi_semak == 'Pending') {
                                            $bg_icon_u3 = '#fef3c7'; $color_icon_u3 = '#d97706'; $fa_icon_u3 = 'fa-clock';
                                        } else {
                                            $bg_icon_u3 = '#fee2e2'; $color_icon_u3 = '#dc2626'; $fa_icon_u3 = 'fa-rectangle-xmark';
                                        }
                                    ?>
                                    <div class="d-flex align-items-center justify-content-center rounded-3 me-3 flex-shrink-0" style="width: 56px; height: 56px; background-color: <?= $bg_icon_u3 ?>; color: <?= $color_icon_u3 ?>;">
                                        <i class="fa-solid <?= $fa_icon_u3 ?> fs-4"></i>
                                    </div>
                                    <div>
                                        <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">Status Kelayakan Undi</div>
                                        <div class="fw-bold text-dark fs-5"><?= htmlspecialchars($status_undi_semak); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>

                <?php endif; // PENUTUP SYARAT SESI INDIVIDU ?>

            </div>
        </div>
    </div>

<!-- MODAL FLOATING CENTER KEMASKINI PROFIL -->
<div class="modal fade" id="modalKemaskiniProfil" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="modalKemaskiniProfilLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="border-radius: 16px; overflow: hidden; max-height: 90vh; display: flex; flex-direction: column;">
            
            <div class="modal-header bg-dark text-white py-3 px-4" style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                <h5 class="modal-title fw-bold fs-6 d-flex align-items-center" id="modalKemaskiniProfilLabel">
                    <i class="fa-solid fa-pen-to-square me-2 text-warning"></i> Kemaskini Maklumat Profil Syarikat
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form action="" method="POST" enctype="multipart/form-data" class="m-0 d-flex flex-column flex-grow-1" style="overflow: hidden;">
                <input type="hidden" name="action_kemaskini_profil" value="1">
                
                <div class="modal-body p-4" style="overflow-y: auto; flex-grow: 1;">
                    
                    <div class="alert alert-warning border-0 small py-3 px-3 mb-4 d-flex align-items-center rounded-3" role="alert" style="background-color: #fff3cd; color: #664d03;">
                        <i class="fa-solid fa-triangle-exclamation fs-5 me-3"></i>
                        <div>
                            <strong>Nota Penting:</strong> Data sedia ada dipaparkan di bawah. Biarkan ruangan fail kosong jika tiada sebarang perubahan pada dokumen lampiran.
                        </div>
                    </div>
                    
                    <!-- SEKSYEN 1: MAKLUMAT ASAS -->
                    <div class="mb-4">
                        <div class="d-flex align-items-center mb-3">
                            <span class="bg-light text-dark rounded-circle p-2 d-inline-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                <i class="fa-solid fa-file-invoice small"></i>
                            </span>
                            <h6 class="fw-bold text-dark m-0">Maklumat Asas & Hubungan</h6>
                        </div>
                        <div class="card border-0 bg-light p-3 rounded-3">
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold text-muted mb-1">Nama Syarikat</label>
                                    <input type="text" name="nama_syarikat" class="form-control form-control-sm border-0 shadow-sm px-3 py-2" value="<?= htmlspecialchars($profil['nama_syarikat'] ?? ''); ?>" required oninput="this.value = this.value.toUpperCase()" style="border-radius: 8px; text-transform: uppercase;">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold text-muted mb-1">No. Pendaftaran (SSM)</label>
                                    <input type="text" name="no_pendaftaran" class="form-control form-control-sm border-0 shadow-sm px-3 py-2" value="<?= htmlspecialchars($profil['no_pendaftaran'] ?? ''); ?>" required maxlength="10" pattern="[A-Za-z0-9]{10}" title="Sila masukkan 10 digit/aksara alfanumerik tanpa ruang atau simbol" oninput="formatSSMInput(this)" style="border-radius: 8px; text-transform: uppercase;">
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label fw-bold"><i class="fa-solid fa-location-dot me-1"></i> Alamat Syarikat</label>
                                    <textarea name="alamat" class="form-control text-uppercase" rows="3" placeholder="MASUKKAN ALAMAT LENGKAP SYARIKAT"><?= htmlspecialchars($profil['alamat'] ?? ''); ?></textarea>
                                </div>
                                <!-- BAHAGIAN JADUAL DINAMIK GRED CIDB / KEWANGAN -->
                                <div class="col-12 mb-3">
                                    <label class="form-label fw-bold text-dark">
                                        <i class="fa-solid fa-award me-1"></i> Gred Kelayakan CIDB / Kewangan
                                    </label>
                                    
                                    <div class="table-responsive border rounded-3 p-2 bg-light">
                                        <table class="table table-bordered align-middle mb-2 bg-white" id="jadualGredModal">
                                            <thead class="table-secondary small text-uppercase fw-bold">
                                                <tr>
                                                    <th style="width: 25%;">Gred</th>
                                                    <th style="width: 25%;">Kategori</th>
                                                    <th style="width: 35%;">Pengkhususan</th>
                                                    <th style="width: 15%; text-align: center;">Tindakan</th>
                                                </tr>
                                            </thead>
                                            <tbody id="containerGredModal">
                                                <!-- Baris textfield dinamik akan dimasukkan melalui JavaScript -->
                                            </tbody>
                                        </table>
                                        
                                        <button type="button" class="btn btn-sm btn-outline-primary fw-bold rounded-2" onclick="tambahBarisGredModal()">
                                            <i class="fa-solid fa-plus me-1"></i> Tambah Kelayakan
                                        </button>
                                    </div>

                                    <!-- Hidden Input untuk menyimpan data string gabungan sebelum hantar borang -->
                                    <input type="hidden" name="gred_cidb_kewangan" id="gred_cidb_kewangan_hidden">
                                </div>

                                <!-- bahagian no tel dan email -->
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold text-muted mb-1">No. Telefon Syarikat</label>
                                    <input type="text" name="no_telefon_syarikat" class="form-control form-control-sm border-0 shadow-sm px-3 py-2" value="<?= htmlspecialchars($profil['no_telefon_syarikat'] ?? ''); ?>" required pattern="[0-9]+" title="Sila masukkan nombor sahaja" oninput="formatNumericOnly(this)" style="border-radius: 8px;">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold text-muted mb-1">No. Telefon Pengurus</label>
                                    <input type="text" name="no_telefon_pengurus" class="form-control form-control-sm border-0 shadow-sm px-3 py-2" value="<?= htmlspecialchars($profil['no_telefon_pengurus'] ?? ''); ?>" required pattern="[0-9]+" title="Sila masukkan nombor sahaja" oninput="formatNumericOnly(this)" style="border-radius: 8px;">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold text-muted mb-1">Alamat Emel Aktif</label>
                                    <input type="email" name="email_aktif" class="form-control form-control-sm border-0 shadow-sm px-3 py-2" value="<?= htmlspecialchars($profil['email_aktif'] ?? ''); ?>" required style="border-radius: 8px;">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- SEKSYEN 2: DOKUMEN LAMPIRAN -->
                    <div>
                        <div class="d-flex align-items-center mb-3">
                            <span class="bg-light text-dark rounded-circle p-2 d-inline-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                <i class="fa-solid fa-cloud-arrow-up small"></i>
                            </span>
                            <h6 class="fw-bold text-dark m-0">Muat Naik Dokumen Lampiran <span class="text-danger small fw-normal">(Format PDF Sahaja)</span></h6>
                        </div>
                        
                        <div class="row g-3">
                            
                            <div class="col-12 col-md-6">
                                <div class="card border border-light-subtle shadow-sm p-3 rounded-3 bg-white h-100 d-flex flex-column justify-content-between">
                                    <div>
                                        <label class="form-label small fw-bold text-dark mb-2"><i class="fa-solid fa-certificate text-muted me-1"></i> Sijil Suruhanjaya Syarikat Malaysia (SSM)</label>
                                        <input type="file" name="fail_ssm" class="form-control form-control-sm mb-2" accept="application/pdf" style="border-radius: 6px;">
                                        
                                        <div class="p-2 bg-light rounded border mt-2" style="font-size: 0.8rem;">
                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <span class="text-muted d-block fw-bold mb-1" style="font-size: 0.7rem; text-transform: uppercase;">Tarikh Mula</span>
                                                    <input type="date" name="tarikh_mula_ssm" id="tarikh_mula_ssm" class="form-control form-control-sm" value="<?= htmlspecialchars($profil['tarikh_mula_ssm'] ?? ''); ?>" style="padding: 3px 6px; font-size: 0.8rem;" onchange="semakValidationTarikh('tarikh_mula_ssm', 'tarikh_tamat_ssm', 'SSM')">
                                                </div>
                                                <div class="col-6">
                                                    <span class="text-muted d-block fw-bold mb-1" style="font-size: 0.7rem; text-transform: uppercase;">Tarikh Tamat</span>
                                                    <input type="date" name="tarikh_tamat_ssm" id="tarikh_tamat_ssm" class="form-control form-control-sm" value="<?= htmlspecialchars($profil['tarikh_tamat_ssm'] ?? ''); ?>" style="padding: 3px 6px; font-size: 0.8rem;" onchange="semakValidationTarikh('tarikh_mula_ssm', 'tarikh_tamat_ssm', 'SSM')">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <?php if(!empty($profil['fail_ssm'])): ?>
                                            <a href="./uploads/<?= $profil['fail_ssm']; ?>" target="_blank" class="badge bg-danger-subtle text-danger border border-danger-subtle py-1.5 px-2 small rounded"><i class="fa-solid fa-file-pdf me-1"></i> Lihat Dokumen Sedia Ada</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-12 col-md-6">
                                <div class="card border border-light-subtle shadow-sm p-3 rounded-3 bg-white h-100 d-flex flex-column justify-content-between">
                                    <div>
                                        <label class="form-label small fw-bold text-dark mb-2"><i class="fa-solid fa-receipt text-muted me-1"></i> Sijil Pematuhan Cukai (TCC)</label>
                                        <input type="file" name="fail_tcc" class="form-control form-control-sm mb-2" accept="application/pdf" style="border-radius: 6px;">
                                        
                                        <div class="p-2 bg-light rounded border mt-2" style="font-size: 0.8rem;">
                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <span class="text-muted d-block fw-bold mb-1" style="font-size: 0.7rem; text-transform: uppercase;">Tarikh Mula</span>
                                                    <input type="date" name="tarikh_mula_tcc" id="tarikh_mula_tcc" class="form-control form-control-sm" value="<?= htmlspecialchars($profil['tarikh_mula_tcc'] ?? ''); ?>" style="padding: 3px 6px; font-size: 0.8rem;" onchange="semakValidationTarikh('tarikh_mula_tcc', 'tarikh_tamat_tcc', 'TCC')">
                                                </div>
                                                <div class="col-6">
                                                    <span class="text-muted d-block fw-bold mb-1" style="font-size: 0.7rem; text-transform: uppercase;">Tarikh Tamat</span>
                                                    <input type="date" name="tarikh_tamat_tcc" id="tarikh_tamat_tcc" class="form-control form-control-sm" value="<?= htmlspecialchars($profil['tarikh_tamat_tcc'] ?? ''); ?>" style="padding: 3px 6px; font-size: 0.8rem;" onchange="semakValidationTarikh('tarikh_mula_tcc', 'tarikh_tamat_tcc', 'TCC')">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <?php if(!empty($profil['fail_tcc'])): ?>
                                            <a href="./uploads/<?= $profil['fail_tcc']; ?>" target="_blank" class="badge bg-danger-subtle text-danger border border-danger-subtle py-1.5 px-2 small rounded"><i class="fa-solid fa-file-pdf me-1"></i> Lihat Dokumen Sedia Ada</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-12 col-md-6">
                                <div class="card border border-light-subtle shadow-sm p-3 rounded-3 bg-white h-100 d-flex flex-column justify-content-between">
                                    <div>
                                        <label class="form-label small fw-bold text-dark mb-2"><i class="fa-solid fa-star text-muted me-1"></i> PKK (Sijil Taraf Bumiputera)</label>
                                        <input type="file" name="fail_pkk" class="form-control form-control-sm mb-2" accept="application/pdf" style="border-radius: 6px;">
                                        
                                        <div class="p-2 bg-light rounded border mt-2" style="font-size: 0.8rem;">
                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <span class="text-muted d-block fw-bold mb-1" style="font-size: 0.7rem; text-transform: uppercase;">Tarikh Mula</span>
                                                    <input type="date" name="tarikh_mula_pkk" id="tarikh_mula_pkk" class="form-control form-control-sm" value="<?= htmlspecialchars($profil['tarikh_mula_pkk'] ?? ''); ?>" style="padding: 3px 6px; font-size: 0.8rem;" onchange="semakValidationTarikh('tarikh_mula_pkk', 'tarikh_tamat_pkk', 'PKK')">
                                                </div>
                                                <div class="col-6">
                                                    <span class="text-muted d-block fw-bold mb-1" style="font-size: 0.7rem; text-transform: uppercase;">Tarikh Tamat</span>
                                                    <input type="date" name="tarikh_tamat_pkk" id="tarikh_tamat_pkk" class="form-control form-control-sm" value="<?= htmlspecialchars($profil['tarikh_tamat_pkk'] ?? ''); ?>" style="padding: 3px 6px; font-size: 0.8rem;" onchange="semakValidationTarikh('tarikh_mula_pkk', 'tarikh_tamat_pkk', 'PKK')">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <?php if(!empty($profil['fail_pkk'])): ?>
                                            <a href="./uploads/<?= $profil['fail_pkk']; ?>" target="_blank" class="badge bg-danger-subtle text-danger border border-danger-subtle py-1.5 px-2 small rounded"><i class="fa-solid fa-file-pdf me-1"></i> Lihat Dokumen Sedia Ada</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-12 col-md-6">
                                <div class="card border border-light-subtle shadow-sm p-3 rounded-3 bg-white h-100 d-flex flex-column justify-content-between">
                                    <div>
                                        <label class="form-label small fw-bold text-dark mb-2"><i class="fa-solid fa-hard-hat text-muted me-1"></i> CIDB (Perakuan Pendaftaran)</label>
                                        <input type="file" name="fail_cidb_perakuan" class="form-control form-control-sm mb-2" accept="application/pdf" style="border-radius: 6px;">
                                        
                                        <div class="p-2 bg-light rounded border mt-2" style="font-size: 0.8rem;">
                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <span class="text-muted d-block fw-bold mb-1" style="font-size: 0.7rem; text-transform: uppercase;">Tarikh Mula</span>
                                                    <input type="date" name="tarikh_mula_cidb_perakuan" id="tarikh_mula_cidb_perakuan" class="form-control form-control-sm" value="<?= htmlspecialchars($profil['tarikh_mula_cidb_perakuan'] ?? ''); ?>" style="padding: 3px 6px; font-size: 0.8rem;" onchange="semakValidationTarikh('tarikh_mula_cidb_perakuan', 'tarikh_tamat_cidb_perakuan', 'CIDB Perakuan')">
                                                </div>
                                                <div class="col-6">
                                                    <span class="text-muted d-block fw-bold mb-1" style="font-size: 0.7rem; text-transform: uppercase;">Tarikh Tamat</span>
                                                    <input type="date" name="tarikh_tamat_cidb_perakuan" id="tarikh_tamat_cidb_perakuan" class="form-control form-control-sm" value="<?= htmlspecialchars($profil['tarikh_tamat_cidb_perakuan'] ?? ''); ?>" style="padding: 3px 6px; font-size: 0.8rem;" onchange="semakValidationTarikh('tarikh_mula_cidb_perakuan', 'tarikh_tamat_cidb_perakuan', 'CIDB Perakuan')">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <?php if(!empty($profil['fail_cidb_perakuan'])): ?>
                                            <a href="./uploads/<?= $profil['fail_cidb_perakuan']; ?>" target="_blank" class="badge bg-danger-subtle text-danger border border-danger-subtle py-1.5 px-2 small rounded"><i class="fa-solid fa-file-pdf me-1"></i> Lihat Dokumen Sedia Ada</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-12 col-md-6">
                                <div class="card border border-light-subtle shadow-sm p-3 rounded-3 bg-white h-100 d-flex flex-column justify-content-between">
                                    <div>
                                        <label class="form-label small fw-bold text-dark mb-2"><i class="fa-solid fa-briefcase text-muted me-1"></i> CIDB (Sijil Perolehan Kerja Kerajaan)</label>
                                        <input type="file" name="fail_cidb_perolehan" class="form-control form-control-sm mb-2" accept="application/pdf" style="border-radius: 6px;">
                                        
                                        <div class="p-2 bg-light rounded border mt-2" style="font-size: 0.8rem;">
                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <span class="text-muted d-block fw-bold mb-1" style="font-size: 0.7rem; text-transform: uppercase;">Tarikh Mula</span>
                                                    <input type="date" name="tarikh_mula_cidb_perolehan" id="tarikh_mula_cidb_perolehan" class="form-control form-control-sm" value="<?= htmlspecialchars($profil['tarikh_mula_cidb_perolehan'] ?? ''); ?>" style="padding: 3px 6px; font-size: 0.8rem;" onchange="semakValidationTarikh('tarikh_mula_cidb_perolehan', 'tarikh_tamat_cidb_perolehan', 'CIDB Perolehan')">
                                                </div>
                                                <div class="col-6">
                                                    <span class="text-muted d-block fw-bold mb-1" style="font-size: 0.7rem; text-transform: uppercase;">Tarikh Tamat</span>
                                                    <input type="date" name="tarikh_tamat_cidb_perolehan" id="tarikh_tamat_cidb_perolehan" class="form-control form-control-sm" value="<?= htmlspecialchars($profil['tarikh_tamat_cidb_perolehan'] ?? ''); ?>" style="padding: 3px 6px; font-size: 0.8rem;" onchange="semakValidationTarikh('tarikh_mula_cidb_perolehan', 'tarikh_tamat_cidb_perolehan', 'CIDB Perolehan')">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <?php if(!empty($profil['fail_cidb_perolehan'])): ?>
                                            <a href="./uploads/<?= $profil['fail_cidb_perolehan']; ?>" target="_blank" class="badge bg-danger-subtle text-danger border border-danger-subtle py-1.5 px-2 small rounded"><i class="fa-solid fa-file-pdf me-1"></i> Lihat Dokumen Sedia Ada</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-12 col-md-6">
                                <div class="card border border-light-subtle shadow-sm p-3 rounded-3 bg-white h-100 d-flex flex-column justify-content-between">
                                    <div>
                                        <label class="form-label small fw-bold text-dark mb-2"><i class="fa-solid fa-building-columns text-muted me-1"></i> Sijil Kementerian Kewangan (MOF)</label>
                                        <input type="file" name="fail_mof" class="form-control form-control-sm mb-2" accept="application/pdf" style="border-radius: 6px;">
                                        
                                        <div class="alert alert-light border border-secondary-subtle small py-2 px-3 text-muted mt-2 mb-0" style="font-size:0.75rem; border-radius: 8px;">
                                            <i class="fa-solid fa-circle-info me-1 text-secondary"></i> Tiada kemasukan tarikh diperlukan bagi sijil Kementerian Kewangan.
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <?php if(!empty($profil['fail_mof'])): ?>
                                            <a href="./uploads/<?= $profil['fail_mof']; ?>" target="_blank" class="badge bg-danger-subtle text-danger border border-danger-subtle py-1.5 px-2 small rounded"><i class="fa-solid fa-file-pdf me-1"></i> Lihat Dokumen Sedia Ada</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                </div>
                
                <div class="modal-footer bg-light border-top py-3 px-4 justify-content-end">
                    <button type="button" class="btn btn-sm btn-secondary text-white fw-bold px-4 py-2" data-bs-dismiss="modal" style="border-radius: 8px;">Batal</button>
                    <button type="submit" class="btn btn-sm btn-dark fw-bold px-4 py-2" style="border-radius: 8px;">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL FLOATING PERMOHONAN KEBENARAN KEMASKINI -->
<div class="modal fade" id="modalMintaKemaskini" tabindex="-1" aria-labelledby="modalMintaKemaskiniLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header bg-dark text-white py-3 px-4">
                <h5 class="modal-title fs-6 fw-bold" id="modalMintaKemaskiniLabel">
                    <i class="fa-solid fa-paper-plane me-2 text-warning"></i> Permohonan Kebenaran Kemaskini
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST" class="m-0">
                <input type="hidden" name="action_minta_kemaskini" value="1">
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">
                        Anda perlu membuat permohonan terlebih dahulu kepada Admin. Sila berikan sebab munasabah mengapa anda perlu mengemaskini profil syarikat.
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark">Sebab Untuk Kemaskini <span class="text-danger">*</span></label>
                        <textarea name="sebab_kemaskini" class="form-control" rows="4" placeholder="Contoh: Pembaharuan tarikh tamat sijil SSM / Kemaskini nombor telefon syarikat..." required style="border-radius: 8px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-4">
                    <button type="button" class="btn btn-secondary btn-sm rounded-2" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-bold rounded-2 px-3">
                        <i class="fa-solid fa-paper-plane me-1"></i> Hantar Ke Admin
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- ELEMEN AI CHATBOT PROFESIONAL & ADVANCE -->
<!-- ========================================== -->
 
<button class="ai-chat-btn" id="aiChatBtn" onclick="toggleAiChat()" title="Bantuan AI MDBG">
    <i class="fa-solid fa-robot"></i>
</button>

<div class="ai-chat-box" id="aiChatBox">
    <div class="ai-chat-header">
        <div class="d-flex align-items-center gap-3">
            <div class="ai-avatar-icon">
                <i class="fa-solid fa-robot"></i>
            </div>
            <div>
                <strong style="font-size: 0.95rem; letter-spacing: -0.2px;" class="d-block text-white">AI SUPPORT</strong>
                <div class="d-flex align-items-center">
                    <span class="ai-online-status"></span>
                    <span style="font-size: 0.72rem;" class="text-light opacity-75">Sistem Pendaftaran & Undi</span>
                </div>
            </div>
        </div>
        <button type="button" class="btn-close btn-close-white opacity-75" onclick="toggleAiChat()"></button>
    </div>
    <div class="ai-chat-body" id="aiChatBody">
        <div class="ai-message bot">
            Selamat datang! Saya ialah Asisten AI bagi Portal Kontraktor MDBG. Ada sebarang soalan mengenai proses pendaftaran syarikat atau pendaftaran kerja undi?
        </div>
    </div>
    <div class="ai-chat-footer">
        <div class="ai-chat-input-wrapper">
            <input type="text" id="aiInput" class="form-control" placeholder="Soalan Berkaitan Sistem" onkeypress="handleAiKeyPress(event)">
        </div>
        <button class="ai-send-btn" onclick="sendAiMessage()" title="Hantar Mesej">
            <i class="fa-solid fa-paper-plane"></i>
        </button>
    </div>
</div>


<!-- Butang Facebook -->
<a href="https://www.facebook.com/mdbatugajah/" target="_blank" class="social-btn social-btn-fb" title="Facebook Rasmi MDBG">
    <i class="fa-brands fa-facebook-f"></i>
</a>

<!-- Butang Instagram -->
<a href="https://www.instagram.com/majlisdaerahbatugajah_/?hl=en" target="_blank" class="social-btn social-btn-ig" title="Instagram Rasmi MDBG">
    <i class="fa-brands fa-instagram"></i>
</a>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        document.getElementById('sidebarWrapper').classList.toggle('collapsed');
    });

    document.addEventListener("DOMContentLoaded", function() {
        const currentUrl = window.location.pathname.split("/").pop();
        const menuLinks = document.querySelectorAll(".nav-link-item");
        
        menuLinks.forEach(link => {
            const linkHref = link.getAttribute("href").split("?")[0];
            if (linkHref === currentUrl) {
                document.querySelectorAll(".nav-link-item").forEach(item => item.classList.remove("active"));
                link.classList.add("active");
            }
        });
    });

    // FUNGSI TAPIS INPUT SSM (10 AKSARA, TIADA SPACES, TIADA SIMBOL, AUTO-UPPERCASE)
    function formatSSMInput(input) {
        let value = input.value.replace(/[^a-zA-Z0-9]/g, '');
        input.value = value.toUpperCase();
    }

    // FUNGSI TAPIS INPUT NUMERIC ONLY (HANYA ANGKA NOMBOR UNTUK NO TELEFON)
    function formatNumericOnly(input) {
        input.value = input.value.replace(/[^0-9]/g, '');
    }

    // FUNGSI TAMBAHAN: VALIDASI FRONTEND TARIKH MULA VS TARIKH TAMAT
    function semakValidationTarikh(idMula, idTamat, namaSijil) {
        const inputMula = document.getElementById(idMula);
        const inputTamat = document.getElementById(idTamat);

        if (inputMula && inputTamat && inputMula.value && inputTamat.value) {
            const tarikhMula = new Date(inputMula.value);
            const tarikhTamat = new Date(inputTamat.value);

            if (tarikhTamat < tarikhMula) {
                Swal.fire({
                    title: 'Ralat Tarikh!',
                    text: 'Tarikh Tamat bagi ' + namaSijil + ' tidak boleh lebih awal daripada Tarikh Mula.',
                    icon: 'error',
                    confirmButtonColor: '#dc3545',
                    confirmButtonText: 'Faham'
                });
                inputTamat.value = '';
            }
        }
    }

    // FUNGSI LOGIK NOTIFIKASI BARU DI HEADER
    function semakNotifikasiTerbaru() {
        const statusDokumen = "<?= $status_dokumen_notifikasi; ?>";
        const statusBayaran = "<?= $status_bayaran_notifikasi; ?>";
        const hasSijil = "<?= !empty($profil['fail_sijil']) ? 'ya' : 'tidak'; ?>";
        const username = "<?= htmlspecialchars($nama_paparan ?? 'Kontraktor'); ?>";
        const hasProfil = "<?= !empty($profil) ? 'ya' : 'tidak'; ?>";

        const badge = document.querySelector('.notification-badge');
        if (badge) {
            badge.style.display = 'none';
        }

        // KEADAAN 1: AKAUN BARU (TIADA PROFIL / DATA LANGSUNG DALAM DATABASE)
        if (hasProfil === 'tidak') {
            Swal.fire({
                title: 'Selamat Datang!',
                text: 'Sila pergi ke fasa 1 untuk mendaftar syarikat kalau ingin mengetahui lebih lanjut sila pergi ke syarat pengguna',
                icon: 'info',
                confirmButtonColor: '#64748b',
                confirmButtonText: 'Tutup'
            });
            return;
        }

        // KAWALAN UTAMA: JIKA STATUS ADALAH GAGAL, TOLAK, ATAU TIDAK LENGKAP
        if (statusDokumen.includes("Gagal") || statusDokumen.includes("Tolak") || statusDokumen.includes("Tidak Lengkap") || statusDokumen === "") {
            Swal.fire({
                title: 'Makluman Permohonan',
                text: 'Dokumen anda tidak lengkap sila baca alasan tolak untuk dikemaskini borang pendaftaran',
                icon: 'error',
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Faham'
            });
            return;
        }

        // KEADAAN 2: DOKUMEN LENGKAP & SUDAH BAYAR (KEDUA-DUA)
        if (statusDokumen === "Lengkap" && statusBayaran === "Sudah Bayar") {
            if (hasSijil === 'ya') {
                Swal.fire({
                    title: 'Sijil Sedia Dimuat Turun',
                    text: 'Sijil pendaftaran anda telah sedia. Sila muat turun sijil pendaftaran rasmi anda di bahagian bawah profil syarikat.',
                    icon: 'success',
                    confirmButtonColor: '#16a34a',
                    confirmButtonText: 'Baik, Faham'
                });
            } else {
                Swal.fire({
                    title: 'Makluman Sijil Pendaftaran',
                    text: 'Sijil akan dikeluarkan dalam waktu terdekat di dalam portal rasmi kontraktor mdbg.',
                    icon: 'success',
                    confirmButtonColor: '#16a34a',
                    confirmButtonText: 'Baik, Faham'
                });
            }
        } 
        // KEADAAN 3: STATUS DOKUMEN SAJA LENGKAP
        else if (statusDokumen === "Lengkap" && statusBayaran !== "Sudah Bayar") {
            Swal.fire({
                title: 'Makluman Bil & Pembayaran',
                text: 'Admin akan memberi bil dalam waktu terdekat platform dihantar di email and portal rasmi kontraktor mdbg selepas pembayaran sila hantar resit di portal rasmi kontraktor mdbg.',
                icon: 'info',
                confirmButtonColor: '#8D5B4C',
                confirmButtonText: 'Baik, Terima Kasih'
            });
        } 
        // KEADAAN 4: STATUS BAYARAN SAJA SUDAH BAYAR
        else if (statusBayaran === "Sudah Bayar") {
            if (hasSijil === 'ya') {
                Swal.fire({
                    title: 'Sijil Sedia Dimuat Turun',
                    text: 'Sijil pendaftaran anda telah sedia. Sila muat turun sijil pendaftaran rasmi anda di bahagian bawah profil syarikat.',
                    icon: 'success',
                    confirmButtonColor: '#16a34a',
                    confirmButtonText: 'Baik, Faham'
                });
            } else {
                Swal.fire({
                    title: 'Makluman Sijil Pendaftaran',
                    text: 'Sijil akan dikeluarkan dalam waktu terdekat di dalam portal rasmi kontraktor mdbg.',
                    icon: 'success',
                    confirmButtonColor: '#16a34a',
                    confirmButtonText: 'Baik, Faham'
                });
            }
        } 
        // KEADAAN 5: STATUS DOKUMEN ADALAH PENDING (SEDANG DISEMAK)
        else if (statusDokumen === "Pending") {
            Swal.fire({
                title: 'Tiada Notifikasi Baharu',
                text: 'Permohonan anda masih dalam fasa semakan. Sila tunggu maklum balas admin.',
                icon: 'warning',
                confirmButtonColor: '#64748b',
                confirmButtonText: 'Tutup'
            });
        } 
        // KEADAAN LAIN-LAIN
        else {
            Swal.fire({
                title: 'Tiada Notifikasi Baharu',
                text: 'Permohonan anda masih dalam fasa semakan. Sila tunggu maklum balas admin.',
                icon: 'warning',
                confirmButtonColor: '#64748b',
                confirmButtonText: 'Tutup'
            });
        }
    }
            //CIDB Dan Kewangan Kemaskini
            // Ambil data string gred sedia ada daripada PHP
            const rawGredDataModal = <?= json_encode($profil['gred_cidb_kewangan'] ?? ''); ?>;

            function renderGredTableModal() {
                const container = document.getElementById('containerGredModal');
                if (!container) return;
                
                container.innerHTML = '';
                
                if (!rawGredDataModal || rawGredDataModal.trim() === '') {
                    tambahBarisGredModal('', '', '');
                    return;
                }

                // Pecahkan data ikut baris (\n)
                let lines = rawGredDataModal.split('\n').filter(line => line.trim() !== '');
                let countData = 0;

                lines.forEach(line => {
                    // Abaikan baris tajuk header jika ada disimpan dalam string
                    if (line.includes('GRED') && line.includes('KATEGORI') && line.includes('PENGKHUSUSAN')) {
                        return;
                    }

                    // Split berdasarkan jarak/ruang kosong (multiple spaces)
                    let parts = line.trim().split(/\s+/);
                    if (parts.length >= 1 && parts[0] !== '') {
                        let gred = parts[0] || '';
                        let kat = parts[1] || '';
                        let peng = parts.slice(2).join(' ') || '';
                        
                        tambahBarisGredModal(gred, kat, peng);
                        countData++;
                    }
                });

                if (countData === 0) {
                    tambahBarisGredModal('', '', '');
                }
            }

            // Fungsi Tambah Baris Textfield
            function tambahBarisGredModal(gred = '', kat = '', peng = '') {
                const container = document.getElementById('containerGredModal');
                if (!container) return;

                const tr = document.createElement('tr');
                tr.className = 'baris-gred-item';
                tr.innerHTML = `
                    <td>
                        <input type="text" class="form-control form-control-sm input-gred" value="${gred}" placeholder="cth: G1" required>
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm input-kat" value="${kat}" placeholder="cth: B" required>
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm input-peng" value="${peng}" placeholder="cth: B04" required>
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-danger px-2 py-1" onclick="padamBarisGredModal(this)" title="Padam Baris">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </td>
                `;
                container.appendChild(tr);
            }

            // Fungsi Padam Baris
            function padamBarisGredModal(btn) {
                const rows = document.querySelectorAll('#containerGredModal .baris-gred-item');
                if (rows.length > 1) {
                    btn.closest('tr').remove();
                } else {
                    alert('Sekurang-kurangnya satu baris Gred Kelayakan mestilah diisi.');
                }
            }

            // Proses format data string sebelum submit borang
            document.addEventListener('DOMContentLoaded', function() {
                renderGredTableModal();

                const modalForm = document.querySelector('#modalKemaskiniProfil form');
                if (modalForm) {
                    modalForm.addEventListener('submit', function(e) {
                        const rows = document.querySelectorAll('#containerGredModal .baris-gred-item');
                        let formattedText = "GRED          KATEGORI        PENGKHUSUSAN\n";
                        let validRows = 0;

                        rows.forEach(tr => {
                            let gred = tr.querySelector('.input-gred').value.trim().toUpperCase();
                            let kat = tr.querySelector('.input-kat').value.trim().toUpperCase();
                            let peng = tr.querySelector('.input-peng').value.trim().toUpperCase();

                            if (gred || kat || peng) {
                                let gPadded = gred.padEnd(14, ' ');
                                let kPadded = kat.padEnd(16, ' ');
                                formattedText += `${gPadded}${kPadded}${peng}\n`;
                                validRows++;
                            }
                        });

                        const hiddenInput = document.getElementById('gred_cidb_kewangan_hidden');
                        if (hiddenInput) {
                            hiddenInput.value = (validRows > 0) ? formattedText.trim() : '';
                        }
                    });
                }
            });
  

    // ==========================================
    // LOGIK CHATBOT AI GEMINI DINAMIK
    // ==========================================
    const GEMINI_API_KEY = "AQ.Ab8RN6IKLrtr9aaBb7ulGhkj-_YqaAGh_gl9cKPBDICmpc3Sow";

    const systemContext = `
    Anda adalah Asisten AI untuk Portal Kontraktor Majlis Daerah Batu Gajah (MDBG).
    Tugas anda adalah memberikan maklumat mesra, profesional, dan tepat dalam Bahasa Melayu ringkas.

    MAKLUMAT PENGGUNA TERKINI DARI DATABASE:
    - Nama Paparan / Kontraktor: "<?= addslashes($nama_paparan); ?>"
    - Jenis Akaun: "<?= addslashes($jenis_akaun); ?>"
    - Nama Syarikat: "<?= addslashes($profil['nama_syarikat'] ?? 'Belum Berdaftar'); ?>"
    - No SSM: "<?= addslashes($profil['no_pendaftaran'] ?? 'Tiada'); ?>"
    - Gred CIDB: "<?= addslashes($profil['gred_cidb_kewangan'] ?? 'Tiada'); ?>"
    - Status Semakan Dokumen Fasa 1: "<?= addslashes($profil['status_borang'] ?? 'Belum Ada Permohonan'); ?>"
    - Status Bayaran Daftar (RM52): "<?= addslashes($profil['status_bayaran_daftar'] ?? 'Belum Bayar'); ?>"
    - Tarikh Tamat Sah Aktif: "<?= (!empty($profil['tarikh_tamat_aktif']) && $profil['tarikh_tamat_aktif'] != '0000-00-00') ? date('d-m-Y', strtotime($profil['tarikh_tamat_aktif'])) : 'Belum Aktif'; ?>"
    - Status Kelayakan Akaun: "<?= $is_status_aktif ? 'Aktif' : 'Tidak Aktif / Belum Aktif'; ?>"
    - Alasan Tolak (jika ada): "<?= addslashes($profil['alasan_tolak'] ?? 'Tiada'); ?>"
    - Status Daftar Kerja Undi Fasa 2: "<?= $sudah_daftar_undi ? 'Sudah Daftar Undi' : 'Belum Daftar Undi'; ?>"
    - Status Kelayakan Undi: "<?= addslashes($data_undi['status_undi'] ?? ($data_undi['status_layak'] ?? 'N/A')); ?>"

    PANDUAN PROSES ALIRAN SISTEM MDBG:
    1. Fasa 1 (Daftar Syarikat): Mengisi borang profil, memuat naik fail PDF (SSM, CIDB Perakuan, CIDB Perolehan, PKK, TCC, MOF, Borang Pengesahan).
    2. Semakan Admin: Admin semak dokumen. Jika "Lengkap", bil pendaftaran RM52 akan dikeluarkan.
    3. Pembayaran RM52: Kontraktor muat turun bil di Dashboard, bayar & muat naik resit (PDF).
    4. Sijil Pendaftaran & Aktif: Selepas resit disahkan, status jadi "Sudah Bayar", tarikh aktif sah 1 tahun, dan sijil boleh dimuat turun.
    5. Fasa 2 (Daftar Kerja Undi): Boleh didaftar sebaik sahaja Fasa 1 Selesai. Fi kerja undi RM10.
    `;

    function toggleAiChat() {
        const chatBox = document.getElementById('aiChatBox');
        chatBox.classList.toggle('active');
    }

    function handleAiKeyPress(e) {
        if (e.key === 'Enter') {
            sendAiMessage();
        }
    }

    async function sendAiMessage() {
        const input = document.getElementById('aiInput');
        const query = input.value.trim();
        if (!query) return;

        // 1. Paparkan soalan pengguna
        appendMessage(query, 'user');
        input.value = '';

        // 2. Tunjukkan indikator sedang memproses
        const tempBotMsg = appendMessage("AI sedang berfikir...", 'bot');

        try {
            const response = await fetch(`https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=${GEMINI_API_KEY}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    contents: [{
                        parts: [
                            { text: systemContext },
                            { text: "Soalan Pengguna: " + query }
                        ]
                    }]
                })
            });

            const data = await response.json();
            if (data.candidates && data.candidates[0].content.parts[0].text) {
                tempBotMsg.innerText = data.candidates[0].content.parts[0].text;
            } else {
                tempBotMsg.innerText = getFallbackAiResponse(query);
            }
        } catch (error) {
            console.error('Gemini API Error:', error);
            tempBotMsg.innerText = getFallbackAiResponse(query);
        }

        const chatBody = document.getElementById('aiChatBody');
        chatBody.scrollTop = chatBody.scrollHeight;
    }

    function appendMessage(text, sender) {
        const chatBody = document.getElementById('aiChatBody');
        const msgDiv = document.createElement('div');
        msgDiv.className = `ai-message ${sender}`;
        msgDiv.innerText = text;
        chatBody.appendChild(msgDiv);
        chatBody.scrollTop = chatBody.scrollHeight;
        return msgDiv;
    }

    // Fungsi Respons Sokongan (Fallback Response) jika berlaku ralat sambungan
    function getFallbackAiResponse(text) {
        const lower = text.toLowerCase();

            // 1. Keutamaan Pertama: Soalan berkaitan Saiz / Format / PDF
            if (lower.includes('saiz') || lower.includes('size') || lower.includes('pdf') || lower.includes('format') || lower.includes('mb')) {
                return "Sila pastikan fail dokumen muat naik berformat PDF dan saiz tidak melebihi 10MB.";
            }

            // 2. Keutamaan Kedua: Soalan berkaitan cara semak/lihat fail yang di-upload
            if ((lower.includes('check') || lower.includes('semak') || lower.includes('lihat') || lower.includes('mana')) && 
                (lower.includes('file') || lower.includes('fail') || lower.includes('upload') || lower.includes('muat naik'))) {
                return "Anda boleh pergi ke page Maklumat Kontraktor untuk melihat fail yang telah dimuat naik.";
            }

            // 1. Soalan TEMPOH / BERAPA (Keutamaan Tertinggi untuk soalan bilangan/masa)
            if (lower.includes('berapa') || lower.includes('tempoh')) {
                return "Tarikh akhir sah sijil pendaftaran adalah 1 tahun.";
            }

            // Soalan KEMASKINI / EDIT / UBAH MAKLUMAT PROFIL SYARIKAT
            if ((lower.includes('kemaskini') || lower.includes('edit') || lower.includes('ubah') || lower.includes('tukar')) && 
                (lower.includes('profil') || lower.includes('maklumat') || lower.includes('syarikat') || lower.includes('data') || lower.includes('akses'))) {
                return "Untuk mengemas kini maklumat syarikat, klik 'Kemaskini' pada Ringkasan Profil Syarikat dan hantar sebab permohonan kepada Admin. Sebaik sahaja diluluskan (Approved), butang 'Kemaskini Profil' akan dibuka semula untuk anda mengemas kini data atau memuat naik fail.";
            }

            // 2. Soalan KEMASKINI / PERBAHARUI selepas tamat
            if ((lower.includes('kemaskini') || lower.includes('update') || lower.includes('perbaharui')) && 
                (lower.includes('tamat') || lower.includes('sah') || lower.includes('selepas'))) {
                return "Selepas Tarikh Tamat Sah pergi ke page perbaharuan untuk mengemas kini data lama jika perlu.";
            }

            // 3. Soalan TINDAKAN jika expired (Gunakan 'apa perlu' atau 'apa nak', elak guna 'apa' sahaja)
            if (lower.includes('expired') || lower.includes('luput') || 
            ((lower.includes('tamat') || lower.includes('sah')) && (lower.includes('macam mana') || lower.includes('bagaimana') || lower.includes('apa perlu')))) {
                return "Jika tarikh sah telah expired/tamat, sila pergi ke page perbaharuan untuk mengemas kini data dan memperbaharui pendaftaran anda.";
            }

            // 4. Syarat Tarikh Am
            if (lower.includes('tarikh') || lower.includes('sah') || lower.includes('tamat')) {
                return "Tarikh akhir sah sijil pendaftaran adalah 1 tahun.";
            }

            // 2. Semak status pending
            if (lower.includes('pending')) {
                return "Semakan dokumen mengambil masa sehingga 3 hari bekerja.";
            }

            // 3. Semak Fasa / Syarikat
            if (lower.includes('fasa 1') || lower.includes('syarikat')) {
                return "Untuk Fasa 1 (Daftar Syarikat), sila lengkapkan profil syarikat dan muat naik dokumen berkaitan (SSM, CIDB, TCC, PKK, MOF) dalam format PDF.";
            }

            if (lower.includes('fasa 2') || lower.includes('undi')) {
                return "Fasa 2 (Daftar Kerja Undi) hanya boleh diakses setelah pendaftaran Fasa 1 disahkan Lengkap dan bayaran yuran RM52 disahkan.";
            }

            // 4. Semak Pembayaran & Sijil
            if (lower.includes('bayar') || lower.includes('resit') || lower.includes('yuran')) {
                return "Sila muat turun bil pendaftaran di Dashboard, buat pembayaran, kemudian muat naik resit (PDF) di bahagian profil anda.";
            }

            if (lower.includes('sijil')) {
                return "Sijil pendaftaran rasmi boleh dimuat turun terus melalui Dashboard apabila status dokumen anda 'Lengkap' dan status bayaran 'Sudah Bayar'.";
            }

            // 5. Kata aluan / Greetings (Diletakkan di bawah agar soalan khusus dapat diproses dahulu)
            if (lower.includes('salam')) {
                return "Waalaikumussalam Warahmatullahi Wabarakatuh! Selamat datang ke Portal Kontraktor MDBG! Ada apa-apa soalan yang boleh saya bantu?";
            }

            if (lower.includes('hello') || lower.includes('hi')) {
                return "Selamat datang ke Portal Kontraktor MDBG! Ada apa-apa soalan yang boleh saya bantu?";
            }

            // 5. Default Response
            return "Maaf, saya tidak mempunyai maklumat lanjut mengenai perkara itu. Sila rujuk page syarat pengguna atau hubungi talian khidmat pelanggan di talian +60 19-555 1234 / emel pendaftaran@mdbg.gov.my.";
    }
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtn = document.getElementById('darkModeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const html = document.documentElement;

        function updateIcon() {
            const isDark = html.getAttribute('data-theme') === 'dark';
            themeIcon.className = isDark ? 'fa-solid fa-sun fs-5' : 'fa-solid fa-moon fs-5';
        }
        updateIcon();

        toggleBtn.addEventListener('click', function() {
            const isDark = html.getAttribute('data-theme') === 'dark';
            if (isDark) {
                html.removeAttribute('data-theme');
                localStorage.setItem('mdbg_theme', 'light');
            } else {
                html.setAttribute('data-theme', 'dark');
                localStorage.setItem('mdbg_theme', 'dark');
            }
            updateIcon();
        });
    });
</script>
</body>
</html>