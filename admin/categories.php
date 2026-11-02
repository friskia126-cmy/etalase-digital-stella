<?php
declare(strict_types=1);
require __DIR__ . '/../middleware/auth_check.php';
$user = require_roles('super_admin');
$pageTitle = 'Kelola Kategori';
$error = '';
$categoryColumns = $pdo->query('SHOW COLUMNS FROM categories')->fetchAll(PDO::FETCH_COLUMN);
$legacyCategoryName = in_array('name', $categoryColumns, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $categoryId = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT);
    $name = trim((string)($_POST['nama'] ?? ''));
    if ($action === 'create' || $action === 'rename') {
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $error = 'Nama kategori harus berisi 2-100 karakter.';
        } else {
            try {
                if ($action === 'create') {
                    if ($legacyCategoryName) {
                        $pdo->prepare('INSERT INTO categories (nama, name) VALUES (?, ?)')->execute([$name, $name]);
                    } else {
                        $pdo->prepare('INSERT INTO categories (nama) VALUES (?)')->execute([$name]);
                    }
                } elseif (!$categoryId || $categoryId < 1) {
                    $error = 'Kategori tidak valid.';
                } else {
                    $check = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
                    $check->execute([$categoryId]);
                    if (!$check->fetch()) {
                        $error = 'Kategori tidak ditemukan.';
                    } elseif ($legacyCategoryName) {
                        $pdo->prepare('UPDATE categories SET nama = ?, name = ? WHERE id = ?')->execute([$name, $name, $categoryId]);
                    } else {
                        $pdo->prepare('UPDATE categories SET nama = ? WHERE id = ?')->execute([$name, $categoryId]);
                    }
                }
                if ($error === '') {
                    flash('success', $action === 'create' ? 'Kategori berhasil ditambahkan.' : 'Kategori berhasil diperbarui.');
                    redirect('/admin/categories.php');
                }
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    $error = 'Nama kategori tersebut sudah digunakan.';
                } else {
                    throw $exception;
                }
            }
        }
    } elseif ($action === 'delete' && $categoryId && $categoryId > 0) {
        $count = $pdo->prepare('SELECT COUNT(*) FROM projects WHERE category_id = ?');
        $count->execute([$categoryId]);
        if ((int)$count->fetchColumn() > 0) {
            $error = 'Kategori yang masih digunakan oleh proyek tidak dapat dihapus.';
        } else {
            $statement = $pdo->prepare('DELETE FROM categories WHERE id = ?');
            $statement->execute([$categoryId]);
            if ($statement->rowCount() === 0) {
                $error = 'Kategori tidak ditemukan.';
            } else {
                flash('success', 'Kategori berhasil dihapus.');
                redirect('/admin/categories.php');
            }
        }
    } else {
        $error = 'Aksi atau kategori tidak valid.';
    }
}
$categories = $pdo->query(
    'SELECT c.id, c.nama, COUNT(p.id) AS project_count
     FROM categories c LEFT JOIN projects p ON p.category_id = c.id
     GROUP BY c.id ORDER BY c.nama'
)->fetchAll();
require __DIR__ . '/../partials/header.php';
?>
<section class="dashboard-shell container"><?php require __DIR__ . '/../partials/admin_sidebar.php'; ?>
  <div class="dashboard-content"><button class="sidebar-toggle" type="button" data-sidebar-toggle aria-expanded="false">☰ Menu</button><div class="dashboard-heading"><div><span class="eyebrow">SUPER ADMIN</span><h1>Kelola kategori</h1><p>Atur bidang karya yang tersedia di etalase.</p></div></div>
    <?php if ($error): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <section class="editor-card admin-create"><h2>Tambah kategori</h2><form action="<?= e(app_url('/admin/categories.php')) ?>" method="post" class="inline-create"><?= csrf_field() ?><input type="hidden" name="action" value="create"><label class="sr-only" for="category-name">Nama kategori</label><input id="category-name" type="text" name="nama" maxlength="100" minlength="2" placeholder="Nama kategori baru" required><button class="button" type="submit">Tambah kategori</button></form></section>
    <section class="table-panel"><div class="panel-heading"><div><span class="eyebrow">KATEGORI TERSEDIA</span><h2>Daftar kategori</h2></div><span class="table-count"><?= count($categories) ?> kategori</span></div><div class="table-scroll"><table><thead><tr><th>Nama kategori</th><th>Jumlah karya</th><th>Perbarui</th></tr></thead><tbody><?php foreach ($categories as $category): ?><tr><td><strong><?= e($category['nama']) ?></strong></td><td><?= (int)$category['project_count'] ?></td><td><div class="table-actions"><form action="<?= e(app_url('/admin/categories.php')) ?>" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="rename"><input type="hidden" name="category_id" value="<?= (int)$category['id'] ?>"><label class="sr-only" for="category-<?= (int)$category['id'] ?>">Nama kategori</label><input id="category-<?= (int)$category['id'] ?>" class="category-name-input" type="text" name="nama" value="<?= e($category['nama']) ?>" maxlength="100" minlength="2" required><button class="table-action" type="submit">Simpan</button></form><form action="<?= e(app_url('/admin/categories.php')) ?>" method="post" data-confirm="Hapus kategori ini? Kategori yang masih digunakan tidak dapat dihapus."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="category_id" value="<?= (int)$category['id'] ?>"><button class="table-action danger-text" type="submit">Hapus</button></form></div></td></tr><?php endforeach; ?></tbody></table></div></section>
  </div>
</section>
<?php require __DIR__ . '/../partials/footer.php'; ?>
