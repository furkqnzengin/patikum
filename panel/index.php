<?php
require __DIR__ . '/../app/bootstrap.php';
$c = require_customer();
$cid = (int)$c['id'];

if (is_post()) {
    if (post('islem') === 'sifre') {
        if (!password_verify((string)post('old'), (string)$c['panel_password'])) flash('danger', 'Mevcut şifre hatalı.');
        elseif (strlen((string)post('new')) < 6) flash('danger', 'Yeni şifre en az 6 karakter olmalı.');
        else { update('customers', ['panel_password' => password_hash((string)post('new'), PASSWORD_DEFAULT)], $cid); flash('success', 'Şifreniz değiştirildi.'); }
    }
    if (post('islem') === 'randevu') {
        $pid = (int)post('patient_id');
        if ($pid && !val('SELECT id FROM patients WHERE id = ? AND customer_id = ?', [$pid, $cid])) $pid = 0;
        $date = max((string)post('date'), today());
        $time = preg_match('/^\d{2}:\d{2}$/', (string)post('time')) ? post('time') : '10:00';
        $open = (int)val("SELECT COUNT(*) FROM appointments WHERE customer_id = ? AND status = 'talep'", [$cid]);
        if ($open >= 3) flash('warning', 'Onay bekleyen talepleriniz var. Lütfen onaylanmasını bekleyin.');
        else {
            insert('appointments', ['customer_id' => $cid, 'patient_id' => $pid ?: null, 'start_at' => "$date $time:00", 'type' => in_array(post('type'), appointment_types(), true) ? post('type') : 'Muayene', 'status' => 'talep', 'notes' => mb_substr((string)post('note'), 0, 1000), 'source' => 'panel']);
            flash('success', 'Randevu talebiniz alındı. Klinik onayladığında WhatsApp ile bilgilendirileceksiniz.');
        }
    }
    redirect(url('panel/'));
}

$pageTitle = 'Müşteri Paneli';
$pets = rows("SELECT p.*, s.name AS tur, s.icon FROM patients p LEFT JOIN species s ON s.id = p.species_id WHERE p.customer_id = ? AND p.status = 'aktif' ORDER BY p.name", [$cid]);
$balance = customer_balance($cid);
$totalSales = (float)val("SELECT COALESCE(SUM(total),0) FROM sales WHERE customer_id = ? AND status = 'aktif'", [$cid]);
$totalPaid = (float)val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE customer_id = ?', [$cid]);
$vaccs = rows("SELECT v.*, t.name AS asi, p.name AS hasta, s.icon FROM vaccinations v JOIN vaccine_types t ON t.id = v.vaccine_type_id JOIN patients p ON p.id = v.patient_id LEFT JOIN species s ON s.id = p.species_id WHERE p.customer_id = ? AND p.status = 'aktif' AND v.status = 'planlandi' ORDER BY v.due_date LIMIT 8", [$cid]);
$appts = rows("SELECT a.*, p.name AS hasta FROM appointments a LEFT JOIN patients p ON p.id = a.patient_id WHERE a.customer_id = ? AND a.start_at >= ? AND a.status IN ('talep','onayli') ORDER BY a.start_at", [$cid, today()]);
// Hesap hareketleri
$ledger = [];
foreach (rows("SELECT s.id, s.sale_date d, s.total, s.note, p.name AS hasta FROM sales s LEFT JOIN patients p ON p.id = s.patient_id WHERE s.customer_id = ? AND s.status = 'aktif'", [$cid]) as $s) {
    $items = rows('SELECT description, qty, total FROM sale_items WHERE sale_id = ?', [$s['id']]);
    $ledger[] = ['d' => $s['d'], 't' => 'islem', 'title' => $s['note'] ?: 'İşlem / Satış', 'hasta' => $s['hasta'], 'items' => $items, 'borc' => (float)$s['total'], 'odeme' => 0];
}
foreach (rows('SELECT pay_date d, amount, method FROM payments WHERE customer_id = ?', [$cid]) as $p) {
    $ledger[] = ['d' => $p['d'], 't' => 'odeme', 'title' => 'Ödeme (' . pay_method_label($p['method']) . ')', 'hasta' => null, 'items' => [], 'borc' => 0, 'odeme' => (float)$p['amount']];
}
usort($ledger, fn($a, $b) => strcmp($a['d'], $b['d']));
$run = 0;
foreach ($ledger as &$l) { $run += $l['borc'] - $l['odeme']; $l['bakiye'] = $run; }
unset($l);
$ledger = array_reverse($ledger);

include APP . '/views/panel_header.php'; ?>
<div class="row g-3">
  <div class="col-md-4"><div class="balance-card" style="background:linear-gradient(135deg,<?= $balance > 0 ? '#f43f5e,#be123c' : '#22c55e,#15803d' ?>)">
    <div class="small fw-bold opacity-75"><?= $balance > 0 ? 'KALAN BORCUNUZ' : ($balance < 0 ? 'ALACAK (AVANS) BAKİYENİZ' : 'BAKİYENİZ') ?></div><div class="display-6 fw-bold"><?= money(abs($balance)) ?></div>
    <div class="small opacity-75"><?= $balance > 0 ? 'Bir sonraki ziyaretinizde ödeyebilirsiniz.' : 'Borcunuz bulunmamaktadır. Teşekkürler!' ?></div></div></div>
  <div class="col-6 col-md-4"><div class="pcard pcard-b h-100"><div class="small text-muted fw-bold">TOPLAM İŞLEM TUTARI</div><div class="fs-3 fw-bold"><?= money($totalSales) ?></div></div></div>
  <div class="col-6 col-md-4"><div class="pcard pcard-b h-100"><div class="small text-muted fw-bold">TOPLAM ÖDEMENİZ</div><div class="fs-3 fw-bold text-success"><?= money($totalPaid) ?></div></div></div>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-8">
    <div class="pcard mb-3"><div class="pcard-h"><span>🐾 Dostlarım</span></div><div class="pcard-b"><div class="row g-3">
      <?php foreach ($pets as $p): $next = val("SELECT MIN(due_date) FROM vaccinations WHERE patient_id = ? AND status = 'planlandi'", [$p['id']]); ?>
        <div class="col-md-6"><a class="pet-tile" href="<?= url('panel/hayvan.php?id=' . $p['id']) ?>"><div class="av"><?= species_icon($p['icon']) ?></div>
          <div class="flex-grow-1"><div class="fw-bold fs-5"><?= e($p['name']) ?></div><div class="small text-muted"><?= e($p['tur']) ?><?= $p['breed'] ? ' · ' . e($p['breed']) : '' ?> · <?= age_text($p['birth_date']) ?></div>
            <?php if ($next): ?><div class="small mt-1 <?= $next < today() ? 'text-danger fw-bold' : 'text-primary' ?>"><i class="bi bi-eyedropper"></i> Sonraki aşı: <?= dt($next) ?></div><?php endif; ?></div>
          <i class="bi bi-chevron-right text-muted"></i></a></div>
      <?php endforeach; ?>
      <?php if (!$pets): ?><div class="text-muted">Kayıtlı dostunuz bulunmuyor.</div><?php endif; ?>
    </div></div></div>

    <div class="pcard"><div class="pcard-h"><span><i class="bi bi-receipt text-primary"></i> Hesap Hareketlerim</span><span class="small text-muted">Yapılan işlemler ve ödemeler</span></div><div class="pcard-b">
      <?php foreach ($ledger as $l): ?>
        <div class="mini-row align-items-start">
          <div class="date-chip" style="<?= $l['t'] === 'odeme' ? 'background:#dcfce7;color:#15803d' : '' ?>"><b><?= date('d', strtotime($l['d'])) ?></b><small><?= mb_substr(tr_month((int)date('n', strtotime($l['d']))), 0, 3) ?> <?= date('y', strtotime($l['d'])) ?></small></div>
          <div class="flex-grow-1">
            <div class="fw-bold"><?= e($l['title']) ?><?= $l['hasta'] ? ' <span class="badge rounded-pill" style="background:#ffedd5;color:#c2410c">' . e($l['hasta']) . '</span>' : '' ?></div>
            <?php foreach ($l['items'] as $i): ?><div class="small text-muted">• <?= e($i['description']) ?><?= (float)$i['qty'] != 1 ? ' x' . qty($i['qty']) : '' ?> — <?= money($i['total']) ?></div><?php endforeach; ?>
          </div>
          <div class="text-end"><div class="fw-bold <?= $l['t'] === 'odeme' ? 'text-success' : '' ?>"><?= $l['t'] === 'odeme' ? '−' . money($l['odeme']) : money($l['borc']) ?></div><div class="small text-muted">Bakiye: <?= money($l['bakiye']) ?></div></div>
        </div>
      <?php endforeach; ?>
      <?php if (!$ledger): ?><div class="text-muted">Henüz işlem yok.</div><?php endif; ?>
    </div></div>
  </div>

  <div class="col-lg-4">
    <div class="pcard mb-3"><div class="pcard-h"><span><i class="bi bi-eyedropper text-danger"></i> Yaklaşan Aşılar</span></div><div class="pcard-b">
      <?php foreach ($vaccs as $v): $late = $v['due_date'] < today(); ?>
        <div class="mini-row"><div class="date-chip <?= $late ? 'late' : '' ?>"><b><?= date('d', strtotime($v['due_date'])) ?></b><small><?= mb_substr(tr_month((int)date('n', strtotime($v['due_date']))), 0, 3) ?></small></div>
          <div><div class="fw-bold"><?= e($v['asi']) ?></div><div class="small text-muted"><?= species_icon($v['icon']) ?> <?= e($v['hasta']) ?><?= $late ? ' · <span class="text-danger fw-bold">Gecikti</span>' : '' ?></div></div></div>
      <?php endforeach; ?>
      <?php if (!$vaccs): ?><div class="text-muted small">Planlanmış aşı yok.</div><?php endif; ?>
    </div></div>

    <div class="pcard mb-3"><div class="pcard-h"><span><i class="bi bi-calendar-heart text-primary"></i> Randevularım</span></div><div class="pcard-b">
      <?php foreach ($appts as $a): ?>
        <div class="mini-row"><div class="date-chip"><b><?= date('d', strtotime($a['start_at'])) ?></b><small><?= mb_substr(tr_month((int)date('n', strtotime($a['start_at']))), 0, 3) ?></small></div>
          <div class="flex-grow-1"><div class="fw-bold"><?= tm($a['start_at']) ?> · <?= e($a['type']) ?></div><div class="small text-muted"><?= e($a['hasta'] ?: '') ?></div></div><?= status_badge($a['status']) ?></div>
      <?php endforeach; ?>
      <?php if (!$appts): ?><div class="text-muted small">Yaklaşan randevunuz yok.</div><?php endif; ?>
    </div></div>

    <?php if (setting('web_appointments', '1') === '1'): ?>
    <form method="post" class="pcard"><div class="pcard-h"><span><i class="bi bi-calendar-plus text-success"></i> Randevu Talebi</span></div><div class="pcard-b">
      <?= csrf_field() ?><input type="hidden" name="islem" value="randevu">
      <select name="patient_id" class="form-select mb-2"><?php foreach ($pets as $p): ?><option value="<?= $p['id'] ?>"><?= species_icon($p['icon']) ?> <?= e($p['name']) ?></option><?php endforeach; ?></select>
      <select name="type" class="form-select mb-2"><?php foreach (appointment_types() as $t): ?><option><?= e($t) ?></option><?php endforeach; ?></select>
      <div class="row g-2 mb-2"><div class="col-7"><input type="date" name="date" class="form-control" min="<?= today() ?>" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" required></div>
        <div class="col-5"><select name="time" class="form-select"><?php for ($h = 9; $h <= 19; $h++) foreach (['00', '30'] as $m): ?><option><?= sprintf('%02d:%s', $h, $m) ?></option><?php endforeach; ?></select></div></div>
      <textarea name="note" class="form-control mb-2" rows="2" placeholder="Notunuz (isteğe bağlı)"></textarea>
      <button class="btn btn-teal w-100">Talep Gönder</button>
    </div></form>
    <?php endif; ?>
  </div>
</div>
<?php include APP . '/views/panel_footer.php';
