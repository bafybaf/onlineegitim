<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/tests.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogrenci');
$st = db()->prepare("SELECT t.*, g.name gname, te.name tname,
  (SELECT COUNT(*) FROM test_questions q WHERE q.test_id=t.id) qn,
  (SELECT COALESCE(SUM(q.points),0) FROM test_questions q WHERE q.test_id=t.id) maxp,
  a.score, a.max_score, a.submitted_at
  FROM tests t
  JOIN class_groups g ON g.id=t.group_id
  JOIN users te ON te.id=t.teacher_id
  JOIN enrollments e ON e.group_id=t.group_id AND e.student_id=?
  LEFT JOIN test_attempts a ON a.test_id=t.id AND a.student_id=? AND a.submitted_at IS NOT NULL
  WHERE t.status='yayinda'
  ORDER BY t.id DESC");
$st->execute([$u['id'], $u['id']]);
$tests = $st->fetchAll();
panel_head('ogrenci', 'testler', 'Testler | Öğrenci Paneli', $u);
?>
<p class="mb-5 text-sm text-muted">Kayıtlı olduğunuz gruplardaki yayınlanmış testler. Her testi bir kez çözebilirsiniz; puanınız kaydedilir.</p>
<?php if (!$tests): ?>
  <div class="card p-5">Şu an çözebileceğiniz yayınlanmış test yok.</div>
<?php endif; ?>
<?php foreach ($tests as $t):
    $taken = $t['submitted_at'] !== null;
    ?>
<article class="card mb-4 p-5">
  <p class="text-xs font-extrabold uppercase text-navy"><?= e($t['gname']) ?> · <?= e($t['tname']) ?><?= ($t['kind'] ?? '') === 'deneme' ? ' · Deneme' : '' ?></p>
  <h2 class="font-display mt-1 text-2xl"><?= e($t['title']) ?></h2>
  <?php if ($t['description']): ?><p class="mt-2 text-sm text-muted"><?= e($t['description']) ?></p><?php endif; ?>
  <p class="mt-2 text-sm text-muted"><?= (int) $t['qn'] ?> soru · <?= (int) $t['maxp'] ?> puan<?= $t['duration_min'] ? ' · ' . (int) $t['duration_min'] . ' dk' : '' ?></p>
  <?php if ($taken): ?>
    <p class="mt-3 font-extrabold text-navy">Sonuç: <?= (int) $t['score'] ?> / <?= (int) $t['max_score'] ?> · %<?= test_percent((int) $t['score'], (int) $t['max_score']) ?></p>
    <a class="btn-outline mt-3 text-sm" href="<?= e(url('ogrenci/test-coz.php?id=' . (int) $t['id'])) ?>">Sonucu gör</a>
    <?php if (($t['kind'] ?? '') === 'deneme'): ?>
      <a class="btn-outline mt-3 text-sm" href="<?= e(url('ogrenci/deneme-siralama.php?id=' . (int) $t['id'])) ?>">Sıralama</a>
    <?php endif; ?>
  <?php elseif ((int) $t['qn'] < 1): ?>
    <p class="mt-3 text-sm text-muted">Bu testte henüz soru yok.</p>
  <?php else: ?>
    <a class="btn-primary mt-3 text-sm" href="<?= e(url('ogrenci/test-coz.php?id=' . (int) $t['id'])) ?>">Testi çöz</a>
  <?php endif; ?>
</article>
<?php endforeach; ?>
<?php panel_foot();
