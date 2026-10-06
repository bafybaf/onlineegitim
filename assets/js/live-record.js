(function () {
  const cfg = window.LIVE_RECORD || {};
  if (!cfg.roomId) return;

  const W = 1920;
  const api = cfg.url || '';
  const video = document.getElementById('live-video');
  const bg = document.getElementById('board-bg');
  const draw = document.getElementById('board-draw');
  const stage = document.getElementById('board-stage');
  const screenVid = document.getElementById('board-screen');
  const startBtn = document.getElementById('live-rec-start');
  const countBox = document.getElementById('live-rec-count');
  const countNum = document.getElementById('live-rec-num');

  let sideW = 400;
  let H = 810;
  const canvas = document.createElement('canvas');
  canvas.width = W;
  canvas.height = H;
  canvas.setAttribute('aria-hidden', 'true');
  canvas.style.cssText = 'position:fixed;left:-9999px;top:0;width:16px;height:16px;opacity:0;pointer-events:none';
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
  let uploadedChunks = 0;
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
    var main = document.querySelector('.live-main');
    var side = document.querySelector('.live-side');
    if (main && side && main.clientWidth > 100 && side.clientWidth > 10) {
      sideW = Math.round(W * side.clientWidth / main.clientWidth);
    }
    var boardW = W - sideW;
    if (stage && stage.clientWidth > 2 && stage.clientHeight > 2) {
      var aspect = stage.clientWidth / stage.clientHeight;
      var next = Math.round(boardW / aspect);
      if (next % 2) next += 1;
      H = Math.max(480, next);
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
    ctx.imageSmoothingQuality = 'high';
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
    if (finishing || done || !armed || recPaused) return;
    var boardW = W - sideW;
    ctx.fillStyle = '#0b1020';
    ctx.fillRect(0, 0, W, H);

    ctx.save();
    ctx.beginPath(); ctx.rect(0, 0, boardW, H); ctx.clip();
    paintBoard(0, 0, boardW, H);
    ctx.restore();

    var pad = 14;
    var ovalW = Math.max(180, sideW - pad * 2);
    var ovalH = Math.round(ovalW * 9 / 16);
    var ox = boardW + pad;
    var oy = pad;
    var camBlock = oy + ovalH + pad;

    ctx.fillStyle = '#111111';
    ctx.fillRect(boardW, 0, sideW, H);

    ctx.save();
    roundRectPath(ox, oy, ovalW, ovalH, 16);
    ctx.fillStyle = '#000';
    ctx.fill();
    ctx.clip();
    drawCover(video, ox, oy, ovalW, ovalH);
    ctx.restore();

    ctx.fillStyle = '#ffffff';
    ctx.font = '600 18px Nunito, sans-serif';
    ctx.fillText('Sohbet', boardW + 16, camBlock + 26);
    var log = document.getElementById('chat-log');
    if (log) {
      ctx.font = '15px Nunito, sans-serif';
      ctx.fillStyle = '#e5e7eb';
      var lines = Array.from(log.querySelectorAll('p')).slice(-25);
      var maxCh = Math.floor((sideW - 32) / 8);
      var y = camBlock + 50;
      lines.forEach(function (p) {
        var t = (p.textContent || '').replace(/\s+/g, ' ').trim();
        if (!t || y > H - 10) return;
        ctx.fillText(t.length > maxCh ? t.slice(0, maxCh - 1) + '…' : t, boardW + 16, y);
        y += 22;
      });
    }

    ctx.fillStyle = '#ffffff';
    ctx.font = '700 16px Nunito, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('onlineilahiyat.com', boardW + sideW / 2, H - 16);
    ctx.textAlign = 'left';
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
      var src = 'setInterval(function(){postMessage(1);},66);';
      paintWorker = new Worker(URL.createObjectURL(new Blob([src], { type: 'text/javascript' })));
      paintWorker.onmessage = function () { paint(); };
    } catch (e) { paintTimer = setInterval(paint, 66); }
    if (screenVid && typeof screenVid.requestVideoFrameCallback === 'function') {
      var onFrame = function () {
        if (!armed || finishing || done) return;
        paint();
        try { screenVid.requestVideoFrameCallback(onFrame); } catch (err) {}
      };
      try { screenVid.requestVideoFrameCallback(onFrame); } catch (e) {}
    }
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
    recStream = canvas.captureStream(15);
    if (audioClone && recStream.getAudioTracks().length === 0) recStream.addTrack(audioClone);
    var hasAudio = recStream.getAudioTracks().length > 0;
    var types = mimeList(hasAudio);
    for (var i = 0; i < types.length && !recorder; i++) {
      var opts = { videoBitsPerSecond: 2800000 };
      if (types[i]) opts.mimeType = types[i];
      if (hasAudio) opts.audioBitsPerSecond = 128000;
      try { recorder = new MediaRecorder(recStream, opts); } catch (e) { recorder = null; }
    }
    if (!recorder) {
      try { recorder = new MediaRecorder(recStream); } catch (fatal) { recorder = null; return false; }
    }
    recorder.ondataavailable = function (ev) { if (ev.data && ev.data.size) upload(ev.data); };
    try { recorder.start(2000); } catch (e) { recorder = null; return false; }
    if (recPaused) { try { recorder.pause(); } catch (e) {} }
    if (!startedMs) startedMs = Date.now();
    return true;
  }

  function upload(blob) {
    if (!blob || blob.size < 20 || done) return queue;
    var n = seq;
    seq += 1;
    queue = queue.then(function () {
      var tries = 0;
      function once() {
        var fd = new FormData();
        fd.append('action', 'record_chunk');
        fd.append('id', String(cfg.roomId));
        fd.append('seq', String(n));
        fd.append('chunk', blob, 'c.webm');
        if (csrfToken) fd.append('_csrf', csrfToken);
        return fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (res) {
          if (!res.ok) throw new Error('chunk ' + res.status);
          uploadedChunks += 1;
          return n;
        }).catch(function (err) {
          tries += 1;
          if (tries < 4) return new Promise(function (r) { setTimeout(r, 600 * tries); }).then(once);
          throw err;
        });
      }
      return once().catch(function () { return n; });
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
    var n = 10;
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
    }).then(function (res) { return res && res.ok; }).catch(function () { return false; });
  }

  async function finish() {
    if (finishing || done) return;
    cancelCount();
    finishing = true;
    if (mixWatch) { clearInterval(mixWatch); mixWatch = 0; }
    stopPaintLoop();
    if (recorder && recorder.state !== 'inactive') {
      await new Promise(function (resolve) {
        var settled = false;
        var once = function () { if (!settled) { settled = true; resolve(); } };
        recorder.onstop = once;
        recorder.addEventListener('dataavailable', function () { setTimeout(once, 50); }, { once: true });
        try { recorder.requestData(); } catch (e) {}
        try { recorder.stop(); } catch (e) { once(); }
        setTimeout(once, 5000);
      });
    }
    if (recorder) {
      await waitAtMost(queue, 60000);
      await waitAtMost(postDone(false), 20000);
    }
    done = true;
    armed = false;
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
      if (btn) { btn.disabled = true; btn.textContent = 'Kaydediliyor…'; }
      finish().finally(function () {
        f.dataset.recOk = '1';
        var mins = f.querySelector('input[name="mins"]');
        if (mins) mins.value = String(minsNow());
        else { var h = document.createElement('input'); h.type = 'hidden'; h.name = 'mins'; h.value = String(minsNow()); f.appendChild(h); }
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
