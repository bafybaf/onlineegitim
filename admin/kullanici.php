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
    'role' => 'ogrenci',
    'phone' => '',
    'city' => '',
    'bio' => '',
    'status' => 'aktif',
    'avatar' => '',
    'created_at' => '',
];
if (!$isNew) {
    if ($id < 1) {
        redirect('admin/kullanicilar.php');
    }
    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$id]);
    $found = $st->fetch();
    if (!$found) {
        redirect('admin/kullanicilar.php');
    }
    $person = $found;
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = post('act');
    try {
        if ($act === 'profil' || $act === 'hesap') {
            if ($act === 'hesap' && !$isNew) {
                admin_save_user_status($id, post('status'));
                flash_ok('Hesap durumu güncellendi.');
            } else {
                $id = admin_save_user($isNew ? 0 : $id, [
                    'name' => post('name'),
                    'email' => post('email'),
                    'phone' => post('phone'),
                    'city' => post('city'),
                    'bio' => post('bio'),
                    'role' => post('role'),
                    'status' => post('status'),
                    'password' => post('password'),
                ], (int) $admin['id']);
                flash_ok($isNew ? 'Kullanıcı eklendi.' : 'Kullanıcı güncellendi.');
                $isNew = false;
            }
        } elseif ($isNew) {
            throw new RuntimeException('Önce kullanıcıyı kaydedin.');
        } elseif ($act === 'paket' && $person['role'] === 'ogrenci') {
            admin_save_enrollment((int) post('enroll_id'), $id, [
                'status' => post('enroll_status'),
                'term' => post('term'),
                'expires_at' => post('expires_at'),
                'package_id' => post('package_id'),
            ]);
            notify_user($id, 'Üyeliğiniz güncellendi', 'Yönetici paket sürenizi veya durumunuzu değiştirdi.', url('ogrenci/hesap'));
            flash_ok('Paket kaydı güncellendi.');
        } elseif ($act === 'paket_ekle' && $person['role'] === 'ogrenci') {
            admin_add_enrollment($id, [
                'group_id' => post('group_id'),
                'status' => post('enroll_status'),
                'term' => post('term'),
                'expires_at' => post('expires_at'),
                'package_id' => post('package_id'),
            ]);
            notify_user($id, 'Yeni paket tanımlandı', 'Yönetici sizi bir gruba ekledi.', url('ogrenci/derslerim'));
            flash_ok('Grup / paket eklendi.');
        } elseif ($act === 'paket_sil' && $person['role'] === 'ogrenci') {
            $eid = (int) post('enroll_id');
            db()->prepare('UPDATE enrollments SET status = ? WHERE id = ? AND student_id = ?')
                ->execute(['silindi', $eid, $id]);
            flash_ok('Kayıt pasife alındı (silindi olarak işaretlendi).');
        } else {
            throw new RuntimeException('Bu işlem bu hesap için geçerli değil.');
        }
        redirect(kullanici_url($id));
    } catch (Throwable $e) {
        $err = $e->getMessage();
        if (post('act') === 'profil') {
            $person['name'] = post('name');
            $person['email'] = post('email');
            $person['phone'] = post('phone');
            $person['city'] = post('city');
            $person['bio'] = post('bio');
            $person['role'] = post('role') ?: $person['role'];
            $person['status'] = post('status') ?: $person['status'];
        }
    }
}

$enrolls = $isNew ? [] : user_enrollments($id);
$mem = live_membership_state($person, $enrolls);
$pays = $isNew ? [] : user_all_payments($id);
$orders = $isNew ? [] : user_book_orders($id);
$books = $isNew ? [] : user_shop_books($id);
$groups = db()->query(
    'SELECT g.id, g.name, p.title program_title FROM class_groups g JOIN programs p ON p.id=g.program_id ORDER BY p.title, g.name'
)->fetchAll();
$packages = packages_all();
$ok = flash_ok();

panel_head('admin', 'kullanicilar', ($isNew ? 'Yeni kullanıcı' : 'Kullanıcı') . ' | Admin', $admin);
$roles = admin_user_roles();
?>
<p class="mb-4"><a class="text-sm font-extrabold text-navy" href="<?= e(url('admin/kullanicilar')) ?>">← Kullanıcılar</a></p>
<?php if ($ok): ?><p class="mb-4 font-bold text-green-700"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>

<?php if (!$isNew): ?>
<section class="card profile-hero p-6">
  <?= user_avatar_html($person, 'lg') ?>
  <div>
    <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-muted"><?= e(user_role_label((string) $person['role'])) ?></p>
    <h2 class="font-display mt-1 text-3xl"><?= e((string) $person['name']) ?></h2>
    <p class="mt-1 text-sm text-muted"><?= e((string) $person['email']) ?></p>
    <p class="mt-3 flex flex-wrap gap-2">
      <span class="shop-pill"><?= e(user_status_label((string) $person['status'])) ?></span>
      <span class="shop-pill <?= e(membership_kind_class((string) $mem['kind'])) ?>"><?= e((string) $mem['short']) ?></span>
    </p>
  </div>
</section>

<div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
  <div class="stat">
    <p class="stat-label">Telefon</p>
    <p class="stat-value text-xl"><?= e((string) ($person['phone'] ?: '—')) ?></p>
  </div>
  <div class="stat">
    <p class="stat-label">Şehir</p>
    <p class="stat-value text-xl"><?= e((string) ($person['city'] ?: '—')) ?></p>
  </div>
  <div class="stat">
    <p class="stat-label">Canlı üyelik</p>
    <p class="stat-value text-xl <?= e(membership_kind_class((string) $mem['kind'])) ?>"><?= e((string) $mem['label']) ?></p>
    <p class="stat-hint"><?= $mem['expires'] ? 'Bitiş: ' . e(profile_dt($mem['expires'])) : 'Bitiş tarihi yok' ?></p>
  </div>
  <div class="stat">
    <p class="stat-label">Kayıt</p>
    <p class="stat-value text-xl"><?= e(profile_dt((string) $person['created_at'])) ?></p>
  </div>
</div>
<?php endif; ?>

<section class="card mt-6 p-5">
  <p class="stat-label">Hesap</p>
  <h3 class="font-display mt-1 text-xl"><?= $isNew ? 'Yeni kullanıcı' : 'Bilgileri düzenle' ?></h3>
  <p class="mt-1 text-sm text-muted">
    <?= $isNew
        ? 'Öğrenci, öğretmen, mağaza veya yönetici hesabı açın. Şifre en az 8 karakter.'
        : 'Ad, iletişim, rol ve durum. Şifreyi boş bırakırsanız değişmez.' ?>
  </p>
  <form method="post" enctype="multipart/form-data" class="mt-4 grid gap-3 md:grid-cols-2">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="profil">
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
    <label class="text-sm font-bold">Rol
      <select name="role" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
        <?php foreach ($roles as $val => $lab): ?>
          <option value="<?= e($val) ?>" <?= ($person['role'] ?? '') === $val ? 'selected' : '' ?>><?= e($lab) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="text-sm font-bold">Durum
      <select name="status" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
        <?php foreach (['aktif' => 'Aktif', 'pasif' => 'Pasif', 'bekliyor' => 'Bekliyor'] as $val => $lab): ?>
          <option value="<?= e($val) ?>" <?= ($person['status'] ?? '') === $val ? 'selected' : '' ?>><?= e($lab) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="text-sm font-bold md:col-span-2">Şifre
      <input type="password" name="password" <?= $isNew ? 'required minlength="8"' : 'minlength="8"' ?> class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" placeholder="<?= $isNew ? 'En az 8 karakter' : 'Boş bırakırsanız değişmez' ?>" autocomplete="new-password">
    </label>
    <label class="text-sm font-bold md:col-span-2">Fotoğraf
      <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
    </label>
    <label class="text-sm font-bold md:col-span-2">Not / özgeçmiş
      <textarea name="bio" rows="3" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal"><?= e((string) ($person['bio'] ?? '')) ?></textarea>
    </label>
    <div class="md:col-span-2">
      <button class="btn-primary"><?= $isNew ? 'Kullanıcıyı oluştur' : 'Bilgileri kaydet' ?></button>
    </div>
  </form>
</section>

<?php if (!$isNew && $person['role'] === 'ogrenci'): ?>
<section class="card mt-6 overflow-hidden">
  <div class="px-5 py-4">
    <p class="stat-label">Paket müdahalesi</p>
    <h3 class="font-display mt-1 text-xl">Grup kayıtları · süreli / süresiz</h3>
    <p class="mt-1 text-sm text-muted">Süresiz kayıtta bitiş olmaz. Pasif kayıt canlı odaya ve ders paneline kapanır; satır silinmez.</p>
  </div>
  <?php if (!$enrolls): ?>
    <p class="dash-empty px-5 pb-2">Bu hesapta ders kaydı yok. Aşağıdan ekleyin.</p>
  <?php else: ?>
    <div class="grid gap-4 px-5 pb-5">
      <?php foreach ($enrolls as $en):
          $em = enrollment_admin_state($en);
          $unlimited = empty($en['expires_at']);
          $expDate = !empty($en['expires_at']) ? date('Y-m-d', strtotime((string) $en['expires_at'])) : date('Y-m-d', strtotime('+30 days'));
          $stNow = (string) ($en['status'] ?? 'aktif');
          ?>
        <form method="post" class="rounded-2xl border border-[#e5e5e7] p-4">
          <input type="hidden" name="act" value="paket">
          <input type="hidden" name="enroll_id" value="<?= (int) $en['id'] ?>">
          <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
              <p class="font-extrabold"><?= e((string) ($en['group_name'] ?? 'Grup')) ?></p>
              <p class="text-sm text-muted"><?= e((string) ($en['program_title'] ?? '')) ?> · <?= e((string) ($en['package_name'] ?? 'Paket yok')) ?></p>
            </div>
            <span class="<?= e(membership_kind_class((string) $em['kind'])) ?>"><?= e((string) $em['label']) ?></span>
          </div>
          <div class="mt-3 grid gap-3 md:grid-cols-2 lg:grid-cols-4">
            <label class="text-xs font-bold">Durum
              <select name="enroll_status" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
                <option value="aktif" <?= $stNow === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                <option value="pasif" <?= in_array($stNow, ['pasif', 'silindi', 'suresi_doldu'], true) ? 'selected' : '' ?>>Pasif</option>
              </select>
            </label>
            <label class="text-xs font-bold">Süre
              <select name="term" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
                <option value="sureli" <?= $unlimited ? '' : 'selected' ?>>Süreli</option>
                <option value="suresiz" <?= $unlimited ? 'selected' : '' ?>>Süresiz</option>
              </select>
            </label>
            <label class="text-xs font-bold">Bitiş (süreliyse)
              <input type="date" name="expires_at" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= e($expDate) ?>">
            </label>
            <label class="text-xs font-bold">Paket
              <select name="package_id" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
                <option value="0">Paket yok</option>
                <?php foreach ($packages as $pkg): ?>
                  <option value="<?= (int) $pkg['id'] ?>" <?= (int) ($en['package_id'] ?? 0) === (int) $pkg['id'] ? 'selected' : '' ?>><?= e((string) $pkg['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <div class="mt-3 flex flex-wrap gap-2">
            <button class="btn-primary text-sm">Bu kaydı güncelle</button>
            <button class="btn-outline text-sm" name="act" value="paket_sil" onclick="return confirm('Kayıt silindi olarak işaretlensin mi?')">Kaydı kapat</button>
          </div>
        </form>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="post" class="border-t px-5 py-5">
    <input type="hidden" name="act" value="paket_ekle">
    <h4 class="font-extrabold">Yeni grup / paket ekle</h4>
    <div class="mt-3 grid gap-3 md:grid-cols-2 lg:grid-cols-5">
      <label class="text-xs font-bold lg:col-span-2">Grup
        <select name="group_id" required class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
          <option value="">Seçin</option>
          <?php foreach ($groups as $g): ?>
            <option value="<?= (int) $g['id'] ?>"><?= e($g['program_title'] . ' · ' . $g['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="text-xs font-bold">Paket
        <select name="package_id" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
          <option value="0">Paket yok</option>
          <?php foreach ($packages as $pkg): ?>
            <option value="<?= (int) $pkg['id'] ?>"><?= e((string) $pkg['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="text-xs font-bold">Durum
        <select name="enroll_status" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
          <option value="aktif">Aktif</option>
          <option value="pasif">Pasif</option>
        </select>
      </label>
      <label class="text-xs font-bold">Süre
        <select name="term" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
          <option value="sureli">Süreli</option>
          <option value="suresiz">Süresiz</option>
        </select>
      </label>
      <label class="text-xs font-bold">Bitiş
        <input type="date" name="expires_at" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= e(date('Y-m-d', strtotime('+30 days'))) ?>">
      </label>
    </div>
    <button class="btn-primary mt-4">Ekle</button>
  </form>
</section>
<?php endif; ?>

<?php if (!$isNew): ?>
<section class="card mt-6 overflow-hidden">
  <div class="px-5 py-4">
    <p class="stat-label">Satın alınanlar</p>
    <h3 class="font-display mt-1 text-xl">Ödemeler</h3>
  </div>
  <?php if (!$pays): ?>
    <p class="dash-empty px-5 pb-5">Ödeme kaydı yok.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>Kalem</th><th>Tür</th><th>Tutar</th><th>Durum</th><th>Tarih</th></tr></thead>
      <tbody>
        <?php foreach ($pays as $p): ?>
          <tr>
            <td class="font-extrabold"><?= e(payment_title($p)) ?></td>
            <td><?= e(payment_kind_label((string) $p['kind'])) ?></td>
            <td><?= money((int) $p['total']) ?></td>
            <td><?= e(pay_status_word((string) $p['status'])) ?></td>
            <td><?= e(profile_dt((string) ($p['paid_at'] ?: $p['created_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<section class="card mt-6 overflow-hidden">
  <div class="px-5 py-4">
    <p class="stat-label">Kitap siparişleri</p>
    <h3 class="font-display mt-1 text-xl">Siparişler</h3>
  </div>
  <?php if (!$orders): ?>
    <p class="dash-empty px-5 pb-5">Kitap siparişi yok.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>Kitap</th><th>Tutar</th><th>Durum</th><th>Ödeme</th><th>Tarih</th></tr></thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td class="font-extrabold"><a class="text-navy" href="<?= e(siparis_url((int) $o['id'])) ?>"><?= e((string) $o['title']) ?></a></td>
            <td><?= money((int) $o['total']) ?></td>
            <td><?= e(function_exists('shop_order_status_label') ? shop_order_status_label((string) $o['status']) : (string) $o['status']) ?></td>
            <td><?= e(pay_status_word((string) ($o['pay_status'] ?? 'odendi'))) ?></td>
            <td><?= e(profile_dt((string) $o['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<section class="card mt-6 overflow-hidden">
  <div class="px-5 py-4">
    <p class="stat-label">Mağaza kitapları</p>
    <h3 class="font-display mt-1 text-xl">Kitaplık</h3>
  </div>
  <?php if (!$books): ?>
    <p class="dash-empty px-5 pb-5">Kitaplık boş.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>Kitap</th><th>Tür</th><th>Durum</th></tr></thead>
      <tbody>
        <?php foreach ($books as $b): ?>
          <tr>
            <td><?= e((string) $b['title']) ?></td>
            <td><?= e((string) ($b['kind'] ?? '—')) ?></td>
            <td><?= e((string) ($b['status'] ?? '—')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>
<?php endif; ?>
<?php panel_foot();
