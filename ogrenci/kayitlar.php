<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogrenci');
if (function_exists('ensure_recordings_test_schema')) {
    ensure_recordings_test_schema();
}
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
$groups = group_apply_teacher_labels($gst->fetchAll());
$allowed = [];
foreach ($groups as $g) {
    $allowed[(int) $g['id']] = $g;
}
if ($gid && !isset($allowed[$gid])) {
    $gid = 0;
}

$sql = "SELECT rec.*, g.name gname, t.name tname, p.slug program_slug
     FROM recordings rec
     JOIN class_groups g ON g.id=rec.group_id
     JOIN users t ON t.id=rec.teacher_id
     LEFT JOIN programs p ON p.id = g.program_id
     JOIN enrollments e ON e.group_id=rec.group_id AND e.student_id=?
     WHERE (rec.is_test = 0 OR rec.is_test IS NULL)";
$params = [(int) $u['id']];
if ($gid) {
    $sql .= ' AND rec.group_id = ?';
    $params[] = $gid;
}
$sql .= ' ORDER BY rec.recorded_on DESC, rec.id DESC';
$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

[$tree, $groupNames] = recordings_lesson_tree($rows);

$current = $gid ? $allowed[$gid] : null;

function ogrenci_kayit_qs(int $gid): string
{
    return $gid > 0 ? ('?' . http_build_query(['grup' => $gid])) : '';
}
$err = flash_error();
panel_head('ogrenci', 'kayitlar', $current ? ((string) $current['name'] . ' kayıtları | Öğrenci Paneli') : 'Ders kayıtları | Öğrenci Paneli', $u);

function ogrenci_kayit_card(array $r, int $backGid): void
{
    $ready = !empty($r['video_path']) || !empty($r['video_url']);
    $watch = 'ogrenci/kayit-izle.php?id=' . (int) $r['id'];
    if ($backGid) {
        $watch .= '&grup=' . $backGid;
    }
    $label = trim((string) ($r['_lesson_sub'] ?? ''));
    if ($label === '') {
        $label = (string) $r['title'];
    }
    echo '<article class="card flex flex-wrap items-center justify-between gap-3 p-5">';
    echo '<div><p class="font-extrabold">' . e($label) . '</p>';
    echo '<p class="text-sm text-muted">' . e($r['tname']) . ' · ' . e($r['recorded_on']) . ' · ' . e(function_exists('vod_length_label') ? vod_length_label($r) : ((int) $r['mins'] . ' dk')) . '</p></div>';
    if ($ready) {
        echo '<a class="btn-primary text-sm" href="' . e(url($watch)) . '">İzle</a>';
    } else {
        echo '<span class="text-sm text-muted">Video bekleniyor</span>';
    }
    echo '</article>';
}
?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>
<?php if ($current): ?>
  <p class="mb-2"><a class="text-sm font-extrabold text-navy" href="<?= e(url('ogrenci/derslerim')) ?>">← Derslerim</a></p>
  <h2 class="font-display text-2xl"><?= e($current['name']) ?></h2>
  <p class="mb-5 text-sm text-muted"><?= e($current['teacher_name']) ?> · Bu dersin kayıtları.</p>
<?php else: ?>
  <p class="mb-5 text-sm text-muted">Kayıtlar gruba, DHBT ve DKAB’de ders kategorisine göre ayrılır. Alt kayıtları açın; yeni tarihli ders en altta durur.</p>
<?php endif; ?>

<?php if (count($groups) > 1): ?>
  <div class="mb-5 flex flex-wrap gap-2">
    <a class="<?= $gid === 0 ? 'btn-primary' : 'btn-outline' ?> text-sm" href="<?= e(url('ogrenci/kayitlar.php' . ogrenci_kayit_qs(0))) ?>">Tüm gruplar</a>
    <?php foreach ($groups as $g): ?>
      <a class="<?= $gid === (int) $g['id'] ? 'btn-primary' : 'btn-outline' ?> text-sm" href="<?= e(url('ogrenci/kayitlar.php' . ogrenci_kayit_qs((int) $g['id']))) ?>"><?= e($g['name']) ?></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (!$tree): ?>
  <div class="card p-5"><?= $gid ? 'Bu gruba ait izlenecek kayıt henüz yok.' : 'Henüz izlenecek kayıt yok.' ?></div>
<?php else:
    recordings_render_lesson_tree($tree, static function (array $r) use ($gid): void {
        ogrenci_kayit_card($r, $gid ?: (int) $r['group_id']);
    }, [
        'split_groups' => $gid === 0 && count($groupNames) > 1,
        'group_extra' => static function (array $g): string {
            return '<a class="text-sm font-extrabold text-navy" href="' . e(url('ogrenci/kayitlar.php' . ogrenci_kayit_qs((int) $g['id']))) . '">Yalnız bu grup →</a>';
        },
    ]);
endif; ?>
<?php panel_foot();
