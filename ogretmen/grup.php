<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogretmen');
$id = (int) ($_GET['id'] ?? 0);
if ($id < 1) {
    redirect('ogretmen/siniflar');
}
group_handle_teacher_post($id, (int) $u['id']);
$g = group_by_id($id, (int) $u['id']);
if (!$g) {
    groups_error('Bu sınıf size ait değil.');
    redirect('ogretmen/siniflar');
}
$counts = group_counts($id);
$roster = group_roster($id);
$upcoming = group_upcoming($id);
$rooms = group_live_rooms($id);
$hasMem = isset(group_enroll_cols()['expires_at']) || isset(users_columns()['membership_expires_at']);
$hasPhone = isset(users_columns()['phone']);
$hasAtt = group_table_exists('attendance');
$live = null;
foreach ($rooms as $r) {
    if (($r['status'] ?? '') === 'live') {
        $live = $r;
        break;
    }
}
panel_head('ogretmen', 'siniflar', (string) $g['name'] . ' | Sınıf | Öğretmen Paneli', $u);
?>
<p class="mb-4"><a class="text-sm font-extrabold text-navy" href="<?= e(url('ogretmen/siniflar')) ?>">← Sınıflarım</a></p>
<?php group_flash_html(); ?>

<section class="card p-6">
  <p class="stat-label"><?= e((string) $g['program_title']) ?></p>
  <div class="mt-1 flex flex-wrap items-start justify-between gap-3">
    <div>
      <h2 class="font-display text-3xl"><?= e((string) $g['name']) ?></h2>
      <p class="mt-2 text-sm font-bold"><?= e((string) $g['days']) ?></p>
    </div>
    <div class="flex flex-wrap gap-2">
      <?= group_cap_html((int) $counts['n'], (int) $g['cap']) ?>
      <?php if ($live): ?><?= live_pill($live) ?><?php endif; ?>
    </div>
  </div>
  <?php if (!empty($g['description'])): ?>
    <p class="mt-4 text-sm"><?= nl2br(e((string) $g['description'])) ?></p>
  <?php endif; ?>
  <div class="mt-5 flex flex-wrap gap-2">
    <a class="btn-outline text-sm" href="<?= e(url('ogretmen/ogrenciler.php?grup=' . (int) $g['id'])) ?>">Öğrenci kartları</a>
    <a class="btn-outline text-sm" href="<?= e(url('ogretmen/takvim')) ?>">Takvim</a>
    <a class="btn-outline text-sm" href="<?= e(url('ogretmen/canli')) ?>">Canlı odalar</a>
    <a class="btn-outline text-sm" href="<?= e(url('ogretmen/odevler')) ?>">Ödevler (<?= (int) $counts['hw'] ?>)</a>
    <a class="btn-outline text-sm" href="<?= e(url('ogretmen/testler')) ?>">Testler (<?= (int) $counts['test'] ?>)</a>
    <a class="btn-outline text-sm" href="<?= e(url('ogretmen/yoklama')) ?>">Yoklama</a>
    <?php if ($live): ?>
      <a class="btn-primary text-sm" href="<?= e(canli_url((int) $live['id'])) ?>">Odada devam et</a>
    <?php else: ?>
      <form method="post" action="<?= e(url('api/live.php')) ?>" class="flex flex-wrap items-end gap-2">
        <input type="hidden" name="action" value="start"><input type="hidden" name="html" value="1">
        <input type="hidden" name="group_id" value="<?= (int) $g['id'] ?>"><input type="hidden" name="topic" value="Ders">
        <input type="hidden" name="record" value="1"><input type="hidden" name="yoklama" value="1">
        <?= live_play_mode_picker('play_mode', live_last_play_mode(), 'select') ?>
        <button class="btn-primary text-sm">Bu grubu canlı aç</button>
      </form>
    <?php endif; ?>
  </div>
</section>

<div class="mt-6 grid gap-4 md:grid-cols-3">
  <div class="stat">
    <p class="stat-label">Öğrenci</p>
    <p class="stat-value text-xl"><?= (int) $counts['n'] ?> / <?= (int) $g['cap'] ?></p>
  </div>
  <div class="stat">
    <p class="stat-label">Ödevler</p>
    <p class="stat-value text-xl"><?= (int) $counts['hw'] ?></p>
  </div>
  <div class="stat">
    <p class="stat-label">Testler</p>
    <p class="stat-value text-xl"><?= (int) $counts['test'] ?></p>
  </div>
</div>

<section class="card mt-6 p-5">
  <p class="stat-label">Düzenle</p>
  <h3 class="font-display mt-1 text-xl">Ad, günler ve kontenjan</h3>
  <p class="mt-1 text-sm text-muted">Program ve hoca atamasını yönetim yapar.</p>
  <form method="post" class="mt-4 grid gap-3 md:grid-cols-2">
    <input type="hidden" name="action" value="save">
    <label class="text-sm font-bold">Grup adı
      <input name="name" required maxlength="80" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= e((string) $g['name']) ?>">
    </label>
    <label class="text-sm font-bold">Ders günleri
      <input name="days" required maxlength="80" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= e((string) $g['days']) ?>">
    </label>
    <label class="text-sm font-bold">Kontenjan
      <input type="number" name="cap" min="1" max="80" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= (int) $g['cap'] ?>">
    </label>
    <label class="text-sm font-bold md:col-span-2">Açıklama
      <textarea name="description" rows="3" maxlength="4000" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal"><?= e((string) ($g['description'] ?? '')) ?></textarea>
    </label>
    <label class="text-sm font-bold md:col-span-2">WhatsApp grubu
      <input name="whatsapp_url" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" placeholder="https://chat.whatsapp.com/..." value="<?= e((string) ($g['whatsapp_url'] ?? '')) ?>">
    </label>
    <div class="md:col-span-2">
      <button class="btn-primary">Kaydet</button>
    </div>
  </form>
</section>

<section class="card mt-6 overflow-hidden">
  <div class="px-5 py-4">
    <p class="stat-label">Öğrenciler</p>
    <h3 class="font-display mt-1 text-xl">Liste</h3>
  </div>
  <?php if (!$roster): ?>
    <p class="dash-empty px-5 pb-5">Bu sınıfta öğrenci yok.</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Öğrenci</th>
          <?php if ($hasPhone): ?><th>Telefon</th><?php endif; ?>
          <th>İlerleme</th>
          <?php if ($hasMem): ?><th>Üyelik</th><?php endif; ?>
          <?php if ($hasAtt): ?><th>Son yoklama</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($roster as $s):
            $mem = $s['membership'];
            ?>
          <tr>
            <td>
              <a class="avatar-row" href="<?= e(teacher_ogrenci_url((int) $s['student_id'])) ?>">
                <?= user_avatar_html($s, 'sm') ?>
                <span>
                  <span class="font-extrabold"><?= e((string) $s['name']) ?></span>
                  <span class="block text-xs text-muted"><?= e((string) ($s['city'] ?? '')) ?></span>
                </span>
              </a>
            </td>
            <?php if ($hasPhone): ?><td><?= e((string) ($s['phone'] ?: '—')) ?></td><?php endif; ?>
            <td>%<?= (int) $s['progress'] ?></td>
            <?php if ($hasMem): ?>
              <td><span class="<?= e(membership_kind_class((string) $mem['kind'])) ?>"><?= e((string) $mem['label']) ?></span></td>
            <?php endif; ?>
            <?php if ($hasAtt): ?><td><?= e(group_attendance_label($s['last_attendance'])) ?></td><?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<section class="card mt-6 overflow-hidden">
  <div class="px-5 py-4">
    <p class="stat-label">Takvim</p>
    <h3 class="font-display mt-1 text-xl">Yaklaşan seanslar</h3>
    <p class="mt-1 text-sm text-muted">Saat eklemek için <a class="font-extrabold text-navy" href="<?= e(url('ogretmen/takvim')) ?>">takvimi</a> kullanın.</p>
  </div>
  <?php if (!$upcoming): ?>
    <p class="dash-empty px-5 pb-5">Önümüzdeki 21 günde planlı seans yok.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>Tarih</th><th>Başlık</th><th>Durum</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($upcoming as $row):
            $start = function_exists('schedule_parse_datetime') ? schedule_parse_datetime((string) $row['starts_at']) : null;
            ?>
          <tr>
            <td class="font-extrabold"><?= e($start ? $start->format('d.m.Y H:i') : (string) $row['starts_at']) ?> · <?= (int) $row['duration_min'] ?> dk</td>
            <td><?= e((string) $row['title']) ?><?= !empty($row['topic']) ? ' · ' . e((string) $row['topic']) : '' ?></td>
            <td><?= function_exists('schedule_badge') ? schedule_badge((string) $row['display_status']) : e((string) $row['display_status']) ?></td>
            <td><?php if (!empty($row['can_join']) && !empty($row['live_room'])): ?><a class="font-extrabold text-navy" href="<?= e(canli_url((int) $row['live_room']['id'])) ?>">Sınıfa gir</a><?php elseif (!empty($row['can_open'])): ?>
              <form method="post" action="<?= e(url('api/live.php')) ?>" class="inline-flex flex-wrap items-end gap-2">
                <input type="hidden" name="action" value="start"><input type="hidden" name="html" value="1">
                <input type="hidden" name="group_id" value="<?= (int) $row['group_id'] ?>">
                <input type="hidden" name="topic" value="<?= e((string) ($row['topic'] ?: $row['title'])) ?>">
                <input type="hidden" name="record" value="1"><input type="hidden" name="yoklama" value="1">
                <?= live_play_mode_picker('play_mode', live_last_play_mode(), 'hidden') ?>
                <button class="btn-primary h-8 px-3 text-xs">Odayı aç</button>
              </form>
            <?php else: ?><a class="text-sm font-extrabold text-navy" href="<?= e(url('ogretmen/takvim')) ?>">Takvim</a><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>
<?php panel_foot();
