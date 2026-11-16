<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$c = legal_company();
$phone = trim((string) ($c['phone'] ?? ''));
$mersis = trim((string) ($c['mersis'] ?? ''));
legal_page_open(
    'Ön Bilgilendirme ve Mesafeli Satış Sözleşmesi | Online İlahiyat',
    '6502 sayılı Kanun ve Mesafeli Sözleşmeler Yönetmeliği m.5 uyarınca ön bilgilendirme formu ile mesafeli satış sözleşmesi.',
    'Ön bilgilendirme ve mesafeli satış sözleşmesi'
);
?>
<p class="mt-4 text-muted">İşbu metin, <?= e($c['site']) ?> üzerinden kurulan mesafeli sözleşmeler için 6502 sayılı Tüketicinin Korunması Hakkında Kanun ve Mesafeli Sözleşmeler Yönetmeliği (RG 27.11.2014/29188; 23.08.2022, 24.05.2025 değişiklikleri dâhil) hükümlerine göre düzenlenmiştir. A bölümü Yönetmelik m.5 ön bilgilendirmesidir; B bölümü mesafeli satış sözleşmesidir. Ödeme öncesi elektronik onay, ön bilgilerin verildiğinin teyididir (Yönetmelik m.5/6, m.7).</p>
<?php legal_company_block(); ?>

<h2>A. Ön bilgilendirme formu (Yönetmelik m.5)</h2>

<h3>A.1. Satıcı / sağlayıcı</h3>
<ul>
  <li>Unvan: <?= e($c['unvan']) ?></li>
  <li>Ticari marka: <?= e($c['brand']) ?></li>
  <li>Açık adres: <?= e($c['address']) ?></li>
  <li>Vergi kimlik no: <?= e($c['vergi_no']) ?> (<?= e($c['vergi_daire']) ?>)</li>
  <?php if ($mersis !== ''): ?><li>MERSİS: <?= e($mersis) ?></li><?php else: ?><li>MERSİS: Yönetmelik m.5/1-a uyarınca MERSİS numarası veya vergi kimlik numarası yeterlidir; vergi kimlik numarası yukarıdadır. MERSİS numarası tescil kaydından öğrenilip bu alana eklenebilir.</li><?php endif; ?>
  <li>E-posta: <?= e($c['email']) ?></li>
  <li>Telefon: <?= $phone !== '' ? e($phone) : 'Yönetmelik m.5/1-b telefon numarası zorunlu unsurdur. Numara sitede henüz ayrı satırda yayımlanmamışsa tüketici, e-posta ve iletişim formu üzerinden 7 gün irtibat kurar; Satıcı talebi makul sürede cevaplar. Numara yayımlanır yayımlanmaz bu madde güncellenir.' ?></li>
  <li>İnternet sitesi: <?= e($c['site']) ?></li>
  <li>İletişim formu: <?= e(rtrim($c['site'], '/')) ?>/iletisim</li>
</ul>

<h3>A.2. Mal ve hizmetin temel nitelikleri</h3>
<p>Satışa konu edimler Site’de ve sipariş özetinde gösterilen program üyeliği, canlı ders, ders kaydı, dijital içerik ve/veya basılı kitaptır. Süre, adet, dijital veya basılı oluş, grup ve teslim şekli sipariş anındaki üründe yazıldığı gibidir.</p>

<h3>A.3. Bedel, vergiler ve ek masraflar</h3>
<p>Peşin satış fiyatı, vergiler dâhil olarak ürün sayfasında ve ödeme adımında gösterilir. Kampanya fiyatı belirtilen süreyle sınırlıdır. Basılı kitapta kargo ücreti, Site’de ilan edilen kurala göre ayrıca gösterilir; gösterilmeyen bir ücret sipariş sonrası yüklenmez. Dijital içerik ve eğitim üyeliğinde kargo yoktur. İnternet bağlantısı, cihaz ve elektrik tüketiciye aittir.</p>

<h3>A.4. Ödeme, ifa ve teslim</h3>
<p>Ödeme <?= e($c['odeme']) ?> üzerinden kart ile, 3D Secure kullanılarak alınır. Kart verisi Satıcı sunucusunda saklanmaz. Dijital edim, ödemenin onayından sonra panele tanımlanır. Basılı mal, siparişin Satıcı’ya ulaşmasından itibaren taahhüt edilen sürede ve her hâlde 30 günü aşmamak üzere (kişiselleştirilmiş mal hariç) teslim edilir (Yönetmelik m.16). Teslimat adresi Alıcı’nın bildirdiği adrestir.</p>

<h3>A.5. Cayma hakkı — özet</h3>
<p>Tüketici, malın tesliminden, hizmette sözleşmenin kurulmasından itibaren 14 gün içinde herhangi bir gerekçe göstermeksizin ve cezai şart ödemeksizin cayabilir. Süre, malın tüketiciye veya belirlediği üçüncü kişiye tesliminde başlar. Cayma bildirimi süre dolmadan <?= e($c['email']) ?> veya iletişim formu ile, kalıcı veri saklayıcısı niteliğindeki yazıyla yapılır. Tüketici malı, bildirimin Satıcı’ya ulaşmasından itibaren 14 gün içinde <?= e($c['iade_kargo']) ?> ile geri gönderir; bu taşıyıcı kullanıldığında iade kargo ücreti Satıcı’ya aittir. Bedel iadesinin 14 günlük süresi, teslim edilmiş malla ilgili olarak malın bu taşıyıcıya verildiği tarihten (başka taşıyıcıda Satıcı’ya ulaştığı tarihten), hizmette ise bildirimin ulaşmasından başlar. Ayrıntı ve form <?= legal_link('iptal-iade', 'Cayma, İptal ve İade Şartları') ?>’ndadır.</p>
<p>Cayma hakkı, Yönetmelik m.15’teki istisnalarda — özellikle elektronik ortamda anında ifa edilen hizmetler ve anında teslim gayri maddi mallar (m.15/ğ) ile tüketicinin onayıyla ifasına başlanan hizmetler (m.15/h) ve koruyucu ambalajı açılmış basılı kitaplar (m.15/e) — kullanılamaz. m.15/ğ ve m.15/h ancak önceden bilgi verilmiş ve açık talep / onay alınmışsa uygulanır. Ödeme sayfasındaki dijital onay kutusu bu açık taleptir.</p>
<p>Ön bilgilendirme usulüne aykırılıkta cayma süresi bir yıl uzar (Kanun m.48, Yönetmelik m.6).</p>

<h3>A.6. Şikâyet ve uyuşmazlık</h3>
<p>Şikâyet: <?= e($c['email']) ?> ve iletişim formu. 6502 sayılı Kanun m.68 uyarınca, Ticaret Bakanlığı’nın her yıl ilan ettiği parasal sınırlar içinde tüketicinin yerleşim yeri veya işlemin yapıldığı yerdeki tüketici hakem heyetine başvurulabilir. Bu sınırın üzerindeki uyuşmazlıklarda tüketici mahkemesi yetkilidir. Tüketici mahkemesinde dava açılmadan önce 6502 sayılı Kanun m.73/A uyarınca arabulucuya başvuru şarttır.</p>

<h3>A.7. Dijital içerik ve teknik şartlar</h3>
<p>Canlı ders ve kayıtlar tarayıcı üzerinden (gerekirse Zoom veya Cloudflare Stream) izlenir. Güncel tarayıcı, kamerası/mikrofonu olan bir cihaz ve yeterli internet hızı gerekir. Kayıtlar kural olarak panelde yayınlanır; indirme hakkı ayrıca belirtilmedikçe verilmez. İçerik, Alıcı’nın kişisel kullanımı içindir; kopyalama, yayın ve hesap paylaşımı yasaktır. Dijital içeriğin işleyişine ilişkin teknik koruma (oturum, yetki) uygulanır.</p>

<h3>A.8. Satış sonrası</h3>
<p>Eğitim desteği panel ve e-posta ile; basılı kitapta ayıplı mal hükümleri 6502 sayılı Kanun m.8 ve devamı ile 6098 sayılı TBK’ya tabidir. Ayıplı malda cayma istisnaları tüketici aleyhine uygulanmaz.</p>

<h2>B. Mesafeli satış sözleşmesi</h2>

<h3>B.1. Taraflar</h3>
<p><b>Satıcı / sağlayıcı:</b> <?= e($c['unvan']) ?>.</p>
<p><b>Alıcı / tüketici:</b> Site üzerinden üye olan veya sipariş veren, siparişte adı, adresi, telefonu ve e-postası yer alan kişi.</p>

<h3>B.2. Konu</h3>
<p>Alıcı’nın sipariş ettiği mal veya hizmetin satışı, ifası ve varsa teslimi ile tarafların hak ve yükümlülükleri. Sitede ilan edilen fiyat, güncellenene kadar geçerlidir. Fiyatlara KDV dâhildir.</p>

<h3>B.3. Genel hükümler</h3>
<p>B.3.1. Alıcı, A bölümündeki ön bilgileri sipariş öncesi okuduğunu ve elektronik ortamda teyit ettiğini kabul eder.</p>
<p>B.3.2. Basılı ürün, 30 günlük yasal süreyi aşmamak kaydıyla bildirilen adrese teslim edilir. Süre içinde teslim edilmezse Alıcı sözleşmeyi feshedebilir; Satıcı teslimat masrafları dâhil tahsil ettiği bedeli, fesih bildiriminin ulaşmasından itibaren 14 gün içinde kanuni faiziyle iade eder (Yönetmelik m.16).</p>
<p>B.3.3. Dijital kitap ve eğitim üyeliği ödemenin onayından sonra panele tanımlanır. Canlı ders takvimi ve teknik koşullar panelde belirtilen esaslara göre yürür.</p>
<p>B.3.4. Edimin ifası imkânsızlaşırsa Satıcı durumu öğrendiği tarihten itibaren 3 gün içinde bildirir ve 14 gün içinde bedeli iade eder. Stokta yokluk, imkânsızlık sayılmaz.</p>
<p>B.3.5. Bedel ödenmez veya banka kaydında iptal edilirse Satıcı’nın ifa yükümlülüğü doğmaz.</p>
<p>B.3.6. Teslimden sonra kartın yetkisiz kullanımı tespit edilir ve bedel Satıcı’ya intikal etmezse Alıcı, basılı ürünü 3 gün içinde, nakliye Satıcı’ya ait olmak üzere iade eder.</p>
<p>B.3.7. Mücbir sebep hâlinde Satıcı durumu bildirir. Alıcı siparişi iptal edebilir, emsali ile değiştirebilir veya teslimatı erteleyebilir. İptalde tahsilat 14 gün içinde iade yoluna konur. Kart iadesinin hesaba yansıması bankanın işlem süresine bağlıdır.</p>
<p>B.3.8. Alıcı, basılı ürünü teslim almadan muayene eder; ezik, kırık veya ambalajı bozulmuş gönderiyi kargo görevlisi önünde tutanakla teslim almaz.</p>
<p>B.3.9. Alıcı hesabını paylaşamaz; ders kaydı, not ve dijital içeriği izinsiz kopyalayamaz, yayamaz.</p>
<p>B.3.10. Kişisel veriler <?= legal_link('kvkk', 'KVKK Aydınlatma Metni') ?> ve <?= legal_link('gizlilik', 'Gizlilik Politikası') ?> kapsamında işlenir.</p>

<h3>B.4. Cayma ve iade</h3>
<p><?= legal_link('iptal-iade', 'Cayma, İptal ve İade Şartları') ?> bu sözleşmenin ayrılmaz parçasıdır. İade kargo: <?= e($c['iade_kargo']) ?>.</p>

<h3>B.5. Ödeme</h3>
<p>Tahsilat <?= e($c['odeme_kisa']) ?> lisanslı ödeme kuruluşu aracılığıyladır. Taksit, bankanın ve Satıcı’nın açtığı imkânla sınırlıdır.</p>

<h3>B.6. Uyuşmazlık ve yürürlük</h3>
<p>A.6’daki hakem heyeti, arabuluculuk ve tüketici mahkemesi kuralları geçerlidir. Alıcı ödemeyi tamamladığında A ve B bölümlerini kabul etmiş sayılır. Satıcı, sipariş öncesinde elektronik onay kaydı tutar.</p>
<p class="legal-note">Satıcı: <?= e($c['unvan']) ?> · <?= e($c['address']) ?> · <?= e($c['email']) ?></p>
<?php legal_page_close();
