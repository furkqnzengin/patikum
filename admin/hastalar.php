<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$pageTitle = 'Hastalar';
$active = 'hasta';
$islem = get('islem', '');

if (is_post() && post('islem') === 'kaydet') {
    $id = (int)post('id');
    $data = [
        'customer_id' => (int)post('customer_id'), 'species_id' => nul(post('species_id')), 'breed' => nul(post('breed')),
        'name' => post('name'), 'gender' => nul(post('gender')), 'birth_date' => nul(post('birth_date')), 'weight' => nul(post('weight')) !== null ? num(post('weight')) : null,
        'color' => nul(post('color')), 'chip_no' => nul(post('chip_no')), 'passport_no' => nul(post('passport_no')), 'neutered' => post('neutered') ? 1 : 0,
        'allergies' => nul(post('allergies')), 'notes' => nul(post('notes')), 'status' => post('status', 'aktif'),
    ];
    if (!$data['customer_id'] || $data['name'] === '') {
        flash('danger', 'Hayvan sahibi ve hasta adı zorunludur.');
        back();
    }
    if ($id) {
        update('patients', $data, $id);
        flash('success', 'Hasta bilgileri güncellendi.');
    } else {
        $id = insert('patients', $data);
        flash('success', 'Hasta kaydedildi.');
        if (post('sonra') === 'asi') redirect(url('admin/asi-sihirbazi.php?hasta=' . $id));
    }
    redirect(url('admin/hasta.php?id=' . $id));
}

if ($islem === 'yeni' || $islem === 'duzenle') {
    $p = $islem === 'duzenle' ? row('SELECT * FROM patients WHERE id = ?', [(int)get('id')]) : null;
    $p = $p ?: ['id' => 0, 'customer_id' => (int)get('musteri'), 'species_id' => '', 'breed' => '', 'name' => '', 'gender' => '', 'birth_date' => '', 'weight' => '', 'color' => '', 'chip_no' => '', 'passport_no' => '', 'neutered' => 0, 'allergies' => '', 'notes' => '', 'status' => 'aktif'];
    $owner = $p['customer_id'] ? row('SELECT id, name, phone FROM customers WHERE id = ?', [$p['customer_id']]) : null;
    include APP . '/views/admin_header.php'; ?>
    <div class="page-head"><div><h1><?= $p['id'] ? 'Hastayı Düzenle' : 'Yeni Hasta' ?></h1><div class="sub">Patili dostumuzun bilgileri</div></div>
      <a href="<?= url('admin/hastalar.php') ?>" class="btn btn-light"><i class="bi bi-arrow-left"></i> Listeye dön</a></div>
    <form method="post" class="card"><div class="card-body">
      <?= csrf_field() ?><input type="hidden" name="islem" value="kaydet"><input type="hidden" name="id" value="<?= $p['id'] ?>">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label fw-bold">Hayvan Sahibi *</label>
          <select name="customer_id" class="js-customer" required><?php if ($owner): ?><option value="<?= $owner['id'] ?>" selected><?= e($owner['name'] . ' · ' . phone_fmt($owner['phone'])) ?></option><?php endif; ?></select>
          <div class="form-text">Listede yoksa <a href="<?= url('admin/musteriler.php?islem=yeni') ?>">yeni müşteri ekleyin</a>.</div></div>
        <div class="col-md-6"><label class="form-label fw-bold">Hasta Adı *</label><input name="name" class="form-control" required value="<?= e($p['name']) ?>"></div>
        <div class="col-12"><label class="form-label fw-bold">Kategori (Tür) *</label>
          <div class="d-flex flex-wrap gap-2">
            <?php foreach (species_list() as $s): ?>
              <input type="radio" class="btn-check" name="species_id" id="sp<?= $s['id'] ?>" value="<?= $s['id'] ?>" <?= (string)$p['species_id'] === (string)$s['id'] ? 'checked' : '' ?> required>
              <label class="btn btn-outline-primary rounded-pill" for="sp<?= $s['id'] ?>"><?= species_icon($s['icon']) ?> <?= e($s['name']) ?></label>
            <?php endforeach; ?>
            <a href="<?= url('admin/kategoriler.php') ?>" class="btn btn-link btn-sm align-self-center"><i class="bi bi-plus"></i> Kategori ekle</a>
          </div></div>
        <div class="col-md-4"><label class="form-label">Irk</label><input name="breed" class="form-control" list="breedList" value="<?= e($p['breed']) ?>"><datalist id="breedList"></datalist></div>
        <div class="col-md-2"><label class="form-label">Cinsiyet</label><select name="gender" class="form-select"><?= options(['' => '-', 'erkek' => 'Erkek', 'disi' => 'Dişi'], $p['gender']) ?></select></div>
        <div class="col-md-3"><label class="form-label">Doğum Tarihi</label><input type="date" name="birth_date" class="form-control" value="<?= e($p['birth_date']) ?>"><div class="form-text">Aşı sihirbazı yaşa göre takvim çıkarır.</div></div>
        <div class="col-md-3"><label class="form-label">Kilo (kg)</label><input name="weight" class="form-control" value="<?= e($p['weight']) ?>"></div>
        <div class="col-md-3"><label class="form-label">Renk</label><input name="color" class="form-control" value="<?= e($p['color']) ?>"></div>
        <div class="col-md-3"><label class="form-label">Mikroçip No</label><input name="chip_no" class="form-control" value="<?= e($p['chip_no']) ?>"></div>
        <div class="col-md-3"><label class="form-label">Pasaport No</label><input name="passport_no" class="form-control" value="<?= e($p['passport_no']) ?>"></div>
        <div class="col-md-3"><label class="form-label">Durum</label><select name="status" class="form-select"><?= options(['aktif' => 'Aktif', 'vefat' => 'Vefat', 'kayip' => 'Kayıp'], $p['status']) ?></select></div>
        <div class="col-md-6"><label class="form-label">Alerjiler / Kronik Durumlar</label><textarea name="allergies" class="form-control" rows="2"><?= e($p['allergies']) ?></textarea></div>
        <div class="col-md-6"><label class="form-label">Notlar</label><textarea name="notes" class="form-control" rows="2"><?= e($p['notes']) ?></textarea></div>
        <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="neutered" value="1" id="nt" <?= $p['neutered'] ? 'checked' : '' ?>><label for="nt" class="form-check-label">Kısırlaştırılmış</label></div></div>
      </div>
    </div><div class="card-footer bg-white d-flex gap-2 justify-content-end">
      <?php if (!$p['id']): ?><button name="sonra" value="asi" class="btn btn-outline-primary"><i class="bi bi-magic"></i> Kaydet ve Aşı Takvimi Oluştur</button><?php endif; ?>
      <button class="btn btn-primary px-4"><i class="bi bi-check-lg"></i> Kaydet</button>
    </div></form>
    <?php
    $pageScripts = '<script>document.querySelectorAll("input[name=species_id]").forEach(r=>r.addEventListener("change",()=>ptApi("a=breeds&species_id="+r.value).then(l=>{document.getElementById("breedList").innerHTML=l.map(b=>`<option value="${b}">`).join("")})));const ch=document.querySelector("input[name=species_id]:checked");if(ch)ch.dispatchEvent(new Event("change"));</script>';
    include APP . '/views/admin_footer.php';
    exit;
}

$qs = (string)get('q', '');
$sp = (int)get('tur');
$st = get('durum', 'aktif');
$sql = 'SELECT p.*, s.name AS tur, s.icon, c.name AS sahip, c.phone FROM patients p LEFT JOIN species s ON s.id = p.species_id JOIN customers c ON c.id = p.customer_id WHERE 1=1';
$prm = [];
if ($st) { $sql .= ' AND p.status = ?'; $prm[] = $st; }
if ($sp) { $sql .= ' AND p.species_id = ?'; $prm[] = $sp; }
if ($qs !== '') { $sql .= ' AND (p.name LIKE ? OR p.chip_no LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR p.breed LIKE ?)'; array_push($prm, ...array_fill(0, 5, "%$qs%")); }
$sql .= ' ORDER BY p.id DESC';
$pg = paginate($sql, $prm, 30);
$counts = [];
foreach (rows("SELECT species_id, COUNT(*) n FROM patients WHERE status = 'aktif' GROUP BY species_id") as $r) $counts[$r['species_id']] = $r['n'];

include APP . '/views/admin_header.php'; ?>
<div class="page-head">
  <div><h1>Hastalar</h1><div class="sub"><?= $pg['total'] ?> kayıt</div></div>
  <div class="d-flex gap-2">
    <form class="d-flex gap-2"><input type="hidden" name="tur" value="<?= $sp ?>"><input name="q" class="form-control" placeholder="Ad, çip no, sahip, ırk…" value="<?= e($qs) ?>">
      <select name="durum" class="form-select" style="width:auto"><?= options(['aktif' => 'Aktif', 'vefat' => 'Vefat', 'kayip' => 'Kayıp', '' => 'Tümü'], $st) ?></select><button class="btn btn-light"><i class="bi bi-search"></i></button></form>
    <?php if (is_admin()): ?><a href="<?= url('admin/aktar.php?tur=hasta') ?>" class="btn btn-outline-primary text-nowrap"><i class="bi bi-file-earmark-excel"></i> Excel'den Yükle</a><?php endif; ?>
    <a href="<?= url('admin/hastalar.php?islem=yeni') ?>" class="btn btn-primary text-nowrap"><i class="bi bi-plus-lg"></i> Yeni Hasta</a>
  </div>
</div>
<div class="d-flex flex-wrap gap-2 mb-3">
  <a href="?<?= e(http_build_query(['q' => $qs, 'durum' => $st])) ?>" class="btn btn-sm rounded-pill <?= !$sp ? 'btn-primary' : 'btn-light' ?>">Tümü</a>
  <?php foreach (species_list() as $s): ?>
    <a href="?<?= e(http_build_query(['tur' => $s['id'], 'q' => $qs, 'durum' => $st])) ?>" class="btn btn-sm rounded-pill <?= $sp === (int)$s['id'] ? 'btn-primary' : 'btn-light' ?>"><?= species_icon($s['icon']) ?> <?= e($s['name']) ?> <span class="opacity-75">(<?= (int)($counts[$s['id']] ?? 0) ?>)</span></a>
  <?php endforeach; ?>
</div>
<div class="card"><div class="table-responsive">
  <table class="table table-hover align-middle">
    <thead><tr><th>Hasta</th><th>Tür / Irk</th><th>Yaş</th><th>Sahibi</th><th>Çip No</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($pg['rows'] as $p): ?>
      <tr>
        <td><div class="d-flex align-items-center gap-2"><div class="pet-avatar"><?= species_icon($p['icon']) ?></div><div><a class="fw-bold" href="<?= url('admin/hasta.php?id=' . $p['id']) ?>"><?= e($p['name']) ?></a><div class="small text-muted"><?= $p['gender'] === 'erkek' ? '♂ Erkek' : ($p['gender'] === 'disi' ? '♀ Dişi' : '') ?></div></div></div></td>
        <td><?= e($p['tur']) ?><div class="small text-muted"><?= e($p['breed']) ?></div></td>
        <td><?= age_text($p['birth_date']) ?></td>
        <td><a href="<?= url('admin/musteri.php?id=' . $p['customer_id']) ?>"><?= e($p['sahip']) ?></a><div class="small text-muted"><?= e(phone_fmt($p['phone'])) ?></div></td>
        <td class="small"><?= e($p['chip_no'] ?: '-') ?></td>
        <td class="text-end text-nowrap">
          <a class="btn btn-sm btn-light" title="Aşı sihirbazı" href="<?= url('admin/asi-sihirbazi.php?hasta=' . $p['id']) ?>"><i class="bi bi-magic"></i></a>
          <a class="btn btn-sm btn-light" href="<?= url('admin/hasta.php?id=' . $p['id']) ?>"><i class="bi bi-eye"></i></a>
          <a class="btn btn-sm btn-light" href="<?= url('admin/hastalar.php?islem=duzenle&id=' . $p['id']) ?>"><i class="bi bi-pencil"></i></a>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$pg['rows']): ?><tr><td colspan="6" class="empty"><i class="bi bi-heart"></i>Kayıt bulunamadı.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div><div class="card-footer bg-white"><?= pager($pg) ?></div></div>
<?php include APP . '/views/admin_footer.php';
