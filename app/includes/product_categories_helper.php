<?php
/**
 * Frontend helpers for product_categories (flat + legacy hierarchical).
 */

function productCategoriesIsFlatSchema($connect) {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $r = @mysqli_query($connect, "SHOW COLUMNS FROM product_categories LIKE 'business_category'");
    $cache = ($r && mysqli_num_rows($r) > 0);
    return $cache;
}

/**
 * Render <option> / <optgroup> for business category selects (primary / secondary).
 */
function renderBusinessCategorySelectOptions($connect, $selected_id = null, $user_id = 0) {
    $selected_id = $selected_id ? (int) $selected_id : null;

    if (productCategoriesIsFlatSchema($connect)) {
        $sql = "SELECT MIN(id) AS id, business_profile_type, business_heading, business_category, business_category_slug,
                       MIN(directory_priority) AS directory_priority
                FROM product_categories
                WHERE is_active = 1
                GROUP BY business_category_slug, business_heading, business_category, business_profile_type
                ORDER BY MIN(directory_priority) ASC, business_heading ASC, business_category ASC";
        $result = mysqli_query($connect, $sql);
        if ($result) {
            $current_group = null;
            while ($row = mysqli_fetch_assoc($result)) {
                $heading = trim((string) ($row['business_heading'] ?? ''));
                $profile = trim((string) ($row['business_profile_type'] ?? ''));
                $group_label = $heading !== '' ? $heading : ($profile !== '' ? $profile : 'Categories');
                if ($profile !== '' && $heading !== '' && strcasecmp($profile, $heading) !== 0) {
                    $group_label = $profile . ' — ' . $heading;
                }

                if ($group_label !== $current_group) {
                    if ($current_group !== null) {
                        echo '</optgroup>';
                    }
                    echo '<optgroup label="' . htmlspecialchars($group_label) . '">';
                    $current_group = $group_label;
                }

                $sel = ($selected_id !== null && $selected_id === (int) $row['id']) ? ' selected' : '';
                echo '<option value="' . (int) $row['id'] . '"' . $sel . '>'
                    . htmlspecialchars($row['business_category']) . '</option>';
            }
            if ($current_group !== null) {
                echo '</optgroup>';
            }
        }
    } else {
        $all_cats_query = mysqli_query($connect, "
            SELECT c.id, c.category_name, c.parent_id, p.category_name AS parent_name
            FROM product_categories c
            LEFT JOIN product_categories p ON c.parent_id = p.id
            WHERE c.is_active = 1 AND c.category_type = 'business-category'
            ORDER BY p.display_order, c.display_order ASC
        ");
        $current_group = null;
        if ($all_cats_query) {
            while ($cat = mysqli_fetch_assoc($all_cats_query)) {
                if ($cat['parent_id'] !== null && $cat['parent_name'] != $current_group) {
                    if ($current_group !== null) {
                        echo '</optgroup>';
                    }
                    echo '<optgroup label="' . htmlspecialchars($cat['parent_name']) . '">';
                    $current_group = $cat['parent_name'];
                }
                if ($cat['parent_id'] !== null) {
                    $sel = ($selected_id !== null && $selected_id === (int) $cat['id']) ? ' selected' : '';
                    echo '<option value="' . (int) $cat['id'] . '"' . $sel . '>'
                        . htmlspecialchars($cat['category_name']) . '</option>';
                }
            }
            if ($current_group !== null) {
                echo '</optgroup>';
            }
        }
    }

    if ($user_id > 0) {
        $user_id = (int) $user_id;
        $custom_cats_query = mysqli_query($connect, "
            SELECT id, category_name FROM user_custom_categories
            WHERE user_id = $user_id AND category_type = 'business-category' AND is_active = 1
            ORDER BY created_at DESC
        ");
        if ($custom_cats_query && mysqli_num_rows($custom_cats_query) > 0) {
            echo '<optgroup label="My Custom Categories">';
            while ($custom_cat = mysqli_fetch_assoc($custom_cats_query)) {
                $sel = ($selected_id !== null && $selected_id === (int) $custom_cat['id']) ? ' selected' : '';
                echo '<option value="' . (int) $custom_cat['id'] . '"' . $sel . '>[Custom] '
                    . htmlspecialchars($custom_cat['category_name']) . '</option>';
            }
            echo '</optgroup>';
        }
    }
}

/**
 * Business primary category options for JS dropdowns (value / label / group).
 *
 * @param array{include_placeholder?: bool, custom_value_mode?: 'prefixed'|'name'} $opts
 * @return array<int, array{value: string, label: string, group?: string|null}>
 */
function getBusinessPrimaryCategoryOptions($connect, $user_id = 0, array $opts = []) {
    $include_placeholder = $opts['include_placeholder'] ?? true;
    $custom_value_mode = $opts['custom_value_mode'] ?? 'prefixed';

    $options = [];
    if ($include_placeholder) {
        $options[] = ['value' => '', 'label' => '-- Select Primary Category --'];
    }

    $seen = [];

    if (productCategoriesIsFlatSchema($connect)) {
        $sql = "SELECT MIN(id) AS id, business_profile_type, business_heading, business_category, business_category_slug,
                       MIN(directory_priority) AS directory_priority
                FROM product_categories
                WHERE is_active = 1
                GROUP BY business_category_slug, business_heading, business_category, business_profile_type
                ORDER BY MIN(directory_priority) ASC, business_heading ASC, business_category ASC";
        $result = mysqli_query($connect, $sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $catName = trim((string) ($row['business_category'] ?? ''));
                if ($catName === '') {
                    continue;
                }
                $heading = trim((string) ($row['business_heading'] ?? ''));
                $profile = trim((string) ($row['business_profile_type'] ?? ''));
                $groupLabel = $heading !== '' ? $heading : ($profile !== '' ? $profile : null);
                if ($profile !== '' && $heading !== '' && strcasecmp($profile, $heading) !== 0) {
                    $groupLabel = $profile . ' — ' . $heading;
                }
                $uniqKey = strtolower(($groupLabel ?? '') . '|' . $catName);
                if (isset($seen[$uniqKey])) {
                    continue;
                }
                $seen[$uniqKey] = true;
                $options[] = [
                    'value' => $catName,
                    'label' => $catName,
                    'group' => $groupLabel,
                ];
            }
        }
    } else {
        $sql = "SELECT c.category_name, p.category_name AS parent_name
                FROM product_categories c
                LEFT JOIN product_categories p ON c.parent_id = p.id
                WHERE c.is_active = 1 AND c.category_type = 'business-category' AND c.parent_id IS NOT NULL
                ORDER BY p.display_order, c.display_order ASC";
        $result = mysqli_query($connect, $sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $catName = trim((string) ($row['category_name'] ?? ''));
                $parentName = trim((string) ($row['parent_name'] ?? ''));
                if ($catName === '') {
                    continue;
                }
                $uniqKey = strtolower($parentName . '|' . $catName);
                if (isset($seen[$uniqKey])) {
                    continue;
                }
                $seen[$uniqKey] = true;
                $options[] = [
                    'value' => $catName,
                    'label' => $catName,
                    'group' => $parentName !== '' ? $parentName : null,
                ];
            }
        }
    }

    $user_id = (int) $user_id;
    if ($user_id > 0) {
        $custom_res = mysqli_query($connect, "
            SELECT category_name FROM user_custom_categories
            WHERE user_id = $user_id AND category_type = 'business-category' AND is_active = 1
            ORDER BY created_at DESC
        ");
        if ($custom_res) {
            while ($custom_row = mysqli_fetch_assoc($custom_res)) {
                $catName = trim((string) ($custom_row['category_name'] ?? ''));
                if ($catName === '') {
                    continue;
                }
                $customLabel = '[Custom] ' . $catName;
                $value = ($custom_value_mode === 'name') ? $catName : $customLabel;
                $uniqKey = strtolower('my custom categories|' . $value);
                if (isset($seen[$uniqKey])) {
                    continue;
                }
                $seen[$uniqKey] = true;
                $options[] = [
                    'value' => $value,
                    'label' => $customLabel,
                    'group' => 'My Custom Categories',
                ];
            }
        }
    }

    return $options;
}

/**
 * Resolve business category display name by row id.
 */
/**
 * Distinct Business Profile Type values for company-details dropdown.
 */
/**
 * Business categories for a profile type (JSON / cascade UI).
 */
function getBusinessCategoriesByProfileType($connect, $profile_type) {
    $items = [];
    $profile_type = trim((string) $profile_type);
    if ($profile_type === '') {
        return $items;
    }

    if (productCategoriesIsFlatSchema($connect)) {
        $profile_esc = mysqli_real_escape_string($connect, $profile_type);
        $sql = "SELECT MIN(id) AS id, business_heading, business_category, business_category_slug,
                       MIN(directory_priority) AS directory_priority
                FROM product_categories
                WHERE is_active = 1 AND business_profile_type = '$profile_esc'
                GROUP BY business_category_slug, business_heading, business_category
                ORDER BY MIN(directory_priority) ASC, business_heading ASC, business_category ASC";
        $result = mysqli_query($connect, $sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $heading = trim((string) ($row['business_heading'] ?? ''));
                $items[] = [
                    'id' => (int) $row['id'],
                    'label' => trim((string) ($row['business_category'] ?? '')),
                    'group' => $heading !== '' ? $heading : 'Categories',
                    'slug' => trim((string) ($row['business_category_slug'] ?? '')),
                ];
            }
        }
    }

    return $items;
}

/**
 * Product categories for selected business category row id(s).
 */
function getProductCategoriesForBusinessIds($connect, array $business_row_ids) {
    $items = [];
    $business_row_ids = array_values(array_filter(array_map('intval', $business_row_ids)));
    if (empty($business_row_ids)) {
        return $items;
    }

    if (!productCategoriesIsFlatSchema($connect)) {
        $ids_sql = implode(',', $business_row_ids);
        $q = mysqli_query($connect, "
            SELECT id, category_name AS label, display_order
            FROM product_categories
            WHERE parent_id IN ($ids_sql) AND is_active = 1 AND category_type = 'product-category'
            ORDER BY display_order ASC, category_name ASC
        ");
        if ($q) {
            while ($row = mysqli_fetch_assoc($q)) {
                $items[] = [
                    'id' => (int) $row['id'],
                    'label' => trim((string) ($row['label'] ?? '')),
                    'slug' => '',
                ];
            }
        }
        return $items;
    }

    $ids_sql = implode(',', $business_row_ids);
    $slug_res = mysqli_query($connect, "SELECT DISTINCT business_category_slug FROM product_categories WHERE id IN ($ids_sql)");
    if (!$slug_res) {
        return $items;
    }

    $slugs = [];
    while ($s = mysqli_fetch_assoc($slug_res)) {
        if (!empty($s['business_category_slug'])) {
            $slugs[] = "'" . mysqli_real_escape_string($connect, $s['business_category_slug']) . "'";
        }
    }
    if (empty($slugs)) {
        return $items;
    }

    $slugs_sql = implode(',', $slugs);
    $seen = [];
    $q = mysqli_query($connect, "
        SELECT id, product_category, product_category_slug, directory_priority
        FROM product_categories
        WHERE business_category_slug IN ($slugs_sql) AND is_active = 1
        ORDER BY directory_priority ASC, product_category ASC
    ");
    if ($q) {
        while ($row = mysqli_fetch_assoc($q)) {
            $slug = trim((string) ($row['product_category_slug'] ?? ''));
            $key = $slug !== '' ? strtolower($slug) : ('id_' . $row['id']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $items[] = [
                'id' => (int) $row['id'],
                'label' => trim((string) ($row['product_category'] ?? '')),
                'slug' => $slug,
            ];
        }
    }

    return $items;
}

function getBusinessProfileTypeOptions($connect) {
    $options = [];
    if (!productCategoriesIsFlatSchema($connect)) {
        return $options;
    }
    $result = mysqli_query($connect, "
        SELECT DISTINCT business_profile_type
        FROM product_categories
        WHERE is_active = 1 AND TRIM(business_profile_type) <> ''
        ORDER BY business_profile_type ASC
    ");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $val = trim((string) ($row['business_profile_type'] ?? ''));
            if ($val !== '') {
                $options[] = $val;
            }
        }
    }
    return $options;
}

/**
 * Display label for a saved product/service row category (system or custom).
 */
function getStoredProductCategoryLabel($connect, $category_id, $category_source = 'system', $user_id = 0) {
    $category_id = (int) $category_id;
    if ($category_id <= 0) {
        return '';
    }
    $category_source = trim((string) $category_source);
    $user_id = (int) $user_id;

    if ($category_source === 'custom' && $user_id > 0) {
        $q = mysqli_query($connect, "SELECT category_name FROM user_custom_categories WHERE id=$category_id AND user_id=$user_id AND is_active=1 LIMIT 1");
        if ($q && ($r = mysqli_fetch_assoc($q))) {
            return trim((string) ($r['category_name'] ?? ''));
        }
        return '';
    }

    if (productCategoriesIsFlatSchema($connect)) {
        $q = mysqli_query($connect, "SELECT product_category FROM product_categories WHERE id=$category_id LIMIT 1");
        if ($q && ($r = mysqli_fetch_assoc($q))) {
            return trim((string) ($r['product_category'] ?? ''));
        }
    } else {
        $q = mysqli_query($connect, "SELECT category_name FROM product_categories WHERE id=$category_id LIMIT 1");
        if ($q && ($r = mysqli_fetch_assoc($q))) {
            return trim((string) ($r['category_name'] ?? ''));
        }
    }

    return '';
}

/**
 * SQL CASE expression for card_product_pricing category display label (alias category_name).
 */
function buildProductPricingCategoryNameCaseSql($connect) {
    $pc_col = productCategoriesIsFlatSchema($connect) ? 'pc.product_category' : 'pc.category_name';
    return "
            CASE
                WHEN pp.category_source = 'custom' AND ucc.category_name IS NOT NULL THEN ucc.category_name
                WHEN (pp.category_source = 'system' OR pp.category_source IS NULL OR pp.category_source = '') AND $pc_col IS NOT NULL THEN $pc_col
                ELSE COALESCE(ucc.category_name, $pc_col,
                    CASE WHEN pp.product_category IS NOT NULL AND pp.product_category > 0 THEN CONCAT('Category ', pp.product_category) ELSE NULL END
                )
            END as category_name";
}

/**
 * Custom user business category label by id.
 */
function getCustomBusinessCategoryNameById($connect, $category_id, $user_id) {
    $category_id = (int) $category_id;
    $user_id = (int) $user_id;
    if ($category_id <= 0 || $user_id <= 0) {
        return '';
    }
    $q = mysqli_query($connect, "SELECT category_name FROM user_custom_categories WHERE id=$category_id AND user_id=$user_id AND category_type='business-category' AND is_active=1 LIMIT 1");
    if ($q && ($r = mysqli_fetch_assoc($q))) {
        $name = trim((string) ($r['category_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
    }
    $q2 = mysqli_query($connect, "SELECT category_name FROM user_custom_categories WHERE id=$category_id AND user_id=$user_id AND is_active=1 LIMIT 1");
    if ($q2 && ($r2 = mysqli_fetch_assoc($q2))) {
        return trim((string) ($r2['category_name'] ?? ''));
    }
    return '';
}

function getBusinessCategoryNameById($connect, $category_id) {
    $category_id = (int) $category_id;
    if ($category_id <= 0) {
        return '';
    }
    if (productCategoriesIsFlatSchema($connect)) {
        $q = mysqli_query($connect, "SELECT business_category FROM product_categories WHERE id=$category_id AND is_active=1 LIMIT 1");
        if ($q && ($r = mysqli_fetch_assoc($q))) {
            return trim((string) ($r['business_category'] ?? ''));
        }
    } else {
        $q = mysqli_query($connect, "SELECT category_name FROM product_categories WHERE id=$category_id AND category_type='business-category' AND is_active=1 LIMIT 1");
        if ($q && ($r = mysqli_fetch_assoc($q))) {
            return trim((string) ($r['category_name'] ?? ''));
        }
    }
    return '';
}
