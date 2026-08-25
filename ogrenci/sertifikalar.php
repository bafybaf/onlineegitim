<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogrenci');
$st = db()->prepare(
    "SELECT e.progress, e.group_id, g.name gname FROM enrollments e JOIN class_groups g ON g.id=e.group_id WHERE e.student_id=?"
);
$st->execute([(int) $u['id']]);
$issued = [];
foreach ($st as $en) {
    $c = certificate_issue((int) $u['id'], (int) $en['group_id'], (int) $en['progress']);
    if ($c) {
        $c['gname'] = $en['gname'];
        $issued[] = $c;
    }
}
panel_head('ogrenci', 'sertifika', 'Sertifikalar | Öğrenci Paneli', $u);
?>
<p class="mb-5 text-sm text-muted">Bir grupta ilerleme yüzde 70 ve üzeriyse katılım belgesi oluşur. Yazdırılabilir sayfa açılır.</p>
<?php if (!$issued): ?>
  <div class="card p-5">Henüz belgeniz yok. İlerleme yüzde 70 olunca burada görünür.</div>
<?php endif; ?>
<?php foreach ($issued as $c): ?>
  <article class="card mb-3 flex flex-wrap items-center justify-between gap-3 p-5">
    <div>
      <p class="font-extrabold"><?= e((string) $c['gname']) ?></p>
      <p class="text-sm text-muted">Kod <?= e($c['code']) ?> · %<?= (int) $c['progress'] ?> · <?= e($c['issued_at']) ?></p>
    </div>
    <a class="btn-primary text-sm" href="<?= e(url('sertifika.php?code=' . rawurlencode((string) $c['code']))) ?>">Gör / yazdır</a>
  </article>
<?php endforeach; ?>
<?php panel_foot();
