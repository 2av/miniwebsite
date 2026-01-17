<?php 
include '../header.php';

// Determine which kit category to show
$kit_category = isset($_GET['kit']) && $_GET['kit'] == 'franchise_sales' ? 'franchise_sales' : 'sales';
$kit_title = $kit_category == 'franchise_sales' ? 'Franchisee Sales Kit' : 'Sales Kit';

// Get all active kit items for the selected category
$kit_items_query = "SELECT * FROM franchisee_kit WHERE status = 'active' AND category = '" . mysqli_real_escape_string($connect, $kit_category) . "' ORDER BY display_order ASC, created_at DESC";
$kit_items_result = mysqli_query($connect, $kit_items_query);
$kit_items = [];
if ($kit_items_result) {
    while ($row = mysqli_fetch_assoc($kit_items_result)) {
        $kit_items[] = $row;
    }
}

// Separate by type
$images = array_filter($kit_items, function($item) {
    return $item['type'] == 'image';
});

$videos = array_filter($kit_items, function($item) {
    return $item['type'] == 'video';
});

$files = array_filter($kit_items, function($item) {
    return $item['type'] == 'file';
});
?>

<main class="Dashboard">
    <div class="container-fluid customer_content_area">
        <div class="main-top">
        <span class="heading">Sales Kit</span>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                  <li class="breadcrumb-item"><a href="../dashboard/">Mini Website </a></li>
                  <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($kit_title); ?></li>
                </ol>
            </nav>                              
        </div>
       
        <div class="card mb-4">
            <div class="card-body">
                <div class="FranchiseeDashboard-head">
                    <h2 class="mb-3 heading2"><?php echo htmlspecialchars($kit_title); ?></h2>
                    <p class="text-muted mb-4 sub_title">You can download these data & share with your customers which will help you grow.</p>
                    
                    <!-- Images Section -->
                    <?php if (!empty($images)): ?>
                    <div class="row mb-5">
                        <div class="col-12">
                            <h4 class="mb-3 font22">Promotional Images</h4>
                            <div class="row">
                                <?php foreach ($images as $image): ?>
                                <div class="col-md-3 col-sm-6 mb-4">
                                    <div class="">
                                    <div class=" h-100">
                                        <img src="../../franchisee/kit/uploads/<?php echo htmlspecialchars($image['file_path']); ?>" 
                                             class="card-img-top" style="height: 200px; object-fit: cover;" 
                                             alt="<?php echo htmlspecialchars($image['title'] ?: 'Promotional Image'); ?>">
                                        
                                    </div>
                                    
                                            <div class="mt-auto d-flex" style="padding: 10px;    justify-content: space-between;">
                                                <h6 class="card-title mb-0 bottom_title"><?php echo htmlspecialchars($image['title'] ?: 'Promotional Image'); ?></h6>
                                                <a href="../../franchisee/kit/uploads/<?php echo htmlspecialchars($image['file_path']); ?>" 
                                                   download="<?php echo htmlspecialchars($image['title'] ?: 'promotional_image'); ?>" 
                                                   title="Download">
                                                    <i class="fa fa-download"></i>
                                                </a>
                                            </div>
                                         
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Videos Section -->
                    <?php if (!empty($videos)): ?>
<div class="row">
    <div class="col-12">
        <h4 class="heading">Videos</h4>
        <div class="row">
            <?php foreach ($videos as $index => $video): ?>

            <?php
                $url = $video['video_url'];

                // YouTube ID extract
                preg_match('/(youtu\.be\/|v=)([^&]+)/', $url, $ytMatch);
                $youtubeId = $ytMatch[2] ?? '';
            ?>

            <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                <div class="card h-100">
                    <div class="card-body p-2 video_section">

                        

                        <?php if ($youtubeId): ?>
                            <!-- YouTube Video -->
                            <div class="ratio ratio-16x9">
                                <iframe 
                                    src="https://www.youtube.com/embed/<?= $youtubeId ?>" 
                                    allowfullscreen>
                                </iframe>
                            </div>

                        <?php elseif (strpos($url, 'instagram.com') !== false): ?>
                            <!-- Instagram Video -->
                            <blockquote class="instagram-media"
                                data-instgrm-permalink="<?= htmlspecialchars($url); ?>"
                                data-instgrm-version="14"
                                style="margin:0 auto; min-width:100% !important">
                            </blockquote>

                        <?php else: ?>
                            <p class="text-danger text-center">Unsupported video link</p>
                        <?php endif; ?>

                    </div>
                </div>
            </div>

            <?php endforeach; ?>
        </div>

    </div>
</div>
<?php endif; ?>


                    
                    <!-- Files Section -->
                    <?php if (!empty($files)): ?>
                    <div class="row mb-5" style="margin-top:30px;">
                        <div class="col-12">
                            <h4 class=" heading">Downloadable Files</h4>
                            <div class="row">
                                <?php foreach ($files as $file): ?>
                                <div class="col-md-3 col-sm-6 mb-4 small_device_center downloadfileSection">
                                    <div class="card height-100">
                                        <div class="card-body text-center downloded_files">
                                            <div class="mb-3">
                                                <?php
                                                $file_extension = strtolower(pathinfo($file['file_path'], PATHINFO_EXTENSION));
                                                $icon_class = 'fa-file';
                                                
                                                // Set appropriate icon based on file type
                                                switch($file_extension) {
                                                    case 'pdf':
                                                        $icon_class = 'fa-file-pdf';
                                                        break;
                                                    case 'doc':
                                                    case 'docx':
                                                        $icon_class = 'fa-file-word';
                                                        break;
                                                    case 'xls':
                                                    case 'xlsx':
                                                        $icon_class = 'fa-file-excel';
                                                        break;
                                                    case 'ppt':
                                                    case 'pptx':
                                                        $icon_class = 'fa-file-powerpoint';
                                                        break;
                                                    case 'zip':
                                                    case 'rar':
                                                        $icon_class = 'fa-file-archive';
                                                        break;
                                                    case 'txt':
                                                        $icon_class = 'fa-file-alt';
                                                        break;
                                                    case 'mp4':
                                                    case 'avi':
                                                    case 'mov':
                                                        $icon_class = 'fa-file-video';
                                                        break;
                                                    case 'mp3':
                                                    case 'wav':
                                                        $icon_class = 'fa-file-audio';
                                                        break;
                                                }
                                                ?>
                                                <i class="fa <?php echo $icon_class; ?> fa-3x text-primary mb-2"></i>
                                            </div>
                                            <h6 class="card-title"><?php echo htmlspecialchars($file['title'] ?: 'Downloadable File'); ?></h6>
                                            <p class="card-text text-muted small">
                                                <?php echo strtoupper($file_extension); ?> File
                                            </p>
                                            <a href="../../franchisee/kit/uploads/<?php echo htmlspecialchars($file['file_path']); ?>" 
                                               download="<?php echo htmlspecialchars($file['title'] ?: 'file'); ?>" 
                                               class="btn last_download btn-sm file-download-btn">
                                                <i class="fa fa-download"></i> <span>Download</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Empty State -->
                    <?php if (empty($images) && empty($videos) && empty($files)): ?>
                    <div class="text-center py-5">
                        <i class="fa fa-toolbox fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">No Kit Items Available</h4>
                        <p class="text-muted">The admin hasn't added any promotional materials yet. Please check back later.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
function copyToClipboard(elementId) {
    const element = document.getElementById(elementId);
    element.select();
    element.setSelectionRange(0, 99999); // For mobile devices
    
    try {
        document.execCommand('copy');
        
        // Show success feedback
        const button = element.nextElementSibling;
        const originalHTML = button.innerHTML;
        button.innerHTML = '<i class="fa fa-check"></i> Copied!';
        button.classList.remove('btn-outline-secondary');
        button.classList.add('btn-success');
        
        setTimeout(function() {
            button.innerHTML = originalHTML;
            button.classList.remove('btn-success');
            button.classList.add('btn-outline-secondary');
        }, 2000);
        
    } catch (err) {
        console.error('Failed to copy: ', err);
        alert('Failed to copy to clipboard');
    }
}

// Add click-to-copy functionality for video URLs
document.addEventListener('DOMContentLoaded', function() {
    const videoInputs = document.querySelectorAll('input[readonly]');
    videoInputs.forEach(function(input) {
        input.addEventListener('click', function() {
            this.select();
        });
    });
});
</script>

<style>
/* Enhanced styling for the new layout */
.card {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: box-shadow 0.3s ease;
}

.card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}
.customer_content_area{
    padding: 0px 40px;
    margin-top: 33px;
    
}
.card-img-top {
    border-bottom: 1px solid #f8f9fa;
}
.last_download{
    display:flex;
    justify-content:center;
}
.downloded_files{
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
/* Card body styling */
.card-body {
    padding: 0.75rem;
    background: #f8f9fa;
    border-top: none;
}



.heading {
    font-size: 24px !important;
}
.heading2{
    font-size:24px !important;
}
/* Title styling - bottom left */
.card-title {
    font-size: 0.85rem;
    font-weight: 600;
    color: #495057;
    margin: 0;
    line-height: 1.2;
    max-width: 70%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.sub_title{
    font-size:22px;
}

/* Download button styling - bottom right */
.btn-primary.btn-sm {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    
    border: none;
    
    transition: all 0.2s ease;
}

.btn-primary.btn-sm:hover {
    background: #0056b3;
    transform: scale(1.1);
    box-shadow: 0 3px 6px rgba(0,123,255,0.4);
}

.btn-primary.btn-sm:active {
    transform: scale(0.95);
}

/* Layout container - clean base for manual positioning */
.d-flex.justify-content-between {
    width: 100%;
    min-height: 32px;
    align-items: center;
    padding: 0.5rem 0;
}
.bottom_title{
    font-size:16px !important;
}

/* Title styling - ready for manual positioning */
.card-title {
    font-size: 0.85rem;
    font-weight: 600;
    color: #495057;
    margin: 0;
    line-height: 1.2;
    max-width: 70%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Download button styling - ready for manual positioning */
.btn-primary.btn-sm {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
   
    border: none;
    
    transition: all 0.2s ease;
}

/* External link icon styling */
.btn-primary.btn-sm i {
    font-size: 14px;
    line-height: 1;
}
.Dashboard .card-body .btn {
    display: block;
    margin: 0 auto;
    color: #002169;
    font-size: 32px;
    line-height: 31px;
    font-weight: 600;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .card-title {
        font-size: 0.8rem;
        max-width: 65%;
    }
    
    
    .Copyright-left,
.Copyright-right{
    padding:0px;
}
    
    .btn-primary.btn-sm {
        width: 26px;
        height: 26px;
        font-size: 0.7rem;
    }
    .small_device_center{
        display:flex;
        justify-content:center;
    }
    .ReferralDetails-head .card, .FranchiseeDashboard-head .card {
        width: 24rem !important;
        margin: 20px auto !important;
    }
    .sub_title{
    font-size:16px;
}
.ReferralDetails-head .card, .FranchiseeDashboard-head .card{
    width: 29rem !important;
}
.instagram-media{
    height: 480px !important;
    width: 100% !important;
}
}

/* Ensure proper spacing and alignment */
.mt-auto {
    margin-top: auto !important;
}

/* Remove any default margins */
.card-body * {
    margin-bottom: 0;
}
.font22{
    font-size:22px;
}
.height-100{
    height:100px !important;
}

.downloadfileSection .card, 
.downloadfileSection  .card{
    width: 100% !important;
}

.ReferralDetails-head .card, .FranchiseeDashboard-head .card{
    width: 100%;
}
.card-body{
    width: 100%;
    margin-bottom:0px;
}
.video_section{
    padding: 0 !important;
}

.card-body .ratio.ratio-16x9{
height:100%;
}

.h-100 {
    height:92% !important;
}

.Embed,iframe{
    width: 100%;
    min-width:100%;
}
</style>

<?php include '../footer.php'; ?>
