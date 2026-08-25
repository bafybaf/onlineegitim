<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$u = current_user();
if (!$u) {
    redirect('giris-ders.php');
}
if (!in_array($u['role'], ['ogrenci', 'ogretmen', 'admin'], true)) {
    redirect('giris-ders.php');
}
$id = (int) ($_GET['id'] ?? 0);
$st = db()->prepare('SELECT r.*, t.name teacher_name FROM live_rooms r JOIN users t ON t.id = r.teacher_id WHERE r.id = ?');
$st->execute([$id]);
$room = $st->fetch();
if (!$room) {
    redirect(panel_home($u['role']));
}
if (!live_user_can_access($u, $room)) {
    redirect(panel_home($u['role']));
}
if ($u['role'] === 'ogrenci' && function_exists('student_can_join_live') && !student_can_join_live((int) $u['id'], (int) $room['group_id'])) {
    flash_error('Bu paket yalnızca kayıt izleme içindir.');
    redirect('ogrenci/kayitlar');
}
if ($u['role'] === 'ogrenci') {
    db()->prepare('UPDATE attendance SET present = 1 WHERE room_id = ? AND student_id = ?')->execute([$id, $u['id']]);
}
$back = panel_home($u['role']);
if ($u['role'] === 'ogrenci') {
    $back = 'ogrenci/canli.php';
} elseif ($u['role'] === 'ogretmen') {
    $back = 'ogretmen/canli.php';
} else {
    $back = 'admin/canli.php';
}
if ($u['role'] === 'ogrenci') {
    $ls = db()->prepare("SELECT r.id, r.title, u.name teacher FROM live_rooms r JOIN users u ON u.id=r.teacher_id JOIN enrollments e ON e.group_id=r.group_id AND e.student_id=? AND (e.status='aktif' OR e.status IS NULL) AND (e.expires_at IS NULL OR e.expires_at > NOW()) WHERE r.status='live' ORDER BY r.id");
    $ls->execute([(int) $u['id']]);
    $lives = $ls->fetchAll();
} elseif ($u['role'] === 'ogretmen') {
    $ls = db()->prepare("SELECT r.id, r.title, u.name teacher FROM live_rooms r JOIN users u ON u.id=r.teacher_id WHERE r.status='live' AND r.teacher_id=? ORDER BY r.id");
    $ls->execute([(int) $u['id']]);
    $lives = $ls->fetchAll();
} else {
    $lives = db()->query("SELECT r.id, r.title, u.name teacher FROM live_rooms r JOIN users u ON u.id=r.teacher_id WHERE r.status='live' ORDER BY r.id")->fetchAll();
}
$chat = db()->prepare('SELECT who_label, body FROM live_chat WHERE room_id = ? ORDER BY id');
$chat->execute([$id]);
$msgs = $chat->fetchAll();
$stu = db()->prepare('SELECT u.id, u.name, COALESCE(a.present,0) present FROM enrollments e JOIN users u ON u.id=e.student_id LEFT JOIN attendance a ON a.student_id=u.id AND a.room_id=? WHERE e.group_id=?');
$stu->execute([$id, $room['group_id']]);
$students = $stu->fetchAll();
$canPublish = live_user_can_publish($u, $room);
$canEnd = $canPublish;
$playKey = live_ensure_stream_key(db(), $room);
$streamKey = $canPublish ? $playKey : '';
$rtmpUrl = $canPublish ? live_rtmp_url() : '';
$hlsUrl = live_hls_url($playKey);
$hlsUrlAlt = live_hls_url($playKey, 1);
$whepUrl = live_whep_url($playKey);
$whepUrlAlt = live_whep_url($playKey, 1);
$healthUrl = live_health_url();
$presentN = 0;
foreach ($students as $s) {
    if ((int) $s['present'] === 1) {
        $presentN++;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php security_html_head(); ?>
  <title>Canlı Sınıf | <?= e($room['title']) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={theme:{extend:{colors:{navy:'#1a3fad',navy3:'#0a1a4e',accent:'#e8232a'},fontFamily:{sans:['Nunito','sans-serif'],display:['Bricolage Grotesque','sans-serif']}}}}</script>
  <link rel="stylesheet" href="<?= e(url('assets/css/site.css')) ?>" />
  <script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.20/dist/hls.min.js"></script>
</head>
<body class="bg-black">
<div class="live-shell<?= $canPublish ? ' live-shell--obs' : '' ?>">
  <div class="live-strip">
    <?php foreach ($lives as $l): ?>
      <a class="<?= (int) $l['id'] === $id ? 'is-here' : '' ?>" href="<?= e(canli_url((int) $l['id'])) ?>">● <?= e($l['title']) ?> · <?= e(explode(' ', $l['teacher'])[count(explode(' ', $l['teacher'])) - 1]) ?></a>
    <?php endforeach; ?>
  </div>
  <header class="flex items-center justify-between gap-3 px-4 text-white">
    <div>
      <h1 class="font-display text-lg"><?= e($room['title']) ?><?php
        $topic = trim((string) ($room['topic'] ?? ''));
        echo ($topic !== '' && $topic !== 'Ders') ? ' — ' . e($topic) : '';
      ?></h1>
    </div>
    <div class="flex items-center gap-3 text-sm">
      <span><?= e($room['teacher_name']) ?></span>
      <?php if ($canEnd && $room['status'] === 'live'): ?>
        <form method="post" action="<?= e(url('api/live.php')) ?>"><input type="hidden" name="action" value="end"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="goto" value="<?= e($back) ?>"><button class="rounded-lg bg-white/10 px-3 py-1.5 font-extrabold">Dersi bitir</button></form>
      <?php endif; ?>
      <a href="<?= e(url($back)) ?>" class="rounded-lg bg-accent px-3 py-1.5 font-extrabold">Ayrıl</a>
    </div>
  </header>
  <div class="live-main text-white">
    <div class="live-stage">
      <video id="live-video" playsinline autoplay muted controls></video>
      <div id="wait-overlay" class="live-wait">
        <p id="wait-title" class="font-display text-2xl"><?= $canPublish ? 'Yayın bekleniyor' : 'Hoca bağlanıyor' ?></p>
        <p id="wait-detail" class="mt-2 text-sm text-white/70"<?= $canPublish ? '' : ' hidden' ?>><?= $canPublish ? 'OBS’i başlatın.' : '' ?></p>
      </div>
      <p id="live-proto" class="absolute bottom-14 left-4 rounded-lg bg-black/50 px-2 py-1 text-[11px] text-white/80" hidden></p>
      <div class="absolute bottom-4 left-4 rounded-xl bg-black/50 px-3 py-2 text-sm"><?= $presentN ?>/<?= count($students) ?> · <?= live_mins($room['started_at']) ?> dk</div>
    </div>
    <aside class="chat">
      <div class="border-b border-[#1d2744] px-4 py-3 font-extrabold">Sohbet · Yoklama</div>
      <div id="chat-log" class="chat-log text-sm"><?php foreach ($msgs as $m): ?><p><b><?= e($m['who_label']) ?>:</b> <?= e($m['body']) ?></p><?php endforeach; ?></div>
      <?php if (in_array($u['role'], ['ogretmen', 'admin'], true)): ?>
      <div class="max-h-28 overflow-auto border-t border-[#1d2744] px-3 py-2 text-xs">
        <?php foreach ($students as $s): ?>
          <label class="mr-3 inline-flex items-center gap-1"><input type="checkbox" class="att" data-sid="<?= (int) $s['id'] ?>" <?= $s['present'] ? 'checked' : '' ?>> <?= e($s['name']) ?></label>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <form id="chat-form" class="flex gap-2 border-t border-[#1d2744] p-3">
        <input name="q" class="flex-1 rounded-lg bg-[#0b1020] px-3 py-2 text-sm outline-none" placeholder="Mesaj yazın" autocomplete="off">
        <button class="rounded-lg bg-navy px-3 font-extrabold">Gönder</button>
      </form>
    </aside>
  </div>
  <?php if ($canPublish): ?>
  <section class="live-obs text-white">
    <label class="live-obs-field">Sunucu
      <span class="live-obs-row">
        <input id="obs-server" readonly value="<?= e($rtmpUrl) ?>">
        <button type="button" data-copy="obs-server">Kopyala</button>
      </span>
    </label>
    <label class="live-obs-field">Anahtar
      <span class="live-obs-row">
        <input id="obs-key" readonly value="<?= e($streamKey) ?>">
        <button type="button" data-copy="obs-key">Kopyala</button>
      </span>
    </label>
  </section>
  <?php endif; ?>
</div>
<script>
const base = <?= json_encode(url('')) ?>;
const roomId = <?= $id ?>;
window.LIVE_PLAYER = {
  hlsUrl: <?= json_encode($hlsUrl) ?>,
  hlsUrlAlt: <?= json_encode($hlsUrlAlt) ?>,
  whepUrl: <?= json_encode($whepUrl) ?>,
  whepUrlAlt: <?= json_encode($whepUrlAlt) ?>,
  healthUrl: <?= json_encode($healthUrl) ?>
};

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

document.getElementById('chat-form').onsubmit = async (e) => {
  e.preventDefault();
  const t = e.target.q.value.trim(); if (!t) return;
  await fetch(base + 'api/live.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=chat&room_id='+roomId+'&body='+encodeURIComponent(t) });
  e.target.q.value='';
};
document.querySelectorAll('.att').forEach((cb) => cb.addEventListener('change', () => {
  fetch(base + 'api/live.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=attend&room_id='+roomId+'&student_id='+cb.dataset.sid+'&present='+(cb.checked?'1':'0') });
}));
document.querySelectorAll('[data-copy]').forEach((b) => b.addEventListener('click', async () => {
  const el = document.getElementById(b.dataset.copy);
  if (!el) return;
  try { await navigator.clipboard.writeText(el.value); b.textContent = 'Kopyalandı'; }
  catch (err) { el.select(); document.execCommand('copy'); b.textContent = 'Kopyalandı'; }
  setTimeout(() => { b.textContent = 'Kopyala'; }, 1500);
}));
setInterval(async () => {
  const r = await fetch(base + 'api/live.php?action=poll&id=' + roomId);
  const j = await r.json();
  if (!j.ok) return;
  const log = document.getElementById('chat-log');
  log.innerHTML = (j.chat||[]).map(c => `<p><b>${esc(c.who_label)}:</b> ${esc(c.body)}</p>`).join('');
  log.scrollTop = log.scrollHeight;
  if (j.room && j.room.status === 'ended') {
    if (typeof window.livePlayerMarkEnded === 'function') window.livePlayerMarkEnded();
  }
}, 2000);
</script>
<script src="<?= e(url('assets/js/live-player.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/assets/js/live-player.js') ?>"></script>
</body>
</html>
