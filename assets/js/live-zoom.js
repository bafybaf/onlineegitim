import ZoomMtgEmbedded from 'https://source.zoom.us/6.10.0/esm/component-view.es.min.js';

const cfg = window.LIVE_ZOOM || {};
const root = document.getElementById('live-zoom-root');
const status = document.getElementById('live-zoom-status');
let lastSize = '';
let mediaOnce = false;

function say(t) {
  if (!status) return;
  status.textContent = t || '';
  status.hidden = !t;
}

function body(obj) {
  return Object.keys(obj).map(function (k) {
    return encodeURIComponent(k) + '=' + encodeURIComponent(obj[k]);
  }).join('&');
}

function errText(err) {
  if (!err) return '';
  if (typeof err === 'string') return err;
  return err.reason || err.message || err.errorMessage || err.errorCode || '';
}

function videoSize() {
  var box = root.getBoundingClientRect();
  var w = Math.round(box.width) || root.clientWidth || 0;
  var h = Math.round(box.height) || root.clientHeight || 0;
  var p = root.parentElement;
  if (p) {
    var pb = p.getBoundingClientRect();
    w = Math.max(w, Math.round(pb.width) || p.clientWidth || 0);
    h = Math.max(h, Math.round(pb.height) || p.clientHeight || 0);
  }
  w = Math.max(240, Math.min(1440, w || 960));
  h = Math.max(135, Math.min(810, h || 540));
  return { width: w, height: h };
}

function waitLayout() {
  return new Promise(function (resolve) {
    var t0 = Date.now();
    function tick() {
      var box = root.getBoundingClientRect();
      if ((box.width >= 480 && box.height >= 270) || Date.now() - t0 > 4000) {
        resolve();
        return;
      }
      requestAnimationFrame(tick);
    }
    tick();
  });
}

async function startMedia(client) {
  if (mediaOnce) return;
  try {
    if (typeof client.mute === 'function') await client.mute(false);
  } catch (err) {}
  if (!cfg.publish) {
    mediaOnce = true;
    return;
  }
  try {
    if (typeof client.startVideo === 'function') await client.startVideo();
    mediaOnce = true;
  } catch (err) {
    say('Kamera kapalı. Sol üstteki kamera düğmesine basın.');
  }
}

function fitVideo(client) {
  if (!client || typeof client.updateVideoOptions !== 'function') return;
  var size = videoSize();
  var key = size.width + 'x' + size.height;
  if (key === lastSize) return;
  lastSize = key;
  try {
    client.updateVideoOptions({
      viewSizes: {
        default: size,
        ribbon: { width: 300, height: Math.min(700, size.height) }
      }
    });
  } catch (err) {}
}

async function start() {
  if (!cfg.api || !cfg.roomId || !root) return;
  say('Zoom açılıyor… kamera bu sayfada belirecek.');
  await waitLayout();
  const r = await fetch(cfg.api, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: body({ action: 'zoom_join', id: String(cfg.roomId) })
  });
  const j = await r.json();
  if (!j || !j.ok) {
    say((j && j.error) || 'Zoom bağlantısı alınamadı.');
    return;
  }
  const client = ZoomMtgEmbedded.createClient();
  const size = videoSize();
  await client.init({
    zoomAppRoot: root,
    language: 'tr-TR',
    patchJsMedia: true,
    leaveOnPageUnload: true,
    customize: {
      video: {
        isResizable: true,
        defaultViewType: 'speaker',
        viewSizes: {
          default: size,
          ribbon: { width: 300, height: Math.min(700, size.height) }
        },
        popper: {
          disableDraggable: true
        }
      }
    }
  });
  const join = {
    signature: j.signature,
    meetingNumber: String(j.meetingNumber),
    password: j.password || '',
    userName: j.userName || 'Katılımcı'
  };
  if (j.userEmail) join.userEmail = j.userEmail;
  if (j.zak) join.zak = j.zak;
  if (typeof client.on === 'function') {
    client.on('connection-change', function (payload) {
      if (payload && payload.state === 'Connected') {
        startMedia(client);
        lastSize = '';
        fitVideo(client);
      }
    });
  }
  await client.join(join);
  say('');
  root.classList.add('is-on');
  const panel = document.querySelector('.live-zoom-panel');
  if (panel) panel.classList.add('is-live');
  await startMedia(client);
  lastSize = '';
  fitVideo(client);
  var fitTimer = 0;
  function scheduleFit() {
    clearTimeout(fitTimer);
    fitTimer = setTimeout(function () { fitVideo(client); }, 200);
  }
  if (typeof ResizeObserver === 'function') {
    new ResizeObserver(scheduleFit).observe(root);
  }
  window.addEventListener('resize', scheduleFit);
}

start().catch(function (err) {
  say(errText(err) || 'Zoom açılamadı. Yeni sekmeden deneyin.');
});
