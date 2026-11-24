<?php
require __DIR__ . '/../app/bootstrap.php';
if (current_user()) redirect(url('admin/'));
$err = null;
if (is_post()) {
    if (staff_login((string)post('email'), (string)post('password'))) {
        $to = $_SESSION['after_login'] ?? '';
        unset($_SESSION['after_login']);
        redirect($to && str_contains($to, '/admin/') ? $to : url('admin/'));
    }
    $err = login_throttled('staff') ? 'Çok fazla hatalı deneme. 10 dakika sonra tekrar deneyin.' : 'E-posta veya şifre hatalı.';
}
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Yönetim Girişi · <?= e(setting('clinic_name', 'Patikum')) ?></title>
<link rel="icon" href="<?= asset('img/favicon.svg') ?>">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= asset('css/admin.css') ?>" rel="stylesheet">
<style>
body{min-height:100vh;display:grid;grid-template-columns:1.1fr 1fr;background:#fff}
.side{background:linear-gradient(140deg,#10302a,#1d7a64 60%,#2b9a7d);color:#fff;padding:3rem;display:flex;flex-direction:column;justify-content:space-between;position:relative;overflow:hidden}
.side .paws{position:absolute;right:-40px;bottom:-40px;font-size:16rem;opacity:.08;transform:rotate(-20deg)}
.side h2{font-weight:900;font-size:2.3rem;line-height:1.15}
.side li{margin:.45rem 0;font-weight:600}
.form-side{display:grid;place-items:center;padding:2rem}
.box{width:100%;max-width:380px}
@media(max-width:900px){body{grid-template-columns:1fr}.side{display:none}}
</style>
</head>
<body>
<div class="side">
  <div class="pt-logo"><span class="paw"><img src="<?= asset('img/favicon.svg') ?>" width="26" alt=""></span> patikum</div>
  <div>
    <h2>Kliniğiniz tek ekranda,<br>dostlarınız güvende.</h2>
    <ul class="list-unstyled mt-4">
      <li><i class="bi bi-check2-circle me-2"></i>Hasta & müşteri yönetimi</li>
      <li><i class="bi bi-check2-circle me-2"></i>Otomatik aşı sihirbazı ve hatırlatmalar</li>
      <li><i class="bi bi-check2-circle me-2"></i>WhatsApp bilgilendirme</li>
      <li><i class="bi bi-check2-circle me-2"></i>Stok, satış, muhasebe ve e-dönüşüm</li>
    </ul>
  </div>
  <small class="opacity-75">© <?= date('Y') ?> <?= e(setting('clinic_name', 'Patikum')) ?></small>
  <div class="paws">🐾</div>
</div>
<div class="form-side">
  <div class="box">
    <h1 class="h3 fw-bold mb-1">Hoş geldiniz 👋</h1>
    <p class="text-muted mb-4">Yönetim paneline giriş yapın</p>
    <?php if ($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label fw-bold">E-posta</label><input type="email" name="email" class="form-control form-control-lg" required autofocus value="<?= e(post('email')) ?>"></div>
      <div class="mb-3"><label class="form-label fw-bold">Şifre</label><input type="password" name="password" class="form-control form-control-lg" required></div>
      <button class="btn btn-primary btn-lg w-100 fw-bold">Giriş Yap</button>
    </form>
    <div class="text-center mt-4"><a href="<?= url('') ?>" class="text-muted small"><i class="bi bi-arrow-left"></i> Siteye dön</a></div>
  </div>
</div>
</body>
</html>
