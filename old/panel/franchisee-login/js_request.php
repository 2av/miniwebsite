<?php
require('connect.php');

if(isset($_POST['id'])){
	$query=mysqli_query($connect,'SELECT * FROM digi_card2 WHERE id="'.$_POST['id'].'" ');
	$value=$_POST['d_pro_img'];
	if(mysqli_num_rows($query) > 0){
		$remove_img=mysqli_query($connect,"UPDATE digi_card2 SET d_pro_img$value='',d_pro_name$value='' WHERE id=".$_POST['id']." ");
		if($remove_img){echo '<div class="alert success">"'.$value.'" Image and discription is removed.</div>';}
	}else {
		echo '<div class="alert danger">Image Id is not available</div>';
	}
}
	
if(isset($_POST['id_gal'])){
	$query=mysqli_query($connect,'SELECT * FROM digi_card3 WHERE id="'.$_POST['id_gal'].'" ');
	$value=$_POST['d_gall_img'];
	if(mysqli_num_rows($query) > 0){
		$id_gal=mysqli_query($connect,"UPDATE digi_card3 SET d_gall_img$value='' WHERE id=".$_POST['id_gal']." ");
		if($id_gal){echo '<div class="alert success">"'.$value.'" Image is removed.</div>';}
	}else {
		echo '<div class="alert danger">Image Id is not available</div>';
	}
}
	
	// activate or deactivate users card from digi card
if(isset($_POST['activate_user'])){
	$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE id="'.$_POST['card_id'].'" AND d_card_status="Active"');
	
	if(mysqli_num_rows($query) > 0){
		$id_gal=mysqli_query($connect,"UPDATE digi_card SET d_card_status='Inactive' WHERE id=".$_POST['card_id']." ");
		if($id_gal){echo '<span class="alert danger">Inactive</div>';}
	}else {
		$id_gal=mysqli_query($connect,"UPDATE digi_card SET d_card_status='Active' WHERE id=".$_POST['card_id']." ");
		if($id_gal){echo '<span class="alert success">Active</div>';}
	}
}
	


// my account details change
if(isset($_POST['my_account'])){
	echo 'Yes';
	
}



?>