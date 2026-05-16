<?php

declare(strict_types=1);

/**
 * Shared HTML shell for public documentation and admin preview.
 *
 * @param array{
 *   connect:mysqli,
 *   page:?array,
 *   nav:array,
 *   flat:array,
 *   current_slug:string,
 *   prev:?array,
 *   next:?array,
 *   search_index:array,
 *   is_preview?:bool,
 *   asset_href_prefix?:string
 * } $ctx
 */
function doc_render_docs_layout(array $ctx): void
{
    $connect = $ctx['connect'];
    $page = $ctx['page'] ?? null;
    $nav = $ctx['nav'] ?? [];
    $flat = $ctx['flat'] ?? [];
    $currentSlug = (string) ($ctx['current_slug'] ?? '');
    $prev = $ctx['prev'] ?? null;
    $next = $ctx['next'] ?? null;
    $searchIndex = $ctx['search_index'] ?? [];
    $isPreview = !empty($ctx['is_preview']);
    $assetPrefix = (string) ($ctx['asset_href_prefix'] ?? (doc_web_root() . '/docs/assets/'));
    $missingSlug = !empty($ctx['missing_slug']);

    $metaTitle = $page
        ? (trim((string) ($page['meta_title'] ?? '')) ?: (string) $page['title'])
        : 'Documentation';
    $metaDesc = $page ? trim((string) ($page['meta_description'] ?? '')) : '';
    $docRoot = doc_web_root();
    $docsIndex = doc_public_prefix();

    $bodyHtml = '';
    if ($page) {
        $bodyHtml = doc_sanitize_html((string) ($page['content_html'] ?? ''));
    }

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($metaTitle); ?> — Documentation</title>
    <?php if ($metaDesc !== ''): ?>
        <meta name="description" content="<?php echo htmlspecialchars($metaDesc); ?>">
    <?php endif; ?>
    <?php if ($page && trim((string) ($page['meta_keywords'] ?? '')) !== ''): ?>
        <meta name="keywords" content="<?php echo htmlspecialchars((string) $page['meta_keywords']); ?>">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400..700;1,9..40,400..700&family=Source+Serif+4:ital,opsz,wght@0,8..60,400;0,8..60,600;1,8..60,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($assetPrefix); ?>docs-ui.css">
</head>
<body class="doc-body<?php echo $isPreview ? ' doc-body--preview' : ''; ?>">
<?php if ($isPreview): ?>
<div class="doc-preview-banner">Preview mode — draft content may be shown. <a href="<?php echo htmlspecialchars(doc_web_root()); ?>/admin/documentation/page-edit.php?id=<?php echo (int) ($page['id'] ?? 0); ?>">Back to editor</a></div>
<?php endif; ?>
<header class="doc-topbar">
    <div class="doc-topbar-inner">
        <a class="doc-brand" href="<?php echo htmlspecialchars(rtrim($docsIndex, '/') . '/'); ?>">Help Center</a>
        <button type="button" class="doc-mobile-toggle" id="docNavToggle" aria-label="Open navigation"><span></span><span></span><span></span></button>
    </div>
</header>
<div class="doc-shell" id="docShell">
    <aside class="doc-sidebar" id="docSidebar">
        <div class="doc-sidebar-inner">
            <div class="doc-search-wrap">
                <input type="search" id="docNavSearch" class="doc-search" placeholder="Type to search" autocomplete="off" aria-label="Search documentation">
            </div>
            <nav class="doc-nav" id="docNav" aria-label="Documentation">
                <?php foreach ($nav as $sec): ?>
                    <?php
                    $secId = 'sec-' . (int) $sec['id'];
                    $hasActive = false;
                    foreach ($sec['pages'] as $px) {
                        if ($currentSlug !== '' && $currentSlug === $px['slug']) {
                            $hasActive = true;
                            break;
                        }
                    }
                    $collapsed = !empty($sec['collapsed_default']) && !$hasActive;
                    ?>
                    <div class="doc-nav-section<?php echo $collapsed ? ' is-collapsed' : ''; ?>" data-section-wrap>
                        <button type="button" class="doc-nav-section-toggle" aria-expanded="<?php echo $collapsed ? 'false' : 'true'; ?>" aria-controls="<?php echo htmlspecialchars($secId); ?>">
                            <span class="doc-nav-chevron" aria-hidden="true"></span>
                            <span class="doc-nav-section-title"><?php echo htmlspecialchars($sec['title']); ?></span>
                        </button>
                        <ul class="doc-nav-list" id="<?php echo htmlspecialchars($secId); ?>">
                            <?php foreach ($sec['pages'] as $p): ?>
                                <?php
                                $active = $currentSlug !== '' && $currentSlug === $p['slug'];
                                $isDraft = ($p['status'] ?? '') === 'draft';
                                ?>
                                <li class="doc-nav-item<?php echo $active ? ' is-active' : ''; ?><?php echo $isDraft ? ' is-draft' : ''; ?>"
                                    data-search="<?php echo htmlspecialchars(strtolower($p['title'] . ' ' . $p['slug'] . ' ' . $sec['title'])); ?>">
                                    <a href="<?php echo htmlspecialchars(rtrim($docsIndex, '/') . '/' . rawurlencode($p['slug'])); ?>">
                                        <?php echo htmlspecialchars($p['title']); ?>
                                        <?php if ($isDraft): ?><span class="doc-nav-draft">Draft</span><?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </nav>
        </div>
    </aside>
    <div class="doc-sidebar-scrim" id="docScrim" hidden></div>
    <main class="doc-main">
        <div class="doc-main-inner">
            <?php if (!$page): ?>
                <div class="doc-empty">
                    <?php if (!empty($missingSlug)): ?>
                        <h1 class="doc-page-title">Page not found</h1>
                        <p class="doc-lead">No documentation matches <code><?php echo htmlspecialchars($currentSlug); ?></code>. Try search or return to the <a href="<?php echo htmlspecialchars(rtrim($docsIndex, '/') . '/'); ?>">index</a>.</p>
                    <?php else: ?>
                        <h1 class="doc-page-title">No page selected</h1>
                        <p class="doc-lead">Choose a topic from the sidebar or use search to find a page.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <nav class="doc-breadcrumb" aria-label="Breadcrumb">
                    <a href="<?php echo htmlspecialchars($docRoot ? $docRoot . '/' : '/'); ?>">Home</a>
                    <span class="doc-bc-sep">/</span>
                    <a href="<?php echo htmlspecialchars(rtrim($docsIndex, '/') . '/'); ?>">Documentation</a>
                    <span class="doc-bc-sep">/</span>
                    <span><?php echo htmlspecialchars((string) ($page['section_title'] ?? '')); ?></span>
                    <span class="doc-bc-sep">/</span>
                    <span class="doc-bc-current"><?php echo htmlspecialchars((string) $page['title']); ?></span>
                </nav>
                <article class="doc-article" id="docArticle">
                    <h1 class="doc-page-title"><?php echo htmlspecialchars((string) $page['title']); ?></h1>
                    <div class="doc-prose">
                        <?php echo $bodyHtml; ?>
                    </div>
                    <nav class="doc-pager" aria-label="Adjacent pages">
                        <?php if ($prev): ?>
                            <a class="doc-pager-link doc-pager-prev" href="<?php echo htmlspecialchars(rtrim($docsIndex, '/') . '/' . rawurlencode($prev['slug'])); ?>">
                                <span class="doc-pager-label">Previous</span>
                                <span class="doc-pager-title"><?php echo htmlspecialchars($prev['title']); ?></span>
                            </a>
                        <?php else: ?>
                            <span class="doc-pager-link doc-pager-prev is-disabled"><span class="doc-pager-label">Previous</span></span>
                        <?php endif; ?>
                        <?php if ($next): ?>
                            <a class="doc-pager-link doc-pager-next" href="<?php echo htmlspecialchars(rtrim($docsIndex, '/') . '/' . rawurlencode($next['slug'])); ?>">
                                <span class="doc-pager-label">Next</span>
                                <span class="doc-pager-title"><?php echo htmlspecialchars($next['title']); ?></span>
                            </a>
                        <?php else: ?>
                            <span class="doc-pager-link doc-pager-next is-disabled"><span class="doc-pager-label">Next</span></span>
                        <?php endif; ?>
                    </nav>
                </article>
            <?php endif; ?>
        </div>
    </main>
</div>
<script>
window.__DOC_SEARCH__ = <?php echo json_encode($searchIndex, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
window.__DOC_SLUG__ = <?php echo json_encode($currentSlug, JSON_HEX_TAG | JSON_HEX_AMP); ?>;
</script>
<script src="<?php echo htmlspecialchars($assetPrefix); ?>docs-ui.js" defer></script>
</body>
</html>
    <?php
}
