<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogretmen');
$groups = group_list((int) $u['id']);
panel_head('ogretmen', 'siniflar', 'Sınıflarım | Öğretmen Paneli', $u);
group_flash_html();
?>
<p class="mb-5 text-sm text-muted">Size atanan gruplar. Detaya girince liste, ilerleme ve yaklaşan seanslar görünür. Program veya hoca atamasını yalnızca yönetim değiştirir.</p>

<?php if (!$groups): ?>
  <p class="dash-empty">Henüz size atanmış sınıf yok.</p>
<?php endif; ?>

<div class="grid gap-4">
<?php foreach ($groups as $g):
    $next = $g['next_session'];
    $nextStart = $next && function_exists('schedule_parse_datetime') ? schedule_parse_datetime((string) $next['starts_at']) : null;
    ?>
  <article class="card p-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <p class="stat-label"><?= e((string) $g['program_title']) ?></p>
        <h2 class="font-display mt-1 text-2xl"><a class="hover:text-navy" href="<?= e(ogretmen_grup_url((int) $g['id'])) ?>"><?= e((string) $g['name']) ?></a></h2>
        <p class="mt-2 text-sm text-muted"><?= e((string) $g['days']) ?></p>
      </div>
      <div class="text-right">
        <p class="stat-label">Kontenjan</p>
        <p class="mt-1"><?= group_cap_html((int) $g['n'], (int) $g['cap']) ?></p>
        <?php if (!empty($g['live_room'])): ?>
          <div class="mt-2"><?= live_pill($g['live_room']) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php if (!empty($g['description'])): ?>
      <p class="mt-3 text-sm"><?= e(mb_strimwidth((string) $g['description'], 0, 180, '…')) ?></p>
    <?php endif; ?>
    <p class="mt-3 text-sm text-muted">
      <?= (int) $g['hw_n'] ?> ödev · <?= (int) $g['test_n'] ?> test
      <?php if ($next): ?>
        · sonraki seans <?= e($nextStart ? $nextStart->format('d.m.Y H:i') : (string) $next['starts_at']) ?>
        <?= !empty($next['title']) ? ' · ' . e((string) $next['title']) : '' ?>
      <?php endif; ?>
    </p>
    <div class="mt-4 flex flex-wrap gap-2">
      <a class="btn-primary text-sm" href="<?= e(ogretmen_grup_url((int) $g['id'])) ?>">Sınıf detayı</a>
      <a class="btn-outline text-sm" href="<?= e(url('ogretmen/takvim')) ?>">Takvim</a>
      <a class="btn-outline text-sm" href="<?= e(url('ogretmen/canli')) ?>">Canlı</a>
      <a class="btn-outline text-sm" href="<?= e(url('ogretmen/odevler')) ?>">Ödevler</a>
      <a class="btn-outline text-sm" href="<?= e(url('ogretmen/testler')) ?>">Testler</a>
      <?php if (!empty($g['live_room'])): ?>
        <a class="btn-primary text-sm" href="<?= e(canli_url((int) $g['live_room']['id'])) ?>">Odada devam et</a>
      <?php else: ?>
        <form method="post" action="<?= e(url('api/live.php')) ?>">
          <input type="hidden" name="action" value="start"><input type="hidden" name="html" value="1">
          <input type="hidden" name="group_id" value="<?= (int) $g['id'] ?>"><input type="hidden" name="topic" value="Ders">
          <input type="hidden" name="record" value="1"><input type="hidden" name="yoklama" value="1">
          <button class="btn-outline text-sm">Bu grubu canlı aç</button>
        </form>
      <?php endif; ?>
    </div>
  </article>
<?php endforeach; ?>
</div>
<?php panel_foot();
