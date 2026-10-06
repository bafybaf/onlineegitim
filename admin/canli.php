<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
ensure_live_attendance_schema();
$all = db()->query(
    "SELECT r.*, t.name teacher_name, g.name gname, g.cap,
            (SELECT COUNT(*) FROM attendance a WHERE a.room_id = r.id AND a.present = 1) present_n
     FROM live_rooms r
     JOIN users t ON t.id = r.teacher_id
     JOIN class_groups g ON g.id = r.group_id
     ORDER BY r.status = 'live' DESC, r.id DESC
     LIMIT 120"
)->fetchAll();
panel_head('admin', 'canli', 'Canlı dersler | Admin', $u);
$ok = flash_ok();
$err = flash_error();
?>
<?php if ($ok): ?><p class="mb-4 font-bold text-green-700"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>
<p class="mb-4 text-sm text-muted">Dersin açıldığı tarih ve saat, kontenjan ve o derse girebilen öğrenciler. Açık odayı kapatabilir veya detayına bakabilirsiniz.</p>
<div class="card overflow-hidden">
  <table class="table">
    <thead>
      <tr>
        <th>Ders</th>
        <th>Hoca</th>
        <th>Açılış</th>
        <th>Bitiş</th>
        <th>Giren / kontenjan</th>
        <th>Durum</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php if (!$all): ?>
      <tr><td colspan="7" class="text-muted">Henüz canlı ders yok.</td></tr>
    <?php endif; ?>
    <?php foreach ($all as $r):
        $cap = max(1, (int) $r['cap']);
        $n = (int) $r['present_n'];
        $full = $n >= $cap;
        ?>
      <tr>
        <td>
          <a class="font-extrabold text-navy" href="<?= e(url('admin/canli-oda.php?id=' . (int) $r['id'])) ?>"><?= e($r['title']) ?></a>
          <p class="text-xs text-muted"><?= e((string) $r['gname']) ?><?php
            $topic = trim((string) ($r['topic'] ?? ''));
            echo ($topic !== '' && $topic !== 'Ders') ? ' · ' . e($topic) : '';
          ?></p>
        </td>
        <td><?= e($r['teacher_name']) ?></td>
        <td><?= e(profile_dt((string) ($r['started_at'] ?? ''))) ?></td>
        <td><?= ($r['status'] ?? '') === 'live' ? '—' : e(profile_dt((string) ($r['ended_at'] ?? ''))) ?></td>
        <td>
          <span class="<?= $full ? 'mem-bad' : 'mem-ok' ?> font-extrabold"><?= $n ?> / <?= $cap ?></span>
        </td>
        <td><?= ($r['status'] ?? '') === 'live' ? live_pill($r) : 'Bitti' ?></td>
        <td>
          <a class="font-extrabold text-navy" href="<?= e(url('admin/canli-oda.php?id=' . (int) $r['id'])) ?>">Girenler</a>
          <?php if (($r['status'] ?? '') === 'live'): ?>
            · <a class="font-extrabold text-accent" href="<?= e(canli_url((int) $r['id'])) ?>">İzle</a>
            · <form class="inline" method="post" action="<?= e(url('api/live.php')) ?>"><input type="hidden" name="action" value="end"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="goto" value="admin/canli.php"><button class="font-extrabold text-muted">Kapat</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php panel_foot();
