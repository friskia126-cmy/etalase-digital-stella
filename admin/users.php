<?php
declare(strict_types=1);
require __DIR__ . '/../middleware/auth_check.php';
$user = require_roles('super_admin');
$pageTitle = 'Kelola Pengguna';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $targetId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    if ($action === 'create') {
        $nama = trim((string)($_POST['nama'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $role = (string)($_POST['role'] ?? '');
        if (mb_strlen($nama) < 2 || mb_strlen($nama) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            strlen($email) > 190 || strlen($password) < 12 || strlen($password) > 72 ||
            !in_array($role, ['siswa', 'admin'], true)) {
            $error = 'Data tidak valid. Password minimal 12 karakter dan role harus admin atau siswa.';
        } else {
            try {
                $statement = $pdo->prepare('INSERT INTO users (nama, email, password, role) VALUES (?, ?, ?, ?)');
                $statement->execute([$nama, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
                flash('success', 'Pengguna berhasil ditambahkan.');
                redirect('/admin/users.php');
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    $error = 'Email tersebut sudah terdaftar.';
                } else {
                    throw $exception;
                }
            }
        }
    } elseif (!$targetId || $targetId < 1) {
        $error = 'Pengguna tidak valid.';
    } elseif ($targetId === (int)$user['id']) {
        $error = 'Akun Super Admin yang sedang digunakan tidak dapat diubah atau dihapus.';
    } elseif ($action === 'update') {
        $nama = trim((string)($_POST['nama'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $kelas = trim((string)($_POST['kelas'] ?? ''));
        $jurusan = trim((string)($_POST['jurusan'] ?? ''));
        if (mb_strlen($nama) < 2 || mb_strlen($nama) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            strlen($email) > 190 || mb_strlen($kelas) > 80 || mb_strlen($jurusan) > 120) {
            $error = 'Data nama, email, kelas, atau jurusan tidak valid.';
        } else {
            try {
                $statement = $pdo->prepare(
                    'UPDATE users SET nama = ?, email = ?, kelas = ?, jurusan = ? WHERE id = ?'
                );
                $statement->execute([$nama, $email, $kelas ?: null, $jurusan ?: null, $targetId]);
                flash('success', 'Data pengguna berhasil diperbarui.');
                redirect('/admin/users.php');
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    $error = 'Email tersebut sudah digunakan akun lain.';
                } else {
                    throw $exception;
                }
            }
        }
    } elseif ($action === 'role') {
        $role = (string)($_POST['role'] ?? '');
        if (!in_array($role, ['super_admin', 'admin', 'siswa'], true)) {
            $error = 'Role yang dipilih tidak valid.';
        } else {
            $targetStatement = $pdo->prepare('SELECT id, role FROM users WHERE id = ? LIMIT 1');
            $targetStatement->execute([$targetId]);
            $target = $targetStatement->fetch();
            if (!$target) {
                $error = 'Pengguna tidak ditemukan.';
            } elseif ($target['role'] === 'super_admin' && $role !== 'super_admin') {
                $superAdminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'super_admin'")->fetchColumn();
                if ($superAdminCount <= 1) {
                    $error = 'Role Super Admin terakhir tidak dapat dicabut.';
                }
            }
            if ($error === '') {
                $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $targetId]);
                flash('success', 'Role pengguna berhasil diperbarui.');
                redirect('/admin/users.php');
            }
        }
    } elseif ($action === 'delete') {
        $targetStatement = $pdo->prepare('SELECT id, role, foto_profil FROM users WHERE id = ? LIMIT 1');
        $targetStatement->execute([$targetId]);
        $target = $targetStatement->fetch();
        if (!$target) {
            $error = 'Pengguna tidak ditemukan.';
        } elseif ($target['role'] === 'super_admin' &&
            (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'super_admin'")->fetchColumn() <= 1) {
            $error = 'Super Admin terakhir tidak dapat dihapus.';
        } else {
            $imagesStatement = $pdo->prepare('SELECT gambar FROM projects WHERE user_id = ?');
            $imagesStatement->execute([$targetId]);
            $images = array_filter(array_column($imagesStatement->fetchAll(), 'gambar'));
            if ($target['foto_profil']) {
                $images[] = $target['foto_profil'];
            }
            $pdo->beginTransaction();
            try {
                $pdo->prepare('DELETE FROM projects WHERE user_id = ?')->execute([$targetId]);
                $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$targetId]);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
            $failedDelete = false;
            foreach ($images as $image) {
                try {
                    remove_project_image($image);
                } catch (RuntimeException $exception) {
                    error_log($exception->getMessage());
                    $failedDelete = true;
                }
            }
            flash($failedDelete ? 'error' : 'success', $failedDelete
                ? 'Akun dan karya terkait dihapus, tetapi sebagian file gambar tidak dapat dihapus.'
                : 'Pengguna dan karya terkait berhasil dihapus.');
            redirect('/admin/users.php');
        }
    } else {
        $error = 'Aksi tidak dikenali.';
    }
}

$users = $pdo->query(
    'SELECT u.id, u.nama, u.email, u.role, u.kelas, u.jurusan, COUNT(p.id) AS project_count
     FROM users u LEFT JOIN projects p ON p.user_id = u.id
     GROUP BY u.id ORDER BY u.created_at DESC'
)->fetchAll();
require __DIR__ . '/../partials/header.php';
?>
<section class="dashboard-shell container"><?php require __DIR__ . '/../partials/admin_sidebar.php'; ?>
  <div class="dashboard-content"><button class="sidebar-toggle" type="button" data-sidebar-toggle aria-expanded="false">☰ Menu</button><div class="dashboard-heading"><div><span class="eyebrow">SUPER ADMIN</span><h1>Kelola pengguna</h1><p>Buat akun pengelola dan atur role akun.</p></div></div>
    <?php if ($error): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <section class="editor-card admin-create"><h2>Tambah pengguna</h2><form action="<?= e(app_url('/admin/users.php')) ?>" method="post" class="form-stack"><?= csrf_field() ?><input type="hidden" name="action" value="create"><div class="form-row"><label>Nama lengkap<input name="nama" required minlength="2" maxlength="120"></label><label>Email<input type="email" name="email" required maxlength="190"></label></div><div class="form-row"><label>Password sementara <small>Minimal 12 karakter</small><input type="password" name="password" required minlength="12" maxlength="72" autocomplete="new-password"></label><label>Role<select name="role" required><option value="siswa">Siswa</option><option value="admin">Admin</option></select></label></div><button class="button" type="submit">Buat akun</button></form></section>
    <section class="table-panel"><div class="panel-heading"><div><span class="eyebrow">AKUN TERDAFTAR</span><h2>Daftar pengguna</h2></div><span class="table-count"><?= count($users) ?> akun</span></div><div class="table-scroll"><table><thead><tr><th>Nama / email</th><th>Role</th><th>Kelas / jurusan</th><th>Karya</th><th>Aksi</th></tr></thead><tbody><?php foreach ($users as $account): ?><tr><td><strong><?= e($account['nama']) ?></strong><small class="table-subtext"><?= e($account['email']) ?></small></td><td><span class="role-badge"><?= e(str_replace('_', ' ', $account['role'])) ?></span></td><td><?= e($account['kelas'] ?: '—') ?><small class="table-subtext"><?= e($account['jurusan']) ?></small></td><td><?= (int)$account['project_count'] ?></td><td><?php if ((int)$account['id'] !== (int)$user['id']): ?><div class="table-actions"><details class="user-edit-details"><summary class="table-action">Edit data</summary><form class="user-edit-form" action="<?= e(app_url('/admin/users.php')) ?>" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="user_id" value="<?= (int)$account['id'] ?>"><label>Nama<input type="text" name="nama" value="<?= e($account['nama']) ?>" required minlength="2" maxlength="120"></label><label>Email<input type="email" name="email" value="<?= e($account['email']) ?>" required maxlength="190"></label><label>Kelas<input type="text" name="kelas" value="<?= e($account['kelas']) ?>" maxlength="80"></label><label>Jurusan<input type="text" name="jurusan" value="<?= e($account['jurusan']) ?>" maxlength="120"></label><button class="table-action" type="submit">Simpan data</button></form></details><form action="<?= e(app_url('/admin/users.php')) ?>" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="user_id" value="<?= (int)$account['id'] ?>"><select name="role" aria-label="Ubah role pengguna"><option value="siswa" <?= $account['role'] === 'siswa' ? 'selected' : '' ?>>Siswa</option><option value="admin" <?= $account['role'] === 'admin' ? 'selected' : '' ?>>Admin</option><option value="super_admin" <?= $account['role'] === 'super_admin' ? 'selected' : '' ?>>Super Admin</option></select><button class="table-action" type="submit">Simpan role</button></form><form action="<?= e(app_url('/admin/users.php')) ?>" method="post" data-confirm="Hapus akun ini dan seluruh karya miliknya?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?= (int)$account['id'] ?>"><button class="table-action danger-text" type="submit">Hapus</button></form></div><?php else: ?><span class="muted">Akun Anda</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></section>
  </div>
</section>
<?php require __DIR__ . '/../partials/footer.php'; ?>
