<?php
session_start();

// 1. Sekat daripada menyimpan cache halaman ini
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kontraktor') { header("Location: index.php"); exit(); }
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

// Logik menentukan Nama Paparan di Header (Menggunakan nama_penuh atau username) & Diformat ke UPPERCASE
$nama_paparan = strtoupper(!empty($user_data['nama_penuh']) ? $user_data['nama_penuh'] : ($_SESSION['username'] ?? 'Kontraktor'));
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Pengguna - Portal Kontraktor MDBG</title>
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
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
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

        .floating-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            padding: 32px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            height: 100%;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .floating-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 20px -8px rgba(141, 91, 76, 0.12), 0 4px 12px -2px rgba(141, 91, 76, 0.08);
            border-color: rgba(141, 91, 76, 0.3);
        }

        .card-badge-step {
            position: absolute;
            top: 24px;
            right: 24px;
            font-size: 3rem;
            font-weight: 850;
            color: #cbd5e1;
            line-height: 1;
            user-select: none;
            z-index: 1;
        }

        .icon-wrapper {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            font-size: 1.35rem;
            position: relative;
            z-index: 2;
        }

        .icon-primary {
            background-color: #fdf4f2;
            color: var(--primary-mdbg);
        }

        .icon-success {
            background-color: #f0fdf4;
            color: #16a34a;
        }

        .icon-warning {
            background-color: #fffbeb;
            color: #d97706;
        }

        .step-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 12px;
            position: relative;
            z-index: 2;
        }

        .step-desc {
            font-size: 0.92rem;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 28px;
            position: relative;
            z-index: 2;
        }

        .step-timeline-list-horizontal {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            gap: 24px;
            width: 100%;
        }

        .step-timeline-item-horizontal {
            position: relative;
            flex: 1;
            padding-top: 36px;
        }

        .step-timeline-item-horizontal::before {
            content: "";
            position: absolute;
            left: 28px;
            top: 11px;
            right: -24px;
            height: 2px;
            background-color: #cbd5e1;
            z-index: 1;
        }

        .step-timeline-item-horizontal:last-child::before {
            display: none;
        }

        .step-number-badge-horizontal {
            position: absolute;
            left: 0;
            top: 0;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background-color: #ffffff;
            color: #1e293b;
            font-size: 0.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #94a3b8;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            z-index: 2;
            transition: all 0.2s ease;
        }

        .step-timeline-item-horizontal:hover .step-number-badge-horizontal {
            background-color: var(--primary-mdbg);
            color: #ffffff;
            border-color: var(--primary-mdbg);
        }

        .step-content-title {
            font-size: 0.88rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 6px;
        }

        .step-content-desc {
            font-size: 0.84rem;
            color: #475569;
            line-height: 1.5;
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

                <a href="manual_pengguna.php" class="nav-link-item active">
                    <i class="fa-solid fa-book-bookmark me-2"></i> Syarat Pengguna
                </a>

                <!-- MENU BAHARU: MAKLUMAT KONTRAKTOR -->
                <a href="maklumat_kontraktor.php" class="nav-link-item">
                    <i class="fa-solid fa-id-card me-2"></i> Maklumat Kontraktor
                </a>
                
            </div>
        </div>

        <!-- MAIN WORKSPACE -->
        <div class="main-content-container" id="contentWrapper">
            <div class="container-fluid">
                
                <!-- Page Header -->
                <div class="mb-5 text-center text-md-start">
                    <h3 class="fw-bold text-dark mb-2">Manual Pengguna & Panduan Sistem</h3>
                    <p class="text-muted mb-0">Ikuti fasa utama di bawah untuk melengkapkan urusan pendaftaran syarikat dan pendaftaran kerja undi anda.</p>
                </div>

                <!-- HORIZONTAL FLOATING CARDS STACK -->
                <div class="row g-4">
                    
                    <!-- FASA 1 -->
                    <div class="col-12">
                        <div class="floating-card">
                            <div class="card-badge-step">01</div>
                            <div class="icon-wrapper icon-primary">
                                <i class="fa-solid fa-file-signature"></i>
                            </div>
                            <h5 class="step-title">Fasa 1: Pendaftaran Syarikat</h5>
                            <p class="step-desc">
                                Sila lengkapkan proses pendaftaran akaun dan profil syarikat anda menerusi carta aliran di bawah:
                            </p>
                            
                            <ul class="step-timeline-list-horizontal">
                                <li class="step-timeline-item-horizontal">
                                    <span class="step-number-badge-horizontal">1</span>
                                    <div class="step-content-title">Penyediaan Dokumen</div>
                                    <div class="step-content-desc">Muat turun, cetak, dan isi borang pengesahan. Muat naik ke Google Form berserta SSM, SPKK CIDB, PKK, dan TCC. Bil bayaran akan di-emel dalam 3 hari bekerja.</div>
                                </li>
                                <li class="step-timeline-item-horizontal">
                                    <span class="step-number-badge-horizontal">2</span>
                                    <div class="step-content-title">Sesi Pembayaran</div>
                                    <div class="step-content-desc">Bayaran RM52.00 (Baharu/Pembaharuan) melalui JomPAY (mengikut butiran bil e-mel) atau tunai di Kaunter Bayaran Majlis Daerah Batu Gajah.</div>
                                </li>
                                <li class="step-timeline-item-horizontal">
                                    <span class="step-number-badge-horizontal">3</span>
                                    <div class="step-content-title">Hantar Bukti Bayaran</div>
                                    <div class="step-content-desc">Muat naik resit bayaran JomPAY ke Portal Kontraktor MDBG, atau serahkan bukti fizikal ke Jabatan Kejuruteraan jika bayaran dibuat di kaunter.</div>
                                </li>
                                <li class="step-timeline-item-horizontal">
                                    <span class="step-number-badge-horizontal">4</span>
                                    <div class="step-content-title">Penyerahan Sijil</div>
                                    <div class="step-content-desc">Setelah disahkan, Sijil Pendaftaran Kontraktor yang sah akan dihantar terus ke e-mel syarikat anda dalam tempoh 7 hari bekerja.</div>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- FASA 2 -->
                    <div class="col-12">
                        <div class="floating-card">
                            <div class="card-badge-step">02</div>
                            <div class="icon-wrapper icon-warning">
                                <i class="fa-solid fa-check-to-slot"></i>
                            </div>
                            
                            <div class="mb-3">
                                <h5 class="step-title mb-2">Fasa 2: Daftar Kerja Undi</h5>
                                <span class="badge d-inline-flex align-items-center gap-2 py-2 px-3 border border-warning" style="font-size: 0.78rem; font-weight: 600; border-radius: 8px; background-color: #fffbeb; color: #b45309;">
                                    <i class="fa-solid fa-calendar-days"></i> 11 MEI 2026 - 12 MEI 2026
                                </span>
                            </div>
                            
                            <p class="step-desc">
                                <strong>Tempoh Pendaftaran Terbuka:</strong> 11 Mei 2026 hingga 12 Mei 2026.<br>
                                <span class="text-secondary fw-semibold">📌 Urusan Kerja Undi Bil. 3/2026</span>
                            </p>
                            
                            <ul class="step-timeline-list-horizontal">
                                <li class="step-timeline-item-horizontal">
                                    <span class="step-number-badge-horizontal">1</span>
                                    <div class="step-content-title">Muat Turun & Lengkap Borang</div>
                                    <div class="step-content-desc">Muat turun, cetak dan lengkapkan Borang Pendaftaran Kerja Undi Bil: 3/2026. Muat naik semula ke Google Form bersama dokumen sokongan (SSM, SPKK CIDB, PKK, TCC). Bil Bayaran Penyertaan dihantar ke e-mel.</div>
                                </li>
                                <li class="step-timeline-item-horizontal">
                                    <span class="step-number-badge-horizontal">2</span>
                                    <div class="step-content-title">Bayaran Penyertaan (RM10.00)</div>
                                    <div class="step-content-desc">Buat bayaran penyertaan sebanyak RM10.00 melalui JomPAY (menggunakan maklumat bil dari e-mel) atau secara tunai di Kaunter Bayaran Majlis Daerah Batu Gajah.</div>
                                </li>
                                <li class="step-timeline-item-horizontal">
                                    <span class="step-number-badge-horizontal">3</span>
                                    <div class="step-content-title">Penyerahan Resit</div>
                                    <div class="step-content-desc">Muat naik resit bayaran JomPAY ke Google Form khas melalui pautan e-mel yang diterima, atau kemukakan resit fizikal ke Jabatan Kejuruteraan.</div>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- KELULUSAN & KEPUTUSAN -->
                    <div class="col-12">
                        <div class="floating-card">
                            <div class="card-badge-step">03</div>
                            <div class="icon-wrapper icon-success">
                                <i class="fa-solid fa-square-poll-horizontal"></i>
                            </div>
                            <h5 class="step-title">Semakan & Keputusan</h5>
                            <p class="step-desc">
                                Sila rujuk paparan Portal Dashboard atau semak keputusan terus daripada panel profil peribadi anda:
                            </p>
                            
                            <ul class="step-timeline-list-horizontal">
                                <li class="step-timeline-item-horizontal">
                                    <span class="step-number-badge-horizontal">1</span>
                                    <div class="step-content-title">Semakan Status Borang</div>
                                    <div class="step-content-desc">Kelulusan borang pendaftaran syarikat mengambil masa antara 1 hingga 3 hari bekerja untuk disemak dan disahkan oleh pihak urus setia MDBG.</div>
                                </li>
                                <li class="step-timeline-item-horizontal">
                                    <span class="step-number-badge-horizontal">2</span>
                                    <div class="step-content-title">Paparan Keputusan Undi</div>
                                    <div class="step-content-desc">Senarai penuh pemenang bagi sesi cabutan kerja undi yang berjaya akan dipaparkan di dalam portal selepas sesi cabutan rasmi selesai dijalankan.</div>
                                </li>
                            </ul>
                        </div>
                    </div>

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