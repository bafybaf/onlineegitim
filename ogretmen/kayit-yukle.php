<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogretmen');
if (function_exists('ensure_recordings_lesson_cat_schema')) {
    ensure_recordings_lesson_cat_schema();
}
if (function_exists('vod_recover_teacher_pending')) {
    try {
        vod_recover_teacher_pending(db(), (int) $u['id']);
    } catch (Throwable $e) {
    }
}
$err = flash_error();
$ok = flash_ok();
$groups = teacher_groups((int) $u['id']);
$filterGid = (int) ($_GET['grup'] ?? 0);
$titleKeep = '';
$urlKeep = '';
$minsKeep = 45;
$gidKeep = 0;
$catKeep = '';
$editId = (int) (post('edit_id') ?: ($_GET['duzenle'] ?? 0));
$edit = $editId > 0 ? academy_teacher_recording($editId, (int) $u['id']) : null;
if ($editId > 0 && !$edit && !post('delete_id')) {
    $err = 'Kayıt bulunamadı veya bu gruba ait değil.';
    $editId = 0;
}

function ogretmen_kayit_qs(int $gid, int $editId = 0): string
{
    $q = [];
    if ($gid > 0) {
        $q['grup'] = $gid;
    }
    if ($editId > 0) {
        $q['duzenle'] = $editId;
    }
    return $q ? ('?' . http_build_query($q)) : '';
}

function ogretmen_kayit_inline_form(array $r, array $groups, array $keep = []): void
{
    $rid = (int) ($r['id'] ?? 0);
    $gid = (int) ($keep['group_id'] ?? $r['group_id'] ?? 0);
    $title = (string) ($keep['title'] ?? recording_edit_title($r));
    $mins = max(1, (int) ($keep['mins'] ?? $r['mins'] ?? 45));
    $url = (string) ($keep['video_url'] ?? $r['video_url'] ?? '');
    $fam = recording_program_family((string) ($r['program_slug'] ?? ''), (string) ($r['gname'] ?? ''));
    $info = recording_lesson_heading($r);
    $cat = (string) ($keep['lesson_cat'] ?? '');
    if ($cat === '' && $fam !== '') {
        $cat = recording_normalize_subject((string) ($r['lesson_cat'] ?? ''), $fam)
            ?: recording_normalize_subject((string) ($info['heading'] ?? ''), $fam)
            ?: '';
    }
    echo '<form method="post" enctype="multipart/form-data" class="vod-rec-edit card grid gap-3 p-5 md:grid-cols-2">';
    echo '<input type="hidden" name="edit_id" value="' . $rid . '">';
    echo '<p class="md:col-span-2 font-extrabold">Kaydı düzenle</p>';
    echo '<label class="text-sm font-bold">Grup<select name="group_id" data-cat-group required class="mt-1 w-full rounded-xl border px-3 py-2">';
    foreach ($groups as $g) {
        $sel = $gid === (int) $g['id'] ? ' selected' : '';
        echo '<option value="' . (int) $g['id'] . '"' . $sel . '>' . e((string) $g['name']) . '</option>';
    }
    echo '</select></label>';
    echo '<label class="text-sm font-bold">Süre (dk)<input name="mins" type="number" min="1" max="300" value="' . $mins . '" class="mt-1 w-full rounded-xl border px-3 py-2"></label>';
    recording_subject_picker_html($groups, $cat, [
        'class' => 'text-sm font-bold md:col-span-2',
        'hint' => false,
        'skip_data' => true,
    ]);
    echo '<label class="text-sm font-bold md:col-span-2">Başlık<input name="title" required class="mt-1 w-full rounded-xl border px-3 py-2" value="' . e($title) . '"></label>';
    echo '<label class="text-sm font-bold">Video dosyası<input name="video" type="file" accept="video/mp4,video/webm" class="mt-1 w-full text-sm"></label>';
    echo '<label class="text-sm font-bold">veya harici adres<input name="video_url" class="mt-1 w-full rounded-xl border px-3 py-2" placeholder="https://..." value="' . e($url) . '"></label>';
    echo '<p class="md:col-span-2 text-xs text-muted">Video boş bırakılırsa mevcut dosya kalır. En fazla 300 MB.</p>';
    echo '<div class="md:col-span-2 flex flex-wrap gap-2">';
    echo '<button class="btn-primary">Kaydet</button>';
    echo '<button type="button" class="btn-outline" data-rec-edit-cancel>Vazgeç</button>';
    echo '</div></form>';
}

if (post('delete_id')) {
    try {
        academy_delete_recording((int) post('delete_id'), (int) $u['id']);
        flash_ok('Kayıt silindi.');
        redirect('ogretmen/kayit-yukle.php' . ogretmen_kayit_qs($filterGid));
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ((int) post('edit_id') > 0 && !$edit) {
        $err = 'Kayıt bulunamadı veya bu gruba ait değil.';
    } elseif (function_exists('request_post_too_large') && request_post_too_large()) {
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
        $owns = null;
        foreach ($groups as $g) {
            if ((int) $g['id'] === $gid) {
                $owns = $g;
                break;
            }
        }
        $fam = $owns ? recording_program_family((string) ($owns['program_slug'] ?? ''), (string) ($owns['name'] ?? '')) : '';
        $cat = $fam !== '' ? recording_normalize_subject(post('lesson_cat'), $fam) : '';
        $catKeep = $cat;
        $isEdit = $edit && (int) post('edit_id') === (int) $edit['id'];
        if (!$owns || $title === '') {
            $err = 'Grup ve başlık zorunlu.';
        } elseif ($fam !== '' && $cat === '') {
            $err = 'Kategori seçin.';
        } else {
            try {
                $path = academy_store_upload('video', 'vod', academy_mimes_video(), 300);
                if ($path && function_exists('vod_optimize_upload')) {
                    $path = vod_optimize_upload($path);
                }
                if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
                    throw new RuntimeException('Geçerli bir video adresi girin.');
                }
                if ($isEdit) {
                    $keepPath = (string) ($edit['video_path'] ?? '');
                    $keepUrl = (string) ($edit['video_url'] ?? '');
                    $finalPath = $path ?? ($keepPath !== '' ? $keepPath : null);
                    $finalUrl = $url !== '' ? $url : ($path ? null : ($keepUrl !== '' ? $keepUrl : null));
                    if ($finalPath === null && $finalUrl === null) {
                        throw new RuntimeException('MP4 yükleyin veya harici video adresi girin.');
                    }
                    academy_update_recording((int) $edit['id'], (int) $u['id'], [
                        'group_id' => $gid,
                        'title' => $title,
                        'mins' => $mins,
                        'video_url' => $finalUrl,
                        'video_path' => $finalPath,
                        'lesson_cat' => $cat,
                    ]);
                    flash_ok('Kayıt güncellendi.');
                    redirect('ogretmen/kayit-yukle.php' . ogretmen_kayit_qs($gid));
                }
                if ($path === null && $url === '') {
                    throw new RuntimeException('MP4 yükleyin veya harici video adresi girin.');
                }
                ensure_recordings_lesson_cat_schema();
                try {
                    db()->prepare('INSERT INTO recordings (group_id, teacher_id, title, mins, recorded_on, video_url, video_path, lesson_cat) VALUES (?,?,?,?,CURDATE(),?,?,?)')
                        ->execute([$gid, (int) $u['id'], $title, $mins, $url !== '' ? $url : null, $path, $cat !== '' ? $cat : null]);
                } catch (Throwable $eIns) {
                    db()->prepare('INSERT INTO recordings (group_id, teacher_id, title, mins, recorded_on, video_url, video_path) VALUES (?,?,?,?,CURDATE(),?,?)')
                        ->execute([$gid, (int) $u['id'], $title, $mins, $url !== '' ? $url : null, $path]);
                    recording_apply_lesson_cat(db(), (int) db()->lastInsertId(), $cat);
                }
                notify_group_students($gid, 'Yeni ders kaydı', $title, url('ogrenci/kayitlar'));
                flash_ok('Kayıt yüklendi. Öğrenciler Ders kayıtları menüsünden izler.');
                redirect('ogretmen/kayit-yukle.php' . ogretmen_kayit_qs($gid));
            } catch (Throwable $e) {
                $err = $e->getMessage();
            }
        }
    }
}
$openEditId = $edit ? (int) $edit['id'] : 0;
$editKeep = [];
if ($edit && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $editKeep = [
        'group_id' => $gidKeep,
        'title' => $titleKeep,
        'mins' => $minsKeep,
        'video_url' => $urlKeep,
        'lesson_cat' => $catKeep,
    ];
}
$owned = function_exists('group_owned_in_sql') ? group_owned_in_sql((int) $u['id']) : '0';
$st = db()->query(
    "SELECT rec.*, g.name gname, t.name tname, p.slug program_slug
     FROM recordings rec
     JOIN class_groups g ON g.id = rec.group_id
     JOIN users t ON t.id = rec.teacher_id
     LEFT JOIN programs p ON p.id = g.program_id
     WHERE rec.group_id IN ($owned)
     ORDER BY rec.recorded_on DESC, rec.id DESC"
);
$rows = $st ? $st->fetchAll() : [];
[$tree, $groupNames] = recordings_lesson_tree($rows);
if ($filterGid && !isset($groupNames[$filterGid])) {
    $filterGid = 0;
}
if ($filterGid) {
    $tree = array_values(array_filter($tree, static fn (array $g): bool => (int) $g['id'] === $filterGid));
}
$tree = recordings_pad_catalog_tree($tree);
$me = (int) $u['id'];
panel_head('ogretmen', 'kayitlar', 'Ders kayıtları | Öğretmen Paneli', $u);
?>
<?php if ($ok): ?><p class="mb-4 font-bold text-green-700"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="card mb-6 grid gap-3 p-5 md:grid-cols-2">
  <h2 class="font-display text-xl md:col-span-2">Yeni kayıt</h2>
  <label class="text-sm font-bold">Grup
    <select name="group_id" data-cat-group required class="mt-1 w-full rounded-xl border px-3 py-2">
      <?php foreach ($groups as $g): ?><option value="<?= (int) $g['id'] ?>"><?= e($g['name']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label class="text-sm font-bold">Süre (dk)
    <input name="mins" type="number" min="1" max="300" value="45" class="mt-1 w-full rounded-xl border px-3 py-2">
  </label>
  <?php recording_subject_picker_html($groups); ?>
  <label class="text-sm font-bold md:col-span-2">Başlık
    <input name="title" required class="mt-1 w-full rounded-xl border px-3 py-2" placeholder="Örn. Soru çözümü 1–20">
  </label>
  <label class="text-sm font-bold">Video dosyası
    <input name="video" type="file" accept="video/mp4,video/webm" class="mt-1 w-full text-sm">
  </label>
  <label class="text-sm font-bold">veya harici adres
    <input name="video_url" class="mt-1 w-full rounded-xl border px-3 py-2" placeholder="https://...">
  </label>
  <p class="md:col-span-2 text-xs text-muted">Dosyalar public klasöre konulmaz. Siz ve kayıtlı öğrenciler izleyebilir. En fazla 300 MB. Yüklerken kalite korunarak 1080p MP4’e sıkıştırılır; bu biraz sürebilir.</p>
  <button class="btn-primary md:col-span-2">Yükle</button>
</form>
<p class="mb-4 text-sm text-muted">Düzenle kaydın yanında açılır. DHBT ve DKAB’de tutamacından sürükleyerek de taşıyabilirsiniz. Yeni tarihli kayıt en altta durur. Yalnız kendi yüklediğinizi silebilirsiniz.</p>
<?php if (count($groupNames) > 1): ?>
  <div class="mb-5 flex flex-wrap gap-2">
    <a class="<?= $filterGid === 0 ? 'btn-primary' : 'btn-outline' ?> text-sm" href="<?= e(url('ogretmen/kayit-yukle')) ?>">Tüm gruplar</a>
    <?php foreach ($groupNames as $hid => $hname): ?>
      <a class="<?= $filterGid === (int) $hid ? 'btn-primary' : 'btn-outline' ?> text-sm" href="<?= e(url('ogretmen/kayit-yukle.php' . ogretmen_kayit_qs((int) $hid))) ?>"><?= e($hname) ?></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php
if (!$tree):
    ?><p class="text-muted"><?= $filterGid ? 'Bu gruba ait kayıt yok.' : 'Henüz kayıt yok.' ?></p><?php
else:
    recordings_render_lesson_tree($tree, static function (array $r) use ($me, $filterGid, $groups, $openEditId, $editKeep): void {
        $rid = (int) $r['id'];
        $ready = !empty($r['video_path']) || !empty($r['video_url']);
        $watch = url('ogretmen/kayit-izle.php?id=' . $rid);
        $mine = (int) $r['teacher_id'] === $me;
        $label = trim((string) ($r['_lesson_sub'] ?? ''));
        if ($label === '') {
            $label = (string) $r['title'];
        }
        $open = $openEditId === $rid;
        echo '<div class="vod-rec-card' . ($open ? ' is-editing' : '') . '" data-rec-card="' . $rid . '" id="kayit-' . $rid . '">';
        echo '<article class="vod-rec-view card flex flex-wrap items-start justify-between gap-3 p-5">';
        echo '<div><p class="font-extrabold">' . e($label) . '</p>';
        echo '<p class="text-sm text-muted">' . e(recording_card_meta($r)) . '</p>';
        echo '<p class="mt-1 text-xs text-muted">' . ($ready ? 'Video hazır' : 'Video yok') . '</p></div>';
        echo '<div class="flex flex-wrap items-center gap-2">';
        if ($ready) {
            echo '<a class="btn-primary text-sm" href="' . e($watch) . '">İzle</a>';
        }
        echo '<button type="button" class="btn-outline text-sm" data-rec-edit>Düzenle</button>';
        if ($mine) {
            echo panel_delete_form('', ['delete_id' => $rid], 'Bu ders kaydı silinsin mi?');
        }
        echo '</div></article>';
        ogretmen_kayit_inline_form($r, $groups, $open ? $editKeep : []);
        echo '</div>';
    }, [
        'split_groups' => $filterGid === 0 && count($groupNames) > 1,
        'movable' => true,
        'open_rec' => $openEditId,
        'group_extra' => static function (array $g): string {
            return '<a class="text-sm font-extrabold text-navy" href="' . e(url('ogretmen/kayit-yukle.php' . ogretmen_kayit_qs((int) $g['id']))) . '">Yalnız bu grup →</a>';
        },
    ]);
endif; ?>
<script>
(function () {
  var max = 300 * 1024 * 1024;
  document.querySelectorAll('input[name="video"]').forEach(function (input) {
    var form = input.form;
    if (!form || form.dataset.vodSizeBound) return;
    form.dataset.vodSizeBound = '1';
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
  });
})();
</script>
<?php panel_foot();
