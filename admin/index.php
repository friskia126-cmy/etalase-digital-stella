<?php
declare(strict_types=1);
require __DIR__ . '/../middleware/auth_check.php';
$user = require_roles('admin', 'super_admin');
$view = (string)($_GET['view'] ?? '');
$pageTitle = $view === 'students' ? 'Data Siswa' : 'Dashboard';

$stats = [
    'projects' => (int)$pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn(),
    'students' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'siswa'")->fetchColumn(),
    'users' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'categories' => (int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
    'admins' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
];

if ($view === 'students') {
    $user = require_roles('admin', 'super_admin');
    $students = $pdo->query(
        "SELECT u.id, u.nama, u.email, u.kelas, u.jurusan, COUNT(p.id) AS project_count
         FROM users u LEFT JOIN projects p ON p.user_id = u.id
         WHERE u.role = 'siswa' GROUP BY u.id ORDER BY u.nama"
    )->fetchAll();
    require __DIR__ . '/../partials/header.php';
    ?>
    <section class="dashboard-shell container">
      <?php require __DIR__ . '/../partials/admin_sidebar.php'; ?>
      <div class="dashboard-content"><button class="sidebar-toggle" type="button" data-sidebar-toggle aria-expanded="false">☰ Menu</button><div class="dashboard-heading"><div><span class="eyebrow">ADMIN</span><h1>Data siswa</h1><p>Informasi siswa dan jumlah karya untuk pemantauan proyek.</p></div></div>
        <section class="table-panel"><div class="panel-heading"><div><span class="eyebrow">SISWA TERDAFTAR</span><h2>Daftar siswa</h2></div><span class="table-count"><?= count($students) ?> siswa</span></div><div class="table-scroll"><table><thead><tr><th>Nama / email</th><th>Kelas</th><th>Jurusan</th><th>Jumlah karya</th></tr></thead><tbody><?php if ($students): foreach ($students as $student): ?><tr><td><strong><?= e($student['nama']) ?></strong><small class="table-subtext"><?= e($student['email']) ?></small></td><td><?= e($student['kelas'] ?: '—') ?></td><td><?= e($student['jurusan'] ?: '—') ?></td><td><?= (int)$student['project_count'] ?></td></tr><?php endforeach; else: ?><tr><td colspan="4" class="table-empty">Belum ada siswa yang terdaftar.</td></tr><?php endif; ?></tbody></table></div></section>
      </div>
    </section>
    <?php
    require __DIR__ . '/../partials/footer.php';
    exit;
}

$recent = $pdo->query(
    'SELECT p.id, p.nama_project, p.created_at, u.nama AS author_name, c.nama AS category_name
     FROM projects p JOIN users u ON u.id = p.user_id
     LEFT JOIN categories c ON c.id = p.category_id
     ORDER BY p.created_at DESC LIMIT 8'
)->fetchAll();
require __DIR__ . '/../partials/header.php';
?>
<section class="dashboard-shell container">
  <?php require __DIR__ . '/../partials/admin_sidebar.php'; ?>
  <div class="dashboard-content"><button class="sidebar-toggle" type="button" data-sidebar-toggle aria-expanded="false">☰ Menu</button><div class="dashboard-heading"><div><span class="eyebrow">OVERVIEW</span><h1>Selamat datang, <?= e(explode(' ', $user['nama'])[0]) ?></h1><p>Ringkasan aktivitas Etalase Digital Stella.</p></div><a class="button" href="<?= e(app_url('/#projects')) ?>">Lihat etalase <span>→</span></a></div>
    <div class="dashboard-stats"><div class="dashboard-stat"><span>Total proyek</span><strong><?= $stats['projects'] ?></strong><small>Proyek yang dipublikasikan</small></div><div class="dashboard-stat"><span>Total siswa</span><strong><?= $stats['students'] ?></strong><small>Kreator terdaftar</small></div><div class="dashboard-stat"><span>Total pengguna</span><strong><?= $stats['users'] ?></strong><small>Seluruh akun</small></div><div class="dashboard-stat"><span>Admin</span><strong><?= $stats['admins'] ?></strong><small>Pengelola akun</small></div><div class="dashboard-stat"><span>Kategori</span><strong><?= $stats['categories'] ?></strong><small>Bidang proyek</small></div></div>
    <section class="table-panel"><div class="panel-heading"><div><span class="eyebrow">AKTIVITAS</span><h2>Karya terbaru</h2></div><a class="text-link" href="<?= e(app_url('/#projects')) ?>">Buka etalase →</a></div><div class="table-scroll"><table><thead><tr><th>Nama karya</th><th>Pembuat</th><th>Kategori</th><th>Tanggal</th><th></th></tr></thead><tbody><?php if ($recent): foreach ($recent as $project): ?><tr><td><strong><?= e($project['nama_project']) ?></strong></td><td><?= e($project['author_name']) ?></td><td><?= e($project['category_name'] ?: '—') ?></td><td><?= e(date('d/m/Y', strtotime($project['created_at']))) ?></td><td><a class="table-action" href="<?= e(app_url('/project_detail.php?id=' . (int)$project['id'])) ?>">Detail ↗</a></td></tr><?php endforeach; else: ?><tr><td colspan="5" class="table-empty">Belum ada proyek yang dipublikasikan.</td></tr><?php endif; ?></tbody></table></div></section>
  </div>
</section>
<?php require __DIR__ . '/../partials/footer.php'; ?>
