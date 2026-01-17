<?php
// Enhanced session validation
if(!isset($_SESSION['user_email']) || !isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
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
if(isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 3600)) {
    // Update login time if session is older than 1 hour
    $_SESSION['login_time'] = time();
}
?>


<header id="header">
    <meta property="og:image" content="<?php if(!empty($row['d_logo'])){echo 'panel/'.str_replace('../','',$row['d_logo_location']);} ?>">
	<div class="logo" onclick="location.href='index.php'">
		<img src="images/digital-logo-final.png"><h3>&nbsp;</h3>
	</div>
	<div class="mobile_home">&equiv;</div>
	<div class="head_txt">
		<h3><?php
		if(isset($_SESSION['user_name'])){
		echo 'Hi! '.$_SESSION['user_name'];
		}else {echo 'Hi! Guest';}
		?>
		</h3>
		<h3>
		<a href="index.php"><i class="fa fa-home"></i> Home</a>

		</h3>
		<h3>
		<a href="logout.php"><i class="fa fa-sign-out"></i> Logout</a>

		</h3>
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

<style>

</style>
<div id="alert_display_full">
	<div id="loader1"></div>
	<h3>Loading...</h3>

</div>