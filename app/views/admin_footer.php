</main>
<footer class="pt-foot">
  <span>🐾 <?= e(setting('clinic_name', 'Patikum')) ?></span>
  <span>Patikum Klinik Yönetim v<?= PATIKUM_VERSION ?> · <?= date('Y') ?></span>
</footer>
</div>
</div>
<script src="<?= asset('vendor/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('vendor/tom-select.complete.min.js') ?>"></script>
<script src="<?= asset('vendor/chart.umd.min.js') ?>"></script>
<script src="<?= asset('js/admin.js') ?>"></script>
<?php if (!empty($pageScripts)) echo $pageScripts; ?>
<?php
$kTodayVac = (int)val("SELECT COUNT(*) FROM vaccinations v JOIN patients p ON p.id = v.patient_id WHERE v.status = 'planlandi' AND v.due_date = ? AND p.status = 'aktif'", [today()]);
$kFirst = isset($hdrUser['name']) ? explode(' ', (string)$hdrUser['name'])[0] : '';
echo kopuk_html('admin', [
    'Miyav! Ben Köpük, kliniğin kedisiyim 🐾',
    $kTodayVac ? 'Bugün ' . $kTodayVac . ' aşı randevumuz var 💉' : 'Bugün planlanmış aşı yok, keyifli bir gün 😺',
    !empty($notif['gecikenAsi']) ? $notif['gecikenAsi'] . ' geciken aşı var. Sahiplerine WhatsApp\'tan hatırlatalım mı? 💬' : null,
    !empty($notif['talep']) ? $notif['talep'] . ' yeni web randevu talebi onay bekliyor 📅' : null,
    !empty($notif['wa']) ? $notif['wa'] . ' WhatsApp mesajı gönderilmeyi bekliyor ✉️' : null,
    'Su içmeyi unutma 💧',
    'Mama kabıma bir bakar mısın? 🍗',
    'Bir mola ver, bir kedi sev 🐈',
    'Tüyüm döküldüyse kusura bakma 😅',
], $kFirst ? 'Merhaba ' . $kFirst . '! 😺' : null);
?>
</body>
</html>
