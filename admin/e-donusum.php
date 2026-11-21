<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$pageTitle = 'E-Dönüşüm';
$active = 'muhasebe';

if ($xid = (int)get('xml')) {
    $inv = row('SELECT * FROM invoices WHERE id = ?', [$xid]);
    if ($inv) {
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $inv['invoice_no'] . '.xml"');
        echo $inv['xml'];
        exit;
    }
}

if (is_post()) {
    $id = (int)post('id');
    $act = post('islem');
    if ($act === 'gonder') {
        [$ok, $msg] = einvoice_send($id);
        flash($ok ? 'success' : 'warning', e($msg));
    } elseif ($act === 'gonderildi') {
        update('invoices', ['status' => 'gonderildi'], $id);
        flash('success', 'Fatura gönderildi olarak işaretlendi.');
    } elseif ($act === 'iptal') {
        update('invoices', ['status' => 'iptal'], $id);
        flash('success', 'Fatura iptal edildi.');
    } elseif ($act === 'yenile') {
        update('invoices', ['xml' => einvoice_xml($id)], $id);
        flash('success', 'XML yeniden oluşturuldu.');
    } elseif ($act === 'toplu') {
        $n = 0;
        foreach ((array)($_POST['sales'] ?? []) as $sid) {
            try { einvoice_create((int)$sid); $n++; } catch (Throwable $e) {}
        }
        flash('success', "$n fatura taslağı oluşturuldu.");
        redirect(url('admin/e-donusum.php'));
    }
    redirect(url('admin/e-donusum.php' . ($id ? '?id=' . $id : '')));
}

$provider = setting('einv_provider', 'manuel');
if ($id = (int)get('id')) {
    $inv = row('SELECT * FROM invoices WHERE id = ?', [$id]);
    if (!$inv) redirect(url('admin/e-donusum.php'));
    $items = rows('SELECT * FROM sale_items WHERE sale_id = ?', [$inv['sale_id']]);
    $s = row('SELECT * FROM sales WHERE id = ?', [$inv['sale_id']]);
    include APP . '/views/admin_header.php'; ?>
    <div class="page-head no-print"><div><h1><?= e($inv['invoice_no']) ?> <?= status_badge($inv['status']) ?></h1><div class="sub"><?= $inv['invoice_type'] === 'e-fatura' ? 'e-Fatura (Temel)' : 'e-Arşiv Fatura' ?> · ETTN: <?= e($inv['uuid']) ?></div></div>
      <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-light" href="<?= url('admin/e-donusum.php') ?>"><i class="bi bi-arrow-left"></i></a>
        <button onclick="window.print()" class="btn btn-light"><i class="bi bi-printer"></i> Yazdır</button>
        <a class="btn btn-outline-primary" href="?xml=<?= $id ?>"><i class="bi bi-filetype-xml"></i> UBL-TR XML İndir</a>
        <?php if ($inv['status'] !== 'iptal'): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
            <?php if ($inv['status'] !== 'gonderildi'): ?>
              <?php if ($provider === 'entegrator'): ?><button name="islem" value="gonder" class="btn btn-primary"><i class="bi bi-send"></i> Entegratöre Gönder</button>
              <?php else: ?><button name="islem" value="gonderildi" class="btn btn-success"><i class="bi bi-check2"></i> Portala yüklendi (Gönderildi)</button><?php endif; ?>
              <button name="islem" value="yenile" class="btn btn-light" title="XML'i yeniden üret"><i class="bi bi-arrow-repeat"></i></button>
            <?php endif; ?>
            <button name="islem" value="iptal" class="btn btn-outline-danger" data-confirm="Fatura iptal edilsin mi?">İptal</button></form>
        <?php endif; ?>
      </div></div>
    <?php if ($inv['response']): ?><div class="alert alert-light small no-print"><b>Entegratör yanıtı:</b><pre class="mb-0"><?= e($inv['response']) ?></pre></div><?php endif; ?>
    <div class="card" style="max-width:900px;margin:auto"><div class="card-body p-4">
      <div class="d-flex justify-content-between border-bottom pb-3 mb-3">
        <div><h5 class="fw-bold mb-1"><?= e(setting('einv_title', setting('clinic_name'))) ?></h5><div class="small"><?= e(setting('einv_address', setting('clinic_address'))) ?><br>VD: <?= e(setting('einv_tax_office', '-')) ?> · VKN/TCKN: <?= e(setting('einv_vkn', '-')) ?></div></div>
        <div class="text-end"><div class="fs-5 fw-bold"><?= $inv['invoice_type'] === 'e-fatura' ? 'e-FATURA' : 'e-ARŞİV FATURA' ?></div><div class="small">No: <b><?= e($inv['invoice_no']) ?></b><br>Tarih: <?= dtt($inv['issue_date']) ?><br>Senaryo: <?= $inv['invoice_type'] === 'e-fatura' ? 'TEMELFATURA' : 'EARSIVFATURA' ?> · Tip: SATIŞ</div></div>
      </div>
      <div class="mb-3"><div class="small text-muted">SAYIN</div><div class="fw-bold"><?= e($inv['receiver_name']) ?></div><div class="small">VKN/TCKN: <?= e($inv['receiver_vkn']) ?></div></div>
      <table class="table table-sm"><thead><tr><th>#</th><th>Mal / Hizmet</th><th class="text-end">Miktar</th><th class="text-end">Birim Fiyat</th><th class="text-end">KDV</th><th class="text-end">Tutar</th></tr></thead><tbody>
      <?php foreach ($items as $k => $i): ?><tr><td><?= $k + 1 ?></td><td><?= e($i['description']) ?></td><td class="text-end"><?= qty($i['qty']) ?></td><td class="text-end"><?= money($i['unit_price']) ?></td><td class="text-end">%<?= (int)$i['vat_rate'] ?></td><td class="text-end"><?= money($i['total']) ?></td></tr><?php endforeach; ?>
      </tbody></table>
      <div class="ms-auto" style="max-width:320px">
        <?php if ($s['discount'] > 0): ?><div class="d-flex justify-content-between"><span>İskonto</span><span>-<?= money($s['discount']) ?></span></div><?php endif; ?>
        <div class="d-flex justify-content-between"><span>Mal/Hizmet Toplamı (KDV hariç)</span><span><?= money($inv['total'] - $inv['vat_total']) ?></span></div>
        <div class="d-flex justify-content-between"><span>Hesaplanan KDV</span><span><?= money($inv['vat_total']) ?></span></div>
        <div class="d-flex justify-content-between fw-bold fs-5 border-top mt-1 pt-1"><span>Ödenecek Tutar</span><span><?= money($inv['total']) ?></span></div>
      </div>
    </div></div>
    <?php include APP . '/views/admin_footer.php';
    exit;
}

$st = get('durum', '');
$sql = 'SELECT i.*, s.customer_id FROM invoices i LEFT JOIN sales s ON s.id = i.sale_id WHERE 1=1';
$prm = [];
if ($st) { $sql .= ' AND i.status = ?'; $prm[] = $st; }
$pg = paginate($sql . ' ORDER BY i.id DESC', $prm, 30);
$unbilled = rows("SELECT s.*, c.name AS musteri FROM sales s LEFT JOIN customers c ON c.id = s.customer_id WHERE s.status = 'aktif' AND s.sale_date >= ? AND NOT EXISTS (SELECT 1 FROM invoices i WHERE i.sale_id = s.id AND i.status <> 'iptal') ORDER BY s.sale_date DESC LIMIT 50", [date('Y-m-d', strtotime('-7 days'))]);
$cnt = array_column(rows('SELECT status, COUNT(*) n FROM invoices GROUP BY status'), 'n', 'status');

include APP . '/views/admin_header.php'; ?>
<div class="page-head"><div><h1><i class="bi bi-file-earmark-text text-primary"></i> E-Dönüşüm</h1><div class="sub">e-Fatura ve e-Arşiv fatura yönetimi (UBL-TR 1.2)</div></div>
  <a href="<?= url('admin/ayarlar.php?sekme=efatura') ?>" class="btn btn-light"><i class="bi bi-gear"></i> E-Dönüşüm Ayarları</a></div>
<?php if (!setting('einv_vkn')): ?><div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Firma VKN/TCKN, ünvan ve vergi dairesi bilgileri girilmemiş. <a class="alert-link" href="<?= url('admin/ayarlar.php?sekme=efatura') ?>">Ayarlardan tamamlayın</a>.</div><?php endif; ?>
<div class="alert alert-info small"><i class="bi bi-info-circle"></i> Çalışma modu: <b><?= $provider === 'entegrator' ? 'Entegratör API' : 'Manuel (XML indir → entegratör / GİB portalına yükle)' ?></b>.
  e-Fatura mükellefi işaretli müşterilere <b>e-Fatura</b>, diğerlerine <b>e-Arşiv</b> taslağı oluşturulur. Resmi gönderim için bir GİB özel entegratörü ile anlaşma gerekir.</div>
<div class="row g-3 mb-3">
  <?php foreach (['taslak' => ['Taslak', 'grad-orange', 'pencil'], 'gonderildi' => ['Gönderildi', 'grad-green', 'send-check'], 'hata' => ['Hatalı', 'grad-pink', 'x-octagon'], 'iptal' => ['İptal', 'grad-blue', 'slash-circle']] as $k => [$l, $g, $ic]): ?>
    <div class="col-6 col-md-3"><a href="?durum=<?= $k ?>" class="kpi text-reset"><div class="ic <?= $g ?>"><i class="bi bi-<?= $ic ?>"></i></div><div><div class="l"><?= $l ?></div><div class="v"><?= (int)($cnt[$k] ?? 0) ?></div></div></a></div>
  <?php endforeach; ?>
</div>
<div class="row g-3">
  <div class="col-lg-7"><div class="card"><div class="card-header d-flex justify-content-between"><span>Faturalar</span><?php if ($st): ?><a href="?" class="small">Filtreyi kaldır</a><?php endif; ?></div>
    <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Fatura No</th><th>Tür</th><th>Alıcı</th><th>Tarih</th><th class="text-end">Tutar</th><th>Durum</th></tr></thead><tbody>
    <?php foreach ($pg['rows'] as $i): ?><tr onclick="location='?id=<?= $i['id'] ?>'" style="cursor:pointer"><td class="fw-bold"><?= e($i['invoice_no']) ?></td><td><span class="badge text-bg-light"><?= $i['invoice_type'] === 'e-fatura' ? 'e-Fatura' : 'e-Arşiv' ?></span></td><td><?= e($i['receiver_name']) ?></td><td class="small"><?= dt($i['issue_date']) ?></td><td class="text-end"><?= money($i['total']) ?></td><td><?= status_badge($i['status']) ?></td></tr><?php endforeach; ?>
    <?php if (!$pg['rows']): ?><tr><td colspan="6" class="empty">Fatura yok.</td></tr><?php endif; ?>
    </tbody></table></div><div class="card-footer bg-white"><?= pager($pg) ?></div></div></div>
  <div class="col-lg-5"><form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="islem" value="toplu">
    <div class="card-header d-flex justify-content-between align-items-center"><span>Faturalanmamış Satışlar (7 gün)</span><button class="btn btn-sm btn-primary">Seçilileri Faturala</button></div>
    <div class="card-body py-1" style="max-height:480px;overflow:auto">
      <?php foreach ($unbilled as $s): ?><label class="list-row" style="cursor:pointer"><input type="checkbox" class="form-check-input" name="sales[]" value="<?= $s['id'] ?>"><div class="flex-grow-1"><b>#<?= $s['id'] ?></b> <?= e($s['musteri'] ?: 'Peşin') ?><div class="small text-muted"><?= dtt($s['sale_date']) ?></div></div><b><?= money($s['total']) ?></b></label><?php endforeach; ?>
      <?php if (!$unbilled): ?><div class="empty py-3">Faturalanmamış satış yok.</div><?php endif; ?>
    </div></form></div>
</div>
<?php include APP . '/views/admin_footer.php';
