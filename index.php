<?php
include 'db.php';

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

session_start();

// Alihkan pengguna jika sudah log masuk
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] == 'kontraktor') { header("Location: kontraktor.php"); exit(); }
    if ($_SESSION['role'] == 'kejuruteraan') { header("Location: kejuruteraan.php"); exit(); }
    if ($_SESSION['role'] == 'perbendaharaan') { header("Location: perbendaharaan.php"); exit(); }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_POST['login'])) {
    
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Permintaan tidak sah (CSRF Validation Failed). Sila segar semula halaman.");
    }

    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['last_login_attempt'] = time();
    }

    if ($_SESSION['login_attempts'] >= 5 && (time() - $_SESSION['last_login_attempt']) < 300) {
        $baki_masa = ceil((300 - (time() - $_SESSION['last_login_attempt'])) / 60);
        $error = "Terlalu banyak cubaan gagal. Akaun anda dikunci sementara. Sila cuba lagi dalam masa $baki_masa minit.";
    } else {
        if ((time() - $_SESSION['last_login_attempt']) >= 300) {
            $_SESSION['login_attempts'] = 0;
        }

        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        
        // Ambil nilai jenis_akaun dari radio button (individu / syarikat) & padankan format (Individu / Syarikat)
        $jenis_akaun = 'Syarikat';
        $username = strtoupper($username);

        if (!preg_match('/^[A-Z0-9]{10}$/', $username)) {
            $error = "No. Pendaftaran Syarikat mestilah tepat 10 aksara mengandungi huruf besar dan nombor sahaja (tanpa ruang atau simbol)!";
        }

        if (!isset($error)) {
            // Kemaskini SQL: Semak username DAN jenis_akaun supaya jenis akaun yang salah disekat terus
            $stmt = $conn->prepare("SELECT id, username, password, role, jenis_akaun, nama_penuh FROM users WHERE username = ? AND jenis_akaun = ?");
            $stmt->bind_param("ss", $username, $jenis_akaun);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                $password_matches = false;
                if (password_verify($password, $user['password'])) {
                    $password_matches = true;
                } elseif ($password === $user['password']) {
                    $password_matches = true;
                }

                if ($password_matches) {
                    unset($_SESSION['login_attempts']);
                    unset($_SESSION['last_login_attempt']);

                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['jenis_akaun'] = $user['jenis_akaun'];
                    
                    // Simpan nama penuh ke dalam sesi
                    $_SESSION['nama_penuh'] = !empty($user['nama_penuh']) ? $user['nama_penuh'] : $user['username'];

                    if ($user['role'] == 'kontraktor') {
                        header("Location: kontraktor.php");
                        exit();
                    } elseif ($user['role'] == 'kejuruteraan') {
                        header("Location: kejuruteraan.php");
                        exit();
                    } elseif ($user['role'] == 'perbendaharaan') {
                        header("Location: perbendaharaan.php");
                        exit();
                    }
                } else {
                    $_SESSION['login_attempts']++;
                    $_SESSION['last_login_attempt'] = time();
                    $error = "ID Pengguna atau Kata Laluan yang anda masukkan adalah salah!";
                }
            } else {
                $_SESSION['login_attempts']++;
                $_SESSION['last_login_attempt'] = time();
                $error = "ID Pengguna atau Kata Laluan yang anda masukkan adalah salah bagi kategori " . htmlspecialchars($jenis_akaun) . "!";
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Masuk - Sistem Portal Rasmi MDBG</title>
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

        .brand-hero-text p {
            text-shadow: 0 1px 4px rgba(0,0,0,0.3);
        }

        .brand-footer-text {
            z-index: 2;
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

        .login-type-toggle {
            display: flex;
            gap: 20px;
            margin-bottom: 18px;
        }

        .login-type-toggle .form-check-input:checked {
            background-color: var(--mdbg-maroon);
            border-color: var(--mdbg-maroon);
        }

        .login-type-toggle label {
            font-weight: 600;
            color: #334155;
            font-size: 0.9rem;
            cursor: pointer;
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
            transition: color 0.2s;
            font-size: 1.05rem;
            z-index: 5;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            cursor: pointer;
            transition: color 0.2s;
            font-size: 1.05rem;
            z-index: 5;
        }

        .toggle-password:hover {
            color: var(--mdbg-maroon);
        }

        .custom-form-control {
            width: 100%;
            padding: 14px 40px 14px 45px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.95rem;
            color: #334155;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }

        .custom-form-control:focus {
            background-color: #fff;
            border-color: var(--mdbg-maroon);
            box-shadow: 0 0 0 4px rgba(114, 47, 55, 0.1);
            outline: none;
        }

        .custom-form-control:focus ~ .prefix-icon {
            color: var(--mdbg-maroon);
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
            box-shadow: 0 6px 18px rgba(114, 47, 55, 0.3);
            color: white;
        }

        .btn-daftar-custom {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--mdbg-maroon);
            background-color: transparent;
            border: 1.5px solid var(--mdbg-maroon);
            padding: 8px 24px;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 30px;
            text-decoration: none;
            transition: all 0.25s ease-in-out;
        }

        .btn-daftar-custom:hover {
            color: #ffffff !important;
            background-color: var(--mdbg-maroon);
            box-shadow: 0 4px 12px rgba(114, 47, 55, 0.15);
            transform: translateY(-1px);
        }
        
        .btn-daftar-custom:active {
            transform: translateY(0);
        }

        .link-forgot-custom {
            color: var(--mdbg-maroon);
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s;
        }

        .link-forgot-custom:hover {
            color: var(--mdbg-maroon-dark);
            text-decoration: underline;
        }

        @media (max-width: 992px) {
            .brand-section {
                display: none !important;
            }
            .form-section {
                width: 100%;
                padding: 30px;
            }
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
                
                <div class="d-block d-lg-none text-center mb-4">
                    <img src="logo_mdbg.png" alt="Logo MDBG" style="width: 70px; height:70px;">
                    <h5 class="fw-bold text-dark mt-2 mb-0">MAJLIS DAERAH BATU GAJAH</h5>
                </div>

                <div class="welcome-header mb-4">
                    <h2>Selamat Datang</h2>
                    <p class="text-muted small">Sila masukkan identiti akaun berdaftar anda untuk capaian dashboard.</p>
                </div>

                <?php if(isset($error)): ?>
                    <div class="alert alert-danger p-3 small d-flex align-items-center gap-2 border-0 rounded-3 shadow-sm mb-4" style="background-color: #fef2f2; color: #991b1b;">
                        <i class="fa-solid fa-circle-exclamation fs-5"></i>
                        <div><?= htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">

                    <label class="form-label small fw-bold text-dark mb-2" id="usernameLabel">NO. PENDAFTARAN SYARIKAT</label>
                    <div class="custom-input-group">
                        <input type="text" name="username" id="usernameInput" class="custom-form-control" placeholder="Masukkan No. Pendaftaran Syarikat" value="<?= htmlspecialchars($_POST['username'] ?? ''); ?>" maxlength="10" required autocomplete="off">
                        <i class="fa-solid fa-building prefix-icon" id="usernameIcon"></i>
                    </div>
                    
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-dark mb-2">KATA LALUAN</label>
                        <div class="custom-input-group mb-0">
                            <input type="password" name="password" id="passwordField" class="custom-form-control" placeholder="Masukkan password" required>
                            <i class="fa-solid fa-lock prefix-icon"></i>
                            <i class="fa-solid fa-eye toggle-password" id="togglePasswordIcon"></i>
                        </div>
                    </div>

                    <div class="text-end mb-4">
                        <a href="forgot_password.php" class="link-forgot-custom">
                            Lupa Kata Laluan?
                        </a>
                    </div>
                    
                    <button type="submit" name="login" class="btn btn-mdbg-primary w-100 py-3 mb-3">
                        <i class="fa-solid fa-right-to-bracket me-2"></i> LOG MASUK SISTEM
                    </button>
                    
                    <div class="text-center mt-4 pt-3 border-top" style="border-color: #e2e8f0 !important;">
                        <p class="text-muted small mb-2" style="font-size: 0.82rem; letter-spacing: 0.3px;">Belum mempunyai akaun?</p>
                        <a href="register.php" class="btn-daftar-custom">
                            <i class="fa-solid fa-user-plus me-2"></i> Daftar Akaun Baharu
                        </a>
                    </div>
                </form>

                <div class="text-center mt-5 d-none d-lg-block">
                    <span class="text-muted" style="font-size: 0.8rem;">Hak Cipta Terpelihara Majlis Daerah Batu Gajah © 2026</span>
                </div>

            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bundle.min.js"></script>
    <script>
        const passwordField = document.getElementById('passwordField');
        const togglePasswordIcon = document.getElementById('togglePasswordIcon');
        const typeSyarikat = document.getElementById('typeSyarikat');
        const usernameLabel = document.getElementById('usernameLabel');
        const usernameInput = document.getElementById('usernameInput');
        const usernameIcon = document.getElementById('usernameIcon');

        // Fungsi penapis input mengikut pilihan (Individu / Syarikat)
        function formatInput() {
        usernameInput.value = usernameInput.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 10);
    }

        // Penapis automatik semasa pengguna menaip
        usernameInput.addEventListener('input', formatInput);

        togglePasswordIcon.addEventListener('click', function () {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>