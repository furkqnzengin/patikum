# 🐾 Patikum — Veteriner Klinik Web Sitesi ve Yönetim Sistemi

Dışarıya normal bir veteriner kliniği web sitesi olarak görünen; arka planda tam bir klinik yönetim paneli ve hayvan sahipleri için müşteri paneli barındıran PHP + MySQL uygulaması. Framework ve Composer gerektirmez — cPanel paylaşımlı hostinge doğrudan yüklenir.

## Bölümler

| Adres | Kim için | İçerik |
|---|---|---|
| `/` | Ziyaretçiler | Klinik tanıtım sitesi, hizmetler, aşı programı, online randevu talebi, iletişim/harita, WhatsApp butonu |
| `/panel/` | Hayvan sahipleri | Telefon + şifre ile giriş: hayvanları, aşı karnesi, yapılan işlemler, ödemeler, kalan borç, randevu talebi |
| `/admin/` | Klinik personeli | Yönetim paneli (aşağıda) |

## Yönetim Paneli Modülleri

- **Genel Bakış** — müşteri/hasta/satış grafikleri, hızlı işlem butonları, bugünkü/yaklaşan/geciken aşılar ve randevular, son tahsilat/satışlar, kritik stok
- **Müşteri Yönetimi** — müşteri kartı, hesap ekstresi (borç/ödeme/bakiye), müşteri paneli şifresi oluşturma ve WhatsApp ile gönderme, KVKK/WhatsApp onayı
- **Hasta Yönetimi** — hayvan **kategorileri** (kedi, köpek, kuş, tavşan… + ırklar, yeni kategori eklenebilir), künye, kilo takibi grafiği, muayene/işlem geçmişi (ücret girilirse cari hesaba işlenir)
- **Randevu Takibi** — liste ve aylık takvim görünümü, web/panel talepleri, onay/tamamlandı/gelmedi, WhatsApp onay mesajı
- **Otomatik Aşı Sihirbazı** — hayvanın türü ve doğum tarihine göre protokolden aşı takvimi üretir; aşı "yapıldı" işaretlenince stoktan düşer, ücretlendirilir ve rapel otomatik planlanır. Protokoller (ilk yaş, doz sayısı, doz aralığı, rapel) düzenlenebilir
- **Otomatik Hatırlatıcılar** — aşıdan X gün önce, geciken aşı, randevudan X saat önce, doğum günü, haftalık bakiye hatırlatması
- **WhatsApp** — düzenlenebilir mesaj şablonları, kuyruk, toplu/kampanya mesajı. 3 gönderim modu:
  - *Manuel*: ücretsiz, personel tek tıkla `wa.me` ile gönderir
  - *Meta WhatsApp Cloud API*: resmi, tam otomatik
  - *3. parti WhatsApp API*: URL + token ile herhangi bir servis
- **Ürün Yönetimi / Stok** — ürün & hizmetler, barkod, KDV, kritik stok, SKT, stok giriş/çıkış/fire/sayım hareketleri
- **Satışlar** — barkod/ürün arama ile hızlı satış (POS), indirim, kısmi ödeme (kalan cariye yazılır), satış fişi, iptal (stok iadesi)
- **Muhasebe** — kasa özeti (ödeme yöntemine göre), tahsilatlar, giderler/ödemeler, cari hesaplar (borçlu müşteriler + toplu WhatsApp hatırlatma)
- **E-Dönüşüm** — satışlardan e-Arşiv / e-Fatura taslağı, UBL-TR 1.2 XML üretimi ve indirme, entegratör API'sine gönderim altyapısı
- **Gelişmiş Raporlar** — ciro, tahsilat, gider, brüt kâr, 12 aylık trend, tür dağılımı, en çok satanlar, aşı ve randevu istatistikleri, Excel (CSV) dışa aktarım
- **Kullanıcılar** — Yönetici / Veteriner / Personel rolleri

## cPanel'e Kurulum

1. **Veritabanı:** cPanel → *MySQL® Veritabanları* → yeni veritabanı + kullanıcı oluşturun, kullanıcıya veritabanında **TÜM YETKİLER**i verin.
2. **Dosyalar:** Bu depodaki dosyaları `public_html/` (veya bir alt alan adı klasörüne) yükleyin.
   - *Git ile:* cPanel → *Git™ Version Control* → *Create* → bu deponun URL'si ile klonlayın, ardından dosyaları `public_html`'e dağıtın (veya depoyu doğrudan `public_html` içine klonlayın).
   - *Elle:* GitHub'dan ZIP indirip cPanel *Dosya Yöneticisi* ile yükleyip açın.
3. **PHP sürümü:** cPanel → *MultiPHP Manager* → **PHP 8.1 veya üzeri** seçin (`pdo_mysql`, `mbstring`, `curl` eklentileri açık olmalı — genelde varsayılan açıktır).
4. **Kurulum sihirbazı:** Tarayıcıda `https://alanadiniz.com/install/` adresini açın, veritabanı bilgilerini ve yönetici hesabını girin. Sihirbaz tabloları oluşturur, varsayılan hayvan kategorilerini, aşı protokollerini ve WhatsApp şablonlarını ekler, `app/config.php` dosyasını yazar.
5. **Güvenlik:** Kurulum biter bitmez **`install/` klasörünü silin.**
6. **SSL:** cPanel → *SSL/TLS Status* ile AutoSSL'i etkinleştirin, sonra kök `.htaccess` içindeki HTTPS yönlendirme satırlarının başındaki `#` işaretlerini kaldırın.
7. **Cron (otomatik hatırlatıcılar):** cPanel → *Cron Jobs* → 15 dakikada bir:
   ```
   */15 * * * * php /home/KULLANICI_ADI/public_html/cron/run.php >/dev/null 2>&1
   ```
   Tam komut ve URL alternatifi yönetim panelinde *Diğer → Otomatik Hatırlatıcılar* sayfasında hazır olarak gösterilir.
8. **Ayarlar:** *Diğer → Ayarlar* bölümünden klinik bilgileri, harita, WhatsApp ve e-Dönüşüm (VKN, ünvan, vergi dairesi) bilgilerini doldurun.

> Elle kurulum tercih ederseniz: `app/schema.sql` dosyasını phpMyAdmin ile içe aktarın, `app/config.sample.php` dosyasını `app/config.php` olarak kopyalayıp düzenleyin. (Bu durumda varsayılan veriler eklenmez; sihirbaz önerilir.)


## Excel'den Veri Aktarımı (Kolayvet vb.)

*Diğer → Excel'den Veri Aktarımı* (yalnızca yönetici). Eski programdan alınan Excel listeleri önizlenip aktarılır:
Müşteri Listesi → Hasta Listesi → Ürün Listesi → Aşı Takvimi → Satış Geçmişi.

- Telefonlar 11 haneli `05XXXXXXXXX` biçiminde kaydedilir; hücredeki "Numarayı Ara / Whatsapp'tan yaz" yazıları alınmaz.
- Kayıtlar eski programdaki numaralarıyla eşlenir (`import_map` tablosu, ilk kullanımda otomatik oluşur); aynı dosya tekrar yüklenirse kayıtlar çoğalmaz.
- Müşteri bakiyeleri eski programdaki "kalan bakiye" ile birebir eşitlenir (gerekirse *Kolayvet devir* kaydı oluşturulur).
- Aktarım sırasında WhatsApp mesajı gönderilmez; her aktarım *Geri al* ile silinebilir.

## WhatsApp Kurulumu

- **Manuel (varsayılan):** Hiçbir ayar gerekmez. Otomatik oluşan mesajlar *WhatsApp Mesajları* kuyruğuna düşer, personel "Gönder"e basınca WhatsApp hazır mesajla açılır.
- **Meta Cloud API:** [Meta for Developers](https://developers.facebook.com/) üzerinden WhatsApp Business uygulaması oluşturun; *Phone Number ID* ve kalıcı *Access Token*'ı Ayarlar → WhatsApp'a girin. Müşteri son 24 saatte size yazmadıysa Meta yalnızca **onaylı şablon** gönderilmesine izin verir: Meta'da şablon oluşturup adını ve parametre sırasını (ör. `musteri,hasta,asi,tarih`) ilgili Patikum şablonuna yazın.
- **3. parti API:** Servisin gönderim URL'si, token'ı ve telefon/mesaj alan adlarını girin.

Ayarlar sayfasındaki **Test Et** ile bağlantıyı deneyebilirsiniz.

## E-Dönüşüm Notu

Sistem, satışlardan GİB'in UBL-TR 1.2 formatına uygun fatura XML'i üretir (e-Fatura mükellefi işaretli müşterilere *TEMELFATURA*, diğerlerine *EARSIVFATURA*). Faturanın resmi olarak iletilmesi için bir **GİB özel entegratörü** ile anlaşma gerekir:
- *Manuel mod:* XML'i indirip entegratörünüzün portalına yükleyin ve "Gönderildi" olarak işaretleyin.
- *Entegratör modu:* XML ayarlardaki API adresine gönderilir. Her entegratörün API'si farklı olduğundan `app/einvoice.php` içindeki `einvoice_send()` fonksiyonunun entegratörünüzün dokümanına göre uyarlanması gerekebilir. Canlı kullanımdan önce XML'i entegratörünüzün test ortamında doğrulayın.

## Yerel Geliştirme

```bash
php -S 127.0.0.1:8000
```
Ardından `http://127.0.0.1:8000/install/` → veritabanı olarak **SQLite (yerel test)** seçip "demo veri" kutusunu işaretleyin. Demo müşteri paneli girişi: `0532 111 22 33` / `123456`.

## Dizin Yapısı

```
index.php          Ana web sitesi
admin/             Yönetim paneli sayfaları
panel/             Müşteri paneli
app/               Çekirdek (web'den erişime kapalı): db, yardımcılar, WhatsApp, aşı, satış, e-fatura, şema
assets/            CSS, JS, görseller
cron/run.php       Zamanlanmış görev
install/           Kurulum sihirbazı (kurulumdan sonra silin)
```

## Güvenlik

- Şifreler `password_hash` (bcrypt) ile saklanır, tüm sorgular hazırlanmış ifadelerle (PDO) çalışır
- Tüm formlarda CSRF koruması, giriş denemelerinde kısıtlama
- `app/` klasörü `.htaccess` ile web erişimine kapalıdır; `app/config.php` git'e eklenmez
