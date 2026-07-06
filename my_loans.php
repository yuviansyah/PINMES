<?php
require_once 'controller/config.php';
if (empty($_SESSION['user'])) { header('Location: index.php'); exit; }
$user = $_SESSION['user'];
$is_guest = $_SESSION['guest_mode'] ?? false;
$res = db()->query('SELECT l.*, i.name as item_name, i.code as item_code FROM loans l LEFT JOIN items i ON l.item_id=i.id WHERE l.user_id='.intval($user['id']).' ORDER BY l.created_at DESC');
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Riwayat Peminjaman — PINMES</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/theme.css">
</head>
<body>
<canvas id="tech-bg"></canvas>
<?php $base_path='.'; include 'views/partials/navbar.php'; ?>

<div class="page-wrapper">
  <h1 class="page-heading">📋 Riwayat Peminjaman Saya</h1>

  <div class="card-panel">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>No. Pinjam</th>
            <th>Barang</th>
            <th>Qty</th>
            <th>Status</th>
            <th>Tanggal Pengajuan</th>
          </tr>
        </thead>
        <tbody>
          <?php if($res && $res->num_rows > 0): ?>
            <?php while($r = $res->fetch_assoc()): ?>
            <tr>
              <td><code style="color:var(--accent-sky);"><?php echo e($r['loan_number']); ?></code></td>
              <td style="white-space:nowrap"><?php echo e($r['item_code'].' - '.$r['item_name']); ?></td>
              <td><?php echo e($r['qty']); ?></td>
              <td>
                <?php
                  $s = $r['status'] ?? '';
                  $b = ['pending'=>'badge-pending','borrowed'=>'badge-borrowed','returned'=>'badge-returned','rejected'=>'badge-rejected'];
                  $l = ['pending'=>'Tertunda','borrowed'=>'Sedang Dipinjam','returned'=>'Dikembalikan','rejected'=>'Ditolak'];
                  echo '<span class="badge-status '.($b[$s]??'').'">'.($l[$s]??$s).'</span>';
                ?>
              </td>
              <td class="muted"><?php echo e($r['created_at']); ?></td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="5" class="text-center muted">Belum ada riwayat peminjaman.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <footer class="pinba-footer">© <?= date('Y') ?> PINMES — <?= e($user['name']) ?></footer>
</div>

<?php include 'views/partials/scripts.php'; ?>
</body>
</html>
