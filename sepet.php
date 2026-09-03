<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$items = cart();
$progItems = cart_programs();
$rows = [];
$progRows = [];
$sub = $list = 0;
if ($items) {
    $ids = array_map('intval', array_keys($items));
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare("SELECT * FROM books WHERE id IN ($in)");
    $st->execute($ids);
    foreach ($st as $b) {
        $qty = (int) $items[$b['id']];
        $b['qty'] = $qty;
        $rows[] = $b;
        $sub += $b['price'] * $qty;
        $list += $b['price_old'] * $qty;
    }
}
if ($progItems) {
    $pids = array_map('intval', array_keys($progItems));
    $pin = implode(',', array_fill(0, count($pids), '?'));
    $pst = db()->prepare("SELECT * FROM programs WHERE id IN ($pin)");
    $pst->execute($pids);
    foreach ($pst as $p) {
        $p['qty'] = 1;
        $p['price'] = (int) ($p['price_now'] ?? 0);
        $p['price_old'] = (int) ($p['price_old'] ?? 0);
        $p['is_digital'] = 1;
        $progRows[] = $p;
        $sub += $p['price'];
        $list += max($p['price_old'], $p['price']);
    }
}
$save = max(0, $list - $sub);
$allDigital = ($rows || $progRows) && count(array_filter($rows, static fn(array $b): bool => empty($b['is_digital']))) === 0;
$ship = ($sub >= 500 || $allDigital) ? 0 : ($rows ? 49 : 0);
$campLines = array_merge($rows, $progRows);
$autoCamp = $campLines ? campaign_resolve_for_cart($campLines, $sub, $ship, '') : ['discount' => 0, 'ship' => $ship, 'campaign' => null, 'code' => null, 'label' => ''];
$couponHint = campaign_first_code_hint();
$campDisc = (int) ($autoCamp['discount'] ?? 0);
$ship = (int) ($autoCamp['ship'] ?? $ship);
$total = max(0, $sub - $campDisc) + $ship;
$done = isset($_GET['ok']);
$shopUser = current_user();
$shopReady = $shopUser && is_shop_role($shopUser['role']) && ($shopUser['status'] ?? '') === 'aktif';
$addresses = $shopReady ? user_addresses((int) $shopUser['id']) : [];
public_head('Sepet | Online İlahiyat');
?>
<header class="border-b border-[#e5e5e7] bg-white">
  <div class="mx-auto max-w-7xl px-4 py-10 lg:px-8">
    <p class="text-xs font-extrabold uppercase tracking-[0.22em] text-accent">Mağaza</p>
    <h1 class="font-display mt-2 text-4xl md:text-5xl">Sepetiniz</h1>
  </div>
</header>
<main class="mx-auto max-w-7xl px-4 py-10 lg:px-8">
<?php if ($done): ?>
  <div class="card p-8"><h2 class="font-display text-3xl">Sipariş alındı</h2><p class="mt-2 text-muted">Kitaplar Kitaplarım’da, eğitimler Eğitimlerim’de görünür. Sınıf yerleştirmenizi yönetim yapar.</p><a class="btn-primary mt-4" href="<?= e(url('magaza/index.php')) ?>">Hesabıma git</a></div>
<?php elseif (!$rows && !$progRows): ?>
  <div class="card overflow-hidden max-w-xl">
    <img src="<?= e(url('assets/img/kitaplik.jpg')) ?>" alt="" class="h-56 w-full object-cover">
    <div class="p-8">
      <h2 class="font-display text-3xl">Sepetiniz boş</h2>
      <div class="mt-6 flex flex-wrap gap-3">
        <a href="<?= e(url('programlar.php')) ?>" class="btn-primary">Eğitimlere bak</a>
        <a href="<?= e(url('kitaplar.php')) ?>" class="btn-outline">Kitaplar</a>
      </div>
    </div>
  </div>
<?php else: ?>
  <div class="grid items-start gap-8 lg:grid-cols-[1fr_380px]">
    <section class="grid gap-4">
      <?php foreach ($rows as $i): ?>
      <article class="card flex flex-col gap-4 p-4 sm:flex-row sm:items-center">
        <a href="<?= e(page_url('kitap', (string) $i['slug'])) ?>" class="book-cover-wrap h-28 w-full shrink-0 rounded-xl sm:w-28"><?= book_cover_html($i) ?></a>
        <div class="flex-1">
          <p class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-navy"><?= e(book_category_name($i)) ?></p>
          <p class="font-extrabold"><?= e($i['title']) ?></p>
          <p class="text-sm text-muted"><?= e($i['author']) ?></p>
          <p class="mt-2"><span class="price-old mr-2"><?= money($i['price_old']) ?></span><span class="price-now"><?= money($i['price']) ?></span></p>
        </div>
        <div class="flex items-center gap-3">
          <button class="qty-btn grid h-10 w-10 rounded-xl border" data-id="<?= (int) $i['id'] ?>" data-d="-1">−</button>
          <span class="w-6 text-center font-extrabold"><?= (int) $i['qty'] ?></span>
          <button class="qty-btn grid h-10 w-10 rounded-xl border" data-id="<?= (int) $i['id'] ?>" data-d="1">+</button>
          <p class="font-display text-xl"><?= money($i['price'] * $i['qty']) ?></p>
        </div>
      </article>
      <?php endforeach; ?>
      <?php foreach ($progRows as $i): ?>
      <article class="card flex flex-col gap-4 p-4 sm:flex-row sm:items-center">
        <a href="<?= e(page_url('program', (string) $i['slug'])) ?>" class="book-cover-wrap h-28 w-full shrink-0 overflow-hidden rounded-xl sm:w-28"><?= program_image_html($i, 'h-full w-full object-cover', 'thumb') ?></a>
        <div class="flex-1">
          <p class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-navy">Eğitim</p>
          <p class="font-extrabold"><?= e($i['title']) ?></p>
          <p class="text-sm text-muted"><?= e((string) $i['level']) ?><?= !empty($i['hours']) ? ' · ' . e((string) $i['hours']) : '' ?></p>
          <p class="mt-2"><?= program_price_html($i) ?></p>
        </div>
        <div class="flex items-center gap-3">
          <button class="qty-prog grid h-10 rounded-xl border px-3 text-sm font-extrabold" data-id="<?= (int) $i['id'] ?>">Kaldır</button>
          <p class="font-display text-xl"><?= money((int) $i['price']) ?></p>
        </div>
      </article>
      <?php endforeach; ?>
      <?php if ($shopReady && !$allDigital): ?>
      <section class="card p-5" id="addr-box">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-muted">Teslimat adresi</p>
            <h2 class="font-display mt-1 text-2xl">Nereye gönderelim?</h2>
          </div>
          <a class="text-sm font-extrabold text-navy" href="<?= e(url('magaza/adresler.php')) ?>">Adreslerim</a>
        </div>
        <div class="mt-4 grid gap-3">
          <?php foreach ($addresses as $a): ?>
            <label class="flex cursor-pointer gap-3 rounded-xl border border-[#e5e5e7] p-3 has-[:checked]:border-navy">
              <input type="radio" name="addr_pick" value="<?= (int) $a['id'] ?>" class="mt-1" <?= (int) $a['is_default'] === 1 ? 'checked' : '' ?>>
              <div class="text-sm">
                <p class="font-extrabold"><?= e((string) $a['title']) ?><?= (int) $a['is_default'] === 1 ? ' · varsayılan' : '' ?></p>
                <p><?= e((string) $a['name']) ?> · <?= e((string) $a['phone']) ?></p>
                <p class="text-muted"><?= e((string) $a['line']) ?><?= !empty($a['district']) ? ', ' . e((string) $a['district']) : '' ?>, <?= e((string) $a['city']) ?></p>
              </div>
            </label>
          <?php endforeach; ?>
          <label class="flex cursor-pointer gap-3 rounded-xl border border-[#e5e5e7] p-3 has-[:checked]:border-navy">
            <input type="radio" name="addr_pick" value="new" class="mt-1" <?= !$addresses ? 'checked' : '' ?>>
            <div>
              <p class="font-extrabold">Yeni adres</p>
              <p class="text-sm text-muted">Kaydedilir ve bu siparişte kullanılır.</p>
            </div>
          </label>
          <div id="new-addr" class="grid gap-3 <?= $addresses ? 'hidden' : '' ?>">
            <label class="text-sm font-bold">Başlık
              <input id="addr_title" class="mt-1 w-full rounded-xl border px-3 py-2" value="Ev" placeholder="Ev / İş">
            </label>
            <label class="text-sm font-bold">Ad soyad
              <input id="addr_name" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e((string) $shopUser['name']) ?>">
            </label>
            <label class="text-sm font-bold">Telefon
              <input id="addr_phone" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e((string) ($shopUser['phone'] ?? '')) ?>">
            </label>
            <div class="grid gap-3 sm:grid-cols-2">
              <label class="text-sm font-bold">Şehir
                <input id="addr_city" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e((string) ($shopUser['city'] ?? '')) ?>">
              </label>
              <label class="text-sm font-bold">İlçe
                <input id="addr_district" class="mt-1 w-full rounded-xl border px-3 py-2" placeholder="İsteğe bağlı">
              </label>
            </div>
            <label class="text-sm font-bold">Açık adres
              <textarea id="addr_line" rows="3" class="mt-1 w-full rounded-xl border px-3 py-2" placeholder="Mahalle, sokak, bina no, daire"></textarea>
            </label>
          </div>
        </div>
      </section>
      <?php endif; ?>
    </section>
    <aside class="card overflow-hidden lg:sticky lg:top-24">
      <div class="bg-navy3 px-6 py-5 text-white">
        <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-white/60">Sipariş özeti</p>
        <p class="font-display mt-1 text-3xl"><?= money($total) ?></p>
      </div>
      <div class="grid gap-3 p-6 text-sm">
        <p class="flex justify-between"><span class="text-muted">Ara toplam</span><b><?= money($sub) ?></b></p>
        <p class="flex justify-between text-accent"><span>İndirim</span><b>−<?= money($save) ?></b></p>
        <?php if (!empty($autoCamp['campaign']) && (int) $autoCamp['discount'] > 0): ?>
          <p class="flex justify-between text-accent"><span><?= e((string) $autoCamp['label']) ?></span><b>−<?= money((int) $autoCamp['discount']) ?></b></p>
        <?php endif; ?>
        <p class="flex justify-between"><span class="text-muted">Kargo</span><b><?= $ship ? money($ship) : 'Ücretsiz' ?></b></p>
        <p class="flex justify-between border-t pt-3 text-base"><span class="font-extrabold">Toplam</span><b class="font-display text-2xl"><?= money($total) ?></b></p>
        <input id="coupon" class="rounded-xl border px-3 py-2" placeholder="Kupon (<?= e($couponHint) ?>)">
        <?php if ($couponHint !== ''): ?>
          <p class="text-xs text-muted">Kupon kodu ödeme anında uygulanır. Kod girmezseniz sepetteki otomatik kampanya kalır.</p>
        <?php endif; ?>
        <?php if (!$shopUser): ?>
          <a href="<?= e(url('giris-magaza.php?next=sepet')) ?>" class="btn-primary text-center">Giriş yapıp satın al · <?= money($total) ?></a>
        <?php elseif (!$shopReady): ?>
          <p class="rounded-xl bg-soft px-3 py-2 text-sm font-bold">Sepet mağaza hesabıyla ödenir. Eğitim oturumunuz açık; <a class="text-navy" href="<?= e(url('giris-magaza.php?next=sepet')) ?>">mağaza girişini</a> kullanın.</p>
        <?php else: ?>
          <button id="checkout" class="btn-primary">Satın al</button>
        <?php endif; ?>
      </div>
    </aside>
  </div>
  <script>
    document.querySelectorAll('.qty-btn').forEach((b) => b.addEventListener('click', async () => {
      const span = b.parentElement.querySelector('span');
      const qty = Math.max(0, Number(span.textContent) + Number(b.dataset.d));
      await OICart.set(b.dataset.id, qty); location.reload();
    }));
    document.querySelectorAll('.qty-prog').forEach((b) => b.addEventListener('click', async () => {
      await OICart.setProgram(b.dataset.id, 0); location.reload();
    }));
    const newAddr = document.getElementById('new-addr');
    const syncNewAddr = () => {
      const pick = document.querySelector('input[name="addr_pick"]:checked');
      if (newAddr) newAddr.classList.toggle('hidden', !!(pick && pick.value !== 'new'));
    };
    document.querySelectorAll('input[name="addr_pick"]').forEach((el) => el.addEventListener('change', syncNewAddr));
    syncNewAddr();
    document.getElementById('checkout')?.addEventListener('click', async () => {
      const pick = document.querySelector('input[name="addr_pick"]:checked');
      const body = new URLSearchParams({ action: 'checkout', coupon: document.getElementById('coupon').value });
      if (!pick || pick.value === 'new') {
        body.set('address_id', '0');
        body.set('addr_title', document.getElementById('addr_title')?.value || '');
        body.set('addr_name', document.getElementById('addr_name')?.value || '');
        body.set('addr_phone', document.getElementById('addr_phone')?.value || '');
        body.set('addr_city', document.getElementById('addr_city')?.value || '');
        body.set('addr_district', document.getElementById('addr_district')?.value || '');
        body.set('addr_line', document.getElementById('addr_line')?.value || '');
      } else {
        body.set('address_id', pick.value);
      }
      const r = await fetch(OI_BASE + 'api/cart.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
      const j = await r.json();
      if (j.ok && j.pay_url) location.href = j.pay_url;
      else if (j.error === 'login') location.href = <?= json_encode(url('giris-magaza.php?next=sepet')) ?>;
      else if (j.error === 'shop_account') alert('Satın alım için mağaza girişi gerekir. Ders hesabı ile sipariş alınmaz.');
      else if (j.error === 'stock') alert('Stok yetersiz. Adedi düşürün.');
      else if (j.error === 'address') alert(j.message || 'Teslimat için telefon, şehir ve açık adres gerekli.');
      else alert('Satın alım tamamlanamadı.');
    });
  </script>
<?php endif; ?>
</main>
<?php public_foot();