<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('musteri');
$rows = program_purchases_for_user((int) $u['id']);

panel_head('musteri', 'egitimler', 'Eğitimlerim | Mağaza', $u);
?>
<p class="mb-5 text-sm text-muted">Mağaza hesabınızla satın aldığınız eğitimler. Sınıf yerleştirmenizi yönetim yapar.</p>
<?php if (!$rows): ?>
  <div class="card">
    <?php shop_empty('Henüz eğitiminiz yok', 'Eğitimleri sepete ekleyip mağaza hesabıyla satın alın.', page_url('programlar'), 'Eğitimlere bak'); ?>
  </div>
<?php else: ?>
  <div class="card overflow-hidden">
    <table class="table">
      <thead><tr><th>Eğitim</th><th>Tutar</th><th>Durum</th><th>Tarih</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td>
              <p class="font-extrabold"><a href="<?= e(page_url('program', (string) $r['slug'])) ?>"><?= e((string) $r['title']) ?></a></p>
              <p class="text-xs text-muted"><?= e((string) ($r['level'] ?? '')) ?><?= !empty($r['hours']) ? ' · ' . e((string) $r['hours']) : '' ?></p>
            </td>
            <td><?= e(money_or_free((int) $r['price'])) ?></td>
            <td><span class="<?= shop_status_class((string) $r['status']) ?>"><?= e((string) $r['status']) ?></span></td>
            <td class="text-sm text-muted"><?= e(shop_date((string) $r['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php panel_foot();
