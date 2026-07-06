<?php
require_once '../controller/config.php';
$user     = current_user();
$is_guest = !$user;
$current  = basename($_SERVER['PHP_SELF']);

// Ambil daftar workshop untuk filter
$workshops_res = db()->query("SELECT * FROM workshops ORDER BY name ASC");
$workshops_arr = [];
if ($workshops_res) {
    while ($w = $workshops_res->fetch_assoc()) $workshops_arr[] = $w;
}

$selected_workshop = intval($_GET['workshop'] ?? 0);
$where = ($selected_workshop > 0) ? " WHERE i.workshop_id={$selected_workshop}" : '';

$items = db()->query(
    "SELECT i.*, c.name AS category_name, w.name AS workshop_name,
     COALESCE(b.borrowed_qty, 0) AS borrowed_qty
     FROM items i
     LEFT JOIN categories c ON i.category_id=c.id
     LEFT JOIN workshops w  ON i.workshop_id=w.id
     LEFT JOIN (
       SELECT item_id, SUM(qty) AS borrowed_qty
       FROM loans
       WHERE status='borrowed'
       GROUP BY item_id
     ) b ON i.id=b.item_id
     {$where}
     ORDER BY i.created_at DESC"
);

// Hitung total untuk stat card
$total_items  = db()->query("SELECT COUNT(*) as n FROM items")->fetch_assoc()['n'] ?? 0;
$total_cats   = db()->query("SELECT COUNT(*) as n FROM categories")->fetch_assoc()['n'] ?? 0;
$total_pinjam = db()->query("SELECT COALESCE(SUM(qty),0) as n FROM loans WHERE status='borrowed'")->fetch_assoc()['n'] ?? 0;
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Stok Barang — PINMES</title>
  <meta name="description" content="Lihat stok barang tersedia di setiap bengkel Jurusan Teknik Mesin Poltesa.">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/theme.css">
  <style>
    /* Form select override */
    .filter-bar { display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:16px; }
    .filter-bar .form-select { max-width:220px; }
  </style>
</head>
<body>
<canvas id="tech-bg"></canvas>
<img src="../assets/img/dekor-kiri.png"  class="fixed-left"  alt="">
<img src="../assets/img/dekor-kanan.png" class="fixed-right" alt="">

<?php include 'partials/navbar.php'; ?>

<div class="page-wrapper">

  <h1 class="page-heading">📦 Stok Barang</h1>

  <?php if ($is_guest): ?>
    <div class="alert-pinba info mb-4">
      Anda sedang melihat stok sebagai <strong>tamu</strong>.
      Untuk melakukan peminjaman, silakan
      <a href="../index.php" style="color:var(--accent-sky)">login terlebih dahulu</a>.
    </div>
  <?php endif; ?>

  <!-- Stat Cards -->
  <div class="stat-grid mb-4">
    <div class="stat-card">
      <div class="stat-icon">📦</div>
      <div class="stat-label">Total Barang</div>
      <div class="stat-value accent"><?= number_format($total_items) ?></div>
    </div>
    
    <div class="stat-card">
      <div class="stat-icon">🔄</div>
      <div class="stat-label">Sedang Dipinjam</div>
      <div class="stat-value yellow"><?= number_format($total_pinjam) ?></div>
    </div>
  </div>

  <!-- Filter & Table -->
  <div class="card-panel mb-4">

    <!-- Filter Workshop -->
    <form method="get" class="filter-bar">
      <label class="form-label mb-0" style="white-space:nowrap">🏭 Filter Bengkel:</label>
      <select name="workshop" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="0">Semua Bengkel</option>
        <?php foreach ($workshops_arr as $ws): ?>
          <option value="<?= intval($ws['id']) ?>"
            <?= $selected_workshop == $ws['id'] ? 'selected' : '' ?>>
            <?= e($ws['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th style="width:48px">No</th>
            <th>Kode</th>
            <th>Nama Barang</th>
            <th>Kategori</th>
            <th>Bengkel</th>
            <th style="width:90px">Jumlah</th>
            <th style="width:130px">Sedang Dipinjam</th>
            <th style="width:140px">Keterangan</th>
            <th style="width:130px">Tanggal</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($items && $items->num_rows > 0): $no = 1;
                while ($r = $items->fetch_assoc()): ?>
            <tr>
              <td class="muted"><?= $no++ ?></td>
              <td><code style="color:var(--accent-sky)"><?= e($r['code']) ?></code></td>
              <td><?= e($r['name']) ?></td>
              <td><?= e($r['category_name']) ?></td>
              <td><?= e($r['workshop_name']) ?></td>
              <td>
                <span class="<?= intval($r['quantity'])<=0?'text-danger':'' ?> fw-semibold">
                  <?= e($r['quantity']) ?>
                </span>
              </td>
              <td>
                <span class="<?= intval($r['borrowed_qty'])>0?'text-warning':'' ?> fw-semibold">
                  <?= e($r['borrowed_qty']) ?>
                </span>
              </td>
              <td>
                <?= intval($r['borrowed_qty']) > 0
                  ? '<span class="badge-status badge-borrowed">Sedang Dipinjam</span>'
                  : '<span class="badge-status badge-returned">Tersedia</span>' ?>
              </td>
              <td class="muted" style="font-size:.82rem"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
            </tr>
          <?php endwhile; else: ?>
            <tr><td colspan="9" class="text-center muted py-4">Belum ada data barang.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <footer class="pinba-footer">© <?= date('Y') ?> PINMES — <?= e($user['name'] ?? 'Tamu') ?></footer>
</div>

<?php include 'partials/scripts.php'; ?>
</body>
</html>
