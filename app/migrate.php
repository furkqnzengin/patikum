<?php
/**
 * Veritabanı güncellemeleri — yeni sürüm yüklendiğinde ilk açılışta bir kez, otomatik çalışır.
 * Mevcut verilere dokunmaz; yalnızca yeni tablo/sütun ekler ve varsayılan aşı akışını kurar.
 */

const DB_VERSION = 2;

function db_migrate(): void
{
    try {
        $cur = (int)setting('db_version', '1');
    } catch (Throwable $e) {
        return; // kurulum yapılmamış
    }
    if ($cur >= DB_VERSION) return;
    $sqlite = db_driver() === 'sqlite';

    if ($cur < 2) {
        // Ürünlerde etki süresi (gün)
        try {
            q('SELECT effect_days FROM products LIMIT 1');
        } catch (Throwable $e) {
            db()->exec('ALTER TABLE products ADD COLUMN effect_days INT NULL');
        }
        // Aşı akışı kuralları
        db()->exec($sqlite
            ? 'CREATE TABLE IF NOT EXISTS vaccine_flow (id INTEGER PRIMARY KEY AUTOINCREMENT, species_id INTEGER NULL, from_type_id INTEGER NOT NULL, from_dose VARCHAR(5) NOT NULL DEFAULT \'*\', to_type_id INTEGER NOT NULL, to_dose VARCHAR(5) NULL, days INT NOT NULL DEFAULT 15, sort INT NOT NULL DEFAULT 0, active TINYINT NOT NULL DEFAULT 1, created_at DATETIME NULL)'
            : 'CREATE TABLE IF NOT EXISTS vaccine_flow (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, species_id INT UNSIGNED NULL, from_type_id INT UNSIGNED NOT NULL, from_dose VARCHAR(5) NOT NULL DEFAULT \'*\', to_type_id INT UNSIGNED NOT NULL, to_dose VARCHAR(5) NULL, days INT NOT NULL DEFAULT 15, sort INT NOT NULL DEFAULT 0, active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NULL, KEY idx_vf_from (from_type_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        if (!(int)val('SELECT COUNT(*) FROM vaccine_flow')) vaccine_flow_seed();
    }
    setting_set('db_version', (string)DB_VERSION);
}

/** Türkçe karşılaştırma anahtarı */
function vt_key(?string $s): string
{
    $s = str_replace(['İ', 'I'], ['i', 'ı'], (string)$s);
    $s = mb_strtolower($s, 'UTF-8');
    $s = strtr($s, ['ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u']);
    return preg_replace('/[^a-z0-9]+/', '', $s);
}

/** Tür içindeki aşı tanımını adının başına göre bulur, yoksa oluşturur */
function vaccine_type_ensure(string $name, string $prefix, int $speciesId): int
{
    foreach (rows('SELECT id, name FROM vaccine_types WHERE species_id = ? ORDER BY active DESC, id', [$speciesId]) as $t) {
        if (str_starts_with(vt_key($t['name']), $prefix)) return (int)$t['id'];
    }
    return insert('vaccine_types', ['species_id' => $speciesId, 'name' => $name, 'active' => 1]);
}

/**
 * Varsayılan aşı akışı (klinik protokolü):
 * Kedi: Karma 1 →15g→ Karma 2 →1 yıl→ Yıllık Karma; Karma 2 →15g→ Lösemi 1 →15g→ Lösemi 2 →1 yıl→ Yıllık Lösemi;
 *       Lösemi 2 →15g→ Kuduz →1 yıl→ Yıllık Kuduz.
 * Köpek: Karma 1 →15g→ Karma 2 →1 yıl→ Yıllık Karma; Karma 2 →15g→ Corona 1 →15g→ Corona 2 + Bronşin (KC) tek doz;
 *        Corona 2 →1 yıl→ Yıllık Corona; Bronşin →1 yıl; Kuduz →1 yıl.
 */
function vaccine_flow_seed(): void
{
    $kedi = (int)val("SELECT id FROM species WHERE name = 'Kedi'");
    $kopek = (int)val("SELECT id FROM species WHERE name = 'Köpek'");
    $rules = [];
    if ($kedi) {
        $karma = vaccine_type_ensure('Kedi Karma (FVRCP)', 'kedikarma', $kedi);
        $losemi = vaccine_type_ensure('Lösemi (FeLV)', 'losemi', $kedi);
        $kuduz = vaccine_type_ensure('Kuduz', 'kuduz', $kedi);
        array_push($rules,
            [$kedi, $karma, '1', $karma, '2', 15], [$kedi, $karma, '2', $karma, 'Y', 365], [$kedi, $karma, '2', $losemi, '1', 15], [$kedi, $karma, 'Y', $karma, 'Y', 365],
            [$kedi, $losemi, '1', $losemi, '2', 15], [$kedi, $losemi, '2', $losemi, 'Y', 365], [$kedi, $losemi, '2', $kuduz, '1', 15], [$kedi, $losemi, 'Y', $losemi, 'Y', 365],
            [$kedi, $kuduz, '*', $kuduz, 'Y', 365]
        );
        q('UPDATE vaccine_protocols SET interval_days = 15 WHERE species_id = ? AND vaccine_type_id IN (?, ?) AND interval_days = 21', [$kedi, $karma, $losemi]);
    }
    if ($kopek) {
        $karma = vaccine_type_ensure('Köpek Karma (DHPPi+L)', 'kopekkarma', $kopek);
        $corona = vaccine_type_ensure('Corona (CCV)', 'corona', $kopek);
        $bronsin = vaccine_type_ensure('Bronşin (KC)', 'bronsin', $kopek);
        $kuduz = vaccine_type_ensure('Kuduz', 'kuduz', $kopek);
        array_push($rules,
            [$kopek, $karma, '1', $karma, '2', 15], [$kopek, $karma, '2', $karma, 'Y', 365], [$kopek, $karma, '2', $corona, '1', 15], [$kopek, $karma, 'Y', $karma, 'Y', 365],
            [$kopek, $corona, '1', $corona, '2', 15], [$kopek, $corona, '1', $bronsin, 'T', 15], [$kopek, $corona, '2', $corona, 'Y', 365], [$kopek, $corona, 'Y', $corona, 'Y', 365],
            [$kopek, $bronsin, '*', $bronsin, 'Y', 365], [$kopek, $kuduz, '*', $kuduz, 'Y', 365]
        );
        q('UPDATE vaccine_protocols SET interval_days = 15 WHERE species_id = ? AND vaccine_type_id IN (?, ?) AND interval_days = 21', [$kopek, $karma, $corona]);
    }
    foreach ($rules as $i => [$sp, $from, $fd, $to, $td, $days]) {
        insert('vaccine_flow', ['species_id' => $sp, 'from_type_id' => $from, 'from_dose' => $fd, 'to_type_id' => $to, 'to_dose' => $td, 'days' => $days, 'sort' => $i, 'active' => 1]);
    }
}
