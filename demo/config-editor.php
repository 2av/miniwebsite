<?php
/**
 * Template Config Editor – edit config via UI (no manual JSON editing).
 * One common config: templates/_default/config.json (base for all templates).
 * Per-template config: templates/{template}/config.json (optional overrides).
 * New template = add folder + theme.css only; it uses common config until you save a custom config here.
 */
$templates_dir = __DIR__ . '/templates';
$COMMON_CONFIG_ID = '_default';
$common_config_path = $templates_dir . '/' . $COMMON_CONFIG_ID . '/config.json';

// Build list: "Default (common)" first, then all template folders (except _default)
$templates = [];
if (is_dir($templates_dir . '/' . $COMMON_CONFIG_ID)) {
    $templates[$COMMON_CONFIG_ID] = [
        'path' => $common_config_path,
        'has_config' => file_exists($common_config_path),
        'label' => 'Default (common)',
    ];
}
foreach (glob($templates_dir . '/*', GLOB_ONLYDIR) as $dir) {
    $name = basename($dir);
    if ($name === $COMMON_CONFIG_ID) continue;
    $config_path = $dir . '/config.json';
    $templates[$name] = [
        'path' => $config_path,
        'has_config' => file_exists($config_path),
        'label' => $name,
    ];
}

$selected = isset($_GET['template']) ? preg_replace('/[^a-z0-9_-]/', '', $_GET['template']) : '';
if ($selected === '' && count($templates) > 0) {
    $selected = array_key_first($templates);
}
if (!isset($templates[$selected])) {
    $selected = count($templates) > 0 ? array_key_first($templates) : '';
}
$config_path = $selected ? $templates[$selected]['path'] : null;

// Load config: for Default use common file; for template use its config if exists else common
$config = [];
if ($selected === $COMMON_CONFIG_ID) {
    if ($config_path && file_exists($config_path)) {
        $raw = file_get_contents($config_path);
        $config = json_decode($raw, true) ?: [];
    }
} else {
    if ($config_path && file_exists($config_path)) {
        $raw = file_get_contents($config_path);
        $config = json_decode($raw, true) ?: [];
    } else {
        if (file_exists($common_config_path)) {
            $raw = file_get_contents($common_config_path);
            $config = json_decode($raw, true) ?: [];
            if (!empty($config['template_meta'])) {
                $config['template_meta']['template_id'] = $selected;
                $config['template_meta']['template_name'] = $selected;
            }
        }
    }
}

$saved = false;
$error = '';
$post_template = isset($_POST['template']) ? preg_replace('/[^a-z0-9_-]/', '', $_POST['template']) : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $post_template && isset($templates[$post_template])) {
    $config_path = $templates[$post_template]['path'];
    $config = [
        'template_meta' => [
            'template_id' => trim($_POST['template_meta']['template_id'] ?? ''),
            'template_name' => trim($_POST['template_meta']['template_name'] ?? ''),
            'mobile_first' => !empty($_POST['template_meta']['mobile_first']),
            'status' => trim($_POST['template_meta']['status'] ?? 'active'),
        ],
        'hero' => [
            'alignment' => trim($_POST['hero']['alignment'] ?? 'center'),
            'show_primary_category' => !empty($_POST['hero']['show_primary_category']),
            'show_location' => !empty($_POST['hero']['show_location']),
            'background_type' => trim($_POST['hero']['background_type'] ?? 'color'),
        ],
        'quick_actions' => [
            'layout' => trim($_POST['quick_actions']['layout'] ?? 'horizontal_scroll'),
            'show_text' => !empty($_POST['quick_actions']['show_text']),
            'actions' => isset($_POST['quick_actions']['actions']) && is_array($_POST['quick_actions']['actions'])
                ? array_values(array_filter(array_map('trim', $_POST['quick_actions']['actions'])))
                : array_values(array_filter(array_map('trim', explode("\n", $_POST['quick_actions']['actions'] ?? '')))),
        ],
        'sections_order' => isset($_POST['sections_order']) && is_array($_POST['sections_order'])
            ? array_values(array_filter(array_map('trim', $_POST['sections_order'])))
            : array_values(array_filter(array_map('trim', explode("\n", $_POST['sections_order'] ?? '')))),
        'about' => [
            'enabled' => !empty($_POST['about']['enabled']),
            'text_limit' => (int)($_POST['about']['text_limit'] ?? 500),
        ],
        'products' => [
            'enabled' => !empty($_POST['products']['enabled']),
            'layout' => trim($_POST['products']['layout'] ?? 'category_based'),
            'category_bar' => [
                'enabled' => !empty($_POST['products']['category_bar_enabled']),
                'position' => trim($_POST['products']['category_bar_position'] ?? 'left'),
                'show_image' => !empty($_POST['products']['category_bar_show_image']),
                'scroll' => trim($_POST['products']['category_bar_scroll'] ?? 'vertical'),
                'highlight_active' => !empty($_POST['products']['category_bar_highlight_active']),
            ],
            'product_card' => [
                'show_image' => !empty($_POST['products']['card_show_image']),
                'show_name' => !empty($_POST['products']['card_show_name']),
                'show_prices' => !empty($_POST['products']['card_show_prices']),
                'show_short_description' => !empty($_POST['products']['card_show_short_description']),
                'description_limit' => (int)($_POST['products']['card_description_limit'] ?? 180),
                'add_button' => [
                    'enabled' => !empty($_POST['products']['add_button_enabled']),
                    'default_text' => trim($_POST['products']['add_button_default_text'] ?? 'ADD'),
                    'added_text' => trim($_POST['products']['add_button_added_text'] ?? '✔ ADDED'),
                ],
            ],
            'fullscreen_view' => [
                'enabled' => !empty($_POST['products']['fullscreen_enabled']),
                'back_button' => !empty($_POST['products']['fullscreen_back_button']),
                'swipe_scope' => trim($_POST['products']['fullscreen_swipe_scope'] ?? 'same_category'),
                'show_price' => !empty($_POST['products']['fullscreen_show_price']),
                'show_add_toggle' => !empty($_POST['products']['fullscreen_show_add_toggle']),
                'show_description' => !empty($_POST['products']['fullscreen_show_description']),
            ],
        ],
        'services' => [
            'enabled' => !empty($_POST['services']['enabled']),
            'layout' => trim($_POST['services']['layout'] ?? 'grid'),
            'image_zoom' => !empty($_POST['services']['image_zoom']),
            'description_limit' => (int)($_POST['services']['description_limit'] ?? 120),
        ],
        'videos' => [
            'enabled' => !empty($_POST['videos']['enabled']),
            'layout' => trim($_POST['videos']['layout'] ?? 'carousel'),
            'max_videos' => (int)($_POST['videos']['max_videos'] ?? 20),
            'enable_swipe' => !empty($_POST['videos']['enable_swipe']),
            'modal_autoplay' => !empty($_POST['videos']['modal_autoplay']),
        ],
        'gallery' => [
            'enabled' => !empty($_POST['gallery']['enabled']),
            'layout' => trim($_POST['gallery']['layout'] ?? 'grid'),
            'image_zoom' => !empty($_POST['gallery']['image_zoom']),
            'enable_swipe' => !empty($_POST['gallery']['enable_swipe']),
        ],
        'payment' => [
            'enabled' => !empty($_POST['payment']['enabled']),
            'layout' => trim($_POST['payment']['layout'] ?? 'center'),
            'show_text' => !empty($_POST['payment']['show_text']),
            'text' => trim($_POST['payment']['text'] ?? 'Scan & Pay'),
        ],
        'contact_location' => [
            'enabled' => !empty($_POST['contact_location']['enabled']),
            'show_phone' => !empty($_POST['contact_location']['show_phone']),
            'show_email' => !empty($_POST['contact_location']['show_email']),
            'show_address' => !empty($_POST['contact_location']['show_address']),
            'address_format' => trim($_POST['contact_location']['address_format'] ?? 'area_city_state_pincode_country'),
            'show_map_link' => !empty($_POST['contact_location']['show_map_link']),
        ],
        'social_icons' => [
            'enabled' => !empty($_POST['social_icons']['enabled']),
            'style' => trim($_POST['social_icons']['style'] ?? 'round'),
            'position' => trim($_POST['social_icons']['position'] ?? 'bottom'),
            'open_in_new_tab' => !empty($_POST['social_icons']['open_in_new_tab']),
        ],
        'footer' => [
            'enabled' => !empty($_POST['footer']['enabled']),
            'show_branding' => !empty($_POST['footer']['show_branding']),
        ],
        'sticky_navigation' => [
            'enabled' => !empty($_POST['sticky_navigation']['enabled']),
            'items' => isset($_POST['sticky_navigation']['items']) && is_array($_POST['sticky_navigation']['items'])
                ? array_values(array_filter(array_map('trim', $_POST['sticky_navigation']['items'])))
                : array_values(array_filter(array_map('trim', explode("\n", $_POST['sticky_navigation']['items'] ?? '')))),
            'swipe_enabled' => !empty($_POST['sticky_navigation']['swipe_enabled']),
        ],
        'floating_whatsapp_cta' => [
            'enabled' => !empty($_POST['floating_whatsapp_cta']['enabled']),
            'default_action' => trim($_POST['floating_whatsapp_cta']['default_action'] ?? 'whatsapp'),
            'selection_action' => trim($_POST['floating_whatsapp_cta']['selection_action'] ?? 'send_selected_products'),
            'position' => trim($_POST['floating_whatsapp_cta']['position'] ?? 'bottom_right'),
        ],
        'responsive' => [
            'desktop_layout' => !empty($_POST['responsive']['desktop_layout']),
            'hide_sticky_nav_desktop' => !empty($_POST['responsive']['hide_sticky_nav_desktop']),
            'product_grid_desktop' => (int)($_POST['responsive']['product_grid_desktop'] ?? 3),
        ],
    ];
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (file_put_contents($config_path, $json) !== false) {
        $saved = true;
        $selected = $post_template;
    } else {
        $error = 'Could not write config file.';
    }
}

// Merge defaults so form always has keys
$defaults = [
    'template_meta' => ['template_id' => '', 'template_name' => '', 'mobile_first' => true, 'status' => 'active'],
    'hero' => ['alignment' => 'center', 'show_primary_category' => true, 'show_location' => true, 'background_type' => 'color'],
    'quick_actions' => ['layout' => 'horizontal_scroll', 'show_text' => false, 'actions' => []],
    'sections_order' => [],
    'about' => ['enabled' => true, 'text_limit' => 500],
    'products' => [
        'enabled' => true, 'layout' => 'category_based',
        'category_bar' => ['enabled' => true, 'position' => 'left', 'show_image' => true, 'scroll' => 'vertical', 'highlight_active' => true],
        'product_card' => ['show_image' => true, 'show_name' => true, 'show_prices' => true, 'show_short_description' => true, 'description_limit' => 180, 'add_button' => ['enabled' => true, 'default_text' => 'ADD', 'added_text' => '✔ ADDED']],
        'fullscreen_view' => ['enabled' => true, 'back_button' => true, 'swipe_scope' => 'same_category', 'show_price' => true, 'show_add_toggle' => true, 'show_description' => true],
    ],
    'services' => ['enabled' => true, 'layout' => 'grid', 'image_zoom' => true, 'description_limit' => 120],
    'videos' => ['enabled' => true, 'layout' => 'carousel', 'max_videos' => 20, 'enable_swipe' => true, 'modal_autoplay' => true],
    'gallery' => ['enabled' => true, 'layout' => 'grid', 'image_zoom' => true, 'enable_swipe' => false],
    'payment' => ['enabled' => true, 'layout' => 'center', 'show_text' => true, 'text' => 'Scan & Pay'],
    'contact_location' => ['enabled' => true, 'show_phone' => true, 'show_email' => true, 'show_address' => true, 'address_format' => 'area_city_state_pincode_country', 'show_map_link' => true],
    'social_icons' => ['enabled' => true, 'style' => 'round', 'position' => 'bottom', 'open_in_new_tab' => true],
    'footer' => ['enabled' => true, 'show_branding' => true],
    'sticky_navigation' => ['enabled' => true, 'items' => [], 'swipe_enabled' => true],
    'floating_whatsapp_cta' => ['enabled' => true, 'default_action' => 'whatsapp', 'selection_action' => 'send_selected_products', 'position' => 'bottom_right'],
    'responsive' => ['desktop_layout' => true, 'hide_sticky_nav_desktop' => true, 'product_grid_desktop' => 3],
];
$config = array_replace_recursive($defaults, $config);

// If this is an AJAX save request, return JSON and skip full page reload
if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $saved && !$error,
        'saved' => $saved,
        'error' => $error,
        'template' => $post_template,
    ]);
    exit;
}

function section($key) { return isset($GLOBALS['config'][$key]) ? $GLOBALS['config'][$key] : []; }
function val($arr, $k, $d = '') { return isset($arr[$k]) ? $arr[$k] : $d; }
function chk($arr, $k) { return !empty($arr[$k]); }
function in_list($list, $item) { return is_array($list) && in_array($item, $list); }

$ALL_SECTIONS = [
    'hero' => 'Hero / Business Header',
    'quick_actions' => 'Quick Action Buttons',
    'about' => 'About Business',
    'products' => 'Products with Pricing',
    'services' => 'Services',
    'videos' => 'Videos',
    'gallery' => 'Image Gallery',
    'payment' => 'Payment QR',
    'contact_location' => 'Contact & Location',
    'social_icons' => 'Social Icons',
    'footer' => 'Footer',
];
$ALL_ACTIONS = [
    'call' => 'Call',
    'whatsapp' => 'WhatsApp',
    'direction' => 'Direction (Maps)',
    'email' => 'Email',
    'share' => 'Share link',
    'save_contact' => 'Save to Contact',
    'scan_qr' => 'Scan QR Code',
];
$ALL_STICKY_ITEMS = [
    'home' => 'Home',
    'about' => 'About',
    'shop' => 'Shop',
    'videos' => 'Videos',
    'gallery' => 'Gallery',
    'payment' => 'Pay',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Template Config Editor</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-page: #f1f5f9;
            --bg-card: #ffffff;
            --accent: #0f766e;
            --accent-hover: #0d9488;
            --accent-soft: #ccfbf1;
            --text: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --radius: 10px;
            --radius-lg: 14px;
            --shadow: 0 1px 3px rgba(0,0,0,.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,.08);
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'DM Sans', system-ui, sans-serif;
            margin: 0;
            padding: 0;
            background: var(--bg-page);
            color: var(--text);
            line-height: 1.5;
            min-height: 100vh;
        }
        .page-header {
            background: linear-gradient(135deg, #0f766e 0%, #134e4a 100%);
            color: #fff;
            padding: 28px 24px 32px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-md);
        }
        .page-header h1 {
            margin: 0 0 6px;
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .page-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.95rem;
            max-width: 560px;
        }
        .wrap {
            max-width: 820px;
            margin: 0 auto 80px;
            padding: 0 20px 24px;
        }
        .toolbar-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }
        .toolbar {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .toolbar label {
            font-weight: 600;
            color: var(--text);
            font-size: 0.9rem;
        }
        .toolbar select {
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            min-width: 220px;
            font-family: inherit;
            font-size: 0.95rem;
            background: var(--bg-card);
        }
        a.btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: var(--accent);
            color: #fff;
            text-decoration: none;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.9rem;
            transition: background .2s;
        }
        a.btn:hover { background: var(--accent-hover); }
        .msg {
            padding: 14px 18px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            font-weight: 500;
        }
        .msg.ok {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .msg.err {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .block {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            margin-bottom: 16px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            overflow: hidden;
        }
        .block h2 {
            font-size: 0.95rem;
            font-weight: 600;
            margin: 0;
            padding: 16px 20px;
            cursor: pointer;
            background: #f8fafc;
            border-bottom: 1px solid var(--border);
            color: var(--text);
            transition: background .15s;
        }
        .block h2:hover { background: #f1f5f9; }
        .block h2::before {
            content: '▾ ';
            color: var(--accent);
            margin-right: 4px;
        }
        .block.collapsed h2::before { content: '▸ '; }
        .block.collapsed .inner { display: none; }
        .block.collapsed h2 { border-bottom: none; }
        .inner { padding: 20px 24px; }
        label {
            display: block;
            margin: 10px 0 6px;
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--text);
        }
        label:first-child { margin-top: 0; }
        input[type="text"], input[type="number"], select, textarea {
            width: 100%;
            max-width: 420px;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-family: inherit;
            font-size: 0.95rem;
            background: var(--bg-card);
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-soft);
        }
        textarea { min-height: 88px; resize: vertical; }
        .inline {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
        }
        .inline input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--accent);
        }
        .checkbox-group { margin: 12px 0; }
        .checkbox-group .inline { margin: 8px 0; }
        .hint {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 6px;
        }
        .label-hint {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: normal;
        }
        code {
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.85em;
        }
        .save-fixed {
            position: fixed;
            top: 50%;
            right: 24px;
            transform: translateY(-50%);
            z-index: 100;
        }
        .save-fixed button {
            padding: 14px 28px;
            background: linear-gradient(135deg, var(--accent) 0%, #134e4a 100%);
            color: #fff;
            border: none;
            border-radius: 999px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            font-family: inherit;
            box-shadow: 0 4px 14px rgba(15, 118, 110, 0.4);
            transition: transform .15s, box-shadow .15s;
        }
        .save-fixed button:hover {
            transform: scale(1.03);
            box-shadow: 0 6px 20px rgba(15, 118, 110, 0.45);
        }
        .intro {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            margin-bottom: 20px;
            border: 1px solid var(--border);
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        .intro p { margin: 0 0 10px; }
        .intro p:last-child { margin-bottom: 0; }
        /* Toast notification */
        .config-toast {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%) translateY(80px);
            padding: 14px 24px;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 500;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            z-index: 1000;
            opacity: 0;
            transition: transform 0.3s ease, opacity 0.3s ease;
            pointer-events: none;
        }
        .config-toast.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }
        .config-toast.success {
            background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
            color: #fff;
        }
        .config-toast.error {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: #fff;
        }
    </style>
</head>
<body>
    <header class="page-header">
        <h1>Template Config Editor</h1>
        <p>Change layout, section order, and options without editing JSON. Pick a template below and edit with the form.</p>
    </header>

    <div class="wrap">
        <div class="toolbar-card">
            <form method="get" class="toolbar">
                <label for="template">Template</label>
                <select name="template" id="template" onchange="this.form.submit()">
                    <?php foreach ($templates as $name => $info): ?>
                    <option value="<?php echo htmlspecialchars($name); ?>" <?php echo $selected === $name ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars(isset($info['label']) ? $info['label'] : $name); ?><?php echo ($name !== $COMMON_CONFIG_ID && !$info['has_config']) ? ' (uses common)' : ''; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <a target="_blank" href="n.php?n=Agrawal-insurance&template=<?php echo htmlspecialchars($selected); ?>" class="btn">Preview demo →</a>
            </form>
        </div>

        <div class="intro">
            <p><strong>One common config</strong>: <code>Default (common)</code> is the base for all templates. Each template can use it as-is or have its own <code>config.json</code> to override layout/sections. <strong>To add a new template</strong>: create a folder under <code>demo/templates/</code> with a <code>theme.css</code> file; it will use the common config until you save a custom config here.</p>
            <p>To see which template a card uses: open <code>demo/n.php?n=card_id&template=...</code>. The <code>template=</code> value is also shown in the demo page footer.</p>
        </div>

        <?php if ($saved): ?>
        <div class="msg ok">✓ Config saved successfully.</div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="msg err"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <div id="live-msg" class="msg" style="display:none"></div>
        <div id="config-toast" class="config-toast" role="status" aria-live="polite"></div>

        <?php if ($selected === $COMMON_CONFIG_ID && (!$config_path || !file_exists($config_path))): ?>
        <div class="block">
            <div class="inner">
                <p style="margin:0; color:var(--text-muted);">Default (common) config file is missing. Add <code>templates/_default/config.json</code> (copy from any template folder) to edit the shared base.</p>
            </div>
        </div>
        <?php else: ?>
        <form method="post" id="config-form">
            <input type="hidden" name="template" value="<?php echo htmlspecialchars($selected); ?>">

            <div class="block">
                <h2>Template meta <span class="label-hint">(name & status of this template)</span></h2>
                <div class="inner">
                    <?php $m = section('template_meta'); ?>
                    <label>Template ID <span class="label-hint">(internal id, e.g. beauty_salon)</span></label>
                    <input type="text" name="template_meta[template_id]" value="<?php echo htmlspecialchars(val($m, 'template_id')); ?>">
                    <label>Template name <span class="label-hint">(display name)</span></label>
                    <input type="text" name="template_meta[template_name]" value="<?php echo htmlspecialchars(val($m, 'template_name')); ?>">
                    <div class="inline"><input type="checkbox" name="template_meta[mobile_first]" value="1" <?php echo chk($m, 'mobile_first') ? 'checked' : ''; ?>><label style="margin:0">Mobile first <span class="label-hint">(optimise for small screens first)</span></label></div>
                    <label>Status <span class="label-hint">(active = visible, draft = hidden)</span></label>
                    <select name="template_meta[status]">
                        <option value="active" <?php echo val($m, 'status') === 'active' ? 'selected' : ''; ?>>active</option>
                        <option value="draft" <?php echo val($m, 'status') === 'draft' ? 'selected' : ''; ?>>draft</option>
                    </select>
                </div>
            </div>

            <div class="block">
                <h2>Hero <span class="label-hint">(top banner with logo & business name)</span></h2>
                <div class="inner">
                    <?php $h = section('hero'); ?>
                    <label>Alignment <span class="label-hint">(text alignment in hero)</span></label>
                    <select name="hero[alignment]">
                        <option value="center" <?php echo val($h, 'alignment') === 'center' ? 'selected' : ''; ?>>center</option>
                        <option value="left" <?php echo val($h, 'alignment') === 'left' ? 'selected' : ''; ?>>left</option>
                        <option value="right" <?php echo val($h, 'alignment') === 'right' ? 'selected' : ''; ?>>right</option>
                    </select>
                    <div class="inline"><input type="checkbox" name="hero[show_primary_category]" value="1" <?php echo chk($h, 'show_primary_category') ? 'checked' : ''; ?>><label style="margin:0">Show primary category <span class="label-hint">(e.g. Salon, Shop)</span></label></div>
                    <div class="inline"><input type="checkbox" name="hero[show_location]" value="1" <?php echo chk($h, 'show_location') ? 'checked' : ''; ?>><label style="margin:0">Show location <span class="label-hint">(city/area under name)</span></label></div>
                    <label>Background type <span class="label-hint">(color or image behind hero)</span></label>
                    <select name="hero[background_type]">
                        <option value="color" <?php echo val($h, 'background_type') === 'color' ? 'selected' : ''; ?>>color</option>
                        <option value="image" <?php echo val($h, 'background_type') === 'image' ? 'selected' : ''; ?>>image</option>
                    </select>
                </div>
            </div>

            <div class="block">
                <h2>Quick actions <span class="label-hint">(Call, WhatsApp, Direction, Share etc. under hero)</span></h2>
                <div class="inner">
                    <?php $qa = section('quick_actions'); $qa_actions = val($qa, 'actions', []); ?>
                    <label>Layout <span class="label-hint">(horizontal scroll or grid)</span></label>
                    <select name="quick_actions[layout]">
                        <option value="horizontal_scroll" <?php echo val($qa, 'layout') === 'horizontal_scroll' ? 'selected' : ''; ?>>horizontal_scroll</option>
                        <option value="grid" <?php echo val($qa, 'layout') === 'grid' ? 'selected' : ''; ?>>grid</option>
                    </select>
                    <div class="inline"><input type="checkbox" name="quick_actions[show_text]" value="1" <?php echo chk($qa, 'show_text') ? 'checked' : ''; ?>><label style="margin:0">Show text <span class="label-hint">(label under icon)</span></label></div>
                    <label>Actions <span class="label-hint">(check to show; order = left to right on page)</span></label>
                    <div class="checkbox-group">
                        <?php foreach ($ALL_ACTIONS as $act_id => $act_label): ?>
                        <div class="inline">
                            <input type="checkbox" name="quick_actions[actions][]" value="<?php echo htmlspecialchars($act_id); ?>" id="act_<?php echo $act_id; ?>" <?php echo in_list($qa_actions, $act_id) ? 'checked' : ''; ?>>
                            <label for="act_<?php echo $act_id; ?>" style="margin:0"><?php echo htmlspecialchars($act_label); ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="block">
                <h2>Section order <span class="label-hint">(which blocks appear and in what order on the page)</span></h2>
                <div class="inner">
                    <label>Sections <span class="label-hint">(check to show; order here = top to bottom)</span></label>
                    <div class="checkbox-group">
                        <?php $sections_order = val($config, 'sections_order', []); foreach ($ALL_SECTIONS as $sec_id => $sec_label): ?>
                        <div class="inline">
                            <input type="checkbox" name="sections_order[]" value="<?php echo htmlspecialchars($sec_id); ?>" id="sec_<?php echo $sec_id; ?>" <?php echo in_list($sections_order, $sec_id) ? 'checked' : ''; ?>>
                            <label for="sec_<?php echo $sec_id; ?>" style="margin:0"><?php echo htmlspecialchars($sec_label); ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="block">
                <h2>About <span class="label-hint">(About us / business description)</span></h2>
                <div class="inner">
                    <?php $a = section('about'); ?>
                    <div class="inline"><input type="checkbox" name="about[enabled]" value="1" <?php echo chk($a, 'enabled') ? 'checked' : ''; ?>><label style="margin:0">Enabled <span class="label-hint">(show/hide this section)</span></label></div>
                    <label>Text limit (chars) <span class="label-hint">(max length of about text)</span></label>
                    <input type="number" name="about[text_limit]" value="<?php echo (int)val($a, 'text_limit', 500); ?>" min="0">
                </div>
            </div>

            <div class="block">
                <h2>Products <span class="label-hint">(product list with prices & ADD to WhatsApp)</span></h2>
                <div class="inner">
                    <?php $p = section('products'); $cb = val($p, 'category_bar', []); $pc = val($p, 'product_card', []); $ab = val($pc, 'add_button', []); $fv = val($p, 'fullscreen_view', []); ?>
                    <div class="inline"><input type="checkbox" name="products[enabled]" value="1" <?php echo chk($p, 'enabled') ? 'checked' : ''; ?>><label style="margin:0">Enabled <span class="label-hint">(show/hide products section)</span></label></div>
                    <label>Layout <span class="label-hint">(category bar + list or plain list)</span></label>
                    <select name="products[layout]">
                        <option value="category_based" <?php echo val($p, 'layout') === 'category_based' ? 'selected' : ''; ?>>category_based</option>
                        <option value="list" <?php echo val($p, 'layout') === 'list' ? 'selected' : ''; ?>>list</option>
                    </select>
                    <p class="hint"><strong>Category bar</strong> <span class="label-hint">(filter products by category)</span></p>
                    <div class="inline"><input type="checkbox" name="products[category_bar_enabled]" value="1" <?php echo chk($cb, 'enabled') ? 'checked' : ''; ?>><label style="margin:0">Enabled</label></div>
                    <label>Position <span class="label-hint">(left or right side)</span></label>
                    <select name="products[category_bar_position]">
                        <option value="left" <?php echo val($cb, 'position') === 'left' ? 'selected' : ''; ?>>left</option>
                        <option value="right" <?php echo val($cb, 'position') === 'right' ? 'selected' : ''; ?>>right</option>
                    </select>
                    <div class="inline"><input type="checkbox" name="products[category_bar_show_image]" value="1" <?php echo chk($cb, 'show_image') ? 'checked' : ''; ?>><label style="margin:0">Show image <span class="label-hint">(category thumbnails)</span></label></div>
                    <div class="inline"><input type="checkbox" name="products[category_bar_highlight_active]" value="1" <?php echo chk($cb, 'highlight_active') ? 'checked' : ''; ?>><label style="margin:0">Highlight active <span class="label-hint">(selected category)</span></label></div>
                    <p class="hint"><strong>Product card</strong> <span class="label-hint">(each product block)</span></p>
                    <div class="inline"><input type="checkbox" name="products[card_show_image]" value="1" <?php echo chk($pc, 'show_image') ? 'checked' : ''; ?>><label style="margin:0">Show image</label></div>
                    <div class="inline"><input type="checkbox" name="products[card_show_name]" value="1" <?php echo chk($pc, 'show_name') ? 'checked' : ''; ?>><label style="margin:0">Show name</label></div>
                    <div class="inline"><input type="checkbox" name="products[card_show_prices]" value="1" <?php echo chk($pc, 'show_prices') ? 'checked' : ''; ?>><label style="margin:0">Show prices</label></div>
                    <div class="inline"><input type="checkbox" name="products[card_show_short_description]" value="1" <?php echo chk($pc, 'show_short_description') ? 'checked' : ''; ?>><label style="margin:0">Show short description</label></div>
                    <label>Description limit <span class="label-hint">(max chars per product)</span></label>
                    <input type="number" name="products[card_description_limit]" value="<?php echo (int)val($pc, 'description_limit', 180); ?>">
                    <p class="hint"><strong>ADD button</strong> <span class="label-hint">(sends product to WhatsApp)</span></p>
                    <div class="inline"><input type="checkbox" name="products[add_button_enabled]" value="1" <?php echo chk($ab, 'enabled') ? 'checked' : ''; ?>><label style="margin:0">Enabled</label></div>
                    <label>Default text <span class="label-hint">(before adding)</span></label>
                    <input type="text" name="products[add_button_default_text]" value="<?php echo htmlspecialchars(val($ab, 'default_text', 'ADD')); ?>">
                    <label>Added text <span class="label-hint">(after adding to list)</span></label>
                    <input type="text" name="products[add_button_added_text]" value="<?php echo htmlspecialchars(val($ab, 'added_text', '✔ ADDED')); ?>">
                    <p class="hint"><strong>Fullscreen view</strong> <span class="label-hint">(tap product = fullscreen popup)</span></p>
                    <div class="inline"><input type="checkbox" name="products[fullscreen_enabled]" value="1" <?php echo chk($fv, 'enabled') ? 'checked' : ''; ?>><label style="margin:0">Enabled</label></div>
                    <div class="inline"><input type="checkbox" name="products[fullscreen_back_button]" value="1" <?php echo chk($fv, 'back_button') ? 'checked' : ''; ?>><label style="margin:0">Back button</label></div>
                    <div class="inline"><input type="checkbox" name="products[fullscreen_show_price]" value="1" <?php echo chk($fv, 'show_price') ? 'checked' : ''; ?>><label style="margin:0">Show price</label></div>
                    <div class="inline"><input type="checkbox" name="products[fullscreen_show_add_toggle]" value="1" <?php echo chk($fv, 'show_add_toggle') ? 'checked' : ''; ?>><label style="margin:0">Show add toggle</label></div>
                    <div class="inline"><input type="checkbox" name="products[fullscreen_show_description]" value="1" <?php echo chk($fv, 'show_description') ? 'checked' : ''; ?>><label style="margin:0">Show description</label></div>
                    <label>Swipe scope <span class="label-hint">(swipe to next in same category or all)</span></label>
                    <select name="products[fullscreen_swipe_scope]">
                        <option value="same_category" <?php echo val($fv, 'swipe_scope') === 'same_category' ? 'selected' : ''; ?>>same_category</option>
                        <option value="all" <?php echo val($fv, 'swipe_scope') === 'all' ? 'selected' : ''; ?>>all</option>
                    </select>
                </div>
            </div>

            <div class="block">
                <h2>Services <span class="label-hint">(services list with images)</span></h2>
                <div class="inner">
                    <?php $s = section('services'); ?>
                    <div class="inline"><input type="checkbox" name="services[enabled]" value="1" <?php echo chk($s, 'enabled') ? 'checked' : ''; ?>><label style="margin:0">Enabled</label></div>
                    <label>Layout <span class="label-hint">(grid or list)</span></label>
                    <select name="services[layout]">
                        <option value="grid" <?php echo val($s, 'layout') === 'grid' ? 'selected' : ''; ?>>grid</option>
                        <option value="list" <?php echo val($s, 'layout') === 'list' ? 'selected' : ''; ?>>list</option>
                    </select>
                    <div class="inline"><input type="checkbox" name="services[image_zoom]" value="1" <?php echo chk($s, 'image_zoom') ? 'checked' : ''; ?>><label style="margin:0">Image zoom <span class="label-hint">(tap to enlarge)</span></label></div>
                    <label>Description limit <span class="label-hint">(max chars)</span></label>
                    <input type="number" name="services[description_limit]" value="<?php echo (int)val($s, 'description_limit', 120); ?>">
                </div>
            </div>

            <div class="block">
                <h2>Videos <span class="label-hint">(YouTube / video section)</span></h2>
                <div class="inner">
                    <?php $v = section('videos'); ?>
                    <div class="inline"><input type="checkbox" name="videos[enabled]" value="1" <?php echo chk($v, 'enabled') ? 'checked' : ''; ?>><label style="margin:0">Enabled</label></div>
                    <label>Layout <span class="label-hint">(carousel or grid)</span></label>
                    <select name="videos[layout]">
                        <option value="carousel" <?php echo val($v, 'layout') === 'carousel' ? 'selected' : ''; ?>>carousel</option>
                        <option value="grid" <?php echo val($v, 'layout') === 'grid' ? 'selected' : ''; ?>>grid</option>
                    </select>
                    <label>Max videos <span class="label-hint">(how many to show)</span></label>
                    <input type="number" name="videos[max_videos]" value="<?php echo (int)val($v, 'max_videos', 20); ?>">
                    <div class="inline"><input type="checkbox" name="videos[enable_swipe]" value="1" <?php echo chk($v, 'enable_swipe') ? 'checked' : ''; ?>><label style="margin:0">Enable swipe <span class="label-hint">(swipe between videos)</span></label></div>
                    <div class="inline"><input type="checkbox" name="videos[modal_autoplay]" value="1" <?php echo chk($v, 'modal_autoplay') ? 'checked' : ''; ?>><label style="margin:0">Modal autoplay <span class="label-hint">(play when popup opens)</span></label></div>
                </div>
            </div>

            <div class="block">
                <h2>Gallery <span class="label-hint">(image gallery section)</span></h2>
                <div class="inner">
                    <?php $g = section('gallery'); ?>
                    <div class="inline"><input type="checkbox" name="gallery[enabled]" value="1" <?php echo chk($g, 'enabled') ? 'checked' : ''; ?>><label style="margin:0">Enabled</label></div>
                    <label>Layout <span class="label-hint">(grid of images)</span></label>
                    <select name="gallery[layout]">
                        <option value="grid" <?php echo val($g, 'layout') === 'grid' ? 'selected' : ''; ?>>grid</option>
                    </select>
                    <div class="inline"><input type="checkbox" name="gallery[image_zoom]" value="1" <?php echo chk($g, 'image_zoom') ? 'checked' : ''; ?>><label style="margin:0">Image zoom <span class="label-hint">(tap to enlarge)</span></label></div>
                    <div class="inline"><input type="checkbox" name="gallery[enable_swipe]" value="1" <?php echo chk($g, 'enable_swipe') ? 'checked' : ''; ?>><label style="margin:0">Enable swipe <span class="label-hint">(swipe between images in modal)</span></label></div>
                </div>
            </div>

            <div class="block">
                <h2>Payment QR <span class="label-hint">(Scan & Pay QR code block)</span></h2>
                <div class="inner">
                    <?php $pay = section('payment'); ?>
                    <div class="inline"><input type="checkbox" name="payment[enabled]" value="1" <?php echo chk($pay, 'enabled') ? 'checked' : ''; ?>><label style="margin:0">Enabled</label></div>
                    <label>Layout <span class="label-hint">(e.g. center)</span></label>
                    <select name="payment[layout]">
                        <option value="center" <?php echo val($pay, 'layout') === 'center' ? 'selected' : ''; ?>>center</option>
                    </select>
                    <div class="inline"><input type="checkbox" name="payment[show_text]" value="1" <?php echo chk($pay, 'show_text') ? 'checked' : ''; ?>><label style="margin:0">Show text <span class="label-hint">(caption under QR)</span></label></div>
                    <label>Text <span class="label-hint">(e.g. Scan & Pay)</span></label>
                    <input type="text" name="payment[text]" value="<?php echo htmlspecialchars(val($pay, 'text', 'Scan & Pay')); ?>">
                </div>
            </div>

            <div class="block">
                <h2>Contact & location <span class="label-hint">(phone, email, address, map link)</span></h2>
                <div class="inner">
                    <?php $cl = section('contact_location'); ?>
                    <div class="inline"><input type="checkbox" name="contact_location[enabled]" value="1" <?php echo chk($cl, 'enabled') ? 'checked' : ''; ?>><label style="margin:0">Enabled</label></div>
                    <div class="inline"><input type="checkbox" name="contact_location[show_phone]" value="1" <?php echo chk($cl, 'show_phone') ? 'checked' : ''; ?>><label style="margin:0">Show phone</label></div>
                    <div class="inline"><input type="checkbox" name="contact_location[show_email]" value="1" <?php echo chk($cl, 'show_email') ? 'checked' : ''; ?>><label style="margin:0">Show email</label></div>
                    <div class="inline"><input type="checkbox" name="contact_location[show_address]" value="1" <?php echo chk($cl, 'show_address') ? 'checked' : ''; ?>><label style="margin:0">Show address</label></div>
                    <div class="inline"><input type="checkbox" name="contact_location[show_map_link]" value="1" <?php echo chk($cl, 'show_map_link') ? 'checked' : ''; ?>><label style="margin:0">Show map link <span class="label-hint">(Get Directions)</span></label></div>
                    <label>Address format <span class="label-hint">(how address is displayed)</span></label>
                    <input type="text" name="contact_location[address_format]" value="<?php echo htmlspecialchars(val($cl, 'address_format', 'area_city_state_pincode_country')); ?>">
                </div>
            </div>

            <div class="block">
                <h2>Social icons <span class="label-hint">(Facebook, Instagram, YouTube, website links)</span></h2>
                <div class="inner">
                    <?php $si = section('social_icons'); ?>
                    <div class="inline"><input type="checkbox" name="social_icons[enabled]" value="1" <?php echo chk($si, 'enabled') ? 'checked' : ''; ?>><label style="margin:0">Enabled</label></div>
                    <label>Style <span class="label-hint">(round or square icons)</span></label>
                    <select name="social_icons[style]">
                        <option value="round" <?php echo val($si, 'style') === 'round' ? 'selected' : ''; ?>>round</option>
                        <option value="square" <?php echo val($si, 'style') === 'square' ? 'selected' : ''; ?>>square</option>
                    </select>
                    <label>Position <span class="label-hint">(where on page)</span></label>
                    <select name="social_icons[position]">
                        <option value="bottom" <?php echo val($si, 'position') === 'bottom' ? 'selected' : ''; ?>>bottom</option>
                    </select>
                    <div class="inline"><input type="checkbox" name="social_icons[open_in_new_tab]" value="1" <?php echo chk($si, 'open_in_new_tab') ? 'checked' : ''; ?>><label style="margin:0">Open in new tab <span class="label-hint">(links open in new tab)</span></label></div>
                </div>
            </div>

            <div class="block">
                <h2>Footer <span class="label-hint">(bottom of page, copyright)</span></h2>
                <div class="inner">
                    <?php $f = section('footer'); ?>
                    <div class="inline"><input type="checkbox" name="footer[enabled]" value="1" <?php echo chk($f, 'enabled') ? 'checked' : ''; ?>><label style="margin:0">Enabled</label></div>
                    <div class="inline"><input type="checkbox" name="footer[show_branding]" value="1" <?php echo chk($f, 'show_branding') ? 'checked' : ''; ?>><label style="margin:0">Show branding <span class="label-hint">(e.g. MiniWebsite name)</span></label></div>
                </div>
            </div>

            <div class="block">
                <h2>Sticky navigation <span class="label-hint">(fixed bottom bar: Home, About, Shop…)</span></h2>
                <div class="inner">
                    <?php $sn = section('sticky_navigation'); $sn_items = val($sn, 'items', []); ?>
                    <div class="inline"><input type="checkbox" name="sticky_navigation[enabled]" value="1" <?php echo chk($sn, 'enabled') ? 'checked' : ''; ?>><label style="margin:0">Enabled</label></div>
                    <div class="inline"><input type="checkbox" name="sticky_navigation[swipe_enabled]" value="1" <?php echo chk($sn, 'swipe_enabled') ? 'checked' : ''; ?>><label style="margin:0">Swipe enabled <span class="label-hint">(swipe nav bar)</span></label></div>
                    <label>Items <span class="label-hint">(check to show; order = left to right)</span></label>
                    <div class="checkbox-group">
                        <?php foreach ($ALL_STICKY_ITEMS as $item_id => $item_label): ?>
                        <div class="inline">
                            <input type="checkbox" name="sticky_navigation[items][]" value="<?php echo htmlspecialchars($item_id); ?>" id="nav_<?php echo $item_id; ?>" <?php echo in_list($sn_items, $item_id) ? 'checked' : ''; ?>>
                            <label for="nav_<?php echo $item_id; ?>" style="margin:0"><?php echo htmlspecialchars($item_label); ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="block">
                <h2>Floating WhatsApp CTA <span class="label-hint">(floating WhatsApp button)</span></h2>
                <div class="inner">
                    <?php $fc = section('floating_whatsapp_cta'); ?>
                    <div class="inline"><input type="checkbox" name="floating_whatsapp_cta[enabled]" value="1" <?php echo chk($fc, 'enabled') ? 'checked' : ''; ?>><label style="margin:0">Enabled</label></div>
                    <label>Default action <span class="label-hint">(e.g. open WhatsApp)</span></label>
                    <select name="floating_whatsapp_cta[default_action]">
                        <option value="whatsapp" <?php echo val($fc, 'default_action') === 'whatsapp' ? 'selected' : ''; ?>>whatsapp</option>
                    </select>
                    <label>Selection action <span class="label-hint">(when user has selected products)</span></label>
                    <input type="text" name="floating_whatsapp_cta[selection_action]" value="<?php echo htmlspecialchars(val($fc, 'selection_action', 'send_selected_products')); ?>">
                    <label>Position <span class="label-hint">(e.g. bottom right)</span></label>
                    <select name="floating_whatsapp_cta[position]">
                        <option value="bottom_right" <?php echo val($fc, 'position') === 'bottom_right' ? 'selected' : ''; ?>>bottom_right</option>
                    </select>
                </div>
            </div>

            <div class="block">
                <h2>Responsive <span class="label-hint">(desktop vs mobile behaviour)</span></h2>
                <div class="inner">
                    <?php $r = section('responsive'); ?>
                    <div class="inline"><input type="checkbox" name="responsive[desktop_layout]" value="1" <?php echo chk($r, 'desktop_layout') ? 'checked' : ''; ?>><label style="margin:0">Desktop layout <span class="label-hint">(use wider layout on big screens)</span></label></div>
                    <div class="inline"><input type="checkbox" name="responsive[hide_sticky_nav_desktop]" value="1" <?php echo chk($r, 'hide_sticky_nav_desktop') ? 'checked' : ''; ?>><label style="margin:0">Hide sticky nav on desktop <span class="label-hint">(hide bottom nav on large screens)</span></label></div>
                    <label>Product grid columns (desktop) <span class="label-hint">(columns in product grid on desktop)</span></label>
                    <input type="number" name="responsive[product_grid_desktop]" value="<?php echo (int)val($r, 'product_grid_desktop', 3); ?>" min="1" max="6">
                </div>
            </div>

        </form>
        <div class="save-fixed" aria-hidden="true">
            <button type="submit" form="config-form">Save config</button>
        </div>
        <?php endif; ?>
    </div>
    <script>
    document.querySelectorAll('.block h2').forEach(function(h) {
        h.addEventListener('click', function() {
            this.parentElement.classList.toggle('collapsed');
        });
    });

    // AJAX save: prevent full page reload; show toast on result
    (function() {
        var form = document.getElementById('config-form');
        if (!form) return;
        var toast = document.getElementById('config-toast');
        var toastTimer = null;

        function showToast(message, type) {
            if (!toast) return;
            if (toastTimer) clearTimeout(toastTimer);
            toast.textContent = message;
            toast.className = 'config-toast ' + (type === 'error' ? 'error' : 'success');
            toast.classList.add('show');
            toastTimer = setTimeout(function() {
                toast.classList.remove('show');
            }, 3500);
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(form);
            fd.append('ajax', '1');

            showToast('Saving...', 'success');

            fetch(window.location.href, {
                method: 'POST',
                body: fd
            }).then(function (res) {
                return res.json();
            }).then(function (data) {
                if (data && data.success) {
                    showToast('✓ Config saved successfully.', 'success');
                } else {
                    showToast((data && data.error) ? data.error : 'Could not save config.', 'error');
                }
            }).catch(function () {
                showToast('Network error while saving.', 'error');
            });
        });
    })();
    </script>
</body>
</html>
