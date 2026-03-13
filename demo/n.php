<?php
/**
 * Demo template - Data binding from database (digi_card) or fallback demo data
 * Access: demo/n.php?n=card_id_slug  (loads from DB) or demo/n.php (uses demo data)
 */
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
        $query = mysqli_query($connect, "SELECT * FROM digi_card WHERE card_id='$card_id_esc'");
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
    $hero_cover = !empty($row['d_logo']) ? 'data:image/*;base64,' . base64_encode($row['d_logo']) : 'https://images.unsplash.com/photo-1556910103-1c02745aae4d?w=1200&q=80';
    $hero_logo = $hero_cover; // Use same or separate cover/logo in digi_card
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
    $location = !empty($row['d_address']) ? htmlspecialchars($row['d_address']) : 'Berlin, Germany';
    $website = !empty($row['d_website']) ? htmlspecialchars($row['d_website']) : '';
    $share_url = $base_url . '/' . htmlspecialchars($row['card_id'] ?? $card_id_slug);

    // Social links from digi_card
    $social_links = [
        ['icon' => 'facebook-f', 'url' => $row['d_fb'] ?? '#'],
        ['icon' => 'instagram', 'url' => $row['d_instagram'] ?? '#'],
        ['icon' => 'linkedin-in', 'url' => $row['d_linkedin'] ?? '#'],
        ['icon' => 'youtube', 'url' => $row['d_youtube'] ?? '#'],
        ['icon' => 'twitter', 'url' => $row['d_twitter'] ?? '#'],
    ];

    // Services from card_products_services (products & services)
    $services = [];
    $svc_query = mysqli_query($connect, "SELECT product_name, product_image FROM card_products_services WHERE card_id='$card_db_id' ORDER BY display_order ASC LIMIT 10");
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
                $services[] = ['name' => htmlspecialchars($s['product_name']), 'image' => $img];
            }
        }
    }
    if (empty($services)) {
        $services = [
            ['name' => 'Private Dining', 'image' => 'https://images.unsplash.com/photo-1555244162-803834f70033?w=300&h=200&fit=crop'],
            ['name' => 'Event Catering', 'image' => 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=300&h=200&fit=crop'],
            ['name' => 'Masterclasses', 'image' => 'https://images.unsplash.com/photo-1577219491135-ce391730fb2c?w=300&h=200&fit=crop'],
        ];
    }

    // Special offers from card_special_offers
    $offers = [];
    $off_query = mysqli_query($connect, "SELECT offer_title, offer_description, offer_image, discount_percentage, badge FROM card_special_offers WHERE card_id='$card_db_id' AND status='Active' ORDER BY display_order ASC LIMIT 5");
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
    $products_by_cat = [];
    $prod_query = mysqli_query($connect, "SELECT pp.*, pc.category_name FROM card_product_pricing pp LEFT JOIN product_categories pc ON pp.product_category = pc.id WHERE pp.card_id='$card_db_id' ORDER BY pp.product_category, pp.display_order ASC");
    if ($prod_query) {
        while ($p = mysqli_fetch_assoc($prod_query)) {
            if (!empty($p['product_name'])) {
                $cat = !empty($p['category_name']) ? strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim($p['category_name']))) : 'mains';
                if (!isset($products_by_cat[$cat])) $products_by_cat[$cat] = [];
                $img = 'https://images.unsplash.com/photo-1544025162-d76694265947?w=300&h=300&fit=crop';
                if (!empty($p['product_image']) && strpos($p['product_image'], '.') !== false && strpos($p['product_image'], '/') === false) {
                    $img = '../assets/upload/websites/product-pricing/' . htmlspecialchars($p['product_image']);
                }
                $products_by_cat[$cat][] = [
                    'name' => htmlspecialchars($p['product_name']),
                    'image' => $img,
                    'price' => floatval($p['selling_price'] ?? 0),
                    'desc' => htmlspecialchars($p['product_description'] ?? ''),
                ];
            }
        }
    }
    // Build cat_order and labels from DB categories
    $cat_order = array_keys($products_by_cat);
    $cat_labels = [];
    $cat_icons = [
        'mains' => 'https://cdn-icons-png.flaticon.com/512/3480/3480823.png',
        'starters' => 'https://cdn-icons-png.flaticon.com/512/2515/2515183.png',
        'desserts' => 'https://cdn-icons-png.flaticon.com/512/3233/3233015.png',
        'drinks' => 'https://cdn-icons-png.flaticon.com/512/3050/3050116.png',
    ];
    foreach ($cat_order as $ck) {
        $cat_labels[$ck] = ucfirst(str_replace('_', ' ', $ck));
        if (!isset($cat_icons[$ck])) $cat_icons[$ck] = $cat_icons['mains'];
    }

    // Gallery from card_image_gallery
    $gallery = [];
    $gal_query = mysqli_query($connect, "SELECT gallery_image FROM card_image_gallery WHERE card_id='$card_db_id' ORDER BY display_order ASC LIMIT 10");
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
    $share_url = $base_url . '/demo/n.php';

    $social_links = [
        ['icon' => 'facebook-f', 'url' => '#'],
        ['icon' => 'instagram', 'url' => '#'],
        ['icon' => 'linkedin-in', 'url' => '#'],
        ['icon' => 'youtube', 'url' => '#'],
        ['icon' => 'twitter', 'url' => '#'],
    ];

    $services = [
        ['name' => 'Private Dining', 'image' => 'https://images.unsplash.com/photo-1555244162-803834f70033?w=300&h=200&fit=crop'],
        ['name' => 'Event Catering', 'image' => 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=300&h=200&fit=crop'],
        ['name' => 'Masterclasses', 'image' => 'https://images.unsplash.com/photo-1577219491135-ce391730fb2c?w=300&h=200&fit=crop'],
        ['name' => 'Menu Consulting', 'image' => 'https://images.unsplash.com/photo-1556910103-1c02745aae4d?w=300&h=200&fit=crop'],
        ['name' => 'Wine Pairing', 'image' => 'https://images.unsplash.com/photo-1510812431401-41d2bd2722f3?w=300&h=200&fit=crop'],
        ['name' => 'Food Styling', 'image' => 'https://images.unsplash.com/photo-1481833761820-0509d3217039?w=300&h=200&fit=crop'],
        ['name' => 'Corporate Lunch', 'image' => 'https://images.unsplash.com/photo-1507048331197-7d4ac70811cf?w=300&h=200&fit=crop'],
        ['name' => 'Kitchen Staffing', 'image' => 'https://images.unsplash.com/photo-1600565193348-f74bd3c7ccdf?w=300&h=200&fit=crop'],
        ['name' => 'Diet Plans', 'image' => 'https://images.unsplash.com/photo-1490645935967-10de6ba17061?w=300&h=200&fit=crop'],
        ['name' => 'Equipment Rental', 'image' => 'https://images.unsplash.com/photo-1583394838336-acd977736f90?w=300&h=200&fit=crop'],
    ];

    $offers = [
        ['title' => 'Romantic Dinner', 'desc' => '5-course meal setup for couples.', 'image' => 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=600&h=300&fit=crop', 'badge' => '25% OFF'],
        ['title' => 'BBQ Party', 'desc' => 'Book 10+ people, free grill setup.', 'image' => 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=600&h=300&fit=crop', 'badge' => 'FREE GRILL'],
        ['title' => 'Meat Lover Combo', 'desc' => 'Ribs & Wings Platter combo.', 'image' => 'https://images.unsplash.com/photo-1544025162-d76694265947?w=600&h=300&fit=crop', 'badge' => 'COMBO'],
        ['title' => 'Pasta Night', 'desc' => 'Discount on all Italian catering.', 'image' => 'https://images.unsplash.com/photo-1551183053-bf91a1d81141?w=600&h=300&fit=crop', 'badge' => '15% OFF'],
        ['title' => 'Wine Tasting', 'desc' => 'Introductory price for groups.', 'image' => 'https://images.unsplash.com/photo-1510812431401-41d2bd2722f3?w=600&h=300&fit=crop', 'badge' => 'NEW'],
    ];

    $products_by_cat = [
        'mains' => [
            ['name' => 'BBQ Pork Ribs', 'image' => 'https://images.unsplash.com/photo-1544025162-d76694265947?w=300&h=300&fit=crop', 'price' => 799, 'desc' => '500g'],
            ['name' => 'Red Sauce Pasta', 'image' => 'https://images.unsplash.com/photo-1551183053-bf91a1d81141?w=300&h=300&fit=crop', 'price' => 320, 'desc' => '1 Portion'],
            ['name' => 'Truffle Burger', 'image' => 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=300&h=300&fit=crop', 'price' => 499, 'desc' => '1 Pc'],
            ['name' => 'Schezwan Noodles', 'image' => 'https://images.unsplash.com/photo-1612929633738-8fe01f72813c?w=300&h=300&fit=crop', 'price' => 280, 'desc' => '300g'],
            ['name' => 'Steak Frites', 'image' => 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=300&h=300&fit=crop', 'price' => 1200, 'desc' => '350g'],
        ],
        'starters' => [
            ['name' => 'Garlic Bread', 'image' => 'https://images.unsplash.com/photo-1626082927389-6cd097cdc6ec?w=300&h=300&fit=crop', 'price' => 150, 'desc' => '4 Pcs'],
            ['name' => 'Bruschetta', 'image' => 'https://images.unsplash.com/photo-1541014741259-de529411b96a?w=300&h=300&fit=crop', 'price' => 220, 'desc' => '6 Pcs'],
            ['name' => 'Cheese Balls', 'image' => 'https://images.unsplash.com/photo-1564834724105-918b73d1b9e0?w=300&h=300&fit=crop', 'price' => 180, 'desc' => '8 Pcs'],
            ['name' => 'Chicken Wings', 'image' => 'https://images.unsplash.com/photo-1604908176997-125f25cc6f3d?w=300&h=300&fit=crop', 'price' => 299, 'desc' => '6 Pcs'],
            ['name' => 'Spring Rolls', 'image' => 'https://images.unsplash.com/photo-1599487488170-d11ec9c172f0?w=300&h=300&fit=crop', 'price' => 160, 'desc' => '4 Pcs'],
        ],
        'desserts' => [
            ['name' => 'Cheesecake', 'image' => 'https://images.unsplash.com/photo-1551024506-0bccd828d307?w=300&h=300&fit=crop', 'price' => 250, 'desc' => '1 Slice'],
            ['name' => 'Chocolate Cake', 'image' => 'https://images.unsplash.com/photo-1578985545062-69928b1d9587?w=300&h=300&fit=crop', 'price' => 200, 'desc' => '1 Slice'],
            ['name' => 'Tiramisu', 'image' => 'https://images.unsplash.com/photo-1563805042-7684c8a9e9cb?w=300&h=300&fit=crop', 'price' => 280, 'desc' => '1 Portion'],
            ['name' => 'Ice Cream', 'image' => 'https://images.unsplash.com/photo-1497034825429-c343d7c6a68f?w=300&h=300&fit=crop', 'price' => 120, 'desc' => '2 Scoops'],
            ['name' => 'Apple Pie', 'image' => 'https://images.unsplash.com/photo-1514844308465-4dcfbc3e9f4d?w=300&h=300&fit=crop', 'price' => 220, 'desc' => '1 Slice'],
        ],
        'drinks' => [
            ['name' => 'Mojito', 'image' => 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?w=300&h=300&fit=crop', 'price' => 180, 'desc' => '300ml'],
            ['name' => 'Cold Coffee', 'image' => 'https://images.unsplash.com/photo-1544145945-f90425340c7e?w=300&h=300&fit=crop', 'price' => 150, 'desc' => '300ml'],
            ['name' => 'Cappuccino', 'image' => 'https://images.unsplash.com/photo-1497935586351-b67a49e012bf?w=300&h=300&fit=crop', 'price' => 120, 'desc' => '200ml'],
            ['name' => 'Coke Can', 'image' => 'https://images.unsplash.com/photo-1622483767028-3f66f32aef97?w=300&h=300&fit=crop', 'price' => 60, 'desc' => '330ml'],
            ['name' => 'Fresh Lime', 'image' => 'https://images.unsplash.com/photo-1546171753-97d7676e4602?w=300&h=300&fit=crop', 'price' => 80, 'desc' => '300ml'],
        ],
    ];

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
}

$videos = [ // Static for now (youtube links can be from d_youtube1..20 in future)
    ['title' => 'Perfect Steaks Guide', 'thumb' => 'https://images.unsplash.com/photo-1556910110-a5a63dfd393c?w=600&h=400&fit=crop'],
    ['title' => 'Plating Masterclass', 'thumb' => 'https://images.unsplash.com/photo-1478144592103-25e218a04891?w=600&h=400&fit=crop'],
    ['title' => 'Behind the Scenes', 'thumb' => 'https://images.unsplash.com/photo-1600565193348-f74bd3c7ccdf?w=600&h=400&fit=crop'],
    ['title' => 'How to chop like a pro', 'thumb' => 'https://images.unsplash.com/photo-1507048331197-7d4ac70811cf?w=600&h=400&fit=crop'],
    ['title' => 'Wine Pairing 101', 'thumb' => 'https://images.unsplash.com/photo-1510812431401-41d2bd2722f3?w=600&h=400&fit=crop'],
    ['title' => 'My Kitchen Tour', 'thumb' => 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=600&h=400&fit=crop'],
];
$cat_order = ['mains', 'starters', 'desserts', 'drinks'];
$cat_labels = ['mains' => 'Mains', 'starters' => 'Starters', 'desserts' => 'Desserts', 'drinks' => 'Drinks'];
$cat_icons = [
    'mains' => 'https://cdn-icons-png.flaticon.com/512/3480/3480823.png',
    'starters' => 'https://cdn-icons-png.flaticon.com/512/2515/2515183.png',
    'desserts' => 'https://cdn-icons-png.flaticon.com/512/3233/3233015.png',
    'drinks' => 'https://cdn-icons-png.flaticon.com/512/3050/3050116.png',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($hero_name); ?> - <?php echo htmlspecialchars($hero_title); ?></title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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
            <p class="text-primary font-medium tracking-wide text-sm md:text-base mb-6"><?php echo $hero_title; ?></p>

            <div class="flex gap-4 w-full max-w-sm justify-center">
                <a href="tel:+<?php echo $phone; ?>" class="flex-1 bg-cardbg border border-primary text-primary py-3 px-4 rounded-theme hover:bg-primary hover:text-bgbase transition-colors flex items-center justify-center gap-2 font-medium">
                    <i class="fas fa-phone-alt"></i> Call Now
                </a>
                <a href="https://wa.me/<?php echo $whatsapp; ?>" class="flex-1 bg-primary text-bgbase py-3 px-4 rounded-theme hover:bg-secondary transition-colors flex items-center justify-center gap-2 font-medium">
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
            <div class="mw-card p-4 flex flex-col gap-2">
                <i class="fas fa-envelope text-primary text-xl"></i>
                <h3 class="text-xs uppercase tracking-wider text-textmain mt-2">Email Address</h3>
                <p class="text-heading font-medium text-sm truncate"><?php echo $email; ?></p>
            </div>
            <div class="mw-card p-4 flex flex-col gap-2">
                <i class="fas fa-phone-alt text-primary text-xl"></i>
                <h3 class="text-xs uppercase tracking-wider text-textmain mt-2">Phone Number</h3>
                <p class="text-heading font-medium text-sm">+<?php echo $phone; ?></p>
            </div>
            <div class="mw-card p-4 flex flex-col gap-2">
                <i class="fas fa-map-marker-alt text-primary text-xl"></i>
                <h3 class="text-xs uppercase tracking-wider text-textmain mt-2">Location</h3>
                <p class="text-heading font-medium text-sm"><?php echo $location; ?></p>
            </div>
            <?php if (!empty($website)): ?>
            <div class="mw-card p-4 flex flex-col gap-2">
                <i class="fas fa-globe text-primary text-xl"></i>
                <h3 class="text-xs uppercase tracking-wider text-textmain mt-2">Website</h3>
                <p class="text-heading font-medium text-sm truncate"><?php echo $website; ?></p>
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
                <input type="tel" placeholder="Enter WhatsApp Number" class="mw-input">
                <button class="w-full bg-primary text-bgbase font-bold py-3 rounded-theme hover:bg-secondary transition flex items-center justify-center gap-2">
                    <i class="fab fa-whatsapp"></i> Share on WhatsApp
                </button>
                <div class="flex gap-4">
                    <button class="flex-1 bg-transparent border border-primary text-primary font-medium py-2 rounded-theme hover:bg-primary/10 transition">Save Contact</button>
                    <button class="flex-1 bg-transparent border border-primary text-primary font-medium py-2 rounded-theme hover:bg-primary/10 transition">Share Link</button>
                </div>
            </div>
        </div>
    </section>

    <!-- 6. Services Section -->
    <section id="mw-services" class="mw-services mw-section-padding">
        <h2 class="mw-section-title">Our Services</h2>
        <div class="mw-grid-services">
            <?php foreach ($services as $svc): ?>
            <div class="mw-card p-4 text-center hover:border-primary/50 transition">
                <img src="<?php echo htmlspecialchars($svc['image']); ?>" class="w-full h-24 object-cover rounded-lg mb-3" alt="<?php echo htmlspecialchars($svc['name']); ?>">
                <h3 class="text-heading font-semibold text-sm mb-1"><?php echo $svc['name']; ?></h3>
            </div>
            <?php endforeach; ?>
        </div>
    </section>


    <!-- 7. Special Offers Section -->
    <section id="mw-offers" class="mw-special-offers mw-section-padding bg-cardbg/30">
        <h2 class="mw-section-title">Special Offers</h2>
        <div class="mw-grid-offers">
            <?php foreach ($offers as $off): ?>
            <div class="mw-card mw-offer-card bg-cardbg rounded-theme">
                <div class="absolute top-3 left-3 px-3 py-1 rounded-full text-xs font-bold z-10" style="background: var(--mw-offer-badge-bg); color: var(--mw-offer-badge-color);"><?php echo htmlspecialchars($off['badge']); ?></div>
                <img src="<?php echo htmlspecialchars($off['image']); ?>" alt="<?php echo htmlspecialchars($off['title']); ?>" class="w-full h-40 object-cover">
                <div class="p-5">
                    <h3 class="text-heading font-semibold text-lg mb-1"><?php echo $off['title']; ?></h3>
                    <p class="text-sm text-textmain mb-4"><?php echo $off['desc']; ?></p>
                    <a href="https://wa.me/<?php echo $whatsapp; ?>?text=Hi! I'm interested in: <?php echo urlencode($off['title']); ?>" target="_blank" class="block w-full py-2.5 rounded-theme font-semibold transition text-center" style="background: var(--mw-offer-cta-bg); color: #111;">Get Offer</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- 8. Products Section (Blinkit Style) -->
    <?php if (!empty($products_by_cat)): ?>
    <section id="mw-products" class="mw-products mw-section-padding">
        <h2 class="mw-section-title">Order Menu</h2>
        
        <div class="mw-blinkit-container">
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
                    <?php foreach ($products_by_cat[$cat_key] as $prod): ?>
                    <div class="mw-card mw-product-card bg-white text-gray-800">
                        <img src="<?php echo htmlspecialchars($prod['image']); ?>" class="w-full aspect-square object-cover rounded-md mb-2" alt="<?php echo htmlspecialchars($prod['name']); ?>">
                        <h3 class="font-semibold text-sm leading-tight mb-1"><?php echo htmlspecialchars($prod['name']); ?></h3>
                        <p class="text-xs text-gray-500 mb-2"><?php echo htmlspecialchars($prod['desc']); ?></p>
                        <div class="mt-auto flex justify-between items-center">
                            <span class="font-bold text-sm">₹<?php echo number_format($prod['price']); ?></span>
                            <a href="https://wa.me/<?php echo $whatsapp; ?>?text=Hi! I want to order: <?php echo urlencode($prod['name']); ?>" target="_blank" class="mw-btn-add">ADD</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>

            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- 9. Video Section -->
    <section class="mw-video-gallery mw-section-padding bg-cardbg/20">
        <h2 class="mw-section-title">Cooking Vlogs</h2>
        <div class="mw-slider-horizontal hide-scrollbar">
            <?php foreach ($videos as $v): ?>
            <div class="mw-slider-item mw-card w-[80vw] md:w-[300px] h-[45vw] md:h-[180px] relative group cursor-pointer overflow-hidden">
                <img src="<?php echo htmlspecialchars($v['thumb']); ?>" class="w-full h-full object-cover opacity-60 group-hover:opacity-80 transition" alt="<?php echo htmlspecialchars($v['title']); ?>">
                <div class="absolute inset-0 flex items-center justify-center"><div class="w-12 h-12 bg-primary/90 text-bgbase rounded-full flex items-center justify-center text-xl group-hover:scale-110 transition shadow-lg"><i class="fas fa-play ml-1"></i></div></div>
                <div class="absolute bottom-2 left-3 right-3 text-heading text-sm font-medium drop-shadow-md truncate"><?php echo htmlspecialchars($v['title']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- 10. Image Gallery -->
    <section id="mw-gallery" class="mw-image-gallery mw-section-padding">
        <h2 class="mw-section-title">Gallery</h2>
        <div class="mw-grid-gallery">
            <?php foreach ($gallery as $g_img): ?>
            <div class="aspect-square rounded-lg overflow-hidden border border-white/5"><img src="<?php echo htmlspecialchars($g_img); ?>" class="w-full h-full object-cover hover:scale-110 transition duration-300" alt="Gallery"></div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- 11. Payment QR Section -->
    <section id="mw-pay" class="mw-payment-qr mw-section-padding">
        <h2 class="mw-section-title">QR Code</h2>
        <div class="max-w-xs mx-auto text-center">
            <div class="mw-card p-6">
                <h3 class="text-heading font-medium mb-4 uppercase tracking-widest text-sm">Scan & Pay</h3>
                <div class="bg-white p-2 rounded-lg aspect-square w-48 mx-auto mb-6">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=UPI://pay?pa=chef@upi&pn=Olivia" alt="Payment QR" class="w-full h-full">
                </div>
                <div class="flex justify-center gap-4 text-2xl text-textmain">
                    <i class="fab fa-google-pay hover:text-primary transition cursor-pointer"></i>
                    <i class="fab fa-amazon-pay hover:text-primary transition cursor-pointer"></i>
                    <i class="fas fa-rupee-sign hover:text-primary transition cursor-pointer"></i>
                </div>
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
                        !empty($row['d_state']) ? $row['d_state'] : null,
                        !empty($row['d_pincode']) ? $row['d_pincode'] : null,
                        !empty($row['d_country']) ? $row['d_country'] : null,
                    ]);
                    $biz_addr = implode(', ', $addr_parts) ?: $location;
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
        <div class="font-heading text-3xl text-heading mb-4">Create Your Brand</div>
        <p class="text-xs text-textmain mb-2">&copy; <?php echo date('Y'); ?> <?php echo $hero_name; ?>. All rights reserved.</p>
        <p class="text-[10px] uppercase tracking-widest text-textmain/50">Powered by <span class="text-primary">MiniWebsite.in</span></p>
    </footer>

</div>

<!-- 14. Sticky Bottom Navigation -->
<nav class="mw-sticky-nav">
    <a href="#mw-hero" class="mw-nav-item active" data-section="mw-hero"><i class="fas fa-home mw-nav-icon"></i><span>Home</span></a>
    <a href="#mw-services" class="mw-nav-item" data-section="mw-services"><i class="fas fa-concierge-bell mw-nav-icon"></i><span>Serv</span></a>
    <a href="#mw-offers" class="mw-nav-item" data-section="mw-offers"><i class="fas fa-tags mw-nav-icon"></i><span>Offers</span></a>
    <a href="#mw-products" class="mw-nav-item <?php echo empty($products_by_cat) ? 'hidden' : ''; ?>" data-section="mw-products"><i class="fas fa-store mw-nav-icon"></i><span>Shop</span></a>
    <a href="#mw-gallery" class="mw-nav-item" data-section="mw-gallery"><i class="fas fa-images mw-nav-icon"></i><span>Gallery</span></a>
    <a href="#mw-pay" class="mw-nav-item hidden sm:flex" data-section="mw-pay"><i class="fas fa-qrcode mw-nav-icon"></i><span>Pay</span></a>
</nav>

<!-- 15. Floating WhatsApp Button -->
<a href="https://wa.me/<?php echo $whatsapp; ?>" target="_blank" class="fixed bottom-[80px] right-4 md:right-8 w-14 h-14 bg-[#25D366] text-white rounded-full flex items-center justify-center text-3xl shadow-[0_4px_15px_rgba(37,211,102,0.4)] z-50 hover:scale-110 transition-transform">
    <i class="fab fa-whatsapp"></i>
</a>

<!-- Config for JS (API key, WhatsApp number) -->
<script>
    window.MW_AI_API_KEY = "<?php echo htmlspecialchars(defined('GEMINI_API_KEY') ? GEMINI_API_KEY : ''); ?>";
    window.MW_WHATSAPP_NUMBER = "<?php echo htmlspecialchars($whatsapp); ?>";
</script>
<script src="js/app.js"></script>

</body>
</html>