(function () {
  const cfg = window.LIVE_PLAYER || {};
  if (!cfg.publish || cfg.method !== 'browser') {
    return;
  }
  const video = document.getElementById('live-video');
  const btn = document.getElementById('whip-toggle');
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

  async function stopPublish() {
    publishing = false;
    btn.textContent = 'Kamerayı aç';
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

  async function startPublish() {
    if (!whipUrls.length || typeof RTCPeerConnection === 'undefined') {
      setWait('Yayın adresi yok', 'OBS + HLS yöntemini deneyin.', true);
      return;
    }
    btn.disabled = true;
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: { width: { ideal: 1280 }, height: { ideal: 720 }, frameRate: { ideal: 30 } },
        audio: { echoCancellation: true, noiseSuppression: true }
      });
      video.srcObject = stream;
      video.muted = true;
      await video.play().catch(() => {});
      if (overlay) overlay.classList.add('is-off');

      pc = new RTCPeerConnection({
        iceServers: [{ urls: 'stun:stun.l.google.com:19302' }]
      });
      stream.getTracks().forEach((t) => pc.addTrack(t, stream));
      const offer = await pc.createOffer();
      await pc.setLocalDescription(offer);
      await waitIceGather(pc, 2000);

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
            body: pc.localDescription && pc.localDescription.sdp ? pc.localDescription.sdp : offer.sdp
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
        lastErr = '';
        break;
      }
      if (!publishing) {
        throw new Error(lastErr || 'whip');
      }
    } catch (e) {
      await stopPublish();
      setWait('Kamera açılamadı', 'İzin verin veya OBS + HLS seçip Uygula deyin.', true);
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

  window.addEventListener('pagehide', () => {
    if (publishing) {
      stopPublish();
    }
  });
})();
