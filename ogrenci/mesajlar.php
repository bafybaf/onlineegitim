<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogrenci');
$teachers = student_teachers((int) $u['id']);
$tid = (int) ($_GET['hoca'] ?? ($teachers[0]['id'] ?? 0));
$valid = false;
foreach ($teachers as $t) {
    if ((int) $t['id'] === $tid) {
        $valid = true;
        break;
    }
}
if (!$valid) {
    $tid = (int) ($teachers[0]['id'] ?? 0);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = (int) post('teacher_id');
    $body = post('body');
    $ok = false;
    foreach ($teachers as $t) {
        if ((int) $t['id'] === $to) {
            $ok = true;
            break;
        }
    }
    if ($ok && $body !== '') {
        db()->prepare('INSERT INTO messages (thread_user_id, from_user_id, teacher_id, body) VALUES (?,?,?,?)')
            ->execute([(int) $u['id'], (int) $u['id'], $to, $body]);
        notify_user($to, 'Yeni soru', (string) $u['name'] . ': ' . mb_strimwidth($body, 0, 80, '…'), url('ogretmen/mesajlar.php?ogrenci=' . (int) $u['id']));
        redirect('ogrenci/mesajlar.php?hoca=' . $to);
    }
}
$msgs = [];
if ($tid > 0) {
    $st = db()->prepare(
        'SELECT m.*, u.name from_name FROM messages m JOIN users u ON u.id=m.from_user_id
         WHERE m.thread_user_id=? AND (m.teacher_id=? OR (m.teacher_id IS NULL AND m.from_user_id IN (?,?)))
         ORDER BY m.id'
    );
    $st->execute([(int) $u['id'], $tid, (int) $u['id'], $tid]);
    $msgs = $st->fetchAll();
}
panel_head('ogrenci', 'mesajlar', 'Mesajlar | Öğrenci Paneli', $u);
?>
<p class="mb-4 text-sm text-muted">Kayıtlı olduğunuz grubun hocasına soru sorun. Yanıtlar burada görünür.</p>
<?php if (!$teachers): ?>
  <div class="card p-5">Henüz grubunuz yok; mesaj göndermek için üyelik alın.</div>
<?php else: ?>
  <div class="mb-4 flex flex-wrap gap-2">
    <?php foreach ($teachers as $t): ?>
      <a class="btn-outline text-sm <?= $tid === (int) $t['id'] ? 'border-navy' : '' ?>" href="<?= e(url('ogrenci/mesajlar.php?hoca=' . (int) $t['id'])) ?>"><?= e($t['name']) ?></a>
    <?php endforeach; ?>
  </div>
  <div class="card p-5">
    <div class="grid gap-3">
      <?php foreach ($msgs as $m):
          $mine = (int) $m['from_user_id'] === (int) $u['id'];
          ?>
        <p class="rounded-xl p-3 <?= $mine ? 'ml-8 bg-soft' : 'bg-[#eef2ff]' ?>"><b><?= e($m['from_name']) ?></b><br><?= nl2br(e($m['body'])) ?></p>
      <?php endforeach; ?>
      <?php if (!$msgs): ?><p class="text-sm text-muted">Henüz mesaj yok. İlk sorunuzu yazın.</p><?php endif; ?>
    </div>
    <form method="post" class="mt-4 flex gap-2">
      <input type="hidden" name="teacher_id" value="<?= $tid ?>">
      <input name="body" required class="flex-1 rounded-xl border px-3 py-2" placeholder="Hocaya yazın">
      <button class="btn-primary">Gönder</button>
    </form>
  </div>
<?php endif; ?>
<?php panel_foot();
