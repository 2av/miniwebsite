        
      
      
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

</body>

</html>






