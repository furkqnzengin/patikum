</main>
<footer class="pt-foot text-center">
  🐾 <?= e(setting('clinic_name', 'Patikum')) ?> · Patikum Klinik Yönetim v<?= PATIKUM_VERSION ?> · <?= date('Y') ?>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="<?= asset('js/admin.js') ?>"></script>
<?php if (!empty($pageScripts)) echo $pageScripts; ?>
</body>
</html>
