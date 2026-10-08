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
if ($u['role'] === 'ogrenci') {
    $watchReason = live_student_watch_reason($room, (int) $u['id']);
    if ($watchReason !== null) {
        flash_error($watchReason);
        redirect('ogrenci/kayitlar.php?grup=' . (int) $room['group_id']);
    }
    if (!live_student_try_enter($room, (int) $u['id'])) {
        flash_error(live_full_watch_message());
        redirect('ogrenci/kayitlar.php?grup=' . (int) $room['group_id']);
    }
}
$back = panel_home($u['role']);
if ($u['role'] === 'ogrenci') {
    $back = 'ogrenci/canli.php';
} elseif ($u['role'] === 'ogretmen') {
    $back = 'ogretmen/canli.php';
} else {
    $back = 'admin/canli.php';
}
$endGo = $u['role'] === 'ogretmen' ? 'ogretmen/kayit-yukle.php' : $back;
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
$students = live_present_students($id);
$canPublish = live_user_can_publish($u, $room);
$isObserver = function_exists('live_user_is_observer') && live_user_is_observer($u, $room);
$canEnd = $canPublish;
$playKey = live_ensure_stream_key(db(), $room);
$hlsUrl = live_hls_url($playKey);
$hlsUrlAlt = live_hls_url($playKey, 1);
$whepUrl = live_whep_url($playKey);
$whepUrlAlt = live_whep_url($playKey, 1);
$whipUrl = $canPublish ? live_whip_url($playKey) : '';
$whipUrlAlt = $canPublish ? live_whip_url($playKey, 1) : '';
$screenKey = $playKey . '-screen';
$hlsScreenUrl = live_hls_url($screenKey);
$hlsScreenUrlAlt = live_hls_url($screenKey, 1);
$whepScreenUrl = live_whep_url($screenKey);
$whepScreenUrlAlt = live_whep_url($screenKey, 1);
$whipScreenUrl = $canPublish ? live_whip_url($screenKey) : '';
$whipScreenUrlAlt = $canPublish ? live_whip_url($screenKey, 1) : '';
$healthUrl = live_health_url();
$doRecord = $canPublish && ($room['status'] ?? '') === 'live';
$pauseInfo = live_room_pause_state($room);
$waitTitle = $canPublish ? 'Kamera' : 'Hoca bağlanıyor';
$showRoster = in_array($u['role'], ['ogretmen', 'admin'], true);
$presentN = count($students);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php security_html_head(); ?>
  <title>Canlı Sınıf | <?= e($room['title']) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={theme:{extend:{colors:{navy:'#111111',navy3:'#0a0a0a',accent:'#e8232a'},fontFamily:{sans:['Nunito','sans-serif'],display:['Bricolage Grotesque','sans-serif']}}}}</script>
  <link rel="stylesheet" href="<?= e(url('assets/css/site.css')) ?>?v=<?= (int) @filemtime(__DIR__ . '/assets/css/site.css') ?>" />
  <script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.20/dist/hls.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
  <script>if (window.pdfjsLib) pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';</script>
</head>
<body class="bg-black">
<div class="live-shell">
  <?php if ($lives): ?>
  <div class="live-strip">
    <?php foreach ($lives as $l): ?>
      <a class="<?= (int) $l['id'] === $id ? 'is-here' : '' ?>" href="<?= e(canli_url((int) $l['id'])) ?>">● <?= e($l['title']) ?> · <?= e(explode(' ', $l['teacher'])[count(explode(' ', $l['teacher'])) - 1]) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php if (!$canPublish): ?>
  <header class="flex items-center justify-between gap-2 px-3 text-white">
    <h1 class="live-top-title font-display"><?php if ($isObserver): ?><span class="live-pill">Gözlem</span> <?php endif; ?><?= e($room['title']) ?><?php
      $topic = trim((string) ($room['topic'] ?? ''));
      echo ($topic !== '' && $topic !== 'Ders') ? ' — ' . e($topic) : '';
    ?></h1>
    <div class="live-top-actions">
      <?php if ($isObserver): ?>
        <a href="<?= e(url('admin/canli-log.php?oda=' . $id)) ?>" class="live-cam-btn">Kayıtlar</a>
      <?php endif; ?>
      <a href="<?= e(url($back)) ?>" id="live-leave" class="live-cam-btn">Ayrıl</a>
    </div>
  </header>
  <?php endif; ?>
  <div class="live-main text-white">
    <section class="live-board" id="live-board">
      <?php if ($canPublish): ?>
      <div class="live-board-bar">
        <h1 class="live-top-title"><?= e($room['title']) ?></h1>
        <button type="button" class="live-board-tool is-on" data-tool="pen">Kalem</button>
        <button type="button" class="live-board-tool" data-tool="erase">Silgi</button>
        <button type="button" class="live-board-tool" data-tool="pan">Kaydır</button>
        <span class="live-board-swatches">
          <button type="button" class="live-board-dot is-on" data-color="#111827" style="background:#111827" title="Siyah"></button>
          <button type="button" class="live-board-dot" data-color="#f5c542" style="background:#f5c542" title="Sarı"></button>
          <button type="button" class="live-board-dot" data-color="#e8232a" style="background:#e8232a" title="Kırmızı"></button>
          <button type="button" class="live-board-dot" data-color="#1a3fad" style="background:#1a3fad" title="Mavi"></button>
        </span>
        <input type="range" id="board-size" min="2" max="18" value="4" aria-label="Kalınlık">
        <button type="button" class="live-board-tool" data-act="undo">Geri</button>
        <button type="button" class="live-board-tool" data-act="clear">Temizle</button>
        <label class="live-board-tool live-board-file" id="board-pdf-label"><span id="board-pdf-text">PDF</span><input type="file" id="board-pdf" accept="application/pdf" hidden></label>
        <button type="button" class="live-board-tool" data-act="pdf_off" id="board-pdf-off" hidden>Kapat</button>
        <button type="button" class="live-board-tool" data-act="zoomout">−</button>
        <span id="board-zoom">100%</span>
        <button type="button" class="live-board-tool" data-act="zoomin">+</button>
        <button type="button" class="live-board-tool" data-act="zoomreset">1:1</button>
        <span id="board-page"></span>
        <span class="live-board-sep"></span>
        <button type="button" id="whip-toggle" class="live-cam-btn">Kamera</button>
        <button type="button" id="whip-share" class="live-cam-btn live-cam-btn--ghost" title="Ekranı beyaz tahtada gösterin; kamera açık kalır">Ekran</button>
        <button type="button" id="whip-listen" class="live-cam-btn live-cam-btn--ghost" hidden>Ses</button>
        <span class="live-mic-meter" id="whip-meter" hidden><i></i></span>
        <?php if ($canEnd && $room['status'] === 'live'): ?>
        <span class="live-board-sep"></span>
        <button type="button" id="live-pause-btn" class="live-board-tool">Mola</button>
        <button type="button" id="live-rec-start" class="live-cam-btn">Kayıt</button>
        <form method="post" action="<?= e(url('api/live.php')) ?>"><input type="hidden" name="action" value="end"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="goto" value="<?= e($endGo) ?>"><button class="live-board-tool">Bitir</button></form>
        <?php endif; ?>
        <a href="<?= e(url($back)) ?>" id="live-leave" class="live-cam-btn">Ayrıl</a>
      </div>
      <?php endif; ?>
      <div class="live-board-stage" id="board-stage">
        <video id="board-screen" playsinline autoplay muted></video>
        <canvas id="board-bg"></canvas>
        <canvas id="board-draw"></canvas>
        <div id="live-rec-count" class="live-rec-count">
          <b id="live-rec-num">3</b>
          <p>Kayıt başlıyor</p>
        </div>
        <div id="live-pause-overlay" class="live-pause-overlay<?= !empty($pauseInfo['paused']) ? ' is-on' : '' ?>">
          <p class="live-pause-kicker">Kısa ara</p>
          <b>Mola</b>
          <p>Öğretmen kısa bir ara verdi. Ders birazdan devam edecek.</p>
        </div>
      </div>
    </section>
    <div class="live-side">
      <div class="live-stage" id="live-stage">
        <div class="live-stage-oval" id="live-cam-pip">
          <video id="live-video" playsinline autoplay muted controls></video>
          <div id="wait-overlay" class="live-wait">
            <p id="wait-title" class="font-display text-2xl"><?= e($waitTitle) ?></p>
            <p id="wait-detail" hidden></p>
          </div>
          <button type="button" id="live-unmute" class="live-unmute-btn" hidden>Sesi aç</button>
        </div>
        <p id="live-proto" class="absolute bottom-14 left-4 rounded-lg bg-black/50 px-2 py-1 text-[11px] text-white/80" hidden></p>
        <div id="live-seat-n" class="absolute bottom-4 left-4 rounded-xl bg-black/50 px-3 py-2 text-sm"><?= (int) $presentN ?> derste</div>
      </div>
      <aside class="chat">
      <div class="border-b border-[#2a2a2a] px-4 py-3 font-extrabold">Sohbet<?php if ($showRoster): ?> · Derstekiler <span id="live-present-n"><?= (int) $presentN ?></span><?php endif; ?></div>
      <div id="chat-log" class="chat-log text-sm"><?php foreach ($msgs as $m): ?><p><b><?= e($m['who_label']) ?>:</b> <?= e($m['body']) ?></p><?php endforeach; ?></div>
      <?php if ($showRoster): ?>
      <p class="px-4 pt-2"><a class="text-xs font-extrabold text-accent" href="<?= e(url(($u['role'] === 'admin' ? 'admin/canli-oda.php?id=' : 'ogretmen/yoklama.php?oda=') . $id)) ?>">Kim girdi (yoklama)</a></p>
      <div id="live-present-list" class="live-present-list">
        <?php if (!$students): ?>
          <p class="live-present-empty">Henüz öğrenci girmedi</p>
        <?php else: ?>
          <?php foreach ($students as $s): ?>
            <?php if ($isObserver): ?>
              <p class="live-present-row"><?= e($s['name']) ?><?php if (!empty($s['entered_at'])): ?> <span class="live-present-time"><?= e(date('H:i', strtotime((string) $s['entered_at']))) ?></span><?php endif; ?></p>
            <?php else: ?>
              <label class="live-present-row"><input type="checkbox" class="att" data-sid="<?= (int) $s['id'] ?>" checked> <?= e($s['name']) ?><?php if (!empty($s['entered_at'])): ?> <span class="live-present-time"><?= e(date('H:i', strtotime((string) $s['entered_at']))) ?></span><?php endif; ?></label>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if (!$isObserver): ?>
      <form id="chat-form" class="flex gap-2 border-t border-[#2a2a2a] p-3">
        <input name="q" class="flex-1 rounded-lg bg-[#0b1020] px-3 py-2 text-sm outline-none" placeholder="Mesaj yazın" autocomplete="off">
        <button class="rounded-lg bg-navy px-3 font-extrabold">Gönder</button>
      </form>
      <?php endif; ?>
      </aside>
    </div>
  </div>
</div>
<script>
const base = <?= json_encode(url('')) ?>;
const roomId = <?= $id ?>;
window.LIVE_PLAYER = {
  method: 'browser',
  provider: <?= json_encode(live_cf_ready() ? 'cloudflare' : 'mediamtx') ?>,
  publish: <?= $canPublish ? 'true' : 'false' ?>,
  hlsUrl: <?= json_encode($hlsUrl) ?>,
  hlsUrlAlt: <?= json_encode($hlsUrlAlt) ?>,
  whepUrl: <?= json_encode($whepUrl) ?>,
  whepUrlAlt: <?= json_encode($whepUrlAlt) ?>,
  whipUrl: <?= json_encode($whipUrl) ?>,
  whipUrlAlt: <?= json_encode($whipUrlAlt) ?>,
  hlsScreenUrl: <?= json_encode($hlsScreenUrl) ?>,
  hlsScreenUrlAlt: <?= json_encode($hlsScreenUrlAlt) ?>,
  whepScreenUrl: <?= json_encode($whepScreenUrl) ?>,
  whepScreenUrlAlt: <?= json_encode($whepScreenUrlAlt) ?>,
  whipScreenUrl: <?= json_encode($whipScreenUrl) ?>,
  whipScreenUrlAlt: <?= json_encode($whipScreenUrlAlt) ?>,
  healthUrl: <?= json_encode($healthUrl) ?>,
  api: <?= json_encode(url('api/live.php')) ?>,
  roomId: <?= (int) $id ?>,
  observe: <?= $isObserver ? 'true' : 'false' ?>
};
window.LIVE_BOARD = {
  publish: <?= $canPublish ? 'true' : 'false' ?>,
  roomId: <?= $id ?>,
  url: <?= json_encode(url('api/live.php')) ?>
};
window.LIVE_RECORD = {
  on: <?= $doRecord ? 'true' : 'false' ?>,
  roomId: <?= $id ?>,
  url: <?= json_encode(url('api/live.php')) ?>,
  started: <?= json_encode((string) ($room['started_at'] ?? '')) ?>
};
window.LIVE_PAUSE = {
  roomId: <?= $id ?>,
  url: <?= json_encode(url('api/live.php')) ?>,
  canCtrl: <?= ($canEnd && ($room['status'] ?? '') === 'live') ? 'true' : 'false' ?>,
  paused: <?= !empty($pauseInfo['paused']) ? 'true' : 'false' ?>
};

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

const chatForm = document.getElementById('chat-form');
if (chatForm) chatForm.onsubmit = (e) => {
  e.preventDefault();
  const t = e.target.q.value.trim(); if (!t) return;
  e.target.q.value = '';
  fetch(base + 'api/live.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=chat&room_id='+roomId+'&body='+encodeURIComponent(t) });
};
const observeOnly = <?= $isObserver ? 'true' : 'false' ?>;
function bindAtt(cb) {
  cb.addEventListener('change', async () => {
    const r = await fetch(base + 'api/live.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=attend&room_id='+roomId+'&student_id='+cb.dataset.sid+'&present='+(cb.checked?'1':'0') });
    const j = await r.json().catch(() => ({}));
    if (!r.ok || !j.ok) {
      cb.checked = !cb.checked;
      if (j.message) window.alert(j.message);
    }
  });
}
document.querySelectorAll('.att').forEach(bindAtt);
let presentSig = <?= json_encode(implode(',', array_map(static fn ($s) => (string) (int) $s['id'], $students))) ?>;
function renderPresent(rows) {
  const list = document.getElementById('live-present-list');
  if (!list) return;
  const n = (rows || []).length;
  const nEl = document.getElementById('live-present-n');
  if (nEl) nEl.textContent = String(n);
  const seat = document.getElementById('live-seat-n');
  if (seat) {
    seat.textContent = seat.textContent.replace(/^\d+ derste/, n + ' derste');
  }
  const sig = (rows || []).map((s) => String(s.id)).join(',');
  if (sig === presentSig) return;
  presentSig = sig;
  if (!rows || !rows.length) {
    list.innerHTML = '<p class="live-present-empty">Henüz öğrenci girmedi</p>';
    return;
  }
  list.innerHTML = rows.map((s) => {
    var t = s.entered_at ? String(s.entered_at).slice(11, 16) : '';
    var time = t ? ' <span class="live-present-time">'+esc(t)+'</span>' : '';
    if (observeOnly) {
      return '<p class="live-present-row">'+esc(s.name)+time+'</p>';
    }
    return '<label class="live-present-row"><input type="checkbox" class="att" data-sid="'+s.id+'" checked> '+esc(s.name)+time+'</label>';
  }).join('');
  if (!observeOnly) list.querySelectorAll('.att').forEach(bindAtt);
}
let chatSig = '';
setInterval(async () => {
  const r = await fetch(base + 'api/live.php?action=poll&id=' + roomId);
  const j = await r.json();
  if (!j.ok) return;
  const rows = j.chat || [];
  const sig = rows.map((c) => String(c.id || '') + ':' + (c.body || '')).join('|');
  if (sig !== chatSig) {
    chatSig = sig;
    const log = document.getElementById('chat-log');
    log.innerHTML = rows.map(c => `<p><b>${esc(c.who_label)}:</b> ${esc(c.body)}</p>`).join('');
    log.scrollTop = log.scrollHeight;
  }
  if (Array.isArray(j.present)) renderPresent(j.present);
  if (j.room && typeof window.livePauseApply === 'function') {
    window.livePauseApply(j.room);
  }
  if (j.room && j.room.status === 'ended') {
    if (typeof window.livePlayerMarkEnded === 'function') window.livePlayerMarkEnded();
  }
}, 6000);
</script>
<script src="<?= e(url('assets/js/live-player.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/assets/js/live-player.js') ?>"></script>
<script src="<?= e(url('assets/js/live-layout.js')) ?>?v=<?= (int) @filemtime(__DIR__ . '/assets/js/live-layout.js') ?>"></script>
<script src="<?= e(url('assets/js/live-board.js')) ?>?v=<?= (int) @filemtime(__DIR__ . '/assets/js/live-board.js') ?>"></script>
<?php if ($canPublish): ?>
<script src="<?= e(url('assets/js/live-publisher.js')) ?>?v=<?= (int) @filemtime(__DIR__ . '/assets/js/live-publisher.js') ?>"></script>
<script src="<?= e(url('assets/js/live-record.js')) ?>?v=<?= (int) @filemtime(__DIR__ . '/assets/js/live-record.js') ?>"></script>
<?php endif; ?>
<script src="<?= e(url('assets/js/live-pause.js')) ?>?v=<?= (int) @filemtime(__DIR__ . '/assets/js/live-pause.js') ?>"></script>
</body>
</html>
