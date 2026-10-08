<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
ensure_live_attendance_schema();
$oda = (int) ($_GET['oda'] ?? 0);
$rows = live_logs_recent($oda, 200);
$labels = [
    'whip_fail' => 'Hoca yayın hatası',
    'whip_409' => 'Yayın meşgul',
    'whep_fail' => 'İzleme hatası',
    'camera' => 'Kamera',
    'ice' => 'Bağlantı',
    'info' => 'Bilgi',
];
panel_head('admin', 'canli', 'Canlı hata kayıtları | Admin', $u);
?>
<p class="mb-4 text-sm">
  <a class="font-extrabold text-navy" href="<?= e(url('admin/canli')) ?>">← Canlı dersler</a>
  · <a class="font-extrabold text-navy" href="<?= e(url('admin/bildirimler')) ?>">Bildirimler</a>
</p>
<div class="card mb-5 p-5">
  <h1 class="font-display text-2xl">Canlı hata kayıtları</h1>
  <p class="mt-1 text-sm text-muted">Hoca kamerası, Cloudflare bağlantısı ve izleme kopmaları. İzle butonu derse müdahale etmez; yalnızca görürsünüz.</p>
  <?php if ($oda > 0): ?>
    <p class="mt-3 text-sm">Oda #<?= (int) $oda ?> · <a class="font-extrabold text-navy" href="<?= e(url('admin/canli-log.php')) ?>">Tümünü göster</a></p>
  <?php endif; ?>
</div>
<div class="card overflow-hidden">
  <?php if (!$rows): ?>
    <p class="dash-empty px-5 py-5">Kayıt yok.</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Zaman</th>
          <th>Tür</th>
          <th>Ders</th>
          <th>Mesaj</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= e(profile_dt((string) ($r['created_at'] ?? ''))) ?></td>
            <td><?= e($labels[(string) ($r['kind'] ?? '')] ?? (string) ($r['kind'] ?? '')) ?></td>
            <td><?= e((string) ($r['room_title'] ?: ('Oda #' . (int) ($r['room_id'] ?? 0)))) ?></td>
            <td>
              <p class="font-extrabold"><?= e((string) ($r['message'] ?? '')) ?></p>
              <?php if (!empty($r['detail'])): ?><p class="text-xs text-muted"><?= e((string) $r['detail']) ?></p><?php endif; ?>
              <?php if (!empty($r['user_name'])): ?><p class="text-xs text-muted"><?= e((string) $r['user_name']) ?></p><?php endif; ?>
            </td>
            <td>
              <?php if ((int) ($r['room_id'] ?? 0) > 0): ?>
                <a class="font-extrabold text-navy" href="<?= e(canli_url((int) $r['room_id'])) ?>">İzle</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php panel_foot();
