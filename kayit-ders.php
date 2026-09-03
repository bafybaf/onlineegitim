<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$err = flash_error();
$packages = packages_active('ders');
$u = current_user();
$ilgiSel = trim((string) ($_GET['ilgi'] ?? $_POST['interest'] ?? ''));
if ($u && $u['role'] === 'ogrenci') {
    redirect(page_url('uyelik-ders') . ($ilgiSel !== '' ? '?ilgi=' . rawurlencode($ilgiSel) : ''));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pkg = package_by_id((int) post('package_id'));
    $name = post('name');
    $email = post('email');
    $phone = post('phone');
    $pass = post('password');
    $interest = post('interest');
    if (!$pkg || $pkg['kind'] !== 'ders' || !(int) $pkg['active']) {
        $err = 'Program / grup paketi seçin.';
    } elseif (security_honeypot_filled()) {
        $err = 'Kayıt alınamadı. Yeniden deneyin.';
    } elseif (security_register_blocked()) {
        $err = 'Çok fazla kayıt denemesi. Bir süre sonra yeniden deneyin.';
    } elseif (strlen($pass) < 8) {
        $err = 'Şifre en az 8 karakter olmalı.';
    } elseif ($u && !is_edu_role($u['role'])) {
        $err = 'Açık mağaza oturumunuz var. Ders için ayrı e-posta kullanın.';
    } elseif ($u && membership_is_staff($u)) {
        $err = 'Yönetici ve öğretmen üyelik ödemez.';
    } elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Ad ve geçerli e-posta girin.';
    } else {
        try {
            $st = db()->prepare('SELECT id, role FROM users WHERE email = ?');
            $st->execute([$email]);
            $exists = $st->fetch();
            if ($exists) {
                throw new RuntimeException($exists['role'] === 'musteri'
                    ? 'Bu e-posta mağaza hesabı. Ders için ayrı e-posta kullanın veya mağaza girişine gidin.'
                    : 'Bu e-posta zaten kayıtlı. Ders girişini kullanın.');
            }
            $nu = register_membership_user($name, $email, $pass, 'ogrenci', $phone);
            db()->prepare('INSERT INTO leads (name, phone, interest) VALUES (?,?,?)')->execute([$name, $phone, $interest]);
            $html = mail_wrap('Yeni öğrenci kaydı', '<p><b>Ad:</b> ' . e($name) . '<br><b>E-posta:</b> ' . e($email) . '<br><b>Telefon:</b> ' . e($phone) . '<br><b>İlgi:</b> ' . e($interest) . '<br><b>Paket:</b> ' . e($pkg['name']) . '</p>');
            notify_admin('Yeni kayıt · ' . $name, $html, $name . "\n" . $email . "\n" . $phone, $email);
            login_user($nu);
            $payment = membership_start_checkout($nu, $pkg);
            redirect(payment_checkout_url($payment));
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
$ilgiOpts = ['Tefsir', 'Hadis', 'Fıkıh', 'Akaid', 'Arapça', 'Kıraat', 'Hafızlık', 'Vaizlik'];
public_head('Ders kaydı | Online İlahiyat');
?>
<main class="mx-auto grid max-w-6xl gap-10 px-4 py-14 lg:grid-cols-2 lg:px-8">
  <div>
    <p class="badge">Canlı eğitim</p>
    <h1 class="font-display mt-4 text-4xl md:text-5xl">Canlı ders üyeliği</h1>
    <p class="mt-3 text-muted">Formu doldurup grubu seçin. Satın alım hemen öğrenci hesabınıza düşer; canlı sınıf, test ve ödev açılır.</p>
    <img src="<?= e(url('assets/img/sinif.jpg')) ?>" alt="" class="mt-8 h-72 w-full rounded-[22px] object-cover" />
  </div>
  <form method="post" class="card p-6">
    <?= csrf_field() ?>
    <?= security_honeypot_field() ?>
    <?php if ($err): ?><p class="mb-3 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>
    <label class="text-sm font-bold">Ad soyad<input required name="name" class="mt-1 w-full rounded-xl border px-3 py-2" autocomplete="name"></label>
    <label class="mt-3 block text-sm font-bold">Telefon<input required name="phone" class="mt-1 w-full rounded-xl border px-3 py-2" autocomplete="tel"></label>
    <label class="mt-3 block text-sm font-bold">E-posta<input type="email" required name="email" class="mt-1 w-full rounded-xl border px-3 py-2" autocomplete="email"></label>
    <label class="mt-3 block text-sm font-bold">Şifre<input type="password" required minlength="8" name="password" class="mt-1 w-full rounded-xl border px-3 py-2" placeholder="En az 8 karakter" autocomplete="new-password"></label>
    <label class="mt-3 block text-sm font-bold">İlgilendiğiniz alan
      <select name="interest" class="mt-1 w-full rounded-xl border px-3 py-2">
        <?php foreach ($ilgiOpts as $opt): ?>
          <option<?= $ilgiSel === $opt ? ' selected' : '' ?>><?= e($opt) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php if (!$packages): ?>
      <p class="mt-2 text-sm text-muted">Satışa açık paket henüz yok. Yönetim panelinden grup ve üyelik paketi ekleyin.</p>
    <?php else: ?>
    <div class="mt-2 grid gap-2">
      <?php foreach ($packages as $pkg): ?>
        <label class="flex cursor-pointer items-start gap-3 rounded-xl border px-3 py-3 <?= $sel === (int) $pkg['id'] ? 'border-navy' : '' ?>">
          <input type="radio" name="package_id" value="<?= (int) $pkg['id'] ?>" <?= $sel === (int) $pkg['id'] ? 'checked' : '' ?> class="mt-1">
          <span>
            <span class="block font-extrabold"><?= e($pkg['name']) ?></span>
            <span class="text-sm text-muted"><?= e(money_or_free((int) $pkg['price'])) ?> - <?= (int) $pkg['duration_days'] ?> gün<?= !empty($pkg['group_name']) ? ' - ' . e($pkg['group_name']) : (!empty($pkg['program_title']) ? ' · ' . e($pkg['program_title']) : '') ?> · <?= e(package_access_label($pkg)) ?></span>
          </span>
        </label>
      <?php endforeach; ?>
    </div>
    <p class="mt-3 text-xs text-muted">Program sayfasından kart çekilmez; paket seçince kayıt hesabınıza düşer.</p>
    <button id="submit-btn" class="btn-primary mt-5 w-full">Satın al</button>
    <script>
    (function(){
      var pkgs=<?= json_encode(array_map(function($p){return['id'=>(int)$p['id'],'price'=>(int)$p['price']];}, $packages)) ?>;
      var btn=document.getElementById('submit-btn');
      document.querySelectorAll('input[name="package_id"]').forEach(function(r){
        r.addEventListener('change',function(){
          var p=pkgs.find(function(x){return x.id==+r.value;});
          btn.textContent=(p&&p.price<1)?'Ücretsiz kayıt ol':'Satın al';
        });
        if(r.checked){var p=pkgs.find(function(x){return x.id==+r.value;});if(p&&p.price<1)btn.textContent='Ücretsiz kayıt ol';}
      });
    })();
    </script>
    <?php endif; ?>
    <?php google_button('ders', '', 'mt-4'); ?>
    <p class="mt-3 text-center text-sm text-muted">Hesabınız var mı? <a class="font-extrabold text-navy" href="<?= e(page_url('giris-ders')) ?>">Ders girişi</a></p>
  </form>
</main>
<?php public_foot();
