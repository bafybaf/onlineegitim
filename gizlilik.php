<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$c = legal_company();
legal_page_open(
    'Gizlilik Politikası | Online İlahiyat',
    'Online İlahiyat gizlilik politikası. 6698 sayılı KVKK, 5651 sayılı Kanun ve 6563 sayılı ETK kapsamında veri, güvenlik ve üçüncü taraf hizmetler.',
    'Gizlilik politikası'
);
?>
<p class="mt-4 text-muted"><?= e($c['site']) ?> ve www alt alanı üzerinden sunulan üyelik, uzaktan eğitim, dijital içerik ve basılı kitap satışı <?= e($c['unvan']) ?> tarafından işletilir. Ticari marka: <?= e($c['brand']) ?>. Kişisel verilere ilişkin ayrıntılı aydınlatma <?= legal_link('kvkk', 'KVKK Aydınlatma Metni') ?>’ndedir; bu politika sitenin güvenlik, saklama, üçüncü taraf ve ticari ileti uygulamalarını tamamlar.</p>
<?php legal_company_block(); ?>

<h2>1. Kapsam</h2>
<p>Politika; ziyaretçi, üye, öğrenci, öğretmen, müşteri ve iletişim formu dolduranlar için geçerlidir. Canlı ders odası, ödeme sayfası ve panel aynı veri sorumlusu altındadır.</p>

<h2>2. Toplanan bilgiler</h2>
<p>Üyelik, sipariş, iletişim ve geri arama formları ile ad soyad, e-posta, telefon, teslimat/fatura adresi, sipariş ve eğitim kaydı (grup, yoklama, ödev, test, mesaj, katılım) işlenir. Ödeme sırasında kart verisi <?= e($c['odeme']) ?> altyapısına gider; kart numarası sitemizde tutulmaz.</p>

<h2>3. Kullanım amaçları</h2>
<p>Bilgiler yalnızca üyelik ve mesafeli satış sözleşmesinin ifası, canlı ders ve kayıt, fatura, kargo, teknik destek, bilgi güvenliği, tüketici talepleri ve kanuni yükümlülükler için kullanılır. Pazarlama iletisi, 6563 sayılı Kanun ve İYS onayı olmadan gönderilmez. Çerez analitiği ancak <?= legal_link('cerez-politikasi', 'Çerez Politikası') ?> ve çerez bandındaki açık rıza ile çalışır.</p>

<h2>4. Üçüncü taraflar</h2>
<p>Hizmetin sunulması için aşağıdaki bağımsız hizmet grupları kullanılabilir. Bunların kendi gizlilik politikaları da uygulanır:</p>
<ul>
  <li><?= e($c['odeme']) ?> — tahsilat ve 3D Secure</li>
  <li>Canlı ders, görüntü ve kayıt iletimi sağlayan yayın hizmetleri</li>
  <li>Barındırma ve e-posta hizmeti sağlayıcıları</li>
  <li>İsteğe bağlı hesap girişi ve (onayınız varsa) ziyaret istatistiği hizmetleri</li>
  <li>Kargo firmaları — yalnızca basılı sipariş teslimatı</li>
</ul>
<p>Sitedeki harici bağlantıların içerik ve gizlilik uygulamalarından Satıcı sorumlu değildir.</p>

<h2>5. Güvenlik</h2>
<p>Site bağlantısı şifreli iletilir. Oturum ve form güvenliği için zorunlu çerezler kullanılır; panel erişimi role göre sınırlanır. Bu tedbirler mutlak güvenlik taahhüdü değildir; olası bir ihlalde Kanun m.12 uyarınca Kurul’a ve ilgili kişiye bildirim yükümlülüğü varsa yerine getirilir.</p>
<p>Kart bilgisi e-posta, mesaj veya form ile gönderilmemelidir.</p>

<h2>6. İnternet ortamında kayıtların saklanması</h2>
<p>5651 sayılı İnternet Ortamında Yapılan Yayınların Düzenlenmesi ve Bu Yayınlar Yoluyla İşlenen Suçlarla Mücadele Edilmesi Hakkında Kanun ve ilgili yönetmelikler uyarınca trafik ve erişim kayıtları, yer sağlayıcı / içerik sağlayıcı yükümlülüğü doğduğu ölçüde yasal süreyle saklanabilir ve yetkili mercilere sunulabilir.</p>

<h2>7. Ticari elektronik ileti</h2>
<p>Sipariş onayı, fatura, teslimat, ders saati değişikliği ve hesabınıza ilişkin işlem bildirimleri sözleşme ifasıdır. Reklam ve kampanya iletileri ancak İYS’te onay ve KVKK açık rızası ile gönderilir. Onayı İYS veya <?= e($c['email']) ?> üzerinden geri alabilirsiniz.</p>

<h2>8. Çocuklar ve hesap güvenliği</h2>
<p>Hesap başkasıyla paylaşılmaz. 18 yaşından küçükler veli/vasi ile işlem yapmalıdır. Şifreler hash’lenerek saklanır; düz metin olarak tutulmaz.</p>

<h2>9. Haklar ve başvuru</h2>
<p>KVKK m.11 hakları ve başvuru usulü aydınlatma metnindedir. Gizlilik soruları için <?= e($c['email']) ?>.</p>

<h2>10. Değişiklik</h2>
<p>Politika sitede yayımlandığı anda yürürlüğe girer. Önemli değişikliklerde güncelleme tarihi yenilenir.</p>
<p class="legal-note">Bu metin, <?= legal_link('kvkk', 'KVKK Aydınlatma Metni') ?>, <?= legal_link('cerez-politikasi', 'Çerez Politikası') ?> ve <?= legal_link('mesafeli-satis', 'Mesafeli Satış Sözleşmesi') ?> ile birlikte okunur.</p>
<?php legal_page_close();
