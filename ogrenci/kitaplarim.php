<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogrenci');
$st = db()->prepare(
    'SELECT sb.id, b.title, sb.status, sb.kind, b.is_digital FROM student_books sb JOIN books b ON b.id=sb.book_id WHERE sb.user_id=?'
);
$st->execute([$u['id']]);
$rows = $st->fetchAll();
panel_head('ogrenci', 'kitaplar', 'Kitaplarım | Öğrenci Paneli', $u);
echo '<p class="mb-4 text-sm text-muted">Mağazadan alınan veya paket hediyesi kitaplar.</p>';
echo '<div class="card overflow-hidden"><table class="table"><thead><tr><th>Kitap</th><th>Durum</th><th>Tür</th><th></th></tr></thead><tbody>';
foreach ($rows as $b) {
    $dl = shop_book_downloadable($b);
    echo '<tr><td>' . e($b['title']) . '</td><td>' . e($b['status']) . '</td><td>' . e($b['kind']) . '</td><td>';
    if ($dl) {
        echo '<a class="font-extrabold text-navy" href="' . e(url('ogrenci/indir.php?id=' . (int) $b['id'])) . '">İndir</a>';
    } else {
        echo '—';
    }
    echo '</td></tr>';
}
echo '</tbody></table></div><a href="' . e(url('kitaplar.php')) . '" class="btn-primary mt-4 inline-flex">Mağazadan yeni kitap</a>';
panel_foot();
