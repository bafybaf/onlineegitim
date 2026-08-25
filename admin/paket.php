<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$admin = require_role('admin');
$rawId = $_GET['id'] ?? null;
$isNew = $rawId !== null && (int) $rawId === 0;
$id = (int) ($rawId ?? 0);
$pkg = [
    'id' => 0,
    'name' => '',
    'duration_days' => 365,
    'price' => 0,
    'active' => 1,
    'access_type' => 'canli_video',
    'gift_book_id' => 0,
    'program_id' => 0,
    'default_group_id' => 0,
];
if (!$isNew) {
    if ($id < 1) {
        redirect('admin/uyelikler');
    }
    $found = package_by_id($id);
    if (!$found || ($found['kind'] ?? '') !== 'ders') {
        flash_error('Paket bulunamadı.');
        redirect('admin/uyelikler');
    }
    $pkg = $found;
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = post('act');
    try {
        if ($act === 'sil' && !$isNew) {
            try {
                db()->prepare("DELETE FROM packages WHERE id = ? AND kind = 'ders'")->execute([$id]);
                flash_ok('Paket silindi.');
            } catch (Throwable) {
                db()->prepare("UPDATE packages SET active = 0 WHERE id = ? AND kind = 'ders'")->execute([$id]);
                flash_ok('Pakete kayıt bağlı. Silinmedi, satışa kapatıldı.');
            }
            redirect('admin/uyelikler');
        }
        $id = admin_save_package($isNew ? 0 : $id, [
            'name' => post('name'),
            'price' => post('price'),
            'duration_days' => post('duration_days'),
            'active' => post('active'),
            'access_type' => post('access_type'),
            'gift_book_id' => post('gift_book_id'),
            'program_id' => post('program_id'),
            'default_group_id' => post('default_group_id'),
        ]);
        flash_ok($isNew ? 'Paket eklendi.' : 'Paket güncellendi.');
        redirect(paket_admin_url($id));
    } catch (Throwable $e) {
        $err = $e->getMessage();
        $pkg['name'] = post('name');
        $pkg['price'] = (int) post('price');
        $pkg['duration_days'] = (int) post('duration_days');
        $pkg['active'] = post('active') === '1' ? 1 : 0;
        $pkg['access_type'] = post('access_type');
        $pkg['gift_book_id'] = (int) post('gift_book_id');
        $pkg['program_id'] = (int) post('program_id');
        $pkg['default_group_id'] = (int) post('default_group_id');
    }
}

$programs = db()->query('SELECT id, title FROM programs ORDER BY title')->fetchAll();
$groups = db()->query(
    'SELECT g.id, g.name, g.program_id, p.title AS program_title FROM class_groups g JOIN programs p ON p.id = g.program_id ORDER BY p.title, g.name'
)->fetchAll();
$books = db()->query('SELECT id, title FROM books ORDER BY title')->fetchAll();
$ok = flash_ok();

panel_head('admin', 'uyelikler', ($isNew ? 'Yeni paket' : 'Paket') . ' | Admin', $admin);
?>
<p class="mb-4"><a class="text-sm font-extrabold text-navy" href="<?= e(url('admin/uyelikler')) ?>">← Üyelik paketleri</a></p>
<?php if ($ok): ?><p class="mb-4 font-bold text-green-700"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>

<section class="card p-5">
  <p class="stat-label">Üyelik</p>
  <h3 class="font-display mt-1 text-xl"><?= $isNew ? 'Yeni paket' : 'Paketi düzenle' ?></h3>
  <p class="mt-1 text-sm text-muted">Ders kayıt formunda görünür. Grup seçerseniz program otomatik bağlanır. Hediye kitap ödeme onayında Kitaplarım’a düşer.</p>
  <form method="post" class="mt-4 grid gap-3 md:grid-cols-2">
    <?= csrf_field() ?>
    <label class="text-sm font-bold md:col-span-2">Paket adı
      <input required name="name" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= e((string) $pkg['name']) ?>" placeholder="Tefsir A · yıllık">
    </label>
    <label class="text-sm font-bold">Program
      <select name="program_id" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
        <option value="0">Seçilmedi</option>
        <?php foreach ($programs as $p): ?>
          <option value="<?= (int) $p['id'] ?>" <?= (int) ($pkg['program_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>><?= e((string) $p['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="text-sm font-bold">Varsayılan grup
      <select name="default_group_id" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
        <option value="0">Grup yok</option>
        <?php foreach ($groups as $g): ?>
          <option value="<?= (int) $g['id'] ?>" <?= (int) ($pkg['default_group_id'] ?? 0) === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['program_title'] . ' · ' . $g['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="text-sm font-bold">Süre (gün)
      <input required type="number" min="1" name="duration_days" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= (int) ($pkg['duration_days'] ?: 365) ?>">
    </label>
    <label class="text-sm font-bold">Fiyat (₺)
      <input required type="number" min="1" name="price" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= (int) ($pkg['price'] ?: '') ?>">
    </label>
    <label class="text-sm font-bold">Erişim
      <select name="access_type" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
        <option value="canli_video" <?= ($pkg['access_type'] ?? '') !== 'sadece_video' ? 'selected' : '' ?>>Canlı + kayıt</option>
        <option value="sadece_video" <?= ($pkg['access_type'] ?? '') === 'sadece_video' ? 'selected' : '' ?>>Yalnızca kayıt</option>
      </select>
    </label>
    <label class="text-sm font-bold">Hediye kitap
      <select name="gift_book_id" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
        <option value="0">Yok</option>
        <?php foreach ($books as $b): ?>
          <option value="<?= (int) $b['id'] ?>" <?= (int) ($pkg['gift_book_id'] ?? 0) === (int) $b['id'] ? 'selected' : '' ?>><?= e((string) $b['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="flex items-center gap-2 text-sm font-bold md:col-span-2">
      <input type="checkbox" name="active" value="1" <?= (int) ($pkg['active'] ?? 1) ? 'checked' : '' ?>> Satışta (aktif)
    </label>
    <div class="flex flex-wrap gap-2 md:col-span-2">
      <button class="btn-primary"><?= $isNew ? 'Paketi oluştur' : 'Kaydet' ?></button>
      <?php if (!$isNew): ?>
        <button class="btn-outline" name="act" value="sil" onclick="return confirm('Paket silinsin mi? Kayıt varsa yalnızca pasife alınır.')">Sil</button>
      <?php endif; ?>
    </div>
  </form>
</section>
<?php panel_foot();
