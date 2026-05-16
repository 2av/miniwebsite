<?php

declare(strict_types=1);

require_once __DIR__ . '/init.php';

global $connect;

$rows = [];
if ($res = $connect->query('SELECT id, filename, rel_path, mime, size_bytes, uploaded_by, created_at FROM doc_media ORDER BY id DESC LIMIT 100')) {
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    $res->free();
}
$base = doc_request_base_url();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentation media — Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body class="bg-light">
<?php require __DIR__ . '/top_strip.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3">Documentation uploads</h1>
        <a href="index.php" class="btn btn-outline-secondary">Back to pages</a>
    </div>
    <p class="text-muted">Files uploaded from the rich text editor. Copy URL to reuse in pages.</p>
    <div class="table-responsive card shadow-sm">
        <table class="table table-sm mb-0">
            <thead><tr><th>Preview</th><th>File</th><th>URL</th><th>Size</th><th>When</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <?php
                $url = $base . '/' . ltrim(str_replace('\\', '/', (string) $r['rel_path']), '/');
                $isImg = strpos((string) $r['mime'], 'image/') === 0;
                ?>
                <tr>
                    <td style="width:72px">
                        <?php if ($isImg): ?>
                            <img src="<?php echo htmlspecialchars($url); ?>" alt="" style="max-width:64px;max-height:64px;object-fit:cover;border-radius:4px">
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><code><?php echo htmlspecialchars($r['filename']); ?></code></td>
                    <td class="small"><a href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($url); ?></a></td>
                    <td><?php echo (int) $r['size_bytes']; ?></td>
                    <td class="text-muted small"><?php echo htmlspecialchars((string) $r['created_at']); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No uploads yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
