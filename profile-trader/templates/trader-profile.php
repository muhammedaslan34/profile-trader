<?php
/**
 * Trader Profile Template
 * Shortcode: [trader_profile id="123"] or [trader_profile] (uses URL parameter)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get trader ID from shortcode attribute or URL parameter
$trader_id = isset($trader_id) ? intval($trader_id) : 0;
if (!$trader_id && isset($_GET['trader_id'])) {
    $trader_id = intval($_GET['trader_id']);
}
if (!$trader_id) {
    $trader_id = get_the_ID();
}

// Get the trader post
$trader = get_post($trader_id);
if (!$trader || $trader->post_type !== 'trader') {
    echo '<div class="pt-error pt-trader-error">المعلن غير موجود</div>';
    return;
}

// Get trader meta fields
$trader_logo_id = get_post_meta($trader_id, 'logo', true);
$trader_logo_url = $trader_logo_id ? wp_get_attachment_image_url($trader_logo_id, 'medium') : '';
$trader_email = get_post_meta($trader_id, 'email', true);
$trader_phone = get_post_meta($trader_id, 'phone', true);
$trader_website = get_post_meta($trader_id, 'website', true);
$trader_address = get_post_meta($trader_id, 'address', true);
$trader_description = get_post_meta($trader_id, 'description', true);

// Query all ads for this trader
// Use custom query to handle all possible formats (ID, URL, slug, serialized)
global $wpdb;
$trader_url = get_permalink($trader_id);
$trader_slug = $trader->post_name;
$trader_id_str = strval($trader_id);

// Get all ad IDs that match this trader
$ad_ids = $wpdb->get_col($wpdb->prepare(
    "SELECT DISTINCT p.ID
     FROM {$wpdb->posts} p
     INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key IN ('trader_link', '_trader_link')
     WHERE p.post_type = 'ads'
     AND p.post_status = 'publish'
     AND (
         pm1.meta_value = %s
         OR pm1.meta_value = %s
         OR pm1.meta_value LIKE %s
         OR pm1.meta_value LIKE %s
         OR pm1.meta_value LIKE %s
         OR pm1.meta_value LIKE %s
     )
     ORDER BY p.post_date DESC",
    $trader_id_str,
    $trader_id,
    '%' . $wpdb->esc_like($trader_url) . '%',
    '%' . $wpdb->esc_like($trader_slug) . '%',
    '%' . $wpdb->esc_like(serialize($trader_id_str)) . '%',
    '%' . $wpdb->esc_like(serialize($trader_id)) . '%'
));

// Create WP_Query with the found IDs
if (!empty($ad_ids)) {
    $trader_ads = new WP_Query([
        'post_type' => 'ads',
        'post__in' => $ad_ids,
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC'
    ]);
} else {
    // Create empty query if no ads found
    $trader_ads = new WP_Query([
        'post_type' => 'ads',
        'post__in' => [0], // No posts
        'posts_per_page' => -1
    ]);
}
?>

<div class="pt-trader-profile" dir="rtl">
    <!-- Breadcrumbs -->
    <nav class="pt-ad-breadcrumbs">
        <a href="<?php echo home_url(); ?>">الرئيسية</a>
        <span class="pt-breadcrumb-sep">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </span>
        <a href="<?php echo get_post_type_archive_link('ads'); ?>">الإعلانات</a>
        <span class="pt-breadcrumb-sep">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </span>
        <span class="pt-breadcrumb-current"><?php echo esc_html($trader->post_title); ?></span>
    </nav>

    <div class="pt-trader-header">
        <div class="pt-trader-avatar">
            <?php if ($trader_logo_url): ?>
                <img src="<?php echo esc_url($trader_logo_url); ?>" alt="<?php echo esc_attr($trader->post_title); ?>">
            <?php else: ?>
                <div class="pt-avatar-placeholder-large">
                    <?php echo mb_substr($trader->post_title, 0, 1); ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="pt-trader-info">
            <h1 class="pt-trader-name"><?php echo esc_html($trader->post_title); ?></h1>
            <div class="pt-trader-badge">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    <polyline points="9 12 12 15 16 10"></polyline>
                </svg>
                <span>معلن موثق</span>
            </div>
            <?php if ($trader_description): ?>
                <p class="pt-trader-desc"><?php echo esc_html($trader_description); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Trader Contact Info -->
    <?php if ($trader_phone || $trader_email || $trader_website || $trader_address): ?>
    <section class="pt-trader-section pt-trader-contact">
        <h3 class="pt-section-title">معلومات الاتصال</h3>
        <div class="pt-contact-grid">
            <?php if ($trader_phone): ?>
            <div class="pt-contact-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                </svg>
                <a href="tel:<?php echo esc_attr($trader_phone); ?>"><?php echo esc_html($trader_phone); ?></a>
            </div>
            <?php endif; ?>

            <?php if ($trader_email): ?>
            <div class="pt-contact-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                    <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
                <a href="mailto:<?php echo esc_attr($trader_email); ?>"><?php echo esc_html($trader_email); ?></a>
            </div>
            <?php endif; ?>

            <?php if ($trader_website): ?>
            <div class="pt-contact-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="2" y1="12" x2="22" y2="12"></line>
                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                </svg>
                <a href="<?php echo esc_url($trader_website); ?>" target="_blank" rel="noopener"><?php echo esc_html($trader_website); ?></a>
            </div>
            <?php endif; ?>

            <?php if ($trader_address): ?>
            <div class="pt-contact-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                </svg>
                <span><?php echo esc_html($trader_address); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Trader Ads Section -->
    <section class="pt-trader-section pt-trader-ads">
        <div class="pt-section-header">
            <h3 class="pt-section-title">إعلانات المعلن</h3>
            <span class="pt-ads-count"><?php echo $trader_ads->found_posts; ?> إعلان</span>
        </div>

        <?php if ($trader_ads->have_posts()): ?>
        <div class="pt-ads-grid">
            <?php while ($trader_ads->have_posts()): $trader_ads->the_post();
                $ad_id = get_the_ID();
                $ad_price = get_post_meta($ad_id, 'price_ads', true);
                $ad_location = get_post_meta($ad_id, 'ad_location', true);
                $ad_thumb = get_the_post_thumbnail_url($ad_id, 'medium');
                
                // Calculate days since posted
                $post_date = strtotime(get_the_date('c'));
                $days_ago = floor((time() - $post_date) / 86400);
                if ($days_ago == 0) {
                    $date_label = 'اليوم';
                } elseif ($days_ago == 1) {
                    $date_label = 'أمس';
                } else {
                    $date_label = sprintf('منذ %d يوم', $days_ago);
                }
            ?>
            <a href="<?php the_permalink(); ?>" class="pt-ad-card">
                <div class="pt-ad-card-image-wrapper">
                    <div class="pt-ad-card-image" style="background-image: url('<?php echo esc_url($ad_thumb ?: ''); ?>');">
                        <?php if (!$ad_thumb): ?>
                        <div class="pt-no-img-placeholder">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                <polyline points="21 15 16 10 5 21"></polyline>
                            </svg>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="pt-ad-card-overlay"></div>
                    <?php if ($ad_price): ?>
                    <div class="pt-ad-card-price-badge">
                        <?php echo esc_html($ad_price); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="pt-ad-card-content">
                    <h4 class="pt-ad-card-title"><?php the_title(); ?></h4>
                    <div class="pt-ad-card-meta">
                        <?php if ($ad_location): ?>
                        <span class="pt-ad-card-location">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            <span><?php echo esc_html($ad_location); ?></span>
                        </span>
                        <?php endif; ?>
                        <span class="pt-ad-card-date">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                            <span><?php echo esc_html($date_label); ?></span>
                        </span>
                    </div>
                </div>
            </a>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>
        <?php else: ?>
        <div class="pt-empty-state">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="9" y1="3" x2="9" y2="21"></line>
            </svg>
            <p>لا توجد إعلانات منشورة</p>
        </div>
        <?php endif; ?>
    </section>
</div>

<style>
.pt-trader-profile {
    /* Theme Colors */
    --pt-primary: #0A4E45;
    --pt-primary-dark: #073832;
    --pt-primary-light: #0d6358;
    --pt-secondary: #EDEAE0;
    --pt-secondary-dark: #ddd8c8;
    --pt-accent: #B9A779;
    --pt-accent-dark: #a08f5f;
    --pt-accent-light: #c9ba93;
    --pt-bg: #f5f3ed;
    --pt-bg-alt: var(--pt-secondary);
    --pt-surface: #ffffff;
    --pt-surface-alt: #fafaf8;
    --pt-border: #ddd8c8;
    --pt-border-light: #e8e5db;
    --pt-text: #1a1a1a;
    --pt-text-secondary: #5a5a5a;
    --pt-text-muted: #8a8a8a;
    --pt-text-inverse: #ffffff;
    --pt-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.08), 0 1px 2px -1px rgb(0 0 0 / 0.08);
    --pt-shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.08), 0 2px 4px -2px rgb(0 0 0 / 0.08);
    --pt-shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.08), 0 4px 6px -4px rgb(0 0 0 / 0.08);
    --pt-radius: 10px;
    --pt-radius-lg: 16px;
    --pt-transition: 0.2s ease;
    --pt-font: 'Cairo', sans-serif;

    font-family: var(--pt-font);
    max-width: 1200px;
    margin: 0 auto;
    padding: 24px 16px;
    color: var(--pt-text);
}

.pt-trader-header {
    display: flex;
    align-items: center;
    gap: 30px;
    padding: 30px;
    border-radius: var(--pt-radius-lg);
    margin-bottom: 30px;
    box-shadow: var(--pt-shadow-md);
    border: 1px solid var(--pt-border-light);
}

.pt-trader-avatar {
    flex-shrink: 0;
}

.pt-trader-avatar img {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--pt-border-light);
}

.pt-avatar-placeholder-large {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--pt-text-inverse);
    font-size: 48px;
    font-weight: 700;
    font-family: var(--pt-font);
    border: 3px solid var(--pt-border-light);
}

.pt-trader-info {
    flex: 1;
}

.pt-trader-name {
    font-family: var(--pt-font);
    font-size: 32px;
    font-weight: 700;
    margin: 0 0 15px 0;
    color: var(--pt-text);
}

.pt-trader-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    color: var(--pt-text-inverse);
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    font-family: var(--pt-font);
    margin-bottom: 15px;
    box-shadow: 0 2px 8px rgba(10, 78, 69, 0.2);
}

.pt-trader-badge svg {
    width: 18px;
    height: 18px;
}

.pt-trader-desc {
    font-family: var(--pt-font);
    color: var(--pt-text-secondary);
    font-size: 16px;
    line-height: 1.6;
    margin: 0;
}

.pt-trader-section {
    border-radius: var(--pt-radius-lg);
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: var(--pt-shadow-md);
    border: 1px solid var(--pt-border-light);
}

.pt-section-title {
    font-family: var(--pt-font);
    font-size: 24px;
    font-weight: 700;
    margin: 0 0 20px 0;
    color: var(--pt-text);
}

.pt-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.pt-ads-count {
    font-family: var(--pt-font);
    color: var(--pt-text-secondary);
    font-size: 16px;
    font-weight: 600;
}

.pt-contact-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.pt-contact-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px;
    border-radius: var(--pt-radius);
    border: 1px solid var(--pt-border-light);
    transition: var(--pt-transition);
}

.pt-contact-item:hover {
    transform: translateY(-2px);
    box-shadow: var(--pt-shadow);
}

.pt-contact-item svg {
    color: var(--pt-primary);
    flex-shrink: 0;
}

.pt-contact-item a {
    font-family: var(--pt-font);
    color: var(--pt-text);
    text-decoration: none;
    transition: var(--pt-transition);
    font-weight: 500;
}

.pt-contact-item a:hover {
    color: var(--pt-primary);
}

.pt-contact-item span {
    font-family: var(--pt-font);
    color: var(--pt-text);
    font-weight: 500;
}

.pt-ads-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 25px;
}

.pt-ad-card {
    display: flex;
    flex-direction: column;
    border-radius: var(--pt-radius-lg);
    overflow: hidden;
    box-shadow: var(--pt-shadow);
    border: 1px solid var(--pt-border-light);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    text-decoration: none;
    color: inherit;
    background: var(--pt-surface);
    height: 100%;
}

.pt-ad-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12);
    border-color: var(--pt-primary);
}

.pt-ad-card:hover .pt-ad-card-image {
    transform: scale(1.05);
}

.pt-ad-card:hover .pt-ad-card-overlay {
    opacity: 0.3;
}

.pt-ad-card-image-wrapper {
    position: relative;
    width: 100%;
    height: 240px;
    overflow: hidden;
    background: var(--pt-secondary);
}

.pt-ad-card-image {
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}

.pt-ad-card-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(to bottom, transparent 0%, rgba(0, 0, 0, 0.4) 100%);
    opacity: 0;
    transition: opacity 0.3s ease;
    pointer-events: none;
}

.pt-ad-card-price-badge {
    position: absolute;
    bottom: 16px;
    right: 16px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    padding: 8px 16px;
    border-radius: 20px;
    font-family: var(--pt-font);
    font-size: 18px;
    font-weight: 700;
    color: var(--pt-primary);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 2;
    border: 1px solid rgba(255, 255, 255, 0.8);
}

.pt-no-img-placeholder {
    color: var(--pt-text-muted);
    opacity: 0.5;
}

.pt-ad-card-content {
    padding: 20px;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.pt-ad-card-title {
    font-family: var(--pt-font);
    font-size: 18px;
    font-weight: 600;
    margin: 0 0 12px 0;
    color: var(--pt-text);
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 54px;
    transition: color 0.2s ease;
}

.pt-ad-card:hover .pt-ad-card-title {
    color: var(--pt-primary);
}

.pt-ad-card-meta {
    display: flex;
    align-items: center;
    gap: 16px;
    font-family: var(--pt-font);
    font-size: 13px;
    color: var(--pt-text-secondary);
    font-weight: 500;
    margin-top: auto;
    padding-top: 12px;
    border-top: 1px solid var(--pt-border-light);
    flex-wrap: wrap;
}

.pt-ad-card-location,
.pt-ad-card-date {
    display: flex;
    align-items: center;
    gap: 6px;
    line-height: 1.4;
}

.pt-ad-card-location svg,
.pt-ad-card-date svg {
    color: var(--pt-accent);
    flex-shrink: 0;
}

.pt-ad-card-location span,
.pt-ad-card-date span {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 150px;
}

.pt-empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--pt-text-muted);
}

.pt-empty-state svg {
    margin-bottom: 20px;
    color: var(--pt-text-muted);
}

.pt-empty-state p {
    font-family: var(--pt-font);
    font-size: 18px;
    font-weight: 500;
    margin: 0;
    color: var(--pt-text-secondary);
}

/* Breadcrumbs */
.pt-ad-breadcrumbs {
    font-family: var(--pt-font);
    margin-bottom: 30px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.pt-ad-breadcrumbs a {
    color: var(--pt-primary);
    text-decoration: none;
    font-weight: 500;
    transition: var(--pt-transition);
}

.pt-ad-breadcrumbs a:hover {
    color: var(--pt-primary-light);
    text-decoration: underline;
}

.pt-breadcrumb-sep {
    color: var(--pt-text-muted);
}

.pt-breadcrumb-current {
    color: var(--pt-text-secondary);
    font-weight: 600;
}

@media (max-width: 1024px) {
    .pt-ads-grid {
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
    }
}

@media (max-width: 768px) {
    .pt-trader-profile {
        padding: 16px;
    }
    
    .pt-trader-header {
        flex-direction: column;
        text-align: center;
        padding: 20px;
    }
    
    .pt-trader-name {
        font-size: 24px;
    }
    
    .pt-ads-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .pt-ad-card-image-wrapper {
        height: 220px;
    }
    
    .pt-ad-card-price-badge {
        font-size: 16px;
        padding: 6px 14px;
        bottom: 12px;
        right: 12px;
    }
    
    .pt-ad-card-content {
        padding: 16px;
    }
    
    .pt-ad-card-title {
        font-size: 16px;
        min-height: 48px;
    }
    
    .pt-ad-card-meta {
        font-size: 12px;
        gap: 12px;
    }
    
    .pt-contact-grid {
        grid-template-columns: 1fr;
    }
    
    .pt-trader-section {
        padding: 20px;
    }
}
</style>
