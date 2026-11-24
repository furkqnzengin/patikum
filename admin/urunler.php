<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$pageTitle = 'Ürünler & Hizmetler';
$active = 'urun';
$cats = array_values(array_filter(array_map('trim', explode(',', setting('product_categories', 'Aşı,İlaç,Mama,Aksesuar,Hizmet')))));

if (is_post()) {
    $act = post('islem');
    $id = (int)post('id');
    if ($act === 'kaydet') {
        $data = [
            'category' => nul(post('category')), 'name' => post('name'), 'barcode' => nul(post('barcode')), 'unit' => post('unit', 'Adet'),
            'buy_price' => num(post('buy_price')), 'sell_price' => num(post('sell_price')), 'vat_rate' => (int)post('vat_rate', 20),
            'critical_stock' => num(post('critical_stock')), 'expiry_date' => nul(post('expiry_date')), 'is_service' => post('is_service') ? 1 : 0, 'active' => post('active') ? 1 : 0,
        ];
        if ($data['name'] === '') { flash('danger', 'Ürün adı zorunlu.'); back(); }
        if ($data['barcode'] && val('SELECT id FROM products WHERE barcode = ? AND id <> ?', [$data['barcode'], $id])) { flash('danger', 'Bu barkod başka bir üründe kayıtlı.'); back(); }
        if ($id) update('products', $data, $id);
        else {
            $id = insert('products', $data + ['stock' => 0]);
            if (!$data['is_service'] && num(post('initial_stock')) > 0) stock_move($id, num(post('initial_stock')), 'giris', 'Açılış stoğu', null, null, $data['buy_price']);
        }
        flash('success', 'Ürün kaydedildi.');
        redirect(url('admin/urunler.php'));
    }
}

if (in_array(get('islem'), ['yeni', 'duzenle'], true)) {
    $p = get('islem') === 'duzenle' ? row('SELECT * FROM products WHERE id = ?', [(int)get('id')]) : null;
    $p = $p ?: ['id' => 0, 'category' => '', 'name' => '', 'barcode' => '', 'unit' => 'Adet', 'buy_price' => '', 'sell_price' => '', 'vat_rate' => 20, 'critical_stock' => 5, 'expiry_date' => '', 'is_service' => 0, 'active' => 1, 'stock' => 0];
    include APP . '/views/admin_header.php'; ?>
    <div class="page-head"><div><h1><?= $p['id'] ? 'Ürünü Düzenle' : 'Yeni Ürün / Hizmet' ?></h1></div><a href="<?= url('admin/urunler.php') ?>" class="btn btn-light"><i class="bi bi-arrow-left"></i> Listeye dön</a></div>
    <form method="post" class="card"><div class="card-body">
      <?= csrf_field() ?><input type="hidden" name="islem" value="kaydet"><input type="hidden" name="id" value="<?= $p['id'] ?>">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label fw-bold">Ürün / Hizmet Adı *</label><input name="name" class="form-control" required value="<?= e($p['name']) ?>"></div>
        <div class="col-md-3"><label class="form-label">Kategori</label><input name="category" class="form-control" list="cats" value="<?= e($p['category']) ?>"><datalist id="cats"><?php foreach ($cats as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist></div>
        <div class="col-md-3"><label class="form-label">Barkod</label><input name="barcode" class="form-control" value="<?= e($p['barcode']) ?>"></div>
        <div class="col-md-3"><label class="form-label">Alış Fiyatı (₺)</label><input name="buy_price" class="form-control" value="<?= e($p['buy_price']) ?>"></div>
        <div class="col-md-3"><label class="form-label fw-bold">Satış Fiyatı (KDV dahil ₺)</label><input name="sell_price" class="form-control" value="<?= e($p['sell_price']) ?>"></div>
        <div class="col-md-2"><label class="form-label">KDV %</label><select name="vat_rate" class="form-select"><?= options(['0' => '0', '1' => '1', '10' => '10', '20' => '20'], (string)$p['vat_rate']) ?></select></div>
        <div class="col-md-2"><label class="form-label">Birim</label><select name="unit" class="form-select"><?= options(array_combine($u = ['Adet', 'Kutu', 'Şişe', 'Doz', 'ml', 'Kg', 'Paket', 'Seans'], $u), $p['unit']) ?></select></div>
        <div class="col-md-2"><label class="form-label">Kritik Stok</label><input name="critical_stock" class="form-control" value="<?= e(qty($p['critical_stock'])) ?>"></div>
        <?php if (!$p['id']): ?><div class="col-md-3"><label class="form-label">Açılış Stoğu</label><input name="initial_stock" class="form-control" placeholder="0"></div><?php endif; ?>
        <div class="col-md-3"><label class="form-label">Son Kullanma Tarihi</label><input type="date" name="expiry_date" class="form-control" value="<?= e($p['expiry_date']) ?>"></div>
        <div class="col-md-6 d-flex align-items-end gap-4">
          <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_service" value="1" id="sv" <?= $p['is_service'] ? 'checked' : '' ?>><label for="sv" class="form-check-label">Hizmet (stok tutulmaz)</label></div>
          <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="active" value="1" id="ac" <?= $p['active'] ? 'checked' : '' ?>><label for="ac" class="form-check-label">Aktif</label></div>
        </div>
      </div>
      <?php if ($p['id'] && !$p['is_service']): ?><div class="alert alert-light mt-3 mb-0">Mevcut stok: <b><?= qty($p['stock']) ?> <?= e($p['unit']) ?></b> — stok değiştirmek için <a href="<?= url('admin/stok.php?urun=' . $p['id']) ?>">stok hareketi</a> girin.</div><?php endif; ?>
    </div><div class="card-footer bg-white text-end"><button class="btn btn-primary px-4">Kaydet</button></div></form>
    <?php include APP . '/views/admin_footer.php';
    exit;
}

$qs = (string)get('q', '');
$cat = (string)get('kategori', '');
$sql = 'SELECT * FROM products WHERE active = ?';
$prm = [get('pasif') ? 0 : 1];
if ($qs !== '') { $sql .= ' AND (name LIKE ? OR barcode = ?)'; array_push($prm, "%$qs%", $qs); }
if ($cat !== '') { $sql .= ' AND category = ?'; $prm[] = $cat; }
if (get('kritik')) $sql .= ' AND is_service = 0 AND stock <= critical_stock';
if (get('skt')) { $sql .= ' AND expiry_date IS NOT NULL AND expiry_date <= ?'; $prm[] = date('Y-m-d', strtotime('+60 days')); }
$pg = paginate($sql . ' ORDER BY is_service, category, name', $prm, 50);
$value = (float)val('SELECT COALESCE(SUM(stock * buy_price),0) FROM products WHERE active = 1 AND is_service = 0 AND stock > 0');
$dbCats = array_column(rows('SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category'), 'category');

include APP . '/views/admin_header.php'; ?>
<div class="page-head"><div><h1>Ürünler & Hizmetler</h1><div class="sub"><?= $pg['total'] ?> kayıt · Stok değeri (alış): <b><?= money($value) ?></b></div></div>
  <div class="d-flex gap-2"><a class="btn btn-outline-primary" href="<?= url('admin/stok.php') ?>"><i class="bi bi-arrow-left-right"></i> Stok Girişi</a><?php if (is_admin()): ?><a href="<?= url('admin/aktar.php?tur=urun') ?>" class="btn btn-outline-primary text-nowrap"><i class="bi bi-file-earmark-excel"></i> Excel'den Yükle</a><?php endif; ?><a class="btn btn-primary" href="?islem=yeni"><i class="bi bi-plus-lg"></i> Yeni Ürün</a></div></div>
<form class="card mb-3"><div class="card-body row g-2 align-items-center">
  <div class="col-md-4"><input name="q" class="form-control" placeholder="Ürün adı veya barkod…" value="<?= e($qs) ?>" autofocus></div>
  <div class="col-md-3"><select name="kategori" class="form-select"><option value="">Tüm kategoriler</option><?php foreach ($dbCats as $c): ?><option <?= $c === $cat ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-5 d-flex gap-3 flex-wrap">
    <div class="form-check"><input type="checkbox" class="form-check-input" name="kritik" value="1" id="kr" <?= get('kritik') ? 'checked' : '' ?>><label for="kr" class="form-check-label">Kritik stok</label></div>
    <div class="form-check"><input type="checkbox" class="form-check-input" name="skt" value="1" id="skt" <?= get('skt') ? 'checked' : '' ?>><label for="skt" class="form-check-label">SKT yaklaşan</label></div>
    <div class="form-check"><input type="checkbox" class="form-check-input" name="pasif" value="1" id="ps" <?= get('pasif') ? 'checked' : '' ?>><label for="ps" class="form-check-label">Pasifler</label></div>
    <button class="btn btn-light btn-sm">Filtrele</button></div>
</div></form>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle">
  <thead><tr><th>Ürün</th><th>Kategori</th><th>Barkod</th><th class="text-end">Alış</th><th class="text-end">Satış</th><th>KDV</th><th class="text-end">Stok</th><th>SKT</th><th></th></tr></thead><tbody>
  <?php foreach ($pg['rows'] as $p): $crit = !$p['is_service'] && $p['stock'] <= $p['critical_stock']; ?>
    <tr>
      <td class="fw-bold"><?= e($p['name']) ?></td><td><span class="badge badge-soft"><?= e($p['category'] ?: '-') ?></span></td><td class="small"><?= e($p['barcode']) ?></td>
      <td class="text-end small"><?= money($p['buy_price']) ?></td><td class="text-end fw-bold"><?= money($p['sell_price']) ?></td><td>%<?= (int)$p['vat_rate'] ?></td>
      <td class="text-end"><?= $p['is_service'] ? '<span class="text-muted small">Hizmet</span>' : '<span class="fw-bold ' . ($crit ? 'text-danger' : '') . '">' . qty($p['stock']) . '</span> <span class="small text-muted">' . e($p['unit']) . '</span>' ?></td>
      <td class="small <?= $p['expiry_date'] && $p['expiry_date'] <= date('Y-m-d', strtotime('+60 days')) ? 'text-danger fw-bold' : '' ?>"><?= $p['expiry_date'] ? dt($p['expiry_date']) : '' ?></td>
      <td class="text-end text-nowrap"><?php if (!$p['is_service']): ?><a class="btn btn-sm btn-light" title="Stok hareketi" href="<?= url('admin/stok.php?urun=' . $p['id']) ?>"><i class="bi bi-arrow-left-right"></i></a><?php endif; ?>
        <a class="btn btn-sm btn-light" href="?islem=duzenle&id=<?= $p['id'] ?>"><i class="bi bi-pencil"></i></a></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$pg['rows']): ?><tr><td colspan="9" class="empty"><i class="bi bi-box"></i>Ürün bulunamadı.</td></tr><?php endif; ?>
  </tbody></table></div><div class="card-footer bg-white"><?= pager($pg) ?></div></div>
<?php include APP . '/views/admin_footer.php';
