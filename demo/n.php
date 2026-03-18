<?php
/**
 * Demo template - Data binding from database (digi_card) or fallback demo data
 * Access: demo/n.php?n=card_id_slug  (loads from DB) or demo/n.php (uses demo data)
 */

/**
 * Default thumbnails for platforms that don't provide native thumbs (unique per index to avoid duplicates)
 */
function getDefaultThumbForIndex($index) {
    $thumbs = [
        'https://images.unsplash.com/photo-1556910110-a5a63dfd393c?w=600&h=400&fit=crop',
        'https://images.unsplash.com/photo-1556910103-1c02745aae4d?w=600&h=400&fit=crop',
        'https://images.unsplash.com/photo-1485846234645-a62644f84728?w=600&h=400&fit=crop',
        'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=600&h=400&fit=crop',
        'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=600&h=400&fit=crop',
        'https://images.unsplash.com/photo-1574717024653-61fd2cf4d44d?w=600&h=400&fit=crop',
    ];
    return $thumbs[$index % count($thumbs)];
}

/**
 * Parse video URL (YouTube, Shorts, Instagram, Facebook, etc.) and return title, thumb, platform, embed_url.
 */
function parseVideoUrl($url, $default_thumb = '', $index = 0) {
    $url = trim($url);
    if (empty($url)) return ['title' => 'Video', 'thumb' => $default_thumb ?: getDefaultThumbForIndex($index), 'platform' => 'other', 'embed_url' => ''];
    $title = 'Video';
    $thumb = $default_thumb ?: getDefaultThumbForIndex($index);
    $platform = 'other';
    $embed_url = '';
    // YouTube: watch?v=, youtu.be/, youtube.com/shorts/
    if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/shorts/)([a-zA-Z0-9_-]{11})#', $url, $m)) {
        $vid = $m[1];
        $thumb = "https://img.youtube.com/vi/{$vid}/hqdefault.jpg";
        $embed_url = "https://www.youtube.com/embed/{$vid}?autoplay=1";
        $platform = 'youtube';
        $title = 'YouTube Video';
    } elseif (preg_match('#instagram\.com/(?:reel|p)/([a-zA-Z0-9_-]+)#', $url, $m)) {
        $platform = 'instagram';
        $title = 'Instagram Video';
        $thumb = getDefaultThumbForIndex($index);
        $embed_url = (strpos($url, '/reel/') !== false ? 'https://www.instagram.com/reel/' : 'https://www.instagram.com/p/') . $m[1] . '/embed/';
    } elseif (preg_match('#(?:facebook\.com|fb\.watch|fb\.com|m\.facebook\.com)/#', $url)) {
        $platform = 'facebook';
        $title = 'Facebook Video';
        $thumb = getDefaultThumbForIndex($index);
        $embed_url = $url;
    } elseif (preg_match('#tiktok\.com/(?:@[^/]+/video/|v/)(\d+)#', $url, $m)) {
        $platform = 'tiktok';
        $title = 'TikTok Video';
        $thumb = getDefaultThumbForIndex($index);
        $embed_url = 'https://www.tiktok.com/embed/v2/' . $m[1];
    } else {
        $thumb = getDefaultThumbForIndex($index);
        $embed_url = $url;
    }
    return ['title' => $title, 'thumb' => $thumb, 'platform' => $platform, 'embed_url' => $embed_url];
}

$card_id_slug = isset($_GET['n']) ? trim($_GET['n']) : (isset($_GET['card_number']) ? trim($_GET['card_number']) : '');
$row = null;
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$assets_base = dirname(__DIR__) . '/assets';

// Try to load from database when card_id provided
if (!empty($card_id_slug)) {
    $db_config = dirname(__DIR__) . '/app/config/database.php';
    if (file_exists($db_config)) {
        require_once $db_config;
        $card_id_esc = mysqli_real_escape_string($connect, $card_id_slug);
        // Support both: slug (card_id) and numeric id (from card_number in URL)
        if (ctype_digit($card_id_slug)) {
            $query = mysqli_query($connect, "SELECT * FROM digi_card WHERE id='$card_id_esc'");
        } else {
            $query = mysqli_query($connect, "SELECT * FROM digi_card WHERE card_id='$card_id_esc'");
        }
        if ($query && mysqli_num_rows($query) > 0) {
            $row = mysqli_fetch_assoc($query);
        }
    }
}

// Build data arrays (from DB or demo fallback)
if ($row) {
    $card_db_id = intval($row['id']);
    $hero_name = !empty($row['d_comp_name']) ? htmlspecialchars($row['d_comp_name']) : trim((isset($row['d_f_name']) ? $row['d_f_name'] : '') . ' ' . (isset($row['d_l_name']) ? $row['d_l_name'] : ''));
    if (empty($hero_name)) $hero_name = 'Your Name';
    $hero_title = !empty($row['d_position']) ? htmlspecialchars($row['d_position']) : 'Executive Chef';
    // Hero cover: use d_hero_image_location first, fallback to unsplash
    $hero_cover = 'https://images.unsplash.com/photo-1556910103-1c02745aae4d?w=1200&q=80';
    if (!empty($row['d_hero_image_location'])) {
        $hero_path = trim($row['d_hero_image_location']);
        if (strpos($hero_path, '/') === false) $hero_path = 'assets/upload/websites/company_details/' . $hero_path;
        $hero_cover = '../' . preg_replace('#^\.\./+#', '', $hero_path);
    }
    $hero_logo = $hero_cover; // placeholder; logo uses d_logo below
    if (!empty($row['d_logo_location'])) {
        $logo_path = trim($row['d_logo_location']);
        if (strpos($logo_path, '/') === false) $logo_path = 'assets/upload/websites/company_details/' . $logo_path;
        $hero_logo = '../' . preg_replace('#^\.\./+#', '', $logo_path);
    } elseif (!empty($row['d_logo'])) {
        $hero_logo = 'data:image/*;base64,' . base64_encode($row['d_logo']);
    } else {
        $hero_logo = 'https://images.unsplash.com/photo-1583394838336-acd977736f90?w=300&q=80';
    }
    $phone = !empty($row['d_contact']) ? preg_replace('/[^0-9+]/', '', $row['d_contact']) : '1234567890';
    $whatsapp = !empty($row['d_whatsapp']) ? preg_replace('/[^0-9+]/', '', $row['d_whatsapp']) : $phone;
    $about = !empty($row['d_about_us']) ? htmlspecialchars($row['d_about_us']) : 'Passionately crafting exceptional culinary experiences.';
    $email = !empty($row['d_email']) ? htmlspecialchars($row['d_email']) : 'contact@example.com';
    // Location: Area, City, State only
    $locParts = array_filter([
        !empty($row['d_address2']) ? trim($row['d_address2']) : null,
        !empty($row['d_city']) ? trim($row['d_city']) : null,
        !empty($row['d_state']) ? trim($row['d_state']) : null,
    ]);
    $location = !empty($locParts) ? htmlspecialchars(implode(', ', $locParts)) : 'Berlin, Germany';
    $website = !empty($row['d_website']) ? htmlspecialchars($row['d_website']) : '';
    $google_direction = !empty($row['d_location']) ? htmlspecialchars($row['d_location']) : '';
    $share_url = $base_url . '/' . htmlspecialchars($row['card_id'] ?? $card_id_slug);

    // Social share links (share profile URL to each platform)
    // Note: Instagram & YouTube have no web share URLs - only link when profile/channel URL exists
    $social_links = [
        ['icon' => 'facebook-f', 'url' => 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($share_url)],
    ];
    if (!empty(trim($row['d_instagram'] ?? ''))) $social_links[] = ['icon' => 'instagram', 'url' => trim($row['d_instagram'])];
    $social_links[] = ['icon' => 'linkedin-in', 'url' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . urlencode($share_url)];
    if (!empty(trim($row['d_youtube'] ?? ''))) $social_links[] = ['icon' => 'youtube', 'url' => trim($row['d_youtube'])];
    $social_links[] = ['icon' => 'x-twitter', 'url' => 'https://twitter.com/intent/tweet?url=' . urlencode($share_url) . '&text=' . urlencode($hero_name)];
    $social_links[] = ['icon' => 'pinterest', 'url' => 'https://pinterest.com/pin/create/button/?url=' . urlencode($share_url) . '&description=' . urlencode($hero_name)];

    // Services from card_products_services (products & services)
    $services = [];
    $svc_query = mysqli_query($connect, "SELECT product_name, product_description, product_image FROM card_products_services WHERE card_id='$card_db_id' ORDER BY display_order ASC");
    if ($svc_query) {
        while ($s = mysqli_fetch_assoc($svc_query)) {
            if (!empty($s['product_name'])) {
                $img = '';
                if (!empty($s['product_image']) && strpos($s['product_image'], '.') !== false && strpos($s['product_image'], '/') === false) {
                    $img = '../assets/upload/websites/product-and-services/' . htmlspecialchars($s['product_image']);
                } elseif (!empty($s['product_image'])) {
                    $img = 'data:image/*;base64,' . base64_encode($s['product_image']);
                } else {
                    $img = 'https://images.unsplash.com/photo-1555244162-803834f70033?w=300&h=200&fit=crop';
                }
                $services[] = [
                    'name' => htmlspecialchars($s['product_name']),
                    'desc' => !empty($s['product_description']) ? htmlspecialchars($s['product_description']) : '',
                    'image' => $img,
                ];
            }
        }
    }
    if (empty($services)) {
        $services = [
            ['name' => 'Private Dining', 'desc' => 'Exclusive dining experiences tailored to your preferences.', 'image' => 'https://images.unsplash.com/photo-1555244162-803834f70033?w=300&h=200&fit=crop'],
            ['name' => 'Event Catering', 'desc' => 'Full-service catering for weddings, corporate events & more.', 'image' => 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=300&h=200&fit=crop'],
            ['name' => 'Masterclasses', 'desc' => 'Hands-on cooking classes for all skill levels.', 'image' => 'https://images.unsplash.com/photo-1577219491135-ce391730fb2c?w=300&h=200&fit=crop'],
        ];
    }

    // Special offers from card_special_offers
    $offers = [];
    $off_query = mysqli_query($connect, "SELECT offer_title, offer_description, offer_image, discount_percentage, badge FROM card_special_offers WHERE card_id='$card_db_id' AND status='Active' ORDER BY display_order ASC");
    if ($off_query) {
        while ($o = mysqli_fetch_assoc($off_query)) {
            if (!empty($o['offer_title'])) {
                $img = 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=600&h=300&fit=crop';
                if (!empty($o['offer_image']) && strpos($o['offer_image'], '.') !== false) {
                    $img = '../assets/upload/websites/special-offers/' . htmlspecialchars($o['offer_image']);
                }
                $offers[] = [
                    'title' => htmlspecialchars($o['offer_title']),
                    'desc' => htmlspecialchars($o['offer_description'] ?? ''),
                    'image' => $img,
                    'badge' => !empty($o['badge']) ? htmlspecialchars($o['badge']) : (!empty($o['discount_percentage']) ? $o['discount_percentage'] . '% OFF' : 'OFFER'),
                ];
            }
        }
    }
    if (empty($offers)) {
        $offers = [
            ['title' => 'Special Offer', 'desc' => 'Contact us for details.', 'image' => 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=600&h=300&fit=crop', 'badge' => 'OFFER'],
        ];
    }

    // Products from card_product_pricing (grouped by category for Blinkit UI)
    // category_name: from product_categories OR user_custom_categories
    // Fallback: when both NULL but product_category has id, use "Category N" so products don't all lump into "mains"
    $products_by_cat = [];
    $cat_display_names = [];
    $prod_query = mysqli_query($connect, "
        SELECT pp.*,
            COALESCE(
                pc.category_name,
                ucc.category_name,
                CASE WHEN pp.product_category IS NOT NULL AND pp.product_category > 0
                    THEN CONCAT('Category ', pp.product_category) ELSE NULL END
            ) as category_name
        FROM card_product_pricing pp
        LEFT JOIN product_categories pc ON pp.product_category = pc.id
        LEFT JOIN user_custom_categories ucc ON pp.product_category = ucc.id AND ucc.is_active = 1
        WHERE pp.card_id='$card_db_id'
        ORDER BY pp.product_category, pp.display_order ASC
    ");
    if ($prod_query) {
        while ($p = mysqli_fetch_assoc($prod_query)) {
            if (!empty($p['product_name'])) {
                $cat = !empty($p['category_name']) ? strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim($p['category_name']))) : 'mains';
                if (!isset($products_by_cat[$cat])) {
                    $products_by_cat[$cat] = [];
                    $cat_display_names[$cat] = !empty($p['category_name']) ? trim($p['category_name']) : 'Mains';
                }
                $img = 'https://images.unsplash.com/photo-1544025162-d76694265947?w=300&h=300&fit=crop';
                if (!empty($p['product_image'])) {
                    if (is_string($p['product_image']) && strlen($p['product_image']) < 255 && strpos($p['product_image'], '.') !== false && strpos($p['product_image'], '/') === false && strpos($p['product_image'], '\\') === false) {
                        $img = '../assets/upload/websites/product-pricing/' . htmlspecialchars($p['product_image']);
                    } elseif (!is_string($p['product_image']) || strlen($p['product_image']) > 100) {
                        $img = 'data:image/*;base64,' . base64_encode($p['product_image']);
                    }
                }
                $mrp = floatval($p['mrp'] ?? 0);
                $price = floatval($p['selling_price'] ?? 0);
                if ($mrp <= 0) $mrp = $price;
                $products_by_cat[$cat][] = [
                    'name' => htmlspecialchars($p['product_name']),
                    'image' => $img,
                    'mrp' => $mrp,
                    'price' => $price,
                    'desc' => htmlspecialchars($p['product_description'] ?? ''),
                ];
            }
        }
    }
    // Build cat_order and labels from DB categories (no "All" tab - only real categories)
    $cat_order = array_keys($products_by_cat);
    $cat_labels = [];
    $cat_icons = [
        'mains' => 'https://cdn-icons-png.flaticon.com/512/3480/3480823.png',
        'starters' => 'https://cdn-icons-png.flaticon.com/512/2515/2515183.png',
        'desserts' => 'https://cdn-icons-png.flaticon.com/512/3233/3233015.png',
        'drinks' => 'https://cdn-icons-png.flaticon.com/512/3050/3050116.png',
    ];
    foreach ($cat_order as $ck) {
        $cat_labels[$ck] = isset($cat_display_names[$ck]) ? $cat_display_names[$ck] : ucfirst(str_replace('_', ' ', $ck));
        if (!isset($cat_icons[$ck])) $cat_icons[$ck] = $cat_icons['mains'];
    }

    // Gallery from card_image_gallery
    $gallery = [];
    $gal_query = mysqli_query($connect, "SELECT gallery_image FROM card_image_gallery WHERE card_id='$card_db_id' ORDER BY display_order ASC");
    if ($gal_query) {
        while ($g = mysqli_fetch_assoc($gal_query)) {
            if (!empty($g['gallery_image'])) {
                if (is_string($g['gallery_image']) && strpos($g['gallery_image'], '.') !== false && strpos($g['gallery_image'], '/') === false) {
                    $gallery[] = '../assets/upload/websites/image-gallery/' . htmlspecialchars($g['gallery_image']);
                } else {
                    $gallery[] = 'data:image/*;base64,' . base64_encode($g['gallery_image']);
                }
            }
        }
    }
    if (empty($gallery)) {
        $gallery = ['https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=400&h=400&fit=crop'];
    }

    // Videos from d_youtube1..d_youtube20 (YouTube, Shorts, Instagram, Facebook, etc.)
    $videos = [];
    $default_thumb = 'https://images.unsplash.com/photo-1556910110-a5a63dfd393c?w=600&h=400&fit=crop';
    $vid_idx = 0;
    for ($i = 1; $i <= 20; $i++) {
        $url = trim($row['d_youtube' . $i] ?? '');
        if (empty($url)) continue;
        $parsed = parseVideoUrl($url, $default_thumb, $vid_idx);
        $videos[] = [
            'url' => $url,
            'title' => $parsed['title'],
            'thumb' => $parsed['thumb'],
            'platform' => $parsed['platform'],
            'embed_url' => $parsed['embed_url'] ?? $url,
        ];
        $vid_idx++;
    }

    // Business Hours from d_business_hours (JSON)
    $business_hours = [];
    if (!empty($row['d_business_hours'])) {
        $bh_decoded = json_decode($row['d_business_hours'], true);
        if (is_array($bh_decoded)) $business_hours = $bh_decoded;
    }
    if (empty($business_hours)) {
        $business_hours = [
            ['days' => 'Monday - Thursday', 'hours' => '10:00 AM - 10:00 PM'],
            ['days' => 'Friday - Saturday', 'hours' => '10:00 AM - 12:00 AM'],
            ['days' => 'Sunday', 'hours' => 'Closed'],
        ];
    }
} else {
    // Demo fallback data
    $hero_name = 'Olivia Murray';
    $hero_title = 'Executive Chef';
    $hero_cover = 'https://images.unsplash.com/photo-1556910103-1c02745aae4d?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80';
    $hero_logo = 'https://images.unsplash.com/photo-1583394838336-acd977736f90?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80';
    $phone = '1234567890';
    $whatsapp = '1234567890';
    $about = 'Passionately crafting exceptional culinary experiences. With over 15 years in fine dining, I specialize in blending classic techniques with modern flavor profiles to deliver an unforgettable taste journey right to your table or private event.';
    $email = 'olivia@gourmet.com';
    $location = 'Berlin, Germany';
    $website = 'www.oliviaculinary.com';
    $google_direction = '';
    $share_url = $base_url . '/demo/n.php';

    // Demo: Instagram & YouTube have no web share URLs - only show share-capable platforms
    $social_links = [
        ['icon' => 'facebook-f', 'url' => 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($share_url)],
        ['icon' => 'linkedin-in', 'url' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . urlencode($share_url)],
        ['icon' => 'x-twitter', 'url' => 'https://twitter.com/intent/tweet?url=' . urlencode($share_url) . '&text=' . urlencode($hero_name)],
        ['icon' => 'pinterest', 'url' => 'https://pinterest.com/pin/create/button/?url=' . urlencode($share_url) . '&description=' . urlencode($hero_name)],
    ];

    $services =[];

    $offers = [];

    // Products from card_product_pricing (dynamic - no hardcoding)
    $products_by_cat = [];
    $cat_display_names = [];
    $db_config = dirname(__DIR__) . '/app/config/database.php';
    if (file_exists($db_config)) {
        require_once $db_config;
        $demo_card_query = mysqli_query($connect, "SELECT pp.card_id FROM card_product_pricing pp INNER JOIN digi_card dc ON dc.id = pp.card_id ORDER BY pp.card_id ASC");
        if ($demo_card_query && $dc = mysqli_fetch_assoc($demo_card_query)) {
            $card_db_id = intval($dc['card_id']);
            $prod_query = mysqli_query($connect, "
                SELECT pp.*,
                    COALESCE(pc.category_name, ucc.category_name,
                        CASE WHEN pp.product_category IS NOT NULL AND pp.product_category > 0 THEN CONCAT('Category ', pp.product_category) ELSE NULL END
                    ) as category_name
                FROM card_product_pricing pp
                LEFT JOIN product_categories pc ON pp.product_category = pc.id
                LEFT JOIN user_custom_categories ucc ON pp.product_category = ucc.id AND ucc.is_active = 1
                WHERE pp.card_id='$card_db_id'
                ORDER BY pp.product_category, pp.display_order ASC
            ");
            if ($prod_query) {
                while ($p = mysqli_fetch_assoc($prod_query)) {
                    if (!empty($p['product_name'])) {
                        $cat = !empty($p['category_name']) ? strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim($p['category_name']))) : 'mains';
                        if (!isset($products_by_cat[$cat])) {
                            $products_by_cat[$cat] = [];
                            $cat_display_names[$cat] = !empty($p['category_name']) ? trim($p['category_name']) : 'Mains';
                        }
                        $img = 'https://images.unsplash.com/photo-1544025162-d76694265947?w=300&h=300&fit=crop';
                        if (!empty($p['product_image'])) {
                            if (is_string($p['product_image']) && strlen($p['product_image']) < 255 && strpos($p['product_image'], '.') !== false && strpos($p['product_image'], '/') === false && strpos($p['product_image'], '\\') === false) {
                                $img = '../assets/upload/websites/product-pricing/' . htmlspecialchars($p['product_image']);
                            } elseif (!is_string($p['product_image']) || strlen($p['product_image']) > 100) {
                                $img = 'data:image/*;base64,' . base64_encode($p['product_image']);
                            }
                        }
                        $mrp = floatval($p['mrp'] ?? 0);
                        $price = floatval($p['selling_price'] ?? 0);
                        if ($mrp <= 0) $mrp = $price;
                        $products_by_cat[$cat][] = [
                            'name' => htmlspecialchars($p['product_name']),
                            'image' => $img,
                            'mrp' => $mrp,
                            'price' => $price,
                            'desc' => htmlspecialchars($p['product_description'] ?? ''),
                        ];
                    }
                }
            }
        }
    }

    $business_hours = [
        ['days' => 'Monday - Thursday', 'hours' => '10:00 AM - 10:00 PM'],
        ['days' => 'Friday - Saturday', 'hours' => '10:00 AM - 12:00 AM'],
        ['days' => 'Sunday', 'hours' => 'Closed'],
    ];

    $gallery = [
        'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=400&h=400&fit=crop',
        'https://images.unsplash.com/photo-1476224203421-9ac39bcb3327?w=400&h=400&fit=crop',
        'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=400&h=400&fit=crop',
        'https://images.unsplash.com/photo-1482049016688-2d3e1b311543?w=400&h=400&fit=crop',
        'https://images.unsplash.com/photo-1493770348161-369560ae357d?w=400&h=400&fit=crop',
        'https://images.unsplash.com/photo-1484723091791-c0e7e8c809e3?w=400&h=400&fit=crop',
        'https://images.unsplash.com/photo-1504754524776-8f4f37790ca0?w=400&h=400&fit=crop',
        'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=400&h=400&fit=crop',
        'https://images.unsplash.com/photo-1432139555190-58524dae6a55?w=400&h=400&fit=crop',
        'https://images.unsplash.com/photo-1565958011703-44f9829ba187?w=400&h=400&fit=crop',
    ];
    $videos = []; // Demo fallback: no videos when no card loaded
}
$cat_order = !empty($products_by_cat) ? array_keys($products_by_cat) : ['mains', 'starters', 'desserts', 'drinks'];
$cat_labels = ['mains' => 'Mains', 'starters' => 'Starters', 'desserts' => 'Desserts', 'drinks' => 'Drinks'];
if (!empty($cat_display_names) && is_array($cat_display_names)) {
    foreach ($cat_display_names as $ck => $label) { $cat_labels[$ck] = $label; }
}
$cat_icons = [
    'mains' => 'https://cdn-icons-png.flaticon.com/512/3480/3480823.png',
    'starters' => 'https://cdn-icons-png.flaticon.com/512/2515/2515183.png',
    'desserts' => 'https://cdn-icons-png.flaticon.com/512/3233/3233015.png',
    'drinks' => 'https://cdn-icons-png.flaticon.com/512/3050/3050116.png',
];
$products_flat = [];
if (!empty($products_by_cat)) {
    foreach ($cat_order as $ck) {
        if (isset($products_by_cat[$ck])) {
            foreach ($products_by_cat[$ck] as $p) { $products_flat[] = $p; }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($hero_name); ?></title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: 'var(--mw-primary-color)',
                        secondary: 'var(--mw-secondary-color)',
                        bgbase: 'var(--mw-background-color)',
                        cardbg: 'var(--mw-card-background)',
                        textmain: 'var(--mw-text-color)',
                        heading: 'var(--mw-heading-color)',
                    },
                    fontFamily: {
                        heading: ['var(--mw-font-heading)', 'cursive'],
                        body: ['var(--mw-font-body)', 'sans-serif'],
                    },
                    borderRadius: { theme: 'var(--mw-border-radius)' }
                }
            }
        }
    </script>

    <!-- External CSS -->
    <link rel="stylesheet" href="css/theme.css">
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/components.css">
</head>
<body>

<div class="app-container">

    <!-- 1. Hero Section -->
    <section id="mw-hero" class="mw-hero relative">
        <div class="h-64 md:h-80 w-full overflow-hidden relative">
            <img src="<?php echo htmlspecialchars($hero_cover); ?>" alt="Cover" class="w-full h-full object-cover opacity-60">
            <div class="absolute inset-0 bg-gradient-to-t from-bgbase to-transparent"></div>
        </div>

        <div class="mw-section-padding relative -mt-24 text-center z-10 flex flex-col items-center">
            <div class="w-32 h-32 rounded-full border-4 border-bgbase shadow-card overflow-hidden mb-4">
                <img src="<?php echo htmlspecialchars($hero_logo); ?>" alt="Logo" class="w-full h-full object-cover">
            </div>
            <h1 class="text-3xl md:text-4xl font-bold mb-1"><?php echo $hero_name; ?></h1>
           

            <div class="flex gap-4 w-full max-w-sm justify-center">
                <a href="tel:+<?php echo $phone; ?>" class="flex-1 bg-cardbg border border-primary text-primary py-3 px-4 rounded-theme hover:bg-primary hover:text-bgbase transition-colors flex items-center justify-center gap-2 font-medium">
                    <i class="fas fa-phone-alt"></i> Call Now
                </a>
                <a href="https://wa.me/<?php echo $whatsapp; ?>" class="flex-1 bg-cardbg border border-primary text-primary py-3 px-4 rounded-theme hover:bg-primary hover:text-bgbase transition-colors flex items-center justify-center gap-2 font-medium">
                    <i class="fab fa-whatsapp"></i> WhatsApp
                </a>
            </div>
        </div>
    </section>

    <!-- 2. Social Links -->
    <section class="mw-social-links pb-6 flex justify-center gap-5">
        <?php foreach ($social_links as $s): ?>
        <a href="<?php echo htmlspecialchars($s['url']); ?>" target="_blank" rel="noopener" class="w-10 h-10 rounded-full bg-cardbg flex items-center justify-center text-primary hover:bg-primary hover:text-bgbase transition"><i class="fab fa-<?php echo $s['icon']; ?>"></i></a>
        <?php endforeach; ?>
    </section>

    <!-- 3. Business Intro -->
    <section class="mw-business-intro mw-section-padding text-center">
        <p class="text-sm md:text-base leading-relaxed max-w-3xl mx-auto">
            <?php echo nl2br($about); ?>
        </p>
    </section>

    <!-- 4. Quick Action Grid -->
    <section class="mw-action-grid mw-section-padding">
        <h2 class="mw-section-title">Contact</h2>
        <div class="grid grid-cols-2 gap-4 max-w-4xl mx-auto">
            <div class="mw-card p-4 flex flex-col gap-2 group cursor-default">
                <span class="mw-contact-icon inline-flex w-10 h-10 items-center justify-center rounded-theme text-primary transition-all duration-300 group-hover:bg-primary group-hover:text-bgbase">
                    <i class="fas fa-envelope text-xl"></i>
                </span>
                <h3 class="text-xs uppercase tracking-wider text-textmain mt-2">Email Address</h3>
                <p class="text-heading font-medium text-sm truncate"><?php echo $email; ?></p>
            </div>
            <div class="mw-card p-4 flex flex-col gap-2 group cursor-default">
                <span class="mw-contact-icon inline-flex w-10 h-10 items-center justify-center rounded-theme text-primary transition-all duration-300 group-hover:bg-primary group-hover:text-bgbase">
                    <i class="fas fa-phone-alt text-xl"></i>
                </span>
                <h3 class="text-xs uppercase tracking-wider text-textmain mt-2">Phone Number</h3>
                <p class="text-heading font-medium text-sm">+<?php echo $phone; ?></p>
            </div>
            <div class="mw-card p-4 flex flex-col gap-2 group cursor-default">
                <span class="mw-contact-icon inline-flex w-10 h-10 items-center justify-center rounded-theme text-primary transition-all duration-300 group-hover:bg-primary group-hover:text-bgbase">
                    <i class="fas fa-map-marker-alt text-xl"></i>
                </span>
                <h3 class="text-xs uppercase tracking-wider text-textmain mt-2">Location</h3>
                <p class="text-heading font-medium text-sm"><?php echo $location; ?></p>
            </div>
            <?php if (!empty($google_direction)): ?>
            <div class="mw-card p-4 flex flex-col gap-2 group cursor-default">
                <span class="mw-contact-icon inline-flex w-10 h-10 items-center justify-center rounded-theme text-primary transition-all duration-300 group-hover:bg-primary group-hover:text-bgbase">
                    <i class="fas fa-directions text-xl"></i>
                </span>
                <h3 class="text-xs uppercase tracking-wider text-textmain mt-2">Google Direction</h3>
                <a href="<?php echo htmlspecialchars((strpos($google_direction, 'http') === 0 ? $google_direction : 'https://' . $google_direction)); ?>" target="_blank" rel="noopener" class="text-heading font-medium text-sm truncate hover:underline">Get Directions</a>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- 5. QR Share Section -->
    <section class="mw-qr-share mw-section-padding bg-cardbg/50">
        <h2 class="mw-section-title">Share Profile</h2>
        <div class="max-w-md mx-auto mw-card p-6 text-center">
            <div class="bg-white p-2 w-40 h-40 mx-auto rounded-lg mb-6">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($share_url); ?>" alt="QR Code" class="w-full h-full">
            </div>
            <div class="space-y-4">
                <input type="tel" id="mw-share-wa-input" placeholder="Enter WhatsApp Number" class="mw-input" maxlength="15">
                <button type="button" id="mw-share-wa-btn" class="mw-share-btn flex-1 bg-cardbg border border-primary text-primary py-3 px-4 rounded-theme hover:bg-primary hover:text-bgbase active:scale-[0.98] transition-all duration-300 flex items-center justify-center gap-2 font-medium w-full cursor-pointer">
                    <i class="fab fa-whatsapp"></i> Share on WhatsApp
                </button>
                <div class="flex gap-4">
                    <button type="button" id="mw-save-contact-btn" class="mw-share-btn flex-1 bg-cardbg border border-primary text-primary py-3 px-4 rounded-theme hover:bg-primary hover:text-bgbase active:scale-[0.98] transition-all duration-300 flex items-center justify-center gap-2 font-medium cursor-pointer">
                        <i class="fas fa-address-card"></i> Save Contact
                    </button>
                    <button type="button" id="mw-share-link-btn" class="mw-share-btn flex-1 bg-cardbg border border-primary text-primary py-3 px-4 rounded-theme hover:bg-primary hover:text-bgbase active:scale-[0.98] transition-all duration-300 flex items-center justify-center gap-2 font-medium cursor-pointer">
                        <i class="fas fa-link"></i> Share Link
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- 6. Services Section -->
    <section id="mw-services" class="mw-services mw-section-padding bg-cardbg/30">
        <h2 class="mw-section-title">Our Services</h2>

        <!-- Services Grid (visible by default) -->
        <div id="mw-services-grid" class="mw-grid-services">
            <?php foreach ($services as $idx => $svc): ?>
            <div class="mw-card mw-offer-card mw-service-card bg-cardbg rounded-theme overflow-hidden relative" data-svc-index="<?php echo $idx; ?>" role="button" tabindex="0">
                <div class="mw-service-image-wrap">
                    <img src="<?php echo htmlspecialchars($svc['image']); ?>" alt="<?php echo htmlspecialchars($svc['name']); ?>">
                </div>
                <div class="p-5">
                    <h3 class="text-heading font-semibold text-lg mb-1"><?php echo $svc['name']; ?></h3>
                    <p class="mw-service-desc-preview text-sm text-textmain line-clamp-3"><?php echo !empty($svc['desc']) ? htmlspecialchars($svc['desc']) : 'Contact us for details.'; ?></p>
                    <div class="mw-service-desc-full hidden text-sm text-textmain mt-2 leading-relaxed"><?php echo !empty($svc['desc']) ? nl2br(htmlspecialchars($svc['desc'])) : 'Contact us for details.'; ?></div>
                    <button type="button" class="mw-service-read-more text-primary text-sm font-medium mt-2 hover:underline">Read more</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Desktop: Inline Expanded View (within product box, with prev/next arrows) -->
        <div id="mw-service-expanded-box" class="mw-service-expanded-box" aria-hidden="true">
            <div class="mw-card mw-offer-card relative overflow-hidden">
                <button type="button" class="mw-service-expanded-close absolute top-4 right-4 z-20 w-10 h-10 rounded-full bg-cardbg/90 hover:bg-cardbg text-heading flex items-center justify-center transition shadow-lg" aria-label="Close"><i class="fas fa-times"></i></button>
                <div class="relative">
                    <div class="mw-service-expanded-image-wrap aspect-[4/3] overflow-hidden relative bg-gray-900 flex items-center justify-center">
                        <img id="mw-service-expanded-img" src="" alt="" class="w-full h-full object-contain">
                        <button type="button" class="mw-service-expanded-prev absolute left-4 top-1/2 -translate-y-1/2 z-10 w-10 h-10 md:w-12 md:h-12 rounded-full bg-white/95 hover:bg-white text-gray-800 flex items-center justify-center shadow-lg transition" aria-label="Previous"><i class="fas fa-chevron-left"></i></button>
                        <button type="button" class="mw-service-expanded-next absolute right-4 top-1/2 -translate-y-1/2 z-10 w-10 h-10 md:w-12 md:h-12 rounded-full bg-white/95 hover:bg-white text-gray-800 flex items-center justify-center shadow-lg transition" aria-label="Next"><i class="fas fa-chevron-right"></i></button>
                    </div>
                    <div class="p-5 md:p-6">
                        <h3 id="mw-service-expanded-title" class="text-heading font-semibold text-xl mb-3"></h3>
                        <p id="mw-service-expanded-desc" class="text-sm text-textmain leading-relaxed whitespace-pre-line"></p>
                    </div>
                </div>
                <div class="flex justify-center gap-2 py-3 text-sm text-textmain border-t border-white/10">
                    <span id="mw-service-expanded-counter">1</span> / <?php echo count($services); ?>
                </div>
            </div>
        </div>
    </section>


    <!-- 7. Special Offers Section -->
    <section id="mw-offers" class="mw-special-offers mw-section-padding bg-cardbg/30">
        <h2 class="mw-section-title">Special Offers</h2>
        <div class="mw-grid-offers">
            <?php foreach ($offers as $off): ?>
            <div class="mw-card mw-offer-card bg-cardbg rounded-theme relative overflow-hidden">
                <div class="mw-offer-badge absolute top-3 left-3 px-3 py-1 rounded-full text-xs font-bold z-10" style="background: var(--mw-offer-badge-bg); color: var(--mw-offer-badge-color);"><?php echo htmlspecialchars($off['badge']); ?></div>
                <div class="mw-offer-image-wrap aspect-[4/3] overflow-hidden">
                    <img src="<?php echo htmlspecialchars($off['image']); ?>" alt="<?php echo htmlspecialchars($off['title']); ?>" class="w-full h-full object-cover">
                </div>
                <div class="p-5">
                    <h3 class="text-heading font-semibold text-lg mb-1"><?php echo $off['title']; ?></h3>
                    <p class="mw-offer-desc-preview text-sm text-textmain line-clamp-1"><?php echo !empty($off['desc']) ? htmlspecialchars($off['desc']) : 'Contact us for details.'; ?></p>
                    <div class="mw-offer-desc-full hidden text-sm text-textmain mt-2 leading-relaxed"><?php echo !empty($off['desc']) ? nl2br(htmlspecialchars($off['desc'])) : 'Contact us for details.'; ?></div>
                    <button type="button" class="mw-offer-read-more text-primary text-sm font-medium mt-2 hover:underline">Read more</button>
                    <a href="https://wa.me/<?php echo $whatsapp; ?>?text=Hi! I'm interested in: <?php echo urlencode($off['title']); ?>" target="_blank" class="block w-full py-2.5 rounded-theme font-semibold transition text-center mt-4" style="background: var(--mw-offer-cta-bg); color: #111;">Get Offer</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- 8. Products Section (Blinkit Style) -->
    <?php if (!empty($products_by_cat)): ?>
    <section id="mw-products" class="mw-products mw-section-padding">
        <h2 class="mw-section-title">Shop Online</h2>
        
        <div id="mw-products-blinkit" class="mw-blinkit-container">
            <!-- Sidebar Categories -->
            <div class="mw-blinkit-sidebar" id="categorySidebar">
                <?php foreach ($cat_order as $idx => $cat_key): if (!isset($products_by_cat[$cat_key]) || empty($products_by_cat[$cat_key])) continue; ?>
                <div class="mw-cat-item <?php echo $idx === 0 ? 'active' : ''; ?>" data-cat="<?php echo htmlspecialchars($cat_key); ?>">
                    <div class="mw-cat-img-box"><img src="<?php echo htmlspecialchars($cat_icons[$cat_key] ?? $cat_icons['mains']); ?>" class="w-full h-full object-contain" alt=""></div>
                    <span class="text-[10px] md:text-xs font-medium text-heading"><?php echo htmlspecialchars($cat_labels[$cat_key] ?? ucfirst($cat_key)); ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Products Area -->
            <div class="mw-blinkit-main">
                <?php foreach ($cat_order as $idx => $cat_key): if (!isset($products_by_cat[$cat_key]) || empty($products_by_cat[$cat_key])) continue; ?>
                <div class="product-category-grid mw-grid-products <?php echo $idx === 0 ? 'active' : 'hidden'; ?>" id="grid-<?php echo htmlspecialchars($cat_key); ?>">
                    <?php foreach ($products_by_cat[$cat_key] as $pidx => $prod):
                        $global_idx = 0;
                        foreach ($cat_order as $ok) {
                            if ($ok === $cat_key) { $global_idx += $pidx; break; }
                            $global_idx += isset($products_by_cat[$ok]) ? count($products_by_cat[$ok]) : 0;
                        } ?>
                    <div class="mw-card mw-product-card bg-white text-gray-800 overflow-hidden rounded-xl shadow-md p-2" data-product-index="<?php echo $global_idx; ?>">
                        <div class="mw-product-image-wrap mw-product-click-area aspect-[4/3] overflow-hidden relative rounded-t-xl cursor-pointer" data-product-index="<?php echo $global_idx; ?>" role="button" tabindex="0">
                            <img src="<?php echo htmlspecialchars($prod['image']); ?>" class="w-full h-full object-cover" alt="<?php echo htmlspecialchars($prod['name']); ?>">
                            <button type="button" class="mw-btn-add-shop mw-add-to-cart absolute bottom-2 right-2 z-10" data-product-index="<?php echo $global_idx; ?>" onclick="event.stopPropagation()">ADD</button>
                        </div>
                        <div class="p-3">
                            <h3 class="font-semibold text-sm leading-tight mb-1 text-gray-900"><?php echo htmlspecialchars($prod['name']); ?></h3>
                            <p class="mw-product-desc-preview text-xs text-gray-500 line-clamp-1"><?php echo !empty($prod['desc']) ? htmlspecialchars($prod['desc']) : 'Contact us for details.'; ?></p>
                            <div class="mw-product-desc-full hidden text-xs text-gray-500 mt-2 leading-relaxed"><?php echo !empty($prod['desc']) ? nl2br(htmlspecialchars($prod['desc'])) : 'Contact us for details.'; ?></div>
                            <button type="button" class="mw-product-read-more text-primary text-xs font-medium mt-1 hover:underline">Read more</button>
                            <div class="flex items-center gap-2 justify-between mt-2">
                                <?php if (isset($prod['mrp']) && $prod['mrp'] > $prod['price']): ?>
                                <span class="text-xs text-gray-400 line-through font-bold">₹<?php echo number_format($prod['mrp']); ?></span>
                                <?php else: ?>
                                <span></span>
                                <?php endif; ?>
                                <span class="font-bold text-sm text-gray-900">₹<?php echo number_format($prod['price']); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>

            </div>
        </div>

        <!-- Desktop: Inline Expanded View (within section, like Services) -->
        <div id="mw-product-expanded-box" class="mw-product-expanded-box" aria-hidden="true">
            <div class="mw-card mw-offer-card relative overflow-hidden bg-white text-gray-800">
                <button type="button" class="mw-product-expanded-close absolute top-4 right-4 z-20 w-10 h-10 rounded-full bg-gray-200 hover:bg-gray-300 text-gray-800 flex items-center justify-center transition shadow-lg" aria-label="Close"><i class="fas fa-times"></i></button>
                <div class="relative">
                    <div class="mw-product-expanded-image-wrap aspect-[4/3] overflow-hidden relative bg-gray-100 flex items-center justify-center">
                        <img id="mw-product-expanded-img" src="" alt="" class="w-full h-full object-contain">
                        <button type="button" class="mw-product-expanded-prev absolute left-4 top-1/2 -translate-y-1/2 z-10 w-10 h-10 md:w-12 md:h-12 rounded-full bg-white/95 hover:bg-white text-gray-800 flex items-center justify-center shadow-lg transition" aria-label="Previous"><i class="fas fa-chevron-left"></i></button>
                        <button type="button" class="mw-product-expanded-next absolute right-4 top-1/2 -translate-y-1/2 z-10 w-10 h-10 md:w-12 md:h-12 rounded-full bg-white/95 hover:bg-white text-gray-800 flex items-center justify-center shadow-lg transition" aria-label="Next"><i class="fas fa-chevron-right"></i></button>
                    </div>
                    <div class="p-5 md:p-6">
                        <h3 id="mw-product-expanded-title" class="text-gray-900 font-semibold text-xl mb-3"></h3>
                        <p id="mw-product-expanded-desc" class="text-sm text-gray-500 leading-relaxed whitespace-pre-line mb-4"></p>
                        <div class="flex items-center gap-3 mb-4">
                            <span id="mw-product-expanded-mrp" class="text-sm text-gray-400 line-through font-bold hidden"></span>
                            <span id="mw-product-expanded-price" class="font-bold text-lg text-gray-900"></span>
                        </div>
                        <button type="button" class="mw-btn-add-shop mw-add-to-cart py-2.5 px-6 rounded-theme" id="mw-product-expanded-add">ADD</button>
                    </div>
                </div>
                <div class="flex justify-center gap-2 py-3 text-sm text-gray-500 border-t border-gray-200">
                    <span id="mw-product-expanded-counter">1</span> / <?php echo count($products_flat); ?>
                </div>
            </div>
        </div>
    </section>

    <?php endif; ?>

    <!-- 9. Video Section (1 col mobile, 3 cols desktop, 6 visible + Load more) -->
    <?php if (!empty($videos)): ?>
    <section class="mw-video-gallery mw-section-padding bg-cardbg/20">
        <h2 class="mw-section-title">Videos</h2>
        <div class="mw-grid-videos">
            <?php foreach ($videos as $idx => $v): ?>
            <div class="mw-video-item mw-card aspect-video relative group cursor-pointer overflow-hidden block <?php echo $idx >= 6 ? 'mw-video-hidden' : ''; ?>" data-video-url="<?php echo htmlspecialchars($v['embed_url'] ?? $v['url']); ?>" data-video-fallback="<?php echo htmlspecialchars($v['url']); ?>" role="button" tabindex="0">
                <img src="<?php echo htmlspecialchars($v['thumb']); ?>" class="w-full h-full object-cover opacity-60 group-hover:opacity-80 transition" alt="<?php echo htmlspecialchars($v['title']); ?>">
                <div class="absolute inset-0 flex items-center justify-center"><div class="w-12 h-12 bg-primary/90 text-bgbase rounded-full flex items-center justify-center text-xl group-hover:scale-110 transition shadow-lg"><i class="fas fa-play ml-1"></i></div></div>
                <div class="absolute bottom-2 left-3 right-3 text-heading text-sm font-medium drop-shadow-md truncate"><?php echo htmlspecialchars($v['title']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (count($videos) > 6): ?>
        <div id="mw-videos-load-more-wrap" class="mt-6 text-center">
            <button type="button" id="mw-videos-load-more" class="w-full max-w-xs mx-auto py-3 px-6 rounded-theme font-semibold transition bg-primary/20 text-primary hover:bg-primary hover:text-bgbase border border-primary/50">
                Load more (<?php echo count($videos) - 6; ?> more)
            </button>
        </div>
        <?php endif; ?>
    </section>

    <!-- Video Modal (play within Miniwebsite) -->
    <div id="mw-video-modal" class="mw-video-modal" aria-hidden="true">
        <button type="button" class="mw-video-modal-close" aria-label="Close"><i class="fas fa-times"></i></button>
        <div class="mw-video-modal-content">
            <iframe id="mw-video-modal-iframe" src="" title="Video" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        </div>
    </div>
    <?php endif; ?>

    <!-- 10. Image Gallery -->
    <section id="mw-gallery" class="mw-image-gallery mw-section-padding">
        <h2 class="mw-section-title">Gallery</h2>
        <div class="mw-grid-gallery">
            <?php foreach ($gallery as $g_img): ?>
            <div class="aspect-square rounded-lg overflow-hidden border border-white/5"><img src="<?php echo htmlspecialchars($g_img); ?>" class="w-full h-full object-cover hover:scale-110 transition duration-300" alt="Gallery"></div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- 11. Payment QR Section - Show all uploaded QR codes -->
    <?php
    $payment_qrs = [];
    if ($row) {
        if (!empty($row['d_qr_paytm'])) $payment_qrs[] = ['label' => 'Paytm', 'img' => 'data:image/*;base64,' . base64_encode($row['d_qr_paytm']), 'upi' => trim($row['d_paytm'] ?? '')];
        if (!empty($row['d_qr_google_pay'])) $payment_qrs[] = ['label' => 'Google Pay', 'img' => 'data:image/*;base64,' . base64_encode($row['d_qr_google_pay']), 'upi' => trim($row['d_google_pay'] ?? '')];
        if (!empty($row['d_qr_phone_pay'])) $payment_qrs[] = ['label' => 'PhonePe', 'img' => 'data:image/*;base64,' . base64_encode($row['d_qr_phone_pay']), 'upi' => trim($row['d_phone_pay'] ?? '')];
    }
    if (empty($payment_qrs)) {
        $payment_qrs = [['label' => 'Scan & Pay', 'img' => 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=UPI://pay?pa=chef@upi&pn=Olivia', 'upi' => 'chef@upi']];
    }
    ?>
    <section id="mw-pay" class="mw-payment-qr mw-section-padding">
        <h2 class="mw-section-title">QR Code</h2>
        <div class="max-w-2xl mx-auto">
            <div class="grid grid-cols-1 sm:grid-cols-2 <?php echo count($payment_qrs) >= 3 ? 'md:grid-cols-3' : ''; ?> gap-6">
                <?php foreach ($payment_qrs as $qr): ?>
                <div class="mw-card p-6 text-center">
                    <h3 class="text-heading font-medium mb-4 uppercase tracking-widest text-sm"><?php echo htmlspecialchars($qr['label']); ?></h3>
                    <div class="bg-white p-2 rounded-lg aspect-square w-40 mx-auto overflow-hidden group cursor-pointer">
                        <img src="<?php echo htmlspecialchars($qr['img']); ?>" alt="<?php echo htmlspecialchars($qr['label']); ?> QR" class="w-full h-full object-contain transition-transform duration-300 group-hover:scale-105">
                    </div>
                    <?php if (!empty($qr['upi'])): ?>
                    <p class="text-heading font-medium text-sm mt-3 break-all"><?php echo htmlspecialchars($qr['upi']); ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- 12. Business Information (Hierarchical Format) -->
    <section class="mw-business-info mw-section-padding bg-cardbg/20">
        <h2 class="mw-section-title">Business Hours</h2>
        <div class="max-w-lg mx-auto">
            <div class="space-y-3 text-sm md:text-base border-t border-b border-white/10 py-6">
                <?php foreach ($business_hours as $bh): ?>
                <div class="flex justify-between items-center">
                    <span class="text-textmain"><?php echo htmlspecialchars($bh['days'] ?? ''); ?></span>
                    <span class="text-heading font-medium<?php echo (isset($bh['hours']) && stripos($bh['hours'], 'closed') !== false) ? ' text-primary' : ''; ?>"><?php echo htmlspecialchars($bh['hours'] ?? ''); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="mt-8 text-center space-y-2 text-sm text-textmain">
                <?php
                $biz_name = $hero_name;
                $biz_addr = $location;
                $biz_gst = '';
                $biz_web = '';
                if ($row && isset($row['d_gst_number'])) $biz_gst = htmlspecialchars($row['d_gst_number']);
                if ($row && isset($row['d_website'])) $biz_web = htmlspecialchars($row['d_website']);
                if (!$row) {
                    $biz_name = 'Chef Olivia Murray';
                    $biz_addr = 'Berlin, Germany - 10115';
                    $biz_gst = $biz_gst ?: '12XXXXX3456XXZ';
                    $biz_web = $biz_web ?: 'www.oliviaculinary.com';
                } else {
                    $addr_parts = array_filter([
                        !empty($row['d_address']) ? $row['d_address'] : null,
                        !empty($row['d_address2']) ? $row['d_address2'] : null,
                        !empty($row['d_city']) ? $row['d_city'] : null,
                        !empty($row['d_state']) ? $row['d_state'] : null
                    ]);
                    $biz_addr = implode(' ', $addr_parts) ?: $location;
                }
                ?>
                <p><span class="text-heading"><?php echo $biz_name; ?></span></p>
                <p>Address: <?php echo htmlspecialchars($biz_addr); ?></p>
                <?php if (!empty($biz_gst)): ?><p>GST No: <span class="text-heading"><?php echo $biz_gst; ?></span></p><?php endif; ?>
                <?php if (!empty($biz_web)): ?>
                <a href="<?php echo htmlspecialchars((strpos($biz_web, 'http') === 0 ? $biz_web : 'https://' . $biz_web)); ?>" target="_blank" class="text-primary hover:underline flex items-center justify-center gap-2 mt-2">
                    <i class="fas fa-globe"></i> <?php echo htmlspecialchars(preg_replace('#^https?://#', '', $biz_web)); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- 13. Footer -->
    <footer class="mw-footer mw-section-padding text-center border-t border-white/5 mt-8">
        <p class="text-xs text-textmain mb-2">&copy; <?php echo date('Y'); ?> <?php echo $hero_name; ?>. All rights reserved.</p>
        <p class="text-[10px] uppercase tracking-widest text-textmain/50">Powered by <span class="text-primary">MiniWebsite.in</span></p>
    </footer>

</div>

<!-- Shop Cart Bar (visible when products added) -->
<?php if (!empty($products_flat)): ?>
<div id="mw-shop-cart-bar" class="mw-shop-cart-bar hidden fixed left-0 right-0 bottom-[60px] z-[45] bg-cardbg/98 backdrop-blur border-t border-white/10 shadow-[0_-4px_20px_rgba(0,0,0,0.3)] px-4 py-3 flex items-center justify-between gap-4">
    <div class="flex items-center gap-2">
        <span id="mw-cart-count" class="bg-primary text-bgbase font-bold text-sm w-7 h-7 rounded-full flex items-center justify-center">0</span>
        <span id="mw-cart-label" class="text-heading font-medium text-sm">items in cart</span>
    </div>
    <button type="button" id="mw-cart-share-wa" class="w-12 h-12 bg-[#25D366] text-white rounded-full flex items-center justify-center text-2xl hover:bg-[#20bd5a] transition shadow-lg" aria-label="Share on WhatsApp">
        <i class="fab fa-whatsapp"></i>
    </button>
</div>
<?php endif; ?>

<!-- 14. Sticky Bottom Navigation -->
<nav class="mw-sticky-nav">
    <a href="#mw-hero" class="mw-nav-item active" data-section="mw-hero"><i class="fas fa-home mw-nav-icon"></i><span>Home</span></a>
    <a href="#mw-services" class="mw-nav-item" data-section="mw-services"><i class="fas fa-concierge-bell mw-nav-icon"></i><span>Serv</span></a>
    <a href="#mw-offers" class="mw-nav-item" data-section="mw-offers"><i class="fas fa-tags mw-nav-icon"></i><span>Offers</span></a>
    <a href="#mw-products" class="mw-nav-item <?php echo empty($products_by_cat) ? 'hidden' : ''; ?>" data-section="mw-products"><i class="fas fa-store mw-nav-icon"></i><span>Shop</span></a>
    <a href="#mw-gallery" class="mw-nav-item" data-section="mw-gallery"><i class="fas fa-images mw-nav-icon"></i><span>Gallery</span></a>
    <a href="#mw-pay" class="mw-nav-item hidden sm:flex" data-section="mw-pay"><i class="fas fa-qrcode mw-nav-icon"></i><span>Pay</span></a>
</nav>

<!-- 15. Floating WhatsApp Button (hidden when cart bar is visible) -->
<a id="mw-floating-wa-btn" href="https://wa.me/<?php echo $whatsapp; ?>" target="_blank" class="fixed bottom-[80px] right-4 md:right-8 w-14 h-14 bg-[#25D366] text-white rounded-full flex items-center justify-center text-3xl shadow-[0_4px_15px_rgba(37,211,102,0.4)] z-50 hover:scale-110 transition-transform">
    <i class="fab fa-whatsapp"></i>
</a>

<!-- Config for JS (API key, WhatsApp number, products for cart, share profile) -->
<script>
    window.MW_AI_API_KEY = "<?php echo htmlspecialchars(defined('GEMINI_API_KEY') ? GEMINI_API_KEY : ''); ?>";
    window.MW_WHATSAPP_NUMBER = "<?php echo htmlspecialchars($whatsapp); ?>";
    window.MW_PRODUCTS = <?php echo json_encode($products_flat ?? []); ?>;
    window.MW_SHARE_URL = <?php echo json_encode($share_url ?? ''); ?>;
    window.MW_HERO_NAME = <?php echo json_encode($hero_name ?? ''); ?>;
    window.MW_PHONE = <?php echo json_encode($phone ?? ''); ?>;
    window.MW_EMAIL = <?php echo json_encode($email ?? ''); ?>;
</script>
<script src="js/app.js"></script>

</body>
</html>