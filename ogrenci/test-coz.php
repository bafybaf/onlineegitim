<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/tests.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogrenci');
$id = (int) ($_GET['id'] ?? 0);
$test = $id ? student_published_test($id, (int) $u['id']) : null;
if (!$test) {
    redirect('ogrenci/testler.php');
}

$attempt = test_attempt($id, (int) $u['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$attempt) {
    $questions = test_questions($id, true);
    if (!$questions) {
        redirect('ogrenci/testler.php');
    }
    $posted = $_POST['q'] ?? [];
    if (!is_array($posted)) {
        $posted = [];
    }
    $score = 0;
    $max = 0;
    $rows = [];
    foreach ($questions as $q) {
        $qid = (int) $q['id'];
        $max += (int) $q['points'];
        $choice = strtolower(trim((string) ($posted[$qid] ?? '')));
        if (!in_array($choice, test_choice_keys(), true)) {
            $choice = null;
        }
        $ok = $choice !== null && $choice === $q['correct'];
        if ($ok) {
            $score += (int) $q['points'];
        }
        $rows[] = [$qid, $choice, $ok ? 1 : 0];
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO test_attempts (test_id, student_id, score, max_score, started_at, submitted_at) VALUES (?,?,?,?,NOW(),NOW())')
            ->execute([$id, $u['id'], $score, $max]);
        $aid = (int) $pdo->lastInsertId();
        $ins = $pdo->prepare('INSERT INTO test_answers (attempt_id, question_id, choice, is_correct) VALUES (?,?,?,?)');
        foreach ($rows as $r) {
            $ins->execute([$aid, $r[0], $r[1], $r[2]]);
        }
        $pdo->commit();
    } catch (PDOException $ex) {
        $pdo->rollBack();
        if ((int) ($ex->errorInfo[1] ?? 0) !== 1062) {
            throw $ex;
        }
    }
    redirect('ogrenci/test-coz.php?id=' . $id);
}

$qs = test_questions($id, (bool) $attempt);
$answers = [];
if ($attempt) {
    $as = db()->prepare('SELECT question_id, choice, is_correct FROM test_answers WHERE attempt_id=?');
    $as->execute([$attempt['id']]);
    foreach ($as as $a) {
        $answers[(int) $a['question_id']] = $a;
    }
}

panel_head('ogrenci', 'testler', $test['title'] . ' | Öğrenci Paneli', $u);
?>
<article class="card mb-6 p-5">
  <p class="text-xs font-extrabold uppercase text-navy"><?= e($test['gname']) ?> · <?= e($test['teacher_name']) ?></p>
  <h2 class="font-display mt-1 text-2xl"><?= e($test['title']) ?></h2>
  <?php if ($test['description']): ?><p class="mt-2 text-sm text-muted"><?= e($test['description']) ?></p><?php endif; ?>
  <p class="mt-2 text-sm text-muted"><?= count($qs) ?> soru<?= $test['duration_min'] ? ' · ' . (int) $test['duration_min'] . ' dakika' : '' ?></p>
  <?php if ($attempt): ?>
    <p class="mt-4 font-display text-3xl text-navy"><?= (int) $attempt['score'] ?> / <?= (int) $attempt['max_score'] ?></p>
    <p class="font-extrabold">Yüzde <?= test_percent((int) $attempt['score'], (int) $attempt['max_score']) ?></p>
    <p class="mt-1 text-sm text-muted">Teslim: <?= e($attempt['submitted_at']) ?> · ikinci deneme yok</p>
  <?php endif; ?>
</article>

<?php if (!$qs): ?>
  <p class="text-muted">Bu testte soru yok.</p>
<?php elseif ($attempt): ?>
  <?php foreach ($qs as $i => $q):
      $ans = $answers[(int) $q['id']] ?? null;
      $mine = $ans['choice'] ?? null;
      $ok = $ans && (int) $ans['is_correct'] === 1;
      ?>
  <article class="card mb-4 p-5">
    <p class="text-xs font-extrabold uppercase text-muted">Soru <?= $i + 1 ?> · <?= (int) $q['points'] ?> puan · <?= $ok ? 'Doğru' : 'Yanlış' ?></p>
    <p class="mt-2 font-bold"><?= e($q['body']) ?></p>
    <ul class="mt-3 grid gap-1 text-sm">
      <?php foreach (test_choice_keys() as $k):
          $txt = $q['choice_' . $k];
          $mark = '';
          if ($k === $q['correct']) {
              $mark = ' · doğru cevap';
          }
          if ($mine === $k) {
              $mark .= ' · sizin işaretiniz';
          }
          ?>
        <li class="<?= $k === $q['correct'] ? 'font-extrabold text-navy' : '' ?>"><?= e(test_choice_label($k)) ?>) <?= e($txt) ?><?= $mark ? '<span class="text-muted">' . e($mark) . '</span>' : '' ?></li>
      <?php endforeach; ?>
    </ul>
  </article>
  <?php endforeach; ?>
  <a class="btn-outline text-sm" href="<?= e(url('ogrenci/testler.php')) ?>">Test listesine dön</a>
<?php else: ?>
<form method="post">
  <?php foreach ($qs as $i => $q): ?>
  <article class="card mb-4 p-5">
    <p class="text-xs font-extrabold uppercase text-muted">Soru <?= $i + 1 ?> · <?= (int) $q['points'] ?> puan</p>
    <p class="mt-2 font-bold"><?= e($q['body']) ?></p>
    <div class="mt-3 grid gap-2">
      <?php foreach (test_choice_keys() as $k): ?>
        <label class="flex cursor-pointer items-start gap-2 rounded-xl border px-3 py-2 text-sm">
          <input type="radio" name="q[<?= (int) $q['id'] ?>]" value="<?= $k ?>" required class="mt-1">
          <span><b><?= e(test_choice_label($k)) ?>)</b> <?= e($q['choice_' . $k]) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
  </article>
  <?php endforeach; ?>
  <button class="btn-primary">Testi bitir ve puanı gör</button>
</form>
<?php endif; ?>
<?php panel_foot();
