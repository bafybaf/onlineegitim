<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$c = legal_company();
legal_page_open(
    'Cayma, İptal ve İade Şartları | Online İlahiyat',
    '6502 sayılı Kanun ve Mesafeli Sözleşmeler Yönetmeliği uyarınca 14 günlük cayma, 14 günlük iade ve bedel iadesi, m.15 istisnaları ve örnek cayma formu.',
    'Cayma, iptal ve iade şartları'
);
?>
<p class="mt-4 text-muted">Bu sayfa <?= legal_link('mesafeli-satis', 'Ön Bilgilendirme ve Mesafeli Satış Sözleşmesi') ?>’nin ayrılmaz parçasıdır. Süreler Mesafeli Sözleşmeler Yönetmeliği’nin güncel metnine (m.9–15) göredir. Eski uygulamadaki 10 günlük satıcı iadesi kullanılmaz; bedel iadesi 14 gündür.</p>
<?php legal_company_block(); ?>

<h2>1. Cayma hakkı</h2>
<p>Tüketici, 14 gün içinde herhangi bir gerekçe göstermeksizin ve cezai şart ödemeksizin sözleşmeden cayabilir.</p>
<ul>
  <li><b>Mal (basılı kitap):</b> süre, malın tüketiciye veya belirlediği üçüncü kişiye tesliminde başlar. Birden fazla mal varsa son teslimattan başlar.</li>
  <li><b>Hizmet (üyelik, canlı ders):</b> süre, sözleşmenin kurulmasında başlar.</li>
</ul>
<p>Tüketici, malı cayma süresi içinde ancak niteliğine, işleyişine ve işleyişine uygun bir şekilde inceleyebilir; giyim emsalindeki “deneme” basılı kitapta sayfaları okuyup tüketmek değildir.</p>
<p>Cayma bildirimi süre dolmadan kalıcı veri saklayıcısı ile yapılır: <?= e($c['email']) ?> veya sitedeki iletişim formu. Aşağıdaki form kullanılabilir; form zorunlu şekil şartı değildir, açık irade yeterlidir.</p>
<p>Satıcı, tüketicinin cayma hakkını kullandığına dair teyidi derhâl iletir.</p>

<h2>2. Satıcının bedeli iade yükümlülüğü (14 gün)</h2>
<p>Satıcı, varsa teslimat masrafları da dâhil tahsil ettiği tüm ödemeleri, tüketicinin satın alırken kullandığı ödeme aracına uygun olarak ve tüketiciye masraf yüklemeden tek seferde iade eder (Yönetmelik m.12). 14 günlük süre:</p>
<ul>
  <li><b>Teslimden sonra basılı mal:</b> malın iade için öngörülen taşıyıcıya (<?= e($c['iade_kargo']) ?>) teslim edildiği tarihten başlar. Tüketici başka taşıyıcı kullanırsa süre, malın Satıcı’ya ulaştığı tarihten başlar.</li>
  <li><b>Teslimden önce cayma veya hizmet sözleşmesi:</b> bildirimin Satıcı’ya ulaştığı tarihten başlar.</li>
</ul>
<p>Kart iadesinin hesaba yansıması bankanın işlem süresine bağlıdır; Satıcı’nın yükümlülüğü tutarın 14 gün içinde ödeme kuruluşuna aktarılmasıdır.</p>

<h2>3. Tüketicinin malı geri göndermesi (14 gün)</h2>
<p>Tüketici, cayma bildiriminin Satıcı’ya ulaşmasından itibaren 14 gün içinde malı Satıcı’ya veya yetkilendirdiği kişiye geri gönderir (Yönetmelik m.13). İade adresi: <?= e($c['address']) ?>.</p>
<p><b>Öngörülen iade taşıyıcısı:</b> <?= e($c['iade_kargo']) ?>. Bu taşıyıcı kullanıldığında iade kargo ücreti Satıcı’ya aittir. Tüketici başka bir taşıyıcı seçerse iade masrafı tüketiciye ait olabilir (Yönetmelik m.12/4–5).</p>
<p>Tüketici, malın değerinde kusuruyla bir azalma olursa bunu karşılar. Cayma hakkı kapsamında malı usulüne uygun incelemeden doğan değişiklik ve bozulmalardan sorumlu değildir.</p>

<h2>4. Cayma hakkının kullanılamayacağı hâller (Yönetmelik m.15)</h2>
<p>Taraflarca aksi kararlaştırılmadıkça tüketici özellikle şu sözleşmelerde cayamaz. İstisna, ancak ilgili şartlar oluşmuşsa uygulanır:</p>
<ul>
  <li>Tüketicinin istekleri veya kişisel ihtiyaçları doğrultusunda hazırlanan mallar (m.15/b).</li>
  <li>Tesliminden sonra koruyucu unsurları (ambalaj, bant, mühür, paket) açılmış olan mallardan; iadesi sağlık ve hijyen açısından uygun olmayanlar (m.15/ç).</li>
  <li>Tesliminden sonra koruyucu unsurları açılmış olması hâlinde <b>maddi ortamda sunulan kitap</b>, dijital içerik ve bilgisayar sarf malzemesi (m.15/e). Ambalajı açılmamış basılı kitap bu istisnanın dışındadır; 14 günlük cayma uygulanır.</li>
  <li>Elektronik ortamda anında ifa edilen hizmetler veya tüketiciye anında teslim edilen gayri maddi mallar (m.15/ğ) — <b>ancak</b> tüketici önceden bu hususta bilgilendirilmiş ve ifanın başlamasını açıkça talep / onaylamışsa.</li>
  <li>Cayma süresi sona ermeden tüketicinin onayı ile ifasına başlanan hizmetler (m.15/h) — aynı şekilde ön bilgi + açık onay şarttır.</li>
</ul>
<p>Canlı ders, panel üyeliği, ders kaydı ve dijital kitapta ödemenin ardından erişim açılması ifanın başlamasıdır. Bu edimler için ödeme adımındaki dijital onay kutusu işaretlenmişse m.15/ğ ve m.15/h uygulanır ve cayma kullanılamaz. Kutu işaretlenmeden ifaya başlanmaz. Ayıplı ifa, hiç ifa etmeme veya yanıltıcı ön bilgi hâlinde yasal haklar saklıdır.</p>

<h2>5. Ayıplı mal ve hizmet</h2>
<p>6502 sayılı Kanun m.8 ve devamı saklıdır. Ayıplı basılı kitap veya vaat edilen eğitimin esaslı unsurlarının sunulmaması durumunda tüketici bedel iadesi, ayıpsız misli ile değişim veya hizmetin yeniden ifasını talep edebilir. m.15 istisnaları ayıplı edime kalkan olamaz.</p>

<h2>6. Satıcının süresinde teslim etmemesi</h2>
<p>Basılı mal, 30 günü (kişiselleştirilmiş mal hariç) aşmamak üzere teslim edilir. Teslim edilmezse tüketici sözleşmeyi feshedebilir; bedel 14 gün içinde kanuni faiziyle iade edilir. Edim imkânsızlaşırsa Satıcı 3 gün içinde bildirir ve 14 gün içinde iade eder.</p>

<h2>7. Yetkisiz kart kullanımı</h2>
<p>Teslimden sonra kartın yetkisiz kullanıldığı ve bedelin Satıcı’ya intikal etmediği tespit edilirse Alıcı ürünü 3 gün içinde, nakliye Satıcı’ya ait olmak üzere iade eder.</p>

<h2>8. Mücbir sebep</h2>
<p>Mücbir sebep bildirilir. Tüketici iptal, emsal ürün veya erteleme seçebilir. İptalde bedel 14 gün içinde iade yoluna konur.</p>

<h2>9. Örnek cayma formu</h2>
<p>Yönetmelik ekindeki forma uygun örnek:</p>
<div class="legal-firm mt-4 rounded-2xl border border-[#e5e5e7] bg-soft p-5 text-sm">
  <p><b>Alıcı:</b> <?= e($c['unvan']) ?></p>
  <p><?= e($c['address']) ?></p>
  <p><?= e($c['email']) ?></p>
  <p class="mt-4">İşbu form ile …………………… tarihinde sipariş ettiğim / teslim aldığım …………………… (mal/hizmet ve sipariş no) satışına ilişkin mesafeli sözleşmeden cayma hakkımı kullandığımı bildiririm.</p>
  <p class="mt-3">Ad soyad:</p>
  <p>Adres:</p>
  <p>Telefon / e-posta:</p>
  <p>Sipariş tarihi / teslim tarihi:</p>
  <p>Tarih ve imza (kâğıt üzerinde gönderiliyorsa):</p>
</div>

<h2>10. Uyuşmazlık</h2>
<p>Tüketici hakem heyeti (Kanun m.68), tüketici mahkemesi ve dava şartı arabuluculuk (Kanun m.73/A) <?= legal_link('mesafeli-satis', 'ön bilgilendirme') ?>’deki esaslarla uygulanır.</p>
<p class="legal-note">Ödeme kuruluşu: <?= e($c['odeme']) ?>. Kart numarası sistemimizde saklanmaz.</p>
<?php legal_page_close();
