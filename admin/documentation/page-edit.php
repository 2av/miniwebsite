<?php

declare(strict_types=1);

require_once __DIR__ . '/init.php';

global $connect;

$pageId = (int) ($_GET['id'] ?? 0);
$isNew = isset($_GET['new']) && (string) $_GET['new'] === '1';
$defaultSection = (int) ($_GET['section_id'] ?? 0);

$flashOk = '';
$flashErr = '';
$pageRow = null;

if ($pageId > 0) {
    $pageRow = doc_get_page_by_id($connect, $pageId);
    if (!$pageRow) {
        $flashErr = 'Page not found.';
        $pageId = 0;
    } else {
        $isNew = false;
    }
}

$sections = [];
if ($res = $connect->query('SELECT id, title FROM doc_sections ORDER BY sort_order ASC, id ASC')) {
    while ($r = $res->fetch_assoc()) {
        $sections[] = $r;
    }
    $res->free();
}

if (!$sections) {
    $flashErr = $flashErr ?: 'Create at least one section before adding pages.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sections) {
    $action = $_POST['form_action'] ?? '';
    $title = trim((string) ($_POST['title'] ?? ''));
    $slugIn = trim((string) ($_POST['slug'] ?? ''));
    $sectionId = (int) ($_POST['section_id'] ?? 0);
    $metaTitle = trim((string) ($_POST['meta_title'] ?? ''));
    $metaDesc = trim((string) ($_POST['meta_description'] ?? ''));
    $metaKw = trim((string) ($_POST['meta_keywords'] ?? ''));
    $rawHtml = (string) ($_POST['content_html'] ?? '');
    $contentHtml = doc_sanitize_html($rawHtml);
    $postId = (int) ($_POST['page_id'] ?? 0);

    $publish = ($action === 'publish');
    $status = $publish ? 'published' : 'draft';

    if ($title === '' || $sectionId <= 0) {
        $flashErr = 'Title and section are required.';
    } else {
        $exclude = ($postId > 0) ? $postId : null;
        $slug = $slugIn !== ''
            ? doc_unique_page_slug($connect, doc_slugify($slugIn), $exclude)
            : doc_unique_page_slug($connect, doc_slugify($title), $exclude);

        $mx = $connect->prepare('SELECT COALESCE(MAX(sort_order),0)+1 AS n FROM doc_pages WHERE section_id=?');
        $mx->bind_param('i', $sectionId);
        $mx->execute();
        $rn = $mx->get_result()->fetch_assoc();
        $mx->close();
        $nextOrd = (int) ($rn['n'] ?? 0);

        if ($postId <= 0) {
            if ($publish) {
                $sql = 'INSERT INTO doc_pages (section_id, title, slug, status, content_html, meta_title, meta_description, meta_keywords, sort_order, published_at)
                        VALUES (?,?,?,?,?,?,?,?,?,NOW())';
            } else {
                $sql = 'INSERT INTO doc_pages (section_id, title, slug, status, content_html, meta_title, meta_description, meta_keywords, sort_order, published_at)
                        VALUES (?,?,?,?,?,?,?,?,?,NULL)';
            }
            $st = $connect->prepare($sql);
            $typesInsert = 'i' . str_repeat('s', 7) . 'i';
            $st->bind_param(
                $typesInsert,
                $sectionId,
                $title,
                $slug,
                $status,
                $contentHtml,
                $metaTitle,
                $metaDesc,
                $metaKw,
                $nextOrd
            );
            if ($st->execute()) {
                $newId = (int) $connect->insert_id;
                $flashOk = $publish ? 'Page published.' : 'Draft saved.';
                header('Location: page-edit.php?id=' . $newId);
                exit;
            }
            $flashErr = 'Could not save page.';
            $st->close();
        } else {
            if ($publish) {
                $sql = 'UPDATE doc_pages SET section_id=?, title=?, slug=?, status=?, content_html=?, meta_title=?, meta_description=?, meta_keywords=?, published_at=COALESCE(published_at, NOW()) WHERE id=?';
            } else {
                $sql = 'UPDATE doc_pages SET section_id=?, title=?, slug=?, status=?, content_html=?, meta_title=?, meta_description=?, meta_keywords=? WHERE id=?';
            }
            $st = $connect->prepare($sql);
            $typesUp = 'i' . str_repeat('s', 7) . 'i';
            if ($publish) {
                $st->bind_param(
                    $typesUp,
                    $sectionId,
                    $title,
                    $slug,
                    $status,
                    $contentHtml,
                    $metaTitle,
                    $metaDesc,
                    $metaKw,
                    $postId
                );
            } else {
                $st->bind_param(
                    $typesUp,
                    $sectionId,
                    $title,
                    $slug,
                    $status,
                    $contentHtml,
                    $metaTitle,
                    $metaDesc,
                    $metaKw,
                    $postId
                );
            }
            if ($st->execute()) {
                $flashOk = $publish ? 'Page published.' : 'Draft saved.';
                header('Location: page-edit.php?id=' . $postId);
                exit;
            }
            $flashErr = 'Could not update page.';
            $st->close();
        }
    }
}

$selSection = (int) ($pageRow['section_id'] ?? ($defaultSection ?: ((int) ($sections[0]['id'] ?? 0))));
$baseUrl = doc_request_base_url();
$docsPrefix = doc_public_prefix();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageRow ? 'Edit page' : 'New page'; ?> — Documentation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background: #f4f6f9; }
        .doc-admin-card { border: 0; border-radius: 12px; box-shadow: 0 4px 24px rgba(15,23,42,.06); }
    </style>
</head>
<body>
<?php require __DIR__ . '/top_strip.php'; ?>
<div class="container-fluid py-4 px-3 px-lg-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-0"><?php echo $pageRow ? 'Edit documentation page' : 'New documentation page'; ?></h1>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="index.php" class="btn btn-outline-secondary">All pages</a>
            <?php if ($pageRow): ?>
                <a href="preview.php?id=<?php echo (int) $pageRow['id']; ?>" target="_blank" class="btn btn-outline-primary">Preview</a>
                <a href="<?php echo htmlspecialchars($docsPrefix . rawurlencode((string) $pageRow['slug'])); ?>" target="_blank" rel="noopener" class="btn btn-outline-success">Live URL</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($flashOk): ?><div class="alert alert-success"><?php echo htmlspecialchars($flashOk); ?></div><?php endif; ?>
    <?php if ($flashErr): ?><div class="alert alert-danger"><?php echo htmlspecialchars($flashErr); ?></div><?php endif; ?>

    <?php if (!$sections): ?>
        <div class="alert alert-warning">Create a <a href="sections.php">section</a> first.</div>
    <?php else: ?>
    <form method="post" id="page-form">
        <input type="hidden" name="page_id" value="<?php echo (int) ($pageRow['id'] ?? 0); ?>">
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card doc-admin-card mb-3">
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Title</label>
                            <input type="text" name="title" id="field-title" class="form-control form-control-lg" required
                                   value="<?php echo htmlspecialchars((string) ($pageRow['title'] ?? '')); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">URL slug</label>
                            <input type="text" name="slug" id="field-slug" class="form-control" placeholder="auto from title"
                                   value="<?php echo htmlspecialchars((string) ($pageRow['slug'] ?? '')); ?>">
                            <div class="form-text">Public URL: <code><?php echo htmlspecialchars(rtrim($docsPrefix, '/') . '/'); ?><span id="slug-preview"><?php echo htmlspecialchars((string) ($pageRow['slug'] ?? 'your-slug')); ?></span></code></div>
                        </div>
                        <label class="form-label fw-semibold">Content</label>
                        <textarea name="content_html" id="content_html" rows="18" class="form-control"><?php echo htmlspecialchars((string) ($pageRow['content_html'] ?? '')); ?></textarea>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card doc-admin-card mb-3">
                    <div class="card-header bg-white fw-semibold">Publish</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Section</label>
                            <select name="section_id" class="form-select" required>
                                <?php foreach ($sections as $s): ?>
                                    <option value="<?php echo (int) $s['id']; ?>" <?php echo $selSection === (int) $s['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" name="form_action" value="draft" class="btn btn-secondary">Save draft</button>
                            <button type="submit" name="form_action" value="publish" class="btn btn-success">Publish</button>
                        </div>
                        <?php if ($pageRow): ?>
                            <p class="small text-muted mt-3 mb-0">Status: <strong><?php echo htmlspecialchars((string) $pageRow['status']); ?></strong>
                                <?php if (!empty($pageRow['published_at'])): ?>
                                    <br>Published: <?php echo htmlspecialchars((string) $pageRow['published_at']); ?>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card doc-admin-card mb-3">
                    <div class="card-header bg-white fw-semibold">SEO</div>
                    <div class="card-body">
                        <div class="mb-2">
                            <label class="form-label small">Meta title</label>
                            <input type="text" name="meta_title" class="form-control" value="<?php echo htmlspecialchars((string) ($pageRow['meta_title'] ?? '')); ?>">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Meta description</label>
                            <textarea name="meta_description" class="form-control" rows="2"><?php echo htmlspecialchars((string) ($pageRow['meta_description'] ?? '')); ?></textarea>
                        </div>
                        <div class="mb-0">
                            <label class="form-label small">Meta keywords</label>
                            <input type="text" name="meta_keywords" class="form-control" value="<?php echo htmlspecialchars((string) ($pageRow['meta_keywords'] ?? '')); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <?php if ($pageRow): ?>
    <div class="card doc-admin-card mt-2">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
            <span>Order pages in this section</span>
            <small class="text-muted">Drag — saves automatically</small>
        </div>
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead class="table-light"><tr><th style="width:40px"></th><th>Title</th><th>Slug</th><th>Status</th></tr></thead>
                <tbody id="page-sort-body" data-section="<?php echo (int) $pageRow['section_id']; ?>">
                <?php
                $sid = (int) $pageRow['section_id'];
                $pq = $connect->prepare('SELECT id, title, slug, status FROM doc_pages WHERE section_id=? ORDER BY sort_order ASC, id ASC');
                $pq->bind_param('i', $sid);
                $pq->execute();
                $pr = $pq->get_result();
                while ($r = $pr->fetch_assoc()):
                ?>
                    <tr data-id="<?php echo (int) $r['id']; ?>">
                        <td class="text-muted" style="cursor:grab"><i class="fas fa-grip-vertical"></i></td>
                        <td><?php echo htmlspecialchars($r['title']); ?></td>
                        <td><code><?php echo htmlspecialchars($r['slug']); ?></code></td>
                        <td><span class="badge bg-<?php echo $r['status'] === 'published' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars($r['status']); ?></span></td>
                    </tr>
                <?php endwhile; ?>
                <?php $pq->close(); ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3/tinymce.min.js" referrerpolicy="origin"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function(){
  var slugEl = document.getElementById('field-slug');
  var titleEl = document.getElementById('field-title');
  var prev = document.getElementById('slug-preview');
  function upd(){ if (prev) prev.textContent = (slugEl && slugEl.value) ? slugEl.value : 'your-slug'; }
  if (slugEl) slugEl.addEventListener('input', upd);
  if (titleEl && slugEl && !slugEl.value) {
    titleEl.addEventListener('blur', function(){
      if (!slugEl.value) { /* leave blank for server auto */ }
    });
  }
  upd();
})();
tinymce.init({
  selector: '#content_html',
  height: 520,
  menubar: 'edit view insert format tools table help',
  plugins: 'lists link image table code autolink fullscreen help wordcount',
  toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist outdent indent | link image table | code fullscreen | removeformat',
  branding: false,
  relative_urls: false,
  remove_script_host: false,
  document_base_url: <?php echo json_encode($baseUrl . '/'); ?>,
  images_upload_url: 'upload.php',
  automatic_uploads: true,
  convert_urls: true,
  content_style: 'body{font-family:system-ui,-apple-system,sans-serif;font-size:16px;line-height:1.6;} .doc-callout{border-left:4px solid #0d9488;padding:12px 16px;margin:16px 0;background:#f0fdfa;border-radius:0 8px 8px 0;} .doc-callout--warning{border-color:#ea580c;background:#fff7ed;} .doc-callout--success{border-color:#16a34a;background:#f0fdf4;} .doc-callout--danger{border-color:#dc2626;background:#fef2f2;}'
});

(function(){
  var body = document.getElementById('page-sort-body');
  if (!body || typeof Sortable === 'undefined') return;
  var sectionId = body.getAttribute('data-section');
  Sortable.create(body, {
    animation: 150,
    handle: 'td:first-child',
    onEnd: function () {
      var ids = [].slice.call(body.querySelectorAll('tr[data-id]')).map(function (tr) { return tr.getAttribute('data-id'); });
      fetch('ajax/reorder-pages.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ section_id: parseInt(sectionId, 10), order: ids })
      }).then(function (r) { return r.json(); }).then(function (j) {
        if (!j.ok) alert(j.error || 'Reorder failed');
      }).catch(function () { alert('Reorder failed'); });
    }
  });
})();

document.getElementById('page-form')?.addEventListener('submit', function () {
  if (typeof tinymce !== 'undefined') tinymce.triggerSave();
});
</script>
</body>
</html>
