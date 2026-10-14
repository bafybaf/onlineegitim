<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogretmen');
if (function_exists('vod_recover_teacher_pending')) {
    try {
        vod_recover_teacher_pending(db(), (int) $u['id']);
    } catch (Throwable $e) {
    }
}
$err = flash_error();
$ok = flash_ok();
$groups = teacher_groups((int) $u['id']);
$tid = (int) ($_GET['hoca'] ?? 0);
$titleKeep = '';
$urlKeep = '';
$minsKeep = 45;
$gidKeep = 0;

function ogretmen_kayit_qs(int $tid): string
{
    return $tid > 0 ? ('?hoca=' . $tid) : '';
}

if (post('delete_id')) {
    try {
        academy_delete_recording((int) post('delete_id'), (int) $u['id']);
        flash_ok('Kayıt silindi.');
        redirect('ogretmen/kayit-yukle.php' . ogretmen_kayit_qs($tid));
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('request_post_too_large') && request_post_too_large()) {
        $err = request_upload_limit_message(300);
    } else {
        $gid = (int) post('group_id');
        $title = post('title');
        $mins = max(1, min(300, (int) post('mins')));
        $url = trim(post('video_url'));
        $gidKeep = $gid;
        $titleKeep = $title;
        $minsKeep = $mins;
        $urlKeep = $url;
        $owns = false;
        foreach ($groups as $g) {
            if ((int) $g['id'] === $gid) {
                $owns = true;
                break;
            }
        }
        if (!$owns || $title === '') {
            $err = 'Grup ve başlık zorunlu.';
        } else {
            try {
                $path = academy_store_upload('video', 'vod', academy_mimes_video(), 300);
                if ($path === null && $url === '') {
                    throw new RuntimeException('MP4 yükleyin veya harici video adresi girin.');
                }
                if ($path && function_exists('vod_optimize_upload')) {
                    $path = vod_optimize_upload($path);
                }
                if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
                    throw new RuntimeException('Geçerli bir video adresi girin.');
                }
                db()->prepare('INSERT INTO recordings (group_id, teacher_id, title, mins, recorded_on, video_url, video_path) VALUES (?,?,?,?,CURDATE(),?,?)')
                    ->execute([$gid, (int) $u['id'], $title, $mins, $url !== '' ? $url : null, $path]);
                notify_group_students($gid, 'Yeni ders kaydı', $title, url('ogrenci/kayitlar'));
                flash_ok('Kayıt yüklendi. Öğrenciler Ders kayıtları menüsünden izler.');
                redirect('ogretmen/kayit-yukle.php' . ogretmen_kayit_qs((int) $u['id']));
            } catch (Throwable $e) {
                $err = $e->getMessage();
            }
        }
    }
}
$owned = function_exists('group_owned_in_sql') ? group_owned_in_sql((int) $u['id']) : '0';
$st = db()->query(
    "SELECT rec.*, g.name gname, t.name tname
     FROM recordings rec
     JOIN class_groups g ON g.id = rec.group_id
     JOIN users t ON t.id = rec.teacher_id
     WHERE rec.group_id IN ($owned)
     ORDER BY t.name ASC, rec.recorded_on DESC, rec.id DESC"
);
$rows = $st ? $st->fetchAll() : [];
$teachers = [];
foreach ($rows as $r) {
    $hid = (int) $r['teacher_id'];
    if (!isset($teachers[$hid])) {
        $teachers[$hid] = (string) $r['tname'];
    }
}
if ($tid && !isset($teachers[$tid])) {
    $tid = 0;
}
if ($tid) {
    $rows = array_values(array_filter($rows, static fn (array $r): bool => (int) $r['teacher_id'] === $tid));
}
$me = (int) $u['id'];
panel_head('ogretmen', 'kayitlar', 'Ders kayıtları | Öğretmen Paneli', $u);
?>
<?php if ($ok): ?><p class="mb-4 font-bold text-green-700"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="card mb-6 grid gap-3 p-5 md:grid-cols-2">
  <label class="text-sm font-bold">Grup
    <select name="group_id" required class="mt-1 w-full rounded-xl border px-3 py-2">
      <?php foreach ($groups as $g): ?><option value="<?= (int) $g['id'] ?>"<?= $gidKeep === (int) $g['id'] ? ' selected' : '' ?>><?= e($g['name']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label class="text-sm font-bold">Süre (dk)
    <input name="mins" type="number" min="1" max="300" value="<?= (int) $minsKeep ?>" class="mt-1 w-full rounded-xl border px-3 py-2">
  </label>
  <label class="text-sm font-bold md:col-span-2">Başlık
    <input name="title" required class="mt-1 w-full rounded-xl border px-3 py-2" placeholder="Örn. Bakara 1–20 kaydı" value="<?= e($titleKeep) ?>">
  </label>
  <label class="text-sm font-bold">Video dosyası
    <input name="video" type="file" accept="video/mp4,video/webm" class="mt-1 w-full text-sm">
  </label>
  <label class="text-sm font-bold">veya harici adres
    <input name="video_url" class="mt-1 w-full rounded-xl border px-3 py-2" placeholder="https://..." value="<?= e($urlKeep) ?>">
  </label>
  <p class="md:col-span-2 text-xs text-muted">Dosyalar public klasöre konulmaz. Siz ve kayıtlı öğrenciler izleyebilir. En fazla 300 MB. Yüklerken kalite korunarak 1080p MP4’e sıkıştırılır; bu biraz sürebilir.</p>
  <button class="btn-primary md:col-span-2">Yükle</button>
</form>
<script>
(function () {
  var input = document.querySelector('input[name="video"]');
  var form = input && input.form;
  if (!input) return;
  var max = 300 * 1024 * 1024;
  function tooBig(file) {
    if (!file || file.size <= max) return false;
    alert('Dosya en fazla 300 MB olabilir. Seçilen: ' + Math.round(file.size / 1048576) + ' MB.');
    input.value = '';
    return true;
  }
  input.addEventListener('change', function () {
    tooBig(input.files && input.files[0]);
  });
  form.addEventListener('submit', function (ev) {
    if (tooBig(input.files && input.files[0])) ev.preventDefault();
  });
})();
</script>
<p class="mb-4 text-sm text-muted">Gruplarınızdaki kayıtlar yükleyen hocaya göre ayrılır. Yalnız kendi yüklediğinizi silebilirsiniz.</p>
<?php if (count($teachers) > 1): ?>
  <div class="mb-5 flex flex-wrap gap-2">
    <a class="<?= $tid === 0 ? 'btn-primary' : 'btn-outline' ?> text-sm" href="<?= e(url('ogretmen/kayit-yukle')) ?>">Tüm hocalar</a>
    <?php foreach ($teachers as $hid => $hname): ?>
      <a class="<?= $tid === (int) $hid ? 'btn-primary' : 'btn-outline' ?> text-sm" href="<?= e(url('ogretmen/kayit-yukle.php' . ogretmen_kayit_qs((int) $hid))) ?>"><?= e($hname) ?></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php
$last = null;
foreach ($rows as $r):
    $ready = !empty($r['video_path']) || !empty($r['video_url']);
    $watch = url('ogretmen/kayit-izle.php?id=' . (int) $r['id']);
    $hid = (int) $r['teacher_id'];
    $mine = $hid === $me;
    if ($tid === 0 && count($teachers) > 1 && $hid !== $last):
        $last = $hid;
        ?>
  <div class="mb-3 <?= $last === (int) $rows[0]['teacher_id'] ? 'mt-0' : 'mt-8' ?> flex flex-wrap items-end justify-between gap-2">
    <div>
      <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-navy">Hoca</p>
      <h2 class="font-display text-2xl"><?= e((string) $r['tname']) ?></h2>
    </div>
    <a class="text-sm font-extrabold text-navy" href="<?= e(url('ogretmen/kayit-yukle.php' . ogretmen_kayit_qs($hid))) ?>">Yalnız bu hoca →</a>
  </div>
        <?php
    endif;
    ?>
  <article class="card mb-3 flex flex-wrap items-start justify-between gap-3 p-5">
    <div>
    <p class="font-extrabold"><?= e($r['title']) ?></p>
    <p class="text-sm text-muted"><?= e($r['gname']) ?> · <?= e($r['recorded_on']) ?> · <?= e(function_exists('vod_length_label') ? vod_length_label($r) : ((int) $r['mins'] . ' dk')) ?></p>
    <p class="mt-1 text-xs text-muted"><?= $ready ? 'Video hazır' : 'Video yok' ?></p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <?php if ($ready): ?>
        <a class="btn-primary text-sm" href="<?= e($watch) ?>">İzle</a>
      <?php endif; ?>
      <?php if ($mine): ?>
        <?= panel_delete_form('', ['delete_id' => (int) $r['id']], 'Bu ders kaydı silinsin mi?') ?>
      <?php endif; ?>
    </div>
  </article>
<?php endforeach; ?>
<?php if (!$rows): ?><p class="text-muted"><?= $tid ? 'Bu hocaya ait kayıt yok.' : 'Henüz kayıt yok.' ?></p><?php endif; ?>
<?php panel_foot();
