<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$pageTitle = 'Otomatik Aşı Sihirbazı';
$active = 'asi';

$pid = (int)get('hasta', post('patient_id'));
$p = $pid ? row('SELECT p.*, s.name AS tur, s.icon, c.name AS sahip, c.id AS cid, c.phone FROM patients p LEFT JOIN species s ON s.id = p.species_id JOIN customers c ON c.id = p.customer_id WHERE p.id = ?', [$pid]) : null;

if (is_post() && $p) {
    $added = [];
    foreach ((array)($_POST['plan'] ?? []) as $item) {
        if (empty($item['sec']) || empty($item['vaccine_type_id']) || empty($item['due_date'])) continue;
        insert('vaccinations', ['patient_id' => $pid, 'vaccine_type_id' => (int)$item['vaccine_type_id'], 'dose_label' => mb_substr((string)($item['dose_label'] ?? ''), 0, 30), 'due_date' => $item['due_date'], 'status' => 'planlandi']);
        $added[] = '• ' . dt($item['due_date']) . ' — ' . val('SELECT name FROM vaccine_types WHERE id = ?', [(int)$item['vaccine_type_id']]) . ' (' . ($item['dose_label'] ?? '') . ')';
    }
    if ($added && post('wa')) {
        $text = "Merhaba {musteri} 🐾\n{$p['name']} için aşı takvimi oluşturuldu:\n" . implode("\n", $added) . "\n\nHer aşıdan önce size hatırlatma göndereceğiz. Takvimi müşteri panelinizden de izleyebilirsiniz: {panel}\n{klinik}";
        $mid = wa_queue((int)$p['cid'], null, $text, [], null, true);
        if ($mid && setting('wa_driver', 'manuel') === 'manuel') {
            flash('info', 'Takvim mesajı hazır: <a class="alert-link" target="_blank" href="' . e(wa_link($p['phone'], (string)val('SELECT body FROM wa_messages WHERE id = ?', [$mid]))) . '">WhatsApp\'ta gönder</a>');
        }
    }
    flash('success', count($added) . ' aşı takvime eklendi.');
    redirect(url('admin/hasta.php?id=' . $pid . '#asilar'));
}

$start = get('baslangic', today());
$plan = $p ? vaccine_plan($p, $start) : [];
$existing = $p ? rows('SELECT v.*, t.name AS asi FROM vaccinations v JOIN vaccine_types t ON t.id = v.vaccine_type_id WHERE v.patient_id = ? AND v.status <> \'iptal\' ORDER BY v.due_date', [$pid]) : [];
$allTypes = $p ? rows('SELECT * FROM vaccine_types WHERE active = 1 AND (species_id = ? OR species_id IS NULL) ORDER BY name', [$p['species_id']]) : [];

include APP . '/views/admin_header.php'; ?>
<div class="page-head"><div><h1><i class="bi bi-magic text-primary"></i> Otomatik Aşı Sihirbazı</h1>
  <div class="sub">Hastanın türü ve yaşına göre aşı protokolünden otomatik takvim oluşturur. Tarihleri dilediğiniz gibi düzenleyebilirsiniz.</div></div>
  <a href="<?= url('admin/asi-tanimlari.php') ?>" class="btn btn-light"><i class="bi bi-sliders"></i> Protokolleri Düzenle</a></div>

<div class="card mb-3"><div class="card-body">
  <form class="row g-2 align-items-end" method="get">
    <div class="col-md-5"><label class="form-label fw-bold">1. Hayvan sahibi</label>
      <select id="wCust" class="js-customer"><?php if ($p): ?><option value="<?= $p['cid'] ?>" selected><?= e($p['sahip']) ?></option><?php endif; ?></select></div>
    <div class="col-md-4"><label class="form-label fw-bold">2. Hasta</label>
      <select name="hasta" class="form-select js-patient" data-customer="#wCust" data-selected="<?= $pid ?>" required><option value="">— Önce müşteri seçin —</option></select></div>
    <div class="col-md-2"><label class="form-label fw-bold">Başlangıç</label><input type="date" name="baslangic" class="form-control" value="<?= e($start) ?>"></div>
    <div class="col-md-1"><button class="btn btn-primary w-100"><i class="bi bi-arrow-right"></i></button></div>
  </form>
</div></div>

<?php if ($p): ?>
<div class="row g-3">
  <div class="col-lg-8">
    <form method="post" class="card">
      <?= csrf_field() ?><input type="hidden" name="patient_id" value="<?= $pid ?>">
      <div class="card-header d-flex align-items-center gap-2"><span class="pet-avatar"><?= species_icon($p['icon']) ?></span>
        <div><div><?= e($p['name']) ?> <span class="text-muted fw-normal">· <?= e($p['tur'] ?: 'Tür yok') ?> · <?= age_text($p['birth_date']) ?></span></div>
        <?php if (!$p['birth_date']): ?><div class="small text-warning fw-normal"><i class="bi bi-exclamation-triangle"></i> Doğum tarihi girilmemiş — takvim başlangıç tarihinden hesaplandı.</div><?php endif; ?></div></div>
      <div class="table-responsive"><table class="table align-middle" id="planTable">
        <thead><tr><th style="width:40px"><input type="checkbox" class="form-check-input" checked onclick="document.querySelectorAll('.sec').forEach(c=>c.checked=this.checked)"></th><th>Aşı</th><th>Doz</th><th>Tarih</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($plan as $i => $v): ?>
          <tr>
            <td><input type="checkbox" class="form-check-input sec" name="plan[<?= $i ?>][sec]" value="1" <?= $v['checked'] ? 'checked' : '' ?>></td>
            <td class="fw-bold"><?= e($v['name']) ?><input type="hidden" name="plan[<?= $i ?>][vaccine_type_id]" value="<?= $v['vaccine_type_id'] ?>"><?= $v['note'] ? '<div class="small text-muted fw-normal">' . e($v['note']) . '</div>' : '' ?></td>
            <td><input name="plan[<?= $i ?>][dose_label]" class="form-control form-control-sm" value="<?= e($v['dose_label']) ?>" style="width:110px"></td>
            <td><input type="date" name="plan[<?= $i ?>][due_date]" class="form-control form-control-sm" value="<?= e($v['due_date']) ?>"></td>
            <td class="small text-muted"><?= dt_long($v['due_date']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$plan): ?><tr><td colspan="5" class="empty"><i class="bi bi-info-circle"></i>Bu tür için tanımlı aşı protokolü yok. <a href="<?= url('admin/asi-tanimlari.php') ?>">Protokol ekleyin</a> veya aşağıdan manuel ekleyin.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
      <div class="card-body border-top">
        <div class="d-flex gap-2 align-items-end flex-wrap">
          <div><label class="form-label small">Manuel aşı ekle</label><select id="mType" class="form-select form-select-sm"><?= options($allTypes, '', 'id', 'name') ?></select></div>
          <div><input type="date" id="mDate" class="form-control form-control-sm" value="<?= today() ?>"></div>
          <button type="button" class="btn btn-sm btn-outline-primary" id="mAdd"><i class="bi bi-plus"></i> Satır ekle</button>
        </div>
      </div>
      <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="wa" value="1" id="waS" checked><label for="waS" class="form-check-label"><i class="bi bi-whatsapp text-success"></i> Takvimi müşteriye WhatsApp ile gönder</label></div>
        <button class="btn btn-primary px-4"><i class="bi bi-calendar-check"></i> Seçilenleri Takvime Ekle</button>
      </div>
    </form>
  </div>
  <div class="col-lg-4">
    <div class="card"><div class="card-header"><i class="bi bi-journal-medical text-danger me-1"></i> Mevcut Aşı Kayıtları</div><div class="card-body py-1">
      <?php foreach ($existing as $v): ?><div class="list-row"><div class="flex-grow-1"><b><?= e($v['asi']) ?></b><div class="small text-muted"><?= e($v['dose_label']) ?></div></div><div class="text-end small"><?= dt($v['done_date'] ?: $v['due_date']) ?><br><?= status_badge($v['status']) ?></div></div><?php endforeach; ?>
      <?php if (!$existing): ?><div class="empty py-3">Kayıt yok.</div><?php endif; ?>
    </div></div>
  </div>
</div>
<?php endif; ?>
<?php
$pageScripts = '<script>
const mAdd=document.getElementById("mAdd");if(mAdd){let n=1000;mAdd.onclick=()=>{const t=document.getElementById("mType"),d=document.getElementById("mDate");if(!t.value)return;n++;
const tr=document.createElement("tr");tr.innerHTML=`<td><input type="checkbox" class="form-check-input sec" name="plan[${n}][sec]" value="1" checked></td><td class="fw-bold">${t.options[t.selectedIndex].text}<input type="hidden" name="plan[${n}][vaccine_type_id]" value="${t.value}"></td><td><input name="plan[${n}][dose_label]" class="form-control form-control-sm" value="Tek Doz" style="width:110px"></td><td><input type="date" name="plan[${n}][due_date]" class="form-control form-control-sm" value="${d.value}"></td><td></td>`;
document.querySelector("#planTable tbody").appendChild(tr);};}
</script>';
include APP . '/views/admin_footer.php';
