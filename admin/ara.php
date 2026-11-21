<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$pageTitle = 'Arama';
$qs = trim((string)get('q', ''));
$like = "%$qs%";
$digits = ltrim(preg_replace('/\D/', '', $qs), '0');
$cust = $pats = $prods = [];
if (mb_strlen($qs) >= 2) {
    $cust = rows('SELECT * FROM customers WHERE active = 1 AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR tc_no = ?' . ($digits ? ' OR REPLACE(REPLACE(phone,\' \',\'\'),\'-\',\'\') LIKE ?' : '') . ') LIMIT 30', array_merge([$like, $like, $like, $qs], $digits ? ["%$digits%"] : []));
    $pats = rows('SELECT p.*, s.icon, c.name AS sahip FROM patients p LEFT JOIN species s ON s.id = p.species_id JOIN customers c ON c.id = p.customer_id WHERE p.name LIKE ? OR p.chip_no LIKE ? OR p.passport_no LIKE ? LIMIT 30', [$like, $like, $like]);
    $prods = rows('SELECT * FROM products WHERE name LIKE ? OR barcode = ? LIMIT 20', [$like, $qs]);
}
if (count($cust) + count($pats) + count($prods) === 1) {
    if ($cust) redirect(url('admin/musteri.php?id=' . $cust[0]['id']));
    if ($pats) redirect(url('admin/hasta.php?id=' . $pats[0]['id']));
    if ($prods) redirect(url('admin/urunler.php?islem=duzenle&id=' . $prods[0]['id']));
}
include APP . '/views/admin_header.php'; ?>
<div class="page-head"><div><h1>"<?= e($qs) ?>" için sonuçlar</h1></div></div>
<div class="row g-3">
  <div class="col-lg-4"><div class="card"><div class="card-header"><i class="bi bi-people"></i> Müşteriler (<?= count($cust) ?>)</div><div class="card-body py-1">
    <?php foreach ($cust as $c): ?><a class="list-row text-reset" href="<?= url('admin/musteri.php?id=' . $c['id']) ?>"><div class="flex-grow-1"><b><?= e($c['name']) ?></b><div class="small text-muted"><?= e(phone_fmt($c['phone'])) ?></div></div><i class="bi bi-chevron-right"></i></a><?php endforeach; ?>
    <?php if (!$cust): ?><div class="empty py-3">Sonuç yok.</div><?php endif; ?></div></div></div>
  <div class="col-lg-4"><div class="card"><div class="card-header"><i class="bi bi-heart-pulse"></i> Hastalar (<?= count($pats) ?>)</div><div class="card-body py-1">
    <?php foreach ($pats as $p): ?><a class="list-row text-reset" href="<?= url('admin/hasta.php?id=' . $p['id']) ?>"><div class="pet-avatar"><?= species_icon($p['icon']) ?></div><div class="flex-grow-1"><b><?= e($p['name']) ?></b><div class="small text-muted"><?= e($p['sahip']) ?></div></div></a><?php endforeach; ?>
    <?php if (!$pats): ?><div class="empty py-3">Sonuç yok.</div><?php endif; ?></div></div></div>
  <div class="col-lg-4"><div class="card"><div class="card-header"><i class="bi bi-box-seam"></i> Ürünler (<?= count($prods) ?>)</div><div class="card-body py-1">
    <?php foreach ($prods as $p): ?><a class="list-row text-reset" href="<?= url('admin/urunler.php?islem=duzenle&id=' . $p['id']) ?>"><div class="flex-grow-1"><b><?= e($p['name']) ?></b><div class="small text-muted"><?= money($p['sell_price']) ?> · Stok <?= qty($p['stock']) ?></div></div></a><?php endforeach; ?>
    <?php if (!$prods): ?><div class="empty py-3">Sonuç yok.</div><?php endif; ?></div></div></div>
</div>
<?php include APP . '/views/admin_footer.php';
