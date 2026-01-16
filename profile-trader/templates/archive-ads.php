<?php
/**
 * Archive Template for Ads Post Type
 * Displays all published ads in a grid with AJAX pagination
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current page
$paged = get_query_var('paged') ? get_query_var('paged') : 1;

// Get posts per page from URL parameter or default to 12
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 12;
// Validate per_page value (only allow 12, 24, or 32)
if (!in_array($per_page, [12, 24, 32])) {
    $per_page = 12;
}
$posts_per_page = $per_page;

// Get sort parameter from URL or default to date_desc
$sort = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'date_desc';
$valid_sorts = ['date_desc', 'date_asc', 'price_asc', 'price_desc', 'title_asc'];
if (!in_array($sort, $valid_sorts)) {
    $sort = 'date_desc';
}

// Parse sort parameter
$orderby = 'date';
$order = 'DESC';
$meta_key = '';

switch ($sort) {
    case 'date_asc':
        $orderby = 'date';
        $order = 'ASC';
        break;
    case 'price_asc':
        $orderby = 'meta_value_num';
        $order = 'ASC';
        $meta_key = 'price_ads';
        break;
    case 'price_desc':
        $orderby = 'meta_value_num';
        $order = 'DESC';
        $meta_key = 'price_ads';
        break;
    case 'title_asc':
        $orderby = 'title';
        $order = 'ASC';
        break;
    default: // date_desc
        $orderby = 'date';
        $order = 'DESC';
}

// Build query args
$query_args = [
    'post_type' => 'ads',
    'post_status' => 'publish',
    'posts_per_page' => $posts_per_page,
    'paged' => $paged,
    'orderby' => $orderby,
    'order' => $order
];

// Add meta_key for price sorting
if ($meta_key) {
    $query_args['meta_key'] = $meta_key;
    // Only include posts that have price_ads meta
    $query_args['meta_query'] = [
        [
            'key' => 'price_ads',
            'compare' => 'EXISTS'
        ]
    ];
}

// Query ads
$ads_query = new WP_Query($query_args);
?>

<div class="pt-ads-archive" dir="rtl">
    <!-- Page Header -->
    <div class="pt-archive-header">
        <div class="pt-archive-header-content">
            <?php if ($ads_query->found_posts > 0): ?>
            <p class="pt-archive-count"><?php echo sprintf('عرض %d من %d إعلان', $ads_query->post_count, $ads_query->found_posts); ?></p>
            <?php endif; ?>
            <div class="pt-archive-controls">
                <div class="pt-sort-selector">
                    <label for="pt-sort-select" class="pt-sort-label">ترتيب:</label>
                    <select id="pt-sort-select" class="pt-sort-select">
                        <option value="date_desc" <?php selected($sort, 'date_desc'); ?>>الأحدث أولاً</option>
                        <option value="date_asc" <?php selected($sort, 'date_asc'); ?>>الأقدم أولاً</option>
                        <option value="price_asc" <?php selected($sort, 'price_asc'); ?>>السعر: من الأقل للأعلى</option>
                        <option value="price_desc" <?php selected($sort, 'price_desc'); ?>>السعر: من الأعلى للأقل</option>
                        <option value="title_asc" <?php selected($sort, 'title_asc'); ?>>الاسم: أبجدي</option>
                    </select>
                </div>
                <div class="pt-per-page-selector">
                    <label for="pt-per-page-select" class="pt-per-page-label">عرض:</label>
                    <select id="pt-per-page-select" class="pt-per-page-select">
                        <option value="12" <?php selected($per_page, 12); ?>>12</option>
                        <option value="24" <?php selected($per_page, 24); ?>>24</option>
                        <option value="32" <?php selected($per_page, 32); ?>>32</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Ads Grid -->
    <div id="pt-ads-container" class="pt-ads-grid">
        <?php if ($ads_query->have_posts()): ?>
            <?php while ($ads_query->have_posts()): $ads_query->the_post();
                $ad_id = get_the_ID();
                $ad_price = get_post_meta($ad_id, 'price_ads', true);
                $ad_location = get_post_meta($ad_id, 'ad_location', true);
                $contact_number = get_post_meta($ad_id, 'contact_number', true);
                $whatsapp = get_post_meta($ad_id, 'whatsapp', true);
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
            <div class="pt-ad-card">
                <div class="pt-ad-card-image-wrapper">
                    <a href="<?php the_permalink(); ?>" class="pt-ad-card-image-link">
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
                    </a>
                </div>
                <div class="pt-ad-card-content">
                    <a href="<?php the_permalink(); ?>" class="pt-ad-card-link">
                        <h4 class="pt-ad-card-title"><?php the_title(); ?></h4>
                        <?php if ($ad_price): ?>
                        <div class="pt-ad-card-price"><?php echo esc_html($ad_price); ?></div>
                        <?php endif; ?>
                    </a>
                    
                    <?php
                    // Get short description for specs (if available)
                    $short_desc = get_post_meta($ad_id, 'short_desc', true);
                    // You can add more meta fields here for specifications
                    ?>
                    
                    <?php if ($short_desc): ?>
                    <div class="pt-ad-specs-grid">
                        <div class="pt-spec-item">
                            <svg class="pt-spec-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            </svg>
                            <span class="pt-spec-text"><?php echo esc_html(wp_trim_words($short_desc, 3)); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="pt-ad-card-footer">
                        <div class="pt-ad-card-meta">
                            <?php if ($ad_location): ?>
                            <span class="pt-ad-card-location">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                    <circle cx="12" cy="10" r="3"></circle>
                                </svg>
                                <span><?php echo esc_html($ad_location); ?></span>
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="pt-ad-card-actions">
                            <?php if ($contact_number): ?>
                            <a href="tel:<?php echo esc_attr($contact_number); ?>" class="pt-action-btn pt-action-btn-call" onclick="event.stopPropagation();">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                </svg>
                                <span>اتصال</span>
                            </a>
                            <?php endif; ?>
                            <?php if ($whatsapp): ?>
                            <a href="https://wa.me/<?php echo esc_attr(preg_replace('/[^0-9]/', '', $whatsapp)); ?>?text=<?php echo urlencode('مرحباً، أنا مهتم بالإعلان: ' . get_the_title()); ?>" target="_blank" class="pt-action-btn pt-action-btn-message" onclick="event.stopPropagation();">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                </svg>
                                <span>رسالة</span>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; wp_reset_postdata(); ?>
        <?php else: ?>
            <div class="pt-empty-state">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="9" y1="3" x2="9" y2="21"></line>
                </svg>
                <p>لا توجد إعلانات منشورة</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($ads_query->max_num_pages > 1): ?>
    <div id="pt-pagination-container" class="pt-pagination">
        <?php
        echo paginate_links([
            'total' => $ads_query->max_num_pages,
            'current' => $paged,
            'prev_text' => '&laquo; السابق',
            'next_text' => 'التالي &raquo;',
            'type' => 'list',
            'end_size' => 2,
            'mid_size' => 2,
        ]);
        ?>
    </div>
    <?php endif; ?>

    <!-- Loading Indicator -->
    <div id="pt-loading" class="pt-loading" style="display: none;">
        <div class="pt-spinner"></div>
        <p>جاري التحميل...</p>
    </div>
</div>

<style>
.pt-ads-archive {
    /* Theme Colors */
    --pt-primary: #0d7377;
    --pt-primary-dark: #0a5d61;
    --pt-primary-light: #0f8a8f;
    --pt-secondary: #EDEAE0;
    --pt-secondary-dark: #ddd8c8;
    --pt-accent: #d4a853;
    --pt-accent-dark: #c19a3f;
    --pt-accent-light: #e5b967;
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

.pt-archive-header {
    margin-bottom: 30px;
}

.pt-archive-header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}

.pt-archive-title {
    font-family: var(--pt-font);
    font-size: 36px;
    font-weight: 700;
    margin: 0 0 10px 0;
    color: var(--pt-text);
}

.pt-archive-count {
    font-family: var(--pt-font);
    font-size: 16px;
    color: var(--pt-text-secondary);
    margin: 0;
}

.pt-archive-controls {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.pt-sort-selector,
.pt-per-page-selector {
    display: flex;
    align-items: center;
    gap: 8px;
}

.pt-sort-label,
.pt-per-page-label {
    font-family: var(--pt-font);
    font-size: 14px;
    color: var(--pt-text-secondary);
    font-weight: 500;
    margin: 0;
}

.pt-sort-select,
.pt-per-page-select {
    font-family: var(--pt-font);
    font-size: 14px;
    padding: 6px 12px;
    border: 1px solid var(--pt-border-light);
    border-radius: 6px;
    background: var(--pt-surface);
    color: var(--pt-text);
    cursor: pointer;
    transition: all 0.2s ease;
    min-width: 160px;
}

.pt-sort-select:hover,
.pt-per-page-select:hover {
    border-color: var(--pt-primary);
}

.pt-sort-select:focus,
.pt-per-page-select:focus {
    outline: none;
    border-color: var(--pt-primary);
    box-shadow: 0 0 0 3px rgba(13, 115, 119, 0.1);
}

.pt-ads-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 24px;
    margin-bottom: 40px;
}

.pt-ad-card {
    display: flex;
    flex-direction: column;
    border-radius: var(--pt-radius-lg);
    overflow: hidden;
    box-shadow: var(--pt-shadow);
    border: 1px solid var(--pt-border-light);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background: var(--pt-surface);
    height: 100%;
    position: relative;
}

.pt-ad-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12);
    border-color: var(--pt-primary);
}

.pt-ad-card-link {
    text-decoration: none;
    color: inherit;
    flex: 1;
    display: flex;
    flex-direction: column;
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
    height: 200px;
    overflow: hidden;
    background: var(--pt-secondary);
    border-radius: var(--pt-radius-lg) var(--pt-radius-lg) 0 0;
}

.pt-ad-card-image-link {
    display: block;
    width: 100%;
    height: 100%;
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

.pt-ad-card:hover .pt-ad-card-image {
    transform: scale(1.05);
}

.pt-no-img-placeholder {
    color: var(--pt-text-muted);
    opacity: 0.5;
}

.pt-ad-card-content {
    padding: 16px;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.pt-ad-card-link {
    text-decoration: none;
    color: inherit;
    margin-bottom: 8px;
}

.pt-ad-card-title {
    font-family: var(--pt-font);
    font-size: 16px;
    font-weight: 600;
    margin: 0 0 8px 0;
    color: var(--pt-text);
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
    transition: color 0.2s ease;
}

.pt-ad-card:hover .pt-ad-card-title {
    color: var(--pt-primary);
}

.pt-ad-card-price {
    font-family: var(--pt-font);
    font-size: 24px;
    font-weight: 700;
    color: var(--pt-primary);
    margin: 0 0 12px 0;
    line-height: 1.2;
}

.pt-ad-specs-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
    margin-bottom: 12px;
}

.pt-spec-item {
    display: flex;
    align-items: center;
    gap: 6px;
}

.pt-spec-icon {
    color: var(--pt-accent);
    flex-shrink: 0;
    width: 14px;
    height: 14px;
}

.pt-spec-text {
    font-family: var(--pt-font);
    font-size: 13px;
    font-weight: 500;
    color: #5a5a5a;
    line-height: 1.4;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pt-ad-card-footer {
    margin-top: auto;
    padding-top: 12px;
    border-top: 1px solid var(--pt-border-light);
}

.pt-ad-card-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-family: var(--pt-font);
    font-size: 12px;
    color: #8a8a8a;
    font-weight: 400;
    margin-bottom: 12px;
}

.pt-ad-card-location {
    display: flex;
    align-items: center;
    gap: 4px;
    line-height: 1.4;
}

.pt-ad-card-location svg {
    color: #8a8a8a;
    flex-shrink: 0;
    width: 12px;
    height: 12px;
}

.pt-ad-card-location span {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 120px;
}

.pt-ad-card-date {
    color: #8a8a8a;
    font-size: 12px;
}

/* Action Buttons */
.pt-ad-card-actions {
    display: flex;
    gap: 8px;
    width: 100%;
}

.pt-action-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 10px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-family: var(--pt-font);
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
}

.pt-action-btn svg {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}

.pt-action-btn-call {
    background: var(--pt-primary);
    color: #ffffff;
}

.pt-action-btn-call:hover {
    background: var(--pt-primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(13, 115, 119, 0.3);
}

.pt-action-btn-message {
    background: transparent;
    color: var(--pt-primary);
    border: 1.5px solid var(--pt-primary);
}

.pt-action-btn-message:hover {
    background: rgba(13, 115, 119, 0.05);
    border-color: var(--pt-primary-dark);
    color: var(--pt-primary-dark);
}

.pt-empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--pt-text-muted);
    grid-column: 1 / -1;
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

/* Pagination */
.pt-pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-top: 40px;
    font-family: var(--pt-font);
}

.pt-pagination ul {
    display: flex;
    list-style: none;
    padding: 0;
    margin: 0;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: center;
}

.pt-pagination li {
    margin: 0;
}

.pt-pagination a,
.pt-pagination span {
    display: block;
    padding: 10px 16px;
    border-radius: var(--pt-radius);
    text-decoration: none;
    font-weight: 600;
    transition: var(--pt-transition);
    border: 1px solid var(--pt-border-light);
}

.pt-pagination a {
    color: var(--pt-text);
    background: var(--pt-surface);
}

.pt-pagination a:hover {
    background: var(--pt-primary);
    color: var(--pt-text-inverse);
    border-color: var(--pt-primary);
}

.pt-pagination .current {
    background: var(--pt-primary);
    color: var(--pt-text-inverse);
    border-color: var(--pt-primary);
}

.pt-pagination .prev,
.pt-pagination .next {
    font-weight: 700;
}

/* Loading Indicator */
.pt-loading {
    text-align: center;
    padding: 40px 20px;
    font-family: var(--pt-font);
}

.pt-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid var(--pt-border-light);
    border-top-color: var(--pt-primary);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin: 0 auto 15px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.pt-loading p {
    color: var(--pt-text-secondary);
    font-weight: 500;
    margin: 0;
}

@media (max-width: 1024px) {
    .pt-ads-grid {
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
    }
}

@media (max-width: 768px) {
    .pt-ads-archive {
        padding: 16px;
    }
    
    .pt-archive-header-content {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .pt-archive-controls {
        flex-direction: column;
        align-items: flex-start;
        width: 100%;
    }
    
    .pt-sort-select,
    .pt-per-page-select {
        min-width: 140px;
    }
    
    .pt-archive-title {
        font-size: 28px;
    }
    
    .pt-ads-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
.pt-ad-card-image-wrapper {
        height: 180px;
    }
    
    .pt-ad-card-content {
        padding: 12px;
    }
    
    .pt-ad-card-title {
        font-size: 15px;
    }
    
    .pt-ad-card-price {
        font-size: 20px;
    }
    
    .pt-ad-specs-grid {
        gap: 6px;
        margin-bottom: 10px;
    }
    
    .pt-spec-text {
        font-size: 12px;
    }
    
    .pt-ad-card-meta {
        font-size: 11px;
        margin-bottom: 10px;
    }
    
    .pt-action-btn {
        padding: 8px 12px;
        font-size: 12px;
        gap: 4px;
    }
    
    .pt-action-btn svg {
        width: 14px;
        height: 14px;
    }
    
    .pt-pagination ul {
        gap: 4px;
    }
    
    .pt-pagination a,
    .pt-pagination span {
        padding: 8px 12px;
        font-size: 14px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('pt-ads-container');
    const pagination = document.getElementById('pt-pagination-container');
    const loading = document.getElementById('pt-loading');
    const perPageSelect = document.getElementById('pt-per-page-select');
    const sortSelect = document.getElementById('pt-sort-select');
    
    // Handle per-page change
    if (perPageSelect) {
        perPageSelect.addEventListener('change', function() {
            const perPage = this.value;
            const url = new URL(window.location.href);
            url.searchParams.set('per_page', perPage);
            url.searchParams.delete('paged'); // Reset to page 1
            window.location.href = url.toString();
        });
    }
    
    // Handle sort change
    if (sortSelect) {
        sortSelect.addEventListener('change', function() {
            const sort = this.value;
            const url = new URL(window.location.href);
            url.searchParams.set('sort', sort);
            url.searchParams.delete('paged'); // Reset to page 1
            window.location.href = url.toString();
        });
    }
    
    if (!pagination) return;
    
    // Handle pagination clicks
    pagination.addEventListener('click', function(e) {
        const link = e.target.closest('a');
        if (!link || link.classList.contains('current')) return;
        
        e.preventDefault();
        
        const url = new URL(link.href);
        const page = url.searchParams.get('paged') || 1;
        
        loadPage(page);
    });
    
    function loadPage(page) {
        const perPage = perPageSelect ? perPageSelect.value : <?php echo $per_page; ?>;
        const sort = sortSelect ? sortSelect.value : '<?php echo esc_js($sort); ?>';
        // Show loading
        loading.style.display = 'block';
        container.style.opacity = '0.5';
        container.style.pointerEvents = 'none';
        
        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
        
        // AJAX request
        const formData = new FormData();
        formData.append('action', 'pt_load_ads_archive');
        formData.append('page', page);
        formData.append('per_page', perPage);
        formData.append('sort', sort);
        formData.append('nonce', '<?php echo wp_create_nonce('pt_archive_nonce'); ?>');
        formData.append('_ajax_nonce', '<?php echo wp_create_nonce('pt_archive_nonce'); ?>');
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update container
                container.innerHTML = data.data.html;
                
                // Update pagination
                if (pagination && data.data.pagination) {
                    pagination.innerHTML = data.data.pagination;
                }
                
                // Update URL without reload
                const newUrl = new URL(window.location.href);
                if (page > 1) {
                    newUrl.searchParams.set('paged', page);
                } else {
                    newUrl.searchParams.delete('paged');
                }
                // Keep per_page and sort parameters
                if (perPage && perPage !== '12') {
                    newUrl.searchParams.set('per_page', perPage);
                } else {
                    newUrl.searchParams.delete('per_page');
                }
                if (sort && sort !== 'date_desc') {
                    newUrl.searchParams.set('sort', sort);
                } else {
                    newUrl.searchParams.delete('sort');
                }
                window.history.pushState({ page: page, perPage: perPage, sort: sort }, '', newUrl);
                
                // Update archive count if exists
                const countEl = document.querySelector('.pt-archive-count');
                if (countEl && data.data.count) {
                    countEl.textContent = data.data.count;
                }
            } else {
                alert('حدث خطأ أثناء تحميل الصفحة');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('حدث خطأ أثناء تحميل الصفحة');
        })
        .finally(() => {
            loading.style.display = 'none';
            container.style.opacity = '1';
            container.style.pointerEvents = 'auto';
        });
    }
    
    // Handle browser back/forward buttons
    window.addEventListener('popstate', function(e) {
        const url = new URL(window.location.href);
        const page = url.searchParams.get('paged') || 1;
        const perPage = url.searchParams.get('per_page') || '12';
        const sort = url.searchParams.get('sort') || 'date_desc';
        if (perPageSelect && perPageSelect.value !== perPage) {
            perPageSelect.value = perPage;
        }
        if (sortSelect && sortSelect.value !== sort) {
            sortSelect.value = sort;
        }
        loadPage(page);
    });
});
</script>
