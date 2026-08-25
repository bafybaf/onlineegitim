<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
public_head('Programlar | Online İlahiyat');
?>
<header class="bg-soft py-14">
  <div class="mx-auto max-w-7xl px-4 lg:px-8">
    <p class="text-xs font-extrabold uppercase tracking-[0.22em] text-accent">Eğitim satış</p>
    <h1 class="font-display mt-2 text-4xl md:text-6xl">Programlar</h1>
  </div>
</header>
<main class="mx-auto max-w-7xl px-4 py-12 lg:px-8">
  <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    <?php foreach (programs() as $p): ?>
    <article class="card overflow-hidden hover:border-navy">
      <?= program_gallery_html($p, 'card', page_url('program', (string) $p['slug'])) ?>
      <a href="<?= e(page_url('program', (string) $p['slug'])) ?>" class="block p-5">
        <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-navy"><?= e($p['level']) ?></p>
        <h2 class="font-display mt-2 text-2xl"><?= e($p['title']) ?></h2>
        <p class="mt-2 text-sm text-muted"><?= e($p['hours']) ?> · <?= e($p['tag']) ?></p>
        <p class="mt-4"><span class="price-old mr-2"><?= money($p['price_old']) ?></span><span class="price-now"><?= money($p['price_now']) ?> / yıl</span></p>
      </a>
    </article>
    <?php endforeach; ?>
  </div>
</main>
<?php public_foot();