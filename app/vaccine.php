<?php
/** Otomatik Aşı Sihirbazı ve aşı işlemleri */

/**
 * Hastanın türüne tanımlı aşı protokollerine göre önerilen aşı takvimini hesaplar.
 * Doğum tarihi biliniyorsa ilk doz "ilk yaş (hafta)" kuralına göre, geçmişte kalıyorsa başlangıç tarihinden başlar.
 */
function vaccine_plan(array $patient, ?string $start = null): array
{
    $start = $start ?: today();
    $protos = rows('SELECT p.*, t.name AS vaccine_name FROM vaccine_protocols p JOIN vaccine_types t ON t.id = p.vaccine_type_id
                    WHERE p.species_id = ? AND p.active = 1 AND t.active = 1 ORDER BY p.sort, p.id', [(int)$patient['species_id']]);
    $plan = [];
    foreach ($protos as $p) {
        $recent = row("SELECT MAX(COALESCE(done_date, due_date)) AS d, COUNT(*) AS n FROM vaccinations
                       WHERE patient_id = ? AND vaccine_type_id = ? AND status IN ('yapildi','planlandi')", [(int)$patient['id'], (int)$p['vaccine_type_id']]);
        $hasHistory = (int)($recent['n'] ?? 0) > 0;

        if ($hasHistory && $p['booster_days']) {
            // Geçmişi olan aşı: son kayıttan itibaren rapel
            $next = date('Y-m-d', strtotime($recent['d'] . ' +' . (int)$p['booster_days'] . ' days'));
            if ($next < $start) $next = $start;
            $plan[] = ['vaccine_type_id' => $p['vaccine_type_id'], 'name' => $p['vaccine_name'], 'dose_label' => 'Rapel', 'due_date' => $next, 'checked' => false, 'note' => 'Daha önce kaydı var'];
            continue;
        }
        $first = $start;
        if (!empty($patient['birth_date'])) {
            $byAge = date('Y-m-d', strtotime($patient['birth_date'] . ' +' . (int)$p['first_age_weeks'] . ' weeks'));
            if ($byAge > $first) $first = $byAge;
        }
        $last = $first;
        for ($d = 1; $d <= max(1, (int)$p['doses']); $d++) {
            $date = date('Y-m-d', strtotime($first . ' +' . (($d - 1) * (int)$p['interval_days']) . ' days'));
            $last = $date;
            $plan[] = ['vaccine_type_id' => $p['vaccine_type_id'], 'name' => $p['vaccine_name'], 'dose_label' => ((int)$p['doses'] > 1 ? $d . '. Doz' : 'Tek Doz'), 'due_date' => $date, 'checked' => true, 'note' => ''];
        }
        if ($p['booster_days']) {
            $plan[] = ['vaccine_type_id' => $p['vaccine_type_id'], 'name' => $p['vaccine_name'], 'dose_label' => 'Rapel',
                'due_date' => date('Y-m-d', strtotime($last . ' +' . (int)$p['booster_days'] . ' days')), 'checked' => true, 'note' => ''];
        }
    }
    usort($plan, fn($a, $b) => strcmp($a['due_date'], $b['due_date']));
    return $plan;
}

/**
 * Aşıyı "yapıldı" olarak işaretler: stok düşer, ücret varsa satış (borç) oluşur,
 * protokolde rapel süresi varsa bir sonraki aşı otomatik planlanır, WhatsApp bilgilendirmesi kuyruğa alınır.
 */
function vaccination_done(int $id, array $o = []): ?string
{
    $v = row('SELECT v.*, t.name AS vaccine_name, t.product_id AS type_product, p.customer_id, p.species_id, p.name AS patient_name
              FROM vaccinations v JOIN vaccine_types t ON t.id = v.vaccine_type_id JOIN patients p ON p.id = v.patient_id WHERE v.id = ?', [$id]);
    if (!$v || $v['status'] === 'yapildi') return null;
    $done = $o['done_date'] ?? today();
    $productId = nul($o['product_id'] ?? null) ?? $v['product_id'] ?? $v['type_product'];
    $price = isset($o['price']) ? (float)$o['price'] : (float)$v['price'];
    if ($price <= 0 && $productId) $price = (float)val('SELECT sell_price FROM products WHERE id = ?', [$productId]);

    $saleId = null;
    if ($price > 0 || $productId) {
        $vat = $productId ? (int)val('SELECT vat_rate FROM products WHERE id = ?', [$productId]) : 10;
        $saleId = sale_create([
            'customer_id' => $v['customer_id'], 'patient_id' => $v['patient_id'], 'sale_date' => $done . ' ' . date('H:i:s'),
            'note' => 'Aşı: ' . $v['vaccine_name'], 'source' => 'asi', 'paid' => (float)($o['paid'] ?? 0), 'pay_method' => $o['pay_method'] ?? 'nakit',
        ], [['product_id' => $productId, 'description' => 'Aşı: ' . $v['vaccine_name'] . ' (' . $v['patient_name'] . ')', 'qty' => 1, 'unit_price' => $price, 'vat_rate' => $vat ?: 10]]);
    }
    update('vaccinations', [
        'status' => 'yapildi', 'done_date' => $done, 'product_id' => $productId, 'price' => $price,
        'batch_no' => nul($o['batch_no'] ?? null) ?? $v['batch_no'], 'sale_id' => $saleId, 'user_id' => $_SESSION['uid'] ?? null,
    ], $id);

    // Sonraki aşılar: aşı akışı kuralları + ürünün etki süresi
    $planned = vaccine_flow_apply($id, vaccine_flow_next($v, $done, $productId ? (int)$productId : null));
    $nextText = $planned ? implode(', ', array_map(fn($x) => $x['name'] . ($x['dose_label'] ? ' ' . $x['dose_label'] : '') . ' - ' . dt($x['due_date']), $planned)) : 'planlanmadı';
    // Sonraki herhangi bir aşı
    $anyNext = row("SELECT v.due_date, t.name FROM vaccinations v JOIN vaccine_types t ON t.id = v.vaccine_type_id WHERE v.patient_id = ? AND v.status = 'planlandi' ORDER BY v.due_date LIMIT 1", [$v['patient_id']]);
    wa_auto('asi_yapildi', (int)$v['customer_id'], [
        'hasta' => $v['patient_name'], 'asi' => $v['vaccine_name'], 'tarih' => dt($done),
        'sonraki' => $anyNext ? $anyNext['name'] . ' - ' . dt($anyNext['due_date']) : $nextText,
    ], 'vdone:' . $id);
    return $nextText;
}


/* ------------------------------------------------------------------ */
/* Otomatik aşı akışı                                                  */
/* ------------------------------------------------------------------ */

/** Doz etiketini koda çevirir: "1. Doz" → 1, "Yıllık Rapel" → Y, "Tek Doz" → T, boş → '' */
function vdose_code(?string $label): string
{
    $l = mb_strtolower(trim((string)$label), 'UTF-8');
    if ($l === '') return '';
    if (str_contains($l, 'yıllık') || str_contains($l, 'yillik') || str_contains($l, 'rapel')) return 'Y';
    if (str_contains($l, 'tek')) return 'T';
    if (preg_match('/^(\d)/', $l, $m)) return $m[1];
    return '';
}

function vdose_label(string $code): ?string
{
    return ['1' => '1. Doz', '2' => '2. Doz', '3' => '3. Doz', 'Y' => 'Yıllık', 'T' => 'Tek Doz'][$code] ?? null;
}

function vdose_options(bool $any = false): array
{
    return ($any ? ['*' => 'Herhangi bir doz'] : []) + ['1' => '1. Doz', '2' => '2. Doz', '3' => '3. Doz', 'T' => 'Tek Doz', 'Y' => 'Yıllık'];
}

/**
 * Yapılan aşıdan sonra planlanacak aşıları hesaplar (kaydetmez).
 * $v: vaccine_type_id, dose_label, species_id, patient_id, vaccine_name
 * Ürünün etki süresi (gün) girilmişse aynı aşının bir sonraki dozu o süreye göre planlanır.
 */
function vaccine_flow_next(array $v, string $doneDate, ?int $productId): array
{
    $code = vdose_code($v['dose_label'] ?? null);
    $eff = $productId ? (int)val('SELECT effect_days FROM products WHERE id = ?', [$productId]) : 0;
    $prodName = $productId && $eff ? (string)val('SELECT name FROM products WHERE id = ?', [$productId]) : '';
    $out = [];
    $rules = rows('SELECT f.*, t.name AS to_name FROM vaccine_flow f JOIN vaccine_types t ON t.id = f.to_type_id WHERE f.active = 1 AND f.from_type_id = ? ORDER BY f.sort, f.id', [(int)$v['vaccine_type_id']]);
    foreach ($rules as $r) {
        $fd = (string)$r['from_dose'];
        if (!($fd === '*' || $fd === $code || ($code === '' && in_array($fd, ['1', 'T'], true)))) continue;
        $days = (int)$r['days'];
        $why = $days . ' gün sonra (aşı akışı)';
        // Etki süresi yalnızca aynı aşının tekrarına (yıllık / aynı doz) uygulanır; 1. → 2. doz arası akıştaki süredir
        if ($eff > 0 && (int)$r['to_type_id'] === (int)$v['vaccine_type_id'] && ((string)$r['to_dose'] === 'Y' || (string)$r['to_dose'] === $code)) {
            $days = $eff;
            $why = $eff . ' gün sonra (' . $prodName . ' etki süresi)';
        }
        $out[] = ['vaccine_type_id' => (int)$r['to_type_id'], 'name' => $r['to_name'], 'dose_label' => vdose_label((string)$r['to_dose']),
            'due_date' => date('Y-m-d', strtotime($doneDate . ' +' . $days . ' days')), 'why' => $why, 'kind' => 'rule'];
    }
    if (!$out && $eff > 0) {
        $out[] = ['vaccine_type_id' => (int)$v['vaccine_type_id'], 'name' => $v['vaccine_name'], 'dose_label' => $v['dose_label'] ?: null,
            'due_date' => date('Y-m-d', strtotime($doneDate . ' +' . $eff . ' days')), 'why' => $eff . ' gün sonra (' . $prodName . ' etki süresi)', 'kind' => 'effect'];
    }
    if (!$out && !empty($v['species_id'])) {
        $booster = (int)val('SELECT booster_days FROM vaccine_protocols WHERE species_id = ? AND vaccine_type_id = ? AND active = 1 LIMIT 1', [$v['species_id'], $v['vaccine_type_id']]);
        if ($booster > 0) {
            $out[] = ['vaccine_type_id' => (int)$v['vaccine_type_id'], 'name' => $v['vaccine_name'], 'dose_label' => 'Rapel',
                'due_date' => date('Y-m-d', strtotime($doneDate . ' +' . $booster . ' days')), 'why' => $booster . ' gün sonra (rapel)', 'kind' => 'booster'];
        }
    }
    return $out;
}

/** Hesaplanan planı kaydeder; aynı aşının aynı dozu zaten planlıysa yenisini açmaz, tarihini günceller. */
function vaccine_flow_apply(int $doneId, array $plan): array
{
    $pid = (int)val('SELECT patient_id FROM vaccinations WHERE id = ?', [$doneId]);
    $res = [];
    foreach ($plan as $x) {
        $existing = rows("SELECT id, dose_label FROM vaccinations WHERE patient_id = ? AND vaccine_type_id = ? AND status = 'planlandi' AND id <> ? ORDER BY due_date", [$pid, $x['vaccine_type_id'], $doneId]);
        $same = null;
        foreach ($existing as $e) if (vdose_code($e['dose_label']) === vdose_code($x['dose_label'])) { $same = $e; break; }
        if (!$same && $x['kind'] !== 'rule' && $existing) $same = $existing[0];
        if ($same) {
            update('vaccinations', ['due_date' => $x['due_date'], 'dose_label' => $x['dose_label'] ?? $same['dose_label']], (int)$same['id']);
        } else {
            insert('vaccinations', ['patient_id' => $pid, 'vaccine_type_id' => $x['vaccine_type_id'], 'dose_label' => $x['dose_label'], 'due_date' => $x['due_date'], 'status' => 'planlandi', 'notes' => 'Otomatik planlandı']);
        }
        $res[] = $x;
    }
    return $res;
}
