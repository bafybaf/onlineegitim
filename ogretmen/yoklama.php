<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogretmen');
ensure_live_attendance_schema();
$tid = (int) $u['id'];
$groups = teacher_groups($tid);
$owned = group_owned_ids($tid);
$gidFilter = (int) ($_GET['grup'] ?? 0);
if ($gidFilter > 0 && !in_array($gidFilter, $owned, true)) {
    $gidFilter = 0;
}
$odaId = (int) ($_GET['oda'] ?? 0);

$where = '(r.teacher_id = ? OR r.group_id IN (' . (count($owned) ? implode(',', array_map('intval', $owned)) : '0') . '))';
$params = [$tid];
if ($gidFilter > 0) {
    $where .= ' AND r.group_id = ?';
    $params[] = $gidFilter;
}

if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="yoklama.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Ders', 'Grup', 'Açılış', 'Öğrenci', 'E-posta', 'Giriş saati', 'Durum'], ';');
    $sql = "SELECT r.id, r.title, r.topic, r.started_at, r.status, r.group_id, g.name gname
            FROM live_rooms r
            JOIN class_groups g ON g.id = r.group_id
            WHERE $where";
    if ($odaId > 0) {
        $sql .= ' AND r.id = ?';
        $params[] = $odaId;
    }
    $sql .= ' ORDER BY r.id DESC LIMIT 80';
    $st = db()->prepare($sql);
    $st->execute($params);
    foreach ($st as $room) {
        $live = ($room['status'] ?? '') === 'live';
        foreach (live_room_attendance_roster((int) $room['id'], (int) $room['group_id']) as $s) {
            fputcsv($out, [
                trim((string) ($room['topic'] ?: $room['title'])),
                $room['gname'],
                $room['started_at'],
                $s['name'],
                $s['email'] ?? '',
                $s['entered_at'] ?? '',
                live_attendance_status_label($s, $live),
            ], ';');
        }
    }
    fclose($out);
    exit;
}

$room = null;
$roster = [];
if ($odaId > 0) {
    $st = db()->prepare(
        'SELECT r.*, g.name gname, g.cap, t.name teacher_name
         FROM live_rooms r
         JOIN class_groups g ON g.id = r.group_id
         JOIN users t ON t.id = r.teacher_id
         WHERE r.id = ?'
    );
    $st->execute([$odaId]);
    $room = $st->fetch();
    if (!$room || !live_teacher_can_see_room($room, $tid)) {
        flash_error('Bu ders yoklamasını göremezsiniz.');
        redirect('ogretmen/yoklama');
    }
    $roster = live_room_attendance_roster($odaId, (int) $room['group_id']);
}

$history = [];
if (!$room) {
    $st = db()->prepare(
        "SELECT r.id, r.title, r.topic, r.started_at, r.ended_at, r.status, g.name gname, g.cap,
                (SELECT COUNT(*) FROM attendance a WHERE a.room_id = r.id AND (a.present = 1 OR a.entered_at IS NOT NULL)) girdi_n,
                (SELECT COUNT(*) FROM enrollments e WHERE e.group_id = r.group_id) n
         FROM live_rooms r
         JOIN class_groups g ON g.id = r.group_id
         WHERE $where
         ORDER BY r.id DESC
         LIMIT 80"
    );
    $st->execute($params);
    $history = $st->fetchAll();
}

panel_head('ogretmen', 'yoklama', $room ? ('Yoklama · ' . $room['title'] . ' | Öğretmen') : 'Yoklama | Öğretmen Paneli', $u);

if ($room):
    $live = ($room['status'] ?? '') === 'live';
    $girdi = 0;
    foreach ($roster as $s) {
        if (!empty($s['entered_at']) || (int) $s['present'] === 1) {
            $girdi++;
        }
    }
    $n = count($roster);
    $back = 'ogretmen/yoklama' . ($gidFilter ? ('?grup=' . $gidFilter) : '');
    ?>
<p class="mb-4 text-sm"><a class="font-extrabold text-navy" href="<?= e(url($back)) ?>">← Yoklama</a></p>
<div class="card mb-5 p-5">
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div>
      <p class="stat-label"><?= e((string) $room['gname']) ?></p>
      <h1 class="font-display mt-1 text-2xl"><?= e((string) ($room['topic'] ?: $room['title'])) ?></h1>
      <p class="mt-1 text-sm text-muted"><?= e(profile_dt((string) $room['started_at'])) ?><?= !empty($room['ended_at']) ? ' — ' . e(profile_dt((string) $room['ended_at'])) : '' ?> · <?= e((string) $room['teacher_name']) ?></p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <?= $live ? live_pill($room) : '<span class="text-sm font-extrabold text-muted">Bitti</span>' ?>
      <?php if ($live): ?>
        <a class="btn-primary text-sm" href="<?= e(canli_url($odaId)) ?>">Odaya git</a>
      <?php endif; ?>
      <a class="btn-outline text-sm" href="<?= e(url('ogretmen/yoklama.php?export=1&oda=' . $odaId . ($gidFilter ? '&grup=' . $gidFilter : ''))) ?>">CSV indir</a>
    </div>
  </div>
  <div class="mt-5 grid gap-3 sm:grid-cols-3">
    <div class="stat"><p class="stat-label">Kayıtlı</p><p class="stat-value text-lg"><?= $n ?> öğrenci</p></div>
    <div class="stat"><p class="stat-label">Giren</p><p class="stat-value text-lg"><?= $girdi ?></p></div>
    <div class="stat"><p class="stat-label">Girmeyen</p><p class="stat-value text-lg"><?= max(0, $n - $girdi) ?></p></div>
  </div>
</div>
<div class="card overflow-hidden">
  <div class="px-5 py-4">
    <p class="stat-label">Kim girdi</p>
    <h2 class="font-display mt-1 text-xl">Öğrenci listesi</h2>
    <p class="mt-1 text-sm text-muted">Giriş saati, öğrencinin odaya ilk girdiği andır. Dersten çıkanlar da burada kalır.</p>
  </div>
  <?php if (!$roster): ?>
    <p class="dash-empty px-5 pb-5">Bu grupta kayıtlı öğrenci yok.</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>#</th>
          <th>Öğrenci</th>
          <th>İletişim</th>
          <th>Giriş saati</th>
          <th>Durum</th>
        </tr>
      </thead>
      <tbody>
        <?php $i = 0; foreach ($roster as $s): $i++;
            $ok = !empty($s['entered_at']) || (int) $s['present'] === 1;
            $stLabel = live_attendance_status_label($s, $live);
            ?>
          <tr>
            <td><?= $i ?></td>
            <td class="font-extrabold"><a class="hover:text-navy" href="<?= e(url('ogretmen/ogrenci.php?id=' . (int) $s['id'])) ?>"><?= e((string) $s['name']) ?></a></td>
            <td class="text-sm text-muted"><?= e((string) ($s['email'] ?? '')) ?><?= !empty($s['phone']) ? '<br>' . e((string) $s['phone']) : '' ?></td>
            <td><?= $ok ? e(profile_dt((string) ($s['entered_at'] ?? ''))) : '—' ?></td>
            <td><?= $ok ? '<span class="mem-ok font-extrabold">' . e($stLabel) . '</span>' : '<span class="text-muted">' . e($stLabel) . '</span>' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php
else:
    $exportQs = 'export=1' . ($gidFilter ? '&grup=' . $gidFilter : '');
    ?>
<p class="mb-2 text-sm text-muted">Hangi derse kim girdi: odayı açın, giriş saatini ve girmeyenleri görün.</p>
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
  <form method="get" class="flex flex-wrap items-center gap-2">
    <label class="text-sm font-bold">Grup
      <select name="grup" class="ml-1 rounded-xl border px-3 py-2 font-normal" onchange="this.form.submit()">
        <option value="0">Tüm sınıflar</option>
        <?php foreach ($groups as $g): ?>
          <option value="<?= (int) $g['id'] ?>" <?= $gidFilter === (int) $g['id'] ? 'selected' : '' ?>><?= e((string) $g['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </form>
  <a class="btn-outline text-sm" href="<?= e(url('ogretmen/yoklama.php?' . $exportQs)) ?>">CSV / Excel indir</a>
</div>
<?php if (!$history): ?>
  <p class="text-muted">Henüz canlı ders yok. Oda açınca yoklama burada birikir.</p>
<?php endif; ?>
<?php foreach ($history as $h):
    $live = ($h['status'] ?? '') === 'live';
    $girdi = (int) $h['girdi_n'];
    $n = (int) $h['n'];
    $detay = 'ogretmen/yoklama.php?oda=' . (int) $h['id'] . ($gidFilter ? '&grup=' . $gidFilter : '');
    ?>
  <article class="card mb-3 flex flex-wrap items-center justify-between gap-3 p-5">
    <div>
      <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-muted"><?= e((string) $h['gname']) ?></p>
      <p class="font-extrabold"><?= e((string) ($h['topic'] ?: $h['title'])) ?></p>
      <p class="text-sm text-muted"><?= e(profile_dt((string) $h['started_at'])) ?><?= $live ? ' · canlı' : '' ?></p>
    </div>
    <div class="flex flex-wrap items-center gap-3">
      <p class="text-sm font-bold"><?= $girdi ?> / <?= $n ?> girdi</p>
      <a class="btn-primary text-sm" href="<?= e(url($detay)) ?>">Kim girdi</a>
    </div>
  </article>
<?php endforeach; ?>
<?php
endif;
panel_foot();
