<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogrenci');
$lives = db()->prepare("SELECT r.*, t.name teacher_name FROM live_rooms r JOIN users t ON t.id=r.teacher_id JOIN enrollments e ON e.group_id=r.group_id AND e.student_id=? AND (e.status='aktif' OR e.status IS NULL) AND (e.expires_at IS NULL OR e.expires_at > NOW()) WHERE r.status='live'");
$lives->execute([$u['id']]);
$lives = $lives->fetchAll();
$groups = db()->prepare("SELECT g.*, t.name teacher_name, e.progress FROM enrollments e JOIN class_groups g ON g.id=e.group_id JOIN users t ON t.id=g.teacher_id WHERE e.student_id=? AND (e.status='aktif' OR e.status IS NULL) AND (e.expires_at IS NULL OR e.expires_at > NOW())");
$groups->execute([$u['id']]);
$groups = $groups->fetchAll();
$openHw = db()->prepare("SELECT COUNT(*) FROM homework_subs s JOIN homework h ON h.id=s.homework_id JOIN enrollments e ON e.group_id=h.group_id AND e.student_id=s.student_id WHERE s.student_id=? AND s.status='open'");
$openHw->execute([$u['id']]);
$hwN = (int) $openHw->fetchColumn();
$books = db()->prepare('SELECT b.title, sb.status FROM student_books sb JOIN books b ON b.id=sb.book_id WHERE sb.user_id=?');
$books->execute([$u['id']]);
$books = $books->fetchAll();
$prog = $groups ? (int) round(array_sum(array_column($groups, 'progress')) / count($groups)) : 0;
$ad = trim((string) explode(' ', (string) $u['name'], 2)[0]);
$mem = live_membership_state($u, $groups ? user_enrollments((int) $u['id']) : []);
$nextCal = null;
if (function_exists('schedule_student_group_ids') && function_exists('schedule_fetch')) {
    if (function_exists('schedule_sync_statuses')) {
        schedule_sync_statuses();
    }
    $gids = schedule_student_group_ids((int) $u['id']);
    if ($gids) {
        $upcoming = schedule_fetch(schedule_now()->modify('-30 minutes'), schedule_now()->modify('+14 days'), ['group_ids' => $gids]);
        $upcoming = array_values(array_filter($upcoming, static fn(array $r): bool => !in_array($r['display_status'] ?? '', ['iptal', 'bitti'], true)));
        $nextCal = $upcoming[0] ?? null;
    }
}
panel_head('ogrenci', 'dashboard', 'Özet | Öğrenci Paneli', $u);
membership_panel_banner($u);
?>
<section class="dash-hello">
  <h2>Merhaba, <?= e($ad !== '' ? $ad : (string) $u['name']) ?></h2>
  <p>Açık canlı dersleriniz, sonraki takvim saati ve ödevleriniz burada.</p>
</section>

<section class="card profile-hero mb-6 p-5">
  <?= user_avatar_html($u, 'md') ?>
  <div>
    <p class="stat-label">Hesabım</p>
    <p class="mt-1 font-extrabold"><?= e((string) $u['name']) ?><?= !empty($u['phone']) ? ' · ' . e((string) $u['phone']) : '' ?></p>
    <p class="mt-1 <?= e(membership_kind_class((string) $mem['kind'])) ?> font-extrabold"><?= e((string) $mem['short']) ?></p>
    <a class="mt-2 inline-block text-sm font-extrabold text-navy" href="<?= e(url('ogrenci/hesap')) ?>">Profil ve paketler →</a>
  </div>
</section>

<div class="dash-stats is-4">
  <div class="stat">
    <p class="stat-label">Program</p>
    <p class="stat-value"><?= count($groups) ?></p>
    <p class="stat-hint"><?= $groups ? e(implode(', ', array_column($groups, 'name'))) : 'Kayıtlı program yok' ?></p>
  </div>
  <div class="stat">
    <p class="stat-label">İlerleme</p>
    <p class="stat-value">%<?= $prog ?></p>
    <div class="prog mt-3" aria-hidden="true"><span style="width:<?= $prog ?>%"></span></div>
  </div>
  <div class="stat">
    <p class="stat-label">Ödev</p>
    <p class="stat-value"><?= $hwN ?></p>
    <p class="stat-hint"><?= $hwN ? 'Açık ödeviniz var' : 'Bekleyen ödev yok' ?></p>
    <a class="mt-2 inline-block text-sm font-extrabold text-navy" href="<?= e(url('ogrenci/odevler')) ?>">Ödevlere git →</a>
  </div>
  <div class="stat">
    <p class="stat-label">Soru</p>
    <p class="stat-value"><?= function_exists('question_open_count') ? question_open_count((int) $u['id']) : 0 ?></p>
    <p class="stat-hint">Hocanıza sorun</p>
    <a class="mt-2 inline-block text-sm font-extrabold text-navy" href="<?= e(url('ogrenci/soru-sor')) ?>">Soru sor →</a>
  </div>
</div>

<div class="dash-grid">
  <section class="card dash-card">
    <p class="stat-label">Şimdi canlı</p>
    <h3 class="mt-1">Açık ders odası</h3>
    <?php if (!$lives): ?>
      <p class="dash-empty mt-4">Şu an kayıtlı gruplarınızda açık oda yok.</p>
      <a class="btn-outline mt-4 h-10 px-4 text-sm" href="<?= e(url('ogrenci/canli')) ?>">Canlı dersler</a>
    <?php else: ?>
      <div class="mt-4 grid gap-4">
        <?php foreach ($lives as $r): ?>
          <div class="dash-next">
            <?= live_pill($r) ?>
            <p class="font-display text-xl"><?= e((string) $r['title']) ?><?= $r['topic'] ? ' — ' . e((string) $r['topic']) : '' ?></p>
            <p class="text-sm text-muted"><?= e((string) $r['teacher_name']) ?></p>
            <a class="btn-primary mt-2 h-10 px-4 text-sm" href="<?= e(canli_url((int) $r['id'])) ?>">Bu sınıfa gir</a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="card dash-card">
    <p class="stat-label">Takvim</p>
    <h3 class="mt-1">Sonraki ders</h3>
    <?php if (!$nextCal): ?>
      <p class="dash-empty mt-4">Önümüzdeki iki haftada planlı ders görünmüyor.</p>
      <a class="btn-outline mt-4 h-10 px-4 text-sm" href="<?= e(url('ogrenci/takvim')) ?>">Takvimi aç</a>
    <?php else:
        $start = function_exists('schedule_parse_datetime') ? schedule_parse_datetime((string) $nextCal['starts_at']) : null;
        $when = '';
        if ($start && function_exists('schedule_gun_kisa') && function_exists('schedule_ay_adi')) {
            $when = schedule_gun_kisa((int) $start->format('N')) . ' ' . $start->format('j') . ' ' . schedule_ay_adi((int) $start->format('n')) . ' · ' . $start->format('H:i');
        } elseif ($start) {
            $when = $start->format('d.m.Y H:i');
        }
        ?>
      <div class="dash-next mt-4">
        <?php if (function_exists('schedule_badge')): ?><?= schedule_badge((string) ($nextCal['display_status'] ?? '')) ?><?php endif; ?>
        <p class="text-xs font-extrabold uppercase tracking-[0.12em] text-muted"><?= e($when) ?> · <?= (int) $nextCal['duration_min'] ?> dk</p>
        <p class="font-display text-xl"><?= e((string) $nextCal['title']) ?><?= !empty($nextCal['topic']) ? ' — ' . e((string) $nextCal['topic']) : '' ?></p>
        <p class="text-sm text-muted"><?= e((string) $nextCal['group_name']) ?> · <?= e((string) $nextCal['teacher_name']) ?></p>
        <div class="mt-3 flex flex-wrap gap-2">
          <?php if (!empty($nextCal['can_join']) && !empty($nextCal['live_room'])): ?>
            <a class="btn-primary h-10 px-4 text-sm" href="<?= e(canli_url((int) $nextCal['live_room']['id'])) ?>">Katıl</a>
          <?php endif; ?>
          <a class="btn-outline h-10 px-4 text-sm" href="<?= e(url('ogrenci/takvim')) ?>">Takvime git</a>
        </div>
      </div>
    <?php endif; ?>
  </section>
</div>

<?php if ($groups): ?>
<section class="card dash-card dash-groups">
  <p class="stat-label">Derslerim</p>
  <h3 class="mt-1">Kayıtlı programlar</h3>
  <?php foreach ($groups as $g):
      $gp = (int) ($g['progress'] ?? 0);
      ?>
    <div class="dash-group">
      <div class="flex flex-wrap items-baseline justify-between gap-2">
        <p class="font-extrabold"><?= e((string) $g['name']) ?></p>
        <p class="text-sm font-bold text-muted">%<?= $gp ?></p>
      </div>
      <p class="text-sm text-muted"><?= e((string) $g['teacher_name']) ?></p>
      <div class="prog mt-2" aria-hidden="true"><span style="width:<?= $gp ?>%"></span></div>
    </div>
  <?php endforeach; ?>
  <a class="mt-2 inline-block text-sm font-extrabold text-navy" href="<?= e(url('ogrenci/derslerim')) ?>">Tüm dersler →</a>
</section>
<?php endif; ?>

<section class="card dash-card dash-books">
  <div class="flex items-center justify-between gap-3">
    <div>
      <p class="stat-label">Hesap</p>
      <h3 class="mt-1">Kitaplarım</h3>
    </div>
    <a class="text-sm font-extrabold text-navy" href="<?= e(url('ogrenci/kitaplarim')) ?>">Tümü</a>
  </div>
  <?php if (!$books): ?>
    <p class="dash-empty mt-4">Henüz kitabınız yok.</p>
  <?php else: ?>
    <ul>
      <?php foreach (array_slice($books, 0, 5) as $b): ?>
        <li><span><?= e((string) $b['title']) ?></span><span class="text-muted font-bold"><?= e((string) $b['status']) ?></span></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php panel_foot();
