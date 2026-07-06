<?php
session_start();

$is_guest = $_SESSION['guest_mode'] ?? false;

require_once '../controller/config.php';
if (empty($_SESSION['user'])) { header('Location: ../index.php'); exit; }
$user = $_SESSION['user'];
$workshop_id = intval($user['workshop_id'] ?? 0);
if (!in_array($user['role'], ['kepala','kepala_bengkel','penanggung jawab'])) {
    echo 'Akses ditolak.';
    exit;
}

$msg = '';
$is_admin = ($user['role'] === 'admin');
$is_kepala = in_array($user['role'], ['kepala','kepala_bengkel','penanggung jawab']);
$can_see_all = ($user['role'] === 'admin' || $user['role'] === 'kepala' || $user['role'] === 'kepala_bengkel');

$workshops = db()->query("SELECT * FROM workshops ORDER BY name ASC");


// proses tambah / update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $code = trim($_POST['code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $workshop_id = intval($_POST['workshop_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 0);

    if (!$code || !$name) {
        $msg = 'Kode dan Nama wajib diisi.';
   } elseif ($workshop_id === 0) {
        $msg = 'Bengkel wajib dipilih.';
   } elseif ($id > 0) {
        $stmt = db()->prepare(
            'UPDATE items SET code=?, name=?, category_id=?, workshop_id=?, quantity=? WHERE id=?'
        );
        $stmt->bind_param('ssiiii', $code, $name, $category_id, $workshop_id, $quantity, $id);
        $stmt->execute();
        $msg = 'Barang berhasil diperbarui.';
   } else {
        $stmt = db()->prepare(
            'INSERT INTO items (code,name,category_id,workshop_id,quantity) VALUES (?,?,?,?,?)'
        );
        $stmt->bind_param('ssiii', $code, $name, $category_id, $workshop_id, $quantity);
        $stmt->execute();
        $msg = 'Barang ditambahkan.';
    }
}

// proses hapus
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $del_where = ($is_admin || $user['role'] === 'kepala' || $user['role'] === 'kepala_bengkel') ? '' : " AND workshop_id=".$workshop_id;
    db()->query(
      "DELETE FROM items
      WHERE id=".intval($id).$del_where
      );
    header('Location: items.php');
    exit;
}

// ambil data untuk tabel dan untuk edit jika ada id
$ws_filter = ($is_admin || $user['role'] === 'kepala' || $user['role'] === 'kepala_bengkel') ? '' : " WHERE i.workshop_id=".$workshop_id;
$edit = null;
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $edit_where = ($is_admin || $user['role'] === 'kepala' || $user['role'] === 'kepala_bengkel') ? '' : " AND workshop_id=".$workshop_id;
    $res = db()->query(
      "SELECT *
      FROM items
      WHERE id=".$id.$edit_where
      );
    $edit = $res->fetch_assoc();
}

// search & filter
$search = trim($_GET['search'] ?? '');
$can_filter_workshop = ($user['role'] === 'kepala' || $user['role'] === 'kepala_bengkel');
$ws_filter_id = $can_filter_workshop ? intval($_GET['workshop'] ?? 0) : 0;
$qty_op = $_GET['qty_op'] ?? '';
$qty_val = trim($_GET['qty_val'] ?? '');

$where = rtrim($ws_filter ?: 'WHERE 1=1', ' ');
$params = [];
$types = '';

if ($search !== '') {
    $where_having = (strpos($where, 'WHERE') === false ? ' WHERE' : ' AND');
    $where .= "$where_having (i.code LIKE ? OR i.name LIKE ? OR c.name LIKE ? OR w.name LIKE ?)";
    $like = "%$search%";
    $params = [$like, $like, $like, $like];
    $types = 'ssss';
}
if ($ws_filter_id > 0) {
    $where_having = (strpos($where, 'WHERE') === false ? ' WHERE' : ' AND');
    $where .= "$where_having i.workshop_id=?";
    $params[] = $ws_filter_id;
    $types .= 'i';
}
$allowed_ops = ['=', '>', '<', '>=', '<='];
if (in_array($qty_op, $allowed_ops, true) && $qty_val !== '' && is_numeric($qty_val)) {
    $where_having = (strpos($where, 'WHERE') === false ? ' WHERE' : ' AND');
    $where .= "$where_having i.quantity $qty_op ?";
    $qty_val_n = intval($qty_val);
    $params[] = $qty_val_n;
    $types .= 'i';
}

$sql = "SELECT i.*, c.name AS category_name,
  w.name AS workshop_name
  FROM items i
  LEFT JOIN categories c ON i.category_id=c.id
  LEFT JOIN workshops w ON i.workshop_id=w.id"
  . $where .
  " ORDER BY i.id DESC";

if ($params) {
    $stmt = db()->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result();
} else {
    $rows = db()->query($sql);
}
?>
<!doctype html><html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Kelola Barang — PINMES Admin</title>
  <meta name="description" content="Halaman admin kelola barang inventaris bengkel.">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/theme.css">
</head>
<body>
<canvas id="tech-bg"></canvas>


<?php
$base_path = '..';
include 'partials/navbar.php';
?>

<div class="page-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="mb-0">🛠️ Kelola Barang</h2>
    </div>

    <?php if($msg): ?>
      <div class="alert alert-info"><?= e($msg) ?></div>
    <?php endif; ?>

    <div class="card-panel mb-4">
      <form method="post" class="row g-2">
        <input type="hidden" name="id" value="<?= $edit ? intval($edit['id']) : 0 ?>">

        <div class="col-md-2">
          <label class="form-label">Kode</label>
          <input type="text" name="code" class="form-control" required value="<?= $edit ? e($edit['code']) : '' ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Nama Barang</label>
          <input type="text" name="name" class="form-control" required value="<?= $edit ? e($edit['name']) : '' ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Bengkel</label>
          <?php if (in_array($user['role'], ['admin'])): ?>
            <select name="workshop_id" class="form-select">
              <option value="0">-- Pilih Bengkel --</option>
              <?php
              $ws = db()->query("SELECT * FROM workshops ORDER BY name ASC");
              while($w = $ws->fetch_assoc()):
              ?>
                <option value="<?= $w['id'] ?>"
                  <?= $edit && intval($edit['workshop_id']) === intval($w['id']) ? 'selected' : '' ?>>
                  <?= e($w['name']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          <?php else: ?>
            <input type="hidden" name="workshop_id" value="<?= $workshop_id ?>">
            <input type="text" class="form-control"
              value="<?php
                $wn = db()->query("SELECT name FROM workshops WHERE id=".$workshop_id);
                echo e($wn && $wn->num_rows ? $wn->fetch_assoc()['name'] : '-');
              ?>" readonly>
          <?php endif; ?>
        </div>
        <input type="hidden" name="category_id" value="0">
        <div class="col-md-1">
          <label class="form-label">Qty</label>
          <input type="number" name="quantity" class="form-control" min="0" value="<?= $edit ? intval($edit['quantity']) : 1 ?>">
        </div>
        <div class="col-12 text-end mt-2">
          <button class="btn btn-primary"><?= $edit ? 'Update' : 'Tambah' ?></button>
          <?php if($edit): ?><a href="items.php" class="btn btn-secondary ms-2">Batal</a><?php endif; ?>
        </div>
      </form>
    </div>

    <div class="card-panel mb-3">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-md-5">
          <label class="form-label">Cari Barang</label>
          <input type="text" name="search" class="form-control" placeholder="Kode, nama, bengkel..." value="<?= e($search) ?>">
        </div>
        <?php if ($can_filter_workshop): ?>
        <div class="col-md-3">
          <label class="form-label">Filter Bengkel</label>
          <select name="workshop" class="form-select">
            <option value="0">Semua Bengkel</option>
            <?php
            $ws_list = db()->query("SELECT * FROM workshops ORDER BY name ASC");
            while ($w = $ws_list->fetch_assoc()):
            ?>
              <option value="<?= $w['id'] ?>" <?= $ws_filter_id === intval($w['id']) ? 'selected' : '' ?>>
                <?= e($w['name']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="col-md-2">
          <label class="form-label">Filter Qty</label>
          <div class="input-group">
            <select name="qty_op" class="form-select" style="max-width:70px">
              <option value="=" <?= $qty_op==='=' ? 'selected' : '' ?>>=</option>
              <option value=">" <?= $qty_op==='>' ? 'selected' : '' ?>>&gt;</option>
              <option value="<" <?= $qty_op==='<' ? 'selected' : '' ?>>&lt;</option>
              <option value=">=" <?= $qty_op==='>=' ? 'selected' : '' ?>>&gt;=</option>
              <option value="<=" <?= $qty_op==='<=' ? 'selected' : '' ?>>&lt;=</option>
            </select>
            <input type="number" name="qty_val" class="form-control" min="0" placeholder="0" value="<?= e($qty_val) ?>">
          </div>
        </div>
        <div class="col-md-1">
          <button type="submit" class="btn btn-primary w-100">Cari</button>
        </div>
        <div class="col-md-1">
          <a href="items.php" class="btn btn-secondary w-100">Reset</a>
        </div>
      </form>
    </div>

    <div class="card-panel">
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead>
          <tr>
          <th>No</th>
          <th>Kode</th>
          <th>Nama</th>
          <th>Bengkel</th>
          <th>Qty</th>
          <th>Tanggal</th>
          <th>Aksi</th>
          </tr>
          </thead>
          <tbody>
            <?php if($rows && $rows->num_rows>0): $no=1; while($i = $rows->fetch_assoc()): ?>
            <tr>
              <td><?= $no++ ?></td>
              <td><?= e($i['code']) ?></td>
              <td><?= e($i['name']) ?></td>
              <td><?= e($i['workshop_name']) ?></td>
              <td><?= e($i['quantity']) ?></td>
              <td class="text-muted"><?= date('d-m-Y', strtotime($i['created_at'])) ?></td>
              <td>
                <a href="?id=<?= $i['id'] ?>" class="btn btn-warning btn-sm text-dark">Edit</a>
                <a href="?delete=<?= $i['id'] ?>" onclick="return confirm('Hapus barang?')" class="btn btn-danger btn-sm">Hapus</a>
              </td>
            </tr>
            <?php endwhile; else: ?>
              <tr><td colspan="7" class="text-center text-muted">Belum ada barang.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <footer class="pinba-footer">© <?= date('Y') ?> PINMES — <?= e($user['name']) ?></footer>
  </div>

<?php include 'partials/scripts.php'; ?>

</body>
</html>
