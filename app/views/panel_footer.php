</main>
<div class="modal fade" id="mPass" tabindex="-1"><div class="modal-dialog"><form method="post" action="<?= url('panel/') ?>" class="modal-content" style="border-radius:20px">
  <?= csrf_field() ?><input type="hidden" name="islem" value="sifre">
  <div class="modal-header"><h5 class="modal-title fw-bold">Şifre Değiştir</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-2"><label class="form-label">Mevcut şifre</label><input type="password" name="old" class="form-control" required></div>
    <div class="mb-2"><label class="form-label">Yeni şifre (en az 6 karakter)</label><input type="password" name="new" class="form-control" minlength="6" required></div>
  </div>
  <div class="modal-footer"><button class="btn btn-teal">Kaydet</button></div>
</form></div></div>
<footer class="text-center small text-muted pb-4">🐾 <?= e(setting('clinic_name')) ?> · <?= e(setting('clinic_phone')) ?></footer>
<script src="<?= asset('vendor/bootstrap.bundle.min.js') ?>"></script>
<?= kopuk_html('site', ['Merhaba! Ben Köpük 🐾', 'Hoş geldin! 😺', 'Dostunun aşı karnesi burada 💉', 'Yaklaşan aşıları sana WhatsApp\'tan hatırlatırız 💬', 'Randevu talebi oluşturmak için sağ üstteki düğmeyi kullanabilirsin 📅', 'Mrrr… 🐾']) ?>
</body>
</html>
