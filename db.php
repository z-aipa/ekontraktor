<?php
// Fail sambungan database ke phpMyAdmin
$host = "localhost";
$user = "root";
$pass = "";
$db_name = "sistem_undi";

$conn = new mysqli($host, $user, $pass, $db_name);

if ($conn->connect_error) {
    die("Sambungan database gagal: " . $conn->connect_error);
}
?>