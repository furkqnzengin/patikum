<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$pageTitle = 'Aşı Takvimi';
$active = 'asi';

if (is_post()) {
    $id = (int)post('id');
    $act = post('islem');
    if ($act === 'yapildi') {
        $next = vaccination_done($id, post('detay') ? [
            'done_date' => post('done_date') ?: today(), 'product_id' => post('product_id'), 'batch_no' => post('batch_no'),
            'price' => post('price') !== '' ? num(post('price')) : null, 'paid' => num(post('paid')), 'pay_method' => post('pay_method', 'nakit'),
        ] : []);
        flash('success', 'Aşı yapıldı olarak işaretlendi.' . ($next ? ' Sonraki aşı: <b>' . e($next) . '</b>' : ''));
    } elseif ($act === 'ertele') {
        $v = row('SELECT due_date FROM vaccinations WHERE id = ?', [$id]);
        $base = max($v['due_date'], today());
        update('vaccinations', ['due_date' => date('Y-m-d', strtotime($base . ' +' . max(1, (int)post('gun', 7)) . ' days'))], $id);
        flash('success', 'Aşı ertelendi.');
    } elseif ($act === 'iptal') {
        update('vaccinations', ['status' => 'iptal'], $id);
        flash('success', 'Aşı iptal edildi.');
    } elseif ($act === 'kaydet') {
        update('vaccinations', ['due_date' => post('due_date'), 'dose_label' => nul(post('dose_label')), 'notes' => nul(post('notes')), 'batch_no' => nul(post('batch_no'))], $id);
        flash('success', 'Aşı kaydı güncellendi.');
    } elseif ($act === 'sil' && is_admin()) {
        delete_row('vaccinations', $id);
        flash('success', 'Aşı kaydı silindi.');
        redirect(url('admin/asilar.php'));
    }
    back(url('admin/asilar.php'));
}

if (get('islem') === 'duzenle') {
    $v = row('SELECT v.*, t.name AS asi, t.product_id AS tprod, p.name AS hasta, p.id AS pid, c.name AS sahip FROM vaccinations v JOIN vaccine_types t ON t.id = v.vaccine_type_id JOIN patients p ON p.id = v.patient_id JOIN customers c ON c.id = p.customer_id WHERE v.id = ?', [(int)get('id')]);
    if (!$v) redirect(url('admin/asilar.php'));
    $prodId = $v['product_id'] ?: $v['tprod'];
    $prod = $prodId ? row('SELECT * FROM products WHERE id = ?', [$prodId]) : null;
    include APP . '/views/admin_header.php'; ?>
    <div class="page-head"><div><h1><?= e($v['asi']) ?> — <?= e($v['hasta']) ?></h1><div class="sub">Sahibi: <?= e($v['sahip']) ?> · <?= status_badge($v['status']) ?></div></div>
      <a href="<?= url('admin/hasta.php?id=' . $v['pid']) ?>#asilar" class="btn btn-light"><i class="bi bi-arrow-left"></i> Hasta kartı</a></div>
    <div class="row g-3">
      <div class="col-lg-6"><form method="post" class="card"><div class="card-header">Plan Bilgileri</div><div class="card-body">
        <?= csrf_field() ?><input type="hidden" name="islem" value="kaydet"><input type="hidden" name="id" value="<?= $v['id'] ?>">
        <div class="row g-3">
          <div class="col-6"><label class="form-label">Planlanan Tarih</label><input type="date" name="due_date" class="form-control" value="<?= e($v['due_date']) ?>"></div>
          <div class="col-6"><label class="form-label">Doz</label><input name="dose_label" class="form-control" value="<?= e($v['dose_label']) ?>"></div>
          <div class="col-6"><label class="form-label">Seri / Lot No</label><input name="batch_no" class="form-control" value="<?= e($v['batch_no']) ?>"></div>
          <div class="col-6"><label class="form-label">Not</label><input name="notes" class="form-control" value="<?= e($v['notes']) ?>"></div>
        </div></div><div class="card-footer bg-white d-flex justify-content-between">
          <?php if (is_admin()): ?><button name="islem" value="sil" class="btn btn-link text-danger" data-confirm="Aşı kaydı silinsin mi?">Sil</button><?php endif; ?>
          <div class="d-flex gap-2"><?php if ($v['status'] === 'planlandi'): ?><button name="islem" value="iptal" class="btn btn-outline-secondary">İptal Et</button><?php endif; ?><button class="btn btn-primary">Kaydet</button></div>
        </div></form></div>
      <?php if ($v['status'] === 'planlandi'): ?>
      <div class="col-lg-6"><form method="post" class="card border-success border-2 border"><div class="card-header text-success"><i class="bi bi-check2-circle"></i> Aşıyı Yap / Ücretlendir</div><div class="card-body">
        <?= csrf_field() ?><input type="hidden" name="islem" value="yapildi"><input type="hidden" name="detay" value="1"><input type="hidden" name="id" value="<?= $v['id'] ?>">
        <div class="row g-3">
          <div class="col-6"><label class="form-label">Uygulama Tarihi</label><input type="date" name="done_date" class="form-control" value="<?= today() ?>"></div>
          <div class="col-6"><label class="form-label">Seri / Lot No</label><input name="batch_no" class="form-control" value="<?= e($v['batch_no']) ?>"></div>
          <div class="col-12"><label class="form-label">Kullanılan Ürün (stoktan düşer)</label><select name="product_id" id="vprod"><?php if ($prod): ?><option value="<?= $prod['id'] ?>" selected><?= e($prod['name']) ?></option><?php endif; ?></select></div>
          <div class="col-4"><label class="form-label">Ücret (₺)</label><input name="price" id="vprice" class="form-control" value="<?= $prod ? e($prod['sell_price']) : '' ?>"></div>
          <div class="col-4"><label class="form-label">Alınan (₺)</label><input name="paid" class="form-control" placeholder="0"></div>
          <div class="col-4"><label class="form-label">Yöntem</label><select name="pay_method" class="form-select"><?= options(['nakit' => 'Nakit', 'kart' => 'Kart', 'havale' => 'Havale'], 'nakit') ?></select></div>
        </div>
        <div class="form-text">Rapel süresi tanımlıysa bir sonraki aşı otomatik planlanır ve müşteriye WhatsApp bilgilendirmesi gider.</div>
      </div><div class="card-footer bg-white text-end"><button class="btn btn-success px-4"><i class="bi bi-check-lg"></i> Yapıldı</button></div></form></div>
      <?php endif; ?>
    </div>
    <?php
    $pageScripts = '<script>ptProductSelect(document.getElementById("vprod"),o=>{document.getElementById("vprice").value=o.price;});</script>';
    include APP . '/views/admin_footer.php';
    exit;
}

$tab = get('sekme', 'yaklasan');
$today = today();
$cnt = [
    'bugun' => (int)val("SELECT COUNT(*) FROM vaccinations v JOIN patients p ON p.id = v.patient_id WHERE p.status='aktif' AND v.status='planlandi' AND v.due_date = ?", [$today]),
    'geciken' => (int)val("SELECT COUNT(*) FROM vaccinations v JOIN patients p ON p.id = v.patient_id WHERE p.status='aktif' AND v.status='planlandi' AND v.due_date < ?", [$today]),
    'yaklasan' => (int)val("SELECT COUNT(*) FROM vaccinations v JOIN patients p ON p.id = v.patient_id WHERE p.status='aktif' AND v.status='planlandi' AND v.due_date > ?", [$today]),
];
$tpl = wa_template('asi_hatirlatma');
$sp = (int)get('tur');

$tabs = function () use ($tab, $cnt) { ?>
  <ul class="nav nav-pills">
    <li class="nav-item"><a class="nav-link <?= $tab === 'takvim' ? 'active' : '' ?>" href="?sekme=takvim"><i class="bi bi-calendar3"></i> Takvim</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'bugun' ? 'active' : '' ?>" href="?sekme=bugun">Bugün <span class="badge text-bg-light"><?= $cnt['bugun'] ?></span></a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'yaklasan' ? 'active' : '' ?>" href="?sekme=yaklasan">Yaklaşan <span class="badge text-bg-light"><?= $cnt['yaklasan'] ?></span></a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'geciken' ? 'active' : '' ?>" href="?sekme=geciken">Geciken <span class="badge text-bg-danger"><?= $cnt['geciken'] ?></span></a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'yapilan' ? 'active' : '' ?>" href="?sekme=yapilan">Yapılanlar</a></li>
  </ul>
<?php };
$head = function () { ?>
<div class="page-head"><div><h1>Aşı Takvimi</h1><div class="sub">Yapılan aşılar uygulama tarihinde, yapılacaklar planlanan tarihinde görünür</div></div>
  <div class="d-flex gap-2 flex-wrap"><?php if (is_admin()): ?><a href="<?= url('admin/aktar.php?tur=asi') ?>" class="btn btn-outline-primary text-nowrap"><i class="bi bi-file-earmark-excel"></i> Excel'den Yükle</a><?php endif; ?><a href="<?= url('admin/asi-sihirbazi.php') ?>" class="btn btn-primary"><i class="bi bi-magic"></i> Otomatik Aşı Sihirbazı</a></div></div>
<?php };

if ($tab === 'takvim') {
    $ay = preg_match('/^\d{4}-\d{2}$/', (string)get('ay')) ? get('ay') : date('Y-m');
    $first = strtotime($ay . '-01');
    $start = strtotime('-' . ((int)date('N', $first) - 1) . ' days', $first);
    $end = strtotime('+41 days', $start);
    $prm = [date('Y-m-d', $start), date('Y-m-d', $end), date('Y-m-d', $start), date('Y-m-d', $end)];
    $sql = "SELECT v.id, v.status, v.due_date, v.done_date, v.dose_label, t.name AS asi, p.name AS hasta, p.id AS pid, s.icon, c.name AS sahip
            FROM vaccinations v JOIN vaccine_types t ON t.id = v.vaccine_type_id JOIN patients p ON p.id = v.patient_id JOIN customers c ON c.id = p.customer_id
            LEFT JOIN species s ON s.id = p.species_id
            WHERE p.status = 'aktif' AND ((v.status = 'planlandi' AND v.due_date BETWEEN ? AND ?) OR (v.status = 'yapildi' AND COALESCE(v.done_date, v.due_date) BETWEEN ? AND ?))";
    if ($sp) { $sql .= ' AND p.species_id = ?'; $prm[] = $sp; }
    $ev = [];
    foreach (rows($sql . ' ORDER BY p.name', $prm) as $r) {
        $d = $r['status'] === 'yapildi' ? ($r['done_date'] ?: $r['due_date']) : $r['due_date'];
        $ev[$d][] = $r;
    }
    $mStats = ['yapildi' => 0, 'plan' => 0, 'late' => 0];
    foreach ($ev as $d => $list) if (substr($d, 0, 7) === $ay) foreach ($list as $r) $mStats[$r['status'] === 'yapildi' ? 'yapildi' : ($d < $today ? 'late' : 'plan')]++;
    include APP . '/views/admin_header.php';
    $head(); ?>
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3 align-items-center">
      <?php $tabs(); ?>
      <form class="d-flex gap-2"><input type="hidden" name="sekme" value="takvim"><input type="hidden" name="ay" value="<?= e($ay) ?>">
        <select name="tur" class="form-select form-select-sm w-auto" onchange="this.form.submit()"><?= options(species_list(), $sp ?: '', 'id', 'name', 'Tüm türler') ?></select></form>
    </div>
    <div class="card"><div class="card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="d-flex align-items-center gap-2">
          <a class="btn btn-light" href="?sekme=takvim&tur=<?= $sp ?>&ay=<?= date('Y-m', strtotime('-1 month', $first)) ?>"><i class="bi bi-chevron-left"></i></a>
          <h5 class="mb-0 fw-bold" style="min-width:150px;text-align:center"><?= tr_month((int)date('n', $first)) ?> <?= date('Y', $first) ?></h5>
          <a class="btn btn-light" href="?sekme=takvim&tur=<?= $sp ?>&ay=<?= date('Y-m', strtotime('+1 month', $first)) ?>"><i class="bi bi-chevron-right"></i></a>
          <a class="btn btn-light" href="?sekme=takvim&tur=<?= $sp ?>">Bugün</a>
        </div>
        <div class="d-flex gap-2 small flex-wrap">
          <span class="vlegend done"><?= $mStats['yapildi'] ?> yapıldı</span>
          <span class="vlegend plan"><?= $mStats['plan'] ?> planlandı</span>
          <span class="vlegend late"><?= $mStats['late'] ?> gecikti</span>
        </div>
      </div>
      <div class="cal-head"><?php foreach (['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'] as $g): ?><div><?= $g ?></div><?php endforeach; ?></div>
      <div class="cal-grid vcal">
        <?php for ($t = $start; $t <= $end; $t = strtotime('+1 day', $t)): $d = date('Y-m-d', $t); $list = $ev[$d] ?? []; ?>
          <div class="d<?= date('m', $t) !== date('m', $first) ? ' out' : '' ?><?= $d === $today ? ' today' : '' ?>">
            <div class="d-flex justify-content-between"><span class="n"><?= date('j', $t) ?></span><?php if ($list): ?><span class="text-muted"><?= count($list) ?></span><?php endif; ?></div>
            <?php foreach (array_slice($list, 0, 4) as $r): $cls = $r['status'] === 'yapildi' ? 'done' : ($d < $today ? 'late' : 'plan'); ?>
              <a class="ev v-<?= $cls ?>" href="?islem=duzenle&id=<?= $r['id'] ?>" title="<?= e($r['hasta'] . ' (' . $r['sahip'] . ') — ' . $r['asi'] . ($r['dose_label'] ? ' · ' . $r['dose_label'] : '') . ' — ' . ($cls === 'done' ? 'Yapıldı' : ($cls === 'late' ? abs(days_diff($d)) . ' gün gecikti' : (days_diff($d) === 0 ? 'Bugün' : days_diff($d) . ' gün sonra')))) ?>"><?= $cls === 'done' ? '✓ ' : '' ?><?= e($r['hasta']) ?> · <?= e($r['asi']) ?></a>
            <?php endforeach; ?>
            <?php if (count($list) > 4): ?><a class="more" href="?sekme=<?= $d < $today ? 'yapilan' : 'yaklasan' ?>&gun=<?= max(1, days_diff($d)) ?>">+<?= count($list) - 4 ?> daha</a><?php endif; ?>
          </div>
        <?php endfor; ?>
      </div>
    </div></div>
    <?php include APP . '/views/admin_footer.php';
    exit;
}

$sql = "SELECT v.*, t.name AS asi, p.name AS hasta, p.id AS pid, s.icon, c.name AS sahip, c.phone FROM vaccinations v
        JOIN vaccine_types t ON t.id = v.vaccine_type_id JOIN patients p ON p.id = v.patient_id JOIN customers c ON c.id = p.customer_id
        LEFT JOIN species s ON s.id = p.species_id WHERE p.status = 'aktif' AND ";
$prm = [];
$gun = (int)get('gun', 30);
switch ($tab) {
    case 'bugun': $sql .= "v.status = 'planlandi' AND v.due_date = ?"; $prm = [$today]; $order = 'p.name'; break;
    case 'geciken': $sql .= "v.status = 'planlandi' AND v.due_date < ?"; $prm = [$today]; $order = 'v.due_date DESC'; break;
    case 'yapilan': $sql .= "v.status = 'yapildi'"; $order = 'v.done_date DESC, v.due_date DESC'; break;
    default: $tab = 'yaklasan'; $sql .= "v.status = 'planlandi' AND v.due_date >= ?"; $prm = [$today];
        if ($gun > 0) { $sql .= ' AND v.due_date <= ?'; $prm[] = date('Y-m-d', strtotime('+' . $gun . ' days')); }
        $order = 'v.due_date, p.name';
}
if ($sp) { $sql .= ' AND p.species_id = ?'; $prm[] = $sp; }
if ($qs = trim((string)get('q'))) { $sql .= ' AND (p.name LIKE ? OR c.name LIKE ? OR t.name LIKE ?)'; array_push($prm, "%$qs%", "%$qs%", "%$qs%"); }
$pg = paginate($sql . " ORDER BY $order", $prm, 40);

include APP . '/views/admin_header.php';
$head(); ?>
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3 align-items-center">
  <?php $tabs(); ?>
  <form class="d-flex gap-2 flex-wrap align-items-center"><input type="hidden" name="sekme" value="<?= e($tab) ?>">
    <input name="q" class="form-control form-control-sm" style="width:180px" placeholder="Hasta, sahip, aşı…" value="<?= e(get('q')) ?>">
    <?php if ($tab === 'yaklasan'): ?><select name="gun" class="form-select form-select-sm w-auto"><?= options(['7' => '7 gün içinde', '15' => '15 gün içinde', '30' => '30 gün içinde', '60' => '60 gün içinde', '90' => '90 gün içinde', '365' => '1 yıl içinde', '0' => 'Tümü'], (string)$gun) ?></select><?php endif; ?>
    <select name="tur" class="form-select form-select-sm w-auto"><?= options(species_list(), $sp ?: '', 'id', 'name', 'Tüm türler') ?></select><button class="btn btn-sm btn-light"><i class="bi bi-funnel"></i></button></form>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle">
  <thead><tr><th>Hasta</th><th>Sahibi</th><th>Aşı</th><th><?= $tab === 'yapilan' ? 'Uygulama Tarihi' : 'Planlanan Tarih' ?></th><th>Durum</th><th></th></tr></thead><tbody>
  <?php foreach ($pg['rows'] as $v):
      $msg = $tpl ? wa_render($tpl['body'], ['musteri' => $v['sahip'], 'hasta' => $v['hasta'], 'asi' => $v['asi'], 'tarih' => dt($v['due_date']), 'zaman' => dt($v['due_date'])]) : ''; ?>
    <tr>
      <td><div class="d-flex align-items-center gap-2"><div class="pet-avatar"><?= species_icon($v['icon']) ?></div><a class="fw-bold" href="<?= url('admin/hasta.php?id=' . $v['pid']) ?>"><?= e($v['hasta']) ?></a></div></td>
      <td><?= e($v['sahip']) ?><div class="small text-muted"><?= e(phone_fmt($v['phone'])) ?></div></td>
      <td><span class="vac-name"><?= e($v['asi']) ?></span><div class="small text-muted"><?= e($v['dose_label']) ?></div></td>
      <td class="text-nowrap"><?= $tab === 'yapilan' ? dt($v['done_date'] ?: $v['due_date']) : dt_long($v['due_date']) ?><?php if ($tab === 'yapilan' && $v['done_date'] && $v['done_date'] !== $v['due_date']): ?><div class="small text-muted">Plan: <?= dt($v['due_date']) ?></div><?php endif; ?></td>
      <td><?= vacc_due_badge($v) ?></td>
      <td class="text-end text-nowrap">
        <?php if ($v['status'] === 'planlandi'): ?>
          <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="islem" value="yapildi"><input type="hidden" name="id" value="<?= $v['id'] ?>"><button class="btn btn-sm btn-outline-success" title="Yapıldı"><i class="bi bi-check-lg"></i></button></form>
          <?php if ($v['phone']): ?><a class="btn btn-sm btn-outline-success" target="_blank" title="WhatsApp hatırlat" href="<?= e(wa_link($v['phone'], $msg)) ?>"><i class="bi bi-whatsapp"></i></a><?php endif; ?>
          <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="islem" value="ertele"><input type="hidden" name="id" value="<?= $v['id'] ?>"><button class="btn btn-sm btn-light" title="1 hafta ertele"><i class="bi bi-clock-history"></i></button></form>
        <?php endif; ?>
        <a class="btn btn-sm btn-light" href="?islem=duzenle&id=<?= $v['id'] ?>"><i class="bi bi-pencil"></i></a>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$pg['rows']): ?><tr><td colspan="6" class="empty"><i class="bi bi-eyedropper"></i>Kayıt yok.</td></tr><?php endif; ?>
  </tbody></table></div><div class="card-footer bg-white d-flex justify-content-between align-items-center"><span class="small text-muted"><?= $pg['total'] ?> kayıt</span><?= pager($pg) ?></div></div>
<?php include APP . '/views/admin_footer.php';
