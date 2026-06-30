<?php
/**
 * Documentation CMS: schema bootstrap, navigation, slugs, HTML sanitization.
 */

declare(strict_types=1);

/**
 * Web path prefix to project root (e.g. /miniwebsite or empty).
 */
function doc_web_root(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $docRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $projRoot = realpath(dirname(__DIR__, 2));
    if ($docRoot === false || $projRoot === false) {
        $cached = '';
        return $cached;
    }
    $docRoot = rtrim(str_replace('\\', '/', $docRoot), '/');
    $projRoot = rtrim(str_replace('\\', '/', $projRoot), '/');
    if (strpos($projRoot, $docRoot) !== 0) {
        $cached = '';
        return $cached;
    }
    $rel = substr($projRoot, strlen($docRoot));
    $cached = $rel === '' ? '' : '/' . ltrim($rel, '/');
    return $cached;
}

/**
 * Absolute base URL for links and HTMLPurifier URI resolution (no trailing slash).
 */
function doc_request_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $root = doc_web_root();
    return rtrim($scheme . '://' . $host . $root, '/');
}

/**
 * Public URL prefix for documentation routes (trailing slash).
 */
function doc_public_prefix(): string
{
    return doc_web_root() . '/docs/';
}

/**
 * URL prefix for Grow with MW user portal documentation (trailing slash).
 */
function doc_grow_with_mw_prefix(): string
{
    return doc_web_root() . '/user/grow-with-mw/';
}

/**
 * Public URL to a documentation page inside Grow with MW.
 */
function doc_grow_with_mw_page_url(string $slug): string
{
    return rtrim(doc_grow_with_mw_prefix(), '/') . '/' . rawurlencode($slug);
}

/**
 * URL to an uploaded documentation asset (leading slash).
 */
function doc_upload_url(string $relativePath): string
{
    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
    return doc_web_root() . '/' . $relativePath;
}

/**
 * Filesystem directory for documentation uploads (no trailing slash).
 */
function doc_upload_fs_dir(): string
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'documentation';
}

/**
 * True when doc_pages already has a foreign key to doc_sections (any constraint name).
 */
function doc_pages_section_fk_exists(mysqli $db): bool
{
    $sql = "SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND TABLE_NAME = 'doc_pages'
              AND REFERENCED_TABLE_NAME = 'doc_sections'
            LIMIT 1";
    $res = @$db->query($sql);
    if (!$res) {
        return false;
    }
    $ok = $res->num_rows > 0;
    $res->free();
    return $ok;
}

function doc_ensure_doc_pages_section_fk(mysqli $db): void
{
    if (doc_pages_section_fk_exists($db)) {
        return;
    }
    $sql = 'ALTER TABLE `doc_pages`
            ADD CONSTRAINT `fk_doc_pages_section` FOREIGN KEY (`section_id`) REFERENCES `doc_sections` (`id`) ON DELETE RESTRICT';
    try {
        $db->query($sql);
    } catch (Throwable $e) {
        // Already present, orphan rows, engine mismatch, or duplicate name (errno 121)
    }
}

function doc_ensure_schema(?mysqli $db = null): void
{
    global $connect;
    $db = $db ?? $connect;
    static $done = false;
    if ($done || !($db instanceof mysqli) || $db->connect_error) {
        return;
    }

    $sqls = [
        "CREATE TABLE IF NOT EXISTS `doc_sections` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `title` VARCHAR(255) NOT NULL,
          `slug` VARCHAR(191) NOT NULL,
          `description` VARCHAR(500) DEFAULT NULL,
          `sort_order` INT NOT NULL DEFAULT 0,
          `collapsed_default` TINYINT(1) NOT NULL DEFAULT 0,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_doc_sections_slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `doc_pages` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `section_id` INT UNSIGNED NOT NULL,
          `title` VARCHAR(255) NOT NULL,
          `slug` VARCHAR(191) NOT NULL,
          `status` ENUM('draft','published') NOT NULL DEFAULT 'draft',
          `content_html` MEDIUMTEXT,
          `meta_title` VARCHAR(255) DEFAULT NULL,
          `meta_description` VARCHAR(500) DEFAULT NULL,
          `meta_keywords` VARCHAR(255) DEFAULT NULL,
          `sort_order` INT NOT NULL DEFAULT 0,
          `published_at` DATETIME DEFAULT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_doc_pages_slug` (`slug`),
          KEY `idx_doc_pages_section_sort` (`section_id`, `sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `doc_media` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `filename` VARCHAR(255) NOT NULL,
          `rel_path` VARCHAR(512) NOT NULL,
          `mime` VARCHAR(128) DEFAULT NULL,
          `size_bytes` INT UNSIGNED NOT NULL DEFAULT 0,
          `uploaded_by` VARCHAR(255) DEFAULT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_doc_media_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($sqls as $sql) {
        try {
            $db->query($sql);
        } catch (Throwable $e) {
            // Table may exist from a partial run; continue so FK step and seed can still run
        }
    }

    doc_ensure_doc_pages_section_fk($db);

    doc_seed_defaults_if_empty($db);

    $done = true;
}

function doc_seed_defaults_if_empty(mysqli $db): void
{
    $res = $db->query('SELECT COUNT(*) AS c FROM doc_sections');
    if (!$res) {
        return;
    }
    $row = $res->fetch_assoc();
    $res->free();
    if ((int) ($row['c'] ?? 0) > 0) {
        return;
    }

    $st = $db->prepare(
        'INSERT INTO doc_sections (title, slug, description, sort_order, collapsed_default) VALUES (?,?,?,?,?)'
    );
    if (!$st) {
        return;
    }
    $title = 'Getting Started';
    $slug = 'getting-started';
    $desc = 'Introduction and setup';
    $sort = 0;
    $collapsed = 0;
    $st->bind_param(str_repeat('s', 3) . str_repeat('i', 2), $title, $slug, $desc, $sort, $collapsed);
    $st->execute();
    $sid = (int) $db->insert_id;
    $st->close();
    if ($sid <= 0) {
        return;
    }

    $welcome = '<h1>Welcome to the documentation</h1>'
        . '<p>This is your first published page. Sign in to the admin panel under <strong>Documentation</strong> to add sections, pages, and rich content.</p>'
        . '<div class="doc-callout doc-callout--info"><p><strong>Tip:</strong> Use callout boxes by applying the classes '
        . '<code>doc-callout</code> and <code>doc-callout--info</code> (or <code>--warning</code>, <code>--success</code>, <code>--danger</code>) on a <code>div</code> in the editor (source / code view).</p></div>';

    $pt = $db->prepare(
        'INSERT INTO doc_pages (section_id, title, slug, status, content_html, sort_order, published_at) VALUES (?,?,?,?,?,?,NOW())'
    );
    if (!$pt) {
        return;
    }
    $pTitle = 'Welcome';
    $pSlug = 'welcome';
    $status = 'published';
    $pSort = 0;
    $pt->bind_param('i' . str_repeat('s', 4) . 'i', $sid, $pTitle, $pSlug, $status, $welcome, $pSort);
    $pt->execute();
    $pt->close();
}

function doc_slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? $text : 'section';
}

/**
 * @return array<int, array{id:int,title:string,slug:string,sort_order:int,collapsed_default:bool,pages:array<int,array>}>
 */
function doc_get_nav_tree(mysqli $db, bool $publishedOnly): array
{
    $sections = [];
    $q = 'SELECT id, title, slug, sort_order, collapsed_default FROM doc_sections ORDER BY sort_order ASC, id ASC';
    if (!($res = $db->query($q))) {
        return [];
    }
    while ($row = $res->fetch_assoc()) {
        $sections[(int) $row['id']] = [
            'id' => (int) $row['id'],
            'title' => (string) $row['title'],
            'slug' => (string) $row['slug'],
            'sort_order' => (int) $row['sort_order'],
            'collapsed_default' => (bool) (int) $row['collapsed_default'],
            'pages' => [],
        ];
    }
    $res->free();

    $where = $publishedOnly ? "WHERE p.status = 'published'" : '';
    $q2 = "SELECT p.id, p.section_id, p.title, p.slug, p.status, p.sort_order
           FROM doc_pages p
           $where
           ORDER BY p.sort_order ASC, p.id ASC";
    if (!($res2 = $db->query($q2))) {
        return array_values($sections);
    }
    while ($row = $res2->fetch_assoc()) {
        $sid = (int) $row['section_id'];
        if (!isset($sections[$sid])) {
            continue;
        }
        $sections[$sid]['pages'][] = [
            'id' => (int) $row['id'],
            'section_id' => $sid,
            'title' => (string) $row['title'],
            'slug' => (string) $row['slug'],
            'status' => (string) $row['status'],
            'sort_order' => (int) $row['sort_order'],
        ];
    }
    $res2->free();

    $list = array_values($sections);
    if ($publishedOnly) {
        $list = array_values(array_filter($list, static function (array $s): bool {
            return count($s['pages']) > 0;
        }));
    }

    return $list;
}

/**
 * Flat ordered list of published pages for prev/next and default landing.
 *
 * @return list<array{id:int,slug:string,title:string,section_id:int}>
 */
function doc_get_flat_published_pages(mysqli $db): array
{
    $sql = "SELECT p.id, p.slug, p.title, p.section_id
            FROM doc_pages p
            INNER JOIN doc_sections s ON s.id = p.section_id
            WHERE p.status = 'published'
            ORDER BY s.sort_order ASC, s.id ASC, p.sort_order ASC, p.id ASC";
    $out = [];
    if (!($res = $db->query($sql))) {
        return $out;
    }
    while ($row = $res->fetch_assoc()) {
        $out[] = [
            'id' => (int) $row['id'],
            'slug' => (string) $row['slug'],
            'title' => (string) $row['title'],
            'section_id' => (int) $row['section_id'],
        ];
    }
    $res->free();
    return $out;
}

/**
 * @return list<array{title:string,slug:string,section:string}>
 */
function doc_search_index(mysqli $db, bool $publishedOnly): array
{
    $where = $publishedOnly ? "WHERE p.status = 'published'" : '';
    $sql = "SELECT p.title, p.slug, p.status, s.title AS section_title
            FROM doc_pages p
            INNER JOIN doc_sections s ON s.id = p.section_id
            $where
            ORDER BY s.sort_order, p.sort_order";
    $out = [];
    if (!($res = $db->query($sql))) {
        return $out;
    }
    while ($row = $res->fetch_assoc()) {
        $out[] = [
            'title' => (string) $row['title'],
            'slug' => (string) $row['slug'],
            'section' => (string) $row['section_title'],
            'status' => (string) $row['status'],
        ];
    }
    $res->free();
    return $out;
}

/**
 * Search index for Grow with MW hub (title, section, slug, excerpt).
 *
 * @return list<array{title:string,slug:string,section:string,excerpt:string,url:string}>
 */
function doc_grow_with_mw_search_index(mysqli $db, string $page_base_url = ''): array
{
    $base = rtrim($page_base_url !== '' ? $page_base_url : doc_grow_with_mw_prefix(), '/') . '/';
    $sql = "SELECT p.title, p.slug, p.content_html, p.meta_description, s.title AS section_title
            FROM doc_pages p
            INNER JOIN doc_sections s ON s.id = p.section_id
            WHERE p.status = 'published'
            ORDER BY s.sort_order ASC, s.id ASC, p.sort_order ASC, p.id ASC";
    $out = [];
    if (!($res = $db->query($sql))) {
        return $out;
    }
    while ($row = $res->fetch_assoc()) {
        $meta = trim(strip_tags((string) ($row['meta_description'] ?? '')));
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($row['content_html'] ?? ''))) ?? '');
        $excerpt = $meta !== '' ? $meta : $plain;
        if (function_exists('mb_substr')) {
            $excerpt = mb_substr($excerpt, 0, 160);
        } else {
            $excerpt = substr($excerpt, 0, 160);
        }
        if ($plain !== '' && strlen($plain) > strlen($excerpt)) {
            $excerpt = rtrim($excerpt) . '…';
        }
        $slug = (string) $row['slug'];
        $out[] = [
            'title' => (string) $row['title'],
            'slug' => $slug,
            'section' => (string) $row['section_title'],
            'excerpt' => $excerpt,
            'url' => $base . rawurlencode($slug),
        ];
    }
    $res->free();
    return $out;
}

/**
 * @return array<string,mixed>|null
 */
function doc_get_page_by_slug(mysqli $db, string $slug, bool $publishedOnly): ?array
{
    $slug = trim($slug);
    if ($slug === '') {
        return null;
    }
    $sql = 'SELECT p.*, s.title AS section_title, s.slug AS section_slug
            FROM doc_pages p
            INNER JOIN doc_sections s ON s.id = p.section_id
            WHERE p.slug = ?';
    if ($publishedOnly) {
        $sql .= " AND p.status = 'published'";
    }
    $sql .= ' LIMIT 1';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

/**
 * @return array<string,mixed>|null
 */
function doc_get_page_by_id(mysqli $db, int $id): ?array
{
    $sql = 'SELECT p.*, s.title AS section_title, s.slug AS section_slug
            FROM doc_pages p
            INNER JOIN doc_sections s ON s.id = p.section_id
            WHERE p.id = ? LIMIT 1';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

/**
 * @return array{prev:?array{slug:string,title:string}, next:?array{slug:string,title:string}}
 */
function doc_prev_next_published(mysqli $db, int $pageId): array
{
    $flat = doc_get_flat_published_pages($db);
    $prev = null;
    $next = null;
    foreach ($flat as $i => $p) {
        if ($p['id'] === $pageId) {
            if ($i > 0) {
                $prev = ['slug' => $flat[$i - 1]['slug'], 'title' => $flat[$i - 1]['title']];
            }
            if ($i < count($flat) - 1) {
                $next = ['slug' => $flat[$i + 1]['slug'], 'title' => $flat[$i + 1]['title']];
            }
            break;
        }
    }
    return ['prev' => $prev, 'next' => $next];
}

function doc_unique_page_slug(mysqli $db, string $slug, ?int $excludeId): string
{
    $base = doc_slugify($slug);
    if ($base === '') {
        $base = 'page';
    }
    $candidate = $base;
    $n = 2;
    while (true) {
        if ($excludeId === null) {
            $stmt = $db->prepare('SELECT id FROM doc_pages WHERE slug = ? LIMIT 1');
            $stmt->bind_param('s', $candidate);
        } else {
            $stmt = $db->prepare('SELECT id FROM doc_pages WHERE slug = ? AND id != ? LIMIT 1');
            $stmt->bind_param('si', $candidate, $excludeId);
        }
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        if (!$exists) {
            return $candidate;
        }
        $candidate = $base . '-' . $n;
        $n++;
    }
}

function doc_unique_section_slug(mysqli $db, string $slug, ?int $excludeId): string
{
    $base = doc_slugify($slug);
    if ($base === '') {
        $base = 'section';
    }
    $candidate = $base;
    $n = 2;
    while (true) {
        if ($excludeId === null) {
            $stmt = $db->prepare('SELECT id FROM doc_sections WHERE slug = ? LIMIT 1');
            $stmt->bind_param('s', $candidate);
        } else {
            $stmt = $db->prepare('SELECT id FROM doc_sections WHERE slug = ? AND id != ? LIMIT 1');
            $stmt->bind_param('si', $candidate, $excludeId);
        }
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        if (!$exists) {
            return $candidate;
        }
        $candidate = $base . '-' . $n;
        $n++;
    }
}

function doc_get_html_purifier(): ?object
{
    static $purifier = false;
    if ($purifier !== false) {
        return $purifier;
    }
    $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }
    if (!class_exists('HTMLPurifier') || !class_exists('HTMLPurifier_Config')) {
        $purifier = null;
        return null;
    }
    $config = \HTMLPurifier_Config::createDefault();
    $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
    $config->set('HTML.Allowed',
        'p,br,strong,b,em,i,u,h1,h2,h3,h4,h5,h6,ul,ol,li,a[href|title|target|rel],'
        . 'img[src|alt|title|width|height|class],'
        . 'table[class],caption,thead,tbody,tfoot,tr,th[colspan|rowspan|scope|class],td[colspan|rowspan|class],'
        . 'blockquote,pre,code[class],hr,div[class],span[class],sup,sub'
    );
    $config->set('Attr.AllowedFrameTargets', ['_blank']);
    $config->set('Attr.AllowedRel', ['nofollow', 'noopener', 'noreferrer', 'nofollow noopener', 'nofollow noreferrer']);
    $config->set('URI.Base', doc_request_base_url() . '/');
    $config->set('URI.DisableExternalResources', false);
    $config->set('CSS.AllowedProperties',
        'text-align,float,margin,margin-left,margin-right,margin-top,margin-bottom,'
        . 'padding,padding-left,padding-right,padding-top,padding-bottom,'
        . 'border,border-width,border-style,border-color,border-collapse,'
        . 'width,max-width,min-width,height,background-color,color,font-size,vertical-align,'
        . 'white-space'
    );
    $config->set('Attr.AllowedClasses', [
        'doc-callout', 'doc-callout--info', 'doc-callout--warning', 'doc-callout--success', 'doc-callout--danger',
        'doc-table-wrap', 'doc-content', 'language-markup', 'language-css', 'language-javascript', 'language-php',
        'hljs', 'mce-preview-object',
        'alignnone', 'alignleft', 'aligncenter', 'alignright', 'alignjustify',
        'table', 'table-bordered', 'table-striped', 'table-hover', 'table-sm', 'table-responsive',
        'img-fluid', 'rounded', 'shadow-sm', 'border', 'w-100', 'mw-100', 'text-muted', 'lead', 'small',
        'MsoNormal',
    ]);
    $config->set('AutoFormat.RemoveEmpty.RemoveNbsp', true);
    $purifier = new \HTMLPurifier($config);
    return $purifier;
}

function doc_sanitize_html(string $html): string
{
    $p = doc_get_html_purifier();
    if ($p instanceof \HTMLPurifier) {
        return $p->purify($html);
    }
    return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
