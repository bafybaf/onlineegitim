<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('musteri');

$ok = (string) ($_SESSION['flash_ok'] ?? '');
unset($_SESSION['flash_ok']);
$err = '';
$edit = null;
$editId = (int) ($_GET['duzenle'] ?? 0);
if ($editId > 0) {
    $edit = user_address((int) $u['id'], $editId);
    if (!$edit) {
        $err = 'Adres bulunamadı.';
        $editId = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = post('act');
    $id = (int) post('id');
    if ($act === 'sil') {
        if (address_delete((int) $u['id'], $id)) {
            $_SESSION['flash_ok'] = 'Adres silindi.';
        } else {
            $_SESSION['flash_err'] = 'Adres silinemedi.';
        }
        redirect('magaza/adresler.php');
    }
    if ($act === 'varsayilan') {
        if (address_set_default((int) $u['id'], $id)) {
            $_SESSION['flash_ok'] = 'Varsayılan adres güncellendi.';
        }
        redirect('magaza/adresler.php');
    }
    if ($act === 'kaydet') {
        [$err, $clean] = address_validate([
            'title' => post('title'),
            'name' => post('name'),
            'phone' => post('phone'),
            'city' => post('city'),
            'district' => post('district'),
            'line' => post('line'),
            'is_default' => post('is_default') === '1',
        ]);
        if ($err === '') {
            address_save((int) $u['id'], $clean, $id > 0 ? $id : null);
            $_SESSION['flash_ok'] = $id > 0 ? 'Adres güncellendi.' : 'Adres kaydedildi.';
            redirect('magaza/adresler.php');
        }
        $edit = $clean + ['id' => $id, 'is_default' => $clean['is_default'] ? 1 : 0];
        $editId = $id;
    }
}

$addresses = user_addresses((int) $u['id']);
$flashErr = flash_error();
if ($flashErr !== '') {
    $err = $err !== '' ? $err : $flashErr;
}

panel_head('musteri', 'adresler', 'Adresler | Mağaza', $u);
?>
<p class="mb-5 text-sm text-muted">Kargo için ev, iş veya başka teslimat adresleri kaydedin. Sepette birini seçersiniz; siparişte adres kopyası saklanır.</p>
<?php if ($ok): ?><p class="mb-4 font-bold text-green-700"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>

<div class="grid items-start gap-6 lg:grid-cols-[1fr_420px]">
  <section class="grid gap-4">
    <?php if (!$addresses): ?>
      <div class="card">
        <?php shop_empty('Kayıtlı adres yok', 'İlk teslimat adresinizi sağdaki formdan ekleyin.', '', ''); ?>
      </div>
    <?php else: ?>
      <?php foreach ($addresses as $a): ?>
        <article class="card p-5">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-muted"><?= e((string) $a['title']) ?></p>
              <h2 class="font-display mt-1 text-xl"><?= e((string) $a['name']) ?></h2>
              <p class="mt-1 text-sm text-muted"><?= e((string) $a['phone']) ?></p>
              <p class="mt-2 text-sm"><?= e((string) $a['line']) ?></p>
              <p class="text-sm text-muted"><?= e(trim((string) $a['district'] . ((string) $a['district'] !== '' ? ' / ' : '') . (string) $a['city'])) ?></p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <?php if ((int) $a['is_default'] === 1): ?>
                <span class="shop-pill shop-pill-ok">Varsayılan</span>
              <?php else: ?>
                <form method="post">
                  <input type="hidden" name="act" value="varsayilan">
                  <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                  <button class="text-sm font-extrabold text-navy">Varsayılan yap</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
          <div class="mt-4 flex flex-wrap gap-3">
            <a class="btn-outline h-10 px-4 text-sm" href="<?= e(url('magaza/adresler.php?duzenle=' . (int) $a['id'])) ?>">Düzenle</a>
            <form method="post" onsubmit="return confirm('Bu adresi silmek istiyor musunuz? Eski siparişlerdeki kopya kalır.');">
              <input type="hidden" name="act" value="sil">
              <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
              <button class="h-10 px-4 text-sm font-extrabold text-accent">Sil</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <aside class="card p-6 lg:sticky lg:top-24">
    <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-muted"><?= $editId > 0 ? 'Düzenle' : 'Yeni adres' ?></p>
    <h2 class="font-display mt-1 text-2xl"><?= $editId > 0 ? 'Adresi güncelle' : 'Adres ekle' ?></h2>
    <form method="post" class="mt-4 grid gap-3">
      <input type="hidden" name="act" value="kaydet">
      <input type="hidden" name="id" value="<?= (int) $editId ?>">
      <label class="text-sm font-bold">Başlık
        <input name="title" class="mt-1 w-full rounded-xl border px-3 py-2" placeholder="Ev / İş" value="<?= e((string) ($edit['title'] ?? 'Ev')) ?>">
      </label>
      <label class="text-sm font-bold">Ad soyad
        <input required name="name" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e((string) ($edit['name'] ?? $u['name'])) ?>">
      </label>
      <label class="text-sm font-bold">Telefon
        <input required name="phone" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e((string) ($edit['phone'] ?? ($u['phone'] ?? ''))) ?>">
      </label>
      <label class="text-sm font-bold">Şehir
        <input required name="city" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e((string) ($edit['city'] ?? ($u['city'] ?? ''))) ?>">
      </label>
      <label class="text-sm font-bold">İlçe <span class="font-normal text-muted">(isteğe bağlı)</span>
        <input name="district" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e((string) ($edit['district'] ?? '')) ?>">
      </label>
      <label class="text-sm font-bold">Açık adres
        <textarea required name="line" rows="3" class="mt-1 w-full rounded-xl border px-3 py-2"><?= e((string) ($edit['line'] ?? '')) ?></textarea>
      </label>
      <label class="flex items-center gap-2 text-sm font-bold">
        <input type="checkbox" name="is_default" value="1" <?= !empty($edit['is_default']) || !$addresses ? 'checked' : '' ?>>
        Varsayılan teslimat adresi
      </label>
      <button class="btn-primary"><?= $editId > 0 ? 'Güncelle' : 'Kaydet' ?></button>
      <?php if ($editId > 0): ?>
        <a class="text-center text-sm font-extrabold text-navy" href="<?= e(url('magaza/adresler.php')) ?>">Yeni adres formuna dön</a>
      <?php endif; ?>
    </form>
  </aside>
</div>
<?php panel_foot();
