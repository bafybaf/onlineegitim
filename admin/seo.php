<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $robots = post('seo_robots') === 'noindex,nofollow' ? 'noindex,nofollow' : 'index,follow';
    $pairs = [
        'seo_site_title' => post('seo_site_title') ?: 'Online İlahiyat',
        'seo_title_suffix' => post('seo_title_suffix'),
        'seo_default_description' => post('seo_default_description'),
        'seo_keywords' => post('seo_keywords'),
        'seo_og_image' => post('seo_og_image'),
        'seo_robots' => $robots,
        'seo_google_analytics' => post('seo_google_analytics'),
        'seo_google_site_verification' => post('seo_google_site_verification'),
        'seo_canonical_base' => rtrim(post('seo_canonical_base'), '/'),
        'seo_home_title' => post('seo_home_title'),
        'seo_home_description' => post('seo_home_description'),
        'seo_home_h1' => post('seo_home_h1'),
    ];
    foreach ($pairs as $k => $v) {
        setting_set($k, $v);
    }
    $saved = true;
}
panel_head('admin', 'seo', 'SEO ayarları | Admin', $u);
?>
<?php if ($saved): ?><p class="mb-4 font-bold text-green-700">SEO ayarları kaydedildi.</p><?php endif; ?>
<div class="grid gap-6 lg:grid-cols-[1fr_320px]">
  <form method="post" class="card grid gap-4 p-6">
    <p class="text-sm text-muted">Başlık, açıklama, robots ve sosyal paylaşım etiketleri tüm genel sayfalara uygulanır. Pretty URL’ler otomatik canonical üretir.</p>
    <label class="text-sm font-bold">Site başlığı
      <input name="seo_site_title" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e(setting('seo_site_title', 'Online İlahiyat')) ?>">
    </label>
    <label class="text-sm font-bold">Başlık eki
      <input name="seo_title_suffix" class="mt-1 w-full rounded-xl border px-3 py-2" placeholder=" | Online İlahiyat" value="<?= e(setting('seo_title_suffix', ' | Online İlahiyat')) ?>">
    </label>
    <label class="text-sm font-bold">Varsayılan açıklama
      <textarea name="seo_default_description" rows="3" class="mt-1 w-full rounded-xl border px-3 py-2"><?= e(setting('seo_default_description')) ?></textarea>
    </label>
    <label class="text-sm font-bold">Anahtar kelimeler
      <input name="seo_keywords" class="mt-1 w-full rounded-xl border px-3 py-2" placeholder="ilahiyat, tefsir, hadis" value="<?= e(setting('seo_keywords')) ?>">
    </label>
    <label class="text-sm font-bold">OG görsel (yol veya URL)
      <input name="seo_og_image" class="mt-1 w-full rounded-xl border px-3 py-2" placeholder="assets/img/hero-cami.jpg" value="<?= e(setting('seo_og_image')) ?>">
    </label>
    <label class="text-sm font-bold">Robots
      <select name="seo_robots" class="mt-1 w-full rounded-xl border px-3 py-2">
        <option value="index,follow" <?= setting('seo_robots', 'index,follow') !== 'noindex,nofollow' ? 'selected' : '' ?>>index,follow</option>
        <option value="noindex,nofollow" <?= setting('seo_robots') === 'noindex,nofollow' ? 'selected' : '' ?>>noindex,nofollow</option>
      </select>
    </label>
    <label class="text-sm font-bold">Canonical taban adresi
      <input name="seo_canonical_base" class="mt-1 w-full rounded-xl border px-3 py-2" placeholder="https://www.siteniz.com/online-ilahiyat" value="<?= e(setting('seo_canonical_base')) ?>">
      <span class="mt-1 block text-xs font-normal text-muted">Boşsa site_url veya otomatik host + BASE kullanılır.</span>
    </label>
    <label class="text-sm font-bold">Google Analytics (G-xxxx veya UA)
      <input name="seo_google_analytics" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e(setting('seo_google_analytics')) ?>">
    </label>
    <label class="text-sm font-bold">Google site doğrulama
      <input name="seo_google_site_verification" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e(setting('seo_google_site_verification')) ?>">
    </label>
    <p class="pt-2 text-xs font-extrabold uppercase tracking-[0.16em] text-muted">Ana sayfa (isteğe bağlı)</p>
    <label class="text-sm font-bold">Ana sayfa başlığı
      <input name="seo_home_title" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e(setting('seo_home_title')) ?>">
    </label>
    <label class="text-sm font-bold">Ana sayfa açıklaması
      <textarea name="seo_home_description" rows="2" class="mt-1 w-full rounded-xl border px-3 py-2"><?= e(setting('seo_home_description')) ?></textarea>
    </label>
    <label class="text-sm font-bold">Ana sayfa H1
      <input name="seo_home_h1" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e(setting('seo_home_h1')) ?>">
    </label>
    <button class="btn-primary">Kaydet</button>
  </form>
  <aside class="grid gap-4">
    <div class="card p-5">
      <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-muted">Pretty URL</p>
      <p class="mt-2 break-all text-sm font-bold"><?= e(page_url('programlar')) ?></p>
      <p class="mt-1 break-all text-sm text-muted"><?= e(page_url('program', 'tefsir')) ?></p>
      <p class="mt-1 break-all text-sm text-muted"><?= e(page_url('kitap', 'tefsir-ozet')) ?></p>
    </div>
    <div class="card p-5">
      <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-muted">Canonical</p>
      <p class="mt-2 break-all text-sm"><?= e(canonical_base()) ?></p>
    </div>
  </aside>
</div>
<?php panel_foot();
