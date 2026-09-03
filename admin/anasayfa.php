<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');

$emptySlide = [
    'id' => 0,
    'badge' => '',
    'title' => '',
    'title_accent' => '',
    'accent_class' => 'accent',
    'body' => '',
    'btn1_label' => '',
    'btn1_url' => '',
    'btn2_label' => '',
    'btn2_url' => '',
    'btn2_kind' => 'link',
    'image' => '',
    'alt' => '',
    'active' => 1,
];
$emptyHi = ['id' => 0, 'mark' => '', 'label' => '', 'active' => 1];

$slide = $emptySlide;
$hi = $emptyHi;
$sid = (int) ($_GET['slide'] ?? 0);
$hid = (int) ($_GET['hi'] ?? 0);
if ($sid > 0) {
    $found = home_slide($sid);
    if ($found) {
        $slide = $found;
    }
}
if ($hid > 0) {
    $found = home_highlight($hid);
    if ($found) {
        $hi = $found;
    }
}

$err = '';
$action = post('action');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'delete_slide') {
        db()->prepare('DELETE FROM home_slides WHERE id=?')->execute([(int) post('id')]);
        flash_ok('Hero slaytı silindi.');
        redirect('admin/anasayfa');
    }
    if ($action === 'delete_hi') {
        db()->prepare('DELETE FROM home_highlights WHERE id=?')->execute([(int) post('id')]);
        flash_ok('Öne çıkan satır silindi.');
        redirect('admin/anasayfa');
    }
    if ($action === 'save_slide') {
        $id = (int) post('id');
        $badge = post('badge');
        $title = post('title');
        $titleAccent = post('title_accent');
        $accent = post('accent_class') === 'navy' ? 'navy' : 'accent';
        $body = post('body');
        $btn1Label = post('btn1_label');
        $btn1Url = post('btn1_url');
        $btn2Label = post('btn2_label');
        $btn2Url = post('btn2_url');
        $btn2Kind = post('btn2_kind') === 'call' ? 'call' : 'link';
        $alt = post('alt');
        $active = isset($_POST['active']) ? 1 : 0;
        $image = $id > 0 ? (string) (home_slide($id)['image'] ?? '') : '';
        if ($title === '') {
            $err = 'Slayt başlığı zorunlu.';
            $slide = array_merge($slide, compact('badge', 'title', 'titleAccent') + [
                'title_accent' => $titleAccent,
                'accent_class' => $accent,
                'body' => $body,
                'btn1_label' => $btn1Label,
                'btn1_url' => $btn1Url,
                'btn2_label' => $btn2Label,
                'btn2_url' => $btn2Url,
                'btn2_kind' => $btn2Kind,
                'alt' => $alt,
                'active' => $active,
                'id' => $id,
            ]);
        } else {
            try {
                $uploaded = catalog_store_upload('image', 'home', 'slide');
                if ($uploaded) {
                    $image = $uploaded;
                }
                if ($id > 0) {
                    db()->prepare(
                        'UPDATE home_slides SET badge=?, title=?, title_accent=?, accent_class=?, body=?, btn1_label=?, btn1_url=?, btn2_label=?, btn2_url=?, btn2_kind=?, image=?, alt=?, active=? WHERE id=?'
                    )->execute([$badge, $title, $titleAccent, $accent, $body, $btn1Label, $btn1Url, $btn2Label, $btn2Url, $btn2Kind, $image, $alt, $active, $id]);
                } else {
                    $sort = home_next_sort('home_slides');
                    db()->prepare(
                        'INSERT INTO home_slides (badge, title, title_accent, accent_class, body, btn1_label, btn1_url, btn2_label, btn2_url, btn2_kind, image, alt, active, sort) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                    )->execute([$badge, $title, $titleAccent, $accent, $body, $btn1Label, $btn1Url, $btn2Label, $btn2Url, $btn2Kind, $image, $alt, $active, $sort]);
                }
                flash_ok('Hero slaytı kaydedildi.');
                redirect('admin/anasayfa');
            } catch (Throwable $e) {
                $err = $e->getMessage();
            }
        }
    }
    if ($action === 'save_hi') {
        $id = (int) post('id');
        $mark = mb_substr(post('mark'), 0, 16);
        $label = post('label');
        $active = isset($_POST['active']) ? 1 : 0;
        if ($label === '') {
            $err = 'Öne çıkan satır metni zorunlu.';
            $hi = ['id' => $id, 'mark' => $mark, 'label' => $label, 'active' => $active];
        } else {
            if ($id > 0) {
                db()->prepare('UPDATE home_highlights SET mark=?, label=?, active=? WHERE id=?')->execute([$mark, $label, $active, $id]);
            } else {
                $sort = home_next_sort('home_highlights');
                db()->prepare('INSERT INTO home_highlights (mark, label, active, sort) VALUES (?,?,?,?)')->execute([$mark, $label, $active, $sort]);
            }
            flash_ok('Öne çıkan satır kaydedildi.');
            redirect('admin/anasayfa');
        }
    }
}

$ok = flash_ok();
$slides = home_slides(false);
$highlights = home_highlights(false);
panel_head('admin', 'anasayfa', 'Anasayfa | Admin', $u);
?>
<?php if ($ok): ?><p class="mb-4 font-bold text-green-700"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>

<h2 class="font-display text-2xl">Hero görselleri</h2>
<p class="mb-4 text-sm text-muted">Ana sayfa üst bölüm. Tam genişlik, 900 px yükseklik. Sürükleyerek sıralayın.</p>

<form method="post" enctype="multipart/form-data" class="card mb-6 grid gap-3 p-5">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save_slide">
  <input type="hidden" name="id" value="<?= (int) $slide['id'] ?>">
  <input type="hidden" name="title" value="<?= e((string) ($slide['title'] ?: 'Hero')) ?>">
  <label class="text-sm font-bold">Link (tıklanınca gideceği sayfa)
    <input name="btn1_url" class="mt-1 w-full rounded-xl border px-3 py-2" placeholder="kayit-ders veya https://..." value="<?= e((string) $slide['btn1_url']) ?>">
  </label>
  <p class="text-xs text-muted">Boş bırakılırsa görsel tıklanamaz. Örnek: <code>kayit-ders</code>, <code>programlar</code>, <code>kitaplar</code> veya tam URL.</p>
  <label class="text-sm font-bold">Görsel alt metni<input name="alt" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e((string) $slide['alt']) ?>"></label>
  <?php $imgSrc = home_image_src((string) ($slide['image'] ?? '')); ?>
  <?php if ($imgSrc !== ''): ?>
    <img src="<?= e($imgSrc) ?>" alt="" class="h-40 w-full rounded-xl object-cover">
  <?php endif; ?>
  <label class="text-sm font-bold">Görsel (en az 1920×900 px)<input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="mt-1 w-full text-sm"></label>
  <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="active" value="1" <?= (int) $slide['active'] ? 'checked' : '' ?>> Yayında</label>
  <div class="flex flex-wrap gap-2">
    <button class="btn-primary"><?= (int) $slide['id'] ? 'Görseli güncelle' : 'Görsel ekle' ?></button>
    <?php if ((int) $slide['id']): ?>
      <a class="btn-outline" href="<?= e(url('admin/anasayfa')) ?>">Vazgeç</a>
    <?php endif; ?>
  </div>
</form>

<div data-sort-table="home_slides">
<?php foreach ($slides as $s): ?>
  <article class="card mb-3 flex flex-wrap items-center justify-between gap-3 p-3" data-sort-id="<?= (int) $s['id'] ?>">
    <span class="sort-handle" title="Sürükleyerek sıralayın">⋮⋮</span>
    <?php $thumbSrc = home_image_src((string) ($s['image'] ?? '')); ?>
    <?php if ($thumbSrc !== ''): ?><img src="<?= e($thumbSrc) ?>" alt="" class="h-16 w-28 rounded-lg object-cover"><?php endif; ?>
    <div class="min-w-0 flex-1">
      <p class="font-extrabold"><?= e((string) ($s['alt'] ?: $s['title'])) ?></p>
      <p class="text-sm text-muted"><?= (int) $s['active'] ? 'Yayında' : 'Gizli' ?></p>
    </div>
    <div class="flex gap-2">
      <a class="btn-outline text-sm" href="<?= e(url('admin/anasayfa.php?slide=' . (int) $s['id'])) ?>">Düzenle</a>
      <form method="post" onsubmit="return confirm('Bu slayt silinsin mi?')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_slide">
        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
        <button class="btn-outline text-sm">Sil</button>
      </form>
    </div>
  </article>
<?php endforeach; ?>
</div>

<h2 class="font-display mt-10 text-2xl">Alt şerit</h2>
<p class="mb-4 text-sm text-muted">Hero’nun hemen altındaki dört (veya daha fazla) madde. İşaret: 10, ▶, kısa emoji.</p>

<form method="post" class="card mb-6 grid gap-3 p-5 sm:grid-cols-[120px_1fr_auto] sm:items-end">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save_hi">
  <input type="hidden" name="id" value="<?= (int) $hi['id'] ?>">
  <label class="text-sm font-bold">İşaret<input name="mark" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e((string) $hi['mark']) ?>"></label>
  <label class="text-sm font-bold">Metin<input name="label" required class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e((string) $hi['label']) ?>"></label>
  <div class="flex flex-wrap items-center gap-3">
    <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="active" value="1" <?= (int) $hi['active'] ? 'checked' : '' ?>> Yayında</label>
    <button class="btn-primary"><?= (int) $hi['id'] ? 'Güncelle' : 'Ekle' ?></button>
    <?php if ((int) $hi['id']): ?><a class="btn-outline text-sm" href="<?= e(url('admin/anasayfa')) ?>">Vazgeç</a><?php endif; ?>
  </div>
</form>

<div data-sort-table="home_highlights">
<?php foreach ($highlights as $h): ?>
  <article class="card mb-3 flex flex-wrap items-center justify-between gap-3 p-5" data-sort-id="<?= (int) $h['id'] ?>">
    <span class="sort-handle" title="Sürükleyerek sıralayın">⋮⋮</span>
    <p class="flex min-w-0 flex-1 items-center gap-3 font-extrabold"><span class="grid h-10 w-10 place-items-center rounded-full bg-soft text-navy"><?= e((string) $h['mark']) ?></span><?= e((string) $h['label']) ?><?php if (!(int) $h['active']): ?> <span class="text-sm font-bold text-muted">· gizli</span><?php endif; ?></p>
    <div class="flex gap-2">
      <a class="btn-outline text-sm" href="<?= e(url('admin/anasayfa.php?hi=' . (int) $h['id'])) ?>">Düzenle</a>
      <form method="post" onsubmit="return confirm('Silinsin mi?')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_hi">
        <input type="hidden" name="id" value="<?= (int) $h['id'] ?>">
        <button class="btn-outline text-sm">Sil</button>
      </form>
    </div>
  </article>
<?php endforeach; ?>
</div>
<?php panel_foot();
