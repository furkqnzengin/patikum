<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$pageTitle = 'Müşteriler';
$active = 'musteri';
$islem = get('islem', '');

if (is_post()) {
    $act = post('islem');
    if ($act === 'kaydet') {
        $id = (int)post('id');
        $data = [
            'name' => post('name'), 'phone' => post('phone'), 'phone2' => nul(post('phone2')), 'email' => nul(post('email')),
            'tc_no' => nul(post('tc_no')), 'company' => nul(post('company')), 'tax_office' => nul(post('tax_office')),
            'is_efatura' => post('is_efatura') ? 1 : 0, 'address' => nul(post('address')), 'city' => nul(post('city')),
            'district' => nul(post('district')), 'notes' => nul(post('notes')), 'wa_optin' => post('wa_optin') ? 1 : 0,
        ];
        if ($data['name'] === '' || $data['phone'] === '') {
            flash('danger', 'Ad soyad ve telefon zorunludur.');
            back();
        }
        if ($id) {
            update('customers', $data, $id);
            flash('success', 'Müşteri güncellendi.');
        } else {
            $dup = val('SELECT id FROM customers WHERE phone = ?', [$data['phone']]);
            if ($dup && !post('force')) {
                flash('warning', 'Bu telefonla kayıtlı bir müşteri zaten var: <a href="' . url('admin/musteri.php?id=' . $dup) . '">kaydı aç</a>');
                back();
            }
            $id = insert('customers', $data);
            flash('success', 'Müşteri eklendi. Şimdi hastasını (hayvanını) ekleyebilirsiniz.');
            if (post('sonra') === 'hasta') redirect(url('admin/hastalar.php?islem=yeni&musteri=' . $id));
        }
        redirect(url('admin/musteri.php?id=' . $id));
    }
    if ($act === 'sil' && is_admin()) {
        $id = (int)post('id');
        update('customers', ['active' => 0], $id);
        flash('success', 'Müşteri arşive taşındı.');
        redirect(url('admin/musteriler.php'));
    }
}

if ($islem === 'yeni' || $islem === 'duzenle') {
    $c = $islem === 'duzenle' ? row('SELECT * FROM customers WHERE id = ?', [(int)get('id')]) : null;
    $c = $c ?: ['id' => 0, 'name' => '', 'phone' => '', 'phone2' => '', 'email' => '', 'tc_no' => '', 'company' => '', 'tax_office' => '', 'is_efatura' => 0, 'address' => '', 'city' => '', 'district' => '', 'notes' => '', 'wa_optin' => 1];
    include APP . '/views/admin_header.php'; ?>
    <div class="page-head"><div><h1><?= $c['id'] ? 'Müşteriyi Düzenle' : 'Yeni Müşteri' ?></h1><div class="sub">Hayvan sahibi bilgileri</div></div>
      <a href="<?= url('admin/musteriler.php') ?>" class="btn btn-light"><i class="bi bi-arrow-left"></i> Listeye dön</a></div>
    <form method="post" class="card"><div class="card-body">
      <?= csrf_field() ?><input type="hidden" name="islem" value="kaydet"><input type="hidden" name="id" value="<?= $c['id'] ?>">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label fw-bold">Ad Soyad *</label><input name="name" class="form-control" required value="<?= e($c['name']) ?>" autofocus></div>
        <div class="col-md-3"><label class="form-label fw-bold">Cep Telefonu (WhatsApp) *</label><input name="phone" class="form-control" required placeholder="05xx xxx xx xx" value="<?= e($c['phone']) ?>"></div>
        <div class="col-md-3"><label class="form-label">2. Telefon</label><input name="phone2" class="form-control" value="<?= e($c['phone2']) ?>"></div>
        <div class="col-md-4"><label class="form-label">E-posta</label><input name="email" type="email" class="form-control" value="<?= e($c['email']) ?>"></div>
        <div class="col-md-4"><label class="form-label">İl</label><input name="city" class="form-control" value="<?= e($c['city']) ?>"></div>
        <div class="col-md-4"><label class="form-label">İlçe</label><input name="district" class="form-control" value="<?= e($c['district']) ?>"></div>
        <div class="col-12"><label class="form-label">Adres</label><textarea name="address" class="form-control" rows="2"><?= e($c['address']) ?></textarea></div>
        <div class="col-12"><h6 class="fw-bold mt-2 mb-0"><i class="bi bi-receipt me-1"></i>Fatura Bilgileri (e-Dönüşüm)</h6></div>
        <div class="col-md-3"><label class="form-label">TC Kimlik / Vergi No</label><input name="tc_no" class="form-control" value="<?= e($c['tc_no']) ?>"></div>
        <div class="col-md-4"><label class="form-label">Firma Ünvanı</label><input name="company" class="form-control" value="<?= e($c['company']) ?>"></div>
        <div class="col-md-3"><label class="form-label">Vergi Dairesi</label><input name="tax_office" class="form-control" value="<?= e($c['tax_office']) ?>"></div>
        <div class="col-md-2 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_efatura" value="1" id="ef" <?= $c['is_efatura'] ? 'checked' : '' ?>><label class="form-check-label" for="ef">e-Fatura mükellefi</label></div></div>
        <div class="col-12"><label class="form-label">Notlar</label><textarea name="notes" class="form-control" rows="2"><?= e($c['notes']) ?></textarea></div>
        <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="wa_optin" value="1" id="wa" <?= $c['wa_optin'] ? 'checked' : '' ?>><label class="form-check-label" for="wa"><i class="bi bi-whatsapp text-success"></i> WhatsApp ile otomatik bilgilendirme mesajları almayı kabul ediyor (KVKK onayı)</label></div></div>
      </div>
    </div><div class="card-footer bg-white d-flex gap-2 justify-content-end">
      <?php if (!$c['id']): ?><button name="sonra" value="hasta" class="btn btn-outline-primary"><i class="bi bi-heart-pulse"></i> Kaydet ve Hasta Ekle</button><?php endif; ?>
      <button class="btn btn-primary px-4"><i class="bi bi-check-lg"></i> Kaydet</button>
    </div></form>
    <?php include APP . '/views/admin_footer.php';
    exit;
}

// Liste
$qs = (string)get('q', '');
$sql = 'SELECT c.*, (SELECT COUNT(*) FROM patients p WHERE p.customer_id = c.id AND p.status = \'aktif\') AS pet_count, ' . balance_sql() . ' AS bakiye FROM customers c WHERE c.active = 1';
$p = [];
if ($qs !== '') {
    $sql .= ' AND (c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ? OR c.id IN (SELECT customer_id FROM patients WHERE name LIKE ?))';
    $p = array_fill(0, 4, "%$qs%");
}
if (get('borclu')) $sql = 'SELECT * FROM (' . $sql . ') t WHERE bakiye > 0';
$sql .= get('borclu') ? ' ORDER BY bakiye DESC' : ' ORDER BY c.id DESC';
$pg = paginate($sql, $p, 25);

include APP . '/views/admin_header.php'; ?>
<div class="page-head">
  <div><h1>Müşteriler</h1><div class="sub"><?= $pg['total'] ?> kayıt</div></div>
  <div class="d-flex gap-2">
    <form class="d-flex gap-2"><input name="q" class="form-control" placeholder="Ad, telefon, hasta adı…" value="<?= e($qs) ?>"><button class="btn btn-light"><i class="bi bi-search"></i></button></form>
    <a href="<?= url('admin/musteriler.php?islem=yeni') ?>" class="btn btn-primary text-nowrap"><i class="bi bi-person-plus"></i> Yeni Müşteri</a>
  </div>
</div>
<div class="card"><div class="table-responsive">
  <table class="table table-hover align-middle">
    <thead><tr><th>Müşteri</th><th>Telefon</th><th>Hasta</th><th>Kayıt</th><th class="text-end">Bakiye</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($pg['rows'] as $c): ?>
      <tr>
        <td><a class="fw-bold" href="<?= url('admin/musteri.php?id=' . $c['id']) ?>"><?= e($c['name']) ?></a><?= $c['company'] ? '<div class="small text-muted">' . e($c['company']) . '</div>' : '' ?></td>
        <td><?= e(phone_fmt($c['phone'])) ?> <?php if ($c['phone']): ?><a href="<?= e(wa_link($c['phone'])) ?>" target="_blank" class="text-success ms-1"><i class="bi bi-whatsapp"></i></a><?php endif; ?></td>
        <td><span class="badge badge-soft"><?= (int)$c['pet_count'] ?> hasta</span></td>
        <td class="small text-muted"><?= dt($c['created_at']) ?></td>
        <td class="text-end fw-bold <?= $c['bakiye'] > 0 ? 'text-danger' : ($c['bakiye'] < 0 ? 'text-success' : '') ?>"><?= money($c['bakiye']) ?></td>
        <td class="text-end text-nowrap">
          <a class="btn btn-sm btn-light" href="<?= url('admin/musteri.php?id=' . $c['id']) ?>"><i class="bi bi-eye"></i></a>
          <a class="btn btn-sm btn-light" href="<?= url('admin/musteriler.php?islem=duzenle&id=' . $c['id']) ?>"><i class="bi bi-pencil"></i></a>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$pg['rows']): ?><tr><td colspan="6" class="empty"><i class="bi bi-people"></i>Kayıt bulunamadı.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div><div class="card-footer bg-white"><?= pager($pg) ?></div></div>
<?php include APP . '/views/admin_footer.php';
