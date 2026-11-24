<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$pageTitle = 'Aşı Tanımları & Protokoller';
$active = 'asi';

if (is_post()) {
    $act = post('islem');
    if ($act === 'kaydet') {
        $sid = (int)post('species_id');
        $tid = (int)post('type_id');
        $td = ['species_id' => $sid ?: null, 'name' => post('name'), 'description' => nul(post('description')), 'product_id' => nul(post('product_id')), 'active' => post('active') ? 1 : 0];
        if ($tid) update('vaccine_types', $td, $tid); else $tid = insert('vaccine_types', $td);
        $pd = ['species_id' => $sid, 'vaccine_type_id' => $tid, 'first_age_weeks' => (int)post('first_age_weeks'), 'doses' => max(1, (int)post('doses')),
            'interval_days' => max(1, (int)post('interval_days')), 'booster_days' => nul(post('booster_days')) !== null ? (int)post('booster_days') : null, 'sort' => (int)post('sort'), 'active' => post('active') ? 1 : 0];
        if ($sid) {
            $pidp = (int)val('SELECT id FROM vaccine_protocols WHERE vaccine_type_id = ? AND species_id = ?', [$tid, $sid]);
            $pidp ? update('vaccine_protocols', $pd, $pidp) : insert('vaccine_protocols', $pd);
        }
        flash('success', 'Aşı tanımı kaydedildi.');
    }
    if ($act === 'sil' && is_admin()) {
        $tid = (int)post('type_id');
        if (val('SELECT COUNT(*) FROM vaccinations WHERE vaccine_type_id = ?', [$tid])) {
            update('vaccine_types', ['active' => 0], $tid);
            q('UPDATE vaccine_protocols SET active = 0 WHERE vaccine_type_id = ?', [$tid]);
            flash('warning', 'Bu aşının kayıtları olduğu için silinmedi, pasif yapıldı.');
        } else {
            delete_row('vaccine_types', $tid);
            flash('success', 'Aşı tanımı silindi.');
        }
    }
    if ($act === 'akis_ekle') {
        $from = (int)post('from_type_id'); $to = (int)post('to_type_id'); $days = max(0, (int)post('days'));
        if ($from && $to) {
            insert('vaccine_flow', ['species_id' => nul(post('species_id')), 'from_type_id' => $from, 'from_dose' => array_key_exists(post('from_dose'), vdose_options(true)) ? post('from_dose') : '*',
                'to_type_id' => $to, 'to_dose' => array_key_exists(post('to_dose'), vdose_options()) ? post('to_dose') : null, 'days' => $days, 'sort' => (int)val('SELECT COALESCE(MAX(sort),0)+1 FROM vaccine_flow'), 'active' => 1]);
            flash('success', 'Aşı akışı kuralı eklendi.');
        }
    }
    if ($act === 'akis_gun') {
        foreach ((array)($_POST['gun'] ?? []) as $fid => $g) update('vaccine_flow', ['days' => max(0, (int)$g)], (int)$fid);
        flash('success', 'Süreler kaydedildi.');
    }
    if ($act === 'akis_sil') {
        delete_row('vaccine_flow', (int)post('flow_id'));
        flash('success', 'Kural silindi.');
    }
    if ($act === 'akis_varsayilan' && is_admin()) {
        q('DELETE FROM vaccine_flow');
        vaccine_flow_seed();
        flash('success', 'Aşı akışı varsayılan klinik protokolüne döndürüldü.');
    }
    redirect(url('admin/asi-tanimlari.php?tur=' . (int)post('species_id') . (str_starts_with((string)$act, 'akis') ? '#akis' : '')));
}

$sp = (int)get('tur', species_list()[0]['id'] ?? 0);
$list = rows('SELECT t.*, p.first_age_weeks, p.doses, p.interval_days, p.booster_days, p.sort, pr.name AS urun FROM vaccine_types t
              LEFT JOIN vaccine_protocols p ON p.vaccine_type_id = t.id AND p.species_id = t.species_id LEFT JOIN products pr ON pr.id = t.product_id
              WHERE t.species_id = ? ORDER BY t.active DESC, p.sort, t.name', [$sp]);
$edit = get('duzenle') ? row('SELECT t.*, p.first_age_weeks, p.doses, p.interval_days, p.booster_days, p.sort FROM vaccine_types t LEFT JOIN vaccine_protocols p ON p.vaccine_type_id = t.id AND p.species_id = t.species_id WHERE t.id = ?', [(int)get('duzenle')]) : null;
$e = $edit ?: ['id' => 0, 'name' => '', 'description' => '', 'product_id' => null, 'active' => 1, 'first_age_weeks' => 8, 'doses' => 1, 'interval_days' => 15, 'booster_days' => 365, 'sort' => count($list)];
$eprod = $e['product_id'] ? row('SELECT id, name FROM products WHERE id = ?', [$e['product_id']]) : null;

$flows = rows('SELECT f.*, a.name AS from_name, b.name AS to_name FROM vaccine_flow f JOIN vaccine_types a ON a.id = f.from_type_id JOIN vaccine_types b ON b.id = f.to_type_id
               WHERE a.species_id = ? ORDER BY f.sort, f.id', [$sp]);
$typesSp = rows('SELECT id, name FROM vaccine_types WHERE species_id = ? AND active = 1 ORDER BY name', [$sp]);
$fmtDays = fn(int $d) => $d % 365 === 0 && $d ? ($d / 365) . ' yıl' : ($d % 30 === 0 && $d >= 60 ? ($d / 30) . ' ay' : $d . ' gün');

include APP . '/views/admin_header.php'; ?>
<div class="page-head"><div><h1>Aşı Tanımları & Protokoller</h1><div class="sub">Aşı sihirbazı bu kurallara göre takvim oluşturur. Klinik protokolünüze göre düzenleyin.</div></div></div>
<div class="d-flex flex-wrap gap-2 mb-3">
  <?php foreach (species_list() as $s): ?><a href="?tur=<?= $s['id'] ?>" class="btn btn-sm rounded-pill <?= $sp === (int)$s['id'] ? 'btn-primary' : 'btn-light' ?>"><?= species_icon($s['icon']) ?> <?= e($s['name']) ?></a><?php endforeach; ?>
</div>
<div class="row g-3">
  <div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table table-hover align-middle">
    <thead><tr><th>Aşı</th><th>İlk Yaş</th><th>Doz</th><th>Doz Arası</th><th>Rapel</th><th>Ürün</th><th></th></tr></thead><tbody>
    <?php foreach ($list as $t): ?>
      <tr class="<?= $t['active'] ? '' : 'opacity-50' ?>">
        <td class="fw-bold"><?= e($t['name']) ?><?= $t['description'] ? '<div class="small text-muted fw-normal">' . e($t['description']) . '</div>' : '' ?></td>
        <td><?= (int)$t['first_age_weeks'] ?> hf</td><td><?= (int)$t['doses'] ?></td><td><?= (int)$t['doses'] > 1 ? (int)$t['interval_days'] . ' gün' : '-' ?></td>
        <td><?= $t['booster_days'] ? ((int)$t['booster_days'] % 365 === 0 ? ((int)$t['booster_days'] / 365) . ' yıl' : (int)$t['booster_days'] . ' gün') : '-' ?></td>
        <td class="small"><?= e($t['urun'] ?: '-') ?></td>
        <td class="text-end"><a class="btn btn-sm btn-light" href="?tur=<?= $sp ?>&duzenle=<?= $t['id'] ?>"><i class="bi bi-pencil"></i></a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="7" class="empty">Bu tür için aşı tanımı yok.</td></tr><?php endif; ?>
  </tbody></table></div></div></div>
  <div class="col-lg-4">
    <form method="post" class="card"><div class="card-header"><?= $e['id'] ? 'Aşıyı Düzenle' : 'Yeni Aşı Tanımı' ?></div><div class="card-body">
      <?= csrf_field() ?><input type="hidden" name="islem" value="kaydet"><input type="hidden" name="type_id" value="<?= $e['id'] ?>"><input type="hidden" name="species_id" value="<?= $sp ?>">
      <div class="mb-2"><label class="form-label">Aşı Adı</label><input name="name" class="form-control" required value="<?= e($e['name']) ?>"></div>
      <div class="mb-2"><label class="form-label">Açıklama</label><input name="description" class="form-control" value="<?= e($e['description']) ?>"></div>
      <div class="row g-2">
        <div class="col-6"><label class="form-label">İlk Yaş (hafta)</label><input type="number" name="first_age_weeks" class="form-control" value="<?= (int)$e['first_age_weeks'] ?>"></div>
        <div class="col-6"><label class="form-label">Doz Sayısı</label><input type="number" min="1" name="doses" class="form-control" value="<?= (int)$e['doses'] ?>"></div>
        <div class="col-6"><label class="form-label">Doz Arası (gün)</label><input type="number" name="interval_days" class="form-control" value="<?= (int)$e['interval_days'] ?>"></div>
        <div class="col-6"><label class="form-label">Rapel (gün)</label><input type="number" name="booster_days" class="form-control" value="<?= e($e['booster_days']) ?>" placeholder="365"></div>
        <div class="col-12"><label class="form-label">Varsayılan Ürün (stok/fiyat)</label><select name="product_id" id="tprod"><?php if ($eprod): ?><option value="<?= $eprod['id'] ?>" selected><?= e($eprod['name']) ?></option><?php endif; ?></select></div>
        <div class="col-6"><label class="form-label">Sıra</label><input type="number" name="sort" class="form-control" value="<?= (int)$e['sort'] ?>"></div>
        <div class="col-6 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="active" value="1" id="ac" <?= $e['active'] ? 'checked' : '' ?>><label for="ac" class="form-check-label">Aktif</label></div></div>
      </div>
      <div class="form-text">Örnek: Karma aşı 8. haftada başlar, 15 gün arayla 2 doz, ardından her 365 günde bir rapel.</div>
    </div><div class="card-footer bg-white d-flex justify-content-between">
      <?php if ($e['id'] && is_admin()): ?><button name="islem" value="sil" class="btn btn-link text-danger" data-confirm="Aşı tanımı silinsin mi?">Sil</button><?php else: ?><span></span><?php endif; ?>
      <div><?php if ($e['id']): ?><a href="?tur=<?= $sp ?>" class="btn btn-light">Vazgeç</a><?php endif; ?> <button class="btn btn-primary">Kaydet</button></div>
    </div></form>
  </div>
</div>
<div class="card mt-3" id="akis">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="bi bi-diagram-3 me-1 text-primary"></i> Otomatik Aşı Akışı <span class="text-muted fw-normal small">· bir aşı "Yapıldı" işaretlenince sıradaki aşılar otomatik planlanır</span></span>
    <?php if (is_admin()): ?><form method="post" data-confirm="Bu türdeki dahil tüm akış kuralları silinip varsayılan klinik protokolü yeniden kurulacak. Emin misiniz?"><?= csrf_field() ?><input type="hidden" name="islem" value="akis_varsayilan"><input type="hidden" name="species_id" value="<?= $sp ?>"><button class="btn btn-sm btn-light"><i class="bi bi-arrow-counterclockwise"></i> Varsayılana döndür</button></form><?php endif; ?>
  </div>
  <div class="card-body">
    <form method="post" id="fgun"><?= csrf_field() ?><input type="hidden" name="islem" value="akis_gun"><input type="hidden" name="species_id" value="<?= $sp ?>"></form>
    <?php foreach ($flows as $f): ?>
      <div class="flow-row">
        <span class="chipv"><?= e($f['from_name']) ?> <small><?= e(vdose_options(true)[$f['from_dose']] ?? $f['from_dose']) ?></small></span>
        <span class="arrow">yapılınca <i class="bi bi-arrow-right"></i></span>
        <span class="d-inline-flex align-items-center gap-1"><input form="fgun" type="number" min="0" name="gun[<?= $f['id'] ?>]" value="<?= (int)$f['days'] ?>" class="form-control form-control-sm" style="width:80px"> <span class="small text-muted">gün sonra</span> <span class="badge badge-soft"><?= $fmtDays((int)$f['days']) ?></span></span>
        <span class="arrow"><i class="bi bi-arrow-right"></i></span>
        <span class="chipv"><?= e($f['to_name']) ?> <small><?= e(vdose_label((string)$f['to_dose']) ?? '') ?></small></span>
        <form method="post" class="ms-auto"><?= csrf_field() ?><input type="hidden" name="islem" value="akis_sil"><input type="hidden" name="flow_id" value="<?= $f['id'] ?>"><input type="hidden" name="species_id" value="<?= $sp ?>"><button class="btn btn-sm btn-link text-danger" data-confirm="Bu kural silinsin mi?"><i class="bi bi-trash"></i></button></form>
      </div>
    <?php endforeach; ?>
    <?php if ($flows): ?><div class="text-end mt-2"><button form="fgun" class="btn btn-sm btn-primary"><i class="bi bi-check-lg"></i> Süreleri Kaydet</button></div><?php else: ?><div class="empty py-3">Bu tür için akış kuralı yok.</div><?php endif; ?>
    <form method="post" class="row g-2 align-items-end mt-3 pt-3 border-top">
      <?= csrf_field() ?><input type="hidden" name="islem" value="akis_ekle"><input type="hidden" name="species_id" value="<?= $sp ?>">
      <div class="col-md-3"><label class="form-label small">Bu aşı</label><select name="from_type_id" class="form-select form-select-sm" required><?= options($typesSp, '', 'id', 'name', 'Seçin…') ?></select></div>
      <div class="col-md-2"><label class="form-label small">Dozu yapılınca</label><select name="from_dose" class="form-select form-select-sm"><?= options(vdose_options(true), '*') ?></select></div>
      <div class="col-md-2"><label class="form-label small">Kaç gün sonra</label><input type="number" name="days" min="0" value="15" class="form-control form-control-sm"></div>
      <div class="col-md-3"><label class="form-label small">Planlanacak aşı</label><select name="to_type_id" class="form-select form-select-sm" required><?= options($typesSp, '', 'id', 'name', 'Seçin…') ?></select></div>
      <div class="col-md-1"><label class="form-label small">Dozu</label><select name="to_dose" class="form-select form-select-sm"><?= options(vdose_options(), '1') ?></select></div>
      <div class="col-md-1"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus-lg"></i> Ekle</button></div>
    </form>
    <div class="form-text"><i class="bi bi-hourglass-split"></i> Kullanılan ürünün <a href="<?= url('admin/urunler.php') ?>">Ürünler</a> sayfasında etki süresi girilmişse, aynı aşının tekrarı (yıllık rapel) bu tablodaki süre yerine ürünün etki süresine göre planlanır; 1. → 2. doz arası her zaman buradaki süredir.</div>
  </div>
</div>
<?php
$pageScripts = '<script>ptProductSelect(document.getElementById("tprod"));</script>';
include APP . '/views/admin_footer.php';
