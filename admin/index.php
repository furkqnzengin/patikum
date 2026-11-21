<?php
require __DIR__ . '/../app/bootstrap.php';
$u = require_login();
$pageTitle = 'Genel Bakış';
$active = 'genel';

$today = today();
$from30 = date('Y-m-d', strtotime('-29 days'));
function series(string $sql, array $p, string $from): array
{
    $map = [];
    foreach (rows($sql, $p) as $r) $map[$r['d']] = (float)$r['v'];
    $out = [];
    for ($i = 0; $i < 30; $i++) {
        $d = date('Y-m-d', strtotime("$from +$i days"));
        $out[] = $map[$d] ?? 0;
    }
    return $out;
}
$custCount = (int)val('SELECT COUNT(*) FROM customers WHERE active = 1');
$patCount = (int)val("SELECT COUNT(*) FROM patients WHERE status = 'aktif'");
$monthSales = (float)val("SELECT COALESCE(SUM(total),0) FROM sales WHERE status = 'aktif' AND sale_date >= ?", [date('Y-m-01')]);
$custSeries = series('SELECT SUBSTR(created_at,1,10) d, COUNT(*) v FROM customers WHERE created_at >= ? GROUP BY SUBSTR(created_at,1,10)', [$from30], $from30);
$patSeries = series('SELECT SUBSTR(created_at,1,10) d, COUNT(*) v FROM patients WHERE created_at >= ? GROUP BY SUBSTR(created_at,1,10)', [$from30], $from30);
$saleSeries = series("SELECT SUBSTR(sale_date,1,10) d, SUM(total) v FROM sales WHERE status = 'aktif' AND sale_date >= ? GROUP BY SUBSTR(sale_date,1,10)", [$from30], $from30);

$vSql = "SELECT v.*, t.name AS asi, p.name AS hasta, p.id AS pid, s.icon, c.name AS sahip, c.id AS cid, c.phone
         FROM vaccinations v JOIN vaccine_types t ON t.id = v.vaccine_type_id JOIN patients p ON p.id = v.patient_id
         JOIN customers c ON c.id = p.customer_id LEFT JOIN species s ON s.id = p.species_id
         WHERE v.status = 'planlandi' AND p.status = 'aktif' AND ";
$vToday = rows($vSql . 'v.due_date = ? ORDER BY p.name', [$today]);
$vSoon = rows($vSql . 'v.due_date > ? AND v.due_date <= ? ORDER BY v.due_date LIMIT 30', [$today, date('Y-m-d', strtotime('+7 days'))]);
$vLate = rows($vSql . 'v.due_date < ? ORDER BY v.due_date DESC LIMIT 30', [$today]);

$aSql = "SELECT a.*, p.name AS hasta, s.icon, c.name AS sahip, c.phone FROM appointments a LEFT JOIN patients p ON p.id = a.patient_id
         LEFT JOIN species s ON s.id = p.species_id LEFT JOIN customers c ON c.id = a.customer_id WHERE ";
$aToday = rows($aSql . "a.start_at >= ? AND a.start_at < ? AND a.status IN ('onayli','talep','tamamlandi') ORDER BY a.start_at", [$today, date('Y-m-d', strtotime('+1 day'))]);
$aSoon = rows($aSql . "a.start_at >= ? AND a.status IN ('onayli','talep') ORDER BY a.start_at LIMIT 30", [date('Y-m-d', strtotime('+1 day'))]);
$aLate = rows($aSql . "a.start_at < ? AND a.status IN ('onayli','talep') ORDER BY a.start_at DESC LIMIT 30", [date('Y-m-d H:i:s', strtotime('-1 hour'))]);

$lastPays = rows('SELECT p.*, c.name AS musteri FROM payments p LEFT JOIN customers c ON c.id = p.customer_id ORDER BY p.pay_date DESC, p.id DESC LIMIT 8');
$lastSales = rows("SELECT s.*, c.name AS musteri FROM sales s LEFT JOIN customers c ON c.id = s.customer_id WHERE s.status = 'aktif' ORDER BY s.sale_date DESC, s.id DESC LIMIT 8");
$critical = rows('SELECT * FROM products WHERE active = 1 AND is_service = 0 AND stock <= critical_stock ORDER BY stock LIMIT 8');
$todayIn = (float)val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE pay_date >= ? AND pay_date < ?', [$today, date('Y-m-d', strtotime('+1 day'))]);
$receivable = (float)val('SELECT COALESCE(SUM(b),0) FROM (SELECT ' . balance_sql() . ' AS b FROM customers c) t WHERE b > 0');

function vacc_rows(array $list, string $emptyText): void
{
    if (!$list) {
        echo '<div class="empty"><i class="bi bi-emoji-smile"></i>' . e($emptyText) . '</div>';
        return;
    }
    foreach ($list as $v) {
        $late = $v['due_date'] < today();
        $msg = wa_render((wa_template('asi_hatirlatma')['body'] ?? ''), ['musteri' => $v['sahip'], 'hasta' => $v['hasta'], 'asi' => $v['asi'], 'tarih' => dt($v['due_date']), 'zaman' => dt($v['due_date'])]);
        ?>
        <div class="list-row">
          <div class="pet-avatar"><?= species_icon($v['icon']) ?></div>
          <div class="flex-grow-1" style="min-width:0">
            <a href="<?= url('admin/hasta.php?id=' . $v['pid']) ?>" class="fw-bold"><?= e($v['hasta']) ?></a>
            <div class="small text-muted text-truncate"><?= e($v['sahip']) ?></div>
          </div>
          <div class="flex-grow-1" style="min-width:0">
            <div class="fw-bold small text-uppercase" style="color:#db2777"><?= e($v['asi']) ?> <span class="text-muted fw-normal">· <?= e($v['dose_label']) ?></span></div>
            <div class="small <?= $late ? 'text-danger fw-bold' : '' ?>"><?= dt_long($v['due_date']) ?></div>
          </div>
          <div class="d-flex gap-1">
            <form method="post" action="<?= url('admin/asilar.php') ?>"><?= csrf_field() ?><input type="hidden" name="islem" value="yapildi"><input type="hidden" name="id" value="<?= $v['id'] ?>">
              <button class="btn btn-sm btn-outline-success" title="Yapıldı olarak işaretle"><i class="bi bi-check-lg"></i></button></form>
            <div class="dropdown">
              <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">İşlemler</button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= url('admin/asilar.php?islem=duzenle&id=' . $v['id']) ?>"><i class="bi bi-pencil me-2"></i>Detaylı işle / düzenle</a></li>
                <li><a class="dropdown-item" target="_blank" href="<?= e(wa_link($v['phone'], $msg)) ?>"><i class="bi bi-whatsapp me-2 text-success"></i>WhatsApp ile hatırlat</a></li>
                <li><a class="dropdown-item" href="<?= url('admin/hasta.php?id=' . $v['pid']) ?>"><i class="bi bi-heart-pulse me-2"></i>Hasta kartı</a></li>
                <li><form method="post" action="<?= url('admin/asilar.php') ?>"><?= csrf_field() ?><input type="hidden" name="islem" value="ertele"><input type="hidden" name="id" value="<?= $v['id'] ?>"><button class="dropdown-item"><i class="bi bi-clock-history me-2"></i>1 hafta ertele</button></form></li>
              </ul>
            </div>
          </div>
        </div>
        <?php
    }
}

function appt_rows(array $list, string $emptyText): void
{
    if (!$list) {
        echo '<div class="empty"><a href="' . url('admin/randevular.php?islem=yeni') . '" class="btn btn-primary rounded-circle mb-2" style="width:46px;height:46px"><i class="bi bi-plus-lg"></i></a><br>' . e($emptyText) . '</div>';
        return;
    }
    foreach ($list as $a) {
        $name = $a['sahip'] ?: $a['guest_name'];
        ?>
        <div class="list-row">
          <div class="text-center" style="width:58px">
            <div class="fw-bold" style="font-size:1.05rem"><?= tm($a['start_at']) ?></div>
            <div class="small text-muted"><?= date('d.m', strtotime($a['start_at'])) ?></div>
          </div>
          <div class="pet-avatar" style="background:#e0f2fe"><?= species_icon($a['icon'] ?? null) ?></div>
          <div class="flex-grow-1" style="min-width:0">
            <div class="fw-bold"><?= e($a['hasta'] ?: ($a['pet_info'] ?: '—')) ?> <span class="badge badge-soft"><?= e($a['type']) ?></span></div>
            <div class="small text-muted text-truncate"><?= e($name) ?></div>
          </div>
          <?= status_badge($a['status']) ?>
          <a class="btn btn-sm btn-outline-secondary" href="<?= url('admin/randevular.php?islem=duzenle&id=' . $a['id']) ?>"><i class="bi bi-pencil"></i></a>
        </div>
        <?php
    }
}

include APP . '/views/admin_header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 my-3">
  <div class="welcome">
    <div class="logo-circle">🐾</div>
    <div>
      <p>Merhaba <?= e(explode(' ', $u['name'])[0]) ?>, iyi çalışmalar!</p>
      <h1><?= e(mb_strtoupper(setting('clinic_name', 'Patikum Veteriner Kliniği'), 'UTF-8')) ?></h1>
    </div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <span class="kpi py-2"><span class="ic grad-green" style="width:38px;height:38px;font-size:1.1rem"><i class="bi bi-cash-stack"></i></span><span><span class="l d-block">Bugünkü tahsilat</span><span class="v" style="font-size:1.05rem"><?= money($todayIn) ?></span></span></span>
    <a href="<?= url('admin/muhasebe.php?sekme=cari') ?>" class="kpi py-2 text-reset"><span class="ic grad-pink" style="width:38px;height:38px;font-size:1.1rem"><i class="bi bi-journal-minus"></i></span><span><span class="l d-block">Toplam alacak</span><span class="v" style="font-size:1.05rem"><?= money($receivable) ?></span></span></a>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-4 col-xl-3">
    <a href="<?= url('admin/musteriler.php') ?>" class="stat-card grad-teal d-block">
      <div class="head">Müşteriler <i class="bi bi-people fs-5"></i></div>
      <div class="value"><?= number_format($custCount, 0, ',', '.') ?></div>
      <div class="label">Kayıtlı müşteri · son 30 gün +<?= array_sum($custSeries) ?></div>
      <canvas id="spCust"></canvas>
    </a>
  </div>
  <div class="col-md-4 col-xl-3">
    <a href="<?= url('admin/hastalar.php') ?>" class="stat-card grad-purple d-block">
      <div class="head">Hastalar <i class="bi bi-heart-pulse fs-5"></i></div>
      <div class="value"><?= number_format($patCount, 0, ',', '.') ?></div>
      <div class="label">Aktif hasta · son 30 gün +<?= array_sum($patSeries) ?></div>
      <canvas id="spPat"></canvas>
    </a>
  </div>
  <div class="col-md-4 col-xl-3">
    <a href="<?= url('admin/satislar.php') ?>" class="stat-card grad-orange d-block">
      <div class="head">Bu Ay Satış <i class="bi bi-graph-up-arrow fs-5"></i></div>
      <div class="value"><?= money($monthSales) ?></div>
      <div class="label"><?= tr_month((int)date('n')) ?> <?= date('Y') ?> cirosu</div>
      <canvas id="spSale"></canvas>
    </a>
  </div>
  <div class="col-xl-3">
    <div class="quick-grid">
      <a class="quick-btn" href="<?= url('admin/musteriler.php?islem=yeni') ?>"><i class="bi bi-person-plus grad-teal"></i>Hızlı Müşteri</a>
      <a class="quick-btn" href="<?= url('admin/hastalar.php?islem=yeni') ?>"><i class="bi bi-heart-pulse grad-purple"></i>Hızlı Hasta</a>
      <a class="quick-btn" href="<?= url('admin/asi-sihirbazi.php') ?>"><i class="bi bi-magic grad-pink"></i>Aşı Sihirbazı</a>
      <a class="quick-btn" href="<?= url('admin/randevular.php?islem=yeni') ?>"><i class="bi bi-calendar-plus grad-blue"></i>Hızlı Randevu</a>
      <a class="quick-btn" href="<?= url('admin/satis.php') ?>"><i class="bi bi-cart-plus grad-orange"></i>Hızlı Satış</a>
      <a class="quick-btn" href="<?= url('admin/muhasebe.php?sekme=tahsilat&yeni=1') ?>"><i class="bi bi-cash-coin grad-green"></i>Hızlı Tahsilat</a>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="panel-tabs grad-pink" data-tabs=".vpanel">
        <span class="title"><i class="bi bi-eyedropper me-1"></i>Aşılar</span>
        <span class="tab active" data-target="#vToday">Bugün <span class="badge text-bg-light ms-1"><?= count($vToday) ?></span></span>
        <span class="tab" data-target="#vSoon">Yaklaşan</span>
        <span class="tab" data-target="#vLate">Geciken <?php if ($vLate): ?><span class="badge text-bg-danger ms-1"><?= count($vLate) ?></span><?php endif; ?></span>
        <a class="add" href="<?= url('admin/asi-sihirbazi.php') ?>"><i class="bi bi-plus-lg"></i> Yeni Aşı</a>
      </div>
      <div class="card-body pt-1" style="max-height:420px;overflow:auto">
        <div id="vToday" class="vpanel"><?php vacc_rows($vToday, 'Bugün planlanmış aşı yok.'); ?></div>
        <div id="vSoon" class="vpanel d-none"><?php vacc_rows($vSoon, 'Önümüzdeki 7 gün için aşı yok.'); ?></div>
        <div id="vLate" class="vpanel d-none"><?php vacc_rows($vLate, 'Geciken aşı yok, harika!'); ?></div>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="panel-tabs grad-blue" data-tabs=".apanel">
        <span class="title"><i class="bi bi-calendar-heart me-1"></i>Randevular</span>
        <span class="tab active" data-target="#aToday">Bugün <span class="badge text-bg-light ms-1"><?= count($aToday) ?></span></span>
        <span class="tab" data-target="#aSoon">Yaklaşan</span>
        <span class="tab" data-target="#aLate">Geciken <?php if ($aLate): ?><span class="badge text-bg-danger ms-1"><?= count($aLate) ?></span><?php endif; ?></span>
        <a class="add" href="<?= url('admin/randevular.php?islem=yeni') ?>"><i class="bi bi-plus-lg"></i> Yeni Randevu</a>
      </div>
      <div class="card-body pt-1" style="max-height:420px;overflow:auto">
        <div id="aToday" class="apanel"><?php appt_rows($aToday, 'Bugün için randevu yok. Oluşturmak için tıklayın.'); ?></div>
        <div id="aSoon" class="apanel d-none"><?php appt_rows($aSoon, 'Yaklaşan randevu yok.'); ?></div>
        <div id="aLate" class="apanel d-none"><?php appt_rows($aLate, 'Sonuçlandırılmamış geçmiş randevu yok.'); ?></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="panel-tabs grad-green" data-tabs=".mpanel">
        <span class="title"><i class="bi bi-wallet2 me-1"></i>Muhasebe</span>
        <span class="tab active" data-target="#mPay">Son Tahsilatlar</span>
        <span class="tab" data-target="#mSale">Son Satışlar</span>
        <a class="add" href="<?= url('admin/muhasebe.php?sekme=tahsilat&yeni=1') ?>"><i class="bi bi-plus-lg"></i> Tahsilat</a>
      </div>
      <div class="card-body pt-1">
        <div id="mPay" class="mpanel">
          <table class="table table-sm table-hover"><thead><tr><th>Tarih</th><th>Müşteri</th><th>Yöntem</th><th class="text-end">Tutar</th></tr></thead><tbody>
          <?php foreach ($lastPays as $p): ?><tr><td><?= dtt($p['pay_date']) ?></td><td><?= $p['customer_id'] ? '<a href="' . url('admin/musteri.php?id=' . $p['customer_id']) . '">' . e($p['musteri']) . '</a>' : '-' ?></td><td><?= pay_method_label($p['method']) ?></td><td class="text-end fw-bold text-success"><?= money($p['amount']) ?></td></tr><?php endforeach; ?>
          <?php if (!$lastPays): ?><tr><td colspan="4" class="empty">Henüz tahsilat yok.</td></tr><?php endif; ?>
          </tbody></table>
        </div>
        <div id="mSale" class="mpanel d-none">
          <table class="table table-sm table-hover"><thead><tr><th>Tarih</th><th>Müşteri</th><th>Açıklama</th><th class="text-end">Tutar</th></tr></thead><tbody>
          <?php foreach ($lastSales as $s): ?><tr><td><?= dtt($s['sale_date']) ?></td><td><?= e($s['musteri'] ?: 'Peşin müşteri') ?></td><td class="small"><a href="<?= url('admin/satislar.php?id=' . $s['id']) ?>"><?= e($s['note'] ?: 'Satış #' . $s['id']) ?></a></td><td class="text-end fw-bold"><?= money($s['total']) ?></td></tr><?php endforeach; ?>
          <?php if (!$lastSales): ?><tr><td colspan="4" class="empty">Henüz satış yok.</td></tr><?php endif; ?>
          </tbody></table>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="panel-tabs grad-orange">
        <span class="title"><i class="bi bi-box-seam me-1"></i>Kritik Stok</span>
        <a class="add" href="<?= url('admin/urunler.php?kritik=1') ?>">Tümünü gör <i class="bi bi-arrow-right"></i></a>
      </div>
      <div class="card-body pt-1">
        <?php if (!$critical): ?><div class="empty"><i class="bi bi-check2-circle"></i>Tüm ürünlerin stoğu yeterli.</div><?php endif; ?>
        <?php foreach ($critical as $p): ?>
          <div class="list-row">
            <div class="pet-avatar" style="background:#ffedd5"><i class="bi bi-box-seam text-warning"></i></div>
            <div class="flex-grow-1"><div class="fw-bold"><?= e($p['name']) ?></div><div class="small text-muted"><?= e($p['category']) ?></div></div>
            <div class="text-end"><div class="fw-bold <?= $p['stock'] <= 0 ? 'text-danger' : 'text-warning' ?>"><?= qty($p['stock']) ?> <?= e($p['unit']) ?></div><div class="small text-muted">Kritik: <?= qty($p['critical_stock']) ?></div></div>
            <a class="btn btn-sm btn-outline-primary" href="<?= url('admin/stok.php?urun=' . $p['id']) ?>"><i class="bi bi-plus-lg"></i> Giriş</a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<?php
$pageScripts = '<script>ptSpark("spCust",' . json_encode($custSeries) . ');ptSpark("spPat",' . json_encode($patSeries) . ');ptSpark("spSale",' . json_encode($saleSeries) . ');</script>';
include APP . '/views/admin_footer.php';
