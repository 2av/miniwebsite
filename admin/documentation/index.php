<?php

declare(strict_types=1);

require_once __DIR__ . '/init.php';

global $connect;

$flashOk = '';
$flashErr = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete_page') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $st = $connect->prepare('DELETE FROM doc_pages WHERE id = ?');
            $st->bind_param('i', $id);
            if ($st->execute()) {
                $flashOk = 'Page deleted.';
            } else {
                $flashErr = 'Could not delete page.';
            }
            $st->close();
        }
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$filterSection = (int) ($_GET['section_id'] ?? 0);
$filterStatus = $_GET['status'] ?? '';
if ($filterStatus !== 'draft' && $filterStatus !== 'published') {
    $filterStatus = '';
}

$where = ['1=1'];
if ($q !== '') {
    $eq = mysqli_real_escape_string($connect, $q);
    $like = '%' . $eq . '%';
    $where[] = "(p.title LIKE '$like' OR p.slug LIKE '$like' OR p.meta_title LIKE '$like')";
}
if ($filterSection > 0) {
    $where[] = 'p.section_id = ' . $filterSection;
}
if ($filterStatus !== '') {
    $stEsc = mysqli_real_escape_string($connect, $filterStatus);
    $where[] = "p.status = '$stEsc'";
}
$sql = 'SELECT p.id, p.title, p.slug, p.status, p.sort_order, p.updated_at, s.title AS section_title, s.id AS section_id
        FROM doc_pages p
        INNER JOIN doc_sections s ON s.id = p.section_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY s.sort_order ASC, p.sort_order ASC, p.id ASC';

$pages = [];
$res = $connect->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $pages[] = $row;
    }
    $res->free();
}

$sectionsDrop = [];
if ($resS = $connect->query('SELECT id, title FROM doc_sections ORDER BY sort_order ASC, id ASC')) {
    while ($r = $resS->fetch_assoc()) {
        $sectionsDrop[] = $r;
    }
    $resS->free();
}

$publicDocs = doc_public_prefix();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentation — Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background: #f4f6f9; }
        .doc-admin-card { border: 0; border-radius: 12px; box-shadow: 0 4px 24px rgba(15,23,42,.06); }
        .badge-draft { background: #64748b; }
        .badge-published { background: #15803d; }
    </style>
</head>
<body>
<?php require __DIR__ . '/top_strip.php'; ?>
<div class="container-fluid py-4 px-3 px-lg-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">Documentation</h1>
            <p class="text-muted mb-0">Manage sections, pages, and publish to the public help center.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="sections.php" class="btn btn-outline-secondary"><i class="fas fa-folder-tree me-1"></i> Sections</a>
            <a href="media.php" class="btn btn-outline-secondary"><i class="fas fa-images me-1"></i> Media</a>
            <a href="page-edit.php?new=1" class="btn btn-success"><i class="fas fa-plus me-1"></i> New page</a>
            <a href="<?php echo htmlspecialchars($publicDocs); ?>" target="_blank" rel="noopener" class="btn btn-primary"><i class="fas fa-book-open me-1"></i> View site</a>
        </div>
    </div>

    <?php if ($flashOk): ?><div class="alert alert-success"><?php echo htmlspecialchars($flashOk); ?></div><?php endif; ?>
    <?php if ($flashErr): ?><div class="alert alert-danger"><?php echo htmlspecialchars($flashErr); ?></div><?php endif; ?>

    <div class="card doc-admin-card mb-4">
        <div class="card-body">
            <form class="row g-2 align-items-end" method="get">
                <div class="col-md-4">
                    <label class="form-label small text-muted">Search</label>
                    <input type="text" name="q" class="form-control" placeholder="Title, slug, meta…" value="<?php echo htmlspecialchars($q); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">Section</label>
                    <select name="section_id" class="form-select">
                        <option value="0">All sections</option>
                        <?php foreach ($sectionsDrop as $s): ?>
                            <option value="<?php echo (int) $s['id']; ?>" <?php echo $filterSection === (int) $s['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Any</option>
                        <option value="draft" <?php echo $filterStatus === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="published" <?php echo $filterStatus === 'published' ? 'selected' : ''; ?>>Published</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-dark w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card doc-admin-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Page</th>
                    <th>Section</th>
                    <th>Slug</th>
                    <th>Status</th>
                    <th>Updated</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$pages): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">No pages match your filters.</td></tr>
                <?php endif; ?>
                <?php foreach ($pages as $p): ?>
                    <tr>
                        <td class="fw-medium"><?php echo htmlspecialchars($p['title']); ?></td>
                        <td><?php echo htmlspecialchars($p['section_title']); ?></td>
                        <td><code><?php echo htmlspecialchars($p['slug']); ?></code></td>
                        <td>
                            <?php if ($p['status'] === 'published'): ?>
                                <span class="badge badge-published">Published</span>
                            <?php else: ?>
                                <span class="badge badge-draft">Draft</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?php echo htmlspecialchars((string) $p['updated_at']); ?></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="page-edit.php?id=<?php echo (int) $p['id']; ?>">Edit</a>
                            <a class="btn btn-sm btn-outline-secondary" target="_blank" href="preview.php?id=<?php echo (int) $p['id']; ?>">Preview</a>
                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this page permanently?');">
                                <input type="hidden" name="action" value="delete_page">
                                <input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
