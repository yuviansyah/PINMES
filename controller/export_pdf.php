<?php
require_once 'config.php';
if (empty($_SESSION['user'])) { header('Location: ../index.php'); exit; }
$user = $_SESSION['user'];
if (!in_array($user['role'], ['kepala','kepala_bengkel','kepala bengkel','penanggung jawab'])) {
    echo 'Akses ditolak.';
    exit;
}

$current = basename($_SERVER['PHP_SELF']);

// Ambil parameter bulan/tahun dari query
$sel_month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('n'));
$sel_year  = isset($_GET['year'])  ? intval($_GET['year'])  : intval(date('Y'));
$sel_status = isset($_GET['status']) ? $_GET['status'] : '';
$export_pdf = isset($_GET['export']) && $_GET['export'] == '1';

// Validasi range
if ($sel_month < 1 || $sel_month > 12) $sel_month = intval(date('n'));
if ($sel_year < 2000 || $sel_year > 2100) $sel_year = intval(date('Y'));

$status_filter = '';
if ($sel_status !== '') {
    $status_filter = " AND l.status = '" . db()->real_escape_string($sel_status) . "'";
}

function status_label($s) {
    $labels = [
        'pending'  => 'Tertunda',
        'borrowed' => 'Sedang Dipinjam',
        'returned' => 'Dikembalikan',
        'rejected' => 'Ditolak',
    ];
    return $labels[$s] ?? $s;
}

// Range tanggal
$start_date = sprintf('%04d-%02d-01', $sel_year, $sel_month);
$end_date = date('Y-m-d', strtotime(sprintf('%s +1 month -1 day', $start_date)));

$ws_filter = '';
if (($user['role'] ?? '') === 'penanggung jawab') {
    $ws_id = intval($user['workshop_id'] ?? 0);
    if ($ws_id > 0) $ws_filter = " AND i.workshop_id=".$ws_id;
}

$sql = "SELECT l.*, u.name as user_name, u.email as user_email, i.code as item_code, i.name as item_name
        FROM loans l
        LEFT JOIN users u ON l.user_id=u.id
        LEFT JOIN items i ON l.item_id=i.id
        WHERE DATE(l.loan_date) BETWEEN '{$start_date}' AND '{$end_date}'{$status_filter}{$ws_filter}
        ORDER BY l.created_at DESC";

$res = db()->query($sql);

// === BAGIAN EXPORT PDF (tidak kirim HTML dulu) ===
if ($export_pdf) {
    require_once __DIR__ . '/../vendor/autoload.php';
    $dompdf = new \Dompdf\Dompdf();

    // ambil semua data lagi khusus untuk PDF
    $res2 = db()->query($sql);
    ob_start();
    ?>
    <h2 style="text-align:center;">Laporan Peminjaman — <?= e(date('Y-m-d H:i')) ?></h2>
    <table border="1" cellspacing="0" cellpadding="6" width="100%">
      <thead>
        <tr>
          <th>No. Pinjam</th>
          <th>Peminjam</th>
          <th>Barang</th>
          <th>Jml</th>
          <th>Tgl Pinjam</th>
          <th>Jatuh Tempo</th>
          <th>Kembali</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if($res2 && $res2->num_rows>0): while($r = $res2->fetch_assoc()): ?>
          <tr>
            <td><?= e($r['loan_number']) ?></td>
            <td><?= e($r['user_name'] . ' (' . $r['user_email'] . ')') ?></td>
            <td><?= e(($r['item_code'] ? $r['item_code'] . ' - ' : '') . $r['item_name']) ?></td>
            <td><?= intval($r['qty']) ?></td>
            <td><?= e($r['loan_date']) ?></td>
            <td><?= e($r['due_date']) ?></td>
<td><?= e($r['return_date'] ?? '') ?></td>
            <td><?= e(status_label($r['status'])) ?></td>
          </tr>
        <?php endwhile; else: ?>
          <tr><td colspan="8">Tidak ada data</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    <?php
    $html = ob_get_clean();

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    // Hapus semua output buffer yang tersisa sebelum kirim header PDF
    ob_end_clean();
    $dompdf->stream("Laporan_Peminjaman_{$sel_year}-{$sel_month}.pdf", ["Attachment" => true]);
    exit;
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Laporan Peminjaman — PINMES Admin</title>
  <meta name="description" content="Laporan peminjaman barang bengkel Teknik Mesin.">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/theme.css">
  <style>
  .filter-bar { display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
  </style>
</head>
<body>
<canvas id="tech-bg"></canvas>

<?php $base_path = '..'; include '../admin/partials/navbar.php'; ?>

<div class="page-wrapper">
  <h1 class="page-heading">🖨️ Laporan Peminjaman</h1>

  <div class="card-panel mb-4">
    <form method="get" class="filter-bar row g-3 align-items-end">
      <div class="col-md-3">
        <label class="form-label">Bulan</label>
        <select name="month" class="form-select">
          <?php for($m=1;$m<=12;$m++): ?>
            <option value="<?= $m ?>" <?= $m === $sel_month ? 'selected' : '' ?>><?= month_id($m) ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Tahun</label>
        <select name="year" class="form-select">
          <?php for($y=intval(date('Y'))-5;$y<=intval(date('Y'))+2;$y++): ?>
            <option value="<?= $y ?>" <?= $y === $sel_year ? 'selected' : '' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="">Semua</option>
          <option value="pending" <?= $sel_status === 'pending' ? 'selected' : '' ?>>Tertunda</option>
          <option value="borrowed" <?= $sel_status === 'borrowed' ? 'selected' : '' ?>>Sedang Dipinjam</option>
          <option value="returned" <?= $sel_status === 'returned' ? 'selected' : '' ?>>Dikembalikan</option>
          <option value="rejected" <?= $sel_status === 'rejected' ? 'selected' : '' ?>>Ditolak</option>
        </select>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100">🔍 Filter</button>
      </div>
      <div class="col-md-2">
        <a href="?month=<?= $sel_month ?>&year=<?= $sel_year ?>&status=<?= e($sel_status) ?>&export=1" class="btn btn-info w-100">📄 Ekspor PDF</a>
      </div>
    </form>
  </div>

  <div class="card-panel">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>No. Pinjam</th>
            <th>Peminjam</th>
            <th>Barang</th>
            <th style="width:60px">Jml</th>
            <th style="width:110px">Tgl Pinjam</th>
            <th style="width:110px">Jatuh Tempo</th>
            <th style="width:110px">Kembali</th>
            <th style="width:100px">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if($res && $res->num_rows>0): while($r = $res->fetch_assoc()): ?>
            <tr>
              <td><code style="color:var(--accent-sky);font-size:.78rem"><?= e($r['loan_number']) ?></code></td>
              <td><?= e($r['user_name']) ?><br><small class="muted"><?= e($r['user_email']) ?></small></td>
              <td><?= e(($r['item_code'] ? $r['item_code'] . ' - ' : '') . $r['item_name']) ?></td>
              <td><?= intval($r['qty']) ?></td>
              <td class="muted" style="font-size:.82rem"><?= e($r['loan_date']) ?></td>
              <td class="muted" style="font-size:.82rem"><?= e($r['due_date']) ?></td>
              <td class="muted" style="font-size:.82rem"><?= e($r['return_date'] ?? '-') ?></td>
              <td>
                <?php $s = $r['status'] ?? ''; ?>
                <span class="badge-status badge-<?= $s ?>"><?= e(status_label($s)) ?></span>
              </td>
            </tr>
          <?php endwhile; else: ?>
            <tr><td colspan="8" class="text-center muted py-4">Tidak ada data peminjaman.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <footer class="pinba-footer">© <?= date('Y') ?> PINMES — <?= e($user['name']) ?></footer>
</div>

<?php include '../admin/partials/scripts.php'; ?>
</body>
</html>
