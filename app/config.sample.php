<?php
// Patikum yapılandırma örneği.
// Normalde /install/ sihirbazı app/config.php dosyasını otomatik oluşturur.
// Elle kurulum yapacaksanız bu dosyayı app/config.php olarak kopyalayıp düzenleyin.
return [
    'db' => [
        'driver' => 'mysql',          // mysql | sqlite
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'cpkullanici_patikum',
        'user' => 'cpkullanici_patikum',
        'pass' => 'GUCLU_BIR_SIFRE',
        // 'path' => __DIR__ . '/data/patikum.sqlite', // sqlite için
    ],
    'base_url' => '',                 // Alt klasöre kurulduysa örn: '/patikum'. Boş = otomatik
    'timezone' => 'Europe/Istanbul',
    'debug' => false,
];
