</main>
<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-brand"><img src="<?= e(app_url('/public/images/logo.png')) ?>" alt="<?= !empty($isKaryaPage) ? 'Logo Telkom' : 'Logo Stella' ?>"><div><strong>Etalase Digital Stella</strong><span>SMK Telkom Lampung</span></div></div>
    <p>Ruang dokumentasi dan apresiasi karya digital siswa. Platform ini merupakan media portofolio pembelajaran.</p>
    <div class="footer-links"><a href="<?= e(app_url('/')) ?>">Beranda</a><a href="<?= e(app_url('/karya.php')) ?>">Karya</a><a href="<?= e(app_url('/auth/register.php')) ?>">Bergabung</a></div>
  </div>
  <div class="container copyright">© <?= date('Y') ?> Etalase Digital Stella · SMK Telkom Lampung</div>
</footer>
<?php if (!empty($isProfilePage)): ?><script src="<?= e(app_url('/public/js/profile.js')) ?>" defer></script><?php endif; ?>
<script src="<?= e(app_url('/public/js/app.js')) ?>" defer></script>
</body>
</html>
