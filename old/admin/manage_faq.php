<?php
require_once('connect.php');
include_once('header.php');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $page_type = mysqli_real_escape_string($connect, $_POST['page_type']);
                $question = mysqli_real_escape_string($connect, $_POST['question']);
                $answer = mysqli_real_escape_string($connect, $_POST['answer']);
                $sort_order = intval($_POST['sort_order']);
                
                $insert_query = "INSERT INTO faq_management (page_type, question, answer, sort_order) VALUES ('$page_type', '$question', '$answer', $sort_order)";
                if (mysqli_query($connect, $insert_query)) {
                    $success_message = "FAQ added successfully!";
                } else {
                    $error_message = "Error adding FAQ: " . mysqli_error($connect);
                }
                break;
                
            case 'edit':
                $id = intval($_POST['id']);
                $page_type = mysqli_real_escape_string($connect, $_POST['page_type']);
                $question = mysqli_real_escape_string($connect, $_POST['question']);
                $answer = mysqli_real_escape_string($connect, $_POST['answer']);
                $sort_order = intval($_POST['sort_order']);
                $status = mysqli_real_escape_string($connect, $_POST['status']);
                
                $update_query = "UPDATE faq_management SET page_type='$page_type', question='$question', answer='$answer', sort_order=$sort_order, status='$status' WHERE id=$id";
                if (mysqli_query($connect, $update_query)) {
                    $success_message = "FAQ updated successfully!";
                } else {
                    $error_message = "Error updating FAQ: " . mysqli_error($connect);
                }
                break;
                
            case 'delete':
                $id = intval($_POST['id']);
                $delete_query = "DELETE FROM faq_management WHERE id=$id";
                if (mysqli_query($connect, $delete_query)) {
                    $success_message = "FAQ deleted successfully!";
                } else {
                    $error_message = "Error deleting FAQ: " . mysqli_error($connect);
                }
                break;
        }
    }
}

// Get all FAQs
$faq_query = "SELECT * FROM faq_management ORDER BY page_type, sort_order ASC";
$faq_result = mysqli_query($connect, $faq_query);
$faqs = [];
while ($row = mysqli_fetch_assoc($faq_result)) {
    $faqs[] = $row;
}
?>

	<!-- CKEditor -->
	<script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>
	
<style>
/* FAQ Management Custom Styles */
.faq-management-container {
    background: #f8f9fa;
    min-height: 100vh;
    padding: 0;
    margin: 0;
    width: 100%;
}


.faq-card {
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border: none;
    border-radius: 10px;
    overflow: hidden;
    margin: 0;
    width: 100%;
}

.faq-card .card-header {
    background: linear-gradient(135deg, #002169 0%, #1e3a8a 100%);
    color: white;
    border: none;
    padding: 20px;
}

.faq-card .card-header h3 {
    margin: 0;
    font-weight: 600;
    font-size: 24px;
}

.faq-card .card-header .btn {
    background: linear-gradient(135deg, #ffc107, #ff8f00);
    border: none;
    color: #000;
    font-weight: 600;
    padding: 12px 25px;
    border-radius: 25px;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    position: relative;
    overflow: hidden;
}

.faq-card .card-header .btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    transition: left 0.5s;
}

.faq-card .card-header .btn:hover::before {
    left: 100%;
}

.faq-card .card-header .btn:hover {
    background: linear-gradient(135deg, #ff8f00, #ffc107);
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 8px 20px rgba(255, 193, 7, 0.4);
}

.faq-table {
    margin: 0;
    width: 100%;
    table-layout: auto;
}

.faq-table thead th {
    background: #e9ecef;
    border: none;
    font-weight: 600;
    color: #495057;
    padding: 15px 12px;
    text-transform: uppercase;
    font-size: 12px;
    letter-spacing: 0.5px;
    width: auto;
}

.faq-table thead th:nth-child(1) { width: 5%; }  /* ID */
.faq-table thead th:nth-child(2) { width: 12%; } /* Page Type */
.faq-table thead th:nth-child(3) { width: 25%; } /* Question */
.faq-table thead th:nth-child(4) { width: 35%; } /* Answer */
.faq-table thead th:nth-child(5) { width: 8%; }  /* Sort Order */
.faq-table thead th:nth-child(6) { width: 8%; }  /* Status */
.faq-table thead th:nth-child(7) { width: 7%; }  /* Actions */

.faq-table tbody tr {
    transition: all 0.3s ease;
    border: none;
}

.faq-table tbody tr:hover {
    background: #f8f9fa;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.faq-table tbody td {
    border: none;
    padding: 15px 12px;
    vertical-align: middle;
    border-bottom: 1px solid #e9ecef;
}

.badge {
    font-size: 11px;
    padding: 6px 12px;
    border-radius: 20px;
    font-weight: 500;
}

.badge-primary {
    background: linear-gradient(135deg, #007bff, #0056b3);
}

.badge-success {
    background: linear-gradient(135deg, #28a745, #1e7e34);
}

.badge-warning {
    background: linear-gradient(135deg, #ffc107, #e0a800);
    color: #000;
}

.badge-danger {
    background: linear-gradient(135deg, #dc3545, #c82333);
}

.btn-sm {
    padding: 6px 12px;
    border-radius: 20px;
    margin: 0 2px;
    transition: all 0.3s ease;
}

.btn-info {
    background: linear-gradient(135deg, #17a2b8, #138496);
    border: none;
}

.btn-info:hover {
    background: linear-gradient(135deg, #138496, #117a8b);
    transform: translateY(-1px);
}

.btn-danger {
    background: linear-gradient(135deg, #dc3545, #c82333);
    border: none;
}

.btn-danger:hover {
    background: linear-gradient(135deg, #c82333, #bd2130);
    transform: translateY(-1px);
}

/* Enhanced Modal Styles */
.modal {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    z-index: 1050 !important;
    width: 100% !important;
    height: 100% !important;
    overflow-x: hidden !important;
    overflow-y: auto !important;
    outline: 0 !important;
    display: none !important;
}

.modal.show {
    display: block !important;
}

.modal-dialog {
    position: relative !important;
    width: auto !important;
    margin: 1.75rem auto !important;
    max-width: 800px !important;
    pointer-events: none !important;
}

.modal-dialog.modal-lg {
    max-width: 900px !important;
}

.modal-content {
    position: relative !important;
    display: flex !important;
    flex-direction: column !important;
    width: 100% !important;
    pointer-events: auto !important;
    background-color: #fff !important;
    background-clip: padding-box !important;
    border: none !important;
    border-radius: 20px !important;
    box-shadow: 0 25px 80px rgba(0, 0, 0, 0.3) !important;
    transform: translateY(-50px) scale(0.8) !important;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
    overflow: hidden !important;
    outline: 0 !important;
}

.modal.show .modal-content {
    transform: translateY(0) scale(1) !important;
}

.modal-backdrop {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    z-index: 1040 !important;
    width: 100vw !important;
    height: 100vh !important;
    background-color: rgba(0, 0, 0, 0.6) !important;
    backdrop-filter: blur(8px) !important;
    transition: all 0.3s ease !important;
    display: none !important;
}

.modal-backdrop.show {
    display: block !important;
    opacity: 1 !important;
}

/* Ensure modal appears above everything */
body.modal-open {
    overflow: hidden !important;
}

.modal-open .modal {
    overflow-x: hidden !important;
    overflow-y: auto !important;
}

/* Fix for Bootstrap modal conflicts */
.modal.fade .modal-dialog {
    transition: transform 0.3s ease-out !important;
    transform: translate(0, -50px) !important;
}

.modal.show .modal-dialog {
    transform: none !important;
}

/* Ensure proper z-index stacking */
.modal {
    z-index: 1055 !important;
}

.modal-backdrop {
    z-index: 1050 !important;
}

/* Ensure modal is hidden by default */
.modal:not(.show) {
    display: none !important;
    opacity: 0 !important;
}

.modal-backdrop:not(.show) {
    display: none !important;
    opacity: 0 !important;
}

.modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 30px;
    position: relative;
    overflow: hidden;
}

.modal-header::after {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: pulse 3s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 0.5; }
    50% { transform: scale(1.1); opacity: 0.8; }
}

.modal-header .modal-title {
    font-weight: 700;
    font-size: 24px;
    position: relative;
    z-index: 2;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

.modal-header .close {
    color: white;
    opacity: 0.9;
    font-size: 32px;
    transition: all 0.3s ease;
    position: relative;
    z-index: 2;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid rgba(255,255,255,0.2);
}

.modal-header .close:hover {
    opacity: 1;
    transform: rotate(90deg) scale(1.1);
    background: rgba(255,255,255,0.2);
    border-color: rgba(255,255,255,0.4);
}

.modal-body {
    padding: 50px;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    min-height: 400px;
}

.form-group {
    margin-bottom: 25px;
    position: relative;
}

.form-group label {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 10px;
    display: block;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-control {
    border: 2px solid #e1e8ed;
    border-radius: 12px;
    
    transition: all 0.3s ease;
    background: #fff;
    font-size: 18px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    min-height: 50px;
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.3rem rgba(102, 126, 234, 0.15);
    transform: translateY(-2px);
    background: #fff;
}

.form-control:hover {
    border-color: #667eea;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

textarea.form-control {
    resize: vertical;
    min-height: 150px;
    font-size: 16px;
    line-height: 1.6;
}

/* CKEditor Styling */
.ck-editor__editable {
    min-height: 200px !important;
    border-radius: 8px !important;
    border: 2px solid #e1e8ed !important;
    transition: all 0.3s ease !important;
}

.ck-editor__editable:focus {
    border-color: #667eea !important;
    box-shadow: 0 0 0 0.3rem rgba(102, 126, 234, 0.15) !important;
}

.ck-editor__top {
    border-radius: 8px 8px 0 0 !important;
    border: 2px solid #e1e8ed !important;
    border-bottom: none !important;
}

.ck-editor__main {
    border-radius: 0 0 8px 8px !important;
}

.ck-editor__editable_inline {
    padding: 15px !important;
}

/* Hide textarea but keep it accessible for form validation */
#add_answer, #edit_answer {
    position: absolute !important;
    left: -9999px !important;
    width: 1px !important;
    height: 1px !important;
    opacity: 0 !important;
    pointer-events: none !important;
}

/* CKEditor in modal */
.modal .ck-editor {
    margin-bottom: 0 !important;
}

.modal .ck-editor__editable {
    min-height: 180px !important;
}

select.form-control {
    cursor: pointer;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 12px center;
    background-repeat: no-repeat;
    background-size: 16px;
    padding-right: 40px;
}

.modal-footer {
    border: none;
    padding: 40px 50px 50px;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    display: flex;
    justify-content: flex-end;
    gap: 20px;
}

.modal-footer .btn {
    padding: 18px 40px;
    border-radius: 30px;
    font-weight: 600;
    font-size: 18px;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    border: none;
    position: relative;
    overflow: hidden;
    min-width: 150px;
    min-height: 55px;
}

.modal-footer .btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}

.modal-footer .btn:hover::before {
    left: 100%;
}

.modal-footer .btn-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.modal-footer .btn-primary:hover {
    background: linear-gradient(135deg, #764ba2, #667eea);
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
}

.modal-footer .btn-secondary {
    background: linear-gradient(135deg, #6c757d, #5a6268);
    color: white;
    box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
}

.modal-footer .btn-secondary:hover {
    background: linear-gradient(135deg, #5a6268, #6c757d);
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 8px 25px rgba(108, 117, 125, 0.4);
}

/* Alert Styles */
.alert {
    border: none;
    border-radius: 10px;
    padding: 15px 20px;
    margin-bottom: 20px;
    font-weight: 500;
}

.alert-success {
    background: linear-gradient(135deg, #d4edda, #c3e6cb);
    color: #155724;
}

.alert-danger {
    background: linear-gradient(135deg, #f8d7da, #f5c6cb);
    color: #721c24;
}

/* Responsive Design */
@media (max-width: 768px) {
    .faq-card .card-header {
        text-align: center;
    }
    
    .faq-card .card-header .btn {
        margin-top: 10px;
        width: 100%;
    }
    
    .faq-table {
        font-size: 14px;
    }
    
    .modal-dialog {
        margin: 10px;
        max-width: calc(100% - 20px);
    }
    
    .modal-body {
        padding: 30px;
        min-height: 300px;
    }
    
    .modal-footer {
        padding: 25px 30px 30px;
        flex-direction: column;
        gap: 15px;
    }
    
    .modal-footer .btn {
        width: 100%;
        margin: 0;
        padding: 15px 30px;
        font-size: 16px;
        min-height: 50px;
    }
    
    .modal-header {
        padding: 25px;
    }
    
    .modal-header .modal-title {
        font-size: 22px;
    }
    
    .form-control {
        padding: 15px 20px;
        font-size: 16px;
        min-height: 45px;
    }
    
    textarea.form-control {
        min-height: 120px;
    }
}

@media (max-width: 480px) {
    .modal-dialog {
        margin: 5px;
        max-width: calc(100% - 10px);
    }
    
    .modal-body {
        padding: 20px;
    }
    
    .modal-header {
        padding: 15px;
    }
    
    .modal-footer {
        padding: 15px 20px 20px;
    }
}

/* Loading Animation */
.loading {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(255,255,255,.3);
    border-radius: 50%;
    border-top-color: #fff;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Table Actions */
.table-actions {
    white-space: nowrap;
}

.table-actions .btn {
    margin: 0 2px;
    transition: all 0.3s ease;
}

.table-actions .btn:hover {
    transform: scale(1.1);
}

/* Enhanced table hover effects */
.table-hover-effect {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef) !important;
    transform: scale(1.02);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* Preview Modal Styles */
#previewModal .modal-header {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    color: white;
    border: none;
    padding: 30px;
    position: relative;
    overflow: hidden;
}

#previewModal .modal-header::after {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: pulse 3s ease-in-out infinite;
}

#previewModal .modal-body {
    padding: 30px;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    min-height: 400px;
    max-height: 600px;
    overflow-y: auto;
}

#previewModal .modal-footer {
    border: none;
    padding: 20px 30px 30px;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    display: flex;
    justify-content: space-between;
    gap: 20px;
}

#previewModal .modal-footer .btn {
    padding: 12px 25px;
    border-radius: 25px;
    font-weight: 600;
    font-size: 16px;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    border: none;
    position: relative;
    overflow: hidden;
    min-width: 120px;
}

#previewModal .modal-footer .btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}

#previewModal .modal-footer .btn:hover::before {
    left: 100%;
}

#previewModal .modal-footer .btn-info {
    background: linear-gradient(135deg, #17a2b8, #138496);
    color: white;
    box-shadow: 0 4px 15px rgba(23, 162, 184, 0.3);
}

#previewModal .modal-footer .btn-info:hover {
    background: linear-gradient(135deg, #138496, #117a8b);
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 8px 25px rgba(23, 162, 184, 0.4);
}

#previewModal .modal-footer .btn-secondary {
    background: linear-gradient(135deg, #6c757d, #5a6268);
    color: white;
    box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
}

#previewModal .modal-footer .btn-secondary:hover {
    background: linear-gradient(135deg, #5a6268, #6c757d);
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 8px 25px rgba(108, 117, 125, 0.4);
}

/* Preview Content Styling */
.preview-content {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    line-height: 1.6;
    color: #333;
    background: #fff;
    border-radius: 8px;
    border: 1px solid #e1e8ed;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.preview-content h1, .preview-content h2, .preview-content h3, 
.preview-content h4, .preview-content h5, .preview-content h6 {
    color: #2c3e50;
    margin-top: 20px;
    margin-bottom: 10px;
    font-weight: 600;
}

.preview-content h1 { font-size: 28px; }
.preview-content h2 { font-size: 24px; }
.preview-content h3 { font-size: 20px; }
.preview-content h4 { font-size: 18px; }
.preview-content h5 { font-size: 16px; }
.preview-content h6 { font-size: 14px; }

.preview-content p {
    margin-bottom: 15px;
    text-align: justify;
}

.preview-content ul, .preview-content ol {
    margin-bottom: 15px;
    padding-left: 30px;
}

.preview-content li {
    margin-bottom: 5px;
}

.preview-content blockquote {
    border-left: 4px solid #17a2b8;
    padding-left: 20px;
    margin: 20px 0;
    font-style: italic;
    color: #666;
    background: #f8f9fa;
    padding: 15px 20px;
    border-radius: 0 8px 8px 0;
}

.preview-content a {
    color: #17a2b8;
    text-decoration: none;
}

.preview-content a:hover {
    text-decoration: underline;
}

.preview-content strong {
    font-weight: 600;
    color: #2c3e50;
}

.preview-content em {
    font-style: italic;
}

.preview-content table {
    width: 100%;
    border-collapse: collapse;
    margin: 20px 0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border-radius: 8px;
    overflow: hidden;
}

.preview-content table th,
.preview-content table td {
    border: 1px solid #e1e8ed;
    padding: 12px;
    text-align: left;
}

.preview-content table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #2c3e50;
}

.preview-content img {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
    margin: 10px 0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* Toast notifications */
.toast {
    min-width: 300px;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

/* Empty state styling */
.text-center.py-5 {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-radius: 15px;
    margin: 20px;
    padding: 60px 20px !important;
}

.text-center.py-5 .fa-4x {
    color: #6c757d;
    margin-bottom: 20px;
}

.text-center.py-5 .btn-lg {
    padding: 15px 30px;
    font-size: 18px;
    border-radius: 30px;
    box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
    transition: all 0.3s ease;
}

.text-center.py-5 .btn-lg:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 123, 255, 0.4);
}
</style>

<div class="faq-management-container">

    <div class="container-fluid px-0" style="padding-top: 20px;">
        <div class="row mx-0">
            <div class="col-12 px-0">
                <div class="card faq-card">
                    <div class="card-header">
                        <h3 class="card-title">FAQ Management</h3>
                        <button class="btn btn-primary float-right" data-toggle="modal" data-target="#addFaqModal" type="button" id="addFaqBtn" onclick="showAddFaqModal(); showModalFallback();">
                            <i class="fas fa-plus"></i> Add New FAQ
                        </button>
                        <!-- Debug button for testing -->
                        <button class="btn btn-secondary float-right mr-2" type="button" onclick="testModal()" style="display: none;">
                            <i class="fas fa-bug"></i> Test Modal
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (isset($success_message)): ?>
                            <div class="alert alert-success"><?php echo $success_message; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php endif; ?>
                        
                        <?php if (empty($faqs)): ?>
                            <div class="text-center py-5">
                                <div class="mb-4">
                                    <i class="fas fa-question-circle fa-4x text-muted"></i>
                                </div>
                                <h4 class="text-muted">No FAQs Found</h4>
                                <p class="text-muted">Start by adding your first FAQ to help your users.</p>
                                <button class="btn btn-primary btn-lg" data-toggle="modal" data-target="#addFaqModal" onclick="showAddFaqModal(); showModalFallback();">
                                    <i class="fas fa-plus"></i> Add Your First FAQ
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive w-100">
                                <table class="table faq-table w-100">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Page Type</th>
                                        <th>Question</th>
                                        <th>Answer</th>
                                        <th>Sort Order</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($faqs as $faq): ?>
                                    <tr>
                                        <td><?php echo $faq['id']; ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $faq['page_type'] == 'home' ? 'primary' : ($faq['page_type'] == 'refer_earn' ? 'success' : 'warning'); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $faq['page_type'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars(substr($faq['question'], 0, 50)) . (strlen($faq['question']) > 50 ? '...' : ''); ?></td>
                                        <td><?php echo htmlspecialchars(substr($faq['answer'], 0, 100)) . (strlen($faq['answer']) > 100 ? '...' : ''); ?></td>
                                        <td><?php echo $faq['sort_order']; ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $faq['status'] == 'active' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($faq['status']); ?>
                                            </span>
                                        </td>
                                        <td class="table-actions">
                                            <button class="btn btn-sm btn-info" onclick="editFaq(<?php echo htmlspecialchars(json_encode($faq)); ?>)" title="Edit FAQ">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteFaq(<?php echo $faq['id']; ?>)" title="Delete FAQ">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add FAQ Modal -->
<div class="modal fade" id="addFaqModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New FAQ</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="hideModalFallback()">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="form-group">
                        <label>Page Type</label>
                        <select name="page_type" class="form-control" required>
                            <option value="">Select Page Type</option>
                            <option value="home">Home Page</option>
                            <option value="refer_earn">Refer & Earn</option>
                            <option value="franchise">Franchise</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Question</label>
                        <input type="text" name="question" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Answer</label>
                        <textarea name="answer" id="add_answer" class="form-control" rows="5" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" value="1" min="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" onclick="hideModalFallback()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add FAQ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit FAQ Modal -->
<div class="modal fade" id="editFaqModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit FAQ</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="hideModalFallback()">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="form-group">
                        <label>Page Type</label>
                        <select name="page_type" id="edit_page_type" class="form-control" required>
                            <option value="">Select Page Type</option>
                            <option value="home">Home Page</option>
                            <option value="refer_earn">Refer & Earn</option>
                            <option value="franchise">Franchise</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Question</label>
                        <input type="text" name="question" id="edit_question" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Answer</label>
                        <textarea name="answer" id="edit_answer" class="form-control" rows="5" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Sort Order</label>
                        <input type="number" name="sort_order" id="edit_sort_order" class="form-control" min="1">
                    </div>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="edit_status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" onclick="hideModalFallback()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update FAQ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-eye"></i> <span id="preview-title">Content Preview</span>
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="preview-content" id="preview-content">
                    <!-- Preview content will be inserted here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-info" onclick="printPreview()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Enhanced modal interactions
$(document).ready(function() {
    console.log('jQuery is working!');
    console.log('Bootstrap modal available:', typeof $.fn.modal);
    
    // Initialize CKEditor for answer fields
    window.addEditor = null;
    window.editEditor = null;
    window.currentFaqData = null;
    
    // Initialize CKEditor when Add modal is shown
    $('#addFaqModal').on('shown.bs.modal', function() {
        if (!window.addEditor) {
            ClassicEditor
                .create(document.querySelector('#add_answer'), {
                    toolbar: {
                        items: [
                            'heading', '|',
                            'bold', 'italic', 'underline', '|',
                            'bulletedList', 'numberedList', '|',
                            'outdent', 'indent', '|',
                            'link', 'blockQuote', '|',
                            'preview', '|',
                            'undo', 'redo'
                        ]
                    },
                    language: 'en',
                    image: {
                        toolbar: [
                            'imageTextAlternative', 'imageStyle:full', 'imageStyle:side'
                        ]
                    },
                    table: {
                        contentToolbar: [
                            'tableColumn', 'tableRow', 'mergeTableCells'
                        ]
                    }
                })
                .then(editor => {
                    window.addEditor = editor;
                    console.log('Add FAQ CKEditor initialized');
                    
                    // Sync content with textarea on every change
                    editor.model.document.on('change:data', () => {
                        const content = editor.getData();
                        const textarea = document.getElementById('add_answer');
                        if (textarea) {
                            textarea.value = content;
                        }
                    });
                    
                    // Add preview functionality
                    editor.ui.componentFactory.add('preview', function(locale) {
                        const view = new editor.ui.ButtonView(locale);
                        view.set({
                            label: 'Preview',
                            icon: '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 4C4 4 0 10 0 10s4 6 10 6 10-6 10-6-4-6-10-6zm0 10c-2.2 0-4-1.8-4-4s1.8-4 4-4 4 1.8 4 4-1.8 4-4 4zm0-6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>',
                            tooltip: true
                        });
                        
                        view.on('execute', () => {
                            const content = editor.getData();
                            showPreviewModal('Add FAQ Preview', content);
                        });
                        
                        return view;
                    });
                })
                .catch(error => {
                    console.error('Error initializing Add FAQ CKEditor:', error);
                });
        }
    });
    
    // Initialize CKEditor when Edit modal is shown
    $('#editFaqModal').on('shown.bs.modal', function() {
        if (!window.editEditor) {
            console.log('Initializing Edit FAQ CKEditor...');
            ClassicEditor
                .create(document.querySelector('#edit_answer'), {
                    toolbar: {
                        items: [
                            'heading', '|',
                            'bold', 'italic', 'underline', '|',
                            'bulletedList', 'numberedList', '|',
                            'outdent', 'indent', '|',
                            'link', 'blockQuote', '|',
                            'preview', '|',
                            'undo', 'redo'
                        ]
                    },
                    language: 'en',
                    image: {
                        toolbar: [
                            'imageTextAlternative', 'imageStyle:full', 'imageStyle:side'
                        ]
                    },
                    table: {
                        contentToolbar: [
                            'tableColumn', 'tableRow', 'mergeTableCells'
                        ]
                    }
                })
                .then(editor => {
                    window.editEditor = editor;
                    console.log('Edit FAQ CKEditor initialized successfully');
                    
                    // Sync content with textarea on every change
                    editor.model.document.on('change:data', () => {
                        const content = editor.getData();
                        const textarea = document.getElementById('edit_answer');
                        if (textarea) {
                            textarea.value = content;
                        }
                    });
                    
                    // Add preview functionality
                    editor.ui.componentFactory.add('preview', function(locale) {
                        const view = new editor.ui.ButtonView(locale);
                        view.set({
                            label: 'Preview',
                            icon: '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 4C4 4 0 10 0 10s4 6 10 6 10-6 10-6-4-6-10-6zm0 10c-2.2 0-4-1.8-4-4s1.8-4 4-4 4 1.8 4 4-1.8 4-4 4zm0-6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>',
                            tooltip: true
                        });
                        
                        view.on('execute', () => {
                            const content = editor.getData();
                            showPreviewModal('Edit FAQ Preview', content);
                        });
                        
                        return view;
                    });
                    
                    // Check if there's content to load from the textarea or stored FAQ data
                    const textarea = document.querySelector('#edit_answer');
                    let contentToLoad = '';
                    
                    if (window.currentFaqData && window.currentFaqData.answer) {
                        contentToLoad = window.currentFaqData.answer;
                        console.log('Loading content from stored FAQ data:', contentToLoad);
                    } else if (textarea && textarea.value) {
                        contentToLoad = textarea.value;
                        console.log('Loading existing content from textarea:', contentToLoad);
                    }
                    
                    if (contentToLoad) {
                        editor.setData(contentToLoad);
                    }
                })
                .catch(error => {
                    console.error('Error initializing Edit FAQ CKEditor:', error);
                });
        } else {
            console.log('Edit FAQ CKEditor already initialized');
            // Editor already exists, check if there's content to load
            const textarea = document.querySelector('#edit_answer');
            let contentToLoad = '';
            
            if (window.currentFaqData && window.currentFaqData.answer) {
                contentToLoad = window.currentFaqData.answer;
                console.log('Loading content from stored FAQ data into existing editor:', contentToLoad);
            } else if (textarea && textarea.value) {
                contentToLoad = textarea.value;
                console.log('Loading existing content from textarea into existing editor:', contentToLoad);
            }
            
            if (contentToLoad) {
                window.editEditor.setData(contentToLoad);
            }
        }
    });
    
    // Clean up editors when modals are hidden
    $('#addFaqModal').on('hidden.bs.modal', function() {
        if (window.addEditor) {
            window.addEditor.setData('');
        }
        // Clear the textarea as well
        document.getElementById('add_answer').value = '';
    });
    
    $('#editFaqModal').on('hidden.bs.modal', function() {
        // Don't destroy edit editor, just clear data
        if (window.editEditor) {
            window.editEditor.setData('');
        }
        // Clear the textarea as well
        document.getElementById('edit_answer').value = '';
        // Clear stored FAQ data
        window.currentFaqData = null;
    });
    
    // Ensure modals are properly initialized for Bootstrap 4
    $('#addFaqModal, #editFaqModal').modal({
        backdrop: true,
        keyboard: true,
        focus: true,
        show: false
    });
    
    // Force modal initialization
    $('#addFaqModal').modal('handleUpdate');
    $('#editFaqModal').modal('handleUpdate');
    
    // Add smooth animations to modals
    $('#addFaqModal, #editFaqModal').on('show.bs.modal', function() {
        $(this).find('.modal-content').css('transform', 'translateY(-50px) scale(0.8)');
    });
    
    $('#addFaqModal, #editFaqModal').on('shown.bs.modal', function() {
        $(this).find('.modal-content').css('transform', 'translateY(0) scale(1)');
        // Focus on first input
        $(this).find('input, select, textarea').first().focus();
    });
    
    $('#addFaqModal, #editFaqModal').on('hide.bs.modal', function() {
        $(this).find('.modal-content').css('transform', 'translateY(-50px) scale(0.8)');
    });
    
    // Add loading state to form submissions with CKEditor validation
    $('form').on('submit', function(e) {
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        
        // Sync CKEditor content before validation
        window.syncCKEditorContent();
        
        // Validate CKEditor content before submission
        const form = this;
        const answerField = form.querySelector('textarea[name="answer"]');
        
        if (answerField) {
            // Check if this is the add form
            if (answerField.id === 'add_answer' && window.addEditor) {
                const content = window.addEditor.getData();
                if (!content || content.trim() === '') {
                    e.preventDefault();
                    alert('Please enter an answer for the FAQ.');
                    window.addEditor.focus();
                    return false;
                }
                // Update the hidden textarea with CKEditor content
                answerField.value = content;
                // Remove the required attribute temporarily to prevent browser validation
                answerField.removeAttribute('required');
            }
            // Check if this is the edit form
            else if (answerField.id === 'edit_answer' && window.editEditor) {
                const content = window.editEditor.getData();
                if (!content || content.trim() === '') {
                    e.preventDefault();
                    alert('Please enter an answer for the FAQ.');
                    window.editEditor.focus();
                    return false;
                }
                // Update the hidden textarea with CKEditor content
                answerField.value = content;
                // Remove the required attribute temporarily to prevent browser validation
                answerField.removeAttribute('required');
            }
        }
        
        // Show loading state
        submitBtn.html('<span class="loading"></span> Processing...').prop('disabled', true);
        
        // Re-enable after 3 seconds (in case of errors)
        setTimeout(function() {
            submitBtn.html(originalText).prop('disabled', false);
            // Restore required attribute if form submission failed
            if (answerField) {
                answerField.setAttribute('required', 'required');
            }
        }, 3000);
    });
    
    // Add tooltips to action buttons
    $('[title]').tooltip();
    
    // Debug modal functionality
    console.log('Modal initialization complete');
    
    // Test modal functionality - Fixed for Bootstrap 4
    $('[data-toggle="modal"]').on('click', function(e) {
        e.preventDefault();
        console.log('Modal trigger clicked:', $(this).data('target'));
        const target = $(this).data('target');
        
        // Try to show modal
        try {
            $(target).modal('show');
        } catch (error) {
            console.error('Modal error:', error);
            // Fallback: show alert
            alert('Modal functionality not available. Please refresh the page.');
        }
    });
    
    // Additional modal trigger for the Add FAQ button
    $('#addFaqBtn').on('click', function(e) {
        e.preventDefault();
        console.log('Add FAQ button clicked');
        $('#addFaqModal').modal('show');
    });
    
    // Fallback for any button with data-target
    $('.btn[data-target="#addFaqModal"]').on('click', function(e) {
        e.preventDefault();
        console.log('Add FAQ button clicked via data-target');
        $('#addFaqModal').modal('show');
    });
    
    // Debug button clicks
    $('.btn').on('click', function() {
        console.log('Button clicked:', $(this).text(), $(this).attr('class'));
    });
    
    // Test if Bootstrap modal is working
    console.log('Bootstrap version check:', typeof $.fn.modal);
    console.log('Modal element exists:', $('#addFaqModal').length > 0);
    
    // Force show modal if needed
    window.showAddFaqModal = function() {
        console.log('Force showing Add FAQ modal');
        try {
            $('#addFaqModal').modal('show');
        } catch (error) {
            console.error('Error showing modal:', error);
            // Fallback: show the modal manually
            $('#addFaqModal').show();
            $('body').addClass('modal-open');
            $('<div class="modal-backdrop show"></div>').appendTo('body');
        }
    };
    
    // Function to sync CKEditor content with textarea
    window.syncCKEditorContent = function() {
        if (window.addEditor) {
            const content = window.addEditor.getData();
            const textarea = document.getElementById('add_answer');
            if (textarea) {
                textarea.value = content;
            }
        }
        if (window.editEditor) {
            const content = window.editEditor.getData();
            const textarea = document.getElementById('edit_answer');
            if (textarea) {
                textarea.value = content;
            }
        }
    };
    
    // Simple fallback modal show function
    window.showModalFallback = function() {
        console.log('Using fallback modal method');
        const modal = document.getElementById('addFaqModal');
        if (modal) {
            modal.style.display = 'block';
            modal.classList.add('show');
            document.body.classList.add('modal-open');
            
            // Add backdrop
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop show';
            backdrop.id = 'modalBackdrop';
            document.body.appendChild(backdrop);
            
            // Close on backdrop click
            backdrop.onclick = function() {
                hideModalFallback();
            };
            
            // Close on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    hideModalFallback();
                }
            });
        }
    };
    
    // Hide modal fallback
    window.hideModalFallback = function() {
        const modal = document.getElementById('addFaqModal');
        const backdrop = document.getElementById('modalBackdrop');
        
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('show');
        }
        
        if (backdrop) {
            backdrop.remove();
        }
        
        document.body.classList.remove('modal-open');
    };
    
    // Test modal on page load
    setTimeout(function() {
        console.log('Testing modal functionality...');
        console.log('jQuery loaded:', typeof $ !== 'undefined');
        console.log('Bootstrap modal available:', typeof $.fn.modal !== 'undefined');
        console.log('Modal element found:', $('#addFaqModal').length);
        
        if ($('#addFaqModal').length === 0) {
            console.error('Modal element not found!');
        }
    }, 1000);
});

// Test function for debugging
function testModal() {
    console.log('Testing modal functionality...');
    console.log('Modal element:', $('#addFaqModal'));
    console.log('Modal length:', $('#addFaqModal').length);
    console.log('Bootstrap modal method:', typeof $.fn.modal);
    
    if ($('#addFaqModal').length > 0) {
        console.log('Modal found, attempting to show...');
        $('#addFaqModal').modal('show');
    } else {
        console.error('Modal not found!');
        alert('Modal element not found!');
    }
}

function editFaq(faq) {
    console.log('editFaq called with:', faq);
    
    // Store the FAQ data globally for later use
    window.currentFaqData = faq;
    
    // Add a subtle animation effect
    const modal = $('#editFaqModal');
    modal.find('.modal-content').css('transform', 'scale(0.7)');
    
    // Populate form fields
    document.getElementById('edit_id').value = faq.id;
    document.getElementById('edit_page_type').value = faq.page_type;
    document.getElementById('edit_question').value = faq.question;
    document.getElementById('edit_sort_order').value = faq.sort_order;
    document.getElementById('edit_status').value = faq.status;
    
    // Set the textarea content first (fallback)
    document.getElementById('edit_answer').value = faq.answer;
    
    // Show modal with animation
    modal.modal('show');
    
    // Set CKEditor content after modal is shown and editor is ready
    setTimeout(function() {
        if (window.editEditor) {
            console.log('Setting editor content:', faq.answer);
            window.editEditor.setData(faq.answer);
        } else {
            console.log('Edit editor not available yet, retrying...');
            // Retry after a longer delay if editor is not ready
            setTimeout(function() {
                if (window.editEditor) {
                    console.log('Setting editor content on retry:', faq.answer);
                    window.editEditor.setData(faq.answer);
                } else {
                    console.error('Edit editor still not available');
                }
            }, 1000);
        }
        modal.find('input, select').first().focus();
    }, 800);
}

function deleteFaq(id) {
    // Create a more attractive confirmation dialog
    const confirmModal = `
        <div class="modal fade" id="deleteConfirmModal" tabindex="-1">
            <div class="modal-dialog modal-sm">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-exclamation-triangle"></i> Confirm Delete
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body text-center">
                        <i class="fas fa-trash fa-3x text-danger mb-3"></i>
                        <p>Are you sure you want to delete this FAQ?</p>
                        <p class="text-muted small">This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal" onclick="hideModalFallback()">Cancel</button>
                        <button type="button" class="btn btn-danger" onclick="confirmDelete(${id})">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    $('#deleteConfirmModal').remove();
    
    // Add modal to body and show
    $('body').append(confirmModal);
    $('#deleteConfirmModal').modal('show');
}

function confirmDelete(id) {
    // Close confirmation modal
    $('#deleteConfirmModal').modal('hide');
    
    // Show loading state
    const loadingToast = `
        <div class="toast" id="deleteLoadingToast" style="position: fixed; top: 20px; right: 20px; z-index: 9999;">
            <div class="toast-header bg-info text-white">
                <strong class="mr-auto">
                    <i class="fas fa-spinner fa-spin"></i> Deleting FAQ...
                </strong>
            </div>
        </div>
    `;
    
    $('body').append(loadingToast);
    $('.toast').toast('show');
    
    // Submit delete form
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="${id}">
    `;
    document.body.appendChild(form);
    form.submit();
}

// Preview Modal Functions
function showPreviewModal(title, content) {
    // Set the title
    document.getElementById('preview-title').textContent = title;
    
    // Set the content with proper styling
    const previewContent = document.getElementById('preview-content');
    previewContent.innerHTML = content;
    
    // Add some basic styling to make the preview look good
    previewContent.style.cssText = `
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        line-height: 1.6;
        color: #333;
        max-height: 500px;
        overflow-y: auto;
        padding: 20px;
        background: #fff;
        border-radius: 8px;
        border: 1px solid #e1e8ed;
    `;
    
    // Style the content elements
    const style = document.createElement('style');
    style.textContent = `
        .preview-content h1, .preview-content h2, .preview-content h3, 
        .preview-content h4, .preview-content h5, .preview-content h6 {
            color: #2c3e50;
            margin-top: 20px;
            margin-bottom: 10px;
            font-weight: 600;
        }
        .preview-content h1 { font-size: 28px; }
        .preview-content h2 { font-size: 24px; }
        .preview-content h3 { font-size: 20px; }
        .preview-content h4 { font-size: 18px; }
        .preview-content h5 { font-size: 16px; }
        .preview-content h6 { font-size: 14px; }
        .preview-content p {
            margin-bottom: 15px;
            text-align: justify;
        }
        .preview-content ul, .preview-content ol {
            margin-bottom: 15px;
            padding-left: 30px;
        }
        .preview-content li {
            margin-bottom: 5px;
        }
        .preview-content blockquote {
            border-left: 4px solid #667eea;
            padding-left: 20px;
            margin: 20px 0;
            font-style: italic;
            color: #666;
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 0 8px 8px 0;
        }
        .preview-content a {
            color: #667eea;
            text-decoration: none;
        }
        .preview-content a:hover {
            text-decoration: underline;
        }
        .preview-content strong {
            font-weight: 600;
            color: #2c3e50;
        }
        .preview-content em {
            font-style: italic;
        }
        .preview-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .preview-content table th,
        .preview-content table td {
            border: 1px solid #e1e8ed;
            padding: 12px;
            text-align: left;
        }
        .preview-content table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .preview-content img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin: 10px 0;
        }
    `;
    
    // Remove existing style if any
    const existingStyle = document.getElementById('preview-modal-style');
    if (existingStyle) {
        existingStyle.remove();
    }
    
    style.id = 'preview-modal-style';
    document.head.appendChild(style);
    
    // Show the modal
    $('#previewModal').modal('show');
}

function printPreview() {
    const previewContent = document.getElementById('preview-content').innerHTML;
    const title = document.getElementById('preview-title').textContent;
    
    // Create a new window for printing
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>${title}</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 800px;
                    margin: 0 auto;
                    padding: 20px;
                }
                h1, h2, h3, h4, h5, h6 {
                    color: #2c3e50;
                    margin-top: 20px;
                    margin-bottom: 10px;
                    font-weight: 600;
                }
                h1 { font-size: 28px; }
                h2 { font-size: 24px; }
                h3 { font-size: 20px; }
                h4 { font-size: 18px; }
                h5 { font-size: 16px; }
                h6 { font-size: 14px; }
                p {
                    margin-bottom: 15px;
                    text-align: justify;
                }
                ul, ol {
                    margin-bottom: 15px;
                    padding-left: 30px;
                }
                li {
                    margin-bottom: 5px;
                }
                blockquote {
                    border-left: 4px solid #667eea;
                    padding-left: 20px;
                    margin: 20px 0;
                    font-style: italic;
                    color: #666;
                    background: #f8f9fa;
                    padding: 15px 20px;
                    border-radius: 0 8px 8px 0;
                }
                a {
                    color: #667eea;
                    text-decoration: none;
                }
                strong {
                    font-weight: 600;
                    color: #2c3e50;
                }
                em {
                    font-style: italic;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 20px 0;
                }
                table th,
                table td {
                    border: 1px solid #e1e8ed;
                    padding: 12px;
                    text-align: left;
                }
                table th {
                    background: #f8f9fa;
                    font-weight: 600;
                }
                img {
                    max-width: 100%;
                    height: auto;
                    border-radius: 8px;
                    margin: 10px 0;
                }
                @media print {
                    body {
                        margin: 0;
                        padding: 15px;
                    }
                }
            </style>
        </head>
        <body>
            <h1>${title}</h1>
            ${previewContent}
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

// Add some interactive effects to table rows
$(document).ready(function() {
    $('.faq-table tbody tr').hover(
        function() {
            $(this).addClass('table-hover-effect');
        },
        function() {
            $(this).removeClass('table-hover-effect');
        }
    );
});
</script>

<?php include_once('footer.php'); ?>
