<?php
session_start();
require_once '../controller/config.php';
if (empty($_SESSION['user'])) { header('Location: ../index.php'); exit; }
$user = $_SESSION['user'];
if (!in_array($user['role'], ['admin','kepala','kepala_bengkel','penanggung jawab'])) { echo 'Akses ditolak.'; exit; }

$current = basename($_SERVER['PHP_SELF']);
// karena halaman ini hanya untuk admin yang sudah login
$is_guest = false;

$edit = null;
$msg = '';
$msg_type = 'success';
$search = trim($_GET['search'] ?? '');
$filter_prodi = trim($_GET['prodi'] ?? '');
$filter_angkatan = trim($_GET['angkatan'] ?? '');

$db = db();

// Ambil daftar prodi & angkatan unik untuk dropdown filter
$prodi_list = $db->query("SELECT DISTINCT prodi FROM pre_students WHERE prodi != '' ORDER BY prodi ASC")->fetch_all(MYSQLI_ASSOC);
$angkatan_list = $db->query("SELECT DISTINCT angkatan FROM pre_students WHERE angkatan IS NOT NULL ORDER BY angkatan DESC")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = trim($_POST['nim_original'] ?? '');
    $nim = trim($_POST['nim'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $prodi = trim($_POST['prodi'] ?? '');
    $angkatan = trim($_POST['angkatan'] ?? '');
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $stmt = $db->prepare('DELETE FROM pre_students WHERE nim = ?');
        $stmt->bind_param('s', $nim);
        if ($stmt->execute()) {
            $msg = 'Data mahasiswa berhasil dihapus.';
        } else {
            $msg = 'Gagal menghapus: ' . $db->error;
            $msg_type = 'danger';
        }
        $stmt->close();
    } else {
        if (!$nim || !$name) {
            $msg = 'NIM dan Nama wajib diisi.';
            $msg_type = 'danger';
        } else {
            if ($angkatan === '') $angkatan = null;
            if ($id !== '' && $id !== $nim) {
                $stmt = $db->prepare('UPDATE pre_students SET nim=?, name=?, prodi=?, angkatan=? WHERE nim=?');
                $stmt->bind_param('sssis', $nim, $name, $prodi, $angkatan, $id);
                if ($stmt->execute()) {
                    $msg = 'Data mahasiswa berhasil diperbarui.';
                } else {
                    $msg = 'Gagal memperbarui: ' . $db->error;
                    $msg_type = 'danger';
                }
                $stmt->close();
            } elseif ($id !== '') {
                $stmt = $db->prepare('UPDATE pre_students SET name=?, prodi=?, angkatan=? WHERE nim=?');
                $stmt->bind_param('ssis', $name, $prodi, $angkatan, $nim);
                if ($stmt->execute()) {
                    $msg = 'Data mahasiswa berhasil diperbarui.';
                } else {
                    $msg = 'Gagal memperbarui: ' . $db->error;
                    $msg_type = 'danger';
                }
                $stmt->close();
            } else {
                $stmt = $db->prepare('INSERT INTO pre_students (nim, name, prodi, angkatan) VALUES (?,?,?,?)');
                $stmt->bind_param('sssi', $nim, $name, $prodi, $angkatan);
                if ($stmt->execute()) {
                    $msg = 'Data mahasiswa berhasil ditambahkan.';
                } else {
                    $msg = 'Gagal menambahkan: ' . $db->error;
                    $msg_type = 'danger';
                }
                $stmt->close();
            }
        }
    }
}

if (isset($_GET['edit'])) {
    $stmt = $db->prepare('SELECT * FROM pre_students WHERE nim = ?');
    $stmt->bind_param('s', $_GET['edit']);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$conditions = [];
$params = [];
$types = '';

if ($search !== '') {
    $conditions[] = '(nim LIKE ? OR name LIKE ? OR prodi LIKE ?)';
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s]);
    $types .= 'sss';
}

if ($filter_prodi !== '') {
    $conditions[] = 'prodi = ?';
    $params[] = $filter_prodi;
    $types .= 's';
}

if ($filter_angkatan !== '') {
    $conditions[] = 'angkatan = ?';
    $params[] = intval($filter_angkatan);
    $types .= 'i';
}

$where = count($conditions) > 0 ? 'WHERE ' . implode(' AND ', $conditions) : '';

$count_sql = "SELECT COUNT(*) c FROM pre_students $where";
$stmt = $db->prepare($count_sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['c'];
$stmt->close();
$total_pages = max(1, ceil($total / $per_page));

$sql = "SELECT * FROM pre_students $where ORDER BY name ASC LIMIT ? OFFSET ?";
$stmt = $db->prepare($sql);
if ($types) {
    $stmt->bind_param($types . 'ii', ...[...$params, $per_page, $offset]);
} else {
    $stmt->bind_param('ii', $per_page, $offset);
}
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Data Mahasiswa Pra-Daftar — PINMES Admin</title>
  <meta name="description" content="Kelola data mahasiswa pra-daftar bengkel.">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/theme.css">
  <style>
  .badge-nim {
    background: rgba(56, 189, 248, 0.12);
    color: var(--accent-sky);
    font-size: 0.8rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 6px;
    font-family: monospace;
  }
  .badge-angkatan {
    background: rgba(148, 163, 184, 0.1);
    color: var(--muted);
    font-size: 0.8rem;
    padding: 4px 10px;
    border-radius: 6px;
  }
  .pagination .page-link {
    background: #1e293b;
    border-color: #334155;
    color: var(--muted);
  }
  .pagination .page-link:hover {
    background: #334155;
    color: #fff;
  }
  .pagination .page-item.active .page-link {
    background: var(--accent) !important;
    border-color: var(--accent) !important;
    color: #fff !important;
  }
  .modal-content {
    background: #0f172a !important;
    border: 1px solid var(--border) !important;
    color: var(--text) !important;
    border-radius: 12px;
  }
  .modal-header, .modal-footer {
    border-color: var(--border) !important;
  }
  </style>
</head>
<body>
<canvas id="tech-bg"></canvas>
<?php $base_path='..'; include 'partials/navbar.php'; ?>

<div class="page-wrapper">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <h1 class="page-heading mb-1">📋 Data Mahasiswa</h1>
      <span class="muted" style="font-size:0.85rem;">Total: <strong class="text-sky"><?= $total ?></strong> mahasiswa</span>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <form method="get" class="d-flex flex-wrap gap-2 align-items-center">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari NIM/Nama/Prodi..." value="<?= e($search) ?>" style="width:160px">
        
        <select name="prodi" class="form-select form-select-sm" style="width:auto;min-width:180px">
          <option value="">Semua Prodi</option>
          <?php foreach ($prodi_list as $p): ?>
          <option value="<?= e($p['prodi']) ?>" <?= $filter_prodi === $p['prodi'] ? 'selected' : '' ?>><?= e($p['prodi']) ?></option>
          <?php endforeach; ?>
        </select>
        
        <select name="angkatan" class="form-select form-select-sm" style="width:auto;min-width:110px">
          <option value="">Semua Angkatan</option>
          <?php foreach ($angkatan_list as $a): ?>
          <option value="<?= e($a['angkatan']) ?>" <?= $filter_angkatan === $a['angkatan'] ? 'selected' : '' ?>><?= e($a['angkatan']) ?></option>
          <?php endforeach; ?>
        </select>
        
        <button type="submit" class="btn btn-primary btn-sm">🔍 Cari</button>
        <?php if ($search !== '' || $filter_prodi !== '' || $filter_angkatan !== ''): ?>
          <a href="pre_students.php" class="btn btn-secondary btn-sm">Reset</a>
        <?php endif; ?>
      </form>
      <button class="btn btn-success btn-sm" onclick="openModal()">➕ Tambah</button>
    </div>
  </div>

  <?php if ($msg): ?>
    <div class="alert-pinba <?= $msg_type === 'danger' ? 'danger' : 'success' ?> mb-4"><?= e($msg) ?></div>
  <?php endif; ?>

  <div class="card-panel">

    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>No</th>
            <th>NIM</th>
            <th>Nama</th>
            <th>Prodi</th>
            <th>Angkatan</th>
            <th style="width:110px">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($students) === 0): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">Belum ada data</td></tr>
          <?php endif; ?>
          <?php $no = $offset + 1; foreach ($students as $s): ?>
          <tr>
            <td style="color:#64748b;"><?= $no++ ?></td>
            <td><span class="badge-nim"><?= e($s['nim']) ?></span></td>
            <td style="font-weight:500;"><?= e($s['name']) ?></td>
            <td style="color:#94a3b8;"><?= e($s['prodi']) ?: '-' ?></td>
            <td><span class="badge-angkatan"><?= e($s['angkatan']) ?: '-' ?></span></td>
            <td>
              <div class="d-flex gap-1">
                <button class="btn btn-sm btn-outline-info" onclick="openModal(<?= htmlspecialchars(json_encode($s)) ?>)">✏️ Edit</button>
                <form method="post" style="display:inline" onsubmit="return confirm('Hapus data ini?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="nim" value="<?= e($s['nim']) ?>">
                  <button class="btn btn-sm btn-outline-danger">🗑️</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <nav style="margin-top:16px;"><ul class="pagination pagination-sm justify-content-center">
      <?php
      $qstr = 'search=' . urlencode($search) . '&prodi=' . urlencode($filter_prodi) . '&angkatan=' . urlencode($filter_angkatan);
      for ($i = 1; $i <= $total_pages; $i++): ?>
      <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>&<?= $qstr ?>"><?= $i ?></a></li>
      <?php endfor; ?>
    </ul></nav>
    <?php endif; ?>
  </div>
  <footer class="pinba-footer">© <?= date('Y') ?> PINMES — <?= e($user['name']) ?></footer>
</div>

<div class="modal fade" id="studentModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" style="color:var(--accent-sky);" id="modalTitle">Tambah Data Mahasiswa</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <div class="modal-body">
          <input type="hidden" name="nim_original" id="nim_original">
          <div class="mb-3">
            <label class="form-label">NIM</label>
            <input type="text" name="nim" id="f_nim" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Nama Lengkap</label>
            <input type="text" name="name" id="f_name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Prodi</label>
            <input type="text" name="prodi" id="f_prodi" class="form-control" placeholder="Teknik Mesin">
          </div>
          <div class="mb-3">
            <label class="form-label">Angkatan</label>
            <input type="number" name="angkatan" id="f_angkatan" class="form-control" placeholder="2024" min="2000" max="2099">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
function openModal(data) {
  const m = document.getElementById('studentModal');
  const t = document.getElementById('modalTitle');
  if (data) {
    t.textContent = 'Edit Data Mahasiswa';
    document.getElementById('nim_original').value = data.nim;
    document.getElementById('f_nim').value = data.nim;
    document.getElementById('f_name').value = data.name;
    document.getElementById('f_prodi').value = data.prodi;
    document.getElementById('f_angkatan').value = data.angkatan;
  } else {
    t.textContent = 'Tambah Data Mahasiswa';
    ['nim_original','f_nim','f_name','f_prodi','f_angkatan'].forEach(id => document.getElementById(id).value = '');
  }
  bootstrap.Modal.getOrCreateInstance(m).show();
}
</script>
<?php include 'partials/scripts.php'; ?>
</body>
</html>
