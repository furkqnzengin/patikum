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
    redirect(url('admin/asi-tanimlari.php?tur=' . (int)post('species_id')));
}

$sp = (int)get('tur', species_list()[0]['id'] ?? 0);
$list = rows('SELECT t.*, p.first_age_weeks, p.doses, p.interval_days, p.booster_days, p.sort, pr.name AS urun FROM vaccine_types t
              LEFT JOIN vaccine_protocols p ON p.vaccine_type_id = t.id AND p.species_id = t.species_id LEFT JOIN products pr ON pr.id = t.product_id
              WHERE t.species_id = ? ORDER BY t.active DESC, p.sort, t.name', [$sp]);
$edit = get('duzenle') ? row('SELECT t.*, p.first_age_weeks, p.doses, p.interval_days, p.booster_days, p.sort FROM vaccine_types t LEFT JOIN vaccine_protocols p ON p.vaccine_type_id = t.id AND p.species_id = t.species_id WHERE t.id = ?', [(int)get('duzenle')]) : null;
$e = $edit ?: ['id' => 0, 'name' => '', 'description' => '', 'product_id' => null, 'active' => 1, 'first_age_weeks' => 8, 'doses' => 1, 'interval_days' => 21, 'booster_days' => 365, 'sort' => count($list)];
$eprod = $e['product_id'] ? row('SELECT id, name FROM products WHERE id = ?', [$e['product_id']]) : null;

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
      <div class="form-text">Örnek: Karma aşı 8. haftada başlar, 21 gün arayla 2 doz, ardından her 365 günde bir rapel.</div>
    </div><div class="card-footer bg-white d-flex justify-content-between">
      <?php if ($e['id'] && is_admin()): ?><button name="islem" value="sil" class="btn btn-link text-danger" data-confirm="Aşı tanımı silinsin mi?">Sil</button><?php else: ?><span></span><?php endif; ?>
      <div><?php if ($e['id']): ?><a href="?tur=<?= $sp ?>" class="btn btn-light">Vazgeç</a><?php endif; ?> <button class="btn btn-primary">Kaydet</button></div>
    </div></form>
  </div>
</div>
<?php
$pageScripts = '<script>ptProductSelect(document.getElementById("tprod"));</script>';
include APP . '/views/admin_footer.php';
