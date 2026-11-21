<?php
require __DIR__ . '/../app/bootstrap.php';
if (current_customer()) redirect(url('panel/'));
$err = null;
if (is_post()) {
    if (customer_login((string)post('phone'), (string)post('password'))) redirect(url('panel/'));
    $err = login_throttled('cust') ? 'Çok fazla hatalı deneme. Lütfen 10 dakika sonra tekrar deneyin.' : 'Telefon numarası veya şifre hatalı.';
}
$wa = setting('clinic_whatsapp');
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Müşteri Paneli · <?= e(setting('clinic_name', 'Patikum')) ?></title>
<link rel="icon" href="<?= asset('img/favicon.svg') ?>">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@700;800&family=Nunito:wght@400;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= asset('css/site.css') ?>" rel="stylesheet">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <a class="brand justify-content-center mb-3" href="<?= url('') ?>"><img src="<?= asset('img/favicon.svg') ?>" alt=""> patikum</a>
    <h1 class="h3 text-center">Müşteri Paneli</h1>
    <p class="text-center text-muted">Dostunuzun aşı takvimi, işlemleri ve hesap durumunuz tek ekranda.</p>
    <?php if ($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label fw-bold">Cep Telefonu</label><input name="phone" type="tel" class="form-control form-control-lg" placeholder="05xx xxx xx xx" required autofocus value="<?= e(post('phone')) ?>"></div>
      <div class="mb-3"><label class="form-label fw-bold">Şifre</label><input name="password" type="password" class="form-control form-control-lg" required></div>
      <button class="btn btn-teal btn-lg w-100">Giriş Yap</button>
    </form>
    <p class="small text-muted text-center mt-3 mb-0">Şifreniz yok mu veya unuttunuz mu?<br>
      <?php if ($wa): ?><a href="<?= e(wa_link($wa, 'Merhaba, müşteri paneli şifremi almak istiyorum.')) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> WhatsApp'tan kliniğimize yazın</a><?php else: ?>Kliniğimizle iletişime geçin.<?php endif; ?></p>
    <div class="text-center mt-3"><a href="<?= url('') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Ana sayfa</a></div>
  </div>
</div>
</body>
</html>
