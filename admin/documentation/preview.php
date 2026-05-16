<?php

declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/helpers/documentation_view.php';

global $connect;

$id = (int) ($_GET['id'] ?? 0);
$page = $id > 0 ? doc_get_page_by_id($connect, $id) : null;
if (!$page) {
    http_response_code(404);
    echo 'Page not found.';
    exit;
}

$nav = doc_get_nav_tree($connect, false);
$slug = (string) $page['slug'];
$pn = ($page['status'] ?? '') === 'published'
    ? doc_prev_next_published($connect, (int) $page['id'])
    : ['prev' => null, 'next' => null];
$search = doc_search_index($connect, false);

doc_render_docs_layout([
    'connect' => $connect,
    'page' => $page,
    'nav' => $nav,
    'flat' => [],
    'current_slug' => $slug,
    'prev' => $pn['prev'],
    'next' => $pn['next'],
    'search_index' => $search,
    'is_preview' => true,
    'asset_href_prefix' => doc_web_root() . '/docs/assets/',
]);
