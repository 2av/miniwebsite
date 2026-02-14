<?php
/**
 * Demo MiniWebsite (MW) – same layout as MW_All spec, dynamic data from DB.
 * Run as: /miniwebsite/demo/n.php?n=card_id
 * If card_id missing or not found, uses sample data so demo always renders.
 */
require_once(__DIR__ . '/../app/config/database.php');

$card_id = isset($_GET['n']) ? trim($_GET['n']) : '';
$row = null;
$query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE card_id="' . mysqli_real_escape_string($connect, $card_id) . '"');
if ($query && mysqli_num_rows($query) > 0) {
    $row = mysqli_fetch_array($query);
}

// Which MW template is used: URL ?template=id, or DB mw_template_id (if column exists), or default
$template_id = isset($_GET['template']) ? preg_replace('/[^a-z0-9_-]/', '', trim($_GET['template'])) : '';
if ($template_id === '' && $row && isset($row['mw_template_id']) && $row['mw_template_id'] !== '') {
    $template_id = preg_replace('/[^a-z0-9_-]/', '', trim($row['mw_template_id']));
}
$templates_dir = __DIR__ . '/templates';
if ($template_id === '' || $template_id === '_default' || !is_dir($templates_dir . '/' . $template_id)) {
    $template_id = 'beauty_salon'; // default ( _default is config-only, not a viewable template)
}
$template_theme_css = 'templates/' . $template_id . '/theme.css';
$template_config_path = $templates_dir . '/' . $template_id . '/config.json';
$common_config_path = $templates_dir . '/_default/config.json';

// Load config: 1) common (default) 2) template override (if exists) 3) inline defaults
$common_config = [];
if ($common_config_path && is_readable($common_config_path)) {
    $raw = @file_get_contents($common_config_path);
    if ($raw !== false) {
        $dec = json_decode($raw, true);
        if (is_array($dec)) $common_config = $dec;
    }
}
$template_config = [];
if ($template_config_path && is_readable($template_config_path)) {
    $raw = @file_get_contents($template_config_path);
    if ($raw !== false) {
        $dec = json_decode($raw, true);
        if (is_array($dec)) $template_config = $dec;
    }
}
$mw_config = array_replace_recursive($common_config, $template_config);
$default_config = [
    'sections_order' => ['hero', 'quick_actions', 'about', 'products', 'services', 'videos', 'gallery', 'payment', 'contact_location', 'social_icons', 'footer'],
    'about' => ['enabled' => true],
    'products' => ['enabled' => true],
    'services' => ['enabled' => true],
    'videos' => ['enabled' => true],
    'gallery' => ['enabled' => true],
    'payment' => ['enabled' => true],
    'contact_location' => ['enabled' => true],
    'social_icons' => ['enabled' => true],
    'footer' => ['enabled' => true],
    'quick_actions' => ['actions' => ['call', 'whatsapp', 'direction', 'email', 'share', 'save_contact', 'scan_qr']],
    'sticky_navigation' => ['enabled' => true, 'items' => ['home', 'about', 'shop', 'videos', 'gallery', 'payment']],
    'floating_whatsapp_cta' => ['enabled' => true],
];
$mw_config = array_replace_recursive($default_config, $mw_config);

function mw_section_enabled($config, $section_id) {
    $key = $section_id;
    if ($section_id === 'contact_location') $key = 'contact_location';
    if ($section_id === 'social_icons') $key = 'social_icons';
    if (isset($config[$key]['enabled'])) return (bool) $config[$key]['enabled'];
    return true;
}
function mw_action_enabled($config, $action_id) {
    $actions = isset($config['quick_actions']['actions']) ? $config['quick_actions']['actions'] : [];
    return in_array($action_id, $actions, true);
}

// Sample data when no card (for demo without DB)
if (!$row) {
    $row = [
        'id' => 0,
        'card_id' => 'demo',
        'd_comp_name' => 'Glamour Beauty Salon',
        'd_f_name' => 'Priya',
        'd_l_name' => 'Sharma',
        'd_position' => 'Hair & Makeup Studio',
        'd_about_us' => 'We offer hair care, makeup, and nail services. Visit us in Mumbai for a glamorous experience.',
        'd_contact' => '9876543210',
        'd_whatsapp' => '919876543210',
        'd_email' => 'info@glamourbeauty.com',
        'd_address' => 'Bandra West, Mumbai, Maharashtra – 400050, India',
        'd_location' => 'https://maps.google.com/?q=Bandra+Mumbai',
        'd_website' => 'glamourbeauty.com',
        'd_fb' => 'https://facebook.com',
        'd_instagram' => 'https://instagram.com',
        'd_youtube' => 'https://youtube.com',
        'd_logo' => null,
        'd_youtube1' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ];
}

$mw_business_name = !empty($row['d_comp_name']) ? htmlspecialchars($row['d_comp_name']) : 'Business';
$mw_primary_category = !empty($row['d_position']) ? htmlspecialchars($row['d_position']) : 'Business';
$mw_location_text = !empty($row['d_address']) ? htmlspecialchars($row['d_address']) : '';
$mw_city = $mw_location_text ?: 'India';
$mw_site_url = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/' . $row['card_id'];
$mw_wa_phone = !empty($row['d_whatsapp']) ? preg_replace('/\D/', '', $row['d_whatsapp']) : '';
$mw_wa_default_text = 'Hi, I found your MiniWebsite.';

// Products (new table then old table)
$product_count = 0;
$query_pricing = null;
$use_old_table = false;
$old_products_data = null;
if (!empty($row['id'])) {
    $cid = (int) $row['id'];
    $query_pricing = mysqli_query($connect, 'SELECT * FROM card_product_pricing WHERE card_id="' . $cid . '" ORDER BY display_order ASC, id ASC');
    if ($query_pricing) $product_count = mysqli_num_rows($query_pricing);
    if ($product_count == 0) {
        $qo = mysqli_query($connect, 'SELECT * FROM products WHERE id="' . $cid . '" LIMIT 1');
        if ($qo && mysqli_num_rows($qo) > 0) {
            $old_products_data = mysqli_fetch_array($qo);
            $use_old_table = true;
            for ($x = 1; $x <= 20; $x++) {
                if (!empty($old_products_data["pro_name$x"])) $product_count++;
            }
        }
    }
}
// Sample products when none in DB
if ($product_count == 0 && !$query_pricing) {
    $product_count = 2;
    $use_old_table = false;
    $query_pricing = null;
}

// Services
$query_services = !empty($row['id']) ? mysqli_query($connect, 'SELECT * FROM card_products_services WHERE card_id="' . (int)$row['id'] . '" ORDER BY display_order ASC') : null;
$services_count = $query_services ? mysqli_num_rows($query_services) : 0;

// Gallery
$query_gallery = !empty($row['id']) ? mysqli_query($connect, 'SELECT * FROM card_image_gallery WHERE card_id="' . (int)$row['id'] . '" ORDER BY display_order ASC') : null;
$gallery_count = $query_gallery ? mysqli_num_rows($query_gallery) : 0;

// Videos
$has_videos = false;
for ($v = 1; $v <= 20; $v++) {
    if (!empty($row["d_youtube$v"])) { $has_videos = true; break; }
}

// Payment QR
$qr_pay_img = '';
if (!empty($row["d_qr_paytm"])) $qr_pay_img = 'data:image/*;base64,' . base64_encode($row["d_qr_paytm"]);
elseif (!empty($row["d_qr_phone_pay"])) $qr_pay_img = 'data:image/*;base64,' . base64_encode($row["d_qr_phone_pay"]);
elseif (!empty($row["d_qr_google_pay"])) $qr_pay_img = 'data:image/*;base64,' . base64_encode($row["d_qr_google_pay"]);
if (!$qr_pay_img) {
    $qr_pay_img = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($mw_site_url);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $mw_business_name; ?> | <?php echo $mw_primary_category; ?> in <?php echo $mw_city; ?></title>
    <meta name="description" content="Buy <?php echo strtolower($mw_primary_category); ?> in <?php echo $mw_location_text; ?>. View prices, photos &amp; WhatsApp inquiry on MiniWebsite.">
    <link rel="canonical" href="<?php echo htmlspecialchars($mw_site_url); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../panel/awesome.min.css">
    <link rel="stylesheet" href="css/components.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($template_theme_css); ?>">
    <script type="application/ld+json">
    {"@context":"https://schema.org","@type":"FAQPage","mainEntity":[{"@type":"Question","name":"Where can I find <?php echo $mw_business_name; ?>?","acceptedAnswer":{"@type":"Answer","text":"<?php echo $mw_business_name; ?> is located in <?php echo $mw_location_text ?: $mw_city; ?>."}},{"@type":"Question","name":"How can I contact <?php echo $mw_business_name; ?>?","acceptedAnswer":{"@type":"Answer","text":"You can contact via WhatsApp or call on <?php echo htmlspecialchars($row['d_contact'] ?? $row['d_whatsapp'] ?? ''); ?>."}}]}
    </script>
</head>
<body>
    <div class="mw-container">
        <?php foreach (($mw_config['sections_order'] ?? []) as $_sid): ?>
        <?php if ($_sid === 'hero'): ?>
        <?php $hero_align = isset($mw_config['hero']['alignment']) && in_array($mw_config['hero']['alignment'], ['left', 'right', 'center'], true) ? $mw_config['hero']['alignment'] : 'center'; ?>
        <!-- 1. HERO -->
        <section class="mw-hero mw-hero--<?php echo htmlspecialchars($hero_align); ?>" id="mw-hero">
            <?php if (!empty($row['d_logo'])): ?>
            <img class="mw-hero__logo" src="data:image/*;base64,<?php echo base64_encode($row['d_logo']); ?>" alt="<?php echo $mw_business_name; ?> logo">
            <?php else: ?>
            <img class="mw-hero__logo" src="https://via.placeholder.com/72/E85A8C/fff?text=G" alt="<?php echo $mw_business_name; ?>">
            <?php endif; ?>
            <h1 class="mw-hero__title"><?php echo $mw_business_name; ?> – <?php echo $mw_primary_category; ?> in <?php echo $mw_city; ?></h1>
            <p class="mw-hero__category"><?php echo $mw_primary_category; ?></p>
            <p class="mw-hero__location"><?php echo $mw_location_text; ?></p>
        </section>
        <?php elseif ($_sid === 'quick_actions'): ?>
        <!-- 2. QUICK ACTIONS (filtered by config) -->
        <section class="mw-quick-actions">
            <?php if (mw_action_enabled($mw_config, 'call') && !empty($row['d_contact'])): ?><a href="tel:+91<?php echo preg_replace('/\D/', '', $row['d_contact']); ?>" class="mw-action-btn" aria-label="Call"><i class="fa fa-phone"></i></a><?php endif; ?>
            <?php if (mw_action_enabled($mw_config, 'whatsapp') && !empty($row['d_whatsapp'])): ?><a href="https://api.whatsapp.com/send?phone=91<?php echo $mw_wa_phone; ?>&text=<?php echo urlencode('Hi, ' . $mw_business_name); ?>" class="mw-action-btn" aria-label="WhatsApp"><i class="fa fa-whatsapp"></i></a><?php endif; ?>
            <?php if (mw_action_enabled($mw_config, 'direction') && !empty($row['d_location'])): ?><a href="<?php echo htmlspecialchars($row['d_location']); ?>" class="mw-action-btn" target="_blank" rel="noopener" aria-label="Direction"><i class="fa fa-map-marker"></i></a><?php endif; ?>
            <?php if (mw_action_enabled($mw_config, 'email') && !empty($row['d_email'])): ?><a href="mailto:<?php echo htmlspecialchars($row['d_email']); ?>" class="mw-action-btn" aria-label="Email"><i class="fa fa-envelope"></i></a><?php endif; ?>
            <?php if (mw_action_enabled($mw_config, 'share')): ?><button type="button" class="mw-action-btn" aria-label="Share" onclick="if(navigator.share){navigator.share({title:'<?php echo addslashes($mw_business_name); ?>',url:'<?php echo $mw_site_url; ?>'});}else{window.open('https://api.whatsapp.com/send?text=<?php echo urlencode($mw_site_url); ?>','_blank');}"><i class="fa fa-share-alt"></i></button><?php endif; ?>
            <?php if (mw_action_enabled($mw_config, 'save_contact') && !empty($row['d_contact']) && !empty($row['id'])): ?><a href="../contact_download.php?id=<?php echo (int)$row['id']; ?>" class="mw-action-btn" aria-label="Save contact"><i class="fa fa-download"></i></a><?php endif; ?>
            <?php if (mw_action_enabled($mw_config, 'scan_qr')): ?><button type="button" class="mw-action-btn mw-scan-qr-trigger" aria-label="Scan QR"><i class="fa fa-qrcode"></i></button><?php endif; ?>
        </section>
        <?php elseif ($_sid === 'about' && mw_section_enabled($mw_config, 'about')): ?>
        <!-- 3. ABOUT -->
        <section class="mw-about" id="mw-about">
            <h2 class="mw-section-title">About <?php echo $mw_business_name; ?></h2>
            <?php if (!empty($row['d_about_us'])): ?><p class="mw-about__text"><?php echo nl2br(htmlspecialchars($row['d_about_us'])); ?></p><?php endif; ?>
        </section>
        <?php elseif ($_sid === 'products' && mw_section_enabled($mw_config, 'products') && $product_count > 0): ?>
        <!-- 4. PRODUCTS -->
        <?php if ($product_count > 0): ?>
        <section id="mw-products">
            <h2 class="mw-section-title"><?php echo $mw_primary_category; ?> Products with Price in <?php echo $mw_city; ?></h2>
            <div class="mw-products-wrapper">
                <div class="mw-product-list">
                <?php
                if ($use_old_table && $old_products_data) {
                    for ($x = 1; $x <= 20; $x++) {
                        if (empty($old_products_data["pro_name$x"])) continue;
                        $pid = 'old_' . $x;
                        $pname = $old_products_data["pro_name$x"];
                        $pprice = !empty($old_products_data["pro_price$x"]) ? number_format((float)$old_products_data["pro_price$x"], 0) : '';
                        $pmrp = !empty($old_products_data["pro_mrp$x"]) ? number_format((float)$old_products_data["pro_mrp$x"], 0) : '';
                        $pimg = !empty($old_products_data["pro_img$x"]) ? 'data:image/*;base64,' . base64_encode($old_products_data["pro_img$x"]) : 'https://via.placeholder.com/400/eee/999?text=Product';
                        ?>
                    <article class="mw-product-card" data-product-id="<?php echo $pid; ?>" data-category-id="all">
                        <img class="mw-product-card__img" src="<?php echo $pimg; ?>" alt="<?php echo htmlspecialchars($pname); ?> by <?php echo $mw_business_name; ?>">
                        <h3 class="mw-product-card__name"><?php echo htmlspecialchars($pname); ?></h3>
                        <div class="mw-product-card__price">
                            <?php if ($pprice): ?><span class="mw-price-selling">₹ <?php echo $pprice; ?></span><?php endif; ?>
                            <?php if ($pmrp): ?><span class="mw-price-actual">₹ <?php echo $pmrp; ?></span><?php endif; ?>
                        </div>
                        <p class="mw-seo-text">Buy <?php echo htmlspecialchars($pname); ?> from <?php echo $mw_business_name; ?> in <?php echo $mw_city; ?>.</p>
                        <button type="button" class="mw-btn-add" data-product-id="<?php echo $pid; ?>" data-product-name="<?php echo htmlspecialchars($pname); ?>" data-product-price="<?php echo $pprice; ?>" data-product-category="all">ADD</button>
                    </article>
                <?php }
                } elseif ($query_pricing) {
                    while ($row3 = mysqli_fetch_array($query_pricing)) {
                        if (empty($row3["product_name"])) continue;
                        $pid = $row3['id'];
                        $pname = $row3["product_name"];
                        $pprice = !empty($row3["selling_price"]) ? number_format((float)$row3["selling_price"], 0) : '';
                        $pmrp = !empty($row3["mrp"]) ? number_format((float)$row3["mrp"], 0) : '';
                        $pimg = !empty($row3["product_image"]) ? 'data:image/*;base64,' . base64_encode($row3["product_image"]) : 'https://via.placeholder.com/400/eee/999?text=Product';
                        ?>
                    <article class="mw-product-card" data-product-id="<?php echo $pid; ?>" data-category-id="all">
                        <img class="mw-product-card__img" src="<?php echo $pimg; ?>" alt="<?php echo htmlspecialchars($pname); ?> by <?php echo $mw_business_name; ?>">
                        <h3 class="mw-product-card__name"><?php echo htmlspecialchars($pname); ?></h3>
                        <div class="mw-product-card__price">
                            <?php if ($pprice): ?><span class="mw-price-selling">₹ <?php echo $pprice; ?></span><?php endif; ?>
                            <?php if ($pmrp): ?><span class="mw-price-actual">₹ <?php echo $pmrp; ?></span><?php endif; ?>
                        </div>
                        <p class="mw-seo-text">Buy <?php echo htmlspecialchars($pname); ?> from <?php echo $mw_business_name; ?> in <?php echo $mw_city; ?>.</p>
                        <button type="button" class="mw-btn-add" data-product-id="<?php echo $pid; ?>" data-product-name="<?php echo htmlspecialchars($pname); ?>" data-product-price="<?php echo $pprice; ?>" data-product-category="all">ADD</button>
                    </article>
                <?php }
                } else {
                    // Sample products for demo when no DB products
                    $samples = [
                        ['id' => 's1', 'name' => 'Keratin Treatment', 'price' => '2,499', 'mrp' => '3,500', 'desc' => 'Smooth, Frizz-Free Hair'],
                        ['id' => 's2', 'name' => 'Bridal Makeup Package', 'price' => '8,999', 'mrp' => '', 'desc' => 'Flawless Bridal Look'],
                    ];
                    foreach ($samples as $s):
                    ?>
                    <article class="mw-product-card" data-product-id="<?php echo $s['id']; ?>" data-category-id="all">
                        <img class="mw-product-card__img" src="https://via.placeholder.com/400/FFE9F0/E85A8C?text=<?php echo urlencode($s['name']); ?>" alt="<?php echo htmlspecialchars($s['name']); ?>">
                        <h3 class="mw-product-card__name"><?php echo htmlspecialchars($s['name']); ?></h3>
                        <div class="mw-product-card__price">
                            <span class="mw-price-selling">₹ <?php echo $s['price']; ?></span>
                            <?php if ($s['mrp']): ?><span class="mw-price-actual">₹ <?php echo $s['mrp']; ?></span><?php endif; ?>
                        </div>
                        <p class="mw-product-card__desc"><?php echo htmlspecialchars($s['desc']); ?></p>
                        <p class="mw-seo-text">Buy <?php echo $s['name']; ?> from <?php echo $mw_business_name; ?> in <?php echo $mw_city; ?>.</p>
                        <button type="button" class="mw-btn-add" data-product-id="<?php echo $s['id']; ?>" data-product-name="<?php echo htmlspecialchars($s['name']); ?>" data-product-price="<?php echo $s['price']; ?>" data-product-category="all">ADD</button>
                    </article>
                <?php endforeach;
                }
                ?>
                </div>
            </div>
        </section>
        <?php endif; ?>
        <?php elseif ($_sid === 'services' && mw_section_enabled($mw_config, 'services') && $services_count > 0): ?>
        <!-- 5. SERVICES -->
        <?php if ($services_count > 0): ?>
        <section id="mw-services">
            <h2 class="mw-section-title">Our Services</h2>
            <div class="mw-services-grid">
            <?php while ($sr = mysqli_fetch_array($query_services)): if (empty($sr["product_name"]) && empty($sr["product_image"])) continue;
                $simg = !empty($sr["product_image"]) ? 'data:image/*;base64,' . base64_encode($sr["product_image"]) : 'https://via.placeholder.com/400x250/FFE9F0/999?text=Service';
            ?>
                <div class="mw-service-card">
                    <img class="mw-service-card__img" src="<?php echo $simg; ?>" alt="<?php echo htmlspecialchars($sr["product_name"]); ?>">
                    <p class="mw-service-card__name"><?php echo htmlspecialchars($sr["product_name"]); ?></p>
                </div>
            <?php endwhile; ?>
            </div>
        </section>
        <?php endif; ?>
        <?php elseif ($_sid === 'videos' && mw_section_enabled($mw_config, 'videos') && $has_videos): ?>
        <!-- 6. VIDEOS -->
        <?php if ($has_videos): ?>
        <section id="mw-videos">
            <h2 class="mw-section-title"><?php echo $mw_primary_category; ?> Videos by <?php echo $mw_business_name; ?></h2>
            <div class="mw-videos-grid">
            <?php
            $yt_replace = ['youtu.be/' => 'www.youtube.com/embed/', 'watch?v=' => 'embed/', '&feature=youtu.be' => ''];
            for ($v = 1; $v <= 20; $v++) {
                if (empty($row["d_youtube$v"])) continue;
                $embed = str_replace(array_keys($yt_replace), array_values($yt_replace), $row["d_youtube$v"]);
                if (strpos($embed, 'embed/') === false && preg_match('/v=([^&]+)/', $embed, $m)) $embed = 'https://www.youtube.com/embed/' . $m[1];
                $vid = preg_match('/(?:embed\/|v=)([a-zA-Z0-9_-]+)/', $embed, $m) ? $m[1] : '';
                $thumb = $vid ? 'https://img.youtube.com/vi/' . $vid . '/mqdefault.jpg' : 'https://via.placeholder.com/400x225/eee/999?text=Video';
            ?>
                <div class="mw-video-card" data-embed-url="<?php echo htmlspecialchars($embed); ?>?autoplay=1">
                    <img class="mw-video-card__thumb" src="<?php echo $thumb; ?>" alt="Video">
                </div>
            <?php } ?>
            </div>
        </section>
        <?php endif; ?>
        <?php elseif ($_sid === 'gallery' && mw_section_enabled($mw_config, 'gallery') && $gallery_count > 0): ?>
        <!-- 7. GALLERY -->
        <?php if ($gallery_count > 0): ?>
        <section id="mw-gallery">
            <h2 class="mw-section-title">Image Gallery</h2>
            <div class="mw-gallery-grid">
            <?php while ($gr = mysqli_fetch_array($query_gallery)): if (empty($gr["gallery_image"])) continue; ?>
                <img class="mw-gallery-img" src="data:image/*;base64,<?php echo base64_encode($gr["gallery_image"]); ?>" alt="Gallery">
            <?php endwhile; ?>
            </div>
        </section>
        <?php endif; ?>
        <?php elseif ($_sid === 'payment' && mw_section_enabled($mw_config, 'payment')): ?>
        <!-- 8. PAYMENT QR -->
        <section id="mw-payment">
            <h2 class="mw-section-title">Scan & Pay</h2>
            <div class="mw-payment">
                <img class="mw-payment__img" src="<?php echo $qr_pay_img; ?>" alt="Scan & Pay">
                <p class="mw-payment__text">Scan & Pay</p>
            </div>
        </section>
        <?php elseif ($_sid === 'contact_location' && mw_section_enabled($mw_config, 'contact_location')): ?>
        <!-- 9. CONTACT & LOCATION -->
        <section id="mw-contact">
            <h2 class="mw-section-title">Contact & Location</h2>
            <div class="mw-contact" itemscope itemtype="https://schema.org/LocalBusiness">
                <address>
                    <strong itemprop="name"><?php echo $mw_business_name; ?></strong><br>
                    <?php if (!empty($row['d_contact'])): ?><span class="mw-contact-item">📞 <?php echo htmlspecialchars($row['d_contact']); ?></span><br><?php endif; ?>
                    <?php if (!empty($row['d_email'])): ?><span class="mw-contact-item">✉️ <?php echo htmlspecialchars($row['d_email']); ?></span><br><?php endif; ?>
                    <?php if (!empty($row['d_address'])): ?><span class="mw-contact-item">📍 <?php echo htmlspecialchars($row['d_address']); ?></span><?php endif; ?>
                </address>
                <?php if (!empty($row['d_location'])): ?><a href="<?php echo htmlspecialchars($row['d_location']); ?>" target="_blank" rel="noopener">Get Directions</a><?php endif; ?>
            </div>
        </section>
        <?php elseif ($_sid === 'social_icons' && mw_section_enabled($mw_config, 'social_icons')): ?>
        <!-- 10. SOCIAL ICONS -->
        <section class="mw-social-icons">
            <?php if (!empty($row['d_fb'])): ?><a href="<?php echo htmlspecialchars($row['d_fb']); ?>" target="_blank" rel="noopener"><i class="fa fa-facebook"></i></a><?php endif; ?>
            <?php if (!empty($row['d_instagram'])): ?><a href="<?php echo htmlspecialchars($row['d_instagram']); ?>" target="_blank" rel="noopener"><i class="fa fa-instagram"></i></a><?php endif; ?>
            <?php if (!empty($row['d_youtube'])): ?><a href="<?php echo htmlspecialchars($row['d_youtube']); ?>" target="_blank" rel="noopener"><i class="fa fa-youtube"></i></a><?php endif; ?>
            <?php if (!empty($row['d_website'])): ?><a href="https://<?php echo htmlspecialchars($row['d_website']); ?>" target="_blank" rel="noopener"><i class="fa fa-globe"></i></a><?php endif; ?>
        </section>
        <?php elseif ($_sid === 'footer' && mw_section_enabled($mw_config, 'footer')): ?>
        <!-- 11. FOOTER -->
        <footer class="mw-footer">
            <p>MiniWebsite</p>
            <p>© <?php echo date('Y'); ?> <?php echo $mw_business_name; ?>.</p>
            <p style="margin-top:8px; font-size:0.75rem; opacity:0.8;">Template: <strong><?php echo htmlspecialchars($template_id); ?></strong></p>
        </footer>
        <?php endif; ?>
        <?php endforeach; ?>

        <!-- Hidden FAQ (AEO) -->
        <section id="faq" style="display:none;" aria-hidden="true">
            <h2>FAQs</h2>
            <p><strong>Where can I find <?php echo $mw_business_name; ?>?</strong></p>
            <p><?php echo $mw_business_name; ?> is located in <?php echo $mw_location_text ?: $mw_city; ?>.</p>
            <p><strong>How to contact <?php echo $mw_business_name; ?>?</strong></p>
            <p>Contact via WhatsApp or call on <?php echo htmlspecialchars($row['d_contact'] ?? $row['d_whatsapp'] ?? ''); ?>.</p>
        </section>
    </div>

    <?php
    $sticky_enabled = !empty($mw_config['sticky_navigation']['enabled']);
    $sticky_items = isset($mw_config['sticky_navigation']['items']) ? $mw_config['sticky_navigation']['items'] : [];
    $sticky_labels = ['home' => 'Home', 'about' => 'About', 'shop' => 'Shop', 'videos' => 'Videos', 'gallery' => 'Gallery', 'payment' => 'Pay'];
    $hide_sticky_desktop = !empty($mw_config['responsive']['hide_sticky_nav_desktop']);
    $floating_cta_enabled = !empty($mw_config['floating_whatsapp_cta']['enabled']);
    ?>
    <!-- 12. STICKY NAV (config-driven) -->
    <?php if ($sticky_enabled && !empty($sticky_items)): ?>
    <nav class="mw-sticky-nav <?php echo !$hide_sticky_desktop ? 'mw-sticky-nav--show-desktop' : ''; ?>" aria-label="Section navigation">
        <?php $first = true; foreach ($sticky_items as $nav_id): ?>
        <button type="button" class="mw-nav-item<?php echo $first ? ' active' : ''; ?>" data-nav="<?php echo htmlspecialchars($nav_id); ?>"><?php echo htmlspecialchars($sticky_labels[$nav_id] ?? $nav_id); ?></button>
        <?php $first = false; endforeach; ?>
    </nav>
    <?php endif; ?>

    <!-- 13. FLOATING WHATSAPP CTA (config-driven) -->
    <?php if ($floating_cta_enabled): ?>
    <a id="mw-floating-cta" class="mw-floating-cta" href="#" data-phone="<?php echo $mw_wa_phone; ?>" data-default-text="<?php echo htmlspecialchars($mw_wa_default_text); ?>" data-state="whatsapp" role="button">
        <i class="fa fa-whatsapp"></i>
        <span class="mw-floating-cta__label"></span>
    </a>
    <?php endif; ?>

    <!-- Product modal -->
    <div id="mw-product-modal" class="mw-modal" aria-hidden="true">
        <div class="mw-modal__content">
            <button type="button" class="mw-modal__back" aria-label="Back"><i class="fa fa-arrow-left"></i></button>
            <button type="button" data-swipe-prev style="position:absolute;left:8px;top:50%;transform:translateY(-50%);z-index:5;background:rgba(0,0,0,0.3);color:#fff;border:none;width:36px;height:36px;border-radius:50%;cursor:pointer;"><i class="fa fa-chevron-left"></i></button>
            <img class="mw-product-modal__img" src="" alt="">
            <button type="button" data-swipe-next style="position:absolute;right:8px;top:50%;transform:translateY(-50%);z-index:5;background:rgba(0,0,0,0.3);color:#fff;border:none;width:36px;height:36px;border-radius:50%;cursor:pointer;"><i class="fa fa-chevron-right"></i></button>
            <div class="mw-product-modal__details">
                <h3 class="mw-product-modal__name"></h3>
                <p class="mw-product-modal__price"></p>
                <button type="button" class="mw-btn-add" data-add-toggle data-product-id="" data-product-name="" data-product-price="">ADD</button>
                <div class="mw-product-description"></div>
            </div>
        </div>
    </div>

    <div id="mw-image-zoom-modal" class="mw-image-zoom-modal" aria-hidden="true">
        <button type="button" class="mw-modal-close" aria-label="Close">×</button>
        <img src="" alt="">
    </div>

    <div id="mw-video-modal" class="mw-modal mw-video-modal" aria-hidden="true">
        <div class="mw-modal__content">
            <button type="button" class="mw-modal-close" aria-label="Close">×</button>
            <iframe src="" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        </div>
    </div>

    <script>
    window.MW_SITE_URL = '<?php echo addslashes($mw_site_url); ?>';
    window.MW_BUSINESS_NAME = '<?php echo addslashes($mw_business_name); ?>';
    </script>
    <script src="js/mw-core.js"></script>
</body>
</html>
