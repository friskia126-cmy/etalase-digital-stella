<?php
declare(strict_types=1);
require __DIR__ . '/middleware/auth_check.php';
$user = require_login();
$pageTitle = 'Profil Saya';
$isProfilePage = true;
$error = '';
$profileStatement = $pdo->prepare(
    'SELECT id, nama, email, role, kelas, jurusan, biodata, keahlian, minat,
            tujuan_pengembangan, github_url, portfolio_url, foto_profil, created_at
     FROM users WHERE id = ? LIMIT 1'
);
$profileStatement->execute([(int)$user['id']]);
$profile = $profileStatement->fetch();
if (!$profile) {
    $_SESSION = [];
    session_regenerate_id(true);
    redirect('/auth/login.php');
}

$splitProfileTags = static function (string $value): array {
    $values = preg_split('/[,\r\n]+/', $value) ?: [];
    $values = array_map(static fn(string $item): string => trim($item), $values);
    return array_values(array_unique(array_filter($values, static fn(string $item): bool => $item !== '')));
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'save');
    if ($action === 'remove_photo') {
        $oldPhoto = (string)($profile['foto_profil'] ?? '');
        $pdo->prepare('UPDATE users SET foto_profil = NULL WHERE id = ?')->execute([(int)$user['id']]);
        if ($oldPhoto !== '') {
            try {
                remove_project_image($oldPhoto);
                flash('success', 'Foto profil berhasil dihapus.');
            } catch (RuntimeException $exception) {
                error_log($exception->getMessage());
                flash('error', 'Foto profil sudah dihapus dari akun, tetapi file lama tidak dapat dibersihkan.');
            }
        } else {
            flash('success', 'Foto profil berhasil dihapus.');
        }
        redirect('/profile.php#edit-profile');
    }

    $values = [
        'nama' => trim((string)($_POST['nama'] ?? '')),
        'kelas' => trim((string)($_POST['kelas'] ?? '')),
        'jurusan' => trim((string)($_POST['jurusan'] ?? '')),
        'biodata' => trim((string)($_POST['biodata'] ?? '')),
        'keahlian' => implode(', ', $splitProfileTags((string)($_POST['keahlian'] ?? ''))),
        'minat' => implode(', ', $splitProfileTags((string)($_POST['minat'] ?? ''))),
        'tujuan_pengembangan' => trim((string)($_POST['tujuan_pengembangan'] ?? '')),
        'github_url' => trim((string)($_POST['github_url'] ?? '')),
        'portfolio_url' => trim((string)($_POST['portfolio_url'] ?? '')),
    ];
    if (mb_strlen($values['nama']) < 2 || mb_strlen($values['nama']) > 120 ||
        mb_strlen($values['kelas']) > 80 || mb_strlen($values['jurusan']) > 120 ||
        mb_strlen($values['biodata']) > 2000 || mb_strlen($values['keahlian']) > 1000 ||
        mb_strlen($values['minat']) > 500 || mb_strlen($values['tujuan_pengembangan']) > 1000 ||
        count($splitProfileTags($values['keahlian'])) > 20 || count($splitProfileTags($values['minat'])) > 12) {
        $error = 'Periksa kembali identitas, deskripsi, minat, keahlian, dan tujuan pengembangan.';
    } elseif (!valid_http_url($values['github_url']) || !valid_http_url($values['portfolio_url'])) {
        $error = 'Link GitHub dan portofolio harus menggunakan HTTP atau HTTPS.';
    } else {
        $newPhoto = null;
        try {
            $newPhoto = handle_project_image('foto');
            $statement = $pdo->prepare(
                'UPDATE users
                 SET nama = ?, kelas = ?, jurusan = ?, biodata = ?, keahlian = ?, minat = ?,
                     tujuan_pengembangan = ?, github_url = ?, portfolio_url = ?,
                     foto_profil = COALESCE(?, foto_profil)
                 WHERE id = ?'
            );
            $statement->execute([
                $values['nama'], $values['kelas'] ?: null, $values['jurusan'] ?: null,
                $values['biodata'] ?: null, $values['keahlian'] ?: null, $values['minat'] ?: null,
                $values['tujuan_pengembangan'] ?: null, $values['github_url'] ?: null,
                $values['portfolio_url'] ?: null, $newPhoto, (int)$user['id'],
            ]);
            if ($newPhoto && $profile['foto_profil']) {
                try {
                    remove_project_image($profile['foto_profil']);
                } catch (RuntimeException $exception) {
                    error_log($exception->getMessage());
                    flash('error', 'Profil berhasil diperbarui, tetapi foto lama tidak dapat dibersihkan.');
                    redirect('/profile.php');
                }
            }
            flash('success', 'Profil berhasil diperbarui.');
            redirect('/profile.php');
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
            $profile = array_merge($profile, $values);
            if ($newPhoto) {
                remove_project_image($newPhoto);
            }
        } catch (Throwable $exception) {
            if ($newPhoto) {
                remove_project_image($newPhoto);
            }
            throw $exception;
        }
    }
    $profile = array_merge($profile, $values);
}

$skills = $splitProfileTags((string)($profile['keahlian'] ?? ''));
$interests = $splitProfileTags((string)($profile['minat'] ?? ''));
$isStudent = $profile['role'] === 'siswa';
$projectCount = 0;
$recentProjectCount = 0;
$projectCategories = [];
$projects = [];
$selectedCategory = filter_input(INPUT_GET, 'kategori', FILTER_VALIDATE_INT);
$selectedCategory = $selectedCategory && $selectedCategory > 0 ? $selectedCategory : 0;

if ($isStudent) {
    $countStatement = $pdo->prepare('SELECT COUNT(*) FROM projects WHERE user_id = ?');
    $countStatement->execute([(int)$user['id']]);
    $projectCount = (int)$countStatement->fetchColumn();

    $recentStatement = $pdo->prepare(
        'SELECT COUNT(*) FROM projects
         WHERE user_id = ? AND created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 30 DAY)'
    );
    $recentStatement->execute([(int)$user['id']]);
    $recentProjectCount = (int)$recentStatement->fetchColumn();

    $categoryStatement = $pdo->prepare(
        'SELECT c.id, c.nama, COUNT(p.id) AS project_count
         FROM projects p JOIN categories c ON c.id = p.category_id
         WHERE p.user_id = ?
         GROUP BY c.id, c.nama ORDER BY c.nama'
    );
    $categoryStatement->execute([(int)$user['id']]);
    $projectCategories = $categoryStatement->fetchAll();
    $categoryIds = array_map(static fn(array $category): int => (int)$category['id'], $projectCategories);
    if ($selectedCategory && !in_array($selectedCategory, $categoryIds, true)) {
        $selectedCategory = 0;
    }

    $projectSql = 'SELECT p.id, p.nama_project, p.deskripsi, p.gambar, p.created_at,
                          c.nama AS category_name
                   FROM projects p JOIN categories c ON c.id = p.category_id
                   WHERE p.user_id = ?';
    $projectParams = [(int)$user['id']];
    if ($selectedCategory) {
        $projectSql .= ' AND p.category_id = ?';
        $projectParams[] = $selectedCategory;
    }
    $projectSql .= ' ORDER BY p.created_at DESC, p.id DESC';
    $projectStatement = $pdo->prepare($projectSql);
    $projectStatement->execute($projectParams);
    $projects = $projectStatement->fetchAll();
}

$userRoleLabel = ucfirst(str_replace('_', ' ', (string)$profile['role']));
require __DIR__ . '/partials/header.php';
?>
<div class="profile-page">
  <section class="profile-hero">
    <div class="container profile-hero-inner">
      <div class="profile-hero-main">
        <div class="profile-avatar-frame">
          <?php if (!empty($profile['foto_profil'])): ?>
            <button class="profile-avatar-button" type="button" data-profile-photo-open aria-label="Perbesar foto profil">
              <img src="<?= e(project_image_url($profile['foto_profil'])) ?>" alt="Foto profil <?= e($profile['nama']) ?>">
              <span aria-hidden="true">Perbesar</span>
            </button>
          <?php else: ?>
            <div class="profile-avatar-fallback" aria-label="Inisial <?= e($profile['nama']) ?>"><?= e(mb_strtoupper(mb_substr($profile['nama'], 0, 1))) ?></div>
          <?php endif; ?>
        </div>
        <div class="profile-hero-copy">
          <span class="profile-eyebrow"><span class="profile-eyebrow-mark" aria-hidden="true">✦</span> DIGITAL STUDENT PROFILE</span>
          <h1><?= e($profile['nama']) ?></h1>
          <div class="profile-identity-line">
            <?php if (!empty($profile['kelas'])): ?><span><?= e($profile['kelas']) ?></span><?php endif; ?>
            <?php if (!empty($profile['jurusan'])): ?><span><?= e($profile['jurusan']) ?></span><?php endif; ?>
            <span class="profile-role"><?= e($userRoleLabel) ?></span>
          </div>
          <p><?= e($profile['biodata'] ?: 'Ruang personal untuk memperkenalkan diri, kemampuan, minat, dan karya di Etalase Digital Stella.') ?></p>
          <div class="profile-hero-actions">
            <button class="profile-button-primary" type="button" data-profile-edit>✎ Edit Profil</button>
            <button class="profile-button-secondary" type="button" data-profile-preview>Pratinjau Profil</button>
            <?php if ($isStudent): ?><a class="profile-button-secondary" href="<?= e(app_url('/project_tambah.php')) ?>">+ Tambah Karya</a><?php endif; ?>
          </div>
        </div>
      </div>
      <div class="profile-hero-decoration" aria-hidden="true"><span class="profile-deco-ring"></span><span class="profile-deco-orbit"></span><span class="profile-deco-star">✧</span><span class="profile-deco-dot"></span></div>
    </div>
  </section>

  <div class="container profile-shell">
    <nav class="profile-tabs" aria-label="Navigasi profil">
      <a href="#overview">Ringkasan</a>
      <?php if ($isStudent): ?><a href="#skills">Keahlian & Minat</a><a href="#portfolio">Koleksi Karya</a><?php endif; ?>
      <a href="#edit-profile">Pengaturan Profil</a>
    </nav>

    <?php if ($isStudent): ?>
      <section class="profile-stats" aria-label="Statistik profil">
        <article class="profile-stat-card"><span class="profile-stat-icon" aria-hidden="true">▧</span><span>Total karya</span><strong><?= $projectCount ?></strong><small>Proyek yang kamu publikasikan</small></article>
        <article class="profile-stat-card"><span class="profile-stat-icon profile-stat-icon-pink" aria-hidden="true">◷</span><span>Karya terbaru</span><strong><?= $recentProjectCount ?></strong><small>Dipublikasikan dalam 30 hari terakhir</small></article>
        <article class="profile-stat-card"><span class="profile-stat-icon profile-stat-icon-lilac" aria-hidden="true">✳</span><span>Kategori karya</span><strong><?= count($projectCategories) ?></strong><small>Jenis kategori yang pernah dibuat</small></article>
        <article class="profile-stat-card"><span class="profile-stat-icon profile-stat-icon-peach" aria-hidden="true">⌂</span><span>Bergabung sejak</span><strong class="profile-stat-date"><?= e(date('M Y', strtotime((string)$profile['created_at']))) ?></strong><small>Menjadi bagian dari komunitas Stella</small></article>
      </section>
    <?php endif; ?>

    <section class="profile-overview" id="overview">
      <div class="profile-section-heading"><div><span class="profile-eyebrow">KENALI SAYA</span><h2>Tentang <?= e(explode(' ', trim((string)$profile['nama']))[0]) ?></h2></div><span class="profile-section-note">Profil ini hanya dapat dilihat oleh pemilik akun.</span></div>
      <div class="profile-overview-grid">
        <article class="profile-panel profile-about-panel">
          <span class="profile-panel-kicker">CERITA SINGKAT</span>
          <p><?= e($profile['biodata'] ?: 'Tambahkan cerita singkat tentang dirimu melalui tombol Edit Profil.') ?></p>
          <?php if (!empty($profile['tujuan_pengembangan'])): ?><div class="profile-goal"><span aria-hidden="true">↗</span><div><strong>Yang ingin saya kembangkan</strong><p><?= e($profile['tujuan_pengembangan']) ?></p></div></div><?php endif; ?>
        </article>
        <article class="profile-panel profile-info-panel">
          <span class="profile-panel-kicker">INFORMASI AKUN</span>
          <dl class="profile-facts">
            <div><dt>Nama lengkap</dt><dd><?= e($profile['nama']) ?></dd></div>
            <div><dt>Email akun</dt><dd><?= e($profile['email']) ?></dd></div>
            <div><dt>Kelas</dt><dd><?= e($profile['kelas'] ?: 'Belum diisi') ?></dd></div>
            <div><dt>Jurusan</dt><dd><?= e($profile['jurusan'] ?: 'Belum diisi') ?></dd></div>
          </dl>
          <div class="profile-social-links">
            <?php if (!empty($profile['github_url'])): ?><a href="<?= e($profile['github_url']) ?>" target="_blank" rel="noopener noreferrer">GitHub <span aria-hidden="true">↗</span></a><?php endif; ?>
            <?php if (!empty($profile['portfolio_url'])): ?><a href="<?= e($profile['portfolio_url']) ?>" target="_blank" rel="noopener noreferrer">Portofolio <span aria-hidden="true">↗</span></a><?php endif; ?>
            <?php if (empty($profile['github_url']) && empty($profile['portfolio_url'])): ?><span class="profile-muted">Tambahkan tautan profesional dari Edit Profil.</span><?php endif; ?>
          </div>
        </article>
      </div>
    </section>

    <?php if ($isStudent): ?>
      <section class="profile-skills-section" id="skills">
        <div class="profile-section-heading"><div><span class="profile-eyebrow">HAL YANG SAYA KUASAI</span><h2>Keahlian & Minat</h2></div></div>
        <div class="profile-skills-grid">
          <article class="profile-panel">
            <div class="profile-panel-heading"><span class="profile-panel-kicker">KEAHLIAN</span><span aria-hidden="true">✦</span></div>
            <?php if ($skills): ?><div class="profile-tags"><?php foreach ($skills as $skill): ?><span class="profile-tag"><?= e($skill) ?></span><?php endforeach; ?></div><?php else: ?><p class="profile-muted">Belum ada keahlian yang ditambahkan.</p><?php endif; ?>
          </article>
          <article class="profile-panel profile-interest-panel">
            <div class="profile-panel-heading"><span class="profile-panel-kicker">BIDANG YANG DIMINATI</span><span aria-hidden="true">✧</span></div>
            <?php if ($interests): ?><div class="profile-tags profile-interest-tags"><?php foreach ($interests as $interest): ?><span class="profile-tag"><?= e($interest) ?></span><?php endforeach; ?></div><?php else: ?><p class="profile-muted">Tambahkan bidang yang kamu minati melalui Edit Profil.</p><?php endif; ?>
          </article>
        </div>
      </section>

      <section class="profile-portfolio-section" id="portfolio">
        <div class="profile-section-heading profile-portfolio-heading">
          <div><span class="profile-eyebrow">DIBUAT DENGAN KREATIVITAS</span><h2>Koleksi Karya Saya</h2><p><?= $projectCount ?> <?= $projectCount === 1 ? 'karya' : 'karya' ?> tersimpan di akunmu</p></div>
          <a class="profile-button-primary" href="<?= e(app_url('/project_tambah.php')) ?>">+ Tambah Karya</a>
        </div>
        <form class="profile-project-filter" action="<?= e(app_url('/profile.php#portfolio')) ?>" method="get">
          <label for="profile-category-filter">Filter kategori</label>
          <select id="profile-category-filter" name="kategori" <?= !$projectCategories ? 'disabled' : '' ?>>
            <option value="0">Semua kategori</option>
            <?php foreach ($projectCategories as $category): ?>
              <option value="<?= (int)$category['id'] ?>" <?= $selectedCategory === (int)$category['id'] ? 'selected' : '' ?>><?= e($category['nama']) ?> (<?= (int)$category['project_count'] ?>)</option>
            <?php endforeach; ?>
          </select>
          <button type="submit">Terapkan</button>
          <span class="profile-project-result"><?= count($projects) ?> <?= count($projects) === 1 ? 'karya' : 'karya' ?> ditampilkan · terbaru lebih dulu</span>
        </form>

        <?php if ($projects): ?>
          <div class="profile-project-grid">
            <?php foreach ($projects as $project): ?>
              <?php $detailUrl = app_url('/project_detail.php?id=' . (int)$project['id']); ?>
              <article class="profile-project-card">
                <a class="profile-project-image" href="<?= e($detailUrl) ?>">
                  <?php if (!empty($project['gambar'])): ?><img src="<?= e(project_image_url($project['gambar'])) ?>" alt="Tampilan proyek <?= e($project['nama_project']) ?>" loading="lazy"><?php else: ?><span>Karya Digital Stella</span><?php endif; ?>
                  <span class="profile-project-category"><?= e($project['category_name']) ?></span>
                </a>
                <div class="profile-project-body">
                  <time datetime="<?= e(date('Y-m-d', strtotime((string)$project['created_at']))) ?>"><?= e(date('d M Y', strtotime((string)$project['created_at']))) ?></time>
                  <h3><a href="<?= e($detailUrl) ?>"><?= e($project['nama_project']) ?></a></h3>
                  <p><?= e(mb_substr((string)$project['deskripsi'], 0, 125)) ?><?= mb_strlen((string)$project['deskripsi']) > 125 ? '…' : '' ?></p>
                  <div class="profile-project-actions">
                    <a href="<?= e($detailUrl) ?>">Lihat Detail <span aria-hidden="true">↗</span></a>
                    <a href="<?= e(app_url('/project_edit.php?id=' . (int)$project['id'])) ?>">Edit</a>
                    <form action="<?= e(app_url('/project_hapus.php')) ?>" method="post" data-confirm="Hapus karya ini secara permanen?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$project['id'] ?>"><button type="submit">Hapus</button></form>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="profile-empty-state"><span aria-hidden="true">✳</span><h3><?= $projectCount ? 'Tidak ada karya pada kategori ini' : 'Perjalanan karyamu dimulai di sini' ?></h3><p><?= $projectCount ? 'Pilih kategori lain untuk melihat karya yang berbeda.' : 'Bagikan proyek digital pertamamu ke galeri Stella.' ?></p><?php if (!$projectCount): ?><a class="profile-button-primary" href="<?= e(app_url('/project_tambah.php')) ?>">+ Tambah Karya Pertama</a><?php endif; ?></div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <section class="profile-edit-section" id="edit-profile" data-profile-edit-section <?= $error === '' ? 'hidden' : '' ?>>
      <div class="profile-section-heading"><div><span class="profile-eyebrow">KENDALI DI TANGANMU</span><h2>Edit Profil</h2><p>Perbarui informasi personal, keahlian, minat, dan tautanmu.</p></div><button class="profile-button-secondary" type="button" data-profile-cancel>Batal</button></div>
      <?php if ($error): ?><div class="profile-form-error" role="alert"><?= e($error) ?></div><?php endif; ?>
      <form action="<?= e(app_url('/profile.php#edit-profile')) ?>" method="post" enctype="multipart/form-data" class="profile-edit-form" data-profile-form>
        <?= csrf_field() ?><input type="hidden" name="action" value="save">
        <div class="profile-form-grid">
          <label class="profile-form-field profile-form-photo">Foto profil <small>JPG, PNG, atau WEBP · maksimal 2 MB</small>
            <input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-profile-image-input>
            <span class="profile-photo-preview-wrap">
              <?php if (!empty($profile['foto_profil'])): ?><img class="profile-photo-preview" src="<?= e(project_image_url($profile['foto_profil'])) ?>" alt="Preview foto profil" data-image-preview><?php else: ?><img class="profile-photo-preview is-hidden" alt="Preview foto profil" data-image-preview><span class="profile-preview-placeholder">Preview foto muncul di sini</span><?php endif; ?>
            </span>
          </label>
          <div class="profile-form-identity">
            <label class="profile-form-field">Nama lengkap<input type="text" name="nama" value="<?= e($profile['nama']) ?>" required minlength="2" maxlength="120"></label>
            <label class="profile-form-field">Email akun <small>Email akun tidak dapat diubah dari halaman profil.</small><input type="email" value="<?= e($profile['email']) ?>" disabled></label>
            <div class="profile-form-row">
              <label class="profile-form-field">Kelas<input type="text" name="kelas" value="<?= e($profile['kelas']) ?>" maxlength="80" placeholder="Contoh: XI RPL 1"></label>
              <label class="profile-form-field">Jurusan<input type="text" name="jurusan" value="<?= e($profile['jurusan']) ?>" maxlength="120" placeholder="Contoh: Rekayasa Perangkat Lunak"></label>
            </div>
          </div>
          <label class="profile-form-field profile-form-full">Tentang saya<textarea name="biodata" rows="4" maxlength="2000" placeholder="Ceritakan tentang dirimu"><?= e($profile['biodata']) ?></textarea></label>
          <div class="profile-tag-editor profile-form-field" data-tag-editor data-field="keahlian">
            <label for="profile-skill-entry">Keahlian <small>Tekan Enter atau koma untuk menambahkan tag.</small></label>
            <div class="profile-tag-input"><div class="profile-edit-tags" data-tag-list><?php foreach ($skills as $skill): ?><span class="profile-edit-tag"><?= e($skill) ?><button type="button" data-tag-remove aria-label="Hapus <?= e($skill) ?>">×</button></span><?php endforeach; ?></div><input id="profile-skill-entry" type="text" data-tag-input placeholder="Contoh: HTML, UI/UX"></div>
            <input type="hidden" name="keahlian" data-tag-value value="<?= e(implode(', ', $skills)) ?>">
          </div>
          <div class="profile-tag-editor profile-form-field" data-tag-editor data-field="minat">
            <label for="profile-interest-entry">Minat & bidang kreativitas <small>Tekan Enter atau koma untuk menambahkan tag.</small></label>
            <div class="profile-tag-input"><div class="profile-edit-tags" data-tag-list><?php foreach ($interests as $interest): ?><span class="profile-edit-tag"><?= e($interest) ?><button type="button" data-tag-remove aria-label="Hapus <?= e($interest) ?>">×</button></span><?php endforeach; ?></div><input id="profile-interest-entry" type="text" data-tag-input placeholder="Contoh: Web Development"></div>
            <input type="hidden" name="minat" data-tag-value value="<?= e(implode(', ', $interests)) ?>">
          </div>
          <label class="profile-form-field profile-form-full">Tujuan atau bidang yang ingin dikembangkan<textarea name="tujuan_pengembangan" rows="3" maxlength="1000" placeholder="Ceritakan keterampilan yang ingin kamu kembangkan"><?= e($profile['tujuan_pengembangan']) ?></textarea></label>
          <label class="profile-form-field">GitHub<input type="url" name="github_url" value="<?= e($profile['github_url']) ?>" maxlength="2048" placeholder="https://github.com/..."></label>
          <label class="profile-form-field">Website portofolio<input type="url" name="portfolio_url" value="<?= e($profile['portfolio_url']) ?>" maxlength="2048" placeholder="https://..."></label>
        </div>
        <div class="profile-form-footer">
          <?php if (!empty($profile['foto_profil'])): ?><button class="profile-remove-photo" type="submit" name="action" value="remove_photo" formnovalidate data-confirm="Hapus foto profil dari akun ini?">Hapus foto profil</button><?php endif; ?>
          <button class="profile-button-primary profile-save-button" type="submit" data-profile-submit><span data-submit-label>Simpan Perubahan</span><span data-submit-loading hidden>Menyimpan…</span></button>
        </div>
      </form>
    </section>
  </div>

  <?php if (!empty($profile['foto_profil'])): ?>
    <dialog class="profile-photo-dialog" data-profile-photo-dialog aria-label="Foto profil berukuran besar">
      <button type="button" class="profile-dialog-close" data-profile-dialog-close aria-label="Tutup foto">×</button>
      <img src="<?= e(project_image_url($profile['foto_profil'])) ?>" alt="Foto profil <?= e($profile['nama']) ?>">
    </dialog>
  <?php endif; ?>
  <dialog class="profile-preview-dialog" data-profile-preview-dialog aria-labelledby="profile-preview-title">
    <button type="button" class="profile-dialog-close" data-profile-preview-close aria-label="Tutup pratinjau">×</button>
    <span class="profile-eyebrow">PRATINJAU DIGITAL PROFILE</span>
    <h2 id="profile-preview-title"><?= e($profile['nama']) ?></h2>
    <p><?= e($profile['kelas'] ?: $userRoleLabel) ?><?= $profile['jurusan'] ? ' · ' . e($profile['jurusan']) : '' ?></p>
    <div class="profile-preview-bio"><?= e($profile['biodata'] ?: 'Belum ada deskripsi diri.') ?></div>
    <?php if ($isStudent): ?><strong><?= $projectCount ?> karya</strong><?php endif; ?>
  </dialog>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
