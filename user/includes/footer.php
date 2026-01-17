        
      
      
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
    <?php
    // Get assets base path (same function as in header)
    if (!function_exists('get_assets_base_path')) {
        function get_assets_base_path() {
            $script_name = $_SERVER['SCRIPT_NAME'];
            $script_dir = dirname($script_name);
            // Get base path (remove /user or /user/dashboard or /user/...)
            $base = preg_replace('#/user(/.*)?$#', '', $script_dir);
            // Ensure it ends with / or is empty for root
            return $base === '/' ? '' : $base;
        }
    }
    $assets_base = get_assets_base_path();
    ?>
    <script src="<?php echo $assets_base; ?>/assets/js/scripts.js"></script>
    <?php
    // Get referral code if available (for customers)
    $user_referral_code = '';
    if (isset($current_role) && $current_role == 'CUSTOMER') {
        $user_referral_code = $_SESSION['user_referral_code'] ?? '';
    }
    ?>
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






