<?php
session_start();
$is_guest = $_SESSION['guest_mode'] ?? false;
require_once '../controller/config.php';
if (empty($_SESSION['user'])) { header('Location: ../index.php'); exit; }
$user = $_SESSION['user'];
if ($user['role'] !== 'admin') { echo 'Akses ditolak.'; exit; }

$current = basename($_SERVER['PHP_SELF']);

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['req_id'])) {
    $req_id = intval($_POST['req_id']);

    $stmt = db()->prepare('SELECT * FROM admin_requests WHERE id = ? AND status = "pending" LIMIT 1');
    $stmt->bind_param('i', $req_id);
    $stmt->execute();
    $req = $stmt->get_result()->fetch_assoc();

    if (!$req) {
        $msg = 'Permintaan tidak ditemukan atau sudah diproses.';
    } elseif ($_POST['action'] === 'approve') {
        $roleLabel = $req['requested_role'] === 'admin' ? 'admin' : ($req['requested_role'] === 'kepala' ? 'kepala' : 'penanggung jawab');
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $req['email']);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        if ($existing) {
            db()->query("DELETE FROM admin_requests WHERE id = {$req_id}");
            $msg = 'Email sudah terdaftar. Permintaan dihapus.';
        } else {
            $nim = $req['requested_role'] === 'penanggung jawab' ? ($req['nidn'] ?? '') : 0;
            $prodi = '';
            $ws_id = intval($req['workshop_id'] ?? 0);
            $stmt = db()->prepare('INSERT INTO users (name, nim, prodi, email, password, role, workshop_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('ssssssi', $req['name'], $nim, $prodi, $req['email'], $req['password'], $roleLabel, $ws_id);
            if ($stmt->execute()) {
                db()->query("DELETE FROM admin_requests WHERE id = {$req_id}");
                $roleName = $roleLabel === 'admin' ? 'Admin' : ($roleLabel === 'kepala' ? 'Kepala Bengkel' : 'Penanggung Jawab');
                $msg = 'Akun berhasil disetujui: ' . e($req['name']) . ' sebagai ' . $roleName;
            } else {
                $msg = 'Gagal membuat akun: ' . db()->error;
            }
        }
    } elseif ($_POST['action'] === 'reject') {
        db()->query("UPDATE admin_requests SET status = 'rejected' WHERE id = {$req_id}");
        $msg = 'Permintaan ditolak.';
    }
}

$res = db()->query('SELECT * FROM admin_requests WHERE status = "pending" ORDER BY created_at DESC');
?>
<!doctype html><html lang="id">
<head>

  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Persetujuan Admin / Kepala Lab — PINMES</title>
  <meta name="description" content="Setujui atau tolak permintaan akun admin dan kepala bengkel.">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/theme.css">
  <style>
  .badge-role { display:inline-block; padding:4px 14px; border-radius:999px; font-size:.8rem; font-weight:700; }
  .badge-admin { background:rgba(147,51,234,.18); color:#c084fc; border:1px solid rgba(147,51,234,.3); }
  .badge-kepala { background:rgba(234,179,8,.18); color:#fde047; border:1px solid rgba(234,179,8,.3); }
  </style>
</head>
<body>
<canvas id="tech-bg"></canvas>
<?php $base_path='..'; include 'partials/navbar.php'; ?>

<div class="page-wrapper">
  <h1 class="page-heading">✅ Konfirmasi Admin</h1>

    <?php if ($msg): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= e($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <script>
        setTimeout(() => {
          const a = document.querySelector('.alert');
          if (a) {
            try { bootstrap.Alert.getOrCreateInstance(a).close(); } catch(e) { a.remove(); }
          }
        }, 4000);
      </script>
    <?php endif; ?>

    <div class="card-panel">
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th>No</th>
              <th>Nama</th>
              <th>Email</th>
              <th>Role Diminta</th>
              <th>Bengkel</th>
              <th>Tanggal</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($res && $res->num_rows > 0): $no = 1; while ($r = $res->fetch_assoc()):
              $ws_name = '';
              if (!empty($r['workshop_id'])) {
                $wsq = db()->query("SELECT name FROM workshops WHERE id=".intval($r['workshop_id']));
                $ws_name = $wsq && $wsq->num_rows ? $wsq->fetch_assoc()['name'] : '-';
              }
            ?>
              <tr>
                <td><?= $no++ ?></td>
                <td><?= e($r['name']) ?></td>
                <td><?= e($r['email']) ?></td>
                <td>
                  <span class="badge-role <?= $r['requested_role'] === 'admin' ? 'badge-admin' : ($r['requested_role'] === 'kepala' ? 'badge-kepala' : 'badge-rejected') ?>">
                    <?= $r['requested_role'] === 'admin' ? 'Admin' : ($r['requested_role'] === 'kepala' ? 'Kepala Lab' : 'Penanggung Jawab') ?>
                  </span>
                </td>
                <td><?= e($ws_name ?: '-') ?></td>
                <td><?= date('d-m-Y H:i', strtotime($r['created_at'])) ?></td>
                <td>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="req_id" value="<?= $r['id'] ?>">
                    <button name="action" value="approve" class="btn btn-success btn-sm">Setujui</button>
                  </form>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="req_id" value="<?= $r['id'] ?>">
                    <button name="action" value="reject" class="btn btn-danger btn-sm">Tolak</button>
                  </form>
                </td>
              </tr>
            <?php endwhile; else: ?>
              <tr>
                <td colspan="7" class="text-center text-muted">Tidak ada permintaan pending.</td>
              </tr>
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
