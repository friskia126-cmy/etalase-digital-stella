<?php
declare(strict_types=1);
require __DIR__ . '/../middleware/auth_check.php';

if (current_user()) {
    redirect(current_user()['role'] === 'siswa' ? '/' : '/admin/index.php');
}
$pageTitle = 'Masuk';
$isAuthPage = true;
$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $attempts = $_SESSION['login_attempts'] ?? [];
    if (!is_array($attempts) || !isset($attempts['count'], $attempts['since'])) {
        $attempts = ['count' => 0, 'since' => time()];
    }
    if (time() - (int)$attempts['since'] > 900) {
        $attempts = ['count' => 0, 'since' => time()];
    }
    if ((int)$attempts['count'] >= 10) {
        $error = 'Terlalu banyak percobaan masuk. Coba kembali beberapa menit lagi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190 || $password === '' || strlen($password) > 72) {
        $error = 'Masukkan email dan password yang valid.';
    } else {
        try {
            $statement = $pdo->prepare(
                'SELECT id, nama, email, password, role, kelas, jurusan, foto_profil
                 FROM users WHERE email = ? LIMIT 1'
            );
            $statement->execute([$email]);
            $account = $statement->fetch();
        } catch (PDOException $exception) {
            error_log('Login database error: ' . $exception->getMessage());
            $account = false;
            $error = 'Layanan masuk belum dapat digunakan. Silakan coba kembali beberapa saat lagi.';
        }
        if ($error === '') {
            if ($account && password_verify($password, $account['password']) &&
                in_array($account['role'], ['siswa', 'admin', 'super_admin'], true)) {
                session_regenerate_id(true);
                unset($_SESSION['login_attempts']);
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $_SESSION['user'] = [
                    'id' => (int)$account['id'],
                    'nama' => $account['nama'],
                    'email' => $account['email'],
                    'role' => $account['role'],
                    'kelas' => $account['kelas'] ?? null,
                    'jurusan' => $account['jurusan'] ?? null,
                    'foto_profil' => $account['foto_profil'] ?? null,
                ];
                redirect($account['role'] === 'siswa' ? '/' : '/admin/index.php');
            }
            $attempts['count'] = (int)$attempts['count'] + 1;
            $_SESSION['login_attempts'] = $attempts;
            $error = 'Email atau password tidak sesuai.';
        }
    }
}
require __DIR__ . '/../partials/header.php';
?>
<section class="auth-shell container"><div class="auth-intro"><span class="eyebrow">SELAMAT DATANG KEMBALI</span><h1>Setiap karya punya cerita.</h1><p>Masuk untuk mengelola proyek, memperbarui portofolio, dan melanjutkan perjalanan berkarya.</p><div class="auth-quote">“Showcase Your Creativity,<br>Share Your Innovation”</div></div>
  <div class="auth-card"><div class="auth-logo"><img src="<?= e(app_url('/public/images/logo.png')) ?>" alt="Logo Stella"><span>SMK Telkom Lampung</span></div><h2>Masuk ke akun</h2><p class="muted">Gunakan email dan password akun Anda.</p>
    <?php if ($error): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form action="<?= e(app_url('/auth/login.php')) ?>" method="post" class="form-stack" data-loading-form data-loading-text="Memeriksa..." data-client-validation><?= csrf_field() ?>
      <div class="notice notice-error" data-client-error role="alert" aria-live="polite" hidden></div>
      <label>Email<input type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required maxlength="190"></label>
      <label>Password<span class="password-field"><input type="password" name="password" autocomplete="current-password" required><button class="password-toggle" type="button" aria-label="Tampilkan password">Tampilkan</button></span></label>
      <button class="button button-full" type="submit" data-loading-button>Masuk <span>→</span></button>
    </form><p class="auth-switch">Belum punya akun? <a href="<?= e(app_url('/auth/register.php')) ?>">Daftar sebagai siswa</a></p>
  </div>
</section>
<?php require __DIR__ . '/../partials/footer.php'; ?>
