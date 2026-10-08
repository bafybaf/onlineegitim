(function () {
  var KEY = 'oi_cookie_consent';
  function read() {
    try { return JSON.parse(localStorage.getItem(KEY) || ''); } catch (e) { return null; }
  }
  function save(analytics) {
    try {
      localStorage.setItem(KEY, JSON.stringify({ v: 1, necessary: true, analytics: !!analytics, t: Date.now() }));
    } catch (e) {}
  }
  function loadGa() {
    if (!window.OI_GA || window.OI_GA_LOADED) return;
    window.OI_GA_LOADED = 1;
    var s = document.createElement('script');
    s.async = true;
    s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(window.OI_GA);
    document.head.appendChild(s);
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    window.gtag('config', window.OI_GA);
  }
  var c = read();
  if (c && c.analytics) loadGa();
  var bar = document.getElementById('cookie-bar');
  if (!bar) return;
  if (c && c.v) {
    bar.hidden = true;
    return;
  }
  bar.hidden = false;
  bar.querySelectorAll('[data-cookie]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var all = btn.getAttribute('data-cookie') === 'all';
      save(all);
      if (all) loadGa();
      bar.hidden = true;
    });
  });
})();
