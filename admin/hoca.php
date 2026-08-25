<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$admin = require_role('admin');
$rawId = $_GET['id'] ?? null;
$isNew = $rawId !== null && (int) $rawId === 0;
$id = (int) ($rawId ?? 0);
$person = [
    'id' => 0,
    'name' => '',
    'email' => '',
    'phone' => '',
    'city' => '',
    'bio' => '',
    'status' => 'aktif',
    'avatar' => '',
    'slug' => '',
];
if (!$isNew) {
    if ($id < 1) {
        redirect('admin/hocalar');
    }
    $st = db()->prepare("SELECT * FROM users WHERE id = ? AND role = 'ogretmen'");
    $st->execute([$id]);
    $found = $st->fetch();
    if (!$found) {
        flash_error('Bu kayıt bir hoca değil.');
        redirect('admin/hocalar');
    }
    $person = $found;
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post('act') === 'sil' && !$isNew) {
        try {
            flash_ok(admin_delete_user($id, (int) $admin['id']));
            redirect('admin/hocalar');
        } catch (Throwable $e) {
            $err = $e->getMessage();
        }
    } else {
    try {
        $id = admin_save_user($isNew ? 0 : $id, [
            'name' => post('name'),
            'email' => post('email'),
            'phone' => post('phone'),
            'city' => post('city'),
            'bio' => post('bio'),
            'role' => 'ogretmen',
            'status' => post('status'),
            'password' => post('password'),
        ], (int) $admin['id']);
        flash_ok($isNew ? 'Hoca eklendi.' : 'Hoca güncellendi.');
        redirect(hoca_admin_url($id));
    } catch (Throwable $e) {
        $err = $e->getMessage();
        $person['name'] = post('name');
        $person['email'] = post('email');
        $person['phone'] = post('phone');
        $person['city'] = post('city');
        $person['bio'] = post('bio');
        $person['status'] = post('status') ?: $person['status'];
    }
    }
}

$ok = flash_ok();
$groups = [];
$liveN = 0;
if (!$isNew) {
    $gs = db()->prepare('SELECT g.id, g.name, p.title program_title FROM class_groups g JOIN programs p ON p.id = g.program_id WHERE g.teacher_id = ? ORDER BY p.title, g.name');
    $gs->execute([$id]);
    $groups = $gs->fetchAll();
    $lv = db()->prepare("SELECT COUNT(*) FROM live_rooms WHERE teacher_id = ? AND status = 'live'");
    $lv->execute([$id]);
    $liveN = (int) $lv->fetchColumn();
}

panel_head('admin', 'hocalar', ($isNew ? 'Yeni hoca' : 'Hoca') . ' | Admin', $admin);
?>
<p class="mb-4"><a class="text-sm font-extrabold text-navy" href="<?= e(url('admin/hocalar')) ?>">← Hocalar</a></p>
<?php if ($ok): ?><p class="mb-4 font-bold text-green-700"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>

<?php if (!$isNew): ?>
<section class="card profile-hero p-6">
  <?= user_avatar_html($person, 'lg') ?>
  <div>
    <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-navy"><?= e((string) ($person['city'] ?: 'Hoca')) ?></p>
    <h2 class="font-display mt-1 text-3xl"><?= e((string) $person['name']) ?></h2>
    <p class="mt-1 text-sm text-muted"><?= e((string) $person['email']) ?></p>
    <p class="mt-3 flex flex-wrap gap-2">
      <span class="shop-pill"><?= e(user_status_label((string) $person['status'])) ?></span>
      <span class="shop-pill"><?= count($groups) ?> grup</span>
      <?php if ($liveN > 0): ?><span class="shop-pill mem-soft"><?= $liveN ?> açık oda</span><?php endif; ?>
    </p>
    <?php if (!empty($person['slug'])): ?>
      <p class="mt-3 text-sm"><a class="font-extrabold text-navy" href="<?= e(page_url('hoca', (string) $person['slug'])) ?>" target="_blank" rel="noreferrer">Kadro profili →</a></p>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<section class="card mt-6 p-5">
  <p class="stat-label">Kadro</p>
  <h3 class="font-display mt-1 text-xl"><?= $isNew ? 'Yeni hoca' : 'Bilgileri düzenle' ?></h3>
  <p class="mt-1 text-sm text-muted">
    <?= $isNew
        ? 'Öğretmen hesabı açılır; ders girişi bu e-posta ile yapılır. Şifre en az 8 karakter.'
        : 'Şifreyi boş bırakırsanız değişmez. Ünvan / özgeçmiş kadro sayfasında görünür.' ?>
  </p>
  <form method="post" enctype="multipart/form-data" class="mt-4 grid gap-3 md:grid-cols-2">
    <?= csrf_field() ?>
    <label class="text-sm font-bold">Ad soyad
      <input required name="name" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= e((string) $person['name']) ?>" autocomplete="name">
    </label>
    <label class="text-sm font-bold">E-posta
      <input required type="email" name="email" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= e((string) $person['email']) ?>" autocomplete="email">
    </label>
    <label class="text-sm font-bold">Telefon
      <input name="phone" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= e((string) ($person['phone'] ?? '')) ?>" autocomplete="tel">
    </label>
    <label class="text-sm font-bold">Şehir
      <input name="city" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= e((string) ($person['city'] ?? '')) ?>">
    </label>
    <label class="text-sm font-bold">Durum
      <select name="status" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
        <?php foreach (['aktif' => 'Aktif', 'pasif' => 'Pasif', 'bekliyor' => 'Bekliyor'] as $val => $lab): ?>
          <option value="<?= e($val) ?>" <?= ($person['status'] ?? '') === $val ? 'selected' : '' ?>><?= e($lab) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="text-sm font-bold">Şifre
      <input type="password" name="password" <?= $isNew ? 'required minlength="8"' : 'minlength="8"' ?> class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" placeholder="<?= $isNew ? 'En az 8 karakter' : 'Boş bırakırsanız değişmez' ?>" autocomplete="new-password">
    </label>
    <label class="text-sm font-bold md:col-span-2">Fotoğraf
      <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
    </label>
    <label class="text-sm font-bold md:col-span-2">Ünvan / özgeçmiş
      <textarea name="bio" rows="4" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" placeholder="Tefsir usulü, canlı ders…"><?= e((string) ($person['bio'] ?? '')) ?></textarea>
    </label>
    <div class="md:col-span-2">
      <button class="btn-primary"><?= $isNew ? 'Hocayı ekle' : 'Kaydet' ?></button>
    </div>
  </form>
  <?php if (!$isNew): ?>
    <div class="mt-3">
      <?= panel_delete_form(hoca_admin_url($id), ['act' => 'sil'], 'Hoca silinsin mi? Gruplara bağlıysa silinmez.', 'Hocayı sil', 'btn-outline') ?>
    </div>
  <?php endif; ?>
</section>

<?php if (!$isNew): ?>
<section class="card mt-6 overflow-hidden">
  <div class="px-5 py-4">
    <p class="stat-label">Sınıflar</p>
    <h3 class="font-display mt-1 text-xl">Gruplar</h3>
  </div>
  <?php if (!$groups): ?>
    <p class="dash-empty px-5 pb-5">Bu hocaya henüz grup bağlı değil. <a class="font-extrabold text-navy" href="<?= e(url('admin/gruplar')) ?>">Gruplar</a></p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>Grup</th><th>Program</th></tr></thead>
      <tbody>
        <?php foreach ($groups as $g): ?>
          <tr>
            <td class="font-extrabold"><a class="text-navy" href="<?= e(grup_url((int) $g['id'])) ?>"><?= e((string) $g['name']) ?></a></td>
            <td><?= e((string) $g['program_title']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>
<?php endif; ?>
<?php panel_foot();
