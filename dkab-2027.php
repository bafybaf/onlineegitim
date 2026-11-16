<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
public_head(
    '2027 DKAB-ÖABT hazırlık | Online İlahiyat',
    '2027 DKAB-ÖABT hazırlık. Detaylı konu anlatımı, canlı ders ve ders kayıtları. Eğitim 16 Kasım 2026’da başlar.'
);
?>
<header class="bg-soft py-8">
  <div class="mx-auto max-w-3xl px-4 lg:px-8">
    <p class="text-sm font-extrabold text-navy">Ücretsiz hazırlık</p>
    <h1 class="font-display mt-2 text-3xl leading-tight md:text-4xl">2027 DKAB-ÖABT</h1>
  </div>
</header>
<main class="mx-auto max-w-3xl px-4 py-12 lg:px-8">
  <ul class="grid gap-3 sm:grid-cols-2">
    <li class="card p-5"><p class="font-extrabold">Detaylı konu anlatımı</p></li>
    <li class="card p-5"><p class="font-extrabold">Canlı Zoom dersleri</p></li>
    <li class="card p-5"><p class="font-extrabold">Ders kayıtları</p></li>
  </ul>

  <div class="card mt-10 border-navy bg-navy p-6 text-white">
    <p class="font-display text-2xl text-accent">Ücretsiz dersler</p>
    <dl class="mt-5 grid gap-4 sm:grid-cols-2">
      <div><dt class="text-xs font-extrabold uppercase tracking-wide text-accent">Eğitim başlangıcı</dt><dd class="mt-1 font-extrabold">16 Kasım 2026</dd></div>
      <div><dt class="text-xs font-extrabold uppercase tracking-wide text-accent">Ders saati</dt><dd class="mt-1 font-extrabold">19:00</dd></div>
      <div><dt class="text-xs font-extrabold uppercase tracking-wide text-accent">Ders günleri</dt><dd class="mt-1 font-extrabold">Hafta içi 3 veya 4 gün</dd></div>
      <div><dt class="text-xs font-extrabold uppercase tracking-wide text-accent">Platform</dt><dd class="mt-1 font-extrabold">Zoom</dd></div>
    </dl>
    <p class="mt-5 text-sm leading-relaxed text-white/80">Ders bağlantısı <a class="font-extrabold text-accent underline" href="https://www.instagram.com/onlineilahiyat/" rel="noopener noreferrer" target="_blank">@onlineilahiyat</a> Instagram bio’sundan paylaşılır.</p>
  </div>

  <div class="mt-8 flex flex-wrap gap-3">
    <a class="btn-primary" href="<?= e(page_url('kayit-ders')) ?>">Ücretsiz kaydol</a>
    <a class="btn-outline" href="<?= e(page_url('giris-ders')) ?>">Ders girişi</a>
  </div>
</main>
<?php public_foot();
