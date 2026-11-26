<?php
require __DIR__ . '/app/bootstrap.php';

$name = setting('clinic_name', 'Patikum Veteriner Kliniği');
$webAppt = setting('web_appointments', '1') === '1';
$sent = false;
$formErr = null;

if (is_post() && post('form') === 'randevu' && $webAppt) {
    $last = $_SESSION['last_web_appt'] ?? 0;
    if (post('website') !== '' || time() - $last < 60) {
        $formErr = 'Lütfen biraz bekleyip tekrar deneyin.';
    } elseif (!post('kvkk')) {
        $formErr = 'Devam etmek için KVKK aydınlatma metnini onaylayın.';
    } elseif (mb_strlen((string)post('name')) < 3 || strlen(phone_norm(post('phone'))) < 12) {
        $formErr = 'Lütfen adınızı ve geçerli bir cep telefonu girin.';
    } else {
        $date = post('date') ?: date('Y-m-d', strtotime('+1 day'));
        if ($date < today()) $date = today();
        $time = preg_match('/^\d{2}:\d{2}$/', (string)post('time')) ? post('time') : '10:00';
        $type = in_array(post('type'), appointment_types(), true) ? post('type') : 'Muayene';
        $phone = (string)post('phone');
        $cust = null;
        foreach (rows('SELECT id, phone FROM customers WHERE active = 1') as $c) if (phone_norm($c['phone']) === phone_norm($phone)) { $cust = (int)$c['id']; break; }
        $id = insert('appointments', [
            'customer_id' => $cust, 'guest_name' => mb_substr((string)post('name'), 0, 150), 'guest_phone' => mb_substr($phone, 0, 30),
            'pet_info' => mb_substr(trim(post('pet_type') . ' - ' . post('pet_name'), ' -'), 0, 200), 'start_at' => "$date $time:00",
            'type' => $type, 'status' => 'talep', 'notes' => mb_substr((string)post('note'), 0, 1000), 'source' => 'web',
        ]);
        $tpl = wa_template('web_randevu_alindi');
        if ($tpl && $tpl['active'] && $tpl['auto_send']) wa_queue(null, $phone, 'web_randevu_alindi', ['musteri' => post('name'), 'tarih' => dt($date) . ' ' . $time], 'web:' . $id);
        $_SESSION['last_web_appt'] = time();
        $sent = true;
    }
}

$services = [
    ['Genel Muayene', 'Baştan kuyruğa detaylı fiziksel muayene, doğru tanı ve kişiye özel tedavi planı.', 'clipboard2-pulse', 'mint'],
    ['Aşılama', 'Türüne ve yaşına göre aşı takvimi; zamanı geldiğinde WhatsApp ile hatırlatırız.', 'shield-plus', 'rose'],
    ['Cerrahi', 'Kısırlaştırma ve yumuşak doku operasyonları; steril ameliyathane, güvenli anestezi.', 'bandaid', 'sky'],
    ['Laboratuvar', 'Hemogram, biyokimya ve hızlı test kitleriyle dakikalar içinde sonuç.', 'droplet-half', 'lilac'],
    ['Görüntüleme', 'Dijital röntgen ile kırık, yabancı cisim ve iç organ değerlendirmesi.', 'radioactive', 'sand'],
    ['Diş Bakımı', 'Diş taşı temizliği, diş çekimi ve ağız sağlığı kontrolleri.', 'emoji-smile', 'apricot'],
    ['Mikroçip & Pasaport', 'Kimliklendirme, pasaport ve yurt dışı çıkış evrakları.', 'upc-scan', 'mint'],
    ['Parazit Koruması', 'İç ve dış parazitlere karşı düzenli koruma programı.', 'bug', 'rose'],
];
$vacc = [];
foreach (['Kedi', 'Köpek'] as $sp) {
    $vacc[$sp] = rows('SELECT t.name, p.first_age_weeks, p.doses, p.interval_days, p.booster_days FROM vaccine_protocols p JOIN vaccine_types t ON t.id = p.vaccine_type_id JOIN species s ON s.id = p.species_id WHERE s.name = ? AND p.active = 1 ORDER BY p.sort', [$sp]);
}
$wa = setting('clinic_whatsapp');
$map = setting('clinic_map');
$heroImg = setting('site_hero_image');
$heroImg = ($heroImg && preg_match('#^https?://#i', $heroImg)) ? $heroImg : '';
$about = setting('site_about', $name . ', kedi ve köpeklerden kuşlara kadar tüm evcil dostlarımıza modern tıbbın imkânlarını şefkatle sunar. Dostunuzun tüm sağlık geçmişini dijital olarak saklıyor, aşı zamanlarını sizin yerinize takip ediyor ve WhatsApp üzerinden hatırlatıyoruz.');
$phone = setting('clinic_phone');
$tel = preg_replace('/[^0-9+]/', '', $phone);
$hoursFirst = trim(strtok((string)setting('clinic_hours'), "\n") ?: '');
$faqs = [
    ['Randevusuz gelebilir miyim?', 'Elbette. Ancak bekleme sürenizi kısaltmak için online randevu formunu veya WhatsApp hattımızı kullanmanızı öneririz. Acil durumlarda doğrudan arayabilirsiniz.'],
    ['Yavru kedi / köpeğimin aşılarına ne zaman başlamalıyım?', 'Genellikle 6–8. haftada iç-dış parazit uygulaması ile başlanır, karma aşılar 8. haftadan itibaren yapılır. İlk muayenede dostunuza özel aşı takvimi oluşturup her aşıdan önce size hatırlatıyoruz.'],
    ['Aşı ve işlem geçmişimi nereden görebilirim?', 'Müşteri panelimize telefon numaranız ve size iletilen şifre ile giriş yaparak tüm aşı karnesine, yapılan işlemlere ve hesap durumunuza 7/24 ulaşabilirsiniz.'],
    ['Kısırlaştırma için ne zaman getirmeliyim?', 'Kedilerde genellikle 5–6 ay, köpeklerde ırka göre 6–12 ay önerilir. Operasyon öncesi kontrol muayenesi ve kan tahlili ile güvenliği sağlıyoruz.'],
];
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($name) ?> · <?= e(setting('clinic_slogan', 'Veteriner Kliniği')) ?></title>
<meta name="description" content="<?= e($name) ?> — muayene, aşılama, cerrahi, laboratuvar, bakım ve online randevu. Patili dostlarınız için sevgiyle, bilimle.">
<meta name="theme-color" content="#0f3336">
<meta property="og:title" content="<?= e($name) ?>">
<meta property="og:description" content="<?= e(setting('clinic_slogan', 'Patili dostlarınız için sevgiyle, bilimle.')) ?>">
<meta property="og:type" content="website">
<?php if ($heroImg): ?><meta property="og:image" content="<?= e($heroImg) ?>"><?php endif; ?>
<link rel="icon" href="<?= e(favicon_url()) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="<?= asset('vendor/bootstrap.min.css') ?>" rel="stylesheet">
<link href="<?= asset('vendor/bootstrap-icons.min.css') ?>" rel="stylesheet">
<link href="<?= asset('css/site.css') ?>" rel="stylesheet">
<script type="application/ld+json"><?= json_encode(['@context' => 'https://schema.org', '@type' => 'VeterinaryCare', 'name' => $name, 'telephone' => $phone, 'address' => setting('clinic_address'), 'url' => site_url()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
</head>
<body class="site">
<div class="topline d-none d-md-block">
  <div class="container d-flex justify-content-between align-items-center">
    <span><i class="bi bi-geo-alt"></i> <?= e(strtok((string)setting('clinic_address'), "\n")) ?></span>
    <span class="d-flex gap-3">
      <?php if ($hoursFirst): ?><span><i class="bi bi-clock"></i> <?= e($hoursFirst) ?></span><?php endif; ?>
      <?php if ($phone): ?><a href="tel:<?= e($tel) ?>"><i class="bi bi-telephone"></i> <?= e($phone) ?></a><?php endif; ?>
    </span>
  </div>
</div>
<nav class="site-nav navbar navbar-expand-lg" id="top">
  <div class="container">
    <a class="brand" href="#top"><?php if ($logo = clinic_logo()): ?><img class="brand-logo" src="<?= e($logo) ?>" alt="<?= e($name) ?>"><?php else: ?><img class="brand-badge" src="<?= asset('img/patikum-logo-192.png') ?>" alt="Patikum Veteriner Kliniği"><span>patikum<small>veteriner kliniği</small></span><?php endif; ?></a>
    <button class="navbar-toggler border-0 shadow-none" data-bs-toggle="collapse" data-bs-target="#nv" aria-label="Menü"><i class="bi bi-list fs-2"></i></button>
    <div class="collapse navbar-collapse" id="nv">
      <ul class="navbar-nav mx-auto gap-lg-1">
        <li class="nav-item"><a class="nav-link" href="#hizmetler">Hizmetler</a></li>
        <li class="nav-item"><a class="nav-link" href="#hakkimizda">Hakkımızda</a></li>
        <li class="nav-item"><a class="nav-link" href="#asi">Aşı Programı</a></li>
        <li class="nav-item"><a class="nav-link" href="#sss">Sık Sorulanlar</a></li>
        <li class="nav-item"><a class="nav-link" href="#iletisim">İletişim</a></li>
      </ul>
      <div class="d-flex gap-2 py-2 py-lg-0">
        <a href="<?= url('panel/') ?>" class="btn btn-soft"><i class="bi bi-person-heart"></i> Müşteri Paneli</a>
        <?php if ($webAppt): ?><a href="#randevu" class="btn btn-brand">Randevu Al <i class="bi bi-arrow-right"></i></a><?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<header class="hero">
  <div class="container">
    <div class="row align-items-center g-4 g-lg-5">
      <div class="col-lg-6 hero-copy">
        <span class="eyebrow-pill"><span class="dot"></span> <?= e(setting('clinic_slogan', 'Patili dostlarınız için sevgiyle, bilimle.')) ?></span>
        <h1>Dostunuz için <em>en iyi bakım</em>, en yakın klinikte.</h1>
        <p class="lead"><?= e($name) ?>; muayeneden aşılamaya, cerrahiden laboratuvara kadar kedi, köpek ve tüm evcil dostlarınız için modern ve şefkatli veteriner hekimliği sunar.</p>
        <div class="d-flex flex-wrap gap-2 mt-4">
          <?php if ($webAppt): ?><a href="#randevu" class="btn btn-brand btn-lg"><i class="bi bi-calendar-heart"></i> Online Randevu</a><?php endif; ?>
          <?php if ($wa): ?><a href="<?= e(wa_link($wa, 'Merhaba, bilgi almak istiyorum.')) ?>" target="_blank" rel="noopener" class="btn btn-wa btn-lg"><i class="bi bi-whatsapp"></i> WhatsApp'tan Yazın</a><?php endif; ?>
        </div>
        <ul class="hero-trust">
          <li><i class="bi bi-journal-medical"></i> Dijital sağlık karnesi</li>
          <li><i class="bi bi-bell"></i> Aşı & randevu hatırlatma</li>
          <li><i class="bi bi-heart"></i> Kedi, köpek & egzotik</li>
        </ul>
      </div>
      <div class="col-lg-6">
        <div class="hero-visual">
          <div class="hv-card<?= $heroImg ? ' has-img' : '' ?>">
            <?php if ($heroImg): ?>
              <img src="<?= e($heroImg) ?>" alt="<?= e($name) ?>" loading="eager">
            <?php else: ?>
              <div class="hv-art" aria-hidden="true">
                <span class="ring r1"></span><span class="ring r2"></span><span class="ring r3"></span>
                <img class="logo-big" src="<?= e(brand_logo(true)) ?>" alt="<?= e($name) ?>">
                <span class="pet pet-a">🐶</span><span class="pet pet-b">🐱</span>
              </div>
            <?php endif; ?>
          </div>
          <div class="float-card fc1"><span class="ic mint"><i class="bi bi-shield-check"></i></span><div><b>Aşı takvimi hazır</b><small>Zamanı gelince hatırlatırız</small></div></div>
          <div class="float-card fc2"><span class="ic wa"><i class="bi bi-whatsapp"></i></span><div><b>WhatsApp hattı</b><small>Sorularınıza hızlı yanıt</small></div></div>
          <?php if ($hoursFirst): ?><div class="float-card fc3"><span class="ic apricot"><i class="bi bi-clock"></i></span><div><b>Çalışma saatleri</b><small><?= e($hoursFirst) ?></small></div></div><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</header>

<div class="ticker" aria-hidden="true"><div class="ticker-track">
  <?php for ($k = 0; $k < 2; $k++): foreach ($services as [$t, , $i]): ?><span><i class="bi bi-<?= $i ?>"></i> <?= e($t) ?></span><?php endforeach; endfor; ?>
</div></div>

<section class="section" id="hizmetler">
  <div class="container">
    <div class="sec-head">
      <div><span class="eyebrow">Hizmetlerimiz</span><h2>Dostunuzun tüm ihtiyaçları <em>tek çatı</em> altında</h2></div>
      <p>Deneyimli hekim kadromuz ve modern ekipmanlarımızla koruyucu hekimlikten cerrahiye kadar her adımda yanınızdayız.</p>
    </div>
    <div class="row g-3 g-lg-4">
      <?php foreach ($services as $n => [$t, $d, $i, $tone]): ?>
        <div class="col-sm-6 col-lg-3"><article class="svc tone-<?= $tone ?>">
          <span class="num"><?= sprintf('%02d', $n + 1) ?></span>
          <div class="ic"><i class="bi bi-<?= $i ?>"></i></div>
          <h3><?= e($t) ?></h3><p><?= e($d) ?></p>
        </article></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section about" id="hakkimizda">
  <div class="container"><div class="row g-4 g-lg-5 align-items-center">
    <div class="col-lg-5">
      <div class="about-visual">
        <div class="av-main"><img src="<?= asset('img/favicon.svg') ?>" alt=""><span class="av-logo"><img src="<?= e(brand_logo()) ?>" alt=""></span><b>Her pati<br>bizim için özel.</b></div>
        <div class="av-badge"><i class="bi bi-heart-pulse"></i><span>Koruyucu<br>hekimlik</span></div>
      </div>
    </div>
    <div class="col-lg-7">
      <span class="eyebrow">Neden <?= e(explode(' ', $name)[0]) ?>?</span>
      <h2>Sağlıklı bir yaşam, <em>düzenli takiple</em> başlar.</h2>
      <p class="text-muted fs-6 mt-3"><?= nl2br(e($about)) ?></p>
      <div class="row g-3 mt-2">
        <?php foreach ([
            ['heart-pulse', 'Uzman ve şefkatli ekip', 'Dostunuzun stresini azaltan, sabırlı hekimler.'],
            ['phone', 'Dijital sağlık karnesi', 'Tüm geçmişe müşteri panelinden 7/24 erişim.'],
            ['whatsapp', 'Otomatik hatırlatmalar', 'Aşı ve randevu zamanında WhatsApp mesajı.'],
            ['shield-check', 'Steril ve modern klinik', 'Güncel cihazlar, hijyenik ameliyathane.'],
        ] as [$i, $t, $d]): ?>
          <div class="col-sm-6"><div class="feat"><span class="ic"><i class="bi bi-<?= $i ?>"></i></span><div><h4><?= $t ?></h4><p><?= $d ?></p></div></div></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div></div>
</section>

<section class="section steps-sec">
  <div class="container">
    <div class="sec-head center"><div><span class="eyebrow">Nasıl çalışıyoruz?</span><h2>Üç adımda <em>kolay</em> veteriner deneyimi</h2></div></div>
    <div class="row g-4">
      <?php foreach ([
          ['calendar-check', 'Randevunuzu alın', 'Online formdan, WhatsApp\'tan ya da telefonla size uygun saati seçin.'],
          ['clipboard2-heart', 'Muayene & tedavi', 'Hekimimiz dostunuzu detaylıca inceler, tedavi ve aşı planını oluşturur.'],
          ['bell', 'Biz takip edelim', 'Sonraki aşı ve kontrolleri biz hatırlatırız, siz sadece sevmeye devam edin.'],
      ] as $n => [$i, $t, $d]): ?>
        <div class="col-md-4"><div class="step-card"><span class="step-no"><?= $n + 1 ?></span><i class="bi bi-<?= $i ?>"></i><h3><?= $t ?></h3><p><?= $d ?></p></div></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($vacc['Kedi'] || $vacc['Köpek']): ?>
<section class="section vacc-sec" id="asi">
  <div class="container">
    <div class="sec-head">
      <div><span class="eyebrow">Aşı Programı</span><h2>Temel <em>aşı takvimi</em></h2></div>
      <p>Genel bilgilendirme amaçlıdır. Hekimimiz dostunuzun yaşına ve sağlık durumuna göre kişisel takvim oluşturur ve her aşıdan önce size hatırlatır.</p>
    </div>
    <ul class="nav vacc-tabs mb-3" role="tablist">
      <?php $first = true; foreach (['Kedi' => '🐱', 'Köpek' => '🐶'] as $sp => $ic): if (!$vacc[$sp]) continue; ?>
        <li class="nav-item"><button class="nav-link<?= $first ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#vac-<?= ($sp === 'Kedi' ? 'kedi' : 'kopek') ?>" type="button"><?= $ic ?> <?= $sp ?></button></li>
      <?php $first = false; endforeach; ?>
    </ul>
    <div class="tab-content">
      <?php $first = true; foreach (['Kedi', 'Köpek'] as $sp): if (!$vacc[$sp]) continue; ?>
        <div class="tab-pane fade<?= $first ? ' show active' : '' ?>" id="vac-<?= ($sp === 'Kedi' ? 'kedi' : 'kopek') ?>">
          <div class="vacc-grid">
            <?php foreach ($vacc[$sp] as $v): ?>
              <div class="vacc-item">
                <div class="wk"><b><?= (int)$v['first_age_weeks'] ?>.</b><small>hafta</small></div>
                <div class="flex-grow-1"><h4><?= e($v['name']) ?></h4>
                  <p><?= (int)$v['doses'] > 1 ? (int)$v['doses'] . ' doz · ' . (int)$v['interval_days'] . ' gün arayla' : 'Tek doz' ?></p></div>
                <?php if ($v['booster_days']): ?><span class="rep"><i class="bi bi-arrow-repeat"></i> <?= (int)$v['booster_days'] >= 365 ? 'Yılda bir' : 'Her ' . (int)$v['booster_days'] . ' gün' ?></span><?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php $first = false; endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section pt-0">
  <div class="container"><div class="promo"><div class="row g-4 g-lg-5 align-items-center">
    <div class="col-lg-6">
      <span class="eyebrow light">Müşteri Paneli</span>
      <h2>Dostunuzun sağlık karnesi <em>cebinizde</em></h2>
      <ul class="checks">
        <li><i class="bi bi-check2"></i> Yaklaşan ve yapılan aşıları görün</li>
        <li><i class="bi bi-check2"></i> Muayene ve tedavi geçmişine ulaşın</li>
        <li><i class="bi bi-check2"></i> Ödemelerinizi ve bakiyenizi takip edin</li>
        <li><i class="bi bi-check2"></i> Panelden randevu talebi oluşturun</li>
      </ul>
      <a href="<?= url('panel/') ?>" class="btn btn-light btn-lg mt-2"><i class="bi bi-box-arrow-in-right"></i> Panele Giriş Yap</a>
      <p class="small mt-3 opacity-75">Giriş bilgileriniz kliniğimiz tarafından WhatsApp ile iletilir.</p>
    </div>
    <div class="col-lg-6"><div class="phone">
      <div class="phone-in">
        <div class="ph-top"><b>Merhaba 👋</b><span class="chip ok">Bakiye: 0,00 ₺</span></div>
        <div class="ph-row"><span class="av">🐱</span><div class="flex-grow-1"><b>Minnoş</b><small>Kedi · 1 yaş</small></div><span class="chip rose">Karma aşı · 3 gün</span></div>
        <div class="ph-row"><span class="av sky">🐶</span><div class="flex-grow-1"><b>Karamel</b><small>Köpek · 2 yaş</small></div><span class="chip">Kontrol · 12 Ara</span></div>
        <div class="ph-row"><span class="av mint"><i class="bi bi-receipt"></i></span><div class="flex-grow-1"><b>Son işlem</b><small>Genel muayene · İç parazit</small></div></div>
        <div class="ph-btn"><i class="bi bi-calendar-plus"></i> Randevu talebi oluştur</div>
      </div>
    </div></div>
  </div></div></div>
</section>

<section class="section faq-sec" id="sss">
  <div class="container"><div class="row g-4 g-lg-5">
    <div class="col-lg-4">
      <span class="eyebrow">Sık Sorulanlar</span>
      <h2>Aklınıza takılanlar</h2>
      <p class="text-muted mt-3">Burada cevabını bulamadığınız her soru için bize WhatsApp'tan yazabilir veya arayabilirsiniz.</p>
      <?php if ($wa): ?><a class="btn btn-wa mt-2" href="<?= e(wa_link($wa, 'Merhaba, bir sorum var.')) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> Soru sor</a><?php endif; ?>
    </div>
    <div class="col-lg-8"><div class="accordion faq" id="faq">
      <?php foreach ($faqs as $n => [$q, $a]): ?>
        <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button<?= $n ? ' collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#f<?= $n ?>"><?= e($q) ?></button></h3>
          <div id="f<?= $n ?>" class="accordion-collapse collapse<?= $n ? '' : ' show' ?>" data-bs-parent="#faq"><div class="accordion-body"><?= e($a) ?></div></div></div>
      <?php endforeach; ?>
    </div></div>
  </div></div>
</section>

<?php if ($webAppt): ?>
<section class="section appt-sec" id="randevu">
  <div class="container"><div class="appt-wrap"><div class="row g-0">
    <div class="col-lg-5 appt-side">
      <span class="eyebrow light">Online Randevu</span>
      <h2>Size en uygun <em>zamanı</em> seçin</h2>
      <p class="opacity-75">Formu doldurun, ekibimiz randevunuzu onaylamak için kısa süre içinde sizinle iletişime geçsin.</p>
      <div class="side-item"><i class="bi bi-clock"></i><div><b>Çalışma Saatleri</b><span><?= nl2br(e(setting('clinic_hours'))) ?></span></div></div>
      <?php if ($phone): ?><div class="side-item"><i class="bi bi-telephone"></i><div><b>Acil durumlar için</b><a href="tel:<?= e($tel) ?>"><?= e($phone) ?></a></div></div><?php endif; ?>
      <img class="side-paw" src="<?= asset('img/favicon.svg') ?>" alt="">
    </div>
    <div class="col-lg-7 appt-form">
      <?php if ($sent): ?>
        <div class="text-center py-5"><div class="done-ic"><i class="bi bi-check2"></i></div><h3>Talebiniz alındı!</h3><p class="text-muted">En kısa sürede onay için sizinle iletişime geçeceğiz. Teşekkür ederiz 🐾</p></div>
      <?php else: ?>
        <?php if ($formErr): ?><div class="alert alert-danger"><?= e($formErr) ?></div><?php endif; ?>
        <form method="post" action="#randevu" class="row g-3">
          <?= csrf_field() ?><input type="hidden" name="form" value="randevu">
          <input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
          <div class="col-md-6"><label class="form-label">Adınız Soyadınız</label><input name="name" class="form-control" required value="<?= e(post('name')) ?>"></div>
          <div class="col-md-6"><label class="form-label">Cep Telefonu</label><input name="phone" type="tel" class="form-control" required placeholder="05XXXXXXXXX" maxlength="16" value="<?= e(post('phone')) ?>"></div>
          <div class="col-md-6"><label class="form-label">Dostunuzun Türü</label><select name="pet_type" class="form-select"><?php foreach (species_list() as $s): ?><option><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-6"><label class="form-label">Dostunuzun Adı</label><input name="pet_name" class="form-control" value="<?= e(post('pet_name')) ?>"></div>
          <div class="col-md-4"><label class="form-label">Hizmet</label><select name="type" class="form-select"><?php foreach (appointment_types() as $t): ?><option><?= e($t) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-4"><label class="form-label">Tarih</label><input type="date" name="date" class="form-control" min="<?= today() ?>" value="<?= e(post('date', date('Y-m-d', strtotime('+1 day')))) ?>" required></div>
          <div class="col-md-4"><label class="form-label">Saat</label><select name="time" class="form-select"><?php for ($h = 9; $h <= 19; $h++) foreach (['00', '30'] as $m): ?><option><?= sprintf('%02d:%s', $h, $m) ?></option><?php endforeach; ?></select></div>
          <div class="col-12"><label class="form-label">Notunuz</label><textarea name="note" class="form-control" rows="2" placeholder="Şikayet veya eklemek istedikleriniz"><?= e(post('note')) ?></textarea></div>
          <div class="col-12"><div class="form-check"><input type="checkbox" class="form-check-input" name="kvkk" value="1" id="kvkk" required><label class="form-check-label small text-muted" for="kvkk">Kişisel verilerimin randevu ve bilgilendirme amacıyla işlenmesini ve WhatsApp üzerinden iletişime geçilmesini kabul ediyorum (KVKK).</label></div></div>
          <div class="col-12"><button class="btn btn-brand btn-lg w-100"><i class="bi bi-calendar-check"></i> Randevu Talebi Gönder</button></div>
        </form>
      <?php endif; ?>
    </div>
  </div></div></div>
</section>
<?php endif; ?>

<section class="section contact-sec" id="iletisim">
  <div class="container"><div class="row g-4">
    <div class="col-lg-5">
      <span class="eyebrow">İletişim</span>
      <h2 class="mb-4">Bize <em>ulaşın</em></h2>
      <div class="c-list">
        <div class="c-item"><span class="ic"><i class="bi bi-geo-alt"></i></span><div><b>Adres</b><span><?= nl2br(e(setting('clinic_address'))) ?></span></div></div>
        <?php if ($phone): ?><div class="c-item"><span class="ic"><i class="bi bi-telephone"></i></span><div><b>Telefon</b><a href="tel:<?= e($tel) ?>"><?= e($phone) ?></a></div></div><?php endif; ?>
        <?php if ($wa): ?><div class="c-item"><span class="ic wa"><i class="bi bi-whatsapp"></i></span><div><b>WhatsApp</b><a href="<?= e(wa_link($wa)) ?>" target="_blank" rel="noopener"><?= e(phone_fmt($wa)) ?></a></div></div><?php endif; ?>
        <?php if ($em = setting('clinic_email')): ?><div class="c-item"><span class="ic"><i class="bi bi-envelope"></i></span><div><b>E-posta</b><a href="mailto:<?= e($em) ?>"><?= e($em) ?></a></div></div><?php endif; ?>
        <?php if ($ig = setting('clinic_instagram')): ?><div class="c-item"><span class="ic ig"><i class="bi bi-instagram"></i></span><div><b>Instagram</b><a href="https://instagram.com/<?= e(ltrim($ig, '@')) ?>" target="_blank" rel="noopener">@<?= e(ltrim($ig, '@')) ?></a></div></div><?php endif; ?>
      </div>
    </div>
    <div class="col-lg-7"><div class="map">
      <?php if ($map && str_starts_with($map, 'https://www.google.com/maps/embed')): ?><iframe src="<?= e($map) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Harita"></iframe>
      <?php else: ?><div class="map-empty"><i class="bi bi-map"></i><div>Harita, yönetim panelinde Ayarlar &gt; Genel bölümünden eklenebilir.</div></div><?php endif; ?>
    </div></div>
  </div></div>
</section>

<footer class="site-foot">
  <div class="container">
    <div class="foot-cta">
      <div><h3>Dostunuzun sağlığı için bugün randevu alın.</h3><p>Sorularınız için WhatsApp hattımız her zaman açık.</p></div>
      <div class="d-flex gap-2 flex-wrap"><?php if ($webAppt): ?><a href="#randevu" class="btn btn-brand btn-lg">Randevu Al</a><?php endif; ?><?php if ($wa): ?><a href="<?= e(wa_link($wa)) ?>" class="btn btn-outline-light btn-lg" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> WhatsApp</a><?php endif; ?></div>
    </div>
    <div class="row g-4">
      <div class="col-md-5"><a class="brand light mb-3" href="#top"><?php if ($logo): ?><span class="logo-pill"><img class="brand-logo" src="<?= e($logo) ?>" alt="<?= e($name) ?>"></span><?php else: ?><img class="brand-badge" src="<?= asset('img/patikum-logo-192.png') ?>" alt="Patikum Veteriner Kliniği"><span>patikum<small>veteriner kliniği</small></span><?php endif; ?></a><p class="small"><?= e(setting('clinic_slogan')) ?></p></div>
      <div class="col-6 col-md-3"><b>Hızlı Erişim</b><ul class="list-unstyled small mt-2 d-grid gap-1"><li><a href="#hizmetler">Hizmetler</a></li><li><a href="#asi">Aşı Programı</a></li><li><a href="#randevu">Randevu</a></li><li><a href="<?= url('panel/') ?>">Müşteri Paneli</a></li></ul></div>
      <div class="col-6 col-md-4"><b>Çalışma Saatleri</b><p class="small mt-2"><?= nl2br(e(setting('clinic_hours'))) ?></p></div>
    </div>
    <div class="foot-bottom"><span>© <?= date('Y') ?> <?= e($name) ?>. Tüm hakları saklıdır.</span><a href="<?= url('admin/') ?>">Personel Girişi</a></div>
  </div>
</footer>
<?php if ($wa): ?><a class="wa-float" href="<?= e(wa_link($wa, 'Merhaba, bilgi almak istiyorum.')) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a><?php endif; ?>
<script src="<?= asset('vendor/bootstrap.bundle.min.js') ?>"></script>
<?= kopuk_html('site', ['Merhaba! Ben Köpük, ' . $name . '\'nin kedisiyim 🐾', $webAppt ? 'Online randevu almak çok kolay, aşağıdaki formu doldur 📅' : null, 'Aşı zamanlarını biz hatırlatıyoruz 💉', $wa ? 'WhatsApp\'tan bize yazabilirsin 💬' : null, 'Dostuna benden selam söyle 🐶🐱', 'Mrrr… burası çok güzel 😺']) ?>
<script>
document.querySelectorAll('.site-nav .nav-link, .site-nav .btn').forEach(a => a.addEventListener('click', () => { const c = document.getElementById('nv'); if (c.classList.contains('show')) bootstrap.Collapse.getOrCreateInstance(c).hide(); }));
const nav = document.querySelector('.site-nav'); const onS = () => nav.classList.toggle('scrolled', window.scrollY > 30); onS(); window.addEventListener('scroll', onS, { passive: true });
</script>
</body>
</html>
