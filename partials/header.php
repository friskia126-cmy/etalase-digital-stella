<?php
$pageTitle = $pageTitle ?? 'Etalase Digital Stella';
$user = current_user();
$notice = take_flash();
$isKaryaPage = $isKaryaPage ?? false;
$isProfilePage = $isProfilePage ?? false;
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Etalase Digital Stella — ruang apresiasi dan portofolio karya digital siswa SMK Telkom Lampung.">
  <title><?= e($pageTitle) ?> | Etalase Digital Stella</title>
  <link rel="stylesheet" href="<?= e(app_url('/public/css/style.css')) ?>">
  <?php if ($isKaryaPage): ?><link rel="stylesheet" href="<?= e(app_url('/public/css/karya.css')) ?>"><?php endif; ?>
  <?php if ($isProfilePage): ?><link rel="stylesheet" href="<?= e(app_url('/public/css/profile.css')) ?>"><?php endif; ?>
</head>
<body class="<?= !empty($isAuthPage) ? 'auth-page' : '' ?>">
<header class="site-header">
  <div class="container nav-wrap">
    <a class="brand" href="<?= e(app_url('/')) ?>" aria-label="Etalase Digital Stella - Beranda">
      <img src="<?= e(app_url('/public/images/logo.png')) ?>" alt="<?= $isKaryaPage ? 'Logo Telkom' : 'Logo Stella' ?>" class="brand-logo">
      <span class="brand-copy"><strong>Etalase Digital Stella</strong><small>SMK Telkom Lampung</small></span>
    </a>
    <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-nav" aria-label="Buka navigasi">☰</button>
    <nav id="primary-nav" class="primary-nav">
      <?php if ($isKaryaPage): ?>
        <a href="<?= e(app_url('/')) ?>">Beranda</a>
        <a class="active karya-active" href="<?= e(app_url('/karya.php')) ?>" aria-current="page">Karya</a>
        <a href="<?= e(app_url('/#about')) ?>">Tentang</a>
      <?php else: ?>
        <a href="<?= e(app_url('/')) ?>">Beranda</a>
        <a href="<?= e(app_url('/#about')) ?>">Tentang</a>
        <a href="<?= e(app_url('/karya.php')) ?>">Karya</a>
      <?php endif; ?>
      <?php if ($user): ?>
        <?php if ($user['role'] !== 'siswa' && !$isKaryaPage): ?><a href="<?= e(app_url('/admin/index.php')) ?>">Dashboard</a><?php endif; ?>
        <?php if (!$isKaryaPage): ?>
          <?php if ($user['role'] === 'super_admin'): ?><a href="<?= e(app_url('/admin/users.php')) ?>">Pengguna</a><a href="<?= e(app_url('/admin/categories.php')) ?>">Kategori</a><?php endif; ?>
          <?php if ($user['role'] === 'admin'): ?><a href="<?= e(app_url('/admin/index.php?view=students')) ?>">Data Siswa</a><?php endif; ?>
          <a href="<?= e(app_url('/profile.php')) ?>">Profil</a>
          <form action="<?= e(app_url('/auth/logout.php')) ?>" method="post" class="nav-logout"><?= csrf_field() ?><button class="nav-link" type="submit">Keluar</button></form>
          <a class="button button-small" href="<?= e(app_url('/project_tambah.php')) ?>">Tambah Karya</a>
        <?php else: ?>
          <a class="karya-nav-dashboard" href="<?= e(app_url($user['role'] === 'siswa' ? '/profile.php' : '/admin/index.php')) ?>"><?= $user['role'] === 'siswa' ? 'Profil' : 'Dashboard' ?></a>
          <div class="karya-nav-account">
            <a class="karya-nav-profile" href="<?= e(app_url('/profile.php')) ?>">
              <span class="karya-nav-avatar"><?php if (!empty($user['foto_profil'])): ?><img src="<?= e(project_image_url($user['foto_profil'])) ?>" alt=""><?php else: ?><?= e(mb_strtoupper(mb_substr($user['nama'], 0, 1))) ?><?php endif; ?></span>
              <span class="karya-nav-copy"><strong><?= e($user['nama']) ?></strong><small><?= e(ucfirst(str_replace('_', ' ', $user['role']))) ?></small></span>
            </a>
            <form action="<?= e(app_url('/auth/logout.php')) ?>" method="post" class="karya-nav-logout"><?= csrf_field() ?><button type="submit">Keluar</button></form>
          </div>
          <?php if ($user['role'] === 'super_admin'): ?><a class="karya-nav-management" href="<?= e(app_url('/admin/users.php')) ?>">Pengguna</a><a class="karya-nav-management" href="<?= e(app_url('/admin/categories.php')) ?>">Kategori</a><?php endif; ?>
          <?php if ($user['role'] === 'admin'): ?><a class="karya-nav-management" href="<?= e(app_url('/admin/index.php?view=students')) ?>">Data Siswa</a><?php endif; ?>
        <?php endif; ?>
      <?php else: ?>
        <a href="<?= e(app_url('/auth/login.php')) ?>">Masuk</a>
        <a class="button button-small" href="<?= e(app_url('/auth/register.php')) ?>">Daftar</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main>
  <?php if ($notice): ?><div class="container"><div class="notice notice-<?= e($notice['type']) ?>" role="status"><?= e($notice['message']) ?></div></div><?php endif; ?>
