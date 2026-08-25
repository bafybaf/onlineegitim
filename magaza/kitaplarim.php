<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('musteri');

$st = db()->prepare(
    'SELECT sb.id, sb.status, sb.kind, b.id book_id, b.title, b.slug, b.author, b.is_digital, b.pages
     FROM student_books sb
     JOIN books b ON b.id = sb.book_id
     WHERE sb.user_id = ?
     ORDER BY sb.id DESC'
);
$st->execute([$u['id']]);
$books = $st->fetchAll();

panel_head('musteri', 'kitaplar', 'Kitaplarım | Mağaza', $u);
?>
<p class="mb-5 text-sm text-muted">Satın aldığınız kitaplar. Dijital kopyalar hemen indirilir; basılılar kargo durumuna göre ilerler.</p>
<?php if (!$books): ?>
  <div class="card">
    <?php shop_empty('Henüz kitabınız yok', 'Ödeme onayından sonra dijital PDF ve basılı siparişler bu sayfaya düşer.', page_url('kitaplar'), 'Mağazadan yeni kitap'); ?>
  </div>
<?php else: ?>
  <div class="card overflow-hidden">
    <table class="table">
      <thead><tr><th>Kitap</th><th>Tür</th><th>Durum</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($books as $b): ?>
          <tr>
            <td>
              <p class="font-extrabold"><?= e($b['title']) ?></p>
              <p class="text-xs text-muted"><?= e((string) $b['author']) ?><?= !empty($b['pages']) ? ' · ' . (int) $b['pages'] . ' sayfa' : '' ?></p>
            </td>
            <td><?= e(utf8_from_mojibake((string) $b['kind'])) ?></td>
            <td><span class="<?= shop_status_class((string) $b['status']) ?>"><?= e(utf8_from_mojibake((string) $b['status'])) ?></span></td>
            <td>
              <?php if (shop_book_downloadable($b)): ?>
                <a class="btn-primary h-9 px-3 text-sm" href="<?= e(url('magaza/indir.php?id=' . (int) $b['id'])) ?>">İndir</a>
              <?php else: ?>
                <span class="text-sm text-muted">Kargo bekleniyor</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php panel_foot();
