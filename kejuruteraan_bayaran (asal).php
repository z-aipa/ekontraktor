<?php
session_start();

// Sekat daripada menyimpan cache halaman ini
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Pastikan hanya admin kejuruteraan yang boleh akses
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kejuruteraan') { 
    header("Location: index.php"); 
    exit(); 
}

include 'db.php';

$target_dir = "uploads/";
define('MAX_FILE_SIZE', 10485760); // Had maksimum saiz fail: 10MB (10 * 1024 * 1024 bytes)

// ==========================================
// LOGIK PROSES MUAT NAIK BIL (ADMIN)
// ==========================================
if (isset($_POST['action_upload_bil'])) {
    $profil_id = mysqli_real_escape_string($conn, $_POST['profil_id']);
    if (!empty($_FILES['fail_bil']['name'])) {
        
        // Validasi Saiz Fail (Tidak boleh lebih 10MB)
        if ($_FILES['fail_bil']['size'] > MAX_FILE_SIZE) {
            echo "<script>alert('Gagal! Saiz fail bil melebihi had maksimum 10MB.'); window.location.href='kejuruteraan_bayaran.php';</script>";
            exit();
        }

        $filename = time() . "_bil_" . basename($_FILES['fail_bil']['name']);
        $target_file = $target_dir . $filename;
        if (move_uploaded_file($_FILES['fail_bil']['tmp_name'], $target_file)) {
            $conn->query("UPDATE kontraktor_profil SET fail_bil = '$filename' WHERE id = '$profil_id'");
            echo "<script>alert('Bil pendaftaran berjaya dihantar ke dashboard kontraktor!'); window.location.href='kejuruteraan_bayaran.php';</script>";
            exit();
        } else {
            echo "<script>alert('Gagal memuat naik bil.');</script>";
        }
    }
}

// ==========================================
// LOGIK PROSES MUAT NAIK SIJIL (ADMIN)
// ==========================================
if (isset($_POST['action_upload_sijil'])) {
    $profil_id = mysqli_real_escape_string($conn, $_POST['profil_id']);
    
    // Validasi Status Kontraktor (Sila tukar status kontraktor sudah bayar baru boleh upload)
    $semak_kontraktor = $conn->query("SELECT status_bayaran_daftar FROM kontraktor_profil WHERE id = '$profil_id'")->fetch_assoc();
    if (!$semak_kontraktor || $semak_kontraktor['status_bayaran_daftar'] != 'Sudah Bayar') {
        echo "<script>alert('Gagal! Sila tukar status kontraktor kepada Sudah Bayar terlebih dahulu sebelum memuat naik sijil.'); window.location.href='kejuruteraan_bayaran.php';</script>";
        exit();
    }

    if (!empty($_FILES['fail_sijil']['name'])) {
        
        // Validasi Saiz Fail (Tidak boleh lebih 10MB)
        if ($_FILES['fail_sijil']['size'] > MAX_FILE_SIZE) {
            echo "<script>alert('Gagal! Saiz fail sijil melebihi had maksimum 10MB.'); window.location.href='kejuruteraan_bayaran.php';</script>";
            exit();
        }

        $filename = time() . "_sijil_" . basename($_FILES['fail_sijil']['name']);
        $target_file = $target_dir . $filename;
        if (move_uploaded_file($_FILES['fail_sijil']['tmp_name'], $target_file)) {
            // Sebaik sahaja sijil dimuat naik, automatik tukar status_bayaran_daftar kepada 'Sudah Bayar'
            $conn->query("UPDATE kontraktor_profil SET fail_sijil = '$filename', status_bayaran_daftar = 'Sudah Bayar' WHERE id = '$profil_id'");
            echo "<script>alert('Sijil pendaftaran berjaya dihantar ke dashboard kontraktor!'); window.location.href='kejuruteraan_bayaran.php';</script>";
            exit();
        } else {
            echo "<script>alert('Gagal memuat naik sijil.');</script>";
        }
    }
}

// Ambil senarai semua kontraktor yang status borangnya 'Lengkap' untuk urusan pembayaran
$senarai_kontraktor = $conn->query("SELECT * FROM kontraktor_profil WHERE status_borang = 'Lengkap' ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resit & Sijil | Jabatan Kejuruteraan Admin</title>
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

        .status-header {
            font-size: 1.15rem;
            font-weight: 800;
            color: #0f172a;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 12px;
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
            border-collapse: collapse;
        }

        .table-mdbg th {
            padding: 16px 20px !important;
            font-size: 0.78rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            color: #475569 !important;
            white-space: nowrap !important; 
            background-color: #f8fafc;
            border-bottom: 2px solid var(--border-color);
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
            font-size: 0.90rem !important;
            color: #334155;
            vertical-align: middle;
        }

        .w-fit {
            width: fit-content;
        }

        @media (max-width: 768px) {
            .main-content-container { padding: 18px; }
        }
    </style>
</head>
<body>

    <!-- NAVBAR ADMIN -->
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

    <!-- WRAPPER CONTENT -->
    <div class="wrapper">
        
        <!-- SIDEBAR ADMIN -->
        <div class="sidebar-container" id="sidebarWrapper">
            <div class="sidebar-menu">
                <div class="sidebar-category-title">Menu Utama</div>
                <a href="kejuruteraan.php" class="nav-link-item">
                    <i class="fa-solid fa-chart-pie me-2"></i> Dashboard
                </a>
                <a href="data_analysis.php" class="nav-link-item">
                    <i class="fa-solid fa-chart-line me-2"></i> Data Analysis
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
                    <a href="kejuruteraan_senarai_borang.php" class="sub-link-item">
                        <i class="fa-solid fa-check-to-slot me-2"></i> Tarikh Sah Borang
                    </a>
                    <a href="kejuruteraan_bayaran.php" class="sub-link-item active">
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
                </div>

            </div>
        </div>

        <!-- MAIN SITE CONTENT -->
        <div class="main-content-container" id="contentWrapper">
            <div class="container-fluid p-0">
                
                <div class="status-card-container mb-4">
                    <h4 class="status-header mb-3"><i class="fa-solid fa-receipt me-2 text-warning"></i>Pengurusan Resit & Sijil Pendaftaran</h4>
                    <p class="text-muted small mb-4">Uruskan penghantaran bil pendaftaran kontraktor, semak muat turun resit bayaran yang dihantar, serta keluarkan Sijil Kelayakan Pembekal Rasmi.</p>
                    
                    <div class="table-responsive-custom">
                        <table class="table table-mdbg align-middle">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 5%;">BIL.</th>
                                    <th style="width: 30%;">MAKLUMAT SYARIKAT</th>
                                    <th class="text-center" style="width: 10%;">GRED</th>
                                    <th style="width: 20%;">1. TINDAKAN BIL</th>
                                    <th style="width: 15%;">2. RESIT KONTRAKTOR</th>
                                    <th style="width: 20%;">3. TINDAKAN SIJIL</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1;
                                if ($senarai_kontraktor->num_rows > 0):
                                    while($row = $senarai_kontraktor->fetch_assoc()):
                                ?>
                                <tr>
                                    <td class="text-center fw-semibold text-muted"><?= $no++; ?>.</td>
                                    <td>
                                        <strong class="d-block text-dark text-uppercase"><?= htmlspecialchars($row['nama_syarikat']); ?></strong>
                                        <span class="text-muted small d-block mt-1"><i class="fa-solid fa-id-card me-1"></i> <?= htmlspecialchars($row['no_pendaftaran']); ?></span>
                                        <span class="text-muted small d-block"><i class="fa-solid fa-envelope me-1"></i> <?= htmlspecialchars($row['email_aktif']); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary px-2.5 py-1.5 rounded-2"><?= htmlspecialchars($row['gred_cidb_kewangan']); ?></span>
                                    </td>
                                    
                                    <!-- 1. UPLOAD BIL -->
                                    <td>
                                        <?php if(empty($row['fail_bil'])): ?>
                                            <form action="" method="POST" enctype="multipart/form-data" class="d-flex align-items-center gap-1 m-0">
                                                <input type="hidden" name="profil_id" value="<?= $row['id']; ?>">
                                                <input type="hidden" name="action_upload_bil" value="1">
                                                <input type="file" name="fail_bil" class="form-control form-control-sm" accept="application/pdf" required style="max-width: 140px;">
                                                <button type="submit" class="btn btn-sm btn-warning text-dark text-nowrap rounded-2" title="Hantar Bil"><i class="fa-solid fa-paper-plane"></i></button>
                                            </form>
                                        <?php else: ?>
                                            <div class="d-flex flex-column gap-1">
                                                <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2 small w-fit rounded-2"><i class="fa-solid fa-circle-check"></i> Bil Dihantar</span>
                                                <a href="./uploads/<?= $row['fail_bil']; ?>" target="_blank" class="text-primary small fw-semibold text-decoration-none mt-1"><i class="fa-solid fa-file-pdf me-1"></i> Lihat Fail Bil</a>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- 2. DOWNLOAD RESIT -->
                                    <td>
                                        <?php if(!empty($row['fail_resit'])): ?>
                                            <div class="d-flex flex-column gap-1">
                                                <span class="badge bg-info-subtle text-dark border border-info-subtle py-1 px-2 small w-fit rounded-2"><i class="fa-solid fa-money-bill-wave"></i> Resit Masuk</span>
                                                <a href="./uploads/<?= $row['fail_resit']; ?>" target="_blank" class="btn btn-sm btn-outline-success fw-bold py-1 px-2 mt-1 align-self-start rounded-2" style="font-size: 0.75rem;"><i class="fa-solid fa-download me-1"></i> Muat Turun</a>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small fst-italic"><i class="fa-solid fa-spinner fa-spin me-1"></i> Menunggu Bayaran</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- 3. UPLOAD SIJIL -->
                                    <td>
                                        <?php if(empty($row['fail_sijil'])): ?>
                                            <form action="" method="POST" enctype="multipart/form-data" class="d-flex align-items-center gap-1 m-0">
                                                <input type="hidden" name="profil_id" value="<?= $row['id']; ?>">
                                                <input type="hidden" name="action_upload_sijil" value="1">
                                                <input type="file" name="fail_sijil" class="form-control form-control-sm" accept="application/pdf" required style="max-width: 140px;" <?= $row['status_bayaran_daftar'] != 'Sudah Bayar' ? 'disabled title="Sila tukar status kontraktor kepada Sudah Bayar terlebih dahulu"' : ''; ?>>
                                                <button type="submit" class="btn btn-sm btn-dark text-nowrap rounded-2" title="Hantar Sijil" <?= $row['status_bayaran_daftar'] != 'Sudah Bayar' ? 'disabled' : ''; ?>><i class="fa-solid fa-upload"></i></button>
                                            </form>
                                            <?php if($row['status_bayaran_daftar'] != 'Sudah Bayar'): ?>
                                                <span class="text-danger d-block mt-1" style="font-size: 0.7rem;">*Sila Tukar Status Kepada Sudah Bayar</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="d-flex flex-column gap-1">
                                                <span class="badge bg-dark text-white border border-dark py-1 px-2 small w-fit rounded-2"><i class="fa-solid fa-award text-warning"></i> Sijil Aktif</span>
                                                <a href="./uploads/<?= $row['fail_sijil']; ?>" target="_blank" class="text-dark small fw-semibold text-decoration-none mt-1"><i class="fa-solid fa-file-invoice me-1"></i> Lihat Sijil Kelayakan</a>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Tiada kontraktor berstatus 'Lengkap' yang ditemui buat masa ini.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebarWrapper');
            if (sidebar) sidebar.classList.toggle('collapsed');
        });
    </script>
</body>
</html>