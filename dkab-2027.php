<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$img = url('assets/img/slider-dkab-2027.jpg');
public_head(
    '2027 DKAB-ÖABT hazırlık | Online İlahiyat',
    '2027 DKAB-ÖABT sınavına ücretsiz hazırlık: detaylı konu anlatımı, akademisyen hocalar, Zoom canlı dersler ve ders kayıtları. Eğitim 16 Kasım 2026’da başlar.'
);
?>
<section class="bg-navy">
  <div class="mx-auto max-w-7xl">
    <img src="<?= e($img) ?>" alt="2027 DKAB’ye hazırlanıyorsan doğru yerdesin — ücretsiz dersler" class="block w-full">
  </div>
</section>
<header class="bg-soft py-8">
  <div class="mx-auto max-w-3xl px-4 lg:px-8">
    <p class="text-sm font-extrabold text-navy">Ücretsiz hazırlık</p>
    <h1 class="font-display mt-2 text-3xl leading-tight md:text-4xl">2027 DKAB-ÖABT hazırlıklarına birlikte başlıyoruz</h1>
    <p class="mt-4 text-lg text-muted">2027 DKAB-ÖABT sınavına hazırlanıyorsan doğru yerdesin.</p>
  </div>
</header>
<main class="mx-auto max-w-3xl px-4 py-12 lg:px-8">
  <p class="text-lg leading-relaxed">Başarıya ulaşmak için yalnızca çok çalışmak değil; konuları doğru kaynaklardan, sistemli ve sınav odaklı öğrenmek gerekiyor. onlineilahiyat.com olarak 2027 DKAB hazırlık sürecinde seni kapsamlı bir eğitim programıyla buluşturuyoruz.</p>

  <h2 class="font-display mt-12 text-2xl">Eğitim programında neler var?</h2>
  <ul class="mt-5 grid gap-3 sm:grid-cols-2">
    <li class="card p-5"><p class="font-extrabold">Detaylı konu anlatımı</p><p class="mt-1 text-sm text-muted">Kapsamlı, sınav odaklı üniteler.</p></li>
    <li class="card p-5"><p class="font-extrabold">Akademisyen eğitimciler</p><p class="mt-1 text-sm text-muted">Alanında uzman hocalar.</p></li>
    <li class="card p-5"><p class="font-extrabold">Canlı Zoom dersleri</p><p class="mt-1 text-sm text-muted">İnteraktif, soru sorulabilen oturumlar.</p></li>
    <li class="card p-5"><p class="font-extrabold">Planlı çalışma</p><p class="mt-1 text-sm text-muted">Sistemli, sınav takvimine göre ilerleme.</p></li>
  </ul>

  <h2 class="font-display mt-12 text-2xl">Canlı ders ve ders kayıtları</h2>
  <p class="mt-3 leading-relaxed">Eğitimler Zoom üzerinden canlı yapılır. Derse katılamayan veya konuyu tekrar etmek isteyenler için kayıtlar, sitedeki <strong>Ders Kayıtları</strong> bölümüne yüklenir. Böylece dersi sonra yeniden izleyebilirsiniz.</p>

  <div class="card mt-10 border-navy bg-navy p-6 text-white">
    <p class="font-display text-2xl text-accent">Tamamen ücretsiz dersler</p>
    <dl class="mt-5 grid gap-4 sm:grid-cols-2">
      <div><dt class="text-xs font-extrabold uppercase tracking-wide text-accent">Eğitim başlangıcı</dt><dd class="mt-1 font-extrabold">16 Kasım 2026</dd></div>
      <div><dt class="text-xs font-extrabold uppercase tracking-wide text-accent">Ders saati</dt><dd class="mt-1 font-extrabold">19:00</dd></div>
      <div><dt class="text-xs font-extrabold uppercase tracking-wide text-accent">Ders günleri</dt><dd class="mt-1 font-extrabold">Hafta içi 3 veya 4 gün</dd></div>
      <div><dt class="text-xs font-extrabold uppercase tracking-wide text-accent">Platform</dt><dd class="mt-1 font-extrabold">Zoom</dd></div>
    </dl>
    <p class="mt-5 text-sm leading-relaxed text-white/80">Ders bağlantısı her gün <a class="font-extrabold text-accent underline" href="https://www.instagram.com/onlineilahiyat/" rel="noopener noreferrer" target="_blank">@onlineilahiyat</a> Instagram bio’sundan paylaşılır.</p>
  </div>

  <h2 class="font-display mt-12 text-2xl">Hedefimiz belli: 2027 DKAB’de başarı</h2>
  <p class="mt-3 leading-relaxed">Bugün attığın adım, sınav günündeki başarının temelini oluşturacak. 2027 DKAB hazırlığını şansa bırakma. Doğru kaynak, güçlü anlatım ve düzenli çalışmayla hedefe birlikte ilerleyelim.</p>

  <div class="mt-8 flex flex-wrap gap-3">
    <a class="btn-primary" href="<?= e(page_url('kayit-ders')) ?>">Ücretsiz kaydol</a>
    <a class="btn-outline" href="<?= e(page_url('giris-ders')) ?>">Ders girişi</a>
  </div>
  <p class="mt-6 text-sm text-muted"><a class="font-extrabold text-navy" href="<?= e(page_url('home')) ?>">onlineilahiyat.com</a></p>
</main>
<?php public_foot();
