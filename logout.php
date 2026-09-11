<?php
session_start();

// Kosongkan semua pembolehubah sesi
$_SESSION = array();

// Padam kuki sesi jika ada
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Musnahkan sesi sepenuhnya
session_destroy();

// Bawa pengguna kembali ke halaman log masuk
header("Location: index.php");
exit();
?>