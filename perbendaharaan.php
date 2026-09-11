<?php
session_start();

// 1. Sekat daripada menyimpan cache halaman ini
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'perbendaharaan') { header("Location: index.php"); exit(); }
include 'db.php';

$count_pay_daftar = $conn->query("SELECT COUNT(*) as total FROM kontraktor_profil WHERE status_borang='Lengkap' AND status_bayaran_daftar='Belum Bayar'")->fetch_assoc()['total'];
$count_pay_undi = $conn->query("SELECT COUNT(*) as total FROM daftar_undi WHERE status_layak='Layak' AND status_resit_undi='Belum Kemaskini'")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Perbendaharaan MDBG</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-treasury: #0f766e; /* Teal Tua */
            --primary-dark: #115e59;
            --primary-light: #f0fdfa;
            --sidebar-width: 280px;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
        }

        body {
            background-color: #f8fafc;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            overflow-x: hidden;
            margin: 0;
            padding: 0;
        }
        
        /* Fixed Navbar */
        .mdbg-navbar {
            background: linear-gradient(135deg, #0f766e 0%, #115e59 40%, #134e4a 100%);
            color: white;
            padding: 0 30px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 75px;
            z-index: 1030;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .mdbg-logo-navbar {
            width: 50px;
            height: 50px;
            object-fit: contain;
            background-color: #ffffff;
            padding: 3px;
            border-radius: 50%;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            flex-shrink: 0;
        }

        .mdbg-brand {
            font-weight: 700;
            font-size: 1.15rem;
            letter-spacing: 0.5px;
            line-height: 1.2;
        }
        .mdbg-subtext {
            font-size: 0.8rem;
            opacity: 0.85;
            font-weight: 400;
            margin-top: 2px;
        }
        
        /* Controls */
        .btn-toggle-sidebar {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: white;
            padding: 8px 14px;
            border-radius: 6px;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .btn-toggle-sidebar:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
        }
        .btn-logout {
            background-color: #ef4444;
            color: white;
            border: none;
            font-weight: 600;
            padding: 8px 16px;
            font-size: 0.85rem;
            border-radius: 6px;
            transition: all 0.2s ease;
            text-decoration: none;
            box-shadow: 0 2px 4px rgba(239, 68, 68, 0.1);
        }
        .btn-logout:hover {
            background-color: #dc2626;
            color: white;
        }

        /* Layout Container Split */
        .wrapper {
            display: flex;
            margin-top: 75px;
            min-height: calc(100vh - 75px);
        }

        /* Sidebar Container */
        .sidebar-container {
            width: var(--sidebar-width);
            background-color: #0f172a;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            flex-shrink: 0;
            box-shadow: 4px 0 15px rgba(0,0,0,0.02);
            z-index: 1010;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        /* Kelas Sembunyi Sidebar yang Dibetulkan */
        .sidebar-container.collapsed {
            margin-left: calc(-1 * var(--sidebar-width));
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .sidebar-category-title {
            padding: 12px 25px 6px 25px;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            font-weight: 700;
            color: #475569;
        }
        
        .sidebar-menu .nav-link-item {
            padding: 14px 25px;
            font-weight: 500;
            color: #94a3b8;
            font-size: 0.92rem;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            text-decoration: none;
            border-left: 4px solid transparent;
        }
        
        .sidebar-menu .nav-link-item:hover {
            background-color: #1e293b;
            color: #f8fafc;
        }
        .sidebar-menu .nav-link-item.active {
            background-color: #1e293b !important;
            color: #ffffff !important;
            font-weight: 600;
            border-left-color: var(--primary-treasury);
        }
        .sidebar-menu .nav-link-item i {
            font-size: 1.1rem;
            width: 28px;
        }

        /* Main Content Workspace */
        .main-content-container {
            flex-grow: 1;
            padding: 20px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background-color: #f8fafc;
            max-width: 100%;
            box-sizing: border-box;
        }

        @media (min-width: 768px) {
            .main-content-container {
                padding: 30px;
            }
        }

        /* Dashboard Container Boxes */
        .status-card-container {
            background-color: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            padding: 24px;
            text-align: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
            width: 100%;
        }

        .status-header {
            color: #0f172a;
            font-weight: 700;
            font-size: 1.4rem;
            letter-spacing: -0.3px;
        }
        
        /* Flexbox Wrapper untuk Kad Indikator Kewangan */
        .indicator-flex-container {
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
            justify-content: center;
            width: 100%;
            margin-top: 15px;
        }
        
        .indicator-box {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 30px 24px;
            text-align: center;
            background-color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.2s, box-shadow 0.2s;
            flex: 1 1 300px;
            max-width: 450px;
            min-width: 280px;
            box-sizing: border-box;
        }
        
        .indicator-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 20px -3px rgba(15, 118, 110, 0.08);
            border-color: #ccfbf1;
        }
        
        .indicator-title {
            font-size: 0.88rem;
            font-weight: 700;
            color: #334155;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .indicator-subtitle {
            font-size: 0.78rem;
            color: #64748b;
            margin-bottom: 15px;
        }

        .btn-action-treasury {
            background-color: var(--primary-treasury);
            color: white;
            font-weight: 600;
            padding: 10px 20px;
            font-size: 0.88rem;
            border-radius: 8px;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
            border: none;
            box-shadow: 0 2px 4px rgba(15, 118, 110, 0.15);
        }
        .btn-action-treasury:hover {
            background-color: var(--primary-dark);
            color: white;
            box-shadow: 0 4px 12px rgba(15, 118, 110, 0.25);
        }

        @media (max-width: 768px) {
            .sidebar-container {
                position: fixed;
                left: 0;
                top: 75px;
                height: calc(100vh - 75px);
                z-index: 1000;
            }
            .sidebar-container.collapsed {
                transform: translateX(-100%);
                margin-left: 0;
            }
        }
    </style>
</head>
<body>

    <div class="mdbg-navbar d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <button class="btn-toggle-sidebar" id="sidebarToggle" type="button" title="Sembunyikan/Paparkan Menu">
                <i class="fa-solid fa-bars fs-5"></i>
            </button>
            
            <img src="logo_mdbg.png" alt="Logo MDBG" class="mdbg-logo-navbar">
            
            <div>
                <div class="mdbg-brand">JABATAN PERBENDAHARAAN</div>
                <div class="mdbg-subtext d-none d-md-block">Modul Penyemak Terimaan Hasil & Pengesahan Bayaran Pembekal MDBG</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <a href="logout.php" class="btn-logout d-flex align-items-center"><i class="fa-solid fa-right-from-bracket me-1"></i> Log Keluar</a>
        </div>
    </div>

    <div class="wrapper">
        
        <div class="sidebar-container" id="sidebarWrapper">
            <div class="sidebar-menu">
                <div class="sidebar-category-title">Menu Utama</div>
                <a href="perbendaharaan.php" class="nav-link-item active">
                    <i class="fa-solid fa-wallet me-2"></i> Dashboard Kewangan
                </a>
                
                <div class="sidebar-category-title">Urusan Terimaan</div>
                <a href="perbendaharaan_sah_bayaran_daftar.php" class="nav-link-item">
                    <i class="fa-solid fa-receipt me-2"></i> Sah Bayaran Daftar (RM52)
                </a>
                <a href="perbendaharaan_sah_bayaran_undi.php" class="nav-link-item">
                    <i class="fa-solid fa-money-check-dollar me-2"></i> Sah Bayaran Sijil Undi
                </a>
                
                <div class="sidebar-category-title">Akaun & Laporan</div>
                <a href="#" class="nav-link-item">
                    <i class="fa-solid fa-print me-2"></i> Penyata Ringkas Hasil
                </a>
            </div>
        </div>

        <div class="main-content-container" id="contentWrapper">
            <div class="container-fluid p-0">
                
                <div class="status-card-container">
                    <h4 class="status-header mb-2">Halaman Ringkasan Tugasan Kewangan</h4>
                    <p class="text-muted small mb-4">Sila buat semakan pada transaksi kemasukan dana pembekal sebelum mengesahkan bil terimaan hasil.</p>
                    <hr class="mt-0 mb-4" style="opacity: 0.08;">
                    
                    <div class="indicator-flex-container">
                        
                        <div class="indicator-box">
                            <div>
                                <div class="indicator-title">Sahkan Bayaran Pendaftaran</div>
                                <div class="indicator-subtitle">Kod Hasil: Bil 21326</div>
                            </div>
                            <div class="my-2">
                                <h1 class="display-3 fw-bold text-teal m-0" style="color: var(--primary-treasury);"><?= $count_pay_daftar; ?></h1>
                                <span class="text-muted small">Kes Belum Disahkan</span>
                            </div>
                            <div class="w-100 mt-3">
                                <a href="perbendaharaan_sah_bayaran_daftar.php" class="btn-action-treasury w-100 text-center">
                                    <i class="fa-solid fa-magnifying-glass-dollar me-1"></i> Sahkan Terimaan RM52
                                </a>
                            </div>
                        </div>

                        <div class="indicator-box">
                            <div>
                                <div class="indicator-title">Sahkan Bayaran Sijil Undi</div>
                                <div class="indicator-subtitle">Kod Hasil: Bil 21355</div>
                            </div>
                            <div class="my-2">
                                <h1 class="display-3 fw-bold text-teal m-0" style="color: var(--primary-treasury);"><?= $count_pay_undi; ?></h1>
                                <span class="text-muted small">Kes Belum Disahkan</span>
                            </div>
                            <div class="w-100 mt-3">
                                <a href="perbendaharaan_sah_bayaran_undi.php" class="btn-action-treasury w-100 text-center">
                                    <i class="fa-solid fa-money-bill-transfer me-1"></i> Sahkan Terimaan Undi
                                </a>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bundle.min.js"></script>

    <script>
        // Logik Butang Sembunyi/Papar Sidebar yang Diperbaiki
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebarWrapper');
            sidebar.classList.toggle('collapsed');
        });

        // Mengesan Halaman Aktif secara Automatik
        document.addEventListener("DOMContentLoaded", function() {
            const currentUrl = window.location.pathname.split("/").pop();
            const menuLinks = document.querySelectorAll(".nav-link-item");
            
            menuLinks.forEach(link => {
                if (link.getAttribute("href") === currentUrl) {
                    document.querySelectorAll(".nav-link-item").forEach(item => item.classList.remove("active"));
                    link.classList.add("active");
                }
            });
        });
    </script>
</body>
</html>