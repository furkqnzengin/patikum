<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$pageTitle = 'Yeni Satış';
$active = 'satis';

if (is_post()) {
    $items = [];
    foreach ((array)($_POST['items'] ?? []) as $i) {
        $items[] = ['product_id' => nul($i['product_id'] ?? null), 'description' => trim((string)($i['description'] ?? '')), 'qty' => num($i['qty'] ?? 1), 'unit_price' => num($i['unit_price'] ?? 0), 'vat_rate' => (int)($i['vat_rate'] ?? 20)];
    }
    $cid = nul(post('customer_id'));
    $paid = num(post('paid'));
    try {
        $id = sale_create([
            'customer_id' => $cid, 'patient_id' => nul(post('patient_id')), 'sale_date' => (post('date') ?: today()) . ' ' . date('H:i:s'),
            'discount' => num(post('discount')), 'note' => nul(post('note')), 'source' => 'satis', 'paid' => 0, 'pay_method' => post('pay_method', 'nakit'),
        ], $items);
        $total = (float)val('SELECT total FROM sales WHERE id = ?', [$id]);
        if (!$cid) $paid = $total; // müşterisiz (peşin) satışta tamamı tahsil edilir
        if ($paid > 0) insert('payments', ['customer_id' => $cid, 'sale_id' => $id, 'amount' => $paid, 'method' => post('pay_method', 'nakit'), 'pay_date' => now(), 'note' => 'Satış #' . $id . ' tahsilatı', 'user_id' => $_SESSION['uid']]);
        if ($cid && post('wa')) wa_queue((int)$cid, null, 'satis_bilgi', ['tarih' => dt(today()), 'tutar' => money($total), 'borc' => money(max(0, customer_balance((int)$cid)))], 'sale:' . $id);
        if (post('fatura')) {
            $inv = einvoice_create($id);
            flash('info', 'e-Arşiv/e-Fatura taslağı oluşturuldu: <a class="alert-link" href="' . url('admin/e-donusum.php?id=' . $inv) . '">görüntüle</a>');
        }
        flash('success', 'Satış kaydedildi: ' . money($total));
        redirect(url('admin/satislar.php?id=' . $id));
    } catch (Throwable $e) {
        flash('danger', e($e->getMessage()));
        back();
    }
}

$cust = get('musteri') ? row('SELECT id, name, phone FROM customers WHERE id = ?', [(int)get('musteri')]) : null;
$quick = rows('SELECT id, name, sell_price, vat_rate, is_service, stock FROM products WHERE active = 1 AND is_service = 1 ORDER BY name LIMIT 12');
include APP . '/views/admin_header.php'; ?>
<div class="page-head"><div><h1><i class="bi bi-cart-plus text-primary"></i> Hızlı Satış</h1><div class="sub">Barkod okutun veya ürün arayın. Fiyatlar KDV dahildir.</div></div>
  <a href="<?= url('admin/satislar.php') ?>" class="btn btn-light"><i class="bi bi-receipt"></i> Satış Listesi</a></div>
<form method="post" id="saleForm">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card mb-3"><div class="card-body row g-2">
        <div class="col-md-6"><label class="form-label fw-bold">Müşteri</label><select name="customer_id" id="sCust" class="js-customer" placeholder="Boş bırakılırsa peşin satış"><?php if ($cust): ?><option value="<?= $cust['id'] ?>" selected><?= e($cust['name']) ?></option><?php endif; ?></select></div>
        <div class="col-md-4"><label class="form-label fw-bold">Hasta</label><select name="patient_id" class="form-select js-patient" data-customer="#sCust" data-selected="<?= (int)get('hasta') ?>"><option value="">—</option></select></div>
        <div class="col-md-2"><label class="form-label fw-bold">Tarih</label><input type="date" name="date" class="form-control" value="<?= today() ?>"></div>
      </div></div>
      <div class="card">
        <div class="card-header"><select id="pSearch"></select></div>
        <div class="table-responsive"><table class="table sale-items mb-0">
          <thead><tr><th>Ürün / Hizmet</th><th style="width:90px">Miktar</th><th style="width:130px">Birim Fiyat</th><th style="width:80px">KDV</th><th class="text-end" style="width:120px">Tutar</th><th></th></tr></thead>
          <tbody id="rows"></tbody>
        </table></div>
        <div class="card-body border-top d-flex flex-wrap gap-2 align-items-center">
          <button type="button" class="btn btn-sm btn-outline-secondary" id="addManual"><i class="bi bi-plus"></i> Serbest kalem</button>
          <span class="small text-muted ms-2">Hızlı hizmetler:</span>
          <?php foreach ($quick as $q): ?><button type="button" class="btn btn-sm btn-light qadd" data-p='<?= e(json_encode(['id' => $q['id'], 'text' => $q['name'], 'price' => (float)$q['sell_price'], 'vat' => (int)$q['vat_rate']])) ?>'><?= e($q['name']) ?></button><?php endforeach; ?>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card" style="position:sticky;top:70px"><div class="card-body">
        <div class="d-flex justify-content-between mb-2"><span>Ara Toplam</span><b id="tSub">0,00 ₺</b></div>
        <div class="d-flex justify-content-between align-items-center mb-2"><span>İndirim (₺)</span><input name="discount" id="disc" class="form-control form-control-sm text-end" style="width:120px" value="0"></div>
        <div class="d-flex justify-content-between mb-3 fs-4"><span class="fw-bold">TOPLAM</span><b id="tTot" class="text-primary">0,00 ₺</b></div>
        <label class="form-label fw-bold">Tahsil Edilen</label>
        <div class="input-group mb-2"><input name="paid" id="paid" class="form-control" value="0"><button type="button" class="btn btn-outline-primary" id="payAll">Tamamı</button></div>
        <div class="btn-group w-100 mb-3" role="group">
          <?php foreach (['nakit' => 'Nakit', 'kart' => 'Kart', 'havale' => 'Havale'] as $k => $l): ?><input type="radio" class="btn-check" name="pay_method" id="pm<?= $k ?>" value="<?= $k ?>" <?= $k === 'nakit' ? 'checked' : '' ?>><label class="btn btn-outline-secondary" for="pm<?= $k ?>"><?= $l ?></label><?php endforeach; ?>
        </div>
        <div class="small mb-2" id="debtInfo"></div>
        <input name="note" class="form-control mb-2" placeholder="Not (ör. Luna - ilaç)">
        <div class="form-check"><input class="form-check-input" type="checkbox" name="wa" value="1" id="wa"><label for="wa" class="form-check-label small"><i class="bi bi-whatsapp text-success"></i> Müşteriye WhatsApp bilgisi gönder</label></div>
        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="fatura" value="1" id="ft"><label for="ft" class="form-check-label small"><i class="bi bi-file-earmark-text"></i> e-Arşiv / e-Fatura taslağı oluştur</label></div>
        <button class="btn btn-primary btn-lg w-100 fw-bold"><i class="bi bi-check2-circle"></i> Satışı Tamamla</button>
      </div></div>
    </div>
  </div>
</form>
<?php
$pageScripts = <<<'JS'
<script>
let n = 0;
const rows = document.getElementById('rows');
function recalc() {
  let sub = 0;
  rows.querySelectorAll('tr').forEach(tr => {
    const q = parseFloat((tr.querySelector('.q').value || '0').replace(',', '.')) || 0;
    const p = parseFloat((tr.querySelector('.p').value || '0').replace(',', '.')) || 0;
    tr.querySelector('.t').textContent = ptMoney(q * p); sub += q * p;
  });
  const d = parseFloat((document.getElementById('disc').value || '0').replace(',', '.')) || 0;
  document.getElementById('tSub').textContent = ptMoney(sub);
  document.getElementById('tTot').textContent = ptMoney(Math.max(0, sub - d));
  window.saleTotal = Math.max(0, sub - d);
  const paid = parseFloat((document.getElementById('paid').value || '0').replace(',', '.')) || 0;
  const cust = document.getElementById('sCust').value;
  document.getElementById('debtInfo').innerHTML = !cust ? '<span class="text-muted">Müşteri seçilmedi: peşin satış olarak tamamı tahsil edilir.</span>' :
    (window.saleTotal - paid > 0 ? '<span class="text-danger fw-bold">' + ptMoney(window.saleTotal - paid) + ' müşterinin cari hesabına borç yazılacak.</span>' : '');
}
function addRow(p) {
  n++;
  const tr = document.createElement('tr');
  tr.innerHTML = `<td><input type="hidden" name="items[${n}][product_id]" value="${p.id || ''}"><input name="items[${n}][description]" class="form-control form-control-sm" value="${(p.text || '').replace(/"/g, '&quot;')}" ${p.id ? 'readonly' : ''} placeholder="Açıklama" required></td>
    <td><input name="items[${n}][qty]" class="form-control form-control-sm q" value="1"></td>
    <td><input name="items[${n}][unit_price]" class="form-control form-control-sm p" value="${p.price || 0}"></td>
    <td><select name="items[${n}][vat_rate]" class="form-select form-select-sm">${[0,1,10,20].map(v => `<option ${v == (p.vat ?? 20) ? 'selected' : ''}>${v}</option>`).join('')}</select></td>
    <td class="text-end fw-bold t"></td><td><button type="button" class="btn btn-sm btn-link text-danger del"><i class="bi bi-x-lg"></i></button></td>`;
  rows.appendChild(tr);
  tr.querySelectorAll('input').forEach(i => i.addEventListener('input', recalc));
  tr.querySelector('.del').onclick = () => { tr.remove(); recalc(); };
  recalc();
}
const ps = ptProductSelect(document.getElementById('pSearch'), o => { addRow(o); setTimeout(() => { ps.clear(true); ps.focus(); }, 10); });
document.getElementById('addManual').onclick = () => addRow({ text: '', price: 0, vat: 20 });
document.querySelectorAll('.qadd').forEach(b => b.onclick = () => addRow(JSON.parse(b.dataset.p)));
document.getElementById('disc').addEventListener('input', recalc);
document.getElementById('paid').addEventListener('input', recalc);
document.getElementById('sCust').addEventListener('change', recalc);
document.getElementById('payAll').onclick = () => { document.getElementById('paid').value = (window.saleTotal || 0).toFixed(2); recalc(); };
document.getElementById('saleForm').addEventListener('submit', e => { if (!rows.children.length) { e.preventDefault(); alert('En az bir kalem ekleyin.'); } });
recalc();
</script>
JS;
include APP . '/views/admin_footer.php';
