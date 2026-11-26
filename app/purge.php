<?php
/**
 * Toplu veri temizleme (Ayarlar → Verileri Temizle). Yalnızca yönetici kullanır.
 * Ayarlar, kullanıcılar, hayvan kategorileri, aşı tanımları ve aşı akışı hiçbir zaman silinmez.
 */

function purge_groups(): array
{
    return [
        'musteri' => ['Tüm müşteriler', 'people', 'Müşterilerle birlikte hayvanları, aşıları, muayene kayıtları, satışları, tahsilatları, randevuları ve WhatsApp mesajları da silinir.',
            'SELECT COUNT(*) FROM customers'],
        'hasta' => ['Tüm hastalar (hayvanlar)', 'heart-pulse', 'Hayvanlarla birlikte aşı ve muayene kayıtları silinir. Müşteriler ve satışlar kalır.',
            'SELECT COUNT(*) FROM patients'],
        'asi' => ['Tüm aşı kayıtları', 'eyedropper', 'Yapılan ve planlanan bütün aşılar silinir. Hastalar ve aşı tanımları kalır.',
            'SELECT COUNT(*) FROM vaccinations'],
        'urun' => ['Tüm ürünler ve stok', 'box-seam', 'Ürün/hizmet listesi ve stok hareketleri silinir. Geçmiş satışlarda kalem adları görünmeye devam eder.',
            'SELECT COUNT(*) FROM products'],
        'satis' => ['Tüm satışlar ve tahsilatlar', 'cart3', 'Satışlar, satış kalemleri, tahsilatlar ve e-fatura taslakları silinir; müşteri bakiyeleri sıfırlanır. Stok miktarları değişmez.',
            'SELECT COUNT(*) FROM sales'],
        'randevu' => ['Tüm randevular', 'calendar-heart', 'Randevular ve web randevu talepleri silinir.',
            'SELECT COUNT(*) FROM appointments'],
        'gider' => ['Tüm giderler', 'box-arrow-up', 'Muhasebedeki gider / ödeme kayıtları silinir.',
            'SELECT COUNT(*) FROM expenses'],
        'wa' => ['Tüm WhatsApp mesajları', 'whatsapp', 'Gönderilen ve kuyruktaki mesaj kayıtları silinir.',
            'SELECT COUNT(*) FROM wa_messages'],
    ];
}

function purge_count(string $sql): int
{
    try {
        return (int)val($sql);
    } catch (Throwable $e) {
        return 0;
    }
}

/** Seçilen grupları tek işlemde siler; her grup için silinen satır sayısını döndürür */
function purge_run(array $groups): array
{
    $all = array_keys(purge_groups());
    $groups = array_values(array_intersect($all, $groups));
    if (!$groups) return [];
    $has = fn(string $g) => in_array($g, $groups, true);
    $done = [];
    $x = fn(string $sql) => q($sql)->rowCount();

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($has('satis') || $has('musteri')) {
            $x('UPDATE vaccinations SET sale_id = NULL WHERE sale_id IS NOT NULL');
            $x('UPDATE examinations SET sale_id = NULL WHERE sale_id IS NOT NULL');
        }
        if ($has('satis')) {
            $x('DELETE FROM payments');
            $x('DELETE FROM invoices');
            $x('DELETE FROM sale_items');
            $done['satis'] = $x('DELETE FROM sales');
        }
        if ($has('musteri')) {
            $x('DELETE FROM payments WHERE customer_id IS NOT NULL');
            $x('DELETE FROM sale_items WHERE sale_id IN (SELECT id FROM sales WHERE customer_id IS NOT NULL)');
            $x('DELETE FROM invoices WHERE sale_id IN (SELECT id FROM sales WHERE customer_id IS NOT NULL)');
            $x('DELETE FROM sales WHERE customer_id IS NOT NULL');
            $x('DELETE FROM appointments WHERE customer_id IS NOT NULL OR patient_id IS NOT NULL');
            $x('DELETE FROM wa_messages WHERE customer_id IS NOT NULL');
            $x('DELETE FROM vaccinations');
            $x('DELETE FROM examinations');
            $x('UPDATE sales SET patient_id = NULL WHERE patient_id IS NOT NULL');
            $x('DELETE FROM patients');
            $done['musteri'] = $x('DELETE FROM customers');
        }
        if ($has('hasta') && !$has('musteri')) {
            $x('DELETE FROM vaccinations');
            $x('DELETE FROM examinations');
            $x('UPDATE sales SET patient_id = NULL WHERE patient_id IS NOT NULL');
            $x('UPDATE appointments SET patient_id = NULL WHERE patient_id IS NOT NULL');
            $done['hasta'] = $x('DELETE FROM patients');
        }
        if ($has('asi') && !$has('musteri') && !$has('hasta')) {
            $done['asi'] = $x('DELETE FROM vaccinations');
        }
        if ($has('urun')) {
            $x('UPDATE sale_items SET product_id = NULL WHERE product_id IS NOT NULL');
            $x('UPDATE vaccine_types SET product_id = NULL WHERE product_id IS NOT NULL');
            $x('UPDATE vaccinations SET product_id = NULL WHERE product_id IS NOT NULL');
            $x('DELETE FROM stock_movements');
            $done['urun'] = $x('DELETE FROM products');
        }
        if ($has('randevu')) $done['randevu'] = $x('DELETE FROM appointments');
        if ($has('gider')) $done['gider'] = $x('DELETE FROM expenses');
        if ($has('wa')) $done['wa'] = $x('DELETE FROM wa_messages');

        // Excel aktarım eşlemeleri: yerel kaydı silinenler temizlenir
        try {
            foreach (['musteri' => 'customers', 'hasta' => 'patients', 'urun' => 'products', 'asi' => 'vaccinations', 'satis' => 'sales'] as $k => $t) {
                q("DELETE FROM import_map WHERE kind = ? AND local_id NOT IN (SELECT id FROM $t)", [$k]);
            }
        } catch (Throwable $e) {
            // import_map henüz oluşturulmamış olabilir
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return $done;
}
