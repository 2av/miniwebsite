<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/helpers/documentation_helper.php';
require_once __DIR__ . '/../app/helpers/documentation_view.php';

global $connect;

doc_ensure_schema($connect);

$slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
$missingSlug = false;
if ($slug === '' && !empty($_SERVER['PATH_INFO'])) {
    $slug = trim((string) $_SERVER['PATH_INFO'], '/');
}

$page = null;
if ($slug !== '') {
    $page = doc_get_page_by_slug($connect, $slug, true);
    if (!$page) {
        http_response_code(404);
        $missingSlug = true;
    }
} else {
    $flat = doc_get_flat_published_pages($connect);
    if ($flat) {
        $target = rtrim(doc_public_prefix(), '/') . '/' . rawurlencode($flat[0]['slug']);
        header('Location: ' . $target, true, 302);
        exit;
    }
}

$nav = doc_get_nav_tree($connect, true);
$flat = doc_get_flat_published_pages($connect);
$pn = $page ? doc_prev_next_published($connect, (int) $page['id']) : ['prev' => null, 'next' => null];
$search = doc_search_index($connect, true);

doc_render_docs_layout([
    'connect' => $connect,
    'page' => $page,
    'nav' => $nav,
    'flat' => $flat,
    'current_slug' => $page ? (string) $page['slug'] : $slug,
    'prev' => $pn['prev'],
    'next' => $pn['next'],
    'search_index' => $search,
    'is_preview' => false,
    'asset_href_prefix' => doc_web_root() . '/docs/assets/',
    'missing_slug' => $missingSlug,
]);
