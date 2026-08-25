<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
$id = (int) ($_GET['id'] ?? 0);
if ($id < 1) {
    redirect('admin/gruplar');
}
group_handle_admin_post($id);
$g = group_by_id($id);
if (!$g) {
    redirect('admin/gruplar');
}
$counts = group_counts($id);
$roster = group_roster($id);
$upcoming = group_upcoming($id);
$rooms = group_live_rooms($id);
$available = group_students_available($id);
$programs = group_programs();
$teachers = group_teachers();
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
panel_head('admin', 'gruplar', (string) $g['name'] . ' | Grup | Admin', $u);
?>
<p class="mb-4"><a class="text-sm font-extrabold text-navy" href="<?= e(url('admin/gruplar')) ?>">← Gruplar</a></p>
<?php group_flash_html(); ?>

<section class="card p-6">
  <p class="stat-label">Sınıf grubu</p>
  <div class="mt-1 flex flex-wrap items-start justify-between gap-3">
    <div>
      <h2 class="font-display text-3xl"><?= e((string) $g['name']) ?></h2>
      <p class="mt-1 text-sm text-muted"><?= e((string) $g['program_title']) ?> · <?= e((string) $g['teacher_name']) ?></p>
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
</section>

<div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
  <div class="stat">
    <p class="stat-label">Kontenjan</p>
    <p class="stat-value text-xl"><?= (int) $counts['n'] ?> / <?= (int) $g['cap'] ?></p>
  </div>
  <div class="stat">
    <p class="stat-label">Hoca</p>
    <p class="stat-value text-xl"><?= e((string) $g['teacher_name']) ?></p>
    <p class="stat-hint"><?= e((string) ($g['teacher_phone'] ?: $g['teacher_email'] ?: '')) ?></p>
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
  <h3 class="font-display mt-1 text-xl">Grup bilgileri</h3>
  <form method="post" class="mt-4 grid gap-3 md:grid-cols-2">
    <input type="hidden" name="action" value="save">
    <label class="text-sm font-bold">Grup adı
      <input name="name" required maxlength="80" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= e((string) $g['name']) ?>">
    </label>
    <label class="text-sm font-bold">Ders günleri
      <input name="days" required maxlength="80" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= e((string) $g['days']) ?>">
    </label>
    <label class="text-sm font-bold">Program
      <select name="program_id" required class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
        <?php foreach ($programs as $p): ?>
          <option value="<?= (int) $p['id'] ?>" <?= (int) $p['id'] === (int) $g['program_id'] ? 'selected' : '' ?>><?= e((string) $p['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="text-sm font-bold">Hoca
      <select name="teacher_id" required class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
        <?php foreach ($teachers as $t): ?>
          <option value="<?= (int) $t['id'] ?>" <?= (int) $t['id'] === (int) $g['teacher_id'] ? 'selected' : '' ?>><?= e((string) $t['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="text-sm font-bold">Kontenjan
      <input type="number" name="cap" min="1" max="80" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= (int) $g['cap'] ?>">
    </label>
    <label class="text-sm font-bold md:col-span-2">Açıklama
      <textarea name="description" rows="3" maxlength="4000" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal"><?= e((string) ($g['description'] ?? '')) ?></textarea>
    </label>
    <label class="text-sm font-bold md:col-span-2">WhatsApp grubu (isteğe bağlı)
      <input name="whatsapp_url" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" placeholder="https://chat.whatsapp.com/..." value="<?= e((string) ($g['whatsapp_url'] ?? '')) ?>">
    </label>
    <div class="md:col-span-2">
      <button class="btn-primary">Değişiklikleri kaydet</button>
    </div>
  </form>
  <div class="mt-3">
    <?= panel_delete_form(grup_url($id), ['action' => 'delete'], 'Grup silinsin mi? Öğrenciler, ödevler, testler ve takvim saatleri de silinir.', 'Grubu sil', 'btn-outline') ?>
  </div>
</section>

<section class="card mt-6 overflow-hidden">
  <div class="flex flex-wrap items-end justify-between gap-3 px-5 py-4">
    <div>
      <p class="stat-label">Öğrenciler</p>
      <h3 class="font-display mt-1 text-xl">Liste · <?= group_cap_html((int) $counts['n'], (int) $g['cap']) ?></h3>
    </div>
    <form method="post" class="flex flex-wrap items-end gap-2">
      <input type="hidden" name="action" value="add_student">
      <label class="text-xs font-bold">Öğrenci ekle
        <select name="student_id" required class="mt-1 min-w-[16rem] rounded-xl border px-3 py-2 text-sm font-normal" <?= !$available || $counts['n'] >= (int) $g['cap'] ? 'disabled' : '' ?>>
          <option value=""><?= $available ? 'Seçin' : 'Eklenecek öğrenci yok' ?></option>
          <?php foreach ($available as $s): ?>
            <option value="<?= (int) $s['id'] ?>"><?= e((string) $s['name']) ?><?= !empty($s['phone']) ? ' · ' . e((string) $s['phone']) : '' ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button class="btn-primary h-10 text-sm" <?= !$available || $counts['n'] >= (int) $g['cap'] ? 'disabled' : '' ?>>Ekle</button>
    </form>
  </div>
  <?php if (!$roster): ?>
    <p class="dash-empty px-5 pb-5">Bu grupta öğrenci yok.</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Öğrenci</th>
          <?php if ($hasPhone): ?><th>Telefon</th><?php endif; ?>
          <th>İlerleme</th>
          <?php if ($hasMem): ?><th>Üyelik</th><?php endif; ?>
          <?php if ($hasAtt): ?><th>Son yoklama</th><?php endif; ?>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($roster as $s):
            $mem = $s['membership'];
            ?>
          <tr>
            <td>
              <a class="avatar-row" href="<?= e(kullanici_url((int) $s['student_id'])) ?>">
                <?= user_avatar_html($s, 'sm') ?>
                <span>
                  <span class="font-extrabold"><?= e((string) $s['name']) ?></span>
                  <span class="block text-xs text-muted"><?= e((string) ($s['email'] ?? '')) ?></span>
                </span>
              </a>
            </td>
            <?php if ($hasPhone): ?><td><?= e((string) ($s['phone'] ?: '—')) ?></td><?php endif; ?>
            <td>%<?= (int) $s['progress'] ?></td>
            <?php if ($hasMem): ?>
              <td><span class="<?= e(membership_kind_class((string) $mem['kind'])) ?>"><?= e((string) $mem['label']) ?></span></td>
            <?php endif; ?>
            <?php if ($hasAtt): ?><td><?= e(group_attendance_label($s['last_attendance'])) ?></td><?php endif; ?>
            <td>
              <form method="post" onsubmit="return confirm('Öğrenci bu gruptan çıkarılsın mı?');">
                <input type="hidden" name="action" value="remove_student">
                <input type="hidden" name="student_id" value="<?= (int) $s['student_id'] ?>">
                <button class="text-sm font-extrabold text-red-700">Çıkar</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<section class="card mt-6 overflow-hidden">
  <div class="px-5 py-4">
    <p class="stat-label">Takvim</p>
    <h3 class="font-display mt-1 text-xl">Yaklaşan dersler</h3>
    <p class="mt-1 text-sm text-muted">Önümüzdeki 21 gün. Tüm takvim için <a class="font-extrabold text-navy" href="<?= e(url('admin/takvim')) ?>">Takvim</a>.</p>
  </div>
  <?php if (!$upcoming): ?>
    <p class="dash-empty px-5 pb-5">Planlı seans yok.</p>
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
            <td><?php if (!empty($row['can_join']) && !empty($row['live_room'])): ?><a class="font-extrabold text-navy" href="<?= e(canli_url((int) $row['live_room']['id'])) ?>">İzle</a><?php else: ?><a class="text-sm font-extrabold text-navy" href="<?= e(url('admin/takvim')) ?>">Takvim</a><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<section class="card mt-6 overflow-hidden">
  <div class="px-5 py-4">
    <p class="stat-label">Canlı</p>
    <h3 class="font-display mt-1 text-xl">Odalar</h3>
    <p class="mt-1 text-sm text-muted"><a class="font-extrabold text-navy" href="<?= e(url('admin/canli')) ?>">Tüm canlı odalar</a></p>
  </div>
  <?php if (!$rooms): ?>
    <p class="dash-empty px-5 pb-5">Bu grup için oda kaydı yok.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>Oda</th><th>Konu</th><th>Durum</th><th>Başlangıç</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rooms as $r): ?>
          <tr>
            <td class="font-extrabold">#<?= (int) $r['id'] ?> · <?= e((string) $r['title']) ?></td>
            <td><?= e((string) ($r['topic'] ?? '')) ?></td>
            <td><?= ($r['status'] ?? '') === 'live' ? live_pill($r) : 'Kapalı' ?></td>
            <td><?= e(profile_dt((string) ($r['started_at'] ?? ''))) ?></td>
            <td><?php if (($r['status'] ?? '') === 'live'): ?><a class="font-extrabold text-navy" href="<?= e(canli_url((int) $r['id'])) ?>">İzle</a><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>
<?php panel_foot();
