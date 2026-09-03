<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('musteri');

$st = db()->prepare(
    'SELECT o.*,
            (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) AS item_n
     FROM orders o
     WHERE o.user_id = ?
     ORDER BY o.id DESC'
);
$st->execute([$u['id']]);
$orders = $st->fetchAll();

$itemsByOrder = [];
if ($orders) {
    $ids = array_map(static fn(array $o): int => (int) $o['id'], $orders);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $it = db()->prepare(
        "SELECT i.order_id, i.qty, i.price, b.title
         FROM order_items i JOIN books b ON b.id = i.book_id
         WHERE i.order_id IN ($in)"
    );
    $it->execute($ids);
    foreach ($it as $row) {
        $itemsByOrder[(int) $row['order_id']][] = $row;
    }
    foreach (program_purchases_for_orders($ids) as $oid => $progs) {
        foreach ($progs as $row) {
            $itemsByOrder[$oid][] = [
                'title' => (string) $row['title'],
                'qty' => 1,
                'price' => (int) $row['price'],
            ];
        }
    }
}

panel_head('musteri', 'siparisler', 'Siparişlerim | Mağaza', $u);
?>
<p class="mb-5 text-sm text-muted">Kitap ve eğitim siparişlerinizin ödeme, teslimat ve güncel durumu.</p>
<?php if (!$orders): ?>
  <div class="card">
    <?php shop_empty('Henüz siparişiniz yok', 'Ödeme sonrası siparişler burada görünür. Kargo ve dijital teslim ayrı izlenir.', page_url('kitaplar'), 'Mağazadan kitap al'); ?>
  </div>
<?php else: ?>
  <div class="grid gap-4">
    <?php foreach ($orders as $o): ?>
      <article class="card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-muted">Sipariş <?= e((string) ($o['merchant_oid'] ?: '#' . $o['id'])) ?></p>
            <h2 class="font-display mt-1 text-xl"><?= money((int) $o['total']) ?></h2>
            <p class="mt-1 text-sm text-muted"><?= e(shop_date((string) $o['created_at'])) ?> · <?= e(shop_ship_label((string) $o['ship_mode'])) ?><?= !empty($o['coupon']) ? ' · kupon ' . e((string) $o['coupon']) : '' ?></p>
            <?php
              $shipTxt = function_exists('address_format') ? address_format($o) : '';
              if ($shipTxt !== ''):
            ?>
              <p class="mt-1 text-sm text-muted">Teslimat: <?= e($shipTxt) ?></p>
            <?php endif; ?>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <span class="<?= shop_status_class((string) $o['status']) ?>"><?= e(shop_order_status_label((string) $o['status'])) ?></span>
            <?php if (!empty($o['pay_status']) && $o['pay_status'] !== 'odendi'): ?>
              <span class="<?= shop_status_class((string) $o['pay_status']) ?>"><?= e(shop_pay_status_label((string) $o['pay_status'])) ?></span>
            <?php endif; ?>
            <a class="btn-outline text-sm" href="<?= e(url('magaza/fatura.php?id=' . (int) $o['id'])) ?>">Fatura özeti</a>
          </div>
        </div>
        <?php if ((string) ($o['ship_mode'] ?? '') !== 'dijital' && (!empty($o['ship_tracking']) || !empty($o['ship_carrier']))): ?>
          <p class="mt-3 text-sm">
            <?php if (!empty($o['ship_carrier'])): ?><span class="font-extrabold"><?= e(shop_carrier_label((string) $o['ship_carrier'])) ?></span><?php endif; ?>
            <?php if (!empty($o['ship_tracking'])): ?> · Takip: <?= e((string) $o['ship_tracking']) ?><?php endif; ?>
            <?php if (!empty($o['ship_tracking_url'])): ?>
              · <a class="font-extrabold text-navy" href="<?= e((string) $o['ship_tracking_url']) ?>" target="_blank" rel="noreferrer">Kargo takibi</a>
            <?php endif; ?>
          </p>
        <?php endif; ?>
        <?php $lines = $itemsByOrder[(int) $o['id']] ?? []; ?>
        <?php if ($lines): ?>
          <ul class="mt-4 grid gap-1 text-sm">
            <?php foreach ($lines as $line): ?>
              <li><?= e($line['title']) ?> · <?= (int) $line['qty'] ?> adet · <?= money((int) $line['price']) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="mt-3 text-sm text-muted"><?= (int) $o['item_n'] ?> kalem</p>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php panel_foot();
