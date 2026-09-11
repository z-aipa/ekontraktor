<?php
session_start();

// 1. Sekat daripada menyimpan cache halaman ini
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kejuruteraan') { header("Location: index.php"); exit(); }
include 'db.php';

// ==========================================
// BACKEND: PROSES SEKAT / BUKA AKSES GLOBAL (DIPINDAHKAN KE SINI)
// ==========================================

// 1. SEKAT SEMUA KONTRAKTOR UNDI (BLOCK ALL)
if (isset($_GET['action']) && $_GET['action'] == 'block_all') {
    $conn->query("UPDATE kontraktor_profil SET akses_undi = 0");
    $conn->query("CREATE TABLE IF NOT EXISTS tetapan_sistem (kunci VARCHAR(50) PRIMARY KEY, nilai VARCHAR(255))");
    $conn->query("INSERT INTO tetapan_sistem (kunci, nilai) VALUES ('sekat_semua_undi', '1') ON DUPLICATE KEY UPDATE nilai = '1'");
    header("Location: kejuruteraan.php");
    exit();
}

// 2. BUKA SEMUA AKSES KONTRAKTOR UNDI (UNBLOCK ALL)
if (isset($_GET['action']) && $_GET['action'] == 'unblock_all') {
    $conn->query("UPDATE kontraktor_profil SET akses_undi = 1");
    $conn->query("CREATE TABLE IF NOT EXISTS tetapan_sistem (kunci VARCHAR(50) PRIMARY KEY, nilai VARCHAR(255))");
    $conn->query("INSERT INTO tetapan_sistem (kunci, nilai) VALUES ('sekat_semua_undi', '0') ON DUPLICATE KEY UPDATE nilai = '0'");
    header("Location: kejuruteraan.php");
    exit();
}

// 3. SEKAT SEMUA BORANG DAFTAR SYARIKAT
if (isset($_GET['action']) && $_GET['action'] == 'block_daftar') {
    $conn->query("CREATE TABLE IF NOT EXISTS tetapan_sistem (kunci VARCHAR(50) PRIMARY KEY, nilai VARCHAR(255))");
    $conn->query("INSERT INTO tetapan_sistem (kunci, nilai) VALUES ('sekat_borang_daftar', '1') ON DUPLICATE KEY UPDATE nilai = '1'");
    header("Location: kejuruteraan.php");
    exit();
}

// 4. BUKA SEMUA BORANG DAFTAR SYARIKAT
if (isset($_GET['action']) && $_GET['action'] == 'unblock_daftar') {
    $conn->query("CREATE TABLE IF NOT EXISTS tetapan_sistem (kunci VARCHAR(50) PRIMARY KEY, nilai VARCHAR(255))");
    $conn->query("INSERT INTO tetapan_sistem (kunci, nilai) VALUES ('sekat_borang_daftar', '0') ON DUPLICATE KEY UPDATE nilai = '0'");
    header("Location: kejuruteraan.php");
    exit();
}

// 5. SEKAT PAGE CARIAN SYARIKAT (DITAMBAH)
if (isset($_GET['action']) && $_GET['action'] == 'block_carian') {
    $conn->query("CREATE TABLE IF NOT EXISTS tetapan_sistem (kunci VARCHAR(50) PRIMARY KEY, nilai VARCHAR(255))");
    $conn->query("INSERT INTO tetapan_sistem (kunci, nilai) VALUES ('sekat_carian_syarikat', '1') ON DUPLICATE KEY UPDATE nilai = '1'");
    header("Location: kejuruteraan.php");
    exit();
}

// 6. BUKA PAGE CARIAN SYARIKAT (DITAMBAH)
if (isset($_GET['action']) && $_GET['action'] == 'unblock_carian') {
    $conn->query("CREATE TABLE IF NOT EXISTS tetapan_sistem (kunci VARCHAR(50) PRIMARY KEY, nilai VARCHAR(255))");
    $conn->query("INSERT INTO tetapan_sistem (kunci, nilai) VALUES ('sekat_carian_syarikat', '0') ON DUPLICATE KEY UPDATE nilai = '0'");
    header("Location: kejuruteraan.php");
    exit();
}

// SEMAK STATUS TETAPAN GLOBAL SEKAT UNDI, SEKAT DAFTAR & SEKAT CARIAN
$status_global_sekat = 0;
$status_global_sekat_daftar = 0;
$status_global_sekat_carian = 0;
$check_tbl = $conn->query("SHOW TABLES LIKE 'tetapan_sistem'");
if ($check_tbl && $check_tbl->num_rows > 0) {
    $res_setting = $conn->query("SELECT nilai FROM tetapan_sistem WHERE kunci = 'sekat_semua_undi'");
    if ($res_setting && $res_setting->num_rows > 0) {
        $status_global_sekat = intval($res_setting->fetch_assoc()['nilai']);
    }
    
    $res_setting_daftar = $conn->query("SELECT nilai FROM tetapan_sistem WHERE kunci = 'sekat_borang_daftar'");
    if ($res_setting_daftar && $res_setting_daftar->num_rows > 0) {
        $status_global_sekat_daftar = intval($res_setting_daftar->fetch_assoc()['nilai']);
    }

    $res_setting_carian = $conn->query("SELECT nilai FROM tetapan_sistem WHERE kunci = 'sekat_carian_syarikat'");
    if ($res_setting_carian && $res_setting_carian->num_rows > 0) {
        $status_global_sekat_carian = intval($res_setting_carian->fetch_assoc()['nilai']);
    }
}

// ==========================================
// A. LOGIK PHP UNTUK ADMIN (APPROVAL KEMASKINI)
// ==========================================
if (isset($_POST['action_approve_kemaskini'])) {
    $user_id_target = mysqli_real_escape_string($conn, $_POST['user_id_kontraktor']);
    $conn->query("UPDATE kontraktor_profil SET status_minta_kemaskini = 'Approved' WHERE user_id = '$user_id_target'");
    echo "<script>alert('Akses kemaskini telah diluluskan!'); window.location.href='kejuruteraan.php';</script>";
    exit();
}

if (isset($_POST['action_reject_kemaskini'])) {
    $user_id_target = mysqli_real_escape_string($conn, $_POST['user_id_kontraktor']);
    $conn->query("UPDATE kontraktor_profil SET status_minta_kemaskini = 'Rejected' WHERE user_id = '$user_id_target'");
    echo "<script>alert('Permohonan kemaskini telah ditolak.'); window.location.href='kejuruteraan.php';</script>";
    exit();
}

// ==========================================
// DELETE HISTORY KEMASKINI (SINGLE)
// ==========================================
if (isset($_GET['delete_history_id'])) {
    $history_id = intval($_GET['delete_history_id']);
    $conn->query("UPDATE kontraktor_profil SET status_minta_kemaskini = NULL, sebab_kemaskini = NULL WHERE id = '$history_id'");
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

// ==========================================
// DELETE ALL HISTORY KEMASKINI
// ==========================================
if (isset($_GET['delete_all_history'])) {
    $conn->query("UPDATE kontraktor_profil SET status_minta_kemaskini = NULL, sebab_kemaskini = NULL WHERE status_minta_kemaskini IN ('Approved', 'Rejected')");
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

// Ambil senarai permohonan kemaskini yang 'Pending'
$senarai_minta_kemaskini = $conn->query("SELECT * FROM kontraktor_profil WHERE status_minta_kemaskini = 'Pending'");
$total_minta_kemaskini = $senarai_minta_kemaskini ? $senarai_minta_kemaskini->num_rows : 0;

// Ambil senarai history kemaskini (Approved & Rejected)
$history_kemaskini = $conn->query("SELECT * FROM kontraktor_profil WHERE sebab_kemaskini IS NOT NULL AND sebab_kemaskini != '' ORDER BY id DESC LIMIT 50");
$total_history = $history_kemaskini ? $history_kemaskini->num_rows : 0;

// PROSES PADAM / DISMISS SATU NOTIFIKASI
if (isset($_GET['dismiss_id']) && isset($_GET['jenis_notif'])) {
    $dismiss_id = intval($_GET['dismiss_id']);
    $jenis_notif = $conn->real_escape_string($_GET['jenis_notif']);
    
    // Masukkan ke jadual notifikasi_dismissed supaya tidak terpapar lagi
    $conn->query("INSERT IGNORE INTO notifikasi_dismissed (kontraktor_id, jenis_notif) VALUES ('$dismiss_id', '$jenis_notif')");
    
    // Redirect semula ke page tanpa query string
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

// Tarikh Hari Ini
$today = date('Y-m-d');

// PROSES CLEAR ALL NOTIFIKASI
if (isset($_GET['dismiss_all']) && $_GET['dismiss_all'] == '1') {
    // 1. Clear Expired
    $conn->query("INSERT IGNORE INTO notifikasi_dismissed (kontraktor_id, jenis_notif) 
                  SELECT id, 'EXPIRED' FROM kontraktor_profil 
                  WHERE tarikh_tamat_aktif < '$today' AND tarikh_tamat_aktif IS NOT NULL AND tarikh_tamat_aktif != '' AND tarikh_tamat_aktif != '-'");
    
    // 2. Clear Baharu
    $conn->query("INSERT IGNORE INTO notifikasi_dismissed (kontraktor_id, jenis_notif) 
                  SELECT id, 'BAHARU' FROM kontraktor_profil 
                  WHERE jenis_pendaftaran LIKE '%BAHARU%'");
                  
    // 3. Clear Pembaharuan
    $conn->query("INSERT IGNORE INTO notifikasi_dismissed (kontraktor_id, jenis_notif) 
                  SELECT id, 'PEMBAHARUAN' FROM kontraktor_profil 
                  WHERE jenis_pendaftaran LIKE '%PEMBAHARUAN%'");
                  
    // 4. Clear Resit
    $conn->query("INSERT IGNORE INTO notifikasi_dismissed (kontraktor_id, jenis_notif) 
                  SELECT id, 'RESIT' FROM kontraktor_profil 
                  WHERE (fail_resit IS NOT NULL AND fail_resit != '') AND (fail_sijil IS NULL OR fail_sijil = '')");

    // 5. Clear Pendaftaran Undi Baharu
    $conn->query("INSERT IGNORE INTO notifikasi_dismissed (kontraktor_id, jenis_notif) 
                  SELECT id, 'UNDI_BAHARU' FROM kontraktor_undi");

    // 6. Clear Resit Undi Dimuat Naik
    $conn->query("INSERT IGNORE INTO notifikasi_dismissed (kontraktor_id, jenis_notif) 
                  SELECT id, 'UNDI_RESIT' FROM kontraktor_undi 
                  WHERE fail_resit_undi IS NOT NULL AND fail_resit_undi != ''");

    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

// BILANGAN KAD STATISTIK KONTRAKTOR
$count_permohonan = $conn->query("SELECT COUNT(*) as total FROM kontraktor_profil")->fetch_assoc()['total'];
$count_aktif = $conn->query("SELECT COUNT(*) as total FROM kontraktor_profil WHERE tarikh_tamat_aktif >= '$today'")->fetch_assoc()['total'];
$count_expired = $conn->query("SELECT COUNT(*) as total FROM kontraktor_profil WHERE tarikh_tamat_aktif < '$today' AND tarikh_tamat_aktif IS NOT NULL AND tarikh_tamat_aktif != '' AND tarikh_tamat_aktif != '-'")->fetch_assoc()['total'];
$count_pembaharuan = $conn->query("SELECT COUNT(*) as total FROM kontraktor_profil WHERE jenis_pendaftaran LIKE '%PEMBAHARUAN%'")->fetch_assoc()['total'];

$count_daftar = $conn->query("SELECT COUNT(*) as total FROM kontraktor_profil WHERE status_borang='Pending'")->fetch_assoc()['total'];
$count_undi = $conn->query("SELECT COUNT(*) as total FROM kontraktor_undi WHERE status_undi='Pending'")->fetch_assoc()['total'];

// BILANGAN KAD STATISTIK UNDI
$count_undi_daftar = $conn->query("SELECT COUNT(*) as total FROM kontraktor_undi")->fetch_assoc()['total'];
$count_undi_resit = $conn->query("SELECT COUNT(*) as total FROM kontraktor_undi WHERE fail_resit_undi IS NOT NULL AND fail_resit_undi != ''")->fetch_assoc()['total'];
$count_undi_layak = $conn->query("SELECT COUNT(*) as total FROM kontraktor_undi WHERE status_undi='Layak'")->fetch_assoc()['total'];
$count_undi_bayar = $conn->query("SELECT COUNT(*) as total FROM kontraktor_undi WHERE LOWER(status_pembayaran)='sudah bayar'")->fetch_assoc()['total'];

// ----------------------------------------------------
// QUERY ALERT
// ----------------------------------------------------
$target_30days = date('Y-m-d', strtotime('+30 days'));
$q_expiring = $conn->query("SELECT COUNT(*) as total FROM kontraktor_profil WHERE tarikh_tamat_aktif >= '$today' AND tarikh_tamat_aktif <= '$target_30days'");
$count_expiring_soon = ($q_expiring && $row = $q_expiring->fetch_assoc()) ? $row['total'] : 0;

$q_pending = $conn->query("SELECT COUNT(*) as total FROM kontraktor_profil WHERE (fail_resit IS NOT NULL AND fail_resit != '') AND (fail_sijil IS NULL OR fail_sijil = '')");
$count_pending_receipts = ($q_pending && $row = $q_pending->fetch_assoc()) ? $row['total'] : 0;

// ----------------------------------------------------
// QUERY SISTEM NOTIFIKASI HEADER (DIKUMPUL & DISUSUN TERKINI DI ATAS)
// ----------------------------------------------------
$notif_expired_query = $conn->query("SELECT id, nama_syarikat, tarikh_tamat_aktif, created_at FROM kontraktor_profil WHERE tarikh_tamat_aktif < '$today' AND tarikh_tamat_aktif IS NOT NULL AND tarikh_tamat_aktif != '' AND tarikh_tamat_aktif != '-' AND id NOT IN (SELECT kontraktor_id FROM notifikasi_dismissed WHERE jenis_notif='EXPIRED') ORDER BY id DESC LIMIT 5");
$notif_baharu_query = $conn->query("SELECT id, nama_syarikat, created_at FROM kontraktor_profil WHERE jenis_pendaftaran LIKE '%BAHARU%' AND id NOT IN (SELECT kontraktor_id FROM notifikasi_dismissed WHERE jenis_notif='BAHARU') ORDER BY id DESC LIMIT 5");
$notif_pembaharuan_query = $conn->query("SELECT id, nama_syarikat, created_at FROM kontraktor_profil WHERE jenis_pendaftaran LIKE '%PEMBAHARUAN%' AND id NOT IN (SELECT kontraktor_id FROM notifikasi_dismissed WHERE jenis_notif='PEMBAHARUAN') ORDER BY id DESC LIMIT 5");
$notif_resit_query = $conn->query("SELECT id, nama_syarikat, created_at FROM kontraktor_profil WHERE (fail_resit IS NOT NULL AND fail_resit != '') AND (fail_sijil IS NULL OR fail_sijil = '') AND id NOT IN (SELECT kontraktor_id FROM notifikasi_dismissed WHERE jenis_notif='RESIT') ORDER BY id DESC LIMIT 5");

// NOTIFIKASI UNDI (BAHARU & RESIT UNDI)
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

// Susun mengikut tarikh terkini di atas (LATEST FIRST)
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
    <title>Dashboard Kejuruteraan MDBG</title>
    <!-- Google Fonts Inter & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
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

        /* GAYA DROPDOWN SUB-MENU SIDEBAR */
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

        /* REKABENTUK KAD STATISTIK */
        .stat-card-custom {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 20px;
            text-align: left;
            position: relative;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
            transition: all 0.25s ease;
            height: 100%;
            overflow: hidden;
        }
        .stat-card-custom:hover {
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
            transform: translateY(-3px);
            border-color: #cbd5e1;
        }

        .stat-card-custom::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 5px;
            height: 100%;
            border-radius: 16px 0 0 16px;
        }
        .stat-card-primary::before { background-color: #3b82f6; }
        .stat-card-success::before { background-color: #10b981; }
        .stat-card-danger::before { background-color: #ef4444; }
        .stat-card-warning::before { background-color: #f59e0b; }
        .stat-card-info::before { background-color: #0284c7; }

        .stat-card-number {
            font-size: 2.2rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.1;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }

        .stat-card-title {
            font-size: 0.92rem;
            font-weight: 700;
            color: #334155;
            margin-bottom: 4px;
        }

        .stat-card-sub {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin: 0;
            line-height: 1.35;
        }

        .stat-card-icon-bg {
            position: absolute;
            top: 18px; right: 18px;
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        .stat-card-primary .stat-card-icon-bg { background: #eff6ff; color: #2563eb; }
        .stat-card-success .stat-card-icon-bg { background: #ecfdf5; color: #059669; }
        .stat-card-danger .stat-card-icon-bg { background: #fef2f2; color: #dc2626; }
        .stat-card-warning .stat-card-icon-bg { background: #fffbeb; color: #d97706; }
        .stat-card-info .stat-card-icon-bg { background: #e0f2fe; color: #0284c7; }

        /* SEKSYEN PENGURUSAN KES */
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

        .indicator-flex-container {
            display: flex;
            gap: 24px;
            margin-top: 20px;
        }

        .indicator-box {
            flex: 1;
            border-radius: 14px;
            padding: 24px;
            text-align: center;
            transition: all 0.25s ease;
            background: #ffffff;
            position: relative;
        }
        
        .indicator-box-yellow {
            background: linear-gradient(180deg, #ffffff 0%, #fffdf5 100%);
            border: 1px solid #fde68a;
        }

        .indicator-box-blue {
            background: linear-gradient(180deg, #ffffff 0%, #f0f9ff 100%);
            border: 1px solid #bae6fd;
        }

        .indicator-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
        }

        .indicator-title {
            font-size: 0.92rem;
            font-weight: 700;
            color: #475569;
        }

        .display-num {
            font-size: 3.2rem;
            font-weight: 800;
            line-height: 1;
            margin: 12px 0;
            letter-spacing: -1px;
        }

        .btn-action-outline {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: #334155;
            font-weight: 700;
            padding: 10px 16px;
            font-size: 0.88rem;
            border-radius: 10px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .indicator-box-yellow .btn-action-outline:hover {
            background: #d97706;
            color: #ffffff;
            border-color: #d97706;
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.25);
        }

        .indicator-box-blue .btn-action-outline:hover {
            background: #0284c7;
            color: #ffffff;
            border-color: #0284c7;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);
        }

        /* GAYA QUICK ACTION BADGES */
        .alert-card-pro {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
            position: relative;
            overflow: hidden;
            transition: all 0.25s ease;
            height: 100%;
        }

        .alert-card-pro:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
        }

        .alert-card-pro::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
        }

        .alert-card-warning {
            background: linear-gradient(135deg, #ffffff 0%, #fffdf5 100%);
            border-color: #fef08a;
        }
        .alert-card-warning::before {
            background-color: #f59e0b;
        }

        .alert-card-info {
            background: linear-gradient(135deg, #ffffff 0%, #f0f9ff 100%);
            border-color: #bae6fd;
        }
        .alert-card-info::before {
            background-color: #0284c7;
        }

        .alert-icon-wrapper {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }

        .alert-card-warning .alert-icon-wrapper {
            background-color: #fef3c7;
            color: #d97706;
        }

        .alert-card-info .alert-icon-wrapper {
            background-color: #e0f2fe;
            color: #0284c7;
        }

        .alert-card-text {
            font-size: 0.88rem;
            color: #334155;
            font-weight: 500;
            line-height: 1.35;
            flex: 1;
            min-width: 0;
            overflow: hidden;
        }

        .alert-card-text strong {
            color: #0f172a;
            font-weight: 700;
        }

        .btn-alert-action {
            font-size: 0.825rem;
            font-weight: 700;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
            margin-left: auto;
        }

        .btn-alert-warning {
            background-color: #f59e0b;
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(245, 158, 11, 0.25);
        }
        .btn-alert-warning:hover {
            background-color: #d97706;
            color: #ffffff;
            box-shadow: 0 4px 10px rgba(217, 119, 6, 0.35);
        }

        .btn-alert-info {
            background-color: #0284c7;
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(2, 132, 199, 0.25);
        }
        .btn-alert-info:hover {
            background-color: #0369a1;
            color: #ffffff;
            box-shadow: 0 4px 10px rgba(3, 105, 161, 0.35);
        }

        /* STYLING UNTUK HISTORY KEMASKINI */
        .history-row {
            transition: background-color 0.2s ease;
        }
        .history-row:hover {
            background-color: #f8fafc;
        }
        .badge-status-approved {
            background-color: #dcfce7;
            color: #16a34a;
            border: 1px solid #86efac;
        }
        .badge-status-rejected {
            background-color: #fee2e2;
            color: #dc2626;
            border: 1px solid #fca5a5;
        }
        .btn-delete-history {
            color: #94a3b8;
            background: none;
            border: none;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.75rem;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-delete-history:hover {
            color: #ef4444;
            background-color: #fef2f2;
        }
        .btn-delete-all-history {
            color: #ef4444;
            font-size: 0.72rem;
            font-weight: 700;
            text-decoration: none;
            background-color: #fef2f2;
            padding: 3px 10px;
            border-radius: 6px;
            border: 1px solid #fee2e2;
            transition: all 0.2s ease;
        }
        .btn-delete-all-history:hover {
            background-color: #ef4444;
            color: #ffffff;
            border-color: #ef4444;
        }

        @media (max-width: 768px) {
            .indicator-flex-container { flex-direction: column; }
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
            
            <!-- B. BUTANG KEMASKINI APPROVAL DI NAVBAR ADMIN -->
            <button class="btn btn-outline-light position-relative d-flex align-items-center gap-2 fw-semibold px-3 py-2" style="font-size: 0.85rem; border-radius: 10px;" data-bs-toggle="modal" data-bs-target="#modalKemaskiniApproval">
                <i class="fa-solid fa-user-pen"></i>
                <span class="d-none d-md-inline">Kemaskini Approval</span>
                <?php if ($total_minta_kemaskini > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light">
                        <?= $total_minta_kemaskini; ?>
                    </span>
                <?php endif; ?>
            </button>

            <!-- DROPDOWN NOTIFIKASI BOLEH KLIK -->
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

    <!-- C. CARD FLOATING / MODAL SENARAI PERMOHONAN KEBENARAN KEMASKINI (ADMIN) + HISTORY -->
    <div class="modal fade" id="modalKemaskiniApproval" tabindex="-1" aria-labelledby="modalKemaskiniLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; max-height: 90vh;">
                <div class="modal-header text-white" style="background: linear-gradient(135deg, #3d221a 0%, #6e4438 100%); border-top-left-radius: 16px; border-top-right-radius: 16px;">
                    <h5 class="modal-title fw-bold" id="modalKemaskiniLabel">
                        <i class="fa-solid fa-user-pen me-2 text-warning"></i>Permohonan Kebenaran Kemaskini Profil
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" style="background-color: #f8fafc; overflow-y: auto; max-height: calc(90vh - 130px);">
                    
                    <!-- ================================================== -->
                    <!-- BAHAGIAN 1: SENARAI PERMOHONAN PENDING -->
                    <!-- ================================================== -->
                    <div class="mb-4">
                        <h6 class="fw-bold text-dark mb-3">
                            <i class="fa-solid fa-clock text-warning me-2"></i>Permohonan Pending (<?= $total_minta_kemaskini; ?>)
                        </h6>
                        <?php if ($total_minta_kemaskini == 0): ?>
                            <div class="text-center py-3 bg-white rounded-3 border">
                                <i class="fa-solid fa-circle-check text-success fs-4 mb-2 d-block"></i>
                                <span class="text-muted small">Tiada permohonan pending</span>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle bg-white rounded shadow-sm overflow-hidden mb-0">
                                    <thead class="table-dark">
                                        <tr style="font-size: 0.82rem;">
                                            <th>Nama Syarikat</th>
                                            <th>No. Pendaftaran</th>
                                            <th>Sebab Permohonan</th>
                                            <th class="text-center" style="width: 170px;">Tindakan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row_minta = $senarai_minta_kemaskini->fetch_assoc()): ?>
                                            <tr style="font-size: 0.85rem;">
                                                <td>
                                                    <div class="fw-bold text-dark"><?= htmlspecialchars($row_minta['nama_syarikat']); ?></div>
                                                    <span class="text-muted small">User ID: <?= $row_minta['user_id']; ?></span>
                                                </td>
                                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row_minta['no_pendaftaran']); ?></span></td>
                                                <td>
                                                    <div class="text-muted fst-italic" style="max-width: 250px; font-size: 0.8rem;">
                                                        "<?= htmlspecialchars($row_minta['sebab_kemaskini'] ?? 'Tiada sebab dinyatakan'); ?>"
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex justify-content-center gap-2">
                                                        <form method="POST" action="kejuruteraan.php" style="display:inline;">
                                                            <input type="hidden" name="user_id_kontraktor" value="<?= $row_minta['user_id']; ?>">
                                                            <button type="submit" name="action_approve_kemaskini" class="btn btn-sm btn-success fw-bold px-2 py-1" style="font-size: 0.75rem;">
                                                                <i class="fa-solid fa-check me-1"></i>Lulus
                                                            </button>
                                                        </form>
                                                        <form method="POST" action="kejuruteraan.php" style="display:inline;">
                                                            <input type="hidden" name="user_id_kontraktor" value="<?= $row_minta['user_id']; ?>">
                                                            <button type="submit" name="action_reject_kemaskini" class="btn btn-sm btn-danger fw-bold px-2 py-1" onclick="return confirm('Adakah anda pasti untuk menolak permohonan ini?');" style="font-size: 0.75rem;">
                                                                <i class="fa-solid fa-xmark me-1"></i>Tolak
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ================================================== -->
                    <!-- BAHAGIAN 2: HISTORY PERMOHONAN KEMASKINI -->
                    <!-- ================================================== -->
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="fw-bold text-dark mb-0">
                                <i class="fa-solid fa-clock-rotate-left text-secondary me-2"></i>History Permohonan Kemaskini (<?= $total_history; ?>)
                            </h6>
                            <?php if ($total_history > 0): ?>
                                <a href="?delete_all_history=1" class="btn-delete-all-history" onclick="return confirm('Adakah anda pasti mahu memadam SEMUA rekod history kemaskini?');">
                                    <i class="fa-solid fa-trash me-1"></i> Delete All
                                </a>
                            <?php endif; ?>
                        </div>
                        <?php if ($total_history == 0): ?>
                            <div class="text-center py-3 bg-white rounded-3 border">
                                <i class="fa-solid fa-inbox text-secondary fs-4 mb-2 d-block"></i>
                                <span class="text-muted small">Tiada rekod history kemaskini</span>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle bg-white rounded shadow-sm overflow-hidden mb-0">
                                    <thead style="background: #f1f5f9;">
                                        <tr style="font-size: 0.78rem; text-transform: uppercase; color: #475569;">
                                            <th>Nama Syarikat</th>
                                            <th>No. Pendaftaran</th>
                                            <th style="max-width: 200px;">Sebab Permohonan</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-center" style="width: 60px;">Tindakan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row_history = $history_kemaskini->fetch_assoc()): ?>
                                            <tr class="history-row" style="font-size: 0.82rem;">
                                                <td>
                                                    <div class="fw-bold text-dark"><?= htmlspecialchars($row_history['nama_syarikat']); ?></div>
                                                    <span class="text-muted small">User ID: <?= $row_history['user_id']; ?></span>
                                                </td>
                                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row_history['no_pendaftaran']); ?></span></td>
                                                <td style="max-width: 200px;">
                                                    <div class="text-muted fst-italic" style="font-size: 0.75rem; word-wrap: break-word;">
                                                        "<?= htmlspecialchars($row_history['sebab_kemaskini'] ?? 'Tiada sebab'); ?>"
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                <span class="badge bg-success px-3 py-1.5 fw-bold" style="background-color: #16a34a !important; border: 1px solid #86efac;">
                                                    <i class="fa-solid fa-circle-check me-1"></i> Done
                                                </span>
                                            </td>
                                                <td class="text-center">
                                                    <a href="?delete_history_id=<?= $row_history['id']; ?>" class="btn-delete-history" onclick="return confirm('Adakah anda pasti mahu memadam rekod ini?');" title="Padam rekod ini">
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-end mt-2">
                                <span class="text-muted small">Menunjukkan <?= min($total_history, 50); ?> rekod terakhir</span>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary btn-sm fw-semibold px-3" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <div class="wrapper">
        <!-- SIDEBAR -->
        <div class="sidebar-container" id="sidebarWrapper">
            <div class="sidebar-menu">
                <div class="sidebar-category-title">Menu Utama</div>
                <a href="kejuruteraan.php" class="nav-link-item active">
                    <i class="fa-solid fa-chart-pie me-2"></i> Dashboard
                </a>
                <a href="data_analysis.php" class="nav-link-item">
                    <i class="fa-solid fa-chart-line me-2"></i> Data Analysis
                </a>
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

                <!-- SEKSYEN "TINDAKAN SEGERA / ALERT" -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6">
                        <div class="alert-card-pro alert-card-warning">
                            <div class="alert-icon-wrapper">
                                <i class="fa-solid fa-clock-rotate-left"></i>
                            </div>
                            <div class="alert-card-text">
                                Peringatan: <strong><?= $count_expiring_soon; ?> syarikat</strong> akan tamat tempoh dalam masa 30 hari.
                            </div>
                            <a href="kejuruteraan_senarai_kontraktor.php" class="btn-alert-action btn-alert-warning">
                                Semak <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="alert-card-pro alert-card-info">
                            <div class="alert-icon-wrapper">
                                <i class="fa-solid fa-file-circle-exclamation"></i>
                            </div>
                            <div class="alert-card-text">
                                Peringatan: <strong><?= $count_pending_receipts; ?> resit bayaran</strong> belum dikeluarkan sijil.
                            </div>
                            <a href="kejuruteraan_bayaran.php" class="btn-alert-action btn-alert-info">
                                Pengesahan <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- SEKSYEN KAD STATISTIK UTAMA (KONTRAKTOR) -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="stat-card-custom stat-card-primary">
                            <div class="stat-card-icon-bg">
                                <i class="fa-solid fa-folder-open"></i>
                            </div>
                            <div class="stat-card-number"><?= number_format($count_permohonan); ?></div>
                            <div class="stat-card-title">Permohonan Kontraktor</div>
                            <p class="stat-card-sub">Jumlah keseluruhan mendaftar</p>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="stat-card-custom stat-card-success">
                            <div class="stat-card-icon-bg">
                                <i class="fa-solid fa-user-check"></i>
                            </div>
                            <div class="stat-card-number"><?= number_format($count_aktif); ?></div>
                            <div class="stat-card-title">Kontraktor Aktif</div>
                            <p class="stat-card-sub">Belum tamat tempoh sah</p>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="stat-card-custom stat-card-danger">
                            <div class="stat-card-icon-bg">
                                <i class="fa-solid fa-user-xmark"></i>
                            </div>
                            <div class="stat-card-number"><?= number_format($count_expired); ?></div>
                            <div class="stat-card-title">Tamat Tempoh</div>
                            <p class="stat-card-sub">Telah luput tempoh sah</p>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="stat-card-custom stat-card-warning">
                            <div class="stat-card-icon-bg">
                                <i class="fa-solid fa-rotate"></i>
                            </div>
                            <div class="stat-card-number"><?= number_format($count_pembaharuan); ?></div>
                            <div class="stat-card-title">Perbaharuan Kontraktor</div>
                            <p class="stat-card-sub">Telah kemaskini perbaharuan</p>
                        </div>
                    </div>
                </div>

                <!-- SEKSYEN KAD STATISTIK UNDI (DIBAWAH 4 KAD KONTRAKTOR) -->
                <div class="row g-3 mb-4">
                    <!-- 1. Total yang daftar Undi -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="stat-card-custom stat-card-primary">
                            <div class="stat-card-icon-bg">
                                <i class="fa-solid fa-check-to-slot"></i>
                            </div>
                            <div class="stat-card-number"><?= number_format($count_undi_daftar); ?></div>
                            <div class="stat-card-title">Daftar Undi Kontraktor</div>
                            <p class="stat-card-sub">Jumlah keseluruhan daftar undi</p>
                        </div>
                    </div>

                    <!-- 2. Total Resit Bayaran Undi (Boleh ditekan ke senarai_kontraktor_undi.php) -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <a href="senarai_kontraktor_undi.php" class="text-decoration-none">
                            <div class="stat-card-custom stat-card-warning" style="cursor: pointer;">
                                <div class="stat-card-icon-bg">
                                    <i class="fa-solid fa-receipt"></i>
                                </div>
                                <div class="stat-card-number"><?= number_format($count_undi_resit); ?></div>
                                <div class="stat-card-title">Jumlah Resit Bayaran Undi <i class="fa-solid fa-arrow-right-to-bracket ms-1" style="font-size:0.75rem;"></i></div>
                                <p class="stat-card-sub">Klik untuk senarai kontraktor undi</p>
                            </div>
                        </a>
                    </div>

                    <!-- 3. Total Layak -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="stat-card-custom stat-card-success">
                            <div class="stat-card-icon-bg">
                                <i class="fa-solid fa-circle-check"></i>
                            </div>
                            <div class="stat-card-number"><?= number_format($count_undi_layak); ?></div>
                            <div class="stat-card-title">Kontraktor Yang Layak</div>
                            <p class="stat-card-sub">Mempunyai status layak</p>
                        </div>
                    </div>

                    <!-- 4. Total yang sudah bayar -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="stat-card-custom stat-card-info">
                            <div class="stat-card-icon-bg">
                                <i class="fa-solid fa-money-bill-wave"></i>
                            </div>
                            <div class="stat-card-number"><?= number_format($count_undi_bayar); ?></div>
                            <div class="stat-card-title">Kontraktor Yang Sudah Bayar</div>
                            <p class="stat-card-sub">Mempunyai status sudah bayar</p>
                        </div>
                    </div>
                </div>

                <!-- SEKSYEN HALAMAN UTAMA PENGURUSAN KES -->
                <div class="status-card-container mb-4">
                    <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-2">
                        <div class="status-header">
                            <i class="fa-solid fa-layer-group text-muted me-2"></i> Halaman Utama Pengurusan Kes
                        </div>
                        <span class="badge bg-light text-secondary border px-3 py-2 rounded-pill fw-semibold" style="font-size:0.75rem;">
                            <i class="fa-regular fa-calendar-check me-1"></i> Tarikh: <?= date('d/m/Y'); ?>
                        </span>
                    </div>

                    <div class="indicator-flex-container">
                        <div class="indicator-box indicator-box-yellow">
                            <div class="indicator-title">Semakan Borang Baharu & Pembaharuan</div>
                            <div class="display-num text-warning"><?= $count_daftar; ?></div>
                            <a href="kejuruteraan_semak_pendaftaran.php" class="btn-action-outline w-100">
                                Buka Semakan <i class="fa-solid fa-arrow-right ms-1"></i>
                            </a>
                        </div>

                        <div class="indicator-box indicator-box-blue">
                            <div class="indicator-title">Semakan Kelayakan Undi</div>
                            <div class="display-num text-info"><?= $count_undi; ?></div>
                            <a href="kejuruteraan_semak_kelayakan_undi.php" class="btn-action-outline w-100">
                                Buka Semakan <i class="fa-solid fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- ==================================================== -->
                <!-- PANEL KAWALAN AKSES GLOBAL (REKABENTUK MODEN & KEMAS) -->
                <!-- ==================================================== -->
                <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="border: 1px solid #e2e8f0 !important;">
                    <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-warning-subtle text-warning-emphasis p-2 rounded-3 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                <i class="fa-solid fa-shield-halved fs-5"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0" style="letter-spacing: 0.3px;">PANEL KAWALAN AKSES GLOBAL</h6>
                                <span class="text-muted small" style="font-size: 0.75rem;">Tetapan status kebenaran capaian borang dan modul pengguna</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4" style="background-color: #f8fafc;">
                        <div class="row g-3">
                            <!-- KAWALAN AKSES 1: SEKAT PAGE DAFTAR UNDI -->
                            <div class="col-12 col-lg-4">
                                <div class="bg-white border rounded-3 p-3.5 p-3 shadow-sm h-100 d-flex flex-column justify-content-between">
                                    <div>
                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="fa-solid fa-vote-yea text-secondary fs-5"></i>
                                                <span class="fw-bold text-dark" style="font-size: 0.88rem;">Page Daftar Undi</span>
                                            </div>
                                            <?php if ($status_global_sekat == 1): ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1.5 rounded-pill fw-bold" style="font-size: 0.7rem;">
                                                    <i class="fa-solid fa-lock me-1"></i> DISEKAT
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 rounded-pill fw-bold" style="font-size: 0.7rem;">
                                                    <i class="fa-solid fa-lock-open me-1"></i> DIBUKA
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="pt-3 border-top d-flex align-items-center gap-2">
                                        <a href="kejuruteraan.php?action=block_all" 
                                           class="btn btn-danger btn-sm flex-fill d-inline-flex align-items-center justify-content-center gap-2 fw-semibold py-2 rounded-2 shadow-sm"
                                           onclick="return confirm('Adakah anda pasti mahu SEKAT SEMUA kontraktor daripada mengakses borang kontraktor_daftar_undi.php?');"
                                           title="Sekat Semua Kontraktor Daripada Mendaftar Undi">
                                            <i class="fa-solid fa-ban"></i> Sekat Daftar Undi
                                        </a>
                                        <a href="kejuruteraan.php?action=unblock_all" 
                                           class="btn btn-success btn-sm flex-fill d-inline-flex align-items-center justify-content-center gap-2 fw-semibold py-2 rounded-2 shadow-sm"
                                           onclick="return confirm('Adakah anda pasti mahu BUKA AKSES SEMUA kontraktor untuk mendaftar undi?');"
                                           title="Buka Akses Semua Kontraktor Mendaftar Undi">
                                            <i class="fa-solid fa-user-check"></i> Buka Daftar Undi
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- KAWALAN AKSES 2: SEKAT PAGE DAFTAR SYARIKAT -->
                            <div class="col-12 col-lg-4">
                                <div class="bg-white border rounded-3 p-3 shadow-sm h-100 d-flex flex-column justify-content-between">
                                    <div>
                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="fa-solid fa-id-card text-secondary fs-5"></i>
                                                <span class="fw-bold text-dark" style="font-size: 0.88rem;">Page Daftar Syarikat</span>
                                            </div>
                                            <?php if ($status_global_sekat_daftar == 1): ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1.5 rounded-pill fw-bold" style="font-size: 0.7rem;">
                                                    <i class="fa-solid fa-lock me-1"></i> DISEKAT
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 rounded-pill fw-bold" style="font-size: 0.7rem;">
                                                    <i class="fa-solid fa-lock-open me-1"></i> DIBUKA
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="pt-3 border-top d-flex align-items-center gap-2">
                                        <a href="kejuruteraan.php?action=block_daftar" 
                                           class="btn btn-danger btn-sm flex-fill d-inline-flex align-items-center justify-content-center gap-2 fw-semibold py-2 rounded-2 shadow-sm"
                                           onclick="return confirm('Adakah anda pasti mahu SEKAT SEMUA kontraktor daripada mengakses borang kontraktor_borang_daftar.php?');"
                                           title="Sekat Akses Borang Daftar Syarikat">
                                            <i class="fa-solid fa-ban"></i> Sekat Daftar Syarikat
                                        </a>
                                        <a href="kejuruteraan.php?action=unblock_daftar" 
                                           class="btn btn-success btn-sm flex-fill d-inline-flex align-items-center justify-content-center gap-2 fw-semibold py-2 rounded-2 shadow-sm"
                                           onclick="return confirm('Adakah anda pasti mahu BUKA AKSES SEMUA kontraktor ke borang kontraktor_borang_daftar.php?');"
                                           title="Buka Akses Borang Daftar Syarikat">
                                            <i class="fa-solid fa-user-check"></i> Buka Daftar Syarikat
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- KAWALAN AKSES 3: SEKAT PAGE CARIAN SYARIKAT -->
                            <div class="col-12 col-lg-4">
                                <div class="bg-white border rounded-3 p-3 shadow-sm h-100 d-flex flex-column justify-content-between">
                                    <div>
                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="fa-solid fa-magnifying-glass text-secondary fs-5"></i>
                                                <span class="fw-bold text-dark" style="font-size: 0.88rem;">Page Carian Syarikat</span>
                                            </div>
                                            <?php if ($status_global_sekat_carian == 1): ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1.5 rounded-pill fw-bold" style="font-size: 0.7rem;">
                                                    <i class="fa-solid fa-lock me-1"></i> DISEKAT
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 rounded-pill fw-bold" style="font-size: 0.7rem;">
                                                    <i class="fa-solid fa-lock-open me-1"></i> DIBUKA
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="pt-3 border-top d-flex align-items-center gap-2">
                                        <a href="kejuruteraan.php?action=block_carian" 
                                           class="btn btn-danger btn-sm flex-fill d-inline-flex align-items-center justify-content-center gap-2 fw-semibold py-2 rounded-2 shadow-sm"
                                           onclick="return confirm('Adakah anda pasti mahu SEKAT SEMUA kontraktor daripada mengakses borang kontraktor_carian_syarikat.php?');"
                                           title="Sekat Akses Carian Syarikat">
                                            <i class="fa-solid fa-ban"></i> Sekat Carian Syarikat
                                        </a>
                                        <a href="kejuruteraan.php?action=unblock_carian" 
                                           class="btn btn-success btn-sm flex-fill d-inline-flex align-items-center justify-content-center gap-2 fw-semibold py-2 rounded-2 shadow-sm"
                                           onclick="return confirm('Adakah anda pasti mahu BUKA AKSES SEMUA kontraktor ke borang kontraktor_carian_syarikat.php?');"
                                           title="Buka Akses Carian Syarikat">
                                            <i class="fa-solid fa-user-check"></i> Buka Carian Syarikat
                                        </a>
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
        // TOGGLE SIDEBAR
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebarWrapper');
            if (sidebar) sidebar.classList.toggle('collapsed');
        });

        // SKRIP UTAMA: MEMAKSA DROPDOWN NOTIFIKASI UNTUK TERBUKA
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
    </script>
</body>
</html>