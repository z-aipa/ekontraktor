<?php
session_start();

// PENTING: Sila sesuaikan path db.php mengikut kedudukan fail anda.
include 'db.php'; 

// Sekatan keselamatan: Pastikan hanya pengguna yang log masuk sebagai kontraktor boleh memproses borang
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kontraktor') {
    header("Location: index.php");
    exit();
}

// Pastikan pembolehubah sambungan pangkalan data wujud
if (!isset($conn)) {
    die("Ralat Sistem: Sambungan pangkalan data (\$conn) tidak dijumpai. Sila semak fail db.php anda.");
}

// Memproses terus apabila data borang dihantar
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 1. SEMAKAN KESELAMATAN: Jika $_POST kosong disebabkan saiz fail melebihi post_max_size PHP
    if (empty($_POST) && $_SERVER['CONTENT_LENGTH'] > 0) {
        echo "<script>alert('Ralat: Jumlah saiz fail yang dimuat naik melebihi had muat naik pelayan (post_max_size). Sila kecilkan saiz fail PDF anda.'); window.history.back();</script>";
        exit();
    }

    // 2. SEMAKAN KESELAMATAN: Pastikan borang sekurang-kurangnya diisi dengan nama syarikat
    if (empty($_POST['nama_syarikat']) || empty($_POST['no_pendaftaran'])) {
        echo "<script>alert('Ralat: Maklumat wajib seperti Nama Syarikat dan No. Pendaftaran tidak boleh dibiarkan kosong.'); window.history.back();</script>";
        exit();
    }

    // TANGKAP DATA BORANG (Gunakan Prepared Statement, tidak memerlukan mysqli_real_escape_string)
    $user_id         = $_SESSION['user_id']; 
    $jenis           = $_POST['jenis_pendaftaran'] ?? '';
    $kat_array       = isset($_POST['kategori_pendaftaran']) ? (array)$_POST['kategori_pendaftaran'] : [];
    $kategori        = implode(', ', $kat_array);
    $nama            = strtoupper(trim($_POST['nama_syarikat'] ?? '')); 
    $alamat          = strtoupper(trim($_POST['alamat'] ?? ''));
    $no_pendaftaran  = trim($_POST['no_pendaftaran'] ?? ''); 
    $no_tel_sya      = trim($_POST['no_telefon_syarikat'] ?? '');
    $no_tel_peng     = trim($_POST['no_telefon_pengurus'] ?? '');
    $email           = trim($_POST['email_aktif'] ?? '');
    $perakuan        = $_POST['perakuan_setuju'] ?? '';
    
    // MEMPROSES JADUAL DINAMIK GRED, KATEGORI & PENGKHUSUSAN
    $gred_data = [];
    if (isset($_POST['gred']) && is_array($_POST['gred'])) {
        for ($i = 0; $i < count($_POST['gred']); $i++) {
            $g_val = trim($_POST['gred'][$i] ?? '');
            $k_val = trim($_POST['kategori_gred'][$i] ?? '');
            $p_val = trim($_POST['pengkhususan'][$i] ?? '');
            if ($g_val !== '' || $k_val !== '' || $p_val !== '') {
                $gred_data[] = [
                    'gred' => strtoupper($g_val),
                    'kategori' => strtoupper($k_val),
                    'pengkhususan' => strtoupper($p_val)
                ];
            }
        }
    }

    // SIMPAN DALAM FORMAT TEKS BERSUSUN KEMAS DENGAN SPACING PAD SELARI (SEPERTI RANGKA RAJAH 2)
    if (!empty($gred_data)) {
        $lines = [];
        $lines[] = sprintf("%-14s%-16s%s", "GRED", "KATEGORI", "PENGKHUSUSAN");
        foreach ($gred_data as $row) {
            $lines[] = sprintf("%-14s%-16s%s", $row['gred'], $row['kategori'], $row['pengkhususan']);
        }
        $gred = implode("\n", $lines);
    } else {
        $gred = strtoupper(trim($_POST['gred_cidb_kewangan'] ?? ''));
    }

    // Fungsi pembantu bagi menukar tarikh kosong kepada NULL supaya tidak memecahkan lajur DATE MySQL
    function sanitize_date($conn, $date_str) {
        $clean = trim($date_str ?? '');
        if (empty($clean) || $clean === '0000-00-00') {
            return NULL;
        }
        return $clean;
    }

    // Menangkap & membersihkan data input tarikh
    $tarikh_mula_ssm            = sanitize_date($conn, $_POST['tarikh_mula_ssm'] ?? '');
    $tarikh_tamat_ssm           = sanitize_date($conn, $_POST['tarikh_tamat_ssm'] ?? '');
    $tarikh_mula_tcc            = sanitize_date($conn, $_POST['tarikh_mula_tcc'] ?? '');
    $tarikh_tamat_tcc           = sanitize_date($conn, $_POST['tarikh_tamat_tcc'] ?? '');
    $tarikh_mula_pkk            = sanitize_date($conn, $_POST['tarikh_mula_pkk'] ?? '');
    $tarikh_tamat_pkk           = sanitize_date($conn, $_POST['tarikh_tamat_pkk'] ?? '');
    $tarikh_mula_cidb_perakuan  = sanitize_date($conn, $_POST['tarikh_mula_cidb_perakuan'] ?? '');
    $tarikh_tamat_cidb_perakuan = sanitize_date($conn, $_POST['tarikh_tamat_cidb_perakuan'] ?? '');
    $tarikh_mula_cidb_perolehan = sanitize_date($conn, $_POST['tarikh_mula_cidb_perolehan'] ?? '');
    $tarikh_tamat_cidb_perolehan= sanitize_date($conn, $_POST['tarikh_tamat_cidb_perolehan'] ?? '');

    // Path ke folder uploads
    $target_dir = "uploads/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true); 
    }
    
    // Fungsi pembantu muat naik fail yang diperkemas dengan penamaan automatik berdasar user_id
    function upload_file($file_field, $target_dir, $user_id) {
        if (isset($_FILES[$file_field]) && $_FILES[$file_field]['error'] != UPLOAD_ERR_NO_FILE) {
            
            // 1. Semak jika fail melebihi had 10MB
            if ($_FILES[$file_field]['error'] == UPLOAD_ERR_INI_SIZE || $_FILES[$file_field]['size'] > (10 * 1024 * 1024)) {
                return "RALAT_SAIZ";
            }

            // 2. Semak sekiranya ada ralat muat naik lain
            if ($_FILES[$file_field]['error'] != UPLOAD_ERR_OK) {
                return "";
            }

            // 3. Semak format fail
            $file_extension = strtolower(pathinfo($_FILES[$file_field]["name"], PATHINFO_EXTENSION));
            if ($file_extension != "pdf") {
                return "RALAT_FORMAT";
            }
            
            // Format nama fail tetap: JENIS_user_ID.pdf (Contoh: SSM_user_35.pdf)
            $prefix = strtoupper(str_replace('fail_', '', $file_field));
            $filename = $prefix . "_user_" . $user_id . ".pdf";
            
            // move_uploaded_file akan terus replace / overwrite fail sedia ada di uploads/
            if (move_uploaded_file($_FILES[$file_field]["tmp_name"], $target_dir . $filename)) {
                return $filename;
            }
        }
        return "";
    }

    // Jalankan muat naik fail dengan memasukkan pembolehubah $user_id
    $fail_ssm         = upload_file('fail_ssm', $target_dir, $user_id);
    $fail_pkk         = upload_file('fail_pkk', $target_dir, $user_id);
    $fail_cidb_prak   = upload_file('fail_cidb_perakuan', $target_dir, $user_id);
    $fail_cidb_prol   = upload_file('fail_cidb_perolehan', $target_dir, $user_id);
    $fail_mof         = upload_file('fail_mof', $target_dir, $user_id);
    $fail_tcc         = upload_file('fail_tcc', $target_dir, $user_id);

    // Semak sekiranya ada fail yang melebihi had saiz 10 MB
    $had_saiz_tergugat = (
        $fail_ssm === "RALAT_SAIZ" || $fail_tcc === "RALAT_SAIZ" || 
        $fail_pkk === "RALAT_SAIZ" || $fail_cidb_prak === "RALAT_SAIZ" || 
        $fail_cidb_prol === "RALAT_SAIZ" || $fail_mof === "RALAT_SAIZ"
    );

    // Semak sekiranya ada fail yang bukan berformat PDF
    $had_format_tergugat = (
        $fail_ssm === "RALAT_FORMAT" || $fail_tcc === "RALAT_FORMAT" || 
        $fail_pkk === "RALAT_FORMAT" || $fail_cidb_prak === "RALAT_FORMAT" || 
        $fail_cidb_prol === "RALAT_FORMAT" || $fail_mof === "RALAT_FORMAT"
    );

    if ($had_saiz_tergugat) {
        echo "<script>alert('Ralat: Setiap dokumen yang dimuat naik MESTILAH tidak melebihi saiz had 10 MB!'); window.history.back();</script>";
        exit();
    }

    if ($had_format_tergugat) {
        echo "<script>alert('Ralat: Semua dokumen yang dimuat naik MESTILAH dalam format PDF sahaja!'); window.history.back();</script>";
        exit();
    }

    // Semak jika profil pembekal sudah wujud
    $check_stmt = $conn->prepare("SELECT * FROM kontraktor_profil WHERE user_id = ?");
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $existing_profile = $result->fetch_assoc();
    $check_stmt->close();

    if ($existing_profile) {
        // ===================================================================
        // JIKA DATA WUJUD: PROSES UPDATE (KEMASKINI / PEMBAHARUAN)
        // ===================================================================
        $fail_ssm        = (!empty($fail_ssm))        ? $fail_ssm        : $existing_profile['fail_ssm'];
        $fail_pkk        = (!empty($fail_pkk))        ? $fail_pkk        : $existing_profile['fail_pkk'];
        $fail_cidb_prak  = (!empty($fail_cidb_prak))  ? $fail_cidb_prak  : $existing_profile['fail_cidb_perakuan'];
        $fail_cidb_prol  = (!empty($fail_cidb_prol))  ? $fail_cidb_prol  : $existing_profile['fail_cidb_perolehan'];
        $fail_mof        = (!empty($fail_mof))        ? $fail_mof        : $existing_profile['fail_mof'];
        $fail_tcc        = (!empty($fail_tcc))        ? $fail_tcc        : $existing_profile['fail_tcc'];

        $stmt = $conn->prepare("UPDATE kontraktor_profil SET 
            jenis_pendaftaran = ?, kategori_pendaftaran = ?, nama_syarikat = ?, alamat = ?, no_pendaftaran = ?, 
            gred_cidb_kewangan = ?, no_telefon_syarikat = ?, no_telefon_pengurus = ?, email_aktif = ?, 
            fail_ssm = ?, tarikh_mula_ssm = ?, tarikh_tamat_ssm = ?, fail_tcc = ?, tarikh_mula_tcc = ?, 
            tarikh_tamat_tcc = ?, fail_pkk = ?, tarikh_mula_pkk = ?, tarikh_tamat_pkk = ?, fail_cidb_perakuan = ?, 
            tarikh_mula_cidb_perakuan = ?, tarikh_tamat_cidb_perakuan = ?, fail_cidb_perolehan = ?, 
            tarikh_mula_cidb_perolehan = ?, tarikh_tamat_cidb_perolehan = ?, fail_mof = ?, perakuan_setuju = ?, 
            status_borang = 'Pending', status_bayaran_daftar = 'Belum Bayar', alasan_tolak = NULL,
            fail_bil = NULL, fail_resit = NULL, fail_sijil = NULL,
            tarikh_mula_aktif = NULL, tarikh_tamat_aktif = NULL
            WHERE user_id = ?");

        if ($stmt) {
            $stmt->bind_param("ssssssssssssssssssssssssssi", 
                $jenis, $kategori, $nama, $alamat, $no_pendaftaran, $gred, $no_tel_sya, $no_tel_peng, 
                $email, $fail_ssm, $tarikh_mula_ssm, $tarikh_tamat_ssm, $fail_tcc, $tarikh_mula_tcc, $tarikh_tamat_tcc, 
                $fail_pkk, $tarikh_mula_pkk, $tarikh_tamat_pkk, $fail_cidb_prak, $tarikh_mula_cidb_perakuan, $tarikh_tamat_cidb_perakuan, 
                $fail_cidb_prol, $tarikh_mula_cidb_perolehan, $tarikh_tamat_cidb_perolehan, $fail_mof, $perakuan, $user_id
            );
        }

    } else {
        // ===================================================================
        // JIKA DATA TIADA: PROSES INSERT (DAFTAR PERTAMA KALI)
        // ===================================================================
        $stmt = $conn->prepare("INSERT INTO kontraktor_profil (
            user_id, jenis_pendaftaran, kategori_pendaftaran, nama_syarikat, alamat, no_pendaftaran, 
            gred_cidb_kewangan, no_telefon_syarikat, no_telefon_pengurus, email_aktif, fail_ssm, 
            tarikh_mula_ssm, tarikh_tamat_ssm, fail_tcc, tarikh_mula_tcc, tarikh_tamat_tcc, 
            fail_pkk, tarikh_mula_pkk, tarikh_tamat_pkk, fail_cidb_perakuan, tarikh_mula_cidb_perakuan, 
            tarikh_tamat_cidb_perakuan, fail_cidb_perolehan, tarikh_mula_cidb_perolehan, tarikh_tamat_cidb_perolehan, 
            fail_mof, perakuan_setuju, status_borang, status_bayaran_daftar
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 'Belum Bayar')");
        
        if ($stmt) {
            $stmt->bind_param("issssssssssssssssssssssssss", 
                $user_id, $jenis, $kategori, $nama, $alamat, $no_pendaftaran, $gred, $no_tel_sya, $no_tel_peng, $email, 
                $fail_ssm, $tarikh_mula_ssm, $tarikh_tamat_ssm, $fail_tcc, $tarikh_mula_tcc, $tarikh_tamat_tcc, 
                $fail_pkk, $tarikh_mula_pkk, $tarikh_tamat_pkk, $fail_cidb_prak, $tarikh_mula_cidb_perakuan, $tarikh_tamat_cidb_perakuan, 
                $fail_cidb_prol, $tarikh_mula_cidb_perolehan, $tarikh_tamat_cidb_perolehan, $fail_mof, $perakuan
            );
        }
    }
    
    // Pelaksanaan Query
    if ($stmt) {
        if ($stmt->execute()) {
            echo "<script>alert('Borang permohonan MDBG anda telah selamat dihantar untuk semakan Jabatan Kejuruteraan.'); window.location='kontraktor_borang_daftar.php';</script>";
            exit();
        } else {
            echo "Ralat Pangkalan Data: " . $stmt->error;
        }
        $stmt->close();
    } else {
        echo "Ralat Penyediaan Struktur SQL (Prepare Failed): " . $conn->error;
    }
}
$conn->close();
?>