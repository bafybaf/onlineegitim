(function () {
  const cfg = window.LIVE_BOARD || {};
  const shell = document.querySelector('.live-shell');
  const stage = document.getElementById('board-stage');
  const home = document.getElementById('live-stage');
  const pip = document.getElementById('live-cam-pip');
  const btn = document.getElementById('board-full');
  const api = cfg.url || '';
  const roomId = Number(cfg.roomId || 0);
  const canMove = !!cfg.publish;
  let full = false;
  let camX = 0.72;
  let camY = 0.04;
  let drag = null;
  let sendTimer = 0;

  function clamp(x, y) {
    if (!stage || !pip) {
      return [x, y];
    }
    const sw = Math.max(1, stage.clientWidth);
    const sh = Math.max(1, stage.clientHeight);
    const maxX = Math.max(0, 1 - pip.offsetWidth / sw);
    const maxY = Math.max(0, 1 - pip.offsetHeight / sh);
    return [Math.max(0, Math.min(maxX, x)), Math.max(0, Math.min(maxY, y))];
  }

  function placePip() {
    if (!pip || !stage || !home) {
      return;
    }
    if (full) {
      if (pip.parentNode !== stage) {
        stage.appendChild(pip);
      }
      const xy = clamp(camX, camY);
      camX = xy[0];
      camY = xy[1];
      pip.style.left = (camX * 100) + '%';
      pip.style.top = (camY * 100) + '%';
    } else if (pip.parentNode !== home) {
      home.insertBefore(pip, home.firstChild);
      pip.style.left = '';
      pip.style.top = '';
    }
  }

  function render() {
    if (shell) {
      shell.classList.toggle('is-board-full', full);
    }
    document.body.classList.toggle('is-board-full', full);
    if (btn) {
      btn.textContent = full ? 'Tahtayı küçült' : 'Tam ekran';
      btn.classList.toggle('is-on', full);
    }
    placePip();
  }

  function sendLayout() {
    if (!canMove || !roomId || !api) {
      return;
    }
    clearTimeout(sendTimer);
    sendTimer = setTimeout(() => {
      fetch(api + '?action=board', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
          op: 'layout',
          id: roomId,
          full: full ? 1 : 0,
          camX: camX,
          camY: camY
        })
      }).catch(() => null);
    }, 160);
  }

  window.liveLayoutApply = function (j) {
    if (!j) {
      return;
    }
    if (j.boardFull != null) {
      full = !!j.boardFull;
    }
    if (j.camX != null) {
      camX = Number(j.camX);
    }
    if (j.camY != null) {
      camY = Number(j.camY);
    }
    if (drag) {
      return;
    }
    render();
  };

  if (btn && canMove) {
    btn.addEventListener('click', () => {
      full = !full;
      render();
      sendLayout();
    });
  }

  if (pip && canMove) {
    pip.addEventListener('pointerdown', (ev) => {
      if (!full || ev.button === 2) {
        return;
      }
      ev.preventDefault();
      ev.stopPropagation();
      pip.setPointerCapture(ev.pointerId);
      const r = stage.getBoundingClientRect();
      drag = {
        dx: ev.clientX - r.left - camX * r.width,
        dy: ev.clientY - r.top - camY * r.height
      };
      pip.classList.add('is-drag');
    });
    pip.addEventListener('pointermove', (ev) => {
      if (!drag || !stage) {
        return;
      }
      ev.preventDefault();
      const r = stage.getBoundingClientRect();
      const xy = clamp(
        (ev.clientX - r.left - drag.dx) / Math.max(1, r.width),
        (ev.clientY - r.top - drag.dy) / Math.max(1, r.height)
      );
      camX = xy[0];
      camY = xy[1];
      pip.style.left = (camX * 100) + '%';
      pip.style.top = (camY * 100) + '%';
    });
    function endDrag() {
      if (!drag) {
        return;
      }
      drag = null;
      pip.classList.remove('is-drag');
      sendLayout();
    }
    pip.addEventListener('pointerup', endDrag);
    pip.addEventListener('pointercancel', endDrag);
  }

  window.addEventListener('resize', () => {
    if (full) {
      placePip();
    }
  });
})();
