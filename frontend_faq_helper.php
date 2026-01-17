<?php
/**
 * Frontend FAQ Helper Functions
 * Functions to display FAQs dynamically on frontend pages with proper styling
 */

// Include centralized database connection
require_once __DIR__ . '/app/config/database.php';


function getFrontendFAQs($page_type = 'home') {
    global $connect;
    
    $page_type = mysqli_real_escape_string($connect, $page_type);
    $query = "SELECT * FROM faq_management WHERE page_type = '$page_type' AND status = 'active' ORDER BY sort_order ASC";
    $result = mysqli_query($connect, $query);
    
    $faqs = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $faqs[] = $row;
        }
    }
    
    return $faqs;
}

function displayFrontendFAQs($page_type = 'home', $accordion_id = 'faqAccordion') {
    $faqs = getFrontendFAQs($page_type);
    
    if (empty($faqs)) {
        return '<p>No FAQs available at the moment.</p>';
    }
    
    // Add CSS if not already included
    static $css_included = false;
    if (!$css_included) {
        $html = '<link rel="stylesheet" href="frontend_faq_styles.css">';
        $css_included = true;
    } else {
        $html = '';
    }
    
    $html .= '<div class="faq-container" id="' . $accordion_id . '">';
    
    foreach ($faqs as $index => $faq) {
        $faq_id = 'faq' . ($index + 1);
        $html .= '
        <div class="faq-item">
            <div class="card-header p-2">
                <h5 class="mb-0">
                    <button class="btn btn-link collapsed" data-toggle="collapse" data-target="#' . $faq_id . '">
                        ' . ($index + 1) . '. ' . htmlspecialchars($faq['question']) . '
                    </button>
                </h5>
            </div>
            <div id="' . $faq_id . '" class="collapse" data-parent="#' . $accordion_id . '">
                <div class="card-body">
                    ' . $faq['answer'] . '
                </div>
            </div>
        </div>';
    }
    
    $html .= '</div>';
    
    return $html;
}

function displayFrontendFAQsAsList($page_type = 'home') {
    $faqs = getFrontendFAQs($page_type);
    
    if (empty($faqs)) {
        return '<p>No FAQs available at the moment.</p>';
    }
    
    // Add CSS if not already included
    static $css_included = false;
    if (!$css_included) {
        $html = '<link rel="stylesheet" href="frontend_faq_styles.css">';
        $css_included = true;
    } else {
        $html = '';
    }
    
    $html .= '<div class="faq-list">';
    
    foreach ($faqs as $index => $faq) {
        $html .= '
        <div class="faq-item-list">
            <h4>' . ($index + 1) . '. ' . htmlspecialchars($faq['question']) . '</h4>
            <p>' . $faq['answer'] . '</p>
        </div>';
    }
    
    $html .= '</div>';
    
    return $html;
}

// Alternative function for simple FAQ display without accordion
function displaySimpleFAQs($page_type = 'home') {
    $faqs = getFrontendFAQs($page_type);
    
    if (empty($faqs)) {
        return '<p>No FAQs available at the moment.</p>';
    }
    
    // Add CSS if not already included
    static $css_included = false;
    if (!$css_included) {
        $html = '<link rel="stylesheet" href="frontend_faq_styles.css">';
        $css_included = true;
    } else {
        $html = '';
    }
    
    $html .= '<div class="simple-faq-container">';
    
    foreach ($faqs as $index => $faq) {
        $html .= '
        <div class="simple-faq-item">
            <h4 class="faq-question">' . ($index + 1) . '. ' . htmlspecialchars($faq['question']) . '</h4>
            <div class="faq-answer">
                ' . $faq['answer'] . '
            </div>
        </div>';
    }
    
    $html .= '</div>';
    
    return $html;
}
?>
