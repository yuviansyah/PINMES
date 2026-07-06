<?php
// config.php — session, koneksi DB, helper, dan aturan proteksi halaman (siap pakai)
// GANTI seluruh isi file config.php kamu dengan kode ini

if (session_status() === PHP_SESSION_NONE) session_start();

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAMES = ['apk_mesin', 'aplikasi_peminjaman_barang'];

$conn = null;
$DB_NAME = '';
$last_db_error = '';

foreach ($DB_NAMES as $candidate) {
    $tmp = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $candidate);
    if ($tmp->connect_error) {
        $last_db_error = $tmp->connect_error;
        continue;
    }
    $conn = $tmp;
    $DB_NAME = $candidate;
    break;
}

if (!$conn) {
    die("Koneksi DB gagal: " . $last_db_error);
}
$conn->set_charset('utf8mb4');

function ensure_app_schema() {
    global $conn;

    $conn->query("CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        `key` VARCHAR(100) NOT NULL UNIQUE,
        `value` TEXT DEFAULT NULL
    ) ENGINE=InnoDB");

    $settings_cols = [];
    $col_res = $conn->query('SHOW COLUMNS FROM settings');
    if ($col_res) {
        while ($col = $col_res->fetch_assoc()) {
            $settings_cols[] = strtolower($col['Field']);
        }
    }
    if (!in_array('key', $settings_cols, true)) {
        $conn->query('ALTER TABLE settings ADD COLUMN `key` VARCHAR(100) NULL');
    }
    if (!in_array('value', $settings_cols, true)) {
        $conn->query('ALTER TABLE settings ADD COLUMN `value` TEXT DEFAULT NULL');
    }

    $has_loans = $conn->query("SHOW TABLES LIKE 'loans'")->num_rows > 0;
    if ($has_loans) {
        $conn->query("CREATE TABLE IF NOT EXISTS loan_details (
            id INT AUTO_INCREMENT PRIMARY KEY,
            loan_id INT NOT NULL,
            item_id INT NOT NULL,
            qty INT NOT NULL DEFAULT 1,
            FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
            FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");
    }

    $defaults = [
        ['loan_period_days', '7'],
        ['fine_per_day', '20000'],
        ['max_loans_per_user', '3'],
    ];

    $stmt = $conn->prepare('INSERT IGNORE INTO settings (`key`, `value`) VALUES (?, ?)');
    foreach ($defaults as $row) {
        $stmt->bind_param('ss', $row[0], $row[1]);
        $stmt->execute();
    }
    $stmt->close();
}

ensure_app_schema();

function db() {
    global $conn;
    return $conn;
}

function e($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

function current_user() {
    return $_SESSION['user'] ?? null;
}

function is_admin() {
    $u = current_user();
    return $u && isset($u['role']) && strtolower($u['role']) === 'admin';
}

function month_id($m) {
    $months = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return $months[intval($m)] ?? '';
}

function bulan_tgl($tgl) {
    $bulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $t = strtotime($tgl);
    return date('d', $t) . ' ' . ($bulan[intval(date('n', $t))] ?? '') . ' ' . date('Y', $t);
}

function is_kepala(){
    $role = $_SESSION['user']['role'] ?? '';
    return $role === 'kepala' || $role === 'kepala_bengkel' || $role === 'kepala bengkel' || $role === 'penanggung jawab';
}

/*
  PUBLIC PAGES:
  Tambahkan nama file yang boleh diakses tanpa login.
  Penting: pastikan 'home.php' dan 'index.php' ada di sini.
*/
$public_pages = [
    'login.php',     // views/login.php -> basename adalah login.php
    'index.php',     // form login akun
    'register.php',
    'home.php',      // views/home.php -> basename adalah home.php
    'logout.php',    // agar bisa logout
    'dashboard.php',  // izinkan tamu melihat stok
    'cek_nim.php',
    'borrow_public.php',
    'favicon.ico',
];

// dapatkan nama file dengan andal (tahan terhadap root '/')
$request_uri = $_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? '');
$path = parse_url($request_uri ?: '/', PHP_URL_PATH) ?: '/';
$current_page = basename($path);
if ($current_page === '') {
    // treat root as index.php
    $current_page = 'index.php';
}

// Jika request bukan CLI, dan halaman bukan publik, dan user belum login -> redirect ke login.php
if (php_sapi_name() !== 'cli') {
    if (!in_array($current_page, $public_pages, true) && !current_user()) {
        header('Location: views/home.php');
        exit;
    }
}

// Tandai pengunjung tanpa login
if (!current_user() && basename($_SERVER['PHP_SELF']) === 'views/home.php') {
    $_SESSION['guest_mode'] = true; // aktifkan mode tamu
}
