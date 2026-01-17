        
      
      
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
    <script src="../../common/assets/js/scripts.js"></script>
    <script>
      // Initialize dropdown functionality for franchisee
      $(document).ready(function() {
          // Prevent dropdown toggle when clicking on upload button or camera icon
          // Use event delegation and stop propagation early
          $(document).on('click', '.upload-button, .p-image', function(e) {
              e.stopPropagation();
              e.preventDefault();
              // The scripts.js will handle the actual file input click
              // But we trigger it here to ensure it works
              setTimeout(function() {
                  $('#profile_image').click();
              }, 10);
              return false;
          });
          
          // Custom dropdown toggle for profile menu (only on name/span click, not upload area)
          $('.upload-profile-wrap .nav-link').on('click', function(e) {
              // Don't toggle if clicking on upload area
              if ($(e.target).closest('.p-image, .upload-button, #profile_image').length) {
                  e.preventDefault();
                  e.stopPropagation();
                  return false;
              }
              // Don't toggle if clicking on the span (name area) - handle separately
              if ($(e.target).closest('.upload-profile span').length) {
                  return true; // Let the span handler deal with it
              }
              e.preventDefault();
              e.stopPropagation();
              var dropdown = $(this).next('.dropdown-menu');
              $('.dropdown-menu').not(dropdown).removeClass('show');
              dropdown.toggleClass('show');
          });
          
          // Toggle dropdown on span click (name area) - but not if clicking on upload button
          $('.upload-profile span').on('click', function(e) {
              // Don't toggle if clicking on upload area
              if ($(e.target).closest('.p-image, .upload-button, #profile_image').length) {
                  return false;
              }
              e.preventDefault();
              e.stopPropagation();
              var dropdown = $(this).closest('.upload-profile-wrap').find('.dropdown-menu');
              $('.dropdown-menu').not(dropdown).removeClass('show');
              dropdown.toggleClass('show');
          });
          
          // Close dropdown when clicking on menu items
          $('.dropdown-item').on('click', function() {
              $(this).closest('.dropdown-menu').removeClass('show');
          });
          
          // Close dropdown when clicking outside
          $(document).on('click', function(e) {
              if (!$(e.target).closest('.upload-profile-wrap').length) {
                  $('.dropdown-menu').removeClass('show');
              }
          });
          
          // Close dropdown on escape key
          $(document).on('keydown', function(e) {
              if (e.key === 'Escape') {
                  $('.dropdown-menu').removeClass('show');
              }
          });
          
          // Smooth scrolling for dropdown
          $('.scrollable-dropdown').on('wheel', function(e) {
              e.preventDefault();
              $(this).scrollTop($(this).scrollTop() + e.originalEvent.deltaY);
          });
      });
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.min.js"></script>
    <script src="../../common/assets/js/demo/chart-area-demo.js"></script>
    <script src="../../common/assets/js/demo/chart-bar-demo.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js"></script>
    <script src="../../common/assets/js/datatables-simple-demo.js"></script>
</body>
</html>



