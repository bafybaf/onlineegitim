<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('musteri');

$ordersSt = db()->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 6');
$ordersSt->execute([$u['id']]);
$orders = $ordersSt->fetchAll();

$cnt = db()->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
$cnt->execute([$u['id']]);
$orderCount = (int) $cnt->fetchColumn();

$booksSt = db()->prepare(
    'SELECT sb.id, sb.status, sb.kind, b.id book_id, b.title, b.slug, b.is_digital
     FROM student_books sb JOIN books b ON b.id = sb.book_id
     WHERE sb.user_id = ? ORDER BY sb.id DESC LIMIT 6'
);
$booksSt->execute([$u['id']]);
$books = $booksSt->fetchAll();

$bookCnt = db()->prepare('SELECT COUNT(*) FROM student_books WHERE user_id = ?');
$bookCnt->execute([$u['id']]);
$bookCount = (int) $bookCnt->fetchColumn();

$dlCnt = 0;
$dlSt = db()->prepare(
    'SELECT sb.status, sb.kind, b.is_digital FROM student_books sb JOIN books b ON b.id = sb.book_id WHERE sb.user_id = ?'
);
$dlSt->execute([$u['id']]);
foreach ($dlSt as $row) {
    if (shop_book_downloadable($row)) {
        $dlCnt++;
    }
}

$mem = shop_membership_state($u);
$cartN = cart_count();

panel_head('musteri', 'dashboard', 'Hesabım | Mağaza', $u);
if (function_exists('membership_panel_banner')) {
    membership_panel_banner($u);
}
?>
<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
  <div class="stat">
    <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-muted">Hesap</p>
    <p class="font-display mt-1 text-xl"><?= e($mem['label']) ?></p>
    <p class="mt-1 text-sm text-muted"><?= e($mem['detail']) ?></p>
  </div>
  <div class="stat">
    <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-muted">Siparişler</p>
    <p class="font-display mt-1 text-2xl"><?= $orderCount ?></p>
    <a class="mt-2 inline-block text-sm font-extrabold text-navy" href="<?= e(url('magaza/siparisler.php')) ?>">Tümünü gör →</a>
  </div>
  <div class="stat">
    <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-muted">Kitaplarım</p>
    <p class="font-display mt-1 text-2xl"><?= $bookCount ?></p>
    <p class="mt-1 text-sm text-muted"><?= $dlCnt ?> dijital kopya hazır</p>
    <a class="mt-2 inline-block text-sm font-extrabold text-navy" href="<?= e(url('magaza/kitaplarim.php')) ?>">Kitaplarım →</a>
  </div>
  <div class="stat">
    <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-muted">Sepet</p>
    <p class="font-display mt-1 text-2xl"><?= $cartN ?></p>
    <p class="mt-1 text-sm text-muted"><?= $cartN ? 'Satın almaya devam edin.' : 'Sepetiniz boş.' ?></p>
    <a class="mt-2 inline-block text-sm font-extrabold text-navy" href="<?= e(page_url('sepet')) ?>"><?= $cartN ? 'Sepete git →' : 'Mağazayı aç →' ?></a>
  </div>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
  <section class="card overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4">
      <h2 class="font-display text-xl">Son siparişler</h2>
      <a class="text-sm font-extrabold text-navy" href="<?= e(url('magaza/siparisler.php')) ?>">Tümü</a>
    </div>
    <?php if (!$orders): ?>
      <?php shop_empty('Henüz sipariş yok', 'Kitap mağazasından ilk siparişinizi verin.', page_url('kitaplar'), 'Mağazaya git'); ?>
    <?php else: ?>
      <table class="table">
        <thead><tr><th>No</th><th>Tutar</th><th>Durum</th><th>Tarih</th></tr></thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td class="font-bold"><?= e((string) ($o['merchant_oid'] ?: '#' . $o['id'])) ?></td>
              <td><?= money((int) $o['total']) ?></td>
              <td><span class="<?= shop_status_class((string) $o['status']) ?>"><?= e(shop_order_status_label((string) $o['status'])) ?></span></td>
              <td class="text-muted"><?= e(shop_date((string) $o['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

  <section class="card overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4">
      <h2 class="font-display text-xl">Kitaplarım</h2>
      <a class="text-sm font-extrabold text-navy" href="<?= e(url('magaza/kitaplarim.php')) ?>">Tümü</a>
    </div>
    <?php if (!$books): ?>
      <?php shop_empty('Kitaplığınız boş', 'Satın aldığınız basılı ve dijital kitaplar burada listelenir.', page_url('kitaplar'), 'Kitap seç'); ?>
    <?php else: ?>
      <table class="table">
        <thead><tr><th>Kitap</th><th>Durum</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($books as $b): ?>
            <tr>
              <td><?= e($b['title']) ?></td>
              <td><span class="<?= shop_status_class((string) $b['status']) ?>"><?= e(utf8_from_mojibake((string) $b['status'])) ?></span></td>
              <td>
                <?php if (shop_book_downloadable($b)): ?>
                  <a class="text-sm font-extrabold text-navy" href="<?= e(url('magaza/indir.php?id=' . (int) $b['id'])) ?>">İndir</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</div>

<section class="card mt-6 p-5">
  <div class="flex flex-wrap items-center justify-between gap-4">
    <div class="profile-hero">
      <?= user_avatar_html($u, 'md') ?>
      <div>
        <h2 class="font-display text-xl">Hesap özeti</h2>
        <p class="mt-1 text-sm text-muted"><?= e($u['name']) ?> · <?= e($u['email']) ?><?= !empty($u['phone']) ? ' · ' . e((string) $u['phone']) : '' ?></p>
      </div>
    </div>
    <div class="flex flex-wrap gap-2">
      <a class="btn-outline h-10 px-4 text-sm" href="<?= e(url('magaza/profil.php')) ?>">Profili düzenle</a>
      <a class="btn-primary h-10 px-4 text-sm" href="<?= e(page_url('kitaplar')) ?>">Mağazaya git</a>
    </div>
  </div>
</section>
<?php panel_foot();
