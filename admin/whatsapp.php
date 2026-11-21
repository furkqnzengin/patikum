<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$pageTitle = 'WhatsApp Mesajları';
$active = 'diger';
$tab = get('sekme', 'kuyruk');
$driver = setting('wa_driver', 'manuel');

if (is_post()) {
    $act = post('islem');
    if ($act === 'ac') { // manuel gönderim: işaretle ve WhatsApp'a yönlendir
        $m = row('SELECT * FROM wa_messages WHERE id = ?', [(int)post('id')]);
        if ($m) {
            update('wa_messages', ['status' => 'gonderildi', 'sent_at' => now(), 'attempts' => (int)$m['attempts'] + 1], (int)$m['id']);
            redirect(wa_link($m['phone'], $m['body']));
        }
    }
    if ($act === 'gonder_simdi') {
        [$ok, $fail] = wa_process_queue(100);
        flash($fail ? 'warning' : 'success', "$ok mesaj gönderildi" . ($fail ? ", $fail mesaj gönderilemedi" : '') . '.');
    }
    if ($act === 'tekrar') {
        $m = row('SELECT * FROM wa_messages WHERE id = ?', [(int)post('id')]);
        if ($m) {
            $ok = wa_send($m);
            flash($ok ? 'success' : 'danger', $ok ? 'Gönderildi.' : 'Gönderilemedi: ' . e((string)val('SELECT error FROM wa_messages WHERE id = ?', [$m['id']])));
        }
    }
    if ($act === 'sil') {
        q("DELETE FROM wa_messages WHERE id = ?", [(int)post('id')]);
    }
    if ($act === 'temizle') {
        q("DELETE FROM wa_messages WHERE status IN ('kuyruk','hata')");
        flash('success', 'Kuyruk temizlendi.');
    }
    if ($act === 'sablon') {
        update('wa_templates', ['name' => post('name'), 'body' => post('body'), 'active' => post('active') ? 1 : 0, 'auto_send' => post('auto_send') ? 1 : 0, 'meta_name' => nul(post('meta_name')), 'meta_params' => nul(post('meta_params'))], (int)post('id'));
        flash('success', 'Şablon kaydedildi.');
        redirect(url('admin/whatsapp.php?sekme=sablon'));
    }
    if ($act === 'toplu') {
        $body = (string)post('body');
        $sql = "SELECT DISTINCT c.id FROM customers c LEFT JOIN patients p ON p.customer_id = c.id AND p.status = 'aktif' WHERE c.active = 1 AND c.wa_optin = 1 AND c.phone IS NOT NULL";
        $prm = [];
        if ($sp = (int)post('species_id')) { $sql .= ' AND p.species_id = ?'; $prm[] = $sp; }
        if (post('borclu')) $sql .= ' AND ' . balance_sql() . ' > 0';
        $n = 0;
        if (trim($body) !== '') foreach (rows($sql, $prm) as $c) if (wa_queue((int)$c['id'], null, $body, ['borc' => money(customer_balance((int)$c['id']))])) $n++;
        flash('success', "$n müşteri için mesaj kuyruğa alındı.");
        redirect(url('admin/whatsapp.php'));
    }
    back(url('admin/whatsapp.php'));
}

include APP . '/views/admin_header.php'; ?>
<div class="page-head"><div><h1><i class="bi bi-whatsapp text-success"></i> WhatsApp Mesajları</h1>
  <div class="sub">Gönderim modu: <b><?= ['manuel' => 'Manuel (tek tık wa.me)', 'cloud' => 'Meta WhatsApp Cloud API (otomatik)', 'api' => '3. Parti WhatsApp API (otomatik)'][$driver] ?? $driver ?></b> · <a href="<?= url('admin/ayarlar.php?sekme=whatsapp') ?>">ayarları değiştir</a></div></div>
  <div class="d-flex gap-2">
    <?php if ($driver !== 'manuel'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="islem" value="gonder_simdi"><button class="btn btn-success"><i class="bi bi-send"></i> Kuyruğu Şimdi Gönder</button></form><?php endif; ?>
    <a href="<?= url('admin/hatirlaticilar.php') ?>" class="btn btn-outline-primary"><i class="bi bi-alarm"></i> Hatırlatıcılar</a></div></div>
<ul class="nav nav-pills mb-3">
  <?php foreach (['kuyruk' => 'Bekleyenler', 'gonderildi' => 'Gönderilenler', 'hata' => 'Hatalı', 'sablon' => 'Mesaj Şablonları', 'toplu' => 'Toplu Mesaj'] as $k => $l): ?>
    <li class="nav-item"><a class="nav-link <?= $tab === $k ? 'active' : '' ?>" href="?sekme=<?= $k ?>"><?= $l ?><?php if ($k === 'kuyruk' && ($n = (int)val("SELECT COUNT(*) FROM wa_messages WHERE status='kuyruk'"))): ?> <span class="badge text-bg-warning"><?= $n ?></span><?php endif; ?></a></li>
  <?php endforeach; ?>
</ul>

<?php if (in_array($tab, ['kuyruk', 'gonderildi', 'hata'], true)):
    $pg = paginate('SELECT m.*, c.name AS musteri FROM wa_messages m LEFT JOIN customers c ON c.id = m.customer_id WHERE m.status = ? ORDER BY m.id ' . ($tab === 'kuyruk' ? 'ASC' : 'DESC'), [$tab], 30); ?>
  <?php if ($tab === 'kuyruk' && $driver === 'manuel' && $pg['total']): ?><div class="alert alert-success small"><i class="bi bi-lightbulb"></i> Manuel modda her mesajın yanındaki <b>Gönder</b> butonu WhatsApp'ı hazır mesajla açar ve mesajı gönderildi olarak işaretler. Tam otomatik gönderim için Ayarlar &gt; WhatsApp bölümünden Cloud API veya 3. parti API bağlayın.</div><?php endif; ?>
  <div class="card"><div class="card-body">
    <?php foreach ($pg['rows'] as $m): ?>
      <div class="list-row align-items-start">
        <div class="pet-avatar" style="background:#dcfce7"><i class="bi bi-whatsapp text-success"></i></div>
        <div class="flex-grow-1" style="min-width:0">
          <div class="d-flex flex-wrap gap-2 align-items-center"><b><?= e($m['musteri'] ?: 'Misafir') ?></b><span class="small text-muted"><?= e(phone_fmt($m['phone'])) ?></span><?= $m['template_code'] ? '<span class="badge text-bg-light">' . e($m['template_code']) . '</span>' : '' ?><span class="small text-muted"><?= dtt($m['created_at']) ?></span></div>
          <div class="wa-bubble mt-1"><?= e($m['body']) ?></div>
          <?php if ($m['error']): ?><div class="small text-danger mt-1"><?= e(mb_strimwidth($m['error'], 0, 300, '…')) ?></div><?php endif; ?>
        </div>
        <div class="d-flex flex-column gap-1">
          <?php if ($m['status'] !== 'gonderildi'): ?>
            <form method="post" target="_blank" onsubmit="setTimeout(()=>location.reload(),800)"><?= csrf_field() ?><input type="hidden" name="islem" value="ac"><input type="hidden" name="id" value="<?= $m['id'] ?>"><button class="btn btn-sm btn-success w-100"><i class="bi bi-whatsapp"></i> Gönder</button></form>
            <?php if ($driver !== 'manuel'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="islem" value="tekrar"><input type="hidden" name="id" value="<?= $m['id'] ?>"><button class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-arrow-repeat"></i> API ile</button></form><?php endif; ?>
          <?php else: ?><span class="small text-success"><i class="bi bi-check2-all"></i> <?= dtt($m['sent_at']) ?></span><?php endif; ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="islem" value="sil"><input type="hidden" name="id" value="<?= $m['id'] ?>"><button class="btn btn-sm btn-link text-danger w-100">Sil</button></form>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$pg['rows']): ?><div class="empty"><i class="bi bi-chat-dots"></i>Mesaj yok.</div><?php endif; ?>
  </div><div class="card-footer bg-white d-flex justify-content-between"><?php if ($tab !== 'gonderildi' && $pg['total']): ?><form method="post" data-confirm="Bekleyen ve hatalı tüm mesajlar silinsin mi?"><?= csrf_field() ?><input type="hidden" name="islem" value="temizle"><button class="btn btn-sm btn-link text-danger">Kuyruğu temizle</button></form><?php else: ?><span></span><?php endif; ?><?= pager($pg) ?></div></div>

<?php elseif ($tab === 'sablon'): $tpls = rows('SELECT * FROM wa_templates ORDER BY id'); ?>
  <div class="alert alert-light small"><b>Kullanılabilir değişkenler:</b> {musteri} {hasta} {asi} {tarih} {saat} {zaman} {islem} {sonraki} {tutar} {borc} {yas} {sifre} {giris} · Klinik: {klinik} {telefon} {adres} {site} {panel}<br>
    <b>Otomatik</b> işaretli şablonlar ilgili olay olduğunda (randevu onayı, aşı yapıldı, tahsilat vb.) veya hatırlatıcılar çalıştığında kendiliğinden kuyruğa eklenir. Meta Cloud API kullanıyorsanız 24 saat dışı mesajlar için onaylı şablon adını ve parametre sırasını girin.</div>
  <div class="row g-3">
    <?php foreach ($tpls as $t): ?>
      <div class="col-lg-6"><form method="post" class="card h-100"><div class="card-body">
        <?= csrf_field() ?><input type="hidden" name="islem" value="sablon"><input type="hidden" name="id" value="<?= $t['id'] ?>">
        <div class="d-flex gap-2 align-items-center mb-2"><input name="name" class="form-control fw-bold" value="<?= e($t['name']) ?>"><code class="small text-nowrap"><?= e($t['code']) ?></code></div>
        <textarea name="body" class="form-control mb-2" rows="5"><?= e($t['body']) ?></textarea>
        <div class="row g-2 mb-2"><div class="col-6"><input name="meta_name" class="form-control form-control-sm" placeholder="Meta şablon adı (ops.)" value="<?= e($t['meta_name']) ?>"></div><div class="col-6"><input name="meta_params" class="form-control form-control-sm" placeholder="Parametreler: musteri,hasta,tarih" value="<?= e($t['meta_params']) ?>"></div></div>
        <div class="d-flex justify-content-between align-items-center">
          <div class="d-flex gap-3"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="active" value="1" id="a<?= $t['id'] ?>" <?= $t['active'] ? 'checked' : '' ?>><label for="a<?= $t['id'] ?>" class="form-check-label small">Aktif</label></div>
            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="auto_send" value="1" id="o<?= $t['id'] ?>" <?= $t['auto_send'] ? 'checked' : '' ?>><label for="o<?= $t['id'] ?>" class="form-check-label small">Otomatik gönder</label></div></div>
          <button class="btn btn-sm btn-primary">Kaydet</button></div>
      </div></form></div>
    <?php endforeach; ?>
  </div>

<?php else: ?>
  <form method="post" class="card" style="max-width:760px"><div class="card-header">Toplu / Kampanya Mesajı</div><div class="card-body">
    <?= csrf_field() ?><input type="hidden" name="islem" value="toplu">
    <p class="small text-muted">Yalnızca WhatsApp bilgilendirme onayı (KVKK) veren müşterilere gönderilir. Değişkenler: {musteri} {borc} {klinik} {telefon} {panel}</p>
    <div class="row g-2 mb-2"><div class="col-md-6"><label class="form-label">Hayvan türüne göre filtre</label><select name="species_id" class="form-select"><?= options(species_list(), '', 'id', 'name', 'Tüm müşteriler') ?></select></div>
      <div class="col-md-6 d-flex align-items-end"><div class="form-check"><input type="checkbox" class="form-check-input" name="borclu" value="1" id="bo"><label for="bo" class="form-check-label">Sadece bakiyesi olanlar</label></div></div></div>
    <textarea name="body" class="form-control" rows="6" required placeholder="Merhaba {musteri} 🐾 Bu ay tüm kedi dostlarımıza parazit uygulamasında %15 indirim! {klinik} {telefon}"></textarea>
  </div><div class="card-footer bg-white text-end"><button class="btn btn-success" data-confirm="Mesaj seçili müşterilere kuyruğa alınacak. Devam edilsin mi?"><i class="bi bi-send"></i> Kuyruğa Ekle</button></div></form>
<?php endif; ?>
<?php include APP . '/views/admin_footer.php';
