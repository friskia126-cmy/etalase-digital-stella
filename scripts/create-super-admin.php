<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$pdo = require __DIR__ . '/../config/kuis_db.php';
$name = trim((string)readline('Nama Super Admin: '));
$email = strtolower(trim((string)readline('Email: ')));
$password = (string)readline('Password (minimal 12 karakter): ');
if (mb_strlen($name) < 2 || mb_strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    strlen($email) > 190 || strlen($password) < 12 || strlen($password) > 72) {
    fwrite(STDERR, "Input tidak valid; pastikan nama, email, dan panjang password sesuai.\n");
    exit(1);
}
$existing = $pdo->query("SELECT id FROM users WHERE role = 'super_admin' LIMIT 1")->fetch();
if ($existing) {
    fwrite(STDERR, "Super Admin sudah tersedia. Tidak ada perubahan yang dilakukan.\n");
    exit(1);
}
try {
    $statement = $pdo->prepare('INSERT INTO users (nama, email, password, role) VALUES (?, ?, ?, ?)');
    $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), 'super_admin']);
    fwrite(STDOUT, "Akun Super Admin berhasil dibuat.\n");
} catch (PDOException $exception) {
    error_log((string)$exception);
    fwrite(STDERR, $exception->getCode() === '23000'
        ? "Email tersebut sudah terdaftar.\n"
        : "Akun Super Admin gagal dibuat.\n");
    exit(1);
}
