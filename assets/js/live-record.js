(function () {
  const cfg = window.LIVE_RECORD || {};
  if (!cfg.roomId) return;

  const W = 1920;
  const H = 1080;
  const sideW = 400;
  const camH = Math.round(sideW * 9 / 16);
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

  function paint() {
    if (finishing || done || !armed || recPaused) {
      raf = 0;
      return;
    }
    const boardW = W - sideW;
    ctx.fillStyle = '#0b1020';
    ctx.fillRect(0, 0, W, H);
    const sharing = !!(stage && stage.classList.contains('is-screen') && screenVid && (screenVid.videoWidth || 0) > 1);
    const sw = (bg && bg.width) ? bg.width : boardW;
    const sh = (bg && bg.height) ? bg.height : H;
    const box = destCover(sw, sh, 0, 0, boardW, H);
    if (sharing) {
      ctx.fillStyle = '#0b1020';
      ctx.fillRect(0, 0, boardW, H);
      drawContain(screenVid, 0, 0, boardW, H);
    } else {
      ctx.save();
      ctx.beginPath();
      ctx.rect(0, 0, boardW, H);
      ctx.clip();
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0, 0, boardW, H);
      if (bg && bg.width) {
        try { ctx.drawImage(bg, box.x, box.y, box.w, box.h); } catch (e) {}
      }
      if (draw && draw.width) {
        try { ctx.drawImage(draw, box.x, box.y, box.w, box.h); } catch (e) {}
      }
      ctx.restore();
    }
    ctx.fillStyle = '#000';
    ctx.fillRect(boardW, 0, sideW, camH);
    drawContain(video, boardW, 0, sideW, camH);
    ctx.fillStyle = '#10182d';
    ctx.fillRect(boardW, camH, sideW, H - camH);
    ctx.fillStyle = '#ffffff';
    ctx.font = '600 18px Nunito, sans-serif';
    ctx.fillText('Sohbet', boardW + 16, camH + 32);
    const log = document.getElementById('chat-log');
    if (log) {
      ctx.font = '15px Nunito, sans-serif';
      ctx.fillStyle = '#e5e7eb';
      const lines = Array.from(log.querySelectorAll('p')).slice(-18);
      let y = camH + 58;
      lines.forEach((p) => {
        const t = (p.textContent || '').replace(/\s+/g, ' ').trim();
        if (!t) return;
        const cut = t.length > 42 ? t.slice(0, 41) + '…' : t;
        ctx.fillText(cut, boardW + 16, y);
        y += 22;
      });
    }
    raf = requestAnimationFrame(paint);
  }

  function mediaFrom(input) {
    if (input instanceof MediaStream) return input;
    if (video && video.srcObject instanceof MediaStream) return video.srcObject;
    return null;
  }

  function cloneMic(media) {
    const src = mediaFrom(media);
    if (!src) return null;
    const mic = src.getAudioTracks().find((t) => t.readyState === 'live');
    if (!mic) return null;
    if (audioClone && audioClone.readyState === 'live') return audioClone;
    try {
      audioClone = mic.clone();
      audioClone.enabled = true;
      return audioClone;
    } catch (e) {
      audioClone = mic;
      return audioClone;
    }
  }

  function start(media) {
    if (!armed || !window.MediaRecorder || recorder || done) return;
    cloneMic(media);
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
      startBtn.textContent = 'Kaydı başlat';
    }
  }

  function beginRecord() {
    hideCount();
    counting = false;
    armed = true;
    if (!startedMs) startedMs = Date.now();
    if (startBtn) {
      startBtn.disabled = true;
      startBtn.textContent = '● Kayıt alınıyor';
      startBtn.classList.add('is-hot');
    }
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
      startBtn.textContent = recorder ? 'Kayıt bitti' : 'Kaydı başlat';
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
    if (armed) start(media);
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
