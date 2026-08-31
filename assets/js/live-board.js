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
  let pages = 0;
  let pdfUrl = '';
  let pdfDoc = null;
  let layouts = [];
  let docH = 1;
  let pageCache = {};
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
  let originX = 0;
  let originY = 0;
  let panStart = null;
  let viewTimer = 0;
  let uploadLock = false;
  let measureToken = 0;
  const zoomEl = document.getElementById('board-zoom');
  const GAP = 0.012;
  const MAX_ZOOM = 40;

  function pdfId(u) {
    const s = String(u || '');
    if (!s) return '';
    return s.replace(/([?&])v=[^&]*/g, '$1').replace(/[?&]$/, '');
  }

  function aspect() {
    const w = Math.max(1, bg.clientWidth || bg.width || 1);
    const h = Math.max(1, bg.clientHeight || bg.height || 1);
    return h / w;
  }

  function contentH() {
    if (layouts.length) {
      return Math.max(docH, aspect());
    }
    return aspect();
  }

  function minZoom() {
    const a = aspect();
    const h = contentH();
    if (h <= a) return 0.25;
    return Math.max(0.08, Math.min(1, (a / h) * 0.98));
  }

  function allStrokes() {
    const out = [];
    Object.keys(strokes).forEach((k) => {
      if (Array.isArray(strokes[k])) out.push.apply(out, strokes[k]);
    });
    return out;
  }

  function viewDocX(sx) {
    return originX + sx / Math.max(0.01, zoom);
  }

  function viewDocY(sy) {
    return originY + (sy * aspect()) / Math.max(0.01, zoom);
  }

  function clampPan() {
    const a = aspect();
    const viewW = 1 / Math.max(0.01, zoom);
    const viewH = a / Math.max(0.01, zoom);
    const maxX = Math.max(0, 1 - viewW);
    const maxY = Math.max(0, contentH() - viewH);
    if (maxX <= 0) originX = (1 - viewW) / 2;
    else originX = Math.max(0, Math.min(maxX, originX));
    originY = Math.max(0, Math.min(maxY, originY));
  }

  function nudgePan(dx, dy) {
    originX -= dx / Math.max(0.01, zoom);
    originY -= (dy * aspect()) / Math.max(0.01, zoom);
    clampPan();
    paintAll();
    scheduleView();
  }

  function applyView(ctx, w) {
    const k = w * zoom;
    ctx.translate(-originX * k, -originY * k);
    ctx.scale(zoom, zoom);
  }

  function screenToDoc(ev) {
    const r = draw.getBoundingClientRect();
    const sx = (ev.clientX - r.left) / Math.max(1, r.width);
    const sy = (ev.clientY - r.top) / Math.max(1, r.height);
    const ch = Math.max(0.01, contentH());
    return [viewDocX(sx), viewDocY(sy) / ch];
  }

  function pos(ev) {
    const xy = screenToDoc(ev);
    const pr = ev.pointerType === 'pen' && ev.pressure > 0 ? ev.pressure : 1;
    return [xy[0], xy[1], Math.max(0.08, Math.min(1, pr))];
  }

  function setZoom(next, around) {
    const a = aspect();
    const sx = around ? around[0] : 0.5;
    const sy = around ? around[1] : 0.5;
    const holdX = viewDocX(sx);
    const holdY = viewDocY(sy);
    zoom = Math.round(Math.max(minZoom(), Math.min(MAX_ZOOM, next)) * 100) / 100;
    originX = holdX - sx / zoom;
    originY = holdY - (sy * a) / zoom;
    clampPan();
    if (zoomEl) zoomEl.textContent = Math.round(zoom * 100) + '%';
    paintAll();
  }

  function scheduleView() {
    if (!publish) return;
    clearTimeout(viewTimer);
    viewTimer = setTimeout(() => {
      send({ op: 'view', zoom: zoom, panX: originX, panY: originY });
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
    pageCache = {};
    if (!layouts.length) {
      docH = aspect();
    }
    paintAll();
  }

  function destSize() {
    return {
      w: bg.width,
      h: bg.width * contentH()
    };
  }

  function blitBg() {
    const ctx = bg.getContext('2d');
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.fillStyle = '#e7e5e4';
    ctx.fillRect(0, 0, bg.width, bg.height);
    applyView(ctx, bg.width);
    const d = destSize();
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, d.w, d.h);
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';
    layouts.forEach((lay) => {
      const y = (lay.y / Math.max(0.01, contentH())) * d.h;
      const h = (lay.h / Math.max(0.01, contentH())) * d.h;
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0, y, d.w, h);
      const cached = pageCache[lay.n];
      if (cached && cached.canvas) {
        ctx.drawImage(cached.canvas, 0, y, d.w, h);
      }
    });
  }

  function visibleLayouts() {
    const y0 = viewDocY(0) - 0.15;
    const y1 = viewDocY(1) + 0.15;
    return layouts.filter((lay) => lay.y + lay.h >= y0 && lay.y <= y1);
  }

  function renderPage(lay, gen) {
    if (!pdfDoc || gen !== pageGen) return;
    const cssW = bg.clientWidth || bg.width || 1;
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const targetW = Math.min(2200, Math.max(280, Math.floor(cssW * Math.min(zoom, 8) * dpr)));
    const key = targetW;
    const cached = pageCache[lay.n];
    if (cached && cached.key === key) return;
    if (cached && cached.pending === key) return;
    pageCache[lay.n] = Object.assign({}, cached || {}, { pending: key });
    pdfDoc.getPage(lay.n).then((pg) => {
      if (gen !== pageGen) return;
      const base = pg.getViewport({ scale: 1 });
      const vp = pg.getViewport({ scale: targetW / Math.max(1, base.width) });
      const off = document.createElement('canvas');
      off.width = Math.max(1, Math.floor(vp.width));
      off.height = Math.max(1, Math.floor(vp.height));
      const octx = off.getContext('2d', { alpha: false });
      octx.fillStyle = '#ffffff';
      octx.fillRect(0, 0, off.width, off.height);
      return pg.render({ canvasContext: octx, viewport: vp }).promise.then(() => {
        if (gen !== pageGen) return;
        pageCache[lay.n] = { canvas: off, key: key };
        const keep = {};
        visibleLayouts().forEach((v) => { keep[v.n] = true; });
        Object.keys(pageCache).forEach((k) => {
          if (!keep[k] && Object.keys(pageCache).length > 8) {
            delete pageCache[k];
          }
        });
        blitBg();
        paintDraw();
      });
    }).catch(() => {
      if (pageCache[lay.n] && pageCache[lay.n].pending === key) {
        delete pageCache[lay.n].pending;
      }
    });
  }

  function paintBg() {
    blitBg();
    if (!pdfDoc || !layouts.length) return;
    const gen = pageGen;
    visibleLayouts().forEach((lay) => renderPage(lay, gen));
  }

  function strokeWidth(s, p) {
    const w = Number(s.w) || 3;
    const pr = (p && p[2]) ? p[2] : 1;
    return Math.max(1, w * pr);
  }

  function paintStroke(ctx, s, destW, destH, px) {
    const pts = s && s.p;
    if (!pts || !pts.length) return;
    ctx.save();
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.globalCompositeOperation = s.t === 'erase' ? 'destination-out' : 'source-over';
    ctx.strokeStyle = s.c || '#111827';
    ctx.beginPath();
    pts.forEach((p, i) => {
      const x = p[0] * destW;
      const y = p[1] * destH;
      ctx.lineWidth = strokeWidth(s, p) * px;
      if (i === 0) ctx.moveTo(x, y);
      else ctx.lineTo(x, y);
    });
    if (pts.length === 1) {
      const p = pts[0];
      ctx.lineTo(p[0] * destW + 0.1, p[1] * destH);
    }
    ctx.stroke();
    ctx.restore();
  }

  function paintDraw() {
    const ctx = draw.getContext('2d');
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.clearRect(0, 0, draw.width, draw.height);
    applyView(ctx, draw.width);
    const destW = draw.width;
    const destH = draw.width * contentH();
    const px = draw.width / Math.max(1, draw.clientWidth || draw.width);
    allStrokes().forEach((s) => paintStroke(ctx, s, destW, destH, px));
    if (current) paintStroke(ctx, current, destW, destH, px);
  }

  function paintAll() {
    paintBg();
    paintDraw();
    if (pageEl) {
      pageEl.textContent = pages > 0 ? (pages + ' syf') : '';
    }
  }

  function resetDoc() {
    layouts = [];
    docH = aspect();
    pageCache = {};
    pageGen += 1;
  }

  function loadPdf(url) {
    if (!window.pdfjsLib || !url) {
      pdfDoc = null;
      resetDoc();
      paintAll();
      return;
    }
    const gen = ++pageGen;
    pageCache = {};
    window.pdfjsLib.getDocument({ url: url, withCredentials: true }).promise.then((doc) => {
      if (gen !== pageGen) return;
      pdfDoc = doc;
      pages = doc.numPages;
      measurePages(doc, gen);
    }).catch(() => {
      if (gen !== pageGen) return;
      pdfDoc = null;
      resetDoc();
      paintAll();
    });
  }

  function measurePages(doc, gen) {
    const token = ++measureToken;
    layouts = [];
    docH = aspect();
    const run = (i) => {
      if (token !== measureToken || gen !== pageGen) return;
      if (i > doc.numPages) {
        if (publish && doc.numPages) {
          send({ op: 'page', page: 1, pages: doc.numPages });
        }
        clampPan();
        paintAll();
        return;
      }
      doc.getPage(i).then((pg) => {
        if (token !== measureToken || gen !== pageGen) return;
        const base = pg.getViewport({ scale: 1 });
        const h = base.height / Math.max(1, base.width);
        const y = layouts.length ? (layouts[layouts.length - 1].y + layouts[layouts.length - 1].h + GAP) : 0;
        layouts.push({ n: i, y: y, h: h });
        docH = y + h;
        if (i === 1 || i % 4 === 0) {
          clampPan();
          paintAll();
        }
        run(i + 1);
      }).catch(() => run(i + 1));
    };
    run(1);
  }

  function applyState(j) {
    if (!j || !j.ok) return;
    if (j.same) return;
    const firstView = rev < 0;
    rev = Number(j.rev || 0);
    pages = Math.max(0, Number(j.pages || 0));
    if (!publish || firstView) {
      if (j.zoom != null) zoom = Math.max(minZoom(), Math.min(MAX_ZOOM, Number(j.zoom) || 1));
      if (j.panX != null) originX = Number(j.panX) || 0;
      if (j.panY != null) originY = Number(j.panY) || 0;
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
    const pdfOff = document.getElementById('board-pdf-off');
    const pdfText = document.getElementById('board-pdf-text');
    if (pdfOff) pdfOff.hidden = !nextPdf && !uploadLock;
    if (pdfText && !uploadLock) pdfText.textContent = nextPdf ? 'PDF değiştir' : 'PDF';
    if (uploadLock && publish) {
      paintAll();
      return;
    }
    if (pdfId(nextPdf) !== pdfId(pdfUrl)) {
      if (pdfDoc && nextPdf && String(pdfUrl).indexOf('blob:') === 0) {
        pdfUrl = nextPdf;
        paintAll();
        return;
      }
      pdfUrl = nextPdf;
      pdfDoc = null;
      resetDoc();
      zoom = 1;
      originX = 0;
      originY = 0;
      if (zoomEl) zoomEl.textContent = '100%';
      if (!pdfUrl) {
        paintAll();
        return;
      }
      loadPdf(pdfUrl);
      return;
    }
    pdfUrl = nextPdf || pdfUrl;
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
    if (busy || uploadLock) return;
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
        document.querySelectorAll('[data-color]').forEach((x) => x.classList.toggle('is-on', x.getAttribute('data-color') === color));
        tool = 'pen';
        document.querySelectorAll('[data-tool]').forEach((x) => x.classList.toggle('is-on', x.getAttribute('data-tool') === 'pen'));
      });
    });
    document.querySelectorAll('[data-act]').forEach((b) => {
      b.addEventListener('click', () => {
        const act = b.getAttribute('data-act');
        if (act === 'undo') send({ op: 'undo' });
        if (act === 'clear') send({ op: 'clear' });
        if (act === 'zoomin') { setZoom(zoom * 1.25); scheduleView(); }
        if (act === 'zoomout') { setZoom(zoom / 1.25); scheduleView(); }
        if (act === 'zoomreset') { originX = 0; originY = 0; setZoom(1); scheduleView(); }
        if (act === 'pdf_off') send({ op: 'pdf_off' });
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
        const pdfText = document.getElementById('board-pdf-text');
        const pdfOff = document.getElementById('board-pdf-off');
        if (pdfText) pdfText.textContent = 'Yükleniyor…';
        if (pdfOff) pdfOff.hidden = false;
        uploadLock = true;
        const local = URL.createObjectURL(file);
        pdfUrl = local;
        zoom = 1;
        originX = 0;
        originY = 0;
        if (zoomEl) zoomEl.textContent = '100%';
        loadPdf(local);
        const fd = new FormData();
        fd.append('action', 'board');
        fd.append('op', 'pdf');
        fd.append('id', String(roomId));
        fd.append('file', file);
        fetch(api, { method: 'POST', body: fd })
          .then((r) => r.json())
          .then((j) => {
            uploadLock = false;
            if (local.indexOf('blob:') === 0) {
              try { URL.revokeObjectURL(local); } catch (e) {}
            }
            if (j && j.ok) {
              pdfUrl = j.pdf || pdfUrl;
              if (pdfText) pdfText.textContent = 'PDF değiştir';
              applyState(j);
            } else if (pdfText) {
              pdfText.textContent = 'PDF';
            }
          })
          .catch(() => {
            uploadLock = false;
            if (pdfText) pdfText.textContent = 'PDF';
          });
      });
    }

    window.liveBoardSetScreen = function (on) {
      stage.classList.toggle('is-screen', !!on);
      send({ op: 'screen', on: on ? 1 : 0 });
    };

    stage.addEventListener('wheel', (ev) => {
      ev.preventDefault();
      const r = draw.getBoundingClientRect();
      if (ev.ctrlKey || ev.metaKey) {
        const around = [
          (ev.clientX - r.left) / Math.max(1, r.width),
          (ev.clientY - r.top) / Math.max(1, r.height)
        ];
        const next = ev.deltaY < 0 ? zoom * 1.12 : zoom / 1.12;
        setZoom(next, around);
        scheduleView();
        return;
      }
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
        panStart = { x: ev.clientX, y: ev.clientY, originX: originX, originY: originY };
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
        originX = panStart.originX - (ev.clientX - panStart.x) / (Math.max(1, r.width) * zoom);
        originY = panStart.originY - (ev.clientY - panStart.y) / (Math.max(1, r.width) * zoom);
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
      if ((dx * dx + dy * dy) < 0.0000004 && current.p.length > 1) return;
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
      if (!Array.isArray(strokes['1'])) strokes['1'] = [];
      strokes['1'].push(stroke);
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
