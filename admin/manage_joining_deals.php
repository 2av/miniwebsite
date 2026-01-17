<?php
require_once(__DIR__ . '/../app/config/database.php');
require('header.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Joining Deals Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>

<div class="container-fluid" style="padding:20px;">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4><i class="fas fa-handshake me-2"></i>Joining Deals Management</h4>
                    <div>
                        <button class="btn btn-success me-2" onclick="openUpgradeOrderModal()">
                            <i class="fas fa-sort me-1"></i>Manage Upgrade Order
                        </button>
                        <button class="btn btn-primary" onclick="refreshData()">
                            <i class="fas fa-sync-alt me-1"></i>Refresh
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    
                    <!-- Filters -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Status Filter</label>
                            <select class="form-select" id="statusFilter" onchange="filterData()">
                                <option value="">All Status</option>
                                <option value="ACTIVE">Active</option>
                                <option value="PENDING_PAYMENT">Pending Payment</option>
                                <option value="EXPIRED">Expired</option>
                                <option value="PAYMENT_FAILED">Payment Failed</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Deal Type</label>
                            <select class="form-select" id="dealFilter" onchange="filterData()">
                                <option value="">All Deals</option>
                                <option value="CREATOR">Creator</option>
                                <option value="BASIC_FREE">Basic Free</option>
                                <option value="STANDARD">Standard</option>
                                <option value="PREMIUM">Premium</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Payment Status</label>
                            <select class="form-select" id="paymentFilter" onchange="filterData()">
                                <option value="">All Payments</option>
                                <option value="PENDING">Pending</option>
                                <option value="PAID">Paid</option>
                                <option value="FAILED">Failed</option>
                                <option value="REFUNDED">Refunded</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" id="searchInput" placeholder="Search by email or name" onkeyup="filterData()">
                        </div>
                    </div>

                    <!-- Data Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>User Email</th>
                                    <th>Deal Name</th>
                                    <th>Start Date</th>
                                    <th>Expiry Date</th>
                                    <th>Days Remaining</th>
                                    <th>Payment Status</th>
                                    <th>Amount</th>
                                    <th>Transaction ID</th>
                                    <th>Invoice</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="dealsTableBody">
                                <!-- Data will be loaded here -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center" id="pagination">
                            <!-- Pagination will be generated here -->
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Update Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Payment Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="paymentForm">
                    <input type="hidden" id="mappingId">
                    
                    <div class="mb-3">
                        <label class="form-label">Payment Status</label>
                        <select class="form-select" id="paymentStatus" required>
                            <option value="PENDING">Pending</option>
                            <option value="PAID">Paid</option>
                            <option value="FAILED">Failed</option>
                            <option value="REFUNDED">Refunded</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Transaction ID</label>
                        <input type="text" class="form-control" id="transactionId" placeholder="Enter transaction ID">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Invoice ID</label>
                        <input type="number" class="form-control" id="invoiceId" placeholder="Enter invoice ID">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Amount Paid</label>
                        <input type="number" class="form-control" id="amountPaid" step="0.01" placeholder="Enter amount paid">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="updatePayment()">Update Payment</button>
            </div>
        </div>
    </div>
</div>

<!-- Upgrade Order Management Modal -->
<div class="modal fade" id="upgradeOrderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-sort me-2"></i>Manage Deal Upgrade Order
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>How it works:</strong> Lower numbers = lower tier deals, Higher numbers = higher tier deals. 
                    Users can only upgrade to deals with higher numbers.
                </div>
                
                <div id="upgradeOrderContainer">
                    <div class="text-center">
                        <i class="fas fa-spinner fa-spin"></i> Loading deals...
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="saveUpgradeOrder()">
                    <i class="fas fa-save me-1"></i>Save Order
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentPage = 1;
let currentFilters = {};

// Load data on page load
document.addEventListener('DOMContentLoaded', function() {
    loadData();
});

// Load joining deals data
function loadData(page = 1) {
    currentPage = page;
    
    const filters = {
        status: document.getElementById('statusFilter').value,
        deal: document.getElementById('dealFilter').value,
        payment: document.getElementById('paymentFilter').value,
        search: document.getElementById('searchInput').value,
        page: page
    };
    
    fetch('get_joining_deals_data.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(filters)
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            displayData(data.deals);
            displayPagination(data.pagination);
        } else {
            alert('Error loading data: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error loading data');
    });
}

// Display data in table
function displayData(deals) {
    const tbody = document.getElementById('dealsTableBody');
    tbody.innerHTML = '';
    
    deals.forEach(deal => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${deal.id}</td>
            <td>${deal.user_email}</td>
            <td>${deal.deal_name}</td>
            <td>${formatDate(deal.start_date)}</td>
            <td>${formatDate(deal.expiry_date)}</td>
            <td>
                <span class="badge ${deal.days_remaining > 30 ? 'bg-success' : deal.days_remaining > 0 ? 'bg-warning' : 'bg-danger'}">
                    ${deal.days_remaining} days
                </span>
            </td>
            <td>
                <span class="badge ${getPaymentBadgeClass(deal.payment_status)}">
                    ${deal.payment_status}
                </span>
            </td>
            <td>₹${parseFloat(deal.amount_paid).toFixed(2)}</td>
            <td>${deal.transaction_id || '-'}</td>
            <td>${deal.invoice_id ? `<a href="invoice_admin_access.php?invoice_id=${deal.invoice_id}" target="_blank">${deal.invoice_id}</a>` : '-'}</td>
            <td>
                <span class="badge ${getStatusBadgeClass(deal.deal_status)}">
                    ${deal.deal_status}
                </span>
            </td>
            <td>
                <button class="btn btn-sm btn-primary" onclick="openPaymentModal(${deal.id})">
                    <i class="fas fa-edit"></i> Update Payment
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

// Get payment badge class
function getPaymentBadgeClass(status) {
    switch(status) {
        case 'PAID': return 'bg-success';
        case 'PENDING': return 'bg-warning';
        case 'FAILED': return 'bg-danger';
        case 'REFUNDED': return 'bg-secondary';
        default: return 'bg-secondary';
    }
}

// Get status badge class
function getStatusBadgeClass(status) {
    switch(status) {
        case 'ACTIVE': return 'bg-success';
        case 'PENDING_PAYMENT': return 'bg-warning';
        case 'EXPIRED': return 'bg-danger';
        case 'PAYMENT_FAILED': return 'bg-danger';
        default: return 'bg-secondary';
    }
}

// Format date
function formatDate(dateString) {
    if(!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-IN');
}

// Filter data
function filterData() {
    loadData(1);
}

// Refresh data
function refreshData() {
    loadData(currentPage);
}

// Open payment modal
function openPaymentModal(mappingId) {
    document.getElementById('mappingId').value = mappingId;
    const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
    modal.show();
}

// Update payment
function updatePayment() {
    const mappingId = document.getElementById('mappingId').value;
    const paymentStatus = document.getElementById('paymentStatus').value;
    const transactionId = document.getElementById('transactionId').value;
    const invoiceId = document.getElementById('invoiceId').value;
    const amountPaid = document.getElementById('amountPaid').value;
    
    const formData = new FormData();
    formData.append('update_joining_deal_payment', '1');
    formData.append('mapping_id', mappingId);
    formData.append('payment_status', paymentStatus);
    formData.append('transaction_id', transactionId);
    formData.append('invoice_id', invoiceId);
    formData.append('amount_paid', amountPaid);
    
    fetch('update_joining_deal_payment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(result => {
        if(result.includes('success')) {
            alert('Payment status updated successfully!');
            bootstrap.Modal.getInstance(document.getElementById('paymentModal')).hide();
            loadData(currentPage);
        } else {
            alert('Error: ' + result);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating payment status');
    });
}

// Display pagination
function displayPagination(pagination) {
    const paginationContainer = document.getElementById('pagination');
    paginationContainer.innerHTML = '';
    
    for(let i = 1; i <= pagination.totalPages; i++) {
        const li = document.createElement('li');
        li.className = `page-item ${i === pagination.currentPage ? 'active' : ''}`;
        li.innerHTML = `<a class="page-link" href="#" onclick="loadData(${i})">${i}</a>`;
        paginationContainer.appendChild(li);
    }
}

// Upgrade Order Management Functions
function openUpgradeOrderModal() {
    const modal = new bootstrap.Modal(document.getElementById('upgradeOrderModal'));
    modal.show();
    loadUpgradeOrderData();
}

function loadUpgradeOrderData() {
    const container = document.getElementById('upgradeOrderContainer');
    container.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading deals...</div>';
    
    fetch('get_deals_for_upgrade_order.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        }
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            displayUpgradeOrderData(data.deals);
        } else {
            container.innerHTML = '<div class="alert alert-danger">Error loading deals: ' + data.message + '</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        container.innerHTML = '<div class="alert alert-danger">Error loading deals</div>';
    });
}

function displayUpgradeOrderData(deals) {
    const container = document.getElementById('upgradeOrderContainer');
    
    if(deals.length === 0) {
        container.innerHTML = '<div class="alert alert-warning">No deals found.</div>';
        return;
    }
    
    // Sort deals by current upgrade_order
    deals.sort((a, b) => a.upgrade_order - b.upgrade_order);
    
    let html = '<div class="list-group">';
    
    deals.forEach((deal, index) => {
        const tierClass = index === 0 ? 'list-group-item-danger' : 
                         index === deals.length - 1 ? 'list-group-item-success' : 
                         'list-group-item-warning';
        
        html += `
            <div class="list-group-item ${tierClass} d-flex justify-content-between align-items-center">
                <div class="flex-grow-1">
                    <h6 class="mb-1">${deal.deal_name}</h6>
                    <small class="text-muted">Code: ${deal.deal_code} | Fees: ₹${parseInt(deal.total_fees).toLocaleString()}</small>
                </div>
                <div class="d-flex align-items-center">
                    <span class="badge bg-primary me-2">Order: ${deal.upgrade_order}</span>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-outline-secondary" 
                                onclick="changeOrder(${deal.id}, 'up')" 
                                ${index === 0 ? 'disabled' : ''}>
                            <i class="fas fa-arrow-up"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" 
                                onclick="changeOrder(${deal.id}, 'down')" 
                                ${index === deals.length - 1 ? 'disabled' : ''}>
                            <i class="fas fa-arrow-down"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

function changeOrder(dealId, direction) {
    const container = document.getElementById('upgradeOrderContainer');
    const currentDeal = container.querySelector(`[onclick*="changeOrder(${dealId}"]`);
    const currentRow = currentDeal.closest('.list-group-item');
    
    if(direction === 'up') {
        const prevRow = currentRow.previousElementSibling;
        if(prevRow) {
            currentRow.parentNode.insertBefore(currentRow, prevRow);
        }
    } else {
        const nextRow = currentRow.nextElementSibling;
        if(nextRow) {
            currentRow.parentNode.insertBefore(nextRow, currentRow);
        }
    }
    
    // Update button states
    updateButtonStates();
}

function updateButtonStates() {
    const rows = document.querySelectorAll('.list-group-item');
    rows.forEach((row, index) => {
        const upBtn = row.querySelector('[onclick*="up"]');
        const downBtn = row.querySelector('[onclick*="down"]');
        
        if(upBtn) upBtn.disabled = index === 0;
        if(downBtn) downBtn.disabled = index === rows.length - 1;
    });
}

function saveUpgradeOrder() {
    const rows = document.querySelectorAll('.list-group-item');
    const orders = [];
    
    rows.forEach((row, index) => {
        const dealId = row.querySelector('[onclick*="changeOrder"]').onclick.toString().match(/changeOrder\((\d+)/)[1];
        orders.push({
            deal_id: dealId,
            upgrade_order: index + 1
        });
    });
    
    const saveBtn = document.querySelector('[onclick="saveUpgradeOrder()"]');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';
    saveBtn.disabled = true;
    
    fetch('update_upgrade_order.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ orders: orders })
    })
    .then(response => response.json())
    .then(data => {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
        
        if(data.success) {
            alert('Upgrade order saved successfully!');
            bootstrap.Modal.getInstance(document.getElementById('upgradeOrderModal')).hide();
        } else {
            alert('Error saving order: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
        alert('Error saving order');
    });
}
</script>

</body>
</html>


