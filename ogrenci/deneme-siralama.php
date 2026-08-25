<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/tests.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogrenci');
$id = (int) ($_GET['id'] ?? 0);
$st = db()->prepare(
    "SELECT t.*, g.name gname FROM tests t
     JOIN class_groups g ON g.id=t.group_id
     JOIN enrollments e ON e.group_id=t.group_id AND e.student_id=?
     WHERE t.id=? AND t.status='yayinda'"
);
$st->execute([(int) $u['id'], $id]);
$t = $st->fetch();
if (!$t || ($t['kind'] ?? '') !== 'deneme') {
    redirect('ogrenci/testler');
}
$rs = db()->prepare(
    'SELECT a.score, a.max_score, a.submitted_at, u.name
     FROM test_attempts a JOIN users u ON u.id=a.student_id
     WHERE a.test_id=? AND a.submitted_at IS NOT NULL
     ORDER BY a.score DESC, a.submitted_at ASC'
);
$rs->execute([$id]);
$rows = $rs->fetchAll();
panel_head('ogrenci', 'testler', 'Deneme sıralaması | Öğrenci Paneli', $u);
?>
<p class="mb-4"><a class="text-sm font-extrabold text-navy" href="<?= e(url('ogrenci/testler')) ?>">← Testler</a></p>
<article class="card mb-6 p-5">
  <p class="text-xs font-extrabold uppercase text-navy"><?= e($t['gname']) ?></p>
  <h2 class="font-display mt-1 text-2xl"><?= e($t['title']) ?></h2>
  <p class="mt-1 text-sm text-muted">Puan sırası. İsimler grubunuzdaki öğrenciler içindir.</p>
</article>
<div class="card overflow-x-auto p-5">
  <table class="w-full text-left text-sm">
    <thead><tr class="border-b text-xs uppercase text-muted"><th class="py-2">Sıra</th><th>Öğrenci</th><th>Puan</th><th>Yüzde</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $i => $r): ?>
      <tr class="border-t <?= (string) $r['name'] === (string) $u['name'] ? 'bg-soft font-extrabold' : '' ?>">
        <td class="py-2"><?= $i + 1 ?></td>
        <td><?= e($r['name']) ?></td>
        <td><?= (int) $r['score'] ?> / <?= (int) $r['max_score'] ?></td>
        <td>%<?= test_percent((int) $r['score'], (int) $r['max_score']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td class="py-4 text-muted" colspan="4">Henüz çözüm yok.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php panel_foot();
