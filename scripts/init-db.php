<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'etalase_stella2';
$user = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
    fwrite(STDERR, "DB_NAME may contain only letters, numbers, and underscores.\n");
    exit(1);
}
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port),
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    if ($schema === false) {
        throw new RuntimeException('Unable to read schema.sql.');
    }
    $pdo->exec('USE `' . $name . '`');
    $statements = preg_split('/;\s*(?:\r?\n|$)/', $schema, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($statements as $statement) {
        if (trim($statement) !== '' && !preg_match('/^INSERT\s+IGNORE\s+INTO\s+categories\b/i', trim($statement))) {
            $pdo->exec($statement);
        }
    }
    $pdo->exec(
        "ALTER TABLE users
         MODIFY role ENUM('super_admin', 'admin', 'siswa') NOT NULL DEFAULT 'siswa'"
    );
    ensureColumns($pdo, 'users', [
        'kelas' => 'VARCHAR(80) NULL',
        'jurusan' => 'VARCHAR(120) NULL',
        'biodata' => 'TEXT NULL',
        'keahlian' => 'TEXT NULL',
        'minat' => 'VARCHAR(500) NULL',
        'tujuan_pengembangan' => 'TEXT NULL',
        'github_url' => 'VARCHAR(2048) NULL',
        'portfolio_url' => 'VARCHAR(2048) NULL',
        'foto_profil' => 'VARCHAR(255) NULL',
        'updated_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    ]);
    $userColumns = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('deskripsi_diri', $userColumns, true)) {
        $pdo->exec('UPDATE users SET biodata = deskripsi_diri WHERE (biodata IS NULL OR biodata = \'\') AND deskripsi_diri IS NOT NULL');
    }
    if (in_array('link_portofolio', $userColumns, true)) {
        $pdo->exec('UPDATE users SET portfolio_url = link_portofolio WHERE (portfolio_url IS NULL OR portfolio_url = \'\') AND link_portofolio IS NOT NULL');
    }
    $pdo->exec('ALTER TABLE users MODIFY jurusan VARCHAR(120) NULL');
    $pdo->exec('ALTER TABLE users MODIFY keahlian TEXT NULL');
    ensureColumns($pdo, 'categories', [
        'nama' => 'VARCHAR(100) NULL',
    ]);
    $categoryColumns = $pdo->query('SHOW COLUMNS FROM categories')->fetchAll(PDO::FETCH_COLUMN);
    $legacyCategoryName = in_array('name', $categoryColumns, true);
    if ($legacyCategoryName) {
        $pdo->exec('UPDATE categories SET nama = name WHERE (nama IS NULL OR nama = \'\') AND name IS NOT NULL');
        $pdo->exec('ALTER TABLE categories MODIFY name VARCHAR(100) NOT NULL');
    }
    $seedCategories = [
        'Website Development',
        'Mobile Application',
        'UI/UX Design',
        'Graphic Design',
        'Multimedia',
        'Programming',
        'Database Project',
        'Other Projects',
    ];
    foreach ($seedCategories as $categoryName) {
        if ($legacyCategoryName) {
            $pdo->prepare('INSERT IGNORE INTO categories (nama, name) VALUES (?, ?)')->execute([$categoryName, $categoryName]);
        } else {
            $pdo->prepare('INSERT IGNORE INTO categories (nama) VALUES (?)')->execute([$categoryName]);
        }
    }
    $pdo->exec('ALTER TABLE categories MODIFY nama VARCHAR(100) NOT NULL');
    $categoryNameIndex = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories'
           AND COLUMN_NAME = 'nama' AND NON_UNIQUE = 0"
    )->fetchColumn();
    if ((int)$categoryNameIndex === 0) {
        $pdo->exec('ALTER TABLE categories ADD UNIQUE KEY uq_categories_nama (nama)');
    }
    ensureColumns($pdo, 'projects', [
        'category_id' => 'INT UNSIGNED NULL',
        'github_url' => 'VARCHAR(2048) NULL',
        'kelas' => 'VARCHAR(80) NULL',
        'jurusan' => 'VARCHAR(120) NULL',
        'updated_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    ]);
    $projectColumns = $pdo->query('SHOW COLUMNS FROM projects')->fetchAll(PDO::FETCH_COLUMN);
    $pdo->exec('ALTER TABLE projects MODIFY link_project VARCHAR(2048) NULL');
    if (in_array('kategori', $projectColumns, true)) {
        $pdo->exec(
            'UPDATE projects p JOIN categories c ON LOWER(TRIM(c.nama)) = LOWER(TRIM(p.kategori))
             SET p.category_id = c.id WHERE p.category_id IS NULL'
        );
    }
    $defaultCategoryId = (int)$pdo->query(
        "SELECT id FROM categories WHERE nama = 'Other Projects' LIMIT 1"
    )->fetchColumn();
    if ($defaultCategoryId < 1) {
        $defaultCategoryId = (int)$pdo->query('SELECT id FROM categories ORDER BY id LIMIT 1')->fetchColumn();
    }
    if ($defaultCategoryId < 1) {
        throw new RuntimeException('At least one project category is required for migration.');
    }
    $pdo->prepare(
        'UPDATE projects SET category_id = ? WHERE category_id IS NULL
         OR category_id NOT IN (SELECT id FROM categories)'
    )->execute([$defaultCategoryId]);
    $pdo->exec('ALTER TABLE projects MODIFY category_id INT UNSIGNED NOT NULL');
    $indexes = $pdo->query('SHOW INDEX FROM projects')->fetchAll(PDO::FETCH_COLUMN, 2);
    if (!in_array('ix_projects_category_id', $indexes, true)) {
        $pdo->exec('ALTER TABLE projects ADD INDEX ix_projects_category_id (category_id)');
    }
    if (!in_array('ix_projects_user_id', $indexes, true)) {
        $pdo->exec('ALTER TABLE projects ADD INDEX ix_projects_user_id (user_id)');
    }
    if (!in_array('ix_projects_created_at', $indexes, true)) {
        $pdo->exec('ALTER TABLE projects ADD INDEX ix_projects_created_at (created_at)');
    }
    $constraints = $pdo->query(
        "SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'projects'"
    )->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('fk_projects_category', $constraints, true)) {
        $pdo->exec(
            'ALTER TABLE projects ADD CONSTRAINT fk_projects_category
             FOREIGN KEY (category_id) REFERENCES categories (id)'
        );
    }
    $uniqueEmail = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
           AND COLUMN_NAME = 'email' AND NON_UNIQUE = 0"
    )->fetchColumn();
    if ((int)$uniqueEmail === 0) {
        $pdo->exec('ALTER TABLE users ADD UNIQUE KEY uq_users_email (email)');
    }
    fwrite(STDOUT, "Database \"$name\" and required tables are ready. Existing records were not dropped.\n");
} catch (Throwable $exception) {
    error_log((string)$exception);
    fwrite(STDERR, 'Database initialization failed: ' . $exception->getMessage() . "\n");
    exit(1);
}

function ensureColumns(PDO $pdo, string $table, array $columns): void
{
    $existing = $pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($columns as $name => $definition) {
        if (!in_array($name, $existing, true)) {
            $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $name . '` ' . $definition);
        }
    }
}
