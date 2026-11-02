<?php
declare(strict_types=1);
require __DIR__ . '/middleware/auth_check.php';

$pageTitle = 'Beranda';
$search = trim(substr((string)($_GET['q'] ?? ''), 0, 100));
$categoryId = filter_var($_GET['category'] ?? '', FILTER_VALIDATE_INT);
$classFilter = trim(substr((string)($_GET['kelas'] ?? ''), 0, 80));
$page = max(1, (int)($_GET['page'] ?? 1));
$pageSize = 9;
$conditions = [];
$params = [];
if ($search !== '') {
    $conditions[] = '(p.nama_project LIKE ? OR u.nama LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($categoryId !== false && $categoryId !== null && $categoryId > 0) {
    $conditions[] = 'p.category_id = ?';
    $params[] = $categoryId;
} else {
    $categoryId = '';
}
if ($classFilter !== '') {
    $conditions[] = 'COALESCE(NULLIF(p.kelas, \'\'), u.kelas) = ?';
    $params[] = $classFilter;
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$countStatement = $pdo->prepare(
    "SELECT COUNT(*) FROM projects p JOIN users u ON u.id = p.user_id $where"
);
$countStatement->execute($params);
$totalProjects = (int)$countStatement->fetchColumn();
$pageCount = max(1, (int)ceil($totalProjects / $pageSize));
$page = min($page, $pageCount);
$statement = $pdo->prepare(
    "SELECT p.id, p.user_id, p.nama_project, p.deskripsi, p.gambar, p.created_at,
            p.kelas, p.jurusan, u.nama AS author_name, u.kelas AS author_class,
            c.nama AS category_name
     FROM projects p
     JOIN users u ON u.id = p.user_id
     LEFT JOIN categories c ON c.id = p.category_id
     $where ORDER BY p.created_at DESC LIMIT ? OFFSET ?"
);
$position = 1;
foreach ($params as $param) {
    $statement->bindValue($position++, $param, PDO::PARAM_STR);
}
$statement->bindValue($position++, $pageSize, PDO::PARAM_INT);
$statement->bindValue($position, ($page - 1) * $pageSize, PDO::PARAM_INT);
$statement->execute();
$projectRows = $statement->fetchAll();
$categories = $pdo->query(
    'SELECT c.id, c.nama, COUNT(p.id) AS project_count
     FROM categories c LEFT JOIN projects p ON p.category_id = c.id
     GROUP BY c.id ORDER BY c.nama'
)->fetchAll();
$stats = [
    'projects' => (int)$pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn(),
    'students' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'siswa'")->fetchColumn(),
    'categories' => count($categories),
];
$classes = $pdo->query(
    "SELECT DISTINCT kelas FROM users WHERE kelas IS NOT NULL AND kelas <> '' ORDER BY kelas"
)->fetchAll(PDO::FETCH_COLUMN);
$pageCount = max(1, (int)ceil($totalProjects / $pageSize));
require __DIR__ . '/partials/header.php';
?>
<section class="hero">
  <div class="container hero-grid">
    <div class="hero-copy">
      <span class="eyebrow"><span class="eyebrow-dot"></span> DIGITAL STUDENT PROJECT SHOWCASE</span>
      <h1>Wadah Kreativitas dan <span>Inovasi Digital</span> Siswa</h1>
      <p>Temukan, jelajahi, dan apresiasi berbagai karya digital hasil kreativitas siswa SMK Telkom Lampung.</p>
      <div class="hero-actions"><a class="button" href="#projects">Jelajahi Karya <span aria-hidden="true">→</span></a>
        <?php if ($user): ?><a class="button button-outline" href="<?= e(app_url('/project_tambah.php')) ?>">Bagikan Karya</a>
        <?php else: ?><a class="button button-outline" href="<?= e(app_url('/auth/register.php')) ?>">Mulai Berkarya</a><?php endif; ?>
      </div>
      <div class="hero-note"><span class="note-mark">✦</span> Showcase Your Creativity, Share Your Innovation</div>
    </div>
    <div class="hero-visual">
      <div class="visual-orbit orbit-one"></div><div class="visual-orbit orbit-two"></div>
      <div class="visual-logo"><img src="<?= e(app_url('/public/images/logo.png')) ?>" alt="Logo Stella"></div>
      <div class="visual-card visual-card-top"><span class="mini-mark">&lt;/&gt;</span><span><b>Ide jadi karya</b><small>Belajar dengan membuat</small></span></div>
      <div class="visual-card visual-card-bottom"><span class="status-dot"></span><span><b>Portofolio siswa</b><small>Proses dan inovasi nyata</small></span></div>
      <span class="visual-spark spark-one">✳</span><span class="visual-spark spark-two">+</span>
    </div>
  </div>
</section>
<section class="stats-strip"><div class="container stats-grid">
  <div class="stat"><strong><?= $stats['projects'] ?></strong><span>Total proyek</span></div>
  <div class="stat"><strong><?= $stats['students'] ?></strong><span>Siswa terdaftar</span></div>
  <div class="stat"><strong><?= $stats['categories'] ?></strong><span>Kategori karya</span></div>
  <div class="stat"><strong><?= $stats['projects'] ?></strong><span>Karya dipublikasikan</span></div>
</div></section>
<section class="section container" id="projects">
  <div class="section-heading"><div><span class="eyebrow">ETALASE KARYA</span><h2>Jelajahi ide yang jadi nyata</h2><p>Telusuri proyek digital para kreator muda Stella.</p></div>
    <?php if ($user): ?><a class="text-link" href="<?= e(app_url('/project_tambah.php')) ?>">+ Tambah karya</a><?php endif; ?>
  </div>
  <form class="filter-panel" action="<?= e(app_url('/')) ?>#projects" method="get">
    <label class="search-input"><span aria-hidden="true">⌕</span><input type="search" name="q" value="<?= e($search) ?>" placeholder="Cari nama karya atau siswa" aria-label="Cari karya atau siswa"></label>
    <label class="select-filter"><span class="sr-only">Kategori</span><select name="category"><option value="">Semua kategori</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= (string)$categoryId === (string)$category['id'] ? 'selected' : '' ?>><?= e($category['nama']) ?></option><?php endforeach; ?></select></label>
    <label class="select-filter"><span class="sr-only">Kelas</span><select name="kelas"><option value="">Semua kelas</option><?php foreach ($classes as $class): ?><option value="<?= e($class) ?>" <?= $classFilter === $class ? 'selected' : '' ?>><?= e($class) ?></option><?php endforeach; ?></select></label>
    <button class="button button-small" type="submit">Terapkan</button>
    <?php if ($search !== '' || $categoryId !== '' || $classFilter !== ''): ?><a class="clear-filter" href="<?= e(app_url('/#projects')) ?>">Hapus filter</a><?php endif; ?>
  </form>
  <div class="results-line"><span><strong><?= $totalProjects ?></strong> karya ditemukan</span><span>Halaman <?= $page ?> dari <?= $pageCount ?></span></div>
  <?php if ($projectRows): ?><div class="project-grid"><?php foreach ($projectRows as $project): render_project_card($project); endforeach; ?></div>
    <?php if ($pageCount > 1): ?><nav class="pagination" aria-label="Halaman etalase">
      <?php if ($page > 1): ?><a href="<?= e(app_url('/?' . http_build_query(['q' => $search, 'category' => $categoryId, 'kelas' => $classFilter, 'page' => $page - 1]) . '#projects')) ?>">← Sebelumnya</a><?php endif; ?>
      <span>Halaman <?= $page ?> dari <?= $pageCount ?></span>
      <?php if ($page < $pageCount): ?><a href="<?= e(app_url('/?' . http_build_query(['q' => $search, 'category' => $categoryId, 'kelas' => $classFilter, 'page' => $page + 1]) . '#projects')) ?>">Berikutnya →</a><?php endif; ?>
    </nav><?php endif; ?>
  <?php else: ?><div class="empty-state"><span class="empty-icon">✳</span><h3><?= $search || $categoryId || $classFilter ? 'Belum ada karya yang cocok' : 'Karya pertama segera hadir' ?></h3><p><?= $search || $categoryId || $classFilter ? 'Coba ubah kata pencarian atau filter kategori dan kelas.' : 'Belum ada proyek dipublikasikan. Jadilah yang pertama membagikan karya.' ?></p><?php if ($user): ?><a class="button" href="<?= e(app_url('/project_tambah.php')) ?>">Tambah karya</a><?php else: ?><a class="button" href="<?= e(app_url('/auth/register.php')) ?>">Daftar sebagai siswa</a><?php endif; ?></div><?php endif; ?>
</section>
<section class="category-section"><div class="container"><div class="section-heading"><div><span class="eyebrow">RUANG BERKREASI</span><h2>Beragam bidang, satu etalase</h2><p>Temukan karya dari berbagai disiplin teknologi dan desain.</p></div></div><div class="category-list"><?php foreach ($categories as $category): ?><a href="<?= e(app_url('/?category=' . (int)$category['id'] . '#projects')) ?>" class="category-chip"><span class="category-symbol">✳</span><span><b><?= e($category['nama']) ?></b><small><?= (int)$category['project_count'] ?> karya</small></span><span class="category-arrow">↗</span></a><?php endforeach; ?></div></div></section>
<section class="about-section container" id="about"><div class="about-mark"><img src="<?= e(app_url('/public/images/logo.png')) ?>" alt=""></div><div><span class="eyebrow">TENTANG PLATFORM</span><h2>Proses belajar yang layak untuk dibagikan.</h2><p>Etalase Digital Stella mendokumentasikan perjalanan ide menjadi proyek. Siswa dapat mempublikasikan karya, membangun portofolio, dan mendapatkan apresiasi. Guru serta pengelola dapat meninjau perkembangan proyek dalam satu ruang bersama.</p><a class="text-link" href="#projects">Jelajahi etalase <span>→</span></a></div></section>
<?php require __DIR__ . '/partials/footer.php'; ?>
