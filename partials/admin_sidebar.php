<aside class="dashboard-sidebar" data-sidebar>
  <div class="sidebar-title"><span class="eyebrow">RUANG PENGELOLA</span><strong><?= $user['role'] === 'super_admin' ? 'Super Admin' : 'Admin' ?></strong></div>
  <a class="sidebar-link <?= empty($_GET['view']) ? 'active' : '' ?>" href="<?= e(app_url('/admin/index.php')) ?>">◫ &nbsp; Ringkasan</a>
  <a class="sidebar-link" href="<?= e(app_url('/#projects')) ?>">▤ &nbsp; Kelola karya</a>
  <?php if ($user['role'] === 'admin'): ?><a class="sidebar-link <?= ($_GET['view'] ?? '') === 'students' ? 'active' : '' ?>" href="<?= e(app_url('/admin/index.php?view=students')) ?>">♙ &nbsp; Data siswa</a><?php endif; ?>
  <a class="sidebar-link" href="<?= e(app_url('/project_tambah.php')) ?>">＋ &nbsp; Tambah karya</a>
  <?php if ($user['role'] === 'super_admin'): ?><a class="sidebar-link" href="<?= e(app_url('/admin/users.php')) ?>">♙ &nbsp; Kelola pengguna</a><a class="sidebar-link" href="<?= e(app_url('/admin/categories.php')) ?>">▦ &nbsp; Kelola kategori</a><?php endif; ?>
  <a class="sidebar-link" href="<?= e(app_url('/profile.php')) ?>">◉ &nbsp; Profil saya</a>
</aside>
