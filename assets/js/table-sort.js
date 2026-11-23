(function () {
  document.querySelectorAll('table[data-sortable]').forEach(function (table) {
    var tbody = table.tBodies[0];
    var heads = table.tHead ? table.tHead.rows[0].cells : [];
    if (!tbody) return;
    Array.prototype.forEach.call(heads, function (th, col) {
      if (th.hasAttribute('data-nosort') || !th.textContent.trim()) return;
      th.style.cursor = 'pointer';
      th.setAttribute('tabindex', '0');
      th.setAttribute('role', 'button');
      var arrow = document.createElement('span');
      arrow.style.cssText = 'margin-left:6px;opacity:.5;font-size:11px';
      arrow.textContent = '↕';
      th.appendChild(arrow);
      function run() {
        var dir = th.getAttribute('data-dir') === 'asc' ? 'desc' : 'asc';
        Array.prototype.forEach.call(heads, function (h) {
          h.removeAttribute('data-dir');
          var a = h.lastElementChild;
          if (a && a.tagName === 'SPAN' && a.textContent.length === 1) { a.textContent = '↕'; a.style.opacity = '.5'; }
        });
        th.setAttribute('data-dir', dir);
        arrow.textContent = dir === 'asc' ? '↑' : '↓';
        arrow.style.opacity = '1';
        var rows = Array.prototype.slice.call(tbody.rows);
        rows.sort(function (a, b) {
          var x = (a.cells[col] ? a.cells[col].textContent : '').trim();
          var y = (b.cells[col] ? b.cells[col].textContent : '').trim();
          var r = x.localeCompare(y, 'tr', { numeric: true, sensitivity: 'base' });
          return dir === 'asc' ? r : -r;
        });
        rows.forEach(function (r) { tbody.appendChild(r); });
      }
      th.addEventListener('click', run);
      th.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); run(); }
      });
    });
  });
})();
