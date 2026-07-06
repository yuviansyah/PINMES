<?php
require_once '../controller/config.php';
if (empty($_SESSION['user'])) { header('Location: home.php'); exit; }
$user     = $_SESSION['user'];
$is_guest = false;
$current  = basename($_SERVER['PHP_SELF']);
$msg      = '';
$msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $msg = '';
    $msg_type = 'success';

    // Form Ganti Foto
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['photo'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $name = uniqid('photo_').'.'.$ext;
                move_uploaded_file($file['tmp_name'], '../uploads/'.$name);
                if (!empty($user['photo']) && file_exists('../uploads/'.$user['photo'])) unlink('../uploads/'.$user['photo']);
                db()->query("UPDATE users SET photo='$name' WHERE id=".intval($user['id']));
                $user['photo'] = $name;
                $_SESSION['user']['photo'] = $name;
                $msg = 'Foto profil berhasil diperbarui.';
            } else { $msg = 'Format foto tidak didukung.'; $msg_type = 'danger'; }
        } else { $msg = 'Gagal mengupload foto.'; $msg_type = 'danger'; }
    }

    // Form Ganti Password
    if (isset($_POST['password_baru'])) {
        $password_lama = $_POST['password_lama'] ?? '';
        $password_baru = $_POST['password_baru'] ?? '';
        $password_konf = $_POST['password_konf'] ?? '';
        if ($password_lama === '') { $msg = 'Password lama wajib diisi.'; $msg_type = 'danger'; }
        elseif ($password_baru === '') { $msg = 'Password baru wajib diisi.'; $msg_type = 'danger'; }
        elseif ($password_konf === '') { $msg = 'Konfirmasi password baru wajib diisi.'; $msg_type = 'danger'; }
        elseif ($password_baru !== $password_konf) { $msg = 'Konfirmasi password tidak cocok.'; $msg_type = 'danger'; }
        else {
            $row = db()->query("SELECT password FROM users WHERE id=".intval($user['id']))->fetch_assoc();
            if (!$row || !password_verify($password_lama, $row['password'])) {
                $msg = 'Password lama salah.'; $msg_type = 'danger';
            } else {
                $hashed = password_hash($password_baru, PASSWORD_DEFAULT);
                $stmt   = db()->prepare('UPDATE users SET password=? WHERE id=?');
                $stmt->bind_param('si', $hashed, $user['id']);
                if ($stmt->execute()) { $msg = 'Password berhasil diperbarui.'; }
                else { $msg_type = 'danger'; $msg = 'Gagal memperbarui password.'; }
                $stmt->close();
            }
        }
    }
}

$res     = db()->query("SELECT * FROM users WHERE id=".intval($user['id']));
$profile = $res->fetch_assoc();
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Profil Saya — PINMES</title>
  <meta name="description" content="Kelola profil dan pengaturan akun PINMES Anda.">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/theme.css">
  <style>
    .page-wrapper { max-width: 620px; }
    .profile-avatar {
      width: 96px; height: 96px; border-radius: 50%; object-fit: cover;
      border: 3px solid rgba(56,189,248,0.4); display: block; margin: 0 auto 12px;
    }
    .profile-initial {
      width: 96px; height: 96px; border-radius: 50%;
      background: linear-gradient(135deg, #1e3a5f, #1e3a8a);
      color: #7dd3fc;
      display: flex; align-items: center; justify-content: center;
      font-size: 2.5rem; font-weight: 800; margin: 0 auto 12px;
      border: 3px solid rgba(56,189,248,0.25);
    }
    .info-label { color: var(--muted); font-size: .78rem; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 2px; }
    .info-value { color: var(--text); font-size: .95rem; margin-bottom: 14px; font-weight: 500; }
    .role-badge {
      display: inline-block; padding: 4px 14px;
      background: rgba(56,189,248,0.15); color: #38bdf8;
      border-radius: 999px; font-size: .78rem; font-weight: 700;
    }
  </style>
</head>
<body>
<canvas id="tech-bg"></canvas>
<img src="../assets/img/dekor-kiri.png"  class="fixed-left"  alt="">
<img src="../assets/img/dekor-kanan.png" class="fixed-right" alt="">

<?php include 'partials/navbar.php'; ?>

<div class="page-wrapper">

  <?php if ($msg): ?>
    <div class="alert-pinba <?= $msg_type==='danger'?'danger':'success' ?> mb-4"><?= e($msg) ?></div>
  <?php endif; ?>

  <!-- Avatar & Identity -->
  <div class="card-panel text-center mb-4">
    <?php if ($profile['photo']): ?>
      <img src="../uploads/<?= e($profile['photo']) ?>" class="profile-avatar" alt="Foto profil">
    <?php else: ?>
      <div class="profile-initial"><?= e(strtoupper(substr($profile['name'],0,1))) ?></div>
    <?php endif; ?>
    <h4 style="margin-bottom:4px;font-weight:700"><?= e($profile['name']) ?></h4>
    <small style="color:var(--muted)"><?= e($profile['email']) ?></small>
    <div class="mt-2">
      <span class="role-badge"><?= e(ucfirst($profile['role'])) ?></span>
    </div>
  </div>

  <!-- Account Info -->
  <div class="card-panel mb-4">
    <h5 style="font-size:.95rem;font-weight:700;margin-bottom:16px;color:var(--accent-sky)">📋 Informasi Akun</h5>
    <?php if (in_array(($profile['role']??''), ['penanggung jawab', 'kepala', 'kepala_bengkel'])): ?>
    <div class="info-label">NIDN</div>
    <div class="info-value text-sky"><?= e($profile['nim']??'-') ?></div>
    <?php endif; ?>
    <div class="info-label">Role</div>
    <div class="info-value"><?= e(ucfirst($profile['role'])) ?></div>
    <?php if (in_array($profile['role']??'', ['penanggung jawab'])): ?>
    <?php
      $ws_id = intval($profile['workshop_id'] ?? 0);
      $ws_name = '-';
      if ($ws_id > 0) {
          $ws_r = db()->query("SELECT name FROM workshops WHERE id=$ws_id");
          $ws_name = $ws_r ? $ws_r->fetch_assoc()['name'] : '-';
      }
    ?>
    <div class="info-label">Bengkel</div>
    <div class="info-value"><?= e($ws_name) ?></div>
    <?php endif; ?>
    <div class="info-label">Terdaftar Sejak</div>
    <div class="info-value" style="margin-bottom:0"><?= e(bulan_tgl($profile['created_at'])) ?></div>
  </div>

  <!-- Ganti Foto -->
  <div class="card-panel mb-4">
    <h5 style="font-size:.95rem;font-weight:700;margin-bottom:16px;color:var(--accent-sky)">📸 Ganti Foto Profil</h5>
    <form method="post" enctype="multipart/form-data">
      <div class="mb-3">
        <input type="file" name="photo" class="form-control" accept="image/*" onchange="previewPhoto(event)">
        <div id="previewWrap" style="display:none;margin-top:8px">
          <img id="previewImg" style="width:56px;height:56px;border-radius:50%;object-fit:cover;border:2px solid rgba(56,189,248,0.3)">
        </div>
      </div>
      <div class="text-center"><button type="submit" class="btn btn-primary">💾 Simpan Foto</button></div>
    </form>
  </div>

  <!-- Ganti Password -->
  <div class="card-panel mb-4">
    <h5 style="font-size:.95rem;font-weight:700;margin-bottom:16px;color:var(--accent-sky)">🔑 Ganti Password</h5>
    <form method="post">
      <div class="mb-3">
        <label class="form-label">Password Lama</label>
        <input type="password" name="password_lama" class="form-control" placeholder="Masukkan password lama" autocomplete="current-password">
      </div>
      <div class="mb-3">
        <label class="form-label">Password Baru</label>
        <input type="password" name="password_baru" class="form-control" placeholder="Masukkan password baru" autocomplete="new-password">
      </div>
      <div class="mb-3">
        <label class="form-label">Konfirmasi Password Baru</label>
        <input type="password" name="password_konf" class="form-control" placeholder="Ulangi password baru" autocomplete="new-password">
      </div>
      <div class="text-center"><button type="submit" class="btn btn-primary">💾 Simpan Password</button></div>
    </form>
  </div>

  <footer class="pinba-footer">© <?= date('Y') ?> PINMES</footer>
</div>

<script>
function previewPhoto(e) {
  const file = e.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = function(ev) {
    document.getElementById('previewImg').src = ev.target.result;
    document.getElementById('previewWrap').style.display = 'block';
  };
  reader.readAsDataURL(file);
}
</script>
<?php include 'partials/scripts.php'; ?>
</body>
</html>