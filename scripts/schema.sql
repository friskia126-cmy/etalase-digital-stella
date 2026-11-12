CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('super_admin', 'admin', 'siswa') NOT NULL DEFAULT 'siswa',
  kelas VARCHAR(80) NULL,
  jurusan VARCHAR(120) NULL,
  biodata TEXT NULL,
  keahlian TEXT NULL,
  minat VARCHAR(500) NULL,
  tujuan_pengembangan TEXT NULL,
  github_url VARCHAR(2048) NULL,
  portfolio_url VARCHAR(2048) NULL,
  foto_profil VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama VARCHAR(100) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_name (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  nama_project VARCHAR(160) NOT NULL,
  deskripsi TEXT NOT NULL,
  gambar VARCHAR(255) NULL,
  link_project VARCHAR(2048) NULL,
  github_url VARCHAR(2048) NULL,
  kelas VARCHAR(80) NULL,
  jurusan VARCHAR(120) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_projects_created_at (created_at),
  KEY ix_projects_category_id (category_id),
  KEY ix_projects_user_id (user_id),
  CONSTRAINT fk_projects_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_projects_category FOREIGN KEY (category_id) REFERENCES categories (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO categories (nama) VALUES
  ('Website Development'),
  ('Mobile Application'),
  ('UI/UX Design'),
  ('Graphic Design'),
  ('Multimedia'),
  ('Programming'),
  ('Database Project'),
  ('Other Projects');

INSERT IGNORE INTO users (nama, email, password, role) VALUES
('Super Admin Stella', 'superadmin@stella.sch.id', '$2y$12$k.M7ujGwmTxt8zSISTj3..v5i7Y9wa7kNgKVsJrytUUxkHmyu41fK', 'super_admin'),
('Admin Stella', 'admin@stella.sch.id', '$2y$12$NH8UacO52nw1uANV0yIkjONn.ufNLe8ttaH95APB6r3AF3mlbrim6', 'admin'),
('Siswa Contoh', 'siswa@stella.sch.id', '$2y$12$y96PPqkE1PgGsoFr2LQCqer3iG0Lv44b070emlzKBfYz5.6LU/QaG', 'siswa');
