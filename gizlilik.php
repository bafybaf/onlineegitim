<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
public_head('Gizlilik Politikası | Online İlahiyat', 'Çerez, oturum ve veri güvenliği politikası.');
?>
<main class="mx-auto max-w-3xl px-4 py-14 lg:px-8">
  <p class="badge">Yasal</p>
  <h1 class="font-display mt-4 text-4xl">Gizlilik politikası</h1>
  <p class="mt-4 text-muted">Siteyi kullanarak aşağıdaki uygulamaları kabul etmiş olursunuz. Eğitim içerikleri (video, not, ödev) yalnızca kayıtlı öğrenciye açıktır; paylaşılması yasaktır.</p>
  <h2 class="font-display mt-8 text-2xl">Çerez ve oturum</h2>
  <p class="mt-2 text-muted">Giriş oturumu için zorunlu çerez kullanılır. İsteğe bağlı analiz (Google Analytics) yalnızca admin SEO ayarında kimlik girilmişse çalışır.</p>
  <h2 class="font-display mt-8 text-2xl">Üçüncü taraflar</h2>
  <p class="mt-2 text-muted">Ödeme PayTR, e-posta SMTP sağlayıcınız, canlı yayın MediaMTX sunucusu üzerinden yürür. Kart numarası sunucumuzda tutulmaz.</p>
  <h2 class="font-display mt-8 text-2xl">İçerik ve kayıtlar</h2>
  <p class="mt-2 text-muted">Ders videoları ve PDF notlar `storage` altında yetki kontrolüyle sunulur. Hesabınızı başkasıyla paylaşmayın.</p>
  <p class="mt-8"><a class="font-extrabold text-navy" href="<?= e(page_url('kvkk')) ?>">KVKK metni →</a></p>
</main>
<?php public_foot();
