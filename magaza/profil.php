<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('musteri');
$ok = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_own_account_post($u, $ok, $err);
}

$mem = live_membership_state($u);
$orders = user_book_orders((int) $u['id']);
$pays = user_paid_payments((int) $u['id']);
$books = user_shop_books((int) $u['id']);

panel_head('musteri', 'profil', 'Profilim | Mağaza', $u);
?>
<section class="card profile-hero mb-6 p-6">
  <?= user_avatar_html($u, 'lg', true) ?>
  <div>
    <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-muted">Mağaza hesabı</p>
    <h2 class="font-display mt-1 text-3xl"><?= e((string) $u['name']) ?></h2>
    <p class="mt-1 text-sm text-muted"><?= e((string) $u['email']) ?><?= !empty($u['phone']) ? ' · ' . e((string) $u['phone']) : '' ?></p>
    <p class="mt-3 <?= e(membership_kind_class((string) $mem['kind'])) ?> font-extrabold"><?= e((string) $mem['short']) ?></p>
    <?php if ($mem['expires']): ?>
      <p class="text-sm text-muted">Bitiş: <?= e(profile_dt($mem['expires'])) ?></p>
    <?php endif; ?>
  </div>
</section>

<p class="mb-5 text-sm text-muted">Mağaza hesabı bilgileriniz. E-posta giriş anahtarıdır, değiştirilemez.</p>
<?php if ($ok): ?><p class="mb-4 font-bold text-green-700"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>
<?php profile_contact_form($u); ?>
<?php profile_password_form($u); ?>

<section class="card mt-6 overflow-hidden">
  <div class="px-5 py-4">
    <p class="stat-label">Alışveriş</p>
    <h3 class="font-display mt-1 text-xl">Satın alma geçmişi</h3>
  </div>
  <?php if (!$orders && !$pays && !$books): ?>
    <p class="dash-empty px-5 pb-5">Henüz satın alma yok.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>Kalem</th><th>Tutar</th><th>Durum</th><th>Tarih</th></tr></thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td class="font-extrabold"><?= e((string) $o['title']) ?></td>
            <td><?= money((int) $o['total']) ?></td>
            <td><?= e(shop_order_status_label((string) $o['status'])) ?></td>
            <td><?= e(profile_dt((string) $o['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php foreach ($pays as $p): ?>
          <?php if (($p['kind'] ?? '') === 'kitap') {
              continue;
          } ?>
          <tr>
            <td class="font-extrabold"><?= e(payment_title($p)) ?></td>
            <td><?= money((int) $p['total']) ?></td>
            <td>ödendi</td>
            <td><?= e(profile_dt((string) ($p['paid_at'] ?: $p['created_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>
<?php profile_delete_form($u); ?>
<?php panel_foot();
