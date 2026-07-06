<?php
require_once 'config.php';
$error = '';
$success = '';
$allowed_types = ['mahasiswa', 'adminreq', 'kepalareq'];
$user_type = in_array($_GET['type'] ?? '', $allowed_types) ? $_GET['type'] : 'mahasiswa';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $form_type = $_POST['form_type'] ?? '';

  if ($form_type === 'admin_request' || $form_type === 'kepala_request') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['adm_email'] ?? '');
    $password = $_POST['adm_password'] ?? '';
    $pass_confirm = $form_type === 'admin_request' ? ($_POST['adm_password_confirm'] ?? '') : ($_POST['kepala_password_confirm'] ?? '');
    $workshop_id = intval($_POST['pj_workshop_id'] ?? 0);
      $requested_role = $form_type === 'kepala_request' ? 'kepala' : 'penanggung jawab';

    if ($name === '' || $email === '' || $password === '' || $workshop_id === 0) {
      $error = 'Semua field wajib diisi.';
    } elseif ($password !== $pass_confirm) {
      $error = 'Konfirmasi password tidak cocok.';
    } elseif (strlen($password) < 4) {
      $error = 'Password minimal 4 karakter.';
    } else {
      $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
      $stmt->bind_param('s', $email);
      $stmt->execute();

      if ($stmt->get_result()->fetch_assoc()) {
        $error = 'Email sudah digunakan.';
      } else {
        $stmt = db()->prepare('SELECT id FROM admin_requests WHERE email = ? AND status = "pending" LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
          $error = 'Permintaan untuk email ini sudah ada dan menunggu persetujuan.';
        } else {
          $hashed = password_hash($password, PASSWORD_DEFAULT);
          $nidn = trim($_POST['pj_nidn'] ?? '');
          $chk_ws = db()->query("SHOW COLUMNS FROM admin_requests LIKE 'workshop_id'");
          $has_ws = $chk_ws && $chk_ws->num_rows > 0;
          $chk_nidn = db()->query("SHOW COLUMNS FROM admin_requests LIKE 'nidn'");
          $has_nidn = $chk_nidn && $chk_nidn->num_rows > 0;
          if ($has_ws && $has_nidn) {
            $stmt = db()->prepare('INSERT INTO admin_requests (name, nidn, email, requested_role, password, workshop_id) VALUES (?,?,?,?,?,?)');
            $stmt->bind_param('sssssi', $name, $nidn, $email, $requested_role, $hashed, $workshop_id);
          } elseif ($has_ws) {
            $stmt = db()->prepare('INSERT INTO admin_requests (name, email, requested_role, password, workshop_id) VALUES (?,?,?,?,?)');
            $stmt->bind_param('ssssi', $name, $email, $requested_role, $hashed, $workshop_id);
          } else {
            $stmt = db()->prepare('INSERT INTO admin_requests (name, email, requested_role, password) VALUES (?,?,?,?)');
            $stmt->bind_param('ssss', $name, $email, $requested_role, $hashed);
          }
          if ($stmt->execute()) {
            $roleName = $requested_role === 'kepala' ? 'Kepala Bengkel' : 'Penanggung Jawab';
            $success = "Permintaan daftar sebagai <strong>$roleName</strong> berhasil dikirim. Silakan tunggu persetujuan Admin.";
          } else {
            $error = 'Gagal mengirim permintaan. Silakan coba lagi.';
          }
        }
      }
    }
  }

  // ===== REGISTRASI MAHASISWA =====
  if ($form_type === 'student_register') {
    $nim          = trim($_POST['nim'] ?? '');
    $name         = trim($_POST['name'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $password     = $_POST['password'] ?? '';
    $pass_confirm = $_POST['password_confirm'] ?? '';

    if ($nim === '' || $name === '' || $email === '' || $password === '') {
      $error = 'Semua field wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $error = 'Format email tidak valid.';
    } elseif ($password !== $pass_confirm) {
      $error = 'Konfirmasi password tidak cocok.';
    } elseif (strlen($password) < 4) {
      $error = 'Password minimal 4 karakter.';
    } else {
      // Cek NIM sudah terdaftar
      $stmt = db()->prepare('SELECT id FROM users WHERE nim = ? LIMIT 1');
      $stmt->bind_param('s', $nim);
      $stmt->execute();
      if ($stmt->get_result()->fetch_assoc()) {
        $error = 'NIM sudah terdaftar. Silakan <a href="../index.php">login</a>.';
      } else {
        // Cek email sudah dipakai
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
          $error = 'Email sudah digunakan.';
        } else {
          // Cari prodi/angkatan dari pre_students
          $prodi = '';
          $angkatan = 0;
          $ps = db()->query("SELECT prodi, angkatan FROM pre_students WHERE nim='".db()->real_escape_string($nim)."' LIMIT 1");
          if ($ps && $ps->num_rows) {
            $pd = $ps->fetch_assoc();
            $prodi = $pd['prodi'] ?? '';
            $angkatan = intval($pd['angkatan'] ?? 0);
          }

          $hashed = password_hash($password, PASSWORD_DEFAULT);
          $role = 'mahasiswa';
          $stmt = db()->prepare('INSERT INTO users (name, nim, prodi, angkatan, email, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)');
          $stmt->bind_param('sssisss', $name, $nim, $prodi, $angkatan, $email, $hashed, $role);

          if ($stmt->execute()) {
            // Auto-login
            $new_user = [
              'id'       => db()->insert_id,
              'name'     => $name,
              'nim'      => $nim,
              'email'    => $email,
              'role'     => 'mahasiswa',
              'prodi'    => $prodi,
              'angkatan' => $angkatan,
            ];
            session_regenerate_id(true);
            $_SESSION['user'] = $new_user;
            header('Location: ../views/borrow.php');
            exit;
          } else {
            $error = 'Gagal mendaftar. Silakan coba lagi.';
          }
        }
      }
    }
  }
}
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <title>Daftar Akun — PINMES</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    /* ===== ENHANCED STYLING — shared with login ===== */
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
      from { transform: translateY(30px) scale(.97); opacity: 0; }
      to { transform: translateY(0) scale(1); opacity: 1; }
    }

    a {
      color: #38bdf8;
      text-decoration: none;
    }

    a:hover { text-decoration: underline; }

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

    .form-select option { background: #111827; color: #fff; }

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

    .nav-custom .nav-link:hover { color: #94a3b8; }

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
    fieldset {
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

    .btn-primary:active { transform: translateY(0); }

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

    @keyframes spin { to { transform: rotate(360deg); } }

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

    /* ===== INPUT GROUP (CARI NIM/NIDN) ===== */
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

    /* ===== RESULT CARD ===== */
    .lecturer-result,
    #student-result {
      background: #0f172a;
      border: 1px solid #1e293b;
      border-radius: 10px;
      padding: 14px;
      margin-bottom: 1rem;
      display: none;
      animation: panelSlide .3s ease-out;
    }

    .lecturer-result .label,
    #student-result .label {
      color: #64748b;
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: .5px;
      margin-bottom: 2px;
    }

    .lecturer-result .value,
    #student-result .value {
      color: #f1f5f9;
      font-weight: 600;
      font-size: 14px;
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

      .input-icon-wrap .form-control { padding-left: 34px; }
      .input-icon-wrap .input-icon { font-size: 13px; left: 11px; }

      .btn { font-size: 14px; padding: 10px 16px; }

      .toggle-password { font-size: 15px; right: 12px; }

      .input-group .btn {
        font-size: 13px;
        padding: 9px 12px;
        white-space: nowrap;
      }

      #matrix, #network { opacity: 0.12; }
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

<body class="d-flex align-items-center py-5">
  <canvas id="matrix"></canvas>
  <canvas id="network"></canvas>
  <div class="overlay-soft"></div>

  <div class="container position-relative" style="z-index:5">
    <div class="row justify-content-center">
      <div class="col-md-5">
        <div class="card p-4">
          <div class="brand-header">
            <span class="brand-icon">📝</span>
            <h4>Daftar Akun</h4>
            <p>Sistem Peminjaman Teknik Mesin</p>
          </div>

          <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
          <?php endif; ?>

          <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
            <script>
              setTimeout(() => location.href = '../index.php', 2500);
            </script>
          <?php endif; ?>

          <ul class="nav-custom">
            <li class="nav-item">
              <a class="nav-link <?= $user_type === 'mahasiswa' ? 'active-adm' : '' ?>" id="tab-mahasiswa" onclick="switchType('mahasiswa')">🎓 Mahasiswa</a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= $user_type === 'adminreq' ? 'active-adm' : '' ?>" id="tab-adminreq" onclick="switchType('adminreq')">Penanggung Jawab</a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= $user_type === 'kepalareq' ? 'active-adm' : '' ?>" id="tab-kepalareq" onclick="switchType('kepalareq')">Kepala Bengkel</a>
            </li>
          </ul>

          <form method="post" id="registerForm">
            <input type="hidden" name="form_type" value="student_register">

            <!-- Fields for Mahasiswa -->
            <fieldset id="fields-mahasiswa" <?= $user_type === 'mahasiswa' ? '' : 'disabled="disabled" style="display:none"' ?>>
              <div class="mb-3">
                <label class="form-label">📋 NIM <span style="color:#f87171">*</span></label>
                <div class="input-group">
                  <div class="input-icon-wrap" style="flex:1">
                    <i class="fa-solid fa-id-card input-icon"></i>
                    <input type="text" name="nim" id="nim_input" class="form-control"
                           placeholder="Masukkan NIM" required value="<?= e($_POST['nim'] ?? '') ?>">
                  </div>
                  <button type="button" class="btn btn-primary" id="btn_cek_nim">🔍 Cari</button>
                </div>
                <small id="nimStatus" class="form-text" style="color:#64748b">Masukkan NIM untuk mencari data Anda.</small>
              </div>

              <div class="mb-3">
                <label class="form-label">👤 Nama Lengkap <span style="color:#f87171">*</span></label>
                <div class="input-icon-wrap">
                  <i class="fa-solid fa-user input-icon"></i>
                  <input type="text" name="name" id="name_input" class="form-control"
                         placeholder="Nama akan terisi otomatis" required
                         value="<?= e($_POST['name'] ?? '') ?>">
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">✉️ Email <span style="color:#f87171">*</span></label>
                <div class="input-icon-wrap">
                  <i class="fa-solid fa-envelope input-icon"></i>
                  <input type="email" name="email" class="form-control"
                         placeholder="email@contoh.com" required
                         value="<?= e($_POST['email'] ?? '') ?>">
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">🔑 Password <span style="color:#f87171">*</span></label>
                <div class="password-wrapper">
                  <div class="input-icon-wrap">
                    <i class="fa-solid fa-lock input-icon"></i>
                    <input type="password" name="password" id="password-student" class="form-control"
                           placeholder="Minimal 4 karakter" required>
                  </div>
                  <span class="toggle-password" onclick="togglePasswordMahasiswa()">
                    <i id="eyeIconStudent" class="fa-solid fa-eye-slash"></i>
                  </span>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">🔑 Konfirmasi Password <span style="color:#f87171">*</span></label>
                <div class="password-wrapper">
                  <div class="input-icon-wrap">
                    <i class="fa-solid fa-lock input-icon"></i>
                    <input type="password" name="password_confirm" id="password-confirm-student" class="form-control"
                           placeholder="Ulangi password" required>
                  </div>
                  <span class="toggle-password" onclick="togglePasswordConfirmMahasiswa()">
                    <i id="eyeIconStudentConfirm" class="fa-solid fa-eye-slash"></i>
                  </span>
                </div>
              </div>

              <div class="alert-info-custom">
                ✅ Dengan mendaftar, Anda akan langsung masuk ke halaman peminjaman barang.
              </div>
              <button class="btn btn-primary w-100 fw-bold" id="btn-register-student">📝 Daftar & Masuk</button>
            </fieldset>

            <!-- Fields for Penanggung Jawab -->
            <fieldset id="fields-adminreq" disabled="disabled" style="<?= $user_type === 'adminreq' ? '' : 'display:none' ?>">
              <input type="hidden" name="requested_role" value="penanggung jawab">
              <div class="mb-3">
                <label class="form-label">NIDN <span style="color:#f87171">*</span></label>
                <div class="input-group">
                  <div class="input-icon-wrap" style="flex:1">
                    <i class="fa-solid fa-id-card input-icon"></i>
                    <input type="text" name="nomor_induk_adminreq" id="nomor_induk_adminreq" class="form-control" placeholder="Masukkan NIDN" required>
                  </div>
                  <button type="button" class="btn btn-primary" onclick="cekNidnPj()">🔍 Cari</button>
                </div>
                <small id="nidnPjStatus" class="form-text" style="color:#94a3b8">Masukkan NIDN untuk mencari data Anda.</small>
              </div>

              <div id="pj-result" class="lecturer-result" style="display:none">
                <div class="row g-2">
                  <div class="col-12">
                    <div class="label">Nama</div>
                    <div class="value" id="pj-name-display">-</div>
                  </div>
                  <div class="col-12">
                    <div class="label">Prodi</div>
                    <div class="value" id="pj-prodi">-</div>
                    <input type="hidden" name="prodi" id="input-prodi-pj" value="">
                  </div>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">👤 Nama Lengkap <span style="color:#f87171">*</span></label>
                <div class="input-icon-wrap">
                  <i class="fa-solid fa-user input-icon"></i>
                  <input type="text" name="name" id="input-name" class="form-control" placeholder="Nama akan terisi otomatis" required value="">
                </div>
              </div>

              <div id="pj-fields-email" style="display:none">
                <input type="hidden" name="pj_nidn" id="pj-nidn-hidden" value="">
                <div class="mb-3">
                  <label class="form-label">🏭 Bengkel <span style="color:#f87171">*</span></label>
                  <select name="pj_workshop_id" class="form-select" required>
                    <option value="">-- Pilih Bengkel --</option>
                    <?php
                    $ws_list = db()->query("SELECT * FROM workshops ORDER BY name ASC");
                    if ($ws_list) while ($w = $ws_list->fetch_assoc()):
                    ?>
                      <option value="<?= intval($w['id']) ?>"><?= e($w['name']) ?></option>
                    <?php endwhile; ?>
                  </select>
                </div>
                <div class="mb-3">
                  <label class="form-label">✉️ Email <span style="color:#f87171">*</span></label>
                  <div class="input-icon-wrap">
                    <i class="fa-solid fa-envelope input-icon"></i>
                    <input type="email" name="adm_email" class="form-control" placeholder="email@contoh.com" required>
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-label">🔑 Password <span style="color:#f87171">*</span></label>
                  <div class="password-wrapper">
                    <div class="input-icon-wrap">
                      <i class="fa-solid fa-lock input-icon"></i>
                      <input type="password" name="adm_password" id="password-adm" class="form-control" placeholder="Minimal 4 karakter" required>
                    </div>
                    <span class="toggle-password" onclick="togglePasswordAdm()">
                      <i id="eyeIconAdm" class="fa-solid fa-eye-slash"></i>
                    </span>
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-label">🔑 Konfirmasi Password <span style="color:#f87171">*</span></label>
                  <div class="password-wrapper">
                    <div class="input-icon-wrap">
                      <i class="fa-solid fa-lock input-icon"></i>
                      <input type="password" name="adm_password_confirm" id="password-adm-confirm" class="form-control" placeholder="Ulangi password" required>
                    </div>
                    <span class="toggle-password" onclick="togglePasswordConfirmAdm()">
                      <i id="eyeIconAdmConfirm" class="fa-solid fa-eye-slash"></i>
                    </span>
                  </div>
                </div>
                <div class="alert-info-custom">
                  ✅ Setelah mendaftar, permintaan Anda akan ditinjau oleh Admin.
                </div>
                <button class="btn btn-primary w-100 fw-bold" id="btn-register-pj">📝 Kirim Permintaan</button>
              </div>
            </fieldset>

            <!-- Fields for Kepala Bengkel -->
            <fieldset id="fields-kepalareq" disabled="disabled" style="display:none">
              <input type="hidden" name="requested_role" value="kepala">
              <div class="mb-3">
                <label class="form-label">NIDN <span style="color:#f87171">*</span></label>
                <div class="input-group">
                  <div class="input-icon-wrap" style="flex:1">
                    <i class="fa-solid fa-id-card input-icon"></i>
                    <input type="text" name="nomor_induk_kepala" id="nomor_induk_kepala" class="form-control" placeholder="Masukkan NIDN" required>
                  </div>
                  <button type="button" class="btn btn-primary" onclick="cekNidnKepala()">🔍 Cari</button>
                </div>
                <small id="nidnKepalaStatus" class="form-text" style="color:#94a3b8">Masukkan NIDN untuk mencari data Anda.</small>
              </div>

              <div id="kepala-result" class="lecturer-result" style="display:none">
                <div class="row g-2">
                  <div class="col-12">
                    <div class="label">Nama</div>
                    <div class="value" id="kepala-name-display">-</div>
                  </div>
                  <div class="col-12">
                    <div class="label">Prodi</div>
                    <div class="value" id="kepala-prodi">-</div>
                    <input type="hidden" name="prodi" id="input-prodi-kepala" value="">
                  </div>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">👤 Nama Lengkap <span style="color:#f87171">*</span></label>
                <div class="input-icon-wrap">
                  <i class="fa-solid fa-user input-icon"></i>
                  <input type="text" name="name" id="input-name-kepala" class="form-control" placeholder="Nama akan terisi otomatis" required value="">
                </div>
              </div>

              <div id="kepala-fields-email" style="display:none">
                <input type="hidden" name="pj_nidn" id="kepala-nidn-hidden" value="">
                <div class="mb-3">
                  <label class="form-label">🏭 Bengkel <span style="color:#f87171">*</span></label>
                  <select name="pj_workshop_id" class="form-select" required>
                    <option value="">-- Pilih Bengkel --</option>
                    <?php
                    $ws_list = db()->query("SELECT * FROM workshops ORDER BY name ASC");
                    if ($ws_list) while ($w = $ws_list->fetch_assoc()):
                    ?>
                      <option value="<?= intval($w['id']) ?>"><?= e($w['name']) ?></option>
                    <?php endwhile; ?>
                  </select>
                </div>
                <div class="mb-3">
                  <label class="form-label">✉️ Email <span style="color:#f87171">*</span></label>
                  <div class="input-icon-wrap">
                    <i class="fa-solid fa-envelope input-icon"></i>
                    <input type="email" name="adm_email" class="form-control" placeholder="email@contoh.com" required>
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-label">🔑 Password <span style="color:#f87171">*</span></label>
                  <div class="password-wrapper">
                    <div class="input-icon-wrap">
                      <i class="fa-solid fa-lock input-icon"></i>
                      <input type="password" name="adm_password" id="password-kepala" class="form-control" placeholder="Minimal 4 karakter" required>
                    </div>
                    <span class="toggle-password" onclick="togglePasswordKepala()">
                      <i id="eyeIconKepala" class="fa-solid fa-eye-slash"></i>
                    </span>
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-label">🔑 Konfirmasi Password <span style="color:#f87171">*</span></label>
                  <div class="password-wrapper">
                    <div class="input-icon-wrap">
                      <i class="fa-solid fa-lock input-icon"></i>
                      <input type="password" name="kepala_password_confirm" id="password-kepala-confirm" class="form-control" placeholder="Ulangi password" required>
                    </div>
                    <span class="toggle-password" onclick="togglePasswordConfirmKepala()">
                      <i id="eyeIconKepalaConfirm" class="fa-solid fa-eye-slash"></i>
                    </span>
                  </div>
                </div>
                <div class="alert-info-custom">
                  ✅ Setelah mendaftar, permintaan Anda akan ditinjau oleh Admin.
                </div>
                <button class="btn btn-primary w-100 fw-bold" id="btn-register-kepala">📝 Kirim Permintaan</button>
              </div>
            </fieldset>
          </form>

          <p class="text-center mt-3">
            Sudah punya akun? <a href="../index.php">Login di sini</a>
          </p>
          <p class="text-center">
            <a href="../index.php" class="btn btn-outline-primary">Kembali</a>
          </p>
        </div>
      </div>
    </div>
  </div>

  <script>
    const m = document.getElementById('matrix'),
      c = m.getContext('2d');
    m.width = innerWidth;
    m.height = innerHeight;
    const ch = "01SYSTEMDATA",
      fs = 14,
      cols = m.width / fs,
      d = Array(cols).fill(1);
    setInterval(() => {
      c.fillStyle = "rgba(11,18,32,.08)";
      c.fillRect(0, 0, m.width, m.height);
      c.fillStyle = "#60a5fa";
      c.font = fs + "px monospace";
      d.forEach((y, i) => {
        c.fillText(ch[Math.random() * ch.length | 0], i * fs, y * fs);
        d[i] = y * fs > m.height && Math.random() > .98 ? 0 : y + 1;
      });
    }, 45);
  </script>

  <script>
    const n = document.getElementById('network'),
      x = n.getContext('2d');
    n.width = innerWidth;
    n.height = innerHeight;
    const mouse = {
      x: null,
      y: null
    };
    addEventListener('mousemove', e => {
      mouse.x = e.clientX;
      mouse.y = e.clientY
    });
    const nodes = [...Array(60)].map(() => ({
      x: Math.random() * n.width,
      y: Math.random() * n.height,
      vx: (Math.random() - .5) * .4,
      vy: (Math.random() - .5) * .4
    }));
    (function anim() {
      x.clearRect(0, 0, n.width, n.height);
      nodes.forEach((a, i) => {
        if (mouse.x) {
          const dx = mouse.x - a.x,
            dy = mouse.y - a.y,
            d = Math.hypot(dx, dy);
          if (d < 180) {
            a.vx += dx / d * .015;
            a.vy += dy / d * .015;
          }
        }
        a.x += a.vx;
        a.y += a.vy;
        x.fillStyle = "rgba(96,165,250,.9)";
        x.beginPath();
        x.arc(a.x, a.y, 2.2, 0, 7);
        x.fill();
        for (let j = i + 1; j < nodes.length; j++) {
          const b = nodes[j],
            d = Math.hypot(a.x - b.x, a.y - b.y);
          if (d < 130) {
            x.strokeStyle = `rgba(96,165,250,${1-d/130})`;
            x.beginPath();
            x.moveTo(a.x, a.y);
            x.lineTo(b.x, b.y);
            x.stroke();
          }
        }
      });
      requestAnimationFrame(anim);
    })();
  </script>

  <script>
    const tabMahasiswa = document.getElementById('tab-mahasiswa');
    const tabAdm = document.getElementById('tab-adminreq');
    const tabKepala = document.getElementById('tab-kepalareq');

    function switchType(type) {
      var formType = type === 'mahasiswa' ? 'student_register' : (type === 'adminreq' ? 'admin_request' : 'kepala_request');
      document.querySelector('input[name="form_type"]').value = formType;

      ['fields-mahasiswa', 'fields-adminreq', 'fields-kepalareq'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) {
          var thisType = id.replace('fields-', '');
          el.disabled = thisType !== type;
          el.style.display = thisType === type ? '' : 'none';
        }
      });

      tabMahasiswa.className = 'nav-link' + (type === 'mahasiswa' ? ' active-adm' : '');
      tabAdm.className = 'nav-link' + (type === 'adminreq' ? ' active-adm' : '');
      tabKepala.className = 'nav-link' + (type === 'kepalareq' ? ' active-adm' : '');

      document.getElementById('pj-result').style.display = 'none';
      document.getElementById('pj-fields-email').style.display = 'none';
      document.getElementById('kepala-result').style.display = 'none';
      document.getElementById('kepala-fields-email').style.display = 'none';
      document.getElementById('nidnPjStatus').innerHTML = '';
      document.getElementById('nidnKepalaStatus').innerHTML = '';
    }

    switchType('<?= $user_type ?>');

    function cekNidnPj() {
      const v = document.getElementById('nomor_induk_adminreq').value.trim();
      const s = document.getElementById('nidnPjStatus');
      s.innerHTML = '';
      document.getElementById('pj-result').style.display = 'none';
      document.getElementById('pj-fields-email').style.display = 'none';

      if (v.length < 3) {
        s.innerHTML = 'Masukkan NIDN minimal 3 karakter';
        s.style.color = '#f87171';
        return;
      }

      s.innerHTML = '🔍 Mencari...';
      s.style.color = '#60a5fa';

      fetch('../cek_nim.php?nim=' + encodeURIComponent(v) + '&type=dosen')
        .then(r => r.json())
        .then(d => {
          if (d.status === 'found') {
            s.innerHTML = '✅ Data ditemukan. Nama: <strong>' + d.data.name + '</strong>';
            s.style.color = '#22c55e';
            document.getElementById('pj-name-display').textContent = d.data.name;
            document.getElementById('pj-prodi').textContent = d.data.prodi;
            const nameInput = document.getElementById('input-name');
            nameInput.value = d.data.name;
            nameInput.readOnly = true;
            document.getElementById('input-prodi-pj').value = d.data.prodi;
            document.getElementById('pj-nidn-hidden').value = v;
            document.getElementById('pj-result').style.display = 'block';
            document.getElementById('pj-fields-email').style.display = 'block';
          } else if (d.status === 'registered') {
            s.innerHTML = '❌ ' + d.message;
            s.style.color = '#f87171';
          } else {
            s.innerHTML = '❌ ' + d.message;
            s.style.color = '#f87171';
          }
        })
        .catch(() => {
          s.innerHTML = '❌ Gagal menghubungi server';
          s.style.color = '#f87171';
        });
    }

    document.getElementById('nomor_induk_adminreq').addEventListener('keydown', e => {
      if (e.key === 'Enter') {
        e.preventDefault();
        cekNidnPj();
      }
    });

    function cekNidnKepala() {
      const v = document.getElementById('nomor_induk_kepala').value.trim();
      const s = document.getElementById('nidnKepalaStatus');
      s.innerHTML = '';
      document.getElementById('kepala-result').style.display = 'none';
      document.getElementById('kepala-fields-email').style.display = 'none';

      if (v.length < 3) {
        s.innerHTML = 'Masukkan NIDN minimal 3 karakter';
        s.style.color = '#f87171';
        return;
      }

      s.innerHTML = '🔍 Mencari...';
      s.style.color = '#60a5fa';

      fetch('../cek_nim.php?nim=' + encodeURIComponent(v) + '&type=dosen')
        .then(r => r.json())
        .then(d => {
          if (d.status === 'found') {
            s.innerHTML = '✅ Data ditemukan. Nama: <strong>' + d.data.name + '</strong>';
            s.style.color = '#22c55e';
            document.getElementById('kepala-name-display').textContent = d.data.name;
            document.getElementById('kepala-prodi').textContent = d.data.prodi;
            const nameInput = document.getElementById('input-name-kepala');
            nameInput.value = d.data.name;
            nameInput.readOnly = true;
            document.getElementById('input-prodi-kepala').value = d.data.prodi;
            document.getElementById('kepala-nidn-hidden').value = v;
            document.getElementById('kepala-result').style.display = 'block';
            document.getElementById('kepala-fields-email').style.display = 'block';
          } else if (d.status === 'registered') {
            s.innerHTML = '❌ ' + d.message;
            s.style.color = '#f87171';
          } else {
            s.innerHTML = '❌ ' + d.message;
            s.style.color = '#f87171';
          }
        })
        .catch(() => {
          s.innerHTML = '❌ Gagal menghubungi server';
          s.style.color = '#f87171';
        });
    }

    document.getElementById('nomor_induk_kepala').addEventListener('keydown', e => {
      if (e.key === 'Enter') {
        e.preventDefault();
        cekNidnKepala();
      }
    });
  </script>

  <script>
    function togglePasswordAdm() {
      const p = document.getElementById('password-adm');
      const e = document.getElementById('eyeIconAdm');
      if (p.type === 'password') {
        p.type = 'text';
        e.classList.replace('fa-eye-slash', 'fa-eye');
      } else {
        p.type = 'password';
        e.classList.replace('fa-eye', 'fa-eye-slash');
      }
    }

    function togglePasswordConfirmAdm() {
      const p = document.getElementById('password-adm-confirm');
      const e = document.getElementById('eyeIconAdmConfirm');
      if (p.type === 'password') {
        p.type = 'text';
        e.classList.replace('fa-eye-slash', 'fa-eye');
      } else {
        p.type = 'password';
        e.classList.replace('fa-eye', 'fa-eye-slash');
      }
    }

    function togglePasswordKepala() {
      const p = document.getElementById('password-kepala');
      const e = document.getElementById('eyeIconKepala');
      if (p.type === 'password') {
        p.type = 'text';
        e.classList.replace('fa-eye-slash', 'fa-eye');
      } else {
        p.type = 'password';
        e.classList.replace('fa-eye', 'fa-eye-slash');
      }
    }

    function togglePasswordConfirmKepala() {
      const p = document.getElementById('password-kepala-confirm');
      const e = document.getElementById('eyeIconKepalaConfirm');
      if (p.type === 'password') {
        p.type = 'text';
        e.classList.replace('fa-eye-slash', 'fa-eye');
      } else {
        p.type = 'password';
        e.classList.replace('fa-eye', 'fa-eye-slash');
      }
    }

    function togglePasswordMahasiswa() {
      const p = document.getElementById('password-student');
      const e = document.getElementById('eyeIconStudent');
      if (p.type === 'password') {
        p.type = 'text';
        e.classList.replace('fa-eye-slash', 'fa-eye');
      } else {
        p.type = 'password';
        e.classList.replace('fa-eye', 'fa-eye-slash');
      }
    }

    function togglePasswordConfirmMahasiswa() {
      const p = document.getElementById('password-confirm-student');
      const e = document.getElementById('eyeIconStudentConfirm');
      if (p.type === 'password') {
        p.type = 'text';
        e.classList.replace('fa-eye-slash', 'fa-eye');
      } else {
        p.type = 'password';
        e.classList.replace('fa-eye', 'fa-eye-slash');
      }
    }
  </script>

  <!-- NIM Lookup for Mahasiswa -->
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    const nimInput  = document.getElementById('nim_input');
    const btnCekNim = document.getElementById('btn_cek_nim');
    const nimStatus = document.getElementById('nimStatus');
    const nameInput = document.getElementById('name_input');

    if (!nimInput) return;

    let nimTimer = null;

    function cekNim() {
      const v = nimInput.value.trim();
      if (v.length < 3) {
        nimStatus.innerHTML = 'Masukkan NIM minimal 3 karakter';
        nimStatus.style.color = '#f87171';
        return;
      }

      nimStatus.innerHTML = '🔍 Mencari...';
      nimStatus.style.color = '#60a5fa';

      fetch('../cek_nim.php?nim=' + encodeURIComponent(v))
        .then(r => r.json())
        .then(d => {
          if (d.status === 'found' || d.status === 'registered') {
            const nama = d.data?.name || '';
            if (nama) {
              nameInput.value = nama;
              nameInput.readOnly = true;
              nimStatus.innerHTML = '✅ Data ditemukan. Nama: <strong>' + nama + '</strong>';
              nimStatus.style.color = '#22c55e';
            } else {
              nimStatus.innerHTML = '✅ NIM ditemukan. Silakan lengkapi nama.';
              nimStatus.style.color = '#22c55e';
            }
          } else {
            nimStatus.innerHTML = '❌ ' + (d.message || 'NIM tidak ditemukan. Isi nama manual.');
            nimStatus.style.color = '#f87171';
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