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
  return err.reason || err.message || err.errorMessage || '';
}

async function start() {
  if (!cfg.api || !cfg.roomId || !root) return;
  say('Zoom açılıyor… kamera bu sayfada belirecek.');
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
  const w = Math.max(480, root.clientWidth || 800);
  const h = Math.max(320, root.clientHeight || 450);
  await client.init({
    zoomAppRoot: root,
    language: 'tr-TR',
    customize: {
      video: {
        isResizable: true,
        viewSizes: { default: { width: w, height: h } }
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
  await client.join(join);
  say('');
  root.classList.add('is-on');
  const panel = document.querySelector('.live-zoom-panel');
  if (panel) panel.classList.add('is-live');
}

start().catch(function (err) {
  say(errText(err) || 'Zoom açılamadı. Yeni sekmeden deneyin.');
});
