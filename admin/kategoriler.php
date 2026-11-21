<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$pageTitle = 'Hayvan Kategorileri';
$active = 'hasta';

if (is_post()) {
    $act = post('islem');
    if ($act === 'tur_kaydet' && post('name') !== '') {
        $d = ['name' => post('name'), 'icon' => post('icon') ?: '🐾', 'color' => post('color') ?: '#64748b', 'sort' => (int)post('sort')];
        (int)post('id') ? update('species', $d, (int)post('id')) : insert('species', $d);
        flash('success', 'Kategori kaydedildi.');
    }
    if ($act === 'tur_sil' && is_admin()) {
        if (val('SELECT COUNT(*) FROM patients WHERE species_id = ?', [(int)post('id')])) flash('danger', 'Bu kategoride kayıtlı hasta var, silinemez.');
        else { delete_row('species', (int)post('id')); flash('success', 'Kategori silindi.'); }
    }
    if ($act === 'irk_ekle' && post('name') !== '') {
        foreach (array_filter(array_map('trim', explode(',', post('name')))) as $b) insert('breeds', ['species_id' => (int)post('species_id'), 'name' => $b]);
        flash('success', 'Irk eklendi.');
    }
    if ($act === 'irk_sil') delete_row('breeds', (int)post('id'));
    redirect(url('admin/kategoriler.php'));
}

$species = rows('SELECT s.*, (SELECT COUNT(*) FROM patients p WHERE p.species_id = s.id) AS n FROM species s ORDER BY s.sort, s.name');
$breeds = [];
foreach (rows('SELECT * FROM breeds ORDER BY name') as $b) $breeds[$b['species_id']][] = $b;
include APP . '/views/admin_header.php'; ?>
<div class="page-head"><div><h1>Hayvan Kategorileri</h1><div class="sub">Türler (kedi, köpek, kuş…) ve ırklar. Aşı protokolleri türe göre çalışır.</div></div></div>
<div class="row g-3">
  <div class="col-lg-4">
    <form method="post" class="card"><div class="card-header">Yeni Kategori</div><div class="card-body">
      <?= csrf_field() ?><input type="hidden" name="islem" value="tur_kaydet">
      <div class="mb-2"><label class="form-label">Ad</label><input name="name" class="form-control" required placeholder="Örn: Gelincik"></div>
      <div class="row g-2"><div class="col-4"><label class="form-label">Emoji</label><input name="icon" class="form-control" placeholder="🦦"></div>
        <div class="col-4"><label class="form-label">Renk</label><input type="color" name="color" class="form-control form-control-color w-100" value="#0f766e"></div>
        <div class="col-4"><label class="form-label">Sıra</label><input name="sort" type="number" class="form-control" value="<?= count($species) ?>"></div></div>
      <button class="btn btn-primary w-100 mt-3">Ekle</button>
    </div></form>
  </div>
  <div class="col-lg-8">
    <?php foreach ($species as $s): ?>
      <div class="card mb-3"><div class="card-body">
        <form method="post" class="row g-2 align-items-center">
          <?= csrf_field() ?><input type="hidden" name="islem" value="tur_kaydet"><input type="hidden" name="id" value="<?= $s['id'] ?>">
          <div class="col-auto"><div class="pet-avatar" style="background:<?= e($s['color']) ?>22"><?= species_icon($s['icon']) ?></div></div>
          <div class="col-md-4"><input name="name" class="form-control fw-bold" value="<?= e($s['name']) ?>"></div>
          <div class="col-2"><input name="icon" class="form-control" value="<?= e($s['icon']) ?>"></div>
          <div class="col-1"><input type="color" name="color" class="form-control form-control-color" value="<?= e($s['color'] ?: '#64748b') ?>"></div>
          <div class="col-1"><input name="sort" type="number" class="form-control" value="<?= (int)$s['sort'] ?>"></div>
          <div class="col-auto"><button class="btn btn-light btn-sm"><i class="bi bi-save"></i></button></div>
          <div class="col-auto small text-muted"><?= (int)$s['n'] ?> hasta</div>
        </form>
        <div class="d-flex flex-wrap gap-1 mt-2">
          <?php foreach ($breeds[$s['id']] ?? [] as $b): ?>
            <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="islem" value="irk_sil"><input type="hidden" name="id" value="<?= $b['id'] ?>">
              <span class="badge badge-soft rounded-pill py-2 px-3"><?= e($b['name']) ?> <button class="btn btn-link p-0 ms-1 text-danger" style="font-size:.7rem"><i class="bi bi-x-lg"></i></button></span></form>
          <?php endforeach; ?>
          <form method="post" class="d-flex gap-1"><?= csrf_field() ?><input type="hidden" name="islem" value="irk_ekle"><input type="hidden" name="species_id" value="<?= $s['id'] ?>">
            <input name="name" class="form-control form-control-sm" placeholder="Irk ekle (virgülle çoklu)" style="width:210px"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-plus"></i></button></form>
        </div>
        <?php if (!$s['n'] && is_admin()): ?><form method="post" class="text-end" data-confirm="Kategori silinsin mi?"><?= csrf_field() ?><input type="hidden" name="islem" value="tur_sil"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="btn btn-sm btn-link text-danger">Kategoriyi sil</button></form><?php endif; ?>
      </div></div>
    <?php endforeach; ?>
  </div>
</div>
<?php include APP . '/views/admin_footer.php';
