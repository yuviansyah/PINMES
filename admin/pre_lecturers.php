<?php
session_start();
require_once '../controller/config.php';
if (empty($_SESSION['user'])) { header('Location: ../index.php'); exit; }
$user = $_SESSION['user'];
if (!in_array($user['role'], ['admin','kepala','kepala_bengkel','penanggung jawab'])) { echo 'Akses ditolak.'; exit; }

$current = basename($_SERVER['PHP_SELF']);
$is_guest = false;
$msg = '';
$msg_type = 'success';
$search = trim($_GET['search'] ?? '');
$filter_prodi = trim($_GET['prodi'] ?? '');

$db = db();

$prodi_list = $db->query("SELECT DISTINCT prodi FROM pre_lecturers WHERE prodi != '' ORDER BY prodi ASC")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = trim($_POST['nidn_original'] ?? '');
    $nidn = trim($_POST['nidn'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $prodi = trim($_POST['prodi'] ?? '');
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $stmt = $db->prepare('DELETE FROM pre_lecturers WHERE nidn = ?');
        $stmt->bind_param('s', $nidn);
        if ($stmt->execute()) {
            $msg = 'Data dosen berhasil dihapus.';
        } else {
            $msg = 'Gagal menghapus: ' . $db->error;
            $msg_type = 'danger';
        }
        $stmt->close();
    } else {
        if (!$nidn || !$name) {
            $msg = 'NIDN dan Nama wajib diisi.';
            $msg_type = 'danger';
        } else {
            if ($id !== '' && $id !== $nidn) {
                // Cek apakah NIDN baru sudah dipakai dosen lain
                $ck = $db->prepare('SELECT 1 FROM pre_lecturers WHERE nidn = ? AND nidn != ? LIMIT 1');
                $ck->bind_param('ss', $nidn, $id);
                $ck->execute();
                $exists = $ck->get_result()->num_rows > 0;
                $ck->close();

                if ($exists) {
                    $msg = 'NIDN "' . e($nidn) . '" sudah terdaftar untuk dosen lain.';
                    $msg_type = 'danger';
                } else {
                    $stmt = $db->prepare('UPDATE pre_lecturers SET nidn=?, name=?, prodi=? WHERE nidn=?');
                    $stmt->bind_param('ssss', $nidn, $name, $prodi, $id);
                    if ($stmt->execute()) {
                        $msg = 'Data dosen berhasil diperbarui.';
                    } else {
                        $msg = 'Gagal memperbarui: ' . $db->error;
                        $msg_type = 'danger';
                    }
                    $stmt->close();
                }
            } elseif ($id !== '') {
                $stmt = $db->prepare('UPDATE pre_lecturers SET name=?, prodi=? WHERE nidn=?');
                $stmt->bind_param('sss', $name, $prodi, $nidn);
                if ($stmt->execute()) {
                    $msg = 'Data dosen berhasil diperbarui.';
                } else {
                    $msg = 'Gagal memperbarui: ' . $db->error;
                    $msg_type = 'danger';
                }
                $stmt->close();
            } else {
                // Cek duplikat NIDN sebelum insert
                $ck = $db->prepare('SELECT 1 FROM pre_lecturers WHERE nidn = ? LIMIT 1');
                $ck->bind_param('s', $nidn);
                $ck->execute();
                $exists = $ck->get_result()->num_rows > 0;
                $ck->close();

                if ($exists) {
                    $msg = 'NIDN "' . e($nidn) . '" sudah terdaftar. Gunakan NIDN yang berbeda.';
                    $msg_type = 'danger';
                } else {
                    $stmt = $db->prepare('INSERT INTO pre_lecturers (nidn, name, prodi) VALUES (?,?,?)');
                    $stmt->bind_param('sss', $nidn, $name, $prodi);
                    if ($stmt->execute()) {
                        $msg = 'Data dosen berhasil ditambahkan.';
                    } else {
                        $msg = 'Gagal menambahkan: ' . $db->error;
                        $msg_type = 'danger';
                    }
                    $stmt->close();
                }
            }
        }
    }
}

if (isset($_GET['edit'])) {
    $stmt = $db->prepare('SELECT * FROM pre_lecturers WHERE nidn = ?');
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
    $conditions[] = '(nidn LIKE ? OR name LIKE ? OR prodi LIKE ?)';
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s]);
    $types .= 'sss';
}

if ($filter_prodi !== '') {
    $conditions[] = 'prodi = ?';
    $params[] = $filter_prodi;
    $types .= 's';
}

$where = count($conditions) > 0 ? 'WHERE ' . implode(' AND ', $conditions) : '';

$count_sql = "SELECT COUNT(*) c FROM pre_lecturers $where";
$stmt = $db->prepare($count_sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['c'];
$stmt->close();
$total_pages = max(1, ceil($total / $per_page));

$sql = "SELECT * FROM pre_lecturers $where ORDER BY name ASC LIMIT ? OFFSET ?";
$stmt = $db->prepare($sql);
if ($types) {
    $stmt->bind_param($types . 'ii', ...[...$params, $per_page, $offset]);
} else {
    $stmt->bind_param('ii', $per_page, $offset);
}
$stmt->execute();
$lecturers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Data Dosen Pra-Daftar — PINMES Admin</title>
  <meta name="description" content="Kelola data dosen pra-daftar bengkel.">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/theme.css">
  <style>
  .badge-nidn {
    background: rgba(56, 189, 248, 0.12);
    color: var(--accent-sky);
    font-size: 0.8rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 6px;
    font-family: monospace;
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
      <h1 class="page-heading mb-1">👨‍🏫 Data Dosen</h1>
      <span class="muted" style="font-size:0.85rem;">Total: <strong class="text-sky"><?= $total ?></strong> dosen</span>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <form method="get" class="d-flex flex-wrap gap-2 align-items-center">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari NIDN/Nama/Prodi..." value="<?= e($search) ?>" style="width:160px">
        
        <select name="prodi" class="form-select form-select-sm" style="width:auto;min-width:180px">
          <option value="">Semua Prodi</option>
          <?php foreach ($prodi_list as $p): ?>
          <option value="<?= e($p['prodi']) ?>" <?= $filter_prodi === $p['prodi'] ? 'selected' : '' ?>><?= e($p['prodi']) ?></option>
          <?php endforeach; ?>
        </select>
        
        <button type="submit" class="btn btn-primary btn-sm">🔍 Cari</button>
        <?php if ($search !== '' || $filter_prodi !== ''): ?>
          <a href="pre_lecturers.php" class="btn btn-secondary btn-sm">Reset</a>
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
            <th>NIDN</th>
            <th>Nama</th>
            <th>Prodi</th>
            <th style="width:110px">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($lecturers) === 0): ?>
          <tr><td colspan="5" class="text-center text-muted py-4">Belum ada data</td></tr>
          <?php endif; ?>
          <?php $no = $offset + 1; foreach ($lecturers as $s): ?>
          <tr>
            <td style="color:#64748b;"><?= $no++ ?></td>
            <td><span class="badge-nidn"><?= e($s['nidn']) ?></span></td>
            <td style="font-weight:500;"><?= e($s['name']) ?></td>
            <td style="color:#94a3b8;"><?= e($s['prodi']) ?: '-' ?></td>
            <td>
              <div class="d-flex gap-1">
                <button class="btn btn-sm btn-outline-info" onclick="openModal(<?= htmlspecialchars(json_encode($s)) ?>)">✏️ Edit</button>
                <form method="post" style="display:inline" onsubmit="return confirm('Hapus data ini?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="nidn" value="<?= e($s['nidn']) ?>">
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
      <?php for ($i = 1; $i <= $total_pages; $i++): ?>
      <?php
      $qstr = 'search=' . urlencode($search) . '&prodi=' . urlencode($filter_prodi);
      ?>
      <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>&<?= $qstr ?>"><?= $i ?></a></li>
      <?php endfor; ?>
    </ul></nav>
    <?php endif; ?>
    </div>
  </div>
  <footer class="pinba-footer">© <?= date('Y') ?> PINMES — <?= e($user['name']) ?></footer>
</div>

<div class="modal fade" id="lecturerModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" style="color:var(--accent-sky);" id="modalTitle">Tambah Data Dosen</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <div class="modal-body">
          <input type="hidden" name="nidn_original" id="f_nidn_original">
          <div class="mb-3">
            <label class="form-label">NIDN</label>
            <input type="text" name="nidn" id="f_nidn" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Nama Lengkap</label>
            <input type="text" name="name" id="f_name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Prodi</label>
            <input type="text" name="prodi" id="f_prodi" class="form-control" placeholder="Teknik Mesin">
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
  const m = document.getElementById('lecturerModal');
  const t = document.getElementById('modalTitle');
  if (data) {
    t.textContent = 'Edit Data Dosen';
    document.getElementById('f_nidn_original').value = data.nidn;
    document.getElementById('f_nidn').value = data.nidn;
    document.getElementById('f_name').value = data.name;
    document.getElementById('f_prodi').value = data.prodi;
  } else {
    t.textContent = 'Tambah Data Dosen';
    ['f_nidn_original','f_nidn','f_name','f_prodi'].forEach(id => document.getElementById(id).value = '');
  }
  bootstrap.Modal.getOrCreateInstance(m).show();
}
</script>
<?php include 'partials/scripts.php'; ?>
</body>
</html>
