<?php
$hdrUser = current_user();
$active = $active ?? '';
$notif = [
    'talep' => (int)val("SELECT COUNT(*) FROM appointments WHERE status = 'talep'"),
    'stok' => (int)val('SELECT COUNT(*) FROM products WHERE active = 1 AND is_service = 0 AND stock <= critical_stock'),
    'wa' => (int)val("SELECT COUNT(*) FROM wa_messages WHERE status IN ('kuyruk','hata')"),
    'gecikenAsi' => (int)val("SELECT COUNT(*) FROM vaccinations v JOIN patients p ON p.id = v.patient_id WHERE v.status = 'planlandi' AND v.due_date < ? AND p.status = 'aktif'", [today()]),
];
$hdrNotifTotal = $notif['talep'] + $notif['stok'] + $notif['gecikenAsi'];
$hdrMenu = [
    'genel' => ['Genel Bakış', 'grid-1x2', url('admin/'), []],
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
        ['Aşı Takvimi (Aylık)', 'calendar3', url('admin/asilar.php?sekme=takvim')],
        ['Yaklaşan Aşılar', 'calendar2-week', url('admin/asilar.php?sekme=yaklasan')],
        ['Geciken Aşılar', 'exclamation-circle', url('admin/asilar.php?sekme=geciken')],
        ['Otomatik Aşı Sihirbazı', 'magic', url('admin/asi-sihirbazi.php')],
        ['Aşı Tanımları & Protokoller', 'sliders', url('admin/asi-tanimlari.php')],
    ]],
    'satis' => ['Satışlar', 'cart3', null, [
        ['Yeni Satış', 'cart-plus', url('admin/satis.php')],
        ['Satış Listesi', 'receipt', url('admin/satislar.php')],
    ]],
    'urun' => ['Ürünler & Stok', 'box-seam', null, [
        ['Ürün & Hizmet Listesi', 'boxes', url('admin/urunler.php')],
        ['Stok Hareketleri', 'arrow-left-right', url('admin/stok.php')],
        ['Kritik Stok', 'exclamation-triangle', url('admin/urunler.php?kritik=1')],
    ]],
    'muhasebe' => ['Muhasebe', 'wallet2', null, [
        ['Kasa Özeti', 'safe', url('admin/muhasebe.php')],
        ['Tahsilatlar', 'box-arrow-in-down', url('admin/muhasebe.php?sekme=tahsilat')],
        ['Giderler / Ödemeler', 'box-arrow-up', url('admin/muhasebe.php?sekme=gider')],
        ['Cari Hesaplar', 'journal-text', url('admin/muhasebe.php?sekme=cari')],
        ['E-Dönüşüm', 'file-earmark-text', url('admin/e-donusum.php')],
    ]],
    'rapor' => ['Raporlar', 'bar-chart-line', url('admin/raporlar.php'), []],
    'diger' => ['Diğer', 'three-dots', null, [
        ['WhatsApp Mesajları', 'whatsapp', url('admin/whatsapp.php')],
        ['Otomatik Hatırlatıcılar', 'alarm', url('admin/hatirlaticilar.php')],
        ['Excel\'den Veri Aktarımı', 'file-earmark-arrow-up', url('admin/aktar.php')],
        ['Ayarlar', 'gear', url('admin/ayarlar.php')],
        ['Kullanıcılar', 'person-badge', url('admin/kullanicilar.php')],
    ]],
];
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="csrf-token" content="<?= csrf_token() ?>">
<meta name="theme-color" content="#0f3336">
<title><?= e(($pageTitle ?? 'Yönetim') . ' · ' . setting('clinic_name', 'Patikum')) ?></title>
<script>try{if(localStorage.getItem('pt_money_hidden')==='1')document.documentElement.classList.add('money-hidden');if(localStorage.getItem('pt_side_mini')==='1')document.documentElement.classList.add('side-mini');}catch(e){}</script>
<link rel="icon" href="<?= e(favicon_url()) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="<?= asset('vendor/bootstrap.min.css') ?>" rel="stylesheet">
<link href="<?= asset('vendor/bootstrap-icons.min.css') ?>" rel="stylesheet">
<link href="<?= asset('vendor/tom-select.bootstrap5.min.css') ?>" rel="stylesheet">
<link href="<?= asset('css/admin.css') ?>" rel="stylesheet">
</head>
<body data-base="<?= e(base_url()) ?>">
<div class="pt-app">
<aside class="pt-side" id="ptSide">
  <div class="pt-side-head">
    <a class="pt-logo" href="<?= url('admin/') ?>">
      <?php if ($hdrLogo = clinic_logo()): ?><span class="logo-box"><img src="<?= e($hdrLogo) ?>" alt="<?= e(setting('clinic_name', 'Patikum')) ?>"></span>
      <?php else: ?><img class="brand-badge" src="<?= asset('img/patikum-logo-192.png') ?>" width="44" height="44" alt="Patikum">
      <span class="txt">patikum<small>klinik</small></span><?php endif; ?>
    </a>
    <button class="pt-side-close d-lg-none" type="button" data-pt-side="close" aria-label="Menüyü kapat"><i class="bi bi-x-lg"></i></button>
  </div>
  <nav class="pt-side-nav">
    <?php foreach ($hdrMenu as $hdrKey => [$hdrLabel, $hdrIcon, $hdrHref, $hdrChildren]): $hdrIsA = $active === $hdrKey; ?>
      <?php if (!$hdrChildren): ?>
        <a class="pt-side-link<?= $hdrIsA ? ' active' : '' ?>" href="<?= $hdrHref ?>" title="<?= e($hdrLabel) ?>"><i class="bi bi-<?= $hdrIcon ?>"></i><span><?= $hdrLabel ?></span></a>
      <?php else: ?>
        <details class="pt-side-group"<?= $hdrIsA ? ' open' : '' ?>>
          <summary class="pt-side-link<?= $hdrIsA ? ' active' : '' ?>" data-pt-group title="<?= e($hdrLabel) ?>"><i class="bi bi-<?= $hdrIcon ?>"></i><span><?= $hdrLabel ?></span><i class="bi bi-chevron-down caret"></i>
            <?php if ($hdrKey === 'asi' && $notif['gecikenAsi']): ?><em class="dot-count"><?= $notif['gecikenAsi'] ?></em><?php endif; ?>
            <?php if ($hdrKey === 'randevu' && $notif['talep']): ?><em class="dot-count warn"><?= $notif['talep'] ?></em><?php endif; ?>
          </summary>
          <div class="pt-side-sub">
            <?php foreach ($hdrChildren as [$hdrCl, $hdrCi, $hdrCh]): ?>
              <a href="<?= $hdrCh ?>"><i class="bi bi-<?= $hdrCi ?>"></i><?= $hdrCl ?></a>
            <?php endforeach; ?>
          </div>
        </details>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>
  <div class="pt-side-foot">
    <a href="<?= url('') ?>" target="_blank" class="pt-side-link"><i class="bi bi-box-arrow-up-right"></i><span>Siteyi Görüntüle</span></a>
    <button class="pt-side-link d-none d-lg-flex" type="button" data-pt-side="mini" title="Menüyü daralt"><i class="bi bi-layout-sidebar-inset"></i><span>Menüyü daralt</span></button>
  </div>
</aside>
<div class="pt-side-backdrop" data-pt-side="close"></div>

<div class="pt-main">
  <header class="pt-topbar">
    <button class="pt-icon-btn d-lg-none" type="button" data-pt-side="open" aria-label="Menü"><i class="bi bi-list"></i></button>
    <form class="pt-search" action="<?= url('admin/ara.php') ?>">
      <i class="bi bi-search"></i>
      <input class="form-control" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Müşteri, hasta, telefon, çip no, ürün veya barkod ara…" autocomplete="off">
      <kbd class="d-none d-md-inline">/</kbd>
    </form>
    <div class="pt-top-actions">
      <a class="pt-quick d-none d-xl-inline-flex" href="<?= url('admin/satis.php') ?>"><i class="bi bi-cart-plus"></i> Hızlı Satış</a>
      <a class="pt-quick d-none d-xl-inline-flex" href="<?= url('admin/randevular.php?islem=yeni') ?>"><i class="bi bi-calendar-plus"></i> Randevu</a>
      <button class="pt-icon-btn js-money-toggle" type="button" title="Bakiyeleri gizle / göster"><i class="bi bi-eye on"></i><i class="bi bi-eye-slash off"></i></button>
      <a class="pt-icon-btn" href="<?= url('admin/whatsapp.php') ?>" title="WhatsApp kuyruğu"><i class="bi bi-whatsapp"></i><?php if ($notif['wa']): ?><span class="count"><?= $notif['wa'] ?></span><?php endif; ?></a>
      <div class="dropdown">
        <a class="pt-icon-btn" href="#" data-bs-toggle="dropdown" title="Bildirimler"><i class="bi bi-bell"></i><?php if ($hdrNotifTotal): ?><span class="count"><?= $hdrNotifTotal ?></span><?php endif; ?></a>
        <div class="dropdown-menu dropdown-menu-end pt-notif">
          <h6 class="dropdown-header">Bildirimler</h6>
          <a class="dropdown-item" href="<?= url('admin/randevular.php?durum=talep') ?>"><span class="ic bg-warning-subtle text-warning-emphasis"><i class="bi bi-globe2"></i></span><span class="flex-grow-1">Web randevu talebi</span><b><?= $notif['talep'] ?></b></a>
          <a class="dropdown-item" href="<?= url('admin/asilar.php?sekme=geciken') ?>"><span class="ic bg-danger-subtle text-danger-emphasis"><i class="bi bi-eyedropper"></i></span><span class="flex-grow-1">Geciken aşı</span><b><?= $notif['gecikenAsi'] ?></b></a>
          <a class="dropdown-item" href="<?= url('admin/urunler.php?kritik=1') ?>"><span class="ic bg-info-subtle text-info-emphasis"><i class="bi bi-box-seam"></i></span><span class="flex-grow-1">Kritik stok</span><b><?= $notif['stok'] ?></b></a>
          <a class="dropdown-item" href="<?= url('admin/whatsapp.php') ?>"><span class="ic bg-success-subtle text-success-emphasis"><i class="bi bi-whatsapp"></i></span><span class="flex-grow-1">Bekleyen WhatsApp</span><b><?= $notif['wa'] ?></b></a>
        </div>
      </div>
      <div class="dropdown">
        <a href="#" class="pt-user" data-bs-toggle="dropdown">
          <span class="avatar"><?= e(initials($hdrUser['name'])) ?></span>
          <span class="d-none d-md-block lh-sm"><b><?= e($hdrUser['name']) ?></b><small><?= e(['admin' => 'Yönetici', 'veteriner' => 'Veteriner Hekim', 'personel' => 'Personel'][$hdrUser['role']] ?? ucfirst((string)$hdrUser['role'])) ?></small></span>
          <i class="bi bi-chevron-down small d-none d-md-inline"></i>
        </a>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="<?= url('admin/kullanicilar.php?islem=profil') ?>"><i class="bi bi-person me-2"></i>Profilim</a></li>
          <li><a class="dropdown-item" href="<?= url('admin/ayarlar.php') ?>"><i class="bi bi-gear me-2"></i>Ayarlar</a></li>
          <li><a class="dropdown-item d-none" href="#" data-kopuk-show>🐱 Köpük'ü geri getir</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="<?= url('admin/logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i>Çıkış Yap</a></li>
        </ul>
      </div>
    </div>
  </header>
<main class="pt-content container-fluid">
<div class="pt-flash"><?= flashes() ?></div>
