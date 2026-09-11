<?php
session_start();

// 1. Sekat daripada menyimpan cache halaman ini
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kejuruteraan') { header("Location: index.php"); exit(); }
include 'db.php';

// BACKEND: PROSES PADAM DATA (DELETE)
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("DELETE FROM kontraktor_profil WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: kejuruteraan_senarai_kontraktor.php");
    exit();
}

// BACKEND: PROSES RESET PASSWORD
if (isset($_GET['action']) && $_GET['action'] == 'reset_password' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $default_password = password_hash('#Abc123', PASSWORD_DEFAULT);
    
    // Kemaskini password dalam jadual users melalui user_id
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = (SELECT user_id FROM kontraktor_profil WHERE id = ?)");
    $stmt->bind_param("si", $default_password, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: kejuruteraan_senarai_kontraktor.php?msg=reset_success");
    exit();
}

// BACKEND: PROSES KEMAS KINI DATA (UPDATE)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    $id = intval($_POST['id']);
    $nama_syarikat = $_POST['nama_syarikat'];
    $no_pendaftaran = $_POST['no_pendaftaran'];
    $gred_cidb_kewangan = $_POST['gred_cidb_kewangan'];
    $no_telefon_syarikat = $_POST['no_telefon_syarikat'];
    $email_aktif = $_POST['email_aktif'];
    $status_borang = $_POST['status_borang'];
    $status_bayaran_daftar = $_POST['status_bayaran_daftar'];
    $alasan_tolak = $_POST['alasan_tolak'];

    // 1. Dapatkan data kontraktor sedia ada terlebih dahulu
    $stmt_get = $conn->prepare("SELECT tarikh_mula_aktif, tarikh_tamat_aktif FROM kontraktor_profil WHERE id = ?");
    $stmt_get->bind_param("i", $id);
    $stmt_get->execute();
    $get_existing = $stmt_get->get_result()->fetch_assoc();
    $stmt_get->close();

    $tarikh_mula_aktif = $get_existing['tarikh_mula_aktif'] ?? '';
    $tarikh_tamat_aktif = $get_existing['tarikh_tamat_aktif'] ?? '';

    // 2. Jika status ditukar/dikekalkan kepada 'Sudah Bayar' dan 'Lengkap'
    if ($status_bayaran_daftar == 'Sudah Bayar' && $status_borang == 'Lengkap') {
        if (empty($tarikh_mula_aktif) || $tarikh_mula_aktif == '0000-00-00' || $tarikh_mula_aktif == '-') {
            $tarikh_mula_aktif = date('Y-m-d');
            $tarikh_tamat_aktif = date('Y-m-d', strtotime('+1 year'));
        }
    }

    $stmt = $conn->prepare("UPDATE kontraktor_profil SET 
        nama_syarikat = ?, 
        no_pendaftaran = ?, 
        gred_cidb_kewangan = ?, 
        no_telefon_syarikat = ?, 
        email_aktif = ?, 
        status_borang = ?, 
        status_bayaran_daftar = ?, 
        alasan_tolak = ?,
        tarikh_mula_aktif = ?,
        tarikh_tamat_aktif = ? 
        WHERE id = ?");
        
    $stmt->bind_param("ssssssssssi", 
        $nama_syarikat, 
        $no_pendaftaran, 
        $gred_cidb_kewangan, 
        $no_telefon_syarikat, 
        $email_aktif, 
        $status_borang, 
        $status_bayaran_daftar, 
        $alasan_tolak,
        $tarikh_mula_aktif,
        $tarikh_tamat_aktif,
        $id
    );
    $stmt->execute();
    $stmt->close();
    header("Location: kejuruteraan_senarai_kontraktor.php");
    exit();
}

$edit_data = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $stmt = $conn->prepare("SELECT * FROM kontraktor_profil WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// FILTER & CARIAN BACKEND
$filter_status = $_GET['status_tempoh'] ?? '';
$filter_jenis = $_GET['jenis_pendaftaran'] ?? '';
$filter_borang = $_GET['status_borang'] ?? '';
$search_query = trim($_GET['search'] ?? '');

$today = date('Y-m-d');
$thirty_days_later = date('Y-m-d', strtotime('+30 days'));

$query_str = "SELECT * FROM kontraktor_profil WHERE 1=1";
$params = [];
$types = "";

if ($filter_status == 'latest') {
    $query_str .= " AND tarikh_tamat_aktif >= ?";
    $params[] = $today;
    $types .= "s";
} elseif ($filter_status == 'almost_expired') {
    $query_str .= " AND tarikh_tamat_aktif >= ? AND tarikh_tamat_aktif <= ?";
    $params[] = $today;
    $params[] = $thirty_days_later;
    $types .= "ss";
} elseif ($filter_status == 'expired') {
    $query_str .= " AND tarikh_tamat_aktif < ? AND tarikh_tamat_aktif IS NOT NULL AND tarikh_tamat_aktif != '' AND tarikh_tamat_aktif != '-'";
    $params[] = $today;
    $types .= "s";
}

if (!empty($filter_jenis)) {
    if ($filter_jenis == 'BAHARU') {
        $query_str .= " AND jenis_pendaftaran LIKE ? AND jenis_pendaftaran NOT LIKE '%PEMBAHARUAN%'";
        $params[] = "%BAHARU%";
        $types .= "s";
    } elseif ($filter_jenis == 'PEMBAHARUAN') {
        $query_str .= " AND jenis_pendaftaran LIKE ?";
        $params[] = "%PEMBAHARUAN%";
        $types .= "s";
    }
}

if (!empty($filter_borang)) {
    if ($filter_borang == 'Pending') {
        $query_str .= " AND (status_borang = 'Pending' OR status_borang IS NULL OR status_borang = '')";
    } elseif ($filter_borang == 'Gagal') {
        $query_str .= " AND (status_borang = 'Gagal' OR status_borang = 'Tidak Lengkap')";
    } else {
        $query_str .= " AND status_borang = ?";
        $params[] = $filter_borang;
        $types .= "s";
    }
}

if (!empty($search_query)) {
    $query_str .= " AND (nama_syarikat LIKE ? OR no_pendaftaran LIKE ?)";
    $search_param = "%" . $search_query . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$query_str .= " ORDER BY id DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($query_str);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $senarai_kontraktor = $stmt->get_result();
} else {
    $senarai_kontraktor = $conn->query($query_str);
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Senarai Penuh Kontraktor Berdaftar</title>
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
        
        /* NAVBAR MODEN */
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

        .sidebar-menu .nav-link-item .chevron-icon {
            transition: transform 0.2s ease;
        }

        .sidebar-menu .nav-link-item[aria-expanded="true"] .chevron-icon {
            transform: rotate(180deg);
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
            border: 1px solid #e2e8f0;
            background: #ffffff;
        }

        .table-mdbg {
            margin-bottom: 0 !important;
            width: 100%;
            min-width: 1350px; 
            border-collapse: collapse;
        }

        .table-mdbg th {
            padding: 18px 20px !important;
            font-size: 0.78rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            color: #475569 !important;
            white-space: nowrap !important; 
            text-align: center !important;
        }

        .table-mdbg tbody tr {
            border-bottom: 2px solid #d0d3d8 !important;
        }

        .table-mdbg td {
            padding: 16px 20px !important;
            font-size: 0.92rem !important;
            color: #334155;
            vertical-align: middle;
            white-space: nowrap !important; 
        }

        /* GAYA JADUAL DALAMAN GRED CIDB / KEWANGAN */
        .table-mdbg td table {
            width: auto !important;
            margin: 0 auto !important;
            font-size: 0.65rem !important;
        }
        .table-mdbg td table th, 
        .table-mdbg td table td {
            padding: 2px 6px !important;
            font-size: 0.65rem !important;
            white-space: nowrap !important;
        }

        .badge-status {
            padding: 6px 12px;
            font-size: 0.8rem;
            font-weight: 600;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .badge-status.status-lengkap { background-color: #dcfce7; color: #15803d; }
        .badge-status.status-pending { background-color: #fef9c3; color: #a16207; }
        .badge-status.status-gagal { background-color: #fee2e2; color: #b91c1c; }
        .badge-status.bayar-disahkan { background-color: #e0f2fe; color: #0369a1; }
        .badge-status.bayar-belum { background-color: #f1f5f9; color: #475569; }

        .company-icon-box {
            width: 36px;
            height: 36px;
            background-color: #f1f5f9;
            color: #8D5B4C;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .reason-box {
            background-color: #fee2e2;
            border-left: 3px solid #ef4444;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 0.8rem;
            min-width: 280px;
            max-width: 450px;
            white-space: normal !important; 
        }

        .btn-filter-tapis {
            background: linear-gradient(135deg, #8D5B4C 0%, #6e4438 100%);
            color: #ffffff;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            padding: 8px 20px;
            box-shadow: 0 2px 6px rgba(141, 91, 76, 0.25);
            transition: all 0.2s ease;
        }
        .btn-filter-tapis:hover {
            background: linear-gradient(135deg, #7a4d3f 0%, #5a362b 100%);
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(141, 91, 76, 0.35);
        }

        .btn-filter-reset {
            background-color: #ffffff;
            color: #64748b;
            border: 1px solid #cbd5e1;
            font-weight: 600;
            border-radius: 8px;
            padding: 8px 18px;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .btn-filter-reset:hover {
            background-color: #f1f5f9;
            color: #334155;
            border-color: #94a3b8;
        }

        .quick-search-box {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 12px 16px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.02);
            transition: all 0.2s ease;
        }
        .quick-search-box:focus-within {
            border-color: #8D5B4C;
            box-shadow: 0 4px 12px rgba(141, 91, 76, 0.1);
        }
        .quick-search-input {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 8px 14px 8px 38px;
            font-size: 0.88rem;
            font-weight: 500;
            color: var(--text-main);
            transition: all 0.2s ease;
        }
        .quick-search-input:focus {
            border-color: #8D5B4C;
            box-shadow: 0 0 0 3px rgba(141, 91, 76, 0.15);
            outline: none;
        }
        .quick-search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.9rem;
            pointer-events: none;
        }
        .btn-quick-search {
            background: #8D5B4C;
            color: white;
            border: none;
            font-weight: 700;
            font-size: 0.85rem;
            padding: 8px 20px;
            border-radius: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 6px rgba(141, 91, 76, 0.2);
        }
        .btn-quick-search:hover {
            background: #6e4438;
            color: white;
            transform: translateY(-1px);
        }

        .btn-print-premium {
            color: #ffffff;
            padding: 12px 30px;
            font-size: 0.9rem;
            font-weight: 700;
            border-radius: 30px;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
            transition: all 0.2s ease;
            border: none;
        }

        .btn-print-premium.btn-layak { background: linear-gradient(135deg, #198754 0%, #157347 100%); }
        .btn-print-premium.btn-gagal { background: linear-gradient(135deg, #dc3545 0%, #bb2d3b 100%); }
        .btn-print-premium.btn-semua { background: linear-gradient(135deg, #475569 0%, #334155 100%); }

        .btn-print-premium:hover {
            transform: translateY(-2px);
            opacity: 0.95;
        }

        /* CETAKAN STYLES */
        @media print {
            @page {
                size: A4 landscape;
                margin: 8mm 7mm 8mm 7mm !important;
            }

            html, body {
                background: #ffffff !important;
                color: #0f172a !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                font-family: 'Plus Jakarta Sans', Arial, Helvetica, sans-serif !important;
                font-size: 6pt !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .wrapper, .main-content-container, .status-card-container, .table-responsive-custom {
                display: block !important;
                background: #ffffff !important;
                border: none !important;
                padding: 0 !important;
                margin: 0 !important;
                box-shadow: none !important;
                width: 100% !important;
                max-width: 100% !important;
                overflow: visible !important;
            }

            .mdbg-navbar, .sidebar-container, .screen-only, .btn-print-premium, th.screen-only, td.screen-only {
                display: none !important;
            }

            .print-only {
                display: block !important;
                text-align: left !important;
                margin-bottom: 10px !important;
                padding-bottom: 6px !important;
                border-bottom: 3px double #0f172a !important;
                width: 100% !important;
            }

            .print-only h4 {
                font-size: 12pt !important;
                font-weight: 800 !important;
                letter-spacing: 0.8px !important;
                color: #0f172a !important;
                margin: 0 0 2px 0 !important;
                text-transform: uppercase !important;
            }

            .print-only h5 {
                font-size: 8.5pt !important;
                font-weight: 700 !important;
                color: #475569 !important;
                margin: 0 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.3px !important;
            }

            table.table-mdbg {
                width: 100% !important;
                min-width: 100% !important;
                max-width: 100% !important;
                border: 1px solid #0f172a !important;
                border-collapse: collapse !important;
                margin: 0 !important;
                table-layout: auto !important;
            }

            table.table-mdbg thead { display: table-header-group !important; }

            table.table-mdbg th {
                background-color: #0f172a !important;
                color: #ffffff !important;
                font-weight: 800 !important;
                font-size: 6.2pt !important;
                text-align: center !important;
                text-transform: uppercase !important;
                padding: 6px 3px !important;
                border: 1px solid #0f172a !important;
                white-space: nowrap !important;
                letter-spacing: 0.4px !important;
            }

            table.table-mdbg tr { page-break-inside: avoid !important; }

            table.table-mdbg tbody tr:nth-child(even) { background-color: #f1f5f9 !important; }

            table.table-mdbg td {
                border: 1px solid #cbd5e1 !important;
                padding: 4px 4px !important;
                font-size: 5.8pt !important;
                vertical-align: middle !important;
                line-height: 1.2 !important;
                color: #0f172a !important;
                white-space: normal !important;
            }

            table.table-mdbg th:nth-child(1), table.table-mdbg td:nth-child(1) { text-align: center; font-weight: 700; width: 22px; }
            table.table-mdbg th:nth-child(2), table.table-mdbg td:nth-child(2) { font-weight: 700; color: #0f172a; }
            table.table-mdbg th:nth-child(4), table.table-mdbg td:nth-child(4) { font-family: monospace; font-weight: 600; text-align: center; }
            table.table-mdbg th:nth-child(5), table.table-mdbg td:nth-child(5) { text-align: center; font-family: monospace; font-weight: 700; }
            table.table-mdbg th:nth-child(6), table.table-mdbg td:nth-child(6) { white-space: nowrap !important; text-align: center; }
            table.table-mdbg th:nth-child(7), table.table-mdbg td:nth-child(7) { word-break: break-all !important; }
            table.table-mdbg th:nth-child(10), table.table-mdbg td:nth-child(10) { text-align: center; font-weight: 600; }
            table.table-mdbg th:nth-child(11), table.table-mdbg td:nth-child(11) { text-align: center; font-size: 5.2pt !important; }
            table.table-mdbg th:nth-child(12), table.table-mdbg td:nth-child(12) { text-align: center; }
            table.table-mdbg th:nth-child(13), table.table-mdbg td:nth-child(13) { text-align: center; }

            table.table-mdbg td .badge, 
            table.table-mdbg td .badge-status {
                border: 1px solid #94a3b8 !important;
                padding: 1px 4px !important;
                border-radius: 3px !important;
                background: #ffffff !important;
                color: #0f172a !important;
                font-weight: 700 !important;
                font-size: 5.5pt !important;
                display: inline-block !important;
                text-transform: uppercase !important;
                box-shadow: none !important;
            }

            .badge-status.status-lengkap { border-color: #16a34a !important; color: #15803d !important; background-color: #f0fdf4 !important; }
            .badge-status.status-pending { border-color: #ca8a04 !important; color: #a16207 !important; background-color: #fefce8 !important; }
            .badge-status.status-gagal { border-color: #dc2626 !important; color: #b91c1c !important; background-color: #fef2f2 !important; }

            .reason-box {
                background: #fee2e2 !important;
                border: 1px solid #fca5a5 !important;
                border-left: 2.5px solid #dc2626 !important;
                padding: 3px 5px !important;
                border-radius: 2px !important;
                color: #991b1b !important;
                font-size: 5.2pt !important;
                margin-top: 3px !important;
                max-width: 100% !important;
                line-height: 1.1 !important;
            }
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
                   aria-expanded="true" 
                   aria-controls="menuFasa1">
                    <span>
                        <i class="fa-solid fa-folder-open me-2 text-warning"></i> Fasa 1
                    </span>
                    <i class="fa-solid fa-chevron-down small chevron-icon"></i>
                </a>

                <!-- SUB-MENU FASA 1 -->
                <div class="collapse show sidebar-submenu" id="menuFasa1">
                    <a href="kejuruteraan_senarai_kontraktor.php" class="sub-link-item active">
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
                
                <?php if ($edit_data): ?>
                <div id="borangEditSeksyen" class="d-none"></div>
                <?php endif; ?>

                <div class="print-only d-none" id="tajukCetakanDinamik">
                    <h4 class="fw-bold text-uppercase m-0">Majlis Daerah Batu Gajah</h4>
                    <h5 class="fw-semibold text-muted text-uppercase small mt-1" id="subTajukCetak">Senarai Penuh Kontraktor Berdaftar (Jabatan Kejuruteraan)</h5>
                </div>

                <div class="status-card-container mb-4">
                    <h5 class="fw-bold text-dark text-start mb-4 screen-only"><i class="fa-solid fa-folder-open text-muted me-2"></i>Senarai Penuh Kontraktor Berdaftar</h5>
                    
                    <!-- KOTAK FILTER -->
                    <div class="p-3 bg-light rounded-3 border mb-3 screen-only">
                        <form method="GET" action="kejuruteraan_senarai_kontraktor.php" class="row g-3 align-items-end">
                            <input type="hidden" name="search" value="<?= htmlspecialchars($search_query); ?>">
                            <div class="col-12 col-md-4 col-lg-3">
                                <label class="form-label small fw-bold text-secondary mb-1"><i class="fa-solid fa-list-check me-1"></i> Status Kelayakan:</label>
                                <select name="status_borang" class="form-select form-select-sm shadow-sm" style="border-radius: 8px; padding: 0.45rem 0.75rem;">
                                    <option value="">-- Semua Status Kelayakan --</option>
                                    <option value="Lengkap" <?= ($filter_borang == 'Lengkap') ? 'selected' : ''; ?>>ðŸŸ¢ Layak / Lengkap</option>
                                    <option value="Pending" <?= ($filter_borang == 'Pending') ? 'selected' : ''; ?>>ðŸŸ¡ Pending</option>
                                    <option value="Gagal" <?= ($filter_borang == 'Gagal') ? 'selected' : ''; ?>>ðŸ”´ Gagal / Tidak Layak</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-4 col-lg-3">
                                <label class="form-label small fw-bold text-secondary mb-1"><i class="fa-solid fa-clock me-1"></i> Status Tempoh:</label>
                                <select name="status_tempoh" class="form-select form-select-sm shadow-sm" style="border-radius: 8px; padding: 0.45rem 0.75rem;">
                                    <option value="">-- Semua Status --</option>
                                    <option value="latest" <?= ($filter_status == 'latest') ? 'selected' : ''; ?>>ðŸŸ¢ Terbaharu / Aktif</option>
                                    <option value="almost_expired" <?= ($filter_status == 'almost_expired') ? 'selected' : ''; ?>>ðŸŸ¡ Hampir Tamat (30 Hari)</option>
                                    <option value="expired" <?= ($filter_status == 'expired') ? 'selected' : ''; ?>>ðŸ”´ Tamat Tempoh (Expired)</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-4 col-lg-3">
                                <label class="form-label small fw-bold text-secondary mb-1"><i class="fa-solid fa-id-card me-1"></i> Jenis Pendaftaran:</label>
                                <select name="jenis_pendaftaran" class="form-select form-select-sm shadow-sm" style="border-radius: 8px; padding: 0.45rem 0.75rem;">
                                    <option value="">-- Semua Jenis Pendaftaran --</option>
                                    <option value="BAHARU" <?= ($filter_jenis == 'BAHARU') ? 'selected' : ''; ?>>ðŸ“ Pendaftaran Baharu</option>
                                    <option value="PEMBAHARUAN" <?= ($filter_jenis == 'PEMBAHARUAN') ? 'selected' : ''; ?>>ðŸ”„ Pembaharuan Pendaftaran</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-auto d-flex flex-wrap gap-2">
                                <button type="submit" class="btn btn-sm btn-filter-tapis d-inline-flex align-items-center gap-1">
                                    <i class="fa-solid fa-filter fs-6"></i> Tapis
                                </button>
                                <?php if (!empty($filter_status) || !empty($filter_jenis) || !empty($filter_borang) || !empty($search_query)): ?>
                                    <a href="kejuruteraan_senarai_kontraktor.php" class="btn btn-sm btn-filter-reset d-inline-flex align-items-center gap-1">
                                        <i class="fa-solid fa-rotate-left fs-6"></i> Reset
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>

                    <!-- BAR CARIAN -->
                    <div class="quick-search-box mb-3 screen-only">
                        <form action="kejuruteraan_senarai_kontraktor.php" method="GET" class="m-0">
                            <input type="hidden" name="status_tempoh" value="<?= htmlspecialchars($filter_status); ?>">
                            <input type="hidden" name="jenis_pendaftaran" value="<?= htmlspecialchars($filter_jenis); ?>">
                            <input type="hidden" name="status_borang" value="<?= htmlspecialchars($filter_borang); ?>">
                            <div class="row g-2 align-items-center">
                                <div class="col-12 col-md-9 col-lg-10 position-relative">
                                    <i class="fa-solid fa-magnifying-glass quick-search-icon"></i>
                                    <input type="text" name="search" class="form-control quick-search-input" placeholder="Carian pantas: Masukkan Nama Syarikat atau No. Pendaftaran SSM..." value="<?= htmlspecialchars($search_query); ?>" autocomplete="off">
                                </div>
                                <div class="col-12 col-md-3 col-lg-2">
                                    <button type="submit" class="btn btn-quick-search w-100 d-flex align-items-center justify-content-center gap-2">
                                        <i class="fa-solid fa-search"></i> Cari Rekod
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="table-responsive-custom">
                        <table class="table table-mdbg align-middle">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 40px;">BIL.</th>
                                    <th class="text-center">NAMA SYARIKAT</th>
                                    <th class="text-center">JENIS PENDAFTARAN</th>
                                    <th class="text-center">KATEGORI PENDAFTARAN</th>
                                    <th class="text-center">NO. PENDAFTARAN SSM</th>
                                    <th class="text-center">GRED CIDB / KEWANGAN</th>
                                    <th class="text-center">NO. TELEFON AM</th>
                                    <th class="text-center">EMAIL SYARIKAT</th>
                                    <th class="text-center">STATUS BORANG</th>
                                    <th class="text-center">BAYARAN</th>
                                    <th class="text-center">PERAKUAN SYARIKAT</th>
                                    <th class="text-center">TIMESTAMP</th>
                                    <th class="text-center">TARIKH MULA</th>
                                    <th class="text-center">TARIKH TAMAT</th>
                                    <th class="text-center screen-only">TINDAKAN</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($senarai_kontraktor->num_rows > 0): ?>
                                    <?php $no = 1; while($row = $senarai_kontraktor->fetch_assoc()): 
                                        $status_b = $row['status_borang'] ?? 'Pending';
                                        $status_bayar = $row['status_bayaran_daftar'] ?? 'Belum Bayar';
                                        $akses_undi = $row['akses_undi'] ?? 1; // Default 1 (Dibenarkan)
                                        
                                        $row_class = '';
                                        if ($status_b == 'Lengkap') {
                                            $row_class = 'row-lengkap';
                                        } elseif ($status_b == 'Gagal' || $status_b == 'Tidak Lengkap') {
                                            $row_class = 'row-gagal';
                                        }

                                        $mula_aktif = ($status_bayar == 'Sudah Bayar') ? ($row['tarikh_mula_aktif'] ?? '-') : '-';
                                        $tamat_aktif = ($status_bayar == 'Sudah Bayar') ? ($row['tarikh_tamat_aktif'] ?? '-') : '-';
                                        
                                        $badge_tarikh_class = 'text-secondary';
                                        $badge_icon = '';
                                        
                                        if ($tamat_aktif != '-' && !empty($tamat_aktif)) {
                                            if ($tamat_aktif < $today) {
                                                $badge_tarikh_class = 'badge bg-danger text-white';
                                                $badge_icon = '<i class="fa-solid fa-circle-xmark me-1"></i>';
                                            } elseif ($tamat_aktif <= $thirty_days_later) {
                                                $badge_tarikh_class = 'badge bg-warning text-dark';
                                                $badge_icon = '<i class="fa-solid fa-triangle-exclamation me-1"></i>';
                                            } else {
                                                $badge_tarikh_class = 'badge bg-success text-white';
                                                $badge_icon = '<i class="fa-solid fa-circle-check me-1"></i>';
                                            }
                                        }
                                    ?>
                                        <tr class="<?= $row_class; ?>">
                                            <td class="text-center fw-semibold text-muted status-bilangan-kolum"><?= $no++; ?>.</td>
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="company-icon-box screen-only"><i class="fa-solid fa-briefcase"></i></div>
                                                    <div class="fw-bold text-dark text-uppercase"><?= htmlspecialchars($row['nama_syarikat'] ?? ''); ?></div>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary text-white text-uppercase" style="font-size: 0.75rem;">
                                                    <?= htmlspecialchars($row['jenis_pendaftaran'] ?? '-'); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge text-white text-uppercase" style="font-size: 0.75rem; background-color: #8D5B4C;">
                                                    <?= htmlspecialchars($row['kategori_pendaftaran'] ?? '-'); ?>
                                                </span>
                                            </td>
                                            <td class="text-center"><span class="text-secondary fw-semibold font-monospace"><?= htmlspecialchars($row['no_pendaftaran'] ?? ''); ?></span></td>
                                            <td class="text-center">
                                                <?php 
                                                    $gred_raw = trim($row['gred_cidb_kewangan'] ?? '');
                                                    $json_data = json_decode($gred_raw, true);

                                                    if (json_last_error() === JSON_ERROR_NONE && is_array($json_data)) {
                                                        echo '<table class="table table-sm table-bordered m-0 text-center align-middle d-inline-table" style="font-size:0.65rem; width: auto; max-width: 100%; background:#ffffff; border-color:#cbd5e1; margin: 0 auto !important;">';
                                                        echo '<thead style="background-color:#64748b; color:#ffffff;"><tr><th style="padding:2px 6px;">GRED</th><th style="padding:2px 6px;">KATEGORI</th><th style="padding:2px 6px;">PENGKHUSUSAN</th></tr></thead><tbody>';
                                                        foreach ($json_data as $item) {
                                                            echo '<tr>';
                                                            echo '<td class="fw-bold font-monospace" style="padding:2px 6px;">' . htmlspecialchars($item['gred'] ?? '') . '</td>';
                                                            echo '<td class="font-monospace" style="padding:2px 6px;">' . htmlspecialchars($item['kategori'] ?? '') . '</td>';
                                                            echo '<td class="font-monospace" style="padding:2px 6px;">' . htmlspecialchars($item['pengkhususan'] ?? '') . '</td>';
                                                            echo '</tr>';
                                                        }
                                                        echo '</tbody></table>';
                                                    } 
                                                    elseif (strpos($gred_raw, "\n") !== false || (strpos($gred_raw, 'GRED') !== false && strpos($gred_raw, 'KATEGORI') !== false)) {
                                                        $lines = array_filter(array_map('trim', explode("\n", str_replace("\r", "", $gred_raw))));
                                                        echo '<table class="table table-sm table-bordered m-0 text-center align-middle d-inline-table" style="font-size:0.65rem; width: auto; max-width: 100%; background:#ffffff; border-color:#cbd5e1; margin: 0 auto !important;">';
                                                        
                                                        $is_first = true;
                                                        foreach ($lines as $line) {
                                                            $cols = preg_split('/\s+/', $line);
                                                            if (count($cols) >= 3) {
                                                                if ($is_first && (strcasecmp($cols[0], 'GRED') == 0 || strcasecmp($cols[1], 'KATEGORI') == 0)) {
                                                                    echo '<thead style="background-color:#64748b; color:#ffffff;"><tr>';
                                                                    echo '<th style="padding:2px 6px;">' . htmlspecialchars($cols[0]) . '</th>';
                                                                    echo '<th style="padding:2px 6px;">' . htmlspecialchars($cols[1]) . '</th>';
                                                                    echo '<th style="padding:2px 6px;">' . htmlspecialchars(implode(' ', array_slice($cols, 2))) . '</th>';
                                                                    echo '</tr></thead><tbody>';
                                                                    $is_first = false;
                                                                } else {
                                                                    if ($is_first) {
                                                                        echo '<thead style="background-color:#64748b; color:#ffffff;"><tr><th style="padding:2px 6px;">GRED</th><th style="padding:2px 6px;">KATEGORI</th><th style="padding:2px 6px;">PENGKHUSUSAN</th></tr></thead><tbody>';
                                                                        $is_first = false;
                                                                    }
                                                                    echo '<tr>';
                                                                    echo '<td class="fw-bold font-monospace" style="padding:2px 6px;">' . htmlspecialchars($cols[0]) . '</td>';
                                                                    echo '<td class="font-monospace" style="padding:2px 6px;">' . htmlspecialchars($cols[1]) . '</td>';
                                                                    echo '<td class="font-monospace" style="padding:2px 6px;">' . htmlspecialchars(implode(' ', array_slice($cols, 2))) . '</td>';
                                                                    echo '</tr>';
                                                                }
                                                            } elseif (count($cols) == 2) {
                                                                if ($is_first) {
                                                                    echo '<thead style="background-color:#64748b; color:#ffffff;"><tr><th style="padding:2px 6px;">GRED</th><th style="padding:2px 6px;">KATEGORI</th></tr></thead><tbody>';
                                                                    $is_first = false;
                                                                }
                                                                echo '<tr><td class="font-monospace" style="padding:2px 6px;">' . htmlspecialchars($cols[0]) . '</td><td class="font-monospace" style="padding:2px 6px;">' . htmlspecialchars($cols[1]) . '</td></tr>';
                                                            } elseif (!empty($cols[0])) {
                                                                if ($is_first) {
                                                                    echo '<tbody>';
                                                                    $is_first = false;
                                                                }
                                                                echo '<tr><td colspan="3" class="font-monospace" style="padding:2px 6px;">' . htmlspecialchars($cols[0]) . '</td></tr>';
                                                            }
                                                        }
                                                        if (!$is_first) {
                                                            echo '</tbody>';
                                                        }
                                                        echo '</table>';
                                                    } 
                                                    else {
                                                        echo '<span class="badge border font-monospace" style="font-size: 0.72rem; font-weight: 700; letter-spacing: 0.5px; padding: 4px 8px; background-color: #f8fafc; color: #475569; border-color: #cbd5e1 !important; border-radius: 6px;">' . htmlspecialchars($gred_raw) . '</span>';
                                                    }
                                                ?>
                                            </td>
                                            <td class="text-center"><span><?= htmlspecialchars($row['no_telefon_syarikat'] ?? ''); ?></span></td>
                                            <td class="text-center"><span><?= htmlspecialchars($row['email_aktif'] ?? ''); ?></span></td>
                                            <td class="text-center">
                                                <?php 
                                                    if ($status_b == 'Lengkap') {
                                                        echo '<span class="badge-status status-lengkap"><i class="fa-solid fa-circle-check"></i> Lengkap</span>';
                                                    } elseif ($status_b == 'Pending') {
                                                        echo '<span class="badge-status status-pending"><i class="fa-solid fa-clock"></i> Pending</span>';
                                                    } else {
                                                        echo '<span class="badge-status status-gagal"><i class="fa-solid fa-circle-xmark"></i> Gagal</span>';
                                                        if (!empty($row['alasan_tolak'])) {
                                                            $lines = array_filter(array_map('trim', explode("\n", str_replace("\r", "", $row['alasan_tolak']))));
                                                            echo '<div class="reason-box mt-1 text-danger text-uppercase fw-semibold text-start">';
                                                            foreach ($lines as $line) {
                                                                $cleaned_line = ltrim($line, '- ');
                                                                if (!empty($cleaned_line)) {
                                                                    echo '<div class="d-flex align-items-start gap-1"><span class="me-1">-</span><span>' . htmlspecialchars($cleaned_line) . '</span></div>';
                                                                }
                                                            }
                                                            echo '</div>';
                                                        }
                                                    }
                                                ?>
                                            </td>
                                            <td class="text-center">
                                                <?php 
                                                    echo ($status_bayar == 'Sudah Bayar') ? 
                                                    '<span class="badge-status bayar-disahkan"><i class="fa-solid fa-circle-check"></i> Disahkan</span>' : 
                                                    '<span class="badge-status bayar-belum"><i class="fa-solid fa-hourglass-start"></i> Belum Bayar</span>';
                                                ?>
                                            </td>
                                            <td class="text-center"><span class="badge bg-success text-white"><?= htmlspecialchars(strtoupper($row['perakuan_setuju'] ?? '-')); ?></span></td>
                                            <td class="text-center"><span class="text-secondary small"><?= htmlspecialchars($row['created_at'] ?? '-'); ?></span></td>
                                            <td class="text-center"><span class="text-secondary fw-semibold"><?= htmlspecialchars($mula_aktif); ?></span></td>
                                            <td class="text-center">
                                                <span class="<?= $badge_tarikh_class; ?> fw-semibold">
                                                    <?= $badge_icon . htmlspecialchars($tamat_aktif); ?>
                                                </span>
                                            </td>
                                            
                                            <td class="text-center screen-only">
                                                <div class="d-flex gap-1 justify-content-center">
                                                    <a href="kejuruteraan_senarai_kontraktor.php?edit_id=<?= $row['id']; ?>#borangEditSeksyen" class="btn btn-sm btn-outline-primary" title="Kemaskini Maklumat">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </a>
                                                    <button class="btn btn-sm btn-outline-warning btn-reset-password" 
                                                            data-id="<?= $row['id']; ?>" 
                                                            data-nama="<?= htmlspecialchars($row['nama_syarikat'] ?? ''); ?>"
                                                            title="Reset Password ke Default">
                                                        <i class="fa-solid fa-key"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger btn-delete" 
                                                            data-id="<?= $row['id']; ?>" 
                                                            data-nama="<?= htmlspecialchars($row['nama_syarikat'] ?? ''); ?>"
                                                            title="Padam Kontraktor">
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="15" class="text-center py-4 text-muted">
                                            <i class="fa-solid fa-magnifying-glass fs-4 d-block mb-2 text-secondary opacity-50"></i>
                                            Tiada rekod kontraktor dijumpai mengikut carian / tapisan anda.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="text-center mt-4 screen-only d-flex justify-content-center gap-3">
                        <button onclick="cetakTapis('layak')" class="btn-print-premium btn-layak">
                            <i class="fa-solid fa-print"></i>
                            <span>Cetak Senarai Layak (Lengkap)</span>
                        </button>
                        <button onclick="cetakTapis('gagal')" class="btn-print-premium btn-gagal">
                            <i class="fa-solid fa-print"></i>
                            <span>Cetak Senarai Gagal</span>
                        </button>
                        <button onclick="cetakTapis('semua')" class="btn-print-premium btn-semua">
                            <i class="fa-solid fa-print"></i>
                            <span>Cetak Semua Senarai</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- BOOTSTRAP & SWEETALERT JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- JAVASCRIPT APP LOGIC -->
    <script>
        function tambahBarisGred() {
            const tbody = document.getElementById('bodyGredCidb');
            if (!tbody) return;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="padding: 6px;">
                    <input type="text" class="form-control form-control-sm text-uppercase font-monospace fw-semibold gred-val" style="border-radius: 6px; padding: 0.4rem 0.6rem; border-color: #cbd5e1;" placeholder="G1 / G2 / MOF">
                </td>
                <td style="padding: 6px;">
                    <input type="text" class="form-control form-control-sm text-uppercase font-monospace fw-semibold kat-val" style="border-radius: 6px; padding: 0.4rem 0.6rem; border-color: #cbd5e1;" placeholder="CE / B / ME">
                </td>
                <td style="padding: 6px;">
                    <input type="text" class="form-control form-control-sm text-uppercase font-monospace fw-semibold peng-val" style="border-radius: 6px; padding: 0.4rem 0.6rem; border-color: #cbd5e1;" placeholder="CE21 / B04">
                </td>
                <td class="text-center" style="padding: 6px;">
                    <button type="button" class="btn btn-sm btn-outline-danger border-0 rounded-2 px-2 py-1" style="background-color: #fee2e2; color: #dc2626;" onclick="padamBarisGred(this)" title="Padam Baris"><i class="fa-solid fa-trash-can"></i></button>
                </td>
            `;
            tbody.appendChild(tr);
        }

        function padamBarisGred(btn) {
            const tbody = document.getElementById('bodyGredCidb');
            if (tbody && tbody.children.length > 1) {
                btn.closest('tr').remove();
            } else {
                const tr = btn.closest('tr');
                tr.querySelectorAll('input').forEach(i => i.value = '');
            }
        }

        function cetakTapis(jenis) {
            const tjkCetak = document.getElementById('tajukCetakanDinamik');
            const subTjk = document.getElementById('subTajukCetak');
            
            if(subTjk) {
                if(jenis === 'layak') {
                    subTjk.textContent = "SENARAI KONTRAKTOR LAYAK / LENGKAP (JABATAN KEJURUTERAAN)";
                } else if(jenis === 'gagal') {
                    subTjk.textContent = "SENARAI KONTRAKTOR GAGAL / DITOLAK (JABATAN KEJURUTERAAN)";
                } else {
                    subTjk.textContent = "SENARAI PENUH KONTRAKTOR BERDAFTAR (JABATAN KEJURUTERAAN)";
                }
            }
            
            if(tjkCetak) tjkCetak.classList.remove('d-none');
            
            // Menggunakan pemilih tepat (direct child) supaya tidak mengganggu jadual Gred CIDB kecil di dalam td
            const rows = document.querySelectorAll('table.table-mdbg > tbody > tr');
            let counter = 1;
            
            rows.forEach(row => {
                let matches = false;

                // Abai jika hanya baris pesanan 'Tiada rekod'
                if (row.cells.length <= 1) {
                    return;
                }
                
                if (jenis === 'layak') {
                    // Semak sama ada ada class 'row-lengkap' ATAU badge '.status-lengkap'
                    if (row.classList.contains('row-lengkap') || row.querySelector('.status-lengkap')) {
                        matches = true;
                    }
                } else if (jenis === 'gagal') {
                    // Semak sama ada ada class 'row-gagal' ATAU badge '.status-gagal'
                    if (row.classList.contains('row-gagal') || row.querySelector('.status-gagal')) {
                        matches = true;
                    }
                } else if (jenis === 'semua') {
                    matches = true;
                }
                
                if (matches) {
                    row.style.setProperty('display', 'table-row', 'important');
                    const bilCell = row.querySelector('.status-bilangan-kolum');
                    if(bilCell) bilCell.textContent = counter + ".";
                    counter++;
                } else {
                    row.style.setProperty('display', 'none', 'important');
                }
            });
            
            window.print();
            
            setTimeout(() => {
                if(tjkCetak) tjkCetak.classList.add('d-none');
                let resetCounter = 1;
                rows.forEach(row => {
                    row.style.removeProperty('display');
                    const bilCell = row.querySelector('.status-bilangan-kolum');
                    if(bilCell) {
                        bilCell.textContent = resetCounter + ".";
                        resetCounter++;
                    }
                });
            }, 1200);
        }

        // TOGGLE SIDEBAR
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebarWrapper');
            if (sidebar) sidebar.classList.toggle('collapsed');
        });

        document.addEventListener('click', function(e) {
            // HANDLE PADAM
            const btnDelete = e.target.closest('.btn-delete');
            if (btnDelete) {
                e.preventDefault();
                const id = btnDelete.getAttribute('data-id');
                const nama = btnDelete.getAttribute('data-nama');
                
                Swal.fire({
                    title: 'Adakah anda pasti?',
                    text: `Anda akan memadam akaun bagi syarikat "${nama}" secara kekal!`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, Padamkan!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = `kejuruteraan_senarai_kontraktor.php?action=delete&id=${id}`;
                    }
                });
            }

            // HANDLE RESET PASSWORD
            const btnResetPass = e.target.closest('.btn-reset-password');
            if (btnResetPass) {
                e.preventDefault();
                const id = btnResetPass.getAttribute('data-id');
                const nama = btnResetPass.getAttribute('data-nama');
                
                Swal.fire({
                    title: 'Reset Kata Laluan?',
                    html: `Adakah anda pasti untuk reset kata laluan bagi syarikat <b>"${nama}"</b> kepada default <code>#Abc123</code>?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#f59e0b',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, Reset Password!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = `kejuruteraan_senarai_kontraktor.php?action=reset_password&id=${id}`;
                    }
                });
            }
        });

        // NOTIFIKASI RESET SUCCESS
        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'reset_success'): ?>
            Swal.fire({
                icon: 'success',
                title: 'Password Berjaya Direset!',
                text: 'Kata laluan kontraktor telah ditukar kepada #Abc123',
                confirmButtonColor: '#8D5B4C'
            });
        <?php endif; ?>

        <?php if ($edit_data): 
            $raw_gred = trim($edit_data['gred_cidb_kewangan'] ?? '');
            $parsed_gred_rows = [];
            $json_edit = json_decode($raw_gred, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($json_edit)) {
                foreach ($json_edit as $item) {
                    $parsed_gred_rows[] = [
                        'gred' => $item['gred'] ?? '',
                        'kategori' => $item['kategori'] ?? '',
                        'pengkhususan' => $item['pengkhususan'] ?? ''
                    ];
                }
            } 
            elseif (!empty($raw_gred)) {
                $lines = array_filter(array_map('trim', explode("\n", str_replace("\r", "", $raw_gred))));
                foreach ($lines as $line) {
                    if (strcasecmp($line, 'GRED KATEGORI PENGKHUSUSAN') === 0) continue;
                    $parts = preg_split('/\s+/', $line, 3);
                    $parsed_gred_rows[] = [
                        'gred' => $parts[0] ?? '',
                        'kategori' => $parts[1] ?? '',
                        'pengkhususan' => $parts[2] ?? ''
                    ];
                }
            }
            
            if (empty($parsed_gred_rows)) {
                $parsed_gred_rows[] = ['gred' => '', 'kategori' => '', 'pengkhususan' => ''];
            }
        ?>
        
        Swal.fire({
            title: '<div class="d-flex align-items-center justify-content-center gap-2 text-dark fw-bold fs-4"><i class="fa-solid fa-pen-to-square" style="color: #8D5B4C;"></i> Kemas Kini Maklumat Syarikat</div>',
            html: `
                <form id="popupEditForm" action="kejuruteraan_senarai_kontraktor.php" method="POST" class="px-2 py-1 text-start">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= $edit_data['id']; ?>">
                    
                    <div class="row g-3" style="font-size: 0.88rem; max-height: 520px; overflow-y: auto; padding-right: 6px;">
                        
                        <div class="col-12 pb-1 border-bottom d-flex align-items-center gap-2">
                            <span class="fw-bold text-uppercase text-secondary small" style="letter-spacing: 0.5px;">
                                <i class="fa-solid fa-building me-1 text-muted"></i> Profil Syarikat
                            </span>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary mb-1 small text-uppercase" style="font-size: 0.75rem;">Nama Syarikat / Perniagaan</label>
                            <input type="text" name="nama_syarikat" class="form-control text-uppercase bg-white border shadow-sm fw-semibold" style="border-radius: 8px; padding: 0.6rem 0.85rem; border-color: #cbd5e1 !important;" value="<?= htmlspecialchars($edit_data['nama_syarikat'] ?? '', ENT_QUOTES); ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary mb-1 small text-uppercase" style="font-size: 0.75rem;">No. Pendaftaran (SSM)</label>
                            <input type="text" name="no_pendaftaran" class="form-control text-uppercase font-monospace bg-white border shadow-sm fw-semibold" style="border-radius: 8px; padding: 0.6rem 0.85rem; border-color: #cbd5e1 !important;" value="<?= htmlspecialchars($edit_data['no_pendaftaran'] ?? '', ENT_QUOTES); ?>" required>
                        </div>

                        <div class="col-12 pt-2 pb-1 border-bottom d-flex align-items-center gap-2">
                            <span class="fw-bold text-uppercase text-secondary small" style="letter-spacing: 0.5px;">
                                <i class="fa-solid fa-award me-1 text-muted"></i> Gred CIDB / Kewangan
                            </span>
                        </div>

                        <div class="col-12">
                            <div class="border rounded-3 p-3 bg-light shadow-sm" style="border-color: #e2e8f0 !important;">
                                <div class="table-responsive rounded-2 border overflow-hidden mb-2" style="border-color: #e2e8f0 !important;">
                                    <table class="table table-sm align-middle m-0" style="font-size: 0.82rem; background: #ffffff;">
                                        <thead class="text-center" style="font-size: 0.7rem; background-color: #1e293b; color: #ffffff;">
                                            <tr>
                                                <th style="width: 24%; padding: 8px 10px; font-weight: 700; letter-spacing: 0.5px;">GRED</th>
                                                <th style="width: 24%; padding: 8px 10px; font-weight: 700; letter-spacing: 0.5px;">KATEGORI</th>
                                                <th style="width: 38%; padding: 8px 10px; font-weight: 700; letter-spacing: 0.5px;">PENGKHUSUSAN</th>
                                                <th style="width: 14%; padding: 8px 10px; font-weight: 700; letter-spacing: 0.5px; white-space: nowrap;">TINDAKAN</th>
                                            </tr>
                                        </thead>
                                        <tbody id="bodyGredCidb">
                                            <?php foreach ($parsed_gred_rows as $r): ?>
                                            <tr>
                                                <td style="padding: 6px;">
                                                    <input type="text" class="form-control form-control-sm text-uppercase font-monospace fw-semibold gred-val" style="border-radius: 6px; padding: 0.4rem 0.6rem; border-color: #cbd5e1;" value="<?= htmlspecialchars($r['gred'], ENT_QUOTES); ?>" placeholder="G1 / G2 / MOF">
                                                </td>
                                                <td style="padding: 6px;">
                                                    <input type="text" class="form-control form-control-sm text-uppercase font-monospace fw-semibold kat-val" style="border-radius: 6px; padding: 0.4rem 0.6rem; border-color: #cbd5e1;" value="<?= htmlspecialchars($r['kategori'], ENT_QUOTES); ?>" placeholder="CE / B / ME">
                                                </td>
                                                <td style="padding: 6px;">
                                                    <input type="text" class="form-control form-control-sm text-uppercase font-monospace fw-semibold peng-val" style="border-radius: 6px; padding: 0.4rem 0.6rem; border-color: #cbd5e1;" value="<?= htmlspecialchars($r['pengkhususan'], ENT_QUOTES); ?>" placeholder="CE21 / B04">
                                                </td>
                                                <td class="text-center" style="padding: 6px;">
                                                    <button type="button" class="btn btn-sm btn-outline-danger border-0 rounded-2 px-2 py-1" style="background-color: #fee2e2; color: #dc2626;" onclick="padamBarisGred(this)" title="Padam Baris"><i class="fa-solid fa-trash-can"></i></button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <button type="button" class="btn btn-sm btn-dark fw-bold px-3 py-1-5" style="border-radius: 8px; background-color: #334155; font-size: 0.8rem;" onclick="tambahBarisGred()"><i class="fa-solid fa-plus me-1"></i> Tambah Gred / Kategori</button>
                            </div>
                            <input type="hidden" name="gred_cidb_kewangan" id="hidden_gred_cidb_kewangan" value="<?= htmlspecialchars($edit_data['gred_cidb_kewangan'] ?? '', ENT_QUOTES); ?>">
                        </div>

                        <div class="col-12 pt-2 pb-1 border-bottom d-flex align-items-center gap-2">
                            <span class="fw-bold text-uppercase text-secondary small" style="letter-spacing: 0.5px;">
                                <i class="fa-solid fa-sliders me-1 text-muted"></i> Perhubungan & Status Permohonan
                            </span>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary mb-1 small text-uppercase" style="font-size: 0.75rem;">No. Telefon Am</label>
                            <input type="text" name="no_telefon_syarikat" class="form-control bg-white border shadow-sm fw-semibold" style="border-radius: 8px; padding: 0.6rem 0.85rem; border-color: #cbd5e1 !important;" value="<?= htmlspecialchars($edit_data['no_telefon_syarikat'] ?? '', ENT_QUOTES); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary mb-1 small text-uppercase" style="font-size: 0.75rem;">E-mel Syarikat Aktif</label>
                            <input type="email" name="email_aktif" class="form-control bg-white border shadow-sm fw-semibold" style="border-radius: 8px; padding: 0.6rem 0.85rem; border-color: #cbd5e1 !important;" value="<?= htmlspecialchars($edit_data['email_aktif'] ?? '', ENT_QUOTES); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary mb-1 small text-uppercase" style="font-size: 0.75rem;">Status Semakan Dokumen</label>
                            <select name="status_borang" id="swal_status_borang" class="form-select bg-white border shadow-sm fw-bold" style="border-radius: 8px; padding: 0.6rem 0.85rem; border-color: #cbd5e1 !important;" onchange="toggleAlasanBoxPopup()">
                                <option value="Pending" <?= ($edit_data['status_borang'] ?? '') == 'Pending' ? 'selected' : ''; ?>>â³ Pending</option>
                                <option value="Lengkap" <?= ($edit_data['status_borang'] ?? '') == 'Lengkap' ? 'selected' : ''; ?>>âœ… Lengkap</option>
                                <option value="Gagal" <?= ($edit_data['status_borang'] ?? '') == 'Gagal' ? 'selected' : ''; ?>>âŒ Gagal (Tolak)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary mb-1 small text-uppercase" style="font-size: 0.75rem;">Status Pengesahan Bayaran</label>
                            <select name="status_bayaran_daftar" class="form-select bg-white border shadow-sm fw-bold" style="border-radius: 8px; padding: 0.6rem 0.85rem; border-color: #cbd5e1 !important;">
                                <option value="Belum Bayar" <?= ($edit_data['status_bayaran_daftar'] ?? '') == 'Belum Bayar' ? 'selected' : ''; ?>>ðŸ”´ Belum Bayar</option>
                                <option value="Sudah Bayar" <?= ($edit_data['status_bayaran_daftar'] ?? '') == 'Sudah Bayar' ? 'selected' : ''; ?>>ðŸ”µ Sudah Bayar (Disahkan)</option>
                            </select>
                        </div>
                        <div class="col-12" id="swal_alasan_container" style="display: <?= ($edit_data['status_borang'] ?? '') == 'Gagal' ? 'block' : 'none'; ?>;">
                            <label class="form-label fw-bold text-danger mb-1 small text-uppercase" style="font-size: 0.75rem;"><i class="fa-solid fa-triangle-exclamation me-1"></i> Alasan Penolakan</label>
                            <textarea name="alasan_tolak" class="form-control text-danger fw-semibold shadow-sm border-danger" style="border-radius: 8px; background-color: #fef2f2; padding: 0.6rem 0.85rem;" rows="3" placeholder="Sila nyatakan alasan permohonan ditolak..."><?= htmlspecialchars($edit_data['alasan_tolak'] ?? '', ENT_QUOTES); ?></textarea>
                        </div>
                    </div>
                </form>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-floppy-disk me-1"></i> Simpan Perubahan',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#8D5B4C',
            cancelButtonColor: '#64748b',
            width: '950px',
            customClass: {
                popup: 'rounded-4 shadow-lg border-0',
                confirmButton: 'px-4 py-2 fw-bold rounded-3 shadow-sm',
                cancelButton: 'px-4 py-2 fw-bold rounded-3 shadow-sm'
            },
            allowOutsideClick: false,
            preConfirm: () => {
                const form = document.getElementById('popupEditForm');
                
                const rows = document.querySelectorAll('#bodyGredCidb tr');
                let compiled = [];
                rows.forEach(r => {
                    const gred = r.querySelector('.gred-val')?.value.trim() || '';
                    const kat = r.querySelector('.kat-val')?.value.trim() || '';
                    const peng = r.querySelector('.peng-val')?.value.trim() || '';
                    if (gred || kat || peng) {
                        compiled.push(`${gred} ${kat} ${peng}`.trim());
                    }
                });
                const hiddenInput = document.getElementById('hidden_gred_cidb_kewangan');
                if (hiddenInput) {
                    hiddenInput.value = compiled.join('\n');
                }

                if (!form.checkValidity()) {
                    form.reportValidity();
                    return false;
                }
                form.submit();
            }
        }).then((result) => {
            if (result.dismiss) {
                window.location.href = 'kejuruteraan_senarai_kontraktor.php';
            }
        });

        function toggleAlasanBoxPopup() {
            const statusEl = document.getElementById('swal_status_borang');
            const alasanBox = document.getElementById('swal_alasan_container');
            if (statusEl && alasanBox) {
                alasanBox.style.display = (statusEl.value === 'Gagal') ? 'block' : 'none';
            }
        }
        <?php endif; ?>
    </script>
</body>
</html>