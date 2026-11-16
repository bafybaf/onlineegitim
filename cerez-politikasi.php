<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$c = legal_company();
legal_page_open(
    'Çerez Politikası | Online İlahiyat',
    'Kişisel Verileri Koruma Kurulu Çerez Uygulamaları Hakkında Rehber (20.06.2022) uyarınca çerez kategorileri, süreleri, üçüncü taraf aktarımı ve rıza yönetimi.',
    'Çerez politikası'
);
?>
<p class="mt-4 text-muted">Bu politika, 6698 sayılı Kanun, Aydınlatma Tebliği ve Kurul’un 20.06.2022 tarihli Çerez Uygulamaları Hakkında Rehberi’ne göre hazırlanmıştır. Çerez rızası, üyelik ve satış sözleşmelerinden ayrıdır. “Siteyi kullanarak kabul etmiş sayılırsınız” ifadesi kullanılmaz; rıza, eşit ağırlıklı kabul / red seçenekleriyle alınır. Analiz çerezi yüklenmeden önce rıza aranır.</p>
<?php legal_company_block(); ?>

<h2>1. Çerez nedir?</h2>
<p>Çerez, tarayıcınıza kaydedilen küçük bir metin dosyasıdır. Benzer teknolojiler (yerel depolama, pikseller) de bu politikanın kapsamındadır. Çerezler kart numarası veya şifre toplamak için tasarlanmamıştır.</p>

<h2>2. Rıza gerekip gerekmediği (Kurul kriterleri)</h2>
<p>Rehber’e göre bir çerez ancak şu iki şartı birlikte sağlıyorsa rıza aranmadan kullanılabilir:</p>
<ul>
  <li><b>Kriter A:</b> iletişim ağının iletimini sağlamak için zorunlu olması, veya</li>
  <li><b>Kriter B:</b> abonenin / kullanıcının açıkça talep ettiği bilgi toplumu hizmetini sunmak için kesinlikle gerekli olması.</li>
</ul>
<p>Oturum, güvenlik, giriş ve sepet bu kriterleri karşılar. İstatistik ve izleme çerezleri karşılamaz; açık rıza ve (yurt dışı aktarım varsa) Kanun m.9’a uygun dayanak gerekir. Reddetmek, sitenin asıl hizmetini (bilgi alma, üyelik, satın alma) kapatmaz; çerez duvarı uygulanmaz.</p>

<h2>3. Kullandığımız çerez ve benzeri kayıtlar</h2>
<table>
  <thead>
    <tr>
      <th>Ad</th>
      <th>Kaynak</th>
      <th>Amaç</th>
      <th>Süre</th>
      <th>Tür</th>
      <th>Rıza</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><code>oi_sid</code></td>
      <td>Birinci taraf</td>
      <td>Oturum, giriş, sepet, güvenlik</td>
      <td>Oturum (tarayıcı kapanınca)</td>
      <td>Zorunlu</td>
      <td>Aranmaz (Kriter B)</td>
    </tr>
    <tr>
      <td><code>oi_cookie_consent</code></td>
      <td>Birinci taraf (localStorage)</td>
      <td>Çerez tercihinin hatırlanması</td>
      <td>Siz silene veya sıfırlayana kadar</td>
      <td>Zorunlu</td>
      <td>Aranmaz (tercihi saklamak için gerekli)</td>
    </tr>
    <tr>
      <td>Analiz çerezleri</td>
      <td>Üçüncü taraf</td>
      <td>Ziyaret istatistiği</td>
      <td>Onayınız süresince, en fazla 24 ay</td>
      <td>Analiz</td>
      <td>Açık rıza. Yurt dışı aktarım içerebilir.</td>
    </tr>
  </tbody>
</table>
<p>Analiz çerezleri yalnızca “Kabul et” derseniz çalışır. “Reddet” seçilirse istatistik çerezi yüklenmez.</p>

<h2>4. Bant ve tercih yönetimi</h2>
<p>İlk ziyarette bant gösterilir. İki seçenek aynı boyutta ve aynı görsel ağırlıktadır:</p>
<ul>
  <li><b>Reddet:</b> yalnızca zorunlu çerezler; analiz yüklenmez.</li>
  <li><b>Kabul et:</b> zorunlu + analiz (kimlik tanımlıysa).</li>
</ul>
<p>Önceden işaretli “kabul” kutusu, kaydırarak onay ve “devam ederseniz kabul etmiş olursunuz” modelleri kullanılmaz. Tercihinizi aşağıdan sıfırlayabilirsiniz; bant yeniden açılır.</p>
<p><button type="button" id="cookie-reset" class="btn-outline text-sm">Çerez tercihini sıfırla</button></p>

<h2>5. Tarayıcı ayarları</h2>
<p>Tarayıcınızdan çerezleri silebilir veya engelleyebilirsiniz. Zorunlu çerezler engellenirse giriş, sepet ve güvenlik doğrulaması çalışmayabilir. Bu, hizmetin sunulamaması sonucunu doğurur; Kanun m.5/2-c kapsamındaki ifa için oturum çerezi gereklidir.</p>

<h2>6. Haklar</h2>
<p>Analiz rızasını geri almak, Kanun m.11 haklarını kullanmak ve yurt dışı aktarıma dair bilgi için <?= legal_link('kvkk', 'KVKK Aydınlatma Metni') ?> geçerlidir. Sorular: <?= e($c['email']) ?>.</p>
<p class="legal-note"><?= legal_link('gizlilik', 'Gizlilik politikası') ?> ile birlikte uygulanır. Rehber veya Kanun değişirse bu sayfa güncellenir.</p>
<script>
document.getElementById('cookie-reset')?.addEventListener('click', function () {
  try { localStorage.removeItem('oi_cookie_consent'); } catch (e) {}
  location.reload();
});
</script>
<?php legal_page_close();
