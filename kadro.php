<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$hocalar = db()->query("SELECT * FROM users WHERE role='ogretmen' ORDER BY " . catalog_order_sql('', 'users'))->fetchAll();
$imgs = ['assets/img/hoca.jpg', 'assets/img/kitaplik.jpg', 'assets/img/ogrenci.jpg', 'assets/img/hero-kuran.jpg'];
public_head('Kadromuz | Online İlahiyat');
?>
<header class="bg-soft py-6">
  <div class="mx-auto max-w-7xl px-4 lg:px-8">
    <h1 class="font-display text-2xl md:text-3xl">Kadromuz</h1>
    <p class="mt-3 max-w-2xl text-muted">Tefsir, hadis, Arapça ve kıraat hocaları. Aynı anda birden fazla canlı oda açabilirler.</p>
  </div>
</header>
<main class="mx-auto max-w-7xl px-4 py-12 lg:px-8">
  <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    <?php foreach ($hocalar as $i => $h):
        $photo = function_exists('user_avatar_src') ? user_avatar_src($h['avatar'] ?? null) : '';
        if ($photo === '') {
            $photo = url($imgs[$i % 4]);
        }
        ?>
    <a class="card overflow-hidden hover:border-navy" href="<?= e(page_url('hoca', teacher_public_slug($h))) ?>">
      <img src="<?= e($photo) ?>" alt="" class="h-48 w-full object-cover">
      <div class="p-5">
        <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-navy"><?= e($h['city']) ?></p>
        <h2 class="font-display mt-1 text-2xl"><?= e($h['name']) ?></h2>
        <p class="mt-2 text-sm text-muted"><?= e($h['bio']) ?></p>
        <p class="mt-3 text-sm font-extrabold text-navy">Profili gör →</p>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</main>
<?php public_foot();