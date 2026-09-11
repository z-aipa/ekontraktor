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

// AMBIL DATA PROFIL SEDIA ADA
include 'db.php';

// --- SEKATAN AKSES JIKA SUDAH BERDAFTAR / AKTIF --- //
$user_id = $_SESSION['user_id'];
$profil = $conn->query("SELECT status_borang, tarikh_mula_aktif, tarikh_tamat_aktif, status_bayaran_daftar FROM kontraktor_profil WHERE user_id='$user_id'")->fetch_assoc();

$today = date('Y-m-d');
$is_status_aktif = false;

$is_expired = false;

if (
    !empty($profil['tarikh_tamat_aktif']) &&
    $profil['tarikh_tamat_aktif'] != '0000-00-00' &&
    $today > $profil['tarikh_tamat_aktif']
) {
    $is_expired = true;
    $is_status_aktif = false;
}

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
    }
}

// Sekat akses jika akaun sudah aktif
if ($is_status_aktif) {
    echo "<script>alert('Pendaftaran syarikat anda masih aktif.'); window.location.href='kontraktor.php';</script>";
    exit();
}
// --------------------------------------------------

// SEMAK STATUS SEKATAN GLOBAL BORANG DAFTAR
$check_sekat_daftar = $conn->query("SELECT nilai FROM tetapan_sistem WHERE kunci = 'sekat_borang_daftar'");
if ($check_sekat_daftar && $check_sekat_daftar->num_rows > 0) {
    $is_blocked = intval($check_sekat_daftar->fetch_assoc()['nilai']);
    if ($is_blocked === 1) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<body style='font-family:sans-serif;'>
        <script>
            setTimeout(function() {
                Swal.fire({
                    title: 'Akses Disekat!',
                    text: 'Borang Pendaftaran Syarikat ini telah ditutup/disekat oleh Pihak Pentadbir.',
                    icon: 'error',
                    confirmButtonText: 'Kembali ke Dashboard',
                    confirmButtonColor: '#8D5B4C',
                    allowOutsideClick: false
                }).then(() => {
                    window.location.href = 'kontraktor.php';
                });
            }, 100);
        </script>
        </body>";
        exit();
    }
}

$user_id = $_SESSION['user_id'];

// Ambil maklumat jenis akaun & nama penuh daripada users
$user_data = $conn->query("SELECT jenis_akaun, nama_penuh FROM users WHERE id='$user_id'")->fetch_assoc();

$profil = $conn->query("SELECT * FROM kontraktor_profil WHERE user_id='$user_id'")->fetch_assoc();
$has_profil = ($profil) ? true : false;

// Logik menentukan Nama Paparan di Header (Menggunakan nama_penuh atau username) & Diformat ke UPPERCASE
$nama_paparan = strtoupper(!empty($user_data['nama_penuh']) ? $user_data['nama_penuh'] : ($_SESSION['username'] ?? 'Kontraktor'));
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fasa 1: Borang Pendaftaran Kontraktor - MDBG</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-mdbg: #8D5B4C;
            --primary-dark: #6e4438;
            --primary-light: #fdfaf9;
            --sidebar-width: 280px;
            --text-dark: #333333;
            --text-muted: #6c757d;
            --border-color: #e3e6f0;
        }

        body {
            background-color: #e9ecef;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
            margin: 0;
            padding: 0;
            height: 100%;

        }
        
        /* --- MODERN WHITE NAVBAR --- */
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

        html {
            height: 100%;
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
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: fixed;
            top: 70px;
            bottom: 0;
            left: 0;
            overflow-y: visible !important;
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

        .main-content-container {
            flex-grow: 1;
            padding: 40px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background-color: #e9ecef;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            margin-left: var(--sidebar-width);
            display: flex;
            justify-content: center;
        }

        .main-content-container > .container-fluid,
        .main-content-container > div {
            width: 100%;
            max-width: 1100px; 
            margin: 0 auto;    
        }
        
        .sidebar-container.collapsed ~ .main-content-container {
            margin-left: 0;   
        }

        .status-card-container {
            background-color: #f8fafc;
            border-radius: 14px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            padding: 40px;
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.03);
            width: 100%;
        }
        
        .status-header {
            color: #444749;
            font-weight: 700;
            font-size: 1.5rem;
            letter-spacing: -0.3px;
        }

        .section-header { 
            border-left: 5px solid var(--primary-mdbg); 
            padding-left: 15px; 
            margin-bottom: 25px; 
            color: #555555;
        }
        .section-header h5 {
            font-weight: 700;
            margin-bottom: 0;
        }
        .form-label { 
            font-weight: 700; 
            color: #555555;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-control, .form-select {
            border-radius: 8px;
            padding: 11px 15px;
            border: 1px solid #dcdcdc;
            font-size: 0.95rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-mdbg);
            box-shadow: 0 0 0 0.2rem rgba(141, 91, 76, 0.15);
        }

        /* Atau lebih gelap sikit */
        .form-control::placeholder {
            color: #424040 !important;    /* Kelabu sederhana */
            opacity: 0.7 !important;
            text-transform: none !important;  /* <-- Pastikan placeholder tidak uppercase */

        }

        .upload-box { 
            background: #fdfaf9; 
            border: 1px dashed #d8c3c1; 
            border-radius: 12px; 
            padding: 20px; 
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
            box-shadow: 0 6px 12px rgba(141, 91, 76, 0.3);
        }

        @media (max-width: 768px) {
            .mdbg-navbar {
                padding: 0 10px;
                display: flex !important;
                flex-direction: row !important;
                align-items: center !important;
                justify-content: space-between !important;
                height: 70px !important;
                position: relative !important;
                top: auto !important;
                left: auto !important;
                right: auto !important;
            }
            .wrapper {
                margin-top: 0 !important;
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
                margin-left: 0 !important;
                width: 100% !important;
            }
             .sidebar-container {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                height: 100% !important;
                z-index: 1000 !important;
                width: var(--sidebar-width) !important;
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
                transform: translateX(0) !important;
                overflow-y: auto !important;
            }
            .sidebar-container.collapsed {
                transform: translateX(-100%) !important;
                margin-left: 0 !important;
            }
        }

        .form-check-input[type="radio"] {
            width: 20px !important;
            height: 20px !important;
            flex-shrink: 0 !important; 
            border-radius: 50% !important; 
            cursor: pointer;
            border: 2px solid #ced4da;
        }

        .form-check-label {
            cursor: pointer;
            user-select: none;
            margin-bottom: 0;
        }

        .dokumen-card {
            background-color: #ffffff;
            border: 1px solid #e3e6f0;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02);
            transition: all 0.2s ease-in-out;
        }
        
        .dokumen-card:hover {
            border-color: #d8c3c1;
            box-shadow: 0 6px 12px rgba(141, 91, 76, 0.06);
        }

        .dokumen-header-title {
            font-size: 0.88rem;
            font-weight: 700;
            color: #555555;
            min-height: 42px;
            display: flex;
            align-items: flex-start;
            margin-bottom: 12px;
            line-height: 1.4;
        }

        .tarikh-container {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 12px;
            margin-top: 12px;
            border: 1px solid #e2e8f0;
        }
        
        .tarikh-title {
            font-size: 0.7rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 4px;
            letter-spacing: 0.5px;
        }

        .tarikh-container input[type="date"] {
            padding: 5px 4px !important;
            font-size: 0.78rem !important;
            letter-spacing: -0.3px;
        }

        .dokumen-footer-action {
            margin-top: auto;
            padding-top: 14px;
            border-top: 1px dashed #f1f5f9;
        }

        /* Styling Jadual Gred, Kategori & Pengkhususan */
        .table-gred th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #cbd5e1;
        }
        .table-gred input {
            font-weight: 600;
        }
    </style>
</head>
<body>

    <div class="mdbg-navbar d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <button class="btn-toggle-sidebar" id="sidebarToggle" type="button" title="Sembunyikan/Paparkan Menu">
                <i class="fa-solid fa-bars fs-5"></i>
            </button>
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
        
        <div class="sidebar-container" id="sidebarWrapper">
            <div class="sidebar-menu">
                <div class="sidebar-category-title">Menu Utama</div>
                <?php 
                    $jenis_akaun = $user_data['jenis_akaun'] ?? 'Syarikat';
                    $dashboard_url = 'kontraktor.php';
                    if ($jenis_akaun == 'Individu' && isset($_SESSION['semak_individu']) && $_SESSION['semak_individu'] === true) {
                        $dashboard_url = 'kontraktor.php?semak=1';
                    }
                ?>
                <a href="<?= $dashboard_url; ?>" class="nav-link-item">
                    <i class="fa-solid fa-chart-pie me-2"></i> Dashboard
                </a>
                <div class="sidebar-category-title">URUSAN PENDAFTARAN</div>

                <!-- Fasa 1 -->
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
            <div class="container-fluid" style="max-width: 1000px;">
                
                <div class="status-card-container">
                    <div class="mb-5 text-center text-md-start">
                        <h4 class="status-header mb-2">Borang Pendaftaran Baharu / Pembaharuan Pendaftaran</h4>
                        <p class="text-muted small">Sila lengkapkan maklumat profil korporat dan muat naik dokumen sokongan syarikat anda di bawah.</p>
                        <hr class="mt-0 mb-4" style="opacity: 0.08;">
                    </div>
                    
                    <form action="proses_daftar.php" method="POST" enctype="multipart/form-data">

                        <!-- SEKSYEN 1: Klasifikasi -->
                        <div class="row mb-5">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <h6 class="fw-bold text-dark mb-1"><i class="fa-solid fa-list-check me-2 text-muted"></i> 01. Klasifikasi</h6>
                                <p class="text-muted small">Tentukan jenis permohonan pendaftaran serta pengkhususan syarikat.</p>
                            </div>
                            <div class="col-md-8">
                                <div class="mb-4">
                                    <label class="form-label fw-bold small text-secondary mb-2">JENIS PENDAFTARAN <span class="text-danger">*</span></label>
                                    <select name="jenis_pendaftaran" class="form-select" required>
                                        <option value="" disabled <?= !$has_profil ? 'selected' : ''; ?>>-- Pilih Jenis Pendaftaran --</option>
                                        <?php if ($is_expired): ?>
                                            <!-- Jika tarikh tamat telah expired, hanya pilihan Pembaharuan sahaja -->
                                            <option value="PEMBAHARUAN PENDAFTARAN (RM52.00)" <?= ($has_profil && $profil['jenis_pendaftaran'] == 'PEMBAHARUAN PENDAFTARAN (RM52.00)') ? 'selected' : ''; ?>>PEMBAHARUAN PENDAFTARAN (RM52.00)</option>
                                        <?php else: ?>
                                            <!-- Jika tidak expired, papar kedua-dua pilihan -->
                                            <option value="PENDAFTARAN BAHARU (RM52.00)" <?= ($has_profil && $profil['jenis_pendaftaran'] == 'PENDAFTARAN BAHARU (RM52.00)') ? 'selected' : ''; ?>>PENDAFTARAN BAHARU (RM52.00)</option>
                                            <option value="PEMBAHARUAN PENDAFTARAN (RM52.00)" <?= ($has_profil && $profil['jenis_pendaftaran'] == 'PEMBAHARUAN PENDAFTARAN (RM52.00)') ? 'selected' : ''; ?>>PEMBAHARUAN PENDAFTARAN (RM52.00)</option>
                                        <?php endif; ?>
                                    </select>
                                    <span class="text-muted small d-block mt-2"><i class="fa-solid fa-circle-info me-1 text-primary"></i> Tarikh akhir sah sijil pendaftaran adalah (1) tahun berikutnya.</span>
                                </div>
                                <div>
                                    <label class="form-label fw-bold small text-secondary mb-2">KATEGORI PENDAFTARAN <span class="text-danger">*</span></label>
                                    <div class="d-flex flex-column flex-sm-row gap-3">
                                        <div class="form-check border p-3 rounded w-100 bg-light d-flex align-items-center" style="gap: 10px;">
                                            <input class="form-check-input m-0" type="checkbox" name="kategori_pendaftaran[]" id="katKerja" value="KONTRAKTOR KERJA" <?= ($has_profil && strpos($profil['kategori_pendaftaran'], 'KONTRAKTOR KERJA') !== false) ? 'checked' : ''; ?>>
                                            <label class="form-check-label fw-semibold" for="katKerja" style="cursor:pointer;">Kontraktor Kerja</label>
                                        </div>
                                        <div class="form-check border p-3 rounded w-100 bg-light d-flex align-items-center" style="gap: 10px;">
                                            <input class="form-check-input m-0" type="checkbox" name="kategori_pendaftaran[]" id="katPembekal" value="KONTRAKTOR PEMBEKALAN/PERKHIDMATAN" <?= ($has_profil && strpos($profil['kategori_pendaftaran'], 'KONTRAKTOR PEMBEKALAN/PERKHIDMATAN') !== false) ? 'checked' : ''; ?>>
                                            <label class="form-check-label fw-semibold" for="katPembekal" style="cursor:pointer;">Kontraktor Pembekalan / Perkhidmatan</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SEKSYEN 2: Profil Syarikat -->
                        <div class="row mb-5">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <h6 class="fw-bold text-dark mb-1"><i class="fa-solid fa-address-card me-2 text-muted"></i> 02. Profil Syarikat</h6>
                                <p class="text-muted small">Maklumat perhubungan rasmi entiti perniagaan anda.</p>
                            </div>
                            <div class="col-md-8">
                                <div class="section-header"><h5>Maklumat Entiti</h5></div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-secondary mb-2">NAMA SYARIKAT <span class="text-danger">*</span></label>
                                    <input type="text" name="nama_syarikat" class="form-control text-uppercase" placeholder="Masukkan nama penuh syarikat" value="<?= htmlspecialchars($profil['nama_syarikat'] ?? ''); ?>" required>
                                </div>
                                
                                <!-- BAHAGIAN DITUKAR: JADUAL GRED, KATEGORI & PENGKHUSUSAN -->
                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-secondary mb-2">GRED CIDB ATAU KEWANGAN <span class="text-danger">*</span></label>
                                    <div class="table-responsive">
                                        <table class="table table-bordered align-middle table-gred mb-2" id="jadualGred">
                                            <thead>
                                                <tr>
                                                    <th style="width: 25%;"><u>GRED</u></th>
                                                    <th style="width: 25%;"><u>KATEGORI</u></th>
                                                    <th style="width: 40%;"><u>PENGKHUSUSAN</u></th>
                                                    <th style="width: 10%; text-align: center;">TINDAKAN</th>
                                                </tr>
                                            </thead>
                                            <tbody id="gredTableBody">
                                               <?php 
                                                    $gred_rows = [];
                                                    if ($has_profil && !empty($profil['gred_cidb_kewangan'])) {
                                                        $decoded = json_decode($profil['gred_cidb_kewangan'], true);
                                                        if (is_array($decoded)) {
                                                            // Jika data dalam format JSON
                                                            $gred_rows = $decoded;
                                                        } else {
                                                            // Jika data dalam format Teks (Sama ada berserta tajuk atau teks bersih)
                                                            $lines = explode("\n", trim($profil['gred_cidb_kewangan']));
                                                            foreach ($lines as $line) {
                                                                $line = trim($line);
                                                                // Abaikannya jika baris kosong atau mengandungi perkataan header 'GRED'
                                                                if (empty($line) || stripos($line, 'GRED') !== false) {
                                                                    continue; 
                                                                }
                                                                
                                                                // Pecahkan mengikut ruang aksara (space/tab)
                                                                $parts = preg_split('/\s+/', $line);
                                                                if (!empty($parts[0])) {
                                                                    $gred_rows[] = [
                                                                        'gred'         => $parts[0] ?? '',
                                                                        'kategori'     => $parts[1] ?? '',
                                                                        'pengkhususan' => count($parts) > 2 ? implode(' ', array_slice($parts, 2)) : ''
                                                                    ];
                                                                }
                                                            }
                                                        }
                                                    }

                                                    // JIKA TIADA DATA (KONTRAKTOR BAHARU), TETAPKAN 1 BARIS KOSONG
                                                    if (empty($gred_rows)) {
                                                        $gred_rows = [
                                                            ['gred' => '', 'kategori' => '', 'pengkhususan' => '']
                                                        ];
                                                    }

                                                    foreach ($gred_rows as $row): 
                                                    ?>
                                                <tr>
                                                    <td>
                                                        <input type="text" name="gred[]" class="form-control form-control-sm text-uppercase" placeholder="Contoh:G1" value="<?= htmlspecialchars($row['gred'] ?? ''); ?>" required>
                                                    </td>
                                                    <td>
                                                        <input type="text" name="kategori_gred[]" class="form-control form-control-sm text-uppercase" placeholder="Contoh:B" value="<?= htmlspecialchars($row['kategori'] ?? ''); ?>">
                                                    </td>
                                                    <td>
                                                        <input type="text" name="pengkhususan[]" class="form-control form-control-sm text-uppercase" placeholder="Contoh:B04" value="<?= htmlspecialchars($row['pengkhususan'] ?? ''); ?>">
                                                    </td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row" title="Hapus Baris"><i class="fa-solid fa-trash"></i></button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary fw-bold" id="btnAddGredRow">
                                        <i class="fa-solid fa-plus me-1"></i> Tambah Baris (Add Row)
                                    </button>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-secondary mb-2">NO. PENDAFTARAN SSM <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           name="no_pendaftaran" 
                                           class="form-control text-uppercase" 
                                           placeholder="CONTOH: IP03XXXXXX (10 KARAKTER)" 
                                           maxlength="10" 
                                           pattern="[A-Z0-9]{10}"
                                           title="Sila masukkan tepat 10 karakter alfanumerik sahaja (tanpa simbol @ jarak)"
                                           oninput="validateSSM(this)"
                                           value="<?= htmlspecialchars($profil['no_pendaftaran'] ?? ''); ?>"
                                           required>
                                    <div class="form-text text-muted small mt-1">Format wajib tepat 10 karakter numerik (tanpa sebarang simbol atau ruang).</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-secondary mb-2">ALAMAT SYARIKAT <span class="text-danger">*</span></label>
                                    <textarea name="alamat" class="form-control text-uppercase" rows="3" placeholder="MASUKKAN ALAMAT PENUH SYARIKAT" required><?= htmlspecialchars($profil['alamat'] ?? ''); ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3 d-flex flex-column">
                                        <label class="form-label small fw-bold text-secondary mb-2">NO. TELEFON SYARIKAT <span class="text-danger">*</span></label>
                                        <input type="text" 
                                               name="no_telefon_syarikat" 
                                               class="form-control mt-auto" 
                                               placeholder="Contoh: 053632111" 
                                               value="<?= htmlspecialchars($profil['no_telefon_syarikat'] ?? ''); ?>" 
                                               pattern="[0-9]+" 
                                               title="Sila masukkan nombor sahaja tanpa ruang atau simbol" 
                                               oninput="this.value = this.value.replace(/[^0-9]/g, '')" 
                                               required>
                                    </div>
                                    <div class="col-md-6 mb-3 d-flex flex-column">
                                        <label class="form-label small fw-bold text-secondary mb-2">NO. TELEFON (PENGURUS SYARIKAT) <span class="text-danger">*</span></label>
                                        <input type="text" 
                                               name="no_telefon_pengurus" 
                                               class="form-control" 
                                               placeholder="Contoh: 0123456789" 
                                               value="<?= htmlspecialchars($profil['no_telefon_pengurus'] ?? ''); ?>" 
                                               pattern="[0-9]+" 
                                               title="Sila masukkan nombor sahaja tanpa ruang atau simbol" 
                                               oninput="this.value = this.value.replace(/[^0-9]/g, '')" 
                                               required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-secondary mb-2">ALAMAT E-MEL YANG AKTIF <span class="text-danger">*</span></label>
                                    <input type="email" name="email_aktif" class="form-control" placeholder="syarikat@email.com" value="<?= htmlspecialchars($profil['email_aktif'] ?? ''); ?>" required>
                                    <div class="form-text text-muted small mt-1">Sijil pendaftaran akan dikemukakan melalui alamat e-mel tersebut.</div>
                                </div>
                            </div>
                        </div>

                        <!-- SEKSYEN 3: Dokumen -->
                        <div class="row mb-5">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <div class="alert alert-danger border-2 shadow-sm p-3" style="border-left: 5px solid #dc3545; background-color: #fff5f5; border-radius: 8px;">
                                    <h6 class="fw-bold text-danger mb-2">
                                        <i class="fa-solid fa-cloud-arrow-up me-2"></i> 03. Dokumen
                                    </h6>
                                    <p class="mb-0 text-dark small fw-semibold" style="line-height: 1.5;">
                                        Muat naik salinan syarikat yang sah dalam format PDF sahaja (MAX 10MB) serta masukkan tarikh tempoh sah laku dokumen.
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="section-header"><h5>Lampiran Sijil Fail & Tarikh Sah Laku</h5></div>
                                
                                <div class="row g-4">
                                    
                                    <!-- 1. SSM -->
                                    <div class="col-md-6">
                                        <div class="dokumen-card">
                                            <div>
                                                <div class="dokumen-header-title">
                                                    SURUHANJAYA SYARIKAT MALAYSIA (SSM) <span class="text-danger ms-1">*</span>
                                                </div>
                                                <input type="file" name="fail_ssm" class="form-control form-control-sm mb-2" accept=".pdf" <?= ($has_profil && !empty($profil['fail_ssm'])) ? '' : 'required'; ?>>
                                                
                                                <div class="tarikh-container">
                                                    <div class="row g-3">
                                                        <div class="col-12 col-xl-6">
                                                            <div class="tarikh-title">Tarikh Mula</div>
                                                            <input type="date" name="tarikh_mula_ssm" class="form-control form-control-sm w-100" value="<?= htmlspecialchars($profil['tarikh_mula_ssm'] ?? ''); ?>" required>
                                                        </div>
                                                        <div class="col-12 col-xl-6">
                                                            <div class="tarikh-title">Tarikh Tamat</div>
                                                            <input type="date" name="tarikh_tamat_ssm" class="form-control form-control-sm w-100" value="<?= htmlspecialchars($profil['tarikh_tamat_ssm'] ?? ''); ?>" required>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="dokumen-footer-action d-flex justify-content-between align-items-center">
                                                <?php if ($has_profil && !empty($profil['fail_ssm'])): ?>
                                                    <a href="uploads/<?= htmlspecialchars($profil['fail_ssm']); ?>" target="_blank" class="badge bg-danger-subtle text-danger border border-danger-subtle py-1.5 px-2 text-decoration-none rounded small"><i class="fa-solid fa-file-pdf me-1"></i> Lihat Dokumen</a>
                                                <?php else: ?>
                                                    <span class="text-muted small italic" style="font-size:0.75rem;">Belum ada dokumen</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- 2. TCC -->
                                    <div class="col-md-6">
                                        <div class="dokumen-card">
                                            <div>
                                                <div class="dokumen-header-title">
                                                    SIJIL PEMATUHAN CUKAI (TCC) <span class="text-danger ms-1">*</span>
                                                </div>
                                                <input type="file" name="fail_tcc" class="form-control form-control-sm mb-2" accept=".pdf" <?= ($has_profil && !empty($profil['fail_tcc'])) ? '' : 'required'; ?>>
                                                
                                                <div class="tarikh-container">
                                                    <div class="row g-3">
                                                        <div class="col-12 col-xl-6">
                                                            <div class="tarikh-title">Tarikh Mula</div>
                                                            <input type="date" name="tarikh_mula_tcc" class="form-control form-control-sm w-100" value="<?= htmlspecialchars($profil['tarikh_mula_tcc'] ?? ''); ?>" required>
                                                        </div>
                                                        <div class="col-12 col-xl-6">
                                                            <div class="tarikh-title">Tarikh Tamat</div>
                                                            <input type="date" name="tarikh_tamat_tcc" class="form-control form-control-sm w-100" value="<?= htmlspecialchars($profil['tarikh_tamat_tcc'] ?? ''); ?>" required>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="dokumen-footer-action d-flex justify-content-between align-items-center">
                                                <?php if ($has_profil && !empty($profil['fail_tcc'])): ?>
                                                    <a href="uploads/<?= htmlspecialchars($profil['fail_tcc']); ?>" target="_blank" class="badge bg-danger-subtle text-danger border border-danger-subtle py-1.5 px-2 text-decoration-none rounded small"><i class="fa-solid fa-file-pdf me-1"></i> Lihat Dokumen</a>
                                                <?php else: ?>
                                                    <span class="text-muted small italic" style="font-size:0.75rem;">Belum ada dokumen</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- 3. PKK -->
                                    <div class="col-md-6">
                                        <div class="dokumen-card">
                                            <div>
                                                <div class="dokumen-header-title">
                                                    PUSAT KHIDMAT KONTRAKTOR SIJIL TARAF BUMIPUTERA
                                                </div>
                                                <input type="file" name="fail_pkk" class="form-control form-control-sm mb-2" accept=".pdf">
                                                
                                                <div class="tarikh-container">
                                                    <div class="row g-3">
                                                        <div class="col-12 col-xl-6">
                                                            <div class="tarikh-title">Tarikh Mula</div>
                                                            <input type="date" name="tarikh_mula_pkk" class="form-control form-control-sm w-100" value="<?= htmlspecialchars($profil['tarikh_mula_pkk'] ?? ''); ?>">
                                                        </div>
                                                        <div class="col-12 col-xl-6">
                                                            <div class="tarikh-title">Tarikh Tamat</div>
                                                            <input type="date" name="tarikh_tamat_pkk" class="form-control form-control-sm w-100" value="<?= htmlspecialchars($profil['tarikh_tamat_pkk'] ?? ''); ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="dokumen-footer-action d-flex justify-content-between align-items-center">
                                                <?php if ($has_profil && !empty($profil['fail_pkk'])): ?>
                                                    <a href="uploads/<?= htmlspecialchars($profil['fail_pkk']); ?>" target="_blank" class="badge bg-danger-subtle text-danger border border-danger-subtle py-1.5 px-2 text-decoration-none rounded small"><i class="fa-solid fa-file-pdf me-1"></i> Lihat Dokumen</a>
                                                <?php else: ?>
                                                    <span class="text-muted small italic" style="font-size:0.75rem;">Belum ada dokumen</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- 4. CIDB Perakuan -->
                                    <div class="col-md-6">
                                        <div class="dokumen-card">
                                            <div>
                                                <div class="dokumen-header-title">
                                                    CIDB (PERAKUAN PENDAFTARAN)
                                                </div>
                                                <input type="file" name="fail_cidb_perakuan" class="form-control form-control-sm mb-2" accept=".pdf">
                                                
                                                <div class="tarikh-container">
                                                    <div class="row g-3">
                                                        <div class="col-12 col-xl-6">
                                                            <div class="tarikh-title">Tarikh Mula</div>
                                                            <input type="date" name="tarikh_mula_cidb_perakuan" class="form-control form-control-sm w-100" value="<?= htmlspecialchars($profil['tarikh_mula_cidb_perakuan'] ?? ''); ?>">
                                                        </div>
                                                        <div class="col-12 col-xl-6">
                                                            <div class="tarikh-title">Tarikh Tamat</div>
                                                            <input type="date" name="tarikh_tamat_cidb_perakuan" class="form-control form-control-sm w-100" value="<?= htmlspecialchars($profil['tarikh_tamat_cidb_perakuan'] ?? ''); ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="dokumen-footer-action d-flex justify-content-between align-items-center">
                                                <?php if ($has_profil && !empty($profil['fail_cidb_perakuan'])): ?>
                                                    <a href="uploads/<?= htmlspecialchars($profil['fail_cidb_perakuan']); ?>" target="_blank" class="badge bg-danger-subtle text-danger border border-danger-subtle py-1.5 px-2 text-decoration-none rounded small"><i class="fa-solid fa-file-pdf me-1"></i> Lihat Dokumen</a>
                                                <?php else: ?>
                                                    <span class="text-muted small italic" style="font-size:0.75rem;">Belum ada dokumen</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- 5. CIDB Perolehan -->
                                    <div class="col-md-6">
                                        <div class="dokumen-card">
                                            <div>
                                                <div class="dokumen-header-title">
                                                    CIDB (SIJIL PEROLEHAN KERJA KERAJAAN)
                                                </div>
                                                <input type="file" name="fail_cidb_perolehan" class="form-control form-control-sm mb-2" accept=".pdf">
                                                
                                                <div class="tarikh-container">
                                                    <div class="row g-3">
                                                        <div class="col-12 col-xl-6">
                                                            <div class="tarikh-title">Tarikh Mula</div>
                                                            <input type="date" name="tarikh_mula_cidb_perolehan" class="form-control form-control-sm w-100" value="<?= htmlspecialchars($profil['tarikh_mula_cidb_perolehan'] ?? ''); ?>">
                                                        </div>
                                                        <div class="col-12 col-xl-6">
                                                            <div class="tarikh-title">Tarikh Tamat</div>
                                                            <input type="date" name="tarikh_tamat_cidb_perolehan" class="form-control form-control-sm w-100" value="<?= htmlspecialchars($profil['tarikh_tamat_cidb_perolehan'] ?? ''); ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="dokumen-footer-action d-flex justify-content-between align-items-center">
                                                <?php if ($has_profil && !empty($profil['fail_cidb_perolehan'])): ?>
                                                    <a href="uploads/<?= htmlspecialchars($profil['fail_cidb_perolehan']); ?>" target="_blank" class="badge bg-danger-subtle text-danger border border-danger-subtle py-1.5 px-2 text-decoration-none rounded small"><i class="fa-solid fa-file-pdf me-1"></i> Lihat Dokumen</a>
                                                <?php else: ?>
                                                    <span class="text-muted small italic" style="font-size:0.75rem;">Belum ada dokumen</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- 6. MOF -->
                                    <div class="col-md-6">
                                        <div class="dokumen-card">
                                            <div>
                                                <div class="dokumen-header-title">
                                                    SIJIL KEMENTERIAN KEWANGAN MALAYSIA
                                                </div>
                                                <input type="file" name="fail_mof" class="form-control form-control-sm mb-3" accept=".pdf">
                                                
                                                <div class="alert alert-light border border-secondary-subtle small py-2 px-3 text-muted mb-0" style="font-size:0.78rem; border-radius: 8px;">
                                                    <i class="fa-solid fa-circle-info me-1 text-secondary"></i> Tiada kemasukan tarikh diperlukan bagi sijil Kementerian Kewangan.
                                                </div>
                                            </div>
                                            
                                            <div class="dokumen-footer-action d-flex justify-content-between align-items-center" style="margin-top: 17px;">
                                                <?php if ($has_profil && !empty($profil['fail_mof'])): ?>
                                                    <a href="uploads/<?= htmlspecialchars($profil['fail_mof']); ?>" target="_blank" class="badge bg-danger-subtle text-danger border border-danger-subtle py-1.5 px-2 text-decoration-none rounded small"><i class="fa-solid fa-file-pdf me-1"></i> Lihat Dokumen</a>
                                                <?php else: ?>
                                                    <span class="text-muted small italic" style="font-size:0.75rem;">Belum ada dokumen</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                </div>
                            </div>
                        </div>

                        <!-- PERAKUAN -->
                        <div class="p-4 rounded border border-warning mb-4" style="background-color: #fffdf5; border-radius: 10px !important;">
                            <h6 class="fw-bold text-dark d-flex align-items-center mb-3">
                                <i class="fa-solid fa-triangle-exclamation text-warning me-2 fs-5"></i> PERAKUAN SYARIKAT <span class="text-danger ms-1">*</span>
                            </h6>
                            <p class="small text-secondary mb-3 text-justify" style="line-height: 1.6;">
                                Dengan ini, kami selaku pengurus atau wakil syarikat mengesahkan bahawa segala maklumat and dokumen yang dikemukakan kepada Majlis Daerah Batu Gajah adalah benar, tepat, lengkap serta tidak mengandungi sebarang maklumat palsu atau yang boleh mengelirukan. Kami bertanggungjawab sepenuhnya terhadap kesahihan maklumat and dokumen yang dihantar bagi tujuan semakan and tindakan pihak Majlis selanjutnya.
                            </p>
                            <div class="form-check">
                                <input class="form-check-input border-warning" type="checkbox" name="perakuan_setuju" value="SETUJU" id="checkSetuju" <?= ($has_profil && isset($profil['perakuan_setuju']) && $profil['perakuan_setuju'] == 'SETUJU') ? 'checked' : ''; ?> required>
                                <label class="form-check-label small fw-bold text-dark" for="checkSetuju" style="cursor:pointer;">
                                    SETUJU
                                </label>
                            </div>
                        </div>

                        <div class="d-flex justify-content-center align-items-center mt-4 pt-4 border-top">
                            <button type="submit" name="submit_daftar" class="btn btn-mdbg w-100">
                                <i class="fa-solid fa-paper-plane me-2"></i> Hantar Permohonan
                            </button>
                        </div>

                    </form>
                </div>

            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebarWrapper');
            sidebar.classList.toggle('collapsed');
        });

        document.addEventListener("DOMContentLoaded", function() {
            const currentUrl = window.location.pathname.split("/").pop();
            const menuLinks = document.querySelectorAll(".nav-link-item, .nav-link-sub-item");
            
            menuLinks.forEach(link => {
                if (link.getAttribute("href") === currentUrl) {
                    document.querySelectorAll(".nav-link-item, .nav-link-sub-item").forEach(item => item.classList.remove("active"));
                    link.classList.add("active");
                }
            });
        });

        function validateSSM(input) {
            let value = input.value.toUpperCase();
            value = value.replace(/[^A-Z0-9]/g, ''); 
            input.value = value;
        }

        // LOGIK TAMBAH & HAPUS BARIS JADUAL GRED / KATEGORI / PENGKHUSUSAN
        document.getElementById('btnAddGredRow').addEventListener('click', function() {
            const tbody = document.getElementById('gredTableBody');
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <input type="text" name="gred[]" class="form-control form-control-sm text-uppercase" placeholder="Contoh:G1" required>
                </td>
                <td>
                    <input type="text" name="kategori_gred[]" class="form-control form-control-sm text-uppercase" placeholder="Contoh:B">
                </td>
                <td>
                    <input type="text" name="pengkhususan[]" class="form-control form-control-sm text-uppercase" placeholder="Contoh:B04">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row" title="Hapus Baris"><i class="fa-solid fa-trash"></i></button>
                </td>
            `;
            tbody.appendChild(tr);
        });

        document.getElementById('gredTableBody').addEventListener('click', function(e) {
            if (e.target.closest('.btn-remove-row')) {
                const rows = document.querySelectorAll('#gredTableBody tr');
                if (rows.length > 1) {
                    e.target.closest('tr').remove();
                } else {
                    alert('Sekurang-kurangnya satu baris rekod Gred diperlukan.');
                }
            }
        });
    </script>
</body>
</html>