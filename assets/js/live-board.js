(function () {
  const cfg = window.LIVE_BOARD || {};
  const stage = document.getElementById('board-stage');
  const bg = document.getElementById('board-bg');
  const draw = document.getElementById('board-draw');
  if (!stage || !bg || !draw) return;

  const publish = !!cfg.publish;
  const roomId = Number(cfg.roomId || 0);
  const api = cfg.url || '';
  const pageEl = document.getElementById('board-page');
  const sizeEl = document.getElementById('board-size');
  const pdfInput = document.getElementById('board-pdf');

  let rev = -1;
  let page = 1;
  let pages = 0;
  let pdfUrl = '';
  let pdfDoc = null;
  let pageBmp = null;
  let pageBmpKey = '';
  let pageGen = 0;
  let strokes = {};
  let tool = 'pen';
  let color = '#111827';
  let size = 4;
  let drawing = false;
  let panning = false;
  let current = null;
  let busy = false;
  let zoom = 1;
  let panX = 0;
  let panY = 0;
  let panStart = null;
  let viewTimer = 0;
  const zoomEl = document.getElementById('board-zoom');

  function pageKey(n) {
    return String(n || page);
  }

  function pageStrokes(n) {
    const list = strokes[pageKey(n)];
    return Array.isArray(list) ? list : [];
  }

  function clampPan() {
    const max = Math.max(0, (zoom - 1) / 2 + 0.02);
    panX = Math.max(-max, Math.min(max, panX));
    panY = Math.max(-max, Math.min(max, panY));
  }

  function nudgePan(dx, dy) {
    panX += dx;
    panY += dy;
    clampPan();
    paintAll();
    scheduleView();
  }

  function applyView(ctx, w, h) {
    ctx.translate(w * (0.5 + panX), h * (0.5 + panY));
    ctx.scale(zoom, zoom);
    ctx.translate(-w / 2, -h / 2);
  }

  function screenToPage(ev) {
    const r = draw.getBoundingClientRect();
    const sx = (ev.clientX - r.left) / Math.max(1, r.width);
    const sy = (ev.clientY - r.top) / Math.max(1, r.height);
    return [
      (sx - 0.5 - panX) / zoom + 0.5,
      (sy - 0.5 - panY) / zoom + 0.5
    ];
  }

  function pos(ev) {
    const xy = screenToPage(ev);
    const pr = ev.pointerType === 'pen' && ev.pressure > 0 ? ev.pressure : 1;
    return [Math.max(0, Math.min(1, xy[0])), Math.max(0, Math.min(1, xy[1])), Math.max(0.08, Math.min(1, pr))];
  }

  function setZoom(next, around) {
    const prev = zoom;
    zoom = Math.round(Math.max(1, Math.min(6, next)) * 100) / 100;
    if (zoom <= 1) {
      zoom = 1;
      panX = 0;
      panY = 0;
    } else if (around && prev > 0) {
      panX += (around[0] - 0.5) * (prev - zoom);
      panY += (around[1] - 0.5) * (prev - zoom);
    }
    clampPan();
    if (zoomEl) zoomEl.textContent = Math.round(zoom * 100) + '%';
    paintAll();
  }

  function scheduleView() {
    if (!publish) return;
    clearTimeout(viewTimer);
    viewTimer = setTimeout(() => {
      send({ op: 'view', zoom: zoom, panX: panX, panY: panY });
    }, 180);
  }

  function fit() {
    const r = stage.getBoundingClientRect();
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    [bg, draw].forEach((c) => {
      c.width = Math.max(1, Math.floor(r.width * dpr));
      c.height = Math.max(1, Math.floor(r.height * dpr));
      c.style.width = r.width + 'px';
      c.style.height = r.height + 'px';
    });
    paintAll();
  }

  function renderQuality() {
    return zoom >= 3.2 ? 3 : (zoom >= 1.6 ? 2 : 1);
  }

  function blitBg() {
    const ctx = bg.getContext('2d');
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, bg.width, bg.height);
    applyView(ctx, bg.width, bg.height);
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';
    if (pageBmp) {
      ctx.drawImage(pageBmp, 0, 0, bg.width, bg.height);
    }
  }

  function paintBg() {
    blitBg();
    if (!pdfDoc) {
      pageBmp = null;
      pageBmpKey = '';
      return;
    }
    const q = renderQuality();
    const key = pdfUrl + ':' + page + ':' + bg.width + ':' + bg.height + ':' + q;
    if (pageBmp && pageBmpKey === key) {
      return;
    }
    const gen = ++pageGen;
    pdfDoc.getPage(page).then((pg) => {
      if (gen !== pageGen) return;
      const off = document.createElement('canvas');
      off.width = Math.max(1, Math.floor(bg.width * q));
      off.height = Math.max(1, Math.floor(bg.height * q));
      const octx = off.getContext('2d');
      octx.fillStyle = '#ffffff';
      octx.fillRect(0, 0, off.width, off.height);
      const cssW = bg.clientWidth || bg.width;
      const cssH = bg.clientHeight || bg.height;
      const base = pg.getViewport({ scale: 1 });
      const fit = Math.min(cssW / base.width, cssH / base.height);
      const vp = pg.getViewport({ scale: fit * (off.width / Math.max(1, cssW)) });
      const x = (off.width - vp.width) / 2;
      const y = (off.height - vp.height) / 2;
      octx.save();
      octx.translate(x, y);
      return pg.render({ canvasContext: octx, viewport: vp }).promise.then(() => {
        octx.restore();
        if (gen !== pageGen) return;
        pageBmp = off;
        pageBmpKey = key;
        blitBg();
        paintDraw();
      });
    }).catch(() => {});
  }

  function strokeWidth(s, p) {
    const w = Number(s.w) || 3;
    const pr = (p && p[2]) ? p[2] : 1;
    return Math.max(1, w * pr);
  }

  function paintStroke(ctx, s, cssW, cssH, dpr) {
    const pts = s && s.p;
    if (!pts || !pts.length) return;
    ctx.save();
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.globalCompositeOperation = s.t === 'erase' ? 'destination-out' : 'source-over';
    ctx.strokeStyle = s.c || '#111827';
    ctx.beginPath();
    pts.forEach((p, i) => {
      const x = p[0] * cssW * dpr;
      const y = p[1] * cssH * dpr;
      ctx.lineWidth = strokeWidth(s, p) * dpr;
      if (i === 0) ctx.moveTo(x, y);
      else ctx.lineTo(x, y);
    });
    if (pts.length === 1) {
      const p = pts[0];
      ctx.lineTo(p[0] * cssW * dpr + 0.1, p[1] * cssH * dpr);
    }
    ctx.stroke();
    ctx.restore();
  }

  function paintDraw() {
    const ctx = draw.getContext('2d');
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.clearRect(0, 0, draw.width, draw.height);
    applyView(ctx, draw.width, draw.height);
    const cssW = draw.clientWidth || 1;
    const cssH = draw.clientHeight || 1;
    const dpr = draw.width / cssW;
    pageStrokes().forEach((s) => paintStroke(ctx, s, cssW, cssH, dpr));
    if (current) paintStroke(ctx, current, cssW, cssH, dpr);
  }

  function paintAll() {
    paintBg();
    paintDraw();
    if (pageEl) {
      pageEl.textContent = pages > 0 ? (page + '/' + pages) : String(page);
    }
  }

  function applyState(j) {
    if (!j || !j.ok) return;
    if (j.same) return;
    const firstView = rev < 0;
    rev = Number(j.rev || 0);
    page = Math.max(1, Number(j.page || 1));
    pages = Math.max(0, Number(j.pages || 0));
    if (!publish || firstView) {
      if (j.zoom != null) zoom = Math.max(1, Math.min(6, Number(j.zoom) || 1));
      if (j.panX != null) panX = Number(j.panX) || 0;
      if (j.panY != null) panY = Number(j.panY) || 0;
      clampPan();
      if (zoomEl) zoomEl.textContent = Math.round(zoom * 100) + '%';
    }
    strokes = j.strokes && typeof j.strokes === 'object' ? j.strokes : {};
    if (!publish) {
      const screenOn = !!j.screen;
      stage.classList.toggle('is-screen', screenOn);
      if (typeof window.liveScreenWatch === 'function') {
        window.liveScreenWatch(screenOn);
      }
    }
    const nextPdf = j.pdf || '';
    if (nextPdf !== pdfUrl) {
      pdfUrl = nextPdf;
      pdfDoc = null;
      if (pdfUrl && window.pdfjsLib) {
        window.pdfjsLib.getDocument({ url: pdfUrl, withCredentials: true }).promise.then((doc) => {
          pdfDoc = doc;
          if (!pages) pages = doc.numPages;
          if (publish && doc.numPages && doc.numPages !== pages) {
            send({ op: 'page', page: page, pages: doc.numPages });
          }
          paintAll();
        }).catch(() => { pdfDoc = null; paintAll(); });
        return;
      }
    }
    paintAll();
  }

  function send(payload) {
    payload.id = roomId;
    return fetch(api + '?action=board', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(payload)
    }).then((r) => r.json()).then((j) => {
      if (j && j.ok && !j.same) applyState(j);
      return j;
    }).catch(() => null);
  }

  function pull() {
    if (busy) return;
    busy = true;
    fetch(api + '?action=board&id=' + roomId + '&since=' + encodeURIComponent(rev), { cache: 'no-store' })
      .then((r) => r.json())
      .then(applyState)
      .catch(() => {})
      .finally(() => { busy = false; });
  }

  if (publish) {
    document.querySelectorAll('[data-tool]').forEach((b) => {
      b.addEventListener('click', () => {
        const next = b.getAttribute('data-tool') || 'pen';
        tool = next === 'erase' ? 'erase' : (next === 'pan' ? 'pan' : 'pen');
        document.querySelectorAll('[data-tool]').forEach((x) => x.classList.toggle('is-on', x === b));
        draw.style.cursor = tool === 'pan' ? 'grab' : 'crosshair';
      });
    });
    draw.addEventListener('contextmenu', (ev) => ev.preventDefault());
    document.querySelectorAll('[data-color]').forEach((b) => {
      b.addEventListener('click', () => {
        color = b.getAttribute('data-color') || '#111827';
        document.querySelectorAll('[data-color]').forEach((x) => x.classList.toggle('is-on', x === b));
        tool = 'pen';
        document.querySelectorAll('[data-tool]').forEach((x) => x.classList.toggle('is-on', x.getAttribute('data-tool') === 'pen'));
      });
    });
    document.querySelectorAll('[data-act]').forEach((b) => {
      b.addEventListener('click', () => {
        const act = b.getAttribute('data-act');
        if (act === 'undo') send({ op: 'undo' });
        if (act === 'clear') send({ op: 'clear' });
        if (act === 'prev') send({ op: 'page', page: page - 1, pages: pages });
        if (act === 'next') send({ op: 'page', page: page + 1, pages: pages });
        if (act === 'zoomin') { setZoom(zoom + 0.25); scheduleView(); }
        if (act === 'zoomout') { setZoom(zoom - 0.25); scheduleView(); }
        if (act === 'zoomreset') { setZoom(1); scheduleView(); }
      });
    });
    if (sizeEl) {
      sizeEl.addEventListener('input', () => { size = Number(sizeEl.value) || 4; });
    }
    if (pdfInput) {
      pdfInput.addEventListener('change', () => {
        const file = pdfInput.files && pdfInput.files[0];
        pdfInput.value = '';
        if (!file) return;
        const fd = new FormData();
        fd.append('action', 'board');
        fd.append('op', 'pdf');
        fd.append('id', String(roomId));
        fd.append('file', file);
        fetch(api, { method: 'POST', body: fd })
          .then((r) => r.json())
          .then(applyState)
          .catch(() => {});
      });
    }

    window.liveBoardSetScreen = function (on) {
      stage.classList.toggle('is-screen', !!on);
      send({ op: 'screen', on: on ? 1 : 0 });
    };

    stage.addEventListener('wheel', (ev) => {
      ev.preventDefault();
      if (zoom <= 1) return;
      const r = draw.getBoundingClientRect();
      let dx = ev.deltaX || 0;
      let dy = ev.deltaY || 0;
      if (ev.shiftKey && Math.abs(dx) < 1) {
        dx = dy;
        dy = 0;
      }
      nudgePan(-dx / Math.max(1, r.width), -dy / Math.max(1, r.height));
    }, { passive: false });
    draw.addEventListener('pointerdown', (ev) => {
      if (ev.button === 1 || ev.button === 2 || ev.altKey || tool === 'pan') {
        ev.preventDefault();
        draw.setPointerCapture(ev.pointerId);
        panning = true;
        panStart = { x: ev.clientX, y: ev.clientY, panX: panX, panY: panY };
        return;
      }
      if (ev.button !== 0 && ev.pointerType === 'mouse') return;
      ev.preventDefault();
      draw.setPointerCapture(ev.pointerId);
      drawing = true;
      current = { t: tool === 'erase' ? 'erase' : 'pen', c: color, w: size, p: [pos(ev)] };
      paintDraw();
    });
    draw.addEventListener('pointermove', (ev) => {
      if (panning && panStart) {
        ev.preventDefault();
        const r = draw.getBoundingClientRect();
        panX = panStart.panX + (ev.clientX - panStart.x) / Math.max(1, r.width);
        panY = panStart.panY + (ev.clientY - panStart.y) / Math.max(1, r.height);
        clampPan();
        paintAll();
        scheduleView();
        return;
      }
      if (!drawing || !current) return;
      ev.preventDefault();
      const p = pos(ev);
      const last = current.p[current.p.length - 1];
      const dx = p[0] - last[0];
      const dy = p[1] - last[1];
      if ((dx * dx + dy * dy) < 0.00002 && current.p.length > 1) return;
      current.p.push(p);
      paintDraw();
    });
    function endStroke() {
      if (panning) {
        panning = false;
        panStart = null;
        scheduleView();
      }
      if (!drawing || !current) {
        drawing = false;
        current = null;
        return;
      }
      drawing = false;
      const stroke = current;
      current = null;
      const key = pageKey();
      if (!Array.isArray(strokes[key])) strokes[key] = [];
      strokes[key].push(stroke);
      paintDraw();
      send({ op: 'stroke', stroke: stroke });
    }
    draw.addEventListener('pointerup', endStroke);
    draw.addEventListener('pointercancel', endStroke);
    draw.addEventListener('lostpointercapture', () => {
      if (drawing) endStroke();
    });
  } else {
    draw.style.pointerEvents = 'none';
  }

  window.addEventListener('resize', fit);
  if (window.ResizeObserver) {
    new ResizeObserver(fit).observe(stage);
  }
  fit();
  pull();
  setInterval(pull, publish ? 1200 : 400);
})();
