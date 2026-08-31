(function () {
  const cfg = window.LIVE_PAUSE || {};
  const overlay = document.getElementById('live-pause-overlay');
  const clock = document.getElementById('live-pause-clock');
  const btn = document.getElementById('live-pause-btn');
  const minsEl = document.getElementById('live-pause-mins');
  const api = cfg.url || '';
  const roomId = cfg.roomId;
  const canCtrl = !!cfg.canCtrl;
  let paused = false;
  let left = 0;
  let pending = false;

  function fmt(sec) {
    sec = Math.max(0, sec | 0);
    return Math.floor(sec / 60) + ':' + String(sec % 60).padStart(2, '0');
  }

  function applyHooks(on) {
    if (typeof window.liveRecordSetPaused === 'function') {
      window.liveRecordSetPaused(on);
    }
    if (typeof window.livePublishSetPaused === 'function') {
      window.livePublishSetPaused(on);
    }
    if (typeof window.livePlayerSetPaused === 'function') {
      window.livePlayerSetPaused(on);
    }
  }

  function render() {
    if (overlay) {
      overlay.classList.toggle('is-on', paused);
    }
    if (clock) {
      clock.textContent = fmt(left);
    }
    document.body.classList.toggle('is-live-paused', paused);
    if (btn) {
      btn.textContent = paused ? 'Dersi sürdür' : 'Mola';
      btn.classList.toggle('is-paused', paused);
    }
    if (minsEl) {
      minsEl.hidden = paused;
    }
  }

  function setState(on, secs) {
    const next = !!on;
    left = next ? Math.max(0, secs | 0) : 0;
    if (paused !== next) {
      paused = next;
      applyHooks(paused);
    }
    render();
  }

  window.livePauseApply = function (room) {
    if (!room) {
      return;
    }
    const on = !!room.paused;
    setState(on, on ? (room.pause_left || 0) : 0);
  };

  if (btn && canCtrl) {
    btn.addEventListener('click', async () => {
      if (pending || !roomId) {
        return;
      }
      pending = true;
      btn.disabled = true;
      try {
        const body = paused
          ? 'action=resume&id=' + roomId
          : 'action=pause&id=' + roomId + '&mins=' + encodeURIComponent(minsEl ? minsEl.value : '5');
        const r = await fetch(api, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: body
        });
        const j = await r.json();
        if (j.ok && j.room) {
          window.livePauseApply(j.room);
        }
      } catch (e) {}
      pending = false;
      btn.disabled = false;
    });
  }

  setInterval(() => {
    if (!paused || left <= 0) {
      return;
    }
    left -= 1;
    render();
  }, 1000);

  if (cfg.paused) {
    setState(true, cfg.pauseLeft || 0);
  }
})();
