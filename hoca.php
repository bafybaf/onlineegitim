<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$slug = trim((string) ($_GET['slug'] ?? ''));
$st = db()->prepare("SELECT * FROM users WHERE role='ogretmen' AND slug=?");
$st->execute([$slug]);
$h = $st->fetch();
if (!$h) {
    http_response_code(404);
    public_head('Hoca bulunamadı | Online İlahiyat');
    echo '<main class="mx-auto max-w-3xl px-4 py-16"><h1 class="font-display text-4xl">Hoca bulunamadı</h1><p class="mt-3"><a class="font-extrabold text-navy" href="' . e(page_url('kadro')) . '">Kadromuza dön</a></p></main>';
    public_foot();
    exit;
}
$groups = db()->prepare(
    'SELECT g.*, p.title program_title, p.slug program_slug FROM class_groups g JOIN programs p ON p.id=g.program_id WHERE g.teacher_id=? ORDER BY g.name'
);
$groups->execute([(int) $h['id']]);
$groups = $groups->fetchAll();
$upcoming = [];
if (function_exists('schedule_fetch')) {
    $gids = array_map(static fn(array $g): int => (int) $g['id'], $groups);
    if ($gids) {
        $upcoming = schedule_fetch(schedule_now(), schedule_now()->modify('+21 days'), ['group_ids' => $gids]);
        $upcoming = array_slice($upcoming, 0, 6);
    }
}
public_head($h['name'] . ' | Kadro | Online İlahiyat', (string) ($h['bio'] ?? ''));
?>
<header class="bg-soft py-6">
  <div class="mx-auto max-w-5xl px-4 lg:px-8">
    <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-navy"><?= e((string) ($h['city'] ?? 'Hoca')) ?></p>
    <h1 class="font-display mt-2 text-2xl md:text-3xl"><?= e($h['name']) ?></h1>
    <?php if (!empty($h['bio'])): ?><p class="mt-4 max-w-2xl text-muted"><?= e($h['bio']) ?></p><?php endif; ?>
  </div>
</header>
<main class="mx-auto max-w-5xl px-4 py-12 lg:px-8">
  <h2 class="font-display text-3xl">Eğitimler</h2>
  <div class="mt-4 grid gap-3 md:grid-cols-2">
    <?php foreach ($groups as $g): ?>
      <a class="card p-5 hover:border-navy" href="<?= e(page_url('program', (string) $g['program_slug'])) ?>">
        <p class="text-xs font-extrabold uppercase text-navy"><?= e($g['program_title']) ?></p>
        <h3 class="font-display mt-1 text-2xl"><?= e($g['name']) ?></h3>
        <p class="mt-1 text-sm text-muted"><?= e($g['days']) ?></p>
      </a>
    <?php endforeach; ?>
    <?php if (!$groups): ?><p class="text-muted">Henüz atanmış grup yok.</p><?php endif; ?>
  </div>
  <?php if ($upcoming): ?>
    <h2 class="font-display mt-12 text-3xl">Yaklaşan canlı dersler</h2>
    <ul class="mt-4 grid gap-2">
      <?php foreach ($upcoming as $ev): ?>
        <li class="card p-4 text-sm"><b><?= e((string) $ev['title']) ?></b> · <?= e((string) ($ev['starts_at'] ?? '')) ?> · <?= e((string) ($ev['group_name'] ?? '')) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <p class="mt-10"><a class="btn-primary" href="<?= e(page_url('kayit-ders')) ?>">Bu hocanın programına kayıt</a></p>
</main>
<?php public_foot();
