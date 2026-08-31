<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$slug = trim((string) ($_GET['slug'] ?? ''));
$st = db()->prepare('SELECT * FROM posts WHERE slug=? AND published=1');
$st->execute([$slug]);
$p = $st->fetch();
if (!$p) {
    http_response_code(404);
    public_head('Yazı bulunamadı | Online İlahiyat');
    echo '<main class="mx-auto max-w-7xl px-4 py-16 lg:px-8"><h1 class="font-display text-4xl">Yazı bulunamadı</h1><p class="mt-3"><a class="font-extrabold text-navy" href="' . e(page_url('blog')) . '">Duyurulara dön</a></p></main>';
    public_foot();
    exit;
}
public_head($p['title'] . ' | Duyurular', mb_strimwidth(strip_tags((string) $p['body']), 0, 160, ''));
?>
<header class="bg-soft py-14">
  <div class="mx-auto max-w-7xl px-4 lg:px-8">
    <p class="text-xs font-extrabold uppercase text-navy"><?= e(date('d.m.Y', strtotime((string) $p['created_at']))) ?></p>
    <h1 class="font-display mt-2 text-4xl md:text-6xl"><?= e($p['title']) ?></h1>
  </div>
</header>
<main class="mx-auto max-w-7xl px-4 py-12 lg:px-8">
  <div class="text-lg leading-relaxed"><?= nl2br(e((string) $p['body'])) ?></div>
  <p class="mt-10"><a class="font-extrabold text-navy" href="<?= e(page_url('blog')) ?>">← Tüm duyurular</a></p>
</main>
<?php public_foot();
