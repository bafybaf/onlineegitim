<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$c = legal_company();
legal_page_open(
    'Mesafeli Satış Sözleşmesi | Online İlahiyat',
    'Online İlahiyat mesafeli satış sözleşmesi. Canlı ders, dijital içerik ve kitap siparişlerinde tarafların hak ve yükümlülükleri.',
    'Mesafeli satış sözleşmesi'
);
?>
<p class="mt-4 text-muted">İşbu sözleşme, <?= e($c['site']) ?> üzerinden verilen siparişler için 6502 sayılı Tüketicinin Korunması Hakkında Kanun ve Mesafeli Sözleşmeler Yönetmeliği hükümlerine göre düzenlenir. Ödemeyi tamamladığınızda aşağıdaki şartları kabul etmiş sayılırsınız.</p>
<?php legal_company_block(); ?>

<h2>1. Taraflar</h2>
<p><b>Satıcı:</b> <?= e($c['unvan']) ?> (ticari marka: <?= e($c['brand']) ?>).</p>
<p><b>Alıcı:</b> Site üzerinden üye olan veya sipariş veren, sipariş formunda ve hesabında adı, adresi, telefonu ve e-postası yer alan gerçek veya tüzel kişi.</p>
<p>Alıcı, siparişi onaylamakla ürün/hizmet bedelini ve varsa kargo, vergi gibi ek ücretleri ödeme yükümlülüğünü kabul eder.</p>

<h2>2. Tanımlar</h2>
<ul>
  <li><b>Site:</b> <?= e($c['site']) ?></li>
  <li><b>Mal:</b> Basılı kitap ve elektronik ortamda sunulan dijital kitap, ders kaydı, PDF not ve benzeri gayri maddi mallar.</li>
  <li><b>Hizmet:</b> Canlı ders, üyelik, kayıt izleme ve öğrenci paneli üzerinden sunulan eğitim hizmeti.</li>
  <li><b>Sipariş veren:</b> Site üzerinden mal veya hizmet talep eden kişi.</li>
</ul>

<h2>3. Konu</h2>
<p>Bu sözleşme, Alıcı’nın Site üzerinden sipariş ettiği mal veya hizmetin satışı, ifası ve varsa teslimi ile tarafların hak ve yükümlülüklerini düzenler. Sitede ilan edilen fiyatlar, güncellenene kadar geçerlidir. Süreli kampanya fiyatları belirtilen süre sonuna kadar geçerlidir. Fiyatlara KDV dâhildir.</p>

<h2>4. Satıcı bilgileri</h2>
<ul>
  <li>Unvan: <?= e($c['unvan']) ?></li>
  <li>Adres: <?= e($c['address']) ?></li>
  <li>E-posta: <?= e($c['email']) ?></li>
  <li>Vergi dairesi / no: <?= e($c['vergi_daire']) ?> · <?= e($c['vergi_no']) ?></li>
</ul>

<h2>5. Alıcı ve sipariş bilgileri</h2>
<p>Alıcı, teslimat ve fatura bilgileri sipariş anında hesapta, sepette ve ödeme kaydında yer alan ad, soyad, telefon, e-posta, teslimat adresi ve fatura adresidir. Alıcı bu bilgilerin doğru olduğunu kabul eder.</p>

<h2>6. Sözleşme konusu ürün / hizmet</h2>
<p>Mal ve hizmetin temel özellikleri (türü, süresi, adedi, dijital veya basılı oluşu) Site’de ve sipariş özetinde yayınlanır. Sözleşme konusu bedel, vergiler dâhil olarak sipariş özetinde ve ödeme sayfasında gösterilir. Basılı kitaplarda kargo ücreti, Site’de ilan edilen kurala göre Alıcı’ya yansıtılabilir; dijital ürün ve canlı ders üyeliklerinde kargo yoktur.</p>

<h2>7. Genel hükümler</h2>
<p>7.1. Alıcı, ürün/hizmetin temel nitelikleri, satış fiyatı, ödeme şekli ve teslimata ilişkin ön bilgileri okuduğunu ve elektronik ortamda teyit ettiğini kabul eder.</p>
<p>7.2. Basılı ürün, 30 günlük yasal süreyi aşmamak kaydıyla Alıcı’nın bildirdiği adrese teslim edilir. Süre içinde teslim edilmezse Alıcı sözleşmeyi feshedebilir.</p>
<p>7.3. Dijital kitap ve canlı ders üyeliği, ödemenin onayından sonra Alıcı’nın paneline tanımlanır. Canlı ders takvimi, grup yerleştirme ve teknik koşullar Site’de ve panelde belirtilen esaslara göre yürür.</p>
<p>7.4. Satıcı, sipariş konusu mal veya hizmetin ifasının imkânsızlaşması halinde durumu öğrendiği tarihten itibaren 3 gün içinde Alıcı’ya bildirir ve 14 gün içinde bedeli iade eder.</p>
<p>7.5. Bedel ödenmez veya banka kaydında iptal edilirse Satıcı’nın teslim ve ifa yükümlülüğü sona erer.</p>
<p>7.6. Teslimden sonra kartın yetkisiz kullanımı tespit edilir ve bedel Satıcı’ya ödenmezse Alıcı, basılı ürünü 3 gün içinde, nakliye Satıcı’ya ait olmak üzere iade eder.</p>
<p>7.7. Mücbir sebep halinde Satıcı durumu bildirir. Alıcı siparişi iptal edebilir, emsali ile değiştirebilir veya teslimatı erteleyebilir. İptalde nakit ödemeler 14 gün içinde; kart ödemeleri 14 gün içinde ilgili bankaya iade edilir. Bankanın hesaba yansıtması 2–3 haftayı bulabilir; bu süre banka işlemine bağlıdır.</p>
<p>7.8. Alıcı, basılı ürünü teslim almadan önce muayene eder; ezik, kırık veya ambalajı bozulmuş ürünü kargodan teslim almaz. Teslim alınan ürün hasarsız kabul edilir.</p>
<p>7.9. Alıcı, Site’yi kamu düzenine, genel ahlaka ve üçüncü kişilerin haklarına aykırı kullanamaz; hesabını başkasıyla paylaşamaz; ders kaydı, not ve dijital içeriği izinsiz kopyalayamaz, yayamaz.</p>

<h2>8. Cayma hakkı</h2>
<p>Alıcı; mal satışında teslimden, hizmette sözleşmenin kurulmasından itibaren 14 gün içinde gerekçe göstermeksizin cayabilir. Cayma masrafı Satıcı’ya aittir. Bildirim <?= e($c['email']) ?> adresine yazılı yapılır.</p>
<p>Cayma hakkı süresi dolmadan tüketicinin onayıyla ifasına başlanan hizmetlerde ve elektronik ortamda anında ifa edilen hizmetler ile anında teslim edilen gayri maddi mallarda (canlı ders, dijital kitap, ders kaydı, yazılım benzeri içerik) Yönetmelik gereği cayma hakkı kullanılamaz. Ambalajı açılmış basılı kitaplar da aynı kapsamda iade edilemez.</p>
<p>Ayrıntı için <?= legal_link('iptal-iade', 'İptal ve İade Şartları') ?> sayfasına bakınız.</p>

<h2>9. Ödeme</h2>
<p>Ödeme, <?= e($c['odeme']) ?> altyapısı ile kredi kartı / banka kartı üzerinden tek çekim veya satıcının açtığı taksit imkânı ile alınır. Kart bilgisi Site sunucusunda saklanmaz. 3D Secure ve SSL kullanılır.</p>

<h2>10. Uyuşmazlık</h2>
<p>Uyuşmazlıklarda 6502 sayılı Kanun m.68 uyarınca Ticaret Bakanlığınca her yıl ilan edilen parasal sınırlar dâhilinde, tüketicinin yerleşim yeri veya işlemin yapıldığı yerdeki tüketici hakem heyetine veya tüketici mahkemesine başvurulabilir.</p>

<h2>11. Yürürlük</h2>
<p>Alıcı, Site üzerinden ödemeyi gerçekleştirdiğinde işbu sözleşmenin tüm şartlarını kabul etmiş sayılır. Satıcı, sipariş öncesinde bu metnin okunup onaylandığına dair elektronik onay alır.</p>
<p class="mt-8 text-sm text-muted">Satıcı: <?= e($c['unvan']) ?></p>
<?php legal_page_close();
