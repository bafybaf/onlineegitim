<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogrenci');
$groups = question_student_groups((int) $u['id']);
$focus = (int) ($_GET['id'] ?? 0);
$ok = flash_ok();
$err = flash_error();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $qid = question_create((int) $u['id'], (int) post('group_id'), post('body'));
        flash_ok('Sorunuz hocanıza iletildi.');
        redirect('ogrenci/soru-sor.php?id=' . $qid);
    } catch (Throwable $e) {
        flash_error($e->getMessage());
        redirect('ogrenci/soru-sor');
    }
}
$st = db()->prepare(
    "SELECT q.*, g.name gname, t.name tname
     FROM student_questions q
     JOIN users t ON t.id = q.teacher_id
     LEFT JOIN class_groups g ON g.id = q.group_id
     WHERE q.student_id = ?
     ORDER BY q.id DESC"
);
$st->execute([(int) $u['id']]);
$rows = $st->fetchAll();
panel_head('ogrenci', 'soru', 'Soru sor | Öğrenci Paneli', $u);
?>
<?php if ($ok): ?><p class="mb-4 font-bold text-green-700"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>

<section class="card mb-6 p-5">
  <p class="stat-label">Hocaya yazın</p>
  <h2 class="font-display mt-1 text-2xl">Soru sor</h2>
  <p class="mt-1 text-sm text-muted">Dersle ilgili sorunuzu yazın; yanıt burada görünür.</p>
  <?php if (!$groups): ?>
    <p class="mt-4 text-sm text-muted">Kayıtlı olduğunuz bir grup yok. Soru sormak için üyelik alın.</p>
    <a class="btn-primary mt-4 inline-flex text-sm" href="<?= e(url('uyelik-ders')) ?>">Üyelik al</a>
  <?php else: ?>
    <form method="post" class="mt-4 grid gap-3">
      <label class="text-sm font-bold">Ders
        <select name="group_id" required class="mt-1 w-full rounded-xl border px-3 py-2">
          <?php foreach ($groups as $g): ?>
            <option value="<?= (int) $g['id'] ?>"><?= e((string) $g['name']) ?> · <?= e((string) $g['teacher_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="text-sm font-bold">Sorunuz
        <textarea name="body" required minlength="8" maxlength="2000" rows="4" class="mt-1 w-full rounded-xl border px-3 py-2" placeholder="Anlamadığım kısım…"></textarea>
      </label>
      <button class="btn-primary justify-self-start">Gönder</button>
    </form>
  <?php endif; ?>
</section>

<?php if (!$rows): ?>
  <div class="card p-5 text-sm text-muted">Henüz soru göndermediniz.</div>
<?php endif; ?>
<?php foreach ($rows as $q):
    $done = !empty($q['answered_at']);
    $on = $focus === (int) $q['id'];
    ?>
  <article class="card mb-4 p-5 <?= $on ? 'border-navy' : '' ?>" id="q-<?= (int) $q['id'] ?>">
    <div class="flex flex-wrap items-start justify-between gap-2">
      <div>
        <p class="text-xs font-extrabold uppercase text-navy"><?= e((string) ($q['gname'] ?: 'Ders')) ?> · <?= e((string) $q['tname']) ?></p>
        <p class="mt-1 text-sm text-muted"><?= e((string) $q['created_at']) ?></p>
      </div>
      <span class="rounded-full px-3 py-1 text-xs font-extrabold <?= $done ? 'bg-[#eef2ff] text-navy' : 'bg-soft text-muted' ?>"><?= $done ? 'Cevaplandı' : 'Bekliyor' ?></span>
    </div>
    <p class="mt-3 whitespace-pre-wrap"><?= e((string) $q['body']) ?></p>
    <?php if ($done): ?>
      <div class="mt-4 rounded-2xl bg-[#eef2ff] p-4">
        <p class="text-xs font-extrabold uppercase text-navy">Hocanın yanıtı</p>
        <p class="mt-2 whitespace-pre-wrap"><?= e((string) $q['answer']) ?></p>
        <p class="mt-2 text-xs text-muted"><?= e((string) $q['answered_at']) ?></p>
      </div>
    <?php else: ?>
      <p class="mt-3 text-sm text-muted">Hocanız yanıtlayınca burada görünür.</p>
    <?php endif; ?>
  </article>
<?php endforeach; ?>
<?php panel_foot();
