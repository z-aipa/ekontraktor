<?php
session_start();

// 1. Sekat daripada menyimpan cache halaman ini
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kejuruteraan') { header("Location: index.php"); exit(); }
include 'db.php';

// PROSES PADAM / DISMISS SATU NOTIFIKASI
if (isset($_GET['dismiss_id']) && isset($_GET['jenis_notif'])) {
    $dismiss_id = intval($_GET['dismiss_id']);
    $jenis_notif = $conn->real_escape_string($_GET['jenis_notif']);
    
    $conn->query("INSERT IGNORE INTO notifikasi_dismissed (kontraktor_id, jenis_notif) VALUES ('$dismiss_id', '$jenis_notif')");
    
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

// Tarikh Hari Ini
$today = date('Y-m-d');

// PROSES CLEAR ALL NOTIFIKASI
if (isset($_GET['dismiss_all']) && $_GET['dismiss_all'] == '1') {
    $conn->query("INSERT IGNORE INTO notifikasi_dismissed (kontraktor_id, jenis_notif) 
                  SELECT id, 'EXPIRED' FROM kontraktor_profil 
                  WHERE tarikh_tamat_aktif < '$today' AND tarikh_tamat_aktif IS NOT NULL AND tarikh_tamat_aktif != '' AND tarikh_tamat_aktif != '-'");
    
    $conn->query("INSERT IGNORE INTO notifikasi_dismissed (kontraktor_id, jenis_notif) 
                  SELECT id, 'BAHARU' FROM kontraktor_profil 
                  WHERE jenis_pendaftaran LIKE '%BAHARU%'");
                  
    $conn->query("INSERT IGNORE INTO notifikasi_dismissed (kontraktor_id, jenis_notif) 
                  SELECT id, 'PEMBAHARUAN' FROM kontraktor_profil 
                  WHERE jenis_pendaftaran LIKE '%PEMBAHARUAN%'");
                  
    $conn->query("INSERT IGNORE INTO notifikasi_dismissed (kontraktor_id, jenis_notif) 
                  SELECT id, 'RESIT' FROM kontraktor_profil 
                  WHERE (fail_resit IS NOT NULL AND fail_resit != '') AND (fail_sijil IS NULL OR fail_sijil = '')");

    $conn->query("INSERT IGNORE INTO notifikasi_dismissed (kontraktor_id, jenis_notif) 
                  SELECT id, 'UNDI_BAHARU' FROM kontraktor_undi");

    $conn->query("INSERT IGNORE INTO notifikasi_dismissed (kontraktor_id, jenis_notif) 
                  SELECT id, 'UNDI_RESIT' FROM kontraktor_undi 
                  WHERE fail_resit_undi IS NOT NULL AND fail_resit_undi != ''");

    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

// ----------------------------------------------------
// QUERY GRAF TREND BULANAN (DINAMIK MENGIKUT TAHUN)
// ----------------------------------------------------
$selected_year = isset($_GET['tahun_graf']) ? intval($_GET['tahun_graf']) : date('Y');

$years_list = [];
$q_years = $conn->query("SELECT DISTINCT YEAR(created_at) as tahun FROM kontraktor_profil WHERE created_at IS NOT NULL ORDER BY tahun DESC");
if ($q_years && $q_years->num_rows > 0) {
    while ($y = $q_years->fetch_assoc()) {
        if ($y['tahun']) $years_list[] = $y['tahun'];
    }
}
if (!in_array(date('Y'), $years_list)) {
    $years_list[] = date('Y');
}
sort($years_list);

$monthly_data = array_fill(1, 12, 0);
$q_graph = $conn->query("SELECT MONTH(created_at) as bulan, COUNT(*) as total FROM kontraktor_profil WHERE YEAR(created_at) = '$selected_year' GROUP BY MONTH(created_at)");
if ($q_graph) {
    while ($r = $q_graph->fetch_assoc()) {
        $monthly_data[intval($r['bulan'])] = intval($r['total']);
    }
}
$graph_json = json_encode(array_values($monthly_data));

// ----------------------------------------------------
// QUERY STATISTIK RINGKASAN (MENGIKUT TAHUN TERPILIH)
// ----------------------------------------------------
$stat_total = $conn->query("SELECT COUNT(*) as total FROM kontraktor_profil WHERE YEAR(created_at) = '$selected_year'")->fetch_assoc()['total'] ?? 0;
$stat_baharu = $conn->query("SELECT COUNT(*) as total FROM kontraktor_profil WHERE jenis_pendaftaran LIKE '%BAHARU%' AND YEAR(created_at) = '$selected_year'")->fetch_assoc()['total'] ?? 0;
$stat_pembaharuan = $conn->query("SELECT COUNT(*) as total FROM kontraktor_profil WHERE jenis_pendaftaran LIKE '%PEMBAHARUAN%' AND YEAR(created_at) = '$selected_year'")->fetch_assoc()['total'] ?? 0;
$stat_lengkap = $conn->query("SELECT COUNT(*) as total FROM kontraktor_profil WHERE status_borang = 'Lengkap' AND YEAR(created_at) = '$selected_year'")->fetch_assoc()['total'] ?? 0;
$stat_pending = $conn->query("SELECT COUNT(*) as total FROM kontraktor_profil WHERE status_borang = 'Pending' AND YEAR(created_at) = '$selected_year'")->fetch_assoc()['total'] ?? 0;
$stat_tidak_lengkap = $conn->query("SELECT COUNT(*) as total FROM kontraktor_profil WHERE status_borang = 'Tidak Lengkap' AND YEAR(created_at) = '$selected_year'")->fetch_assoc()['total'] ?? 0;
$stat_undi = $conn->query("SELECT COUNT(*) as total FROM kontraktor_undi WHERE YEAR(tarikh_hantar) = '$selected_year'")->fetch_assoc()['total'] ?? 0;

$stat_kerja = $conn->query("SELECT COUNT(*) as total FROM kontraktor_profil WHERE kategori_pendaftaran LIKE '%KERJA%' AND YEAR(created_at) = '$selected_year'")->fetch_assoc()['total'] ?? 0;
$stat_pembekalan = $conn->query("SELECT COUNT(*) as total FROM kontraktor_profil WHERE kategori_pendaftaran LIKE '%PEMBEKALAN%' AND YEAR(created_at) = '$selected_year'")->fetch_assoc()['total'] ?? 0;

// ----------------------------------------------------
// QUERY SISTEM NOTIFIKASI HEADER
// ----------------------------------------------------
$notif_expired_query = $conn->query("SELECT id, nama_syarikat, tarikh_tamat_aktif, created_at FROM kontraktor_profil WHERE tarikh_tamat_aktif < '$today' AND tarikh_tamat_aktif IS NOT NULL AND tarikh_tamat_aktif != '' AND tarikh_tamat_aktif != '-' AND id NOT IN (SELECT kontraktor_id FROM notifikasi_dismissed WHERE jenis_notif='EXPIRED') ORDER BY id DESC LIMIT 5");
$notif_baharu_query = $conn->query("SELECT id, nama_syarikat, created_at FROM kontraktor_profil WHERE jenis_pendaftaran LIKE '%BAHARU%' AND id NOT IN (SELECT kontraktor_id FROM notifikasi_dismissed WHERE jenis_notif='BAHARU') ORDER BY id DESC LIMIT 5");
$notif_pembaharuan_query = $conn->query("SELECT id, nama_syarikat, created_at FROM kontraktor_profil WHERE jenis_pendaftaran LIKE '%PEMBAHARUAN%' AND id NOT IN (SELECT kontraktor_id FROM notifikasi_dismissed WHERE jenis_notif='PEMBAHARUAN') ORDER BY id DESC LIMIT 5");
$notif_resit_query = $conn->query("SELECT id, nama_syarikat, created_at FROM kontraktor_profil WHERE (fail_resit IS NOT NULL AND fail_resit != '') AND (fail_sijil IS NULL OR fail_sijil = '') AND id NOT IN (SELECT kontraktor_id FROM notifikasi_dismissed WHERE jenis_notif='RESIT') ORDER BY id DESC LIMIT 5");

$notif_undi_baharu_query = $conn->query("SELECT id, nama_syarikat, tarikh_hantar FROM kontraktor_undi WHERE id NOT IN (SELECT kontraktor_id FROM notifikasi_dismissed WHERE jenis_notif='UNDI_BAHARU') ORDER BY id DESC LIMIT 5");
$notif_undi_resit_query = $conn->query("SELECT id, nama_syarikat, tarikh_hantar FROM kontraktor_undi WHERE (fail_resit_undi IS NOT NULL AND fail_resit_undi != '') AND id NOT IN (SELECT kontraktor_id FROM notifikasi_dismissed WHERE jenis_notif='UNDI_RESIT') ORDER BY id DESC LIMIT 5");

$all_notifications = [];

if ($notif_expired_query) {
    while ($row = $notif_expired_query->fetch_assoc()) {
        $all_notifications[] = [
            'id' => $row['id'],
            'jenis_notif' => 'EXPIRED',
            'nama_syarikat' => $row['nama_syarikat'],
            'tarikh' => $row['created_at'] ?? $row['tarikh_tamat_aktif'],
            'link' => 'kejuruteraan_senarai_kontraktor.php?status_tempoh=expired',
            'icon_box' => 'bg-danger-subtle text-danger',
            'icon' => 'fa-triangle-exclamation',
            'title' => htmlspecialchars($row['nama_syarikat']),
            'sub' => 'Tamat tempoh sah (' . date('d/m/Y', strtotime($row['tarikh_tamat_aktif'])) . ')',
            'sub_class' => 'text-danger'
        ];
    }
}

if ($notif_baharu_query) {
    while ($row = $notif_baharu_query->fetch_assoc()) {
        $all_notifications[] = [
            'id' => $row['id'],
            'jenis_notif' => 'BAHARU',
            'nama_syarikat' => $row['nama_syarikat'],
            'tarikh' => $row['created_at'],
            'link' => 'kejuruteraan_senarai_kontraktor.php?jenis_pendaftaran=BAHARU',
            'icon_box' => 'bg-primary-subtle text-primary',
            'icon' => 'fa-user-plus',
            'title' => htmlspecialchars($row['nama_syarikat']),
            'sub' => 'Pendaftaran Baharu diterima',
            'sub_class' => 'text-muted'
        ];
    }
}

if ($notif_pembaharuan_query) {
    while ($row = $notif_pembaharuan_query->fetch_assoc()) {
        $all_notifications[] = [
            'id' => $row['id'],
            'jenis_notif' => 'PEMBAHARUAN',
            'nama_syarikat' => $row['nama_syarikat'],
            'tarikh' => $row['created_at'],
            'link' => 'kejuruteraan_senarai_kontraktor.php?jenis_pendaftaran=PEMBAHARUAN',
            'icon_box' => 'bg-warning-subtle text-warning-emphasis',
            'icon' => 'fa-rotate',
            'title' => htmlspecialchars($row['nama_syarikat']),
            'sub' => 'Permohonan Pembaharuan Lesen',
            'sub_class' => 'text-muted'
        ];
    }
}

if ($notif_resit_query) {
    while ($row = $notif_resit_query->fetch_assoc()) {
        $all_notifications[] = [
            'id' => $row['id'],
            'jenis_notif' => 'RESIT',
            'nama_syarikat' => $row['nama_syarikat'],
            'tarikh' => $row['created_at'],
            'link' => 'kejuruteraan_bayaran.php',
            'icon_box' => 'bg-success-subtle text-success',
            'icon' => 'fa-file-invoice-dollar',
            'title' => htmlspecialchars($row['nama_syarikat']),
            'sub' => 'Resit dimuat naik (Sijil belum diberikan)',
            'sub_class' => 'text-success'
        ];
    }
}

if ($notif_undi_baharu_query) {
    while ($row = $notif_undi_baharu_query->fetch_assoc()) {
        $all_notifications[] = [
            'id' => $row['id'],
            'jenis_notif' => 'UNDI_BAHARU',
            'nama_syarikat' => $row['nama_syarikat'],
            'tarikh' => $row['tarikh_hantar'],
            'link' => 'kejuruteraan_semak_kelayakan_undi.php',
            'icon_box' => 'bg-info-subtle text-info',
            'icon' => 'fa-check-to-slot',
            'title' => htmlspecialchars($row['nama_syarikat']),
            'sub' => 'Pendaftaran Undi Baharu',
            'sub_class' => 'text-muted'
        ];
    }
}

if ($notif_undi_resit_query) {
    while ($row = $notif_undi_resit_query->fetch_assoc()) {
        $all_notifications[] = [
            'id' => $row['id'],
            'jenis_notif' => 'UNDI_RESIT',
            'nama_syarikat' => $row['nama_syarikat'],
            'tarikh' => $row['tarikh_hantar'],
            'link' => 'senarai_kontraktor_undi.php',
            'icon_box' => 'bg-success-subtle text-success',
            'icon' => 'fa-receipt',
            'title' => htmlspecialchars($row['nama_syarikat']),
            'sub' => 'Resit undi dimuat naik',
            'sub_class' => 'text-success'
        ];
    }
}

usort($all_notifications, function($a, $b) {
    $timeA = strtotime($a['tarikh']);
    $timeB = strtotime($b['tarikh']);
    if ($timeA == $timeB) {
        return $b['id'] <=> $a['id'];
    }
    return $timeB <=> $timeA;
});

$total_notif = count($all_notifications);

?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Analysis - Kejuruteraan MDBG</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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

        /* GAYA NOTIFIKASI HEADER */
        .notif-dropdown-wrapper {
            position: relative;
            z-index: 1060;
        }

        .btn-notif-bell {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: white;
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            cursor: pointer;
            transition: all 0.2s ease;
            outline: none;
        }
        .btn-notif-bell:hover, .btn-notif-bell:focus, .btn-notif-bell[aria-expanded="true"] {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            border-color: rgba(255, 255, 255, 0.5);
        }
        .badge-notif-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #ef4444;
            color: white;
            font-size: 0.65rem;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 50rem;
            border: 2px solid #543228;
            pointer-events: none;
        }
        .notif-dropdown-menu {
            width: 360px;
            max-height: 420px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            padding: 0;
            margin-top: 12px !important;
            z-index: 1070 !important;
            position: absolute;
            right: 0;
            top: 100%;
            display: none;
            background-color: #ffffff;
        }
        .notif-dropdown-menu.show-menu {
            display: block !important;
        }
        .notif-header {
            background-color: #f8fafc;
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 700;
            font-size: 0.85rem;
            color: #1e293b;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .notif-item-row {
            padding: 10px 14px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: background-color 0.15s ease;
        }
        .notif-item-row:hover {
            background-color: #f8fafc;
        }
        .notif-item-content {
            display: flex;
            gap: 12px;
            align-items: center;
            text-decoration: none;
            color: #334155;
            flex-grow: 1;
        }
        .notif-icon-box {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 0.85rem;
        }
        .btn-dismiss-notif {
            color: #94a3b8;
            background: none;
            border: none;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.8rem;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-dismiss-notif:hover {
            color: #ef4444;
            background-color: #fef2f2;
        }
        .btn-clear-all-notif {
            color: #ef4444;
            font-size: 0.72rem;
            font-weight: 700;
            text-decoration: none;
            background-color: #fef2f2;
            padding: 3px 8px;
            border-radius: 6px;
            border: 1px solid #fee2e2;
            transition: all 0.2s ease;
        }
        .btn-clear-all-notif:hover {
            background-color: #ef4444;
            color: #ffffff;
            border-color: #ef4444;
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
        
        .sidebar-menu .nav-link-item.active {
            background-color: var(--sidebar-active) !important;
            color: #ffffff !important;
            box-shadow: 0 4px 14px rgba(141, 91, 76, 0.35);
        }
        
        .sidebar-menu .nav-link-item i {
            font-size: 1.05rem;
            width: 28px;
            color: #cbd5e1;
            transition: transform 0.2s;
        }
        
        .sidebar-menu .nav-link-item.active i {
            color: #ffc107;
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
            position: relative;
            z-index: 1;
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
            letter-spacing: -0.2px;
        }

        .custom-select-year {
            font-size: 0.85rem;
            font-weight: 700;
            color: #1e293b;
            border-color: #cbd5e1 !important;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.04);
            border-radius: 8px;
            padding-top: 6px;
            padding-bottom: 6px;
            padding-right: 2.2rem !important;
            padding-left: 0.8rem !important;
        }
        .custom-select-year:focus {
            border-color: #8D5B4C !important;
            box-shadow: 0 0 0 0.25rem rgba(141, 91, 76, 0.25);
        }

        /* GAYA STAT CARD MODEN (TAMBAHAN) */
        .stat-card-kpi {
            background: #ffffff;
            border-radius: 16px;
            padding: 20px 22px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.03);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            height: 100%;
        }
        .stat-card-kpi:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.07);
        }
        .stat-icon-box {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            flex-shrink: 0;
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
            
            <!-- DROPDOWN NOTIFIKASI -->
            <div class="dropdown notif-dropdown-wrapper">
                <button class="btn-notif-bell" type="button" id="dropdownNotif" title="Pemberitahuan Sistem">
                    <i class="fa-regular fa-bell fs-5"></i>
                    <?php if ($total_notif > 0): ?>
                        <span class="badge-notif-count"><?= $total_notif; ?></span>
                    <?php endif; ?>
                </button>

                <div class="notif-dropdown-menu" id="notifMenu">
                    <div class="notif-header">
                        <span><i class="fa-solid fa-bell text-warning me-2"></i>Notifikasi Sistem</span>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-secondary text-white" style="font-size: 0.7rem;"><?= $total_notif; ?> Baru</span>
                            <?php if ($total_notif > 0): ?>
                                <a href="?dismiss_all=1" class="btn-clear-all-notif" title="Kosongkan Semua Notifikasi">Clear All</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($total_notif == 0): ?>
                        <div class="p-4 text-center text-muted small">
                            <i class="fa-regular fa-bell-slash fs-3 d-block mb-2 text-secondary opacity-50"></i>
                            Tiada sebarang notifikasi terbaharu.
                        </div>
                    <?php else: ?>
                        <?php foreach ($all_notifications as $notif): ?>
                            <div class="notif-item-row">
                                <a href="<?= $notif['link']; ?>" class="notif-item-content">
                                    <div class="notif-icon-box <?= $notif['icon_box']; ?>">
                                        <i class="fa-solid <?= $notif['icon']; ?>"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark small text-uppercase"><?= $notif['title']; ?></div>
                                        <div class="<?= $notif['sub_class']; ?> small" style="font-size: 0.75rem;"><?= $notif['sub']; ?></div>
                                    </div>
                                </a>
                                <a href="?dismiss_id=<?= $notif['id']; ?>&jenis_notif=<?= $notif['jenis_notif']; ?>" class="btn-dismiss-notif" title="Padam Notifikasi">
                                    <i class="fa-solid fa-xmark fs-6"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

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
                <a href="data_analysis.php" class="nav-link-item active">
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

                <!-- SUB-MENU FASA 1 -->
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
                        <i class="fa-solid fa-layer-group me-2"></i> Kategori Undi
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

                <div class="mb-4">
                    <h4 class="fw-bold text-dark m-0">Analisis Data</h4>
                    <p class="text-muted small m-0">Pengawasan trend dan analisis statistik permohonan secara berkala.</p>
                </div>

                <!-- SEKSYEN KAD STATISTIK (KPI) -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="stat-card-kpi">
                            <div>
                                <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.5px;">Jumlah Permohonan</div>
                                <div class="fs-3 fw-bold text-dark mt-1"><?= number_format($stat_total); ?></div>
                                <div class="small text-muted mt-1" style="font-size: 0.78rem;"><i class="fa-regular fa-calendar me-1"></i>Tahun <?= $selected_year; ?></div>
                            </div>
                            <div class="stat-icon-box bg-primary-subtle text-primary">
                                <i class="fa-solid fa-folder-tree"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="stat-card-kpi">
                            <div>
                                <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.5px;">Pendaftaran Baharu</div>
                                <div class="fs-3 fw-bold text-success mt-1"><?= number_format($stat_baharu); ?></div>
                                <div class="small text-muted mt-1" style="font-size: 0.78rem;"><i class="fa-solid fa-user-plus me-1 text-success"></i>Permohonan baru</div>
                            </div>
                            <div class="stat-icon-box bg-success-subtle text-success">
                                <i class="fa-solid fa-id-card"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="stat-card-kpi">
                            <div>
                                <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.5px;">Pembaharuan Lesen</div>
                                <div class="fs-3 fw-bold text-warning-emphasis mt-1"><?= number_format($stat_pembaharuan); ?></div>
                                <div class="small text-muted mt-1" style="font-size: 0.78rem;"><i class="fa-solid fa-rotate me-1 text-warning"></i>Permohonan semula</div>
                            </div>
                            <div class="stat-icon-box bg-warning-subtle text-warning-emphasis">
                                <i class="fa-solid fa-arrows-rotate"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="stat-card-kpi">
                            <div>
                                <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.5px;">Pendaftaran Undian</div>
                                <div class="fs-3 fw-bold text-info mt-1"><?= number_format($stat_undi); ?></div>
                                <div class="small text-muted mt-1" style="font-size: 0.78rem;"><i class="fa-solid fa-check-to-slot me-1 text-info"></i>Fasa 2 (Undi)</div>
                            </div>
                            <div class="stat-icon-box bg-info-subtle text-info">
                                <i class="fa-solid fa-box-archive"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SEKSYEN CARTA/GRAF TREND PERMOHONAN BULANAN -->
                <div class="status-card-container mb-4">
                    <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
                        <div class="status-header">
                            <i class="fa-solid fa-chart-line text-primary me-2"></i> Trend Permohonan Bulanan (Tahun <?= $selected_year; ?>)
                        </div>
                        
                        <!-- FILTER SELECT TAHUN -->
                        <div class="position-relative d-inline-block">
                            <select onchange="tukarTahunGraf(this.value)" class="form-select form-select-sm bg-white custom-select-year pe-5 ps-3 py-1">
                                <?php foreach($years_list as $yr): ?>
                                    <option value="<?= $yr; ?>" <?= ($yr == $selected_year) ? 'selected' : ''; ?>>
                                        ðŸ“… Tahun: <?= $yr; ?>&nbsp;&nbsp;
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </div>
                    <div style="position: relative; height: 380px; width: 100%;">
                        <canvas id="chartPermohonanBulanan"></canvas>
                    </div>
                </div>

                <!-- SEKSYEN PECAHAN STATUS & KATEGORI (TAMBAHAN BAHAGIAN BAWAH) -->
                <div class="row g-4">
                    <!-- RINGKASAN STATUS BORANG -->
                    <div class="col-12 col-md-6">
                        <div class="status-card-container h-100">
                            <div class="status-header mb-3 border-bottom pb-2">
                                <i class="fa-solid fa-list-check text-warning me-2"></i> Status Borang Permohonan
                            </div>
                            <div class="d-flex flex-column gap-3">
                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="small fw-semibold text-secondary"><i class="fa-solid fa-circle-check text-success me-1"></i> Borang Lengkap</span>
                                        <span class="fw-bold small text-dark"><?= $stat_lengkap; ?> Syarikat</span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-success" style="width: <?= $stat_total > 0 ? round(($stat_lengkap/$stat_total)*100) : 0; ?>%"></div>
                                    </div>
                                </div>

                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="small fw-semibold text-secondary"><i class="fa-solid fa-hourglass-half text-warning me-1"></i> Menunggu Semakan (Pending)</span>
                                        <span class="fw-bold small text-dark"><?= $stat_pending; ?> Syarikat</span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-warning" style="width: <?= $stat_total > 0 ? round(($stat_pending/$stat_total)*100) : 0; ?>%"></div>
                                    </div>
                                </div>

                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="small fw-semibold text-secondary"><i class="fa-solid fa-circle-xmark text-danger me-1"></i> Tidak Lengkap / Ditolak</span>
                                        <span class="fw-bold small text-dark"><?= $stat_tidak_lengkap; ?> Syarikat</span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-danger" style="width: <?= $stat_total > 0 ? round(($stat_tidak_lengkap/$stat_total)*100) : 0; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RINGKASAN KATEGORI PENDAFTARAN -->
                    <div class="col-12 col-md-6">
                        <div class="status-card-container h-100">
                            <div class="status-header mb-3 border-bottom pb-2">
                                <i class="fa-solid fa-layer-group text-info me-2"></i> Pecahan Kategori Pendaftaran
                            </div>
                            <div class="row g-3 align-items-center h-75">
                                <div class="col-6 text-center border-end">
                                    <div class="p-2">
                                        <div class="stat-icon-box bg-primary-subtle text-primary mx-auto mb-2">
                                            <i class="fa-solid fa-helmet-safety"></i>
                                        </div>
                                        <div class="fs-4 fw-bold text-dark"><?= $stat_kerja; ?></div>
                                        <div class="text-muted small fw-semibold">Kontraktor Kerja</div>
                                    </div>
                                </div>
                                <div class="col-6 text-center">
                                    <div class="p-2">
                                        <div class="stat-icon-box bg-success-subtle text-success mx-auto mb-2">
                                            <i class="fa-solid fa-truck-ramp-box"></i>
                                        </div>
                                        <div class="fs-4 fw-bold text-dark"><?= $stat_pembekalan; ?></div>
                                        <div class="text-muted small fw-semibold">Pembekalan / Perkhidmatan</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- BOOTSTRAP JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // FUNGSI TUKAR TAHUN GRAF
        function tukarTahunGraf(tahun) {
            const url = new URL(window.location.href);
            url.searchParams.set('tahun_graf', tahun);
            window.location.href = url.toString();
        }

        // TOGGLE SIDEBAR
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebarWrapper');
            if (sidebar) sidebar.classList.toggle('collapsed');
        });

        // DROPDOWN NOTIFIKASI
        const bellBtn = document.getElementById('dropdownNotif');
        const notifMenu = document.getElementById('notifMenu');

        if (bellBtn && notifMenu) {
            bellBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                notifMenu.classList.toggle('show-menu');
            });

            document.addEventListener('click', function(e) {
                if (!notifMenu.contains(e.target) && !bellBtn.contains(e.target)) {
                    notifMenu.classList.remove('show-menu');
                }
            });
        }

        // BINA CARTA TREND BULANAN (CHART.JS)
        const ctx = document.getElementById('chartPermohonanBulanan').getContext('2d');
        const dataPermohonan = <?= $graph_json; ?>;

        const gradientFill = ctx.createLinearGradient(0, 0, 0, 380);
        gradientFill.addColorStop(0, 'rgba(141, 91, 76, 0.28)');
        gradientFill.addColorStop(0.8, 'rgba(141, 91, 76, 0.02)');
        gradientFill.addColorStop(1, 'rgba(141, 91, 76, 0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mac', 'Apr', 'Mei', 'Jun', 'Jul', 'Ogo', 'Sep', 'Okt', 'Nov', 'Dis'],
                datasets: [{
                    label: 'Permohonan Masuk (<?= $selected_year; ?>)',
                    data: dataPermohonan,
                    borderColor: '#8D5B4C',
                    backgroundColor: gradientFill,
                    borderWidth: 3.5,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#8D5B4C',
                    pointBorderWidth: 2.5,
                    pointRadius: 5,
                    pointHoverRadius: 8,
                    pointHoverBackgroundColor: '#8D5B4C',
                    pointHoverBorderColor: '#ffffff',
                    pointHoverBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleFont: { size: 13, family: "'Plus Jakarta Sans', sans-serif", weight: 'bold' },
                        bodyFont: { size: 12, family: "'Plus Jakarta Sans', sans-serif" },
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: false
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: { family: "'Plus Jakarta Sans', sans-serif", size: 11, weight: '600' },
                            color: '#64748b'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(226, 232, 240, 0.6)',
                            borderDash: [4, 4]
                        },
                        ticks: {
                            precision: 0,
                            font: { family: "'Plus Jakarta Sans', sans-serif", size: 11 },
                            color: '#64748b'
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>