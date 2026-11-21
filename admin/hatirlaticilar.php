<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$pageTitle = 'Otomatik Hatırlatıcılar';
$active = 'diger';

if (is_post()) {
    if (post('islem') === 'kaydet') {
        foreach (['rem_vaccine_enabled', 'rem_appt_enabled', 'rem_birthday', 'rem_debt_enabled'] as $k) setting_set($k, post($k) ? '1' : '0');
        setting_set('rem_vaccine_days', preg_replace('/[^0-9,]/', '', (string)post('rem_vaccine_days')) ?: '3,1');
        foreach (['rem_vaccine_overdue_days', 'rem_appt_hours', 'rem_debt_min', 'rem_debt_weekday'] as $k) setting_set($k, (string)(int)post($k));
        flash('success', 'Hatırlatıcı ayarları kaydedildi.');
    }
    if (post('islem') === 'calistir') {
        $st = reminders_generate();
        [$ok, $fail] = wa_process_queue(100);
        flash('success', 'Hatırlatıcılar çalıştı: ' . $st['asi'] . ' aşı, ' . $st['gecikme'] . ' geciken aşı, ' . $st['randevu'] . ' randevu, ' . $st['dogum_gunu'] . ' doğum günü, ' . $st['borc'] . ' bakiye mesajı oluşturuldu.' . (setting('wa_driver', 'manuel') !== 'manuel' ? " $ok gönderildi." : ' <a class="alert-link" href="' . url('admin/whatsapp.php') . '">Kuyruğa git</a>'));
    }
    redirect(url('admin/hatirlaticilar.php'));
}

$cronKey = setting('cron_key');
$cronUrl = site_url() . '/cron/run.php?key=' . $cronKey;
$cronPath = str_replace('\\', '/', ROOT) . '/cron/run.php';
$lastRun = setting('rem_last_run');
include APP . '/views/admin_header.php'; ?>
<div class="page-head"><div><h1><i class="bi bi-alarm text-primary"></i> Otomatik Hatırlatıcılar</h1><div class="sub">Aşı, randevu, doğum günü ve bakiye hatırlatmaları WhatsApp ile otomatik gönderilir.</div></div>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="islem" value="calistir"><button class="btn btn-primary"><i class="bi bi-play-circle"></i> Şimdi Çalıştır</button></form></div>
<div class="row g-3">
  <div class="col-lg-7"><form method="post" class="card"><div class="card-body">
    <?= csrf_field() ?><input type="hidden" name="islem" value="kaydet">
    <div class="list-row"><div class="pet-avatar" style="background:#fce7f3">💉</div><div class="flex-grow-1"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="rem_vaccine_enabled" value="1" id="r1" <?= setting('rem_vaccine_enabled', '1') === '1' ? 'checked' : '' ?>><label for="r1" class="form-check-label fw-bold">Aşı hatırlatma</label></div>
      <div class="row g-2 mt-1"><div class="col-md-6"><label class="small">Kaç gün önce (virgülle, 0 = aynı gün)</label><input name="rem_vaccine_days" class="form-control form-control-sm" value="<?= e(setting('rem_vaccine_days', '3,1')) ?>"></div>
        <div class="col-md-6"><label class="small">Geciken aşı uyarısı (kaç gün sonra, 0 = kapalı)</label><input type="number" name="rem_vaccine_overdue_days" class="form-control form-control-sm" value="<?= e(setting('rem_vaccine_overdue_days', '3')) ?>"></div></div></div></div>
    <div class="list-row"><div class="pet-avatar" style="background:#e0f2fe">📅</div><div class="flex-grow-1"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="rem_appt_enabled" value="1" id="r2" <?= setting('rem_appt_enabled', '1') === '1' ? 'checked' : '' ?>><label for="r2" class="form-check-label fw-bold">Randevu hatırlatma</label></div>
      <div class="row g-2 mt-1"><div class="col-md-6"><label class="small">Randevudan kaç saat önce</label><input type="number" name="rem_appt_hours" class="form-control form-control-sm" value="<?= e(setting('rem_appt_hours', '24')) ?>"></div></div></div></div>
    <div class="list-row"><div class="pet-avatar" style="background:#fef3c7">🎂</div><div class="flex-grow-1"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="rem_birthday" value="1" id="r3" <?= setting('rem_birthday', '1') === '1' ? 'checked' : '' ?>><label for="r3" class="form-check-label fw-bold">Doğum günü kutlaması</label></div><div class="small text-muted">Hastanın doğum gününde sahibine kutlama mesajı.</div></div></div>
    <div class="list-row"><div class="pet-avatar" style="background:#fee2e2">💳</div><div class="flex-grow-1"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="rem_debt_enabled" value="1" id="r4" <?= setting('rem_debt_enabled', '0') === '1' ? 'checked' : '' ?>><label for="r4" class="form-check-label fw-bold">Haftalık bakiye hatırlatma</label></div>
      <div class="row g-2 mt-1"><div class="col-md-6"><label class="small">Minimum bakiye (₺)</label><input type="number" name="rem_debt_min" class="form-control form-control-sm" value="<?= e(setting('rem_debt_min', '100')) ?>"></div>
        <div class="col-md-6"><label class="small">Gönderim günü</label><select name="rem_debt_weekday" class="form-select form-select-sm"><?= options([1 => 'Pazartesi', 2 => 'Salı', 3 => 'Çarşamba', 4 => 'Perşembe', 5 => 'Cuma', 6 => 'Cumartesi', 7 => 'Pazar'], setting('rem_debt_weekday', '1')) ?></select></div></div></div></div>
    <div class="small text-muted mt-2">Mesaj metinlerini <a href="<?= url('admin/whatsapp.php?sekme=sablon') ?>">WhatsApp şablonlarından</a> düzenleyebilirsiniz. Aynı hatırlatma bir kez gönderilir.</div>
  </div><div class="card-footer bg-white text-end"><button class="btn btn-primary px-4">Kaydet</button></div></form></div>
  <div class="col-lg-5"><div class="card"><div class="card-header"><i class="bi bi-clock-history"></i> Zamanlanmış Görev (cPanel Cron)</div><div class="card-body small">
    <p>Hatırlatıcıların otomatik çalışması için cPanel &gt; <b>Cron Jobs</b> bölümüne aşağıdaki komutu <b>15 dakikada bir</b> çalışacak şekilde ekleyin:</p>
    <pre class="bg-light p-2 rounded small" style="white-space:pre-wrap">*/15 * * * * php <?= e($cronPath) ?> >/dev/null 2>&1</pre>
    <p class="mb-1">Alternatif (URL ile):</p>
    <pre class="bg-light p-2 rounded small" style="white-space:pre-wrap">*/15 * * * * curl -s "<?= e($cronUrl) ?>" >/dev/null</pre>
    <p class="mb-0">Son çalışma: <b><?= $lastRun ? dtt($lastRun) : 'henüz çalışmadı' ?></b></p>
  </div></div></div>
</div>
<?php include APP . '/views/admin_footer.php';
