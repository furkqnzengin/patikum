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

    // Sonraki aşı (rapel) otomatik planlama
    $nextText = 'planlanmadı';
    $future = row("SELECT due_date FROM vaccinations WHERE patient_id = ? AND vaccine_type_id = ? AND status = 'planlandi' AND id <> ? ORDER BY due_date LIMIT 1",
        [$v['patient_id'], $v['vaccine_type_id'], $id]);
    if ($future) {
        $nextText = dt($future['due_date']);
    } else {
        $booster = val('SELECT booster_days FROM vaccine_protocols WHERE species_id = ? AND vaccine_type_id = ? AND active = 1 LIMIT 1', [$v['species_id'], $v['vaccine_type_id']]);
        if ($booster) {
            $next = date('Y-m-d', strtotime($done . ' +' . (int)$booster . ' days'));
            insert('vaccinations', ['patient_id' => $v['patient_id'], 'vaccine_type_id' => $v['vaccine_type_id'], 'dose_label' => 'Rapel', 'due_date' => $next, 'status' => 'planlandi']);
            $nextText = dt($next);
        }
    }
    // Sonraki herhangi bir aşı
    $anyNext = row("SELECT v.due_date, t.name FROM vaccinations v JOIN vaccine_types t ON t.id = v.vaccine_type_id WHERE v.patient_id = ? AND v.status = 'planlandi' ORDER BY v.due_date LIMIT 1", [$v['patient_id']]);
    wa_auto('asi_yapildi', (int)$v['customer_id'], [
        'hasta' => $v['patient_name'], 'asi' => $v['vaccine_name'], 'tarih' => dt($done),
        'sonraki' => $anyNext ? $anyNext['name'] . ' - ' . dt($anyNext['due_date']) : $nextText,
    ], 'vdone:' . $id);
    return $nextText;
}
