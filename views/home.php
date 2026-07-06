<?php
require_once '../controller/config.php';
$user     = current_user();
$is_guest = !$user;
$current  = basename($_SERVER['PHP_SELF']);
$workshop_name = '-';
$can_access_quick_menu = !$is_guest;

if (!$is_guest && !empty($user['workshop_id'])) {
    $wid = (int)$user['workshop_id'];
    $q   = db()->query("SELECT name FROM workshops WHERE id={$wid} LIMIT 1");
    if ($q && $q->num_rows > 0) $workshop_name = $q->fetch_assoc()['name'];
}
?>
<?php if($is_guest): ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PINMES — Sistem Peminjaman Teknik Mesin</title>
<meta name="description" content="Platform digital terintegrasi untuk manajemen logistik dan inventarisasi aset Jurusan Teknik Mesin.">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root { --accent:#2563eb; --glow-blue:rgba(37,99,235,0.3); }
*{ margin:0; padding:0; box-sizing:border-box; }
body{ background:#0b1220; overflow-x:hidden; font-family:'Segoe UI',sans-serif; color:white; }
#tech-bg{ position:fixed; inset:0; z-index:0; background:radial-gradient(circle at center,#0f172a 0%,#0b1220 100%); }

.hero{ position:relative; z-index:5; min-height:100vh; width:100%; display:flex; align-items:center; padding:40px; background:url('../assets/mesin.jpg') no-repeat center center/cover; }
.hero::before{ content:''; position:absolute; inset:0; background:rgba(11,18,32,0.75); z-index:1; }
.landing-container{ width:100%; max-width:1400px; margin:auto; display:flex; align-items:center; justify-content:space-between; gap:50px; }
.content-left{ flex:1; max-width:600px; position:relative; z-index:10; animation:fadeInUp 1s ease-out; }
.content-right{ flex:1; display:flex; justify-content:center; align-items:center; position:relative; z-index:2; height:650px; }

.app-title{ font-size:5rem; font-weight:900; letter-spacing:5px; color:#fff; text-shadow:0 0 15px #2563eb, 0 0 30px #1e3a8a; margin-bottom:5px; }
.app-subtitle{ color:#93c5fd; font-size:1.2rem; font-weight:600; text-transform:uppercase; letter-spacing:2px; margin-bottom:30px; }
.app-description{ color:#cbd5e1; font-size:1.05rem; line-height:1.7; margin-bottom:35px; background:rgba(15,23,42,0.6); padding:25px; border-left:4px solid var(--accent); border-radius:4px; backdrop-filter:blur(5px); }
.btn-group-landing{ display:flex; flex-wrap:wrap; gap:14px; margin-top:0; }
.btn-login{ padding:15px 50px; font-size:18px; font-weight:700; border-radius:12px; background:#2563eb; border:1px solid #60a5fa; box-shadow:0 0 15px rgba(37,99,235,0.4); color:white; transition:.3s; text-decoration:none; display:inline-block; }
.btn-login:hover{ background:#1d4ed8; box-shadow:0 0 25px rgba(37,99,235,0.8); transform:translateY(-3px); color:white; }


@keyframes fadeInUp{ from{opacity:0;transform:translateY(30px)} to{opacity:1;transform:translateY(0)} }
@media(max-width:992px){
  .landing-container{flex-direction:column-reverse;text-align:center;gap:30px}
  .content-left{max-width:100%}
  .content-right{height:480px}
  .app-title{font-size:3.5rem}
  .btn-group-landing{justify-content:center}
}
@media(max-width:576px){
  .hero{padding:20px !important}
  .app-title{font-size:2.5rem !important;letter-spacing:2px !important}
  .app-subtitle{font-size:0.9rem !important;margin-bottom:20px !important}
  .app-description{padding:16px !important;font-size:0.88rem !important}
  .content-right{height:300px !important}
  .content-right img{max-width:220px !important}
  .btn-login{padding:12px 30px !important;font-size:15px !important}
  .btn-group-landing{justify-content:center}
}
@media(max-width:400px){
  .app-title{font-size:2rem !important}
  .app-subtitle{font-size:0.8rem !important}
  .content-right{height:220px !important}
  .content-right img{max-width:160px !important}
  .btn-login{padding:10px 24px !important;font-size:14px !important}
}
</style>
</head>
<body>
<canvas id="tech-bg"></canvas>
<div class="hero">
  <div class="landing-container">
    <div class="content-left">
      <h1 class="app-title">PINMES</h1>
      <p class="app-subtitle">Sistem Peminjaman &amp; Manajemen Stok Barang</p>
      <div class="app-description">
        <p>Selamat datang di <strong>PINMES</strong>, platform digital terintegrasi yang dirancang khusus untuk memodernisasi manajemen logistik dan inventarisasi aset di lingkungan Jurusan Teknik Mesin.</p>
        <p class="mt-3">Aplikasi ini hadir sebagai solusi cerdas untuk mempermudah civitas akademika dalam melakukan transaksi operasional inventaris secara transparan dan akurat.</p>
      </div>
      <div class="btn-group-landing">
        <a href="../index.php" class="btn btn-login">🔐 MASUK KE APLIKASI</a>
      </div>
    </div>
    <div class="content-right">
      <img src="../assets/logo2.png" alt="Logo Poltesa" style="max-width:550px;width:100%;height:auto;">
    </div>
  </div>
</div>
</body>
</html>
<?php exit; endif; ?>

<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Home — PINMES</title>
  <meta name="description" content="Beranda sistem peminjaman barang Jurusan Teknik Mesin.">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/theme.css">
  <style>
    .welcome-card {
      background: linear-gradient(135deg, rgba(37,99,235,0.25), rgba(15,23,42,0.9));
      border: 1px solid rgba(56,189,248,0.2);
      border-radius: 16px;
      padding: 32px;
      margin-bottom: 24px;
      position: relative;
      overflow: hidden;
    }
    .welcome-card::before {
      content: '';
      position: absolute;
      top: -40px; right: -40px;
      width: 200px; height: 200px;
      background: radial-gradient(circle, rgba(56,189,248,0.1), transparent 70%);
      border-radius: 50%;
    }
    .quick-links { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:14px; margin-top:24px; }
    .quick-link {
      display:flex; flex-direction:column; align-items:center; justify-content:center;
      gap:8px; padding:20px 12px;
      background: rgba(15,23,42,0.7);
      border: 1px solid rgba(255,255,255,0.06);
      border-radius:12px;
      text-decoration:none; color:var(--text);
      transition:all .2s; font-size:.88rem; font-weight:500; text-align:center;
    }
    .quick-link:hover { background:rgba(37,99,235,0.25); border-color:rgba(56,189,248,0.3); color:#fff; transform:translateY(-3px); box-shadow:0 8px 20px rgba(37,99,235,0.2); }
    .quick-link .icon { font-size:1.8rem; }
  </style>
</head>
<body>
<canvas id="tech-bg"></canvas>
<img src="../assets/img/dekor-kiri.png"  class="fixed-left"  alt="">
<img src="../assets/img/dekor-kanan.png" class="fixed-right" alt="">

<?php include 'partials/navbar.php'; ?>

<div class="page-wrapper">

  <!-- Welcome Banner -->
  <div class="welcome-card">
    <h1 style="font-size:2rem;font-weight:800;margin-bottom:6px">
      👋 Selamat datang, <?= e($user['name'] ?? $user['email']) ?>!
    </h1>
    <p style="color:var(--muted);margin-bottom:0">
      <?php
        $prodi_msg = [
          'Teknik Informatika'       => '💻 Fokus pada pemrograman, algoritma, dan teknologi masa depan.',
          'Sistem Informasi'         => '📊 Menghubungkan teknologi dengan proses bisnis.',
          'Teknik Komputer'          => '🖥️ Menguasai hardware, jaringan, dan embedded system.',
          'Manajemen Informatika'    => '📈 IT untuk manajemen dan organisasi.',
          'Rekayasa Perangkat Lunak' => '🧠 Membangun software skala profesional.',
        ];
        echo $prodi_msg[$user['prodi'] ?? ''] ?? '🔧 Selamat berkarya di Jurusan Teknik Mesin!';
      ?>
    </p>

    <!-- Info badges -->
    <div class="mt-3 d-flex gap-2 flex-wrap">
      <?php if(!empty($user['nim'])): ?>
        <span class="badge" style="background:rgba(56,189,248,0.15);color:#38bdf8;padding:6px 12px;border-radius:999px;font-size:.8rem"><?= in_array(($user['role'] ?? ''), ['dosen', 'penanggung jawab', 'kepala', 'kepala_bengkel']) ? '🎓 NIDN' : '🎓 NIM' ?>: <?= e($user['nim']) ?></span>
      <?php endif; ?>
      <?php if(!empty($user['prodi'])): ?>
        <span class="badge" style="background:rgba(37,99,235,0.15);color:#93c5fd;padding:6px 12px;border-radius:999px;font-size:.8rem">🏫 <?= e($user['prodi']) ?></span>
      <?php endif; ?>
      <span class="badge" style="background:rgba(16,185,129,0.15);color:#6ee7b7;padding:6px 12px;border-radius:999px;font-size:.8rem">🏷️ <?= e(ucfirst($user['role'])) ?></span>
      <?php if($workshop_name !== '-' && in_array(($user['role']??''), ['penanggung jawab', 'kepala', 'kepala_bengkel'])): ?>
        <span class="badge" style="background:rgba(251,191,36,0.15);color:#fbbf24;padding:6px 12px;border-radius:999px;font-size:.8rem">🏭 <?= e($workshop_name) ?></span>
      <?php endif; ?>
    </div>
  </div>

  <?php if($can_access_quick_menu): ?>
  <!-- Quick Links -->
  <h2 style="font-size:1rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px">Menu Cepat</h2>
  <div class="quick-links">
    <a href="dashboard.php" class="quick-link">
      <span class="icon">📦</span>
      <span>Stok Barang</span>
    </a>

    <?php if(!$is_guest && in_array(($user['role'] ?? ''), ['mahasiswa', 'penanggung jawab'])): ?>
    <a href="borrow.php" class="quick-link">
      <span class="icon">🔄</span>
      <span>Peminjaman</span>
    </a>
    <?php endif; ?>

    <a href="profile.php" class="quick-link">
      <span class="icon">👤</span>
      <span>Profil Saya</span>
    </a>
    <?php if(in_array(($user['role'] ?? ''), ['kepala','kepala_bengkel','penanggung jawab'])): ?>
    <a href="../admin/items.php" class="quick-link">
      <span class="icon">🛠️</span>
      <span>Kelola Barang</span>
    </a>
    <?php endif; ?>
    <?php if(in_array(($user['role'] ?? ''), ['admin','kepala','kepala_bengkel','penanggung jawab'])): ?>
    <a href="../admin/pre_students.php" class="quick-link">
      <span class="icon">📋</span>
      <span>Data Mahasiswa</span>
    </a>
    <?php endif; ?>
    <?php if(in_array(($user['role'] ?? ''), ['admin','kepala','kepala_bengkel','penanggung jawab'])): ?>
    <a href="../admin/pre_lecturers.php" class="quick-link">
      <span class="icon">👨‍🏫</span>
      <span>Data Dosen</span>
    </a>
    <?php endif; ?>
   
  </div>
  <?php endif; ?>

  <footer class="pinba-footer">© <?= date('Y') ?> PINMES — <?= e($user['name'] ?? 'Tamu') ?></footer>
</div>

<?php include 'partials/scripts.php'; ?>
</body>
</html>
