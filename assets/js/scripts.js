
$(document).ready(function () {
    $(".upload-profile span").click(function (e) {
        e.preventDefault(); // Prevent default action if inside an <a> tag
        $(this).closest(".nav-item").find(".dropdown-menu").slideToggle(); // Toggle dropdown
    });

    // Optional: Close dropdown when clicking outside
    $(document).click(function (event) {
        if (!$(event.target).closest(".nav-item").length) {
            $(".dropdown-menu").slideUp();
        }
    });
});





// Remove automatic preview update - only update on successful upload
// $(".file-upload").on('change', function () {
//     readURL(this);
// });

$(".upload-button").on('click', function () {
    $("#profile_image").click();
});

window.addEventListener('DOMContentLoaded', event => {
    const sidebarToggle = document.body.querySelector('#sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', event => {
            event.preventDefault();
            
            document.body.classList.toggle('sb-sidenav-toggled');
            localStorage.setItem('sb|sidebar-toggle', document.body.classList.contains('sb-sidenav-toggled'));
        });
    }

});

// Profile image upload: use common ImageCropUpload (common/image_upload_crop_modal.php + assets/js/image_upload_crop.js)
$("#profile_image").on('change', function() {
    if (!this.files || !this.files[0]) return;
    var file = this.files[0];
    var currentOrigin = window.location.origin;
    var basePath = (typeof APP_BASE_PATH !== 'undefined') ? APP_BASE_PATH : (window.location.pathname.replace(/\/user(\/.*)?$/, '') || '');
    if (basePath === '/') basePath = '';
    var uploadUrl = (typeof UPLOAD_PROFILE_URL !== 'undefined' && UPLOAD_PROFILE_URL)
        ? (UPLOAD_PROFILE_URL.indexOf('http') === 0 ? UPLOAD_PROFILE_URL : currentOrigin + UPLOAD_PROFILE_URL)
        : (currentOrigin + basePath + '/common/upload_profile.php');
    if (typeof ImageCropUpload === 'undefined') {
        alert('Image upload is not loaded. Please refresh the page.');
        $(this).val('');
        return;
    }
    ImageCropUpload.open(file, {
        method: 'upload',
        uploadUrl: uploadUrl,
        uploadFieldName: 'profile_image',
        showLoading: true,
        title: 'Adjust & Crop Profile Image',
        onSuccess: function(response) {
            if (response && response.status === 'success') {
                alert('Profile image updated successfully!');
                var newImageSrc = currentOrigin + basePath + '/' + response.image_path + '?t=' + new Date().getTime();
                $('.profile-pic').attr('src', newImageSrc);
            } else {
                var msg = (response && response.message) ? response.message : 'Failed to upload image';
                alert($('<div>').html(msg).text());
            }
        },
        onError: function(msg) { alert(msg); }
    });
    $(this).val('');
});










