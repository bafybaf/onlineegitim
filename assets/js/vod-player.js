(function () {
  var box = document.getElementById('vod-box');
  var video = document.getElementById('vod-player');
  if (!box || !video) return;

  var hlsSrc = box.getAttribute('data-hls') || '';
  var mp4Src = box.getAttribute('data-mp4') || '';
  var fileSrc = box.getAttribute('data-src') || '';
  var iframeSrc = box.getAttribute('data-iframe') || '';
  var hlsObj = null;
  var tried = {};

  function durationOk() {
    var d = video.duration;
    return typeof d === 'number' && isFinite(d) && d > 1;
  }

  function destroyHls() {
    if (!hlsObj) return;
    try { hlsObj.destroy(); } catch (e) {}
    hlsObj = null;
  }

  function showErr() {
    if (box.querySelector('.vod-err')) return;
    var p = document.createElement('p');
    p.className = 'vod-err mt-3 font-bold text-accent';
    p.style.padding = '1rem';
    p.textContent = 'Video şu an açılamadı. Sayfayı yenileyin.';
    box.appendChild(p);
  }

  function showIframe() {
    if (!iframeSrc || tried.iframe) return false;
    tried.iframe = true;
    destroyHls();
    var f = document.createElement('iframe');
    f.src = iframeSrc;
    f.className = 'block w-full bg-black';
    f.setAttribute('allow', 'accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture; fullscreen');
    f.setAttribute('allowfullscreen', '');
    f.style.cssText = 'aspect-ratio:16/9;min-height:240px;border:0';
    if (video.parentNode) {
      video.replaceWith(f);
    } else {
      box.appendChild(f);
    }
    return true;
  }

  function useMp4() {
    if (!mp4Src || tried.mp4) return false;
    tried.mp4 = true;
    destroyHls();
    video.removeAttribute('src');
    video.src = mp4Src;
    try { video.load(); } catch (e) {}
    return true;
  }

  function attachHls(url) {
    if (video.canPlayType('application/vnd.apple.mpegurl')) {
      video.src = url;
      return true;
    }
    if (window.Hls && Hls.isSupported()) {
      destroyHls();
      hlsObj = new Hls({
        enableWorker: true,
        lowLatencyMode: false,
        liveDurationInfinity: false,
        startPosition: 0,
        maxBufferLength: 30,
        maxMaxBufferLength: 90,
        backBufferLength: 120
      });
      hlsObj.loadSource(url);
      hlsObj.attachMedia(video);
      hlsObj.on(Hls.Events.LEVEL_LOADED, function (_e, data) {
        if (!data || !data.details || !data.details.live) return;
        var total = Number(data.details.totalduration || 0);
        if (total > 1 || durationOk()) return;
        useMp4();
      });
      hlsObj.on(Hls.Events.ERROR, function (_e, data) {
        if (!data || !data.fatal) return;
        if (useMp4()) return;
        if (showIframe()) return;
        showErr();
      });
      return true;
    }
    return false;
  }

  video.addEventListener('loadedmetadata', function () {
    if (durationOk()) return;
    if (video.duration === Infinity) {
      if (useMp4()) return;
      showIframe();
    }
  });

  video.addEventListener('error', function () {
    if (hlsSrc && useMp4()) return;
    if (showIframe()) return;
    showErr();
  });

  video.addEventListener('contextmenu', function (e) { e.preventDefault(); });
  window.addEventListener('pagehide', function () {
    try { video.pause(); } catch (e) {}
    destroyHls();
    video.removeAttribute('src');
    try { video.load(); } catch (e) {}
  });

  if (hlsSrc) {
    if (!attachHls(hlsSrc) && !useMp4() && !showIframe()) showErr();
  } else if (fileSrc) {
    video.src = fileSrc;
  } else if (mp4Src) {
    useMp4();
  } else if (!showIframe()) {
    showErr();
  }
})();
