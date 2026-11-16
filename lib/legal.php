<?php
declare(strict_types=1);

function legal_company(): array
{
    return [
        'brand' => 'Online İlahiyat',
        'unvan' => 'Aydıner Basım İç ve Dış Ticaret Ltd. Şti.',
        'address' => 'Zübeyde Hanım Mahallesi Elif Sokak No:7 /70 Altındağ / Ankara',
        'email' => 'info@onlineilahiyat.com.tr',
        'phone' => '',
        'kep' => '',
        'mersis' => '',
        'site' => 'https://www.onlineilahiyat.com',
        'vergi_no' => '1161000406',
        'vergi_daire' => 'Kızılbey Vergi Dairesi',
        'odeme' => 'Sipay Elektronik Para ve Ödeme Hizmetleri A.Ş.',
        'odeme_kisa' => 'Sipay',
        'iade_kargo' => 'Yurtiçi Kargo',
    ];
}

function legal_updated(): string
{
    return '16.11.2026';
}

function legal_contact_channels(): string
{
    $c = legal_company();
    $parts = ['e-posta: ' . $c['email']];
    if (trim((string) ($c['phone'] ?? '')) !== '') {
        $parts[] = 'telefon: ' . $c['phone'];
    }
    $parts[] = 'iletişim formu: ' . rtrim($c['site'], '/') . '/iletisim';
    return implode('; ', $parts);
}

function legal_link(string $name, string $label): string
{
    return '<a class="font-extrabold text-navy underline decoration-accent/60 underline-offset-2" href="'
        . e(page_url($name)) . '" target="_blank" rel="noopener">' . e($label) . '</a>';
}

function legal_consent_sales(bool $digital = true, bool $kvkk = true): void
{
    ?>
    <div class="legal-consents mt-4 grid gap-2 text-sm">
      <label class="flex items-start gap-2">
        <input type="checkbox" name="accept_sales" value="1" required class="mt-1 shrink-0">
        <span><?= legal_link('mesafeli-satis', 'Ön Bilgilendirme Formu ve Mesafeli Satış Sözleşmesi') ?> ile <?= legal_link('iptal-iade', 'Cayma, İptal ve İade Şartları') ?>’nı okudum, anladım ve elektronik ortamda onaylıyorum.</span>
      </label>
      <?php if ($kvkk): ?>
      <label class="flex items-start gap-2">
        <input type="checkbox" name="accept_kvkk" value="1" required class="mt-1 shrink-0">
        <span><?= legal_link('kvkk', 'KVKK Aydınlatma Metni') ?>’ni okudum ve anladım. Bu kutu 6698 sayılı Kanun m.10 aydınlatmasıdır; açık rıza yerine geçmez.</span>
      </label>
      <?php endif; ?>
      <?php if ($digital): ?>
      <label class="flex items-start gap-2">
        <input type="checkbox" name="accept_digital" value="1" required class="mt-1 shrink-0">
        <span>Dijital eğitim içeriğinin ve/veya anında ifa edilen hizmetin ifasına cayma süresi dolmadan başlanmasını açıkça talep ediyor; 6502 sayılı Kanun ve Mesafeli Sözleşmeler Yönetmeliği m.15/ğ ile m.15/h uyarınca bu edimler için cayma hakkımı kaybedeceğimi biliyorum.</span>
      </label>
      <?php endif; ?>
    </div>
    <?php
}

function legal_consent_register(): void
{
    ?>
    <div class="legal-consents mt-4 grid gap-2 text-sm">
      <label class="flex items-start gap-2">
        <input type="checkbox" name="accept_privacy" value="1" required class="mt-1 shrink-0">
        <span><?= legal_link('gizlilik', 'Gizlilik Politikası') ?> ve <?= legal_link('cerez-politikasi', 'Çerez Politikası') ?>’nı okudum, anladım.</span>
      </label>
      <label class="flex items-start gap-2">
        <input type="checkbox" name="accept_kvkk" value="1" required class="mt-1 shrink-0">
        <span><?= legal_link('kvkk', 'KVKK Aydınlatma Metni') ?>’ni okudum ve anladım. Bu kutu açık rıza değildir; hesabın kurulması 6698 sayılı Kanun m.5/2-c hukuki sebebine dayanır.</span>
      </label>
    </div>
    <?php
}

function legal_consent_contact(): void
{
    ?>
    <label class="flex items-start gap-2 text-sm">
      <input type="checkbox" name="accept_kvkk" value="1" required class="mt-1 shrink-0">
      <span><?= legal_link('kvkk', 'KVKK Aydınlatma Metni') ?>’ni okudum ve anladım. Talebimin karşılanması 6698 sayılı Kanun m.5/2-c kapsamında sözleşmenin kurulması / ifası hukuki sebebine dayanır.</span>
    </label>
    <?php
}

function legal_sales_accepted(?bool $digital = null): bool
{
    if (post('accept_sales') !== '1' || post('accept_kvkk') !== '1') {
        return false;
    }
    if ($digital === true && post('accept_digital') !== '1') {
        return false;
    }
    return true;
}

function legal_register_accepted(): bool
{
    return post('accept_privacy') === '1' && post('accept_kvkk') === '1';
}

function legal_contact_accepted(): bool
{
    return post('accept_kvkk') === '1';
}

function legal_sales_error(): string
{
    return 'Satın almadan önce ön bilgilendirme formunu, mesafeli satış sözleşmesini, cayma-iade şartlarını ve KVKK aydınlatma metnini okuduğunuzu onaylamanız gerekir.';
}

function legal_register_error(): string
{
    return 'Kayıt için gizlilik politikasını ve KVKK aydınlatma metnini okuduğunuzu onaylamanız gerekir.';
}

function legal_google_gate(): void
{
    ?>
    <script>
    document.querySelectorAll('.btn-google').forEach(function (a) {
      a.addEventListener('click', function (ev) {
        var root = a.closest('form') || document;
        var boxes = root.querySelectorAll('input[name="accept_kvkk"], input[name="accept_privacy"], input[name="accept_sales"]');
        for (var i = 0; i < boxes.length; i++) {
          if (!boxes[i].checked) {
            ev.preventDefault();
            if (typeof boxes[i].reportValidity === 'function') boxes[i].reportValidity();
            else alert('Devam etmeden önce yasal metinleri okuduğunuzu onaylayın.');
            return;
          }
        }
      });
    });
    </script>
    <?php
}

function legal_page_open(string $title, string $desc, string $h1): void
{
    public_head($title, $desc);
    echo '<main class="legal-doc mx-auto max-w-3xl px-4 py-14 lg:px-8">';
    echo '<p class="badge">Yasal</p>';
    echo '<h1 class="font-display mt-4 text-4xl">' . e($h1) . '</h1>';
    echo '<p class="mt-2 text-xs text-muted">Son güncelleme: ' . e(legal_updated()) . '</p>';
}

function legal_page_close(): void
{
    echo '</main>';
    public_foot();
}

function legal_company_block(): void
{
    $c = legal_company();
    ?>
    <div class="legal-firm mt-6 rounded-2xl border border-[#e5e5e7] bg-soft p-5 text-sm">
      <p class="font-extrabold"><?= e($c['unvan']) ?></p>
      <p class="mt-1 text-muted">Ticari unvan · <?= e($c['brand']) ?></p>
      <p class="mt-3"><?= e($c['address']) ?></p>
      <p class="mt-1">Vergi dairesi / no: <?= e($c['vergi_daire']) ?> · <?= e($c['vergi_no']) ?></p>
      <?php if (trim((string) ($c['mersis'] ?? '')) !== ''): ?>
      <p class="mt-1">MERSİS: <?= e($c['mersis']) ?></p>
      <?php endif; ?>
      <?php if (trim((string) ($c['phone'] ?? '')) !== ''): ?>
      <p class="mt-1">Telefon: <?= e($c['phone']) ?></p>
      <?php endif; ?>
      <p class="mt-1"><a class="font-extrabold text-navy" href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></p>
      <p class="mt-1"><a class="font-extrabold text-navy" href="<?= e($c['site']) ?>"><?= e($c['site']) ?></a></p>
    </div>
    <?php
}
