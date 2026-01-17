<?php
// Ensure user_referral_code is available for footer scripts
if (!isset($user_referral_code)) {
    $user_referral_code = $_SESSION['user_referral_code'] ?? '';
    // If still empty, try to get from team_members table
    if (empty($user_referral_code) && !empty($_SESSION['user_email']) && isset($connect)) {
        $user_email = $_SESSION['user_email'];
        $team_stmt = $connect->prepare("SELECT referral_code FROM team_members WHERE member_email = ?");
        if ($team_stmt) {
            $team_stmt->bind_param("s", $user_email);
            $team_stmt->execute();
            $team_result = $team_stmt->get_result();
            $team_data = $team_result->fetch_assoc();
            $team_stmt->close();
            if ($team_data && !empty($team_data['referral_code'])) {
                $user_referral_code = $team_data['referral_code'];
            }
        }
    }
}
?>
        
      
      
      <footer class="py-4 bg-blue mt-auto">
    <div class="container-fluid px-4">
        <div class="d-flex align-items-center justify-content-between Copyright">
            <div class="Copyright-left">www.miniwebsite.in</div>
            <div class="Copyright-right">
                <p>Copyright &copy; 2025. All Rights Reserved.</p>
            </div>
        </div>
    </div>
</footer>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.3.1.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Cropper.js -->
    <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.js"></script>
    <script src="../../customer/assets/js/scripts.js"></script>
    <script>
        function copyToClipboard(type) {
            let textToCopy = '';
            
            switch(type) {
                case 'link':
                case 'regular_link':
                    textToCopy = 'https://miniwebsite.in/panel/login/create-account.php?ref=<?php echo $user_referral_code; ?>';
                    break;
                case 'code':
                case 'regular_code':
                    textToCopy = '<?php echo $user_referral_code; ?>';
                    break;
                case 'collab_link':
                    textToCopy = 'https://miniwebsite.in/panel/login/create-franchisee-account.php?ref=<?php echo $user_referral_code; ?>';
                    break;
                case 'collab_code':
                    textToCopy = '<?php echo $user_referral_code; ?>';
                    break;
                default:
                    textToCopy = type === 'link' ? 'https://miniwebsite.in/panel/login/create-account.php?ref=<?php echo $user_referral_code; ?>' : '<?php echo $user_referral_code; ?>';
            }
            
            navigator.clipboard.writeText(textToCopy).then(() => {
                if(type.includes('link')) {
                    alert('Referral link copied!');
                } else {
                    alert('Referral code copied!');
                }
            }).catch(err => {
                console.error('Failed to copy: ', err);
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = textToCopy;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert(type.includes('link') ? 'Referral link copied!' : 'Referral code copied!');
            });
        }
    </script>

</body>

</html>



