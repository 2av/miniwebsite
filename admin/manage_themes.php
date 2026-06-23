<?php
require_once(__DIR__ . '/../app/config/database.php');
require('header.php');

function mw_theme_slug($value) {
    $slug = strtolower(trim((string)$value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim((string)$slug, '-');
    return $slug === '' ? 'theme' : $slug;
}

function mw_ensure_theme_table($connect) {
    $sql = "CREATE TABLE IF NOT EXISTS miniwebsite_theme_catalog (
        id INT AUTO_INCREMENT PRIMARY KEY,
        theme_number INT NOT NULL UNIQUE,
        theme_name VARCHAR(100) NOT NULL,
        css_value VARCHAR(120) NOT NULL,
        preview_image VARCHAR(255) DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    return mysqli_query($connect, $sql);
}

function mw_file_theme_numbers($theme_css_dir) {
    $numbers = [];
    $theme_files = glob($theme_css_dir . '/theme*.css');
    if (!is_array($theme_files)) {
        return $numbers;
    }
    foreach ($theme_files as $theme_file) {
        if (preg_match('/theme(\d+)\.css$/', $theme_file, $m)) {
            $theme_no = (int)$m[1];
            if ($theme_no > 0 && file_exists($theme_css_dir . '/layout' . $theme_no . '.css')) {
                $numbers[] = $theme_no;
            }
        }
    }
    $numbers = array_values(array_unique($numbers));
    sort($numbers, SORT_NUMERIC);
    return $numbers;
}

function mw_seed_themes_from_files($connect) {
    $theme_css_dir = __DIR__ . '/../theme/css';
    $numbers = mw_file_theme_numbers($theme_css_dir);
    foreach ($numbers as $theme_no) {
        $theme_no_esc = (int)$theme_no;
        $name = 'Theme ' . $theme_no_esc;
        $name_esc = mysqli_real_escape_string($connect, $name);
        $css = 'theme/css/theme' . $theme_no_esc . '.css';
        $css_esc = mysqli_real_escape_string($connect, $css);
        $img = 'template' . $theme_no_esc . '.png';
        $img_abs = __DIR__ . '/../assets/images/templates/' . $img;
        $img_val = file_exists($img_abs) ? "'" . mysqli_real_escape_string($connect, $img) . "'" : 'NULL';
        $insert = "INSERT IGNORE INTO miniwebsite_theme_catalog
            (theme_number, theme_name, css_value, preview_image, is_active, sort_order)
            VALUES ($theme_no_esc, '$name_esc', '$css_esc', $img_val, 1, $theme_no_esc)";
        mysqli_query($connect, $insert);
    }
}

function mw_migrate_legacy_css_values($connect) {
    $q = mysqli_query($connect, "SELECT id, theme_number, css_value FROM miniwebsite_theme_catalog");
    if (!$q) {
        return;
    }
    while ($row = mysqli_fetch_assoc($q)) {
        $id = (int)$row['id'];
        $theme_number = (int)$row['theme_number'];
        $css_value = trim((string)$row['css_value']);
        if ($theme_number <= 0) {
            continue;
        }
        $new_css = 'theme/css/theme' . $theme_number . '.css';
        if ($css_value !== $new_css) {
            $new_css_esc = mysqli_real_escape_string($connect, $new_css);
            mysqli_query($connect, "UPDATE miniwebsite_theme_catalog SET css_value = '$new_css_esc' WHERE id = $id");
        }
    }
}

function mw_pick_palette($theme_number) {
    $palettes = [
        ['#4f46e5', '#6366f1', '#22d3ee', '#0f172a', '#111827', '#cbd5e1'],
        ['#ff6b6b', '#feca57', '#a29bfe', '#130f1f', '#1e1830', '#b8b0d0'],
        ['#10b981', '#34d399', '#60a5fa', '#0b141a', '#112027', '#94a3b8'],
        ['#f97316', '#fb923c', '#f43f5e', '#1a1025', '#261437', '#c9b6dd'],
        ['#14b8a6', '#2dd4bf', '#8b5cf6', '#0e1a1b', '#132a2c', '#a9c7c9'],
        ['#eab308', '#f59e0b', '#ef4444', '#1f1b10', '#2b2516', '#d7cfb3'],
    ];
    $idx = $theme_number % count($palettes);
    return $palettes[$idx];
}

function mw_clone_with_palette($source_css, $theme_number) {
    [$primary, $secondary, $accent, $bg, $cardBg, $text] = mw_pick_palette($theme_number);

    $replacements = [
        '/(--mw-primary-color:\s*)#[0-9a-fA-F]{3,8}\s*;/',
        '/(--mw-secondary-color:\s*)#[0-9a-fA-F]{3,8}\s*;/',
        '/(--mw-accent-color:\s*)#[0-9a-fA-F]{3,8}\s*;/',
        '/(--mw-background-color:\s*)#[0-9a-fA-F]{3,8}\s*;/',
        '/(--mw-card-background:\s*)#[0-9a-fA-F]{3,8}\s*;/',
        '/(--mw-text-color:\s*)#[0-9a-fA-F]{3,8}\s*;/',
    ];
    $values = [$primary, $secondary, $accent, $bg, $cardBg, $text];

    $out = $source_css;
    for ($i = 0; $i < count($replacements); $i++) {
        $out = preg_replace($replacements[$i], '${1}' . $values[$i] . ';', $out, 1);
    }
    return $out;
}

function mw_create_theme_starter_files($theme_number, $clone_from_theme) {
    $theme_number = (int)$theme_number;
    $clone_from_theme = (int)$clone_from_theme;
    if ($theme_number <= 0) {
        return ['ok' => false, 'message' => 'Invalid theme number.'];
    }
    if ($clone_from_theme <= 0) {
        return ['ok' => false, 'message' => 'Clone source theme number must be greater than 0.'];
    }
    if ($clone_from_theme === $theme_number) {
        return ['ok' => false, 'message' => 'Clone source and new theme number cannot be same.'];
    }

    $theme_dir = __DIR__ . '/../theme/css';
    if (!is_dir($theme_dir) && !@mkdir($theme_dir, 0775, true)) {
        return ['ok' => false, 'message' => 'Unable to create theme/css directory.'];
    }

    $theme_file = $theme_dir . '/theme' . $theme_number . '.css';
    $layout_file = $theme_dir . '/layout' . $theme_number . '.css';

    if (file_exists($theme_file) || file_exists($layout_file)) {
        return ['ok' => false, 'message' => 'theme' . $theme_number . '.css or layout' . $theme_number . '.css already exists.'];
    }

    $source_theme_file = $theme_dir . '/theme' . $clone_from_theme . '.css';
    $source_layout_file = $theme_dir . '/layout' . $clone_from_theme . '.css';
    if (!file_exists($source_theme_file) || !file_exists($source_layout_file)) {
        return ['ok' => false, 'message' => 'Clone source theme files not found: theme' . $clone_from_theme . '.css/layout' . $clone_from_theme . '.css'];
    }

    $source_theme_css = @file_get_contents($source_theme_file);
    $source_layout_css = @file_get_contents($source_layout_file);
    if ($source_theme_css === false || $source_layout_css === false) {
        return ['ok' => false, 'message' => 'Unable to read clone source files.'];
    }

    $theme_css = mw_clone_with_palette($source_theme_css, $theme_number);
    $theme_css = preg_replace('/theme' . preg_quote((string)$clone_from_theme, '/') . '\.css/i', 'theme' . $theme_number . '.css', $theme_css);
    $layout_css = str_replace('layout' . $clone_from_theme . '.css', 'layout' . $theme_number . '.css', $source_layout_css);

    $theme_written = @file_put_contents($theme_file, $theme_css);
    $layout_written = @file_put_contents($layout_file, $layout_css);

    if ($theme_written === false || $layout_written === false) {
        return ['ok' => false, 'message' => 'Failed to create starter files. Check file permissions.'];
    }

    return ['ok' => true, 'message' => 'Cloned theme' . $clone_from_theme . ' into theme' . $theme_number . ' (layout preserved, colors adjusted).'];
}

function mw_theme_file_paths($theme_number) {
    $theme_number = (int)$theme_number;
    if ($theme_number <= 0) {
        return null;
    }
    $theme_dir = realpath(__DIR__ . '/../theme/css');
    if ($theme_dir === false) {
        return null;
    }
    $theme_file = $theme_dir . DIRECTORY_SEPARATOR . 'theme' . $theme_number . '.css';
    $layout_file = $theme_dir . DIRECTORY_SEPARATOR . 'layout' . $theme_number . '.css';
    if (!is_file($theme_file) || !is_file($layout_file)) {
        return null;
    }
    $theme_real = realpath($theme_file);
    $layout_real = realpath($layout_file);
    if ($theme_real === false || $layout_real === false) {
        return null;
    }
    if (strpos($theme_real, $theme_dir) !== 0 || strpos($layout_real, $theme_dir) !== 0) {
        return null;
    }
    return [
        'theme' => $theme_real,
        'layout' => $layout_real,
        'theme_web' => 'theme/css/theme' . $theme_number . '.css',
        'layout_web' => 'theme/css/layout' . $theme_number . '.css',
    ];
}

function mw_read_theme_css_files($theme_number) {
    $paths = mw_theme_file_paths($theme_number);
    if ($paths === null) {
        return null;
    }
    $theme_css = @file_get_contents($paths['theme']);
    $layout_css = @file_get_contents($paths['layout']);
    if ($theme_css === false || $layout_css === false) {
        return null;
    }
    return [
        'theme_css' => $theme_css,
        'layout_css' => $layout_css,
        'paths' => $paths,
    ];
}

function mw_save_theme_css_files($theme_number, $theme_css, $layout_css) {
    $paths = mw_theme_file_paths($theme_number);
    if ($paths === null) {
        return ['ok' => false, 'message' => 'Theme CSS files not found for theme ' . (int)$theme_number . '.'];
    }
    if (!is_writable($paths['theme']) || !is_writable($paths['layout'])) {
        return ['ok' => false, 'message' => 'CSS files are not writable. Check folder permissions for theme/css.'];
    }
    $theme_written = @file_put_contents($paths['theme'], (string)$theme_css, LOCK_EX);
    $layout_written = @file_put_contents($paths['layout'], (string)$layout_css, LOCK_EX);
    if ($theme_written === false || $layout_written === false) {
        return ['ok' => false, 'message' => 'Failed to save one or more CSS files.'];
    }
    return ['ok' => true, 'message' => 'Theme and layout CSS saved for theme ' . (int)$theme_number . '.'];
}

$flash_success = '';
$flash_error = '';

if (!mw_ensure_theme_table($connect)) {
    $flash_error = 'Failed to initialize theme catalog table: ' . mysqli_error($connect);
} else {
    mw_seed_themes_from_files($connect);
    mw_migrate_legacy_css_values($connect);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $flash_error === '') {
    $action = trim((string)$_POST['action']);

    if ($action === 'create_theme_files') {
        $theme_number = isset($_POST['theme_number']) ? (int)$_POST['theme_number'] : 0;
        $clone_from_theme = isset($_POST['clone_from_theme']) ? (int)$_POST['clone_from_theme'] : 1;
        $create_result = mw_create_theme_starter_files($theme_number, $clone_from_theme);
        if ($create_result['ok']) {
            mw_seed_themes_from_files($connect);
            mw_migrate_legacy_css_values($connect);
            $flash_success = $create_result['message'];
        } else {
            $flash_error = $create_result['message'];
        }
    } elseif ($action === 'create_full_theme') {
        $theme_number = isset($_POST['theme_number']) ? (int)$_POST['theme_number'] : 0;
        $clone_from_theme = isset($_POST['clone_from_theme']) ? (int)$_POST['clone_from_theme'] : 1;
        $theme_name = isset($_POST['theme_name']) ? trim((string)$_POST['theme_name']) : '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $sort_order = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : $theme_number;

        if ($theme_number <= 0) {
            $flash_error = 'Theme number must be greater than 0.';
        } elseif ($theme_name === '') {
            $flash_error = 'Theme name is required.';
        } elseif (mw_theme_file_paths($theme_number) !== null) {
            $flash_error = 'Theme files already exist for theme ' . $theme_number . '.';
        } else {
            $create_result = mw_create_theme_starter_files($theme_number, $clone_from_theme);
            if (!$create_result['ok']) {
                $flash_error = $create_result['message'];
            } else {
                $css_value = 'theme/css/theme' . $theme_number . '.css';
                $theme_name_esc = mysqli_real_escape_string($connect, $theme_name);
                $css_value_esc = mysqli_real_escape_string($connect, $css_value);
                $preview_image_sql = 'NULL';

                if (isset($_FILES['preview_image']) && isset($_FILES['preview_image']['name']) && $_FILES['preview_image']['name'] !== '') {
                    $file_name = $_FILES['preview_image']['name'];
                    $tmp_name = $_FILES['preview_image']['tmp_name'];
                    $file_size = (int)$_FILES['preview_image']['size'];
                    $file_error = (int)$_FILES['preview_image']['error'];
                    $ext = strtolower((string)pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed_ext = ['png', 'jpg', 'jpeg', 'webp'];

                    if ($file_error !== UPLOAD_ERR_OK) {
                        $flash_error = 'Image upload failed. Error code: ' . $file_error;
                    } elseif (!in_array($ext, $allowed_ext, true)) {
                        $flash_error = 'Only PNG, JPG, JPEG, WEBP images are allowed.';
                    } elseif ($file_size > (3 * 1024 * 1024)) {
                        $flash_error = 'Image size must be 3MB or smaller.';
                    } else {
                        $upload_dir = __DIR__ . '/../assets/images/templates';
                        if (!is_dir($upload_dir)) {
                            @mkdir($upload_dir, 0775, true);
                        }
                        $new_name = 'template_theme_' . $theme_number . '_' . time() . '_' . mw_theme_slug($theme_name) . '.' . $ext;
                        $dest_path = $upload_dir . '/' . $new_name;
                        if (move_uploaded_file($tmp_name, $dest_path)) {
                            $preview_image_sql = "'" . mysqli_real_escape_string($connect, $new_name) . "'";
                        } else {
                            $flash_error = 'Failed to move uploaded image.';
                        }
                    }
                }

                if ($flash_error === '') {
                    $check = mysqli_query($connect, "SELECT id FROM miniwebsite_theme_catalog WHERE theme_number = $theme_number LIMIT 1");
                    $theme_id = 0;
                    $saved = false;
                    if ($check && mysqli_num_rows($check) > 0) {
                        $id_row = mysqli_fetch_assoc($check);
                        $theme_id = (int)$id_row['id'];
                        $update_sql = "UPDATE miniwebsite_theme_catalog SET
                            theme_name = '$theme_name_esc',
                            css_value = '$css_value_esc',
                            preview_image = $preview_image_sql,
                            is_active = $is_active,
                            sort_order = $sort_order
                            WHERE theme_number = $theme_number";
                        $saved = mysqli_query($connect, $update_sql);
                    } else {
                        $insert_sql = "INSERT INTO miniwebsite_theme_catalog
                            (theme_number, theme_name, css_value, preview_image, is_active, sort_order)
                            VALUES ($theme_number, '$theme_name_esc', '$css_value_esc', $preview_image_sql, $is_active, $sort_order)";
                        $saved = mysqli_query($connect, $insert_sql);
                        $theme_id = (int)mysqli_insert_id($connect);
                    }

                    if ($saved && $theme_id > 0) {
                        header('Location: manage_themes.php?edit=' . $theme_id . '&created=1');
                        exit;
                    }
                    $flash_error = 'Theme files created but catalog save failed: ' . mysqli_error($connect);
                }
            }
        }
    } elseif ($action === 'save_theme') {
        $theme_number = isset($_POST['theme_number']) ? (int)$_POST['theme_number'] : 0;
        $theme_name = isset($_POST['theme_name']) ? trim((string)$_POST['theme_name']) : '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $sort_order = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : $theme_number;

        if ($theme_number <= 0) {
            $flash_error = 'Theme number must be greater than 0.';
        } elseif ($theme_name === '') {
            $flash_error = 'Theme name is required.';
        } else {
            $theme_css_path = __DIR__ . '/../theme/css/theme' . $theme_number . '.css';
            $layout_css_path = __DIR__ . '/../theme/css/layout' . $theme_number . '.css';
            if (!file_exists($theme_css_path) || !file_exists($layout_css_path)) {
                $flash_error = 'theme' . $theme_number . '.css and layout' . $theme_number . '.css must exist in theme/css before adding.';
            } else {
                $css_value = 'theme/css/theme' . $theme_number . '.css';
                $theme_name_esc = mysqli_real_escape_string($connect, $theme_name);
                $css_value_esc = mysqli_real_escape_string($connect, $css_value);
                $preview_image_sql = "preview_image";

                if (isset($_POST['remove_preview']) && $_POST['remove_preview'] === '1') {
                    $preview_image_sql = "NULL";
                } else {
                    $preview_image_sql = "preview_image";
                }

                if (isset($_FILES['preview_image']) && isset($_FILES['preview_image']['name']) && $_FILES['preview_image']['name'] !== '') {
                    $file_name = $_FILES['preview_image']['name'];
                    $tmp_name = $_FILES['preview_image']['tmp_name'];
                    $file_size = (int)$_FILES['preview_image']['size'];
                    $file_error = (int)$_FILES['preview_image']['error'];
                    $ext = strtolower((string)pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed_ext = ['png', 'jpg', 'jpeg', 'webp'];

                    if ($file_error !== UPLOAD_ERR_OK) {
                        $flash_error = 'Image upload failed. Error code: ' . $file_error;
                    } elseif (!in_array($ext, $allowed_ext, true)) {
                        $flash_error = 'Only PNG, JPG, JPEG, WEBP images are allowed.';
                    } elseif ($file_size > (3 * 1024 * 1024)) {
                        $flash_error = 'Image size must be 3MB or smaller.';
                    } else {
                        $upload_dir = __DIR__ . '/../assets/images/templates';
                        if (!is_dir($upload_dir)) {
                            @mkdir($upload_dir, 0775, true);
                        }
                        $new_name = 'template_theme_' . $theme_number . '_' . time() . '_' . mw_theme_slug($theme_name) . '.' . $ext;
                        $dest_path = $upload_dir . '/' . $new_name;
                        if (move_uploaded_file($tmp_name, $dest_path)) {
                            $preview_image_sql = "'" . mysqli_real_escape_string($connect, $new_name) . "'";
                        } else {
                            $flash_error = 'Failed to move uploaded image.';
                        }
                    }
                }

                if ($flash_error === '') {
                    $check = mysqli_query($connect, "SELECT id FROM miniwebsite_theme_catalog WHERE theme_number = $theme_number LIMIT 1");
                    if ($check && mysqli_num_rows($check) > 0) {
                        $update_sql = "UPDATE miniwebsite_theme_catalog SET
                            theme_name = '$theme_name_esc',
                            css_value = '$css_value_esc',
                            preview_image = $preview_image_sql,
                            is_active = $is_active,
                            sort_order = $sort_order
                            WHERE theme_number = $theme_number";
                        if (mysqli_query($connect, $update_sql)) {
                            $flash_success = 'Theme ' . $theme_number . ' updated successfully.';
                        } else {
                            $flash_error = 'Failed to update theme: ' . mysqli_error($connect);
                        }
                    } else {
                        if ($preview_image_sql === 'preview_image') {
                            $preview_image_sql = 'NULL';
                        }
                        $insert_sql = "INSERT INTO miniwebsite_theme_catalog
                            (theme_number, theme_name, css_value, preview_image, is_active, sort_order)
                            VALUES ($theme_number, '$theme_name_esc', '$css_value_esc', $preview_image_sql, $is_active, $sort_order)";
                        if (mysqli_query($connect, $insert_sql)) {
                            $flash_success = 'Theme ' . $theme_number . ' added successfully.';
                        } else {
                            $flash_error = 'Failed to add theme: ' . mysqli_error($connect);
                        }
                    }
                }
            }
        }
    } elseif ($action === 'save_theme_css') {
        $theme_number = isset($_POST['theme_number']) ? (int)$_POST['theme_number'] : 0;
        $theme_css = isset($_POST['theme_css_content']) ? (string)$_POST['theme_css_content'] : '';
        $layout_css = isset($_POST['layout_css_content']) ? (string)$_POST['layout_css_content'] : '';
        $active_pane = (isset($_POST['active_pane']) && $_POST['active_pane'] === 'layout') ? 'layout' : 'theme';
        if ($theme_number <= 0) {
            $flash_error = 'Invalid theme number for CSS save.';
        } else {
            $save_css_result = mw_save_theme_css_files($theme_number, $theme_css, $layout_css);
            if ($save_css_result['ok']) {
                header('Location: manage_themes.php?css=' . $theme_number . '&pane=' . urlencode($active_pane) . '&saved_css=1');
                exit;
            } else {
                $flash_error = $save_css_result['message'];
            }
        }
    } elseif ($action === 'toggle_active') {
        $theme_id = isset($_POST['theme_id']) ? (int)$_POST['theme_id'] : 0;
        $new_status = isset($_POST['new_status']) ? (int)$_POST['new_status'] : 0;
        if ($theme_id > 0) {
            if (mysqli_query($connect, "UPDATE miniwebsite_theme_catalog SET is_active = $new_status WHERE id = $theme_id")) {
                $flash_success = 'Theme status updated.';
            } else {
                $flash_error = 'Failed to update status: ' . mysqli_error($connect);
            }
        } else {
            $flash_error = 'Invalid theme id.';
        }
    }
}

$themes = [];
$theme_q = mysqli_query($connect, "SELECT * FROM miniwebsite_theme_catalog ORDER BY sort_order ASC, theme_number ASC");
if ($theme_q) {
    while ($row = mysqli_fetch_assoc($theme_q)) {
        $themes[] = $row;
    }
}

$edit_theme = null;
if (isset($_GET['edit']) && (int)$_GET['edit'] > 0) {
    $edit_id = (int)$_GET['edit'];
    foreach ($themes as $t) {
        if ((int)$t['id'] === $edit_id) {
            $edit_theme = $t;
            break;
        }
    }
}

$default_theme_number = $edit_theme ? (int)$edit_theme['theme_number'] : 3;
$default_theme_name = $edit_theme ? (string)$edit_theme['theme_name'] : 'Theme 3';
$default_sort_order = $edit_theme ? (int)$edit_theme['sort_order'] : $default_theme_number;
$default_active = $edit_theme ? ((int)$edit_theme['is_active'] === 1) : true;

$css_editor = null;
$css_edit_number = $edit_theme ? (int)$edit_theme['theme_number'] : 0;
if (isset($_GET['css']) && (int)$_GET['css'] > 0) {
    $css_edit_number = (int)$_GET['css'];
}
$css_editor_pane = 'theme';
if (isset($_GET['pane']) && $_GET['pane'] === 'layout') {
    $css_editor_pane = 'layout';
}
$css_only_mode = isset($_GET['css']) && (int)$_GET['css'] > 0;
if ($css_edit_number > 0) {
    $css_editor = mw_read_theme_css_files($css_edit_number);
}
if (isset($_GET['saved_css']) && $_GET['saved_css'] === '1') {
    $flash_success = 'Theme and layout CSS saved successfully.';
}

$theme_css_dir = __DIR__ . '/../theme/css';
$existing_theme_numbers = mw_file_theme_numbers($theme_css_dir);
$next_theme_number = empty($existing_theme_numbers) ? 1 : (max($existing_theme_numbers) + 1);
$create_theme_number = $next_theme_number;
$default_clone_from = !empty($existing_theme_numbers) ? (int)$existing_theme_numbers[0] : 1;
$open_create_modal = ($flash_error !== '' && isset($_POST['action']) && $_POST['action'] === 'create_full_theme');
if (isset($_GET['created']) && $_GET['created'] === '1') {
    $flash_success = 'New theme created successfully. You can edit settings and CSS below.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theme Management - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/dracula.min.css">
    <style>
        .theme-admin-wrap { padding: 24px; }
        .theme-admin-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.08); margin-bottom: 20px; }
        .theme-admin-card .card-body { padding: 20px; }
        .theme-preview { width: 88px; height: 120px; border-radius: 8px; object-fit: cover; background: #f0f2f5; border: 1px solid #d9dde2; }
        .theme-preview-placeholder { display:flex; align-items:center; justify-content:center; font-size:12px; color:#6c757d; text-align:center; padding:6px; }
        .badge-active { background:#d4edda; color:#155724; padding:4px 8px; border-radius:999px; font-size:12px; }
        .badge-inactive { background:#f8d7da; color:#721c24; padding:4px 8px; border-radius:999px; font-size:12px; }
        .table td, .table th { vertical-align: middle; }
        .css-editor-tabs { display:flex; gap:8px; margin-bottom:12px; flex-wrap:wrap; }
        .css-editor-tabs .btn { min-width: 140px; }
        .css-editor-pane { display:none; }
        .css-editor-pane.active { display:block; }
        .css-editor-wrap { border:1px solid #d9dde2; border-radius:8px; overflow:hidden; }
        .css-editor-wrap .CodeMirror { height: 520px; font-size: 13px; }
        .css-file-label { font-family: monospace; font-size: 12px; color:#6c757d; margin-bottom:6px; }
        .theme-form-grid .form-group { margin-bottom: 1rem; }
        .theme-form-grid label { font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; }
        .theme-form-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 0.5rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }
        .theme-form-actions .btn-group { display: flex; flex-wrap: wrap; gap: 8px; }
        .theme-create-section {
            margin-top: 1.25rem;
            padding-top: 1.25rem;
            border-top: 1px solid #e9ecef;
        }
        .theme-create-section h6 { margin-bottom: 1rem; font-weight: 600; }
        .theme-create-row { align-items: flex-end; }
        .theme-create-actions { display: flex; flex-direction: column; gap: 0.35rem; }
        @media (min-width: 768px) {
            .theme-create-actions { padding-bottom: 2px; }
        }
        .themes-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 1rem;
        }
        .themes-card-header h5 { margin: 0; }
        .btn-add-theme {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        .create-step-label {
            font-size: 0.8125rem;
            color: #6c757d;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
<div class="theme-admin-wrap<?php echo $css_only_mode ? ' theme-admin-wrap--css-only' : ''; ?>">
    <h2 class="mb-3"><?php echo $css_only_mode ? 'Edit Theme CSS' : 'Theme Management'; ?></h2>
    <p class="text-muted"><?php echo $css_only_mode ? 'Edit theme and layout CSS files, then save.' : 'Manage theme visibility, preview images, and edit theme/layout CSS directly from admin panel.'; ?></p>

    <?php if ($flash_success !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($flash_success); ?></div>
    <?php endif; ?>
    <?php if ($flash_error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($flash_error); ?></div>
    <?php endif; ?>

    <?php if (!$css_only_mode): ?>
    <div class="theme-admin-card">
        <div class="card-body">
            <h5><?php echo $edit_theme ? 'Edit Theme #' . (int)$edit_theme['theme_number'] : 'Add / Update Theme'; ?></h5>
            <form method="POST" enctype="multipart/form-data" class="mt-3 theme-form-grid" id="themeMetaForm">
                <input type="hidden" name="action" value="save_theme">
                <div class="form-row">
                    <div class="form-group col-md-2 col-6">
                        <label for="theme_number">Theme Number</label>
                        <input type="number" min="1" class="form-control" id="theme_number" name="theme_number" value="<?php echo (int)$default_theme_number; ?>" required>
                    </div>
                    <div class="form-group col-md-5 col-6">
                        <label for="theme_name">Theme Name</label>
                        <input type="text" class="form-control" id="theme_name" name="theme_name" value="<?php echo htmlspecialchars($default_theme_name); ?>" required>
                    </div>
                    <div class="form-group col-md-2 col-6">
                        <label for="sort_order">Sort Order</label>
                        <input type="number" class="form-control" id="sort_order" name="sort_order" value="<?php echo (int)$default_sort_order; ?>">
                    </div>
                    <div class="form-group col-md-3 col-6">
                        <label for="preview_image">Preview Image</label>
                        <input type="file" class="form-control-file" id="preview_image" name="preview_image" accept=".png,.jpg,.jpeg,.webp">
                        <small class="form-text text-muted">PNG/JPG/WEBP, max 3MB</small>
                    </div>
                </div>
                <div class="theme-form-actions">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?php echo $default_active ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">Visible on user page (Active)</label>
                    </div>
                    <?php if ($edit_theme && !empty($edit_theme['preview_image'])): ?>
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="remove_preview" value="1" id="remove_preview">
                            <label class="form-check-label" for="remove_preview">Remove current preview image</label>
                        </div>
                    <?php endif; ?>
                    <div class="btn-group ml-md-auto">
                        <button type="submit" class="btn btn-primary">Save Theme Settings</button>
                        <?php if ($edit_theme): ?>
                            <a href="manage_themes.php?css=<?php echo (int)$edit_theme['theme_number']; ?>&pane=theme" class="btn btn-outline-dark" target="_blank" rel="noopener noreferrer">Theme CSS</a>
                            <a href="manage_themes.php?css=<?php echo (int)$edit_theme['theme_number']; ?>&pane=layout" class="btn btn-outline-secondary" target="_blank" rel="noopener noreferrer">Layout CSS</a>
                            <a href="manage_themes.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($css_editor !== null): ?>
    <div class="theme-admin-card" id="cssEditorCard">
        <div class="card-body">
            <h5 class="mb-2">Edit CSS — Theme <?php echo (int)$css_edit_number; ?></h5>
            <p class="text-muted mb-3">Editing <code>theme<?php echo (int)$css_edit_number; ?>.css</code> and <code>layout<?php echo (int)$css_edit_number; ?>.css</code></p>
            <form method="POST" id="themeCssForm">
                <input type="hidden" name="action" value="save_theme_css">
                <input type="hidden" name="theme_number" value="<?php echo (int)$css_edit_number; ?>">
                <input type="hidden" name="active_pane" id="active_pane" value="<?php echo htmlspecialchars($css_editor_pane); ?>">
                <div class="css-editor-tabs">
                    <button type="button" class="btn css-tab-btn <?php echo $css_editor_pane === 'theme' ? 'btn-primary active' : 'btn-outline-primary'; ?>" data-target="theme-pane" data-pane="theme">Theme CSS</button>
                    <button type="button" class="btn css-tab-btn <?php echo $css_editor_pane === 'layout' ? 'btn-primary active' : 'btn-outline-primary'; ?>" data-target="layout-pane" data-pane="layout">Layout CSS</button>
                </div>
                <div class="css-editor-pane <?php echo $css_editor_pane === 'theme' ? 'active' : ''; ?>" id="theme-pane">
                    <div class="css-file-label"><?php echo htmlspecialchars($css_editor['paths']['theme_web']); ?></div>
                    <div class="css-editor-wrap">
                        <textarea name="theme_css_content" id="theme_css_content"><?php echo htmlspecialchars($css_editor['theme_css']); ?></textarea>
                    </div>
                </div>
                <div class="css-editor-pane <?php echo $css_editor_pane === 'layout' ? 'active' : ''; ?>" id="layout-pane">
                    <div class="css-file-label"><?php echo htmlspecialchars($css_editor['paths']['layout_web']); ?></div>
                    <div class="css-editor-wrap">
                        <textarea name="layout_css_content" id="layout_css_content"><?php echo htmlspecialchars($css_editor['layout_css']); ?></textarea>
                    </div>
                </div>
                <div class="mt-3 text-right">
                    <?php if ($css_only_mode): ?>
                        <button type="button" class="btn btn-secondary" onclick="window.close();">Close Tab</button>
                    <?php else: ?>
                        <a href="manage_themes.php" class="btn btn-secondary">Back</a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-success">Save CSS Files</button>
                </div>
            </form>
        </div>
    </div>
    <?php elseif ($css_edit_number > 0 && $css_only_mode): ?>
    <div class="alert alert-warning">CSS files not found for theme <?php echo (int)$css_edit_number; ?>. Create theme files from Theme Management first.</div>
    <?php endif; ?>

    <?php if (!$css_only_mode): ?>
    <div class="theme-admin-card">
        <div class="card-body">
            <div class="themes-card-header">
                <h5>Configured Themes</h5>
                <button type="button" class="btn btn-primary btn-add-theme" data-toggle="modal" data-target="#createThemeModal" title="Add new theme" aria-label="Add new theme">
                    <i class="fa fa-plus"></i>
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Preview</th>
                        <th>Name</th>
                        <th>DB CSS</th>
                        <th>Visibility</th>
                        <th>Sort</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($themes)): ?>
                        <tr><td colspan="7" class="text-center text-muted">No themes configured yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($themes as $theme): ?>
                            <?php
                            $img = trim((string)$theme['preview_image']);
                            $img_abs = __DIR__ . '/../assets/images/templates/' . $img;
                            $img_web = '../assets/images/templates/' . $img;
                            $has_img = ($img !== '' && file_exists($img_abs));
                            $is_active = ((int)$theme['is_active'] === 1);
                            ?>
                            <tr>
                                <td><?php echo (int)$theme['theme_number']; ?></td>
                                <td>
                                    <?php if ($has_img): ?>
                                        <img src="<?php echo htmlspecialchars($img_web); ?>" alt="Theme preview" class="theme-preview">
                                    <?php else: ?>
                                        <div class="theme-preview theme-preview-placeholder">No image</div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars((string)$theme['theme_name']); ?></td>
                                <td><code><?php echo htmlspecialchars((string)$theme['css_value']); ?></code></td>
                                <td>
                                    <span class="<?php echo $is_active ? 'badge-active' : 'badge-inactive'; ?>">
                                        <?php echo $is_active ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td><?php echo (int)$theme['sort_order']; ?></td>
                                <td>
                                    <a href="manage_themes.php?edit=<?php echo (int)$theme['id']; ?>" class="btn btn-sm btn-info">Settings</a>
                                    <a href="manage_themes.php?css=<?php echo (int)$theme['theme_number']; ?>&pane=theme" class="btn btn-sm btn-dark" target="_blank" rel="noopener noreferrer">Theme CSS</a>
                                    <a href="manage_themes.php?css=<?php echo (int)$theme['theme_number']; ?>&pane=layout" class="btn btn-sm btn-secondary" target="_blank" rel="noopener noreferrer">Layout CSS</a>
                                    <form method="POST" style="display:inline-block">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="theme_id" value="<?php echo (int)$theme['id']; ?>">
                                        <input type="hidden" name="new_status" value="<?php echo $is_active ? 0 : 1; ?>">
                                        <button type="submit" class="btn btn-sm <?php echo $is_active ? 'btn-warning' : 'btn-success'; ?>">
                                            <?php echo $is_active ? 'Set Inactive' : 'Set Active'; ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$css_only_mode): ?>
    <div class="modal fade" id="createThemeModal" tabindex="-1" role="dialog" aria-labelledby="createThemeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createThemeModalLabel">Create New Theme</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="createThemeForm">
                    <input type="hidden" name="action" value="create_full_theme">
                    <div class="modal-body">
                        <div class="create-step" id="createStep1">
                            <p class="create-step-label">Step 1 of 2 — Choose theme number and clone source</p>
                            <div class="form-group">
                                <label for="modal_theme_number">New Theme Number</label>
                                <input type="number" min="1" class="form-control" id="modal_theme_number" name="theme_number" value="<?php echo (int)$create_theme_number; ?>" required>
                                <small class="form-text text-muted">Suggested next available: <?php echo (int)$create_theme_number; ?></small>
                            </div>
                            <div class="form-group mb-0">
                                <label for="modal_clone_from">Clone From Theme</label>
                                <select class="form-control" id="modal_clone_from" name="clone_from_theme" required>
                                    <?php if (empty($existing_theme_numbers)): ?>
                                        <option value="1">Theme 1</option>
                                    <?php else: ?>
                                        <?php foreach ($existing_theme_numbers as $num): ?>
                                            <option value="<?php echo (int)$num; ?>"<?php echo ((int)$num === (int)$default_clone_from) ? ' selected' : ''; ?>>Theme <?php echo (int)$num; ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <small class="form-text text-muted">Clones theme + layout files, then adjusts color tokens.</small>
                            </div>
                        </div>
                        <div class="create-step d-none" id="createStep2">
                            <p class="create-step-label">Step 2 of 2 — Theme details</p>
                            <div class="form-group">
                                <label for="modal_theme_name">Theme Name</label>
                                <input type="text" class="form-control" id="modal_theme_name" name="theme_name" value="Theme <?php echo (int)$create_theme_number; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="modal_sort_order">Sort Order</label>
                                <input type="number" class="form-control" id="modal_sort_order" name="sort_order" value="<?php echo (int)$create_theme_number; ?>">
                            </div>
                            <div class="form-group">
                                <label for="modal_preview_image">Preview Image (optional)</label>
                                <input type="file" class="form-control-file" id="modal_preview_image" name="preview_image" accept=".png,.jpg,.jpeg,.webp">
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="modal_is_active" checked>
                                <label class="form-check-label" for="modal_is_active">Visible on user page (Active)</label>
                            </div>
                        </div>
                        <div id="createStepError" class="alert alert-danger d-none mt-3 mb-0"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-outline-secondary d-none" id="createStepBack">Back</button>
                        <button type="button" class="btn btn-primary" id="createStepNext">Next</button>
                        <button type="submit" class="btn btn-success d-none" id="createStepSubmit">Create Theme</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
<script>
(function () {
    var existingThemeNumbers = <?php echo json_encode(array_values($existing_theme_numbers)); ?>;

    function themeFilesExist(num) {
        return existingThemeNumbers.indexOf(parseInt(num, 10)) !== -1;
    }

    var createModal = document.getElementById('createThemeModal');
    var createStep1 = document.getElementById('createStep1');
    var createStep2 = document.getElementById('createStep2');
    var createStepBack = document.getElementById('createStepBack');
    var createStepNext = document.getElementById('createStepNext');
    var createStepSubmit = document.getElementById('createStepSubmit');
    var createStepError = document.getElementById('createStepError');
    var modalThemeNumber = document.getElementById('modal_theme_number');
    var modalThemeName = document.getElementById('modal_theme_name');
    var modalSortOrder = document.getElementById('modal_sort_order');

    function showCreateStep(step) {
        if (!createStep1 || !createStep2) return;
        var isStep1 = step === 1;
        createStep1.classList.toggle('d-none', !isStep1);
        createStep2.classList.toggle('d-none', isStep1);
        if (createStepBack) createStepBack.classList.toggle('d-none', isStep1);
        if (createStepNext) createStepNext.classList.toggle('d-none', !isStep1);
        if (createStepSubmit) createStepSubmit.classList.toggle('d-none', isStep1);
        if (createStepError) createStepError.classList.add('d-none');
    }

    function resetCreateModal() {
        showCreateStep(1);
        if (createStepError) {
            createStepError.classList.add('d-none');
            createStepError.textContent = '';
        }
    }

    if (createStepNext) {
        createStepNext.addEventListener('click', function () {
            var n = parseInt(modalThemeNumber ? modalThemeNumber.value : '0', 10);
            if (!n || n < 1) {
                if (createStepError) {
                    createStepError.textContent = 'Please enter a valid theme number.';
                    createStepError.classList.remove('d-none');
                }
                return;
            }
            if (themeFilesExist(n)) {
                if (createStepError) {
                    createStepError.textContent = 'Theme files already exist for theme ' + n + '. Choose another number.';
                    createStepError.classList.remove('d-none');
                }
                return;
            }
            if (modalThemeName && !modalThemeName.value.trim()) {
                modalThemeName.value = 'Theme ' + n;
            }
            if (modalSortOrder && !modalSortOrder.value) {
                modalSortOrder.value = String(n);
            }
            showCreateStep(2);
        });
    }

    if (createStepBack) {
        createStepBack.addEventListener('click', function () {
            showCreateStep(1);
        });
    }

    if (createModal && typeof jQuery !== 'undefined') {
        jQuery(createModal).on('hidden.bs.modal', resetCreateModal);
        <?php if ($open_create_modal): ?>
        jQuery(createModal).modal('show');
        <?php endif; ?>
    }

    var themeTa = document.getElementById('theme_css_content');
    var layoutTa = document.getElementById('layout_css_content');
    if (!themeTa || !layoutTa) return;

    var cmOpts = {
        lineNumbers: true,
        mode: 'text/css',
        theme: 'dracula',
        indentUnit: 4,
        tabSize: 4,
        lineWrapping: true
    };
    var themeCm = CodeMirror.fromTextArea(themeTa, cmOpts);
    var layoutCm = CodeMirror.fromTextArea(layoutTa, cmOpts);
    var activePaneInput = document.getElementById('active_pane');
    var initialPane = activePaneInput ? activePaneInput.value : 'theme';

    function activateCssPane(target, paneName) {
        document.querySelectorAll('.css-tab-btn').forEach(function (b) {
            b.classList.remove('btn-primary', 'active');
            b.classList.add('btn-outline-primary');
        });
        document.querySelectorAll('.css-editor-pane').forEach(function (pane) {
            pane.classList.remove('active');
        });
        var btn = document.querySelector('.css-tab-btn[data-target="' + target + '"]');
        if (btn) {
            btn.classList.add('btn-primary', 'active');
            btn.classList.remove('btn-outline-primary');
        }
        var pane = document.getElementById(target);
        if (pane) pane.classList.add('active');
        if (activePaneInput && paneName) activePaneInput.value = paneName;
        setTimeout(function () {
            if (target === 'theme-pane') themeCm.refresh();
            if (target === 'layout-pane') layoutCm.refresh();
        }, 10);
    }

    document.querySelectorAll('.css-tab-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            activateCssPane(btn.getAttribute('data-target'), btn.getAttribute('data-pane'));
        });
    });

    if (initialPane === 'layout') {
        activateCssPane('layout-pane', 'layout');
    } else {
        setTimeout(function () { themeCm.refresh(); }, 10);
    }

    var cssForm = document.getElementById('themeCssForm');
    if (cssForm) {
        cssForm.addEventListener('submit', function () {
            themeTa.value = themeCm.getValue();
            layoutTa.value = layoutCm.getValue();
        });
    }
})();
</script>
</body>
</html>
