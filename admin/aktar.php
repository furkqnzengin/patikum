<?php
require __DIR__ . '/../app/bootstrap.php';
require APP . '/import.php';
require_admin();
import_ensure();
$pageTitle = 'Excel\'den Veri Aktarımı';
$active = 'diger';
$kinds = imp_kinds();
$dir = APP . '/data/imports';
if (!is_dir($dir)) @mkdir($dir, 0775, true);
if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\n");

// Örnek şablon indir
if (($t = get('sablon')) && isset($kinds[$t])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="patikum-sablon-' . $t . '.csv"');
    echo imp_template_csv($t);
    exit;
}

// Eski geçici dosyaları temizle (2 saatten eski)
foreach (glob($dir . '/*.{xlsx,csv,txt}', GLOB_BRACE) ?: [] as $f) if (filemtime($f) < time() - 7200) @unlink($f);

$preview = null;
$kind = get('tur', '');
if (!isset($kinds[$kind])) $kind = '';

if (is_post()) {
    $act = post('islem');
    if ($act === 'yukle') {
        $f = $_FILES['dosya'] ?? null;
        $want = isset($kinds[post('tur')]) ? post('tur') : null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
            flash('danger', 'Dosya yüklenemedi. Lütfen bir Excel (.xlsx) veya CSV dosyası seçin' . (($f['error'] ?? 0) === UPLOAD_ERR_INI_SIZE ? ' (dosya sunucu limitinden büyük)' : '') . '.');
            redirect(url('admin/aktar.php' . ($want ? '?tur=' . $want : '')));
        }
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'csv', 'txt', 'xls'], true)) {
            flash('danger', 'Desteklenen dosya türleri: .xlsx, .csv');
            redirect(url('admin/aktar.php'));
        }
        $token = bin2hex(random_bytes(12));
        $path = $dir . '/' . $token . '.' . ($ext === 'xls' ? 'xlsx' : $ext);
        move_uploaded_file($f['tmp_name'], $path);
        try {
            [$k, $recs] = imp_load($path, $f['name'], $want);
            $_SESSION['imp'][$token] = ['path' => $path, 'name' => $f['name'], 'kind' => $k];
            $preview = ['token' => $token, 'kind' => $k, 'name' => $f['name'], 'data' => imp_preview($k, $recs)];
            $kind = $k;
        } catch (Throwable $e) {
            @unlink($path);
            flash('danger', e($e->getMessage()));
            redirect(url('admin/aktar.php' . ($want ? '?tur=' . $want : '')));
        }
    } elseif ($act === 'aktar') {
        $token = (string)post('token');
        $info = $_SESSION['imp'][$token] ?? null;
        if (!$info || !is_file($info['path'])) {
            flash('warning', 'Yükleme süresi doldu, lütfen dosyayı tekrar seçin.');
            redirect(url('admin/aktar.php'));
        }
        try {
            [$k, $recs] = imp_load($info['path'], $info['name'], $info['kind']);
            $opt = ['hizmet_ekle' => (bool)post('hizmet_ekle')];
            $st = imp_run($k, $recs, $opt);
            $msg = '<b>' . e($kinds[$k]['title']) . '</b> aktarıldı: ' . $st['yeni'] . ' yeni, ' . $st['guncelle'] . ' güncellendi'
                . ($st['atla'] ? ', ' . $st['atla'] . ' satır atlandı' : '')
                . (!empty($st['musteri_yeni']) ? ' · ' . $st['musteri_yeni'] . ' yeni müşteri oluşturuldu' : '')
                . (!empty($st['hasta_yeni']) ? ' · ' . $st['hasta_yeni'] . ' yeni hasta oluşturuldu' : '')
                . (!empty($st['hizmet_yeni']) ? ' · ' . $st['hizmet_yeni'] . ' hizmet ürün listesine eklendi' : '') . '.';
            flash($st['hata'] ? 'warning' : 'success', $msg . ($st['hata'] ? '<br>' . $st['hata'] . ' satırda hata: ' . e(implode(' | ', $st['hatalar'])) : ''));
        } catch (Throwable $e) {
            flash('danger', 'Aktarım tamamlanamadı, hiçbir değişiklik yapılmadı: ' . e($e->getMessage()));
        }
        @unlink($info['path']);
        unset($_SESSION['imp'][$token]);
        redirect(url('admin/aktar.php?tur=' . ($k ?? $info['kind']) . '&tamam=1'));
    } elseif ($act === 'iptal') {
        $info = $_SESSION['imp'][(string)post('token')] ?? null;
        if ($info) @unlink($info['path']);
        unset($_SESSION['imp'][(string)post('token')]);
        redirect(url('admin/aktar.php'));
    } elseif ($act === 'geri_al' && isset($kinds[post('tur')])) {
        try {
            $n = imp_undo(post('tur'));
            flash('success', e($kinds[post('tur')]['title']) . ' aktarımı geri alındı: ' . $n . ' kayıt silindi.');
        } catch (Throwable $e) {
            flash('danger', 'Geri alınamadı: ' . e($e->getMessage()));
        }
        redirect(url('admin/aktar.php'));
    }
}

$counts = imp_counts();
$targets = ['musteri' => 'admin/musteriler.php', 'hasta' => 'admin/hastalar.php', 'urun' => 'admin/urunler.php', 'asi' => 'admin/asilar.php?sekme=takvim', 'satis' => 'admin/satislar.php?baslangic=2000-01-01'];
$order = array_keys($kinds);

include APP . '/views/admin_header.php'; ?>
<div class="page-head">
  <div><h1><i class="bi bi-file-earmark-arrow-up text-primary"></i> Excel'den Veri Aktarımı</h1><div class="sub">Eski programınızdan (Kolayvet vb.) aldığınız Excel listelerini Patikum'a aktarın</div></div>
  <a href="<?= url('admin/') ?>" class="btn btn-light"><i class="bi bi-arrow-left"></i> Genel Bakış</a>
</div>

<?php if ($preview): $pv = $preview['data']; $k = $preview['kind']; ?>
  <div class="card mb-3 import-preview">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <span><i class="bi bi-<?= $kinds[$k]['icon'] ?> me-1"></i> Önizleme: <?= e($kinds[$k]['title']) ?> <span class="text-muted fw-normal small">· <?= e($preview['name']) ?></span></span>
      <span class="badge rounded-pill text-bg-light border">Algılanan liste: <?= e($kinds[$k]['file']) ?></span>
    </div>
    <div class="card-body">
      <div class="row g-2 mb-3">
        <div class="col-6 col-md-3"><div class="mini-stat"><span>Toplam satır</span><b><?= $pv['sum']['toplam'] ?></b></div></div>
        <div class="col-6 col-md-3"><div class="mini-stat ok"><span>Yeni eklenecek</span><b><?= $pv['sum']['yeni'] ?></b></div></div>
        <div class="col-6 col-md-3"><div class="mini-stat info"><span>Güncellenecek</span><b><?= $pv['sum']['guncelle'] ?></b></div></div>
        <div class="col-6 col-md-3"><div class="mini-stat warn"><span>Atlanacak / uyarı</span><b><?= $pv['sum']['atla'] ?> / <?= $pv['sum']['uyari'] ?></b></div></div>
      </div>
      <?php foreach ($pv['notes'] as $n): ?><div class="alert alert-info py-2 small mb-2"><i class="bi bi-info-circle me-1"></i><?= e($n) ?></div><?php endforeach; ?>
      <div class="table-responsive border rounded-3">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th></th><?php foreach ($pv['heads'] as $h): ?><th><?= e($h) ?></th><?php endforeach; ?><th>Not</th></tr></thead>
          <tbody>
          <?php foreach ($pv['rows'] as $r): ?>
            <tr class="<?= $r['act'] === 'atla' ? 'opacity-50' : '' ?>">
              <td><?= ['yeni' => '<span class="badge text-bg-success">Yeni</span>', 'guncelle' => '<span class="badge text-bg-info">Güncelle</span>', 'atla' => '<span class="badge text-bg-secondary">Atla</span>'][$r['act']] ?></td>
              <?php foreach ($r['cells'] as $c): ?><td class="small"><?= e($c) ?></td><?php endforeach; ?>
              <td class="small text-warning-emphasis"><?= e(implode(', ', $r['warn'])) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($pv['sum']['toplam'] > count($pv['rows'])): ?><div class="small text-muted mt-1">İlk <?= count($pv['rows']) ?> satır gösteriliyor.</div><?php endif; ?>
    </div>
    <form method="post" class="card-footer bg-white">
      <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($preview['token']) ?>">
      <?php if ($k === 'asi'): ?>
        <div class="alert alert-light border small mb-2"><i class="bi bi-calendar2-check text-primary me-1"></i> Aşılar Excel'deki tarihleriyle takvime eklenir: <b>Yapıldı</b> olanlar yapıldı olarak işlenir, <b>Yapılmadı</b> olanlar kendi tarihine planlanır ve "15 gün sonra yapılacak" gibi kalan süreyle görünür. Tarihi geçmiş olanlar "geciken" listesine düşer.</div>
      <?php endif; ?>
      <?php if ($k === 'satis'): ?>
        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="hizmet_ekle" value="1" id="hzE">
          <label class="form-check-label" for="hzE">Ürün listesinde olmayan kalemleri (Hemogram, Enjeksiyon, Röntgen…) <b>hizmet</b> olarak ürün listesine ekle (fiyatları sonra girilebilir)</label></div>
      <?php endif; ?>
      <div class="d-flex flex-wrap gap-2 justify-content-end">
        <button name="islem" value="iptal" class="btn btn-light" formnovalidate>Vazgeç</button>
        <button name="islem" value="aktar" class="btn btn-primary px-4" data-confirm="<?= $pv['sum']['toplam'] ?> satır aktarılacak. Devam edilsin mi?"><i class="bi bi-check2-circle"></i> Onayla ve Aktar</button>
      </div>
    </form>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-cloud-arrow-up me-1"></i> Dosya Yükle</div>
      <div class="card-body">
        <form method="post" enctype="multipart/form-data" class="dropzone-form">
          <?= csrf_field() ?><input type="hidden" name="islem" value="yukle">
          <label class="dropzone" for="dosya">
            <i class="bi bi-file-earmark-spreadsheet"></i>
            <b>Excel dosyasını seçin veya buraya sürükleyin</b>
            <span class="small text-muted">.xlsx veya .csv · Dosya türü başlıklardan otomatik algılanır</span>
            <span class="file-name small fw-bold text-primary mt-1"></span>
            <input type="file" name="dosya" id="dosya" accept=".xlsx,.csv,.xls" required>
          </label>
          <div class="d-flex flex-wrap gap-2 align-items-center mt-3">
            <select name="tur" class="form-select w-auto"><option value="">Liste türü: otomatik algıla</option><?php foreach ($kinds as $key => $d): ?><option value="<?= $key ?>" <?= $kind === $key ? 'selected' : '' ?>><?= e($d['file']) ?></option><?php endforeach; ?></select>
            <button class="btn btn-primary ms-auto px-4"><i class="bi bi-eye"></i> Önizle</button>
          </div>
        </form>
      </div>
    </div>

    <div class="row g-3">
      <?php foreach ($kinds as $key => $d): $c = $counts[$key] ?? null; $i = array_search($key, $order) + 1; ?>
        <div class="col-md-6">
          <div class="import-card <?= $kind === $key ? 'active' : '' ?>">
            <div class="d-flex align-items-start gap-3">
              <div class="step"><?= $i ?></div>
              <div class="flex-grow-1">
                <div class="d-flex justify-content-between align-items-center"><h6 class="mb-0 fw-bold"><i class="bi bi-<?= $d['icon'] ?> me-1"></i><?= e($d['file']) ?></h6>
                  <?php if ($c): ?><span class="badge text-bg-success-subtle text-success-emphasis"><i class="bi bi-check2"></i> <?= $c['n'] ?> kayıt</span><?php endif; ?></div>
                <p class="small text-muted mb-2 mt-1"><?= e($d['desc']) ?></p>
                <div class="d-flex flex-wrap gap-1">
                  <a class="btn btn-sm btn-outline-primary" href="?tur=<?= $key ?>#dosya"><i class="bi bi-upload"></i> Yükle</a>
                  <a class="btn btn-sm btn-light" href="?sablon=<?= $key ?>"><i class="bi bi-download"></i> Şablon</a>
                  <?php if ($c): ?>
                    <a class="btn btn-sm btn-light" href="<?= url($targets[$key]) ?>"><i class="bi bi-box-arrow-up-right"></i> Görüntüle</a>
                    <?php if ($c['c'] > 0): ?><form method="post" class="ms-auto" data-confirm="<?= e($d['title']) ?> aktarımıyla oluşturulan <?= $c['c'] ?> kayıt silinecek. Emin misiniz?"><?= csrf_field() ?><input type="hidden" name="islem" value="geri_al"><input type="hidden" name="tur" value="<?= $key ?>"><button class="btn btn-sm btn-link text-danger p-1" title="Bu aktarımı geri al"><i class="bi bi-arrow-counterclockwise"></i> Geri al</button></form><?php endif; ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-lightbulb me-1"></i> Nasıl çalışır?</div>
      <div class="card-body small">
        <ol class="ps-3 mb-3 import-steps">
          <li>Eski programdan listeleri <b>Excel</b> olarak indirin.</li>
          <li>Önerilen sıra: <b>Müşteriler → Hastalar → Ürünler → Aşı Takvimi → Satışlar</b>.</li>
          <li>Dosyayı yükleyin, önizlemeyi kontrol edip <b>Onayla ve Aktar</b>'a basın.</li>
        </ol>
        <ul class="list-unstyled mb-0 d-grid gap-2">
          <li><i class="bi bi-telephone text-primary me-1"></i> Telefonlar <b>11 haneli</b> (05XXXXXXXXX) kaydedilir; hücredeki "Numarayı Ara / Whatsapp'tan yaz" gibi yazılar alınmaz.</li>
          <li><i class="bi bi-arrow-repeat text-primary me-1"></i> Aynı dosyayı tekrar yüklerseniz kayıtlar <b>çoğalmaz</b>, güncellenir.</li>
          <li><i class="bi bi-wallet2 text-primary me-1"></i> Müşteri bakiyeleri eski programdaki <b>kalan bakiye</b> ile birebir eşitlenir.</li>
          <li><i class="bi bi-whatsapp text-success me-1"></i> Aktarım sırasında müşterilere <b>hiçbir mesaj gönderilmez</b>.</li>
          <li><i class="bi bi-arrow-counterclockwise text-danger me-1"></i> Bir aktarımı <b>Geri al</b> ile tamamen silebilirsiniz.</li>
        </ul>
      </div>
    </div>
  </div>
</div>
<?php
$pageScripts = '<script>(function(){const i=document.getElementById("dosya");if(!i)return;const z=i.closest(".dropzone"),n=z.querySelector(".file-name");i.addEventListener("change",()=>{n.textContent=i.files[0]?i.files[0].name:"";});["dragenter","dragover"].forEach(ev=>z.addEventListener(ev,e=>{e.preventDefault();z.classList.add("over");}));["dragleave","drop"].forEach(ev=>z.addEventListener(ev,e=>{e.preventDefault();z.classList.remove("over");}));z.addEventListener("drop",e=>{if(e.dataTransfer.files.length){i.files=e.dataTransfer.files;n.textContent=i.files[0].name;}});})();</script>';
include APP . '/views/admin_footer.php';
