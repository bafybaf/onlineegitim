<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
$rows = users_admin_rows();
$q = trim((string) ($_GET['q'] ?? ''));
$roleF = (string) ($_GET['rol'] ?? '');
if ($q !== '' || ($roleF !== '' && isset(admin_user_roles()[$roleF]))) {
    $rows = array_values(array_filter($rows, static function (array $r) use ($q, $roleF): bool {
        if ($roleF !== '' && ($r['role'] ?? '') !== $roleF) {
            return false;
        }
        if ($q === '') {
            return true;
        }
        $hay = mb_strtolower(($r['name'] ?? '') . ' ' . ($r['email'] ?? '') . ' ' . ($r['phone'] ?? ''), 'UTF-8');
        return str_contains($hay, mb_strtolower($q, 'UTF-8'));
    }));
}
panel_head('admin', 'kullanicilar', 'Kullanıcılar | Admin', $u);
$roles = admin_user_roles();
?>
<div class="mb-4 flex flex-wrap items-start justify-between gap-3">
  <p class="text-sm text-muted">Tüm hesaplar. Satırın başındaki tutamacı sürükleyerek sırayı değiştirin. Ada tıklayınca kart açılır.</p>
  <a class="btn-primary text-sm" href="<?= e(kullanici_url(0)) ?>">Yeni kullanıcı</a>
</div>
<form method="get" class="mb-4 flex flex-wrap gap-2">
  <input name="q" value="<?= e($q) ?>" class="rounded-xl border px-3 py-2 text-sm" placeholder="Ad, e-posta, telefon">
  <select name="rol" class="rounded-xl border px-3 py-2 text-sm">
    <option value="">Tüm roller</option>
    <?php foreach ($roles as $val => $lab): ?>
      <option value="<?= e($val) ?>" <?= $roleF === $val ? 'selected' : '' ?>><?= e($lab) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn-outline text-sm">Filtrele</button>
</form>
<div class="card overflow-hidden">
  <table class="table">
    <thead>
      <tr>
        <th></th>
        <th>Kişi</th>
        <th>Telefon</th>
        <th>Rol</th>
        <th>Şehir</th>
        <th>Durum</th>
        <th>Canlı üyelik</th>
        <th></th>
      </tr>
    </thead>
    <tbody data-sort-table="users">
      <?php if (!$rows): ?>
        <tr><td colspan="8" class="px-4 py-6 text-sm text-muted">Kayıt yok.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $r):
          $mem = membership_from_admin_row($r);
          ?>
        <tr data-sort-id="<?= (int) $r['id'] ?>">
          <td class="sort-handle" title="Sürükleyerek sıralayın">⋮⋮</td>
          <td>
            <a class="avatar-row" href="<?= e(kullanici_url((int) $r['id'])) ?>">
              <?= user_avatar_html($r, 'sm') ?>
              <span>
                <span class="font-extrabold"><?= e((string) $r['name']) ?></span>
                <span class="block text-xs text-muted"><?= e((string) $r['email']) ?></span>
              </span>
            </a>
          </td>
          <td><?= e((string) ($r['phone'] ?: '—')) ?></td>
          <td><?= e(user_role_label((string) $r['role'])) ?></td>
          <td><?= e((string) ($r['city'] ?: '—')) ?></td>
          <td><?= e(user_status_label((string) $r['status'])) ?></td>
          <td><span class="<?= e(membership_kind_class((string) $mem['kind'])) ?>"><?= e((string) $mem['label']) ?></span></td>
          <td><a class="text-sm font-extrabold text-navy" href="<?= e(kullanici_url((int) $r['id'])) ?>">Düzenle</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php panel_foot();
