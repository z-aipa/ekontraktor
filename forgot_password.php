<?php
include 'db.php';

// Import kelas PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Muat pustaka PHPMailer mengikut struktur kejuruteraan_semak_pendaftaran.php
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Wajib set zon masa tempatan
date_default_timezone_set('Asia/Kuala_Lumpur');

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

session_start();

$mesej = "";
$step = 1; // 1 = Minta Email, 2 = Minta PIN & Password Baharu

// BAHAGIAN 1: Permohonan Kod PIN melalui E-mel
if (isset($_POST['request_pin'])) {
    $email = trim($_POST['email']);

    // Semak jika e-mel wujud
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Jana Kod PIN 6-digit rawak
        $pin = sprintf("%06d", mt_rand(100000, 999999));
        
        // Luput dalam masa 15 minit
        $expires = date("Y-m-d H:i:s", strtotime("+15 minutes"));

        // Simpan PIN dalam kolum reset_token
        $stmt_update = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
        $stmt_update->bind_param("sss", $pin, $expires, $email);
        $stmt_update->execute();

        $_SESSION['reset_email'] = $email;

        // --- PROSES HANTAR E-MEL SEBENAR MENGGUNAKAN PHPMAILER ---
        $mail_sent = false;
        $mail = new PHPMailer(true);
        try {
            // Tetapan Pelayan SMTP (Diselaraskan mengikut kejuruteraan_semak_pendaftaran.php)
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';             
            $mail->SMTPAuth   = true;
            $mail->Username   = 'prk.mdbg@mdbg.gov.my';       
            $mail->Password   = 'bgcd cdwt bfup uxeq'; // App Password Gmail
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;

            // Pintas semakan SSL Certificate untuk Localhost / XAMPP
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true
                )
            );

            // Penerima & Pengirim
            $mail->setFrom('prk.mdbg@mdbg.gov.my', 'Portal MDBG');
            $mail->addAddress($email);

            // Kandungan E-mel
            $mail->isHTML(true);
            $mail->Subject = 'Kod PIN Penetapan Semula Kata Laluan - Portal MDBG';
            $mail->Body    = "
                <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                    <h2 style='color: #722f37;'>Permohonan Penetapan Semula Kata Laluan</h2>
                    <p>Salam sejahtera,</p>
                    <p>Permohonan untuk menetapkan semula kata laluan bagi akaun anda telah diterima. Sila gunakan kod PIN pengesahan di bawah:</p>
                    <div style='background: #f8fafc; border: 1px dashed #722f37; padding: 15px; text-align: center; margin: 20px 0;'>
                        <span style='font-size: 28px; font-weight: bold; color: #722f37; letter-spacing: 5px;'>$pin</span>
                    </div>
                    <p>Kod PIN ini sah selama <strong>15 minit</strong>. Jika anda tidak membuat permohonan ini, sila abaikan e-mel ini.</p>
                    <hr style='border: none; border-top: 1px solid #ddd; margin-top: 20px;' />
                    <small style='color: #888;'>Hak Cipta Terpelihara Majlis Daerah Batu Gajah © 2026</small>
                </div>";

            $mail->send();
            $mail_sent = true;
        } catch (Exception $e) {
            $mail_sent = false;
        }

        $step = 2;

        if ($mail_sent) {
            $mesej = "<div class='alert alert-success p-3 small border-0 rounded-3 shadow-sm mb-4' style='background-color: #f0fdf4; color: #166534;'>
                        <div class='d-flex align-items-center gap-2 mb-1'>
                            <i class='fa-solid fa-circle-check fs-5'></i>
                            <strong>Kod PIN Berjaya Dihantar!</strong>
                        </div>
                        <div class='mt-1' style='font-size: 0.85rem;'>
                            Kod PIN pengesahan telah dihantar ke e-mel <strong>" . htmlspecialchars($email) . "</strong>. Sila semak peti masuk (inbox/spam) anda.
                        </div>
                      </div>";
        } else {
            // Cadangan paparan jika e-mel gagal dihantar
            $mesej = "<div class='alert alert-danger p-3 small border-0 rounded-3 shadow-sm mb-4' style='background-color: #fef2f2; color: #991b1b;'>
                        <div class='d-flex align-items-center gap-2 mb-1'>
                            <i class='fa-solid fa-circle-exclamation fs-5'></i>
                            <strong>Gagal Menghantar E-mel!</strong>
                        </div>
                        <div class='mt-1' style='font-size: 0.85rem;'>
                            Kod PIN tidak dapat dihantar ke e-mel <strong>" . htmlspecialchars($email) . "</strong>. Sila semak semula tetapan sambungan SMTP anda.
                        </div>
                      </div>";
        }
    } else {
        $mesej = "<div class='alert alert-danger p-3 small d-flex align-items-center gap-2 border-0 rounded-3 shadow-sm mb-4' style='background-color: #fef2f2; color: #991b1b;'>
                    <i class='fa-solid fa-circle-exclamation fs-5'></i>
                    <div>Alamat e-mel tidak dijumpai dalam rekod sistem.</div>
                  </div>";
    }
}

// BAHAGIAN 2: Pengesahan PIN & Kemaskini Kata Laluan
if (isset($_POST['reset_with_pin'])) {
    $email = $_SESSION['reset_email'] ?? trim($_POST['email']);
    $pin_input = trim($_POST['pin']);
    $new_password = trim($_POST['password']);

    if (strlen($new_password) < 6) {
        $step = 2;
        $mesej = "<div class='alert alert-danger p-3 small d-flex align-items-center gap-2 border-0 rounded-3 shadow-sm mb-4' style='background-color: #fef2f2; color: #991b1b;'>
                    <i class='fa-solid fa-circle-exclamation fs-5'></i>
                    <div>Kata laluan mestilah sekurang-kurangnya <strong>6 karakter</strong>!</div>
                  </div>";
    } else {
        // Semak PIN dan masa luput
        $stmt = $conn->prepare("SELECT id, reset_expires FROM users WHERE email = ? AND reset_token = ?");
        $stmt->bind_param("ss", $email, $pin_input);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
            $current_time = time();
            $expires_time = strtotime($user_data['reset_expires']);

            if ($expires_time >= $current_time) {
                // Kemaskini Kata Laluan & Padam PIN
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                $stmt_update = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE email = ?");
                $stmt_update->bind_param("ss", $hashed_password, $email);
                
                if ($stmt_update->execute()) {
                    unset($_SESSION['reset_email']);
                    $step = 1;
                    $mesej = "<div class='alert alert-success p-3 small d-flex align-items-center gap-2 border-0 rounded-3 shadow-sm mb-4' style='background-color: #f0fdf4; color: #166534;'>
                                <i class='fa-solid fa-circle-check fs-5'></i>
                                <div>Kata laluan berjaya dikemas kini! Anda kini boleh <a href='index.php' class='fw-bold' style='color: #166534;'>Log Masuk di sini</a>.</div>
                              </div>";
                }
            } else {
                $step = 2;
                $mesej = "<div class='alert alert-danger p-3 small d-flex align-items-center gap-2 border-0 rounded-3 shadow-sm mb-4' style='background-color: #fef2f2; color: #991b1b;'>
                            <i class='fa-solid fa-circle-exclamation fs-5'></i>
                            <div>Kod PIN telah luput. Sila minta PIN baharu.</div>
                          </div>";
            }
        } else {
            $step = 2;
            $mesej = "<div class='alert alert-danger p-3 small d-flex align-items-center gap-2 border-0 rounded-3 shadow-sm mb-4' style='background-color: #fef2f2; color: #991b1b;'>
                        <i class='fa-solid fa-circle-exclamation fs-5'></i>
                        <div>Kod PIN tidak sah. Sila semak semula PIN anda.</div>
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
    <title>Lupa Kata Laluan - Portal MDBG</title>
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
            margin-bottom: 20px;
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

        .link-login-custom {
            color: var(--mdbg-maroon);
            font-weight: 700;
            text-decoration: none;
        }

        .pin-input {
            letter-spacing: 6px;
            font-weight: 700;
            font-size: 1.1rem;
            text-align: center;
            padding-left: 15px !important;
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
                    <h2>Lupa Kata Laluan?</h2>
                    <p class="text-muted small">
                        <?= $step === 1 ? 'Masukkan e-mel anda untuk menerima Kod PIN Pengesahan 6-digit.' : 'Masukkan Kod PIN 6-digit dan kata laluan baharu anda.'; ?>
                    </p>
                </div>

                <?= $mesej; ?>

                <?php if ($step === 1): ?>
                <!-- LANGKAH 1: MINTA EMAIL -->
                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-dark mb-2">ALAMAT E-MEL</label>
                        <div class="custom-input-group mb-0">
                            <input type="email" name="email" class="custom-form-control" placeholder="Contoh: nama@syarikat.com" required autocomplete="off">
                            <i class="fa-solid fa-envelope prefix-icon"></i>
                        </div>
                    </div>
                    
                    <button type="submit" name="request_pin" class="btn btn-mdbg-primary w-100 py-3 mb-3">
                        <i class="fa-solid fa-paper-plane me-2"></i> HANTAR KOD PIN
                    </button>
                </form>

                <?php else: ?>
                <!-- LANGKAH 2: MINTA PIN 6-DIGIT & KATA LALUAN BAHARU -->
                <form method="POST">
                    <input type="hidden" name="email" value="<?= htmlspecialchars($_SESSION['reset_email'] ?? ''); ?>">

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark mb-2">KOD PIN (6-DIGIT)</label>
                        <div class="custom-input-group mb-0">
                            <input type="text" name="pin" class="custom-form-control pin-input" maxlength="6" placeholder="000000" required autocomplete="off">
                            <i class="fa-solid fa-key prefix-icon"></i>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-dark mb-2">KATA LALUAN BAHARU</label>
                        <div class="custom-input-group mb-0">
                            <input type="password" name="password" class="custom-form-control" placeholder="Minimum 6 karakter" minlength="6" required>
                            <i class="fa-solid fa-lock prefix-icon"></i>
                        </div>
                    </div>
                    
                    <button type="submit" name="reset_with_pin" class="btn btn-mdbg-primary w-100 py-3 mb-3">
                        <i class="fa-solid fa-shield-halved me-2"></i> TETAPKAN KATA LALUAN
                    </button>
                </form>
                <?php endif; ?>

                <div class="text-center mt-3 pt-3 border-top" style="border-color: #e2e8f0 !important;">
                    <a href="index.php" class="link-login-custom small">
                        <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Log Masuk
                    </a>
                </div>
            </div>
        </div>
    </div>

</body>
</html>