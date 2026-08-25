<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');

$id = (int) ($_GET['id'] ?? post('id') ?: 0);
if ($id < 1) {
    redirect('admin/siparisler');
}

if (post('id') && post('act') === 'ilerlet') {
    $st = db()->prepare(
        'SELECT o.*, u.email, u.role, u.name FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id=?'
    );
    $st->execute([$id]);
    $cur = $st->fetch();
    if ($cur) {
        $res = shop_order_advance($cur, (int) $u['id']);
        if ($res['changed']) {
            $msg = 'Sipariş durumu güncellendi. Müşteriye panel bildirimi gönderildi.';
            if ($res['mailed']) {
                $msg = 'Sipariş durumu güncellendi. Müşteriye e-posta ve panel bildirimi gitti.';
            } elseif ($res['notified'] && !smtp_ready()) {
                $msg = 'Sipariş durumu güncellendi. Panel bildirimi gitti (SMTP kapalı, e-posta yok).';
            }
            flash_ok($msg);
        }
    }
    redirect('admin/siparis.php?id=' . $id);
}

if (post('id') && post('act') === 'kargo') {
    $st = db()->prepare(
        'SELECT o.*, u.email, u.role, u.name FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id=?'
    );
    $st->execute([$id]);
    $cur = $st->fetch();
    if (!$cur) {
        redirect('admin/siparisler');
    }
    try {
        $res = shop_order_set_shipping($cur, [
            'status' => post('status'),
            'carrier' => post('carrier'),
            'tracking' => post('tracking'),
            'tracking_url' => post('tracking_url'),
            'customer_note' => post('customer_note'),
        ], (int) $u['id'], isset($_POST['notify_customer']));
        if (!$res['changed']) {
            flash_ok('Kargo bilgisi aynı; değişiklik yok.');
        } elseif ($res['notified'] && $res['mailed']) {
            flash_ok('Kargo durumu kaydedildi. Müşteriye e-posta ve panel bildirimi gitti.');
        } elseif ($res['notified'] && !smtp_ready()) {
            flash_ok('Kargo durumu kaydedildi. Panel bildirimi gitti (SMTP kapalı, e-posta yok).');
        } elseif ($res['notified']) {
            flash_ok('Kargo durumu kaydedildi. Müşteriye panel bildirimi gönderildi.');
        } else {
            flash_ok('Kargo durumu kaydedildi.');
        }
    } catch (Throwable $e) {
        flash_error($e->getMessage());
    }
    redirect('admin/siparis.php?id=' . $id);
}

if (post('id') && post('act') === 'not') {
    $note = mb_substr(trim(post('admin_note')), 0, 2000);
    db()->prepare('UPDATE orders SET admin_note=? WHERE id=?')->execute([$note !== '' ? $note : null, $id]);
    flash_ok('Not kaydedildi.');
    redirect('admin/siparis.php?id=' . $id);
}

$st = db()->prepare(
    'SELECT o.*, u.name, u.email, u.phone, u.role
     FROM orders o JOIN users u ON u.id = o.user_id
     WHERE o.id = ?'
);
$st->execute([$id]);
$o = $st->fetch();
if (!$o) {
    redirect('admin/siparisler');
}

$it = db()->prepare(
    'SELECT i.*, b.title, b.slug, b.color, b.cover, b.author, b.is_digital
     FROM order_items i JOIN books b ON b.id = i.book_id
     WHERE i.order_id = ?'
);
$it->execute([$id]);
$items = $it->fetchAll();

$pay = null;
if (!empty($o['merchant_oid'])) {
    $ps = db()->prepare('SELECT * FROM payments WHERE merchant_oid = ? OR order_id = ? ORDER BY id DESC LIMIT 1');
    $ps->execute([(string) $o['merchant_oid'], $id]);
    $pay = $ps->fetch() ?: null;
}

$ship = function_exists('address_format') ? address_format($o) : '';
$next = siparis_next_status((string) $o['status']);
$events = shop_ship_events($id);
$statuses = shop_fulfillment_statuses((string) $o['ship_mode']);
$ok = flash_ok();
$err = flash_error();

panel_head('admin', 'siparisler', 'Sipariş #' . $id . ' | Admin', $u);
?>
<p class="mb-4">
  <a class="text-sm font-extrabold text-navy" href="<?= e(url('admin/siparisler')) ?>">← Siparişler</a>
</p>
<?php if ($ok): ?><p class="mb-4 font-bold text-green-700"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>

<section class="card p-6">
  <div class="flex flex-wrap items-start justify-between gap-4">
    <div>
      <p class="stat-label">Sipariş</p>
      <h2 class="font-display mt-1 text-3xl">#<?= $id ?></h2>
      <p class="mt-1 text-sm text-muted"><?= e(shop_date((string) $o['created_at'])) ?> · <?= e(shop_ship_label((string) $o['ship_mode'])) ?><?= !empty($o['coupon']) ? ' · kupon ' . e((string) $o['coupon']) : '' ?></p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <span class="<?= shop_status_class((string) $o['status']) ?>"><?= e(shop_order_status_label((string) $o['status'])) ?></span>
      <span class="<?= shop_status_class((string) ($o['pay_status'] ?: 'odendi')) ?>"><?= e(shop_pay_status_label((string) ($o['pay_status'] ?: 'odendi'))) ?></span>
      <a class="btn-outline text-sm" href="<?= e(url('magaza/fatura.php?id=' . $id)) ?>">Fatura özeti</a>
    </div>
  </div>
  <?php
    $steps = shop_fulfillment_statuses((string) $o['ship_mode']);
    $curI = array_search((string) $o['status'], $steps, true);
    ?>
  <ol class="ship-track mt-5">
    <?php foreach ($steps as $i => $stName): ?>
      <li class="ship-track-step<?= $curI !== false && $i < $curI ? ' is-done' : '' ?><?= $stName === (string) $o['status'] ? ' is-on' : '' ?>"><?= e($stName) ?></li>
    <?php endforeach; ?>
  </ol>
  <?php if ($next): ?>
    <form method="post" class="mt-4">
      <input type="hidden" name="id" value="<?= $id ?>">
      <button name="act" value="ilerlet" class="btn-primary text-sm"><?= e(siparis_next_label((string) $o['status'])) ?></button>
      <span class="ml-2 text-sm text-muted">Müşteriye otomatik bildirilir.</span>
    </form>
  <?php endif; ?>
</section>

<div class="mt-6 grid gap-4 lg:grid-cols-2">
  <section class="card p-5">
    <p class="stat-label">Müşteri</p>
    <h3 class="font-display mt-1 text-xl">
      <a class="text-navy" href="<?= e(kullanici_url((int) $o['user_id'])) ?>"><?= e((string) $o['name']) ?></a>
    </h3>
    <p class="mt-1 text-sm text-muted"><?= e((string) $o['email']) ?></p>
    <?php if (!empty($o['phone'])): ?>
      <p class="mt-1 text-sm"><?= e((string) $o['phone']) ?></p>
    <?php endif; ?>
    <p class="mt-3"><a class="text-sm font-extrabold text-navy" href="<?= e(kullanici_url((int) $o['user_id'])) ?>">Kullanıcı kartı →</a></p>
  </section>

  <section class="card p-5">
    <p class="stat-label">Teslimat adresi</p>
    <?php if ($ship === ''): ?>
      <p class="dash-empty mt-3">Bu siparişte adres kaydı yok (dijital teslim veya eski kayıt).</p>
    <?php else: ?>
      <p class="mt-2 font-extrabold"><?= e((string) ($o['ship_name'] ?: $o['name'])) ?></p>
      <?php if (!empty($o['ship_phone'])): ?><p class="text-sm"><?= e((string) $o['ship_phone']) ?></p><?php endif; ?>
      <?php if (!empty($o['ship_line'])): ?><p class="mt-1"><?= e((string) $o['ship_line']) ?></p><?php endif; ?>
      <p class="text-sm text-muted"><?= e(trim((string) ($o['ship_district'] ?? '') . ((string) ($o['ship_district'] ?? '') !== '' && (string) ($o['ship_city'] ?? '') !== '' ? ' / ' : '') . (string) ($o['ship_city'] ?? ''))) ?></p>
    <?php endif; ?>
  </section>
</div>

<section class="card mt-6 overflow-hidden">
  <div class="border-b px-5 py-3 font-extrabold">Kalemler</div>
  <?php if (!$items): ?>
    <p class="dash-empty px-5 py-8">Bu siparişte ürün satırı yok.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>Ürün</th><th>Adet</th><th>Birim</th><th>Toplam</th></tr></thead>
      <tbody>
        <?php foreach ($items as $line): ?>
          <tr>
            <td>
              <span class="inline-flex items-center gap-3">
                <span class="prod-swatch"><?= book_cover_html($line, '', 'thumb') ?></span>
                <span>
                  <a class="font-extrabold text-navy" href="<?= e(page_url('kitap', (string) $line['slug'])) ?>"><?= e((string) $line['title']) ?></a>
                  <span class="block text-xs text-muted"><?= e((string) $line['author']) ?><?= (int) $line['is_digital'] ? ' · Dijital' : '' ?></span>
                </span>
              </span>
            </td>
            <td><?= (int) $line['qty'] ?></td>
            <td><?= money((int) $line['price']) ?></td>
            <td class="font-extrabold"><?= money((int) $line['price'] * (int) $line['qty']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
  <div class="flex justify-end border-t px-5 py-4">
    <p class="font-display text-2xl"><?= money((int) $o['total']) ?></p>
  </div>
</section>

<?php if ($pay): ?>
<section class="card mt-6 p-5">
  <p class="stat-label">Ödeme</p>
  <p class="mt-2 font-extrabold"><?= e(payment_kind_label((string) $pay['kind'])) ?> · <?= money((int) $pay['total']) ?></p>
  <p class="mt-1 text-sm text-muted">İşlem no <?= e((string) $pay['merchant_oid']) ?> · <?= e(shop_pay_status_label((string) $pay['status'])) ?><?= !empty($pay['fail_reason']) ? ' · ' . e((string) $pay['fail_reason']) : '' ?></p>
</section>
<?php endif; ?>

<section class="card mt-6 p-5">
  <p class="stat-label">Kargo</p>
  <h3 class="font-display mt-1 text-xl">Durum ve takip</h3>
  <p class="mt-1 text-sm text-muted">Durumu kaydedince müşteri panel bildirimi alır; SMTP açıksa e-posta da gider.</p>
  <form method="post" class="mt-4 grid gap-3">
    <input type="hidden" name="id" value="<?= $id ?>">
    <label class="text-sm font-bold">Durum
      <select name="status" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
        <?php foreach ($statuses as $stName): ?>
          <option value="<?= e($stName) ?>" <?= (string) $o['status'] === $stName ? 'selected' : '' ?>><?= e($stName) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php if ((string) $o['ship_mode'] !== 'dijital'): ?>
      <div class="grid gap-3 sm:grid-cols-2">
        <label class="text-sm font-bold">Kargo firması
          <select name="carrier" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
            <option value="">Seçin</option>
            <?php foreach (shop_carriers() as $code => $lab): ?>
              <option value="<?= e($code) ?>" <?= (string) ($o['ship_carrier'] ?? '') === $code ? 'selected' : '' ?>><?= e($lab) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="text-sm font-bold">Takip numarası
          <input name="tracking" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= e((string) ($o['ship_tracking'] ?? '')) ?>" placeholder="Kargo barkod / takip no">
        </label>
      </div>
      <label class="text-sm font-bold">Takip bağlantısı (isteğe bağlı)
        <input name="tracking_url" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= e((string) ($o['ship_tracking_url'] ?? '')) ?>" placeholder="Boş bırakırsanız firmaya göre üretilir">
      </label>
      <?php if (!empty($o['ship_tracking_url'])): ?>
        <p class="text-sm"><a class="font-extrabold text-navy" href="<?= e((string) $o['ship_tracking_url']) ?>" target="_blank" rel="noreferrer">Mevcut takibi aç →</a></p>
      <?php endif; ?>
    <?php endif; ?>
    <label class="text-sm font-bold">Müşteriye not (isteğe bağlı)
      <input name="customer_note" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" maxlength="400" placeholder="Örn. Bugün kurye çıkacak, kapıda imza gerekir">
    </label>
    <label class="flex items-center gap-2 text-sm font-bold">
      <input type="checkbox" name="notify_customer" value="1" checked>
      Müşteriye bildir (panel<?= function_exists('smtp_ready') && smtp_ready() ? ' + e-posta' : '' ?>)
    </label>
    <?php if (!function_exists('smtp_ready') || !smtp_ready()): ?>
      <p class="text-xs text-muted">SMTP kapalı. E-posta gitmez; panel bildirimi yine düşer. Açmak için <a class="font-extrabold text-navy" href="<?= e(url('admin/smtp')) ?>">SMTP</a>.</p>
    <?php endif; ?>
    <button type="submit" name="act" value="kargo" class="btn-primary">Kaydet ve bildir</button>
  </form>
  <?php if ($events): ?>
    <p class="stat-label mt-6">Geçmiş</p>
    <ul class="mt-2 grid gap-2 text-sm">
      <?php foreach ($events as $ev): ?>
        <li class="rounded-xl border px-3 py-2">
          <span class="font-extrabold"><?= e(shop_order_status_label((string) $ev['status'])) ?></span>
          <span class="text-muted"> · <?= e(shop_date((string) $ev['created_at'])) ?></span>
          <?php if (!empty($ev['carrier'])): ?> · <?= e(shop_carrier_label((string) $ev['carrier'])) ?><?php endif; ?>
          <?php if (!empty($ev['tracking'])): ?> · <?= e((string) $ev['tracking']) ?><?php endif; ?>
          <?php if ((int) $ev['mail_sent'] === 1): ?> · e-posta gitti<?php endif; ?>
          <?php if (!empty($ev['message'])): ?><span class="block text-muted"><?= e((string) $ev['message']) ?></span><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section class="card mt-6 p-5">
  <p class="stat-label">Yönetici notu</p>
  <form method="post" class="mt-3">
    <input type="hidden" name="id" value="<?= $id ?>">
    <textarea name="admin_note" rows="4" maxlength="2000" class="w-full rounded-xl border px-3 py-2" placeholder="İç not; müşteriye gitmez."><?= e((string) ($o['admin_note'] ?? '')) ?></textarea>
    <button name="act" value="not" class="btn-outline mt-3 text-sm">Notu kaydet</button>
  </form>
</section>
<?php panel_foot();
