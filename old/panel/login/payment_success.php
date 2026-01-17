// When payment is successful and referral earning is created
if(isset($_SESSION['referrer_email'])) {
    $referrer_email = $_SESSION['referrer_email'];
    $is_collaboration = $_SESSION['is_collaboration'] ?? 'NO';
    $referred_email = $_SESSION['user_email'];
    
    // Insert referral earning with collaboration flag
    $insert_earning = mysqli_query($connect, "INSERT INTO referral_earnings 
        (referrer_email, referred_email, amount, status, referral_date, is_collaboration) 
        VALUES ('$referrer_email', '$referred_email', '$referral_amount', 'Pending', NOW(), '$is_collaboration')");
}