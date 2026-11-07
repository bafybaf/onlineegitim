import ZoomMtgEmbedded from 'https://source.zoom.us/6.10.0/esm/component-view.es.min.js';

const cfg = window.LIVE_ZOOM || {};
const root = document.getElementById('live-zoom-root');
const status = document.getElementById('live-zoom-status');

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
  var w = root.clientWidth || 0;
  var h = root.clientHeight || 0;
  var p = root.parentElement;
  if (p) {
    w = Math.max(w, p.clientWidth || 0);
    h = Math.max(h, p.clientHeight || 0);
  }
  w = Math.max(640, Math.min(1440, w || 960));
  h = Math.max(360, Math.min(810, h || 540));
  return { width: w, height: h };
}

function waitLayout() {
  return new Promise(function (resolve) {
    var t0 = Date.now();
    function tick() {
      if ((root.clientWidth >= 480 && root.clientHeight >= 270) || Date.now() - t0 > 4000) {
        resolve();
        return;
      }
      requestAnimationFrame(tick);
    }
    tick();
  });
}

async function startMedia(client) {
  try {
    if (typeof client.mute === 'function') await client.mute(false);
  } catch (err) {}
  if (!cfg.publish) return;
  try {
    if (typeof client.startVideo === 'function') await client.startVideo();
  } catch (err) {
    say('Kamera kapalı. Sol üstteki kamera düğmesine basın.');
  }
}

function fitVideo(client) {
  if (!client || typeof client.updateVideoOptions !== 'function') return;
  var size = videoSize();
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
        isResizable: false,
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
  fitVideo(client);
  requestAnimationFrame(function () { fitVideo(client); });
  if (typeof ResizeObserver === 'function') {
    new ResizeObserver(function () { fitVideo(client); }).observe(root);
  }
  window.addEventListener('resize', function () { fitVideo(client); });
}

start().catch(function (err) {
  say(errText(err) || 'Zoom açılamadı. Yeni sekmeden deneyin.');
});
