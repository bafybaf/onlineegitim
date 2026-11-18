(function () {
  var box = document.getElementById('vod-box');
  var video = document.getElementById('vod-player');
  if (!box || !video) return;

  var hlsSrc = box.getAttribute('data-hls') || '';
  var mp4Src = box.getAttribute('data-mp4') || '';
  var fileSrc = box.getAttribute('data-src') || '';
  var iframeSrc = box.getAttribute('data-iframe') || '';
  var hint = parseFloat(box.getAttribute('data-duration') || '0') || 0;
  var hlsObj = null;
  var mode = '';
  var mp4Tries = 0;
  var freezeHls = false;
  var seenDur = 0;
  var stableTimer = null;
  var resumeAt = 0;
  var wantPlay = false;
  var mp4RetryTimer = null;
  var lastLiveTotal = 0;

  function durationOk() {
    var d = video.duration;
    return typeof d === 'number' && isFinite(d) && d > 1;
  }

  function destroyHls() {
    if (!hlsObj) return;
    try { hlsObj.destroy(); } catch (e) {}
    hlsObj = null;
  }

  function rememberPos() {
    resumeAt = video.currentTime || 0;
    wantPlay = !video.paused;
  }

  function restorePos() {
    if (resumeAt > 0.4 && durationOk()) {
      try { video.currentTime = Math.min(resumeAt, Math.max(0, video.duration - 0.25)); } catch (e) {}
    }
    if (wantPlay) {
      video.play().catch(function () {});
    }
    resumeAt = 0;
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
    if (!iframeSrc || mode === 'iframe') return false;
    mode = 'iframe';
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

  function endlistLoader() {
    var Base = window.Hls && Hls.DefaultConfig ? Hls.DefaultConfig.loader : null;
    if (!Base) return null;
    function Loader(config) {
      Base.call(this, config);
    }
    Loader.prototype = Object.create(Base.prototype);
    Loader.prototype.constructor = Loader;
    Loader.prototype.load = function (context, config, callbacks) {
      var onSuccess = callbacks.onSuccess;
      callbacks.onSuccess = function (response, stats, ctx, networkDetails) {
        var data = response && response.data;
        if (typeof data === 'string' && data.indexOf('#EXTINF') !== -1 && data.indexOf('#EXT-X-ENDLIST') === -1) {
          response.data = data.replace(/\s*$/, '\n#EXT-X-ENDLIST\n');
        }
        onSuccess(response, stats, ctx, networkDetails);
      };
      return Base.prototype.load.call(this, context, config, callbacks);
    };
    return Loader;
  }

  function freezePlaylist() {
    if (freezeHls || mode !== 'hls' || !hlsSrc) return;
    freezeHls = true;
    rememberPos();
    destroyHls();
    startHls(hlsSrc);
  }

  function scheduleFreeze(ms) {
    clearTimeout(stableTimer);
    stableTimer = setTimeout(freezePlaylist, ms);
  }

  function onLivePlaylist(details) {
    var total = Number((details && details.totalduration) || 0);
    if (mp4Src && mp4Tries === 0) {
      useMp4();
      return;
    }
    if (hint > 20 && total >= hint * 0.96) {
      scheduleFreeze(400);
      return;
    }
    if (total > lastLiveTotal + 0.8) {
      lastLiveTotal = total;
      scheduleFreeze(8000);
      return;
    }
    if (!stableTimer) {
      scheduleFreeze(8000);
    }
  }

  function startHls(url) {
    mode = 'hls';
    if (video.canPlayType('application/vnd.apple.mpegurl') && !freezeHls) {
      video.src = url;
      return true;
    }
    if (window.Hls && Hls.isSupported()) {
      destroyHls();
      var cfg = {
        enableWorker: true,
        lowLatencyMode: false,
        liveDurationInfinity: false,
        startPosition: resumeAt > 0.4 ? resumeAt : 0,
        maxBufferLength: 30,
        maxMaxBufferLength: 90,
        backBufferLength: 120
      };
      var Loader = freezeHls ? endlistLoader() : null;
      if (Loader) {
        cfg.pLoader = Loader;
      }
      hlsObj = new Hls(cfg);
      hlsObj.loadSource(url);
      hlsObj.attachMedia(video);
      hlsObj.on(Hls.Events.LEVEL_LOADED, function (_e, data) {
        if (!data || !data.details) return;
        if (!data.details.live) {
          clearTimeout(stableTimer);
          return;
        }
        onLivePlaylist(data.details);
      });
      hlsObj.on(Hls.Events.LEVEL_UPDATED, function (_e, data) {
        if (!data || !data.details || !data.details.live) return;
        onLivePlaylist(data.details);
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

  function useMp4() {
    if (!mp4Src || mode === 'mp4') return false;
    mp4Tries += 1;
    rememberPos();
    destroyHls();
    clearTimeout(stableTimer);
    mode = 'mp4';
    video.removeAttribute('src');
    video.src = mp4Src;
    try { video.load(); } catch (e) {}
    return true;
  }

  function retryMp4Later() {
    if (!mp4Src || mp4RetryTimer) return;
    mp4RetryTimer = setTimeout(function () {
      mp4RetryTimer = null;
      if (mode === 'mp4' || !mp4Src) return;
      mp4Tries = Math.min(mp4Tries, 1);
      useMp4();
    }, 12000);
  }

  video.addEventListener('loadedmetadata', function () {
    restorePos();
  });

  video.addEventListener('durationchange', function () {
    if (mode !== 'hls') return;
    if (!durationOk()) return;
    if (seenDur > 1 && video.duration > seenDur + 1.2) {
      if (mp4Src && mp4Tries === 0) {
        useMp4();
      } else {
        scheduleFreeze(2500);
      }
    }
    if (video.duration > seenDur) {
      seenDur = video.duration;
    }
  });

  video.addEventListener('error', function () {
    if (mode === 'mp4' && hlsSrc) {
      mode = '';
      retryMp4Later();
      if (!startHls(hlsSrc) && !showIframe()) showErr();
      return;
    }
    if (mode === 'hls' && useMp4()) return;
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

  if (mp4Src) {
    useMp4();
  } else if (hlsSrc) {
    if (!startHls(hlsSrc) && !showIframe()) showErr();
  } else if (fileSrc) {
    mode = 'file';
    video.src = fileSrc;
  } else if (!showIframe()) {
    showErr();
  }
})();
