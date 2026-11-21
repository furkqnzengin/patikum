<?php
/**
 * Otomatik Hatırlatıcılar — cron/run.php tarafından periyodik çalıştırılır.
 * Her hatırlatma benzersiz bir ref_key ile kaydedilir; aynı hatırlatma iki kez gönderilmez.
 */

function reminders_generate(): array
{
    $stats = ['asi' => 0, 'gecikme' => 0, 'randevu' => 0, 'dogum_gunu' => 0, 'borc' => 0];
    $today = today();

    // 1) Yaklaşan aşı hatırlatmaları (ör. 3 ve 1 gün önce, 0 = aynı gün)
    if (setting('rem_vaccine_enabled', '1') === '1') {
        $days = array_unique(array_map('intval', array_filter(explode(',', setting('rem_vaccine_days', '3,1')), 'is_numeric')));
        foreach ($days as $d) {
            $target = date('Y-m-d', strtotime("$today +$d days"));
            $list = rows("SELECT v.id, v.due_date, t.name AS asi, p.name AS hasta, p.customer_id FROM vaccinations v
                          JOIN vaccine_types t ON t.id = v.vaccine_type_id JOIN patients p ON p.id = v.patient_id
                          WHERE v.status = 'planlandi' AND v.due_date = ? AND p.status = 'aktif'", [$target]);
            foreach ($list as $v) {
                $when = $d === 0 ? 'bugün' : ($d === 1 ? 'yarın' : "$d gün sonra");
                if (wa_queue((int)$v['customer_id'], null, 'asi_hatirlatma', ['hasta' => $v['hasta'], 'asi' => $v['asi'], 'tarih' => dt($v['due_date']), 'zaman' => $when], "vrem:{$v['id']}:$d")) $stats['asi']++;
            }
        }
    }

    // 2) Geciken aşılar
    $od = (int)setting('rem_vaccine_overdue_days', '3');
    if ($od > 0 && setting('rem_vaccine_enabled', '1') === '1') {
        $target = date('Y-m-d', strtotime("$today -$od days"));
        $list = rows("SELECT v.id, v.due_date, t.name AS asi, p.name AS hasta, p.customer_id FROM vaccinations v
                      JOIN vaccine_types t ON t.id = v.vaccine_type_id JOIN patients p ON p.id = v.patient_id
                      WHERE v.status = 'planlandi' AND v.due_date = ? AND p.status = 'aktif'", [$target]);
        foreach ($list as $v) {
            if (wa_queue((int)$v['customer_id'], null, 'asi_gecikme', ['hasta' => $v['hasta'], 'asi' => $v['asi'], 'tarih' => dt($v['due_date'])], "vlate:{$v['id']}")) $stats['gecikme']++;
        }
    }

    // 3) Randevu hatırlatmaları (X saat içinde başlayacak onaylı randevular)
    if (setting('rem_appt_enabled', '1') === '1') {
        $h = max(1, (int)setting('rem_appt_hours', '24'));
        $list = rows("SELECT a.*, p.name AS hasta FROM appointments a LEFT JOIN patients p ON p.id = a.patient_id
                      WHERE a.status = 'onayli' AND a.reminded_at IS NULL AND a.start_at > ? AND a.start_at <= ?",
            [now(), date('Y-m-d H:i:s', time() + $h * 3600)]);
        foreach ($list as $a) {
            $vars = ['hasta' => $a['hasta'] ?: ($a['pet_info'] ?: 'dostumuz'), 'tarih' => dtt($a['start_at']), 'saat' => tm($a['start_at']), 'islem' => $a['type']];
            $id = $a['customer_id']
                ? wa_queue((int)$a['customer_id'], null, 'randevu_hatirlatma', $vars, "arem:{$a['id']}")
                : wa_queue(null, $a['guest_phone'], 'randevu_hatirlatma', $vars + ['musteri' => $a['guest_name']], "arem:{$a['id']}");
            update('appointments', ['reminded_at' => now()], (int)$a['id']);
            if ($id) $stats['randevu']++;
        }
    }

    // 4) Doğum günü kutlaması
    if (setting('rem_birthday', '1') === '1') {
        $md = date('m-d');
        $year = date('Y');
        foreach (rows("SELECT id, name, birth_date, customer_id FROM patients WHERE status = 'aktif' AND birth_date IS NOT NULL") as $p) {
            if (substr($p['birth_date'], 5, 5) === $md && substr($p['birth_date'], 0, 4) !== $year) {
                if (wa_queue((int)$p['customer_id'], null, 'dogum_gunu', ['hasta' => $p['name'], 'yas' => age_text($p['birth_date'])], "bday:{$p['id']}:$year")) $stats['dogum_gunu']++;
            }
        }
    }

    // 5) Borç hatırlatma (haftanın belirli günü)
    if (setting('rem_debt_enabled', '0') === '1' && (int)date('N') === (int)setting('rem_debt_weekday', '1')) {
        $min = (float)setting('rem_debt_min', '100');
        $week = date('o-W');
        foreach (rows('SELECT c.id, ' . balance_sql() . ' AS bakiye FROM customers c WHERE c.active = 1') as $c) {
            if ((float)$c['bakiye'] >= $min) {
                if (wa_queue((int)$c['id'], null, 'borc_hatirlatma', ['borc' => money($c['bakiye'])], "debt:{$c['id']}:$week")) $stats['borc']++;
            }
        }
    }

    setting_set('rem_last_run', now());
    return $stats;
}
