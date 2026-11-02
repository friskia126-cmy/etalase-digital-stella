<?php
declare(strict_types=1);
require __DIR__ . '/middleware/auth_check.php';
$projectId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$projectId || $projectId < 1) {
    http_response_code(404);
    render_error_page('Karya tidak ditemukan', 'Karya ini tidak tersedia.');
}
$project = require_project_access($pdo, $projectId);
$user = require_login();
$pageTitle = 'Edit Karya';
$error = '';
$categories = $pdo->query('SELECT id, nama FROM categories ORDER BY nama')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $values = [
        'nama_project' => trim((string)($_POST['nama_project'] ?? '')),
        'deskripsi' => trim((string)($_POST['deskripsi'] ?? '')),
        'category_id' => filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT) ?: '',
        'kelas' => $user['role'] === 'siswa' ? (string)($project['kelas'] ?? '') : trim((string)($_POST['kelas'] ?? '')),
        'jurusan' => $user['role'] === 'siswa' ? (string)($project['jurusan'] ?? '') : trim((string)($_POST['jurusan'] ?? '')),
        'link_project' => trim((string)($_POST['link_project'] ?? '')),
        'github_url' => trim((string)($_POST['github_url'] ?? '')),
    ];
    $categoryExists = false;
    foreach ($categories as $category) {
        if ((int)$category['id'] === (int)$values['category_id']) {
            $categoryExists = true;
            break;
        }
    }
    if (mb_strlen($values['nama_project']) < 3 || mb_strlen($values['nama_project']) > 160) {
        $error = 'Nama proyek harus berisi 3-160 karakter.';
    } elseif (mb_strlen($values['deskripsi']) < 20 || mb_strlen($values['deskripsi']) > 10000) {
        $error = 'Deskripsi proyek harus berisi 20-10.000 karakter.';
    } elseif (!$categoryExists) {
        $error = 'Pilih kategori proyek yang tersedia.';
    } elseif (mb_strlen($values['kelas']) > 80 || mb_strlen($values['jurusan']) > 120) {
        $error = 'Kelas atau jurusan melebihi panjang maksimum.';
    } elseif (!valid_http_url($values['link_project']) || !valid_http_url($values['github_url'])) {
        $error = 'Link harus menggunakan alamat HTTP atau HTTPS yang valid.';
    } else {
        $newImage = null;
        try {
            $newImage = handle_project_image('gambar');
            $statement = $pdo->prepare(
                'UPDATE projects
                 SET category_id = ?, nama_project = ?, deskripsi = ?, gambar = ?,
                     link_project = ?, github_url = ?, kelas = ?, jurusan = ?
                 WHERE id = ?'
            );
            $statement->execute([
                (int)$values['category_id'],
                $values['nama_project'],
                $values['deskripsi'],
                $newImage ?: $project['gambar'],
                $values['link_project'] ?: null,
                $values['github_url'] ?: null,
                $values['kelas'] ?: null,
                $values['jurusan'] ?: null,
                $projectId,
            ]);
            if ($newImage && $project['gambar']) {
                try {
                    remove_project_image($project['gambar']);
                } catch (RuntimeException $exception) {
                    error_log($exception->getMessage());
                    flash('error', 'Karya berhasil diperbarui, tetapi gambar lama tidak dapat dihapus.');
                    redirect('/project_detail.php?id=' . $projectId);
                }
            }
            flash('success', 'Perubahan karya berhasil disimpan.');
            redirect('/project_detail.php?id=' . $projectId);
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
            if ($newImage) {
                remove_project_image($newImage);
            }
            $project = array_merge($project, $values);
        } catch (Throwable $exception) {
            if ($newImage) {
                remove_project_image($newImage);
            }
            throw $exception;
        }
    }
}
$formAction = app_url('/project_edit.php?id=' . $projectId);
require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/project_form.php';
require __DIR__ . '/partials/footer.php';
