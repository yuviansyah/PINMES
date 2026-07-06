<?php
session_start();
$is_guest = $_SESSION['guest_mode'] ?? false;
require_once '../controller/config.php';
if (empty($_SESSION['user'])) { header('Location: ../index.php'); exit; }
$user = $_SESSION['user'];
if (!in_array($user['role'], ['admin','kepala','kepala_bengkel','penanggung jawab'])) { echo 'Akses ditolak.'; exit; }

$current = basename($_SERVER['PHP_SELF']);

$edit = null;
$msg = '';
$msg_type = 'success';

$search = trim($_GET['search'] ?? '');
$angkatan_filter = $_GET['angkatan'] ?? '';

function handle_upload($file, $old_photo = null) {
    if ($file['error'] !== UPLOAD_ERR_OK) return $old_photo;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) return $old_photo;
    $name = uniqid('photo_') . '.' . $ext;
    move_uploaded_file($file['tmp_name'], '../uploads/' . $name);
    if ($old_photo && file_exists('../uploads/' . $old_photo)) unlink('../uploads/' . $old_photo);
    return $name;
}

// Process form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $nim = trim($_POST['nim'] ?? '');
    $prodi = trim($_POST['prodi'] ?? '');
    $angkatan = trim($_POST['angkatan'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($angkatan === '') $angkatan = null;
    if (!$name || !$nim || !$email) {
        $msg = 'Nama, NIM, dan Email wajib diisi.';
        $msg_type = 'danger';
    } else {
        if ($id > 0) {
            // UPDATE
            $photo = null;
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                $old = db()->query("SELECT photo FROM users WHERE id=$id")->fetch_assoc();
                $photo = handle_upload($_FILES['photo'], $old['photo'] ?? null);
            }
            if ($password !== '') {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = db()->prepare('UPDATE users SET name=?, nim=?, prodi=?, angkatan=?, email=?, password=?' . ($photo ? ', photo=?' : '') . ' WHERE id=?');
                $photo ? $stmt->bind_param('sssssssi', $name, $nim, $prodi, $angkatan, $email, $hashed, $photo, $id) : $stmt->bind_param('ssssssi', $name, $nim, $prodi, $angkatan, $email, $hashed, $id);
            } else {
                $stmt = db()->prepare('UPDATE users SET name=?, nim=?, prodi=?, angkatan=?, email=?' . ($photo ? ', photo=?' : '') . ' WHERE id=?');
                $photo ? $stmt->bind_param('ssssssi', $name, $nim, $prodi, $angkatan, $email, $photo, $id) : $stmt->bind_param('sssssi', $name, $nim, $prodi, $angkatan, $email, $id);
            }
            if ($stmt->execute()) {
                $msg = 'Mahasiswa berhasil diperbarui.';
            } else {
                $msg = 'Gagal memperbarui: ' . db()->error;
                $msg_type = 'danger';
            }
            $stmt->close();
        } else {
            // INSERT
            $photo = null;
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                $photo = handle_upload($_FILES['photo']);
            }
            $hashed = password_hash($password ?: '123456', PASSWORD_DEFAULT);
            $user_role = $_POST['user_role'] ?? 'mahasiswa';
            $stmt = db()->prepare('INSERT INTO users (name, nim, prodi, angkatan, email, password, role, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('ssssssss', $name, $nim, $prodi, $angkatan, $email, $hashed, $user_role, $photo);
            if ($stmt->execute()) {
                $msg = 'Mahasiswa berhasil ditambahkan.';
            } else {
                $msg = 'Gagal menambahkan: ' . db()->error;
                $msg_type = 'danger';
            }
            $stmt->close();
        }
    }
}

// Delete
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    if ($del_id === intval($user['id'])) {
        $msg = 'Tidak dapat menghapus akun sendiri.';
        $msg_type = 'danger';
    } else {
        $old = db()->query("SELECT photo FROM users WHERE id=$del_id AND (role='mahasiswa' OR role='dosen')")->fetch_assoc();
        $stmt = db()->prepare('DELETE FROM users WHERE id=? AND (role="mahasiswa" OR role="dosen")');
        $stmt->bind_param('i', $del_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            if ($old && $old['photo'] && file_exists('../uploads/' . $old['photo'])) unlink('../uploads/' . $old['photo']);
            $msg = 'Mahasiswa berhasil dihapus.';
        } else {
            $msg = 'Mahasiswa tidak ditemukan.';
            $msg_type = 'danger';
        }
        $stmt->close();
    }
}

// Load edit data
if (isset($_GET['id']) && !isset($_GET['delete'])) {
    $eid = intval($_GET['id']);
    $res = db()->query("SELECT * FROM users WHERE id=$eid AND (role='mahasiswa' OR role='dosen')");
    $edit = $res->fetch_assoc();
}

// Build query
$where = "WHERE role IN ('mahasiswa','dosen')";
$params = [];
$types = '';

if ($search !== '') {
    $where .= " AND (name LIKE ? OR nim LIKE ? OR email LIKE ?)";
    $like = "%$search%";
    $params = [$like, $like, $like];
    $types = 'sss';
}
if ($angkatan_filter !== '') {
    $where .= " AND angkatan = ?";
    $params[] = $angkatan_filter;
    $types .= 's';
}

$sql = "SELECT * FROM users $where ORDER BY created_at DESC";
$stmt = db()->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$students = $stmt->get_result();

// Get distinct angkatan for filter
$angkatans = db()->query("SELECT DISTINCT angkatan FROM users WHERE role IN ('mahasiswa','dosen') AND angkatan IS NOT NULL AND angkatan != '' ORDER BY angkatan DESC");

$current_url = $_SERVER['PHP_SELF'];
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Data Mahasiswa — PINMES Admin</title>
  <meta name="description" content="Kelola data mahasiswa pengguna PINMES.">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/theme.css">
  <style>
  .badge-angkatan {
    display: inline-block;
    padding: 2px 12px;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    background: rgba(37, 99, 235, 0.2);
    color: #93c5fd;
    border: 1px solid rgba(37, 99, 235, 0.3);
  }

  /* Profile overlay */
  #profileOverlay {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(0,0,0,0.65);
    align-items: center;
    justify-content: center;
  }
  #profileOverlay.show { display: flex; }
  #profileBox {
    background: #0f172a;
    border: 1px solid #334155;
    border-radius: 12px;
    padding: 28px;
    max-width: 460px;
    width: 90%;
    color: #e2e8f0;
    box-shadow: 0 0 40px rgba(37,99,235,0.2);
    position: relative;
    animation: fadeIn 0.2s ease;
  }
  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
  }
  #profileBox .close-btn {
    position: absolute;
    top: 12px;
    right: 16px;
    font-size: 1.5rem;
    color: #94a3b8;
    cursor: pointer;
    background: none;
    border: none;
    line-height: 1;
  }
  #profileBox .close-btn:hover { color: #fff; }
  #profileBox .avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: #1e3a5f;
    color: #7dd3fc;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: 700;
    margin: 0 auto 12px;
  }
  #profileBox table td {
    background: transparent !important;
    color: #e2e8f0;
    border: none;
    padding: 8px 4px;
  }
  #profileBox table td:first-child { color: #94a3b8; width: 110px; }
  #profileBox .text-muted { color: #94a3b8; }
  </style>
</head>
<body>
<canvas id="tech-bg"></canvas>
<?php $base_path='..'; include 'partials/navbar.php'; ?>

<div class="page-wrapper">
  <h1 class="page-heading">🎓 Data Mahasiswa</h1>

  <?php if ($msg): ?>
    <div class="alert-pinba <?= $msg_type === 'danger' ? 'danger' : 'success' ?> mb-4"><?= e($msg) ?></div>
  <?php endif; ?>

    <!-- Add / Edit Form -->
    <div class="card-panel mb-4">
      <h5 class="mb-3"><?= $edit ? '✏️ Edit User' : '➕ Tambah User' ?></h5>
      <form method="post" class="row g-3" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= $edit ? intval($edit['id']) : 0 ?>">
        <div class="col-md-3">
          <label class="form-label">Nama Lengkap</label>
          <input type="text" name="name" class="form-control" required value="<?= $edit ? e($edit['name']) : '' ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">NIM</label>
          <input type="text" name="nim" class="form-control" required value="<?= $edit ? e($edit['nim']) : '' ?>">
        </div>
        <div class="col-md-1">
          <label class="form-label">Role</label>
          <select name="user_role" class="form-select">
            <option value="mahasiswa" <?= $edit && $edit['role']==='mahasiswa'?'selected':'' ?>>Mahasiswa</option>
            <option value="dosen" <?= $edit && $edit['role']==='dosen'?'selected':'' ?>>Dosen</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Prodi</label>
          <input type="text" name="prodi" class="form-control" value="<?= $edit ? e($edit['prodi'] ?? '') : '' ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Angkatan</label>
          <select name="angkatan" class="form-select">
            <option value="">-- Pilih --</option>
            <?php for ($y = intval(date('Y')) + 4; $y >= 2000; $y--): ?>
              <option value="<?= $y ?>" <?= $edit && intval($edit['angkatan']) === $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" required value="<?= $edit ? e($edit['email']) : '' ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Password <?= $edit ? '<small class="text-muted">(kosongi jika tidak diubah)</small>' : '' ?></label>
          <input type="password" name="password" class="form-control" <?= $edit ? '' : 'required' ?>>
        </div>
        <div class="col-md-2">
          <label class="form-label">Foto</label>
          <input type="file" name="photo" class="form-control" accept="image/*" onchange="previewPhoto(event)">
          <?php if ($edit && $edit['photo']): ?>
            <small style="color:#94a3b8;">Biarkan kosong jika tidak ingin diganti</small>
          <?php endif; ?>
        </div>
        <?php if ($edit && $edit['photo']): ?>
        <div class="col-md-1 d-flex align-items-end">
          <img src="../uploads/<?= e($edit['photo']) ?>" id="photoPreview" style="width:50px;height:50px;border-radius:50%;object-fit:cover;border:2px solid #334155;">
        </div>
        <?php else: ?>
        <div class="col-md-1 d-flex align-items-end">
          <div id="photoPreview" style="display:none;width:50px;height:50px;border-radius:50%;object-fit:cover;border:2px solid #334155;"></div>
        </div>
        <?php endif; ?>
        <div class="col-12 text-end">
          <button class="btn btn-primary"><?= $edit ? 'Simpan Perubahan' : 'Tambah Mahasiswa' ?></button>
          <?php if($edit): ?><a href="students.php" class="btn btn-secondary ms-2">Batal</a><?php endif; ?>
        </div>
      </form>
    </div>
    <script>
    function previewPhoto(e) {
      const file = e.target.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = function(ev) {
        const preview = document.getElementById('photoPreview');
        if (preview.tagName === 'DIV') {
          preview.style.display = 'block';
          preview.style.backgroundImage = `url(${ev.target.result})`;
          preview.style.backgroundSize = 'cover';
          preview.style.backgroundPosition = 'center';
        } else {
          preview.src = ev.target.result;
          preview.style.display = 'block';
        }
      };
      reader.readAsDataURL(file);
    }
    </script>

    <!-- Search & Filter -->
    <div class="card-panel mb-4">
      <form method="get" action="" class="row g-3 align-items-end">
        <div class="col-md-5">
          <label class="form-label">Cari Nama / NIM / Email</label>
          <input type="text" name="search" class="form-control" placeholder="Ketik kata kunci..." value="<?= e($search) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Filter Angkatan</label>
          <select name="angkatan" class="form-select">
            <option value="">Semua Angkatan</option>
            <?php while($a = $angkatans->fetch_assoc()): ?>
              <option value="<?= e($a['angkatan']) ?>" <?= $angkatan_filter === $a['angkatan'] ? 'selected' : '' ?>><?= e($a['angkatan']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-primary w-100">🔍 Cari</button>
        </div>
        <div class="col-md-2">
          <a href="students.php" class="btn btn-secondary w-100">↻ Reset</a>
        </div>
      </form>
    </div>

    <!-- Table -->
    <div class="card-panel">
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th style="width:50px">Foto</th>
              <th>No</th>
              <th>Nama</th>
              <th>NIM</th>
              <th>Prodi</th>
              <th>Angkatan</th>
              <th>Email</th>
              <th>Terdaftar</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($students && $students->num_rows > 0): $no = 1; while ($s = $students->fetch_assoc()): ?>
              <tr>
                <td>
                  <?php if ($s['photo']): ?>
                    <img src="../uploads/<?= e($s['photo']) ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid #334155;">
                  <?php else: ?>
                    <div style="width:40px;height:40px;border-radius:50%;background:#1e293b;display:flex;align-items:center;justify-content:center;font-size:1rem;color:#64748b;">?</div>
                  <?php endif; ?>
                </td>
                <td><?= $no++ ?></td>
                <td><?= e($s['name']) ?></td>
                <td class="text-sky-400"><?= e($s['nim'] ?? '-') ?></td>
                <td><?= e($s['prodi'] ?? '-') ?></td>
                <td>
                  <?php if ($s['angkatan']): ?>
                    <span class="badge-angkatan"><?= e($s['angkatan']) ?></span>
                  <?php else: ?>
                    <span class="text-muted">-</span>
                  <?php endif; ?>
                </td>
                <td><?= e($s['email']) ?></td>
                <td class="text-muted"><?= date('d-m-Y', strtotime($s['created_at'])) ?></td>
                <td>
                  <button type="button" class="btn btn-info btn-sm" onclick="showProfile(<?= intval($s['id']) ?>)">👤 Profil</button>
                  <a href="students.php?id=<?= intval($s['id']) ?>" class="btn btn-warning btn-sm">✏️ Edit</a>
                  <a href="students.php?delete=<?= intval($s['id']) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus <?= e($s['name']) ?>?')">🗑️ Hapus</a>
                </td>
              </tr>
            <?php endwhile; else: ?>
              <tr><td colspan="9" class="text-center text-muted">Tidak ada data mahasiswa.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Profile Overlay -->
    <div id="profileOverlay" onclick="closeProfile(event)">
      <div id="profileBox" onclick="event.stopPropagation()">
        <button class="close-btn" onclick="closeProfile()">&times;</button>
        <div id="profileBody">
          <div class="text-center py-4" style="color:#94a3b8;">Memuat data...</div>
        </div>
      </div>
    </div>

  <footer class="pinba-footer">© <?= date('Y') ?> PINMES — <?= e($user['name']) ?></footer>
</div>

<!-- Student data for profile -->
<script>
const studentData = <?php
  $all = db()->query("SELECT id, name, nim, prodi, angkatan, email, role, photo, created_at FROM users WHERE role IN ('mahasiswa','dosen') ORDER BY created_at DESC");
  $arr = [];
  while ($r = $all->fetch_assoc()) {
      $arr[] = $r;
  }
  echo json_encode($arr);
?>;

function showProfile(id) {
  const s = studentData.find(d => parseInt(d.id) === id);
  if (!s) return;
  const photo = s.photo ? `<img src="../uploads/${s.photo}" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:2px solid #334155;display:block;margin:0 auto 12px;">` : `<div style="width:80px;height:80px;border-radius:50%;background:#1e3a5f;color:#7dd3fc;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;margin:0 auto 12px;">${s.name.charAt(0).toUpperCase()}</div>`;
  document.getElementById('profileBody').innerHTML = `
    ${photo}
    <h5 style="text-align:center;margin:0 0 4px;">${s.name}</h5>
    <p style="text-align:center;color:#94a3b8;margin:0 0 16px;font-size:0.85rem;">${s.email}</p>
    <table style="width:100%;border-collapse:collapse;">
      <tr><td style="color:#94a3b8;padding:8px 4px;width:110px;border:none;">NIM</td><td style="color:#7dd3fc;padding:8px 4px;border:none;">${s.nim || '-'}</td></tr>
      <tr><td style="color:#94a3b8;padding:8px 4px;border:none;">Prodi</td><td style="color:#e2e8f0;padding:8px 4px;border:none;">${s.prodi || '-'}</td></tr>
      <tr><td style="color:#94a3b8;padding:8px 4px;border:none;">Angkatan</td><td style="color:#e2e8f0;padding:8px 4px;border:none;"><span class="badge-angkatan">${s.angkatan || '-'}</span></td></tr>
      <tr><td style="color:#94a3b8;padding:8px 4px;border:none;">Role</td><td style="color:#e2e8f0;padding:8px 4px;border:none;">${s.role}</td></tr>
      <tr><td style="color:#94a3b8;padding:8px 4px;border:none;">Terdaftar</td><td style="color:#e2e8f0;padding:8px 4px;border:none;">${s.created_at}</td></tr>
    </table>
  `;
  document.getElementById('profileOverlay').classList.add('show');
}
function closeProfile(e) {
  if (e && e.target !== e.currentTarget) return;
  document.getElementById('profileOverlay').classList.remove('show');
}
</script>

<?php include 'partials/scripts.php'; ?>
</body>
</html>