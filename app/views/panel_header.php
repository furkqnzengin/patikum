<?php $me = current_customer(); ?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= e(($pageTitle ?? 'Müşteri Paneli') . ' · ' . setting('clinic_name', 'Patikum')) ?></title>
<link rel="icon" href="<?= asset('img/favicon.svg') ?>">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@700;800&family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= asset('css/site.css') ?>" rel="stylesheet">
</head>
<body class="panel-body">
<div class="panel-top">
  <div class="container d-flex align-items-center justify-content-between flex-wrap gap-2">
    <a class="brand" href="<?= url('panel/') ?>"><img src="<?= asset('img/favicon.svg') ?>" alt="" style="filter:brightness(0) invert(1)"> patikum</a>
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
