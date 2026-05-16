<?php

declare(strict_types=1);

require_once __DIR__ . '/init.php';

global $connect;

$flashOk = '';
$flashErr = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_section') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $slugIn = trim((string) ($_POST['slug'] ?? ''));
        $desc = trim((string) ($_POST['description'] ?? ''));
        $collapsed = !empty($_POST['collapsed_default']) ? 1 : 0;
        if ($title === '') {
            $flashErr = 'Title is required.';
        } else {
            $slug = $slugIn !== '' ? doc_unique_section_slug($connect, doc_slugify($slugIn), null) : doc_unique_section_slug($connect, doc_slugify($title), null);
            $mx = $connect->query('SELECT COALESCE(MAX(sort_order),0)+1 AS n FROM doc_sections');
            $row = $mx ? $mx->fetch_assoc() : ['n' => 0];
            if ($mx) {
                $mx->free();
            }
            $ord = (int) ($row['n'] ?? 0);
            $st = $connect->prepare('INSERT INTO doc_sections (title, slug, description, sort_order, collapsed_default) VALUES (?,?,?,?,?)');
            $st->bind_param(str_repeat('s', 3) . str_repeat('i', 2), $title, $slug, $desc, $ord, $collapsed);
            if ($st->execute()) {
                $flashOk = 'Section created.';
            } else {
                $flashErr = 'Could not create section.';
            }
            $st->close();
        }
    }
    if ($action === 'edit_section') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $slugIn = trim((string) ($_POST['slug'] ?? ''));
        $desc = trim((string) ($_POST['description'] ?? ''));
        $collapsed = !empty($_POST['collapsed_default']) ? 1 : 0;
        if ($id <= 0 || $title === '') {
            $flashErr = 'Invalid section.';
        } else {
            $slug = $slugIn !== '' ? doc_unique_section_slug($connect, doc_slugify($slugIn), $id) : doc_unique_section_slug($connect, doc_slugify($title), $id);
            $st = $connect->prepare('UPDATE doc_sections SET title=?, slug=?, description=?, collapsed_default=? WHERE id=?');
            $st->bind_param(str_repeat('s', 3) . str_repeat('i', 2), $title, $slug, $desc, $collapsed, $id);
            if ($st->execute()) {
                $flashOk = 'Section updated.';
            } else {
                $flashErr = 'Could not update section.';
            }
            $st->close();
        }
    }
    if ($action === 'delete_section') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $c = $connect->query('SELECT COUNT(*) AS c FROM doc_pages WHERE section_id=' . $id);
            $cnt = $c ? (int) $c->fetch_assoc()['c'] : 1;
            if ($c) {
                $c->free();
            }
            if ($cnt > 0) {
                $flashErr = 'Move or delete pages in this section first.';
            } else {
                $st = $connect->prepare('DELETE FROM doc_sections WHERE id=?');
                $st->bind_param('i', $id);
                if ($st->execute()) {
                    $flashOk = 'Section deleted.';
                } else {
                    $flashErr = 'Could not delete section.';
                }
                $st->close();
            }
        }
    }
}

$sections = [];
if ($res = $connect->query('SELECT * FROM doc_sections ORDER BY sort_order ASC, id ASC')) {
    while ($r = $res->fetch_assoc()) {
        $sections[] = $r;
    }
    $res->free();
}

$editId = (int) ($_GET['edit'] ?? 0);
$editRow = null;
if ($editId > 0) {
    foreach ($sections as $s) {
        if ((int) $s['id'] === $editId) {
            $editRow = $s;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentation sections — Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background: #f4f6f9; }
        .doc-admin-card { border: 0; border-radius: 12px; box-shadow: 0 4px 24px rgba(15,23,42,.06); }
        .drag-handle { cursor: grab; color: #94a3b8; }
        tbody tr { background: #fff; }
    </style>
</head>
<body>
<?php require __DIR__ . '/top_strip.php'; ?>
<div class="container-fluid py-4 px-3 px-lg-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">Documentation sections</h1>
            <p class="text-muted mb-0">Drag rows to reorder the public sidebar. Slugs are used internally (page URLs use page slugs).</p>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Pages</a>
            <a href="page-edit.php?new=1" class="btn btn-success"><i class="fas fa-plus me-1"></i> New page</a>
        </div>
    </div>

    <?php if ($flashOk): ?><div class="alert alert-success"><?php echo htmlspecialchars($flashOk); ?></div><?php endif; ?>
    <?php if ($flashErr): ?><div class="alert alert-danger"><?php echo htmlspecialchars($flashErr); ?></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card doc-admin-card">
                <div class="card-header bg-white fw-semibold"><?php echo $editRow ? 'Edit section' : 'Add section'; ?></div>
                <div class="card-body">
                    <form method="post">
                        <?php if ($editRow): ?>
                            <input type="hidden" name="action" value="edit_section">
                            <input type="hidden" name="id" value="<?php echo (int) $editRow['id']; ?>">
                        <?php else: ?>
                            <input type="hidden" name="action" value="add_section">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" required value="<?php echo htmlspecialchars($editRow['title'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Slug</label>
                            <input type="text" name="slug" class="form-control" placeholder="auto from title if empty" value="<?php echo htmlspecialchars($editRow['slug'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($editRow['description'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="collapsed_default" id="cd" value="1" <?php echo !empty($editRow['collapsed_default']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="cd">Collapsed by default in sidebar</label>
                        </div>
                        <button type="submit" class="btn btn-primary"><?php echo $editRow ? 'Save changes' : 'Create section'; ?></button>
                        <?php if ($editRow): ?>
                            <a href="sections.php" class="btn btn-link">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card doc-admin-card">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                    <span>Sections (drag to reorder)</span>
                    <small class="text-muted">Saves automatically</small>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead class="table-light">
                        <tr><th style="width:40px"></th><th>Title</th><th>Slug</th><th>Order</th><th class="text-end">Actions</th></tr>
                        </thead>
                        <tbody id="section-sort-body">
                        <?php foreach ($sections as $s): ?>
                            <tr data-id="<?php echo (int) $s['id']; ?>">
                                <td class="drag-handle"><i class="fas fa-grip-vertical"></i></td>
                                <td class="fw-medium"><?php echo htmlspecialchars($s['title']); ?></td>
                                <td><code><?php echo htmlspecialchars($s['slug']); ?></code></td>
                                <td class="text-muted small"><?php echo (int) $s['sort_order']; ?></td>
                                <td class="text-end text-nowrap">
                                    <a href="sections.php?edit=<?php echo (int) $s['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this empty section?');">
                                        <input type="hidden" name="action" value="delete_section">
                                        <input type="hidden" name="id" value="<?php echo (int) $s['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$sections): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No sections yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function(){
  var el = document.getElementById('section-sort-body');
  if (!el || typeof Sortable === 'undefined') return;
  Sortable.create(el, {
    handle: '.drag-handle',
    animation: 150,
    onEnd: function () {
      var ids = [].slice.call(el.querySelectorAll('tr[data-id]')).map(function (tr) { return tr.getAttribute('data-id'); });
      fetch('ajax/reorder-sections.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order: ids })
      }).then(function (r) { return r.json(); }).then(function (j) {
        if (!j.ok) alert(j.error || 'Reorder failed');
      }).catch(function () { alert('Reorder failed'); });
    }
  });
})();
</script>
</body>
</html>
