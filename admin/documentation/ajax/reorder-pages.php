<?php

declare(strict_types=1);

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json; charset=utf-8');

global $connect;

$raw = file_get_contents('php://input');
$data = json_decode((string) $raw, true);
$sectionId = (int) ($data['section_id'] ?? 0);
$order = $data['order'] ?? null;
if ($sectionId <= 0 || !is_array($order)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid payload']);
    exit;
}

$ids = array_map('intval', $order);
$ids = array_values(array_filter($ids, static function ($v) {
    return $v > 0;
}));
if (!$ids) {
    echo json_encode(['ok' => false, 'error' => 'No ids']);
    exit;
}

$connect->begin_transaction();
try {
    $chk = $connect->prepare('SELECT id FROM doc_pages WHERE id=? AND section_id=?');
    $pos = 0;
    $st = $connect->prepare('UPDATE doc_pages SET sort_order=? WHERE id=? AND section_id=?');
    foreach ($ids as $id) {
        $chk->bind_param('ii', $id, $sectionId);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows === 0) {
            throw new RuntimeException('bad id');
        }
        $st->bind_param('iii', $pos, $id, $sectionId);
        $st->execute();
        $pos++;
    }
    $chk->close();
    $st->close();
    $connect->commit();
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    $connect->rollback();
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}
