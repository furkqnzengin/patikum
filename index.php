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
    ['Genel Muayene', 'Detaylı fiziksel muayene, tanı ve tedavi planlaması.', 'clipboard2-pulse', '#ccfbf1', '#0f766e'],
    ['Aşılama', 'Türüne ve yaşına özel aşı takvimi, otomatik hatırlatmalar.', 'eyedropper', '#fce7f3', '#db2777'],
    ['Cerrahi Operasyon', 'Kısırlaştırma ve yumuşak doku operasyonları, steril ortam.', 'bandaid', '#e0f2fe', '#0369a1'],
    ['Laboratuvar', 'Kan tahlili, biyokimya ve hızlı test kitleri ile hızlı sonuç.', 'droplet-half', '#ede9fe', '#6d28d9'],
    ['Diş Bakımı', 'Diş taşı temizliği, ağız ve diş sağlığı kontrolü.', 'emoji-smile', '#fef9c3', '#a16207'],
    ['Tıraş & Bakım', 'Banyo, tüy kesimi, tırnak bakımı ve kulak temizliği.', 'scissors', '#ffedd5', '#c2410c'],
    ['Mikroçip & Pasaport', 'Kimliklendirme, yurt dışı çıkış evrakları ve pasaport.', 'upc-scan', '#dcfce7', '#15803d'],
    ['Parazit Koruması', 'İç ve dış parazit uygulamaları, düzenli koruma programı.', 'shield-check', '#fee2e2', '#b91c1c'],
];
$vacc = [];
foreach (['Kedi', 'Köpek'] as $sp) {
    $vacc[$sp] = rows('SELECT t.name, p.first_age_weeks, p.doses, p.interval_days, p.booster_days FROM vaccine_protocols p JOIN vaccine_types t ON t.id = p.vaccine_type_id JOIN species s ON s.id = p.species_id WHERE s.name = ? AND p.active = 1 ORDER BY p.sort', [$sp]);
}
$wa = setting('clinic_whatsapp');
$map = setting('clinic_map');
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($name) ?> · <?= e(setting('clinic_slogan', 'Veteriner Kliniği')) ?></title>
<meta name="description" content="<?= e($name) ?> — muayene, aşılama, cerrahi, laboratuvar, bakım ve online randevu. Patili dostlarınız için sevgiyle, bilimle.">
<meta property="og:title" content="<?= e($name) ?>">
<meta property="og:type" content="website">
<link rel="icon" href="<?= asset('img/favicon.svg') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= asset('css/site.css') ?>" rel="stylesheet">
<script type="application/ld+json"><?= json_encode(['@context' => 'https://schema.org', '@type' => 'VeterinaryCare', 'name' => $name, 'telephone' => setting('clinic_phone'), 'address' => setting('clinic_address'), 'url' => site_url()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
</head>
<body>
<nav class="site-nav navbar navbar-expand-lg">
  <div class="container">
    <a class="brand" href="#"><img src="<?= asset('img/favicon.svg') ?>" alt=""> patikum</a>
    <button class="navbar-toggler border-0" data-bs-toggle="collapse" data-bs-target="#nv"><i class="bi bi-list fs-2"></i></button>
    <div class="collapse navbar-collapse" id="nv">
      <ul class="navbar-nav mx-auto gap-lg-2">
        <li class="nav-item"><a class="nav-link" href="#hizmetler">Hizmetler</a></li>
        <li class="nav-item"><a class="nav-link" href="#hakkimizda">Hakkımızda</a></li>
        <li class="nav-item"><a class="nav-link" href="#asi">Aşı Takvimi</a></li>
        <li class="nav-item"><a class="nav-link" href="#iletisim">İletişim</a></li>
      </ul>
      <div class="d-flex gap-2 py-2">
        <a href="<?= url('panel/') ?>" class="btn btn-ghost"><i class="bi bi-person-heart"></i> Müşteri Paneli</a>
        <?php if ($webAppt): ?><a href="#randevu" class="btn btn-coral">Randevu Al</a><?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<header class="hero">
  <span class="paw-bg" style="top:12%;left:4%">🐾</span><span class="paw-bg" style="bottom:10%;left:45%">🐾</span>
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <span class="badge rounded-pill text-bg-light border px-3 py-2 mb-3 fw-bold"><i class="bi bi-heart-fill text-danger"></i> <?= e(setting('clinic_slogan', 'Patili dostlarınız için sevgiyle, bilimle.')) ?></span>
        <h1>Dostunuzun sağlığı <span>bizim için</span> bir aile meselesi.</h1>
        <p class="lead my-4"><?= e($name) ?>; muayeneden aşılamaya, cerrahiden bakıma kadar kedi, köpek ve tüm evcil dostlarınız için modern ve şefkatli veteriner hizmeti sunar.</p>
        <div class="d-flex flex-wrap gap-2">
          <?php if ($webAppt): ?><a href="#randevu" class="btn btn-coral btn-lg"><i class="bi bi-calendar-heart"></i> Online Randevu</a><?php endif; ?>
          <?php if ($wa): ?><a href="<?= e(wa_link($wa, 'Merhaba, bilgi almak istiyorum.')) ?>" target="_blank" rel="noopener" class="btn btn-ghost btn-lg"><i class="bi bi-whatsapp"></i> WhatsApp'tan Yazın</a><?php endif; ?>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="hero-art">
          <div class="blob"></div>
          <div class="pet">🐶</div>
          <div class="chip c1"><i class="bi bi-calendar2-check text-success"></i> Aşı takviminiz hazır!</div>
          <div class="chip c2"><span style="font-size:1.6rem">🐱</span> Luna'nın kontrolü <br>yarın 14:30</div>
          <div class="chip c3"><i class="bi bi-whatsapp text-success"></i> Otomatik hatırlatma</div>
        </div>
      </div>
    </div>
  </div>
</header>

<div class="container">
  <div class="stat-strip row text-center g-3">
    <div class="col-6 col-md-3"><div class="n"><i class="bi bi-journal-medical"></i></div><div class="l">Dijital Sağlık Karnesi</div></div>
    <div class="col-6 col-md-3"><div class="n"><i class="bi bi-whatsapp"></i></div><div class="l">Aşı & Randevu Hatırlatma</div></div>
    <div class="col-6 col-md-3"><div class="n"><i class="bi bi-calendar-heart"></i></div><div class="l">Online Randevu</div></div>
    <div class="col-6 col-md-3"><div class="n">🐾</div><div class="l">Kedi, Köpek & Egzotik</div></div>
  </div>
</div>

<section class="section" id="hizmetler">
  <div class="container">
    <div class="section-title"><div class="eyebrow">Hizmetlerimiz</div><h2>Tüm ihtiyaçlar için tek adres</h2><p>Deneyimli hekim kadromuz ve modern ekipmanlarımızla dostlarınızın her ihtiyacına yanıt veriyoruz.</p></div>
    <div class="row g-4">
      <?php foreach ($services as [$t, $d, $i, $bg, $c]): ?>
        <div class="col-md-6 col-lg-3"><div class="service"><div class="ic" style="background:<?= $bg ?>;color:<?= $c ?>"><i class="bi bi-<?= $i ?>"></i></div><h3><?= $t ?></h3><p><?= $d ?></p></div></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section pt-0" id="hakkimizda">
  <div class="container"><div class="row g-5 align-items-stretch">
    <div class="col-lg-5"><div class="about-card">
      <div class="eyebrow fw-bold text-uppercase small opacity-75">Neden Patikum?</div>
      <h2 class="mt-2">Her pati bizim için özel.</h2>
      <p class="opacity-75 mt-3">Dostunuzun tüm sağlık geçmişini dijital olarak tutuyor, aşı zamanlarını sizin yerinize takip ediyor ve WhatsApp üzerinden hatırlatıyoruz. Siz sadece sevmeye devam edin.</p>
      <div class="big">🐾</div>
    </div></div>
    <div class="col-lg-7"><div class="row">
      <?php foreach ([
          ['heart-pulse', 'Uzman ve şefkatli ekip', 'Dostunuzun stresini azaltan, sabırlı ve deneyimli veteriner hekimler.'],
          ['phone', 'Dijital sağlık karnesi', 'Tüm muayene, aşı ve tedavi geçmişine müşteri panelinden 7/24 ulaşın.'],
          ['whatsapp', 'WhatsApp hatırlatmaları', 'Aşı ve randevu zamanı geldiğinde size otomatik mesaj gönderiyoruz.'],
          ['shield-plus', 'Steril ve modern klinik', 'Güncel cihazlar, hijyenik ameliyathane ve laboratuvar altyapısı.'],
          ['cash-coin', 'Şeffaf fiyatlandırma', 'Yapılan her işlem ve ödeme panelinizde anında görünür.'],
          ['house-heart', 'Bakım & pet shop', 'Mama, aksesuar ve bakım ürünlerinde güvenilir seçenekler.'],
      ] as [$i, $t, $d]): ?>
        <div class="col-md-6"><div class="feature"><div class="ic"><i class="bi bi-<?= $i ?>"></i></div><div><h4><?= $t ?></h4><p><?= $d ?></p></div></div></div>
      <?php endforeach; ?>
    </div></div>
  </div></div>
</section>

<section class="section" id="asi" style="background:var(--cream)">
  <div class="container">
    <div class="section-title"><div class="eyebrow">Aşı Takvimi</div><h2>Temel aşı programı</h2><p>Aşağıdaki program genel bilgilendirme amaçlıdır. Dostunuzun yaşına ve sağlık durumuna göre hekimimiz kişisel takvim oluşturur ve her aşıdan önce size hatırlatır.</p></div>
    <div class="row g-4">
      <?php foreach (['Kedi' => ['🐱', '#f59e0b'], 'Köpek' => ['🐶', '#0ea5e9']] as $sp => [$ic, $col]): if (!$vacc[$sp]) continue; ?>
        <div class="col-lg-6"><div class="vacc-table"><div class="head" style="background:<?= $col ?>"><?= $ic ?> <?= $sp ?> Aşı Programı</div><ul>
          <?php foreach ($vacc[$sp] as $v): ?>
            <li><span class="fw-bold"><?= e($v['name']) ?></span><span><?= (int)$v['first_age_weeks'] ?>. haftadan itibaren<?= (int)$v['doses'] > 1 ? ', ' . (int)$v['doses'] . ' doz (' . (int)$v['interval_days'] . ' gün ara)' : '' ?><?= $v['booster_days'] ? '<br>Tekrar: ' . ((int)$v['booster_days'] >= 365 ? 'yılda bir' : 'her ' . (int)$v['booster_days'] . ' günde') : '' ?></span></li>
          <?php endforeach; ?>
        </ul></div></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container"><div class="panel-promo"><div class="row g-5 align-items-center">
    <div class="col-lg-6">
      <div class="section-title text-start mb-3 mx-0"><div class="eyebrow">Müşteri Paneli</div><h2>Dostunuzun her şeyi cebinizde</h2></div>
      <ul class="list-unstyled fs-6">
        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Yaklaşan ve yapılan aşıları görün</li>
        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Muayene ve tedavi geçmişine ulaşın</li>
        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Ödemelerinizi ve kalan bakiyenizi takip edin</li>
        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Panelden randevu talebi oluşturun</li>
      </ul>
      <a href="<?= url('panel/') ?>" class="btn btn-teal btn-lg mt-2"><i class="bi bi-box-arrow-in-right"></i> Panele Giriş Yap</a>
      <p class="small text-muted mt-2">Giriş bilgileriniz kliniğimiz tarafından WhatsApp ile iletilir.</p>
    </div>
    <div class="col-lg-6"><div class="mock">
      <div class="d-flex justify-content-between align-items-center mb-2"><b>Merhaba Ayşe 👋</b><span class="badge text-bg-success">Bakiye: 0,00 ₺</span></div>
      <div class="row-i"><span style="font-size:2rem">🐱</span><div class="flex-grow-1"><b>Luna</b><div class="small text-muted">British Shorthair · 1 yaş</div></div><span class="badge rounded-pill" style="background:#fce7f3;color:#db2777">Karma aşı · 3 gün</span></div>
      <div class="row-i"><span style="font-size:2rem">🐶</span><div class="flex-grow-1"><b>Max</b><div class="small text-muted">Golden Retriever · 2 yaş</div></div><span class="badge rounded-pill text-bg-light">Kontrol · 12 Ara</span></div>
      <div class="row-i border-0"><i class="bi bi-receipt fs-4 text-primary"></i><div class="flex-grow-1"><b>Son işlem</b><div class="small text-muted">Genel muayene · İç parazit</div></div><b>1.000,00 ₺</b></div>
    </div></div>
  </div></div></div>
</section>

<?php if ($webAppt): ?>
<section class="section pt-0" id="randevu">
  <div class="container"><div class="row g-5 align-items-center">
    <div class="col-lg-5">
      <div class="section-title text-start mx-0 mb-3"><div class="eyebrow">Online Randevu</div><h2>Size en uygun zamanı seçin</h2><p>Formu doldurun, ekibimiz randevunuzu onaylamak için kısa süre içinde sizinle iletişime geçsin.</p></div>
      <div class="contact-item"><div class="ic"><i class="bi bi-clock"></i></div><div><b>Çalışma Saatleri</b><div class="text-muted"><?= nl2br(e(setting('clinic_hours'))) ?></div></div></div>
      <div class="contact-item"><div class="ic"><i class="bi bi-telephone"></i></div><div><b>Acil durumlar için</b><div><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', setting('clinic_phone'))) ?>" class="fs-5 fw-bold"><?= e(setting('clinic_phone')) ?></a></div></div></div>
    </div>
    <div class="col-lg-7"><div class="appt-box">
      <?php if ($sent): ?>
        <div class="text-center py-4"><div style="font-size:4rem">🎉</div><h3>Talebiniz alındı!</h3><p class="text-muted">En kısa sürede onay için sizinle iletişime geçeceğiz. Teşekkür ederiz 🐾</p></div>
      <?php else: ?>
        <?php if ($formErr): ?><div class="alert alert-danger"><?= e($formErr) ?></div><?php endif; ?>
        <form method="post" action="#randevu" class="row g-3">
          <?= csrf_field() ?><input type="hidden" name="form" value="randevu">
          <input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
          <div class="col-md-6"><label class="form-label fw-bold">Adınız Soyadınız</label><input name="name" class="form-control" required value="<?= e(post('name')) ?>"></div>
          <div class="col-md-6"><label class="form-label fw-bold">Cep Telefonu</label><input name="phone" type="tel" class="form-control" required placeholder="05xx xxx xx xx" value="<?= e(post('phone')) ?>"></div>
          <div class="col-md-6"><label class="form-label fw-bold">Dostunuzun Türü</label><select name="pet_type" class="form-select"><?php foreach (species_list() as $s): ?><option><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-6"><label class="form-label fw-bold">Dostunuzun Adı</label><input name="pet_name" class="form-control" value="<?= e(post('pet_name')) ?>"></div>
          <div class="col-md-4"><label class="form-label fw-bold">Hizmet</label><select name="type" class="form-select"><?php foreach (appointment_types() as $t): ?><option><?= e($t) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-4"><label class="form-label fw-bold">Tarih</label><input type="date" name="date" class="form-control" min="<?= today() ?>" value="<?= e(post('date', date('Y-m-d', strtotime('+1 day')))) ?>" required></div>
          <div class="col-md-4"><label class="form-label fw-bold">Saat</label><select name="time" class="form-select"><?php for ($h = 9; $h <= 19; $h++) foreach (['00', '30'] as $m): ?><option><?= sprintf('%02d:%s', $h, $m) ?></option><?php endforeach; ?></select></div>
          <div class="col-12"><label class="form-label fw-bold">Notunuz</label><textarea name="note" class="form-control" rows="2" placeholder="Şikayet veya eklemek istedikleriniz"><?= e(post('note')) ?></textarea></div>
          <div class="col-12"><div class="form-check"><input type="checkbox" class="form-check-input" name="kvkk" value="1" id="kvkk" required><label class="form-check-label small" for="kvkk">Kişisel verilerimin randevu ve bilgilendirme amacıyla işlenmesini ve WhatsApp üzerinden iletişime geçilmesini kabul ediyorum (KVKK).</label></div></div>
          <div class="col-12"><button class="btn btn-coral btn-lg w-100"><i class="bi bi-calendar-check"></i> Randevu Talebi Gönder</button></div>
        </form>
      <?php endif; ?>
    </div></div>
  </div></div>
</section>
<?php endif; ?>

<section class="section" id="iletisim" style="background:var(--cream)">
  <div class="container"><div class="row g-5">
    <div class="col-lg-5">
      <div class="section-title text-start mx-0 mb-4"><div class="eyebrow">İletişim</div><h2>Bize ulaşın</h2></div>
      <div class="contact-item"><div class="ic"><i class="bi bi-geo-alt"></i></div><div><b>Adres</b><div class="text-muted"><?= nl2br(e(setting('clinic_address'))) ?></div></div></div>
      <div class="contact-item"><div class="ic"><i class="bi bi-telephone"></i></div><div><b>Telefon</b><div><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', setting('clinic_phone'))) ?>"><?= e(setting('clinic_phone')) ?></a></div></div></div>
      <?php if ($wa): ?><div class="contact-item"><div class="ic"><i class="bi bi-whatsapp"></i></div><div><b>WhatsApp</b><div><a href="<?= e(wa_link($wa)) ?>" target="_blank" rel="noopener"><?= e(phone_fmt($wa)) ?></a></div></div></div><?php endif; ?>
      <div class="contact-item"><div class="ic"><i class="bi bi-envelope"></i></div><div><b>E-posta</b><div><a href="mailto:<?= e(setting('clinic_email')) ?>"><?= e(setting('clinic_email')) ?></a></div></div></div>
      <?php if ($ig = setting('clinic_instagram')): ?><div class="contact-item"><div class="ic"><i class="bi bi-instagram"></i></div><div><b>Instagram</b><div><a href="https://instagram.com/<?= e(ltrim($ig, '@')) ?>" target="_blank" rel="noopener">@<?= e(ltrim($ig, '@')) ?></a></div></div></div><?php endif; ?>
    </div>
    <div class="col-lg-7"><div class="map">
      <?php if ($map && str_starts_with($map, 'https://www.google.com/maps/embed')): ?><iframe src="<?= e($map) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Harita"></iframe>
      <?php else: ?><div class="d-grid h-100 text-center text-muted p-5" style="place-items:center;min-height:320px"><div><i class="bi bi-map fs-1"></i><div>Harita, yönetim panelinde Ayarlar &gt; Klinik bölümünden eklenebilir.</div></div></div><?php endif; ?>
    </div></div>
  </div></div>
</section>

<footer class="site-foot">
  <div class="container">
    <div class="row g-4">
      <div class="col-md-5"><a class="brand mb-2" href="#"><img src="<?= asset('img/favicon.svg') ?>" alt="" style="filter:brightness(0) invert(1)"> patikum</a><p class="small"><?= e(setting('clinic_slogan')) ?></p></div>
      <div class="col-6 col-md-3"><b class="text-white">Hızlı Erişim</b><ul class="list-unstyled small mt-2"><li><a href="#hizmetler">Hizmetler</a></li><li><a href="#asi">Aşı Takvimi</a></li><li><a href="#randevu">Randevu</a></li><li><a href="<?= url('panel/') ?>">Müşteri Paneli</a></li></ul></div>
      <div class="col-6 col-md-4"><b class="text-white">Çalışma Saatleri</b><p class="small mt-2"><?= nl2br(e(setting('clinic_hours'))) ?></p></div>
    </div>
    <hr class="border-secondary">
    <div class="d-flex justify-content-between small flex-wrap gap-2"><span>© <?= date('Y') ?> <?= e($name) ?>. Tüm hakları saklıdır.</span><a href="<?= url('admin/') ?>" class="opacity-50">Personel Girişi</a></div>
  </div>
</footer>
<?php if ($wa): ?><a class="wa-float" href="<?= e(wa_link($wa, 'Merhaba, bilgi almak istiyorum.')) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a><?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
