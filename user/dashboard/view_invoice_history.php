<?php
// Include centralized database config
require_once(__DIR__ . '/../../app/config/database.php');

// Check if user is logged in
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
    echo '<div class="alert alert-danger">Please login to view invoice history.</div>';
    exit;
}

// Check if card_id is provided
if (!isset($_GET['card_id']) || empty($_GET['card_id'])) {
    echo '<div class="alert alert-danger">Invalid request. Card ID is required.</div>';
    exit;
}

$card_id = mysqli_real_escape_string($connect, $_GET['card_id']);
$user_email = $_SESSION['user_email'];

// Verify that the card belongs to the logged-in user
$card_verify_query = mysqli_query($connect, "SELECT id, d_comp_name FROM digi_card WHERE id = '$card_id' AND user_email = '$user_email'");
if (mysqli_num_rows($card_verify_query) == 0) {
    echo '<div class="alert alert-danger">Access denied. This card does not belong to you.</div>';
    exit;
}

$card_data = mysqli_fetch_array($card_verify_query);
$company_name = $card_data['d_comp_name'];

// Get invoice history
$invoice_query = mysqli_query($connect, "SELECT * FROM invoice_details WHERE card_id = '$card_id' ORDER BY created_at DESC");

if (mysqli_num_rows($invoice_query) == 0) {
    echo '<div class="alert alert-info">No invoice history found for this card.</div>';
    exit;
}

?>

<div class="container-fluid px-0">
    <div class="row">
        <div class="col-12">
            <h4 class="mb-3 text-dark fw-bold">Invoice History for: <span class="text-primary"><?php echo htmlspecialchars($company_name); ?></span></h4>
            
                         <div class="table-responsive">
                 <table class="table table-striped table-hover border">
                     <thead class="table-primary">
                         <tr>
                             <th class="text-dark fw-bold">Invoice #</th>
                             <th class="text-dark fw-bold">Payment Date</th>
                             <th class="text-dark fw-bold">Download</th>
                         </tr>
                     </thead>
                    <tbody>
                                                 <?php while ($invoice = mysqli_fetch_array($invoice_query)) { ?>
                         <tr class="align-middle">
                             <td class="text-dark fw-semibold">
                                 <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($invoice['invoice_number']); ?></span>
                             </td>
                             <td class="text-dark">
                                 <div class="fw-semibold"><?php echo date('d-m-Y', strtotime($invoice['invoice_date'])); ?></div>
                                 <small class="text-muted"><?php echo date('h:i A', strtotime($invoice['created_at'])); ?></small>
                             </td>
                             <td>
                             <span class="download"><a target="_blank" href="download_invoice_new.php?id=<?php echo $invoice['id']; ?>" title="Download Invoice"><i class="fa-solid fa-arrow-down download_icon_style"></i></a></span>
                             </td>
                         </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            
                          
        </div>
    </div>
</div>

  <!-- Invoice Details Modal -->
 <div class="modal fade" id="invoiceDetailsModal" tabindex="-1" aria-labelledby="invoiceDetailsModalLabel" aria-hidden="true">
     <div class="modal-dialog modal-xl">
         <div class="modal-content">
             <div class="modal-header">
                 <h3 class="modal-title" id="invoiceDetailsModalLabel">
                     <i class="fas fa-file-invoice me-2"></i>Invoice Details
                 </h3>
                 <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
             </div>
             <div class="modal-body" id="invoiceDetailsContent">
                 <!-- Content will be loaded here -->
             </div>
             <div class="modal-footer">
                 <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                     <i class="fas fa-times me-1"></i>Close
                 </button>
             </div>
         </div>
     </div>
 </div>

 <style>
 /* Invoice History Modal Specific Styles - Won't affect other modals */
 #invoiceHistoryModal .modal-content {
     border: none;
     border-radius: 12px;
     box-shadow: 0 10px 30px rgba(0, 33, 105, 0.2);
 }
 
 #invoiceHistoryModal .modal-header {
     background: linear-gradient(135deg, #002169 0%, #1D2943 100%);
     color: white;
     border-radius: 12px 12px 0 0;
     border-bottom: 3px solid #ffbe17;
     padding: 20px 25px;
 }
 
 #invoiceHistoryModal .modal-title {
     color: white !important;
     font-size: 24px;
     font-weight: 700;
     margin: 0;
 }
 
 #invoiceHistoryModal .btn-close {
     filter: brightness(0) invert(1);
     opacity: 0.8;
 }
 
 #invoiceHistoryModal .btn-close:hover {
     opacity: 1;
 }
 
 #invoiceHistoryModal .modal-body {
     padding: 25px;
     background: #F2F4F9;
 }
 
 #invoiceHistoryModal .modal-footer {
     background: #F2F4F9;
     border-top: 1px solid #e9ecef;
     padding: 15px 25px;
     border-radius: 0 0 12px 12px;
 }
 
 #invoiceHistoryModal .table {
     background: white;
     border-radius: 8px;
     overflow: hidden;
     box-shadow: 0 2px 10px rgba(0, 33, 105, 0.1);
 }
 
 #invoiceHistoryModal .table thead {
     background: linear-gradient(135deg, #ffbe17 0%, #ffd54f 100%);
 }
 
 #invoiceHistoryModal .table thead th {
     color: #002169 !important;
     font-weight: 700;
     border: none;
     padding: 15px;
     font-size: 14px;
 }
 
 #invoiceHistoryModal .table tbody tr {
     border-bottom: 1px solid #f8f9fa;
     transition: all 0.3s ease;
 }
 
 #invoiceHistoryModal .table tbody tr:hover {
     background: #f8f9fa;
     transform: translateY(-1px);
     box-shadow: 0 2px 8px rgba(0, 33, 105, 0.1);
 }
 
 #invoiceHistoryModal .table tbody td {
     padding: 15px;
     vertical-align: middle;
     color: #1D2943;
     border: none;
 }
 
 #invoiceHistoryModal .badge {
     background: #002169 !important;
     color: white !important;
     font-weight: 600;
     padding: 8px 12px;
     border-radius: 6px;
     border: none;
 }
 
 #invoiceHistoryModal .btn-outline-primary {
     color: #002169;
     border-color: #002169;
     background: transparent;
     font-weight: 600;
     padding: 8px 16px;
     border-radius: 6px;
     transition: all 0.3s ease;
 }
 
 #invoiceHistoryModal .btn-outline-primary:hover {
     background: #002169;
     color: white;
     transform: translateY(-1px);
     box-shadow: 0 4px 12px rgba(0, 33, 105, 0.3);
 }
 
 #invoiceHistoryModal .bg-light {
     background: linear-gradient(135deg, #ffbe17 0%, #ffd54f 100%) !important;
     border: none;
     border-radius: 8px;
 }
 
 #invoiceHistoryModal .text-primary {
     color: #002169 !important;
 }
 
 #invoiceHistoryModal .text-dark {
     color: #1D2943 !important;
 }
 
 #invoiceHistoryModal .text-muted {
     color: #6c757d !important;
 }
 
 #invoiceHistoryModal .fw-bold {
     font-weight: 700 !important;
 }
 
 #invoiceHistoryModal .fw-semibold {
     font-weight: 600 !important;
 }
 
 /* Invoice Details Modal Specific Styles */
 #invoiceDetailsModal .modal-content {
     border: none;
     border-radius: 12px;
     box-shadow: 0 15px 40px rgba(0, 33, 105, 0.3);
 }
 
 #invoiceDetailsModal .modal-header {
     background: linear-gradient(135deg, #002169 0%, #1D2943 100%);
     color: white;
     border-radius: 12px 12px 0 0;
     border-bottom: 3px solid #ffbe17;
     padding: 25px 30px;
 }
 
 #invoiceDetailsModal .modal-title {
     color: white !important;
     font-size: 28px;
     font-weight: 700;
     margin: 0;
 }
 
 #invoiceDetailsModal .btn-close-white {
     filter: brightness(0) invert(1);
     opacity: 0.8;
 }
 
 #invoiceDetailsModal .btn-close-white:hover {
     opacity: 1;
 }
 
 #invoiceDetailsModal .modal-body {
     padding: 30px;
     background: #F2F4F9;
 }
 
 #invoiceDetailsModal .modal-footer {
     background: #F2F4F9;
     border-top: 1px solid #e9ecef;
     padding: 20px 30px;
     border-radius: 0 0 12px 12px;
 }
 
 #invoiceDetailsModal .btn-secondary {
     background: #6c757d;
     border-color: #6c757d;
     color: white;
     font-weight: 600;
     padding: 10px 20px;
     border-radius: 6px;
     transition: all 0.3s ease;
 }
 
 #invoiceDetailsModal .btn-secondary:hover {
     background: #5a6268;
     border-color: #545b62;
     transform: translateY(-1px);
     box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
 }
 </style>
 
 <script>
 function viewInvoiceDetails(invoiceId) {
     // Show loading
     document.getElementById('invoiceDetailsContent').innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
     
     // Show modal
     var modal = new bootstrap.Modal(document.getElementById('invoiceDetailsModal'));
     modal.show();
     
     // Fetch invoice details via AJAX
     fetch('view_invoice_details.php?invoice_id=' + invoiceId)
         .then(response => response.text())
         .then(data => {
             document.getElementById('invoiceDetailsContent').innerHTML = data;
         })
         .catch(error => {
             document.getElementById('invoiceDetailsContent').innerHTML = '<div class="alert alert-danger">Error loading invoice details: ' + error.message + '</div>';
         });
 }
 </script>
