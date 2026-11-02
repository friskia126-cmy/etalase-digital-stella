<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    $isSecure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('stella_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
header('X-Content-Type-Options: nosniff');
header_remove('X-Powered-By');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'");

$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$scriptDirectory = preg_replace('~/(auth|admin)$~', '', $scriptDirectory) ?? $scriptDirectory;
$baseUrl = rtrim($scriptDirectory, '/');
if ($baseUrl === '.' || $baseUrl === '/') {
    $baseUrl = '';
}

function app_url(string $path = ''): string
{
    global $baseUrl;
    return $baseUrl . ($path === '' ? '' : '/' . ltrim($path, '/'));
}

function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . app_url($path), true, 303);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($message) ? $message : null;
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $submitted = $_POST['_csrf'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        flash('error', 'Sesi formulir telah berubah atau kedaluwarsa. Silakan coba kembali dari halaman ini.');
        $requestPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        global $baseUrl;
        if ($baseUrl !== '' && str_starts_with($requestPath, $baseUrl . '/')) {
            $requestPath = substr($requestPath, strlen($baseUrl));
        }
        $query = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_QUERY) ?? '');
        redirect($requestPath . ($query !== '' ? '?' . $query : ''));
    }
}

function current_user(): ?array
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('error', 'Silakan masuk terlebih dahulu.');
        redirect('/auth/login.php');
    }
    return $user;
}

function require_roles(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'] ?? '', $roles, true)) {
        http_response_code(403);
        render_error_page('Akses ditolak', 'Anda tidak memiliki izin untuk mengakses halaman ini.');
    }
    return $user;
}

function refresh_authenticated_user(PDO $pdo): ?array
{
    $sessionUser = current_user();
    if (!$sessionUser) {
        return null;
    }

    $statement = $pdo->prepare(
        'SELECT id, nama, email, role, kelas, jurusan, foto_profil FROM users WHERE id = ? LIMIT 1'
    );
    $statement->execute([(int)$sessionUser['id']]);
    $user = $statement->fetch();
    if (!$user) {
        $_SESSION = [];
        session_regenerate_id(true);
        return null;
    }
    $_SESSION['user'] = $user;
    return $user;
}

function valid_http_url(string $value): bool
{
    if ($value === '') {
        return true;
    }
    if (strlen($value) > 2048 || filter_var($value, FILTER_VALIDATE_URL) === false) {
        return false;
    }
    return in_array(strtolower((string)parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true);
}

function project_image_url(?string $filename): string
{
    if (!$filename) {
        return '';
    }
    $safeName = basename(str_replace('\\', '/', $filename));
    if (!preg_match('/^[a-zA-Z0-9_-]{1,220}\.(?:jpg|jpeg|png|webp)$/i', $safeName)) {
        return '';
    }
    return app_url('/uploads/' . rawurlencode($safeName));
}

function render_error_page(string $title, string $message): never
{
    http_response_code(http_response_code() >= 400 ? http_response_code() : 400);
    require __DIR__ . '/../partials/header.php';
    echo '<section class="container error-page"><div class="error-mark">!</div><span class="eyebrow">ETALASE DIGITAL STELLA</span><h1>' . e($title) . '</h1><p>' . e($message) . '</p><a class="button" href="' . e(app_url('/')) . '">Kembali ke beranda</a></section>';
    require __DIR__ . '/../partials/footer.php';
    exit;
}

set_exception_handler(static function (Throwable $exception): void {
    error_log((string)$exception);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo '<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Terjadi kesalahan</title><body style="font:16px Arial,sans-serif;max-width:680px;margin:12vh auto;padding:24px;color:#171717"><h1>Terjadi kesalahan</h1><p>Permintaan belum dapat diproses. Silakan coba kembali.</p><a href="' . e(app_url('/')) . '">Kembali ke beranda</a></body></html>';
});

function require_project_access(PDO $pdo, int $projectId): array
{
    $user = require_login();
    $statement = $pdo->prepare(
        'SELECT p.*, u.nama AS author_name, u.email AS author_email,
                u.kelas AS author_class, u.jurusan AS author_major,
                c.nama AS category_name
         FROM projects p
         JOIN users u ON u.id = p.user_id
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.id = ? LIMIT 1'
    );
    $statement->execute([$projectId]);
    $project = $statement->fetch();
    if (!$project) {
        http_response_code(404);
        render_error_page('Karya tidak ditemukan', 'Karya ini tidak tersedia.');
    }
    if ($user['role'] === 'siswa' && (int)$project['user_id'] !== (int)$user['id']) {
        http_response_code(403);
        render_error_page('Akses ditolak', 'Anda hanya dapat mengelola karya milik sendiri.');
    }
    return $project;
}

function handle_project_image(string $field = 'gambar', bool $required = false): ?string
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            throw new InvalidArgumentException('Gambar proyek wajib diunggah.');
        }
        return null;
    }
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('Unggahan gambar gagal. Silakan coba kembali.');
    }
    if ($file['size'] < 1 || $file['size'] > 2 * 1024 * 1024) {
        throw new InvalidArgumentException('Ukuran gambar maksimal 2 MB.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $types = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $imageInfo = @getimagesize($file['tmp_name']);
    if (!isset($types[$mime]) || $imageInfo === false || $imageInfo['mime'] !== $mime) {
        throw new InvalidArgumentException('Gunakan gambar JPG, PNG, atau WEBP yang valid.');
    }

    $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder penyimpanan gambar tidak dapat dibuat.');
    }
    $filename = bin2hex(random_bytes(18)) . '.' . $types[$mime];
    if (!move_uploaded_file($file['tmp_name'], $directory . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('Gambar gagal disimpan. Periksa izin folder uploads.');
    }
    return $filename;
}

function remove_project_image(?string $filename): void
{
    if (!$filename) {
        return;
    }
    $safeName = basename(str_replace('\\', '/', $filename));
    if (!preg_match('/^[a-zA-Z0-9_-]{1,220}\.(?:jpg|jpeg|png|webp)$/i', $safeName)) {
        return;
    }
    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $safeName;
    if (!is_link($path) && is_file($path) && !unlink($path)) {
        error_log('Unable to remove uploaded image: ' . $path);
        throw new RuntimeException('Data tersimpan, tetapi file gambar lama tidak dapat dihapus.');
    }
}

function render_project_card(array $project): void
{
    $projectUrl = app_url('/project_detail.php?id=' . (int)$project['id']);
    echo '<article class="project-card"><a class="project-image" href="' . e($projectUrl) . '">';
    if (!empty($project['gambar'])) {
        echo '<img src="' . e(project_image_url($project['gambar'])) . '" alt="Tampilan ' . e($project['nama_project']) . '" loading="lazy">';
    } else {
        echo '<div class="image-placeholder"><span>STELLA / KARYA SISWA</span></div>';
    }
    echo '<span class="project-category">' . e($project['category_name'] ?: 'Karya Siswa') . '</span></a>';
    echo '<div class="project-card-body"><h2><a href="' . e($projectUrl) . '">' . e($project['nama_project']) . '</a></h2>';
    $description = (string)$project['deskripsi'];
    echo '<p>' . e(mb_substr($description, 0, 130)) . (mb_strlen($description) > 130 ? '…' : '') . '</p>';
    echo '<div class="project-byline"><span>' . e($project['author_name']) . '</span><span>' . e($project['kelas'] ?: ($project['author_class'] ?: 'Siswa Stella')) . '</span></div>';
    echo '<div class="project-meta"><time>' . e(date('d M Y', strtotime((string)$project['created_at']))) . '</time><a href="' . e($projectUrl) . '">Lihat detail ↗</a></div></div></article>';
}

$pdo = require __DIR__ . '/../config/kuis_db.php';
$authenticatedUser = refresh_authenticated_user($pdo);
$user = $authenticatedUser;
