<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$pageTitle = 'Randevular';
$active = 'randevu';

function appt_notify(int $id, string $code): ?int
{
    $a = row('SELECT a.*, p.name AS hasta FROM appointments a LEFT JOIN patients p ON p.id = a.patient_id WHERE a.id = ?', [$id]);
    $vars = ['hasta' => $a['hasta'] ?: ($a['pet_info'] ?: 'dostumuz'), 'tarih' => dtt($a['start_at']), 'saat' => tm($a['start_at']), 'islem' => $a['type']];
    if ($a['customer_id']) return wa_auto($code, (int)$a['customer_id'], $vars, "$code:$id:" . $a['start_at']);
    $tpl = wa_template($code);
    if ($tpl && $tpl['active'] && $tpl['auto_send'] && $a['guest_phone']) return wa_queue(null, $a['guest_phone'], $code, $vars + ['musteri' => $a['guest_name']], "$code:$id:" . $a['start_at']);
    return null;
}

if (is_post()) {
    $act = post('islem');
    $id = (int)post('id');
    if ($act === 'kaydet') {
        $data = [
            'customer_id' => nul(post('customer_id')), 'patient_id' => nul(post('patient_id')),
            'guest_name' => nul(post('guest_name')), 'guest_phone' => nul(post('guest_phone')), 'pet_info' => nul(post('pet_info')),
            'start_at' => post('date') . ' ' . post('time') . ':00', 'duration' => (int)post('duration', 30), 'type' => post('type', 'Muayene'),
            'status' => post('status', 'onayli'), 'notes' => nul(post('notes')),
        ];
        if (!$data['customer_id'] && !$data['guest_name']) {
            flash('danger', 'Müşteri seçin veya misafir adı girin.');
            back();
        }
        $clash = val("SELECT COUNT(*) FROM appointments WHERE id <> ? AND status IN ('onayli','talep') AND start_at = ?", [$id, $data['start_at']]);
        $old = $id ? row('SELECT status, start_at FROM appointments WHERE id = ?', [$id]) : null;
        if ($id) update('appointments', $data + ($old && $old['start_at'] !== $data['start_at'] ? ['reminded_at' => null] : []), $id);
        else $id = insert('appointments', $data + ['source' => 'admin']);
        if ($data['status'] === 'onayli' && (!$old || $old['status'] !== 'onayli' || $old['start_at'] !== $data['start_at']) && post('wa')) appt_notify($id, 'randevu_onay');
        flash('success', 'Randevu kaydedildi.' . ($clash ? ' <b>Uyarı:</b> aynı saatte başka randevu var.' : ''));
        redirect(url('admin/randevular.php' . (post('return') === 'takvim' ? '?gorunum=takvim&ay=' . substr($data['start_at'], 0, 7) : '')));
    }
    if ($act === 'durum') {
        $st = post('status');
        if (in_array($st, ['onayli', 'tamamlandi', 'iptal', 'gelmedi'], true)) {
            update('appointments', ['status' => $st], $id);
            if ($st === 'onayli') appt_notify($id, 'randevu_onay');
            flash('success', 'Randevu durumu güncellendi.');
        }
    }
    if ($act === 'sil') {
        delete_row('appointments', $id);
        flash('success', 'Randevu silindi.');
    }
    back(url('admin/randevular.php'));
}

$islem = get('islem');
if ($islem === 'yeni' || $islem === 'duzenle') {
    $a = $islem === 'duzenle' ? row('SELECT * FROM appointments WHERE id = ?', [(int)get('id')]) : null;
    $a = $a ?: ['id' => 0, 'customer_id' => get('musteri'), 'patient_id' => get('hasta'), 'guest_name' => '', 'guest_phone' => '', 'pet_info' => '', 'start_at' => (get('tarih') ?: today()) . ' ' . date('H', strtotime('+1 hour')) . ':00:00', 'duration' => 30, 'type' => 'Muayene', 'status' => 'onayli', 'notes' => '', 'source' => 'admin'];
    $owner = $a['customer_id'] ? row('SELECT id, name, phone FROM customers WHERE id = ?', [$a['customer_id']]) : null;
    include APP . '/views/admin_header.php'; ?>
    <div class="page-head"><div><h1><?= $a['id'] ? 'Randevuyu Düzenle' : 'Yeni Randevu' ?></h1><?php if ($a['source'] === 'web'): ?><div class="sub"><span class="badge text-bg-warning">Web sitesinden gelen talep</span></div><?php endif; ?></div>
      <a href="<?= url('admin/randevular.php') ?>" class="btn btn-light"><i class="bi bi-arrow-left"></i> Listeye dön</a></div>
    <form method="post" class="card"><div class="card-body">
      <?= csrf_field() ?><input type="hidden" name="islem" value="kaydet"><input type="hidden" name="id" value="<?= $a['id'] ?>"><input type="hidden" name="return" value="<?= e(get('return')) ?>">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label fw-bold">Müşteri</label>
          <select name="customer_id" id="rCust" class="js-customer"><?php if ($owner): ?><option value="<?= $owner['id'] ?>" selected><?= e($owner['name'] . ' · ' . phone_fmt($owner['phone'])) ?></option><?php endif; ?></select></div>
        <div class="col-md-6"><label class="form-label fw-bold">Hasta</label>
          <select name="patient_id" class="form-select js-patient" data-customer="#rCust" data-selected="<?= e($a['patient_id']) ?>"><option value="">— Önce müşteri seçin —</option></select></div>
        <div class="col-12"><details <?= ($a['guest_name'] || !$owner) && $a['id'] ? 'open' : '' ?>><summary class="small text-primary fw-bold">Kayıtlı olmayan müşteri (misafir / web talebi)</summary>
          <div class="row g-2 mt-1">
            <div class="col-md-4"><input name="guest_name" class="form-control" placeholder="Ad Soyad" value="<?= e($a['guest_name']) ?>"></div>
            <div class="col-md-4"><input name="guest_phone" class="form-control" placeholder="Telefon" value="<?= e($a['guest_phone']) ?>"></div>
            <div class="col-md-4"><input name="pet_info" class="form-control" placeholder="Hayvan bilgisi (Kedi - Minnoş)" value="<?= e($a['pet_info']) ?>"></div>
            <?php if ($a['guest_phone']): ?><div class="col-12 small"><a href="<?= e(wa_link($a['guest_phone'])) ?>" target="_blank"><i class="bi bi-whatsapp text-success"></i> Misafire WhatsApp'tan yaz</a> · Kalıcı kayıt için <a href="<?= url('admin/musteriler.php?islem=yeni') ?>" target="_blank">yeni müşteri oluşturup</a> yukarıdan seçin.</div><?php endif; ?>
          </div></details></div>
        <div class="col-md-3"><label class="form-label fw-bold">Tarih</label><input type="date" name="date" class="form-control" required value="<?= substr($a['start_at'], 0, 10) ?>"></div>
        <div class="col-md-2"><label class="form-label fw-bold">Saat</label><input type="time" name="time" class="form-control" required step="300" value="<?= substr($a['start_at'], 11, 5) ?>"></div>
        <div class="col-md-2"><label class="form-label">Süre (dk)</label><select name="duration" class="form-select"><?= options(['15' => '15', '30' => '30', '45' => '45', '60' => '60', '90' => '90', '120' => '120'], (string)$a['duration']) ?></select></div>
        <div class="col-md-3"><label class="form-label">İşlem</label><select name="type" class="form-select"><?= options(array_combine(appointment_types(), appointment_types()), $a['type']) ?></select></div>
        <div class="col-md-2"><label class="form-label">Durum</label><select name="status" class="form-select"><?= options(['talep' => 'Talep', 'onayli' => 'Onaylı', 'tamamlandi' => 'Tamamlandı', 'gelmedi' => 'Gelmedi', 'iptal' => 'İptal'], $a['status']) ?></select></div>
        <div class="col-12"><label class="form-label">Not</label><textarea name="notes" class="form-control" rows="2"><?= e($a['notes']) ?></textarea></div>
        <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="wa" value="1" id="wa" checked><label for="wa" class="form-check-label"><i class="bi bi-whatsapp text-success"></i> Onaylanınca müşteriye WhatsApp ile randevu bilgisi gönder</label></div></div>
      </div>
    </div><div class="card-footer bg-white d-flex justify-content-between">
      <?php if ($a['id']): ?><button name="islem" value="sil" class="btn btn-link text-danger" data-confirm="Randevu silinsin mi?">Sil</button><?php else: ?><span></span><?php endif; ?>
      <button class="btn btn-primary px-4"><i class="bi bi-check-lg"></i> Kaydet</button></div></form>
    <?php include APP . '/views/admin_footer.php';
    exit;
}

$view = get('gorunum', 'liste');
$base = "SELECT a.*, p.name AS hasta, s.icon, c.name AS sahip, c.phone FROM appointments a LEFT JOIN patients p ON p.id = a.patient_id LEFT JOIN species s ON s.id = p.species_id LEFT JOIN customers c ON c.id = a.customer_id";

if ($view === 'takvim') {
    $ym = preg_match('/^\d{4}-\d{2}$/', (string)get('ay')) ? get('ay') : date('Y-m');
    $first = strtotime($ym . '-01');
    $gridStart = strtotime('-' . ((int)date('N', $first) - 1) . ' days', $first);
    $gridEnd = strtotime('+41 days', $gridStart);
    $evs = [];
    foreach (rows("$base WHERE a.start_at >= ? AND a.start_at < ? ORDER BY a.start_at", [date('Y-m-d', $gridStart), date('Y-m-d', $gridEnd + 86400)]) as $r) $evs[substr($r['start_at'], 0, 10)][] = $r;
    include APP . '/views/admin_header.php'; ?>
    <div class="page-head"><div><h1>Randevu Takvimi</h1><div class="sub"><?= tr_month((int)date('n', $first)) ?> <?= date('Y', $first) ?></div></div>
      <div class="d-flex gap-2">
        <a class="btn btn-light" href="?gorunum=takvim&ay=<?= date('Y-m', strtotime('-1 month', $first)) ?>"><i class="bi bi-chevron-left"></i></a>
        <a class="btn btn-light" href="?gorunum=takvim">Bugün</a>
        <a class="btn btn-light" href="?gorunum=takvim&ay=<?= date('Y-m', strtotime('+1 month', $first)) ?>"><i class="bi bi-chevron-right"></i></a>
        <a class="btn btn-outline-primary" href="?gorunum=liste"><i class="bi bi-list-ul"></i> Liste</a>
        <a class="btn btn-primary" href="?islem=yeni&return=takvim"><i class="bi bi-plus-lg"></i> Yeni</a></div></div>
    <div class="cal-head"><?php foreach (['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'] as $d): ?><div><?= $d ?></div><?php endforeach; ?></div>
    <div class="cal-grid">
      <?php for ($i = 0; $i < 42; $i++): $t = strtotime("+$i days", $gridStart); $d = date('Y-m-d', $t); ?>
        <div class="d <?= date('m', $t) !== date('m', $first) ? 'out' : '' ?> <?= $d === today() ? 'today' : '' ?>">
          <div class="d-flex justify-content-between"><span class="n"><?= date('j', $t) ?></span><a href="?islem=yeni&tarih=<?= $d ?>&return=takvim" class="text-muted"><i class="bi bi-plus"></i></a></div>
          <?php foreach ($evs[$d] ?? [] as $ev): ?>
            <a class="ev <?= e($ev['status']) ?>" href="?islem=duzenle&id=<?= $ev['id'] ?>&return=takvim" title="<?= e(($ev['sahip'] ?: $ev['guest_name']) . ' - ' . $ev['type']) ?>"><?= tm($ev['start_at']) ?> <?= e($ev['hasta'] ?: ($ev['pet_info'] ?: $ev['guest_name'])) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endfor; ?>
    </div>
    <?php include APP . '/views/admin_footer.php';
    exit;
}

$from = get('baslangic', today());
$to = get('bitis', date('Y-m-d', strtotime('+30 days')));
$st = get('durum', '');
$sql = "$base WHERE 1=1";
$prm = [];
if ($st === 'talep') { $sql .= " AND a.status = 'talep'"; }
else {
    $sql .= ' AND a.start_at >= ? AND a.start_at < ?';
    $prm = [$from, date('Y-m-d', strtotime($to . ' +1 day'))];
    if ($st) { $sql .= ' AND a.status = ?'; $prm[] = $st; }
}
$pg = paginate($sql . ' ORDER BY a.start_at', $prm, 40);

include APP . '/views/admin_header.php'; ?>
<div class="page-head"><div><h1>Randevular</h1><div class="sub"><?= $pg['total'] ?> randevu</div></div>
  <div class="d-flex gap-2"><a class="btn btn-outline-primary" href="?gorunum=takvim"><i class="bi bi-calendar3"></i> Takvim</a><a class="btn btn-primary" href="?islem=yeni"><i class="bi bi-plus-lg"></i> Yeni Randevu</a></div></div>
<form class="card mb-3"><div class="card-body row g-2 align-items-end">
  <div class="col-md-3"><label class="form-label small">Başlangıç</label><input type="date" name="baslangic" class="form-control" value="<?= e($from) ?>"></div>
  <div class="col-md-3"><label class="form-label small">Bitiş</label><input type="date" name="bitis" class="form-control" value="<?= e($to) ?>"></div>
  <div class="col-md-3"><label class="form-label small">Durum</label><select name="durum" class="form-select"><?= options(['' => 'Tümü', 'talep' => 'Web Talepleri', 'onayli' => 'Onaylı', 'tamamlandi' => 'Tamamlandı', 'gelmedi' => 'Gelmedi', 'iptal' => 'İptal'], $st) ?></select></div>
  <div class="col-md-3"><button class="btn btn-light w-100">Filtrele</button></div>
</div></form>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle">
  <thead><tr><th>Tarih</th><th>Hasta</th><th>Müşteri</th><th>İşlem</th><th>Durum</th><th></th></tr></thead><tbody>
  <?php foreach ($pg['rows'] as $a): $ph = $a['phone'] ?: $a['guest_phone']; ?>
    <tr>
      <td class="fw-bold"><?= dt($a['start_at']) ?> <span class="text-primary"><?= tm($a['start_at']) ?></span><div class="small text-muted"><?= tr_day((int)date('w', strtotime($a['start_at']))) ?> · <?= (int)$a['duration'] ?> dk</div></td>
      <td><?= species_icon($a['icon'] ?? null) ?> <?= e($a['hasta'] ?: ($a['pet_info'] ?: '-')) ?></td>
      <td><?= $a['customer_id'] ? '<a href="' . url('admin/musteri.php?id=' . $a['customer_id']) . '">' . e($a['sahip']) . '</a>' : e($a['guest_name']) . ' <span class="badge text-bg-light">misafir</span>' ?><div class="small text-muted"><?= e(phone_fmt($ph)) ?></div></td>
      <td><span class="badge badge-soft"><?= e($a['type']) ?></span><?= $a['source'] === 'web' ? ' <span class="badge text-bg-light"><i class="bi bi-globe2"></i></span>' : '' ?><?= $a['source'] === 'panel' ? ' <span class="badge text-bg-light"><i class="bi bi-person"></i> panel</span>' : '' ?></td>
      <td><?= status_badge($a['status']) ?></td>
      <td class="text-end text-nowrap">
        <?php if (in_array($a['status'], ['talep', 'onayli'], true)): ?>
          <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="islem" value="durum"><input type="hidden" name="id" value="<?= $a['id'] ?>">
            <?php if ($a['status'] === 'talep'): ?><button name="status" value="onayli" class="btn btn-sm btn-outline-primary" title="Onayla"><i class="bi bi-check2"></i> Onayla</button>
            <?php else: ?><button name="status" value="tamamlandi" class="btn btn-sm btn-outline-success" title="Tamamlandı"><i class="bi bi-check2-all"></i></button>
              <button name="status" value="gelmedi" class="btn btn-sm btn-outline-danger" title="Gelmedi"><i class="bi bi-person-x"></i></button><?php endif; ?></form>
        <?php endif; ?>
        <?php if ($ph): ?><a class="btn btn-sm btn-light text-success" target="_blank" href="<?= e(wa_link($ph)) ?>"><i class="bi bi-whatsapp"></i></a><?php endif; ?>
        <a class="btn btn-sm btn-light" href="?islem=duzenle&id=<?= $a['id'] ?>"><i class="bi bi-pencil"></i></a>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$pg['rows']): ?><tr><td colspan="6" class="empty"><i class="bi bi-calendar-x"></i>Bu aralıkta randevu yok.</td></tr><?php endif; ?>
  </tbody></table></div><div class="card-footer bg-white"><?= pager($pg) ?></div></div>
<?php include APP . '/views/admin_footer.php';
