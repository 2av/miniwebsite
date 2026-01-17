<?php
// Enhanced session validation
if(!isset($_SESSION['f_user_email']) || !isset($_SESSION['f_is_logged_in']) || $_SESSION['f_is_logged_in'] !== true) {
    // Clear any potentially corrupted session data
    session_unset();
    session_destroy();

    // Start a new session
    session_start();

    // Redirect to login page
    header('Location:login.php?session=expired');
    exit;
}

// Refresh session periodically to prevent timeout
if(isset($_SESSION['f_login_time']) && (time() - $_SESSION['f_login_time'] > 3600)) {
    // Update login time if session is older than 1 hour
    $_SESSION['f_login_time'] = time();
}
?>


<header id="header">
	<div class="logo" onclick="location.href='index.php'">
			<img src="images/Miniwebsite logo.png?">
	</div>
	<div class="mobile_home">&equiv;</div>
	<div class="head_txt">
		
		
		<h3><?php
		if(isset($_SESSION['f_user_name'])){
		echo 'Hi! '.$_SESSION['f_user_name'];
		}else {echo 'Hi! Guest';}
		?>
		</h3>
		<h3><img src="<?php
		$queryq=mysqli_query($connect,"SELECT * FROM franchisee_login WHERE f_user_email='$_SESSION[f_user_email]'");
		$rowq=mysqli_fetch_array($queryq);
		if(!empty($rowq['f_user_image'])){echo 'data:image/*;base64,'.base64_encode($rowq['f_user_image']);}else {echo 'images/profile.png';} ?>"></h3>


	</div>


</header>
<script>

$(document).ready(function(){
	$('.mobile_home').on('click',function(){
		$('#header').toggleClass('add_height');

	})
})

</script>


<script>
$(document).ready(function(){
  $("form").submit(function(){
    $('#alert_display_full').css('display','block');
  });
});
</script>


<div id="alert_display_full">
	<div id="loader1"></div>
	<h3>Loading...</h3>

</div>