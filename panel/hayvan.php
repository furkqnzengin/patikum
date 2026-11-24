<?php
require __DIR__ . '/../app/bootstrap.php';
$c = require_customer();
$p = row('SELECT p.*, s.name AS tur, s.icon FROM patients p LEFT JOIN species s ON s.id = p.species_id WHERE p.id = ? AND p.customer_id = ?', [(int)get('id'), (int)$c['id']]);
if (!$p) redirect(url('panel/'));

$vaccs = rows("SELECT v.*, t.name AS asi FROM vaccinations v JOIN vaccine_types t ON t.id = v.vaccine_type_id WHERE v.patient_id = ? AND v.status <> 'iptal' ORDER BY CASE WHEN v.status = 'planlandi' THEN 0 ELSE 1 END, v.due_date", [$p['id']]);
$exams = rows('SELECT exam_date, type, complaint, diagnosis, treatment, weight, price FROM examinations WHERE patient_id = ? ORDER BY exam_date DESC', [$p['id']]);
$pageTitle = $p['name'];
$pageHead = '<a href="' . url('panel/') . '" class="text-white opacity-75 fs-5"><i class="bi bi-arrow-left"></i></a> ' . species_icon($p['icon']) . ' ' . e($p['name']);

include APP . '/views/panel_header.php'; ?>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="pcard"><div class="pcard-b text-center">
      <div class="pet-tile border-0 justify-content-center flex-column p-0"><div class="av" style="width:96px;height:96px;font-size:3.2rem"><?= species_icon($p['icon']) ?></div></div>
      <h2 class="h3 mt-2 mb-0"><?= e($p['name']) ?></h2><div class="text-muted"><?= e($p['tur']) ?><?= $p['breed'] ? ' · ' . e($p['breed']) : '' ?></div>
    </div><div class="pcard-b border-top">
      <div class="row small g-2">
        <div class="col-6 text-muted">Yaş</div><div class="col-6 fw-bold"><?= age_text($p['birth_date']) ?></div>
        <div class="col-6 text-muted">Doğum</div><div class="col-6 fw-bold"><?= dt($p['birth_date']) ?></div>
        <div class="col-6 text-muted">Cinsiyet</div><div class="col-6 fw-bold"><?= $p['gender'] === 'erkek' ? 'Erkek' : ($p['gender'] === 'disi' ? 'Dişi' : '-') ?><?= $p['neutered'] ? ' · Kısır' : '' ?></div>
        <div class="col-6 text-muted">Kilo</div><div class="col-6 fw-bold"><?= $p['weight'] ? qty($p['weight']) . ' kg' : '-' ?></div>
        <div class="col-6 text-muted">Mikroçip</div><div class="col-6 fw-bold"><?= e($p['chip_no'] ?: '-') ?></div>
      </div>
    </div></div>
  </div>
  <div class="col-lg-8">
    <div class="pcard mb-3"><div class="pcard-h"><span><i class="bi bi-journal-medical text-danger"></i> Aşı Karnesi</span><button class="btn btn-sm btn-light" onclick="window.print()"><i class="bi bi-printer"></i></button></div>
      <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Aşı</th><th>Doz</th><th>Tarih</th><th>Durum</th></tr></thead><tbody>
      <?php foreach ($vaccs as $v): $late = $v['status'] === 'planlandi' && $v['due_date'] < today(); ?>
        <tr><td class="fw-bold"><?= e($v['asi']) ?></td><td class="small"><?= e($v['dose_label']) ?></td>
          <td><?= $v['status'] === 'yapildi' ? dt($v['done_date']) : dt($v['due_date']) ?></td>
          <td><?php $dd = days_diff($v['due_date']); ?><?= $v['status'] === 'yapildi' ? '<span class="badge text-bg-success">Yapıldı</span>' : ($late ? '<span class="badge text-bg-danger">' . abs($dd) . ' gün gecikti</span>' : '<span class="badge text-bg-info">' . ($dd === 0 ? 'Bugün' : $dd . ' gün sonra') . '</span>') ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$vaccs): ?><tr><td colspan="4" class="text-muted">Aşı kaydı yok.</td></tr><?php endif; ?>
      </tbody></table></div></div>

    <div class="pcard"><div class="pcard-h"><span><i class="bi bi-clipboard2-pulse text-primary"></i> Yapılan İşlemler</span></div><div class="pcard-b">
      <div class="tl">
        <?php foreach ($exams as $ex): ?>
          <div class="it"><div class="d-flex justify-content-between"><b><?= e($ex['type']) ?></b><span class="small text-muted"><?= dtt($ex['exam_date']) ?></span></div>
            <?php if ($ex['complaint']): ?><div class="small"><span class="text-muted">Şikayet:</span> <?= e($ex['complaint']) ?></div><?php endif; ?>
            <?php if ($ex['diagnosis']): ?><div class="small"><span class="text-muted">Tanı:</span> <?= e($ex['diagnosis']) ?></div><?php endif; ?>
            <?php if ($ex['treatment']): ?><div class="small"><span class="text-muted">Tedavi:</span> <?= e($ex['treatment']) ?></div><?php endif; ?>
            <?php if ($ex['price'] > 0): ?><div class="small fw-bold"><?= money($ex['price']) ?></div><?php endif; ?></div>
        <?php endforeach; ?>
        <?php foreach (array_filter($vaccs, fn($v) => $v['status'] === 'yapildi') as $v): ?>
          <div class="it v"><div class="d-flex justify-content-between"><b>Aşı: <?= e($v['asi']) ?></b><span class="small text-muted"><?= dt($v['done_date']) ?></span></div></div>
        <?php endforeach; ?>
        <?php if (!$exams && !array_filter($vaccs, fn($v) => $v['status'] === 'yapildi')): ?><div class="text-muted small">Henüz işlem kaydı yok.</div><?php endif; ?>
      </div>
    </div></div>
  </div>
</div>
<?php include APP . '/views/panel_footer.php';
