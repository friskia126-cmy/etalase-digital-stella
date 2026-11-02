<?php
declare(strict_types=1);
require __DIR__ . '/../middleware/auth_check.php';

if (current_user()) {
    redirect('/');
}
$pageTitle = 'Daftar';
$error = '';
$nama = '';
$email = '';
$kelas = '';
$jurusan = '';
$isAuthPage = true;
$configuredOptions = require __DIR__ . '/../config/registration_options.php';
$kelasOptions = is_array($configuredOptions['kelas'] ?? null) ? $configuredOptions['kelas'] : [];
$jurusanOptions = is_array($configuredOptions['jurusan'] ?? null) ? $configuredOptions['jurusan'] : [];
$kelasOptions = array_values(array_filter(array_map(
    static fn(mixed $value): string => trim((string)$value),
    $kelasOptions
), static fn(string $value): bool => $value !== ''));
$jurusanOptions = array_values(array_filter(array_map(
    static fn(mixed $value): string => trim((string)$value),
    $jurusanOptions
), static fn(string $value): bool => $value !== ''));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $nama = trim((string)($_POST['nama'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $kelas = trim((string)($_POST['kelas'] ?? ''));
    $jurusan = trim((string)($_POST['jurusan'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirmation = (string)($_POST['password_confirmation'] ?? '');
    if (mb_strlen($nama) < 2 || mb_strlen($nama) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $error = 'Periksa kembali nama lengkap dan alamat email.';
    } elseif (strlen($password) < 8 || strlen($password) > 72) {
        $error = 'Password harus berisi minimal 8 karakter dan maksimal 72 karakter.';
    } elseif (!hash_equals($password, $confirmation)) {
        $error = 'Konfirmasi password tidak sama.';
    } elseif ($kelas === '' || mb_strlen($kelas) > 80 ||
        ($kelasOptions && !in_array($kelas, $kelasOptions, true))) {
        $error = 'Pilih atau masukkan kelas yang valid.';
    } elseif ($jurusan === '' || mb_strlen($jurusan) > 120 ||
        ($jurusanOptions && !in_array($jurusan, $jurusanOptions, true))) {
        $error = 'Pilih atau masukkan jurusan yang valid.';
    } else {
        try {
            $existingAccount = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $existingAccount->execute([$email]);
            if ($existingAccount->fetchColumn()) {
                $error = 'Email tersebut sudah terdaftar.';
            } else {
                $statement = $pdo->prepare(
                    'INSERT INTO users (nama, email, password, role, kelas, jurusan)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $statement->execute([
                    $nama,
                    $email,
                    password_hash($password, PASSWORD_DEFAULT),
                    'siswa',
                    $kelas,
                    $jurusan,
                ]);
                flash('success', 'Akun berhasil dibuat. Silakan masuk.');
                redirect('/auth/login.php');
            }
        } catch (PDOException $exception) {
            if ((string)$exception->getCode() === '23000') {
                $error = 'Email tersebut sudah terdaftar.';
            } else {
                error_log('Student registration database error: ' . $exception->getMessage());
                $error = 'Pendaftaran belum dapat diproses. Silakan coba kembali beberapa saat lagi.';
            }
        }
    }
}
require __DIR__ . '/../partials/header.php';
?>
<section class="auth-shell container"><div class="auth-intro"><span class="eyebrow">MULAI BERKARYA</span><h1>Bangun ruang untuk ide-ide hebatmu.</h1><p>Daftar sebagai siswa untuk membagikan karya dan menyusun portofolio digital.</p><div class="auth-quote">Satu akun. Banyak kemungkinan.</div></div>
  <div class="auth-card"><div class="auth-logo"><img src="<?= e(app_url('/public/images/logo.png')) ?>" alt="Logo Stella"><span>SMK Telkom Lampung</span></div><h2>Buat akun siswa</h2><p class="muted">Semua pendaftaran baru otomatis menjadi akun siswa.</p>
    <?php if ($error): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form action="<?= e(app_url('/auth/register.php')) ?>" method="post" class="form-stack" data-loading-form data-loading-text="Mendaftarkan..." data-password-confirmation data-client-validation><?= csrf_field() ?>
      <div class="notice notice-error" data-client-error role="alert" aria-live="polite" hidden></div>
      <label>Nama lengkap<input type="text" name="nama" value="<?= e($nama) ?>" autocomplete="name" required minlength="2" maxlength="120"></label>
      <label>Email<input type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required maxlength="190"></label>
      <label>Kelas<?php if ($kelasOptions): ?><select name="kelas" required><option value="">Pilih kelas</option><?php foreach ($kelasOptions as $option): ?><option value="<?= e($option) ?>" <?= $kelas === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select><?php else: ?><input type="text" name="kelas" value="<?= e($kelas) ?>" autocomplete="organization-title" required maxlength="80" placeholder="Masukkan kelas"><?php endif; ?></label>
      <label>Jurusan<?php if ($jurusanOptions): ?><select name="jurusan" required><option value="">Pilih jurusan</option><?php foreach ($jurusanOptions as $option): ?><option value="<?= e($option) ?>" <?= $jurusan === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select><?php else: ?><input type="text" name="jurusan" value="<?= e($jurusan) ?>" required maxlength="120" placeholder="Masukkan jurusan"><?php endif; ?></label>
      <label>Password<span class="password-field"><input type="password" name="password" autocomplete="new-password" required minlength="8" maxlength="72"><button class="password-toggle" type="button" aria-label="Tampilkan password">Tampilkan</button></span><small>Gunakan minimal 8 karakter.</small></label>
      <label>Konfirmasi password<input type="password" name="password_confirmation" autocomplete="new-password" required minlength="8" maxlength="72"></label>
      <button class="button button-full" type="submit" data-loading-button>Daftar sebagai siswa <span>→</span></button>
    </form><p class="auth-switch">Sudah memiliki akun? <a href="<?= e(app_url('/auth/login.php')) ?>">Masuk</a></p>
  </div>
</section>
<?php require __DIR__ . '/../partials/footer.php'; ?>
