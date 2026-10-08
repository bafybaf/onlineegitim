(function () {
  const cfg = window.LIVE_PLAYER || {};
  const video = document.getElementById('live-video');
  const overlay = document.getElementById('wait-overlay');
  const protoEl = document.getElementById('live-proto');
  const waitTitle = document.getElementById('wait-title') || document.querySelector('#wait-overlay .font-display');
  const waitDetail = document.getElementById('wait-detail');
  if (!video) return;

  const board = document.getElementById('board-screen');
  const whepUrls = [cfg.whepUrl, cfg.whepUrlAlt].filter(Boolean);
  const hlsUrls = [cfg.hlsUrl, cfg.hlsUrlAlt].filter(Boolean);
  const healthUrl = cfg.healthUrl || '';
  const boardOff = !!(document.querySelector('.live-shell.is-board-off'));
  let screenMode = !cfg.publish && !!window._liveScreenOn;
  let hls = null;
  let pc = null;
  let discTimer = 0;
  let playing = false;
  let playMode = 'none';
  let busy = false;
  let ended = false;
  let lastHint = '';
  let lessonPaused = false;
  let retryTimer = 0;
  let retryMs = 4000;
  let lastLogAt = {};

  function reportLive(kind, message, detail) {
    const api = cfg.api || '';
    const rid = cfg.roomId || 0;
    if (!api || !rid || !kind) return;
    const now = Date.now();
    if (lastLogAt[kind] && now - lastLogAt[kind] < 90000) return;
    lastLogAt[kind] = now;
    const body = new URLSearchParams();
    body.set('action', 'log');
    body.set('room_id', String(rid));
    body.set('kind', kind);
    body.set('message', message || kind);
    body.set('detail', detail || '');
    fetch(api, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() }).catch(() => {});
  }

  function sink() {
    if (!cfg.publish && board && boardOff) {
      return board;
    }
    if (!cfg.publish && board && screenMode) {
      return board;
    }
    return video;
  }

  function parkViewerChrome() {
    if (cfg.publish || !boardOff) return;
    const stage = document.getElementById('board-stage');
    if (!stage) return;
    if (overlay && overlay.parentElement !== stage) {
      stage.appendChild(overlay);
    }
    if (unmuteBtn && unmuteBtn.parentElement !== stage) {
      stage.appendChild(unmuteBtn);
    }
  }

  function applySink() {
    const dest = sink();
    const other = dest === video ? board : video;
    const stream = (video.srcObject instanceof MediaStream && video.srcObject)
      || (board && board.srcObject instanceof MediaStream && board.srcObject)
      || null;
    if (dest && stream && dest.srcObject !== stream) {
      dest.srcObject = stream;
      dest.muted = dest === board ? true : video.muted;
      dest.play().catch(() => {});
    }
    if (other && other !== dest && !(!cfg.publish && boardOff && dest === board)) {
      other.srcObject = null;
      other.removeAttribute('src');
    }
    const stage = document.getElementById('board-stage');
    if (stage) stage.classList.toggle('is-screen', boardOff || !!screenMode);
    parkViewerChrome();
    if (overlay && !boardOff) {
      if (screenMode) {
        if (waitTitle) waitTitle.textContent = 'Ekran paylaşımı';
        if (waitDetail) {
          waitDetail.textContent = 'Görüntü solda';
          waitDetail.hidden = false;
        }
        overlay.classList.remove('is-off');
      } else if (playing && !lessonPaused) {
        overlay.classList.add('is-off');
      }
    }
  }

  window.liveScreenWatch = function (on) {
    if (cfg.publish) return;
    screenMode = !!on;
    if (boardOff) {
      const stage = document.getElementById('board-stage');
      if (stage) stage.classList.toggle('is-screen', true);
      return;
    }
    applySink();
  };

  function showWait(on) {
    if (boardOff && !cfg.publish) {
      if (overlay) overlay.classList.toggle('is-off', !on);
      return;
    }
    if (screenMode) {
      if (overlay) overlay.classList.remove('is-off');
      return;
    }
    if (overlay) overlay.classList.toggle('is-off', !on);
  }
  function setProto(text) {
    lastHint = text || '';
    if (!protoEl) return;
    protoEl.textContent = lastHint;
    protoEl.hidden = !lastHint;
  }
  function setWait(title, detail) {
    if (waitTitle) waitTitle.textContent = title;
    if (waitDetail) {
      waitDetail.textContent = detail || '';
      waitDetail.hidden = !detail;
    }
    showWait(true);
    setProto('');
  }
  function onPlaying() {
    playing = true;
    if (!lessonPaused) {
      showWait(false);
    }
  }
  function onStall() {
    playing = false;
    showWait(true);
  }

  const unmuteBtn = document.getElementById('live-unmute');

  function enableViewerSound() {
    if (cfg.publish) return;
    const dest = sink();
    dest.volume = 1;
    dest.muted = false;
    dest.removeAttribute('muted');
    const playP = dest.play();
    if (playP) {
      playP.catch(() => {
        dest.muted = true;
        if (unmuteBtn) unmuteBtn.hidden = false;
      });
    }
    if (unmuteBtn) unmuteBtn.hidden = !dest.muted;
  }

  if (unmuteBtn) {
    unmuteBtn.addEventListener('click', () => {
      video.muted = false;
      video.removeAttribute('muted');
      video.volume = 1;
      video.play().catch(() => {});
      const screen = document.getElementById('board-screen');
      if (screen) {
        screen.muted = false;
        screen.volume = 1;
        screen.play().catch(() => {});
      }
      unmuteBtn.hidden = true;
    });
  }

  function onDestPlaying() {
    onPlaying();
    enableViewerSound();
  }
  video.addEventListener('playing', onDestPlaying);
  if (board) board.addEventListener('playing', onDestPlaying);
  video.addEventListener('volumechange', () => {
    if (unmuteBtn && !cfg.publish) unmuteBtn.hidden = !video.muted;
  });
  video.addEventListener('waiting', () => { if (!playing) showWait(true); });
  video.addEventListener('error', onStall);

  function stopHls() {
    if (hls) {
      try { hls.destroy(); } catch (e) {}
      hls = null;
    }
    if (!video.srcObject) {
      video.removeAttribute('src');
    }
  }

  function stopWhep() {
    if (discTimer) {
      clearTimeout(discTimer);
      discTimer = 0;
    }
    if (pc) {
      try { pc.close(); } catch (e) {}
      pc = null;
    }
    if (video.srcObject) video.srcObject = null;
    if (board && board.srcObject) board.srcObject = null;
  }

  function waitIceGather(conn, ms) {
    if (conn.iceGatheringState === 'complete') {
      return Promise.resolve();
    }
    return new Promise((resolve) => {
      const t = setTimeout(resolve, ms);
      conn.addEventListener('icegatheringstatechange', () => {
        if (conn.iceGatheringState === 'complete') {
          clearTimeout(t);
          resolve();
        }
      });
    });
  }

  function waitPcReady(conn, ms) {
    if (conn.connectionState === 'connected') {
      return Promise.resolve(true);
    }
    return new Promise((resolve) => {
      const t = setTimeout(() => {
        resolve(conn.connectionState === 'connected');
      }, ms);
      const onChange = () => {
        if (conn.connectionState === 'connected') {
          clearTimeout(t);
          resolve(true);
        } else if (conn.connectionState === 'failed' || conn.connectionState === 'closed') {
          clearTimeout(t);
          resolve(false);
        }
      };
      conn.addEventListener('connectionstatechange', onChange);
    });
  }

  function hasFrames(el) {
    return !!(el && (el.videoWidth > 0 || el.readyState >= 2 || (!el.paused && el.currentTime > 0)));
  }

  function waitForFrames(ms) {
    const els = [sink(), video, board].filter(Boolean);
    if (els.some(hasFrames)) {
      return Promise.resolve(true);
    }
    return new Promise((resolve) => {
      const started = Date.now();
      const tick = setInterval(() => {
        const ok = els.some(hasFrames);
        if (ok || Date.now() - started >= ms) {
          clearInterval(tick);
          resolve(ok);
        }
      }, 200);
    });
  }

  async function pingMtx() {
    const url = healthUrl || hlsUrls[0];
    if (!url) return 'unknown';
    try {
      const ctrl = new AbortController();
      const t = setTimeout(() => ctrl.abort(), 4000);
      await fetch(url, { method: 'GET', cache: 'no-store', mode: 'cors', signal: ctrl.signal });
      clearTimeout(t);
      return 'up';
    } catch (e) {
      return 'down';
    }
  }

  function isLocalDev() {
    const h = location.hostname;
    return h === 'localhost' || h === '127.0.0.1' || h === '::1';
  }

  function waitHint(kind) {
    if (kind === 'down' && isLocalDev()) {
      return ['Sunucu kapalı', ''];
    }
    return ['Hoca bağlanıyor', 'Kamera açılınca görüntü gelir.'];
  }

  function isWaitStatus(code) {
    return code === 404 || code === 409 || code === 410 || code === 423 || code === 425 || code === 503;
  }

  function scheduleRetry(waitStatus) {
    if (ended || playing) return;
    if (retryTimer) clearTimeout(retryTimer);
    if (waitStatus) {
      retryMs = Math.min(12000, Math.max(6000, retryMs + 2000));
    } else {
      retryMs = 4000;
    }
    retryTimer = setTimeout(() => {
      retryTimer = 0;
      tryWhepOrHls();
    }, retryMs);
  }

  async function startWhep(url) {
    if (!url || typeof RTCPeerConnection === 'undefined') {
      return 'unsupported';
    }
    if (pc && ['new', 'connecting', 'connected'].indexOf(pc.connectionState) !== -1) {
      return 'busy';
    }
    stopWhep();
    const conn = new RTCPeerConnection({
      iceServers: [{ urls: 'stun:stun.l.google.com:19302' }]
    });
    pc = conn;
    conn.addTransceiver('video', { direction: 'recvonly' });
    conn.addTransceiver('audio', { direction: 'recvonly' });
    conn.ontrack = (ev) => {
      if (ended || conn !== pc) return;
      const dest = sink();
      let stream = dest.srcObject instanceof MediaStream ? dest.srcObject : new MediaStream();
      if (!stream.getTracks().includes(ev.track)) {
        stream.addTrack(ev.track);
      }
      if (dest.srcObject !== stream) {
        dest.srcObject = stream;
      }
      if (dest !== video && video.srcObject) video.srcObject = null;
      if (dest !== board && board && board.srcObject) board.srcObject = null;
      enableViewerSound();
      dest.play().catch(() => {});
      if (screenMode) applySink();
    };
    conn.onconnectionstatechange = () => {
      if (conn !== pc) return;
      if (conn.connectionState === 'connected') {
        if (discTimer) {
          clearTimeout(discTimer);
          discTimer = 0;
        }
        return;
      }
      if (conn.connectionState === 'failed') {
        onStall();
        stopWhep();
        if (playMode === 'webrtc') playMode = 'none';
        return;
      }
      if (conn.connectionState === 'disconnected') {
        if (discTimer) clearTimeout(discTimer);
        discTimer = setTimeout(() => {
          if (conn !== pc || conn.connectionState !== 'disconnected') return;
          onStall();
          stopWhep();
          if (playMode === 'webrtc') playMode = 'none';
        }, 2500);
      }
    };
    const offer = await conn.createOffer();
    await conn.setLocalDescription(offer);
    await waitIceGather(conn, 400);
    let res;
    try {
      res = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/sdp',
          Accept: 'application/sdp'
        },
        body: conn.localDescription && conn.localDescription.sdp ? conn.localDescription.sdp : offer.sdp
      });
    } catch (e) {
      stopWhep();
      return 'offline';
    }
    if (!res.ok) {
      stopWhep();
      return res.status;
    }
    const sdp = await res.text();
    if (!sdp) {
      stopWhep();
      return 204;
    }
    await conn.setRemoteDescription({ type: 'answer', sdp: sdp });
    const ready = await waitPcReady(conn, 8000);
    if (!ready && conn === pc && conn.connectionState !== 'connected') {
      stopWhep();
      return 'ice';
    }
    const framed = await waitForFrames(4000);
    if (!framed || !sink().srcObject) {
      stopWhep();
      return 'notrack';
    }
    applySink();
    playing = true;
    playMode = 'webrtc';
    setProto('Canlı');
    enableViewerSound();
    return true;
  }

  function attachHls(url) {
    if (!url) {
      showWait(true);
      return Promise.resolve(false);
    }
    stopWhep();
    stopHls();
    playMode = 'hls';
    if (window.Hls && Hls.isSupported()) {
      return new Promise((resolve) => {
        let settled = false;
        const done = (ok) => {
          if (settled) return;
          settled = true;
          resolve(ok);
        };
        hls = new Hls({
          enableWorker: true,
          lowLatencyMode: false,
          liveSyncDurationCount: 2,
          liveMaxLatencyDurationCount: 4,
          maxLiveSyncPlaybackRate: 1.2,
          liveDurationInfinity: true,
          maxBufferLength: 4,
          maxMaxBufferLength: 6,
          backBufferLength: 3,
          maxBufferHole: 0.5,
          manifestLoadingTimeOut: 6000,
          startPosition: -1
        });
        const dest = sink();
        hls.loadSource(url);
        hls.attachMedia(dest);
        hls.on(Hls.Events.MANIFEST_PARSED, () => {
          setProto('Canlı');
          enableViewerSound();
          const edge = hls.liveSyncPosition;
          if (Number.isFinite(edge) && edge > 0) {
            try { dest.currentTime = edge; } catch (e) {}
          }
          dest.play().catch(() => {});
          done(true);
        });
        hls.on(Hls.Events.ERROR, (_, data) => {
          if (!data || !data.fatal) return;
          onStall();
          try { hls.startLoad(); } catch (e) {}
          done(false);
        });
        setTimeout(() => done(playing), 9000);
      });
    }
    const dest = sink();
    if (dest.canPlayType('application/vnd.apple.mpegurl')) {
      dest.srcObject = null;
      dest.src = url;
      setProto('Canlı');
      enableViewerSound();
      return dest.play().then(() => true).catch(() => false);
    }
    setWait('Tarayıcı desteklemiyor', '');
    return Promise.resolve(false);
  }

  async function tryWhepOrHls() {
    if (ended || playing || busy || lessonPaused) return;
    if (playMode === 'webrtc' && pc && playing) return;
    if (playMode === 'hls' && hls && playing) return;
    if (playMode === 'webrtc' && !playing) {
      stopWhep();
      playMode = 'none';
    }
    busy = true;
    let whepResult = null;
    let waiting = false;
    try {
      const mtx = cfg.provider === 'cloudflare' ? 'unknown' : await pingMtx();
      if (playMode !== 'hls') {
        for (let i = 0; i < whepUrls.length; i++) {
          setWait('Bağlanıyor…', '');
          whepResult = await startWhep(whepUrls[i]);
          if (whepResult === true) {
            retryMs = 4000;
            return;
          }
          if (isWaitStatus(whepResult)) {
            waiting = true;
            break;
          }
          if (whepResult === 'offline') {
            continue;
          }
          if (whepResult === 'notrack' || whepResult === 'ice') {
            break;
          }
        }
        if (whepResult === true) {
          retryMs = 4000;
          return;
        }
      }
      if (!waiting && cfg.provider !== 'cloudflare') {
        for (let i = 0; i < hlsUrls.length; i++) {
          const ok = await attachHls(hlsUrls[i]);
          if (ok || playing) {
            retryMs = 4000;
            return;
          }
          stopHls();
          playMode = 'none';
        }
      }
      if (waiting || isWaitStatus(whepResult)) {
        setWait('Hoca bağlanıyor', 'Kamera açılınca görüntü gelir.');
      } else {
        const hint = waitHint(mtx === 'down' ? 'down' : 'wait');
        setWait(hint[0], hint[1]);
        if (whepResult === 'ice') {
          reportLive('ice', 'Öğrenci görüntüsü bağlanamadı.', String(whepResult));
        } else if (whepResult === 'notrack' || whepResult === 'offline') {
          reportLive('whep_fail', 'Canlı görüntü alınamadı.', String(whepResult));
        }
      }
      playMode = 'none';
    } catch (e) {
      setWait('Yayın bekleniyor', '');
      playMode = 'none';
    } finally {
      busy = false;
      if (!ended && !playing && whepResult !== true) {
        scheduleRetry(waiting || isWaitStatus(whepResult));
      }
    }
  }

  window.livePlayerMarkEnded = function () {
    ended = true;
    playing = false;
    lessonPaused = false;
    stopWhep();
    stopHls();
    if (typeof window.liveScreenWatch === 'function') {
      window.liveScreenWatch(false);
    }
    setWait('Ders bitti', '');
    setProto('');
  };

  window.livePlayerSetPaused = function (on) {
    lessonPaused = !!on && !ended;
    if (lessonPaused) {
      setWait('Mola', '');
      return;
    }
    if (!ended && playing) {
      showWait(false);
    }
  };

  if (cfg.publish) {
    return;
  }

  parkViewerChrome();
  applySink();
  tryWhepOrHls();
})();
