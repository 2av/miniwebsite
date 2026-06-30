<?php
require_once(__DIR__ . '/../../app/config/database.php');
require_once(__DIR__ . '/../../app/helpers/role_helper.php');
require_once(__DIR__ . '/../../app/helpers/role_access_helper.php');
require_once(__DIR__ . '/../../app/helpers/access_control.php');
require_once(__DIR__ . '/../../app/helpers/documentation_helper.php');
require_once(__DIR__ . '/../../app/helpers/documentation_view.php');

require_login('/login/customer.php');
require_page_access('/grow-with-mw');

$ras = get_current_user_role_access_settings($connect);
$profile_key = $ras['profile_key'] ?? null;
$user_email_gwm = get_user_email() ?? '';
if (!is_role_access_feature_visible_for_user($connect, $profile_key, 'grow_with_mw', 'yes_no', $user_email_gwm, get_current_user_role())) {
    header('Location: ../dashboard/');
    exit;
}

doc_ensure_schema($connect);

$slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
$missingSlug = false;

if ($slug === '' && !empty($_SERVER['PATH_INFO'])) {
    $slug = trim((string) $_SERVER['PATH_INFO'], '/');
}

if ($slug === '' && !empty($_SERVER['REQUEST_URI'])) {
    $growPrefix = rtrim(doc_grow_with_mw_prefix(), '/');
    $requestPath = parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_string($requestPath) && strpos($requestPath, $growPrefix) === 0) {
        $tail = trim(substr($requestPath, strlen($growPrefix)), '/');
        if ($tail !== '' && $tail !== 'index.php') {
            $slug = $tail;
        }
    }
}

$page = null;
if ($slug !== '') {
    $page = doc_get_page_by_slug($connect, $slug, true);
    if (!$page) {
        $missingSlug = true;
    }
}

$nav = doc_get_nav_tree($connect, true);
$pn = $page ? doc_prev_next_published($connect, (int) $page['id']) : ['prev' => null, 'next' => null];
$gwm_search_index = doc_grow_with_mw_search_index($connect, doc_grow_with_mw_prefix());

$page_title = ($page && $slug !== '')
    ? (trim((string) ($page['meta_title'] ?? '')) ?: (string) $page['title'])
    : 'Grow with MW';

include __DIR__ . '/../includes/header.php';
?>

<main class="Dashboard mw-page">
    <div class="container-fluid customer_content_area px-4">
        <div class="main-top mw-page-header">
            <h1 class="mw-page-title"><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mw-breadcrumb">
                    <li class="breadcrumb-item mw-breadcrumb-item"><a href="../dashboard/">Dashboard</a></li>
                    <li class="breadcrumb-item mw-breadcrumb-item active" aria-current="page">Grow with MW</li>
                </ol>
            </nav>
        </div>

        <?php
        doc_render_grow_with_mw_hub([
            'page' => $page,
            'nav' => $nav,
            'prev' => $pn['prev'],
            'next' => $pn['next'],
            'current_slug' => $slug,
            'page_base_url' => doc_grow_with_mw_prefix(),
            'asset_href_prefix' => doc_web_root() . '/docs/assets/',
            'missing_slug' => $missingSlug,
            'search_index' => $gwm_search_index,
        ]);
        ?>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
