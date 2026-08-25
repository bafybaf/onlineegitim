<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
$rows = packages_all();
$ok = flash_ok();
$err = flash_error();
panel_head('admin', 'uyelikler', 'Üyelik paketleri | Admin', $u);
?>
<div class="mb-4 flex flex-wrap items-start justify-between gap-3">
  <p class="text-sm text-muted">Ders kaydında görünen paketler. Yeni paket ekleyin veya düzenleyin; pasif paket satışa kapanır.</p>
  <a class="btn-primary text-sm" href="<?= e(paket_admin_url(0)) ?>">Yeni paket</a>
</div>
<?php if ($ok): ?><p class="mb-4 font-bold text-green-700"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>

<?php if (!$rows): ?>
  <div class="card"><p class="dash-empty px-5 py-10">Ders paketi yok. <a class="font-extrabold text-navy" href="<?= e(paket_admin_url(0)) ?>">İlk paketi ekleyin</a>.</p></div>
<?php else: ?>
<div class="card overflow-hidden">
  <table class="table">
    <thead>
      <tr>
        <th></th>
        <th>Paket</th>
        <th>Grup / program</th>
        <th>Süre</th>
        <th>Fiyat</th>
        <th>Erişim</th>
        <th>Durum</th>
        <th></th>
      </tr>
    </thead>
    <tbody data-sort-table="packages">
      <?php foreach ($rows as $r): ?>
        <tr data-sort-id="<?= (int) $r['id'] ?>">
          <td class="sort-handle">⋮⋮</td>
          <td>
            <a class="font-extrabold text-navy" href="<?= e(paket_admin_url((int) $r['id'])) ?>"><?= e((string) $r['name']) ?></a>
          </td>
          <td class="text-sm text-muted">
            <?= e((string) ($r['group_name'] ?: 'Grup yok')) ?>
            <?php if (!empty($r['program_title'])): ?>
              <span class="block"><?= e((string) $r['program_title']) ?></span>
            <?php endif; ?>
          </td>
          <td><?= (int) $r['duration_days'] ?> gün</td>
          <td><?= money((int) $r['price']) ?></td>
          <td><?= e(package_access_label($r)) ?></td>
          <td><?= (int) $r['active'] ? 'Aktif' : 'Pasif' ?></td>
          <td class="whitespace-nowrap">
            <a class="text-sm font-extrabold text-navy" href="<?= e(paket_admin_url((int) $r['id'])) ?>">Düzenle</a>
            <span class="text-muted"> · </span>
            <?= panel_delete_form(paket_admin_url((int) $r['id']), ['act' => 'sil'], 'Paket silinsin mi? Kayıt varsa yalnızca pasife alınır.') ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<?php panel_foot();
