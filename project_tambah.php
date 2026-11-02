<?php
declare(strict_types=1);
require __DIR__ . '/middleware/auth_check.php';
$user = require_login();
$pageTitle = 'Tambah Karya';
$error = '';
$project = [
    'nama_project' => '',
    'deskripsi' => '',
    'category_id' => '',
    'kelas' => $user['kelas'] ?? '',
    'jurusan' => $user['jurusan'] ?? '',
    'link_project' => '',
    'github_url' => '',
];
$categories = $pdo->query('SELECT id, nama FROM categories ORDER BY nama')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $project['nama_project'] = trim((string)($_POST['nama_project'] ?? ''));
    $project['deskripsi'] = trim((string)($_POST['deskripsi'] ?? ''));
    $project['category_id'] = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT) ?: '';
    $project['link_project'] = trim((string)($_POST['link_project'] ?? ''));
    $project['github_url'] = trim((string)($_POST['github_url'] ?? ''));
    if ($user['role'] !== 'siswa') {
        $project['kelas'] = trim((string)($_POST['kelas'] ?? ''));
        $project['jurusan'] = trim((string)($_POST['jurusan'] ?? ''));
    }

    $categoryExists = false;
    foreach ($categories as $category) {
        if ((int)$category['id'] === (int)$project['category_id']) {
            $categoryExists = true;
            break;
        }
    }
    if (mb_strlen($project['nama_project']) < 3 || mb_strlen($project['nama_project']) > 160) {
        $error = 'Nama proyek harus berisi 3-160 karakter.';
    } elseif (mb_strlen($project['deskripsi']) < 20 || mb_strlen($project['deskripsi']) > 10000) {
        $error = 'Deskripsi proyek harus berisi 20-10.000 karakter.';
    } elseif (!$categoryExists) {
        $error = 'Pilih kategori proyek yang tersedia.';
    } elseif (mb_strlen($project['kelas']) > 80 || mb_strlen($project['jurusan']) > 120) {
        $error = 'Kelas atau jurusan melebihi panjang maksimum.';
    } elseif (!valid_http_url($project['link_project']) || !valid_http_url($project['github_url'])) {
        $error = 'Link harus menggunakan alamat HTTP atau HTTPS yang valid.';
    } else {
        $image = null;
        try {
            $image = handle_project_image('gambar', true);
            $statement = $pdo->prepare(
                'INSERT INTO projects (user_id, category_id, nama_project, deskripsi, gambar, link_project, github_url, kelas, jurusan)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([
                (int)$user['id'],
                (int)$project['category_id'],
                $project['nama_project'],
                $project['deskripsi'],
                $image,
                $project['link_project'] ?: null,
                $project['github_url'] ?: null,
                $user['role'] === 'siswa' ? ($user['kelas'] ?: null) : ($project['kelas'] ?: null),
                $user['role'] === 'siswa' ? ($user['jurusan'] ?: null) : ($project['jurusan'] ?: null),
            ]);
            flash('success', 'Karya berhasil dipublikasikan.');
            redirect('/project_detail.php?id=' . (int)$pdo->lastInsertId());
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            if ($image) {
                remove_project_image($image);
            }
            throw $exception;
        }
    }
}

$formAction = app_url('/project_tambah.php');
require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/project_form.php';
require __DIR__ . '/partials/footer.php';
