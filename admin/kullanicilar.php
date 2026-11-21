<?php
require __DIR__ . '/../app/bootstrap.php';
$me = require_login();
$pageTitle = 'Kullanıcılar';
$active = 'diger';
$profile = get('islem') === 'profil';
if (!$profile && $me['role'] !== 'admin') redirect(url('admin/kullanicilar.php?islem=profil'));
$roles = ['admin' => 'Yönetici', 'veteriner' => 'Veteriner Hekim', 'personel' => 'Personel / Sekreter'];

if (is_post()) {
    $id = (int)post('id');
    if (!is_admin()) $id = (int)$me['id'];
    $data = ['name' => post('name'), 'email' => mb_strtolower((string)post('email')), 'phone' => nul(post('phone'))];
    if (is_admin() && $id !== (int)$me['id']) {
        $data['role'] = array_key_exists(post('role'), $roles) ? post('role') : 'personel';
        $data['active'] = post('active') ? 1 : 0;
    }
    if (post('password') !== '') {
        if (strlen((string)post('password')) < 8) { flash('danger', 'Şifre en az 8 karakter olmalı.'); back(); }
        $data['password'] = password_hash((string)post('password'), PASSWORD_DEFAULT);
    }
    if (val('SELECT id FROM users WHERE email = ? AND id <> ?', [$data['email'], $id])) { flash('danger', 'Bu e-posta başka bir kullanıcıda kayıtlı.'); back(); }
    if ($id) update('users', $data, $id);
    else {
        if (empty($data['password'])) { flash('danger', 'Yeni kullanıcı için şifre girin.'); back(); }
        insert('users', $data + ['active' => 1]);
    }
    flash('success', 'Kullanıcı kaydedildi.');
    redirect(url('admin/kullanicilar.php' . ($profile || !is_admin() ? '?islem=profil' : '')));
}

$users = is_admin() ? rows('SELECT * FROM users ORDER BY active DESC, name') : [];
$edit = $profile ? $me : (get('id') ? row('SELECT * FROM users WHERE id = ?', [(int)get('id')]) : null);
$u = $edit ?: ['id' => 0, 'name' => '', 'email' => '', 'phone' => '', 'role' => 'personel', 'active' => 1];
include APP . '/views/admin_header.php'; ?>
<div class="page-head"><div><h1><?= $profile ? 'Profilim' : 'Kullanıcılar' ?></h1><div class="sub">Personel hesapları ve yetkiler</div></div></div>
<div class="row g-3">
  <?php if (!$profile): ?>
  <div class="col-lg-7"><div class="card"><table class="table table-hover align-middle mb-0"><thead><tr><th>Ad</th><th>E-posta</th><th>Rol</th><th>Son giriş</th><th></th></tr></thead><tbody>
    <?php foreach ($users as $x): ?><tr class="<?= $x['active'] ? '' : 'opacity-50' ?>"><td class="fw-bold"><?= e($x['name']) ?></td><td><?= e($x['email']) ?></td><td><span class="badge badge-soft"><?= e($roles[$x['role']] ?? $x['role']) ?></span></td><td class="small"><?= dtt($x['last_login']) ?></td><td class="text-end"><a class="btn btn-sm btn-light" href="?id=<?= $x['id'] ?>"><i class="bi bi-pencil"></i></a></td></tr><?php endforeach; ?>
  </tbody></table></div></div>
  <?php endif; ?>
  <div class="col-lg-5"><form method="post" class="card"><div class="card-header"><?= $u['id'] ? 'Düzenle: ' . e($u['name']) : 'Yeni Kullanıcı' ?></div><div class="card-body">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $u['id'] ?>">
    <div class="mb-2"><label class="form-label">Ad Soyad</label><input name="name" class="form-control" required value="<?= e($u['name']) ?>"></div>
    <div class="mb-2"><label class="form-label">E-posta (giriş)</label><input type="email" name="email" class="form-control" required value="<?= e($u['email']) ?>"></div>
    <div class="mb-2"><label class="form-label">Telefon</label><input name="phone" class="form-control" value="<?= e($u['phone']) ?>"></div>
    <?php if (!$profile && is_admin() && (int)$u['id'] !== (int)$me['id']): ?>
      <div class="mb-2"><label class="form-label">Rol</label><select name="role" class="form-select"><?= options($roles, $u['role']) ?></select><div class="form-text">Yönetici dışındaki roller ayarlara erişemez ve kayıt silemez.</div></div>
      <div class="form-check form-switch mb-2"><input type="checkbox" class="form-check-input" name="active" value="1" id="ac" <?= $u['active'] ? 'checked' : '' ?>><label for="ac" class="form-check-label">Aktif</label></div>
    <?php endif; ?>
    <div class="mb-2"><label class="form-label">Şifre <?= $u['id'] ? '(değiştirmek için doldurun)' : '' ?></label><input type="password" name="password" class="form-control" autocomplete="new-password" minlength="8"></div>
  </div><div class="card-footer bg-white d-flex justify-content-between"><?php if ($u['id'] && !$profile): ?><a href="?" class="btn btn-light">Yeni ekle</a><?php else: ?><span></span><?php endif; ?><button class="btn btn-primary px-4">Kaydet</button></div></form></div>
</div>
<?php include APP . '/views/admin_footer.php';
