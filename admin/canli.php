<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
ensure_live_attendance_schema();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'live_board') {
    setting_set('live_board_on', post('live_board_on') === '1' ? '1' : '0');
    flash_ok(live_board_enabled()
        ? 'Tahta açıldı. Hocalar kalem ve PDF kullanabilir.'
        : 'Tahta gizlendi. Derste yalnız kamera ve ekran paylaşımı kalır.');
    redirect('admin/canli.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'cf_stream') {
    $pairs = [
        'cf_stream_whip' => trim((string) post('cf_stream_whip')),
        'cf_stream_whep' => trim((string) post('cf_stream_whep')),
        'cf_stream_whip_screen' => trim((string) post('cf_stream_whip_screen')),
        'cf_stream_whep_screen' => trim((string) post('cf_stream_whep_screen')),
    ];
    foreach ($pairs as $k => $v) {
        setting_set($k, $v);
    }
    flash_ok(live_cf_ready()
        ? 'Cloudflare yayın adresi kaydedildi. Kamera artık Stream üzerinden gider.'
        : 'Adresler temizlendi. Yayın yine sunucudaki MediaMTX üzerinden gider.');
    redirect('admin/canli.php');
}
$all = db()->query(
    "SELECT r.*, t.name teacher_name, g.name gname, g.cap,
            (SELECT COUNT(*) FROM attendance a WHERE a.room_id = r.id AND a.present = 1) present_n
     FROM live_rooms r
     JOIN users t ON t.id = r.teacher_id
     JOIN class_groups g ON g.id = r.group_id
     ORDER BY r.status = 'live' DESC, r.id DESC
     LIMIT 120"
)->fetchAll();
$groups = db()->query('SELECT id, name FROM class_groups ORDER BY name')->fetchAll();
$enrollSql = 'SELECT e.group_id, u.id, u.name FROM enrollments e JOIN users u ON u.id = e.student_id WHERE 1=1';
$enCols = function_exists('live_enrollment_columns') ? live_enrollment_columns() : [];
if (isset($enCols['status'])) {
    $enrollSql .= " AND (e.status = 'aktif' OR e.status IS NULL)";
}
if (isset($enCols['expires_at'])) {
    $enrollSql .= ' AND (e.expires_at IS NULL OR e.expires_at > NOW())';
}
$enrollSql .= ' ORDER BY u.name';
$enrollRows = db()->query($enrollSql)->fetchAll();
panel_head('admin', 'canli', 'Canlı dersler | Admin', $u);
$ok = flash_ok();
$err = flash_error();
?>
<?php if ($ok): ?><p class="mb-4 font-bold text-green-700"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>
<?php $liveErrN = function_exists('live_log_recent_error_count') ? live_log_recent_error_count() : 0; ?>
<p class="mb-4 text-sm text-muted">Test yayını yalnızca seçtiğiniz öğrencileri alır; grubun geri kalanı odayı görmez. Kamerayı sizin açmanız için oda size bağlanır. Gözlemle ile normal derse öğrenci gibi girersiniz; kalem, kamera, mola veya yoklama değiştirmezsiniz. Oda kapatmak bu listedeki Kapat ile kalır.</p>
<p class="mb-4 text-sm"><a class="font-extrabold text-navy" href="<?= e(url('admin/kayitlar')) ?>">Ders kayıtları</a> · <a class="font-extrabold text-navy" href="<?= e(url('admin/canli-log.php')) ?>">Hata kayıtları<?= $liveErrN > 0 ? ' (' . (int) $liveErrN . ')' : '' ?></a> · <a class="font-extrabold text-navy" href="<?= e(url('admin/bildirimler')) ?>">Bildirimler</a></p>
<div class="card mb-6 p-5">
  <h2 class="font-display text-xl">Cloudflare Stream</h2>
  <p class="mt-1 text-sm text-muted">WHIP yayın / WHEP izleme adresleri doluysa kamera ve ekran Cloudflare’a gider; sunucu CPU’su rahatlar. Şu an tek girdi var: aynı anda bir ders yayınlasın. Kayıt yine sitede kalır.</p>
  <p class="mt-2 text-sm <?= live_cf_ready() ? 'font-extrabold text-green-700' : 'text-amber-700' ?>"><?= live_cf_ready() ? 'Stream açık.' : 'Stream adresi yok; MediaMTX kullanılıyor.' ?></p>
  <form method="post" class="mt-4 grid gap-3">
    <input type="hidden" name="action" value="cf_stream">
    <label class="text-sm font-bold">Kamera WHIP
      <input name="cf_stream_whip" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= e(setting('cf_stream_whip') ?: (defined('CF_STREAM_WHIP') ? (string) CF_STREAM_WHIP : '')) ?>" autocomplete="off">
    </label>
    <label class="text-sm font-bold">Kamera WHEP
      <input name="cf_stream_whep" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= e(setting('cf_stream_whep') ?: (defined('CF_STREAM_WHEP') ? (string) CF_STREAM_WHEP : '')) ?>" autocomplete="off">
    </label>
    <label class="text-sm font-bold">Ekran WHIP
      <input name="cf_stream_whip_screen" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= e(setting('cf_stream_whip_screen') ?: (defined('CF_STREAM_WHIP_SCREEN') ? (string) CF_STREAM_WHIP_SCREEN : '')) ?>" autocomplete="off">
    </label>
    <label class="text-sm font-bold">Ekran WHEP
      <input name="cf_stream_whep_screen" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= e(setting('cf_stream_whep_screen') ?: (defined('CF_STREAM_WHEP_SCREEN') ? (string) CF_STREAM_WHEP_SCREEN : '')) ?>" autocomplete="off">
    </label>
    <button class="btn-primary h-10 w-fit text-sm">Kaydet</button>
  </form>
</div>
<div class="card mb-6 p-5">
  <h2 class="font-display text-xl">Beyaz tahta</h2>
  <p class="mt-1 text-sm text-muted">Kapalıyken kalem, silgi ve PDF görünmez. Derste kamera ve ekran paylaşımı kalır. Açınca hocalar tekrar tahtayı kullanır.</p>
  <p class="mt-2 text-sm <?= live_board_enabled() ? 'font-extrabold text-green-700' : 'text-amber-700' ?>"><?= live_board_enabled() ? 'Tahta açık.' : 'Tahta gizli.' ?></p>
  <form method="post" class="mt-4">
    <input type="hidden" name="action" value="live_board">
    <label class="flex items-center gap-2 text-sm font-bold">
      <input type="checkbox" name="live_board_on" value="1" <?= live_board_enabled() ? 'checked' : '' ?>>
      Tahtayı aç (kalem, PDF)
    </label>
    <button class="btn-primary mt-3 h-10 w-fit text-sm">Kaydet</button>
  </form>
</div>
<div class="card mb-6 p-5">
  <h2 class="font-display text-xl">Canlı yayın testi</h2>
  <p class="mt-1 text-sm text-muted">Kayıtlı grubun tamamı girmez. Öğrenci seçmezseniz oda yalnız sizde kalır; kamerayı deneyebilirsiniz.</p>
  <?php if (!$groups): ?>
    <p class="mt-3 text-sm text-muted">Önce bir sınıf grubu oluşturun.</p>
  <?php else: ?>
  <form method="post" action="<?= e(url('api/live.php')) ?>" class="mt-4 grid gap-3">
    <input type="hidden" name="action" value="start">
    <input type="hidden" name="html" value="1">
    <input type="hidden" name="test" value="1">
    <input type="hidden" name="record" value="1">
    <label class="text-sm font-bold">Grup
      <select id="test-group" name="group_id" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
        <?php foreach ($groups as $g): ?>
          <option value="<?= (int) $g['id'] ?>"><?= e((string) $g['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="text-sm font-bold">Konu
      <input name="topic" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="Test yayını">
    </label>
    <label class="text-sm font-bold">Girebilecek öğrenciler
      <select id="test-students" name="student_ids[]" multiple size="8" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
        <?php foreach ($enrollRows as $s): ?>
          <option value="<?= (int) $s['id'] ?>" data-group="<?= (int) $s['group_id'] ?>"><?= e((string) $s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <p class="text-xs text-muted">Ctrl veya Cmd ile birden çok kişi seçin. Boş bırakılırsa kimse giremez.</p>
    <button class="btn-primary h-10 w-fit text-sm">Test odasını aç</button>
  </form>
  <script>
  (function () {
    var g = document.getElementById('test-group');
    var s = document.getElementById('test-students');
    if (!g || !s) return;
    function filt() {
      var id = String(g.value);
      Array.prototype.forEach.call(s.options, function (o) {
        var ok = o.getAttribute('data-group') === id;
        o.hidden = !ok;
        o.disabled = !ok;
        if (!ok) o.selected = false;
      });
    }
    g.addEventListener('change', filt);
    filt();
  })();
  </script>
  <?php endif; ?>
</div>
<div class="card overflow-hidden">
  <table class="table">
    <thead>
      <tr>
        <th>Ders</th>
        <th>Hoca</th>
        <th>Açılış</th>
        <th>Bitiş</th>
        <th>Giren / kontenjan</th>
        <th>Durum</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php if (!$all): ?>
      <tr><td colspan="7" class="text-muted">Henüz canlı ders yok.</td></tr>
    <?php endif; ?>
    <?php foreach ($all as $r):
        $cap = max(1, (int) $r['cap']);
        $n = (int) $r['present_n'];
        $full = $n >= $cap;
        ?>
      <tr>
        <td>
          <a class="font-extrabold text-navy" href="<?= e(url('admin/canli-oda.php?id=' . (int) $r['id'])) ?>"><?= e($r['title']) ?></a>
          <p class="text-xs text-muted"><?= e((string) $r['gname']) ?><?php
            if (function_exists('live_room_is_test') && live_room_is_test($r)) {
                echo ' · test';
                $nAllow = function_exists('live_allow_ids') ? count(live_allow_ids($r)) : 0;
                echo ' · ' . (int) $nAllow . ' öğrenci';
            }
            $topic = trim((string) ($r['topic'] ?? ''));
            echo ($topic !== '' && $topic !== 'Ders' && $topic !== 'Test yayını') ? ' · ' . e($topic) : '';
          ?></p>
        </td>
        <td><?= e($r['teacher_name']) ?></td>
        <td><?= e(profile_dt((string) ($r['started_at'] ?? ''))) ?></td>
        <td><?= ($r['status'] ?? '') === 'live' ? '—' : e(profile_dt((string) ($r['ended_at'] ?? ''))) ?></td>
        <td>
          <span class="<?= $full ? 'mem-bad' : 'mem-ok' ?> font-extrabold"><?= $n ?> / <?= $cap ?></span>
        </td>
        <td><?= ($r['status'] ?? '') === 'live' ? live_pill($r) : 'Bitti' ?></td>
        <td>
          <a class="font-extrabold text-navy" href="<?= e(url('admin/canli-oda.php?id=' . (int) $r['id'])) ?>">Girenler</a>
          <?php if (($r['status'] ?? '') === 'live'): ?>
            · <a class="font-extrabold text-accent" href="<?= e(canli_url((int) $r['id'])) ?>"><?= live_user_can_publish($u, $r) ? 'Yayına gir' : 'Gözlemle' ?></a>
            · <form class="inline" method="post" action="<?= e(url('api/live.php')) ?>"><input type="hidden" name="action" value="end"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="goto" value="admin/kayitlar.php"><button class="font-extrabold text-muted">Kapat</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php panel_foot();
