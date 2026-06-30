(function () {
  'use strict';

  var input = document.getElementById('gwmSearchInput');
  var clearBtn = document.getElementById('gwmSearchClear');
  var resultsEl = document.getElementById('gwmSearchResults');
  var menuList = document.getElementById('gwmMenuList');
  var index = window.__GWM_SEARCH__ || [];

  if (!input || !menuList) {
    return;
  }

  function escapeHtml(str) {
    var d = document.createElement('div');
    d.textContent = str == null ? '' : String(str);
    return d.innerHTML;
  }

  function highlightText(text, query) {
    var safe = escapeHtml(text);
    if (!query) {
      return safe;
    }
    var re = new RegExp('(' + query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig');
    return safe.replace(re, '<mark class="gwm-search-mark">$1</mark>');
  }

  function normalizeQuery(q) {
    return (q || '').trim().toLowerCase();
  }

  function scoreItem(item, q) {
    var title = (item.title || '').toLowerCase();
    var section = (item.section || '').toLowerCase();
    var excerpt = (item.excerpt || '').toLowerCase();
    var slug = (item.slug || '').toLowerCase();
    if (title.indexOf(q) !== -1) return 4;
    if (section.indexOf(q) !== -1) return 3;
    if (slug.indexOf(q) !== -1) return 2;
    if (excerpt.indexOf(q) !== -1) return 1;
    return 0;
  }

  function filterIndex(q) {
    if (!q) return [];
    return index
      .map(function (item) {
        return { item: item, score: scoreItem(item, q) };
      })
      .filter(function (row) {
        return row.score > 0;
      })
      .sort(function (a, b) {
        return b.score - a.score;
      })
      .slice(0, 12)
      .map(function (row) {
        return row.item;
      });
  }

  function renderResults(matches, q) {
    if (!resultsEl) return;
    if (!q) {
      resultsEl.hidden = true;
      resultsEl.innerHTML = '';
      return;
    }
    if (!matches.length) {
      resultsEl.hidden = false;
      resultsEl.innerHTML = '<p class="gwm-search-empty">No topics match your search.</p>';
      return;
    }
    var html = matches
      .map(function (item) {
        var url = item.url || '#';
        var titleClass = item.section && item.section.toLowerCase().indexOf(q) !== -1 ? ' gwm-search-result-title--section' : '';
        return (
          '<a class="gwm-search-result" href="' +
          escapeHtml(url) +
          '">' +
          '<span class="gwm-search-result-title' +
          titleClass +
          '">' +
          highlightText(item.title, q) +
          '</span>' +
          (item.excerpt
            ? '<span class="gwm-search-result-excerpt">' + highlightText(item.excerpt, q) + '</span>'
            : '') +
          '</a>'
        );
      })
      .join('');
    resultsEl.hidden = false;
    resultsEl.innerHTML = html;
  }

  function filterMenu(q) {
    var sections = menuList.querySelectorAll('.gwm-menu-section');
    sections.forEach(function (sec) {
      var secText = sec.getAttribute('data-gwm-section-search') || '';
      var secMatch = !q || secText.indexOf(q) !== -1;
      var pages = sec.querySelectorAll('.gwm-page-item');
      var visiblePages = 0;
      pages.forEach(function (page) {
        var pageText = page.getAttribute('data-gwm-page-search') || '';
        var pageMatch = !q || pageText.indexOf(q) !== -1;
        page.hidden = !pageMatch;
        if (pageMatch) visiblePages += 1;
      });
      sec.hidden = !secMatch && visiblePages === 0;
    });
  }

  function applySearch() {
    var q = normalizeQuery(input.value);
    if (clearBtn) {
      clearBtn.hidden = q.length === 0;
    }
    var matches = filterIndex(q);
    renderResults(matches, q);
    filterMenu(q);
    if (menuList) {
      menuList.classList.toggle('gwm-menu-list--filtered', q.length > 0);
    }
  }

  function clearSearch() {
    input.value = '';
    applySearch();
    input.focus();
  }

  input.addEventListener('input', applySearch);
  input.addEventListener('search', applySearch);
  if (clearBtn) {
    clearBtn.addEventListener('click', clearSearch);
  }
})();
