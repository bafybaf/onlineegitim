<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$c = legal_company();
legal_page_open(
    'KVKK Aydınlatma Metni | Online İlahiyat',
    '6698 sayılı KVKK kapsamında Online İlahiyat kişisel veri aydınlatma metni ve açık rıza onayı.',
    'KVKK aydınlatma metni'
);
?>
<p class="mt-4 text-muted">6698 sayılı Kişisel Verilerin Korunması Kanunu m.10 uyarınca veri sorumlusu sıfatıyla sizi bilgilendiririz. Formlarda ve satın alımda bu metni onaylamanız istenir.</p>
<?php legal_company_block(); ?>

<h2>Veri sorumlusu</h2>
<p><?= e($c['unvan']) ?> — <?= e($c['brand']) ?>. Başvurular: <?= e($c['email']) ?> · <?= e($c['phone']) ?> · <?= e($c['address']) ?>.</p>

<h2>İşlenen kişisel veriler</h2>
<ul>
  <li>Kimlik ve iletişim: ad soyad, e-posta, telefon</li>
  <li>Müşteri işlemi: üyelik, sipariş, fatura, teslimat adresi</li>
  <li>Eğitim: grup kaydı, yoklama, ödev, test, mesaj, canlı ders katılımı</li>
  <li>İşlem güvenliği: IP, oturum, çerez, cihaz bilgisi</li>
  <li>Ödeme: Sipay üzerinden işlem kaydı (kart numarası bizde saklanmaz)</li>
</ul>

<h2>Amaç ve hukuki sebep</h2>
<p>Veriler; üyelik ve mesafeli satış sözleşmesinin kurulması/ifası, eğitim hizmetinin sunulması, fatura ve sipariş, müşteri desteği, bilgi güvenliği, yasal yükümlülükler ve meşru menfaat (kötüye kullanımın önlenmesi) amaçlarıyla işlenir. Hukuki sebepler: KVKK m.5/2-a, c, ç, e, f. Pazarlama iletileri ancak açık rızanızla gönderilir.</p>

<h2>Aktarım</h2>
<p>Veriler, hizmetin gerektirdiği ölçüde ödeme kuruluşuna (<?= e($c['odeme']) ?>), barındırma/e-posta altyapısına, kargo firmasına (basılı sipariş) ve canlı yayın sağlayıcısına aktarılabilir. Yasal zorunluluk halinde yetkili kurumlara bildirilir.</p>

<h2>Saklama</h2>
<p>Veriler, işleme amacının ve ilgili mevzuattaki zamanaşımı sürelerinin gerektirdiği süre kadar saklanır; sonra silinir, yok edilir veya anonim hale getirilir.</p>

<h2>Haklarınız</h2>
<p>KVKK m.11 uyarınca verilerinizin işlenip işlenmediğini öğrenme, düzeltme, silme, aktarıldığı üçüncü kişileri bilme, itiraz ve zararın giderilmesini talep etme haklarınız vardır. Taleplerinizi <?= e($c['email']) ?> adresine iletebilirsiniz. Başvurular Veri Sorumlusuna Başvuru Usul ve Esasları Hakkında Tebliğ’e göre yanıtlanır.</p>
<p class="mt-8"><?= legal_link('gizlilik', 'Gizlilik politikası') ?> · <?= legal_link('cerez-politikasi', 'Çerez politikası') ?></p>
<?php legal_page_close();
