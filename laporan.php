<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kejuruteraan') { header("Location: index.php"); exit(); }
include 'db.php';

// PROSES DISMISS NOTIFIKASI
if (isset($_GET['dismiss_id']) && isset($_GET['jenis_notif'])) {
    $dismiss_id = intval($_GET['dismiss_id']);
    $jenis_notif = $conn->real_escape_string($_GET['jenis_notif']);
    $conn->query("INSERT IGNORE INTO notifikasi_dismissed (kontraktor_id, jenis_notif) VALUES ('$dismiss_id', '$jenis_notif')");
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

$today = date('Y-m-d');

// PROSES CLEAR ALL NOTIFIKASI
if (isset($_GET['dismiss_all']) && $_GET['dismiss_all'] == '1') {
    $conn->query("INSERT IGNORE INTO notifikasi_dismissed (kontraktor_id, jenis_notif) SELECT id, 'EXPIRED' FROM kontraktor_profil WHERE tarikh_tamat_aktif < '$today' AND tarikh_tamat_aktif IS NOT NULL AND tarikh_tamat_aktif != '' AND tarikh_tamat_aktif != '-'");
    $conn->query("INSERT IGNORE INTO notifikasi_dismissed (kontraktor_id, jenis_notif) SELECT id, 'BAHARU' FROM kontraktor_profil WHERE jenis_pendaftaran LIKE '%BAHARU%'");
    $conn->query("INSERT IGNORE INTO notifikasi_dismissed (kontraktor_id, jenis_notif) SELECT id, 'PEMBAHARUAN' FROM kontraktor_profil WHERE jenis_pendaftaran LIKE '%PEMBAHARUAN%'");
    $conn->query("INSERT IGNORE INTO notifikasi_dismissed (kontraktor_id, jenis_notif) SELECT id, 'RESIT' FROM kontraktor_profil WHERE (fail_resit IS NOT NULL AND fail_resit != '') AND (fail_sijil IS NULL OR fail_sijil = '')");
    $conn->query("INSERT IGNORE INTO notifikasi_dismissed (kontraktor_id, jenis_notif) SELECT id, 'UNDI_BAHARU' FROM kontraktor_undi");
    $conn->query("INSERT IGNORE INTO notifikasi_dismissed (kontraktor_id, jenis_notif) SELECT id, 'UNDI_RESIT' FROM kontraktor_undi WHERE fail_resit_undi IS NOT NULL AND fail_resit_undi != ''");
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

// QUERY NOTIFIKASI HEADER
$notif_expired_query = $conn->query("SELECT id, nama_syarikat, tarikh_tamat_aktif, created_at FROM kontraktor_profil WHERE tarikh_tamat_aktif < '$today' AND tarikh_tamat_aktif IS NOT NULL AND tarikh_tamat_aktif != '' AND tarikh_tamat_aktif != '-' AND id NOT IN (SELECT kontraktor_id FROM notifikasi_dismissed WHERE jenis_notif='EXPIRED') ORDER BY id DESC LIMIT 5");
$notif_baharu_query = $conn->query("SELECT id, nama_syarikat, created_at FROM kontraktor_profil WHERE jenis_pendaftaran LIKE '%BAHARU%' AND id NOT IN (SELECT kontraktor_id FROM notifikasi_dismissed WHERE jenis_notif='BAHARU') ORDER BY id DESC LIMIT 5");
$notif_pembaharuan_query = $conn->query("SELECT id, nama_syarikat, created_at FROM kontraktor_profil WHERE jenis_pendaftaran LIKE '%PEMBAHARUAN%' AND id NOT IN (SELECT kontraktor_id FROM notifikasi_dismissed WHERE jenis_notif='PEMBAHARUAN') ORDER BY id DESC LIMIT 5");
$notif_resit_query = $conn->query("SELECT id, nama_syarikat, created_at FROM kontraktor_profil WHERE (fail_resit IS NOT NULL AND fail_resit != '') AND (fail_sijil IS NULL OR fail_sijil = '') AND id NOT IN (SELECT kontraktor_id FROM notifikasi_dismissed WHERE jenis_notif='RESIT') ORDER BY id DESC LIMIT 5");
$notif_undi_baharu_query = $conn->query("SELECT id, nama_syarikat, tarikh_hantar FROM kontraktor_undi WHERE id NOT IN (SELECT kontraktor_id FROM notifikasi_dismissed WHERE jenis_notif='UNDI_BAHARU') ORDER BY id DESC LIMIT 5");
$notif_undi_resit_query = $conn->query("SELECT id, nama_syarikat, tarikh_hantar FROM kontraktor_undi WHERE (fail_resit_undi IS NOT NULL AND fail_resit_undi != '') AND id NOT IN (SELECT kontraktor_id FROM notifikasi_dismissed WHERE jenis_notif='UNDI_RESIT') ORDER BY id DESC LIMIT 5");

$all_notifications = [];
if ($notif_expired_query) {
    while ($row = $notif_expired_query->fetch_assoc()) {
        $all_notifications[] = ['id' => $row['id'], 'jenis_notif' => 'EXPIRED', 'nama_syarikat' => $row['nama_syarikat'], 'tarikh' => $row['created_at'] ?? $row['tarikh_tamat_aktif'], 'link' => 'kejuruteraan_senarai_kontraktor.php?status_tempoh=expired', 'icon_box' => 'bg-danger-subtle text-danger', 'icon' => 'fa-triangle-exclamation', 'title' => htmlspecialchars($row['nama_syarikat']), 'sub' => 'Tamat tempoh sah (' . date('d/m/Y', strtotime($row['tarikh_tamat_aktif'])) . ')', 'sub_class' => 'text-danger'];
    }
}
if ($notif_baharu_query) {
    while ($row = $notif_baharu_query->fetch_assoc()) {
        $all_notifications[] = ['id' => $row['id'], 'jenis_notif' => 'BAHARU', 'nama_syarikat' => $row['nama_syarikat'], 'tarikh' => $row['created_at'], 'link' => 'kejuruteraan_senarai_kontraktor.php?jenis_pendaftaran=BAHARU', 'icon_box' => 'bg-primary-subtle text-primary', 'icon' => 'fa-user-plus', 'title' => htmlspecialchars($row['nama_syarikat']), 'sub' => 'Pendaftaran Baharu diterima', 'sub_class' => 'text-muted'];
    }
}
if ($notif_pembaharuan_query) {
    while ($row = $notif_pembaharuan_query->fetch_assoc()) {
        $all_notifications[] = ['id' => $row['id'], 'jenis_notif' => 'PEMBAHARUAN', 'nama_syarikat' => $row['nama_syarikat'], 'tarikh' => $row['created_at'], 'link' => 'kejuruteraan_senarai_kontraktor.php?jenis_pendaftaran=PEMBAHARUAN', 'icon_box' => 'bg-warning-subtle text-warning-emphasis', 'icon' => 'fa-rotate', 'title' => htmlspecialchars($row['nama_syarikat']), 'sub' => 'Permohonan Pembaharuan Lesen', 'sub_class' => 'text-muted'];
    }
}
if ($notif_resit_query) {
    while ($row = $notif_resit_query->fetch_assoc()) {
        $all_notifications[] = ['id' => $row['id'], 'jenis_notif' => 'RESIT', 'nama_syarikat' => $row['nama_syarikat'], 'tarikh' => $row['created_at'], 'link' => 'kejuruteraan_bayaran.php', 'icon_box' => 'bg-success-subtle text-success', 'icon' => 'fa-file-invoice-dollar', 'title' => htmlspecialchars($row['nama_syarikat']), 'sub' => 'Resit dimuat naik (Sijil belum diberikan)', 'sub_class' => 'text-success'];
    }
}
if ($notif_undi_baharu_query) {
    while ($row = $notif_undi_baharu_query->fetch_assoc()) {
        $all_notifications[] = ['id' => $row['id'], 'jenis_notif' => 'UNDI_BAHARU', 'nama_syarikat' => $row['nama_syarikat'], 'tarikh' => $row['tarikh_hantar'], 'link' => 'kejuruteraan_semak_kelayakan_undi.php', 'icon_box' => 'bg-info-subtle text-info', 'icon' => 'fa-check-to-slot', 'title' => htmlspecialchars($row['nama_syarikat']), 'sub' => 'Pendaftaran Undi Baharu', 'sub_class' => 'text-muted'];
    }
}
if ($notif_undi_resit_query) {
    while ($row = $notif_undi_resit_query->fetch_assoc()) {
        $all_notifications[] = ['id' => $row['id'], 'jenis_notif' => 'UNDI_RESIT', 'nama_syarikat' => $row['nama_syarikat'], 'tarikh' => $row['tarikh_hantar'], 'link' => 'senarai_kontraktor_undi.php', 'icon_box' => 'bg-success-subtle text-success', 'icon' => 'fa-receipt', 'title' => htmlspecialchars($row['nama_syarikat']), 'sub' => 'Resit undi dimuat naik', 'sub_class' => 'text-success'];
    }
}

usort($all_notifications, function($a, $b) {
    $timeA = strtotime($a['tarikh']);
    $timeB = strtotime($b['tarikh']);
    return ($timeA == $timeB) ? ($b['id'] <=> $a['id']) : ($timeB <=> $timeA);
});
$total_notif = count($all_notifications);

// ----------------------------------------------------
// ITEM 6: PERMOHONAN MENGIKUT TEMPOH (PENAPIS TARIKH & SYARAT)
// ----------------------------------------------------
$where_clauses = ["1=1"];
$tarikh_mula = $_GET['tarikh_mula'] ?? '';
$tarikh_tamat = $_GET['tarikh_tamat'] ?? '';
$gred = $_GET['gred'] ?? '';
$status_tempoh = $_GET['status_tempoh'] ?? '';

if (!empty($tarikh_mula)) {
    $where_clauses[] = "DATE(created_at) >= '" . $conn->real_escape_string($tarikh_mula) . "'";
}
if (!empty($tarikh_tamat)) {
    $where_clauses[] = "DATE(created_at) <= '" . $conn->real_escape_string($tarikh_tamat) . "'";
}
if (!empty($gred)) {
    $where_clauses[] = "gred_cidb_kewangan LIKE '%" . $conn->real_escape_string($gred) . "%'";
}
if ($status_tempoh === 'aktif') {
    $where_clauses[] = "tarikh_tamat_aktif >= '$today'";
} elseif ($status_tempoh === 'expired') {
    $where_clauses[] = "(tarikh_tamat_aktif < '$today' OR tarikh_tamat_aktif IS NULL OR tarikh_tamat_aktif = '')";
}

$where_sql = implode(' AND ', $where_clauses);

// ----------------------------------------------------
// QUERY STATISTIK UNTUK LAPORAN (ITEM 1 - 5)
// ----------------------------------------------------

// ITEM 1: Jumlah Kontraktor Berdaftar
$q_total = $conn->query("SELECT COUNT(*) as total FROM kontraktor_profil WHERE $where_sql");
$total_kontraktor = ($q_total) ? $q_total->fetch_assoc()['total'] : 0;

// ITEM 3: Kontraktor Aktif dan Tidak Aktif
$q_aktif = $conn->query("SELECT COUNT(*) as total FROM kontraktor_profil WHERE $where_sql AND tarikh_tamat_aktif >= '$today'");
$total_aktif = ($q_aktif) ? $q_aktif->fetch_assoc()['total'] : 0;
$total_tidak_aktif = $total_kontraktor - $total_aktif;

// ITEM 4: Status Permohonan (Baharu vs Pembaharuan)
$q_baharu = $conn->query("SELECT COUNT(*) as total FROM kontraktor_profil WHERE $where_sql AND jenis_pendaftaran LIKE '%BAHARU%'");
$total_baharu = ($q_baharu) ? $q_baharu->fetch_assoc()['total'] : 0;

$q_pembaharuan = $conn->query("SELECT COUNT(*) as total FROM kontraktor_profil WHERE $where_sql AND jenis_pendaftaran LIKE '%PEMBAHARUAN%'");
$total_pembaharuan = ($q_pembaharuan) ? $q_pembaharuan->fetch_assoc()['total'] : 0;

// ITEM 5: Dokumen yang Telah dan Akan Tamat Tempoh
$q_expired = $conn->query("SELECT COUNT(*) as total FROM kontraktor_profil WHERE $where_sql AND tarikh_tamat_aktif < '$today' AND tarikh_tamat_aktif IS NOT NULL AND tarikh_tamat_aktif != '' AND tarikh_tamat_aktif != '-'");
$total_expired = ($q_expired) ? $q_expired->fetch_assoc()['total'] : 0;

$q_expiring_soon = $conn->query("SELECT COUNT(*) as total FROM kontraktor_profil WHERE $where_sql AND tarikh_tamat_aktif BETWEEN '$today' AND DATE_ADD('$today', INTERVAL 30 DAY)");
$total_expiring_soon = ($q_expiring_soon) ? $q_expiring_soon->fetch_assoc()['total'] : 0;

// ITEM 2: Kontraktor Mengikut Kategori / Gred
$q_kategori = $conn->query("SELECT gred_cidb_kewangan, COUNT(*) as total FROM kontraktor_profil WHERE $where_sql AND gred_cidb_kewangan IS NOT NULL AND gred_cidb_kewangan != '' GROUP BY gred_cidb_kewangan ORDER BY total DESC");

// Senarai Rekod Jadual Utama
$q_list = $conn->query("SELECT * FROM kontraktor_profil WHERE $where_sql ORDER BY id DESC LIMIT 100");
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penuh Kontraktor - Kejuruteraan MDBG</title>
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
            font-size: 0.88rem;
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

        /* ============================================ */
        /* DESIGN PROFESIONAL - KAD & JADUAL */
        /* ============================================ */

        .page-header-wrapper {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            padding: 20px 28px;
            margin-bottom: 24px;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }

        .page-header-wrapper .header-left h4 {
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 2px;
            letter-spacing: -0.3px;
        }

        .page-header-wrapper .header-left p {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 0;
        }

        .btn-print-laporan {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            color: #ffffff;
            border: none;
            font-weight: 700;
            padding: 10px 22px;
            border-radius: 10px;
            font-size: 0.85rem;
            transition: all 0.25s ease;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.25);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-print-laporan:hover {
            background: linear-gradient(135deg, #0f172a 0%, #020617 100%);
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.35);
        }

        /* FILTER CARD */
        .filter-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            padding: 24px 28px;
            margin-bottom: 28px;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
        }

        .filter-card .filter-title {
            font-weight: 700;
            color: #0f172a;
            font-size: 0.92rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-card .filter-title i {
            color: var(--primary-mdbg);
        }

        .filter-card .form-label {
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 4px;
        }

        .filter-card .form-control,
        .filter-card .form-select {
            border-radius: 10px;
            border: 1.5px solid #e2e8f0;
            font-size: 0.88rem;
            padding: 8px 14px;
            transition: all 0.2s ease;
            background-color: #fafbfc;
        }

        .filter-card .form-control:focus,
        .filter-card .form-select:focus {
            border-color: var(--primary-mdbg);
            box-shadow: 0 0 0 3px rgba(141, 91, 76, 0.12);
            background-color: #ffffff;
        }

        .btn-filter-submit {
            background: var(--primary-mdbg);
            color: #ffffff;
            border: none;
            font-weight: 700;
            padding: 9px 20px;
            border-radius: 10px;
            font-size: 0.85rem;
            transition: all 0.2s ease;
            width: 100%;
        }

        .btn-filter-submit:hover {
            background: var(--primary-dark);
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(141, 91, 76, 0.3);
        }

        .btn-filter-reset {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            font-weight: 600;
            padding: 9px 20px;
            border-radius: 10px;
            font-size: 0.85rem;
            transition: all 0.2s ease;
            width: 100%;
            text-decoration: none;
            text-align: center;
        }

        .btn-filter-reset:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        /* STATISTIK KARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            padding: 20px 22px;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
            transition: all 0.25s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(15, 23, 42, 0.06);
        }

        .stat-card .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            margin-bottom: 12px;
        }

        .stat-card .stat-icon.blue { background: #eff6ff; color: #2563eb; }
        .stat-card .stat-icon.green { background: #ecfdf5; color: #059669; }
        .stat-card .stat-icon.purple { background: #f5f3ff; color: #7c3aed; }
        .stat-card .stat-icon.red { background: #fef2f2; color: #dc2626; }

        .stat-card .stat-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--text-muted);
            margin-bottom: 4px;
        }

        .stat-card .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.5px;
            line-height: 1.1;
        }

        .stat-card .stat-sub {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .stat-card .stat-badges {
            display: flex;
            gap: 8px;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        .stat-card .stat-badges .badge-custom {
            font-size: 0.7rem;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
        }

        .badge-custom.badge-success { background: #dcfce7; color: #16a34a; border: 1px solid #86efac; }
        .badge-custom.badge-danger { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; }
        .badge-custom.badge-primary { background: #dbeafe; color: #2563eb; border: 1px solid #93c5fd; }
        .badge-custom.badge-warning { background: #fef3c7; color: #d97706; border: 1px solid #fcd34d; }

        /* TABLE CARD */
        .table-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            padding: 24px 28px;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
        }

        .table-card .table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border-color);
        }

        .table-card .table-header h5 {
            font-weight: 700;
            color: #0f172a;
            font-size: 1rem;
            margin-bottom: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-card .table-header .badge-count {
            background: var(--primary-mdbg);
            color: #ffffff;
            font-weight: 700;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
        }

        .table-custom {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }

        .table-custom thead th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 14px;
            border-bottom: 2px solid var(--border-color);
        }

        .table-custom tbody td {
            padding: 10px 14px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            color: #1e293b;
        }

        .table-custom tbody tr {
            transition: background-color 0.15s ease;
        }

        .table-custom tbody tr:hover {
            background-color: #fdf8f6;
        }

        .table-custom tbody tr:last-child td {
            border-bottom: none;
        }

        .badge-status-aktif {
            background: #dcfce7;
            color: #16a34a;
            border: 1px solid #86efac;
            font-weight: 700;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-status-expired {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fca5a5;
            font-weight: 700;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-jenis {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            font-weight: 600;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
        }

        .empty-state {
            text-align: center;
            padding: 40px 0;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 2.5rem;
            color: #cbd5e1;
            margin-bottom: 12px;
            display: block;
        }

        .empty-state p {
            font-size: 0.9rem;
            margin-bottom: 0;
        }

        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .filter-card .row > div {
                margin-bottom: 12px;
            }
        }

        /* PRINT STYLES */
        @media print {
            .no-print, .mdbg-navbar, .sidebar-container, .btn-print-laporan {
                display: none !important;
            }
            .wrapper { margin-top: 0 !important; }
            .main-content-container {
                width: 100% !important;
                padding: 0 !important;
                background: #fff !important;
            }
            .page-header-wrapper,
            .filter-card,
            .stat-card,
            .table-card {
                border: 1px solid #ddd !important;
                box-shadow: none !important;
                page-break-inside: avoid;
            }
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 12px;
            }
            .stat-card {
                padding: 12px 16px;
            }
            .stat-card .stat-number {
                font-size: 1.6rem;
            }
            .table-custom {
                font-size: 0.7rem;
            }
            .table-custom thead th {
                background: #e9ecef !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .badge-status-aktif,
            .badge-status-expired,
            .badge-jenis {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
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
                <a href="laporan.php" class="nav-link-item active">
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
                </div>
            </div>
        </div>

        <div class="main-content-container">
            <div class="container-fluid p-0">

                <!-- HEADER & BUTANG CETAK -->
                <div class="page-header-wrapper">
                    <div class="header-left">
                        <h4><i class="fa-solid fa-file-contract me-2" style="color: var(--primary-mdbg);"></i> Laporan Analisis Kontraktor</h4>
                        <p>Ringkasan statistik pendaftaran, status lesen, dan dokumen kontraktor.</p>
                    </div>
                    <button onclick="window.print()" class="btn-print-laporan no-print">
                        <i class="fa-solid fa-print me-1"></i> Cetak Laporan
                    </button>
                </div>

                <!-- ITEM 6: PENAPIS PERMOHONAN MENGIKUT TEMPOH -->
                <div class="filter-card no-print">
                    <div class="filter-title">
                        <i class="fa-solid fa-sliders"></i> Penapis Permohonan Mengikut Tempoh & Status
                    </div>
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Tarikh Mula</label>
                            <input type="date" name="tarikh_mula" class="form-control" value="<?= htmlspecialchars($tarikh_mula); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tarikh Tamat</label>
                            <input type="date" name="tarikh_tamat" class="form-control" value="<?= htmlspecialchars($tarikh_tamat); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Gred CIDB</label>
                            <select name="gred" class="form-select">
                                <option value="">-- Semua Gred --</option>
                                <?php foreach(['G1','G2','G3','G4','G5','G6','G7'] as $g): ?>
                                    <option value="<?= $g; ?>" <?= ($gred == $g) ? 'selected' : ''; ?>><?= $g; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status Tempoh</label>
                            <select name="status_tempoh" class="form-select">
                                <option value="">-- Semua --</option>
                                <option value="aktif" <?= ($status_tempoh === 'aktif') ? 'selected' : ''; ?>>Aktif</option>
                                <option value="expired" <?= ($status_tempoh === 'expired') ? 'selected' : ''; ?>>Tamat Tempoh</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn-filter-submit">
                                <i class="fa-solid fa-magnifying-glass me-1"></i> Tapis
                            </button>
                            <a href="laporan.php" class="btn-filter-reset">Reset</a>
                        </div>
                    </form>
                </div>

                <!-- RINGKASAN KAD STATISTIK (ITEM 1, 3, 4, 5) -->
                <div class="stats-grid">
                    <!-- ITEM 1: JUMLAH KONTRAKTOR -->
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fa-solid fa-users"></i></div>
                        <div class="stat-label">1. Jumlah Berdaftar</div>
                        <div class="stat-number"><?= number_format($total_kontraktor); ?></div>
                        <div class="stat-sub">Kontraktor dalam rekod</div>
                    </div>

                    <!-- ITEM 3: STATUS AKTIF / TAMAT -->
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fa-solid fa-circle-check"></i></div>
                        <div class="stat-label">3. Status Aktif / Tamat</div>
                        <div class="stat-number" style="font-size:1.4rem;"><?= $total_aktif; ?> / <?= $total_tidak_aktif; ?></div>
                        <div class="stat-badges">
                            <span class="badge-custom badge-success"><i class="fa-solid fa-circle-check me-1"></i> Aktif: <?= $total_aktif; ?></span>
                            <span class="badge-custom badge-danger"><i class="fa-solid fa-circle-xmark me-1"></i> Tamat: <?= $total_tidak_aktif; ?></span>
                        </div>
                    </div>

                    <!-- ITEM 4: STATUS PERMOHONAN -->
                    <div class="stat-card">
                        <div class="stat-icon purple"><i class="fa-solid fa-file-pen"></i></div>
                        <div class="stat-label">4. Status Permohonan</div>
                        <div class="stat-number" style="font-size:1.4rem;"><?= $total_baharu; ?> / <?= $total_pembaharuan; ?></div>
                        <div class="stat-badges">
                            <span class="badge-custom badge-primary"><i class="fa-solid fa-user-plus me-1"></i> Baharu: <?= $total_baharu; ?></span>
                            <span class="badge-custom badge-warning"><i class="fa-solid fa-rotate me-1"></i> Pembaharuan: <?= $total_pembaharuan; ?></span>
                        </div>
                    </div>

                    <!-- ITEM 5: DOKUMEN TAMAT & AKAN TAMAT TEMPOH -->
                    <div class="stat-card">
                        <div class="stat-icon red"><i class="fa-solid fa-file-circle-exclamation"></i></div>
                        <div class="stat-label">5. Dokumen & Sijil</div>
                        <div class="stat-number" style="font-size:1.4rem;"><?= $total_expired; ?> / <?= $total_expiring_soon; ?></div>
                        <div class="stat-badges">
                            <span class="badge-custom badge-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i> Expired: <?= $total_expired; ?></span>
                            <span class="badge-custom badge-warning"><i class="fa-solid fa-clock me-1"></i> < 30 Hari: <?= $total_expiring_soon; ?></span>
                        </div>
                    </div>
                </div>

                <!-- JADUAL PERINCIAN REKOD KONTRAKTOR -->
                <div class="table-card">
                    <div class="table-header">
                        <h5>
                            <i class="fa-solid fa-list-ul" style="color: var(--primary-mdbg);"></i>
                            Senarai Perincian Kontraktor
                        </h5>
                        <span class="badge-count"><?= ($q_list) ? $q_list->num_rows : 0; ?> Rekod</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">Bil</th>
                                    <th>Nama Syarikat</th>
                                    <th>Jenis Permohonan</th>
                                    <th class="text-center">Gred CIDB</th>
                                    <th class="text-center">Tarikh Daftar</th>
                                    <th class="text-center">Status Tempoh</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($q_list && $q_list->num_rows > 0): ?>
                                    <?php $bil = 1; while ($row = $q_list->fetch_assoc()): ?>
                                        <?php $is_aktif = ($row['tarikh_tamat_aktif'] && $row['tarikh_tamat_aktif'] >= $today); ?>
                                        <tr>
                                            <td class="fw-bold text-muted"><?= $bil++; ?></td>
                                            <td class="fw-bold text-dark"><?= htmlspecialchars($row['nama_syarikat'] ?? '-'); ?></td>
                                            <td>
                                                <span class="badge-jenis">
                                                    <?= htmlspecialchars($row['jenis_pendaftaran'] ?? 'BAHARU'); ?>
                                                </span>
                                            </td>
                                            <td class="text-center align-middle">
                                                <?php 
                                                $gred_raw = trim($row['gred_cidb_kewangan'] ?? '');
                                                if (strpos($gred_raw, "\n") !== false || (strpos($gred_raw, 'GRED') !== false && strpos($gred_raw, 'KATEGORI') !== false)) {
                                                    $lines = array_filter(array_map('trim', explode("\n", str_replace("\r", "", $gred_raw))));
                                                    echo '<div class="d-flex justify-content-center">';
                                                    echo '<table class="table table-sm table-bordered m-0 text-center align-middle" style="font-size:0.6rem; width: auto; max-width: 100%; background:#ffffff; border-color:#cbd5e1;">';
                                                    $is_first = true;
                                                    foreach ($lines as $line) {
                                                        $cols = preg_split('/\s+/', $line);
                                                        if (count($cols) >= 3) {
                                                            if ($is_first && (strcasecmp($cols[0], 'GRED') == 0 || strcasecmp($cols[1], 'KATEGORI') == 0)) {
                                                                echo '<thead style="background-color:#64748b; color:#ffffff;"><tr><th>' . htmlspecialchars($cols[0]) . '</th><th>' . htmlspecialchars($cols[1]) . '</th><th>' . htmlspecialchars(implode(' ', array_slice($cols, 2))) . '</th></tr></thead><tbody>';
                                                                $is_first = false;
                                                            } else {
                                                                if ($is_first) {
                                                                    echo '<thead style="background-color:#64748b; color:#ffffff;"><tr><th>GRED</th><th>KATEGORI</th><th>PENGKHUSUSAN</th></tr></thead><tbody>';
                                                                    $is_first = false;
                                                                }
                                                                echo '<tr><td>' . htmlspecialchars($cols[0]) . '</td><td>' . htmlspecialchars($cols[1]) . '</td><td>' . htmlspecialchars(implode(' ', array_slice($cols, 2))) . '</td></tr>';
                                                            }
                                                        }
                                                    }
                                                    if (!$is_first) echo '</tbody>';
                                                    echo '</table></div>';
                                                } else {
                                                    echo '<span class="badge border font-monospace" style="font-size: 0.7rem; padding: 4px 12px; background-color: #f8fafc; color: #475569;">' . htmlspecialchars($gred_raw) . '</span>';
                                                }
                                                ?>
                                            </td>
                                            <td class="text-center"><?= (!empty($row['created_at'])) ? date('d/m/Y', strtotime($row['created_at'])) : '-'; ?></td>
                                            <td class="text-center">
                                                <?php if ($is_aktif): ?>
                                                    <span class="badge-status-aktif">
                                                        <i class="fa-solid fa-circle-check"></i> Aktif
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge-status-expired">
                                                        <i class="fa-solid fa-circle-xmark"></i> Tamat Tempoh
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6">
                                            <div class="empty-state">
                                                <i class="fa-solid fa-inbox"></i>
                                                <p>Tiada rekod pendaftaran kontraktor dijumpai.</p>
                                            </div>
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