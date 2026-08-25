<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/tests.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogretmen');
$id = (int) ($_GET['id'] ?? 0);
$cur = $id ? teacher_test($id, (int) $u['id']) : null;
if (!$cur) {
    redirect('ogretmen/testler.php');
}
$rs = db()->prepare('SELECT a.*, u.name, u.email FROM test_attempts a JOIN users u ON u.id=a.student_id WHERE a.test_id=? AND a.submitted_at IS NOT NULL ORDER BY a.score DESC, a.submitted_at ASC');
$rs->execute([$cur['id']]);
$results = $rs->fetchAll();
$qn = count(test_questions((int) $cur['id']));
panel_head('ogretmen', 'testler', 'Test sonuçları | Öğretmen Paneli', $u);
?>
<article class="card mb-6 p-5">
  <p class="text-xs font-extrabold uppercase text-navy"><?= e($cur['gname']) ?></p>
  <h2 class="font-display mt-1 text-2xl"><?= e($cur['title']) ?></h2>
  <p class="mt-1 text-sm text-muted"><?= $qn ?> soru · <?= count($results) ?> öğrenci çözdü · max <?= test_max_points((int) $cur['id']) ?> puan</p>
  <a class="btn-outline mt-4 text-sm" href="<?= e(url('ogretmen/testler.php?id=' . (int) $cur['id'])) ?>">Testi düzenle</a>
</article>
<div class="card overflow-x-auto p-5">
  <table class="w-full text-left text-sm">
    <thead><tr class="border-b text-xs uppercase tracking-wide text-muted"><th class="py-2">Sıra</th><th>Öğrenci</th><th>Puan</th><th>Yüzde</th><th>Teslim</th></tr></thead>
    <tbody>
    <?php if (!$results): ?>
      <tr><td class="py-4 text-muted" colspan="5">Henüz çözüm yok.</td></tr>
    <?php endif; ?>
    <?php foreach ($results as $i => $r): ?>
      <tr class="border-t">
        <td class="py-2"><?= $i + 1 ?></td>
        <td><b><?= e($r['name']) ?></b><br><span class="text-xs text-muted"><?= e($r['email']) ?></span></td>
        <td><?= (int) $r['score'] ?> / <?= (int) $r['max_score'] ?></td>
        <td>%<?= test_percent((int) $r['score'], (int) $r['max_score']) ?></td>
        <td><?= e($r['submitted_at']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php panel_foot();
