<?php
/**
 * Single Ad Display Template
 * Shortcode: [single_ad id="123"]
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get ad ID from shortcode attribute or URL parameter
$ad_id = isset($ad_id) ? intval($ad_id) : 0;
if (!$ad_id && isset($_GET['ad_id'])) {
    $ad_id = intval($_GET['ad_id']);
}
if (!$ad_id) {
    $ad_id = get_the_ID();
}

// Get the ad post
$ad = get_post($ad_id);
if (!$ad || $ad->post_type !== 'ads') {
    echo '<div class="pt-error pt-single-ad-error">الإعلان غير موجود</div>';
    return;
}

// Get meta fields
$short_desc = get_post_meta($ad_id, 'short_desc', true);
$price = get_post_meta($ad_id, 'price_ads', true);
$contact_number = get_post_meta($ad_id, 'contact_number', true);
$whatsapp = get_post_meta($ad_id, 'whatsapp', true);
$advertisers_name = get_post_meta($ad_id, 'advertisers_name', true);
$ad_location = get_post_meta($ad_id, 'ad_location', true);
$trader_link_raw = get_post_meta($ad_id, 'trader_link', true);
// Fallback: check for underscore prefix version (JetEngine sometimes uses this)
if (empty($trader_link_raw)) {
    $trader_link_raw = get_post_meta($ad_id, '_trader_link', true);
}
// JetEngine may store relations as arrays or serialized data
if (is_array($trader_link_raw)) {
    $trader_link = reset($trader_link_raw); // Get first element
} else {
    $trader_link = $trader_link_raw;
}
$media = get_post_meta($ad_id, 'media', true);

// Get featured image
$featured_image_id = get_post_thumbnail_id($ad_id);
$featured_image_url = $featured_image_id ? wp_get_attachment_image_url($featured_image_id, 'large') : '';

// Process gallery images
$gallery_images = [];
if (!empty($media)) {
    $media_ids = is_array($media) ? $media : explode(',', $media);
    foreach (array_filter(array_map('intval', $media_ids)) as $img_id) {
        $img_url = wp_get_attachment_image_url($img_id, 'large');
        $thumb_url = wp_get_attachment_image_url($img_id, 'thumbnail');
        if ($img_url) {
            $gallery_images[] = [
                'id' => $img_id,
                'full' => $img_url,
                'thumb' => $thumb_url
            ];
        }
    }
}

// Add featured image to gallery if exists and not already included
if ($featured_image_url && $featured_image_id) {
    $has_featured = false;
    foreach ($gallery_images as $img) {
        if ($img['id'] == $featured_image_id) {
            $has_featured = true;
            break;
        }
    }
    if (!$has_featured) {
        array_unshift($gallery_images, [
            'id' => $featured_image_id,
            'full' => $featured_image_url,
            'thumb' => wp_get_attachment_image_url($featured_image_id, 'thumbnail')
        ]);
    }
}

// Main image (first gallery image or featured)
$main_image = !empty($gallery_images) ? $gallery_images[0]['full'] : $featured_image_url;

// Get linked trader info from trader_link field (can be URL or post ID)
$trader_info = null;
if ($trader_link) {
    $trader_id = 0;

    // Check if it's a post ID
    if (is_numeric($trader_link)) {
        $trader_id = intval($trader_link);
    } else {
        // It's a URL - try url_to_postid first
        $trader_id = url_to_postid($trader_link);

        // Fallback: extract slug from URL and find trader by slug
        if (!$trader_id) {
            $url_path = trim(parse_url($trader_link, PHP_URL_PATH), '/');
            $path_parts = explode('/', $url_path);
            // Get the last non-empty segment
            $path_parts = array_filter($path_parts);
            $trader_slug = end($path_parts);
            // Decode URL-encoded characters (Arabic slugs)
            $trader_slug = urldecode($trader_slug);

            if ($trader_slug) {
                $trader_query = get_posts([
                    'name' => $trader_slug,
                    'post_type' => 'trader',
                    'post_status' => 'publish',
                    'numberposts' => 1
                ]);
                if (!empty($trader_query)) {
                    $trader_id = $trader_query[0]->ID;
                }
            }
        }
    }

    if ($trader_id) {
        $trader_post = get_post($trader_id);
        if ($trader_post && $trader_post->post_type === 'trader') {
            $trader_logo_id = get_post_meta($trader_id, 'logo', true);
            
            // Generate trader profile URL
            // Try to find a page with trader_profile shortcode
            global $wpdb;
            $profile_page_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE (post_content LIKE %s OR post_content LIKE %s)
                 AND post_status = 'publish'
                 AND post_type = 'page'
                 LIMIT 1",
                '%[trader_profile%',
                '%trader_profile%'
            ));
            
            if ($profile_page_id) {
                // Use the page with shortcode and add trader_id parameter
                $trader_profile_url = add_query_arg('trader_id', $trader_id, get_permalink($profile_page_id));
            } else {
                // Fallback: use trader permalink (single trader post) or create URL with query param
                $trader_profile_url = add_query_arg('trader_id', $trader_id, get_permalink($trader_id));
            }
            
            $trader_info = [
                'name' => $trader_post->post_title,
                'logo' => $trader_logo_id ? wp_get_attachment_image_url($trader_logo_id, 'thumbnail') : '',
                'url' => $trader_profile_url
            ];
        }
    }
}

// Calculate days since posted
$post_date = strtotime($ad->post_date);
$days_ago = floor((time() - $post_date) / 86400);
if ($days_ago == 0) {
    $date_label = 'اليوم';
} elseif ($days_ago == 1) {
    $date_label = 'أمس';
} else {
    $date_label = sprintf('منذ %d يوم', $days_ago);
}

// Get similar ads
$similar_ads = new WP_Query([
    'post_type' => 'ads',
    'posts_per_page' => 4,
    'post__not_in' => [$ad_id],
    'post_status' => 'publish',
    'orderby' => 'date',
    'order' => 'DESC'
]);
?>

<div class="pt-single-ad" dir="rtl">
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
        <span class="pt-breadcrumb-current"><?php echo esc_html(wp_trim_words($ad->post_title, 5)); ?></span>
    </nav>

    <div class="pt-ad-layout">
        <!-- Left Column: Media & Details -->
        <div class="pt-ad-main">
            <!-- Image Gallery -->
            <div class="pt-ad-gallery">
                <div class="pt-ad-main-image">
                    <?php if ($main_image): ?>
                        <img id="pt-main-img" src="<?php echo esc_url($main_image); ?>" alt="<?php echo esc_attr($ad->post_title); ?>">
                    <?php else: ?>
                        <div class="pt-ad-no-image">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                <polyline points="21 15 16 10 5 21"></polyline>
                            </svg>
                            <span>لا توجد صورة</span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (count($gallery_images) > 1): ?>
                <div class="pt-ad-thumbnails">
                    <?php
                    $visible_count = min(5, count($gallery_images));
                    $remaining = count($gallery_images) - $visible_count;

                    for ($i = 0; $i < $visible_count; $i++):
                        $img = $gallery_images[$i];
                        $is_active = $i === 0 ? 'pt-thumb-active' : '';
                    ?>
                        <div class="pt-ad-thumb <?php echo $is_active; ?>" data-full="<?php echo esc_url($img['full']); ?>">
                            <img src="<?php echo esc_url($img['thumb']); ?>" alt="">
                            <?php if ($i === $visible_count - 1 && $remaining > 0): ?>
                                <div class="pt-thumb-more">+<?php echo $remaining; ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Specifications -->
            <section class="pt-ad-section pt-ad-specs">
                <h3 class="pt-section-title">المواصفات</h3>
                <div class="pt-specs-grid">
                    <?php if ($price): ?>
                    <div class="pt-spec-item">
                        <span class="pt-spec-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="1" x2="12" y2="23"></line>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                            </svg>
                        </span>
                        <div>
                            <p class="pt-spec-label">السعر</p>
                            <p class="pt-spec-value"><?php echo esc_html($price); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($ad_location): ?>
                    <div class="pt-spec-item">
                        <span class="pt-spec-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                        </span>
                        <div>
                            <p class="pt-spec-label">الموقع</p>
                            <p class="pt-spec-value"><?php echo esc_html($ad_location); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="pt-spec-item">
                        <span class="pt-spec-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                        </span>
                        <div>
                            <p class="pt-spec-label">تاريخ النشر</p>
                            <p class="pt-spec-value"><?php echo esc_html($date_label); ?></p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Description -->
            <section class="pt-ad-section pt-ad-description">
                <h3 class="pt-section-title">الوصف</h3>
                <?php if ($short_desc): ?>
                    <p class="pt-ad-short-desc"><?php echo esc_html($short_desc); ?></p>
                <?php endif; ?>
                <div class="pt-ad-content">
                    <?php echo wp_kses_post($ad->post_content); ?>
                </div>
            </section>

            <!-- Location Section -->
            <?php if ($ad_location): ?>
            <section class="pt-ad-section pt-ad-location-section">
                <div class="pt-location-header">
                    <h3 class="pt-section-title">الموقع</h3>
                    <div class="pt-location-text">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                        <?php echo esc_html($ad_location); ?>
                    </div>
                </div>
                <div class="pt-location-map-placeholder">
                    <div class="pt-map-dot"></div>
                    <span>المنطقة التقريبية</span>
                </div>
            </section>
            <?php endif; ?>
        </div>

        <!-- Right Column: Sticky Sidebar -->
        <div class="pt-ad-sidebar">
            <div class="pt-ad-sidebar-sticky">
                <!-- Price & CTAs Card -->
                <div class="pt-ad-price-card">
                    <div class="pt-price-header">
                        <h1 class="pt-ad-title"><?php echo esc_html($ad->post_title); ?></h1>
                        <div class="pt-price-row">
                            <span class="pt-ad-price"><?php echo $price ? esc_html($price) . ' ' : 'السعر عند الاتصال'; ?></span>
                        </div>
                    </div>

                    <div class="pt-ad-actions">
                        <?php if ($trader_link): ?>
                        <a href="<?php echo esc_url($trader_link); ?>" class="pt-btn pt-btn-profile pt-btn-full">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            عرض ملف المعلن
                        </a>
                        <?php endif; ?>

                        <?php if ($contact_number || $whatsapp): ?>
                        <div class="pt-btn-group">
                            <?php if ($contact_number): ?>
                            <a href="tel:<?php echo esc_attr($contact_number); ?>" class="pt-btn pt-btn-primary">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                </svg>
                                اتصل الآن
                            </a>
                            <?php endif; ?>

                            <?php if ($whatsapp): ?>
                            <a href="https://wa.me/<?php echo esc_attr(preg_replace('/[^0-9]/', '', $whatsapp)); ?>?text=<?php echo urlencode('مرحباً، أنا مهتم بالإعلان: ' . $ad->post_title); ?>" target="_blank" class="pt-btn pt-btn-whatsapp">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                </svg>
                                واتساب
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="pt-ad-meta-actions">
                        <div class="pt-meta-buttons">
                            <button class="pt-meta-btn" id="pt-share-btn" data-title="<?php echo esc_attr($ad->post_title); ?>">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="18" cy="5" r="3"></circle>
                                    <circle cx="6" cy="12" r="3"></circle>
                                    <circle cx="18" cy="19" r="3"></circle>
                                    <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                                    <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                                </svg>
                                <span>مشاركة</span>
                            </button>
                        </div>
                        <span class="pt-listed-date"><?php echo esc_html($date_label); ?></span>
                    </div>
                </div>

                <!-- Seller Profile Card -->
                <div class="pt-ad-seller-card">
                    <div class="pt-seller-header">
                        <h3>عن المعلن</h3>
                        <?php if ($trader_info): ?>
                        <a href="<?php echo esc_url($trader_info['url']); ?>" class="pt-view-profile">عرض الملف</a>
                        <?php endif; ?>
                    </div>

                    <?php if ($trader_info): ?>
                    <a href="<?php echo esc_url($trader_info['url']); ?>" class="pt-seller-info pt-seller-link">
                    <?php else: ?>
                    <div class="pt-seller-info">
                    <?php endif; ?>
                        <div class="pt-seller-avatar">
                            <?php if ($trader_info && $trader_info['logo']): ?>
                                <img src="<?php echo esc_url($trader_info['logo']); ?>" alt="<?php echo esc_attr($trader_info['name']); ?>">
                            <?php else: ?>
                                <div class="pt-avatar-placeholder">
                                    <?php echo mb_substr($advertisers_name ?: $ad->post_title, 0, 1); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="pt-seller-details">
                            <p class="pt-seller-name"><?php echo esc_html($trader_info ? $trader_info['name'] : $advertisers_name); ?></p>
                            <?php if ($trader_info): ?>
                            <div class="pt-seller-badge">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                            <polyline points="9 12 12 15 16 10"></polyline>
                            </svg>
                            معلن موثق
                    </div>  
                            <!-- <p class="pt-seller-member">عضو في حماة التجار</p> -->
                            <?php endif; ?>
                        </div>
                    <?php if ($trader_info): ?>
                    </a>
                    <?php else: ?>
                    </div>
                    <?php endif; ?>

                
                </div>

                <!-- Safety Tips -->
                <div class="pt-ad-safety-tips">
                    <h4>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        </svg>
                        نصائح الأمان
                    </h4>
                    <ul>
                        <li>قابل البائع في مكان عام ومضاء جيداً</li>
                        <li>افحص المنتج قبل الدفع</li>
                        <li>لا تدفع مقدماً قبل استلام المنتج</li>
                        <li>أبلغ عن أي سلوك مشبوه</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Similar Ads Section -->
    <?php if ($similar_ads->have_posts()): ?>
    <section class="pt-ad-section pt-similar-ads">
        <div class="pt-similar-header">
            <h3 class="pt-section-title">إعلانات مشابهة</h3>
        </div>
        <div class="pt-similar-grid">
            <?php while ($similar_ads->have_posts()): $similar_ads->the_post();
                $sim_price = get_post_meta(get_the_ID(), 'price_ads', true);
                $sim_location = get_post_meta(get_the_ID(), 'ad_location', true);
                $sim_thumb = get_the_post_thumbnail_url(get_the_ID(), 'medium');
            ?>
            <a href="<?php the_permalink(); ?>" class="pt-similar-card">
                <div class="pt-similar-image" style="background-image: url('<?php echo esc_url($sim_thumb ?: ''); ?>');">
                    <?php if (!$sim_thumb): ?>
                    <div class="pt-no-img-placeholder">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <polyline points="21 15 16 10 5 21"></polyline>
                        </svg>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="pt-similar-content">
                    <h4 class="pt-similar-title"><?php the_title(); ?></h4>
                    <?php if ($sim_price): ?>
                    <p class="pt-similar-price"><?php echo esc_html($sim_price); ?> </p>
                    <?php endif; ?>
                    <div class="pt-similar-meta">
                        <?php if ($sim_location): ?>
                        <span class="pt-similar-location">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            <?php echo esc_html($sim_location); ?>
                        </span>
                        <?php endif; ?>
                        <span class="pt-similar-date"><?php echo human_time_diff(get_the_time('U'), current_time('timestamp')); ?></span>
                    </div>
                </div>
            </a>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>
    </section>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Thumbnail click handler
    const thumbs = document.querySelectorAll('.pt-ad-thumb');
    const mainImg = document.getElementById('pt-main-img');

    thumbs.forEach(function(thumb) {
        thumb.addEventListener('click', function() {
            const fullUrl = this.getAttribute('data-full');
            if (mainImg && fullUrl) {
                mainImg.src = fullUrl;
                thumbs.forEach(t => t.classList.remove('pt-thumb-active'));
                this.classList.add('pt-thumb-active');
            }
        });
    });

    // Share button handler
    const shareBtn = document.getElementById('pt-share-btn');
    if (shareBtn) {
        shareBtn.addEventListener('click', function() {
            ptShareAd(this);
        });
    }
});

function ptShareAd(btn) {
    var url = window.location.href;
    var title = btn.getAttribute('data-title') || document.title;

    // Check if we're on a mobile device and Web Share is actually supported
    var isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    var hasWebShare = navigator.share && typeof navigator.share === 'function';

    // Only try Web Share on mobile devices, skip on desktop to avoid error modals
    if (hasWebShare && isMobile) {
        navigator.share({
            title: title,
            url: url
        }).catch(function(err) {
            // If user cancelled, don't do anything
            // If any other error (like "couldn't show sharing options"), fallback to copy
            if (err.name !== 'AbortError') {
                ptCopyToClipboard(btn, url);
            }
        });
    } else {
        // Desktop or Web Share not available - go straight to clipboard copy
        ptCopyToClipboard(btn, url);
    }
}

function ptCopyToClipboard(btn, url) {
    var span = btn.querySelector('span');
    var original = span ? span.textContent : 'مشاركة';

    // Modern clipboard API (requires HTTPS or localhost)
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(url).then(function() {
            if (span) {
                span.textContent = 'تم النسخ!';
                setTimeout(function() {
                    span.textContent = original;
                }, 2000);
            }
        }).catch(function(err) {
            // Fallback to execCommand method
            ptCopyFallback(btn, url, span, original);
        });
    } else {
        // Fallback for older browsers or non-secure contexts
        ptCopyFallback(btn, url, span, original);
    }
}

function ptCopyFallback(btn, url, span, original) {
    // Create temporary textarea element
    var textarea = document.createElement('textarea');
    textarea.value = url;
    textarea.style.position = 'fixed';
    textarea.style.left = '-999999px';
    textarea.style.top = '-999999px';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();

    try {
        var successful = document.execCommand('copy');
        if (successful && span) {
            span.textContent = 'تم النسخ!';
            setTimeout(function() {
                span.textContent = original;
            }, 2000);
        } else {
            // Last resort: show URL in alert
            alert('الرابط: ' + url);
        }
    } catch (err) {
        // If all methods fail, show URL in alert
        alert('الرابط: ' + url);
    } finally {
        document.body.removeChild(textarea);
    }
}
</script>
