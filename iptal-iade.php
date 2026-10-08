<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$c = legal_company();
legal_page_open(
    'İptal ve İade Şartları | Online İlahiyat',
    'Online İlahiyat iptal, iade ve cayma hakkı şartları. Canlı ders, dijital içerik ve basılı kitap siparişleri.',
    'İptal ve iade şartları'
);
?>
<p class="mt-4 text-muted">Site üzerinden sipariş verdiğinizde ön bilgilendirme ve <?= legal_link('mesafeli-satis', 'Mesafeli Satış Sözleşmesi') ?>’ni kabul etmiş sayılırsınız. Alıcılar, 6502 sayılı Kanun ve Mesafeli Sözleşmeler Yönetmeliği ile yürürlükteki diğer mevzuata tabidir.</p>
<?php legal_company_block(); ?>

<h2>Genel</h2>
<p>Basılı ürün, 30 günlük yasal süreyi aşmamak kaydıyla bildirilen adrese teslim edilir. Süre içinde teslim edilmezse Alıcı sözleşmeyi sona erdirebilir. Ürün, siparişte belirtilen niteliklere uygun teslim edilir.</p>
<p>Satın alınan ürünün satılması imkânsızlaşırsa Satıcı durumu öğrendiğinden itibaren 3 gün içinde bildirir ve 14 gün içinde bedeli iade eder.</p>
<p>Bedel ödenmez veya banka kaydında iptal edilirse Satıcı’nın teslim yükümlülüğü sona erer.</p>

<h2>Cayma hakkı</h2>
<p>Alıcı; malın kendisine veya gösterdiği adrese tesliminden itibaren 14 gün içinde, hiçbir gerekçe göstermeksizin malı reddederek sözleşmeden cayabilir. Hizmet sözleşmelerinde süre, sözleşmenin kurulmasından başlar.</p>
<p>Cayma bildirimi <?= e($c['email']) ?> adresine veya <?= e($c['phone']) ?> numarasına yazılı iletilir. Cayma hakkının kullanımından kaynaklanan masraflar Satıcı’ya aittir.</p>
<p>Cayma için 14 gün içinde yazılı bildirim ve ürünün kullanılmamış / aşağıda sayılan istisnalara girmemiş olması gerekir. İade edilecek basılı üründe fatura, ambalaj ve varsa aksesuar eksiksiz gönderilir.</p>
<p>Satıcı, cayma bildiriminin ulaşmasından itibaren en geç 10 gün içinde bedeli iade eder ve 20 gün içinde malı teslim alır. Alıcı’nın kusuruyla malın değerinde azalma olursa Alıcı kusuru oranında zararları karşılar. Usulüne uygun incelemeden doğan değişikliklerden Alıcı sorumlu değildir.</p>

<h2>Cayma hakkının kullanılamayacağı haller</h2>
<p>Yönetmelik gereği özellikle şu durumlarda cayma hakkı kullanılamaz:</p>
<ul>
  <li>Elektronik ortamda anında ifa edilen hizmetler veya tüketiciye anında teslim edilen gayri maddi mallar (canlı ders üyeliği, dijital kitap, ders kaydı, PDF not, yazılım ve benzeri içerik).</li>
  <li>Cayma süresi dolmadan tüketicinin onayıyla ifasına başlanan hizmetler.</li>
  <li>Ambalajı açılmış basılı kitap, kopyalanabilir yazılım, ses/görüntü kaydı ve benzeri ürünler.</li>
  <li>Alıcının isteğiyle kişiselleştirilen ve geri gönderilmeye elverişli olmayan mallar.</li>
  <li>Teslimden sonra sağlık ve hijyen nedeniyle iadesi uygun olmayan, ambalajı açılmış ürünler.</li>
</ul>
<p>Canlı ders ve dijital içerikte ödemenin ardından panele erişim açıldığı için ifa başlamış sayılır. Bu nedenle dijital ürün ve eğitim hizmetlerinde 14 günlük cayma kuralı uygulanmaz.</p>

<h2>Basılı kitap iadesi</h2>
<p>Ambalajı açılmamış, denenmemiş ve bozulmamış basılı kitaplar, teslimden itibaren 14 gün içinde iade edilebilir. Hasarlı kargo teslim alınmamalıdır. Kurumsal faturalı siparişlerde iade faturası gerekir.</p>

<h2>Öngörülemeyen gecikme</h2>
<p>Mücbir sebep halinde durum Alıcı’ya bildirilir. Alıcı iptal, emsal ürün veya erteleme talep edebilir. İptalde nakit 14 gün içinde; kart ödemesi 14 gün içinde bankaya iade edilir. Bankanın hesaba aktarması 2–3 haftayı bulabilir.</p>

<h2>Yetkisiz kart kullanımı</h2>
<p>Teslimden sonra kartın yetkisiz kullanıldığı ve bedelin Satıcı’ya ödenmediği tespit edilirse Alıcı ürünü 3 gün içinde, nakliye Satıcı’ya ait olmak üzere iade eder.</p>

<h2>Ödeme</h2>
<p>Kart ödemeleri <?= e($c['odeme']) ?> üzerinden alınır. Kart numarası sistemimizde saklanmaz.</p>

<h2>Temerrüt</h2>
<p>Kartla ödemede temerrüt halinde Alıcı, kart sözleşmesi çerçevesinde bankasına karşı sorumludur. Gecikmeden Satıcı’nın uğradığı zarar talep edilebilir.</p>
<?php legal_page_close();
