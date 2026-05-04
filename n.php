<?php
/**
 * MiniWebsite card template - data from database (digi_card) or fallback demo data
 * Access: n.php?n=card_id_slug (loads from DB) or n.php (uses demo data)
 */
$thumbnail_config = __DIR__ . '/app/config/thumbnail_api.php';
if (file_exists($thumbnail_config)) require_once $thumbnail_config;

/**
 * Fetch thumbnail URL for Instagram, Facebook, TikTok via LinkPreview API or direct og:image.
 */
function fetchVideoOgThumb($url, $timeout = 4) {
    static $cache = [];
    $url = trim($url);
    $fetchUrl = (preg_match('#^https?://#', $url) ? $url : 'https://' . $url);
    $key = md5($fetchUrl);
    if (isset($cache[$key])) return $cache[$key];
    $clean = preg_replace('#^https?://#', '', $fetchUrl);
    $isSocial = (strpos($clean, 'instagram.com') !== false || strpos($clean, 'facebook.com') !== false ||
                 strpos($clean, 'fb.') !== false || strpos($clean, 'tiktok.com') !== false);
    if (!$isSocial) { $cache[$key] = null; return null; }

    // 1. Try LinkPreview API (works when Instagram/Facebook block direct fetches)
    if (defined('LINKPREVIEW_API_KEY') && LINKPREVIEW_API_KEY) {
        $lpUrl = 'https://api.linkpreview.net/?q=' . urlencode($fetchUrl);
        if (function_exists('curl_init')) {
            $ch = curl_init($lpUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => ['X-Linkpreview-Api-Key: ' . LINKPREVIEW_API_KEY],
            ]);
            $json = @curl_exec($ch);
            curl_close($ch);
        } else {
            $json = @file_get_contents($lpUrl, false, stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'header' => 'X-Linkpreview-Api-Key: ' . LINKPREVIEW_API_KEY . "\r\n",
                ],
            ]));
        }
        if ($json) {
            $data = @json_decode($json, true);
            if (!empty($data['image']) && filter_var($data['image'], FILTER_VALIDATE_URL)) {
                $cache[$key] = $data['image'];
                return $cache[$key];
            }
        }
    }

    // 2. Direct fetch og:image (often blocked by Instagram/Facebook)
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    if (function_exists('curl_init')) {
        $ch = curl_init($fetchUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => $ua,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml', 'Accept-Language: en-US,en;q=0.9'],
        ]);
        $html = @curl_exec($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create([
            'http' => ['timeout' => $timeout, 'follow_location' => true, 'user_agent' => $ua, 'ignore_errors' => true],
        ]);
        $html = @file_get_contents($fetchUrl, false, $ctx);
    }
    if ($html && strlen($html) >= 200) {
        if (preg_match('#<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']#i', $html, $m)) { $cache[$key] = trim($m[1]); return $cache[$key]; }
        if (preg_match('#<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']#i', $html, $m)) { $cache[$key] = trim($m[1]); return $cache[$key]; }
    }
    $cache[$key] = null;
    return null;
}

/** Normalize user-entered video URL for opening in a new tab. */
function mw_normalize_video_watch_url($url) {
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }
    return preg_match('#^https?://#i', $url) ? $url : 'https://' . $url;
}

/**
 * Parse video URL: YouTube → iframe embed; Facebook / Instagram / TikTok → thumbnail + external link only (no iframe).
 *
 * @param string $explicit_type  '', 'auto', 'youtube', 'facebook', 'instagram' (from dashboard)
 * @param string $explicit_thumb_file  Filename stored in assets/upload/websites/video-thumbnails/
 */
function parseVideoUrl($url, $default_thumb = '', $index = 0, $explicit_type = '', $explicit_thumb_file = '') {
    $url = trim((string) $url);
    $fallback = $default_thumb ?: 'assets/img/default.jpg';
    $watch_url = mw_normalize_video_watch_url($url);
    $has_uploaded_thumb = false;
    $thumb = $fallback;
    if ($explicit_thumb_file !== '' && preg_match('/^[a-zA-Z0-9._-]+$/', $explicit_thumb_file)) {
        $absThumb = __DIR__ . '/assets/upload/websites/video-thumbnails/' . $explicit_thumb_file;
        if (is_file($absThumb)) {
            $thumb = 'assets/upload/websites/video-thumbnails/' . $explicit_thumb_file;
            $has_uploaded_thumb = true;
        }
    }

    if ($url === '') {
        return [
            'title' => 'Video',
            'thumb' => $fallback,
            'platform' => 'other',
            'embed_url' => '',
            'watch_url' => '',
            'play_mode' => 'external',
        ];
    }

    $title = 'Video';
    $platform = 'other';
    $embed_url = '';

    // YouTube: watch?v=, youtu.be/, youtube.com/shorts/
    if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/shorts/)([a-zA-Z0-9_-]{11})#', $url, $m)) {
        $vid = $m[1];
        if (!$has_uploaded_thumb) {
            $thumb = "https://img.youtube.com/vi/{$vid}/hqdefault.jpg";
        }
        $embed_url = "https://www.youtube.com/embed/{$vid}?autoplay=1";
        $platform = 'youtube';
        $title = 'YouTube Video';
    } elseif (preg_match('#instagram\.com/(?:reel|p|tv)/#i', $url)) {
        $platform = 'instagram';
        $title = 'Instagram Video';
        if (!$has_uploaded_thumb) {
            $fetchUrl = (preg_match('#^https?://#', $url) ? $url : 'https://' . $url);
            $thumb = fetchVideoOgThumb($fetchUrl) ?: $fallback;
        }
    } elseif (preg_match('#(?:facebook\.com|fb\.watch|fb\.com|m\.facebook\.com)/#i', $url)) {
        $platform = 'facebook';
        $title = 'Facebook Video';
        if (!$has_uploaded_thumb) {
            $fetchUrl = (preg_match('#^https?://#', $url) ? $url : 'https://' . $url);
            $thumb = fetchVideoOgThumb($fetchUrl) ?: $fallback;
        }
    } elseif (preg_match('#tiktok\.com/#i', $url)) {
        $platform = 'tiktok';
        $title = 'TikTok Video';
        if (!$has_uploaded_thumb) {
            $fetchUrl = (preg_match('#^https?://#', $url) ? $url : 'https://' . $url);
            $thumb = fetchVideoOgThumb($fetchUrl) ?: $fallback;
        }
    } else {
        if (!$has_uploaded_thumb) {
            $thumb = $fallback;
        }
    }

    $et = strtolower(trim((string) $explicit_type));
    if (in_array($et, ['youtube', 'facebook', 'instagram'], true)) {
        $platform = $et;
    }

    // Facebook / Instagram must never use iframe (platform policy). TikTok: embed unreliable → external link.
    $play_mode = 'external';
    if ($platform === 'youtube' && $embed_url !== '') {
        $play_mode = 'iframe';
    }
    if ($platform === 'youtube' && $embed_url === '') {
        $play_mode = 'external';
    }

    return [
        'title' => $title,
        'thumb' => $thumb,
        'platform' => $platform,
        'embed_url' => ($play_mode === 'iframe') ? $embed_url : '',
        'watch_url' => $watch_url,
        'play_mode' => $play_mode,
    ];
}

/** Web directory of this script (e.g. /miniwebsite), no trailing slash. */
function mw_demo_script_dir() {
    return rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/n.php')), '/');
}

/** Absolute URL to this MiniWebsite page (clean /slug when possible, else n.php). */
function mw_miniwebsite_profile_url($base_url, $card_identifier) {
    $dir = mw_demo_script_dir();
    if ($card_identifier !== '' && $card_identifier !== null) {
        $slug = rawurlencode((string) $card_identifier);
        return rtrim($base_url, '/') . $dir . '/' . $slug;
    }
    return rtrim($base_url, '/') . $dir . '/n.php';
}

/** Site path under the app web root for an assets-relative URL (n.php at project root). */
function mw_site_relative_from_demo_asset($relative) {
    if ($relative === '' || preg_match('#^https?://#i', $relative) || strpos($relative, 'data:') === 0) {
        return '';
    }
    $rel = ltrim(preg_replace('#^\.\./+#', '', $relative), '/');
    $dir = trim(str_replace('\\', '/', mw_demo_script_dir()), '/');
    if ($dir === '') {
        return $rel;
    }
    return $dir . '/' . $rel;
}

$card_id_slug = isset($_GET['n']) ? trim($_GET['n']) : (isset($_GET['card_number']) ? trim($_GET['card_number']) : '');
$row = null;
$mw_old_slug_redirect = null;
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$assets_base = __DIR__ . '/assets';
// Default image when src is unavailable or broken (use whichever file exists)
$default_image = (file_exists(__DIR__ . '/assets/img/default.jpg') ? 'assets/img/default.jpg' : 'assets/img/deafult.jpg');

/** Product image: file under assets/upload/websites/product-pricing, or default if missing; data URL for large/binary DB blobs. */
function mw_demo_card_product_image_src($product_image, $default_image, $assets_root) {
    if ($product_image === null || $product_image === '') {
        return $default_image;
    }
    if (is_string($product_image) && strlen($product_image) < 255 && strpos($product_image, '.') !== false
        && strpos($product_image, '/') === false && strpos($product_image, '\\') === false) {
        $safe = basename($product_image);
        $abs = $assets_root . '/upload/websites/product-pricing/' . $safe;
        if (is_file($abs)) {
            return 'assets/upload/websites/product-pricing/' . htmlspecialchars($safe);
        }
        return $default_image;
    }
    if (!is_string($product_image) || strlen($product_image) > 100) {
        return 'data:image/*;base64,' . base64_encode($product_image);
    }
    return $default_image;
}

/** Normalize DB date for special offer (DATE / invalid sentinels). */
function mw_demo_clean_offer_date($raw) {
    $d = trim((string) ($raw ?? ''));
    if ($d === '' || $d === '0000-00-00' || strpos($d, '0000-00-00') === 0) {
        return '';
    }
    return $d;
}

/** One line: formatted time (g:i A) or ''. */
function mw_demo_format_offer_time_part($raw) {
    $t = trim((string) ($raw ?? ''));
    if ($t === '') {
        return '';
    }
    $dt = DateTime::createFromFormat('H:i:s', $t) ?: DateTime::createFromFormat('H:i', $t);
    return $dt ? $dt->format('g:i A') : $t;
}

/**
 * Public label for Start/End Dt on the card (no year in the string — e.g. "17 Mar, 9:54 PM").
 * The stored DB date is still the full Y-m-d; strtotime() uses that full date internally.
 * For Offer Expired, use mw_demo_offer_end_moment_ts() / offer raw fields (year included there).
 */
function mw_demo_format_offer_datetime_display($date_raw, $time_raw) {
    $d = mw_demo_clean_offer_date($date_raw);
    $tpart = mw_demo_format_offer_time_part($time_raw);
    if ($d !== '' && $tpart !== '') {
        $t = strtotime($d);
        $date_fmt = $t ? date('j M', $t) : $d;
        return $date_fmt . ', ' . $tpart;
    }
    if ($d !== '') {
        $t = strtotime($d);
        return $t ? date('j M', $t) : $d;
    }
    if ($tpart !== '') {
        return $tpart;
    }
    return '';
}

/**
 * Start Dt / End Dt strings for the card (plain text, not escaped).
 *
 * @return array{start_dt: string, end_dt: string}
 */
function mw_demo_offer_start_end_dt_labels($start_date, $end_date, $start_time, $end_time) {
    return [
        'start_dt' => mw_demo_format_offer_datetime_display($start_date, $start_time),
        'end_dt' => mw_demo_format_offer_datetime_display($end_date, $end_time),
    ];
}

/**
 * Unix timestamp for offer end (background / expiry only). Uses full calendar date from DB including year.
 * Not shown on the page — public labels omit the year via mw_demo_format_offer_datetime_display().
 * Handles: DATE + TIME columns, DATETIME / ISO in end_date, fractional seconds on TIME, T separator.
 */
function mw_demo_offer_end_moment_ts($end_date, $end_time) {
    $raw = trim((string) ($end_date ?? ''));
    if ($raw === '' || $raw === '0000-00-00' || strpos($raw, '0000-00-00') === 0) {
        return null;
    }
    // Full datetime already in end_date (DATETIME column or ISO)
    if (preg_match('/^\d{4}-\d{2}-\d{2}[\sT]\d/', $raw)) {
        $norm = str_replace('T', ' ', $raw);
        $norm = preg_replace('/(\d{2}:\d{2}:\d{2})\.\d+/', '$1', $norm);
        $ts = strtotime($norm);
        return ($ts === false) ? null : $ts;
    }
    if (!preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $dm)) {
        return null;
    }
    $dateOnly = $dm[1];
    $timeRaw = trim((string) ($end_time ?? ''));
    if ($timeRaw !== '') {
        $timeRaw = preg_replace('/\.\d+$/', '', $timeRaw);
        if (preg_match('/^(\d{1,2}:\d{2}(?::\d{2})?)/', $timeRaw, $mm)) {
            $timeRaw = $mm[1];
        } else {
            $timeRaw = '';
        }
    }
    $combined = ($timeRaw !== '') ? ($dateOnly . ' ' . $timeRaw) : ($dateOnly . ' 23:59:59');
    $ts = strtotime($combined);
    if ($ts !== false) {
        return $ts;
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $combined)
        ?: DateTimeImmutable::createFromFormat('Y-m-d G:i:s', $combined)
        ?: DateTimeImmutable::createFromFormat('Y-m-d H:i', $combined)
        ?: DateTimeImmutable::createFromFormat('Y-m-d G:i', $combined);
    if ($dt instanceof DateTimeImmutable) {
        return $dt->getTimestamp();
    }
    return null;
}

/** True when end date/time is set and is strictly before now (date-only → end of that day at 23:59:59). */
function mw_demo_offer_end_dt_expired($end_date, $end_time) {
    $endTs = mw_demo_offer_end_moment_ts($end_date, $end_time);
    if ($endTs === null) {
        return false;
    }
    return $endTs < time();
}

// Try to load from database when card_id provided
if (!empty($card_id_slug)) {
    $db_config = __DIR__ . '/app/config/database.php';
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
        } elseif (!ctype_digit($card_id_slug)) {
            // Locked old MiniWebsite URL — row in digi_card_previous_slug (not on digi_card to avoid row-size limit)
            $q_prev = mysqli_query($connect, "SELECT digi_card_id FROM digi_card_previous_slug WHERE previous_slug='$card_id_esc' LIMIT 1");
            if ($q_prev && mysqli_num_rows($q_prev) > 0) {
                $pr = mysqli_fetch_assoc($q_prev);
                $did = intval($pr['digi_card_id'] ?? 0);
                if ($did > 0) {
                    $did_esc = mysqli_real_escape_string($connect, (string) $did);
                    $q_card = mysqli_query($connect, "SELECT * FROM digi_card WHERE id='$did_esc' LIMIT 1");
                    if ($q_card && mysqli_num_rows($q_card) > 0) {
                        $mw_old_slug_redirect = mysqli_fetch_assoc($q_card);
                    }
                }
            }
        }
    }
}

/** Requested a card by URL (?n= / card_number) but no matching row in the database. */
$mw_card_not_found = ($card_id_slug !== '' && $row === null && $mw_old_slug_redirect === null);
if ($mw_old_slug_redirect !== null) {
    $mw_company_label = trim((string) ($mw_old_slug_redirect['d_display_name'] ?? ''));
    if ($mw_company_label === '') {
        $mw_company_label = trim((string) ($mw_old_slug_redirect['d_comp_name'] ?? 'Company'));
    }
    $mw_new_profile_url = mw_miniwebsite_profile_url($base_url, (string) ($mw_old_slug_redirect['card_id'] ?? ''));
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MiniWebsite URL updated</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>body { font-family: Inter, system-ui, sans-serif; }</style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 flex items-center justify-center p-6">
    <div class="max-w-lg w-full text-center space-y-6">
        <p class="text-lg text-slate-700 leading-relaxed">
            <?php echo htmlspecialchars($mw_company_label, ENT_QUOTES, 'UTF-8'); ?> MiniWebsite URL changed to new URL.
            <a href="<?php echo htmlspecialchars($mw_new_profile_url, ENT_QUOTES, 'UTF-8'); ?>"
               class="text-slate-900 font-medium underline underline-offset-2 hover:text-slate-600">
                Click here to Visit: <?php echo htmlspecialchars($mw_new_profile_url, ENT_QUOTES, 'UTF-8'); ?>
            </a>
        </p>
    </div>
</body>
</html>
<?php
    exit;
}

if ($mw_card_not_found) {
    $mw_miniwebsite_home = rtrim($base_url, '/') . mw_demo_script_dir();
    header('HTTP/1.1 404 Not Found');
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Card not found — MiniWebsite</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>body { font-family: Inter, system-ui, sans-serif; }</style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 flex items-center justify-center p-6">
    <div class="max-w-md w-full text-center space-y-6">
        <p class="text-lg text-slate-600 leading-relaxed">
            This digital card is not available. It may have been removed or the link might be incorrect.
        </p>
        <a href="<?php echo htmlspecialchars($mw_miniwebsite_home, ENT_QUOTES, 'UTF-8'); ?>"
           class="inline-flex items-center justify-center rounded-lg bg-slate-900 text-white px-6 py-3 text-sm font-medium hover:bg-slate-800 transition-colors">
            Go to MiniWebsite
        </a>
    </div>
</body>
</html>
<?php
    exit;
}

/** Card expiry check (based on digi_card.validity_date). */
$mw_card_expired = false;
$mw_card_expiry_text = '';
if ($row) {
    $mw_validity_raw = trim((string) ($row['validity_date'] ?? ''));
    if ($mw_validity_raw !== '' && $mw_validity_raw !== '0000-00-00 00:00:00' && $mw_validity_raw !== '0000-00-00') {
        $mw_validity_ts = strtotime($mw_validity_raw);
        if ($mw_validity_ts !== false && $mw_validity_ts < time()) {
            $mw_card_expired = true;
            $mw_card_expiry_text = date('d M Y', $mw_validity_ts);
        }
    }
}

if ($mw_card_expired) {
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MiniWebsite Expired</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --mw-primary-color: #14b8a6;
            --mw-bg: #0f172a;
            --mw-card: #111827;
            --mw-text: #e5e7eb;
            --mw-muted: #9ca3af;
        }
        body { font-family: Inter, system-ui, sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-[var(--mw-bg)] text-[var(--mw-text)] flex items-center justify-center p-6">
    <div class="w-full max-w-xl rounded-2xl border border-white/10 bg-[var(--mw-card)] shadow-2xl p-8 md:p-10 text-center">
        <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-red-500/20 text-red-300">
            <i class="fas fa-calendar-xmark text-2xl"></i>
        </div>
        <h1 class="text-2xl md:text-3xl font-bold tracking-tight">MiniWebsite is expired</h1>
        <p class="mt-3 text-sm md:text-base text-[var(--mw-muted)]">
            This miniwebsite has reached its validity end date and is no longer available.
        </p>
        <?php if ($mw_card_expiry_text !== ''): ?>
        <p class="mt-4 inline-flex items-center rounded-full border border-red-300/30 bg-red-500/10 px-4 py-1.5 text-sm text-red-200">
            Expired on: <?php echo htmlspecialchars($mw_card_expiry_text, ENT_QUOTES, 'UTF-8'); ?>
        </p>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
    exit;
}

// Build data arrays (from DB or demo fallback)
$primary_business_category_title = '';
if ($row) {
    $card_db_id = intval($row['id']);
    $hero_name = !empty($row['d_comp_name']) ? htmlspecialchars($row['d_comp_name']) : trim((isset($row['d_f_name']) ? $row['d_f_name'] : '') . ' ' . (isset($row['d_l_name']) ? $row['d_l_name'] : ''));
    if (empty($hero_name)) $hero_name = 'Your Name';
    $hero_title = !empty($row['d_position']) ? htmlspecialchars($row['d_position']) : 'Executive Chef';
    $mw_hero_user_id = intval($row['user_id'] ?? 0);
    if ($mw_hero_user_id <= 0 && !empty($row['user_email']) && isset($connect) && $connect) {
        $em_lookup = mysqli_real_escape_string($connect, strtolower(trim((string) $row['user_email'])));
        $uq = @mysqli_query($connect, "SELECT id FROM user_details WHERE LOWER(TRIM(email)) = '$em_lookup' LIMIT 1");
        if ($uq && ($ur = mysqli_fetch_assoc($uq))) {
            $mw_hero_user_id = intval($ur['id'] ?? 0);
        }
    }
    if (isset($connect) && $connect) {
        $pri_cat_name = mw_vcard_resolve_business_category_name($connect, intval($row['d_position_primary'] ?? 0), $mw_hero_user_id);
        if ($pri_cat_name !== '') {
            $primary_business_category_title = htmlspecialchars($pri_cat_name, ENT_QUOTES, 'UTF-8');
        }
    }
    // Hero cover: use d_hero_image_location first, fallback to default image
    $hero_cover = $default_image;
    if (!empty($row['d_hero_image_location'])) {
        $hero_path = trim($row['d_hero_image_location']);
        if (strpos($hero_path, '/') === false) $hero_path = 'assets/upload/websites/company_details/' . $hero_path;
        $hero_cover = ltrim(preg_replace('#^\.\./+#', '', $hero_path), '/');
    }
    $hero_logo = $hero_cover; // placeholder; logo uses d_logo below
    if (!empty($row['d_logo_location'])) {
        $logo_path = trim($row['d_logo_location']);
        if (strpos($logo_path, '/') === false) $logo_path = 'assets/upload/websites/company_details/' . $logo_path;
        $hero_logo = ltrim(preg_replace('#^\.\./+#', '', $logo_path), '/');
    } elseif (!empty($row['d_logo'])) {
        $hero_logo = 'data:image/*;base64,' . base64_encode($row['d_logo']);
    } else {
        $hero_logo = $default_image;
    }
    $phone = !empty($row['d_contact']) ? preg_replace('/[^0-9+]/', '', $row['d_contact']) : '';
    $whatsapp = !empty($row['d_whatsapp']) ? preg_replace('/[^0-9+]/', '', $row['d_whatsapp']) : '';
    $about = !empty($row['d_about_us']) ? htmlspecialchars($row['d_about_us']) : '';
    $email = !empty($row['d_email']) ? htmlspecialchars($row['d_email']) : '';
    // Location: Area, City, State only
    $locParts = array_filter([
        !empty($row['d_address2']) ? trim($row['d_address2']) : null,
        !empty($row['d_city']) ? trim($row['d_city']) : null,
        !empty($row['d_state']) ? trim($row['d_state']) : null,
    ]);
    $location = !empty($locParts) ? htmlspecialchars(implode(', ', $locParts)) : '';
    $website = !empty($row['d_website']) ? htmlspecialchars($row['d_website']) : '';
    $google_direction = !empty($row['d_location']) ? htmlspecialchars($row['d_location']) : '';
    $share_card_key = (string) ($row['card_id'] ?? $card_id_slug);
    $share_url = mw_miniwebsite_profile_url($base_url, $share_card_key);

    // Social profile links: show only when user has added that social URL.
    $social_links = [];
    if (!empty(trim($row['d_fb'] ?? ''))) $social_links[] = ['icon' => 'facebook-f', 'url' => trim($row['d_fb'])];
    if (!empty(trim($row['d_instagram'] ?? ''))) $social_links[] = ['icon' => 'instagram', 'url' => trim($row['d_instagram'])];
    if (!empty(trim($row['d_linkedin'] ?? ''))) $social_links[] = ['icon' => 'linkedin-in', 'url' => trim($row['d_linkedin'])];
    if (!empty(trim($row['d_twitter'] ?? ''))) $social_links[] = ['icon' => 'x-twitter', 'url' => trim($row['d_twitter'])];
    if (!empty(trim($row['d_youtube'] ?? ''))) $social_links[] = ['icon' => 'youtube', 'url' => trim($row['d_youtube'])];
    if (!empty(trim($row['d_pinterest'] ?? ''))) $social_links[] = ['icon' => 'pinterest', 'url' => trim($row['d_pinterest'])];

    // Services from card_products_services (products & services)
    $services = [];
    $svc_query = mysqli_query($connect, "SELECT product_name, product_description, product_image FROM card_products_services WHERE card_id='$card_db_id' ORDER BY display_order ASC");
    if ($svc_query) {
        while ($s = mysqli_fetch_assoc($svc_query)) {
            if (!empty($s['product_name'])) {
                $img = '';
                if (!empty($s['product_image']) && strpos($s['product_image'], '.') !== false && strpos($s['product_image'], '/') === false) {
                    $img = 'assets/upload/websites/product-and-services/' . htmlspecialchars($s['product_image']);
                } elseif (!empty($s['product_image'])) {
                    $img = 'data:image/*;base64,' . base64_encode($s['product_image']);
                } else {
                    $img = $default_image;
                }
                $services[] = [
                    'name' => htmlspecialchars($s['product_name']),
                    'desc' => !empty($s['product_description']) ? htmlspecialchars($s['product_description']) : '',
                    'image' => $img,
                ];
            }
        }
    }
    // Special offers from card_special_offers
    $offers = [];
    $off_query = mysqli_query($connect, "SELECT offer_title, offer_description, offer_image, discount_percentage, badge, start_date, end_date, start_time, end_time FROM card_special_offers WHERE card_id='$card_db_id' AND status='Active' ORDER BY display_order ASC");
    if ($off_query) {
        while ($o = mysqli_fetch_assoc($off_query)) {
            if (!empty($o['offer_title'])) {
                $img = $default_image;
                if (!empty($o['offer_image']) && strpos($o['offer_image'], '.') !== false) {
                    $img = 'assets/upload/websites/special-offers/' . htmlspecialchars($o['offer_image']);
                }
                $se = mw_demo_offer_start_end_dt_labels(
                    $o['start_date'] ?? null,
                    $o['end_date'] ?? null,
                    $o['start_time'] ?? null,
                    $o['end_time'] ?? null
                );
                $expired = mw_demo_offer_end_dt_expired($o['end_date'] ?? null, $o['end_time'] ?? null);
                $offers[] = [
                    'title' => htmlspecialchars($o['offer_title']),
                    'desc' => htmlspecialchars($o['offer_description'] ?? ''),
                    'image' => $img,
                    'badge' => !empty($o['badge']) ? htmlspecialchars($o['badge']) : (!empty($o['discount_percentage']) ? $o['discount_percentage'] . '% OFF' : 'OFFER'),
                    'offer_expired' => (bool) $expired,
                    /** Raw DB values — template recomputes expiry so it always matches End Dt display */
                    'offer_end_date_raw' => $o['end_date'] ?? null,
                    'offer_end_time_raw' => $o['end_time'] ?? null,
                    'offer_start_dt' => $se['start_dt'] !== '' ? htmlspecialchars($se['start_dt']) : '',
                    'offer_end_dt' => $se['end_dt'] !== '' ? htmlspecialchars($se['end_dt']) : '',
                ];
            }
        }
    }
    // Products from card_product_pricing (grouped by category for Blinkit UI)
    // category_name: from product_categories OR user_custom_categories (use category_source to avoid ID collision)
    $col_check = @mysqli_query($connect, "SHOW COLUMNS FROM card_product_pricing LIKE 'category_source'");
    if(!$col_check || mysqli_num_rows($col_check) == 0) {
        @mysqli_query($connect, "ALTER TABLE card_product_pricing ADD category_source VARCHAR(10) DEFAULT 'system' AFTER product_category");
    }
    $products_by_cat = [];
    $cat_display_names = [];
    $prod_query = mysqli_query($connect, "
        SELECT pp.*,
            CASE
                WHEN pp.category_source = 'custom' AND ucc.category_name IS NOT NULL THEN ucc.category_name
                WHEN (pp.category_source = 'system' OR pp.category_source IS NULL OR pp.category_source = '') AND pc.category_name IS NOT NULL THEN pc.category_name
                ELSE COALESCE(ucc.category_name, pc.category_name,
                    CASE WHEN pp.product_category IS NOT NULL AND pp.product_category > 0 THEN CONCAT('Category ', pp.product_category) ELSE NULL END
                )
            END as category_name
        FROM card_product_pricing pp
        LEFT JOIN product_categories pc ON pp.product_category = pc.id
        LEFT JOIN user_custom_categories ucc ON pp.product_category = ucc.id AND ucc.user_id = pp.user_id AND ucc.is_active = 1
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
                $img = !empty($p['product_image'])
                    ? mw_demo_card_product_image_src($p['product_image'], $default_image, $assets_base)
                    : $default_image;
                $mrp = floatval($p['mrp'] ?? 0);
                $price = floatval($p['selling_price'] ?? 0);
                $pricing_type = trim((string)($p['price_type'] ?? ''));
                if (!in_array($pricing_type, ['fixed_price', 'starting_from', 'on_request'], true)) {
                    $pricing_type = ($price > 0) ? 'fixed_price' : 'on_request';
                }
                $pricing_unit = trim((string)($p['pricing_unit'] ?? ''));
                $price_on_request = ($pricing_type === 'on_request' || $price <= 0);
                if ($mrp <= 0) {
                    $mrp = $price;
                }
                $products_by_cat[$cat][] = [
                    'name' => htmlspecialchars($p['product_name']),
                    'cat_key' => $cat,
                    'category' => htmlspecialchars(isset($cat_display_names[$cat]) ? $cat_display_names[$cat] : ''),
                    'image' => $img,
                    'mrp' => $mrp,
                    'price' => $price,
                    'price_type' => $pricing_type,
                    'pricing_unit' => htmlspecialchars($pricing_unit),
                    'cta_text' => htmlspecialchars(trim((string)($p['cta_text'] ?? ''))),
                    'price_on_request' => $price_on_request,
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
                    $gallery[] = 'assets/upload/websites/image-gallery/' . htmlspecialchars($g['gallery_image']);
                } else {
                    $gallery[] = 'data:image/*;base64,' . base64_encode($g['gallery_image']);
                }
            }
        }
    }
    // Videos from d_youtube1..d_youtube20 (YouTube, Shorts, Instagram, Facebook, etc.)
    $videos = [];
    $default_thumb = $default_image;
    $vid_idx = 0;
    for ($i = 1; $i <= 20; $i++) {
        $url = trim($row['d_youtube' . $i] ?? '');
        if (empty($url)) continue;
        $vtype = strtolower(trim((string) ($row['d_video_type' . $i] ?? '')));
        if ($vtype === 'auto') {
            $vtype = '';
        }
        $vthumb = trim((string) ($row['d_video_thumb' . $i] ?? ''));
        $parsed = parseVideoUrl($url, $default_thumb, $vid_idx, $vtype, $vthumb);
        $watch = !empty($parsed['watch_url']) ? $parsed['watch_url'] : mw_normalize_video_watch_url($url);
        $videos[] = [
            'url' => $url,
            'title' => $parsed['title'],
            'thumb' => $parsed['thumb'],
            'platform' => $parsed['platform'],
            'embed_url' => $parsed['embed_url'] ?? '',
            'watch_url' => $watch,
            'play_mode' => $parsed['play_mode'] ?? 'external',
        ];
        $vid_idx++;
    }

    // Business Hours from d_business_hours (JSON; v2 weekly or legacy rows)
    $bh_display_inc = __DIR__ . '/includes/business_hours_display.php';
    if (is_file($bh_display_inc)) {
        require_once $bh_display_inc;
    }
    if (function_exists('mw_normalize_business_hours_display')) {
        $business_hours = mw_normalize_business_hours_display($row['d_business_hours'] ?? '');
    } else {
        $business_hours = [
            ['days' => 'Monday - Thursday', 'hours' => '10:00 AM - 10:00 PM'],
            ['days' => 'Friday - Saturday', 'hours' => '10:00 AM - 12:00 AM'],
            ['days' => 'Sunday', 'hours' => 'Closed'],
        ];
    }
} else {
    // Keep fallback fields blank (no demo data)
    $primary_business_category_title = '';
    $hero_name = '';
    $hero_title = '';
    $hero_cover = '';
    $hero_logo = '';
    $phone = '';
    $whatsapp = '';
    $about = '';
    $email = '';
    $location = '';
    $website = '';
    $google_direction = '';
    $share_url = '';
    $social_links = [];

    $services =[];

    $offers = [];

    // Products from card_product_pricing (dynamic - no hardcoding)
    $products_by_cat = [];
    $cat_display_names = [];
    $db_config = __DIR__ . '/app/config/database.php';
    if (file_exists($db_config)) {
        require_once $db_config;
        $demo_card_query = mysqli_query($connect, "SELECT pp.card_id FROM card_product_pricing pp INNER JOIN digi_card dc ON dc.id = pp.card_id ORDER BY pp.card_id ASC");
        if ($demo_card_query && $dc = mysqli_fetch_assoc($demo_card_query)) {
            $card_db_id = intval($dc['card_id']);
            $col_check = @mysqli_query($connect, "SHOW COLUMNS FROM card_product_pricing LIKE 'category_source'");
            if(!$col_check || mysqli_num_rows($col_check) == 0) {
                @mysqli_query($connect, "ALTER TABLE card_product_pricing ADD category_source VARCHAR(10) DEFAULT 'system' AFTER product_category");
            }
            $prod_query = mysqli_query($connect, "
                SELECT pp.*,
                    CASE
                        WHEN pp.category_source = 'custom' AND ucc.category_name IS NOT NULL THEN ucc.category_name
                        WHEN (pp.category_source = 'system' OR pp.category_source IS NULL OR pp.category_source = '') AND pc.category_name IS NOT NULL THEN pc.category_name
                        ELSE COALESCE(ucc.category_name, pc.category_name,
                            CASE WHEN pp.product_category IS NOT NULL AND pp.product_category > 0 THEN CONCAT('Category ', pp.product_category) ELSE NULL END
                        )
                    END as category_name
                FROM card_product_pricing pp
                LEFT JOIN product_categories pc ON pp.product_category = pc.id
                LEFT JOIN user_custom_categories ucc ON pp.product_category = ucc.id AND ucc.user_id = pp.user_id AND ucc.is_active = 1
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
                        $img = !empty($p['product_image'])
                            ? mw_demo_card_product_image_src($p['product_image'], $default_image, $assets_base)
                            : $default_image;
                        $mrp = floatval($p['mrp'] ?? 0);
                        $price = floatval($p['selling_price'] ?? 0);
                        $pricing_type = trim((string)($p['price_type'] ?? ''));
                        if (!in_array($pricing_type, ['fixed_price', 'starting_from', 'on_request'], true)) {
                            $pricing_type = ($price > 0) ? 'fixed_price' : 'on_request';
                        }
                        $pricing_unit = trim((string)($p['pricing_unit'] ?? ''));
                        $price_on_request = ($pricing_type === 'on_request' || $price <= 0);
                        if ($mrp <= 0) {
                            $mrp = $price;
                        }
                        $products_by_cat[$cat][] = [
                            'name' => htmlspecialchars($p['product_name']),
                            'cat_key' => $cat,
                            'category' => htmlspecialchars(isset($cat_display_names[$cat]) ? $cat_display_names[$cat] : ''),
                            'image' => $img,
                            'mrp' => $mrp,
                            'price' => $price,
                            'price_type' => $pricing_type,
                            'pricing_unit' => htmlspecialchars($pricing_unit),
                            'cta_text' => htmlspecialchars(trim((string)($p['cta_text'] ?? ''))),
                            'price_on_request' => $price_on_request,
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

    $gallery = [];
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
/** Modal/cart JS payload: description max 400 characters */
$products_for_js = [];
if (!empty($products_flat)) {
    foreach ($products_flat as $pj) {
        $d = isset($pj['desc']) ? (string) $pj['desc'] : '';
        $pj['desc'] = function_exists('mb_substr') ? mb_substr($d, 0, 400) : substr($d, 0, 400);
        $products_for_js[] = $pj;
    }
}

/**
 * Resolve business category label from digi_card d_position_primary / d_position_secondary (IDs).
 */
function mw_vcard_resolve_business_category_name($connect, $category_id, $user_id) {
    $category_id = intval($category_id);
    if ($category_id <= 0 || !$connect) {
        return '';
    }
    $id_esc = mysqli_real_escape_string($connect, (string) $category_id);
    $q = @mysqli_query($connect, "SELECT category_name FROM product_categories WHERE id='$id_esc' AND category_type='business-category' AND is_active=1 LIMIT 1");
    if ($q && ($r = mysqli_fetch_assoc($q))) {
        $name = trim((string) ($r['category_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
    }
    // Fallback for older rows where category_type may be empty/missing.
    $q_fallback = @mysqli_query($connect, "SELECT category_name FROM product_categories WHERE id='$id_esc' AND is_active=1 LIMIT 1");
    if ($q_fallback && ($r_fb = mysqli_fetch_assoc($q_fallback))) {
        $name_fb = trim((string) ($r_fb['category_name'] ?? ''));
        if ($name_fb !== '') {
            return $name_fb;
        }
    }
    $uid = intval($user_id);
    if ($uid <= 0) {
        return '';
    }
    $uid_esc = mysqli_real_escape_string($connect, (string) $uid);
    $q2 = @mysqli_query($connect, "SELECT category_name FROM user_custom_categories WHERE id='$id_esc' AND user_id='$uid_esc' AND category_type='business-category' AND is_active=1 LIMIT 1");
    if ($q2 && ($r2 = mysqli_fetch_assoc($q2))) {
        $name2 = trim((string) ($r2['category_name'] ?? ''));
        if ($name2 !== '') {
            return $name2;
        }
    }
    $q2_fallback = @mysqli_query($connect, "SELECT category_name FROM user_custom_categories WHERE id='$id_esc' AND user_id='$uid_esc' AND is_active=1 LIMIT 1");
    if ($q2_fallback && ($r2_fb = mysqli_fetch_assoc($q2_fallback))) {
        return trim((string) ($r2_fb['category_name'] ?? ''));
    }
    return '';
}

// vCard payload: raw UTF-8 strings (no HTML entities) for Save Contact / .vcf
$mw_vcard = [];
$mw_vcard_note_text = 'Trusted local grocery store offering grains, packaged foods and daily essentials. We provide chips, biscuits, Dal, Rice & all other essential items at discounted prices.';
if ($row) {
    $raw_owner = trim(trim($row['d_f_name'] ?? '') . ' ' . trim($row['d_l_name'] ?? ''));
    $raw_org = trim($row['d_comp_name'] ?? '');
    $raw_email = trim($row['d_email'] ?? '');
    $raw_website = trim($row['d_website'] ?? '');
    $tel_cell = preg_replace('/[^0-9+]/', '', (string) ($row['d_contact'] ?? ''));
    $tel_wa = preg_replace('/[^0-9+]/', '', (string) ($row['d_whatsapp'] ?? ''));
    if ($tel_wa === '') {
        $tel_wa = $tel_cell;
    }
    $street = trim((string) ($row['d_address'] ?? ''));
    $ext = trim((string) ($row['d_address2'] ?? ''));
    $adr_street = $street;
    if ($ext !== '' && $street !== '') {
        $adr_street = $street . ', ' . $ext;
    } elseif ($ext !== '') {
        $adr_street = $ext;
    }
    $wa_digits = preg_replace('/\D+/', '', $tel_wa);
    $logo_relative = '';
    if (!empty($row['d_logo_location'])) {
        $logo_path = trim((string) $row['d_logo_location']);
        if (strpos($logo_path, '/') === false) {
            $logo_path = 'assets/upload/websites/company_details/' . $logo_path;
        }
        $logo_relative = ltrim(preg_replace('#^\.\./+#', '', $logo_path), '/');
    } elseif (!empty($row['d_profile_image'])) {
        $logo_relative = ltrim(preg_replace('#^\.\./+#', '', (string) $row['d_profile_image']), '/');
    } elseif (!empty($hero_logo) && strpos((string) $hero_logo, 'data:') !== 0) {
        $logo_relative = ltrim(preg_replace('#^\.\./+#', '', (string) $hero_logo), '/');
    }
    $logo_url = '';
    if ($logo_relative !== '') {
        if (preg_match('#^https?://#i', $logo_relative)) {
            $logo_url = $logo_relative;
        } else {
            $logo_url = rtrim($base_url, '/') . '/' . $logo_relative;
        }
    }
    $raw_map = trim((string) ($row['d_location'] ?? ''));
    if ($raw_map === '') {
        $addr_query_parts = array_filter([
            $adr_street,
            trim((string) ($row['d_city'] ?? '')),
            trim((string) ($row['d_state'] ?? '')),
            trim((string) ($row['d_pincode'] ?? '')),
            trim((string) ($row['d_country'] ?? '')),
        ], static function ($p) {
            return $p !== null && $p !== '';
        });
        if (!empty($addr_query_parts)) {
            $raw_map = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(implode(', ', $addr_query_parts));
        }
    }
    if ($raw_map !== '' && !preg_match('#^https?://#i', $raw_map)) {
        $raw_map = 'https://' . $raw_map;
    }
    $social = [
        'facebook' => trim((string) ($row['d_fb'] ?? '')),
        'instagram' => trim((string) ($row['d_instagram'] ?? '')),
        'linkedin' => trim((string) ($row['d_linkedin'] ?? '')),
        'twitter' => trim((string) ($row['d_twitter'] ?? '')),
        'youtube' => trim((string) ($row['d_youtube'] ?? '')),
        'pinterest' => trim((string) ($row['d_pinterest'] ?? '')),
    ];
    $mw_card_user_id = 0;
    if (!empty($row['user_id'])) {
        $mw_card_user_id = intval($row['user_id']);
    } elseif (!empty($row['user_email']) && isset($connect) && $connect) {
        $em_lookup = mysqli_real_escape_string($connect, strtolower(trim((string) $row['user_email'])));
        $uq = @mysqli_query($connect, "SELECT id FROM user_details WHERE LOWER(TRIM(email)) = '$em_lookup' LIMIT 1");
        if ($uq && ($ur = mysqli_fetch_assoc($uq))) {
            $mw_card_user_id = intval($ur['id'] ?? 0);
        }
    }
    $bc_parts = [];
    $bc_pri = mw_vcard_resolve_business_category_name($connect, $row['d_position_primary'] ?? 0, $mw_card_user_id);
    if ($bc_pri !== '') {
        $bc_parts[] = $bc_pri;
    }
    $bc_sec = mw_vcard_resolve_business_category_name($connect, $row['d_position_secondary'] ?? 0, $mw_card_user_id);
    if ($bc_sec !== '' && $bc_sec !== $bc_pri) {
        $bc_parts[] = $bc_sec;
    }
    $business_category = implode(', ', $bc_parts);
    $mw_vcard = [
        'fn' => $raw_owner !== '' ? $raw_owner : ($raw_org !== '' ? $raw_org : 'Your Name'),
        'org' => $raw_org,
        'title' => $bc_pri,
        'businessCategory' => $business_category,
        'telCell' => $tel_cell,
        'telWhatsapp' => $tel_wa,
        'email' => $raw_email !== '' ? $raw_email : 'contact@example.com',
        'urlProfile' => $share_url,
        'urlWebsite' => $raw_website,
        'mapUrl' => $raw_map,
        'adr' => [
            'street' => $adr_street,
            'locality' => trim((string) ($row['d_city'] ?? '')),
            'region' => trim((string) ($row['d_state'] ?? '')),
            'postal' => trim((string) ($row['d_pincode'] ?? '')),
            'country' => trim((string) ($row['d_country'] ?? '')),
        ],
        'note' => $mw_vcard_note_text,
        'waMe' => $wa_digits !== '' ? ('https://wa.me/' . $wa_digits) : '',
        'logoUrl' => $logo_url,
        'social' => $social,
    ];
    $parts = explode(' ', $raw_owner, 2);
    $mw_vcard['nFamily'] = count($parts) > 1 ? $parts[1] : '';
    $mw_vcard['nGiven'] = $parts[0] ?? '';
} else {
    $wa_digits = preg_replace('/\D+/', '', $whatsapp);
    $mw_vcard = [
        'fn' => 'Olivia Murray',
        'org' => 'Olivia Culinary',
        'title' => '',
        'businessCategory' => '',
        'telCell' => preg_replace('/[^0-9+]/', '', $phone),
        'telWhatsapp' => preg_replace('/[^0-9+]/', '', $whatsapp),
        'email' => $email,
        'urlProfile' => $share_url,
        'urlWebsite' => $website,
        'mapUrl' => '',
        'adr' => [
            'street' => 'Sample Street 1',
            'locality' => 'Berlin',
            'region' => 'Berlin',
            'postal' => '10115',
            'country' => 'Germany',
        ],
        'note' => $mw_vcard_note_text,
        'waMe' => $wa_digits !== '' ? ('https://wa.me/' . $wa_digits) : '',
        'logoUrl' => '',
        'social' => [],
        'nFamily' => 'Murray',
        'nGiven' => 'Olivia',
    ];
}
// Resolve active miniwebsite CSS files from selected dashboard theme (d_css).
// Theme N is stored as card_css(N+1) for DB/admin compatibility.
$selected_d_css = !empty($row['d_css']) ? trim((string) $row['d_css']) : '';
$selected_theme_number = 1;

if (preg_match('/card_css(\d+)\.css$/', $selected_d_css, $m)) {
    $saved_card_css_no = intval($m[1]);
    if ($saved_card_css_no > 1) {
        $selected_theme_number = $saved_card_css_no - 1;
    }
}

$theme_css_file = 'theme/css/theme' . $selected_theme_number . '.css';
$layout_css_file = 'theme/css/layout' . $selected_theme_number . '.css';

if (!file_exists(__DIR__ . '/' . $theme_css_file) || !file_exists(__DIR__ . '/' . $layout_css_file)) {
    // Safe fallback for missing/invalid theme files.
    if (file_exists(__DIR__ . '/theme/css/theme1.css') && file_exists(__DIR__ . '/theme/css/layout1.css')) {
        $theme_css_file = 'theme/css/theme1.css';
        $layout_css_file = 'theme/css/layout1.css';
    } else {
        $theme_css_file = 'theme/css/theme.css';
        $layout_css_file = 'theme/css/layout.css';
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
    <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Inter:wght@300;400;500;600;700&family=Roboto+Condensed:wght@400;700&display=swap" rel="stylesheet">

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
    <link rel="stylesheet" href="<?php echo htmlspecialchars($theme_css_file, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($layout_css_file, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="theme/css/components.css">
</head>
<body>

<div class="app-container">

    <!-- 1. Hero Section -->
    <section id="mw-hero" class="mw-hero relative">
        <div class="h-64 md:h-80 w-full overflow-hidden relative">
            <img src="<?php echo htmlspecialchars($hero_cover); ?>" alt="Cover" class="w-full h-full object-cover opacity-60" onerror="this.onerror=null;this.src='<?php echo htmlspecialchars($default_image, ENT_QUOTES); ?>'">
            <div class="absolute inset-0 bg-gradient-to-t from-bgbase to-transparent"></div>
        </div>

        <div class="mw-section-padding relative -mt-24 text-center z-10 flex flex-col items-center">
            <div class="w-32 h-32 rounded-full border-4 border-bgbase shadow-card overflow-hidden mb-4">
                <img src="<?php echo htmlspecialchars($hero_logo); ?>" alt="Logo" class="w-full h-full object-cover" onerror="this.onerror=null;this.src='<?php echo htmlspecialchars($default_image, ENT_QUOTES); ?>'">
            </div>
            <h1 class="text-3xl md:text-4xl font-bold mb-1"><?php echo $hero_name; ?></h1>
            <?php if ($primary_business_category_title !== ''): ?>
            <p class="text-heading font-semibold text-base md:text-lg mb-3 mt-0"><?php echo $primary_business_category_title; ?></p>
            <?php endif; ?>

            <div class="flex gap-4 w-full max-w-sm justify-center">
                <a href="tel:<?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?>" class="flex-1 bg-cardbg border border-primary text-primary py-3 px-4 rounded-theme hover:bg-primary hover:text-bgbase transition-colors flex items-center justify-center gap-2 font-medium">
                    <i class="fas fa-phone-alt"></i> Call Now
                </a>
                <a href="https://wa.me/<?php echo $whatsapp; ?>" class="flex-1 bg-cardbg border border-primary text-primary py-3 px-4 rounded-theme hover:bg-primary hover:text-bgbase transition-colors flex items-center justify-center gap-2 font-medium">
                    <i class="fab fa-whatsapp"></i> WhatsApp
                </a>
            </div>
        </div>
    </section>

    <!-- 2. Social Links -->
    <?php if (!empty($social_links)): ?>
    <section class="mw-social-links pb-6 flex justify-center gap-5">
        <?php foreach ($social_links as $s): ?>
        <a href="<?php echo htmlspecialchars($s['url']); ?>" target="_blank" rel="noopener" class="w-10 h-10 rounded-full bg-cardbg flex items-center justify-center text-primary hover:bg-primary hover:text-bgbase transition"><i class="fab fa-<?php echo $s['icon']; ?>"></i></a>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <!-- 3. Business Intro -->
    <section class="mw-business-intro mw-section-padding text-center">
        <p class="text-sm md:text-base leading-relaxed max-w-3xl mx-auto">
            <?php echo nl2br($about); ?>
        </p>
    </section>

    <!-- 4. Quick Action Grid -->
    <?php if (!empty($email) || !empty($phone) || !empty($location) || !empty($google_direction)): ?>
    <section class="mw-action-grid mw-section-padding">
        <h2 class="mw-section-title">Contact</h2>
        <div class="grid grid-cols-2 gap-4 max-w-4xl mx-auto">
            <?php if (!empty($email)): ?>
            <div class="mw-card p-4 flex flex-col gap-2 group cursor-default">
                <span class="mw-contact-icon inline-flex w-10 h-10 items-center justify-center rounded-theme text-primary transition-all duration-300 group-hover:bg-primary group-hover:text-bgbase">
                    <i class="fas fa-envelope text-xl"></i>
                </span>
                <h3 class="text-xs uppercase tracking-wider text-textmain mt-2">Email Address</h3>
                <p class="text-heading font-medium text-sm truncate"><?php echo $email; ?></p>
            </div>
            <?php endif; ?>
            <?php if (!empty($phone)): ?>
            <div class="mw-card p-4 flex flex-col gap-2 group cursor-default">
                <span class="mw-contact-icon inline-flex w-10 h-10 items-center justify-center rounded-theme text-primary transition-all duration-300 group-hover:bg-primary group-hover:text-bgbase">
                    <i class="fas fa-phone-alt text-xl"></i>
                </span>
                <h3 class="text-xs uppercase tracking-wider text-textmain mt-2">Phone Number</h3>
                <p class="text-heading font-medium text-sm"><?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <?php endif; ?>
            <?php if (!empty($location)): ?>
            <div class="mw-card p-4 flex flex-col gap-2 group cursor-default">
                <span class="mw-contact-icon inline-flex w-10 h-10 items-center justify-center rounded-theme text-primary transition-all duration-300 group-hover:bg-primary group-hover:text-bgbase">
                    <i class="fas fa-map-marker-alt text-xl"></i>
                </span>
                <h3 class="text-xs uppercase tracking-wider text-textmain mt-2">Location</h3>
                <p class="text-heading font-medium text-sm"><?php echo $location; ?></p>
            </div>
            <?php endif; ?>
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
    <?php endif; ?>

    <!-- 5. QR Share Section -->
    <?php
    $qr_image_url = 'https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=' . urlencode($share_url ?? '');
    // Match visible hero fallbacks: live DB rows often omit d_comp_name / names but still have card_id or title.
    $qr_business_name = '';
    $qr_person_name = '';
    if ($row) {
        $comp = trim((string) ($row['d_comp_name'] ?? ''));
        $fn = trim((string) ($row['d_f_name'] ?? ''));
        $ln = trim((string) ($row['d_l_name'] ?? ''));
        $firstlast = trim($fn . ' ' . $ln);
        $pos = trim((string) ($row['d_position'] ?? ''));
        $slug = trim((string) ($row['card_id'] ?? $card_id_slug));
        if ($comp !== '') {
            $qr_business_name = htmlspecialchars($comp);
            $qr_person_name = $firstlast !== '' ? htmlspecialchars($firstlast) : ($pos !== '' ? htmlspecialchars($pos) : '');
        } elseif ($firstlast !== '') {
            $qr_business_name = htmlspecialchars($firstlast);
            $qr_person_name = $pos !== '' ? htmlspecialchars($pos) : '';
        } elseif ($slug !== '') {
            $qr_business_name = htmlspecialchars($slug);
            $qr_person_name = $pos !== '' ? htmlspecialchars($pos) : '';
        } elseif (isset($hero_name) && $hero_name !== '' && $hero_name !== 'Your Name') {
            $qr_business_name = $hero_name;
            $qr_person_name = $pos !== '' ? htmlspecialchars($pos) : '';
        }
        if ($qr_person_name === '' && $pos !== '' && $qr_business_name !== '') {
            $qr_person_name = htmlspecialchars($pos);
        }
    } else {
        $qr_person_name = $hero_name ?? '';
    }
    $qr_card_id = isset($row) && $row ? ($row['card_id'] ?? 'card') : 'card';
    ?>
    <section class="mw-qr-share mw-section-padding bg-cardbg/50">
        <h2 class="mw-section-title">Share Profile</h2>
        <div class="max-w-md mx-auto mw-card p-6 text-center">
            <div class="bg-white p-2 w-40 h-40 mx-auto rounded-lg mb-6">
                <img id="mw-qr-code-img" src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($share_url ?? ''); ?>" alt="QR Code" class="w-full h-full" onerror="this.onerror=null;this.src='<?php echo htmlspecialchars($default_image, ENT_QUOTES); ?>'">
            </div>
            <canvas id="mw-qr-canvas" style="display: none;"></canvas>
            <div class="space-y-4">
                <input type="tel" id="mw-share-wa-input" placeholder="Enter WhatsApp Number" class="mw-input" maxlength="15">
                <div class="flex gap-3 items-center">
                    <button type="button" id="mw-share-wa-btn" style="background: var(--mw-offer-cta-bg); color: #111;" class="mw-share-btn flex-1 bg-cardbg border border-primary text-primary py-3 px-4 rounded-theme hover:bg-primary hover:text-bgbase active:scale-[0.98] transition-all duration-300 flex items-center justify-center gap-2 font-medium cursor-pointer">
                        <i class="fab fa-whatsapp"></i> Share on WhatsApp
                    </button>
                    <button type="button" id="mw-download-qr-btn" class="flex-shrink-0 w-12 h-12 rounded-theme flex items-center justify-center cursor-pointer" style="background: var(--mw-offer-cta-bg); color: #111;" title="Download QR">
                    <i class="fa-solid fa-download"></i>
                    </button>
                </div>
               
                <div class="flex gap-4">
                    <button type="button" id="mw-save-contact-btn" class="mw-share-btn flex-1 bg-cardbg border border-primary text-primary py-3 px-4 rounded-theme hover:bg-primary hover:text-bgbase active:scale-[0.98] transition-all duration-300 flex items-center justify-center gap-2 font-medium cursor-pointer">
                     Save Contact
                    </button>
                    <button type="button" id="mw-share-link-btn" class="mw-share-btn flex-1 bg-cardbg border border-primary text-primary py-3 px-4 rounded-theme hover:bg-primary hover:text-bgbase active:scale-[0.98] transition-all duration-300 flex items-center justify-center gap-2 font-medium cursor-pointer">
                        Share Link
                    </button>
                </div>
            </div>
        </div>
    </section>

    <script>
    (function() {
        const canvas = document.getElementById('mw-qr-canvas');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        const backgroundImageUrl = 'assets/images/Miniwebsite_QR.png';
        const qrImageUrl = <?php echo json_encode($qr_image_url); ?>;
        const businessName = <?php echo json_encode($qr_business_name); ?>;
        const personName = <?php echo json_encode($qr_person_name); ?>;
        const websiteUrl = 'www.miniwebsite.in';
        const downloadFilename = 'QR_Code_<?php echo htmlspecialchars($qr_card_id); ?>.png';

        let imagesLoaded = 0;
        const totalImages = 2;
        let bgImage, qrImage;
        let canvasReady = false;

        function drawCanvas() {
            if (imagesLoaded < totalImages) return;
            canvas.width = bgImage.width;
            canvas.height = bgImage.height;
            ctx.drawImage(bgImage, 0, 0);
            const padding = 18;
            const qrSize = Math.min(canvas.width * 0.555, canvas.height * 0.555);
            const qrX = (canvas.width - qrSize) / 2 + 8;
            const qrY = (canvas.height - qrSize) / 2 - 70;
            ctx.fillStyle = '#FFFFFF';
            const borderRadius = 12;
            const bgX = qrX - padding;
            const bgY = qrY - padding;
            const bgWidth = qrSize + (padding * 2);
            const bgHeight = qrSize + (padding * 2);
            ctx.beginPath();
            ctx.moveTo(bgX + borderRadius, bgY);
            ctx.lineTo(bgX + bgWidth - borderRadius, bgY);
            ctx.quadraticCurveTo(bgX + bgWidth, bgY, bgX + bgWidth, bgY + borderRadius);
            ctx.lineTo(bgX + bgWidth, bgY + bgHeight - borderRadius);
            ctx.quadraticCurveTo(bgX + bgWidth, bgY + bgHeight, bgX + bgWidth - borderRadius, bgY + bgHeight);
            ctx.lineTo(bgX + borderRadius, bgY + bgHeight);
            ctx.quadraticCurveTo(bgX, bgY + bgHeight, bgX, bgY + bgHeight - borderRadius);
            ctx.lineTo(bgX, bgY + borderRadius);
            ctx.quadraticCurveTo(bgX, bgY, bgX + borderRadius, bgY);
            ctx.closePath();
            ctx.fill();
            ctx.drawImage(qrImage, qrX, qrY, qrSize, qrSize);
            if (businessName) {
                ctx.fillStyle = '#FFFFFF';
                ctx.font = 'bold ' + Math.floor(canvas.width * 0.08) + 'px "Roboto Condensed"';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(businessName.toUpperCase(), canvas.width / 2, canvas.height * 0.12);
            }
            if (personName) {
                ctx.fillStyle = '#202023';
                ctx.font = 'bold ' + Math.floor(canvas.width * 0.04) + 'px "Roboto Condensed"';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'top';
                ctx.fillText(personName.toUpperCase(), canvas.width / 2, qrY + qrSize + 60);
            }
            ctx.fillStyle = '#202023';
            ctx.font = 'bold ' + Math.floor(canvas.width * 0.070) + 'px "Roboto Condensed"';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'top';
            const scanTextY = personName ? qrY + qrSize + 180 : qrY + qrSize + 160;
            ctx.fillText('SCAN TO VIEW AND SAVE!', canvas.width / 2, scanTextY);
            ctx.fillStyle = '#202023';
            ctx.font = Math.floor(canvas.width * 0.03) + 'px "Roboto Condensed"';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'top';
            ctx.fillText('Access our Miniwebsite & Contact Info', canvas.width / 2, scanTextY + 120);
            ctx.fillStyle = '#FFFFFF';
            ctx.font = Math.floor(canvas.width * 0.04) + 'px "Roboto Condensed"';
            ctx.textAlign = 'right';
            ctx.textBaseline = 'bottom';
            ctx.fillText(websiteUrl, canvas.width - (canvas.width * 0.05), canvas.height - (canvas.height * 0.015));
            canvasReady = true;
        }

        function initCanvas() {
            bgImage = new Image();
            bgImage.crossOrigin = 'anonymous';
            qrImage = new Image();
            qrImage.crossOrigin = 'anonymous';
            bgImage.onload = function() { imagesLoaded++; drawCanvas(); };
            qrImage.onload = function() { imagesLoaded++; drawCanvas(); };
            bgImage.onerror = function() { console.error('Failed to load QR background'); };
            qrImage.onerror = function() { console.error('Failed to load QR image'); };
            bgImage.src = backgroundImageUrl;
            qrImage.src = qrImageUrl;
        }

        if (document.fonts && document.fonts.load) {
            document.fonts.load('bold 16px "Roboto Condensed"').then(initCanvas).catch(initCanvas);
        } else {
            setTimeout(initCanvas, 500);
        }

        function mwQrToast(msg) {
            const t = document.createElement('div');
            t.className = 'fixed bottom-24 left-1/2 -translate-x-1/2 bg-heading text-bgbase px-4 py-2 rounded-theme text-sm font-medium shadow-lg z-[60] transition-opacity duration-300';
            t.textContent = msg;
            document.body.appendChild(t);
            setTimeout(function() { t.style.opacity = '0'; setTimeout(function() { t.remove(); }, 300); }, 2000);
        }
        const btn = document.getElementById('mw-download-qr-btn');
        if (btn) {
            btn.addEventListener('click', function() {
                if (!canvasReady) {
                    mwQrToast('Please wait, QR code is still being prepared...');
                    return;
                }
                const link = document.createElement('a');
                link.download = downloadFilename;
                link.href = canvas.toDataURL('image/png');
                link.click();
                mwQrToast('QR downloaded!');
            });
        }
    })();
    </script>

    <!-- 6. Services Section -->
    <?php if (!empty($services)): ?>
    <section id="mw-services" class="mw-services mw-section-padding bg-cardbg/30">
        <h2 class="mw-section-title">Our Services</h2>

        <!-- Services Grid (visible by default) -->
        <div id="mw-services-grid" class="mw-grid-services">
            <?php foreach ($services as $svc): ?>
            <div class="mw-card mw-offer-card mw-service-card bg-cardbg rounded-theme overflow-hidden relative">
                <div class="mw-service-image-wrap">
                    <img src="<?php echo htmlspecialchars($svc['image']); ?>" alt="<?php echo htmlspecialchars($svc['name']); ?>" onerror="this.onerror=null;this.src='<?php echo htmlspecialchars($default_image, ENT_QUOTES); ?>'">
                </div>
                <div class="p-5">
                    <h3 class="text-heading font-semibold text-lg mb-1"><?php echo $svc['name']; ?></h3>
                    <p class="mw-service-desc-preview text-sm text-textmain line-clamp-3"><?php echo !empty($svc['desc']) ? htmlspecialchars($svc['desc']) : 'Contact us for details.'; ?></p>
                    <div class="mw-service-desc-full hidden text-sm text-textmain mt-2 leading-relaxed"><?php echo !empty($svc['desc']) ? nl2br(htmlspecialchars($svc['desc'])) : 'Contact us for details.'; ?></div>
                    <button type="button" class="mw-service-read-more self-start text-left text-primary text-sm font-medium mt-2 hover:underline">Read more</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>


    <!-- 7. Special Offers Section -->
    <?php if (!empty($offers)): ?>
    <section id="mw-offers" class="mw-special-offers mw-section-padding bg-cardbg/30">
        <h2 class="mw-section-title">Special Offers</h2>
        <div class="mw-grid-offers">
            <?php foreach ($offers as $off):
                $mw_offer_has_end_dt = !empty($off['offer_end_dt'] ?? '');
                /** Recompute from raw end_date/end_time so label matches DB (no stale/cached flag). */
                $mw_offer_expired_from_end_dt = $mw_offer_has_end_dt && mw_demo_offer_end_dt_expired($off['offer_end_date_raw'] ?? null, $off['offer_end_time_raw'] ?? null);
            ?>
            <div class="mw-card mw-offer-card bg-cardbg rounded-theme relative overflow-hidden">
                <div class="mw-offer-badge absolute top-3 left-3 px-3 py-1 rounded-full text-xs font-bold z-10" style="background: var(--mw-offer-badge-bg); color: var(--mw-offer-badge-color);"><?php echo htmlspecialchars($off['badge']); ?></div>
                <?php if ($mw_offer_expired_from_end_dt): ?>
                <div class="mw-offer-expired-badge absolute top-3 right-3 px-3 py-1 rounded-full text-xs font-bold z-10 shadow-sm" style="background:#dc2626;color:#fff;">Offer Expired</div>
                <?php endif; ?>
                <div class="mw-offer-image-wrap aspect-[4/3] overflow-hidden">
                <img src="<?php echo htmlspecialchars($off['image']); ?>" alt="<?php echo htmlspecialchars($off['title']); ?>" class="w-full h-full object-cover" onerror="this.onerror=null;this.src='<?php echo htmlspecialchars($default_image, ENT_QUOTES); ?>'">
                </div>
                <div class="p-5 flex flex-col min-h-0">
                    <h3 class="text-heading font-semibold text-lg mb-1"><?php echo $off['title']; ?></h3>
                    <p class="mw-offer-desc-preview text-sm text-textmain line-clamp-1"><?php echo !empty($off['desc']) ? htmlspecialchars($off['desc']) : 'Contact us for details.'; ?></p>
                    <div class="mw-offer-desc-full hidden text-sm text-textmain mt-2 leading-relaxed"><?php echo !empty($off['desc']) ? nl2br(htmlspecialchars($off['desc'])) : 'Contact us for details.'; ?></div>
                    <button type="button" class="mw-offer-read-more self-start text-left text-primary text-sm font-medium mt-2 hover:underline">Read more</button>
                    <?php if (!empty($off['offer_start_dt'] ?? '') || !empty($off['offer_end_dt'] ?? '')): ?>
                    <div class="mw-offer-valid-row flex flex-row justify-between items-baseline gap-3 w-full text-xs text-textmain pt-3 mt-3 border-t border-white/10">
                        <span class="min-w-0 text-left leading-snug"><?php if (!empty($off['offer_start_dt'] ?? '')): ?><span class="font-medium text-heading">Start Dt:</span> <?php echo $off['offer_start_dt']; ?><?php endif; ?></span>
                        <span class="min-w-0 text-right leading-snug shrink-0"><?php if ($mw_offer_has_end_dt): ?><span class="font-medium text-heading">End Dt:</span> <?php echo $off['offer_end_dt']; ?><?php endif; ?></span>
                    </div>
                    <?php endif; ?>
                    <?php
                        $offer_wa_msg = "Hi 😊\nI am interested in the offer mentioned in your MiniWebsite \"" . $off['title'] . "\".\nPlease share the price & availability of this.";
                        ?>
                    <button type="button" class="mw-offer-wa-cta block w-full py-2.5 rounded-theme font-semibold transition text-center mt-3 cursor-pointer border-0" style="background: var(--mw-offer-cta-bg); color: #111;" data-phone="<?php echo htmlspecialchars((string) $whatsapp, ENT_QUOTES, 'UTF-8'); ?>" data-msg="<?php echo htmlspecialchars($offer_wa_msg, ENT_QUOTES, 'UTF-8'); ?>">Get This Offer</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- 8. Products Section (Blinkit Style) -->
    <?php if (!empty($products_by_cat)): ?>
    <section id="mw-products" class="mw-products mw-section-padding">
        <h2 class="mw-section-title">Details & Pricing</h2>
        
        <div id="mw-products-blinkit" class="mw-blinkit-container">
            <!-- Sidebar Categories -->
            <div class="mw-blinkit-sidebar" id="categorySidebar">
                <?php foreach ($cat_order as $idx => $cat_key): if (!isset($products_by_cat[$cat_key]) || empty($products_by_cat[$cat_key])) continue; ?>
                <div class="mw-cat-item <?php echo $idx === 0 ? 'active' : ''; ?>" data-cat="<?php echo htmlspecialchars($cat_key); ?>">
                    <div class="mw-cat-img-box"><img src="<?php echo htmlspecialchars($cat_icons[$cat_key] ?? $cat_icons['mains']); ?>" class="w-full h-full object-contain" alt="" onerror="this.onerror=null;this.src='<?php echo htmlspecialchars($default_image, ENT_QUOTES); ?>'"></div>
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
                    <div class="mw-card mw-product-card bg-white text-gray-800 overflow-hidden rounded-xl shadow-md p-1 cursor-pointer" data-product-index="<?php echo $global_idx; ?>">
                        <?php $mw_card_cta_text = !empty($prod['cta_text']) ? trim((string)$prod['cta_text']) : 'Enquire'; ?>
                        <div class="mw-product-image-wrap aspect-[4/3] relative rounded-t-xl">
                            <img src="<?php echo htmlspecialchars($prod['image']); ?>" class="w-full h-full object-cover pointer-events-none select-none" alt="<?php echo htmlspecialchars($prod['name']); ?>" onerror="this.onerror=null;this.src='<?php echo htmlspecialchars($default_image, ENT_QUOTES); ?>'">
                            <button type="button" class="mw-btn-add-shop mw-add-to-cart absolute z-10 pointer-events-auto" data-product-index="<?php echo $global_idx; ?>" aria-label="Add <?php echo htmlspecialchars($prod['name']); ?> to cart"><span class="mw-cart-btn-label"><?php echo htmlspecialchars($mw_card_cta_text); ?></span></button>
                        </div>
                        <div class="p-1">
                            <h3 class="font-medium text-sm leading-tight mb-1 text-gray-900"><?php echo htmlspecialchars($prod['name']); ?></h3>
                            <p class="mw-product-desc-preview text-xs text-gray-500 line-clamp-1"><?php echo !empty($prod['desc']) ? htmlspecialchars($prod['desc']) : 'Contact us for details.'; ?></p>
                            <div class="mw-product-desc-full hidden text-xs text-gray-500 mt-2 leading-relaxed"><?php echo !empty($prod['desc']) ? nl2br(htmlspecialchars($prod['desc'])) : 'Contact us for details.'; ?></div>
                            <button type="button" class="mw-product-read-more text-primary text-xs font-medium mt-1 hover:underline">Read more</button>
                            <?php
                            $mw_price_type = !empty($prod['price_type']) ? $prod['price_type'] : 'fixed_price';
                            $mw_pricing_unit = !empty($prod['pricing_unit']) ? trim((string)$prod['pricing_unit']) : '';
                            $mw_price_on_request = !empty($prod['price_on_request']) || $mw_price_type === 'on_request';
                            $mw_has_prod_discount = !$mw_price_on_request && $mw_price_type === 'fixed_price' && isset($prod['mrp']) && $prod['mrp'] > $prod['price'];
                            $mw_price_base = '₹' . number_format($prod['price']);
                            $mw_price_with_unit = $mw_pricing_unit !== '' ? ($mw_price_base . ' / ' . htmlspecialchars($mw_pricing_unit)) : $mw_price_base;
                            if ($mw_price_on_request) {
                                $mw_price_display = 'Price on Request';
                            } elseif ($mw_price_type === 'starting_from') {
                                $mw_price_display = 'Starting ' . $mw_price_with_unit;
                            } else {
                                $mw_price_display = $mw_price_with_unit;
                            }
                            ?>
                            <div class="mw-product-card-prices flex flex-col items-start gap-0.5 mt-2 md:flex-row md:items-center md:gap-2 <?php echo $mw_has_prod_discount ? 'md:justify-between' : ''; ?>">
                                <?php if ($mw_has_prod_discount): ?>
                                <span class="text-xs text-gray-400 line-through font-bold">₹<?php echo number_format($prod['mrp']); ?></span>
                                <?php endif; ?>
                                <span class="mw-product-card-sale font-bold text-[13px] text-gray-900 md:text-sm <?php echo $mw_has_prod_discount ? '' : 'md:ml-auto'; ?>"><?php echo $mw_price_display; ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>

            </div>
        </div>

        <!-- Product detail: centered on screen; width synced to .mw-blinkit-main (grid column) via JS -->
        <div id="mw-product-expanded-box" class="mw-product-expanded-box" aria-hidden="true">
            <button type="button" class="mw-product-expanded-backdrop" aria-label="Close product details"></button>
            <div class="mw-product-expanded-panel mw-card mw-offer-card relative overflow-hidden bg-white text-gray-800 flex flex-col shadow-xl" role="dialog" aria-modal="true" aria-labelledby="mw-product-expanded-title">
                <button type="button" class="mw-product-expanded-close absolute top-3 right-3 z-20 w-10 h-10 rounded-full bg-gray-200 hover:bg-gray-300 text-gray-800 flex items-center justify-center transition shadow-lg" aria-label="Close"><i class="fas fa-times"></i></button>
                <div class="mw-product-expanded-body">
                    <div class="mw-product-expanded-col mw-product-expanded-media">
                        <div class="mw-product-expanded-image-wrap relative">
                            <img id="mw-product-expanded-img" src="" alt="" class="mw-product-expanded-img" onerror="this.onerror=null;this.src='<?php echo htmlspecialchars($default_image, ENT_QUOTES); ?>'">
                            <button type="button" class="mw-product-expanded-prev absolute left-2 top-1/2 -translate-y-1/2 w-9 h-9 md:w-10 md:h-10 rounded-full bg-white/95 hover:bg-white text-gray-800 flex items-center justify-center shadow-lg transition" aria-label="Previous product"><i class="fas fa-chevron-left"></i></button>
                            <button type="button" class="mw-product-expanded-next absolute right-2 top-1/2 -translate-y-1/2 w-9 h-9 md:w-10 md:h-10 rounded-full bg-white/95 hover:bg-white text-gray-800 flex items-center justify-center shadow-lg transition" aria-label="Next product"><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>
                    <div class="mw-product-expanded-col mw-product-expanded-detail">
                        <div class="mw-product-expanded-detail-inner p-4 md:p-6 flex flex-col min-h-0">
                            <div class="mw-product-expanded-text-block flex-1 min-h-0">
                                <span id="mw-product-expanded-badge" class="mw-product-expanded-badge hidden"></span>
                                <h3 id="mw-product-expanded-title" class="text-gray-900 font-semibold text-lg md:text-xl mb-2 md:mb-3"></h3>
                                <p id="mw-product-expanded-desc" class="mw-product-expanded-desc text-sm text-gray-500 leading-relaxed whitespace-pre-line"></p>
                                <div class="mw-product-expanded-pricing mt-3 md:mt-4" aria-label="Pricing">
                                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-2 justify-between">
                                        <div class="flex flex-wrap items-baseline gap-2">
                                            <span id="mw-product-expanded-mrp" class="mw-product-expanded-mrp text-sm text-gray-400 line-through font-bold"></span>
                                            <span id="mw-product-expanded-price" class="mw-product-expanded-sale text-xl md:text-2xl font-bold text-green-600"></span>
                                        </div>
                                        <span id="mw-product-expanded-savings" class="mw-product-expanded-savings hidden"></span>
                                    </div>
                                   </div>
                            </div>
                            <button type="button" class="mw-product-expanded-add-btn mw-btn-add-shop mw-add-to-cart mt-4 flex items-center justify-center gap-2 rounded-xl font-bold uppercase tracking-wide border-0 cursor-pointer flex-shrink-0 self-end" style="background: var(--mw-offer-cta-bg); color: #111;" id="mw-product-expanded-add-main" data-product-index="0" aria-label="Add to cart"><span class="mw-cart-btn-label">Enquire</span></button>
                        </div>
                    </div>
                </div>
                <div class="mw-product-expanded-footer flex justify-center gap-2 py-2.5 md:py-3 text-sm text-gray-500 border-t border-gray-200 flex-shrink-0">
                    <span id="mw-product-expanded-counter">1</span> / <span id="mw-product-expanded-counter-total"><?php echo count($products_flat); ?></span>
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
            <?php foreach ($videos as $idx => $v):
                $is_iframe = !empty($v['play_mode']) && $v['play_mode'] === 'iframe' && !empty($v['embed_url']);
                $wrap_class = 'mw-video-item mw-card aspect-video relative group cursor-pointer overflow-hidden block ' . ($idx >= 6 ? 'mw-video-hidden' : '');
                ?>
            <?php if ($is_iframe): ?>
            <div class="<?php echo $wrap_class; ?>" data-play-mode="iframe" data-video-url="<?php echo htmlspecialchars($v['embed_url']); ?>" data-video-fallback="<?php echo htmlspecialchars($v['url']); ?>" role="button" tabindex="0">
                <img src="<?php echo htmlspecialchars($v['thumb']); ?>" class="w-full h-full object-cover opacity-60 group-hover:opacity-80 transition" alt="<?php echo htmlspecialchars($v['title']); ?>" onerror="this.onerror=null;this.src='<?php echo htmlspecialchars($default_image, ENT_QUOTES); ?>'">
                <div class="absolute inset-0 flex items-center justify-center"><div class="w-12 h-12 bg-primary/90 text-bgbase rounded-full flex items-center justify-center text-xl group-hover:scale-110 transition shadow-lg"><i class="fas fa-play ml-1"></i></div></div>
                <div class="absolute bottom-2 left-3 right-3 text-heading text-sm font-medium drop-shadow-md truncate"><?php echo htmlspecialchars($v['title']); ?></div>
            </div>
            <?php else:
                $ext_href = !empty($v['watch_url']) ? $v['watch_url'] : $v['url'];
                $plat = $v['platform'] ?? 'other';
                $is_fb_ig = ($plat === 'facebook' || $plat === 'instagram');
                $ext_theme_class = $is_fb_ig ? (' mw-video-external--' . $plat) : '';
                if ($plat === 'facebook') {
                    $ext_center_icon = 'fab fa-facebook-f';
                } elseif ($plat === 'instagram') {
                    $ext_center_icon = 'fab fa-instagram';
                } else {
                    $ext_center_icon = 'fas fa-external-link-alt ml-0.5';
                }
                $img_opacity = $is_fb_ig ? 'opacity-80 group-hover:opacity-95' : 'opacity-60 group-hover:opacity-80';
                ?>
            <a href="<?php echo htmlspecialchars($ext_href); ?>" target="_blank" rel="noopener noreferrer" class="<?php echo $wrap_class; ?> mw-video-external<?php echo $ext_theme_class; ?> no-underline text-inherit">
                <img src="<?php echo htmlspecialchars($v['thumb']); ?>" class="w-full h-full object-cover <?php echo $img_opacity; ?> transition" alt="<?php echo htmlspecialchars($v['title']); ?>" onerror="this.onerror=null;this.src='<?php echo htmlspecialchars($default_image, ENT_QUOTES); ?>'">
                <?php if ($is_fb_ig): ?>
                <div class="mw-video-platform-overlay mw-video-platform-overlay--<?php echo htmlspecialchars($plat); ?>" aria-hidden="true"></div>
                <div class="absolute top-2 left-2 z-[4] flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold text-white shadow-md mw-video-platform-pill mw-video-platform-pill--<?php echo htmlspecialchars($plat); ?>">
                    <?php if ($plat === 'facebook'): ?>
                    <i class="fab fa-facebook-f" aria-hidden="true"></i><span>Facebook</span>
                    <?php else: ?>
                    <i class="fab fa-instagram" aria-hidden="true"></i><span>Instagram</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="absolute inset-0 flex items-center justify-center z-[2] pointer-events-none">
                    <div class="<?php echo $is_fb_ig ? 'w-14 h-14 bg-white text-gray-900' : 'w-12 h-12 bg-primary/90 text-bgbase'; ?> rounded-full flex items-center justify-center text-xl group-hover:scale-110 transition shadow-lg">
                        <?php if ($is_fb_ig): ?>
                        <i class="fas fa-play ml-1" aria-hidden="true"></i>
                        <?php else: ?>
                        <i class="<?php echo htmlspecialchars($ext_center_icon); ?>" aria-hidden="true"></i>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="absolute bottom-2 left-3 right-3 z-[3] text-sm font-medium drop-shadow-md truncate <?php echo $is_fb_ig ? 'text-white' : 'text-heading'; ?>"><?php echo htmlspecialchars($v['title']); ?></div>
            </a>
            <?php endif; ?>
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
    <?php if (!empty($gallery)): ?>
    <section id="mw-gallery" class="mw-image-gallery mw-section-padding">
        <h2 class="mw-section-title">Image Gallery</h2>
        <div class="mw-grid-gallery">
            <?php foreach ($gallery as $g_img): ?>
            <div class="aspect-square rounded-lg overflow-hidden border border-white/5 mw-gallery-item cursor-pointer group" role="button" tabindex="0" data-gallery-src="<?php echo htmlspecialchars($g_img, ENT_QUOTES); ?>">
                <img src="<?php echo htmlspecialchars($g_img); ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-300 pointer-events-none select-none" alt="Gallery" onerror="this.onerror=null;this.src='<?php echo htmlspecialchars($default_image, ENT_QUOTES); ?>'">
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Gallery lightbox: centered on screen, content width matches app section (max 1200px) -->
    <?php if (!empty($gallery)): ?>
    <div id="mw-gallery-modal" class="mw-gallery-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-label="Gallery" data-default-src="<?php echo htmlspecialchars($default_image, ENT_QUOTES); ?>">
        <button type="button" class="mw-gallery-modal-backdrop" aria-label="Close gallery"></button>
        <div class="mw-gallery-modal-panel">
            <button type="button" class="mw-gallery-modal-close" aria-label="Close"><i class="fas fa-times"></i></button>
            <button type="button" class="mw-gallery-modal-prev" aria-label="Previous image"><i class="fas fa-chevron-left"></i></button>
            <button type="button" class="mw-gallery-modal-next" aria-label="Next image"><i class="fas fa-chevron-right"></i></button>
            <div class="mw-gallery-modal-stage">
                <img id="mw-gallery-modal-img" src="" alt="Gallery" class="mw-gallery-modal-img">
            </div>
            <div id="mw-gallery-modal-counter" class="mw-gallery-modal-counter"></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 11. Payment QR Section - Show all uploaded QR codes -->
    <?php
    $payment_qrs = [];
    if ($row) {
        if (!empty($row['d_qr_paytm'])) $payment_qrs[] = ['label' => 'Paytm', 'img' => 'data:image/*;base64,' . base64_encode($row['d_qr_paytm']), 'upi' => trim($row['d_paytm'] ?? '')];
        if (!empty($row['d_qr_google_pay'])) $payment_qrs[] = ['label' => 'Google Pay', 'img' => 'data:image/*;base64,' . base64_encode($row['d_qr_google_pay']), 'upi' => trim($row['d_google_pay'] ?? '')];
        if (!empty($row['d_qr_phone_pay'])) $payment_qrs[] = ['label' => 'PhonePe', 'img' => 'data:image/*;base64,' . base64_encode($row['d_qr_phone_pay']), 'upi' => trim($row['d_phone_pay'] ?? '')];
    }
    if (empty($payment_qrs) && !$row) {
        $payment_qrs = [['label' => 'Scan & Pay', 'img' => 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=UPI://pay?pa=chef@upi&pn=Olivia', 'upi' => 'chef@upi']];
    }
    ?>
    <?php if (!empty($payment_qrs)): ?>
    <section id="mw-pay" class="mw-payment-qr mw-section-padding">
        <h2 class="mw-section-title">QR Code</h2>
        <div class="max-w-2xl mx-auto">
            <div class="grid grid-cols-1 sm:grid-cols-2 <?php echo count($payment_qrs) >= 3 ? 'md:grid-cols-3' : ''; ?> gap-6">
                <?php foreach ($payment_qrs as $qr): ?>
                <div class="mw-card p-6 text-center">
                    <h3 class="text-heading font-medium mb-4 uppercase tracking-widest text-sm"><?php echo htmlspecialchars($qr['label']); ?></h3>
                    <div class="bg-white p-2 rounded-lg aspect-square w-40 mx-auto overflow-hidden group cursor-pointer">
                        <img src="<?php echo htmlspecialchars($qr['img']); ?>" alt="<?php echo htmlspecialchars($qr['label']); ?> QR" class="w-full h-full object-contain transition-transform duration-300 group-hover:scale-105" onerror="this.onerror=null;this.src='<?php echo htmlspecialchars($default_image, ENT_QUOTES); ?>'">
                    </div>
                    <?php if (!empty($qr['upi'])): ?>
                    <p class="text-heading font-medium text-sm mt-3 break-all"><?php echo htmlspecialchars($qr['upi']); ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

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
    <a href="#mw-services" class="mw-nav-item <?php echo empty($services) ? 'hidden' : ''; ?>" data-section="mw-services"><i class="fas fa-concierge-bell mw-nav-icon"></i><span>Serv</span></a>
    <a href="#mw-offers" class="mw-nav-item <?php echo empty($offers) ? 'hidden' : ''; ?>" data-section="mw-offers"><i class="fas fa-tags mw-nav-icon"></i><span>Offers</span></a>
    <a href="#mw-products" class="mw-nav-item <?php echo empty($products_by_cat) ? 'hidden' : ''; ?>" data-section="mw-products"><i class="fas fa-store mw-nav-icon"></i><span>Shop</span></a>
    <a href="#mw-gallery" class="mw-nav-item <?php echo empty($gallery) ? 'hidden' : ''; ?>" data-section="mw-gallery"><i class="fas fa-images mw-nav-icon"></i><span>Gallery</span></a>
    <a href="#mw-pay" class="mw-nav-item hidden sm:flex <?php echo empty($payment_qrs) ? 'hidden' : ''; ?>" data-section="mw-pay"><i class="fas fa-qrcode mw-nav-icon"></i><span>Pay</span></a>
</nav>

<!-- 15. Floating WhatsApp Button (hidden when cart bar is visible) -->
<a id="mw-floating-wa-btn" href="https://wa.me/<?php echo $whatsapp; ?>" target="_blank" class="fixed bottom-[80px] right-4 md:right-8 w-14 h-14 bg-[#25D366] text-white rounded-full flex items-center justify-center text-3xl shadow-[0_4px_15px_rgba(37,211,102,0.4)] z-50 hover:scale-110 transition-transform">
    <i class="fab fa-whatsapp"></i>
</a>

<!-- Config for JS (API key, WhatsApp number, products for cart, share profile) -->
<script>
    window.MW_AI_API_KEY = "<?php echo htmlspecialchars(defined('GEMINI_API_KEY') ? GEMINI_API_KEY : ''); ?>";
    window.MW_WHATSAPP_NUMBER = "<?php echo htmlspecialchars($whatsapp); ?>";
    window.MW_PRODUCTS = <?php echo json_encode($products_for_js ?? []); ?>;
    window.MW_SHARE_URL = <?php echo json_encode($share_url ?? ''); ?>;
    window.MW_HERO_NAME = <?php echo json_encode($hero_name ?? ''); ?>;
    window.MW_LOCATION = <?php echo json_encode($location ?? ''); ?>;
    window.MW_PHONE = <?php echo json_encode($phone ?? ''); ?>;
    window.MW_EMAIL = <?php echo json_encode($email ?? ''); ?>;
    window.MW_VCARD = <?php echo json_encode($mw_vcard ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="theme/js/app.js?v=15"></script>

</body>
</html>