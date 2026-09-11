<?php
session_start();

// 1. Sekat daripada menyimpan cache halaman ini
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kontraktor') { header("Location: index.php"); exit(); }
include 'db.php';

$user_id = $_SESSION['user_id'];

// =========================================================================
// SEMAKAN SAMA ADA AKAUN KONTRAKTOR DISEKAT DARIPADA MENDAFTAR UNDI
// =========================================================================
$check_akses = $conn->query("SELECT akses_undi FROM kontraktor_profil WHERE user_id = '$user_id'");
if ($check_akses && $row_akses = $check_akses->fetch_assoc()) {
    if (isset($row_akses['akses_undi']) && $row_akses['akses_undi'] == 0) {
        echo "<script>
            alert('Borang pendaftaran undi ini telah disekat oleh pihak Pentadbir/Admin pergi ke page Semakan Daftar Undi Untuk Mendaftar.');
            window.location.href = 'kontraktor.php';
        </script>";
        exit();
    }
}

// Semak sekatan global daripada admin
$check_global = $conn->query("SELECT nilai FROM tetapan_sistem WHERE kunci = 'sekat_semua_undi'");
if ($check_global && $row_g = $check_global->fetch_assoc()) {
    if ($row_g['nilai'] == '1') {
        die("<script>
            alert('Borang pendaftaran undi ini telah disekat oleh pihak Pentadbir/Admin pergi ke page Semakan Daftar Undi Untuk Mendaftar.');
            window.location.href = 'kontraktor.php';
        </script>");
    }
}

// Ambil maklumat profil kontraktor
$profil = $conn->query("SELECT * FROM kontraktor_profil WHERE user_id='$user_id'")->fetch_assoc();
$syarikat = $profil; // Kekalkan $syarikat supaya borang di bawah tidak terjejas

$user_data = $conn->query("SELECT jenis_akaun, nama_penuh FROM users WHERE id='$user_id'")->fetch_assoc();

// Ambil maklumat syarikat sedia ada jika ada untuk memudahkan kontraktor
$syarikat = $conn->query("SELECT nama_syarikat, email_aktif, no_telefon_syarikat FROM kontraktor_profil WHERE user_id='$user_id'")->fetch_assoc();

// Semak sama ada kontraktor SUDAH PERNAH HANTAR borang undi dalam jadual kontraktor_undi
$permohonan_undi = $conn->query("SELECT * FROM kontraktor_undi WHERE user_id='$user_id'")->fetch_assoc();
$sudah_hantar = !empty($permohonan_undi);

// Logik menentukan Nama Paparan di Header (Menggunakan nama_penuh atau username) & Diformat ke UPPERCASE
$nama_paparan = strtoupper(!empty($user_data['nama_penuh']) ? $user_data['nama_penuh'] : ($_SESSION['username'] ?? 'Kontraktor'));

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

?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sesi 2: Borang Daftar Kerja Undi - MDBG</title>
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
        
        /* --- REKA BENTUK MODEN SIDEBAR MENU & SUB-MENU (SAMA SEPERTI KONTRAKTOR.PHP) --- */

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
            border-left: 2px solid #334155;
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

        .main-content-container > .container-fluid,
        .main-content-container > div {
            width: 100%;
            max-width: 1200px; 
            margin: 0 auto;    
        }
        
        .sidebar-container.collapsed ~ .main-content-container {
            margin-left: 0;   
        }

        .status-card-container {
            background-color: #ffffff;
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
            color: #494c4e;
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
        .form-control {
            border-radius: 8px;
            padding: 11px 15px;
            border: 1px solid #dcdcdc;
            font-size: 0.95rem;
        }
        .form-control:focus {
            border-color: var(--primary-mdbg);
            box-shadow: 0 0 0 0.2rem rgba(141, 91, 76, 0.15);
        }
        .doc-list { 
            background: #fdfaf9; 
            border: 1px dashed #d8c3c1; 
            border-radius: 12px; 
            padding: 25px; 
            margin-bottom: 20px; 
        }
        .doc-list ol { 
            font-size: 0.92rem; 
            color: #555; 
            padding-left: 20px;
        }
        .doc-list ol li {
            margin-bottom: 8px;
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

    <!-- MODERN WHITE NAVBAR -->
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
            <a href="logout.php" class="btn-logout d-flex align-items-center"><i class="fa-solid fa-right-from-bracket me-2"></i> Log Keluar</a>
        </div>
    </div>

    <div class="wrapper">
        
        <div class="sidebar-container" id="sidebarWrapper">
            <div class="sidebar-menu">
                <div class="sidebar-category-title">Menu Utama</div>
                <?php 
                    $dashboard_url = 'kontraktor.php';
                    if (isset($user_data['jenis_akaun']) && $user_data['jenis_akaun'] == 'Individu' && !empty($_SESSION['semak_individu'])) {
                        $dashboard_url = 'kontraktor.php?semak=1';
                    }
                ?>
                <a href="<?= $dashboard_url; ?>" class="nav-link-item">
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
                <a href="#submenuFasa2" class="nav-link-item d-flex align-items-center justify-content-between" data-bs-toggle="collapse" role="button" aria-expanded="true" aria-controls="submenuFasa2">
                    <div class="d-flex align-items-center overflow-hidden">
                        <i class="fa-solid fa-box-archive me-2"></i>
                        <span>Fasa 2: Daftar Kerja Undi</span>
                    </div>
                    <i class="fa-solid fa-chevron-down chevron-icon"></i>
                </a>

                <!-- Sub-Menu Fasa 2 -->
                <div class="collapse show" id="submenuFasa2">
                    <div class="submenu-tree">
                        <a href="kontraktor_daftar_undi.php" class="nav-link-sub-item active">
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
                    <h4 class="status-header mb-2">Borang Daftar Kerja Undi</h4>
                    <p class="text-muted small mb-4">Sila lengkapkan maklumat permohonan undian kerja bagi tahun 2026.</p>
                    
                    <?php if ($sudah_hantar): ?>
                        <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
                            <i class="fa-solid fa-circle-check fs-4 me-3"></i>
                            <div>
                                <strong>Permohonan Telah Dihantar!</strong><br>
                                Maklumat di bawah telah disemak dan dihantar pada <?= date('d/m/Y H:i A', strtotime($permohonan_undi['tarikh_hantar'])) ?>.
                            </div>
                        </div>
                    <?php endif; ?>

                    <hr class="mt-0 mb-4" style="opacity: 0.08;">
                    
                    <form id="formUndi" action="proses_undi.php" method="POST" enctype="multipart/form-data">
                        
                        <!-- HIDDEN INPUT SUPAYA POST submit_undi SENTIASA DIHANTAR -->
                        <input type="hidden" name="submit_undi" value="1">

                        <div class="section-header"><h5>Maklumat Syarikat</h5></div>
                        
                        <div class="mb-4">
                            <label class="form-label">NAMA SYARIKAT <span class="text-danger">*</span></label>
                            <input type="text" name="nama_syarikat" class="form-control" placeholder="Masukkan Nama Syarikat" value="<?= htmlspecialchars($sudah_hantar ? $permohonan_undi['nama_syarikat'] : ($syarikat['nama_syarikat'] ?? ($user_data['nama_penuh'] ?? ''))) ?>" required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">NO. PENDAFTARAN SYARIKAT <span class="text-danger">*</span></label>
                            <input type="text" id="noPendaftaran" name="no_pendaftaran" class="form-control" placeholder="Contoh: IP0320000S" maxlength="10" style="text-transform: uppercase;" value="<?= htmlspecialchars($sudah_hantar ? $permohonan_undi['no_pendaftaran'] : '') ?>" required>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label class="form-label">NO. TELEFON <span class="text-danger">*</span></label>
                                <input type="text" name="no_tel" class="form-control" placeholder="Contoh: 0123456789" value="<?= htmlspecialchars($sudah_hantar ? $permohonan_undi['no_tel'] : ($syarikat['no_telefon_syarikat'] ?? '')) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ALAMAT E-MEL AKTIF <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" placeholder="Contoh: syarikat@gmail.com" value="<?= htmlspecialchars($sudah_hantar ? $permohonan_undi['email'] : ($syarikat['email_aktif'] ?? '')) ?>" required>
                                <small class="text-muted mt-1 d-block">Bil bayaran akan dikemukakan melalui e-mel ini.</small>
                            </div>
                        </div>

                        <div class="section-header"><h5>Dokumen Sokongan</h5></div>
                        <div class="doc-list">
                            <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-triangle-exclamation text-warning me-2"></i>SILA MUAT NAIK DOKUMEN BERIKUT DIDALAM SATU FAIL (PDF):</h6>
                            <ol>
                                <li>Borang Pendaftaran Perolehan Kerja Secara Undian Bil.3/2026 Yang Telah Lengkap Diisi</li>
                                <li>Salinan Kad Pengenalan Pemilik Syarikat</li>
                                <li>Sijil Pendaftaran Syarikat (SSM)</li>
                                <li>Sijil Perakuan Pendaftaran Kontraktor (PKK) (CIDB)</li>
                                <li>Sijil Perolehan Kerja Kerajaan (SPKK) (CIDB)</li>
                                <li>Sijil Taraf Bumiputera (STB)</li>
                                <li>Sijil Pematuhan Cukai (TCC) 2026</li>
                                <li>Sijil Pendaftaran Kontraktor Majlis Daerah Batu Gajah</li>
                                <li>Penyata Akaun Terkini</li>
                            </ol>
                            <hr style="opacity: 0.1;">
                            
                            <?php 
                            if ($sudah_hantar && !empty($permohonan_undi['fail_dokumen'])): 
                                // Dapatkan nama fail sebenar sahaja secara selamat
                                $filename = basename($permohonan_undi['fail_dokumen']);
                                $fail_url = 'uploads/' . rawurlencode($filename);
                            ?>
                                <label class="form-label d-block mb-2">Fail PDF Dihantar</label>
                                <div class="mt-2 mb-3">
                                    <a href="<?= $fail_url; ?>" target="_blank" class="btn btn-outline-danger btn-sm fw-bold">
                                        <i class="fa-solid fa-file-pdf me-2"></i>Lihat Fail PDF Dihantar
                                    </a>
                                </div>
                                <label class="form-label d-block mb-2">Kemas Kini Fail PDF (Pilihan - Tinggalkan kosong jika tiada perubahan)</label>
                                <input type="file" id="failUndi" name="fail_undi" class="form-control" accept=".pdf">
                            <?php else: ?>
                                <label class="form-label d-block mb-2">Pilih Fail PDF (Maksimum 10MB) <span class="text-danger">*</span></label>
                                <input type="file" id="failUndi" name="fail_undi" class="form-control" accept=".pdf" required>
                            <?php endif; ?>
                        </div>

                        <div class="p-4 rounded border border-warning mb-4" style="background-color: #fffdf5; border-radius: 10px !important;">
                            <h6 class="fw-bold mb-2" style="color: #323435;">PERAKUAN SYARIKAT <span class="text-danger">*</span></h6>
                            <p class="small text-muted mb-3 text-justify" style="line-height: 1.5;">
                                Dengan ini, kami selaku pengurus atau wakil syarikat mengesahkan bahawa segala maklumat dan dokumen yang dikemukakan kepada Majlis Daerah Batu Gajah adalah benar, tepat, lengkap serta tidak mengandungi sebarang maklumat palsu.
                            </p>
                            <div class="form-check">
                                <input class="form-check-input border-warning" type="checkbox" id="setuju" name="perakuan" <?= $sudah_hantar ? 'checked' : '' ?> required>
                                <label class="form-check-label fw-bold" for="setuju" style="color: #343532; cursor: pointer;">SETUJU</label>
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="submit" id="btnSubmit" name="btn_submit_undi" class="btn btn-mdbg w-100">
                                <i class="fa-solid fa-paper-plane me-2"></i><?= $sudah_hantar ? 'Kemaskini Permohonan' : 'Hantar Permohonan' ?>
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
            // Masukkan sekali .nav-link-sub-item
            const menuLinks = document.querySelectorAll(".nav-link-item, .nav-link-sub-item");
            
            menuLinks.forEach(link => {
                if (link.getAttribute("href") === currentUrl) {
                    link.classList.add("active");
                }
            });
        });

        // VALIDATION 1: No. Pendaftaran Syarikat (UPPERCASE, No Space, No Symbol, Max 10 Aksara)
        const noPendaftaranInput = document.getElementById('noPendaftaran');
        if (noPendaftaranInput) {
            noPendaftaranInput.addEventListener('input', function() {
                let val = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
                if (val.length > 10) {
                    val = val.substring(0, 10);
                }
                this.value = val;
            });
        }

        // VALIDATION 2: Semakan Saiz Fail PDF (Maksimum 10MB) & Form Submit
        const fileInput = document.getElementById('failUndi');
        const formUndi = document.getElementById('formUndi');
        const btnSubmit = document.getElementById('btnSubmit');

        if (fileInput) {
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    const file = this.files[0];
                    const maxSize = 10 * 1024 * 1024; // 10MB

                    if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
                        alert('Hanya fail format PDF sahaja dibenarkan.');
                        this.value = '';
                        return;
                    }

                    if (file.size > maxSize) {
                        alert('Saiz fail melebihi 10 MB! Sila muat naik fail yang lebih kecil daripada 10 MB.');
                        this.value = '';
                        return;
                    }
                }
            });
        }

        if (formUndi && btnSubmit) {
            formUndi.addEventListener('submit', function(e) {
                if (noPendaftaranInput && noPendaftaranInput.value.length !== 10) {
                    e.preventDefault();
                    alert('NO. PENDAFTARAN SYARIKAT mestilah mengandungi tepat 10 digit/aksara!');
                    noPendaftaranInput.focus();
                    return false;
                }

                if (fileInput && fileInput.files.length > 0) {
                    if (fileInput.files[0].size > 10 * 1024 * 1024) {
                        e.preventDefault();
                        alert('Saiz fail melebihi 10 MB! Sila muat naik fail yang lebih kecil daripada 10 MB.');
                        fileInput.value = '';
                        return false;
                    }
                }

                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Sedang Mengirim...';
                formUndi.submit();
            });
        }
    </script>
</body>
</html>