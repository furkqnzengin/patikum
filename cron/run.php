<?php
// Zamanlanmış görev — hatırlatıcıları üretir ve WhatsApp kuyruğunu gönderir.
// cPanel Cron (15 dakikada bir): php /home/KULLANICI/public_html/cron/run.php
// veya URL: https://alanadiniz.com/cron/run.php?key=CRON_ANAHTARI
define('NO_CSRF', true);
require __DIR__ . '/../app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    $key = (string)($_GET['key'] ?? '');
    if (!$key || !hash_equals((string)setting('cron_key'), $key)) {
        http_response_code(403);
        exit('Yetkisiz');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

$st = reminders_generate();
[$ok, $fail] = wa_process_queue(60);
echo date('Y-m-d H:i:s') . ' | Oluşturulan: ' . json_encode($st, JSON_UNESCAPED_UNICODE) . " | Gönderilen: $ok | Hatalı: $fail\n";
