<?php
header('Content-Type: application/json');
require_once('connect.php');

// Get page type from request
$page_type = isset($_GET['page_type']) ? mysqli_real_escape_string($connect, $_GET['page_type']) : 'home';

// Fetch FAQs for the specified page type
$query = "SELECT * FROM faq_management WHERE page_type = '$page_type' AND status = 'active' ORDER BY sort_order ASC";
$result = mysqli_query($connect, $query);

$faqs = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $faqs[] = [
            'id' => $row['id'],
            'question' => $row['question'],
            'answer' => $row['answer'],
            'sort_order' => $row['sort_order']
        ];
    }
}

echo json_encode([
    'success' => true,
    'faqs' => $faqs,
    'page_type' => $page_type
]);
?>
