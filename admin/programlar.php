<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
$rows = db()->query(
    'SELECT p.*,
            (SELECT COUNT(*) FROM class_groups g WHERE g.program_id = p.id) AS grup_n
     FROM programs p
     ORDER BY ' . catalog_order_sql('p', 'programs')
)->fetchAll();
$ok = flash_ok();
$err = flash_error();
panel_head('admin', 'programlar', 'Programlar | Admin', $u);
?>
<?php if ($ok): ?><p class="mb-4 font-bold text-green-700"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
  <p class="text-sm text-muted">Eğitim programları. Satırları tutup sürükleyerek vitrin sırasını değiştirin.</p>
  <a class="btn-primary text-sm" href="<?= e(url('admin/program/yeni')) ?>">Yeni program</a>
</div>

<?php if (!$rows): ?>
  <div class="card"><p class="dash-empty px-5 py-10">Henüz program yok.</p></div>
<?php else: ?>
  <div class="card overflow-hidden">
    <table class="table">
      <thead>
        <tr>
          <th></th>
          <th>Program</th>
          <th>Fiyat</th>
          <th>Grup</th>
          <th></th>
        </tr>
      </thead>
      <tbody data-sort-table="programs">
        <?php foreach ($rows as $p): ?>
          <tr data-sort-id="<?= (int) $p['id'] ?>">
            <td class="sort-handle" title="Sürükleyerek sıralayın">⋮⋮</td>
            <td>
              <a class="inline-flex items-center gap-3" href="<?= e(program_admin_url((int) $p['id'])) ?>">
                <span class="prod-swatch"><?= program_image_html($p, '', 'thumb') ?></span>
                <span>
                  <span class="font-extrabold text-navy"><?= e((string) $p['title']) ?></span>
                  <span class="block text-xs text-muted"><?= e((string) $p['level']) ?> · <?= e((string) $p['hours']) ?></span>
                </span>
              </a>
            </td>
            <td>
              <span class="price-old mr-1"><?= money((int) $p['price_old']) ?></span>
              <span class="font-extrabold"><?= money((int) $p['price_now']) ?></span>
            </td>
            <td><?= (int) $p['grup_n'] ?></td>
            <td class="whitespace-nowrap">
              <a class="font-extrabold text-navy" href="<?= e(program_admin_url((int) $p['id'])) ?>">Düzenle</a>
              <span class="text-muted"> · </span>
              <a class="font-extrabold text-navy" href="<?= e(page_url('program', (string) $p['slug'])) ?>">Sitede gör</a>
              <span class="text-muted"> · </span>
              <?= panel_delete_form(program_admin_url((int) $p['id']), ['act' => 'sil'], 'Program silinsin mi? Bağlı grup varsa silinmez.') ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php panel_foot();
