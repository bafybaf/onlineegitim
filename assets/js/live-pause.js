(function () {
  const cfg = window.LIVE_PAUSE || {};
  const overlay = document.getElementById('live-pause-overlay');
  const btn = document.getElementById('live-pause-btn');
  const api = cfg.url || '';
  const roomId = cfg.roomId;
  const canCtrl = !!cfg.canCtrl;
  let paused = false;
  let pending = false;

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
    document.body.classList.toggle('is-live-paused', paused);
    if (btn) {
      btn.textContent = paused ? 'Sürdür' : 'Mola';
      btn.classList.toggle('is-paused', paused);
    }
  }

  function setState(on) {
    const next = !!on;
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
    setState(!!room.paused);
  };

  if (btn && canCtrl) {
    btn.addEventListener('click', async () => {
      if (pending || !roomId) {
        return;
      }
      pending = true;
      const next = !paused;
      setState(next);
      try {
        const body = next
          ? 'action=pause&id=' + roomId
          : 'action=resume&id=' + roomId;
        const r = await fetch(api, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: body
        });
        const j = await r.json();
        if (j.ok && j.room) {
          window.livePauseApply(j.room);
        } else {
          setState(!next);
        }
      } catch (e) {
        setState(!next);
      }
      pending = false;
    });
  }

  if (cfg.paused) {
    setState(true);
  }
})();
