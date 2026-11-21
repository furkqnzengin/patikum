<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$active = 'hasta';
$id = (int)get('id', post('id'));
$p = row('SELECT p.*, s.name AS tur, s.icon, c.name AS sahip, c.phone, c.id AS cid FROM patients p LEFT JOIN species s ON s.id = p.species_id JOIN customers c ON c.id = p.customer_id WHERE p.id = ?', [$id]);
if (!$p) {
    flash('danger', 'Hasta bulunamadı.');
    redirect(url('admin/hastalar.php'));
}

if (is_post()) {
    $act = post('islem');
    if ($act === 'muayene') {
        $price = num(post('price'));
        $date = (post('exam_date') ?: today()) . ' ' . (post('exam_time') ?: date('H:i')) . ':00';
        $saleId = null;
        if ($price > 0) {
            $saleId = sale_create(['customer_id' => $p['cid'], 'patient_id' => $id, 'sale_date' => $date, 'note' => post('type') . ' - ' . $p['name'], 'source' => 'muayene', 'paid' => num(post('paid')), 'pay_method' => post('pay_method', 'nakit')],
                [['product_id' => null, 'description' => post('type') . ' (' . $p['name'] . ')', 'qty' => 1, 'unit_price' => $price, 'vat_rate' => (int)setting('service_vat', '20')]]);
        }
        insert('examinations', [
            'patient_id' => $id, 'user_id' => $_SESSION['uid'], 'exam_date' => $date, 'type' => post('type', 'Muayene'),
            'complaint' => nul(post('complaint')), 'findings' => nul(post('findings')), 'diagnosis' => nul(post('diagnosis')), 'treatment' => nul(post('treatment')),
            'weight' => nul(post('weight')) !== null ? num(post('weight')) : null, 'temperature' => nul(post('temperature')) !== null ? num(post('temperature')) : null,
            'price' => $price, 'sale_id' => $saleId,
        ]);
        if (nul(post('weight')) !== null) update('patients', ['weight' => num(post('weight'))], $id);
        if ($saleId) wa_auto('satis_bilgi', (int)$p['cid'], ['tarih' => dt($date), 'tutar' => money($price), 'borc' => money(max(0, customer_balance((int)$p['cid'])))], 'sale:' . $saleId);
        flash('success', 'İşlem kaydedildi' . ($price > 0 ? ' ve ' . money($price) . ' müşteri hesabına işlendi.' : '.'));
    }
    if ($act === 'muayene_sil' && is_admin()) {
        $ex = row('SELECT * FROM examinations WHERE id = ? AND patient_id = ?', [(int)post('exam_id'), $id]);
        if ($ex) {
            if ($ex['sale_id']) sale_cancel((int)$ex['sale_id']);
            delete_row('examinations', (int)$ex['id']);
            flash('success', 'İşlem kaydı silindi.');
        }
    }
    if ($act === 'asi_ekle') {
        $vid = insert('vaccinations', ['patient_id' => $id, 'vaccine_type_id' => (int)post('vaccine_type_id'), 'dose_label' => nul(post('dose_label')), 'due_date' => post('due_date') ?: today(), 'status' => 'planlandi', 'notes' => nul(post('notes'))]);
        if (post('yapildi')) vaccination_done($vid, ['done_date' => post('due_date') ?: today(), 'price' => post('price') !== '' ? num(post('price')) : null, 'batch_no' => post('batch_no')]);
        flash('success', 'Aşı kaydı eklendi.');
    }
    redirect(url('admin/hasta.php?id=' . $id . '#' . (post('islem') === 'asi_ekle' ? 'asilar' : 'islemler')));
}

$pageTitle = $p['name'];
$vaccs = rows('SELECT v.*, t.name AS asi FROM vaccinations v JOIN vaccine_types t ON t.id = v.vaccine_type_id WHERE v.patient_id = ? ORDER BY CASE WHEN v.status = \'planlandi\' THEN 0 ELSE 1 END, v.due_date', [$id]);
$exams = rows('SELECT e.*, u.name AS hekim FROM examinations e LEFT JOIN users u ON u.id = e.user_id WHERE e.patient_id = ? ORDER BY e.exam_date DESC', [$id]);
$appts = rows('SELECT * FROM appointments WHERE patient_id = ? ORDER BY start_at DESC LIMIT 10', [$id]);
$types = rows('SELECT * FROM vaccine_types WHERE active = 1 AND (species_id = ? OR species_id IS NULL) ORDER BY name', [$p['species_id']]);
$weights = array_reverse(array_filter($exams, fn($e) => $e['weight'] !== null));

include APP . '/views/admin_header.php'; ?>
<div class="page-head">
  <div class="d-flex align-items-center gap-3">
    <div class="pet-avatar lg"><?= species_icon($p['icon']) ?></div>
    <div><h1><?= e($p['name']) ?> <?= $p['status'] !== 'aktif' ? status_badge($p['status']) : '' ?></h1>
      <div class="sub"><?= e($p['tur']) ?> · <?= e($p['breed'] ?: 'Irk belirtilmemiş') ?> · <?= age_text($p['birth_date']) ?> · Sahibi: <a href="<?= url('admin/musteri.php?id=' . $p['cid']) ?>" class="fw-bold"><?= e($p['sahip']) ?></a></div></div>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a class="btn btn-primary" href="#islemler" data-bs-toggle="modal" data-bs-target="#mExam"><i class="bi bi-clipboard2-plus"></i> İşlem / Muayene Ekle</a>
    <a class="btn btn-outline-primary" href="<?= url('admin/asi-sihirbazi.php?hasta=' . $id) ?>"><i class="bi bi-magic"></i> Aşı Sihirbazı</a>
    <a class="btn btn-outline-primary" href="<?= url('admin/satis.php?musteri=' . $p['cid'] . '&hasta=' . $id) ?>"><i class="bi bi-cart-plus"></i> Satış</a>
    <a class="btn btn-light" href="<?= url('admin/hastalar.php?islem=duzenle&id=' . $id) ?>"><i class="bi bi-pencil"></i></a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card mb-3"><div class="card-header"><i class="bi bi-info-circle text-primary me-1"></i> Künye</div><div class="card-body">
      <dl class="row info-list mb-0">
        <dt class="col-6">Cinsiyet</dt><dd class="col-6"><?= $p['gender'] === 'erkek' ? '♂ Erkek' : ($p['gender'] === 'disi' ? '♀ Dişi' : '-') ?><?= $p['neutered'] ? ' · Kısır' : '' ?></dd>
        <dt class="col-6">Doğum</dt><dd class="col-6"><?= dt($p['birth_date']) ?></dd>
        <dt class="col-6">Kilo</dt><dd class="col-6"><?= $p['weight'] ? qty($p['weight']) . ' kg' : '-' ?></dd>
        <dt class="col-6">Renk</dt><dd class="col-6"><?= e($p['color'] ?: '-') ?></dd>
        <dt class="col-6">Mikroçip</dt><dd class="col-6"><?= e($p['chip_no'] ?: '-') ?></dd>
        <dt class="col-6">Pasaport</dt><dd class="col-6"><?= e($p['passport_no'] ?: '-') ?></dd>
        <dt class="col-6">Telefon</dt><dd class="col-6"><a href="<?= e(wa_link($p['phone'])) ?>" target="_blank"><i class="bi bi-whatsapp text-success"></i> <?= e(phone_fmt($p['phone'])) ?></a></dd>
      </dl>
      <?php if ($p['allergies']): ?><div class="alert alert-warning py-2 mt-2 mb-0 small"><i class="bi bi-exclamation-triangle"></i> <b>Alerji / Kronik:</b> <?= e($p['allergies']) ?></div><?php endif; ?>
      <?php if ($p['notes']): ?><div class="small text-muted mt-2"><?= nl2br(e($p['notes'])) ?></div><?php endif; ?>
    </div></div>
    <?php if (count($weights) > 1): ?>
    <div class="card mb-3"><div class="card-header"><i class="bi bi-graph-up text-primary me-1"></i> Kilo Takibi</div><div class="card-body"><canvas id="wChart" height="160"></canvas></div></div>
    <?php endif; ?>
    <div class="card"><div class="card-header"><i class="bi bi-calendar-heart text-primary me-1"></i> Randevular</div><div class="card-body py-1">
      <?php foreach ($appts as $a): ?><div class="list-row"><div class="flex-grow-1"><b><?= dtt($a['start_at']) ?></b><div class="small text-muted"><?= e($a['type']) ?></div></div><?= status_badge($a['status']) ?></div><?php endforeach; ?>
      <?php if (!$appts): ?><div class="empty py-3">Randevu yok. <a href="<?= url('admin/randevular.php?islem=yeni&musteri=' . $p['cid'] . '&hasta=' . $id) ?>">Oluştur</a></div><?php endif; ?>
    </div></div>
  </div>

  <div class="col-lg-8">
    <div class="card mb-3" id="asilar">
      <div class="card-header d-flex justify-content-between align-items-center"><span><i class="bi bi-eyedropper text-danger me-1"></i> Aşı Karnesi</span>
        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#mVacc"><i class="bi bi-plus-lg"></i> Aşı Ekle</button></div>
      <div class="table-responsive"><table class="table table-sm align-middle">
        <thead><tr><th>Aşı</th><th>Doz</th><th>Planlanan</th><th>Yapıldı</th><th>Seri / Ücret</th><th>Durum</th><th></th></tr></thead><tbody>
        <?php foreach ($vaccs as $v): $late = $v['status'] === 'planlandi' && $v['due_date'] < today(); ?>
          <tr class="<?= $late ? 'table-danger' : '' ?>">
            <td class="fw-bold"><?= e($v['asi']) ?></td><td class="small"><?= e($v['dose_label']) ?></td>
            <td><?= dt($v['due_date']) ?><?= $late ? ' <span class="badge text-bg-danger">Gecikti</span>' : '' ?></td>
            <td><?= dt($v['done_date']) ?></td><td class="small"><?= e($v['batch_no'] ?: '') ?> <?= $v['price'] > 0 ? money($v['price']) : '' ?></td>
            <td><?= status_badge($v['status']) ?></td>
            <td class="text-end text-nowrap">
              <?php if ($v['status'] === 'planlandi'): ?>
                <form method="post" action="<?= url('admin/asilar.php') ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="islem" value="yapildi"><input type="hidden" name="id" value="<?= $v['id'] ?>"><button class="btn btn-sm btn-outline-success" title="Yapıldı"><i class="bi bi-check-lg"></i></button></form>
              <?php endif; ?>
              <a class="btn btn-sm btn-light" href="<?= url('admin/asilar.php?islem=duzenle&id=' . $v['id']) ?>"><i class="bi bi-pencil"></i></a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$vaccs): ?><tr><td colspan="7" class="empty"><i class="bi bi-magic"></i>Aşı kaydı yok. <a href="<?= url('admin/asi-sihirbazi.php?hasta=' . $id) ?>">Aşı sihirbazı ile otomatik takvim oluşturun</a>.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>

    <div class="card" id="islemler">
      <div class="card-header d-flex justify-content-between align-items-center"><span><i class="bi bi-clipboard2-pulse text-primary me-1"></i> Yapılan İşlemler / Muayene Geçmişi</span>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#mExam"><i class="bi bi-plus-lg"></i> Ekle</button></div>
      <div class="card-body">
        <div class="timeline">
          <?php foreach ($exams as $ex): ?>
            <div class="item">
              <div class="d-flex justify-content-between flex-wrap gap-2">
                <div><span class="fw-bold"><?= e($ex['type']) ?></span> <span class="text-muted small">· <?= dtt($ex['exam_date']) ?> · <?= e($ex['hekim'] ?: '') ?></span></div>
                <div class="d-flex gap-2 align-items-center"><?= $ex['price'] > 0 ? '<span class="badge text-bg-light">' . money($ex['price']) . '</span>' : '' ?>
                  <?php if (is_admin()): ?><form method="post" data-confirm="Bu işlem kaydı silinsin mi? Bağlı satış da iptal edilir."><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="islem" value="muayene_sil"><input type="hidden" name="exam_id" value="<?= $ex['id'] ?>"><button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button></form><?php endif; ?></div>
              </div>
              <div class="small mt-1">
                <?php if ($ex['complaint']): ?><div><b>Şikayet:</b> <?= nl2br(e($ex['complaint'])) ?></div><?php endif; ?>
                <?php if ($ex['findings']): ?><div><b>Bulgular:</b> <?= nl2br(e($ex['findings'])) ?></div><?php endif; ?>
                <?php if ($ex['diagnosis']): ?><div><b>Tanı:</b> <?= nl2br(e($ex['diagnosis'])) ?></div><?php endif; ?>
                <?php if ($ex['treatment']): ?><div><b>Tedavi:</b> <?= nl2br(e($ex['treatment'])) ?></div><?php endif; ?>
                <?php if ($ex['weight'] || $ex['temperature']): ?><div class="text-muted"><?= $ex['weight'] ? 'Kilo: ' . qty($ex['weight']) . ' kg ' : '' ?><?= $ex['temperature'] ? ' · Ateş: ' . e($ex['temperature']) . ' °C' : '' ?></div><?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
          <?php if (!$exams): ?><div class="empty"><i class="bi bi-clipboard2"></i>Henüz işlem kaydı yok.</div><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="mExam" tabindex="-1"><div class="modal-dialog modal-lg"><form method="post" class="modal-content">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="islem" value="muayene">
  <div class="modal-header"><h5 class="modal-title fw-bold">İşlem / Muayene Ekle — <?= e($p['name']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><div class="row g-3">
    <div class="col-md-4"><label class="form-label">İşlem Türü</label><select name="type" class="form-select"><?= options(array_combine(exam_types(), exam_types()), 'Muayene') ?></select></div>
    <div class="col-md-4"><label class="form-label">Tarih</label><input type="date" name="exam_date" class="form-control" value="<?= today() ?>"></div>
    <div class="col-md-4"><label class="form-label">Saat</label><input type="time" name="exam_time" class="form-control" value="<?= date('H:i') ?>"></div>
    <div class="col-md-6"><label class="form-label">Şikayet / Anamnez</label><textarea name="complaint" class="form-control" rows="2"></textarea></div>
    <div class="col-md-6"><label class="form-label">Bulgular</label><textarea name="findings" class="form-control" rows="2"></textarea></div>
    <div class="col-md-6"><label class="form-label">Tanı</label><textarea name="diagnosis" class="form-control" rows="2"></textarea></div>
    <div class="col-md-6"><label class="form-label">Tedavi / Reçete</label><textarea name="treatment" class="form-control" rows="2"></textarea></div>
    <div class="col-md-3"><label class="form-label">Kilo (kg)</label><input name="weight" class="form-control"></div>
    <div class="col-md-3"><label class="form-label">Ateş (°C)</label><input name="temperature" class="form-control"></div>
    <div class="col-md-2"><label class="form-label">Ücret (₺)</label><input name="price" class="form-control" placeholder="0"></div>
    <div class="col-md-2"><label class="form-label">Alınan (₺)</label><input name="paid" class="form-control" placeholder="0"></div>
    <div class="col-md-2"><label class="form-label">Yöntem</label><select name="pay_method" class="form-select"><?= options(['nakit' => 'Nakit', 'kart' => 'Kart', 'havale' => 'Havale'], 'nakit') ?></select></div>
    <div class="col-12 form-text mt-0">Ücret girilirse tutar müşterinin cari hesabına borç olarak işlenir; alınan tutar tahsilat olarak düşülür.</div>
  </div></div>
  <div class="modal-footer"><button class="btn btn-primary px-4">Kaydet</button></div>
</form></div></div>

<div class="modal fade" id="mVacc" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="islem" value="asi_ekle">
  <div class="modal-header"><h5 class="modal-title fw-bold">Aşı Ekle</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><div class="row g-3">
    <div class="col-12"><label class="form-label">Aşı</label><select name="vaccine_type_id" class="form-select" required><?= options($types, '', 'id', 'name', '— Seçin —') ?></select></div>
    <div class="col-6"><label class="form-label">Tarih</label><input type="date" name="due_date" class="form-control" value="<?= today() ?>"></div>
    <div class="col-6"><label class="form-label">Doz</label><input name="dose_label" class="form-control" placeholder="1. Doz / Rapel"></div>
    <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="yapildi" value="1" id="yp"><label for="yp" class="form-check-label">Bu aşı yapıldı (stoktan düş, ücretlendir, sonraki rapeli planla)</label></div></div>
    <div class="col-6"><label class="form-label">Seri / Lot No</label><input name="batch_no" class="form-control"></div>
    <div class="col-6"><label class="form-label">Ücret (boş = ürün fiyatı)</label><input name="price" class="form-control"></div>
    <div class="col-12"><label class="form-label">Not</label><input name="notes" class="form-control"></div>
  </div></div>
  <div class="modal-footer"><button class="btn btn-danger px-4">Kaydet</button></div>
</form></div></div>
<?php
if (count($weights) > 1) {
    $pageScripts = '<script>new Chart(document.getElementById("wChart"),{type:"line",data:{labels:' . json_encode(array_values(array_map(fn($e) => dt($e['exam_date']), $weights))) . ',datasets:[{label:"kg",data:' . json_encode(array_values(array_map(fn($e) => (float)$e['weight'], $weights))) . ',borderColor:"#0f766e",backgroundColor:"rgba(15,118,110,.1)",fill:true,tension:.3}]},options:{plugins:{legend:{display:false}}}});</script>';
}
include APP . '/views/admin_footer.php';
