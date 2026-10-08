(function () {
  const cfg = window.LIVE_PLAYER || {};
  if (!cfg.publish) return;

  let dockWin = null;
  let dockKind = '';
  let syncTimer = 0;
  let wantDock = false;

  function cssUrl() {
    const link = document.querySelector('link[href*="site.css"]');
    return link ? link.href : '';
  }

  function camMedia() {
    const video = document.getElementById('live-video');
    return (video && video.srcObject) || null;
  }

  function dockDoc() {
    return dockWin && !dockWin.closed ? dockWin.document : null;
  }

  function fillDock(doc) {
    if (!doc || !doc.body) return;
    const n = document.getElementById('live-present-n');
    const seat = document.getElementById('live-seat-n');
    const count = n ? n.textContent : (seat ? String(seat.textContent || '').replace(/\D.*/, '') : '');
    doc.body.innerHTML =
      '<div class="live-dock">' +
        '<div class="live-dock-bar"><b>Sunum</b><span id="dock-n"></span>' +
          '<button type="button" id="dock-stop">Paylaşımı durdur</button></div>' +
        '<video id="dock-cam" playsinline autoplay muted></video>' +
        '<div id="dock-chat" class="chat-log text-sm"></div>' +
        '<div id="dock-people" class="live-present-list"></div>' +
        '<form id="dock-form" class="live-dock-form">' +
          '<input name="q" autocomplete="off" placeholder="Mesaj yazın">' +
          '<button type="submit">Gönder</button>' +
        '</form>' +
      '</div>';
    const nEl = doc.getElementById('dock-n');
    if (nEl) nEl.textContent = count ? (count + ' derste') : '';
    const stop = doc.getElementById('dock-stop');
    if (stop) {
      stop.onclick = function () {
        if (typeof window.liveShareStop === 'function') window.liveShareStop();
      };
    }
    const form = doc.getElementById('dock-form');
    if (form) {
      form.onsubmit = function (ev) {
        ev.preventDefault();
        const box = form.querySelector('[name="q"]');
        const t = box && box.value ? box.value.trim() : '';
        if (!t) return;
        box.value = '';
        const api = (cfg.api || '') || ((window.base || '') + 'api/live.php');
        const rid = cfg.roomId || window.roomId || 0;
        fetch(api, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'action=chat&room_id=' + rid + '&body=' + encodeURIComponent(t)
        }).catch(function () {});
      };
    }
    syncDock();
  }

  function paintDock(doc) {
    if (!doc) return;
    const cam = doc.getElementById('dock-cam');
    const media = camMedia();
    if (cam && media && cam.srcObject !== media) {
      cam.srcObject = media;
      cam.play().catch(function () {});
    }
    const srcChat = document.getElementById('chat-log');
    const dstChat = doc.getElementById('dock-chat');
    if (srcChat && dstChat && dstChat.innerHTML !== srcChat.innerHTML) {
      dstChat.innerHTML = srcChat.innerHTML;
      dstChat.scrollTop = dstChat.scrollHeight;
    }
    const srcList = document.getElementById('live-present-list');
    const dstList = doc.getElementById('dock-people');
    if (srcList && dstList) {
      const rows = srcList.querySelectorAll('.live-present-row, .live-present-empty');
      const html = Array.prototype.map.call(rows, function (row) {
        const cls = row.classList.contains('live-present-empty') ? 'live-present-empty' : 'live-present-row';
        return '<p class="' + cls + '">' + row.textContent + '</p>';
      }).join('');
      if (dstList.innerHTML !== html) dstList.innerHTML = html;
    }
    const n = document.getElementById('live-present-n');
    const seat = document.getElementById('live-seat-n');
    const nEl = doc.getElementById('dock-n');
    if (nEl) {
      nEl.textContent = n ? (n.textContent + ' derste') : (seat ? seat.textContent : '');
    }
  }

  function syncDock() {
    paintDock(dockDoc());
  }

  function startSync() {
    stopSync();
    syncTimer = setInterval(syncDock, 1000);
  }

  function stopSync() {
    if (syncTimer) {
      clearInterval(syncTimer);
      syncTimer = 0;
    }
  }

  function closeDock() {
    stopSync();
    const win = dockWin;
    dockWin = null;
    dockKind = '';
    if (win && !win.closed) {
      try { win.close(); } catch (e) {}
    }
    if (document.pictureInPictureElement) {
      document.exitPictureInPicture().catch(function () {});
    }
  }

  function bindClose(win) {
    if (!win) return;
    win.addEventListener('pagehide', function () {
      if (dockWin === win) {
        dockWin = null;
        dockKind = '';
        stopSync();
      }
    });
  }

  function writeHead(doc, title) {
    doc.open();
    doc.write(
      '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8">' +
      '<title>' + title + '</title>' +
      (cssUrl() ? '<link rel="stylesheet" href="' + cssUrl() + '">' : '') +
      '<style>html,body{height:100%;margin:0;background:#111;color:#fff}</style>' +
      '</head><body></body></html>'
    );
    doc.close();
  }

  async function openDocPip() {
    if (!('documentPictureInPicture' in window)) return false;
    const pip = await window.documentPictureInPicture.requestWindow({
      width: 380,
      height: 720
    });
    dockWin = pip;
    dockKind = 'doc';
    const doc = pip.document;
    if (cssUrl()) {
      const link = doc.createElement('link');
      link.rel = 'stylesheet';
      link.href = cssUrl();
      doc.head.appendChild(link);
    }
    doc.documentElement.style.height = '100%';
    doc.body.style.cssText = 'margin:0;height:100%;background:#111;color:#fff;';
    fillDock(doc);
    bindClose(pip);
    startSync();
    return true;
  }

  function openPopup() {
    const popup = window.open('', 'live-present-dock', 'width=380,height=720,menubar=no,toolbar=no,location=no,status=no,resizable=yes');
    if (!popup) return false;
    dockWin = popup;
    dockKind = 'popup';
    writeHead(popup.document, 'Sunum');
    fillDock(popup.document);
    bindClose(popup);
    startSync();
    try { popup.focus(); } catch (e) {}
    return true;
  }

  async function openVideoPip() {
    const video = document.getElementById('live-video');
    if (!video || !video.requestPictureInPicture) return false;
    if (!video.srcObject) return false;
    await video.requestPictureInPicture();
    dockKind = 'video';
    return true;
  }

  window.livePresentDock = async function (on) {
    wantDock = !!on;
    if (!on) {
      closeDock();
      return false;
    }
    if (dockWin && !dockWin.closed) {
      syncDock();
      try { dockWin.focus(); } catch (e) {}
      return true;
    }
    try {
      if (await openDocPip()) return true;
    } catch (e) {}
    if (openPopup()) return true;
    try {
      if (await openVideoPip()) return true;
    } catch (e) {}
    return false;
  };

  window.livePresentSync = function () {
    syncDock();
  };

  window.addEventListener('pagehide', function () {
    if (wantDock) closeDock();
  });
})();
