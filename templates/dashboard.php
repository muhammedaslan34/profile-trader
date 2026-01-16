<?php
/**
 * Dashboard Template
 * 
 * @package Profile_Trader
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
$plugin = Profile_Trader::get_instance();
$listings = $plugin->get_user_listings();
$jobs = $plugin->get_user_jobs();
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
$listing_id = isset($_GET['listing_id']) ? intval($_GET['listing_id']) : 0;

// Prime meta cache to avoid N+1 query problem
if (!empty($listings)) {
    $listing_ids = wp_list_pluck($listings, 'ID');
    update_meta_cache('post', $listing_ids);
}
if (!empty($jobs)) {
    $job_ids = wp_list_pluck($jobs, 'ID');
    update_meta_cache('post', $job_ids);
}
?>

<div class="pt-dashboard" dir="rtl">
    <!-- Mobile Hamburger Button -->
    <button class="pt-hamburger-btn" aria-label="فتح القائمة" aria-expanded="false">
        <span class="pt-hamburger-line"></span>
        <span class="pt-hamburger-line"></span>
        <span class="pt-hamburger-line"></span>
    </button>

    <!-- Drawer Overlay -->
    <div class="pt-drawer-overlay"></div>

    <!-- Sidebar / Mobile Drawer -->
    <aside class="pt-sidebar">
        <!-- Mobile Close Button -->
        <button class="pt-drawer-close" aria-label="إغلاق القائمة">
            <svg class="pt-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>

        <!-- Desktop/Laptop Toggle Button -->
        <button class="pt-sidebar-toggle" aria-label="تبديل الشريط الجانبي" title="تصغير/تكبير القائمة">
            <svg class="pt-icon pt-icon-collapse" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
            <svg class="pt-icon pt-icon-expand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </button>

        <div class="pt-user-profile">
            <div class="pt-avatar">
                <?php 
                $avatar_id = get_user_meta($current_user->ID, 'pt_user_avatar', true);
                if ($avatar_id) {
                    $avatar_url = wp_get_attachment_image_url($avatar_id, 'thumbnail');
                    echo '<img src="' . esc_url($avatar_url) . '" alt="' . esc_attr($current_user->display_name) . '" class="pt-sidebar-avatar">';
                } else {
                    echo get_avatar($current_user->ID, 80);
                }
                ?>
            </div>
            <div class="pt-user-info">
                <h3 class="pt-user-name"><?php echo esc_html($current_user->display_name); ?></h3>
                <span class="pt-user-email"><?php echo esc_html($current_user->user_email); ?></span>
            </div>
        </div>
        
        <nav class="pt-nav">
            <a href="<?php echo esc_url(add_query_arg('tab', 'overview', get_permalink())); ?>" 
               class="pt-nav-item <?php echo $active_tab === 'overview' ? 'active' : ''; ?>">
                <svg class="pt-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                <span>نظرة عامة</span>
            </a>
            
            <a href="<?php echo esc_url(add_query_arg('tab', 'listings', get_permalink())); ?>" 
               class="pt-nav-item <?php echo $active_tab === 'listings' ? 'active' : ''; ?>">
                <svg class="pt-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                </svg>
                <span>جميع الدلائل</span>
                <span class="pt-badge"><?php echo count($listings); ?></span>
            </a>

            <a href="<?php echo esc_url(add_query_arg('tab', 'jobs', get_permalink())); ?>"
               class="pt-nav-item <?php echo in_array($active_tab, ['jobs', 'add-job', 'edit-job']) ? 'active' : ''; ?>">
                <svg class="pt-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                </svg>
                <span>الوظائف</span>
                <span class="pt-badge"><?php echo count($jobs); ?></span>
            </a>

            <a href="<?php echo esc_url(add_query_arg('tab', 'profile', get_permalink())); ?>"
               class="pt-nav-item <?php echo $active_tab === 'profile' ? 'active' : ''; ?>">
                <svg class="pt-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <span>الملف الشخصي</span>
            </a>
        </nav>
        
        <div class="pt-sidebar-footer">
            <a href="<?php echo esc_url(home_url()); ?>" class="pt-home-btn">
                <svg class="pt-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                <span>العودة للموقع الرئيسي</span>
            </a>
            <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="pt-logout-btn">
                <svg class="pt-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                <span>تسجيل الخروج</span>
            </a>
        </div>
    </aside>
    
    <!-- Main Content -->
    <main class="pt-main">
        <header class="pt-header">
            <div class="pt-header-content">
                <h1 class="pt-page-title">
                    <?php
                    switch ($active_tab) {
                        case 'overview':
                            echo 'نظرة عامة';
                            break;
                        case 'listings':
                            echo 'جميع الدلائل';
                            break;
                        case 'add-new':
                            echo 'إضافة دليل جديد';
                            break;
                        case 'edit':
                            echo 'تعديل الإعلان';
                            break;
                        case 'jobs':
                            echo 'الوظائف';
                            break;
                        case 'add-job':
                            echo 'إضافة وظيفة جديدة';
                            break;
                        case 'edit-job':
                            echo 'تعديل الوظيفة';
                            break;
                        case 'profile':
                            echo 'الملف الشخصي';
                            break;
                        default:
                            echo 'لوحة التحكم';
                    }
                    ?>
                </h1>
                <p class="pt-page-subtitle">
                    <?php echo sprintf(__('مرحباً %s، إدارة إعلاناتك من هنا', 'profile-trader'), $current_user->display_name); ?>
                </p>
            </div>
            
            <?php if ($active_tab === 'jobs'): ?>
            <a href="<?php echo esc_url(add_query_arg('tab', 'add-job', get_permalink())); ?>" class="pt-btn pt-btn-primary">
                <svg class="pt-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                إضافة وظيفة جديدة
            </a>
            <?php endif; ?>
        </header>
        
        <div class="pt-content">
            <?php
            switch ($active_tab) {
                case 'overview':
                    include pt_get_template_path('templates/partials/overview.php');
                    break;
                case 'listings':
                    include pt_get_template_path('templates/partials/listings-list.php');
                    break;
                case 'add-new':
                    include pt_get_template_path('templates/partials/edit-form.php');
                    break;
                case 'edit':
                    include pt_get_template_path('templates/partials/edit-form.php');
                    break;
                case 'jobs':
                    include pt_get_template_path('templates/partials/job-listings.php');
                    break;
                case 'add-job':
                    include pt_get_template_path('templates/partials/job-create-form.php');
                    break;
                case 'edit-job':
                    include pt_get_template_path('templates/partials/job-create-form.php');
                    break;
                case 'profile':
                    include pt_get_template_path('templates/partials/profile.php');
                    break;
                default:
                    include pt_get_template_path('templates/partials/overview.php');
            }
            ?>
        </div>
    </main>
</div>

