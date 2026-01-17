<?php
require_once('../../common/config.php');

// Check if user is logged in
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
    echo '<div class="alert alert-danger">Please login to view invoice details.</div>';
    exit;
}

// Check if invoice_id is provided
if (!isset($_GET['invoice_id']) || empty($_GET['invoice_id'])) {
    echo '<div class="alert alert-danger">Invalid request. Invoice ID is required.</div>';
    exit;
}

$invoice_id = mysqli_real_escape_string($connect, $_GET['invoice_id']);
$user_email = $_SESSION['user_email'];

// Get invoice details and verify ownership
$invoice_query = mysqli_query($connect, "SELECT id.*, dc.d_comp_name, dc.d_f_name, dc.d_l_name 
                                        FROM invoice_details id 
                                        LEFT JOIN digi_card dc ON id.card_id = dc.id 
                                        WHERE id.id = '$invoice_id' AND id.user_email = '$user_email'");

if (mysqli_num_rows($invoice_query) == 0) {
    echo '<div class="alert alert-danger">Invoice not found or access denied.</div>';
    exit;
}

$invoice = mysqli_fetch_array($invoice_query);

?>

<div class="invoice-details-container">
    <!-- Invoice Header -->
    <div class="invoice-header">
        <div class="invoice-title">
            <h2><i class="fas fa-file-invoice"></i> Invoice Details</h2>
            <div class="invoice-number"><?php echo htmlspecialchars($invoice['invoice_number']); ?></div>
        </div>
        <div class="invoice-status">
            <?php if ($invoice['payment_status'] == 'Success') { ?>
                <span class="status-badge success">Paid</span>
            <?php } else { ?>
                <span class="status-badge warning"><?php echo htmlspecialchars($invoice['payment_status']); ?></span>
            <?php } ?>
        </div>
    </div>

    <!-- Invoice Information Grid -->
    <div class="invoice-grid">
        <!-- Invoice Information -->
        <div class="info-section">
            <h3><i class="fas fa-info-circle"></i> Invoice Information</h3>
            <div class="info-table">
                <div class="info-row">
                    <span class="label">Invoice Number:</span>
                    <span class="value"><?php echo htmlspecialchars($invoice['invoice_number']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Invoice Date:</span>
                    <span class="value"><?php echo date('d-m-Y', strtotime($invoice['invoice_date'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Payment Date:</span>
                    <span class="value"><?php echo date('d-m-Y H:i A', strtotime($invoice['payment_date'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Payment Method:</span>
                    <span class="value"><?php echo htmlspecialchars($invoice['payment_type']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Reference Number:</span>
                    <span class="value"><?php echo htmlspecialchars($invoice['reference_number']); ?></span>
                </div>
            </div>
        </div>

        <!-- Customer Information -->
        <div class="info-section">
            <h3><i class="fas fa-user"></i> Customer Information</h3>
            <div class="info-table">
                <div class="info-row">
                    <span class="label">Customer Name:</span>
                    <span class="value"><?php echo htmlspecialchars($invoice['user_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Email:</span>
                    <span class="value"><?php echo htmlspecialchars($invoice['user_email']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Contact:</span>
                    <span class="value"><?php echo htmlspecialchars($invoice['user_contact']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Company:</span>
                    <span class="value"><?php echo htmlspecialchars($invoice['d_comp_name']); ?></span>
                </div>
            </div>
        </div>

        <!-- Billing Information -->
        <div class="info-section full-width">
            <h3><i class="fas fa-map-marker-alt"></i> Billing Information</h3>
            <div class="billing-grid">
                <div class="billing-left">
                    <div class="info-row">
                        <span class="label">Billing Name:</span>
                        <span class="value"><?php echo htmlspecialchars($invoice['billing_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Billing Email:</span>
                        <span class="value"><?php echo htmlspecialchars($invoice['billing_email']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Billing Contact:</span>
                        <span class="value"><?php echo htmlspecialchars($invoice['billing_contact']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">GST Number:</span>
                        <span class="value"><?php echo htmlspecialchars($invoice['billing_gst_number']); ?></span>
                    </div>
                </div>
                <div class="billing-right">
                    <div class="info-row">
                        <span class="label">Address:</span>
                        <span class="value"><?php echo htmlspecialchars($invoice['billing_address']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">City:</span>
                        <span class="value"><?php echo htmlspecialchars($invoice['billing_city']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">State:</span>
                        <span class="value"><?php echo htmlspecialchars($invoice['billing_state']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Pincode:</span>
                        <span class="value"><?php echo htmlspecialchars($invoice['billing_pincode']); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Service Details -->
    <div class="service-section">
        <h3><i class="fas fa-cogs"></i> Service Details</h3>
        <div class="service-table">
            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>HSN/SAC</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo htmlspecialchars($invoice['service_description']); ?></td>
                        <td><?php echo htmlspecialchars($invoice['hsn_sac_code']); ?></td>
                        <td><?php echo htmlspecialchars($invoice['quantity']); ?></td>
                        <td>₹<?php echo number_format($invoice['unit_price'], 2); ?></td>
                        <td>₹<?php echo number_format($invoice['total_price'], 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Payment Summary -->
    <div class="payment-summary">
        <div class="summary-table">
            <div class="summary-row">
                <span class="label">Sub Total:</span>
                <span class="value">₹<?php echo number_format($invoice['sub_total'], 2); ?></span>
            </div>
            <?php if ($invoice['igst_amount'] > 0) { ?>
            <div class="summary-row">
                <span class="label">IGST (<?php echo $invoice['igst_percentage']; ?>%):</span>
                <span class="value">₹<?php echo number_format($invoice['igst_amount'], 2); ?></span>
            </div>
            <?php } ?>
            <?php if ($invoice['promo_discount'] > 0) { ?>
            <div class="summary-row discount">
                <span class="label">Discount:</span>
                <span class="value">-₹<?php echo number_format($invoice['promo_discount'], 2); ?></span>
            </div>
            <?php } ?>
            <div class="summary-row total">
                <span class="label">Total Amount:</span>
                <span class="value">₹<?php echo number_format($invoice['total_amount'], 2); ?></span>
            </div>
        </div>
    </div>

    <!-- Payment Gateway Details -->
    <?php if (!empty($invoice['razorpay_payment_id'])) { ?>
    <div class="gateway-section">
        <h3><i class="fas fa-credit-card"></i> Payment Gateway Details</h3>
        <div class="info-table">
            <div class="info-row">
                <span class="label">Razorpay Order ID:</span>
                <span class="value"><?php echo htmlspecialchars($invoice['razorpay_order_id']); ?></span>
            </div>
            <div class="info-row">
                <span class="label">Razorpay Payment ID:</span>
                <span class="value"><?php echo htmlspecialchars($invoice['razorpay_payment_id']); ?></span>
            </div>
            <div class="info-row">
                <span class="label">Created At:</span>
                <span class="value"><?php echo date('d-m-Y H:i:s', strtotime($invoice['created_at'])); ?></span>
            </div>
        </div>
    </div>
    <?php } ?>

    <!-- Action Buttons -->
    <div class="action-buttons">
        <a href="../../panel/login/payment_page/download_invoice.php?card_id=<?php echo $invoice['card_id']; ?>&invoice_id=<?php echo $invoice['id']; ?>" 
           class="btn-download" target="_blank">
            <i class="fas fa-download"></i> Download Invoice
        </a>
        <button type="button" class="btn-close" data-bs-dismiss="modal">
            <i class="fas fa-times"></i> Close
        </button>
    </div>
</div>

<style>
/* Invoice Details Specific Styles - Won't affect other components */
.invoice-details-container {
    background: #F2F4F9;
    border-radius: 12px;
    padding: 0;
    font-family: "Baloo Bhai 2", sans-serif;
    color: #1D2943;
}

/* Invoice Header */
.invoice-header {
    background: linear-gradient(135deg, #002169 0%, #1D2943 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 12px 12px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 3px solid #ffbe17;
}

.invoice-title h2 {
    margin: 0;
    font-size: 24px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
}

.invoice-number {
    font-size: 18px;
    font-weight: 600;
    margin-top: 5px;
    opacity: 0.9;
}

.status-badge {
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 14px;
}

.status-badge.success {
    background: #28a745;
    color: white;
}

.status-badge.warning {
    background: #ffc107;
    color: #1D2943;
}

/* Invoice Grid */
.invoice-grid {
    padding: 30px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

.info-section {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0, 33, 105, 0.1);
}

.info-section.full-width {
    grid-column: 1 / -1;
}

.info-section h3 {
    color: #002169;
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
    border-bottom: 2px solid #ffbe17;
    padding-bottom: 10px;
}

.info-table {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f8f9fa;
}

.info-row:last-child {
    border-bottom: none;
}

.label {
    font-weight: 600;
    color: #1D2943;
    min-width: 120px;
}

.value {
    color: #6c757d;
    text-align: right;
    font-weight: 500;
}

/* Billing Grid */
.billing-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

/* Service Section */
.service-section {
    padding: 0 30px 30px;
}

.service-section h3 {
    color: #002169;
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.service-table {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0, 33, 105, 0.1);
}

.service-table table {
    width: 100%;
    border-collapse: collapse;
}

.service-table th {
    background: linear-gradient(135deg, #ffbe17 0%, #ffd54f 100%);
    color: #002169;
    font-weight: 700;
    padding: 15px;
    text-align: left;
    border: none;
}

.service-table td {
    padding: 15px;
    border-bottom: 1px solid #f8f9fa;
    color: #1D2943;
}

.service-table tr:last-child td {
    border-bottom: none;
}

/* Payment Summary */
.payment-summary {
    padding: 0 30px 30px;
}

.summary-table {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0, 33, 105, 0.1);
    max-width: 400px;
    margin-left: auto;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f8f9fa;
}

.summary-row:last-child {
    border-bottom: none;
}

.summary-row.total {
    font-weight: 700;
    font-size: 18px;
    color: #002169;
    border-top: 2px solid #ffbe17;
    margin-top: 10px;
    padding-top: 15px;
}

.summary-row.discount .value {
    color: #28a745;
}

/* Gateway Section */
.gateway-section {
    padding: 0 30px 30px;
}

.gateway-section h3 {
    color: #002169;
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Action Buttons */
.action-buttons {
    padding: 20px 30px 30px;
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    background: white;
    border-radius: 0 0 12px 12px;
    border-top: 1px solid #e9ecef;
}

.btn-download, .btn-close {
    padding: 12px 24px;
    border-radius: 6px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    font-size: 14px;
}

.btn-download {
    background: #002169;
    color: white;
}

.btn-download:hover {
    background: #1D2943;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 33, 105, 0.3);
    color: white;
}

.btn-close {
    background: #6c757d;
    color: white;
}

.btn-close:hover {
    background: #5a6268;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
}

/* Responsive Design */
@media (max-width: 768px) {
    .invoice-header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .invoice-grid {
        grid-template-columns: 1fr;
        gap: 20px;
        padding: 20px;
    }
    
    .billing-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .service-section,
    .payment-summary,
    .gateway-section {
        padding-left: 20px;
        padding-right: 20px;
    }
    
    .action-buttons {
        flex-direction: column;
        padding: 20px;
    }
    
    .summary-table {
        margin: 0 auto;
    }
}
</style>