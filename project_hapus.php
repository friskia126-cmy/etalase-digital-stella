<?php
declare(strict_types=1);
require __DIR__ . '/middleware/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Metode tidak diizinkan.');
}
verify_csrf();
$projectId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if (!$projectId || $projectId < 1) {
    http_response_code(404);
    render_error_page('Karya tidak ditemukan', 'Karya ini tidak tersedia.');
}
$project = require_project_access($pdo, $projectId);
$statement = $pdo->prepare('DELETE FROM projects WHERE id = ?');
$statement->execute([$projectId]);
try {
    remove_project_image($project['gambar']);
    flash('success', 'Karya berhasil dihapus.');
} catch (RuntimeException $exception) {
    error_log($exception->getMessage());
    flash('error', 'Karya sudah dihapus, tetapi file gambarnya gagal dihapus.');
}
redirect('/');
