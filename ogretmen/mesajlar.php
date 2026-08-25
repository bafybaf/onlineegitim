<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogretmen');
$sid = (int) ($_GET['ogrenci'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = (int) post('student_id');
    $body = post('body');
    $chk = db()->prepare(
        'SELECT e.student_id FROM enrollments e JOIN class_groups g ON g.id=e.group_id WHERE e.student_id=? AND g.teacher_id=?'
    );
    $chk->execute([$to, (int) $u['id']]);
    if ($chk->fetch() && $body !== '') {
        db()->prepare('INSERT INTO messages (thread_user_id, from_user_id, teacher_id, body) VALUES (?,?,?,?)')
            ->execute([$to, (int) $u['id'], (int) $u['id'], $body]);
        notify_user($to, 'Hocanız yanıtladı', mb_strimwidth($body, 0, 80, '…'), url('ogrenci/mesajlar.php?hoca=' . (int) $u['id']));
        redirect('ogretmen/mesajlar.php?ogrenci=' . $to);
    }
}
$threads = db()->prepare(
    "SELECT u.id, u.name, u.email, MAX(m.id) last_id, SUBSTRING_INDEX(GROUP_CONCAT(m.body ORDER BY m.id DESC SEPARATOR '\n'), '\n', 1) last_body
     FROM messages m
     JOIN users u ON u.id = m.thread_user_id
     WHERE m.teacher_id = ? OR (m.teacher_id IS NULL AND (m.from_user_id = ? OR m.thread_user_id IN (
       SELECT e.student_id FROM enrollments e JOIN class_groups g ON g.id=e.group_id WHERE g.teacher_id=?
     )))
     GROUP BY u.id, u.name, u.email
     ORDER BY last_id DESC"
);
$threads->execute([(int) $u['id'], (int) $u['id'], (int) $u['id']]);
$threads = $threads->fetchAll();
if ($sid < 1 && $threads) {
    $sid = (int) $threads[0]['id'];
}
$msgs = [];
$stu = null;
if ($sid > 0) {
    $st = db()->prepare('SELECT id, name, email FROM users WHERE id=? AND role=?');
    $st->execute([$sid, 'ogrenci']);
    $stu = $st->fetch();
    if ($stu) {
        $ms = db()->prepare(
            'SELECT m.*, u.name from_name FROM messages m JOIN users u ON u.id=m.from_user_id
             WHERE m.thread_user_id=? AND (m.teacher_id=? OR m.teacher_id IS NULL OR m.from_user_id IN (?,?))
             ORDER BY m.id'
        );
        $ms->execute([$sid, (int) $u['id'], $sid, (int) $u['id']]);
        $msgs = $ms->fetchAll();
    }
}
panel_head('ogretmen', 'mesajlar', 'Mesajlar | Öğretmen Paneli', $u);
?>
<p class="mb-4 text-sm text-muted">Öğrencilerinizin soruları. Thread öğrenci–hoca çifti olarak saklanır.</p>
<div class="grid gap-4 lg:grid-cols-[260px_1fr]">
  <aside class="card p-3">
    <?php if (!$threads): ?><p class="p-2 text-sm text-muted">Henüz gelen mesaj yok.</p><?php endif; ?>
    <?php foreach ($threads as $t): ?>
      <a class="mb-1 block rounded-xl px-3 py-2 <?= $sid === (int) $t['id'] ? 'bg-soft' : '' ?>" href="<?= e(url('ogretmen/mesajlar.php?ogrenci=' . (int) $t['id'])) ?>">
        <span class="block font-extrabold"><?= e($t['name']) ?></span>
        <span class="block truncate text-xs text-muted"><?= e((string) $t['last_body']) ?></span>
      </a>
    <?php endforeach; ?>
  </aside>
  <section class="card p-5">
    <?php if (!$stu): ?>
      <p class="text-muted">Bir öğrenci seçin.</p>
    <?php else: ?>
      <h2 class="font-display text-2xl"><?= e($stu['name']) ?></h2>
      <p class="text-sm text-muted"><?= e($stu['email']) ?></p>
      <div class="mt-4 grid gap-3">
        <?php foreach ($msgs as $m):
            $mine = (int) $m['from_user_id'] === (int) $u['id'];
            ?>
          <p class="rounded-xl p-3 <?= $mine ? 'ml-8 bg-[#eef2ff]' : 'mr-8 bg-soft' ?>"><b><?= e($m['from_name']) ?></b><br><?= nl2br(e($m['body'])) ?></p>
        <?php endforeach; ?>
      </div>
      <form method="post" class="mt-4 flex gap-2">
        <input type="hidden" name="student_id" value="<?= (int) $stu['id'] ?>">
        <input name="body" required class="flex-1 rounded-xl border px-3 py-2" placeholder="Yanıt yazın">
        <button class="btn-primary">Gönder</button>
      </form>
    <?php endif; ?>
  </section>
</div>
<?php panel_foot();
