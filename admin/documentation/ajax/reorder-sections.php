<?php

declare(strict_types=1);

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json; charset=utf-8');

global $connect;

$raw = file_get_contents('php://input');
$data = json_decode((string) $raw, true);
$order = $data['order'] ?? null;
if (!is_array($order) || !$order) {
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
    $pos = 0;
    $st = $connect->prepare('UPDATE doc_sections SET sort_order=? WHERE id=?');
    foreach ($ids as $id) {
        $st->bind_param('ii', $pos, $id);
        $st->execute();
        $pos++;
    }
    $st->close();
    $connect->commit();
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    $connect->rollback();
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}
