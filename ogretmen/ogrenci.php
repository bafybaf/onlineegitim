<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogretmen');
$id = (int) ($_GET['id'] ?? 0);
if ($id < 1 || !teacher_owns_student((int) $u['id'], $id)) {
    flash_error('Bu öğrenci sizin sınıfınızda değil.');
    redirect('ogretmen/ogrenciler');
}
$st = db()->prepare('SELECT * FROM users WHERE id = ? AND role = ?');
$st->execute([$id, 'ogrenci']);
$person = $st->fetch();
if (!$person) {
    redirect('ogretmen/ogrenciler');
}
$rows = teacher_class_students((int) $u['id']);
$mine = array_values(array_filter($rows, static fn(array $r): bool => (int) $r['id'] === $id));
$att = [];
try {
    $as = db()->prepare(
        "SELECT r.title, r.started_at, a.present, g.name gname
         FROM attendance a
         JOIN live_rooms r ON r.id = a.room_id
         JOIN class_groups g ON g.id = r.group_id
         WHERE a.student_id = ? AND g.teacher_id = ?
         ORDER BY r.started_at DESC LIMIT 12"
    );
    $as->execute([$id, (int) $u['id']]);
    $att = $as->fetchAll();
} catch (Throwable) {
}
$hw = [];
try {
    $hs = db()->prepare(
        "SELECT h.title, h.due_label, s.status, s.body, g.name gname
         FROM homework_subs s
         JOIN homework h ON h.id = s.homework_id
         JOIN class_groups g ON g.id = h.group_id
         WHERE s.student_id = ? AND g.teacher_id = ?
         ORDER BY h.id DESC LIMIT 12"
    );
    $hs->execute([$id, (int) $u['id']]);
    $hw = $hs->fetchAll();
} catch (Throwable) {
}
$tests = [];
try {
    $ts = db()->prepare(
        "SELECT t.title, a.score, a.max_score, a.submitted_at, g.name gname
         FROM test_attempts a
         JOIN tests t ON t.id = a.test_id
         JOIN class_groups g ON g.id = t.group_id
         WHERE a.student_id = ? AND g.teacher_id = ? AND a.submitted_at IS NOT NULL
         ORDER BY a.submitted_at DESC LIMIT 12"
    );
    $ts->execute([$id, (int) $u['id']]);
    $tests = $ts->fetchAll();
} catch (Throwable) {
}
panel_head('ogretmen', 'ogrenciler', (string) $person['name'] . ' | Öğrencilerim', $u);
?>
<p class="mb-4"><a class="text-sm font-extrabold text-navy" href="<?= e(url('ogretmen/ogrenciler')) ?>">← Öğrencilerim</a></p>

<section class="card profile-hero p-6">
  <?= user_avatar_html($person, 'lg') ?>
  <div>
    <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-muted">Sizin öğrenciniz</p>
    <h2 class="font-display mt-1 text-3xl"><?= e((string) $person['name']) ?></h2>
    <p class="mt-1 text-sm text-muted"><?= e((string) $person['email']) ?><?= !empty($person['phone']) ? ' · ' . e((string) $person['phone']) : '' ?></p>
    <p class="mt-1 text-sm text-muted"><?= e((string) ($person['city'] ?: 'Şehir yok')) ?></p>
    <div class="mt-4 flex flex-wrap gap-2">
      <a class="btn-primary text-sm" href="<?= e(url('ogretmen/mesajlar.php?ogrenci=' . $id)) ?>">Mesaj yaz</a>
    </div>
  </div>
</section>

<section class="card mt-6 p-5">
  <p class="stat-label">Sınıflarınız</p>
  <h3 class="font-display mt-1 text-xl">Bu hocaya bağlı kayıtlar</h3>
  <div class="mt-4 grid gap-3">
    <?php foreach ($mine as $en):
        $mem = $en['membership'];
        ?>
      <div class="rounded-2xl border border-[#e5e5e7] p-4">
        <div class="flex flex-wrap items-start justify-between gap-2">
          <div>
            <p class="text-xs font-extrabold uppercase text-navy"><?= e((string) $en['program_title']) ?></p>
            <p class="font-extrabold"><?= e((string) $en['group_name']) ?></p>
            <p class="text-sm text-muted"><?= e((string) $en['days']) ?><?= !empty($en['package_name']) ? ' · ' . e((string) $en['package_name']) : '' ?></p>
          </div>
          <span class="<?= e(membership_kind_class((string) $mem['kind'])) ?>"><?= e((string) $mem['label']) ?></span>
        </div>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-sm">
          <span>İlerleme %<?= (int) $en['progress'] ?></span>
          <a class="font-extrabold text-navy" href="<?= e(ogretmen_grup_url((int) $en['group_id'])) ?>">Sınıfa git</a>
        </div>
        <div class="prog mt-2" aria-hidden="true"><span style="width:<?= (int) $en['progress'] ?>%"></span></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<div class="mt-6 grid gap-4 lg:grid-cols-3">
  <section class="card overflow-hidden">
    <div class="px-5 py-4"><p class="stat-label">Yoklama</p><h3 class="font-display mt-1 text-xl">Son dersler</h3></div>
    <?php if (!$att): ?>
      <p class="dash-empty px-5 pb-5">Yoklama yok.</p>
    <?php else: ?>
      <ul class="px-5 pb-5 text-sm">
        <?php foreach ($att as $a): ?>
          <li class="border-t py-2"><b><?= ((int) $a['present']) ? 'Var' : 'Yok' ?></b> · <?= e($a['gname']) ?> · <?= e(profile_dt((string) $a['started_at'])) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
  <section class="card overflow-hidden">
    <div class="px-5 py-4"><p class="stat-label">Ödev</p><h3 class="font-display mt-1 text-xl">Teslimler</h3></div>
    <?php if (!$hw): ?>
      <p class="dash-empty px-5 pb-5">Ödev yok.</p>
    <?php else: ?>
      <ul class="px-5 pb-5 text-sm">
        <?php foreach ($hw as $h): ?>
          <li class="border-t py-2"><b><?= e($h['title']) ?></b><br><span class="text-muted"><?= e($h['gname']) ?> · <?= e(match ((string) $h['status']) {
              'open' => 'açık',
              'sent' => 'gönderildi',
              'ok' => 'onaylandı',
              default => (string) $h['status'],
          }) ?></span></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
  <section class="card overflow-hidden">
    <div class="px-5 py-4"><p class="stat-label">Test</p><h3 class="font-display mt-1 text-xl">Sonuçlar</h3></div>
    <?php if (!$tests): ?>
      <p class="dash-empty px-5 pb-5">Çözüm yok.</p>
    <?php else: ?>
      <ul class="px-5 pb-5 text-sm">
        <?php foreach ($tests as $t): ?>
          <li class="border-t py-2"><b><?= e($t['title']) ?></b><br><span class="text-muted"><?= (int) $t['score'] ?> / <?= (int) $t['max_score'] ?> · <?= e($t['gname']) ?></span></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>
<p class="mt-6 text-xs text-muted">Ödeme ve mağaza bilgisi öğretmen kartında yoktur; onu yönetim görür.</p>
<?php panel_foot();
