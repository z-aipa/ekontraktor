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

// FUNGSI UNTUK FORMAT JADUAL MINI GRED (DIKEMAS KINI DENGAN SOKONGAN FORMAT JSON & DUAL-SOURCE)
function formatGredTable($gred_str) {
    $gred_raw = trim($gred_str ?? '');
    if (empty($gred_raw)) return '-';

    // 1. Semak jika data dalam format JSON
    if (strpos($gred_raw, '[') === 0 || strpos($gred_raw, '{') === 0) {
        $json_data = json_decode($gred_raw, true);
        if (is_array($json_data) && !empty($json_data)) {
            $html = '<table class="table table-sm table-bordered m-0 text-center align-middle d-inline-table inner-gred-table" style="font-size:0.65rem; width: auto; max-width: 100%; background:#ffffff; border-color:#cbd5e1; margin: 0 auto !important;">';
            $html .= '<thead style="background-color:#64748b; color:#ffffff;"><tr><th style="padding:2px 6px;">GRED</th><th style="padding:2px 6px;">KATEGORI</th><th style="padding:2px 6px;">PENGKHUSUSAN</th></tr></thead><tbody>';
            foreach ($json_data as $item) {
                $g = htmlspecialchars($item['gred'] ?? '-');
                $k = htmlspecialchars($item['kategori'] ?? '-');
                $p = htmlspecialchars($item['pengkhususan'] ?? '-');
                $html .= '<tr>';
                $html .= '<td class="fw-bold font-monospace" style="padding:2px 6px;">' . $g . '</td>';
                $html .= '<td class="font-monospace" style="padding:2px 6px;">' . $k . '</td>';
                $html .= '<td class="font-monospace" style="padding:2px 6px;">' . $p . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            return $html;
        }
    }

    // 2. Semak jika data berbilang baris (multiline) atau mempunyai kata kunci GRED & KATEGORI
    if (strpos($gred_raw, "\n") !== false || (strpos($gred_raw, 'GRED') !== false && strpos($gred_raw, 'KATEGORI') !== false)) {
        $lines = array_filter(array_map('trim', explode("\n", str_replace("\r", "", $gred_raw))));
        $html = '<table class="table table-sm table-bordered m-0 text-center align-middle d-inline-table inner-gred-table" style="font-size:0.65rem; width: auto; max-width: 100%; background:#ffffff; border-color:#cbd5e1; margin: 0 auto !important;">';
        
        $is_first = true;
        foreach ($lines as $line) {
            $cols = preg_split('/\s+/', $line);
            if (count($cols) >= 3) {
                if ($is_first && (strcasecmp($cols[0], 'GRED') == 0 || strcasecmp($cols[1], 'KATEGORI') == 0)) {
                    $html .= '<thead style="background-color:#64748b; color:#ffffff;"><tr>';
                    $html .= '<th style="padding:2px 6px;">' . htmlspecialchars($cols[0]) . '</th>';
                    $html .= '<th style="padding:2px 6px;">' . htmlspecialchars($cols[1]) . '</th>';
                    $html .= '<th style="padding:2px 6px;">' . htmlspecialchars(implode(' ', array_slice($cols, 2))) . '</th>';
                    $html .= '</tr></thead><tbody>';
                    $is_first = false;
                } else {
                    if ($is_first) {
                        $html .= '<thead style="background-color:#64748b; color:#ffffff;"><tr><th style="padding:2px 6px;">GRED</th><th style="padding:2px 6px;">KATEGORI</th><th style="padding:2px 6px;">PENGKHUSUSAN</th></tr></thead><tbody>';
                        $is_first = false;
                    }
                    $html .= '<tr>';
                    $html .= '<td class="fw-bold font-monospace" style="padding:2px 6px;">' . htmlspecialchars($cols[0]) . '</td>';
                    $html .= '<td class="font-monospace" style="padding:2px 6px;">' . htmlspecialchars($cols[1]) . '</td>';
                    $html .= '<td class="font-monospace" style="padding:2px 6px;">' . htmlspecialchars(implode(' ', array_slice($cols, 2))) . '</td>';
                    $html .= '</tr>';
                }
            } elseif (count($cols) == 2) {
                if ($is_first) {
                    $html .= '<thead style="background-color:#64748b; color:#ffffff;"><tr><th style="padding:2px 6px;">GRED</th><th style="padding:2px 6px;">KATEGORI</th></tr></thead><tbody>';
                    $is_first = false;
                }
                $html .= '<tr><td class="font-monospace" style="padding:2px 6px;">' . htmlspecialchars($cols[0]) . '</td><td class="font-monospace" style="padding:2px 6px;">' . htmlspecialchars($cols[1]) . '</td></tr>';
            } elseif (!empty($cols[0])) {
                if ($is_first) {
                    $html .= '<tbody>';
                    $is_first = false;
                }
                $html .= '<tr><td colspan="3" class="font-monospace" style="padding:2px 6px;">' . htmlspecialchars($cols[0]) . '</td></tr>';
            }
        }
        if (!$is_first) {
            $html .= '</tbody>';
        }
        $html .= '</table>';
        return $html;
    } else {
        return '<span class="badge border font-monospace" style="font-size: 0.72rem; font-weight: 700; letter-spacing: 0.5px; padding: 4px 8px; background-color: #f8fafc; color: #475569; border-color: #cbd5e1 !important; border-radius: 6px;">' . htmlspecialchars($gred_raw) . '</span>';
    }
}

// BACKEND: PROSES PADAM DATA (DELETE)
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("DELETE FROM kontraktor_undi WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: senarai_kontraktor_undi.php");
    exit();
}

// BACKEND: PROSES SEKAT / BUKAR AKSES KONTRAKTOR (TOGGLE ACCESS)
if (isset($_GET['action']) && $_GET['action'] == 'toggle_access' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    // Menukar nilai akses_undi: 1 (Dibenarkan) -> 0 (Disekat) atau sebaliknya
    $stmt = $conn->prepare("UPDATE kontraktor_undi SET akses_undi = IF(COALESCE(akses_undi, 1) = 1, 0, 1) WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: senarai_kontraktor_undi.php");
    exit();
}

// BACKEND: PROSES KEMAS KINI DATA (UPDATE) & GENERATE AUTO ID
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    $id = intval($_POST['id']);
    $status_permohonan = $_POST['status_permohonan'];
    $status_undi = $_POST['status_undi'];
    $no_bil_transaksi = $_POST['no_bil_transaksi'];
    $status_pembayaran = $_POST['status_pembayaran'] ?? 'belum bayar';

    // Dapatkan data semasa kontraktor untuk semak id_kontraktor_auto & kategori_id
    $stmt_curr = $conn->prepare("SELECT ku.id_kontraktor_auto, ku.kategori_id, kat.pilihan 
                                 FROM kontraktor_undi ku 
                                 LEFT JOIN kategori_undi kat ON ku.kategori_id = kat.id 
                                 WHERE ku.id = ?");
    $stmt_curr->bind_param("i", $id);
    $stmt_curr->execute();
    $curr_data = $stmt_curr->get_result()->fetch_assoc();
    $stmt_curr->close();

    $id_kontraktor_auto = $curr_data['id_kontraktor_auto'] ?? null;

    // Jika Status Undi = 'Layak' DAN Status Pembayaran = 'sudah bayar'
    if ($status_undi == 'Layak' && strtolower($status_pembayaran) == 'sudah bayar') {
        // Jana ID baru jika belum wujud
        if (empty($id_kontraktor_auto)) {
            $pilihan = $curr_data['pilihan'] ?? 'KATEGORI A';
            
            // Ekstrak huruf Kategori (Contoh: "KATEGORI A" -> "A")
            $prefix = 'A';
            if (!empty($pilihan)) {
                $pilihan_clean = trim(strtoupper($pilihan));
                if (preg_match('/([A-Z0-9]+)$/', $pilihan_clean, $matches)) {
                    $prefix = $matches[1];
                }
            }

            // Cari ID auto tertinggi dalam database bagi prefix berkenaan
            $search_prefix = $prefix . '%';
            $stmt_max = $conn->prepare("SELECT id_kontraktor_auto FROM kontraktor_undi WHERE id_kontraktor_auto LIKE ? ORDER BY LENGTH(id_kontraktor_auto) DESC, id_kontraktor_auto DESC LIMIT 1");
            $stmt_max->bind_param("s", $search_prefix);
            $stmt_max->execute();
            $res_max = $stmt_max->get_result()->fetch_assoc();
            $stmt_max->close();

            $max_num = 0;
            if ($res_max && !empty($res_max['id_kontraktor_auto'])) {
                $num_part = preg_replace('/[^0-9]/', '', $res_max['id_kontraktor_auto']);
                $max_num = intval($num_part);
            }

            $next_num = $max_num + 1;
            // Format 3 digit berurutan: Contoh A001, A002, B001
            $id_kontraktor_auto = $prefix . sprintf('%03d', $next_num);
        }
    }

    $stmt = $conn->prepare("UPDATE kontraktor_undi SET 
        status_permohonan = ?, 
        status_undi = ?, 
        no_bil_transaksi = ?,
        status_pembayaran = ?,
        id_kontraktor_auto = ?
        WHERE id = ?");
        
    $stmt->bind_param("sssssi", 
        $status_permohonan, 
        $status_undi, 
        $no_bil_transaksi,
        $status_pembayaran,
        $id_kontraktor_auto,
        $id
    );
    $stmt->execute();
    $stmt->close();
    header("Location: senarai_kontraktor_undi.php");
    exit();
}

$edit_data = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $stmt = $conn->prepare("SELECT ku.*, COALESCE(NULLIF(ku.gred_cidb_kewangan, ''), kp.gred_cidb_kewangan) AS gred_cidb_kewangan FROM kontraktor_undi ku LEFT JOIN kontraktor_profil kp ON ku.user_id = kp.user_id WHERE ku.id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// FILTER & CARIAN BACKEND
$filter_permohonan = $_GET['status_permohonan'] ?? '';
$filter_undi = $_GET['status_undi'] ?? '';
$search_query = trim($_GET['search'] ?? '');

$query_str = "SELECT ku.*, COALESCE(NULLIF(ku.gred_cidb_kewangan, ''), kp.gred_cidb_kewangan) AS gred_cidb_kewangan, kat.pilihan AS nama_kategori FROM kontraktor_undi ku LEFT JOIN kontraktor_profil kp ON ku.user_id = kp.user_id LEFT JOIN kategori_undi kat ON ku.kategori_id = kat.id WHERE 1=1";
$params = [];
$types = "";

if (!empty($filter_permohonan)) {
    $query_str .= " AND ku.status_permohonan = ?";
    $params[] = $filter_permohonan;
    $types .= "s";
}

if (!empty($filter_undi)) {
    $query_str .= " AND ku.status_undi = ?";
    $params[] = $filter_undi;
    $types .= "s";
}

if (!empty($search_query)) {
    $query_str .= " AND (ku.nama_syarikat LIKE ? OR ku.no_pendaftaran LIKE ? OR ku.email LIKE ? OR ku.no_tel LIKE ?)";
    $search_param = "%" . $search_query . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

$query_str .= " ORDER BY ku.id DESC";

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
    <title>Senarai Pendaftaran Undian Kontraktor</title>
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
            min-width: 1600px; 
            border-collapse: collapse;
        }

        .table-mdbg th {
            padding: 18px 20px !important;
            font-size: 0.78rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            color: #475569 !important;
            white-space: nowrap !important; 
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
        .table-mdbg td table, table.inner-gred-table {
            width: auto !important;
            margin: 0 auto !important;
            font-size: 0.65rem !important;
        }
        .table-mdbg td table th, 
        .table-mdbg td table td,
        table.inner-gred-table th,
        table.inner-gred-table td {
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

        .badge-status.status-layak { background-color: #dcfce7; color: #15803d; }
        .badge-status.status-pending { background-color: #fef9c3; color: #a16207; }
        .badge-status.status-gagal { background-color: #fee2e2; color: #b91c1c; }

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

        /* CETAKAN STYLES (DIPERBAIKI UNTUK JADUAL UTAMA DAN JADUAL MINI GRED) */
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
                display: table !important;
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

            /* SEMAKAN PAPARAN JADUAL MINI GRED SEWAKTU CETAKAN */
            table.table-mdbg td table, table.inner-gred-table {
                display: table !important;
                width: 100% !important;
                margin: 0 auto !important;
                border-collapse: collapse !important;
                border: 1px solid #94a3b8 !important;
                background-color: #ffffff !important;
            }

            table.table-mdbg td table th, table.inner-gred-table th {
                background-color: #64748b !important;
                color: #ffffff !important;
                font-size: 5.5pt !important;
                padding: 2px 4px !important;
                border: 1px solid #64748b !important;
                text-align: center !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            table.table-mdbg td table td, table.inner-gred-table td {
                font-size: 5.5pt !important;
                padding: 2px 4px !important;
                border: 1px solid #cbd5e1 !important;
                text-align: center !important;
                color: #0f172a !important;
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

                <!-- SUB-MENU FASA 2 -->
                <div class="collapse show sidebar-submenu" id="menuFasa2">
                    <a href="senarai_kontraktor_undi.php" class="sub-link-item active">
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
                    <h5 class="fw-semibold text-muted text-uppercase small mt-1" id="subTajukCetak">Senarai Kontraktor Berdaftar Undi (Jabatan Kejuruteraan)</h5>
                </div>

                <div class="status-card-container mb-4">
                    <h5 class="fw-bold text-dark text-start mb-4 screen-only"><i class="fa-solid fa-folder-open text-muted me-2"></i>Senarai Kontraktor Berdaftar Undi</h5>
                    
                    <!-- KOTAK FILTER -->
                    <div class="p-3 bg-light rounded-3 border mb-3 screen-only">
                        <form method="GET" action="senarai_kontraktor_undi.php" class="row g-3 align-items-end">
                            <input type="hidden" name="search" value="<?= htmlspecialchars($search_query); ?>">
                            <div class="col-12 col-md-4 col-lg-4">
                                <label class="form-label small fw-bold text-secondary mb-1"><i class="fa-solid fa-clock me-1"></i> Status Permohonan:</label>
                                <select name="status_permohonan" class="form-select form-select-sm shadow-sm" style="border-radius: 8px; padding: 0.45rem 0.75rem;">
                                    <option value="">-- Semua Status Permohonan --</option>
                                    <option value="Baru" <?= ($filter_permohonan == 'Baru') ? 'selected' : ''; ?>>🟢 Baru</option>
                                    <option value="Menunggu Semakan" <?= ($filter_permohonan == 'Menunggu Semakan') ? 'selected' : ''; ?>>🟡 Menunggu Semakan</option>
                                    <option value="Lulus" <?= ($filter_permohonan == 'Lulus') ? 'selected' : ''; ?>>🔵 Lulus</option>
                                    <option value="Gagal" <?= ($filter_permohonan == 'Gagal') ? 'selected' : ''; ?>>🔴 Gagal</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-4 col-lg-4">
                                <label class="form-label small fw-bold text-secondary mb-1"><i class="fa-solid fa-check-double me-1"></i> Status Kelayakan Undi:</label>
                                <select name="status_undi" class="form-select form-select-sm shadow-sm" style="border-radius: 8px; padding: 0.45rem 0.75rem;">
                                    <option value="">-- Semua Status Undi --</option>
                                    <option value="Layak" <?= ($filter_undi == 'Layak') ? 'selected' : ''; ?>>🟢 Layak</option>
                                    <option value="Pending" <?= ($filter_undi == 'Pending') ? 'selected' : ''; ?>>🟡 Pending</option>
                                    <option value="Tidak Layak" <?= ($filter_undi == 'Tidak Layak') ? 'selected' : ''; ?>>🔴 Tidak Layak</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-4 col-lg-4 d-flex gap-2">
                                <button type="submit" class="btn btn-sm btn-filter-tapis d-inline-flex align-items-center gap-1">
                                    <i class="fa-solid fa-filter fs-6"></i> Tapis
                                </button>
                                <?php if (!empty($filter_permohonan) || !empty($filter_undi) || !empty($search_query)): ?>
                                    <a href="senarai_kontraktor_undi.php" class="btn btn-sm btn-filter-reset d-inline-flex align-items-center gap-1">
                                        <i class="fa-solid fa-rotate-left fs-6"></i> Reset
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>

                    <!-- BAR CARIAN -->
                    <div class="quick-search-box mb-4 screen-only">
                        <form action="senarai_kontraktor_undi.php" method="GET" class="m-0">
                            <input type="hidden" name="status_permohonan" value="<?= htmlspecialchars($filter_permohonan); ?>">
                            <input type="hidden" name="status_undi" value="<?= htmlspecialchars($filter_undi); ?>">
                            <div class="row g-2 align-items-center">
                                <div class="col-12 col-md-9 col-lg-10 position-relative">
                                    <i class="fa-solid fa-magnifying-glass quick-search-icon"></i>
                                    <input type="text" name="search" class="form-control quick-search-input" placeholder="Carian pantas: Masukkan Nama Syarikat, No. Pendaftaran, No. Tel, atau E-mel..." value="<?= htmlspecialchars($search_query); ?>" autocomplete="off">
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
                                    <th>NAMA SYARIKAT</th>
                                    <th>NO. PENDAFTARAN</th>
                                    <th>NO. TELEFON</th>
                                    <th>EMAIL</th>
                                    <th class="text-center">GRED</th>
                                    <th class="text-center">PERAKUAN</th>
                                    <th class="text-center">STATUS PERMOHONAN</th>
                                    <th class="text-center">TARIKH HANTAR</th>
                                    <th class="text-center">STATUS UNDI</th>
                                    <th class="text-center">STATUS PEMBAYARAN</th>
                                    <th class="text-center">ID / KATEGORI</th>
                                    <th class="text-center">NO. BIL TRANSAKSI</th>
                                    <th class="text-center">FAIL BIL UNDI</th>
                                    <th class="text-center">FAIL RESIT UNDI</th>
                                    <th class="text-center screen-only">TINDAKAN</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($senarai_kontraktor && $senarai_kontraktor->num_rows > 0): ?>
                                    <?php $no = 1; while($row = $senarai_kontraktor->fetch_assoc()): 
                                        $status_p = $row['status_permohonan'] ?? 'Baru';
                                        $status_u = $row['status_undi'] ?? 'Pending';
                                        $akses_undi = $row['akses_undi'] ?? 1; // Default 1 (Dibenarkan)
                                    ?>
                                        <tr>
                                            <td class="text-center fw-semibold text-muted status-bilangan-kolum"><?= $no++; ?>.</td>
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="company-icon-box screen-only"><i class="fa-solid fa-briefcase"></i></div>
                                                    <div class="fw-bold text-dark text-uppercase"><?= htmlspecialchars($row['nama_syarikat'] ?? ''); ?></div>
                                                </div>
                                            </td>
                                            <td><span class="text-secondary fw-semibold font-monospace"><?= htmlspecialchars($row['no_pendaftaran'] ?? ''); ?></span></td>
                                            <td><span><?= htmlspecialchars($row['no_tel'] ?? ''); ?></span></td>
                                            <td><span><?= htmlspecialchars($row['email'] ?? ''); ?></span></td>
                                            <!-- KEPUTUSAN GRED MENJADI MINI TABLE BERSAIZ KECIL -->
                                            <td class="text-center">
                                                <?= formatGredTable($row['gred_cidb_kewangan'] ?? ''); ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-success text-white"><?= htmlspecialchars(strtoupper($row['perakuan'] ?? '-')); ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-primary text-white"><?= htmlspecialchars($status_p); ?></span>
                                            </td>
                                            <td class="text-center"><span class="text-secondary small"><?= htmlspecialchars($row['tarikh_hantar'] ?? '-'); ?></span></td>
                                            <td class="text-center">
                                                <?php 
                                                    if ($status_u == 'Layak') {
                                                        echo '<span class="badge-status status-layak"><i class="fa-solid fa-circle-check"></i> Layak</span>';
                                                    } elseif ($status_u == 'Pending') {
                                                        echo '<span class="badge-status status-pending"><i class="fa-solid fa-clock"></i> Pending</span>';
                                                    } else {
                                                        echo '<span class="badge-status status-gagal"><i class="fa-solid fa-circle-xmark"></i> Tidak Layak</span>';
                                                    }
                                                ?>
                                            </td>

                                            <td class="text-center">
                                                <?php 
                                                    $st_bayar = strtolower($row['status_pembayaran'] ?? 'belum bayar');
                                                    if ($st_bayar == 'sudah bayar') {
                                                        echo '<span class="badge bg-success text-white px-2 py-1"><i class="fa-solid fa-check me-1"></i> Sudah Bayar</span>';
                                                    } else {
                                                        echo '<span class="badge bg-secondary text-white px-2 py-1"><i class="fa-solid fa-xmark me-1"></i> Belum Bayar</span>';
                                                    }
                                                ?>
                                            </td>

                                            <!-- COLUMN BARU: ID / KATEGORI -->
                                            <td class="text-center">
                                                <?php if (!empty($row['id_kontraktor_auto'])): ?>
                                                    <span class="badge bg-dark font-monospace"><?= htmlspecialchars($row['id_kontraktor_auto']); ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($row['nama_kategori'])): ?>
                                                    <div class="small text-secondary fw-bold mt-1"><?= htmlspecialchars($row['nama_kategori']); ?></div>
                                                <?php endif; ?>
                                                <?php if (empty($row['id_kontraktor_auto']) && empty($row['nama_kategori'])): ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>

                                            <td class="text-center"><span class="font-monospace fw-bold text-dark"><?= htmlspecialchars($row['no_bil_transaksi'] ?? '-'); ?></span></td>
                                            <td class="text-center">
                                                <?php if (!empty($row['fail_bil_undi'])): ?>
                                                    <a href="uploads/<?= htmlspecialchars($row['fail_bil_undi']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <i class="fa-solid fa-file-invoice me-1"></i> Bil
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>

                                            <td class="text-center">
                                                <?php if (!empty($row['fail_resit_undi'])): ?>
                                                    <a href="uploads/<?= htmlspecialchars($row['fail_resit_undi']); ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                                        <i class="fa-solid fa-receipt me-1"></i> Resit
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>

                                            <td class="text-center screen-only">
                                                <div class="d-flex gap-1 justify-content-center">
                                                    <!-- BUTTON KAWALAN AKSES KONTRAKTOR -->
                                                    <?php if ($akses_undi == 1): ?>
                                                        <a href="senarai_kontraktor_undi.php?action=toggle_access&id=<?= $row['id']; ?>" 
                                                           class="btn btn-sm btn-warning text-dark" 
                                                           title="Sekat Akses Pendaftaran Undi"
                                                           onclick="return confirm('Adakah anda pasti mahu SEKAT syarikat ini daripada mengakses borang kontraktor_daftar_undi.php?');">
                                                            <i class="fa-solid fa-user-slash"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="senarai_kontraktor_undi.php?action=toggle_access&id=<?= $row['id']; ?>" 
                                                           class="btn btn-sm btn-success" 
                                                           title="Buka Akses Pendaftaran Undi"
                                                           onclick="return confirm('Adakah anda pasti mahu BUKA AKSES borang kontraktor_daftar_undi.php untuk syarikat ini?');">
                                                            <i class="fa-solid fa-user-check"></i>
                                                        </a>
                                                    <?php endif; ?>

                                                    <a href="senarai_kontraktor_undi.php?edit_id=<?= $row['id']; ?>#borangEditSeksyen" class="btn btn-sm btn-outline-primary" title="Kemaskini Maklumat">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </a>
                                                    <button class="btn btn-sm btn-outline-danger btn-delete" 
                                                            data-id="<?= $row['id']; ?>" 
                                                            data-nama="<?= htmlspecialchars($row['nama_syarikat'] ?? ''); ?>"
                                                            title="Padam Rekod Undi">
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="16" class="text-center py-4 text-muted">
                                            <i class="fa-solid fa-magnifying-glass fs-4 d-block mb-2 text-secondary opacity-50"></i>
                                            Tiada rekod pendaftaran undi dijumpai mengikut carian / tapisan anda.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="text-center mt-4 screen-only d-flex justify-content-center gap-3">
                        <button onclick="cetakTapis('layak')" class="btn-print-premium btn-layak">
                            <i class="fa-solid fa-print"></i>
                            <span>Cetak Senarai Layak Undi</span>
                        </button>
                        <button onclick="cetakTapis('gagal')" class="btn-print-premium btn-gagal">
                            <i class="fa-solid fa-print"></i>
                            <span>Cetak Senarai Tidak Layak</span>
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
        function cetakTapis(jenis) {
            const tjkCetak = document.getElementById('tajukCetakanDinamik');
            const subTjk = document.getElementById('subTajukCetak');
            
            if(subTjk) {
                if(jenis === 'layak') {
                    subTjk.textContent = "SENARAI KONTRAKTOR LAYAK UNDI (JABATAN KEJURUTERAAN)";
                } else if(jenis === 'gagal') {
                    subTjk.textContent = "SENARAI KONTRAKTOR TIDAK LAYAK UNDI (JABATAN KEJURUTERAAN)";
                } else {
                    subTjk.textContent = "SENARAI PENUH KONTRAKTOR BERDAFTAR UNDI (JABATAN KEJURUTERAAN)";
                }
            }
            
            if(tjkCetak) tjkCetak.classList.remove('d-none');
            
            const rows = document.querySelectorAll('table.table-mdbg tbody tr');
            let counter = 1;
            
            rows.forEach(row => {
                let matches = false;
                let statusCell = row.cells[9];
                
                // Sekiranya tiada sel (contohnya baris 'Tiada rekod')
                if (!statusCell) return;

                let statusText = statusCell.innerText ? statusCell.innerText.toUpperCase() : '';
                
                if (jenis === 'layak') {
                    if (statusText.includes('LAYAK') && !statusText.includes('TIDAK')) {
                        matches = true;
                    }
                } else if (jenis === 'gagal') {
                    if (statusText.includes('TIDAK LAYAK')) {
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

        // PADAM REKOD
        document.addEventListener('click', function(e) {
            const btnDelete = e.target.closest('.btn-delete');
            if (btnDelete) {
                e.preventDefault();
                const id = btnDelete.getAttribute('data-id');
                const nama = btnDelete.getAttribute('data-nama');
                
                Swal.fire({
                    title: 'Adakah anda pasti?',
                    text: `Anda akan memadam rekod undi bagi syarikat "${nama}" secara kekal!`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, Padamkan!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = `senarai_kontraktor_undi.php?action=delete&id=${id}`;
                    }
                });
            }
        });

        // POPUP EDIT KEMASKINI
        <?php if ($edit_data): ?>
        Swal.fire({
            title: 'Kemas Kini Undian Syarikat',
            html: `
                <form id="popupEditForm" action="senarai_kontraktor_undi.php" method="POST" class="px-1 text-start">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= $edit_data['id']; ?>">
                    
                    <div class="row g-3" style="font-size: 0.88rem; max-height: 460px; overflow-y: auto; padding-right: 8px;">
                        <div class="col-12">
                            <label class="form-label fw-bold text-dark mb-1 small text-uppercase">Nama Syarikat / Perniagaan</label>
                            <input type="text" class="form-control text-uppercase bg-light shadow-sm" style="border-radius: 8px; padding: 0.65rem 0.85rem;" value="<?= htmlspecialchars($edit_data['nama_syarikat'] ?? '', ENT_QUOTES); ?>" readonly>
                        </div>

                        <?php if (!empty($edit_data['id_kontraktor_auto'])): ?>
                        <div class="col-12">
                            <label class="form-label fw-bold text-dark mb-1 small text-uppercase">ID Kontraktor (Jana Otomatik)</label>
                            <input type="text" class="form-control font-monospace bg-light shadow-sm fw-bold text-success" style="border-radius: 8px; padding: 0.65rem 0.85rem;" value="<?= htmlspecialchars($edit_data['id_kontraktor_auto'], ENT_QUOTES); ?>" readonly>
                        </div>
                        <?php endif; ?>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark mb-1 small text-uppercase">Status Permohonan</label>
                            <select name="status_permohonan" class="form-select bg-light shadow-sm" style="border-radius: 8px; padding: 0.65rem 0.85rem;">
                                <option value="Baru" <?= ($edit_data['status_permohonan'] ?? '') == 'Baru' ? 'selected' : ''; ?>>🟢 Baru</option>
                                <option value="Menunggu Semakan" <?= ($edit_data['status_permohonan'] ?? '') == 'Menunggu Semakan' ? 'selected' : ''; ?>>🟡 Menunggu Semakan</option>
                                <option value="Lulus" <?= ($edit_data['status_permohonan'] ?? '') == 'Lulus' ? 'selected' : ''; ?>>🔵 Lulus</option>
                                <option value="Gagal" <?= ($edit_data['status_permohonan'] ?? '') == 'Gagal' ? 'selected' : ''; ?>>🔴 Gagal</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark mb-1 small text-uppercase">Status Kelayakan Undi</label>
                            <select name="status_undi" class="form-select bg-light shadow-sm" style="border-radius: 8px; padding: 0.65rem 0.85rem;">
                                <option value="Pending" <?= ($edit_data['status_undi'] ?? '') == 'Pending' ? 'selected' : ''; ?>>⏳ Pending</option>
                                <option value="Layak" <?= ($edit_data['status_undi'] ?? '') == 'Layak' ? 'selected' : ''; ?>>✅ Layak</option>
                                <option value="Tidak Layak" <?= ($edit_data['status_undi'] ?? '') == 'Tidak Layak' ? 'selected' : ''; ?>>❌ Tidak Layak</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark mb-1 small text-uppercase">Status Pembayaran</label>
                            <select name="status_pembayaran" class="form-select bg-light shadow-sm" style="border-radius: 8px; padding: 0.65rem 0.85rem;">
                                <option value="belum bayar" <?= (strtolower($edit_data['status_pembayaran'] ?? '') == 'belum bayar') ? 'selected' : ''; ?>>🔴 Belum Bayar</option>
                                <option value="sudah bayar" <?= (strtolower($edit_data['status_pembayaran'] ?? '') == 'sudah bayar') ? 'selected' : ''; ?>>🟢 Sudah Bayar</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark mb-1 small text-uppercase">No. Bil Transaksi</label>
                            <input type="text" name="no_bil_transaksi" class="form-control font-monospace bg-light shadow-sm" style="border-radius: 8px; padding: 0.65rem 0.85rem;" value="<?= htmlspecialchars($edit_data['no_bil_transaksi'] ?? '', ENT_QUOTES); ?>">
                        </div>
                    </div>
                </form>
            `,
            showCancelButton: true,
            confirmButtonText: 'Simpan Perubahan',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#8D5B4C',
            cancelButtonColor: '#64748b',
            width: '680px',
            allowOutsideClick: false,
            preConfirm: () => {
                const form = document.getElementById('popupEditForm');
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return false;
                }
                form.submit();
            }
        }).then((result) => {
            if (result.dismiss) {
                window.location.href = 'senarai_kontraktor_undi.php';
            }
        });
        <?php endif; ?>
    </script>
</body>
</html> 