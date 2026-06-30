(function () {
  'use strict';

  var rootEl = document.getElementById('kitExplorerRoot');
  var payloadEl = document.getElementById('kitExplorerPayload');
  if (!rootEl || !payloadEl) return;

  var data;
  try {
    data = JSON.parse(payloadEl.textContent || '{}');
  } catch (e) {
    return;
  }

  var kitLabel = data.kitLabel || 'Kit';
  var kitQuery = data.kitQuery || {};
  var foldersById = {};
  (data.folders || []).forEach(function (f) {
    foldersById[f.id] = f;
  });
  var childrenMap = data.childrenMap || {};
  var itemsByFolder = data.itemsByFolder || {};
  var currentFolderId = parseInt(data.initialFolderId, 10) || 0;

  function esc(s) {
    if (s == null) return '';
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function folderHasContent(folderId) {
    folderId = parseInt(folderId, 10);
    if ((itemsByFolder[String(folderId)] || []).length > 0) return true;
    var kids = childrenMap[String(folderId)] || [];
    for (var i = 0; i < kids.length; i++) {
      if (folderHasContent(kids[i])) return true;
    }
    return false;
  }

  function countFolderItems(folderId) {
    folderId = parseInt(folderId, 10);
    var count = (itemsByFolder[String(folderId)] || []).length;
    (childrenMap[String(folderId)] || []).forEach(function (cid) {
      count += countFolderItems(cid);
    });
    return count;
  }

  function getBreadcrumb(folderId) {
    var crumbs = [];
    var current = parseInt(folderId, 10);
    var guard = 0;
    while (current > 0 && foldersById[current] && guard < 50) {
      crumbs.push(foldersById[current]);
      current = parseInt(foldersById[current].parent_id, 10) || 0;
      guard++;
    }
    return crumbs.reverse();
  }

  function buildUrl(folderId) {
    var params = {};
    Object.keys(kitQuery).forEach(function (k) {
      if (kitQuery[k]) params[k] = kitQuery[k];
    });
    if (parseInt(folderId, 10) > 0) {
      params.folder = parseInt(folderId, 10);
    }
    var qs = Object.keys(params)
      .map(function (k) {
        return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
      })
      .join('&');
    return qs ? 'index.php?' + qs : 'index.php';
  }

  function renderItem(item) {
    if (item.type === 'image') {
      var title = esc(item.title || 'Promotional Image');
      var file = esc(item.file_path);
      return '<div class="col-md-3 col-sm-6 mb-4"><div>' +
        '<img src="../../assets/upload/kits/' + file + '" class="card-img-top" style="height:200px;object-fit:cover;" alt="' + title + '">' +
        '<div class="mt-auto d-flex" style="padding:10px;justify-content:space-between;">' +
        '<h6 class="card-title mb-0 bottom_title">' + title + '</h6>' +
        '<a href="../../assets/upload/kits/' + file + '" download title="Download"><i class="fa fa-download"></i></a>' +
        '</div></div></div>';
    }

    if (item.type === 'video') {
      var videoHtml = '<div class="col-lg-3 col-md-4 col-sm-6 mb-4"><div class="card h-100"><div class="card-body p-2 video_section">';
      if (item.file_path) {
        videoHtml += '<video controls style="width:100%;border-radius:8px;"><source src="../../assets/upload/kits/' + esc(item.file_path) + '"></video>';
      } else if (item.video_url && (item.video_url.indexOf('youtube') !== -1 || item.video_url.indexOf('youtu.be') !== -1)) {
        var m = item.video_url.match(/(youtu\.be\/|v=)([^&]+)/);
        var vid = m && m[2] ? m[2] : '';
        videoHtml += '<div class="ratio ratio-16x9"><iframe src="https://www.youtube.com/embed/' + esc(vid) + '" allowfullscreen></iframe></div>';
      } else if (item.video_url && item.video_url.indexOf('instagram.com') !== -1) {
        videoHtml += '<blockquote class="instagram-media" data-instgrm-permalink="' + esc(item.video_url) + '" data-instgrm-version="14" style="margin:0 auto;min-width:100%!important"></blockquote>';
      } else {
        videoHtml += '<p class="text-danger text-center">Unsupported video</p>';
      }
      return videoHtml + '</div></div></div>';
    }

    if (item.type === 'file') {
      var fileTitle = esc(item.title || 'Downloadable File');
      var path = esc(item.file_path);
      var ext = (item.file_path || '').split('.').pop().toUpperCase();
      var icon = fileIconClass(item.file_path);
      return '<div class="col-md-4 col-sm-6 mb-4 small_device_center downloadfileSection">' +
        '<div class="card height-100"><div class="card-body text-center downloded_files">' +
        '<div class="mb-3"><i class="fa ' + icon + ' fa-3x text-primary mb-2"></i></div>' +
        '<h6 class="card-title">' + fileTitle + '</h6>' +
        '<p class="card-text text-muted small">' + esc(ext) + ' File</p>' +
        '<a href="../../assets/upload/kits/' + path + '" download class="btn last_download btn-sm">' +
        '<i class="fa fa-download me-1"></i><span>Download</span></a></div></div></div>';
    }

    return '';
  }

  function renderItems(items) {
    if (!items.length) return '';
    var html = '<div class="kit-folder-items row">';
    items.forEach(function (item) {
      html += renderItem(item);
    });
    return html + '</div>';
  }
  function fileIconClass(path) {
    var ext = (path || '').split('.').pop().toLowerCase();
    var map = {
      pdf: 'fa-file-pdf',
      doc: 'fa-file-word', docx: 'fa-file-word',
      xls: 'fa-file-excel', xlsx: 'fa-file-excel',
      ppt: 'fa-file-powerpoint', pptx: 'fa-file-powerpoint',
      zip: 'fa-file-archive', rar: 'fa-file-archive',
      txt: 'fa-file-alt',
      mp4: 'fa-file-video', avi: 'fa-file-video', mov: 'fa-file-video',
      mp3: 'fa-file-audio', wav: 'fa-file-audio'
    };
    return map[ext] || 'fa-file';
  }

  function renderFolderGrid(folderIds) {
    var visible = folderIds.filter(function (id) { return folderHasContent(id); });
    if (!visible.length) return '';
    var html = '<div class="kit-explorer-folders row g-3 mb-4">';
    visible.forEach(function (fid) {
      var folder = foldersById[fid];
      if (!folder) return;
      var count = countFolderItems(fid);
      html += '<div class="col-6 col-sm-4 col-md-3 col-lg-2">' +
        '<div class="kit-folder-tile" role="button" tabindex="0" data-kit-folder-id="' + fid + '" title="' + esc(folder.title) + '">' +
        '<span class="kit-folder-icon" aria-hidden="true"><i class="fa fa-folder"></i></span>' +
        '<span class="kit-folder-name">' + esc(folder.title) + '</span>' +
        '<span class="kit-folder-count">' + count + ' ' + (count === 1 ? 'item' : 'items') + '</span>' +
        '</div></div>';
    });
    return html + '</div>';
  }

  function renderBar(folderId) {
    folderId = parseInt(folderId, 10) || 0;
    if (folderId === 0) {
      return '';
    }
    var crumbs = getBreadcrumb(folderId);
    var html = '<nav class="kit-explorer-bar" aria-label="Folder navigation">' +
      '<button type="button" class="kit-explorer-up kit-nav-home" title="Back to kit home"><i class="fa fa-home"></i></button>' +
      '<ol class="kit-explorer-breadcrumb mb-0"><li>' +
      '<button type="button" class="kit-crumb-btn" data-kit-folder-id="0">' + esc(kitLabel) + '</button></li>';
    crumbs.forEach(function (crumb) {
      html += '<li>';
      if (parseInt(crumb.id, 10) === parseInt(folderId, 10)) {
        html += '<span aria-current="page">' + esc(crumb.title) + '</span>';
      } else {
        html += '<button type="button" class="kit-crumb-btn" data-kit-folder-id="' + crumb.id + '">' + esc(crumb.title) + '</button>';
      }
      html += '</li>';
    });
    return html + '</ol></nav>';
  }

  function renderBody(folderId) {
    folderId = parseInt(folderId, 10) || 0;
    var html = '';
    var childIds = folderId === 0 ? (childrenMap['0'] || []) : (childrenMap[String(folderId)] || []);
    html += renderFolderGrid(childIds);

    if (folderId > 0) {
      var items = itemsByFolder[String(folderId)] || [];
      html += renderItems(items);
      var hasSubfolders = childIds.filter(folderHasContent).length > 0;
      if (!hasSubfolders && !items.length) {
        html += '<div class="text-center py-4 text-muted"><i class="fa fa-folder-open fa-2x mb-2"></i><p class="mb-0">This folder is empty.</p></div>';
      }
    } else {
      var rootFolderIds = (childrenMap['0'] || []).filter(folderHasContent);
      if (!rootFolderIds.length) {
        html += '<div class="text-center py-5"><i class="fa fa-toolbox fa-3x text-muted mb-3"></i>' +
          '<h4 class="text-muted">No Kit Items Available</h4><p class="text-muted">Please check back later.</p></div>';
      }
    }
    return html;
  }

  function bindNav() {
    rootEl.querySelectorAll('[data-kit-folder-id]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.preventDefault();
        navigateTo(parseInt(el.getAttribute('data-kit-folder-id'), 10) || 0);
      });
      el.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          navigateTo(parseInt(el.getAttribute('data-kit-folder-id'), 10) || 0);
        }
      });
    });
    var homeBtn = rootEl.querySelector('.kit-nav-home');
    if (homeBtn) {
      homeBtn.addEventListener('click', function (e) {
        e.preventDefault();
        navigateTo(0);
      });
    }
  }

  function processInstagram() {
    if (window.instgrm && window.instgrm.Embeds && typeof window.instgrm.Embeds.process === 'function') {
      window.instgrm.Embeds.process();
    }
  }

  function renderView(folderId) {
    if (folderId > 0 && !foldersById[folderId]) {
      folderId = 0;
    }
    currentFolderId = folderId;
    rootEl.innerHTML = renderBar(folderId) + '<div id="kitExplorerBody">' + renderBody(folderId) + '</div>';
    bindNav();
    processInstagram();
  }

  function navigateTo(folderId, skipPush) {
    folderId = parseInt(folderId, 10) || 0;
    if (folderId > 0 && !foldersById[folderId]) {
      folderId = 0;
    }
    renderView(folderId);
    if (!skipPush) {
      var url = buildUrl(folderId);
      history.pushState({ kitFolderId: folderId }, '', url);
    }
  }

  window.addEventListener('popstate', function (e) {
    var fid = e.state && e.state.kitFolderId != null ? parseInt(e.state.kitFolderId, 10) : 0;
    navigateTo(fid, true);
  });

  if (!history.state || history.state.kitFolderId == null) {
    history.replaceState({ kitFolderId: currentFolderId }, '', buildUrl(currentFolderId));
  }

  renderView(currentFolderId);
})();
