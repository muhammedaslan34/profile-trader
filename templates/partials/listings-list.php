<?php
/**
 * Listings List Partial Template
 * 
 * @package Profile_Trader
 */

if (!defined('ABSPATH')) {
    exit;
}

$plugin = Profile_Trader::get_instance();
$listings = $plugin->get_user_listings();
$filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';

// Filter listings
if ($filter_status !== 'all') {
    $listings = array_filter($listings, function($listing) use ($filter_status) {
        return $listing->post_status === $filter_status;
    });
}

// Prime meta cache to avoid N+1 query problem
if (!empty($listings)) {
    $listing_ids = wp_list_pluck($listings, 'ID');
    update_meta_cache('post', $listing_ids);
}
?>

<!-- Filters -->
<div class="pt-filters">
    <div class="pt-filter-tabs">
        <a href="<?php echo esc_url(add_query_arg(['tab' => 'listings', 'status' => 'all'], get_permalink())); ?>" 
           class="pt-filter-tab <?php echo $filter_status === 'all' ? 'active' : ''; ?>">
            الكل
        </a>
        <a href="<?php echo esc_url(add_query_arg(['tab' => 'listings', 'status' => 'publish'], get_permalink())); ?>" 
           class="pt-filter-tab <?php echo $filter_status === 'publish' ? 'active' : ''; ?>">
            منشور
        </a>
        <a href="<?php echo esc_url(add_query_arg(['tab' => 'listings', 'status' => 'pending'], get_permalink())); ?>" 
           class="pt-filter-tab <?php echo $filter_status === 'pending' ? 'active' : ''; ?>">
            قيد المراجعة
        </a>
        <a href="<?php echo esc_url(add_query_arg(['tab' => 'listings', 'status' => 'draft'], get_permalink())); ?>" 
           class="pt-filter-tab <?php echo $filter_status === 'draft' ? 'active' : ''; ?>">
            مسودة
        </a>
    </div>
</div>

<?php if (empty($listings)): ?>
<div class="pt-empty-state">
    <div class="pt-empty-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
        </svg>
    </div>
    <h3>لا توجد إعلانات</h3>
    <p>
        <?php if ($filter_status !== 'all'): ?>
            لا توجد إعلانات بهذه الحالة
        <?php else: ?>
            ابدأ بإضافة إعلانك الأول لعرضه في دليل التجار
        <?php endif; ?>
    </p>
</div>
<?php else: ?>

<!-- Listings Grid -->
<div class="pt-listings-grid">
    <?php foreach ($listings as $listing): 
        $logo_id = get_post_meta($listing->ID, 'logo', true);
        $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';
        $short_desc = get_post_meta($listing->ID, 'short_desc', true);
        $phone = get_post_meta($listing->ID, 'phone', true);
        $email = get_post_meta($listing->ID, 'email', true);
        $is_featured = get_post_meta($listing->ID, 'is_featured', true);
        $company_type = get_post_meta($listing->ID, 'company_type', true);
        $score = get_post_meta($listing->ID, 'score', true);
    ?>
    <div class="pt-listing-card <?php echo $is_featured ? 'pt-featured' : ''; ?>">
        
        <div class="pt-card-header">
            <div class="pt-card-logo">
                <?php if ($logo_url): ?>
                    <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($listing->post_title); ?>">
                <?php else: ?>
                    <div class="pt-logo-placeholder">
                        <?php echo mb_substr($listing->post_title, 0, 1); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="pt-card-status">
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
            </div>
        </div>
        
        <div class="pt-card-body">
            <h3 class="pt-card-title"><?php echo esc_html($listing->post_title); ?></h3>
            
            <?php if ($short_desc): ?>
            <p class="pt-card-desc"><?php echo esc_html($short_desc); ?></p>
            <?php endif; ?>
            
            <div class="pt-card-meta">
                <?php if ($company_type): ?>
                <span class="pt-meta-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    </svg>
                    <?php echo esc_html($company_type); ?>
                </span>
                <?php endif; ?>
                
                <?php if ($score): ?>
                <span class="pt-meta-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                    </svg>
                    <?php echo esc_html($score); ?>
                </span>
                <?php endif; ?>
                
                <?php if (class_exists('PT_Ad_Views')): 
                    $total_views = PT_Ad_Views::get_instance()->get_total_views($listing->ID);
                ?>
                <span class="pt-meta-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <?php echo number_format_i18n($total_views); ?> مشاهدة
                </span>
                <?php endif; ?>
            </div>
            
            <div class="pt-card-contact">
                <?php if ($phone): ?>
                <span class="pt-contact-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                    </svg>
                    <?php echo esc_html($phone); ?>
                </span>
                <?php endif; ?>
                
                <?php if ($email): ?>
                <span class="pt-contact-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    <?php echo esc_html($email); ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="pt-card-footer">
            <span class="pt-card-date">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <?php echo get_the_date('Y/m/d', $listing); ?>
            </span>
            
            <div class="pt-card-actions">
                <a href="<?php echo esc_url(add_query_arg(['tab' => 'edit', 'listing_id' => $listing->ID], get_permalink())); ?>" 
                   class="pt-btn pt-btn-sm pt-btn-outline">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    تعديل
                </a>
                
                <?php if ($listing->post_status === 'publish'): ?>
                <a href="<?php echo get_permalink($listing->ID); ?>" 
                   class="pt-btn pt-btn-sm pt-btn-primary" target="_blank">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    عرض
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>

