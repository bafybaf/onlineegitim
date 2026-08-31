<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
$focus = (int) ($_GET['id'] ?? 0);
$durum = (string) ($_GET['durum'] ?? 'bekleyen');
if (!in_array($durum, ['bekleyen', 'cevapli', 'hepsi'], true)) {
    $durum = 'bekleyen';
}
$ok = flash_ok();
$err = flash_error();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        question_answer($u, (int) post('qid'), post('answer'));
        flash_ok('Yanıt gönderildi.');
        redirect('admin/sorular.php?id=' . (int) post('qid') . '&durum=hepsi');
    } catch (Throwable $e) {
        flash_error($e->getMessage());
        redirect('admin/sorular.php?id=' . (int) post('qid'));
    }
}
$sql = "SELECT q.*, u.name sname, u.email semail, g.name gname, pr.title pname
        FROM student_questions q
        LEFT JOIN users u ON u.id = q.student_id
        LEFT JOIN class_groups g ON g.id = q.group_id
        LEFT JOIN programs pr ON pr.id = q.program_id
        WHERE 1=1";
if ($durum === 'bekleyen') {
    $sql .= ' AND q.answered_at IS NULL';
} elseif ($durum === 'cevapli') {
    $sql .= ' AND q.answered_at IS NOT NULL';
}
$sql .= ' ORDER BY (q.answered_at IS NULL) DESC, q.id DESC';
$rows = db()->query($sql)->fetchAll();
$pending = question_admin_pending_count();
panel_head('admin', 'sorular', 'Sorular | Admin', $u);
?>
<?php if ($ok): ?><p class="mb-4 font-bold text-green-700"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>

<p class="mb-4 text-sm text-muted">Program sayfasından ve öğrenci panelinden gelen sorular. Yanıt öğrenciye veya e-postasına gider.</p>
<div class="mb-4 flex flex-wrap gap-2">
  <a class="btn-outline text-sm <?= $durum === 'bekleyen' ? 'border-navy' : '' ?>" href="<?= e(url('admin/sorular.php?durum=bekleyen')) ?>">Bekleyen<?= $pending ? ' (' . $pending . ')' : '' ?></a>
  <a class="btn-outline text-sm <?= $durum === 'cevapli' ? 'border-navy' : '' ?>" href="<?= e(url('admin/sorular.php?durum=cevapli')) ?>">Cevaplanan</a>
  <a class="btn-outline text-sm <?= $durum === 'hepsi' ? 'border-navy' : '' ?>" href="<?= e(url('admin/sorular.php?durum=hepsi')) ?>">Tümü</a>
</div>

<?php if (!$rows): ?>
  <div class="card p-5 text-sm text-muted">Bu listede soru yok.</div>
<?php endif; ?>
<?php foreach ($rows as $q):
    $done = !empty($q['answered_at']);
    $on = $focus === (int) $q['id'];
    ?>
  <article class="card mb-4 p-5 <?= $on ? 'border-navy' : '' ?>" id="q-<?= (int) $q['id'] ?>">
    <div class="flex flex-wrap items-start justify-between gap-2">
      <div>
        <p class="text-xs font-extrabold uppercase text-navy"><?= e(question_row_context($q)) ?><?= (($q['source'] ?? '') === 'program') ? ' · Program formu' : '' ?></p>
        <h2 class="font-display mt-1 text-2xl"><?= e(question_row_name($q)) ?></h2>
        <p class="text-sm text-muted"><?= e(question_row_email($q)) ?> · <?= e((string) $q['created_at']) ?></p>
      </div>
      <span class="rounded-full px-3 py-1 text-xs font-extrabold <?= $done ? 'bg-[#eef2ff] text-navy' : 'bg-soft text-muted' ?>"><?= $done ? 'Cevaplandı' : 'Bekliyor' ?></span>
    </div>
    <p class="mt-4 whitespace-pre-wrap"><?= e((string) $q['body']) ?></p>
    <form method="post" class="mt-4 grid gap-3">
      <input type="hidden" name="qid" value="<?= (int) $q['id'] ?>">
      <label class="text-sm font-bold">Yanıtınız
        <textarea name="answer" required minlength="2" maxlength="4000" rows="4" class="mt-1 w-full rounded-xl border px-3 py-2"><?= e((string) ($q['answer'] ?? '')) ?></textarea>
      </label>
      <button class="btn-primary justify-self-start text-sm"><?= $done ? 'Yanıtı güncelle' : 'Yanıtla' ?></button>
    </form>
  </article>
<?php endforeach; ?>
<?php panel_foot();
