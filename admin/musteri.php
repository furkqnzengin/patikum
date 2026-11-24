<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$active = 'musteri';
$id = (int)get('id', post('id'));
$c = row('SELECT * FROM customers WHERE id = ?', [$id]);
if (!$c) {
    flash('danger', 'Müşteri bulunamadı.');
    redirect(url('admin/musteriler.php'));
}

if (is_post()) {
    $act = post('islem');
    if ($act === 'panel_sifre') {
        $pass = post('sifre') ?: random_password(6);
        update('customers', ['panel_password' => password_hash($pass, PASSWORD_DEFAULT)], $id);
        $msg = 'Müşteri paneli şifresi oluşturuldu: <b>' . e($pass) . '</b>';
        if (post('gonder')) {
            $mid = wa_queue($id, null, 'panel_bilgi', ['sifre' => $pass, 'giris' => phone_fmt($c['phone'])], null, true);
            $msg .= $mid ? ' — WhatsApp mesajı kuyruğa alındı.' : '';
            if ($mid && setting('wa_driver', 'manuel') === 'manuel') $msg .= ' <a target="_blank" class="alert-link" href="' . e(wa_link($c['phone'], (string)val('SELECT body FROM wa_messages WHERE id = ?', [$mid]))) . '">WhatsApp\'ta gönder</a>';
        }
        flash('success', $msg);
    }
    if ($act === 'wa_mesaj') {
        $body = (string)post('mesaj');
        if ($body !== '') {
            $mid = wa_queue($id, null, $body, [], null, true);
            if (setting('wa_driver', 'manuel') === 'manuel') {
                update('wa_messages', ['status' => 'gonderildi', 'sent_at' => now()], (int)$mid);
                redirect(wa_link($c['phone'], wa_render($body, ['musteri' => $c['name']])));
            }
            flash('success', 'Mesaj gönderim kuyruğuna alındı.');
        }
    }
    redirect(url('admin/musteri.php?id=' . $id));
}

$pageTitle = $c['name'];
$pets = rows('SELECT p.*, s.name AS tur, s.icon FROM patients p LEFT JOIN species s ON s.id = p.species_id WHERE p.customer_id = ? ORDER BY p.status, p.name', [$id]);
$balance = customer_balance($id);
// Hesap ekstresi
$ledger = [];
foreach (rows("SELECT id, sale_date AS d, total, note, status FROM sales WHERE customer_id = ? AND status = 'aktif'", [$id]) as $s) {
    $ledger[] = ['d' => $s['d'], 'type' => 'satis', 'desc' => $s['note'] ?: 'Satış #' . $s['id'], 'borc' => (float)$s['total'], 'alacak' => 0, 'id' => $s['id']];
}
foreach (rows('SELECT id, pay_date AS d, amount, method, note FROM payments WHERE customer_id = ?', [$id]) as $p) {
    $ledger[] = ['d' => $p['d'], 'type' => 'tahsilat', 'desc' => 'Tahsilat (' . pay_method_label($p['method']) . ')' . ($p['note'] ? ' - ' . $p['note'] : ''), 'borc' => 0, 'alacak' => (float)$p['amount'], 'id' => $p['id']];
}
usort($ledger, fn($a, $b) => strcmp($a['d'], $b['d']));
$run = 0;
foreach ($ledger as &$l) {
    $run += $l['borc'] - $l['alacak'];
    $l['bakiye'] = $run;
}
unset($l);
$ledger = array_reverse($ledger);
$upcomingVacc = rows("SELECT v.*, t.name AS asi, p.name AS hasta FROM vaccinations v JOIN vaccine_types t ON t.id = v.vaccine_type_id JOIN patients p ON p.id = v.patient_id WHERE p.customer_id = ? AND v.status = 'planlandi' ORDER BY v.due_date LIMIT 10", [$id]);
$appts = rows('SELECT a.*, p.name AS hasta FROM appointments a LEFT JOIN patients p ON p.id = a.patient_id WHERE a.customer_id = ? ORDER BY a.start_at DESC LIMIT 10', [$id]);
$msgs = rows('SELECT * FROM wa_messages WHERE customer_id = ? ORDER BY id DESC LIMIT 10', [$id]);

include APP . '/views/admin_header.php'; ?>
<div class="page-head">
  <div class="d-flex align-items-center gap-3">
    <div class="pet-avatar lg" style="background:#ccfbf1;font-size:1.8rem;font-weight:900;color:#0f766e"><?= e(initials($c['name'])) ?></div>
    <div><h1><?= e($c['name']) ?></h1><div class="sub"><i class="bi bi-telephone"></i> <?= e(phone_fmt($c['phone'])) ?> <?= $c['email'] ? ' · <i class="bi bi-envelope"></i> ' . e($c['email']) : '' ?> · Müşteri no: #<?= $c['id'] ?></div></div>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a class="btn btn-success" target="_blank" href="<?= e(wa_link($c['phone'])) ?>"><i class="bi bi-whatsapp"></i> WhatsApp</a>
    <a class="btn btn-outline-primary" href="<?= url('admin/satis.php?musteri=' . $id) ?>"><i class="bi bi-cart-plus"></i> Satış</a>
    <a class="btn btn-outline-primary" href="<?= url('admin/muhasebe.php?sekme=tahsilat&yeni=1&musteri=' . $id) ?>"><i class="bi bi-cash-coin"></i> Tahsilat</a>
    <a class="btn btn-outline-primary" href="<?= url('admin/randevular.php?islem=yeni&musteri=' . $id) ?>"><i class="bi bi-calendar-plus"></i> Randevu</a>
    <a class="btn btn-light" href="<?= url('admin/musteriler.php?islem=duzenle&id=' . $id) ?>"><i class="bi bi-pencil"></i> Düzenle</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-4"><div class="kpi"><div class="ic <?= $balance > 0 ? 'grad-pink' : 'grad-green' ?>"><i class="bi bi-wallet2"></i></div><div><div class="l"><?= $balance > 0 ? 'Kalan Borç' : ($balance < 0 ? 'Alacaklı (avans)' : 'Bakiye') ?></div><div class="v <?= $balance > 0 ? 'text-danger' : '' ?>"><?= money_h(abs($balance)) ?></div></div></div></div>
  <div class="col-md-4"><div class="kpi"><div class="ic grad-orange"><i class="bi bi-bag-check"></i></div><div><div class="l">Toplam Alışveriş</div><div class="v"><?= money_h(array_sum(array_column($ledger, 'borc'))) ?></div></div></div></div>
  <div class="col-md-4"><div class="kpi"><div class="ic grad-teal"><i class="bi bi-cash-stack"></i></div><div><div class="l">Toplam Ödeme</div><div class="v"><?= money_h(array_sum(array_column($ledger, 'alacak'))) ?></div></div></div></div>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center"><span><i class="bi bi-heart-pulse text-primary me-1"></i> Hastaları (<?= count($pets) ?>)</span>
        <a class="btn btn-sm btn-primary" href="<?= url('admin/hastalar.php?islem=yeni&musteri=' . $id) ?>"><i class="bi bi-plus-lg"></i> Hasta Ekle</a></div>
      <div class="card-body"><div class="row g-2">
        <?php foreach ($pets as $p): ?>
          <div class="col-md-6"><a href="<?= url('admin/hasta.php?id=' . $p['id']) ?>" class="d-flex align-items-center gap-3 p-2 rounded-4 border text-reset">
            <div class="pet-avatar"><?= species_icon($p['icon']) ?></div>
            <div class="flex-grow-1"><div class="fw-bold"><?= e($p['name']) ?> <?= $p['status'] !== 'aktif' ? status_badge($p['status']) : '' ?></div><div class="small text-muted"><?= e($p['tur']) ?> · <?= e($p['breed'] ?: '-') ?> · <?= age_text($p['birth_date']) ?></div></div>
            <i class="bi bi-chevron-right text-muted"></i></a></div>
        <?php endforeach; ?>
        <?php if (!$pets): ?><div class="empty"><i class="bi bi-heart"></i>Henüz hasta kaydı yok.</div><?php endif; ?>
      </div></div>
    </div>

    <div class="card">
      <div class="card-header d-flex justify-content-between"><span><i class="bi bi-journal-text text-primary me-1"></i> Hesap Ekstresi</span><button onclick="window.print()" class="btn btn-sm btn-light no-print"><i class="bi bi-printer"></i> Yazdır</button></div>
      <div class="table-responsive"><table class="table table-sm table-hover align-middle">
        <thead><tr><th>Tarih</th><th>İşlem</th><th class="text-end">Borç</th><th class="text-end">Ödeme</th><th class="text-end">Bakiye</th></tr></thead><tbody>
        <?php foreach ($ledger as $l): ?>
          <tr><td class="small"><?= dtt($l['d']) ?></td>
            <td><?php if ($l['type'] === 'satis'): ?><a href="<?= url('admin/satislar.php?id=' . $l['id']) ?>"><?= e($l['desc']) ?></a><?php else: ?><i class="bi bi-arrow-down-circle text-success"></i> <?= e($l['desc']) ?><?php endif; ?></td>
            <td class="text-end"><?= $l['borc'] ? money($l['borc']) : '' ?></td><td class="text-end text-success"><?= $l['alacak'] ? money($l['alacak']) : '' ?></td>
            <td class="text-end fw-bold"><?= money($l['bakiye']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$ledger): ?><tr><td colspan="5" class="empty">Hareket yok.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card mb-3"><div class="card-header"><i class="bi bi-person-vcard text-primary me-1"></i> Bilgiler</div><div class="card-body">
      <dl class="info-list mb-0">
        <?php if ($c['phone2']): ?><dt>2. Telefon</dt><dd><?= e(phone_fmt($c['phone2'])) ?></dd><?php endif; ?>
        <dt>Adres</dt><dd><?= e(trim(($c['address'] ?? '') . ' ' . ($c['district'] ?? '') . ' ' . ($c['city'] ?? ''))) ?: '-' ?></dd>
        <dt>TC / VKN</dt><dd><?= e($c['tc_no'] ?: '-') ?> <?= $c['is_efatura'] ? '<span class="badge text-bg-info">e-Fatura</span>' : '' ?></dd>
        <?php if ($c['company']): ?><dt>Firma</dt><dd><?= e($c['company']) ?> / <?= e($c['tax_office']) ?></dd><?php endif; ?>
        <dt>WhatsApp bildirimi</dt><dd><?= $c['wa_optin'] ? '<span class="text-success"><i class="bi bi-check-circle"></i> Açık</span>' : '<span class="text-muted">Kapalı</span>' ?></dd>
        <?php if ($c['notes']): ?><dt>Notlar</dt><dd><?= nl2br(e($c['notes'])) ?></dd><?php endif; ?>
      </dl>
    </div></div>

    <div class="card mb-3"><div class="card-header"><i class="bi bi-person-lock text-primary me-1"></i> Müşteri Paneli</div><div class="card-body">
      <p class="small text-muted mb-2">Müşteri; telefon numarası ve şifresiyle <b><?= e(site_url()) ?>/panel</b> adresinden aşı takvimini, işlemleri, ödemelerini ve borcunu görebilir.</p>
      <p class="small mb-2">Durum: <?= $c['panel_password'] ? '<span class="text-success fw-bold">Aktif</span>' . ($c['last_login'] ? ' · son giriş ' . dtt($c['last_login']) : '') : '<span class="text-muted">Şifre oluşturulmadı</span>' ?></p>
      <form method="post" class="d-flex flex-column gap-2"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="islem" value="panel_sifre">
        <input name="sifre" class="form-control form-control-sm" placeholder="Şifre (boş = otomatik üret)">
        <div class="form-check"><input type="checkbox" class="form-check-input" name="gonder" value="1" id="pg" checked><label for="pg" class="form-check-label small">Giriş bilgilerini WhatsApp ile gönder</label></div>
        <button class="btn btn-sm btn-outline-primary"><i class="bi bi-key"></i> <?= $c['panel_password'] ? 'Şifreyi Yenile' : 'Panel Şifresi Oluştur' ?></button>
      </form>
    </div></div>

    <div class="card mb-3"><div class="card-header"><i class="bi bi-whatsapp text-success me-1"></i> WhatsApp Mesajı Gönder</div><div class="card-body">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="islem" value="wa_mesaj">
        <textarea name="mesaj" class="form-control mb-2" rows="3" placeholder="Merhaba {musteri}, ..."></textarea>
        <button class="btn btn-sm btn-success w-100"><i class="bi bi-send"></i> Gönder</button></form>
      <?php foreach ($msgs as $m): ?><div class="mt-2 small"><div class="wa-bubble"><?= e(mb_strimwidth($m['body'], 0, 160, '…')) ?></div><span class="text-muted"><?= dtt($m['created_at']) ?></span> <?= status_badge($m['status']) ?></div><?php endforeach; ?>
    </div></div>

    <div class="card mb-3"><div class="card-header"><i class="bi bi-eyedropper text-danger me-1"></i> Planlı Aşılar</div><div class="card-body py-1">
      <?php foreach ($upcomingVacc as $v): ?><div class="list-row"><div class="flex-grow-1"><b><?= e($v['hasta']) ?></b> · <?= e($v['asi']) ?></div><span class="small <?= $v['due_date'] < today() ? 'text-danger fw-bold' : '' ?>"><?= dt($v['due_date']) ?></span></div><?php endforeach; ?>
      <?php if (!$upcomingVacc): ?><div class="empty py-3">Planlı aşı yok.</div><?php endif; ?>
    </div></div>

    <div class="card"><div class="card-header"><i class="bi bi-calendar-heart text-primary me-1"></i> Randevular</div><div class="card-body py-1">
      <?php foreach ($appts as $a): ?><div class="list-row"><div class="flex-grow-1"><b><?= dtt($a['start_at']) ?></b><div class="small text-muted"><?= e($a['hasta'] ?: '-') ?> · <?= e($a['type']) ?></div></div><?= status_badge($a['status']) ?></div><?php endforeach; ?>
      <?php if (!$appts): ?><div class="empty py-3">Randevu yok.</div><?php endif; ?>
    </div></div>
  </div>
</div>
<?php include APP . '/views/admin_footer.php';
