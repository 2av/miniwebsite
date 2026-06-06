<?php
// Check if this is an AJAX request FIRST - before any output
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Start output buffering for AJAX requests immediately to prevent any output
if($is_ajax && isset($_POST['offer'])) {
    ob_start();
    error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
}

// Handle card_number from URL, session, or cookie
// MUST be done before any output (before including header.php)
require_once(__DIR__ . '/../../app/config/database.php');

/**
 * Validate offer schedule: Start Dt and End Dt each need date + time together; End on or after Start.
 *
 * @return string Empty if OK, else plain-text message for the user.
 */
function mw_special_offer_validate_dt($start_date, $end_date, $start_time, $end_time) {
    $sd = trim((string) $start_date);
    $ed = trim((string) $end_date);
    $st = trim((string) $start_time);
    $et = trim((string) $end_time);
    if ($sd === '' && $ed === '' && $st === '' && $et === '') {
        return '';
    }
    if (($sd !== '') !== ($st !== '')) {
        return 'Start Dt: enter both date and time, or leave both empty.';
    }
    if (($ed !== '') !== ($et !== '')) {
        return 'End Dt: enter both date and time, or leave both empty.';
    }
    $start_ok = ($sd !== '' && $st !== '');
    $end_ok = ($ed !== '' && $et !== '');
    if ($start_ok !== $end_ok) {
        return $start_ok
            ? 'End Dt: enter both date and time when Start Dt is set.'
            : 'Start Dt: enter both date and time when End Dt is set.';
    }
    if (!$start_ok) {
        return '';
    }
    $ts_s = strtotime($sd . ' ' . $st);
    $ts_e = strtotime($ed . ' ' . $et);
    if ($ts_s === false || $ts_e === false) {
        return 'Start Dt or End Dt is not a valid date or time.';
    }
    if ($ts_e < $ts_s) {
        return 'End Dt must be on or after Start Dt.';
    }
    return '';
}

/**
 * Table cell: date + time on one line for display (no year — "j M"). DB values are full dates;
 * expiry checks use mw_special_offer_end_moment_ts() which includes the year.
 */
function mw_special_offer_format_dt_cell($date_raw, $time_raw) {
    $d = trim((string) $date_raw);
    if ($d === '' || $d === '0000-00-00' || strpos($d, '0000-00-00') === 0) {
        $d = '';
    }
    $t = trim((string) $time_raw);
    if ($d === '' && $t === '') {
        return '-';
    }
    $t_disp = '';
    if ($t !== '') {
        $dt_obj = DateTime::createFromFormat('H:i:s', $t) ?: DateTime::createFromFormat('H:i', $t);
        $t_disp = $dt_obj ? $dt_obj->format('g:i A') : substr($t, 0, 5);
    }
    if ($d !== '' && $t_disp !== '') {
        $ts = strtotime($d);
        $d_disp = $ts ? date('j M', $ts) : $d;
        return $d_disp . ', ' . $t_disp;
    }
    if ($d !== '') {
        $ts = strtotime($d);
        return $ts ? date('j M', $ts) : $d;
    }
    return $t_disp !== '' ? $t_disp : '-';
}

/** Unix timestamp for offer end, or null (mirrors n.php mw_demo_offer_end_moment_ts). */
function mw_special_offer_end_moment_ts($date_raw, $time_raw) {
    $raw = trim((string) $date_raw);
    if ($raw === '' || $raw === '0000-00-00' || strpos($raw, '0000-00-00') === 0) {
        return null;
    }
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
    $timeRaw = trim((string) $time_raw);
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
function mw_special_offer_end_dt_expired($date_raw, $time_raw) {
    $endTs = mw_special_offer_end_moment_ts($date_raw, $time_raw);
    if ($endTs === null) {
        return false;
    }
    return $endTs < time();
}

// Handle AJAX image processing FIRST - before any other output
if(isset($_POST['process_product_image_ajax']) && !empty($_FILES['product_image']['tmp_name'])){
    while(ob_get_level() > 0) {
        ob_end_clean();
    }
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    
    try {
        $file_validation_path = __DIR__ . '/../../includes/file_validation.php';
        if(file_exists($file_validation_path)) {
            require_once $file_validation_path;
        } elseif(file_exists('../../includes/file_validation.php')) {
            require_once '../../includes/file_validation.php';
        } else {
            throw new Exception('File validation library not found');
        }
        
        if(!function_exists('processHeroImageUpload') && !function_exists('processImageUploadWithAutoCrop')) {
            throw new Exception('Image processing function not available');
        }
        
        if($_FILES['product_image']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $_FILES['product_image']['error']);
        }
        
        // Special offer images: 4:3 ratio (width to height)
        if(function_exists('processHeroImageUpload')) {
            $result = processHeroImageUpload($_FILES['product_image'], 1200, 900);
        } else {
            $result = processImageUploadWithAutoCrop($_FILES['product_image'], 600, 250000, 200000, 300000, ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'], 'jpeg', null);
        }
        
        if($result['status']) {
            $base64Image = base64_encode($result['data']);
            
            echo json_encode([
                'success' => true,
                'image_data' => $base64Image,
                'dimensions' => isset($result['dimensions']) ? $result['dimensions'] : ['width' => 1200, 'height' => 900],
                'file_size' => isset($result['file_size']) ? $result['file_size'] : 0,
                'message' => 'Image processed successfully'
            ]);
            
            if(isset($result['file_path']) && $result['file_path'] && file_exists($result['file_path'])) {
                @unlink($result['file_path']);
            }
        } else {
            $errorMsg = isset($result['message']) ? strip_tags($result['message']) : 'Unknown error processing image';
            echo json_encode([
                'success' => false,
                'message' => $errorMsg
            ]);
        }
    } catch(Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    } catch(Error $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Fatal error: ' . $e->getMessage()
        ]);
    }
    
    exit;
}

if(isset($_GET['card_number']) && !empty($_GET['card_number'])){
    $card_number = mysqli_real_escape_string($connect, $_GET['card_number']);
    $_SESSION['card_id_inprocess'] = $card_number;
    setcookie('card_id_inprocess', $card_number, time() + (86400 * 1), '/');
} elseif(isset($_COOKIE['card_id_inprocess']) && !empty($_COOKIE['card_id_inprocess'])) {
    $_SESSION['card_id_inprocess'] = $_COOKIE['card_id_inprocess'];
}

// Fetch existing card data
$row = [];
$offers_data = [];
$user_id = 0;

if(isset($_SESSION['card_id_inprocess']) && !empty($_SESSION['card_id_inprocess'])) {
    $query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'" AND user_email="'.$_SESSION['user_email'].'"');
    if(mysqli_num_rows($query) > 0) {
        $row = mysqli_fetch_array($query);
        
        $user_email_escaped = mysqli_real_escape_string($connect, $_SESSION['user_email']);
        $user_email_lower = strtolower(trim($user_email_escaped));
        $user_id = 0;
        
        $user_details_query = mysqli_query($connect, "SELECT id FROM user_details WHERE LOWER(TRIM(email)) = '$user_email_lower' LIMIT 1");
        if($user_details_query && mysqli_num_rows($user_details_query) > 0) {
            $user_details_row = mysqli_fetch_array($user_details_query);
            $user_id = isset($user_details_row['id']) ? intval($user_details_row['id']) : 0;
        }
        
        // Get special offers from new table (card_special_offers)
        $card_id = mysqli_real_escape_string($connect, $_SESSION['card_id_inprocess']);
        if($user_id > 0) {
            $offers_query = mysqli_query($connect, "SELECT * FROM card_special_offers WHERE card_id='$card_id' AND user_id=$user_id ORDER BY display_order ASC, id ASC");
            while($offer_row = mysqli_fetch_array($offers_query)) {
                $offers_data[] = $offer_row;
            }
        }
    } else {
        echo '<script>alert("Card ID not found or does not belong to your account."); window.location.href="business-name.php";</script>';
        exit;
    }
} else {
    echo '<script>alert("No card selected. Please select a card first."); window.location.href="business-name.php";</script>';
    exit;
}

// Handle form submission
$error_message = '';

if(isset($_POST['offer'])){
    if($user_id <= 0) {
        if($is_ajax) {
            while(ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['success' => false, 'message' => 'User not found. Please login again.']);
            exit;
        }
        $error_message = '<div class="alert alert-danger">User not found. Please login again.</div>';
    } else {
        
        $file_validation_path = __DIR__ . '/../../includes/file_validation.php';
        if(file_exists($file_validation_path)) {
            require_once $file_validation_path;
        } elseif(file_exists('../../includes/file_validation.php')) {
            require_once '../../includes/file_validation.php';
        }
        
        if(!function_exists('processImageUploadWithAutoCrop') && !function_exists('compressImage')) {
            function compressImage($source,$destination,$quality){
            if(!function_exists('imagecreatefromjpeg') || !function_exists('imagejpeg')) {
                return $source;
            }
            
            $imageInfo = @getimagesize($source);
            if($imageInfo === false) {
                return $source;
            }
            
            $mime = $imageInfo['mime'];
            $image = false;
            
            switch($mime){
                case 'image/jpeg':
                    if(function_exists('imagecreatefromjpeg')) {
                        $image = @imagecreatefromjpeg($source);
                    }
                    break;
                case 'image/png':
                    if(function_exists('imagecreatefrompng')) {
                        $image = @imagecreatefrompng($source);
                    }
                    break;
                case 'image/gif':
                    if(function_exists('imagecreatefromgif')) {
                        $image = @imagecreatefromgif($source);
                    }
                    break;
                default:
                    if(function_exists('imagecreatefromjpeg')) {
                        $image = @imagecreatefromjpeg($source);
                    }
            }
            
            if($image === false) {
                return $source;
            }
            
            @imagejpeg($image, $destination, $quality);
            @imagedestroy($image);
            return $destination;
        }
        }
        
        $offerUploadDirAbs = __DIR__ . '/../../assets/upload/websites/special-offers/';
        if (!is_dir($offerUploadDirAbs)) {
            @mkdir($offerUploadDirAbs, 0775, true);
        }
        
        function saveOfferImageToFilesystem($binaryData, $uploadDirAbs, $cardId, $offerTitle) {
            if (empty($binaryData) || empty($uploadDirAbs) || !is_dir($uploadDirAbs)) {
                return null;
            }
            $safeOfferTitle = preg_replace('/[^a-zA-Z0-9_-]/', '_', substr($offerTitle, 0, 50));
            $fileName = $cardId . '_' . $safeOfferTitle . '_' . date('ymdsih') . '.jpg';
            $filePath = $uploadDirAbs . $fileName;
            if(@file_put_contents($filePath, $binaryData)) {
                return $fileName;
            }
            return null;
        }
        
        $card_id = mysqli_real_escape_string($connect, $_SESSION['card_id_inprocess']);
        $offers_processed = false;
        
        $processed_offer_ids = [];
        
        if(isset($_POST['offer_id']) && !empty($_POST['offer_id'])) {
            $direct_offer_id = intval($_POST['offer_id']);
            $offer_title = '';
            $offer_discount = 0;
            $offer_image = null;
            
            $slot_found = null;
            for($x = 1; $x <= 5; $x++) {
                if(isset($_POST["offer_title$x"]) && !empty(trim($_POST["offer_title$x"]))) {
                    $slot_found = $x;
                    break;
                }
            }
            
            if($slot_found) {
                $offer_title = mysqli_real_escape_string($connect, trim($_POST["offer_title$slot_found"]));
                
                $discount_val = isset($_POST["offer_discount$slot_found"]) ? trim($_POST["offer_discount$slot_found"]) : '';
                if($discount_val !== '') {
                    $offer_discount = intval(preg_replace('/[^0-9]/', '', $discount_val));
                }
                
                $offer_description = '';
                if(isset($_POST["offer_desc$slot_found"])) {
                    $offer_description = mysqli_real_escape_string($connect, trim($_POST["offer_desc$slot_found"]));
                    if(strlen($offer_description) > 500) {
                        $offer_description = substr($offer_description, 0, 500);
                    }
                }
                
                $offer_badge = '';
                $badge_val = isset($_POST["offer_badge$slot_found"]) ? trim($_POST["offer_badge$slot_found"]) : '';
                if($badge_val !== '') {
                    if(strlen($badge_val) > 20) {
                        $badge_val = substr($badge_val, 0, 20);
                    }
                    $offer_badge = mysqli_real_escape_string($connect, $badge_val);
                }
                
                $offer_start_date = '';
                if(isset($_POST["offer_start_date$slot_found"]) && !empty($_POST["offer_start_date$slot_found"])) {
                    $offer_start_date = mysqli_real_escape_string($connect, $_POST["offer_start_date$slot_found"]);
                }
                $offer_end_date = '';
                if(isset($_POST["offer_end_date$slot_found"]) && !empty($_POST["offer_end_date$slot_found"])) {
                    $offer_end_date = mysqli_real_escape_string($connect, $_POST["offer_end_date$slot_found"]);
                }
                $offer_start_time = '';
                if(isset($_POST["offer_start_time$slot_found"]) && !empty($_POST["offer_start_time$slot_found"])) {
                    $offer_start_time = mysqli_real_escape_string($connect, $_POST["offer_start_time$slot_found"]);
                }
                $offer_end_time = '';
                if(isset($_POST["offer_end_time$slot_found"]) && !empty($_POST["offer_end_time$slot_found"])) {
                    $offer_end_time = mysqli_real_escape_string($connect, $_POST["offer_end_time$slot_found"]);
                }
                $offer_status = 'Active';
                if(isset($_POST["offer_status$slot_found"]) && !empty($_POST["offer_status$slot_found"])) {
                    $offer_status = mysqli_real_escape_string($connect, $_POST["offer_status$slot_found"]);
                }
                
                if(!empty($_POST["processed_offer_image_data$slot_found"])){
                    $binary_data = base64_decode($_POST["processed_offer_image_data$slot_found"]);
                    $offer_image = saveOfferImageToFilesystem($binary_data, $offerUploadDirAbs, $card_id, $offer_title);
                } elseif(!empty($_FILES["offer_img$slot_found"]['tmp_name'])) {
                    if(function_exists('processHeroImageUpload')) {
                        $result = processHeroImageUpload($_FILES["offer_img$slot_found"], 1200, 900);
                    } elseif(function_exists('processImageUploadWithAutoCrop')) {
                        $result = processImageUploadWithAutoCrop($_FILES["offer_img$slot_found"], 600, 250000, 200000, 300000, ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'], 'jpeg', null);
                    } else {
                        $result = ['status' => false];
                    }
                    if($result['status']) {
                        $offer_image = saveOfferImageToFilesystem($result['data'], $offerUploadDirAbs, $card_id, $offer_title);
                        if(isset($result['file_path']) && $result['file_path'] && file_exists($result['file_path'])) {
                            @unlink($result['file_path']);
                        }
                    } else {
                        $error_message .= isset($result['message']) ? $result['message'] : '<div class="alert alert-danger">Error processing image.</div>';
                    }
                }
                
                $offer_dt_err = mw_special_offer_validate_dt($offer_start_date, $offer_end_date, $offer_start_time, $offer_end_time);
                if ($offer_dt_err !== '') {
                    $error_message .= '<div class="alert alert-danger">' . htmlspecialchars($offer_dt_err) . '</div>';
                } else {
                $verify_query = mysqli_query($connect, "SELECT id FROM card_special_offers WHERE id=$direct_offer_id AND card_id='$card_id' AND user_id=$user_id");
                if(mysqli_num_rows($verify_query) > 0) {
                    $start_date_sql = ($offer_start_date !== '') ? "'" . mysqli_real_escape_string($connect, $offer_start_date) . "'" : "NULL";
                    $end_date_sql = ($offer_end_date !== '') ? "'" . mysqli_real_escape_string($connect, $offer_end_date) . "'" : "NULL";
                    $start_time_sql = ($offer_start_time !== '') ? "'" . mysqli_real_escape_string($connect, $offer_start_time) . "'" : "NULL";
                    $end_time_sql = ($offer_end_time !== '') ? "'" . mysqli_real_escape_string($connect, $offer_end_time) . "'" : "NULL";
                    $offer_badge_escaped = mysqli_real_escape_string($connect, $offer_badge);
                    $offer_status_escaped = mysqli_real_escape_string($connect, $offer_status);
                    if($offer_image !== null) {
                        $offer_image_escaped = mysqli_real_escape_string($connect, $offer_image);
                        $update_query = "UPDATE card_special_offers SET offer_title='$offer_title', offer_description='$offer_description', offer_image='$offer_image_escaped', badge='$offer_badge_escaped', discount_percentage=$offer_discount, start_date=$start_date_sql, end_date=$end_date_sql, start_time=$start_time_sql, end_time=$end_time_sql, status='$offer_status_escaped' WHERE id=$direct_offer_id AND card_id='$card_id' AND user_id=$user_id";
                    } else {
                        $update_query = "UPDATE card_special_offers SET offer_title='$offer_title', offer_description='$offer_description', badge='$offer_badge_escaped', discount_percentage=$offer_discount, start_date=$start_date_sql, end_date=$end_date_sql, start_time=$start_time_sql, end_time=$end_time_sql, status='$offer_status_escaped' WHERE id=$direct_offer_id AND card_id='$card_id' AND user_id=$user_id";
                    }
                    $update_result = mysqli_query($connect, $update_query);
                    if(!$update_result) {
                        $error_message .= '<div class="alert alert-danger">Failed to update offer. Error: ' . mysqli_error($connect) . '</div>';
                    }
                    $processed_offer_ids[] = $direct_offer_id;
                }
                }
            }
        }
        
        for($x = 1; $x <= 5; $x++) {
            $offer_title = '';
            $offer_id = null;
            $offer_discount = 0;
            $offer_image = null;
            $offer_badge = '';
            $offer_start_date = '';
            $offer_end_date = '';
            $offer_start_time = '';
            $offer_end_time = '';
            $offer_status = 'Active';
            
            if(isset($_POST["offer_title$x"]) && !empty(trim($_POST["offer_title$x"]))) {
                $offers_processed = true;
                $offer_title = mysqli_real_escape_string($connect, trim($_POST["offer_title$x"]));
                
                if(isset($_POST["offer_discount$x"]) && !empty(trim($_POST["offer_discount$x"]))) {
                    $offer_discount = intval(preg_replace('/[^0-9]/', '', $_POST["offer_discount$x"]));
                }
                
                $offer_description = '';
                if(isset($_POST["offer_desc$x"])) {
                    $offer_description = mysqli_real_escape_string($connect, trim($_POST["offer_desc$x"]));
                    if(strlen($offer_description) > 500) {
                        $offer_description = substr($offer_description, 0, 500);
                    }
                }
                
                if(isset($_POST["offer_badge$x"]) && !empty(trim($_POST["offer_badge$x"]))) {
                    $badge_trimmed = trim($_POST["offer_badge$x"]);
                    if(strlen($badge_trimmed) > 20) {
                        $badge_trimmed = substr($badge_trimmed, 0, 20);
                    }
                    $offer_badge = mysqli_real_escape_string($connect, $badge_trimmed);
                }
                
                if(isset($_POST["offer_start_date$x"]) && !empty($_POST["offer_start_date$x"])) {
                    $offer_start_date = mysqli_real_escape_string($connect, $_POST["offer_start_date$x"]);
                }
                
                if(isset($_POST["offer_end_date$x"]) && !empty($_POST["offer_end_date$x"])) {
                    $offer_end_date = mysqli_real_escape_string($connect, $_POST["offer_end_date$x"]);
                }
                
                if(isset($_POST["offer_start_time$x"]) && !empty($_POST["offer_start_time$x"])) {
                    $offer_start_time = mysqli_real_escape_string($connect, $_POST["offer_start_time$x"]);
                }
                
                if(isset($_POST["offer_end_time$x"]) && !empty($_POST["offer_end_time$x"])) {
                    $offer_end_time = mysqli_real_escape_string($connect, $_POST["offer_end_time$x"]);
                }
                
                if(isset($_POST["offer_status$x"]) && !empty($_POST["offer_status$x"])) {
                    $offer_status = mysqli_real_escape_string($connect, $_POST["offer_status$x"]);
                }
                
                if(isset($_POST["offer_id$x"]) && !empty($_POST["offer_id$x"])) {
                    $offer_id = intval($_POST["offer_id$x"]);
                    if(in_array($offer_id, $processed_offer_ids)) {
                        continue;
                    }
                }

                $offer_dt_err = mw_special_offer_validate_dt($offer_start_date, $offer_end_date, $offer_start_time, $offer_end_time);
                if ($offer_dt_err !== '') {
                    $error_message .= '<div class="alert alert-danger">Offer ' . (int) $x . ': ' . htmlspecialchars($offer_dt_err) . '</div>';
                    continue;
                }
                
                if(!empty($_POST["processed_offer_image_data$x"])){
                    $binary_data = base64_decode($_POST["processed_offer_image_data$x"]);
                    $offer_image = saveOfferImageToFilesystem($binary_data, $offerUploadDirAbs, $card_id, $offer_title);
                } elseif(!empty($_FILES["offer_img$x"]['tmp_name'])) {
                    if(function_exists('processHeroImageUpload')) {
                        $result = processHeroImageUpload($_FILES["offer_img$x"], 1200, 900);
                        if($result['status']) {
                            $offer_image = saveOfferImageToFilesystem($result['data'], $offerUploadDirAbs, $card_id, $offer_title);
                        } else {
                            $error_message .= isset($result['message']) ? $result['message'] : '<div class="alert alert-danger">Error processing image for offer '.$x.'.</div>';
                        }
                    } elseif(function_exists('processImageUploadWithAutoCrop')) {
                        $result = processImageUploadWithAutoCrop($_FILES["offer_img$x"], 600, 250000, 200000, 300000, ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'], 'jpeg', null);
                        if($result['status']) {
                            $offer_image = saveOfferImageToFilesystem($result['data'], $offerUploadDirAbs, $card_id, $offer_title);
                            if(isset($result['file_path']) && $result['file_path'] && file_exists($result['file_path'])) {
                                @unlink($result['file_path']);
                            }
                        } else {
                            $error_message .= isset($result['message']) ? $result['message'] : '<div class="alert alert-danger">Error processing image for offer '.$x.'.</div>';
                        }
                    } else {
                        $filename = $_FILES["offer_img$x"]['name'];
                        $imageFileType = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                        $file_allow = array('png', 'jpeg', 'jpg', 'gif', 'webp');
                        if(in_array($imageFileType, $file_allow)) {
                            if($_FILES["offer_img$x"]['size'] <= 250000) {
                                $source = $_FILES["offer_img$x"]['tmp_name'];
                                if(function_exists('imagecreatefromjpeg') && function_exists('imagejpeg')) {
                                    $destination = $_FILES["offer_img$x"]['tmp_name'];
                                    $quality = 65;
                                    $compressimage = compressImage($source, $destination, $quality);
                                    $binary_data = file_get_contents($compressimage);
                                    $offer_image = saveOfferImageToFilesystem($binary_data, $offerUploadDirAbs, $card_id, $offer_title);
                                } else {
                                    $binary_data = file_get_contents($source);
                                    $offer_image = saveOfferImageToFilesystem($binary_data, $offerUploadDirAbs, $card_id, $offer_title);
                                }
                            } else {
                                $error_message .= '<div class="alert alert-danger">File size for Offer Image '.$x.' exceeds 250KB limit.</div>';
                            }
                        } else {
                            $error_message .= '<div class="alert alert-danger">Only PNG, JPG, JPEG, GIF, WEBP files allowed for Offer Image '.$x.'</div>';
                        }
                    }
                }
                
                $max_order_query = mysqli_query($connect, "SELECT MAX(display_order) as max_order FROM card_special_offers WHERE card_id='$card_id' AND user_id=$user_id");
                $max_order_row = mysqli_fetch_array($max_order_query);
                $display_order = isset($max_order_row['max_order']) ? intval($max_order_row['max_order']) + 1 : $x;
                
                if($offer_id && $offer_id > 0) {
                    $verify_query = mysqli_query($connect, "SELECT id FROM card_special_offers WHERE id=$offer_id AND card_id='$card_id' AND user_id=$user_id");
                    if(mysqli_num_rows($verify_query) == 0) {
                        $error_message .= '<div class="alert alert-danger">Offer not found or access denied.</div>';
                        continue;
                    }
                    
                    // Use NULL for empty optional date/time fields (MySQL rejects empty string for DATE/TIME)
                    $start_date_sql = ($offer_start_date !== '') ? "'" . mysqli_real_escape_string($connect, $offer_start_date) . "'" : "NULL";
                    $end_date_sql = ($offer_end_date !== '') ? "'" . mysqli_real_escape_string($connect, $offer_end_date) . "'" : "NULL";
                    $start_time_sql = ($offer_start_time !== '') ? "'" . mysqli_real_escape_string($connect, $offer_start_time) . "'" : "NULL";
                    $end_time_sql = ($offer_end_time !== '') ? "'" . mysqli_real_escape_string($connect, $offer_end_time) . "'" : "NULL";
                    
                    if($offer_image !== null) {
                        $offer_image_escaped = mysqli_real_escape_string($connect, $offer_image);
                        $update_query = "UPDATE card_special_offers SET offer_title='$offer_title', offer_description='$offer_description', offer_image='$offer_image_escaped', badge='$offer_badge', discount_percentage=$offer_discount, start_date=$start_date_sql, end_date=$end_date_sql, start_time=$start_time_sql, end_time=$end_time_sql, status='$offer_status' WHERE id=$offer_id AND card_id='$card_id' AND user_id=$user_id";
                    } else {
                        $update_query = "UPDATE card_special_offers SET offer_title='$offer_title', offer_description='$offer_description', badge='$offer_badge', discount_percentage=$offer_discount, start_date=$start_date_sql, end_date=$end_date_sql, start_time=$start_time_sql, end_time=$end_time_sql, status='$offer_status' WHERE id=$offer_id AND card_id='$card_id' AND user_id=$user_id";
                    }
                    $update_result = mysqli_query($connect, $update_query);
                    if(!$update_result) {
                        $error_message .= '<div class="alert alert-danger">Failed to update offer. Error: ' . mysqli_error($connect) . '</div>';
                    }
                } else {
                    if($user_id <= 0) {
                        $error_message .= '<div class="alert alert-danger">Invalid user ID. Please login again.</div>';
                        continue;
                    }
                    
                    $card_id_escaped = mysqli_real_escape_string($connect, $card_id);
                    $offer_title_escaped = mysqli_real_escape_string($connect, $offer_title);
                    
                    // Use NULL for empty optional date/time fields (MySQL rejects empty string for DATE/TIME)
                    $start_date_sql = ($offer_start_date !== '') ? "'" . mysqli_real_escape_string($connect, $offer_start_date) . "'" : "NULL";
                    $end_date_sql = ($offer_end_date !== '') ? "'" . mysqli_real_escape_string($connect, $offer_end_date) . "'" : "NULL";
                    $start_time_sql = ($offer_start_time !== '') ? "'" . mysqli_real_escape_string($connect, $offer_start_time) . "'" : "NULL";
                    $end_time_sql = ($offer_end_time !== '') ? "'" . mysqli_real_escape_string($connect, $offer_end_time) . "'" : "NULL";
                    
                    if($offer_image !== null) {
                        $offer_image_escaped = mysqli_real_escape_string($connect, $offer_image);
                        $insert_query = "INSERT INTO card_special_offers (card_id, user_id, offer_title, offer_description, offer_image, badge, discount_percentage, start_date, end_date, start_time, end_time, status, display_order) VALUES ('$card_id_escaped', $user_id, '$offer_title_escaped', '$offer_description', '$offer_image_escaped', '$offer_badge', $offer_discount, $start_date_sql, $end_date_sql, $start_time_sql, $end_time_sql, '$offer_status', $display_order)";
                    } else {
                        $insert_query = "INSERT INTO card_special_offers (card_id, user_id, offer_title, offer_description, badge, discount_percentage, start_date, end_date, start_time, end_time, status, display_order) VALUES ('$card_id_escaped', $user_id, '$offer_title_escaped', '$offer_description', '$offer_badge', $offer_discount, $start_date_sql, $end_date_sql, $start_time_sql, $end_time_sql, '$offer_status', $display_order)";
                    }
                    
                    $insert_result = mysqli_query($connect, $insert_query);
                    if(!$insert_result) {
                        $error_message .= '<div class="alert alert-danger">Failed to add offer. Error: ' . mysqli_error($connect) . '</div>';
                    } else {
                        $new_offer_id = mysqli_insert_id($connect);
                        if($is_ajax && $new_offer_id > 0) {
                            if(!isset($ajax_offer_id)) {
                                $ajax_offer_id = $new_offer_id;
                            }
                        }
                    }
                }
            }
        }
        
        if(empty($error_message)) {
            if($is_ajax) {
                if(ob_get_level() > 0) {
                    ob_clean();
                }
                if (!headers_sent()) {
                    header('Content-Type: application/json');
                }
                $response_data = ['success' => true, 'message' => 'Offer saved successfully'];
                if(isset($ajax_offer_id) && $ajax_offer_id > 0) {
                    $response_data['offer_id'] = $ajax_offer_id;
                }
                echo json_encode($response_data);
                if(ob_get_level() > 0) {
                    ob_end_flush();
                }
                exit;
            } else {
                $_SESSION['save_success'] = "Offers Updated Successfully!";
                header('Location: special-offers.php?card_number='.$_SESSION['card_id_inprocess']);
                exit;
            }
        } else {
            if($is_ajax) {
                if(ob_get_level() > 0) {
                    ob_clean();
                }
                if (!headers_sent()) {
                    header('Content-Type: application/json');
                }
                echo json_encode(['success' => false, 'message' => $error_message]);
                if(ob_get_level() > 0) {
                    ob_end_flush();
                }
                exit;
            }
        }
    }
}

include '../includes/header.php';
require_once(__DIR__ . '/../../common/mw_modal.php');
?>

<!-- Phase B · Step 13 — special-offers.php page chrome uses .mw-* design system.
     Offers table, hidden #offerForm with 5 sets of inputs, #offerModal via mw_modal.php.
     JS hooks intact: openOfferModal(), editOfferFromRow(), removeOffer(), saveOffers(). -->
<main class="Dashboard mw-page">
    <div class="container-fluid customer_content_area mw-container">
        <div class="main-top mw-page-header">
            <h1 class="heading mw-page-title">Special Offers</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mw-breadcrumb">
                    <li class="breadcrumb-item mw-breadcrumb-item"><a href="#">Mini Website</a></li>
                    <li class="breadcrumb-item mw-breadcrumb-item active" aria-current="page">Special Offers</li>
                </ol>
            </nav>
        </div>

        <?php if(isset($_SESSION['save_success'])): ?>
            <div class="alert alert-dismissible fade show mw-alert mw-alert-success" role="alert">
                <i class="fa fa-check-circle mw-alert-icon" aria-hidden="true"></i>
                <div class="mw-alert-body"><?php echo $_SESSION['save_success']; unset($_SESSION['save_success']); ?></div>
                <button type="button" class="close mw-alert-close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
        <?php endif; ?>
        <?php if(isset($_SESSION['save_error'])): ?>
            <div class="alert alert-dismissible fade show mw-alert mw-alert-danger" role="alert">
                <i class="fa fa-exclamation-circle mw-alert-icon" aria-hidden="true"></i>
                <div class="mw-alert-body"><?php echo $_SESSION['save_error']; unset($_SESSION['save_error']); ?></div>
                <button type="button" class="close mw-alert-close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
        <?php endif; ?>

        <div class="card mb-4 mw-card">
            <div class="card-body mw-card-body">
                <div class="so-section-head">
                    <h2 class="heading mw-section-title so-section-heading">Special Offers</h2>
                    <p class="sub_title mw-helper-text">
                        <i class="fa fa-info-circle" aria-hidden="true"></i>
                        <span>You can add up to <strong style="color:var(--mw-color-text);font-weight:600">5 special offers</strong> to showcase on your Mini Website. <span class="text-muted">Image formats: jpg, jpeg, png, gif, webp.</span></span>
                    </p>
                </div>

                <div id="status_remove_img"></div>

                <div class="so-toolbar">
                    <button type="button" id="addOfferBtn" class="btn btn-primary add_product mw-btn mw-btn-save" onclick="openOfferModal()" <?php echo (count($offers_data ?? []) >= 5) ? 'disabled' : ''; ?>>
                        <i class="fa fa-plus" aria-hidden="true"></i>
                        <span>Add Offer</span>
                    </button>
                 </div>

                <div class="Product-ServicesTable mw-table-scroll mw-table-scroll-wide">
                    <table class="display table">
                        <thead class="mw-table-header">
                            <tr>
                                <th>Image</th>
                                <th>Badge</th>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Start Dt</th>
                                <th>End Dt</th>
                                <th>Offer Status</th>
                                <th>Manage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $offerCount = count($offers_data);
                            if($offerCount > 0):
                                foreach($offers_data as $index => $offer): 
                                    $offer_id = intval($offer['id']);
                                    $offer_title = !empty($offer['offer_title']) ? htmlspecialchars($offer['offer_title']) : 'No Title';
                                    $offer_description = !empty($offer['offer_description']) ? htmlspecialchars($offer['offer_description']) : '';
                                    $offer_discount = !empty($offer['discount_percentage']) ? intval($offer['discount_percentage']) : 0;
                                    $offer_badge = !empty($offer['badge']) ? htmlspecialchars($offer['badge']) : '-';
                                    $start_date = !empty($offer['start_date']) ? $offer['start_date'] : '-';
                                    $end_date = !empty($offer['end_date']) ? $offer['end_date'] : '-';
                                    $start_time = !empty($offer['start_time']) ? $offer['start_time'] : '-';
                                    $end_time = !empty($offer['end_time']) ? $offer['end_time'] : '-';
                                    $offer_status = !empty($offer['status']) ? htmlspecialchars($offer['status']) : 'Active';
                            ?>
                                <tr data-offer-id="<?php echo $offer_id; ?>" data-card-id="<?php echo $card_id;?>" data-offer-title="<?php echo htmlspecialchars($offer_title, ENT_QUOTES); ?>" data-offer-badge="<?php echo htmlspecialchars($offer_badge !== '-' ? $offer_badge : '', ENT_QUOTES); ?>" data-offer-discount="<?php echo $offer_discount; ?>" data-offer-desc="<?php echo htmlspecialchars($offer_description ?? '', ENT_QUOTES); ?>" data-offer-start-date="<?php echo $start_date !== '-' ? htmlspecialchars($start_date, ENT_QUOTES) : ''; ?>" data-offer-end-date="<?php echo $end_date !== '-' ? htmlspecialchars($end_date, ENT_QUOTES) : ''; ?>" data-offer-start-time="<?php echo $start_time !== '-' ? htmlspecialchars($start_time, ENT_QUOTES) : ''; ?>" data-offer-end-time="<?php echo $end_time !== '-' ? htmlspecialchars($end_time, ENT_QUOTES) : ''; ?>" data-offer-status="<?php echo htmlspecialchars($offer_status, ENT_QUOTES); ?>">
                                    <td valign="middle">
                                        <?php if(!empty($offer['offer_image'])): ?>
                                            <?php
                                            if(is_string($offer['offer_image']) && strpos($offer['offer_image'], '/') === false && strpos($offer['offer_image'], '\\') === false && strpos($offer['offer_image'], '.') !== false) {
                                                $image_src = '../../assets/upload/websites/special-offers/' . $offer['offer_image'];
                                            } else {
                                                $image_src = 'data:image/*;base64,' . base64_encode($offer['offer_image']);
                                            }
                                            ?>
                                            <img src="<?php echo htmlspecialchars($image_src); ?>" class="img-fluid" width="30px" alt="">
                                        <?php else: ?>
                                            <span class="text-muted">No Image</span>
                                        <?php endif; ?>
                                    </td>
                                    <td valign="middle">
                                        <?php if($offer_badge !== '-'): ?>
                                            <span class="badge badge-info"><?php echo $offer_badge; ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td valign="middle"><strong><?php echo $offer_title; ?></strong></td>
                                    <td valign="middle"><?php echo !empty($offer_description) ? substr($offer_description, 0, 40) . (strlen($offer_description) > 40 ? '...' : '') : '<span class="text-muted">-</span>'; ?></td>
                                    <td valign="middle">
                                        <small><?php echo htmlspecialchars(mw_special_offer_format_dt_cell($offer['start_date'] ?? '', $offer['start_time'] ?? '')); ?></small>
                                    </td>
                                    <td valign="middle">
                                        <small><?php echo htmlspecialchars(mw_special_offer_format_dt_cell($offer['end_date'] ?? '', $offer['end_time'] ?? '')); ?></small>
                                        <?php if (mw_special_offer_end_dt_expired($offer['end_date'] ?? '', $offer['end_time'] ?? '')): ?>
                                            <br><small class="text-danger">offer expired</small>
                                        <?php endif; ?>
                                    </td>
                                    <td valign="middle">
                                        <?php 
                                        if($offer_status === 'Active') {
                                            echo '<span class="badge badge-success">Active</span>';
                                        } elseif($offer_status === 'Inactive') {
                                            echo '<span class="badge badge-secondary">Inactive</span>';
                                        } else {
                                            echo '<span class="badge badge-warning">' . $offer_status . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td valign="middle">
                                        <a class="edit" href="javascript:void(0);" onclick="event.stopPropagation(); event.preventDefault(); editOfferFromRow(this); return false;" title="Edit"><i class="fa fa-edit" style="font-size:16px;color:#007bff;margin-right:8px;"></i></a>
                                        <a class="delet" href="javascript:void(0);" onclick="event.stopPropagation(); event.preventDefault(); removeOffer(<?php echo $offer_id; ?>); return false;" title="Delete"><i class="fa fa-trash" style="font-size:16px;color:#dc3545;"></i></a>
                                    </td>
                                </tr>
                            <?php 
                                endforeach;
                            else:
                            ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No special offers added yet. Click "Add Offer" to add.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Hidden form for saving all offers -->
                    <form id="offerForm" action="" method="POST" enctype="multipart/form-data" style="display:none;">
                        <?php for($m = 1; $m <= 5; $m++): ?>
                            <input type="text" name="offer_title<?php echo $m; ?>" id="form_offer_title<?php echo $m; ?>" value="">
                            <input type="text" name="offer_badge<?php echo $m; ?>" id="form_offer_badge<?php echo $m; ?>" value="" maxlength="20">
                            <input type="number" name="offer_discount<?php echo $m; ?>" id="form_offer_discount<?php echo $m; ?>" value="">
                            <input type="text" name="offer_desc<?php echo $m; ?>" id="form_offer_desc<?php echo $m; ?>" value="">
                            <input type="date" name="offer_start_date<?php echo $m; ?>" id="form_offer_start_date<?php echo $m; ?>" value="">
                            <input type="date" name="offer_end_date<?php echo $m; ?>" id="form_offer_end_date<?php echo $m; ?>" value="">
                            <input type="time" name="offer_start_time<?php echo $m; ?>" id="form_offer_start_time<?php echo $m; ?>" value="">
                            <input type="time" name="offer_end_time<?php echo $m; ?>" id="form_offer_end_time<?php echo $m; ?>" value="">
                            <input type="text" name="offer_status<?php echo $m; ?>" id="form_offer_status<?php echo $m; ?>" value="">
                            <input type="file" name="offer_img<?php echo $m; ?>" id="form_offer_img<?php echo $m; ?>">
                            <input type="hidden" name="processed_offer_image_data<?php echo $m; ?>" id="form_processed_image_data<?php echo $m; ?>" value="">
                            <input type="hidden" name="offer_id<?php echo $m; ?>" id="form_offer_id<?php echo $m; ?>" value="">
                        <?php endfor; ?>
                    </form>
                   
                </div>
                <div class="Product-ServicesBtn mw-btn-row">
                    <a href="services.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-left mw-btn mw-btn-back">
                        <span class="left_angle angle mw-btn-angle"><i class="fa fa-angle-left" aria-hidden="true"></i></span>
                        <span>Back</span>
                    </a>
                    <button type="button" class="btn btn-primary align-center save_btn mw-btn mw-btn-save" onclick="saveOffers()">
                        <img src="../../assets/images/Save.png" alt="" style="width:1.25rem;height:1.25rem;flex-shrink:0;">
                        <span>Save</span>
                    </button>
                    <a href="products.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-right mw-btn mw-btn-next">
                        <span>Next</span>
                        <span class="right_angle angle mw-btn-angle"><i class="fa fa-angle-right" aria-hidden="true"></i></span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
ob_start();
?>
                <form id="modalOfferForm" class="mw-form so-offer-modal-form">
                    <input type="hidden" id="modal_offer_id" value="">
                    <input type="hidden" id="modal_offer_discount" value="0">

                    <!-- Image first -->
                    <div class="form-group">
                        <label>Offer Image (Banner) <span class="text-muted">(Optional)</span></label>
                        <div class="offer-image-preview-modal" style="max-width: 280px; margin: 0 auto 15px; aspect-ratio: 4/3; overflow: hidden; border: 2px dashed #ddd; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; background: #f5f5f5;" onclick="document.getElementById('modal_offer_image').click()">
                            <img id="modal_offer_image_preview" 
                                 src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjgwIiBoZWlnaHQ9IjIxMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjgwIiBoZWlnaHQ9IjIxMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5DbGljayB0byBVcGxvYWQ8L3RleHQ+PC9zdmc+" 
                                 alt="Offer Image" 
                                 style="width: 100%; height: 100%; object-fit: cover; display: block;">
                        </div>
                        <input type="file" id="modal_offer_image" onchange="handleOfferImageUpload(this);" accept=".jpg,.jpeg,.png,.gif,.webp" style="display:none;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('modal_offer_image').click()">Choose Image</button>
                    </div>

                    <div class="form-group">
                        <label for="modal_offer_title">Offer Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="modal_offer_title" placeholder="e.g., Flat 25% OFF" maxlength="255" required>
                    </div>

                    <div class="form-group">
                        <label for="modal_offer_badge_select">Offer Badge <span class="text-muted">(Optional)</span></label>
                        <select class="form-control mb-2" id="modal_offer_badge_select">
                            <option value="">Select badge</option>
                            <option value="Flat 10% Off">Flat 10% Off [Editable]</option>
                            <option value="Upto 50% Off">Upto 50% Off [Editable]</option>
                            <option value="Starting @ &#8377;99">Starting @ &#8377;99 [Editable]</option>
                            <option value="Starting @ &#8377;199">Starting @ &#8377;199 [Editable]</option>
                            <option value="Under &#8377;499">Under &#8377;499 [Editable]</option>
                            <option value="Under &#8377;999">Under &#8377;999 [Editable]</option>
                            <option value="Lowest Price">Lowest Price</option>
                            <option value="Special Offer">Special Offer</option>
                            <option value="Limited Time Offer">Limited Time Offer</option>
                            <option value="Mega Deal">Mega Deal</option>
                            <option value="Best Deal">Best Deal</option>
                            <option value="Combo Offer">Combo Offer</option>
                            <option value="Buy 1 Get 1">Buy 1 Get 1</option>
                            <option value="Buy 2 Get 1">Buy 2 Get 1</option>
                            <option value="Bundle Offer">Bundle Offer</option>
                            <option value="Limited Stock">Limited Stock</option>
                            <option value="New Arrival">New Arrival</option>
                            <option value="Just Launched">Just Launched</option>
                            <option value="Trending">Trending</option>
                            <option value="Popular">Popular</option>
                            <option value="Bestseller">Bestseller</option>
                            <option value="Summer Offer">Summer Offer</option>
                            <option value="Winter Sale">Winter Sale</option>
                            <option value="New Year Offer">New Year Offer</option>
                            <option value="Festive Offer">Festive Offer</option>
                            <option value="Wedding Special">Wedding Special</option>
                            <option value="__custom__">Customize...</option>
                        </select>
                        <input type="text" class="form-control" id="modal_offer_badge" placeholder="Edit badge text (max 20 characters)" maxlength="20" style="display:none;">
                        <small class="form-text text-muted" id="badge_char_counter_wrap" style="display:none;"><strong id="badge_char_counter">0</strong>/20 characters</small>
                    </div>

                    <div class="form-group">
                        <label for="modal_offer_description">Offer Description <span class="text-muted">(Optional)</span></label>
                        <textarea class="form-control" id="modal_offer_description" maxlength="500" placeholder="Enter offer description details" rows="3"></textarea>
                        <small class="form-text text-muted">
                            <strong id="char_counter_display">0/500</strong> characters
                        </small>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="modal_offer_start_date">Start Dt <span class="text-muted">(date)</span></label>
                                <input type="date" class="form-control" id="modal_offer_start_date">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="modal_offer_start_time">Start Dt <span class="text-muted">(time)</span></label>
                                <input type="time" class="form-control" id="modal_offer_start_time">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="modal_offer_end_date">End Dt <span class="text-muted">(date)</span></label>
                                <input type="date" class="form-control" id="modal_offer_end_date">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="modal_offer_end_time">End Dt <span class="text-muted">(time)</span></label>
                                <input type="time" class="form-control" id="modal_offer_end_time">
                            </div>
                        </div>
                    </div>
                    <p class="text-muted small mb-0">Start Dt and End Dt are optional. If you use them, enter <strong>both</strong> date and time for each; End Dt must be on or after Start Dt.</p>

                    <div class="form-group">
                        <label for="modal_offer_status">Offer Status</label>
                        <select class="form-control" id="modal_offer_status">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </form>
<?php
$offer_modal_body = ob_get_clean();

mw_modal_render([
    'id'       => 'offerModal',
    'size'     => 'lg',
    'title'    => 'Add Special Offer',
    'subtitle' => 'Banner, title, dates, and status',
    'icon'     => 'fa-tag',
    'body'     => $offer_modal_body,
    'body_class' => 'so-offer-modal-body',
    'footer'   => mw_modal_footer([
        ['label' => 'Cancel', 'class' => 'mw-btn mw-btn-cancel', 'attrs' => 'type="button" data-mw-modal-close'],
        ['label' => 'Add Offer', 'class' => 'mw-btn mw-btn-save', 'attrs' => 'type="button" id="offerModalSubmitBtn"'],
    ]),
    'static'   => true,
    'hidden'   => true,
]);
?>

<style>
    select.form-control {
        appearance: none !important;
        -webkit-appearance: none !important;
        -moz-appearance: none !important;
        background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e") !important;
        background-repeat: no-repeat !important;
        background-position: right 10px center !important;
        background-size: 20px !important;
        padding-right: 40px !important;
        cursor: pointer;
        background-color: white !important;
        border: 1px solid #ced4da !important;
    }

    select.form-control:disabled {
        background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23ccc' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e") !important;
        background-color: #e9ecef !important;
    }

    .add_product:disabled,
    .add_product[disabled] {
        opacity: 0.6;
        cursor: not-allowed;
    }
</style>

<!-- Phase B · Step 13 — design-system chrome overrides for special-offers. -->
<style>
    main.Dashboard .heading.mw-section-title.so-section-heading {
        font-size: var(--mw-font-section-title);
        line-height: 1.3;
        color: var(--mw-color-text);
        font-weight: 600;
        margin: 0 0 0.5rem;
        display: inline-block;
        position: relative;
        padding-bottom: 0.5rem;
        background: transparent;
    }
    @media (min-width: 768px) {
        main.Dashboard .heading.mw-section-title.so-section-heading { font-size: var(--mw-font-section-title-lg); }
    }
    main.Dashboard .heading.mw-section-title.so-section-heading::after {
        content: ''; position: absolute; left: 0; bottom: 0;
        width: 3rem; height: 2px; background: var(--mw-color-primary); border-radius: 9999px;
    }
    main.Dashboard .so-section-head { margin-bottom: 1.25rem; }
    main.Dashboard .so-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; margin: 0.5rem 0 1.25rem; }
    main.Dashboard .so-toolbar .add_product.mw-btn.mw-btn-save { margin: 0 !important; }
    main.Dashboard .so-toolbar .so-count-pill { padding: 0.375rem 0.75rem; }
    /* Offer modal (MwModal) */
    #offerModal .so-offer-modal-body {
        max-height: min(600px, 65vh);
        overflow-y: auto;
    }
    #offerModal .so-offer-modal-form .offer-image-preview-modal {
        max-width: 280px;
        margin: 0 auto 15px;
        aspect-ratio: 4 / 3;
        overflow: hidden;
        border: 2px dashed var(--mw-color-border, #e2e8f0);
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8fafc;
    }

    /* Button row — neutralize legacy rules; layout from .mw-btn-row in header.php */
    main.Dashboard .Product-ServicesBtn.mw-btn-row {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 0.75rem !important;
        width: 100% !important;
        max-width: 100% !important;
        padding: 0 !important;
        margin-top: 1.5rem !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
        position: relative !important;
        box-sizing: border-box !important;
    }
    main.Dashboard .Product-ServicesBtn.mw-btn-row .mw-btn,
    main.Dashboard .Product-ServicesBtn.mw-btn-row .save_btn {
        position: static !important;
        bottom: auto !important;
        left: auto !important;
        right: auto !important;
        width: auto !important;
        max-width: none !important;
        min-width: 0 !important;
        height: auto !important;
        min-height: 3rem !important;
        margin: 0 !important;
        margin-top: 0 !important;
        padding: var(--mw-btn-padding-y) var(--mw-btn-padding-x) !important;
        flex: 0 0 auto !important;
        order: unset !important;
    }
    main.Dashboard .Product-ServicesBtn.mw-btn-row .align-center img {
        width: 1.25rem !important;
        height: 1.25rem !important;
    }
    @media screen and (max-width: 767.98px) {
        main.Dashboard .card-body.mw-card-body {
            padding-bottom: 1.5rem !important;
        }
        main.Dashboard .Product-ServicesBtn.mw-btn-row {
            flex-wrap: wrap !important;
            justify-content: stretch !important;
        }
        main.Dashboard .Product-ServicesBtn.mw-btn-row .mw-btn-save {
            order: 1 !important;
            flex: 1 1 100% !important;
            width: 100% !important;
            max-width: 100% !important;
        }
        main.Dashboard .Product-ServicesBtn.mw-btn-row .mw-btn-back {
            order: 2 !important;
            flex: 1 1 calc(50% - 0.375rem) !important;
            max-width: calc(50% - 0.375rem) !important;
        }
        main.Dashboard .Product-ServicesBtn.mw-btn-row .mw-btn-next {
            order: 3 !important;
            flex: 1 1 calc(50% - 0.375rem) !important;
            max-width: calc(50% - 0.375rem) !important;
        }
    }
</style>

<script>
var currentOfferId = null;
var processedOfferImageData = null;
var EDITABLE_BADGE_PRESETS = [
    'Flat 10% Off',
    'Upto 50% Off',
    'Starting @ ₹99',
    'Starting @ ₹199',
    'Under ₹499',
    'Under ₹999'
];

var OFFER_MODAL_PLACEHOLDER_IMG = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjgwIiBoZWlnaHQ9IjIxMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjgwIiBoZWlnaHQ9IjIxMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5DbGljayB0byBVcGxvYWQ8L3RleHQ+PC9zdmc+';

function setOfferModalMode(isEdit) {
    var titleEl = document.getElementById('offerModalTitle');
    var saveBtn = document.getElementById('offerModalSubmitBtn');
    if (titleEl) {
        titleEl.textContent = isEdit ? 'Edit Special Offer' : 'Add Special Offer';
    }
    if (saveBtn) {
        saveBtn.textContent = isEdit ? 'Update Offer' : 'Add Offer';
    }
}

function showOfferModal() {
    if (window.MwModal && typeof window.MwModal.open === 'function') {
        window.MwModal.open('offerModal');
    }
}

function updateCharCount() {
    var textarea = document.getElementById('modal_offer_description');
    var counter = document.getElementById('char_counter_display');
    
    if(textarea && counter) {
        var length = textarea.value.length;
        counter.textContent = length + '/500';
    }
}

function updateBadgeCharCount() {
    var badgeInput = document.getElementById('modal_offer_badge');
    var badgeCounter = document.getElementById('badge_char_counter');
    if(badgeInput && badgeCounter) {
        badgeCounter.textContent = String(badgeInput.value.length);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var textarea = document.getElementById('modal_offer_description');
    if(textarea) {
        textarea.addEventListener('input', updateCharCount);
        textarea.addEventListener('keyup', updateCharCount);
        textarea.addEventListener('change', updateCharCount);
    }
    var badgeInput = document.getElementById('modal_offer_badge');
    if(badgeInput) {
        badgeInput.addEventListener('input', updateBadgeCharCount);
        badgeInput.addEventListener('keyup', updateBadgeCharCount);
        badgeInput.addEventListener('change', updateBadgeCharCount);
        badgeInput.addEventListener('input', syncBadgeSelectFromInput);
    }
    var badgeSelect = document.getElementById('modal_offer_badge_select');
    if(badgeSelect) {
        badgeSelect.addEventListener('change', onBadgeSelectChange);
    }
    var saveBtn = document.getElementById('offerModalSubmitBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', addOfferToForm);
    }
});

function onBadgeSelectChange() {
    var badgeSelect = document.getElementById('modal_offer_badge_select');
    var badgeInput = document.getElementById('modal_offer_badge');
    if(!badgeSelect || !badgeInput) return;

    var selectedValue = badgeSelect.value || '';
    var shouldShowInput = shouldShowBadgeInput(selectedValue);

    if(selectedValue === '__custom__') {
        if(!badgeInput.value) badgeInput.value = '';
    } else {
        badgeInput.value = selectedValue;
    }

    updateBadgeInputVisibility(shouldShowInput);
    if(shouldShowInput) {
        badgeInput.focus();
    }
    updateBadgeCharCount();
}

function syncBadgeSelectFromInput() {
    var badgeSelect = document.getElementById('modal_offer_badge_select');
    var badgeInput = document.getElementById('modal_offer_badge');
    if(!badgeSelect || !badgeInput) return;
    var inputValue = (badgeInput.value || '').trim();
    if(!inputValue) {
        badgeSelect.value = '';
        updateBadgeInputVisibility(false);
        return;
    }
    var matchedOption = Array.prototype.find.call(badgeSelect.options, function(opt) {
        return opt.value === inputValue;
    });
    if(matchedOption) {
        badgeSelect.value = inputValue;
        updateBadgeInputVisibility(shouldShowBadgeInput(inputValue));
    } else {
        badgeSelect.value = '__custom__';
        updateBadgeInputVisibility(true);
    }
}

function shouldShowBadgeInput(selectedValue) {
    if(!selectedValue) return false;
    return selectedValue === '__custom__' || EDITABLE_BADGE_PRESETS.indexOf(selectedValue) !== -1;
}

function updateBadgeInputVisibility(showCustomInput) {
    var badgeInput = document.getElementById('modal_offer_badge');
    var badgeCounterWrap = document.getElementById('badge_char_counter_wrap');
    if(badgeInput) {
        badgeInput.style.display = showCustomInput ? 'block' : 'none';
    }
    if(badgeCounterWrap) {
        badgeCounterWrap.style.display = showCustomInput ? 'block' : 'none';
    }
}

var MAX_OFFERS = 5;
function updateAddOfferButtonState() {
    var btn = document.getElementById('addOfferBtn');
    if(!btn) return;
    var offerCount = document.querySelectorAll('tr[data-offer-id]').length;
    if(offerCount >= MAX_OFFERS) {
        btn.disabled = true;
        btn.setAttribute('title', 'Maximum ' + MAX_OFFERS + ' offers allowed. Delete one to add more.');
    } else {
        btn.disabled = false;
        btn.removeAttribute('title');
    }
}

function openOfferModal() {
    var offerCount = document.querySelectorAll('tr[data-offer-id]').length;
    if(offerCount >= MAX_OFFERS) {
        alert('You can only add up to ' + MAX_OFFERS + ' special offers. Please delete one before adding a new offer.');
        return;
    }
    currentOfferId = null;
    processedOfferImageData = null;
    
    var fields = [
        'modal_offer_id',
        'modal_offer_title',
        'modal_offer_badge_select',
        'modal_offer_badge',
        'modal_offer_discount',
        'modal_offer_description',
        'modal_offer_start_date',
        'modal_offer_end_date',
        'modal_offer_start_time',
        'modal_offer_end_time',
        'modal_offer_status',
        'modal_offer_image'
    ];
    
    fields.forEach(function(fieldId) {
        var field = document.getElementById(fieldId);
        if(field) {
            if(fieldId === 'modal_offer_status') {
                field.value = 'Active';
            } else if(fieldId === 'modal_offer_badge_select') {
                field.value = '';
            } else if(fieldId === 'modal_offer_discount') {
                field.value = '0';
            } else {
                field.value = '';
            }
        }
    });
    
    var imgPreview = document.getElementById('modal_offer_image_preview');
    if(imgPreview) {
        imgPreview.src = OFFER_MODAL_PLACEHOLDER_IMG;
    }
    var wrapper = document.querySelector('.offer-image-preview-modal');
    if(wrapper) wrapper.style.border = '2px dashed #ddd';
    
    updateCharCount();
    updateBadgeCharCount();
    updateBadgeInputVisibility(false);
    setOfferModalMode(false);
    showOfferModal();
}

function editOfferFromRow(editLink) {
    var row = editLink.closest ? editLink.closest('tr') : editLink.parentElement;
    while (row && row.tagName !== 'TR') row = row.parentElement;
    if (!row) return;
    var offerId = row.getAttribute('data-offer-id') || row.dataset.offerId;
    var offerTitle = row.getAttribute('data-offer-title') || row.dataset.offerTitle || '';
    var offerBadge = row.getAttribute('data-offer-badge') || row.dataset.offerBadge || '';
    var offerDiscount = row.getAttribute('data-offer-discount') || row.dataset.offerDiscount || '0';
    var offerDescription = row.getAttribute('data-offer-desc') || row.dataset.offerDesc || '';
    var startDate = row.getAttribute('data-offer-start-date') || row.dataset.offerStartDate || '';
    var endDate = row.getAttribute('data-offer-end-date') || row.dataset.offerEndDate || '';
    var startTime = row.getAttribute('data-offer-start-time') || row.dataset.offerStartTime || '';
    var endTime = row.getAttribute('data-offer-end-time') || row.dataset.offerEndTime || '';
    var offerStatus = row.getAttribute('data-offer-status') || row.dataset.offerStatus || 'Active';
    editOffer(offerId, offerTitle, offerBadge, offerDiscount, offerDescription, startDate, endDate, startTime, endTime, offerStatus);
}

function editOffer(offerId, offerTitle, offerBadge, offerDiscount, offerDescription, startDate, endDate, startTime, endTime, offerStatus) {
    currentOfferId = offerId;
    processedOfferImageData = null;
    
    var modalOfferIdField = document.getElementById('modal_offer_id');
    var modalOfferTitleField = document.getElementById('modal_offer_title');
    var modalOfferBadgeField = document.getElementById('modal_offer_badge');
    var modalOfferDiscountField = document.getElementById('modal_offer_discount');
    var modalOfferDescField = document.getElementById('modal_offer_description');
    var modalOfferStartDateField = document.getElementById('modal_offer_start_date');
    var modalOfferEndDateField = document.getElementById('modal_offer_end_date');
    var modalOfferStartTimeField = document.getElementById('modal_offer_start_time');
    var modalOfferEndTimeField = document.getElementById('modal_offer_end_time');
    var modalOfferStatusField = document.getElementById('modal_offer_status');
    
    if(modalOfferIdField) modalOfferIdField.value = offerId;
    if(modalOfferTitleField) modalOfferTitleField.value = offerTitle || '';
    if(modalOfferBadgeField) {
        var badgeVal = (offerBadge && offerBadge !== '-') ? offerBadge : '';
        if(badgeVal.length > 20) badgeVal = badgeVal.substring(0, 20);
        modalOfferBadgeField.value = badgeVal;
    }
    syncBadgeSelectFromInput();
    if(modalOfferDiscountField) modalOfferDiscountField.value = offerDiscount || '';
    if(modalOfferDescField) modalOfferDescField.value = offerDescription || '';
    if(modalOfferStartDateField) modalOfferStartDateField.value = (startDate && startDate !== '-') ? startDate : '';
    if(modalOfferEndDateField) modalOfferEndDateField.value = (endDate && endDate !== '-') ? endDate : '';
    if(modalOfferStartTimeField) modalOfferStartTimeField.value = (startTime && startTime !== '-') ? startTime : '';
    if(modalOfferEndTimeField) modalOfferEndTimeField.value = (endTime && endTime !== '-') ? endTime : '';
    if(modalOfferStatusField) modalOfferStatusField.value = offerStatus || 'Active';
    
    var row = document.querySelector('tr[data-offer-id="' + offerId + '"]');
    var imgPreview = document.getElementById('modal_offer_image_preview');
    
    if(row && imgPreview) {
        var img = row.querySelector('img');
        if(img && img.src) {
            imgPreview.src = img.src;
        } else {
            imgPreview.src = OFFER_MODAL_PLACEHOLDER_IMG;
        }
    }
    
    updateCharCount();
    updateBadgeCharCount();
    syncBadgeSelectFromInput();
    setOfferModalMode(true);
    showOfferModal();
}

function handleOfferImageUpload(input) {
    if(input.files && input.files[0]) {
        var file = input.files[0];
        var allowedTypes = ['image/jpeg','image/png','image/gif','image/webp'];
        var maxSize = 10 * 1024 * 1024;
        
        if(allowedTypes.indexOf(file.type) === -1) {
            var typeMsg = 'Only JPG, PNG, GIF, and WEBP images are allowed.';
            if (window.MwModal && window.MwModal.alert) {
                window.MwModal.alert({ title: 'Offer Image', message: typeMsg });
            } else {
                alert(typeMsg);
            }
            input.value = '';
            processedOfferImageData = null;
            return;
        }
        
        if(file.size > maxSize) {
            var sizeMsg = 'Image size must be 10MB or less. The image will be automatically optimized to 250KB.';
            if (window.MwModal && window.MwModal.alert) {
                window.MwModal.alert({ title: 'Offer Image', message: sizeMsg });
            } else {
                alert(sizeMsg);
            }
            input.value = '';
            processedOfferImageData = null;
            return;
        }
        
        if (typeof ImageCropUpload !== 'undefined') {
            window.offerImageCropCallback = function(base64Data) {
                processedOfferImageData = base64Data;
                var previewDataUri = 'data:image/jpeg;base64,' + base64Data;
                var imgPreview = document.getElementById('modal_offer_image_preview');
                if(imgPreview) {
                    imgPreview.src = previewDataUri;
                    imgPreview.style.width = '100%';
                    imgPreview.style.height = '100%';
                    imgPreview.style.objectFit = 'cover';
                    imgPreview.style.display = 'block';
                    var wrapper = imgPreview.closest('.offer-image-preview-modal');
                    if(wrapper) wrapper.style.border = '2px solid #28a745';
                }
            };
            
            ImageCropUpload.open(file, {
                method: 'base64',
                title: 'Adjust & Crop Offer Banner (4:3 ratio)',
                aspectRatio: 4/3,
                cropWidth: 1200,
                cropHeight: 900,
                onSuccess: function(base64Data) {
                    if (window.offerImageCropCallback) window.offerImageCropCallback(base64Data);
                },
                onError: function(msg) {
                    var errMsg = msg || 'Error processing image. Please try again.';
                    if (window.MwModal && window.MwModal.alert) {
                        window.MwModal.alert({ title: 'Offer Image', message: errMsg });
                    } else {
                        alert(errMsg);
                    }
                    input.value = '';
                    processedOfferImageData = null;
                }
            });
            input.value = '';
        } else {
            alert('Image crop tool not available. Please refresh the page.');
        }
    }
}

function addOfferToForm() {
    var modalOfferTitleField = document.getElementById('modal_offer_title');
    var offerTitleValue = modalOfferTitleField ? modalOfferTitleField.value : '';
    
    if(!offerTitleValue) {
        alert('Please enter offer title.');
        return;
    }
    
    // Check if limit of 5 offers is reached (only for new offers, not edits)
    var modalOfferIdField = document.getElementById('modal_offer_id');
    var offerId = modalOfferIdField ? modalOfferIdField.value : '';
    var isEdit = (offerId && offerId !== '');
    
    if(!isEdit) {
        var offerRows = document.querySelectorAll('tr[data-offer-id]');
        if(offerRows.length >= MAX_OFFERS) {
            alert('You can only add up to ' + MAX_OFFERS + ' special offers. Please delete one before adding a new offer.');
            return;
        }
    }
    
    // Get all date and time fields
    var modalOfferStartDateField = document.getElementById('modal_offer_start_date');
    var startDate = modalOfferStartDateField ? modalOfferStartDateField.value : '';
    
    var modalOfferEndDateField = document.getElementById('modal_offer_end_date');
    var endDate = modalOfferEndDateField ? modalOfferEndDateField.value : '';
    
    var modalOfferStartTimeField = document.getElementById('modal_offer_start_time');
    var startTime = modalOfferStartTimeField ? modalOfferStartTimeField.value : '';
    
    var modalOfferEndTimeField = document.getElementById('modal_offer_end_time');
    var endTime = modalOfferEndTimeField ? modalOfferEndTimeField.value : '';
    
    function normalizeTimeForParse(t) {
        if(!t) return '';
        return t.length === 5 ? t + ':00' : t;
    }
    
    var hasStartDate = !!startDate;
    var hasStartTime = !!startTime;
    var hasEndDate = !!endDate;
    var hasEndTime = !!endTime;
    if(hasStartDate !== hasStartTime) {
        alert('Start Dt: enter both date and time, or leave both empty.');
        return;
    }
    if(hasEndDate !== hasEndTime) {
        alert('End Dt: enter both date and time, or leave both empty.');
        return;
    }
    if(hasStartDate && hasStartTime && (!hasEndDate || !hasEndTime)) {
        alert('End Dt: enter both date and time when Start Dt is set.');
        return;
    }
    if(hasEndDate && hasEndTime && (!hasStartDate || !hasStartTime)) {
        alert('Start Dt: enter both date and time when End Dt is set.');
        return;
    }
    if(hasStartDate && hasEndDate) {
        var startIso = startDate + 'T' + normalizeTimeForParse(startTime);
        var endIso = endDate + 'T' + normalizeTimeForParse(endTime);
        var startMs = Date.parse(startIso);
        var endMs = Date.parse(endIso);
        if(isNaN(startMs) || isNaN(endMs)) {
            alert('Start Dt or End Dt is not a valid date or time.');
            return;
        }
        if(endMs < startMs) {
            alert('End Dt must be on or after Start Dt.');
            return;
        }
    }
    
    var formData = new FormData();
    formData.append('offer', '1');
    var tempSlot = '1';
    formData.append('offer_title' + tempSlot, offerTitleValue);
    
    var modalOfferBadgeField = document.getElementById('modal_offer_badge');
    var badge = modalOfferBadgeField ? modalOfferBadgeField.value : '';
    if(badge) formData.append('offer_badge' + tempSlot, badge);
    
    var modalOfferDiscountField = document.getElementById('modal_offer_discount');
    var discount = modalOfferDiscountField ? modalOfferDiscountField.value : '';
    if(discount) formData.append('offer_discount' + tempSlot, discount);
    
    var modalOfferDescField = document.getElementById('modal_offer_description');
    var description = modalOfferDescField ? modalOfferDescField.value : '';
    if(description) formData.append('offer_desc' + tempSlot, description);
    
    if(startDate) formData.append('offer_start_date' + tempSlot, startDate);
    if(endDate) formData.append('offer_end_date' + tempSlot, endDate);
    if(startTime) formData.append('offer_start_time' + tempSlot, startTime);
    if(endTime) formData.append('offer_end_time' + tempSlot, endTime);
    
    var modalOfferStatusField = document.getElementById('modal_offer_status');
    var status = modalOfferStatusField ? modalOfferStatusField.value : 'Active';
    if(status) formData.append('offer_status' + tempSlot, status);
    
    if(isEdit) {
        formData.append('offer_id' + tempSlot, offerId);
        formData.append('offer_id', offerId);
    }
    
    if(processedOfferImageData) {
        formData.append('processed_offer_image_data' + tempSlot, processedOfferImageData);
    } else {
        var imageFileInput = document.getElementById('modal_offer_image');
        if(imageFileInput && imageFileInput.files[0]) {
            formData.append('offer_img' + tempSlot, imageFileInput.files[0]);
        }
    }
    
    var statusElement = document.getElementById('status_remove_img');
    if(statusElement) {
        statusElement.innerHTML = '<div class="alert alert-info">Saving offer...</div>';
    }
    
    var updateTableCallback = function() {
        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        })
        .then(response => response.json())
        .then(data => {
            if(data && data.success) {
                var statusEl = document.getElementById('status_remove_img');
                if(statusEl) {
                    statusEl.innerHTML = '<div class="alert alert-success">' + (data.message || 'Offer saved successfully!') + '</div>';
                }
                setTimeout(function(){ window.location.reload(); }, 800);
            } else {
                var statusEl = document.getElementById('status_remove_img');
                if(statusEl) {
                    statusEl.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Error saving offer.') + '</div>';
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            var statusEl = document.getElementById('status_remove_img');
            if(statusEl) {
                statusEl.innerHTML = '<div class="alert alert-danger">Error saving offer. Please try again.</div>';
            }
        });
    };
    
    if(processedOfferImageData) {
        updateTableCallback();
    } else {
        var imageFileInput = document.getElementById('modal_offer_image');
        if(imageFileInput && imageFileInput.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) { updateTableCallback(); };
            reader.readAsDataURL(imageFileInput.files[0]);
        } else {
            updateTableCallback();
        }
    }
    closeOfferModal();
}

function saveOffers() {
    $('#status_remove_img').html('<div class="alert alert-success">All offers saved successfully!</div>');
    setTimeout(function(){ $('#status_remove_img').html(''); }, 2000);
}

function removeOffer(offerId) {
    function doDelete() {
        var statusElement = document.getElementById('status_remove_img');
        if(statusElement) {
            statusElement.style.color = 'blue';
        }
        
        fetch('../../admin/js_request.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'offer_id=' + encodeURIComponent(offerId) + '&action=delete_offer'
        })
        .then(response => response.text())
        .then(data => {
            var statusEl = document.getElementById('status_remove_img');
            if(statusEl) {
                statusEl.innerHTML = data;
            }
            
            if(data.includes('success')){
                var row = document.querySelector('tr[data-offer-id="' + offerId + '"]');
                if(row) {
                    row.remove();
                }
                
                var tableBody = document.querySelector('.Product-ServicesTable tbody');
                var hasOffers = tableBody ? tableBody.querySelector('tr[data-offer-id]') : null;
                
                if(tableBody && !hasOffers) {
                    tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No special offers added yet. Click "Add Offer" to add.</td></tr>';
                }
                
                updateAddOfferButtonState();
                
                if(statusEl) {
                    statusEl.innerHTML = '<div class="alert alert-success">Offer removed successfully!</div>';
                    setTimeout(function(){ statusEl.innerHTML = ''; }, 2000);
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            var statusEl = document.getElementById('status_remove_img');
            if(statusEl) {
                statusEl.innerHTML = '<div class="alert alert-danger">Error deleting offer. Please try again.</div>';
            }
        });
    }
    if (window.MwModal && typeof window.MwModal.confirm === 'function') {
        window.MwModal.confirm({
            title: 'Remove offer?',
            message: 'Are you sure you want to remove this offer?',
            confirmText: 'Remove',
            cancelText: 'Cancel',
            confirmClass: 'mw-btn mw-btn-danger',
            onConfirm: doDelete
        });
    } else if (confirm('Are you sure you want to remove this offer?')) {
        doDelete();
    }
}

function closeOfferModal() {
    if (window.MwModal && typeof window.MwModal.close === 'function') {
        window.MwModal.close('offerModal');
    }
    var modalOfferIdField = document.getElementById('modal_offer_id');
    if(modalOfferIdField) modalOfferIdField.value = '';
    processedOfferImageData = null;
    currentOfferId = null;
}

(function() {
    var modalEl = document.getElementById('offerModal');
    if (!modalEl) return;
    modalEl.addEventListener('mw-modal:closed', function() {
        currentOfferId = null;
        processedOfferImageData = null;
    });
})();

</script>

<?php include '../includes/footer.php'; ?>
