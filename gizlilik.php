<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$c = legal_company();
legal_page_open(
    'Gizlilik Politikası | Online İlahiyat',
    'Online İlahiyat gizlilik ve güvenlik politikası. Üyelik, sipariş, ödeme ve çerez uygulamaları.',
    'Gizlilik politikası'
);
?>
<p class="mt-4 text-muted"><?= e($c['site']) ?> üzerinden verilen tüm hizmetler, <?= e($c['address']) ?> adresinde kayıtlı <?= e($c['unvan']) ?> tarafından işletilir. Ticari marka: <?= e($c['brand']) ?>.</p>
<?php legal_company_block(); ?>

<h2>Toplanan bilgiler</h2>
<p>Üyelik, sipariş, iletişim ve “sizi arayalım” formları ile ad soyad, e-posta, telefon, adres, fatura bilgisi ve eğitim kaydı (grup, yoklama, ödev, test, mesaj) işlenir. Ayrıntı <?= legal_link('kvkk', 'KVKK Aydınlatma Metni') ?>’ndedir.</p>

<h2>Kullanım amacı</h2>
<p>Bilgiler üyelik sözleşmesinin ifası, canlı ders ve kayıt hizmeti, sipariş/fatura, teknik destek, güvenlik ve yasal yükümlülükler için kullanılır. Kampanya ve yeni ürün bilgisini e-posta veya SMS ile almak istemezseniz <?= e($c['email']) ?> üzerinden bildirebilirsiniz.</p>
<p>Kişisel veriler, belirlenen amaçlar dışında üçüncü kişilere açıklanmaz. İstisnalar: mevzuat zorunluluğu, sözleşmenin ifası, yetkili idari/adli talep ve hakların korunması.</p>

<h2>IP ve güvenlik</h2>
<p>Hizmetin sunulması, kötüye kullanımın önlenmesi ve uyuşmazlıkların çözümü için IP adresi ve oturum kayıtları tutulabilir.</p>

<h2>Kredi kartı güvenliği</h2>
<p>Kart bilgisi sitemizde saklanmaz. Ödeme <?= e($c['odeme']) ?> altyapısı ve SSL / 3D Secure ile bankaya iletilir. Kart numarası e-posta ile gönderilmemelidir.</p>

<h2>Üçüncü taraf siteler</h2>
<p>Sitedeki dış bağlantıların gizlilik uygulamalarından Satıcı sorumlu değildir. Ödeme <?= e($c['odeme']) ?>, e-posta SMTP sağlayıcısı ve canlı yayın altyapısı (Cloudflare Stream) kendi gizlilik kurallarına tabidir.</p>

<h2>Çerezler</h2>
<p>Zorunlu çerezler oturum, sepet ve güvenlik için kullanılır. İsteğe bağlı analiz çerezleri ancak sizin onayınızla çalışır. Ayrıntı ve tercihleri <?= legal_link('cerez-politikasi', 'Çerez Politikası') ?> sayfasından yönetebilirsiniz.</p>

<h2>Değişiklik</h2>
<p>Bu politika sitede yayınlandığı tarihte yürürlüğe girer. Sorularınız için <?= e($c['email']) ?> adresine yazabilirsiniz.</p>
<?php legal_page_close();
