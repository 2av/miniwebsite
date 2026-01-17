<?php

require('connect.php');

// Handle product/service deletion (NEW: from card_products_services table)
if(isset($_POST['product_id']) && isset($_POST['action']) && $_POST['action'] == 'delete_product'){
    $product_id = intval($_POST['product_id']);
    $delete_query = mysqli_query($connect, "DELETE FROM card_products_services WHERE id=$product_id");
    if($delete_query){
        echo '<div class="alert success">Product/Service has been removed successfully.</div>';
    } else {
        echo '<div class="alert danger">Failed to remove product/service. Error: ' . mysqli_error($connect) . '</div>';
    }
}

// Handle product pricing deletion (NEW: from card_product_pricing table)
if(isset($_POST['product_id']) && isset($_POST['action']) && $_POST['action'] == 'delete_product_pricing'){
    $product_id = intval($_POST['product_id']);
    $delete_query = mysqli_query($connect, "DELETE FROM card_product_pricing WHERE id=$product_id");
    if($delete_query){
        echo '<div class="alert success">Product has been removed successfully.</div>';
    } else {
        echo '<div class="alert danger">Failed to remove product. Error: ' . mysqli_error($connect) . '</div>';
    }
}

// Handle product/service deletion (OLD: from digi_card2 table - for backward compatibility)
if(isset($_POST['id']) && isset($_POST['d_pro_img']) && !isset($_POST['action'])){
    $query = mysqli_query($connect, 'SELECT * FROM digi_card2 WHERE id="'.$_POST['id'].'" ');
    $value = $_POST['d_pro_img'];
    if(mysqli_num_rows($query) > 0){
        $remove_img = mysqli_query($connect, "UPDATE digi_card2 SET d_pro_img$value='', d_pro_name$value='' WHERE id=".$_POST['id']." ");
        if($remove_img){
            echo '<div class="alert success">"'.$value.'" Product/Service has been removed successfully.</div>';
        } else {
            echo '<div class="alert danger">Failed to remove product/service. Error: ' . mysqli_error($connect) . '</div>';
        }
    } else {
        echo '<div class="alert danger">Product ID is not available</div>';
    }
}

// Handle gallery image deletion (NEW: from card_image_gallery table)
if(isset($_POST['image_id']) && isset($_POST['action']) && $_POST['action'] == 'delete_gallery_image'){
    $image_id = intval($_POST['image_id']);
    $delete_query = mysqli_query($connect, "DELETE FROM card_image_gallery WHERE id=$image_id");
    if($delete_query){
        echo '<div class="alert success">Gallery image has been removed successfully.</div>';
    } else {
        echo '<div class="alert danger">Failed to remove gallery image. Error: ' . mysqli_error($connect) . '</div>';
    }
}

// Handle gallery image deletion (OLD: from digi_card3 table - for backward compatibility)
if(isset($_POST['id_gal']) && isset($_POST['d_gall_img']) && !isset($_POST['action'])){
    $query = mysqli_query($connect, 'SELECT * FROM digi_card3 WHERE id="'.$_POST['id_gal'].'" ');
    $value = $_POST['d_gall_img'];
    if(mysqli_num_rows($query) > 0){
        $remove_img = mysqli_query($connect, "UPDATE digi_card3 SET d_gall_img$value='' WHERE id=".$_POST['id_gal']." ");
        if($remove_img){
            echo '<div class="alert success">"'.$value.'" Gallery image has been removed successfully.</div>';
        } else {
            echo '<div class="alert danger">Failed to remove gallery image. Error: ' . mysqli_error($connect) . '</div>';
        }
    } else {
        echo '<div class="alert danger">Gallery ID is not available</div>';
    }
}

// Handle QR code deletion
if(isset($_POST['qr_id']) && isset($_POST['qr_num'])){
    $query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE id="'.$_POST['qr_id'].'"');
    if(mysqli_num_rows($query) > 0){
        $qr_field = '';
        if($_POST['qr_num'] == 1) {
            $qr_field = 'd_qr_paytm';
        } else if($_POST['qr_num'] == 2) {
            $qr_field = 'd_qr_google_pay';
        } else if($_POST['qr_num'] == 3) {
            $qr_field = 'd_qr_phone_pay';
        }
        
        if(!empty($qr_field)) {
            $remove_qr = mysqli_query($connect, "UPDATE digi_card SET $qr_field='' WHERE id=".$_POST['qr_id']);
            if($remove_qr){
                echo '<div class="alert success">QR code has been removed successfully.</div>';
            } else {
                echo '<div class="alert danger">Failed to remove QR code. Error: ' . mysqli_error($connect) . '</div>';
            }
        } else {
            echo '<div class="alert danger">Invalid QR code number.</div>';
        }
    } else {
        echo '<div class="alert danger">Card ID is not available.</div>';
    }
}

// Handle e-commerce product deletion (from create_card7.php)
if(isset($_POST['id']) && isset($_POST['pro_img'])){
    $query = mysqli_query($connect, 'SELECT * FROM products WHERE id="'.$_POST['id'].'" ');
    $value = $_POST['pro_img'];
    if(mysqli_num_rows($query) > 0){
        $remove_img = mysqli_query($connect, "UPDATE products SET 
            pro_img$value='', 
            pro_name$value='',
            pro_mrp$value='',
            pro_price$value='',
            pro_tax$value=''
            WHERE id=".$_POST['id']." ");
            
        if($remove_img){
            echo '<div class="alert success">Product #'.$value.' has been removed successfully.</div>';
        } else {
            echo '<div class="alert danger">Failed to remove product. Error: ' . mysqli_error($connect) . '</div>';
        }
    } else {
        echo '<div class="alert danger">Product ID is not available</div>';
    }
}
?>
