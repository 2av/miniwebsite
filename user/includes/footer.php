        
      
      
      <!-- Footer (modernized in Phase A · Step 3) -->
      <footer class="py-4 bg-blue mt-auto !bg-white !border-t !border-border" role="contentinfo">
          <div class="container-fluid px-4 !max-w-7xl !mx-auto">
              <div class="Copyright !flex !flex-col sm:!flex-row !items-center !justify-center sm:!justify-between !gap-2 !text-sm !text-center sm:!text-left">
                  <div class="Copyright-left !w-full sm:!w-auto">
                      <a class="!inline-flex !items-center !justify-center sm:!justify-start !gap-1.5 !w-full sm:!w-auto !font-normal !text-slate-600 hover:!text-[var(--mw-color-nav-active,#2b4ba9)] !no-underline focus:outline-none focus-visible:!ring-2 focus-visible:!ring-primary/50 !rounded-md transition-colors"
                         href="https://www.miniwebsite.in"
                         target="_blank"
                         rel="noopener noreferrer">
                          <i class="fa fa-globe" aria-hidden="true"></i>
                          <span>www.miniwebsite.in</span>
                      </a>
                  </div>
                  <div class="Copyright-right !w-full sm:!w-auto">
                      <p class="!m-0">
                          &copy; <?php echo date('Y'); ?> Mini Website. All Rights Reserved.
                      </p>
                  </div>
              </div>
          </div>
      </footer>
        </div>
    </div>

    <?php if (isset($current_dir) && $current_dir === 'website'): ?>
    <style id="mw-website-form-controls-foot">
        /* Overrides page-level legacy styles in user/website/*.php */
        body.mw-website-builder .form-control,
        body.mw-website-builder input.form-control,
        body.mw-website-builder select.form-control,
        body.mw-website-builder textarea.form-control,
        body.mw-website-builder .form-control-sm,
        body.mw-website-builder .operation-locations-chips-field.form-control,
        body.mw-website-builder .Personal-Details .form-group .form-control,
        body.mw-website-builder .paysection .form-control,
        body.mw-website-builder .BankDetails .form-control,
        body.mw-website-builder .modal .form-control {
            font-weight: 400 !important;
        }
        body.mw-website-builder .form-control::placeholder {
            font-weight: 400 !important;
        }
        body.mw-website-builder select.form-control option {
            font-weight: 400;
        }
        body.mw-website-builder .mw-btn-row .mw-btn-back,
        body.mw-website-builder .mw-btn-row a.mw-btn-back,
        body.mw-website-builder .mw-btn-row .mw-btn-next,
        body.mw-website-builder .mw-btn-row a.mw-btn-next,
        body.mw-website-builder .Product-ServicesBtn.mw-btn-row .mw-btn-back,
        body.mw-website-builder .Product-ServicesBtn.mw-btn-row .mw-btn-next,
        body.mw-website-builder .Product-ServicesBtn.mw-btn-row a.mw-btn-back,
        body.mw-website-builder .Product-ServicesBtn.mw-btn-row a.mw-btn-next {
            background-color: #5c6b7a !important;
            border-color: #5c6b7a !important;
            color: #fff !important;
            font-size: 20px !important;
        }
        body.mw-website-builder .mw-btn-row .mw-btn-back:hover,
        body.mw-website-builder .mw-btn-row a.mw-btn-back:hover,
        body.mw-website-builder .mw-btn-row .mw-btn-next:hover,
        body.mw-website-builder .mw-btn-row a.mw-btn-next:hover,
        body.mw-website-builder .Product-ServicesBtn.mw-btn-row .mw-btn-back:hover,
        body.mw-website-builder .Product-ServicesBtn.mw-btn-row .mw-btn-next:hover {
            background-color: #4d5966 !important;
            border-color: #4d5966 !important;
            color: #fff !important;
        }
        body.mw-website-builder .mw-btn-row .mw-btn-save,
        body.mw-website-builder .mw-btn-row .save_btn,
        body.mw-website-builder .Product-ServicesBtn.mw-btn-row .mw-btn-save,
        body.mw-website-builder .Product-ServicesBtn.mw-btn-row .save_btn {
            background-color: #ffbe17 !important;
            border-color: #ffbe17 !important;
            color: #0f172a !important;
            font-size: 20px !important;
        }
        body.mw-website-builder .mw-btn-row .mw-btn-save:hover,
        body.mw-website-builder .mw-btn-row .save_btn:hover,
        body.mw-website-builder .Product-ServicesBtn.mw-btn-row .mw-btn-save:hover,
        body.mw-website-builder .Product-ServicesBtn.mw-btn-row .save_btn:hover {
            background-color: #e6ab15 !important;
            border-color: #e6ab15 !important;
            color: #0f172a !important;
        }
        body.mw-website-builder .Product-ServicesBtn.mw-btn-row .mw-btn span:not(.mw-btn-angle):not(.angle),
        body.mw-website-builder .Product-ServicesBtn.mw-btn-row .save_btn span:not(.mw-btn-angle):not(.angle) {
            font-size: inherit !important;
            color: inherit !important;
        }
        /* Upload preview clear (×) — same as payment-details .delImg */
        body.mw-website-builder .logo-placeholder,
        body.mw-website-builder .product-image-preview-modal,
        body.mw-website-builder .image-preview-modal,
        body.mw-website-builder .offer-image-preview-modal {
            position: relative;
        }
        body.mw-website-builder .delImg {
            position: absolute;
            top: 6px;
            right: 6px;
            z-index: 3;
            width: 28px;
            height: 28px;
            display: none;
            align-items: center;
            justify-content: center;
            background: #f6364a;
            color: #fff;
            border-radius: 50%;
            cursor: pointer;
            font-size: 14px;
            line-height: 1;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.2);
        }
        body.mw-website-builder .delImg.is-visible {
            display: flex;
        }
        body.mw-website-builder .delImg:hover {
            background: #d92d40;
        }
    </style>
    <?php endif; ?>

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
    <?php
    /* Phase B · Step 16 — Legacy website-step-nav.css was deleted; its rules
       now live in the design system in user/includes/header.php
       (mobile .mw-btn-row layout + Product-ServicesTable scroll rules). */
    ?>
    <script src="<?php echo $assets_base; ?>/assets/js/mw_modal.js"></script>
    <script src="<?php echo $assets_base; ?>/assets/js/mw_upload_clear.js"></script>
    <script src="<?php echo $assets_base; ?>/assets/js/image_upload_crop.js"></script>
    <script src="<?php echo $assets_base; ?>/assets/js/scripts.js"></script>
    <?php
    // Mount point for JS-created modals (MwModal.create / confirm / alert)
    echo '<div id="mw-modal-root" aria-hidden="true"></div>';

    // Get referral code if available (for customers)
    $user_referral_code = '';
    if (isset($current_role) && $current_role == 'CUSTOMER') {
        $user_referral_code = $_SESSION['user_referral_code'] ?? '';
    }
    ?>

</body>

</html>






