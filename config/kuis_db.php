<?php
declare(strict_types=1);

$databaseHost = getenv('DB_HOST') ?: '127.0.0.1';
$databasePort = getenv('DB_PORT') ?: '3306';
$databaseName = getenv('DB_NAME') ?: 'etalase_stella';
$databaseUser = getenv('DB_USER') ?: 'root';
$databasePassword = getenv('DB_PASSWORD') ?: '';

if (!preg_match('/^[a-zA-Z0-9_]+$/', $databaseName)) {
    throw new RuntimeException('DB_NAME may contain only letters, numbers, and underscores.');
}

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $databaseHost,
    $databasePort,
    $databaseName
);

try {
    $pdo = new PDO($dsn, $databaseUser, $databasePassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
} catch (PDOException $exception) {
    error_log('Database connection failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('Basis data belum dapat diakses. Pastikan MySQL aktif dan konfigurasi koneksi sudah benar.');
}

return $pdo;
