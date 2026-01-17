<?php
/**
 * FAQ Helper Functions
 * Functions to display FAQs dynamically on frontend pages
 */

function getFAQsByPageType($page_type = 'home') {
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

function displayFAQs($page_type = 'home', $accordion_id = 'faqAccordion') {
    $faqs = getFAQsByPageType($page_type);
    
    if (empty($faqs)) {
        return '<p>No FAQs available at the moment.</p>';
    }
    
    $html = '<div class="faq-container" id="' . $accordion_id . '">';
    
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
                    ' . nl2br(htmlspecialchars($faq['answer'])) . '
                </div>
            </div>
        </div>';
    }
    
    $html .= '</div>';
    
    return $html;
}

function displayFAQsAsList($page_type = 'home') {
    $faqs = getFAQsByPageType($page_type);
    
    if (empty($faqs)) {
        return '<p>No FAQs available at the moment.</p>';
    }
    
    $html = '<div class="faq-list">';
    
    foreach ($faqs as $index => $faq) {
        $html .= '
        <div class="faq-item-list">
            <h4>' . ($index + 1) . '. ' . htmlspecialchars($faq['question']) . '</h4>
            <p>' . nl2br(htmlspecialchars($faq['answer'])) . '</p>
        </div>';
    }
    
    $html .= '</div>';
    
    return $html;
}
?>



