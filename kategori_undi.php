<?php
session_start();

// 1. Sekat daripada menyimpan cache halaman ini
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kejuruteraan') { 
    header("Location: index.php"); 
    exit(); 
}
include 'db.php';

// AUTO-CREATE JADUAL `kategori_undi` JIKA BELUM WUJUD DALAM DATABASE
$conn->query("CREATE TABLE IF NOT EXISTS `kategori_undi` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pilihan` VARCHAR(100) NOT NULL,
    `gred_kelayakan` VARCHAR(100) DEFAULT '',
    `pengkhususan` VARCHAR(255) DEFAULT '',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// AUTO-CHECK & ADD COLUMN `gred_kelayakan` JIKA BELUM WUJUD
$check_gred_col = $conn->query("SHOW COLUMNS FROM `kategori_undi` LIKE 'gred_kelayakan'");
if ($check_gred_col && $check_gred_col->num_rows == 0) {
    $conn->query("ALTER TABLE `kategori_undi` ADD COLUMN `gred_kelayakan` VARCHAR(100) DEFAULT '' AFTER `pilihan`");
}

// AUTO-CHECK & ADD COLUMN `pengkhususan` JIKA BELUM WUJUD
$check_col = $conn->query("SHOW COLUMNS FROM `kategori_undi` LIKE 'pengkhususan'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE `kategori_undi` ADD COLUMN `pengkhususan` VARCHAR(255) DEFAULT '' AFTER `gred_kelayakan`");
}

// AUTO-CREATE JADUAL `sub_kategori_undi` JIKA BELUM WUJUD DALAM DATABASE
$conn->query("CREATE TABLE IF NOT EXISTS `sub_kategori_undi` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `kategori_id` INT NOT NULL,
    `tajuk_kerja` TEXT NOT NULL,
    `nilai_projek` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`kategori_id`) REFERENCES `kategori_undi`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// BACKEND: TAMBAH PILIHAN KATEGORI BAHARU
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $pilihan        = trim($_POST['pilihan']);
    $gred_kelayakan = strtoupper(trim($_POST['gred_kelayakan'] ?? ''));
    $pengkhususan   = strtoupper(trim($_POST['pengkhususan'] ?? ''));

    if (!empty($pilihan)) {
        $stmt = $conn->prepare("INSERT INTO kategori_undi (pilihan, gred_kelayakan, pengkhususan) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $pilihan, $gred_kelayakan, $pengkhususan);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: kategori_undi.php");
    exit();
}

// BACKEND: KEMAS KINI PILIHAN (UPDATE)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    $id             = intval($_POST['id']);
    $pilihan        = trim($_POST['pilihan']);
    $gred_kelayakan = strtoupper(trim($_POST['gred_kelayakan'] ?? ''));
    $pengkhususan   = strtoupper(trim($_POST['pengkhususan'] ?? ''));

    if (!empty($pilihan) && $id > 0) {
        $stmt = $conn->prepare("UPDATE kategori_undi SET pilihan = ?, gred_kelayakan = ?, pengkhususan = ? WHERE id = ?");
        $stmt->bind_param("sssi", $pilihan, $gred_kelayakan, $pengkhususan, $id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: kategori_undi.php");
    exit();
}

// BACKEND: PADAM PILIHAN (DELETE)
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("DELETE FROM kategori_undi WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: kategori_undi.php");
    exit();
}

// BACKEND: TAMBAH SUB ROW (TAJUK KERJA, NILAI PROJEK)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_sub') {
    $kategori_id  = intval($_POST['kategori_id']);
    $tajuk_kerja  = trim($_POST['tajuk_kerja']);
    $nilai_projek = floatval($_POST['nilai_projek']);

    if ($kategori_id > 0 && !empty($tajuk_kerja)) {
        $stmt = $conn->prepare("INSERT INTO sub_kategori_undi (kategori_id, tajuk_kerja, nilai_projek) VALUES (?, ?, ?)");
        $stmt->bind_param("isd", $kategori_id, $tajuk_kerja, $nilai_projek);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: kategori_undi.php");
    exit();
}

// BACKEND: KEMAS KINI SUB ROW (UPDATE)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_sub') {
    $sub_id       = intval($_POST['sub_id']);
    $tajuk_kerja  = trim($_POST['tajuk_kerja']);
    $nilai_projek = floatval($_POST['nilai_projek']);

    if ($sub_id > 0 && !empty($tajuk_kerja)) {
        $stmt = $conn->prepare("UPDATE sub_kategori_undi SET tajuk_kerja = ?, nilai_projek = ? WHERE id = ?");
        $stmt->bind_param("sdi", $tajuk_kerja, $nilai_projek, $sub_id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: kategori_undi.php");
    exit();
}

// BACKEND: PADAM SUB ROW
if (isset($_GET['action']) && $_GET['action'] == 'delete_sub' && isset($_GET['sub_id'])) {
    $sub_id = intval($_GET['sub_id']);
    $stmt = $conn->prepare("DELETE FROM sub_kategori_undi WHERE id = ?");
    $stmt->bind_param("i", $sub_id);
    $stmt->execute();
    $stmt->close();
    header("Location: kategori_undi.php");
    exit();
}

// DAPATKAN SENARAI KATEGORI UNDI
$senarai_kategori = $conn->query("SELECT * FROM kategori_undi ORDER BY id ASC");

// DAPATKAN SENARAI SUB ROW KATEGORI
$sub_rows_by_kat = [];
$sub_query = $conn->query("SELECT * FROM sub_kategori_undi ORDER BY id ASC");
if ($sub_query) {
    while ($sub = $sub_query->fetch_assoc()) {
        $sub_rows_by_kat[$sub['kategori_id']][] = $sub;
    }
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengurusan Kategori Undi</title>
    <!-- Google Fonts Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-mdbg: #8D5B4C;
            --primary-dark: #6e4438;
            --sidebar-bg: #0f172a;
            --sidebar-active: #8D5B4C;
            --bg-body: #f8fafc;
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
        
        .sidebar-submenu .sub-link-item.active {
            background-color: var(--primary-mdbg) !important;
            color: #ffffff !important;
            border-left-color: #ffc107 !important;
            font-weight: 700;
        }

        .sidebar-submenu .sub-link-item.active i {
            color: #ffc107 !important;
        }

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
        }

        .sidebar-container.collapsed + .main-content-container {
            width: 100%;
        }

        .status-card-container {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            padding: 28px;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.04), 0 8px 10px -6px rgba(15, 23, 42, 0.02);
        }

        .table-responsive-custom {
            display: block;
            width: 100% !important;
            overflow-x: auto !important;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
        }

        .table-mdbg {
            margin-bottom: 0 !important;
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table-mdbg > thead > tr > th {
            padding: 14px 20px !important;
            font-size: 0.75rem !important;
            font-weight: 800 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.8px;
            color: #475569 !important;
            background-color: #f8fafc;
            border-bottom: 2px solid #e2e8f0 !important;
        }

        .cat-parent-row {
            background-color: #f8fafc !important;
            transition: background-color 0.2s ease;
        }

        .cat-parent-row td {
            padding: 14px 20px !important;
            border-top: 1px solid #e2e8f0 !important;
            border-bottom: none !important;
        }

        .cat-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background: #ffffff;
            color: #0f172a;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.92rem;
            border: 1px solid #cbd5e1;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }

        .sub-row-box {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-left: 4px solid var(--primary-mdbg);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            margin: 4px 6px 12px 6px;
        }

        .sub-row-header {
            padding-bottom: 12px;
            margin-bottom: 14px;
            border-bottom: 1px dashed #e2e8f0;
        }

        .sub-table-wrapper {
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #cbd5e1;
        }

        .sub-table {
            margin-bottom: 0 !important;
            border-collapse: collapse !important;
        }

        .sub-table thead tr th {
            background-color: #f1f5f9 !important;
            color: #475569 !important;
            font-size: 0.72rem !important;
            font-weight: 700 !important;
            letter-spacing: 0.5px;
            padding: 10px 14px !important;
            border: 1px solid #cbd5e1 !important;
        }

        .sub-table tbody td {
            padding: 12px 14px !important;
            font-size: 0.86rem !important;
            color: #334155;
            vertical-align: middle;
            border: 1px solid #cbd5e1 !important;
        }

        .sub-table tbody tr:hover {
            background-color: #f8fafc !important;
        }

        .btn-add-kategori {
            background: linear-gradient(135deg, #8D5B4C 0%, #6e4438 100%);
            color: #ffffff;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            padding: 10px 22px;
            box-shadow: 0 3px 10px rgba(141, 91, 76, 0.25);
            transition: all 0.2s ease;
        }
        .btn-add-kategori:hover {
            background: linear-gradient(135deg, #7a4d3f 0%, #5a362b 100%);
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 5px 14px rgba(141, 91, 76, 0.35);
        }

        .btn-soft-primary {
            background-color: #eff6ff;
            color: #2563eb;
            border: 1px solid #bfdbfe;
            font-weight: 600;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        .btn-soft-primary:hover {
            background-color: #2563eb;
            color: #ffffff;
        }

        .btn-soft-danger {
            background-color: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
            font-weight: 600;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        .btn-soft-danger:hover {
            background-color: #dc2626;
            color: #ffffff;
        }

        .btn-soft-success {
            background-color: #f0fdf4;
            color: #16a34a;
            border: 1px solid #bbf7d0;
            font-weight: 600;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        .btn-soft-success:hover {
            background-color: #16a34a;
            color: #ffffff;
        }

        .btn-soft-secondary {
            background-color: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
            font-weight: 600;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        .btn-soft-secondary:hover {
            background-color: #64748b;
            color: #ffffff;
        }

        .toggle-chevron {
            transition: transform 0.2s ease;
            display: inline-block;
        }

        .btn-toggle-subrow[aria-expanded="true"] .toggle-chevron {
            transform: rotate(180deg);
        }

        .badge-nilai {
            background-color: #f0fdf4;
            color: #15803d;
            padding: 5px 10px;
            border-radius: 6px;
            font-weight: 700;
            border: 1px solid #bbf7d0;
            display: inline-block;
        }

        /* Untuk menjadikan placeholder input gred kelayakan lebih kelabu/pudar */
        input[name="gred_kelayakan"]::placeholder {
            color: #7a7979 !important;
            opacity: 0.5 !important;
            font-weight: 300 !important;
        }

        /* Untuk input nama kategori dan pengkhususan juga jika perlu */
        input[name="pilihan"]::placeholder {
            color: #7a7979 !important;
            opacity: 0.5 !important;
            font-weight: 300 !important;
        }

        input[name="pengkhususan"]::placeholder {
            color: #7a7979 !important;
            opacity: 0.5 !important;
            font-weight: 300 !important;
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

    <!-- WRAPPER UTAMA -->
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
                   aria-expanded="false" 
                   aria-controls="menuFasa1">
                    <span>
                        <i class="fa-solid fa-folder-open me-2 text-warning"></i> Fasa 1
                    </span>
                    <i class="fa-solid fa-chevron-down small chevron-icon"></i>
                </a>

                <div class="collapse sidebar-submenu" id="menuFasa1">
                    <a href="kejuruteraan_senarai_kontraktor.php" class="sub-link-item">
                        <i class="fa-solid fa-file-signature me-2"></i> Senarai Kontraktor
                    </a>
                    <a href="kejuruteraan_senarai_borang.php" class="sub-link-item">
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
                   aria-expanded="true" 
                   aria-controls="menuFasa2">
                    <span>
                        <i class="fa-solid fa-folder-open me-2 text-warning"></i> Fasa 2
                    </span>
                    <i class="fa-solid fa-chevron-down small chevron-icon"></i>
                </a>

                <div class="collapse show sidebar-submenu" id="menuFasa2">
                    <a href="senarai_kontraktor_undi.php" class="sub-link-item">
                        <i class="fa-solid fa-box-archive me-2"></i> Senarai Undian
                    </a>
                    <a href="kategori_undi.php" class="sub-link-item active">
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
                
                <div class="status-card-container mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h5 class="fw-bold text-dark m-0 d-flex align-items-center gap-2">
                                <i class="fa-solid fa-tags me-1" style="color: var(--primary-mdbg);"></i>
                                Kategori Tajuk Kontraktor
                            </h5>
                            <p class="text-muted small mb-0 mt-1">Urus senarai pilihan kategori undi serta sub-rekod maklumat kerja berkaitan.</p>
                        </div>
                        <button class="btn btn-add-kategori" onclick="tambahKategoriModal()">
                            <i class="fa-solid fa-plus me-1"></i> Tambah Row Pilihan
                        </button>
                    </div>

                    <div class="table-responsive-custom">
                        <table class="table table-mdbg align-middle">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 70px;">BIL.</th>
                                    <th class="text-start">PILIHAN KATEGORI</th>
                                    <th class="text-center" style="width: 280px;">TINDAKAN</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($senarai_kategori && $senarai_kategori->num_rows > 0): ?>
                                    <?php $no = 1; while($row = $senarai_kategori->fetch_assoc()): ?>
                                        <!-- ROW UTAMA KATEGORI -->
                                        <tr class="cat-parent-row">
                                            <td class="text-center fw-bold text-secondary"><?= $no++; ?>.</td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                                    <div class="cat-badge">
                                                        <i class="fa-solid fa-folder-tree text-warning"></i>
                                                        <span class="font-monospace"><?= htmlspecialchars($row['pilihan']); ?></span>
                                                    </div>
                                                    
                                                    <!-- GRED KELAYAKAN -->
                                                    <?php if (!empty(trim($row['gred_kelayakan'] ?? ''))): ?>
                                                        <span class="badge bg-primary text-white font-monospace px-2 py-1 fs-6">
                                                            Gred: <?= htmlspecialchars($row['gred_kelayakan']); ?>
                                                        </span>
                                                    <?php endif; ?>

                                                    <!-- PENGKHUSUSAN -->
                                                    <?php if (!empty(trim($row['pengkhususan'] ?? ''))): ?>
                                                        <div class="d-flex gap-1 flex-wrap align-items-center">
                                                            <?php 
                                                            $khus_list = explode(' ', trim($row['pengkhususan']));
                                                            foreach ($khus_list as $k_item):
                                                                $k_item = trim($k_item);
                                                                if (empty($k_item)) continue;
                                                            ?>
                                                                <span class="badge bg-secondary-subtle text-dark border font-monospace px-2 py-1 fs-6">
                                                                    <?= htmlspecialchars($k_item); ?>
                                                                </span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex gap-2 justify-content-center">
                                                    <!-- BUTANG COLLAPSE HIDE / UNHIDE SUB ROW (CENTERED) -->
                                                    <button class="btn btn-sm btn-soft-secondary d-inline-flex align-items-center justify-content-center btn-toggle-subrow" 
                                                            type="button" 
                                                            data-bs-toggle="collapse" 
                                                            data-bs-target="#subrow-<?= $row['id']; ?>" 
                                                            aria-expanded="true" 
                                                            aria-controls="subrow-<?= $row['id']; ?>" 
                                                            title="Papar/Sembunyi Sub Row"
                                                            style="width: 34px; height: 31px; padding: 0;">
                                                        <i class="fa-solid fa-chevron-down toggle-chevron"></i> 
                                                    </button>
                                                    <button class="btn btn-sm btn-soft-primary px-3" 
                                                            onclick="editKategoriModal(<?= $row['id']; ?>, '<?= htmlspecialchars($row['pilihan'], ENT_QUOTES); ?>', '<?= htmlspecialchars($row['gred_kelayakan'] ?? '', ENT_QUOTES); ?>', '<?= htmlspecialchars($row['pengkhususan'] ?? '', ENT_QUOTES); ?>')" 
                                                            title="Edit Pilihan">
                                                        <i class="fa-solid fa-pen-to-square me-1"></i> Edit
                                                    </button>
                                                    <button class="btn btn-sm btn-soft-danger btn-delete px-3" 
                                                            data-id="<?= $row['id']; ?>" 
                                                            data-pilihan="<?= htmlspecialchars($row['pilihan'], ENT_QUOTES); ?>" 
                                                            title="Padam Pilihan">
                                                        <i class="fa-solid fa-trash-can me-1"></i> Padam
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- SUB ROW DI BAWAH KATEGORI (DIKAPSUKAN DALAM BOOTSTRAP COLLAPSE - TERTETAP TERBUKA) -->
                                        <tr>
                                            <td colspan="3" class="p-0 border-0 bg-light">
                                                <div class="collapse show" id="subrow-<?= $row['id']; ?>">
                                                    <div class="p-2">
                                                        <div class="sub-row-box">
                                                            <div class="d-flex justify-content-between align-items-center sub-row-header">
                                                                <div class="fw-bold text-dark small d-flex align-items-center gap-2">
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                                                        <i class="fa-solid fa-sitemap me-1 text-primary"></i> SUB ROW
                                                                    </span>
                                                                    <span class="text-muted">Kategori:</span>
                                                                    <strong class="font-monospace text-dark"><?= htmlspecialchars($row['pilihan']); ?></strong>
                                                                </div>
                                                                <button class="btn btn-sm btn-soft-success px-3" 
                                                                        onclick="tambahSubRowModal(<?= $row['id']; ?>, '<?= htmlspecialchars($row['pilihan'], ENT_QUOTES); ?>')">
                                                                    <i class="fa-solid fa-plus me-1"></i> Tambah Sub Row
                                                                </button>
                                                            </div>

                                                            <div class="sub-table-wrapper">
                                                                <table class="table table-sm sub-table align-middle">
                                                                    <thead>
                                                                        <tr>
                                                                            <th style="width: 50px;" class="text-center">#</th>
                                                                            <th>TAJUK KERJA</th>
                                                                            <th class="text-end" style="width: 170px;">NILAI PROJEK (RM)</th>
                                                                            <th class="text-center" style="width: 120px;">TINDAKAN</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php 
                                                                        $kat_id = $row['id'];
                                                                        if (!empty($sub_rows_by_kat[$kat_id])): 
                                                                            $sub_no = 1;
                                                                            foreach ($sub_rows_by_kat[$kat_id] as $sub):
                                                                        ?>
                                                                            <tr>
                                                                                <td class="text-center fw-semibold text-muted small"><?= $sub_no++; ?></td>
                                                                                <td class="text-wrap"><?= htmlspecialchars($sub['tajuk_kerja']); ?></td>
                                                                                <td class="text-end">
                                                                                    <span class="badge-nilai font-monospace">
                                                                                        <?= number_format($sub['nilai_projek'], 2); ?>
                                                                                    </span>
                                                                                </td>
                                                                                <td class="text-center">
                                                                                    <div class="d-flex gap-1 justify-content-center">
                                                                                        <button class="btn btn-sm btn-soft-primary btn-edit-sub py-1 px-2" 
                                                                                                data-id="<?= $sub['id']; ?>" 
                                                                                                data-tajuk="<?= htmlspecialchars($sub['tajuk_kerja'], ENT_QUOTES); ?>" 
                                                                                                data-nilai="<?= $sub['nilai_projek']; ?>" 
                                                                                                title="Edit Sub Row">
                                                                                            <i class="fa-solid fa-pen-to-square small"></i>
                                                                                        </button>
                                                                                        <button class="btn btn-sm btn-soft-danger btn-delete-sub py-1 px-2" 
                                                                                                data-id="<?= $sub['id']; ?>" 
                                                                                                title="Padam Sub Row">
                                                                                            <i class="fa-solid fa-trash-can small"></i>
                                                                                        </button>
                                                                                    </div>
                                                                                </td>
                                                                            </tr>
                                                                        <?php 
                                                                            endforeach; 
                                                                        else: 
                                                                        ?>
                                                                            <tr>
                                                                                <td colspan="4" class="text-center py-4 text-muted bg-white">
                                                                                    <i class="fa-solid fa-folder-open d-block mb-2 fs-4 opacity-50 text-secondary"></i>
                                                                                    <span class="small">Tiada rekod sub row ditemui. Klik butang <strong class="text-dark">"Tambah Sub Row"</strong> untuk mendaftar.</span>
                                                                                </td>
                                                                            </tr>
                                                                        <?php endif; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-5 text-muted">
                                            <i class="fa-solid fa-folder-open fs-2 d-block mb-3 opacity-40 text-secondary"></i>
                                            <span class="fs-6 fw-semibold d-block text-dark mb-1">Tiada Rekod Pilihan Kategori</span>
                                            <span class="small">Sila klik butang <strong>"Tambah Row Pilihan"</strong> di atas untuk memulakan.</span>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- BOOTSTRAP & SWEETALERT JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebarWrapper');
            if (sidebar) sidebar.classList.toggle('collapsed');
        });

        // POPUP TAMBAH ROW PILIHAN
        function tambahKategoriModal() {
            Swal.fire({
                title: '<div class="fw-bold text-dark fs-5"><i class="fa-solid fa-plus-circle me-2" style="color: #8D5B4C;"></i>Tambah Pilihan Kategori</div>',
                html: `
                    <form id="formTambah" action="kategori_undi.php" method="POST" class="text-start px-2">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary small">Nama Kategori (Contoh: KATEGORI A)</label>
                            <input type="text" name="pilihan" class="form-control font-monospace fw-bold text-uppercase" placeholder="KATEGORI A" required autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary small">Gred Kelayakan (Contoh: G1, G2)</label>
                            <input type="text" name="gred_kelayakan" class="form-control font-monospace text-uppercase" placeholder="Contoh : G1" autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary small">Pengkhususan (Kod CIDB diasingkan dengan jarak)</label>
                            <input type="text" name="pengkhususan" class="form-control font-monospace text-uppercase" placeholder="Contoh : B01 B02 B03" autocomplete="off">
                        </div>
                    </form>
                `,
                showCancelButton: true,
                confirmButtonText: 'Simpan',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#8D5B4C',
                preConfirm: () => {
                    const input = Swal.getPopup().querySelector('input[name="pilihan"]').value;
                    if (!input.trim()) {
                        Swal.showValidationMessage('Sila masukkan teks pilihan!');
                        return false;
                    }
                    return true;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('formTambah').submit();
                }
            });
        }

        // POPUP EDIT ROW PILIHAN, GRED & PENGKHUSUSAN
        function editKategoriModal(id, pilihanSemasa, gredSemasa, pengkhususanSemasa) {
            Swal.fire({
                title: '<div class="fw-bold text-dark fs-5"><i class="fa-solid fa-pen-to-square me-2" style="color: #8D5B4C;"></i>Kemas Kini Kategori</div>',
                html: `
                    <form id="formEdit" action="kategori_undi.php" method="POST" class="text-start px-2">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="${id}">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary small">Pilihan Kategori</label>
                            <input type="text" name="pilihan" class="form-control font-monospace fw-bold text-uppercase" value="${pilihanSemasa}" required autocomplete="off">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary small">Gred Kelayakan (Contoh: G1, G2)</label>
                            <input type="text" name="gred_kelayakan" class="form-control font-monospace text-uppercase" value="${gredSemasa || ''}" placeholder="Contoh : G1" autocomplete="off">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary small">Pengkhususan</label>
                            <input type="text" id="inputCode" class="form-control font-monospace text-uppercase mb-2" placeholder="CONTOH : B01 B02">
                            <div id="badgeContainer" class="d-flex flex-wrap gap-1 p-2 border rounded bg-light min-height-40"></div>
                            <input type="hidden" name="pengkhususan" id="hiddenPengkhususan" value="${pengkhususanSemasa || ''}">
                        </div>
                    </form>
                `,
                didOpen: () => {
                    const hiddenInput = document.getElementById('hiddenPengkhususan');
                    const badgeContainer = document.getElementById('badgeContainer');
                    const inputCode = document.getElementById('inputCode');

                    let tags = hiddenInput.value ? hiddenInput.value.split(' ').filter(Boolean) : [];

                    function renderTags() {
                        badgeContainer.innerHTML = '';
                        if (tags.length === 0) {
                            badgeContainer.innerHTML = '<span class="text-muted small italic">Tiada pengkhususan ditambah.</span>';
                        } else {
                            tags.forEach((tag, index) => {
                                const span = document.createElement('span');
                                span.className = 'badge bg-secondary-subtle text-dark border font-monospace fs-6 d-inline-flex align-items-center gap-1 px-2 py-1';
                                span.innerHTML = `${tag} <i class="fa-solid fa-xmark text-danger ms-1" style="cursor:pointer;" onclick="removeTag(${index})"></i>`;
                                badgeContainer.appendChild(span);
                            });
                        }
                        hiddenInput.value = tags.join(' ');
                    }

                    window.removeTag = function(idx) {
                        tags.splice(idx, 1);
                        renderTags();
                    };

                    function addTag() {
                        let val = inputCode.value.trim().toUpperCase().replace(/[\[\]]/g, '');
                        if (val) {
                            if (!tags.includes(val)) {
                                tags.push(val);
                                renderTags();
                            }
                            inputCode.value = '';
                        }
                    }

                    inputCode.addEventListener('keypress', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            addTag();
                        }
                    });

                    renderTags();
                },
                showCancelButton: true,
                confirmButtonText: 'Kemaskini',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#8D5B4C',
                preConfirm: () => {
                    const input = Swal.getPopup().querySelector('input[name="pilihan"]').value;
                    if (!input.trim()) {
                        Swal.showValidationMessage('Sila masukkan teks pilihan!');
                        return false;
                    }

                    const inputCode = Swal.getPopup().querySelector('#inputCode');
                    const hiddenInput = Swal.getPopup().querySelector('#hiddenPengkhususan');
                    if (inputCode && inputCode.value.trim()) {
                        let val = inputCode.value.trim().toUpperCase().replace(/[\[\]]/g, '');
                        let currentTags = hiddenInput.value ? hiddenInput.value.split(' ').filter(Boolean) : [];
                        if (val && !currentTags.includes(val)) {
                            currentTags.push(val);
                            hiddenInput.value = currentTags.join(' ');
                        }
                    }
                    return true;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('formEdit').submit();
                }
            });
        }

        // POPUP TAMBAH SUB ROW
        function tambahSubRowModal(kategoriId, namaPilihanUtama) {
            Swal.fire({
                title: `<div class="fw-bold text-dark fs-5"><i class="fa-solid fa-plus-circle me-2" style="color: #8D5B4C;"></i>Tambah Sub Row (${namaPilihanUtama})</div>`,
                html: `
                    <form id="formTambahSub" action="kategori_undi.php" method="POST" class="text-start px-2">
                        <input type="hidden" name="action" value="add_sub">
                        <input type="hidden" name="kategori_id" value="${kategoriId}">

                        <div class="mb-2">
                            <label class="form-label fw-bold text-secondary small mb-1">Tajuk Kerja</label>
                            <textarea name="tajuk_kerja" class="form-control form-control-sm" rows="2" placeholder="Masukkan tajuk kerja..." required></textarea>
                        </div>

                        <div class="mb-2">
                            <label class="form-label fw-bold text-secondary small mb-1">Nilai Projek (RM)</label>
                            <input type="number" step="0.01" name="nilai_projek" class="form-control form-control-sm font-monospace" placeholder="0.00" required autocomplete="off">
                        </div>
                    </form>
                `,
                showCancelButton: true,
                confirmButtonText: 'Simpan Sub Row',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#8D5B4C',
                preConfirm: () => {
                    const tajukKerja = Swal.getPopup().querySelector('textarea[name="tajuk_kerja"]').value;
                    const nilaiProjek = Swal.getPopup().querySelector('input[name="nilai_projek"]').value;

                    if (!tajukKerja.trim() || !nilaiProjek.trim()) {
                        Swal.showValidationMessage('Sila lengkapkan semua maklumat sub row!');
                        return false;
                    }
                    return true;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('formTambahSub').submit();
                }
            });
        }

        // POPUP EDIT SUB ROW
        function editSubRowModal(subId, tajukKerjaSemasa, nilaiProjekSemasa) {
            Swal.fire({
                title: `<div class="fw-bold text-dark fs-5"><i class="fa-solid fa-pen-to-square me-2" style="color: #8D5B4C;"></i>Kemas Kini Sub Row</div>`,
                html: `
                    <form id="formEditSub" action="kategori_undi.php" method="POST" class="text-start px-2">
                        <input type="hidden" name="action" value="update_sub">
                        <input type="hidden" name="sub_id" value="${subId}">

                        <div class="mb-2">
                            <label class="form-label fw-bold text-secondary small mb-1">Tajuk Kerja</label>
                            <textarea name="tajuk_kerja" class="form-control form-control-sm" rows="2" placeholder="Masukkan tajuk kerja..." required>${tajukKerjaSemasa}</textarea>
                        </div>

                        <div class="mb-2">
                            <label class="form-label fw-bold text-secondary small mb-1">Nilai Projek (RM)</label>
                            <input type="number" step="0.01" name="nilai_projek" class="form-control form-control-sm font-monospace" value="${nilaiProjekSemasa}" placeholder="0.00" required autocomplete="off">
                        </div>
                    </form>
                `,
                showCancelButton: true,
                confirmButtonText: 'Kemaskini Sub Row',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#8D5B4C',
                preConfirm: () => {
                    const tajukKerja = Swal.getPopup().querySelector('textarea[name="tajuk_kerja"]').value;
                    const nilaiProjek = Swal.getPopup().querySelector('input[name="nilai_projek"]').value;

                    if (!tajukKerja.trim() || !nilaiProjek.trim()) {
                        Swal.showValidationMessage('Sila lengkapkan semua maklumat sub row!');
                        return false;
                    }
                    return true;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('formEditSub').submit();
                }
            });
        }

        // PROSES CLICK EVENTS (PADAM & EDIT SUB ROW)
        document.addEventListener('click', function(e) {
            // PROSES EDIT SUB ROW
            const btnEditSub = e.target.closest('.btn-edit-sub');
            if (btnEditSub) {
                e.preventDefault();
                const subId = btnEditSub.getAttribute('data-id');
                const tajuk = btnEditSub.getAttribute('data-tajuk');
                const nilai = btnEditSub.getAttribute('data-nilai');
                editSubRowModal(subId, tajuk, nilai);
            }

            // PROSES PADAM BARIS KATEGORI
            const btnDelete = e.target.closest('.btn-delete');
            if (btnDelete) {
                e.preventDefault();
                const id = btnDelete.getAttribute('data-id');
                const pilihan = btnDelete.getAttribute('data-pilihan');
                
                Swal.fire({
                    title: 'Adakah anda pasti?',
                    text: `Pilihan "${pilihan}" dan semua sub row di dalamnya akan dipadamkan!`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, Padam!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = `kategori_undi.php?action=delete&id=${id}`;
                    }
                });
            }

            // PROSES PADAM SUB ROW
            const btnDeleteSub = e.target.closest('.btn-delete-sub');
            if (btnDeleteSub) {
                e.preventDefault();
                const subId = btnDeleteSub.getAttribute('data-id');

                Swal.fire({
                    title: 'Adakah anda pasti?',
                    text: `Sub row ini akan dipadamkan!`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, Padam!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = `kategori_undi.php?action=delete_sub&sub_id=${subId}`;
                    }
                });
            }
        });
    </script>
</body>
</html>