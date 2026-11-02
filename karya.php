<?php
declare(strict_types=1);
require __DIR__ . '/middleware/auth_check.php';

$pageTitle = 'Karya Digital';
$isKaryaPage = true;
$search = trim(substr((string)($_GET['q'] ?? ''), 0, 100));
$selectedGroup = strtolower(trim((string)($_GET['kategori'] ?? '')));
$supportedGroups = [
    'website' => ['website', 'web', 'website development', 'web development', 'aplikasi web', 'pemrograman web'],
    'aplikasi' => ['application', 'applications', 'aplikasi', 'aplikasi mobile', 'mobile application', 'mobile applications'],
    'desain' => ['design', 'desain', 'desain grafis', 'graphic design', 'ilustrasi digital', 'multimedia', 'ui/ux design', 'ui ux design'],
    'game' => ['game', 'games', 'game development'],
];
if (!isset($supportedGroups[$selectedGroup])) {
    $selectedGroup = '';
}

$categoryColumns = $pdo->query('SHOW COLUMNS FROM categories')->fetchAll(PDO::FETCH_COLUMN);
$categoryNameColumn = in_array('nama', $categoryColumns, true) ? 'nama' : 'name';
$groupExpression = in_array('filter_group', $categoryColumns, true)
    ? "COALESCE(filter_group, 'other')"
    : "'other'";
$categoryStatement = $pdo->query(
    'SELECT id, `' . $categoryNameColumn . '` AS category_name, ' . $groupExpression . ' AS filter_group
     FROM categories ORDER BY `' . $categoryNameColumn . '`'
);
$allCategories = $categoryStatement->fetchAll();
$categoryIdsByGroup = array_fill_keys(array_keys($supportedGroups), []);
$groupAliases = [
    'web' => 'website',
    'applications' => 'aplikasi',
    'application' => 'aplikasi',
    'design' => 'desain',
    'games' => 'game',
];
foreach ($allCategories as $category) {
    $group = strtolower(trim((string)$category['filter_group']));
    $name = mb_strtolower(trim((string)$category['category_name']));
    $normalizedGroup = $groupAliases[$group] ?? $group;
    foreach ($supportedGroups as $key => $aliases) {
        if ($normalizedGroup === $key || in_array($name, $aliases, true)) {
            $categoryIdsByGroup[$key][] = (int)$category['id'];
            break;
        }
    }
}
$categoryIdsByGroup = array_map(
    static fn(array $ids): array => array_values(array_unique($ids)),
    $categoryIdsByGroup
);
$availableGroups = array_filter(
    $categoryIdsByGroup,
    static fn(array $ids): bool => $ids !== []
);
if ($selectedGroup !== '' && !isset($availableGroups[$selectedGroup])) {
    $selectedGroup = '';
}

$page = max(1, (int)($_GET['page'] ?? 1));
$pageSize = 9;
$conditions = [];
$params = [];
if ($search !== '') {
    $conditions[] = '(p.nama_project LIKE ? OR u.nama LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($selectedGroup !== '') {
    $groupIds = $availableGroups[$selectedGroup];
    $conditions[] = 'p.category_id IN (' . implode(',', array_fill(0, count($groupIds), '?')) . ')';
    array_push($params, ...$groupIds);
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
            u.jurusan AS author_major, c.`$categoryNameColumn` AS category_name
     FROM projects p
     JOIN users u ON u.id = p.user_id
     LEFT JOIN categories c ON c.id = p.category_id
     $where ORDER BY p.created_at DESC LIMIT ? OFFSET ?"
);
$position = 1;
foreach ($params as $param) {
    $statement->bindValue($position++, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$statement->bindValue($position++, $pageSize, PDO::PARAM_INT);
$statement->bindValue($position, ($page - 1) * $pageSize, PDO::PARAM_INT);
$statement->execute();
$projectRows = $statement->fetchAll();
$user = current_user();
require __DIR__ . '/partials/header.php';
?>
<div class="karya-page">
  <section class="karya-hero">
    <div class="container karya-hero-inner">
      <div class="karya-hero-copy">
        <span class="karya-eyebrow"><span class="karya-eyebrow-dot"></span> GALERI STELLA <span class="karya-sparkle" aria-hidden="true">✿</span></span>
        <h1>Karya Digital</h1>
        <p>Jelajahi ide dan karya terbaik dari komunitas siswa Stella.</p>
        <?php if ($user): ?><a class="karya-add-button" href="<?= e(app_url('/project_tambah.php')) ?>"><span aria-hidden="true">+</span> Tambah karya</a><?php endif; ?>
      </div>
      <div class="karya-orbit-art" aria-hidden="true">
        <span class="karya-orbit karya-orbit-one"></span><span class="karya-orbit karya-orbit-two"></span>
        <span class="karya-orbit karya-orbit-three"></span><span class="karya-orbit-center"></span>
        <span class="karya-orbit-star karya-orbit-star-top">✦</span><span class="karya-orbit-star karya-orbit-star-bottom">✧</span>
      </div>
    </div>
  </section>

  <section class="container karya-content" aria-label="Galeri karya siswa">
    <form class="karya-search" action="<?= e(app_url('/karya.php')) ?>" method="get" role="search">
      <label class="karya-search-field">
        <span class="karya-search-icon" aria-hidden="true"></span>
        <span class="sr-only">Cari nama proyek atau siswa</span>
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Cari nama proyek atau siswa..." maxlength="100">
      </label>
      <?php if ($selectedGroup !== ''): ?><input type="hidden" name="kategori" value="<?= e($selectedGroup) ?>"><?php endif; ?>
      <button class="karya-search-button" type="submit">Cari Karya</button>
    </form>

    <nav class="karya-filters" aria-label="Filter kategori karya">
      <a class="karya-filter-pill <?= $selectedGroup === '' ? 'is-active' : '' ?>" href="<?= e(app_url('/karya.php' . ($search !== '' ? '?' . http_build_query(['q' => $search]) : ''))) ?>" <?= $selectedGroup === '' ? 'aria-current="page"' : '' ?>>Semua</a>
      <?php
      $filterLabels = ['website' => 'Website', 'aplikasi' => 'Aplikasi', 'desain' => 'Desain', 'game' => 'Game'];
      foreach ($filterLabels as $group => $label):
          if (!isset($availableGroups[$group])) {
              continue;
          }
          $query = ['kategori' => $group];
          if ($search !== '') {
              $query['q'] = $search;
          }
      ?>
        <a class="karya-filter-pill <?= $selectedGroup === $group ? 'is-active' : '' ?>" href="<?= e(app_url('/karya.php?' . http_build_query($query))) ?>" <?= $selectedGroup === $group ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="karya-collection-heading">
      <div><span class="karya-eyebrow">TEMUKAN INSPIRASI</span><h2>Koleksi Karya Siswa</h2></div>
      <span class="karya-result-count">Menampilkan <strong><?= $totalProjects ?></strong> hasil</span>
    </div>

    <?php if ($projectRows): ?>
      <div class="karya-grid">
        <?php foreach ($projectRows as $project): ?>
          <?php $detailUrl = app_url('/project_detail.php?id=' . (int)$project['id']); ?>
          <article class="karya-card">
            <a class="karya-card-image" href="<?= e($detailUrl) ?>" aria-label="Lihat detail <?= e($project['nama_project']) ?>">
              <?php if (!empty($project['gambar'])): ?>
                <img src="<?= e(project_image_url($project['gambar'])) ?>" alt="Tampilan proyek <?= e($project['nama_project']) ?>" loading="lazy">
              <?php else: ?>
                <span class="karya-image-empty">Karya Digital Stella</span>
              <?php endif; ?>
              <?php if (!empty($project['category_name'])): ?><span class="karya-card-category"><?= e($project['category_name']) ?></span><?php endif; ?>
            </a>
            <div class="karya-card-body">
              <h3><a href="<?= e($detailUrl) ?>"><?= e($project['nama_project']) ?></a></h3>
              <div class="karya-card-author"><span class="karya-author-mark"><?= e(mb_strtoupper(mb_substr($project['author_name'], 0, 1))) ?></span><span><strong><?= e($project['author_name']) ?></strong><small><?= e($project['kelas'] ?: ($project['author_class'] ?: 'Siswa Stella')) ?><?= ($project['jurusan'] ?: $project['author_major']) ? ' · ' . e($project['jurusan'] ?: $project['author_major']) : '' ?></small></span></div>
              <p><?= e(mb_substr((string)$project['deskripsi'], 0, 115)) ?><?= mb_strlen((string)$project['deskripsi']) > 115 ? '…' : '' ?></p>
              <a class="karya-detail-link" href="<?= e($detailUrl) ?>">Lihat Detail <span aria-hidden="true">↗</span></a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <?php if ($pageCount > 1): ?>
        <nav class="karya-pagination" aria-label="Halaman galeri karya">
          <?php if ($page > 1): ?><a href="<?= e(app_url('/karya.php?' . http_build_query(['q' => $search, 'kategori' => $selectedGroup, 'page' => $page - 1]))) ?>">← Sebelumnya</a><?php endif; ?>
          <span>Halaman <?= $page ?> dari <?= $pageCount ?></span>
          <?php if ($page < $pageCount): ?><a href="<?= e(app_url('/karya.php?' . http_build_query(['q' => $search, 'kategori' => $selectedGroup, 'page' => $page + 1]))) ?>">Berikutnya →</a><?php endif; ?>
        </nav>
      <?php endif; ?>
    <?php else: ?>
      <div class="karya-empty-state"><span class="karya-empty-symbol" aria-hidden="true">✳</span><h3><?= $search !== '' || $selectedGroup !== '' ? 'Belum ada karya yang cocok' : 'Karya pertama segera hadir' ?></h3><p><?= $search !== '' || $selectedGroup !== '' ? 'Coba ubah kata pencarian atau pilih kategori lain.' : 'Belum ada proyek yang dipublikasikan di galeri.' ?></p><?php if ($user): ?><a class="karya-add-button" href="<?= e(app_url('/project_tambah.php')) ?>">+ Tambah karya</a><?php endif; ?></div>
    <?php endif; ?>
  </section>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
