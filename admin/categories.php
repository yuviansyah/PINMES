<?php
session_start();

$is_guest = $_SESSION['guest_mode'] ?? false;
require_once '../controller/config.php';
if (empty($_SESSION['user'])) { header('Location: ../index.php'); exit; }
$user = $_SESSION['user'];
if (!in_array($user['role'], ['kepala','kepala_bengkel','penanggung jawab'])) { echo 'Akses ditolak.'; exit; }

// --- Flash message (tambah, ubah, hapus) ---
$flash = null;
if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

$current = basename($_SERVER['PHP_SELF']); // deteksi halaman aktif

// ====== EDIT: inisialisasi dan load data untuk edit jika ada ?id=... ======
$edit_id = 0;
$edit_name = '';
$edit_desc = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $edit_id = intval($_GET['id']);
    if ($edit_id > 0) {
        $res = db()->query('SELECT * FROM categories WHERE id=' . $edit_id . ' LIMIT 1');
        if ($res && $res->num_rows) {
            $row = $res->fetch_assoc();
            $edit_name = $row['name'];
            $edit_desc = $row['description'];
        }
    }
}

// proses tambah / update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $name = trim($_POST['name']);
    $desc = trim($_POST['description'] ?? '');
    $id = intval($_POST['id'] ?? 0);

    if ($name !== '') {
        if ($id > 0) {
            // UPDATE existing category
            $stmt = db()->prepare('UPDATE categories SET name = ?, description = ? WHERE id = ?');
            $stmt->bind_param('ssi', $name, $desc, $id);
            $stmt->execute();
            $_SESSION['flash'] = ['type'=>'success','msg'=>'Kategori berhasil diperbarui.'];
            $stmt->close();
        } else {
            // INSERT new category
            $stmt = db()->prepare('INSERT INTO categories (name, description) VALUES (?, ?)');
            $stmt->bind_param('ss', $name, $desc);
            $stmt->execute();
            $_SESSION['flash'] = ['type'=>'success','msg'=>'Kategori berhasil ditambahkan.'];
            $stmt->close();
        }
    }
    header('Location: categories.php'); exit;
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $_SESSION['flash'] = ['type'=>'success','msg'=>'Kategori berhasil dihapus.'];
    db()->query('DELETE FROM categories WHERE id=' . $id);
    header('Location: categories.php'); exit;
}

$categories = db()->query('SELECT * FROM categories ORDER BY id DESC');
?>
<!doctype html><html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Kategori — PINMES Admin</title>
  <meta name="description" content="Kelola kategori barang inventaris bengkel.">
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
      <h2 class="mb-0">📂 Kategori</h2>
    </div>

    <?php if($flash): ?>
      <div class="alert <?= ($flash['type'] === 'success') ? 'alert-success' : 'alert-danger' ?> alert-dismissible fade show" role="alert">
        <?= e($flash['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <script>
        // auto-hide setelah 4 detik
        setTimeout(() => {
          const a = document.querySelector('.alert');
          if (a) {
            try { bootstrap.Alert.getOrCreateInstance(a).close(); } catch(e) { a.remove(); }
          }
        }, 4000);
      </script>
    <?php endif; ?>

    <div class="card-panel mb-4">
      <form method="post" class="row g-2">
        <!-- hidden id untuk edit -->
        <input type="hidden" name="id" value="<?= intval($edit_id) ?>">
        <div class="col-md-6">
          <label class="form-label">Nama Kategori</label>
          <input type="text" name="name" class="form-control" required value="<?= e($edit_name) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Deskripsi</label>
          <input type="text" name="description" class="form-control" value="<?= e($edit_desc) ?>">
        </div>
        <div class="col-12 text-end mt-2">
          <button class="btn btn-primary"><?= $edit_id ? 'Simpan Perubahan' : 'Tambah' ?></button>
          <?php if($edit_id): ?>
            <a href="categories.php" class="btn btn-secondary ms-2">Batal</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <div class="card-panel">
      <div class="table-responsive">
        <table class="table table-striped">
          <thead><tr><th>No</th><th>Nama</th><th>Deskripsi</th><th>Aksi</th></tr></thead>
          <tbody>
            <?php if($categories->num_rows>0): $n=1; while($c=$categories->fetch_assoc()): ?>
              <tr>
                <td><?= $n++ ?></td>
                <td><?= e($c['name']) ?></td>
                <td><?= e($c['description']) ?></td>
                <td>
                  <!-- tombol edit sekarang mengisi form di halaman yang sama menggunakan ?id= -->
                  <a href="?id=<?= $c['id'] ?>" class="btn btn-warning btn-sm text-dark">Edit</a>
                  <a href="?delete=<?= $c['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus kategori?')">Hapus</a>
                </td>
              </tr>
            <?php endwhile; else: ?>
              <tr><td colspan="4" class="text-center text-muted">Belum ada kategori.</td></tr>
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
