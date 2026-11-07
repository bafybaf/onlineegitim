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
  return String(err.reason || err.message || err.errorMessage || err.errorCode || '');
}

function isTokenStartError(err) {
  var t = (errText(err) + ' ' + (err && err.type ? err.type : '')).toLowerCase();
  var code = err && (err.errorCode || err.errorcode);
  return /via tokens|not support start/.test(t) || code === 3265 || code === 3136;
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

async function fetchJoin(guest) {
  const r = await fetch(cfg.api, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: body({
      action: 'zoom_join',
      id: String(cfg.roomId),
      guest: guest ? '1' : '0'
    })
  });
  const j = await r.json();
  if (!j || !j.ok) {
    throw new Error((j && j.error) || 'Zoom bağlantısı alınamadı.');
  }
  return j;
}

function joinPayload(j) {
  const join = {
    signature: j.signature,
    meetingNumber: String(j.meetingNumber),
    password: j.password || '',
    userName: j.userName || 'Katılımcı'
  };
  if (j.sdkKey) join.sdkKey = j.sdkKey;
  if (j.userEmail) join.userEmail = j.userEmail;
  if (j.zak) join.zak = j.zak;
  return join;
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

async function makeClient() {
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
  return client;
}

function recOn(payload) {
  var st = '';
  if (payload == null) return false;
  if (typeof payload === 'string' || typeof payload === 'number') st = String(payload);
  else st = String(payload.recordingStatus || payload.state || payload.status || '');
  st = st.toLowerCase();
  return st === 'recording' || st === '1' || st === 'started' || st === 'start';
}

function recCall(client, start) {
  var fn = start
    ? (client.startCloudRecording || client.startRecording)
    : (client.stopCloudRecording || client.stopRecording);
  if (typeof fn !== 'function') {
    return Promise.reject(new Error('Zoom bulut kaydı bu hesapta yok.'));
  }
  return fn.call(client);
}

function wireRecord(client) {
  var btn = document.getElementById('live-zoom-rec');
  if (!btn || !cfg.publish) return;
  var on = false;
  function paint() {
    btn.hidden = false;
    btn.classList.toggle('is-hot', on);
    btn.textContent = on ? '● Kayıt' : 'Kayıt';
  }
  paint();
  if (typeof client.on === 'function') {
    client.on('recording-change', function (payload) {
      on = recOn(payload);
      paint();
    });
  }
  btn.addEventListener('click', function () {
    recCall(client, !on).then(function () {
      on = !on;
      paint();
    }).catch(function (err) {
      say(errText(err) || 'Zoom kaydı açılamadı. Zoom’da Cloud recording açık olmalı.');
    });
  });
}

function bindClient(client) {
  if (typeof client.on === 'function') {
    client.on('connection-change', function (payload) {
      if (payload && payload.state === 'Connected') {
        startMedia(client);
        lastSize = '';
        fitVideo(client);
      }
    });
  }
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

async function start() {
  if (!cfg.api || !cfg.roomId || !root) return;
  say('Zoom açılıyor… kamera bu sayfada belirecek.');
  await waitLayout();
  let j = await fetchJoin(false);
  let client = await makeClient();
  bindClient(client);
  try {
    await client.join(joinPayload(j));
  } catch (err) {
    if (!j.host || !isTokenStartError(err)) {
      throw err;
    }
    say('Toplantı host olarak açılamadı, katılımcı olarak deneniyor…');
    j = await fetchJoin(true);
    root.innerHTML = '';
    client = await makeClient();
    bindClient(client);
    await client.join(joinPayload(j));
  }
  say('');
  root.classList.add('is-on');
  const panel = document.querySelector('.live-zoom-panel');
  if (panel) panel.classList.add('is-live');
  wireRecord(client);
  await startMedia(client);
}

start().catch(function (err) {
  var t = errText(err);
  if (isTokenStartError(err)) {
    t = 'Zoom toplantısı bu hesapla host olarak açılamadı. Yeni bir test yayını başlatın.';
  }
  say(t || 'Zoom açılamadı. Yeni sekmeden deneyin.');
});
