<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kejuruteraan') { header("Location: index.php"); exit(); }
include 'db.php';

$senarai_borang = $conn->query("SELECT * FROM kontraktor_profil ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Senarai Tarikh Tempoh Sah Borang Kontraktor</title>
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
            border: 1px solid var(--border-color);
            padding: 28px;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.03);
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
            min-width: 1200px; 
            border-collapse: collapse;
        }

        .table-mdbg th {
            padding: 18px 20px !important;
            font-size: 0.78rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            color: #475569 !important;
            white-space: nowrap !important; 
            background-color: #f8fafc;
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
            font-size: 0.92rem !important;
            color: #334155;
            vertical-align: middle;
            white-space: nowrap !important; 
        }

        .company-icon-box {
            width: 36px;
            height: 36px;
            background-color: #f1f5f9;
            color: var(--primary-mdbg);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @media (max-width: 768px) {
            .main-content-container { padding: 18px; }
        }
    </style>
</head>
<body>

    <!-- NAVBAR UTAMA -->
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

    <div class="wrapper">
        <!-- SIDEBAR -->
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
                    <a href="kejuruteraan_senarai_borang.php" class="sub-link-item active">
                        <i class="fa-solid fa-check-to-slot me-2"></i> Tarikh Sah Borang
                    </a>
                    <a href="kejuruteraan_bayaran.php" class="sub-link-item">
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

        <!-- MAIN CONTENT CONTAINER -->
        <div class="main-content-container" id="contentWrapper">
            <div class="container-fluid p-0">
                <div class="status-card-container">
                    <h5 class="fw-bold text-dark text-start mb-4"><i class="fa-solid fa-calendar-days text-muted me-2"></i>Senarai Tarikh Tempoh Sah Borang Kontraktor</h5>
                    
                    <div class="table-responsive-custom">
                        <table class="table table-mdbg align-middle">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 60px;">BIL.</th>
                                    <th>NAMA SYARIKAT</th>
                                    <th class="text-center">TARIKH MULA SSM</th>
                                    <th class="text-center">TARIKH TAMAT SSM</th>
                                    <th class="text-center">TARIKH MULA PKK</th>
                                    <th class="text-center">TARIKH TAMAT PKK</th>
                                    <th class="text-center">TARIKH MULA CIDB (PERAKUAN)</th>
                                    <th class="text-center">TARIKH TAMAT CIDB (PERAKUAN)</th>
                                    <th class="text-center">TARIKH MULA CIDB (PEROLEHAN)</th>
                                    <th class="text-center">TARIKH TAMAT CIDB (PEROLEHAN)</th>
                                    <th class="text-center">TARIKH MULA TCC</th>
                                    <th class="text-center">TARIKH TAMAT TCC</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($senarai_borang->num_rows > 0): ?>
                                    <?php $no_b = 1; while($row_b = $senarai_borang->fetch_assoc()): ?>
                                        <tr>
                                            <td class="text-center fw-semibold text-muted"><?= $no_b++; ?>.</td>
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="company-icon-box"><i class="fa-solid fa-briefcase"></i></div>
                                                    <div class="fw-bold text-dark text-uppercase"><?= htmlspecialchars($row_b['nama_syarikat'] ?? ''); ?></div>
                                                </div>
                                            </td>
                                            <td class="text-center"><span class="text-secondary fw-semibold"><?= ($row_b['tarikh_mula_ssm'] && $row_b['tarikh_mula_ssm'] != '0000-00-00') ? htmlspecialchars($row_b['tarikh_mula_ssm']) : '-'; ?></span></td>
                                            <td class="text-center"><span class="text-danger fw-semibold"><?= ($row_b['tarikh_tamat_ssm'] && $row_b['tarikh_tamat_ssm'] != '0000-00-00') ? htmlspecialchars($row_b['tarikh_tamat_ssm']) : '-'; ?></span></td>
                                            
                                            <td class="text-center"><span class="text-secondary fw-semibold"><?= ($row_b['tarikh_mula_pkk'] && $row_b['tarikh_mula_pkk'] != '0000-00-00') ? htmlspecialchars($row_b['tarikh_mula_pkk']) : '-'; ?></span></td>
                                            <td class="text-center"><span class="text-danger fw-semibold"><?= ($row_b['tarikh_tamat_pkk'] && $row_b['tarikh_tamat_pkk'] != '0000-00-00') ? htmlspecialchars($row_b['tarikh_tamat_pkk']) : '-'; ?></span></td>
                                            
                                            <td class="text-center"><span class="text-secondary fw-semibold"><?= ($row_b['tarikh_mula_cidb_perakuan'] && $row_b['tarikh_mula_cidb_perakuan'] != '0000-00-00') ? htmlspecialchars($row_b['tarikh_mula_cidb_perakuan']) : '-'; ?></span></td>
                                            <td class="text-center"><span class="text-danger fw-semibold"><?= ($row_b['tarikh_tamat_cidb_perakuan'] && $row_b['tarikh_tamat_cidb_perakuan'] != '0000-00-00') ? htmlspecialchars($row_b['tarikh_tamat_cidb_perakuan']) : '-'; ?></span></td>
                                            
                                            <td class="text-center"><span class="text-secondary fw-semibold"><?= ($row_b['tarikh_mula_cidb_perolehan'] && $row_b['tarikh_mula_cidb_perolehan'] != '0000-00-00') ? htmlspecialchars($row_b['tarikh_mula_cidb_perolehan']) : '-'; ?></span></td>
                                            <td class="text-center"><span class="text-danger fw-semibold"><?= ($row_b['tarikh_tamat_cidb_perolehan'] && $row_b['tarikh_tamat_cidb_perolehan'] != '0000-00-00') ? htmlspecialchars($row_b['tarikh_tamat_cidb_perolehan']) : '-'; ?></span></td>
                                            
                                            <td class="text-center"><span class="text-secondary fw-semibold"><?= ($row_b['tarikh_mula_tcc'] && $row_b['tarikh_mula_tcc'] != '0000-00-00') ? htmlspecialchars($row_b['tarikh_mula_tcc']) : '-'; ?></span></td>
                                            <td class="text-center"><span class="text-danger fw-semibold"><?= ($row_b['tarikh_tamat_tcc'] && $row_b['tarikh_tamat_tcc'] != '0000-00-00') ? htmlspecialchars($row_b['tarikh_tamat_tcc']) : '-'; ?></span></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- BOOTSTRAP JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // TOGGLE SIDEBAR
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebarWrapper');
            if (sidebar) sidebar.classList.toggle('collapsed');
        });
    </script>
</body>
</html>