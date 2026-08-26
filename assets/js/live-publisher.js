(function () {
  const cfg = window.LIVE_PLAYER || {};
  if (!cfg.publish || cfg.method !== 'browser') {
    return;
  }
  const video = document.getElementById('live-video');
  const btn = document.getElementById('whip-toggle');
  const listenBtn = document.getElementById('whip-listen');
  const meterEl = document.getElementById('whip-meter');
  const meterFill = meterEl ? meterEl.querySelector('i') : null;
  const overlay = document.getElementById('wait-overlay');
  const waitTitle = document.getElementById('wait-title');
  const waitDetail = document.getElementById('wait-detail');
  if (!video || !btn) {
    return;
  }

  const whipUrls = [cfg.whipUrl, cfg.whipUrlAlt].filter(Boolean);
  let pc = null;
  let loc = '';
  let stream = null;
  let publishing = false;
  let hearing = false;
  let meterTimer = 0;
  let audioCtx = null;

  function setWait(title, detail, show) {
    if (waitTitle) waitTitle.textContent = title;
    if (waitDetail) {
      waitDetail.textContent = detail || '';
      waitDetail.hidden = !detail;
    }
    if (overlay) overlay.classList.toggle('is-off', !show);
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
    btn.textContent = 'Kamerayı aç';
    setHearing(false);
    if (listenBtn) listenBtn.hidden = true;
    stopMeter();
    if (loc) {
      try {
        await fetch(loc, { method: 'DELETE' });
      } catch (e) {}
      loc = '';
    }
    if (pc) {
      try { pc.close(); } catch (e) {}
      pc = null;
    }
    if (stream) {
      stream.getTracks().forEach((t) => t.stop());
      stream = null;
    }
    video.srcObject = null;
    setWait('Kamerayı açın', 'Aşağıdaki düğmeyle tarayıcı kamerasını açın. Öğrenciler sizi izler.', true);
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

  async function startPublish() {
    if (!whipUrls.length || typeof RTCPeerConnection === 'undefined') {
      setWait('Yayın adresi yok', 'OBS + HLS yöntemini deneyin.', true);
      return;
    }
    btn.disabled = true;
    try {
      stream = await captureMedia();
      if (!stream.getAudioTracks().length) {
        throw new Error('mic');
      }
      video.srcObject = stream;
      setHearing(false);
      await video.play().catch(() => {});
      if (overlay) overlay.classList.add('is-off');
      startMeter(stream);

      pc = new RTCPeerConnection({
        iceServers: [{ urls: 'stun:stun.l.google.com:19302' }]
      });
      stream.getVideoTracks().forEach((t) => {
        pc.addTransceiver(t, { direction: 'sendonly', streams: [stream] });
      });
      stream.getAudioTracks().forEach((t) => {
        t.enabled = true;
        pc.addTransceiver(t, { direction: 'sendonly', streams: [stream] });
      });
      const offer = await pc.createOffer();
      await pc.setLocalDescription(offer);
      await waitIceGather(pc, 2000);
      const offerSdp = pc.localDescription && pc.localDescription.sdp ? pc.localDescription.sdp : offer.sdp;
      if (!/m=audio/i.test(offerSdp || '')) {
        throw new Error('mic');
      }

      let lastErr = '';
      for (let i = 0; i < whipUrls.length; i++) {
        const url = whipUrls[i];
        let res;
        try {
          res = await fetch(url, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/sdp',
              Accept: 'application/sdp'
            },
            body: offerSdp
          });
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
        if (!sdp) {
          lastErr = 'empty';
          continue;
        }
        await pc.setRemoteDescription({ type: 'answer', sdp: sdp });
        publishing = true;
        btn.textContent = 'Kamerayı kapat';
        if (listenBtn) listenBtn.hidden = false;
        lastErr = '';
        break;
      }
      if (!publishing) {
        throw new Error(lastErr || 'whip');
      }
    } catch (e) {
      const noMic = String(e && e.message) === 'mic';
      await stopPublish();
      setWait(
        noMic ? 'Mikrofon yok' : 'Kamera açılamadı',
        noMic ? 'Tarayıcı mikrofon iznini verin; aksi halde öğrenciler sizi duyamaz.' : 'İzin verin veya OBS + HLS seçip Uygula deyin.',
        true
      );
    } finally {
      btn.disabled = false;
    }
  }

  btn.addEventListener('click', () => {
    if (publishing) {
      stopPublish();
    } else {
      startPublish();
    }
  });

  if (listenBtn) {
    listenBtn.addEventListener('click', () => setHearing(!hearing));
  }

  window.addEventListener('pagehide', () => {
    if (publishing) {
      stopPublish();
    }
  });
})();
