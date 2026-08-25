<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogretmen');
$gid = (int) ($_GET['grup'] ?? 0);
$onlyOk = isset($_GET['aktif']);
$groups = teacher_groups((int) $u['id']);
$rows = teacher_class_students((int) $u['id'], $gid > 0 ? $gid : null);
if ($onlyOk) {
    $rows = array_values(array_filter($rows, static fn(array $r): bool => ($r['membership']['kind'] ?? '') !== 'expired'));
}
$byGroup = [];
foreach ($rows as $r) {
    $byGroup[(int) $r['group_id']][] = $r;
}
$uniq = [];
foreach ($rows as $r) {
    $uniq[(int) $r['id']] = true;
}
$aktifN = 0;
$pasifN = 0;
foreach ($rows as $r) {
    if (($r['membership']['kind'] ?? '') === 'expired') {
        $pasifN++;
    } else {
        $aktifN++;
    }
}
$tones = [];
$i = 0;
foreach ($groups as $g) {
    $tones[(int) $g['id']] = $i % 5;
    $i++;
}
$flashErr = flash_error();
panel_head('ogretmen', 'ogrenciler', 'Öğrencilerim | Öğretmen Paneli', $u);
?>
<?php if ($flashErr): ?><p class="mb-4 font-bold text-accent"><?= e($flashErr) ?></p><?php endif; ?>
<p class="mb-2 text-sm text-muted">Yalnızca sizin sınıflarınızdaki öğrenciler. Yönetimdeki tüm kullanıcı listesi burada görünmez.</p>
<div class="dash-stats is-4 mb-6">
  <div class="stat">
    <p class="stat-label">Öğrenci</p>
    <p class="stat-value"><?= count($uniq) ?></p>
    <p class="stat-hint">Tekil kişi</p>
  </div>
  <div class="stat">
    <p class="stat-label">Kayıt satırı</p>
    <p class="stat-value"><?= count($rows) ?></p>
    <p class="stat-hint"><?= count($byGroup) ?> sınıf</p>
  </div>
  <div class="stat">
    <p class="stat-label">Aktif üyelik</p>
    <p class="stat-value mem-ok"><?= $aktifN ?></p>
  </div>
  <div class="stat">
    <p class="stat-label">Pasif / süresi dolmuş</p>
    <p class="stat-value mem-bad"><?= $pasifN ?></p>
  </div>
</div>

<div class="stu-toolbar">
  <a class="stu-chip <?= $gid < 1 ? 'is-on' : '' ?>" href="<?= e(url('ogretmen/ogrenciler')) ?>">Tüm sınıflarım</a>
  <?php foreach ($groups as $g): ?>
    <a class="stu-chip <?= $gid === (int) $g['id'] ? 'is-on' : '' ?>" href="<?= e(url('ogretmen/ogrenciler.php?grup=' . (int) $g['id'])) ?>"><?= e($g['name']) ?></a>
  <?php endforeach; ?>
  <a class="stu-chip <?= $onlyOk ? 'is-on' : '' ?>" href="<?= e(url('ogretmen/ogrenciler.php' . ($gid ? '?grup=' . $gid . '&aktif=1' : '?aktif=1'))) ?>">Yalnızca aktif</a>
  <input id="stu-q" class="ml-auto min-w-[12rem] rounded-full border px-3 py-2 text-sm" placeholder="İsim veya e-posta ara" type="search">
</div>

<?php if (!$rows): ?>
  <div class="card p-6">Bu filtrede öğrenci yok.</div>
<?php endif; ?>

<?php foreach ($byGroup as $groupId => $list):
    $head = $list[0];
    $tone = $tones[$groupId] ?? 0;
    ?>
  <section class="card stu-group mb-5 overflow-hidden" data-tone="<?= (int) $tone ?>">
    <div class="flex flex-wrap items-end justify-between gap-3 border-b px-5 py-4">
      <div>
        <p class="stat-label"><?= e((string) $head['program_title']) ?></p>
        <h2 class="font-display mt-1 text-2xl"><?= e((string) $head['group_name']) ?></h2>
        <p class="text-sm text-muted"><?= e((string) $head['days']) ?> · <?= count($list) ?> öğrenci</p>
      </div>
      <a class="btn-outline text-sm" href="<?= e(ogretmen_grup_url((int) $groupId)) ?>">Sınıf sayfası</a>
    </div>
    <div class="stu-card-grid p-4">
      <?php foreach ($list as $s):
          $mem = $s['membership'];
          $off = ($mem['kind'] ?? '') === 'expired';
          $att = $s['last_attendance'];
          $attTxt = 'Yoklama yok';
          if ($att && !empty($att['last_present'])) {
              $attTxt = 'Son katılım ' . profile_dt((string) $att['last_present']);
          } elseif ($att && !empty($att['last_at'])) {
              $attTxt = 'Son yoklama ' . profile_dt((string) $att['last_at']);
          }
          $search = mb_strtolower(($s['name'] ?? '') . ' ' . ($s['email'] ?? '') . ' ' . ($s['phone'] ?? ''));
          ?>
        <article class="stu-card<?= $off ? ' is-off' : '' ?>" data-q="<?= e($search) ?>">
          <div class="flex items-start justify-between gap-3">
            <a class="avatar-row" href="<?= e(teacher_ogrenci_url((int) $s['id'])) ?>">
              <?= user_avatar_html($s, 'md') ?>
              <span>
                <span class="font-extrabold"><?= e((string) $s['name']) ?></span>
                <span class="block text-xs text-muted"><?= e((string) ($s['city'] ?: 'Şehir yok')) ?></span>
              </span>
            </a>
            <span class="<?= e(membership_kind_class((string) $mem['kind'])) ?>"><?= e((string) $mem['short']) ?></span>
          </div>
          <p class="mt-3 text-sm text-muted"><?= e((string) ($s['email'] ?: '—')) ?><?= !empty($s['phone']) ? ' · ' . e((string) $s['phone']) : '' ?></p>
          <?php if (!empty($s['package_name']) || !empty($s['access_type'])): ?>
            <p class="mt-1 text-xs font-bold text-navy"><?= e(package_access_label($s)) ?><?= !empty($s['package_name']) ? ' · ' . e((string) $s['package_name']) : '' ?></p>
          <?php endif; ?>
          <div class="mt-3 flex items-center justify-between gap-2 text-xs font-bold">
            <span>İlerleme %<?= (int) $s['progress'] ?></span>
            <span class="<?= (int) $s['hw_open'] ? 'mem-bad' : 'text-muted' ?>"><?= (int) $s['hw_open'] ? (int) $s['hw_open'] . ' açık ödev' : 'Ödev yok' ?></span>
          </div>
          <div class="prog mt-2" aria-hidden="true"><span style="width:<?= (int) $s['progress'] ?>%"></span></div>
          <p class="mt-2 text-xs text-muted"><?= e($attTxt) ?></p>
          <div class="mt-3 flex flex-wrap gap-2">
            <a class="btn-primary text-sm" href="<?= e(teacher_ogrenci_url((int) $s['id'])) ?>">Kartı aç</a>
            <a class="btn-outline text-sm" href="<?= e(url('ogretmen/mesajlar.php?ogrenci=' . (int) $s['id'])) ?>">Mesaj</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>
<script>
(function () {
  var q = document.getElementById('stu-q');
  if (!q) return;
  q.addEventListener('input', function () {
    var v = (q.value || '').toLocaleLowerCase('tr');
    document.querySelectorAll('[data-q]').forEach(function (el) {
      el.style.display = !v || (el.getAttribute('data-q') || '').indexOf(v) !== -1 ? '' : 'none';
    });
  });
})();
</script>
<?php panel_foot();
