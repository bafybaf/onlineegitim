(function () {
  const cfg = window.LIVE_RECORD || {};
  if (!cfg.roomId) return;

  const W = 1280;
  const api = cfg.url || '';
  const video = document.getElementById('live-video');
  const bg = document.getElementById('board-bg');
  const draw = document.getElementById('board-draw');
  const stage = document.getElementById('board-stage');
  const screenVid = document.getElementById('board-screen');
  const startBtn = document.getElementById('live-rec-start');
  const countBox = document.getElementById('live-rec-count');
  const countNum = document.getElementById('live-rec-num');

  let H = 720;
  let layoutLocked = false;
  const canvas = document.createElement('canvas');
  canvas.width = W;
  canvas.height = H;
  canvas.setAttribute('aria-hidden', 'true');
  canvas.style.cssText = 'position:fixed;left:0;top:0;width:320px;height:180px;opacity:0.02;pointer-events:none;z-index:-1';
  document.body.appendChild(canvas);
  const ctx = canvas.getContext('2d', { alpha: false });

  let recorder = null;
  let recStream = null;
  let seq = 0;
  let queue = Promise.resolve();
  let finishing = false;
  let done = false;
  let armed = false;
  let counting = false;
  let countTimer = 0;
  let startedMs = 0;
  let raf = 0;
  let paintTimer = 0;
  let paintWorker = null;
  let audioClone = null;
  let pendingMedia = null;
  let recPaused = false;
  let mixCtx = null;
  let mixDest = null;
  let mixHooked = {};
  let pendingShare = null;
  let mixNodes = [];
  let mixWatch = 0;
  let recPulse = 0;
  let painting = false;
  let uploadedChunks = 0;
  let uploadFailed = 0;
  let queued = 0;
  const seqKey = 'oi-rec-seq-' + String(cfg.roomId);
  try {
    seq = Math.max(0, parseInt(sessionStorage.getItem(seqKey) || '0', 10) || 0);
  } catch (e) {}
  let csrfToken = '';
  try {
    var meta = document.querySelector('meta[name="csrf-token"]');
    csrfToken = (meta && meta.getAttribute('content')) || '';
  } catch (e) {}

  function mimeList(hasAudio) {
    var types = hasAudio
      ? ['video/webm;codecs=vp8,opus', 'video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8', 'video/webm']
      : ['video/webm;codecs=vp8', 'video/webm'];
    return types.filter(function (t) {
      return window.MediaRecorder && MediaRecorder.isTypeSupported(t);
    }).concat(['']);
  }

  function calcLayout() {
    if (layoutLocked) return;
    if (stage && stage.clientWidth > 2 && stage.clientHeight > 2) {
      var aspect = stage.clientWidth / stage.clientHeight;
      var next = Math.round(W / aspect);
      if (next % 2) next += 1;
      H = Math.max(640, Math.min(800, next));
    } else {
      H = 720;
    }
    canvas.width = W;
    canvas.height = H;
  }

  function roundRectPath(x, y, w, h, r) {
    r = Math.max(0, Math.min(r, w / 2, h / 2));
    ctx.beginPath();
    if (typeof ctx.roundRect === 'function') { ctx.roundRect(x, y, w, h, r); return; }
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
  }

  function fitBox(sw, sh, x, y, w, h) {
    if (sw < 2 || sh < 2) return { x: x, y: y, w: w, h: h };
    var s = Math.min(w / sw, h / sh);
    var dw = sw * s, dh = sh * s;
    return { x: x + (w - dw) / 2, y: y + (h - dh) / 2, w: dw, h: dh };
  }

  function coverBox(sw, sh, x, y, w, h) {
    if (sw < 2 || sh < 2) return { x: x, y: y, w: w, h: h };
    var s = Math.max(w / sw, h / sh);
    var dw = sw * s, dh = sh * s;
    return { x: x + (w - dw) / 2, y: y + (h - dh) / 2, w: dw, h: dh };
  }

  function drawFit(src, x, y, w, h) {
    if (!src) return;
    var sw = src.videoWidth || src.width || 0;
    var sh = src.videoHeight || src.height || 0;
    if (sw < 2 || sh < 2) return;
    var b = fitBox(sw, sh, x, y, w, h);
    try { ctx.drawImage(src, b.x, b.y, b.w, b.h); } catch (e) {}
  }

  function drawCover(src, x, y, w, h) {
    if (!src) return;
    var sw = src.videoWidth || src.width || 0;
    var sh = src.videoHeight || src.height || 0;
    if (sw < 2 || sh < 2) return;
    var b = coverBox(sw, sh, x, y, w, h);
    ctx.save();
    ctx.beginPath(); ctx.rect(x, y, w, h); ctx.clip();
    try { ctx.drawImage(src, b.x, b.y, b.w, b.h); } catch (e) {}
    ctx.restore();
  }

  function drawStretch(src, x, y, w, h) {
    if (!src) return;
    var sw = src.videoWidth || src.width || 0;
    var sh = src.videoHeight || src.height || 0;
    if (sw < 2 || sh < 2) return;
    try { ctx.drawImage(src, x, y, w, h); } catch (e) {}
  }

  function paintBoard(x, y, w, h) {
    var sharing = !!(stage && stage.classList.contains('is-screen') && screenVid && (screenVid.videoWidth || 0) > 1);
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'medium';
    if (sharing) {
      ctx.fillStyle = '#0b1020';
      ctx.fillRect(x, y, w, h);
      drawFit(screenVid, x, y, w, h);
    } else {
      ctx.fillStyle = '#e7e5e4';
      ctx.fillRect(x, y, w, h);
      drawFit(bg, x, y, w, h);
    }
    drawFit(draw, x, y, w, h);
  }

  function paint() {
    if (painting || finishing || done || !armed || recPaused) return;
    painting = true;
    try {
      paintBoard(0, 0, W, H);
      var pipW = Math.round(W * 0.22);
      var pipH = Math.round(pipW * 9 / 16);
      var ox = W - pipW - 18;
      var oy = 18;
      ctx.save();
      roundRectPath(ox, oy, pipW, pipH, 14);
      ctx.fillStyle = '#000';
      ctx.fill();
      ctx.clip();
      drawCover(video, ox, oy, pipW, pipH);
      ctx.restore();
    } finally {
      painting = false;
    }
  }

  function stopPaintLoop() {
    if (raf) { cancelAnimationFrame(raf); raf = 0; }
    if (paintTimer) { clearInterval(paintTimer); paintTimer = 0; }
    if (paintWorker) { try { paintWorker.terminate(); } catch (e) {} paintWorker = null; }
  }

  function startPaintLoop() {
    stopPaintLoop();
    paint();
    try {
      var src = 'setInterval(function(){postMessage(1);},125);';
      paintWorker = new Worker(URL.createObjectURL(new Blob([src], { type: 'text/javascript' })));
      paintWorker.onmessage = function () { paint(); };
    } catch (e) { paintTimer = setInterval(paint, 125); }
  }

  function ensureMixer() {
    var AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) return null;
    if (!mixCtx) mixCtx = new AC();
    if (mixCtx.state === 'suspended') mixCtx.resume().catch(function () {});
    if (!mixDest) mixDest = mixCtx.createMediaStreamDestination();
    audioClone = mixDest.stream.getAudioTracks()[0] || null;
    if (audioClone) audioClone.enabled = true;
    return audioClone;
  }

  function hookAudio(media, gainVal) {
    if (!media || !mixCtx || !mixDest) return;
    media.getAudioTracks().forEach(function (t) {
      if (!t || t.readyState !== 'live' || mixHooked[t.id]) return;
      mixHooked[t.id] = true;
      try {
        t.enabled = true;
        var cloned = typeof t.clone === 'function' ? t.clone() : t;
        cloned.enabled = true;
        var s = mixCtx.createMediaStreamSource(new MediaStream([cloned]));
        var g = mixCtx.createGain();
        g.gain.value = gainVal || 1;
        s.connect(g);
        g.connect(mixDest);
        mixNodes.push(s, g, cloned);
      } catch (e) { delete mixHooked[t.id]; }
    });
  }

  function refreshMix(media) {
    if (!ensureMixer()) return;
    if (media instanceof MediaStream) hookAudio(media, 1);
    if (pendingShare) hookAudio(pendingShare, 1.5);
    if (video && video.srcObject) hookAudio(video.srcObject, 1);
    if (screenVid && screenVid.srcObject) hookAudio(screenVid.srcObject, 1.5);
  }

  function watchShareMix() {
    if (mixWatch) return;
    mixWatch = setInterval(function () {
      if (!armed || finishing || done) return;
      refreshMix(pendingMedia);
    }, 1500);
  }

  function startRecorder(media) {
    if (!armed || !window.MediaRecorder || recorder || done) return !!recorder;
    refreshMix(media);
    recStream = canvas.captureStream(8);
    if (audioClone && recStream.getAudioTracks().length === 0) recStream.addTrack(audioClone);
    var hasAudio = recStream.getAudioTracks().length > 0;
    var types = mimeList(hasAudio);
    for (var i = 0; i < types.length && !recorder; i++) {
      var opts = { videoBitsPerSecond: 1600000 };
      if (types[i]) opts.mimeType = types[i];
      if (hasAudio) opts.audioBitsPerSecond = 128000;
      try { recorder = new MediaRecorder(recStream, opts); } catch (e) { recorder = null; }
    }
    if (!recorder) {
      try { recorder = new MediaRecorder(recStream); } catch (fatal) { recorder = null; return false; }
    }
    recorder.ondataavailable = function (ev) { if (ev.data && ev.data.size > 8) upload(ev.data); };
    try { recorder.start(2000); } catch (e) { recorder = null; return false; }
    if (recPulse) clearInterval(recPulse);
    recPulse = 0;
    if (recPaused) { try { recorder.pause(); } catch (e) {} }
    if (!startedMs) startedMs = Date.now();
    return true;
  }

  function postChunk(fd) {
    return new Promise(function (resolve, reject) {
      var xhr = new XMLHttpRequest();
      xhr.open('POST', api);
      xhr.withCredentials = true;
      if (csrfToken) xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
      xhr.timeout = 12000;
      xhr.onload = function () {
        if (xhr.status >= 200 && xhr.status < 300) resolve(xhr);
        else reject(new Error('chunk ' + xhr.status));
      };
      xhr.onerror = function () { reject(new Error('net')); };
      xhr.ontimeout = function () { reject(new Error('timeout')); };
      xhr.send(fd);
    });
  }

  function upload(blob) {
    if (!blob || blob.size < 8) return queue;
    if (done && !finishing) return queue;
    var n = seq;
    seq += 1;
    try { sessionStorage.setItem(seqKey, String(seq)); } catch (e) {}
    queued += 1;
    queue = queue.then(function () {
      var tries = 0;
      function once() {
        var fd = new FormData();
        fd.append('action', 'record_chunk');
        fd.append('id', String(cfg.roomId));
        fd.append('seq', String(n));
        fd.append('resume', n > 0 ? '1' : '0');
        fd.append('chunk', blob, 'c.webm');
        if (csrfToken) fd.append('_csrf', csrfToken);
        return postChunk(fd).then(function () {
          uploadedChunks += 1;
          return n;
        }).catch(function (err) {
          tries += 1;
          if (tries < 3) return new Promise(function (r) { setTimeout(r, 700 * tries); }).then(once);
          uploadFailed += 1;
          throw err;
        });
      }
      return once().catch(function () { return n; }).then(function (v) {
        queued = Math.max(0, queued - 1);
        return v;
      });
    });
    return queue;
  }

  function minsNow() {
    var from = startedMs || Date.now();
    return Math.max(1, Math.ceil((Date.now() - from) / 60000));
  }

  function hideCount() { if (countBox) countBox.classList.remove('is-on'); }
  function showCount(n) {
    if (countNum) countNum.textContent = String(n);
    if (countBox) countBox.classList.add('is-on');
  }

  function cancelCount() {
    counting = false;
    clearInterval(countTimer); countTimer = 0;
    hideCount();
    if (startBtn && !armed && !done) { startBtn.disabled = false; startBtn.textContent = 'Kayıt'; }
  }

  function beginRecord() {
    hideCount();
    counting = false;
    armed = true;
    if (!startedMs) startedMs = Date.now();
    calcLayout();
    layoutLocked = true;
    startPaintLoop();
    var ok = startRecorder(pendingMedia || (video && video.srcObject));
    watchShareMix();
    if (!ok) {
      armed = false;
      stopPaintLoop();
      if (startBtn) { startBtn.disabled = false; startBtn.textContent = 'Kayıt'; startBtn.classList.remove('is-hot'); }
      window.alert('Kayıt başlatılamadı. Kamerayı açıp tekrar “Kayıt”a basın.');
      return;
    }
    if (startBtn) { startBtn.disabled = true; startBtn.textContent = '● Kayıt'; startBtn.classList.add('is-hot'); }
  }

  function beginCountdown() {
    if (armed || counting || done || finishing) return;
    counting = true;
    var n = 3;
    if (startBtn) { startBtn.disabled = true; startBtn.textContent = n + '…'; }
    showCount(n);
    clearInterval(countTimer);
    countTimer = setInterval(function () {
      n -= 1;
      if (n <= 0) { clearInterval(countTimer); countTimer = 0; beginRecord(); return; }
      if (startBtn) startBtn.textContent = n + '…';
      showCount(n);
    }, 1000);
  }

  function waitAtMost(p, ms) {
    return Promise.race([Promise.resolve(p).catch(function () { return null; }), new Promise(function (r) { setTimeout(r, ms); })]);
  }

  function postDone(useBeacon) {
    var body = 'action=record_done&id=' + encodeURIComponent(cfg.roomId) + '&mins=' + minsNow();
    if (csrfToken) body += '&_csrf=' + encodeURIComponent(csrfToken);
    if (useBeacon) {
      try {
        if (navigator.sendBeacon) {
          var blob = new Blob([body], { type: 'application/x-www-form-urlencoded' });
          if (navigator.sendBeacon(api, blob)) return Promise.resolve();
        }
      } catch (e) {}
    }
    return fetch(api, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body,
      credentials: 'same-origin',
      keepalive: true
    }).then(function (res) { return res.ok ? res.json() : { saved: false }; }).then(function (j) {
      return !!(j && j.saved);
    }).catch(function () { return false; });
  }

  function sleep(ms) {
    return new Promise(function (r) { setTimeout(r, ms); });
  }

  async function finish() {
    if (finishing || done) return;
    if (counting) {
      cancelCount();
      beginRecord();
      await sleep(1200);
    }
    finishing = true;
    if (mixWatch) { clearInterval(mixWatch); mixWatch = 0; }
    if (recPulse) { clearInterval(recPulse); recPulse = 0; }
    if (recorder && recorder.state !== 'inactive') {
      try { recorder.requestData(); } catch (e) {}
      await new Promise(function (resolve) {
        var settled = false;
        var once = function () { if (!settled) { settled = true; resolve(); } };
        recorder.onstop = once;
        recorder.addEventListener('dataavailable', function () { setTimeout(once, 200); }, { once: true });
        try { recorder.requestData(); } catch (e) {}
        try { recorder.stop(); } catch (e) { once(); }
        setTimeout(once, 8000);
      });
      await sleep(400);
    }
    stopPaintLoop();
    if (recorder) {
      await waitAtMost(queue, 25000);
      var ok = await waitAtMost(postDone(false), 8000);
      if (!ok && uploadedChunks > 0) {
        await waitAtMost(postDone(false), 8000);
      }
    }
    done = true;
    armed = false;
    try { sessionStorage.removeItem(seqKey); } catch (e) {}
    stopPaintLoop();
    if (startBtn) { startBtn.disabled = true; startBtn.textContent = recorder && uploadedChunks ? 'Bitti' : 'Kayıt'; }
  }

  window.liveRecordFinish = finish;
  window.liveRecordSetPaused = function (on) {
    recPaused = !!on;
    if (recorder) {
      try {
        if (recPaused && recorder.state === 'recording') recorder.pause();
        if (!recPaused && recorder.state === 'paused') recorder.resume();
      } catch (e) {}
    }
    if (!recPaused && armed && !finishing && !done) startPaintLoop();
  };
  window.liveRecordOnCam = function (media) {
    pendingMedia = media;
    if (armed && recorder) { refreshMix(media); return; }
    if (armed) startRecorder(media);
  };
  window.liveRecordOnShare = function (media) {
    pendingShare = media instanceof MediaStream ? media : null;
    if (pendingShare) {
      pendingShare.addEventListener('addtrack', function () {
        if (armed) refreshMix(pendingShare);
      });
    }
    if (armed) refreshMix(pendingShare || pendingMedia);
  };

  if (startBtn) startBtn.addEventListener('click', beginCountdown);
  if (video) {
    video.addEventListener('playing', function () { pendingMedia = video.srcObject; if (armed) startRecorder(video.srcObject); });
    video.addEventListener('loadeddata', function () { pendingMedia = video.srcObject; if (armed) startRecorder(video.srcObject); });
  }

  document.querySelectorAll('form').forEach(function (f) {
    var act = f.querySelector('input[name="action"]');
    if (!act || act.value !== 'end') return;
    f.addEventListener('submit', function (ev) {
      if (f.dataset.recOk === '1') return;
      ev.preventDefault();
      var btn = f.querySelector('button');
      if (btn) { btn.disabled = true; btn.textContent = queued ? 'Yükleniyor…' : 'Kaydediliyor…'; }
      finish().finally(function () {
        f.dataset.recOk = '1';
        function hid(name, val) {
          var el = f.querySelector('input[name="' + name + '"]');
          if (!el) { el = document.createElement('input'); el.type = 'hidden'; el.name = name; f.appendChild(el); }
          el.value = String(val);
        }
        hid('mins', minsNow());
        hid('rec_chunks', uploadedChunks);
        hid('rec_failed', uploadFailed);
        f.submit();
      });
    });
  });

  var leave = document.getElementById('live-leave');
  if (leave) {
    leave.addEventListener('click', function (ev) {
      if (done && !recorder) return;
      if (!armed && !counting) return;
      ev.preventDefault();
      var href = leave.getAttribute('href');
      finish().finally(function () { location.href = href; });
    });
  }

  window.addEventListener('pagehide', function () {
    if (done || !recorder) return;
    if (recorder.state === 'recording') { try { recorder.requestData(); } catch (e) {} }
    postDone(true);
  });
})();
