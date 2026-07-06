<?php
require_once '../controller/config.php';
if (empty($_SESSION['user'])) { header('Location: ../index.php'); exit; }
$user = $_SESSION['user'];
if (!in_array($user['role'], ['admin','kepala','kepala_bengkel','penanggung jawab'])) { echo 'Akses ditolak.'; exit; }
$msg = '';
$can_confirm = ($user['role'] ?? '') === 'penanggung jawab';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['loan_id'])) {
    if (!$can_confirm) { $msg = 'Akses ditolak.'; }
    else {
    $loan_id = intval($_POST['loan_id']);
    $action = $_POST['action'];
    $loan = db()->query('SELECT * FROM loans WHERE id='.intval($loan_id))->fetch_assoc();
    if (!$loan) { $msg = 'Peminjaman tidak ditemukan.'; }
    else {
        if ($action === 'approve' && $loan['status'] === 'pending') {
            $item_id = intval($loan['item_id']);
            $qty = intval($loan['qty']);
            $stockRow = db()->query("SELECT quantity FROM items WHERE id={$item_id} LIMIT 1");
            $stock = $stockRow ? $stockRow->fetch_assoc() : null;
            if (!$stock) {
                $msg = 'Barang pada peminjaman tidak ditemukan.';
            } elseif (intval($stock['quantity']) >= $qty) {
                db()->begin_transaction();
                try {
                    db()->query("UPDATE loans SET status='borrowed' WHERE id=".intval($loan_id));
                    db()->query("UPDATE items SET quantity = quantity - {$qty} WHERE id={$item_id}");
                    db()->query("INSERT INTO logs (user_id,action,meta) VALUES ({$user['id']},'approve_loan',CONCAT('loan:',{$loan_id}))");
                    db()->commit();
                    $msg = 'Peminjaman disetujui dan stok diperbarui.';
                } catch (Throwable $e) {
                    db()->rollback();
                    $msg = 'Gagal menyetujui peminjaman: ' . $e->getMessage();
                }
            } else $msg = 'Stok tidak mencukupi.';
        } elseif ($action === 'reject' && $loan['status'] === 'pending') {
            db()->query("UPDATE loans SET status='rejected' WHERE id=".intval($loan_id));
            db()->query("INSERT INTO logs (user_id,action,meta) VALUES ({$user['id']},'reject_loan',CONCAT('loan:',{$loan_id}))");
            $msg = 'Peminjaman ditolak.';
        } elseif ($action === 'return' && $loan['status'] === 'borrowed') {
            // Handle return photo upload
            $return_photo = '';
            if (isset($_FILES['return_photo']) && $_FILES['return_photo']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['return_photo']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                    $return_photo = 'return_' . time() . '_' . rand(1000,9999) . '.' . $ext;
                    move_uploaded_file($_FILES['return_photo']['tmp_name'], '../uploads/' . $return_photo);
                }
            }
            if (!$return_photo) {
                $msg = 'Foto bukti pengembalian wajib diupload.';
                $msg_type = 'danger';
            } else {
                db()->query("UPDATE loans SET status='returned', return_photo='{$return_photo}', return_date='".date('Y-m-d')."' WHERE id=".intval($loan_id));
                db()->query('UPDATE items SET quantity = quantity + '.intval($loan['qty']).' WHERE id='.intval($loan['item_id']));
                db()->query("INSERT INTO logs (user_id,action,meta) VALUES ({$user['id']},'return_loan',CONCAT('loan:',{$loan_id}))");
                $msg = 'Barang dikembalikan dan stok diperbarui. Foto bukti tersimpan.';
            }
        } else $msg = 'Aksi tidak valid.';
    }
    }
}
$res = db()->query('SELECT l.*, u.name as user_name, u.email as user_email, i.name as item_name, i.code as item_code FROM loans l LEFT JOIN users u ON l.user_id=u.id LEFT JOIN items i ON l.item_id=i.id ORDER BY l.created_at DESC');
$msg_type = 'success';
if (!$res) { $msg = 'Gagal mengambil data: ' . db()->error; $msg_type = 'danger'; }
?>
<?php
$current  = basename($_SERVER['PHP_SELF']);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Data Peminjaman — PINMES Admin</title>
  <meta name="description" content="Kelola data peminjaman barang bengkel.">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/theme.css">
</head>
<body>
<canvas id="tech-bg"></canvas>
<?php $base_path='..'; include 'partials/navbar.php'; ?>

<div class="page-wrapper">
  <h1 class="page-heading">📋 Data Peminjaman</h1>

  <?php if($msg): ?>
    <div class="alert-pinba <?= $msg_type ?> mb-4"><?= e($msg) ?></div>
  <?php endif; ?>

  <div class="card-panel">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>#</th><th>No. Pinjam</th><th>Peminjam</th><th>Email</th>
            <th>Barang</th><th style="width:60px">Jml</th>
            <th style="width:110px">Tgl Pinjam</th><th style="width:110px">Jatuh Tempo</th>
            <th>Status</th><th>Foto</th><?php if ($can_confirm): ?><th style="width:200px">Aksi</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if($res && $res->num_rows > 0): $no=1; while($r = $res->fetch_assoc()): ?>
          <tr>
            <td class="muted"><?php echo $no++; ?></td>
            <td><code style="color:var(--accent-sky);font-size:.78rem"><?php echo e($r['loan_number']); ?></code></td>
            <td><?php echo e($r['user_name']); ?></td>
            <td style="font-size:.82rem;color:var(--muted)"><?php echo e($r['user_email']); ?></td>
            <td style="white-space:nowrap"><?php echo e($r['item_code'].' - '.$r['item_name']); ?></td>
            <td><?php echo e($r['qty']); ?></td>
            <td class="muted" style="font-size:.82rem"><?php echo e($r['loan_date']); ?></td>
            <td class="muted" style="font-size:.82rem"><?php echo e($r['due_date']); ?></td>
            <td>
              <?php
                $s = $r['status'] ?? '';
                $b = ['pending'=>'badge-pending','borrowed'=>'badge-borrowed','returned'=>'badge-returned','rejected'=>'badge-rejected'];
                $l = ['pending'=>'Tertunda','borrowed'=>'Sedang Dipinjam','returned'=>'Dikembalikan','rejected'=>'Ditolak'];
                echo '<span class="badge-status '.($b[$s]??'').'">'.($l[$s]??$s).'</span>';
              ?>
            </td>
            <td>
              <div style="display:flex;gap:6px;align-items:center">
                <?php if (!empty($r['borrow_photo'])): ?>
                  <a href="#" onclick="previewPhoto('<?= e($r['borrow_photo']) ?>','Bukti Peminjaman');return false" title="Lihat foto peminjaman">
                    <img src="../uploads/<?= e($r['borrow_photo']) ?>" alt="Foto pinjam" style="width:44px;height:44px;object-fit:cover;border-radius:8px;border:2px solid rgba(96,165,250,.3);transition:.2s" onmouseover="this.style.borderColor='#60a5fa';this.style.transform='scale(1.1)'" onmouseout="this.style.borderColor='rgba(96,165,250,.3)';this.style.transform='scale(1)'" loading="lazy">
                  </a>
                <?php endif; ?>
                <?php if (!empty($r['return_photo'])): ?>
                  <a href="#" onclick="previewPhoto('<?= e($r['return_photo']) ?>','Bukti Pengembalian');return false" title="Lihat foto pengembalian">
                    <img src="../uploads/<?= e($r['return_photo']) ?>" alt="Foto kembali" style="width:44px;height:44px;object-fit:cover;border-radius:8px;border:2px solid rgba(34,197,94,.3);transition:.2s" onmouseover="this.style.borderColor='#22c55e';this.style.transform='scale(1.1)'" onmouseout="this.style.borderColor='rgba(34,197,94,.3)';this.style.transform='scale(1)'" loading="lazy">
                  </a>
                <?php endif; ?>
                <?php if (empty($r['borrow_photo']) && empty($r['return_photo'])): ?>
                  <span class="muted" style="font-size:11px">📸 -</span>
                <?php endif; ?>
              </div>
            </td>
            <?php if ($can_confirm): ?>
            <td>
              <?php if($r['status'] === 'pending'): ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="loan_id" value="<?php echo $r['id']; ?>">
                  <button name="action" value="approve" class="btn btn-success btn-sm">Setujui</button>
                </form>
                <form method="post" style="display:inline">
                  <input type="hidden" name="loan_id" value="<?php echo $r['id']; ?>">
                  <button name="action" value="reject" class="btn btn-danger btn-sm">Tolak</button>
                </form>
              <?php elseif($r['status'] === 'borrowed'): ?>
                <form method="post" enctype="multipart/form-data" class="d-flex gap-1 flex-wrap" onsubmit="return confirm('Tandai barang sudah dikembalikan?')">
                  <input type="hidden" name="loan_id" value="<?php echo $r['id']; ?>">
                  <input type="hidden" name="action" value="return">
                  <input type="file" name="return_photo" class="form-control form-control-sm" style="max-width:100px;font-size:.7rem" accept="image/*" required>
                  <button type="submit" class="btn btn-primary btn-sm">Kembali</button>
                </form>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
            <?php endif; ?>
          </tr>
          <?php endwhile; else: ?>
            <tr><td colspan="<?= $can_confirm ? 11 : 10 ?>" class="text-center text-muted py-4">Belum ada data peminjaman.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <footer class="pinba-footer">© <?= date('Y') ?> PINMES — <?= e($user['name']) ?></footer>
</div>

<!-- Image Preview Modal -->
<div id="photoModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.85);backdrop-filter:blur(4px);justify-content:center;align-items:center;cursor:pointer" onclick="closePhoto()">
  <div onclick="event.stopPropagation()" style="position:relative;max-width:90vw;max-height:90vh">
    <span style="position:absolute;top:-32px;left:0;right:0;text-align:center;color:#94a3b8;font-size:13px" id="photoLabel"></span>
    <img id="photoPreview" src="" alt="Preview" style="max-width:90vw;max-height:85vh;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.5);cursor:default">
  </div>
</div>

<script>
function previewPhoto(filename, label) {
  document.getElementById('photoPreview').src = '../uploads/' + filename;
  document.getElementById('photoLabel').textContent = '📸 ' + label + ' — klik di luar untuk tutup';
  document.getElementById('photoModal').style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function closePhoto() {
  document.getElementById('photoModal').style.display = 'none';
  document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closePhoto();
});
</script>

<?php include 'partials/scripts.php'; ?>
</body>
</html>
