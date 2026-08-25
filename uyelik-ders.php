<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$u = current_user();
if (!$u || $u['role'] !== 'ogrenci') {
    redirect('giris-ders.php?next=' . rawurlencode('uyelik-ders'));
}
$err = '';
$packages = packages_active('ders');
$ilgiSel = trim((string) ($_GET['ilgi'] ?? ''));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pkg = package_by_id((int) post('package_id'));
    if (!$pkg || $pkg['kind'] !== 'ders' || !(int) $pkg['active']) {
        $err = 'Program / grup paketi seçin.';
    } else {
        try {
            $payment = membership_start_checkout($u, $pkg);
            redirect(odeme_sonuc_url('ok', (string) $payment['merchant_oid']));
        } catch (Throwable $e) {
            $err = $e->getMessage();
        }
    }
}
$sel = (int) ($_POST['package_id'] ?? 0);
if ($sel < 1) {
    foreach ($packages as $pkg) {
        if (package_matches_interest($pkg, $ilgiSel)) {
            $sel = (int) $pkg['id'];
            break;
        }
    }
    if ($sel < 1 && $packages) {
        $sel = (int) $packages[0]['id'];
    }
}
$mine = db()->prepare("SELECT g.name, e.expires_at, e.status FROM enrollments e JOIN class_groups g ON g.id=e.group_id WHERE e.student_id=? ORDER BY e.id DESC");
$mine->execute([(int) $u['id']]);
$mine = $mine->fetchAll();
public_head('Canlı ders üyeliği | Online İlahiyat');
?>
<main class="mx-auto max-w-xl px-4 py-14 lg:px-8">
  <p class="badge">Canlı eğitim</p>
  <h1 class="font-display mt-3 text-4xl">Üyelik satın al</h1>
  <p class="mt-2 text-muted">Öğrenci hesabınız açık. Yeni grup veya yenileme için paketi seçin; kayıt hemen düşer.</p>
  <?php if ($mine): ?>
    <div class="card mt-6 p-5">
      <p class="text-sm font-extrabold">Kayıtlı gruplarınız</p>
      <ul class="mt-2 grid gap-1 text-sm text-muted">
        <?php foreach ($mine as $row): ?>
          <li><?= e($row['name']) ?> · <?= e($row['status']) ?><?= $row['expires_at'] ? ' · ' . e($row['expires_at']) : '' ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
  <form method="post" class="card mt-6 p-6">
    <?php if ($err): ?><p class="mb-3 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>
    <div class="grid gap-2">
      <?php foreach ($packages as $pkg): ?>
        <label class="flex cursor-pointer items-start gap-3 rounded-xl border px-3 py-3 <?= $sel === (int) $pkg['id'] ? 'border-navy' : '' ?>">
          <input type="radio" name="package_id" value="<?= (int) $pkg['id'] ?>" <?= $sel === (int) $pkg['id'] ? 'checked' : '' ?> class="mt-1">
          <span>
            <span class="block font-extrabold"><?= e($pkg['name']) ?></span>
            <span class="text-sm text-muted"><?= money((int) $pkg['price']) ?> · <?= (int) $pkg['duration_days'] ?> gün<?= !empty($pkg['group_name']) ? ' · ' . e($pkg['group_name']) : '' ?> · <?= e(package_access_label($pkg)) ?></span>
          </span>
        </label>
      <?php endforeach; ?>
    </div>
    <p class="mt-3 text-xs text-muted">Satın alınca grup kaydı hemen hesabınıza düşer.</p>
    <button class="btn-primary mt-5 w-full">Satın al</button>
  </form>
</main>
<?php public_foot();
