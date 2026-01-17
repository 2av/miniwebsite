
<header id="header">
	<div class="logo" onclick="location.href='index.php'">
	<img src="../images/Miniwebsite logo.png?">
		 <h3>Admin Dashboard</h3>
	</div>
	<div class="mobile_home">&equiv;</div>
	<div class="head_txt">
		<div class="menu-item">
			<a href="index.php" class="menu-link">
				<i class="fas fa-home"></i>
				<span>Home</span>
			</a>
		</div>
		<div class="menu-item user-greeting">
			<i class="fas fa-user-circle"></i>
			<span><?php
			if(isset($_SESSION['admin_name'])){
			echo 'Hi! '.$_SESSION['admin_name'];
			}else {echo 'Hi! Guest';}
			?></span>
		</div>
		
		<div class="menu-item">
			<?php
			if(isset($_SESSION['admin_email'])){
			echo '<a href="my_account.php" class="menu-link"><i class="fas fa-cog"></i><span>Settings</span></a>';
			}
			?>
		</div>
		<div class="menu-item">
			<a href="teams-management.php" class="menu-link">
				<i class="fas fa-users"></i>
				<span>Teams Management</span>
			</a>
		</div>
		
		<div class="menu-item">
			<a href="logout.php" class="menu-link logout-link">
				<i class="fas fa-sign-out-alt"></i>
				<span>Logout</span>
			</a>
		</div>
	</div>
	
	<!-- Font Awesome for icons -->
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
	
	<!-- Bootstrap CSS -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
	<!-- Common Admin CSS -->
	<link href="assets/css/common-admin.css" rel="stylesheet">
	
	<!-- jQuery -->
	<script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
	
	<!-- Bootstrap JS -->
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct" crossorigin="anonymous"></script>
	
	<!-- Notification Bell - Only show on dashboard -->
	<?php 
	$current_page = basename($_SERVER['PHP_SELF']);
	if ($current_page == 'index.php'): 
	?>
	<div class="notification-section">
		<?php include_once 'includes/notification_dropdown.php'; ?>
	</div>
	<?php endif; ?>
	
	<!-- Page Loader -->
	<div id="pageLoader" class="page-loader">
		<div class="loader-content">
			 
			<div class="loader-text mt-3">
				<h5>Loading ...</h5>
				<p class="text-muted">Please wait while we prepare everything</p>
			</div>
			<div class="loading-dots">
				<span></span>
				<span></span>
				<span></span>
			</div>
		</div>
	</div>
	

</header>

<style>
/* Notification section styling */
.notification-section {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 9999;
}

.notification-section .btn-link {
    color: #333;
    text-decoration: none;
    padding: 8px 12px;
    border-radius: 50%;
    transition: all 0.3s ease;
}

.notification-section .btn-link:hover {
    background-color: rgba(0,0,0,0.1);
    color: #007bff;
}

.notification-section .badge {
    font-size: 0.7rem;
    padding: 4px 6px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .notification-section {
        right: 10px;
    }
    
    .notification-section .btn-link {
        padding: 6px 8px;
    }
}

/* Ensure notification section is always visible */
.notification-section {
    pointer-events: auto;
    visibility: visible;
    opacity: 1;
}

/* Fix any potential conflicts with other elements */
.notification-section * {
    position: relative;
    z-index: inherit;
}

/* Clean notification section styling */
.notification-section {
    background: transparent;
    border: none;
}

/* Enhanced Header Menu Styling */
.head_txt {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-right: 80px; /* Space for notification bell */
    margin-top: -70px;
}

.menu-item {
    display: flex;
    align-items: center;
    position: relative;
}

.menu-link {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    color: #495057;
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.3s ease;
    font-weight: 500;
    font-size: 0.9rem;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.menu-link:hover {
    background: rgba(255, 255, 255, 0.2);
    color: #007bff;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.15);
    text-decoration: none;
}

.menu-link i {
    font-size: 1rem;
    width: 20px;
    text-align: center;
}

.menu-link span {
    white-space: nowrap;
}

.user-greeting {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    color: #495057;
    font-weight: 500;
    font-size: 0.9rem;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
}

.user-greeting i {
    font-size: 1.2rem;
    color: #007bff;
}

.logout-link {
    background: rgba(220, 53, 69, 0.1) !important;
    border-color: rgba(220, 53, 69, 0.2) !important;
    color: #dc3545 !important;
    margin-top: 10px;
}

.logout-link:hover {
    background: rgba(220, 53, 69, 0.2) !important;
    color: #c82333 !important;
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.15) !important;
}

/* Responsive adjustments for menu */
@media (max-width: 768px) {
    .head_txt {
        gap: 1rem;
        margin-right: 60px;
    }
    
    .menu-link, .user-greeting {
        padding: 0.5rem 0.75rem;
        font-size: 0.8rem;
    }
    
    .menu-link span, .user-greeting span {
        display: none; /* Hide text on mobile, show only icons */
    }
    
    .menu-link i, .user-greeting i {
        font-size: 1.1rem;
    }
}

/* Page Loader Styling - Enhanced Design */
.page-loader {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
}

.page-loader::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
    opacity: 0.3;
    animation: float 20s ease-in-out infinite;
}

.page-loader.hidden {
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transform: scale(0.95);
}

.loader-content {
    text-align: center;
    padding: 3rem 2.5rem;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 25px;
    box-shadow: 
        0 25px 50px rgba(0, 0, 0, 0.15),
        0 0 0 1px rgba(255, 255, 255, 0.2),
        inset 0 1px 0 rgba(255, 255, 255, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.2);
    position: relative;
    animation: slideUp 0.8s ease-out;
    max-width: 400px;
    width: 90%;
}

 

.loader-content .spinner-border {
    width: 4rem;
    height: 4rem;
    border-width: 0.3rem;
    border-color: #667eea;
    border-right-color: transparent;
    animation: spin 1s linear infinite, pulse 2s ease-in-out infinite;
}

.loader-text {
    margin-top: 2rem;
    animation: fadeInUp 0.8s ease-out 0.3s both;
}

.loader-text h5 {
    color: #2c3e50;
    margin-bottom: 0.8rem;
    font-weight: 700;
    font-size: 1.4rem;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

.loader-text p {
    margin-bottom: 0;
    font-size: 1rem;
    color: #7f8c8d;
    line-height: 1.5;
}

/* Enhanced Animations */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

@keyframes slideUp {
    0% {
        opacity: 0;
        transform: translateY(30px) scale(0.9);
    }
    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes fadeInUp {
    0% {
        opacity: 0;
        transform: translateY(20px);
    }
    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes borderGlow {
    0%, 100% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
}

@keyframes float {
    0%, 100% {
        transform: translateY(0px);
    }
    50% {
        transform: translateY(-20px);
    }
}

/* Loading dots animation */
.loading-dots {
    display: inline-block;
    margin-top: 1rem;
}

.loading-dots span {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #667eea;
    margin: 0 3px;
    animation: dots 1.4s ease-in-out infinite both;
}

.loading-dots span:nth-child(1) { animation-delay: -0.32s; }
.loading-dots span:nth-child(2) { animation-delay: -0.16s; }
.loading-dots span:nth-child(3) { animation-delay: 0s; }

@keyframes dots {
    0%, 80%, 100% {
        transform: scale(0);
        opacity: 0.5;
    }
    40% {
        transform: scale(1);
        opacity: 1;
    }
}

/* Responsive design */
@media (max-width: 768px) {
    .loader-content {
        padding: 2rem 1.5rem;
        margin: 1rem;
        border-radius: 20px;
    }
    
    .loader-content .spinner-border {
        width: 3rem;
        height: 3rem;
    }
    
    .loader-text h5 {
        font-size: 1.2rem;
    }
    
    .loader-text p {
        font-size: 0.9rem;
    }
}
</style>

<script>

$(document).ready(function(){
	$('.mobile_home').on('click',function(){
		$('#header').toggleClass('add_height');
		
	})
})

</script>

<script>
// Page Loader Functionality
document.addEventListener('DOMContentLoaded', function() {
    const pageLoader = document.getElementById('pageLoader');
    
    // Hide loader after a minimum time to prevent flickering
    setTimeout(function() {
        if (pageLoader) {
            pageLoader.classList.add('hidden');
            
            // Remove loader completely after animation
            setTimeout(function() {
                if (pageLoader && pageLoader.classList.contains('hidden')) {
                    pageLoader.remove();
                }
            }, 500);
        }
    }, 1000); // Show loader for at least 1 second
});

// Show loader on page navigation
window.addEventListener('beforeunload', function() {
    if (document.getElementById('pageLoader')) {
        document.getElementById('pageLoader').classList.remove('hidden');
    }
});

// Show loader on form submission
$(document).ready(function(){
    $("form").submit(function(){
        $('#pageLoader').classList.remove('hidden');
        $('#alert_display_full').css('display','block');
    });
});

// Global loader functions for AJAX operations
window.showPageLoader = function(message = 'Loading...') {
    const pageLoader = document.getElementById('pageLoader');
    if (pageLoader) {
        const loaderText = pageLoader.querySelector('.loader-text h5');
        if (loaderText) {
            loaderText.textContent = message;
        }
        pageLoader.classList.remove('hidden');
    }
};

window.hidePageLoader = function() {
    const pageLoader = document.getElementById('pageLoader');
    if (pageLoader) {
        pageLoader.classList.add('hidden');
        setTimeout(function() {
            if (pageLoader && pageLoader.classList.contains('hidden')) {
                pageLoader.remove();
            }
        }, 500);
    }
};
</script>


<div id="alert_display_full">
	<div id="loader1"></div>
	<h3>Loading...</h3>

</div>