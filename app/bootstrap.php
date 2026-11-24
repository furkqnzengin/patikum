<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('APP', __DIR__);
define('PATIKUM_VERSION', '1.1.0');

$cfgFile = APP . '/config.php';
if (!is_file($cfgFile)) {
    if (PHP_SAPI === 'cli') exit("app/config.php bulunamadı. Önce kurulumu yapın.\n");
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $base = preg_replace('#/(admin|panel|cron|install)(/.*)?$#', '', str_replace('\\', '/', dirname($script)));
    header('Location: ' . rtrim((string)$base, '/') . '/install/');
    exit;
}
$CONFIG = require $cfgFile;

date_default_timezone_set($CONFIG['timezone'] ?? 'Europe/Istanbul');
mb_internal_encoding('UTF-8');
if (!empty($CONFIG['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

require APP . '/db.php';
require APP . '/helpers.php';
require APP . '/auth.php';
require APP . '/whatsapp.php';
require APP . '/sales.php';
require APP . '/vaccine.php';
require APP . '/reminders.php';
require APP . '/einvoice.php';

if (PHP_SAPI !== 'cli') {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('PATIKUMSESS');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $secure]);
    session_start();
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    if (is_post() && !defined('NO_CSRF')) csrf_check();
}
