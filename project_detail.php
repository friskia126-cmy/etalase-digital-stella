<?php
declare(strict_types=1);
require __DIR__ . '/middleware/auth_check.php';

$projectId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$projectId || $projectId < 1) {
    http_response_code(404);
    render_error_page('Karya tidak ditemukan', 'Karya ini tidak tersedia.');
}
$statement = $pdo->prepare(
    'SELECT p.*, u.nama AS author_name, u.kelas AS author_class,
            u.jurusan AS author_major, c.nama AS category_name
     FROM projects p JOIN users u ON u.id = p.user_id
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.id = ? LIMIT 1'
);
$statement->execute([$projectId]);
$project = $statement->fetch();
if (!$project) {
    http_response_code(404);
    render_error_page('Karya tidak ditemukan', 'Karya ini tidak tersedia.');
}
$user = current_user();
$canEdit = $user && ($user['role'] !== 'siswa' || (int)$user['id'] === (int)$project['user_id']);
$pageTitle = $project['nama_project'];
$isKaryaPage = true;
require __DIR__ . '/partials/header.php';
?>
<section class="container detail-page karya-detail-page">
  <a class="back-link" href="<?= e(app_url('/karya.php')) ?>">← Kembali ke koleksi</a>
  <div class="detail-grid"><div class="detail-main">
    <?php if ($project['gambar']): ?><div class="detail-image"><img src="<?= e(project_image_url($project['gambar'])) ?>" alt="Tampilan proyek <?= e($project['nama_project']) ?>"></div><?php else: ?><div class="detail-image image-placeholder"><span>STELLA / KARYA SISWA</span></div><?php endif; ?>
    <div class="detail-content"><span class="project-category static"><?= e($project['category_name'] ?: 'Karya Siswa') ?></span><h1><?= e($project['nama_project']) ?></h1>
      <p class="detail-author">Karya <strong><?= e($project['author_name']) ?></strong><span>·</span><?= e($project['kelas'] ?: ($project['author_class'] ?: 'Siswa Stella')) ?><?= ($project['jurusan'] ?: $project['author_major']) ? ' · ' . e($project['jurusan'] ?: $project['author_major']) : '' ?></p>
      <div class="detail-description"><?= e($project['deskripsi']) ?></div>
      <?php if ($canEdit): ?><div class="detail-actions"><a class="button button-outline" href="<?= e(app_url('/project_edit.php?id=' . (int)$project['id'])) ?>">Edit karya</a><form action="<?= e(app_url('/project_hapus.php')) ?>" method="post" data-confirm="Hapus karya ini secara permanen?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$project['id'] ?>"><button class="button button-danger" type="submit">Hapus karya</button></form></div><?php endif; ?>
    </div>
  </div>
  <aside class="detail-sidebar"><span class="eyebrow">INFORMASI PROYEK</span><dl class="detail-facts"><div><dt>Dipublikasikan</dt><dd><?= e(date('d F Y', strtotime((string)$project['created_at']))) ?></dd></div><div><dt>Kategori</dt><dd><?= e($project['category_name'] ?: 'Karya Siswa') ?></dd></div><div><dt>Kelas</dt><dd><?= e($project['kelas'] ?: ($project['author_class'] ?: '—')) ?></dd></div><div><dt>Jurusan</dt><dd><?= e($project['jurusan'] ?: ($project['author_major'] ?: '—')) ?></dd></div></dl>
    <?php if ($project['link_project'] || $project['github_url']): ?><div class="detail-links"><?php if ($project['link_project']): ?><a class="button button-full" href="<?= e($project['link_project']) ?>" target="_blank" rel="noopener noreferrer">Lihat demo ↗</a><?php endif; ?><?php if ($project['github_url']): ?><a class="button button-outline button-full" href="<?= e($project['github_url']) ?>" target="_blank" rel="noopener noreferrer">Lihat GitHub ↗</a><?php endif; ?></div><?php endif; ?>
  </aside></div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
