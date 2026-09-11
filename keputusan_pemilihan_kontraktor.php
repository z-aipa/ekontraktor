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

// AUTO-CREATE JADUAL `keputusan_pemilihan_kontraktor` JIKA BELUM WUJUD
$conn->query("CREATE TABLE IF NOT EXISTS `keputusan_pemilihan_kontraktor` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sub_kategori_id` INT NOT NULL,
    `kontraktor_undi_id` INT NOT NULL,
    `id_kontraktor_auto` VARCHAR(50) NOT NULL,
    `keputusan` ENUM('Berjaya', 'Simpanan') NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$notis_kejayaan = false;
$notis_ralat = "";

// 1. DAPATKAN SEMUA KATEGORI UNDI
$kategori_list = [];
$res_kat = $conn->query("SELECT * FROM kategori_undi ORDER BY id ASC");
if ($res_kat) {
    while ($r = $res_kat->fetch_assoc()) {
        $kategori_list[] = $r;
    }
}

// Kategori Terpilih (Lalai: Kategori Pertama)
$selected_kat_id = isset($_GET['kategori_id']) ? intval($_GET['kategori_id']) : ($kategori_list[0]['id'] ?? 0);

// Sub Kategori / Projek Terpilih (jika ada)
$selected_sub_kat_id = isset($_POST['sub_kategori_id']) ? intval($_POST['sub_kategori_id']) : (isset($_GET['sub_kategori_id']) ? intval($_GET['sub_kategori_id']) : 0);

// 2. DAPATKAN SENARAI PROJEK (SUB KATEGORI) DARI DB
$projek_list = [];
if ($selected_kat_id > 0) {
    $res_proj = $conn->query("SELECT s.*, k.pilihan AS nama_kategori 
                              FROM sub_kategori_undi s 
                              JOIN kategori_undi k ON s.kategori_id = k.id 
                              WHERE s.kategori_id = $selected_kat_id 
                              ORDER BY s.id ASC");
    if ($res_proj) {
        $bil = 1;
        while ($p = $res_proj->fetch_assoc()) {
            $p['bil'] = $bil++;
            $projek_list[] = $p;
        }
    }
}

// 3. DAPATKAN SENARAI KONTRAKTOR LAYAK & SUDAH BAYAR
$kontraktor_list = [];
if ($selected_kat_id > 0) {
    $res_kon = $conn->query("SELECT id, nama_syarikat, id_kontraktor_auto 
                             FROM kontraktor_undi 
                             WHERE kategori_id = $selected_kat_id 
                               AND status_undi = 'Layak' 
                               AND LOWER(status_pembayaran) = 'sudah bayar' 
                             ORDER BY nama_syarikat ASC");
    if ($res_kon) {
        while ($k = $res_kon->fetch_assoc()) {
            $kontraktor_list[] = $k;
        }
    }
}

// 4. DAPATKAN REKOD KEPUTUSAN TERKINI BAGI KATEGORI INI (UNTUK AUTO-TICK)
$saved_keputusan = [];
if ($selected_kat_id > 0) {
    $res_kep = $conn->query("SELECT k.* 
                             FROM keputusan_pemilihan_kontraktor k
                             JOIN sub_kategori_undi s ON k.sub_kategori_id = s.id
                             WHERE s.kategori_id = $selected_kat_id");
    if ($res_kep) {
        while ($rk = $res_kep->fetch_assoc()) {
            $saved_keputusan[$rk['sub_kategori_id']][$rk['keputusan']] = $rk['kontraktor_undi_id'];
        }
    }
}

// DAPATKAN HURUF AWAL KATEGORI UNTUK AUTO GENERATE ID (Contoh: KATEGORI A -> A)
$kategori_nama = "";
foreach ($kategori_list as $kat) {
    if ($kat['id'] == $selected_kat_id) {
        $kategori_nama = $kat['pilihan'];
        break;
    }
}
preg_match('/([A-Z])$/i', trim($kategori_nama), $matches);
$prefix_huruf = !empty($matches[1]) ? strtoupper($matches[1]) : 'A';

// PETA ID KONTRAKTOR AUTO (ID -> A001, A002, ...)
$kontraktor_auto_map = [];
foreach ($kontraktor_list as $index => $kontraktor) {
    $auto_id = !empty($kontraktor['id_kontraktor_auto']) ? $kontraktor['id_kontraktor_auto'] : ($prefix_huruf . str_pad($index + 1, 3, '0', STR_PAD_LEFT));
    $kontraktor_list[$index]['id_auto'] = $auto_id;
    $kontraktor_auto_map[$kontraktor['id']] = $auto_id;
}

// PROSES SIMPAN KEPUTUSAN FORM
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hantar_keputusan'])) {
    $sub_kategori_id        = intval($_POST['sub_kategori_id'] ?? 0);
    $kontraktor_berjaya_id  = intval($_POST['kontraktor_berjaya_id'] ?? 0);
    $kontraktor_simpanan_id = intval($_POST['kontraktor_simpanan_id'] ?? 0);

    if ($sub_kategori_id > 0 && ($kontraktor_berjaya_id > 0 || $kontraktor_simpanan_id > 0)) {
        
        // PADAM REKOD KEPUTUSAN LAMA BAGI PROJEK INI UNTUK ELAK DATA BERTINDIH
        $stmt_del = $conn->prepare("DELETE FROM keputusan_pemilihan_kontraktor WHERE sub_kategori_id = ?");
        $stmt_del->bind_param("i", $sub_kategori_id);
        $stmt_del->execute();
        $stmt_del->close();

        $stmt = $conn->prepare("INSERT INTO keputusan_pemilihan_kontraktor (sub_kategori_id, kontraktor_undi_id, id_kontraktor_auto, keputusan) VALUES (?, ?, ?, ?)");
        
        $berjaya_saved = false;
        $simpanan_saved = false;

        // Simpan Kontraktor Berjaya jika dipilih
        if ($kontraktor_berjaya_id > 0) {
            $auto_id_b = $kontraktor_auto_map[$kontraktor_berjaya_id] ?? '';
            $keputusan_b = 'Berjaya';
            $stmt->bind_param("iiss", $sub_kategori_id, $kontraktor_berjaya_id, $auto_id_b, $keputusan_b);
            if ($stmt->execute()) {
                $berjaya_saved = true;
            }
        }

        // Simpan Kontraktor Simpanan jika dipilih
        if ($kontraktor_simpanan_id > 0) {
            $auto_id_s = $kontraktor_auto_map[$kontraktor_simpanan_id] ?? '';
            $keputusan_s = 'Simpanan';
            $stmt->bind_param("iiss", $sub_kategori_id, $kontraktor_simpanan_id, $auto_id_s, $keputusan_s);
            if ($stmt->execute()) {
                $simpanan_saved = true;
            }
        }

        if ($berjaya_saved || $simpanan_saved) {
            $notis_kejayaan = true;
            
            // KEMASKINI REKOD DALAM ARRAY SUPAYA PAPARAN TERUS BERUBAH
            if ($kontraktor_berjaya_id > 0) {
                $saved_keputusan[$sub_kategori_id]['Berjaya'] = $kontraktor_berjaya_id;
            }
            if ($kontraktor_simpanan_id > 0) {
                $saved_keputusan[$sub_kategori_id]['Simpanan'] = $kontraktor_simpanan_id;
            }
        } else {
            $notis_ralat = "Ralat semasa menyimpan: " . $conn->error;
        }
        $stmt->close();
    } else {
        $notis_ralat = "Sila pastikan projek dan sekurang-kurangnya satu kontraktor dipilih.";
    }
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keputusan Pemilihan Kontraktor | MDBG</title>
    
    <!-- Google Fonts Inter & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    
    <!-- SELECT2 CSS & JQUERY -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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

        /* PAGE HEADER & KAD FORM MODEN */
        .page-header-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            padding: 24px 28px;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.02);
            margin-bottom: 28px;
            max-width: 1150px;
            margin-left: auto;
            margin-right: auto;
        }

        .form-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
            max-width: 1150px;
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

        /* INPUT GROUPS MODEN */
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

        .form-select-custom, .form-control-custom {
            padding: 11px 16px;
            border-top-right-radius: 10px !important;
            border-bottom-right-radius: 10px !important;
            border: 1.5px solid #cbd5e1;
            font-size: 0.92rem;
            font-weight: 500;
            color: #1e293b;
            transition: all 0.2s ease;
            height: auto;
            width: 100%;
            max-width: 100%;
        }

        /* REKABENTUK SELECT2 MODEN & NORMAL */
        .select2-container {
            flex: 1 1 auto;
            width: 1% !important;
            min-width: 0;
        }

        .select2-container--default .select2-selection--single {
            height: auto !important;
            min-height: 48px;
            padding: 8px 14px;
            border: 1.5px solid #cbd5e1 !important;
            border-top-right-radius: 10px !important;
            border-bottom-right-radius: 10px !important;
            border-top-left-radius: 0 !important;
            border-bottom-left-radius: 0 !important;
            display: flex;
            align-items: center;
            background-color: #ffffff;
            transition: all 0.2s ease;
        }

        .select2-container--default.select2-container--open .select2-selection--single,
        .select2-container--default .select2-selection--single:focus {
            border-color: #0d6efd !important;
            box-shadow: 0 0 0 3.5px rgba(13, 110, 253, 0.15);
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            white-space: normal !important;
            word-wrap: break-word !important;
            word-break: break-word !important;
            line-height: 1.45 !important;
            color: #1e293b !important;
            padding-left: 0 !important;
            padding-right: 24px !important;
            font-weight: 600;
            font-size: 0.88rem;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 100% !important;
            top: 0 !important;
            right: 14px !important;
            display: flex;
            align-items: center;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow b {
            border-color: #64748b transparent transparent transparent !important;
            border-width: 6px 5px 0 5px !important;
            transition: transform 0.2s ease;
        }

        .select2-container--default.select2-container--open .select2-selection--single .select2-selection__arrow b {
            border-color: #0d6efd transparent transparent transparent !important;
            transform: rotate(180deg);
        }

        .select2-dropdown {
            border: 1px solid #e2e8f0 !important;
            border-radius: 12px !important;
            box-shadow: 0 16px 32px -6px rgba(15, 23, 42, 0.12), 0 4px 12px -2px rgba(15, 23, 42, 0.04) !important;
            overflow: hidden;
            z-index: 9999 !important;
            background-color: #ffffff !important;
            padding: 6px !important;
            margin-top: 4px;
        }

        .select2-results__options {
            max-height: 420px !important;
            padding-right: 2px;
        }

        .select2-results__options::-webkit-scrollbar {
            width: 6px;
        }
        .select2-results__options::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }
        .select2-results__options::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .select2-results__options::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .select2-results__option {
            white-space: normal !important;
            word-wrap: break-word !important;
            word-break: break-word !important;
            padding: 10px 14px !important;
            font-size: 0.86rem !important;
            line-height: 1.5 !important;
            color: #334155 !important;
            border-radius: 8px !important;
            margin-bottom: 3px !important;
            font-weight: 500;
            transition: all 0.15s ease !important;
        }

        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #0d6efd !important;
            color: #ffffff !important;
            font-weight: 600 !important;
        }

        .select2-container--default .select2-results__option[aria-selected="true"] {
            background-color: #0d6efd !important;
            color: #ffffff !important;
            font-weight: 600 !important;
        }

        .select2-container--default .select2-results__option[aria-selected="true"].select2-results__option--highlighted {
            background-color: #0b5ed7 !important;
            color: #ffffff !important;
        }

        .select2-search--dropdown {
            display: none !important;
        }

        .custom-input-group:focus-within .input-group-text {
            border-color: var(--primary-mdbg);
            background-color: #fdf8f6;
        }

        .form-select-custom:focus, .form-control-custom:focus {
            border-color: var(--primary-mdbg);
            box-shadow: 0 0 0 3.5px rgba(141, 91, 76, 0.12);
        }

        /* STYLES KHAS JADUAL CARIAN & RADIO BUTTON KONTRAKTOR */
        .kontraktor-table-wrapper {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            background: #ffffff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
            max-height: 480px;
            overflow-y: auto;
        }

        .table-kontraktor {
            margin-bottom: 0;
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table-kontraktor thead th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: 700;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 14px 16px;
            position: sticky;
            top: 0;
            z-index: 10;
            border-bottom: 2px solid var(--border-color);
        }

        .table-kontraktor tbody tr {
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .table-kontraktor tbody tr:hover {
            background-color: #fdf8f6;
        }

        .table-kontraktor tbody td {
            padding: 14px 16px;
            vertical-align: middle;
            font-size: 0.88rem;
            border-bottom: 1px solid var(--border-color);
        }

        .custom-radio-input {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: var(--primary-mdbg);
        }

        .badge-id-auto {
            background-color: #e2e8f0;
            color: #1e293b;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.82rem;
            letter-spacing: 0.5px;
            display: inline-block;
        }

        .btn-submit-custom {
            background: linear-gradient(135deg, #8D5B4C 0%, #6e4438 100%);
            color: #ffffff;
            font-weight: 700;
            border: none;
            border-radius: 10px;
            padding: 14px 28px;
            width: 100%;
            font-size: 0.98rem;
            letter-spacing: 0.3px;
            box-shadow: 0 4px 14px rgba(141, 91, 76, 0.3);
            transition: all 0.25s ease;
        }

        .btn-submit-custom:hover {
            background: linear-gradient(135deg, #7a4d3f 0%, #5a362b 100%);
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(141, 91, 76, 0.4);
        }

        @media (max-width: 768px) {
            .main-content-container { padding: 18px; }
            .form-card-body { padding: 24px 20px; }
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
                    <a href="keputusan_pemilihan_kontraktor.php" class="sub-link-item active">
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
                
                <!-- HEADER TAJUK HALAMAN -->
                <div class="page-header-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="p-3 bg-light rounded-3 text-primary-mdbg border">
                            <i class="fa-solid fa-square-poll-vertical fs-4"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold mb-1" style="color: #0f172a;">Keputusan Pemilihan Kontraktor</h4>
                            <p class="text-muted small m-0">Pengurusan dan pendaftaran keputusan penentuan kontraktor berjaya atau simpanan bagi cabutan undi.</p>
                        </div>
                    </div>
                </div>

                <!-- CARD FORM MODEN -->
                <div class="form-card">

                    <div class="form-card-header">
                        <div class="header-icon">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0" style="color: #0f172a;">Borang Kemas Masuk Keputusan</h6>
                            <small class="text-muted fs-7">Sila pastikan maklumat kategori, projek dan kontraktor dipilih dengan betul.</small>
                        </div>
                    </div>

                    <div class="form-card-body">
                        <form action="" method="POST" id="formKeputusan">

                            <!-- SEKSYEN 1: PROJEK -->
                            <div class="section-divider mt-0">
                                <i class="fa-solid fa-folder-tree"></i> 1. Maklumat Kategori & Projek
                            </div>

                            <div class="row g-3 mb-3">
                                <!-- Jenis Kategori (Atas Kiri) -->
                                <div class="col-md-7">
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

                                <!-- Nilai Harga Projek (Atas Kanan) -->
                                <div class="col-md-5">
                                    <label for="nilai_harga" class="form-label form-label-custom">Nilai Harga Projek</label>
                                    <div class="input-group custom-input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-sack-dollar"></i></span>
                                        <input type="text" class="form-control form-control-custom bg-light fw-bold text-success" id="nilai_harga" readonly placeholder="RM 0.00">
                                    </div>
                                </div>

                                <!-- MEDAN PROJEK -->
                                <div class="col-12">
                                    <label for="sub_kategori_id" class="form-label form-label-custom">Projek</label>
                                    <div class="input-group custom-input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-briefcase"></i></span>
                                        <select class="form-select form-select-custom" id="sub_kategori_id" name="sub_kategori_id" onchange="kemaskiniHarga(this)" required>
                                            <option value="" disabled <?= ($selected_sub_kat_id == 0) ? 'selected' : ''; ?>>-- Pilih Projek --</option>
                                            <?php if (!empty($projek_list)): ?>
                                                <?php foreach ($projek_list as $proj): ?>
                                                    <?php 
                                                        $nilai_formatted = number_format($proj['nilai_projek'], 2);
                                                        $papar_projek = "{$proj['bil']}. {$proj['tajuk_kerja']}";
                                                    ?>
                                                    <option value="<?= $proj['id']; ?>" data-harga="RM <?= $nilai_formatted; ?>" title="<?= htmlspecialchars($papar_projek); ?>" <?= ($selected_sub_kat_id == $proj['id']) ? 'selected' : ''; ?>>
                                                        <?= htmlspecialchars($papar_projek); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <option value="" disabled>Tiada Projek Dalam Kategori Ini</option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- RUANGAN PAPARAN SYARIKAT BERJAYA & SIMPANAN TERSIIMPAN BAGI PROJEK -->
                                <div class="col-12" id="boxKeputusanTersimpan" style="display: none;">
                                    <div class="p-3 rounded-3 border" style="background-color: #f8fafc;">
                                        <div class="row g-2">
                                            <!-- SYARIKAT BERJAYA -->
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center justify-content-between p-2 px-3 bg-white rounded border">
                                                    <div class="d-flex align-items-center gap-3">
                                                        <i class="fa-solid fa-trophy text-warning fs-4"></i>
                                                        <div>
                                                            <small class="text-muted d-block fw-bold" style="font-size: 0.72rem; letter-spacing: 0.5px;">SYARIKAT BERJAYA</small>
                                                            <span id="textSyarikatBerjaya" class="fw-bold text-dark" style="font-size: 0.9rem;">Tiada / Belum Dipilih</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- SYARIKAT SIMPANAN -->
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center justify-content-between p-2 px-3 bg-white rounded border">
                                                    <div class="d-flex align-items-center gap-3">
                                                        <i class="fa-solid fa-user-clock text-secondary fs-4"></i>
                                                        <div>
                                                            <small class="text-muted d-block fw-bold" style="font-size: 0.72rem; letter-spacing: 0.5px;">SYARIKAT SIMPANAN</small>
                                                            <span id="textSyarikatSimpanan" class="fw-bold text-dark" style="font-size: 0.9rem;">Tiada / Belum Dipilih</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SEKSYEN 2: KONTRAKTOR & KEPUTUSAN -->
                            <div class="section-divider">
                                <i class="fa-solid fa-user-check"></i> 2. Maklumat Kontraktor & Keputusan
                            </div>

                            <!-- SEARCH BAR UNTUK CARI KONTRAKTOR -->
                            <div class="row g-3 mb-3">
                                <div class="col-12">
                                    <label for="searchKontraktor" class="form-label form-label-custom">Carian Kontraktor (ID Auto / ID Kategori / Nama Syarikat):</label>
                                    <div class="input-group custom-input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass text-primary-mdbg"></i></span>
                                        <input type="text" id="searchKontraktor" class="form-control form-control-custom" placeholder="Taip ID Auto (contoh: A001) atau Nama Syarikat untuk tapis kontraktor...">
                                    </div>
                                </div>
                            </div>

                            <!-- JADUAL PILIHAN RADIO BUTTON KONTRAKTOR -->
                            <div class="kontraktor-table-wrapper mb-4">
                                <table class="table table-hover table-kontraktor" id="jadualKontraktor">
                                    <thead>
                                        <tr>
                                            <th style="width: 50px;" class="text-center">#</th>
                                            <th style="width: 140px;">ID Auto</th>
                                            <th>Nama Syarikat Kontraktor</th>
                                            <th style="width: 160px;" class="text-center">
                                                <i class="fa-solid fa-trophy text-warning me-1"></i>Berjaya
                                            </th>
                                            <th style="width: 160px;" class="text-center">
                                                <i class="fa-solid fa-user-clock text-secondary me-1"></i> Simpanan
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($kontraktor_list)): ?>
                                            <?php foreach ($kontraktor_list as $index => $kontraktor): ?>
                                                <tr class="row-kontraktor-item" style="display: none;">
                                                    <td class="text-center text-muted fw-bold"><?= $index + 1; ?></td>
                                                    <td>
                                                        <span class="badge-id-auto"><?= htmlspecialchars($kontraktor['id_auto']); ?></span>
                                                    </td>
                                                    <td class="fw-semibold text-dark search-target">
                                                        <?= htmlspecialchars($kontraktor['nama_syarikat']); ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <input type="radio" name="kontraktor_berjaya_id" value="<?= $kontraktor['id']; ?>" class="custom-radio-input radio-berjaya" title="Pilih sebagai Kontraktor Berjaya">
                                                    </td>
                                                    <td class="text-center">
                                                        <input type="radio" name="kontraktor_simpanan_id" value="<?= $kontraktor['id']; ?>" class="custom-radio-input radio-simpanan" title="Pilih sebagai Kontraktor Simpanan">
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr id="rowTiadaData">
                                                <td colspan="5" class="text-center text-muted py-4">
                                                    <i class="fa-solid fa-magnifying-glass fs-3 mb-2 d-block text-secondary"></i>
                                                    <span id="textTiadaData">Sila taip ID Auto atau Nama Syarikat untuk membuat carian.</span>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <tr id="rowTiadaData">
                                                <td colspan="5" class="text-center text-muted py-4">
                                                    <i class="fa-solid fa-folder-open fs-3 mb-2 d-block text-secondary"></i>
                                                    Data Tiada Dalam Senarai
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Butang Hantar -->
                            <div class="mt-4 pt-2">
                                <button type="submit" name="hantar_keputusan" class="btn-submit-custom">
                                    <i class="fa-solid fa-paper-plane me-2"></i> Hantar Keputusan Pemilihan
                                </button>
                            </div>

                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- BOOTSTRAP JS, SWEETALERT2 & SELECT2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        // DATA KEPUTUSAN TERSIMPAN DARIPADA DATABASE (PHP TO JS)
        var savedKeputusan = <?= json_encode($saved_keputusan); ?>;
        var kontraktorList = <?= json_encode($kontraktor_list); ?>;
        var currentBerjayaId = 0;
        var currentSimpananId = 0;

        // ELAK POPUP 'CONFIRM FORM RESUBMISSION' SEWAKTU REFRESH
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // FUNGSI UNTUK MENDAPATKAN NAMA SYARIKAT BERDASARKAN ID
        function getNamaKontraktor(id) {
            if (!id) return 'Tiada / Belum Dipilih';
            var k = kontraktorList.find(function(item) { return item.id == id; });
            if (k) {
                return (k.id_auto ? '[' + k.id_auto + '] ' : '') + k.nama_syarikat;
            }
            return 'Tiada / Belum Dipilih';
        }

        // FUNGSI UNTUK SEMAK DAN AUTO-TICK KONTRAKTOR TERSEDIA & PAPAR KEPUTUSAN
        function semakKeputusanTersimpan() {
            var selectedSubKat = $('#sub_kategori_id').val();
            
            // Format semula / Nyah-pilih semua radio
            $('.radio-berjaya').prop('checked', false);
            $('.radio-simpanan').prop('checked', false);

            $('#textSyarikatBerjaya').text('Tiada / Belum Dipilih').removeClass('text-success').addClass('text-muted');
            $('#textSyarikatSimpanan').text('Tiada / Belum Dipilih').removeClass('text-primary').addClass('text-muted');

            currentBerjayaId = 0;
            currentSimpananId = 0;

            if (selectedSubKat) {
                $('#boxKeputusanTersimpan').slideDown();

                if (savedKeputusan[selectedSubKat]) {
                    var dataProjek = savedKeputusan[selectedSubKat];
                    
                    if (dataProjek['Berjaya']) {
                        currentBerjayaId = dataProjek['Berjaya'];
                        $('.radio-berjaya[value="' + currentBerjayaId + '"]').prop('checked', true);
                        $('#textSyarikatBerjaya').text(getNamaKontraktor(currentBerjayaId)).removeClass('text-muted').addClass('text-success');
                    }
                    if (dataProjek['Simpanan']) {
                        currentSimpananId = dataProjek['Simpanan'];
                        $('.radio-simpanan[value="' + currentSimpananId + '"]').prop('checked', true);
                        $('#textSyarikatSimpanan').text(getNamaKontraktor(currentSimpananId)).removeClass('text-muted').addClass('text-primary');
                    }
                }
            } else {
                $('#boxKeputusanTersimpan').slideUp();
            }
        }

        $(document).ready(function() {
            // Aktifkan Select2 bagi Projek sahaja
            $('#sub_kategori_id').select2({
                width: '100%',
                minimumResultsForSearch: Infinity
            }).on('change', function() {
                kemaskiniHarga(this);
                semakKeputusanTersimpan();
            });

            // Jalankan semakan keputusan awal jika ada projek dipilih
            if ($('#sub_kategori_id').val()) {
                kemaskiniHarga(document.getElementById('sub_kategori_id'));
                semakKeputusanTersimpan();
            }

            // CARIAN PINTAR KONTRAKTOR
            $('#searchKontraktor').on('input keyup', function() {
                var query = $(this).val().toLowerCase().trim();
                var countMatch = 0;

                if (query === '') {
                    $('.row-kontraktor-item').hide();
                    $('#rowTiadaData').show();
                    $('#textTiadaData').text('Sila taip ID Auto atau Nama Syarikat untuk membuat carian.');
                } else {
                    $('.row-kontraktor-item').each(function() {
                        var rowText = $(this).text().toLowerCase();
                        if (rowText.indexOf(query) !== -1) {
                            $(this).show();
                            countMatch++;
                        } else {
                            $(this).hide();
                        }
                    });

                    if (countMatch === 0) {
                        $('#rowTiadaData').show();
                        $('#textTiadaData').text('Data Tiada Dalam Senarai');
                    } else {
                        $('#rowTiadaData').hide();
                    }
                }
            });

            // ELAKKAN KONTRAKTOR YANG SAMA DIPILIH KEDUA-DUA BERJAYA & SIMPANAN & KEMASKINI TEKS PAPARAN
            $('.radio-berjaya').on('change', function() {
                var selectedId = $(this).val();
                currentBerjayaId = selectedId;
                $('.radio-simpanan[value="' + selectedId + '"]').prop('checked', false);
                $('#textSyarikatBerjaya').text(getNamaKontraktor(selectedId)).removeClass('text-muted').addClass('text-success');

                if ($('.radio-simpanan:checked').length === 0) {
                    $('#textSyarikatSimpanan').text('Tiada / Belum Dipilih').removeClass('text-primary').addClass('text-muted');
                    currentSimpananId = 0;
                }
            });

            $('.radio-simpanan').on('change', function() {
                var selectedId = $(this).val();
                currentSimpananId = selectedId;
                $('.radio-berjaya[value="' + selectedId + '"]').prop('checked', false);
                $('#textSyarikatSimpanan').text(getNamaKontraktor(selectedId)).removeClass('text-muted').addClass('text-primary');

                if ($('.radio-berjaya:checked').length === 0) {
                    $('#textSyarikatBerjaya').text('Tiada / Belum Dipilih').removeClass('text-success').addClass('text-muted');
                    currentBerjayaId = 0;
                }
            });
        });

        // Penukaran Kategori
        function tukarKategori(katId) {
            window.location.href = "?kategori_id=" + katId;
        }

        // Paparkan Nilai Harga Projek
        function kemaskiniHarga(selectElem) {
            const selectedOption = selectElem.options[selectElem.selectedIndex];
            const harga = selectedOption ? selectedOption.getAttribute('data-harga') : '';
            
            if (harga) {
                document.getElementById('nilai_harga').value = harga;
            } else {
                document.getElementById('nilai_harga').value = "";
            }
        }

        // TOGGLE SIDEBAR
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebarWrapper');
            if (sidebar) sidebar.classList.toggle('collapsed');
        });

        // Notifikasi Kejayaan / Ralat
        <?php if ($notis_kejayaan): ?>
            Swal.fire({
                icon: 'success',
                title: 'Berjaya!',
                text: 'Keputusan pemilihan kontraktor berjaya dihantar.',
                confirmButtonColor: '#8D5B4C'
            });
        <?php elseif (!empty($notis_ralat)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Ralat!',
                text: '<?= $notis_ralat; ?>',
                confirmButtonColor: '#8D5B4C'
            });
        <?php endif; ?>
    </script>
</body>
</html>