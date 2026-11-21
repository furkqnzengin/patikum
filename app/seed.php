<?php
/** Varsayılan veriler: hayvan kategorileri, aşı protokolleri, WhatsApp şablonları, ayarlar */

function schema_statements(string $driver): array
{
    $sql = file_get_contents(APP . '/schema.sql');
    $sql = preg_replace('/^--.*$/m', '', $sql);
    if ($driver === 'sqlite') {
        $sql = str_replace('INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
        $sql = preg_replace('/\)\s*ENGINE=[^;]*;/', ');', $sql);
        $sql = str_replace(' UNSIGNED', '', $sql);
    }
    return array_filter(array_map('trim', explode(';', $sql)));
}

function seed_defaults(): void
{
    $species = [
        ['Kedi', '🐱', '#f59e0b'], ['Köpek', '🐶', '#0ea5e9'], ['Kuş', '🦜', '#22c55e'], ['Tavşan', '🐰', '#ec4899'],
        ['Kemirgen', '🐹', '#a855f7'], ['Sürüngen', '🦎', '#14b8a6'], ['Balık', '🐠', '#3b82f6'], ['Büyükbaş / Diğer', '🐾', '#64748b'],
    ];
    $sid = [];
    foreach ($species as $i => [$n, $ic, $col]) $sid[$n] = insert('species', ['name' => $n, 'icon' => $ic, 'color' => $col, 'sort' => $i]);

    $breeds = [
        'Kedi' => ['Tekir', 'British Shorthair', 'Scottish Fold', 'Van Kedisi', 'Ankara Kedisi', 'Persian (İran)', 'Siyam', 'Maine Coon', 'Bengal', 'Sfenks', 'Melez'],
        'Köpek' => ['Golden Retriever', 'Labrador', 'Alman Çoban', 'Kangal', 'Pomeranian', 'Poodle', 'Yorkshire Terrier', 'Chihuahua', 'Beagle', 'Husky', 'Maltese Terrier', 'French Bulldog', 'Melez'],
        'Kuş' => ['Muhabbet Kuşu', 'Sultan Papağanı', 'Kanarya', 'Cennet Papağanı', 'Jako'],
        'Tavşan' => ['Hollanda Lop', 'Aslan Baş', 'Hollanda Cüce'],
        'Kemirgen' => ['Hamster', 'Guinea Pig', 'Chinchilla'],
    ];
    foreach ($breeds as $sp => $list) foreach ($list as $b) insert('breeds', ['species_id' => $sid[$sp], 'name' => $b]);

    // Aşı türleri ve protokolleri: [ad, ilk yaş (hafta), doz, doz arası gün, rapel gün]
    $protocols = [
        'Kedi' => [
            ['İç Parazit', 6, 1, 0, 90], ['Dış Parazit', 8, 1, 0, 30], ['Kedi Karma (FVRCP)', 8, 2, 21, 365],
            ['Lösemi (FeLV)', 10, 2, 21, 365], ['Kuduz', 12, 1, 0, 365],
        ],
        'Köpek' => [
            ['İç Parazit', 6, 1, 0, 90], ['Dış Parazit', 8, 1, 0, 30], ['Köpek Karma (DHPPi+L)', 6, 3, 21, 365],
            ['Corona (CCV)', 6, 2, 21, 365], ['Bronşin (KC)', 8, 1, 0, 365], ['Kuduz', 12, 1, 0, 365],
        ],
        'Tavşan' => [['İç Parazit', 8, 1, 0, 90], ['Tavşan Karma (Myxo-RHD)', 10, 1, 0, 365]],
        'Kuş' => [['İç Parazit', 8, 1, 0, 180], ['Dış Parazit', 8, 1, 0, 60]],
        'Kemirgen' => [['İç Parazit', 6, 1, 0, 180]],
    ];
    foreach ($protocols as $sp => $list) {
        foreach ($list as $i => [$n, $age, $doses, $int, $boost]) {
            $vt = insert('vaccine_types', ['species_id' => $sid[$sp], 'name' => $n, 'active' => 1]);
            insert('vaccine_protocols', ['species_id' => $sid[$sp], 'vaccine_type_id' => $vt, 'first_age_weeks' => $age, 'doses' => $doses, 'interval_days' => $int ?: 21, 'booster_days' => $boost, 'sort' => $i]);
        }
    }

    $tpls = [
        ['randevu_onay', 'Randevu Onayı', "Merhaba {musteri} 🐾\n{hasta} için *{tarih}* tarihli *{islem}* randevunuz oluşturulmuştur.\nDeğişiklik için bize bu numaradan yazabilirsiniz.\n{klinik} · {telefon}"],
        ['randevu_hatirlatma', 'Randevu Hatırlatma', "Merhaba {musteri} 🐾\n{hasta} için *{tarih}* tarihindeki {islem} randevunuzu hatırlatmak isteriz.\nGörüşmek üzere!\n{klinik}"],
        ['asi_hatirlatma', 'Aşı Hatırlatma', "Merhaba {musteri} 💉\n{hasta} isimli dostumuzun *{asi}* aşısı {zaman} (*{tarih}*) yapılmalı.\nRandevu için bize yazabilir ya da arayabilirsiniz: {telefon}\n{klinik}"],
        ['asi_gecikme', 'Geciken Aşı', "Merhaba {musteri},\n{hasta} için {tarih} tarihinde yapılması gereken *{asi}* aşısı henüz yapılmadı. Sağlığı için en kısa sürede kliniğimize uğramanızı rica ederiz. 🐾\n{klinik} · {telefon}"],
        ['asi_yapildi', 'Aşı Yapıldı Bilgisi', "Merhaba {musteri} ✅\n{hasta} için *{asi}* aşısı {tarih} tarihinde yapıldı.\nBir sonraki aşı: *{sonraki}*\nTüm geçmişi müşteri panelinizden görebilirsiniz: {panel}\n{klinik}"],
        ['dogum_gunu', 'Doğum Günü Kutlama', "🎉 İyi ki doğdun {hasta}! 🎂\nSevgili {musteri}, dostumuzun {yas} oldu. Nice sağlıklı ve mutlu yıllara! 🐾\n{klinik}"],
        ['borc_hatirlatma', 'Bakiye Hatırlatma', "Merhaba {musteri},\n{klinik} cari hesabınızda *{borc}* bakiye bulunmaktadır. Detayları müşteri panelinizden inceleyebilirsiniz: {panel}\nBilginize sunarız."],
        ['satis_bilgi', 'Satış / İşlem Bilgisi', "Merhaba {musteri},\n{tarih} tarihli işleminizin tutarı: *{tutar}*\nGüncel bakiyeniz: {borc}\nTeşekkür ederiz 🐾 {klinik}"],
        ['tahsilat_bilgi', 'Tahsilat Bilgisi', "Merhaba {musteri},\n*{tutar}* tutarındaki ödemeniz alınmıştır. Kalan bakiyeniz: {borc}\nTeşekkür ederiz 🐾 {klinik}"],
        ['panel_bilgi', 'Müşteri Paneli Bilgisi', "Merhaba {musteri} 🐾\n{klinik} müşteri paneliniz hazır! Dostlarınızın aşı takvimini, yapılan işlemleri ve hesap durumunuzu buradan takip edebilirsiniz:\n{panel}\nGiriş: {giris}\nŞifre: {sifre}"],
        ['web_randevu_alindi', 'Web Randevu Talebi Alındı', "Merhaba {musteri} 🐾\n{tarih} için randevu talebiniz alındı. Ekibimiz en kısa sürede onay için size dönüş yapacak.\n{klinik}"],
    ];
    foreach ($tpls as [$code, $name, $body]) {
        insert('wa_templates', ['code' => $code, 'name' => $name, 'body' => $body, 'auto_send' => in_array($code, ['satis_bilgi', 'borc_hatirlatma'], true) ? 0 : 1, 'active' => 1]);
    }

    $settings = [
        'clinic_name' => 'Patikum Veteriner Kliniği', 'clinic_slogan' => 'Patili dostlarınız için sevgiyle, bilimle.',
        'clinic_phone' => '0 (212) 000 00 00', 'clinic_whatsapp' => '905000000000', 'clinic_email' => 'info@patikum.com',
        'clinic_address' => 'Örnek Mah. Patili Sok. No:1, İstanbul', 'clinic_hours' => "Hafta içi: 09:00 - 20:00\nCumartesi: 10:00 - 18:00\nPazar: Acil hizmet",
        'clinic_instagram' => 'patikumveteriner', 'clinic_map' => '',
        'wa_driver' => 'manuel', 'wa_send_instant' => '1', 'wa_cloud_version' => 'v21.0', 'wa_cloud_lang' => 'tr',
        'rem_vaccine_enabled' => '1', 'rem_vaccine_days' => '3,1', 'rem_vaccine_overdue_days' => '3',
        'rem_appt_enabled' => '1', 'rem_appt_hours' => '24', 'rem_birthday' => '1',
        'rem_debt_enabled' => '0', 'rem_debt_min' => '100', 'rem_debt_weekday' => '1',
        'web_appointments' => '1', 'service_vat' => '20',
        'cron_key' => bin2hex(random_bytes(12)), 'einv_prefix' => 'PTK', 'einv_provider' => 'manuel', 'einv_city' => 'İstanbul',
        'appointment_types' => 'Muayene,Aşı,Kontrol,Operasyon,Tıraş / Bakım,Tahlil,Diş Bakımı,Diğer',
        'product_categories' => 'Aşı,İlaç,Mama,Parazit Ürünü,Aksesuar,Bakım Ürünü,Hizmet,Sarf Malzeme',
        'expense_categories' => 'Kira,Maaş,Elektrik/Su/Doğalgaz,Tedarikçi Ödemesi,Vergi/SGK,Kırtasiye,Bakım/Onarım,Diğer',
    ];
    foreach ($settings as $k => $v) setting_set($k, $v);

    // Örnek hizmet ve ürünler
    $prods = [
        ['Hizmet', 'Genel Muayene', 0, 750, 20, 1], ['Hizmet', 'Kontrol Muayenesi', 0, 400, 20, 1], ['Hizmet', 'Tıraş / Bakım', 0, 900, 20, 1],
        ['Aşı', 'Kedi Karma Aşısı', 350, 900, 10, 0], ['Aşı', 'Köpek Karma Aşısı', 380, 950, 10, 0], ['Aşı', 'Kuduz Aşısı', 150, 600, 10, 0],
        ['Parazit Ürünü', 'İç Parazit Tableti', 60, 250, 10, 0], ['Parazit Ürünü', 'Dış Parazit Damla', 120, 450, 10, 0],
        ['Mama', 'Yetişkin Kedi Maması 1,5 kg', 400, 650, 1, 0],
    ];
    foreach ($prods as [$cat, $n, $buy, $sell, $vat, $svc]) {
        insert('products', ['category' => $cat, 'name' => $n, 'buy_price' => $buy, 'sell_price' => $sell, 'vat_rate' => $vat, 'is_service' => $svc, 'stock' => $svc ? 0 : 20, 'critical_stock' => $svc ? 0 : 5, 'unit' => 'Adet']);
    }
    // Aşı türlerini ürünlerle eşleştir
    $map = ['Kedi Karma (FVRCP)' => 'Kedi Karma Aşısı', 'Köpek Karma (DHPPi+L)' => 'Köpek Karma Aşısı', 'Kuduz' => 'Kuduz Aşısı', 'İç Parazit' => 'İç Parazit Tableti', 'Dış Parazit' => 'Dış Parazit Damla'];
    foreach ($map as $vt => $pn) {
        $pid = val('SELECT id FROM products WHERE name = ?', [$pn]);
        q('UPDATE vaccine_types SET product_id = ? WHERE name = ?', [$pid, $vt]);
    }
}

/** Demo verisi (isteğe bağlı) — arayüzü denemek için */
function seed_demo(): void
{
    $kedi = (int)val("SELECT id FROM species WHERE name = 'Kedi'");
    $kopek = (int)val("SELECT id FROM species WHERE name = 'Köpek'");
    $people = [
        ['Ayşe Yılmaz', '05321112233', [['Luna', $kedi, 'British Shorthair', 'disi', '-14 months'], ['Paşa', $kedi, 'Tekir', 'erkek', '-10 weeks']]],
        ['Mehmet Demir', '05334445566', [['Karabaş', $kopek, 'Kangal', 'erkek', '-3 years']]],
        ['Zeynep Kaya', '05447778899', [['Şirin', $kedi, 'Van Kedisi', 'disi', '-9 weeks'], ['Max', $kopek, 'Golden Retriever', 'erkek', '-2 years']]],
        ['Can Öztürk', '05051234567', [['Boncuk', $kopek, 'Pomeranian', 'disi', '-7 months']]],
    ];
    foreach ($people as [$n, $ph, $pets]) {
        $cid = insert('customers', ['name' => $n, 'phone' => $ph, 'city' => 'İstanbul', 'wa_optin' => 1, 'panel_password' => password_hash('123456', PASSWORD_DEFAULT)]);
        foreach ($pets as [$pn, $sp, $br, $g, $age]) {
            $pid = insert('patients', ['customer_id' => $cid, 'species_id' => $sp, 'breed' => $br, 'name' => $pn, 'gender' => $g, 'birth_date' => date('Y-m-d', strtotime($age)), 'weight' => $sp === $kedi ? 4.2 : 18.5]);
            $p = row('SELECT * FROM patients WHERE id = ?', [$pid]);
            foreach (vaccine_plan($p, date('Y-m-d', strtotime('-20 days'))) as $v) {
                if ($v['checked']) insert('vaccinations', ['patient_id' => $pid, 'vaccine_type_id' => $v['vaccine_type_id'], 'dose_label' => $v['dose_label'], 'due_date' => $v['due_date'], 'status' => 'planlandi']);
            }
        }
        $muayene = row("SELECT * FROM products WHERE name = 'Genel Muayene'");
        $sale = sale_create(['customer_id' => $cid, 'sale_date' => date('Y-m-d H:i:s', strtotime('-' . random_int(1, 40) . ' days')), 'paid' => 500, 'source' => 'satis'],
            [['product_id' => $muayene['id'], 'description' => $muayene['name'], 'qty' => 1, 'unit_price' => $muayene['sell_price'], 'vat_rate' => 20]]);
    }
    $first = row('SELECT * FROM patients ORDER BY id LIMIT 1');
    insert('appointments', ['customer_id' => $first['customer_id'], 'patient_id' => $first['id'], 'start_at' => date('Y-m-d 14:30:00'), 'type' => 'Kontrol', 'status' => 'onayli']);
    insert('appointments', ['customer_id' => $first['customer_id'], 'patient_id' => $first['id'], 'start_at' => date('Y-m-d 11:00:00', strtotime('+2 days')), 'type' => 'Aşı', 'status' => 'onayli']);
    insert('appointments', ['guest_name' => 'Elif Arslan', 'guest_phone' => '05559998877', 'pet_info' => 'Kedi - Minnoş', 'start_at' => date('Y-m-d 16:00:00', strtotime('+1 day')), 'type' => 'Muayene', 'status' => 'talep', 'source' => 'web']);
    insert('expenses', ['category' => 'Kira', 'description' => 'Klinik kirası', 'amount' => 25000, 'exp_date' => date('Y-m-01'), 'method' => 'havale']);
}
