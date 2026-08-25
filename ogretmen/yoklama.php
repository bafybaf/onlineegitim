<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogretmen');

if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="yoklama.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Oda', 'Grup', 'Tarih', 'Öğrenci', 'Durum'], ';');
    $st = db()->prepare(
        "SELECT r.title, g.name gname, r.started_at, u.name, COALESCE(a.present,0) present
         FROM live_rooms r
         JOIN class_groups g ON g.id=r.group_id
         JOIN enrollments e ON e.group_id=r.group_id
         JOIN users u ON u.id=e.student_id
         LEFT JOIN attendance a ON a.room_id=r.id AND a.student_id=u.id
         WHERE r.teacher_id=?
         ORDER BY r.id DESC, u.name"
    );
    $st->execute([(int) $u['id']]);
    foreach ($st as $row) {
        fputcsv($out, [
            $row['title'],
            $row['gname'],
            $row['started_at'],
            $row['name'],
            ((int) $row['present']) ? 'Var' : 'Yok',
        ], ';');
    }
    fclose($out);
    exit;
}

$live = db()->prepare("SELECT * FROM live_rooms WHERE teacher_id=? AND status='live'");
$live->execute([$u['id']]);
$history = db()->prepare(
    "SELECT r.id, r.title, r.started_at, g.name gname,
            (SELECT COUNT(*) FROM attendance a WHERE a.room_id=r.id AND a.present=1) present_n,
            (SELECT COUNT(*) FROM enrollments e WHERE e.group_id=r.group_id) n
     FROM live_rooms r JOIN class_groups g ON g.id=r.group_id
     WHERE r.teacher_id=? ORDER BY r.id DESC LIMIT 20"
);
$history->execute([(int) $u['id']]);
$history = $history->fetchAll();
panel_head('ogretmen', 'yoklama', 'Yoklama | Öğretmen Paneli', $u);
?>
<p class="mb-4"><a class="btn-outline text-sm" href="<?= e(url('ogretmen/yoklama.php?export=1')) ?>">CSV / Excel indir</a></p>
<?php
$any = false;
foreach ($live as $r) {
    $any = true;
    $rows = db()->prepare('SELECT u.id, u.name, COALESCE(a.present,0) present FROM enrollments e JOIN users u ON u.id=e.student_id LEFT JOIN attendance a ON a.student_id=u.id AND a.room_id=? WHERE e.group_id=?');
    $rows->execute([$r['id'], $r['group_id']]);
    echo '<article class="card mb-4 overflow-hidden"><div class="flex items-center justify-between border-b px-5 py-3">' . live_pill($r) . ' <b>' . e($r['title']) . '</b></div><table class="table"><thead><tr><th>Öğrenci</th><th>Durum</th></tr></thead><tbody>';
    foreach ($rows as $s) {
        echo '<tr><td>' . e($s['name']) . '</td><td>' . ($s['present'] ? 'Var' : 'Yok') . '</td></tr>';
    }
    echo '</tbody></table></article>';
}
if (!$any) {
    echo '<p class="mb-6 text-muted">Açık odanız yok. Geçmiş yoklama aşağıdadır.</p>';
}
?>
<h2 class="font-display mb-3 text-2xl">Son odalar</h2>
<?php foreach ($history as $h): ?>
  <article class="card mb-3 flex flex-wrap items-center justify-between gap-3 p-5">
    <div>
      <p class="font-extrabold"><?= e($h['title']) ?></p>
      <p class="text-sm text-muted"><?= e($h['gname']) ?> · <?= e((string) $h['started_at']) ?></p>
    </div>
    <p class="text-sm font-bold"><?= (int) $h['present_n'] ?> / <?= (int) $h['n'] ?> var</p>
  </article>
<?php endforeach; ?>
<?php panel_foot();
