<?php
session_start();
include 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kontraktor') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 1. Ambil input teks dan borang
    $nama_syarikat       = $conn->real_escape_string($_POST['nama_syarikat'] ?? '');
    $alamat              = $conn->real_escape_string($_POST['alamat'] ?? '');
    $no_pendaftaran      = $conn->real_escape_string($_POST['no_pendaftaran'] ?? '');
    $no_tel              = $conn->real_escape_string($_POST['no_tel'] ?? '');
    $email               = $conn->real_escape_string($_POST['email'] ?? '');
    $perakuan            = isset($_POST['perakuan']) ? 'SETUJU' : '';

    // TAMBAHAN KEMAS KINI: Tarik gred terus dari kontraktor_profil supaya format JSON tidak rosak
    $gred_cidb_kewangan = '';
    $query_p = $conn->query("SELECT gred_cidb_kewangan FROM kontraktor_profil WHERE user_id='$user_id'");
    if ($row_p = $query_p->fetch_assoc()) {
        $gred_cidb_kewangan = $conn->real_escape_string($row_p['gred_cidb_kewangan']);
    } else {
        // Fallback jika tiada dalam profil
        $gred_cidb_kewangan = $conn->real_escape_string($_POST['gred_cidb_kewangan'] ?? '');
    }

    // 2. Ambil input tarikh (Format SQL NULL jika tiada tarikh diisi)
    $tarikh_mula_pkk            = !empty($_POST['tarikh_mula_pkk']) ? "'".$conn->real_escape_string($_POST['tarikh_mula_pkk'])."'" : "NULL";
    $tarikh_tamat_pkk           = !empty($_POST['tarikh_tamat_pkk']) ? "'".$conn->real_escape_string($_POST['tarikh_tamat_pkk'])."'" : "NULL";
    
    $tarikh_mula_cidb_perakuan  = !empty($_POST['tarikh_mula_cidb_perakuan']) ? "'".$conn->real_escape_string($_POST['tarikh_mula_cidb_perakuan'])."'" : "NULL";
    $tarikh_tamat_cidb_perakuan = !empty($_POST['tarikh_tamat_cidb_perakuan']) ? "'".$conn->real_escape_string($_POST['tarikh_tamat_cidb_perakuan'])."'" : "NULL";
    
    $tarikh_mula_cidb_perolehan  = !empty($_POST['tarikh_mula_cidb_perolehan']) ? "'".$conn->real_escape_string($_POST['tarikh_mula_cidb_perolehan'])."'" : "NULL";
    $tarikh_tamat_cidb_perolehan = !empty($_POST['tarikh_tamat_cidb_perolehan']) ? "'".$conn->real_escape_string($_POST['tarikh_tamat_cidb_perolehan'])."'" : "NULL";

    // 3. Pastikan folder uploads wujud
    $upload_dir = __DIR__ . '/uploads/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Semak samada pengguna sudah ada rekod terdahulu
    $check = $conn->query("SELECT * FROM kontraktor_undi WHERE user_id='$user_id'");
    $existing = $check->fetch_assoc();

   // Fungsi pembantu untuk memproses muat naik fail dengan ciri overwrite automatik
    function muatNaikFail($input_name, $fail_lama, $upload_dir, $user_id) {
        if (isset($_FILES[$input_name]) && $_FILES[$input_name]['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES[$input_name]['tmp_name'];
            $file_extension = strtolower(pathinfo($_FILES[$input_name]["name"], PATHINFO_EXTENSION));
            
            // Format nama fail tetap: JENIS_user_ID.pdf (Contoh: DOKUMEN_user_35.pdf)
            $prefix = strtoupper(str_replace('fail_', '', $input_name));
            $new_filename = $prefix . '_user_' . $user_id . '.' . $file_extension;
            $destination = $upload_dir . $new_filename;

            if (move_uploaded_file($file_tmp, $destination)) {
                return $new_filename;
            }
        }
        return $fail_lama;
    }

    // 4. Proses muat naik fail untuk setiap dokumen (ditambah $user_id)
    $fail_dokumen        = muatNaikFail('fail_undi', $existing['fail_dokumen'] ?? '', $upload_dir, $user_id);
    $fail_pkk            = muatNaikFail('fail_pkk', $existing['fail_pkk'] ?? '', $upload_dir, $user_id);
    $fail_cidb_perakuan  = muatNaikFail('fail_cidb_perakuan', $existing['fail_cidb_perakuan'] ?? '', $upload_dir, $user_id);
    $fail_cidb_perolehan = muatNaikFail('fail_cidb_perolehan', $existing['fail_cidb_perolehan'] ?? '', $upload_dir, $user_id);
    $fail_mof            = muatNaikFail('fail_mof', $existing['fail_mof'] ?? '', $upload_dir, $user_id);
    
    // 5. Simpan atau Kemaskini rekod dalam pangkalan data
    if ($existing) {
        $sql = "UPDATE kontraktor_undi SET 
                nama_syarikat='$nama_syarikat', 
                alamat='$alamat',
                no_pendaftaran='$no_pendaftaran', 
                gred_cidb_kewangan='$gred_cidb_kewangan',
                no_tel='$no_tel', 
                email='$email', 
                fail_dokumen='$fail_dokumen', 
                fail_pkk='$fail_pkk',
                tarikh_mula_pkk=$tarikh_mula_pkk,
                tarikh_tamat_pkk=$tarikh_tamat_pkk,
                fail_cidb_perakuan='$fail_cidb_perakuan',
                tarikh_mula_cidb_perakuan=$tarikh_mula_cidb_perakuan,
                tarikh_tamat_cidb_perakuan=$tarikh_tamat_cidb_perakuan,
                fail_cidb_perolehan='$fail_cidb_perolehan',
                tarikh_mula_cidb_perolehan=$tarikh_mula_cidb_perolehan,
                tarikh_tamat_cidb_perolehan=$tarikh_tamat_cidb_perolehan,
                fail_mof='$fail_mof',
                perakuan='$perakuan', 
                tarikh_hantar=NOW() 
                WHERE user_id='$user_id'";
    } else {
        $sql = "INSERT INTO kontraktor_undi (
                    user_id, nama_syarikat, alamat, no_pendaftaran, gred_cidb_kewangan, 
                    no_tel, email, fail_dokumen, fail_pkk, tarikh_mula_pkk, tarikh_tamat_pkk, 
                    fail_cidb_perakuan, tarikh_mula_cidb_perakuan, tarikh_tamat_cidb_perakuan, 
                    fail_cidb_perolehan, tarikh_mula_cidb_perolehan, tarikh_tamat_cidb_perolehan, 
                    fail_mof, perakuan, tarikh_hantar
                ) VALUES (
                    '$user_id', '$nama_syarikat', '$alamat', '$no_pendaftaran', '$gred_cidb_kewangan', 
                    '$no_tel', '$email', '$fail_dokumen', '$fail_pkk', $tarikh_mula_pkk, $tarikh_tamat_pkk, 
                    '$fail_cidb_perakuan', $tarikh_mula_cidb_perakuan, $tarikh_tamat_cidb_perakuan, 
                    '$fail_cidb_perolehan', $tarikh_mula_cidb_perolehan, $tarikh_tamat_cidb_perolehan, 
                    '$fail_mof', '$perakuan', NOW()
                )";
    }

    if ($conn->query($sql)) {
        echo "<script>alert('Permohonan berjaya disimpan!'); window.location.href='kontraktor_daftar_undi.php';</script>";
    } else {
        echo "<script>alert('Ralat Pangkalan Data: " . $conn->error . "'); window.history.back();</script>";
    }
}
?>