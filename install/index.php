<?php
/**
 * Patikum Kurulum Sihirbazı
 * Kurulumdan sonra bu klasörü (install/) sunucudan SİLİN.
 */
declare(strict_types=1);
define('ROOT', dirname(__DIR__));
define('APP', ROOT . '/app');
date_default_timezone_set('Europe/Istanbul');
session_start();

$cfgFile = APP . '/config.php';
$done = false;
$error = null;
$locked = is_file($cfgFile);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (!$locked && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $driver = $_POST['driver'] === 'sqlite' ? 'sqlite' : 'mysql';
    $CONFIG = [
        'db' => $driver === 'sqlite'
            ? ['driver' => 'sqlite', 'path' => APP . '/data/patikum.sqlite']
            : ['driver' => 'mysql', 'host' => trim($_POST['host'] ?: 'localhost'), 'port' => (int)($_POST['port'] ?: 3306), 'name' => trim($_POST['name']), 'user' => trim($_POST['user']), 'pass' => $_POST['pass']],
        'base_url' => '',
        'timezone' => 'Europe/Istanbul',
        'debug' => false,
    ];
    $GLOBALS['CONFIG'] = $CONFIG;
    try {
        if (strlen($_POST['admin_pass'] ?? '') < 8) throw new RuntimeException('Yönetici şifresi en az 8 karakter olmalı.');
        if (!filter_var($_POST['admin_email'] ?? '', FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Geçerli bir e-posta girin.');
        if ($driver === 'sqlite' && !is_dir(APP . '/data')) mkdir(APP . '/data', 0775, true);
        require APP . '/db.php';
        require APP . '/helpers.php';
        require APP . '/whatsapp.php';
        require APP . '/sales.php';
        require APP . '/vaccine.php';
        require APP . '/seed.php';
        $pdo = db();
        if ((int)$pdo->query("SELECT COUNT(*) FROM " . ($driver === 'sqlite' ? "sqlite_master WHERE type='table' AND name='users'" : "information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'"))->fetchColumn() > 0) {
            throw new RuntimeException('Veritabanında zaten Patikum tabloları var. Boş bir veritabanı kullanın.');
        }
        foreach (schema_statements($driver) as $st) $pdo->exec($st);
        seed_defaults();
        if (!empty($_POST['clinic_name'])) setting_set('clinic_name', trim($_POST['clinic_name']));
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $base = rtrim(str_replace('\\', '/', dirname(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])))), '/');
        setting_set('site_url', ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $base);
        insert('users', ['name' => trim($_POST['admin_name'] ?: 'Yönetici'), 'email' => mb_strtolower(trim($_POST['admin_email'])), 'password' => password_hash($_POST['admin_pass'], PASSWORD_DEFAULT), 'role' => 'admin', 'active' => 1]);
        if (!empty($_POST['demo'])) seed_demo();

        $export = var_export($CONFIG, true);
        $php = "<?php\n// Patikum yapılandırma dosyası — kurulum sihirbazı tarafından oluşturuldu (" . date('d.m.Y H:i') . ")\nreturn " . $export . ";\n";
        if (file_put_contents($cfgFile, $php) === false) throw new RuntimeException('app/config.php yazılamadı. Klasör izinlerini kontrol edin (755).');
        $done = true;
        $locked = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Patikum Kurulum</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:linear-gradient(135deg,#0d5c63,#14919b);min-height:100vh}.card{border:0;border-radius:20px}</style>
</head>
<body class="d-flex align-items-center py-5">
<div class="container" style="max-width:720px">
  <div class="card shadow-lg"><div class="card-body p-4 p-md-5">
    <h1 class="h3 mb-1">🐾 Patikum Kurulum</h1>
    <p class="text-muted">Veteriner klinik yönetim sistemi kurulum sihirbazı</p>
    <?php if ($done): ?>
      <div class="alert alert-success"><strong>Kurulum tamamlandı!</strong><br>Güvenlik için sunucudaki <code>install/</code> klasörünü şimdi silin.</div>
      <a class="btn btn-success btn-lg" href="../admin/login.php">Yönetim Paneline Git →</a>
      <a class="btn btn-outline-secondary btn-lg" href="../">Siteyi Gör</a>
    <?php elseif ($locked): ?>
      <div class="alert alert-warning">Sistem zaten kurulu (<code>app/config.php</code> mevcut). Yeniden kurmak için önce bu dosyayı silin. Güvenlik için <code>install/</code> klasörünü kaldırmayı unutmayın.</div>
      <a class="btn btn-primary" href="../admin/login.php">Yönetim Paneli</a>
    <?php else: ?>
      <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
      <form method="post" autocomplete="off">
        <h5 class="mt-3">1. Veritabanı</h5>
        <p class="small text-muted">cPanel &gt; MySQL Veritabanları bölümünden bir veritabanı ve kullanıcı oluşturup kullanıcıya TÜM YETKİLERİ verin.</p>
        <div class="mb-3">
          <select name="driver" class="form-select" onchange="document.getElementById('mysqlBox').style.display=this.value==='mysql'?'':'none'">
            <option value="mysql">MySQL / MariaDB (cPanel - önerilen)</option>
            <option value="sqlite" <?= (($_POST['driver'] ?? '') === 'sqlite') ? 'selected' : '' ?>>SQLite (yerel test)</option>
          </select>
        </div>
        <div id="mysqlBox" class="row g-2" <?= (($_POST['driver'] ?? '') === 'sqlite') ? 'style="display:none"' : '' ?>>
          <div class="col-md-8"><label class="form-label">Sunucu</label><input name="host" class="form-control" value="<?= h($_POST['host'] ?? 'localhost') ?>"></div>
          <div class="col-md-4"><label class="form-label">Port</label><input name="port" class="form-control" value="<?= h($_POST['port'] ?? '3306') ?>"></div>
          <div class="col-md-4"><label class="form-label">Veritabanı adı</label><input name="name" class="form-control" placeholder="cpkullanici_patikum" value="<?= h($_POST['name'] ?? '') ?>"></div>
          <div class="col-md-4"><label class="form-label">Kullanıcı</label><input name="user" class="form-control" value="<?= h($_POST['user'] ?? '') ?>"></div>
          <div class="col-md-4"><label class="form-label">Şifre</label><input name="pass" type="password" class="form-control"></div>
        </div>
        <h5 class="mt-4">2. Klinik ve Yönetici</h5>
        <div class="row g-2">
          <div class="col-12"><label class="form-label">Klinik adı</label><input name="clinic_name" class="form-control" value="<?= h($_POST['clinic_name'] ?? 'Patikum Veteriner Kliniği') ?>"></div>
          <div class="col-md-4"><label class="form-label">Ad Soyad</label><input name="admin_name" class="form-control" required value="<?= h($_POST['admin_name'] ?? '') ?>"></div>
          <div class="col-md-4"><label class="form-label">E-posta</label><input name="admin_email" type="email" class="form-control" required value="<?= h($_POST['admin_email'] ?? '') ?>"></div>
          <div class="col-md-4"><label class="form-label">Şifre (min 8)</label><input name="admin_pass" type="password" class="form-control" required minlength="8"></div>
        </div>
        <div class="form-check mt-3">
          <input class="form-check-input" type="checkbox" name="demo" value="1" id="demo">
          <label class="form-check-label" for="demo">Örnek (demo) müşteri, hasta ve randevu verisi ekle</label>
        </div>
        <button class="btn btn-success btn-lg w-100 mt-4">Kurulumu Başlat</button>
      </form>
    <?php endif; ?>
  </div></div>
</div>
</body>
</html>
