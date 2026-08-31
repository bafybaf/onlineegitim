(function () {
  const cfg = window.LIVE_PLAYER || {};
  if (!cfg.publish) {
    return;
  }
  const video = document.getElementById('live-video');
  const btn = document.getElementById('whip-toggle');
  const listenBtn = document.getElementById('whip-listen');
  const shareBtn = document.getElementById('whip-share');
  const meterEl = document.getElementById('whip-meter');
  const meterFill = meterEl ? meterEl.querySelector('i') : null;
  const overlay = document.getElementById('wait-overlay');
  const waitTitle = document.getElementById('wait-title');
  const waitDetail = document.getElementById('wait-detail');
  if (!video || !btn) {
    return;
  }

  const whipUrls = [cfg.whipUrl, cfg.whipUrlAlt].filter(Boolean);
  const whipScreenUrls = [cfg.whipScreenUrl, cfg.whipScreenUrlAlt].filter(Boolean);
  const screenEl = document.getElementById('board-screen');
  let pc = null;
  let loc = '';
  let screenPc = null;
  let screenLoc = '';
  let stream = null;
  let camStream = null;
  let displayStream = null;
  let sharing = false;
  let publishing = false;
  let starting = false;
  let hearing = false;
  let meterTimer = 0;
  let audioCtx = null;
  let sendPaused = false;
  const protoEl = document.getElementById('live-proto');

  function applySendPause() {
    [pc, screenPc].forEach((conn) => {
      if (!conn) {
        return;
      }
      try {
        conn.getSenders().forEach((snd) => {
          if (snd.track) {
            snd.track.enabled = !sendPaused;
          }
        });
      } catch (e) {}
    });
    [stream, camStream, displayStream].forEach((media) => {
      if (!media) {
        return;
      }
      media.getTracks().forEach((t) => {
        t.enabled = !sendPaused;
      });
    });
  }

  function setProto(text) {
    if (!protoEl) return;
    protoEl.textContent = text || '';
    protoEl.hidden = !text;
  }

  function setWait(title, detail, show) {
    if (waitTitle) waitTitle.textContent = title;
    if (waitDetail) {
      waitDetail.textContent = detail || '';
      waitDetail.hidden = !detail;
    }
    if (overlay) overlay.classList.toggle('is-off', !show);
  }

  function waitPcReady(conn, ms) {
    if (!conn) return Promise.resolve(false);
    if (conn.connectionState === 'connected') return Promise.resolve(true);
    return new Promise((resolve) => {
      let done = false;
      const finish = (ok) => {
        if (done) return;
        done = true;
        resolve(!!ok);
      };
      const t = setTimeout(() => finish(conn.connectionState === 'connected'), ms);
      conn.addEventListener('connectionstatechange', () => {
        if (conn.connectionState === 'connected') {
          clearTimeout(t);
          finish(true);
        }
        if (conn.connectionState === 'failed' || conn.connectionState === 'closed') {
          clearTimeout(t);
          finish(false);
        }
      });
    });
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

  function publicWhipUrl(postedTo, headerLoc) {
    if (!headerLoc) {
      return '';
    }
    try {
      const posted = new URL(postedTo, location.href);
      const abs = new URL(headerLoc, posted);
      const mtxPrefix = posted.pathname.split('/live/')[0];
      const idx = abs.pathname.indexOf('/live/');
      const rest = idx >= 0 ? abs.pathname.slice(idx) : abs.pathname;
      if (rest.indexOf('/live/') === 0) {
        return posted.origin + mtxPrefix + rest + abs.search;
      }
      return posted.origin + abs.pathname + abs.search;
    } catch (e) {
      return '';
    }
  }

  function stopMeter() {
    if (meterTimer) {
      clearInterval(meterTimer);
      meterTimer = 0;
    }
    if (audioCtx) {
      try { audioCtx.close(); } catch (e) {}
      audioCtx = null;
    }
    if (meterFill) meterFill.style.width = '0';
    if (meterEl) meterEl.hidden = true;
  }

  function startMeter(media) {
    stopMeter();
    const track = media.getAudioTracks()[0];
    if (!track || !meterEl || !meterFill) {
      return;
    }
    meterEl.hidden = false;
    const AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) {
      return;
    }
    try {
      audioCtx = new AC();
      const src = audioCtx.createMediaStreamSource(media);
      const anal = audioCtx.createAnalyser();
      anal.fftSize = 256;
      src.connect(anal);
      const data = new Uint8Array(anal.frequencyBinCount);
      meterTimer = setInterval(() => {
        if (audioCtx && audioCtx.state === 'suspended') {
          audioCtx.resume().catch(() => {});
        }
        anal.getByteFrequencyData(data);
        let sum = 0;
        for (let i = 0; i < data.length; i++) sum += data[i];
        const pct = Math.min(100, Math.round((sum / data.length) * 1.6));
        meterFill.style.width = pct + '%';
      }, 80);
    } catch (e) {}
  }

  function setHearing(on) {
    hearing = !!on;
    video.muted = !hearing;
    if (hearing) video.removeAttribute('muted');
    else video.muted = true;
    if (listenBtn) {
      listenBtn.hidden = !publishing;
      listenBtn.textContent = hearing ? 'Önizlemede ses açık' : 'Önizlemede ses kapalı';
    }
  }

  async function stopPublish() {
    publishing = false;
    starting = false;
    btn.textContent = 'Kamerayı aç';
    setHearing(false);
    if (listenBtn) listenBtn.hidden = true;
    stopMeter();
    setProto('');
    if (loc) {
      try {
        await fetch(loc, { method: 'DELETE', credentials: 'omit' });
      } catch (e) {}
      loc = '';
    }
    if (pc) {
      try { pc.close(); } catch (e) {}
      pc = null;
    }
    if (camStream && camStream !== stream) {
      camStream.getTracks().forEach((t) => t.stop());
    }
    camStream = null;
    if (stream) {
      stream.getTracks().forEach((t) => t.stop());
      stream = null;
    }
    video.srcObject = null;
    setWait('Kamera', '', true);
  }

  function fetchSdp(url, body, ms) {
    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), ms);
    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/sdp',
        Accept: 'application/sdp'
      },
      body: body,
      mode: 'cors',
      credentials: 'omit',
      signal: ctrl.signal
    }).finally(() => clearTimeout(timer));
  }

  async function captureMedia() {
    const videoConstraints = {
      width: { ideal: 1280 },
      height: { ideal: 720 },
      frameRate: { ideal: 30 }
    };
    const audioConstraints = {
      echoCancellation: true,
      noiseSuppression: true,
      autoGainControl: true,
      channelCount: 1
    };
    let media = await navigator.mediaDevices.getUserMedia({
      video: videoConstraints,
      audio: audioConstraints
    });
    if (!media.getAudioTracks().length) {
      try {
        const mic = await navigator.mediaDevices.getUserMedia({ audio: audioConstraints, video: false });
        mic.getAudioTracks().forEach((t) => media.addTrack(t));
      } catch (e) {}
    }
    media.getAudioTracks().forEach((t) => {
      t.enabled = true;
      try { t.applyConstraints(audioConstraints); } catch (e) {}
    });
    return media;
  }

  async function connectWhip() {
    if (pc) {
      try { pc.close(); } catch (e) {}
      pc = null;
    }
    loc = '';
    pc = new RTCPeerConnection({
      iceServers: [{ urls: 'stun:stun.l.google.com:19302' }]
    });
    stream.getVideoTracks().forEach((t) => {
      pc.addTrack(t, stream);
    });
    stream.getAudioTracks().forEach((t) => {
      t.enabled = true;
      pc.addTrack(t, stream);
    });
    const offer = await pc.createOffer();
    await pc.setLocalDescription(offer);
    await waitIceGather(pc, 2500);
    const offerSdp = pc.localDescription && pc.localDescription.sdp ? pc.localDescription.sdp : offer.sdp;
    let lastErr = '';
    for (let i = 0; i < whipUrls.length; i++) {
      const url = whipUrls[i];
      let res;
      try {
        res = await fetchSdp(url, offerSdp, 8000);
      } catch (e) {
        lastErr = 'offline';
        continue;
      }
      if (!res.ok) {
        lastErr = String(res.status);
        continue;
      }
      loc = publicWhipUrl(url, res.headers.get('Location') || '');
      const sdp = await res.text();
      if (!sdp || !/v=0/i.test(sdp)) {
        lastErr = 'empty';
        continue;
      }
      await pc.setRemoteDescription({ type: 'answer', sdp: sdp });
      const iceOk = await waitPcReady(pc, 10000);
      if (!iceOk) {
        lastErr = 'ice';
        continue;
      }
      return true;
    }
    throw new Error(lastErr || 'whip');
  }

  async function startPublish() {
    if (starting || publishing) return;
    if (!whipUrls.length || typeof RTCPeerConnection === 'undefined') {
      setWait('Yayın yok', '', true);
      return;
    }
    starting = true;
    btn.disabled = true;
    try {
      if (!stream) {
        stream = await captureMedia();
        camStream = stream;
        video.srcObject = stream;
        setHearing(false);
        await video.play().catch(() => {});
        startMeter(stream);
        if (typeof window.liveRecordOnCam === 'function') {
          window.liveRecordOnCam(stream);
        }
      }
      setWait('Yayına bağlanılıyor…', 'Öğrenciler bağlanınca görüntü açılır.', true);
      let lastErr = null;
      for (let n = 0; n < 3; n++) {
        try {
          setProto(n ? 'Yeniden bağlanıyor…' : 'Bağlanıyor…');
          await connectWhip();
          lastErr = null;
          break;
        } catch (err) {
          lastErr = err;
          await new Promise((r) => setTimeout(r, 800));
        }
      }
      if (lastErr) throw lastErr;
      publishing = true;
      btn.textContent = 'Kamerayı kapat';
      if (listenBtn) listenBtn.hidden = false;
      if (overlay) overlay.classList.add('is-off');
      setProto(sendPaused ? 'Mola' : 'Yayındasınız');
      applySendPause();
      if (typeof window.liveRecordOnCam === 'function') {
        window.liveRecordOnCam(stream);
      }
    } catch (e) {
      if (pc) {
        try { pc.close(); } catch (err) {}
        pc = null;
      }
      publishing = false;
      if (!stream) {
        setWait('Kamera açılamadı', '', true);
      } else {
        setWait('Yayın bağlanamadı', 'Öğrenciler sizi göremez. Kamerayı kapatıp tekrar açın.', true);
        setProto('Yayın bağlanamadı');
        btn.textContent = 'Kamerayı kapat';
      }
    } finally {
      starting = false;
      btn.disabled = false;
    }
  }

  async function connectWhipScreen() {
    if (screenPc) {
      try { screenPc.close(); } catch (e) {}
      screenPc = null;
    }
    screenLoc = '';
    if (!displayStream || !whipScreenUrls.length) return false;
    screenPc = new RTCPeerConnection({
      iceServers: [{ urls: 'stun:stun.l.google.com:19302' }]
    });
    displayStream.getVideoTracks().forEach((t) => {
      screenPc.addTrack(t, displayStream);
    });
    const offer = await screenPc.createOffer();
    await screenPc.setLocalDescription(offer);
    await waitIceGather(screenPc, 2500);
    const offerSdp = screenPc.localDescription && screenPc.localDescription.sdp ? screenPc.localDescription.sdp : offer.sdp;
    for (let i = 0; i < whipScreenUrls.length; i++) {
      const url = whipScreenUrls[i];
      let res;
      try {
        res = await fetchSdp(url, offerSdp, 8000);
      } catch (e) {
        continue;
      }
      if (!res.ok) continue;
      screenLoc = publicWhipUrl(url, res.headers.get('Location') || '');
      const sdp = await res.text();
      if (!sdp || !/v=0/i.test(sdp)) continue;
      await screenPc.setRemoteDescription({ type: 'answer', sdp: sdp });
      applySendPause();
      return true;
    }
    try { screenPc.close(); } catch (e) {}
    screenPc = null;
    return false;
  }

  function showBoardScreen(on) {
    const stage = document.getElementById('board-stage');
    if (stage) stage.classList.toggle('is-screen', !!on);
    if (typeof window.liveBoardSetScreen === 'function') {
      window.liveBoardSetScreen(!!on);
    }
  }

  async function stopShare() {
    if (screenLoc) {
      try { await fetch(screenLoc, { method: 'DELETE', credentials: 'omit' }); } catch (e) {}
      screenLoc = '';
    }
    if (screenPc) {
      try { screenPc.close(); } catch (e) {}
      screenPc = null;
    }
    if (displayStream) {
      displayStream.getTracks().forEach((t) => t.stop());
      displayStream = null;
    }
    sharing = false;
    if (screenEl) screenEl.srcObject = null;
    if (shareBtn) shareBtn.textContent = 'Ekran paylaş';
    showBoardScreen(false);
  }

  async function startShare() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia) {
      setProto('Ekran paylaşımı yok');
      return;
    }
    try {
      displayStream = await navigator.mediaDevices.getDisplayMedia({
        video: { frameRate: { ideal: 15 } },
        audio: false
      });
    } catch (err) {
      if (err && err.name === 'NotAllowedError') throw err;
      displayStream = await navigator.mediaDevices.getDisplayMedia({ video: true, audio: false });
    }
    const screenTrack = displayStream.getVideoTracks()[0];
    if (!screenTrack) {
      await stopShare();
      return;
    }
    screenTrack.onended = () => { stopShare(); };
    if (screenEl) {
      screenEl.srcObject = displayStream;
      screenEl.play().catch(() => {});
    }
    sharing = true;
    if (shareBtn) shareBtn.textContent = 'Paylaşımı durdur';
    showBoardScreen(true);
    if (typeof window.liveRecordOnCam === 'function') {
      window.liveRecordOnCam(camStream || stream || displayStream);
    }
    const ok = await connectWhipScreen();
    if (!ok) setProto('Ekran bağlanamadı');
  }

  btn.addEventListener('click', () => {
    if (publishing || stream) {
      stopPublish();
    } else {
      startPublish();
    }
  });

  if (shareBtn) {
    shareBtn.addEventListener('click', () => {
      if (sharing) stopShare();
      else startShare().catch(() => setProto('Paylaşım iptal'));
    });
  }

  if (listenBtn) {
    listenBtn.addEventListener('click', () => setHearing(!hearing));
  }

  setInterval(() => {
    if (stream && !publishing && !starting) {
      startPublish().catch(() => {});
    }
  }, 5000);

  window.addEventListener('pagehide', () => {
    if (sharing) stopShare();
    if (publishing || stream) {
      stopPublish();
    }
  });

  window.livePublishSetPaused = function (on) {
    sendPaused = !!on;
    applySendPause();
    if (publishing) {
      setProto(sendPaused ? 'Mola' : 'Yayındasınız');
    }
  };
})();
