<?php

declare(strict_types=1);

require_once __DIR__ . '/init.php';

header('Content-Type: application/json; charset=utf-8');

global $connect;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['file'])) {
    echo json_encode(['error' => 'No file']);
    exit;
}

$file = $_FILES['file'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'Upload error']);
    exit;
}

$max = 5 * 1024 * 1024;
if (($file['size'] ?? 0) > $max) {
    echo json_encode(['error' => 'Max 5MB']);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']) ?: '';
$map = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
];
if (!isset($map[$mime])) {
    echo json_encode(['error' => 'Only JPG, PNG, GIF, WebP allowed']);
    exit;
}

$dir = doc_upload_fs_dir();
if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
    echo json_encode(['error' => 'Storage error']);
    exit;
}

$base = 'doc-' . date('Ymd') . '-' . bin2hex(random_bytes(4)) . '.' . $map[$mime];
$dest = $dir . DIRECTORY_SEPARATOR . $base;
if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['error' => 'Could not save file']);
    exit;
}

$rel = 'uploads/documentation/' . $base;
$url = doc_request_base_url() . '/' . $rel;

$adminEmail = (string) ($_SESSION['admin_email'] ?? '');
$size = (int) filesize($dest);
$st = $connect->prepare('INSERT INTO doc_media (filename, rel_path, mime, size_bytes, uploaded_by) VALUES (?,?,?,?,?)');
$st->bind_param(str_repeat('s', 3) . 'i' . 's', $base, $rel, $mime, $size, $adminEmail);
$st->execute();
$st->close();

echo json_encode(['location' => $url]);
