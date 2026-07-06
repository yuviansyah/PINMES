<?php
session_start();
require_once '../controller/config.php';

// Hapus semua session user
$_SESSION = [];
unset($_SESSION['guest_mode']);
session_destroy();

// Arahkan ke halaman utama
header('Location: home.php');
exit;
?>