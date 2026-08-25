<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogretmen');
$err = '';
$ok = flash_ok();
$groups = teacher_groups((int) $u['id']);
if (post('delete_id')) {
    try {
        academy_delete_recording((int) post('delete_id'), (int) $u['id']);
        flash_ok('Kayıt silindi.');
        redirect('ogretmen/kayit-yukle');
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gid = (int) post('group_id');
    $title = post('title');
    $mins = max(1, min(300, (int) post('mins')));
    $url = trim(post('video_url'));
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
            $path = academy_store_upload('video', 'vod', academy_mimes_video(), 200);
            if ($path === null && $url === '') {
                throw new RuntimeException('MP4 yükleyin veya harici video adresi girin.');
            }
            if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
                throw new RuntimeException('Geçerli bir video adresi girin.');
            }
            db()->prepare('INSERT INTO recordings (group_id, teacher_id, title, mins, recorded_on, video_url, video_path) VALUES (?,?,?,?,CURDATE(),?,?)')
                ->execute([$gid, (int) $u['id'], $title, $mins, $url !== '' ? $url : null, $path]);
            notify_group_students($gid, 'Yeni ders kaydı', $title, url('ogrenci/kayitlar'));
            flash_ok('Kayıt yüklendi. Öğrenciler Ders kayıtları menüsünden izler.');
            redirect('ogretmen/kayit-yukle');
        } catch (Throwable $e) {
            $err = $e->getMessage();
        }
    }
}
$st = db()->prepare(
    'SELECT rec.*, g.name gname FROM recordings rec JOIN class_groups g ON g.id=rec.group_id WHERE rec.teacher_id=? ORDER BY rec.id DESC'
);
$st->execute([(int) $u['id']]);
$rows = $st->fetchAll();
panel_head('ogretmen', 'kayitlar', 'Kayıt yükle | Öğretmen Paneli', $u);
?>
<?php if ($ok): ?><p class="mb-4 font-bold text-green-700"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="card mb-6 grid gap-3 p-5 md:grid-cols-2">
  <label class="text-sm font-bold">Grup
    <select name="group_id" required class="mt-1 w-full rounded-xl border px-3 py-2">
      <?php foreach ($groups as $g): ?><option value="<?= (int) $g['id'] ?>"><?= e($g['name']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label class="text-sm font-bold">Süre (dk)
    <input name="mins" type="number" min="1" max="300" value="45" class="mt-1 w-full rounded-xl border px-3 py-2">
  </label>
  <label class="text-sm font-bold md:col-span-2">Başlık
    <input name="title" required class="mt-1 w-full rounded-xl border px-3 py-2" placeholder="Örn. Bakara 1–20 kaydı">
  </label>
  <label class="text-sm font-bold">Video dosyası
    <input name="video" type="file" accept="video/mp4,video/webm" class="mt-1 w-full text-sm">
  </label>
  <label class="text-sm font-bold">veya harici adres
    <input name="video_url" class="mt-1 w-full rounded-xl border px-3 py-2" placeholder="https://...">
  </label>
  <p class="md:col-span-2 text-xs text-muted">Dosyalar public klasöre konulmaz; yalnızca kayıtlı öğrenciler izler. En fazla 200 MB.</p>
  <button class="btn-primary md:col-span-2">Yükle</button>
</form>
<?php foreach ($rows as $r): ?>
  <article class="card mb-3 flex flex-wrap items-start justify-between gap-3 p-5">
    <div>
    <p class="font-extrabold"><?= e($r['title']) ?></p>
    <p class="text-sm text-muted"><?= e($r['gname']) ?> · <?= e($r['recorded_on']) ?> · <?= (int) $r['mins'] ?> dk</p>
    <p class="mt-1 text-xs text-muted"><?= !empty($r['video_path']) || !empty($r['video_url']) ? 'Video hazır' : 'Video yok' ?></p>
    </div>
    <?= panel_delete_form('', ['delete_id' => (int) $r['id']], 'Bu ders kaydı silinsin mi?') ?>
  </article>
<?php endforeach; ?>
<?php if (!$rows): ?><p class="text-muted">Henüz kayıt yok.</p><?php endif; ?>
<?php panel_foot();
