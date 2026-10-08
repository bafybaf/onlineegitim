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
  let shareStarting = false;
  let publishing = false;
  let starting = false;
  let wantPublish = false;
  let hearing = false;
  let reconnectTimer = 0;
  let dropTimer = 0;
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

  function iceServers() {
    return [
      { urls: 'stun:stun.cloudflare.com:3478' },
      { urls: 'stun:stun.l.google.com:19302' }
    ];
  }

  function camLive() {
    return !!(stream && stream.getVideoTracks().some((t) => t.readyState === 'live'));
  }

  function whipAlive() {
    return !!(pc && (pc.connectionState === 'connected' || pc.connectionState === 'connecting'));
  }

  function clearReconnect() {
    if (reconnectTimer) {
      clearTimeout(reconnectTimer);
      reconnectTimer = 0;
    }
    if (dropTimer) {
      clearTimeout(dropTimer);
      dropTimer = 0;
    }
  }

  function queueReconnect(ms) {
    if (!wantPublish || starting) return;
    clearReconnect();
    reconnectTimer = setTimeout(() => {
      reconnectTimer = 0;
      if (!wantPublish || starting || whipAlive() || !camLive()) return;
      startPublish().catch(() => {});
    }, ms || 2000);
  }

  function bindPublisherPc(conn) {
    conn.onconnectionstatechange = () => {
      if (conn !== pc) return;
      if (conn.connectionState === 'connected') {
        if (dropTimer) {
          clearTimeout(dropTimer);
          dropTimer = 0;
        }
        publishing = true;
        btn.textContent = 'Kapat';
        setProto(sendPaused ? 'Mola' : 'Yayındasınız');
        if (overlay) overlay.classList.add('is-off');
        return;
      }
      if (conn.connectionState === 'failed') {
        publishing = false;
        if (wantPublish) {
          setProto('Yeniden bağlanıyor…');
          queueReconnect(1500);
        }
        return;
      }
      if (conn.connectionState === 'disconnected') {
        if (dropTimer) clearTimeout(dropTimer);
        dropTimer = setTimeout(() => {
          dropTimer = 0;
          if (conn !== pc || conn.connectionState !== 'disconnected' || !wantPublish) return;
          publishing = false;
          setProto('Yeniden bağlanıyor…');
          queueReconnect(1200);
        }, 2500);
      }
    };
  }

  let lastPubLog = {};
  function reportPublish(kind, message, detail) {
    const api = liveApi();
    const rid = liveRoomId();
    if (!api || !rid) return;
    const now = Date.now();
    if (lastPubLog[kind] && now - lastPubLog[kind] < 90000) return;
    lastPubLog[kind] = now;
    const body = new URLSearchParams();
    body.set('action', 'log');
    body.set('room_id', String(rid));
    body.set('kind', kind);
    body.set('message', message || kind);
    body.set('detail', detail || '');
    fetch(api, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() }).catch(() => {});
  }

  function liveApi() {
    return cfg.api || (typeof base === 'string' ? base + 'api/live.php' : '');
  }

  function liveRoomId() {
    return cfg.roomId || (typeof roomId !== 'undefined' ? roomId : 0);
  }

  async function whipProxy(method, target, sdp) {
    const api = liveApi();
    if (!api || !target) {
      return { ok: false, status: 0, sdp: '', location: '' };
    }
    const body = new URLSearchParams();
    body.set('action', 'whip');
    body.set('room_id', String(liveRoomId()));
    body.set('method', method);
    body.set('target', target);
    if (sdp) {
      body.set('sdp', sdp);
    }
    const res = await fetch(api, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString()
    });
    return res.json().catch(() => ({ ok: false, status: 0, sdp: '', location: '' }));
  }

  async function deleteWhip(url) {
    if (!url) return;
    try {
      await whipProxy('DELETE', url, '');
    } catch (e) {}
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
      listenBtn.textContent = hearing ? 'Ses açık' : 'Ses';
    }
  }

  async function stopWhipOnly() {
    const oldLoc = loc;
    loc = '';
    const oldPc = pc;
    pc = null;
    await deleteWhip(oldLoc);
    if (oldPc) {
      try { oldPc.close(); } catch (e) {}
    }
  }

  async function stopPublish() {
    wantPublish = false;
    clearReconnect();
    publishing = false;
    starting = false;
    btn.textContent = 'Kamera';
    setHearing(false);
    if (listenBtn) listenBtn.hidden = true;
    stopMeter();
    setProto('');
    await stopWhipOnly();
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

  async function fetchSdp(url, body) {
    const j = await whipProxy('POST', url, body);
    return {
      ok: !!j.ok,
      status: j.status || 0,
      location: j.location || '',
      text: async function () {
        return j.sdp || '';
      }
    };
  }

  async function captureMedia() {
    const videoConstraints = {
      width: { ideal: 1280, max: 1280 },
      height: { ideal: 720, max: 720 },
      frameRate: { ideal: 24, max: 24 }
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
    await stopWhipOnly();
    if (!stream) {
      throw new Error('nocam');
    }
    pc = new RTCPeerConnection({
      iceServers: iceServers()
    });
    const conn = pc;
    bindPublisherPc(conn);
    stream.getVideoTracks().forEach((t) => {
      if (t.readyState === 'live') {
        conn.addTransceiver(t, { direction: 'sendonly', streams: [stream] });
      }
    });
    stream.getAudioTracks().forEach((t) => {
      t.enabled = true;
      if (t.readyState === 'live') {
        conn.addTransceiver(t, { direction: 'sendonly', streams: [stream] });
      }
    });
    if (!conn.getTransceivers().length) {
      throw new Error('nocam');
    }
    const offer = await conn.createOffer();
    await conn.setLocalDescription(offer);
    await waitIceGather(conn, 2000);
    const offerSdp = conn.localDescription && conn.localDescription.sdp ? conn.localDescription.sdp : offer.sdp;
    let lastErr = '';
    for (let attempt = 0; attempt < 4; attempt++) {
      if (conn !== pc) {
        throw new Error('stale');
      }
      for (let i = 0; i < whipUrls.length; i++) {
        const url = whipUrls[i];
        let res;
        try {
          res = await fetchSdp(url, offerSdp);
        } catch (e) {
          lastErr = 'offline';
          continue;
        }
        if (res.status === 409) {
          lastErr = '409';
          await new Promise((r) => setTimeout(r, 1000 + attempt * 800));
          continue;
        }
        if (!res.ok) {
          lastErr = String(res.status || 'whip');
          continue;
        }
        loc = res.location || publicWhipUrl(url, res.location || '') || '';
        const sdp = await res.text();
        if (!sdp || !/v=0/i.test(sdp)) {
          lastErr = 'empty';
          continue;
        }
        await conn.setRemoteDescription({ type: 'answer', sdp: sdp });
        waitPcReady(conn, 8000);
        return true;
      }
    }
    throw new Error(lastErr || 'whip');
  }

  async function startPublish() {
    if (starting) return;
    if (whipAlive()) return;
    if (!whipUrls.length || typeof RTCPeerConnection === 'undefined') {
      setWait('Yayın yok', '', true);
      return;
    }
    wantPublish = true;
    starting = true;
    publishing = false;
    clearReconnect();
    btn.textContent = 'Bağlanıyor…';
    try {
      if (!camLive()) {
        if (stream) {
          stream.getTracks().forEach((t) => t.stop());
          stream = null;
        }
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
      setProto('Bağlanıyor…');
      await connectWhip();
      publishing = true;
      btn.textContent = 'Kapat';
      if (listenBtn) listenBtn.hidden = false;
      if (overlay) overlay.classList.add('is-off');
      setProto(sendPaused ? 'Mola' : 'Yayındasınız');
      applySendPause();
      if (typeof window.liveRecordOnCam === 'function') {
        window.liveRecordOnCam(stream);
      }
    } catch (e) {
      publishing = false;
      await stopWhipOnly();
      const msg = String((e && e.message) || '');
      if (!stream) {
        setWait('Kamera açılamadı', '', true);
        btn.textContent = 'Kamera';
        reportPublish('camera', 'Hoca kamerası açılamadı.', msg);
      } else if (msg === '409') {
        setWait('Yayın meşgul', 'Önceki oturum kapanıyor, tekrar deniyor…', true);
        setProto('Yeniden bağlanıyor…');
        btn.textContent = 'Tekrar';
        queueReconnect(2500);
      } else {
        setWait('Yayın bağlanamadı', 'Tekrar deneyin.', true);
        setProto('Yayın bağlanamadı');
        btn.textContent = 'Tekrar';
        queueReconnect(4000);
      }
    } finally {
      starting = false;
    }
  }

  async function connectWhipScreen() {
    if (screenPc) {
      try { screenPc.close(); } catch (e) {}
      screenPc = null;
    }
    screenLoc = '';
    if (!displayStream) return false;
    if (!whipScreenUrls.length) {
      setProto('Ekran WHIP yok — Admin → Canlı’da Ekran adreslerini kaydedin');
      return false;
    }
    screenPc = new RTCPeerConnection({
      iceServers: iceServers()
    });
    displayStream.getVideoTracks().forEach((t) => {
      screenPc.addTransceiver(t, { direction: 'sendonly', streams: [displayStream] });
    });
    displayStream.getAudioTracks().forEach((t) => {
      t.enabled = true;
      screenPc.addTransceiver(t, { direction: 'sendonly', streams: [displayStream] });
    });
    if (!displayStream.getVideoTracks().length) {
      screenPc.addTransceiver('video', { direction: 'sendonly' });
    }
    const offer = await screenPc.createOffer();
    await screenPc.setLocalDescription(offer);
    await waitIceGather(screenPc, 2000);
    const offerSdp = screenPc.localDescription && screenPc.localDescription.sdp ? screenPc.localDescription.sdp : offer.sdp;
    for (let i = 0; i < whipScreenUrls.length; i++) {
      const url = whipScreenUrls[i];
      let res;
      try {
        res = await fetchSdp(url, offerSdp);
      } catch (e) {
        continue;
      }
      if (!res.ok) continue;
      screenLoc = res.location || publicWhipUrl(url, res.location || '') || '';
      const sdp = await res.text();
      if (!sdp || !/v=0/i.test(sdp)) continue;
      await screenPc.setRemoteDescription({ type: 'answer', sdp: sdp });
      waitPcReady(screenPc, 4000);
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
      await deleteWhip(screenLoc);
      screenLoc = '';
    }
    if (screenPc) {
      try { screenPc.close(); } catch (e) {}
      screenPc = null;
    }
    if (displayStream) {
      displayStream.onaddtrack = null;
      displayStream.getTracks().forEach((t) => t.stop());
      displayStream = null;
    }
    sharing = false;
    if (screenEl) screenEl.srcObject = null;
    if (typeof window.liveRecordOnShare === 'function') {
      window.liveRecordOnShare(null);
    }
    if (shareBtn) shareBtn.textContent = 'Ekran';
    showBoardScreen(false);
  }

  async function startShare() {
    if (shareStarting || sharing) return;
    if (!navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia) {
      setProto('Ekran paylaşımı yok');
      return;
    }
    shareStarting = true;
    if (shareBtn) shareBtn.textContent = 'Seçin…';
    try {
      displayStream = await navigator.mediaDevices.getDisplayMedia({
        video: { frameRate: { ideal: 10, max: 12 }, width: { max: 1280 }, height: { max: 720 } },
        audio: {
          echoCancellation: false,
          noiseSuppression: false,
          autoGainControl: false
        },
        systemAudio: 'include'
      });
    } catch (err) {
      if (err && err.name === 'NotAllowedError') throw err;
      try {
        displayStream = await navigator.mediaDevices.getDisplayMedia({
          video: true,
          audio: true,
          systemAudio: 'include'
        });
      } catch (e2) {
        displayStream = await navigator.mediaDevices.getDisplayMedia({ video: true, audio: false });
      }
    }
    if (!displayStream) {
      shareStarting = false;
      if (shareBtn) shareBtn.textContent = 'Ekran';
      return;
    }
    const screenTrack = displayStream.getVideoTracks()[0];
    if (screenTrack) {
      try {
        screenTrack.applyConstraints({
          frameRate: { ideal: 10, max: 12 },
          width: { max: 1280 },
          height: { max: 720 }
        });
      } catch (e) {}
    }
    if (!screenTrack) {
      shareStarting = false;
      await stopShare();
      return;
    }
    screenTrack.onended = () => { stopShare(); };
    displayStream.onaddtrack = () => {
      if (typeof window.liveRecordOnShare === 'function') {
        window.liveRecordOnShare(displayStream);
      }
    };
    if (screenEl) {
      screenEl.srcObject = new MediaStream(displayStream.getVideoTracks());
      screenEl.muted = true;
      screenEl.play().catch(() => {});
    }
    sharing = true;
    if (shareBtn) shareBtn.textContent = 'Durdur';
    showBoardScreen(true);
    if (!publishing && !stream) {
      startPublish().catch(() => {});
    }
    if (typeof window.liveRecordOnCam === 'function' && (camStream || stream)) {
      window.liveRecordOnCam(camStream || stream);
    }
    if (typeof window.liveRecordOnShare === 'function') {
      window.liveRecordOnShare(displayStream);
    }
    if (!displayStream.getAudioTracks().length) {
      setProto('Ekran sesi yok — Chrome’da Sekme seçip “Sekme sesini paylaş”ı işaretleyin');
    }
    shareStarting = false;
    connectWhipScreen().then(function (ok) {
      if (!ok) setProto('Ekran Cloudflare’a bağlanamadı — Admin’de Ekran WHIP/WHEP dolu olsun');
      else if (!sendPaused) setProto('Ekran yayında');
    });
  }

  btn.addEventListener('click', () => {
    if (starting) return;
    if (publishing || whipAlive()) {
      stopPublish();
      return;
    }
    startPublish();
  });

  if (shareBtn) {
    shareBtn.addEventListener('click', () => {
      if (shareStarting) return;
      if (sharing) stopShare();
      else startShare().catch(() => {
        shareStarting = false;
        if (shareBtn) shareBtn.textContent = 'Ekran';
        setProto('Paylaşım iptal');
      });
    });
  }

  if (listenBtn) {
    listenBtn.addEventListener('click', () => setHearing(!hearing));
  }

  setInterval(() => {
    if (wantPublish && camLive() && !whipAlive() && !starting && !publishing) {
      queueReconnect(800);
    }
  }, 8000);

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
