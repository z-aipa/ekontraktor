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

// Ambil maklumat jenis akaun & nama penuh daripada users
$user_data = $conn->query("SELECT jenis_akaun, nama_penuh FROM users WHERE id='$user_id'")->fetch_assoc();

// Ambil data profil kontraktor
$profil = $conn->query("SELECT * FROM kontraktor_profil WHERE user_id='$user_id'")->fetch_assoc();

// Hide Sidebar Fasa 1 / Semak Pembaharuan
$today = date('Y-m-d');
$is_status_aktif = false;
$is_expired = false;

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

// Logik menentukan Nama Paparan di Header
$nama_paparan = strtoupper(!empty($user_data['nama_penuh']) ? $user_data['nama_penuh'] : ($_SESSION['username'] ?? 'Kontraktor'));

// Fungsi pembantu paparan tarikh
function format_tarikh($tarikh) {
    if (empty($tarikh) || $tarikh == '0000-00-00') return '-';
    return date('d/m/Y', strtotime($tarikh));
}

// Fungsi pembantu laluan fail uploads
function get_file_path($filename) {
    if (empty($filename)) return '';
    if (strpos($filename, 'uploads/') === 0) {
        return $filename;
    }
    return 'uploads/' . ltrim($filename, '/');
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maklumat Kontraktor - Portal Kontraktor MDBG</title>
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

        /* --- SIDEBAR --- */
        .sidebar-container {
            width: var(--sidebar-width);
            background-color: #1e293b; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            flex-shrink: 0;
            box-shadow: 4px 0 15px rgba(0,0,0,0.03);
            z-index: 1010;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
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
        
        /* --- REKA BENTUK MODEN SIDEBAR MENU & SUB-MENU --- */
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

        .submenu-tree {
            position: relative;
            padding-left: 12px;
            margin: 4px 16px 8px 32px;
            border-left: 2px solid #334155;
        }

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
            color: #38bdf8;
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
            margin-left: var(--sidebar-width);
            display: flex;
            justify-content: center;
        }

        .main-content-container > .container-fluid {
            width: 100%;
            max-width: 1300px;
            margin: 0 auto;    
        }
        
        .sidebar-container.collapsed ~ .main-content-container {
            margin-left: 0;   
        }

        /* --- KAD KANDUNGAN UTAMA --- */
        .floating-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            padding: 32px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            position: relative;
            overflow: hidden;
        }

        /* --- STYLES BAHARU: TAJUK & KAD BELUM DAFTAR --- */
        .page-header-box {
            position: relative;
            padding: 22px 28px;
            background: #ffffff;
            border-radius: 16px;
            border-left: 5px solid var(--primary-mdbg);
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.03);
            margin-bottom: 28px;
        }

        .page-header-box h3 {
            font-size: 1.5rem;
            letter-spacing: -0.3px;
            color: #0f172a;
        }

        .empty-state-card {
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            padding: 60px 40px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .empty-state-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-mdbg), #b37d6e);
        }

        .empty-state-icon-wrapper {
            position: relative;
            width: 90px;
            height: 90px;
            margin: 0 auto 24px auto;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(141, 91, 76, 0.08);
            border: 2px dashed rgba(141, 91, 76, 0.25);
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .empty-state-card:hover .empty-state-icon-wrapper {
            transform: scale(1.08) rotate(-4deg);
            background: rgba(141, 91, 76, 0.12);
            border-color: var(--primary-mdbg);
        }

        .empty-state-icon-wrapper i {
            font-size: 2.6rem;
            color: var(--primary-mdbg);
        }

        .empty-state-title {
            font-weight: 700;
            color: #1e293b;
            font-size: 1.35rem;
            margin-bottom: 10px;
        }

        .empty-state-desc {
            color: #64748b;
            font-size: 0.95rem;
            max-width: 500px;
            margin: 0 auto 28px auto;
            line-height: 1.6;
        }

        .btn-mdbg-primary {
            background: linear-gradient(135deg, var(--primary-mdbg) 0%, var(--primary-dark) 100%);
            color: #ffffff;
            border: none;
            padding: 12px 32px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            box-shadow: 0 4px 15px rgba(141, 91, 76, 0.3);
            transition: all 0.25s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-mdbg-primary:hover {
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(141, 91, 76, 0.45);
        }

        .section-header-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--primary-mdbg);
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 8px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-label {
            font-size: 0.78rem;
            text-transform: uppercase;
            font-weight: 700;
            color: #64748b;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: #1e293b;
        }

        .doc-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
            background-color: #f8fafc;
            transition: all 0.2s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .doc-card:hover {
            border-color: var(--primary-mdbg);
            background-color: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .doc-title {
            font-size: 0.88rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .doc-date {
            font-size: 0.78rem;
            color: #64748b;
            margin-bottom: 12px;
        }

        /* --- STYLES UNTUK JADUAL GRED --- */
        .gred-table-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
        }

        .gred-table {
            margin-bottom: 0;
            font-size: 0.9rem;
        }

        .gred-table tbody td {
            padding: 12px 16px;
            vertical-align: middle;
            color: #1e293b;
            border-right: 1px solid #cbd5e1;
        }

        .gred-table tbody td:last-child {
            border-right: none;
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
        
        <!-- SIDEBAR -->
        <div class="sidebar-container" id="sidebarWrapper">
            <div class="sidebar-menu">
                
                <div class="sidebar-category-title">Menu Utama</div>
                <a href="kontraktor.php" class="nav-link-item">
                    <i class="fa-solid fa-chart-pie me-2"></i> Dashboard
                </a>
                
                <div class="sidebar-category-title">URUSAN PENDAFTARAN</div>

                <?php if (!$is_status_aktif): ?>
                <!-- Fasa 1 -->
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
                <a href="maklumat_kontraktor.php" class="nav-link-item active">
                    <i class="fa-solid fa-id-card me-2"></i> Maklumat Kontraktor
                </a>
                
            </div>
        </div>

        <!-- MAIN WORKSPACE -->
        <div class="main-content-container" id="contentWrapper">
            <div class="container-fluid">
                
                <!-- Page Header (Rekabentuk Ditingkatkan) -->
                <div class="page-header-box d-flex flex-column justify-content-center">
                    <h3 class="fw-bold mb-1">Maklumat Profil Kontraktor</h3>
                    <p class="text-muted mb-0 small">Maklumat lengkap dan senarai dokumen syarikat yang telah didaftarkan di dalam sistem.</p>
                </div>

                <?php if (empty($profil)): ?>
                    <!-- KAD BELUM DAFTAR (REKA BENTUK MODEN & KEMAS) -->
                    <div class="empty-state-card">
                        <div class="empty-state-icon-wrapper">
                            <i class="fa-solid fa-folder-open"></i>
                        </div>
                        <h4 class="empty-state-title">Maklumat Syarikat Belum Didaftarkan</h4>
                        <p class="empty-state-desc">
                            Anda belum mendaftar profil syarikat anda. Sila lengkapkan Borang Pendaftaran Fasa 1 untuk memaparkan maklumat penuh di halaman ini.
                        </p>
                        <a href="kontraktor_borang_daftar.php" class="btn-mdbg-primary">
                            <i class="fa-solid fa-file-pen"></i> Daftar Syarikat Sekarang
                        </a>
                    </div>
                <?php else: ?>

                    <!-- KAD BESAR MAKLUMAT KONTRAKTOR (KEKAL SEPERTI ASAL) -->
                    <div class="floating-card">
                        
                        <!-- BANNER TAJUK SYARIKAT -->
                        <div class="border-bottom pb-4 mb-4">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <h4 class="fw-bold text-dark mb-0"><?= htmlspecialchars($profil['nama_syarikat']); ?></h4>
                                </div>
                                <div class="text-muted small">
                                    <i class="fa-solid fa-hashtag me-1"></i> No. SSM / Pendaftaran: <strong class="text-dark"><?= htmlspecialchars($profil['no_pendaftaran']); ?></strong>
                                </div>
                            </div>
                        </div>

                        <!-- SEKSYEN 1: MAKLUMAT SYARIKAT & HUBUNGAN -->
                        <div class="section-header-title">
                            <i class="fa-solid fa-building me-1"></i> 1. Maklumat Am & Hubungan Syarikat
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6 col-lg-4">
                                <div class="info-label">Jenis Pendaftaran</div>
                                <div class="info-value"><?= htmlspecialchars($profil['jenis_pendaftaran'] ?? '-'); ?></div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="info-label">Kategori Pendaftaran</div>
                                <div class="info-value"><?= htmlspecialchars($profil['kategori_pendaftaran'] ?? '-'); ?></div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="info-label">Emel Aktif Syarikat</div>
                                <div class="info-value text-primary"><i class="fa-regular fa-envelope me-1"></i> <?= htmlspecialchars($profil['email_aktif'] ?? '-'); ?></div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="info-label">No. Telefon Syarikat</div>
                                <div class="info-value"><i class="fa-solid fa-phone me-1"></i> <?= htmlspecialchars($profil['no_telefon_syarikat'] ?? '-'); ?></div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="info-label">No. Telefon Pengurus</div>
                                <div class="info-value"><i class="fa-solid fa-mobile-screen-button me-1"></i> <?= htmlspecialchars($profil['no_telefon_pengurus'] ?? '-'); ?></div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="info-label">Tempoh Kelulusan Aktif MDBG</div>
                                <div class="info-value text-success">
                                    <i class="fa-solid fa-calendar-check me-1"></i>
                                    <?= format_tarikh($profil['tarikh_mula_aktif']); ?> - <?= format_tarikh($profil['tarikh_tamat_aktif']); ?>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="info-label">Alamat Premis / Perniagaan</div>
                                <div class="info-value"><?= nl2br(htmlspecialchars($profil['alamat'] ?? 'Tiada Maklumat Alamat')); ?></div>
                            </div>
                        </div>

                        <!-- SEKSYEN 2: KELAYAKAN GRED / CIDB / KEWANGAN (JADUAL REKABENTUK KEMAS) -->
                        <div class="section-header-title">
                            <i class="fa-solid fa-award me-1"></i> 2. Kelayakan Gred & Pengkhususan
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <div class="gred-table-card">
                                    <div class="table-responsive">
                                        <table class="table gred-table table-hover align-middle text-center">
                                            <tbody>
                                                <?php 
                                                $gred_text = trim($profil['gred_cidb_kewangan'] ?? '');
                                                $gred_lines = array_filter(explode("\n", str_replace("\r", "", $gred_text)));
                                                
                                                if (!empty($gred_lines)): 
                                                    foreach ($gred_lines as $line): 
                                                        $cols = preg_split('/\s+/', trim($line), 3);
                                                        $gred = $cols[0] ?? '-';
                                                        $kat = $cols[1] ?? '-';
                                                        $pengkhususan = $cols[2] ?? '-';
                                                ?>
                                                    <tr>
                                                        <td class="fw-bold text-dark" style="width: 25%;"><?= htmlspecialchars($gred); ?></td>
                                                        <td class="fw-semibold text-secondary" style="width: 25%;"><?= htmlspecialchars($kat); ?></td>
                                                        <td class="fw-semibold text-dark" style="width: 50%;"><?= htmlspecialchars($pengkhususan); ?></td>
                                                    </tr>
                                                <?php 
                                                    endforeach; 
                                                else: 
                                                ?>
                                                    <tr>
                                                        <td colspan="3" class="text-center text-muted py-4">Tiada Maklumat Gred / CIDB / Kewangan</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SEKSYEN 3: REKOD DOKUMEN & FAIL YANG DIMUAT NAIK -->
                        <div class="section-header-title">
                            <i class="fa-solid fa-folder-tree me-1"></i> 3. Fail Dokumen Yang Dimuat Naik
                        </div>
                        <div class="row g-3">

                            <!-- SSM -->
                            <div class="col-md-6 col-lg-4">
                                <div class="doc-card">
                                    <div>
                                        <div class="doc-title"><i class="fa-solid fa-file-pdf text-danger me-2"></i> Dokumen SSM</div>
                                        <div class="doc-date">
                                            Sah: <?= format_tarikh($profil['tarikh_mula_ssm']); ?> hingga <?= format_tarikh($profil['tarikh_tamat_ssm']); ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($profil['fail_ssm'])): ?>
                                        <a href="<?= htmlspecialchars(get_file_path($profil['fail_ssm'])); ?>" target="_blank" class="btn btn-sm btn-outline-primary w-100 rounded-2 fw-semibold">
                                            <i class="fa-solid fa-eye me-1"></i> Lihat Dokumen
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border w-100 py-2">Tiada Fail</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- PKK -->
                            <div class="col-md-6 col-lg-4">
                                <div class="doc-card">
                                    <div>
                                        <div class="doc-title"><i class="fa-solid fa-file-pdf text-danger me-2"></i> Sijil PKK</div>
                                        <div class="doc-date">
                                            Sah: <?= format_tarikh($profil['tarikh_mula_pkk']); ?> hingga <?= format_tarikh($profil['tarikh_tamat_pkk']); ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($profil['fail_pkk'])): ?>
                                        <a href="<?= htmlspecialchars(get_file_path($profil['fail_pkk'])); ?>" target="_blank" class="btn btn-sm btn-outline-primary w-100 rounded-2 fw-semibold">
                                            <i class="fa-solid fa-eye me-1"></i> Lihat Dokumen
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border w-100 py-2">Tiada Fail</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- CIDB PERAKUAN -->
                            <div class="col-md-6 col-lg-4">
                                <div class="doc-card">
                                    <div>
                                        <div class="doc-title"><i class="fa-solid fa-file-pdf text-danger me-2"></i> CIDB (Perakuan)</div>
                                        <div class="doc-date">
                                            Sah: <?= format_tarikh($profil['tarikh_mula_cidb_perakuan']); ?> hingga <?= format_tarikh($profil['tarikh_tamat_cidb_perakuan']); ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($profil['fail_cidb_perakuan'])): ?>
                                        <a href="<?= htmlspecialchars(get_file_path($profil['fail_cidb_perakuan'])); ?>" target="_blank" class="btn btn-sm btn-outline-primary w-100 rounded-2 fw-semibold">
                                            <i class="fa-solid fa-eye me-1"></i> Lihat Dokumen
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border w-100 py-2">Tiada Fail</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- CIDB PEROLEHAN -->
                            <div class="col-md-6 col-lg-4">
                                <div class="doc-card">
                                    <div>
                                        <div class="doc-title"><i class="fa-solid fa-file-pdf text-danger me-2"></i> CIDB (Perolehan)</div>
                                        <div class="doc-date">
                                            Sah: <?= format_tarikh($profil['tarikh_mula_cidb_perolehan']); ?> hingga <?= format_tarikh($profil['tarikh_tamat_cidb_perolehan']); ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($profil['fail_cidb_perolehan'])): ?>
                                        <a href="<?= htmlspecialchars(get_file_path($profil['fail_cidb_perolehan'])); ?>" target="_blank" class="btn btn-sm btn-outline-primary w-100 rounded-2 fw-semibold">
                                            <i class="fa-solid fa-eye me-1"></i> Lihat Dokumen
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border w-100 py-2">Tiada Fail</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- MOF -->
                            <div class="col-md-6 col-lg-4">
                                <div class="doc-card">
                                    <div>
                                        <div class="doc-title"><i class="fa-solid fa-file-pdf text-danger me-2"></i> Sijil MOF</div>
                                        <div class="doc-date">Dokumen Pendaftaran MOF</div>
                                    </div>
                                    <?php if (!empty($profil['fail_mof'])): ?>
                                        <a href="<?= htmlspecialchars(get_file_path($profil['fail_mof'])); ?>" target="_blank" class="btn btn-sm btn-outline-primary w-100 rounded-2 fw-semibold">
                                            <i class="fa-solid fa-eye me-1"></i> Lihat Dokumen
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border w-100 py-2">Tiada Fail</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- TCC -->
                            <div class="col-md-6 col-lg-4">
                                <div class="doc-card">
                                    <div>
                                        <div class="doc-title"><i class="fa-solid fa-file-pdf text-danger me-2"></i> Sijil TCC</div>
                                        <div class="doc-date">
                                            Sah: <?= format_tarikh($profil['tarikh_mula_tcc']); ?> hingga <?= format_tarikh($profil['tarikh_tamat_tcc']); ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($profil['fail_tcc'])): ?>
                                        <a href="<?= htmlspecialchars(get_file_path($profil['fail_tcc'])); ?>" target="_blank" class="btn btn-sm btn-outline-primary w-100 rounded-2 fw-semibold">
                                            <i class="fa-solid fa-eye me-1"></i> Lihat Dokumen
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border w-100 py-2">Tiada Fail</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>

                    </div>
                <?php endif; ?>

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
            const menuLinks = document.querySelectorAll(".nav-link-item");
            
            menuLinks.forEach(link => {
                if (link.getAttribute("href") === currentUrl) {
                    document.querySelectorAll(".nav-link-item").forEach(item => item.classList.remove("active"));
                    link.classList.add("active");
                }
            });
        });
    </script>
</body>
</html>