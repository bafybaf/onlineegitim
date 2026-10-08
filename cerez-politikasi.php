<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$c = legal_company();
legal_page_open(
    'Çerez Politikası | Online İlahiyat',
    'Online İlahiyat çerez politikası. Zorunlu çerezler, isteğe bağlı analiz ve onay tercihleriniz.',
    'Çerez politikası'
);
?>
<p class="mt-4 text-muted">Bu site, 6698 sayılı KVKK ve Elektronik Ticaret mevzuatı uyarınca çerez kullanır. Çerez onayı, satış sözleşmelerinden ayrıdır; ilk ziyarette bant üzerinden alınır.</p>
<?php legal_company_block(); ?>

<h2>Çerez nedir?</h2>
<p>Çerez, tarayıcınıza kaydedilen küçük bir metin dosyasıdır. Oturumun açık kalması, sepetin hatırlanması ve (onaylarsanız) ziyaret istatistiği için kullanılır. Çerezler kredi kartı veya şifre toplamak için tasarlanmamıştır.</p>

<h2>Zorunlu çerezler</h2>
<p>Bu çerezler sitesiz çalışmaz; onayınız aranmaz.</p>
<ul>
  <li>Oturum ve giriş (öğrenci, öğretmen, mağaza, yönetici)</li>
  <li>CSRF ve güvenlik</li>
  <li>Sepet içeriği</li>
  <li>Çerez tercih kaydı</li>
</ul>

<h2>İsteğe bağlı analiz çerezleri</h2>
<p>Yönetici panelinde Google Analytics kimliği tanımlıysa ve siz “Tümünü kabul et” derseniz istatistik çerezleri çalışır. “Yalnızca zorunlu” derseniz analiz yüklenmez. Tercihinizi tarayıcı verilerini temizleyerek veya aşağıdaki düğmeyle yenileyebilirsiniz.</p>
<p><button type="button" id="cookie-reset" class="btn-outline text-sm">Çerez tercihini sıfırla</button></p>

<h2>Yönetim</h2>
<p>Tarayıcı ayarlarından çerezleri silebilir veya engelleyebilirsiniz. Zorunlu çerezler engellenirse giriş ve sepet çalışmayabilir.</p>
<p>Kişisel veri işleme hakkında <?= legal_link('kvkk', 'KVKK Aydınlatma Metni') ?> ve <?= legal_link('gizlilik', 'Gizlilik Politikası') ?> geçerlidir. Sorular: <?= e($c['email']) ?>.</p>
<script>
document.getElementById('cookie-reset')?.addEventListener('click', function () {
  try { localStorage.removeItem('oi_cookie_consent'); } catch (e) {}
  location.reload();
});
</script>
<?php legal_page_close();
