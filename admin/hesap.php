<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
$ok = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_own_account_post($u, $ok, $err);
}

panel_head('admin', 'hesap', 'Hesabım | Admin', $u);
?>
<section class="card profile-hero p-6">
  <?= user_avatar_html($u, 'lg', true) ?>
  <div>
    <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-muted">Yönetici hesabı</p>
    <h2 class="font-display mt-1 text-3xl"><?= e((string) $u['name']) ?></h2>
    <p class="mt-1 text-sm text-muted"><?= e((string) $u['email']) ?></p>
  </div>
</section>

<?php if ($ok): ?><p class="mt-5 font-bold text-green-700"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="mt-5 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>

<?php profile_contact_form($u); ?>
<?php profile_password_form($u); ?>
<?php profile_delete_form($u); ?>
<?php panel_foot();
