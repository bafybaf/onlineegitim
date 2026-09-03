<?php
function programs(): array
{
    return db()->query('SELECT * FROM programs ORDER BY ' . catalog_order_sql('', 'programs'))->fetchAll();
}

function public_head(string $title, string $desc = ''): void
{
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    $u = current_user();
    $fullTitle = seo_document_title($title);
    $metaDesc = $desc !== '' ? $desc : setting('seo_default_description');
    $keywords = setting('seo_keywords');
    $robots = setting('seo_robots', 'index,follow') ?: 'index,follow';
    $canonical = canonical_url();
    $ogImage = seo_image_url(setting('seo_og_image'));
    $ga = trim(setting('seo_google_analytics'));
    $verify = trim(setting('seo_google_site_verification'));
    ?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php security_html_head(); ?>
  <title><?= e($fullTitle) ?></title>
  <?php if ($metaDesc): ?><meta name="description" content="<?= e($metaDesc) ?>" /><?php endif; ?>
  <?php if ($keywords): ?><meta name="keywords" content="<?= e($keywords) ?>" /><?php endif; ?>
  <meta name="robots" content="<?= e($robots) ?>" />
  <link rel="canonical" href="<?= e($canonical) ?>" />
  <meta property="og:type" content="website" />
  <meta property="og:title" content="<?= e($fullTitle) ?>" />
  <?php if ($metaDesc): ?><meta property="og:description" content="<?= e($metaDesc) ?>" /><?php endif; ?>
  <meta property="og:url" content="<?= e($canonical) ?>" />
  <?php if ($ogImage): ?><meta property="og:image" content="<?= e($ogImage) ?>" /><?php endif; ?>
  <meta name="twitter:card" content="<?= $ogImage ? 'summary_large_image' : 'summary' ?>" />
  <meta name="twitter:title" content="<?= e($fullTitle) ?>" />
  <?php if ($metaDesc): ?><meta name="twitter:description" content="<?= e($metaDesc) ?>" /><?php endif; ?>
  <?php if ($ogImage): ?><meta name="twitter:image" content="<?= e($ogImage) ?>" /><?php endif; ?>
  <?php if ($verify): ?><meta name="google-site-verification" content="<?= e($verify) ?>" /><?php endif; ?>
  <link rel="icon" href="<?= e(url('assets/img/logo.png')) ?>" type="image/png" />
  <?php if ($ga !== ''): ?>
  <script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga) ?>"></script>
  <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config',<?= json_encode($ga, JSON_UNESCAPED_UNICODE) ?>);</script>
  <?php endif; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@700;800&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={theme:{extend:{colors:{navy:'#1a3fad',navy2:'#0f2a7a',navy3:'#0a1a4e',accent:'#12705a',accent2:'#0c5444',ink:'#1a1f36',muted:'#6e6e73',soft:'#f5f5f7'},fontFamily:{sans:['Nunito','sans-serif'],display:['Bricolage Grotesque','sans-serif']}}}}</script>
  <link rel="stylesheet" href="<?= e(url('assets/css/site.css')) ?>?v=<?= (int) @filemtime(__DIR__ . '/../assets/css/site.css') ?>" />
</head>
<body class="bg-white">
<header class="site-header">
  <div class="mx-auto flex max-w-7xl items-stretch justify-between gap-4 px-4 lg:px-8">
    <a href="<?= e(page_url('home')) ?>" class="site-logo">
      <img src="<?= e(url('assets/img/logo.png')) ?>" alt="Online İlahiyat">
    </a>
    <nav class="hidden items-stretch gap-6 uppercase lg:flex">
      <div class="nav-item">
        <a class="nav-link flex h-full items-center" href="<?= e(page_url('programlar')) ?>">Eğitimlerimiz</a>
        <div class="mega"><div class="mega-panel">
          <?php foreach (array_slice(programs(), 0, 6) as $p): ?>
            <a class="block rounded-lg px-3 py-2 text-sm font-bold hover:bg-soft" href="<?= e(page_url('program', $p['slug'])) ?>"><?= e($p['title']) ?></a>
          <?php endforeach; ?>
          <a class="mt-1 block rounded-lg px-3 py-2 text-sm font-extrabold text-navy" href="<?= e(page_url('programlar')) ?>">Tüm eğitimler →</a>
        </div></div>
      </div>
      <a class="nav-link flex items-center" href="<?= e(kitaplar_url('dkab-ihl')) ?>">DKAB-İHL</a>
      <a class="nav-link flex items-center" href="<?= e(kitaplar_url('mbsts')) ?>">MBSTS</a>
      <a class="nav-link flex items-center" href="<?= e(kitaplar_url('dhbt')) ?>">DHBT</a>
      <a class="nav-link flex items-center" href="<?= e(page_url('blog')) ?>">Duyurular</a>
      <a class="nav-link flex items-center" href="<?= e(page_url('iletisim')) ?>">İletişim</a>
    </nav>
    <div class="flex items-center gap-2 py-3">
      <a href="<?= e(page_url('sepet')) ?>" class="relative grid h-10 w-10 place-items-center rounded-xl border border-[#e5e5e7]" aria-label="Sepet">
        <svg width="20" height="20" fill="none" stroke="#1a3fad" stroke-width="2"><path d="M4 6h16l-1.5 9h-13z"/><circle cx="8" cy="18" r="1.4"/><circle cx="16" cy="18" r="1.4"/></svg>
        <span id="cart-count" class="absolute -right-1 -top-1 grid h-5 min-w-5 place-items-center rounded-full bg-accent px-1 text-[10px] font-extrabold text-white"><?= cart_count() ?></span>
      </a>
      <button data-open-call class="btn-outline hidden h-10 px-3 text-sm sm:inline-flex">Sizi Arayalım</button>
      <?php if ($u && membership_needs_pay($u)): ?>
        <a href="<?= e(membership_complete_url($u)) ?>" class="btn-outline h-10 px-3 text-sm">Üyeliği tamamla</a>
      <?php endif; ?>
      <?php if ($u && is_shop_role($u['role'])): ?>
        <a href="<?= e(url(panel_home($u['role']))) ?>" class="btn-primary h-10 px-4 text-sm">Hesabım</a>
      <?php elseif ($u): ?>
        <a href="<?= e(url(panel_home($u['role']))) ?>" class="btn-primary h-10 px-4 text-sm">Panelim</a>
      <?php else: ?>
        <div class="nav-item account-dd hidden lg:flex">
          <a class="btn-primary h-10 px-4 text-sm" href="<?= e(page_url('kayit')) ?>">Kayıt Ol</a>
          <div class="mega"><div class="mega-panel">
            <a class="block rounded-lg px-3 py-2 text-sm font-bold hover:bg-soft" href="<?= e(page_url('kayit-magaza')) ?>">Mağaza kaydı</a>
            <a class="block rounded-lg px-3 py-2 text-sm font-bold hover:bg-soft" href="<?= e(page_url('kayit-ders')) ?>">Ders kaydı</a>
          </div></div>
        </div>
        <div class="nav-item account-dd hidden lg:flex">
          <a class="flex h-10 items-center rounded-xl px-3 text-sm font-extrabold text-navy" href="<?= e(page_url('giris')) ?>">Giriş</a>
          <div class="mega"><div class="mega-panel">
            <a class="block rounded-lg px-3 py-2 text-sm font-bold hover:bg-soft" href="<?= e(page_url('giris-magaza')) ?>">Mağaza girişi</a>
            <a class="block rounded-lg px-3 py-2 text-sm font-bold hover:bg-soft" href="<?= e(page_url('giris-ders')) ?>">Ders girişi</a>
          </div></div>
        </div>
        <a href="<?= e(page_url('kayit')) ?>" class="btn-primary h-10 px-4 text-sm lg:hidden">Kayıt Ol</a>
      <?php endif; ?>
      <button id="menu-btn" class="grid h-10 w-10 place-items-center rounded-xl border border-[#e5e5e7] lg:hidden" aria-label="Menü">☰</button>
    </div>
  </div>
</header>
<div id="drawer" class="drawer">
  <div class="drawer-panel p-5">
    <div class="mb-4 flex items-center justify-between"><strong>Menü</strong><button id="drawer-close" class="text-2xl leading-none">×</button></div>
    <div class="grid gap-2 font-bold uppercase">
      <a href="<?= e(page_url('programlar')) ?>">Eğitimlerimiz</a>
      <a href="<?= e(kitaplar_url('dkab-ihl')) ?>">DKAB-İHL</a>
      <a href="<?= e(kitaplar_url('mbsts')) ?>">MBSTS</a>
      <a href="<?= e(kitaplar_url('dhbt')) ?>">DHBT</a>
      <a href="<?= e(page_url('blog')) ?>">Duyurular</a>
      <a href="<?= e(page_url('iletisim')) ?>">İletişim</a>
      <?php if ($u && membership_needs_pay($u)): ?>
        <a href="<?= e(membership_complete_url($u)) ?>" class="btn-outline mt-2">Üyeliği tamamla</a>
      <?php endif; ?>
      <?php if ($u && is_shop_role($u['role'])): ?>
        <a href="<?= e(url(panel_home($u['role']))) ?>" class="btn-primary mt-2">Hesabım</a>
      <?php elseif ($u): ?>
        <a href="<?= e(url(panel_home($u['role']))) ?>" class="btn-primary mt-2">Panelim</a>
      <?php else: ?>
        <a href="<?= e(page_url('giris-magaza')) ?>">Mağaza girişi</a>
        <a href="<?= e(page_url('giris-ders')) ?>">Ders girişi</a>
        <a href="<?= e(page_url('kayit-magaza')) ?>">Mağaza kaydı</a>
        <a href="<?= e(page_url('kayit-ders')) ?>" class="btn-primary mt-2">Ders kaydı</a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php
}

function public_foot(): void
{
    ?>
<footer class="border-t border-[#e5e5e7] bg-[#0a1a4e] text-white">
  <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 md:grid-cols-4 lg:px-8">
    <div>
      <p>
        <img src="<?= e(url('assets/img/logo-dark.jpg')) ?>" alt="Online İlahiyat" class="footer-logo">
      </p>
      <p class="mt-3 text-sm text-white/70">Canlı ilahiyat dersleri, küçük gruplar ve kitap mağazası. Evden, gerçek takip ile.</p>
      <p class="mt-4 text-sm font-bold">info@onlineilahiyat.com</p>
    </div>
    <div>
      <p class="text-xs font-extrabold uppercase tracking-[0.2em] text-white/50">Eğitimler</p>
      <div class="mt-3 grid gap-2 text-sm text-white/80">
        <a href="<?= e(page_url('programlar')) ?>">Tefsir</a><a href="<?= e(page_url('programlar')) ?>">Hadis</a>
        <a href="<?= e(page_url('programlar')) ?>">Fıkıh</a><a href="<?= e(page_url('programlar')) ?>">Arapça</a>
      </div>
    </div>
    <div>
      <p class="text-xs font-extrabold uppercase tracking-[0.2em] text-white/50">Mağaza &amp; Sistem</p>
      <div class="mt-3 grid gap-2 text-sm text-white/80">
        <a href="<?= e(page_url('kitaplar')) ?>">Kitaplar</a>
        <a href="<?= e(page_url('giris-magaza')) ?>">Mağaza girişi</a>
        <a href="<?= e(page_url('giris-ders')) ?>">Ders girişi</a>
        <a href="<?= e(page_url('kayit-magaza')) ?>">Mağaza kaydı</a>
        <a href="<?= e(page_url('kayit-ders')) ?>">Ders kaydı</a>
      </div>
    </div>
    <div>
      <p class="text-xs font-extrabold uppercase tracking-[0.2em] text-white/50">Yasal</p>
      <div class="mt-3 grid gap-2 text-sm text-white/80">
        <a href="<?= e(page_url('iletisim')) ?>">İletişim</a>
        <a href="<?= e(page_url('gizlilik')) ?>">Gizlilik</a>
        <a href="<?= e(page_url('kvkk')) ?>">KVKK</a>
        <a href="<?= e(page_url('blog')) ?>">Duyurular</a>
      </div>
    </div>
  </div>
  <p class="border-t border-white/10 py-4 text-center text-xs text-white/50">© <?= date('Y') ?> Online İlahiyat. Tüm hakları saklıdır.</p>
</footer>
<div id="call-modal" class="modal">
  <div class="card w-full max-w-md p-6">
    <h3 class="font-display text-2xl">Sizi arayalım</h3>
    <p class="mt-2 text-sm text-muted">Adınızı ve telefonunuzu bırakın; eğitim danışmanımız sizi arasın.</p>
    <form class="mt-4 grid gap-3" method="post" action="<?= e(url('api/lead.php')) ?>">
      <?= csrf_field() ?>
      <?= security_honeypot_field() ?>
      <input required name="name" class="rounded-xl border border-[#e5e5e7] px-3 py-2" placeholder="Ad soyad" autocomplete="name">
      <input required name="phone" class="rounded-xl border border-[#e5e5e7] px-3 py-2" placeholder="Telefon" autocomplete="tel">
      <select name="interest" class="rounded-xl border border-[#e5e5e7] px-3 py-2">
        <option>Program seçin</option><option>Tefsir</option><option>Hadis</option><option>Fıkıh</option><option>Arapça</option><option>Kitap siparişi</option>
      </select>
      <button class="btn-primary">Beni arayın</button>
      <button type="button" data-close-call class="btn-outline">Vazgeç</button>
    </form>
  </div>
</div>
<script>window.OI_BASE = <?= json_encode(url('')) ?>; window.OI_CART = <?= (int) cart_count() ?>;</script>
<script src="<?= e(url('assets/js/app.js')) ?>?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
</body></html>
<?php
}

function panel_nav(string $role, string $page): array
{
    $liveN = (int) db()->query("SELECT COUNT(*) FROM live_rooms WHERE status='live'")->fetchColumn();
    if ($role === 'ogrenci') {
        return [
            ['label' => 'Ders', 'items' => [
                ['id' => 'dashboard', 'href' => 'ogrenci', 'label' => 'Özet', 'icon' => 'home'],
                ['id' => 'dersler', 'href' => 'ogrenci/derslerim', 'label' => 'Derslerim', 'icon' => 'book'],
                ['id' => 'canli', 'href' => 'ogrenci/canli', 'label' => 'Canlı dersler', 'icon' => 'live', 'badge' => $liveN],
                ['id' => 'takvim', 'href' => 'ogrenci/takvim', 'label' => 'Takvim', 'icon' => 'calendar'],
                ['id' => 'kayitlar', 'href' => 'ogrenci/kayitlar', 'label' => 'Ders kayıtları', 'icon' => 'play'],
            ]],
            ['label' => 'Çalışma', 'items' => [
                ['id' => 'odevler', 'href' => 'ogrenci/odevler', 'label' => 'Ödevler', 'icon' => 'task'],
                ['id' => 'notlar', 'href' => 'ogrenci/notlar', 'label' => 'Ders notları', 'icon' => 'layers'],
                ['id' => 'testler', 'href' => 'ogrenci/testler', 'label' => 'Testler', 'icon' => 'check'],
                ['id' => 'soru', 'href' => 'ogrenci/soru-sor', 'label' => 'Soru sor', 'icon' => 'mail'],
                ['id' => 'sertifika', 'href' => 'ogrenci/sertifikalar', 'label' => 'Sertifikalar', 'icon' => 'check'],
            ]],
            ['label' => 'Hesap', 'items' => [
                ['id' => 'hesap', 'href' => 'ogrenci/hesap', 'label' => 'Hesabım', 'icon' => 'user'],
                ['id' => 'uyelik', 'href' => 'uyelik-ders', 'label' => 'Üyelik al', 'icon' => 'card'],
                ['id' => 'kitaplar', 'href' => 'ogrenci/kitaplarim', 'label' => 'Kitaplarım', 'icon' => 'library'],
                ['id' => 'mesajlar', 'href' => 'ogrenci/mesajlar', 'label' => 'Mesajlar', 'icon' => 'mail'],
                ['id' => 'bildirimler', 'href' => 'ogrenci/bildirimler', 'label' => 'Bildirimler', 'icon' => 'mail', 'badge' => function_exists('academy_unread_count') ? academy_unread_count((int) (current_user()['id'] ?? 0)) : 0],
            ]],
        ];
    }
    if ($role === 'ogretmen') {
        return [
            ['label' => 'Ders', 'items' => [
                ['id' => 'dashboard', 'href' => 'ogretmen', 'label' => 'Özet', 'icon' => 'home'],
                ['id' => 'siniflar', 'href' => 'ogretmen/siniflar', 'label' => 'Sınıflarım', 'icon' => 'users'],
                ['id' => 'canli', 'href' => 'ogretmen/canli', 'label' => 'Canlı odalar', 'icon' => 'live', 'badge' => $liveN],
                ['id' => 'takvim', 'href' => 'ogretmen/takvim', 'label' => 'Takvim', 'icon' => 'calendar'],
                ['id' => 'yoklama', 'href' => 'ogretmen/yoklama', 'label' => 'Yoklama', 'icon' => 'list'],
            ]],
            ['label' => 'Çalışma', 'items' => [
                ['id' => 'odevler', 'href' => 'ogretmen/odevler', 'label' => 'Ödevler', 'icon' => 'task'],
                ['id' => 'notlar', 'href' => 'ogretmen/notlar', 'label' => 'Ders notları', 'icon' => 'layers'],
                ['id' => 'kayitlar', 'href' => 'ogretmen/kayit-yukle', 'label' => 'Ders kayıtları', 'icon' => 'play'],
                ['id' => 'testler', 'href' => 'ogretmen/testler', 'label' => 'Testler', 'icon' => 'check'],
            ]],
            ['label' => 'Sınıf', 'items' => [
                ['id' => 'ogrenciler', 'href' => 'ogretmen/ogrenciler', 'label' => 'Öğrenciler', 'icon' => 'user'],
                ['id' => 'sorular', 'href' => 'ogretmen/sorular', 'label' => 'Sorular', 'icon' => 'mail', 'badge' => function_exists('question_teacher_pending_count') ? question_teacher_pending_count((int) (current_user()['id'] ?? 0)) : 0],
                ['id' => 'mesajlar', 'href' => 'ogretmen/mesajlar', 'label' => 'Mesajlar', 'icon' => 'mail'],
            ]],
            ['label' => 'Hesap', 'items' => [
                ['id' => 'hesap', 'href' => 'ogretmen/hesap', 'label' => 'Hesabım', 'icon' => 'user'],
            ]],
        ];
    }
    if ($role === 'musteri') {
        return [
            ['label' => 'Hesap', 'items' => [
                ['id' => 'dashboard', 'href' => 'magaza', 'label' => 'Hesabım', 'icon' => 'home'],
                ['id' => 'siparisler', 'href' => 'magaza/siparisler', 'label' => 'Siparişlerim', 'icon' => 'bag'],
                ['id' => 'bildirimler', 'href' => 'magaza/bildirimler', 'label' => 'Bildirimler', 'icon' => 'mail', 'badge' => function_exists('academy_unread_count') ? academy_unread_count((int) (current_user()['id'] ?? 0)) : 0],
                ['id' => 'kitaplar', 'href' => 'magaza/kitaplarim', 'label' => 'Kitaplarım', 'icon' => 'library'],
                ['id' => 'egitimler', 'href' => 'magaza/egitimlerim', 'label' => 'Eğitimlerim', 'icon' => 'layers'],
                ['id' => 'adresler', 'href' => 'magaza/adresler', 'label' => 'Adresler', 'icon' => 'pin'],
                ['id' => 'profil', 'href' => 'magaza/profil', 'label' => 'Profilim', 'icon' => 'user'],
            ]],
        ];
    }
    return [
        ['label' => 'Yönetim', 'items' => [
            ['id' => 'dashboard', 'href' => 'admin', 'label' => 'Yönetim özeti', 'icon' => 'home'],
            ['id' => 'anasayfa', 'href' => 'admin/anasayfa', 'label' => 'Anasayfa', 'icon' => 'layers'],
            ['id' => 'kullanicilar', 'href' => 'admin/kullanicilar', 'label' => 'Kullanıcılar', 'icon' => 'users'],
            ['id' => 'hocalar', 'href' => 'admin/hocalar', 'label' => 'Hocalar', 'icon' => 'user'],
        ]],
        ['label' => 'Eğitim', 'items' => [
            ['id' => 'programlar', 'href' => 'admin/programlar', 'label' => 'Eğitimler', 'icon' => 'layers'],
            ['id' => 'gruplar', 'href' => 'admin/gruplar', 'label' => 'Gruplar', 'icon' => 'users'],
            ['id' => 'uyelikler', 'href' => 'admin/uyelikler', 'label' => 'Üyelikler', 'icon' => 'card'],
            ['id' => 'canli', 'href' => 'admin/canli', 'label' => 'Canlı', 'icon' => 'live', 'badge' => $liveN],
            ['id' => 'takvim', 'href' => 'admin/takvim', 'label' => 'Takvim', 'icon' => 'calendar'],
            ['id' => 'yazilar', 'href' => 'admin/yazilar', 'label' => 'Duyurular', 'icon' => 'mail'],
            ['id' => 'sorular', 'href' => 'admin/sorular', 'label' => 'Sorular', 'icon' => 'mail', 'badge' => function_exists('question_admin_pending_count') ? question_admin_pending_count() : 0],
        ]],
        ['label' => 'Mağaza', 'items' => [
            ['id' => 'eticaret', 'href' => 'admin/eticaret', 'label' => 'E-ticaret özeti', 'icon' => 'bag'],
            ['id' => 'siparisler', 'href' => 'admin/siparisler', 'label' => 'Siparişler', 'icon' => 'list'],
            ['id' => 'urunler', 'href' => 'admin/urunler', 'label' => 'Ürünler', 'icon' => 'box'],
            ['id' => 'kategoriler', 'href' => 'admin/kategoriler', 'label' => 'Kategoriler', 'icon' => 'layers'],
            ['id' => 'kampanyalar', 'href' => 'admin/kampanyalar', 'label' => 'Kampanyalar', 'icon' => 'card'],
        ]],
        ['label' => 'Sistem', 'items' => [
            ['id' => 'paytr', 'href' => 'admin/paytr', 'label' => 'Ödeme', 'icon' => 'card'],
            ['id' => 'seo', 'href' => 'admin/seo', 'label' => 'SEO', 'icon' => 'search'],
            ['id' => 'smtp', 'href' => 'admin/smtp', 'label' => 'SMTP', 'icon' => 'mail'],
            ['id' => 'google', 'href' => 'admin/google', 'label' => 'Google', 'icon' => 'user'],
            ['id' => 'hesap', 'href' => 'admin/hesap', 'label' => 'Hesabım', 'icon' => 'user'],
        ]],
    ];
}

function panel_href(string $href): string
{
    $pretty = ['uyelik-ders', 'sepet', 'cikis'];
    if (in_array($href, $pretty, true)) {
        return page_url($href);
    }
    return url($href);
}

function panel_role_label(string $role): string
{
    return match ($role) {
        'ogrenci' => 'Öğrenci',
        'ogretmen' => 'Öğretmen',
        'admin' => 'Yönetici',
        'musteri' => 'Mağaza',
        default => 'Panel',
    };
}

function panel_icon(string $name): string
{
    $p = match ($name) {
        'home' => '<path d="M4 11.5 12 4l8 7.5V20a1 1 0 0 1-1 1h-5.5v-6h-3v6H5a1 1 0 0 1-1-1z"/>',
        'book' => '<path d="M5 4.5A2.5 2.5 0 0 1 7.5 2H20v18H7.5A2.5 2.5 0 0 0 5 22.5z"/><path d="M5 4.5A2.5 2.5 0 0 1 7.5 7H20"/>',
        'live' => '<rect x="3" y="7" width="12" height="10" rx="1.5"/><path d="m15 10 5-3v10l-5-3z"/>',
        'calendar' => '<rect x="4" y="5" width="16" height="16" rx="2"/><path d="M8 3v4M16 3v4M4 10h16"/>',
        'play' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m10 9 5 3-5 3z"/>',
        'task' => '<rect x="7" y="3" width="10" height="18" rx="2"/><path d="M9 3v2h6V3M9 12h6M9 16h4"/>',
        'check' => '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="m8 12 3 3 5-6"/>',
        'card' => '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18"/>',
        'library' => '<path d="M4 19V5M9 19V5M14 19V8l6-2v13"/><path d="M4 19h16"/>',
        'mail' => '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="m3 8 9 6 9-6"/>',
        'users' => '<circle cx="9" cy="8" r="3"/><path d="M3 19a6 6 0 0 1 12 0"/><circle cx="17" cy="9" r="2.2"/><path d="M16 19a5 5 0 0 0 5-5"/>',
        'user' => '<circle cx="12" cy="8" r="3.2"/><path d="M5 20a7 7 0 0 1 14 0"/>',
        'list' => '<path d="M9 7h11M9 12h11M9 17h11M4 7h.01M4 12h.01M4 17h.01"/>',
        'layers' => '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>',
        'bag' => '<path d="M6 8h12l1 13H5z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
        'box' => '<path d="M3 8 12 3l9 5-9 5z"/><path d="M3 8v8l9 5 9-5V8M12 13v8"/>',
        'search' => '<circle cx="11" cy="11" r="6"/><path d="m16 16 4 4"/>',
        'pin' => '<path d="M12 21s7-5.4 7-11a7 7 0 1 0-14 0c0 5.6 7 11 7 11z"/><circle cx="12" cy="10" r="2.2"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'back' => '<path d="M15 6 9 12l6 6M9 12h12"/>',
        default => '<circle cx="12" cy="12" r="3"/>',
    };
    return '<svg class="side-ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

function panel_render_nav(array $groups, string $page): void
{
    foreach ($groups as $g) {
        echo '<p class="side-sec">' . e((string) $g['label']) . '</p>';
        echo '<div class="side-group">';
        foreach ($g['items'] as $l) {
            $cls = ($l['id'] ?? '') === $page ? 'is-on' : '';
            echo '<a class="' . $cls . '" href="' . e(panel_href((string) $l['href'])) . '">';
            echo panel_icon((string) ($l['icon'] ?? 'dot'));
            echo '<span>' . e((string) $l['label']) . '</span>';
            if (!empty($l['badge'])) {
                echo '<span class="side-badge">' . (int) $l['badge'] . '</span>';
            }
            echo '</a>';
        }
        echo '</div>';
    }
}

function panel_head(string $role, string $page, string $title, array $user): void
{
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    $titles = ['ogrenci' => 'Öğrenci Paneli', 'ogretmen' => 'Öğretmen Paneli', 'admin' => 'Yönetim Paneli', 'musteri' => 'Mağaza Hesabım'];
    $chips = ['ogrenci' => 'Öğrenci', 'ogretmen' => 'Öğretmen', 'admin' => 'Yönetici', 'musteri' => 'Mağaza'];
    $liveN = (int) db()->query("SELECT COUNT(*) FROM live_rooms WHERE status='live'")->fetchColumn();
    $nav = panel_nav($role, $page);
    $pageTitle = trim(explode('|', $title)[0]);
    ?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php security_html_head(); ?>
  <title><?= e($title) ?></title>
  <link rel="icon" href="<?= e(url('assets/img/logo.png')) ?>" type="image/png" />
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@700;800&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={theme:{extend:{colors:{navy:'#1a3fad',navy3:'#0a1a4e',accent:'#e8232a',muted:'#6e6e73',soft:'#f5f5f7'},fontFamily:{sans:['Nunito','sans-serif'],display:['Bricolage Grotesque','sans-serif']}}}}</script>
  <link rel="stylesheet" href="<?= e(url('assets/css/site.css')) ?>?v=<?= (int) @filemtime(__DIR__ . '/../assets/css/site.css') ?>" />
</head>
<body class="bg-soft" data-role="<?= e($role) ?>">
<div class="panel-shell">
  <div class="panel-backdrop" id="panel-backdrop" hidden></div>
  <aside class="side" id="panel-side">
    <div class="side-brand">
      <a href="<?= e(page_url('home')) ?>" class="side-logo">
        <img src="<?= e(url('assets/img/logo-dark.jpg')) ?>" alt="Online İlahiyat">
      </a>
      <button type="button" class="side-close" id="panel-close" aria-label="Menüyü kapat"><?= panel_icon('close') ?></button>
    </div>
    <p class="side-kicker"><?= e($titles[$role] ?? 'Panel') ?></p>
    <nav class="side-nav">
      <?php panel_render_nav($nav, $page); ?>
    </nav>
    <a class="side-back" href="<?= e(page_url('home')) ?>"><?= panel_icon('back') ?><span>Siteye dön</span></a>
  </aside>
  <div class="panel-main">
    <div class="topbar">
      <div class="topbar-left">
        <button type="button" class="side-toggle" id="panel-toggle" aria-controls="panel-side" aria-expanded="false" aria-label="Menü"><?= panel_icon('menu') ?></button>
        <div>
          <h1 class="topbar-title"><?= e($pageTitle) ?></h1>
          <?php if ($role !== 'musteri' && $liveN > 0): ?>
            <p class="topbar-live"><i></i><?= $liveN ?> canlı oda</p>
          <?php endif; ?>
        </div>
      </div>
      <div class="topbar-right">
        <?php if ($role === 'musteri'): ?>
          <a href="<?= e(page_url('sepet')) ?>" class="topbar-cart">Sepet (<?= cart_count() ?>)</a>
        <?php endif; ?>
        <span class="topbar-name"><?= e((string) $user['name']) ?></span>
        <span class="role-chip"><?= e($chips[$role] ?? panel_role_label($role)) ?></span>
        <a href="<?= e(page_url('cikis')) ?>" class="btn-outline topbar-exit">Çıkış</a>
      </div>
    </div>
    <main class="panel-body">
<?php
}

function panel_foot(): void
{
    ?>
    </main>
  </div>
</div>
<script>
(function () {
  var shell = document.querySelector('.panel-shell');
  var tog = document.getElementById('panel-toggle');
  var close = document.getElementById('panel-close');
  var bd = document.getElementById('panel-backdrop');
  if (!shell) return;
  function setOpen(on) {
    shell.classList.toggle('is-nav-open', on);
    document.body.classList.toggle('panel-nav-lock', on);
    if (tog) tog.setAttribute('aria-expanded', on ? 'true' : 'false');
    if (bd) bd.hidden = !on;
  }
  tog && tog.addEventListener('click', function () { setOpen(!shell.classList.contains('is-nav-open')); });
  close && close.addEventListener('click', function () { setOpen(false); });
  bd && bd.addEventListener('click', function () { setOpen(false); });
})();
</script>
<script>window.OI_BASE = <?= json_encode(url('')) ?>;</script>
<script src="<?= e(url('assets/js/admin-ui.js')) ?>?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/admin-ui.js') ?>"></script>
</body></html>
<?php
}

function live_pill(array $r): string
{
    if (($r['status'] ?? '') !== 'live') {
        return '<span class="text-muted">Kapalı</span>';
    }
    return '<span class="live-pill"><i></i> Canlı · ' . live_mins($r['started_at']) . ' dk · oda #' . $r['id'] . '</span>';
}
