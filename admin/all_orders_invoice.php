<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once(__DIR__ . '/../app/config/database.php');
require('header.php');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>All Orders (Payments)</title>


<div class="container-fluid" style="padding:20px;">
    <div class="row">
        <div class="col-12">
            <div class="card" style="border:none;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center" style="gap:10px;">
                            <button type="button" class="btn btn-outline-secondary" onclick="history.back()"><i class="fas fa-arrow-left"></i> Back</button>
                            <h4 class="mb-0">All Orders</h4>
                        </div>
                        <form method="GET" class="d-flex" style="gap:10px;">
                            <input type="text" class="form-control" name="search" placeholder="Search email/name/invoice no." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                            <button class="btn btn-primary" type="submit">Search</button>
                        </form>
                    </div>

                    <div class="table-card">
                        <div class="table-responsive table-container">
                            <table class="table table-striped table-hover modern-table" style="text-align: center;">
                                <thead class="bg-secondary">
                                    <tr>
                                        <th>USER ID/FR ID</th>
                                        <th>MW ID</th>
                                        <th>User Payment Status</th>
                                        <th>Total Order Value</th>
                                        <th>Invoice</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                if(isset($_GET['page_no'])){ } else { $_GET['page_no'] = '1'; }
                                $limit = 30;
                                $start_from = ((int)$_GET['page_no']-1)*$limit;

                                $where = [];
                                if(isset($_GET['search']) && $_GET['search']!=''){
                                    $s = mysqli_real_escape_string($connect, $_GET['search']);
                                    $where[] = "(id.user_email LIKE '%$s%' OR id.user_name LIKE '%$s%' OR id.invoice_number LIKE '%$s%')";
                                }
                                $whereClause = !empty($where) ? ('WHERE '.implode(' AND ', $where)) : '';

                                $sql = "SELECT id.* FROM invoice_details id $whereClause ORDER BY id.id DESC LIMIT $start_from, $limit";
                                $q = mysqli_query($connect, $sql);

                                if($q && mysqli_num_rows($q)>0){
                                    while($inv = mysqli_fetch_array($q)){
                                        $user_email = $inv['user_email'] ?? '';
                                        $card_id = $inv['card_id'] ?? '';
                                        $service_name = $inv['service_name'] ?? '';
                                        $payment_status = $inv['payment_status'] ?? '';
                                        $invoice_date = isset($inv['invoice_date']) ? $inv['invoice_date'] : '';
                                        $total_amount = $inv['total_amount'] ?? '';

                                        // USER ID / FR ID display - using user_details
                                        $uid = '-';
                                        if($user_email !== ''){
                                            // Check if customer
                                            $u_q = mysqli_query($connect, "SELECT id, role FROM user_details WHERE email='".mysqli_real_escape_string($connect, $user_email)."' AND role='CUSTOMER' LIMIT 1");
                                            if($u_q && mysqli_num_rows($u_q)>0){ 
                                                $u = mysqli_fetch_array($u_q); 
                                                $uid = str_pad(intval($u['id']), 5, '0', STR_PAD_LEFT); 
                                            }
                                        }

                                        // Determine Franchisee and MW ID separately
                                        $id_display = $uid;
                                        $mw_id_display = '';
                                        if(!empty($card_id)){
                                            $mw_id_display = intval($card_id);
                                        }
                                        // If not MW, try to show Franchisee ID
                                        $is_fr = false;
                                        if(isset($inv['payment_type']) && $inv['payment_type']==='Franchisee'){ $is_fr = true; }
                                        if(!$is_fr && stripos($service_name, 'Franchisee') !== false){ $is_fr = true; }
                                        if($is_fr && $user_email!==''){
                                            // Query user_details for franchisee
                                            $fr_q = mysqli_query($connect, "SELECT id FROM user_details WHERE email='".mysqli_real_escape_string($connect, $user_email)."' AND role='FRANCHISEE' LIMIT 1");
                                            if($fr_q && mysqli_num_rows($fr_q)>0){ $fr = mysqli_fetch_array($fr_q); $id_display = 'FR - '.intval($fr['id']); }
                                        }

                                        // Invoice link
                                        $invoice_link = '<a href="invoice_admin_access.php?invoice_id='.intval($inv['id']).'" target="_blank" class="download-btn" title="Download Invoice"><i class="fa fa-download"></i></a>';

                                        echo '<tr>';
                                        echo '<td>'.$id_display.'</td>';
                                        echo '<td>'.$mw_id_display.'</td>';
                                        // Normalize payment status display and include paid date if available
                                        $statusCell = '-';
                                        $ps = trim((string)$payment_status);
                                        $paidDateNorm = '';
                                        if($invoice_date && $invoice_date !== '0000-00-00 00:00:00'){
                                            $paidDateNorm = date('d-m-Y', strtotime($invoice_date));
                                        }
                                        if($ps !== ''){
                                            if(stripos($ps, 'Paid on') === 0){
                                                // Extract date part after 'Paid on '
                                                $paidDate = trim(substr($ps, strlen('Paid on')));
                                                if($paidDate === '' && $paidDateNorm !== ''){ $paidDate = $paidDateNorm; }
                                                $statusCell = '<span class="badge bg-success">Paid</span>';
                                                if($paidDate !== ''){
                                                    $statusCell .= '<div><small class="text-muted">on '.htmlspecialchars($paidDate).'</small></div>';
                                                }
                                            } else if(strcasecmp($ps, 'Success') === 0 || strcasecmp($ps, 'Paid') === 0){
                                                $statusCell = '<span class="">Paid on '.htmlspecialchars($paidDateNorm).'</span>';
                                               
                                            } else if(strcasecmp($ps, 'Failed') === 0 || strcasecmp($ps, 'Failure') === 0){
                                                $statusCell = '<span class="badge bg-danger">Failed</span>';
                                            } else if(strcasecmp($ps, 'Pending') === 0 || strcasecmp($ps, 'Initiated') === 0){
                                                $statusCell = '<span class="badge bg-warning">Pending</span>';
                                            } else if(strcasecmp($ps, 'Refunded') === 0){
                                                $statusCell = '<span class="badge bg-info">Refunded</span>';
                                            } else {
                                                $statusCell = htmlspecialchars($ps);
                                            }
                                        }
                                        echo '<td>'.$statusCell.'</td>';
                                        echo '<td>'.('₹'.number_format((float)$total_amount, 2)).'</td>';
                                        echo '<td class="invoice-cell">'.$invoice_link.'</td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="5" class="text-center py-4">No invoices found</td></tr>';
                                }
                                ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="pagination-modern">
                        <?php 
                        $cntSql = "SELECT COUNT(*) as c FROM invoice_details id ".($whereClause? $whereClause : '');
                        $cntQ = mysqli_query($connect, $cntSql);
                        $total_rows = ($cntQ && ($cr = mysqli_fetch_array($cntQ))) ? (int)$cr['c'] : 0;
                        $pages = ($limit>0) ? ceil($total_rows/$limit) : 1;
                        for($i=1;$i<=$pages;$i++){
                            $params = $_GET; $params['page_no']=$i; $href='?'.http_build_query($params);
                            if($_GET['page_no']==$i){ echo '<a href="'.$href.'" class="page-btn-modern active">'.$i.'</a>'; } else { echo '<a href="'.$href.'" class="page-btn-modern">'.$i.'</a>'; }
                        }
                        ?>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>
</head>
</html>





