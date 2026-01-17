<?php

require('connect.php');
require('header.php');

?>

 
<div class="container">
   
<a href="index.php"><h3 class="back_btn"><i class="fa fa-arrow-circle-left"></i> back </h3></a>
 
	<table class="orders-table">
    <thead>
        <tr>
            <th>Card ID</th>
            <th>Company Name</th>
            <th>Payment Status</th>
            <th>Card Status</th>
            <th>Date</th>
            <th>Download Invoice</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE d_payment_status="Success" AND f_user_email="" ORDER BY id DESC');

        if (mysqli_num_rows($query) > 0) {
            while ($row = mysqli_fetch_array($query)) {
                echo '<tr>';
                echo '<td><a href="https://' . $_SERVER['HTTP_HOST'] . '/' . $row['card_id'] . '" target="_blank">' . $row['id'] . '</a></td>';
                echo '<td>' . $row['d_comp_name'] . ' <i class="fa fa-external-link"></i></td>';
                echo '<td>';

                if (!empty($row['f_user_email'])) {
                    echo 'Created';
                } else if ($row['d_payment_status'] == 'Created') {
                    echo 'Pending';
                } else {
                    echo $row['d_payment_status'];
                }

                echo '</td>';
                echo '<td>' . ($row['d_card_status'] == 'Active' ? 'Active' : 'Inactive') . '</td>';
                echo '<td>' . date("d-M-Y", strtotime($row['uploaded_date'])) . '</td>';
                echo '<td><a class="pay_now_btn" href="../panel/login/download_invoice.php?id=' . $row['id'] . '" target="_blank">Download Invoice</a></td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="6" class="no-data">No Data Available...</td></tr>';
        }
        ?>
    </tbody>
</table>
<style>
.orders-table {
    width: 100%;
    border-collapse: collapse;
    font-family: Arial, sans-serif;
    margin-top: 20px;
    font-size: 14px;
}

.orders-table th,
.orders-table td {
    border: 1px solid #ddd;
    padding: 10px;
    text-align: left;
    vertical-align: middle;
}

.orders-table th {
    background-color: #f5f5f5;
    font-weight: bold;
}

.orders-table tr:nth-child(even) {
    background-color: #fafafa;
}

.orders-table tr:hover {
    background-color: #f1f1f1;
}

.pay_now_btn {
    background-color: #007bff;
    color: white;
    padding: 6px 10px;
    border-radius: 4px;
    text-decoration: none;
    display: inline-block;
    font-size: 13px;
    transition: background-color 0.3s ease;
}

.pay_now_btn:hover {
    background-color: #0056b3;
}

.no-data {
    text-align: center;
    color: #666;
    padding: 15px;
    background-color: #e7f3fe;
}
</style>
