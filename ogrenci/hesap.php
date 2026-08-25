<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogrenci');
$ok = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = post('phone');
    $city = post('city');
    update_user_contact((int) $u['id'], $phone, $city);
    $fresh = refresh_current_user((int) $u['id']);
    if ($fresh) {
        $u = $fresh;
    }
    $ok = 'Bilgileriniz kaydedildi.';
}

$enrolls = user_enrollments((int) $u['id']);
$mem = live_membership_state($u, $enrolls);
$packages = user_purchased_packages((int) $u['id']);
$orders = user_book_orders((int) $u['id']);
$books = user_shop_books((int) $u['id']);

panel_head('ogrenci', 'hesap', 'Hesabım | Öğrenci Paneli', $u);
membership_panel_banner($u);
?>
<section class="card profile-hero p-6">
  <?= user_avatar_html($u, 'lg') ?>
  <div>
    <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-muted">Öğrenci hesabı</p>
    <h2 class="font-display mt-1 text-3xl"><?= e((string) $u['name']) ?></h2>
    <p class="mt-1 text-sm text-muted"><?= e((string) $u['email']) ?></p>
    <p class="mt-3 <?= e(membership_kind_class((string) $mem['kind'])) ?> font-extrabold"><?= e((string) $mem['short']) ?></p>
    <?php if ($mem['expires']): ?>
      <p class="text-sm text-muted">Bitiş: <?= e(profile_dt($mem['expires'])) ?></p>
    <?php endif; ?>
  </div>
</section>

<div class="mt-6 grid gap-4 md:grid-cols-3">
  <div class="stat">
    <p class="stat-label">Telefon</p>
    <p class="stat-value text-xl"><?= e((string) ($u['phone'] ?: '—')) ?></p>
  </div>
  <div class="stat">
    <p class="stat-label">Şehir</p>
    <p class="stat-value text-xl"><?= e((string) ($u['city'] ?: '—')) ?></p>
  </div>
  <div class="stat">
    <p class="stat-label">Canlı üyelik</p>
    <p class="stat-value text-xl <?= e(membership_kind_class((string) $mem['kind'])) ?>"><?= e((string) $mem['label']) ?></p>
  </div>
</div>

<?php if ($ok): ?><p class="mt-5 font-bold text-green-700"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="mt-5 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>

<form method="post" class="card mt-6 grid max-w-xl gap-4 p-6">
  <p class="font-extrabold">İletişim</p>
  <p class="text-sm text-muted">Telefon ve şehri güncelleyebilirsiniz. E-posta giriş anahtarıdır, değiştirilemez.</p>
  <label class="text-sm font-bold">Telefon
    <input name="phone" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e((string) ($u['phone'] ?? '')) ?>">
  </label>
  <label class="text-sm font-bold">Şehir
    <input name="city" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e((string) ($u['city'] ?? '')) ?>">
  </label>
  <label class="text-sm font-bold">E-posta
    <input readonly class="mt-1 w-full rounded-xl border bg-soft px-3 py-2 text-muted" value="<?= e((string) $u['email']) ?>">
  </label>
  <button class="btn-primary">Kaydet</button>
</form>

<section class="card mt-6 overflow-hidden">
  <div class="px-5 py-4">
    <p class="stat-label">Satın alınan paketler</p>
    <h3 class="font-display mt-1 text-xl">Ders üyeliği</h3>
  </div>
  <?php if (!$packages && !$enrolls): ?>
    <p class="dash-empty px-5 pb-5">Henüz paketiniz yok.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>Paket / grup</th><th>Program</th><th>Durum</th><th>Kalan</th></tr></thead>
      <tbody>
        <?php foreach ($enrolls as $en):
            $em = membership_from_expires($en['expires_at'] ?? null);
            ?>
          <tr>
            <td class="font-extrabold"><?= e((string) ($en['package_name'] ?: $en['group_name'] ?? '—')) ?></td>
            <td><?= e((string) ($en['program_title'] ?? '—')) ?></td>
            <td><?= e((string) ($en['status'] ?? 'aktif')) ?></td>
            <td><span class="<?= e(membership_kind_class((string) $em['kind'])) ?>"><?= e((string) $em['label']) ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php foreach ($packages as $p): ?>
          <tr>
            <td class="font-extrabold"><?= e(payment_title($p)) ?></td>
            <td><?= e((string) ($p['program_title'] ?? '—')) ?></td>
            <td>ödendi</td>
            <td><?= e(profile_dt((string) ($p['paid_at'] ?: $p['created_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<?php if ($orders || $books): ?>
<section class="card mt-6 overflow-hidden">
  <div class="px-5 py-4">
    <p class="stat-label">Kitaplar</p>
    <h3 class="font-display mt-1 text-xl">Satın aldıklarınız</h3>
  </div>
  <table class="table">
    <thead><tr><th>Kitap</th><th>Tutar</th><th>Durum</th><th>Tarih</th></tr></thead>
    <tbody>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td><?= e((string) $o['title']) ?></td>
          <td><?= money((int) $o['total']) ?></td>
          <td><?= e(function_exists('shop_order_status_label') ? shop_order_status_label((string) $o['status']) : (string) $o['status']) ?></td>
          <td><?= e(profile_dt((string) $o['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$orders): ?>
        <?php foreach ($books as $b): ?>
          <tr>
            <td><?= e((string) $b['title']) ?></td>
            <td>—</td>
            <td><?= e((string) ($b['status'] ?? '')) ?></td>
            <td>—</td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>
<?php panel_foot();
