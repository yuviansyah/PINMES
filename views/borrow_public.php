<?php
session_start();
require_once '../controller/config.php';

// Halaman peminjaman publik telah dihapus.
// Mahasiswa sekarang login melalui halaman utama.
header('Location: ../index.php');
exit;
