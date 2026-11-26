<?php $me = current_customer(); ?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= e(($pageTitle ?? 'Müşteri Paneli') . ' · ' . setting('clinic_name', 'Patikum')) ?></title>
<link rel="icon" href="<?= e(favicon_url()) ?>">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="<?= asset('vendor/bootstrap.min.css') ?>" rel="stylesheet">
<link href="<?= asset('vendor/bootstrap-icons.min.css') ?>" rel="stylesheet">
<link href="<?= asset('css/site.css') ?>" rel="stylesheet">
</head>
<body class="panel-body">
<div class="panel-top">
  <div class="container d-flex align-items-center justify-content-between flex-wrap gap-2">
    <a class="brand" href="<?= url('panel/') ?>"><?php if ($lg = clinic_logo()): ?><span class="logo-pill"><img class="brand-logo" src="<?= e($lg) ?>" alt=""></span><?php else: ?><img class="brand-badge" src="<?= asset('img/patikum-logo-192.png') ?>" alt=""> patikum<?php endif; ?></a>
    <div class="d-flex gap-2 align-items-center">
      <?php if ($wa = setting('clinic_whatsapp')): ?><a class="btn btn-sm btn-light" href="<?= e(wa_link($wa)) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp text-success"></i> <span class="d-none d-sm-inline">Kliniğe yaz</span></a><?php endif; ?>
      <div class="dropdown"><button class="btn btn-sm btn-outline-light dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-person-circle"></i> <?= e(explode(' ', $me['name'])[0]) ?></button>
        <ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="<?= url('panel/') ?>"><i class="bi bi-house me-2"></i>Ana Sayfa</a></li><li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#mPass"><i class="bi bi-key me-2"></i>Şifre Değiştir</a></li><li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-danger" href="<?= url('panel/logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i>Çıkış</a></li></ul></div>
    </div>
  </div>
  <div class="container mt-4"><h1 class="h2 mb-0"><?= $pageHead ?? ('Merhaba ' . e(explode(' ', $me['name'])[0]) . ' 👋') ?></h1><div class="opacity-75"><?= e(setting('clinic_name')) ?></div></div>
</div>
<main class="container panel-wrap pb-5">
<?= flashes() ?>
