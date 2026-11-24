<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$pageTitle = 'Satışlar';
$active = 'satis';

if (is_post()) {
    $id = (int)post('id');
    $act = post('islem');
    if ($act === 'iptal' && is_admin()) {
        sale_cancel($id);
        flash('success', 'Satış iptal edildi, stoklar geri alındı.');
    }
    if ($act === 'tahsilat') {
        $s = row('SELECT * FROM sales WHERE id = ?', [$id]);
        $amt = num(post('amount'));
        if ($s && $amt > 0) {
            payment_create($s['customer_id'] ? (int)$s['customer_id'] : null, $amt, post('method', 'nakit'), null, 'Satış #' . $id . ' tahsilatı', $id);
            flash('success', money($amt) . ' tahsil edildi.');
        }
    }
    if ($act === 'fatura') {
        try {
            $inv = einvoice_create($id, post('tip') ?: null);
            redirect(url('admin/e-donusum.php?id=' . $inv));
        } catch (Throwable $e) {
            flash('danger', e($e->getMessage()));
        }
    }
    redirect(url('admin/satislar.php?id=' . $id));
}

if ($id = (int)get('id')) {
    $s = row('SELECT s.*, c.name AS musteri, c.phone, p.name AS hasta, u.name AS kullanici FROM sales s LEFT JOIN customers c ON c.id = s.customer_id LEFT JOIN patients p ON p.id = s.patient_id LEFT JOIN users u ON u.id = s.user_id WHERE s.id = ?', [$id]);
    if (!$s) redirect(url('admin/satislar.php'));
    $items = rows('SELECT * FROM sale_items WHERE sale_id = ?', [$id]);
    $pays = rows('SELECT * FROM payments WHERE sale_id = ? ORDER BY pay_date', [$id]);
    $paid = array_sum(array_column($pays, 'amount'));
    $inv = row("SELECT * FROM invoices WHERE sale_id = ? AND status <> 'iptal'", [$id]);
    include APP . '/views/admin_header.php'; ?>
    <div class="page-head no-print"><div><h1>Satış #<?= $id ?> <?= status_badge($s['status']) ?></h1><div class="sub"><?= dtt($s['sale_date']) ?> · <?= e($s['kullanici']) ?></div></div>
      <div class="d-flex gap-2 flex-wrap">
        <button onclick="window.print()" class="btn btn-light"><i class="bi bi-printer"></i> Yazdır</button>
        <?php if ($s['status'] === 'aktif'): ?>
          <?php if ($inv): ?><a class="btn btn-outline-primary" href="<?= url('admin/e-donusum.php?id=' . $inv['id']) ?>"><i class="bi bi-file-earmark-text"></i> Fatura <?= e($inv['invoice_no']) ?></a>
          <?php else: ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="islem" value="fatura"><button class="btn btn-outline-primary"><i class="bi bi-file-earmark-plus"></i> e-Fatura / e-Arşiv Oluştur</button></form><?php endif; ?>
          <?php if (is_admin()): ?><form method="post" data-confirm="Satış iptal edilsin mi? Stoklar geri alınır, bağlı tahsilatlar silinir."><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="islem" value="iptal"><button class="btn btn-outline-danger"><i class="bi bi-x-circle"></i> İptal</button></form><?php endif; ?>
        <?php endif; ?>
      </div></div>
    <div class="row g-3">
      <div class="col-lg-8"><div class="card"><div class="card-body">
        <div class="d-flex justify-content-between mb-3">
          <div><h5 class="fw-bold mb-0"><?= e(setting('clinic_name')) ?></h5><div class="small text-muted"><?= e(setting('clinic_address')) ?><br><?= e(setting('clinic_phone')) ?></div></div>
          <div class="text-end"><div class="fw-bold">SATIŞ FİŞİ #<?= $id ?></div><div class="small"><?= dtt($s['sale_date']) ?></div></div>
        </div>
        <div class="mb-3"><b>Müşteri:</b> <?= $s['customer_id'] ? '<a href="' . url('admin/musteri.php?id=' . $s['customer_id']) . '">' . e($s['musteri']) . '</a>' : 'Peşin müşteri' ?><?= $s['hasta'] ? ' · <b>Hasta:</b> ' . e($s['hasta']) : '' ?></div>
        <table class="table table-sm"><thead><tr><th>Açıklama</th><th class="text-end">Miktar</th><th class="text-end">Birim</th><th class="text-end">KDV</th><th class="text-end">Tutar</th></tr></thead><tbody>
          <?php foreach ($items as $i): ?><tr><td><?= e($i['description']) ?></td><td class="text-end"><?= qty($i['qty']) ?></td><td class="text-end"><?= money($i['unit_price']) ?></td><td class="text-end">%<?= (int)$i['vat_rate'] ?></td><td class="text-end"><?= money($i['total']) ?></td></tr><?php endforeach; ?>
        </tbody><tfoot>
          <?php if ($s['discount'] > 0): ?><tr><td colspan="4" class="text-end">Ara toplam</td><td class="text-end"><?= money($s['subtotal']) ?></td></tr><tr><td colspan="4" class="text-end">İndirim</td><td class="text-end">-<?= money($s['discount']) ?></td></tr><?php endif; ?>
          <tr><td colspan="4" class="text-end small text-muted">Toplam içindeki KDV</td><td class="text-end small text-muted"><?= money($s['vat_total']) ?></td></tr>
          <tr><td colspan="4" class="text-end fw-bold">GENEL TOPLAM</td><td class="text-end fw-bold fs-5"><?= money($s['total']) ?></td></tr>
          <tr><td colspan="4" class="text-end">Ödenen</td><td class="text-end text-success"><?= money($paid) ?></td></tr>
          <tr><td colspan="4" class="text-end">Kalan</td><td class="text-end fw-bold <?= $s['total'] - $paid > 0 ? 'text-danger' : '' ?>"><?= money(max(0, $s['total'] - $paid)) ?></td></tr>
        </tfoot></table>
        <?php if ($s['note']): ?><div class="small text-muted">Not: <?= e($s['note']) ?></div><?php endif; ?>
      </div></div></div>
      <div class="col-lg-4 no-print">
        <div class="card mb-3"><div class="card-header">Tahsilatlar</div><div class="card-body py-1">
          <?php foreach ($pays as $p): ?><div class="list-row"><div class="flex-grow-1"><?= dtt($p['pay_date']) ?><div class="small text-muted"><?= pay_method_label($p['method']) ?></div></div><b class="text-success"><?= money($p['amount']) ?></b></div><?php endforeach; ?>
          <?php if (!$pays): ?><div class="empty py-3">Tahsilat yok.</div><?php endif; ?>
        </div>
        <?php if ($s['status'] === 'aktif' && $s['total'] - $paid > 0): ?>
          <form method="post" class="card-footer bg-white"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="islem" value="tahsilat">
            <div class="input-group input-group-sm"><input name="amount" class="form-control" value="<?= number_format($s['total'] - $paid, 2, '.', '') ?>"><select name="method" class="form-select"><?= options(['nakit' => 'Nakit', 'kart' => 'Kart', 'havale' => 'Havale'], 'nakit') ?></select><button class="btn btn-success">Tahsil Et</button></div></form>
        <?php endif; ?></div>
      </div>
    </div>
    <?php include APP . '/views/admin_footer.php';
    exit;
}

$from = get('baslangic', date('Y-m-01'));
$to = get('bitis', today());
$sql = "SELECT s.*, c.name AS musteri, p.name AS hasta, (SELECT COALESCE(SUM(amount),0) FROM payments x WHERE x.sale_id = s.id) AS odenen,
        (SELECT invoice_no FROM invoices i WHERE i.sale_id = s.id AND i.status <> 'iptal' LIMIT 1) AS fatura
        FROM sales s LEFT JOIN customers c ON c.id = s.customer_id LEFT JOIN patients p ON p.id = s.patient_id WHERE s.sale_date >= ? AND s.sale_date < ?";
$prm = [$from, date('Y-m-d', strtotime($to . ' +1 day'))];
if (!get('iptal')) $sql .= " AND s.status = 'aktif'";
if ($qs = get('q')) { $sql .= ' AND (c.name LIKE ? OR s.note LIKE ?)'; array_push($prm, "%$qs%", "%$qs%"); }
$sum = row("SELECT COALESCE(SUM(total),0) t, COUNT(*) n FROM sales WHERE status = 'aktif' AND sale_date >= ? AND sale_date < ?", array_slice($prm, 0, 2));
$pg = paginate($sql . ' ORDER BY s.sale_date DESC, s.id DESC', $prm, 40);

include APP . '/views/admin_header.php'; ?>
<div class="page-head"><div><h1>Satışlar</h1><div class="sub"><?= (int)$sum['n'] ?> satış · Toplam <b><?= money($sum['t']) ?></b></div></div>
  <div class="d-flex gap-2 flex-wrap"><?php if (is_admin()): ?><a href="<?= url('admin/aktar.php?tur=satis') ?>" class="btn btn-outline-primary text-nowrap"><i class="bi bi-file-earmark-excel"></i> Excel'den Yükle</a><?php endif; ?><a class="btn btn-primary" href="<?= url('admin/satis.php') ?>"><i class="bi bi-cart-plus"></i> Yeni Satış</a></div></div>
<form class="card mb-3"><div class="card-body row g-2 align-items-end">
  <div class="col-md-3"><label class="form-label small">Başlangıç</label><input type="date" name="baslangic" class="form-control" value="<?= e($from) ?>"></div>
  <div class="col-md-3"><label class="form-label small">Bitiş</label><input type="date" name="bitis" class="form-control" value="<?= e($to) ?>"></div>
  <div class="col-md-3"><label class="form-label small">Ara</label><input name="q" class="form-control" value="<?= e(get('q')) ?>" placeholder="Müşteri, not"></div>
  <div class="col-md-2"><div class="form-check"><input type="checkbox" class="form-check-input" name="iptal" value="1" id="ip" <?= get('iptal') ? 'checked' : '' ?>><label for="ip" class="form-check-label">İptaller dahil</label></div></div>
  <div class="col-md-1"><button class="btn btn-light w-100"><i class="bi bi-funnel"></i></button></div>
</div></form>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle">
  <thead><tr><th>#</th><th>Tarih</th><th>Müşteri</th><th>Açıklama</th><th class="text-end">Tutar</th><th class="text-end">Ödenen</th><th>Fatura</th><th></th></tr></thead><tbody>
  <?php foreach ($pg['rows'] as $s): $kalan = $s['total'] - $s['odenen']; ?>
    <tr class="<?= $s['status'] === 'iptal' ? 'opacity-50 text-decoration-line-through' : '' ?>">
      <td class="small text-muted"><?= $s['id'] ?></td><td><?= dtt($s['sale_date']) ?></td>
      <td><?= $s['customer_id'] ? '<a href="' . url('admin/musteri.php?id=' . $s['customer_id']) . '">' . e($s['musteri']) . '</a>' : '<span class="text-muted">Peşin</span>' ?><?= $s['hasta'] ? '<div class="small text-muted">' . e($s['hasta']) . '</div>' : '' ?></td>
      <td class="small"><?= e($s['note'] ?: '-') ?><?= in_array($s['source'], ['kolayvet', 'kolayvet_devir'], true) ? ' <span class="badge text-bg-light border">Kolayvet</span>' : '' ?></td>
      <td class="text-end fw-bold"><?= money($s['total']) ?></td>
      <td class="text-end"><?= money($s['odenen']) ?><?= $kalan > 0.009 && $s['status'] === 'aktif' ? '<div class="small text-danger">Kalan ' . money($kalan) . '</div>' : '' ?></td>
      <td class="small"><?= e($s['fatura'] ?: '') ?></td>
      <td class="text-end"><a class="btn btn-sm btn-light" href="?id=<?= $s['id'] ?>"><i class="bi bi-eye"></i></a></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$pg['rows']): ?><tr><td colspan="8" class="empty"><i class="bi bi-receipt"></i>Bu aralıkta satış yok.</td></tr><?php endif; ?>
  </tbody></table></div><div class="card-footer bg-white"><?= pager($pg) ?></div></div>
<?php include APP . '/views/admin_footer.php';
