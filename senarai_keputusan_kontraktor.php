<?php
session_start();

// Sekat daripada menyimpan cache halaman ini
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kejuruteraan') { 
    header("Location: index.php"); 
    exit(); 
}
include 'db.php';

// Dapatkan semua kategori
$kategori_list = [];
$res_kat = $conn->query("SELECT * FROM kategori_undi ORDER BY id ASC");
if ($res_kat) {
    while ($r = $res_kat->fetch_assoc()) {
        $kategori_list[] = $r;
    }
}

// Kategori Terpilih
$selected_kat_id = isset($_GET['kategori_id']) ? intval($_GET['kategori_id']) : ($kategori_list[0]['id'] ?? 0);

// Dapatkan nama kategori terpilih
$selected_kategori_nama = '';
foreach ($kategori_list as $kat) {
    if ($kat['id'] == $selected_kat_id) {
        $selected_kategori_nama = $kat['pilihan'];
        break;
    }
}

// Dapatkan senarai projek untuk kategori terpilih dengan keputusan
$projek_with_keputusan = [];
if ($selected_kat_id > 0) {
    // Query untuk dapatkan semua projek dalam kategori
    $res_proj = $conn->query("
        SELECT 
            s.id AS sub_id,
            s.tajuk_kerja,
            s.nilai_projek
        FROM sub_kategori_undi s
        WHERE s.kategori_id = $selected_kat_id
        ORDER BY s.id ASC
    ");
    
    if ($res_proj) {
        while ($row = $res_proj->fetch_assoc()) {
            $sub_id = $row['sub_id'];
            $projek_with_keputusan[$sub_id] = [
                'tajuk' => $row['tajuk_kerja'],
                'nilai' => $row['nilai_projek'],
                'berjaya' => null,
                'simpanan' => null,
                'berjaya_auto' => null,
                'simpanan_auto' => null,
            ];
        }
    }
    
    // Kemudian dapatkan keputusan untuk projek-projek tersebut
    if (!empty($projek_with_keputusan)) {
        $sub_ids = array_keys($projek_with_keputusan);
        $sub_ids_str = implode(',', $sub_ids);
        
        $res_kep = $conn->query("
            SELECT 
                kp.sub_kategori_id,
                kp.kontraktor_undi_id,
                kp.keputusan,
                kp.id_kontraktor_auto,
                k.nama_syarikat
            FROM keputusan_pemilihan_kontraktor kp
            LEFT JOIN kontraktor_undi k ON kp.kontraktor_undi_id = k.id
            WHERE kp.sub_kategori_id IN ($sub_ids_str)
            ORDER BY kp.sub_kategori_id ASC, kp.keputusan DESC
        ");
        
        if ($res_kep) {
            while ($rk = $res_kep->fetch_assoc()) {
                $sub_id = $rk['sub_kategori_id'];
                if ($rk['keputusan'] == 'Berjaya') {
                    $projek_with_keputusan[$sub_id]['berjaya'] = $rk['nama_syarikat'];
                    $projek_with_keputusan[$sub_id]['berjaya_auto'] = $rk['id_kontraktor_auto'];
                } elseif ($rk['keputusan'] == 'Simpanan') {
                    $projek_with_keputusan[$sub_id]['simpanan'] = $rk['nama_syarikat'];
                    $projek_with_keputusan[$sub_id]['simpanan_auto'] = $rk['id_kontraktor_auto'];
                }
            }
        }
    }
}

// Convert to indexed array untuk pagination
$projek_array = array_values($projek_with_keputusan);
$total_projek = count($projek_array);
$per_page = 5;
$total_pages = ceil($total_projek / $per_page);
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

$start_index = ($current_page - 1) * $per_page;
$page_projek = array_slice($projek_array, $start_index, $per_page);
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Senarai Keputusan Kontraktor | MDBG</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>

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
        }

        /* NAVBAR */
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

        /* LAYOUT */
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

        .sidebar-menu .nav-link-item .chevron-icon {
            transition: transform 0.2s ease;
        }

        .sidebar-menu .nav-link-item[aria-expanded="true"] .chevron-icon {
            transform: rotate(180deg);
        }

        .main-content-container {
            flex-grow: 1;
            padding: 32px 40px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background-color: var(--bg-body);
            box-sizing: border-box;
            width: calc(100% - var(--sidebar-width));
            position: relative;
            z-index: 1;
        }

        .sidebar-container.collapsed + .main-content-container {
            width: 100%;
        }

        /* PAGE HEADER */
        .page-header-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            padding: 24px 28px;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.02);
            margin-bottom: 28px;
            max-width: 1300px;
            margin-left: auto;
            margin-right: auto;
        }

        .form-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
            max-width: 1300px;
            margin: 0 auto;
            overflow: hidden;
        }

        .form-card-header {
            background: linear-gradient(135deg, #fdf8f6 0%, #f1f5f9 100%);
            padding: 20px 32px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .form-card-header .header-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--primary-mdbg);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            box-shadow: 0 4px 12px rgba(141, 91, 76, 0.25);
        }

        .form-card-body {
            padding: 32px 36px;
        }

        .section-divider {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 24px 0 18px 0;
            font-size: 0.78rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--primary-mdbg);
        }

        .section-divider::after {
            content: "";
            flex-grow: 1;
            height: 1px;
            background: #e2e8f0;
        }

        .form-label-custom {
            font-weight: 700;
            color: #334155;
            font-size: 0.85rem;
            margin-bottom: 6px;
            display: block;
        }

        .form-select-custom, .form-control-custom {
            padding: 11px 16px;
            border-radius: 10px;
            border: 1.5px solid #cbd5e1;
            font-size: 0.92rem;
            font-weight: 500;
            color: #1e293b;
            transition: all 0.2s ease;
            height: auto;
            width: 100%;
            max-width: 100%;
            background-color: #ffffff;
        }

        .form-select-custom:focus, .form-control-custom:focus {
            border-color: var(--primary-mdbg);
            box-shadow: 0 0 0 3.5px rgba(141, 91, 76, 0.12);
            outline: none;
        }

        .custom-input-group {
            border-radius: 10px;
            transition: all 0.2s ease;
            width: 100%;
            max-width: 100%;
            display: flex;
            align-items: stretch;
        }

        .custom-input-group .input-group-text {
            background-color: #f8fafc;
            border: 1.5px solid #cbd5e1;
            border-right: none;
            color: var(--primary-mdbg);
            font-size: 0.95rem;
            padding-left: 16px;
            padding-right: 14px;
            border-top-left-radius: 10px;
            border-bottom-left-radius: 10px;
            display: flex;
            align-items: center;
        }

        .custom-input-group .form-select-custom,
        .custom-input-group .form-control-custom {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }

        .custom-input-group:focus-within .input-group-text {
            border-color: var(--primary-mdbg);
            background-color: #fdf8f6;
        }

        /* TABLE - DENGAN BORDER/OUTLINE */
        .table-carousel-container {
            position: relative;
            overflow: hidden;
            border-radius: 0;
            border: none;
            background: #ffffff;
            width: 100%;
            max-width: 100%;
            -webkit-overflow-scrolling: touch;
            margin-bottom: 0;
        }

        .table-carousel-track {
            display: flex;
            width: 100%;
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            will-change: transform;
        }

        .table-carousel-slide {
            min-width: 100%;
            width: 100%;
            flex: 0 0 100%;
            padding: 0;
            display: block;
            color: #1e293b !important;
            overflow: visible;
        }

        .table-keputusan {
            width: 100%;
            min-width: 0;
            max-width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 0.75rem;
            color: #1e293b !important;
            background: #ffffff;
            border: 1.5px solid #d1d5db;
        }

        .table-keputusan thead {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%) !important;
            color: #ffffff !important;
        }

        .table-keputusan thead tr {
            background: transparent !important;
        }

        .table-keputusan thead th {
            padding: 8px 10px;
            font-weight: 600;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border: 1px solid #374151;
            text-align: left;
            color: #ffffff !important;
            opacity: 1 !important;
            visibility: visible !important;
        }

        /* Lebar column dikunci */
        .table-keputusan th:nth-child(1),
        .table-keputusan td:nth-child(1) { width: 5%; text-align: center; }
        .table-keputusan th:nth-child(2),
        .table-keputusan td:nth-child(2) { width: 43%; }
        .table-keputusan th:nth-child(3),
        .table-keputusan td:nth-child(3) { width: 12%; }
        .table-keputusan th:nth-child(4),
        .table-keputusan td:nth-child(4) { width: 20%; }
        .table-keputusan th:nth-child(5),
        .table-keputusan td:nth-child(5) { width: 20%; }

        .table-keputusan tbody tr {
            transition: background-color 0.2s ease;
        }

        .table-keputusan tbody tr:hover {
            background-color: #fdf8f6;
        }

        .table-keputusan tbody tr:last-child td {
            border-bottom: none !important;
        }

        .table-keputusan tbody {
            display: table-row-group;
            color: #1e293b !important;
            background: #ffffff !important;
        }

        .table-keputusan tbody tr {
            display: table-row;
            color: #1e293b !important;
            background: #ffffff;
        }

        .table-keputusan tbody td {
            display: table-cell;
            padding: 10px 12px;
            vertical-align: middle;
            color: #1e293b !important;
            line-height: 1.4;
            overflow-wrap: anywhere;
            word-break: break-word;
            white-space: normal !important;
            min-width: 0;
            max-width: 0;
            opacity: 1 !important;
            visibility: visible !important;
            border: 1px solid #d1d5db;
            border-top: none;
        }

        .table-keputusan tbody td .projek-text {
            color: #1e293b !important;
            font-weight: 500;
            font-size: 0.73rem;
            display: block;
            width: 100%;
            max-width: 100%;
            overflow-wrap: anywhere;
            word-break: break-word;
            white-space: normal !important;
            line-height: 1.3;
        }

        .table-keputusan tbody td .nilai-text {
            color: #059669 !important;
            font-weight: 700;
            font-size: 0.75rem;
            display: block;
            width: 100%;
            max-width: 100%;
            overflow-wrap: anywhere;
            word-break: break-word;
            white-space: normal !important;
            line-height: 1.3;
        }

        /* Kontraktor display - ID di atas, nama di bawah */
        .kontraktor-text {
            display: block;
            font-weight: 600;
            font-size: 0.73rem;
            color: #0f172a !important;
            overflow-wrap: anywhere;
            word-break: break-word;
            white-space: normal !important;
            line-height: 1.3;
        }

        .kontraktor-id-inline {
            display: block;
            font-weight: 700;
            color: #8D5B4C;
            margin-bottom: 2px;
            font-size: 0.73rem;
        }

        .badge-tiada {
            background: #f1f5f9;
            color: #94a3b8;
            padding: 1px 8px;
            border-radius: 12px;
            font-weight: 500;
            font-size: 0.55rem;
            display: inline-block;
            white-space: nowrap;
        }

        /* CAROUSEL NAVIGATION - di luar table dengan jarak */
        .carousel-nav-wrapper {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px 8px 16px;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
            border-top: 1px solid #e2e8f0;
        }

        .carousel-nav-wrapper .nav-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .carousel-nav-wrapper .nav-right {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .carousel-nav-btn {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            border: 1.5px solid #d1d5db;
            background: #ffffff;
            color: #1e293b;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            cursor: pointer;
            box-shadow: 0 1px 4px rgba(0,0,0,0.03);
        }

        .carousel-nav-btn:hover:not(.disabled) {
            background: var(--primary-mdbg);
            color: #ffffff;
            border-color: var(--primary-mdbg);
            transform: scale(1.05);
            box-shadow: 0 3px 10px rgba(141, 91, 76, 0.2);
        }

        .carousel-nav-btn.disabled {
            opacity: 0.3;
            cursor: not-allowed;
            pointer-events: none;
        }

        .carousel-nav-btn i {
            font-size: 0.7rem;
        }

        .carousel-info {
            font-size: 0.82rem;
            color: #64748b;
            font-weight: 600;
            padding: 0 4px;
        }

        .carousel-info strong {
            color: #0f172a;
        }

        .carousel-dots {
            display: flex;
            gap: 5px;
            align-items: center;
        }

        .carousel-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #cbd5e1;
            border: none;
            padding: 0;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .carousel-dot.active {
            background: var(--primary-mdbg);
            width: 18px;
            border-radius: 10px;
        }

        .carousel-dot:hover:not(.active) {
            background: #94a3b8;
        }

        /* INFO TOTAL REKOD - PROFESSIONAL */
        .total-info {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.78rem;
            color: #64748b;
            font-weight: 500;
            background: #f8fafc;
            padding: 4px 14px;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
        }

        .total-info .badge-count {
            background: var(--primary-mdbg);
            color: #ffffff;
            font-weight: 700;
            padding: 0px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            line-height: 1.8;
        }

        .total-info .badge-page {
            background: #e2e8f0;
            color: #1e293b;
            font-weight: 600;
            padding: 0px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            line-height: 1.8;
        }

        .btn-print-custom {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            color: #ffffff;
            font-weight: 700;
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-size: 0.85rem;
            letter-spacing: 0.3px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.3);
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-print-custom:hover {
            background: linear-gradient(135deg, #0f172a 0%, #020617 100%);
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.4);
        }

        /* PRINT STYLES */
        @media print {
            .sidebar-container, .mdbg-navbar, .btn-print-custom, 
            .page-header-card .d-flex .p-3, .form-card-header,
            .carousel-nav-wrapper, .section-divider:first-of-type, .row.g-3.mb-4,
            #searchContainer {
                display: none !important;
            }
            .wrapper {
                margin-top: 0 !important;
            }
            .main-content-container {
                padding: 0 !important;
                width: 100% !important;
            }
            .form-card {
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
            }
            .form-card-body {
                padding: 0 !important;
            }
            .page-header-card {
                box-shadow: none !important;
                border: none !important;
                padding: 0 0 10px 0 !important;
                margin-bottom: 10px !important;
            }
            .table-carousel-container {
                border: none !important;
                border-radius: 0 !important;
                overflow: visible !important;
                margin-bottom: 0 !important;
            }
            .table-carousel-track {
                transform: none !important;
                display: block !important;
            }
            .table-carousel-slide {
                min-width: 100% !important;
                display: block !important;
                page-break-after: always !important;
                break-after: page !important;
            }
            .table-carousel-slide:last-child {
                page-break-after: auto !important;
                break-after: auto !important;
            }
            .table-keputusan {
                min-width: 0 !important;
                width: 100% !important;
                border: 1px solid #000 !important;
                margin-bottom: 20px !important;
            }
            .table-keputusan thead {
                background: #1e293b !important;
                color: #ffffff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .table-keputusan thead th {
                border: 1px solid #000 !important;
            }
            .table-keputusan tbody td {
                border: 1px solid #000 !important;
            }
            .table-keputusan tbody tr {
                page-break-inside: avoid;
            }
        }

        @media (max-width: 768px) {
            .main-content-container { padding: 18px; }
            .form-card-body { padding: 20px; }
            .table-keputusan { font-size: 0.65rem; }
            .table-keputusan thead th, .table-keputusan tbody td {
                padding: 4px 6px;
            }
            .page-header-card { padding: 16px; }
            .carousel-nav-btn {
                width: 28px;
                height: 28px;
                font-size: 0.65rem;
            }
            .carousel-nav-wrapper {
                padding: 8px 12px;
            }
            .carousel-info {
                font-size: 0.7rem;
            }
            .total-info {
                font-size: 0.7rem;
                padding: 2px 10px;
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
                
                <!-- FASA 1 -->
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

                <!-- FASA 2 -->
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
                    <a href="kategori_undi.php" class="sub-link-item">
                        <i class="fa-solid fa-tags me-2"></i> Kategori Undi
                    </a>
                    <a href="keputusan_pemilihan_kontraktor.php" class="sub-link-item">
                        <i class="fa-solid fa-tags me-2"></i> Keputusan Kontraktor
                    </a>
                    <a href="senarai_keputusan_kontraktor.php" class="sub-link-item active">
                        <i class="fa-solid fa-tags me-2"></i> Senarai Keputusan
                    </a>
                </div>

            </div>
        </div>

        <!-- MAIN CONTENT -->
        <div class="main-content-container" id="contentWrapper">
            <div class="container-fluid p-0">
                
                <!-- HEADER -->
                <div class="page-header-card">
                    <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                        <div class="d-flex align-items-center gap-3">
                            <div class="p-3 bg-light rounded-3 text-primary-mdbg border">
                                <i class="fa-solid fa-list-check fs-4"></i>
                            </div>
                            <div>
                                <h4 class="fw-bold mb-1" style="color: #0f172a;">Senarai Keputusan Kontraktor</h4>
                                <p class="text-muted small m-0">Paparan senarai kontraktor berjaya dan simpanan mengikut kategori dan projek.</p>
                            </div>
                        </div>
                        <button onclick="window.print()" class="btn-print-custom">
                            <i class="fa-solid fa-print"></i> Cetak / Print
                        </button>
                    </div>
                </div>

                <!-- MAIN CARD -->
                <div class="form-card">

                    <div class="form-card-header">
                        <div class="header-icon">
                            <i class="fa-solid fa-filter"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0" style="color: #0f172a;">Pilihan Kategori</h6>
                            <small class="text-muted fs-7">Pilih kategori untuk melihat senarai keputusan pemilihan kontraktor.</small>
                        </div>
                    </div>

                    <div class="form-card-body">

                        <!-- PILIHAN KATEGORI -->
                        <div class="section-divider mt-0">
                            <i class="fa-solid fa-layer-group"></i> Pilihan Kategori
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="jenis_kategori" class="form-label form-label-custom">Jenis Kategori</label>
                                <div class="input-group custom-input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-layer-group"></i></span>
                                    <select class="form-select form-select-custom" id="jenis_kategori" name="kategori_id" onchange="tukarKategori(this.value)">
                                        <?php foreach ($kategori_list as $kat): ?>
                                            <option value="<?= $kat['id']; ?>" <?= ($selected_kat_id == $kat['id']) ? 'selected' : ''; ?>>
                                                <?= htmlspecialchars($kat['pilihan']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- SENARAI KEPUTUSAN -->
                        <div class="section-divider">
                            <i class="fa-solid fa-square-poll-vertical"></i> 
                            Senarai Keputusan Pemilihan - <?= htmlspecialchars($selected_kategori_nama); ?>
                        </div>

                        <?php if (empty($projek_array)): ?>
                            <div class="text-center py-5">
                                <i class="fa-solid fa-inbox fs-1 text-muted mb-3 d-block"></i>
                                <h6 class="text-muted">Tiada projek untuk kategori ini.</h6>
                                <p class="text-muted small">Sila pastikan projek telah didaftarkan di halaman <a href="kategori_undi.php" class="text-primary">Kategori Undi</a>.</p>
                            </div>
                        <?php else: ?>
                            <!-- SEARCH BAR DI ATAS JADUAL -->
                            <div class="row mb-3" id="searchContainer">
                                <div class="col-md-5 col-lg-4">
                                    <div class="input-group custom-input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                                        <input type="text" id="searchBar" class="form-control form-control-custom" placeholder="Cari ID atau Nama Syarikat...">
                                    </div>
                                </div>
                            </div>

                            <!-- CAROUSEL TABLE -->
                            <div class="table-carousel-container" style="overflow: hidden;">
                                <div class="table-carousel-track" id="carouselTrack">
                                    <?php 
                                    $total_slides = ceil($total_projek / $per_page);
                                    for ($slide = 0; $slide < $total_slides; $slide++):
                                        $start = $slide * $per_page;
                                        $slide_projek = array_slice($projek_array, $start, $per_page);
                                    ?>
                                        <div class="table-carousel-slide" data-slide="<?= $slide; ?>">
                                            <table class="table-keputusan">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Nama Projek</th>
                                                        <th>Nilai (RM)</th>
                                                        <th>Kontraktor Berjaya</th>
                                                        <th>Kontraktor Simpanan</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    $no = $start + 1;
                                                    foreach ($slide_projek as $projek): 
                                                    ?>
                                                        <tr>
                                                            <td class="text-center fw-bold text-muted"><?= $no++; ?></td>
                                                            <td>
                                                                <span class="projek-text"><?= htmlspecialchars($projek['tajuk'] ?? '-'); ?></span>
                                                            </td>
                                                            <td>
                                                                <span class="nilai-text"><?= number_format($projek['nilai'] ?? 0, 2); ?></span>
                                                            </td>
                                                            <td>
                                                                <?php if (!empty($projek['berjaya'])): ?>
                                                                    <div class="kontraktor-text">
                                                                        <span class="kontraktor-id-inline"><?= htmlspecialchars($projek['berjaya_auto'] ?? ''); ?></span>
                                                                        <span><?= htmlspecialchars($projek['berjaya']); ?></span>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <span class="badge-tiada">
                                                                        <i class="fa-regular fa-circle me-1"></i> Tiada
                                                                    </span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if (!empty($projek['simpanan'])): ?>
                                                                    <div class="kontraktor-text">
                                                                        <span class="kontraktor-id-inline"><?= htmlspecialchars($projek['simpanan_auto'] ?? ''); ?></span>
                                                                        <span><?= htmlspecialchars($projek['simpanan']); ?></span>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <span class="badge-tiada">
                                                                        <i class="fa-regular fa-circle me-1"></i> Tiada
                                                                    </span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <!-- CAROUSEL NAVIGATION - DI BAWAH TABLE DENGAN TOTAL INFO -->
                            <div class="carousel-nav-wrapper">
                                <div class="nav-left">
                                    <button class="carousel-nav-btn" id="prevBtn" <?= ($total_pages <= 1) ? 'disabled' : ''; ?>>
                                        <i class="fa-solid fa-chevron-left"></i>
                                    </button>
                                    <span class="carousel-info">
                                        <strong id="slideInfo"><?= $current_page; ?></strong> / <?= $total_pages; ?>
                                    </span>
                                    <button class="carousel-nav-btn" id="nextBtn" <?= ($total_pages <= 1) ? 'disabled' : ''; ?>>
                                        <i class="fa-solid fa-chevron-right"></i>
                                    </button>
                                </div>
                                <div class="nav-right">
                                    <!-- TOTAL REKOD & MAKLUMAT HALAMAN -->
                                    <span class="total-info">
                                        <i class="fa-regular fa-file-lines"></i>
                                        <span>Jumlah: <span class="badge-count"><?= $total_projek; ?></span></span>
                                        <span style="opacity:0.3;margin:0 2px;">|</span>
                                        <span>Halaman: <span class="badge-page"><?= $current_page; ?> / <?= $total_pages; ?></span></span>
                                    </span>
                                    
                                    <div class="carousel-dots" id="carouselDots">
                                        <?php for ($i = 0; $i < $total_pages; $i++): ?>
                                            <button class="carousel-dot <?= ($i == $current_page - 1) ? 'active' : ''; ?>" 
                                                    data-slide="<?= $i; ?>"></button>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- BOOTSTRAP JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Carousel variables
        let currentSlide = <?= $current_page - 1; ?>;
        const totalSlides = <?= $total_pages; ?>;
        const track = document.getElementById('carouselTrack');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const slideInfo = document.getElementById('slideInfo');
        const dots = document.querySelectorAll('.carousel-dot');

        function goToSlide(index) {
            if (index < 0) index = 0;
            if (index >= totalSlides) index = totalSlides - 1;
            
            currentSlide = index;
            track.style.transform = 'translateX(-' + (index * 100) + '%)';
            
            // Update info
            slideInfo.textContent = (index + 1);
            
            // Update dots
            dots.forEach((dot, i) => {
                dot.classList.toggle('active', i === index);
            });
            
            // Update buttons
            prevBtn.disabled = (index === 0);
            prevBtn.classList.toggle('disabled', index === 0);
            nextBtn.disabled = (index === totalSlides - 1);
            nextBtn.classList.toggle('disabled', index === totalSlides - 1);
        }

        // Event listeners
        prevBtn.addEventListener('click', function() {
            if (currentSlide > 0) {
                goToSlide(currentSlide - 1);
                updateURL(currentSlide);
            }
        });

        nextBtn.addEventListener('click', function() {
            if (currentSlide < totalSlides - 1) {
                goToSlide(currentSlide + 1);
                updateURL(currentSlide);
            }
        });

        dots.forEach((dot) => {
            dot.addEventListener('click', function() {
                const index = parseInt(this.dataset.slide);
                goToSlide(index);
                updateURL(index);
            });
        });

        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            if (e.key === 'ArrowLeft' && currentSlide > 0) {
                goToSlide(currentSlide - 1);
                updateURL(currentSlide);
            } else if (e.key === 'ArrowRight' && currentSlide < totalSlides - 1) {
                goToSlide(currentSlide + 1);
                updateURL(currentSlide);
            }
        });

        function updateURL(index) {
            const page = index + 1;
            const url = new URL(window.location.href);
            url.searchParams.set('page', page);
            window.history.pushState({}, '', url);
        }

        // Penukaran Kategori
        function tukarKategori(katId) {
            window.location.href = "?kategori_id=" + katId + "&page=1";
        }

        // TOGGLE SIDEBAR
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebarWrapper');
            if (sidebar) sidebar.classList.toggle('collapsed');
        });

        // AUTO COLLAPSE SIDEBAR PADA PRINT
        window.addEventListener('beforeprint', function() {
            const sidebar = document.getElementById('sidebarWrapper');
            if (sidebar && !sidebar.classList.contains('collapsed')) {
                sidebar.classList.add('collapsed');
            }
        });

        // Handle browser back/forward
        window.addEventListener('popstate', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const page = parseInt(urlParams.get('page')) || 1;
            if (page >= 1 && page <= totalSlides) {
                goToSlide(page - 1);
            }
        });

        // FUNGSI SEARCH BAR (ID / NAMA SYARIKAT)
        document.getElementById('searchBar')?.addEventListener('keyup', function() {
            const query = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('.table-keputusan tbody tr');

            rows.forEach(row => {
                const kontraktorBerjayaCol = row.children[3]?.textContent.toLowerCase() || '';
                const kontraktorSimpananCol = row.children[4]?.textContent.toLowerCase() || '';

                if (kontraktorBerjayaCol.includes(query) || kontraktorSimpananCol.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>