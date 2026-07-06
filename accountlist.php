<?php
require_once 'controller/config.php';
$is_guest = $_SESSION['guest_mode'] ?? false;
$role = $_SESSION['user']['role'] ?? '';
if (!in_array($role, ['admin','kepala','kepala_bengkel','kepala bengkel'])) {
    echo 'Akses ditolak.';
    exit;
}

$user = current_user();
$err = '';
$ok = '';

if (!is_admin() && isset($_GET['delete'])) {
    die('Akses ditolak');
}

if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    if ($del_id === intval($user['id'])) {
        $err = 'Anda tidak dapat menghapus akun sendiri.';
    } else {
        $stmt = db()->prepare('SELECT id, role FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $del_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $target = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$target) {
            $err = 'Akun tidak ditemukan.';
        } elseif (strtolower($target['role']) === 'admin') {
            $err = 'Tidak diperbolehkan menghapus akun admin.';
        } else {
            $stmt = db()->prepare('DELETE FROM users WHERE id = ?');
            $stmt->bind_param('i', $del_id);
            if ($stmt->execute()) {
                $ok = 'Akun berhasil dihapus.';
            } else {
                $err = 'Gagal menghapus akun.';
            }
            $stmt->close();
        }
    }
    header('Location: accountlist.php'
        . ($err ? '?err=' . urlencode($err) : ($ok ? '?ok=' . urlencode($ok) : ''))
    );
    exit;
}

if (isset($_GET['err'])) $err = $_GET['err'];
if (isset($_GET['ok'])) $ok = $_GET['ok'];

$f_role = trim($_GET['role'] ?? '');
$f_search = trim($_GET['search'] ?? '');
$f_angkatan = trim($_GET['angkatan'] ?? '');

$where = [];
$params = [];
$types = '';

if ($f_role !== '') {
    $where[] = 'role = ?';
    $params[] = $f_role;
    $types .= 's';
}
if ($f_search !== '') {
    $where[] = '(name LIKE ? OR email LIKE ? OR nim LIKE ?)';
    $like = '%' . $f_search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'sss';
}
if ($f_angkatan !== '') {
    $where[] = 'angkatan = ?';
    $params[] = $f_angkatan;
    $types .= 's';
}

$sql = "SELECT id, name, nim, prodi, angkatan, email, photo, role, created_at FROM users";
if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY created_at DESC";

$stmt = db()->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
$stmt->close();

?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Daftar Akun — PINMES Admin</title>
  <meta name="description" content="Kelola seluruh akun pengguna di PINMES.">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/theme.css">
  <style>
    .avatar {
      width: 40px; height: 40px; border-radius: 50%;
      object-fit: cover; background: #1e293b;
    }
    .avatar-placeholder {
      width: 40px; height: 40px; border-radius: 50%;
      background: #334155; display: inline-flex;
      align-items: center; justify-content: center;
      font-size: 0.85rem; color: var(--muted);
    }
    .filter-bar {
      display: flex; flex-wrap: wrap; gap: 12px; align-items: end;
    }
    .filter-bar .form-control,
    .filter-bar .form-select {
      min-width: 160px;
    }
  </style>
</head>
<body>
<canvas id="tech-bg"></canvas>
<?php $base_path='.'; include 'admin/partials/navbar.php'; ?>

<div class="page-wrapper">
  <h1 class="page-heading">📲 Daftar Akun</h1>

  <?php if ($err): ?>
    <div class="alert-pinba danger mb-4"><?= e($err) ?></div>
  <?php endif; ?>
  <?php if ($ok): ?>
    <div class="alert-pinba success mb-4"><?= e($ok) ?></div>
  <?php endif; ?>

  <div class="card-panel mb-4">
    <form method="get" class="filter-bar">
      <div>
        <label class="form-label">Role</label>
        <select name="role" class="form-select" onchange="this.form.submit()">
          <option value="">Semua Role</option>
          <option value="admin" <?= $f_role==='admin'?'selected':'' ?>>Admin</option>
          <option value="kepala" <?= $f_role==='kepala'?'selected':'' ?>>Kepala</option>
          <option value="penanggung jawab" <?= $f_role==='penanggung jawab'?'selected':'' ?>>Penanggung Jawab</option>
          <option value="mahasiswa" <?= $f_role==='mahasiswa'?'selected':'' ?>>Mahasiswa</option>
          <option value="dosen" <?= $f_role==='dosen'?'selected':'' ?>>Dosen</option>
        </select>
      </div>
      <div>
        <label class="form-label">Cari (Nama/Email/NIM)</label>
        <input type="text" name="search" class="form-control" placeholder="Ketikkan kata kunci..." value="<?= e($f_search) ?>">
      </div>
      <div>
        <label class="form-label">Angkatan</label>
        <input type="text" name="angkatan" class="form-control" placeholder="Cth: 2025" value="<?= e($f_angkatan) ?>">
      </div>
      <div>
        <label class="form-label">&nbsp;</label>
        <button class="btn btn-primary">Filter</button>
        <?php if ($f_role || $f_search || $f_angkatan): ?>
          <a href="accountlist.php" class="btn btn-secondary">Reset</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card-panel">
    <div class="table-responsive">
      <table class="table table-borderless table-hover align-middle">
        <thead>
          <tr class="text-muted">
            <th style="width:56px">No</th>
            <th style="width:54px">Foto</th>
            <th>Nama</th>
            <th>NIM/NIDN</th>
            <th>Angkatan</th>
            <th>Prodi</th>
            <th>Email</th>
            <th style="width:100px">Role</th>
            <th style="width:160px">Terdaftar</th>
            <th style="width:140px">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php $users_data = []; if ($res && $res->num_rows>0): $no=1; while($r=$res->fetch_assoc()): $users_data[] = $r; ?>
            <tr>
              <td class="muted"><?= $no++ ?></td>
              <td>
                <?php if (!empty($r['photo'])): ?>
                  <img src="uploads/<?= e($r['photo']) ?>" alt="foto" class="avatar" style="cursor:pointer" loading="lazy"
                       onclick="showProfile(<?= intval($r['id']) ?>)">
                <?php else: ?>
                  <span class="avatar-placeholder" style="cursor:pointer"
                        onclick="showProfile(<?= intval($r['id']) ?>)"><?= e(strtoupper(substr($r['name'], 0, 1))) ?></span>
                <?php endif; ?>
              </td>
              <td><?= e($r['name']) ?></td>
              <td class="text-sky"><?= e($r['nim'] ?? '-') ?></td>
              <td><?= e($r['angkatan'] ?? '-') ?></td>
              <td><?= e($r['prodi'] ?? '-') ?></td>
              <td><?= e($r['email']) ?></td>
              <td>
                <span class="badge-status <?= 
                    $r['role'] === 'admin' ? 'badge-borrowed' : 
                    ($r['role'] === 'kepala' ? 'badge-pending' : 
                    (                    $r['role'] === 'penanggung jawab' ? 'badge-penanggung' : 'badge-returned')) 
                  ?>">
                    <?= e(ucfirst($r['role'])) ?>
                </span>
              </td>
              <td class="muted"><?= e($r['created_at']) ?></td>
              <td>
                <?php if (intval($r['id']) === intval($user['id'])): ?>
                  <span class="text-muted">-</span>
                <?php elseif (strtolower($r['role']) === 'admin'): ?>
                  <span class="text-muted">Admin</span>
                <?php else: ?>
                  <?php if(is_admin()): ?>
                    <a href="accountlist.php?delete=<?= intval($r['id']) ?>"
                       class="btn btn-danger btn-sm"
                       onclick="return confirm('Hapus akun?')">Hapus</a>
                  <?php else: ?>
                    <span class="text-muted">Lihat Saja</span>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; else: ?>
            <tr><td colspan="10" class="text-center muted">Belum ada akun.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <footer class="pinba-footer">© <?= date('Y') ?> PINMES — <?= e($user['name']) ?></footer>
</div>

<div class="modal fade" id="profileModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background:#1e293b;border:1px solid rgba(255,255,255,0.1);color:var(--text)">
      <div class="modal-header border-0">
        <h5 class="modal-title">Detail Akun</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center" id="profileModalBody">
        <div id="pmPhoto" style="width:100px;height:100px;border-radius:50%;margin:0 auto 12px;background:#334155;display:flex;align-items:center;justify-content:center;font-size:2rem;color:var(--muted);background-size:cover;background-position:center"></div>
        <h5 id="pmName" style="margin-bottom:4px"></h5>
        <table class="table table-borderless text-start" style="color:var(--text);margin-top:12px">
          <tr><td class="text-muted" style="width:100px">NIM</td><td id="pmNim"></td></tr>
          <tr><td class="text-muted">Prodi</td><td id="pmProdi"></td></tr>
          <tr><td class="text-muted">Angkatan</td><td id="pmAngkatan"></td></tr>
          <tr><td class="text-muted">Email</td><td id="pmEmail"></td></tr>
          <tr><td class="text-muted">Role</td><td id="pmRole"></td></tr>
          <tr><td class="text-muted">Terdaftar</td><td id="pmCreated"></td></tr>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
const users = <?= json_encode($users_data) ?>;

function showProfile(id) {
  const u = users.find(x => parseInt(x.id) === id);
  if (!u) return;
  const photo = document.getElementById('pmPhoto');
  if (u.photo) {
    photo.style.backgroundImage = "url('uploads/" + u.photo + "')";
    photo.textContent = '';
  } else {
    photo.style.backgroundImage = '';
    photo.textContent = (u.name || '?')[0].toUpperCase();
  }
  document.getElementById('pmName').textContent = u.name || '-';
  document.getElementById('pmNim').textContent = u.nim || '-';
  document.getElementById('pmProdi').textContent = u.prodi || '-';
  document.getElementById('pmAngkatan').textContent = u.angkatan || '-';
  document.getElementById('pmEmail').textContent = u.email || '-';
  document.getElementById('pmRole').textContent = u.role || '-';
  document.getElementById('pmCreated').textContent = u.created_at || '-';
  new bootstrap.Modal(document.getElementById('profileModal')).show();
}
</script>

<?php include 'views/partials/scripts.php'; ?>
</body>
</html>
