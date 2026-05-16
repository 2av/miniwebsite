(function () {
  'use strict';

  function qs(id) { return document.getElementById(id); }

  var toggle = qs('docNavToggle');
  var scrim = qs('docScrim');
  var body = document.body;

  function setOpen(open) {
    if (open) body.classList.add('doc-nav-open');
    else body.classList.remove('doc-nav-open');
    if (scrim) scrim.hidden = !open;
  }

  if (toggle) {
    toggle.addEventListener('click', function () {
      setOpen(!body.classList.contains('doc-nav-open'));
    });
  }
  if (scrim) {
    scrim.addEventListener('click', function () { setOpen(false); });
  }

  document.querySelectorAll('[data-section-wrap]').forEach(function (wrap) {
    var btn = wrap.querySelector('.doc-nav-section-toggle');
    var list = wrap.querySelector('.doc-nav-list');
    if (!btn || !list) return;
    btn.addEventListener('click', function () {
      var collapsed = wrap.classList.toggle('is-collapsed');
      btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    });
  });

  var search = qs('docNavSearch');
  if (search) {
    search.addEventListener('input', function () {
      var q = (search.value || '').trim().toLowerCase();
      var items = document.querySelectorAll('.doc-nav-item');
      items.forEach(function (li) {
        var t = (li.getAttribute('data-search') || '') + ' ' + (li.getAttribute('data-title') || '') + ' ' + (li.getAttribute('data-slug') || '');
        if (!q || t.indexOf(q) !== -1) li.classList.remove('doc-nav-hidden');
        else li.classList.add('doc-nav-hidden');
      });
      document.querySelectorAll('[data-section-wrap]').forEach(function (wrap) {
        var visible = wrap.querySelector('.doc-nav-item:not(.doc-nav-hidden)');
        if (q && visible) wrap.classList.remove('is-collapsed');
      });
    });
  }

  document.querySelectorAll('a[href^="#"]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      var id = a.getAttribute('href');
      if (!id || id === '#') return;
      var el = document.querySelector(id);
      if (el) {
        e.preventDefault();
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        setOpen(false);
      }
    });
  });
})();
