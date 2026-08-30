<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogrenci');
$gid = (int) ($_GET['grup'] ?? 0);

$gst = db()->prepare(
    "SELECT g.id, g.name, t.name teacher_name
     FROM enrollments e
     JOIN class_groups g ON g.id=e.group_id
     JOIN users t ON t.id=g.teacher_id
     WHERE e.student_id=?
     ORDER BY g.name"
);
$gst->execute([(int) $u['id']]);
$groups = $gst->fetchAll();
$allowed = [];
foreach ($groups as $g) {
    $allowed[(int) $g['id']] = $g;
}
if ($gid && !isset($allowed[$gid])) {
    $gid = 0;
}

$sql = "SELECT rec.*, g.name gname, t.name tname
     FROM recordings rec
     JOIN class_groups g ON g.id=rec.group_id
     JOIN users t ON t.id=rec.teacher_id
     JOIN enrollments e ON e.group_id=rec.group_id AND e.student_id=?";
$params = [(int) $u['id']];
if ($gid) {
    $sql .= ' AND rec.group_id=?';
    $params[] = $gid;
}
$sql .= ' ORDER BY g.name ASC, rec.recorded_on DESC, rec.id DESC';
$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$current = $gid ? $allowed[$gid] : null;
panel_head('ogrenci', 'kayitlar', $current ? ((string) $current['name'] . ' kayıtları | Öğrenci Paneli') : 'Ders kayıtları | Öğrenci Paneli', $u);

function ogrenci_kayit_card(array $r, int $backGid): void
{
    $ready = !empty($r['video_path']) || !empty($r['video_url']);
    $watch = 'ogrenci/kayit-izle.php?id=' . (int) $r['id'];
    if ($backGid) {
        $watch .= '&grup=' . $backGid;
    }
    echo '<article class="card mb-3 flex flex-wrap items-center justify-between gap-3 p-5">';
    echo '<div><p class="font-extrabold">' . e($r['title']) . '</p>';
    echo '<p class="text-sm text-muted">' . e($r['tname']) . ' · ' . e($r['recorded_on']) . ' · ' . (int) $r['mins'] . ' dk</p></div>';
    if ($ready) {
        echo '<a class="btn-primary text-sm" href="' . e(url($watch)) . '">İzle</a>';
    } else {
        echo '<span class="text-sm text-muted">Video bekleniyor</span>';
    }
    echo '</article>';
}
?>
<?php if ($current): ?>
  <p class="mb-2"><a class="text-sm font-extrabold text-navy" href="<?= e(url('ogrenci/derslerim')) ?>">← Derslerim</a></p>
  <h2 class="font-display text-2xl"><?= e($current['name']) ?></h2>
  <p class="mb-5 text-sm text-muted"><?= e($current['teacher_name']) ?> · Bu dersin kayıtları.</p>
<?php else: ?>
  <p class="mb-5 text-sm text-muted">Kayıtlar derse göre ayrılır. Derslerim’den bir ders seçebilir veya aşağıdan süzebilirsiniz.</p>
<?php endif; ?>

<?php if (count($groups) > 1): ?>
  <div class="mb-5 flex flex-wrap gap-2">
    <a class="<?= $gid === 0 ? 'btn-primary' : 'btn-outline' ?> text-sm" href="<?= e(url('ogrenci/kayitlar')) ?>">Tüm dersler</a>
    <?php foreach ($groups as $g): ?>
      <a class="<?= $gid === (int) $g['id'] ? 'btn-primary' : 'btn-outline' ?> text-sm" href="<?= e(url('ogrenci/kayitlar.php?grup=' . (int) $g['id'])) ?>"><?= e($g['name']) ?></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (!$rows): ?>
  <div class="card p-5"><?= $gid ? 'Bu derse ait izlenecek kayıt henüz yok.' : 'Henüz izlenecek kayıt yok.' ?></div>
<?php elseif ($gid): ?>
  <?php foreach ($rows as $r) {
      ogrenci_kayit_card($r, $gid);
  } ?>
<?php else:
    $last = null;
    foreach ($rows as $r):
        $key = (int) $r['group_id'];
        if ($key !== $last):
            $last = $key;
            ?>
    <div class="mb-3 <?= $last === (int) $rows[0]['group_id'] ? 'mt-0' : 'mt-8' ?> flex flex-wrap items-end justify-between gap-2">
      <div>
        <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-navy">Ders</p>
        <h2 class="font-display text-2xl"><?= e($r['gname']) ?></h2>
      </div>
      <a class="text-sm font-extrabold text-navy" href="<?= e(url('ogrenci/kayitlar.php?grup=' . $key)) ?>">Yalnız bu ders →</a>
    </div>
            <?php
        endif;
        ogrenci_kayit_card($r, $key);
    endforeach;
endif; ?>
<?php panel_foot();
