(function () {
  const cfg = window.LIVE_RECORD || {};
  if (!cfg.roomId) return;

  const W = 1920;
  const sideW = 500;
  let H = 810;
  const canvas = document.createElement('canvas');
  canvas.width = W;
  canvas.height = H;
  canvas.setAttribute('aria-hidden', 'true');
  canvas.style.cssText = 'position:fixed;left:-9999px;top:0;width:16px;height:16px;opacity:0;pointer-events:none';
  document.body.appendChild(canvas);
  const ctx = canvas.getContext('2d', { alpha: false });
  const video = document.getElementById('live-video');
  const bg = document.getElementById('board-bg');
  const draw = document.getElementById('board-draw');
  const stage = document.getElementById('board-stage');
  const screenVid = document.getElementById('board-screen');
  const api = cfg.url || '';
  const startBtn = document.getElementById('live-rec-start');
  const countBox = document.getElementById('live-rec-count');
  const countNum = document.getElementById('live-rec-num');

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
  let audioClone = null;
  let pendingMedia = null;
  let recPaused = false;
  let mixCtx = null;
  let mixDest = null;
  let mixHooked = {};

  function mime() {
    const types = [
      'video/webm;codecs=vp8,opus',
      'video/webm;codecs=vp9,opus',
      'video/webm;codecs=vp8',
      'video/webm'
    ];
    for (let i = 0; i < types.length; i++) {
      if (window.MediaRecorder && MediaRecorder.isTypeSupported(types[i])) {
        return types[i];
      }
    }
    return 'video/webm';
  }

  function roundRectPath(x, y, w, h, r) {
    r = Math.max(0, Math.min(r, w / 2, h / 2));
    ctx.beginPath();
    if (typeof ctx.roundRect === 'function') {
      ctx.roundRect(x, y, w, h, r);
      return;
    }
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
  }

  function destRect(sw, sh, x, y, w, h) {
    if (sw < 2 || sh < 2) {
      return { x: x, y: y, w: w, h: h };
    }
    const s = Math.min(w / sw, h / sh);
    const dw = sw * s;
    const dh = sh * s;
    return { x: x + (w - dw) / 2, y: y + (h - dh) / 2, w: dw, h: dh };
  }

  function destCover(sw, sh, x, y, w, h) {
    if (sw < 2 || sh < 2) {
      return { x: x, y: y, w: w, h: h };
    }
    const s = Math.max(w / sw, h / sh);
    const dw = sw * s;
    const dh = sh * s;
    return { x: x + (w - dw) / 2, y: y + (h - dh) / 2, w: dw, h: dh };
  }

  function drawContain(src, x, y, w, h) {
    if (!src) return false;
    const vw = src.videoWidth || src.width || 0;
    const vh = src.videoHeight || src.height || 0;
    if (vw < 2 || vh < 2) return false;
    const box = destRect(vw, vh, x, y, w, h);
    try {
      ctx.drawImage(src, box.x, box.y, box.w, box.h);
      return true;
    } catch (e) {
      return false;
    }
  }

  function sourceAspect() {
    if (stage) {
      const w = stage.clientWidth;
      const h = stage.clientHeight;
      if (w > 2 && h > 2) return w / h;
    }
    if (bg && bg.width > 2 && bg.height > 2) {
      return bg.width / bg.height;
    }
    return 16 / 9;
  }

  function lockCanvasSize() {
    const boardW = W - sideW;
    let next = Math.round(boardW / sourceAspect());
    if (next % 2) next += 1;
    H = Math.max(480, next);
    canvas.width = W;
    canvas.height = H;
  }

  function blitFit(src, x, y, w, h) {
    if (!src) return false;
    const sw = src.videoWidth || src.width || 0;
    const sh = src.videoHeight || src.height || 0;
    if (sw < 2 || sh < 2) return false;
    const box = destRect(sw, sh, x, y, w, h);
    try {
      ctx.drawImage(src, box.x, box.y, box.w, box.h);
      return true;
    } catch (e) {
      return false;
    }
  }

  function blitFill(src, x, y, w, h) {
    if (!src) return false;
    const sw = src.videoWidth || src.width || 0;
    const sh = src.videoHeight || src.height || 0;
    if (sw < 2 || sh < 2) return false;
    try {
      ctx.drawImage(src, x, y, w, h);
      return true;
    } catch (e) {
      return false;
    }
  }

  function paintStage(x, y, w, h, sharing) {
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';
    if (sharing) {
      ctx.fillStyle = '#0b1020';
      ctx.fillRect(x, y, w, h);
      blitFit(screenVid, x, y, w, h);
    } else {
      ctx.fillStyle = '#e7e5e4';
      ctx.fillRect(x, y, w, h);
      blitFit(bg, x, y, w, h);
    }
    blitFill(draw, x, y, w, h);
  }

  function drawCover(src, x, y, w, h) {
    if (!src) return false;
    const vw = src.videoWidth || src.width || 0;
    const vh = src.videoHeight || src.height || 0;
    if (vw < 2 || vh < 2) return false;
    const box = destCover(vw, vh, x, y, w, h);
    try {
      ctx.drawImage(src, box.x, box.y, box.w, box.h);
      return true;
    } catch (e) {
      return false;
    }
  }

  function paint() {
    if (finishing || done || !armed || recPaused) {
      raf = 0;
      return;
    }
    const full = !!document.body.classList.contains('is-board-full');
    const sharing = !!(stage && stage.classList.contains('is-screen') && screenVid && (screenVid.videoWidth || 0) > 1);
    const boardW = full ? W : (W - sideW);
    const boardH = H;
    const boardX = 0;
    const boardY = 0;
    ctx.fillStyle = '#0b1020';
    ctx.fillRect(0, 0, W, H);
    ctx.save();
    ctx.beginPath();
    ctx.rect(boardX, boardY, boardW, boardH);
    ctx.clip();
    paintStage(boardX, boardY, boardW, boardH, sharing);
    ctx.restore();
    const pip = document.getElementById('live-cam-pip');
    const sideNow = W - boardW;
    let ox;
    let oy;
    let ovalW;
    let ovalH;
    let camBlock = 0;
    if (full && pip && stage) {
      const st = stage.getBoundingClientRect();
      const pr = pip.getBoundingClientRect();
      const rw = Math.max(1, st.width);
      const rh = Math.max(1, st.height);
      ox = boardX + ((pr.left - st.left) / rw) * boardW;
      oy = boardY + ((pr.top - st.top) / rh) * boardH;
      ovalW = (pr.width / rw) * boardW;
      ovalH = (pr.height / rh) * boardH;
    } else {
      const camPad = 18;
      ovalW = Math.max(220, sideNow - camPad * 2);
      ovalH = Math.round(ovalW * 9 / 16);
      ox = boardW + camPad;
      oy = camPad;
      camBlock = oy + ovalH + camPad;
      ctx.fillStyle = '#10182d';
      ctx.fillRect(boardW, 0, sideNow, H);
    }
    ctx.save();
    roundRectPath(ox, oy, ovalW, ovalH, 20);
    ctx.fillStyle = '#000';
    ctx.fill();
    ctx.clip();
    drawCover(video, ox, oy, ovalW, ovalH);
    ctx.restore();
    if (!full) {
      ctx.fillStyle = '#ffffff';
      ctx.font = '600 20px Nunito, sans-serif';
      ctx.fillText('Sohbet', boardW + 20, camBlock + 30);
      const log = document.getElementById('chat-log');
      if (log) {
        ctx.font = '16px Nunito, sans-serif';
        ctx.fillStyle = '#e5e7eb';
        const lines = Array.from(log.querySelectorAll('p')).slice(-20);
        let y = camBlock + 58;
        lines.forEach((p) => {
          const t = (p.textContent || '').replace(/\s+/g, ' ').trim();
          if (!t) return;
          const cut = t.length > 52 ? t.slice(0, 51) + '…' : t;
          ctx.fillText(cut, boardW + 20, y);
          y += 24;
        });
      }
    }
    raf = requestAnimationFrame(paint);
  }

  function mediaFrom(input) {
    if (input instanceof MediaStream) return input;
    if (video && video.srcObject instanceof MediaStream) return video.srcObject;
    return null;
  }

  function ensureMixer() {
    if (mixDest && audioClone && audioClone.readyState === 'live') {
      return audioClone;
    }
    const AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) return null;
    if (!mixCtx) {
      mixCtx = new AC();
    }
    if (mixCtx.state === 'suspended') {
      mixCtx.resume().catch(() => {});
    }
    if (!mixDest) {
      mixDest = mixCtx.createMediaStreamDestination();
    }
    audioClone = mixDest.stream.getAudioTracks()[0] || null;
    if (audioClone) audioClone.enabled = true;
    return audioClone;
  }

  function hookAudio(media) {
    if (!media || !mixCtx || !mixDest) return;
    media.getAudioTracks().forEach((t) => {
      if (!t || t.readyState !== 'live' || mixHooked[t.id]) return;
      mixHooked[t.id] = true;
      try {
        const src = mixCtx.createMediaStreamSource(new MediaStream([t]));
        src.connect(mixDest);
      } catch (e) {
        delete mixHooked[t.id];
      }
    });
  }

  function refreshMix(media) {
    if (!ensureMixer()) return;
    hookAudio(mediaFrom(media));
    hookAudio(video && video.srcObject);
    hookAudio(screenVid && screenVid.srcObject);
  }

  function start(media) {
    if (!armed || !window.MediaRecorder || recorder || done) return;
    refreshMix(media);
    recStream = canvas.captureStream(15);
    if (audioClone) {
      recStream.addTrack(audioClone);
    }
    const opts = { mimeType: mime(), videoBitsPerSecond: 2800000, audioBitsPerSecond: 128000 };
    try {
      recorder = new MediaRecorder(recStream, opts);
    } catch (e) {
      try {
        recorder = new MediaRecorder(recStream, { mimeType: mime() });
      } catch (err) {
        try {
          recorder = new MediaRecorder(recStream);
        } catch (fatal) {
          recorder = null;
          return;
        }
      }
    }
    recorder.ondataavailable = (ev) => {
      if (ev.data && ev.data.size) upload(ev.data);
    };
    try {
      recorder.start(4000);
    } catch (e) {
      recorder = null;
      return;
    }
    if (recPaused) {
      try { recorder.pause(); } catch (e) {}
    }
    if (!startedMs) startedMs = Date.now();
  }

  function upload(blob) {
    if (!blob || blob.size < 20 || done) return queue;
    const fd = new FormData();
    fd.append('action', 'record_chunk');
    fd.append('id', String(cfg.roomId));
    fd.append('seq', String(seq));
    fd.append('chunk', blob, 'c.webm');
    const n = seq;
    seq += 1;
    queue = queue.then(() => fetch(api, { method: 'POST', body: fd }).catch(() => null)).then(() => n);
    return queue;
  }

  function minsNow() {
    const from = startedMs || Date.now();
    return Math.max(1, Math.ceil((Date.now() - from) / 60000));
  }

  function hideCount() {
    if (countBox) countBox.classList.remove('is-on');
  }

  function showCount(n) {
    if (countNum) countNum.textContent = String(n);
    if (countBox) countBox.classList.add('is-on');
  }

  function cancelCount() {
    counting = false;
    clearInterval(countTimer);
    countTimer = 0;
    hideCount();
    if (startBtn && !armed && !done) {
      startBtn.disabled = false;
      startBtn.textContent = 'Kayıt';
    }
  }

  function beginRecord() {
    hideCount();
    counting = false;
    armed = true;
    if (!startedMs) startedMs = Date.now();
    if (startBtn) {
      startBtn.disabled = true;
      startBtn.textContent = '● Kayıt';
      startBtn.classList.add('is-hot');
    }
    lockCanvasSize();
    if (!raf) paint();
    start(pendingMedia || (video && video.srcObject));
  }

  function beginCountdown() {
    if (armed || counting || done || finishing) return;
    counting = true;
    let n = 10;
    if (startBtn) {
      startBtn.disabled = true;
      startBtn.textContent = n + '…';
    }
    showCount(n);
    clearInterval(countTimer);
    countTimer = setInterval(() => {
      n -= 1;
      if (n <= 0) {
        clearInterval(countTimer);
        countTimer = 0;
        beginRecord();
        return;
      }
      if (startBtn) startBtn.textContent = n + '…';
      showCount(n);
    }, 1000);
  }

  function waitAtMost(p, ms) {
    return Promise.race([
      Promise.resolve(p).catch(() => null),
      new Promise((resolve) => setTimeout(resolve, ms))
    ]);
  }

  function postDone() {
    const body = 'action=record_done&id=' + encodeURIComponent(cfg.roomId) + '&mins=' + minsNow();
    try {
      if (navigator.sendBeacon) {
        const blob = new Blob([body], { type: 'application/x-www-form-urlencoded' });
        if (navigator.sendBeacon(api, blob)) {
          return Promise.resolve();
        }
      }
    } catch (e) {}
    return fetch(api, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body,
      keepalive: true
    }).catch(() => null);
  }

  async function finish() {
    if (finishing || done) return;
    cancelCount();
    finishing = true;
    cancelAnimationFrame(raf);
    raf = 0;
    if (recorder && recorder.state !== 'inactive') {
      await new Promise((resolve) => {
        let settled = false;
        const once = () => { if (!settled) { settled = true; resolve(); } };
        recorder.onstop = once;
        try { recorder.requestData(); } catch (e) {}
        try { recorder.stop(); } catch (e) { once(); }
        setTimeout(once, 2500);
      });
    }
    if (recorder) {
      await waitAtMost(queue, 12000);
      await waitAtMost(postDone(), 10000);
    }
    done = true;
    armed = false;
    cancelAnimationFrame(raf);
    if (startBtn) {
      startBtn.disabled = true;
      startBtn.textContent = recorder ? 'Bitti' : 'Kayıt';
    }
  }

  window.liveRecordFinish = finish;
  window.liveRecordSetPaused = function (on) {
    recPaused = !!on;
    if (recorder) {
      try {
        if (recPaused && recorder.state === 'recording') {
          recorder.pause();
        }
        if (!recPaused && recorder.state === 'paused') {
          recorder.resume();
        }
      } catch (e) {}
    }
    if (!recPaused && armed && !raf && !finishing && !done) {
      paint();
    }
  };
  window.liveRecordOnCam = function (media) {
    pendingMedia = media;
    if (armed && recorder) {
      refreshMix(media);
      return;
    }
    if (armed) start(media);
  };
  window.liveRecordOnShare = function (media) {
    if (armed) refreshMix(media);
  };

  if (startBtn) {
    startBtn.addEventListener('click', beginCountdown);
  }
  if (video) {
    video.addEventListener('playing', () => {
      pendingMedia = video.srcObject;
      if (armed) start(video.srcObject);
    });
    video.addEventListener('loadeddata', () => {
      pendingMedia = video.srcObject;
      if (armed) start(video.srcObject);
    });
  }

  document.querySelectorAll('form').forEach((f) => {
    const act = f.querySelector('input[name="action"]');
    if (!act || act.value !== 'end') return;
    f.addEventListener('submit', (ev) => {
      if (f.dataset.recOk === '1') return;
      ev.preventDefault();
      const btn = f.querySelector('button');
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Kaydediliyor…';
      }
      finish().finally(() => {
        f.dataset.recOk = '1';
        const mins = f.querySelector('input[name="mins"]');
        if (mins) mins.value = String(minsNow());
        else {
          const h = document.createElement('input');
          h.type = 'hidden';
          h.name = 'mins';
          h.value = String(minsNow());
          f.appendChild(h);
        }
        f.submit();
      });
    });
  });

  const leave = document.getElementById('live-leave');
  if (leave) {
    leave.addEventListener('click', (ev) => {
      if (done && !recorder) return;
      if (!armed && !counting) return;
      ev.preventDefault();
      const href = leave.getAttribute('href');
      finish().finally(() => { location.href = href; });
    });
  }

  window.addEventListener('pagehide', () => {
    if (done || finishing || !recorder) return;
    if (recorder.state === 'recording') {
      try { recorder.requestData(); } catch (e) {}
    }
    postDone();
  });
})();
