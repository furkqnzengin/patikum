<?php
require __DIR__ . '/../app/bootstrap.php';
require_admin();
$pageTitle = 'Ayarlar';
$active = 'diger';
$tab = get('sekme', 'genel');

$fields = [
    'genel' => ['clinic_name', 'clinic_slogan', 'clinic_phone', 'clinic_whatsapp', 'clinic_email', 'clinic_address', 'clinic_hours', 'clinic_instagram', 'clinic_facebook', 'clinic_map', 'site_hero_image', 'site_about', 'kopuk_admin', 'kopuk_site', 'site_url', 'appointment_types', 'product_categories', 'expense_categories', 'service_vat', 'web_appointments'],
    'whatsapp' => ['wa_driver', 'wa_send_instant', 'wa_cloud_token', 'wa_cloud_phone_id', 'wa_cloud_version', 'wa_cloud_lang', 'wa_api_url', 'wa_api_token', 'wa_api_auth_header', 'wa_api_auth_prefix', 'wa_api_phone_field', 'wa_api_message_field', 'wa_api_extra'],
    'efatura' => ['einv_provider', 'einv_title', 'einv_vkn', 'einv_tax_office', 'einv_address', 'einv_city', 'einv_district', 'einv_prefix', 'einv_api_url', 'einv_api_user', 'einv_api_pass'],
];
$secret = ['wa_cloud_token', 'wa_api_token', 'einv_api_pass'];

if (is_post()) {
    if (post('islem') === 'temizle') {
        require_once APP . '/purge.php';
        $sel = array_values(array_filter((array)($_POST['grup'] ?? []), 'is_string'));
        if (post('hepsi')) $sel = array_keys(purge_groups());
        $me = current_user();
        $hash = (string)val('SELECT password FROM users WHERE id = ?', [$me['id']]);
        if (!$sel) {
            flash('warning', 'Silinecek en az bir veri türü seçin.');
        } elseif (mb_strtoupper(trim((string)post('onay')), 'UTF-8') !== 'SİL' && strtoupper(trim((string)post('onay'))) !== 'SIL') {
            flash('danger', 'Onay kutusuna büyük harflerle <b>SİL</b> yazmalısınız. Hiçbir şey silinmedi.');
        } elseif (!password_verify((string)post('sifre'), $hash)) {
            flash('danger', 'Şifreniz hatalı. Hiçbir şey silinmedi.');
        } else {
            try {
                $done = purge_run($sel);
                $labels = purge_groups();
                $parts = [];
                foreach ($done as $k => $n) $parts[] = e($labels[$k][0]) . ': <b>' . (int)$n . '</b>';
                flash('success', 'Veriler silindi. ' . implode(' · ', $parts));
            } catch (Throwable $e) {
                flash('danger', 'Silme tamamlanamadı, hiçbir şey değişmedi: ' . e($e->getMessage()));
            }
        }
        redirect(url('admin/ayarlar.php?sekme=temizle'));
    }
    if (post('islem') === 'test_wa') {
        $id = wa_queue(null, post('test_phone'), "Patikum test mesajı ✅ " . dtt(now()), [], null, true);
        $m = $id ? row('SELECT * FROM wa_messages WHERE id = ?', [$id]) : null;
        if (!$m) flash('danger', 'Geçerli bir telefon girin.');
        elseif (setting('wa_driver', 'manuel') === 'manuel') flash('info', 'Manuel modda test: <a class="alert-link" target="_blank" href="' . e(wa_link($m['phone'], $m['body'])) . '">WhatsApp\'ta aç</a>');
        else flash($m['status'] === 'gonderildi' ? 'success' : 'danger', $m['status'] === 'gonderildi' ? 'Test mesajı gönderildi.' : 'Gönderilemedi: ' . e((string)$m['error']));
        redirect(url('admin/ayarlar.php?sekme=whatsapp'));
    }
    if (post('islem') === 'cron_yenile') {
        setting_set('cron_key', bin2hex(random_bytes(12)));
        flash('success', 'Cron anahtarı yenilendi. cPanel cron URL\'nizi güncelleyin.');
        redirect(url('admin/ayarlar.php?sekme=sistem'));
    }
    if ($tab === 'genel') {
        $old = (string)setting('clinic_logo');
        $f = $_FILES['logo_file'] ?? null;
        if (post('logo_sil') && $old !== '') {
            @unlink(ROOT . '/assets/' . $old);
            setting_set('clinic_logo', '');
        }
        if ($f && $f['error'] === UPLOAD_ERR_OK) {
            $info = @getimagesize($f['tmp_name']);
            $ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'][$info['mime'] ?? ''] ?? null;
            if (!$ext || $f['size'] > 3 * 1024 * 1024) {
                flash('danger', 'Logo yüklenemedi: PNG, JPG veya WEBP biçiminde, en fazla 3 MB bir resim seçin.');
            } else {
                $dir = ROOT . '/assets/img/uploads';
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi|sh)$\">\n  Require all denied\n</FilesMatch>\n");
                $name = 'logo-' . date('YmdHis') . '.' . $ext;
                if (move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
                    if ($old !== '' && $old !== 'img/uploads/' . $name) @unlink(ROOT . '/assets/' . $old);
                    setting_set('clinic_logo', 'img/uploads/' . $name);
                } else {
                    flash('danger', 'Logo kaydedilemedi. assets/img klasörünün yazma izni (755) olduğundan emin olun.');
                }
            }
        } elseif ($f && $f['error'] !== UPLOAD_ERR_NO_FILE) {
            flash('danger', 'Logo yüklenemedi (dosya çok büyük olabilir).');
        }
    }
    foreach ($fields[$tab] ?? [] as $f) {
        if (!array_key_exists($f, $_POST)) {
            if (in_array($f, ['wa_send_instant', 'web_appointments', 'kopuk_admin', 'kopuk_site'], true)) setting_set($f, '0');
            continue;
        }
        if (in_array($f, $secret, true) && $_POST[$f] === '') continue; // boş bırakılırsa eskisi korunur
        setting_set($f, trim((string)$_POST[$f]));
    }
    flash('success', 'Ayarlar kaydedildi.');
    redirect(url('admin/ayarlar.php?sekme=' . $tab));
}

function fld(string $k, string $label, string $type = 'text', string $help = '', array $opts = []): string
{
    $v = setting($k);
    $h = '<div class="mb-3"><label class="form-label fw-bold">' . $label . '</label>';
    if ($type === 'textarea') $h .= '<textarea name="' . $k . '" class="form-control" rows="3">' . e($v) . '</textarea>';
    elseif ($type === 'select') $h .= '<select name="' . $k . '" class="form-select">' . options($opts, $v) . '</select>';
    elseif ($type === 'secret') $h .= '<input type="password" name="' . $k . '" class="form-control" autocomplete="new-password" placeholder="' . ($v ? '•••••••• (kayıtlı — değiştirmek için yazın)' : '') . '">';
    elseif ($type === 'switch') $h = '<div class="form-check form-switch mb-3"><input type="checkbox" class="form-check-input" name="' . $k . '" value="1" id="f' . $k . '" ' . ($v === '1' ? 'checked' : '') . '><label class="form-check-label fw-bold" for="f' . $k . '">' . $label . '</label>';
    else $h .= '<input type="' . $type . '" name="' . $k . '" class="form-control" value="' . e($v) . '">';
    return $h . ($help ? '<div class="form-text">' . $help . '</div>' : '') . '</div>';
}

include APP . '/views/admin_header.php'; ?>
<div class="page-head"><div><h1>Ayarlar</h1><div class="sub">Klinik bilgileri, WhatsApp entegrasyonu ve e-Dönüşüm</div></div></div>
<ul class="nav nav-pills mb-3">
  <?php foreach (['genel' => ['Klinik & Site', 'building'], 'whatsapp' => ['WhatsApp', 'whatsapp'], 'efatura' => ['E-Dönüşüm', 'file-earmark-text'], 'sistem' => ['Sistem', 'cpu'], 'temizle' => ['Verileri Temizle', 'trash3']] as $k => [$l, $i]): ?>
    <li class="nav-item"><a class="nav-link <?= $tab === $k ? 'active' : '' ?>" href="?sekme=<?= $k ?>"><i class="bi bi-<?= $i ?>"></i> <?= $l ?></a></li>
  <?php endforeach; ?>
</ul>
<?php if ($tab === 'temizle'): require_once APP . '/purge.php'; ?>
  <div class="row g-3" style="max-width:1100px">
    <div class="col-lg-8">
      <form method="post" class="card border-danger-subtle" id="purgeForm" data-confirm="Seçilen veriler KALICI olarak silinecek. Emin misiniz?">
        <div class="card-header text-danger"><i class="bi bi-trash3 me-1"></i> Verileri Temizle</div>
        <div class="card-body">
          <?= csrf_field() ?><input type="hidden" name="islem" value="temizle">
          <div class="alert alert-danger small"><i class="bi bi-exclamation-octagon me-1"></i><b>Silinen veri geri getirilemez.</b> Başlamadan önce cPanel → <b>phpMyAdmin</b> → veritabanınız → <b>Dışa Aktar</b> ile yedek alın. Ayarlar, kullanıcılar, hayvan kategorileri, aşı tanımları ve aşı akışı silinmez.</div>
          <div class="purge-list">
            <?php foreach (purge_groups() as $k => [$label, $icon, $desc, $cntSql]): $n = purge_count($cntSql); ?>
              <label class="purge-item">
                <input type="checkbox" class="form-check-input js-purge" name="grup[]" value="<?= $k ?>">
                <span class="ic"><i class="bi bi-<?= $icon ?>"></i></span>
                <span class="flex-grow-1"><b><?= e($label) ?></b><small><?= e($desc) ?></small></span>
                <span class="badge text-bg-light border"><?= number_format($n, 0, ',', '.') ?> kayıt</span>
              </label>
            <?php endforeach; ?>
          </div>
          <label class="purge-item all mt-2"><input type="checkbox" class="form-check-input" name="hepsi" value="1" id="purgeAll"><span class="ic"><i class="bi bi-exclamation-triangle"></i></span><span class="flex-grow-1"><b>Hepsini sil (sistemi sıfırla)</b><small>Yukarıdaki tüm veriler silinir; sistem ilk kurulduğu gibi boş kalır.</small></span></label>
          <div class="row g-2 mt-3">
            <div class="col-md-6"><label class="form-label">Onaylamak için <b>SİL</b> yazın</label><input name="onay" class="form-control" autocomplete="off" placeholder="SİL" required></div>
            <div class="col-md-6"><label class="form-label">Şifreniz</label><input type="password" name="sifre" class="form-control" autocomplete="current-password" required></div>
          </div>
        </div>
        <div class="card-footer bg-white text-end"><button class="btn btn-danger px-4"><i class="bi bi-trash3"></i> Seçilenleri Kalıcı Olarak Sil</button></div>
      </form>
    </div>
    <div class="col-lg-4">
      <div class="card"><div class="card-header"><i class="bi bi-lightbulb me-1"></i> İpucu</div><div class="card-body small">
        <p>Yalnızca Excel'den aktardığınız kayıtları silmek istiyorsanız <a href="<?= url('admin/aktar.php') ?>">Excel'den Veri Aktarımı</a> sayfasındaki <b>Geri al</b> düğmelerini kullanın. O düğmeler elle girdiğiniz kayıtlara dokunmaz.</p>
        <p class="mb-0">Silme sırasında bir hata olursa işlem tamamen geri alınır; yarım silme olmaz.</p>
      </div></div>
    </div>
  </div>
<?php elseif ($tab === 'sistem'): ?>
  <div class="card" style="max-width:760px"><div class="card-body small">
    <dl class="row mb-0">
      <dt class="col-4">Sürüm</dt><dd class="col-8">Patikum v<?= PATIKUM_VERSION ?></dd>
      <dt class="col-4">PHP</dt><dd class="col-8"><?= PHP_VERSION ?></dd>
      <dt class="col-4">Veritabanı</dt><dd class="col-8"><?= e(db_driver()) ?></dd>
      <dt class="col-4">Site adresi</dt><dd class="col-8"><?= e(site_url()) ?></dd>
      <dt class="col-4">Cron anahtarı</dt><dd class="col-8"><code><?= e(setting('cron_key')) ?></code>
        <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="islem" value="cron_yenile"><button class="btn btn-sm btn-link" data-confirm="Anahtar yenilensin mi?">Yenile</button></form></dd>
      <dt class="col-4">Kurulum klasörü</dt><dd class="col-8"><?= is_dir(ROOT . '/install') ? '<span class="text-danger fw-bold"><i class="bi bi-exclamation-triangle"></i> install/ klasörü hâlâ sunucuda — güvenlik için silin!</span>' : '<span class="text-success">Silinmiş ✓</span>' ?></dd>
    </dl>
  </div></div>
<?php else: ?>
<form method="post" class="card" style="max-width:900px" enctype="multipart/form-data"><div class="card-body">
  <?= csrf_field() ?>
  <?php if ($tab === 'genel'): ?>
    <div class="row"><div class="col-md-6"><?= fld('clinic_name', 'Klinik Adı') ?></div><div class="col-md-6"><?= fld('clinic_slogan', 'Slogan') ?></div>
      <div class="col-md-4"><?= fld('clinic_phone', 'Telefon') ?></div><div class="col-md-4"><?= fld('clinic_whatsapp', 'WhatsApp Numarası', 'text', 'Sitedeki WhatsApp butonu için (90 ile)') ?></div><div class="col-md-4"><?= fld('clinic_email', 'E-posta', 'email') ?></div>
      <div class="col-md-6"><?= fld('clinic_address', 'Adres', 'textarea') ?></div><div class="col-md-6"><?= fld('clinic_hours', 'Çalışma Saatleri', 'textarea') ?></div>
      <div class="col-md-6"><?= fld('clinic_instagram', 'Instagram kullanıcı adı') ?></div><div class="col-md-6"><?= fld('clinic_facebook', 'Facebook sayfa adresi') ?></div>
      <div class="col-12"><?= fld('clinic_map', 'Google Harita Embed URL', 'text', 'Google Maps &gt; Paylaş &gt; Harita yerleştir &gt; iframe içindeki src adresi') ?></div>
      <div class="col-12"><h6 class="fw-bold mt-2"><i class="bi bi-window me-1"></i>Web Sitesi Görünümü</h6></div>
      <div class="col-12"><div class="mb-3"><label class="form-label fw-bold">Klinik Logosu</label>
        <div class="d-flex align-items-center gap-3 flex-wrap">
          <div class="logo-preview"><?php if ($lg = clinic_logo()): ?><img src="<?= e($lg) ?>" alt="Logo"><?php else: ?><img src="<?= asset('img/patikum-logo-192.png') ?>" alt="Patikum logosu"><?php endif; ?></div>
          <div class="flex-grow-1"><input type="file" name="logo_file" accept="image/png,image/jpeg,image/webp" class="form-control">
            <div class="form-text">PNG (şeffaf arka planlı önerilir), JPG veya WEBP · en fazla 3 MB. Boş bırakılırsa Patikum logosu kullanılır. Yüklenen logo web sitesinin üst menüsünde, alt bilgisinde, yönetim panelinde, müşteri panelinde ve giriş ekranlarında görünür.</div>
            <?php if (clinic_logo()): ?><div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="logo_sil" value="1" id="lsil"><label class="form-check-label small text-danger" for="lsil">Logoyu kaldır</label></div><?php endif; ?>
          </div>
        </div></div></div>
      <div class="col-md-6"><?= fld('site_hero_image', 'Ana sayfa fotoğrafı (adres)', 'url', 'Kliniğinizin veya bir hastanızın fotoğrafının bağlantısı (https://…jpg). Boş bırakılırsa çizim gösterilir.') ?></div>
      <div class="col-md-6"><?= fld('site_about', 'Hakkımızda yazısı', 'textarea', 'Ana sayfadaki "Hakkımızda" bölümünde görünür.') ?></div>
      <div class="col-md-6"><?= fld('kopuk_admin', '🐱 Köpük yönetim panelinde gezinsin', 'switch') ?></div>
      <div class="col-md-6"><?= fld('kopuk_site', '🐱 Köpük web sitesinde ve müşteri panelinde gezinsin', 'switch') ?></div>
      <div class="col-md-6"><?= fld('site_url', 'Site Adresi', 'url', 'WhatsApp mesajlarındaki panel linki için. Örn: https://patikum.com') ?></div><div class="col-md-6"><?= fld('service_vat', 'Muayene/hizmet KDV oranı (%)', 'number') ?></div>
      <div class="col-12"><?= fld('web_appointments', 'Web sitesinden online randevu talebi alınsın', 'switch') ?></div>
      <div class="col-12"><?= fld('appointment_types', 'Randevu Türleri', 'text', 'Virgülle ayırın') ?></div>
      <div class="col-md-6"><?= fld('product_categories', 'Ürün Kategorileri', 'text', 'Virgülle ayırın') ?></div><div class="col-md-6"><?= fld('expense_categories', 'Gider Kategorileri', 'text', 'Virgülle ayırın') ?></div></div>
  <?php elseif ($tab === 'whatsapp'): ?>
    <?= fld('wa_driver', 'Gönderim Yöntemi', 'select', 'Manuel: ücretsiz, personel tek tıkla gönderir. Cloud API: Meta Business hesabı ile tam otomatik. 3. Parti API: Türkiye\'deki WhatsApp API servisleri.', ['manuel' => 'Manuel (wa.me bağlantısı)', 'cloud' => 'Meta WhatsApp Cloud API (resmi)', 'api' => '3. Parti WhatsApp API servisi']) ?>
    <?= fld('wa_send_instant', 'Mesaj oluşturulunca hemen gönder (kapalıysa cron ile gönderilir)', 'switch') ?>
    <h6 class="fw-bold mt-4"><i class="bi bi-meta"></i> Meta WhatsApp Cloud API</h6>
    <div class="row"><div class="col-md-6"><?= fld('wa_cloud_phone_id', 'Phone Number ID') ?></div><div class="col-md-6"><?= fld('wa_cloud_token', 'Kalıcı Erişim Token', 'secret') ?></div>
      <div class="col-md-6"><?= fld('wa_cloud_version', 'Graph API Sürümü') ?></div><div class="col-md-6"><?= fld('wa_cloud_lang', 'Şablon Dil Kodu') ?></div></div>
    <div class="form-text mb-3">Not: Meta kuralları gereği müşteri size son 24 saatte yazmadıysa yalnızca onaylı şablonlar gönderilebilir. Şablon adlarını WhatsApp &gt; Mesaj Şablonları ekranında eşleştirin.</div>
    <h6 class="fw-bold mt-4"><i class="bi bi-plug"></i> 3. Parti WhatsApp API</h6>
    <div class="row"><div class="col-md-8"><?= fld('wa_api_url', 'API Gönderim URL') ?></div><div class="col-md-4"><?= fld('wa_api_token', 'API Token', 'secret') ?></div>
      <div class="col-md-3"><?= fld('wa_api_auth_header', 'Yetki başlığı') ?></div><div class="col-md-3"><?= fld('wa_api_auth_prefix', 'Token ön eki') ?></div>
      <div class="col-md-3"><?= fld('wa_api_phone_field', 'Telefon alan adı') ?></div><div class="col-md-3"><?= fld('wa_api_message_field', 'Mesaj alan adı') ?></div>
      <div class="col-12"><?= fld('wa_api_extra', 'Ek JSON alanları', 'text', 'Örn: {"instance_id":"123"}') ?></div></div>
  <?php else: ?>
    <?= fld('einv_provider', 'Çalışma Modu', 'select', 'Manuel: UBL-TR XML indirilip entegratör portalına yüklenir. Entegratör: XML doğrudan API\'ye gönderilir (entegratörünüzün dokümanına göre uyarlanması gerekebilir).', ['manuel' => 'Manuel (XML indir)', 'entegrator' => 'Entegratör API']) ?>
    <div class="row"><div class="col-md-8"><?= fld('einv_title', 'Firma Ünvanı') ?></div><div class="col-md-4"><?= fld('einv_vkn', 'VKN / TCKN') ?></div>
      <div class="col-md-4"><?= fld('einv_tax_office', 'Vergi Dairesi') ?></div><div class="col-md-4"><?= fld('einv_city', 'İl') ?></div><div class="col-md-4"><?= fld('einv_district', 'İlçe') ?></div>
      <div class="col-md-9"><?= fld('einv_address', 'Fatura Adresi') ?></div><div class="col-md-3"><?= fld('einv_prefix', 'Fatura Seri Ön Eki', 'text', '3 harf, ör: PTK') ?></div>
      <div class="col-md-6"><?= fld('einv_api_url', 'Entegratör API URL') ?></div><div class="col-md-3"><?= fld('einv_api_user', 'Kullanıcı') ?></div><div class="col-md-3"><?= fld('einv_api_pass', 'Şifre', 'secret') ?></div></div>
  <?php endif; ?>
</div><div class="card-footer bg-white text-end"><button class="btn btn-primary px-4">Kaydet</button></div></form>
<?php if ($tab === 'whatsapp'): ?>
  <form method="post" class="card mt-3" style="max-width:900px"><div class="card-body d-flex gap-2 align-items-end"><?= csrf_field() ?><input type="hidden" name="islem" value="test_wa">
    <div class="flex-grow-1"><label class="form-label fw-bold">Test mesajı gönder</label><input name="test_phone" class="form-control" placeholder="05xx xxx xx xx"></div><button class="btn btn-success"><i class="bi bi-send"></i> Test Et</button></div></form>
<?php endif; ?>
<?php endif; ?>
<?php if ($tab === 'temizle') $pageScripts = '<script>(function(){const a=document.getElementById("purgeAll");if(!a)return;a.addEventListener("change",()=>document.querySelectorAll(".js-purge").forEach(c=>{c.checked=a.checked;c.disabled=a.checked;}));})();</script>'; ?>
<?php include APP . '/views/admin_footer.php';
