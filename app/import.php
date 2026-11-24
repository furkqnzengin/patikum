<?php
/**
 * Excel'den veri aktarımı (Kolayvet ve benzeri programların dışa aktarımları).
 * Desteklenen dosyalar: Müşteri Listesi, Hasta Listesi, Ürün Listesi, Aşı Takvimi, Satış Geçmişi.
 *
 * Her aktarılan kayıt import_map tablosunda eski programdaki numarasıyla eşlenir;
 * böylece aynı dosya tekrar yüklendiğinde kayıtlar çoğaltılmaz, güncellenir.
 */

require_once __DIR__ . '/xlsx.php';

const IMP_SRC = 'kolayvet';
const IMP_DEVIR_DATE = '2000-01-01 00:00:00';

/* ------------------------------------------------------------------ */
/* Tanımlar                                                            */
/* ------------------------------------------------------------------ */

function imp_kinds(): array
{
    return [
        'musteri' => [
            'title' => 'Müşteriler', 'icon' => 'people', 'file' => 'Müşteri Listesi',
            'desc' => 'Ad soyad, 11 haneli cep telefonu, TC kimlik no ve kalan bakiye (devir) aktarılır.',
            'cols' => [
                'ext' => ['müşteri no', 'musteri no', 'müşteri kodu'],
                'name' => ['müşteri adı', 'ad soyad', 'adı soyadı', 'müşteri', 'isim'],
                'phone' => ['cep telefonu', 'telefon', 'cep', 'gsm', 'müşteri telefon', 'telefon no'],
                'balance' => ['kalan bakiye', 'bakiye', 'borç', 'cari bakiye'],
                'tc' => ['tc kimlik no', 'tc no', 'tc kimlik', 'tc', 'vergi no'],
                'email' => ['e-posta', 'eposta', 'email', 'mail'],
                'address' => ['adres'],
            ],
            'required' => ['name'],
        ],
        'hasta' => [
            'title' => 'Hastalar', 'icon' => 'heart-pulse', 'file' => 'Hasta Listesi',
            'desc' => 'Hayvanlar sahibine bağlanarak aktarılır: tür, ırk, cinsiyet, mikroçip.',
            'cols' => [
                'cust_ext' => ['müşteri no'],
                'cust_name' => ['müşteri adı', 'sahibi', 'hasta sahibi'],
                'phone' => ['müşteri telefon', 'telefon', 'cep telefonu'],
                'ext' => ['hasta no', 'hasta kodu'],
                'name' => ['hasta adı', 'hayvan adı', 'adı'],
                'species' => ['türü', 'tür', 'hasta tür', 'cins'],
                'gender' => ['cinsiyet', 'cinsiyeti'],
                'breed' => ['ırkı', 'ırk', 'irki'],
                'chip' => ['mikroçip', 'mikroçip no', 'çip no'],
                'birth' => ['doğum tarihi'],
            ],
            'required' => ['name'],
        ],
        'urun' => [
            'title' => 'Ürünler', 'icon' => 'box-seam', 'file' => 'Ürün Listesi',
            'desc' => 'Ürün adı, barkod, satış/alış fiyatı ve stok miktarı aktarılır. "Stoksuz ürün" olanlar hizmet olarak eklenir.',
            'cols' => [
                'status' => ['durum'],
                'stock' => ['stok', 'stok miktarı', 'miktar'],
                'ext' => ['ürün no', 'ürün kodu', 'stok kodu'],
                'name' => ['ürün', 'ürün adı', 'ürün / hizmet'],
                'barcode' => ['barkod', 'barkod no'],
                'serial' => ['seri numarası'],
                'sell' => ['satış fiyatı', 'fiyat', 'satış'],
                'buy' => ['alış fiyatı', 'maliyet', 'alış'],
                'category' => ['kategori', 'grup'],
                'vat' => ['kdv', 'kdv oranı'],
            ],
            'required' => ['name'],
        ],
        'asi' => [
            'title' => 'Aşı Takvimi', 'icon' => 'eyedropper', 'file' => 'Aşı Takvimi',
            'desc' => 'Yapılan ve planlanan aşılar hasta kartlarına işlenir. Eksik müşteri/hasta varsa otomatik oluşturulur.',
            'cols' => [
                'cust_ext' => ['müşteri no'],
                'cust_name' => ['müşteri adı'],
                'phone' => ['müşteri telefon', 'telefon', 'cep telefonu'],
                'tc' => ['tc kimlik no'],
                'pat_ext' => ['hasta no'],
                'pat_name' => ['hasta adı'],
                'species' => ['hasta tür', 'türü', 'tür'],
                'vaccine' => ['aşı', 'aşı adı', 'aşı türü'],
                'due' => ['aşı tarihi', 'planlanan tarih', 'tarih'],
                'status' => ['yapılma durumu', 'durum'],
                'done' => ['yapılma tarihi', 'uygulama tarihi'],
                'batch' => ['seri no', 'lot no'],
                'location' => ['lokasyon'],
            ],
            'required' => ['pat_name', 'vaccine', 'due'],
        ],
        'satis' => [
            'title' => 'Satışlar', 'icon' => 'cart3', 'file' => 'Satış Geçmişi',
            'desc' => 'Geçmiş satışlar, kalemleri ve tahsilat durumu (Ödenmiş / Kısmen / Ödenmemiş) aktarılır. Stoklar değişmez.',
            'cols' => [
                'ext' => ['işlem no', 'satış no', 'fiş no'],
                'date' => ['tarih', 'satış tarihi', 'işlem tarihi'],
                'customer' => ['müşteri', 'müşteri adı'],
                'content' => ['açıklama / içerik', 'açıklama', 'içerik', 'ürünler'],
                'total' => ['toplam tutar', 'tutar', 'toplam'],
                'paid' => ['tahsilat', 'ödenen', 'tahsil edilen'],
                'status' => ['işlem durumu', 'durum', 'ödeme durumu'],
            ],
            'required' => ['date', 'total'],
        ],
    ];
}

/* ------------------------------------------------------------------ */
/* Tablo ve eşleme                                                     */
/* ------------------------------------------------------------------ */

function import_ensure(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    $sql = db_driver() === 'sqlite'
        ? 'CREATE TABLE IF NOT EXISTS import_map (id INTEGER PRIMARY KEY AUTOINCREMENT, source VARCHAR(30) NOT NULL, kind VARCHAR(20) NOT NULL, ext_id VARCHAR(190) NOT NULL, local_id INTEGER NOT NULL, created TINYINT NOT NULL DEFAULT 0, extra TEXT NULL, created_at DATETIME NULL)'
        : 'CREATE TABLE IF NOT EXISTS import_map (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, source VARCHAR(30) NOT NULL, kind VARCHAR(20) NOT NULL, ext_id VARCHAR(190) NOT NULL, local_id INT UNSIGNED NOT NULL, created TINYINT(1) NOT NULL DEFAULT 0, extra TEXT NULL, created_at DATETIME NULL, KEY idx_imp (source, kind, ext_id), KEY idx_imp_local (kind, local_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    db()->exec($sql);
    if (db_driver() === 'sqlite') db()->exec('CREATE INDEX IF NOT EXISTS idx_imp ON import_map (source, kind, ext_id)');
}

function imp_map_get(string $kind, string $ext): ?int
{
    if ($ext === '') return null;
    $id = val('SELECT local_id FROM import_map WHERE source = ? AND kind = ? AND ext_id = ? ORDER BY id DESC LIMIT 1', [IMP_SRC, $kind, $ext]);
    return $id ? (int)$id : null;
}

function imp_map_set(string $kind, string $ext, int $localId, bool $created, ?string $extra = null): void
{
    if ($ext === '') return;
    $row = row('SELECT id, created FROM import_map WHERE source = ? AND kind = ? AND ext_id = ?', [IMP_SRC, $kind, $ext]);
    if ($row) {
        q('UPDATE import_map SET local_id = ?, extra = ? WHERE id = ?', [$localId, $extra, $row['id']]);
    } else {
        insert('import_map', ['source' => IMP_SRC, 'kind' => $kind, 'ext_id' => $ext, 'local_id' => $localId, 'created' => $created ? 1 : 0, 'extra' => $extra]);
    }
}

/** Eşlenen kaydın yerel karşılığı silinmişse eşlemeyi de temizler */
function imp_map_valid(string $kind, string $ext, string $table): ?int
{
    $id = imp_map_get($kind, $ext);
    if ($id && !val("SELECT id FROM $table WHERE id = ?", [$id])) {
        q('DELETE FROM import_map WHERE source = ? AND kind = ? AND ext_id = ?', [IMP_SRC, $kind, $ext]);
        return null;
    }
    return $id;
}

/* ------------------------------------------------------------------ */
/* Değer dönüştürücüler                                                */
/* ------------------------------------------------------------------ */

/** Karşılaştırma anahtarı: Türkçe harfleri sadeleştirir, küçük harfe çevirir */
function imp_key(?string $s): string
{
    $s = str_replace(['İ', 'I'], ['i', 'ı'], (string)$s);
    $s = mb_strtolower($s, 'UTF-8');
    $s = strtr($s, ['ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u', 'â' => 'a', 'î' => 'i', 'û' => 'u']);
    return preg_replace('/[^a-z0-9]+/', '', $s);
}

function imp_empty($v): bool
{
    $k = imp_key((string)$v);
    return $k === '' || in_array($k, ['eklenmemis', 'null', 'nullnull', 'yok', 'belirtilmemis'], true) || trim((string)$v) === '-';
}

function imp_text($v): string
{
    return imp_empty($v) ? '' : trim(preg_replace('/\s+/u', ' ', (string)$v));
}

/** Excel'in bilimsel gösterimdeki tam sayılarını düzeltir (1.99E10 → 19900000000) */
function imp_intstr($v): string
{
    $v = trim((string)$v);
    if ($v === '' || imp_empty($v)) return '';
    if (preg_match('/^-?\d+(\.\d+)?[eE]\+?\d+$/', $v)) return number_format((float)$v, 0, '', '');
    if (preg_match('/^(\d+)\.0+$/', $v, $m)) return $m[1];
    return $v;
}

/**
 * Telefonu 11 haneli 05XXXXXXXXX biçimine getirir.
 * Kolayvet'in "0545 905 79 560545 905 79 56 Numarayı Ara Whatsapp'tan yaz…" gibi
 * tekrar eden ve yazı içeren hücrelerinden yalnızca numarayı alır.
 */
function imp_phone($v): string
{
    $s = (string)$v;
    if (imp_empty($s)) return '';
    // Yalnızca ilk harften önceki kısım numaradır; arkasındaki yazılar atılır
    if (preg_match('/^[^\p{L}]*/u', $s, $m)) $s = $m[0];
    $plus = str_starts_with(ltrim($s), '+');
    $d = preg_replace('/\D+/', '', $s);
    if ($d === '') return '';
    // Aynı numara iki kez yan yana yazılmışsa birini al
    $len = strlen($d);
    if ($len % 2 === 0 && $len >= 14 && substr($d, 0, $len / 2) === substr($d, $len / 2)) $d = substr($d, 0, $len / 2);
    if ($plus && !str_starts_with($d, '90')) return '+' . $d; // yurt dışı numarası
    if (strlen($d) === 12 && str_starts_with($d, '90')) $d = '0' . substr($d, 2);
    elseif (strlen($d) === 10 && $d[0] === '5') $d = '0' . $d;
    elseif (strlen($d) > 11 && str_starts_with($d, '05')) $d = substr($d, 0, 11);
    elseif (strlen($d) > 11 && str_starts_with($d, '5')) $d = '0' . substr($d, 0, 10);
    return $d;
}

/** "6,000.00TL", "1.500,00 ₺", "800.0" → float */
function imp_money($v): float
{
    $s = preg_replace('/[^0-9,.\-]/', '', (string)$v);
    if ($s === '' || $s === '-') return 0.0;
    $neg = str_starts_with($s, '-');
    $s = str_replace('-', '', $s);
    $c = strrpos($s, ',');
    $d = strrpos($s, '.');
    if ($c !== false && $d !== false) {
        $dec = $c > $d ? ',' : '.';
        $s = $dec === ',' ? str_replace(['.', ','], ['', '.'], $s) : str_replace(',', '', $s);
    } elseif ($c !== false) {
        $s = preg_match('/,\d{1,2}$/', $s) && substr_count($s, ',') === 1 ? str_replace(',', '.', $s) : str_replace(',', '', $s);
    } elseif ($d !== false && substr_count($s, '.') > 1) {
        $s = str_replace('.', '', $s);
    }
    $n = round((float)$s, 2);
    return $neg ? -$n : $n;
}

/** "22 Kasım 2026", "22.11.2026", "2026-11-22" veya Excel seri numarası → Y-m-d */
function imp_date($v): ?string
{
    $s = trim((string)$v);
    if ($s === '' || imp_empty($s)) return null;
    $months = ['ocak' => 1, 'subat' => 2, 'mart' => 3, 'nisan' => 4, 'mayis' => 5, 'haziran' => 6, 'temmuz' => 7, 'agustos' => 8, 'eylul' => 9, 'ekim' => 10, 'kasim' => 11, 'aralik' => 12,
        'oca' => 1, 'sub' => 2, 'mar' => 3, 'nis' => 4, 'may' => 5, 'haz' => 6, 'tem' => 7, 'agu' => 8, 'eyl' => 9, 'eki' => 10, 'kas' => 11, 'ara' => 12];
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $s, $m)) return imp_ymd((int)$m[1], (int)$m[2], (int)$m[3]);
    if (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})/', $s, $m)) return imp_ymd((int)$m[3], (int)$m[2], (int)$m[1]);
    if (preg_match('/^(\d{1,2})\s+(\S+)\s+(\d{4})/u', $s, $m)) {
        $mon = $months[imp_key($m[2])] ?? null;
        if ($mon) return imp_ymd((int)$m[3], $mon, (int)$m[1]);
    }
    if (is_numeric($s) && (float)$s > 20000 && (float)$s < 80000) return date('Y-m-d', (int)round(((float)$s - 25569) * 86400));
    return null;
}

function imp_ymd(int $y, int $m, int $d): ?string
{
    return checkdate($m, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $m, $d) : null;
}

function imp_title(string $s): string
{
    $s = trim($s);
    if ($s === '') return $s;
    $lower = mb_strtolower(str_replace(['İ', 'I'], ['i', 'ı'], $s), 'UTF-8');
    return preg_replace_callback('/(^|[\s(\/+\-])(\p{Ll})/u', fn($m) => $m[1] . ($m[2] === 'i' ? 'İ' : ($m[2] === 'ı' ? 'I' : mb_strtoupper($m[2], 'UTF-8'))), $lower);
}

/* ------------------------------------------------------------------ */
/* Dosya okuma ve başlık tespiti                                       */
/* ------------------------------------------------------------------ */

/** Başlık satırını bulur ve tür tahmini yapar. [kind, headerRowIndex, colMap] */
function imp_detect(array $rows, ?string $want = null): array
{
    $best = [null, -1, [], 0];
    $kinds = imp_kinds();
    foreach (array_slice($rows, 0, 15, true) as $i => $r) {
        $keys = array_map('imp_key', $r);
        foreach ($kinds as $k => $def) {
            if ($want && $want !== $k) continue;
            $map = [];
            foreach ($def['cols'] as $field => $aliases) {
                foreach ($aliases as $a) {
                    $pos = array_search(imp_key($a), $keys, true);
                    if ($pos !== false && !in_array($pos, $map, true)) { $map[$field] = $pos; break; }
                }
            }
            $ok = !array_diff($def['required'], array_keys($map));
            $score = count($map) + ($ok ? 10 : 0);
            if ($ok && $score > $best[3]) $best = [$k, $i, $map, $score];
        }
    }
    return [$best[0], $best[1], $best[2]];
}

/** Dosyayı okuyup normalleştirilmiş kayıt listesi döndürür */
function imp_load(string $path, string $origName, ?string $want = null): array
{
    $rows = sheet_read($path, $origName);
    [$kind, $hi, $map] = imp_detect($rows, $want);
    if (!$kind) {
        $hint = $want ? '"' . imp_kinds()[$want]['file'] . '" dosyası' : 'bilinen bir liste';
        throw new RuntimeException('Dosyada ' . $hint . ' için gerekli sütun başlıkları bulunamadı. Eski programdan aldığınız Excel dosyasını değiştirmeden yükleyin veya örnek şablondaki başlıkları kullanın.');
    }
    $records = [];
    foreach (array_slice($rows, $hi + 1) as $n => $r) {
        $rec = [];
        foreach ($map as $f => $pos) $rec[$f] = (string)($r[$pos] ?? '');
        if (!array_filter($rec, fn($v) => trim($v) !== '')) continue;
        $rec['_line'] = $hi + $n + 2;
        $records[] = $rec;
    }
    return [$kind, $records, $map];
}

/* ------------------------------------------------------------------ */
/* Ortak çözümleyiciler (müşteri, hasta, tür, aşı türü, ürün)          */
/* ------------------------------------------------------------------ */

/** Müşteri arama önbelleği (telefon ve ada göre) */
function &imp_ctx_ref(): array
{
    static $ctx = null;
    if ($ctx === null) {
        $ctx = ['phone' => [], 'name' => []];
        foreach (rows('SELECT id, name, phone FROM customers') as $c) {
            $p = phone_norm($c['phone']);
            if ($p !== '') $ctx['phone'][$p] = (int)$c['id'];
            $ctx['name'][imp_key($c['name'])][] = (int)$c['id'];
        }
    }
    return $ctx;
}

function imp_ctx_add(int $id, string $name, string $phone): void
{
    $ref = &imp_ctx_ref();
    if (phone_norm($phone) !== '') $ref['phone'][phone_norm($phone)] = $id;
    $ref['name'][imp_key($name)][] = $id;
}

/** Var olan müşteriyi bulur (oluşturmaz) */
function imp_find_customer(string $ext, string $name, string $phone): ?int
{
    $ctx = &imp_ctx_ref();
    if ($ext !== '' && ($id = imp_map_valid('musteri', $ext, 'customers'))) return $id;
    $pn = phone_norm($phone);
    if ($pn !== '' && isset($ctx['phone'][$pn])) {
        $id = $ctx['phone'][$pn];
        // Telefon başka bir Kolayvet müşterisine zaten bağlıysa (aile ortak numara) yeni kayıt açılır
        $taken = $ext !== '' ? val('SELECT ext_id FROM import_map WHERE source = ? AND kind = ? AND local_id = ? AND ext_id <> ? LIMIT 1', [IMP_SRC, 'musteri', $id, $ext]) : null;
        if (!$taken) return $id;
    }
    $nk = imp_key($name);
    if ($nk !== '' && $pn === '' && count($ctx['name'][$nk] ?? []) === 1) {
        $id = $ctx['name'][$nk][0];
        $mapped = val('SELECT COUNT(*) FROM import_map WHERE source = ? AND kind = ? AND local_id = ?', [IMP_SRC, 'musteri', $id]);
        if (!$mapped) return $id;
    }
    return null;
}

function imp_customer(string $ext, string $name, string $phone = '', string $tc = '', array &$st = []): ?int
{
    $id = imp_find_customer($ext, $name, $phone);
    if ($id) {
        if ($ext !== '' && !imp_map_get('musteri', $ext)) imp_map_set('musteri', $ext, $id, false);
        $c = row('SELECT phone, tc_no FROM customers WHERE id = ?', [$id]);
        $upd = [];
        if ($phone !== '' && trim((string)$c['phone']) === '') $upd['phone'] = $phone;
        if ($tc !== '' && trim((string)$c['tc_no']) === '') $upd['tc_no'] = $tc;
        if ($upd) update('customers', $upd, $id);
        return $id;
    }
    if ($name === '') return null;
    $id = insert('customers', [
        'name' => mb_substr($name, 0, 150), 'phone' => $phone !== '' ? $phone : null, 'tc_no' => $tc !== '' ? $tc : null,
        'notes' => 'Kolayvet\'ten aktarıldı' . ($ext !== '' ? ' (Müşteri No: ' . $ext . ')' : ''), 'wa_optin' => 1, 'active' => 1,
    ]);
    imp_map_set('musteri', $ext, $id, true);
    imp_ctx_add($id, $name, $phone);
    $st['musteri_yeni'] = ($st['musteri_yeni'] ?? 0) + 1;
    return $id;
}

function imp_species(string $name): ?int
{
    static $cache = [];
    $k = imp_key($name);
    if ($k === '' || imp_empty($name)) return null;
    if (array_key_exists($k, $cache)) return $cache[$k];
    $alias = ['kedi' => 'kedi', 'cat' => 'kedi', 'kopek' => 'kopek', 'dog' => 'kopek', 'kus' => 'kus', 'tavsan' => 'tavsan', 'diger' => 'diger', 'hamster' => 'kemirgen', 'kemirgen' => 'kemirgen', 'balik' => 'balik'];
    $want = $alias[$k] ?? $k;
    foreach (rows('SELECT id, name FROM species') as $s) {
        $sk = imp_key($s['name']);
        if ($sk === $want || str_starts_with($sk, $want) || ($want === 'diger' && str_contains($sk, 'diger'))) return $cache[$k] = (int)$s['id'];
    }
    $icons = ['kedi' => '🐱', 'kopek' => '🐶', 'kus' => '🦜', 'tavsan' => '🐰'];
    $id = insert('species', ['name' => imp_title($name), 'icon' => $icons[$want] ?? '🐾', 'color' => '#64748b', 'sort' => 50]);
    return $cache[$k] = $id;
}

function imp_breed(?int $speciesId, string $breed): void
{
    static $seen = [];
    if (!$speciesId || $breed === '') return;
    $k = $speciesId . ':' . imp_key($breed);
    if (isset($seen[$k])) return;
    $seen[$k] = true;
    foreach (rows('SELECT name FROM breeds WHERE species_id = ?', [$speciesId]) as $b) if (imp_key($b['name']) === imp_key($breed)) return;
    insert('breeds', ['species_id' => $speciesId, 'name' => mb_substr($breed, 0, 100)]);
}

function imp_gender(string $g): ?string
{
    $k = imp_key($g);
    if (str_starts_with($k, 'dis') || $k === 'f' || $k === 'kadin') return 'disi';
    if (str_starts_with($k, 'erk') || $k === 'm') return 'erkek';
    return null;
}

/** Aşı adından tür (kedi/köpek) tahmini */
function imp_species_from_vaccine(string $vac): ?int
{
    $k = imp_key($vac);
    if (str_contains($k, 'kedi')) return imp_species('Kedi');
    if (str_contains($k, 'kopek')) return imp_species('Köpek');
    return null;
}

/** Kolayvet aşı adını [standart ad, doz etiketi] olarak ayırır */
function imp_vaccine_parts(string $raw): array
{
    $s = trim(preg_replace('/\s+/u', ' ', $raw));
    $dose = null;
    if (preg_match('/^YILLIK\s+/iu', $s) || preg_match('/^yıllık\s+/iu', $s)) {
        $s = preg_replace('/^(YILLIK|yıllık|Yıllık)\s+/u', '', $s);
        $dose = 'Yıllık Rapel';
    }
    if (preg_match('/^(.*\S)\s+(\d{1,2})$/u', $s, $m)) {
        $s = $m[1];
        $dose = $m[2] . '. Doz';
    }
    $k = imp_key($s);
    $canon = null;
    if (str_contains($k, 'parazit')) {
        $ic = str_contains($k, 'ic');
        $dis = str_contains($k, 'dis');
        $canon = ($ic && $dis) ? 'İç + Dış Parazit' : ($dis ? 'Dış Parazit' : 'İç Parazit');
        if (preg_match('/(\d+)\s*AYLIK/iu', $s, $m)) $dose = $dose ?? ($m[1] . ' Aylık');
    } elseif (str_contains($k, 'karma')) {
        $canon = str_contains($k, 'kopek') ? 'Köpek Karma' : (str_contains($k, 'kedi') ? 'Kedi Karma' : 'Karma');
    } elseif (str_contains($k, 'kuduz')) {
        $canon = 'Kuduz';
    } elseif (str_contains($k, 'losemi')) {
        $canon = 'Lösemi';
    } elseif (str_contains($k, 'bronsin')) {
        $canon = 'Bronşin';
    } elseif (str_contains($k, 'corona')) {
        $canon = 'Corona';
    } elseif (str_contains($k, 'lyme')) {
        $canon = 'Lyme';
    } elseif (str_contains($k, 'giardia')) {
        $canon = 'Giardia';
    }
    return [$canon ?? imp_title($s), $dose];
}

function imp_vaccine_type(string $raw, ?int $speciesId): int
{
    static $cache = [];
    [$canon] = imp_vaccine_parts($raw);
    $ck = imp_key($canon) . ':' . (int)$speciesId;
    if (isset($cache[$ck])) return $cache[$ck];
    $want = imp_key($canon);
    // Standart adlar ile seed adları arasında eşleme (ör. "Lösemi" → "Lösemi (FeLV)")
    $types = rows('SELECT id, name, species_id FROM vaccine_types ORDER BY active DESC, id');
    $pick = null;
    foreach ([true, false] as $strictSpecies) {
        foreach ($types as $t) {
            $tk = imp_key($t['name']);
            if (!($tk === $want || str_starts_with($tk, $want))) continue;
            if ($strictSpecies) {
                if ($speciesId && (int)$t['species_id'] === $speciesId) { $pick = (int)$t['id']; break 2; }
                if (!$speciesId && $t['species_id'] === null) { $pick = (int)$t['id']; break 2; }
            } elseif (!$speciesId || $t['species_id'] === null) {
                $pick = (int)$t['id'];
                break 2;
            }
        }
    }
    if (!$pick) {
        $pick = insert('vaccine_types', ['species_id' => $speciesId, 'name' => mb_substr($canon, 0, 120), 'description' => 'Kolayvet aktarımı ile eklendi', 'active' => 1]);
    }
    return $cache[$ck] = $pick;
}

/** Ürün adı → [id, sell_price, vat_rate] önbelleği */
function &imp_products(): array
{
    static $p = null;
    if ($p === null) {
        $p = [];
        foreach (rows('SELECT id, name, sell_price, vat_rate FROM products') as $r) $p[imp_key($r['name'])] = $r;
    }
    return $p;
}

/**
 * "Hemogram,Purina Proplan Renal Function Cat 1,5 Kg,Enjeksiyon" gibi virgülle birleşik içerikleri kalemlere böler.
 * Ürün adlarının içindeki ondalık virgülleri (1,5 Kg) korur.
 */
function imp_split_items(string $content): array
{
    $content = trim($content);
    if ($content === '' || $content === '-') return [];
    $parts = array_map('trim', explode(',', $content));
    $prods = &imp_products();
    $out = [];
    $n = count($parts);
    for ($i = 0; $i < $n; $i++) {
        // En uzun eşleşen ürün adını dene (en fazla 3 parça birleştir)
        $matched = false;
        for ($len = min(3, $n - $i); $len >= 2; $len--) {
            $cand = implode(',', array_slice($parts, $i, $len));
            if (isset($prods[imp_key($cand)])) {
                $out[] = $cand;
                $i += $len - 1;
                $matched = true;
                break;
            }
        }
        if ($matched) continue;
        $cur = $parts[$i];
        // "Cat 1" + "5 Kg" → "Cat 1,5 Kg"
        while ($i + 1 < $n && preg_match('/\d$/', $cur) && preg_match('/^\d/', $parts[$i + 1]) && !isset($prods[imp_key($cur)])) {
            $cur .= ',' . $parts[++$i];
        }
        if ($cur !== '') $out[] = $cur;
    }
    return $out;
}

/* ------------------------------------------------------------------ */
/* Önizleme                                                            */
/* ------------------------------------------------------------------ */

/** Kayıtları veritabanına yazmadan analiz eder: tablo satırları ve özet */
function imp_preview(string $kind, array $records): array
{
    $sum = ['toplam' => count($records), 'yeni' => 0, 'guncelle' => 0, 'atla' => 0, 'uyari' => 0];
    $rowsOut = [];
    $notes = [];
    foreach ($records as $r) {
        $warn = [];
        $act = 'yeni';
        switch ($kind) {
            case 'musteri':
                $name = imp_text($r['name'] ?? '');
                $phone = imp_phone($r['phone'] ?? '');
                if ($name === '') { $act = 'atla'; $warn[] = 'Ad yok'; }
                elseif (imp_find_customer(imp_intstr($r['ext'] ?? ''), $name, $phone)) $act = 'guncelle';
                if ($phone === '') $warn[] = 'Telefon yok';
                elseif (!str_starts_with($phone, '+') && strlen($phone) !== 11) $warn[] = 'Telefon 11 hane değil';
                $cells = [imp_intstr($r['ext'] ?? ''), $name, $phone !== '' ? $phone : '—', imp_intstr($r['tc'] ?? ''), money(imp_money($r['balance'] ?? 0))];
                break;
            case 'hasta':
                $name = imp_text($r['name'] ?? '');
                if ($name === '' && trim((string)($r['name'] ?? '')) !== '') { $name = 'İsimsiz'; $warn[] = 'Adı "' . trim($r['name']) . '" → İsimsiz'; }
                $ext = imp_intstr($r['ext'] ?? '');
                if ($name === '') { $act = 'atla'; $warn[] = 'Hasta adı yok'; }
                elseif ($ext !== '' && imp_map_get('hasta', $ext)) $act = 'guncelle';
                if (!imp_find_customer(imp_intstr($r['cust_ext'] ?? ''), imp_text($r['cust_name'] ?? ''), imp_phone($r['phone'] ?? ''))) $warn[] = 'Sahibi yeni oluşturulacak';
                $cells = [$ext, $name, imp_text($r['cust_name'] ?? ''), imp_text($r['species'] ?? ''), imp_text($r['breed'] ?? ''), imp_text($r['gender'] ?? '')];
                break;
            case 'urun':
                $name = imp_text($r['name'] ?? '');
                if ($name === '') { $act = 'atla'; $warn[] = 'Ürün adı yok'; }
                else {
                    $ext = imp_intstr($r['ext'] ?? '');
                    $bc = imp_intstr($r['barcode'] ?? '');
                    if (($ext !== '' && imp_map_get('urun', $ext)) || ($bc !== '' && val('SELECT id FROM products WHERE barcode = ?', [$bc])) || isset(imp_products()[imp_key($name)])) $act = 'guncelle';
                }
                [$q, $unit, $svc] = imp_stock_parse($r['stock'] ?? '');
                $cells = [$name, imp_intstr($r['barcode'] ?? '') ?: '—', $svc ? 'Hizmet' : qty($q) . ' ' . $unit, money(imp_money($r['sell'] ?? 0)), money(imp_money($r['buy'] ?? 0))];
                break;
            case 'asi':
                $due = imp_date($r['due'] ?? '');
                $done = imp_key($r['status'] ?? '') === 'yapildi';
                if (!$due) { $act = 'atla'; $warn[] = 'Tarih okunamadı'; }
                elseif (imp_map_get('asi', imp_vac_key($r))) $act = 'guncelle';
                [$canon, $dose] = imp_vaccine_parts($r['vaccine'] ?? '');
                $when = '';
                if ($due && !$done) { $dd = days_diff($due); $when = $dd === 0 ? ' · bugün' : ($dd > 0 ? ' · ' . $dd . ' gün sonra' : ' · ' . abs($dd) . ' gün gecikti'); }
                $cells = [imp_text($r['pat_name'] ?? ''), imp_text($r['cust_name'] ?? ''), $canon . ($dose ? ' · ' . $dose : ''), $due ? dt($due) : '?', $done ? 'Yapıldı' . (($dt = imp_date($r['done'] ?? '')) ? ' (' . dt($dt) . ')' : '') : 'Planlandı' . $when];
                break;
            case 'satis':
                $date = imp_date($r['date'] ?? '');
                $ext = imp_intstr($r['ext'] ?? '');
                if (!$date) { $act = 'atla'; $warn[] = 'Tarih okunamadı'; }
                elseif ($ext !== '' && imp_map_get('satis', $ext)) $act = 'guncelle';
                [$cname] = imp_sale_customer($r['customer'] ?? '');
                $total = imp_money($r['total'] ?? 0);
                $cells = [$ext, $date ? dt($date) : '?', $cname ?: 'Müşterisiz', mb_strimwidth(imp_text($r['content'] ?? ''), 0, 60, '…'), money($total), imp_sale_status($r)[0]];
                break;
            default:
                $cells = [];
        }
        $sum[$act]++;
        if ($warn) $sum['uyari']++;
        if (count($rowsOut) < 25) $rowsOut[] = ['act' => $act, 'cells' => $cells, 'warn' => $warn];
    }
    if ($kind === 'satis') $notes[] = 'Satışlar stoktan düşülmez; ürün stokları Ürün Listesi aktarımındaki güncel miktarla belirlenir.';
    if ($kind === 'musteri') $notes[] = 'Telefonlar 11 haneli (05XXXXXXXXX) olarak kaydedilir, hücredeki "Numarayı Ara / Whatsapp\'tan yaz" yazıları alınmaz.';
    return ['sum' => $sum, 'rows' => $rowsOut, 'notes' => $notes, 'heads' => imp_preview_heads($kind)];
}

function imp_preview_heads(string $kind): array
{
    return [
        'musteri' => ['No', 'Ad Soyad', 'Telefon (11 hane)', 'TC Kimlik', 'Bakiye'],
        'hasta' => ['No', 'Hasta', 'Sahibi', 'Tür', 'Irk', 'Cinsiyet'],
        'urun' => ['Ürün', 'Barkod', 'Stok', 'Satış', 'Alış'],
        'asi' => ['Hasta', 'Sahibi', 'Aşı', 'Tarih', 'Durum'],
        'satis' => ['No', 'Tarih', 'Müşteri', 'İçerik', 'Tutar', 'Durum'],
    ][$kind] ?? [];
}

/* ------------------------------------------------------------------ */
/* Aktarım                                                             */
/* ------------------------------------------------------------------ */

function imp_run(string $kind, array $records, array $opt = []): array
{
    import_ensure();
    @set_time_limit(600);
    $st = ['yeni' => 0, 'guncelle' => 0, 'atla' => 0, 'hata' => 0, 'hatalar' => []];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        foreach ($records as $r) {
            try {
                $res = match ($kind) {
                    'musteri' => imp_row_musteri($r, $st),
                    'hasta' => imp_row_hasta($r, $st),
                    'urun' => imp_row_urun($r, $st),
                    'asi' => imp_row_asi($r, $st, $opt),
                    'satis' => imp_row_satis($r, $st, $opt),
                };
                $st[$res]++;
            } catch (Throwable $e) {
                $st['hata']++;
                if (count($st['hatalar']) < 20) $st['hatalar'][] = 'Satır ' . ($r['_line'] ?? '?') . ': ' . $e->getMessage();
            }
        }
        if (in_array($kind, ['musteri', 'satis'], true)) imp_reconcile_all();
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    setting_set('import_last_' . $kind, now());
    return $st;
}

function imp_row_musteri(array $r, array &$st): string
{
    $name = imp_text($r['name'] ?? '');
    if ($name === '') return 'atla';
    $ext = imp_intstr($r['ext'] ?? '');
    $phone = imp_phone($r['phone'] ?? '');
    $tc = imp_intstr($r['tc'] ?? '');
    if ($tc !== '' && !preg_match('/^\d{10,11}$/', $tc)) $tc = '';
    $balance = isset($r['balance']) ? imp_money($r['balance']) : null;
    $email = filter_var(imp_text($r['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: null;
    $address = imp_text($r['address'] ?? '') ?: null;

    $mapKey = $ext !== '' ? $ext : 'ad:' . imp_key($name) . ':' . $phone;
    $extra = $balance !== null ? (string)$balance : null;
    $id = imp_find_customer($ext, $name, $phone) ?? imp_map_valid('musteri', $mapKey, 'customers');
    if ($id) {
        $c = row('SELECT * FROM customers WHERE id = ?', [$id]);
        $upd = ['name' => mb_substr($name, 0, 150)];
        if ($phone !== '') $upd['phone'] = $phone;
        if ($tc !== '') $upd['tc_no'] = $tc;
        if ($email && !$c['email']) $upd['email'] = $email;
        if ($address && !$c['address']) $upd['address'] = $address;
        update('customers', $upd, $id);
        imp_map_set('musteri', $mapKey, $id, false, $extra);
        $res = 'guncelle';
    } else {
        $id = insert('customers', [
            'name' => mb_substr($name, 0, 150), 'phone' => $phone !== '' ? $phone : null, 'tc_no' => $tc !== '' ? $tc : null,
            'email' => $email, 'address' => $address, 'wa_optin' => 1, 'active' => 1,
            'notes' => 'Kolayvet\'ten aktarıldı' . ($ext !== '' ? ' (Müşteri No: ' . $ext . ')' : ''),
        ]);
        imp_map_set('musteri', $mapKey, $id, true, $extra);
        imp_ctx_add($id, $name, $phone);
        $res = 'yeni';
    }
    return $res;
}

function imp_row_hasta(array $r, array &$st): string
{
    $name = imp_text($r['name'] ?? '');
    if ($name === '' && trim((string)($r['name'] ?? '')) !== '') $name = 'İsimsiz';
    if ($name === '') return 'atla';
    $ext = imp_intstr($r['ext'] ?? '');
    $cid = imp_customer(imp_intstr($r['cust_ext'] ?? ''), imp_text($r['cust_name'] ?? ''), imp_phone($r['phone'] ?? ''), '', $st);
    if (!$cid) throw new RuntimeException('Hasta sahibi bulunamadı: ' . $name);
    $sp = imp_species(imp_text($r['species'] ?? ''));
    $breed = imp_text($r['breed'] ?? '');
    imp_breed($sp, $breed);
    $data = ['customer_id' => $cid, 'name' => mb_substr($name, 0, 100)];
    if ($sp) $data['species_id'] = $sp;
    if ($breed !== '') $data['breed'] = mb_substr($breed, 0, 100);
    if ($g = imp_gender($r['gender'] ?? '')) $data['gender'] = $g;
    if (($chip = imp_intstr($r['chip'] ?? '')) !== '' && !in_array(imp_key($chip), ['olusturuldu', 'olusturulmadi', 'var', 'yok'], true)) $data['chip_no'] = mb_substr($chip, 0, 60);
    if ($b = imp_date($r['birth'] ?? '')) $data['birth_date'] = $b;

    $id = $ext !== '' ? imp_map_valid('hasta', $ext, 'patients') : null;
    if (!$id) {
        foreach (rows('SELECT id, name FROM patients WHERE customer_id = ?', [$cid]) as $p) {
            if (imp_key($p['name']) === imp_key($name) && !val('SELECT COUNT(*) FROM import_map WHERE source = ? AND kind = ? AND local_id = ?', [IMP_SRC, 'hasta', $p['id']])) { $id = (int)$p['id']; break; }
        }
        if ($id) imp_map_set('hasta', $ext, $id, false);
    }
    if ($id) {
        update('patients', $data, $id);
        return 'guncelle';
    }
    $data += ['status' => 'aktif', 'notes' => 'Kolayvet\'ten aktarıldı' . ($ext !== '' ? ' (Hasta No: ' . $ext . ')' : '')];
    $id = insert('patients', $data);
    imp_map_set('hasta', $ext !== '' ? $ext : 'ad:' . $cid . ':' . imp_key($name), $id, true);
    return 'yeni';
}

/** Hasta (Kolayvet aşı dosyasından): bulamazsa oluşturur */
function imp_patient_for(string $pext, string $pname, int $cid, ?int $speciesGuess, array &$st): int
{
    if ($pext !== '' && ($id = imp_map_valid('hasta', $pext, 'patients'))) return $id;
    foreach (rows('SELECT id, name FROM patients WHERE customer_id = ?', [$cid]) as $p) {
        if (imp_key($p['name']) === imp_key($pname)) {
            imp_map_set('hasta', $pext, (int)$p['id'], false);
            return (int)$p['id'];
        }
    }
    $id = insert('patients', ['customer_id' => $cid, 'name' => mb_substr($pname ?: 'İsimsiz', 0, 100), 'species_id' => $speciesGuess, 'status' => 'aktif', 'notes' => 'Kolayvet aşı takviminden aktarıldı']);
    imp_map_set('hasta', $pext !== '' ? $pext : 'ad:' . $cid . ':' . imp_key($pname), $id, true);
    $st['hasta_yeni'] = ($st['hasta_yeni'] ?? 0) + 1;
    return $id;
}

function imp_stock_parse($v): array
{
    $s = trim((string)$v);
    if ($s === '' ) return [0.0, 'Adet', false];
    if (str_contains(imp_key($s), 'stoksuz') || str_contains(imp_key($s), 'hizmet')) return [0.0, 'Adet', true];
    $q = 0.0;
    if (preg_match('/-?\d+(?:[.,]\d+)?/', $s, $m)) $q = (float)str_replace(',', '.', $m[0]);
    $unit = trim(preg_replace('/-?\d+(?:[.,]\d+)?/', '', $s)) ?: 'Adet';
    return [$q, mb_substr(imp_title($unit), 0, 20), false];
}

function imp_row_urun(array $r, array &$st): string
{
    $name = imp_text($r['name'] ?? '');
    if ($name === '') return 'atla';
    $ext = imp_intstr($r['ext'] ?? '');
    $bc = imp_intstr($r['barcode'] ?? '');
    [$q, $unit, $svc] = imp_stock_parse($r['stock'] ?? '');
    $sell = imp_money($r['sell'] ?? 0);
    $buy = imp_money($r['buy'] ?? 0);
    $data = ['name' => mb_substr($name, 0, 200), 'unit' => $unit, 'sell_price' => $sell, 'is_service' => $svc ? 1 : 0];
    if ($bc !== '') $data['barcode'] = mb_substr($bc, 0, 60);
    if ($buy > 0) $data['buy_price'] = $buy;
    if (($cat = imp_text($r['category'] ?? '')) !== '') $data['category'] = mb_substr($cat, 0, 80);
    if (isset($r['vat']) && is_numeric(trim(str_replace('%', '', $r['vat'])))) $data['vat_rate'] = (int)trim(str_replace('%', '', $r['vat']));

    $prods = &imp_products();
    $id = $ext !== '' ? imp_map_valid('urun', $ext, 'products') : null;
    if (!$id && $bc !== '') $id = (int)val('SELECT id FROM products WHERE barcode = ? LIMIT 1', [$bc]) ?: null;
    if (!$id && isset($prods[imp_key($name)])) {
        $cand = (int)$prods[imp_key($name)]['id'];
        // Aynı adlı ama eski programda farklı numaralı ürünler ayrı tutulur
        $other = $ext !== '' ? val("SELECT ext_id FROM import_map WHERE source = ? AND kind = 'urun' AND local_id = ? AND ext_id <> ? AND ext_id NOT LIKE 'ad:%' LIMIT 1", [IMP_SRC, $cand, $ext]) : null;
        if (!$other) $id = $cand;
    }
    if ($id) {
        $old = row('SELECT stock FROM products WHERE id = ?', [$id]);
        update('products', $data + ['active' => 1], $id);
        if (!$svc && abs($q - (float)$old['stock']) > 0.0001) stock_move($id, $q - (float)$old['stock'], 'sayim', 'Kolayvet aktarımı (stok eşitleme)', 'import', null);
        imp_map_set('urun', $ext, $id, false);
        $res = 'guncelle';
    } else {
        $id = insert('products', $data + ['vat_rate' => (int)setting('service_vat', '20'), 'stock' => 0, 'critical_stock' => 0, 'active' => 1]);
        if (!$svc && abs($q) > 0.0001) stock_move($id, $q, 'sayim', 'Kolayvet açılış stoğu', 'import', null);
        imp_map_set('urun', $ext !== '' ? $ext : 'ad:' . imp_key($name), $id, true);
        $res = 'yeni';
    }
    $prods[imp_key($name)] = ['id' => $id, 'name' => $name, 'sell_price' => $sell, 'vat_rate' => $data['vat_rate'] ?? (int)setting('service_vat', '20')];
    return $res;
}

function imp_vac_key(array $r): string
{
    $pat = imp_intstr($r['pat_ext'] ?? '') ?: imp_key(($r['cust_name'] ?? '') . '-' . ($r['pat_name'] ?? ''));
    return $pat . '|' . imp_key($r['vaccine'] ?? '') . '|' . (imp_date($r['due'] ?? '') ?? '');
}

function imp_row_asi(array $r, array &$st, array $opt): string
{
    $due = imp_date($r['due'] ?? '');
    $vac = imp_text($r['vaccine'] ?? '');
    $pname = imp_text($r['pat_name'] ?? '');
    if (!$due || $vac === '' || $pname === '') return 'atla';
    $cid = imp_customer(imp_intstr($r['cust_ext'] ?? ''), imp_text($r['cust_name'] ?? ''), imp_phone($r['phone'] ?? ''), imp_intstr($r['tc'] ?? ''), $st);
    if (!$cid) throw new RuntimeException('Müşteri bulunamadı');
    $spGuess = imp_species(imp_text($r['species'] ?? '')) ?: imp_species_from_vaccine($vac);
    $pid = imp_patient_for(imp_intstr($r['pat_ext'] ?? ''), $pname, $cid, $spGuess, $st);
    $patSp = val('SELECT species_id FROM patients WHERE id = ?', [$pid]);
    if (!$patSp && $spGuess) { update('patients', ['species_id' => $spGuess], $pid); $patSp = $spGuess; }
    $typeId = imp_vaccine_type($vac, $patSp ? (int)$patSp : null);
    [, $dose] = imp_vaccine_parts($vac);

    $isDone = imp_key($r['status'] ?? '') === 'yapildi';
    $status = $isDone ? 'yapildi' : 'planlandi';
    $doneDate = $isDone ? (imp_date($r['done'] ?? '') ?? $due) : null;
    $notes = [];
    if (($loc = imp_text($r['location'] ?? '')) !== '') $notes[] = $loc;
    $data = [
        'patient_id' => $pid, 'vaccine_type_id' => $typeId, 'dose_label' => $dose, 'due_date' => $due,
        'done_date' => $doneDate, 'status' => $status, 'batch_no' => imp_text($r['batch'] ?? '') ?: null,
        'notes' => $notes ? mb_substr('Kolayvet · ' . implode(' · ', $notes), 0, 250) : 'Kolayvet aktarımı',
    ];
    $key = imp_vac_key($r);
    $id = imp_map_valid('asi', $key, 'vaccinations');
    if ($id) {
        $cur = row('SELECT status FROM vaccinations WHERE id = ?', [$id]);
        // Patikum'da sonradan yapıldı işaretlenen aşıyı geri almayalım
        if ($cur && $cur['status'] === 'yapildi' && $status !== 'yapildi') unset($data['status'], $data['done_date']);
        update('vaccinations', $data, $id);
        return 'guncelle';
    }
    $id = insert('vaccinations', $data);
    imp_map_set('asi', $key, $id, true);
    return 'yeni';
}

/** "Ayşegül Zengin (719232)" → ['Ayşegül Zengin', '719232'] ; "-" → ['', ''] */
function imp_sale_customer(string $v): array
{
    $v = trim($v);
    if ($v === '' || $v === '-' || imp_key($v) === 'musterisizsatis') return ['', ''];
    if (preg_match('/^(.*?)\s*\((\d+)\)\s*$/u', $v, $m)) return [trim($m[1]), $m[2]];
    return [$v, ''];
}

/** [etiket, ödenen tutar] */
function imp_sale_status(array $r): array
{
    $total = imp_money($r['total'] ?? 0);
    $paid = imp_money($r['paid'] ?? 0);
    $k = imp_key($r['status'] ?? '');
    if (str_starts_with($k, 'kismen')) return ['Kısmen Ödenmiş', min($paid, $total)];
    if (str_starts_with($k, 'odenmemis')) return ['Ödenmemiş', min($paid, $total)];
    if (str_starts_with($k, 'odenmis')) return ['Ödenmiş', $total];
    if (!isset($r['status'])) return [$paid >= $total ? 'Ödenmiş' : 'Kısmen Ödenmiş', min($paid, $total)];
    return [$paid >= $total ? 'Ödenmiş' : ($paid > 0 ? 'Kısmen Ödenmiş' : 'Ödenmemiş'), min($paid, $total)];
}

function imp_row_satis(array $r, array &$st, array $opt): string
{
    $date = imp_date($r['date'] ?? '');
    if (!$date) return 'atla';
    $ext = imp_intstr($r['ext'] ?? '');
    $total = imp_money($r['total'] ?? 0);
    [$cname, $cext] = imp_sale_customer($r['customer'] ?? '');
    $cid = $cname !== '' || $cext !== '' ? imp_customer($cext, $cname, '', '', $st) : null;
    [, $paid] = imp_sale_status($r);
    if (!$cid) $paid = $total; // müşterisiz (peşin) satış
    $content = imp_text($r['content'] ?? '');
    $names = imp_split_items($content) ?: ['Satış' . ($ext !== '' ? ' #' . $ext : '')];

    // Kalemlere fiyat dağıtımı: ürün listesindeki satış fiyatlarına orantılı, toplam tutarı tutturacak şekilde
    $prods = &imp_products();
    $lines = [];
    $known = 0.0;
    $unknown = 0;
    foreach ($names as $n) {
        $p = $prods[imp_key($n)] ?? null;
        if (!$p && !empty($opt['hizmet_ekle']) && mb_strlen($n) >= 3) {
            $pidNew = insert('products', ['category' => 'Hizmet', 'name' => mb_substr($n, 0, 200), 'unit' => 'Adet', 'sell_price' => 0, 'vat_rate' => (int)setting('service_vat', '20'), 'is_service' => 1, 'active' => 1]);
            imp_map_set('urun', 'ad:' . imp_key($n), $pidNew, true);
            $p = $prods[imp_key($n)] = ['id' => $pidNew, 'name' => $n, 'sell_price' => 0, 'vat_rate' => (int)setting('service_vat', '20')];
            $st['hizmet_yeni'] = ($st['hizmet_yeni'] ?? 0) + 1;
        }
        $w = $p ? (float)$p['sell_price'] : 0.0;
        if ($w > 0) $known += $w; else $unknown++;
        $lines[] = ['description' => mb_substr($n, 0, 250), 'product_id' => $p['id'] ?? null, 'vat_rate' => (int)($p['vat_rate'] ?? 20), 'w' => $w];
    }
    $cnt = count($lines);
    if ($known > 0 && $known <= $total) {
        $rest = $unknown ? ($total - $known) / $unknown : 0;
        foreach ($lines as &$l) $l['amt'] = $l['w'] > 0 ? $l['w'] * ($unknown ? 1 : $total / $known) : $rest;
    } elseif ($known > 0) {
        foreach ($lines as &$l) $l['amt'] = $unknown ? ($l['w'] > 0 ? $l['w'] / $known * $total * 0.8 : $total * 0.2 / $unknown) : $l['w'] / $known * $total;
    } else {
        foreach ($lines as &$l) $l['amt'] = $total / $cnt;
    }
    unset($l);
    $sumR = 0.0;
    foreach ($lines as $i => &$l) {
        $l['amt'] = $i === $cnt - 1 ? round($total - $sumR, 2) : round($l['amt'], 2);
        $sumR += $l['amt'];
    }
    unset($l);
    $vat = 0.0;
    foreach ($lines as $l) $vat += $l['amt'] - $l['amt'] / (1 + $l['vat_rate'] / 100);

    $saleDate = $date . ' 12:00:00';
    $head = [
        'customer_id' => $cid, 'sale_date' => $saleDate, 'subtotal' => $total, 'discount' => 0, 'vat_total' => round($vat, 2), 'total' => $total,
        'note' => mb_substr($content !== '' ? $content : 'Kolayvet satışı', 0, 250), 'source' => 'kolayvet', 'status' => 'aktif',
    ];
    $id = $ext !== '' ? imp_map_valid('satis', $ext, 'sales') : null;
    if ($id) {
        update('sales', $head, $id);
        q('DELETE FROM sale_items WHERE sale_id = ?', [$id]);
        q("DELETE FROM payments WHERE sale_id = ? AND note LIKE 'Kolayvet%'", [$id]);
        $res = 'guncelle';
    } else {
        $id = insert('sales', $head + ['user_id' => $_SESSION['uid'] ?? null]);
        imp_map_set('satis', $ext !== '' ? $ext : 'satir:' . md5(json_encode($r)), $id, true);
        $res = 'yeni';
    }
    foreach ($lines as $l) {
        insert('sale_items', ['sale_id' => $id, 'product_id' => $l['product_id'], 'description' => $l['description'], 'qty' => 1, 'unit_price' => $l['amt'], 'vat_rate' => $l['vat_rate'], 'total' => $l['amt']]);
    }
    if ($paid > 0.004) {
        insert('payments', ['customer_id' => $cid, 'sale_id' => $id, 'amount' => round($paid, 2), 'method' => 'diger', 'pay_date' => $saleDate,
            'note' => 'Kolayvet tahsilatı · İşlem No ' . ($ext ?: $id), 'user_id' => $_SESSION['uid'] ?? null]);
    }
    return $res;
}

/* ------------------------------------------------------------------ */
/* Bakiye eşitleme (devir)                                             */
/* ------------------------------------------------------------------ */

/**
 * Kolayvet müşteri listesindeki "Kalan bakiye" ile Patikum'daki aktarılmış kayıtlardan
 * hesaplanan bakiye farklıysa farkı "Kolayvet devir bakiyesi" olarak işler.
 * Böylece müşterinin bakiyesi eski programla birebir aynı olur.
 */
function imp_reconcile(int $cid, float $kvBalance): void
{
    foreach (rows("SELECT id FROM sales WHERE customer_id = ? AND source = 'kolayvet_devir'", [$cid]) as $s) q('DELETE FROM sales WHERE id = ?', [$s['id']]);
    q("DELETE FROM payments WHERE customer_id = ? AND note LIKE 'Kolayvet devir%'", [$cid]);
    $sales = (float)val("SELECT COALESCE(SUM(total),0) FROM sales WHERE customer_id = ? AND status = 'aktif' AND source = 'kolayvet'", [$cid]);
    $pays = (float)val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE customer_id = ? AND note LIKE 'Kolayvet%'", [$cid]);
    $diff = round($kvBalance - ($sales - $pays), 2);
    if ($diff > 0.009) {
        $sid = insert('sales', ['customer_id' => $cid, 'sale_date' => IMP_DEVIR_DATE, 'subtotal' => $diff, 'discount' => 0, 'vat_total' => 0, 'total' => $diff,
            'note' => 'Kolayvet devir bakiyesi', 'source' => 'kolayvet_devir', 'status' => 'aktif']);
        insert('sale_items', ['sale_id' => $sid, 'description' => 'Devir bakiyesi (Kolayvet)', 'qty' => 1, 'unit_price' => $diff, 'vat_rate' => 0, 'total' => $diff]);
    } elseif ($diff < -0.009) {
        // Eski programda satıştan bağımsız alınan tahsilatlar: en eski açık satışlardan başlayarak kapatılır
        $credit = -$diff;
        $open = rows("SELECT s.id, s.sale_date, s.total - COALESCE((SELECT SUM(amount) FROM payments x WHERE x.sale_id = s.id),0) AS kalan
                      FROM sales s WHERE s.customer_id = ? AND s.status = 'aktif' AND s.source = 'kolayvet' ORDER BY s.sale_date, s.id", [$cid]);
        foreach ($open as $o) {
            if ($credit <= 0.009) break;
            $k = round((float)$o['kalan'], 2);
            if ($k <= 0.009) continue;
            $amt = min($k, $credit);
            insert('payments', ['customer_id' => $cid, 'sale_id' => $o['id'], 'amount' => round($amt, 2), 'method' => 'diger', 'pay_date' => $o['sale_date'], 'note' => 'Kolayvet devir tahsilatı (cari hesaptan)']);
            $credit -= $amt;
        }
        if ($credit > 0.009) {
            insert('payments', ['customer_id' => $cid, 'amount' => round($credit, 2), 'method' => 'diger', 'pay_date' => IMP_DEVIR_DATE, 'note' => 'Kolayvet devir alacağı (müşterinin fazla ödemesi)']);
        }
    }
}

function imp_reconcile_all(): void
{
    foreach (rows("SELECT local_id, extra FROM import_map WHERE source = ? AND kind = 'musteri' AND extra IS NOT NULL AND extra <> ''", [IMP_SRC]) as $m) {
        if (val('SELECT id FROM customers WHERE id = ?', [$m['local_id']])) imp_reconcile((int)$m['local_id'], (float)$m['extra']);
    }
}

/* ------------------------------------------------------------------ */
/* Geri alma ve durum                                                  */
/* ------------------------------------------------------------------ */

function imp_counts(): array
{
    import_ensure();
    $out = [];
    foreach (rows('SELECT kind, COUNT(*) n, SUM(created) c FROM import_map WHERE source = ? GROUP BY kind', [IMP_SRC]) as $r) $out[$r['kind']] = ['n' => (int)$r['n'], 'c' => (int)$r['c']];
    return $out;
}

/** Aktarımla OLUŞTURULAN kayıtları siler (mevcut kayıtlarla eşleşenlere dokunmaz) */
function imp_undo(string $kind): int
{
    import_ensure();
    $tables = ['musteri' => 'customers', 'hasta' => 'patients', 'urun' => 'products', 'asi' => 'vaccinations', 'satis' => 'sales'];
    if (!isset($tables[$kind])) return 0;
    $pdo = db();
    $pdo->beginTransaction();
    $n = 0;
    try {
        if ($kind === 'satis' || $kind === 'musteri') {
            // Devir kayıtları
            foreach (rows("SELECT id FROM sales WHERE source = 'kolayvet_devir'") as $s) q('DELETE FROM sales WHERE id = ?', [$s['id']]);
            q("DELETE FROM payments WHERE note LIKE 'Kolayvet devir%'");
        }
        $ids = array_map('intval', array_column(rows('SELECT local_id FROM import_map WHERE source = ? AND kind = ? AND created = 1', [IMP_SRC, $kind]), 'local_id'));
        foreach (array_chunk($ids, 300) as $chunk) {
            $in = implode(',', $chunk);
            if ($kind === 'satis') q("DELETE FROM payments WHERE sale_id IN ($in)");
            if ($kind === 'urun') q("DELETE FROM stock_movements WHERE product_id IN ($in)");
            if ($kind === 'musteri') {
                q("DELETE FROM payments WHERE customer_id IN ($in)");
                foreach (rows("SELECT id FROM sales WHERE customer_id IN ($in)") as $s) q('DELETE FROM sales WHERE id = ?', [$s['id']]);
            }
            $n += q("DELETE FROM {$tables[$kind]} WHERE id IN ($in)")->rowCount();
        }
        if ($kind === 'satis') q("DELETE FROM payments WHERE note LIKE 'Kolayvet tahsilatı%'");
        q('DELETE FROM import_map WHERE source = ? AND kind = ?', [IMP_SRC, $kind]);
        imp_map_cleanup();
        if ($kind === 'satis') imp_reconcile_all();
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return $n;
}

/** Yerel kaydı silinmiş eşlemeleri temizler */
function imp_map_cleanup(): void
{
    foreach (['musteri' => 'customers', 'hasta' => 'patients', 'urun' => 'products', 'asi' => 'vaccinations', 'satis' => 'sales'] as $k => $t) {
        q("DELETE FROM import_map WHERE source = ? AND kind = ? AND local_id NOT IN (SELECT id FROM $t)", [IMP_SRC, $k]);
    }
}

/** Örnek şablon (CSV, Excel ile açılır) */
function imp_template_csv(string $kind): string
{
    $tpl = [
        'musteri' => [['Müşteri NO', 'Müşteri Adı', 'Cep Telefonu', 'Kalan bakiye', 'TC Kimlik No'], ['1001', 'Ayşe Yılmaz', '05321112233', '0', '']],
        'hasta' => [['Müşteri NO', 'Müşteri Adı', 'Hasta NO', 'Hasta Adı', 'Türü', 'Cinsiyet', 'Irkı', 'Mikroçip'], ['1001', 'Ayşe Yılmaz', '5001', 'Luna', 'Kedi', 'Dişi', 'Tekir', '']],
        'urun' => [['Durum', 'Stok', 'Ürün NO', 'Ürün', 'Barkod', 'Satış fiyatı', 'Alış fiyatı'], ['Aktif', '10 Adet', '9001', 'Kedi Maması 1,5 Kg', '8690000000000', '650', '400']],
        'asi' => [['Müşteri NO', 'Müşteri Adı', 'Müşteri Telefon', 'Hasta NO', 'Hasta Adı', 'Aşı', 'Aşı Tarihi', 'Yapılma durumu', 'Yapılma Tarihi'], ['1001', 'Ayşe Yılmaz', '05321112233', '5001', 'Luna', 'KEDİ KARMA 1', '01.12.2026', 'Yapılmadı', '']],
        'satis' => [['İşlem No', 'Tarih', 'Müşteri', 'Açıklama / İçerik', 'Toplam Tutar', 'Tahsilat', 'İşlem Durumu'], ['70001', '22.11.2026', 'Ayşe Yılmaz (1001)', 'Genel Muayene,Kuduz Aşısı', '1500', '1500', 'Ödenmiş']],
    ][$kind] ?? [];
    $fh = fopen('php://temp', 'r+');
    fwrite($fh, "\xEF\xBB\xBF");
    foreach ($tpl as $r) fputcsv($fh, $r, ';', '"', '');
    rewind($fh);
    return (string)stream_get_contents($fh);
}
