<?php
/**
 * Shared Navbar Partial
 * 
 * Variabel yang harus tersedia sebelum include:
 *   $user       - array user saat ini (atau null jika tamu)
 *   $is_guest   - bool
 *   $current    - basename halaman saat ini
 *   $base_path  - path relatif ke root project (default: '..')
 */
$base_path = $base_path ?? '..';
$current   = $current   ?? basename($_SERVER['PHP_SELF']);
?>
<header class="pinba-header">
  <!-- BRAND -->
  <a href="<?= $base_path ?>/views/home.php" class="brand"><img src="<?= $base_path ?>/assets/poltesa_logo.png" alt="Logo" style="height:28px;width:28px;border-radius:50%;object-fit:cover;vertical-align:middle;margin-right:6px">PINMES</a>

  <!-- NAV -->
  <nav>
    <a href="<?= $base_path ?>/views/home.php"
       class="<?= $current==='home.php'?'active-nav':'' ?>">🏠 Home</a>

    <a href="<?= $base_path ?>/views/dashboard.php"
       class="<?= $current==='dashboard.php'?'active-nav':'' ?>">📦 Stok</a>

    <?php if(!$is_guest && in_array(($user['role'] ?? ''), ['mahasiswa', 'penanggung jawab'])): ?>
    <a href="<?= $base_path ?>/views/borrow.php"
       class="<?= $current==='borrow.php'?'active-nav':'' ?>">🔄 Peminjaman</a>
    <?php endif; ?>

    <!-- DROPDOWN -->
    <div class="dropdown-wrap">
      <button class="dropdown-btn" onclick="toggleNavDropdown(event)" aria-label="Menu">☰</button>

      <div id="nav-dropdown" class="dropdown-menu-pinba">

        <?php if($is_guest): ?>
          <a href="<?= $base_path ?>/index.php">🔑 Login</a>
          <a href="<?= $base_path ?>/controller/register.php">📝 Daftar</a>

        <?php else: ?>
          <div class="dropdown-user-info">
            <strong><?= e($user['name'] ?? $user['email']) ?></strong>
            <span><?= e($user['role']) ?></span>
          </div>

          <a href="<?= $base_path ?>/views/profile.php"
             class="<?= $current==='profile.php'?'active-item':'' ?>">👤 Profil Saya</a>

          <?php
          $role = $user['role'] ?? '';
          if(in_array($role, ['admin','kepala','kepala_bengkel','penanggung jawab'])):
          ?>
            <div class="dropdown-divider"></div>
            <?php if(in_array($role, ['kepala','kepala_bengkel','penanggung jawab'])): ?>
            <a href="<?= $base_path ?>/admin/items.php"
               class="<?= $current==='items.php'?'active-item':'' ?>">🛠️ Kelola Barang</a>
            <?php endif; ?>
            <?php if($role !== 'admin'): ?>
            <a href="<?= $base_path ?>/controller/export_pdf.php"
               class="<?= $current==='export_pdf.php'?'active-item':'' ?>">🖨️ Laporan Peminjaman</a>
            <?php endif; ?>
            <?php if(in_array($role, ['admin','kepala','kepala_bengkel','penanggung jawab'])): ?>
            <a href="<?= $base_path ?>/admin/pre_students.php"
               class="<?= $current==='pre_students.php'?'active-item':'' ?>">📋 Data Mahasiswa</a>
            <?php endif; ?>
            <?php if(in_array($role, ['admin','kepala','kepala_bengkel','penanggung jawab'])): ?>
            <a href="<?= $base_path ?>/admin/pre_lecturers.php"
               class="<?= $current==='pre_lecturers.php'?'active-item':'' ?>">👨‍🏫 Data Dosen</a>
            <?php endif; ?>
            <?php if($role !== 'penanggung jawab'): ?>
            <a href="<?= $base_path ?>/accountlist.php"
               class="<?= $current==='accountlist.php'?'active-item':'' ?>">📲 Daftar Akun</a>
            <?php endif; ?>
            <?php if($role === 'admin'): ?>
            <a href="<?= $base_path ?>/admin/approve_admins.php"
               class="<?= $current==='approve_admins.php'?'active-item':'' ?>">✅ Konfirmasi Admin</a>
            <?php endif; ?>
          <?php endif; ?>

          <div class="dropdown-divider"></div>
          <a href="<?= $base_path ?>/views/logout.php" class="danger">🚪 Logout</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>
</header>
