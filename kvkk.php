<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$c = legal_company();
legal_page_open(
    'KVKK Aydınlatma Metni | Online İlahiyat',
    '6698 sayılı KVKK m.10 ve Aydınlatma Yükümlülüğünün Yerine Getirilmesinde Uyulacak Usul ve Esaslar Hakkında Tebliğ uyarınca kişisel veri aydınlatma metni.',
    'KVKK aydınlatma metni'
);
?>
<p class="mt-4 text-muted">Bu metin, 6698 sayılı Kişisel Verilerin Korunması Kanunu’nun 10. maddesi ile Kişisel Verileri Koruma Kurulu’nun Aydınlatma Yükümlülüğünün Yerine Getirilmesinde Uyulacak Usul ve Esaslar Hakkında Tebliği uyarınca veri sorumlusu tarafından hazırlanmıştır. Aydınlatma, açık rızadan ayrıdır. Formlardaki “okudum, anladım” kutusu rıza beyanı değildir; Kanun m.5/2 kapsamında rıza aranmayan işlemler için ayrıca rıza alınmaz.</p>
<?php legal_company_block(); ?>

<h2>1. Veri sorumlusunun kimliği</h2>
<p>Veri sorumlusu: <b><?= e($c['unvan']) ?></b> (ticari marka: <?= e($c['brand']) ?>).</p>
<ul>
  <li>Açık adres: <?= e($c['address']) ?></li>
  <li>E-posta: <?= e($c['email']) ?></li>
  <li>İnternet sitesi: <?= e($c['site']) ?></li>
  <li>Vergi dairesi / no: <?= e($c['vergi_daire']) ?> · <?= e($c['vergi_no']) ?></li>
  <li>Diğer irtibat: <?= e(legal_contact_channels()) ?></li>
</ul>
<p>VERBİS kaydı, Kanun ve Veri Sorumluları Sicili Hakkında Yönetmelik uyarınca yükümlülük doğduğu ölçüde yapılır. Sicil numarası tescil edildiğinde bu metinde yayımlanır; henüz yayımlanmamış bir numara uydurulmaz.</p>

<h2>2. İşlenen kişisel veri kategorileri</h2>
<ul>
  <li><b>Kimlik:</b> ad, soyad</li>
  <li><b>İletişim:</b> e-posta, telefon, teslimat ve fatura adresi</li>
  <li><b>Müşteri işlem:</b> üyelik, sipariş, fatura, ödeme sonucu, cayma/iade talebi</li>
  <li><b>Eğitim süreci:</b> program/grup kaydı, yoklama, ödev, test, panel mesajı, canlı ders katılımı</li>
  <li><b>İşlem güvenliği:</b> IP adresi, oturum kimliği, çerez, tarayıcı/cihaz bilgisi, güvenlik günlükleri</li>
  <li><b>Pazarlama (yalnızca ayrı ve isteğe bağlı rıza + İYS kaydı varsa):</b> ticari elektronik ileti tercihi</li>
  <li><b>Ödeme:</b> ödeme kuruluşunun işlem referansı ve sonuç bilgisi. Kart numarası, CVV ve son kullanma tarihi veri sorumlusunun sisteminde saklanmaz.</li>
</ul>
<p>Özel nitelikli kişisel veri (din, sağlık, biyometri vb.) talep edilmez. Canlı derste görüntü/ses, eğitim hizmetinin ifası için işlenir; bu kayıtlar pazarlama amacıyla kullanılmaz.</p>

<h2>3. Toplama yöntemi</h2>
<p>Veriler; üyelik ve sipariş formları, iletişim / “sizi arayalım” formu, öğrenci-öğretmen paneli, canlı ders ve kayıt, çerezler, ödeme kuruluşunun dönüş bildirimi ve (açık rızanız varsa) analiz araçları aracılığıyla otomatik veya kısmen otomatik yollarla toplanır.</p>

<h2>4. İşleme amaçları ve hukuki sebepler</h2>
<p>Kurul, aydınlatma ile açık rızanın iç içe geçirilmesini ve sözleşmenin ifası için zaten işlenmesi gereken verilere rıza dayandırılmasını uygun görmez. Aşağıdaki tabloda amaçlar, Kanun m.5 ve m.6’daki hukuki sebeplere ayrılmıştır.</p>
<table>
  <thead>
    <tr>
      <th>Amaç</th>
      <th>Hukuki sebep</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>Üyelik hesabının açılması, girişi, şifre sıfırlama</td>
      <td>m.5/2-c sözleşmenin kurulması ve ifası</td>
    </tr>
    <tr>
      <td>Mesafeli satış, fatura, teslimat, dijital içeriğin panele tanımlanması, canlı ders</td>
      <td>m.5/2-c sözleşmenin ifası</td>
    </tr>
    <tr>
      <td>Vergi, e-fatura, tüketici, 5651 sayılı Kanun ve benzeri yükümlülükler</td>
      <td>m.5/2-ç kanuni yükümlülük; m.5/2-a kanunlarda açıkça öngörülme</td>
    </tr>
    <tr>
      <td>Cayma, iade, şikâyet, tüketici uyuşmazlığı, delil saklama</td>
      <td>m.5/2-e bir hakkın tesisi, kullanılması veya korunması</td>
    </tr>
    <tr>
      <td>Bilgi güvenliği, kötüye kullanımın ve hesap paylaşımının önlenmesi, oturum koruması</td>
      <td>m.5/2-f meşru menfaat (temel hak ve özgürlüklere zarar vermemek kaydıyla); kısmen m.5/2-c</td>
    </tr>
    <tr>
      <td>İletişim formu ve geri arama talebinin karşılanması</td>
      <td>m.5/2-c talebe konu sözleşmenin kurulması / ifası</td>
    </tr>
    <tr>
      <td>İsteğe bağlı analiz çerezleri</td>
      <td>m.5/1 açık rıza (çerez bandı). Siteyi kullanmak rıza sayılmaz.</td>
    </tr>
    <tr>
      <td>Reklam, kampanya, ticari elektronik ileti (e-posta, SMS, arama)</td>
      <td>m.5/1 açık rıza + 6563 sayılı ETK ve İYS. Zorunlu değildir; alınmadan gönderilmez.</td>
    </tr>
    <tr>
      <td>Yurt dışındaki hizmet sağlayıcılarına aktarım</td>
      <td>Kanun m.9; analiz için açık rıza, eğitim ve sitenin sunulması için sözleşmenin gerektirdiği aktarım ve uygun güvenceler</td>
    </tr>
  </tbody>
</table>

<h2>5. Aktarılan kişi grupları</h2>
<p>Veriler, işleme amacıyla sınırlı ve orantılı olarak aşağıdaki alıcılara aktarılabilir:</p>
<ul>
  <li><b>Ödeme kuruluşu:</b> <?= e($c['odeme']) ?> — tahsilat, 3D Secure, iade. Kart verisi ödeme kuruluşu / banka altyapısındadır.</li>
  <li><b>Barındırma ve e-posta:</b> sitenin ve iletişimin çalışması için hizmet aldığımız altyapı sağlayıcıları.</li>
  <li><b>Canlı ders ve kayıt:</b> görüntü, ses ve derse katılım bilgisi, eğitimin sunulması için kullandığımız yayın ve kayıt hizmetlerine iletilebilir.</li>
  <li><b>Hesap girişi (isteğe bağlı):</b> üçüncü taraf hesapla giriş yapılırsa, kimlik doğrulama için gerekli asgari veri ilgili sağlayıcıya gider.</li>
  <li><b>Kargo:</b> basılı kitap siparişinde taşıyıcıya ad, telefon, teslimat adresi.</li>
  <li><b>Yetkili kamu kurumları:</b> mevzuatın zorunlu kıldığı hallerde mahkeme, savcılık, idare, vergi dairesi, GİB, BTK, Tüketici Hakem Heyeti.</li>
</ul>
<p>Bazı hizmet sağlayıcıları yurt dışında yerleşik olabilir; bu aktarımlar Kanun m.9 kapsamındadır. Analiz çerezleri için aktarım, çerez bandındaki kabulünüze bağlıdır. Canlı ders, kayıt ve sitenin sunulması için zorunlu aktarımlar sözleşmenin ifasının gereğidir.</p>

<h2>6. Saklama süreleri</h2>
<p>Veriler, işleme amacının gerektirdiği süre ve ilgili mevzuattaki zamanaşımı / saklama süreleri kadar tutulur; süre bitince silinir, yok edilir veya anonim hale getirilir.</p>
<ul>
  <li>Üyelik hesabı: hesap açık kaldığı süre + kapanıştan sonra olası uyuşmazlık için makul süre</li>
  <li>Sipariş, fatura, ödeme: 213 sayılı VUK ve Türk Ticaret Kanunu defter saklama süreleri (kural olarak 10 yıl)</li>
  <li>Tüketici işlemleri ve cayma yazışması: 6502 sayılı Kanun zamanaşımı (kural olarak 2 yıl, her halde 3 yıl; ayıplı ifada ilgili süreler)</li>
  <li>İletişim formu: talebin sonuçlanması ve ispat için makul süre (kural olarak 2 yıl)</li>
  <li>Güvenlik / erişim günlükleri: 5651 sayılı Kanun ve ikincil mevzuattaki süreler</li>
  <li>Çerezler: <?= legal_link('cerez-politikasi', 'Çerez Politikası') ?>’ndaki süreler</li>
  <li>Analiz çerezleri: rızanın geri alınması veya sürenin dolması</li>
</ul>

<h2>7. Kanun m.11 kapsamındaki haklarınız</h2>
<p>Veri sahibi olarak:</p>
<ul>
  <li>kişisel verilerinizin işlenip işlenmediğini öğrenme,</li>
  <li>işlenmişse buna ilişkin bilgi talep etme,</li>
  <li>işleme amacını ve amacına uygun kullanılıp kullanılmadığını öğrenme,</li>
  <li>yurt içinde veya yurt dışında aktarıldığı üçüncü kişileri bilme,</li>
  <li>eksik veya yanlış işlenmişse düzeltilmesini isteme,</li>
  <li>Kanun m.7’deki şartlar çerçevesinde silinmesini veya yok edilmesini isteme,</li>
  <li>düzeltme / silme işlemlerinin aktarıldığı üçüncü kişilere bildirilmesini isteme,</li>
  <li>işlenen verilerin münhasıran otomatik sistemler vasıtasıyla analiz edilmesi suretiyle aleyhinize bir sonucun ortaya çıkmasına itiraz etme,</li>
  <li>kanuna aykırı işleme nedeniyle zarara uğramanız hâlinde zararın giderilmesini talep etme</li>
</ul>
<p>haklarına sahipsiniz. Açık rızaya dayanan işlemlerde rızanızı, ilerisi için her zaman geri alabilirsiniz. Rızanın geri alınması, geri alma anına kadar yapılan hukuka uygun işlemleri geçersiz kılmaz.</p>

<h2>8. Başvuru usulü</h2>
<p>Başvurular, Veri Sorumlusuna Başvuru Usul ve Esasları Hakkında Tebliğ’e göre yapılır. Kimliğinizi tespit etmeye elverişli olacak şekilde:</p>
<ul>
  <li>yazılı olarak <?= e($c['address']) ?> adresine,</li>
  <li>kayıtlı elektronik posta (KEP) varsa KEP üzerinden,</li>
  <li>daha önce bize bildirdiğiniz ve sistemimizde kayıtlı e-posta üzerinden <?= e($c['email']) ?> adresine,</li>
  <li>sitedeki iletişim formu ile</li>
</ul>
<p>iletebilirsiniz. Başvuruda ad soyad, T.C. kimlik numarası (uyruğu başka ise uyruğu, pasaport numarası veya kimlik numarası), tebligata esas yerleşim yeri veya iş yeri adresi, varsa bildirime esas KEP / e-posta / faks, talep konusu ve imza (yazılı başvuruda) bulunur.</p>
<p>Veri sorumlusu, talebi en kısa sürede ve en geç otuz gün içinde ücretsiz sonuçlandırır. İşlemin ayrıca bir maliyet gerektirmesi hâlinde Tebliğ’deki tarifedeki ücret alınabilir. Başvurunun reddi, cevap verilmesi veya süresinde cevap verilmemesi hâlinde, cevabı öğrendiğiniz tarihten itibaren otuz ve her hâlde başvuru tarihinden itibaren altmış gün içinde Kurul’a şikâyet hakkınız vardır (Kanun m.13–14). Kurul’a şikâyetten önce veri sorumlusuna başvuru zorunludur.</p>

<h2>9. Ticari elektronik ileti ve İYS</h2>
<p>Kampanya, tanıtım ve benzeri ticari elektronik iletiler 6563 sayılı Elektronik Ticaretin Düzenlenmesi Hakkında Kanun ve Ticari İletişim ve Ticari Elektronik İletiler Hakkında Yönetmelik uyarınca, İleti Yönetim Sistemi’nde (İYS) onayınız olmadan gönderilmez. KVKK açık rızası ile İYS onayı birbirinin yerine geçmez. Henüz ayrı bir pazarlama onayı almadıysanız reklam SMS/e-posta/arama gönderilmez. Sipariş, üyelik, fatura, ders saati ve benzeri işlem iletileri ticari reklam değildir.</p>

<h2>10. Çocuklar</h2>
<p>Site, kural olarak ergin tüketicilere ve adaylara yöneliktir. 18 yaşından küçük bir kişi adına işlem, veli veya vasi tarafından yapılmalıdır.</p>

<h2>11. Değişiklik</h2>
<p>Mevzuat veya işleme faaliyetleri değişirse bu metin güncellenir ve sitede yayımlanan sürüm geçerlidir. Son güncelleme tarihi sayfanın üzerindedir.</p>
<p class="legal-note">Bu metin hukuki bilgilendirme niteliğindedir; uyuşmazlıkta yürürlükteki mevzuat ve Kurul kararları esas alınır. <?= legal_link('gizlilik', 'Gizlilik politikası') ?> · <?= legal_link('cerez-politikasi', 'Çerez politikası') ?></p>
<?php legal_page_close();
