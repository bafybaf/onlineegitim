<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/tests.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogretmen');

function test_redirect(?int $id = null): void
{
    redirect('ogretmen/testler.php' . ($id ? '?id=' . $id : ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    $tid = (int) post('test_id');

    if ($action === 'create' && post('title')) {
        $gid = (int) post('group_id');
        if (!teacher_owns_group((int) $u['id'], $gid)) {
            test_redirect();
        }
        $dur = post('duration_min');
        $duration = $dur !== '' ? max(1, min(180, (int) $dur)) : null;
        $kind = post('kind') === 'deneme' ? 'deneme' : 'quiz';
        db()->prepare('INSERT INTO tests (teacher_id, group_id, title, description, duration_min, status, kind) VALUES (?,?,?,?,?,?,?)')
            ->execute([(int) $u['id'], $gid, post('title'), post('description'), $duration, 'taslak', $kind]);
        test_redirect((int) db()->lastInsertId());
    }

    $row = $tid ? teacher_test($tid, (int) $u['id']) : null;
    if (!$row) {
        test_redirect();
    }

    if ($action === 'update') {
        $dur = post('duration_min');
        $duration = $dur !== '' ? max(1, min(180, (int) $dur)) : null;
        db()->prepare('UPDATE tests SET title=?, description=?, duration_min=? WHERE id=? AND teacher_id=?')
            ->execute([post('title'), post('description'), $duration, $tid, $u['id']]);
        test_redirect($tid);
    }

    if ($action === 'toggle') {
        $qn = count(test_questions($tid));
        $next = $row['status'] === 'yayinda' ? 'taslak' : 'yayinda';
        if ($next === 'yayinda' && $qn < 1) {
            test_redirect($tid);
        }
        db()->prepare('UPDATE tests SET status=? WHERE id=? AND teacher_id=?')->execute([$next, $tid, $u['id']]);
        test_redirect($tid);
    }

    if ($action === 'delete') {
        db()->prepare('DELETE FROM tests WHERE id=? AND teacher_id=?')->execute([$tid, $u['id']]);
        test_redirect();
    }

    $correct = strtolower(post('correct'));
    if (!in_array($correct, test_choice_keys(), true)) {
        $correct = 'a';
    }
    $points = max(1, min(100, (int) post('points', '10')));

    if ($action === 'add_q' && post('body') && post('choice_a') && post('choice_b') && post('choice_c') && post('choice_d')) {
        $mx = db()->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM test_questions WHERE test_id=?');
        $mx->execute([$tid]);
        $ord = (int) $mx->fetchColumn();
        db()->prepare('INSERT INTO test_questions (test_id, body, choice_a, choice_b, choice_c, choice_d, correct, points, sort_order) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$tid, post('body'), post('choice_a'), post('choice_b'), post('choice_c'), post('choice_d'), $correct, $points, $ord]);
        test_redirect($tid);
    }

    if ($action === 'edit_q' || $action === 'del_q') {
        $qid = (int) post('qid');
        $own = db()->prepare('SELECT q.id FROM test_questions q JOIN tests t ON t.id=q.test_id WHERE q.id=? AND t.teacher_id=?');
        $own->execute([$qid, $u['id']]);
        if (!$own->fetch()) {
            test_redirect($tid);
        }
        if ($action === 'del_q') {
            db()->prepare('DELETE FROM test_questions WHERE id=?')->execute([$qid]);
        } else {
            db()->prepare('UPDATE test_questions SET body=?, choice_a=?, choice_b=?, choice_c=?, choice_d=?, correct=?, points=? WHERE id=?')
                ->execute([post('body'), post('choice_a'), post('choice_b'), post('choice_c'), post('choice_d'), $correct, $points, $qid]);
        }
        test_redirect($tid);
    }

    test_redirect($tid);
}

$groups = db()->prepare('SELECT * FROM class_groups WHERE teacher_id=?');
$groups->execute([$u['id']]);
$groups = $groups->fetchAll();
$gids = array_column($groups, 'id') ?: [0];
$tests = db()->query('SELECT t.*, g.name gname,
  (SELECT COUNT(*) FROM test_questions q WHERE q.test_id=t.id) qn,
  (SELECT COUNT(*) FROM test_attempts a WHERE a.test_id=t.id AND a.submitted_at IS NOT NULL) an
  FROM tests t JOIN class_groups g ON g.id=t.group_id
  WHERE t.group_id IN (' . implode(',', array_map('intval', $gids)) . ')
  ORDER BY t.id DESC')->fetchAll();

$editId = (int) ($_GET['id'] ?? 0);
$cur = $editId ? teacher_test($editId, (int) $u['id']) : null;
$qs = $cur ? test_questions((int) $cur['id'], true) : [];
$results = [];
if ($cur) {
    $rs = db()->prepare('SELECT a.*, u.name FROM test_attempts a JOIN users u ON u.id=a.student_id WHERE a.test_id=? AND a.submitted_at IS NOT NULL ORDER BY a.submitted_at DESC');
    $rs->execute([$cur['id']]);
    $results = $rs->fetchAll();
}

panel_head('ogretmen', 'testler', 'Testler | Öğretmen Paneli', $u);
?>
<form method="post" class="card mb-6 grid gap-3 p-5 md:grid-cols-2 md:items-end">
  <input type="hidden" name="action" value="create">
  <label class="text-sm font-bold">Grup
    <select name="group_id" class="mt-1 w-full rounded-xl border px-3 py-2">
      <?php foreach ($groups as $g): ?><option value="<?= (int) $g['id'] ?>"><?= e($g['name']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label class="text-sm font-bold">Süre (dk, isteğe bağlı)
    <input name="duration_min" type="number" min="1" max="180" class="mt-1 w-full rounded-xl border px-3 py-2" placeholder="Örn. 15">
  </label>
  <label class="text-sm font-bold">Tür
    <select name="kind" class="mt-1 w-full rounded-xl border px-3 py-2">
      <option value="quiz">Konu testi</option>
      <option value="deneme">Deneme sınavı (sıralamalı)</option>
    </select>
  </label>
  <label class="text-sm font-bold md:col-span-2">Başlık
    <input name="title" required class="mt-1 w-full rounded-xl border px-3 py-2">
  </label>
  <label class="text-sm font-bold md:col-span-2">Açıklama
    <textarea name="description" rows="2" class="mt-1 w-full rounded-xl border px-3 py-2"></textarea>
  </label>
  <button class="btn-primary md:col-span-2">Test oluştur (taslak)</button>
</form>

<?php if ($cur): ?>
<article class="card mb-6 p-5">
  <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-navy"><?= e($cur['gname']) ?> · <?= ($cur['kind'] ?? '') === 'deneme' ? 'Deneme' : 'Test' ?> · <?= $cur['status'] === 'yayinda' ? 'Yayında' : 'Taslak' ?></p>
  <h2 class="font-display mt-1 text-2xl"><?= e($cur['title']) ?></h2>
  <form method="post" class="mt-4 grid gap-3 md:grid-cols-2">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="test_id" value="<?= (int) $cur['id'] ?>">
    <label class="text-sm font-bold md:col-span-2">Başlık<input name="title" required value="<?= e($cur['title']) ?>" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
    <label class="text-sm font-bold md:col-span-2">Açıklama<textarea name="description" rows="2" class="mt-1 w-full rounded-xl border px-3 py-2"><?= e($cur['description']) ?></textarea></label>
    <label class="text-sm font-bold">Süre (dk)<input name="duration_min" type="number" min="1" max="180" value="<?= $cur['duration_min'] !== null ? (int) $cur['duration_min'] : '' ?>" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
    <div class="flex flex-wrap items-end gap-2">
      <button class="btn-primary text-sm">Kaydet</button>
    </div>
  </form>
  <div class="mt-4 flex flex-wrap gap-2">
    <form method="post">
      <input type="hidden" name="action" value="toggle">
      <input type="hidden" name="test_id" value="<?= (int) $cur['id'] ?>">
      <button class="btn-outline text-sm" <?= $cur['status'] !== 'yayinda' && !$qs ? 'disabled title="Önce soru ekleyin"' : '' ?>>
        <?= $cur['status'] === 'yayinda' ? 'Taslağa al' : 'Yayınla' ?>
      </button>
    </form>
    <a class="btn-outline text-sm" href="<?= e(url('ogretmen/test-sonuclar.php?id=' . (int) $cur['id'])) ?>">Sonuçlar</a>
    <form method="post" onsubmit="return confirm('Test ve sonuçlar silinsin mi?')">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="test_id" value="<?= (int) $cur['id'] ?>">
      <button class="btn-outline text-sm">Testi sil</button>
    </form>
  </div>
</article>

<form method="post" class="card mb-6 grid gap-3 p-5">
  <input type="hidden" name="action" value="add_q">
  <input type="hidden" name="test_id" value="<?= (int) $cur['id'] ?>">
  <h3 class="font-display text-xl">Soru ekle</h3>
  <label class="text-sm font-bold">Soru<textarea name="body" required rows="2" class="mt-1 w-full rounded-xl border px-3 py-2"></textarea></label>
  <div class="grid gap-3 md:grid-cols-2">
    <label class="text-sm font-bold">A<input name="choice_a" required class="mt-1 w-full rounded-xl border px-3 py-2"></label>
    <label class="text-sm font-bold">B<input name="choice_b" required class="mt-1 w-full rounded-xl border px-3 py-2"></label>
    <label class="text-sm font-bold">C<input name="choice_c" required class="mt-1 w-full rounded-xl border px-3 py-2"></label>
    <label class="text-sm font-bold">D<input name="choice_d" required class="mt-1 w-full rounded-xl border px-3 py-2"></label>
  </div>
  <div class="grid gap-3 md:grid-cols-3">
    <label class="text-sm font-bold">Doğru şık
      <select name="correct" class="mt-1 w-full rounded-xl border px-3 py-2">
        <option value="a">A</option><option value="b">B</option><option value="c">C</option><option value="d">D</option>
      </select>
    </label>
    <label class="text-sm font-bold">Puan<input name="points" type="number" min="1" max="100" value="10" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
    <button class="btn-primary self-end text-sm">Soruyu ekle</button>
  </div>
</form>

<?php foreach ($qs as $i => $q): ?>
<form method="post" class="card mb-4 grid gap-3 p-5">
  <input type="hidden" name="action" value="edit_q">
  <input type="hidden" name="test_id" value="<?= (int) $cur['id'] ?>">
  <input type="hidden" name="qid" value="<?= (int) $q['id'] ?>">
  <p class="text-xs font-extrabold uppercase text-muted">Soru <?= $i + 1 ?> · <?= (int) $q['points'] ?> puan · doğru: <?= e(test_choice_label($q['correct'])) ?></p>
  <label class="text-sm font-bold">Soru<textarea name="body" required rows="2" class="mt-1 w-full rounded-xl border px-3 py-2"><?= e($q['body']) ?></textarea></label>
  <div class="grid gap-3 md:grid-cols-2">
    <label class="text-sm font-bold">A<input name="choice_a" required value="<?= e($q['choice_a']) ?>" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
    <label class="text-sm font-bold">B<input name="choice_b" required value="<?= e($q['choice_b']) ?>" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
    <label class="text-sm font-bold">C<input name="choice_c" required value="<?= e($q['choice_c']) ?>" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
    <label class="text-sm font-bold">D<input name="choice_d" required value="<?= e($q['choice_d']) ?>" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
  </div>
  <div class="flex flex-wrap items-end gap-3">
    <label class="text-sm font-bold">Doğru
      <select name="correct" class="mt-1 rounded-xl border px-3 py-2">
        <?php foreach (test_choice_keys() as $k): ?>
          <option value="<?= $k ?>" <?= $q['correct'] === $k ? 'selected' : '' ?>><?= e(test_choice_label($k)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="text-sm font-bold">Puan<input name="points" type="number" min="1" max="100" value="<?= (int) $q['points'] ?>" class="mt-1 w-24 rounded-xl border px-3 py-2"></label>
    <button class="btn-primary text-sm">Güncelle</button>
    <button class="btn-outline text-sm" name="action" value="del_q" onclick="return confirm('Soru silinsin mi?')">Sil</button>
  </div>
</form>
<?php endforeach; if ($cur && !$qs) echo '<p class="mb-6 text-sm text-muted">Henüz soru yok. Yayınlamak için en az bir soru ekleyin.</p>'; ?>

<?php if ($results): ?>
<div class="card mb-6 overflow-x-auto p-5">
  <h3 class="font-display text-xl">Son çözümler</h3>
  <table class="mt-3 w-full text-left text-sm">
    <thead><tr class="border-b text-xs uppercase tracking-wide text-muted"><th class="py-2">Öğrenci</th><th>Puan</th><th>Yüzde</th><th>Tarih</th></tr></thead>
    <tbody>
    <?php foreach ($results as $r): ?>
      <tr class="border-t"><td class="py-2 font-bold"><?= e($r['name']) ?></td><td><?= (int) $r['score'] ?> / <?= (int) $r['max_score'] ?></td><td>%<?= test_percent((int) $r['score'], (int) $r['max_score']) ?></td><td><?= e($r['submitted_at']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<?php endif; ?>

<h2 class="font-display mb-3 text-2xl">Tüm testleriniz</h2>
<?php foreach ($tests as $t): ?>
<article class="card mb-3 flex flex-wrap items-center justify-between gap-3 p-5">
  <div>
    <p class="text-xs font-extrabold uppercase text-navy"><?= e($t['gname']) ?> · <?= ($t['kind'] ?? '') === 'deneme' ? 'Deneme' : 'Test' ?> · <?= $t['status'] === 'yayinda' ? 'Yayında' : 'Taslak' ?></p>
    <h3 class="font-extrabold"><?= e($t['title']) ?></h3>
    <p class="text-sm text-muted"><?= (int) $t['qn'] ?> soru · <?= (int) $t['an'] ?> çözüm<?= $t['duration_min'] ? ' · ' . (int) $t['duration_min'] . ' dk' : '' ?></p>
  </div>
  <div class="flex gap-2">
    <a class="btn-primary text-sm" href="<?= e(url('ogretmen/testler.php?id=' . (int) $t['id'])) ?>">Düzenle</a>
    <a class="btn-outline text-sm" href="<?= e(url('ogretmen/test-sonuclar.php?id=' . (int) $t['id'])) ?>">Sonuçlar</a>
  </div>
</article>
<?php endforeach; if (!$tests) echo '<p class="text-muted">Henüz test yok.</p>'; ?>
<?php panel_foot();
