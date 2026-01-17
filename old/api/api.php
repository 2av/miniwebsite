<?php
	
date_default_timezone_set("Asia/Kolkata");

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Configure session parameters
    $session_lifetime = 86400; // 24 hours in seconds
    $secure = isset($_SERVER['HTTPS']); // Set to true if using HTTPS
    $httponly = true; // Prevents JavaScript access to session cookie

    // Set the session cookie parameters
    session_set_cookie_params([
        'lifetime' => $session_lifetime,
        'path' => '/',
        'domain' => '',  // Current domain
        'secure' => $secure,
        'httponly' => $httponly,
        'samesite' => 'Lax'  // Allows session to persist across same-site requests
    ]);
    
    session_start();
}

if($_SERVER['HTTP_HOST']=="localhost"){
    $connect=mysqli_connect("localhost","root","","mydigibr_card") or die ('Database not available...');
} else {
    $connect=mysqli_connect("localhost","onbvn_card","onbvn_card","onbvn_card") or die ('Connection issue #567844 Error');
}

$date=date('Y-m-d H:i:s');


if(isset($_GET['token']) && isset($_GET['ref_id'])){
			
			// check if id and password matches 
			$query=mysqli_query($connect,'SELECT * FROM franchisee_login WHERE  id="'.$_GET['ref_id'].'"');
			if(mysqli_num_rows($query)==0){
					// if id does not exist 
					$result=array(
					'error'=> 'Error, token or id2'
					
					);
			}else {
				
				$row=mysqli_fetch_array($query);
				$password=md5($row['f_user_password']);
					if($_GET['token']==$password){
						
						//success status 
						
						$query2=mysqli_query($connect,'SELECT * FROM franchisee_login WHERE id="'.$_GET['ref_id'].'"');
						$query3=mysqli_query($connect,'SELECT * FROM digi_card WHERE f_user_email="'.$row['f_user_email'].'"');
					    $result= while($row3=mysqli_fetch_array($query3)){
							
							array('status', 'OK');
							array('card_id', $row3['id']);
													
						 };
						 
			
			
			// authentication success ends
					}else {
						$result=array(
							'status'=> '401'
							
							);
					}
				
				
			}
			
			
		}else {
			
			$result=array(
			'error'=> 'Error, token or id'
			
			);
			
}
			
			


 echo json_encode($result);

header('Content-Type: application/json');




?>

