<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
public_head('KVKK Aydınlatma Metni | Online İlahiyat', 'Kişisel verilerin işlenmesi hakkında aydınlatma metni.');
?>
<main class="mx-auto max-w-3xl px-4 py-14 lg:px-8">
  <p class="badge">Yasal</p>
  <h1 class="font-display mt-4 text-4xl">KVKK aydınlatma metni</h1>
  <p class="mt-4 text-muted">6698 sayılı Kişisel Verilerin Korunması Kanunu kapsamında, Online İlahiyat olarak kişisel verilerinizi aşağıdaki amaçlarla işleriz.</p>
  <h2 class="font-display mt-8 text-2xl">Veri sorumlusu</h2>
  <p class="mt-2">Online İlahiyat · info@onlineilahiyat.com</p>
  <h2 class="font-display mt-8 text-2xl">İşlenen veriler</h2>
  <ul class="mt-2 list-disc pl-5 text-muted">
    <li>Kimlik ve iletişim: ad soyad, e-posta, telefon</li>
    <li>Eğitim: grup kaydı, yoklama, ödev, test sonucu, mesaj</li>
    <li>Ödeme: PayTR üzerinden kart işlemi (kart bilgisi bizde saklanmaz)</li>
    <li>Teslimat: kargo adresi (kitap siparişi)</li>
  </ul>
  <h2 class="font-display mt-8 text-2xl">Amaç ve hukuki sebep</h2>
  <p class="mt-2 text-muted">Üyelik sözleşmesinin ifası, canlı ders ve kayıt hizmeti, fatura/sipariş takibi, yasal yükümlülükler ve meşru menfaat (güvenlik, kötüye kullanımın önlenmesi).</p>
  <h2 class="font-display mt-8 text-2xl">Haklarınız</h2>
  <p class="mt-2 text-muted">KVKK m.11 uyarınca verilerinize erişme, düzeltme, silme ve itiraz taleplerinizi iletişim formundan iletebilirsiniz.</p>
  <p class="mt-8"><a class="font-extrabold text-navy" href="<?= e(page_url('gizlilik')) ?>">Gizlilik politikası →</a></p>
</main>
<?php public_foot();
