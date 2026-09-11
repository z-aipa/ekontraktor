<?php
include 'db.php';

// Wajib set zon masa tempatan
date_default_timezone_set('Asia/Kuala_Lumpur');

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

session_start();

$mesej = "";
$token = $_GET['token'] ?? '';
$valid_token = false;

// Semak kesahan token
if (!empty($token)) {
    $stmt = $conn->prepare("SELECT id, reset_expires FROM users WHERE reset_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        
        // Tukar kedua-dua waktu ke format UNIX Timestamp untuk semakan tepat
        $current_time = time();
        $expires_time = strtotime($user_data['reset_expires']);
        
        if ($expires_time >= $current_time) {
            $valid_token = true;
        } else {
            $mesej = "<div class='alert alert-danger p-3 small d-flex align-items-center gap-2 border-0 rounded-3 shadow-sm mb-4' style='background-color: #fef2f2; color: #991b1b;'>
                        <i class='fa-solid fa-circle-exclamation fs-5'></i>
                        <div>Pautan reset telah luput. Sila buat permohonan semula.</div>
                      </div>";
        }
    } else {
        $mesej = "<div class='alert alert-danger p-3 small d-flex align-items-center gap-2 border-0 rounded-3 shadow-sm mb-4' style='background-color: #fef2f2; color: #991b1b;'>
                    <i class='fa-solid fa-circle-exclamation fs-5'></i>
                    <div>Pautan reset tidak sah. Sila buat permohonan semula.</div>
                  </div>";
    }
} else {
    $mesej = "<div class='alert alert-danger p-3 small d-flex align-items-center gap-2 border-0 rounded-3 shadow-sm mb-4' style='background-color: #fef2f2; color: #991b1b;'>
                <i class='fa-solid fa-circle-exclamation fs-5'></i>
                <div>Token pengesahan tidak dijumpai.</div>
              </div>";
}

if ($valid_token && isset($_POST['update_password'])) {
    $new_password = trim($_POST['password']);

    if (strlen($new_password) < 6) {
        $mesej = "<div class='alert alert-danger p-3 small d-flex align-items-center gap-2 border-0 rounded-3 shadow-sm mb-4' style='background-color: #fef2f2; color: #991b1b;'>
                    <i class='fa-solid fa-circle-exclamation fs-5'></i>
                    <div>Kata laluan mestilah sekurang-kurangnya <strong>6 karakter</strong>!</div>
                  </div>";
    } else {
        // Enkripsi Kata Laluan Baharu
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

        // Kemaskini Password & Padam Token supaya tidak boleh diguna semula
        $stmt_update = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE reset_token = ?");
        $stmt_update->bind_param("ss", $hashed_password, $token);

        if ($stmt_update->execute()) {
            $valid_token = false;
            $mesej = "<div class='alert alert-success p-3 small d-flex align-items-center gap-2 border-0 rounded-3 shadow-sm mb-4' style='background-color: #f0fdf4; color: #166534;'>
                        <i class='fa-solid fa-circle-check fs-5'></i>
                        <div>Kata laluan anda telah berjaya dikemas kini! Anda kini boleh <a href='index.php' class='fw-bold' style='color: #166534;'>Log Masuk di sini</a>.</div>
                      </div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Kata Laluan Baharu - Portal MDBG</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --mdbg-maroon: #722f37;
            --mdbg-maroon-dark: #582228;
            --mdbg-gold: #d4af37;
            --slate-bg: #f8fafc;
        }

        body {
            background-color: var(--slate-bg);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            height: 100vh;
            margin: 0;
            overflow: hidden;
        }

        .login-wrapper {
            height: 100vh;
            display: flex;
        }

        .brand-section {
            background: linear-gradient(135deg, rgba(114, 47, 55, 0.92) 0%, rgba(88, 34, 40, 0.96) 100%), 
                        url('mdbg.jpg') no-repeat center center;
            background-size: cover;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 50px;
            width: 50%;
            position: relative;
        }

        .brand-section::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background-image: radial-gradient(circle at 20% 30%, rgba(212, 175, 55, 0.12) 0%, transparent 60%);
            pointer-events: none;
        }

        .brand-logo-container {
            display: flex;
            align-items: center;
            gap: 15px;
            z-index: 2;
        }

        .logo-img {
            width: 65px;
            height: 65px;
            object-fit: contain;
            background: white;
            padding: 5px;
            border-radius: 50%;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .brand-title-main {
            font-weight: 800;
            font-size: 1.5rem;
            letter-spacing: 0.5px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .brand-subtitle-main {
            font-size: 0.85rem;
            color: var(--mdbg-gold);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .brand-hero-text {
            z-index: 2;
        }

        .brand-hero-text h1 {
            font-weight: 800;
            font-size: 2.6rem;
            line-height: 1.2;
            letter-spacing: -0.5px;
            margin-bottom: 20px;
            text-shadow: 0 2px 8px rgba(0,0,0,0.4);
        }

        .form-section {
            width: 50%;
            background: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 60px;
            position: relative;
            box-shadow: -10px 0 30px rgba(0,0,0,0.02);
        }

        .form-container {
            width: 100%;
            max-width: 420px;
        }

        .welcome-header h2 {
            font-weight: 700;
            color: #0f172a;
            font-size: 1.8rem;
            letter-spacing: -0.5px;
        }

        .custom-input-group {
            position: relative;
            margin-bottom: 25px;
        }

        .custom-input-group .prefix-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1.05rem;
            z-index: 5;
        }

        .custom-form-control {
            width: 100%;
            padding: 14px 40px 14px 45px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.95rem;
            color: #334155;
            box-sizing: border-box;
        }

        .custom-form-control:focus {
            background-color: #fff;
            border-color: var(--mdbg-maroon);
            box-shadow: 0 0 0 4px rgba(114, 47, 55, 0.1);
            outline: none;
        }

        .btn-mdbg-primary {
            background: linear-gradient(135deg, var(--mdbg-maroon) 0%, var(--mdbg-maroon-dark) 100%);
            color: white;
            border: none;
            padding: 14px;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 10px;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(114, 47, 55, 0.2);
            cursor: pointer;
        }

        .btn-mdbg-primary:hover {
            transform: translateY(-1px);
            color: white;
        }

        @media (max-width: 992px) {
            .brand-section { display: none !important; }
            .form-section { width: 100%; padding: 30px; }
        }
    </style>
</head>
<body>

    <div class="login-wrapper">
        <div class="brand-section">
            <div class="brand-logo-container">
                <img src="logo_mdbg.png" alt="Logo MDBG" class="logo-img">
                <div>
                    <div class="brand-title-main">MAJLIS DAERAH</div>
                    <div class="brand-subtitle-main">Batu Gajah (MDBG)</div>
                </div>
            </div>
            
            <div class="brand-hero-text">
                <h1>Sistem Bersepadu Pendaftaran Kontraktor & Undi Dalam Talian</h1>
                <p class="text-white-50 lead fs-6">Urusan penyerahan borang permohonan, semakan kelayakan jawatankuasa kejuruteraan, dan pelaporan rasmi terimaan hasil dalam satu portal pengurusan selamat.</p>
            </div>
            
            <div class="brand-footer-text">
                <small class="opacity-50">Sistem Pengurusan Kerajaan Majlis Daerah Batu Gajah © 2026</small>
            </div>
        </div>

        <div class="form-section">
            <div class="form-container">
                <div class="welcome-header mb-4">
                    <h2>Tetapkan Kata Laluan Baharu</h2>
                    <p class="text-muted small">Sila masukkan kata laluan keselamatan baharu anda di bawah.</p>
                </div>

                <?= $mesej; ?>

                <?php if ($valid_token): ?>
                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-dark mb-2">KATA LALUAN BAHARU</label>
                        <div class="custom-input-group mb-0">
                            <input type="password" name="password" class="custom-form-control" required minlength="6" placeholder="Masukkan kata laluan baharu">
                            <i class="fa-solid fa-lock prefix-icon"></i>
                        </div>
                    </div>
                    
                    <button type="submit" name="update_password" class="btn btn-mdbg-primary w-100 py-3 mb-3">
                        <i class="fa-solid fa-key me-2"></i> KEMASKINI KATA LALUAN
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

</body>
</html>