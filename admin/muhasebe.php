<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$pageTitle = 'Muhasebe';
$active = 'muhasebe';
$tab = get('sekme', 'ozet');
$expCats = array_values(array_filter(array_map('trim', explode(',', setting('expense_categories', 'Kira,Maaş,Fatura,Tedarikçi Ödemesi,Diğer')))));

if (is_post()) {
    $act = post('islem');
    if ($act === 'tahsilat') {
        $amt = num(post('amount'));
        $cid = (int)post('customer_id');
        if ($amt <= 0 || !$cid) { flash('danger', 'Müşteri ve tutar girin.'); back(); }
        payment_create($cid, $amt, post('method', 'nakit'), (post('date') ?: today()) . ' ' . date('H:i:s'), nul(post('note')));
        flash('success', money($amt) . ' tahsilat kaydedildi. Yeni bakiye: ' . money(customer_balance($cid)));
        redirect(url('admin/muhasebe.php?sekme=tahsilat'));
    }
    if ($act === 'tahsilat_sil' && is_admin()) {
        delete_row('payments', (int)post('id'));
        flash('success', 'Tahsilat silindi.');
    }
    if ($act === 'gider') {
        $amt = num(post('amount'));
        if ($amt <= 0) { flash('danger', 'Tutar girin.'); back(); }
        insert('expenses', ['category' => post('category'), 'description' => nul(post('description')), 'supplier' => nul(post('supplier')), 'amount' => $amt, 'method' => post('method', 'nakit'), 'exp_date' => post('date') ?: today(), 'user_id' => $_SESSION['uid']]);
        flash('success', 'Gider kaydedildi.');
        redirect(url('admin/muhasebe.php?sekme=gider'));
    }
    if ($act === 'gider_sil' && is_admin()) {
        delete_row('expenses', (int)post('id'));
        flash('success', 'Gider silindi.');
    }
    if ($act === 'borc_hatirlat') {
        $n = 0;
        foreach ((array)($_POST['ids'] ?? []) as $cid) {
            $b = customer_balance((int)$cid);
            if ($b > 0 && wa_queue((int)$cid, null, 'borc_hatirlatma', ['borc' => money($b)], null, true)) $n++;
        }
        flash('success', "$n müşteriye bakiye hatırlatması kuyruğa alındı. <a class='alert-link' href='" . url('admin/whatsapp.php') . "'>WhatsApp kuyruğu</a>");
    }
    back(url('admin/muhasebe.php'));
}

$from = get('baslangic', date('Y-m-01'));
$to = get('bitis', today());
$toN = date('Y-m-d', strtotime($to . ' +1 day'));
$tabs = ['ozet' => ['Kasa Özeti', 'safe'], 'tahsilat' => ['Tahsilatlar', 'box-arrow-in-down'], 'gider' => ['Giderler / Ödemeler', 'box-arrow-up'], 'cari' => ['Cari Hesaplar', 'journal-text']];

include APP . '/views/admin_header.php'; ?>
<div class="page-head"><div><h1>Muhasebe</h1><div class="sub">Kasa, tahsilat, gider ve cari hesap yönetimi</div></div>
  <div class="d-flex gap-2"><a class="btn btn-success" href="?sekme=tahsilat&yeni=1"><i class="bi bi-plus-lg"></i> Tahsilat</a><a class="btn btn-outline-danger" href="?sekme=gider&yeni=1"><i class="bi bi-dash-lg"></i> Gider / Ödeme</a><a class="btn btn-outline-primary" href="<?= url('admin/e-donusum.php') ?>"><i class="bi bi-file-earmark-text"></i> E-Dönüşüm</a></div></div>
<ul class="nav nav-pills mb-3">
  <?php foreach ($tabs as $k => [$l, $i]): ?><li class="nav-item"><a class="nav-link <?= $tab === $k ? 'active' : '' ?>" href="?sekme=<?= $k ?>"><i class="bi bi-<?= $i ?>"></i> <?= $l ?></a></li><?php endforeach; ?>
</ul>
<?php if ($tab !== 'cari'): ?>
<form class="d-flex gap-2 mb-3 flex-wrap"><input type="hidden" name="sekme" value="<?= e($tab) ?>">
  <input type="date" name="baslangic" class="form-control" style="width:auto" value="<?= e($from) ?>"><input type="date" name="bitis" class="form-control" style="width:auto" value="<?= e($to) ?>"><button class="btn btn-light">Uygula</button>
  <a class="btn btn-link" href="?sekme=<?= e($tab) ?>&baslangic=<?= today() ?>&bitis=<?= today() ?>">Bugün</a><a class="btn btn-link" href="?sekme=<?= e($tab) ?>&baslangic=<?= date('Y-m-01') ?>">Bu ay</a><a class="btn btn-link" href="?sekme=<?= e($tab) ?>&baslangic=<?= date('Y-01-01') ?>">Bu yıl</a>
</form>
<?php endif; ?>

<?php if ($tab === 'ozet'):
    $in = rows('SELECT method, SUM(amount) t FROM payments WHERE pay_date >= ? AND pay_date < ? GROUP BY method', [$from, $toN]);
    $out = rows('SELECT method, SUM(amount) t FROM expenses WHERE exp_date >= ? AND exp_date <= ? GROUP BY method', [$from, $to]);
    $inT = array_sum(array_column($in, 't'));
    $outT = array_sum(array_column($out, 't'));
    $salesT = (float)val("SELECT COALESCE(SUM(total),0) FROM sales WHERE status='aktif' AND sale_date >= ? AND sale_date < ?", [$from, $toN]);
    $byCat = rows('SELECT category, SUM(amount) t FROM expenses WHERE exp_date >= ? AND exp_date <= ? GROUP BY category ORDER BY t DESC', [$from, $to]);
    $methods = ['nakit', 'kart', 'havale', 'diger'];
    $inM = array_column($in, 't', 'method');
    $outM = array_column($out, 't', 'method'); ?>
  <div class="row g-3 mb-3">
    <div class="col-md-3"><div class="kpi"><div class="ic grad-orange"><i class="bi bi-bag"></i></div><div><div class="l">Satış (Ciro)</div><div class="v"><?= money($salesT) ?></div></div></div></div>
    <div class="col-md-3"><div class="kpi"><div class="ic grad-green"><i class="bi bi-box-arrow-in-down"></i></div><div><div class="l">Tahsilat (Giriş)</div><div class="v text-success"><?= money($inT) ?></div></div></div></div>
    <div class="col-md-3"><div class="kpi"><div class="ic grad-pink"><i class="bi bi-box-arrow-up"></i></div><div><div class="l">Gider (Çıkış)</div><div class="v text-danger"><?= money($outT) ?></div></div></div></div>
    <div class="col-md-3"><div class="kpi"><div class="ic grad-teal"><i class="bi bi-safe"></i></div><div><div class="l">Net Kasa</div><div class="v"><?= money($inT - $outT) ?></div></div></div></div>
  </div>
  <div class="row g-3">
    <div class="col-lg-6"><div class="card"><div class="card-header">Ödeme Yöntemine Göre</div><table class="table mb-0"><thead><tr><th>Yöntem</th><th class="text-end">Giriş</th><th class="text-end">Çıkış</th><th class="text-end">Net</th></tr></thead><tbody>
      <?php foreach ($methods as $m): ?><tr><td><?= pay_method_label($m) ?></td><td class="text-end text-success"><?= money($inM[$m] ?? 0) ?></td><td class="text-end text-danger"><?= money($outM[$m] ?? 0) ?></td><td class="text-end fw-bold"><?= money(($inM[$m] ?? 0) - ($outM[$m] ?? 0)) ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
    <div class="col-lg-6"><div class="card"><div class="card-header">Gider Dağılımı</div><div class="card-body">
      <?php foreach ($byCat as $c): $pct = $outT > 0 ? $c['t'] / $outT * 100 : 0; ?>
        <div class="mb-2"><div class="d-flex justify-content-between small"><span><?= e($c['category'] ?: 'Diğer') ?></span><b><?= money($c['t']) ?></b></div><div class="progress" style="height:8px"><div class="progress-bar bg-danger" style="width:<?= round($pct) ?>%"></div></div></div>
      <?php endforeach; ?>
      <?php if (!$byCat): ?><div class="empty">Bu dönemde gider yok.</div><?php endif; ?>
    </div></div></div>
  </div>

<?php elseif ($tab === 'tahsilat'):
    $cust = get('musteri') ? row('SELECT id, name FROM customers WHERE id = ?', [(int)get('musteri')]) : null;
    $pg = paginate('SELECT p.*, c.name AS musteri, u.name AS kullanici FROM payments p LEFT JOIN customers c ON c.id = p.customer_id LEFT JOIN users u ON u.id = p.user_id WHERE p.pay_date >= ? AND p.pay_date < ? ORDER BY p.pay_date DESC, p.id DESC', [$from, $toN], 40);
    $total = (float)val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE pay_date >= ? AND pay_date < ?', [$from, $toN]); ?>
  <div class="row g-3">
    <div class="col-lg-4"><form method="post" class="card <?= get('yeni') ? 'border border-success border-2' : '' ?>"><div class="card-header text-success"><i class="bi bi-cash-coin"></i> Hızlı Tahsilat</div><div class="card-body">
      <?= csrf_field() ?><input type="hidden" name="islem" value="tahsilat">
      <div class="mb-2"><label class="form-label">Müşteri</label><select name="customer_id" id="tCust" class="js-customer" required><?php if ($cust): ?><option value="<?= $cust['id'] ?>" selected><?= e($cust['name']) ?></option><?php endif; ?></select></div>
      <div class="small mb-2 fw-bold" id="bal"></div>
      <div class="row g-2"><div class="col-6"><label class="form-label">Tutar (₺)</label><input name="amount" class="form-control" required></div><div class="col-6"><label class="form-label">Tarih</label><input type="date" name="date" class="form-control" value="<?= today() ?>"></div></div>
      <div class="mt-2"><label class="form-label">Yöntem</label><select name="method" class="form-select"><?= options(['nakit' => 'Nakit', 'kart' => 'Kredi Kartı', 'havale' => 'Havale/EFT', 'diger' => 'Diğer'], 'nakit') ?></select></div>
      <div class="mt-2"><label class="form-label">Açıklama</label><input name="note" class="form-control"></div>
    </div><div class="card-footer bg-white text-end"><button class="btn btn-success px-4">Tahsil Et</button></div></form></div>
    <div class="col-lg-8"><div class="card"><div class="card-header d-flex justify-content-between"><span>Tahsilatlar</span><span class="text-success">Toplam: <?= money($total) ?></span></div>
      <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Tarih</th><th>Müşteri</th><th>Yöntem</th><th>Açıklama</th><th class="text-end">Tutar</th><th></th></tr></thead><tbody>
      <?php foreach ($pg['rows'] as $p): ?><tr><td class="small"><?= dtt($p['pay_date']) ?></td><td><?= $p['customer_id'] ? '<a href="' . url('admin/musteri.php?id=' . $p['customer_id']) . '">' . e($p['musteri']) . '</a>' : 'Peşin' ?></td><td><?= pay_method_label($p['method']) ?></td><td class="small"><?= e($p['note']) ?></td><td class="text-end fw-bold text-success"><?= money($p['amount']) ?></td>
        <td class="text-end"><?php if (is_admin()): ?><form method="post" data-confirm="Tahsilat silinsin mi?"><?= csrf_field() ?><input type="hidden" name="islem" value="tahsilat_sil"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="btn btn-sm btn-link text-danger"><i class="bi bi-trash"></i></button></form><?php endif; ?></td></tr><?php endforeach; ?>
      <?php if (!$pg['rows']): ?><tr><td colspan="6" class="empty">Tahsilat yok.</td></tr><?php endif; ?>
      </tbody></table></div><div class="card-footer bg-white"><?= pager($pg) ?></div></div></div>
  </div>
  <?php $pageScripts = '<script>const tc=document.getElementById("tCust");const sb=()=>{if(!tc.value){document.getElementById("bal").textContent="";return;}const o=tc.tomselect&&tc.tomselect.options[tc.value];document.getElementById("bal").textContent=o?o.text:"";};tc.addEventListener("change",sb);</script>';

elseif ($tab === 'gider'):
    $pg = paginate('SELECT e.*, u.name AS kullanici FROM expenses e LEFT JOIN users u ON u.id = e.user_id WHERE e.exp_date >= ? AND e.exp_date <= ? ORDER BY e.exp_date DESC, e.id DESC', [$from, $to], 40);
    $total = (float)val('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE exp_date >= ? AND exp_date <= ?', [$from, $to]); ?>
  <div class="row g-3">
    <div class="col-lg-4"><form method="post" class="card <?= get('yeni') ? 'border border-danger border-2' : '' ?>"><div class="card-header text-danger"><i class="bi bi-box-arrow-up"></i> Gider / Ödeme Ekle</div><div class="card-body">
      <?= csrf_field() ?><input type="hidden" name="islem" value="gider">
      <div class="mb-2"><label class="form-label">Kategori</label><select name="category" class="form-select"><?= options(array_combine($expCats, $expCats), '') ?></select></div>
      <div class="mb-2"><label class="form-label">Açıklama</label><input name="description" class="form-control"></div>
      <div class="mb-2"><label class="form-label">Tedarikçi / Kişi</label><input name="supplier" class="form-control"></div>
      <div class="row g-2"><div class="col-6"><label class="form-label">Tutar (₺)</label><input name="amount" class="form-control" required></div><div class="col-6"><label class="form-label">Tarih</label><input type="date" name="date" class="form-control" value="<?= today() ?>"></div></div>
      <div class="mt-2"><label class="form-label">Yöntem</label><select name="method" class="form-select"><?= options(['nakit' => 'Nakit', 'kart' => 'Kredi Kartı', 'havale' => 'Havale/EFT', 'diger' => 'Diğer'], 'nakit') ?></select></div>
    </div><div class="card-footer bg-white text-end"><button class="btn btn-danger px-4">Kaydet</button></div></form></div>
    <div class="col-lg-8"><div class="card"><div class="card-header d-flex justify-content-between"><span>Giderler</span><span class="text-danger">Toplam: <?= money($total) ?></span></div>
      <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Tarih</th><th>Kategori</th><th>Açıklama</th><th>Yöntem</th><th class="text-end">Tutar</th><th></th></tr></thead><tbody>
      <?php foreach ($pg['rows'] as $x): ?><tr><td class="small"><?= dt($x['exp_date']) ?></td><td><span class="badge text-bg-light"><?= e($x['category']) ?></span></td><td class="small"><?= e($x['description']) ?><?= $x['supplier'] ? '<div class="text-muted">' . e($x['supplier']) . '</div>' : '' ?></td><td><?= pay_method_label($x['method']) ?></td><td class="text-end fw-bold text-danger"><?= money($x['amount']) ?></td>
        <td class="text-end"><?php if (is_admin()): ?><form method="post" data-confirm="Gider silinsin mi?"><?= csrf_field() ?><input type="hidden" name="islem" value="gider_sil"><input type="hidden" name="id" value="<?= $x['id'] ?>"><button class="btn btn-sm btn-link text-danger"><i class="bi bi-trash"></i></button></form><?php endif; ?></td></tr><?php endforeach; ?>
      <?php if (!$pg['rows']): ?><tr><td colspan="6" class="empty">Gider yok.</td></tr><?php endif; ?>
      </tbody></table></div><div class="card-footer bg-white"><?= pager($pg) ?></div></div></div>
  </div>

<?php else:
    $list = rows('SELECT * FROM (SELECT c.id, c.name, c.phone, ' . balance_sql() . ' AS bakiye, (SELECT MAX(pay_date) FROM payments p WHERE p.customer_id = c.id) AS son_odeme FROM customers c WHERE c.active = 1) t WHERE bakiye <> 0 ORDER BY bakiye DESC');
    $recv = array_sum(array_filter(array_column($list, 'bakiye'), fn($b) => $b > 0));
    $adv = array_sum(array_filter(array_column($list, 'bakiye'), fn($b) => $b < 0)); ?>
  <div class="row g-3 mb-3">
    <div class="col-md-6"><div class="kpi"><div class="ic grad-pink"><i class="bi bi-journal-minus"></i></div><div><div class="l">Toplam Alacak (müşteri borçları)</div><div class="v text-danger"><?= money($recv) ?></div></div></div></div>
    <div class="col-md-6"><div class="kpi"><div class="ic grad-teal"><i class="bi bi-journal-plus"></i></div><div><div class="l">Müşteri Avansları</div><div class="v"><?= money(abs($adv)) ?></div></div></div></div>
  </div>
  <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="islem" value="borc_hatirlat">
    <div class="card-header d-flex justify-content-between align-items-center"><span>Bakiyesi Olan Müşteriler</span><button class="btn btn-sm btn-success" data-confirm="Seçili müşterilere WhatsApp bakiye hatırlatması gönderilsin mi?"><i class="bi bi-whatsapp"></i> Seçililere Hatırlatma Gönder</button></div>
    <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th><input type="checkbox" class="form-check-input" onclick="document.querySelectorAll('.cb').forEach(c=>c.checked=this.checked)"></th><th>Müşteri</th><th>Telefon</th><th>Son Ödeme</th><th class="text-end">Bakiye</th><th></th></tr></thead><tbody>
    <?php foreach ($list as $c): ?><tr><td><?php if ($c['bakiye'] > 0): ?><input type="checkbox" class="form-check-input cb" name="ids[]" value="<?= $c['id'] ?>"><?php endif; ?></td>
      <td><a class="fw-bold" href="<?= url('admin/musteri.php?id=' . $c['id']) ?>"><?= e($c['name']) ?></a></td><td><?= e(phone_fmt($c['phone'])) ?></td><td class="small"><?= dt($c['son_odeme']) ?></td>
      <td class="text-end fw-bold <?= $c['bakiye'] > 0 ? 'text-danger' : 'text-success' ?>"><?= money($c['bakiye']) ?><?= $c['bakiye'] < 0 ? ' <span class="small fw-normal">(avans)</span>' : '' ?></td>
      <td class="text-end"><a class="btn btn-sm btn-outline-success" href="?sekme=tahsilat&yeni=1&musteri=<?= $c['id'] ?>">Tahsilat</a></td></tr><?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="6" class="empty"><i class="bi bi-emoji-smile"></i>Açık bakiye yok.</td></tr><?php endif; ?>
    </tbody></table></div></form>
<?php endif; ?>
<?php include APP . '/views/admin_footer.php';
