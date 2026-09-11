<?php
session_start();

// Sekat daripada menyimpan cache halaman ini
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kontraktor') { 
    header("Location: index.php"); 
    exit(); 
}

include 'db.php';
$user_id = $_SESSION['user_id'];

// Semak status sekat carian syarikat
$res_carian = $conn->query("SELECT nilai FROM tetapan_sistem WHERE kunci = 'sekat_carian_syarikat'");
if ($res_carian && $res_carian->num_rows > 0) {
    if (intval($res_carian->fetch_assoc()['nilai']) == 1) {
        die("<script>
            alert('Semakan Daftar undi telah disekat oleh pihak Pentadbir/Admin.');
            window.location.href = 'kontraktor.php';
        </script>");
    }
}

// Semak & tambah kolum kategori_id dalam jadual kontraktor_undi jika belum ada
$check_kat_col = $conn->query("SHOW COLUMNS FROM kontraktor_undi LIKE 'kategori_id'");
if ($check_kat_col && $check_kat_col->num_rows == 0) {
    $conn->query("ALTER TABLE kontraktor_undi ADD COLUMN kategori_id INT DEFAULT NULL");
}

// Ambil maklumat user bagi paparan nama di header
$user_data = $conn->query("SELECT jenis_akaun, nama_penuh FROM users WHERE id='$user_id'")->fetch_assoc();
$nama_paparan = strtoupper(!empty($user_data['nama_penuh']) ? $user_data['nama_penuh'] : ($_SESSION['username'] ?? 'Kontraktor'));

// AUTOMATIK Dapatkan data profil kontraktor yang log masuk
$found_profil = null;
$existing_undi = null;

$sql_search = "SELECT * FROM kontraktor_profil WHERE user_id = '$user_id' LIMIT 1";
$result_search = $conn->query($sql_search);

if ($result_search && $result_search->num_rows > 0) {
    $found_profil = $result_search->fetch_assoc();
    
    // Semak jika pernah mendaftar undi sebelum ini
    $check_undi = $conn->query("SELECT * FROM kontraktor_undi WHERE user_id='$user_id' LIMIT 1");
    if ($check_undi && $check_undi->num_rows > 0) {
        $existing_undi = $check_undi->fetch_assoc();
    }
}
$has_profil = ($found_profil) ? true : false;

// Semak status aktif & tarikh tamat sah Fasa 1
$today = date('Y-m-d');
$is_expired = false;
$is_status_aktif = false;

if (
    !empty($found_profil) &&
    !empty($found_profil['tarikh_mula_aktif']) &&
    !empty($found_profil['tarikh_tamat_aktif']) &&
    $found_profil['tarikh_mula_aktif'] != '0000-00-00' &&
    $found_profil['tarikh_tamat_aktif'] != '0000-00-00' &&
    isset($found_profil['status_bayaran_daftar']) &&
    $found_profil['status_bayaran_daftar'] == 'Sudah Bayar'
) {
    if (
        $today >= $found_profil['tarikh_mula_aktif'] &&
        $today <= $found_profil['tarikh_tamat_aktif']
    ) {
        $is_status_aktif = true;
    } elseif ($today > $found_profil['tarikh_tamat_aktif']) {
        $is_expired = true;
        $is_status_aktif = false;
    }
}

// PROSES SIMPAN / HANTAR PERMOHONAN KATEGORI UNDI KE DATABASE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_undi'])) {
    $target_user_id = intval($_POST['target_user_id']);
    $kategori_id    = intval($_POST['kategori_id'] ?? 0);
    $perakuan       = isset($_POST['perakuan_setuju']) ? 'SETUJU' : 'TIDAK';

    if ($kategori_id <= 0) {
        echo "<script>alert('Sila pilih Kategori Undi yang layak sebelum menghantar!'); window.history.back();</script>";
        exit();
    }

    // Ambil maklumat terus daripada kontraktor_profil (Read-Only Data)
    $nama_syarikat       = $conn->real_escape_string($found_profil['nama_syarikat'] ?? '');
    $no_pendaftaran      = $conn->real_escape_string($found_profil['no_pendaftaran'] ?? '');
    $alamat              = $conn->real_escape_string($found_profil['alamat'] ?? '');
    $no_tel              = $conn->real_escape_string($found_profil['no_telefon_syarikat'] ?? '');
    $email               = $conn->real_escape_string($found_profil['email_aktif'] ?? '');
    $gred_cidb_kewangan  = $conn->real_escape_string($found_profil['gred_cidb_kewangan'] ?? '');

    $fail_pkk            = $conn->real_escape_string($found_profil['fail_pkk'] ?? '');
    $fail_cidb_perakuan  = $conn->real_escape_string($found_profil['fail_cidb_perakuan'] ?? '');
    $fail_cidb_perolehan = $conn->real_escape_string($found_profil['fail_cidb_perolehan'] ?? '');
    $fail_mof            = $conn->real_escape_string($found_profil['fail_mof'] ?? '');
    $fail_dokumen        = !empty($fail_cidb_perakuan) ? $fail_cidb_perakuan : (!empty($found_profil['fail_ssm']) ? $found_profil['fail_ssm'] : '');

    // Format Tarikh Dokumen
    $tarikh_mula_pkk  = !empty($found_profil['tarikh_mula_pkk']) ? "'" . $conn->real_escape_string($found_profil['tarikh_mula_pkk']) . "'" : "NULL";
    $tarikh_tamat_pkk = !empty($found_profil['tarikh_tamat_pkk']) ? "'" . $conn->real_escape_string($found_profil['tarikh_tamat_pkk']) . "'" : "NULL";

    $tarikh_mula_cidb_perakuan  = !empty($found_profil['tarikh_mula_cidb_perakuan']) ? "'" . $conn->real_escape_string($found_profil['tarikh_mula_cidb_perakuan']) . "'" : "NULL";
    $tarikh_tamat_cidb_perakuan = !empty($found_profil['tarikh_tamat_cidb_perakuan']) ? "'" . $conn->real_escape_string($found_profil['tarikh_tamat_cidb_perakuan']) . "'" : "NULL";

    $tarikh_mula_cidb_perolehan  = !empty($found_profil['tarikh_mula_cidb_perolehan']) ? "'" . $conn->real_escape_string($found_profil['tarikh_mula_cidb_perolehan']) . "'" : "NULL";
    $tarikh_tamat_cidb_perolehan = !empty($found_profil['tarikh_tamat_cidb_perolehan']) ? "'" . $conn->real_escape_string($found_profil['tarikh_tamat_cidb_perolehan']) . "'" : "NULL";

    $check_exist = $conn->query("SELECT * FROM kontraktor_undi WHERE user_id='$target_user_id' LIMIT 1");
    $existing_rec = $check_exist ? $check_exist->fetch_assoc() : null;

    // SIMPAN / KEMASKINI JADUAL kontraktor_undi BERSAMA kategori_id
    if ($existing_rec) {
        $sql_save = "UPDATE kontraktor_undi SET 
                        kategori_id='$kategori_id',
                        nama_syarikat='$nama_syarikat', 
                        alamat='$alamat',
                        no_pendaftaran='$no_pendaftaran', 
                        gred_cidb_kewangan='$gred_cidb_kewangan',
                        no_tel='$no_tel', 
                        email='$email', 
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
                        fail_dokumen='$fail_dokumen', 
                        perakuan='$perakuan', 
                        status_permohonan='Baru',
                        status_undi='Pending',
                        tarikh_hantar=NOW() 
                    WHERE user_id='$target_user_id'";
    } else {
        $sql_save = "INSERT INTO kontraktor_undi 
                        (user_id, kategori_id, nama_syarikat, alamat, no_pendaftaran, gred_cidb_kewangan, no_tel, email, fail_pkk, tarikh_mula_pkk, tarikh_tamat_pkk, fail_cidb_perakuan, tarikh_mula_cidb_perakuan, tarikh_tamat_cidb_perakuan, fail_cidb_perolehan, tarikh_mula_cidb_perolehan, tarikh_tamat_cidb_perolehan, fail_mof, fail_dokumen, perakuan, status_permohonan, status_undi, tarikh_hantar) 
                     VALUES 
                        ('$target_user_id', '$kategori_id', '$nama_syarikat', '$alamat', '$no_pendaftaran', '$gred_cidb_kewangan', '$no_tel', '$email', '$fail_pkk', $tarikh_mula_pkk, $tarikh_tamat_pkk, '$fail_cidb_perakuan', $tarikh_mula_cidb_perakuan, $tarikh_tamat_cidb_perakuan, '$fail_cidb_perolehan', $tarikh_mula_cidb_perolehan, $tarikh_tamat_cidb_perolehan, '$fail_mof', '$fail_dokumen', '$perakuan', 'Baru', 'Pending', NOW())";
    }

    if ($conn->query($sql_save)) {
        echo "<script>
            alert('Permohonan Pendaftaran Kategori Undi berjaya dihantar!');
            window.location.href = 'kontraktor_carian_syarikat.php';
        </script>";
        exit();
    } else {
        echo "<script>alert('Ralat Pangkalan Data: " . $conn->error . "'); window.history.back();</script>";
        exit();
    }
}

// Fungsi untuk menyemak sama ada tarikh tamat telah tamat tempoh
function is_date_expired($tarikh_tamat) {
    if (empty($tarikh_tamat) || $tarikh_tamat == '0000-00-00') {
        return false;
    }
    $today = date('Y-m-d');
    return $today > $tarikh_tamat;
}

// Fungsi untuk memformat tarikh dengan warna merah jika tamat tempoh
function format_date_with_expiry($tarikh, $is_expired) {
    if (empty($tarikh) || $tarikh == '0000-00-00') {
        return '-';
    }
    $formatted = date('d/m/Y', strtotime($tarikh));
    if ($is_expired) {
        return '<span style="color: #dc3545; font-weight: 600;">' . $formatted . '</span>';
    }
    return $formatted;
}

// Fungsi untuk menyemak sama ada mana-mana dokumen telah tamat tempoh
function has_expired_documents($found_profil) {
    $senarai_tarikh_tamat = [
        $found_profil['tarikh_tamat_ssm'] ?? '',
        $found_profil['tarikh_tamat_tcc'] ?? '',
        $found_profil['tarikh_tamat_pkk'] ?? '',
        $found_profil['tarikh_tamat_cidb_perakuan'] ?? '',
        $found_profil['tarikh_tamat_cidb_perolehan'] ?? ''
    ];
    
    foreach ($senarai_tarikh_tamat as $tarikh) {
        if (is_date_expired($tarikh)) {
            return true;
        }
    }
    return false;
}

// Semak sama ada tarikh tamat aktif syarikat telah tamat
$is_aktif_expired = false;
if (!empty($found_profil['tarikh_tamat_aktif']) && $found_profil['tarikh_tamat_aktif'] != '0000-00-00') {
    $is_aktif_expired = is_date_expired($found_profil['tarikh_tamat_aktif']);
}

// Semak sama ada terdapat dokumen yang tamat tempoh
$has_expired = has_expired_documents($found_profil);

// Tentukan sama ada butang hantar boleh ditekan
$disable_submit = $has_expired || $is_aktif_expired;
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carian Syarikat & Pendaftaran Kerja Undi - MDBG</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-mdbg: #8D5B4C;
            --primary-dark: #6e4438;
            --sidebar-width: 280px;
        }

        body { 
            background-color: #e9ecef; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            overflow-x: hidden; 
            margin: 0; 
            padding: 0; 
        }

        .mdbg-navbar { 
            background-color: #ffffff; 
            padding: 0 30px; 
            position: fixed; 
            top: 0; 
            left: 0; 
            right: 0; 
            height: 70px; 
            z-index: 1030; 
            border-bottom: 1px solid #e2e8f0; 
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
            color: #0f172a; 
            line-height: 1.2;
        }

        .mdbg-subtext { 
            font-size: 0.8rem; 
            color: #64748b; 
            font-weight: 400;
        }

        .btn-toggle-sidebar { 
            background: #f1f5f9; 
            border: none; 
            color: #475569; 
            padding: 8px 14px; 
            border-radius: 8px; 
            transition: all 0.2s ease;
        }

        .btn-toggle-sidebar:hover {
            background: #e2e8f0;
            color: #0f172a;
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

        .wrapper { 
            display: flex; 
            margin-top: 70px; 
            min-height: calc(100vh - 70px); 
        }

        .sidebar-container { 
            width: var(--sidebar-width); 
            background-color: #1e293b; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
            flex-shrink: 0; 
            box-shadow: 4px 0 15px rgba(0,0,0,0.05);
            z-index: 1010; 
            position: fixed; 
            top: 70px; 
            bottom: 0; 
            left: 0; 
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

        .submenu-tree { 
            padding-left: 12px; 
            margin: 4px 16px 8px 32px; 
            border-left: 2px solid #334155; 
        }

        .nav-link-sub-item { 
            padding: 9px 14px; 
            margin: 3px 0; 
            border-radius: 8px; 
            color: #94a3b8; 
            font-size: 0.84rem; 
            transition: all 0.2s ease;
            display: flex; 
            align-items: center; 
            text-decoration: none; 
        }

        .nav-link-sub-item:hover {
            background-color: rgba(255, 255, 255, 0.08);
            color: #38bdf8;
            transform: translateX(4px);
        }

        .nav-link-sub-item.active { 
            color: #ffffff; 
            font-weight: 600; 
            background-color: rgba(56, 189, 248, 0.15); 
            border: 1px solid rgba(56, 189, 248, 0.3); 
        }

        .main-content-container { 
            flex-grow: 1; 
            padding: 40px; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
            background-color: #e9ecef; 
            margin-left: var(--sidebar-width); 
            display: flex; 
            justify-content: center; 
        }

        .sidebar-container.collapsed ~ .main-content-container { 
            margin-left: 0; 
        } 
        
        .status-card-container { 
            background-color: #ffffff; 
            border-radius: 14px; 
            border: 1px solid #e2e8f0; 
            padding: 40px; 
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03); 
            width: 100%; 
        }

        .status-header { 
            color: #444749; 
            font-weight: 700; 
            font-size: 1.5rem; 
            letter-spacing: -0.3px;
        }

        .btn-mdbg { 
            background: var(--primary-mdbg); 
            color: white; 
            border: none; 
            padding: 12px 30px; 
            border-radius: 8px; 
            font-weight: 600; 
            transition: all 0.2s ease;
            box-shadow: 0 4px 8px rgba(141, 91, 76, 0.2);
        }

        .btn-mdbg:hover { 
            background: #754a3e; 
            color: white; 
            transform: translateY(-1px);
        }

        .btn-mdbg:disabled {
            background: #adb5bd !important;
            cursor: not-allowed;
            opacity: 0.7;
            box-shadow: none;
            transform: none !important;
        }

        .dokumen-card { 
            background-color: #ffffff; 
            border: 1px solid #e3e6f0; 
            border-radius: 12px; 
            padding: 18px; 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; 
            height: 100%; 
        }

        .dokumen-card.expired {
            border: 2px solid #dc3545 !important;
            background-color: #fff5f5;
        }

        .table-gred th { 
            background-color: #f1f5f9; 
            color: #334155; 
            font-size: 0.85rem; 
            text-transform: uppercase; 
        }

        .seksyen-undi-card {
            background: #ffffff;
            border: 1px solid #bfdbfe;
            border-left: 6px solid #2563eb !important;
            border-radius: 14px;
            box-shadow: 0 4px 20px -2px rgba(37, 99, 235, 0.08);
        }

        .icon-box-undi {
            width: 48px;
            height: 48px;
            background-color: #eff6ff;
            color: #2563eb;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
            border: 1px solid #dbeafe;
        }

        .select-kategori-custom {
            border: 1.5px solid #cbd5e1;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 0.95rem;
            color: #0f172a;
            background-color: #f8fafc;
        }

        .select-kategori-custom:focus {
            background-color: #ffffff;
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
            outline: none;
        }

        .status-pill-info {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
            padding: 8px 14px;
            border-radius: 50rem;
            font-size: 0.82rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .status-pill-danger {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 8px 14px;
            border-radius: 50rem;
            font-size: 0.82rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        /* Gaya Khusus Kad Peringatan Kosong */
        .empty-state-card {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 50px 30px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.04);
        }

        .empty-state-icon-wrapper {
            width: 76px;
            height: 76px;
            background-color: #fef3c7;
            color: #d97706;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 4px solid #fffbeb;
        }

        @media (max-width: 991px) {
            .step-timeline-list-horizontal {
                flex-direction: column;
                gap: 20px;
            }
            .step-timeline-item-horizontal {
                padding-top: 0;
                padding-left: 36px;
            }
            .step-timeline-item-horizontal::before {
                left: 11px;
                top: 22px;
                bottom: -22px;
                right: auto;
                width: 2px;
                height: auto;
                background-color: #cbd5e1;
            }
            .step-number-badge-horizontal {
                top: 2px;
            }
        }

        @media (max-width: 768px) {
            .mdbg-navbar {
                padding: 0 10px;
                display: flex !important;
                flex-direction: row !important;
                align-items: center !important;
                justify-content: space-between !important;
                height: 70px !important;
            }
            .mdbg-navbar > div:first-child {
                display: flex !important;
                align-items: center !important;
                gap: 8px !important; 
            }
            .btn-toggle-sidebar {
                padding: 6px 10px;
            }
            .header-logo-mdbg {
                height: 32px;
                margin: 0 !important;
            }
            .mdbg-brand {
                font-size: 0.8rem;
                letter-spacing: -0.2px;
                white-space: nowrap;
                margin-left: 0 !important; 
            }
            .btn-logout {
                padding: 6px 10px;
                font-size: 0.75rem;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                height: 34px !important;
                white-space: nowrap;
            }
            .btn-logout i {
                margin-right: 4px !important;
            }
            .main-content-container {
                padding: 20px 15px;
                margin-left: 0;
            }
            .sidebar-container {
                position: fixed;
                left: 0;
                top: 70px;
                height: calc(100vh - 70px);
                z-index: 1000;
            }
            .sidebar-container:not(.collapsed) {
                margin-left: 0;
            }
            .sidebar-container.collapsed {
                margin-left: calc(-1 * var(--sidebar-width));
            }
            .floating-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <div class="mdbg-navbar d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <button class="btn-toggle-sidebar" id="sidebarToggle" type="button"><i class="fa-solid fa-bars fs-5"></i></button>
            <div class="d-flex align-items-center gap-2">
                <img src="logo_mdbg.png" alt="Logo MDBG" class="header-logo-mdbg me-2">
                <div>
                    <div class="mdbg-brand">PORTAL KONTRAKTOR MDBG</div>
                    <div class="mdbg-subtext d-none d-md-block">Sistem Pendaftaran & Undi Pembekal Atas Talian</div>
                </div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-dark medium d-none d-md-inline">Selamat Datang, <strong class="text-secondary"><?= htmlspecialchars($nama_paparan); ?></strong></span>
            <a href="logout.php" class="btn-logout d-flex align-items-center btn-logout-mobile"><i class="fa-solid fa-right-from-bracket me-1 me-md-2"></i> <span class="d-none d-md-inline">Log Keluar</span></a>
        </div>
    </div>

    <div class="wrapper">
        <!-- SIDEBAR MENU -->
        <div class="sidebar-container" id="sidebarWrapper">
            <div class="sidebar-menu">
                <div class="sidebar-category-title">Menu Utama</div>
                <a href="kontraktor.php" class="nav-link-item"><i class="fa-solid fa-chart-pie me-2"></i> Dashboard</a>
                <div class="sidebar-category-title">URUSAN PENDAFTARAN</div>
                <?php if (!$has_profil || $is_expired): ?>
                    <a href="kontraktor_borang_daftar.php" class="nav-link-item">
                        <i class="fa-solid fa-file-pen me-2"></i>
                        <span>
                            <?= $is_expired ? 'Fasa 1: Pembaharuan Syarikat' : 'Fasa 1: Daftar Syarikat'; ?>
                        </span>
                    </a>
                <?php endif; ?>
                <a href="#submenuFasa2" class="nav-link-item d-flex align-items-center justify-content-between" data-bs-toggle="collapse" role="button" aria-expanded="true">
                    <div class="d-flex align-items-center"><i class="fa-solid fa-box-archive me-2"></i> Fasa 2: Daftar Kerja Undi</div>
                    <i class="fa-solid fa-chevron-down chevron-icon"></i>
                </a>
                <div class="collapse show" id="submenuFasa2">
                    <div class="submenu-tree">
                        <a href="kontraktor_daftar_undi.php" class="nav-link-sub-item"><i class="fa-solid fa-pen-to-square me-2"></i> Pendaftaran Undi</a>
                        <a href="kontraktor_carian_syarikat.php" class="nav-link-sub-item active"><i class="fa-solid fa-magnifying-glass me-2"></i> Semakan Daftar Undi</a>
                    </div>
                </div>

                <div class="sidebar-category-title">Maklumat & Syarat</div>
                <a href="manual_pengguna.php" class="nav-link-item"><i class="fa-solid fa-book-bookmark me-2"></i> Syarat Pengguna</a>
                <a href="maklumat_kontraktor.php" class="nav-link-item"><i class="fa-solid fa-id-card me-2"></i> Maklumat Kontraktor</a>
            </div>
        </div>

        <!-- MAIN CONTENT CONTAINER -->
        <div class="main-content-container" id="contentWrapper">
            <div class="container-fluid" style="max-width: 1000px;">
                
                <div class="status-card-container mb-4" style="padding: 25px 30px;">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-primary-subtle text-primary p-3 rounded-3 d-none d-sm-block"><i class="fa-solid fa-building-circle-check fs-3"></i></div>
                            <div>
                                <h5 class="status-header mb-1" style="font-size: 1.25rem;">Semakan Syarikat &amp; Pendaftaran Undi</h5>
                                <p class="text-muted small mb-0">Maklumat profil dikunci. Sila pilih Kategori Undi yang layak sahaja.</p>
                            </div>
                        </div>
                        <button type="button" class="btn btn-mdbg text-nowrap" id="btnPaparForm">
                            <i class="fa-solid fa-file-pen me-2"></i> Papar Borang Undi
                        </button>
                    </div>
                </div>

                <!-- SEKSYEN BORANG UNDI -->
                <div id="borangContainer" style="display: block;">
                    <?php if ($found_profil): ?>
                    <div class="status-card-container">
                        
                        <div class="mb-4 text-center text-md-start">
                            <h4 class="status-header mb-2">Borang Pendaftaran Kerja Undi</h4>
                            <p class="text-muted small">Profil dan dokumen syarikat dikunci (Read-Only). Sila pilih <strong>Kategori Undi yang layak</strong> di Seksyen 04 di bawah untuk dihantar.</p>
                            <hr class="mt-0 mb-4" style="opacity: 0.08;">
                        </div>

                        <?php if ($disable_submit): ?>
                        <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                            <i class="fa-solid fa-circle-exclamation fs-4 me-3"></i>
                            <div>
                                <strong>Perhatian!</strong> 
                                <?php if ($is_aktif_expired): ?>
                                    <span class="d-block">Tarikh tamat sah pendaftaran syarikat anda (<?= date('d/m/Y', strtotime($found_profil['tarikh_tamat_aktif'])); ?>) telah tamat tempoh. Sila perbaharui pendaftaran syarikat terlebih dahulu.</span>
                                <?php elseif ($has_expired): ?>
                                    <span class="d-block">Terdapat dokumen yang telah tamat tempoh. Sila kemas kini dokumen yang telah tamat tempoh sebelum menghantar permohonan undi.</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <form action="kontraktor_carian_syarikat.php" method="POST">
                            
                            <input type="hidden" name="target_user_id" value="<?= htmlspecialchars($found_profil['user_id']); ?>">

                            <!-- SEKSYEN 1: KLASIFIKASI -->
                            <div class="row mb-5">
                                <div class="col-md-4 mb-3 mb-md-0">
                                    <h6 class="fw-bold text-dark mb-1"><i class="fa-solid fa-list-check me-2 text-muted"></i> 01. Klasifikasi</h6>
                                    <p class="text-muted small">Maklumat klasifikasi pendaftaran syarikat.</p>
                                </div>
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small text-secondary mb-1">JENIS PENDAFTARAN</label>
                                        <input type="text" class="form-control bg-light fw-bold" value="<?= htmlspecialchars($found_profil['jenis_pendaftaran'] ?? 'TIADA MAKLUMAT'); ?>" readonly>
                                    </div>
                                    <div>
                                        <label class="form-label fw-bold small text-secondary mb-1">KATEGORI PENDAFTARAN</label>
                                        <input type="text" class="form-control bg-light fw-bold" value="<?= htmlspecialchars($found_profil['kategori_pendaftaran'] ?? 'TIADA MAKLUMAT'); ?>" readonly>
                                    </div>
                                </div>
                            </div>

                            <!-- SEKSYEN 2: PROFIL SYARIKAT & GRED -->
                            <div class="row mb-5">
                                <div class="col-md-4 mb-3 mb-md-0">
                                    <h6 class="fw-bold text-dark mb-1"><i class="fa-solid fa-address-card me-2 text-muted"></i> 02. Profil Syarikat</h6>
                                    <p class="text-muted small">Maklumat rasmi dan rekod Gred CIDB syarikat.</p>
                                </div>
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-secondary mb-1">NAMA SYARIKAT</label>
                                        <input type="text" class="form-control bg-light text-uppercase fw-bold" value="<?= htmlspecialchars($found_profil['nama_syarikat'] ?? ''); ?>" readonly>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-secondary mb-1">GRED CIDB / KEWANGAN</label>
                                        <div class="table-responsive">
                                            <table class="table table-bordered align-middle table-gred mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>GRED</th>
                                                        <th>KATEGORI</th>
                                                        <th>PENGKHUSUSAN</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    $gred_rows = [];
                                                    if (!empty($found_profil['gred_cidb_kewangan'])) {
                                                        $raw_data = trim($found_profil['gred_cidb_kewangan']);
                                                        $decoded  = json_decode($raw_data, true);

                                                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                                            foreach ($decoded as $d) {
                                                                $gred_rows[] = [
                                                                    'gred'         => $d['gred'] ?? '-',
                                                                    'kategori'     => $d['kategori'] ?? ($d['kategori_gred'] ?? '-'),
                                                                    'pengkhususan' => $d['pengkhususan'] ?? '-'
                                                                ];
                                                            }
                                                        } else {
                                                            $lines = explode("\n", $raw_data);
                                                            foreach ($lines as $line) {
                                                                $line = trim($line);
                                                                if (empty($line) || stripos($line, 'GRED') !== false) continue;
                                                                
                                                                $cols = preg_split('/\s+/', $line);
                                                                if (!empty($cols[0])) {
                                                                    $gred_rows[] = [
                                                                        'gred'         => $cols[0],
                                                                        'kategori'     => $cols[1] ?? '-',
                                                                        'pengkhususan' => isset($cols[2]) ? implode(' ', array_slice($cols, 2)) : '-'
                                                                    ];
                                                                }
                                                            }
                                                        }
                                                    }

                                                    if (!empty($gred_rows)):
                                                        foreach ($gred_rows as $row):
                                                    ?>
                                                        <tr>
                                                            <td class="fw-bold text-dark"><?= htmlspecialchars($row['gred']); ?></td>
                                                            <td class="text-secondary"><?= htmlspecialchars($row['kategori']); ?></td>
                                                            <td class="text-primary fw-bold"><?= htmlspecialchars($row['pengkhususan']); ?></td>
                                                        </tr>
                                                    <?php 
                                                        endforeach; 
                                                    else: 
                                                    ?>
                                                        <tr>
                                                            <td colspan="3" class="text-muted small text-center py-3">Tiada Rekod Gred Ditemui</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-secondary mb-1">NO. PENDAFTARAN SSM</label>
                                        <input type="text" class="form-control bg-light font-monospace fw-bold" value="<?= htmlspecialchars($found_profil['no_pendaftaran'] ?? ''); ?>" readonly>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-secondary mb-1">ALAMAT SYARIKAT</label>
                                        <textarea class="form-control bg-light text-uppercase" rows="2" readonly><?= htmlspecialchars($found_profil['alamat'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label small fw-bold text-secondary mb-1">NO. TELEFON SYARIKAT</label>
                                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($found_profil['no_telefon_syarikat'] ?? ''); ?>" readonly>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label small fw-bold text-secondary mb-1">ALAMAT E-MEL</label>
                                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($found_profil['email_aktif'] ?? ''); ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SEKSYEN 3: DOKUMEN -->
                            <div class="row mb-5">
                                <div class="col-md-4 mb-3 mb-md-0">
                                    <h6 class="fw-bold text-dark mb-1"><i class="fa-solid fa-folder-open me-2 text-muted"></i> 03. Dokumen Sijil</h6>
                                    <p class="text-muted small">Semakan fail salinan dokumen yang telah dikemukakan.</p>
                                </div>
                                <div class="col-md-8">
                                    <div class="row g-3">
                                        <?php 
                                        $senarai_fail_papar = [
                                            ['label' => 'SSM (Suruhanjaya Syarikat Malaysia)', 'file' => $found_profil['fail_ssm'] ?? '', 'mula' => $found_profil['tarikh_mula_ssm'] ?? '', 'tamat' => $found_profil['tarikh_tamat_ssm'] ?? ''],
                                            ['label' => 'Sijil Pematuhan Cukai (TCC)', 'file' => $found_profil['fail_tcc'] ?? '', 'mula' => $found_profil['tarikh_mula_tcc'] ?? '', 'tamat' => $found_profil['tarikh_tamat_tcc'] ?? ''],
                                            ['label' => 'Sijil Taraf Bumiputera (PKK)', 'file' => $found_profil['fail_pkk'] ?? '', 'mula' => $found_profil['tarikh_mula_pkk'] ?? '', 'tamat' => $found_profil['tarikh_tamat_pkk'] ?? ''],
                                            ['label' => 'Perakuan Pendaftaran CIDB', 'file' => $found_profil['fail_cidb_perakuan'] ?? '', 'mula' => $found_profil['tarikh_mula_cidb_perakuan'] ?? '', 'tamat' => $found_profil['tarikh_tamat_cidb_perakuan'] ?? ''],
                                            ['label' => 'Sijil Perolehan Kerja (CIDB)', 'file' => $found_profil['fail_cidb_perolehan'] ?? '', 'mula' => $found_profil['tarikh_mula_cidb_perolehan'] ?? '', 'tamat' => $found_profil['tarikh_tamat_cidb_perolehan'] ?? ''],
                                            ['label' => 'Sijil MOF', 'file' => $found_profil['fail_mof'] ?? '', 'mula' => '', 'tamat' => '']
                                        ];

                                        foreach ($senarai_fail_papar as $dfail):
                                            // Semak sama ada tarikh tamat telah tamat tempoh
                                            $expired_mula = is_date_expired($dfail['mula']);
                                            $expired_tamat = is_date_expired($dfail['tamat']);
                                            $dokumen_expired = $expired_tamat && !empty($dfail['tamat']) && $dfail['tamat'] != '0000-00-00';
                                        ?>
                                        <div class="col-md-6">
                                            <div class="dokumen-card <?= $dokumen_expired ? 'expired' : ''; ?>">
                                                <div>
                                                    <div class="fw-bold small text-dark mb-1"><?= $dfail['label']; ?></div>
                                                    <?php if (!empty($dfail['mula']) || !empty($dfail['tamat'])): ?>
                                                        <div class="text-muted small mb-2" style="font-size: 0.75rem;">
                                                            Sah: <?= !empty($dfail['mula']) ? date('d/m/Y', strtotime($dfail['mula'])) : '-'; ?> 
                                                            - 
                                                            <?= !empty($dfail['tamat']) ? format_date_with_expiry($dfail['tamat'], $expired_tamat) : '-'; ?>
                                                            <?php if ($dokumen_expired): ?>
                                                                <span class="badge bg-danger ms-1">TAMAT</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mt-2">
                                                    <?php if (!empty($dfail['file'])): ?>
                                                        <a href="uploads/<?= htmlspecialchars($dfail['file']); ?>" target="_blank" class="btn btn-sm btn-outline-danger w-100 fw-bold">
                                                            <i class="fa-solid fa-file-pdf me-1"></i> Lihat Dokumen
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary w-100 py-1">Tiada Dokumen</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- PERAKUAN SYARIKAT -->
                            <div class="p-4 rounded border border-warning mb-4" style="background-color: #fffdf5;">
                                <h6 class="fw-bold text-dark d-flex align-items-center mb-2">
                                    <i class="fa-solid fa-triangle-exclamation text-warning me-2 fs-5"></i> PERAKUAN SYARIKAT <span class="text-danger ms-1">*</span>
                                </h6>
                                <p class="small text-secondary mb-3" style="line-height: 1.6;">
                                    Dengan ini, kami selaku pengurus atau wakil syarikat mengesahkan bahawa segala maklumat dan dokumen yang dikemukakan adalah benar dan tepat.
                                </p>
                                <div class="form-check">
                                    <input class="form-check-input border-warning" type="checkbox" name="perakuan_setuju" value="SETUJU" id="checkSetuju" required checked>
                                    <label class="form-check-label small fw-bold text-dark" for="checkSetuju" style="cursor:pointer;">SETUJU</label>
                                </div>
                            </div>

                            <!-- SEKSYEN 4: PEMILIHAN KATEGORI UNDI -->
                            <?php
                            $gred_kontraktor = [];
                            $pengkhususan = [];

                            if (!empty($found_profil['gred_cidb_kewangan'])) {
                                $raw_gred = trim($found_profil['gred_cidb_kewangan']);
                                $decoded_gred = json_decode($raw_gred, true);

                                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_gred)) {
                                    foreach ($decoded_gred as $rg) {
                                        if (!empty($rg['gred'])) {
                                            $gred_kontraktor[] = strtoupper(trim($rg['gred']));
                                        }
                                        $khus_str = $rg['pengkhususan'] ?? '';
                                        if (!empty($khus_str)) {
                                            $parts = preg_split('/[\s,]+/', strtoupper(trim($khus_str)));
                                            foreach ($parts as $p) if ($p) $pengkhususan[] = $p;
                                        }
                                    }
                                } else {
                                    $lines = explode("\n", $raw_gred);
                                    foreach ($lines as $line) {
                                        $line = trim($line);
                                        if (empty($line) || stripos($line, 'GRED') !== false) continue;
                                        
                                        $cols = preg_split('/\s+/', $line);
                                        if (!empty($cols[0])) {
                                            $gred_kontraktor[] = strtoupper($cols[0]);
                                        }
                                        if (isset($cols[2])) {
                                            $khus_list = array_slice($cols, 2);
                                            foreach ($khus_list as $khus) {
                                                $parts = preg_split('/[\s,]+/', strtoupper(trim($khus)));
                                                foreach ($parts as $p) if ($p) $pengkhususan[] = $p;
                                            }
                                        }
                                    }
                                }
                            }

                            $gred_kontraktor = array_unique(array_filter($gred_kontraktor));
                            $pengkhususan = array_unique(array_filter($pengkhususan));

                            // Cari Kategori Utama daripada jadual `kategori_undi`
                            $kategori_layak = [];
                            $query_kat = $conn->query("SELECT * FROM kategori_undi ORDER BY id ASC");

                            if ($query_kat && $query_kat->num_rows > 0) {
                                while ($kat = $query_kat->fetch_assoc()) {
                                    $gred_k_raw = strtoupper(trim($kat['gred_kelayakan'] ?? ''));
                                    $gred_k_list = array_filter(preg_split('/[\s,]+/', $gred_k_raw));

                                    $peng_k_raw = strtoupper(trim($kat['pengkhususan'] ?? ''));
                                    $peng_k_list = array_filter(preg_split('/[\s,]+/', $peng_k_raw));

                                    $layak_gred = empty($gred_k_list) || count(array_intersect($gred_k_list, $gred_kontraktor)) > 0;
                                    $layak_peng = empty($peng_k_list) || count(array_intersect($peng_k_list, $pengkhususan)) > 0;

                                    if ($layak_gred && $layak_peng) {
                                        $kategori_layak[] = $kat;
                                    }
                                }   
                            }
                            ?>

                            <div class="row mb-4 p-4 seksyen-undi-card align-items-center">
                                <div class="col-md-5 mb-3 mb-md-0">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="icon-box-undi">
                                            <i class="fa-solid fa-box-archive"></i>
                                        </div>
                                        <div>
                                            <span class="badge bg-primary-subtle text-primary fw-bold mb-1" style="font-size: 0.72rem;">SEKSYEN 04</span>
                                            <h6 class="fw-bold text-dark mb-1" style="font-size: 1.05rem;">Pemilihan Kategori Undi</h6>
                                            <p class="text-muted small mb-0" style="font-size: 0.83rem;">
                                                Sila pilih Kategori Undi yang hendak didaftarkan mengikut kelayakan CIDB syarikat anda.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-7 ps-md-4">
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold text-dark text-uppercase mb-2">
                                            PILIH KATEGORI UNDI <span class="text-danger">*</span>
                                        </label>
                                        <select name="kategori_id" id="kategoriSelect" class="form-select form-select-lg select-kategori-custom fw-bold" required <?= $disable_submit ? 'disabled' : ''; ?>>
                                            <option value="" disabled <?= empty($existing_undi['kategori_id']) ? 'selected' : ''; ?>>-- Pilih Kategori Yang Layak --</option>
                                            <?php if (!empty($kategori_layak)): ?>
                                                <?php foreach ($kategori_layak as $kat): ?>
                                                    <?php $peng_disp = !empty($kat['pengkhususan']) ? htmlspecialchars($kat['pengkhususan']) : 'Terbuka'; ?>
                                                    <option value="<?= $kat['id']; ?>" <?= (isset($existing_undi['kategori_id']) && $existing_undi['kategori_id'] == $kat['id']) ? 'selected' : ''; ?>>
                                                        <?= htmlspecialchars($kat['pilihan']); ?> - (<?= $peng_disp; ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <option value="" disabled>Tiada Kategori Undi yang sepadan dengan Gred CIDB syarikat anda.</option>
                                            <?php endif; ?>
                                        </select>
                                    </div>

                                    <?php if (!empty($kategori_layak)): ?>
                                        <div class="mt-2">
                                            <?php if ($disable_submit): ?>
                                            <div class="status-pill-danger">
                                                <i class="fa-solid fa-circle-exclamation"></i>
                                                <span>Permohonan tidak boleh dihantar. Sila kemas kini dokumen yang telah tamat tempoh.</span>
                                            </div>
                                            <?php else: ?>
                                            <div class="status-pill-info">
                                                <i class="fa-solid fa-circle-check text-success"></i>
                                                <span>Pilihan dipadankan secara automatik mengikut Gred &amp; Pengkhususan CIDB anda.</span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- BUTANG HANTAR -->
                            <div class="d-flex justify-content-center align-items-center mt-4 pt-4 border-top">
                                <button type="submit" name="submit_undi" class="btn btn-mdbg w-100 btn-lg" <?= (empty($kategori_layak) || $disable_submit) ? 'disabled' : ''; ?>>
                                    <i class="fa-solid fa-paper-plane me-2"></i> Hantar Permohonan Kategori Undi
                                </button>
                            </div>

                            <?php if ($disable_submit): ?>
                            <div class="mt-3 text-center">
                                <small class="text-danger fw-bold">
                                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                                    Permohonan tidak boleh dihantar kerana terdapat dokumen yang telah tamat tempoh atau tarikh tamat sah syarikat telah tamat.
                                </small>
                            </div>
                            <?php endif; ?>

                        </form>
                    </div>

                    <?php else: ?>
                    <!-- KAD AMARAN DAFTAR SYARIKAT (KEMAS & PROFESIONAL) -->
                    <div class="empty-state-card text-center">
                        <div class="empty-state-icon-wrapper mb-3">
                            <i class="fa-solid fa-triangle-exclamation fs-2"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-2" style="font-size: 1.35rem; letter-spacing: -0.3px;">
                            Sila daftar syarikat terlebih dahulu
                        </h4>
                        <p class="text-muted small mb-4 mx-auto" style="max-width: 440px; font-size: 0.88rem; line-height: 1.6;">
                            Anda belum mendaftar maklumat profil syarikat di Fasa 1. Sila lengkapkan pendaftaran profil syarikat anda terlebih dahulu untuk melayakan diri.
                        </p>
                        <a href="kontraktor_borang_daftar.php" class="btn btn-mdbg px-4 py-2-5">
                            <i class="fa-solid fa-building-circle-check me-2"></i> Daftar Sekarang
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebarWrapper').classList.toggle('collapsed');
        });
        document.getElementById('btnPaparForm').addEventListener('click', function() {
            var container = document.getElementById('borangContainer');
            if (container.style.display === 'none') {
                container.style.display = 'block';
                this.innerHTML = '<i class="fa-solid fa-eye-slash me-2"></i> Sembunyi Borang';
            } else {
                container.style.display = 'none';
                this.innerHTML = '<i class="fa-solid fa-file-pen me-2"></i> Papar Borang Undi';
            }
        });
    </script>
</body>
</html>