<?php
session_start();
$is_guest = $_SESSION['guest_mode'] ?? false;
require_once '../controller/config.php';
$current = basename($_SERVER['PHP_SELF']);
if (empty($_SESSION['user'])) { header('Location: ../index.php'); exit; }
$user = $_SESSION['user'];

$err = '';
$ok  = '';

if (!empty($_SESSION['borrow_flash'])) {
    $flash = $_SESSION['borrow_flash'];
    unset($_SESSION['borrow_flash']);
    if (!empty($flash['err'])) {
        $err = $flash['err'];
    }
    if (!empty($flash['ok'])) {
        $ok = $flash['ok'];
    }
}

// --- AJAX: ambil items per bengkel ---
if (isset($_GET['workshop_items'])) {
    $workshop = intval($_GET['workshop_items']);
    $sql = 'SELECT i.*, w.name AS workshop_name FROM items i LEFT JOIN workshops w ON i.workshop_id=w.id';
    if ($workshop > 0) $sql .= " WHERE i.workshop_id={$workshop}";
    $sql .= ' ORDER BY i.created_at DESC';
    $res = db()->query($sql);
    $out = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $out[] = ['id'=>intval($r['id']),'code'=>$r['code']??'','name'=>$r['name']??'','quantity'=>intval($r['quantity']??0),'workshop_name'=>$r['workshop_name']??''];
        }
    }
    header('Content-Type: application/json');
    echo json_encode($out);
    exit;
}

// --- Load workshops & items ---
$workshops_arr = [];
$res_workshop = db()->query('SELECT * FROM workshops ORDER BY name ASC');
if ($res_workshop) { while ($w = $res_workshop->fetch_assoc()) $workshops_arr[] = $w; }

$items_arr = [];
$res_items = db()->query('SELECT i.*, w.name AS workshop_name FROM items i LEFT JOIN workshops w ON i.workshop_id=w.id ORDER BY i.created_at DESC');
if ($res_items) { while ($it = $res_items->fetch_assoc()) $items_arr[] = $it; }

$settings_table_exists = db()->query("SHOW TABLES LIKE 'settings'")->num_rows > 0;
if (!$settings_table_exists) {
    db()->query("CREATE TABLE IF NOT EXISTS settings (id INT AUTO_INCREMENT PRIMARY KEY, `key` VARCHAR(100) NOT NULL UNIQUE, `value` TEXT DEFAULT NULL) ENGINE=InnoDB");
    db()->query("INSERT IGNORE INTO settings (`key`,`value`) VALUES ('fine_per_day','20000')");
}

// ===== ADMIN ACTIONS =====
if(in_array($user['role'], ['penanggung jawab'])) {
    if (isset($_GET['approve'])) {
        $id = intval($_GET['approve']);
        $loanRow = db()->query("SELECT * FROM loans WHERE id={$id} LIMIT 1");
        $loan = $loanRow ? $loanRow->fetch_assoc() : null;
        if ($loan && $loan['status'] === 'pending') {
            $details = [];
            $hasLD = db()->query("SHOW TABLES LIKE 'loan_details'");
            if ($hasLD && $hasLD->num_rows > 0) {
                $ld = db()->query("SELECT item_id, qty FROM loan_details WHERE loan_id={$id}");
                if ($ld) while ($d = $ld->fetch_assoc()) $details[] = ['item_id'=>intval($d['item_id']),'qty'=>intval($d['qty'])];
            }
            if (!$details && !empty($loan['item_id'])) $details[] = ['item_id'=>intval($loan['item_id']),'qty'=>intval($loan['qty'])];
            if (!$details) {
                $err = 'Data barang tidak valid.';
            } else {
                $ok_stock = true;
                foreach ($details as $d) {
                    $sr = db()->query("SELECT quantity FROM items WHERE id={$d['item_id']} LIMIT 1")->fetch_assoc();
                    if (!$sr || intval($sr['quantity']) < $d['qty']) { $ok_stock = false; break; }
                }
                if (!$ok_stock) { $err = 'Stok tidak mencukupi.'; }
                else {
                    db()->begin_transaction();
                    try {
                        foreach ($details as $d) db()->query("UPDATE items SET quantity=quantity-{$d['qty']} WHERE id={$d['item_id']}");
                        db()->query("UPDATE loans SET status='borrowed' WHERE id={$id}");
                        db()->query("INSERT INTO logs (user_id,action,meta) VALUES ({$user['id']},'approve_loan','loan_id:{$id}')");
                        db()->commit();
                        $ok = 'Peminjaman disetujui.';
                    } catch (Throwable $ex) { db()->rollback(); $err = 'Gagal: '.$ex->getMessage(); }
                }
            }
        } else { $err = 'Peminjaman tidak ditemukan atau bukan status pending.'; }
    }
    if (isset($_GET['reject'])) {
        $id = intval($_GET['reject']);
        $loan = db()->query("SELECT * FROM loans WHERE id={$id} LIMIT 1")->fetch_assoc();
        if ($loan && $loan['status'] === 'pending') {
            db()->query("UPDATE loans SET status='rejected' WHERE id={$id}");
            db()->query("INSERT INTO logs (user_id,action,meta) VALUES ({$user['id']},'reject_loan','loan_id:{$id}')");
            $ok = 'Peminjaman ditolak.';
        } else { $err = 'Tidak ditemukan atau bukan pending.'; }
    }
    // Return now handled via POST with photo upload (return_submit) below
    if (isset($_POST['return_submit'])) {
        $id = intval($_POST['return_id']);
        $loan = db()->query("SELECT * FROM loans WHERE id={$id} LIMIT 1")->fetch_assoc();
        if ($loan && strtolower($loan['status']) === 'borrowed') {
            if (empty($_FILES['return_photo']) || $_FILES['return_photo']['error'] !== UPLOAD_ERR_OK) {
                $err = 'Foto bukti pengembalian wajib diupload.';
            } else {
                $ext = strtolower(pathinfo($_FILES['return_photo']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                    $err = 'Format foto tidak didukung.';
                } else {
                    $return_photo = 'return_' . time() . '_' . rand(1000,9999) . '.' . $ext;
                    move_uploaded_file($_FILES['return_photo']['tmp_name'], '../uploads/' . $return_photo);

                    if (!empty($loan['item_id'])) db()->query("UPDATE items SET quantity=quantity+{$loan['qty']} WHERE id={$loan['item_id']}");
                    db()->query("UPDATE loans SET status='returned',return_photo='{$return_photo}',return_date=CURDATE() WHERE id={$id}");
                    db()->query("INSERT INTO logs (user_id,action,meta) VALUES ({$user['id']},'return_loan','loan_id:{$id}')");
                    $due = strtotime($loan['due_date']);
                    if (time() > $due) {
                        $days_late = ceil((time()-$due)/86400);
                        $fps = db()->query("SELECT value FROM settings WHERE `key`='fine_per_day' LIMIT 1");
                        $fp  = ($fps && $fps->num_rows) ? intval($fps->fetch_assoc()['value']) : 10000;
                        $fine = $days_late * $fp;
                        db()->query("UPDATE loans SET fine={$fine} WHERE id={$id}");
                        $ok = "Dikembalikan. Foto bukti tersimpan. Denda Rp".number_format($fine,0,',','.')." ({$days_late} hari).";
                    } else { $ok = 'Barang ditandai sudah dikembalikan. Foto bukti tersimpan.'; }
                }
            }
        } else { $err = 'Tidak ditemukan atau bukan status borrowed.'; }
        if ($err || $ok) {
            $_SESSION['borrow_flash'] = $err !== '' ? ['err' => $err] : ['ok' => $ok];
            header('Location: borrow.php');
            exit;
        }
    }
    if (isset($_GET['approve']) || isset($_GET['reject']) || isset($_GET['return'])) {
        $_SESSION['borrow_flash'] = $err !== '' ? ['err' => $err] : ['ok' => $ok];
        header('Location: borrow.php');
        exit;
    }
}

// ===== SUBMIT PEMINJAMAN (MAHASISWA) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (in_array($user['role']??'', ['admin','kepala','kepala_bengkel','kepala bengkel','penanggung jawab'])) {
        $err = 'Admin tidak dapat mengajukan peminjaman di sini.';
    } else {
        $item_id  = intval($_POST['item_id'] ?? 0);
        $qty      = 1;
        $reason   = $_POST['reason'] ?? '';
        $loan_date= date('Y-m-d');
        $due_date = date('Y-m-d', strtotime('+1 day'));
        $row      = db()->query("SELECT quantity FROM items WHERE id={$item_id}")->fetch_assoc();
        $stock    = $row ? intval($row['quantity']) : 0;
        if ($item_id <= 0 || !$row) $err = 'Data barang tidak valid.';
        elseif ($stock < 1) $err = 'Stok barang tidak tersedia.';
        else {
            $existing = db()->query("SELECT id FROM loans WHERE user_id={$user['id']} AND item_id={$item_id} AND status IN ('pending','borrowed') LIMIT 1");
            if ($existing && $existing->num_rows > 0) {
                $err = 'Anda sudah meminjam barang ini. Tunggu sampai dikembalikan.';
            } else {
                // Handle photo upload
                $borrow_photo = '';
                if (isset($_FILES['borrow_photo']) && $_FILES['borrow_photo']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['borrow_photo']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                        $borrow_photo = 'borrow_' . time() . '_' . rand(1000,9999) . '.' . $ext;
                        move_uploaded_file($_FILES['borrow_photo']['tmp_name'], '../uploads/' . $borrow_photo);
                    }
                }
                if (!$borrow_photo) {
                    $err = 'Foto bukti peminjaman wajib diupload.';
                } else {
                $ln   = 'LN'.time().rand(100,999);
                $stmt = db()->prepare('INSERT INTO loans (loan_number,user_id,item_id,qty,reason,loan_date,due_date,status,borrow_photo) VALUES (?,?,?,?,?,?,?,?,?)');
                $st   = 'pending';
                $stmt->bind_param('siiisssss',$ln,$user['id'],$item_id,$qty,$reason,$loan_date,$due_date,$st,$borrow_photo);
                $stmt->execute();
                $loan_id = db()->insert_id;
                $chk = db()->query("SHOW TABLES LIKE 'loan_details'");
                if ($chk && $chk->num_rows) {
                    $s2 = db()->prepare('INSERT INTO loan_details (loan_id,item_id,qty) VALUES (?,?,?)');
                    $s2->bind_param('iii',$loan_id,$item_id,$qty);
                    $s2->execute();
                }
                db()->query("INSERT INTO logs (user_id,action,meta) VALUES ({$user['id']},'create_loan','loan_id:{$loan_id}')");
                $ok = 'Permintaan peminjaman dikirim. Menunggu konfirmasi Penanggung Jawab Bengkel.';
            }
        }
    }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $_SESSION['borrow_flash'] = $err !== '' ? ['err' => $err] : ['ok' => $ok];
        header('Location: borrow.php');
        exit;
    }
}

// Hitung denda manual (admin)
if (isset($_GET['calc_fine']) && ($user['role']??'') === 'admin') {
    $id   = intval($_GET['calc_fine']);
    $loan = db()->query("SELECT * FROM loans WHERE id={$id}")->fetch_assoc();
    if ($loan) {
        $due   = strtotime($loan['due_date']);
        $ret   = strtotime($loan['return_date'] ?? 'now');
        if ($ret > $due) {
            $days = ceil(($ret-$due)/86400);
            $fps  = db()->query("SELECT value FROM settings WHERE `key`='fine_per_day' LIMIT 1");
            $fp   = ($fps && $fps->num_rows) ? intval($fps->fetch_assoc()['value']) : 10000;
            $fine = $days * $fp;
            db()->query("UPDATE loans SET fine={$fine} WHERE id={$id}");
            $ok   = "Denda: Rp".number_format($fine,0,',','.')." ({$days} hari).";
        } else { $ok = "Tidak terlambat / belum dikembalikan."; }
    }
}

// Denda per hari update (admin)
if (isset($_POST['save_fine']) && ($user['role']??'') === 'admin') {
    $fn = intval($_POST['fine_per_day'] ?? 2000);
    db()->query("INSERT INTO settings (`key`,`value`) VALUES ('fine_per_day',{$fn}) ON DUPLICATE KEY UPDATE value={$fn}");
    $ok = 'Denda per hari diperbarui menjadi Rp'.number_format($fn,0,',','.').'.';
}

// Query loans
$where_sql = '';
if (in_array(($user['role']??''), ['penanggung jawab'])) {
    $where = [];
    if (!empty($_GET['from']) && !empty($_GET['to'])) {
        $f = db()->real_escape_string($_GET['from']);
        $t = db()->real_escape_string($_GET['to']);
        $where[] = "DATE(l.created_at) BETWEEN '{$f}' AND '{$t}'";
    }
    if (!empty($_GET['month'])) $where[] = "MONTH(l.created_at)=".intval($_GET['month']);
    if (!empty($_GET['year']))  $where[] = "YEAR(l.created_at)=".intval($_GET['year']);
    if ($where) $where_sql = 'WHERE '.implode(' AND ',$where);
    $my_loans = db()->query("SELECT l.*,i.name AS item_name,u.name AS peminjam FROM loans l LEFT JOIN items i ON l.item_id=i.id LEFT JOIN users u ON l.user_id=u.id {$where_sql} ORDER BY l.created_at DESC");
} else {
    $my_loans = db()->query("SELECT l.*,i.name AS item_name FROM loans l LEFT JOIN items i ON l.item_id=i.id WHERE l.user_id=".intval($user['id'])." ORDER BY l.created_at DESC");
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Peminjaman — PINMES</title>
  <meta name="description" content="Halaman peminjaman barang bengkel Jurusan Teknik Mesin.">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/theme.css">
</head>
<body>
<canvas id="tech-bg"></canvas>
<img src="../assets/img/dekor-kiri.png"  class="fixed-left"  alt="">
<img src="../assets/img/dekor-kanan.png" class="fixed-right" alt="">

<?php include 'partials/navbar.php'; ?>

<div class="page-wrapper">
  <h1 class="page-heading">🔄 Peminjaman</h1>

  <?php if($err): ?><div class="alert-pinba danger mb-3"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <?php if($ok):  ?><div class="alert-pinba success mb-3"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

  <!-- Form Ajukan Peminjaman (Mahasiswa) -->
  <?php if (($user['role']??'') === 'mahasiswa'): ?>
  <div class="card-panel mb-4">
    <h4 class="mb-3" style="font-size:1.1rem;font-weight:700">📝 Ajukan Peminjaman</h4>
    <form method="post" class="row g-3" enctype="multipart/form-data">
      <div class="col-md-4">
        <label class="form-label">Pilih Bengkel</label>
        <select id="filter_workshop" class="form-select">
          <?php foreach($workshops_arr as $workshop): ?>
            <option value="<?= intval($workshop['id']) ?>"><?= e($workshop['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-8">
        <label class="form-label">Pilih Barang</label>
        <?php
          $first_item = $items_arr[0] ?? null;
          $first_item_label = $first_item ? (($first_item['code']??'').'-'.$first_item['name'].' (stok:'.intval($first_item['quantity']??0).')') : '';
        ?>
        <input type="hidden" name="item_id" id="item_id" value="<?= $first_item ? intval($first_item['id']) : 0 ?>">
        <div class="row g-2">
          <div class="col-md-6">
            <input type="text" id="item_search" class="form-control" list="item_options" required
              value="<?= e($first_item_label) ?>" placeholder="Ketik kode atau nama barang...">
          </div>
          <div class="col-md-6">
            <select id="item_select" class="form-select" required>
              <?php foreach($items_arr as $it): ?>
                <?php $item_label = ($it['code']??'').'-'.$it['name'].' (stok:'.intval($it['quantity']??0).')'; ?>
                <option value="<?= intval($it['id']) ?>"><?= e($item_label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <datalist id="item_options">
          <?php foreach($items_arr as $it): ?>
            <option value="<?= e(($it['code']??'').'-'.$it['name'].' (stok:'.intval($it['quantity']??0).')') ?>"></option>
          <?php endforeach; ?>
        </datalist>
      </div>
      <div class="col-md-2">
        <label class="form-label">Jumlah</label>
        <input type="text" value="1 barang" class="form-control" readonly>
      </div>
      <div class="col-md-3">
        <label class="form-label">Batas Waktu</label>
        <input type="text" value="24 jam (sampai <?= date('d M Y', strtotime('+1 day')) ?>)" class="form-control" readonly>
      </div>
      <div class="col-md-5">
        <label class="form-label">Keterangan</label>
        <input type="text" name="reason" class="form-control" placeholder="Untuk praktikum...">
      </div>
      <div class="col-12">
        <label class="form-label">📸 Foto Bukti Peminjaman <span style="color:#f87171">*</span></label>
        <input type="file" name="borrow_photo" class="form-control" accept="image/*" required>
        <small style="color:var(--muted)">Ambil foto barang yang akan dipinjam sebagai bukti</small>
      </div>
      <div class="col-12 text-end">
        <button class="btn btn-primary">Kirim Permintaan</button>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <!-- Filter Tanggal (Admin / Penanggung Jawab) -->
  <?php if (in_array(($user['role']??''), ['penanggung jawab'])): ?>
  <div class="card-panel mb-4">
    <h5 class="mb-3" style="font-size:.95rem;font-weight:600;color:var(--muted)">📅 Filter Peminjaman</h5>
    <form method="get" class="row g-3">
      <div class="col-md-3">
        <label class="form-label">Tanggal Mulai</label>
        <input type="date" name="from" value="<?= htmlspecialchars($_GET['from']??'') ?>" class="form-control">
      </div>
      <div class="col-md-3">
        <label class="form-label">Tanggal Selesai</label>
        <input type="date" name="to" value="<?= htmlspecialchars($_GET['to']??'') ?>" class="form-control">
      </div>
      <div class="col-md-2">
        <label class="form-label">Bulan</label>
        <select name="month" class="form-select">
          <option value="">Semua</option>
          <?php for($m=1;$m<=12;$m++): ?>
            <option value="<?= $m ?>" <?= (isset($_GET['month'])&&$_GET['month']==$m)?'selected':'' ?>><?= month_id($m) ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Tahun</label>
        <select name="year" class="form-select">
          <option value="">Semua</option>
          <?php for($y=date('Y');$y>=2020;$y--): ?>
            <option value="<?= $y ?>" <?= (isset($_GET['year'])&&$_GET['year']==$y)?'selected':'' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-md-2 align-self-end">
        <button class="btn btn-primary w-100 ">Filter</button>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <!-- Tabel Peminjaman -->
  <div class="card-panel">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th style="width:48px">No</th>
            <?php if(in_array(($user['role']??''), ['penanggung jawab'])): ?><th>Peminjam</th><?php endif; ?>
            <th>No. Pinjam</th>
            <th>Barang</th>
            <th style="width:70px">Qty</th>
            <th>Status</th>              <th style="width:110px">Tgl Pinjam</th>
            <th style="width:110px">Jatuh Tempo</th>
            <th>Denda</th>
            <th>Foto</th>
            <?php if(in_array(($user['role']??''), ['penanggung jawab'])): ?><th style="width:200px">Aksi</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if ($my_loans && $my_loans->num_rows > 0): $no = 1;
                while ($r = $my_loans->fetch_assoc()): ?>
            <tr>
              <td class="muted"><?= $no++ ?></td>
              <?php if(in_array(($user['role']??''), ['penanggung jawab'])): ?>
                <td><?= e($r['peminjam']??'') ?></td>
              <?php endif; ?>
              <td><code style="color:var(--accent-sky);font-size:.8rem"><?= e($r['loan_number']) ?></code></td>
              <td style="white-space:nowrap"><?= e($r['item_name']) ?></td>
              <td><?= e($r['qty']) ?></td>
              <td>
                <?php
                  $s = $r['status'] ?? '';
                  $badges = ['pending'=>'badge-pending','borrowed'=>'badge-borrowed','returned'=>'badge-returned','rejected'=>'badge-rejected'];
                  $labels = ['pending'=>'Tertunda','borrowed'=>'Sedang Dipinjam','returned'=>'Dikembalikan','rejected'=>'Ditolak'];
                  $cls = $badges[$s] ?? '';
                  $lbl = $labels[$s] ?? $s;
                  echo "<span class=\"badge-status {$cls}\">{$lbl}</span>";
                ?>
              </td>
              <td class="muted" style="font-size:.82rem"><?= date('d M Y', strtotime($r['created_at']??$r['loan_date'])) ?></td>
              <td class="muted" style="font-size:.82rem"><?= date('d M Y', strtotime($r['due_date'])) ?></td>
              <td style="color:#f87171;font-weight:600">
                <?php if(in_array(($user['role']??''), ['penanggung jawab'])): ?>
                  <?= ($r['fine']??0)>0 ? 'Rp'.number_format($r['fine'],0,',','.') : '<a href="?calc_fine='.$r['id'].'" class="btn btn-warning btn-sm">Hitung</a>' ?>
                <?php else: ?>
                  <?= ($r['fine']??0)>0 ? 'Rp'.number_format($r['fine'],0,',','.') : '-' ?>
                <?php endif; ?>
              </td>
              <td>
                <div style="display:flex;gap:6px;align-items:center">
                  <?php if (!empty($r['borrow_photo'])): ?>
                    <a href="#" onclick="previewPhoto('<?= e($r['borrow_photo']) ?>','Bukti Peminjaman');return false" title="Lihat foto peminjaman">
                      <img src="../uploads/<?= e($r['borrow_photo']) ?>" alt="Foto pinjam" style="width:44px;height:44px;object-fit:cover;border-radius:8px;border:2px solid rgba(96,165,250,.3);transition:.2s" onmouseover="this.style.borderColor='#60a5fa';this.style.transform='scale(1.1)'" onmouseout="this.style.borderColor='rgba(96,165,250,.3)';this.style.transform='scale(1)'" loading="lazy">
                    </a>
                  <?php else: ?>
                    <span class="muted" style="font-size:11px">📸 -</span>
                  <?php endif; ?>
                  <?php if (!empty($r['return_photo'])): ?>
                    <a href="#" onclick="previewPhoto('<?= e($r['return_photo']) ?>','Bukti Pengembalian');return false" title="Lihat foto pengembalian">
                      <img src="../uploads/<?= e($r['return_photo']) ?>" alt="Foto kembali" style="width:44px;height:44px;object-fit:cover;border-radius:8px;border:2px solid rgba(34,197,94,.3);transition:.2s" onmouseover="this.style.borderColor='#22c55e';this.style.transform='scale(1.1)'" onmouseout="this.style.borderColor='rgba(34,197,94,.3)';this.style.transform='scale(1)'" loading="lazy">
                    </a>
                  <?php endif; ?>
                </div>
              </td>
              <?php if(in_array(($user['role']??''), ['penanggung jawab'])): ?>
                <td>
                  <?php if($r['status']==='pending'): ?>
                    <a href="?approve=<?= $r['id'] ?>" class="btn btn-success btn-sm">Setujui</a>
                    <a href="?reject=<?= $r['id'] ?>"  class="btn btn-danger btn-sm">Tolak</a>
                  <?php elseif($r['status']==='borrowed'): ?>
                    <form method="post" enctype="multipart/form-data" class="d-flex gap-1 flex-wrap" onsubmit="return confirm('Tandai barang sudah dikembalikan?')">
                      <input type="hidden" name="return_id" value="<?= $r['id'] ?>">
                      <input type="file" name="return_photo" class="form-control form-control-sm" style="max-width:110px;font-size:.7rem" accept="image/*" required>
                      <button type="submit" name="return_submit" class="btn btn-primary btn-sm">Kembali</button>
                    </form>
                  <?php else: ?>
                    <span class="muted">—</span>
                  <?php endif; ?>
                </td>
              <?php endif; ?>
            </tr>
          <?php endwhile; else: ?>
            <tr><td colspan="10" class="text-center muted py-4">Belum ada data peminjaman.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <footer class="pinba-footer">© <?= date('Y') ?> PINMES — <?= e($user['name']) ?></footer>
</div>

<script>
const initialItems = <?= json_encode(array_map(function($it) {
  return [
    'id' => intval($it['id']),
    'code' => $it['code'] ?? '',
    'name' => $it['name'] ?? '',
    'quantity' => intval($it['quantity'] ?? 0),
  ];
}, $items_arr), JSON_UNESCAPED_UNICODE) ?>;

function itemLabel(item) {
  return (item.code ? item.code + '-' : '') + item.name + ' (stok:' + item.quantity + ')';
}

// Filter bengkel -> update autocomplete barang
document.addEventListener('DOMContentLoaded', function(){
  const workshopSel  = document.getElementById('filter_workshop');
  const itemInput = document.getElementById('item_search');
  const itemSelect = document.getElementById('item_select');
  const itemIdInput = document.getElementById('item_id');
  const itemOptions = document.getElementById('item_options');
  const form = itemInput ? itemInput.closest('form') : null;
  if (!workshopSel || !itemInput || !itemSelect || !itemIdInput || !itemOptions || !form) return;

  let currentItems = initialItems;

  function syncItemId() {
    const value = itemInput.value.trim();
    const found = currentItems.find(item => itemLabel(item) === value);
    itemIdInput.value = found ? found.id : '';
    if (found) itemSelect.value = String(found.id);
    itemInput.setCustomValidity(found ? '' : 'Pilih barang dari daftar.');
  }

  function renderItems(items) {
    currentItems = items;
    itemOptions.innerHTML = '';
    itemSelect.innerHTML = '';
    items.forEach(item => {
      const label = itemLabel(item);
      const option = document.createElement('option');
      option.value = label;
      itemOptions.appendChild(option);

      const selectOption = document.createElement('option');
      selectOption.value = item.id;
      selectOption.textContent = label;
      itemSelect.appendChild(selectOption);
    });

    if (items.length) {
      itemInput.value = itemLabel(items[0]);
      itemIdInput.value = items[0].id;
      itemSelect.value = String(items[0].id);
      itemSelect.disabled = false;
      itemInput.setCustomValidity('');
    } else {
      itemInput.value = '';
      itemIdInput.value = '';
      itemSelect.disabled = true;
      itemInput.setCustomValidity('Tidak ada barang pada bengkel ini.');
    }
  }

  itemInput.addEventListener('input', syncItemId);
  itemSelect.addEventListener('change', function() {
    const found = currentItems.find(item => String(item.id) === this.value);
    if (!found) return;
    itemInput.value = itemLabel(found);
    itemIdInput.value = found.id;
    itemInput.setCustomValidity('');
  });
  form.addEventListener('submit', function(event) {
    syncItemId();
    if (!itemIdInput.value) {
      event.preventDefault();
      itemInput.reportValidity();
    }
  });

  workshopSel.addEventListener('change', function(){
    const wid = this.value;
    if (wid==='0') { renderItems(initialItems); return; }
    fetch('?workshop_items='+encodeURIComponent(wid)).then(r=>r.json()).then(data=>{
      renderItems(data || []);
    }).catch(err=>console.error(err));
  });
});
</script>
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
