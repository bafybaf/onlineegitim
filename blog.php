<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$posts = db()->query('SELECT * FROM posts WHERE published=1 ORDER BY ' . catalog_order_sql('', 'posts'))->fetchAll();
public_head('Duyurular | Online İlahiyat', 'Online İlahiyat duyuru ve yazıları.');
?>
<header class="bg-soft py-14">
  <div class="mx-auto max-w-5xl px-4 lg:px-8">
    <h1 class="font-display text-4xl md:text-6xl">Duyurular</h1>
    <p class="mt-3 text-muted">Dönem, kayıt ve ders duyuruları.</p>
  </div>
</header>
<main class="mx-auto max-w-5xl px-4 py-12 lg:px-8">
  <?php if (!$posts): ?><p class="text-muted">Henüz duyuru yok.</p><?php endif; ?>
  <div class="grid gap-4">
    <?php foreach ($posts as $p): ?>
      <a class="card p-6 hover:border-navy" href="<?= e(page_url('blog', (string) $p['slug'])) ?>">
        <p class="text-xs font-extrabold uppercase text-navy"><?= e(date('d.m.Y', strtotime((string) $p['created_at']))) ?></p>
        <h2 class="font-display mt-1 text-2xl"><?= e($p['title']) ?></h2>
        <p class="mt-2 text-sm text-muted"><?= e(mb_strimwidth(strip_tags((string) $p['body']), 0, 140, '…')) ?></p>
      </a>
    <?php endforeach; ?>
  </div>
</main>
<?php public_foot();
