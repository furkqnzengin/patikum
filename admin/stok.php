<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$pageTitle = 'Stok Hareketleri';
$active = 'urun';

if (is_post()) {
    $pid = (int)post('product_id');
    $qty = num(post('qty'));
    $type = post('type');
    $prod = row('SELECT * FROM products WHERE id = ?', [$pid]);
    if (!$prod || $qty == 0) { flash('danger', 'Ürün ve miktar girin.'); back(); }
    if ($type === 'sayim') {
        $diff = $qty - (float)$prod['stock'];
        if ($diff != 0) stock_move($pid, $diff, 'duzeltme', 'Sayım: ' . qty($prod['stock']) . ' → ' . qty($qty) . (post('note') ? ' · ' . post('note') : ''));
    } else {
        $signed = in_array($type, ['cikis', 'fire'], true) ? -abs($qty) : abs($qty);
        $cost = post('unit_cost') !== '' ? num(post('unit_cost')) : null;
        stock_move($pid, $signed, $type, nul(post('note')), null, null, $cost);
        if ($type === 'giris' && $cost) update('products', ['buy_price' => $cost], $pid);
        if ($type === 'giris' && nul(post('expiry_date'))) update('products', ['expiry_date' => post('expiry_date')], $pid);
        if ($type === 'giris' && $cost && post('gider')) {
            insert('expenses', ['category' => 'Tedarikçi Ödemesi', 'description' => $prod['name'] . ' x' . qty($qty), 'supplier' => nul(post('supplier')), 'amount' => round($cost * abs($qty), 2), 'method' => post('method', 'nakit'), 'exp_date' => today(), 'user_id' => $_SESSION['uid']]);
        }
    }
    flash('success', 'Stok hareketi kaydedildi.');
    redirect(url('admin/stok.php'));
}

$sel = get('urun') ? row('SELECT * FROM products WHERE id = ?', [(int)get('urun')]) : null;
$sql = 'SELECT m.*, p.name, p.unit, u.name AS kullanici FROM stock_movements m JOIN products p ON p.id = m.product_id LEFT JOIN users u ON u.id = m.user_id WHERE 1=1';
$prm = [];
if ($sel) { $sql .= ' AND m.product_id = ?'; $prm[] = $sel['id']; }
if ($t = get('tip')) { $sql .= ' AND m.type = ?'; $prm[] = $t; }
$pg = paginate($sql . ' ORDER BY m.id DESC', $prm, 40);
$types = ['giris' => 'Stok Girişi (Alım)', 'cikis' => 'Stok Çıkışı', 'fire' => 'Fire / Zayi', 'iade' => 'İade (Girişi)', 'sayim' => 'Sayım (stoğu eşitle)'];
$labels = $types + ['satis' => 'Satış', 'duzeltme' => 'Sayım Düzeltme'];

include APP . '/views/admin_header.php'; ?>
<div class="page-head"><div><h1>Stok Hareketleri</h1><div class="sub">Alım, çıkış, fire, sayım ve satışlardan gelen tüm hareketler</div></div></div>
<div class="row g-3">
  <div class="col-lg-4">
    <form method="post" class="card"><div class="card-header"><i class="bi bi-plus-circle text-primary"></i> Yeni Stok Hareketi</div><div class="card-body">
      <?= csrf_field() ?>
      <div class="mb-2"><label class="form-label">Ürün</label><select name="product_id" id="sProd" required><?php if ($sel): ?><option value="<?= $sel['id'] ?>" selected><?= e($sel['name']) ?></option><?php endif; ?></select>
        <?php if ($sel): ?><div class="form-text">Mevcut stok: <b><?= qty($sel['stock']) ?> <?= e($sel['unit']) ?></b></div><?php endif; ?></div>
      <div class="mb-2"><label class="form-label">Hareket Türü</label><select name="type" id="sType" class="form-select"><?= options($types, 'giris') ?></select></div>
      <div class="row g-2">
        <div class="col-6"><label class="form-label">Miktar</label><input name="qty" class="form-control" required></div>
        <div class="col-6 giris"><label class="form-label">Birim Maliyet (₺)</label><input name="unit_cost" class="form-control" value="<?= $sel ? e($sel['buy_price']) : '' ?>"></div>
        <div class="col-6 giris"><label class="form-label">Tedarikçi</label><input name="supplier" class="form-control"></div>
        <div class="col-6 giris"><label class="form-label">SKT</label><input type="date" name="expiry_date" class="form-control"></div>
      </div>
      <div class="mb-2 mt-2"><label class="form-label">Açıklama</label><input name="note" class="form-control" placeholder="Fatura no, açıklama…"></div>
      <div class="giris"><div class="form-check"><input class="form-check-input" type="checkbox" name="gider" value="1" id="gd"><label for="gd" class="form-check-label small">Tutarı gider (tedarikçi ödemesi) olarak muhasebeye işle</label></div>
        <select name="method" class="form-select form-select-sm mt-1"><?= options(['nakit' => 'Nakit', 'kart' => 'Kart', 'havale' => 'Havale'], 'nakit') ?></select></div>
    </div><div class="card-footer bg-white text-end"><button class="btn btn-primary px-4">Kaydet</button></div></form>
  </div>
  <div class="col-lg-8">
    <div class="card"><div class="card-header d-flex justify-content-between"><span><?= $sel ? e($sel['name']) . ' hareketleri' : 'Tüm hareketler' ?></span><?php if ($sel): ?><a href="<?= url('admin/stok.php') ?>" class="small">Tümünü göster</a><?php endif; ?></div>
    <div class="table-responsive"><table class="table table-sm table-hover align-middle">
      <thead><tr><th>Tarih</th><th>Ürün</th><th>Tür</th><th class="text-end">Miktar</th><th>Açıklama</th><th>Kullanıcı</th></tr></thead><tbody>
      <?php foreach ($pg['rows'] as $m): ?>
        <tr><td class="small"><?= dtt($m['created_at']) ?></td><td><a href="?urun=<?= $m['product_id'] ?>"><?= e($m['name']) ?></a></td>
          <td><span class="badge <?= $m['qty'] > 0 ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= e($labels[$m['type']] ?? $m['type']) ?></span></td>
          <td class="text-end fw-bold <?= $m['qty'] > 0 ? 'text-success' : 'text-danger' ?>"><?= $m['qty'] > 0 ? '+' : '' ?><?= qty($m['qty']) ?></td>
          <td class="small"><?= $m['ref_type'] === 'sale' ? '<a href="' . url('admin/satislar.php?id=' . $m['ref_id']) . '">' . e($m['note']) . '</a>' : e($m['note']) ?></td><td class="small text-muted"><?= e($m['kullanici']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$pg['rows']): ?><tr><td colspan="6" class="empty">Hareket yok.</td></tr><?php endif; ?>
    </tbody></table></div><div class="card-footer bg-white"><?= pager($pg) ?></div></div>
  </div>
</div>
<?php
$pageScripts = '<script>ptProductSelect(document.getElementById("sProd"),o=>{});const st=document.getElementById("sType");const tg=()=>document.querySelectorAll(".giris").forEach(x=>x.style.display=st.value==="giris"?"":"none");st.onchange=tg;tg();</script>';
include APP . '/views/admin_footer.php';
