<?php
/**
 * Overview Partial Template
 * 
 * @package Profile_Trader
 */

if (!defined('ABSPATH')) {
    exit;
}

$plugin = Profile_Trader::get_instance();
$listings = $plugin->get_user_listings();
$jobs = $plugin->get_user_jobs();

// Prime meta cache to avoid N+1 query problem
if (!empty($listings)) {
    $listing_ids = wp_list_pluck($listings, 'ID');
    update_meta_cache('post', $listing_ids);
}

// Calculate stats
$total_listings = count($listings);
$published_count = 0;
$pending_count = 0;
$draft_count = 0;
$featured_count = 0;

foreach ($listings as $listing) {
    switch ($listing->post_status) {
        case 'publish':
            $published_count++;
            break;
        case 'pending':
            $pending_count++;
            break;
        case 'draft':
            $draft_count++;
            break;
    }
    
    if (get_post_meta($listing->ID, 'is_featured', true)) {
        $featured_count++;
    }
}

// Calculate jobs count
$total_jobs = count($jobs);
?>

<!-- Stats Cards -->
<div class="pt-stats-grid">
    <div class="pt-stat-card pt-stat-total">
        <div class="pt-stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
            </svg>
        </div>
        <div class="pt-stat-content">
            <span class="pt-stat-number"><?php echo $total_listings; ?></span>
            <span class="pt-stat-label">جميع الدلائل</span>
        </div>
    </div>
    
    <div class="pt-stat-card pt-stat-published">
        <div class="pt-stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
        </div>
        <div class="pt-stat-content">
            <span class="pt-stat-number"><?php echo $published_count; ?></span>
            <span class="pt-stat-label">منشور</span>
        </div>
    </div>
    
    <div class="pt-stat-card pt-stat-pending">
        <div class="pt-stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
        </div>
        <div class="pt-stat-content">
            <span class="pt-stat-number"><?php echo $pending_count; ?></span>
            <span class="pt-stat-label">قيد المراجعة</span>
        </div>
    </div>
    
    <div class="pt-stat-card pt-stat-jobs">
        <div class="pt-stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
            </svg>
        </div>
        <div class="pt-stat-content">
            <span class="pt-stat-number"><?php echo $total_jobs; ?></span>
            <span class="pt-stat-label">الوظائف المنشورة</span>
        </div>
    </div>
</div>

<!-- Recent Listings -->
<div class="pt-section">
    <div class="pt-section-header">
        <h2 class="pt-section-title">آخر الإعلانات</h2>
        <a href="<?php echo esc_url(add_query_arg('tab', 'listings', get_permalink())); ?>" class="pt-link">
            عرض الكل
            <svg class="pt-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
        </a>
    </div>
    
    <?php if (empty($listings)): ?>
    <div class="pt-empty-state">
        <div class="pt-empty-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
            </svg>
        </div>
        <h3>لا توجد إعلانات بعد</h3>
        <p>ابدأ بإضافة إعلانك الأول لعرضه في دليل التجار</p>
        <a href="<?php echo esc_url(add_query_arg('tab', 'add-new', get_permalink())); ?>" class="pt-btn pt-btn-primary">
            <svg class="pt-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            إضافة إعلان جديد
        </a>
    </div>
    <?php else: ?>
    <div class="pt-listings-table-wrapper">
        <table class="pt-listings-table">
            <thead>
                <tr>
                    <th>الإعلان</th>
                    <th>الحالة</th>
                    <th>التاريخ</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $recent = array_slice($listings, 0, 5);
                foreach ($recent as $listing): 
                    $logo_id = get_post_meta($listing->ID, 'logo', true);
                    $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'thumbnail') : '';
                ?>
                <tr>
                    <td data-label="الإعلان">
                        <div class="pt-listing-cell">
                            <div class="pt-listing-thumb">
                                <?php if ($logo_url): ?>
                                    <img src="<?php echo esc_url($logo_url); ?>" alt="">
                                <?php else: ?>
                                    <div class="pt-listing-placeholder">
                                        <?php echo mb_substr($listing->post_title, 0, 1); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="pt-listing-info">
                                <strong><?php echo esc_html($listing->post_title); ?></strong>
                                <span><?php echo esc_html(get_post_meta($listing->ID, 'short_desc', true)); ?></span>
                            </div>
                        </div>
                    </td>
                    <td data-label="الحالة">
                        <?php
                        $status_class = '';
                        $status_text = '';
                        switch ($listing->post_status) {
                            case 'publish':
                                $status_class = 'pt-status-published';
                                $status_text = 'منشور';
                                break;
                            case 'pending':
                                $status_class = 'pt-status-pending';
                                $status_text = 'قيد المراجعة';
                                break;
                            case 'draft':
                                $status_class = 'pt-status-draft';
                                $status_text = 'مسودة';
                                break;
                        }
                        ?>
                        <span class="pt-status <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                    </td>
                    <td data-label="التاريخ">
                        <span class="pt-date"><?php echo get_the_date('Y/m/d', $listing); ?></span>
                    </td>
                    <td data-label="إجراءات">
                        <div class="pt-actions">
                            <a href="<?php echo esc_url(add_query_arg(['tab' => 'edit', 'listing_id' => $listing->ID], get_permalink())); ?>"
                               class="pt-action-btn pt-action-edit" title="تعديل">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                            </a>
                            <?php if ($listing->post_status === 'publish'): ?>
                            <a href="<?php echo get_permalink($listing->ID); ?>"
                               class="pt-action-btn pt-action-view" title="عرض" target="_blank">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Quick Actions -->
<div class="pt-section">
    <h2 class="pt-section-title">إجراءات سريعة</h2>
    <div class="pt-quick-actions">
        <a href="<?php echo esc_url(add_query_arg('tab', 'add-job', get_permalink())); ?>" class="pt-quick-action">
            <div class="pt-quick-action-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                </svg>
            </div>
            <span>نشر وظيفة</span>
        </a>
        
        <a href="<?php echo esc_url(add_query_arg('tab', 'profile', get_permalink())); ?>" class="pt-quick-action">
            <div class="pt-quick-action-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
            </div>
            <span>تعديل الملف الشخصي</span>
        </a>
        
        <a href="<?php echo home_url('/trader/'); ?>" class="pt-quick-action" target="_blank">
            <div class="pt-quick-action-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                </svg>
            </div>
            <span>عرض دليل التجار</span>
        </a>
    </div>
</div>

