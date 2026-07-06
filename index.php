<?php
require_once 'controller/config.php';

// Jika sudah login, langsung arahkan
if (current_user()) {
    $role = strtolower($_SESSION['user']['role'] ?? '');
    if ($role === 'mahasiswa') {
        header('Location: views/borrow.php');
    } else {
        header('Location: views/home.php');
    }
    exit;
}

$error = '';
$student_data = null; // hasil lookup NIM

// Proses POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_type = $_POST['login_type'] ?? 'staff'; // 'staff' atau 'student'

    if ($login_type === 'student') {
        // ===== LOGIN/REGISTRASI MAHASISWA =====
        $nim     = trim($_POST['nim'] ?? '');
        $password = $_POST['password'] ?? '';
        $name     = trim($_POST['name'] ?? '');

        if ($nim === '' || $password === '') {
            $error = 'NIM dan Kata Sandi wajib diisi.';
        } else {
            // Cek apakah user sudah ada berdasarkan NIM
            $stmt = db()->prepare('SELECT * FROM users WHERE nim = ? LIMIT 1');
            $stmt->bind_param('s', $nim);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if ($user) {
                // User sudah ada — verifikasi password saja
                $dbpw = $user['password'] ?? '';
                $ok = false;
                if ($dbpw && password_verify($password, $dbpw)) $ok = true;
                if (!$ok && $dbpw === $password) $ok = true;

                if ($ok) {
                    unset($user['password']);
                    session_regenerate_id(true);
                    $_SESSION['user'] = $user;
                    header('Location: views/borrow.php');
                    exit;
                } else {
                    $error = 'Kata sandi salah. Silakan coba lagi.';
                }
            } else {
                // User belum ada — buat akun baru
                // Generate placeholder email dari NIM
                $email = $nim . '@student.local';

                // Cari nama dari pre_students jika tidak diisi
                if ($name === '') {
                    $ps = db()->query("SELECT name, prodi, angkatan FROM pre_students WHERE nim='".db()->real_escape_string($nim)."' LIMIT 1");
                    if ($ps && $ps->num_rows) {
                        $pd = $ps->fetch_assoc();
                        $name = $pd['name'] ?? '';
                    }
                }
                if ($name === '') {
                    $error = 'Nama tidak ditemukan. Silakan isi nama lengkap Anda.';
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $role = 'mahasiswa';
                    $stmt = db()->prepare('INSERT INTO users (name, nim, prodi, angkatan, email, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $prodi = '';
                    $angkatan = 0;
                    $stmt->bind_param('sssisss', $name, $nim, $prodi, $angkatan, $email, $hashed, $role);
                    if ($stmt->execute()) {
                        $user_id = db()->insert_id;
                        // Update prodi/angkatan dari pre_students jika ada
                        $ps = db()->query("SELECT prodi, angkatan FROM pre_students WHERE nim='".db()->real_escape_string($nim)."' LIMIT 1");
                        if ($ps && $ps->num_rows) {
                            $pd = $ps->fetch_assoc();
                            $prodi = $pd['prodi'] ?? '';
                            $angkatan = intval($pd['angkatan'] ?? 0);
                            db()->query("UPDATE users SET prodi='".db()->real_escape_string($prodi)."', angkatan={$angkatan} WHERE id={$user_id}");
                        }
                        $new_user = [
                            'id'       => $user_id,
                            'name'     => $name,
                            'nim'      => $nim,
                            'email'    => $email,
                            'role'     => 'mahasiswa',
                            'prodi'    => $prodi,
                            'angkatan' => $angkatan,
                        ];
                        session_regenerate_id(true);
                        $_SESSION['user'] = $new_user;
                        header('Location: views/borrow.php');
                        exit;
                    } else {
                        $error = 'Gagal membuat akun. Silakan coba lagi.';
                    }
                }
            }
        }

        // Simpan NIM untuk form
        $nim_old = $nim;

    } else {
        // ===== LOGIN STAF (Admin / Penanggung Jawab / Kepala) =====
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $error = 'Email dan kata sandi wajib diisi.';
        } else {
            $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $res = $stmt->get_result();

                if ($user = $res->fetch_assoc()) {
                    $dbpw = $user['password'] ?? '';
                    $ok = false;
                    if ($dbpw && password_verify($password, $dbpw)) $ok = true;
                    if (!$ok && $dbpw === $password) $ok = true;

                    if ($ok) {
                        $allowed_roles = ['admin', 'penanggung jawab', 'kepala', 'kepala_bengkel'];
                        $user_role = strtolower($user['role'] ?? '');
                        if (!in_array($user_role, $allowed_roles, true)) {
                            $error = 'Akses ditolak. Hanya Admin, Penanggung Jawab, dan Kepala Bengkel yang dapat masuk.';
                        } else {
                            unset($user['password']);
                            session_regenerate_id(true);
                            $_SESSION['user'] = $user;
                            header('Location: views/home.php');
                            exit;
                        }
                    } else {
                        $error = 'Email atau kata sandi salah.';
                    }
                } else {
                    $error = 'Email tidak ditemukan.';
                }
                $stmt->close();
            } else {
                $error = 'Gagal koneksi database.';
            }
        }
    }
}

$me = current_user();
$nim_old = $nim_old ?? ($_POST['nim'] ?? '');
$name_old = $name ?? ($_POST['name'] ?? '');
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Masuk — Aplikasi PinMes</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    body {
      background: #0b1220;
      color: #fff;
      min-height: 100vh;
      overflow-x: hidden;
    }

    /* ===== CARD STYLING ===== */
    .card {
      background: #111827;
      color: #fff;
      border: 1px solid rgba(96,165,250,.12);
      border-radius: 16px;
      animation: cardEnter 1s ease-out forwards;
      opacity: 0;
      position: relative;
      overflow: hidden;
    }

    .card::before {
      content: '';
      position: absolute;
      top: -1px; left: -1px; right: -1px;
      height: 3px;
      background: linear-gradient(90deg, #60a5fa, #a78bfa, #60a5fa);
      background-size: 200% 100%;
      animation: borderGlow 3s ease-in-out infinite;
      border-radius: 16px 16px 0 0;
    }

    @keyframes borderGlow {
      0%, 100% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
    }

    @keyframes cardEnter {
      from {
        transform: translateY(30px) scale(.97);
        opacity: 0;
      }
      to {
        transform: translateY(0) scale(1);
        opacity: 1;
      }
    }

    a {
      color: #38bdf8;
      text-decoration: none;
    }

    a:hover {
      text-decoration: underline;
    }

    #matrix, #network {
      position: fixed;
      inset: 0;
    }
    #matrix { z-index: 0; opacity: .22; }
    #network { z-index: 1; pointer-events: none; }

    .overlay-soft {
      position: fixed;
      inset: 0;
      background: radial-gradient(circle, transparent, rgba(0, 0, 0, .65));
      z-index: 2;
    }

    /* ===== INPUT STYLING ===== */
    .input-icon-wrap {
      position: relative;
    }

    .input-icon-wrap .input-icon {
      position: absolute;
      left: 13px;
      top: 50%;
      transform: translateY(-50%);
      color: #475569;
      font-size: 15px;
      z-index: 3;
      pointer-events: none;
      transition: color .3s;
    }

    .input-icon-wrap:focus-within .input-icon {
      color: #60a5fa;
    }

    .input-icon-wrap .form-control {
      padding-left: 38px;
    }

    .form-control,
    .form-select {
      background: #020617;
      border: 1px solid #1e293b;
      color: #fff;
      border-radius: 10px;
      padding: 10px 14px;
      font-size: 14px;
      transition: border-color .3s, box-shadow .3s;
    }

    .form-control:focus,
    .form-select:focus {
      background: #020617;
      color: #fff;
      border-color: #60a5fa;
      box-shadow: 0 0 0 3px rgba(96, 165, 250, .15), 0 0 20px rgba(96, 165, 250, .08);
    }

    .form-select option {
      background: #111827;
      color: #fff;
    }

    .form-control:disabled,
    .form-control[readonly] {
      background: #0f172a;
      color: #94a3b8;
      cursor: default;
    }

    .form-label {
      font-size: 13px;
      font-weight: 600;
      color: #94a3b8;
      margin-bottom: 6px;
      letter-spacing: .3px;
    }

    /* ===== PASSWORD TOGGLE ===== */
    .password-wrapper { position: relative; }

    .password-wrapper .form-control {
      padding-right: 42px;
    }

    .toggle-password {
      position: absolute;
      right: 14px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      font-size: 17px;
      user-select: none;
      transition: .3s;
      color: #475569;
      z-index: 3;
    }

    .toggle-password:hover {
      color: #60a5fa;
      transform: translateY(-50%) scale(1.15);
    }

    /* ===== TABS ===== */
    .nav-custom {
      display: flex;
      gap: 0;
      margin-bottom: 1.5rem;
      border-bottom: 2px solid #1e293b;
      position: relative;
    }

    .nav-custom .nav-item {
      flex: 1;
      list-style: none;
      margin-bottom: -2px;
    }

    .nav-custom .nav-link {
      display: block;
      text-align: center;
      padding: 12px 10px;
      cursor: pointer;
      font-size: 13px;
      font-weight: 600;
      transition: all .25s ease;
      color: #64748b;
      border-bottom: 2px solid transparent;
      position: relative;
    }

    .nav-custom .nav-link:hover {
      color: #94a3b8;
    }

    .nav-custom .nav-link.active-adm {
      color: #60a5fa;
      border-bottom-color: #60a5fa;
    }

    .nav-custom .nav-link.active-adm::after {
      content: '';
      position: absolute;
      bottom: -2px;
      left: 20%;
      right: 20%;
      height: 2px;
      background: linear-gradient(90deg, transparent, #60a5fa, transparent);
      animation: tabGlow 2s ease-in-out infinite;
    }

    @keyframes tabGlow {
      0%, 100% { opacity: .4; }
      50% { opacity: 1; }
    }

    /* ===== PANEL TRANSITIONS ===== */
    #panel-student, #panel-staff {
      animation: panelSlide .3s ease-out;
    }

    @keyframes panelSlide {
      from { opacity: 0; transform: translateY(8px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* ===== BRAND HEADER ===== */
    .brand-header {
      text-align: center;
      margin-bottom: 24px;
    }

    .brand-header .brand-icon {
      font-size: 2.5rem;
      margin-bottom: 6px;
      display: block;
    }

    .brand-header h4 {
      font-size: 1.35rem;
      font-weight: 800;
      letter-spacing: 1px;
      margin-bottom: 2px;
      background: linear-gradient(135deg, #60a5fa 0%, #a78bfa 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .brand-header p {
      font-size: 12px;
      color: #64748b;
      letter-spacing: .5px;
      margin-bottom: 0;
    }

    /* ===== BUTTONS ===== */
    .btn {
      border-radius: 10px;
      font-weight: 600;
      transition: all .25s ease;
      position: relative;
      overflow: hidden;
    }

    .btn-primary {
      background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
      border: none;
    }

    .btn-primary:hover {
      background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(37,99,235,.4);
    }

    .btn-primary:active {
      transform: translateY(0);
    }

    .btn-primary.loading {
      pointer-events: none;
      opacity: .8;
    }

    .btn-primary.loading::after {
      content: '';
      display: inline-block;
      width: 16px; height: 16px;
      border: 2px solid rgba(255,255,255,.3);
      border-top-color: #fff;
      border-radius: 50%;
      animation: spin .6s linear infinite;
      margin-left: 8px;
      vertical-align: middle;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    .btn-outline-primary {
      border: 1px solid rgba(96,165,250,.3);
      color: #93c5fd;
    }

    .btn-outline-primary:hover {
      background: rgba(96,165,250,.1);
      border-color: #60a5fa;
      color: #60a5fa;
    }

    /* ===== ALERTS ===== */
    .alert {
      border-radius: 10px;
      padding: 12px 14px;
      font-size: 13px;
      border: none;
      animation: alertSlide .3s ease-out;
    }

    @keyframes alertSlide {
      from { opacity: 0; transform: translateY(-8px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .alert-danger {
      background: rgba(239,68,68,.12);
      color: #fca5a5;
      border-left: 3px solid #ef4444;
    }

    .alert-success {
      background: rgba(34,197,94,.12);
      color: #86efac;
      border-left: 3px solid #22c55e;
    }

    .alert-info-custom {
      background: rgba(96, 165, 250, .1);
      border: 1px solid rgba(96, 165, 250, .2);
      color: #93c5fd;
      padding: 12px 14px;
      border-radius: 10px;
      font-size: 13px;
      margin-bottom: 1rem;
      line-height: 1.5;
    }

    /* ===== INPUT GROUP (CARI NIM) ===== */
    .input-group {
      border-radius: 10px;
      overflow: hidden;
    }

    .input-group .form-control {
      border-top-right-radius: 0;
      border-bottom-right-radius: 0;
    }

    .input-group .btn {
      border-top-left-radius: 0;
      border-bottom-left-radius: 0;
      padding: 10px 16px;
      font-size: 13px;
      font-weight: 600;
    }

    .form-text {
      font-size: 12px;
      margin-top: 4px;
      display: block;
    }

    /* ===== DIVIDER ===== */
    hr {
      border: none;
      height: 1px;
      background: linear-gradient(90deg, transparent, #1e293b, transparent);
      margin: 16px 0;
    }

    /* ===== RESPONSIVE MOBILE ===== */
    @media (max-width: 576px) {
      body { padding: 10px 0; }

      .card {
        margin: 0 8px;
        padding: 20px 16px !important;
      }

      .card h4 { font-size: 1.15rem; }

      .nav-custom .nav-link {
        font-size: 11px;
        padding: 10px 6px;
      }

      .form-label { font-size: 12px; }

      .form-control, .form-select {
        font-size: 14px;
        padding: 9px 12px;
      }

      .input-icon-wrap .form-control {
        padding-left: 34px;
      }

      .input-icon-wrap .input-icon {
        font-size: 13px;
        left: 11px;
      }

      .btn { font-size: 14px; padding: 10px 16px; }

      .toggle-password {
        font-size: 15px;
        right: 12px;
      }

      .input-group .btn {
        font-size: 13px;
        padding: 9px 12px;
        white-space: nowrap;
      }

      #matrix, #network { opacity: 0.12; }

      .d-flex.gap-2 {
        flex-direction: column;
        gap: 8px !important;
      }

      .d-flex.gap-2 .btn { width: 100%; }
    }

    @media (max-width: 400px) {
      .card { padding: 16px 12px !important; margin: 0 4px; }

      .nav-custom .nav-link {
        font-size: 10px;
        padding: 8px 4px;
      }

      .form-control, .form-select {
        font-size: 13px;
        padding: 8px 10px;
      }

      .btn { font-size: 13px; padding: 8px 14px; }
    }
</style>
</head>

<body class="d-flex align-items-center">

<canvas id="matrix"></canvas>
<canvas id="network"></canvas>
<div class="overlay-soft"></div>

<div class="container position-relative" style="z-index:7">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card p-4">
        <div class="brand-header">
          <span class="brand-icon">🔐</span>
          <h4>Masuk Akun</h4>
          <p>Sistem Peminjaman Teknik Mesin</p>
        </div>

        <?php if ($me): ?>
          <div class="alert alert-success">
            Anda sudah masuk sebagai <strong><?= e($me['name'] ?? $me['email']) ?></strong> (<?= e($me['role'] ?? '') ?>).
          </div>

          <div class="d-flex gap-2 mb-3">
            <a href="views/home.php" class="btn btn-primary">Lanjut ke Beranda</a>
            <?php if (is_admin()): ?>
              <a href="views/dashboard.php" class="btn btn-warning text-dark">Buka Dashboard Admin</a>
            <?php endif; ?>
            <a href="views/logout.php" class="btn btn-outline-light">Logout</a>
          </div>

          <p class="text-muted small">Atau gunakan menu di atas untuk berpindah.</p>

        <?php else: ?>
          <?php if($error): ?><div class="alert alert-danger"><?= e($error); ?></div><?php endif; ?>

          <ul class="nav-custom">
            <li class="nav-item">
              <a class="nav-link active-adm" id="tab-student" onclick="switchLoginTab('student')">🎓 Mahasiswa</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="tab-staff" onclick="switchLoginTab('staff')">👔 Staf / Admin</a>
            </li>
          </ul>

            <!-- STUDENT LOGIN -->
            <div id="panel-student">
              <form method="post" novalidate>
                <input type="hidden" name="login_type" value="student">

                <div class="mb-3">
                  <label class="form-label">📋 NIM</label>
                  <div class="input-group">
                    <div class="input-icon-wrap" style="flex:1">
                      <i class="fa-solid fa-id-card input-icon"></i>
                      <input type="text" name="nim" id="nim_input" class="form-control"
                             placeholder="Masukkan NIM" required value="<?= e($nim_old) ?>">
                    </div>
                    <button type="button" class="btn btn-primary" id="btn_cek_nim">🔍 Cari</button>
                  </div>
                  <small id="nimStatus" class="form-text" style="color:#64748b">Masukkan NIM untuk mencari data Anda.</small>
                </div>

                <div class="mb-3" id="name_group" style="display:<?= $nim_old !== '' ? 'block' : 'none' ?>">
                  <label class="form-label">👤 Nama Lengkap</label>
                  <div class="input-icon-wrap">
                    <i class="fa-solid fa-user input-icon"></i>
                    <input type="text" name="name" id="name_input" class="form-control"
                           placeholder="Nama akan terisi otomatis" value="<?= e($name_old) ?>">
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label">🔑 Kata Sandi</label>
                  <div class="password-wrapper">
                    <div class="input-icon-wrap">
                      <i class="fa-solid fa-lock input-icon"></i>
                      <input type="password" name="password" class="form-control"
                             placeholder="Buat password untuk akun baru" required>
                    </div>
                    <span class="toggle-password" onclick="togglePassword('student')">
                      <i class="fa-solid fa-eye-slash"></i>
                    </span>
                  </div>
                </div>

                <button class="btn btn-primary w-100" id="btn-login-student">🔐 Masuk</button>
              </form>
              
              <p class="text-center mt-2">
                Belum punya akun? <a href="controller/register.php">Daftar di sini</a>
              </p>

              <div class="text-center mt-3">
                <a href="views/home.php" class="btn btn-outline-primary btn-sm">Kembali</a>
              </div>
            </div>

            <!-- STAFF LOGIN -->
            <div id="panel-staff" style="display:none">
              <form method="post" novalidate>
                <input type="hidden" name="login_type" value="staff">

                <div class="mb-3">
                  <label class="form-label">✉️ Email</label>
                  <div class="input-icon-wrap">
                    <i class="fa-solid fa-envelope input-icon"></i>
                    <input type="email" name="email" class="form-control" placeholder="email@contoh.com" required>
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label">🔑 Kata Sandi</label>
                  <div class="password-wrapper">
                    <div class="input-icon-wrap">
                      <i class="fa-solid fa-lock input-icon"></i>
                      <input type="password" name="password" class="form-control" required>
                    </div>
                    <span class="toggle-password" onclick="togglePassword('staff')">
                      <i class="fa-solid fa-eye-slash"></i>
                    </span>
                  </div>
                </div>

                <button class="btn btn-primary w-100" id="btn-login-staff">👔 Masuk sebagai Staf</button>
              </form>

              <hr class="my-3" />
              <p class="text-center muted small">Belum punya akun? <a href="controller/register.php">Buat Akun</a></p>
              <p class="text-center mt-2">
                <a href="views/home.php" class="btn btn-outline-primary">Kembali</a>
              </p>
            </div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</div>

<script>
const matrix = document.getElementById('matrix');
const mctx = matrix.getContext('2d');

function resizeMatrix(){
  matrix.width = innerWidth;
  matrix.height = innerHeight;
}
resizeMatrix();

const letters = "01SYSTEMNETWORKDATA";
const fontSize = 14;
let drops = Array.from({length: matrix.width/fontSize}).fill(1);

function drawMatrix(){
  mctx.fillStyle="rgba(11,18,32,0.07)";
  mctx.fillRect(0,0,matrix.width,matrix.height);
  mctx.fillStyle="#60a5fa";
  mctx.font=fontSize+"px monospace";

  drops.forEach((y,i)=>{
    const text = letters[Math.floor(Math.random()*letters.length)];
    mctx.fillText(text,i*fontSize,y*fontSize);
    if(y*fontSize>matrix.height && Math.random()>0.98) drops[i]=0;
    drops[i]++;
  });
}
setInterval(drawMatrix,45);
</script>

<script>
const net=document.getElementById('network');
const nctx=net.getContext('2d');
function resizeNet(){ net.width=innerWidth; net.height=innerHeight; }
resizeNet();

const mouse={x:null,y:null};
addEventListener('mousemove',e=>{mouse.x=e.clientX;mouse.y=e.clientY});
addEventListener('mouseleave',()=>{mouse.x=null;mouse.y=null});

const nodes=[...Array(65)].map(()=>({
  x:Math.random()*net.width,
  y:Math.random()*net.height,
  vx:(Math.random()-.5)*.4,
  vy:(Math.random()-.5)*.4
}));

function drawNetwork(){
  nctx.clearRect(0,0,net.width,net.height);
  nodes.forEach((n,i)=>{
    if(mouse.x){
      const dx=mouse.x-n.x,dy=mouse.y-n.y;
      const d=Math.hypot(dx,dy);
      if(d<180){ n.vx+=dx/d*0.015; n.vy+=dy/d*0.015; }
    }
    n.x+=n.vx; n.y+=n.vy;
    n.vx*=.98; n.vy*=.98;

    nctx.fillStyle="rgba(96,165,250,.95)";
    nctx.beginPath(); nctx.arc(n.x,n.y,2.3,0,Math.PI*2); nctx.fill();

    for(let j=i+1;j<nodes.length;j++){
      const m=nodes[j],d=Math.hypot(n.x-m.x,n.y-m.y);
      if(d<130){
        nctx.strokeStyle=`rgba(96,165,250,${1-d/130})`;
        nctx.beginPath(); nctx.moveTo(n.x,n.y); nctx.lineTo(m.x,m.y); nctx.stroke();
      }
    }
  });
  requestAnimationFrame(drawNetwork);
}
drawNetwork();

addEventListener('resize',()=>{resizeMatrix();resizeNet();});
</script>

<script>
const tabStudent = document.getElementById('tab-student');
const tabStaff = document.getElementById('tab-staff');

function switchLoginTab(type) {
  document.getElementById('panel-student').style.display = type === 'student' ? '' : 'none';
  document.getElementById('panel-staff').style.display = type === 'staff' ? '' : 'none';
  tabStudent.className = 'nav-link' + (type === 'student' ? ' active-adm' : '');
  tabStaff.className = 'nav-link' + (type === 'staff' ? ' active-adm' : '');
}

function togglePassword(tab) {
  const container = tab === 'student'
    ? document.querySelector('#panel-student .password-wrapper')
    : document.querySelector('#panel-staff .password-wrapper');
  if (!container) return;
  const input = container.querySelector('input');
  const icon  = container.querySelector('i');
  if (!input || !icon) return;

  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.remove('fa-eye-slash');
    icon.classList.add('fa-eye');
  } else {
    input.type = 'password';
    icon.classList.remove('fa-eye');
    icon.classList.add('fa-eye-slash');
  }
}

  // NIM Lookup untuk mahasiswa
  document.addEventListener('DOMContentLoaded', function() {
    const nimInput  = document.getElementById('nim_input');
    const btnCekNim = document.getElementById('btn_cek_nim');
    const nimStatus = document.getElementById('nimStatus');
    const nameInput = document.getElementById('name_input');
    const nameGroup = document.getElementById('name_group');

    if (!nimInput) return;

    let nimTimer = null;

    function cekNim() {
      const v = nimInput.value.trim();
      if (v.length < 3) {
        nimStatus.innerHTML = 'Masukkan NIM minimal 3 karakter';
        nimStatus.style.color = '#f87171';
        nameGroup.style.display = 'none';
        return;
      }

      nimStatus.innerHTML = '🔍 Mencari...';
      nimStatus.style.color = '#60a5fa';

      fetch('cek_nim.php?nim=' + encodeURIComponent(v))
        .then(r => r.json())
        .then(d => {
          if (d.status === 'found' || d.status === 'registered') {
            const nama = d.data?.name || '';
            if (nama) {
              nameInput.value = nama;
              nameGroup.style.display = 'block';
              nimStatus.innerHTML = '✅ Data ditemukan. Nama: <strong>' + nama + '</strong>';
              nimStatus.style.color = '#22c55e';
            } else {
              nameGroup.style.display = 'block';
              nimStatus.innerHTML = '✅ NIM ditemukan. Silakan lengkapi data.';
              nimStatus.style.color = '#22c55e';
            }
          } else {
            nameGroup.style.display = 'block';
            nimStatus.innerHTML = '❌ ' + (d.message || 'NIM tidak ditemukan. Isi nama manual.');
            nimStatus.style.color = '#f87171';
            nameInput.value = '';
            nameInput.readOnly = false;
          }
        })
        .catch(() => {
          nimStatus.innerHTML = '❌ Gagal menghubungi server';
          nimStatus.style.color = '#f87171';
        });
    }

    nimInput.addEventListener('input', function() {
      clearTimeout(nimTimer);
      if (this.value.trim().length < 3) {
        nimStatus.innerHTML = 'Masukkan NIM minimal 3 karakter';
        nimStatus.style.color = '#64748b';
        nameGroup.style.display = 'none';
        return;
      }
      nimTimer = setTimeout(cekNim, 400);
    });

    btnCekNim.addEventListener('click', function() {
      clearTimeout(nimTimer);
      cekNim();
    });

    nimInput.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        clearTimeout(nimTimer);
        cekNim();
      }
    });
  });
</script>
</body>
</html>
