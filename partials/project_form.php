<section class="container form-page">
  <a class="back-link" href="<?= !empty($project['id']) ? e(app_url('/project_detail.php?id=' . (int)$project['id'])) : e(app_url('/#projects')) ?>">← Kembali</a>
  <div class="form-heading"><span class="eyebrow">DOKUMENTASIKAN KARYAMU</span><h1><?= e($pageTitle) ?></h1><p>Bagikan ide, proses, dan hasil proyek digitalmu kepada komunitas Stella.</p></div>
  <div class="editor-card">
    <?php if (!empty($error)): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form action="<?= e($formAction) ?>" method="post" enctype="multipart/form-data" class="form-stack">
      <?= csrf_field() ?>
      <label>Nama proyek<input type="text" name="nama_project" value="<?= e($project['nama_project'] ?? '') ?>" required minlength="3" maxlength="160" placeholder="Contoh: Aplikasi Kasir Digital"></label>
      <label>Kategori<select name="category_id" required><option value="">Pilih kategori</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= (string)($project['category_id'] ?? '') === (string)$category['id'] ? 'selected' : '' ?>><?= e($category['nama']) ?></option><?php endforeach; ?></select></label>
      <label>Deskripsi proyek<textarea name="deskripsi" rows="6" required minlength="20" maxlength="10000" placeholder="Ceritakan tujuan, fitur, dan proses pembuatan proyek..."><?= e($project['deskripsi'] ?? '') ?></textarea></label>
      <?php if ($user['role'] !== 'siswa'): ?><div class="form-row"><label>Kelas<input type="text" name="kelas" maxlength="80" value="<?= e($project['kelas'] ?? '') ?>"></label><label>Jurusan<input type="text" name="jurusan" maxlength="120" value="<?= e($project['jurusan'] ?? '') ?>"></label></div><?php endif; ?>
      <label>Gambar proyek <small>JPG, PNG, atau WEBP · maksimal 2 MB <?= !empty($project['id']) ? '· kosongkan jika tidak ingin mengganti' : '' ?></small><input type="file" name="gambar" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-image-input <?= empty($project['id']) ? 'required' : '' ?>></label>
      <div class="image-preview-wrap"><?php if (!empty($project['gambar'])): ?><img class="image-preview" src="<?= e(project_image_url($project['gambar'])) ?>" alt="Preview gambar proyek" data-image-preview><?php else: ?><img class="image-preview is-hidden" alt="Preview gambar proyek" data-image-preview><?php endif; ?><span class="preview-hint">Preview gambar akan muncul di sini</span></div>
      <div class="form-row"><label>Link demo <small>Opsional</small><input type="url" name="link_project" value="<?= e($project['link_project'] ?? '') ?>" placeholder="https://"></label><label>Link GitHub <small>Opsional</small><input type="url" name="github_url" value="<?= e($project['github_url'] ?? '') ?>" placeholder="https://github.com/..."></label></div>
      <div class="form-actions"><button class="button" type="submit"><?= !empty($project['id']) ? 'Simpan perubahan' : 'Publikasikan karya' ?> <span>→</span></button><a class="button button-outline" href="<?= !empty($project['id']) ? e(app_url('/project_detail.php?id=' . (int)$project['id'])) : e(app_url('/#projects')) ?>">Batal</a></div>
    </form>
  </div>
</section>
