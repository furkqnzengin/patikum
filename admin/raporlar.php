<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$pageTitle = 'Raporlar';
$active = 'rapor';

$from = get('baslangic', date('Y-m-01'));
$to = get('bitis', today());
$toN = date('Y-m-d', strtotime($to . ' +1 day'));

// CSV dışa aktarım
if ($csv = get('csv')) {
    $sets = [
        'satis' => ["SELECT s.id AS 'No', s.sale_date AS 'Tarih', COALESCE(c.name,'Peşin') AS 'Müşteri', s.note AS 'Açıklama', s.subtotal AS 'Ara Toplam', s.discount AS 'İndirim', s.vat_total AS 'KDV', s.total AS 'Toplam' FROM sales s LEFT JOIN customers c ON c.id = s.customer_id WHERE s.status='aktif' AND s.sale_date >= ? AND s.sale_date < ? ORDER BY s.sale_date", [$from, $toN]],
        'tahsilat' => ["SELECT p.pay_date AS 'Tarih', COALESCE(c.name,'Peşin') AS 'Müşteri', p.method AS 'Yöntem', p.amount AS 'Tutar', p.note AS 'Açıklama' FROM payments p LEFT JOIN customers c ON c.id = p.customer_id WHERE p.pay_date >= ? AND p.pay_date < ? ORDER BY p.pay_date", [$from, $toN]],
        'gider' => ["SELECT exp_date AS 'Tarih', category AS 'Kategori', description AS 'Açıklama', supplier AS 'Tedarikçi', method AS 'Yöntem', amount AS 'Tutar' FROM expenses WHERE exp_date >= ? AND exp_date <= ? ORDER BY exp_date", [$from, $to]],
        'alacak' => ['SELECT * FROM (SELECT c.name AS Müşteri, c.phone AS Telefon, ' . balance_sql() . ' AS Bakiye FROM customers c) t WHERE Bakiye > 0 ORDER BY Bakiye DESC', []],
        'stok' => ["SELECT name AS 'Ürün', category AS 'Kategori', barcode AS 'Barkod', stock AS 'Stok', unit AS 'Birim', buy_price AS 'Alış', sell_price AS 'Satış', expiry_date AS 'SKT' FROM products WHERE active = 1 AND is_service = 0 ORDER BY name", []],
    ];
    if (isset($sets[$csv])) {
        $data = rows($sets[$csv][0], $sets[$csv][1]);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="patikum-' . $csv . '-' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        if ($data) fputcsv($out, array_keys($data[0]), ';');
        foreach ($data as $r) fputcsv($out, array_map(fn($v) => is_numeric($v) ? str_replace('.', ',', (string)$v) : $v, $r), ';');
        exit;
    }
}

$sales = (float)val("SELECT COALESCE(SUM(total),0) FROM sales WHERE status='aktif' AND sale_date >= ? AND sale_date < ?", [$from, $toN]);
$saleCnt = (int)val("SELECT COUNT(*) FROM sales WHERE status='aktif' AND sale_date >= ? AND sale_date < ?", [$from, $toN]);
$pays = (float)val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE pay_date >= ? AND pay_date < ?', [$from, $toN]);
$exps = (float)val('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE exp_date >= ? AND exp_date <= ?', [$from, $to]);
$cost = (float)val("SELECT COALESCE(SUM(i.qty * p.buy_price),0) FROM sale_items i JOIN sales s ON s.id = i.sale_id JOIN products p ON p.id = i.product_id WHERE s.status='aktif' AND s.sale_date >= ? AND s.sale_date < ?", [$from, $toN]);
$newCust = (int)val('SELECT COUNT(*) FROM customers WHERE created_at >= ? AND created_at < ?', [$from, $toN]);
$newPat = (int)val('SELECT COUNT(*) FROM patients WHERE created_at >= ? AND created_at < ?', [$from, $toN]);
$vaccDone = (int)val("SELECT COUNT(*) FROM vaccinations WHERE status='yapildi' AND done_date >= ? AND done_date <= ?", [$from, $to]);
$exams = (int)val('SELECT COUNT(*) FROM examinations WHERE exam_date >= ? AND exam_date < ?', [$from, $toN]);

// 12 aylık trend
$months = [];
for ($i = 11; $i >= 0; $i--) $months[date('Y-m', strtotime(date('Y-m-01') . " -$i months"))] = [0, 0, 0];
$m0 = array_key_first($months) . '-01';
foreach (rows("SELECT SUBSTR(sale_date,1,7) m, SUM(total) t FROM sales WHERE status='aktif' AND sale_date >= ? GROUP BY SUBSTR(sale_date,1,7)", [$m0]) as $r) if (isset($months[$r['m']])) $months[$r['m']][0] = (float)$r['t'];
foreach (rows('SELECT SUBSTR(pay_date,1,7) m, SUM(amount) t FROM payments WHERE pay_date >= ? GROUP BY SUBSTR(pay_date,1,7)', [$m0]) as $r) if (isset($months[$r['m']])) $months[$r['m']][1] = (float)$r['t'];
foreach (rows('SELECT SUBSTR(exp_date,1,7) m, SUM(amount) t FROM expenses WHERE exp_date >= ? GROUP BY SUBSTR(exp_date,1,7)', [$m0]) as $r) if (isset($months[$r['m']])) $months[$r['m']][2] = (float)$r['t'];
$mLabels = array_map(fn($k) => mb_substr(tr_month((int)substr($k, 5, 2)), 0, 3) . ' ' . substr($k, 2, 2), array_keys($months));

$topItems = rows("SELECT i.description AS name, SUM(i.qty) q, SUM(i.total) t FROM sale_items i JOIN sales s ON s.id = i.sale_id WHERE s.status='aktif' AND s.sale_date >= ? AND s.sale_date < ? GROUP BY i.description ORDER BY t DESC LIMIT 10", [$from, $toN]);
$species = rows("SELECT COALESCE(s.name,'Belirsiz') name, COALESCE(s.color,'#94a3b8') color, COUNT(*) n FROM patients p LEFT JOIN species s ON s.id = p.species_id WHERE p.status='aktif' GROUP BY s.name, s.color ORDER BY n DESC");
$vaccTypes = rows("SELECT t.name, COUNT(*) n FROM vaccinations v JOIN vaccine_types t ON t.id = v.vaccine_type_id WHERE v.status='yapildi' AND v.done_date >= ? AND v.done_date <= ? GROUP BY t.name ORDER BY n DESC LIMIT 10", [$from, $to]);
$apptStats = array_column(rows('SELECT status, COUNT(*) n FROM appointments WHERE start_at >= ? AND start_at < ? GROUP BY status', [$from, $toN]), 'n', 'status');
$payMethods = rows('SELECT method, SUM(amount) t FROM payments WHERE pay_date >= ? AND pay_date < ? GROUP BY method', [$from, $toN]);
$topCust = rows("SELECT c.id, c.name, SUM(s.total) t, COUNT(*) n FROM sales s JOIN customers c ON c.id = s.customer_id WHERE s.status='aktif' AND s.sale_date >= ? AND s.sale_date < ? GROUP BY c.id, c.name ORDER BY t DESC LIMIT 10", [$from, $toN]);
$receivable = (float)val('SELECT COALESCE(SUM(b),0) FROM (SELECT ' . balance_sql() . ' AS b FROM customers c) t WHERE b > 0');
$vaccPlanned = (int)val("SELECT COUNT(*) FROM vaccinations v JOIN patients p ON p.id = v.patient_id WHERE v.status='planlandi' AND p.status='aktif' AND v.due_date >= ? AND v.due_date <= ?", [today(), date('Y-m-d', strtotime('+30 days'))]);

include APP . '/views/admin_header.php'; ?>
<div class="page-head"><div><h1><i class="bi bi-bar-chart-line text-primary"></i> Gelişmiş Raporlar</h1><div class="sub"><?= dt($from) ?> – <?= dt($to) ?></div></div>
  <div class="dropdown no-print"><button class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-download"></i> Excel (CSV) İndir</button>
    <ul class="dropdown-menu dropdown-menu-end"><?php foreach (['satis' => 'Satışlar', 'tahsilat' => 'Tahsilatlar', 'gider' => 'Giderler', 'alacak' => 'Alacak Listesi', 'stok' => 'Stok Listesi'] as $k => $l): ?><li><a class="dropdown-item" href="?<?= e(http_build_query(['csv' => $k, 'baslangic' => $from, 'bitis' => $to])) ?>"><?= $l ?></a></li><?php endforeach; ?></ul></div></div>
<form class="d-flex gap-2 mb-3 flex-wrap no-print">
  <input type="date" name="baslangic" class="form-control" style="width:auto" value="<?= e($from) ?>"><input type="date" name="bitis" class="form-control" style="width:auto" value="<?= e($to) ?>"><button class="btn btn-light">Uygula</button>
  <a class="btn btn-link" href="?baslangic=<?= today() ?>&bitis=<?= today() ?>">Bugün</a>
  <a class="btn btn-link" href="?baslangic=<?= date('Y-m-d', strtotime('monday this week')) ?>">Bu hafta</a>
  <a class="btn btn-link" href="?baslangic=<?= date('Y-m-01') ?>">Bu ay</a>
  <a class="btn btn-link" href="?baslangic=<?= date('Y-m-01', strtotime('first day of last month')) ?>&bitis=<?= date('Y-m-t', strtotime('last month')) ?>">Geçen ay</a>
  <a class="btn btn-link" href="?baslangic=<?= date('Y-01-01') ?>">Bu yıl</a>
  <button type="button" onclick="window.print()" class="btn btn-light ms-auto"><i class="bi bi-printer"></i></button>
</form>

<div class="row g-3 mb-3">
  <?php foreach ([
      ['Ciro', money($sales), $saleCnt . ' satış', 'grad-orange', 'bag'], ['Tahsilat', money($pays), 'kasaya giren', 'grad-green', 'cash-stack'],
      ['Gider', money($exps), 'kasadan çıkan', 'grad-pink', 'box-arrow-up'], ['Brüt Kâr', money($sales - $cost - $exps), 'ciro − ürün maliyeti − gider', 'grad-teal', 'graph-up-arrow'],
      ['Yeni Müşteri', $newCust, $newPat . ' yeni hasta', 'grad-blue', 'person-plus'], ['Yapılan Aşı', $vaccDone, $vaccPlanned . ' aşı önümüzdeki 30 gün', 'grad-purple', 'eyedropper'],
      ['Muayene / İşlem', $exams, 'kayıtlı işlem', 'grad-teal', 'clipboard2-pulse'], ['Toplam Alacak', money($receivable), 'açık müşteri bakiyesi', 'grad-pink', 'journal-minus'],
  ] as [$l, $v, $s, $g, $ic]): ?>
    <div class="col-6 col-lg-3"><div class="kpi"><div class="ic <?= $g ?>"><i class="bi bi-<?= $ic ?>"></i></div><div><div class="l"><?= $l ?></div><div class="v"><?= $v ?></div><div class="small text-muted"><?= $s ?></div></div></div></div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <div class="col-lg-8"><div class="card h-100"><div class="card-header">Son 12 Ay — Satış / Tahsilat / Gider</div><div class="card-body"><canvas id="cMonth" height="120"></canvas></div></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-header">Hasta Dağılımı (Tür)</div><div class="card-body"><canvas id="cSpecies" height="220"></canvas></div></div></div>
  <div class="col-lg-6"><div class="card h-100"><div class="card-header">En Çok Satan Ürün / Hizmetler</div><table class="table table-sm mb-0"><thead><tr><th>Ürün</th><th class="text-end">Adet</th><th class="text-end">Tutar</th></tr></thead><tbody>
    <?php foreach ($topItems as $t): ?><tr><td><?= e($t['name']) ?></td><td class="text-end"><?= qty($t['q']) ?></td><td class="text-end fw-bold"><?= money($t['t']) ?></td></tr><?php endforeach; ?>
    <?php if (!$topItems): ?><tr><td colspan="3" class="empty">Veri yok.</td></tr><?php endif; ?></tbody></table></div></div>
  <div class="col-lg-6"><div class="card h-100"><div class="card-header">En Çok Alışveriş Yapan Müşteriler</div><table class="table table-sm mb-0"><thead><tr><th>Müşteri</th><th class="text-end">Satış</th><th class="text-end">Tutar</th></tr></thead><tbody>
    <?php foreach ($topCust as $t): ?><tr><td><a href="<?= url('admin/musteri.php?id=' . $t['id']) ?>"><?= e($t['name']) ?></a></td><td class="text-end"><?= (int)$t['n'] ?></td><td class="text-end fw-bold"><?= money($t['t']) ?></td></tr><?php endforeach; ?>
    <?php if (!$topCust): ?><tr><td colspan="3" class="empty">Veri yok.</td></tr><?php endif; ?></tbody></table></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-header">Yapılan Aşılar</div><div class="card-body"><canvas id="cVacc" height="220"></canvas><?php if (!$vaccTypes): ?><div class="empty">Veri yok.</div><?php endif; ?></div></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-header">Randevu Durumları</div><div class="card-body">
    <?php $tot = array_sum($apptStats) ?: 1; foreach (['onayli' => 'Onaylı', 'tamamlandi' => 'Tamamlandı', 'talep' => 'Talep', 'gelmedi' => 'Gelmedi', 'iptal' => 'İptal'] as $k => $l): $n = (int)($apptStats[$k] ?? 0); ?>
      <div class="mb-2"><div class="d-flex justify-content-between small"><span><?= $l ?></span><b><?= $n ?></b></div><div class="progress" style="height:8px"><div class="progress-bar" style="width:<?= round($n / $tot * 100) ?>%"></div></div></div>
    <?php endforeach; ?>
    <?php $done = (int)($apptStats['tamamlandi'] ?? 0); $noShow = (int)($apptStats['gelmedi'] ?? 0); if ($done + $noShow): ?><div class="small text-muted mt-2">Gelmeme oranı: <b>%<?= round($noShow / ($done + $noShow) * 100) ?></b></div><?php endif; ?>
  </div></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-header">Tahsilat Yöntemleri</div><div class="card-body"><canvas id="cPay" height="220"></canvas><?php if (!$payMethods): ?><div class="empty">Veri yok.</div><?php endif; ?></div></div></div>
</div>
<?php
$pageScripts = '<script>
const C=["#0f766e","#f97316","#8b5cf6","#db2777","#0ea5e9","#22c55e","#eab308","#64748b","#14b8a6","#ef4444"];
new Chart(document.getElementById("cMonth"),{type:"bar",data:{labels:' . json_encode($mLabels) . ',datasets:[
 {label:"Satış",data:' . json_encode(array_column(array_values($months), 0)) . ',backgroundColor:"#f97316",borderRadius:6},
 {label:"Tahsilat",data:' . json_encode(array_column(array_values($months), 1)) . ',backgroundColor:"#0f766e",borderRadius:6},
 {type:"line",label:"Gider",data:' . json_encode(array_column(array_values($months), 2)) . ',borderColor:"#db2777",backgroundColor:"#db2777",tension:.3}]},
 options:{plugins:{tooltip:{callbacks:{label:c=>c.dataset.label+": "+ptMoney(c.raw)}}},scales:{y:{ticks:{callback:v=>v.toLocaleString("tr-TR")+" ₺"}}}}});
new Chart(document.getElementById("cSpecies"),{type:"doughnut",data:{labels:' . json_encode(array_column($species, 'name')) . ',datasets:[{data:' . json_encode(array_map('intval', array_column($species, 'n'))) . ',backgroundColor:' . json_encode(array_column($species, 'color')) . '}]},options:{plugins:{legend:{position:"bottom"}}}});
' . ($vaccTypes ? 'new Chart(document.getElementById("cVacc"),{type:"bar",data:{labels:' . json_encode(array_column($vaccTypes, 'name')) . ',datasets:[{data:' . json_encode(array_map('intval', array_column($vaccTypes, 'n'))) . ',backgroundColor:"#db2777",borderRadius:6}]},options:{indexAxis:"y",plugins:{legend:{display:false}}}});' : '') . '
' . ($payMethods ? 'new Chart(document.getElementById("cPay"),{type:"pie",data:{labels:' . json_encode(array_map('pay_method_label', array_column($payMethods, 'method'))) . ',datasets:[{data:' . json_encode(array_map('floatval', array_column($payMethods, 't'))) . ',backgroundColor:C}]},options:{plugins:{legend:{position:"bottom"}}}});' : '') . '
</script>';
include APP . '/views/admin_footer.php';
