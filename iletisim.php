<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$ok = false;
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (security_honeypot_filled()) {
        $ok = true;
    } else {
        $name = post('name');
        $email = post('email');
        $message = post('message');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $message === '') {
            $err = 'Ad, geçerli e-posta ve mesaj girin.';
        } elseif (mb_strlen($name) > 120 || mb_strlen($message) > 4000) {
            $err = 'Mesaj çok uzun.';
        } elseif (security_contact_blocked()) {
            $err = 'Çok fazla istek. Bir süre sonra yeniden deneyin.';
        } else {
            db()->prepare('INSERT INTO contacts (name, email, message) VALUES (?,?,?)')
                ->execute([$name, $email, $message]);
            $html = mail_wrap('Yeni iletişim mesajı', '<p><b>Ad:</b> ' . e($name) . '<br><b>E-posta:</b> ' . e($email) . '</p><p>' . nl2br(e($message)) . '</p>');
            notify_admin('İletişim formu · ' . $name, $html, $name . "\n" . $email . "\n" . $message, $email);
            $ok = true;
        }
    }
}
public_head('İletişim | Online İlahiyat');
?>
<header class="bg-soft py-6">
  <div class="mx-auto max-w-7xl px-4 lg:px-8">
    <h1 class="font-display text-2xl md:text-3xl">İletişim</h1>
    <p class="mt-4 text-muted">Kayıt, kitap siparişi ve canlı ders teknik destek. 0850 303 40 14 · info@onlineilahiyat.com</p>
  </div>
</header>
<main class="mx-auto max-w-xl px-4 py-12">
  <?php if ($ok): ?><p class="mb-4 font-bold text-accent">Mesajınız iletildi.</p><?php endif; ?>
  <?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>
  <form method="post" class="card grid gap-3 p-6">
    <?= csrf_field() ?>
    <?= security_honeypot_field() ?>
    <input required name="name" class="rounded-xl border px-3 py-2" placeholder="Ad soyad" autocomplete="name">
    <input required type="email" name="email" class="rounded-xl border px-3 py-2" placeholder="E-posta" autocomplete="email">
    <textarea required name="message" rows="5" maxlength="4000" class="rounded-xl border px-3 py-2" placeholder="Mesajınız"></textarea>
    <button class="btn-primary">Gönder</button>
  </form>
</main>
<?php public_foot();