<?php
include 'db.php';

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$mesej = "";

if (isset($_POST['register'])) {
    
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Permintaan tidak sah (CSRF Validation Failed). Sila segar semula halaman.");
    }

    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
    $secret_key = '6Ld-BlwtAAAAAHZ5e-cmH92jtxuaXTJSmMvYL1iZ'; 

    if (empty($recaptcha_response)) {
        $mesej = "<div class='alert alert-danger p-3 small d-flex align-items-center gap-2 border-0 rounded-3 shadow-sm mb-4' style='background-color: #fef2f2; color: #991b1b;'>
                    <i class='fa-solid fa-circle-exclamation fs-5'></i>
                    <div>Sila tandakan pengesahan CAPTCHA 'I'm not a robot' terlebih dahulu.</div>
                  </div>";
    } else {
        $verify_url = "https://www.google.com/recaptcha/api/siteverify";
        $response = @file_get_contents($verify_url . '?secret=' . $secret_key . '&response=' . $recaptcha_response);
        $response_data = json_decode($response);

        if (!$response_data || !$response_data->success) {
            $mesej = "<div class='alert alert-danger p-3 small d-flex align-items-center gap-2 border-0 rounded-3 shadow-sm mb-4' style='background-color: #fef2f2; color: #991b1b;'>
                        <i class='fa-solid fa-circle-exclamation fs-5'></i>
                        <div>Pengesahan CAPTCHA tidak sah. Sila cuba lagi.</div>
                      </div>";
        } else {
            $jenis_akaun = 'Syarikat';
            $no_pendaftaran = trim($_POST['no_pendaftaran']);
            $ic = trim($_POST['ic']);
            $nama_penuh = mb_strtoupper(trim($_POST['nama_penuh']), 'UTF-8');
            $email = trim($_POST['email']);
            $no_tel = trim($_POST['no_tel']);
            $password = trim($_POST['password']);
            $confirm_password = trim($_POST['confirm_password']);

            // --- SERVER-SIDE VALIDATION ---
            if (!preg_match('/^[A-Z0-9]{10}$/', $no_pendaftaran)) {
                $mesej = "<div class='alert alert-danger p-3 small d-flex align-items-center gap-2 border-0 rounded-3 shadow-sm mb-4' style='background-color: #fef2f2; color: #991b1b;'>
                            <i class='fa-solid fa-circle-exclamation fs-5'></i>
                            <div>Ralat: No. Pendaftaran Syarikat mestilah tepat <strong>10 aksara/digit</strong> (Huruf Besar sahaja, tanpa ruang atau simbol)!</div>
                        </div>";
            }

            // Validation No. IC (Optional - hanya valid jika diisi)
            if (!empty($ic) && !preg_match('/^[0-9]{12}$/', $ic)) {
                $mesej = "<div class='alert alert-danger p-3 small d-flex align-items-center gap-2 border-0 rounded-3 shadow-sm mb-4' style='background-color: #fef2f2; color: #991b1b;'>
                            <i class='fa-solid fa-circle-exclamation fs-5'></i>
                            <div>Ralat: No. IC mestilah mengandungi <strong>tepat 12 digit nombor sahaja</strong> (tanpa sempang)!</div>
                        </div>";
            }

            // Validation No Telefon: Numeric Only
            if (empty($mesej) && !preg_match('/^[0-9]+$/', $no_tel)) {
                $mesej = "<div class='alert alert-danger p-3 small d-flex align-items-center gap-2 border-0 rounded-3 shadow-sm mb-4' style='background-color: #fef2f2; color: #991b1b;'>
                            <i class='fa-solid fa-circle-exclamation fs-5'></i>
                            <div>Ralat: No. Telefon mestilah mengandungi <strong>nombor sahaja</strong>!</div>
                          </div>";
            }

            if (empty($mesej)) {
                if ($password !== $confirm_password) {
                    $mesej = "<div class='alert alert-danger p-3 small d-flex align-items-center gap-2 border-0 rounded-3 shadow-sm mb-4' style='background-color: #fef2f2; color: #991b1b;'>
                                <i class='fa-solid fa-circle-exclamation fs-5'></i>
                                <div>Ralat: Pengesahan kata laluan tidak sepadan!</div>
                              </div>";
                } elseif (strlen($password) < 6) {
                    $mesej = "<div class='alert alert-danger p-3 small d-flex align-items-center gap-2 border-0 rounded-3 shadow-sm mb-4' style='background-color: #fef2f2; color: #991b1b;'>
                                <i class='fa-solid fa-circle-exclamation fs-5'></i>
                                <div>Ralat: Kata laluan mestilah sekurang-kurangnya <strong>6 karakter</strong>!</div>
                              </div>";
                } else {
                    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
                    $stmt->bind_param("s", $no_pendaftaran);
                    $stmt->execute();
                    $stmt->store_result();
                    
                    if ($stmt->num_rows > 0) {
                        $safe_id = htmlspecialchars($no_pendaftaran, ENT_QUOTES, 'UTF-8');
                        $mesej = "<div class='alert alert-danger p-3 small d-flex align-items-center gap-2 border-0 rounded-3 shadow-sm mb-4' style='background-color: #fef2f2; color: #991b1b;'>
                                    <i class='fa-solid fa-circle-exclamation fs-5'></i>
                                    <div>Ralat: No. ID / Pendaftaran <strong>$safe_id</strong> sudah berdaftar!</div>
                                  </div>";
                    } else {
                        $stmt->close();
                        
                        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                        $role = 'kontraktor';

                        $stmt_insert = $conn->prepare("INSERT INTO users (username, password, role, jenis_akaun, ic, nama_penuh, email, no_tel) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt_insert->bind_param("ssssssss", $no_pendaftaran, $hashed_password, $role, $jenis_akaun, $ic, $nama_penuh, $email, $no_tel);
                        
                        if ($stmt_insert->execute()) {
                            $mesej = "<div class='alert alert-success p-3 small d-flex align-items-center gap-2 border-0 rounded-3 shadow-sm mb-4' style='background-color: #f0fdf4; color: #166534;'>
                                        <i class='fa-solid fa-circle-check fs-5'></i>
                                        <div>Pendaftaran berjaya! Anda kini boleh <a href='index.php' class='fw-bold' style='color: #166534;'>Log Masuk di sini</a>.</div>
                                      </div>";
                        } else {
                            $mesej = "<div class='alert alert-danger p-3 small border-0 rounded-3 mb-4'>Ralat Sistem semasa pendaftaran.</div>";
                        }
                        $stmt_insert->close();
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akaun Baharu - Portal MDBG</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    
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
            min-height: 100vh;
            margin: 0;
        }

        .login-wrapper {
            min-height: 100vh;
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
            padding: 40px 60px;
            position: relative;
            box-shadow: -10px 0 30px rgba(0,0,0,0.02);
        }

        .form-container {
            width: 100%;
            max-width: 440px;
        }

        .welcome-header h2 {
            font-weight: 700;
            color: #0f172a;
            font-size: 1.8rem;
            letter-spacing: -0.5px;
        }

        .custom-form-control-pill {
            width: 100%;
            padding: 13px 22px;
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 50px;
            font-size: 0.9rem;
            color: #1e293b;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }

        .custom-form-control-pill:focus {
            background-color: #fff;
            border-color: var(--mdbg-maroon);
            box-shadow: 0 0 0 3px rgba(114, 47, 55, 0.15);
            outline: none;
        }

        .uppercase-input {
            text-transform: uppercase;
        }

        .input-group-pill {
            position: relative;
        }

        .input-group-pill .suffix-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            cursor: pointer;
            z-index: 5;
        }

        .radio-account-group {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 28px;
            margin-top: 6px;
            margin-bottom: 20px;
        }

        .radio-account-group .form-check {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
            padding: 0;
        }

        .radio-account-group .form-check-input {
            width: 18px;
            height: 18px;
            margin: 0;
            cursor: pointer;
            border: 1.5px solid #cbd5e1;
        }

        .radio-account-group .form-check-input:checked {
            background-color: var(--mdbg-maroon);
            border-color: var(--mdbg-maroon);
        }

        .radio-account-group label {
            font-weight: 600;
            color: #334155;
            font-size: 0.95rem;
            cursor: pointer;
            user-select: none;
        }

        .btn-mdbg-primary {
            background: linear-gradient(135deg, var(--mdbg-maroon) 0%, var(--mdbg-maroon-dark) 100%);
            color: white;
            border: none;
            padding: 14px;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 50px;
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
                <p class="text-white-50 lead fs-6">Urusan penyerahan borang permohonan dan pelaporan rasmi terimaan hasil dalam satu portal pengurusan selamat ekontraktor.</p>
            </div>
            
            <div class="brand-footer-text">
                <small class="opacity-50">Sistem Pengurusan Kerajaan Majlis Daerah Batu Gajah © 2026</small>
            </div>
        </div>

        <div class="form-section">
            <div class="form-container">
                
                <div class="welcome-header text-center mb-3">
                    <h2>Daftar Akaun Baharu</h2>
                    <p class="text-muted small">Cipta identiti log masuk untuk memulakan permohonan kontraktor anda.</p>
                </div>

                <?= $mesej; ?>

                <form id="registerForm" method="POST" action="register.php">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">

                    <!-- Input 1: No. Pendaftaran Syarikat -->
                    <div class="mb-3">
                        <div id="labelContoh" class="text-start text-secondary mb-1" style="font-size: 0.8rem; padding-left: 22px;">Contoh : IPXXXXXXXX tanpa (-)</div>
                        <input type="text" name="no_pendaftaran" id="inputNoPendaftaran" class="custom-form-control-pill uppercase-input" placeholder="No. Pendaftaran Syarikat" maxlength="10" minlength="10" required autocomplete="off">
                    </div>

                    <!-- Input 2: No. IC Pemohon (12 Digit) -->
                    <div class="mb-3">
                        <input type="text" name="ic" id="inputIc" class="custom-form-control-pill" placeholder="No. IC (cth: 900101015555)" maxlength="12" minlength="12" autocomplete="off">
                    </div>

                    <!-- Input 3: Nama Penuh Pemohon -->
                    <div class="mb-3">
                        <input type="text" name="nama_penuh" id="inputNamaPenuh" class="custom-form-control-pill uppercase-input" placeholder="Nama Penuh Pemohon" required autocomplete="off">
                    </div>

                    <!-- Input 4: E-mel -->
                    <div class="mb-3">
                        <input type="email" name="email" class="custom-form-control-pill" placeholder="E-mel" required autocomplete="off">
                    </div>

                    <!-- Input 5: No. Telefon -->
                    <div class="mb-3">
                        <input type="text" name="no_tel" id="inputNoTel" class="custom-form-control-pill" placeholder="No. Telefon" required autocomplete="off">
                    </div>

                    <!-- Input 6: Kata Laluan -->
                    <div class="mb-2 input-group-pill">
                        <input type="password" name="password" id="passwordField" class="custom-form-control-pill" placeholder="Kata Laluan" minlength="6" required>
                        <i class="fa-solid fa-eye-slash suffix-icon" id="togglePasswordIcon"></i>
                    </div>
                    <div class="text-muted text-center mb-3" style="font-size: 0.75rem;">
                        Minimum 8 aksara, kombinasi huruf kecil, huruf besar, aksara khas dan nombor.
                    </div>

                    <!-- Input 7: Pengesahan Kata Laluan -->
                    <div class="mb-3">
                        <input type="password" name="confirm_password" id="confirmPasswordField" class="custom-form-control-pill" placeholder="Pengesahan Kata Laluan" required>
                    </div>

                    <!-- Widget Google reCAPTCHA v2 -->
                    <div class="mb-4 d-flex justify-content-center">
                        <div class="g-recaptcha" data-sitekey="6Ld-BlwtAAAAADpRfSoS36Jji2dsBpJlifTDV03X"></div>
                    </div>

                    <!-- Button Hantar -->
                    <button type="submit" name="register" class="btn btn-mdbg-primary w-100 py-3 mb-3">
                        <i class="fa-solid fa-user-plus me-2"></i> DAFTAR AKAUN KONTRAKTOR
                    </button>

                    <div class="text-center">
                        <span class="text-muted small">Sudah mempunyai akaun berdaftar? </span>
                        <a href="index.php" class="link-login-custom small">Log Masuk Di Sini</a>
                    </div>
                </form>

                <div class="text-center mt-4 d-none d-lg-block">
                    <span class="text-muted" style="font-size: 0.8rem;">Hak Cipta Terpelihara Majlis Daerah Batu Gajah © 2026</span>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
    const passwordField = document.getElementById('passwordField');
    const togglePasswordIcon = document.getElementById('togglePasswordIcon');
    const inputNoPendaftaran = document.getElementById('inputNoPendaftaran');
    const inputIc = document.getElementById('inputIc');
    const inputNamaPenuh = document.getElementById('inputNamaPenuh');
    const inputNoTel = document.getElementById('inputNoTel');

    // Realtime Input Filtering Logic: Syarikat (Huruf Besar, Abjad & Nombor Sahaja, Max 10 Aksara)
    inputNoPendaftaran.addEventListener('input', function() {
        this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 10);
    });

    // IC: Nombor sahaja & maksimum 12 digit (benarkan kosong)
    inputIc.addEventListener('input', function() {
        if (this.value !== '') {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 12);
        }
    });

    // Nama Penuh: Huruf besar sahaja
    inputNamaPenuh.addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });

    // No Tel: Nombor sahaja
    inputNoTel.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });

    // Toggle Kelihatan Kata Laluan
    togglePasswordIcon.addEventListener('click', function () {
        const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordField.setAttribute('type', type);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });

    // Form Submit Validation
    document.getElementById('registerForm').addEventListener('submit', function(e) {
        // Semakan CAPTCHA
        var response = grecaptcha.getResponse();
        if (response.length === 0) {
            e.preventDefault();
            alert("Sila tandakan kotak 'I'm not a robot' terlebih dahulu!");
            return false;
        }

        // Semakan Panjang No. Pendaftaran Syarikat (mestilah tepat 10 aksara)
        if (inputNoPendaftaran.value.length !== 10) {
            e.preventDefault();
            alert("No. Pendaftaran Syarikat mestilah tepat 10 aksara/digit!");
            inputNoPendaftaran.focus();
            return false;
        }

        // Semakan Panjang IC (Optional - jika diisi, mestilah 12 digit)
        if (inputIc.value.length > 0 && inputIc.value.length !== 12) {
            e.preventDefault();
            alert("No. IC mestilah tepat 12 digit nombor jika diisi!");
            inputIc.focus();
            return false;
        }
    });
</script>
</body>
</html>