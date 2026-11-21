<?php
$u = current_user();
$active = $active ?? '';
$notif = [
    'talep' => (int)val("SELECT COUNT(*) FROM appointments WHERE status = 'talep'"),
    'stok' => (int)val('SELECT COUNT(*) FROM products WHERE active = 1 AND is_service = 0 AND stock <= critical_stock'),
    'wa' => (int)val("SELECT COUNT(*) FROM wa_messages WHERE status IN ('kuyruk','hata')"),
    'gecikenAsi' => (int)val("SELECT COUNT(*) FROM vaccinations v JOIN patients p ON p.id = v.patient_id WHERE v.status = 'planlandi' AND v.due_date < ? AND p.status = 'aktif'", [today()]),
];
$notifTotal = $notif['talep'] + $notif['stok'] + $notif['gecikenAsi'];
$menu = [
    'genel' => ['Genel Bakış', 'house-heart', url('admin/'), []],
    'randevu' => ['Randevular', 'calendar-heart', null, [
        ['Randevu Listesi', 'list-ul', url('admin/randevular.php')],
        ['Randevu Takvimi', 'calendar3', url('admin/randevular.php?gorunum=takvim')],
        ['Yeni Randevu', 'plus-circle', url('admin/randevular.php?islem=yeni')],
        ['Web Talepleri', 'globe2', url('admin/randevular.php?durum=talep')],
    ]],
    'musteri' => ['Müşteriler', 'people', null, [
        ['Müşteri Listesi', 'person-lines-fill', url('admin/musteriler.php')],
        ['Yeni Müşteri', 'person-plus', url('admin/musteriler.php?islem=yeni')],
        ['Borçlu Müşteriler', 'cash-coin', url('admin/muhasebe.php?sekme=cari')],
    ]],
    'hasta' => ['Hastalar', 'heart-pulse', null, [
        ['Hasta Listesi', 'clipboard2-pulse', url('admin/hastalar.php')],
        ['Yeni Hasta', 'plus-circle', url('admin/hastalar.php?islem=yeni')],
        ['Hayvan Kategorileri', 'tags', url('admin/kategoriler.php')],
    ]],
    'asi' => ['Aşı Takvimi', 'eyedropper', null, [
        ['Aşı Takvimi', 'calendar2-week', url('admin/asilar.php')],
        ['Otomatik Aşı Sihirbazı', 'magic', url('admin/asi-sihirbazi.php')],
        ['Aşı Tanımları & Protokoller', 'sliders', url('admin/asi-tanimlari.php')],
    ]],
    'satis' => ['Satışlar', 'cart3', null, [
        ['Yeni Satış', 'cart-plus', url('admin/satis.php')],
        ['Satış Listesi', 'receipt', url('admin/satislar.php')],
    ]],
    'urun' => ['Ürünler', 'box-seam', null, [
        ['Ürün & Hizmet Listesi', 'boxes', url('admin/urunler.php')],
        ['Stok Hareketleri', 'arrow-left-right', url('admin/stok.php')],
        ['Kritik Stok', 'exclamation-triangle', url('admin/urunler.php?kritik=1')],
    ]],
    'muhasebe' => ['Muhasebe', 'wallet2', null, [
        ['Kasa Özeti', 'safe', url('admin/muhasebe.php')],
        ['Tahsilatlar', 'box-arrow-in-down', url('admin/muhasebe.php?sekme=tahsilat')],
        ['Giderler / Ödemeler', 'box-arrow-up', url('admin/muhasebe.php?sekme=gider')],
        ['Cari Hesaplar', 'journal-text', url('admin/muhasebe.php?sekme=cari')],
        ['E-Dönüşüm (e-Fatura / e-Arşiv)', 'file-earmark-text', url('admin/e-donusum.php')],
    ]],
    'rapor' => ['Raporlar', 'bar-chart-line', url('admin/raporlar.php'), []],
    'diger' => ['Diğer', 'grid', null, [
        ['WhatsApp Mesajları', 'whatsapp', url('admin/whatsapp.php')],
        ['Otomatik Hatırlatıcılar', 'alarm', url('admin/hatirlaticilar.php')],
        ['Ayarlar', 'gear', url('admin/ayarlar.php')],
        ['Kullanıcılar', 'person-badge', url('admin/kullanicilar.php')],
        ['Siteyi Görüntüle', 'box-arrow-up-right', url('')],
    ]],
];
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="csrf-token" content="<?= csrf_token() ?>">
<title><?= e(($pageTitle ?? 'Yönetim') . ' · ' . setting('clinic_name', 'Patikum')) ?></title>
<link rel="icon" href="<?= asset('img/favicon.svg') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<link href="<?= asset('css/admin.css') ?>" rel="stylesheet">
</head>
<body data-base="<?= e(base_url()) ?>">
<header class="pt-topbar">
  <div class="container-xxl inner">
    <a class="pt-logo" href="<?= url('admin/') ?>">
      <span class="paw"><img src="<?= asset('img/favicon.svg') ?>" width="26" height="26" alt=""></span>
      patikum <small>PRO</small>
    </a>
    <form class="pt-search ms-lg-4" action="<?= url('admin/ara.php') ?>">
      <i class="bi bi-search"></i>
      <input class="form-control" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Ara: müşteri, hasta, telefon, çip no, ürün, barkod…">
    </form>
    <div class="ms-auto d-flex align-items-center gap-1">
      <a class="pt-icon-btn" href="<?= url('admin/whatsapp.php') ?>" title="WhatsApp kuyruğu"><i class="bi bi-whatsapp"></i><?php if ($notif['wa']): ?><span class="count"><?= $notif['wa'] ?></span><?php endif; ?></a>
      <div class="dropdown">
        <a class="pt-icon-btn" href="#" data-bs-toggle="dropdown" title="Bildirimler"><i class="bi bi-bell"></i><?php if ($notifTotal): ?><span class="count"><?= $notifTotal ?></span><?php endif; ?></a>
        <div class="dropdown-menu dropdown-menu-end p-2" style="min-width:290px">
          <h6 class="dropdown-header">Bildirimler</h6>
          <a class="dropdown-item d-flex justify-content-between" href="<?= url('admin/randevular.php?durum=talep') ?>"><span><i class="bi bi-globe2 me-2"></i>Web randevu talebi</span><b><?= $notif['talep'] ?></b></a>
          <a class="dropdown-item d-flex justify-content-between" href="<?= url('admin/asilar.php?sekme=geciken') ?>"><span><i class="bi bi-eyedropper me-2"></i>Geciken aşı</span><b><?= $notif['gecikenAsi'] ?></b></a>
          <a class="dropdown-item d-flex justify-content-between" href="<?= url('admin/urunler.php?kritik=1') ?>"><span><i class="bi bi-box-seam me-2"></i>Kritik stok</span><b><?= $notif['stok'] ?></b></a>
          <a class="dropdown-item d-flex justify-content-between" href="<?= url('admin/whatsapp.php') ?>"><span><i class="bi bi-whatsapp me-2"></i>Bekleyen WhatsApp</span><b><?= $notif['wa'] ?></b></a>
        </div>
      </div>
      <div class="dropdown ms-2">
        <a href="#" class="pt-user" data-bs-toggle="dropdown">
          <span class="avatar"><?= e(initials($u['name'])) ?></span>
          <span class="d-none d-md-inline fw-bold"><?= e($u['name']) ?></span><i class="bi bi-chevron-down small"></i>
        </a>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="<?= url('admin/kullanicilar.php?islem=profil') ?>"><i class="bi bi-person me-2"></i>Profilim</a></li>
          <li><a class="dropdown-item" href="<?= url('admin/ayarlar.php') ?>"><i class="bi bi-gear me-2"></i>Ayarlar</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="<?= url('admin/logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i>Çıkış Yap</a></li>
        </ul>
      </div>
    </div>
  </div>
</header>
<nav class="pt-nav navbar navbar-expand-lg py-0">
  <div class="container-xxl">
    <button class="navbar-toggler my-2" type="button" data-bs-toggle="collapse" data-bs-target="#ptMenu"><i class="bi bi-list"></i> Menü</button>
    <div class="collapse navbar-collapse" id="ptMenu">
      <ul class="navbar-nav">
        <?php foreach ($menu as $key => [$label, $icon, $href, $children]): ?>
          <?php if (!$children): ?>
            <li class="nav-item"><a class="nav-link<?= $active === $key ? ' active' : '' ?>" href="<?= $href ?>"><i class="bi bi-<?= $icon ?>"></i> <?= $label ?></a></li>
          <?php else: ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle<?= $active === $key ? ' active' : '' ?>" href="#" data-bs-toggle="dropdown"><i class="bi bi-<?= $icon ?>"></i> <?= $label ?></a>
              <ul class="dropdown-menu">
                <?php foreach ($children as [$cl, $ci, $ch]): ?>
                  <li><a class="dropdown-item" href="<?= $ch ?>"><i class="bi bi-<?= $ci ?>"></i> <?= $cl ?></a></li>
                <?php endforeach; ?>
              </ul>
            </li>
          <?php endif; ?>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</nav>
<main class="container-xxl pb-4">
<div class="mt-3"><?= flashes() ?></div>
